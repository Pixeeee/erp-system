<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/inventory-warehouse/functions.php';

function inventory_tracking_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function inventory_tracking_reject(callable $operation, string $needle, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        inventory_tracking_assert(str_contains(strtolower($error->getMessage()), strtolower($needle)), $message . ' Unexpected: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException($message);
}

foreach ([
    'yovel_admin_save_inventory_batch', 'yovel_admin_save_inventory_serial',
    'yovel_admin_inventory_validate_serial_batch_effects', 'yovel_admin_inventory_post_serial_batch_bundle',
    'yovel_admin_inventory_reverse_serial_batch_bundle', 'yovel_admin_inventory_available_batches',
    'yovel_admin_inventory_available_serials', 'yovel_admin_inventory_trace',
] as $service) {
    inventory_tracking_assert(function_exists($service), 'Missing IW-07 service: ' . $service);
}

$db = bx_db();
yovel_admin_inventory_catalogue_schema();
yovel_admin_inventory_warehouse_control_schema();
yovel_admin_inventory_ledger_schema();
yovel_admin_inventory_serial_batch_schema();

$tables = [
    'project_company_inventory_batch', 'project_company_inventory_serial',
    'project_company_inventory_serial_batch_bundle', 'project_company_inventory_serial_batch_entry',
    'project_company_inventory_trace_event',
];
foreach ($tables as $table) {
    inventory_tracking_assert((int) $db->GetOne('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [BUILDERX_DB_NAME, $table]) === 1, 'Missing IW-07 table: ' . $table);
}
$indexes = $db->GetCol("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('project_company_inventory_batch','project_company_inventory_serial','project_company_inventory_serial_batch_bundle')", [BUILDERX_DB_NAME]);
foreach (['uq_inventory_batch_number', 'uq_inventory_serial_number', 'uq_inventory_tracking_bundle_effect'] as $index) {
    inventory_tracking_assert(in_array($index, is_array($indexes) ? $indexes : [], true), 'Missing IW-07 uniqueness index: ' . $index);
}

$scope = $db->GetRow("SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key,a.admin_status FROM project_company c JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE' WHERE c.company_status='ACTIVE' ORDER BY c.x_id,a.x_id LIMIT 1");
inventory_tracking_assert(is_array($scope) && $scope !== [], 'An active company/admin fixture is required.');
$company = ['company_key' => (string) $scope['company_key'], 'company_key_hash' => (string) $scope['company_key_hash'], 'company_name' => (string) $scope['company_name']];
$admin = ['admin_key' => (string) $scope['admin_key'], 'admin_status' => (string) $scope['admin_status']];
$prefix = 'IW07' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 8));
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id),0) FROM builder_audit_log');
$itemKeys = [];
$warehouseKeys = [];
$typeKey = '';

register_shutdown_function(static function () use ($db, $company, $prefix, $auditFloor, &$itemKeys, &$warehouseKeys, &$typeKey): void {
    unset($GLOBALS['yovel_admin_inventory_serial_batch_fault']);
    $hash = $company['company_key_hash'];
    if ($itemKeys !== []) {
        $marks = implode(',', array_fill(0, count($itemKeys), '?'));
        $params = array_merge([$hash], $itemKeys);
        foreach (['project_company_inventory_trace_event','project_company_inventory_serial_batch_entry','project_company_inventory_serial_batch_bundle','project_company_inventory_batch','project_company_inventory_serial','project_company_inventory_ledger_state','project_company_inventory_repost_run','project_company_inventory_stock_closing','project_company_inventory_stock_ledger_entry','project_company_inventory_ledger_partition'] as $table) {
            $db->Execute("DELETE FROM {$table} WHERE company_key_hash=? AND item_key IN ({$marks})", $params);
        }
        $db->Execute("DELETE FROM project_company_inventory_bin_source WHERE company_key_hash=? AND item_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_inventory_bin WHERE company_key_hash=? AND item_key IN ({$marks})", $params);
        foreach (['project_company_inventory_item_reorder','project_company_inventory_item_website_spec','project_company_inventory_item_lead_time','project_company_inventory_item_default','project_company_inventory_item_tax','project_company_inventory_item_party_detail','project_company_inventory_item_alternative','project_company_inventory_item_manufacturer','project_company_inventory_item_variant_attribute','project_company_inventory_item_barcode','project_company_inventory_item_uom'] as $table) {
            $db->Execute("DELETE FROM {$table} WHERE company_key_hash=? AND item_key IN ({$marks})", $params);
        }
        $db->Execute("DELETE FROM project_company_inventory_item WHERE company_key_hash=? AND item_key IN ({$marks})", $params);
        foreach ($itemKeys as $itemKey) {
            $db->Execute('DELETE FROM builder_audit_log WHERE x_id>? AND new_values LIKE ?', [$auditFloor, '%' . $itemKey . '%']);
        }
    }
    foreach (array_reverse($warehouseKeys) as $warehouseKey) {
        $db->Execute('DELETE FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_key=?', [$hash, $warehouseKey]);
    }
    if ($typeKey !== '') {
        $db->Execute('DELETE FROM project_company_inventory_warehouse_type WHERE company_key_hash=? AND warehouse_type_key=?', [$hash, $typeKey]);
    }
    $db->Execute('DELETE FROM builder_audit_log WHERE x_id>? AND new_values LIKE ?', [$auditFloor, '%' . $prefix . '%']);
});

$type = yovel_admin_save_warehouse_type($company, $admin, ['warehouse_type_code' => $prefix . '-TYPE', 'warehouse_type_name' => 'Tracking storage', 'warehouse_type_status' => 'ACTIVE']);
$typeKey = (string) $type['warehouse_type_key'];
$rootWarehouse = yovel_admin_save_warehouse($company, $admin, ['warehouse_code' => $prefix . '-ROOT', 'warehouse_name' => 'Tracking root', 'warehouse_type_key' => $typeKey, 'is_group' => '1', 'warehouse_status' => 'ACTIVE']);
$warehouseKeys[] = (string) $rootWarehouse['warehouse_key'];
$warehouses = [];
foreach (['A', 'B'] as $suffix) {
    $warehouse = yovel_admin_save_warehouse($company, $admin, ['warehouse_code' => $prefix . '-' . $suffix, 'warehouse_name' => 'Tracking ' . $suffix, 'parent_warehouse_key' => $rootWarehouse['warehouse_key'], 'warehouse_type_key' => $typeKey, 'capacity_qty' => '1000', 'warehouse_status' => 'ACTIVE']);
    $warehouseKeys[] = (string) $warehouse['warehouse_key'];
    $warehouses[$suffix] = $warehouse;
}
$items = [];
foreach (['SERIAL' => [1,0], 'BATCH' => [0,1], 'BOTH' => [1,1]] as $name => [$serialFlag, $batchFlag]) {
    $item = yovel_admin_save_inventory_item($company, $admin, ['item_code' => $prefix . '-' . $name, 'item_name' => $name . ' tracked item', 'item_status' => 'ACTIVE', 'item_kind' => 'STOCK', 'stock_uom_code' => 'EA', 'has_serial_no' => $serialFlag, 'has_batch_no' => $batchFlag, 'uoms' => [['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '1']]]);
    $itemKeys[] = (string) $item['item_key'];
    $items[$name] = $item;
}

$serials = [];
foreach (['S1','S2','S3'] as $number) {
    $serials[$number] = yovel_admin_save_inventory_serial($company, $admin, ['item_key' => $items['SERIAL']['item_key'], 'serial_number' => $prefix . '-' . $number, 'serial_status' => 'ACTIVE']);
}
$batch = yovel_admin_save_inventory_batch($company, $admin, ['item_key' => $items['BATCH']['item_key'], 'batch_number' => $prefix . '-B1', 'manufacturing_date' => '2026-01-01', 'expiry_date' => '2027-12-31', 'batch_status' => 'ACTIVE']);
$expiredBatch = yovel_admin_save_inventory_batch($company, $admin, ['item_key' => $items['BATCH']['item_key'], 'batch_number' => $prefix . '-EXPIRED', 'manufacturing_date' => '2025-01-01', 'expiry_date' => '2025-12-31', 'batch_status' => 'ACTIVE']);
$bothBatch = yovel_admin_save_inventory_batch($company, $admin, ['item_key' => $items['BOTH']['item_key'], 'batch_number' => $prefix . '-BOTH-B', 'expiry_date' => '2028-12-31', 'batch_status' => 'ACTIVE']);
$bothSerial = yovel_admin_save_inventory_serial($company, $admin, ['item_key' => $items['BOTH']['item_key'], 'serial_number' => $prefix . '-BOTH-S', 'serial_status' => 'ACTIVE']);

inventory_tracking_reject(static fn () => yovel_admin_save_inventory_serial($company, $admin, ['item_key' => $items['BATCH']['item_key'], 'serial_number' => $prefix . '-S1']), 'serial', 'Company-global duplicate serial identity was accepted.');
inventory_tracking_reject(static fn () => yovel_admin_save_inventory_batch($company, $admin, ['item_key' => $items['SERIAL']['item_key'], 'batch_number' => $prefix . '-B1']), 'batch', 'Batch identity moved to another item.');

$voucher = static fn (string $key, string $date): array => ['voucher_type' => 'IW07_TEST', 'voucher_key' => $prefix . '-' . $key, 'voucher_line_key' => 'LINE-1', 'posting_datetime' => $date, 'valuation_method' => 'FIFO', 'currency_code' => 'PHP'];
$post = static function (array $voucher, array $bundle) use ($company, $admin): array {
    return yovel_admin_inventory_in_transaction(static fn (ADOConnection $db): array => yovel_admin_inventory_post_serial_batch_bundle($db, $company, $admin, $voucher, $bundle));
};
$serialBundle = static fn (string $qty, array $serialRows, string $warehouse = 'A', array $extra = []): array => $extra + ['item_key' => $items['SERIAL']['item_key'], 'warehouse_key' => $warehouses[$warehouse]['warehouse_key'], 'dimensions' => [], 'actual_qty' => $qty, 'incoming_rate' => bccomp($qty, '0', 9) === 1 ? '10' : null, 'direction' => bccomp($qty, '0', 9) === 1 ? 'IN' : 'OUT', 'serials' => $serialRows];
$batchBundle = static fn (string $qty, array $batchRows, array $extra = []): array => $extra + ['item_key' => $items['BATCH']['item_key'], 'warehouse_key' => $warehouses['A']['warehouse_key'], 'dimensions' => [], 'actual_qty' => $qty, 'incoming_rate' => bccomp($qty, '0', 9) === 1 ? '4' : null, 'direction' => bccomp($qty, '0', 9) === 1 ? 'IN' : 'OUT', 'batches' => $batchRows];

$serialReceipt = $post($voucher('SERIAL-IN', '2026-02-01 08:00:00.000000'), $serialBundle('2', [['serial_key' => $serials['S1']['serial_key']], ['serial_key' => $serials['S2']['serial_key']]]));
inventory_tracking_assert((string) $serialReceipt['ledger']['states'][0]['qty_after'] === '2.000000000', 'Serial receipt did not post its ledger quantity.');
inventory_tracking_assert(count($serialReceipt['entries']) === 2 && array_sum(array_map(static fn (array $row): float => (float) $row['stock_value_difference'], $serialReceipt['entries'])) === 20.0, 'Serial valuation lineage does not equal the ledger effect.');
$serialRetry = $post($voucher('SERIAL-IN', '2026-02-01 08:00:00.000000'), $serialBundle('2', [['serial_key' => $serials['S1']['serial_key']], ['serial_key' => $serials['S2']['serial_key']]]));
inventory_tracking_assert(!empty($serialRetry['idempotent']) && (string) $serialRetry['bundle']['bundle_key'] === (string) $serialReceipt['bundle']['bundle_key'], 'Tracking bundle retry is not idempotent.');
inventory_tracking_reject(static fn () => $post($voucher('SERIAL-DUP', '2026-02-01 09:00:00.000000'), $serialBundle('1', [['serial_key' => $serials['S1']['serial_key']]])), 'available', 'Duplicate inward serial use was accepted.');
inventory_tracking_reject(static fn () => $post($voucher('SERIAL-COUNT', '2026-02-01 10:00:00.000000'), $serialBundle('2', [['serial_key' => $serials['S3']['serial_key']]])), 'quantity', 'Serial count mismatch was accepted.');

$serialIssue = $post($voucher('SERIAL-OUT', '2026-02-02 08:00:00.000000'), $serialBundle('-1', [['serial_key' => $serials['S1']['serial_key']]]));
$availableSerials = yovel_admin_inventory_available_serials($company, (string) $items['SERIAL']['item_key'], (string) $warehouses['A']['warehouse_key'], '2026-02-02 23:59:59.999999');
inventory_tracking_assert(array_column($availableSerials, 'serial_key') === [$serials['S2']['serial_key']], 'Serial warehouse availability is incorrect.');

$post($voucher('BATCH-IN', '2026-03-01 08:00:00.000000'), $batchBundle('5', [['batch_key' => $batch['batch_key'], 'quantity' => '5']]));
$batchIssue = $post($voucher('BATCH-OUT', '2026-03-02 08:00:00.000000'), $batchBundle('-2', [['batch_key' => $batch['batch_key'], 'quantity' => '2']]));
inventory_tracking_assert((string) $batchIssue['entries'][0]['stock_value_difference'] === '-8.000000000', 'Batch valuation lineage is incorrect.');
$post($voucher('EXPIRED-IN', '2026-03-01 09:00:00.000000'), $batchBundle('1', [['batch_key' => $expiredBatch['batch_key'], 'quantity' => '1']]));
inventory_tracking_reject(static fn () => $post($voucher('EXPIRED-OUT', '2026-03-02 09:00:00.000000'), $batchBundle('-1', [['batch_key' => $expiredBatch['batch_key'], 'quantity' => '1']])), 'expired', 'Expired batch issue was accepted without override.');
$post($voucher('EXPIRED-OVERRIDE', '2026-03-02 10:00:00.000000'), $batchBundle('-1', [['batch_key' => $expiredBatch['batch_key'], 'quantity' => '1']], ['expiry_override_authorized' => true, 'expiry_override_reason' => 'Authorized quality disposition']));
inventory_tracking_reject(static fn () => $post($voucher('BATCH-NEG-LINE', '2026-03-03 08:00:00.000000'), $batchBundle('-1', [['batch_key' => $batch['batch_key'], 'quantity' => '-1']])), 'positive', 'Negative batch line quantity was accepted.');

$bothResult = $post($voucher('BOTH-IN', '2026-04-01 08:00:00.000000'), ['item_key' => $items['BOTH']['item_key'], 'warehouse_key' => $warehouses['A']['warehouse_key'], 'dimensions' => [], 'actual_qty' => '1', 'incoming_rate' => '7', 'direction' => 'IN', 'serials' => [['serial_key' => $bothSerial['serial_key'], 'batch_key' => $bothBatch['batch_key']]]]);
inventory_tracking_assert(count($bothResult['entries']) === 1 && (string) $bothResult['entries'][0]['stock_value_difference'] === '7.000000000', 'Combined serial/batch tracking double-counted value.');

$reversal = yovel_admin_inventory_in_transaction(static fn (ADOConnection $db): array => yovel_admin_inventory_reverse_serial_batch_bundle($db, $company, $admin, (string) $batchIssue['bundle']['bundle_key'], $voucher('BATCH-REVERSE', '2026-03-03 09:00:00.000000'), 'Cancel test issue'));
inventory_tracking_assert((string) $reversal['bundle']['actual_qty'] === '2.000000000' && (string) $reversal['entries'][0]['stock_value_difference'] === '8.000000000', 'Bundle reversal is not additive or valuation exact.');
inventory_tracking_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_serial_batch_bundle WHERE company_key_hash=? AND (bundle_key=? OR reversal_of_bundle_key=?)', [$company['company_key_hash'], $batchIssue['bundle']['bundle_key'], $batchIssue['bundle']['bundle_key']]) === 2, 'Bundle reversal rewrote or lost original history.');

yovel_admin_inventory_save_bin_source($company, $admin, ['item_key' => $items['BATCH']['item_key'], 'warehouse_key' => $warehouses['A']['warehouse_key'], 'source_owner' => 'IW07_TEST', 'source_key' => $prefix . '-RESERVE', 'source_type' => 'RESERVED', 'quantity' => '1']);
$availableBatches = yovel_admin_inventory_available_batches($company, (string) $items['BATCH']['item_key'], (string) $warehouses['A']['warehouse_key'], '2026-03-04 08:00:00.000000');
inventory_tracking_assert((string) $availableBatches[0]['available_qty'] === '4.000000000', 'Batch availability did not exclude authoritative reservations.');

$trace = yovel_admin_inventory_trace($company, (string) $serials['S1']['serial_key']);
inventory_tracking_assert(count($trace['movements']) === 2 && strcmp((string) $trace['movements'][0]['posting_datetime'], (string) $trace['movements'][1]['posting_datetime']) < 0, 'Serial trace chronology is incomplete or unstable.');
$foreign = $company;
$foreign['company_key_hash'] = str_repeat('0', 64);
inventory_tracking_reject(static fn () => yovel_admin_inventory_trace($foreign, (string) $serials['S1']['serial_key']), 'company', 'Cross-company trace was accepted.');

$badValueVoucher = $voucher('BAD-VALUE', '2026-04-02 08:00:00.000000');
inventory_tracking_reject(static fn () => $post($badValueVoucher, $serialBundle('1', [['serial_key' => $serials['S3']['serial_key'], 'value_difference' => '99']])), 'valuation', 'Incorrect serial valuation was accepted.');
inventory_tracking_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_key=?', [$company['company_key_hash'], $badValueVoucher['voucher_key']]) === 0, 'Incorrect serial valuation left a ledger effect.');

$rollbackVoucher = $voucher('ROLLBACK', '2026-05-01 08:00:00.000000');
$GLOBALS['yovel_admin_inventory_serial_batch_fault'] = static function (string $point): void {
    if ($point === 'after_ledger') {
        throw new RuntimeException('injected tracking rollback');
    }
};
inventory_tracking_reject(
    static fn () => $post($rollbackVoucher, $serialBundle('1', [['serial_key' => $serials['S3']['serial_key']]])),
    'injected tracking rollback',
    'Tracking rollback injection did not surface.'
);
unset($GLOBALS['yovel_admin_inventory_serial_batch_fault']);
inventory_tracking_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_key=?', [$company['company_key_hash'], $rollbackVoucher['voucher_key']]) === 0, 'Tracking rollback left an IW-04 ledger effect.');
inventory_tracking_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_serial_batch_bundle WHERE company_key_hash=? AND voucher_key=?', [$company['company_key_hash'], $rollbackVoucher['voucher_key']]) === 0, 'Tracking rollback left a bundle.');

$lockPayload = base64_encode(json_encode([
    'company' => $company,
    'admin' => $admin,
    'voucher' => $voucher('LOCKED', '2026-05-02 08:00:00.000000'),
    'bundle' => $serialBundle('1', [['serial_key' => $serials['S3']['serial_key']]]),
], JSON_THROW_ON_ERROR));
$db->BeginTrans();
$db->GetRow('SELECT serial_key FROM project_company_inventory_serial WHERE company_key_hash=? AND serial_key=? FOR UPDATE', [$company['company_key_hash'], $serials['S3']['serial_key']]);
$process = proc_open([PHP_BINARY, __DIR__ . '/inventory-warehouse-serial-batch-worker.php', $lockPayload], [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, $root);
inventory_tracking_assert(is_resource($process), 'Tracking lock worker could not start.');
usleep(500000);
inventory_tracking_assert((bool) (proc_get_status($process)['running'] ?? false), 'Competing serial writer did not wait for the identity lock.');
$db->RollbackTrans();
$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
inventory_tracking_assert(proc_close($process) === 0, 'Tracking lock worker failed: ' . trim((string) $error));
inventory_tracking_assert((float) (json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR)['elapsed'] ?? 0) >= 0.45, 'Serial identity lock did not serialize writers.');

$handler = yovel_admin_inventory_warehouse_handle_post($company, $admin, 'save_inventory_batch', ['item_key' => $items['BATCH']['item_key'], 'batch_number' => $prefix . '-HANDLER', 'expiry_date' => '2029-12-31']);
inventory_tracking_assert($handler['section'] === 'batch-numbers' && isset($handler['query']['batch']), 'Batch shared POST dispatch contract is incomplete.');
$_GET['item'] = (string) $items['BATCH']['item_key'];
$_GET['warehouse'] = (string) $warehouses['A']['warehouse_key'];
$_GET['as_of'] = '2026-03-04T08:00';
$batchData = yovel_admin_inventory_warehouse_data($company, $admin, 'batch-numbers');
$activeModuleSections = yovel_admin_inventory_warehouse_sections();
$activeModuleSection = 'batch-numbers';
$activeModuleData = $batchData;
$companyName = (string) $company['company_name'];
ob_start();
require $root . '/company/admin/modules/inventory-warehouse/views/workspace.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
foreach (['data-inventory-batches', 'data-inventory-availability', $prefix . '-B1', 'data-record-modal-open="inventory-batch-modal-new"', 'name="module_view" value="inventory-warehouse"', 'data-confirm-submit', 'data-confirm-dialog'] as $marker) {
    inventory_tracking_assert(str_contains($markup, $marker), 'Batch UI contract is missing: ' . $marker);
}
inventory_tracking_assert(strpos($markup, 'data-confirm-dialog') > strrpos($markup, '</form>'), 'Tracking confirmation is nested in a write form.');

$source = (string) file_get_contents($root . '/company/admin/modules/inventory-warehouse/serial-batch.php');
inventory_tracking_assert(!preg_match('/\b(DELETE|TRUNCATE)\s+FROM\s+project_company_inventory_(serial_batch_bundle|serial_batch_entry|trace_event)/i', $source), 'Tracking movement history is not append-only.');
foreach (['project_company_finance_', 'project_company_sales_', 'project_company_buying_', 'project_company_manufacturing_'] as $prefixName) {
    inventory_tracking_assert(!str_contains($source, $prefixName), 'IW-07 references foreign owner table: ' . $prefixName);
}

echo "Inventory/Warehouse IW-07 serial, batch, bundle, and traceability checks passed.\n";
