<?php
declare(strict_types=1);

$salesCrmTestRoot = dirname(__DIR__);
require_once $salesCrmTestRoot . '/app/foundation.php';
require_once $salesCrmTestRoot . '/company/admin/core/functions.php';
require_once $salesCrmTestRoot . '/company/admin/modules/shared/registry.php';
require_once $salesCrmTestRoot . '/company/admin/modules/shared/forms.php';
require_once $salesCrmTestRoot . '/company/admin/modules/sales-crm/functions.php';

function sales_crm_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function sales_crm_test_company(): array
{
    $fixture = bx_db()->GetRow(
        "SELECT company_record.company_key, company_record.company_key_hash, company_record.company_name,
                company_record.company_slug, admin_record.admin_key, admin_record.admin_login
         FROM project_company company_record
         INNER JOIN project_company_admin admin_record
            ON admin_record.company_key_hash = company_record.company_key_hash
           AND admin_record.admin_status = 'ACTIVE'
         WHERE company_record.company_status = 'ACTIVE'
         ORDER BY company_record.x_id, admin_record.x_id
         LIMIT 1"
    );
    sales_crm_assert(is_array($fixture) && $fixture !== [], 'An active company-admin Sales / CRM fixture is required.');

    return [
        'company' => [
            'company_key' => (string) $fixture['company_key'],
            'company_key_hash' => (string) $fixture['company_key_hash'],
            'company_name' => (string) $fixture['company_name'],
            'company_slug' => (string) $fixture['company_slug'],
        ],
        'admin' => [
            'admin_key' => (string) $fixture['admin_key'],
            'admin_login' => (string) $fixture['admin_login'],
        ],
    ];
}

function sales_crm_create_isolated_company(ADOConnection $db): array
{
    $companyKey = bx_uuid();
    $companyHash = hash('sha256', $companyKey);
    $suffix = strtolower(substr(str_replace('-', '', $companyKey), 0, 12));
    $companyCode = strtoupper('SC' . substr($suffix, 0, 8));
    $companySlug = 'sc02-' . $suffix;
    $saved = $db->Execute(
        "INSERT INTO project_company (
            company_key, company_key_hash, company_code, company_slug, company_name, company_status
         ) VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
        [$companyKey, $companyHash, $companyCode, $companySlug, 'SC-02 Isolated Test Company']
    );
    sales_crm_assert($saved !== false, 'The isolated SC-02 company could not be created.');
    $company = [
        'company_key' => $companyKey,
        'company_key_hash' => $companyHash,
        'company_code' => $companyCode,
        'company_slug' => $companySlug,
        'company_name' => 'SC-02 Isolated Test Company',
    ];
    $admin = sales_crm_create_test_admin($db, $company);

    return ['company' => $company, 'admin' => $admin];
}

function sales_crm_test_code(string $prefix): string
{
    return strtoupper($prefix . '_' . substr(str_replace('-', '', bx_uuid()), 0, 12));
}

function sales_crm_with_post(array $post, callable $callback): mixed
{
    $previous = $_POST;
    $_POST = $post;
    try {
        return $callback();
    } finally {
        $_POST = $previous;
    }
}

function sales_crm_cleanup_lead(ADOConnection $db, string $companyKeyHash, string $leadKey, string $leadCode): void
{
    if ($leadKey !== '') {
        $db->Execute(
            "DELETE FROM builder_audit_log WHERE module = 'project_company_sales_lead' AND record_key = ?",
            [$leadKey]
        );
        $db->Execute(
            'DELETE FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ?',
            [$companyKeyHash, $leadKey]
        );
    }
    $db->Execute(
        'DELETE FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_code = ?',
        [$companyKeyHash, $leadCode]
    );
}

function sales_crm_create_test_admin(ADOConnection $db, array $company): array
{
    $adminKey = bx_uuid();
    $login = strtolower(sales_crm_test_code('sc02_admin'));
    $saved = $db->Execute(
        "INSERT INTO project_company_admin (
            admin_key, company_key, company_key_hash, admin_login, admin_password_hash,
            admin_name, admin_email, admin_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
        [
            $adminKey,
            (string) $company['company_key'],
            (string) $company['company_key_hash'],
            $login,
            bx_password_hash('sc02-test-only'),
            'SC-02 Access Test Admin',
            $login . '@example.test',
        ]
    );
    sales_crm_assert($saved !== false, 'The SC-02 access-test administrator could not be created.');

    return [
        'admin_key' => $adminKey,
        'admin_login' => $login,
        'admin_name' => 'SC-02 Access Test Admin',
        'admin_email' => $login . '@example.test',
        'admin_status' => 'ACTIVE',
        'company_key_hash' => (string) $company['company_key_hash'],
    ];
}

function sales_crm_cleanup_sc02(ADOConnection $db, string $companyKeyHash, array $recordKeys = [], string $testAdminKey = ''): void
{
    foreach ($recordKeys as $recordKey) {
        if ($recordKey !== '') {
            $db->Execute(
                "DELETE FROM builder_audit_log WHERE module IN ('project_company_sales_crm_settings', 'project_company_sales_crm_preference') AND record_key = ?",
                [$recordKey]
            );
        }
    }
    $db->Execute('DELETE FROM project_company_sales_crm_allowed_user WHERE company_key_hash = ?', [$companyKeyHash]);
    $db->Execute('DELETE FROM project_company_sales_crm_preference WHERE company_key_hash = ?', [$companyKeyHash]);
    $db->Execute('DELETE FROM project_company_sales_crm_settings WHERE company_key_hash = ?', [$companyKeyHash]);
    if ($testAdminKey !== '') {
        $db->Execute('DELETE FROM project_company_admin WHERE admin_key = ? AND company_key_hash = ?', [$testAdminKey, $companyKeyHash]);
    }
}

function sales_crm_cleanup_isolated_company(ADOConnection $db, string $companyKeyHash): void
{
    $db->Execute('DELETE FROM project_company_admin WHERE company_key_hash = ?', [$companyKeyHash]);
    $db->Execute('DELETE FROM project_company WHERE company_key_hash = ?', [$companyKeyHash]);
}

function sales_crm_cleanup_campaigns(ADOConnection $db, string $companyKeyHash, string $campaignKey = '', array $campaignCodes = []): void
{
    $campaignKeys = [];
    if ($campaignKey !== '') {
        $campaignKeys[] = $campaignKey;
    }
    foreach ($campaignCodes as $campaignCode) {
        $found = $db->GetOne(
            'SELECT campaign_key FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_code = ? LIMIT 1',
            [$companyKeyHash, $campaignCode]
        );
        if (is_string($found) && $found !== '') {
            $campaignKeys[] = $found;
        }
    }
    foreach (array_unique($campaignKeys) as $key) {
        $scheduleKeys = $db->GetCol(
            'SELECT campaign_email_schedule_key FROM project_company_sales_campaign_email_schedule WHERE company_key_hash = ? AND campaign_key = ?',
            [$companyKeyHash, $key]
        );
        foreach (is_array($scheduleKeys) ? $scheduleKeys : [] as $scheduleKey) {
            $db->Execute("DELETE FROM builder_audit_log WHERE module = 'project_company_sales_campaign_email_schedule' AND record_key = ?", [(string) $scheduleKey]);
        }
        $db->Execute('DELETE FROM project_company_sales_campaign_email_schedule WHERE company_key_hash = ? AND campaign_key = ?', [$companyKeyHash, $key]);
        $db->Execute("DELETE FROM builder_audit_log WHERE module = 'project_company_sales_campaign' AND record_key = ?", [$key]);
        $db->Execute('DELETE FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_key = ?', [$companyKeyHash, $key]);
    }
}
