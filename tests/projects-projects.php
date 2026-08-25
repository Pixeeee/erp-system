<?php
declare(strict_types=1);

$GLOBALS['projects_workforce_contract_fixtures'] = [];
function yovel_admin_hr_workforce_read_contract(array $company, array $options = []): array
{
    $hash = (string) ($company['company_key_hash'] ?? '');
    return [
        'contract' => 'hr.workforce.v1',
        'company_key_hash' => $hash,
        'employees' => $GLOBALS['projects_workforce_contract_fixtures'][$hash] ?? [],
        'availability' => [],
    ];
}

require __DIR__ . '/projects-test-helper.php';

foreach ([
    'yovel_admin_project_type_upsert',
    'yovel_admin_projects_project_types',
    'yovel_admin_project_upsert',
    'yovel_admin_projects_records',
    'yovel_admin_project_transition',
    'yovel_admin_project_recalculate',
] as $function) {
    projects_assert(function_exists($function), 'Projects Task 3 function is missing: ' . $function);
}

$scope = projects_test_scope();
$company = $scope['company'];
$admin = $scope['admin'];
$otherScope = projects_test_scope();
$otherCompany = $otherScope['company'];
$otherAdmin = $otherScope['admin'];
$employeeKey = bx_uuid();
$GLOBALS['projects_workforce_contract_fixtures'][$company['company_key_hash']] = [[
    'employee_key' => $employeeKey,
    'employee_code' => 'EMP-PROJECTS',
    'employee_name' => 'Project Lead',
    'employee_status' => 'ACTIVE',
    'department_key' => '',
    'job_position_key' => '',
]];
register_shutdown_function(static function () use ($company, $admin, $otherCompany, $otherAdmin): void {
    projects_cleanup($company, $admin);
    projects_cleanup($otherCompany, $otherAdmin);
});

$db = bx_db();
yovel_admin_projects_schema();
$leadColumn = (int) $db->GetOne(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'project_company_project_record'
       AND COLUMN_NAME = 'project_lead_employee_key'",
    [BUILDERX_DB_NAME]
);
projects_assert($leadColumn === 1, 'Projects schema is missing the HR workforce reference column.');

projects_with_session_token(static function (string $csrf) use (
    $company,
    $admin,
    $otherCompany,
    $otherAdmin,
    $employeeKey,
    $db
): void {
    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($company, $admin, 'save_project_type', [
            'csrf' => 'bad-token',
            'section' => 'projects',
            'project_type_code' => 'DELIVERY',
            'project_type_name' => 'Delivery',
            'project_type_status' => 'ACTIVE',
        ]),
        'token'
    );
    projects_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_project_type WHERE company_key_hash = ?',
        [$company['company_key_hash']]
    ) === 0, 'Invalid CSRF wrote a Project Type.');

    $typeResult = yovel_admin_projects_handle_post($company, $admin, 'save_project_type', [
        'csrf' => $csrf,
        'section' => 'projects',
        'project_type_code' => 'DELIVERY',
        'project_type_name' => 'Delivery',
        'project_type_status' => 'ACTIVE',
    ]);
    projects_assert(($typeResult['message'] ?? '') === 'Project Type saved.', 'Project Type handler feedback is unstable.');
    $types = yovel_admin_projects_project_types($company, $admin);
    projects_assert(count($types) === 1, 'Project Type was not rehydrated.');
    $typeKey = (string) $types[0]['project_type_key'];
    projects_assert(yovel_admin_is_uuid($typeKey), 'Project Type stable key is invalid.');

    yovel_admin_projects_handle_post($company, $admin, 'save_project_type', [
        'csrf' => $csrf,
        'section' => 'projects',
        'project_type_key' => $typeKey,
        'project_type_code' => 'DELIVERY',
        'project_type_name' => 'Client Delivery',
        'project_type_status' => 'ACTIVE',
    ]);
    $updatedTypes = yovel_admin_projects_project_types($company, $admin);
    projects_assert((string) $updatedTypes[0]['project_type_key'] === $typeKey, 'Project Type update changed its stable key.');
    projects_assert((string) $updatedTypes[0]['project_type_name'] === 'Client Delivery', 'Project Type update did not read back.');
    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($company, $admin, 'save_project_type', [
            'csrf' => $csrf,
            'section' => 'projects',
            'project_type_key' => bx_uuid(),
            'project_type_code' => 'DELIVERY',
            'project_type_name' => 'Duplicate',
            'project_type_status' => 'ACTIVE',
        ]),
        'belong'
    );

    $baseProject = [
        'csrf' => $csrf,
        'section' => 'projects',
        'project_code' => 'PRJ-001',
        'project_name' => 'Platform Delivery',
        'project_type_key' => $typeKey,
        'customer_key' => '',
        'project_lead_employee_key' => $employeeKey,
        'project_status' => 'OPEN',
        'completion_method' => 'MANUAL',
        'percent_complete' => '25.0000',
        'expected_start_date' => '2026-09-01',
        'expected_end_date' => '2026-09-30',
    ];
    $projectResult = yovel_admin_projects_handle_post($company, $admin, 'save_project', $baseProject);
    projects_assert(($projectResult['message'] ?? '') === 'Project saved.', 'Project handler feedback is unstable.');
    $projects = yovel_admin_projects_records($company, $admin);
    projects_assert(count($projects) === 1, 'Project was not rehydrated.');
    $project = $projects[0];
    $projectKey = (string) $project['project_key'];
    projects_assert(yovel_admin_is_uuid($projectKey), 'Project stable key is invalid.');
    foreach ([
        'project_code' => 'PRJ-001',
        'project_name' => 'Platform Delivery',
        'project_type_key' => $typeKey,
        'customer_key' => '',
        'project_lead_employee_key' => $employeeKey,
        'project_status' => 'OPEN',
        'completion_method' => 'MANUAL',
        'percent_complete' => '25.0000',
        'expected_start_date' => '2026-09-01',
        'expected_end_date' => '2026-09-30',
    ] as $field => $expected) {
        projects_assert((string) ($project[$field] ?? '') === $expected, 'Project exact read-back failed for ' . $field . '.');
    }

    $updateProject = $baseProject;
    $updateProject['project_key'] = $projectKey;
    $updateProject['project_name'] = 'Platform Delivery Updated';
    $updateProject['percent_complete'] = '40.0000';
    yovel_admin_projects_handle_post($company, $admin, 'save_project', $updateProject);
    $savedProject = yovel_admin_projects_records($company, $admin)[0];
    projects_assert((string) $savedProject['project_key'] === $projectKey, 'Project update changed its stable key.');
    projects_assert((string) $savedProject['project_name'] === 'Platform Delivery Updated', 'Project update did not rehydrate the committed name.');
    projects_assert((string) $savedProject['percent_complete'] === '40.0000', 'Project update did not rehydrate the committed percentage.');

    $customerInput = $baseProject;
    $customerInput['project_code'] = 'PRJ-CUSTOMER';
    $customerInput['customer_key'] = bx_uuid();
    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($company, $admin, 'save_project', $customerInput),
        'UNAVAILABLE_DEPENDENCY'
    );
    projects_assert($customerInput['customer_key'] !== '', 'Unavailable Customer input was not retained by the caller.');
    projects_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_project_record WHERE company_key_hash = ? AND project_code = ?',
        [$company['company_key_hash'], 'PRJ-CUSTOMER']
    ) === 0, 'Unavailable Customer validation wrote a Project.');

    $badEmployee = $baseProject;
    $badEmployee['project_code'] = 'PRJ-EMPLOYEE';
    $badEmployee['project_lead_employee_key'] = bx_uuid();
    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($company, $admin, 'save_project', $badEmployee),
        'employee'
    );
    $badDates = $baseProject;
    $badDates['project_code'] = 'PRJ-DATES';
    $badDates['expected_start_date'] = '2026-10-01';
    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($company, $admin, 'save_project', $badDates),
        'date'
    );
    $badPercent = $baseProject;
    $badPercent['project_code'] = 'PRJ-PERCENT';
    $badPercent['percent_complete'] = '100.01';
    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($company, $admin, 'save_project', $badPercent),
        'percentage'
    );

    $foreignUpdate = $updateProject;
    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($otherCompany, $otherAdmin, 'save_project', $foreignUpdate),
        'belong'
    );

    yovel_admin_projects_handle_post($company, $admin, 'transition_project', [
        'csrf' => $csrf, 'section' => 'projects', 'project_key' => $projectKey, 'transition' => 'HOLD',
    ]);
    $held = yovel_admin_projects_records($company, $admin)[0];
    projects_assert((string) $held['project_status'] === 'ON_HOLD', 'Project hold transition failed.');
    projects_assert((string) $held['percent_complete'] === '40.0000', 'Project hold changed completion.');
    yovel_admin_projects_handle_post($company, $admin, 'transition_project', [
        'csrf' => $csrf, 'section' => 'projects', 'project_key' => $projectKey, 'transition' => 'COMPLETE',
    ]);
    $completed = yovel_admin_projects_records($company, $admin)[0];
    projects_assert((string) $completed['project_status'] === 'COMPLETED', 'Project completion transition failed.');
    projects_assert((string) $completed['percent_complete'] === '100.0000', 'Completed Project is not 100 percent.');

    $recalcInput = $updateProject;
    $recalcInput['completion_method'] = 'TASK_PROGRESS';
    $recalcInput['project_status'] = 'OPEN';
    $recalcInput['percent_complete'] = '0';
    yovel_admin_projects_handle_post($company, $admin, 'save_project', $recalcInput);
    foreach ([20, 80] as $index => $progress) {
        $taskKey = bx_uuid();
        yovel_admin_projects_db_execute(
            $db,
            "INSERT INTO project_company_project_task (
                task_key, company_key, company_key_hash, project_key, task_code, task_subject,
                task_status, priority, progress, is_group, task_weight,
                created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, 'WORKING', 'MEDIUM', ?, 0, ?, ?, ?)",
            [
                $taskKey, $company['company_key'], $company['company_key_hash'], $projectKey,
                'PRJ-001-T' . ($index + 1), 'Task ' . ($index + 1), $progress, $index === 0 ? 1 : 3,
                $admin['admin_key'], $admin['admin_key'],
            ],
            'Projects recalculation fixture'
        );
    }
    $recalculated = yovel_admin_project_recalculate($company, $admin, $projectKey);
    projects_assert((string) $recalculated['percent_complete'] === '50.0000', 'Task-progress completion calculation is incorrect.');
    $weightInput = $recalcInput;
    $weightInput['completion_method'] = 'TASK_WEIGHT';
    yovel_admin_projects_handle_post($company, $admin, 'save_project', $weightInput);
    $weighted = yovel_admin_project_recalculate($company, $admin, $projectKey);
    projects_assert((string) $weighted['percent_complete'] === '65.0000', 'Task-weight completion calculation is incorrect.');

    projects_assert((int) $db->GetOne(
        "SELECT COUNT(*) FROM builder_audit_log
         WHERE module IN ('project_company_project_type','project_company_project_record')
           AND JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.company_key_hash')) = ?",
        [$company['company_key_hash']]
    ) >= 7, 'Project master writes are missing audit events.');
});

$activeModuleSections = yovel_admin_projects_sections();
$activeModuleSection = 'projects';
$activeModuleMeta = $activeModuleSections['projects'];
$activeModuleData = yovel_admin_projects_data($company, $admin, 'projects');
$retainedCustomer = bx_uuid();
$activeModuleFormState = [
    'section' => 'projects',
    'action' => 'save_project',
    'input' => [
        'project_code' => 'PRJ-RETAINED',
        'project_name' => 'Retained Project Name',
        'customer_key' => $retainedCustomer,
        'project_lead_employee_key' => $employeeKey,
        'completion_method' => 'MANUAL',
        'percent_complete' => '10',
        'expected_start_date' => '2026-09-01',
        'expected_end_date' => '2026-09-30',
    ],
    'error' => 'UNAVAILABLE_DEPENDENCY: Sales customer validation is unavailable.',
];
$companyName = (string) $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/projects/views/workspace.php';
require dirname(__DIR__) . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
projects_assert(str_contains($markup, 'data-projects-projects'), 'Projects master workspace is missing.');
projects_assert(str_contains($markup, 'data-record-modal-open="projects-project-modal-new"'), 'Add Project does not open a record modal.');
projects_assert(str_contains($markup, 'data-record-modal-open="projects-type-modal-new"'), 'Add Project Type does not open a record modal.');
projects_assert(str_contains($markup, 'data-record-modal-open="projects-template-modal-new"'), 'Add Project Template does not open a record modal.');
projects_assert(str_contains($markup, 'data-confirm-submit'), 'Projects master forms do not use confirmation.');
projects_assert(str_contains($markup, 'data-confirm-dialog'), 'Projects confirmation dialog is not a sibling.');
projects_assert(str_contains($markup, 'data-record-modal-open-on-load'), 'Failed Project form does not reopen.');
projects_assert(str_contains($markup, 'Retained Project Name'), 'Failed Project name was not retained.');
projects_assert(str_contains($markup, $retainedCustomer), 'Unavailable Customer reference was not retained.');
projects_assert(str_contains($markup, 'UNAVAILABLE_DEPENDENCY: Sales customer validation is unavailable.'), 'Unavailable Customer error is not explicit.');

echo "Projects Task 3 project master checks passed.\n";
