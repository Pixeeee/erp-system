<?php
declare(strict_types=1);

function yovel_admin_hr_onboarding_form_targets(): array
{
    return [
        'employee-onboarding-template' => ['label' => 'Employee Onboarding Template', 'protected_fields' => ['boarding_template_key', 'template_code', 'template_type', 'template_status'], 'versioned' => true],
        'employee-onboarding' => ['label' => 'Employee Onboarding', 'protected_fields' => ['employee_onboarding_key', 'employee_key', 'onboarding_status', 'source_reference'], 'versioned' => true],
        'employee-separation-template' => ['label' => 'Employee Separation Template', 'protected_fields' => ['boarding_template_key', 'template_code', 'template_type', 'template_status'], 'versioned' => true],
        'employee-separation' => ['label' => 'Employee Separation', 'protected_fields' => ['employee_separation_key', 'employee_key', 'separation_status', 'relieving_date'], 'versioned' => true],
        'employee-boarding-activity' => ['label' => 'Employee Boarding Activity', 'protected_fields' => ['employee_boarding_activity_key', 'parent_type', 'parent_key', 'activity_status'], 'versioned' => true],
        'exit-interview' => ['label' => 'Exit Interview', 'protected_fields' => ['exit_interview_key', 'employee_separation_key', 'scheduled_at', 'exit_interview_status'], 'versioned' => true],
        'employee-document-requirement' => ['label' => 'Employee Document Requirement', 'protected_fields' => ['employee_document_requirement_key', 'employee_key', 'identification_document_type_key', 'requirement_status'], 'versioned' => true],
        'identification-document-type' => ['label' => 'Identification Document Type', 'protected_fields' => ['identification_document_type_key', 'document_type_code', 'document_type_status'], 'versioned' => true],
        'full-and-final-statement' => ['label' => 'Full and Final Statement', 'protected_fields' => ['full_and_final_statement_key', 'employee_separation_key', 'statement_status'], 'versioned' => true],
    ];
}

function yovel_admin_hr_onboarding_default_form_fields(): array
{
    return [
        'employee-onboarding-template' => [
            ['template_code', 'Template code', 'TEXT', 'overview', 10, 1, 1, ''], ['template_name', 'Template name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['template_status', 'Status', 'SELECT', 'overview', 30, 1, 1, "ACTIVE\nINACTIVE"], ['description', 'Description', 'TEXTAREA', 'details', 40, 0, 0, ''],
        ],
        'employee-onboarding' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''], ['boarding_template_key', 'Template', 'SELECT', 'overview', 20, 1, 0, ''],
            ['start_date', 'Start date', 'DATE', 'dates', 30, 1, 1, ''], ['expected_completion_date', 'Expected completion', 'DATE', 'dates', 40, 0, 0, ''],
            ['onboarding_status', 'Status', 'SELECT', 'overview', 50, 1, 1, "DRAFT\nIN_PROGRESS\nCOMPLETED\nCANCELLED"],
        ],
        'employee-separation-template' => [
            ['template_code', 'Template code', 'TEXT', 'overview', 10, 1, 1, ''], ['template_name', 'Template name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['template_status', 'Status', 'SELECT', 'overview', 30, 1, 1, "ACTIVE\nINACTIVE"], ['description', 'Description', 'TEXTAREA', 'details', 40, 0, 0, ''],
        ],
        'employee-separation' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''], ['boarding_template_key', 'Template', 'SELECT', 'overview', 20, 0, 0, ''],
            ['resignation_date', 'Resignation date', 'DATE', 'dates', 30, 1, 0, ''], ['relieving_date', 'Relieving date', 'DATE', 'dates', 40, 1, 1, ''],
            ['reason', 'Reason', 'TEXTAREA', 'details', 50, 0, 0, ''], ['separation_status', 'Status', 'SELECT', 'approval', 60, 1, 1, "DRAFT\nSUBMITTED\nAPPROVED\nREJECTED\nCANCELLED"],
        ],
        'employee-boarding-activity' => [
            ['activity_name', 'Activity', 'TEXT', 'overview', 10, 1, 0, ''], ['owner_employee_key', 'Owner', 'SELECT', 'overview', 20, 0, 0, ''],
            ['due_date', 'Due date', 'DATE', 'dates', 30, 0, 0, ''], ['activity_status', 'Status', 'SELECT', 'overview', 40, 1, 1, "OPEN\nCOMPLETED\nCANCELLED"],
        ],
        'exit-interview' => [
            ['employee_separation_key', 'Separation', 'SELECT', 'overview', 10, 1, 1, ''], ['interviewer_employee_key', 'Interviewer', 'SELECT', 'overview', 20, 1, 0, ''],
            ['scheduled_at', 'Scheduled at', 'TEXT', 'schedule', 30, 1, 1, ''], ['exit_interview_status', 'Status', 'SELECT', 'overview', 40, 1, 1, "SCHEDULED\nCOMPLETED\nCANCELLED"],
        ],
        'employee-document-requirement' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''], ['identification_document_type_key', 'Document type', 'SELECT', 'overview', 20, 1, 1, ''],
            ['context_type', 'Context', 'SELECT', 'overview', 30, 1, 1, "EMPLOYEE\nONBOARDING\nSEPARATION"], ['requirement_status', 'Status', 'SELECT', 'overview', 40, 1, 1, "DRAFT\nSUBMITTED\nVERIFIED\nREJECTED"],
        ],
        'identification-document-type' => [
            ['document_type_code', 'Document type code', 'TEXT', 'overview', 10, 1, 1, ''], ['document_type_name', 'Document type name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['requires_expiry_date', 'Requires expiry date', 'CHECKBOX', 'rules', 30, 0, 0, ''], ['document_type_status', 'Status', 'SELECT', 'overview', 40, 1, 1, "ACTIVE\nINACTIVE"],
        ],
        'full-and-final-statement' => [
            ['employee_separation_key', 'Separation', 'SELECT', 'overview', 10, 1, 1, ''], ['asset_clearance_status', 'Asset clearance', 'SELECT', 'clearance', 20, 1, 1, "PENDING\nCLEARED\nBLOCKED"],
            ['outstanding_status', 'Outstanding status', 'SELECT', 'clearance', 30, 1, 1, "PENDING\nCLEARED\nBLOCKED"], ['statement_status', 'Status', 'SELECT', 'overview', 40, 1, 1, "DRAFT\nCLEARED\nBLOCKED\nCANCELLED"],
        ],
    ];
}

function yovel_admin_hr_ensure_onboarding_schema(ADOConnection $db): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_hr_identification_document_type (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, identification_document_type_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, document_type_code VARCHAR(80) NOT NULL, document_type_name VARCHAR(180) NOT NULL, requires_expiry_date TINYINT(1) NOT NULL DEFAULT 0, document_type_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_document_type_code (company_key_hash, document_type_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_boarding_template (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, boarding_template_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, template_type VARCHAR(20) NOT NULL, template_code VARCHAR(80) NOT NULL, template_name VARCHAR(180) NOT NULL, description TEXT NULL, template_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_boarding_template_code (company_key_hash, template_type, template_code), INDEX idx_hr_boarding_template_type (company_key_hash, template_type, template_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_boarding_template_activity (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, boarding_template_activity_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, boarding_template_key CHAR(36) NOT NULL, activity_name VARCHAR(220) NOT NULL, owner_employee_key CHAR(36) NULL, due_days_offset INT NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_hr_boarding_template_activity (company_key_hash, boarding_template_key, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_employee_onboarding (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_onboarding_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL, boarding_template_key CHAR(36) NULL, job_offer_key CHAR(36) NULL, source_reference VARCHAR(180) NOT NULL, start_date DATE NOT NULL, expected_completion_date DATE NULL, completed_at DATETIME NULL, onboarding_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_onboarding_source (company_key_hash, source_reference), INDEX idx_hr_onboarding_employee (company_key_hash, employee_key, onboarding_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_employee_separation (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_separation_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL, boarding_template_key CHAR(36) NULL, resignation_date DATE NOT NULL, relieving_date DATE NOT NULL, reason TEXT NULL, separation_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', approved_by_admin_key CHAR(36) NULL, approved_at DATETIME NULL, immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_hr_separation_employee (company_key_hash, employee_key, separation_status, relieving_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_employee_boarding_activity (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_boarding_activity_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, parent_type VARCHAR(20) NOT NULL, parent_key CHAR(36) NOT NULL, employee_key CHAR(36) NOT NULL, activity_name VARCHAR(220) NOT NULL, owner_employee_key CHAR(36) NULL, due_date DATE NULL, activity_status VARCHAR(20) NOT NULL DEFAULT 'OPEN', completed_at DATETIME NULL, completion_notes TEXT NULL, sort_order INT NOT NULL DEFAULT 0, immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_hr_boarding_activity_parent (company_key_hash, parent_type, parent_key, activity_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_employee_document_requirement (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_document_requirement_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL, identification_document_type_key CHAR(36) NOT NULL, context_type VARCHAR(20) NOT NULL, context_key CHAR(36) NULL, requirement_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', received_at DATETIME NULL, expiry_date DATE NULL, verification_notes TEXT NULL, immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_document_requirement (company_key_hash, employee_key, identification_document_type_key, context_type, context_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_exit_interview (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, exit_interview_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_separation_key CHAR(36) NOT NULL, employee_key CHAR(36) NOT NULL, interviewer_employee_key CHAR(36) NOT NULL, scheduled_at DATETIME NOT NULL, timezone_name VARCHAR(80) NOT NULL, questionnaire_json LONGTEXT NOT NULL, result_json LONGTEXT NULL, exit_interview_status VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED', completed_at DATETIME NULL, immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_hr_exit_interview_schedule (company_key_hash, scheduled_at, exit_interview_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_full_and_final_statement (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, full_and_final_statement_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_separation_key CHAR(36) NOT NULL, employee_key CHAR(36) NOT NULL, asset_clearance_status VARCHAR(20) NOT NULL DEFAULT 'PENDING', outstanding_status VARCHAR(20) NOT NULL DEFAULT 'PENDING', statement_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_full_final_separation (company_key_hash, employee_separation_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_full_and_final_asset (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, full_and_final_asset_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, full_and_final_statement_key CHAR(36) NOT NULL, asset_reference VARCHAR(180) NOT NULL, clearance_status VARCHAR(20) NOT NULL DEFAULT 'PENDING', remarks TEXT NULL, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_full_final_asset (company_key_hash, full_and_final_statement_key, asset_reference)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_full_and_final_outstanding_statement (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, full_and_final_outstanding_statement_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, full_and_final_statement_key CHAR(36) NOT NULL, source_reference VARCHAR(180) NOT NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0, clearance_status VARCHAR(20) NOT NULL DEFAULT 'PENDING', remarks TEXT NULL, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_full_final_outstanding (company_key_hash, full_and_final_statement_key, source_reference)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) { yovel_admin_db_execute($db, $statement, [], 'HR Onboarding schema update'); }
}

function yovel_admin_hr_onboarding_readback(ADOConnection $db, string $table, string $column, string $hash, string $key): array
{
    $allowed = [
        'project_company_hr_identification_document_type' => 'identification_document_type_key',
        'project_company_hr_boarding_template' => 'boarding_template_key',
        'project_company_hr_employee_onboarding' => 'employee_onboarding_key',
        'project_company_hr_employee_separation' => 'employee_separation_key',
        'project_company_hr_employee_boarding_activity' => 'employee_boarding_activity_key',
        'project_company_hr_employee_document_requirement' => 'employee_document_requirement_key',
        'project_company_hr_exit_interview' => 'exit_interview_key',
        'project_company_hr_full_and_final_statement' => 'full_and_final_statement_key',
    ];
    if (($allowed[$table] ?? '') !== $column) { throw new LogicException('Onboarding read-back target is invalid.'); }
    $row = $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash = ? AND {$column} = ? LIMIT 1", [$hash, $key]);
    if (!is_array($row) || $row === [] || !hash_equals($key, (string) ($row[$column] ?? ''))) { throw new RuntimeException('Onboarding record read-back failed.'); }
    return $row;
}

function yovel_admin_hr_onboarding_date(mixed $value, string $label): string
{
    $date = yovel_admin_optional_date(trim((string) $value), $label);
    if ($date === '') { throw new InvalidArgumentException($label . ' is required.'); }
    return $date;
}

function yovel_admin_persist_identification_document_type(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $providedKey = trim((string) ($input['identification_document_type_key'] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_identification_document_type', 'identification_document_type_key', $providedKey, 'Identification Document Type', true);
            $existing = $db->GetRow('SELECT * FROM project_company_hr_identification_document_type WHERE company_key_hash = ? AND identification_document_type_key = ? FOR UPDATE', [$hash, $providedKey]);
        }
        $key = $providedKey ?: bx_uuid();
        $code = yovel_admin_hr_leave_code($input['document_type_code'] ?? '', 'Document type code');
        $name = yovel_admin_hr_leave_text($input['document_type_name'] ?? '', 'Document type name', 180);
        $requiresExpiry = in_array($input['requires_expiry_date'] ?? null, [1, '1', true, 'on', 'yes'], true) ? 1 : 0;
        $status = yovel_admin_status((string) ($input['document_type_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_identification_document_type (identification_document_type_key, company_key, company_key_hash, document_type_code, document_type_name, requires_expiry_date, document_type_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE document_type_name=VALUES(document_type_name), requires_expiry_date=VALUES(requires_expiry_date), document_type_status=VALUES(document_type_status), updated_by_admin_key=VALUES(updated_by_admin_key)', [$key, $companyKey, $hash, $code, $name, $requiresExpiry, $status, $adminKey, $adminKey], 'Identification Document Type save');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_identification_document_type', 'identification_document_type_key', $hash, $key);
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_identification_document_type', $key, ['company_key' => $companyKey, 'document_type_status' => $status], 'Company administrator saved an Identification Document Type.');
        return $saved;
    });
}

function yovel_admin_hr_onboarding_json_rows(array $input, string $name): array
{
    $value = $input[$name] ?? [];
    if (is_array($value)) { return $value; }
    $trimmed = trim((string) $value);
    if ($trimmed === '') { return []; }
    $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) { throw new InvalidArgumentException(ucwords(str_replace('_', ' ', $name)) . ' must be a list.'); }
    return $decoded;
}

function yovel_admin_persist_boarding_template(ADOConnection $db, array $company, array $admin, string $templateType, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $templateType = yovel_admin_status(strtoupper(trim($templateType)), ['ONBOARDING', 'SEPARATION'], 'ONBOARDING');
    $activities = is_array($input['activities'] ?? null) ? $input['activities'] : [];
    if ($activities === []) { throw new InvalidArgumentException('Boarding Template requires at least one activity.'); }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $templateType, $input, $activities, $failureInjector): array {
        $providedKey = trim((string) ($input['boarding_template_key'] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_boarding_template', 'boarding_template_key', $providedKey, 'Boarding Template', true);
            $existing = $db->GetRow('SELECT * FROM project_company_hr_boarding_template WHERE company_key_hash = ? AND boarding_template_key = ? FOR UPDATE', [$hash, $providedKey]);
        }
        $key = $providedKey ?: bx_uuid();
        $code = yovel_admin_hr_leave_code($input['template_code'] ?? '', 'Template code');
        $name = yovel_admin_hr_leave_text($input['template_name'] ?? '', 'Template name', 180);
        $description = yovel_admin_hr_leave_text($input['description'] ?? '', 'Description', 8000, false);
        $status = yovel_admin_status((string) ($input['template_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_boarding_template (boarding_template_key, company_key, company_key_hash, template_type, template_code, template_name, description, template_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE template_name=VALUES(template_name), description=VALUES(description), template_status=VALUES(template_status), updated_by_admin_key=VALUES(updated_by_admin_key)', [$key, $companyKey, $hash, $templateType, $code, $name, $description ?: null, $status, $adminKey, $adminKey], 'Boarding Template save');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_boarding_template_activity WHERE company_key_hash = ? AND boarding_template_key = ?', [$hash, $key], 'Boarding Template activities replace');
        foreach ($activities as $activity) {
            if (!is_array($activity)) { continue; }
            $owner = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee', 'employee_key', $activity['owner_employee_key'] ?? '', 'Owner employee');
            $activityName = yovel_admin_hr_leave_text($activity['activity_name'] ?? '', 'Activity name', 220);
            $offset = max(-365, min(365, (int) ($activity['due_days_offset'] ?? 0)));
            $sort = max(0, min(10000, (int) ($activity['sort_order'] ?? 0)));
            yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_boarding_template_activity (boarding_template_activity_key, company_key, company_key_hash, boarding_template_key, activity_name, owner_employee_key, due_days_offset, sort_order, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $hash, $key, $activityName, $owner ?: null, $offset, $sort, $adminKey], 'Boarding Template Activity save');
        }
        if ($failureInjector) { $failureInjector(); }
        if ((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_boarding_template_activity WHERE company_key_hash = ? AND boarding_template_key = ?', [$hash, $key]) < 1) { throw new RuntimeException('Boarding Template activities were not verified.'); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_boarding_template', 'boarding_template_key', $hash, $key);
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_boarding_template', $key, ['company_key' => $companyKey, 'template_type' => $templateType], 'Company administrator saved a Boarding Template.');
        return $saved;
    });
}

function yovel_admin_hr_clone_boarding_template_activities(ADOConnection $db, array $scope, string $templateKey, string $parentType, string $parentKey, string $employeeKey, string $baseDate): void
{
    [$companyKey, $hash, $adminKey] = $scope;
    $activities = $db->GetAll('SELECT * FROM project_company_hr_boarding_template_activity WHERE company_key_hash = ? AND boarding_template_key = ? ORDER BY sort_order, x_id', [$hash, $templateKey]) ?: [];
    foreach ($activities as $activity) {
        $dueDate = $baseDate;
        $offset = (int) ($activity['due_days_offset'] ?? 0);
        if ($offset !== 0) { $dueDate = (new DateTimeImmutable($baseDate))->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d'); }
        $activityName = (string) $activity['activity_name'];
        $checksum = yovel_admin_hr_leave_checksum(compact('parentType', 'parentKey', 'employeeKey', 'activityName', 'dueDate'));
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_employee_boarding_activity (employee_boarding_activity_key, company_key, company_key_hash, parent_type, parent_key, employee_key, activity_name, owner_employee_key, due_date, activity_status, sort_order, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $hash, $parentType, $parentKey, $employeeKey, $activityName, ($activity['owner_employee_key'] ?: null), $dueDate, 'OPEN', (int) $activity['sort_order'], $checksum, $adminKey, $adminKey], 'Boarding Activity clone');
    }
}

function yovel_admin_start_employee_onboarding(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $hash, $adminKey] = $scope;
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $employeeKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee', 'employee_key', $input['employee_key'] ?? '', 'Employee', true);
        $templateKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_boarding_template', 'boarding_template_key', $input['boarding_template_key'] ?? '', 'Onboarding Template');
        if ($templateKey !== '' && (string) $db->GetOne('SELECT template_type FROM project_company_hr_boarding_template WHERE company_key_hash = ? AND boarding_template_key = ?', [$hash, $templateKey]) !== 'ONBOARDING') { throw new InvalidArgumentException('The selected template is not an onboarding template.'); }
        $startDate = yovel_admin_hr_onboarding_date($input['start_date'] ?? date('Y-m-d'), 'Start date');
        $expected = yovel_admin_optional_date((string) ($input['expected_completion_date'] ?? ''), 'Expected completion date') ?: null;
        $status = yovel_admin_status((string) ($input['onboarding_status'] ?? 'DRAFT'), ['DRAFT', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'], 'DRAFT');
        $key = trim((string) ($input['employee_onboarding_key'] ?? '')) ?: bx_uuid();
        $source = trim((string) ($input['source_reference'] ?? 'ONBOARDING-' . $key));
        $offerKey = trim((string) ($input['job_offer_key'] ?? '')) ?: null;
        $checksum = yovel_admin_hr_leave_checksum(compact('employeeKey', 'templateKey', 'startDate', 'expected', 'status', 'source'));
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_employee_onboarding (employee_onboarding_key, company_key, company_key_hash, employee_key, boarding_template_key, job_offer_key, source_reference, start_date, expected_completion_date, onboarding_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE expected_completion_date=VALUES(expected_completion_date), onboarding_status=VALUES(onboarding_status), updated_by_admin_key=VALUES(updated_by_admin_key)', [$key, $companyKey, $hash, $employeeKey, $templateKey ?: null, $offerKey, $source, $startDate, $expected, $status, $checksum, $adminKey, $adminKey], 'Employee Onboarding save');
        if ($templateKey !== '' && (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_employee_boarding_activity WHERE company_key_hash = ? AND parent_key = ?', [$hash, $key]) === 0) { yovel_admin_hr_clone_boarding_template_activities($db, $scope, $templateKey, 'ONBOARDING', $key, $employeeKey, $startDate); }
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_employee_onboarding', 'employee_onboarding_key', $hash, $key);
        bx_audit('CREATE', 'project_company_hr_employee_onboarding', $key, ['company_key' => $companyKey, 'onboarding_status' => $status], 'Company administrator started Employee Onboarding.');
        return $saved;
    });
}

function yovel_admin_complete_boarding_activity(ADOConnection $db, array $company, array $admin, string $activityKey, string $notes = '', ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    if (!yovel_admin_is_uuid($activityKey)) { throw new InvalidArgumentException('Boarding Activity is invalid.'); }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $activityKey, $notes, $failureInjector): array {
        $row = $db->GetRow('SELECT * FROM project_company_hr_employee_boarding_activity WHERE company_key_hash = ? AND employee_boarding_activity_key = ? FOR UPDATE', [$hash, $activityKey]);
        if (!is_array($row) || $row === []) { throw new InvalidArgumentException('Boarding Activity does not belong to this company.'); }
        if ((string) $row['activity_status'] === 'CANCELLED') { throw new RuntimeException('Cancelled activities cannot be completed.'); }
        yovel_admin_db_execute($db, "UPDATE project_company_hr_employee_boarding_activity SET activity_status = 'COMPLETED', completed_at = NOW(), completion_notes = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND employee_boarding_activity_key = ?", [yovel_admin_hr_leave_text($notes, 'Completion notes', 8000, false) ?: null, $adminKey, $hash, $activityKey], 'Boarding Activity completion');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_employee_boarding_activity', 'employee_boarding_activity_key', $hash, $activityKey);
        bx_audit('UPDATE', 'project_company_hr_employee_boarding_activity', $activityKey, ['company_key' => $companyKey, 'activity_status' => 'COMPLETED'], 'Company administrator completed a Boarding Activity.');
        return $saved;
    });
}

function yovel_admin_persist_employee_document_requirement(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $employeeKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee', 'employee_key', $input['employee_key'] ?? '', 'Employee', true);
        $typeKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_identification_document_type', 'identification_document_type_key', $input['identification_document_type_key'] ?? '', 'Document type', true);
        $contextType = yovel_admin_status((string) ($input['context_type'] ?? 'EMPLOYEE'), ['EMPLOYEE', 'ONBOARDING', 'SEPARATION'], 'EMPLOYEE');
        $contextKey = trim((string) ($input['context_key'] ?? '')) ?: '';
        if ($contextKey !== '' && !yovel_admin_is_uuid($contextKey)) { throw new InvalidArgumentException('Document context is invalid.'); }
        $status = yovel_admin_status((string) ($input['requirement_status'] ?? 'DRAFT'), ['DRAFT', 'SUBMITTED', 'VERIFIED', 'REJECTED'], 'DRAFT');
        $received = trim((string) ($input['received_at'] ?? '')) ?: null;
        $expiry = yovel_admin_optional_date((string) ($input['expiry_date'] ?? ''), 'Expiry date') ?: null;
        $notes = yovel_admin_hr_leave_text($input['verification_notes'] ?? '', 'Verification notes', 8000, false);
        $key = trim((string) ($input['employee_document_requirement_key'] ?? '')) ?: bx_uuid();
        $checksum = yovel_admin_hr_leave_checksum(compact('employeeKey', 'typeKey', 'contextType', 'contextKey', 'status', 'received', 'expiry'));
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_employee_document_requirement (employee_document_requirement_key, company_key, company_key_hash, employee_key, identification_document_type_key, context_type, context_key, requirement_status, received_at, expiry_date, verification_notes, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE requirement_status=VALUES(requirement_status), received_at=VALUES(received_at), expiry_date=VALUES(expiry_date), verification_notes=VALUES(verification_notes), updated_by_admin_key=VALUES(updated_by_admin_key)', [$key, $companyKey, $hash, $employeeKey, $typeKey, $contextType, $contextKey ?: null, $status, $received, $expiry, $notes ?: null, $checksum, $adminKey, $adminKey], 'Employee Document Requirement save');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_employee_document_requirement', 'employee_document_requirement_key', $hash, $key);
        bx_audit('CREATE', 'project_company_hr_employee_document_requirement', $key, ['company_key' => $companyKey, 'requirement_status' => $status], 'Company administrator saved an Employee Document Requirement.');
        return $saved;
    });
}

function yovel_admin_complete_employee_onboarding(ADOConnection $db, array $company, array $admin, string $onboardingKey, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    if (!yovel_admin_is_uuid($onboardingKey)) { throw new InvalidArgumentException('Employee Onboarding is invalid.'); }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $onboardingKey, $failureInjector): array {
        $row = $db->GetRow('SELECT * FROM project_company_hr_employee_onboarding WHERE company_key_hash = ? AND employee_onboarding_key = ? FOR UPDATE', [$hash, $onboardingKey]);
        if (!is_array($row) || $row === []) { throw new InvalidArgumentException('Employee Onboarding does not belong to this company.'); }
        $openActivities = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_employee_boarding_activity WHERE company_key_hash = ? AND parent_type = 'ONBOARDING' AND parent_key = ? AND activity_status <> 'COMPLETED'", [$hash, $onboardingKey]);
        $openDocuments = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_employee_document_requirement WHERE company_key_hash = ? AND employee_key = ? AND context_type = 'ONBOARDING' AND context_key = ? AND requirement_status NOT IN ('SUBMITTED','VERIFIED')", [$hash, $row['employee_key'], $onboardingKey]);
        if ($openActivities > 0 || $openDocuments > 0) { throw new RuntimeException('Complete all onboarding activities and document requirements first.'); }
        yovel_admin_db_execute($db, "UPDATE project_company_hr_employee_onboarding SET onboarding_status = 'COMPLETED', completed_at = NOW(), updated_by_admin_key = ? WHERE company_key_hash = ? AND employee_onboarding_key = ?", [$adminKey, $hash, $onboardingKey], 'Employee Onboarding completion');
        yovel_admin_db_execute($db, "UPDATE project_company_hr_employee SET employee_status = 'ACTIVE', updated_by_admin_key = ? WHERE company_key_hash = ? AND employee_key = ?", [$adminKey, $hash, $row['employee_key']], 'Employee status onboarding sync');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_employee_onboarding', 'employee_onboarding_key', $hash, $onboardingKey);
        bx_audit('UPDATE', 'project_company_hr_employee_onboarding', $onboardingKey, ['company_key' => $companyKey, 'onboarding_status' => 'COMPLETED'], 'Company administrator completed Employee Onboarding.');
        return $saved;
    });
}

function yovel_admin_persist_employee_separation(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $hash, $adminKey] = $scope;
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $employeeKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee', 'employee_key', $input['employee_key'] ?? '', 'Employee', true);
        $templateKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_boarding_template', 'boarding_template_key', $input['boarding_template_key'] ?? '', 'Separation Template');
        if ($templateKey !== '' && (string) $db->GetOne('SELECT template_type FROM project_company_hr_boarding_template WHERE company_key_hash = ? AND boarding_template_key = ?', [$hash, $templateKey]) !== 'SEPARATION') { throw new InvalidArgumentException('The selected template is not a separation template.'); }
        $resignation = yovel_admin_hr_onboarding_date($input['resignation_date'] ?? date('Y-m-d'), 'Resignation date');
        $relieving = yovel_admin_hr_onboarding_date($input['relieving_date'] ?? '', 'Relieving date');
        if ($relieving < $resignation) { throw new InvalidArgumentException('Relieving date cannot be before resignation date.'); }
        $reason = yovel_admin_hr_leave_text($input['reason'] ?? '', 'Reason', 8000, false);
        $status = yovel_admin_status((string) ($input['separation_status'] ?? 'DRAFT'), ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'], 'DRAFT');
        $key = trim((string) ($input['employee_separation_key'] ?? '')) ?: bx_uuid();
        $checksum = yovel_admin_hr_leave_checksum(compact('employeeKey', 'templateKey', 'resignation', 'relieving', 'reason', 'status'));
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_employee_separation (employee_separation_key, company_key, company_key_hash, employee_key, boarding_template_key, resignation_date, relieving_date, reason, separation_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE relieving_date=VALUES(relieving_date), reason=VALUES(reason), separation_status=VALUES(separation_status), updated_by_admin_key=VALUES(updated_by_admin_key)', [$key, $companyKey, $hash, $employeeKey, $templateKey ?: null, $resignation, $relieving, $reason ?: null, $status, $checksum, $adminKey, $adminKey], 'Employee Separation save');
        if ($templateKey !== '' && (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_employee_boarding_activity WHERE company_key_hash = ? AND parent_key = ?', [$hash, $key]) === 0) { yovel_admin_hr_clone_boarding_template_activities($db, $scope, $templateKey, 'SEPARATION', $key, $employeeKey, $relieving); }
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_employee_separation', 'employee_separation_key', $hash, $key);
        bx_audit('CREATE', 'project_company_hr_employee_separation', $key, ['company_key' => $companyKey, 'separation_status' => $status], 'Company administrator saved Employee Separation.');
        return $saved;
    });
}

function yovel_admin_transition_employee_separation(ADOConnection $db, array $company, array $admin, string $separationKey, string $status, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $hash, $adminKey] = $scope;
    $status = yovel_admin_status($status, ['APPROVED', 'REJECTED', 'CANCELLED'], 'APPROVED');
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $hash, $adminKey, $separationKey, $status, $failureInjector): array {
        $row = $db->GetRow('SELECT * FROM project_company_hr_employee_separation WHERE company_key_hash = ? AND employee_separation_key = ? FOR UPDATE', [$hash, $separationKey]);
        if (!is_array($row) || $row === []) { throw new InvalidArgumentException('Employee Separation does not belong to this company.'); }
        if ($status === 'APPROVED') {
            $statement = $db->GetRow("SELECT * FROM project_company_hr_full_and_final_statement WHERE company_key_hash = ? AND employee_separation_key = ? AND statement_status = 'CLEARED' FOR UPDATE", [$hash, $separationKey]);
            if (!is_array($statement) || $statement === []) { throw new RuntimeException('Full and final clearance is required before separation approval.'); }
            yovel_admin_db_execute($db, "UPDATE project_company_hr_employee SET employee_status = 'SEPARATED', updated_by_admin_key = ? WHERE company_key_hash = ? AND employee_key = ?", [$adminKey, $hash, $row['employee_key']], 'Employee separation status sync');
        }
        yovel_admin_db_execute($db, 'UPDATE project_company_hr_employee_separation SET separation_status = ?, approved_by_admin_key = ?, approved_at = NOW(), updated_by_admin_key = ? WHERE company_key_hash = ? AND employee_separation_key = ?', [$status, $adminKey, $adminKey, $hash, $separationKey], 'Employee Separation transition');
        yovel_admin_hr_leave_notification_intent($db, $scope, $separationKey, 'EMPLOYEE_SEPARATION_' . $status, ['employee_key' => $row['employee_key'], 'separation_status' => $status]);
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_employee_separation', 'employee_separation_key', $hash, $separationKey);
        bx_audit('UPDATE', 'project_company_hr_employee_separation', $separationKey, ['company_key' => $companyKey, 'separation_status' => $status], 'Company administrator transitioned Employee Separation.');
        return $saved;
    });
}

function yovel_admin_schedule_exit_interview(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $hash, $adminKey] = $scope;
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $separationKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee_separation', 'employee_separation_key', $input['employee_separation_key'] ?? '', 'Employee Separation', true);
        $separation = $db->GetRow('SELECT * FROM project_company_hr_employee_separation WHERE company_key_hash = ? AND employee_separation_key = ? FOR UPDATE', [$hash, $separationKey]);
        $interviewer = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee', 'employee_key', $input['interviewer_employee_key'] ?? '', 'Interviewer', true);
        [$scheduledAt, $timezone] = yovel_admin_hr_recruitment_datetime((string) ($input['scheduled_at'] ?? ''), (string) ($input['timezone_name'] ?? 'Asia/Manila'));
        $questionnaire = trim((string) ($input['questionnaire_json'] ?? '{}')) ?: '{}';
        json_decode($questionnaire, true, 512, JSON_THROW_ON_ERROR);
        $key = trim((string) ($input['exit_interview_key'] ?? '')) ?: bx_uuid();
        $status = yovel_admin_status((string) ($input['exit_interview_status'] ?? 'SCHEDULED'), ['SCHEDULED', 'COMPLETED', 'CANCELLED'], 'SCHEDULED');
        $employeeKey = (string) $separation['employee_key'];
        $checksum = yovel_admin_hr_leave_checksum(compact('separationKey', 'employeeKey', 'interviewer', 'scheduledAt', 'timezone', 'questionnaire', 'status'));
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_exit_interview (exit_interview_key, company_key, company_key_hash, employee_separation_key, employee_key, interviewer_employee_key, scheduled_at, timezone_name, questionnaire_json, exit_interview_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE scheduled_at=VALUES(scheduled_at), timezone_name=VALUES(timezone_name), questionnaire_json=VALUES(questionnaire_json), exit_interview_status=VALUES(exit_interview_status), updated_by_admin_key=VALUES(updated_by_admin_key)', [$key, $companyKey, $hash, $separationKey, $employeeKey, $interviewer, $scheduledAt, $timezone, $questionnaire, $status, $checksum, $adminKey, $adminKey], 'Exit Interview save');
        yovel_admin_hr_leave_notification_intent($db, $scope, $key, 'EXIT_INTERVIEW_SCHEDULED', ['employee_separation_key' => $separationKey, 'scheduled_at' => $scheduledAt]);
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_exit_interview', 'exit_interview_key', $hash, $key);
        bx_audit('CREATE', 'project_company_hr_exit_interview', $key, ['company_key' => $companyKey, 'exit_interview_status' => $status], 'Company administrator scheduled an Exit Interview.');
        return $saved;
    });
}

function yovel_admin_transition_exit_interview(ADOConnection $db, array $company, array $admin, string $exitInterviewKey, string $status, array $result = [], ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $status = yovel_admin_status($status, ['COMPLETED', 'CANCELLED'], 'COMPLETED');
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $exitInterviewKey, $status, $result, $failureInjector): array {
        $row = $db->GetRow('SELECT * FROM project_company_hr_exit_interview WHERE company_key_hash = ? AND exit_interview_key = ? FOR UPDATE', [$hash, $exitInterviewKey]);
        if (!is_array($row) || $row === []) { throw new InvalidArgumentException('Exit Interview does not belong to this company.'); }
        $resultJson = yovel_admin_hr_json($result);
        yovel_admin_db_execute($db, 'UPDATE project_company_hr_exit_interview SET exit_interview_status = ?, result_json = ?, completed_at = IF(? = ?, NOW(), completed_at), updated_by_admin_key = ? WHERE company_key_hash = ? AND exit_interview_key = ?', [$status, $resultJson, $status, 'COMPLETED', $adminKey, $hash, $exitInterviewKey], 'Exit Interview transition');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_exit_interview', 'exit_interview_key', $hash, $exitInterviewKey);
        bx_audit('UPDATE', 'project_company_hr_exit_interview', $exitInterviewKey, ['company_key' => $companyKey, 'exit_interview_status' => $status], 'Company administrator transitioned an Exit Interview.');
        return $saved;
    });
}

function yovel_admin_persist_full_and_final_statement(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $separationKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee_separation', 'employee_separation_key', $input['employee_separation_key'] ?? '', 'Employee Separation', true);
        $separation = $db->GetRow('SELECT * FROM project_company_hr_employee_separation WHERE company_key_hash = ? AND employee_separation_key = ? FOR UPDATE', [$hash, $separationKey]);
        if (!is_array($separation) || $separation === []) { throw new InvalidArgumentException('Employee Separation does not belong to this company.'); }
        $assetStatus = yovel_admin_status((string) ($input['asset_clearance_status'] ?? 'PENDING'), ['PENDING', 'CLEARED', 'BLOCKED'], 'PENDING');
        $outstandingStatus = yovel_admin_status((string) ($input['outstanding_status'] ?? 'PENDING'), ['PENDING', 'CLEARED', 'BLOCKED'], 'PENDING');
        $statementStatus = yovel_admin_status((string) ($input['statement_status'] ?? 'DRAFT'), ['DRAFT', 'CLEARED', 'BLOCKED', 'CANCELLED'], 'DRAFT');
        if (($assetStatus !== 'CLEARED' || $outstandingStatus !== 'CLEARED') && $statementStatus === 'CLEARED') { throw new InvalidArgumentException('Full and final cannot be cleared while blockers remain.'); }
        $existing = $db->GetRow('SELECT * FROM project_company_hr_full_and_final_statement WHERE company_key_hash = ? AND employee_separation_key = ? FOR UPDATE', [$hash, $separationKey]);
        $key = is_array($existing) && $existing !== [] ? (string) $existing['full_and_final_statement_key'] : bx_uuid();
        $employeeKey = (string) $separation['employee_key'];
        $checksum = yovel_admin_hr_leave_checksum(compact('separationKey', 'employeeKey', 'assetStatus', 'outstandingStatus', 'statementStatus'));
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_full_and_final_statement (full_and_final_statement_key, company_key, company_key_hash, employee_separation_key, employee_key, asset_clearance_status, outstanding_status, statement_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE asset_clearance_status=VALUES(asset_clearance_status), outstanding_status=VALUES(outstanding_status), statement_status=VALUES(statement_status), immutable_checksum=VALUES(immutable_checksum), updated_by_admin_key=VALUES(updated_by_admin_key)', [$key, $companyKey, $hash, $separationKey, $employeeKey, $assetStatus, $outstandingStatus, $statementStatus, $checksum, $adminKey, $adminKey], 'Full and Final Statement save');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_full_and_final_asset WHERE company_key_hash = ? AND full_and_final_statement_key = ?', [$hash, $key], 'Full and Final assets replace');
        foreach ((array) ($input['assets'] ?? []) as $asset) {
            if (!is_array($asset)) { continue; }
            yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_full_and_final_asset (full_and_final_asset_key, company_key, company_key_hash, full_and_final_statement_key, asset_reference, clearance_status, remarks, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $hash, $key, yovel_admin_hr_leave_text($asset['asset_reference'] ?? '', 'Asset reference', 180), yovel_admin_status((string) ($asset['clearance_status'] ?? 'PENDING'), ['PENDING', 'CLEARED', 'BLOCKED'], 'PENDING'), yovel_admin_hr_leave_text($asset['remarks'] ?? '', 'Asset remarks', 8000, false) ?: null, $adminKey], 'Full and Final Asset save');
        }
        yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_full_and_final_outstanding_statement WHERE company_key_hash = ? AND full_and_final_statement_key = ?', [$hash, $key], 'Full and Final outstandings replace');
        foreach ((array) ($input['outstandings'] ?? []) as $outstanding) {
            if (!is_array($outstanding)) { continue; }
            yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_full_and_final_outstanding_statement (full_and_final_outstanding_statement_key, company_key, company_key_hash, full_and_final_statement_key, source_reference, amount, clearance_status, remarks, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $hash, $key, yovel_admin_hr_leave_text($outstanding['source_reference'] ?? '', 'Outstanding source', 180), number_format((float) ($outstanding['amount'] ?? 0), 2, '.', ''), yovel_admin_status((string) ($outstanding['clearance_status'] ?? 'PENDING'), ['PENDING', 'CLEARED', 'BLOCKED'], 'PENDING'), yovel_admin_hr_leave_text($outstanding['remarks'] ?? '', 'Outstanding remarks', 8000, false) ?: null, $adminKey], 'Full and Final Outstanding save');
        }
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_onboarding_readback($db, 'project_company_hr_full_and_final_statement', 'full_and_final_statement_key', $hash, $key);
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_full_and_final_statement', $key, ['company_key' => $companyKey, 'statement_status' => $statementStatus], 'Company administrator saved a Full and Final Statement.');
        return $saved;
    });
}

function yovel_admin_create_employee_onboarding_from_offer(ADOConnection $db, array $company, array $admin, array $input): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $offerKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_offer', 'job_offer_key', $input['job_offer_key'] ?? '', 'Job Offer', true);
    $offer = $db->GetRow('SELECT offer.*, applicant.applicant_name, applicant.email_address FROM project_company_hr_job_offer offer INNER JOIN project_company_hr_job_applicant applicant ON applicant.company_key_hash = offer.company_key_hash AND applicant.job_applicant_key = offer.job_applicant_key WHERE offer.company_key_hash = ? AND offer.job_offer_key = ?', [$hash, $offerKey]);
    if (!is_array($offer) || $offer === [] || (string) $offer['offer_status'] !== 'ACCEPTED') { throw new RuntimeException('Only an accepted Job Offer can enter onboarding.'); }
    $employeeKey = trim((string) ($input['employee_key'] ?? ''));
    if ($employeeKey === '') {
        $employeeKey = bx_uuid();
        $name = yovel_admin_hr_leave_text($offer['applicant_name'] ?? 'New Employee', 'Applicant name', 180);
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_employee (employee_key, company_key, company_key_hash, employee_code, first_name, employee_name, employee_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, 'DRAFT', ?, ?)", [$employeeKey, $companyKey, $hash, 'OFFER-' . substr(str_replace('-', '', $offerKey), 0, 12), $name, $name, $adminKey, $adminKey], 'Offer onboarding employee create');
    } else {
        $employeeKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee', 'employee_key', $employeeKey, 'Employee', true);
    }
    return yovel_admin_start_employee_onboarding($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'job_offer_key' => $offerKey,
        'source_reference' => 'JOB-OFFER-' . $offerKey,
        'start_date' => (string) ($input['start_date'] ?? date('Y-m-d')),
        'expected_completion_date' => (string) ($input['expected_completion_date'] ?? ''),
        'onboarding_status' => 'IN_PROGRESS',
    ]);
}

function yovel_admin_hr_onboarding_data(array $company): array
{
    yovel_admin_hr_schema();
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) { throw new InvalidArgumentException('Onboarding company scope is invalid.'); }
    $db = bx_db();
    return [
        'templates' => $db->GetAll('SELECT * FROM project_company_hr_boarding_template WHERE company_key_hash = ? ORDER BY template_type, template_name', [$hash]) ?: [],
        'onboardings' => $db->GetAll("SELECT onboarding.*, employee.employee_name FROM project_company_hr_employee_onboarding onboarding INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = onboarding.company_key_hash AND employee.employee_key = onboarding.employee_key WHERE onboarding.company_key_hash = ? ORDER BY onboarding.created_at DESC LIMIT 300", [$hash]) ?: [],
        'separations' => $db->GetAll("SELECT separation.*, employee.employee_name FROM project_company_hr_employee_separation separation INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = separation.company_key_hash AND employee.employee_key = separation.employee_key WHERE separation.company_key_hash = ? ORDER BY separation.created_at DESC LIMIT 300", [$hash]) ?: [],
        'activities' => $db->GetAll('SELECT * FROM project_company_hr_employee_boarding_activity WHERE company_key_hash = ? ORDER BY due_date IS NULL, due_date ASC, sort_order ASC LIMIT 400', [$hash]) ?: [],
        'documents' => $db->GetAll("SELECT requirement.*, employee.employee_name, document_type.document_type_name FROM project_company_hr_employee_document_requirement requirement INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = requirement.company_key_hash AND employee.employee_key = requirement.employee_key INNER JOIN project_company_hr_identification_document_type document_type ON document_type.company_key_hash = requirement.company_key_hash AND document_type.identification_document_type_key = requirement.identification_document_type_key WHERE requirement.company_key_hash = ? ORDER BY requirement.created_at DESC LIMIT 300", [$hash]) ?: [],
        'documentTypes' => $db->GetAll("SELECT * FROM project_company_hr_identification_document_type WHERE company_key_hash = ? AND document_type_status = 'ACTIVE' ORDER BY document_type_name", [$hash]) ?: [],
        'exitInterviews' => $db->GetAll('SELECT * FROM project_company_hr_exit_interview WHERE company_key_hash = ? ORDER BY scheduled_at DESC LIMIT 300', [$hash]) ?: [],
        'fullFinalStatements' => $db->GetAll('SELECT * FROM project_company_hr_full_and_final_statement WHERE company_key_hash = ? ORDER BY created_at DESC LIMIT 300', [$hash]) ?: [],
        'formTargets' => yovel_admin_hr_onboarding_form_targets(),
        'dependencies' => [
            'attachments' => ['available' => false, 'contract' => 'orchestration.attachments.v1'],
            'assets' => ['available' => function_exists('yovel_admin_assets_clearance_read_contract'), 'contract' => 'orchestration.assets-clearance.v1'],
            'finance' => ['available' => function_exists('yovel_admin_finance_settlement_read_contract'), 'contract' => 'orchestration.finance-settlement.v1'],
        ],
    ];
}

function yovel_admin_hr_handle_onboarding_post(array $company, array $admin, string $action, array $input): array
{
    $db = bx_db();
    $saved = match ($action) {
        'hr_onboarding_save_document_type' => yovel_admin_persist_identification_document_type($db, $company, $admin, $input),
        'hr_onboarding_save_template' => yovel_admin_persist_boarding_template($db, $company, $admin, (string) ($input['template_type'] ?? 'ONBOARDING'), array_merge($input, ['activities' => yovel_admin_hr_onboarding_json_rows($input, 'activities_json')])),
        'hr_onboarding_start' => yovel_admin_start_employee_onboarding($db, $company, $admin, $input),
        'hr_onboarding_complete_activity' => yovel_admin_complete_boarding_activity($db, $company, $admin, (string) ($input['employee_boarding_activity_key'] ?? ''), (string) ($input['completion_notes'] ?? '')),
        'hr_onboarding_save_document' => yovel_admin_persist_employee_document_requirement($db, $company, $admin, $input),
        'hr_onboarding_complete' => yovel_admin_complete_employee_onboarding($db, $company, $admin, (string) ($input['employee_onboarding_key'] ?? '')),
        'hr_onboarding_save_separation' => yovel_admin_persist_employee_separation($db, $company, $admin, $input),
        'hr_onboarding_transition_separation' => yovel_admin_transition_employee_separation($db, $company, $admin, (string) ($input['employee_separation_key'] ?? ''), (string) ($input['separation_status'] ?? '')),
        'hr_onboarding_schedule_exit' => yovel_admin_schedule_exit_interview($db, $company, $admin, $input),
        'hr_onboarding_transition_exit' => yovel_admin_transition_exit_interview($db, $company, $admin, (string) ($input['exit_interview_key'] ?? ''), (string) ($input['exit_interview_status'] ?? ''), ['summary' => (string) ($input['summary'] ?? '')]),
        'hr_onboarding_save_final' => yovel_admin_persist_full_and_final_statement($db, $company, $admin, array_merge($input, ['assets' => yovel_admin_hr_onboarding_json_rows($input, 'assets_json'), 'outstandings' => yovel_admin_hr_onboarding_json_rows($input, 'outstandings_json')])),
        default => throw new InvalidArgumentException('This Onboarding action is not available.'),
    };
    $recordKey = '';
    foreach (['full_and_final_statement_key', 'exit_interview_key', 'employee_separation_key', 'employee_onboarding_key', 'employee_document_requirement_key', 'boarding_template_key', 'identification_document_type_key', 'employee_boarding_activity_key'] as $key) {
        if (!empty($saved[$key])) { $recordKey = (string) $saved[$key]; break; }
    }
    return ['message' => 'Onboarding record saved.', 'section' => 'onboarding', 'query' => ['record' => $recordKey, 'onboarding_mode' => (string) ($input['onboarding_mode'] ?? 'onboarding')]];
}
