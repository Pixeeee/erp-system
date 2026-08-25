<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

$company = operations_test_company('Operations Bulk Company');
$otherCompany = operations_test_company('Other Bulk Company');
$admin = operations_test_admin();
$inactiveAdmin = [...$admin, 'admin_key' => bx_uuid(), 'admin_status' => 'INACTIVE'];
operations_test_register_cleanup($company);
operations_test_register_cleanup($otherCompany);
yovel_admin_operations_schema();

$db = bx_db();
$db->Execute('CREATE TEMPORARY TABLE operations_owner_join_stub (stub_key CHAR(36) PRIMARY KEY, idempotency_key VARCHAR(160) NOT NULL, record_key VARCHAR(160) NOT NULL) ENGINE=InnoDB');
$calls = 0;
$provider = static function (ADOConnection $ownerDb, array $scope, array $actor, array $command) use (&$calls): array {
    $calls++;
    $ownerDb->Execute(
        'INSERT INTO operations_owner_join_stub (stub_key,idempotency_key,record_key) VALUES (?,?,?)',
        [bx_uuid(), $command['idempotency_key'], $command['record_key']]
    );
    return [
        'ok' => true,
        'owner_record_key' => (string) $command['record_key'],
        'status' => 'COMPLETED',
        'idempotency_key' => (string) $command['idempotency_key'],
        'read_back' => ['record_key' => (string) $command['record_key'], 'status' => 'UPDATED', 'api_token' => 'never-store-me'],
        'audit_key' => bx_uuid(),
        'errors' => [],
    ];
};
$providers = ['owners.bulk-command.v1' => $provider];
$input = [
    'idempotency_key' => 'bulk-customer-archive-20260825',
    'record_type' => 'Customer',
    'action_code' => 'ARCHIVE',
    'targets' => [
        ['record_key' => 'customer-001'],
        ['record_key' => 'customer-002'],
    ],
    'evidence_summary' => 'Confirmed archive requested by Operations.',
];

$completed = yovel_admin_operations_submit_bulk_job($db, $company, $admin, $input, $providers);
operations_test_assert(yovel_admin_is_uuid((string) ($completed['bulk_log_key'] ?? '')), 'Bulk submit did not return a stable log key.');
operations_test_assert((string) $completed['status'] === 'COMPLETED' && (int) $completed['completed_count'] === 2, 'Bulk submit did not complete every owner command.');
operations_test_assert(count($completed['details'] ?? []) === 2, 'Bulk log did not rehydrate per-record details.');
operations_test_assert($calls === 2, 'Bulk owner provider was not called exactly once per target.');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM operations_owner_join_stub') === 2, 'Owner provider writes did not join the committed bulk transaction.');
foreach ($completed['details'] as $detail) {
    operations_test_assert((string) $detail['action_status'] === 'COMPLETED', 'Bulk detail status was not read back.');
    operations_test_assert(!str_contains((string) $detail['read_back_json'], 'never-store-me'), 'Bulk evidence persisted a raw secret.');
    operations_test_assert((string) $detail['target_idempotency_key'] !== '', 'Bulk detail lost its stable idempotency key.');
}

$duplicate = yovel_admin_operations_submit_bulk_job($db, $company, $admin, $input, $providers);
operations_test_assert((string) $duplicate['bulk_log_key'] === (string) $completed['bulk_log_key'], 'Idempotent bulk retry created another log.');
operations_test_assert($calls === 2, 'Idempotent bulk retry repeated owner commands.');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=? AND idempotency_key=?', [$company['company_key_hash'], 'bulk:' . $input['idempotency_key']]) === 1, 'Bulk confirmation enqueued more than one job.');
operations_test_assert(yovel_admin_operations_bulk_log($otherCompany, (string) $completed['bulk_log_key']) === null, 'Bulk log leaked across company scope.');
operations_test_expect_exception(static fn () => yovel_admin_operations_submit_bulk_job($db, $company, $inactiveAdmin, $input, $providers), 'authorized');

$failureInput = [...$input, 'idempotency_key' => 'bulk-owner-rollback-20260825'];
$failureCalls = 0;
$failingProviders = ['owners.bulk-command.v1' => static function (ADOConnection $ownerDb, array $scope, array $actor, array $command) use (&$failureCalls): array {
    $failureCalls++;
    $ownerDb->Execute('INSERT INTO operations_owner_join_stub (stub_key,idempotency_key,record_key) VALUES (?,?,?)', [bx_uuid(), $command['idempotency_key'], $command['record_key']]);
    if ($failureCalls === 2) {
        throw new RuntimeException('Owner rejected target.');
    }
    return ['ok' => true, 'owner_record_key' => $command['record_key'], 'status' => 'COMPLETED', 'idempotency_key' => $command['idempotency_key'], 'read_back' => ['record_key' => $command['record_key']], 'audit_key' => bx_uuid(), 'errors' => []];
}];
$stubCountBeforeFailure = (int) $db->GetOne('SELECT COUNT(*) FROM operations_owner_join_stub');
operations_test_expect_exception(static fn () => yovel_admin_operations_submit_bulk_job($db, $company, $admin, $failureInput, $failingProviders), 'Owner rejected');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM operations_owner_join_stub') === $stubCountBeforeFailure, 'Owner failure did not roll back joined owner writes.');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_operations_bulk_log WHERE company_key_hash=? AND idempotency_key=?', [$company['company_key_hash'], $failureInput['idempotency_key']]) === 0, 'Owner failure left a partial Operations bulk log.');
operations_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=? AND idempotency_key=?', [$company['company_key_hash'], 'bulk:' . $failureInput['idempotency_key']]) === 0, 'Owner failure left a partial Operations job.');

$retryCallsBefore = $calls;
$retried = yovel_admin_operations_submit_bulk_job($db, $company, $admin, $failureInput, $providers);
operations_test_assert((string) $retried['idempotency_key'] === $failureInput['idempotency_key'], 'Bulk retry changed its stable idempotency key.');
operations_test_assert($calls === $retryCallsBefore + 2, 'Bulk retry did not execute the original target set once.');

$queued = yovel_admin_operations_submit_bulk_job($db, $company, $admin, [...$input, 'idempotency_key' => 'bulk-cancel-20260825', 'defer_execution' => true], $providers);
operations_test_assert((string) $queued['status'] === 'QUEUED', 'Deferred bulk submission was not queued.');
$cancelled = yovel_admin_operations_cancel_bulk_job($db, $company, $admin, (string) $queued['bulk_log_key'], 'Request superseded.');
operations_test_assert((string) $cancelled['status'] === 'CANCELLED' && (string) $cancelled['cancel_reason'] === 'Request superseded.', 'Bulk cancellation was not explicit or rehydrated.');

$GLOBALS['yovel_admin_operations_dependency_providers'] = $providers;
$postResult = yovel_admin_operations_handle_post($company, $admin, 'submit_operations_bulk', [
    'section' => 'bulk-processing', 'idempotency_key' => 'bulk-shared-post-20260825', 'record_type' => 'Customer',
    'action_code' => 'ARCHIVE', 'targets_json' => '[{"record_key":"customer-post-001"}]', 'evidence_summary' => 'Shared POST dispatch verification.',
]);
unset($GLOBALS['yovel_admin_operations_dependency_providers']);
operations_test_assert(array_keys($postResult) === ['message', 'section', 'query'], 'Bulk shared POST action returned the wrong envelope.');
operations_test_assert((string) $postResult['section'] === 'bulk-processing' && yovel_admin_is_uuid((string) ($postResult['query']['bulk_log'] ?? '')), 'Bulk shared POST action did not return its server key.');

echo "Operations bulk processing checks passed.\n";
