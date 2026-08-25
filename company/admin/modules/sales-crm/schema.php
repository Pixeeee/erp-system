<?php
declare(strict_types=1);

function yovel_admin_sales_crm_ensure_schema(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_form_schema (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_schema_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            module_code VARCHAR(80) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            schema_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            schema_version INT UNSIGNED NOT NULL DEFAULT 1,
            schema_json LONGTEXT NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_form_schema_version (company_key_hash, module_code, record_type, schema_version),
            INDEX idx_project_company_form_schema_active (company_key_hash, module_code, record_type, schema_status, schema_version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_form_schema_audit (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_schema_audit_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            form_schema_key CHAR(36) NOT NULL,
            module_code VARCHAR(80) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            audit_action VARCHAR(40) NOT NULL,
            previous_schema_json LONGTEXT NULL,
            next_schema_json LONGTEXT NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_company_form_schema_audit_form (company_key_hash, form_schema_key),
            INDEX idx_project_company_form_schema_audit_record (company_key_hash, module_code, record_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_campaign (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            campaign_code VARCHAR(80) NOT NULL,
            campaign_name VARCHAR(180) NOT NULL,
            campaign_status ENUM('DRAFT','ACTIVE','INACTIVE','COMPLETED','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_campaign_code (company_key_hash, campaign_code),
            INDEX idx_project_company_sales_campaign_status (company_key_hash, campaign_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_campaign_email_schedule (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_email_schedule_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            campaign_key CHAR(36) NOT NULL,
            idempotency_key CHAR(36) NULL,
            schedule_code VARCHAR(80) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            recipient_segment VARCHAR(180) NOT NULL DEFAULT '',
            scheduled_at DATETIME NOT NULL,
            send_status ENUM('DRAFT','SCHEDULED','SENT','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_campaign_schedule_code (company_key_hash, campaign_key, schedule_code),
            UNIQUE KEY uq_project_company_sales_campaign_schedule_idempotency (company_key_hash, idempotency_key),
            INDEX idx_project_company_sales_campaign_schedule_due (company_key_hash, send_status, scheduled_at),
            INDEX idx_project_company_sales_campaign_schedule_campaign (company_key_hash, campaign_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_customer (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            customer_code VARCHAR(80) NOT NULL,
            customer_name VARCHAR(200) NOT NULL,
            customer_status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_customer_code (company_key_hash, customer_code),
            INDEX idx_project_company_sales_customer_status (company_key_hash, customer_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_territory (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            territory_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            territory_code VARCHAR(80) NOT NULL,
            territory_name VARCHAR(180) NOT NULL,
            territory_status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_territory_code (company_key_hash, territory_code),
            INDEX idx_project_company_sales_territory_status (company_key_hash, territory_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_salesperson (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            salesperson_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            salesperson_code VARCHAR(80) NOT NULL,
            salesperson_name VARCHAR(180) NOT NULL,
            salesperson_status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_salesperson_code (company_key_hash, salesperson_code),
            INDEX idx_project_company_salesperson_status (company_key_hash, salesperson_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_lead (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lead_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            lead_code VARCHAR(80) NOT NULL,
            lead_name VARCHAR(200) NOT NULL,
            organization_name VARCHAR(200) NULL,
            lead_status ENUM('DRAFT','OPEN','QUALIFIED','CONVERTED','LOST','INACTIVE','DELETED') NOT NULL DEFAULT 'OPEN',
            lead_source VARCHAR(120) NULL,
            campaign_key CHAR(36) NULL,
            territory_key CHAR(36) NULL,
            salesperson_key CHAR(36) NULL,
            email VARCHAR(180) NULL,
            phone VARCHAR(80) NULL,
            mobile VARCHAR(80) NULL,
            website VARCHAR(220) NULL,
            industry VARCHAR(120) NULL,
            estimated_value DECIMAL(15,2) NULL,
            next_contact_date DATE NULL,
            notes TEXT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_lead_code (company_key_hash, lead_code),
            INDEX idx_project_company_sales_lead_status (company_key_hash, lead_status),
            INDEX idx_project_company_sales_lead_source (company_key_hash, lead_source),
            INDEX idx_project_company_sales_lead_campaign (company_key_hash, campaign_key),
            INDEX idx_project_company_sales_lead_territory (company_key_hash, territory_key),
            INDEX idx_project_company_sales_lead_salesperson (company_key_hash, salesperson_key),
            INDEX idx_project_company_sales_lead_updated (company_key_hash, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_crm_settings (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            settings_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            settings_version INT UNSIGNED NOT NULL DEFAULT 1,
            crm_enabled TINYINT(1) NOT NULL DEFAULT 1,
            selling_enabled TINYINT(1) NOT NULL DEFAULT 1,
            restrict_to_allowed_users TINYINT(1) NOT NULL DEFAULT 0,
            default_lead_status ENUM('DRAFT','OPEN','QUALIFIED') NOT NULL DEFAULT 'OPEN',
            default_opportunity_stage VARCHAR(120) NOT NULL DEFAULT 'Qualification',
            default_customer_group VARCHAR(120) NOT NULL DEFAULT '',
            default_price_list VARCHAR(120) NOT NULL DEFAULT '',
            allow_duplicate_lead_email TINYINT(1) NOT NULL DEFAULT 0,
            validate_selling_price TINYINT(1) NOT NULL DEFAULT 0,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_crm_settings_company (company_key_hash),
            INDEX idx_project_company_sales_crm_settings_updated (company_key_hash, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_crm_allowed_user (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            allowed_user_key CHAR(36) NOT NULL UNIQUE,
            settings_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            admin_key CHAR(36) NOT NULL,
            allow_crm TINYINT(1) NOT NULL DEFAULT 0,
            allow_selling TINYINT(1) NOT NULL DEFAULT 0,
            manage_settings TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_crm_allowed_admin (company_key_hash, admin_key),
            INDEX idx_project_company_sales_crm_allowed_settings (company_key_hash, settings_key),
            INDEX idx_project_company_sales_crm_allowed_scope (company_key_hash, allow_crm, allow_selling, manage_settings)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_sales_crm_preference (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            preference_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            admin_key CHAR(36) NOT NULL,
            preference_version INT UNSIGNED NOT NULL DEFAULT 1,
            setup_dismissed TINYINT(1) NOT NULL DEFAULT 0,
            tour_status ENUM('NEW','DISMISSED','COMPLETED') NOT NULL DEFAULT 'NEW',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_sales_crm_preference_admin (company_key_hash, admin_key),
            INDEX idx_project_company_sales_crm_preference_state (company_key_hash, tour_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Sales/CRM schema update');
    }
    bx_add_column_if_missing('project_company_sales_campaign', 'campaign_version', "INT UNSIGNED NOT NULL DEFAULT 1 AFTER campaign_status");
    bx_add_column_if_missing('project_company_sales_campaign', 'campaign_type', "VARCHAR(120) NOT NULL DEFAULT '' AFTER campaign_version");
    bx_add_column_if_missing('project_company_sales_campaign', 'start_date', 'DATE NULL AFTER campaign_type');
    bx_add_column_if_missing('project_company_sales_campaign', 'end_date', 'DATE NULL AFTER start_date');
    bx_add_column_if_missing('project_company_sales_campaign', 'budget', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER end_date');
    bx_add_column_if_missing('project_company_sales_campaign', 'expected_revenue', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER budget');
    bx_add_column_if_missing('project_company_sales_campaign', 'notes', 'TEXT NULL AFTER expected_revenue');
    bx_add_column_if_missing('project_company_sales_campaign', 'idempotency_key', 'CHAR(36) NULL AFTER notes');
    bx_add_column_if_missing('project_company_sales_campaign', 'created_by_admin_key', 'CHAR(36) NULL AFTER idempotency_key');
    bx_add_column_if_missing('project_company_sales_campaign', 'updated_by_admin_key', 'CHAR(36) NULL AFTER created_by_admin_key');
    bx_add_column_if_missing('project_company_sales_campaign_email_schedule', 'idempotency_key', 'CHAR(36) NULL AFTER campaign_key');
    bx_add_unique_index_if_missing(
        'project_company_sales_campaign',
        'idempotency_key',
        'uq_project_company_sales_campaign_idempotency',
        'UNIQUE KEY uq_project_company_sales_campaign_idempotency (company_key_hash, idempotency_key)'
    );
    bx_add_index_if_missing(
        'project_company_sales_campaign',
        'idx_project_company_sales_campaign_dates',
        'INDEX idx_project_company_sales_campaign_dates (company_key_hash, start_date, end_date)'
    );
    bx_add_unique_index_if_missing(
        'project_company_sales_campaign_email_schedule',
        'idempotency_key',
        'uq_project_company_sales_campaign_schedule_idempotency',
        'UNIQUE KEY uq_project_company_sales_campaign_schedule_idempotency (company_key_hash, idempotency_key)'
    );
    $ready = true;
}
