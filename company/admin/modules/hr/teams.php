<?php
declare(strict_types=1);

function yovel_admin_save_hr_team(array $company, array $admin): string
{
    yovel_admin_hr_schema();

    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $teamKey = trim((string) ($_POST['team_key'] ?? ''));
    $teamCode = yovel_admin_code((string) ($_POST['team_code'] ?? ''));
    $teamName = trim((string) ($_POST['team_name'] ?? ''));
    $teamDescription = trim((string) ($_POST['team_description'] ?? ''));
    $teamStatus = yovel_admin_status((string) ($_POST['team_status'] ?? 'ACTIVE'), ['DRAFT', 'ACTIVE', 'INACTIVE', 'DELETED']);

    if ($teamKey !== '' && !yovel_admin_is_uuid($teamKey)) {
        throw new InvalidArgumentException('Invalid team key.');
    }
    if ($teamCode === '' || !preg_match('/^[A-Z0-9_.-]{2,80}$/', $teamCode)) {
        throw new InvalidArgumentException('Team code must use 2-80 uppercase letters, numbers, underscores, periods, or hyphens.');
    }
    if ($teamName === '') {
        throw new InvalidArgumentException('Team name is required.');
    }
    foreach ([
        'Team name' => [$teamName, 160],
        'Team description' => [$teamDescription, 5000],
    ] as $label => [$value, $max]) {
        if (strlen((string) $value) > $max) {
            throw new InvalidArgumentException($label . ' exceeds the allowed length.');
        }
    }

    $db->BeginTrans();
    try {
        $existing = null;
        if ($teamKey !== '') {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_hr_team WHERE team_key = ? AND company_key_hash = ? FOR UPDATE',
                [$teamKey, $companyKeyHash]
            );
            if (!$existing) {
                throw new InvalidArgumentException('Team was not found for this company.');
            }
        } else {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_hr_team WHERE team_code = ? AND company_key_hash = ? FOR UPDATE',
                [$teamCode, $companyKeyHash]
            );
            if ($existing) {
                $teamKey = (string) $existing['team_key'];
            }
        }
        if ($teamKey === '') {
            $teamKey = bx_uuid();
        }

        $duplicateCode = (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_hr_team WHERE company_key_hash = ? AND team_code = ? AND team_key <> ?',
            [$companyKeyHash, $teamCode, $teamKey]
        );
        if ($duplicateCode > 0) {
            throw new InvalidArgumentException('Team code already belongs to another team.');
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_hr_team (
                team_key, company_key, company_key_hash, team_code, team_name,
                team_description, team_status, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                company_key = VALUES(company_key),
                company_key_hash = VALUES(company_key_hash),
                team_code = VALUES(team_code),
                team_name = VALUES(team_name),
                team_description = VALUES(team_description),
                team_status = VALUES(team_status),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $teamKey,
                $companyKey,
                $companyKeyHash,
                $teamCode,
                $teamName,
                $teamDescription,
                $teamStatus,
                $adminKey,
                $adminKey,
            ],
            'HR team save'
        );

        $savedRow = $db->GetRow(
            'SELECT team_key, company_key, company_key_hash, team_code, team_name, team_description, team_status FROM project_company_hr_team WHERE team_key = ? AND company_key_hash = ? LIMIT 1',
            [$teamKey, $companyKeyHash]
        );
        foreach ([
            'team_key' => $teamKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'team_code' => $teamCode,
            'team_name' => $teamName,
            'team_description' => $teamDescription,
            'team_status' => $teamStatus,
        ] as $column => $expectedValue) {
            if (!is_array($savedRow) || (string) ($savedRow[$column] ?? '') !== (string) $expectedValue) {
                throw new RuntimeException('HR team read-back verification failed for ' . $column . '.');
            }
        }

        yovel_admin_save_hr_custom_values($db, $company, $admin, 'teams', $teamKey, yovel_admin_hr_form_fields($company, 'teams', $admin));

        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_team', $teamKey, [
            'company_key' => $companyKey,
            'company_name' => (string) $company['company_name'],
            'team_code' => $teamCode,
            'team_name' => $teamName,
            'team_status' => $teamStatus,
            'admin_key' => $adminKey,
        ], $existing ? 'Company admin updated HR team.' : 'Company admin created HR team.');

        if ($db->CommitTrans() === false) {
            throw new RuntimeException('HR team transaction commit failed.');
        }
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return $existing ? 'Team updated.' : 'Team created.';
}

function yovel_admin_set_hr_team_status(array $company, array $admin): string
{
    yovel_admin_hr_schema();

    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $teamKey = trim((string) ($_POST['team_key'] ?? ''));
    $teamStatus = yovel_admin_status((string) ($_POST['team_status'] ?? ''), ['ACTIVE', 'INACTIVE', 'DELETED'], '');

    if (!yovel_admin_is_uuid($teamKey) || $teamStatus === '') {
        throw new InvalidArgumentException('Invalid team status request.');
    }

    $db->BeginTrans();
    try {
        $existing = $db->GetRow(
            'SELECT team_key, company_key, team_code, team_name FROM project_company_hr_team WHERE team_key = ? AND company_key_hash = ? FOR UPDATE',
            [$teamKey, $companyKeyHash]
        );
        if (!$existing) {
            throw new InvalidArgumentException('Team was not found for this company.');
        }

        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_hr_team SET team_status = ?, updated_by_admin_key = ? WHERE team_key = ? AND company_key_hash = ?',
            [$teamStatus, (string) $admin['admin_key'], $teamKey, $companyKeyHash],
            'HR team status update'
        );

        $savedStatus = (string) $db->GetOne(
            'SELECT team_status FROM project_company_hr_team WHERE team_key = ? AND company_key_hash = ? LIMIT 1',
            [$teamKey, $companyKeyHash]
        );
        if ($savedStatus !== $teamStatus) {
            throw new RuntimeException('HR team status read-back verification failed.');
        }

        bx_audit($teamStatus === 'DELETED' ? 'DELETE' : 'STATUS', 'project_company_hr_team', $teamKey, [
            'company_key' => (string) ($existing['company_key'] ?? ''),
            'company_name' => (string) $company['company_name'],
            'team_code' => (string) ($existing['team_code'] ?? ''),
            'team_name' => (string) ($existing['team_name'] ?? ''),
            'team_status' => $teamStatus,
            'admin_key' => (string) $admin['admin_key'],
        ], 'Company admin changed HR team status.');

        if ($db->CommitTrans() === false) {
            throw new RuntimeException('HR team status transaction commit failed.');
        }
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Team status updated.';
}
