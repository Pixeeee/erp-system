<?php
declare(strict_types=1);

function yovel_admin_operations_db_execute(ADOConnection $db, string $sql, array $params, string $operation): void
{
    $result = $db->Execute($sql, $params);
    if ($result === false) {
        $databaseError = trim((string) $db->ErrorMsg());
        throw new RuntimeException($operation . ' failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
    }
}

function yovel_admin_operations_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_operations_builder_form (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            builder_form_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            form_title VARCHAR(180) NOT NULL,
            form_description VARCHAR(500) NOT NULL DEFAULT '',
            form_status ENUM('DRAFT','PUBLISHED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
            current_version_key CHAR(36) NOT NULL,
            version_number INT UNSIGNED NOT NULL DEFAULT 1,
            schema_json LONGTEXT NOT NULL,
            schema_checksum CHAR(64) NOT NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_form_title (company_key_hash,target_section,form_title),
            INDEX idx_project_company_operations_form_status (company_key_hash,target_section,form_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_builder_form_version (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_version_key CHAR(36) NOT NULL UNIQUE,
            builder_form_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            form_title VARCHAR(180) NOT NULL,
            form_description VARCHAR(500) NOT NULL DEFAULT '',
            form_status ENUM('DRAFT','PUBLISHED','ARCHIVED') NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            schema_json LONGTEXT NOT NULL,
            schema_checksum CHAR(64) NOT NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_form_version (company_key_hash,builder_form_key,version_number),
            UNIQUE KEY uq_project_company_operations_form_checksum (company_key_hash,builder_form_key,schema_checksum),
            INDEX idx_project_company_operations_version_status (company_key_hash,target_section,form_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_form_submission (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_key CHAR(36) NOT NULL UNIQUE,
            builder_form_key CHAR(36) NOT NULL,
            form_version_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            subject_key VARCHAR(160) NOT NULL,
            values_json LONGTEXT NOT NULL,
            submission_status ENUM('SUBMITTED','ARCHIVED') NOT NULL DEFAULT 'SUBMITTED',
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_submission_subject (company_key_hash,builder_form_key,subject_key),
            INDEX idx_project_company_operations_submission_version (company_key_hash,form_version_key,submission_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_job (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            idempotency_key VARCHAR(160) NOT NULL,
            job_type VARCHAR(40) NOT NULL,
            source_section VARCHAR(80) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            status ENUM('QUEUED','RUNNING','SUCCEEDED','FAILED','CANCELLED') NOT NULL DEFAULT 'QUEUED',
            priority SMALLINT UNSIGNED NOT NULL DEFAULT 50,
            max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
            attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
            progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL,
            leased_by_worker_key VARCHAR(120) NULL,
            lease_expires_at DATETIME NULL,
            result_json LONGTEXT NULL,
            error_summary VARCHAR(1000) NULL,
            cancel_reason VARCHAR(1000) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_job_idempotency (company_key_hash,idempotency_key),
            INDEX idx_project_company_operations_job_claim (company_key_hash,status,available_at,priority,x_id),
            INDEX idx_project_company_operations_job_lease (company_key_hash,status,lease_expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_job_attempt (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attempt_key CHAR(36) NOT NULL UNIQUE,
            job_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            attempt_number TINYINT UNSIGNED NOT NULL,
            worker_key VARCHAR(120) NOT NULL,
            attempt_status ENUM('RUNNING','SUCCEEDED','FAILED','EXPIRED','CANCELLED') NOT NULL,
            lease_expires_at DATETIME NOT NULL,
            error_summary VARCHAR(1000) NULL,
            result_json LONGTEXT NULL,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            UNIQUE KEY uq_project_company_operations_attempt_number (company_key_hash,job_key,attempt_number),
            INDEX idx_project_company_operations_attempt_worker (company_key_hash,worker_key,attempt_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_worker (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            worker_record_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            worker_key VARCHAR(120) NOT NULL,
            worker_status ENUM('IDLE','BUSY','OFFLINE') NOT NULL DEFAULT 'IDLE',
            current_job_key CHAR(36) NULL,
            lease_expires_at DATETIME NULL,
            last_seen_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_worker (company_key_hash,worker_key),
            INDEX idx_project_company_operations_worker_status (company_key_hash,worker_status,last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_schedule (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            schedule_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            schedule_name VARCHAR(180) NOT NULL,
            job_type VARCHAR(40) NOT NULL,
            cron_expression VARCHAR(120) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            schedule_status ENUM('ACTIVE','PAUSED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_schedule_name (company_key_hash,schedule_name),
            INDEX idx_project_company_operations_schedule_status (company_key_hash,schedule_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_sync_conflict (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            conflict_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            source_contract VARCHAR(160) NOT NULL,
            owner_record_key VARCHAR(160) NOT NULL,
            local_snapshot_json LONGTEXT NOT NULL,
            remote_snapshot_json LONGTEXT NOT NULL,
            summary VARCHAR(1000) NOT NULL,
            status ENUM('OPEN','RESOLVED','DISMISSED') NOT NULL DEFAULT 'OPEN',
            resolution VARCHAR(40) NULL,
            resolution_note VARCHAR(1000) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            resolved_by_admin_key CHAR(36) NULL,
            resolved_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_company_operations_conflict_status (company_key_hash,status,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_system_alert (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            alert_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            alert_code VARCHAR(120) NOT NULL,
            severity ENUM('INFO','WARNING','CRITICAL') NOT NULL,
            title VARCHAR(180) NOT NULL,
            summary VARCHAR(1000) NOT NULL,
            status ENUM('ACTIVE','ACKNOWLEDGED','RESOLVED') NOT NULL DEFAULT 'ACTIVE',
            acknowledgement_note VARCHAR(1000) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            acknowledged_by_admin_key CHAR(36) NULL,
            acknowledged_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_alert_code (company_key_hash,alert_code),
            INDEX idx_project_company_operations_alert_status (company_key_hash,status,severity,updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_release_check (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            release_check_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            check_code VARCHAR(120) NOT NULL,
            check_label VARCHAR(180) NOT NULL,
            status ENUM('PASS','WARN','FAIL') NOT NULL,
            evidence_summary VARCHAR(2000) NOT NULL,
            checked_by_admin_key CHAR(36) NOT NULL,
            checked_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_company_operations_release_code (company_key_hash,check_code,checked_at),
            INDEX idx_project_company_operations_release_status (company_key_hash,status,checked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_bulk_log (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bulk_log_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            idempotency_key VARCHAR(160) NOT NULL,
            job_key CHAR(36) NOT NULL,
            record_type VARCHAR(160) NOT NULL,
            action_code VARCHAR(80) NOT NULL,
            requested_count INT UNSIGNED NOT NULL,
            completed_count INT UNSIGNED NOT NULL DEFAULT 0,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('QUEUED','RUNNING','FAILED','COMPLETED','CANCELLED') NOT NULL,
            evidence_summary VARCHAR(2000) NOT NULL,
            cancel_reason VARCHAR(1000) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_bulk_idempotency (company_key_hash,idempotency_key),
            INDEX idx_project_company_operations_bulk_status (company_key_hash,status,updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_bulk_log_detail (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            detail_key CHAR(36) NOT NULL UNIQUE,
            bulk_log_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            sequence_no INT UNSIGNED NOT NULL,
            owner_contract VARCHAR(160) NOT NULL,
            target_record_key VARCHAR(160) NOT NULL,
            target_idempotency_key VARCHAR(160) NOT NULL,
            action_status ENUM('PENDING','COMPLETED','FAILED','CANCELLED') NOT NULL,
            owner_record_key VARCHAR(160) NULL,
            owner_status VARCHAR(80) NULL,
            read_back_json LONGTEXT NULL,
            owner_audit_key VARCHAR(160) NULL,
            error_summary VARCHAR(1000) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_bulk_sequence (company_key_hash,bulk_log_key,sequence_no),
            UNIQUE KEY uq_project_company_operations_bulk_target_idempotency (company_key_hash,target_idempotency_key),
            INDEX idx_project_company_operations_bulk_detail_status (company_key_hash,bulk_log_key,action_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_deletion_request (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            idempotency_key VARCHAR(160) NOT NULL,
            job_key CHAR(36) NOT NULL,
            plan_hash CHAR(64) NOT NULL,
            record_type VARCHAR(160) NOT NULL,
            target_count INT UNSIGNED NOT NULL,
            completed_count INT UNSIGNED NOT NULL DEFAULT 0,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            consequence VARCHAR(2000) NOT NULL,
            status ENUM('QUEUED','RUNNING','FAILED','COMPLETED','CANCELLED') NOT NULL,
            cancel_reason VARCHAR(1000) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_delete_idempotency (company_key_hash,idempotency_key),
            INDEX idx_project_company_operations_delete_status (company_key_hash,status,updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_deletion_item (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_key CHAR(36) NOT NULL UNIQUE,
            request_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            owner_contract VARCHAR(160) NOT NULL,
            record_type VARCHAR(160) NOT NULL,
            target_count INT UNSIGNED NOT NULL,
            item_status ENUM('PENDING','COMPLETED','FAILED','CANCELLED') NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_delete_item (company_key_hash,request_key,owner_contract,record_type),
            INDEX idx_project_company_operations_delete_item_status (company_key_hash,request_key,item_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_deletion_target (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            target_key CHAR(36) NOT NULL UNIQUE,
            request_key CHAR(36) NOT NULL,
            item_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            sequence_no INT UNSIGNED NOT NULL,
            owner_contract VARCHAR(160) NOT NULL,
            owner_record_key VARCHAR(160) NOT NULL,
            target_idempotency_key VARCHAR(160) NOT NULL,
            target_status ENUM('PENDING','COMPLETED','FAILED','CANCELLED') NOT NULL,
            owner_status VARCHAR(80) NULL,
            read_back_json LONGTEXT NULL,
            owner_audit_key VARCHAR(160) NULL,
            error_summary VARCHAR(1000) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_delete_sequence (company_key_hash,request_key,sequence_no),
            UNIQUE KEY uq_project_company_operations_delete_target_idempotency (company_key_hash,target_idempotency_key),
            INDEX idx_project_company_operations_delete_target_status (company_key_hash,request_key,target_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_operations_authorization_policy (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            policy_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            policy_name VARCHAR(180) NOT NULL,
            lifecycle_key VARCHAR(160) NOT NULL,
            document_type VARCHAR(160) NOT NULL,
            action_code VARCHAR(80) NOT NULL,
            threshold_amount DECIMAL(18,6) NOT NULL DEFAULT 0,
            currency_key VARCHAR(160) NOT NULL DEFAULT '',
            approver_user_key VARCHAR(160) NOT NULL,
            approver_role_key VARCHAR(160) NOT NULL DEFAULT '',
            approver_employee_key VARCHAR(160) NOT NULL DEFAULT '',
            policy_status ENUM('ACTIVE','INACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_operations_policy_name (company_key_hash,policy_name),
            INDEX idx_project_company_operations_policy_status (company_key_hash,policy_status,document_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        yovel_admin_operations_db_execute($db, $statement, [], 'Operations schema update');
    }
}

function yovel_admin_operations_scope(array $company, array $admin): array
{
    if (isset($admin['admin_status']) && strtoupper(trim((string) $admin['admin_status'])) !== 'ACTIVE') {
        throw new InvalidArgumentException('An authorized active administrator is required for Operations writes.');
    }
    return yovel_admin_operations_contract_scope($company, $admin);
}

function yovel_admin_operations_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function yovel_admin_operations_json_array(string $value, string $label): array
{
    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException($label . ' must be a JSON object or array.');
    }
    return $decoded;
}

function yovel_admin_operations_audit(
    string $action,
    string $module,
    string $recordKey,
    string $companyKeyHash,
    string $adminKey,
    array $values,
    string $reason
): void {
    bx_audit($action, $module, $recordKey, [
        'company_key_hash' => $companyKeyHash,
        'admin_key' => $adminKey,
        ...$values,
    ], $reason);
}
