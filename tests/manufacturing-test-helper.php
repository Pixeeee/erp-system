<?php
declare(strict_types=1);

$manufacturingTestRoot = dirname(__DIR__);
require_once $manufacturingTestRoot . '/app/foundation.php';
require_once $manufacturingTestRoot . '/company/admin/core/functions.php';
require_once $manufacturingTestRoot . '/company/admin/modules/shared/registry.php';
require_once $manufacturingTestRoot . '/company/admin/modules/shared/forms.php';
$manufacturingInventoryRoot = $manufacturingTestRoot . '/company/admin/modules/inventory-warehouse';
if (is_file($manufacturingInventoryRoot . '/ledger.php')) {
    require_once $manufacturingInventoryRoot . '/functions.php';
} else {
    foreach (['persistence.php', 'schema.php', 'forms.php', 'catalogue.php', 'warehouses.php'] as $manufacturingInventoryFile) {
        require_once $manufacturingInventoryRoot . '/' . $manufacturingInventoryFile;
    }
}
require_once $manufacturingTestRoot . '/company/admin/modules/buying-procurement/functions.php';
require_once $manufacturingTestRoot . '/company/admin/modules/accounting-finance/functions.php';
require_once $manufacturingTestRoot . '/company/admin/modules/accounting-finance/core.php';
require_once $manufacturingTestRoot . '/company/admin/modules/manufacturing/functions.php';

function manufacturing_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function manufacturing_test_expect_error(callable $operation, string $messagePart): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        manufacturing_test_assert(
            str_contains(strtolower($error->getMessage()), strtolower($messagePart)),
            'Unexpected error: ' . $error->getMessage()
        );
        return;
    }
    throw new RuntimeException('Expected an error containing: ' . $messagePart);
}

function manufacturing_test_execute(string $sql, array $params = []): void
{
    $result = bx_db()->Execute($sql, $params);
    if ($result === false) {
        throw new RuntimeException('Manufacturing test SQL failed: ' . bx_db()->ErrorMsg());
    }
}

function manufacturing_test_create_scope(string $label): array
{
    $suffix = strtolower(substr(str_replace('-', '', bx_uuid()), 0, 12));
    $companyKey = 'manufacturing-test:' . $label . ':' . $suffix;
    $companyHash = hash('sha256', $companyKey);
    $companyCode = strtoupper(substr('MFG_' . preg_replace('/[^a-z0-9]+/i', '_', $label) . '_' . $suffix, 0, 40));
    $companySlug = strtolower(substr('manufacturing-' . preg_replace('/[^a-z0-9]+/i', '-', $label) . '-' . $suffix, 0, 120));
    $adminKey = bx_uuid();

    manufacturing_test_execute(
        'INSERT INTO project_company (company_key, company_key_hash, company_code, company_slug, company_name, company_status) VALUES (?, ?, ?, ?, ?, ?)',
        [$companyKey, $companyHash, $companyCode, $companySlug, 'Manufacturing Test ' . $label, 'ACTIVE']
    );
    manufacturing_test_execute(
        'INSERT INTO project_company_admin (admin_key, company_key, company_key_hash, admin_login, admin_password_hash, admin_name, admin_email, admin_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$adminKey, $companyKey, $companyHash, 'mfg_' . $suffix, password_hash('test-only-password', PASSWORD_DEFAULT), 'Manufacturing Test Admin', $suffix . '@example.test', 'ACTIVE']
    );

    $_SESSION['builderx_csrf'] = 'manufacturing-test-csrf-' . $suffix;
    return [
        'company' => [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'company_code' => $companyCode,
            'company_slug' => $companySlug,
            'company_name' => 'Manufacturing Test ' . $label,
            'company_status' => 'ACTIVE',
        ],
        'admin' => [
            'admin_key' => $adminKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'admin_status' => 'ACTIVE',
        ],
        'csrf' => $_SESSION['builderx_csrf'],
    ];
}

function manufacturing_test_table_exists(string $table): bool
{
    return (string) bx_db()->GetOne('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]) === $table;
}

function manufacturing_test_cleanup_scope(array $scope): void
{
    $hash = (string) ($scope['company']['company_key_hash'] ?? '');
    foreach ([
        'project_company_manufacturing_bom_update_log',
        'project_company_manufacturing_bom_update_batch',
        'project_company_manufacturing_bom_version',
        'project_company_manufacturing_bom_secondary_item',
        'project_company_manufacturing_bom_operation',
        'project_company_manufacturing_bom_component',
        'project_company_manufacturing_bom',
        'project_company_manufacturing_downtime',
        'project_company_manufacturing_workstation_component',
        'project_company_manufacturing_workstation_cost',
        'project_company_manufacturing_workstation',
        'project_company_manufacturing_plant_floor',
        'project_company_manufacturing_workstation_type_component',
        'project_company_manufacturing_working_hour',
        'project_company_manufacturing_workstation_type_operation',
        'project_company_manufacturing_workstation_type',
        'project_company_manufacturing_routing_operation',
        'project_company_manufacturing_routing',
        'project_company_manufacturing_sub_operation',
        'project_company_manufacturing_operation',
        'project_company_manufacturing_record_form_version',
        'project_company_manufacturing_form_audit',
        'project_company_manufacturing_form_version',
        'project_company_manufacturing_form',
        'project_company_manufacturing_setting',
        'project_company_manufacturing_audit',
    ] as $table) {
        if (manufacturing_test_table_exists($table)) {
            manufacturing_test_execute("DELETE FROM `{$table}` WHERE company_key_hash = ?", [$hash]);
        }
    }
    manufacturing_test_execute('DELETE FROM project_company_admin WHERE company_key_hash = ?', [$hash]);
    manufacturing_test_execute('DELETE FROM project_company WHERE company_key_hash = ?', [$hash]);
    unset($GLOBALS['yovel_admin_manufacturing_fault']);
}

function manufacturing_test_render(array $scope, string $section, array $formState = []): string
{
    global $manufacturingTestRoot;
    $activeModuleSections = yovel_admin_manufacturing_sections();
    $activeModuleSection = $section;
    $activeModuleData = yovel_admin_manufacturing_data($scope['company'], $scope['admin'], $section);
    $activeModuleFormState = $formState;
    $companyName = (string) $scope['company']['company_name'];
    ob_start();
    require $manufacturingTestRoot . '/company/admin/modules/manufacturing/views/workspace.php';
    require $manufacturingTestRoot . '/company/admin/views/partials/confirm-dialog.php';
    return (string) ob_get_clean();
}
