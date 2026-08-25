<?php
declare(strict_types=1);

function yovel_admin_manufacturing_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_manufacturing_audit (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            audit_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            record_key VARCHAR(1500) NOT NULL,
            business_key VARCHAR(190) NOT NULL,
            audit_action VARCHAR(40) NOT NULL,
            persisted_values_json LONGTEXT NOT NULL,
            admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mfg_audit_record (company_key_hash, record_type, business_key),
            INDEX idx_mfg_audit_created (company_key_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_manufacturing_setting (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            allow_overproduction_percent DECIMAL(9,4) NOT NULL DEFAULT 0,
            capacity_planning_enabled TINYINT(1) NOT NULL DEFAULT 1,
            default_wip_warehouse_key VARCHAR(1500) NULL,
            default_finished_goods_warehouse_key VARCHAR(1500) NULL,
            notes TEXT NULL,
            setting_status ENUM('ACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_mfg_setting_company (company_key_hash),
            INDEX idx_mfg_setting_status (company_key_hash, setting_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_manufacturing_form (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            form_title VARCHAR(180) NOT NULL,
            form_description VARCHAR(1000) NULL,
            form_status ENUM('DRAFT','PUBLISHED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
            current_form_version_key CHAR(36) NOT NULL,
            version_count INT UNSIGNED NOT NULL DEFAULT 1,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_mfg_form_title (company_key_hash, target_section, form_title),
            INDEX idx_mfg_form_target (company_key_hash, target_section, form_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_manufacturing_form_version (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_version_key CHAR(36) NOT NULL UNIQUE,
            form_key CHAR(36) NOT NULL,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            version_status ENUM('DRAFT','PUBLISHED') NOT NULL,
            schema_json LONGTEXT NOT NULL,
            schema_checksum CHAR(64) NOT NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_mfg_form_version (company_key_hash, form_key, version_number),
            INDEX idx_mfg_form_version_status (company_key_hash, form_key, version_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_manufacturing_form_audit (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_audit_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            form_key CHAR(36) NOT NULL,
            form_version_key CHAR(36) NOT NULL,
            audit_action VARCHAR(40) NOT NULL,
            schema_checksum CHAR(64) NOT NULL,
            admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mfg_form_audit (company_key_hash, form_key, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_manufacturing_record_form_version (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            binding_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            record_key VARCHAR(1500) NOT NULL,
            record_key_hash CHAR(64) NOT NULL,
            form_key CHAR(36) NOT NULL,
            form_version_key CHAR(36) NOT NULL,
            bound_by_admin_key CHAR(36) NOT NULL,
            bound_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_mfg_record_form (company_key_hash, record_type, record_key_hash),
            INDEX idx_mfg_record_form_version (company_key_hash, form_version_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_manufacturing_execute($db, $statement, [], 'Manufacturing schema update');
    }
}
