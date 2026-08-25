<?php
declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/hr/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';

function hr_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hr_contract_source(string $path): string
{
    hr_contract_assert(is_file($path), 'Required HR contract file is missing: ' . $path);
    $source = file_get_contents($path);
    hr_contract_assert(is_string($source), 'Unable to read HR contract file: ' . $path);
    return $source;
}

$expectedSections = [
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

hr_contract_assert(array_keys(yovel_admin_hr_sections()) === $expectedSections, 'The 13-section HR navigation contract changed.');

$registry = yovel_admin_module_registry();
$hrRegistry = $registry['hr'] ?? null;
hr_contract_assert(is_array($hrRegistry), 'HR is not registered with the shared module registry.');
hr_contract_assert(($hrRegistry['view'] ?? null) === 'hr', 'The registered HR view route changed.');
hr_contract_assert(($hrRegistry['default_section'] ?? null) === 'dashboard', 'The registered HR default section must use the existing HR Dashboard.');

foreach ($expectedSections as $section) {
    hr_contract_assert(isset(yovel_admin_hr_sections()[$section]), 'HR section does not resolve: ' . $section);
}

hr_contract_assert(function_exists('yovel_admin_persist_hr_builder_form'), 'HR Form Builder persistence moved or disappeared.');
hr_contract_assert(function_exists('yovel_admin_persist_hr_form_submission'), 'HR form submission persistence moved or disappeared.');
hr_contract_assert(function_exists('yovel_admin_upsert_hr_builder_form_version'), 'Immutable HR Form Builder version persistence moved or disappeared.');

$builderTargets = yovel_admin_hr_builder_target_sections();
hr_contract_assert(array_keys($builderTargets) === array_slice($expectedSections, 1), 'The HR Form Builder target tabs changed.');

$adapter = yovel_admin_shared_form_adapter('hr');
hr_contract_assert(($adapter['module'] ?? null) === 'hr', 'The shared Form Builder adapter no longer resolves HR.');
hr_contract_assert(array_keys($adapter['target_record_types'] ?? []) === array_slice($expectedSections, 1), 'The shared Form Builder adapter lost an HR target.');
hr_contract_assert(($adapter['row_column_layout']['version'] ?? null) === 2, 'The HR row/column schema version changed.');
hr_contract_assert(($adapter['row_column_layout']['max_columns'] ?? null) === 3, 'The HR Form Builder no longer supports the approved three-column maximum.');
hr_contract_assert(!empty($adapter['row_column_layout']['stable_keys']), 'Stable HR Form Builder keys are no longer protected.');

$viewContracts = [
    'employee-profiles.php' => ['yovel-employee-modal-open', 'yovel-employee-modal', 'yovel-employee-form'],
    'departments.php' => ['yovel-department-modal-open', 'yovel-department-modal', 'yovel-department-form'],
    'job-positions.php' => ['yovel-job-position-modal-open', 'yovel-job-position-modal', 'yovel-job-position-form'],
    'teams.php' => ['yovel-team-modal-open', 'yovel-team-modal', 'yovel-team-form'],
];

foreach ($viewContracts as $view => $markers) {
    $source = hr_contract_source($root . '/company/admin/modules/hr/views/' . $view);
    foreach ($markers as $marker) {
        hr_contract_assert(str_contains($source, $marker), $view . ' lost modal contract marker: ' . $marker);
    }
    hr_contract_assert(str_contains($source, 'data-confirm-submit'), $view . ' no longer requires confirmation before persistence.');
}

$dashboard = hr_contract_source($root . '/company/admin/modules/hr/views/dashboard.php');
foreach ([
    'aria-label="HR feature forms"',
    'builder_mode=new',
    'builder_mode=existing',
    'name="action" value="save_hr_builder_form"',
    'name="action" value="save_hr_form_submission"',
    'name="form_version_key"',
    'name="submission_key"',
] as $marker) {
    hr_contract_assert(str_contains($dashboard, $marker), 'HR dashboard Form Builder route contract changed: ' . $marker);
}

$controller = hr_contract_source($root . '/company/admin/bootstrap/controller.php');
foreach ([
    "'save_hr_builder_form' => yovel_admin_save_hr_builder_form",
    "'save_hr_form_submission' => yovel_admin_save_hr_form_submission",
    "'save_hr_builder_form' => 'dashboard'",
    "'save_hr_form_submission' => 'dashboard'",
] as $marker) {
    hr_contract_assert(str_contains($controller, $marker), 'HR POST route contract changed: ' . $marker);
}

$forms = hr_contract_source($root . '/company/admin/modules/hr/forms.php');
hr_contract_assert(str_contains($forms, "'bx_project_module_sync_hr_builder_form'"), 'HR Form Builder no longer calls the project-module projection boundary.');
hr_contract_assert(str_contains($forms, "'projected_form_count'"), 'HR Form Builder no longer verifies and audits its projected forms.');

echo "HR preservation contract checks passed.\n";
