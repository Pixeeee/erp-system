<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/buying-procurement/schema.php';
require_once $root . '/company/admin/modules/buying-procurement/core.php';
require_once __DIR__ . '/buying-procurement-test-helper.php';

$formsPath = $root . '/company/admin/modules/buying-procurement/forms.php';
$functionsPath = $root . '/company/admin/modules/buying-procurement/functions.php';
if (is_file($formsPath)) {
    require_once $formsPath;
}
if (is_file($functionsPath)) {
    require_once $functionsPath;
}

$expectedSections = [
    'dashboard',
    'suppliers',
    'material-requests',
    'request-for-quotation',
    'supplier-quotations',
    'purchase-orders',
    'purchase-receipts',
    'supplier-scorecards',
    'purchase-analytics',
    'reports',
    'buying-settings',
    'form-builder',
];
$sections = yovel_admin_buying_procurement_sections();
buying_test_assert(array_keys($sections) === $expectedSections, 'Buying / Procurement section order or slugs changed unexpectedly.');

$originalGet = $_GET;
try {
    $_GET['section'] = 'not-a-buying-section';
    buying_test_assert(yovel_admin_buying_procurement_section() === 'suppliers', 'Buying route does not use the registered suppliers default.');
    $_GET['section'] = 'purchase-orders';
    buying_test_assert(yovel_admin_buying_procurement_section() === 'purchase-orders', 'Buying route rejected a registered section.');
} finally {
    $_GET = $originalGet;
}

$db = bx_db();
$fixture = buying_test_company_admin_fixture($db);
$company = $fixture['company'];
$admin = $fixture['admin'];
$data = yovel_admin_buying_procurement_data($company, $admin);

buying_test_assert(($data['state'] ?? '') === 'ready', 'Buying workspace did not load a real server-backed state.');
buying_test_assert(($data['company_key_hash'] ?? '') === $company['company_key_hash'], 'Buying workspace data lost company scope.');
buying_test_assert(
    (int) ($data['counts']['suppliers'] ?? -1) === (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_buying_supplier WHERE company_key_hash = ? AND supplier_status <> 'DELETED'",
        [$company['company_key_hash']]
    ),
    'Buying supplier count is not server-backed.'
);
buying_test_assert(($data['dependencies']['shared']['available'] ?? false) === true, 'Shared Buying contracts are not reported as available.');
buying_test_assert(array_key_exists('inventory', $data['dependencies'] ?? []), 'Inventory dependency state is absent.');
buying_test_assert(array_key_exists('finance', $data['dependencies'] ?? []), 'Finance dependency state is absent.');
buying_test_assert(($data['dependencies']['inventory']['available'] ?? true) === false, 'Inventory dependency was reported ready without its service contract.');
buying_test_assert(($data['dependencies']['finance']['available'] ?? true) === false, 'Finance dependency was reported ready without its service contract.');

$activeModuleSection = 'suppliers';
$activeModuleMeta = $sections[$activeModuleSection];
$activeModuleData = $data;
$companyName = (string) $company['company_name'];

ob_start();
require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
$supplierMarkup = (string) ob_get_clean();

foreach ([
    'data-buying-workspace',
    'data-buying-main-panel',
    'data-buying-tools-panel',
    'grid-template-columns: minmax(0, 12fr) minmax(0, 8fr)',
    'No supplier records exist for this company.',
    'Inventory contract unavailable',
    'Finance contract unavailable',
    'name="module_view" value="buying-procurement"',
    'data-record-modal',
    'data-confirm-submit',
] as $marker) {
    buying_test_assert(str_contains($supplierMarkup, $marker), 'Buying supplier workspace is missing marker: ' . $marker);
}
buying_test_assert(strpos($supplierMarkup, 'data-buying-main-panel') < strpos($supplierMarkup, 'data-buying-tools-panel'), 'Buying workspace does not stack the main panel first.');
buying_test_assert(str_contains($supplierMarkup, 'data-record-modal-open="buying-supplier-modal"'), 'Add Supplier does not open its owned record modal.');
buying_test_assert(str_contains($supplierMarkup, 'Add Supplier'), 'Buying supplier workspace is missing the approved Add Supplier command.');
buying_test_assert(!str_contains(strtolower($supplierMarkup), 'queued'), 'Buying workspace contains a queued placeholder state.');
buying_test_assert(!str_contains($supplierMarkup, 'project_company_finance_'), 'Buying workspace directly references a Finance table.');
buying_test_assert(!str_contains($supplierMarkup, 'project_company_inventory_'), 'Buying workspace directly references an Inventory table.');

$activeModuleSection = 'form-builder';
$activeModuleMeta = $sections[$activeModuleSection];
ob_start();
require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
$builderMarkup = (string) ob_get_clean();
buying_test_assert(str_contains($builderMarkup, 'Buying Form Builder'), 'Buying Form Builder workspace is missing.');
buying_test_assert(str_contains($builderMarkup, 'name="form_target"'), 'Buying Form Builder target selector is missing.');
buying_test_assert(str_contains($builderMarkup, 'data-confirm-submit'), 'Buying Form Builder GET selection does not use the shared confirmation boundary.');
buying_test_assert(str_contains($builderMarkup, 'Protected workflow fields'), 'Buying Form Builder does not explain protected workflow fields.');

$source = (string) file_get_contents($functionsPath);
buying_test_assert(!str_contains($source, 'project_company_finance_'), 'Buying entry point directly queries Finance tables.');
buying_test_assert(!str_contains($source, 'project_company_inventory_'), 'Buying entry point directly queries Inventory tables.');

$beforeSeriesCount = (int) $db->GetOne(
    'SELECT COUNT(*) FROM project_company_buying_number_series WHERE company_key_hash = ?',
    [$company['company_key_hash']]
);
buying_test_expect_error(
    static fn () => yovel_admin_buying_procurement_handle_post(
        $company,
        $admin,
        'save_buying_supplier',
        ['action' => 'save_buying_supplier', 'section' => 'suppliers']
    ),
    'supplier name'
);
$afterSeriesCount = (int) $db->GetOne(
    'SELECT COUNT(*) FROM project_company_buying_number_series WHERE company_key_hash = ?',
    [$company['company_key_hash']]
);
buying_test_assert($beforeSeriesCount === $afterSeriesCount, 'Unsupported Buying POST handling mutated persisted state.');

$reviewResult = yovel_admin_buying_procurement_handle_post(
    $company,
    $admin,
    'review_buying_form_target',
    [
        'action' => 'review_buying_form_target',
        'module_view' => 'buying-procurement',
        'section' => 'suppliers',
        'form_target' => 'supplier',
    ]
);
buying_test_assert(
    $reviewResult === [
        'message' => 'Buying Form Builder target loaded.',
        'section' => 'form-builder',
        'query' => ['form_target' => 'supplier'],
    ],
    'Buying generic POST result does not follow the shared message/section/query contract.'
);

$activeModuleSection = 'suppliers';
$activeModuleMeta = $sections[$activeModuleSection];
$activeModuleFormState = [
    'section' => 'suppliers',
    'action' => 'review_buying_form_target',
    'input' => ['form_target' => 'supplier'],
    'error' => 'The selected target requires review <script>alert(1)</script>.',
];
ob_start();
require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
$rehydratedMarkup = (string) ob_get_clean();
buying_test_assert(str_contains($rehydratedMarkup, 'data-record-modal-open-on-load'), 'Buying validation failure does not reopen its record modal.');
buying_test_assert(str_contains($rehydratedMarkup, 'value="supplier" selected'), 'Buying validation failure did not rehydrate the selected target.');
buying_test_assert(str_contains($rehydratedMarkup, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Buying validation failure was not safely escaped.');
buying_test_assert(!str_contains($rehydratedMarkup, '<script>alert(1)</script>'), 'Buying validation failure exposed executable prior input/error content.');

echo "Buying / Procurement workspace checks passed.\n";
