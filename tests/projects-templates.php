<?php
declare(strict_types=1);

require __DIR__ . '/projects-test-helper.php';

foreach ([
    'yovel_admin_project_template_upsert',
    'yovel_admin_projects_templates',
    'yovel_admin_project_create_from_template',
] as $function) {
    projects_assert(function_exists($function), 'Projects template function is missing: ' . $function);
}

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

$rootTask = bx_uuid();
$childTask = bx_uuid();
$reviewTask = bx_uuid();
$tasks = [
    [
        'project_template_task_key' => $rootTask,
        'parent_template_task_key' => '',
        'depends_on_template_task_key' => '',
        'task_subject' => 'Discovery',
        'relative_start_days' => 0,
        'duration_days' => 2,
        'task_weight' => '20.0000',
        'sort_order' => 10,
    ],
    [
        'project_template_task_key' => $childTask,
        'parent_template_task_key' => $rootTask,
        'depends_on_template_task_key' => $rootTask,
        'task_subject' => 'Implementation',
        'relative_start_days' => 2,
        'duration_days' => 5,
        'task_weight' => '60.0000',
        'sort_order' => 20,
    ],
    [
        'project_template_task_key' => $reviewTask,
        'parent_template_task_key' => '',
        'depends_on_template_task_key' => $childTask,
        'task_subject' => 'Review',
        'relative_start_days' => 7,
        'duration_days' => 1,
        'task_weight' => '20.0000',
        'sort_order' => 30,
    ],
];

projects_with_session_token(static function (string $csrf) use (
    $company,
    $admin,
    $otherCompany,
    $otherAdmin,
    $tasks,
    $rootTask,
    $childTask,
    $reviewTask
): void {
    $db = bx_db();
    $templateResult = yovel_admin_projects_handle_post($company, $admin, 'save_project_template', [
        'csrf' => $csrf,
        'section' => 'projects',
        'template_code' => 'DELIVERY-TEMPLATE',
        'template_name' => 'Delivery Template',
        'template_status' => 'ACTIVE',
        'tasks_json' => json_encode($tasks, JSON_THROW_ON_ERROR),
    ]);
    projects_assert(($templateResult['message'] ?? '') === 'Project Template saved.', 'Template handler feedback is unstable.');
    $templates = yovel_admin_projects_templates($company, $admin);
    projects_assert(count($templates) === 1, 'Project Template was not rehydrated.');
    $template = $templates[0];
    $templateKey = (string) $template['project_template_key'];
    projects_assert(yovel_admin_is_uuid($templateKey), 'Project Template stable key is invalid.');
    projects_assert(count($template['tasks'] ?? []) === 3, 'Project Template tasks were not rehydrated.');
    projects_assert(array_column($template['tasks'], 'project_template_task_key') === [$rootTask, $childTask, $reviewTask], 'Template task order or stable keys changed.');
    projects_assert((string) $template['tasks'][1]['parent_template_task_key'] === $rootTask, 'Template parent edge was not persisted.');
    projects_assert((string) $template['tasks'][1]['source_task_key'] === $rootTask, 'Template dependency edge was not persisted.');

    $updatedTasks = $tasks;
    $updatedTasks[1]['task_subject'] = 'Build and configure';
    yovel_admin_projects_handle_post($company, $admin, 'save_project_template', [
        'csrf' => $csrf,
        'section' => 'projects',
        'project_template_key' => $templateKey,
        'template_code' => 'DELIVERY-TEMPLATE',
        'template_name' => 'Delivery Template Updated',
        'template_status' => 'ACTIVE',
        'tasks_json' => json_encode($updatedTasks, JSON_THROW_ON_ERROR),
    ]);
    $updated = yovel_admin_projects_templates($company, $admin)[0];
    projects_assert((string) $updated['project_template_key'] === $templateKey, 'Template update changed its stable key.');
    projects_assert((string) $updated['tasks'][1]['project_template_task_key'] === $childTask, 'Template update changed a task stable key.');
    projects_assert((string) $updated['tasks'][1]['task_subject'] === 'Build and configure', 'Template task update did not read back.');

    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($otherCompany, $otherAdmin, 'save_project_template', [
            'csrf' => $csrf,
            'section' => 'projects',
            'project_template_key' => $templateKey,
            'template_code' => 'DELIVERY-TEMPLATE',
            'template_name' => 'Foreign Update',
            'template_status' => 'ACTIVE',
            'tasks_json' => json_encode($updatedTasks, JSON_THROW_ON_ERROR),
        ]),
        'belong'
    );
    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($otherCompany, $otherAdmin, 'save_project_template', [
            'csrf' => $csrf,
            'section' => 'projects',
            'template_code' => 'FOREIGN-TASK-KEY',
            'template_name' => 'Foreign Task Key',
            'template_status' => 'ACTIVE',
            'tasks_json' => json_encode([$updatedTasks[0]], JSON_THROW_ON_ERROR),
        ]),
        'belongs'
    );
    projects_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_project_template WHERE company_key_hash = ?',
        [$otherCompany['company_key_hash']]
    ) === 0, 'Cross-company Template task collision left a header row.');

    $created = yovel_admin_projects_handle_post($company, $admin, 'create_project_from_template', [
        'csrf' => $csrf,
        'section' => 'projects',
        'project_template_key' => $templateKey,
        'project_code' => 'PRJ-TEMPLATE-001',
        'project_name' => 'Instantiated Delivery',
        'customer_key' => '',
        'project_lead_employee_key' => '',
        'completion_method' => 'TASK_WEIGHT',
        'expected_start_date' => '2026-10-01',
    ]);
    projects_assert(($created['message'] ?? '') === 'Project created from template.', 'Template instantiation feedback is unstable.');
    $projectKey = (string) ($created['query']['project_key'] ?? '');
    projects_assert(yovel_admin_is_uuid($projectKey), 'Template instantiation did not return a Project key.');
    $project = $db->GetRow(
        'SELECT * FROM project_company_project_record WHERE company_key_hash = ? AND project_key = ?',
        [$company['company_key_hash'], $projectKey]
    );
    projects_assert(is_array($project) && (string) $project['project_code'] === 'PRJ-TEMPLATE-001', 'Instantiated Project read-back failed.');
    projects_assert((string) $project['expected_end_date'] === '2026-10-09', 'Template did not calculate the Project end date.');
    $createdTasks = $db->GetAll(
        'SELECT * FROM project_company_project_task WHERE company_key_hash = ? AND project_key = ? ORDER BY task_code',
        [$company['company_key_hash'], $projectKey]
    );
    projects_assert(count($createdTasks) === 3, 'Template did not create every task.');
    $createdBySubject = [];
    foreach ($createdTasks as $task) {
        $createdBySubject[(string) $task['task_subject']] = $task;
    }
    projects_assert((string) $createdBySubject['Discovery']['expected_start_date'] === '2026-10-01', 'Root task start date is incorrect.');
    projects_assert((string) $createdBySubject['Build and configure']['expected_start_date'] === '2026-10-03', 'Child task start date is incorrect.');
    projects_assert((string) $createdBySubject['Build and configure']['parent_task_key'] === (string) $createdBySubject['Discovery']['task_key'], 'Instantiated parent edge was not remapped.');
    $dependencies = $db->GetAll(
        'SELECT * FROM project_company_project_task_dependency WHERE company_key_hash = ? AND task_key IN (?, ?)',
        [
            $company['company_key_hash'],
            $createdBySubject['Build and configure']['task_key'],
            $createdBySubject['Review']['task_key'],
        ]
    );
    projects_assert(count($dependencies) === 2, 'Template dependencies were not instantiated.');
    $dependencyMap = [];
    foreach ($dependencies as $dependency) {
        $dependencyMap[(string) $dependency['task_key']] = (string) $dependency['depends_on_task_key'];
    }
    projects_assert($dependencyMap[$createdBySubject['Build and configure']['task_key']] === (string) $createdBySubject['Discovery']['task_key'], 'Child dependency was not remapped.');
    projects_assert($dependencyMap[$createdBySubject['Review']['task_key']] === (string) $createdBySubject['Build and configure']['task_key'], 'Review dependency was not remapped.');

    $beforeProjects = (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_project_record WHERE company_key_hash = ?',
        [$company['company_key_hash']]
    );
    $beforeTasks = (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_project_task WHERE company_key_hash = ?',
        [$company['company_key_hash']]
    );
    $beforeDependencies = (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_project_task_dependency WHERE company_key_hash = ?',
        [$company['company_key_hash']]
    );
    $beforeAudit = (int) $db->GetOne(
        "SELECT COUNT(*) FROM builder_audit_log
         WHERE module = 'project_company_project_record'
           AND JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.company_key_hash')) = ?",
        [$company['company_key_hash']]
    );
    projects_expect_exception(
        static fn () => yovel_admin_project_create_from_template(
            $company,
            $admin,
            [
                'csrf' => $csrf,
                'project_template_key' => $templateKey,
                'project_code' => 'PRJ-ROLLBACK',
                'project_name' => 'Rollback Project',
                'customer_key' => '',
                'project_lead_employee_key' => '',
                'completion_method' => 'TASK_WEIGHT',
                'expected_start_date' => '2026-11-01',
            ],
            static function (string $stage): void {
                if ($stage === 'after-dependencies') {
                    throw new RuntimeException('forced template rollback');
                }
            }
        ),
        'forced template rollback'
    );
    projects_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_project_record WHERE company_key_hash = ?',
        [$company['company_key_hash']]
    ) === $beforeProjects, 'Template rollback left a Project.');
    projects_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_project_task WHERE company_key_hash = ?',
        [$company['company_key_hash']]
    ) === $beforeTasks, 'Template rollback left tasks.');
    projects_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_project_task_dependency WHERE company_key_hash = ?',
        [$company['company_key_hash']]
    ) === $beforeDependencies, 'Template rollback left dependencies.');
    projects_assert((int) $db->GetOne(
        "SELECT COUNT(*) FROM builder_audit_log
         WHERE module = 'project_company_project_record'
           AND JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.company_key_hash')) = ?",
        [$company['company_key_hash']]
    ) === $beforeAudit, 'Template rollback left an audit event.');
});

echo "Projects Task 3 template checks passed.\n";
