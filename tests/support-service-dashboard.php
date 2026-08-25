<?php
declare(strict_types=1);

require_once __DIR__ . '/support-service-test-helper.php';

support_service_assert(
    function_exists('yovel_admin_support_service_dashboard_data'),
    'Support / Service dashboard provider is missing.'
);
support_service_assert(
    is_file(dirname(__DIR__) . '/company/admin/modules/support-service/views/dashboard.php'),
    'Support / Service dashboard view is missing.'
);
support_service_assert(
    is_file(dirname(__DIR__) . '/company/admin/modules/support-service/views/workspace.php'),
    'Support / Service workspace dispatcher is missing.'
);

$db = bx_db();
$primary = support_service_create_company($db, 'Dashboard Primary');
$foreign = support_service_create_company($db, 'Dashboard Foreign');
register_shutdown_function(static function () use ($db, $primary, $foreign): void {
    support_service_cleanup_company($db, $primary);
    support_service_cleanup_company($db, $foreign);
});

$company = $primary['company'];
$admin = $primary['admin'];
$foreignCompany = $foreign['company'];
$foreignAdmin = $foreign['admin'];

yovel_admin_support_service_schema();
$settings = yovel_admin_save_support_settings($company, $admin, [
    'expected_version' => '0',
    'close_issue_after_days' => '10',
    'portal_enabled' => '1',
    'track_service_level_agreement' => '1',
    'allow_resetting_service_level_agreement' => '0',
    'greeting_title' => 'Dashboard support',
    'greeting_subtitle' => 'Company-scoped dashboard fixture.',
]);
yovel_admin_save_support_search_source($company, $admin, [
    'expected_version' => '0',
    'source_name' => 'Dashboard Knowledge',
    'source_type' => 'LINK',
    'source_doctype' => 'Support Article',
    'result_title_field' => 'title',
    'result_preview_field' => 'summary',
    'result_route_field' => 'route',
    'source_status' => 'ACTIVE',
]);
yovel_admin_save_support_settings($foreignCompany, $foreignAdmin, [
    'expected_version' => '0',
    'close_issue_after_days' => '20',
    'portal_enabled' => '0',
    'track_service_level_agreement' => '0',
    'allow_resetting_service_level_agreement' => '0',
    'greeting_title' => 'Foreign dashboard support',
    'greeting_subtitle' => 'Must not appear in the primary dashboard.',
]);
yovel_admin_save_support_search_source($foreignCompany, $foreignAdmin, [
    'expected_version' => '0',
    'source_name' => 'Foreign Dashboard Knowledge',
    'source_type' => 'LINK',
    'source_doctype' => 'Foreign Support Article',
    'result_title_field' => 'title',
    'result_preview_field' => 'summary',
    'result_route_field' => 'route',
    'source_status' => 'ACTIVE',
]);

$before = [
    'settings' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_setting WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'sources' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_search_source WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'audit' => (int) $db->GetOne(
        "SELECT COUNT(*) FROM builder_audit_log
         WHERE module IN ('project_company_support_setting', 'project_company_support_search_source')
           AND new_values LIKE ?",
        ['%"company_key_hash":"' . $company['company_key_hash'] . '"%']
    ),
];

$dashboard = yovel_admin_support_service_dashboard_data($company, $admin);
foreach (['summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directories', 'dependencies'] as $key) {
    support_service_assert(is_array($dashboard[$key] ?? null), 'Dashboard envelope is missing ' . $key . '.');
}
$after = [
    'settings' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_setting WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'sources' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_support_search_source WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'audit' => (int) $db->GetOne(
        "SELECT COUNT(*) FROM builder_audit_log
         WHERE module IN ('project_company_support_setting', 'project_company_support_search_source')
           AND new_values LIKE ?",
        ['%"company_key_hash":"' . $company['company_key_hash'] . '"%']
    ),
];
support_service_assert($before === $after, 'Dashboard reads created or changed persisted records.');

$foundation = is_array($dashboard['foundation'] ?? null) ? $dashboard['foundation'] : [];
support_service_assert(($foundation['status'] ?? '') === 'AVAILABLE', 'Support dashboard foundation is not available.');
support_service_assert(!empty($foundation['settings_configured']), 'Support dashboard did not detect saved settings.');
support_service_assert((int) ($foundation['active_search_sources'] ?? -1) === 1, 'Support dashboard search-source count is not company scoped.');
support_service_assert((int) ($foundation['form_targets'] ?? 0) === 8, 'Support dashboard did not consume the Form Builder adapter.');
support_service_assert((int) ($foundation['audit_events'] ?? 0) === 2, 'Support dashboard audit count is not company scoped.');
support_service_assert((string) ($foundation['settings']['support_setting_key'] ?? '') === (string) $settings['support_setting_key'], 'Support dashboard did not read the scoped settings row.');

$expectedMetricContracts = [
    'open-tickets' => 'support.issue-dashboard.v1',
    'sla-risks' => 'support.sla-dashboard.v1',
    'customer-context' => 'sales-crm.support-customer-metrics.v1',
    'assignment-backlog' => 'operations.support-assignment-metrics.v1',
    'communications' => 'operations.support-communication-metrics.v1',
    'project-service-work' => 'projects.support-service-work-metrics.v1',
];
$summaryByKey = [];
foreach ($dashboard['summary'] as $metric) {
    $summaryByKey[(string) ($metric['key'] ?? '')] = $metric;
}
support_service_assert(array_keys($summaryByKey) === array_keys($expectedMetricContracts), 'Support dashboard metric keys are incomplete or unstable.');
foreach ($expectedMetricContracts as $key => $contract) {
    $metric = $summaryByKey[$key];
    support_service_assert(($metric['availability'] ?? '') === 'UNAVAILABLE_DEPENDENCY', $key . ' fabricated an available state.');
    support_service_assert(array_key_exists('value', $metric) && $metric['value'] === null, $key . ' fabricated a numeric zero.');
    support_service_assert(($metric['dependency'] ?? '') === $contract, $key . ' does not name its exact owner contract.');
}

$setupByKey = [];
foreach ($dashboard['setup'] as $step) {
    $setupByKey[(string) ($step['key'] ?? '')] = $step;
}
support_service_assert(!empty($setupByKey['support-settings']['complete']), 'Support settings setup step is not complete.');
support_service_assert(!empty($setupByKey['search-source']['complete']), 'Support search setup step is not complete.');
support_service_assert(!empty($setupByKey['form-builder']['complete']), 'Support Form Builder setup step is not complete.');
support_service_assert(empty($setupByKey['issue-contract']['complete']), 'Unavailable Issue metrics were reported ready.');

$activityLabels = array_column($dashboard['activity'], 'record_label');
support_service_assert(in_array('Dashboard Knowledge', $activityLabels, true), 'Support search-source audit activity is missing.');
support_service_assert(!in_array('Foreign Dashboard Knowledge', $activityLabels, true), 'Foreign-company audit activity leaked into the dashboard.');
support_service_assert(count($dashboard['activity']) === 2, 'Support activity is not bounded to company-owned foundation events.');

$dependencyByContract = [];
foreach ($dashboard['dependencies'] as $dependency) {
    $dependencyByContract[(string) ($dependency['contract'] ?? '')] = $dependency;
}
foreach ($expectedMetricContracts as $contract) {
    support_service_assert(($dependencyByContract[$contract]['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Dependency health omitted ' . $contract . '.');
}
support_service_assert(isset($dependencyByContract['operations.support-search.v1']), 'Operations search dependency health is missing.');

$shortcutByKey = [];
foreach ($dashboard['shortcuts'] as $shortcut) {
    $shortcutByKey[(string) ($shortcut['key'] ?? '')] = $shortcut;
}
support_service_assert(!empty($shortcutByKey['form-builder']['available']), 'Form Builder shortcut is unavailable.');
support_service_assert(str_contains((string) $shortcutByKey['form-builder']['href'], 'section=form-builder'), 'Form Builder shortcut destination is incorrect.');
support_service_assert(empty($shortcutByKey['issues']['available']), 'Unimplemented Issue destination is active.');
support_service_assert(($shortcutByKey['issues']['availability'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Issue shortcut lacks an explicit unavailable state.');
support_service_assert($dashboard['queue'] !== [], 'Support dashboard queue is empty.');
support_service_assert($dashboard['directories'] !== [], 'Support dashboard directories are empty.');

$emptyDashboard = yovel_admin_support_service_dashboard_data($foreignCompany, $foreignAdmin);
support_service_assert(!empty($emptyDashboard['foundation']['settings_configured']), 'Foreign fixture settings were not visible to its own dashboard.');
support_service_assert((int) $emptyDashboard['foundation']['active_search_sources'] === 1, 'Foreign dashboard did not retain its own source count.');
support_service_assert(!in_array('Dashboard Knowledge', array_column($emptyDashboard['activity'], 'record_label'), true), 'Primary-company activity leaked into the foreign dashboard.');

$previousGet = $_GET;
$_GET['section'] = 'dashboard';
$moduleData = yovel_admin_support_service_data($company, $admin);
$_GET = $previousGet;
support_service_assert(($moduleData['section'] ?? '') === 'dashboard', 'Support module data did not select dashboard.');
support_service_assert(is_array($moduleData['dashboard'] ?? null), 'Support module data omitted the dashboard envelope.');
support_service_assert(array_key_exists('dashboard', yovel_admin_support_service_sections()), 'Support section registry omitted dashboard.');
support_service_assert(array_key_exists('form-builder', yovel_admin_support_service_sections()), 'Support section registry omitted Form Builder.');

$activeModuleData = $moduleData;
$activeModuleSection = 'dashboard';
$activeModuleSections = yovel_admin_support_service_sections();
$activeModuleFormState = [];
$companyName = (string) $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/support-service/views/workspace.php';
$markup = (string) ob_get_clean();

support_service_assert(str_contains($markup, 'data-support-service-workspace'), 'Support workspace root marker is missing.');
support_service_assert(str_contains($markup, 'data-support-service-panels'), 'Support 12/8 panel marker is missing.');
support_service_assert(str_contains($markup, 'grid-template-columns:minmax(0,12fr) minmax(16rem,8fr)'), 'Support desktop 12/8 ratio is missing.');
support_service_assert(str_contains($markup, 'data-support-service-main data-grid-span="12"'), 'Support main panel does not declare 12 columns.');
support_service_assert(str_contains($markup, 'data-support-service-tools data-grid-span="8"'), 'Support tools panel does not declare 8 columns.');
$mainPosition = strpos($markup, '<main data-support-service-main');
$toolsPosition = strpos($markup, '<aside data-support-service-tools');
support_service_assert(is_int($mainPosition) && is_int($toolsPosition) && $mainPosition < $toolsPosition, 'Support panels do not render main-first in the DOM.');
foreach (['data-support-dashboard-summary', 'data-support-dashboard-queue', 'data-support-dashboard-activity', 'data-support-dashboard-setup', 'data-support-dashboard-alerts', 'data-support-dashboard-dependencies', 'data-support-dashboard-shortcuts', 'data-support-dashboard-directories'] as $marker) {
    support_service_assert(str_contains($markup, $marker), 'Dashboard markup omitted ' . $marker . '.');
}
support_service_assert(substr_count($markup, 'Unavailable') >= 6, 'Unavailable Support metrics are not visibly nonnumeric.');
support_service_assert(str_contains($markup, 'data-support-tour-start'), 'Support guided-tour trigger is missing.');
support_service_assert(str_contains($markup, 'data-support-tour-next') && str_contains($markup, 'data-support-tour-back') && str_contains($markup, 'data-support-tour-skip'), 'Support tour controls are incomplete.');
support_service_assert(str_contains($markup, "event.key === 'Escape'") && str_contains($markup, "event.key !== 'Tab'"), 'Support tour keyboard handling is incomplete.');
support_service_assert(str_contains($markup, 'trigger.focus()'), 'Support tour does not restore trigger focus.');
support_service_assert(str_contains($markup, 'builderx:support-service:' . $company['company_key_hash'] . ':tour'), 'Support tour state is not scoped to company and module.');
support_service_assert(str_contains($markup, 'section=form-builder'), 'Support workspace does not expose Form Builder.');

echo "Support / Service dashboard checks passed: live foundation, unavailable metrics, setup, queue, activity, dependencies, destinations, 12/8 dispatch, and guided tour.\n";
