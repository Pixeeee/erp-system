<?php
declare(strict_types=1);

function yovel_admin_save_hr_job_position(array $company, array $admin): string
{
    yovel_admin_hr_schema();

    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $jobPositionKey = trim((string) ($_POST['job_position_key'] ?? ''));
    $jobPositionCode = yovel_admin_code((string) ($_POST['job_position_code'] ?? ''));
    $jobPositionName = trim((string) ($_POST['job_position_name'] ?? ''));
    $jobPositionDescription = trim((string) ($_POST['job_position_description'] ?? ''));
    $jobPositionStatus = yovel_admin_status((string) ($_POST['job_position_status'] ?? 'ACTIVE'), ['DRAFT', 'ACTIVE', 'INACTIVE', 'DELETED']);

    if ($jobPositionKey !== '' && !yovel_admin_is_uuid($jobPositionKey)) {
        throw new InvalidArgumentException('Invalid job position key.');
    }
    if ($jobPositionCode === '' || !preg_match('/^[A-Z0-9_.-]{2,80}$/', $jobPositionCode)) {
        throw new InvalidArgumentException('Job position code must use 2-80 uppercase letters, numbers, underscores, periods, or hyphens.');
    }
    if ($jobPositionName === '') {
        throw new InvalidArgumentException('Job position name is required.');
    }
    foreach ([
        'Job position name' => [$jobPositionName, 160],
        'Job position description' => [$jobPositionDescription, 5000],
    ] as $label => [$value, $max]) {
        if (strlen((string) $value) > $max) {
            throw new InvalidArgumentException($label . ' exceeds the allowed length.');
        }
    }

    $db->BeginTrans();
    try {
        $existing = null;
        if ($jobPositionKey !== '') {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_hr_job_position WHERE job_position_key = ? AND company_key_hash = ? FOR UPDATE',
                [$jobPositionKey, $companyKeyHash]
            );
            if (!$existing) {
                throw new InvalidArgumentException('Job position was not found for this company.');
            }
        } else {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_hr_job_position WHERE job_position_code = ? AND company_key_hash = ? FOR UPDATE',
                [$jobPositionCode, $companyKeyHash]
            );
            if ($existing) {
                $jobPositionKey = (string) $existing['job_position_key'];
            }
        }
        if ($jobPositionKey === '') {
            $jobPositionKey = bx_uuid();
        }

        $duplicateCode = (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_hr_job_position WHERE company_key_hash = ? AND job_position_code = ? AND job_position_key <> ?',
            [$companyKeyHash, $jobPositionCode, $jobPositionKey]
        );
        if ($duplicateCode > 0) {
            throw new InvalidArgumentException('Job position code already belongs to another position.');
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_hr_job_position (
                job_position_key, company_key, company_key_hash, job_position_code, job_position_name,
                job_position_description, job_position_status, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                company_key = VALUES(company_key),
                company_key_hash = VALUES(company_key_hash),
                job_position_code = VALUES(job_position_code),
                job_position_name = VALUES(job_position_name),
                job_position_description = VALUES(job_position_description),
                job_position_status = VALUES(job_position_status),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $jobPositionKey,
                $companyKey,
                $companyKeyHash,
                $jobPositionCode,
                $jobPositionName,
                $jobPositionDescription,
                $jobPositionStatus,
                $adminKey,
                $adminKey,
            ],
            'HR job position save'
        );

        $savedRow = $db->GetRow(
            'SELECT job_position_key, company_key, company_key_hash, job_position_code, job_position_name, job_position_description, job_position_status FROM project_company_hr_job_position WHERE job_position_key = ? AND company_key_hash = ? LIMIT 1',
            [$jobPositionKey, $companyKeyHash]
        );
        foreach ([
            'job_position_key' => $jobPositionKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'job_position_code' => $jobPositionCode,
            'job_position_name' => $jobPositionName,
            'job_position_description' => $jobPositionDescription,
            'job_position_status' => $jobPositionStatus,
        ] as $column => $expectedValue) {
            if (!is_array($savedRow) || (string) ($savedRow[$column] ?? '') !== (string) $expectedValue) {
                throw new RuntimeException('HR job position read-back verification failed for ' . $column . '.');
            }
        }
        yovel_admin_save_hr_custom_values($db, $company, $admin, 'job-positions', $jobPositionKey, yovel_admin_hr_form_fields($company, 'job-positions', $admin));

        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_job_position', $jobPositionKey, [
            'company_key' => $companyKey,
            'company_name' => (string) $company['company_name'],
            'job_position_code' => $jobPositionCode,
            'job_position_name' => $jobPositionName,
            'job_position_status' => $jobPositionStatus,
            'admin_key' => $adminKey,
        ], $existing ? 'Company admin updated HR job position.' : 'Company admin created HR job position.');

        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return $existing ? 'Job position updated.' : 'Job position created.';
}

function yovel_admin_set_hr_job_position_status(array $company, array $admin): string
{
    yovel_admin_hr_schema();

    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $jobPositionKey = trim((string) ($_POST['job_position_key'] ?? ''));
    $jobPositionStatus = yovel_admin_status((string) ($_POST['job_position_status'] ?? ''), ['ACTIVE', 'INACTIVE', 'DELETED'], '');

    if (!yovel_admin_is_uuid($jobPositionKey) || $jobPositionStatus === '') {
        throw new InvalidArgumentException('Invalid job position status request.');
    }

    $db->BeginTrans();
    try {
        $existing = $db->GetRow(
            'SELECT job_position_key, company_key, job_position_code, job_position_name FROM project_company_hr_job_position WHERE job_position_key = ? AND company_key_hash = ? FOR UPDATE',
            [$jobPositionKey, $companyKeyHash]
        );
        if (!$existing) {
            throw new InvalidArgumentException('Job position was not found for this company.');
        }

        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_hr_job_position SET job_position_status = ?, updated_by_admin_key = ? WHERE job_position_key = ? AND company_key_hash = ?',
            [$jobPositionStatus, (string) $admin['admin_key'], $jobPositionKey, $companyKeyHash],
            'HR job position status update'
        );

        $savedStatus = (string) $db->GetOne(
            'SELECT job_position_status FROM project_company_hr_job_position WHERE job_position_key = ? AND company_key_hash = ? LIMIT 1',
            [$jobPositionKey, $companyKeyHash]
        );
        if ($savedStatus !== $jobPositionStatus) {
            throw new RuntimeException('HR job position status read-back verification failed.');
        }

        bx_audit($jobPositionStatus === 'DELETED' ? 'DELETE' : 'STATUS', 'project_company_hr_job_position', $jobPositionKey, [
            'company_key' => (string) ($existing['company_key'] ?? ''),
            'company_name' => (string) $company['company_name'],
            'job_position_code' => (string) ($existing['job_position_code'] ?? ''),
            'job_position_name' => (string) ($existing['job_position_name'] ?? ''),
            'job_position_status' => $jobPositionStatus,
            'admin_key' => (string) $admin['admin_key'],
        ], 'Company admin changed HR job position status.');

        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Job position status updated.';
}
