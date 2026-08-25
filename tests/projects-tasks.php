<?php
declare(strict_types=1);

require __DIR__ . '/projects-test-helper.php';

foreach ([
    'yovel_admin_task_type_upsert',
    'yovel_admin_projects_task_types',
    'yovel_admin_project_task_upsert',
    'yovel_admin_projects_tasks',
    'yovel_admin_project_task_transition',
    'yovel_admin_project_task_move',
    'yovel_admin_project_task_reschedule',
    'yovel_admin_project_task_retry_assignment',
] as $function) {
    projects_assert(function_exists($function), 'Projects Task 4 function is missing: ' . $function);
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

$db = bx_db();
yovel_admin_projects_schema();
foreach (['sort_order', 'assigned_employee_key', 'assignment_request_key', 'assignment_status', 'assignment_error_code'] as $column) {
    projects_assert((int) $db->GetOne(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'project_company_project_task' AND COLUMN_NAME = ?",
        [BUILDERX_DB_NAME, $column]
    ) === 1, 'Projects task schema is missing ' . $column . '.');
}

projects_with_session_token(static function (string $csrf) use (
    $company,
    $admin,
    $otherCompany,
    $otherAdmin,
    $db
): void {
    $today = new DateTimeImmutable('today');
    $projectStart = $today->modify('-20 days')->format('Y-m-d');
    $projectEnd = $today->modify('+40 days')->format('Y-m-d');
    $taskStart = $today->modify('+5 days')->format('Y-m-d');
    $taskEnd = $today->modify('+7 days')->format('Y-m-d');
    $dependentStart = $today->modify('+8 days')->format('Y-m-d');
    $dependentEnd = $today->modify('+10 days')->format('Y-m-d');

    projects_expect_exception(static fn () => yovel_admin_projects_handle_post($company, $admin, 'save_task_type', [
        'csrf' => 'bad-token',
        'task_type_code' => 'DELIVERABLE',
        'task_type_name' => 'Deliverable',
        'task_type_status' => 'ACTIVE',
    ]), 'token');

    $typeResult = yovel_admin_projects_handle_post($company, $admin, 'save_task_type', [
        'csrf' => $csrf,
        'task_type_code' => 'DELIVERABLE',
        'task_type_name' => 'Deliverable',
        'task_type_status' => 'ACTIVE',
    ]);
    projects_assert(($typeResult['message'] ?? '') === 'Task Type saved.', 'Task Type feedback is unstable.');
    $types = yovel_admin_projects_task_types($company, $admin);
    projects_assert(count($types) === 1, 'Task Type was not rehydrated.');
    $typeKey = (string) $types[0]['task_type_key'];
    yovel_admin_projects_handle_post($company, $admin, 'save_task_type', [
        'csrf' => $csrf,
        'task_type_key' => $typeKey,
        'task_type_code' => 'DELIVERABLE',
        'task_type_name' => 'Client Deliverable',
        'task_type_status' => 'ACTIVE',
    ]);
    projects_assert((string) yovel_admin_projects_task_types($company, $admin)[0]['task_type_key'] === $typeKey, 'Task Type update changed its stable key.');
    projects_expect_exception(static fn () => yovel_admin_task_type_upsert($company, $admin, [
        'csrf' => $csrf,
        'task_type_key' => bx_uuid(),
        'task_type_code' => 'DELIVERABLE',
        'task_type_name' => 'Collision',
        'task_type_status' => 'ACTIVE',
    ]), 'belong');

    $project = yovel_admin_project_upsert($company, $admin, [
        'csrf' => $csrf,
        'project_code' => 'TASK-GRAPH',
        'project_name' => 'Task Graph Project',
        'project_status' => 'OPEN',
        'completion_method' => 'TASK_PROGRESS',
        'percent_complete' => '0',
        'expected_start_date' => $projectStart,
        'expected_end_date' => $projectEnd,
    ]);
    $projectKey = (string) $project['project_key'];
    $taskSchema = yovel_admin_projects_default_form_schemas()['TASK'];
    yovel_admin_projects_save_form_schema($company, $admin, 'TASK', $taskSchema, 'PUBLISHED', $csrf);

    $base = [
        'csrf' => $csrf,
        'project_key' => $projectKey,
        'task_type_key' => $typeKey,
        'parent_task_key' => '',
        'task_status' => 'OPEN',
        'priority' => 'MEDIUM',
        'progress' => '0',
        'expected_start_date' => $taskStart,
        'expected_end_date' => $taskEnd,
        'completed_on' => '',
        'is_group' => '0',
        'task_weight' => '1',
        'sort_order' => '100',
        'assigned_employee_key' => '',
        'dependency_keys' => [],
    ];
    $taskInput = static fn (array $changes): array => array_replace($base, $changes);

    $group = yovel_admin_project_task_upsert($company, $admin, $taskInput([
        'task_code' => 'TASK-GROUP',
        'task_subject' => 'Delivery group',
        'is_group' => '1',
        'sort_order' => '10',
    ]));
    $groupKey = (string) $group['task_key'];
    $prerequisiteInput = $taskInput([
        'task_code' => 'TASK-PREREQ',
        'task_subject' => 'Prerequisite',
        'parent_task_key' => $groupKey,
        'progress' => '20',
        'sort_order' => '20',
    ]);
    $prerequisite = yovel_admin_project_task_upsert($company, $admin, $prerequisiteInput);
    $prerequisiteKey = (string) $prerequisite['task_key'];
    $dependentInput = $taskInput([
        'task_code' => 'TASK-DEPENDENT',
        'task_subject' => 'Dependent task',
        'parent_task_key' => $groupKey,
        'progress' => '80',
        'expected_start_date' => $dependentStart,
        'expected_end_date' => $dependentEnd,
        'sort_order' => '30',
        'dependency_keys' => [$prerequisiteKey],
    ]);
    $dependent = yovel_admin_project_task_upsert($company, $admin, $dependentInput);
    $dependentKey = (string) $dependent['task_key'];
    projects_assert($dependent['dependency_keys'] === [$prerequisiteKey], 'Task dependency was not exactly read back.');
    projects_assert((string) $dependent['parent_task_key'] === $groupKey, 'Task parent was not exactly read back.');
    projects_assert((string) $dependent['form_schema_version'] === '1', 'Task did not bind the active Form Builder version.');

    $ordered = yovel_admin_projects_tasks($company, $admin, $projectKey);
    projects_assert(array_column($ordered, 'task_code') === ['TASK-GROUP', 'TASK-PREREQ', 'TASK-DEPENDENT'], 'Task tree ordering is unstable.');
    $rolledProject = yovel_admin_projects_records($company, $admin)[0];
    projects_assert((string) $rolledProject['percent_complete'] === '50.0000', 'Task upsert did not recalculate Project progress in its transaction.');

    projects_expect_exception(static fn () => yovel_admin_project_task_upsert($company, $admin, $taskInput([
        'task_code' => 'TASK-BAD-PARENT',
        'task_subject' => 'Bad parent',
        'parent_task_key' => $prerequisiteKey,
    ])), 'group');
    projects_expect_exception(static fn () => yovel_admin_project_task_upsert($company, $admin, $taskInput([
        'task_code' => 'TASK-BAD-DATE',
        'task_subject' => 'Outside project',
        'expected_end_date' => $today->modify('+50 days')->format('Y-m-d'),
    ])), 'project date');
    projects_expect_exception(static fn () => yovel_admin_project_task_upsert($company, $admin, $taskInput([
        'task_code' => 'TASK-BAD-PROGRESS',
        'task_subject' => 'Bad progress',
        'progress' => '100.01',
    ])), 'percentage');

    $subgroup = yovel_admin_project_task_upsert($company, $admin, $taskInput([
        'task_code' => 'TASK-SUBGROUP',
        'task_subject' => 'Subgroup',
        'parent_task_key' => $groupKey,
        'is_group' => '1',
        'sort_order' => '15',
    ]));
    projects_expect_exception(
        static fn () => yovel_admin_project_task_move($company, $admin, $groupKey, (string) $subgroup['task_key'], $csrf),
        'cycle'
    );
    projects_expect_exception(static fn () => yovel_admin_project_task_upsert($company, $admin, array_replace($prerequisiteInput, [
        'task_key' => $prerequisiteKey,
        'dependency_keys' => [$dependentKey],
    ])), 'cycle');

    projects_expect_exception(
        static fn () => yovel_admin_project_task_transition($company, $admin, $dependentKey, 'COMPLETE', $csrf),
        'dependencies'
    );

    $unscheduled = yovel_admin_project_task_upsert($company, $admin, $taskInput([
        'task_code' => 'TASK-UNSCHEDULED',
        'task_subject' => 'Unscheduled dependent',
        'expected_start_date' => '',
        'expected_end_date' => '',
        'sort_order' => '35',
        'dependency_keys' => [$prerequisiteKey],
    ]));

    $rescheduled = yovel_admin_project_task_reschedule(
        $company,
        $admin,
        $prerequisiteKey,
        $today->modify('+6 days')->format('Y-m-d'),
        $today->modify('+9 days')->format('Y-m-d'),
        $csrf
    );
    projects_assert((string) $rescheduled['expected_end_date'] === $today->modify('+9 days')->format('Y-m-d'), 'Task reschedule did not read back.');
    $dependentAfterShift = array_values(array_filter(
        yovel_admin_projects_tasks($company, $admin, $projectKey),
        static fn (array $task): bool => (string) $task['task_key'] === $dependentKey
    ))[0];
    projects_assert((string) $dependentAfterShift['expected_start_date'] === $today->modify('+10 days')->format('Y-m-d'), 'Dependent task start was not shifted.');
    projects_assert((string) $dependentAfterShift['expected_end_date'] === $today->modify('+12 days')->format('Y-m-d'), 'Dependent task end was not shifted.');
    $unscheduledAfterShift = array_values(array_filter(
        yovel_admin_projects_tasks($company, $admin, $projectKey),
        static fn (array $task): bool => (string) $task['task_key'] === (string) $unscheduled['task_key']
    ))[0];
    projects_assert((string) ($unscheduledAfterShift['expected_start_date'] ?? '') === '', 'Reschedule fabricated an unscheduled dependent start date.');
    projects_assert((string) ($unscheduledAfterShift['expected_end_date'] ?? '') === '', 'Reschedule fabricated an unscheduled dependent end date.');

    yovel_admin_project_task_transition($company, $admin, $prerequisiteKey, 'COMPLETE', $csrf);
    $completed = yovel_admin_project_task_transition($company, $admin, $dependentKey, 'COMPLETE', $csrf);
    projects_assert((string) $completed['task_status'] === 'COMPLETED', 'Task completion transition failed.');
    projects_assert((string) $completed['progress'] === '100.0000', 'Completed Task is not 100 percent.');
    projects_assert((string) $completed['completed_on'] !== '', 'Completed Task has no completion date.');

    $overdue = yovel_admin_project_task_upsert($company, $admin, $taskInput([
        'task_code' => 'TASK-OVERDUE',
        'task_subject' => 'Overdue task',
        'expected_start_date' => $today->modify('-5 days')->format('Y-m-d'),
        'expected_end_date' => $today->modify('-1 day')->format('Y-m-d'),
        'sort_order' => '40',
    ]));
    projects_assert((string) $overdue['task_status'] === 'OVERDUE', 'Past open Task was not marked overdue.');
    $cancelled = yovel_admin_project_task_transition($company, $admin, (string) $overdue['task_key'], 'CANCEL', $csrf);
    projects_assert((string) $cancelled['task_status'] === 'CANCELLED', 'Task cancellation failed.');
    projects_assert((string) ($cancelled['completed_on'] ?? '') === '', 'Cancelled Task retained a completion date.');

    $assignmentEmployee = bx_uuid();
    $unassigned = yovel_admin_project_task_upsert($company, $admin, $taskInput([
        'task_code' => 'TASK-ASSIGN-PENDING',
        'task_subject' => 'Assignment unavailable',
        'assigned_employee_key' => $assignmentEmployee,
        'sort_order' => '50',
    ]));
    projects_assert(($unassigned['assignment']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Unavailable HR assignment state is not explicit.');
    projects_assert(!empty($unassigned['assignment']['retryable']), 'Unavailable HR assignment is not retryable.');
    projects_assert((string) $unassigned['assignment_status'] === 'FAILED', 'Unavailable assignment was not persisted as failed.');
    projects_assert((string) $unassigned['assignment_error_code'] === 'UNAVAILABLE_DEPENDENCY', 'Unavailable assignment error code was not persisted.');
    projects_assert((string) ($unassigned['external_assignment_key'] ?? '') === '', 'Unavailable assignment claimed an HR key.');

    $foreignInput = array_replace($dependentInput, ['task_key' => $dependentKey]);
    projects_expect_exception(static fn () => yovel_admin_project_task_upsert($otherCompany, $otherAdmin, $foreignInput), 'belong');

    $rollbackCode = 'TASK-ROLLBACK';
    $beforeAudit = (int) $db->GetOne(
        "SELECT COUNT(*) FROM builder_audit_log
         WHERE module LIKE 'project_company_project_task%'
           AND JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.company_key_hash')) = ?",
        [$company['company_key_hash']]
    );
    projects_expect_exception(static fn () => yovel_admin_project_task_upsert(
        $company,
        $admin,
        $taskInput([
            'task_code' => $rollbackCode,
            'task_subject' => 'Rollback task',
            'dependency_keys' => [$prerequisiteKey],
        ]),
        static function (string $phase): void {
            if ($phase === 'after-dependencies') {
                throw new RuntimeException('Injected task graph failure.');
            }
        }
    ), 'injected');
    projects_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_project_task WHERE company_key_hash = ? AND task_code = ?',
        [$company['company_key_hash'], $rollbackCode]
    ) === 0, 'Task rollback left a task row.');
    projects_assert((int) $db->GetOne(
        "SELECT COUNT(*) FROM builder_audit_log
         WHERE module LIKE 'project_company_project_task%'
           AND JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.company_key_hash')) = ?",
        [$company['company_key_hash']]
    ) === $beforeAudit, 'Task rollback left an audit event.');
});

$activeModuleSections = yovel_admin_projects_sections();
$activeModuleSection = 'project-tasks';
$activeModuleMeta = $activeModuleSections['project-tasks'];
$activeModuleData = yovel_admin_projects_data($company, $admin, 'project-tasks');
$activeModuleFormState = [
    'section' => 'project-tasks',
    'action' => 'save_project_task',
    'input' => [
        'task_code' => 'TASK-RETAINED',
        'task_subject' => 'Retained Task Subject',
        'project_key' => (string) ($activeModuleData['projects'][0]['project_key'] ?? ''),
        'assigned_employee_key' => bx_uuid(),
    ],
    'error' => 'UNAVAILABLE_DEPENDENCY: HR assignment is unavailable.',
];
$companyName = (string) $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/projects/views/workspace.php';
require dirname(__DIR__) . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
projects_assert(str_contains($markup, 'data-projects-task-tree'), 'Projects task tree workspace is missing.');
projects_assert(str_contains($markup, 'data-record-modal-open="projects-task-modal-new"'), 'Add Task does not open a record modal.');
projects_assert(str_contains($markup, 'data-record-modal-open="projects-task-type-modal-new"'), 'Add Task Type does not open a record modal.');
projects_assert(str_contains($markup, 'data-record-modal-open="projects-task-status-modal-'), 'Task status does not open a record modal.');
projects_assert(str_contains($markup, 'data-record-modal-open="projects-task-schedule-modal-'), 'Task schedule does not open a record modal.');
projects_assert(str_contains($markup, 'data-record-modal-open="projects-task-retry-modal-'), 'Retryable assignment does not open a record modal.');
foreach (['transition_project_task', 'reschedule_project_task', 'retry_project_task_assignment'] as $action) {
    projects_assert(str_contains($markup, 'name="action" value="' . $action . '"'), 'Task workspace omits action: ' . $action);
}
projects_assert(str_contains($markup, 'data-confirm-submit'), 'Task forms do not use confirmation.');
projects_assert(str_contains($markup, 'data-confirm-dialog'), 'Task confirmation dialog is not a sibling.');
projects_assert(str_contains($markup, 'data-record-modal-open-on-load'), 'Failed Task form does not reopen.');
projects_assert(str_contains($markup, 'Retained Task Subject'), 'Failed Task subject was not retained.');
projects_assert(str_contains($markup, 'UNAVAILABLE_DEPENDENCY: HR assignment is unavailable.'), 'Task assignment error is not explicit.');

echo "Projects Task 4 task graph checks passed.\n";
