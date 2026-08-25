<?php
declare(strict_types=1);

function yovel_admin_projects_template_tasks(array $input): array
{
    $raw = $input['tasks_json'] ?? '[]';
    $tasks = is_array($raw) ? $raw : json_decode(trim((string) $raw) ?: '[]', true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($tasks) || count($tasks) > 200) {
        throw new InvalidArgumentException('Project Template tasks must be a list of at most 200 rows.');
    }
    $normalized = [];
    $keys = [];
    foreach (array_values($tasks) as $index => $task) {
        if (!is_array($task)) {
            throw new InvalidArgumentException('Project Template task row is invalid.');
        }
        $key = trim((string) ($task['project_template_task_key'] ?? ''));
        if ($key === '') {
            $key = bx_uuid();
        }
        if (!yovel_admin_is_uuid($key) || isset($keys[$key])) {
            throw new InvalidArgumentException('Project Template task keys must be unique UUIDs.');
        }
        $keys[$key] = true;
        $subject = yovel_admin_projects_required_text($task, 'task_subject', 'Template task subject', 190);
        $relativeStart = filter_var($task['relative_start_days'] ?? 0, FILTER_VALIDATE_INT);
        $duration = filter_var($task['duration_days'] ?? 1, FILTER_VALIDATE_INT);
        $sortOrder = filter_var($task['sort_order'] ?? (($index + 1) * 10), FILTER_VALIDATE_INT);
        if ($relativeStart === false || $relativeStart < 0 || $relativeStart > 3650) {
            throw new InvalidArgumentException('Template task relative start must be between 0 and 3650 days.');
        }
        if ($duration === false || $duration < 1 || $duration > 3650) {
            throw new InvalidArgumentException('Template task duration must be between 1 and 3650 days.');
        }
        if ($sortOrder === false || $sortOrder < 1 || $sortOrder > 1000000) {
            throw new InvalidArgumentException('Template task sort order is invalid.');
        }
        $normalized[] = [
            'project_template_task_key' => $key,
            'parent_template_task_key' => trim((string) ($task['parent_template_task_key'] ?? '')),
            'source_task_key' => trim((string) ($task['depends_on_template_task_key'] ?? $task['source_task_key'] ?? '')),
            'task_subject' => $subject,
            'relative_start_days' => $relativeStart,
            'duration_days' => $duration,
            'task_weight' => yovel_admin_projects_percentage($task['task_weight'] ?? '0', 'Template task weight'),
            'sort_order' => $sortOrder,
        ];
    }
    foreach ($normalized as $task) {
        foreach (['parent_template_task_key', 'source_task_key'] as $reference) {
            $referencedKey = (string) $task[$reference];
            if ($referencedKey !== '' && (!isset($keys[$referencedKey]) || $referencedKey === $task['project_template_task_key'])) {
                throw new InvalidArgumentException('Template task references must target another task in the same template.');
            }
        }
    }
    foreach (['parent_template_task_key', 'source_task_key'] as $edgeField) {
        $edges = [];
        foreach ($normalized as $task) {
            if ($task[$edgeField] !== '') {
                $edges[$task['project_template_task_key']] = $task[$edgeField];
            }
        }
        foreach (array_keys($keys) as $start) {
            $seen = [];
            $cursor = $start;
            while (isset($edges[$cursor])) {
                if (isset($seen[$cursor])) {
                    throw new InvalidArgumentException('Project Template task graph contains a cycle.');
                }
                $seen[$cursor] = true;
                $cursor = $edges[$cursor];
            }
        }
    }
    usort($normalized, static fn (array $left, array $right): int => [$left['sort_order'], $left['project_template_task_key']] <=> [$right['sort_order'], $right['project_template_task_key']]);
    return $normalized;
}

function yovel_admin_projects_template_input(array $input): array
{
    $requestedKey = yovel_admin_projects_optional_uuid($input, 'project_template_key', 'Project Template');
    $code = strtoupper(yovel_admin_projects_required_text($input, 'template_code', 'Template code', 80));
    if (preg_match('/^[A-Z0-9][A-Z0-9_-]{0,79}$/', $code) !== 1) {
        throw new InvalidArgumentException('Project Template code is invalid.');
    }
    $name = yovel_admin_projects_required_text($input, 'template_name', 'Template name', 190);
    $typeKey = yovel_admin_projects_optional_uuid($input, 'project_type_key', 'Project Type');
    $status = strtoupper(trim((string) ($input['template_status'] ?? 'DRAFT')));
    if (!in_array($status, ['DRAFT', 'ACTIVE', 'ARCHIVED'], true)) {
        throw new InvalidArgumentException('Project Template status is invalid.');
    }
    return [
        'project_template_key' => $requestedKey,
        'template_code' => $code,
        'template_name' => $name,
        'project_type_key' => $typeKey,
        'template_status' => $status,
        'tasks' => yovel_admin_projects_template_tasks($input),
    ];
}

function yovel_admin_projects_templates(array $company, array $admin): array
{
    [, $companyKeyHash] = yovel_admin_projects_scope($company, $admin);
    yovel_admin_projects_schema();
    $db = bx_db();
    $templates = $db->GetAll(
        "SELECT template.*, project_type.project_type_name
         FROM project_company_project_template template
         LEFT JOIN project_company_project_type project_type
           ON project_type.company_key_hash = template.company_key_hash
          AND project_type.project_type_key = template.project_type_key
         WHERE template.company_key_hash = ? AND template.template_status <> 'DELETED'
         ORDER BY template.template_name, template.project_template_key LIMIT 200",
        [$companyKeyHash]
    );
    $templates = is_array($templates) ? $templates : [];
    foreach ($templates as &$template) {
        $tasks = $db->GetAll(
            'SELECT * FROM project_company_project_template_task
             WHERE company_key_hash = ? AND project_template_key = ?
             ORDER BY sort_order, project_template_task_key',
            [$companyKeyHash, $template['project_template_key']]
        );
        $template['tasks'] = is_array($tasks) ? $tasks : [];
    }
    unset($template);
    return $templates;
}

function yovel_admin_project_template_upsert(array $company, array $admin, array $input): array
{
    yovel_admin_projects_assert_csrf($input);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    $values = yovel_admin_projects_template_input($input);
    $form = yovel_admin_projects_active_schema($company, 'PROJECT_TEMPLATE');
    $formKey = !empty($form['persisted']) ? (string) $form['form_schema_key'] : null;
    $formVersion = !empty($form['persisted']) ? (int) $form['version'] : null;

    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $values, $formKey, $formVersion
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        yovel_admin_projects_verify_project_type($db, $companyKeyHash, $values['project_type_key']);
        $requestedKey = $values['project_template_key'];
        $byKey = $requestedKey === null ? false : $db->GetRow(
            'SELECT * FROM project_company_project_template WHERE company_key_hash = ? AND project_template_key = ? FOR UPDATE',
            [$companyKeyHash, $requestedKey]
        );
        if ($requestedKey !== null && (!is_array($byKey) || $byKey === [])) {
            throw new InvalidArgumentException('Project Template key does not belong to this company.');
        }
        $byCode = $db->GetRow(
            'SELECT * FROM project_company_project_template WHERE company_key_hash = ? AND template_code = ? FOR UPDATE',
            [$companyKeyHash, $values['template_code']]
        );
        if ($requestedKey !== null && is_array($byCode) && $byCode !== []
            && (string) $byCode['project_template_key'] !== $requestedKey) {
            throw new InvalidArgumentException('Project Template code belongs to another record.');
        }
        $existing = is_array($byKey) && $byKey !== [] ? $byKey : (is_array($byCode) ? $byCode : []);
        $templateKey = $existing !== [] ? (string) $existing['project_template_key'] : bx_uuid();
        yovel_admin_projects_db_execute(
            $db,
            "INSERT INTO project_company_project_template (
                project_template_key, company_key, company_key_hash, template_code,
                template_name, project_type_key, template_status, form_schema_key,
                form_schema_version, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                template_name = VALUES(template_name),
                project_type_key = VALUES(project_type_key),
                template_status = VALUES(template_status),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $templateKey, $companyKey, $companyKeyHash, $values['template_code'],
                $values['template_name'], $values['project_type_key'], $values['template_status'],
                $formKey, $formVersion, $adminKey, $adminKey,
            ],
            'Project Template save'
        );
        $newTaskKeys = array_column($values['tasks'], 'project_template_task_key');
        $existingTasks = $db->GetAll(
            'SELECT project_template_task_key FROM project_company_project_template_task
             WHERE company_key_hash = ? AND project_template_key = ? FOR UPDATE',
            [$companyKeyHash, $templateKey]
        );
        foreach (is_array($existingTasks) ? $existingTasks : [] as $existingTask) {
            if (!in_array((string) $existingTask['project_template_task_key'], $newTaskKeys, true)) {
                yovel_admin_projects_db_execute(
                    $db,
                    'DELETE FROM project_company_project_template_task
                     WHERE company_key_hash = ? AND project_template_key = ? AND project_template_task_key = ?',
                    [$companyKeyHash, $templateKey, $existingTask['project_template_task_key']],
                    'Project Template task removal'
                );
            }
        }
        foreach ($values['tasks'] as $task) {
            $taskOwner = $db->GetRow(
                'SELECT company_key_hash, project_template_key
                 FROM project_company_project_template_task
                 WHERE project_template_task_key = ? FOR UPDATE',
                [$task['project_template_task_key']]
            );
            if (is_array($taskOwner) && $taskOwner !== []
                && ((string) $taskOwner['company_key_hash'] !== $companyKeyHash
                    || (string) $taskOwner['project_template_key'] !== $templateKey)) {
                throw new InvalidArgumentException('Project Template task key belongs to another company or template.');
            }
            yovel_admin_projects_db_execute(
                $db,
                "INSERT INTO project_company_project_template_task (
                    project_template_task_key, company_key, company_key_hash, project_template_key,
                    source_task_key, parent_template_task_key, task_subject, relative_start_days,
                    duration_days, task_weight, sort_order, created_by_admin_key, updated_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    source_task_key = VALUES(source_task_key),
                    parent_template_task_key = VALUES(parent_template_task_key),
                    task_subject = VALUES(task_subject),
                    relative_start_days = VALUES(relative_start_days),
                    duration_days = VALUES(duration_days),
                    task_weight = VALUES(task_weight),
                    sort_order = VALUES(sort_order),
                    updated_by_admin_key = VALUES(updated_by_admin_key)",
                [
                    $task['project_template_task_key'], $companyKey, $companyKeyHash, $templateKey,
                    $task['source_task_key'] ?: null, $task['parent_template_task_key'] ?: null,
                    $task['task_subject'], $task['relative_start_days'], $task['duration_days'],
                    $task['task_weight'], $task['sort_order'], $adminKey, $adminKey,
                ],
                'Project Template task save'
            );
            yovel_admin_projects_audit(
                'UPSERT', 'project_company_project_template_task', $task['project_template_task_key'],
                $companyKeyHash, $adminKey,
                ['project_template_key' => $templateKey, 'task_subject' => $task['task_subject']]
            );
        }
        yovel_admin_projects_audit(
            $existing === [] ? 'CREATE' : 'UPDATE',
            'project_company_project_template',
            $templateKey,
            $companyKeyHash,
            $adminKey,
            ['template_code' => $values['template_code'], 'template_status' => $values['template_status']]
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_project_template WHERE company_key_hash = ? AND project_template_key = ?',
            [$companyKeyHash, $templateKey]
        );
        yovel_admin_projects_assert_readback(
            [
                'project_template_key' => $templateKey, 'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash, 'template_code' => $values['template_code'],
                'template_name' => $values['template_name'], 'project_type_key' => $values['project_type_key'],
                'template_status' => $values['template_status'], 'updated_by_admin_key' => $adminKey,
            ],
            is_array($saved) ? $saved : [],
            [
                'project_template_key', 'company_key', 'company_key_hash', 'template_code',
                'template_name', 'project_type_key', 'template_status', 'updated_by_admin_key',
            ],
            'Project Template'
        );
        $savedTasks = $db->GetAll(
            'SELECT * FROM project_company_project_template_task
             WHERE company_key_hash = ? AND project_template_key = ?
             ORDER BY sort_order, project_template_task_key',
            [$companyKeyHash, $templateKey]
        );
        $savedTasks = is_array($savedTasks) ? $savedTasks : [];
        if (count($savedTasks) !== count($values['tasks'])) {
            throw new RuntimeException('Project Template task read-back count failed.');
        }
        foreach ($values['tasks'] as $index => $expectedTask) {
            yovel_admin_projects_assert_readback(
                [
                    'project_template_task_key' => $expectedTask['project_template_task_key'],
                    'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                    'project_template_key' => $templateKey,
                    'source_task_key' => $expectedTask['source_task_key'],
                    'parent_template_task_key' => $expectedTask['parent_template_task_key'],
                    'task_subject' => $expectedTask['task_subject'],
                    'relative_start_days' => $expectedTask['relative_start_days'],
                    'duration_days' => $expectedTask['duration_days'],
                    'task_weight' => $expectedTask['task_weight'],
                    'sort_order' => $expectedTask['sort_order'],
                    'updated_by_admin_key' => $adminKey,
                ],
                $savedTasks[$index] ?? [],
                [
                    'project_template_task_key', 'company_key', 'company_key_hash',
                    'project_template_key', 'source_task_key', 'parent_template_task_key',
                    'task_subject', 'relative_start_days', 'duration_days', 'task_weight',
                    'sort_order', 'updated_by_admin_key',
                ],
                'Project Template task'
            );
        }
        $saved['tasks'] = $savedTasks;
        return $saved;
    });
}

function yovel_admin_project_create_from_template(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    yovel_admin_projects_assert_csrf($input);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    $templateKey = yovel_admin_projects_optional_uuid($input, 'project_template_key', 'Project Template');
    if ($templateKey === null) {
        throw new InvalidArgumentException('Project Template is required.');
    }
    $projectValues = yovel_admin_projects_normalize_project_input($company, $input);
    $startDate = yovel_admin_projects_date($input, 'expected_start_date', 'Expected start', true);
    $form = yovel_admin_projects_active_schema($company, 'PROJECT');
    $projectValues['form_schema_key'] = !empty($form['persisted']) ? (string) $form['form_schema_key'] : null;
    $projectValues['form_schema_version'] = !empty($form['persisted']) ? (int) $form['version'] : null;
    $taskForm = yovel_admin_projects_active_schema($company, 'TASK');
    $taskFormKey = !empty($taskForm['persisted']) ? (string) $taskForm['form_schema_key'] : null;
    $taskFormVersion = !empty($taskForm['persisted']) ? (int) $taskForm['version'] : null;

    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $templateKey, $projectValues,
        $startDate, $taskFormKey, $taskFormVersion, $failureInjector
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        $template = $db->GetRow(
            "SELECT * FROM project_company_project_template
             WHERE company_key_hash = ? AND project_template_key = ? AND template_status = 'ACTIVE'
             FOR UPDATE",
            [$companyKeyHash, $templateKey]
        );
        if (!is_array($template) || $template === []) {
            throw new InvalidArgumentException('Active Project Template does not belong to this company.');
        }
        $templateTasks = $db->GetAll(
            'SELECT * FROM project_company_project_template_task
             WHERE company_key_hash = ? AND project_template_key = ?
             ORDER BY sort_order, project_template_task_key FOR UPDATE',
            [$companyKeyHash, $templateKey]
        );
        $templateTasks = is_array($templateTasks) ? $templateTasks : [];
        $values = $projectValues;
        $values['project_type_key'] = $values['project_type_key'] ?? $template['project_type_key'];
        yovel_admin_projects_verify_project_type($db, $companyKeyHash, $values['project_type_key']);
        $maxOffset = 0;
        foreach ($templateTasks as $task) {
            $maxOffset = max($maxOffset, (int) $task['relative_start_days'] + (int) $task['duration_days']);
        }
        $values['expected_start_date'] = $startDate;
        $values['expected_end_date'] = (new DateTimeImmutable($startDate))->modify('+' . $maxOffset . ' days')->format('Y-m-d');
        $project = yovel_admin_projects_write_project($db, $companyKey, $companyKeyHash, $adminKey, $values);
        $projectKey = (string) $project['project_key'];
        $taskKeyMap = [];
        foreach ($templateTasks as $task) {
            $taskKeyMap[(string) $task['project_template_task_key']] = bx_uuid();
        }
        $createdTasks = [];
        foreach ($templateTasks as $index => $task) {
            $templateTaskKey = (string) $task['project_template_task_key'];
            $taskKey = $taskKeyMap[$templateTaskKey];
            $parentTemplateKey = (string) ($task['parent_template_task_key'] ?? '');
            $parentTaskKey = $parentTemplateKey !== '' ? ($taskKeyMap[$parentTemplateKey] ?? null) : null;
            $taskStart = (new DateTimeImmutable($startDate))->modify('+' . (int) $task['relative_start_days'] . ' days');
            $taskEnd = $taskStart->modify('+' . (int) $task['duration_days'] . ' days');
            $taskCode = $values['project_code'] . '-T' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            yovel_admin_projects_db_execute(
                $db,
                "INSERT INTO project_company_project_task (
                    task_key, company_key, company_key_hash, project_key, parent_task_key,
                    task_code, task_subject, task_status, priority, progress,
                    expected_start_date, expected_end_date, is_group, task_weight,
                    form_schema_key, form_schema_version, created_by_admin_key, updated_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, 'OPEN', 'MEDIUM', 0, ?, ?, 0, ?, ?, ?, ?, ?)",
                [
                    $taskKey, $companyKey, $companyKeyHash, $projectKey, $parentTaskKey,
                    $taskCode, $task['task_subject'], $taskStart->format('Y-m-d'), $taskEnd->format('Y-m-d'),
                    $task['task_weight'], $taskFormKey, $taskFormVersion, $adminKey, $adminKey,
                ],
                'Project Template task instantiation'
            );
            yovel_admin_projects_audit(
                'CREATE', 'project_company_project_task', $taskKey, $companyKeyHash, $adminKey,
                ['project_key' => $projectKey, 'template_task_key' => $templateTaskKey]
            );
            $createdTasks[$templateTaskKey] = [
                'task_key' => $taskKey,
                'parent_task_key' => $parentTaskKey,
                'task_code' => $taskCode,
                'task_subject' => (string) $task['task_subject'],
                'expected_start_date' => $taskStart->format('Y-m-d'),
                'expected_end_date' => $taskEnd->format('Y-m-d'),
            ];
        }
        $expectedDependencies = [];
        foreach ($templateTasks as $task) {
            $sourceTemplateKey = (string) ($task['source_task_key'] ?? '');
            if ($sourceTemplateKey === '') {
                continue;
            }
            $taskKey = $taskKeyMap[(string) $task['project_template_task_key']];
            $dependsOn = $taskKeyMap[$sourceTemplateKey];
            $dependencyKey = bx_uuid();
            yovel_admin_projects_db_execute(
                $db,
                "INSERT INTO project_company_project_task_dependency (
                    task_dependency_key, company_key, company_key_hash, task_key,
                    depends_on_task_key, dependency_status, created_by_admin_key, updated_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                [$dependencyKey, $companyKey, $companyKeyHash, $taskKey, $dependsOn, $adminKey, $adminKey],
                'Project Template dependency instantiation'
            );
            yovel_admin_projects_audit(
                'CREATE', 'project_company_project_task_dependency', $dependencyKey,
                $companyKeyHash, $adminKey, ['task_key' => $taskKey, 'depends_on_task_key' => $dependsOn]
            );
            $expectedDependencies[$taskKey] = $dependsOn;
        }
        if ($failureInjector !== null) {
            $failureInjector('after-dependencies');
        }
        $savedProject = $db->GetRow(
            'SELECT * FROM project_company_project_record WHERE company_key_hash = ? AND project_key = ?',
            [$companyKeyHash, $projectKey]
        );
        yovel_admin_projects_assert_readback(
            ['project_key' => $projectKey, 'project_code' => $values['project_code'], 'expected_start_date' => $values['expected_start_date'], 'expected_end_date' => $values['expected_end_date']],
            is_array($savedProject) ? $savedProject : [],
            ['project_key', 'project_code', 'expected_start_date', 'expected_end_date'],
            'Instantiated Project'
        );
        $savedTasks = $db->GetAll(
            'SELECT * FROM project_company_project_task WHERE company_key_hash = ? AND project_key = ? ORDER BY task_code',
            [$companyKeyHash, $projectKey]
        );
        if (!is_array($savedTasks) || count($savedTasks) !== count($createdTasks)) {
            throw new RuntimeException('Instantiated Project task read-back count failed.');
        }
        foreach ($savedTasks as $savedTask) {
            $templateTask = array_search((string) $savedTask['task_key'], $taskKeyMap, true);
            if ($templateTask === false || !isset($createdTasks[$templateTask])) {
                throw new RuntimeException('Instantiated Project task key mapping failed.');
            }
            yovel_admin_projects_assert_readback(
                $createdTasks[$templateTask] + ['project_key' => $projectKey, 'company_key_hash' => $companyKeyHash],
                $savedTask,
                ['task_key', 'project_key', 'company_key_hash', 'parent_task_key', 'task_code', 'task_subject', 'expected_start_date', 'expected_end_date'],
                'Instantiated Project task'
            );
        }
        $savedDependencies = $db->GetAll(
            'SELECT task_key, depends_on_task_key FROM project_company_project_task_dependency
             WHERE company_key_hash = ? AND task_key IN (
                SELECT task_key FROM project_company_project_task WHERE company_key_hash = ? AND project_key = ?
             ) ORDER BY task_key',
            [$companyKeyHash, $companyKeyHash, $projectKey]
        );
        $actualDependencies = [];
        foreach (is_array($savedDependencies) ? $savedDependencies : [] as $dependency) {
            $actualDependencies[(string) $dependency['task_key']] = (string) $dependency['depends_on_task_key'];
        }
        ksort($expectedDependencies);
        ksort($actualDependencies);
        if ($actualDependencies !== $expectedDependencies) {
            throw new RuntimeException('Instantiated Project dependency read-back failed.');
        }
        $savedProject['tasks'] = $savedTasks;
        $savedProject['dependencies'] = $savedDependencies;
        return $savedProject;
    });
}
