<?php
declare(strict_types=1);

function yovel_admin_hr_data(array $company): array
{
    yovel_admin_hr_schema();
    yovel_admin_seed_hr_form_fields($company);

    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $branches = $db->GetAll("
        SELECT branch_key, branch_code, branch_name, branch_status
        FROM project_company_branch
        WHERE company_key_hash = ? AND branch_status <> 'DELETED'
        ORDER BY branch_name ASC
    ", [$companyKeyHash]);
    $departmentMasters = $db->GetAll("
        SELECT department_master_key, department_code, department_name, department_type, department_description, department_status, is_fixed
        FROM project_company_department_master
        WHERE department_status <> 'DELETED'
        ORDER BY is_fixed DESC, department_name ASC
    ");
    $departments = $db->GetAll("
        SELECT
            d.department_key,
            d.company_key,
            d.company_key_hash,
            d.branch_key,
            d.department_code,
            d.department_name,
            d.department_status,
            d.department_type,
            d.department_source,
            d.default_department_key,
            d.department_description,
            d.is_default,
            d.created_at,
            d.updated_at,
            COALESCE(b.branch_code, '') AS branch_code,
            COALESCE(b.branch_name, '') AS branch_name,
            COALESCE(b.branch_status, '') AS branch_status
        FROM project_company_department d
        LEFT JOIN project_company_branch b ON b.branch_key = d.branch_key AND b.company_key_hash = d.company_key_hash
        WHERE d.company_key_hash = ? AND d.department_status <> 'DELETED'
        ORDER BY b.branch_name ASC, d.department_name ASC
    ", [$companyKeyHash]);
    $jobPositions = $db->GetAll("
        SELECT
            j.job_position_key,
            j.company_key,
            j.company_key_hash,
            j.job_position_code,
            j.job_position_name,
            j.job_position_description,
            j.job_position_status,
            j.created_at,
            j.updated_at,
            COALESCE(employee_counts.assigned_employee_count, 0) AS assigned_employee_count
        FROM project_company_hr_job_position j
        LEFT JOIN (
            SELECT company_key_hash, job_position_key, COUNT(*) AS assigned_employee_count
            FROM project_company_hr_employee
            WHERE company_key_hash = ? AND employee_status <> 'DELETED' AND job_position_key IS NOT NULL
            GROUP BY company_key_hash, job_position_key
        ) employee_counts ON employee_counts.job_position_key = j.job_position_key AND employee_counts.company_key_hash = j.company_key_hash
        WHERE j.company_key_hash = ? AND j.job_position_status <> 'DELETED'
        ORDER BY j.job_position_name ASC
    ", [$companyKeyHash, $companyKeyHash]);
    $teams = $db->GetAll("
        SELECT
            t.team_key,
            t.company_key,
            t.company_key_hash,
            t.team_code,
            t.team_name,
            t.team_description,
            t.team_status,
            t.created_at,
            t.updated_at,
            COALESCE(employee_counts.assigned_employee_count, 0) AS assigned_employee_count
        FROM project_company_hr_team t
        LEFT JOIN (
            SELECT effective_assignment.company_key_hash, effective_assignment.team_key, COUNT(*) AS assigned_employee_count
            FROM (
                SELECT employee.company_key_hash, COALESCE(assignment.team_key, employee.team_key) AS team_key
                FROM project_company_hr_employee employee
                LEFT JOIN project_company_hr_employee_assignment assignment
                    ON assignment.employee_key = employee.employee_key
                   AND assignment.company_key_hash = employee.company_key_hash
                   AND assignment.assignment_status = 'ACTIVE'
                   AND assignment.is_primary = 1
                WHERE employee.company_key_hash = ? AND employee.employee_status <> 'DELETED'
            ) effective_assignment
            WHERE effective_assignment.team_key IS NOT NULL
            GROUP BY effective_assignment.company_key_hash, effective_assignment.team_key
        ) employee_counts ON employee_counts.team_key = t.team_key AND employee_counts.company_key_hash = t.company_key_hash
        WHERE t.company_key_hash = ? AND t.team_status <> 'DELETED'
        ORDER BY t.team_name ASC
    ", [$companyKeyHash, $companyKeyHash]);
    $employees = $db->GetAll("
        SELECT
            e.*,
            COALESCE(a.assignment_key, '') AS primary_assignment_key,
            COALESCE(a.project_key, '') AS project_key,
            COALESCE(a.effective_from, '') AS assignment_effective_from,
            COALESCE(a.effective_until, '') AS assignment_effective_until,
            COALESCE(a.branch_key, e.branch_key, '') AS assignment_branch_key,
            COALESCE(a.department_key, e.department_key, '') AS assignment_department_key,
            COALESCE(a.job_position_key, e.job_position_key, '') AS assignment_job_position_key,
            COALESCE(a.team_key, e.team_key, '') AS assignment_team_key,
            COALESCE(a.reports_to_employee_key, e.reports_to_employee_key, '') AS assignment_reports_to_employee_key,
            COALESCE(b.branch_name, '') AS branch_name,
            COALESCE(d.department_name, '') AS department_name,
            COALESCE(j.job_position_name, '') AS job_position_name,
            COALESCE(t.team_name, '') AS team_name,
            COALESCE(m.employee_name, '') AS reports_to_employee_name
        FROM project_company_hr_employee e
        LEFT JOIN project_company_hr_employee_assignment a
            ON a.employee_key = e.employee_key
           AND a.company_key_hash = e.company_key_hash
           AND a.assignment_status = 'ACTIVE'
           AND a.is_primary = 1
        LEFT JOIN project_company_branch b ON b.branch_key = COALESCE(a.branch_key, e.branch_key) AND b.company_key_hash = e.company_key_hash
        LEFT JOIN project_company_department d ON d.department_key = COALESCE(a.department_key, e.department_key) AND d.company_key_hash = e.company_key_hash
        LEFT JOIN project_company_hr_job_position j ON j.job_position_key = COALESCE(a.job_position_key, e.job_position_key) AND j.company_key_hash = e.company_key_hash
        LEFT JOIN project_company_hr_team t ON t.team_key = COALESCE(a.team_key, e.team_key) AND t.company_key_hash = e.company_key_hash
        LEFT JOIN project_company_hr_employee m ON m.employee_key = COALESCE(a.reports_to_employee_key, e.reports_to_employee_key) AND m.company_key_hash = e.company_key_hash
        WHERE e.company_key_hash = ? AND e.employee_status <> 'DELETED'
        ORDER BY e.employee_name ASC, e.employee_code ASC
    ", [$companyKeyHash]);
    if (is_array($employees)) {
        foreach ($employees as &$employee) {
            $employee['branch_key'] = (string) ($employee['assignment_branch_key'] ?? $employee['branch_key'] ?? '');
            $employee['department_key'] = (string) ($employee['assignment_department_key'] ?? $employee['department_key'] ?? '');
            $employee['job_position_key'] = (string) ($employee['assignment_job_position_key'] ?? $employee['job_position_key'] ?? '');
            $employee['team_key'] = (string) ($employee['assignment_team_key'] ?? $employee['team_key'] ?? '');
            $employee['reports_to_employee_key'] = (string) ($employee['assignment_reports_to_employee_key'] ?? $employee['reports_to_employee_key'] ?? '');
        }
        unset($employee);
    }
    $builderForms = $db->GetAll("
        SELECT
            form_record.*,
            COALESCE(version_record.form_version_key, '') AS current_form_version_key,
            COALESCE(version_record.version_number, 0) AS current_version_number
        FROM project_company_hr_builder_form form_record
        LEFT JOIN project_company_hr_builder_form_version version_record
            ON version_record.builder_form_key = form_record.builder_form_key
           AND version_record.version_number = (
                SELECT MAX(latest_version.version_number)
                FROM project_company_hr_builder_form_version latest_version
                WHERE latest_version.builder_form_key = form_record.builder_form_key
           )
        WHERE form_record.company_key_hash = ? AND form_record.form_status <> 'DELETED'
        ORDER BY form_record.target_section ASC, form_record.updated_at DESC, form_record.form_title ASC
    ", [$companyKeyHash]);
    $formSubmissions = yovel_admin_hr_form_submissions($company);
    $setupData = yovel_admin_hr_setup_data($company);
    $attendanceData = yovel_admin_hr_attendance_data($company);
    $leaveData = yovel_admin_hr_leave_data($company);
    $recruitmentData = yovel_admin_hr_recruitment_data($company);

    return [
        'branches' => is_array($branches) ? $branches : [],
        'departmentMasters' => is_array($departmentMasters) ? $departmentMasters : [],
        'departments' => is_array($departments) ? $departments : [],
        'jobPositions' => is_array($jobPositions) ? $jobPositions : [],
        'teams' => is_array($teams) ? $teams : [],
        'employees' => is_array($employees) ? $employees : [],
        'builderForms' => is_array($builderForms) ? $builderForms : [],
        'formSubmissions' => $formSubmissions,
        'setup' => $setupData,
        'attendance' => $attendanceData,
        'leave' => $leaveData,
        'recruitment' => $recruitmentData,
        'formFields' => [
            'employee-profiles' => yovel_admin_hr_form_fields($company, 'employee-profiles'),
            'departments' => yovel_admin_hr_form_fields($company, 'departments'),
            'job-positions' => yovel_admin_hr_form_fields($company, 'job-positions'),
            'teams' => yovel_admin_hr_form_fields($company, 'teams'),
        ],
    ];
}
