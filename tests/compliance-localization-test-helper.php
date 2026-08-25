<?php
declare(strict_types=1);

$complianceTestRoot = dirname(__DIR__);

require_once $complianceTestRoot . '/app/foundation.php';
require_once $complianceTestRoot . '/company/admin/core/functions.php';
require_once $complianceTestRoot . '/company/admin/modules/shared/registry.php';
require_once $complianceTestRoot . '/company/admin/modules/shared/forms.php';

$complianceFunctionFile = $complianceTestRoot . '/company/admin/modules/compliance-localization/functions.php';
if (!is_file($complianceFunctionFile)) {
    throw new RuntimeException('Compliance / Localization module foundation is missing.');
}
require_once $complianceFunctionFile;

function compliance_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function compliance_test_expect_exception(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        compliance_test_assert(
            str_contains(strtolower($error->getMessage()), strtolower($contains)),
            'Unexpected exception: ' . $error->getMessage()
        );
        return;
    }

    throw new RuntimeException('Expected exception containing: ' . $contains);
}

function compliance_test_scope(): array
{
    $row = bx_db()->GetRow(
        "SELECT company_record.company_key, company_record.company_key_hash,
                company_record.company_name, company_record.company_code,
                admin_record.admin_key, admin_record.admin_status
         FROM project_company company_record
         INNER JOIN project_company_admin admin_record
           ON admin_record.company_key_hash = company_record.company_key_hash
          AND admin_record.admin_status = 'ACTIVE'
         WHERE company_record.company_status = 'ACTIVE'
         ORDER BY company_record.x_id, admin_record.x_id
         LIMIT 1"
    );
    if (!is_array($row) || $row === []) {
        throw new RuntimeException('An active company administrator fixture is required.');
    }

    return [
        [
            'company_key' => (string) $row['company_key'],
            'company_key_hash' => (string) $row['company_key_hash'],
            'company_name' => (string) $row['company_name'],
            'company_code' => (string) $row['company_code'],
        ],
        [
            'admin_key' => (string) $row['admin_key'],
            'admin_status' => (string) $row['admin_status'],
            'company_key_hash' => (string) $row['company_key_hash'],
        ],
    ];
}

function compliance_test_create_company(string $label, int $adminCount = 2): array
{
    $db = bx_db();
    $suffix = strtolower(substr(str_replace('-', '', bx_uuid()), 0, 12));
    $companyKey = 'compliance:test/' . $suffix;
    $companyHash = hash('sha256', 'membership:' . $companyKey);
    $company = [
        'company_key' => $companyKey,
        'company_key_hash' => $companyHash,
        'company_name' => $label,
        'company_code' => 'CL' . strtoupper(substr($suffix, 0, 8)),
    ];
    $db->Execute(
        "INSERT INTO project_company (company_key, company_key_hash, company_code, company_slug, company_name, company_status)
         VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
        [$companyKey, $companyHash, $company['company_code'], 'compliance-' . $suffix, $label]
    );

    $admins = [];
    for ($index = 1; $index <= $adminCount; $index++) {
        $adminKey = bx_uuid();
        $admin = [
            'admin_key' => $adminKey,
            'admin_status' => 'ACTIVE',
            'company_key_hash' => $companyHash,
        ];
        $db->Execute(
            "INSERT INTO project_company_admin (
                admin_key, company_key, company_key_hash, admin_login, admin_password_hash,
                admin_name, admin_email, admin_status
             ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
            [
                $adminKey,
                $companyKey,
                $companyHash,
                'compliance_' . $suffix . '_' . $index,
                password_hash('Compliance-Test-Only-12345', PASSWORD_DEFAULT),
                $label . ' Administrator ' . $index,
                'compliance-' . $suffix . '-' . $index . '@example.test',
            ]
        );
        $admins[] = $admin;
    }

    return ['company' => $company, 'admins' => $admins];
}

function compliance_test_cleanup_company(array $fixture): void
{
    $company = is_array($fixture['company'] ?? null) ? $fixture['company'] : [];
    $companyHash = (string) ($company['company_key_hash'] ?? '');
    if ($companyHash === '') {
        return;
    }
    $db = bx_db();
    $paths = $db->GetCol(
        'SELECT storage_path FROM project_company_compliance_evidence WHERE company_key_hash = ?',
        [$companyHash]
    ) ?: [];
    $storageRoot = realpath(dirname(__DIR__) . '/company/admin/modules/compliance-localization/storage/evidence');
    foreach ($paths as $path) {
        $resolved = is_file((string) $path) ? realpath((string) $path) : false;
        if ($resolved !== false && $storageRoot !== false && str_starts_with($resolved, $storageRoot . DIRECTORY_SEPARATOR)) {
            unlink($resolved);
        }
    }

    foreach ([
        'project_company_compliance_retention_hold',
        'project_company_compliance_approval',
        'project_company_compliance_tax_account_map',
        'project_company_compliance_evidence',
        'project_company_compliance_rule_set',
    ] as $table) {
        $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$companyHash]);
    }
    $db->Execute(
        "DELETE FROM builder_audit_log WHERE module LIKE 'project_company_compliance_%' AND new_values LIKE ?",
        ['%' . $companyHash . '%']
    );
    $db->Execute('DELETE FROM project_company_admin WHERE company_key_hash = ?', [$companyHash]);
    $db->Execute(
        'DELETE FROM project_company WHERE company_key = ? AND company_key_hash = ?',
        [(string) ($company['company_key'] ?? ''), $companyHash]
    );
}

function compliance_test_register_cleanup(array ...$fixtures): void
{
    register_shutdown_function(static function () use ($fixtures): void {
        foreach ($fixtures as $fixture) {
            compliance_test_cleanup_company($fixture);
        }
    });
}

function compliance_test_finance_validator(): callable
{
    return static function (array $company, string $accountKey): array {
        return [
            'ok' => true,
            'company_key_hash' => (string) $company['company_key_hash'],
            'record' => [
                'account_key' => $accountKey,
                'account_code' => 'TEST-' . strtoupper(substr(str_replace('-', '', $accountKey), 0, 8)),
                'account_name' => 'Injected Finance Account',
                'account_status' => 'ACTIVE',
                'is_group' => false,
            ],
            'errors' => [],
        ];
    };
}

function compliance_test_upload(string $content, string $name = 'evidence.txt', string $type = 'text/plain'): array
{
    $path = tempnam(sys_get_temp_dir(), 'compliance-evidence-');
    if ($path === false || file_put_contents($path, $content) === false) {
        throw new RuntimeException('Compliance upload fixture could not be created.');
    }
    return [
        'name' => $name,
        'type' => $type,
        'tmp_name' => $path,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($path),
    ];
}
