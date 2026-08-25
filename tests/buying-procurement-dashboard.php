<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once __DIR__ . '/buying-procurement-test-helper.php';
require_once $root . '/company/admin/modules/buying-procurement/schema.php';
require_once $root . '/company/admin/modules/buying-procurement/core.php';
require_once $root . '/company/admin/modules/buying-procurement/forms.php';

foreach (['settings.php', 'suppliers.php', 'scorecards.php', 'dashboard.php', 'functions.php'] as $moduleFile) {
    $path = $root . '/company/admin/modules/buying-procurement/' . $moduleFile;
    if (is_file($path)) {
        require_once $path;
    }
}

buying_test_assert(
    function_exists('yovel_admin_buying_procurement_dashboard_data'),
    'Missing Task 3 Buying live-dashboard provider.'
);

yovel_admin_buying_schema();
$db = bx_db();
$forcedReadErrors = [];
$failedRead = yovel_admin_buying_dashboard_read_one(
    $db,
    'forced-error',
    'SELECT missing_dashboard_column FROM project_company_buying_supplier LIMIT 1',
    [],
    $forcedReadErrors
);
buying_test_assert($failedRead === null && isset($forcedReadErrors['forced-error']), 'Buying dashboard read failures fabricate a value or escape their section boundary.');
$ownerFixture = buying_test_temporary_company($db, 'Buying Dashboard Owner');
$relatedFixture = buying_test_temporary_company($db, 'Buying Dashboard Related');
$owner = $ownerFixture['company'];
$ownerAdmin = $ownerFixture['admin'];
$related = $relatedFixture['company'];
$relatedAdmin = $relatedFixture['admin'];

$summaryByKey = static function (array $summary): array {
    $indexed = [];
    foreach ($summary as $item) {
        $indexed[(string) ($item['key'] ?? '')] = $item;
    }
    return $indexed;
};

try {
    yovel_admin_buying_save_settings($db, $owner, $ownerAdmin, [
        'supplier_naming_mode' => 'SUPPLIER_NAME',
        'maintain_same_rate_action' => 'WARN',
    ]);
    yovel_admin_buying_save_settings($db, $related, $relatedAdmin, [
        'supplier_naming_mode' => 'SUPPLIER_NAME',
        'maintain_same_rate_action' => 'WARN',
    ]);

    $readySupplier = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'Dashboard Ready Supplier',
        'supplier_type' => 'COMPANY',
        'supplier_group_key' => bx_uuid(),
        'default_currency' => 'PHP',
        'payment_terms_key' => bx_uuid(),
        'email' => 'ready-supplier@example.test',
    ]);
    $heldSupplier = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'Dashboard Held Supplier',
        'supplier_type' => 'COMPANY',
    ]);
    yovel_admin_buying_set_supplier_status(
        $db,
        $owner,
        $ownerAdmin,
        (string) $heldSupplier['supplier_key'],
        'HOLD',
        ['hold_type' => 'ALL']
    );
    $gapSupplier = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'Dashboard Setup Gap Supplier',
        'supplier_type' => 'COMPANY',
    ]);
    $relatedSupplier = yovel_admin_buying_save_supplier($db, $related, $relatedAdmin, [
        'supplier_name' => 'Related Dashboard Supplier',
        'supplier_type' => 'COMPANY',
    ]);

    $scorecard = yovel_admin_buying_save_scorecard_definition($db, $owner, $ownerAdmin, [
        'supplier_key' => (string) $readySupplier['supplier_key'],
        'period_type' => 'MONTH',
        'weighting_expression' => 'QUALITY',
        'scorecard_status' => 'ACTIVE',
        'variables' => [
            ['variable_code' => 'QUALITY', 'variable_label' => 'Quality score', 'metric_path' => 'quality_score'],
        ],
        'criteria' => [
            ['criteria_code' => 'QUALITY', 'criteria_name' => 'Quality', 'max_score' => '100', 'weight_percentage' => '100', 'formula_expression' => 'QUALITY'],
        ],
        'standings' => [
            ['standing_code' => 'BLOCKED', 'standing_name' => 'Blocked', 'color_token' => 'destructive', 'minimum_score' => '0', 'maximum_score' => '60', 'prevent_rfqs' => '1', 'prevent_purchase_orders' => '1'],
            ['standing_code' => 'GOOD', 'standing_name' => 'Good', 'color_token' => 'success', 'minimum_score' => '60', 'maximum_score' => '100'],
        ],
    ]);
    $GLOBALS['yovel_admin_buying_scorecard_metric_provider'] = static fn (): array => ['quality_score' => 35];
    $period = yovel_admin_buying_calculate_scorecard_period(
        $db,
        $owner,
        $ownerAdmin,
        (string) $scorecard['scorecard_key'],
        '2026-08-01',
        '2026-08-31'
    );
    unset($GLOBALS['yovel_admin_buying_scorecard_metric_provider']);

    $dashboard = yovel_admin_buying_procurement_dashboard_data($owner, $ownerAdmin);
    buying_test_assert(
        array_keys($dashboard) === ['summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directories', 'dependencies'],
        'Buying dashboard envelope keys changed.'
    );
    $summary = $summaryByKey($dashboard['summary']);
    buying_test_assert((int) ($summary['active-suppliers']['value'] ?? -1) === 3, 'Buying dashboard active supplier total is not live.');
    buying_test_assert(($summary['active-suppliers']['availability'] ?? '') === 'AVAILABLE', 'Live supplier total is not marked available.');
    buying_test_assert((int) ($summary['score-exceptions']['value'] ?? -1) === 1, 'Buying dashboard score exception total is not live.');
    buying_test_assert(($summary['score-exceptions']['availability'] ?? '') === 'AVAILABLE', 'Score exceptions are not marked available.');
    buying_test_assert(($summary['material-requests']['availability'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Material requests do not expose their Inventory dependency.');
    buying_test_assert(array_key_exists('value', $summary['material-requests']) && $summary['material-requests']['value'] === null, 'Unavailable material requests fabricated a numeric value.');
    buying_test_assert(($summary['requests-for-quotation']['availability'] ?? '') === 'AVAILABLE', 'RFQ KPI is not live after the sourcing package was installed.');
    buying_test_assert((int) ($summary['requests-for-quotation']['value'] ?? -1) === 0, 'RFQ KPI does not expose the company-scoped live count.');
    buying_test_assert(($summary['requests-for-quotation']['href'] ?? '') === './?view=buying-procurement&section=request-for-quotation', 'RFQ KPI does not open the sourcing workspace.');
    buying_test_assert(($summary['purchase-orders']['availability'] ?? '') === 'NOT_IMPLEMENTED', 'Purchase-order KPI does not expose its implementation state.');
    buying_test_assert(($summary['purchase-receipts']['availability'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Purchase receipts do not expose their Inventory dependency.');

    $queueKeys = array_column($dashboard['queue'], 'key');
    buying_test_assert(in_array('supplier-hold-' . $heldSupplier['supplier_key'], $queueKeys, true), 'Held supplier is absent from the Buying queue.');
    buying_test_assert(in_array('supplier-setup-' . $gapSupplier['supplier_key'], $queueKeys, true), 'Supplier setup gap is absent from the Buying queue.');
    buying_test_assert(in_array('score-exception-' . $period['scorecard_period_key'], $queueKeys, true), 'Scorecard restriction is absent from the Buying queue.');
    buying_test_assert(count($dashboard['queue']) <= 10, 'Buying dashboard queue is not bounded.');

    buying_test_assert(count($dashboard['activity']) > 0 && count($dashboard['activity']) <= 8, 'Buying activity is empty or unbounded.');
    foreach ($dashboard['activity'] as $activity) {
        buying_test_assert((string) ($activity['actor_label'] ?? '') !== '', 'Buying activity omitted its actor.');
        buying_test_assert((string) ($activity['record_label'] ?? '') !== '', 'Buying activity omitted its record label.');
        buying_test_assert((string) ($activity['occurred_at'] ?? '') !== '', 'Buying activity omitted its timestamp.');
        buying_test_assert((string) ($activity['key'] ?? '') !== '', 'Buying activity omitted its stable key.');
    }
    buying_test_assert(
        !in_array((string) $relatedSupplier['supplier_key'], array_column($dashboard['activity'], 'record_key'), true),
        'Buying activity leaked a related-company supplier.'
    );

    $setup = array_column($dashboard['setup'], null, 'key');
    buying_test_assert(($setup['suppliers']['complete'] ?? false) === true, 'Supplier setup was not recognized.');
    buying_test_assert(($setup['buying-settings']['complete'] ?? false) === true, 'Persisted Buying Settings were not recognized.');
    buying_test_assert(($setup['supplier-scorecards']['complete'] ?? false) === true, 'Supplier scorecard setup was not recognized.');
    buying_test_assert(($setup['inventory-contract']['complete'] ?? true) === false, 'Missing Inventory contract was reported complete.');

    $shortcutKeys = array_column($dashboard['shortcuts'], 'key');
    buying_test_assert(in_array('suppliers', $shortcutKeys, true) && in_array('form-builder', $shortcutKeys, true) && in_array('requests-for-quotation', $shortcutKeys, true), 'Buying shortcuts omit Suppliers, RFQs, or Form Builder.');
    $directoryGroups = array_column($dashboard['directories'], 'group');
    buying_test_assert($directoryGroups === ['Reports', 'Masters'], 'Buying reports/masters directory groups changed.');
    $directoryText = json_encode($dashboard['directories'], JSON_THROW_ON_ERROR);
    buying_test_assert(str_contains($directoryText, 'Form Builder') && str_contains($directoryText, 'NOT_IMPLEMENTED'), 'Buying directory omits Form Builder or unavailable report states.');

    $dependencyStatus = array_column($dashboard['dependencies'], 'status', 'contract');
    buying_test_assert(($dependencyStatus['buying.suppliers.v1'] ?? '') === 'AVAILABLE', 'Supplier dashboard contract is unavailable.');
    buying_test_assert(($dependencyStatus['buying.scorecards.v1'] ?? '') === 'AVAILABLE', 'Scorecard dashboard contract is unavailable.');
    buying_test_assert(($dependencyStatus['inventory.buying-snapshot.v1'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Inventory dashboard contract status is inaccurate.');
    buying_test_assert(($dependencyStatus['buying.rfq.v1'] ?? '') === 'AVAILABLE', 'RFQ dashboard package status is inaccurate.');

    $relatedDashboard = yovel_admin_buying_procurement_dashboard_data($related, $relatedAdmin);
    $relatedSummary = $summaryByKey($relatedDashboard['summary']);
    buying_test_assert((int) ($relatedSummary['active-suppliers']['value'] ?? -1) === 1, 'Buying dashboard supplier total is not company isolated.');
    buying_test_assert((int) ($relatedSummary['score-exceptions']['value'] ?? -1) === 0, 'Buying dashboard score exceptions leaked across companies.');

    $data = yovel_admin_buying_procurement_data($owner, $ownerAdmin);
    buying_test_assert(($data['dashboard']['summary'][0]['key'] ?? '') !== '', 'Buying workspace data does not expose the live dashboard envelope.');
    $activeModuleSection = 'dashboard';
    $activeModuleMeta = yovel_admin_buying_procurement_sections()['dashboard'];
    $activeModuleData = $data;
    $companyName = (string) $owner['company_name'];
    ob_start();
    require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
    $markup = (string) ob_get_clean();
    foreach ([
        'data-buying-dashboard',
        'data-buying-dashboard-summary',
        'data-buying-dashboard-queue',
        'data-buying-dashboard-activity',
        'data-buying-dashboard-setup',
        'data-buying-dashboard-alerts',
        'data-buying-dashboard-shortcuts',
        'data-buying-dashboard-directories',
        'Active suppliers',
        'Score exceptions',
        'UNAVAILABLE_DEPENDENCY',
        'NOT_IMPLEMENTED',
        'Form Builder',
        'Reports',
        'Masters',
        'Show Tour',
        'data-buying-tour-target="summary"',
        'data-buying-tour-target="queue"',
        'data-buying-tour-target="activity"',
        'data-buying-tour-target="setup"',
        'data-buying-tour-target="alerts"',
        'data-buying-tour-target="directories"',
    ] as $marker) {
        buying_test_assert(str_contains($markup, $marker), 'Buying live dashboard is missing marker: ' . $marker);
    }
    buying_test_assert(str_contains($markup, "event.key === 'Tab'"), 'Buying guided tour does not trap keyboard focus.');
    buying_test_assert(str_contains($markup, "event.key === 'Escape'"), 'Buying guided tour does not close with Escape.');
    buying_test_assert(str_contains($markup, 'trigger.focus()'), 'Buying guided tour does not restore trigger focus.');
    buying_test_assert(strpos($markup, 'data-buying-main-panel') < strpos($markup, 'data-buying-tools-panel'), 'Buying dashboard no longer stacks main-first.');
    buying_test_assert(!str_contains($markup, 'href=""'), 'Buying dashboard renders a broken empty destination.');

    $providerSource = (string) file_get_contents($root . '/company/admin/modules/buying-procurement/dashboard.php');
    buying_test_assert(!str_contains($providerSource, 'project_company_inventory_'), 'Buying dashboard directly queries Inventory tables.');
    buying_test_assert(!str_contains($providerSource, 'project_company_finance_'), 'Buying dashboard directly queries Finance tables.');
    buying_test_assert(!str_contains($providerSource, 'project_company_operations_'), 'Buying dashboard directly queries Operations tables.');
    buying_test_assert(!str_contains($providerSource, 'yovel_admin_buying_schema('), 'Buying dashboard read provider performs schema writes.');
    buying_test_assert(str_contains($providerSource, 'LIMIT 8'), 'Buying dashboard activity is not source-bounded.');
    buying_test_assert(str_contains($providerSource, "admin_status = 'ACTIVE'"), 'Buying dashboard activity is not role-filtered to active administrators.');
} finally {
    unset($GLOBALS['yovel_admin_buying_scorecard_metric_provider']);
    buying_test_cleanup_temporary_company($db, $ownerFixture);
    buying_test_cleanup_temporary_company($db, $relatedFixture);
}

echo "Buying / Procurement live dashboard checks passed.\n";
