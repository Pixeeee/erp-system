<?php
declare(strict_types=1);

function yovel_admin_projects_task_types(array $company, array $admin): array
{
    [, $companyKeyHash] = yovel_admin_projects_scope($company, $admin);
    yovel_admin_projects_schema();
    $rows = bx_db()->GetAll(
        "SELECT * FROM project_company_project_task_type
         WHERE company_key_hash = ? AND task_type_status <> 'DELETED'
         ORDER BY task_type_name, task_type_code",
        [$companyKeyHash]
    );
    return is_array($rows) ? $rows : [];
}

function yovel_admin_task_type_upsert(array $company, array $admin, array $input): array
{
    yovel_admin_projects_assert_csrf($input);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    $requestedKey = yovel_admin_projects_optional_uuid($input, 'task_type_key', 'Task Type');
    $code = strtoupper(yovel_admin_projects_required_text($input, 'task_type_code', 'Task Type code', 80));
    if (preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $code) !== 1) {
        throw new InvalidArgumentException('Task Type code is invalid.');
    }
    $name = yovel_admin_projects_required_text($input, 'task_type_name', 'Task Type name', 160);
    $status = strtoupper(trim((string) ($input['task_type_status'] ?? 'ACTIVE')));
    if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
        throw new InvalidArgumentException('Task Type status is invalid.');
    }

    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $requestedKey, $code, $name, $status
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        $byCode = $db->GetRow(
            'SELECT * FROM project_company_project_task_type WHERE company_key_hash = ? AND task_type_code = ? FOR UPDATE',
            [$companyKeyHash, $code]
        );
        $byKey = $requestedKey === null ? [] : $db->GetRow(
            'SELECT * FROM project_company_project_task_type WHERE task_type_key = ? FOR UPDATE',
            [$requestedKey]
        );
        if ($requestedKey !== null && (!is_array($byKey) || $byKey === [] || (string) $byKey['company_key_hash'] !== $companyKeyHash)) {
            throw new InvalidArgumentException('Task Type does not belong to this company.');
        }
        if (is_array($byCode) && $byCode !== [] && $requestedKey !== null && (string) $byCode['task_type_key'] !== $requestedKey) {
            throw new InvalidArgumentException('Task Type code belongs to another Task Type.');
        }
        $existing = $requestedKey !== null ? $byKey : $byCode;
        $key = is_array($existing) && $existing !== [] ? (string) $existing['task_type_key'] : bx_uuid();
        yovel_admin_projects_db_execute(
            $db,
            "INSERT INTO project_company_project_task_type (
                task_type_key, company_key, company_key_hash, task_type_code, task_type_name,
                task_type_status, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                task_type_name = VALUES(task_type_name), task_type_status = VALUES(task_type_status),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$key, $companyKey, $companyKeyHash, $code, $name, $status, $adminKey, $adminKey],
            'Task Type upsert'
        );
        yovel_admin_projects_audit(
            is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE',
            'project_company_project_task_type',
            $key,
            $companyKeyHash,
            $adminKey,
            ['task_type_code' => $code, 'task_type_name' => $name, 'task_type_status' => $status]
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_project_task_type WHERE company_key_hash = ? AND task_type_key = ?',
            [$companyKeyHash, $key]
        );
        yovel_admin_projects_assert_readback(
            [
                'task_type_key' => $key, 'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash, 'task_type_code' => $code,
                'task_type_name' => $name, 'task_type_status' => $status,
                'updated_by_admin_key' => $adminKey,
            ],
            is_array($saved) ? $saved : [],
            ['task_type_key', 'company_key', 'company_key_hash', 'task_type_code', 'task_type_name', 'task_type_status', 'updated_by_admin_key'],
            'Task Type'
        );
        return $saved;
    });
}

function yovel_admin_projects_task_dependency_keys(array $input): array
{
    $raw = $input['dependency_keys'] ?? $input['dependency_keys_json'] ?? [];
    if (is_string($raw)) {
        $trimmed = trim($raw);
        $raw = $trimmed === '' ? [] : json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
    }
    if (!is_array($raw) || count($raw) > 200) {
        throw new InvalidArgumentException('Task dependencies are invalid.');
    }
    $keys = [];
    foreach ($raw as $key) {
        $key = trim((string) $key);
        if (!yovel_admin_is_uuid($key)) {
            throw new InvalidArgumentException('Task dependency key is invalid.');
        }
        $keys[$key] = true;
    }
    $keys = array_keys($keys);
    sort($keys, SORT_STRING);
    return $keys;
}

function yovel_admin_projects_task_input(array $input): array
{
    $projectKey = yovel_admin_projects_optional_uuid($input, 'project_key', 'Project');
    if ($projectKey === null) {
        throw new InvalidArgumentException('Project is required for a Task.');
    }
    $taskKey = yovel_admin_projects_optional_uuid($input, 'task_key', 'Task');
    $parentKey = yovel_admin_projects_optional_uuid($input, 'parent_task_key', 'Parent Task');
    $typeKey = yovel_admin_projects_optional_uuid($input, 'task_type_key', 'Task Type');
    $employeeKey = yovel_admin_projects_optional_uuid($input, 'assigned_employee_key', 'Assigned employee');
    $code = strtoupper(yovel_admin_projects_required_text($input, 'task_code', 'Task code', 80));
    if (preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $code) !== 1) {
        throw new InvalidArgumentException('Task code is invalid.');
    }
    $status = strtoupper(trim((string) ($input['task_status'] ?? 'OPEN')));
    if (!in_array($status, ['OPEN', 'WORKING', 'PENDING_REVIEW', 'OVERDUE', 'COMPLETED', 'CANCELLED'], true)) {
        throw new InvalidArgumentException('Task status is invalid.');
    }
    $priority = strtoupper(trim((string) ($input['priority'] ?? 'MEDIUM')));
    if (!in_array($priority, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], true)) {
        throw new InvalidArgumentException('Task priority is invalid.');
    }
    $progress = yovel_admin_projects_percentage($input['progress'] ?? '0', 'Task percentage');
    $weight = yovel_admin_projects_percentage($input['task_weight'] ?? '0', 'Task weight');
    $startDate = yovel_admin_projects_date($input, 'expected_start_date', 'Task expected start');
    $endDate = yovel_admin_projects_date($input, 'expected_end_date', 'Task expected end');
    if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
        throw new InvalidArgumentException('Task expected date order is invalid.');
    }
    $completedOn = yovel_admin_projects_date($input, 'completed_on', 'Task completed on');
    if ($status === 'COMPLETED') {
        $progress = '100.0000';
        $completedOn ??= date('Y-m-d');
    } else {
        $completedOn = null;
        if (in_array($status, ['OPEN', 'WORKING', 'PENDING_REVIEW', 'OVERDUE'], true) && $endDate !== null) {
            $status = $endDate < date('Y-m-d') ? 'OVERDUE' : ($status === 'OVERDUE' ? 'OPEN' : $status);
        }
    }
    $sortOrder = filter_var($input['sort_order'] ?? 100, FILTER_VALIDATE_INT);
    if ($sortOrder === false || $sortOrder < 0 || $sortOrder > 1000000) {
        throw new InvalidArgumentException('Task sort order is invalid.');
    }
    return [
        'task_key' => $taskKey,
        'project_key' => $projectKey,
        'parent_task_key' => $parentKey,
        'task_type_key' => $typeKey,
        'task_code' => $code,
        'task_subject' => yovel_admin_projects_required_text($input, 'task_subject', 'Task subject', 190),
        'task_status' => $status,
        'priority' => $priority,
        'progress' => $progress,
        'expected_start_date' => $startDate,
        'expected_end_date' => $endDate,
        'completed_on' => $completedOn,
        'is_group' => yovel_admin_projects_flag($input['is_group'] ?? 0),
        'task_weight' => $weight,
        'sort_order' => $sortOrder,
        'assigned_employee_key' => $employeeKey,
        'dependency_keys' => yovel_admin_projects_task_dependency_keys($input),
    ];
}

function yovel_admin_projects_assert_acyclic(array $edges, string $label): void
{
    $visited = [];
    $active = [];
    $visit = static function (string $node) use (&$visit, &$visited, &$active, $edges, $label): void {
        if (!empty($active[$node])) {
            throw new InvalidArgumentException($label . ' cycle is not allowed.');
        }
        if (!empty($visited[$node])) {
            return;
        }
        $active[$node] = true;
        foreach (($edges[$node] ?? []) as $next) {
            $visit((string) $next);
        }
        unset($active[$node]);
        $visited[$node] = true;
    };
    foreach (array_keys($edges) as $node) {
        $visit((string) $node);
    }
}

function yovel_admin_projects_lock_task_graph(
    ADOConnection $db,
    string $companyKeyHash,
    string $projectKey
): array {
    $project = $db->GetRow(
        'SELECT * FROM project_company_project_record WHERE company_key_hash = ? AND project_key = ? FOR UPDATE',
        [$companyKeyHash, $projectKey]
    );
    if (!is_array($project) || $project === []) {
        throw new InvalidArgumentException('Project does not belong to this company.');
    }
    $tasks = $db->GetAll(
        'SELECT * FROM project_company_project_task
         WHERE company_key_hash = ? AND project_key = ? ORDER BY task_key FOR UPDATE',
        [$companyKeyHash, $projectKey]
    );
    $dependencies = $db->GetAll(
        'SELECT dependency.* FROM project_company_project_task_dependency dependency
         INNER JOIN project_company_project_task task
           ON task.task_key = dependency.task_key AND task.company_key_hash = dependency.company_key_hash
         WHERE dependency.company_key_hash = ? AND task.project_key = ?
         ORDER BY dependency.task_key, dependency.depends_on_task_key FOR UPDATE',
        [$companyKeyHash, $projectKey]
    );
    return [
        'project' => $project,
        'tasks' => is_array($tasks) ? $tasks : [],
        'dependencies' => is_array($dependencies) ? $dependencies : [],
    ];
}

function yovel_admin_projects_validate_task_graph(array $values, array $graph, string $taskKey): void
{
    $project = $graph['project'];
    if ($values['expected_start_date'] !== null
        && (string) ($project['expected_start_date'] ?? '') !== ''
        && $values['expected_start_date'] < (string) $project['expected_start_date']) {
        throw new InvalidArgumentException('Task is outside the Project date range.');
    }
    if ($values['expected_end_date'] !== null
        && (string) ($project['expected_end_date'] ?? '') !== ''
        && $values['expected_end_date'] > (string) $project['expected_end_date']) {
        throw new InvalidArgumentException('Task is outside the Project date range.');
    }

    $taskByKey = [];
    $parentEdges = [];
    $dependencyEdges = [];
    foreach ($graph['tasks'] as $task) {
        $key = (string) $task['task_key'];
        $taskByKey[$key] = $task;
        $parent = (string) ($task['parent_task_key'] ?? '');
        $parentEdges[$key] = $parent === '' ? [] : [$parent];
    }
    foreach ($graph['dependencies'] as $dependency) {
        if (($dependency['dependency_status'] ?? '') === 'ACTIVE') {
            $dependencyEdges[(string) $dependency['task_key']][] = (string) $dependency['depends_on_task_key'];
        }
    }
    if ($values['parent_task_key'] !== null) {
        $parent = $taskByKey[$values['parent_task_key']] ?? null;
        if (!is_array($parent) || (int) ($parent['is_group'] ?? 0) !== 1) {
            throw new InvalidArgumentException('Parent Task must be a group Task in the same Project.');
        }
        if ($values['parent_task_key'] === $taskKey) {
            throw new InvalidArgumentException('Task parent cycle is not allowed.');
        }
    }
    foreach ($values['dependency_keys'] as $dependencyKey) {
        if ($dependencyKey === $taskKey || !isset($taskByKey[$dependencyKey])) {
            throw new InvalidArgumentException('Task dependencies must belong to the same Project and cannot reference the Task itself.');
        }
    }
    $parentEdges[$taskKey] = $values['parent_task_key'] === null ? [] : [$values['parent_task_key']];
    $dependencyEdges[$taskKey] = $values['dependency_keys'];
    yovel_admin_projects_assert_acyclic($parentEdges, 'Task parent');
    yovel_admin_projects_assert_acyclic($dependencyEdges, 'Task dependency');

    if ($values['task_status'] === 'COMPLETED') {
        foreach ($values['dependency_keys'] as $dependencyKey) {
            $dependencyStatus = (string) ($taskByKey[$dependencyKey]['task_status'] ?? '');
            if (!in_array($dependencyStatus, ['COMPLETED', 'CANCELLED'], true)) {
                throw new InvalidArgumentException('Task cannot complete until its dependencies are completed or cancelled.');
            }
        }
    }
}

function yovel_admin_projects_task_with_dependencies(ADOConnection $db, array $task): array
{
    $dependencies = $db->GetCol(
        "SELECT depends_on_task_key FROM project_company_project_task_dependency
         WHERE company_key_hash = ? AND task_key = ? AND dependency_status = 'ACTIVE'
         ORDER BY depends_on_task_key",
        [(string) $task['company_key_hash'], (string) $task['task_key']]
    );
    $task['dependency_keys'] = is_array($dependencies) ? array_values(array_map('strval', $dependencies)) : [];
    return $task;
}

function yovel_admin_projects_task_assignment_envelope(array $task): array
{
    $status = (string) ($task['assignment_status'] ?? 'UNASSIGNED');
    $errorCode = (string) ($task['assignment_error_code'] ?? '');
    return [
        'status' => $status === 'SYNCED' ? 'ASSIGNED' : ($status === 'FAILED' ? $errorCode : $status),
        'retryable' => $status === 'FAILED',
        'employee_key' => (string) ($task['assigned_employee_key'] ?? ''),
        'assignment_key' => (string) ($task['external_assignment_key'] ?? ''),
        'idempotency_key' => (string) ($task['assignment_request_key'] ?? ''),
    ];
}

function yovel_admin_projects_persist_assignment_state(
    array $company,
    array $admin,
    string $taskKey,
    string $requestKey,
    string $status,
    ?string $externalKey,
    ?string $errorCode
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $taskKey, $requestKey, $status, $externalKey, $errorCode
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        $task = $db->GetRow(
            'SELECT * FROM project_company_project_task WHERE company_key_hash = ? AND task_key = ? FOR UPDATE',
            [$companyKeyHash, $taskKey]
        );
        if (!is_array($task) || $task === [] || !hash_equals((string) $task['assignment_request_key'], $requestKey)) {
            throw new RuntimeException('Task assignment request is no longer current.');
        }
        yovel_admin_projects_db_execute(
            $db,
            'UPDATE project_company_project_task
             SET assignment_status = ?, external_assignment_key = ?, assignment_error_code = ?, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND task_key = ? AND assignment_request_key = ?',
            [$status, $externalKey, $errorCode, $adminKey, $companyKeyHash, $taskKey, $requestKey],
            'Task assignment state update'
        );
        yovel_admin_projects_audit(
            $status === 'SYNCED' ? 'ASSIGN' : 'ASSIGNMENT_FAILED',
            'project_company_project_task',
            $taskKey,
            $companyKeyHash,
            $adminKey,
            [
                'assignment_status' => $status,
                'assignment_request_key' => $requestKey,
                'external_assignment_key' => $externalKey,
                'assignment_error_code' => $errorCode,
            ]
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_project_task WHERE company_key_hash = ? AND task_key = ?',
            [$companyKeyHash, $taskKey]
        );
        yovel_admin_projects_assert_readback(
            [
                'task_key' => $taskKey, 'assignment_request_key' => $requestKey,
                'assignment_status' => $status, 'external_assignment_key' => $externalKey,
                'assignment_error_code' => $errorCode, 'updated_by_admin_key' => $adminKey,
            ],
            is_array($saved) ? $saved : [],
            ['task_key', 'assignment_request_key', 'assignment_status', 'external_assignment_key', 'assignment_error_code', 'updated_by_admin_key'],
            'Task assignment state'
        );
        return yovel_admin_projects_task_with_dependencies($db, $saved);
    });
}

function yovel_admin_projects_handoff_task_assignment(array $company, array $admin, array $task): array
{
    $requestKey = (string) ($task['assignment_request_key'] ?? '');
    if ((string) ($task['assignment_status'] ?? '') !== 'PENDING' || $requestKey === '') {
        $task['assignment'] = yovel_admin_projects_task_assignment_envelope($task);
        return $task;
    }
    $taskKey = (string) $task['task_key'];
    if (!function_exists('yovel_admin_hr_assign_project_task')) {
        $task = yovel_admin_projects_persist_assignment_state(
            $company, $admin, $taskKey, $requestKey, 'FAILED', null, 'UNAVAILABLE_DEPENDENCY'
        );
        $task['assignment'] = yovel_admin_projects_task_assignment_envelope($task);
        return $task;
    }
    try {
        $response = yovel_admin_hr_assign_project_task($company, $admin, [
            'company_key' => (string) $company['company_key'],
            'company_key_hash' => (string) $company['company_key_hash'],
            'task_key' => $taskKey,
            'employee_key' => (string) $task['assigned_employee_key'],
            'due_date' => (string) ($task['expected_end_date'] ?? ''),
            'priority' => (string) $task['priority'],
            'actor_admin_key' => (string) $admin['admin_key'],
            'idempotency_key' => $requestKey,
        ]);
        $assignmentKey = trim((string) ($response['assignment_key'] ?? ''));
        if (!yovel_admin_is_uuid($assignmentKey)
            || (string) ($response['task_key'] ?? '') !== $taskKey
            || (string) ($response['employee_key'] ?? '') !== (string) $task['assigned_employee_key']
            || (string) ($response['idempotency_key'] ?? '') !== $requestKey
            || (string) ($response['status'] ?? '') !== 'ASSIGNED') {
            throw new RuntimeException('HR assignment response verification failed.');
        }
        $task = yovel_admin_projects_persist_assignment_state(
            $company, $admin, $taskKey, $requestKey, 'SYNCED', $assignmentKey, null
        );
    } catch (Throwable) {
        $task = yovel_admin_projects_persist_assignment_state(
            $company, $admin, $taskKey, $requestKey, 'FAILED', null, 'DEPENDENCY_FAILURE'
        );
    }
    $task['assignment'] = yovel_admin_projects_task_assignment_envelope($task);
    return $task;
}

function yovel_admin_project_task_upsert(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    yovel_admin_projects_assert_csrf($input);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    $values = yovel_admin_projects_task_input($input);
    $form = yovel_admin_projects_active_schema($company, 'TASK');
    $formKey = (string) ($form['form_schema_key'] ?? '');
    $formVersion = (int) ($form['version'] ?? 0);

    $task = yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $values, $formKey, $formVersion, $failureInjector
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        $graph = yovel_admin_projects_lock_task_graph($db, $companyKeyHash, $values['project_key']);
        $byCode = null;
        $byKey = null;
        foreach ($graph['tasks'] as $row) {
            if ((string) $row['task_code'] === $values['task_code']) {
                $byCode = $row;
            }
            if ($values['task_key'] !== null && (string) $row['task_key'] === $values['task_key']) {
                $byKey = $row;
            }
        }
        if ($values['task_key'] !== null && $byKey === null) {
            $foreign = $db->GetRow('SELECT task_key, company_key_hash FROM project_company_project_task WHERE task_key = ? FOR UPDATE', [$values['task_key']]);
            if (is_array($foreign) && $foreign !== []) {
                throw new InvalidArgumentException('Task does not belong to this company.');
            }
            throw new InvalidArgumentException('Task does not belong to this Project.');
        }
        if ($byCode !== null && $values['task_key'] !== null && (string) $byCode['task_key'] !== $values['task_key']) {
            throw new InvalidArgumentException('Task code belongs to another Task.');
        }
        $existing = $values['task_key'] !== null ? $byKey : $byCode;
        $taskKey = is_array($existing) ? (string) $existing['task_key'] : bx_uuid();
        if ($values['task_type_key'] !== null) {
            $taskType = $db->GetRow(
                "SELECT task_type_key FROM project_company_project_task_type
                 WHERE company_key_hash = ? AND task_type_key = ? AND task_type_status <> 'DELETED' FOR UPDATE",
                [$companyKeyHash, $values['task_type_key']]
            );
            if (!is_array($taskType) || $taskType === []) {
                throw new InvalidArgumentException('Task Type does not belong to this company.');
            }
        }
        yovel_admin_projects_validate_task_graph($values, $graph, $taskKey);

        $employeeKey = $values['assigned_employee_key'];
        $requestKey = $employeeKey === null ? null : hash('sha256', $companyKeyHash . '|' . $taskKey . '|' . $employeeKey);
        $assignmentStatus = $employeeKey === null ? 'UNASSIGNED' : 'PENDING';
        $assignmentError = null;
        $externalAssignment = null;
        if (is_array($existing)
            && (string) ($existing['assigned_employee_key'] ?? '') === (string) ($employeeKey ?? '')
            && (string) ($existing['assignment_request_key'] ?? '') === (string) ($requestKey ?? '')
            && in_array((string) ($existing['assignment_status'] ?? ''), ['SYNCED', 'FAILED'], true)) {
            $assignmentStatus = (string) $existing['assignment_status'];
            $assignmentError = ($existing['assignment_error_code'] ?? null) ?: null;
            $externalAssignment = ($existing['external_assignment_key'] ?? null) ?: null;
        }

        yovel_admin_projects_db_execute(
            $db,
            "INSERT INTO project_company_project_task (
                task_key, company_key, company_key_hash, project_key, parent_task_key, task_type_key,
                task_code, task_subject, task_status, priority, progress, expected_start_date,
                expected_end_date, completed_on, is_group, task_weight, sort_order,
                assigned_employee_key, assignment_request_key, assignment_status,
                assignment_error_code, external_assignment_key, form_schema_key, form_schema_version,
                created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                parent_task_key = VALUES(parent_task_key), task_type_key = VALUES(task_type_key),
                task_subject = VALUES(task_subject), task_status = VALUES(task_status),
                priority = VALUES(priority), progress = VALUES(progress),
                expected_start_date = VALUES(expected_start_date), expected_end_date = VALUES(expected_end_date),
                completed_on = VALUES(completed_on), is_group = VALUES(is_group),
                task_weight = VALUES(task_weight), sort_order = VALUES(sort_order),
                assigned_employee_key = VALUES(assigned_employee_key),
                assignment_request_key = VALUES(assignment_request_key),
                assignment_status = VALUES(assignment_status), assignment_error_code = VALUES(assignment_error_code),
                external_assignment_key = VALUES(external_assignment_key),
                form_schema_key = VALUES(form_schema_key), form_schema_version = VALUES(form_schema_version),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $taskKey, $companyKey, $companyKeyHash, $values['project_key'], $values['parent_task_key'], $values['task_type_key'],
                $values['task_code'], $values['task_subject'], $values['task_status'], $values['priority'], $values['progress'],
                $values['expected_start_date'], $values['expected_end_date'], $values['completed_on'], $values['is_group'],
                $values['task_weight'], $values['sort_order'], $employeeKey, $requestKey, $assignmentStatus,
                $assignmentError, $externalAssignment, $formKey !== '' ? $formKey : null, $formVersion > 0 ? $formVersion : null,
                $adminKey, $adminKey,
            ],
            'Project Task upsert'
        );

        yovel_admin_projects_db_execute(
            $db,
            "UPDATE project_company_project_task_dependency
             SET dependency_status = 'REMOVED', updated_by_admin_key = ?
             WHERE company_key_hash = ? AND task_key = ? AND dependency_status = 'ACTIVE'",
            [$adminKey, $companyKeyHash, $taskKey],
            'Task dependency reset'
        );
        $existingDependencies = [];
        foreach ($graph['dependencies'] as $dependency) {
            if ((string) $dependency['task_key'] === $taskKey) {
                $existingDependencies[(string) $dependency['depends_on_task_key']] = $dependency;
            }
        }
        foreach ($values['dependency_keys'] as $dependencyKey) {
            $dependency = $existingDependencies[$dependencyKey] ?? null;
            $dependencyStableKey = is_array($dependency) ? (string) $dependency['task_dependency_key'] : bx_uuid();
            yovel_admin_projects_db_execute(
                $db,
                "INSERT INTO project_company_project_task_dependency (
                    task_dependency_key, company_key, company_key_hash, task_key, depends_on_task_key,
                    dependency_status, created_by_admin_key, updated_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?, ?)
                 ON DUPLICATE KEY UPDATE dependency_status = 'ACTIVE', updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$dependencyStableKey, $companyKey, $companyKeyHash, $taskKey, $dependencyKey, $adminKey, $adminKey],
                'Task dependency upsert'
            );
            yovel_admin_projects_audit(
                is_array($dependency) ? 'RESTORE' : 'CREATE',
                'project_company_project_task_dependency',
                $dependencyStableKey,
                $companyKeyHash,
                $adminKey,
                ['task_key' => $taskKey, 'depends_on_task_key' => $dependencyKey, 'dependency_status' => 'ACTIVE']
            );
        }
        yovel_admin_projects_audit(
            is_array($existing) ? 'UPDATE' : 'CREATE',
            'project_company_project_task',
            $taskKey,
            $companyKeyHash,
            $adminKey,
            ['project_key' => $values['project_key'], 'task_code' => $values['task_code'], 'task_status' => $values['task_status']]
        );
        if ($failureInjector !== null) {
            $failureInjector('after-dependencies');
        }
        yovel_admin_projects_recalculate_locked($db, $graph['project'], $companyKeyHash, $adminKey);
        $saved = $db->GetRow(
            'SELECT * FROM project_company_project_task WHERE company_key_hash = ? AND task_key = ?',
            [$companyKeyHash, $taskKey]
        );
        $expected = [
            'task_key' => $taskKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
            'project_key' => $values['project_key'], 'parent_task_key' => $values['parent_task_key'],
            'task_type_key' => $values['task_type_key'], 'task_code' => $values['task_code'],
            'task_subject' => $values['task_subject'], 'task_status' => $values['task_status'],
            'priority' => $values['priority'], 'progress' => $values['progress'],
            'expected_start_date' => $values['expected_start_date'], 'expected_end_date' => $values['expected_end_date'],
            'completed_on' => $values['completed_on'], 'is_group' => $values['is_group'],
            'task_weight' => $values['task_weight'], 'sort_order' => $values['sort_order'],
            'assigned_employee_key' => $employeeKey, 'assignment_request_key' => $requestKey,
            'assignment_status' => $assignmentStatus, 'assignment_error_code' => $assignmentError,
            'external_assignment_key' => $externalAssignment,
            'form_schema_key' => $formKey !== '' ? $formKey : null,
            'form_schema_version' => $formVersion > 0 ? $formVersion : null,
            'updated_by_admin_key' => $adminKey,
        ];
        yovel_admin_projects_assert_readback(
            $expected,
            is_array($saved) ? $saved : [],
            array_keys($expected),
            'Project Task'
        );
        $saved = yovel_admin_projects_task_with_dependencies($db, $saved);
        if ($saved['dependency_keys'] !== $values['dependency_keys']) {
            throw new RuntimeException('Project Task dependency read-back verification failed.');
        }
        return $saved;
    });
    return yovel_admin_projects_handoff_task_assignment($company, $admin, $task);
}

function yovel_admin_projects_tasks(array $company, array $admin, ?string $projectKey = null): array
{
    [, $companyKeyHash] = yovel_admin_projects_scope($company, $admin);
    yovel_admin_projects_schema();
    if ($projectKey !== null && !yovel_admin_is_uuid($projectKey)) {
        throw new InvalidArgumentException('Project key is invalid.');
    }
    $parameters = [$companyKeyHash];
    $projectWhere = '';
    if ($projectKey !== null) {
        $projectWhere = ' AND project_key = ?';
        $parameters[] = $projectKey;
    }
    $rows = bx_db()->GetAll(
        "SELECT * FROM project_company_project_task
         WHERE company_key_hash = ? AND task_status <> 'DELETED'{$projectWhere}
         ORDER BY sort_order, task_code, task_key",
        $parameters
    );
    $rows = is_array($rows) ? $rows : [];
    $byParent = [];
    $known = array_fill_keys(array_map(static fn (array $row): string => (string) $row['task_key'], $rows), true);
    foreach ($rows as $row) {
        $parent = (string) ($row['parent_task_key'] ?? '');
        if ($parent !== '' && !isset($known[$parent])) {
            $parent = '';
        }
        $byParent[$parent][] = $row;
    }
    $ordered = [];
    $append = static function (string $parent, int $depth) use (&$append, &$ordered, $byParent): void {
        foreach (($byParent[$parent] ?? []) as $row) {
            $row['tree_depth'] = $depth;
            $ordered[] = $row;
            $append((string) $row['task_key'], $depth + 1);
        }
    };
    $append('', 0);
    $db = bx_db();
    foreach ($ordered as &$row) {
        $row = yovel_admin_projects_task_with_dependencies($db, $row);
        $row['assignment'] = yovel_admin_projects_task_assignment_envelope($row);
    }
    unset($row);
    return $ordered;
}

function yovel_admin_projects_scoped_task_project(array $company, array $admin, string $taskKey): array
{
    [, $companyKeyHash] = yovel_admin_projects_scope($company, $admin);
    if (!yovel_admin_is_uuid($taskKey)) {
        throw new InvalidArgumentException('Task key is invalid.');
    }
    $task = bx_db()->GetRow(
        'SELECT task_key, project_key FROM project_company_project_task WHERE company_key_hash = ? AND task_key = ?',
        [$companyKeyHash, $taskKey]
    );
    if (!is_array($task) || $task === []) {
        throw new InvalidArgumentException('Task does not belong to this company.');
    }
    return $task;
}

function yovel_admin_project_task_transition(
    array $company,
    array $admin,
    string $taskKey,
    string $action,
    string $csrf = ''
): array {
    yovel_admin_projects_assert_csrf(['csrf' => $csrf]);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    $scoped = yovel_admin_projects_scoped_task_project($company, $admin, $taskKey);
    $projectKey = (string) $scoped['project_key'];
    $transitions = [
        'START' => 'WORKING', 'REVIEW' => 'PENDING_REVIEW', 'COMPLETE' => 'COMPLETED',
        'CANCEL' => 'CANCELLED', 'REOPEN' => 'OPEN', 'ARCHIVE' => 'DELETED',
    ];
    $action = strtoupper(trim($action));
    if (!isset($transitions[$action])) {
        throw new InvalidArgumentException('Task transition is invalid.');
    }
    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $projectKey, $taskKey, $action, $transitions
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        $graph = yovel_admin_projects_lock_task_graph($db, $companyKeyHash, $projectKey);
        $taskByKey = [];
        foreach ($graph['tasks'] as $task) {
            $taskByKey[(string) $task['task_key']] = $task;
        }
        $task = $taskByKey[$taskKey] ?? null;
        if (!is_array($task)) {
            throw new InvalidArgumentException('Task does not belong to this Project.');
        }
        $status = $transitions[$action];
        if ($status === 'COMPLETED') {
            foreach ($graph['dependencies'] as $dependency) {
                if ((string) $dependency['task_key'] !== $taskKey || (string) $dependency['dependency_status'] !== 'ACTIVE') {
                    continue;
                }
                $dependencyStatus = (string) ($taskByKey[(string) $dependency['depends_on_task_key']]['task_status'] ?? '');
                if (!in_array($dependencyStatus, ['COMPLETED', 'CANCELLED'], true)) {
                    throw new InvalidArgumentException('Task cannot complete until its dependencies are completed or cancelled.');
                }
            }
        }
        $progress = $status === 'COMPLETED' ? '100.0000' : (string) $task['progress'];
        $completedOn = $status === 'COMPLETED' ? date('Y-m-d') : null;
        yovel_admin_projects_db_execute(
            $db,
            'UPDATE project_company_project_task
             SET task_status = ?, progress = ?, completed_on = ?, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND task_key = ?',
            [$status, $progress, $completedOn, $adminKey, $companyKeyHash, $taskKey],
            'Task transition'
        );
        yovel_admin_projects_audit(
            $action, 'project_company_project_task', $taskKey, $companyKeyHash, $adminKey,
            ['task_status' => $status, 'progress' => $progress, 'completed_on' => $completedOn]
        );
        yovel_admin_projects_recalculate_locked($db, $graph['project'], $companyKeyHash, $adminKey);
        $saved = $db->GetRow(
            'SELECT * FROM project_company_project_task WHERE company_key_hash = ? AND task_key = ?',
            [$companyKeyHash, $taskKey]
        );
        yovel_admin_projects_assert_readback(
            ['task_key' => $taskKey, 'task_status' => $status, 'progress' => $progress, 'completed_on' => $completedOn, 'updated_by_admin_key' => $adminKey],
            is_array($saved) ? $saved : [],
            ['task_key', 'task_status', 'progress', 'completed_on', 'updated_by_admin_key'],
            'Task transition'
        );
        return yovel_admin_projects_task_with_dependencies($db, $saved);
    });
}

function yovel_admin_project_task_move(
    array $company,
    array $admin,
    string $taskKey,
    ?string $parentTaskKey,
    string $csrf = ''
): array {
    yovel_admin_projects_assert_csrf(['csrf' => $csrf]);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    if ($parentTaskKey !== null && !yovel_admin_is_uuid($parentTaskKey)) {
        throw new InvalidArgumentException('Parent Task key is invalid.');
    }
    $projectKey = (string) yovel_admin_projects_scoped_task_project($company, $admin, $taskKey)['project_key'];
    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $projectKey, $taskKey, $parentTaskKey
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        $graph = yovel_admin_projects_lock_task_graph($db, $companyKeyHash, $projectKey);
        $taskByKey = [];
        $parentEdges = [];
        foreach ($graph['tasks'] as $task) {
            $key = (string) $task['task_key'];
            $taskByKey[$key] = $task;
            $parent = (string) ($task['parent_task_key'] ?? '');
            $parentEdges[$key] = $parent === '' ? [] : [$parent];
        }
        if (!isset($taskByKey[$taskKey])) {
            throw new InvalidArgumentException('Task does not belong to this Project.');
        }
        if ($parentTaskKey !== null && (!isset($taskByKey[$parentTaskKey]) || (int) $taskByKey[$parentTaskKey]['is_group'] !== 1)) {
            throw new InvalidArgumentException('Parent Task must be a group Task in the same Project.');
        }
        $parentEdges[$taskKey] = $parentTaskKey === null ? [] : [$parentTaskKey];
        yovel_admin_projects_assert_acyclic($parentEdges, 'Task parent');
        yovel_admin_projects_db_execute(
            $db,
            'UPDATE project_company_project_task SET parent_task_key = ?, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND task_key = ?',
            [$parentTaskKey, $adminKey, $companyKeyHash, $taskKey],
            'Task move'
        );
        yovel_admin_projects_audit(
            'MOVE', 'project_company_project_task', $taskKey, $companyKeyHash, $adminKey,
            ['parent_task_key' => $parentTaskKey]
        );
        yovel_admin_projects_recalculate_locked($db, $graph['project'], $companyKeyHash, $adminKey);
        $saved = $db->GetRow(
            'SELECT * FROM project_company_project_task WHERE company_key_hash = ? AND task_key = ?',
            [$companyKeyHash, $taskKey]
        );
        yovel_admin_projects_assert_readback(
            ['task_key' => $taskKey, 'parent_task_key' => $parentTaskKey, 'updated_by_admin_key' => $adminKey],
            is_array($saved) ? $saved : [],
            ['task_key', 'parent_task_key', 'updated_by_admin_key'],
            'Task move'
        );
        return yovel_admin_projects_task_with_dependencies($db, $saved);
    });
}

function yovel_admin_project_task_reschedule(
    array $company,
    array $admin,
    string $taskKey,
    string $startDate,
    string $endDate,
    string $csrf = ''
): array {
    yovel_admin_projects_assert_csrf(['csrf' => $csrf]);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    $dates = [
        'expected_start_date' => $startDate,
        'expected_end_date' => $endDate,
    ];
    $startDate = yovel_admin_projects_date($dates, 'expected_start_date', 'Task expected start', true);
    $endDate = yovel_admin_projects_date($dates, 'expected_end_date', 'Task expected end', true);
    if ($startDate > $endDate) {
        throw new InvalidArgumentException('Task expected date order is invalid.');
    }
    $projectKey = (string) yovel_admin_projects_scoped_task_project($company, $admin, $taskKey)['project_key'];
    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $projectKey, $taskKey, $startDate, $endDate
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        $graph = yovel_admin_projects_lock_task_graph($db, $companyKeyHash, $projectKey);
        $taskByKey = [];
        foreach ($graph['tasks'] as $task) {
            $taskByKey[(string) $task['task_key']] = $task;
        }
        $task = $taskByKey[$taskKey] ?? null;
        if (!is_array($task)) {
            throw new InvalidArgumentException('Task does not belong to this Project.');
        }
        if ((string) ($graph['project']['expected_start_date'] ?? '') !== '' && $startDate < (string) $graph['project']['expected_start_date']) {
            throw new InvalidArgumentException('Task is outside the Project date range.');
        }
        if ((string) ($graph['project']['expected_end_date'] ?? '') !== '' && $endDate > (string) $graph['project']['expected_end_date']) {
            throw new InvalidArgumentException('Task is outside the Project date range.');
        }
        $oldEnd = (string) ($task['expected_end_date'] ?? '');
        $deltaDays = $oldEnd === '' ? 0 : (int) (new DateTimeImmutable($oldEnd))->diff(new DateTimeImmutable($endDate))->format('%r%a');
        $dependents = [];
        foreach ($graph['dependencies'] as $dependency) {
            if ((string) $dependency['dependency_status'] === 'ACTIVE') {
                $dependents[(string) $dependency['depends_on_task_key']][] = (string) $dependency['task_key'];
            }
        }
        $queue = $dependents[$taskKey] ?? [];
        $downstream = [];
        while ($queue !== []) {
            sort($queue, SORT_STRING);
            $next = array_shift($queue);
            if (isset($downstream[$next])) {
                continue;
            }
            $downstream[$next] = true;
            foreach (($dependents[$next] ?? []) as $child) {
                $queue[] = $child;
            }
        }
        $updates = [$taskKey => [$startDate, $endDate]];
        foreach (array_keys($downstream) as $dependentKey) {
            $dependent = $taskByKey[$dependentKey];
            $dependentStart = trim((string) ($dependent['expected_start_date'] ?? '')) ?: null;
            $dependentEnd = trim((string) ($dependent['expected_end_date'] ?? '')) ?: null;
            if ($deltaDays !== 0) {
                $modifier = ($deltaDays >= 0 ? '+' : '') . $deltaDays . ' days';
                if ($dependentStart !== null) {
                    $dependentStart = (new DateTimeImmutable($dependentStart))->modify($modifier)->format('Y-m-d');
                }
                if ($dependentEnd !== null) {
                    $dependentEnd = (new DateTimeImmutable($dependentEnd))->modify($modifier)->format('Y-m-d');
                }
            }
            if ($dependentStart !== null
                && (string) ($graph['project']['expected_start_date'] ?? '') !== ''
                && $dependentStart < (string) $graph['project']['expected_start_date']) {
                throw new InvalidArgumentException('Dependent Task is outside the Project date range.');
            }
            if ($dependentEnd !== null
                && (string) ($graph['project']['expected_end_date'] ?? '') !== ''
                && $dependentEnd > (string) $graph['project']['expected_end_date']) {
                throw new InvalidArgumentException('Dependent Task is outside the Project date range.');
            }
            $updates[$dependentKey] = [$dependentStart, $dependentEnd];
        }
        ksort($updates, SORT_STRING);
        foreach ($updates as $updatedTaskKey => [$updatedStart, $updatedEnd]) {
            $currentStatus = (string) $taskByKey[$updatedTaskKey]['task_status'];
            $nextStatus = $currentStatus;
            if ($updatedEnd !== null && in_array($currentStatus, ['OPEN', 'WORKING', 'PENDING_REVIEW', 'OVERDUE'], true)) {
                $nextStatus = $updatedEnd < date('Y-m-d') ? 'OVERDUE' : ($currentStatus === 'OVERDUE' ? 'OPEN' : $currentStatus);
            }
            yovel_admin_projects_db_execute(
                $db,
                'UPDATE project_company_project_task
                 SET expected_start_date = ?, expected_end_date = ?, task_status = ?, updated_by_admin_key = ?
                 WHERE company_key_hash = ? AND task_key = ?',
                [$updatedStart, $updatedEnd, $nextStatus, $adminKey, $companyKeyHash, $updatedTaskKey],
                'Task reschedule'
            );
            yovel_admin_projects_audit(
                'RESCHEDULE', 'project_company_project_task', $updatedTaskKey, $companyKeyHash, $adminKey,
                ['expected_start_date' => $updatedStart, 'expected_end_date' => $updatedEnd, 'task_status' => $nextStatus]
            );
        }
        yovel_admin_projects_recalculate_locked($db, $graph['project'], $companyKeyHash, $adminKey);
        $saved = $db->GetRow(
            'SELECT * FROM project_company_project_task WHERE company_key_hash = ? AND task_key = ?',
            [$companyKeyHash, $taskKey]
        );
        yovel_admin_projects_assert_readback(
            ['task_key' => $taskKey, 'expected_start_date' => $startDate, 'expected_end_date' => $endDate, 'updated_by_admin_key' => $adminKey],
            is_array($saved) ? $saved : [],
            ['task_key', 'expected_start_date', 'expected_end_date', 'updated_by_admin_key'],
            'Task reschedule'
        );
        return yovel_admin_projects_task_with_dependencies($db, $saved);
    });
}

function yovel_admin_project_task_retry_assignment(
    array $company,
    array $admin,
    string $taskKey,
    string $csrf = ''
): array {
    yovel_admin_projects_assert_csrf(['csrf' => $csrf]);
    [, $companyKeyHash] = yovel_admin_projects_scope($company, $admin);
    yovel_admin_projects_schema();
    if (!yovel_admin_is_uuid($taskKey)) {
        throw new InvalidArgumentException('Task key is invalid.');
    }
    $task = bx_db()->GetRow(
        'SELECT * FROM project_company_project_task WHERE company_key_hash = ? AND task_key = ?',
        [$companyKeyHash, $taskKey]
    );
    if (!is_array($task) || $task === []) {
        throw new InvalidArgumentException('Task does not belong to this company.');
    }
    $task = yovel_admin_projects_task_with_dependencies(bx_db(), $task);
    if ((string) ($task['assignment_status'] ?? '') === 'SYNCED') {
        $task['assignment'] = yovel_admin_projects_task_assignment_envelope($task);
        return $task;
    }
    if ((string) ($task['assigned_employee_key'] ?? '') === '' || (string) ($task['assignment_request_key'] ?? '') === '') {
        throw new InvalidArgumentException('Task has no assignment to retry.');
    }
    $task = yovel_admin_projects_persist_assignment_state(
        $company,
        $admin,
        $taskKey,
        (string) $task['assignment_request_key'],
        'PENDING',
        null,
        null
    );
    return yovel_admin_projects_handoff_task_assignment($company, $admin, $task);
}
