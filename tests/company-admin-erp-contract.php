<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/app/foundation.php';
require_once $projectRoot . '/company/admin/core/functions.php';
require_once $projectRoot . '/company/admin/modules/hr/functions.php';
require_once $projectRoot . '/company/admin/modules/sales-crm/functions.php';
require_once $projectRoot . '/company/admin/modules/accounting-finance/functions.php';

function erp_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$expectedViews = ['dashboard', 'platform', 'hr', 'sales-crm', 'accounting-finance'];
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
    "require_once dirname(__DIR__) . '/modules/platform/functions.php';",
    "require_once dirname(__DIR__) . '/modules/hr/functions.php';",
    "require_once dirname(__DIR__) . '/modules/sales-crm/functions.php';",
    "require_once dirname(__DIR__) . '/modules/accounting-finance/functions.php';",
] as $functionLoader) {
    $loaderPosition = strpos($appSource, $functionLoader);
    erp_contract_assert($loaderPosition !== false, 'Company admin function loader is missing: ' . $functionLoader);
    erp_contract_assert($loaderPosition < $controllerPosition, 'Company admin controller loads before module functions.');
}

$layoutSource = (string) file_get_contents($projectRoot . '/company/admin/views/layout.php');
foreach ([
    "modules/platform/views/workspace.php",
    "modules/hr/views/workspace.php",
    "modules/sales-crm/views/workspace.php",
    "modules/accounting-finance/views/workspace.php",
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
