<?php
declare(strict_types=1);

function yovel_admin_projects_required_text(array $input, string $key, string $label, int $maxLength): string
{
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '' || strlen($value) > $maxLength) {
        throw new InvalidArgumentException($label . ' is required and limited to ' . $maxLength . ' characters.');
    }
    return $value;
}

function yovel_admin_projects_optional_uuid(array $input, string $key, string $label): ?string
{
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '') {
        return null;
    }
    if (!yovel_admin_is_uuid($value)) {
        throw new InvalidArgumentException($label . ' key is invalid.');
    }
    return $value;
}

function yovel_admin_projects_date(array $input, string $key, string $label, bool $required = false): ?string
{
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '' && !$required) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($label . ' date is invalid.');
    }
    return $value;
}

function yovel_admin_projects_percentage(mixed $value, string $label = 'Project percentage'): string
{
    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
        throw new InvalidArgumentException($label . ' must be between 0 and 100.');
    }
    return number_format((float) $value, 4, '.', '');
}

function yovel_admin_projects_validate_customer(array $company, ?string $customerKey): ?array
{
    if ($customerKey === null) {
        return null;
    }
    if (!function_exists('yovel_admin_sales_customer_snapshot')) {
        throw new RuntimeException('UNAVAILABLE_DEPENDENCY: Sales customer validation is unavailable.');
    }
    $snapshot = yovel_admin_sales_customer_snapshot($company, $customerKey);
    if (!is_array($snapshot)
        || (string) ($snapshot['customer_key'] ?? '') !== $customerKey
        || (($snapshot['company_key_hash'] ?? $company['company_key_hash']) !== $company['company_key_hash'])) {
        throw new InvalidArgumentException('Customer does not belong to this company.');
    }
    return $snapshot;
}

function yovel_admin_projects_validate_employee(array $company, ?string $employeeKey): ?array
{
    if ($employeeKey === null) {
        return null;
    }
    if (!function_exists('yovel_admin_hr_workforce_read_contract')) {
        throw new RuntimeException('UNAVAILABLE_DEPENDENCY: HR workforce validation is unavailable.');
    }
    $contract = yovel_admin_hr_workforce_read_contract($company, ['status' => 'ALL']);
    if (($contract['contract'] ?? '') !== 'hr.workforce.v1'
        || (string) ($contract['company_key_hash'] ?? '') !== (string) ($company['company_key_hash'] ?? '')) {
        throw new RuntimeException('UNAVAILABLE_DEPENDENCY: HR workforce contract is invalid.');
    }
    foreach (($contract['employees'] ?? []) as $employee) {
        if (is_array($employee) && (string) ($employee['employee_key'] ?? '') === $employeeKey) {
            return $employee;
        }
    }
    throw new InvalidArgumentException('Project lead employee does not belong to this company.');
}

function yovel_admin_projects_lock_company(
    ADOConnection $db,
    string $companyKey,
    string $companyKeyHash
): void {
    $row = $db->GetRow(
        "SELECT company_key, company_key_hash FROM project_company
         WHERE company_key = ? AND company_key_hash = ? AND company_status = 'ACTIVE'
         FOR UPDATE",
        [$companyKey, $companyKeyHash]
    );
    yovel_admin_projects_assert_readback(
        ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash],
        is_array($row) ? $row : [],
        ['company_key', 'company_key_hash'],
        'Projects company lock'
    );
}

function yovel_admin_projects_project_types(array $company, array $admin): array
{
    [, $companyKeyHash] = yovel_admin_projects_scope($company, $admin);
    yovel_admin_projects_schema();
    $rows = bx_db()->GetAll(
        "SELECT * FROM project_company_project_type
         WHERE company_key_hash = ? AND project_type_status <> 'DELETED'
         ORDER BY project_type_name, project_type_key LIMIT 200",
        [$companyKeyHash]
    );
    return is_array($rows) ? $rows : [];
}

function yovel_admin_project_type_upsert(array $company, array $admin, array $input): array
{
    yovel_admin_projects_assert_csrf($input);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    $requestedKey = yovel_admin_projects_optional_uuid($input, 'project_type_key', 'Project Type');
    $code = strtoupper(yovel_admin_projects_required_text($input, 'project_type_code', 'Project Type code', 80));
    if (preg_match('/^[A-Z0-9][A-Z0-9_-]{0,79}$/', $code) !== 1) {
        throw new InvalidArgumentException('Project Type code is invalid.');
    }
    $name = yovel_admin_projects_required_text($input, 'project_type_name', 'Project Type name', 160);
    $status = strtoupper(trim((string) ($input['project_type_status'] ?? 'ACTIVE')));
    if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
        throw new InvalidArgumentException('Project Type status is invalid.');
    }

    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $requestedKey, $code, $name, $status
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        $byKey = $requestedKey === null ? false : $db->GetRow(
            'SELECT * FROM project_company_project_type WHERE company_key_hash = ? AND project_type_key = ? FOR UPDATE',
            [$companyKeyHash, $requestedKey]
        );
        if ($requestedKey !== null && (!is_array($byKey) || $byKey === [])) {
            throw new InvalidArgumentException('Project Type key does not belong to this company.');
        }
        $byCode = $db->GetRow(
            'SELECT * FROM project_company_project_type WHERE company_key_hash = ? AND project_type_code = ? FOR UPDATE',
            [$companyKeyHash, $code]
        );
        if ($requestedKey !== null && is_array($byCode) && $byCode !== []
            && (string) $byCode['project_type_key'] !== $requestedKey) {
            throw new InvalidArgumentException('Project Type code belongs to another record.');
        }
        $existing = is_array($byKey) && $byKey !== [] ? $byKey : (is_array($byCode) ? $byCode : []);
        $recordKey = $existing !== [] ? (string) $existing['project_type_key'] : bx_uuid();
        yovel_admin_projects_db_execute(
            $db,
            "INSERT INTO project_company_project_type (
                project_type_key, company_key, company_key_hash, project_type_code,
                project_type_name, project_type_status, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                project_type_name = VALUES(project_type_name),
                project_type_status = VALUES(project_type_status),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$recordKey, $companyKey, $companyKeyHash, $code, $name, $status, $adminKey, $adminKey],
            'Project Type save'
        );
        yovel_admin_projects_audit(
            $existing === [] ? 'CREATE' : 'UPDATE',
            'project_company_project_type',
            $recordKey,
            $companyKeyHash,
            $adminKey,
            ['project_type_code' => $code, 'project_type_name' => $name, 'project_type_status' => $status]
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_project_type WHERE company_key_hash = ? AND project_type_key = ?',
            [$companyKeyHash, $recordKey]
        );
        yovel_admin_projects_assert_readback(
            [
                'project_type_key' => $recordKey, 'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash, 'project_type_code' => $code,
                'project_type_name' => $name, 'project_type_status' => $status,
                'updated_by_admin_key' => $adminKey,
            ],
            is_array($saved) ? $saved : [],
            [
                'project_type_key', 'company_key', 'company_key_hash', 'project_type_code',
                'project_type_name', 'project_type_status', 'updated_by_admin_key',
            ],
            'Project Type'
        );
        return $saved;
    });
}

function yovel_admin_projects_normalize_project_input(array $company, array $input): array
{
    $requestedKey = yovel_admin_projects_optional_uuid($input, 'project_key', 'Project');
    $code = strtoupper(yovel_admin_projects_required_text($input, 'project_code', 'Project code', 80));
    if (preg_match('/^[A-Z0-9][A-Z0-9_-]{0,79}$/', $code) !== 1) {
        throw new InvalidArgumentException('Project code is invalid.');
    }
    $name = yovel_admin_projects_required_text($input, 'project_name', 'Project name', 190);
    $typeKey = yovel_admin_projects_optional_uuid($input, 'project_type_key', 'Project Type');
    $customerKey = yovel_admin_projects_optional_uuid($input, 'customer_key', 'Customer');
    $employeeKey = yovel_admin_projects_optional_uuid($input, 'project_lead_employee_key', 'Project lead employee');
    yovel_admin_projects_validate_customer($company, $customerKey);
    yovel_admin_projects_validate_employee($company, $employeeKey);
    $status = strtoupper(trim((string) ($input['project_status'] ?? 'OPEN')));
    if (!in_array($status, ['OPEN', 'ON_HOLD', 'COMPLETED', 'CANCELLED'], true)) {
        throw new InvalidArgumentException('Project status is invalid.');
    }
    $completionMethod = strtoupper(trim((string) ($input['completion_method'] ?? 'MANUAL')));
    if (!in_array($completionMethod, ['MANUAL', 'TASK_COMPLETION', 'TASK_PROGRESS', 'TASK_WEIGHT'], true)) {
        throw new InvalidArgumentException('Project completion method is invalid.');
    }
    $percentage = yovel_admin_projects_percentage($input['percent_complete'] ?? '0');
    if ($status === 'COMPLETED') {
        $percentage = '100.0000';
    }
    $startDate = yovel_admin_projects_date($input, 'expected_start_date', 'Expected start');
    $endDate = yovel_admin_projects_date($input, 'expected_end_date', 'Expected end');
    if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
        throw new InvalidArgumentException('Expected end date must be on or after the expected start date.');
    }
    return [
        'project_key' => $requestedKey,
        'project_code' => $code,
        'project_name' => $name,
        'project_type_key' => $typeKey,
        'customer_key' => $customerKey,
        'project_lead_employee_key' => $employeeKey,
        'project_status' => $status,
        'completion_method' => $completionMethod,
        'percent_complete' => $percentage,
        'expected_start_date' => $startDate,
        'expected_end_date' => $endDate,
    ];
}

function yovel_admin_projects_verify_project_type(
    ADOConnection $db,
    string $companyKeyHash,
    ?string $projectTypeKey
): void {
    if ($projectTypeKey === null) {
        return;
    }
    $exists = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_project_type
         WHERE company_key_hash = ? AND project_type_key = ? AND project_type_status = 'ACTIVE'",
        [$companyKeyHash, $projectTypeKey]
    );
    if ($exists !== 1) {
        throw new InvalidArgumentException('Project Type does not belong to this company or is inactive.');
    }
}

function yovel_admin_projects_write_project(
    ADOConnection $db,
    string $companyKey,
    string $companyKeyHash,
    string $adminKey,
    array $values
): array {
    $requestedKey = $values['project_key'];
    $byKey = $requestedKey === null ? false : $db->GetRow(
        'SELECT * FROM project_company_project_record WHERE company_key_hash = ? AND project_key = ? FOR UPDATE',
        [$companyKeyHash, $requestedKey]
    );
    if ($requestedKey !== null && (!is_array($byKey) || $byKey === [])) {
        throw new InvalidArgumentException('Project key does not belong to this company.');
    }
    $byCode = $db->GetRow(
        'SELECT * FROM project_company_project_record WHERE company_key_hash = ? AND project_code = ? FOR UPDATE',
        [$companyKeyHash, $values['project_code']]
    );
    if ($requestedKey !== null && is_array($byCode) && $byCode !== []
        && (string) $byCode['project_key'] !== $requestedKey) {
        throw new InvalidArgumentException('Project code belongs to another record.');
    }
    $existing = is_array($byKey) && $byKey !== [] ? $byKey : (is_array($byCode) ? $byCode : []);
    $projectKey = $existing !== [] ? (string) $existing['project_key'] : bx_uuid();
    $formKey = $values['form_schema_key'] ?? null;
    $formVersion = $values['form_schema_version'] ?? null;
    yovel_admin_projects_db_execute(
        $db,
        "INSERT INTO project_company_project_record (
            project_key, company_key, company_key_hash, project_code, project_name,
            project_type_key, customer_key, project_lead_employee_key, project_status,
            completion_method, percent_complete, expected_start_date, expected_end_date,
            form_schema_key, form_schema_version, created_by_admin_key, updated_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            project_name = VALUES(project_name),
            project_type_key = VALUES(project_type_key),
            customer_key = VALUES(customer_key),
            project_lead_employee_key = VALUES(project_lead_employee_key),
            project_status = VALUES(project_status),
            completion_method = VALUES(completion_method),
            percent_complete = VALUES(percent_complete),
            expected_start_date = VALUES(expected_start_date),
            expected_end_date = VALUES(expected_end_date),
            updated_by_admin_key = VALUES(updated_by_admin_key)",
        [
            $projectKey, $companyKey, $companyKeyHash, $values['project_code'], $values['project_name'],
            $values['project_type_key'], $values['customer_key'], $values['project_lead_employee_key'],
            $values['project_status'], $values['completion_method'], $values['percent_complete'],
            $values['expected_start_date'], $values['expected_end_date'], $formKey, $formVersion,
            $adminKey, $adminKey,
        ],
        'Project save'
    );
    yovel_admin_projects_audit(
        $existing === [] ? 'CREATE' : 'UPDATE',
        'project_company_project_record',
        $projectKey,
        $companyKeyHash,
        $adminKey,
        ['project_code' => $values['project_code'], 'project_status' => $values['project_status']]
    );
    $saved = $db->GetRow(
        'SELECT * FROM project_company_project_record WHERE company_key_hash = ? AND project_key = ?',
        [$companyKeyHash, $projectKey]
    );
    $expected = ['project_key' => $projectKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash] + $values;
    $expected['updated_by_admin_key'] = $adminKey;
    yovel_admin_projects_assert_readback(
        $expected,
        is_array($saved) ? $saved : [],
        [
            'project_key', 'company_key', 'company_key_hash', 'project_code', 'project_name',
            'project_type_key', 'customer_key', 'project_lead_employee_key', 'project_status',
            'completion_method', 'percent_complete', 'expected_start_date', 'expected_end_date',
            'updated_by_admin_key',
        ],
        'Project'
    );
    return $saved;
}

function yovel_admin_project_upsert(array $company, array $admin, array $input): array
{
    yovel_admin_projects_assert_csrf($input);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    $values = yovel_admin_projects_normalize_project_input($company, $input);
    $form = yovel_admin_projects_active_schema($company, 'PROJECT');
    $values['form_schema_key'] = !empty($form['persisted']) ? (string) $form['form_schema_key'] : null;
    $values['form_schema_version'] = !empty($form['persisted']) ? (int) $form['version'] : null;
    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $values
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        yovel_admin_projects_verify_project_type($db, $companyKeyHash, $values['project_type_key']);
        return yovel_admin_projects_write_project($db, $companyKey, $companyKeyHash, $adminKey, $values);
    });
}

function yovel_admin_projects_records(array $company, array $admin): array
{
    [, $companyKeyHash] = yovel_admin_projects_scope($company, $admin);
    yovel_admin_projects_schema();
    $rows = bx_db()->GetAll(
        "SELECT project.*, project_type.project_type_name
         FROM project_company_project_record project
         LEFT JOIN project_company_project_type project_type
           ON project_type.company_key_hash = project.company_key_hash
          AND project_type.project_type_key = project.project_type_key
         WHERE project.company_key_hash = ? AND project.project_status <> 'DELETED'
         ORDER BY project.updated_at DESC, project.project_key LIMIT 300",
        [$companyKeyHash]
    );
    return is_array($rows) ? $rows : [];
}

function yovel_admin_project_transition(
    array $company,
    array $admin,
    string $projectKey,
    string $action,
    string $csrf = ''
): array {
    yovel_admin_projects_assert_csrf(['csrf' => $csrf]);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    if (!yovel_admin_is_uuid($projectKey)) {
        throw new InvalidArgumentException('Project key is invalid.');
    }
    $action = strtoupper(trim($action));
    $statusMap = ['HOLD' => 'ON_HOLD', 'RESUME' => 'OPEN', 'COMPLETE' => 'COMPLETED', 'CANCEL' => 'CANCELLED'];
    if (!isset($statusMap[$action])) {
        throw new InvalidArgumentException('Project transition is invalid.');
    }
    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $projectKey, $action, $statusMap
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        $existing = $db->GetRow(
            'SELECT * FROM project_company_project_record WHERE company_key_hash = ? AND project_key = ? FOR UPDATE',
            [$companyKeyHash, $projectKey]
        );
        if (!is_array($existing) || $existing === []) {
            throw new InvalidArgumentException('Project does not belong to this company.');
        }
        $status = $statusMap[$action];
        $percentage = $status === 'COMPLETED' ? '100.0000' : (string) $existing['percent_complete'];
        yovel_admin_projects_db_execute(
            $db,
            'UPDATE project_company_project_record
             SET project_status = ?, percent_complete = ?, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND project_key = ?',
            [$status, $percentage, $adminKey, $companyKeyHash, $projectKey],
            'Project transition'
        );
        yovel_admin_projects_audit(
            $action,
            'project_company_project_record',
            $projectKey,
            $companyKeyHash,
            $adminKey,
            ['project_status' => $status, 'percent_complete' => $percentage]
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_project_record WHERE company_key_hash = ? AND project_key = ?',
            [$companyKeyHash, $projectKey]
        );
        yovel_admin_projects_assert_readback(
            ['project_key' => $projectKey, 'project_status' => $status, 'percent_complete' => $percentage, 'updated_by_admin_key' => $adminKey],
            is_array($saved) ? $saved : [],
            ['project_key', 'project_status', 'percent_complete', 'updated_by_admin_key'],
            'Project transition'
        );
        return $saved;
    });
}

function yovel_admin_projects_recalculate_locked(
    ADOConnection $db,
    array $project,
    string $companyKeyHash,
    string $adminKey
): array {
    $projectKey = (string) $project['project_key'];
    $tasks = $db->GetAll(
        "SELECT task_status, progress, task_weight FROM project_company_project_task
         WHERE company_key_hash = ? AND project_key = ?
           AND task_status NOT IN ('DELETED','TEMPLATE') AND is_group = 0
         ORDER BY task_key FOR UPDATE",
        [$companyKeyHash, $projectKey]
    );
    $tasks = is_array($tasks) ? $tasks : [];
    $method = (string) $project['completion_method'];
    $percentage = (float) $project['percent_complete'];
    if ($method !== 'MANUAL' && $tasks !== []) {
        if ($method === 'TASK_COMPLETION') {
            $complete = count(array_filter($tasks, static fn (array $task): bool => $task['task_status'] === 'COMPLETED'));
            $percentage = ($complete / count($tasks)) * 100;
        } elseif ($method === 'TASK_PROGRESS') {
            $percentage = array_sum(array_map(static fn (array $task): float => (float) $task['progress'], $tasks)) / count($tasks);
        } elseif ($method === 'TASK_WEIGHT') {
            $weight = array_sum(array_map(static fn (array $task): float => (float) $task['task_weight'], $tasks));
            $weightedProgress = array_sum(array_map(
                static fn (array $task): float => (float) $task['progress'] * (float) $task['task_weight'],
                $tasks
            ));
            $percentage = $weight > 0 ? $weightedProgress / $weight : 0;
        }
    } elseif ($method !== 'MANUAL') {
        $percentage = 0;
    }
    $percentageText = number_format(max(0, min(100, $percentage)), 4, '.', '');
    $status = (string) $project['project_status'];
    if (!in_array($status, ['ON_HOLD', 'CANCELLED'], true)) {
        $status = (float) $percentageText >= 100 ? 'COMPLETED' : 'OPEN';
    }
    yovel_admin_projects_db_execute(
        $db,
        'UPDATE project_company_project_record
         SET percent_complete = ?, project_status = ?, updated_by_admin_key = ?
         WHERE company_key_hash = ? AND project_key = ?',
        [$percentageText, $status, $adminKey, $companyKeyHash, $projectKey],
        'Project completion recalculation'
    );
    yovel_admin_projects_audit(
        'RECALCULATE', 'project_company_project_record', $projectKey, $companyKeyHash, $adminKey,
        ['completion_method' => $method, 'percent_complete' => $percentageText, 'project_status' => $status]
    );
    $saved = $db->GetRow(
        'SELECT * FROM project_company_project_record WHERE company_key_hash = ? AND project_key = ?',
        [$companyKeyHash, $projectKey]
    );
    yovel_admin_projects_assert_readback(
        ['project_key' => $projectKey, 'percent_complete' => $percentageText, 'project_status' => $status, 'updated_by_admin_key' => $adminKey],
        is_array($saved) ? $saved : [],
        ['project_key', 'percent_complete', 'project_status', 'updated_by_admin_key'],
        'Project completion recalculation'
    );
    return $saved;
}

function yovel_admin_project_recalculate(array $company, array $admin, string $projectKey): array
{
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    if (!yovel_admin_is_uuid($projectKey)) {
        throw new InvalidArgumentException('Project key is invalid.');
    }
    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $projectKey
    ): array {
        yovel_admin_projects_lock_company($db, $companyKey, $companyKeyHash);
        $project = $db->GetRow(
            'SELECT * FROM project_company_project_record WHERE company_key_hash = ? AND project_key = ? FOR UPDATE',
            [$companyKeyHash, $projectKey]
        );
        if (!is_array($project) || $project === []) {
            throw new InvalidArgumentException('Project does not belong to this company.');
        }
        return yovel_admin_projects_recalculate_locked($db, $project, $companyKeyHash, $adminKey);
    });
}
