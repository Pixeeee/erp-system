<?php
declare(strict_types=1);

function yovel_admin_hr_resolve_assignment_project_key(
    ADOConnection $db,
    string $companyKeyHash,
    string $branchKey,
    string $requestedProjectKey
): string {
    $requestedProjectKey = trim($requestedProjectKey);
    if ($requestedProjectKey !== '') {
        $valid = (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_project
             WHERE company_key_hash = ? AND branch_key = ? AND project_key = ? AND project_status <> 'DELETED'",
            [$companyKeyHash, $branchKey, $requestedProjectKey]
        );
        if ($valid !== 1) {
            throw new InvalidArgumentException('The selected project does not belong to the employee branch.');
        }

        return $requestedProjectKey;
    }
    if ($branchKey === '') {
        return '';
    }

    $projectKeys = $db->GetCol(
        "SELECT project_key FROM project_company_project
         WHERE company_key_hash = ? AND branch_key = ? AND project_status <> 'DELETED'
         ORDER BY x_id",
        [$companyKeyHash, $branchKey]
    );

    return is_array($projectKeys) && count($projectKeys) === 1 ? (string) $projectKeys[0] : '';
}

function yovel_admin_upsert_hr_employee_primary_assignment(
    ADOConnection $db,
    array $company,
    array $admin,
    string $employeeKey,
    array $assignment
): array {
    $companyKey = (string) ($company['company_key'] ?? '');
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $adminKey = (string) ($admin['admin_key'] ?? '');
    $employee = $db->GetRow(
        'SELECT employee_key FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_key = ? FOR UPDATE',
        [$companyKeyHash, $employeeKey]
    );
    if (!is_array($employee) || $employee === []) {
        throw new InvalidArgumentException('Employee assignment subject was not found for this company.');
    }

    $normalized = [];
    foreach (['branch_key', 'project_key', 'department_key', 'job_position_key', 'team_key', 'reports_to_employee_key'] as $field) {
        $normalized[$field] = trim((string) ($assignment[$field] ?? ''));
    }
    $normalized['effective_from'] = trim((string) ($assignment['effective_from'] ?? ''));
    $normalized['assignment_notes'] = trim((string) ($assignment['assignment_notes'] ?? ''));
    if ($normalized['reports_to_employee_key'] === $employeeKey) {
        throw new InvalidArgumentException('An employee cannot report to themselves.');
    }

    $references = [
        'branch_key' => ['project_company_branch', 'branch_key', 'branch'],
        'department_key' => ['project_company_department', 'department_key', 'department'],
        'job_position_key' => ['project_company_hr_job_position', 'job_position_key', 'job position'],
        'team_key' => ['project_company_hr_team', 'team_key', 'team'],
        'reports_to_employee_key' => ['project_company_hr_employee', 'employee_key', 'manager'],
    ];
    foreach ($references as $field => [$table, $keyColumn, $label]) {
        if ($normalized[$field] !== '' && !yovel_admin_existing_key($db, $table, $keyColumn, $companyKeyHash, $normalized[$field])) {
            throw new InvalidArgumentException('The selected ' . $label . ' does not belong to this company.');
        }
    }
    if ($normalized['project_key'] !== '') {
        if ($normalized['branch_key'] === '') {
            throw new InvalidArgumentException('A project assignment requires a branch.');
        }
        $projectValid = (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_project
             WHERE company_key_hash = ? AND branch_key = ? AND project_key = ? AND project_status <> 'DELETED'",
            [$companyKeyHash, $normalized['branch_key'], $normalized['project_key']]
        );
        if ($projectValid !== 1) {
            throw new InvalidArgumentException('The selected project does not belong to the employee branch.');
        }
    }
    if ($normalized['department_key'] !== '' && $normalized['branch_key'] !== '') {
        $departmentBranch = (string) $db->GetOne(
            'SELECT branch_key FROM project_company_department WHERE company_key_hash = ? AND department_key = ? LIMIT 1',
            [$companyKeyHash, $normalized['department_key']]
        );
        if ($departmentBranch !== '' && $departmentBranch !== $normalized['branch_key']) {
            throw new InvalidArgumentException('The selected department does not belong to the employee branch.');
        }
    }
    if ($normalized['effective_from'] !== '') {
        yovel_admin_optional_date($normalized['effective_from'], 'Assignment effective date');
    }

    $activeAssignments = $db->GetAll(
        "SELECT * FROM project_company_hr_employee_assignment
         WHERE company_key_hash = ? AND employee_key = ? AND assignment_status = 'ACTIVE' AND is_primary = 1
         ORDER BY x_id DESC FOR UPDATE",
        [$companyKeyHash, $employeeKey]
    );
    $current = is_array($activeAssignments) && $activeAssignments !== [] ? $activeAssignments[0] : null;
    $scopeFields = ['branch_key', 'project_key', 'department_key', 'job_position_key', 'team_key', 'reports_to_employee_key'];
    $unchanged = is_array($current);
    if ($unchanged) {
        foreach ($scopeFields as $field) {
            if ((string) ($current[$field] ?? '') !== $normalized[$field]) {
                $unchanged = false;
                break;
            }
        }
    }

    if ($unchanged) {
        $assignmentKey = (string) $current['assignment_key'];
    } else {
        if (is_array($activeAssignments) && $activeAssignments !== []) {
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_hr_employee_assignment
                 SET assignment_status = 'ENDED', is_primary = 0, effective_until = ?, updated_by_admin_key = ?
                 WHERE company_key_hash = ? AND employee_key = ? AND assignment_status = 'ACTIVE' AND is_primary = 1",
                [date('Y-m-d'), $adminKey, $companyKeyHash, $employeeKey],
                'HR employee assignment history update'
            );
        }
        $assignmentKey = bx_uuid();
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_hr_employee_assignment (
                assignment_key, company_key, company_key_hash, employee_key, branch_key, project_key,
                department_key, job_position_key, team_key, reports_to_employee_key, assignment_status,
                is_primary, effective_from, assignment_notes, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 1, ?, ?, ?, ?)",
            [
                $assignmentKey, $companyKey, $companyKeyHash, $employeeKey,
                $normalized['branch_key'] !== '' ? $normalized['branch_key'] : null,
                $normalized['project_key'] !== '' ? $normalized['project_key'] : null,
                $normalized['department_key'] !== '' ? $normalized['department_key'] : null,
                $normalized['job_position_key'] !== '' ? $normalized['job_position_key'] : null,
                $normalized['team_key'] !== '' ? $normalized['team_key'] : null,
                $normalized['reports_to_employee_key'] !== '' ? $normalized['reports_to_employee_key'] : null,
                $normalized['effective_from'] !== '' ? $normalized['effective_from'] : date('Y-m-d'),
                $normalized['assignment_notes'], $adminKey, $adminKey,
            ],
            'HR employee assignment create'
        );
        bx_audit('CREATE', 'project_company_hr_employee_assignment', $assignmentKey, [
            'company_key' => $companyKey,
            'employee_key' => $employeeKey,
            'branch_key' => $normalized['branch_key'],
            'project_key' => $normalized['project_key'],
            'department_key' => $normalized['department_key'],
            'job_position_key' => $normalized['job_position_key'],
        ], is_array($current) ? 'Company admin reassigned an employee.' : 'Company admin assigned an employee.');
    }

    yovel_admin_db_execute(
        $db,
        "UPDATE project_company_hr_employee
         SET branch_key = ?, department_key = ?, job_position_key = ?, team_key = ?, reports_to_employee_key = ?, updated_by_admin_key = ?
         WHERE company_key_hash = ? AND employee_key = ?",
        [
            $normalized['branch_key'] !== '' ? $normalized['branch_key'] : null,
            $normalized['department_key'] !== '' ? $normalized['department_key'] : null,
            $normalized['job_position_key'] !== '' ? $normalized['job_position_key'] : null,
            $normalized['team_key'] !== '' ? $normalized['team_key'] : null,
            $normalized['reports_to_employee_key'] !== '' ? $normalized['reports_to_employee_key'] : null,
            $adminKey, $companyKeyHash, $employeeKey,
        ],
        'HR employee assignment compatibility update'
    );

    $readBack = $db->GetRow(
        "SELECT * FROM project_company_hr_employee_assignment
         WHERE company_key_hash = ? AND employee_key = ? AND assignment_key = ?
           AND assignment_status = 'ACTIVE' AND is_primary = 1 LIMIT 1",
        [$companyKeyHash, $employeeKey, $assignmentKey]
    );
    if (!is_array($readBack) || (string) ($readBack['assignment_key'] ?? '') !== $assignmentKey) {
        throw new RuntimeException('HR employee assignment direct read-back failed.');
    }
    foreach ($scopeFields as $field) {
        if ((string) ($readBack[$field] ?? '') !== $normalized[$field]) {
            throw new RuntimeException('HR employee assignment read-back mismatch for ' . $field . '.');
        }
    }
    $mirror = $db->GetRow(
        'SELECT branch_key, department_key, job_position_key, team_key, reports_to_employee_key FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_key = ? LIMIT 1',
        [$companyKeyHash, $employeeKey]
    );
    foreach (['branch_key', 'department_key', 'job_position_key', 'team_key', 'reports_to_employee_key'] as $field) {
        if (!is_array($mirror) || (string) ($mirror[$field] ?? '') !== $normalized[$field]) {
            throw new RuntimeException('HR employee assignment compatibility read-back mismatch for ' . $field . '.');
        }
    }

    return $readBack;
}

function yovel_admin_save_hr_employee(array $company, array $admin): string
{
    yovel_admin_hr_schema();

    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $employeeKey = trim((string) ($_POST['employee_key'] ?? ''));
    $employeeCode = yovel_admin_code((string) ($_POST['employee_code'] ?? ''));
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $middleName = trim((string) ($_POST['middle_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $employeeName = trim((string) ($_POST['employee_name'] ?? ''));
    $employeeStatus = yovel_admin_status((string) ($_POST['employee_status'] ?? 'ACTIVE'), ['DRAFT', 'ACTIVE', 'INACTIVE', 'ON_LEAVE', 'SEPARATED', 'DELETED']);
    $branchKey = yovel_admin_optional_company_key($db, 'project_company_branch', 'branch_key', $companyKeyHash, (string) ($_POST['branch_key'] ?? ''), 'branch');
    $departmentKey = yovel_admin_optional_company_key($db, 'project_company_department', 'department_key', $companyKeyHash, (string) ($_POST['department_key'] ?? ''), 'department');
    $jobPositionKey = yovel_admin_optional_company_key($db, 'project_company_hr_job_position', 'job_position_key', $companyKeyHash, (string) ($_POST['job_position_key'] ?? ''), 'job position');
    $teamKey = yovel_admin_optional_company_key($db, 'project_company_hr_team', 'team_key', $companyKeyHash, (string) ($_POST['team_key'] ?? ''), 'team');
    $reportsToEmployeeKey = yovel_admin_optional_company_key($db, 'project_company_hr_employee', 'employee_key', $companyKeyHash, (string) ($_POST['reports_to_employee_key'] ?? ''), 'manager');
    $projectKey = yovel_admin_hr_resolve_assignment_project_key(
        $db,
        $companyKeyHash,
        $branchKey,
        (string) ($_POST['project_key'] ?? '')
    );
    $dateOfBirth = yovel_admin_optional_date((string) ($_POST['date_of_birth'] ?? ''), 'Date of birth');
    $dateOfJoining = yovel_admin_optional_date((string) ($_POST['date_of_joining'] ?? ''), 'Date of joining');
    $employmentType = trim((string) ($_POST['employment_type'] ?? ''));
    $mobileNumber = trim((string) ($_POST['mobile_number'] ?? ''));
    $companyEmail = trim((string) ($_POST['company_email'] ?? ''));
    $personalEmail = trim((string) ($_POST['personal_email'] ?? ''));
    $employeeNotes = trim((string) ($_POST['employee_notes'] ?? ''));

    if ($employeeKey !== '' && !yovel_admin_is_uuid($employeeKey)) {
        throw new InvalidArgumentException('Invalid employee profile key.');
    }
    if ($employeeCode === '' || !preg_match('/^[A-Z0-9_.-]{2,80}$/', $employeeCode)) {
        throw new InvalidArgumentException('Employee code must use 2-80 uppercase letters, numbers, underscores, periods, or hyphens.');
    }
    if ($employeeName === '') {
        $employeeName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName], static fn (string $part): bool => $part !== '')));
    }
    if ($firstName === '' || $employeeName === '') {
        throw new InvalidArgumentException('First name and full name are required.');
    }
    foreach ([
        'First name' => [$firstName, 120],
        'Middle name' => [$middleName, 120],
        'Last name' => [$lastName, 120],
        'Full name' => [$employeeName, 200],
        'Employment type' => [$employmentType, 80],
        'Mobile number' => [$mobileNumber, 60],
        'Company email' => [$companyEmail, 160],
        'Personal email' => [$personalEmail, 160],
    ] as $label => [$value, $max]) {
        if (strlen((string) $value) > $max) {
            throw new InvalidArgumentException($label . ' exceeds the allowed length.');
        }
    }
    foreach (['Company email' => $companyEmail, 'Personal email' => $personalEmail] as $label => $email) {
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException($label . ' must be a valid email address.');
        }
    }

    $db->BeginTrans();
    try {
        $existing = null;
        if ($employeeKey !== '') {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_hr_employee WHERE employee_key = ? AND company_key_hash = ? FOR UPDATE',
                [$employeeKey, $companyKeyHash]
            );
            if (!$existing) {
                throw new InvalidArgumentException('Employee profile was not found for this company.');
            }
        } else {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_hr_employee WHERE employee_code = ? AND company_key_hash = ? FOR UPDATE',
                [$employeeCode, $companyKeyHash]
            );
            if ($existing) {
                $employeeKey = (string) $existing['employee_key'];
            }
        }
        if ($employeeKey === '') {
            $employeeKey = bx_uuid();
        }
        if ($reportsToEmployeeKey !== '' && $reportsToEmployeeKey === $employeeKey) {
            throw new InvalidArgumentException('An employee cannot report to themselves.');
        }

        $duplicateCode = (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_code = ? AND employee_key <> ?',
            [$companyKeyHash, $employeeCode, $employeeKey]
        );
        if ($duplicateCode > 0) {
            throw new InvalidArgumentException('Employee code already belongs to another employee.');
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_hr_employee (
                employee_key, company_key, company_key_hash, employee_code, first_name, middle_name, last_name,
                employee_name, employee_status, branch_key, department_key, job_position_key, team_key,
                reports_to_employee_key, date_of_birth, date_of_joining, employment_type, mobile_number,
                company_email, personal_email, employee_notes, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                company_key = VALUES(company_key),
                company_key_hash = VALUES(company_key_hash),
                employee_code = VALUES(employee_code),
                first_name = VALUES(first_name),
                middle_name = VALUES(middle_name),
                last_name = VALUES(last_name),
                employee_name = VALUES(employee_name),
                employee_status = VALUES(employee_status),
                branch_key = VALUES(branch_key),
                department_key = VALUES(department_key),
                job_position_key = VALUES(job_position_key),
                team_key = VALUES(team_key),
                reports_to_employee_key = VALUES(reports_to_employee_key),
                date_of_birth = VALUES(date_of_birth),
                date_of_joining = VALUES(date_of_joining),
                employment_type = VALUES(employment_type),
                mobile_number = VALUES(mobile_number),
                company_email = VALUES(company_email),
                personal_email = VALUES(personal_email),
                employee_notes = VALUES(employee_notes),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $employeeKey,
                $companyKey,
                $companyKeyHash,
                $employeeCode,
                $firstName,
                $middleName,
                $lastName,
                $employeeName,
                $employeeStatus,
                $branchKey !== '' ? $branchKey : null,
                $departmentKey !== '' ? $departmentKey : null,
                $jobPositionKey !== '' ? $jobPositionKey : null,
                $teamKey !== '' ? $teamKey : null,
                $reportsToEmployeeKey !== '' ? $reportsToEmployeeKey : null,
                $dateOfBirth !== '' ? $dateOfBirth : null,
                $dateOfJoining !== '' ? $dateOfJoining : null,
                $employmentType,
                $mobileNumber,
                $companyEmail,
                $personalEmail,
                $employeeNotes,
                $adminKey,
                $adminKey,
            ],
            'HR employee save'
        );

        yovel_admin_upsert_hr_employee_primary_assignment($db, $company, $admin, $employeeKey, [
            'branch_key' => $branchKey,
            'project_key' => $projectKey,
            'department_key' => $departmentKey,
            'job_position_key' => $jobPositionKey,
            'team_key' => $teamKey,
            'reports_to_employee_key' => $reportsToEmployeeKey,
            'effective_from' => $dateOfJoining,
        ]);

        $savedRow = $db->GetRow(
            'SELECT employee_key, company_key, company_key_hash, employee_code, first_name, middle_name, last_name, employee_name, employee_status, branch_key, department_key, job_position_key, team_key, reports_to_employee_key, date_of_birth, date_of_joining, employment_type, mobile_number, company_email, personal_email, employee_notes FROM project_company_hr_employee WHERE employee_key = ? AND company_key_hash = ? LIMIT 1',
            [$employeeKey, $companyKeyHash]
        );
        foreach ([
            'employee_key' => $employeeKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'employee_code' => $employeeCode,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'employee_name' => $employeeName,
            'employee_status' => $employeeStatus,
            'branch_key' => $branchKey,
            'department_key' => $departmentKey,
            'job_position_key' => $jobPositionKey,
            'team_key' => $teamKey,
            'reports_to_employee_key' => $reportsToEmployeeKey,
            'date_of_birth' => $dateOfBirth,
            'date_of_joining' => $dateOfJoining,
            'employment_type' => $employmentType,
            'mobile_number' => $mobileNumber,
            'company_email' => $companyEmail,
            'personal_email' => $personalEmail,
            'employee_notes' => $employeeNotes,
        ] as $column => $expectedValue) {
            if (!is_array($savedRow) || (string) ($savedRow[$column] ?? '') !== (string) $expectedValue) {
                throw new RuntimeException('HR employee read-back verification failed for ' . $column . '.');
            }
        }
        yovel_admin_save_hr_custom_values($db, $company, $admin, 'employee-profiles', $employeeKey, yovel_admin_hr_form_fields($company, 'employee-profiles', $admin));

        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_employee', $employeeKey, [
            'company_key' => $companyKey,
            'company_name' => (string) $company['company_name'],
            'employee_code' => $employeeCode,
            'employee_name' => $employeeName,
            'employee_status' => $employeeStatus,
            'admin_key' => $adminKey,
        ], $existing ? 'Company admin updated HR employee profile.' : 'Company admin created HR employee profile.');

        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return $existing ? 'Employee profile updated.' : 'Employee profile created.';
}

function yovel_admin_set_hr_employee_status(array $company, array $admin): string
{
    yovel_admin_hr_schema();

    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $employeeKey = trim((string) ($_POST['employee_key'] ?? ''));
    $employeeStatus = yovel_admin_status((string) ($_POST['employee_status'] ?? ''), ['ACTIVE', 'INACTIVE', 'ON_LEAVE', 'SEPARATED', 'DELETED'], '');

    if (!yovel_admin_is_uuid($employeeKey) || $employeeStatus === '') {
        throw new InvalidArgumentException('Invalid employee status request.');
    }

    $db->BeginTrans();
    try {
        $existing = $db->GetRow(
            'SELECT employee_key, company_key, employee_code, employee_name FROM project_company_hr_employee WHERE employee_key = ? AND company_key_hash = ? FOR UPDATE',
            [$employeeKey, $companyKeyHash]
        );
        if (!$existing) {
            throw new InvalidArgumentException('Employee profile was not found for this company.');
        }

        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_hr_employee SET employee_status = ?, updated_by_admin_key = ? WHERE employee_key = ? AND company_key_hash = ?',
            [$employeeStatus, (string) $admin['admin_key'], $employeeKey, $companyKeyHash],
            'HR employee status update'
        );

        $savedStatus = (string) $db->GetOne(
            'SELECT employee_status FROM project_company_hr_employee WHERE employee_key = ? AND company_key_hash = ? LIMIT 1',
            [$employeeKey, $companyKeyHash]
        );
        if ($savedStatus !== $employeeStatus) {
            throw new RuntimeException('HR employee status read-back verification failed.');
        }

        bx_audit($employeeStatus === 'DELETED' ? 'DELETE' : 'STATUS', 'project_company_hr_employee', $employeeKey, [
            'company_key' => (string) ($existing['company_key'] ?? ''),
            'company_name' => (string) $company['company_name'],
            'employee_code' => (string) ($existing['employee_code'] ?? ''),
            'employee_name' => (string) ($existing['employee_name'] ?? ''),
            'employee_status' => $employeeStatus,
            'admin_key' => (string) $admin['admin_key'],
        ], 'Company admin changed HR employee profile status.');

        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Employee status updated.';
}
