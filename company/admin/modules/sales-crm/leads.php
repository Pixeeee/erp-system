<?php
declare(strict_types=1);

function yovel_admin_sales_crm_leads_schema(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_sales_market_segment (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            market_segment_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            segment_code VARCHAR(80) NOT NULL,
            segment_name VARCHAR(180) NOT NULL,
            segment_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_market_segment_code (company_key_hash, segment_code),
            INDEX idx_project_company_sales_market_segment_status (company_key_hash, segment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_industry_type (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            industry_type_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            industry_code VARCHAR(80) NOT NULL,
            industry_name VARCHAR(180) NOT NULL,
            industry_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_industry_type_code (company_key_hash, industry_code),
            INDEX idx_project_company_sales_industry_type_status (company_key_hash, industry_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_prospect (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            prospect_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            prospect_code VARCHAR(80) NOT NULL,
            prospect_name VARCHAR(200) NOT NULL,
            prospect_status ENUM('OPEN','QUALIFIED','CONVERTED','INACTIVE','DELETED') NOT NULL DEFAULT 'OPEN',
            prospect_version INT UNSIGNED NOT NULL DEFAULT 1,
            market_segment_key CHAR(36) NULL,
            industry_type_key CHAR(36) NULL,
            assigned_admin_key CHAR(36) NULL,
            website VARCHAR(220) NOT NULL DEFAULT '',
            notes TEXT NULL,
            idempotency_key CHAR(36) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_prospect_code (company_key_hash, prospect_code),
            UNIQUE KEY uq_project_company_sales_prospect_idempotency (company_key_hash, idempotency_key),
            INDEX idx_project_company_sales_prospect_status (company_key_hash, prospect_status),
            INDEX idx_project_company_sales_prospect_owner (company_key_hash, assigned_admin_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_prospect_lead (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            prospect_lead_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            prospect_key CHAR(36) NOT NULL,
            lead_key CHAR(36) NOT NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_prospect_lead (company_key_hash, prospect_key, lead_key),
            INDEX idx_project_company_sales_prospect_lead_lead (company_key_hash, lead_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_prospect_opportunity (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            prospect_opportunity_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            prospect_key CHAR(36) NOT NULL,
            opportunity_key CHAR(36) NOT NULL,
            conversion_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_prospect_opportunity (company_key_hash, prospect_key, opportunity_key),
            INDEX idx_project_company_sales_prospect_opportunity_conversion (company_key_hash, conversion_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_appointment_booking_settings (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            settings_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            settings_version INT UNSIGNED NOT NULL DEFAULT 1,
            timezone VARCHAR(80) NOT NULL DEFAULT 'UTC',
            minimum_notice_minutes INT UNSIGNED NOT NULL DEFAULT 0,
            booking_horizon_days INT UNSIGNED NOT NULL DEFAULT 30,
            appointment_duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_appointment_settings_company (company_key_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_appointment_booking_slot (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_slot_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            weekday_number TINYINT UNSIGNED NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            slot_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            idempotency_key CHAR(36) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_appointment_slot (company_key_hash, weekday_number, start_time, end_time),
            UNIQUE KEY uq_project_company_sales_appointment_slot_idempotency (company_key_hash, idempotency_key),
            INDEX idx_project_company_sales_appointment_slot_status (company_key_hash, slot_status, weekday_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_appointment_availability (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            availability_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            availability_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            availability_status ENUM('AVAILABLE','BLOCKED','DELETED') NOT NULL DEFAULT 'AVAILABLE',
            reason VARCHAR(255) NOT NULL DEFAULT '',
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_appointment_availability (company_key_hash, availability_date, start_time, end_time),
            INDEX idx_project_company_sales_appointment_availability_date (company_key_hash, availability_date, availability_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_appointment (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            appointment_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            appointment_code VARCHAR(80) NOT NULL,
            appointment_status ENUM('SCHEDULED','COMPLETED','CANCELLED','NO_SHOW','DELETED') NOT NULL DEFAULT 'SCHEDULED',
            appointment_version INT UNSIGNED NOT NULL DEFAULT 1,
            lead_key CHAR(36) NULL,
            prospect_key CHAR(36) NULL,
            assigned_admin_key CHAR(36) NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            contact_name VARCHAR(200) NOT NULL DEFAULT '',
            contact_email VARCHAR(180) NOT NULL DEFAULT '',
            contact_phone VARCHAR(80) NOT NULL DEFAULT '',
            notes TEXT NULL,
            idempotency_key CHAR(36) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_appointment_code (company_key_hash, appointment_code),
            UNIQUE KEY uq_project_company_sales_appointment_idempotency (company_key_hash, idempotency_key),
            INDEX idx_project_company_sales_appointment_schedule (company_key_hash, appointment_status, starts_at, ends_at),
            INDEX idx_project_company_sales_appointment_lead (company_key_hash, lead_key),
            INDEX idx_project_company_sales_appointment_prospect (company_key_hash, prospect_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_crm_note (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            crm_note_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            note_code VARCHAR(80) NOT NULL,
            subject_type ENUM('LEAD','PROSPECT') NOT NULL,
            subject_key CHAR(36) NOT NULL,
            note_text TEXT NOT NULL,
            note_status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            idempotency_key CHAR(36) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_crm_note_code (company_key_hash, note_code),
            UNIQUE KEY uq_project_company_sales_crm_note_idempotency (company_key_hash, idempotency_key),
            INDEX idx_project_company_sales_crm_note_subject (company_key_hash, subject_type, subject_key, note_status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_lead_communication (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            communication_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            lead_key CHAR(36) NOT NULL,
            communication_type ENUM('ASSIGNMENT','EMAIL','PHONE','MEETING','FOLLOW_UP','IMPORT','CONVERSION') NOT NULL,
            direction ENUM('INTERNAL','INBOUND','OUTBOUND') NOT NULL DEFAULT 'INTERNAL',
            summary VARCHAR(255) NOT NULL,
            reference_type VARCHAR(80) NOT NULL DEFAULT '',
            reference_key CHAR(36) NULL,
            occurred_at DATETIME NOT NULL,
            idempotency_key CHAR(36) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_lead_communication_idempotency (company_key_hash, idempotency_key),
            INDEX idx_project_company_sales_lead_communication_timeline (company_key_hash, lead_key, occurred_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_lead_conversion (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            conversion_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            lead_key CHAR(36) NOT NULL,
            target_type ENUM('CUSTOMER','OPPORTUNITY','BOTH') NOT NULL,
            conversion_status ENUM('PENDING','PROCESSING','COMPLETED','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
            dependency_status ENUM('AVAILABLE','UNAVAILABLE_DEPENDENCY','ERROR') NOT NULL DEFAULT 'UNAVAILABLE_DEPENDENCY',
            customer_key CHAR(36) NULL,
            opportunity_key CHAR(36) NULL,
            idempotency_key CHAR(36) NOT NULL,
            error_message VARCHAR(500) NOT NULL DEFAULT '',
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_lead_conversion_idempotency (company_key_hash, idempotency_key),
            INDEX idx_project_company_sales_lead_conversion_lead (company_key_hash, lead_key, conversion_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_integration_outbox (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            outbox_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            aggregate_type VARCHAR(80) NOT NULL,
            aggregate_key CHAR(36) NOT NULL,
            event_type VARCHAR(120) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            outbox_status ENUM('PENDING','PROCESSING','PROCESSED','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
            idempotency_key CHAR(36) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(500) NOT NULL DEFAULT '',
            available_at DATETIME NOT NULL,
            processed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_outbox_idempotency (company_key_hash, idempotency_key),
            INDEX idx_project_company_sales_outbox_due (company_key_hash, outbox_status, available_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Sales / CRM SC-03 schema update');
    }
    bx_add_column_if_missing('project_company_sales_lead', 'lead_version', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER lead_status');
    bx_add_column_if_missing('project_company_sales_lead', 'assigned_admin_key', 'CHAR(36) NULL AFTER salesperson_key');
    bx_add_column_if_missing('project_company_sales_lead', 'first_response_at', 'DATETIME NULL AFTER next_contact_date');
    bx_add_column_if_missing('project_company_sales_lead', 'qualified_at', 'DATETIME NULL AFTER first_response_at');
    bx_add_column_if_missing('project_company_sales_lead', 'converted_at', 'DATETIME NULL AFTER qualified_at');
    bx_add_column_if_missing('project_company_sales_lead', 'address_line', "VARCHAR(255) NOT NULL DEFAULT '' AFTER converted_at");
    bx_add_column_if_missing('project_company_sales_lead', 'city', "VARCHAR(120) NOT NULL DEFAULT '' AFTER address_line");
    bx_add_column_if_missing('project_company_sales_lead', 'country', "VARCHAR(120) NOT NULL DEFAULT '' AFTER city");
    bx_add_index_if_missing('project_company_sales_lead', 'idx_project_company_sales_lead_assigned', 'INDEX idx_project_company_sales_lead_assigned (company_key_hash, assigned_admin_key, lead_status)');
    bx_add_index_if_missing('project_company_sales_lead', 'idx_project_company_sales_lead_conversion', 'INDEX idx_project_company_sales_lead_conversion (company_key_hash, converted_at, created_at)');
    $ready = true;
}

function yovel_admin_sales_sc03_code(string $value, string $label): string
{
    $value = yovel_admin_code($value);
    if ($value === '' || preg_match('/^[A-Z0-9_.-]{2,80}$/', $value) !== 1) {
        throw new InvalidArgumentException($label . ' must use 2-80 uppercase letters, numbers, underscores, periods, or hyphens.');
    }
    return $value;
}

function yovel_admin_sales_sc03_idempotency(mixed $value, bool $required = false): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        if ($required) {
            throw new InvalidArgumentException('An idempotency key is required.');
        }
        return null;
    }
    if (!yovel_admin_is_uuid($value)) {
        throw new InvalidArgumentException('The idempotency key is invalid.');
    }
    return $value;
}

function yovel_admin_sales_sc03_related_key(string $table, string $keyColumn, array $company, mixed $value, string $label): ?string
{
    $allowed = [
        'project_company_sales_lead' => 'lead_key',
        'project_company_sales_prospect' => 'prospect_key',
        'project_company_sales_market_segment' => 'market_segment_key',
        'project_company_sales_industry_type' => 'industry_type_key',
        'project_company_admin' => 'admin_key',
    ];
    if (($allowed[$table] ?? '') !== $keyColumn) {
        throw new LogicException('Unsupported SC-03 related-record lookup.');
    }
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (!yovel_admin_is_uuid($value)) {
        throw new InvalidArgumentException($label . ' key is invalid.');
    }
    $statusClause = $table === 'project_company_admin' ? " AND admin_status = 'ACTIVE'" : '';
    $exists = (int) bx_db()->GetOne(
        "SELECT COUNT(*) FROM {$table} WHERE company_key_hash = ? AND {$keyColumn} = ?{$statusClause}",
        [(string) $company['company_key_hash'], $value]
    );
    if ($exists !== 1) {
        throw new InvalidArgumentException($label . ' was not found for this company.');
    }
    return $value;
}

function yovel_admin_sales_sc03_audit_payload(array $company, array $admin, array $values): array
{
    return array_merge([
        'company_key' => (string) $company['company_key'],
        'company_key_hash' => (string) $company['company_key_hash'],
        'company_name' => (string) $company['company_name'],
        'admin_key' => (string) $admin['admin_key'],
    ], $values);
}

function yovel_admin_sales_reference_save(array $company, array $admin, string $type, array $input): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $definitions = [
        'market-segment' => ['table' => 'project_company_sales_market_segment', 'key' => 'market_segment_key', 'code' => 'segment_code', 'name' => 'segment_name', 'status' => 'segment_status'],
        'industry-type' => ['table' => 'project_company_sales_industry_type', 'key' => 'industry_type_key', 'code' => 'industry_code', 'name' => 'industry_name', 'status' => 'industry_status'],
    ];
    if (!isset($definitions[$type])) {
        throw new InvalidArgumentException('Unsupported Sales reference type.');
    }
    $definition = $definitions[$type];
    $code = yovel_admin_sales_sc03_code((string) ($input['reference_code'] ?? ''), 'Reference code');
    $name = trim((string) ($input['reference_name'] ?? ''));
    $status = yovel_admin_status((string) ($input['reference_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED'], '');
    if ($name === '' || strlen($name) > 180 || $status === '') {
        throw new InvalidArgumentException('A valid reference name and status are required.');
    }
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $db->BeginTrans();
    try {
        $existing = $db->GetRow(
            "SELECT * FROM {$definition['table']} WHERE company_key_hash = ? AND {$definition['code']} = ? FOR UPDATE",
            [$hash, $code]
        );
        $key = $existing ? (string) $existing[$definition['key']] : bx_uuid();
        yovel_admin_db_execute(
            $db,
            "INSERT INTO {$definition['table']} ({$definition['key']}, company_key, company_key_hash, {$definition['code']}, {$definition['name']}, {$definition['status']}, created_by_admin_key, updated_by_admin_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE {$definition['name']} = VALUES({$definition['name']}), {$definition['status']} = VALUES({$definition['status']}), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$key, (string) $company['company_key'], $hash, $code, $name, $status, (string) $admin['admin_key'], (string) $admin['admin_key']],
            'Sales reference save'
        );
        $saved = $db->GetRow(
            "SELECT {$definition['key']} AS reference_key, {$definition['code']} AS reference_code, {$definition['name']} AS reference_name, {$definition['status']} AS reference_status FROM {$definition['table']} WHERE company_key_hash = ? AND {$definition['key']} = ?",
            [$hash, $key]
        );
        foreach (['reference_key' => $key, 'reference_code' => $code, 'reference_name' => $name, 'reference_status' => $status] as $column => $expected) {
            if (!is_array($saved) || (string) ($saved[$column] ?? '') !== (string) $expected) {
                throw new RuntimeException('Sales reference read-back verification failed for ' . $column . '.');
            }
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', $definition['table'], $key, yovel_admin_sales_sc03_audit_payload($company, $admin, [
            'reference_type' => $type, 'reference_code' => $code, 'reference_name' => $name, 'reference_status' => $status,
        ]), 'Company administrator saved a Sales reference.');
        $db->CommitTrans();
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_lead_assign(array $company, array $admin, array $input): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $leadKey = yovel_admin_sales_sc03_related_key('project_company_sales_lead', 'lead_key', $company, $input['lead_key'] ?? '', 'Lead');
    $assignedAdminKey = yovel_admin_sales_sc03_related_key('project_company_admin', 'admin_key', $company, $input['assigned_admin_key'] ?? '', 'Assigned administrator');
    $nextContactDate = yovel_admin_optional_date((string) ($input['next_contact_date'] ?? ''), 'Next contact date');
    $expectedVersion = filter_var($input['expected_version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idempotencyKey = yovel_admin_sales_sc03_idempotency($input['idempotency_key'] ?? null, true);
    if ($expectedVersion === false) {
        throw new InvalidArgumentException('A valid Lead version is required.');
    }
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $db->BeginTrans();
    try {
        $lead = $db->GetRow('SELECT * FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ? FOR UPDATE', [$hash, $leadKey]);
        if (!$lead) {
            throw new RuntimeException('Lead assignment changed. Reload and try again.');
        }
        $existingEvent = $db->GetRow('SELECT communication_key FROM project_company_sales_lead_communication WHERE company_key_hash = ? AND idempotency_key = ? LIMIT 1', [$hash, $idempotencyKey]);
        if ($existingEvent) {
            if ((string) ($lead['assigned_admin_key'] ?? '') !== (string) $assignedAdminKey || (string) ($lead['next_contact_date'] ?? '') !== $nextContactDate) {
                throw new RuntimeException('Lead assignment idempotency key belongs to another state.');
            }
            $db->CommitTrans();
            return [
                'lead_key' => (string) $lead['lead_key'],
                'assigned_admin_key' => $lead['assigned_admin_key'],
                'next_contact_date' => $lead['next_contact_date'],
                'lead_version' => $lead['lead_version'],
                'first_response_at' => $lead['first_response_at'],
            ];
        }
        if ((int) $lead['lead_version'] !== (int) $expectedVersion) {
            throw new RuntimeException('Lead assignment changed. Reload and try again.');
        }
        if (!$existingEvent) {
            $nextVersion = (int) $lead['lead_version'] + 1;
            yovel_admin_db_execute(
                $db,
                'UPDATE project_company_sales_lead SET assigned_admin_key = ?, next_contact_date = ?, first_response_at = COALESCE(first_response_at, UTC_TIMESTAMP()), lead_version = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND lead_key = ?',
                [$assignedAdminKey, $nextContactDate !== '' ? $nextContactDate : null, $nextVersion, (string) $admin['admin_key'], $hash, $leadKey],
                'Sales Lead assignment save'
            );
            $communicationKey = bx_uuid();
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_sales_lead_communication (communication_key, company_key, company_key_hash, lead_key, communication_type, direction, summary, reference_type, reference_key, occurred_at, idempotency_key, created_by_admin_key)
                 VALUES (?, ?, ?, ?, 'ASSIGNMENT', 'INTERNAL', ?, 'project_company_admin', ?, UTC_TIMESTAMP(), ?, ?)",
                [$communicationKey, (string) $company['company_key'], $hash, $leadKey, 'Lead assigned for follow-up.', $assignedAdminKey, $idempotencyKey, (string) $admin['admin_key']],
                'Sales Lead assignment timeline save'
            );
            bx_audit('ASSIGN', 'project_company_sales_lead', $leadKey, yovel_admin_sales_sc03_audit_payload($company, $admin, [
                'lead_code' => (string) $lead['lead_code'], 'lead_name' => (string) $lead['lead_name'], 'assigned_admin_key' => $assignedAdminKey, 'next_contact_date' => $nextContactDate, 'lead_version' => $nextVersion,
            ]), 'Company administrator assigned a Lead.');
        }
        $saved = $db->GetRow('SELECT lead_key, assigned_admin_key, next_contact_date, lead_version, first_response_at FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ?', [$hash, $leadKey]);
        if (!is_array($saved) || (string) $saved['assigned_admin_key'] !== (string) $assignedAdminKey || (string) ($saved['next_contact_date'] ?? '') !== $nextContactDate) {
            throw new RuntimeException('Lead assignment read-back verification failed.');
        }
        $db->CommitTrans();
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_crm_note_save(array $company, array $admin, array $input): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $subjectType = yovel_admin_status((string) ($input['subject_type'] ?? ''), ['LEAD', 'PROSPECT'], '');
    $subjectKey = trim((string) ($input['subject_key'] ?? ''));
    $noteText = trim((string) ($input['note_text'] ?? ''));
    $noteCode = trim((string) ($input['note_code'] ?? ''));
    $noteCode = $noteCode !== '' ? yovel_admin_sales_sc03_code($noteCode, 'Note code') : 'NOTE-' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 16));
    $idempotencyKey = yovel_admin_sales_sc03_idempotency($input['idempotency_key'] ?? null);
    if ($subjectType === '' || $noteText === '' || strlen($noteText) > 10000) {
        throw new InvalidArgumentException('A valid Note subject and text are required.');
    }
    $subjectTable = $subjectType === 'LEAD' ? 'project_company_sales_lead' : 'project_company_sales_prospect';
    $subjectColumn = $subjectType === 'LEAD' ? 'lead_key' : 'prospect_key';
    yovel_admin_sales_sc03_related_key($subjectTable, $subjectColumn, $company, $subjectKey, ucfirst(strtolower($subjectType)));
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $db->BeginTrans();
    try {
        if ($idempotencyKey !== null) {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_crm_note WHERE company_key_hash = ? AND idempotency_key = ? FOR UPDATE', [$hash, $idempotencyKey]);
            if ($existing) {
                $db->CommitTrans();
                return $existing;
            }
        }
        $noteKey = bx_uuid();
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_sales_crm_note (crm_note_key, company_key, company_key_hash, note_code, subject_type, subject_key, note_text, note_status, idempotency_key, created_by_admin_key, updated_by_admin_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?)",
            [$noteKey, (string) $company['company_key'], $hash, $noteCode, $subjectType, $subjectKey, $noteText, $idempotencyKey, (string) $admin['admin_key'], (string) $admin['admin_key']],
            'Sales CRM Note save'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_sales_crm_note WHERE company_key_hash = ? AND crm_note_key = ?', [$hash, $noteKey]);
        if (!is_array($saved) || (string) $saved['note_code'] !== $noteCode || (string) $saved['subject_type'] !== $subjectType || (string) $saved['subject_key'] !== $subjectKey || (string) $saved['note_text'] !== $noteText) {
            throw new RuntimeException('CRM Note read-back verification failed.');
        }
        bx_audit('CREATE', 'project_company_sales_crm_note', $noteKey, yovel_admin_sales_sc03_audit_payload($company, $admin, [
            'note_code' => $noteCode, 'subject_type' => $subjectType, 'subject_key' => $subjectKey, 'note_text' => $noteText,
        ]), 'Company administrator created a CRM Note.');
        $db->CommitTrans();
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_prospect_save(array $company, array $admin, array $input): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $prospectKey = trim((string) ($input['prospect_key'] ?? ''));
    if ($prospectKey !== '' && !yovel_admin_is_uuid($prospectKey)) {
        throw new InvalidArgumentException('Prospect key is invalid.');
    }
    $code = yovel_admin_sales_sc03_code((string) ($input['prospect_code'] ?? ''), 'Prospect code');
    $name = trim((string) ($input['prospect_name'] ?? ''));
    $status = yovel_admin_status((string) ($input['prospect_status'] ?? 'OPEN'), ['OPEN', 'QUALIFIED', 'CONVERTED', 'INACTIVE', 'DELETED'], '');
    $website = trim((string) ($input['website'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));
    if ($name === '' || strlen($name) > 200 || $status === '' || strlen($notes) > 10000 || ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL))) {
        throw new InvalidArgumentException('Valid Prospect name, status, website, and notes are required.');
    }
    $marketSegmentKey = yovel_admin_sales_sc03_related_key('project_company_sales_market_segment', 'market_segment_key', $company, $input['market_segment_key'] ?? '', 'Market segment');
    $industryTypeKey = yovel_admin_sales_sc03_related_key('project_company_sales_industry_type', 'industry_type_key', $company, $input['industry_type_key'] ?? '', 'Industry type');
    $assignedAdminKey = yovel_admin_sales_sc03_related_key('project_company_admin', 'admin_key', $company, $input['assigned_admin_key'] ?? '', 'Assigned administrator');
    $leadKeys = array_values(array_unique(array_filter(array_map('strval', is_array($input['lead_keys'] ?? null) ? $input['lead_keys'] : []))));
    foreach ($leadKeys as $leadKey) {
        yovel_admin_sales_sc03_related_key('project_company_sales_lead', 'lead_key', $company, $leadKey, 'Prospect Lead');
    }
    $idempotencyKey = yovel_admin_sales_sc03_idempotency($input['idempotency_key'] ?? null);
    $expectedVersion = trim((string) ($input['expected_version'] ?? ''));
    if ($expectedVersion !== '' && filter_var($expectedVersion, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new InvalidArgumentException('Prospect version is invalid.');
    }
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $db->BeginTrans();
    try {
        $existing = null;
        if ($idempotencyKey !== null) {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_prospect WHERE company_key_hash = ? AND idempotency_key = ? FOR UPDATE', [$hash, $idempotencyKey]);
            if ($existing) {
                $savedLeadKeys = $db->GetCol('SELECT lead_key FROM project_company_sales_prospect_lead WHERE company_key_hash = ? AND prospect_key = ? ORDER BY lead_key', [$hash, (string) $existing['prospect_key']]);
                $expectedLeadKeys = $leadKeys;
                sort($expectedLeadKeys);
                $matches = (string) $existing['prospect_code'] === $code
                    && (string) $existing['prospect_name'] === $name
                    && (string) $existing['prospect_status'] === $status
                    && (string) ($existing['market_segment_key'] ?? '') === (string) $marketSegmentKey
                    && (string) ($existing['industry_type_key'] ?? '') === (string) $industryTypeKey
                    && (string) ($existing['assigned_admin_key'] ?? '') === (string) $assignedAdminKey
                    && (string) $existing['website'] === $website
                    && (string) ($existing['notes'] ?? '') === $notes
                    && array_values($savedLeadKeys ?: []) === $expectedLeadKeys;
                if (!$matches) {
                    throw new RuntimeException('Prospect idempotency key belongs to another request.');
                }
                $db->CommitTrans();
                $existing['lead_keys'] = $expectedLeadKeys;
                return $existing;
            }
        }
        if (!$existing && $prospectKey !== '') {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_prospect WHERE company_key_hash = ? AND prospect_key = ? FOR UPDATE', [$hash, $prospectKey]);
            if (!$existing) {
                throw new InvalidArgumentException('Prospect was not found for this company.');
            }
        }
        if (!$existing) {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_prospect WHERE company_key_hash = ? AND prospect_code = ? FOR UPDATE', [$hash, $code]);
        }
        if ($existing && $expectedVersion !== '' && (int) $existing['prospect_version'] !== (int) $expectedVersion) {
            throw new RuntimeException('Prospect changed. Reload and try again.');
        }
        $prospectKey = $existing ? (string) $existing['prospect_key'] : bx_uuid();
        $version = $existing ? (int) $existing['prospect_version'] + 1 : 1;
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_sales_prospect (prospect_key, company_key, company_key_hash, prospect_code, prospect_name, prospect_status, prospect_version, market_segment_key, industry_type_key, assigned_admin_key, website, notes, idempotency_key, created_by_admin_key, updated_by_admin_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE prospect_name = VALUES(prospect_name), prospect_status = VALUES(prospect_status), prospect_version = VALUES(prospect_version), market_segment_key = VALUES(market_segment_key), industry_type_key = VALUES(industry_type_key), assigned_admin_key = VALUES(assigned_admin_key), website = VALUES(website), notes = VALUES(notes), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$prospectKey, (string) $company['company_key'], $hash, $code, $name, $status, $version, $marketSegmentKey, $industryTypeKey, $assignedAdminKey, $website, $notes, $idempotencyKey, (string) $admin['admin_key'], (string) $admin['admin_key']],
            'Sales Prospect save'
        );
        yovel_admin_db_execute($db, 'DELETE FROM project_company_sales_prospect_lead WHERE company_key_hash = ? AND prospect_key = ?', [$hash, $prospectKey], 'Sales Prospect Lead replace');
        foreach ($leadKeys as $leadKey) {
            yovel_admin_db_execute(
                $db,
                'INSERT INTO project_company_sales_prospect_lead (prospect_lead_key, company_key, company_key_hash, prospect_key, lead_key, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?)',
                [bx_uuid(), (string) $company['company_key'], $hash, $prospectKey, $leadKey, (string) $admin['admin_key']],
                'Sales Prospect Lead save'
            );
        }
        $saved = $db->GetRow('SELECT * FROM project_company_sales_prospect WHERE company_key_hash = ? AND prospect_key = ?', [$hash, $prospectKey]);
        $savedLeadKeys = $db->GetCol('SELECT lead_key FROM project_company_sales_prospect_lead WHERE company_key_hash = ? AND prospect_key = ? ORDER BY lead_key', [$hash, $prospectKey]);
        $expectedLeadKeys = $leadKeys;
        sort($expectedLeadKeys);
        if (!is_array($saved) || (string) $saved['prospect_code'] !== $code || (string) $saved['prospect_name'] !== $name || (string) $saved['prospect_status'] !== $status || array_values($savedLeadKeys ?: []) !== $expectedLeadKeys) {
            throw new RuntimeException('Prospect header or Lead child read-back verification failed.');
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_sales_prospect', $prospectKey, yovel_admin_sales_sc03_audit_payload($company, $admin, [
            'prospect_code' => $code, 'prospect_name' => $name, 'prospect_status' => $status, 'prospect_version' => $version, 'lead_keys' => $expectedLeadKeys,
        ]), 'Company administrator saved a Prospect and its Lead links.');
        $db->CommitTrans();
        $saved['lead_keys'] = $expectedLeadKeys;
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_appointment_settings_save(array $company, array $admin, array $input): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $timezone = trim((string) ($input['timezone'] ?? 'UTC'));
    $notice = filter_var($input['minimum_notice_minutes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10080]]);
    $horizon = filter_var($input['booking_horizon_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
    $duration = filter_var($input['appointment_duration_minutes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 480]]);
    $expectedVersion = filter_var($input['expected_version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true) || $notice === false || $horizon === false || $duration === false || $expectedVersion === false) {
        throw new InvalidArgumentException('Valid Appointment settings are required.');
    }
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $db->BeginTrans();
    try {
        $existing = $db->GetRow('SELECT * FROM project_company_sales_appointment_booking_settings WHERE company_key_hash = ? FOR UPDATE', [$hash]);
        $version = (int) ($existing['settings_version'] ?? 0);
        if ($version !== (int) $expectedVersion) {
            throw new RuntimeException('Appointment settings changed. Reload and try again.');
        }
        $key = $existing ? (string) $existing['settings_key'] : bx_uuid();
        $nextVersion = $version + 1;
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_sales_appointment_booking_settings (settings_key, company_key, company_key_hash, settings_version, timezone, minimum_notice_minutes, booking_horizon_days, appointment_duration_minutes, created_by_admin_key, updated_by_admin_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE settings_version = VALUES(settings_version), timezone = VALUES(timezone), minimum_notice_minutes = VALUES(minimum_notice_minutes), booking_horizon_days = VALUES(booking_horizon_days), appointment_duration_minutes = VALUES(appointment_duration_minutes), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$key, (string) $company['company_key'], $hash, $nextVersion, $timezone, $notice, $horizon, $duration, (string) $admin['admin_key'], (string) $admin['admin_key']],
            'Sales Appointment settings save'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_sales_appointment_booking_settings WHERE company_key_hash = ?', [$hash]);
        foreach (['settings_key' => $key, 'settings_version' => $nextVersion, 'timezone' => $timezone, 'minimum_notice_minutes' => $notice, 'booking_horizon_days' => $horizon, 'appointment_duration_minutes' => $duration] as $column => $expected) {
            if (!is_array($saved) || (string) ($saved[$column] ?? '') !== (string) $expected) {
                throw new RuntimeException('Appointment settings read-back verification failed for ' . $column . '.');
            }
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_sales_appointment_booking_settings', $key, yovel_admin_sales_sc03_audit_payload($company, $admin, [
            'settings_version' => $nextVersion, 'timezone' => $timezone, 'minimum_notice_minutes' => $notice, 'booking_horizon_days' => $horizon, 'appointment_duration_minutes' => $duration,
        ]), 'Company administrator saved Appointment booking settings.');
        $db->CommitTrans();
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_sc03_time(string $value, string $label): string
{
    $date = DateTimeImmutable::createFromFormat('!H:i', trim($value));
    if (!$date || $date->format('H:i') !== trim($value)) {
        throw new InvalidArgumentException($label . ' must use HH:MM.');
    }
    return $date->format('H:i:s');
}

function yovel_admin_sales_appointment_slot_save(array $company, array $admin, array $input): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $weekday = filter_var($input['weekday_number'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
    $start = yovel_admin_sales_sc03_time((string) ($input['start_time'] ?? ''), 'Slot start');
    $end = yovel_admin_sales_sc03_time((string) ($input['end_time'] ?? ''), 'Slot end');
    $status = yovel_admin_status((string) ($input['slot_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED'], '');
    $idempotencyKey = yovel_admin_sales_sc03_idempotency($input['idempotency_key'] ?? null);
    if ($weekday === false || $start >= $end || $status === '') {
        throw new InvalidArgumentException('A valid weekday, time range, and slot status are required.');
    }
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $db->BeginTrans();
    try {
        $existing = null;
        if ($idempotencyKey !== null) {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_appointment_booking_slot WHERE company_key_hash = ? AND idempotency_key = ? FOR UPDATE', [$hash, $idempotencyKey]);
            if ($existing) {
                if ((int) $existing['weekday_number'] !== (int) $weekday || (string) $existing['start_time'] !== $start || (string) $existing['end_time'] !== $end || (string) $existing['slot_status'] !== $status) {
                    throw new RuntimeException('Appointment slot idempotency key belongs to another request.');
                }
                $db->CommitTrans();
                return $existing;
            }
        }
        if (!$existing) {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_appointment_booking_slot WHERE company_key_hash = ? AND weekday_number = ? AND start_time = ? AND end_time = ? FOR UPDATE', [$hash, $weekday, $start, $end]);
        }
        $key = $existing ? (string) $existing['booking_slot_key'] : bx_uuid();
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_sales_appointment_booking_slot (booking_slot_key, company_key, company_key_hash, weekday_number, start_time, end_time, slot_status, idempotency_key, created_by_admin_key, updated_by_admin_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE slot_status = VALUES(slot_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$key, (string) $company['company_key'], $hash, $weekday, $start, $end, $status, $idempotencyKey, (string) $admin['admin_key'], (string) $admin['admin_key']],
            'Sales Appointment slot save'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_sales_appointment_booking_slot WHERE company_key_hash = ? AND booking_slot_key = ?', [$hash, $key]);
        if (!is_array($saved) || (int) $saved['weekday_number'] !== (int) $weekday || (string) $saved['start_time'] !== $start || (string) $saved['end_time'] !== $end || (string) $saved['slot_status'] !== $status) {
            throw new RuntimeException('Appointment slot read-back verification failed.');
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_sales_appointment_booking_slot', $key, yovel_admin_sales_sc03_audit_payload($company, $admin, [
            'weekday_number' => $weekday, 'start_time' => $start, 'end_time' => $end, 'slot_status' => $status,
        ]), 'Company administrator saved an Appointment booking slot.');
        $db->CommitTrans();
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_appointment_availability(array $company, array $admin, string $date): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$day || $day->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Availability date is invalid.');
    }
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $settings = $db->GetRow('SELECT * FROM project_company_sales_appointment_booking_settings WHERE company_key_hash = ? LIMIT 1', [$hash]);
    $duration = max(5, (int) ($settings['appointment_duration_minutes'] ?? 30));
    $slots = $db->GetAll(
        "SELECT start_time, end_time FROM project_company_sales_appointment_booking_slot WHERE company_key_hash = ? AND weekday_number = ? AND slot_status = 'ACTIVE' ORDER BY start_time",
        [$hash, (int) $day->format('N')]
    );
    $booked = $db->GetAll(
        "SELECT starts_at, ends_at FROM project_company_sales_appointment WHERE company_key_hash = ? AND appointment_status = 'SCHEDULED' AND DATE(starts_at) = ? ORDER BY starts_at",
        [$hash, $date]
    );
    $blocked = $db->GetAll(
        "SELECT start_time, end_time FROM project_company_sales_appointment_availability WHERE company_key_hash = ? AND availability_date = ? AND availability_status = 'BLOCKED' ORDER BY start_time",
        [$hash, $date]
    );
    $results = [];
    foreach (is_array($slots) ? $slots : [] as $slot) {
        $cursor = new DateTimeImmutable($date . ' ' . substr((string) $slot['start_time'], 0, 8));
        $slotEnd = new DateTimeImmutable($date . ' ' . substr((string) $slot['end_time'], 0, 8));
        while ($cursor->modify('+' . $duration . ' minutes') <= $slotEnd) {
            $candidateEnd = $cursor->modify('+' . $duration . ' minutes');
            $available = true;
            foreach (array_merge(is_array($booked) ? $booked : [], array_map(static fn (array $row): array => [
                'starts_at' => $date . ' ' . $row['start_time'], 'ends_at' => $date . ' ' . $row['end_time'],
            ], is_array($blocked) ? $blocked : [])) as $occupied) {
                $occupiedStart = new DateTimeImmutable((string) $occupied['starts_at']);
                $occupiedEnd = new DateTimeImmutable((string) $occupied['ends_at']);
                if ($cursor < $occupiedEnd && $candidateEnd > $occupiedStart) {
                    $available = false;
                    break;
                }
            }
            if ($available) {
                $results[] = ['date' => $date, 'start_time' => $cursor->format('H:i'), 'end_time' => $candidateEnd->format('H:i'), 'status' => 'AVAILABLE'];
            }
            $cursor = $candidateEnd;
        }
    }
    return $results;
}

function yovel_admin_sales_sc03_datetime(string $value, string $label): string
{
    $value = str_replace('T', ' ', trim($value));
    if (strlen($value) === 16) {
        $value .= ':00';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function yovel_admin_sales_appointment_save(array $company, array $admin, array $input): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $appointmentKey = trim((string) ($input['appointment_key'] ?? ''));
    if ($appointmentKey !== '' && !yovel_admin_is_uuid($appointmentKey)) {
        throw new InvalidArgumentException('Appointment key is invalid.');
    }
    $code = yovel_admin_sales_sc03_code((string) ($input['appointment_code'] ?? ''), 'Appointment code');
    $status = yovel_admin_status((string) ($input['appointment_status'] ?? 'SCHEDULED'), ['SCHEDULED', 'COMPLETED', 'CANCELLED', 'NO_SHOW', 'DELETED'], '');
    $startsAt = yovel_admin_sales_sc03_datetime((string) ($input['starts_at'] ?? ''), 'Appointment start');
    $endsAt = yovel_admin_sales_sc03_datetime((string) ($input['ends_at'] ?? ''), 'Appointment end');
    if ($status === '' || $startsAt >= $endsAt) {
        throw new InvalidArgumentException('A valid Appointment status and time range are required.');
    }
    $leadKey = yovel_admin_sales_sc03_related_key('project_company_sales_lead', 'lead_key', $company, $input['lead_key'] ?? '', 'Lead');
    $prospectKey = yovel_admin_sales_sc03_related_key('project_company_sales_prospect', 'prospect_key', $company, $input['prospect_key'] ?? '', 'Prospect');
    $assignedAdminKey = yovel_admin_sales_sc03_related_key('project_company_admin', 'admin_key', $company, $input['assigned_admin_key'] ?? '', 'Assigned administrator');
    if ($leadKey === null && $prospectKey === null) {
        throw new InvalidArgumentException('Appointment requires a Lead or Prospect.');
    }
    $contactName = trim((string) ($input['contact_name'] ?? ''));
    $contactEmail = trim((string) ($input['contact_email'] ?? ''));
    $contactPhone = trim((string) ($input['contact_phone'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));
    if (strlen($contactName) > 200 || strlen($contactEmail) > 180 || strlen($contactPhone) > 80 || strlen($notes) > 10000 || ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL))) {
        throw new InvalidArgumentException('Appointment contact details are invalid.');
    }
    $idempotencyKey = yovel_admin_sales_sc03_idempotency($input['idempotency_key'] ?? null);
    $expectedVersion = trim((string) ($input['expected_version'] ?? ''));
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $db->BeginTrans();
    try {
        $existing = null;
        if ($idempotencyKey !== null) {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_appointment WHERE company_key_hash = ? AND idempotency_key = ? FOR UPDATE', [$hash, $idempotencyKey]);
            if ($existing) {
                $matches = (string) $existing['appointment_code'] === $code
                    && (string) $existing['appointment_status'] === $status
                    && (string) ($existing['lead_key'] ?? '') === (string) $leadKey
                    && (string) ($existing['prospect_key'] ?? '') === (string) $prospectKey
                    && (string) ($existing['assigned_admin_key'] ?? '') === (string) $assignedAdminKey
                    && (string) $existing['starts_at'] === $startsAt
                    && (string) $existing['ends_at'] === $endsAt
                    && (string) $existing['contact_name'] === $contactName
                    && (string) $existing['contact_email'] === $contactEmail
                    && (string) $existing['contact_phone'] === $contactPhone
                    && (string) ($existing['notes'] ?? '') === $notes;
                if (!$matches) {
                    throw new RuntimeException('Appointment idempotency key belongs to another request.');
                }
                $db->CommitTrans();
                return $existing;
            }
        }
        if (!$existing && $appointmentKey !== '') {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_appointment WHERE company_key_hash = ? AND appointment_key = ? FOR UPDATE', [$hash, $appointmentKey]);
            if (!$existing) {
                throw new InvalidArgumentException('Appointment was not found for this company.');
            }
        }
        if (!$existing) {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_appointment WHERE company_key_hash = ? AND appointment_code = ? FOR UPDATE', [$hash, $code]);
        }
        if ($existing && $expectedVersion !== '' && (int) $existing['appointment_version'] !== (int) $expectedVersion) {
            throw new RuntimeException('Appointment changed. Reload and try again.');
        }
        $appointmentKey = $existing ? (string) $existing['appointment_key'] : bx_uuid();
        $overlap = $db->GetRow(
            "SELECT appointment_key FROM project_company_sales_appointment WHERE company_key_hash = ? AND appointment_status = 'SCHEDULED' AND appointment_key <> ? AND starts_at < ? AND ends_at > ? ORDER BY starts_at LIMIT 1 FOR UPDATE",
            [$hash, $appointmentKey, $endsAt, $startsAt]
        );
        if ($status === 'SCHEDULED' && $overlap) {
            throw new RuntimeException('The Appointment overlaps another scheduled booking.');
        }
        $version = $existing ? (int) $existing['appointment_version'] + 1 : 1;
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_sales_appointment (appointment_key, company_key, company_key_hash, appointment_code, appointment_status, appointment_version, lead_key, prospect_key, assigned_admin_key, starts_at, ends_at, contact_name, contact_email, contact_phone, notes, idempotency_key, created_by_admin_key, updated_by_admin_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE appointment_status = VALUES(appointment_status), appointment_version = VALUES(appointment_version), lead_key = VALUES(lead_key), prospect_key = VALUES(prospect_key), assigned_admin_key = VALUES(assigned_admin_key), starts_at = VALUES(starts_at), ends_at = VALUES(ends_at), contact_name = VALUES(contact_name), contact_email = VALUES(contact_email), contact_phone = VALUES(contact_phone), notes = VALUES(notes), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$appointmentKey, (string) $company['company_key'], $hash, $code, $status, $version, $leadKey, $prospectKey, $assignedAdminKey, $startsAt, $endsAt, $contactName, $contactEmail, $contactPhone, $notes, $idempotencyKey, (string) $admin['admin_key'], (string) $admin['admin_key']],
            'Sales Appointment save'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_sales_appointment WHERE company_key_hash = ? AND appointment_key = ?', [$hash, $appointmentKey]);
        foreach (['appointment_code' => $code, 'appointment_status' => $status, 'appointment_version' => $version, 'lead_key' => $leadKey, 'prospect_key' => $prospectKey, 'assigned_admin_key' => $assignedAdminKey, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'contact_name' => $contactName, 'contact_email' => $contactEmail, 'contact_phone' => $contactPhone, 'notes' => $notes] as $column => $expected) {
            if (!is_array($saved) || (string) ($saved[$column] ?? '') !== (string) $expected) {
                throw new RuntimeException('Appointment read-back verification failed for ' . $column . '.');
            }
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_sales_appointment', $appointmentKey, yovel_admin_sales_sc03_audit_payload($company, $admin, [
            'appointment_code' => $code, 'appointment_status' => $status, 'lead_key' => $leadKey, 'prospect_key' => $prospectKey, 'starts_at' => $startsAt, 'ends_at' => $endsAt,
        ]), 'Company administrator saved an Appointment.');
        $db->CommitTrans();
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_crm_register_owner_service(string $contract, callable $service): void
{
    $allowed = ['sales.customer.create-from-lead.v1', 'sales.opportunity.create-from-lead.v1'];
    if (!in_array($contract, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported Sales conversion owner service contract.');
    }
    $GLOBALS['yovel_admin_sales_crm_owner_services'][$contract] = $service;
}

function yovel_admin_sales_crm_conversion_services(): array
{
    $registered = is_array($GLOBALS['yovel_admin_sales_crm_owner_services'] ?? null) ? $GLOBALS['yovel_admin_sales_crm_owner_services'] : [];
    $definitions = [
        'CUSTOMER' => ['contract' => 'sales.customer.create-from-lead.v1', 'function' => 'yovel_admin_sales_customer_create_from_lead'],
        'OPPORTUNITY' => ['contract' => 'sales.opportunity.create-from-lead.v1', 'function' => 'yovel_admin_sales_opportunity_create_from_lead'],
    ];
    foreach ($definitions as &$definition) {
        $callable = $registered[$definition['contract']] ?? (function_exists($definition['function']) ? $definition['function'] : null);
        $definition['service'] = is_callable($callable) ? $callable : null;
        $definition['status'] = is_callable($callable) ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY';
    }
    unset($definition);
    return $definitions;
}

function yovel_admin_sales_lead_conversion_request(array $company, array $admin, array $input): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $leadKey = yovel_admin_sales_sc03_related_key('project_company_sales_lead', 'lead_key', $company, $input['lead_key'] ?? '', 'Lead');
    $targetType = yovel_admin_status((string) ($input['target_type'] ?? ''), ['CUSTOMER', 'OPPORTUNITY', 'BOTH'], '');
    $idempotencyKey = yovel_admin_sales_sc03_idempotency($input['idempotency_key'] ?? null, true);
    if ($targetType === '') {
        throw new InvalidArgumentException('A valid Lead conversion target is required.');
    }
    $services = yovel_admin_sales_crm_conversion_services();
    $requiredTargets = $targetType === 'BOTH' ? ['CUSTOMER', 'OPPORTUNITY'] : [$targetType];
    $dependencyStatus = count(array_filter($requiredTargets, static fn (string $target): bool => ($services[$target]['status'] ?? '') !== 'AVAILABLE')) === 0 ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY';
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $db->BeginTrans();
    try {
        $existing = $db->GetRow('SELECT * FROM project_company_sales_lead_conversion WHERE company_key_hash = ? AND idempotency_key = ? FOR UPDATE', [$hash, $idempotencyKey]);
        if ($existing) {
            if ((string) $existing['lead_key'] !== $leadKey || (string) $existing['target_type'] !== $targetType) {
                throw new RuntimeException('Conversion idempotency key belongs to another request.');
            }
            $db->CommitTrans();
            return $existing;
        }
        $lead = $db->GetRow('SELECT * FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ? FOR UPDATE', [$hash, $leadKey]);
        if (!$lead || in_array((string) $lead['lead_status'], ['CONVERTED', 'INACTIVE', 'DELETED'], true)) {
            throw new RuntimeException('This Lead cannot start a conversion request.');
        }
        $conversionKey = bx_uuid();
        $outboxKey = bx_uuid();
        $payload = json_encode([
            'company_key' => (string) $company['company_key'], 'company_key_hash' => $hash,
            'lead_key' => $leadKey, 'target_type' => $targetType, 'conversion_key' => $conversionKey,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_sales_lead_conversion (conversion_key, company_key, company_key_hash, lead_key, target_type, conversion_status, dependency_status, idempotency_key, created_by_admin_key, updated_by_admin_key)
             VALUES (?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?)",
            [$conversionKey, (string) $company['company_key'], $hash, $leadKey, $targetType, $dependencyStatus, $idempotencyKey, (string) $admin['admin_key'], (string) $admin['admin_key']],
            'Sales Lead conversion request save'
        );
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_sales_integration_outbox (outbox_key, company_key, company_key_hash, aggregate_type, aggregate_key, event_type, payload_json, outbox_status, idempotency_key, available_at)
             VALUES (?, ?, ?, 'LEAD_CONVERSION', ?, 'sales.lead.conversion-requested.v1', ?, 'PENDING', ?, UTC_TIMESTAMP())",
            [$outboxKey, (string) $company['company_key'], $hash, $conversionKey, $payload, $idempotencyKey],
            'Sales Lead conversion outbox save'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_sales_lead_conversion WHERE company_key_hash = ? AND conversion_key = ?', [$hash, $conversionKey]);
        $savedOutbox = $db->GetRow('SELECT * FROM project_company_sales_integration_outbox WHERE company_key_hash = ? AND aggregate_key = ?', [$hash, $conversionKey]);
        if (!is_array($saved) || (string) $saved['lead_key'] !== $leadKey || (string) $saved['target_type'] !== $targetType || (string) $saved['dependency_status'] !== $dependencyStatus || !is_array($savedOutbox) || (string) $savedOutbox['payload_json'] !== $payload) {
            throw new RuntimeException('Lead conversion request or outbox read-back verification failed.');
        }
        bx_audit('REQUEST', 'project_company_sales_lead_conversion', $conversionKey, yovel_admin_sales_sc03_audit_payload($company, $admin, [
            'lead_key' => $leadKey, 'lead_code' => (string) $lead['lead_code'], 'target_type' => $targetType, 'conversion_status' => 'PENDING', 'dependency_status' => $dependencyStatus,
        ]), 'Company administrator requested Lead conversion through owner services.');
        $db->CommitTrans();
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_lead_conversion_process(array $company, array $admin, string $conversionKey): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    if (!yovel_admin_is_uuid($conversionKey)) {
        throw new InvalidArgumentException('Conversion key is invalid.');
    }
    $services = yovel_admin_sales_crm_conversion_services();
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $db->BeginTrans();
    try {
        $conversion = $db->GetRow('SELECT * FROM project_company_sales_lead_conversion WHERE company_key_hash = ? AND conversion_key = ? FOR UPDATE', [$hash, $conversionKey]);
        if (!$conversion) {
            throw new InvalidArgumentException('Conversion request was not found for this company.');
        }
        if ((string) $conversion['conversion_status'] === 'COMPLETED') {
            $db->CommitTrans();
            return $conversion;
        }
        $lead = $db->GetRow('SELECT * FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ? FOR UPDATE', [$hash, (string) $conversion['lead_key']]);
        if (!$lead) {
            throw new RuntimeException('Conversion Lead is unavailable.');
        }
        $targets = (string) $conversion['target_type'] === 'BOTH' ? ['CUSTOMER', 'OPPORTUNITY'] : [(string) $conversion['target_type']];
        foreach ($targets as $target) {
            if (!is_callable($services[$target]['service'] ?? null)) {
                throw new RuntimeException($services[$target]['contract'] . ' owner service is unavailable.');
            }
        }
        $customerKey = (string) ($conversion['customer_key'] ?? '');
        $opportunityKey = (string) ($conversion['opportunity_key'] ?? '');
        foreach ($targets as $target) {
            $result = ($services[$target]['service'])($company, $admin, $lead, (string) $conversion['idempotency_key']);
            $targetKey = is_array($result) ? (string) ($result[strtolower($target) . '_key'] ?? $result['record_key'] ?? '') : (string) $result;
            if (!yovel_admin_is_uuid($targetKey)) {
                throw new RuntimeException($services[$target]['contract'] . ' returned an invalid stable key.');
            }
            if ($target === 'CUSTOMER') {
                $customerKey = $targetKey;
            } else {
                $opportunityKey = $targetKey;
            }
        }
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_sales_lead_conversion SET conversion_status = 'COMPLETED', dependency_status = 'AVAILABLE', customer_key = ?, opportunity_key = ?, error_message = '', updated_by_admin_key = ? WHERE company_key_hash = ? AND conversion_key = ?",
            [$customerKey !== '' ? $customerKey : null, $opportunityKey !== '' ? $opportunityKey : null, (string) $admin['admin_key'], $hash, $conversionKey],
            'Sales Lead conversion complete'
        );
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_sales_integration_outbox SET outbox_status = 'PROCESSED', attempts = attempts + 1, processed_at = UTC_TIMESTAMP(), last_error = '' WHERE company_key_hash = ? AND aggregate_key = ?",
            [$hash, $conversionKey],
            'Sales Lead conversion outbox complete'
        );
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_sales_lead SET lead_status = 'CONVERTED', converted_at = COALESCE(converted_at, UTC_TIMESTAMP()), lead_version = lead_version + 1, updated_by_admin_key = ? WHERE company_key_hash = ? AND lead_key = ?",
            [(string) $admin['admin_key'], $hash, (string) $lead['lead_key']],
            'Sales Lead conversion status save'
        );
        if ($opportunityKey !== '') {
            $prospectKeys = $db->GetCol('SELECT prospect_key FROM project_company_sales_prospect_lead WHERE company_key_hash = ? AND lead_key = ?', [$hash, (string) $lead['lead_key']]);
            foreach (is_array($prospectKeys) ? $prospectKeys : [] as $prospectKey) {
                yovel_admin_db_execute(
                    $db,
                    'INSERT INTO project_company_sales_prospect_opportunity (prospect_opportunity_key, company_key, company_key_hash, prospect_key, opportunity_key, conversion_key) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE conversion_key = VALUES(conversion_key)',
                    [bx_uuid(), (string) $company['company_key'], $hash, (string) $prospectKey, $opportunityKey, $conversionKey],
                    'Sales Prospect Opportunity link save'
                );
            }
        }
        $saved = $db->GetRow('SELECT * FROM project_company_sales_lead_conversion WHERE company_key_hash = ? AND conversion_key = ?', [$hash, $conversionKey]);
        $savedLead = $db->GetRow('SELECT lead_status, converted_at FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ?', [$hash, (string) $lead['lead_key']]);
        $savedOutbox = $db->GetRow('SELECT outbox_status, processed_at FROM project_company_sales_integration_outbox WHERE company_key_hash = ? AND aggregate_key = ?', [$hash, $conversionKey]);
        if (!is_array($saved) || (string) $saved['conversion_status'] !== 'COMPLETED' || (string) ($saved['customer_key'] ?? '') !== $customerKey || (string) ($saved['opportunity_key'] ?? '') !== $opportunityKey || (string) ($savedLead['lead_status'] ?? '') !== 'CONVERTED' || (string) ($savedOutbox['outbox_status'] ?? '') !== 'PROCESSED') {
            throw new RuntimeException('Lead conversion completion read-back verification failed.');
        }
        bx_audit('CONVERT', 'project_company_sales_lead', (string) $lead['lead_key'], yovel_admin_sales_sc03_audit_payload($company, $admin, [
            'lead_code' => (string) $lead['lead_code'], 'lead_name' => (string) $lead['lead_name'], 'lead_status' => 'CONVERTED', 'conversion_key' => $conversionKey, 'customer_key' => $customerKey, 'opportunity_key' => $opportunityKey,
        ]), 'Owner services completed Lead conversion.');
        $db->CommitTrans();
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_lead_timeline(array $company, array $admin, string $leadKey): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    yovel_admin_sales_sc03_related_key('project_company_sales_lead', 'lead_key', $company, $leadKey, 'Lead');
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $events = [];
    $communications = $db->GetAll(
        'SELECT communication_key AS event_key, communication_type AS event_type, summary AS event_label, occurred_at, direction AS event_status FROM project_company_sales_lead_communication WHERE company_key_hash = ? AND lead_key = ? ORDER BY occurred_at DESC, x_id DESC LIMIT 50',
        [$hash, $leadKey]
    );
    foreach (is_array($communications) ? $communications : [] as $row) {
        $events[] = $row;
    }
    $notes = $db->GetAll(
        "SELECT crm_note_key AS event_key, 'NOTE' AS event_type, note_text AS event_label, created_at AS occurred_at, note_status AS event_status FROM project_company_sales_crm_note WHERE company_key_hash = ? AND subject_type = 'LEAD' AND subject_key = ? AND note_status = 'ACTIVE' ORDER BY created_at DESC, x_id DESC LIMIT 50",
        [$hash, $leadKey]
    );
    foreach (is_array($notes) ? $notes : [] as $row) {
        $events[] = $row;
    }
    $appointments = $db->GetAll(
        "SELECT appointment_key AS event_key, 'APPOINTMENT' AS event_type, CONCAT(appointment_code, ' ', contact_name) AS event_label, starts_at AS occurred_at, appointment_status AS event_status FROM project_company_sales_appointment WHERE company_key_hash = ? AND lead_key = ? AND appointment_status <> 'DELETED' ORDER BY starts_at DESC, x_id DESC LIMIT 50",
        [$hash, $leadKey]
    );
    foreach (is_array($appointments) ? $appointments : [] as $row) {
        $events[] = $row;
    }
    usort($events, static fn (array $left, array $right): int => strcmp((string) $right['occurred_at'], (string) $left['occurred_at']) ?: strcmp((string) $right['event_key'], (string) $left['event_key']));
    return array_slice($events, 0, 50);
}

function yovel_admin_sales_lead_import(array $company, array $admin, string $csv): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    if ($csv === '' || strlen($csv) > 2_000_000) {
        throw new InvalidArgumentException('Lead import CSV is empty or too large.');
    }
    $stream = fopen('php://temp', 'w+');
    if ($stream === false) {
        throw new RuntimeException('Lead import stream could not be opened.');
    }
    fwrite($stream, $csv);
    rewind($stream);
    $headers = fgetcsv($stream, null, ',', '"', '');
    $allowed = ['lead_code', 'lead_name', 'lead_status', 'organization_name', 'lead_source', 'email', 'phone', 'mobile', 'website', 'industry', 'estimated_value', 'next_contact_date', 'address_line', 'city', 'country'];
    if (!is_array($headers) || $headers === [] || array_diff($headers, $allowed) !== [] || !in_array('lead_code', $headers, true) || !in_array('lead_name', $headers, true)) {
        fclose($stream);
        throw new InvalidArgumentException('Lead import headers are invalid.');
    }
    $result = ['created' => 0, 'updated' => 0, 'rows' => []];
    $rowNumber = 1;
    while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
        $rowNumber++;
        if ($rowNumber > 501) {
            fclose($stream);
            throw new InvalidArgumentException('Lead import is limited to 500 records.');
        }
        if (count($values) !== count($headers)) {
            fclose($stream);
            throw new InvalidArgumentException('Lead import row ' . $rowNumber . ' has the wrong column count.');
        }
        $input = array_combine($headers, $values);
        if (!is_array($input)) {
            continue;
        }
        $code = yovel_admin_sales_sc03_code((string) ($input['lead_code'] ?? ''), 'Lead code');
        $existing = bx_db()->GetRow('SELECT lead_key FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_code = ?', [(string) $company['company_key_hash'], $code]);
        if ($existing) {
            $input['lead_key'] = (string) $existing['lead_key'];
        }
        $message = yovel_admin_sales_crm_lead_import_with_post($input, static fn (): string => yovel_admin_save_sales_lead($company, $admin));
        $result[$existing ? 'updated' : 'created']++;
        $result['rows'][] = ['row' => $rowNumber, 'lead_code' => $code, 'message' => $message];
    }
    fclose($stream);
    return $result;
}

function yovel_admin_sales_crm_lead_import_with_post(array $input, callable $callback): mixed
{
    $previous = $_POST;
    $_POST = $input;
    try {
        return $callback();
    } finally {
        $_POST = $previous;
    }
}

function yovel_admin_sales_lead_export(array $company, array $admin, array $filters = []): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $columns = ['lead_code', 'lead_name', 'lead_status', 'organization_name', 'email', 'phone', 'mobile', 'assigned_admin_key', 'next_contact_date'];
    $sql = 'SELECT ' . implode(', ', $columns) . " FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_status <> 'DELETED'";
    $params = [(string) $company['company_key_hash']];
    $leadCode = trim((string) ($filters['lead_code'] ?? ''));
    if ($leadCode !== '') {
        $sql .= ' AND lead_code = ?';
        $params[] = yovel_admin_sales_sc03_code($leadCode, 'Lead code');
    }
    $sql .= ' ORDER BY lead_code ASC';
    $rows = bx_db()->GetAll($sql, $params);
    return ['columns' => $columns, 'rows' => is_array($rows) ? $rows : []];
}

function yovel_admin_sales_sc03_report_filters(array $company, array $admin, array $filters): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $from = yovel_admin_optional_date((string) ($filters['date_from'] ?? ''), 'Report start date');
    $to = yovel_admin_optional_date((string) ($filters['date_to'] ?? ''), 'Report end date');
    if ($from !== '' && $to !== '' && $from > $to) {
        throw new InvalidArgumentException('Report start date must not be after end date.');
    }
    $owner = yovel_admin_sales_sc03_related_key('project_company_admin', 'admin_key', $company, $filters['owner_admin_key'] ?? '', 'Report owner');
    return ['date_from' => $from, 'date_to' => $to, 'owner_admin_key' => $owner];
}

function yovel_admin_sales_sc03_lead_where(array $company, array $filters, string $alias = 'lead_record'): array
{
    $where = ["{$alias}.company_key_hash = ?", "{$alias}.lead_status <> 'DELETED'"];
    $params = [(string) $company['company_key_hash']];
    if ((string) $filters['date_from'] !== '') {
        $where[] = "DATE({$alias}.created_at) >= ?";
        $params[] = (string) $filters['date_from'];
    }
    if ((string) $filters['date_to'] !== '') {
        $where[] = "DATE({$alias}.created_at) <= ?";
        $params[] = (string) $filters['date_to'];
    }
    if ((string) ($filters['owner_admin_key'] ?? '') !== '') {
        $where[] = "{$alias}.assigned_admin_key = ?";
        $params[] = (string) $filters['owner_admin_key'];
    }
    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

function yovel_admin_sales_report_lead_details(array $company, array $admin, array $input = []): array
{
    $filters = yovel_admin_sales_sc03_report_filters($company, $admin, $input);
    $where = yovel_admin_sales_sc03_lead_where($company, $filters);
    $columns = ['lead_code', 'lead_name', 'lead_status', 'organization_name', 'owner', 'email', 'phone', 'next_contact_date', 'created_at'];
    $rows = bx_db()->GetAll(
        "SELECT lead_record.lead_code, lead_record.lead_name, lead_record.lead_status, lead_record.organization_name, COALESCE(admin_record.admin_name, admin_record.admin_login, '') AS owner, lead_record.email, lead_record.phone, lead_record.next_contact_date, lead_record.created_at
         FROM project_company_sales_lead lead_record
         LEFT JOIN project_company_admin admin_record ON admin_record.company_key_hash = lead_record.company_key_hash AND admin_record.admin_key = lead_record.assigned_admin_key
         WHERE {$where['sql']} ORDER BY lead_record.created_at ASC, lead_record.lead_code ASC",
        $where['params']
    );
    return ['columns' => $columns, 'rows' => is_array($rows) ? $rows : [], 'filters' => $filters];
}

function yovel_admin_sales_report_lead_conversion_time(array $company, array $admin, array $input = []): array
{
    $filters = yovel_admin_sales_sc03_report_filters($company, $admin, $input);
    $where = yovel_admin_sales_sc03_lead_where($company, $filters);
    $rows = bx_db()->GetAll(
        "SELECT lead_record.lead_code, lead_record.lead_name, lead_record.created_at, lead_record.converted_at,
                ROUND(TIMESTAMPDIFF(MINUTE, lead_record.created_at, lead_record.converted_at) / 60, 2) AS conversion_hours
         FROM project_company_sales_lead lead_record WHERE {$where['sql']} AND lead_record.converted_at IS NOT NULL
         ORDER BY lead_record.converted_at ASC, lead_record.lead_code ASC",
        $where['params']
    );
    foreach (is_array($rows) ? $rows : [] as &$row) {
        $row['conversion_hours'] = number_format((float) $row['conversion_hours'], 2, '.', '');
    }
    unset($row);
    return ['columns' => ['lead_code', 'lead_name', 'created_at', 'converted_at', 'conversion_hours'], 'rows' => is_array($rows) ? $rows : [], 'filters' => $filters];
}

function yovel_admin_sales_report_lead_owner_efficiency(array $company, array $admin, array $input = []): array
{
    $filters = yovel_admin_sales_sc03_report_filters($company, $admin, $input);
    $where = yovel_admin_sales_sc03_lead_where($company, $filters);
    $rows = bx_db()->GetAll(
        "SELECT lead_record.assigned_admin_key, COALESCE(admin_record.admin_name, admin_record.admin_login, 'Unassigned') AS owner,
                COUNT(*) AS lead_count, SUM(CASE WHEN lead_record.converted_at IS NOT NULL OR lead_record.lead_status = 'CONVERTED' THEN 1 ELSE 0 END) AS converted_count,
                ROUND(SUM(CASE WHEN lead_record.converted_at IS NOT NULL OR lead_record.lead_status = 'CONVERTED' THEN 1 ELSE 0 END) * 100 / COUNT(*), 2) AS conversion_rate,
                ROUND(AVG(CASE WHEN lead_record.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, lead_record.created_at, lead_record.first_response_at) END), 2) AS average_response_minutes
         FROM project_company_sales_lead lead_record
         LEFT JOIN project_company_admin admin_record ON admin_record.company_key_hash = lead_record.company_key_hash AND admin_record.admin_key = lead_record.assigned_admin_key
         WHERE {$where['sql']} GROUP BY lead_record.assigned_admin_key, owner ORDER BY owner ASC",
        $where['params']
    );
    foreach (is_array($rows) ? $rows : [] as &$row) {
        $row['lead_count'] = (int) $row['lead_count'];
        $row['converted_count'] = (int) $row['converted_count'];
        $row['conversion_rate'] = number_format((float) $row['conversion_rate'], 2, '.', '');
        $row['average_response_minutes'] = $row['average_response_minutes'] === null ? null : number_format((float) $row['average_response_minutes'], 2, '.', '');
    }
    unset($row);
    return ['columns' => ['assigned_admin_key', 'owner', 'lead_count', 'converted_count', 'conversion_rate', 'average_response_minutes'], 'rows' => is_array($rows) ? $rows : [], 'filters' => $filters];
}

function yovel_admin_sales_report_prospects_engaged(array $company, array $admin, array $input = []): array
{
    $filters = yovel_admin_sales_sc03_report_filters($company, $admin, $input);
    $where = ["prospect.company_key_hash = ?", "prospect.prospect_status NOT IN ('CONVERTED','DELETED')"];
    $params = [(string) $company['company_key_hash']];
    if ((string) $filters['date_from'] !== '') {
        $where[] = 'DATE(prospect.created_at) >= ?';
        $params[] = (string) $filters['date_from'];
    }
    if ((string) $filters['date_to'] !== '') {
        $where[] = 'DATE(prospect.created_at) <= ?';
        $params[] = (string) $filters['date_to'];
    }
    if ((string) ($filters['owner_admin_key'] ?? '') !== '') {
        $where[] = 'prospect.assigned_admin_key = ?';
        $params[] = (string) $filters['owner_admin_key'];
    }
    $rows = bx_db()->GetAll(
        "SELECT prospect.prospect_code, prospect.prospect_name, prospect.prospect_status, COUNT(DISTINCT prospect_lead.lead_key) AS lead_count,
                COUNT(DISTINCT note_record.crm_note_key) AS note_count, MAX(note_record.created_at) AS last_engaged_at
         FROM project_company_sales_prospect prospect
         LEFT JOIN project_company_sales_prospect_lead prospect_lead ON prospect_lead.company_key_hash = prospect.company_key_hash AND prospect_lead.prospect_key = prospect.prospect_key
         LEFT JOIN project_company_sales_crm_note note_record ON note_record.company_key_hash = prospect.company_key_hash AND note_record.subject_type = 'PROSPECT' AND note_record.subject_key = prospect.prospect_key AND note_record.note_status = 'ACTIVE'
         LEFT JOIN project_company_sales_prospect_opportunity prospect_opportunity ON prospect_opportunity.company_key_hash = prospect.company_key_hash AND prospect_opportunity.prospect_key = prospect.prospect_key
         WHERE " . implode(' AND ', $where) . " AND prospect_opportunity.prospect_opportunity_key IS NULL
         GROUP BY prospect.prospect_key, prospect.prospect_code, prospect.prospect_name, prospect.prospect_status
         HAVING note_count > 0 OR lead_count > 0 ORDER BY last_engaged_at DESC, prospect.prospect_code ASC",
        $params
    );
    foreach (is_array($rows) ? $rows : [] as &$row) {
        $row['lead_count'] = (int) $row['lead_count'];
        $row['note_count'] = (int) $row['note_count'];
    }
    unset($row);
    return ['columns' => ['prospect_code', 'prospect_name', 'prospect_status', 'lead_count', 'note_count', 'last_engaged_at'], 'rows' => is_array($rows) ? $rows : [], 'filters' => $filters];
}

function yovel_admin_sales_report_address_contacts(array $company, array $admin, array $input = []): array
{
    $filters = yovel_admin_sales_sc03_report_filters($company, $admin, $input);
    $where = yovel_admin_sales_sc03_lead_where($company, $filters);
    $columns = ['lead_code', 'lead_name', 'email', 'phone', 'mobile', 'address_line', 'city', 'country'];
    $rows = bx_db()->GetAll(
        'SELECT ' . implode(', ', array_map(static fn (string $column): string => 'lead_record.' . $column, $columns)) . " FROM project_company_sales_lead lead_record WHERE {$where['sql']} ORDER BY lead_record.lead_code ASC",
        $where['params']
    );
    return ['columns' => $columns, 'rows' => is_array($rows) ? $rows : [], 'filters' => $filters];
}

function yovel_admin_sales_lead_package_data(array $company, array $admin): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $prospects = $db->GetAll(
        "SELECT prospect.*, COALESCE(segment.segment_name, '') AS market_segment_name, COALESCE(industry.industry_name, '') AS industry_type_name,
                COUNT(DISTINCT prospect_lead.lead_key) AS lead_count, COUNT(DISTINCT prospect_opportunity.opportunity_key) AS opportunity_count
         FROM project_company_sales_prospect prospect
         LEFT JOIN project_company_sales_market_segment segment ON segment.company_key_hash = prospect.company_key_hash AND segment.market_segment_key = prospect.market_segment_key
         LEFT JOIN project_company_sales_industry_type industry ON industry.company_key_hash = prospect.company_key_hash AND industry.industry_type_key = prospect.industry_type_key
         LEFT JOIN project_company_sales_prospect_lead prospect_lead ON prospect_lead.company_key_hash = prospect.company_key_hash AND prospect_lead.prospect_key = prospect.prospect_key
         LEFT JOIN project_company_sales_prospect_opportunity prospect_opportunity ON prospect_opportunity.company_key_hash = prospect.company_key_hash AND prospect_opportunity.prospect_key = prospect.prospect_key
         WHERE prospect.company_key_hash = ? AND prospect.prospect_status <> 'DELETED'
         GROUP BY prospect.prospect_key ORDER BY prospect.updated_at DESC, prospect.prospect_name ASC",
        [$hash]
    );
    foreach (is_array($prospects) ? $prospects : [] as &$prospect) {
        $prospect['lead_count'] = (int) $prospect['lead_count'];
        $prospect['opportunity_count'] = (int) $prospect['opportunity_count'];
        $prospect['lead_keys'] = $db->GetCol('SELECT lead_key FROM project_company_sales_prospect_lead WHERE company_key_hash = ? AND prospect_key = ? ORDER BY lead_key', [$hash, (string) $prospect['prospect_key']]);
    }
    unset($prospect);
    $appointments = $db->GetAll(
        "SELECT appointment.*, COALESCE(lead_record.lead_name, '') AS lead_name, COALESCE(prospect.prospect_name, '') AS prospect_name
         FROM project_company_sales_appointment appointment
         LEFT JOIN project_company_sales_lead lead_record ON lead_record.company_key_hash = appointment.company_key_hash AND lead_record.lead_key = appointment.lead_key
         LEFT JOIN project_company_sales_prospect prospect ON prospect.company_key_hash = appointment.company_key_hash AND prospect.prospect_key = appointment.prospect_key
         WHERE appointment.company_key_hash = ? AND appointment.appointment_status <> 'DELETED'
         ORDER BY appointment.starts_at ASC, appointment.appointment_code ASC",
        [$hash]
    );
    $settings = $db->GetRow('SELECT * FROM project_company_sales_appointment_booking_settings WHERE company_key_hash = ? LIMIT 1', [$hash]);
    if (!$settings) {
        $settings = ['settings_key' => '', 'settings_version' => 0, 'timezone' => 'UTC', 'minimum_notice_minutes' => 0, 'booking_horizon_days' => 30, 'appointment_duration_minutes' => 30];
    }
    return [
        'prospects' => is_array($prospects) ? $prospects : [],
        'appointments' => is_array($appointments) ? $appointments : [],
        'appointment_settings' => $settings,
        'appointment_slots' => $db->GetAll("SELECT * FROM project_company_sales_appointment_booking_slot WHERE company_key_hash = ? AND slot_status <> 'DELETED' ORDER BY weekday_number, start_time", [$hash]) ?: [],
        'market_segments' => $db->GetAll("SELECT * FROM project_company_sales_market_segment WHERE company_key_hash = ? AND segment_status <> 'DELETED' ORDER BY segment_name", [$hash]) ?: [],
        'industry_types' => $db->GetAll("SELECT * FROM project_company_sales_industry_type WHERE company_key_hash = ? AND industry_status <> 'DELETED' ORDER BY industry_name", [$hash]) ?: [],
        'admin_directory' => $db->GetAll("SELECT admin_key, admin_login, admin_name FROM project_company_admin WHERE company_key_hash = ? AND admin_status = 'ACTIVE' ORDER BY admin_name, admin_login", [$hash]) ?: [],
        'conversions' => $db->GetAll('SELECT * FROM project_company_sales_lead_conversion WHERE company_key_hash = ? ORDER BY created_at DESC LIMIT 50', [$hash]) ?: [],
        'conversion_services' => array_map(static fn (array $service): array => ['contract' => $service['contract'], 'status' => $service['status']], yovel_admin_sales_crm_conversion_services()),
        'reports' => [
            'lead-details' => yovel_admin_sales_report_lead_details($company, $admin),
            'lead-conversion-time' => yovel_admin_sales_report_lead_conversion_time($company, $admin),
            'lead-owner-efficiency' => yovel_admin_sales_report_lead_owner_efficiency($company, $admin),
            'prospects-engaged' => yovel_admin_sales_report_prospects_engaged($company, $admin),
            'address-contacts' => yovel_admin_sales_report_address_contacts($company, $admin),
        ],
    ];
}
