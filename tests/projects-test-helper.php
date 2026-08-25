<?php
declare(strict_types=1);

$projectsTestRoot = dirname(__DIR__);
require_once $projectsTestRoot . '/app/foundation.php';
require_once $projectsTestRoot . '/company/admin/core/functions.php';
require_once $projectsTestRoot . '/company/admin/modules/shared/registry.php';
require_once $projectsTestRoot . '/company/admin/modules/shared/forms.php';
require_once $projectsTestRoot . '/company/admin/modules/projects/functions.php';

function projects_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function projects_expect_exception(callable $operation, string $messagePart): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        projects_assert(
            str_contains(strtolower($error->getMessage()), strtolower($messagePart)),
            'Unexpected exception: ' . $error->getMessage()
        );
        return;
    }

    throw new RuntimeException('Expected an exception containing: ' . $messagePart);
}

function projects_table_names(): array
{
    return [
        'project_company_project_settings',
        'project_company_project_type',
        'project_company_project_task_type',
        'project_company_project_activity_type',
        'project_company_project_activity_cost',
        'project_company_project_record',
        'project_company_project_template',
        'project_company_project_template_task',
        'project_company_project_task',
        'project_company_project_task_dependency',
        'project_company_project_user',
        'project_company_project_update',
        'project_company_project_form_schema',
    ];
}

function projects_test_scope(): array
{
    $db = bx_db();
    $suffix = strtolower(substr(str_replace('-', '', bx_uuid()), 0, 18));
    $companyKey = 'projects-test:' . $suffix . '/opaque';
    $companyKeyHash = hash('sha256', $companyKey);
    $companyCode = strtoupper('PJ' . substr($suffix, 0, 8));
    $companySlug = 'projects-test-' . $suffix;
    $adminKey = bx_uuid();
    $adminLogin = 'projects_' . substr($suffix, 0, 12);

    $companySaved = $db->Execute(
        "INSERT INTO project_company (
            company_key, company_key_hash, company_code, company_slug, company_name, company_status
         ) VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
        [$companyKey, $companyKeyHash, $companyCode, $companySlug, 'Projects Test Company']
    );
    projects_assert($companySaved !== false, 'Projects test company could not be created.');

    $adminSaved = $db->Execute(
        "INSERT INTO project_company_admin (
            admin_key, company_key, company_key_hash, admin_login, admin_password_hash,
            admin_name, admin_email, admin_status
         ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
        [
            $adminKey,
            $companyKey,
            $companyKeyHash,
            $adminLogin,
            bx_password_hash('projects-test-only'),
            'Projects Test Admin',
            $adminLogin . '@example.test',
        ]
    );
    projects_assert($adminSaved !== false, 'Projects test administrator could not be created.');

    return [
        'company' => [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'company_name' => 'Projects Test Company',
            'company_slug' => $companySlug,
        ],
        'admin' => [
            'admin_key' => $adminKey,
            'admin_status' => 'ACTIVE',
        ],
    ];
}

function projects_cleanup(array $company, array $admin): void
{
    $db = bx_db();
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    foreach (array_reverse(projects_table_names()) as $table) {
        $exists = (int) $db->GetOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [BUILDERX_DB_NAME, $table]
        );
        if ($exists === 1) {
            $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$companyKeyHash]);
        }
    }
    $db->Execute(
        "DELETE FROM builder_audit_log
         WHERE module LIKE 'project_company_project_%'
           AND JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.company_key_hash')) = ?",
        [$companyKeyHash]
    );
    $db->Execute(
        'DELETE FROM project_company_admin WHERE admin_key = ? AND company_key_hash = ?',
        [(string) ($admin['admin_key'] ?? ''), $companyKeyHash]
    );
    $db->Execute(
        'DELETE FROM project_company WHERE company_key = ? AND company_key_hash = ?',
        [(string) ($company['company_key'] ?? ''), $companyKeyHash]
    );
}

function projects_with_session_token(callable $operation): mixed
{
    $previous = $_SESSION['builderx_csrf'] ?? null;
    $_SESSION['builderx_csrf'] = 'projects-test-csrf-token';
    try {
        return $operation('projects-test-csrf-token');
    } finally {
        if ($previous === null) {
            unset($_SESSION['builderx_csrf']);
        } else {
            $_SESSION['builderx_csrf'] = $previous;
        }
    }
}

