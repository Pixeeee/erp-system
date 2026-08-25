<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

$ledger = json_decode(
    (string) file_get_contents(dirname(__DIR__) . '/docs/erpnext-parity/ledgers/operations.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$expectedContracts = [];
foreach ($ledger['rows'] as $row) {
    foreach ($row['dependencies'] as $dependency) {
        if (is_string($dependency) && str_ends_with($dependency, '.v1')) {
            $expectedContracts[$dependency] = true;
        }
    }
}

$catalog = yovel_admin_operations_dependency_contracts();
foreach (array_keys($expectedContracts) as $contractId) {
    operations_test_assert(isset($catalog[$contractId]), 'Operations contract allow-list is missing ' . $contractId . '.');
    operations_test_assert(
        in_array($catalog[$contractId]['mode'] ?? '', ['read', 'command', 'internal', 'ui'], true),
        'Operations contract has an invalid mode: ' . $contractId
    );
}
operations_test_assert(count($catalog) === count($expectedContracts), 'Operations contract catalog contains untracked contracts.');

$company = operations_test_company();
$admin = operations_test_admin();
$recordKey = bx_uuid();
$readProviders = [
    'platform.company-scope.v1' => static fn (array $scope, array $query): array => [
        'ok' => true,
        'company_key_hash' => (string) $scope['company_key_hash'],
        'records' => [['company_key' => (string) $scope['company_key'], 'label' => (string) ($query['label'] ?? 'Company')]],
        'errors' => [],
    ],
];
$read = yovel_admin_operations_call_read_contract(
    'platform.company-scope.v1',
    $company,
    ['label' => 'Scoped company'],
    $readProviders
);
operations_test_assert(($read['records'][0]['company_key'] ?? '') === $company['company_key'], 'Read contract lost the stable owner key.');

operations_test_expect_exception(
    static fn () => yovel_admin_operations_call_read_contract('unknown.contract.v1', $company, [], []),
    'not allow-listed'
);
operations_test_expect_exception(
    static fn () => yovel_admin_operations_call_read_contract('platform.company-scope.v1', $company, [], []),
    'provider is unavailable'
);
operations_test_expect_exception(
    static fn () => yovel_admin_operations_call_read_contract(
        'platform.company-scope.v1',
        $company,
        [],
        ['platform.company-scope.v1' => static fn (): array => [
            'ok' => true,
            'company_key_hash' => str_repeat('0', 64),
            'records' => [],
            'errors' => [],
        ]]
    ),
    'company scope'
);

$idempotencyKey = 'ops-contract-' . substr(hash('sha256', $recordKey), 0, 24);
$commandProviders = [
    'owners.bulk-command.v1' => static fn (ADOConnection $db, array $scope, array $actor, array $command): array => [
        'ok' => true,
        'owner_record_key' => (string) $command['record_key'],
        'status' => 'COMPLETED',
        'idempotency_key' => (string) $command['idempotency_key'],
        'read_back' => ['record_key' => (string) $command['record_key']],
        'audit_key' => bx_uuid(),
        'errors' => [],
    ],
];
$command = yovel_admin_operations_call_command_contract(
    'owners.bulk-command.v1',
    bx_db(),
    $company,
    $admin,
    ['record_key' => $recordKey, 'idempotency_key' => $idempotencyKey],
    $commandProviders
);
operations_test_assert(($command['owner_record_key'] ?? '') === $recordKey, 'Command contract lost the owner record key.');
operations_test_assert(($command['idempotency_key'] ?? '') === $idempotencyKey, 'Command contract changed its idempotency key.');
operations_test_assert(($command['read_back']['record_key'] ?? '') === $recordKey, 'Command contract read-back does not match the owner record.');

echo "Operations dependency contract checks passed.\n";
