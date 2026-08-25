<?php
declare(strict_types=1);

function yovel_admin_projects_settings(array $company, array $admin): array
{
    [, $companyKeyHash] = yovel_admin_projects_scope($company, $admin);
    yovel_admin_projects_schema();
    $row = bx_db()->GetRow(
        "SELECT project_settings_key, company_key, company_key_hash,
                ignore_employee_time_overlap, fetch_timesheet_in_sales_invoice,
                settings_status, created_by_admin_key, updated_by_admin_key,
                created_at, updated_at
         FROM project_company_project_settings
         WHERE company_key_hash = ? AND settings_status = 'ACTIVE'
         LIMIT 1",
        [$companyKeyHash]
    );

    if (!is_array($row) || $row === []) {
        return [
            'project_settings_key' => '',
            'ignore_employee_time_overlap' => 0,
            'fetch_timesheet_in_sales_invoice' => 0,
            'settings_status' => 'ACTIVE',
            'persisted' => false,
        ];
    }
    $row['persisted'] = true;
    return $row;
}

function yovel_admin_projects_save_settings(array $company, array $admin, array $input): array
{
    yovel_admin_projects_assert_csrf($input);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    $overlap = yovel_admin_projects_flag($input['ignore_employee_time_overlap'] ?? 0);
    $invoice = yovel_admin_projects_flag($input['fetch_timesheet_in_sales_invoice'] ?? 0);

    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey,
        $companyKeyHash,
        $adminKey,
        $overlap,
        $invoice
    ): array {
        $companyLock = $db->GetRow(
            "SELECT company_key, company_key_hash FROM project_company
             WHERE company_key = ? AND company_key_hash = ? AND company_status = 'ACTIVE'
             FOR UPDATE",
            [$companyKey, $companyKeyHash]
        );
        yovel_admin_projects_assert_readback(
            ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash],
            is_array($companyLock) ? $companyLock : [],
            ['company_key', 'company_key_hash'],
            'Projects company lock'
        );

        $existing = $db->GetRow(
            'SELECT project_settings_key FROM project_company_project_settings WHERE company_key_hash = ? FOR UPDATE',
            [$companyKeyHash]
        );
        $isUpdate = is_array($existing) && $existing !== [];
        $settingsKey = $isUpdate ? (string) $existing['project_settings_key'] : bx_uuid();
        yovel_admin_projects_db_execute(
            $db,
            "INSERT INTO project_company_project_settings (
                project_settings_key, company_key, company_key_hash,
                ignore_employee_time_overlap, fetch_timesheet_in_sales_invoice,
                settings_status, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?, ?)
             ON DUPLICATE KEY UPDATE
                company_key = VALUES(company_key),
                ignore_employee_time_overlap = VALUES(ignore_employee_time_overlap),
                fetch_timesheet_in_sales_invoice = VALUES(fetch_timesheet_in_sales_invoice),
                settings_status = 'ACTIVE',
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$settingsKey, $companyKey, $companyKeyHash, $overlap, $invoice, $adminKey, $adminKey],
            'Projects settings save'
        );

        yovel_admin_projects_audit(
            $isUpdate ? 'UPDATE' : 'CREATE',
            'project_company_project_settings',
            $settingsKey,
            $companyKeyHash,
            $adminKey,
            [
                'ignore_employee_time_overlap' => $overlap,
                'fetch_timesheet_in_sales_invoice' => $invoice,
            ]
        );
        $saved = $db->GetRow(
            "SELECT project_settings_key, company_key, company_key_hash,
                    ignore_employee_time_overlap, fetch_timesheet_in_sales_invoice,
                    settings_status, created_by_admin_key, updated_by_admin_key,
                    created_at, updated_at
             FROM project_company_project_settings
             WHERE project_settings_key = ? AND company_key_hash = ?
             LIMIT 1",
            [$settingsKey, $companyKeyHash]
        );
        yovel_admin_projects_assert_readback(
            [
                'project_settings_key' => $settingsKey,
                'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash,
                'ignore_employee_time_overlap' => $overlap,
                'fetch_timesheet_in_sales_invoice' => $invoice,
                'settings_status' => 'ACTIVE',
                'updated_by_admin_key' => $adminKey,
            ],
            is_array($saved) ? $saved : [],
            [
                'project_settings_key', 'company_key', 'company_key_hash',
                'ignore_employee_time_overlap', 'fetch_timesheet_in_sales_invoice',
                'settings_status', 'updated_by_admin_key',
            ],
            'Projects settings'
        );
        $saved['persisted'] = true;
        return $saved;
    });
}
