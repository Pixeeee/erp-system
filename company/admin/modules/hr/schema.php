<?php
declare(strict_types=1);

function yovel_admin_hr_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_hr_job_position (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_position_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            job_position_code VARCHAR(80) NOT NULL,
            job_position_name VARCHAR(160) NOT NULL,
            job_position_description TEXT NULL,
            job_position_status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_job_position_code (company_key_hash, job_position_code),
            INDEX idx_project_company_hr_job_position_company (company_key_hash),
            INDEX idx_project_company_hr_job_position_status (company_key_hash, job_position_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_team (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            team_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            team_code VARCHAR(80) NOT NULL,
            team_name VARCHAR(160) NOT NULL,
            team_description TEXT NULL,
            team_status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_team_code (company_key_hash, team_code),
            INDEX idx_project_company_hr_team_company (company_key_hash),
            INDEX idx_project_company_hr_team_status (company_key_hash, team_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_employee (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            employee_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_code VARCHAR(80) NOT NULL,
            first_name VARCHAR(120) NOT NULL,
            middle_name VARCHAR(120) NULL,
            last_name VARCHAR(120) NULL,
            employee_name VARCHAR(200) NOT NULL,
            employee_status ENUM('DRAFT','ACTIVE','INACTIVE','ON_LEAVE','SEPARATED','DELETED') NOT NULL DEFAULT 'ACTIVE',
            branch_key CHAR(36) NULL,
            department_key CHAR(36) NULL,
            job_position_key CHAR(36) NULL,
            team_key CHAR(36) NULL,
            reports_to_employee_key CHAR(36) NULL,
            date_of_birth DATE NULL,
            date_of_joining DATE NULL,
            employment_type VARCHAR(80) NULL,
            mobile_number VARCHAR(60) NULL,
            company_email VARCHAR(160) NULL,
            personal_email VARCHAR(160) NULL,
            employee_notes TEXT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_employee_code (company_key_hash, employee_code),
            INDEX idx_project_company_hr_employee_company (company_key_hash),
            INDEX idx_project_company_hr_employee_status (company_key_hash, employee_status),
            INDEX idx_project_company_hr_employee_branch (company_key_hash, branch_key),
            INDEX idx_project_company_hr_employee_department (company_key_hash, department_key),
            INDEX idx_project_company_hr_employee_job_position (company_key_hash, job_position_key),
            INDEX idx_project_company_hr_employee_team (company_key_hash, team_key),
            INDEX idx_project_company_hr_employee_manager (company_key_hash, reports_to_employee_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_form_field (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            field_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            form_key VARCHAR(80) NOT NULL,
            field_name VARCHAR(80) NOT NULL,
            field_label VARCHAR(160) NOT NULL,
            field_type ENUM('TEXT','TEXTAREA','DATE','NUMBER','EMAIL','PHONE','SELECT','CHECKBOX') NOT NULL DEFAULT 'TEXT',
            field_section VARCHAR(80) NOT NULL DEFAULT 'overview',
            field_placeholder VARCHAR(200) NULL,
            field_help TEXT NULL,
            field_options TEXT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            is_core TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            field_status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_form_field_name (company_key_hash, form_key, field_name),
            INDEX idx_project_company_hr_form_field_form (company_key_hash, form_key, field_status),
            INDEX idx_project_company_hr_form_field_sort (company_key_hash, form_key, field_section, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_custom_value (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            value_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            form_key VARCHAR(80) NOT NULL,
            record_key CHAR(36) NOT NULL,
            field_name VARCHAR(80) NOT NULL,
            field_value TEXT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_custom_value (company_key_hash, form_key, record_key, field_name),
            INDEX idx_project_company_hr_custom_value_record (company_key_hash, form_key, record_key),
            INDEX idx_project_company_hr_custom_value_field (company_key_hash, form_key, field_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_builder_form (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            builder_form_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            form_title VARCHAR(180) NOT NULL,
            form_description TEXT NULL,
            form_status ENUM('DRAFT','ACTIVE','ARCHIVED','DELETED') NOT NULL DEFAULT 'DRAFT',
            schema_json LONGTEXT NOT NULL,
            question_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_company_hr_builder_form_target (company_key_hash, target_section, form_status),
            INDEX idx_project_company_hr_builder_form_updated (company_key_hash, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_employee_assignment (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            assignment_key CHAR(36) NOT NULL UNIQUE,
            migration_key VARCHAR(120) NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_key CHAR(36) NOT NULL,
            branch_key CHAR(36) NULL,
            project_key CHAR(36) NULL,
            department_key CHAR(36) NULL,
            job_position_key CHAR(36) NULL,
            team_key CHAR(36) NULL,
            reports_to_employee_key CHAR(36) NULL,
            assignment_status ENUM('ACTIVE','ENDED','DELETED') NOT NULL DEFAULT 'ACTIVE',
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            effective_from DATE NULL,
            effective_until DATE NULL,
            assignment_notes TEXT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_assignment_migration (migration_key),
            INDEX idx_project_company_hr_assignment_employee (company_key_hash, employee_key, assignment_status, is_primary),
            INDEX idx_project_company_hr_assignment_scope (company_key_hash, branch_key, project_key, assignment_status),
            INDEX idx_project_company_hr_assignment_effective (employee_key, effective_from, effective_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_builder_form_version (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_version_key CHAR(36) NOT NULL UNIQUE,
            builder_form_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            form_title VARCHAR(180) NOT NULL,
            form_description TEXT NULL,
            form_status ENUM('DRAFT','ACTIVE','ARCHIVED') NOT NULL,
            schema_json LONGTEXT NOT NULL,
            schema_checksum CHAR(64) NOT NULL,
            question_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_builder_version_number (builder_form_key, version_number),
            UNIQUE KEY uq_project_company_hr_builder_version_checksum (builder_form_key, schema_checksum),
            INDEX idx_project_company_hr_builder_version_company (company_key_hash, builder_form_key, version_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_form_submission (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_key CHAR(36) NOT NULL UNIQUE,
            company_key_hash CHAR(64) NOT NULL,
            branch_key CHAR(36) NULL,
            project_key CHAR(36) NULL,
            module_group_key CHAR(36) NULL,
            module_key CHAR(36) NULL,
            module_form_key CHAR(36) NULL,
            builder_form_key CHAR(36) NOT NULL,
            form_version_key CHAR(36) NOT NULL,
            subject_type VARCHAR(80) NOT NULL,
            subject_key CHAR(36) NOT NULL,
            submission_status ENUM('DRAFT','SUBMITTED','VOID') NOT NULL DEFAULT 'DRAFT',
            submitted_by_admin_key CHAR(36) NULL,
            submitted_by_user_key CHAR(36) NULL,
            submitted_at DATETIME NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_form_submission_scope (company_key_hash, project_key, module_key, module_form_key, submission_status),
            INDEX idx_project_form_submission_subject (company_key_hash, subject_type, subject_key, submission_status),
            INDEX idx_project_form_submission_builder (company_key_hash, builder_form_key, form_version_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_form_submission_value (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_value_key CHAR(36) NOT NULL UNIQUE,
            submission_key CHAR(36) NOT NULL,
            question_key VARCHAR(120) NOT NULL,
            field_type ENUM('SHORT_TEXT','PARAGRAPH','DROPDOWN','CHECKBOXES','DATE','NUMBER','EMAIL','PHONE') NOT NULL,
            value_text TEXT NULL,
            value_number DECIMAL(20,6) NULL,
            value_date DATE NULL,
            value_json LONGTEXT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_form_submission_question (submission_key, question_key),
            INDEX idx_project_form_submission_value_submission (submission_key, field_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_setting (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hr_setting_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_naming_mode ENUM('SERIES','MANUAL') NOT NULL DEFAULT 'SERIES',
            employee_number_prefix VARCHAR(40) NOT NULL DEFAULT 'HR-EMP-',
            default_retirement_age SMALLINT UNSIGNED NOT NULL DEFAULT 60,
            allow_employee_self_service TINYINT(1) NOT NULL DEFAULT 0,
            settings_json LONGTEXT NOT NULL,
            setting_status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_setting_company (company_key_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_setup_master (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setup_master_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            record_type ENUM('EMPLOYMENT_TYPE','EMPLOYEE_GRADE','GRIEVANCE_TYPE','INTEREST','EMPLOYEE_HEALTH_INSURANCE') NOT NULL,
            record_code VARCHAR(80) NOT NULL,
            record_name VARCHAR(160) NOT NULL,
            record_description TEXT NULL,
            record_status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            metadata_json LONGTEXT NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_setup_master_code (company_key_hash, record_type, record_code),
            INDEX idx_project_company_hr_setup_master_type (company_key_hash, record_type, record_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_employee_lifecycle (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lifecycle_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            record_type ENUM('TRANSFER','PROMOTION','EMPLOYEE_GRIEVANCE','EMPLOYEE_HEALTH_INSURANCE','DEPARTMENT_APPROVER','APPRAISEE') NOT NULL,
            employee_key CHAR(36) NULL,
            related_employee_key CHAR(36) NULL,
            department_key CHAR(36) NULL,
            branch_key CHAR(36) NULL,
            project_key CHAR(36) NULL,
            job_position_key CHAR(36) NULL,
            team_key CHAR(36) NULL,
            setup_master_key CHAR(36) NULL,
            effective_date DATE NOT NULL,
            record_status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','ACTIVE','INACTIVE','OPEN','INVESTIGATED','RESOLVED','INVALID','CANCELLED','DELETED') NOT NULL DEFAULT 'DRAFT',
            reason TEXT NULL,
            payload_json LONGTEXT NOT NULL,
            immutable_checksum CHAR(64) NOT NULL,
            submitted_by_admin_key CHAR(36) NULL,
            submitted_at DATETIME NULL,
            approved_by_admin_key CHAR(36) NULL,
            approved_at DATETIME NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_company_hr_lifecycle_employee (company_key_hash, employee_key, record_type, effective_date),
            INDEX idx_project_company_hr_lifecycle_status (company_key_hash, record_type, record_status),
            INDEX idx_project_company_hr_lifecycle_department (company_key_hash, department_key, record_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_employee_property_history (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            property_history_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_key CHAR(36) NOT NULL,
            source_lifecycle_key CHAR(36) NOT NULL,
            property_label VARCHAR(160) NOT NULL,
            property_name VARCHAR(80) NOT NULL,
            previous_value TEXT NULL,
            new_value TEXT NULL,
            effective_date DATE NOT NULL,
            immutable_checksum CHAR(64) NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_property_source (source_lifecycle_key, property_name),
            INDEX idx_project_company_hr_property_employee (company_key_hash, employee_key, effective_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_shift_location (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shift_location_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            location_name VARCHAR(160) NOT NULL,
            checkin_radius INT UNSIGNED NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            location_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_shift_location_name (company_key_hash, location_name),
            INDEX idx_project_company_hr_shift_location_status (company_key_hash, location_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_overtime_type (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            overtime_type_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            overtime_code VARCHAR(80) NOT NULL,
            overtime_name VARCHAR(160) NOT NULL,
            calculation_method ENUM('SALARY_COMPONENT_BASED','FIXED_HOURLY_RATE') NOT NULL DEFAULT 'SALARY_COMPONENT_BASED',
            salary_component_reference VARCHAR(120) NULL,
            applicable_salary_components_json LONGTEXT NOT NULL,
            hourly_rate DECIMAL(20,6) NULL,
            standard_multiplier DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
            weekend_multiplier DECIMAL(10,4) NULL,
            public_holiday_multiplier DECIMAL(10,4) NULL,
            maximum_hours DECIMAL(10,2) NULL,
            overtime_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_overtime_type_code (company_key_hash, overtime_code),
            INDEX idx_project_company_hr_overtime_type_status (company_key_hash, overtime_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_overtime_salary_component (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            overtime_salary_component_key CHAR(36) NOT NULL UNIQUE,
            overtime_type_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            salary_component_reference VARCHAR(120) NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_overtime_salary_component (overtime_type_key, salary_component_reference),
            INDEX idx_project_company_hr_overtime_salary_component_company (company_key_hash, overtime_type_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_shift_type (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shift_type_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            shift_code VARCHAR(80) NOT NULL,
            shift_name VARCHAR(160) NOT NULL,
            timezone_name VARCHAR(80) NOT NULL DEFAULT 'UTC',
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            crosses_midnight TINYINT(1) NOT NULL DEFAULT 0,
            checkin_pairing_mode ENUM('ALTERNATING','LOG_TYPE') NOT NULL DEFAULT 'LOG_TYPE',
            working_hours_mode ENUM('FIRST_LAST','EVERY_PAIR') NOT NULL DEFAULT 'FIRST_LAST',
            begin_checkin_before_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
            allow_checkout_after_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
            late_entry_grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            early_exit_grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            half_day_threshold_hours DECIMAL(10,2) NULL,
            absent_threshold_hours DECIMAL(10,2) NULL,
            enable_auto_attendance TINYINT(1) NOT NULL DEFAULT 0,
            process_attendance_after DATE NULL,
            last_sync_at_utc DATETIME NULL,
            allow_overtime TINYINT(1) NOT NULL DEFAULT 0,
            overtime_type_key CHAR(36) NULL,
            shift_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_shift_type_code (company_key_hash, shift_code),
            INDEX idx_project_company_hr_shift_type_status (company_key_hash, shift_status),
            INDEX idx_project_company_hr_shift_type_overtime (company_key_hash, overtime_type_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_shift_schedule (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shift_schedule_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            schedule_code VARCHAR(80) NOT NULL,
            schedule_name VARCHAR(160) NOT NULL,
            shift_type_key CHAR(36) NOT NULL,
            frequency_weeks TINYINT UNSIGNED NOT NULL DEFAULT 1,
            repeat_days_json LONGTEXT NOT NULL,
            schedule_status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_shift_schedule_code (company_key_hash, schedule_code),
            INDEX idx_project_company_hr_shift_schedule_type (company_key_hash, shift_type_key, schedule_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_shift_assignment (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shift_assignment_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_key CHAR(36) NOT NULL,
            shift_type_key CHAR(36) NOT NULL,
            shift_schedule_key CHAR(36) NULL,
            shift_location_key CHAR(36) NULL,
            shift_request_key CHAR(36) NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            assignment_status ENUM('ACTIVE','INACTIVE','CANCELLED','DELETED') NOT NULL DEFAULT 'ACTIVE',
            source_system VARCHAR(40) NOT NULL DEFAULT 'MANUAL',
            source_reference VARCHAR(160) NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_shift_assignment_source (company_key_hash, source_system, source_reference),
            INDEX idx_project_company_hr_shift_assignment_employee (company_key_hash, employee_key, start_date, end_date, assignment_status),
            INDEX idx_project_company_hr_shift_assignment_schedule (company_key_hash, shift_schedule_key, assignment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_shift_request (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shift_request_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_key CHAR(36) NOT NULL,
            shift_type_key CHAR(36) NOT NULL,
            from_date DATE NOT NULL,
            to_date DATE NULL,
            request_status ENUM('DRAFT','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            approver_admin_key CHAR(36) NOT NULL,
            reason TEXT NULL,
            approved_by_admin_key CHAR(36) NULL,
            approved_at DATETIME NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_company_hr_shift_request_employee (company_key_hash, employee_key, from_date, request_status),
            INDEX idx_project_company_hr_shift_request_approver (company_key_hash, approver_admin_key, request_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_employee_checkin (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checkin_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_key CHAR(36) NOT NULL,
            log_type ENUM('IN','OUT') NOT NULL,
            checkin_at_utc DATETIME NOT NULL,
            checkin_at_local DATETIME NOT NULL,
            timezone_name VARCHAR(80) NOT NULL,
            device_id VARCHAR(120) NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            shift_type_key CHAR(36) NULL,
            shift_assignment_key CHAR(36) NULL,
            attendance_key CHAR(36) NULL,
            skip_auto_attendance TINYINT(1) NOT NULL DEFAULT 0,
            offshift TINYINT(1) NOT NULL DEFAULT 0,
            source_system VARCHAR(40) NOT NULL DEFAULT 'MANUAL',
            source_reference VARCHAR(160) NOT NULL,
            immutable_checksum CHAR(64) NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_checkin_source (company_key_hash, source_system, source_reference),
            INDEX idx_project_company_hr_checkin_employee_time (company_key_hash, employee_key, checkin_at_utc),
            INDEX idx_project_company_hr_checkin_attendance (company_key_hash, attendance_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_attendance_request (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attendance_request_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_key CHAR(36) NOT NULL,
            from_date DATE NOT NULL,
            to_date DATE NOT NULL,
            half_day TINYINT(1) NOT NULL DEFAULT 0,
            half_day_date DATE NULL,
            reason ENUM('WORK_FROM_HOME','ON_DUTY') NOT NULL,
            explanation TEXT NULL,
            include_holidays TINYINT(1) NOT NULL DEFAULT 0,
            shift_type_key CHAR(36) NULL,
            request_status ENUM('DRAFT','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            approved_by_admin_key CHAR(36) NULL,
            approved_at DATETIME NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_company_hr_attendance_request_employee (company_key_hash, employee_key, from_date, to_date, request_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_attendance (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attendance_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_key CHAR(36) NOT NULL,
            attendance_date DATE NOT NULL,
            attendance_status ENUM('PRESENT','ABSENT','ON_LEAVE','HALF_DAY','WORK_FROM_HOME') NOT NULL,
            working_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
            in_time_utc DATETIME NULL,
            out_time_utc DATETIME NULL,
            shift_type_key CHAR(36) NULL,
            shift_assignment_key CHAR(36) NULL,
            attendance_request_key CHAR(36) NULL,
            late_entry TINYINT(1) NOT NULL DEFAULT 0,
            early_exit TINYINT(1) NOT NULL DEFAULT 0,
            source_mode ENUM('MANUAL','AUTO','REQUEST') NOT NULL DEFAULT 'MANUAL',
            source_reference VARCHAR(160) NOT NULL,
            source_references_json LONGTEXT NOT NULL,
            source_checksum CHAR(64) NOT NULL,
            remarks TEXT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_attendance_day (company_key_hash, employee_key, attendance_date),
            UNIQUE KEY uq_project_company_hr_attendance_source (company_key_hash, source_mode, source_reference),
            INDEX idx_project_company_hr_attendance_status (company_key_hash, attendance_date, attendance_status),
            INDEX idx_project_company_hr_attendance_request (company_key_hash, attendance_request_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_overtime_slip (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            overtime_slip_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_key CHAR(36) NOT NULL,
            posting_date DATE NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            total_overtime_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
            slip_status ENUM('DRAFT','SUBMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            payroll_handoff_status ENUM('PENDING','ACCEPTED','REJECTED') NOT NULL DEFAULT 'PENDING',
            payroll_entry_key CHAR(36) NULL,
            salary_slip_key CHAR(36) NULL,
            immutable_checksum CHAR(64) NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            submitted_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_company_hr_overtime_slip_employee (company_key_hash, employee_key, posting_date, slip_status),
            INDEX idx_project_company_hr_overtime_handoff (company_key_hash, payroll_handoff_status, slip_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_overtime_detail (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            overtime_detail_key CHAR(36) NOT NULL UNIQUE,
            overtime_slip_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            employee_key CHAR(36) NOT NULL,
            reference_attendance_key CHAR(36) NULL,
            overtime_date DATE NOT NULL,
            overtime_type_key CHAR(36) NOT NULL,
            overtime_hours DECIMAL(10,2) NOT NULL,
            maximum_hours DECIMAL(10,2) NULL,
            standard_working_hours DECIMAL(10,2) NOT NULL,
            applied_multiplier DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
            calculated_amount DECIMAL(20,6) NULL,
            immutable_checksum CHAR(64) NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_hr_overtime_detail_day (overtime_slip_key, overtime_date, overtime_type_key),
            INDEX idx_project_company_hr_overtime_detail_employee (company_key_hash, employee_key, overtime_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'HR schema update');
    }

    yovel_admin_hr_ensure_setup_foundation_schema($db);
    yovel_admin_hr_ensure_typed_custom_value_schema($db);
    if (function_exists('yovel_admin_hr_ensure_leave_schema')) {
        yovel_admin_hr_ensure_leave_schema($db);
    }
    if (function_exists('yovel_admin_hr_ensure_recruitment_schema')) {
        yovel_admin_hr_ensure_recruitment_schema($db);
    }
    if (function_exists('yovel_admin_hr_ensure_onboarding_schema')) {
        yovel_admin_hr_ensure_onboarding_schema($db);
    }
    yovel_admin_migrate_hr_database_model($db);
}

function yovel_admin_hr_ensure_setup_foundation_schema(ADOConnection $db): void
{
    $columns = [
        ['project_company_hr_setting', 'settings_json', "LONGTEXT NOT NULL AFTER allow_employee_self_service"],
        ['project_company_hr_employee_property_history', 'property_label', "VARCHAR(160) NOT NULL DEFAULT '' AFTER source_lifecycle_key"],
    ];
    foreach ($columns as [$table, $column, $definition]) {
        $exists = (int) $db->GetOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [BUILDERX_DB_NAME, $table, $column]
        );
        if ($exists === 0) {
            yovel_admin_db_execute($db, 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition, [], 'HR Setup schema extension');
        }
    }

    $masterType = strtolower((string) $db->GetOne(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [BUILDERX_DB_NAME, 'project_company_hr_setup_master', 'record_type']
    ));
    if (!str_contains($masterType, 'employee_health_insurance')) {
        yovel_admin_db_execute(
            $db,
            "ALTER TABLE project_company_hr_setup_master MODIFY record_type ENUM('EMPLOYMENT_TYPE','EMPLOYEE_GRADE','GRIEVANCE_TYPE','INTEREST','EMPLOYEE_HEALTH_INSURANCE') NOT NULL",
            [],
            'HR Setup master type extension'
        );
    }

    $lifecycleStatus = strtolower((string) $db->GetOne(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [BUILDERX_DB_NAME, 'project_company_hr_employee_lifecycle', 'record_status']
    ));
    if (!str_contains($lifecycleStatus, "'resolved'")) {
        yovel_admin_db_execute(
            $db,
            "ALTER TABLE project_company_hr_employee_lifecycle MODIFY record_status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','ACTIVE','INACTIVE','OPEN','INVESTIGATED','RESOLVED','INVALID','CANCELLED','DELETED') NOT NULL DEFAULT 'DRAFT'",
            [],
            'HR grievance status extension'
        );
    }
}

function yovel_admin_hr_ensure_typed_custom_value_schema(ADOConnection $db): void
{
    $columns = [
        'field_key' => "CHAR(36) NULL AFTER field_name",
        'field_type' => "ENUM('TEXT','TEXTAREA','DATE','NUMBER','EMAIL','PHONE','SELECT','CHECKBOX') NOT NULL DEFAULT 'TEXT' AFTER field_key",
        'value_text' => 'TEXT NULL AFTER field_value',
        'value_number' => 'DECIMAL(20,6) NULL AFTER value_text',
        'value_date' => 'DATE NULL AFTER value_number',
        'value_boolean' => 'TINYINT(1) NULL AFTER value_date',
        'value_json' => 'LONGTEXT NULL AFTER value_boolean',
    ];
    foreach ($columns as $column => $definition) {
        $exists = (int) $db->GetOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [BUILDERX_DB_NAME, 'project_company_hr_custom_value', $column]
        );
        if ($exists === 0) {
            yovel_admin_db_execute(
                $db,
                'ALTER TABLE project_company_hr_custom_value ADD COLUMN ' . $column . ' ' . $definition,
                [],
                'HR typed custom-value schema update'
            );
        }
    }

    $indexExists = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [BUILDERX_DB_NAME, 'project_company_hr_custom_value', 'idx_project_company_hr_custom_value_field_key']
    );
    if ($indexExists === 0) {
        yovel_admin_db_execute(
            $db,
            'ALTER TABLE project_company_hr_custom_value ADD INDEX idx_project_company_hr_custom_value_field_key (company_key_hash, field_key)',
            [],
            'HR typed custom-value index update'
        );
    }
}

function yovel_admin_hr_migration_typed_value(string $fieldType, string $value): ?array
{
    $fieldType = strtoupper(trim($fieldType));
    $typed = [
        'value_text' => null,
        'value_number' => null,
        'value_date' => null,
        'value_boolean' => null,
        'value_json' => null,
    ];
    if ($fieldType === 'NUMBER') {
        if (!is_numeric($value)) {
            return null;
        }
        $typed['value_number'] = (string) $value;
    } elseif ($fieldType === 'DATE') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return null;
        }
        $typed['value_date'] = $value;
    } elseif ($fieldType === 'CHECKBOX') {
        $typed['value_boolean'] = in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    } else {
        $typed['value_text'] = $value;
    }

    return $typed;
}

function yovel_admin_hr_builder_version_checksum(array $form, string $schemaJson): string
{
    $canonical = json_encode([
        'target_section' => (string) ($form['target_section'] ?? ''),
        'form_title' => (string) ($form['form_title'] ?? ''),
        'form_description' => (string) ($form['form_description'] ?? ''),
        'form_status' => (string) ($form['form_status'] ?? 'DRAFT'),
        'schema_json' => $schemaJson,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    return hash('sha256', $canonical);
}

function yovel_admin_migrate_hr_database_model(ADOConnection $db): void
{
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('HR database migration transaction could not start.');
    }
    $changes = ['assignments' => 0, 'versions' => 0, 'typed_values' => 0, 'empty_values_removed' => 0];
    try {
        $employees = $db->GetAll("
            SELECT employee_key, company_key, company_key_hash, branch_key, department_key,
                   job_position_key, team_key, reports_to_employee_key, date_of_joining,
                   created_by_admin_key, updated_by_admin_key
            FROM project_company_hr_employee
            WHERE branch_key IS NOT NULL OR department_key IS NOT NULL OR job_position_key IS NOT NULL
               OR team_key IS NOT NULL OR reports_to_employee_key IS NOT NULL
            ORDER BY x_id
        ");
        foreach (is_array($employees) ? $employees : [] as $employee) {
            $migrationKey = 'legacy-current:' . (string) $employee['employee_key'];
            $existingAssignment = $db->GetRow(
                'SELECT assignment_key FROM project_company_hr_employee_assignment WHERE migration_key = ? FOR UPDATE',
                [$migrationKey]
            );
            if (!$existingAssignment) {
                $projectKey = null;
                $branchKey = (string) ($employee['branch_key'] ?? '');
                if ($branchKey !== '') {
                    $projectKeys = $db->GetCol(
                        "SELECT project_key FROM project_company_project WHERE company_key_hash = ? AND branch_key = ? AND project_status <> 'DELETED' ORDER BY x_id",
                        [(string) $employee['company_key_hash'], $branchKey]
                    );
                    if (is_array($projectKeys) && count($projectKeys) === 1) {
                        $projectKey = (string) $projectKeys[0];
                    }
                }
                $assignmentKey = bx_uuid();
                yovel_admin_db_execute(
                    $db,
                    "INSERT INTO project_company_hr_employee_assignment (
                        assignment_key, migration_key, company_key, company_key_hash, employee_key,
                        branch_key, project_key, department_key, job_position_key, team_key,
                        reports_to_employee_key, assignment_status, is_primary, effective_from,
                        created_by_admin_key, updated_by_admin_key
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 1, ?, ?, ?)",
                    [
                        $assignmentKey, $migrationKey, (string) $employee['company_key'], (string) $employee['company_key_hash'],
                        (string) $employee['employee_key'], $branchKey !== '' ? $branchKey : null, $projectKey,
                        ($employee['department_key'] ?? null) ?: null, ($employee['job_position_key'] ?? null) ?: null,
                        ($employee['team_key'] ?? null) ?: null, ($employee['reports_to_employee_key'] ?? null) ?: null,
                        ($employee['date_of_joining'] ?? null) ?: null, ($employee['created_by_admin_key'] ?? null) ?: null,
                        ($employee['updated_by_admin_key'] ?? null) ?: null,
                    ],
                    'HR legacy assignment migration'
                );
                $readBack = $db->GetRow(
                    'SELECT assignment_key, employee_key, migration_key, assignment_status, is_primary FROM project_company_hr_employee_assignment WHERE assignment_key = ? LIMIT 1',
                    [$assignmentKey]
                );
                if (!is_array($readBack)
                    || (string) ($readBack['employee_key'] ?? '') !== (string) $employee['employee_key']
                    || (string) ($readBack['migration_key'] ?? '') !== $migrationKey
                    || (string) ($readBack['assignment_status'] ?? '') !== 'ACTIVE'
                    || (int) ($readBack['is_primary'] ?? 0) !== 1) {
                    throw new RuntimeException('HR legacy assignment migration read-back failed.');
                }
                $changes['assignments']++;
            }
        }

        $builderForms = $db->GetAll("
            SELECT builder_form_key, company_key, company_key_hash, target_section, form_title,
                   form_description, form_status, schema_json, question_count, created_by_admin_key
            FROM project_company_hr_builder_form
            WHERE form_status <> 'DELETED'
            ORDER BY x_id
        ");
        foreach (is_array($builderForms) ? $builderForms : [] as $form) {
            $schemaJson = (string) ($form['schema_json'] ?? '{\"version\":1,\"questions\":[]}');
            $checksum = yovel_admin_hr_builder_version_checksum($form, $schemaJson);
            $existingVersion = $db->GetRow(
                'SELECT form_version_key FROM project_company_hr_builder_form_version WHERE builder_form_key = ? AND schema_checksum = ? FOR UPDATE',
                [(string) $form['builder_form_key'], $checksum]
            );
            if (!$existingVersion) {
                $versionKey = bx_uuid();
                $versionNumber = (int) $db->GetOne(
                    'SELECT COALESCE(MAX(version_number), 0) + 1 FROM project_company_hr_builder_form_version WHERE builder_form_key = ?',
                    [(string) $form['builder_form_key']]
                );
                yovel_admin_db_execute(
                    $db,
                    "INSERT INTO project_company_hr_builder_form_version (
                        form_version_key, builder_form_key, company_key, company_key_hash, version_number,
                        target_section, form_title, form_description, form_status, schema_json,
                        schema_checksum, question_count, created_by_admin_key
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $versionKey, (string) $form['builder_form_key'], (string) $form['company_key'],
                        (string) $form['company_key_hash'], $versionNumber, (string) $form['target_section'],
                        (string) $form['form_title'], (string) ($form['form_description'] ?? ''),
                        (string) $form['form_status'], $schemaJson, $checksum, (int) $form['question_count'],
                        ($form['created_by_admin_key'] ?? null) ?: null,
                    ],
                    'HR builder form version migration'
                );
                $savedChecksum = (string) $db->GetOne(
                    'SELECT schema_checksum FROM project_company_hr_builder_form_version WHERE form_version_key = ? LIMIT 1',
                    [$versionKey]
                );
                if ($savedChecksum !== $checksum) {
                    throw new RuntimeException('HR builder form version migration read-back failed.');
                }
                $changes['versions']++;
            }
        }

        $emptyRows = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_custom_value WHERE field_value IS NULL OR TRIM(field_value) = ''");
        if ($emptyRows > 0) {
            yovel_admin_db_execute(
                $db,
                "DELETE FROM project_company_hr_custom_value WHERE field_value IS NULL OR TRIM(field_value) = ''",
                [],
                'HR empty custom-value migration'
            );
            $changes['empty_values_removed'] = $emptyRows;
        }

        $legacyValues = $db->GetAll("
            SELECT value_record.value_key, value_record.field_value, field_record.field_key, field_record.field_type
            FROM project_company_hr_custom_value value_record
            INNER JOIN project_company_hr_form_field field_record
                ON field_record.company_key_hash = value_record.company_key_hash
               AND field_record.form_key = value_record.form_key
               AND field_record.field_name = value_record.field_name
               AND field_record.field_status = 'ACTIVE'
            WHERE value_record.field_key IS NULL OR value_record.field_key = ''
            ORDER BY value_record.x_id
        ");
        foreach (is_array($legacyValues) ? $legacyValues : [] as $legacyValue) {
            $typed = yovel_admin_hr_migration_typed_value((string) $legacyValue['field_type'], (string) $legacyValue['field_value']);
            if ($typed === null) {
                continue;
            }
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_hr_custom_value
                 SET field_key = ?, field_type = ?, value_text = ?, value_number = ?, value_date = ?, value_boolean = ?, value_json = ?
                 WHERE value_key = ?",
                [
                    (string) $legacyValue['field_key'], (string) $legacyValue['field_type'],
                    $typed['value_text'], $typed['value_number'], $typed['value_date'],
                    $typed['value_boolean'], $typed['value_json'], (string) $legacyValue['value_key'],
                ],
                'HR typed custom-value migration'
            );
            $savedFieldKey = (string) $db->GetOne(
                'SELECT field_key FROM project_company_hr_custom_value WHERE value_key = ? LIMIT 1',
                [(string) $legacyValue['value_key']]
            );
            if ($savedFieldKey !== (string) $legacyValue['field_key']) {
                throw new RuntimeException('HR typed custom-value migration read-back failed.');
            }
            $changes['typed_values']++;
        }

        if (array_sum($changes) > 0) {
            bx_audit('MIGRATE', 'project_company_hr_database_model', null, $changes, 'Migrated HR records to the database-driven form model.');
        }
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('HR database migration transaction commit failed.');
        }
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_hr_default_form_fields(): array
{
    $employee = [
        ['employee_code', 'Series', 'TEXT', 'overview', 10, 1, 1, 'HR-EMP-'],
        ['employee_status', 'Status', 'SELECT', 'overview', 20, 1, 1, "DRAFT\nACTIVE\nINACTIVE\nON_LEAVE\nSEPARATED"],
        ['employee_name', 'Full name', 'TEXT', 'overview', 30, 0, 1, ''],
        ['first_name', 'First name', 'TEXT', 'overview', 40, 1, 1, ''],
        ['middle_name', 'Middle name', 'TEXT', 'overview', 50, 0, 1, ''],
        ['last_name', 'Last name', 'TEXT', 'overview', 60, 0, 1, ''],
        ['branch_key', 'Branch', 'SELECT', 'overview', 70, 0, 1, ''],
        ['department_key', 'Department', 'SELECT', 'overview', 80, 0, 1, ''],
        ['job_position_key', 'Designation', 'SELECT', 'overview', 90, 0, 1, ''],
        ['team_key', 'Team', 'SELECT', 'overview', 100, 0, 1, ''],
        ['reports_to_employee_key', 'Reports to', 'SELECT', 'overview', 110, 0, 1, ''],
        ['date_of_joining', 'Date of joining', 'DATE', 'joining', 120, 0, 1, ''],
        ['employment_type', 'Employment type', 'TEXT', 'joining', 130, 0, 1, ''],
        ['offer_date', 'Offer date', 'DATE', 'joining', 140, 0, 0, ''],
        ['confirmation_date', 'Confirmation date', 'DATE', 'joining', 150, 0, 0, ''],
        ['contract_end_date', 'Contract end date', 'DATE', 'joining', 160, 0, 0, ''],
        ['notice_days', 'Notice days', 'NUMBER', 'joining', 170, 0, 0, ''],
        ['mobile_number', 'Mobile', 'PHONE', 'address-contacts', 180, 0, 1, ''],
        ['company_email', 'Company email', 'EMAIL', 'address-contacts', 190, 0, 1, ''],
        ['personal_email', 'Personal email', 'EMAIL', 'address-contacts', 200, 0, 1, ''],
        ['current_address', 'Current address', 'TEXTAREA', 'address-contacts', 210, 0, 0, ''],
        ['emergency_phone', 'Emergency phone', 'PHONE', 'address-contacts', 220, 0, 0, ''],
        ['attendance_device_id', 'Attendance device ID', 'TEXT', 'attendance-leaves', 230, 0, 0, ''],
        ['holiday_list', 'Holiday list', 'TEXT', 'attendance-leaves', 240, 0, 0, ''],
        ['salary_mode', 'Salary mode', 'SELECT', 'salary', 250, 0, 0, "Bank\nCash\nCheque"],
        ['ctc', 'Cost to company', 'NUMBER', 'salary', 260, 0, 0, ''],
        ['salary_currency', 'Salary currency', 'TEXT', 'salary', 270, 0, 0, ''],
        ['bank_name', 'Bank name', 'TEXT', 'salary', 280, 0, 0, ''],
        ['bank_ac_no', 'Bank A/C no.', 'TEXT', 'salary', 290, 0, 0, ''],
        ['iban', 'IBAN', 'TEXT', 'salary', 300, 0, 0, ''],
        ['date_of_birth', 'Date of birth', 'DATE', 'personal', 310, 0, 1, ''],
        ['gender', 'Gender', 'TEXT', 'personal', 320, 0, 0, ''],
        ['marital_status', 'Marital status', 'SELECT', 'personal', 330, 0, 0, "Single\nMarried\nDivorced\nWidowed"],
        ['family_background', 'Family background', 'TEXTAREA', 'personal', 340, 0, 0, ''],
        ['blood_group', 'Blood group', 'SELECT', 'personal', 350, 0, 0, "A+\nA-\nB+\nB-\nAB+\nAB-\nO+\nO-"],
        ['employee_notes', 'Notes', 'TEXTAREA', 'profile', 360, 0, 1, ''],
        ['education', 'Educational qualification', 'TEXTAREA', 'profile', 370, 0, 0, ''],
        ['previous_work_experience', 'Previous work experience', 'TEXTAREA', 'profile', 380, 0, 0, ''],
        ['resignation_letter_date', 'Resignation letter date', 'DATE', 'exit', 390, 0, 0, ''],
        ['relieving_date', 'Relieving date', 'DATE', 'exit', 400, 0, 0, ''],
        ['exit_interview_held_on', 'Exit interview held on', 'DATE', 'exit', 410, 0, 0, ''],
        ['reason_for_leaving', 'Reason for leaving', 'TEXTAREA', 'exit', 420, 0, 0, ''],
    ];
    $department = [
        ['department_code', 'Department code', 'TEXT', 'overview', 10, 1, 1, ''],
        ['department_name', 'Department name', 'TEXT', 'overview', 20, 1, 1, ''],
        ['branch_key', 'Branch', 'SELECT', 'overview', 30, 1, 1, ''],
        ['department_status', 'Status', 'SELECT', 'overview', 40, 1, 1, "DRAFT\nACTIVE\nINACTIVE\nARCHIVED"],
        ['department_type', 'Department type', 'TEXT', 'details', 50, 1, 1, 'OPERATIONS'],
        ['department_description', 'Description', 'TEXTAREA', 'details', 60, 0, 1, ''],
    ];
    $jobPosition = [
        ['job_position_code', 'Position code', 'TEXT', 'overview', 10, 1, 1, ''],
        ['job_position_name', 'Position name', 'TEXT', 'overview', 20, 1, 1, ''],
        ['job_position_status', 'Status', 'SELECT', 'overview', 30, 1, 1, "DRAFT\nACTIVE\nINACTIVE"],
        ['job_position_description', 'Description', 'TEXTAREA', 'details', 40, 0, 1, ''],
    ];
    $team = [
        ['team_code', 'Team code', 'TEXT', 'overview', 10, 1, 1, ''],
        ['team_name', 'Team name', 'TEXT', 'overview', 20, 1, 1, ''],
        ['team_status', 'Status', 'SELECT', 'overview', 30, 1, 1, "DRAFT\nACTIVE\nINACTIVE"],
        ['team_description', 'Description', 'TEXTAREA', 'details', 40, 0, 1, ''],
    ];

    $forms = [
        'employee-profiles' => $employee,
        'departments' => $department,
        'job-positions' => $jobPosition,
        'teams' => $team,
        'hr-settings' => [
            ['employee_naming_mode', 'Employee naming mode', 'SELECT', 'overview', 10, 1, 1, "SERIES\nMANUAL"],
            ['employee_number_prefix', 'Employee number prefix', 'TEXT', 'overview', 20, 1, 1, ''],
            ['default_retirement_age', 'Default retirement age', 'NUMBER', 'overview', 30, 1, 1, ''],
            ['allow_employee_self_service', 'Allow employee self-service', 'CHECKBOX', 'overview', 40, 0, 0, ''],
            ['setting_status', 'Status', 'SELECT', 'overview', 50, 1, 1, "ACTIVE\nINACTIVE"],
        ],
        'employment-type' => [
            ['record_code', 'Employment type code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['record_name', 'Employment type', 'TEXT', 'overview', 20, 1, 1, ''],
            ['record_description', 'Description', 'TEXTAREA', 'details', 30, 0, 0, ''],
            ['record_status', 'Status', 'SELECT', 'overview', 40, 1, 1, "DRAFT\nACTIVE\nINACTIVE"],
        ],
        'employee-grade' => [
            ['record_code', 'Employee grade code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['record_name', 'Employee grade', 'TEXT', 'overview', 20, 1, 1, ''],
            ['default_salary_structure', 'Default salary structure', 'TEXT', 'salary', 30, 0, 0, ''],
            ['currency', 'Currency', 'TEXT', 'salary', 40, 0, 0, ''],
            ['default_base_pay', 'Default base pay', 'NUMBER', 'salary', 50, 0, 0, ''],
            ['record_description', 'Description', 'TEXTAREA', 'details', 60, 0, 0, ''],
            ['record_status', 'Status', 'SELECT', 'overview', 70, 1, 1, "DRAFT\nACTIVE\nINACTIVE"],
        ],
        'employee-transfer' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['effective_date', 'Effective date', 'DATE', 'overview', 20, 1, 1, ''],
            ['transfer_status', 'Transfer status', 'SELECT', 'overview', 30, 1, 1, "DRAFT\nSUBMITTED\nAPPROVED\nREJECTED\nCANCELLED"],
            ['branch_key', 'Branch', 'SELECT', 'assignment', 40, 0, 1, ''],
            ['department_key', 'Department', 'SELECT', 'assignment', 50, 0, 1, ''],
            ['job_position_key', 'Job position', 'SELECT', 'assignment', 60, 0, 1, ''],
            ['team_key', 'Team', 'SELECT', 'assignment', 70, 0, 1, ''],
            ['reports_to_employee_key', 'Reports to', 'SELECT', 'assignment', 80, 0, 1, ''],
            ['reason', 'Reason', 'TEXTAREA', 'details', 90, 1, 0, ''],
        ],
        'employee-promotion' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['effective_date', 'Effective date', 'DATE', 'overview', 20, 1, 1, ''],
            ['promotion_status', 'Promotion status', 'SELECT', 'overview', 30, 1, 1, "DRAFT\nSUBMITTED\nAPPROVED\nREJECTED\nCANCELLED"],
            ['job_position_key', 'Job position', 'SELECT', 'assignment', 40, 1, 1, ''],
            ['employee_grade_key', 'Employee grade', 'SELECT', 'assignment', 50, 1, 1, ''],
            ['current_ctc', 'Current CTC', 'NUMBER', 'compensation', 60, 0, 0, ''],
            ['revised_ctc', 'Revised CTC', 'NUMBER', 'compensation', 70, 0, 0, ''],
            ['reason', 'Reason', 'TEXTAREA', 'details', 80, 1, 0, ''],
        ],
        'employee-grievance' => [
            ['employee_key', 'Raised by', 'SELECT', 'overview', 10, 1, 1, ''],
            ['subject', 'Subject', 'TEXT', 'overview', 20, 1, 0, ''],
            ['effective_date', 'Raised on', 'DATE', 'overview', 30, 1, 1, ''],
            ['record_status', 'Status', 'SELECT', 'overview', 40, 1, 1, "OPEN\nINVESTIGATED\nRESOLVED\nINVALID\nCANCELLED"],
            ['grievance_type_key', 'Grievance type', 'SELECT', 'details', 50, 1, 0, ''],
            ['grievance_against_party', 'Grievance against', 'TEXT', 'details', 60, 0, 0, ''],
            ['description', 'Description', 'TEXTAREA', 'details', 70, 1, 0, ''],
            ['investigation_cause', 'Cause', 'TEXTAREA', 'investigation', 80, 0, 0, ''],
            ['resolution_detail', 'Resolution details', 'TEXTAREA', 'resolution', 90, 0, 0, ''],
            ['resolution_date', 'Resolution date', 'DATE', 'resolution', 100, 0, 0, ''],
        ],
        'employee-health-insurance' => [
            ['record_code', 'Health insurance code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['record_name', 'Health insurance name', 'TEXT', 'overview', 20, 1, 1, ''],
            ['record_status', 'Status', 'SELECT', 'overview', 30, 1, 1, "DRAFT\nACTIVE\nINACTIVE"],
        ],
        'department-approver' => [
            ['approver_user_key', 'Approver', 'SELECT', 'overview', 10, 1, 1, ''],
            ['department_key', 'Department', 'SELECT', 'overview', 20, 1, 1, ''],
            ['effective_date', 'Effective date', 'DATE', 'overview', 30, 1, 1, ''],
            ['record_status', 'Status', 'SELECT', 'overview', 40, 1, 1, "DRAFT\nACTIVE\nINACTIVE\nCANCELLED"],
            ['approver_role', 'Approver role', 'TEXT', 'details', 50, 0, 0, ''],
        ],
        'appraisee' => [
            ['employee_key', 'Appraisee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['appraisal_template_key', 'Appraisal template', 'TEXT', 'overview', 20, 0, 1, ''],
            ['effective_date', 'Effective date', 'DATE', 'overview', 30, 1, 1, ''],
            ['record_status', 'Status', 'SELECT', 'overview', 40, 1, 1, "DRAFT\nACTIVE\nINACTIVE\nCANCELLED"],
        ],
        'grievance-type' => [
            ['record_code', 'Grievance type code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['record_name', 'Grievance type', 'TEXT', 'overview', 20, 1, 1, ''],
            ['record_status', 'Status', 'SELECT', 'overview', 30, 1, 1, "DRAFT\nACTIVE\nINACTIVE"],
        ],
        'interest' => [
            ['record_code', 'Interest code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['record_name', 'Interest', 'TEXT', 'overview', 20, 1, 1, ''],
            ['record_status', 'Status', 'SELECT', 'overview', 30, 1, 1, "DRAFT\nACTIVE\nINACTIVE"],
        ],
        'attendance' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['attendance_date', 'Attendance date', 'DATE', 'overview', 20, 1, 1, ''],
            ['attendance_status', 'Status', 'SELECT', 'overview', 30, 1, 1, "PRESENT\nABSENT\nON_LEAVE\nHALF_DAY\nWORK_FROM_HOME"],
            ['working_hours', 'Working hours', 'NUMBER', 'details', 40, 0, 0, ''],
            ['source_mode', 'Source mode', 'SELECT', 'audit', 50, 1, 1, "MANUAL\nAUTO\nREQUEST"],
            ['source_reference', 'Source reference', 'TEXT', 'audit', 60, 1, 1, ''],
            ['remarks', 'Remarks', 'TEXTAREA', 'details', 70, 0, 0, ''],
        ],
        'attendance-request' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['from_date', 'From date', 'DATE', 'overview', 20, 1, 1, ''],
            ['to_date', 'To date', 'DATE', 'overview', 30, 1, 1, ''],
            ['reason', 'Reason', 'SELECT', 'details', 40, 1, 0, "WORK_FROM_HOME\nON_DUTY"],
            ['explanation', 'Explanation', 'TEXTAREA', 'details', 50, 0, 0, ''],
            ['request_status', 'Status', 'SELECT', 'approval', 60, 1, 1, "DRAFT\nAPPROVED\nREJECTED\nCANCELLED"],
        ],
        'employee-checkin' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['checkin_at', 'Check-in time', 'TEXT', 'overview', 20, 1, 1, ''],
            ['log_type', 'Log type', 'SELECT', 'overview', 30, 1, 1, "IN\nOUT"],
            ['timezone_name', 'Time zone', 'TEXT', 'details', 40, 1, 0, 'Asia/Manila'],
            ['device_id', 'Device ID', 'TEXT', 'details', 50, 0, 0, ''],
            ['source_reference', 'Source reference', 'TEXT', 'audit', 60, 1, 1, ''],
        ],
        'shift-type' => [
            ['shift_code', 'Shift code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['shift_name', 'Shift name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['timezone_name', 'Time zone', 'TEXT', 'timing', 30, 1, 1, 'Asia/Manila'],
            ['start_time', 'Start time', 'TEXT', 'timing', 40, 1, 1, ''],
            ['end_time', 'End time', 'TEXT', 'timing', 50, 1, 1, ''],
            ['enable_auto_attendance', 'Enable auto attendance', 'CHECKBOX', 'automation', 60, 0, 0, ''],
            ['shift_status', 'Status', 'SELECT', 'overview', 70, 1, 1, "ACTIVE\nINACTIVE"],
        ],
        'shift-location' => [
            ['location_name', 'Location name', 'TEXT', 'overview', 10, 1, 1, ''],
            ['checkin_radius', 'Check-in radius', 'NUMBER', 'geofence', 20, 0, 0, ''],
            ['latitude', 'Latitude', 'NUMBER', 'geofence', 30, 0, 0, ''],
            ['longitude', 'Longitude', 'NUMBER', 'geofence', 40, 0, 0, ''],
            ['location_status', 'Status', 'SELECT', 'overview', 50, 1, 1, "ACTIVE\nINACTIVE"],
        ],
        'shift-assignment' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['shift_type_key', 'Shift type', 'SELECT', 'overview', 20, 1, 1, ''],
            ['shift_schedule_key', 'Shift schedule', 'SELECT', 'details', 30, 0, 0, ''],
            ['start_date', 'Start date', 'DATE', 'dates', 40, 1, 1, ''],
            ['end_date', 'End date', 'DATE', 'dates', 50, 0, 0, ''],
            ['assignment_status', 'Status', 'SELECT', 'overview', 60, 1, 1, "ACTIVE\nINACTIVE\nCANCELLED"],
            ['source_reference', 'Source reference', 'TEXT', 'audit', 70, 1, 1, ''],
        ],
        'shift-schedule' => [
            ['schedule_code', 'Schedule code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['schedule_name', 'Schedule name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['shift_type_key', 'Shift type', 'SELECT', 'overview', 30, 1, 1, ''],
            ['frequency_weeks', 'Frequency in weeks', 'NUMBER', 'pattern', 40, 1, 1, ''],
            ['repeat_days', 'Repeat days', 'TEXTAREA', 'pattern', 50, 1, 0, ''],
            ['schedule_status', 'Status', 'SELECT', 'overview', 60, 1, 1, "DRAFT\nACTIVE\nINACTIVE"],
        ],
        'shift-request' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['shift_type_key', 'Shift type', 'SELECT', 'overview', 20, 1, 1, ''],
            ['from_date', 'From date', 'DATE', 'dates', 30, 1, 1, ''],
            ['to_date', 'To date', 'DATE', 'dates', 40, 0, 0, ''],
            ['request_status', 'Status', 'SELECT', 'approval', 50, 1, 1, "DRAFT\nAPPROVED\nREJECTED\nCANCELLED"],
            ['reason', 'Reason', 'TEXTAREA', 'details', 60, 0, 0, ''],
        ],
        'overtime-type' => [
            ['overtime_code', 'Overtime code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['overtime_name', 'Overtime name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['calculation_method', 'Calculation method', 'SELECT', 'calculation', 30, 1, 1, "SALARY_COMPONENT_BASED\nFIXED_HOURLY_RATE"],
            ['hourly_rate', 'Hourly rate', 'NUMBER', 'calculation', 40, 0, 0, ''],
            ['standard_multiplier', 'Standard multiplier', 'NUMBER', 'calculation', 50, 1, 0, ''],
            ['maximum_hours', 'Maximum hours', 'NUMBER', 'limits', 60, 0, 0, ''],
            ['overtime_status', 'Status', 'SELECT', 'overview', 70, 1, 1, "ACTIVE\nINACTIVE"],
        ],
        'overtime-slip' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['posting_date', 'Posting date', 'DATE', 'overview', 20, 1, 1, ''],
            ['start_date', 'Start date', 'DATE', 'period', 30, 1, 1, ''],
            ['end_date', 'End date', 'DATE', 'period', 40, 1, 1, ''],
            ['slip_status', 'Status', 'SELECT', 'overview', 50, 1, 1, "DRAFT\nSUBMITTED\nCANCELLED"],
            ['payroll_handoff_status', 'Payroll handoff', 'SELECT', 'handoff', 60, 1, 1, "PENDING\nACCEPTED\nREJECTED"],
        ],
    ];
    foreach (array_keys(yovel_admin_hr_sections()) as $sectionKey) {
        if ($sectionKey === 'dashboard') {
            continue;
        }
        if (!isset($forms[$sectionKey])) {
            $forms[$sectionKey] = [
                ['record_code', 'Record code', 'TEXT', 'overview', 10, 1, 0, ''],
                ['record_name', 'Record name', 'TEXT', 'overview', 20, 1, 0, ''],
                ['record_status', 'Status', 'SELECT', 'overview', 30, 1, 0, "DRAFT\nACTIVE\nINACTIVE"],
                ['record_notes', 'Notes', 'TEXTAREA', 'details', 40, 0, 0, ''],
            ];
        }
    }

    if (function_exists('yovel_admin_hr_leave_default_form_fields')) {
        $forms = array_merge($forms, yovel_admin_hr_leave_default_form_fields());
    }
    if (function_exists('yovel_admin_hr_recruitment_default_form_fields')) {
        $forms = array_merge($forms, yovel_admin_hr_recruitment_default_form_fields());
    }
    if (function_exists('yovel_admin_hr_onboarding_default_form_fields')) {
        $forms = array_merge($forms, yovel_admin_hr_onboarding_default_form_fields());
    }

    return $forms;
}

function yovel_admin_seed_hr_form_fields(array $company, ?array $admin = null): void
{
    yovel_admin_hr_schema();

    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = $admin ? (string) $admin['admin_key'] : null;
    foreach (yovel_admin_hr_default_form_fields() as $formKey => $fields) {
        foreach ($fields as $field) {
            [$fieldName, $label, $type, $section, $sortOrder, $required, $core, $options] = $field;
            yovel_admin_db_execute(
                $db,
                "INSERT IGNORE INTO project_company_hr_form_field (
                    field_key, company_key, company_key_hash, form_key, field_name, field_label, field_type,
                    field_section, field_options, is_required, is_visible, is_core, sort_order,
                    created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)",
                [bx_uuid(), $companyKey, $companyKeyHash, $formKey, $fieldName, $label, $type, $section, $options, (int) $required, (int) $core, (int) $sortOrder, $adminKey, $adminKey],
                'HR form field seed'
            );
        }
    }

    yovel_admin_db_execute(
        $db,
        "UPDATE project_company_hr_form_field
         SET field_status = 'DELETED', updated_by_admin_key = ?
         WHERE company_key_hash = ?
           AND form_key = 'teams'
           AND is_core = 0
           AND field_status <> 'DELETED'
           AND field_name IN ('record_code', 'record_name', 'record_status', 'record_notes')",
        [$adminKey, $companyKeyHash],
        'HR team placeholder field retirement'
    );
    $activeTeamPlaceholders = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_hr_form_field
         WHERE company_key_hash = ?
           AND form_key = 'teams'
           AND field_status = 'ACTIVE'
           AND field_name IN ('record_code', 'record_name', 'record_status', 'record_notes')",
        [$companyKeyHash]
    );
    if ($activeTeamPlaceholders !== 0) {
        throw new RuntimeException('HR team placeholder field retirement verification failed.');
    }
}
