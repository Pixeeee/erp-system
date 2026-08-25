<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

$company = operations_test_company('Operations Deletion Company');
$otherCompany = operations_test_company('Other Deletion Company');
$admin = operations_test_admin();
$inactiveAdmin = [...$admin, 'admin_key' => bx_uuid(), 'admin_status' => 'INACTIVE'];
operations_test_register_cleanup($company);
operations_test_register_cleanup($otherCompany);
yovel_admin_operations_schema();
$db = bx_db();
$db->Execute('CREATE TEMPORARY TABLE operations_delete_owner_stub (stub_key CHAR(36) PRIMARY KEY, idempotency_key VARCHAR(160) NOT NULL, record_key VARCHAR(160) NOT NULL) ENGINE=InnoDB');

$directoryCalls = 0;
$deleteCalls = 0;
$providers = [
    'shared.record-type-directory.v1' => static function (array $scope, array $query) use (&$directoryCalls): array {
        $directoryCalls++;
        return ['ok' => true, 'company_key_hash' => $scope['company_key_hash'], 'records' => [[
            'record_type' => (string) $query['record_type'], 'owner_contract' => 'owners.governed-delete.v1', 'deletable' => true,
        ]], 'errors' => []];
    },
    'owners.governed-delete.v1' => static function (ADOConnection $ownerDb, array $scope, array $actor, array $command) use (&$deleteCalls): array {
        $deleteCalls++;
        $ownerDb->Execute('INSERT INTO operations_delete_owner_stub (stub_key,idempotency_key,record_key) VALUES (?,?,?)', [bx_uuid(), $command['idempotency_key'], $command['record_key']]);
        return ['ok' => true, 'owner_record_key' => $command['record_key'], 'status' => 'DELETED', 'idempotency_key' => $command['idempotency_key'], 'read_back' => ['record_key' => $command['record_key'], 'status' => 'DELETED', 'sql' => 'DELETE FROM forbidden'], 'audit_key' => bx_uuid(), 'errors' => []];
    },
];
$input = [
    'idempotency_key' => 'delete-test-orders-20260825',
    'record_type' => 'Test Order',
    'targets' => [['record_key' => 'order-001'], ['record_key' => 'order-002']],
    'consequence' => 'The owner will permanently remove two test orders after its own lock and authorization checks.',
];

$writeCountBeforePlan = (int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module LIKE 'project_company_operations_%'");
$plan = yovel_admin_operations_plan_deletion($company, $admin, $input, $providers);
operations_test_assert((int) $plan['target_count'] === 2 && (string) $plan['owner_contract'] === 'owners.governed-delete.v1', 'Deletion dry-run did not enumerate its owner contract and targets.');
operations_test_assert($directoryCalls === 1 && $deleteCalls === 0, 'Deletion dry-run called a mutating owner provider.');
operations_test_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module LIKE 'project_company_operations_%'") === $writeCountBeforePlan, 'Deletion dry-run wrote an audit or Operations row.');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM operations_delete_owner_stub') === 0, 'Deletion dry-run mutated owner data.');

$completed = yovel_admin_operations_submit_deletion($db, $company, $admin, $input, $providers);
operations_test_assert(yovel_admin_is_uuid((string) ($completed['request_key'] ?? '')), 'Deletion submit did not return a stable request key.');
operations_test_assert((string) $completed['status'] === 'COMPLETED' && (int) $completed['completed_count'] === 2, 'Governed deletion did not complete all owner commands.');
operations_test_assert(count($completed['items'] ?? []) === 1 && count($completed['targets'] ?? []) === 2, 'Deletion request did not rehydrate item and target rows.');
operations_test_assert($deleteCalls === 2, 'Deletion provider was not called exactly once per target.');
foreach ($completed['targets'] as $target) {
    operations_test_assert(!str_contains((string) $target['read_back_json'], 'DELETE FROM'), 'Deletion evidence persisted raw SQL.');
}

$duplicate = yovel_admin_operations_submit_deletion($db, $company, $admin, $input, $providers);
operations_test_assert((string) $duplicate['request_key'] === (string) $completed['request_key'], 'Idempotent deletion retry created another request.');
operations_test_assert($deleteCalls === 2, 'Idempotent deletion retry repeated owner commands.');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=? AND idempotency_key=?', [$company['company_key_hash'], 'delete:' . $input['idempotency_key']]) === 1, 'Deletion confirmation enqueued more than one job.');
operations_test_assert(yovel_admin_operations_deletion_request($otherCompany, (string) $completed['request_key']) === null, 'Deletion request leaked across company scope.');
operations_test_expect_exception(static fn () => yovel_admin_operations_submit_deletion($db, $company, $inactiveAdmin, $input, $providers), 'authorized');

$failureInput = [...$input, 'idempotency_key' => 'delete-owner-rollback-20260825'];
$failureCalls = 0;
$failingProviders = $providers;
$failingProviders['owners.governed-delete.v1'] = static function (ADOConnection $ownerDb, array $scope, array $actor, array $command) use (&$failureCalls): array {
    $failureCalls++;
    $ownerDb->Execute('INSERT INTO operations_delete_owner_stub (stub_key,idempotency_key,record_key) VALUES (?,?,?)', [bx_uuid(), $command['idempotency_key'], $command['record_key']]);
    if ($failureCalls === 2) throw new RuntimeException('Owner delete failed.');
    return ['ok' => true, 'owner_record_key' => $command['record_key'], 'status' => 'DELETED', 'idempotency_key' => $command['idempotency_key'], 'read_back' => ['record_key' => $command['record_key']], 'audit_key' => bx_uuid(), 'errors' => []];
};
$ownerRowsBeforeFailure = (int) $db->GetOne('SELECT COUNT(*) FROM operations_delete_owner_stub');
operations_test_expect_exception(static fn () => yovel_admin_operations_submit_deletion($db, $company, $admin, $failureInput, $failingProviders), 'Owner delete failed');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM operations_delete_owner_stub') === $ownerRowsBeforeFailure, 'Owner delete failure did not roll back joined writes.');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_operations_deletion_request WHERE company_key_hash=? AND idempotency_key=?', [$company['company_key_hash'], $failureInput['idempotency_key']]) === 0, 'Owner delete failure left a partial request.');

$queued = yovel_admin_operations_submit_deletion($db, $company, $admin, [...$input, 'idempotency_key' => 'delete-cancel-20260825', 'defer_execution' => true], $providers);
operations_test_assert((string) $queued['status'] === 'QUEUED', 'Deferred deletion request was not queued.');
$cancelled = yovel_admin_operations_cancel_deletion($db, $company, $admin, (string) $queued['request_key'], 'Legal hold applied.');
operations_test_assert((string) $cancelled['status'] === 'CANCELLED' && (string) $cancelled['cancel_reason'] === 'Legal hold applied.', 'Deletion cancellation was not explicit or rehydrated.');

$bulkSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/sections/bulk.php');
$deletionSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/sections/deletion.php');
foreach (['module_view', 'operations', 'record-modal.php', 'data-confirm-submit'] as $marker) {
    operations_test_assert(str_contains($bulkSource, $marker), 'Bulk section is missing modal/confirmation marker: ' . $marker);
    operations_test_assert(str_contains($deletionSource, $marker), 'Deletion section is missing modal/confirmation marker: ' . $marker);
}
operations_test_assert(str_contains($bulkSource, 'operationsPrior'), 'Bulk validation state is not rehydrated into its modal.');
operations_test_assert(str_contains($deletionSource, 'operationsPrior'), 'Deletion validation state is not rehydrated into its modal.');

$GLOBALS['yovel_admin_operations_dependency_providers'] = $providers;
$planPost = yovel_admin_operations_handle_post($company, $admin, 'plan_operations_deletion', [
    'section' => 'governed-deletion', 'idempotency_key' => 'delete-shared-plan-20260825', 'record_type' => 'Test Order',
    'targets_json' => '[{"record_key":"order-post-001"}]', 'consequence' => 'Permanently remove one owner-authorized test order.',
]);
operations_test_assert(array_keys($planPost) === ['message', 'section', 'query'] && (string) ($planPost['query']['plan'] ?? '') === '1', 'Deletion dry-run POST action returned the wrong envelope.');
$submitPost = yovel_admin_operations_handle_post($company, $admin, 'submit_operations_deletion', [
    'section' => 'governed-deletion', 'idempotency_key' => 'delete-shared-submit-20260825', 'record_type' => 'Test Order',
    'targets_json' => '[{"record_key":"order-post-002"}]', 'consequence' => 'Permanently remove one owner-authorized test order.',
]);
unset($GLOBALS['yovel_admin_operations_dependency_providers']);
operations_test_assert(array_keys($submitPost) === ['message', 'section', 'query'], 'Deletion shared POST action returned the wrong envelope.');
operations_test_assert((string) $submitPost['section'] === 'governed-deletion' && yovel_admin_is_uuid((string) ($submitPost['query']['request'] ?? '')), 'Deletion shared POST action did not return its server key.');

echo "Operations governed deletion checks passed.\n";
