<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/app/foundation.php';
require_once $projectRoot . '/company/admin/core/functions.php';
require_once $projectRoot . '/company/admin/modules/shared/registry.php';
require_once $projectRoot . '/company/admin/modules/hr/functions.php';
require_once $projectRoot . '/company/admin/modules/sales-crm/functions.php';
require_once $projectRoot . '/company/admin/modules/accounting-finance/functions.php';

function erp_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$expectedModuleViews = [
    'hr',
    'accounting-finance',
    'sales-crm',
    'buying-procurement',
    'inventory-warehouse',
    'manufacturing',
    'projects',
    'support-service',
    'assets-maintenance',
    'operations',
    'compliance-localization',
];
$registry = yovel_admin_module_registry();
erp_contract_assert(array_keys($registry) === $expectedModuleViews, 'ERP module registry order or route ownership changed unexpectedly.');
erp_contract_assert(
    ($registry['compliance-localization']['default_section'] ?? '') === 'dashboard',
    'Compliance / Localization must open its implemented dashboard section.'
);
erp_contract_assert(
    ($registry['manufacturing']['default_section'] ?? '') === 'dashboard',
    'Manufacturing must open its implemented dashboard section.'
);
foreach ($expectedModuleViews as $dashboardView) {
    erp_contract_assert(
        ($registry[$dashboardView]['default_section'] ?? '') === 'dashboard',
        'Every web ERP module must open its dashboard: ' . $dashboardView
    );
}
foreach ($expectedModuleViews as $expectedView) {
    $route = yovel_admin_module_route($expectedView);
    erp_contract_assert(is_array($route), 'Registered ERP route does not resolve: ' . $expectedView);
    foreach (['view', 'label', 'function_file', 'workspace_file', 'sections_provider', 'data_provider', 'action_provider', 'default_section', 'owner'] as $field) {
        erp_contract_assert(trim((string) ($route[$field] ?? '')) !== '', 'ERP route metadata is missing ' . $field . ': ' . $expectedView);
    }
    erp_contract_assert(($route['view'] ?? '') === $expectedView, 'ERP route metadata changed its stable view: ' . $expectedView);
}

function erp_contract_fixture_action(array $company, array $admin, string $action, array $input): array
{
    return [
        'message' => 'Fixture handled ' . $action,
        'section' => (string) ($input['section'] ?? 'dashboard'),
        'query' => ['record' => 'fixture-key'],
    ];
}

$fixturePostResult = yovel_admin_module_post_result(
    ['view' => 'operations', 'default_section' => 'scheduled-jobs', 'sections_provider' => '', 'action_provider' => 'erp_contract_fixture_action'],
    ['company_key' => 'company'],
    ['admin_key' => 'admin'],
    'save_fixture',
    ['section' => 'system-alerts']
);
erp_contract_assert($fixturePostResult['message'] === 'Fixture handled save_fixture', 'Module POST result changed its handler message.');
erp_contract_assert($fixturePostResult['section'] === 'system-alerts', 'Module POST result changed its requested section.');
erp_contract_assert($fixturePostResult['query'] === ['record' => 'fixture-key'], 'Module POST result dropped redirect state.');
erp_contract_assert(
    yovel_admin_module_feature_section($registry['hr'], 'Dashboard') === 'dashboard',
    'HR feature labels do not retain their complete stable slug.'
);
erp_contract_assert(
    yovel_admin_module_feature_section($registry['compliance-localization'], 'Tax templates') === 'tax-templates',
    'Compliance feature labels do not retain their complete stable slug.'
);

$expectedViews = array_merge(['dashboard', 'platform'], $expectedModuleViews);
$originalGet = $_GET;
try {
    foreach ($expectedViews as $expectedView) {
        $_GET['view'] = $expectedView;
        erp_contract_assert(yovel_admin_view() === $expectedView, 'Existing ERP view no longer resolves: ' . $expectedView);
    }
    $_GET['view'] = 'unregistered-view';
    erp_contract_assert(yovel_admin_view() === 'dashboard', 'Unknown ERP views no longer fall back to dashboard.');
} finally {
    $_GET = $originalGet;
}

$expectedHrSections = [
    'dashboard',
    'employee-profiles',
    'departments',
    'job-positions',
    'teams',
    'attendance',
    'leave-requests',
    'leave-approvals',
    'payroll-access',
    'recruitment',
    'onboarding',
    'employee-documents',
    'hr-reports',
];
erp_contract_assert(array_keys(yovel_admin_hr_sections()) === $expectedHrSections, 'HR section slugs changed unexpectedly.');

$expectedFinanceSections = [
    'dashboard',
    'chart-of-accounts',
    'cost-centers',
    'accounting-dimensions',
    'sales-invoices',
    'purchase-invoices',
    'journal-entries',
    'payment-entries',
    'bank-accounts',
    'bank-reconciliation',
    'budgets',
    'period-closing',
    'general-ledger',
    'profit-loss',
    'balance-sheet',
    'cash-flow',
    'tax-reports',
];
erp_contract_assert(array_keys(yovel_admin_accounting_finance_sections()) === $expectedFinanceSections, 'Finance section slugs changed unexpectedly.');

$appSource = (string) file_get_contents($projectRoot . '/company/admin/bootstrap/app.php');
$controllerLoader = "require dirname(__DIR__) . '/bootstrap/controller.php';";
$controllerPosition = strpos($appSource, $controllerLoader);
erp_contract_assert($controllerPosition !== false, 'Company admin controller loader is missing.');
foreach ([
    "require_once dirname(__DIR__) . '/modules/shared/registry.php';",
    "require_once dirname(__DIR__) . '/modules/shared/forms.php';",
    "require_once dirname(__DIR__) . '/modules/platform/functions.php';",
    "require_once dirname(__DIR__) . '/modules/hr/functions.php';",
    "require_once dirname(__DIR__) . '/modules/sales-crm/functions.php';",
    "require_once dirname(__DIR__) . '/modules/accounting-finance/functions.php';",
] as $functionLoader) {
    $loaderPosition = strpos($appSource, $functionLoader);
    erp_contract_assert($loaderPosition !== false, 'Company admin function loader is missing: ' . $functionLoader);
    erp_contract_assert($loaderPosition < $controllerPosition, 'Company admin controller loads before module functions.');
}

$controllerSource = (string) file_get_contents($projectRoot . '/company/admin/bootstrap/controller.php');
erp_contract_assert(str_contains($controllerSource, 'yovel_admin_module_post_result('), 'Company admin controller does not dispatch registered module POST handlers.');

$layoutSource = (string) file_get_contents($projectRoot . '/company/admin/views/layout.php');
erp_contract_assert(str_contains($layoutSource, 'yovel_admin_module_route($activeView)'), 'Workspace rendering does not resolve through the ERP module registry.');
foreach ([
    "modules/platform/views/workspace.php",
    'require $activeModuleWorkspace;',
    "require __DIR__ . '/dashboard.php';",
] as $workspaceMarker) {
    erp_contract_assert(str_contains($layoutSource, $workspaceMarker), 'Existing workspace selection marker is missing: ' . $workspaceMarker);
}

$lockPath = $projectRoot . '/docs/erpnext-parity/shared-write-lock.json';
erp_contract_assert(is_file($lockPath), 'Shared ERP write-lock manifest is missing.');
$lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
erp_contract_assert(($lock['schema_version'] ?? '') === 'builderx.erp-shared-write-lock.v1', 'Shared ERP write-lock schema is invalid.');
erp_contract_assert(($lock['owner'] ?? '') === 'orchestration', 'Shared ERP paths are not owned by orchestration.');
erp_contract_assert(count($lock['prohibited_thread_ids'] ?? []) === 11, 'Shared ERP write lock does not protect against all module tasks.');

$requiredLockedPaths = [
    'app/foundation.php',
    'company/admin/bootstrap/',
    'company/admin/core/',
    'company/admin/views/layout.php',
    'company/admin/views/partials/',
    'company/admin/assets/css/admin.css',
    'company/admin/assets/js/admin-modal.js',
    'company/admin/modules/shared/',
    'tests/erpnext-',
];
erp_contract_assert(($lock['path_prefixes'] ?? []) === $requiredLockedPaths, 'Shared ERP locked paths changed or are incomplete.');

echo "Company admin ERP contract checks passed.\n";
