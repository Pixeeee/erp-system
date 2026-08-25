<?php
declare(strict_types=1);

function yovel_admin_hr_leave_form_targets(): array
{
    return [
        'leave-type' => ['label' => 'Leave Type', 'protected_fields' => ['leave_type_key', 'leave_type_code', 'leave_type_status'], 'versioned' => true],
        'leave-period' => ['label' => 'Leave Period', 'protected_fields' => ['leave_period_key', 'period_code', 'from_date', 'to_date', 'period_status'], 'versioned' => true],
        'leave-policy' => ['label' => 'Leave Policy', 'protected_fields' => ['leave_policy_key', 'policy_code', 'policy_status'], 'versioned' => true],
        'leave-policy-assignment' => ['label' => 'Leave Policy Assignment', 'protected_fields' => ['leave_policy_assignment_key', 'employee_key', 'leave_policy_key', 'leave_period_key', 'assignment_status'], 'versioned' => true],
        'leave-allocation' => ['label' => 'Leave Allocation', 'protected_fields' => ['leave_allocation_key', 'employee_key', 'leave_type_key', 'leave_period_key', 'allocation_status', 'source_reference'], 'versioned' => true],
        'leave-application' => ['label' => 'Leave Application', 'protected_fields' => ['leave_application_key', 'employee_key', 'leave_type_key', 'from_date', 'to_date', 'application_status', 'source_reference'], 'versioned' => true],
        'leave-adjustment' => ['label' => 'Leave Adjustment', 'protected_fields' => ['leave_adjustment_key', 'employee_key', 'leave_type_key', 'posting_date', 'quantity'], 'versioned' => true],
        'compensatory-leave-request' => ['label' => 'Compensatory Leave Request', 'protected_fields' => ['compensatory_leave_request_key', 'employee_key', 'leave_type_key', 'work_date', 'request_status'], 'versioned' => true],
        'leave-encashment' => ['label' => 'Leave Encashment', 'protected_fields' => ['leave_encashment_key', 'employee_key', 'leave_type_key', 'posting_date', 'encashment_status'], 'versioned' => true],
        'earned-leave-schedule' => ['label' => 'Earned Leave Schedule', 'protected_fields' => ['earned_leave_schedule_key', 'schedule_code', 'leave_type_key', 'schedule_status'], 'versioned' => true],
        'holiday-list-assignment' => ['label' => 'Holiday List Assignment', 'protected_fields' => ['holiday_list_assignment_key', 'employee_key', 'holiday_list_key', 'effective_from', 'assignment_status'], 'versioned' => true],
        'leave-block-list' => ['label' => 'Leave Block List', 'protected_fields' => ['leave_block_list_key', 'block_list_code', 'block_list_status'], 'versioned' => true],
    ];
}

function yovel_admin_hr_leave_default_form_fields(): array
{
    return [
        'leave-type' => [
            ['leave_type_code', 'Leave type code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['leave_type_name', 'Leave type name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['maximum_days', 'Maximum days', 'NUMBER', 'rules', 30, 1, 0, ''],
            ['allow_carry_forward', 'Allow carry forward', 'CHECKBOX', 'rules', 40, 0, 0, ''],
            ['allow_negative_balance', 'Allow negative balance', 'CHECKBOX', 'rules', 50, 0, 0, ''],
            ['include_holidays', 'Include holidays', 'CHECKBOX', 'rules', 60, 0, 0, ''],
            ['is_paid', 'Paid leave', 'CHECKBOX', 'rules', 70, 0, 0, ''],
            ['leave_type_status', 'Status', 'SELECT', 'overview', 80, 1, 1, "ACTIVE\nINACTIVE"],
        ],
        'leave-period' => [
            ['period_code', 'Period code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['period_name', 'Period name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['from_date', 'From date', 'DATE', 'dates', 30, 1, 1, ''],
            ['to_date', 'To date', 'DATE', 'dates', 40, 1, 1, ''],
            ['period_status', 'Status', 'SELECT', 'overview', 50, 1, 1, "ACTIVE\nINACTIVE\nCLOSED"],
        ],
        'leave-policy' => [
            ['policy_code', 'Policy code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['policy_name', 'Policy name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['policy_status', 'Status', 'SELECT', 'overview', 30, 1, 1, "ACTIVE\nINACTIVE"],
        ],
        'leave-policy-assignment' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['leave_policy_key', 'Leave policy', 'SELECT', 'overview', 20, 1, 1, ''],
            ['leave_period_key', 'Leave period', 'SELECT', 'overview', 30, 1, 1, ''],
            ['effective_from', 'Effective from', 'DATE', 'dates', 40, 1, 1, ''],
            ['effective_until', 'Effective until', 'DATE', 'dates', 50, 0, 0, ''],
            ['assignment_status', 'Status', 'SELECT', 'overview', 60, 1, 1, "ACTIVE\nINACTIVE\nCANCELLED"],
        ],
        'leave-allocation' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['leave_type_key', 'Leave type', 'SELECT', 'overview', 20, 1, 1, ''],
            ['leave_period_key', 'Leave period', 'SELECT', 'overview', 30, 1, 1, ''],
            ['from_date', 'From date', 'DATE', 'dates', 40, 1, 1, ''],
            ['to_date', 'To date', 'DATE', 'dates', 50, 1, 1, ''],
            ['allocated_quantity', 'Allocated quantity', 'NUMBER', 'balance', 60, 1, 0, ''],
            ['carry_forward_quantity', 'Carry forward', 'NUMBER', 'balance', 70, 0, 0, ''],
            ['allocation_status', 'Status', 'SELECT', 'overview', 80, 1, 1, "DRAFT\nSUBMITTED\nCANCELLED"],
            ['source_reference', 'Source reference', 'TEXT', 'audit', 90, 1, 1, ''],
        ],
        'leave-application' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['leave_type_key', 'Leave type', 'SELECT', 'overview', 20, 1, 1, ''],
            ['from_date', 'From date', 'DATE', 'dates', 30, 1, 1, ''],
            ['to_date', 'To date', 'DATE', 'dates', 40, 1, 1, ''],
            ['is_half_day', 'Half day', 'CHECKBOX', 'dates', 50, 0, 0, ''],
            ['half_day_date', 'Half-day date', 'DATE', 'dates', 60, 0, 0, ''],
            ['reason', 'Reason', 'TEXTAREA', 'details', 70, 0, 0, ''],
            ['application_status', 'Status', 'SELECT', 'approval', 80, 1, 1, "DRAFT\nSUBMITTED\nAPPROVED\nREJECTED\nCANCELLED"],
            ['source_reference', 'Source reference', 'TEXT', 'audit', 90, 1, 1, ''],
        ],
        'leave-adjustment' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['leave_type_key', 'Leave type', 'SELECT', 'overview', 20, 1, 1, ''],
            ['posting_date', 'Posting date', 'DATE', 'overview', 30, 1, 1, ''],
            ['quantity', 'Quantity', 'NUMBER', 'balance', 40, 1, 1, ''],
            ['reason', 'Reason', 'TEXTAREA', 'details', 50, 1, 0, ''],
        ],
        'compensatory-leave-request' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['leave_type_key', 'Leave type', 'SELECT', 'overview', 20, 1, 1, ''],
            ['work_date', 'Work date', 'DATE', 'overview', 30, 1, 1, ''],
            ['quantity', 'Quantity', 'NUMBER', 'balance', 40, 1, 0, ''],
            ['reason', 'Reason', 'TEXTAREA', 'details', 50, 0, 0, ''],
            ['request_status', 'Status', 'SELECT', 'approval', 60, 1, 1, "DRAFT\nSUBMITTED\nAPPROVED\nREJECTED\nCANCELLED"],
        ],
        'leave-encashment' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['leave_type_key', 'Leave type', 'SELECT', 'overview', 20, 1, 1, ''],
            ['posting_date', 'Posting date', 'DATE', 'overview', 30, 1, 1, ''],
            ['quantity', 'Quantity', 'NUMBER', 'balance', 40, 1, 0, ''],
            ['encashment_status', 'Status', 'SELECT', 'overview', 50, 1, 1, "DRAFT\nSUBMITTED\nCANCELLED"],
        ],
        'earned-leave-schedule' => [
            ['schedule_code', 'Schedule code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['schedule_name', 'Schedule name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['leave_type_key', 'Leave type', 'SELECT', 'overview', 30, 1, 1, ''],
            ['frequency', 'Frequency', 'SELECT', 'rules', 40, 1, 0, "MONTHLY\nQUARTERLY\nANNUAL"],
            ['earned_quantity', 'Earned quantity', 'NUMBER', 'rules', 50, 1, 0, ''],
            ['schedule_status', 'Status', 'SELECT', 'overview', 60, 1, 1, "ACTIVE\nINACTIVE"],
        ],
        'holiday-list-assignment' => [
            ['employee_key', 'Employee', 'SELECT', 'overview', 10, 1, 1, ''],
            ['holiday_list_key', 'Holiday list', 'SELECT', 'overview', 20, 1, 1, ''],
            ['effective_from', 'Effective from', 'DATE', 'dates', 30, 1, 1, ''],
            ['effective_until', 'Effective until', 'DATE', 'dates', 40, 0, 0, ''],
            ['assignment_status', 'Status', 'SELECT', 'overview', 50, 1, 1, "ACTIVE\nINACTIVE\nCANCELLED"],
        ],
        'leave-block-list' => [
            ['block_list_code', 'Block list code', 'TEXT', 'overview', 10, 1, 1, ''],
            ['block_list_name', 'Block list name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['block_list_status', 'Status', 'SELECT', 'overview', 30, 1, 1, "ACTIVE\nINACTIVE"],
        ],
    ];
}

function yovel_admin_hr_ensure_leave_schema(ADOConnection $db): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_type (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_type_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, leave_type_code VARCHAR(80) NOT NULL,
            leave_type_name VARCHAR(160) NOT NULL, maximum_days DECIMAL(10,4) NOT NULL DEFAULT 0,
            allow_carry_forward TINYINT(1) NOT NULL DEFAULT 0, allow_negative_balance TINYINT(1) NOT NULL DEFAULT 0,
            include_holidays TINYINT(1) NOT NULL DEFAULT 0, is_paid TINYINT(1) NOT NULL DEFAULT 1,
            leave_type_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_leave_type_code (company_key_hash, leave_type_code), INDEX idx_hr_leave_type_status (company_key_hash, leave_type_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_period (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_period_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, period_code VARCHAR(80) NOT NULL,
            period_name VARCHAR(160) NOT NULL, from_date DATE NOT NULL, to_date DATE NOT NULL,
            period_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_leave_period_code (company_key_hash, period_code), INDEX idx_hr_leave_period_dates (company_key_hash, from_date, to_date, period_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_holiday_list (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, holiday_list_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, holiday_list_code VARCHAR(80) NOT NULL,
            holiday_list_name VARCHAR(160) NOT NULL, from_date DATE NOT NULL, to_date DATE NOT NULL,
            timezone_name VARCHAR(80) NOT NULL DEFAULT 'UTC', holiday_list_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_holiday_list_code (company_key_hash, holiday_list_code), INDEX idx_hr_holiday_list_dates (company_key_hash, from_date, to_date, holiday_list_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_holiday (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, holiday_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, holiday_list_key CHAR(36) NOT NULL,
            holiday_date DATE NOT NULL, description VARCHAR(240) NOT NULL, holiday_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_holiday_day (company_key_hash, holiday_list_key, holiday_date), INDEX idx_hr_holiday_date (company_key_hash, holiday_date, holiday_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_holiday_list_assignment (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, holiday_list_assignment_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL,
            holiday_list_key CHAR(36) NOT NULL, effective_from DATE NOT NULL, effective_until DATE NULL,
            assignment_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_hr_holiday_assignment_employee (company_key_hash, employee_key, effective_from, effective_until, assignment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_earned_leave_schedule (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, earned_leave_schedule_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, schedule_code VARCHAR(80) NOT NULL,
            schedule_name VARCHAR(160) NOT NULL, leave_type_key CHAR(36) NOT NULL, frequency VARCHAR(20) NOT NULL,
            earned_quantity DECIMAL(10,4) NOT NULL, schedule_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_earned_schedule_code (company_key_hash, schedule_code), INDEX idx_hr_earned_schedule_type (company_key_hash, leave_type_key, schedule_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_policy (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_policy_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, policy_code VARCHAR(80) NOT NULL,
            policy_name VARCHAR(160) NOT NULL, policy_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_leave_policy_code (company_key_hash, policy_code), INDEX idx_hr_leave_policy_status (company_key_hash, policy_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_policy_detail (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_policy_detail_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, leave_policy_key CHAR(36) NOT NULL,
            leave_type_key CHAR(36) NOT NULL, annual_allocation DECIMAL(10,4) NOT NULL,
            earned_leave_schedule_key CHAR(36) NULL, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_leave_policy_detail (company_key_hash, leave_policy_key, leave_type_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_policy_assignment (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_policy_assignment_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL,
            leave_policy_key CHAR(36) NOT NULL, leave_period_key CHAR(36) NOT NULL, effective_from DATE NOT NULL,
            effective_until DATE NULL, assignment_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_hr_leave_policy_assignment (company_key_hash, employee_key, effective_from, effective_until, assignment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_allocation (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_allocation_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL,
            leave_type_key CHAR(36) NOT NULL, leave_period_key CHAR(36) NOT NULL, from_date DATE NOT NULL, to_date DATE NOT NULL,
            allocated_quantity DECIMAL(10,4) NOT NULL, carry_forward_quantity DECIMAL(10,4) NOT NULL DEFAULT 0,
            allocation_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', source_reference VARCHAR(160) NOT NULL,
            immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL,
            submitted_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_leave_allocation_source (company_key_hash, source_reference), INDEX idx_hr_leave_allocation_employee (company_key_hash, employee_key, leave_type_key, from_date, to_date, allocation_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_application (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_application_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL,
            leave_type_key CHAR(36) NOT NULL, from_date DATE NOT NULL, to_date DATE NOT NULL,
            is_half_day TINYINT(1) NOT NULL DEFAULT 0, half_day_date DATE NULL, total_leave_days DECIMAL(10,4) NOT NULL,
            reason TEXT NULL, application_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', source_reference VARCHAR(160) NOT NULL,
            immutable_checksum CHAR(64) NOT NULL, approved_by_admin_key CHAR(36) NULL, approved_at DATETIME NULL,
            created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_leave_application_source (company_key_hash, source_reference), INDEX idx_hr_leave_application_employee (company_key_hash, employee_key, from_date, to_date, application_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_ledger_entry (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_ledger_entry_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL,
            leave_type_key CHAR(36) NOT NULL, posting_date DATE NOT NULL, quantity DECIMAL(10,4) NOT NULL,
            entry_type VARCHAR(40) NOT NULL, source_table VARCHAR(120) NOT NULL, source_key CHAR(36) NOT NULL,
            source_reference VARCHAR(180) NOT NULL, immutable_checksum CHAR(64) NOT NULL, entry_status VARCHAR(20) NOT NULL DEFAULT 'POSTED',
            created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_leave_ledger_source (company_key_hash, source_table, source_key, source_reference), INDEX idx_hr_leave_ledger_balance (company_key_hash, employee_key, leave_type_key, posting_date, entry_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_adjustment (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_adjustment_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL,
            leave_type_key CHAR(36) NOT NULL, posting_date DATE NOT NULL, quantity DECIMAL(10,4) NOT NULL,
            reason TEXT NOT NULL, immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_hr_leave_adjustment_employee (company_key_hash, employee_key, leave_type_key, posting_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_compensatory_leave_request (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, compensatory_leave_request_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL,
            leave_type_key CHAR(36) NOT NULL, work_date DATE NOT NULL, quantity DECIMAL(10,4) NOT NULL,
            reason TEXT NULL, request_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', immutable_checksum CHAR(64) NOT NULL,
            approved_by_admin_key CHAR(36) NULL, approved_at DATETIME NULL, created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_hr_comp_leave_employee (company_key_hash, employee_key, work_date, request_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_encashment (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_encashment_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL,
            leave_type_key CHAR(36) NOT NULL, posting_date DATE NOT NULL, quantity DECIMAL(10,4) NOT NULL,
            encashment_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', immutable_checksum CHAR(64) NOT NULL,
            created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_hr_leave_encashment_employee (company_key_hash, employee_key, leave_type_key, posting_date, encashment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_block_list (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_block_list_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, block_list_code VARCHAR(80) NOT NULL,
            block_list_name VARCHAR(160) NOT NULL, block_list_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_leave_block_code (company_key_hash, block_list_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_block_date (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_block_date_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, leave_block_list_key CHAR(36) NOT NULL,
            blocked_date DATE NOT NULL, description VARCHAR(240) NOT NULL DEFAULT '', created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_leave_block_day (company_key_hash, leave_block_list_key, blocked_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_leave_block_allow (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_block_allow_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, leave_block_list_key CHAR(36) NOT NULL,
            employee_key CHAR(36) NOT NULL, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_leave_block_allow (company_key_hash, leave_block_list_key, employee_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_notification_intent (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, notification_intent_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, record_key CHAR(36) NOT NULL,
            intent_type VARCHAR(80) NOT NULL, payload_json LONGTEXT NOT NULL, intent_status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hr_notification_intent (company_key_hash, record_key, intent_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'HR Leave schema update');
    }
}

function yovel_admin_hr_leave_bool(mixed $value): int
{
    return in_array($value, [1, '1', true, 'on', 'yes'], true) ? 1 : 0;
}

function yovel_admin_hr_leave_text(mixed $value, string $label, int $max = 160, bool $required = true): string
{
    $text = trim((string) $value);
    if ($required && $text === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }
    if (strlen($text) > $max) {
        throw new InvalidArgumentException($label . ' is too long.');
    }
    return $text;
}

function yovel_admin_hr_leave_code(mixed $value, string $label): string
{
    $code = strtoupper(yovel_admin_hr_leave_text($value, $label, 80));
    if (preg_match('/^[A-Z0-9_.-]+$/', $code) !== 1) {
        throw new InvalidArgumentException($label . ' may use letters, numbers, periods, underscores, and hyphens.');
    }
    return $code;
}

function yovel_admin_hr_leave_signed_decimal(mixed $value, string $label): string
{
    $text = trim((string) $value);
    if ($text === '' || !is_numeric($text) || !is_finite((float) $text) || abs((float) $text) > 100000 || abs((float) $text) < 0.00005) {
        throw new InvalidArgumentException($label . ' must be a non-zero number in the supported range.');
    }
    return number_format((float) $text, 4, '.', '');
}

function yovel_admin_hr_leave_key(ADOConnection $db, string $companyKeyHash, string $table, string $column, mixed $value, string $label, bool $required = true): string
{
    return yovel_admin_hr_setup_key($db, $companyKeyHash, $table, $column, $value, $label, $required);
}

function yovel_admin_hr_leave_dates(array $input, string $fromName = 'from_date', string $toName = 'to_date'): array
{
    $from = yovel_admin_hr_iso_date((string) ($input[$fromName] ?? ''), ucwords(str_replace('_', ' ', $fromName)));
    $to = yovel_admin_hr_iso_date((string) ($input[$toName] ?? ''), ucwords(str_replace('_', ' ', $toName)));
    if ($to < $from) {
        throw new InvalidArgumentException('The end date cannot be before the start date.');
    }
    if ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 3660) {
        throw new InvalidArgumentException('The date range is too long.');
    }
    return [$from, $to];
}

function yovel_admin_hr_leave_checksum(array $payload): string
{
    return hash('sha256', yovel_admin_hr_json($payload));
}

function yovel_admin_hr_leave_readback(ADOConnection $db, string $table, string $keyColumn, string $companyKeyHash, string $key): array
{
    $allowed = [
        'project_company_hr_leave_type' => 'leave_type_key', 'project_company_hr_leave_period' => 'leave_period_key',
        'project_company_hr_holiday_list' => 'holiday_list_key', 'project_company_hr_holiday' => 'holiday_key',
        'project_company_hr_earned_leave_schedule' => 'earned_leave_schedule_key', 'project_company_hr_leave_block_list' => 'leave_block_list_key',
        'project_company_hr_leave_policy' => 'leave_policy_key', 'project_company_hr_leave_policy_assignment' => 'leave_policy_assignment_key',
        'project_company_hr_holiday_list_assignment' => 'holiday_list_assignment_key', 'project_company_hr_leave_allocation' => 'leave_allocation_key',
        'project_company_hr_leave_application' => 'leave_application_key', 'project_company_hr_leave_adjustment' => 'leave_adjustment_key',
        'project_company_hr_compensatory_leave_request' => 'compensatory_leave_request_key', 'project_company_hr_leave_encashment' => 'leave_encashment_key',
    ];
    if (($allowed[$table] ?? null) !== $keyColumn) {
        throw new LogicException('HR Leave read-back target is invalid.');
    }
    $row = $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash = ? AND {$keyColumn} = ? LIMIT 1", [$companyKeyHash, $key]);
    if (!is_array($row) || $row === [] || !hash_equals($key, (string) ($row[$keyColumn] ?? ''))) {
        throw new RuntimeException('The HR Leave record could not be verified after saving.');
    }
    return $row;
}

function yovel_admin_hr_leave_append_ledger(ADOConnection $db, array $scope, array $entry): array
{
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $sourceTable = yovel_admin_hr_leave_text($entry['source_table'] ?? '', 'Ledger source table', 120);
    $allowedSources = [
        'project_company_hr_leave_allocation', 'project_company_hr_leave_application', 'project_company_hr_leave_adjustment',
        'project_company_hr_compensatory_leave_request', 'project_company_hr_leave_encashment',
    ];
    if (!in_array($sourceTable, $allowedSources, true)) {
        throw new LogicException('Leave ledger source is invalid.');
    }
    $sourceKey = trim((string) ($entry['source_key'] ?? ''));
    if (!yovel_admin_is_uuid($sourceKey)) {
        throw new InvalidArgumentException('Leave ledger source key is invalid.');
    }
    $sourceReference = yovel_admin_hr_leave_text($entry['source_reference'] ?? '', 'Ledger source reference', 180);
    $existing = $db->GetRow(
        'SELECT * FROM project_company_hr_leave_ledger_entry WHERE company_key_hash = ? AND source_table = ? AND source_key = ? AND source_reference = ? FOR UPDATE',
        [$companyKeyHash, $sourceTable, $sourceKey, $sourceReference]
    );
    $payload = [
        'employee_key' => (string) $entry['employee_key'], 'leave_type_key' => (string) $entry['leave_type_key'],
        'posting_date' => (string) $entry['posting_date'], 'quantity' => (string) $entry['quantity'],
        'entry_type' => (string) $entry['entry_type'], 'source_table' => $sourceTable,
        'source_key' => $sourceKey, 'source_reference' => $sourceReference,
    ];
    $checksum = yovel_admin_hr_leave_checksum($payload);
    if (is_array($existing) && $existing !== []) {
        if (!hash_equals($checksum, (string) $existing['immutable_checksum'])) {
            throw new RuntimeException('An immutable leave ledger source already exists with different values.');
        }
        return $existing;
    }
    $key = bx_uuid();
    yovel_admin_db_execute($db, "INSERT INTO project_company_hr_leave_ledger_entry (
        leave_ledger_entry_key, company_key, company_key_hash, employee_key, leave_type_key, posting_date,
        quantity, entry_type, source_table, source_key, source_reference, immutable_checksum, entry_status, created_by_admin_key
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'POSTED', ?)", [
        $key, $companyKey, $companyKeyHash, $payload['employee_key'], $payload['leave_type_key'], $payload['posting_date'],
        $payload['quantity'], $payload['entry_type'], $sourceTable, $sourceKey, $sourceReference, $checksum, $adminKey,
    ], 'Leave ledger append');
    $saved = $db->GetRow('SELECT * FROM project_company_hr_leave_ledger_entry WHERE company_key_hash = ? AND leave_ledger_entry_key = ?', [$companyKeyHash, $key]);
    if (!is_array($saved) || !hash_equals($checksum, (string) ($saved['immutable_checksum'] ?? ''))) {
        throw new RuntimeException('Leave ledger append could not be verified.');
    }
    return $saved;
}

function yovel_admin_calculate_leave_balance(ADOConnection $db, string $companyKeyHash, string $employeeKey, string $leaveTypeKey, string $asOf): array
{
    if (preg_match('/^[0-9a-f]{64}$/', $companyKeyHash) !== 1 || !yovel_admin_is_uuid($employeeKey) || !yovel_admin_is_uuid($leaveTypeKey)) {
        throw new InvalidArgumentException('Leave balance scope is invalid.');
    }
    $asOf = yovel_admin_hr_iso_date($asOf, 'Balance date');
    $available = (float) ($db->GetOne("SELECT COALESCE(SUM(quantity), 0) FROM project_company_hr_leave_ledger_entry
        WHERE company_key_hash = ? AND employee_key = ? AND leave_type_key = ? AND posting_date <= ? AND entry_status = 'POSTED'",
        [$companyKeyHash, $employeeKey, $leaveTypeKey, $asOf]) ?? 0);
    return ['employee_key' => $employeeKey, 'leave_type_key' => $leaveTypeKey, 'as_of' => $asOf, 'available' => number_format($available, 4, '.', '')];
}

function yovel_admin_calculate_leave_days(ADOConnection $db, string $companyKeyHash, string $employeeKey, string $leaveTypeKey, string $fromDate, string $toDate, bool $halfDay = false, string $halfDayDate = ''): string
{
    [$fromDate, $toDate] = yovel_admin_hr_leave_dates(['from_date' => $fromDate, 'to_date' => $toDate]);
    if (!yovel_admin_is_uuid($employeeKey) || !yovel_admin_is_uuid($leaveTypeKey)) {
        throw new InvalidArgumentException('Leave day calculation scope is invalid.');
    }
    $leaveType = $db->GetRow('SELECT include_holidays FROM project_company_hr_leave_type WHERE company_key_hash = ? AND leave_type_key = ? AND leave_type_status = ? LIMIT 1', [$companyKeyHash, $leaveTypeKey, 'ACTIVE']);
    if (!is_array($leaveType) || $leaveType === []) {
        throw new InvalidArgumentException('The selected leave type is unavailable.');
    }
    $holidayDates = [];
    if ((int) $leaveType['include_holidays'] === 0) {
        $rows = $db->GetAll("SELECT holiday.holiday_date FROM project_company_hr_holiday_list_assignment assignment
            INNER JOIN project_company_hr_holiday holiday ON holiday.company_key_hash = assignment.company_key_hash AND holiday.holiday_list_key = assignment.holiday_list_key AND holiday.holiday_status = 'ACTIVE'
            WHERE assignment.company_key_hash = ? AND assignment.employee_key = ? AND assignment.assignment_status = 'ACTIVE'
              AND assignment.effective_from <= holiday.holiday_date AND (assignment.effective_until IS NULL OR assignment.effective_until >= holiday.holiday_date)
              AND holiday.holiday_date BETWEEN ? AND ?", [$companyKeyHash, $employeeKey, $fromDate, $toDate]);
        foreach ($rows ?: [] as $row) {
            $holidayDates[(string) $row['holiday_date']] = true;
        }
    }
    if ($halfDay) {
        $halfDayDate = yovel_admin_hr_iso_date($halfDayDate, 'Half-day date');
        if ($halfDayDate < $fromDate || $halfDayDate > $toDate || isset($holidayDates[$halfDayDate])) {
            throw new InvalidArgumentException('Half-day date must be a counted leave date.');
        }
    }
    $count = 0.0;
    for ($date = new DateTimeImmutable($fromDate), $end = new DateTimeImmutable($toDate); $date <= $end; $date = $date->modify('+1 day')) {
        $day = $date->format('Y-m-d');
        if (!isset($holidayDates[$day])) {
            $count += ($halfDay && $day === $halfDayDate) ? 0.5 : 1.0;
        }
    }
    if ($count <= 0) {
        throw new InvalidArgumentException('The selected dates contain no applicable leave days.');
    }
    return number_format($count, 4, '.', '');
}

function yovel_admin_persist_leave_master(ADOConnection $db, array $company, array $admin, string $recordType, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $recordType = strtoupper(trim($recordType));
    $definitions = [
        'LEAVE_TYPE' => ['table' => 'project_company_hr_leave_type', 'key' => 'leave_type_key'],
        'LEAVE_PERIOD' => ['table' => 'project_company_hr_leave_period', 'key' => 'leave_period_key'],
        'HOLIDAY_LIST' => ['table' => 'project_company_hr_holiday_list', 'key' => 'holiday_list_key'],
        'HOLIDAY' => ['table' => 'project_company_hr_holiday', 'key' => 'holiday_key'],
        'EARNED_LEAVE_SCHEDULE' => ['table' => 'project_company_hr_earned_leave_schedule', 'key' => 'earned_leave_schedule_key'],
        'LEAVE_BLOCK_LIST' => ['table' => 'project_company_hr_leave_block_list', 'key' => 'leave_block_list_key'],
    ];
    if (!isset($definitions[$recordType])) {
        throw new InvalidArgumentException('Leave master type is invalid.');
    }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $companyKeyHash, $adminKey, $recordType, $input, $definitions, $failureInjector): array {
        $definition = $definitions[$recordType];
        $providedKey = trim((string) ($input[$definition['key']] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_leave_key($db, $companyKeyHash, $definition['table'], $definition['key'], $providedKey, $definition['key']);
            $existing = $db->GetRow("SELECT * FROM {$definition['table']} WHERE company_key_hash = ? AND {$definition['key']} = ? FOR UPDATE", [$companyKeyHash, $providedKey]);
        }
        $key = $providedKey !== '' ? $providedKey : bx_uuid();

        if ($recordType === 'LEAVE_TYPE') {
            $code = yovel_admin_hr_leave_code($input['leave_type_code'] ?? '', 'Leave type code');
            $name = yovel_admin_hr_leave_text($input['leave_type_name'] ?? '', 'Leave type name');
            $maximum = yovel_admin_hr_decimal($input['maximum_days'] ?? '0', 'Maximum days', 0, 10000, 4, true);
            $status = yovel_admin_status((string) ($input['leave_type_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_leave_type (leave_type_key, company_key, company_key_hash, leave_type_code, leave_type_name, maximum_days, allow_carry_forward, allow_negative_balance, include_holidays, is_paid, leave_type_status, created_by_admin_key, updated_by_admin_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE leave_type_name = VALUES(leave_type_name), maximum_days = VALUES(maximum_days), allow_carry_forward = VALUES(allow_carry_forward), allow_negative_balance = VALUES(allow_negative_balance), include_holidays = VALUES(include_holidays), is_paid = VALUES(is_paid), leave_type_status = VALUES(leave_type_status), updated_by_admin_key = VALUES(updated_by_admin_key)", [
                $key, $companyKey, $companyKeyHash, $code, $name, $maximum,
                yovel_admin_hr_leave_bool($input['allow_carry_forward'] ?? 0), yovel_admin_hr_leave_bool($input['allow_negative_balance'] ?? 0),
                yovel_admin_hr_leave_bool($input['include_holidays'] ?? 0), yovel_admin_hr_leave_bool($input['is_paid'] ?? 1), $status, $adminKey, $adminKey,
            ], 'Leave Type save');
        } elseif ($recordType === 'LEAVE_PERIOD') {
            [$from, $to] = yovel_admin_hr_leave_dates($input);
            $code = yovel_admin_hr_leave_code($input['period_code'] ?? '', 'Period code');
            $name = yovel_admin_hr_leave_text($input['period_name'] ?? '', 'Period name');
            $status = yovel_admin_status((string) ($input['period_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'CLOSED'], 'ACTIVE');
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_leave_period (leave_period_key, company_key, company_key_hash, period_code, period_name, from_date, to_date, period_status, created_by_admin_key, updated_by_admin_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE period_name = VALUES(period_name), from_date = VALUES(from_date), to_date = VALUES(to_date), period_status = VALUES(period_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$key, $companyKey, $companyKeyHash, $code, $name, $from, $to, $status, $adminKey, $adminKey], 'Leave Period save');
        } elseif ($recordType === 'HOLIDAY_LIST') {
            [$from, $to] = yovel_admin_hr_leave_dates($input);
            $code = yovel_admin_hr_leave_code($input['holiday_list_code'] ?? '', 'Holiday list code');
            $name = yovel_admin_hr_leave_text($input['holiday_list_name'] ?? '', 'Holiday list name');
            $timezone = yovel_admin_hr_timezone((string) ($input['timezone_name'] ?? 'UTC'))->getName();
            $status = yovel_admin_status((string) ($input['holiday_list_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_holiday_list (holiday_list_key, company_key, company_key_hash, holiday_list_code, holiday_list_name, from_date, to_date, timezone_name, holiday_list_status, created_by_admin_key, updated_by_admin_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE holiday_list_name = VALUES(holiday_list_name), from_date = VALUES(from_date), to_date = VALUES(to_date), timezone_name = VALUES(timezone_name), holiday_list_status = VALUES(holiday_list_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$key, $companyKey, $companyKeyHash, $code, $name, $from, $to, $timezone, $status, $adminKey, $adminKey], 'Holiday List save');
        } elseif ($recordType === 'HOLIDAY') {
            $listKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_holiday_list', 'holiday_list_key', $input['holiday_list_key'] ?? '', 'Holiday List');
            $date = yovel_admin_hr_iso_date((string) ($input['holiday_date'] ?? ''), 'Holiday date');
            $description = yovel_admin_hr_leave_text($input['description'] ?? '', 'Holiday description', 240);
            $status = yovel_admin_status((string) ($input['holiday_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_holiday (holiday_key, company_key, company_key_hash, holiday_list_key, holiday_date, description, holiday_status, created_by_admin_key, updated_by_admin_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description), holiday_status = VALUES(holiday_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$key, $companyKey, $companyKeyHash, $listKey, $date, $description, $status, $adminKey, $adminKey], 'Holiday save');
        } elseif ($recordType === 'EARNED_LEAVE_SCHEDULE') {
            $typeKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_type', 'leave_type_key', $input['leave_type_key'] ?? '', 'Leave Type');
            $code = yovel_admin_hr_leave_code($input['schedule_code'] ?? '', 'Schedule code');
            $name = yovel_admin_hr_leave_text($input['schedule_name'] ?? '', 'Schedule name');
            $frequency = yovel_admin_status((string) ($input['frequency'] ?? 'MONTHLY'), ['MONTHLY', 'QUARTERLY', 'ANNUAL'], 'MONTHLY');
            $quantity = yovel_admin_hr_decimal($input['earned_quantity'] ?? '', 'Earned quantity', 0.0001, 10000, 4, true);
            $status = yovel_admin_status((string) ($input['schedule_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_earned_leave_schedule (earned_leave_schedule_key, company_key, company_key_hash, schedule_code, schedule_name, leave_type_key, frequency, earned_quantity, schedule_status, created_by_admin_key, updated_by_admin_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE schedule_name = VALUES(schedule_name), leave_type_key = VALUES(leave_type_key), frequency = VALUES(frequency), earned_quantity = VALUES(earned_quantity), schedule_status = VALUES(schedule_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$key, $companyKey, $companyKeyHash, $code, $name, $typeKey, $frequency, $quantity, $status, $adminKey, $adminKey], 'Earned Leave Schedule save');
        } else {
            $code = yovel_admin_hr_leave_code($input['block_list_code'] ?? '', 'Block list code');
            $name = yovel_admin_hr_leave_text($input['block_list_name'] ?? '', 'Block list name');
            $status = yovel_admin_status((string) ($input['block_list_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_leave_block_list (leave_block_list_key, company_key, company_key_hash, block_list_code, block_list_name, block_list_status, created_by_admin_key, updated_by_admin_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE block_list_name = VALUES(block_list_name), block_list_status = VALUES(block_list_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$key, $companyKey, $companyKeyHash, $code, $name, $status, $adminKey, $adminKey], 'Leave Block List save');
            yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_leave_block_date WHERE company_key_hash = ? AND leave_block_list_key = ?', [$companyKeyHash, $key], 'Leave Block Dates replace');
            yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_leave_block_allow WHERE company_key_hash = ? AND leave_block_list_key = ?', [$companyKeyHash, $key], 'Leave Block Allow replace');
            foreach (is_array($input['blocked_dates'] ?? null) ? $input['blocked_dates'] : [] as $blocked) {
                if (!is_array($blocked)) { continue; }
                $date = yovel_admin_hr_iso_date((string) ($blocked['blocked_date'] ?? ''), 'Blocked date');
                $description = yovel_admin_hr_leave_text($blocked['description'] ?? '', 'Blocked date description', 240, false);
                yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_leave_block_date (leave_block_date_key, company_key, company_key_hash, leave_block_list_key, blocked_date, description, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [bx_uuid(), $companyKey, $companyKeyHash, $key, $date, $description, $adminKey], 'Leave Block Date save');
            }
            foreach (array_values(array_unique(array_map('strval', is_array($input['allowed_employee_keys'] ?? null) ? $input['allowed_employee_keys'] : []))) as $employeeKey) {
                yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
                yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_leave_block_allow (leave_block_allow_key, company_key, company_key_hash, leave_block_list_key, employee_key, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?)',
                    [bx_uuid(), $companyKey, $companyKeyHash, $key, $employeeKey, $adminKey], 'Leave Block Allow save');
            }
        }
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_leave_readback($db, $definition['table'], $definition['key'], $companyKeyHash, $key);
        bx_audit($existing ? 'UPDATE' : 'CREATE', $definition['table'], $key, ['company_key' => $companyKey, 'record_type' => $recordType], 'Company administrator saved an HR Leave master.');
        return $saved;
    });
}

function yovel_admin_persist_leave_policy(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $details = is_array($input['details'] ?? null) ? $input['details'] : [];
    if ($details === []) { throw new InvalidArgumentException('At least one leave policy detail is required.'); }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $input, $details, $failureInjector): array {
        $providedKey = trim((string) ($input['leave_policy_key'] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_policy', 'leave_policy_key', $providedKey, 'Leave Policy');
            $existing = $db->GetRow('SELECT * FROM project_company_hr_leave_policy WHERE company_key_hash = ? AND leave_policy_key = ? FOR UPDATE', [$companyKeyHash, $providedKey]);
        }
        $key = $providedKey ?: bx_uuid();
        $code = yovel_admin_hr_leave_code($input['policy_code'] ?? '', 'Policy code');
        $name = yovel_admin_hr_leave_text($input['policy_name'] ?? '', 'Policy name');
        $status = yovel_admin_status((string) ($input['policy_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_leave_policy (leave_policy_key, company_key, company_key_hash, policy_code, policy_name, policy_status, created_by_admin_key, updated_by_admin_key)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE policy_name = VALUES(policy_name), policy_status = VALUES(policy_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$key, $companyKey, $companyKeyHash, $code, $name, $status, $adminKey, $adminKey], 'Leave Policy save');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_leave_policy_detail WHERE company_key_hash = ? AND leave_policy_key = ?', [$companyKeyHash, $key], 'Leave Policy details replace');
        $used = [];
        foreach ($details as $detail) {
            if (!is_array($detail)) { continue; }
            $typeKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_type', 'leave_type_key', $detail['leave_type_key'] ?? '', 'Leave Type');
            if (isset($used[$typeKey])) { throw new InvalidArgumentException('A leave type may appear once in a policy.'); }
            $used[$typeKey] = true;
            $allocation = yovel_admin_hr_decimal($detail['annual_allocation'] ?? '', 'Annual allocation', 0, 10000, 4, true);
            $scheduleKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_earned_leave_schedule', 'earned_leave_schedule_key', $detail['earned_leave_schedule_key'] ?? '', 'Earned Leave Schedule', false);
            yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_leave_policy_detail (leave_policy_detail_key, company_key, company_key_hash, leave_policy_key, leave_type_key, annual_allocation, earned_leave_schedule_key, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [bx_uuid(), $companyKey, $companyKeyHash, $key, $typeKey, $allocation, $scheduleKey ?: null, $adminKey], 'Leave Policy Detail save');
        }
        if ($failureInjector) { $failureInjector(); }
        if ((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_leave_policy_detail WHERE company_key_hash = ? AND leave_policy_key = ?', [$companyKeyHash, $key]) !== count($used)) {
            throw new RuntimeException('Leave Policy details could not be verified.');
        }
        $saved = yovel_admin_hr_leave_readback($db, 'project_company_hr_leave_policy', 'leave_policy_key', $companyKeyHash, $key);
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_leave_policy', $key, ['company_key' => $companyKey, 'detail_count' => count($used)], 'Company administrator saved a Leave Policy.');
        return $saved;
    });
}

function yovel_admin_hr_leave_effective_assignment(ADOConnection $db, array $company, array $admin, array $input, string $kind, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $isPolicy = $kind === 'POLICY';
    $table = $isPolicy ? 'project_company_hr_leave_policy_assignment' : 'project_company_hr_holiday_list_assignment';
    $keyColumn = $isPolicy ? 'leave_policy_assignment_key' : 'holiday_list_assignment_key';
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $input, $failureInjector, $isPolicy, $table, $keyColumn): array {
        $employeeKey = trim((string) ($input['employee_key'] ?? ''));
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $effectiveFrom = yovel_admin_hr_iso_date((string) ($input['effective_from'] ?? ''), 'Effective from');
        $effectiveUntil = yovel_admin_optional_date((string) ($input['effective_until'] ?? ''), 'Effective until');
        if ($effectiveUntil !== '' && $effectiveUntil < $effectiveFrom) { throw new InvalidArgumentException('Effective until cannot precede effective from.'); }
        $status = yovel_admin_status((string) ($input['assignment_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'CANCELLED'], 'ACTIVE');
        $providedKey = trim((string) ($input[$keyColumn] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_leave_key($db, $companyKeyHash, $table, $keyColumn, $providedKey, 'Assignment');
            $existing = $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash = ? AND {$keyColumn} = ? FOR UPDATE", [$companyKeyHash, $providedKey]);
        }
        $overlap = (int) $db->GetOne("SELECT COUNT(*) FROM {$table} WHERE company_key_hash = ? AND employee_key = ? AND assignment_status = 'ACTIVE' AND {$keyColumn} <> ? AND effective_from <= ? AND (effective_until IS NULL OR effective_until >= ?)",
            [$companyKeyHash, $employeeKey, $providedKey ?: '', $effectiveUntil ?: '9999-12-31', $effectiveFrom]);
        if ($status === 'ACTIVE' && $overlap > 0) { throw new InvalidArgumentException('An active assignment already overlaps these dates.'); }
        $key = $providedKey ?: bx_uuid();
        if ($isPolicy) {
            $policyKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_policy', 'leave_policy_key', $input['leave_policy_key'] ?? '', 'Leave Policy');
            $periodKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_period', 'leave_period_key', $input['leave_period_key'] ?? '', 'Leave Period');
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_leave_policy_assignment (leave_policy_assignment_key, company_key, company_key_hash, employee_key, leave_policy_key, leave_period_key, effective_from, effective_until, assignment_status, created_by_admin_key, updated_by_admin_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE leave_policy_key = VALUES(leave_policy_key), leave_period_key = VALUES(leave_period_key), effective_from = VALUES(effective_from), effective_until = VALUES(effective_until), assignment_status = VALUES(assignment_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$key, $companyKey, $companyKeyHash, $employeeKey, $policyKey, $periodKey, $effectiveFrom, $effectiveUntil ?: null, $status, $adminKey, $adminKey], 'Leave Policy Assignment save');
        } else {
            $listKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_holiday_list', 'holiday_list_key', $input['holiday_list_key'] ?? '', 'Holiday List');
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_holiday_list_assignment (holiday_list_assignment_key, company_key, company_key_hash, employee_key, holiday_list_key, effective_from, effective_until, assignment_status, created_by_admin_key, updated_by_admin_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE holiday_list_key = VALUES(holiday_list_key), effective_from = VALUES(effective_from), effective_until = VALUES(effective_until), assignment_status = VALUES(assignment_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [$key, $companyKey, $companyKeyHash, $employeeKey, $listKey, $effectiveFrom, $effectiveUntil ?: null, $status, $adminKey, $adminKey], 'Holiday List Assignment save');
        }
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_leave_readback($db, $table, $keyColumn, $companyKeyHash, $key);
        bx_audit($existing ? 'UPDATE' : 'CREATE', $table, $key, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'assignment_status' => $status], 'Company administrator saved an effective HR Leave assignment.');
        return $saved;
    });
}

function yovel_admin_persist_leave_policy_assignment(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    return yovel_admin_hr_leave_effective_assignment($db, $company, $admin, $input, 'POLICY', $failureInjector);
}

function yovel_admin_persist_holiday_list_assignment(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    return yovel_admin_hr_leave_effective_assignment($db, $company, $admin, $input, 'HOLIDAY', $failureInjector);
}

function yovel_admin_hr_leave_allocation_in_transaction(ADOConnection $db, array $scope, array $input): array
{
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $employeeKey = trim((string) ($input['employee_key'] ?? ''));
    yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
    $typeKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_type', 'leave_type_key', $input['leave_type_key'] ?? '', 'Leave Type');
    $periodKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_period', 'leave_period_key', $input['leave_period_key'] ?? '', 'Leave Period');
    [$from, $to] = yovel_admin_hr_leave_dates($input);
    $allocated = yovel_admin_hr_decimal($input['allocated_quantity'] ?? '', 'Allocated quantity', 0, 10000, 4, true);
    $carry = yovel_admin_hr_decimal($input['carry_forward_quantity'] ?? '0', 'Carry forward quantity', 0, 10000, 4, true);
    $status = yovel_admin_status((string) ($input['allocation_status'] ?? 'SUBMITTED'), ['DRAFT', 'SUBMITTED', 'CANCELLED'], 'SUBMITTED');
    $sourceReference = yovel_admin_hr_leave_text($input['source_reference'] ?? ('ALLOC-' . bx_uuid()), 'Source reference', 160);
    $providedKey = trim((string) ($input['leave_allocation_key'] ?? ''));
    $existing = [];
    if ($providedKey !== '') {
        $providedKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_allocation', 'leave_allocation_key', $providedKey, 'Leave Allocation');
        $existing = $db->GetRow('SELECT * FROM project_company_hr_leave_allocation WHERE company_key_hash = ? AND leave_allocation_key = ? FOR UPDATE', [$companyKeyHash, $providedKey]);
        if (($existing['allocation_status'] ?? '') !== 'DRAFT') { throw new RuntimeException('Submitted leave allocations are immutable.'); }
    } else {
        $existingBySource = $db->GetRow('SELECT * FROM project_company_hr_leave_allocation WHERE company_key_hash = ? AND source_reference = ? FOR UPDATE', [$companyKeyHash, $sourceReference]);
        if (is_array($existingBySource) && $existingBySource !== []) { return $existingBySource; }
    }
    $key = $providedKey ?: bx_uuid();
    $payload = compact('employeeKey', 'typeKey', 'periodKey', 'from', 'to', 'allocated', 'carry', 'status', 'sourceReference');
    $checksum = yovel_admin_hr_leave_checksum($payload);
    yovel_admin_db_execute($db, "INSERT INTO project_company_hr_leave_allocation (leave_allocation_key, company_key, company_key_hash, employee_key, leave_type_key, leave_period_key, from_date, to_date, allocated_quantity, carry_forward_quantity, allocation_status, source_reference, immutable_checksum, created_by_admin_key, updated_by_admin_key, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE leave_type_key = VALUES(leave_type_key), leave_period_key = VALUES(leave_period_key), from_date = VALUES(from_date), to_date = VALUES(to_date), allocated_quantity = VALUES(allocated_quantity), carry_forward_quantity = VALUES(carry_forward_quantity), allocation_status = VALUES(allocation_status), source_reference = VALUES(source_reference), immutable_checksum = VALUES(immutable_checksum), updated_by_admin_key = VALUES(updated_by_admin_key), submitted_at = VALUES(submitted_at)", [
        $key, $companyKey, $companyKeyHash, $employeeKey, $typeKey, $periodKey, $from, $to, $allocated, $carry, $status,
        $sourceReference, $checksum, $adminKey, $adminKey, $status === 'SUBMITTED' ? date('Y-m-d H:i:s') : null,
    ], 'Leave Allocation save');
    if ($status === 'SUBMITTED') {
        $quantity = number_format((float) $allocated + (float) $carry, 4, '.', '');
        yovel_admin_hr_leave_append_ledger($db, $scope, [
            'employee_key' => $employeeKey, 'leave_type_key' => $typeKey, 'posting_date' => $from,
            'quantity' => $quantity, 'entry_type' => 'ALLOCATION', 'source_table' => 'project_company_hr_leave_allocation',
            'source_key' => $key, 'source_reference' => $sourceReference,
        ]);
    }
    $saved = yovel_admin_hr_leave_readback($db, 'project_company_hr_leave_allocation', 'leave_allocation_key', $companyKeyHash, $key);
    if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Leave Allocation verification failed.'); }
    bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_leave_allocation', $key, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'allocation_status' => $status, 'source_reference' => $sourceReference], 'Company administrator saved a Leave Allocation.');
    return $saved;
}

function yovel_admin_persist_leave_allocation(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $input, $failureInjector): array {
        $saved = yovel_admin_hr_leave_allocation_in_transaction($db, $scope, $input);
        if ($failureInjector) { $failureInjector(); }
        return $saved;
    });
}

function yovel_admin_bulk_leave_allocation(ADOConnection $db, array $company, array $admin, array $employeeKeys, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    $employeeKeys = array_values(array_unique(array_filter(array_map('strval', $employeeKeys))));
    if ($employeeKeys === [] || count($employeeKeys) > 500) { throw new InvalidArgumentException('Select from 1 to 500 employees.'); }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $employeeKeys, $input, $failureInjector): array {
        $saved = [];
        $baseReference = yovel_admin_hr_leave_text($input['source_reference'] ?? ('CONTROL-' . bx_uuid()), 'Source reference', 120);
        foreach ($employeeKeys as $employeeKey) {
            $saved[] = yovel_admin_hr_leave_allocation_in_transaction($db, $scope, array_merge($input, [
                'employee_key' => $employeeKey, 'allocation_status' => 'SUBMITTED', 'source_reference' => $baseReference . '-' . substr($employeeKey, 0, 8),
            ]));
        }
        if ($failureInjector) { $failureInjector(); }
        return $saved;
    });
}

function yovel_admin_hr_leave_blocked(ADOConnection $db, string $companyKeyHash, string $employeeKey, string $fromDate, string $toDate): bool
{
    return (int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_leave_block_date block_date
        INNER JOIN project_company_hr_leave_block_list block_list ON block_list.company_key_hash = block_date.company_key_hash AND block_list.leave_block_list_key = block_date.leave_block_list_key AND block_list.block_list_status = 'ACTIVE'
        LEFT JOIN project_company_hr_leave_block_allow allow_record ON allow_record.company_key_hash = block_date.company_key_hash AND allow_record.leave_block_list_key = block_date.leave_block_list_key AND allow_record.employee_key = ?
        WHERE block_date.company_key_hash = ? AND block_date.blocked_date BETWEEN ? AND ? AND allow_record.leave_block_allow_key IS NULL",
        [$employeeKey, $companyKeyHash, $fromDate, $toDate]) > 0;
}

function yovel_admin_persist_leave_application(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $input, $failureInjector): array {
        $employeeKey = trim((string) ($input['employee_key'] ?? ''));
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $typeKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_type', 'leave_type_key', $input['leave_type_key'] ?? '', 'Leave Type');
        [$from, $to] = yovel_admin_hr_leave_dates($input);
        $halfDay = yovel_admin_hr_leave_bool($input['is_half_day'] ?? 0) === 1;
        $halfDate = trim((string) ($input['half_day_date'] ?? ''));
        $days = yovel_admin_calculate_leave_days($db, $companyKeyHash, $employeeKey, $typeKey, $from, $to, $halfDay, $halfDate);
        $status = yovel_admin_status((string) ($input['application_status'] ?? 'SUBMITTED'), ['DRAFT', 'SUBMITTED'], 'SUBMITTED');
        $reason = yovel_admin_hr_leave_text($input['reason'] ?? '', 'Reason', 4000, false);
        $sourceReference = yovel_admin_hr_leave_text($input['source_reference'] ?? ('APP-' . bx_uuid()), 'Source reference', 160);
        $providedKey = trim((string) ($input['leave_application_key'] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_application', 'leave_application_key', $providedKey, 'Leave Application');
            $existing = $db->GetRow('SELECT * FROM project_company_hr_leave_application WHERE company_key_hash = ? AND leave_application_key = ? FOR UPDATE', [$companyKeyHash, $providedKey]);
            if (($existing['application_status'] ?? '') !== 'DRAFT') { throw new RuntimeException('Submitted leave applications are immutable.'); }
        }
        $key = $providedKey ?: bx_uuid();
        $overlap = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_leave_application WHERE company_key_hash = ? AND employee_key = ? AND leave_application_key <> ? AND application_status IN ('DRAFT','SUBMITTED','APPROVED') AND from_date <= ? AND to_date >= ?",
            [$companyKeyHash, $employeeKey, $key, $to, $from]);
        if ($overlap > 0) { throw new InvalidArgumentException('This leave application overlaps another active request.'); }
        if (yovel_admin_hr_leave_blocked($db, $companyKeyHash, $employeeKey, $from, $to)) { throw new InvalidArgumentException('A selected date is blocked for leave.'); }
        $payload = compact('employeeKey', 'typeKey', 'from', 'to', 'halfDay', 'halfDate', 'days', 'reason', 'status', 'sourceReference');
        $checksum = yovel_admin_hr_leave_checksum($payload);
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_leave_application (leave_application_key, company_key, company_key_hash, employee_key, leave_type_key, from_date, to_date, is_half_day, half_day_date, total_leave_days, reason, application_status, source_reference, immutable_checksum, created_by_admin_key, updated_by_admin_key)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE leave_type_key = VALUES(leave_type_key), from_date = VALUES(from_date), to_date = VALUES(to_date), is_half_day = VALUES(is_half_day), half_day_date = VALUES(half_day_date), total_leave_days = VALUES(total_leave_days), reason = VALUES(reason), application_status = VALUES(application_status), source_reference = VALUES(source_reference), immutable_checksum = VALUES(immutable_checksum), updated_by_admin_key = VALUES(updated_by_admin_key)", [
            $key, $companyKey, $companyKeyHash, $employeeKey, $typeKey, $from, $to, $halfDay ? 1 : 0, $halfDay ? $halfDate : null,
            $days, $reason ?: null, $status, $sourceReference, $checksum, $adminKey, $adminKey,
        ], 'Leave Application save');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_leave_readback($db, 'project_company_hr_leave_application', 'leave_application_key', $companyKeyHash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Leave Application verification failed.'); }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_leave_application', $key, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'application_status' => $status], 'Company administrator saved a Leave Application.');
        return $saved;
    });
}

function yovel_admin_hr_leave_notification_intent(ADOConnection $db, array $scope, string $recordKey, string $intentType, array $payload): void
{
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_db_execute($db, "INSERT INTO project_company_hr_notification_intent (notification_intent_key, company_key, company_key_hash, record_key, intent_type, payload_json, intent_status, created_by_admin_key)
        VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?) ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json)",
        [bx_uuid(), $companyKey, $companyKeyHash, $recordKey, $intentType, yovel_admin_hr_json($payload), $adminKey], 'HR Leave notification intent');
}

function yovel_admin_transition_leave_application(ADOConnection $db, array $company, array $admin, string $applicationKey, string $status, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $status = yovel_admin_status($status, ['APPROVED', 'REJECTED', 'CANCELLED'], '');
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $companyKeyHash, $adminKey, $applicationKey, $status, $failureInjector): array {
        if (!yovel_admin_is_uuid($applicationKey)) { throw new InvalidArgumentException('Leave Application is invalid.'); }
        $row = $db->GetRow('SELECT * FROM project_company_hr_leave_application WHERE company_key_hash = ? AND leave_application_key = ? FOR UPDATE', [$companyKeyHash, $applicationKey]);
        if (!is_array($row) || $row === []) { throw new InvalidArgumentException('Leave Application was not found.'); }
        if ((string) $row['application_status'] !== 'SUBMITTED') { throw new RuntimeException('Only submitted leave applications may be reviewed.'); }
        yovel_admin_hr_lock_employee($db, $companyKeyHash, (string) $row['employee_key']);
        $type = $db->GetRow('SELECT * FROM project_company_hr_leave_type WHERE company_key_hash = ? AND leave_type_key = ? FOR UPDATE', [$companyKeyHash, $row['leave_type_key']]);
        if (!is_array($type) || $type === []) { throw new RuntimeException('Leave Type is unavailable.'); }
        if ($status === 'APPROVED') {
            $balance = yovel_admin_calculate_leave_balance($db, $companyKeyHash, (string) $row['employee_key'], (string) $row['leave_type_key'], (string) $row['to_date']);
            if ((int) $type['allow_negative_balance'] === 0 && (float) $balance['available'] < (float) $row['total_leave_days']) {
                throw new RuntimeException('The employee does not have enough leave balance.');
            }
            yovel_admin_hr_leave_append_ledger($db, $scope, [
                'employee_key' => $row['employee_key'], 'leave_type_key' => $row['leave_type_key'], 'posting_date' => $row['to_date'],
                'quantity' => number_format(-(float) $row['total_leave_days'], 4, '.', ''), 'entry_type' => 'APPLICATION',
                'source_table' => 'project_company_hr_leave_application', 'source_key' => $applicationKey, 'source_reference' => $row['source_reference'],
            ]);
        }
        yovel_admin_db_execute($db, 'UPDATE project_company_hr_leave_application SET application_status = ?, approved_by_admin_key = ?, approved_at = NOW(), updated_by_admin_key = ? WHERE company_key_hash = ? AND leave_application_key = ?',
            [$status, $adminKey, $adminKey, $companyKeyHash, $applicationKey], 'Leave Application transition');
        yovel_admin_hr_leave_notification_intent($db, $scope, $applicationKey, 'LEAVE_APPLICATION_' . $status, ['application_status' => $status, 'employee_key' => $row['employee_key']]);
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_leave_readback($db, 'project_company_hr_leave_application', 'leave_application_key', $companyKeyHash, $applicationKey);
        if ((string) $saved['application_status'] !== $status) { throw new RuntimeException('Leave Application transition could not be verified.'); }
        bx_audit($status, 'project_company_hr_leave_application', $applicationKey, ['company_key' => $companyKey, 'application_status' => $status], 'Company administrator reviewed a Leave Application.');
        return $saved;
    });
}

function yovel_admin_persist_leave_adjustment(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $companyKeyHash, $adminKey, $input, $failureInjector): array {
        $employeeKey = trim((string) ($input['employee_key'] ?? ''));
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $typeKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_type', 'leave_type_key', $input['leave_type_key'] ?? '', 'Leave Type');
        $postingDate = yovel_admin_hr_iso_date((string) ($input['posting_date'] ?? ''), 'Posting date');
        $quantity = yovel_admin_hr_leave_signed_decimal($input['quantity'] ?? '', 'Quantity');
        $reason = yovel_admin_hr_leave_text($input['reason'] ?? '', 'Reason', 4000);
        $key = bx_uuid();
        $checksum = yovel_admin_hr_leave_checksum(compact('employeeKey', 'typeKey', 'postingDate', 'quantity', 'reason'));
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_leave_adjustment (leave_adjustment_key, company_key, company_key_hash, employee_key, leave_type_key, posting_date, quantity, reason, immutable_checksum, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$key, $companyKey, $companyKeyHash, $employeeKey, $typeKey, $postingDate, $quantity, $reason, $checksum, $adminKey], 'Leave Adjustment save');
        yovel_admin_hr_leave_append_ledger($db, $scope, [
            'employee_key' => $employeeKey, 'leave_type_key' => $typeKey, 'posting_date' => $postingDate, 'quantity' => $quantity,
            'entry_type' => 'ADJUSTMENT', 'source_table' => 'project_company_hr_leave_adjustment', 'source_key' => $key, 'source_reference' => 'ADJUSTMENT-' . $key,
        ]);
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_leave_readback($db, 'project_company_hr_leave_adjustment', 'leave_adjustment_key', $companyKeyHash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Leave Adjustment verification failed.'); }
        bx_audit('CREATE', 'project_company_hr_leave_adjustment', $key, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'quantity' => $quantity], 'Company administrator posted a Leave Adjustment.');
        return $saved;
    });
}

function yovel_admin_persist_compensatory_leave_request(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $companyKeyHash, $adminKey, $input, $failureInjector): array {
        $employeeKey = trim((string) ($input['employee_key'] ?? ''));
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $typeKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_type', 'leave_type_key', $input['leave_type_key'] ?? '', 'Leave Type');
        $workDate = yovel_admin_hr_iso_date((string) ($input['work_date'] ?? ''), 'Work date');
        $quantity = yovel_admin_hr_decimal($input['quantity'] ?? '', 'Quantity', 0.0001, 10000, 4, true);
        $reason = yovel_admin_hr_leave_text($input['reason'] ?? '', 'Reason', 4000, false);
        $status = yovel_admin_status((string) ($input['request_status'] ?? 'SUBMITTED'), ['DRAFT', 'SUBMITTED'], 'SUBMITTED');
        $key = bx_uuid();
        $checksum = yovel_admin_hr_leave_checksum(compact('employeeKey', 'typeKey', 'workDate', 'quantity', 'reason', 'status'));
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_compensatory_leave_request (compensatory_leave_request_key, company_key, company_key_hash, employee_key, leave_type_key, work_date, quantity, reason, request_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$key, $companyKey, $companyKeyHash, $employeeKey, $typeKey, $workDate, $quantity, $reason ?: null, $status, $checksum, $adminKey, $adminKey], 'Compensatory Leave Request save');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_leave_readback($db, 'project_company_hr_compensatory_leave_request', 'compensatory_leave_request_key', $companyKeyHash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Compensatory Leave Request verification failed.'); }
        bx_audit('CREATE', 'project_company_hr_compensatory_leave_request', $key, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'request_status' => $status], 'Company administrator saved a Compensatory Leave Request.');
        return $saved;
    });
}

function yovel_admin_transition_compensatory_leave_request(ADOConnection $db, array $company, array $admin, string $requestKey, string $status, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $status = yovel_admin_status($status, ['APPROVED', 'REJECTED', 'CANCELLED'], '');
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $companyKeyHash, $adminKey, $requestKey, $status, $failureInjector): array {
        if (!yovel_admin_is_uuid($requestKey)) { throw new InvalidArgumentException('Compensatory Leave Request is invalid.'); }
        $row = $db->GetRow('SELECT * FROM project_company_hr_compensatory_leave_request WHERE company_key_hash = ? AND compensatory_leave_request_key = ? FOR UPDATE', [$companyKeyHash, $requestKey]);
        if (!is_array($row) || $row === [] || (string) $row['request_status'] !== 'SUBMITTED') { throw new RuntimeException('Only submitted compensatory leave requests may be reviewed.'); }
        yovel_admin_hr_lock_employee($db, $companyKeyHash, (string) $row['employee_key']);
        if ($status === 'APPROVED') {
            yovel_admin_hr_leave_append_ledger($db, $scope, [
                'employee_key' => $row['employee_key'], 'leave_type_key' => $row['leave_type_key'], 'posting_date' => $row['work_date'],
                'quantity' => $row['quantity'], 'entry_type' => 'COMPENSATORY', 'source_table' => 'project_company_hr_compensatory_leave_request',
                'source_key' => $requestKey, 'source_reference' => 'COMPENSATORY-' . $requestKey,
            ]);
        }
        yovel_admin_db_execute($db, 'UPDATE project_company_hr_compensatory_leave_request SET request_status = ?, approved_by_admin_key = ?, approved_at = NOW(), updated_by_admin_key = ? WHERE company_key_hash = ? AND compensatory_leave_request_key = ?',
            [$status, $adminKey, $adminKey, $companyKeyHash, $requestKey], 'Compensatory Leave Request transition');
        yovel_admin_hr_leave_notification_intent($db, $scope, $requestKey, 'COMPENSATORY_LEAVE_' . $status, ['request_status' => $status, 'employee_key' => $row['employee_key']]);
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_leave_readback($db, 'project_company_hr_compensatory_leave_request', 'compensatory_leave_request_key', $companyKeyHash, $requestKey);
        bx_audit($status, 'project_company_hr_compensatory_leave_request', $requestKey, ['company_key' => $companyKey, 'request_status' => $status], 'Company administrator reviewed a Compensatory Leave Request.');
        return $saved;
    });
}

function yovel_admin_persist_leave_encashment(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $companyKeyHash, $adminKey, $input, $failureInjector): array {
        $employeeKey = trim((string) ($input['employee_key'] ?? ''));
        yovel_admin_hr_lock_employee($db, $companyKeyHash, $employeeKey);
        $typeKey = yovel_admin_hr_leave_key($db, $companyKeyHash, 'project_company_hr_leave_type', 'leave_type_key', $input['leave_type_key'] ?? '', 'Leave Type');
        $postingDate = yovel_admin_hr_iso_date((string) ($input['posting_date'] ?? ''), 'Posting date');
        $quantity = yovel_admin_hr_decimal($input['quantity'] ?? '', 'Quantity', 0.0001, 10000, 4, true);
        $status = yovel_admin_status((string) ($input['encashment_status'] ?? 'SUBMITTED'), ['DRAFT', 'SUBMITTED', 'CANCELLED'], 'SUBMITTED');
        if ($status === 'SUBMITTED') {
            $balance = yovel_admin_calculate_leave_balance($db, $companyKeyHash, $employeeKey, $typeKey, $postingDate);
            if ((float) $balance['available'] < (float) $quantity) { throw new RuntimeException('The employee does not have enough leave balance to encash.'); }
        }
        $key = bx_uuid();
        $checksum = yovel_admin_hr_leave_checksum(compact('employeeKey', 'typeKey', 'postingDate', 'quantity', 'status'));
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_leave_encashment (leave_encashment_key, company_key, company_key_hash, employee_key, leave_type_key, posting_date, quantity, encashment_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$key, $companyKey, $companyKeyHash, $employeeKey, $typeKey, $postingDate, $quantity, $status, $checksum, $adminKey, $adminKey], 'Leave Encashment save');
        if ($status === 'SUBMITTED') {
            yovel_admin_hr_leave_append_ledger($db, $scope, [
                'employee_key' => $employeeKey, 'leave_type_key' => $typeKey, 'posting_date' => $postingDate,
                'quantity' => number_format(-(float) $quantity, 4, '.', ''), 'entry_type' => 'ENCASHMENT',
                'source_table' => 'project_company_hr_leave_encashment', 'source_key' => $key, 'source_reference' => 'ENCASHMENT-' . $key,
            ]);
        }
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_leave_readback($db, 'project_company_hr_leave_encashment', 'leave_encashment_key', $companyKeyHash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Leave Encashment verification failed.'); }
        bx_audit('CREATE', 'project_company_hr_leave_encashment', $key, ['company_key' => $companyKey, 'employee_key' => $employeeKey, 'encashment_status' => $status], 'Company administrator saved a Leave Encashment.');
        return $saved;
    });
}

function yovel_admin_hr_calendar_read_contract(array $company, array $options = []): array
{
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[0-9a-f]{64}$/', $companyKeyHash) !== 1) { throw new InvalidArgumentException('HR calendar company scope is invalid.'); }
    $from = yovel_admin_hr_iso_date((string) ($options['from_date'] ?? date('Y-01-01')), 'Calendar from date');
    $to = yovel_admin_hr_iso_date((string) ($options['to_date'] ?? date('Y-12-31')), 'Calendar to date');
    if ($to < $from) { throw new InvalidArgumentException('Calendar end date cannot precede its start date.'); }
    $db = bx_db();
    $lists = $db->GetAll("SELECT holiday_list_key, holiday_list_code, holiday_list_name, from_date, to_date, timezone_name, holiday_list_status
        FROM project_company_hr_holiday_list WHERE company_key_hash = ? AND holiday_list_status <> 'INACTIVE' AND from_date <= ? AND to_date >= ? ORDER BY holiday_list_name, holiday_list_key", [$companyKeyHash, $to, $from]) ?: [];
    $holidays = $db->GetAll("SELECT holiday_key, holiday_list_key, holiday_date, description, holiday_status
        FROM project_company_hr_holiday WHERE company_key_hash = ? AND holiday_status = 'ACTIVE' AND holiday_date BETWEEN ? AND ? ORDER BY holiday_date, holiday_key", [$companyKeyHash, $from, $to]) ?: [];
    $assignments = $db->GetAll("SELECT holiday_list_assignment_key, employee_key, holiday_list_key, effective_from, effective_until, assignment_status
        FROM project_company_hr_holiday_list_assignment WHERE company_key_hash = ? AND assignment_status = 'ACTIVE' AND effective_from <= ? AND (effective_until IS NULL OR effective_until >= ?) ORDER BY employee_key, effective_from", [$companyKeyHash, $to, $from]) ?: [];
    $leaveRanges = $db->GetAll("SELECT leave_application_key, employee_key, leave_type_key, from_date, to_date, total_leave_days, application_status
        FROM project_company_hr_leave_application WHERE company_key_hash = ? AND application_status = 'APPROVED' AND from_date <= ? AND to_date >= ? ORDER BY from_date, employee_key", [$companyKeyHash, $to, $from]) ?: [];
    return [
        'contract' => 'hr.calendar-directory.v1', 'company_key_hash' => $companyKeyHash,
        'range' => ['from_date' => $from, 'to_date' => $to], 'holiday_lists' => $lists, 'holidays' => $holidays,
        'assignments' => $assignments, 'leave_ranges' => $leaveRanges,
        'availability' => ['status' => 'AVAILABLE', 'authoritative' => true],
    ];
}

function yovel_admin_hr_leave_data(array $company): array
{
    yovel_admin_hr_schema();
    $hash = (string) ($company['company_key_hash'] ?? '');
    $db = bx_db();
    return [
        'leaveTypes' => $db->GetAll("SELECT * FROM project_company_hr_leave_type WHERE company_key_hash = ? AND leave_type_status <> 'INACTIVE' ORDER BY leave_type_name", [$hash]) ?: [],
        'leavePeriods' => $db->GetAll("SELECT * FROM project_company_hr_leave_period WHERE company_key_hash = ? ORDER BY from_date DESC", [$hash]) ?: [],
        'holidayLists' => $db->GetAll("SELECT * FROM project_company_hr_holiday_list WHERE company_key_hash = ? ORDER BY holiday_list_name", [$hash]) ?: [],
        'leavePolicies' => $db->GetAll("SELECT * FROM project_company_hr_leave_policy WHERE company_key_hash = ? ORDER BY policy_name", [$hash]) ?: [],
        'applications' => $db->GetAll("SELECT application.*, employee.employee_code, employee.employee_name, leave_type.leave_type_name FROM project_company_hr_leave_application application INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = application.company_key_hash AND employee.employee_key = application.employee_key INNER JOIN project_company_hr_leave_type leave_type ON leave_type.company_key_hash = application.company_key_hash AND leave_type.leave_type_key = application.leave_type_key WHERE application.company_key_hash = ? ORDER BY application.created_at DESC LIMIT 300", [$hash]) ?: [],
        'allocations' => $db->GetAll("SELECT allocation.*, employee.employee_code, employee.employee_name, leave_type.leave_type_name FROM project_company_hr_leave_allocation allocation INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = allocation.company_key_hash AND employee.employee_key = allocation.employee_key INNER JOIN project_company_hr_leave_type leave_type ON leave_type.company_key_hash = allocation.company_key_hash AND leave_type.leave_type_key = allocation.leave_type_key WHERE allocation.company_key_hash = ? ORDER BY allocation.created_at DESC LIMIT 300", [$hash]) ?: [],
        'ledger' => $db->GetAll("SELECT ledger.*, employee.employee_code, employee.employee_name, leave_type.leave_type_name FROM project_company_hr_leave_ledger_entry ledger INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = ledger.company_key_hash AND employee.employee_key = ledger.employee_key INNER JOIN project_company_hr_leave_type leave_type ON leave_type.company_key_hash = ledger.company_key_hash AND leave_type.leave_type_key = ledger.leave_type_key WHERE ledger.company_key_hash = ? ORDER BY ledger.posting_date DESC, ledger.x_id DESC LIMIT 500", [$hash]) ?: [],
        'compensatoryRequests' => $db->GetAll("SELECT request.*, employee.employee_name, leave_type.leave_type_name FROM project_company_hr_compensatory_leave_request request INNER JOIN project_company_hr_employee employee ON employee.company_key_hash = request.company_key_hash AND employee.employee_key = request.employee_key INNER JOIN project_company_hr_leave_type leave_type ON leave_type.company_key_hash = request.company_key_hash AND leave_type.leave_type_key = request.leave_type_key WHERE request.company_key_hash = ? ORDER BY request.created_at DESC LIMIT 300", [$hash]) ?: [],
        'calendar' => yovel_admin_hr_calendar_read_contract($company, ['from_date' => date('Y-01-01'), 'to_date' => date('Y-12-31')]),
        'formTargets' => yovel_admin_hr_leave_form_targets(),
    ];
}

function yovel_admin_hr_handle_leave_post(array $company, array $admin, string $action, array $input): array
{
    $db = bx_db();
    $saved = match ($action) {
        'hr_leave_save_master' => yovel_admin_persist_leave_master($db, $company, $admin, (string) ($input['record_type'] ?? ''), $input),
        'hr_leave_save_policy' => yovel_admin_persist_leave_policy($db, $company, $admin, array_merge($input, ['details' => json_decode((string) ($input['details_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR)])),
        'hr_leave_save_policy_assignment' => yovel_admin_persist_leave_policy_assignment($db, $company, $admin, $input),
        'hr_leave_save_holiday_assignment' => yovel_admin_persist_holiday_list_assignment($db, $company, $admin, $input),
        'hr_leave_save_allocation' => yovel_admin_persist_leave_allocation($db, $company, $admin, $input),
        'hr_leave_bulk_allocation' => yovel_admin_bulk_leave_allocation($db, $company, $admin, is_array($input['employee_keys'] ?? null) ? $input['employee_keys'] : [], $input),
        'hr_leave_save_application' => yovel_admin_persist_leave_application($db, $company, $admin, $input),
        'hr_leave_transition_application' => yovel_admin_transition_leave_application($db, $company, $admin, (string) ($input['leave_application_key'] ?? ''), (string) ($input['application_status'] ?? '')),
        'hr_leave_save_adjustment' => yovel_admin_persist_leave_adjustment($db, $company, $admin, $input),
        'hr_leave_save_compensatory' => yovel_admin_persist_compensatory_leave_request($db, $company, $admin, $input),
        'hr_leave_transition_compensatory' => yovel_admin_transition_compensatory_leave_request($db, $company, $admin, (string) ($input['compensatory_leave_request_key'] ?? ''), (string) ($input['request_status'] ?? '')),
        'hr_leave_save_encashment' => yovel_admin_persist_leave_encashment($db, $company, $admin, $input),
        default => throw new InvalidArgumentException('This Leave action is not available.'),
    };
    $recordKey = '';
    foreach (['leave_application_key', 'leave_allocation_key', 'leave_type_key', 'leave_period_key', 'holiday_list_key', 'holiday_key', 'leave_policy_key', 'leave_policy_assignment_key', 'holiday_list_assignment_key', 'leave_adjustment_key', 'compensatory_leave_request_key', 'leave_encashment_key', 'earned_leave_schedule_key', 'leave_block_list_key'] as $key) {
        if (!empty($saved[$key])) { $recordKey = (string) $saved[$key]; break; }
    }
    return ['message' => 'Leave record saved.', 'section' => (string) ($input['section'] ?? 'leave-requests'), 'query' => ['record' => $recordKey]];
}
