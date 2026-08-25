<?php
declare(strict_types=1);

function yovel_admin_save_department(array $company, array $admin): string
{
    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $departmentKey = trim((string) ($_POST['department_key'] ?? ''));
    $branchKey = trim((string) ($_POST['branch_key'] ?? ''));
    $departmentCode = yovel_admin_code((string) ($_POST['department_code'] ?? ''));
    $departmentName = trim((string) ($_POST['department_name'] ?? ''));
    $departmentStatus = yovel_admin_status((string) ($_POST['department_status'] ?? 'ACTIVE'), ['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED', 'DELETED']);
    $departmentType = strtoupper(trim((string) ($_POST['department_type'] ?? 'OPERATIONS')));
    $departmentSource = yovel_admin_status((string) ($_POST['department_source'] ?? 'CUSTOM'), ['ERP_DEFAULT', 'CUSTOM'], 'CUSTOM');
    $defaultDepartmentKey = trim((string) ($_POST['default_department_key'] ?? ''));
    $departmentDescription = trim((string) ($_POST['department_description'] ?? ''));
    $isDefault = $departmentSource === 'ERP_DEFAULT' ? 1 : 0;

    if ($departmentKey !== '' && !yovel_admin_is_uuid($departmentKey)) {
        throw new InvalidArgumentException('Invalid department record key.');
    }
    if (!yovel_admin_is_uuid($branchKey)) {
        throw new InvalidArgumentException('Select a valid branch before saving the department.');
    }
    if ($departmentCode === '' || $departmentName === '') {
        throw new InvalidArgumentException('Department code and department name are required.');
    }
    if (!preg_match('/^[A-Z0-9_-]{2,40}$/', $departmentCode)) {
        throw new InvalidArgumentException('Department code must use 2-40 uppercase letters, numbers, underscores, or hyphens.');
    }
    if (strlen($departmentName) > 160 || strlen($departmentType) > 60 || strlen($defaultDepartmentKey) > 80) {
        throw new InvalidArgumentException('Department name, type, or template key exceeds the allowed length.');
    }
    if (!preg_match('/^[A-Z0-9_ -]{2,60}$/', $departmentType)) {
        throw new InvalidArgumentException('Department type must use letters, numbers, spaces, underscores, or hyphens.');
    }
    if ($departmentSource === 'ERP_DEFAULT' && $defaultDepartmentKey === '') {
        throw new InvalidArgumentException('Select an ERP department before saving.');
    }
    if ($departmentSource === 'CUSTOM') {
        $defaultDepartmentKey = '';
        $isDefault = 0;
    }
    if ($defaultDepartmentKey !== '' && !preg_match('/^[a-z0-9_-]{2,80}$/', $defaultDepartmentKey)) {
        throw new InvalidArgumentException('Invalid ERP department template key.');
    }

    $branch = $db->GetRow(
        "SELECT branch_key, branch_code, branch_name FROM project_company_branch WHERE branch_key = ? AND company_key_hash = ? AND branch_status <> 'DELETED'",
        [$branchKey, $companyKeyHash]
    );
    if (!$branch) {
        throw new InvalidArgumentException('Selected branch was not found under this company.');
    }

    if ($departmentSource === 'ERP_DEFAULT') {
        $departmentMaster = $db->GetRow(
            "SELECT department_master_key FROM project_company_department_master WHERE department_master_key = ? AND department_status = 'ACTIVE'",
            [$defaultDepartmentKey]
        );
        if (!$departmentMaster) {
            throw new InvalidArgumentException('Selected standard department was not found.');
        }
    }

    $duplicateCode = (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_department WHERE branch_key = ? AND department_code = ? AND department_key <> ?',
        [$branchKey, $departmentCode, $departmentKey !== '' ? $departmentKey : '__new__']
    );
    if ($duplicateCode > 0) {
        throw new InvalidArgumentException('Department code already exists for the selected branch.');
    }

    $db->BeginTrans();
    try {
        $existing = null;
        if ($departmentKey !== '') {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_department WHERE department_key = ? AND company_key_hash = ?',
                [$departmentKey, $companyKeyHash]
            );
            if (!$existing) {
                throw new InvalidArgumentException('Company department was not found.');
            }
        } else {
            $departmentKey = bx_uuid();
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_department (
                department_key, company_key, company_key_hash, branch_key, department_code, department_name,
                department_status, department_type, department_source, default_department_key, department_description, is_default
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                company_key = VALUES(company_key),
                company_key_hash = VALUES(company_key_hash),
                branch_key = VALUES(branch_key),
                department_code = VALUES(department_code),
                department_name = VALUES(department_name),
                department_status = VALUES(department_status),
                department_type = VALUES(department_type),
                department_source = VALUES(department_source),
                default_department_key = VALUES(default_department_key),
                department_description = VALUES(department_description),
                is_default = VALUES(is_default)",
            [$departmentKey, $companyKey, $companyKeyHash, $branchKey, $departmentCode, $departmentName, $departmentStatus, $departmentType, $departmentSource, $defaultDepartmentKey, $departmentDescription, $isDefault],
            'Company department save'
        );

        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_department', $departmentKey, [
            'department_key' => $departmentKey,
            'company_key' => $companyKey,
            'company_name' => (string) $company['company_name'],
            'branch_key' => $branchKey,
            'branch_code' => (string) ($branch['branch_code'] ?? ''),
            'department_code' => $departmentCode,
            'department_name' => $departmentName,
            'department_status' => $departmentStatus,
            'department_source' => $departmentSource,
            'admin_key' => (string) $admin['admin_key'],
        ], $existing ? 'Company admin updated branch department.' : 'Company admin created branch department.');

        $savedRow = $db->GetRow(
            'SELECT department_key, company_key, company_key_hash, branch_key, department_code, department_name, department_status, department_type, department_source, default_department_key, department_description, is_default FROM project_company_department WHERE department_key = ? AND company_key_hash = ?',
            [$departmentKey, $companyKeyHash]
        );
        foreach ([
            'department_key' => $departmentKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'branch_key' => $branchKey,
            'department_code' => $departmentCode,
            'department_name' => $departmentName,
            'department_status' => $departmentStatus,
            'department_type' => $departmentType,
            'department_source' => $departmentSource,
            'default_department_key' => $defaultDepartmentKey,
            'department_description' => $departmentDescription,
            'is_default' => (string) $isDefault,
        ] as $column => $expectedValue) {
            if (!is_array($savedRow) || (string) ($savedRow[$column] ?? '') !== (string) $expectedValue) {
                throw new RuntimeException('Company department read-back verification failed for ' . $column . '.');
            }
        }
        if ((string) ($_POST['action'] ?? '') === 'save_hr_department') {
            yovel_admin_save_hr_custom_values($db, $company, $admin, 'departments', $departmentKey, yovel_admin_hr_form_fields($company, 'departments', $admin));
        }

        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return $existing ? 'Department updated.' : 'Department created.';
}

function yovel_admin_set_department_status(array $company, array $admin): string
{
    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $departmentKey = trim((string) ($_POST['department_key'] ?? ''));
    $departmentStatus = yovel_admin_status((string) ($_POST['department_status'] ?? ''), ['ACTIVE', 'INACTIVE', 'ARCHIVED', 'DELETED'], '');

    if (!yovel_admin_is_uuid($departmentKey) || $departmentStatus === '') {
        throw new InvalidArgumentException('Invalid company department status request.');
    }

    $db->BeginTrans();
    try {
        $existing = $db->GetRow(
            'SELECT * FROM project_company_department WHERE department_key = ? AND company_key_hash = ?',
            [$departmentKey, $companyKeyHash]
        );
        if (!$existing) {
            throw new InvalidArgumentException('Company department was not found.');
        }

        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_department SET department_status = ? WHERE department_key = ? AND company_key_hash = ?',
            [$departmentStatus, $departmentKey, $companyKeyHash],
            'Company department status update'
        );

        bx_audit($departmentStatus === 'DELETED' ? 'DELETE' : 'STATUS', 'project_company_department', $departmentKey, [
            'department_key' => $departmentKey,
            'company_key' => (string) ($existing['company_key'] ?? ''),
            'company_name' => (string) $company['company_name'],
            'branch_key' => (string) ($existing['branch_key'] ?? ''),
            'department_code' => (string) ($existing['department_code'] ?? ''),
            'department_status' => $departmentStatus,
            'admin_key' => (string) $admin['admin_key'],
        ], 'Company admin changed branch department status.');

        $savedStatus = (string) $db->GetOne(
            'SELECT department_status FROM project_company_department WHERE department_key = ? AND company_key_hash = ?',
            [$departmentKey, $companyKeyHash]
        );
        if ($savedStatus !== $departmentStatus) {
            throw new RuntimeException('Company department status read-back verification failed.');
        }

        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Department status updated.';
}
