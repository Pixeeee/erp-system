<?php
declare(strict_types=1);

require __DIR__ . '/manufacturing-test-helper.php';

manufacturing_test_assert(
    function_exists('yovel_admin_manufacturing_dashboard_data'),
    'Manufacturing live dashboard provider is missing.'
);

$scopeA = manufacturing_test_create_scope('dashboard-a');
$scopeB = manufacturing_test_create_scope('dashboard-b');
try {
    yovel_admin_manufacturing_schema();
    yovel_admin_save_manufacturing_settings($scopeA['company'], $scopeA['admin'], [
        'allow_overproduction_percent' => '4.5000',
        'capacity_planning_enabled' => '1',
        'default_wip_warehouse_key' => '',
        'default_finished_goods_warehouse_key' => '',
        'notes' => 'Dashboard-ready settings',
    ]);
    yovel_admin_save_manufacturing_form($scopeA['company'], $scopeA['admin'], [
        'target_section' => 'boms',
        'form_title' => 'Dashboard BOM form',
        'form_description' => 'Published dashboard fixture',
        'form_status' => 'PUBLISHED',
        'schema' => yovel_admin_manufacturing_default_form_schemas()['BOM'],
    ]);

    $dashboard = yovel_admin_manufacturing_dashboard_data($scopeA['company'], $scopeA['admin']);
    $emptyDashboard = yovel_admin_manufacturing_dashboard_data($scopeB['company'], $scopeB['admin']);
    foreach (['summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directories', 'dependencies'] as $key) {
        manufacturing_test_assert(isset($dashboard[$key]) && is_array($dashboard[$key]), 'Manufacturing dashboard envelope is missing ' . $key . '.');
    }

    manufacturing_test_assert(($dashboard['foundation']['settings_configured'] ?? false) === true, 'Manufacturing settings readiness is not live.');
    manufacturing_test_assert(($dashboard['foundation']['published_form_count'] ?? 0) === 1, 'Manufacturing published Form Builder total is not live.');
    manufacturing_test_assert(($dashboard['foundation']['audit_count'] ?? 0) >= 2, 'Manufacturing audit readiness is not live.');
    manufacturing_test_assert(($emptyDashboard['foundation']['settings_configured'] ?? true) === false, 'Manufacturing settings leaked across companies.');
    manufacturing_test_assert(($emptyDashboard['foundation']['published_form_count'] ?? -1) === 0, 'Manufacturing forms leaked across companies.');
    manufacturing_test_assert(($emptyDashboard['foundation']['audit_count'] ?? -1) === 0, 'Manufacturing audit activity leaked across companies.');

    $summary = [];
    foreach ($dashboard['summary'] as $item) {
        $summary[(string) ($item['key'] ?? '')] = $item;
    }
    $expectedUnavailable = [
        'active-boms' => 'NOT_IMPLEMENTED',
        'production-plans' => 'NOT_IMPLEMENTED',
        'work-orders' => 'NOT_IMPLEMENTED',
        'job-cards' => 'NOT_IMPLEMENTED',
        'material-shortages' => 'UNAVAILABLE_DEPENDENCY',
        'capacity-exceptions' => 'AVAILABLE',
    ];
    manufacturing_test_assert(array_keys($summary) === array_keys($expectedUnavailable), 'Manufacturing production KPI order changed.');
    foreach ($expectedUnavailable as $key => $availability) {
        manufacturing_test_assert(($summary[$key]['availability'] ?? '') === $availability, 'Manufacturing KPI availability is wrong for ' . $key . '.');
        if ($availability === 'AVAILABLE') {
            manufacturing_test_assert(($summary[$key]['value'] ?? null) === '0', 'Manufacturing capacity KPI must use live owned downtime data.');
        } else {
            manufacturing_test_assert(($summary[$key]['value'] ?? null) === 'Unavailable', 'Manufacturing unavailable KPI must not fabricate numeric zero: ' . $key . '.');
        }
    }

    $dependencies = [];
    foreach ($dashboard['dependencies'] as $dependency) {
        $dependencies[(string) ($dependency['key'] ?? '')] = $dependency;
    }
    manufacturing_test_assert(($dependencies['item_lookup']['status'] ?? '') === 'AVAILABLE', 'Verified Inventory item lookup is not shown as available.');
    manufacturing_test_assert(($dependencies['item_valuation']['status'] ?? '') === 'AVAILABLE', 'Verified Inventory valuation must be available.');
    manufacturing_test_assert(($dependencies['cost_preview']['status'] ?? '') === 'AVAILABLE', 'Verified Finance costing must be shown as available.');
    manufacturing_test_assert(($dependencies['material_request']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Buying material request must remain explicitly unavailable.');

    manufacturing_test_assert(count($dashboard['activity']) >= 2 && count($dashboard['activity']) <= 10, 'Manufacturing dashboard activity must be live and bounded.');
    foreach ($dashboard['activity'] as $activity) {
        manufacturing_test_assert(($activity['actor_label'] ?? '') !== '' && ($activity['occurred_at'] ?? '') !== '', 'Manufacturing activity is missing actor or timestamp context.');
    }
    manufacturing_test_assert(count($emptyDashboard['activity']) === 0, 'Manufacturing activity leaked across companies.');
    manufacturing_test_assert(count(array_filter($dashboard['setup'], static fn (array $step): bool => !empty($step['complete']))) >= 3, 'Manufacturing setup progress did not use live foundation readiness.');
    manufacturing_test_assert(count(array_filter($dashboard['alerts'], static fn (array $alert): bool => ($alert['severity'] ?? '') === 'WARNING')) >= 1, 'Manufacturing dependency blockers are not surfaced as alerts.');

    $data = yovel_admin_manufacturing_data($scopeA['company'], $scopeA['admin'], 'dashboard');
    manufacturing_test_assert(isset($data['dashboard']) && $data['dashboard'] === $dashboard, 'Manufacturing workspace did not consume the live dashboard provider.');
    $markup = manufacturing_test_render($scopeA, 'dashboard');
    foreach (['summary', 'queue', 'activity', 'setup', 'alerts', 'dependencies', 'shortcuts', 'directories'] as $region) {
        manufacturing_test_assert(str_contains($markup, 'data-manufacturing-dashboard-' . $region), 'Manufacturing dashboard region is missing: ' . $region . '.');
    }
    manufacturing_test_assert(substr_count($markup, '>Unavailable<') >= 5, 'Manufacturing dashboard does not visibly preserve unavailable production states.');
    manufacturing_test_assert(str_contains($markup, 'aria-disabled="true"'), 'Unavailable Manufacturing destinations are not visibly disabled.');
    manufacturing_test_assert(str_contains($markup, 'data-manufacturing-tour-start'), 'Manufacturing dashboard Show Tour control is missing.');
    manufacturing_test_assert(str_contains($markup, 'data-manufacturing-tour') && str_contains($markup, 'role="dialog"'), 'Manufacturing dashboard tour dialog is missing.');
    manufacturing_test_assert(str_contains($markup, 'data-manufacturing-panels-end') && strpos($markup, 'data-manufacturing-tour hidden') > strpos($markup, 'data-manufacturing-panels-end'), 'Manufacturing dashboard tour must be a sibling after the scrolling panels.');
    manufacturing_test_assert(substr_count($markup, 'data-manufacturing-tour-target=') >= 6, 'Manufacturing dashboard tour needs at least six real targets.');
    manufacturing_test_assert(str_contains($markup, "event.key === 'Escape'") && str_contains($markup, 'trigger.focus()'), 'Manufacturing dashboard tour lacks Escape and focus restoration.');
    manufacturing_test_assert(str_contains($markup, 'builderx:manufacturing:' . $scopeA['company']['company_key_hash'] . ':dashboard-tour'), 'Manufacturing tour completion is not scoped per company and module.');
    manufacturing_test_assert(str_contains($markup, 'minmax(0, 12fr) minmax(16rem, 8fr)'), 'Manufacturing live dashboard lost the 12/8 workspace shell.');
    manufacturing_test_assert(str_contains($markup, 'height:max(32rem,calc(100dvh - 16rem))'), 'Manufacturing live dashboard panels must own desktop overflow.');
    manufacturing_test_assert(strpos($markup, 'data-manufacturing-main') < strpos($markup, 'data-manufacturing-tools'), 'Manufacturing live dashboard must stack main before tools.');
} finally {
    manufacturing_test_cleanup_scope($scopeA);
    manufacturing_test_cleanup_scope($scopeB);
}

echo "Manufacturing live dashboard tests passed.\n";
