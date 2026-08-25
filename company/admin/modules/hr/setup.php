<?php
declare(strict_types=1);

function yovel_admin_hr_scope(array $company, array $admin): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $adminKey = trim((string) ($admin['admin_key'] ?? ''));
    if ($companyKey === '' || strlen($companyKey) > 36 || preg_match('/^[A-Za-z0-9_-]+$/', $companyKey) !== 1
        || preg_match('/^[0-9a-f]{64}$/', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('HR company scope is invalid.');
    }
    if (!yovel_admin_is_uuid($adminKey)) {
        throw new InvalidArgumentException('HR administrator scope is invalid.');
    }

    $authorized = bx_db()->GetRow(
        "SELECT admin_record.admin_key
         FROM project_company_admin admin_record
         INNER JOIN project_company company_record
           ON company_record.company_key = admin_record.company_key
          AND company_record.company_key_hash = admin_record.company_key_hash
          AND company_record.company_status = 'ACTIVE'
         WHERE admin_record.admin_key = ?
           AND admin_record.company_key = ?
           AND admin_record.company_key_hash = ?
           AND admin_record.admin_status = 'ACTIVE'
         LIMIT 1",
        [$adminKey, $companyKey, $companyKeyHash]
    );
    if (!is_array($authorized) || $authorized === []) {
        throw new RuntimeException('An authorized active company administrator is required for HR Setup.');
    }

    return [$companyKey, $companyKeyHash, $adminKey];
}

function yovel_admin_hr_setup_master_types(): array
{
    return [
        'EMPLOYMENT_TYPE' => 'Employment Type',
        'EMPLOYEE_GRADE' => 'Employee Grade',
        'GRIEVANCE_TYPE' => 'Grievance Type',
        'INTEREST' => 'Interest',
        'EMPLOYEE_HEALTH_INSURANCE' => 'Employee Health Insurance',
    ];
}

function yovel_admin_hr_setup_lifecycle_types(): array
{
    return [
        'EMPLOYEE_GRIEVANCE' => 'Employee Grievance',
        'DEPARTMENT_APPROVER' => 'Department Approver',
        'APPRAISEE' => 'Appraisee',
    ];
}

function yovel_admin_hr_setup_form_targets(): array
{
    return [
        'hr-settings' => ['label' => 'HR Settings', 'protected_fields' => ['hr_setting_key', 'company_key_hash', 'setting_status'], 'versioned' => true],
        'employment-type' => ['label' => 'Employment Type', 'protected_fields' => ['setup_master_key', 'record_type', 'record_code', 'record_status'], 'versioned' => true],
        'employee-grade' => ['label' => 'Employee Grade', 'protected_fields' => ['setup_master_key', 'record_type', 'record_code', 'record_status'], 'versioned' => true],
        'grievance-type' => ['label' => 'Grievance Type', 'protected_fields' => ['setup_master_key', 'record_type', 'record_code', 'record_status'], 'versioned' => true],
        'interest' => ['label' => 'Interest', 'protected_fields' => ['setup_master_key', 'record_type', 'record_code', 'record_status'], 'versioned' => true],
        'employee-transfer' => ['label' => 'Employee Transfer', 'protected_fields' => ['lifecycle_key', 'employee_key', 'effective_date', 'transfer_status'], 'versioned' => true],
        'employee-promotion' => ['label' => 'Employee Promotion', 'protected_fields' => ['lifecycle_key', 'employee_key', 'effective_date', 'promotion_status'], 'versioned' => true],
        'employee-grievance' => ['label' => 'Employee Grievance', 'protected_fields' => ['lifecycle_key', 'employee_key', 'effective_date', 'record_status'], 'versioned' => true],
        'employee-health-insurance' => ['label' => 'Employee Health Insurance', 'protected_fields' => ['setup_master_key', 'record_type', 'record_code', 'record_status'], 'versioned' => true],
        'department-approver' => ['label' => 'Department Approver', 'protected_fields' => ['lifecycle_key', 'department_key', 'approver_user_key', 'record_status'], 'versioned' => true],
        'appraisee' => ['label' => 'Appraisee', 'protected_fields' => ['lifecycle_key', 'employee_key', 'appraisal_template_key', 'record_status'], 'versioned' => true],
    ];
}

function yovel_admin_hr_json(array $value): string
{
    ksort($value);
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function yovel_admin_hr_settings_payload(array $input): array
{
    $settings = is_array($input['settings'] ?? null) ? $input['settings'] : [];
    $allowed = [
        'standard_working_hours', 'send_birthday_reminders', 'send_work_anniversary_reminders',
        'expense_approver_mandatory', 'leave_approver_mandatory', 'shift_assignment_threshold',
        'allow_geolocation_tracking', 'employee_exit_questionnaire', 'job_offer_email_template',
        'daily_work_summary_email_template', 'sender_email', 'sender_name',
    ];
    $normalized = [];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $settings)) {
            continue;
        }
        $value = $settings[$key];
        $normalized[$key] = is_bool($value) ? $value : trim((string) $value);
    }
    return $normalized;
}

function yovel_admin_hr_setup_date(array $input): string
{
    $date = yovel_admin_optional_date((string) ($input['effective_date'] ?? date('Y-m-d')), 'Effective date');
    if ($date === '') {
        throw new InvalidArgumentException('Effective date is required.');
    }
    return $date;
}

function yovel_admin_hr_setup_key(
    ADOConnection $db,
    string $companyKeyHash,
    string $table,
    string $column,
    mixed $value,
    string $label,
    bool $required = false
): string {
    $key = trim((string) $value);
    if ($key === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return '';
    }
    if (!yovel_admin_is_uuid($key) || !yovel_admin_existing_key($db, $table, $column, $companyKeyHash, $key)) {
        throw new InvalidArgumentException('The selected ' . strtolower($label) . ' does not belong to this company.');
    }
    return $key;
}

function yovel_admin_hr_in_transaction(ADOConnection $db, callable $callback): mixed
{
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('HR Setup transaction could not start.');
    }
    try {
        $result = $callback();
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('HR Setup transaction could not commit.');
        }
        return $result;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_persist_hr_settings(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $mode = yovel_admin_status((string) ($input['employee_naming_mode'] ?? 'SERIES'), ['SERIES', 'MANUAL'], 'SERIES');
    $prefix = strtoupper(trim((string) ($input['employee_number_prefix'] ?? 'HR-EMP-')));
    $retirementAge = filter_var($input['default_retirement_age'] ?? 60, FILTER_VALIDATE_INT);
    $selfService = in_array($input['allow_employee_self_service'] ?? null, [1, '1', true, 'on', 'yes'], true) ? 1 : 0;
    $settingsJson = yovel_admin_hr_json(yovel_admin_hr_settings_payload($input));
    $status = yovel_admin_status((string) ($input['record_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
    if ($prefix === '' || strlen($prefix) > 40 || preg_match('/^[A-Z0-9_.-]+$/', $prefix) !== 1) {
        throw new InvalidArgumentException('Employee number prefix must use uppercase letters, numbers, periods, underscores, or hyphens.');
    }
    if ($retirementAge === false || $retirementAge < 18 || $retirementAge > 100) {
        throw new InvalidArgumentException('Default retirement age must be from 18 to 100.');
    }

    return yovel_admin_hr_in_transaction($db, static function () use (
        $db, $company, $companyKey, $companyKeyHash, $adminKey, $mode, $prefix, $retirementAge, $selfService, $settingsJson, $status, $failureInjector
    ): array {
        $existing = $db->GetRow('SELECT * FROM project_company_hr_setting WHERE company_key_hash = ? FOR UPDATE', [$companyKeyHash]);
        $key = is_array($existing) && $existing !== [] ? (string) $existing['hr_setting_key'] : bx_uuid();
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_hr_setting (
                hr_setting_key, company_key, company_key_hash, employee_naming_mode, employee_number_prefix,
                default_retirement_age, allow_employee_self_service, settings_json, setting_status, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                employee_naming_mode = VALUES(employee_naming_mode), employee_number_prefix = VALUES(employee_number_prefix),
                default_retirement_age = VALUES(default_retirement_age), allow_employee_self_service = VALUES(allow_employee_self_service),
                settings_json = VALUES(settings_json), setting_status = VALUES(setting_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$key, $companyKey, $companyKeyHash, $mode, $prefix, $retirementAge, $selfService, $settingsJson, $status, $adminKey, $adminKey],
            'HR Settings save'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_hr_setting WHERE company_key_hash = ? AND hr_setting_key = ? LIMIT 1', [$companyKeyHash, $key]);
        if (!is_array($saved)
            || (string) ($saved['employee_naming_mode'] ?? '') !== $mode
            || (string) ($saved['employee_number_prefix'] ?? '') !== $prefix
            || (int) ($saved['default_retirement_age'] ?? 0) !== $retirementAge
            || (int) ($saved['allow_employee_self_service'] ?? -1) !== $selfService
            || (string) ($saved['settings_json'] ?? '') !== $settingsJson
            || (string) ($saved['setting_status'] ?? '') !== $status) {
            throw new RuntimeException('HR Settings exact read-back verification failed.');
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_setting', $key, [
            'company_key' => $companyKey,
            'employee_naming_mode' => $mode,
            'setting_status' => $status,
            'admin_key' => $adminKey,
        ], $existing ? 'Company administrator updated HR Settings.' : 'Company administrator created HR Settings.');
        if ($failureInjector !== null) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_persist_hr_setup_master(
    ADOConnection $db,
    array $company,
    array $admin,
    string $recordType,
    array $input,
    ?callable $failureInjector = null
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    if (!isset(yovel_admin_hr_setup_master_types()[$recordType])) {
        throw new InvalidArgumentException('Unknown HR Setup master type.');
    }
    $key = trim((string) ($input['setup_master_key'] ?? ''));
    $code = yovel_admin_code((string) ($input['record_code'] ?? ''));
    $name = trim((string) ($input['record_name'] ?? ''));
    $description = trim((string) ($input['record_description'] ?? ''));
    $status = yovel_admin_status((string) ($input['record_status'] ?? 'ACTIVE'), ['DRAFT', 'ACTIVE', 'INACTIVE', 'DELETED'], 'ACTIVE');
    if ($key !== '' && !yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('HR Setup master key is invalid.');
    }
    if ($code === '' || preg_match('/^[A-Z0-9_.-]{2,80}$/', $code) !== 1 || $name === '' || strlen($name) > 160) {
        throw new InvalidArgumentException('HR Setup code and name are required and must use the supported lengths.');
    }
    $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
    if ($recordType === 'EMPLOYEE_GRADE') {
        $basePay = trim((string) ($metadata['default_base_pay'] ?? ''));
        $currency = strtoupper(trim((string) ($metadata['currency'] ?? '')));
        if ($basePay !== '' && (!is_numeric($basePay) || (float) $basePay < 0)) {
            throw new InvalidArgumentException('Employee Grade default base pay must be a non-negative number.');
        }
        if ($currency !== '' && preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Employee Grade currency must use a three-letter code.');
        }
        $metadata = [
            'default_salary_structure' => trim((string) ($metadata['default_salary_structure'] ?? '')),
            'currency' => $currency,
            'default_base_pay' => $basePay,
        ];
    }
    $metadataJson = yovel_admin_hr_json($metadata);

    return yovel_admin_hr_in_transaction($db, static function () use (
        $db, $companyKey, $companyKeyHash, $adminKey, $recordType, $key, $code, $name, $description, $status, $metadataJson, $failureInjector
    ): array {
        $existing = null;
        if ($key !== '') {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_hr_setup_master WHERE company_key_hash = ? AND setup_master_key = ? AND record_type = ? FOR UPDATE',
                [$companyKeyHash, $key, $recordType]
            );
            if (!is_array($existing) || $existing === []) {
                throw new InvalidArgumentException('HR Setup master was not found for this company.');
            }
        } else {
            $duplicate = $db->GetRow(
                "SELECT setup_master_key FROM project_company_hr_setup_master
                 WHERE company_key_hash = ? AND record_type = ? AND record_code = ? AND record_status <> 'DELETED' FOR UPDATE",
                [$companyKeyHash, $recordType, $code]
            );
            if (is_array($duplicate) && $duplicate !== []) {
                throw new InvalidArgumentException('This active HR Setup code already exists.');
            }
            $key = bx_uuid();
        }
        $duplicateCount = (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_hr_setup_master
             WHERE company_key_hash = ? AND record_type = ? AND record_code = ? AND setup_master_key <> ? AND record_status <> 'DELETED'",
            [$companyKeyHash, $recordType, $code, $key]
        );
        if ($duplicateCount !== 0) {
            throw new InvalidArgumentException('This active HR Setup code already exists.');
        }
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_hr_setup_master (
                setup_master_key, company_key, company_key_hash, record_type, record_code, record_name,
                record_description, record_status, metadata_json, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE record_code = VALUES(record_code), record_name = VALUES(record_name),
                record_description = VALUES(record_description), record_status = VALUES(record_status),
                metadata_json = VALUES(metadata_json), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$key, $companyKey, $companyKeyHash, $recordType, $code, $name, $description, $status, $metadataJson, $adminKey, $adminKey],
            'HR Setup master save'
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_hr_setup_master WHERE company_key_hash = ? AND setup_master_key = ? AND record_type = ? LIMIT 1',
            [$companyKeyHash, $key, $recordType]
        );
        foreach (['record_type' => $recordType, 'record_code' => $code, 'record_name' => $name, 'record_status' => $status, 'metadata_json' => $metadataJson] as $field => $expected) {
            if (!is_array($saved) || (string) ($saved[$field] ?? '') !== $expected) {
                throw new RuntimeException('HR Setup master exact read-back verification failed for ' . $field . '.');
            }
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_setup_master', $key, [
            'company_key' => $companyKey,
            'record_type' => $recordType,
            'record_code' => $code,
            'record_status' => $status,
            'admin_key' => $adminKey,
        ], $existing ? 'Company administrator updated an HR Setup master.' : 'Company administrator created an HR Setup master.');
        if ($failureInjector !== null) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_hr_lifecycle_payload(array $input): array
{
    $payload = [];
    foreach ([
        'subject', 'description', 'grievance_against_party', 'associated_document_type',
        'associated_document_key', 'investigation_cause', 'resolution_detail', 'resolution_date',
        'appraisal_template_key', 'approver_user_key', 'approver_role',
    ] as $field) {
        $payload[$field] = trim((string) ($input[$field] ?? ''));
    }
    return $payload;
}

function yovel_admin_hr_insert_lifecycle(
    ADOConnection $db,
    array $company,
    array $admin,
    string $recordType,
    array $normalized
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $key = trim((string) ($normalized['lifecycle_key'] ?? ''));
    $existing = null;
    if ($key !== '') {
        if (!yovel_admin_is_uuid($key)) {
            throw new InvalidArgumentException('HR lifecycle key is invalid.');
        }
        $existing = $db->GetRow(
            'SELECT * FROM project_company_hr_employee_lifecycle WHERE company_key_hash = ? AND lifecycle_key = ? AND record_type = ? FOR UPDATE',
            [$companyKeyHash, $key, $recordType]
        );
        if (!is_array($existing) || $existing === []) {
            throw new InvalidArgumentException('HR lifecycle record was not found for this company.');
        }
        $mutableStatuses = is_array($normalized['mutable_statuses'] ?? null) ? $normalized['mutable_statuses'] : ['DRAFT'];
        if (!in_array((string) ($existing['record_status'] ?? ''), $mutableStatuses, true)) {
            throw new InvalidArgumentException('Submitted HR lifecycle history is immutable; create a new record instead.');
        }
    } else {
        $key = bx_uuid();
    }
    $payloadJson = yovel_admin_hr_json(is_array($normalized['payload'] ?? null) ? $normalized['payload'] : []);
    $checksum = hash('sha256', yovel_admin_hr_json([
        'record_type' => $recordType,
        'employee_key' => (string) ($normalized['employee_key'] ?? ''),
        'related_employee_key' => (string) ($normalized['related_employee_key'] ?? ''),
        'department_key' => (string) ($normalized['department_key'] ?? ''),
        'branch_key' => (string) ($normalized['branch_key'] ?? ''),
        'project_key' => (string) ($normalized['project_key'] ?? ''),
        'job_position_key' => (string) ($normalized['job_position_key'] ?? ''),
        'team_key' => (string) ($normalized['team_key'] ?? ''),
        'setup_master_key' => (string) ($normalized['setup_master_key'] ?? ''),
        'effective_date' => (string) $normalized['effective_date'],
        'record_status' => (string) $normalized['record_status'],
        'reason' => (string) ($normalized['reason'] ?? ''),
        'payload_json' => $payloadJson,
    ]));
    $submitted = in_array((string) $normalized['record_status'], ['SUBMITTED', 'APPROVED', 'REJECTED'], true);
    $approved = (string) $normalized['record_status'] === 'APPROVED';

    yovel_admin_db_execute(
        $db,
        "INSERT INTO project_company_hr_employee_lifecycle (
            lifecycle_key, company_key, company_key_hash, record_type, employee_key, related_employee_key,
            department_key, branch_key, project_key, job_position_key, team_key, setup_master_key,
            effective_date, record_status, reason, payload_json, immutable_checksum,
            submitted_by_admin_key, submitted_at, approved_by_admin_key, approved_at,
            created_by_admin_key, updated_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE employee_key = VALUES(employee_key), related_employee_key = VALUES(related_employee_key),
            department_key = VALUES(department_key), branch_key = VALUES(branch_key), project_key = VALUES(project_key),
            job_position_key = VALUES(job_position_key), team_key = VALUES(team_key), setup_master_key = VALUES(setup_master_key),
            effective_date = VALUES(effective_date), record_status = VALUES(record_status), reason = VALUES(reason),
            payload_json = VALUES(payload_json), immutable_checksum = VALUES(immutable_checksum),
            submitted_by_admin_key = VALUES(submitted_by_admin_key), submitted_at = VALUES(submitted_at),
            approved_by_admin_key = VALUES(approved_by_admin_key), approved_at = VALUES(approved_at),
            updated_by_admin_key = VALUES(updated_by_admin_key)",
        [
            $key, $companyKey, $companyKeyHash, $recordType,
            ($normalized['employee_key'] ?? '') !== '' ? $normalized['employee_key'] : null,
            ($normalized['related_employee_key'] ?? '') !== '' ? $normalized['related_employee_key'] : null,
            ($normalized['department_key'] ?? '') !== '' ? $normalized['department_key'] : null,
            ($normalized['branch_key'] ?? '') !== '' ? $normalized['branch_key'] : null,
            ($normalized['project_key'] ?? '') !== '' ? $normalized['project_key'] : null,
            ($normalized['job_position_key'] ?? '') !== '' ? $normalized['job_position_key'] : null,
            ($normalized['team_key'] ?? '') !== '' ? $normalized['team_key'] : null,
            ($normalized['setup_master_key'] ?? '') !== '' ? $normalized['setup_master_key'] : null,
            $normalized['effective_date'], $normalized['record_status'], $normalized['reason'] ?? '', $payloadJson, $checksum,
            $submitted ? $adminKey : null, $submitted ? date('Y-m-d H:i:s') : null,
            $approved ? $adminKey : null, $approved ? date('Y-m-d H:i:s') : null,
            $adminKey, $adminKey,
        ],
        'HR employee lifecycle save'
    );
    $saved = $db->GetRow(
        'SELECT * FROM project_company_hr_employee_lifecycle WHERE company_key_hash = ? AND lifecycle_key = ? AND record_type = ? LIMIT 1',
        [$companyKeyHash, $key, $recordType]
    );
    if (!is_array($saved)
        || (string) ($saved['record_status'] ?? '') !== (string) $normalized['record_status']
        || (string) ($saved['effective_date'] ?? '') !== (string) $normalized['effective_date']
        || (string) ($saved['immutable_checksum'] ?? '') !== $checksum) {
        throw new RuntimeException('HR lifecycle exact read-back verification failed.');
    }
    return $saved;
}

function yovel_admin_hr_property_history(
    ADOConnection $db,
    array $company,
    array $admin,
    string $employeeKey,
    string $sourceLifecycleKey,
    string $propertyName,
    string $previousValue,
    string $newValue,
    string $effectiveDate,
    string $propertyLabel = ''
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $historyKey = bx_uuid();
    $checksum = hash('sha256', implode('|', [$companyKeyHash, $employeeKey, $sourceLifecycleKey, $propertyName, $previousValue, $newValue, $effectiveDate]));
    yovel_admin_db_execute(
        $db,
        "INSERT INTO project_company_hr_employee_property_history (
            property_history_key, company_key, company_key_hash, employee_key, source_lifecycle_key,
            property_label, property_name, previous_value, new_value, effective_date, immutable_checksum, created_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$historyKey, $companyKey, $companyKeyHash, $employeeKey, $sourceLifecycleKey, $propertyLabel !== '' ? $propertyLabel : ucwords(str_replace('_', ' ', $propertyName)), $propertyName, $previousValue, $newValue, $effectiveDate, $checksum, $adminKey],
        'HR immutable employee property history create'
    );
    $saved = $db->GetRow(
        'SELECT * FROM project_company_hr_employee_property_history WHERE company_key_hash = ? AND property_history_key = ? LIMIT 1',
        [$companyKeyHash, $historyKey]
    );
    if (!is_array($saved) || (string) ($saved['immutable_checksum'] ?? '') !== $checksum) {
        throw new RuntimeException('HR property history direct read-back verification failed.');
    }
    return $saved;
}

function yovel_admin_hr_assert_reporting_hierarchy(
    ADOConnection $db,
    string $companyKeyHash,
    string $employeeKey,
    string $managerKey
): void {
    if ($managerKey === '') {
        return;
    }
    if ($managerKey === $employeeKey) {
        throw new InvalidArgumentException('An employee cannot report to themselves.');
    }
    $visited = [];
    $current = $managerKey;
    while ($current !== '') {
        if ($current === $employeeKey || isset($visited[$current])) {
            throw new InvalidArgumentException('The selected manager would create an organization hierarchy cycle.');
        }
        $visited[$current] = true;
        if (count($visited) > 500) {
            throw new RuntimeException('Organization hierarchy traversal exceeded its safety limit.');
        }
        $current = (string) $db->GetOne(
            "SELECT COALESCE(assignment.reports_to_employee_key, employee.reports_to_employee_key, '')
             FROM project_company_hr_employee employee
             LEFT JOIN project_company_hr_employee_assignment assignment
               ON assignment.company_key_hash = employee.company_key_hash
              AND assignment.employee_key = employee.employee_key
              AND assignment.assignment_status = 'ACTIVE'
              AND assignment.is_primary = 1
             WHERE employee.company_key_hash = ? AND employee.employee_key = ?
             LIMIT 1",
            [$companyKeyHash, $current]
        );
    }
}

function yovel_admin_persist_employee_transfer(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $employeeKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_hr_employee', 'employee_key', $input['employee_key'] ?? '', 'Employee', true);
    $effectiveDate = yovel_admin_hr_setup_date($input);
    $status = yovel_admin_status((string) ($input['transfer_status'] ?? 'DRAFT'), ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'], 'DRAFT');
    $branchKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_branch', 'branch_key', $input['branch_key'] ?? '', 'Branch');
    $projectKey = yovel_admin_hr_resolve_assignment_project_key($db, $companyKeyHash, $branchKey, (string) ($input['project_key'] ?? ''));
    $departmentKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_department', 'department_key', $input['department_key'] ?? '', 'Department');
    $jobPositionKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_hr_job_position', 'job_position_key', $input['job_position_key'] ?? '', 'Job position');
    $teamKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_hr_team', 'team_key', $input['team_key'] ?? '', 'Team');
    $managerKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_hr_employee', 'employee_key', $input['reports_to_employee_key'] ?? '', 'Manager');
    $reason = trim((string) ($input['reason'] ?? ''));
    if ($reason === '' || strlen($reason) > 2000) {
        throw new InvalidArgumentException('Transfer reason is required and must not exceed 2000 characters.');
    }
    yovel_admin_hr_assert_reporting_hierarchy($db, $companyKeyHash, $employeeKey, $managerKey);

    return yovel_admin_hr_in_transaction($db, static function () use (
        $db, $company, $admin, $companyKey, $companyKeyHash, $adminKey, $input, $employeeKey, $effectiveDate,
        $status, $branchKey, $projectKey, $departmentKey, $jobPositionKey, $teamKey, $managerKey, $reason, $failureInjector
    ): array {
        $employee = $db->GetRow('SELECT * FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_key = ? FOR UPDATE', [$companyKeyHash, $employeeKey]);
        if (!is_array($employee) || $employee === []) {
            throw new InvalidArgumentException('Employee transfer subject was not found for this company.');
        }
        $current = $db->GetRow(
            "SELECT * FROM project_company_hr_employee_assignment
             WHERE company_key_hash = ? AND employee_key = ? AND assignment_status = 'ACTIVE' AND is_primary = 1
             ORDER BY x_id DESC LIMIT 1 FOR UPDATE",
            [$companyKeyHash, $employeeKey]
        );
        $saved = yovel_admin_hr_insert_lifecycle($db, $company, $admin, 'TRANSFER', [
            'lifecycle_key' => $input['lifecycle_key'] ?? '',
            'employee_key' => $employeeKey,
            'branch_key' => $branchKey,
            'project_key' => $projectKey,
            'department_key' => $departmentKey,
            'job_position_key' => $jobPositionKey,
            'team_key' => $teamKey,
            'related_employee_key' => $managerKey,
            'effective_date' => $effectiveDate,
            'record_status' => $status,
            'reason' => $reason,
            'payload' => ['reports_to_employee_key' => $managerKey],
        ]);
        if (in_array($status, ['SUBMITTED', 'APPROVED'], true)) {
            yovel_admin_upsert_hr_employee_primary_assignment($db, $company, $admin, $employeeKey, [
                'branch_key' => $branchKey,
                'project_key' => $projectKey,
                'department_key' => $departmentKey,
                'job_position_key' => $jobPositionKey,
                'team_key' => $teamKey,
                'reports_to_employee_key' => $managerKey,
                'effective_from' => $effectiveDate,
                'assignment_notes' => 'Employee Transfer ' . (string) $saved['lifecycle_key'] . ': ' . $reason,
            ]);
            $newAssignment = [
                'branch_key' => $branchKey,
                'project_key' => $projectKey,
                'department_key' => $departmentKey,
                'job_position_key' => $jobPositionKey,
                'team_key' => $teamKey,
                'reports_to_employee_key' => $managerKey,
            ];
            $propertyLabels = [
                'branch_key' => 'Branch', 'project_key' => 'Project', 'department_key' => 'Department',
                'job_position_key' => 'Designation', 'team_key' => 'Team', 'reports_to_employee_key' => 'Reports To',
            ];
            foreach ($newAssignment as $property => $newValue) {
                $previousValue = (string) ($current[$property] ?? $employee[$property] ?? '');
                if ($previousValue !== $newValue) {
                    yovel_admin_hr_property_history($db, $company, $admin, $employeeKey, (string) $saved['lifecycle_key'], $property, $previousValue, $newValue, $effectiveDate, $propertyLabels[$property] ?? 'Employee Property');
                }
            }
        }
        $saved['transfer_status'] = (string) $saved['record_status'];
        bx_audit('CREATE', 'project_company_hr_employee_transfer', (string) $saved['lifecycle_key'], [
            'company_key' => $companyKey,
            'employee_key' => $employeeKey,
            'transfer_status' => $status,
            'effective_date' => $effectiveDate,
            'admin_key' => $adminKey,
        ], 'Company administrator recorded an employee transfer.');
        if ($failureInjector !== null) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_persist_employee_promotion(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $employeeKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_hr_employee', 'employee_key', $input['employee_key'] ?? '', 'Employee', true);
    $effectiveDate = yovel_admin_hr_setup_date($input);
    $status = yovel_admin_status((string) ($input['promotion_status'] ?? 'DRAFT'), ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'], 'DRAFT');
    $jobPositionKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_hr_job_position', 'job_position_key', $input['job_position_key'] ?? '', 'Job position', true);
    $gradeKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_hr_setup_master', 'setup_master_key', $input['employee_grade_key'] ?? '', 'Employee grade', true);
    $gradeType = (string) $db->GetOne(
        "SELECT record_type FROM project_company_hr_setup_master
         WHERE company_key_hash = ? AND setup_master_key = ? AND record_status = 'ACTIVE' LIMIT 1",
        [$companyKeyHash, $gradeKey]
    );
    if ($gradeType !== 'EMPLOYEE_GRADE') {
        throw new InvalidArgumentException('The selected Employee Grade is not active.');
    }
    $reason = trim((string) ($input['reason'] ?? ''));
    $currentCtc = trim((string) ($input['current_ctc'] ?? ''));
    $revisedCtc = trim((string) ($input['revised_ctc'] ?? ''));
    if (($currentCtc !== '' && !is_numeric($currentCtc)) || ($revisedCtc !== '' && (!is_numeric($revisedCtc) || (float) $revisedCtc < 0))) {
        throw new InvalidArgumentException('Promotion CTC values must be valid non-negative numbers.');
    }
    if ($reason === '' || strlen($reason) > 2000) {
        throw new InvalidArgumentException('Promotion reason is required and must not exceed 2000 characters.');
    }

    return yovel_admin_hr_in_transaction($db, static function () use (
        $db, $company, $admin, $companyKey, $companyKeyHash, $adminKey, $input, $employeeKey,
        $effectiveDate, $status, $jobPositionKey, $gradeKey, $reason, $currentCtc, $revisedCtc, $failureInjector
    ): array {
        $employee = $db->GetRow('SELECT * FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_key = ? FOR UPDATE', [$companyKeyHash, $employeeKey]);
        if (!is_array($employee) || $employee === []) {
            throw new InvalidArgumentException('Employee promotion subject was not found for this company.');
        }
        $current = $db->GetRow(
            "SELECT * FROM project_company_hr_employee_assignment
             WHERE company_key_hash = ? AND employee_key = ? AND assignment_status = 'ACTIVE' AND is_primary = 1
             ORDER BY x_id DESC LIMIT 1 FOR UPDATE",
            [$companyKeyHash, $employeeKey]
        );
        $saved = yovel_admin_hr_insert_lifecycle($db, $company, $admin, 'PROMOTION', [
            'lifecycle_key' => $input['lifecycle_key'] ?? '',
            'employee_key' => $employeeKey,
            'job_position_key' => $jobPositionKey,
            'setup_master_key' => $gradeKey,
            'effective_date' => $effectiveDate,
            'record_status' => $status,
            'reason' => $reason,
            'payload' => ['current_ctc' => $currentCtc, 'revised_ctc' => $revisedCtc],
        ]);
        if (in_array($status, ['SUBMITTED', 'APPROVED'], true)) {
            yovel_admin_upsert_hr_employee_primary_assignment($db, $company, $admin, $employeeKey, [
                'branch_key' => (string) ($current['branch_key'] ?? $employee['branch_key'] ?? ''),
                'project_key' => (string) ($current['project_key'] ?? ''),
                'department_key' => (string) ($current['department_key'] ?? $employee['department_key'] ?? ''),
                'job_position_key' => $jobPositionKey,
                'team_key' => (string) ($current['team_key'] ?? $employee['team_key'] ?? ''),
                'reports_to_employee_key' => (string) ($current['reports_to_employee_key'] ?? $employee['reports_to_employee_key'] ?? ''),
                'effective_from' => $effectiveDate,
                'assignment_notes' => 'Employee Promotion ' . (string) $saved['lifecycle_key'] . ': ' . $reason,
            ]);
            yovel_admin_hr_property_history(
                $db, $company, $admin, $employeeKey, (string) $saved['lifecycle_key'], 'job_position_key',
                (string) ($current['job_position_key'] ?? $employee['job_position_key'] ?? ''), $jobPositionKey, $effectiveDate
                , 'Designation'
            );
            yovel_admin_hr_property_history(
                $db, $company, $admin, $employeeKey, (string) $saved['lifecycle_key'], 'employee_grade_key',
                '', $gradeKey, $effectiveDate, 'Employee Grade'
            );
            if ($revisedCtc !== '') {
                yovel_admin_hr_property_history(
                    $db, $company, $admin, $employeeKey, (string) $saved['lifecycle_key'], 'annual_ctc',
                    $currentCtc, $revisedCtc, $effectiveDate, 'Annual CTC'
                );
            }
        }
        $saved['promotion_status'] = (string) $saved['record_status'];
        bx_audit('CREATE', 'project_company_hr_employee_promotion', (string) $saved['lifecycle_key'], [
            'company_key' => $companyKey,
            'employee_key' => $employeeKey,
            'promotion_status' => $status,
            'effective_date' => $effectiveDate,
            'admin_key' => $adminKey,
        ], 'Company administrator recorded an employee promotion.');
        if ($failureInjector !== null) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_persist_hr_lifecycle_record(
    ADOConnection $db,
    array $company,
    array $admin,
    string $recordType,
    array $input,
    ?callable $failureInjector = null
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    if (!isset(yovel_admin_hr_setup_lifecycle_types()[$recordType])) {
        throw new InvalidArgumentException('Unknown HR lifecycle record type.');
    }
    $employeeRequired = in_array($recordType, ['EMPLOYEE_GRIEVANCE', 'APPRAISEE'], true);
    $employeeKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_hr_employee', 'employee_key', $input['employee_key'] ?? '', 'Employee', $employeeRequired);
    $relatedEmployeeKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_hr_employee', 'employee_key', $input['approver_employee_key'] ?? $input['related_employee_key'] ?? '', 'Related employee');
    $departmentKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_department', 'department_key', $input['department_key'] ?? '', 'Department', $recordType === 'DEPARTMENT_APPROVER');
    $approverUserKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_user', 'user_key', $input['approver_user_key'] ?? '', 'Approver user', $recordType === 'DEPARTMENT_APPROVER');
    $setupMasterKey = yovel_admin_hr_setup_key($db, $companyKeyHash, 'project_company_hr_setup_master', 'setup_master_key', $input['grievance_type_key'] ?? '', 'Grievance type', $recordType === 'EMPLOYEE_GRIEVANCE');
    if ($recordType === 'EMPLOYEE_GRIEVANCE') {
        $masterType = (string) $db->GetOne('SELECT record_type FROM project_company_hr_setup_master WHERE company_key_hash = ? AND setup_master_key = ? LIMIT 1', [$companyKeyHash, $setupMasterKey]);
        if ($masterType !== 'GRIEVANCE_TYPE') {
            throw new InvalidArgumentException('The selected Grievance Type is invalid.');
        }
    }
    $effectiveDate = yovel_admin_hr_setup_date($input);
    $allowedStatuses = match ($recordType) {
        'EMPLOYEE_GRIEVANCE' => ['OPEN', 'INVESTIGATED', 'RESOLVED', 'INVALID', 'CANCELLED'],
        default => ['DRAFT', 'ACTIVE', 'INACTIVE', 'CANCELLED'],
    };
    $status = yovel_admin_status((string) ($input['record_status'] ?? $allowedStatuses[0]), $allowedStatuses, $allowedStatuses[0]);
    $reason = trim((string) ($input['description'] ?? $input['reason'] ?? ''));
    $payload = yovel_admin_hr_lifecycle_payload($input);
    $payload['approver_user_key'] = $approverUserKey;
    if ($recordType === 'EMPLOYEE_GRIEVANCE' && ($payload['subject'] === '' || $reason === '')) {
        throw new InvalidArgumentException('Grievance subject and description are required.');
    }
    if ($recordType === 'EMPLOYEE_GRIEVANCE' && $status === 'RESOLVED'
        && ($payload['resolution_detail'] === '' || $payload['resolution_date'] === '')) {
        throw new InvalidArgumentException('Resolved grievances require resolution details and a resolution date.');
    }
    if ($recordType === 'APPRAISEE' && strlen($payload['appraisal_template_key']) > 160) {
        throw new InvalidArgumentException('Appraisal template reference is too long.');
    }

    $placement = [];
    if ($employeeKey !== '') {
        $placement = $db->GetRow(
            "SELECT COALESCE(assignment.department_key, employee.department_key, '') AS department_key,
                    COALESCE(assignment.branch_key, employee.branch_key, '') AS branch_key,
                    COALESCE(assignment.job_position_key, employee.job_position_key, '') AS job_position_key
             FROM project_company_hr_employee employee
             LEFT JOIN project_company_hr_employee_assignment assignment
               ON assignment.company_key_hash = employee.company_key_hash
              AND assignment.employee_key = employee.employee_key
              AND assignment.assignment_status = 'ACTIVE' AND assignment.is_primary = 1
             WHERE employee.company_key_hash = ? AND employee.employee_key = ? LIMIT 1",
            [$companyKeyHash, $employeeKey]
        );
        $placement = is_array($placement) ? $placement : [];
    }

    return yovel_admin_hr_in_transaction($db, static function () use (
        $db, $company, $admin, $companyKey, $adminKey, $recordType, $input, $employeeKey,
        $relatedEmployeeKey, $departmentKey, $setupMasterKey, $effectiveDate, $status, $reason, $payload, $placement, $failureInjector
    ): array {
        $saved = yovel_admin_hr_insert_lifecycle($db, $company, $admin, $recordType, [
            'lifecycle_key' => $input['lifecycle_key'] ?? '',
            'employee_key' => $employeeKey,
            'related_employee_key' => $relatedEmployeeKey,
            'department_key' => $departmentKey !== '' ? $departmentKey : (string) ($placement['department_key'] ?? ''),
            'branch_key' => (string) ($placement['branch_key'] ?? ''),
            'job_position_key' => (string) ($placement['job_position_key'] ?? ''),
            'setup_master_key' => $setupMasterKey,
            'effective_date' => $effectiveDate,
            'record_status' => $status,
            'reason' => $reason,
            'payload' => $payload,
            'mutable_statuses' => $recordType === 'EMPLOYEE_GRIEVANCE' ? ['OPEN', 'INVESTIGATED'] : ['DRAFT'],
        ]);
        bx_audit('CREATE', 'project_company_hr_' . strtolower($recordType), (string) $saved['lifecycle_key'], [
            'company_key' => $companyKey,
            'employee_key' => $employeeKey,
            'record_status' => $status,
            'admin_key' => $adminKey,
        ], 'Company administrator saved an HR employee lifecycle record.');
        if ($failureInjector !== null) {
            $failureInjector($saved);
        }
        return $saved;
    });
}

function yovel_admin_persist_hr_setup_record(
    ADOConnection $db,
    array $company,
    array $admin,
    string $recordType,
    array $input,
    ?callable $failureInjector = null
): array {
    $recordType = strtoupper(trim($recordType));
    return match (true) {
        $recordType === 'HR_SETTINGS' => yovel_admin_persist_hr_settings($db, $company, $admin, $input, $failureInjector),
        isset(yovel_admin_hr_setup_master_types()[$recordType]) => yovel_admin_persist_hr_setup_master($db, $company, $admin, $recordType, $input, $failureInjector),
        isset(yovel_admin_hr_setup_lifecycle_types()[$recordType]) => yovel_admin_persist_hr_lifecycle_record($db, $company, $admin, $recordType, $input, $failureInjector),
        default => throw new InvalidArgumentException('Unsupported HR Setup record type.'),
    };
}

function yovel_admin_hr_organization_chart(array $company): array
{
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $rows = bx_db()->GetAll(
        "SELECT employee.employee_key, employee.employee_code, employee.employee_name, employee.employee_status,
                COALESCE(assignment.reports_to_employee_key, employee.reports_to_employee_key, '') AS reports_to_employee_key,
                COALESCE(manager.employee_name, '') AS reports_to_employee_name,
                COALESCE(position.job_position_name, '') AS job_position_name,
                COALESCE(department.department_name, '') AS department_name
         FROM project_company_hr_employee employee
         LEFT JOIN project_company_hr_employee_assignment assignment
           ON assignment.company_key_hash = employee.company_key_hash
          AND assignment.employee_key = employee.employee_key
          AND assignment.assignment_status = 'ACTIVE'
          AND assignment.is_primary = 1
         LEFT JOIN project_company_hr_employee manager
           ON manager.company_key_hash = employee.company_key_hash
          AND manager.employee_key = COALESCE(assignment.reports_to_employee_key, employee.reports_to_employee_key)
         LEFT JOIN project_company_hr_job_position position
           ON position.company_key_hash = employee.company_key_hash
          AND position.job_position_key = COALESCE(assignment.job_position_key, employee.job_position_key)
         LEFT JOIN project_company_department department
           ON department.company_key_hash = employee.company_key_hash
          AND department.department_key = COALESCE(assignment.department_key, employee.department_key)
         WHERE employee.company_key_hash = ? AND employee.employee_status <> 'DELETED'
         ORDER BY reports_to_employee_name, employee.employee_name",
        [$companyKeyHash]
    );
    return is_array($rows) ? $rows : [];
}

function yovel_admin_hr_setup_data(array $company): array
{
    yovel_admin_hr_schema();
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $db = bx_db();
    $settings = $db->GetRow('SELECT * FROM project_company_hr_setting WHERE company_key_hash = ? LIMIT 1', [$companyKeyHash]);
    $masters = $db->GetAll(
        "SELECT * FROM project_company_hr_setup_master
         WHERE company_key_hash = ? AND record_status <> 'DELETED'
         ORDER BY record_type, record_name, record_code",
        [$companyKeyHash]
    );
    $lifecycle = $db->GetAll(
        "SELECT lifecycle.*, employee.employee_name, employee.employee_code,
                related.employee_name AS related_employee_name, department.department_name,
                position.job_position_name, setup_master.record_name AS setup_master_name
         FROM project_company_hr_employee_lifecycle lifecycle
         LEFT JOIN project_company_hr_employee employee ON employee.company_key_hash = lifecycle.company_key_hash AND employee.employee_key = lifecycle.employee_key
         LEFT JOIN project_company_hr_employee related ON related.company_key_hash = lifecycle.company_key_hash AND related.employee_key = lifecycle.related_employee_key
         LEFT JOIN project_company_department department ON department.company_key_hash = lifecycle.company_key_hash AND department.department_key = lifecycle.department_key
         LEFT JOIN project_company_hr_job_position position ON position.company_key_hash = lifecycle.company_key_hash AND position.job_position_key = lifecycle.job_position_key
         LEFT JOIN project_company_hr_setup_master setup_master ON setup_master.company_key_hash = lifecycle.company_key_hash AND setup_master.setup_master_key = lifecycle.setup_master_key
         WHERE lifecycle.company_key_hash = ? AND lifecycle.record_status <> 'DELETED'
         ORDER BY lifecycle.effective_date DESC, lifecycle.created_at DESC
         LIMIT 300",
        [$companyKeyHash]
    );
    $propertyHistory = $db->GetAll(
        "SELECT history.*, employee.employee_name, employee.employee_code
         FROM project_company_hr_employee_property_history history
         INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = history.company_key_hash AND employee.employee_key = history.employee_key
         WHERE history.company_key_hash = ?
         ORDER BY history.effective_date DESC, history.x_id DESC
         LIMIT 300",
        [$companyKeyHash]
    );
    $users = $db->GetAll(
        "SELECT user_key, user_name, user_email
         FROM project_company_user
         WHERE company_key_hash = ? AND user_status = 'ACTIVE'
         ORDER BY user_name, user_email",
        [$companyKeyHash]
    );
    return [
        'settings' => is_array($settings) ? $settings : [],
        'masters' => is_array($masters) ? $masters : [],
        'lifecycle' => is_array($lifecycle) ? $lifecycle : [],
        'propertyHistory' => is_array($propertyHistory) ? $propertyHistory : [],
        'organizationChart' => yovel_admin_hr_organization_chart($company),
        'formTargets' => yovel_admin_hr_setup_form_targets(),
        'users' => is_array($users) ? $users : [],
    ];
}

function yovel_admin_hr_handle_post(array $company, array $admin, string $action, array $input): array
{
    yovel_admin_hr_scope($company, $admin);
    if (strtolower(trim((string) ($input['module_view'] ?? ''))) !== 'hr') {
        throw new InvalidArgumentException('HR module scope is invalid.');
    }
    if (str_starts_with($action, 'hr_attendance_')) {
        return yovel_admin_hr_handle_attendance_post($company, $admin, $action, $input);
    }
    if (str_starts_with($action, 'hr_leave_')) {
        return yovel_admin_hr_handle_leave_post($company, $admin, $action, $input);
    }
    if (str_starts_with($action, 'hr_recruitment_')) {
        return yovel_admin_hr_handle_recruitment_post($company, $admin, $action, $input);
    }
    $db = bx_db();
    $recordType = strtoupper(trim((string) ($input['record_type'] ?? '')));
    $saved = match ($action) {
        'hr_setup_save_record' => yovel_admin_persist_hr_setup_record($db, $company, $admin, $recordType, $input),
        'hr_setup_transfer' => yovel_admin_persist_employee_transfer($db, $company, $admin, $input),
        'hr_setup_promotion' => yovel_admin_persist_employee_promotion($db, $company, $admin, $input),
        default => throw new InvalidArgumentException('This HR action is not available.'),
    };
    $label = $action === 'hr_setup_transfer' ? 'Employee transfer' : ($action === 'hr_setup_promotion' ? 'Employee promotion' : (yovel_admin_hr_setup_master_types()[$recordType] ?? yovel_admin_hr_setup_lifecycle_types()[$recordType] ?? 'HR Setup record'));
    $recordKey = (string) ($saved['lifecycle_key'] ?? $saved['setup_master_key'] ?? $saved['hr_setting_key'] ?? '');
    return [
        'message' => $label . ' saved.',
        'section' => 'dashboard',
        'query' => ['workspace' => 'setup', 'record' => $recordKey],
    ];
}
