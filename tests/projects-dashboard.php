<?php
declare(strict_types=1);

require __DIR__ . '/projects-test-helper.php';

projects_assert(
    function_exists('yovel_admin_projects_dashboard_data'),
    'Projects live dashboard provider is missing.'
);
projects_assert(
    is_file(dirname(__DIR__) . '/company/admin/modules/projects/views/dashboard.php'),
    'Projects live dashboard view is missing.'
);

$sections = yovel_admin_projects_sections();
projects_assert(isset($sections['dashboard']), 'Projects dashboard section is not registered.');
projects_assert(($sections['dashboard']['label'] ?? '') === 'Dashboard', 'Projects dashboard label is unstable.');

$scope = projects_test_scope();
$company = $scope['company'];
$admin = $scope['admin'];
$otherScope = projects_test_scope();
$otherCompany = $otherScope['company'];
$otherAdmin = $otherScope['admin'];
register_shutdown_function(static function () use ($company, $admin, $otherCompany, $otherAdmin): void {
    projects_cleanup($company, $admin);
    projects_cleanup($otherCompany, $otherAdmin);
});

$emptyDashboard = yovel_admin_projects_dashboard_data($company, $admin);
foreach (['summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directories', 'dependencies'] as $key) {
    projects_assert(is_array($emptyDashboard[$key] ?? null), 'Projects dashboard envelope is missing ' . $key . '.');
}

$summaryByKey = [];
foreach ($emptyDashboard['summary'] as $summary) {
    $summaryByKey[(string) ($summary['key'] ?? '')] = $summary;
}
projects_assert(($summaryByKey['foundation']['availability'] ?? '') === 'AVAILABLE', 'Projects foundation status is not available.');
projects_assert((int) ($summaryByKey['foundation']['value'] ?? 0) === 13, 'Projects foundation does not report all 13 owned tables.');
projects_assert(($summaryByKey['settings']['value'] ?? null) === 'Not configured', 'Projects settings setup state is inaccurate.');
projects_assert((int) ($summaryByKey['published-forms']['value'] ?? -1) === 0, 'Empty company has a fabricated published form count.');

$unavailableKeys = ['customers', 'employees', 'milestones', 'time', 'billing', 'utilization', 'budget'];
projects_assert(($summaryByKey['tasks']['availability'] ?? '') === 'AVAILABLE', 'Projects Task KPI is not available.');
projects_assert((int) ($summaryByKey['tasks']['value'] ?? -1) === 0, 'Empty company Task KPI is inaccurate.');
foreach ($unavailableKeys as $key) {
    projects_assert(isset($summaryByKey[$key]), 'Projects unavailable KPI is missing: ' . $key);
    projects_assert(
        in_array($summaryByKey[$key]['availability'] ?? '', ['UNAVAILABLE_DEPENDENCY', 'NOT_IMPLEMENTED'], true),
        'Projects unavailable KPI has an invalid state: ' . $key
    );
    projects_assert(!is_numeric($summaryByKey[$key]['value'] ?? null), 'Projects unavailable KPI fabricates a numeric zero: ' . $key);
}
$dependencyByKey = [];
foreach ($emptyDashboard['dependencies'] as $dependency) {
    $dependencyByKey[(string) ($dependency['key'] ?? '')] = $dependency;
}
projects_assert(($dependencyByKey['tasks']['status'] ?? '') === 'AVAILABLE', 'Projects Task dependency health is not available.');
foreach ($unavailableKeys as $key) {
    projects_assert(isset($dependencyByKey[$key]), 'Projects dependency health omits ' . $key . '.');
    projects_assert(
        in_array($dependencyByKey[$key]['status'] ?? '', ['UNAVAILABLE_DEPENDENCY', 'NOT_IMPLEMENTED'], true),
        'Projects dependency health is not explicit for ' . $key . '.'
    );
    projects_assert(trim((string) ($dependencyByKey[$key]['contract'] ?? '')) !== '', 'Projects dependency contract is unnamed for ' . $key . '.');
}

$setupByKey = [];
foreach ($emptyDashboard['setup'] as $step) {
    $setupByKey[(string) ($step['key'] ?? '')] = $step;
}
projects_assert(isset($setupByKey['foundation'], $setupByKey['settings'], $setupByKey['form-builder']), 'Projects setup checklist is incomplete.');
projects_assert(!empty($setupByKey['foundation']['complete']), 'Projects foundation setup step is not complete.');
projects_assert(empty($setupByKey['settings']['complete']), 'Unsaved Projects settings are marked complete.');
projects_assert(empty($setupByKey['form-builder']['complete']), 'Unpublished Projects forms are marked complete.');
projects_assert(count($emptyDashboard['queue']) >= 2, 'Projects setup queue does not expose pending setup work.');
projects_assert($emptyDashboard['activity'] === [], 'Empty company leaks Projects audit activity.');

projects_with_session_token(static function (string $csrf) use ($company, $admin, $otherCompany, $otherAdmin): void {
    yovel_admin_projects_handle_post($company, $admin, 'save_projects_settings', [
        'csrf' => $csrf,
        'section' => 'settings',
        'ignore_employee_time_overlap' => '1',
        'fetch_timesheet_in_sales_invoice' => '0',
    ]);
    $schema = yovel_admin_projects_default_form_schemas()['PROJECT'];
    yovel_admin_projects_handle_post($company, $admin, 'save_projects_form_schema', [
        'csrf' => $csrf,
        'section' => 'form-builder',
        'target_type' => 'PROJECT',
        'schema_status' => 'PUBLISHED',
        'schema_json' => yovel_admin_projects_form_json($schema),
    ]);

    $live = yovel_admin_projects_dashboard_data($company, $admin);
    $liveSummary = [];
    foreach ($live['summary'] as $summary) {
        $liveSummary[(string) $summary['key']] = $summary;
    }
    projects_assert(($liveSummary['settings']['value'] ?? '') === 'Configured', 'Projects dashboard did not rehydrate live settings state.');
    projects_assert((int) ($liveSummary['published-forms']['value'] ?? 0) === 1, 'Projects dashboard did not read the published Form Builder count.');
    projects_assert(count($live['activity']) === 2, 'Projects dashboard did not read the company audit activity.');
    projects_assert(count($live['activity']) <= 8, 'Projects dashboard activity is not bounded.');
    foreach ($live['activity'] as $activity) {
        projects_assert(str_starts_with((string) ($activity['key'] ?? ''), 'project-activity-'), 'Projects activity key is unstable.');
        projects_assert(trim((string) ($activity['action'] ?? '')) !== '', 'Projects activity action is missing.');
        projects_assert(trim((string) ($activity['record_label'] ?? '')) !== '', 'Projects activity record label is missing.');
        projects_assert(trim((string) ($activity['occurred_at'] ?? '')) !== '', 'Projects activity timestamp is missing.');
    }

    $other = yovel_admin_projects_dashboard_data($otherCompany, $otherAdmin);
    $otherSummary = [];
    foreach ($other['summary'] as $summary) {
        $otherSummary[(string) $summary['key']] = $summary;
    }
    projects_assert(($otherSummary['settings']['value'] ?? '') === 'Not configured', 'Projects dashboard leaked settings across companies.');
    projects_assert((int) ($otherSummary['published-forms']['value'] ?? -1) === 0, 'Projects dashboard leaked form totals across companies.');
    projects_assert($other['activity'] === [], 'Projects dashboard leaked activity across companies.');
});

$dashboardData = yovel_admin_projects_dashboard_data($company, $admin);
foreach ($dashboardData['shortcuts'] as $shortcut) {
    projects_assert(preg_match('/^\.\/\?view=projects&section=[a-z0-9-]+$/', (string) ($shortcut['href'] ?? '')) === 1, 'Projects shortcut destination is malformed.');
    if (!empty($shortcut['available'])) {
        parse_str((string) parse_url((string) $shortcut['href'], PHP_URL_QUERY), $query);
        projects_assert(isset($sections[$query['section'] ?? '']), 'Projects available shortcut is broken.');
    }
}
foreach ($dashboardData['directories'] as $directory) {
    projects_assert(in_array($directory['group'] ?? '', ['Reports', 'Masters'], true), 'Projects dashboard directory group is invalid.');
    foreach (($directory['items'] ?? []) as $item) {
        projects_assert(isset($item['label'], $item['href'], $item['available']), 'Projects directory item contract is incomplete.');
    }
}

$activeModuleSections = $sections;
$activeModuleSection = 'dashboard';
$activeModuleMeta = $sections['dashboard'];
$activeModuleData = yovel_admin_projects_data($company, $admin, 'dashboard');
$activeModuleFormState = [];
$companyName = (string) $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/projects/views/workspace.php';
$markup = (string) ob_get_clean();

foreach ([
    'data-projects-dashboard',
    'data-projects-dashboard-summary',
    'data-projects-dashboard-queue',
    'data-projects-dashboard-activity',
    'data-projects-dashboard-setup',
    'data-projects-dashboard-dependencies',
    'data-projects-dashboard-alerts',
    'data-projects-dashboard-shortcuts',
    'data-projects-dashboard-directories',
] as $marker) {
    projects_assert(str_contains($markup, $marker), 'Projects dashboard marker is missing: ' . $marker);
}
projects_assert(str_contains($markup, 'data-projects-main-panel') && str_contains($markup, 'data-grid-span="12"'), 'Projects dashboard main panel is not 12/20.');
projects_assert(str_contains($markup, 'data-projects-tools-panel') && str_contains($markup, 'data-grid-span="8"'), 'Projects dashboard tools panel is not 8/20.');
projects_assert(strpos($markup, 'data-projects-main-panel') < strpos($markup, 'data-projects-tools-panel'), 'Projects dashboard does not stack main first.');
projects_assert(str_contains($markup, 'Show Tour'), 'Projects guided tour trigger is missing.');
projects_assert(str_contains($markup, 'data-projects-dashboard-tour'), 'Projects guided tour dialog is missing.');
projects_assert(str_contains($markup, 'aria-modal="true"'), 'Projects guided tour is not modal-accessible.');
projects_assert(str_contains($markup, 'data-projects-tour-back'), 'Projects guided tour Back control is missing.');
projects_assert(str_contains($markup, 'data-projects-tour-next'), 'Projects guided tour Next control is missing.');
projects_assert(str_contains($markup, 'data-projects-tour-skip'), 'Projects guided tour Skip control is missing.');
projects_assert(str_contains($markup, 'projects-dashboard-tour:'), 'Projects guided tour persistence is not company-scoped.');
projects_assert(str_contains($markup, 'UNAVAILABLE_DEPENDENCY'), 'Projects dashboard does not visibly expose unavailable dependencies.');
projects_assert(str_contains($markup, 'Form Builder'), 'Projects dashboard omits Form Builder access.');
projects_assert(str_contains($markup, 'Reports'), 'Projects dashboard omits reports directory.');
projects_assert(str_contains($markup, 'Masters'), 'Projects dashboard omits masters directory.');

$source = '';
foreach (glob(dirname(__DIR__) . '/company/admin/modules/projects/*.php') ?: [] as $path) {
    $source .= (string) file_get_contents($path);
}
foreach (glob(dirname(__DIR__) . '/company/admin/modules/projects/views/*.php') ?: [] as $path) {
    $source .= (string) file_get_contents($path);
}
foreach ([
    'project_company_hr_',
    'project_company_finance_',
    'project_company_sales_',
    'project_company_buying_',
    'project_company_inventory_',
    'project_company_operations_',
    'project_company_support_',
] as $forbiddenPrefix) {
    projects_assert(!str_contains($source, $forbiddenPrefix), 'Projects dashboard reads a foreign table: ' . $forbiddenPrefix);
}

echo "Projects live dashboard checks passed.\n";
