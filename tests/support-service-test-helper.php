<?php
declare(strict_types=1);

$supportTestRoot = dirname(__DIR__);
require_once $supportTestRoot . '/app/foundation.php';
require_once $supportTestRoot . '/company/admin/core/functions.php';
require_once $supportTestRoot . '/company/admin/modules/shared/registry.php';
require_once $supportTestRoot . '/company/admin/modules/shared/forms.php';

$supportFunctionsPath = $supportTestRoot . '/company/admin/modules/support-service/functions.php';
if (!is_file($supportFunctionsPath)) {
    throw new RuntimeException('Support/Service function service is missing.');
}
require_once $supportFunctionsPath;

function support_service_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function support_service_test_code(string $prefix): string
{
    return strtoupper($prefix . '_' . substr(str_replace('-', '', bx_uuid()), 0, 12));
}

function support_service_create_company(ADOConnection $db, string $label): array
{
    $uuid = bx_uuid();
    $companyKey = 'support-company|' . strtolower($label) . '|' . $uuid;
    $companyHash = hash('sha256', $companyKey);
    $suffix = strtolower(substr(str_replace('-', '', $uuid), 0, 12));
    $companyCode = strtoupper('SS' . substr($suffix, 0, 8));
    $companySlug = 'support-' . $suffix;
    $companyName = 'Support Test ' . $label;
    $savedCompany = $db->Execute(
        "INSERT INTO project_company (
            company_key, company_key_hash, company_code, company_slug, company_name, company_status
         ) VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
        [$companyKey, $companyHash, $companyCode, $companySlug, $companyName]
    );
    support_service_assert($savedCompany !== false, 'Support test company could not be created.');

    $adminKey = bx_uuid();
    $adminLogin = 'support_' . $suffix;
    $savedAdmin = $db->Execute(
        "INSERT INTO project_company_admin (
            admin_key, company_key, company_key_hash, admin_login, admin_password_hash,
            admin_name, admin_email, admin_status
         ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
        [
            $adminKey,
            $companyKey,
            $companyHash,
            $adminLogin,
            bx_password_hash('support-test-only'),
            'Support Test Admin',
            $adminLogin . '@example.test',
        ]
    );
    support_service_assert($savedAdmin !== false, 'Support test administrator could not be created.');

    return [
        'company' => [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'company_code' => $companyCode,
            'company_slug' => $companySlug,
            'company_name' => $companyName,
        ],
        'admin' => [
            'admin_key' => $adminKey,
            'company_key_hash' => $companyHash,
            'admin_login' => $adminLogin,
            'admin_status' => 'ACTIVE',
        ],
    ];
}

function support_service_table_exists(ADOConnection $db, string $table): bool
{
    return (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    ) === 1;
}

function support_service_cleanup_company(ADOConnection $db, array $fixture): void
{
    $company = $fixture['company'] ?? [];
    $admin = $fixture['admin'] ?? [];
    $companyHash = (string) ($company['company_key_hash'] ?? '');
    if ($companyHash === '') {
        return;
    }

    $tables = [
        'project_company_support_call_log_link',
        'project_company_support_call_log',
        'project_company_support_voice_call_setting',
        'project_company_support_incoming_call_schedule',
        'project_company_support_incoming_call_setting',
        'project_company_support_telephony_call_type',
        'project_company_support_warranty_claim',
        'project_company_support_sla_fulfilled_status',
        'project_company_support_sla_pause_status',
        'project_company_support_sla_priority',
        'project_company_support_sla_service_day',
        'project_company_support_sla',
        'project_company_support_issue_event',
        'project_company_support_issue',
        'project_company_support_issue_type',
        'project_company_support_issue_priority',
        'project_company_support_search_source',
        'project_company_support_setting',
    ];
    foreach ($tables as $table) {
        if (support_service_table_exists($db, $table)) {
            $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$companyHash]);
        }
    }
    $db->Execute(
        "DELETE FROM builder_audit_log
         WHERE module IN (
             'project_company_support_setting', 'project_company_support_search_source',
             'project_company_support_issue_priority', 'project_company_support_issue_type',
             'project_company_support_issue', 'project_company_support_sla'
         )
           AND new_values LIKE ?",
        ['%' . $companyHash . '%']
    );
    $db->Execute(
        'DELETE FROM project_company_admin WHERE admin_key = ? AND company_key_hash = ?',
        [(string) ($admin['admin_key'] ?? ''), $companyHash]
    );
    $db->Execute(
        'DELETE FROM project_company WHERE company_key = ? AND company_key_hash = ?',
        [(string) ($company['company_key'] ?? ''), $companyHash]
    );
}

function support_service_expect_exception(callable $callback, string $class, string $messageFragment): void
{
    $caught = null;
    try {
        $callback();
    } catch (Throwable $error) {
        $caught = $error;
    }
    support_service_assert($caught instanceof $class, 'Expected ' . $class . ' but received ' . ($caught ? $caught::class : 'no exception') . '.');
    support_service_assert(
        $messageFragment === '' || str_contains($caught->getMessage(), $messageFragment),
        'Exception message did not contain: ' . $messageFragment . '; received: ' . $caught->getMessage()
    );
}
