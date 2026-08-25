<?php
declare(strict_types=1);

require __DIR__ . '/manufacturing-test-helper.php';

yovel_admin_manufacturing_schema();
yovel_admin_manufacturing_schema();

$tables = [
    'project_company_manufacturing_setting',
    'project_company_manufacturing_audit',
    'project_company_manufacturing_form',
    'project_company_manufacturing_form_version',
    'project_company_manufacturing_form_audit',
    'project_company_manufacturing_record_form_version',
    'project_company_manufacturing_operation',
    'project_company_manufacturing_bom',
    'project_company_manufacturing_bom_component',
    'project_company_manufacturing_bom_operation',
    'project_company_manufacturing_bom_secondary_item',
    'project_company_manufacturing_bom_version',
    'project_company_manufacturing_bom_update_batch',
    'project_company_manufacturing_bom_update_log',
    'project_company_manufacturing_sub_operation',
    'project_company_manufacturing_routing',
    'project_company_manufacturing_routing_operation',
    'project_company_manufacturing_workstation_type',
    'project_company_manufacturing_workstation_type_operation',
    'project_company_manufacturing_working_hour',
    'project_company_manufacturing_workstation_type_component',
    'project_company_manufacturing_plant_floor',
    'project_company_manufacturing_workstation',
    'project_company_manufacturing_workstation_cost',
    'project_company_manufacturing_workstation_component',
    'project_company_manufacturing_downtime',
];
foreach ($tables as $table) {
    manufacturing_test_assert(manufacturing_test_table_exists($table), 'Manufacturing schema table is missing: ' . $table);
}

$settingColumns = bx_db()->GetAll('SHOW COLUMNS FROM project_company_manufacturing_setting');
$columnTypes = [];
foreach ($settingColumns as $column) {
    $columnTypes[(string) $column['Field']] = strtolower((string) $column['Type']);
}
manufacturing_test_assert(($columnTypes['company_key'] ?? '') === 'varchar(1500)', 'Manufacturing company keys must be bounded opaque values, not UUID columns.');
manufacturing_test_assert(($columnTypes['company_key_hash'] ?? '') === 'char(64)', 'Manufacturing company hash must be 64 hex characters.');

$foreignReferences = bx_db()->GetAll(
    "SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'project_company_manufacturing_%' AND REFERENCED_TABLE_NAME IS NOT NULL"
);
foreach ($foreignReferences as $reference) {
    $table = (string) ($reference['REFERENCED_TABLE_NAME'] ?? '');
    manufacturing_test_assert(!preg_match('/^project_company_(inventory|buying|finance|accounting|general_ledger)/', $table), 'Manufacturing schema contains a foreign dependency-table key.');
}

$scope = manufacturing_test_create_scope('opaque-company-key');
try {
    [$companyKey, $hash, $adminKey] = yovel_admin_manufacturing_scope($scope['company'], $scope['admin']);
    manufacturing_test_assert($companyKey === $scope['company']['company_key'], 'Opaque company key changed during scope verification.');
    manufacturing_test_assert($hash === $scope['company']['company_key_hash'], 'Company hash changed during scope verification.');
    manufacturing_test_assert($adminKey === $scope['admin']['admin_key'], 'Active admin membership was not verified.');
    manufacturing_test_expect_error(
        fn () => yovel_admin_manufacturing_scope(array_replace($scope['company'], ['company_key_hash' => str_repeat('f', 64)]), $scope['admin']),
        'authorized active company administrator'
    );
    manufacturing_test_execute("UPDATE project_company_admin SET admin_status='INACTIVE' WHERE admin_key=?", [$scope['admin']['admin_key']]);
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_scope($scope['company'], $scope['admin']), 'authorized active company administrator');
    manufacturing_test_execute("UPDATE project_company_admin SET admin_status='ACTIVE' WHERE admin_key=?", [$scope['admin']['admin_key']]);
} finally {
    manufacturing_test_cleanup_scope($scope);
}

echo "Manufacturing schema tests passed.\n";
