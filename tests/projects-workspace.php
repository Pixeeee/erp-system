<?php
declare(strict_types=1);

require __DIR__ . '/projects-test-helper.php';

foreach ([
    'yovel_admin_projects_settings',
    'yovel_admin_projects_save_settings',
    'yovel_admin_projects_owner_states',
] as $function) {
    projects_assert(function_exists($function), 'Projects workspace function is missing: ' . $function);
}

$scope = projects_test_scope();
$company = $scope['company'];
$admin = $scope['admin'];
register_shutdown_function(static fn () => projects_cleanup($company, $admin));

$defaults = yovel_admin_projects_settings($company, $admin);
projects_assert(empty($defaults['persisted']), 'Projects settings should use server defaults before first save.');
projects_assert((int) $defaults['ignore_employee_time_overlap'] === 0, 'Default overlap policy is incorrect.');
projects_assert((int) $defaults['fetch_timesheet_in_sales_invoice'] === 0, 'Default Finance timesheet policy is incorrect.');

projects_with_session_token(static function (string $csrf) use ($company, $admin): void {
    $db = bx_db();
    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($company, $admin, 'save_projects_settings', [
            'csrf' => 'invalid',
            'section' => 'settings',
            'ignore_employee_time_overlap' => '1',
            'fetch_timesheet_in_sales_invoice' => '1',
        ]),
        'token'
    );
    projects_assert(
        (int) $db->GetOne('SELECT COUNT(*) FROM project_company_project_settings WHERE company_key_hash = ?', [$company['company_key_hash']]) === 0,
        'Invalid CSRF wrote Projects settings.'
    );

    $created = yovel_admin_projects_handle_post($company, $admin, 'save_projects_settings', [
        'csrf' => $csrf,
        'section' => 'settings',
        'ignore_employee_time_overlap' => '1',
        'fetch_timesheet_in_sales_invoice' => '1',
    ]);
    projects_assert(($created['message'] ?? '') === 'Projects settings saved.', 'Settings create feedback is unstable.');
    $first = yovel_admin_projects_settings($company, $admin);
    projects_assert(!empty($first['persisted']), 'Projects settings were not rehydrated from the server.');
    projects_assert((int) $first['ignore_employee_time_overlap'] === 1, 'Overlap setting did not persist.');
    projects_assert((int) $first['fetch_timesheet_in_sales_invoice'] === 1, 'Finance timesheet setting did not persist.');
    $stableKey = (string) $first['project_settings_key'];

    yovel_admin_projects_handle_post($company, $admin, 'save_projects_settings', [
        'csrf' => $csrf,
        'section' => 'settings',
        'ignore_employee_time_overlap' => '0',
        'fetch_timesheet_in_sales_invoice' => '1',
    ]);
    $updated = yovel_admin_projects_settings($company, $admin);
    projects_assert((string) $updated['project_settings_key'] === $stableKey, 'Settings update changed the stable key.');
    projects_assert((int) $updated['ignore_employee_time_overlap'] === 0, 'Settings update did not persist the committed value.');
    projects_assert((int) $updated['fetch_timesheet_in_sales_invoice'] === 1, 'Settings update lost an unchanged value.');
    projects_assert(
        (int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_project_settings' AND record_key = ?", [$stableKey]) === 2,
        'Settings create and update did not each write an audit event.'
    );
});

$ownerStates = yovel_admin_projects_owner_states();
foreach (['customer', 'employee', 'timesheets', 'assignments', 'finance', 'notifications', 'portal'] as $stateKey) {
    projects_assert(isset($ownerStates[$stateKey]), 'Owner state is missing: ' . $stateKey);
    projects_assert(in_array($ownerStates[$stateKey]['state'], ['AVAILABLE', 'UNAVAILABLE'], true), 'Owner state is not explicit: ' . $stateKey);
    projects_assert(trim((string) $ownerStates[$stateKey]['contract']) !== '', 'Owner contract is unnamed: ' . $stateKey);
}

$activeModuleSections = yovel_admin_projects_sections();
$activeModuleSection = 'form-builder';
$activeModuleMeta = $activeModuleSections[$activeModuleSection];
$activeModuleData = yovel_admin_projects_data($company, $admin, 'form-builder');
$activeModuleFormState = [
    'section' => 'form-builder',
    'action' => 'save_projects_form_schema',
    'input' => [
        'target_type' => 'PROJECT',
        'schema_status' => 'DRAFT',
        'schema_json' => (string) yovel_admin_projects_form_json(yovel_admin_projects_default_form_schemas()['PROJECT']),
        'new_field_label' => 'Retained delivery field',
    ],
    'error' => 'Retained Projects validation error.',
];
$companyName = (string) $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/projects/views/workspace.php';
require dirname(__DIR__) . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();

projects_assert(str_contains($markup, 'data-projects-workspace'), 'Projects workspace marker is missing.');
projects_assert(str_contains($markup, 'data-projects-main-panel') && str_contains($markup, 'data-grid-span="12"'), 'Projects main panel is not 12/20.');
projects_assert(str_contains($markup, 'data-projects-tools-panel') && str_contains($markup, 'data-grid-span="8"'), 'Projects tools panel is not 8/20.');
projects_assert(str_contains($markup, 'minmax(0, 12fr) minmax(16rem, 8fr)'), 'Projects desktop grid is not 12/8.');
projects_assert(strpos($markup, 'data-projects-main-panel') < strpos($markup, 'data-projects-tools-panel'), 'Projects responsive source order must keep main first.');
projects_assert(str_contains($markup, 'Field Toolbox'), 'Form Builder Field Toolbox is missing.');
projects_assert(str_contains($markup, 'Form Layout'), 'Form Builder canvas is missing.');
projects_assert(str_contains($markup, 'Field Properties'), 'Form Builder properties are missing.');
projects_assert(str_contains($markup, 'Retained delivery field'), 'Controller input was not rehydrated.');
projects_assert(str_contains($markup, 'Retained Projects validation error.'), 'Controller error was not rehydrated.');
projects_assert(str_contains($markup, 'data-record-modal-open-on-load'), 'Failed form does not reopen its record modal.');
projects_assert(str_contains($markup, 'data-record-modal'), 'Projects record modal hook is missing.');
projects_assert(str_contains($markup, 'data-confirm-submit'), 'Projects form does not use the shared confirmation hook.');
projects_assert(str_contains($markup, 'data-confirm-dialog'), 'Shared sibling confirmation dialog is missing.');
projects_assert(strrpos($markup, 'data-confirm-dialog') > strrpos($markup, '</form>'), 'Confirmation dialog must be outside every record form.');
projects_assert(str_contains($markup, 'aria-describedby="projects-form-builder-modal-description"'), 'Projects record modal lacks an accessible description association.');
projects_assert(str_contains($markup, 'name="module_view" value="projects"'), 'Projects form does not identify its registry route.');
projects_assert(str_contains($markup, 'name="csrf"'), 'Projects modal form is missing CSRF input.');

preg_match_all('/<button\b([^>]*)>(.*?)<\/button>/si', $markup, $buttons, PREG_SET_ORDER);
$creationCommands = 0;
foreach ($buttons as $button) {
    if (preg_match('/(?:Add|New|Create|Insert)/i', strip_tags((string) $button[2])) !== 1) {
        continue;
    }
    $creationCommands++;
    projects_assert(str_contains((string) $button[1], 'data-record-modal-open='), 'Every creation command must open a record modal.');
}
projects_assert($creationCommands > 0, 'Projects Task 2 exposes no creation command.');

$dependencyData = yovel_admin_projects_data($company, $admin, 'timesheets');
if (($ownerStates['timesheets']['state'] ?? '') === 'UNAVAILABLE') {
    projects_assert(($dependencyData['state']['kind'] ?? '') === 'dependency', 'Unavailable HR timesheets are not explicit in the workspace.');
    projects_assert(in_array('yovel_admin_hr_project_timesheets', $dependencyData['state']['dependencies'] ?? [], true), 'HR timesheet contract is not named.');
}

$source = '';
foreach (glob(dirname(__DIR__) . '/company/admin/modules/projects/*.php') ?: [] as $path) {
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
    projects_assert(!str_contains($source, $forbiddenPrefix), 'Projects directly references a foreign table prefix: ' . $forbiddenPrefix);
}

echo "Projects Task 2 settings and workspace checks passed.\n";

