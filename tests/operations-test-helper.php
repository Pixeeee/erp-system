<?php
declare(strict_types=1);

$operationsTestRoot = dirname(__DIR__);

require_once $operationsTestRoot . '/app/foundation.php';
require_once $operationsTestRoot . '/company/admin/core/functions.php';
require_once $operationsTestRoot . '/company/admin/modules/shared/registry.php';
require_once $operationsTestRoot . '/company/admin/modules/shared/forms.php';

$operationsFunctionFile = $operationsTestRoot . '/company/admin/modules/operations/functions.php';
if (!is_file($operationsFunctionFile)) {
    throw new RuntimeException('Operations module foundation is missing.');
}
require_once $operationsFunctionFile;

function operations_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function operations_test_company(string $label = 'Operations Test Company'): array
{
    $companyKey = bx_uuid();
    return [
        'company_key' => $companyKey,
        'company_key_hash' => hash('sha256', $companyKey),
        'company_name' => $label,
        'company_code' => 'OPS',
        'company_slug' => 'operations-test-' . substr($companyKey, 0, 8),
    ];
}

function operations_test_admin(): array
{
    return [
        'admin_key' => bx_uuid(),
        'admin_name' => 'Operations Test Admin',
        'admin_login' => 'operations-test-admin',
        'admin_status' => 'ACTIVE',
    ];
}

function operations_test_expect_exception(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        operations_test_assert(
            str_contains(strtolower($error->getMessage()), strtolower($contains)),
            'Unexpected exception: ' . $error->getMessage()
        );
        return;
    }

    throw new RuntimeException('Expected exception containing: ' . $contains);
}

function operations_test_cleanup(array $company): void
{
    $db = bx_db();
    $hash = (string) $company['company_key_hash'];
    $tables = [
        'project_company_operations_authorization_policy',
        'project_company_operations_deletion_target',
        'project_company_operations_deletion_item',
        'project_company_operations_deletion_request',
        'project_company_operations_bulk_log_detail',
        'project_company_operations_bulk_log',
        'project_company_operations_job_attempt',
        'project_company_operations_worker',
        'project_company_operations_schedule',
        'project_company_operations_sync_conflict',
        'project_company_operations_system_alert',
        'project_company_operations_release_check',
        'project_company_operations_job',
        'project_company_operations_form_submission',
        'project_company_operations_builder_form_version',
        'project_company_operations_builder_form',
    ];

    $available = array_fill_keys(array_map('strtolower', $db->MetaTables('TABLES') ?: []), true);
    foreach ($tables as $table) {
        if (isset($available[strtolower($table)])) {
            $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$hash]);
        }
    }

    $db->Execute(
        "DELETE FROM builder_audit_log WHERE module LIKE 'project_company_operations_%' AND new_values LIKE ?",
        ['%' . $hash . '%']
    );
}

function operations_test_register_cleanup(array $company): void
{
    register_shutdown_function(static function () use ($company): void {
        operations_test_cleanup($company);
    });
}
