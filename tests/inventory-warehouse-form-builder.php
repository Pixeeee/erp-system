<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/inventory-warehouse/functions.php';

function inventory_builder_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = bx_db();
yovel_admin_inventory_warehouse_schema();

$requiredTables = [
    'project_company_inventory_form_schema',
    'project_company_inventory_form_schema_version',
    'project_company_inventory_form_schema_audit',
];
foreach ($requiredTables as $table) {
    $exists = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    inventory_builder_assert($exists === 1, 'Missing Inventory Form Builder table: ' . $table);
}

$requiredIndexes = [
    'project_company_inventory_form_schema' => [
        'uq_project_company_inventory_form_record',
        'idx_project_company_inventory_form_status',
    ],
    'project_company_inventory_form_schema_version' => [
        'uq_project_company_inventory_form_version',
        'uq_project_company_inventory_form_checksum',
    ],
    'project_company_inventory_form_schema_audit' => [
        'idx_project_company_inventory_form_audit_record',
    ],
];
foreach ($requiredIndexes as $table => $indexes) {
    $actual = $db->GetCol(
        'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    foreach ($indexes as $index) {
        inventory_builder_assert(is_array($actual) && in_array($index, $actual, true), 'Missing Inventory Form Builder index: ' . $index);
    }
}

$scope = $db->GetRow(
    "SELECT company_record.company_key, company_record.company_key_hash, company_record.company_name,
            admin_record.admin_key, admin_record.admin_status
     FROM project_company company_record
     INNER JOIN project_company_admin admin_record
       ON admin_record.company_key_hash = company_record.company_key_hash
      AND admin_record.admin_status = 'ACTIVE'
     WHERE company_record.company_status = 'ACTIVE'
     ORDER BY company_record.x_id, admin_record.x_id
     LIMIT 1"
);
inventory_builder_assert(is_array($scope) && $scope !== [], 'An active company/admin fixture is required.');
$company = [
    'company_key' => (string) $scope['company_key'],
    'company_key_hash' => (string) $scope['company_key_hash'],
    'company_name' => (string) $scope['company_name'],
];
$admin = [
    'admin_key' => (string) $scope['admin_key'],
    'admin_status' => (string) $scope['admin_status'],
];

$originalRows = [];
foreach (array_reverse($requiredTables) as $table) {
    $originalRows[$table] = $db->GetAll("SELECT * FROM {$table} WHERE company_key_hash = ? ORDER BY x_id", [$company['company_key_hash']]);
}
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id), 0) FROM builder_audit_log');
register_shutdown_function(static function () use ($db, $requiredTables, $originalRows, $company, $auditFloor): void {
    foreach ($requiredTables as $table) {
        $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$company['company_key_hash']]);
        foreach ($originalRows[$table] ?? [] as $row) {
            $columns = array_keys($row);
            $quoted = implode(',', array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns));
            $marks = implode(',', array_fill(0, count($columns), '?'));
            $db->Execute("INSERT INTO {$table} ({$quoted}) VALUES ({$marks})", array_values($row));
        }
    }
    $db->Execute("DELETE FROM builder_audit_log WHERE x_id > ? AND module = 'project_company_inventory_form_schema'", [$auditFloor]);
});

$resolvedScope = yovel_admin_inventory_scope($company, $admin);
inventory_builder_assert($resolvedScope === [$company['company_key'], $company['company_key_hash'], $admin['admin_key']], 'Authorized Inventory scope did not resolve exact keys.');

$foreignCompany = $company;
$foreignCompany['company_key_hash'] = str_repeat('0', 64);
try {
    yovel_admin_inventory_scope($foreignCompany, $admin);
    throw new RuntimeException('Cross-company admin scope must be rejected.');
} catch (RuntimeException $error) {
    inventory_builder_assert(str_contains($error->getMessage(), 'authorized'), 'Cross-company rejection must be safe and explicit.');
}

$defaults = yovel_admin_inventory_default_form_schemas();
$expectedRecordTypes = ['ITEM', 'WAREHOUSE', 'STOCK_ENTRY', 'STOCK_RECONCILIATION', 'STOCK_RESERVATION', 'BATCH', 'SERIAL', 'SHIPMENT', 'QUALITY_INSPECTION'];
inventory_builder_assert(array_keys($defaults) === $expectedRecordTypes, 'Inventory default Form Builder record types are incomplete.');
$adapter = yovel_admin_inventory_form_adapter();
inventory_builder_assert(array_values($adapter['target_record_types'] ?? []) === $expectedRecordTypes, 'Inventory adapter target types are incomplete.');
inventory_builder_assert(($adapter['row_column_layout']['max_columns'] ?? 0) === 3, 'Inventory adapter must support up to three columns.');
foreach ($expectedRecordTypes as $recordType) {
    $protected = $adapter['protected_fields'][$recordType] ?? [];
    inventory_builder_assert($protected !== [], 'Protected fields are missing for ' . $recordType . '.');
    foreach ($protected as $fieldKey) {
        inventory_builder_assert(preg_match('/^[a-z][a-z0-9_]{1,79}$/', (string) $fieldKey) === 1, 'Protected field key is not stable: ' . $fieldKey);
    }
}

$schemaRunsBefore = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_inventory_form_schema WHERE company_key_hash = ?", [$company['company_key_hash']]);
yovel_admin_inventory_warehouse_schema();
yovel_admin_inventory_warehouse_schema();
$schemaRunsAfter = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_inventory_form_schema WHERE company_key_hash = ?", [$company['company_key_hash']]);
inventory_builder_assert($schemaRunsBefore === $schemaRunsAfter, 'Idempotent schema setup must not create Form Builder records.');

$itemDefault = $defaults['ITEM'];
$customSchema = $itemDefault;
$customSchema['fields'][] = [
    'key' => 'storage_temperature',
    'label' => 'Storage temperature',
    'type' => 'NUMBER',
    'section' => 'Inventory',
    'required' => false,
    'visible' => true,
    'system' => false,
    'column' => 2,
    'order' => 900,
    'precision' => 3,
    'options' => [],
];
$savedV1 = yovel_admin_save_inventory_form_schema($company, $admin, 'ITEM', $customSchema);
inventory_builder_assert((int) ($savedV1['schema_version'] ?? 0) === 1, 'First Inventory form save must create version 1.');
inventory_builder_assert((string) ($savedV1['schema_checksum'] ?? '') === hash('sha256', (string) $savedV1['schema_json']), 'Saved schema checksum does not match canonical JSON.');
$savedV1Schema = json_decode((string) $savedV1['schema_json'], true, 512, JSON_THROW_ON_ERROR);
$savedV1FieldsByKey = array_column($savedV1Schema['fields'], null, 'key');
inventory_builder_assert(($savedV1FieldsByKey['storage_temperature']['section'] ?? '') === 'inventory', 'Form section keys must normalize uppercase input without dropping characters.');

$readV1 = yovel_admin_inventory_form_schema($company, 'ITEM');
inventory_builder_assert(($readV1['persisted'] ?? false) === true, 'Saved Inventory form was not rehydrated from the database.');
inventory_builder_assert((int) ($readV1['version'] ?? 0) === 1, 'Rehydrated Inventory form version is incorrect.');
inventory_builder_assert(in_array('storage_temperature', array_column($readV1['fields'], 'key'), true), 'Custom field was not rehydrated.');

$withoutProtected = $customSchema;
$withoutProtected['fields'] = array_values(array_filter(
    $withoutProtected['fields'],
    static fn (array $field): bool => !in_array((string) ($field['key'] ?? ''), $adapter['protected_fields']['ITEM'], true)
));
$withoutProtected['fields'][0]['label'] = 'Storage temperature updated';
$savedV2 = yovel_admin_save_inventory_form_schema($company, $admin, 'ITEM', $withoutProtected);
inventory_builder_assert((int) ($savedV2['schema_version'] ?? 0) === 2, 'Changed Inventory form must create version 2.');
$v2Schema = json_decode((string) $savedV2['schema_json'], true, 512, JSON_THROW_ON_ERROR);
foreach ($adapter['protected_fields']['ITEM'] as $fieldKey) {
    inventory_builder_assert(in_array($fieldKey, array_column($v2Schema['fields'], 'key'), true), 'Protected field was removed: ' . $fieldKey);
}

$sameV2 = yovel_admin_save_inventory_form_schema($company, $admin, 'ITEM', $v2Schema);
inventory_builder_assert((int) ($sameV2['schema_version'] ?? 0) === 2, 'Unchanged Inventory form save must be idempotent.');
$versionCount = (int) $db->GetOne(
    'SELECT COUNT(*) FROM project_company_inventory_form_schema_version WHERE company_key_hash = ? AND record_type = ?',
    [$company['company_key_hash'], 'ITEM']
);
inventory_builder_assert($versionCount === 2, 'Idempotent save created a duplicate form version.');

$rollbackKey = bx_uuid();
$callbackRuns = 0;
try {
    yovel_admin_inventory_in_transaction(static function (ADOConnection $transactionDb) use ($company, $admin, $rollbackKey, &$callbackRuns): void {
        $callbackRuns++;
        $saved = $transactionDb->Execute(
            "INSERT INTO project_company_inventory_form_schema_audit (
                form_schema_audit_key, company_key, company_key_hash, form_schema_key,
                form_version_key, record_type, audit_action, previous_checksum,
                next_checksum, created_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, 'ITEM', 'ROLLBACK_TEST', NULL, ?, ?)",
            [$rollbackKey, $company['company_key'], $company['company_key_hash'], bx_uuid(), bx_uuid(), str_repeat('a', 64), $admin['admin_key']]
        );
        inventory_builder_assert($saved !== false, 'Rollback fixture insert failed.');
        throw new RuntimeException('force rollback');
    });
    throw new RuntimeException('Forced transaction failure did not propagate.');
} catch (RuntimeException $error) {
    inventory_builder_assert($error->getMessage() === 'force rollback', 'Unexpected rollback error was returned.');
}
inventory_builder_assert($callbackRuns === 1, 'Transaction callback did not execute exactly once.');
inventory_builder_assert(
    (int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_form_schema_audit WHERE form_schema_audit_key = ?', [$rollbackKey]) === 0,
    'Failed Inventory transaction left a persisted audit row.'
);

$badSchema = $itemDefault;
$badSchema['fields'][] = [
    'key' => 'bad_field',
    'label' => str_repeat('x', 181),
    'type' => 'SHORT_TEXT',
];
try {
    yovel_admin_save_inventory_form_schema($company, $admin, 'ITEM', $badSchema);
    throw new RuntimeException('Invalid schema must fail server validation.');
} catch (InvalidArgumentException) {
}
$postResult = yovel_admin_inventory_warehouse_handle_post($company, $admin, 'save_inventory_form_schema', [
    'section' => 'form-builder',
    'record_type' => 'ITEM',
    'schema_json' => (string) $savedV2['schema_json'],
]);
inventory_builder_assert($postResult === [
    'message' => 'Inventory form version saved.',
    'section' => 'form-builder',
    'query' => ['record_type' => 'ITEM'],
], 'Inventory shared POST result is not stable.');

echo "Inventory/Warehouse IW-01 Form Builder checks passed.\n";
