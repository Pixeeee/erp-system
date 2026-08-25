<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/inventory-warehouse/functions.php';

function inventory_warehouse_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function inventory_warehouse_reject(callable $operation, string $needle, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        inventory_warehouse_assert(str_contains(strtolower($error->getMessage()), strtolower($needle)), $message . ' Unexpected error: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException($message);
}

$db = bx_db();
yovel_admin_inventory_catalogue_schema();
yovel_admin_inventory_warehouse_control_schema();

$tables = [
    'project_company_inventory_stock_setting',
    'project_company_inventory_warehouse_type',
    'project_company_inventory_warehouse',
    'project_company_inventory_dimension',
    'project_company_inventory_dimension_value',
    'project_company_inventory_bin',
    'project_company_inventory_bin_source',
    'project_company_inventory_putaway_rule',
    'project_company_inventory_reorder_rule',
];
foreach ($tables as $table) {
    inventory_warehouse_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
        [BUILDERX_DB_NAME, $table]
    ) === 1, 'Missing IW-03 table: ' . $table);
}
$indexes = $db->GetCol(
    "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('project_company_inventory_warehouse','project_company_inventory_bin','project_company_inventory_dimension_value','project_company_inventory_reorder_rule')",
    [BUILDERX_DB_NAME]
);
foreach (['uq_inventory_warehouse_code', 'uq_inventory_bin_tuple', 'uq_inventory_dimension_value', 'uq_inventory_reorder_rule'] as $index) {
    inventory_warehouse_assert(is_array($indexes) && in_array($index, $indexes, true), 'Missing IW-03 uniqueness index: ' . $index);
}

$scope = $db->GetRow(
    "SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key,a.admin_status
     FROM project_company c JOIN project_company_admin a
       ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE'
     WHERE c.company_status='ACTIVE' ORDER BY c.x_id,a.x_id LIMIT 1"
);
inventory_warehouse_assert(is_array($scope) && $scope !== [], 'An active company/admin fixture is required.');
$company = ['company_key' => (string) $scope['company_key'], 'company_key_hash' => (string) $scope['company_key_hash'], 'company_name' => (string) $scope['company_name']];
$admin = ['admin_key' => (string) $scope['admin_key'], 'admin_status' => (string) $scope['admin_status']];

$original = [];
foreach (array_reverse($tables) as $table) {
    $original[$table] = $db->GetAll("SELECT * FROM {$table} WHERE company_key_hash=? ORDER BY x_id", [$company['company_key_hash']]);
}
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id),0) FROM builder_audit_log');
register_shutdown_function(static function () use ($db, $tables, $original, $company, $auditFloor): void {
    unset($GLOBALS['yovel_admin_inventory_warehouse_fault']);
    foreach (array_reverse($tables) as $table) {
        $db->Execute("DELETE FROM {$table} WHERE company_key_hash=?", [$company['company_key_hash']]);
    }
    foreach ($tables as $table) {
        foreach ($original[$table] ?? [] as $row) {
            $columns = array_keys($row);
            $quoted = implode(',', array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns));
            $marks = implode(',', array_fill(0, count($columns), '?'));
            $db->Execute("INSERT INTO {$table} ({$quoted}) VALUES ({$marks})", array_values($row));
        }
    }
    $db->Execute("DELETE FROM builder_audit_log WHERE x_id>? AND module LIKE 'project_company_inventory_%'", [$auditFloor]);
});
foreach (array_reverse($tables) as $table) {
    $db->Execute("DELETE FROM {$table} WHERE company_key_hash=?", [$company['company_key_hash']]);
}

$prefix = 'IW03' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 8));
$item = yovel_admin_save_inventory_item($company, $admin, [
    'item_code' => $prefix . '-ITEM', 'item_name' => 'IW-03 bin item', 'item_status' => 'ACTIVE',
    'item_kind' => 'STOCK', 'stock_uom_code' => 'EA',
    'uoms' => [['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '1']],
]);
register_shutdown_function(static function () use ($db, $company, $item, $prefix): void {
    foreach ([
        'project_company_inventory_item_reorder', 'project_company_inventory_item_website_spec',
        'project_company_inventory_item_lead_time', 'project_company_inventory_item_default',
        'project_company_inventory_item_tax', 'project_company_inventory_item_party_detail',
        'project_company_inventory_item_alternative', 'project_company_inventory_item_manufacturer',
        'project_company_inventory_item_variant_attribute',
        'project_company_inventory_item_price', 'project_company_inventory_item_barcode', 'project_company_inventory_item_uom',
    ] as $table) {
        $db->Execute("DELETE FROM {$table} WHERE company_key_hash=? AND item_key=?", [$company['company_key_hash'], $item['item_key']]);
    }
    $db->Execute('DELETE FROM project_company_inventory_variant_field WHERE company_key_hash=? AND template_item_key=?', [$company['company_key_hash'], $item['item_key']]);
    $db->Execute('DELETE FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=?', [$company['company_key_hash'], $item['item_key']]);
    $priceListKeys = $db->GetCol('SELECT price_list_key FROM project_company_inventory_price_list WHERE company_key_hash=? AND price_list_code LIKE ?', [$company['company_key_hash'], $prefix . '%']);
    foreach ($priceListKeys as $priceListKey) {
        $db->Execute('DELETE FROM project_company_inventory_price_list_country WHERE company_key_hash=? AND price_list_key=?', [$company['company_key_hash'], $priceListKey]);
    }
    $db->Execute('DELETE FROM project_company_inventory_price_list WHERE company_key_hash=? AND price_list_code LIKE ?', [$company['company_key_hash'], $prefix . '%']);
});
$price = yovel_admin_save_item_price($company, $admin, [
    'item_key' => $item['item_key'], 'price_list_code' => $prefix . '-PRICE', 'price_list_name' => 'IW-03 Stock Price',
    'currency_code' => 'PHP', 'uom_code' => 'EA', 'rate' => '12.50', 'minimum_qty' => '0',
    'valid_from' => '2026-08-25', 'valid_to' => '', 'is_selling' => '1', 'is_buying' => '0', 'price_status' => 'ACTIVE',
]);

$type = yovel_admin_save_warehouse_type($company, $admin, [
    'warehouse_type_code' => $prefix . '-STORAGE', 'warehouse_type_name' => 'Storage', 'warehouse_type_status' => 'ACTIVE',
]);
inventory_warehouse_assert(yovel_admin_is_uuid((string) ($type['warehouse_type_key'] ?? '')), 'Warehouse Type did not return a stable key.');

$rootWarehouse = yovel_admin_save_warehouse($company, $admin, [
    'warehouse_code' => $prefix . '-ROOT', 'warehouse_name' => 'Main warehouse group',
    'warehouse_type_key' => $type['warehouse_type_key'], 'is_group' => '1', 'warehouse_status' => 'ACTIVE',
]);
$priorityWarehouse = yovel_admin_save_warehouse($company, $admin, [
    'warehouse_code' => $prefix . '-A', 'warehouse_name' => 'Priority storage',
    'parent_warehouse_key' => $rootWarehouse['warehouse_key'], 'warehouse_type_key' => $type['warehouse_type_key'],
    'is_group' => '0', 'warehouse_status' => 'ACTIVE', 'capacity_qty' => '100', 'putaway_priority' => '10',
]);
$overflowWarehouse = yovel_admin_save_warehouse($company, $admin, [
    'warehouse_code' => $prefix . '-B', 'warehouse_name' => 'Overflow storage',
    'parent_warehouse_key' => $rootWarehouse['warehouse_key'], 'warehouse_type_key' => $type['warehouse_type_key'],
    'is_group' => '0', 'warehouse_status' => 'ACTIVE', 'capacity_qty' => '50', 'putaway_priority' => '20',
]);
$disabledWarehouse = yovel_admin_save_warehouse($company, $admin, [
    'warehouse_code' => $prefix . '-OFF', 'warehouse_name' => 'Disabled storage',
    'parent_warehouse_key' => $rootWarehouse['warehouse_key'], 'warehouse_type_key' => $type['warehouse_type_key'],
    'is_group' => '0', 'warehouse_status' => 'DISABLED', 'capacity_qty' => '50', 'putaway_priority' => '30',
]);
inventory_warehouse_assert((string) $priorityWarehouse['parent_warehouse_key'] === (string) $rootWarehouse['warehouse_key'], 'Warehouse parent read-back failed.');
inventory_warehouse_assert((string) $priorityWarehouse['capacity_qty'] === '100.000000000', 'Warehouse capacity must be an exact decimal string.');

$GLOBALS['yovel_admin_inventory_warehouse_fault'] = static function (string $point): void {
    if ($point === 'warehouse_after_write') {
        throw new RuntimeException('injected warehouse header rollback');
    }
};
inventory_warehouse_reject(static fn () => yovel_admin_save_warehouse($company, $admin, [
    'warehouse_code' => $prefix . '-ROLLBACK-WH', 'warehouse_name' => 'Rollback warehouse',
    'warehouse_type_key' => $type['warehouse_type_key'], 'warehouse_status' => 'ACTIVE', 'capacity_qty' => '10',
]), 'injected warehouse header rollback', 'Injected warehouse header failure did not roll back.');
unset($GLOBALS['yovel_admin_inventory_warehouse_fault']);
inventory_warehouse_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_code=?', [$company['company_key_hash'], $prefix . '-ROLLBACK-WH']) === 0, 'Warehouse rollback injection left a header.');

inventory_warehouse_reject(static fn () => yovel_admin_save_warehouse($company, $admin, array_merge($rootWarehouse, ['parent_warehouse_key' => $priorityWarehouse['warehouse_key']])), 'cycle', 'Warehouse cycles must be rejected.');
inventory_warehouse_reject(static fn () => yovel_admin_save_warehouse($company, $admin, array_merge($priorityWarehouse, ['parent_warehouse_key' => $priorityWarehouse['warehouse_key']])), 'cycle', 'A warehouse cannot parent itself.');

$foreignWarehouseKey = bx_uuid();
$foreignHash = str_repeat('f', 64);
$db->Execute(
    "INSERT INTO project_company_inventory_warehouse
     (warehouse_key,company_key,company_key_hash,warehouse_code,warehouse_name,parent_warehouse_key,warehouse_type_key,is_group,warehouse_status,capacity_qty,putaway_priority,created_by_admin_key,updated_by_admin_key)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
    [$foreignWarehouseKey, bx_uuid(), $foreignHash, $prefix . '-FOREIGN', 'Foreign', null, null, 1, 'ACTIVE', '0.000000000', 100, $admin['admin_key'], $admin['admin_key']]
);
inventory_warehouse_reject(static fn () => yovel_admin_save_warehouse($company, $admin, [
    'warehouse_code' => $prefix . '-CROSS', 'warehouse_name' => 'Cross company child', 'parent_warehouse_key' => $foreignWarehouseKey,
    'warehouse_type_key' => $type['warehouse_type_key'], 'is_group' => '0', 'warehouse_status' => 'ACTIVE',
]), 'company', 'Cross-company warehouse parents must be rejected.');
$db->Execute('DELETE FROM project_company_inventory_warehouse WHERE warehouse_key=?', [$foreignWarehouseKey]);

$dimension = yovel_admin_save_inventory_dimension($company, $admin, [
    'dimension_code' => 'ZONE', 'dimension_name' => 'Storage zone', 'dimension_status' => 'ACTIVE', 'is_required' => '1',
    'values' => [
        ['dimension_value_code' => 'COLD', 'dimension_value_name' => 'Cold zone', 'dimension_value_status' => 'ACTIVE'],
        ['dimension_value_code' => 'AMBIENT', 'dimension_value_name' => 'Ambient zone', 'dimension_value_status' => 'ACTIVE'],
    ],
]);
inventory_warehouse_assert(count($dimension['values'] ?? []) === 2, 'Inventory Dimension children were not read back.');
inventory_warehouse_reject(static fn () => yovel_admin_save_inventory_dimension($company, $admin, [
    'dimension_code' => 'ZONE', 'dimension_name' => 'Duplicate', 'values' => [],
]), 'unique', 'Duplicate dimension tuples must be rejected.');

$settings = yovel_admin_save_inventory_settings($company, $admin, [
    'allow_negative_stock' => '0', 'capacity_enforcement' => '1', 'default_putaway_strategy' => 'PRIORITY',
]);
inventory_warehouse_assert((string) $settings['allow_negative_stock'] === '0', 'Stock Settings read-back failed.');

$dimensions = ['ZONE' => 'COLD'];
inventory_warehouse_reject(static fn () => yovel_admin_inventory_save_bin_source($company, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $rootWarehouse['warehouse_key'], 'dimensions' => $dimensions,
    'source_owner' => 'IW03_TEST', 'source_key' => $prefix . '-GROUP', 'source_type' => 'ACTUAL', 'quantity' => '1',
]), 'group', 'Group warehouses must reject stock actions.');
inventory_warehouse_reject(static fn () => yovel_admin_inventory_save_bin_source($company, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $disabledWarehouse['warehouse_key'], 'dimensions' => $dimensions,
    'source_owner' => 'IW03_TEST', 'source_key' => $prefix . '-DISABLED', 'source_type' => 'ACTUAL', 'quantity' => '1',
]), 'disabled', 'Disabled warehouses must reject stock actions.');
inventory_warehouse_reject(static fn () => yovel_admin_inventory_save_bin_source($company, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $priorityWarehouse['warehouse_key'], 'dimensions' => [],
    'source_owner' => 'IW03_TEST', 'source_key' => $prefix . '-NO-DIM', 'source_type' => 'ACTUAL', 'quantity' => '1',
]), 'dimension', 'Required stock dimensions must be enforced.');
inventory_warehouse_reject(static fn () => yovel_admin_inventory_save_bin_source($company, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $priorityWarehouse['warehouse_key'],
    'dimensions' => [['dimension_code' => 'ZONE', 'dimension_value_code' => 'COLD'], ['dimension_code' => 'ZONE', 'dimension_value_code' => 'AMBIENT']],
    'source_owner' => 'IW03_TEST', 'source_key' => $prefix . '-DUP-DIM', 'source_type' => 'ACTUAL', 'quantity' => '1',
]), 'duplicate', 'Duplicate dimension tuples must be rejected.');

foreach ([
    ['ACTUAL', '90'], ['RESERVED', '12'], ['ORDERED', '5'], ['REQUESTED', '7'], ['PLANNED', '3'],
] as [$sourceType, $quantity]) {
    yovel_admin_inventory_save_bin_source($company, $admin, [
        'item_key' => $item['item_key'], 'warehouse_key' => $priorityWarehouse['warehouse_key'], 'dimensions' => $dimensions,
        'source_owner' => 'IW03_TEST', 'source_key' => $prefix . '-' . $sourceType, 'source_type' => $sourceType, 'quantity' => $quantity,
    ]);
}
$bin = yovel_admin_inventory_bin($company, (string) $item['item_key'], (string) $priorityWarehouse['warehouse_key'], $dimensions);
$expectedProjection = [
    'actual' => '90.000000000', 'reserved' => '12.000000000', 'ordered' => '5.000000000',
    'requested' => '7.000000000', 'planned' => '3.000000000', 'projected' => '93.000000000',
    'available_to_reserve' => '78.000000000',
];
foreach ($expectedProjection as $field => $expected) {
    inventory_warehouse_assert((string) ($bin[$field] ?? '') === $expected, 'Projected quantity field is wrong: ' . $field);
}
$priceStock = yovel_admin_inventory_item_price_stock($company, ['item_key' => (string) $item['item_key']]);
inventory_warehouse_assert(count($priceStock) === 1, 'Item Price Stock report did not return the priced stock item.');
inventory_warehouse_assert((string) $priceStock[0]['rate'] === (string) $price['rate'] && (string) $priceStock[0]['actual'] === '90.000000000' && (string) $priceStock[0]['projected'] === '93.000000000', 'Item Price Stock did not integrate catalogue price and authoritative bin projection.');
inventory_warehouse_assert((int) $db->GetOne(
    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='project_company_inventory_bin' AND COLUMN_NAME IN ('reserved_qty','ordered_qty','requested_qty','planned_qty','projected_qty','available_to_reserve')",
    [BUILDERX_DB_NAME]
) === 0, 'Derived projected totals must not be persisted on bins.');

$sameSource = yovel_admin_inventory_save_bin_source($company, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $priorityWarehouse['warehouse_key'], 'dimensions' => $dimensions,
    'source_owner' => 'IW03_TEST', 'source_key' => $prefix . '-ACTUAL', 'source_type' => 'ACTUAL', 'quantity' => '91',
]);
inventory_warehouse_assert((string) $sameSource['actual'] === '91.000000000', 'Authoritative source-key upsert was not idempotent.');
inventory_warehouse_assert((int) $db->GetOne(
    'SELECT COUNT(*) FROM project_company_inventory_bin_source WHERE company_key_hash=? AND source_owner=? AND source_key=? AND source_type=?',
    [$company['company_key_hash'], 'IW03_TEST', $prefix . '-ACTUAL', 'ACTUAL']
) === 1, 'Authoritative source-key database uniqueness failed.');

inventory_warehouse_reject(static fn () => yovel_admin_save_warehouse($company, $admin, array_merge($priorityWarehouse, ['capacity_qty' => '90'])), 'capacity', 'Warehouse capacity cannot be lowered below actual stock.');

inventory_warehouse_reject(static fn () => yovel_admin_inventory_save_bin_source($company, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $priorityWarehouse['warehouse_key'], 'dimensions' => $dimensions,
    'source_owner' => 'IW03_TEST', 'source_key' => $prefix . '-OVER-CAPACITY', 'source_type' => 'ACTUAL', 'quantity' => '10',
]), 'capacity', 'Warehouse capacity violations must roll back.');
$afterCapacityFailure = yovel_admin_inventory_bin($company, (string) $item['item_key'], (string) $priorityWarehouse['warehouse_key'], $dimensions);
inventory_warehouse_assert((string) $afterCapacityFailure['actual'] === '91.000000000', 'Capacity failure changed authoritative actual quantity.');

$ruleA = yovel_admin_save_putaway_rule($company, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $priorityWarehouse['warehouse_key'], 'dimensions' => $dimensions,
    'priority' => '10', 'putaway_rule_status' => 'ACTIVE',
]);
$ruleB = yovel_admin_save_putaway_rule($company, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $overflowWarehouse['warehouse_key'], 'dimensions' => $dimensions,
    'priority' => '20', 'putaway_rule_status' => 'ACTIVE',
]);
inventory_warehouse_assert(yovel_admin_is_uuid((string) $ruleA['putaway_rule_key']) && yovel_admin_is_uuid((string) $ruleB['putaway_rule_key']), 'Putaway Rules need stable keys.');
$putaway = yovel_admin_putaway_plan($company, (string) $item['item_key'], '25', $dimensions);
inventory_warehouse_assert(count($putaway['allocations'] ?? []) === 2, 'Putaway must split across available warehouse capacity.');
inventory_warehouse_assert((string) $putaway['allocations'][0]['warehouse_key'] === (string) $priorityWarehouse['warehouse_key'], 'Putaway priority ordering failed.');
inventory_warehouse_assert((string) $putaway['allocations'][0]['quantity'] === '9.000000000' && (string) $putaway['allocations'][1]['quantity'] === '16.000000000', 'Putaway capacity allocation is not exact.');

$reorder = yovel_admin_save_inventory_reorder_rule($company, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $priorityWarehouse['warehouse_key'],
    'reorder_level' => '100', 'reorder_quantity' => '30', 'reorder_rule_status' => 'ACTIVE',
]);
inventory_warehouse_assert((string) $reorder['reorder_level'] === '100.000000000', 'Item Reorder exact read-back failed.');
$recommendations = yovel_admin_reorder_recommendations($company, (string) $priorityWarehouse['warehouse_key']);
inventory_warehouse_assert(count($recommendations) === 1, 'Items To Be Requested must include the below-level bin.');
inventory_warehouse_assert((string) $recommendations[0]['recommended_quantity'] === '30.000000000', 'Recommended reorder quantity is wrong.');
inventory_warehouse_assert((string) $recommendations[0]['projected'] === '94.000000000', 'Reorder projection must use the authoritative source rows.');

$GLOBALS['yovel_admin_inventory_warehouse_fault'] = static function (string $point): void {
    if ($point === 'bin_source_after_readback') {
        throw new RuntimeException('injected warehouse rollback');
    }
};
inventory_warehouse_reject(static fn () => yovel_admin_inventory_save_bin_source($company, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $overflowWarehouse['warehouse_key'], 'dimensions' => $dimensions,
    'source_owner' => 'IW03_TEST', 'source_key' => $prefix . '-ROLLBACK', 'source_type' => 'ACTUAL', 'quantity' => '4',
]), 'injected warehouse rollback', 'Injected bin failure did not roll back.');
unset($GLOBALS['yovel_admin_inventory_warehouse_fault']);
inventory_warehouse_assert((int) $db->GetOne(
    'SELECT COUNT(*) FROM project_company_inventory_bin_source WHERE company_key_hash=? AND source_key=?',
    [$company['company_key_hash'], $prefix . '-ROLLBACK']
) === 0, 'Rollback injection left an authoritative source row.');

$foreignCompany = $company;
$foreignCompany['company_key_hash'] = str_repeat('0', 64);
$foreignBin = yovel_admin_inventory_bin($foreignCompany, (string) $item['item_key'], (string) $priorityWarehouse['warehouse_key'], $dimensions);
inventory_warehouse_assert((string) $foreignBin['actual'] === '0.000000000', 'Bin reads leaked across companies.');
inventory_warehouse_reject(static fn () => yovel_admin_inventory_save_bin_source($foreignCompany, $admin, [
    'item_key' => $item['item_key'], 'warehouse_key' => $priorityWarehouse['warehouse_key'], 'dimensions' => $dimensions,
    'source_owner' => 'IW03_TEST', 'source_key' => $prefix . '-CROSS', 'source_type' => 'ACTUAL', 'quantity' => '1',
]), 'authorized', 'Cross-company bin writes must be rejected.');

$db->BeginTrans();
$locked = yovel_admin_inventory_lock_bin($db, $company, (string) $item['item_key'], (string) $priorityWarehouse['warehouse_key'], $dimensions);
inventory_warehouse_assert((string) $locked['bin_key'] === (string) $bin['bin_key'], 'Bin row lock did not return the authoritative tuple.');
$lockPayload = base64_encode(json_encode([
    'company' => $company,
    'admin' => $admin,
    'input' => [
        'item_key' => $item['item_key'], 'warehouse_key' => $priorityWarehouse['warehouse_key'], 'dimensions' => $dimensions,
        'source_owner' => 'IW03_LOCK_TEST', 'source_key' => $prefix . '-LOCK', 'source_type' => 'RESERVED', 'quantity' => '1',
    ],
], JSON_THROW_ON_ERROR));
$pipes = [];
$process = proc_open([PHP_BINARY, __DIR__ . '/inventory-warehouse-lock-worker.php', $lockPayload], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
inventory_warehouse_assert(is_resource($process), 'Competing bin allocation worker could not start.');
usleep(500000);
$workerStatus = proc_get_status($process);
inventory_warehouse_assert((bool) ($workerStatus['running'] ?? false), 'Competing allocation was not blocked by the Inventory row lock.');
$db->RollbackTrans();
$workerOutput = stream_get_contents($pipes[1]);
$workerError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$workerExit = proc_close($process);
inventory_warehouse_assert($workerExit === 0, 'Competing allocation worker failed: ' . trim((string) $workerError));
$workerResult = json_decode((string) $workerOutput, true, 512, JSON_THROW_ON_ERROR);
inventory_warehouse_assert((float) ($workerResult['elapsed'] ?? 0) >= 0.45, 'Competing allocation did not wait for the locked bin row.');
inventory_warehouse_assert((string) ($workerResult['result']['reserved'] ?? '') === '13.000000000', 'Competing allocation did not serialize against the authoritative bin.');

$warehousePost = yovel_admin_inventory_warehouse_handle_post($company, $admin, 'save_inventory_warehouse', $priorityWarehouse + ['section' => 'warehouses']);
inventory_warehouse_assert($warehousePost === ['message' => 'Warehouse saved.', 'section' => 'warehouses', 'query' => ['warehouse' => (string) $priorityWarehouse['warehouse_key']]], 'Warehouse POST response is unstable.');
$putawayPost = yovel_admin_inventory_warehouse_handle_post($company, $admin, 'save_inventory_putaway_rule', $ruleA + ['section' => 'putaway']);
inventory_warehouse_assert($putawayPost === ['message' => 'Putaway rule saved.', 'section' => 'putaway', 'query' => ['putaway_rule' => (string) $ruleA['putaway_rule_key']]], 'Putaway POST response is unstable.');
$reorderPost = yovel_admin_inventory_warehouse_handle_post($company, $admin, 'save_inventory_reorder_rule', $reorder + ['section' => 'reorder-levels']);
inventory_warehouse_assert($reorderPost === ['message' => 'Reorder rule saved.', 'section' => 'reorder-levels', 'query' => ['reorder_rule' => (string) $reorder['reorder_rule_key']]], 'Reorder POST response is unstable.');

$activeModuleSections = yovel_admin_inventory_warehouse_sections();
$activeModuleSection = 'warehouses';
$activeModuleData = yovel_admin_inventory_warehouse_data($company, $admin, 'warehouses');
$companyName = (string) $company['company_name'];
ob_start();
require $root . '/company/admin/modules/inventory-warehouse/views/workspace.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
inventory_warehouse_assert(str_contains($markup, 'Add Warehouse'), 'Warehouse creation action is missing.');
inventory_warehouse_assert(str_contains($markup, 'data-record-modal-open="inventory-warehouse-modal-new"'), 'Add Warehouse must open the shared record modal.');
inventory_warehouse_assert(str_contains($markup, 'name="module_view" value="inventory-warehouse"'), 'Warehouse forms must use the shared module dispatcher.');
inventory_warehouse_assert(str_contains($markup, 'data-confirm-submit'), 'Warehouse forms must reach the sibling confirmation dialog.');
inventory_warehouse_assert(strpos($markup, 'data-confirm-dialog') > strrpos($markup, '</form>'), 'Warehouse confirmation must be body-owned and sibling to forms.');
inventory_warehouse_assert(str_contains($markup, 'aria-label="Edit ' . bx_h((string) $priorityWarehouse['warehouse_code']) . '"'), 'Warehouse edit action is missing.');

$activeModuleFormState = [
    'section' => 'warehouses', 'action' => 'save_inventory_warehouse', 'error' => 'Warehouse capacity is invalid.',
    'input' => ['warehouse_code' => 'RETAINED-WH', 'warehouse_name' => 'Retained warehouse', 'capacity_qty' => 'bad'],
];
$activeModuleData = yovel_admin_inventory_warehouse_data($company, $admin, 'warehouses');
ob_start();
require $root . '/company/admin/modules/inventory-warehouse/views/workspace.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$errorMarkup = (string) ob_get_clean();
inventory_warehouse_assert(str_contains($errorMarkup, 'data-record-modal-open-on-load'), 'Warehouse server errors must reopen the matching modal.');
inventory_warehouse_assert(str_contains($errorMarkup, 'value="RETAINED-WH"'), 'Warehouse server errors must rehydrate prior values.');
inventory_warehouse_assert(str_contains($errorMarkup, 'Warehouse capacity is invalid.'), 'Warehouse server error is missing.');

$source = (string) file_get_contents($root . '/company/admin/modules/inventory-warehouse/warehouses.php');
inventory_warehouse_assert(str_contains($source, 'FOR UPDATE'), 'Inventory bin and warehouse writes must use row locks.');
foreach (['project_company_finance_', 'project_company_sales_', 'project_company_buying_', 'project_company_manufacturing_'] as $forbiddenPrefix) {
    inventory_warehouse_assert(!str_contains($source, $forbiddenPrefix), 'IW-03 directly references a foreign owner table: ' . $forbiddenPrefix);
}

echo "Inventory/Warehouse IW-03 warehouse and bin checks passed.\n";
