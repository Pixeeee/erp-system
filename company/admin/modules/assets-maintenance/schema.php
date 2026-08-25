<?php
declare(strict_types=1);

function yovel_admin_assets_maintenance_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_asset_form (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            form_title VARCHAR(180) NOT NULL,
            form_description TEXT NOT NULL,
            form_status ENUM('DRAFT','PUBLISHED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
            current_version_key CHAR(36) NOT NULL,
            published_version_key CHAR(36) NULL,
            created_by_admin_key VARCHAR(191) NOT NULL,
            updated_by_admin_key VARCHAR(191) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_asset_form_title (company_key_hash, target_section, form_title),
            INDEX idx_project_company_asset_form_scope (company_key_hash, target_section, form_status),
            INDEX idx_project_company_asset_form_current (company_key_hash, current_version_key),
            INDEX idx_project_company_asset_form_published (company_key_hash, published_version_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_asset_form_version (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_version_key CHAR(36) NOT NULL UNIQUE,
            form_key CHAR(36) NOT NULL,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            form_title VARCHAR(180) NOT NULL,
            form_description TEXT NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            version_status ENUM('DRAFT','PUBLISHED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
            schema_json LONGTEXT NOT NULL,
            schema_checksum CHAR(64) NOT NULL,
            version_checksum CHAR(64) NOT NULL,
            created_by_admin_key VARCHAR(191) NOT NULL,
            published_by_admin_key VARCHAR(191) NULL,
            published_at TIMESTAMP NULL,
            archived_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_asset_form_version (company_key_hash, form_key, version_number),
            UNIQUE KEY uq_project_company_asset_form_version_checksum (company_key_hash, form_key, version_checksum),
            INDEX idx_project_company_asset_form_version_scope (company_key_hash, form_key, version_status, version_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_asset_form_submission (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            form_key CHAR(36) NOT NULL,
            form_version_key CHAR(36) NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            subject_owner_key VARCHAR(1500) NULL,
            submission_status ENUM('SUBMITTED','ARCHIVED') NOT NULL DEFAULT 'SUBMITTED',
            values_json LONGTEXT NOT NULL,
            values_checksum CHAR(64) NOT NULL,
            created_by_admin_key VARCHAR(191) NOT NULL,
            updated_by_admin_key VARCHAR(191) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_company_asset_submission_form (company_key_hash, form_key, form_version_key, submission_status),
            INDEX idx_project_company_asset_submission_subject (company_key_hash, subject_owner_key(191), submission_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_asset_form_audit (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            audit_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            form_key CHAR(36) NOT NULL,
            form_version_key CHAR(36) NULL,
            submission_key CHAR(36) NULL,
            audit_action VARCHAR(40) NOT NULL,
            details_json LONGTEXT NOT NULL,
            created_by_admin_key VARCHAR(191) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_company_asset_form_audit_scope (company_key_hash, form_key, created_at),
            INDEX idx_project_company_asset_form_audit_version (company_key_hash, form_version_key),
            INDEX idx_project_company_asset_form_audit_submission (company_key_hash, submission_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        yovel_admin_assets_execute($db, $statement, [], 'Assets / Maintenance schema update');
    }
}
