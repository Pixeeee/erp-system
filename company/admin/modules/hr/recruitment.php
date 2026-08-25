<?php
declare(strict_types=1);

function yovel_admin_hr_recruitment_form_targets(): array
{
    return [
        'job-requisition' => ['label' => 'Job Requisition', 'protected_fields' => ['job_requisition_key', 'requisition_code', 'job_position_key', 'requisition_status'], 'versioned' => true],
        'job-opening' => ['label' => 'Job Opening', 'protected_fields' => ['job_opening_key', 'opening_code', 'job_requisition_key', 'job_position_key', 'opening_status'], 'versioned' => true],
        'job-applicant' => ['label' => 'Job Applicant', 'protected_fields' => ['job_applicant_key', 'job_opening_key', 'email_address', 'applicant_status'], 'versioned' => true],
        'interview' => ['label' => 'Interview', 'protected_fields' => ['interview_key', 'job_applicant_key', 'scheduled_at', 'interview_status'], 'versioned' => true],
        'interview-feedback' => ['label' => 'Interview Feedback', 'protected_fields' => ['interview_feedback_key', 'interview_key', 'interviewer_employee_key', 'rating', 'recommendation'], 'versioned' => true],
        'job-offer' => ['label' => 'Job Offer', 'protected_fields' => ['job_offer_key', 'offer_code', 'job_applicant_key', 'offer_status'], 'versioned' => true],
        'staffing-plan' => ['label' => 'Staffing Plan', 'protected_fields' => ['staffing_plan_key', 'plan_code', 'from_date', 'to_date', 'plan_status'], 'versioned' => true],
    ];
}

function yovel_admin_hr_recruitment_default_form_fields(): array
{
    return [
        'job-requisition' => [
            ['requisition_code', 'Requisition code', 'TEXT', 'overview', 10, 1, 1, ''], ['department_key', 'Department', 'SELECT', 'overview', 20, 1, 0, ''],
            ['job_position_key', 'Job position', 'SELECT', 'overview', 30, 1, 1, ''], ['staffing_plan_key', 'Staffing plan', 'SELECT', 'planning', 40, 1, 0, ''],
            ['requested_positions', 'Requested positions', 'NUMBER', 'planning', 50, 1, 0, ''], ['required_by_date', 'Required by', 'DATE', 'planning', 60, 1, 0, ''],
            ['reason', 'Reason', 'TEXTAREA', 'details', 70, 0, 0, ''], ['requisition_status', 'Status', 'SELECT', 'approval', 80, 1, 1, "DRAFT\nSUBMITTED\nAPPROVED\nREJECTED\nCANCELLED"],
        ],
        'job-opening' => [
            ['opening_code', 'Opening code', 'TEXT', 'overview', 10, 1, 1, ''], ['job_requisition_key', 'Job requisition', 'SELECT', 'overview', 20, 1, 1, ''],
            ['department_key', 'Department', 'SELECT', 'overview', 30, 1, 0, ''], ['job_position_key', 'Job position', 'SELECT', 'overview', 40, 1, 1, ''],
            ['vacancies', 'Vacancies', 'NUMBER', 'planning', 50, 1, 0, ''], ['opening_date', 'Opening date', 'DATE', 'dates', 60, 1, 0, ''],
            ['closing_date', 'Closing date', 'DATE', 'dates', 70, 0, 0, ''], ['description', 'Description', 'TEXTAREA', 'details', 80, 0, 0, ''],
            ['opening_status', 'Status', 'SELECT', 'overview', 90, 1, 1, "DRAFT\nOPEN\nCLOSED\nCANCELLED"],
        ],
        'job-applicant' => [
            ['job_opening_key', 'Job opening', 'SELECT', 'overview', 10, 1, 1, ''], ['applicant_name', 'Applicant name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['email_address', 'Email', 'EMAIL', 'contact', 30, 1, 1, ''], ['phone_number', 'Phone', 'PHONE', 'contact', 40, 0, 0, ''],
            ['job_applicant_source_key', 'Source', 'SELECT', 'source', 50, 0, 0, ''], ['cover_letter', 'Cover letter', 'TEXTAREA', 'details', 60, 0, 0, ''],
            ['applicant_status', 'Status', 'SELECT', 'overview', 70, 1, 1, "OPEN\nSCREENING\nINTERVIEW\nOFFERED\nHIRED\nREJECTED\nWITHDRAWN"],
        ],
        'interview' => [
            ['job_applicant_key', 'Applicant', 'SELECT', 'overview', 10, 1, 1, ''], ['interview_type_key', 'Interview type', 'SELECT', 'overview', 20, 1, 0, ''],
            ['scheduled_at', 'Scheduled at', 'TEXT', 'schedule', 30, 1, 1, ''], ['timezone_name', 'Time zone', 'TEXT', 'schedule', 40, 1, 0, 'Asia/Manila'],
            ['interview_status', 'Status', 'SELECT', 'overview', 50, 1, 1, "DRAFT\nSCHEDULED\nCOMPLETED\nCANCELLED"],
        ],
        'interview-feedback' => [
            ['interview_key', 'Interview', 'SELECT', 'overview', 10, 1, 1, ''], ['interviewer_employee_key', 'Interviewer', 'SELECT', 'overview', 20, 1, 1, ''],
            ['rating', 'Rating', 'NUMBER', 'assessment', 30, 1, 1, ''], ['recommendation', 'Recommendation', 'SELECT', 'assessment', 40, 1, 1, "HIRE\nHOLD\nREJECT"],
            ['feedback', 'Feedback', 'TEXTAREA', 'assessment', 50, 1, 0, ''],
        ],
        'job-offer' => [
            ['offer_code', 'Offer code', 'TEXT', 'overview', 10, 1, 1, ''], ['job_applicant_key', 'Applicant', 'SELECT', 'overview', 20, 1, 1, ''],
            ['offer_date', 'Offer date', 'DATE', 'dates', 30, 1, 0, ''], ['valid_until', 'Valid until', 'DATE', 'dates', 40, 0, 0, ''],
            ['designation', 'Designation', 'TEXT', 'details', 50, 1, 0, ''], ['offer_status', 'Status', 'SELECT', 'approval', 60, 1, 1, "DRAFT\nSUBMITTED\nACCEPTED\nREJECTED\nWITHDRAWN\nAMENDED"],
        ],
        'staffing-plan' => [
            ['plan_code', 'Plan code', 'TEXT', 'overview', 10, 1, 1, ''], ['plan_name', 'Plan name', 'TEXT', 'overview', 20, 1, 0, ''],
            ['from_date', 'From date', 'DATE', 'dates', 30, 1, 1, ''], ['to_date', 'To date', 'DATE', 'dates', 40, 1, 1, ''],
            ['plan_status', 'Status', 'SELECT', 'overview', 50, 1, 1, "DRAFT\nACTIVE\nCLOSED\nCANCELLED"],
        ],
    ];
}

function yovel_admin_hr_ensure_recruitment_schema(ADOConnection $db): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_hr_job_applicant_source (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_applicant_source_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, source_code VARCHAR(80) NOT NULL, source_name VARCHAR(160) NOT NULL, source_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_applicant_source_code (company_key_hash, source_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_interview_type (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, interview_type_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, interview_type_code VARCHAR(80) NOT NULL, interview_type_name VARCHAR(160) NOT NULL, interview_type_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_interview_type_code (company_key_hash, interview_type_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_offer_term (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, offer_term_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, offer_term_code VARCHAR(80) NOT NULL, offer_term_name VARCHAR(160) NOT NULL, offer_term_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_offer_term_code (company_key_hash, offer_term_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_job_opening_template (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_opening_template_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, template_code VARCHAR(80) NOT NULL, template_name VARCHAR(160) NOT NULL, description TEXT NULL, template_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_opening_template_code (company_key_hash, template_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_job_offer_term_template (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_offer_term_template_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, template_code VARCHAR(80) NOT NULL, template_name VARCHAR(160) NOT NULL, template_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_offer_term_template_code (company_key_hash, template_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_job_offer_term_template_detail (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_offer_term_template_detail_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, job_offer_term_template_key CHAR(36) NOT NULL, offer_term_key CHAR(36) NOT NULL, term_value TEXT NOT NULL, sort_order INT NOT NULL DEFAULT 0, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_offer_term_template_detail (company_key_hash, job_offer_term_template_key, offer_term_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_appointment_letter_template (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, appointment_letter_template_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, template_code VARCHAR(80) NOT NULL, template_name VARCHAR(160) NOT NULL, template_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_letter_template_code (company_key_hash, template_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_appointment_letter_content (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, appointment_letter_content_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, appointment_letter_template_key CHAR(36) NOT NULL, content_heading VARCHAR(180) NOT NULL, content_body TEXT NOT NULL, sort_order INT NOT NULL DEFAULT 0, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_hr_letter_content (company_key_hash, appointment_letter_template_key, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_staffing_plan (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, staffing_plan_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, plan_code VARCHAR(80) NOT NULL, plan_name VARCHAR(160) NOT NULL, from_date DATE NOT NULL, to_date DATE NOT NULL, plan_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_staffing_plan_code (company_key_hash, plan_code), INDEX idx_hr_staffing_plan_dates (company_key_hash, from_date, to_date, plan_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_staffing_plan_detail (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, staffing_plan_detail_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, staffing_plan_key CHAR(36) NOT NULL, job_position_key CHAR(36) NOT NULL, planned_positions INT UNSIGNED NOT NULL, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_staffing_plan_detail (company_key_hash, staffing_plan_key, job_position_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_job_requisition (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_requisition_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, requisition_code VARCHAR(80) NOT NULL, staffing_plan_key CHAR(36) NOT NULL, department_key CHAR(36) NOT NULL, job_position_key CHAR(36) NOT NULL, requested_positions INT UNSIGNED NOT NULL, required_by_date DATE NOT NULL, reason TEXT NULL, requisition_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', immutable_checksum CHAR(64) NOT NULL, approved_by_admin_key CHAR(36) NULL, approved_at DATETIME NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_requisition_code (company_key_hash, requisition_code), INDEX idx_hr_requisition_plan (company_key_hash, staffing_plan_key, job_position_key, requisition_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_job_opening (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_opening_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, opening_code VARCHAR(80) NOT NULL, job_requisition_key CHAR(36) NOT NULL, job_opening_template_key CHAR(36) NULL, department_key CHAR(36) NOT NULL, job_position_key CHAR(36) NOT NULL, vacancies INT UNSIGNED NOT NULL, opening_date DATE NOT NULL, closing_date DATE NULL, description TEXT NULL, opening_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_opening_code (company_key_hash, opening_code), INDEX idx_hr_opening_requisition (company_key_hash, job_requisition_key, opening_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_job_applicant (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_applicant_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, job_opening_key CHAR(36) NOT NULL, job_applicant_source_key CHAR(36) NULL, applicant_name VARCHAR(180) NOT NULL, email_address VARCHAR(254) NOT NULL, phone_number VARCHAR(60) NULL, cover_letter TEXT NULL, applicant_status VARCHAR(20) NOT NULL DEFAULT 'OPEN', immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_applicant_email_opening (company_key_hash, job_opening_key, email_address), INDEX idx_hr_applicant_status (company_key_hash, applicant_status, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_employee_referral (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_referral_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, employee_key CHAR(36) NOT NULL, job_applicant_key CHAR(36) NOT NULL, referral_notes TEXT NULL, referral_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_employee_referral (company_key_hash, employee_key, job_applicant_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_interview (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, interview_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, job_applicant_key CHAR(36) NOT NULL, interview_type_key CHAR(36) NOT NULL, scheduled_at DATETIME NOT NULL, timezone_name VARCHAR(80) NOT NULL, interview_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', immutable_checksum CHAR(64) NOT NULL, completed_at DATETIME NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_hr_interview_schedule (company_key_hash, scheduled_at, interview_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_interview_detail (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, interview_detail_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, interview_key CHAR(36) NOT NULL, employee_key CHAR(36) NOT NULL, role_label VARCHAR(120) NOT NULL, sort_order INT NOT NULL DEFAULT 0, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_interview_detail (company_key_hash, interview_key, employee_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_interviewer (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, interviewer_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, interview_key CHAR(36) NOT NULL, employee_key CHAR(36) NOT NULL, interviewer_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_interviewer (company_key_hash, interview_key, employee_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_interview_feedback (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, interview_feedback_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, interview_key CHAR(36) NOT NULL, interviewer_employee_key CHAR(36) NOT NULL, rating DECIMAL(4,2) NOT NULL, feedback TEXT NOT NULL, recommendation VARCHAR(20) NOT NULL, immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_interview_feedback (company_key_hash, interview_key, interviewer_employee_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_job_offer (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_offer_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, offer_code VARCHAR(80) NOT NULL, job_applicant_key CHAR(36) NOT NULL, appointment_letter_template_key CHAR(36) NULL, offer_date DATE NOT NULL, valid_until DATE NULL, designation VARCHAR(180) NOT NULL, offer_status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', immutable_checksum CHAR(64) NOT NULL, decision_by_admin_key CHAR(36) NULL, decision_at DATETIME NULL, created_by_admin_key CHAR(36) NULL, updated_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_job_offer_code (company_key_hash, offer_code), INDEX idx_hr_job_offer_applicant (company_key_hash, job_applicant_key, offer_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_hr_job_offer_term (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_offer_term_key CHAR(36) NOT NULL UNIQUE, company_key CHAR(36) NOT NULL, company_key_hash CHAR(64) NOT NULL, job_offer_key CHAR(36) NOT NULL, offer_term_key CHAR(36) NOT NULL, term_label VARCHAR(180) NOT NULL, term_value TEXT NOT NULL, sort_order INT NOT NULL DEFAULT 0, immutable_checksum CHAR(64) NOT NULL, created_by_admin_key CHAR(36) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_job_offer_term (company_key_hash, job_offer_key, offer_term_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) { yovel_admin_db_execute($db, $statement, [], 'HR Recruitment schema update'); }
}

function yovel_admin_hr_recruitment_readback(ADOConnection $db, string $table, string $column, string $hash, string $key): array
{
    $allowed = [
        'project_company_hr_job_applicant_source' => 'job_applicant_source_key', 'project_company_hr_interview_type' => 'interview_type_key',
        'project_company_hr_offer_term' => 'offer_term_key', 'project_company_hr_job_opening_template' => 'job_opening_template_key',
        'project_company_hr_job_offer_term_template' => 'job_offer_term_template_key', 'project_company_hr_appointment_letter_template' => 'appointment_letter_template_key',
        'project_company_hr_staffing_plan' => 'staffing_plan_key', 'project_company_hr_job_requisition' => 'job_requisition_key',
        'project_company_hr_job_opening' => 'job_opening_key', 'project_company_hr_job_applicant' => 'job_applicant_key',
        'project_company_hr_employee_referral' => 'employee_referral_key', 'project_company_hr_interview' => 'interview_key',
        'project_company_hr_interview_feedback' => 'interview_feedback_key', 'project_company_hr_job_offer' => 'job_offer_key',
    ];
    if (($allowed[$table] ?? '') !== $column) { throw new LogicException('Recruitment read-back target is invalid.'); }
    $row = $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash = ? AND {$column} = ? LIMIT 1", [$hash, $key]);
    if (!is_array($row) || $row === [] || !hash_equals($key, (string) ($row[$column] ?? ''))) { throw new RuntimeException('Recruitment record read-back failed.'); }
    return $row;
}

function yovel_admin_hr_recruitment_datetime(string $value, string $timezone): array
{
    $zone = yovel_admin_hr_timezone($timezone);
    $date = yovel_admin_hr_local_datetime($value, $zone, 'Scheduled at');
    return [$date->format('Y-m-d H:i:s'), $zone->getName()];
}

function yovel_admin_persist_recruitment_master(ADOConnection $db, array $company, array $admin, string $recordType, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $recordType = strtoupper(trim($recordType));
    $definitions = [
        'JOB_APPLICANT_SOURCE' => ['project_company_hr_job_applicant_source', 'job_applicant_source_key', 'source_code', 'source_name', 'source_status'],
        'INTERVIEW_TYPE' => ['project_company_hr_interview_type', 'interview_type_key', 'interview_type_code', 'interview_type_name', 'interview_type_status'],
        'OFFER_TERM' => ['project_company_hr_offer_term', 'offer_term_key', 'offer_term_code', 'offer_term_name', 'offer_term_status'],
        'JOB_OPENING_TEMPLATE' => ['project_company_hr_job_opening_template', 'job_opening_template_key', 'template_code', 'template_name', 'template_status'],
        'JOB_OFFER_TERM_TEMPLATE' => ['project_company_hr_job_offer_term_template', 'job_offer_term_template_key', 'template_code', 'template_name', 'template_status'],
        'APPOINTMENT_LETTER_TEMPLATE' => ['project_company_hr_appointment_letter_template', 'appointment_letter_template_key', 'template_code', 'template_name', 'template_status'],
    ];
    if (!isset($definitions[$recordType])) { throw new InvalidArgumentException('Recruitment master type is invalid.'); }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $recordType, $input, $failureInjector, $definitions): array {
        [$table, $keyColumn, $codeColumn, $nameColumn, $statusColumn] = $definitions[$recordType];
        $providedKey = trim((string) ($input[$keyColumn] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_setup_key($db, $hash, $table, $keyColumn, $providedKey, 'Recruitment master');
            $existing = $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash = ? AND {$keyColumn} = ? FOR UPDATE", [$hash, $providedKey]);
        }
        $key = $providedKey ?: bx_uuid();
        $code = yovel_admin_hr_leave_code($input[$codeColumn] ?? '', ucwords(str_replace('_', ' ', $codeColumn)));
        $name = yovel_admin_hr_leave_text($input[$nameColumn] ?? '', ucwords(str_replace('_', ' ', $nameColumn)));
        $status = yovel_admin_status((string) ($input[$statusColumn] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE'], 'ACTIVE');
        if ($recordType === 'JOB_OPENING_TEMPLATE') {
            $description = yovel_admin_hr_leave_text($input['description'] ?? '', 'Description', 8000, false);
            yovel_admin_db_execute($db, "INSERT INTO {$table} ({$keyColumn}, company_key, company_key_hash, {$codeColumn}, {$nameColumn}, description, {$statusColumn}, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE {$nameColumn}=VALUES({$nameColumn}), description=VALUES(description), {$statusColumn}=VALUES({$statusColumn}), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $hash, $code, $name, $description ?: null, $status, $adminKey, $adminKey], 'Job Opening Template save');
        } else {
            yovel_admin_db_execute($db, "INSERT INTO {$table} ({$keyColumn}, company_key, company_key_hash, {$codeColumn}, {$nameColumn}, {$statusColumn}, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE {$nameColumn}=VALUES({$nameColumn}), {$statusColumn}=VALUES({$statusColumn}), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $hash, $code, $name, $status, $adminKey, $adminKey], 'Recruitment master save');
        }
        if ($recordType === 'JOB_OFFER_TERM_TEMPLATE') {
            yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_job_offer_term_template_detail WHERE company_key_hash = ? AND job_offer_term_template_key = ?', [$hash, $key], 'Offer Term Template details replace');
            $details = is_array($input['details'] ?? null) ? $input['details'] : [];
            if ($details === []) { throw new InvalidArgumentException('At least one offer term template detail is required.'); }
            $seen = [];
            foreach ($details as $detail) {
                if (!is_array($detail)) { continue; }
                $termKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_offer_term', 'offer_term_key', $detail['offer_term_key'] ?? '', 'Offer Term', true);
                if (isset($seen[$termKey])) { throw new InvalidArgumentException('An offer term can appear once in a template.'); }
                $seen[$termKey] = true;
                $value = yovel_admin_hr_leave_text($detail['term_value'] ?? '', 'Term value', 8000);
                $sort = max(0, min(10000, (int) ($detail['sort_order'] ?? 0)));
                yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_job_offer_term_template_detail (job_offer_term_template_detail_key, company_key, company_key_hash, job_offer_term_template_key, offer_term_key, term_value, sort_order, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $hash, $key, $termKey, $value, $sort, $adminKey], 'Offer Term Template Detail save');
            }
        }
        if ($recordType === 'APPOINTMENT_LETTER_TEMPLATE') {
            yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_appointment_letter_content WHERE company_key_hash = ? AND appointment_letter_template_key = ?', [$hash, $key], 'Appointment Letter content replace');
            $content = is_array($input['content'] ?? null) ? $input['content'] : [];
            if ($content === []) { throw new InvalidArgumentException('Appointment Letter Template requires content.'); }
            foreach ($content as $entry) {
                if (!is_array($entry)) { continue; }
                $heading = yovel_admin_hr_leave_text($entry['content_heading'] ?? '', 'Content heading', 180);
                $body = yovel_admin_hr_leave_text($entry['content_body'] ?? '', 'Content body', 12000);
                $sort = max(0, min(10000, (int) ($entry['sort_order'] ?? 0)));
                yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_appointment_letter_content (appointment_letter_content_key, company_key, company_key_hash, appointment_letter_template_key, content_heading, content_body, sort_order, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $hash, $key, $heading, $body, $sort, $adminKey], 'Appointment Letter content save');
            }
        }
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_recruitment_readback($db, $table, $keyColumn, $hash, $key);
        bx_audit($existing ? 'UPDATE' : 'CREATE', $table, $key, ['company_key' => $companyKey, 'record_type' => $recordType, 'record_status' => $status], 'Company administrator saved a Recruitment master.');
        return $saved;
    });
}

function yovel_admin_persist_staffing_plan(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $details = is_array($input['details'] ?? null) ? $input['details'] : [];
    if ($details === []) { throw new InvalidArgumentException('Staffing Plan requires at least one position.'); }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $input, $details, $failureInjector): array {
        $providedKey = trim((string) ($input['staffing_plan_key'] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_staffing_plan', 'staffing_plan_key', $providedKey, 'Staffing Plan');
            $existing = $db->GetRow('SELECT * FROM project_company_hr_staffing_plan WHERE company_key_hash = ? AND staffing_plan_key = ? FOR UPDATE', [$hash, $providedKey]);
            if (in_array((string) ($existing['plan_status'] ?? ''), ['CLOSED', 'CANCELLED'], true)) { throw new RuntimeException('Closed Staffing Plans are immutable.'); }
        }
        $key = $providedKey ?: bx_uuid();
        $code = yovel_admin_hr_leave_code($input['plan_code'] ?? '', 'Plan code');
        $name = yovel_admin_hr_leave_text($input['plan_name'] ?? '', 'Plan name');
        [$from, $to] = yovel_admin_hr_leave_dates($input);
        $status = yovel_admin_status((string) ($input['plan_status'] ?? 'DRAFT'), ['DRAFT', 'ACTIVE', 'CLOSED', 'CANCELLED'], 'DRAFT');
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_staffing_plan (staffing_plan_key, company_key, company_key_hash, plan_code, plan_name, from_date, to_date, plan_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE plan_name=VALUES(plan_name), from_date=VALUES(from_date), to_date=VALUES(to_date), plan_status=VALUES(plan_status), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $hash, $code, $name, $from, $to, $status, $adminKey, $adminKey], 'Staffing Plan save');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_staffing_plan_detail WHERE company_key_hash = ? AND staffing_plan_key = ?', [$hash, $key], 'Staffing Plan details replace');
        $seen = [];
        foreach ($details as $detail) {
            if (!is_array($detail)) { continue; }
            $positionKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_position', 'job_position_key', $detail['job_position_key'] ?? '', 'Job Position', true);
            if (isset($seen[$positionKey])) { throw new InvalidArgumentException('A job position can appear once in a Staffing Plan.'); }
            $seen[$positionKey] = true;
            $planned = filter_var($detail['planned_positions'] ?? null, FILTER_VALIDATE_INT);
            if ($planned === false || $planned < 1 || $planned > 100000) { throw new InvalidArgumentException('Planned positions must be from 1 to 100000.'); }
            yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_staffing_plan_detail (staffing_plan_detail_key, company_key, company_key_hash, staffing_plan_key, job_position_key, planned_positions, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $hash, $key, $positionKey, $planned, $adminKey], 'Staffing Plan Detail save');
        }
        if ($failureInjector) { $failureInjector(); }
        if ((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_staffing_plan_detail WHERE company_key_hash = ? AND staffing_plan_key = ?', [$hash, $key]) !== count($seen)) { throw new RuntimeException('Staffing Plan details could not be verified.'); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_staffing_plan', 'staffing_plan_key', $hash, $key);
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_staffing_plan', $key, ['company_key' => $companyKey, 'plan_status' => $status, 'detail_count' => count($seen)], 'Company administrator saved a Staffing Plan.');
        return $saved;
    });
}

function yovel_admin_hr_recruitment_required_date(mixed $value, string $label): string
{
    $date = yovel_admin_optional_date(trim((string) $value), $label);
    if ($date === '') { throw new InvalidArgumentException($label . ' is required.'); }
    return $date;
}

function yovel_admin_hr_recruitment_positive_int(mixed $value, string $label): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || $number < 1 || $number > 100000) {
        throw new InvalidArgumentException($label . ' must be from 1 to 100000.');
    }
    return $number;
}

function yovel_admin_persist_job_requisition(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $hash, $adminKey] = $scope;
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $providedKey = trim((string) ($input['job_requisition_key'] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_requisition', 'job_requisition_key', $providedKey, 'Job Requisition', true);
            $existing = $db->GetRow('SELECT * FROM project_company_hr_job_requisition WHERE company_key_hash = ? AND job_requisition_key = ? FOR UPDATE', [$hash, $providedKey]);
            if ((string) ($existing['requisition_status'] ?? '') !== 'DRAFT') { throw new RuntimeException('Submitted Job Requisitions are immutable.'); }
        }
        $key = $providedKey ?: bx_uuid();
        $code = yovel_admin_hr_leave_code($input['requisition_code'] ?? '', 'Requisition code');
        $planKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_staffing_plan', 'staffing_plan_key', $input['staffing_plan_key'] ?? '', 'Staffing Plan', true);
        $departmentKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_department', 'department_key', $input['department_key'] ?? '', 'Department', true);
        $positionKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_position', 'job_position_key', $input['job_position_key'] ?? '', 'Job Position', true);
        $plan = $db->GetRow('SELECT * FROM project_company_hr_staffing_plan WHERE company_key_hash = ? AND staffing_plan_key = ? FOR UPDATE', [$hash, $planKey]);
        if (!is_array($plan) || !in_array((string) ($plan['plan_status'] ?? ''), ['DRAFT', 'ACTIVE'], true)) { throw new RuntimeException('The Staffing Plan is not available for requisitions.'); }
        $planDetail = $db->GetRow('SELECT * FROM project_company_hr_staffing_plan_detail WHERE company_key_hash = ? AND staffing_plan_key = ? AND job_position_key = ? FOR UPDATE', [$hash, $planKey, $positionKey]);
        if (!is_array($planDetail) || $planDetail === []) { throw new InvalidArgumentException('The selected position is not included in the Staffing Plan.'); }
        $requested = yovel_admin_hr_recruitment_positive_int($input['requested_positions'] ?? null, 'Requested positions');
        if ($requested > (int) $planDetail['planned_positions']) { throw new InvalidArgumentException('Requested positions exceed the Staffing Plan.'); }
        $requiredBy = yovel_admin_hr_recruitment_required_date($input['required_by_date'] ?? '', 'Required by date');
        $reason = yovel_admin_hr_leave_text($input['reason'] ?? '', 'Reason', 8000, false);
        $status = yovel_admin_status((string) ($input['requisition_status'] ?? 'DRAFT'), ['DRAFT', 'SUBMITTED'], 'DRAFT');
        $checksum = yovel_admin_hr_leave_checksum(compact('code', 'planKey', 'departmentKey', 'positionKey', 'requested', 'requiredBy', 'reason', 'status'));
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_job_requisition (job_requisition_key, company_key, company_key_hash, requisition_code, staffing_plan_key, department_key, job_position_key, requested_positions, required_by_date, reason, requisition_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE staffing_plan_key=VALUES(staffing_plan_key), department_key=VALUES(department_key), job_position_key=VALUES(job_position_key), requested_positions=VALUES(requested_positions), required_by_date=VALUES(required_by_date), reason=VALUES(reason), requisition_status=VALUES(requisition_status), immutable_checksum=VALUES(immutable_checksum), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $hash, $code, $planKey, $departmentKey, $positionKey, $requested, $requiredBy, $reason ?: null, $status, $checksum, $adminKey, $adminKey], 'Job Requisition save');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_job_requisition', 'job_requisition_key', $hash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Job Requisition verification failed.'); }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_job_requisition', $key, ['company_key' => $companyKey, 'requisition_status' => $status], 'Company administrator saved a Job Requisition.');
        return $saved;
    });
}

function yovel_admin_transition_job_requisition(ADOConnection $db, array $company, array $admin, string $requisitionKey, string $status, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $hash, $adminKey] = $scope;
    $status = yovel_admin_status($status, ['APPROVED', 'REJECTED', 'CANCELLED'], '');
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $hash, $adminKey, $requisitionKey, $status, $failureInjector): array {
        if (!yovel_admin_is_uuid($requisitionKey)) { throw new InvalidArgumentException('Job Requisition is invalid.'); }
        $row = $db->GetRow('SELECT * FROM project_company_hr_job_requisition WHERE company_key_hash = ? AND job_requisition_key = ? FOR UPDATE', [$hash, $requisitionKey]);
        if (!is_array($row) || $row === []) { throw new InvalidArgumentException('Job Requisition was not found.'); }
        if ((string) $row['requisition_status'] !== 'SUBMITTED') { throw new RuntimeException('Only submitted Job Requisitions may be reviewed.'); }
        yovel_admin_db_execute($db, 'UPDATE project_company_hr_job_requisition SET requisition_status = ?, approved_by_admin_key = ?, approved_at = NOW(), updated_by_admin_key = ? WHERE company_key_hash = ? AND job_requisition_key = ?', [$status, $adminKey, $adminKey, $hash, $requisitionKey], 'Job Requisition transition');
        yovel_admin_hr_leave_notification_intent($db, $scope, $requisitionKey, 'JOB_REQUISITION_' . $status, ['requisition_status' => $status, 'job_position_key' => $row['job_position_key']]);
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_job_requisition', 'job_requisition_key', $hash, $requisitionKey);
        if ((string) $saved['requisition_status'] !== $status) { throw new RuntimeException('Job Requisition transition verification failed.'); }
        bx_audit($status, 'project_company_hr_job_requisition', $requisitionKey, ['company_key' => $companyKey, 'requisition_status' => $status], 'Company administrator reviewed a Job Requisition.');
        return $saved;
    });
}

function yovel_admin_persist_job_opening(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $providedKey = trim((string) ($input['job_opening_key'] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_opening', 'job_opening_key', $providedKey, 'Job Opening', true);
            $existing = $db->GetRow('SELECT * FROM project_company_hr_job_opening WHERE company_key_hash = ? AND job_opening_key = ? FOR UPDATE', [$hash, $providedKey]);
            if ((string) ($existing['opening_status'] ?? '') !== 'DRAFT') { throw new RuntimeException('Published Job Openings are immutable.'); }
        }
        $key = $providedKey ?: bx_uuid();
        $requisitionKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_requisition', 'job_requisition_key', $input['job_requisition_key'] ?? '', 'Job Requisition', true);
        $requisition = $db->GetRow('SELECT * FROM project_company_hr_job_requisition WHERE company_key_hash = ? AND job_requisition_key = ? FOR UPDATE', [$hash, $requisitionKey]);
        if (!is_array($requisition) || (string) ($requisition['requisition_status'] ?? '') !== 'APPROVED') { throw new RuntimeException('An approved Job Requisition is required.'); }
        $departmentKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_department', 'department_key', $input['department_key'] ?? '', 'Department', true);
        $positionKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_position', 'job_position_key', $input['job_position_key'] ?? '', 'Job Position', true);
        if ($departmentKey !== (string) $requisition['department_key'] || $positionKey !== (string) $requisition['job_position_key']) { throw new InvalidArgumentException('Opening assignment must match its approved requisition.'); }
        $templateKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_opening_template', 'job_opening_template_key', $input['job_opening_template_key'] ?? '', 'Job Opening Template');
        $vacancies = yovel_admin_hr_recruitment_positive_int($input['vacancies'] ?? null, 'Vacancies');
        $planDetail = $db->GetRow('SELECT * FROM project_company_hr_staffing_plan_detail WHERE company_key_hash = ? AND staffing_plan_key = ? AND job_position_key = ? FOR UPDATE', [$hash, $requisition['staffing_plan_key'], $positionKey]);
        if (!is_array($planDetail) || $planDetail === []) { throw new RuntimeException('Staffing Plan coverage is unavailable.'); }
        $activeVacancies = (int) $db->GetOne("SELECT COALESCE(SUM(opening.vacancies), 0) FROM project_company_hr_job_opening opening INNER JOIN project_company_hr_job_requisition requisition ON requisition.company_key_hash = opening.company_key_hash AND requisition.job_requisition_key = opening.job_requisition_key WHERE opening.company_key_hash = ? AND requisition.staffing_plan_key = ? AND opening.job_position_key = ? AND opening.job_opening_key <> ? AND opening.opening_status IN ('DRAFT','OPEN') FOR UPDATE", [$hash, $requisition['staffing_plan_key'], $positionKey, $key]);
        if (($activeVacancies + $vacancies) > (int) $planDetail['planned_positions']) { throw new InvalidArgumentException('Open vacancies exceed the Staffing Plan.'); }
        $code = yovel_admin_hr_leave_code($input['opening_code'] ?? '', 'Opening code');
        $openingDate = yovel_admin_hr_recruitment_required_date($input['opening_date'] ?? '', 'Opening date');
        $closingDate = yovel_admin_optional_date((string) ($input['closing_date'] ?? ''), 'Closing date');
        if ($closingDate !== '' && $closingDate < $openingDate) { throw new InvalidArgumentException('Closing date cannot precede the opening date.'); }
        $description = yovel_admin_hr_leave_text($input['description'] ?? '', 'Description', 8000, false);
        $status = yovel_admin_status((string) ($input['opening_status'] ?? 'DRAFT'), ['DRAFT', 'OPEN'], 'DRAFT');
        $checksum = yovel_admin_hr_leave_checksum(compact('code', 'requisitionKey', 'templateKey', 'departmentKey', 'positionKey', 'vacancies', 'openingDate', 'closingDate', 'description', 'status'));
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_job_opening (job_opening_key, company_key, company_key_hash, opening_code, job_requisition_key, job_opening_template_key, department_key, job_position_key, vacancies, opening_date, closing_date, description, opening_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE job_requisition_key=VALUES(job_requisition_key), job_opening_template_key=VALUES(job_opening_template_key), department_key=VALUES(department_key), job_position_key=VALUES(job_position_key), vacancies=VALUES(vacancies), opening_date=VALUES(opening_date), closing_date=VALUES(closing_date), description=VALUES(description), opening_status=VALUES(opening_status), immutable_checksum=VALUES(immutable_checksum), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $hash, $code, $requisitionKey, $templateKey ?: null, $departmentKey, $positionKey, $vacancies, $openingDate, $closingDate ?: null, $description ?: null, $status, $checksum, $adminKey, $adminKey], 'Job Opening save');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_job_opening', 'job_opening_key', $hash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Job Opening verification failed.'); }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_job_opening', $key, ['company_key' => $companyKey, 'opening_status' => $status], 'Company administrator saved a Job Opening.');
        return $saved;
    });
}

function yovel_admin_persist_job_applicant(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $providedKey = trim((string) ($input['job_applicant_key'] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_applicant', 'job_applicant_key', $providedKey, 'Job Applicant', true);
            $existing = $db->GetRow('SELECT * FROM project_company_hr_job_applicant WHERE company_key_hash = ? AND job_applicant_key = ? FOR UPDATE', [$hash, $providedKey]);
            if (in_array((string) ($existing['applicant_status'] ?? ''), ['HIRED', 'REJECTED', 'WITHDRAWN'], true)) { throw new RuntimeException('Finalized Job Applicants are immutable.'); }
        }
        $key = $providedKey ?: bx_uuid();
        $openingKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_opening', 'job_opening_key', $input['job_opening_key'] ?? '', 'Job Opening', true);
        $opening = $db->GetRow('SELECT * FROM project_company_hr_job_opening WHERE company_key_hash = ? AND job_opening_key = ? FOR UPDATE', [$hash, $openingKey]);
        if (!is_array($opening) || (string) ($opening['opening_status'] ?? '') !== 'OPEN') { throw new RuntimeException('The Job Opening is not accepting applicants.'); }
        $sourceKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_applicant_source', 'job_applicant_source_key', $input['job_applicant_source_key'] ?? '', 'Applicant Source');
        $name = yovel_admin_hr_leave_text($input['applicant_name'] ?? '', 'Applicant name', 180);
        $email = strtolower(trim((string) ($input['email_address'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) { throw new InvalidArgumentException('A valid applicant email is required.'); }
        $duplicate = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_job_applicant WHERE company_key_hash = ? AND job_opening_key = ? AND LOWER(email_address) = ? AND job_applicant_key <> ? FOR UPDATE', [$hash, $openingKey, $email, $key]);
        if ($duplicate > 0) { throw new InvalidArgumentException('This applicant email already exists for the Job Opening.'); }
        $phone = yovel_admin_hr_leave_text($input['phone_number'] ?? '', 'Phone number', 60, false);
        $cover = yovel_admin_hr_leave_text($input['cover_letter'] ?? '', 'Cover letter', 12000, false);
        $status = yovel_admin_status((string) ($input['applicant_status'] ?? 'OPEN'), ['OPEN', 'SCREENING', 'INTERVIEW'], 'OPEN');
        $checksum = yovel_admin_hr_leave_checksum(compact('openingKey', 'sourceKey', 'name', 'email', 'phone', 'cover', 'status'));
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_job_applicant (job_applicant_key, company_key, company_key_hash, job_opening_key, job_applicant_source_key, applicant_name, email_address, phone_number, cover_letter, applicant_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE job_opening_key=VALUES(job_opening_key), job_applicant_source_key=VALUES(job_applicant_source_key), applicant_name=VALUES(applicant_name), email_address=VALUES(email_address), phone_number=VALUES(phone_number), cover_letter=VALUES(cover_letter), applicant_status=VALUES(applicant_status), immutable_checksum=VALUES(immutable_checksum), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $hash, $openingKey, $sourceKey ?: null, $name, $email, $phone ?: null, $cover ?: null, $status, $checksum, $adminKey, $adminKey], 'Job Applicant save');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_job_applicant', 'job_applicant_key', $hash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum']) || strtolower((string) $saved['email_address']) !== $email) { throw new RuntimeException('Job Applicant verification failed.'); }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_job_applicant', $key, ['company_key' => $companyKey, 'applicant_status' => $status], 'Company administrator saved a Job Applicant.');
        return $saved;
    });
}

function yovel_admin_persist_employee_referral(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $employeeKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee', 'employee_key', $input['employee_key'] ?? '', 'Employee', true);
        yovel_admin_hr_lock_employee($db, $hash, $employeeKey);
        $applicantKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_applicant', 'job_applicant_key', $input['job_applicant_key'] ?? '', 'Job Applicant', true);
        $db->GetRow('SELECT job_applicant_key FROM project_company_hr_job_applicant WHERE company_key_hash = ? AND job_applicant_key = ? FOR UPDATE', [$hash, $applicantKey]);
        $notes = yovel_admin_hr_leave_text($input['referral_notes'] ?? '', 'Referral notes', 8000, false);
        $key = bx_uuid();
        $checksum = yovel_admin_hr_leave_checksum(compact('employeeKey', 'applicantKey', 'notes'));
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_employee_referral (employee_referral_key, company_key, company_key_hash, employee_key, job_applicant_key, referral_notes, referral_status, immutable_checksum, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)", [$key, $companyKey, $hash, $employeeKey, $applicantKey, $notes ?: null, $checksum, $adminKey], 'Employee Referral save');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_employee_referral', 'employee_referral_key', $hash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Employee Referral verification failed.'); }
        bx_audit('CREATE', 'project_company_hr_employee_referral', $key, ['company_key' => $companyKey, 'job_applicant_key' => $applicantKey], 'Company administrator saved an Employee Referral.');
        return $saved;
    });
}

function yovel_admin_persist_interview(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $hash, $adminKey] = $scope;
    $details = is_array($input['details'] ?? null) ? $input['details'] : [];
    if ($details === []) { throw new InvalidArgumentException('An Interview requires at least one interviewer.'); }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $hash, $adminKey, $input, $details, $failureInjector): array {
        $providedKey = trim((string) ($input['interview_key'] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_interview', 'interview_key', $providedKey, 'Interview', true);
            $existing = $db->GetRow('SELECT * FROM project_company_hr_interview WHERE company_key_hash = ? AND interview_key = ? FOR UPDATE', [$hash, $providedKey]);
            if (!in_array((string) ($existing['interview_status'] ?? ''), ['DRAFT', 'SCHEDULED'], true)) { throw new RuntimeException('Completed Interviews are immutable.'); }
            if ((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_interview_feedback WHERE company_key_hash = ? AND interview_key = ? FOR UPDATE', [$hash, $providedKey]) > 0) { throw new RuntimeException('An Interview with feedback cannot be edited.'); }
        }
        $key = $providedKey ?: bx_uuid();
        $applicantKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_applicant', 'job_applicant_key', $input['job_applicant_key'] ?? '', 'Job Applicant', true);
        $applicant = $db->GetRow('SELECT * FROM project_company_hr_job_applicant WHERE company_key_hash = ? AND job_applicant_key = ? FOR UPDATE', [$hash, $applicantKey]);
        if (!is_array($applicant) || in_array((string) ($applicant['applicant_status'] ?? ''), ['HIRED', 'REJECTED', 'WITHDRAWN'], true)) { throw new RuntimeException('The Job Applicant is not available for an Interview.'); }
        $typeKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_interview_type', 'interview_type_key', $input['interview_type_key'] ?? '', 'Interview Type', true);
        [$scheduledAt, $timezone] = yovel_admin_hr_recruitment_datetime((string) ($input['scheduled_at'] ?? ''), (string) ($input['timezone_name'] ?? 'Asia/Manila'));
        $status = yovel_admin_status((string) ($input['interview_status'] ?? 'DRAFT'), ['DRAFT', 'SCHEDULED'], 'DRAFT');
        $checksum = yovel_admin_hr_leave_checksum(compact('applicantKey', 'typeKey', 'scheduledAt', 'timezone', 'status'));
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_interview (interview_key, company_key, company_key_hash, job_applicant_key, interview_type_key, scheduled_at, timezone_name, interview_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE job_applicant_key=VALUES(job_applicant_key), interview_type_key=VALUES(interview_type_key), scheduled_at=VALUES(scheduled_at), timezone_name=VALUES(timezone_name), interview_status=VALUES(interview_status), immutable_checksum=VALUES(immutable_checksum), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $hash, $applicantKey, $typeKey, $scheduledAt, $timezone, $status, $checksum, $adminKey, $adminKey], 'Interview save');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_interviewer WHERE company_key_hash = ? AND interview_key = ?', [$hash, $key], 'Interviewers replace');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_interview_detail WHERE company_key_hash = ? AND interview_key = ?', [$hash, $key], 'Interview details replace');
        $seen = [];
        foreach ($details as $index => $detail) {
            if (!is_array($detail)) { continue; }
            $employeeKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee', 'employee_key', $detail['employee_key'] ?? '', 'Interviewer', true);
            yovel_admin_hr_lock_employee($db, $hash, $employeeKey);
            if (isset($seen[$employeeKey])) { throw new InvalidArgumentException('An employee may interview once in the same Interview.'); }
            $seen[$employeeKey] = true;
            $role = yovel_admin_hr_leave_text($detail['role_label'] ?? 'Interviewer', 'Interviewer role', 120);
            yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_interview_detail (interview_detail_key, company_key, company_key_hash, interview_key, employee_key, role_label, sort_order, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $hash, $key, $employeeKey, $role, ($index + 1) * 10, $adminKey], 'Interview Detail save');
            yovel_admin_db_execute($db, "INSERT INTO project_company_hr_interviewer (interviewer_key, company_key, company_key_hash, interview_key, employee_key, interviewer_status, created_by_admin_key) VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?)", [bx_uuid(), $companyKey, $hash, $key, $employeeKey, $adminKey], 'Interviewer save');
        }
        if ($seen === []) { throw new InvalidArgumentException('An Interview requires at least one valid interviewer.'); }
        yovel_admin_db_execute($db, "UPDATE project_company_hr_job_applicant SET applicant_status = 'INTERVIEW', updated_by_admin_key = ? WHERE company_key_hash = ? AND job_applicant_key = ? AND applicant_status IN ('OPEN','SCREENING','INTERVIEW')", [$adminKey, $hash, $applicantKey], 'Job Applicant interview status');
        if ($status === 'SCHEDULED') { yovel_admin_hr_leave_notification_intent($db, $scope, $key, 'INTERVIEW_SCHEDULED', ['job_applicant_key' => $applicantKey, 'scheduled_at' => $scheduledAt, 'timezone_name' => $timezone]); }
        if ($failureInjector) { $failureInjector(); }
        if ((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_interview_detail WHERE company_key_hash = ? AND interview_key = ?', [$hash, $key]) !== count($seen)) { throw new RuntimeException('Interview panel verification failed.'); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_interview', 'interview_key', $hash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Interview verification failed.'); }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_interview', $key, ['company_key' => $companyKey, 'interview_status' => $status, 'interviewer_count' => count($seen)], 'Company administrator saved an Interview.');
        return $saved;
    });
}

function yovel_admin_submit_interview_feedback(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $input, $failureInjector): array {
        $interviewKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_interview', 'interview_key', $input['interview_key'] ?? '', 'Interview', true);
        $interview = $db->GetRow('SELECT * FROM project_company_hr_interview WHERE company_key_hash = ? AND interview_key = ? FOR UPDATE', [$hash, $interviewKey]);
        if (!is_array($interview) || (string) ($interview['interview_status'] ?? '') !== 'SCHEDULED') { throw new RuntimeException('Feedback requires a scheduled Interview.'); }
        $employeeKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_employee', 'employee_key', $input['interviewer_employee_key'] ?? '', 'Interviewer', true);
        yovel_admin_hr_lock_employee($db, $hash, $employeeKey);
        if ((int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_interviewer WHERE company_key_hash = ? AND interview_key = ? AND employee_key = ? AND interviewer_status = 'ACTIVE' FOR UPDATE", [$hash, $interviewKey, $employeeKey]) !== 1) { throw new RuntimeException('Only an assigned interviewer may submit feedback.'); }
        $rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($rating === false || $rating < 0 || $rating > 5) { throw new InvalidArgumentException('Interview rating must be from 0 to 5.'); }
        $feedback = yovel_admin_hr_leave_text($input['feedback'] ?? '', 'Feedback', 12000);
        $recommendation = yovel_admin_status((string) ($input['recommendation'] ?? ''), ['HIRE', 'HOLD', 'REJECT'], '');
        $key = bx_uuid();
        $checksum = yovel_admin_hr_leave_checksum(compact('interviewKey', 'employeeKey', 'rating', 'feedback', 'recommendation'));
        yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_interview_feedback (interview_feedback_key, company_key, company_key_hash, interview_key, interviewer_employee_key, rating, feedback, recommendation, immutable_checksum, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$key, $companyKey, $hash, $interviewKey, $employeeKey, number_format((float) $rating, 2, '.', ''), $feedback, $recommendation, $checksum, $adminKey], 'Interview Feedback save');
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_interview_feedback', 'interview_feedback_key', $hash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Interview Feedback verification failed.'); }
        bx_audit('SUBMIT', 'project_company_hr_interview_feedback', $key, ['company_key' => $companyKey, 'interview_key' => $interviewKey, 'recommendation' => $recommendation], 'An interviewer submitted immutable feedback.');
        return $saved;
    });
}

function yovel_admin_transition_interview(ADOConnection $db, array $company, array $admin, string $interviewKey, string $status, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $hash, $adminKey] = $scope;
    $status = yovel_admin_status($status, ['COMPLETED', 'CANCELLED'], '');
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $hash, $adminKey, $interviewKey, $status, $failureInjector): array {
        if (!yovel_admin_is_uuid($interviewKey)) { throw new InvalidArgumentException('Interview is invalid.'); }
        $row = $db->GetRow('SELECT * FROM project_company_hr_interview WHERE company_key_hash = ? AND interview_key = ? FOR UPDATE', [$hash, $interviewKey]);
        if (!is_array($row) || $row === []) { throw new InvalidArgumentException('Interview was not found.'); }
        if ((string) $row['interview_status'] !== 'SCHEDULED') { throw new RuntimeException('Only scheduled Interviews may be completed or cancelled.'); }
        if ($status === 'COMPLETED' && (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_interview_feedback WHERE company_key_hash = ? AND interview_key = ? FOR UPDATE', [$hash, $interviewKey]) < 1) { throw new RuntimeException('At least one feedback record is required to complete an Interview.'); }
        yovel_admin_db_execute($db, 'UPDATE project_company_hr_interview SET interview_status = ?, completed_at = CASE WHEN ? = \'COMPLETED\' THEN NOW() ELSE NULL END, updated_by_admin_key = ? WHERE company_key_hash = ? AND interview_key = ?', [$status, $status, $adminKey, $hash, $interviewKey], 'Interview transition');
        yovel_admin_hr_leave_notification_intent($db, $scope, $interviewKey, 'INTERVIEW_' . $status, ['interview_status' => $status, 'job_applicant_key' => $row['job_applicant_key']]);
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_interview', 'interview_key', $hash, $interviewKey);
        if ((string) $saved['interview_status'] !== $status) { throw new RuntimeException('Interview transition verification failed.'); }
        bx_audit($status, 'project_company_hr_interview', $interviewKey, ['company_key' => $companyKey, 'interview_status' => $status], 'Company administrator changed an Interview decision.');
        return $saved;
    });
}

function yovel_admin_persist_job_offer(ADOConnection $db, array $company, array $admin, array $input, ?callable $failureInjector = null): array
{
    [$companyKey, $hash, $adminKey] = yovel_admin_hr_scope($company, $admin);
    $terms = is_array($input['terms'] ?? null) ? $input['terms'] : [];
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $input, $terms, $failureInjector): array {
        $providedKey = trim((string) ($input['job_offer_key'] ?? ''));
        $existing = [];
        if ($providedKey !== '') {
            $providedKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_offer', 'job_offer_key', $providedKey, 'Job Offer', true);
            $existing = $db->GetRow('SELECT * FROM project_company_hr_job_offer WHERE company_key_hash = ? AND job_offer_key = ? FOR UPDATE', [$hash, $providedKey]);
            if ((string) ($existing['offer_status'] ?? '') !== 'DRAFT') { throw new RuntimeException('Submitted Job Offers are immutable outside explicit decisions.'); }
        }
        if ($terms === []) { throw new InvalidArgumentException('A Job Offer requires at least one term.'); }
        $key = $providedKey ?: bx_uuid();
        $applicantKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_job_applicant', 'job_applicant_key', $input['job_applicant_key'] ?? '', 'Job Applicant', true);
        $applicant = $db->GetRow('SELECT * FROM project_company_hr_job_applicant WHERE company_key_hash = ? AND job_applicant_key = ? FOR UPDATE', [$hash, $applicantKey]);
        if (!is_array($applicant) || in_array((string) ($applicant['applicant_status'] ?? ''), ['HIRED', 'REJECTED', 'WITHDRAWN'], true)) { throw new RuntimeException('The Job Applicant is not eligible for an offer.'); }
        $templateKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_appointment_letter_template', 'appointment_letter_template_key', $input['appointment_letter_template_key'] ?? '', 'Appointment Letter Template');
        $code = yovel_admin_hr_leave_code($input['offer_code'] ?? '', 'Offer code');
        $offerDate = yovel_admin_hr_recruitment_required_date($input['offer_date'] ?? '', 'Offer date');
        $validUntil = yovel_admin_optional_date((string) ($input['valid_until'] ?? ''), 'Valid until');
        if ($validUntil !== '' && $validUntil < $offerDate) { throw new InvalidArgumentException('Offer validity cannot end before the offer date.'); }
        $designation = yovel_admin_hr_leave_text($input['designation'] ?? '', 'Designation', 180);
        $status = yovel_admin_status((string) ($input['offer_status'] ?? 'DRAFT'), ['DRAFT', 'SUBMITTED'], 'DRAFT');
        $checksum = yovel_admin_hr_leave_checksum(compact('code', 'applicantKey', 'templateKey', 'offerDate', 'validUntil', 'designation', 'status'));
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_job_offer (job_offer_key, company_key, company_key_hash, offer_code, job_applicant_key, appointment_letter_template_key, offer_date, valid_until, designation, offer_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE job_applicant_key=VALUES(job_applicant_key), appointment_letter_template_key=VALUES(appointment_letter_template_key), offer_date=VALUES(offer_date), valid_until=VALUES(valid_until), designation=VALUES(designation), offer_status=VALUES(offer_status), immutable_checksum=VALUES(immutable_checksum), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $hash, $code, $applicantKey, $templateKey ?: null, $offerDate, $validUntil ?: null, $designation, $status, $checksum, $adminKey, $adminKey], 'Job Offer save');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_hr_job_offer_term WHERE company_key_hash = ? AND job_offer_key = ?', [$hash, $key], 'Job Offer terms replace');
        $seen = [];
        foreach ($terms as $index => $term) {
            if (!is_array($term)) { continue; }
            $termKey = yovel_admin_hr_setup_key($db, $hash, 'project_company_hr_offer_term', 'offer_term_key', $term['offer_term_key'] ?? '', 'Offer Term', true);
            if (isset($seen[$termKey])) { throw new InvalidArgumentException('An Offer Term may appear once.'); }
            $seen[$termKey] = true;
            $label = yovel_admin_hr_leave_text($term['term_label'] ?? '', 'Term label', 180);
            $value = yovel_admin_hr_leave_text($term['term_value'] ?? '', 'Term value', 12000);
            $sort = max(0, min(10000, (int) ($term['sort_order'] ?? (($index + 1) * 10))));
            $termChecksum = yovel_admin_hr_leave_checksum(compact('key', 'termKey', 'label', 'value', 'sort'));
            yovel_admin_db_execute($db, 'INSERT INTO project_company_hr_job_offer_term (job_offer_term_key, company_key, company_key_hash, job_offer_key, offer_term_key, term_label, term_value, sort_order, immutable_checksum, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $hash, $key, $termKey, $label, $value, $sort, $termChecksum, $adminKey], 'Job Offer Term save');
        }
        if ($seen === []) { throw new InvalidArgumentException('A Job Offer requires at least one valid term.'); }
        if ($status === 'SUBMITTED') { yovel_admin_db_execute($db, "UPDATE project_company_hr_job_applicant SET applicant_status = 'OFFERED', updated_by_admin_key = ? WHERE company_key_hash = ? AND job_applicant_key = ? AND applicant_status NOT IN ('HIRED','REJECTED','WITHDRAWN')", [$adminKey, $hash, $applicantKey], 'Job Applicant offer status'); }
        if ($failureInjector) { $failureInjector(); }
        if ((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_job_offer_term WHERE company_key_hash = ? AND job_offer_key = ?', [$hash, $key]) !== count($seen)) { throw new RuntimeException('Job Offer terms verification failed.'); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_job_offer', 'job_offer_key', $hash, $key);
        if (!hash_equals($checksum, (string) $saved['immutable_checksum'])) { throw new RuntimeException('Job Offer verification failed.'); }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_job_offer', $key, ['company_key' => $companyKey, 'offer_status' => $status, 'term_count' => count($seen)], 'Company administrator saved a Job Offer.');
        return $saved;
    });
}

function yovel_admin_transition_job_offer(ADOConnection $db, array $company, array $admin, string $offerKey, string $status, ?callable $failureInjector = null): array
{
    $scope = yovel_admin_hr_scope($company, $admin);
    [$companyKey, $hash, $adminKey] = $scope;
    $status = yovel_admin_status($status, ['ACCEPTED', 'REJECTED', 'WITHDRAWN', 'AMENDED'], '');
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $scope, $companyKey, $hash, $adminKey, $offerKey, $status, $failureInjector): array {
        if (!yovel_admin_is_uuid($offerKey)) { throw new InvalidArgumentException('Job Offer is invalid.'); }
        $row = $db->GetRow('SELECT * FROM project_company_hr_job_offer WHERE company_key_hash = ? AND job_offer_key = ? FOR UPDATE', [$hash, $offerKey]);
        if (!is_array($row) || $row === []) { throw new InvalidArgumentException('Job Offer was not found.'); }
        $current = (string) $row['offer_status'];
        $allowed = $current === 'SUBMITTED' ? ['ACCEPTED', 'REJECTED', 'WITHDRAWN'] : ($current === 'ACCEPTED' ? ['WITHDRAWN', 'AMENDED'] : []);
        if (!in_array($status, $allowed, true)) { throw new RuntimeException('This Job Offer decision is not allowed from its current status.'); }
        yovel_admin_db_execute($db, 'UPDATE project_company_hr_job_offer SET offer_status = ?, decision_by_admin_key = ?, decision_at = NOW(), updated_by_admin_key = ? WHERE company_key_hash = ? AND job_offer_key = ?', [$status, $adminKey, $adminKey, $hash, $offerKey], 'Job Offer transition');
        $applicantStatus = match ($status) { 'ACCEPTED' => 'HIRED', 'REJECTED' => 'REJECTED', 'WITHDRAWN' => 'WITHDRAWN', 'AMENDED' => 'OFFERED' };
        yovel_admin_db_execute($db, 'UPDATE project_company_hr_job_applicant SET applicant_status = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND job_applicant_key = ?', [$applicantStatus, $adminKey, $hash, $row['job_applicant_key']], 'Job Applicant decision status');
        yovel_admin_hr_leave_notification_intent($db, $scope, $offerKey, 'JOB_OFFER_' . $status, ['offer_status' => $status, 'job_applicant_key' => $row['job_applicant_key']]);
        if ($failureInjector) { $failureInjector(); }
        $saved = yovel_admin_hr_recruitment_readback($db, 'project_company_hr_job_offer', 'job_offer_key', $hash, $offerKey);
        if ((string) $saved['offer_status'] !== $status) { throw new RuntimeException('Job Offer transition verification failed.'); }
        if ((string) $db->GetOne('SELECT applicant_status FROM project_company_hr_job_applicant WHERE company_key_hash = ? AND job_applicant_key = ?', [$hash, $row['job_applicant_key']]) !== $applicantStatus) { throw new RuntimeException('Applicant decision status verification failed.'); }
        bx_audit($status, 'project_company_hr_job_offer', $offerKey, ['company_key' => $companyKey, 'offer_status' => $status], 'Company administrator recorded an immutable Job Offer decision.');
        return $saved;
    });
}

function yovel_admin_hr_recruitment_print_payload(array $company, string $recordKey, string $kind): array
{
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[0-9a-f]{64}$/', $hash) !== 1 || !yovel_admin_is_uuid($recordKey)) { throw new InvalidArgumentException('Recruitment print scope is invalid.'); }
    $kind = strtoupper(trim($kind));
    if (!in_array($kind, ['JOB_OFFER', 'APPOINTMENT_LETTER'], true)) { throw new InvalidArgumentException('Recruitment print document type is invalid.'); }
    $db = bx_db();
    $offer = $db->GetRow("SELECT offer.*, applicant.applicant_name, applicant.email_address, opening.opening_code, position.job_position_name FROM project_company_hr_job_offer offer INNER JOIN project_company_hr_job_applicant applicant ON applicant.company_key_hash = offer.company_key_hash AND applicant.job_applicant_key = offer.job_applicant_key INNER JOIN project_company_hr_job_opening opening ON opening.company_key_hash = applicant.company_key_hash AND opening.job_opening_key = applicant.job_opening_key INNER JOIN project_company_hr_job_position position ON position.company_key_hash = opening.company_key_hash AND position.job_position_key = opening.job_position_key WHERE offer.company_key_hash = ? AND offer.job_offer_key = ? LIMIT 1", [$hash, $recordKey]);
    if (!is_array($offer) || $offer === []) { throw new InvalidArgumentException('Job Offer was not found.'); }
    $terms = $db->GetAll('SELECT term_label, term_value, sort_order FROM project_company_hr_job_offer_term WHERE company_key_hash = ? AND job_offer_key = ? ORDER BY sort_order, x_id', [$hash, $recordKey]) ?: [];
    $content = [];
    if ((string) ($offer['appointment_letter_template_key'] ?? '') !== '') {
        $content = $db->GetAll('SELECT content_heading, content_body, sort_order FROM project_company_hr_appointment_letter_content WHERE company_key_hash = ? AND appointment_letter_template_key = ? ORDER BY sort_order, x_id', [$hash, $offer['appointment_letter_template_key']]) ?: [];
    }
    return [
        'contract' => 'hr.recruitment-print.v1',
        'company_key_hash' => $hash,
        'document' => [
            'type' => $kind, 'record_key' => $recordKey, 'offer_code' => $offer['offer_code'], 'offer_status' => $offer['offer_status'],
            'applicant_name' => $offer['applicant_name'], 'email_address' => $offer['email_address'], 'designation' => $offer['designation'],
            'offer_date' => $offer['offer_date'], 'valid_until' => $offer['valid_until'], 'opening_code' => $offer['opening_code'],
            'job_position_name' => $offer['job_position_name'], 'terms' => $terms, 'sections' => $content,
            'content' => implode("\n\n", array_map(static fn (array $section): string => trim((string) $section['content_heading']) . "\n" . trim((string) $section['content_body']), $content)),
        ],
        'renderer' => ['contract' => 'orchestration.print-renderer.v1', 'available' => false, 'reason' => 'Shared print rendering is not currently registered.'],
    ];
}

function yovel_admin_convert_accepted_offer_to_onboarding(ADOConnection $db, array $company, array $admin, string $offerKey): array
{
    [$companyKey, $hash] = yovel_admin_hr_scope($company, $admin);
    if (!yovel_admin_is_uuid($offerKey)) { throw new InvalidArgumentException('Job Offer is invalid.'); }
    return yovel_admin_hr_in_transaction($db, static function () use ($db, $company, $admin, $companyKey, $hash, $offerKey): array {
        $offer = $db->GetRow('SELECT * FROM project_company_hr_job_offer WHERE company_key_hash = ? AND job_offer_key = ? FOR UPDATE', [$hash, $offerKey]);
        if (!is_array($offer) || $offer === [] || (string) $offer['offer_status'] !== 'ACCEPTED') { throw new RuntimeException('Only an accepted Job Offer can enter onboarding.'); }
        if (!function_exists('yovel_admin_create_employee_onboarding_from_offer')) { throw new RuntimeException('The onboarding owner contract is not available yet.'); }
        $result = yovel_admin_create_employee_onboarding_from_offer($db, $company, $admin, ['job_offer_key' => $offerKey, 'company_key' => $companyKey]);
        if (!is_array($result) || empty($result['employee_onboarding_key'])) { throw new RuntimeException('The onboarding owner did not return a verified record.'); }
        return $result;
    });
}

function yovel_admin_hr_recruitment_data(array $company): array
{
    yovel_admin_hr_schema();
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) { throw new InvalidArgumentException('Recruitment company scope is invalid.'); }
    $db = bx_db();
    return [
        'staffingPlans' => $db->GetAll('SELECT * FROM project_company_hr_staffing_plan WHERE company_key_hash = ? ORDER BY from_date DESC, plan_name', [$hash]) ?: [],
        'requisitions' => $db->GetAll("SELECT requisition.*, department.department_name, position.job_position_name FROM project_company_hr_job_requisition requisition INNER JOIN project_company_department department ON department.company_key_hash = requisition.company_key_hash AND department.department_key = requisition.department_key INNER JOIN project_company_hr_job_position position ON position.company_key_hash = requisition.company_key_hash AND position.job_position_key = requisition.job_position_key WHERE requisition.company_key_hash = ? ORDER BY requisition.created_at DESC LIMIT 300", [$hash]) ?: [],
        'openings' => $db->GetAll("SELECT opening.*, position.job_position_name, department.department_name, COUNT(applicant.job_applicant_key) applicant_count FROM project_company_hr_job_opening opening INNER JOIN project_company_hr_job_position position ON position.company_key_hash = opening.company_key_hash AND position.job_position_key = opening.job_position_key INNER JOIN project_company_department department ON department.company_key_hash = opening.company_key_hash AND department.department_key = opening.department_key LEFT JOIN project_company_hr_job_applicant applicant ON applicant.company_key_hash = opening.company_key_hash AND applicant.job_opening_key = opening.job_opening_key WHERE opening.company_key_hash = ? GROUP BY opening.job_opening_key ORDER BY opening.opening_date DESC LIMIT 300", [$hash]) ?: [],
        'applicants' => $db->GetAll("SELECT applicant.*, opening.opening_code, position.job_position_name, source.source_name FROM project_company_hr_job_applicant applicant INNER JOIN project_company_hr_job_opening opening ON opening.company_key_hash = applicant.company_key_hash AND opening.job_opening_key = applicant.job_opening_key INNER JOIN project_company_hr_job_position position ON position.company_key_hash = opening.company_key_hash AND position.job_position_key = opening.job_position_key LEFT JOIN project_company_hr_job_applicant_source source ON source.company_key_hash = applicant.company_key_hash AND source.job_applicant_source_key = applicant.job_applicant_source_key WHERE applicant.company_key_hash = ? ORDER BY applicant.created_at DESC LIMIT 300", [$hash]) ?: [],
        'interviews' => $db->GetAll("SELECT interview.*, applicant.applicant_name, type.interview_type_name FROM project_company_hr_interview interview INNER JOIN project_company_hr_job_applicant applicant ON applicant.company_key_hash = interview.company_key_hash AND applicant.job_applicant_key = interview.job_applicant_key INNER JOIN project_company_hr_interview_type type ON type.company_key_hash = interview.company_key_hash AND type.interview_type_key = interview.interview_type_key WHERE interview.company_key_hash = ? ORDER BY interview.scheduled_at DESC LIMIT 300", [$hash]) ?: [],
        'offers' => $db->GetAll("SELECT offer.*, applicant.applicant_name, applicant.email_address FROM project_company_hr_job_offer offer INNER JOIN project_company_hr_job_applicant applicant ON applicant.company_key_hash = offer.company_key_hash AND applicant.job_applicant_key = offer.job_applicant_key WHERE offer.company_key_hash = ? ORDER BY offer.offer_date DESC, offer.created_at DESC LIMIT 300", [$hash]) ?: [],
        'applicantSources' => $db->GetAll("SELECT * FROM project_company_hr_job_applicant_source WHERE company_key_hash = ? AND source_status = 'ACTIVE' ORDER BY source_name", [$hash]) ?: [],
        'interviewTypes' => $db->GetAll("SELECT * FROM project_company_hr_interview_type WHERE company_key_hash = ? AND interview_type_status = 'ACTIVE' ORDER BY interview_type_name", [$hash]) ?: [],
        'offerTerms' => $db->GetAll("SELECT * FROM project_company_hr_offer_term WHERE company_key_hash = ? AND offer_term_status = 'ACTIVE' ORDER BY offer_term_name", [$hash]) ?: [],
        'openingTemplates' => $db->GetAll("SELECT * FROM project_company_hr_job_opening_template WHERE company_key_hash = ? AND template_status = 'ACTIVE' ORDER BY template_name", [$hash]) ?: [],
        'letterTemplates' => $db->GetAll("SELECT * FROM project_company_hr_appointment_letter_template WHERE company_key_hash = ? AND template_status = 'ACTIVE' ORDER BY template_name", [$hash]) ?: [],
        'formTargets' => yovel_admin_hr_recruitment_form_targets(),
        'dependencies' => [
            'attachments' => ['available' => false, 'contract' => 'orchestration.attachments.v1'],
            'print' => ['available' => false, 'contract' => 'orchestration.print-renderer.v1'],
            'onboarding' => ['available' => function_exists('yovel_admin_create_employee_onboarding_from_offer'), 'contract' => 'hr.onboarding-owner.v1'],
        ],
    ];
}

function yovel_admin_hr_recruitment_json_rows(array $input, string $name): array
{
    $value = $input[$name] ?? [];
    if (is_array($value)) { return $value; }
    $decoded = json_decode(trim((string) $value), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) { throw new InvalidArgumentException(ucwords(str_replace('_', ' ', $name)) . ' must be a list.'); }
    return $decoded;
}

function yovel_admin_hr_handle_recruitment_post(array $company, array $admin, string $action, array $input): array
{
    $db = bx_db();
    $saved = match ($action) {
        'hr_recruitment_save_master' => yovel_admin_persist_recruitment_master($db, $company, $admin, (string) ($input['record_type'] ?? ''), array_merge($input, ['details' => yovel_admin_hr_recruitment_json_rows($input, 'details_json'), 'content' => yovel_admin_hr_recruitment_json_rows($input, 'content_json')])),
        'hr_recruitment_save_staffing_plan' => yovel_admin_persist_staffing_plan($db, $company, $admin, array_merge($input, ['details' => yovel_admin_hr_recruitment_json_rows($input, 'details_json')])),
        'hr_recruitment_save_requisition' => yovel_admin_persist_job_requisition($db, $company, $admin, $input),
        'hr_recruitment_transition_requisition' => yovel_admin_transition_job_requisition($db, $company, $admin, (string) ($input['job_requisition_key'] ?? ''), (string) ($input['requisition_status'] ?? '')),
        'hr_recruitment_save_opening' => yovel_admin_persist_job_opening($db, $company, $admin, $input),
        'hr_recruitment_save_applicant' => yovel_admin_persist_job_applicant($db, $company, $admin, $input),
        'hr_recruitment_save_referral' => yovel_admin_persist_employee_referral($db, $company, $admin, $input),
        'hr_recruitment_save_interview' => yovel_admin_persist_interview($db, $company, $admin, array_merge($input, ['details' => yovel_admin_hr_recruitment_json_rows($input, 'details_json')])),
        'hr_recruitment_save_feedback' => yovel_admin_submit_interview_feedback($db, $company, $admin, $input),
        'hr_recruitment_transition_interview' => yovel_admin_transition_interview($db, $company, $admin, (string) ($input['interview_key'] ?? ''), (string) ($input['interview_status'] ?? '')),
        'hr_recruitment_save_offer' => yovel_admin_persist_job_offer($db, $company, $admin, array_merge($input, ['terms' => yovel_admin_hr_recruitment_json_rows($input, 'terms_json')])),
        'hr_recruitment_transition_offer' => yovel_admin_transition_job_offer($db, $company, $admin, (string) ($input['job_offer_key'] ?? ''), (string) ($input['offer_status'] ?? '')),
        default => throw new InvalidArgumentException('This Recruitment action is not available.'),
    };
    $recordKey = '';
    foreach (['job_offer_key', 'interview_feedback_key', 'interview_key', 'employee_referral_key', 'job_applicant_key', 'job_opening_key', 'job_requisition_key', 'staffing_plan_key', 'appointment_letter_template_key', 'job_offer_term_template_key', 'job_opening_template_key', 'offer_term_key', 'interview_type_key', 'job_applicant_source_key'] as $key) {
        if (!empty($saved[$key])) { $recordKey = (string) $saved[$key]; break; }
    }
    return ['message' => 'Recruitment record saved.', 'section' => 'recruitment', 'query' => ['record' => $recordKey, 'recruitment_mode' => (string) ($input['recruitment_mode'] ?? 'pipeline')]];
}
