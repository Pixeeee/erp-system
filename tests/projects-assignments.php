<?php
declare(strict_types=1);

$GLOBALS['projects_assignment_calls'] = [];
$GLOBALS['projects_assignment_fail_first'] = true;
$GLOBALS['projects_assignment_key'] = '';

function yovel_admin_hr_assign_project_task(array $company, array $admin, array $assignment): array
{
    $probe = ADONewConnection(BUILDERX_DB_DRIVER);
    $probe->Connect(BUILDERX_DB_HOST, BUILDERX_DB_USER, BUILDERX_DB_PASS, BUILDERX_DB_NAME);
    $probe->SetFetchMode(ADODB_FETCH_ASSOC);
    $saved = $probe->GetRow(
        'SELECT task_key, assignment_status FROM project_company_project_task WHERE company_key_hash = ? AND task_key = ?',
        [(string) ($company['company_key_hash'] ?? ''), (string) ($assignment['task_key'] ?? '')]
    );
    $probe->Close();
    if (!is_array($saved) || (string) ($saved['assignment_status'] ?? '') !== 'PENDING') {
        throw new RuntimeException('HR assignment was called before the Projects task transaction committed.');
    }

    $GLOBALS['projects_assignment_calls'][] = [
        'company' => $company,
        'admin' => $admin,
        'assignment' => $assignment,
    ];
    if ($GLOBALS['projects_assignment_fail_first']) {
        $GLOBALS['projects_assignment_fail_first'] = false;
        throw new RuntimeException('HR assignment service temporarily unavailable.');
    }

    if ($GLOBALS['projects_assignment_key'] === '') {
        $GLOBALS['projects_assignment_key'] = bx_uuid();
    }
    return [
        'assignment_key' => $GLOBALS['projects_assignment_key'],
        'task_key' => (string) ($assignment['task_key'] ?? ''),
        'employee_key' => (string) ($assignment['employee_key'] ?? ''),
        'idempotency_key' => (string) ($assignment['idempotency_key'] ?? ''),
        'status' => 'ASSIGNED',
    ];
}

require __DIR__ . '/projects-test-helper.php';

projects_assert(function_exists('yovel_admin_project_task_retry_assignment'), 'Projects assignment retry adapter is missing.');

$scope = projects_test_scope();
$company = $scope['company'];
$admin = $scope['admin'];
register_shutdown_function(static fn () => projects_cleanup($company, $admin));

projects_with_session_token(static function (string $csrf) use ($company, $admin): void {
    $today = new DateTimeImmutable('today');
    $project = yovel_admin_project_upsert($company, $admin, [
        'csrf' => $csrf,
        'project_code' => 'ASSIGNMENT-HANDOFF',
        'project_name' => 'Assignment Handoff',
        'project_status' => 'OPEN',
        'completion_method' => 'TASK_PROGRESS',
        'percent_complete' => '0',
        'expected_start_date' => $today->format('Y-m-d'),
        'expected_end_date' => $today->modify('+30 days')->format('Y-m-d'),
    ]);
    $employeeKey = bx_uuid();
    $task = yovel_admin_project_task_upsert($company, $admin, [
        'csrf' => $csrf,
        'project_key' => (string) $project['project_key'],
        'task_code' => 'ASSIGN-001',
        'task_subject' => 'Assigned work',
        'task_status' => 'OPEN',
        'priority' => 'HIGH',
        'progress' => '0',
        'expected_start_date' => $today->modify('+1 day')->format('Y-m-d'),
        'expected_end_date' => $today->modify('+5 days')->format('Y-m-d'),
        'is_group' => '0',
        'task_weight' => '1',
        'sort_order' => '10',
        'assigned_employee_key' => $employeeKey,
        'dependency_keys' => [],
    ]);
    projects_assert(($task['assignment']['status'] ?? '') === 'DEPENDENCY_FAILURE', 'Failed HR call was reported as assigned.');
    projects_assert(!empty($task['assignment']['retryable']), 'Failed HR call is not retryable.');
    projects_assert((string) $task['assignment_status'] === 'FAILED', 'Failed HR call was not persisted.');
    projects_assert((string) ($task['external_assignment_key'] ?? '') === '', 'Failed HR call claimed an assignment key.');
    projects_assert(count($GLOBALS['projects_assignment_calls']) === 1, 'Initial HR assignment call count is incorrect.');

    $firstPayload = $GLOBALS['projects_assignment_calls'][0]['assignment'];
    foreach (['task_key', 'employee_key', 'due_date', 'priority', 'actor_admin_key', 'idempotency_key'] as $field) {
        projects_assert(trim((string) ($firstPayload[$field] ?? '')) !== '', 'HR assignment payload is missing ' . $field . '.');
    }
    projects_assert((string) $firstPayload['task_key'] === (string) $task['task_key'], 'HR assignment task key changed.');
    projects_assert((string) $firstPayload['employee_key'] === $employeeKey, 'HR assignment employee key changed.');
    projects_assert((string) $firstPayload['due_date'] === $today->modify('+5 days')->format('Y-m-d'), 'HR assignment due date changed.');
    projects_assert((string) $firstPayload['priority'] === 'HIGH', 'HR assignment priority changed.');
    projects_assert((string) $firstPayload['actor_admin_key'] === (string) $admin['admin_key'], 'HR assignment actor changed.');

    $retried = yovel_admin_project_task_retry_assignment($company, $admin, (string) $task['task_key'], $csrf);
    projects_assert(($retried['assignment']['status'] ?? '') === 'ASSIGNED', 'Assignment retry did not report success.');
    projects_assert((string) $retried['assignment_status'] === 'SYNCED', 'Assignment retry was not persisted as synced.');
    projects_assert((string) $retried['external_assignment_key'] === $GLOBALS['projects_assignment_key'], 'Assignment key was not exactly read back.');
    projects_assert(count($GLOBALS['projects_assignment_calls']) === 2, 'Assignment retry call count is incorrect.');
    projects_assert(
        (string) $GLOBALS['projects_assignment_calls'][1]['assignment']['idempotency_key'] === (string) $firstPayload['idempotency_key'],
        'Assignment retry changed its idempotency key.'
    );

    $alreadySynced = yovel_admin_project_task_retry_assignment($company, $admin, (string) $task['task_key'], $csrf);
    projects_assert(($alreadySynced['assignment']['status'] ?? '') === 'ASSIGNED', 'Synced assignment retry is not idempotent.');
    projects_assert(count($GLOBALS['projects_assignment_calls']) === 2, 'Synced assignment called HR again.');

    projects_assert((int) bx_db()->GetOne(
        "SELECT COUNT(*) FROM builder_audit_log
         WHERE module = 'project_company_project_task'
           AND record_key = ?
           AND JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.company_key_hash')) = ?",
        [(string) $task['task_key'], $company['company_key_hash']]
    ) >= 3, 'Task assignment state changes are not audited.');
});

projects_assert(!in_array('project_company_hr_assignment', projects_table_names(), true), 'Projects test schema claims an HR assignment table.');

echo "Projects Task 4 assignment handoff checks passed.\n";
