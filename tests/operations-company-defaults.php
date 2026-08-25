<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

$company = operations_test_company('Operations Defaults Company');
$admin = operations_test_admin();
operations_test_register_cleanup($company);
yovel_admin_operations_schema();

$provider = static function (array $records): callable {
    return static fn (array $scope, array $query): array => [
        'ok' => true,
        'company_key_hash' => $scope['company_key_hash'],
        'records' => $records,
        'errors' => [],
    ];
};
$providers = [
    'platform.company-directory.v1' => $provider([['company_key' => $company['company_key'], 'company_name' => 'Operations Defaults Company', 'default_currency_key' => 'currency-php']]),
    'platform.company-branch-directory.v1' => $provider([['branch_key' => 'branch-main-001', 'branch_name' => 'Main Branch']]),
    'accounting-finance.company-defaults.v1' => $provider([['defaults_key' => 'finance-defaults-001', 'currency_key' => 'currency-php', 'receivable_account_key' => 'account-ar-001', 'secret' => 'finance-secret']]),
    'inventory-warehouse.company-defaults.v1' => $provider([['defaults_key' => 'inventory-defaults-001', 'warehouse_key' => 'warehouse-main-001', 'unit_key' => 'unit-piece']]),
    'assets-maintenance.company-defaults.v1' => $provider([['defaults_key' => 'asset-defaults-001', 'asset_account_key' => 'account-assets-001']]),
    'accounting-finance.currency-directory.v1' => $provider([['currency_key' => 'currency-php', 'currency_code' => 'PHP']]),
    'inventory-warehouse.unit-directory.v1' => $provider([['unit_key' => 'unit-piece', 'unit_code' => 'PCS']]),
];

$projection = yovel_admin_operations_company_defaults_projection($company, $providers);
foreach (array_keys($providers) as $contractId) {
    operations_test_assert(($projection['contracts'][$contractId]['status'] ?? '') === 'AVAILABLE', 'Company/default projection did not expose an available owner contract: ' . $contractId);
}
operations_test_assert(($projection['contracts']['platform.company-branch-directory.v1']['records'][0]['branch_key'] ?? '') === 'branch-main-001', 'Company/default projection changed a branch reference.');
operations_test_assert(!str_contains(json_encode($projection, JSON_THROW_ON_ERROR), 'finance-secret'), 'Company/default projection exposed a raw secret.');
operations_test_assert(count($projection['owner_actions'] ?? []) >= 5, 'Company/default projection omitted owner action links.');
foreach ($projection['owner_actions'] as $action) {
    operations_test_assert(str_starts_with((string) ($action['href'] ?? ''), '?view='), 'Owner action did not remain a module route.');
}

$missing = yovel_admin_operations_company_defaults_projection($company, []);
foreach ($missing['contracts'] as $state) {
    operations_test_assert(($state['status'] ?? '') === 'UNAVAILABLE' && ($state['blocking'] ?? true) === false, 'Missing defaults provider was not a non-blocking unavailable state.');
}
$malformed = $providers;
$malformed['accounting-finance.company-defaults.v1'] = static fn (array $scope, array $query): array => ['ok' => true, 'company_key_hash' => $scope['company_key_hash'], 'records' => 'invalid', 'errors' => []];
$malformedProjection = yovel_admin_operations_company_defaults_projection($company, $malformed);
operations_test_assert(($malformedProjection['contracts']['accounting-finance.company-defaults.v1']['status'] ?? '') === 'UNAVAILABLE', 'Malformed defaults envelope was accepted.');
$unstable = $providers;
$unstable['platform.company-branch-directory.v1'] = $provider([['branch_name' => 'No stable reference']]);
$unstableProjection = yovel_admin_operations_company_defaults_projection($company, $unstable);
operations_test_assert(($unstableProjection['contracts']['platform.company-branch-directory.v1']['status'] ?? '') === 'UNAVAILABLE', 'Owner record without a stable key was accepted.');

$data = yovel_admin_operations_data($company, $admin, $providers);
operations_test_assert(($data['company_defaults_projection']['contracts']['platform.company-directory.v1']['status'] ?? '') === 'AVAILABLE', 'Shared data provider omitted company/default projections.');
operations_test_assert(($data['authorization_projection']['contracts']['shared.identity-permission-directory.v1']['status'] ?? '') === 'UNAVAILABLE', 'Shared data provider did not tolerate an unrelated missing provider.');

$viewSource = file_get_contents($operationsTestRoot . '/company/admin/modules/operations/views/sections/company-defaults.php');
operations_test_assert(str_contains((string) $viewSource, 'owner_actions'), 'Company/default UI omitted owner action links.');
operations_test_assert(!str_contains((string) $viewSource, '<form'), 'Read-only company/default UI exposes a mutation form.');
$workspaceSource = file_get_contents($operationsTestRoot . '/company/admin/modules/operations/views/workspace.php');
operations_test_assert(str_contains((string) $workspaceSource, "'authorization-setup' => 'authorization.php'"), 'Authorization section is not mapped into the workspace.');
operations_test_assert(str_contains((string) $workspaceSource, "'company-defaults' => 'company-defaults.php'"), 'Company/default section is not mapped into the workspace.');
operations_test_assert(str_contains((string) $workspaceSource, 'minmax(0, 12fr) minmax(16rem, 8fr)'), 'OP-02 sections lost the 12/8 workspace shell.');

$source = '';
foreach (['setup.php', 'functions.php', 'schema.php', 'views/sections/authorization.php', 'views/sections/company-defaults.php'] as $path) {
    $source .= (string) @file_get_contents($operationsTestRoot . '/company/admin/modules/operations/' . $path);
}
operations_test_assert(!preg_match('/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+project_company_(?!operations_)/i', $source), 'OP-02 source writes a foreign owner table.');

echo "Operations company/default projection checks passed.\n";
