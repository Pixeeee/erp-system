<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/inventory-warehouse/functions.php';

function inventory_reconciliation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function inventory_reconciliation_reject(callable $operation, string $needle, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        inventory_reconciliation_assert(
            str_contains(strtolower($error->getMessage()), strtolower($needle)),
            $message . ' Unexpected: ' . $error->getMessage()
        );
        return;
    }
    throw new RuntimeException($message);
}

foreach ([
    'yovel_admin_save_stock_reconciliation',
    'yovel_admin_submit_stock_reconciliation',
    'yovel_admin_cancel_stock_reconciliation',
    'yovel_admin_inventory_integrity_diagnostics',
] as $service) {
    inventory_reconciliation_assert(function_exists($service), 'Missing IW-08 service: ' . $service);
}

$db = bx_db();
yovel_admin_inventory_catalogue_schema();
yovel_admin_inventory_warehouse_control_schema();
yovel_admin_inventory_ledger_schema();
yovel_admin_inventory_serial_batch_schema();
yovel_admin_inventory_reconciliation_schema();

foreach (['project_company_inventory_stock_reconciliation', 'project_company_inventory_stock_reconciliation_line'] as $table) {
    inventory_reconciliation_assert(
        (int) $db->GetOne('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [BUILDERX_DB_NAME, $table]) === 1,
        'Missing IW-08 table: ' . $table
    );
}
$indexes = $db->GetCol("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('project_company_inventory_stock_reconciliation','project_company_inventory_stock_reconciliation_line')", [BUILDERX_DB_NAME]);
foreach (['uq_inventory_reconciliation_number', 'uq_inventory_reconciliation_tuple'] as $index) {
    inventory_reconciliation_assert(in_array($index, is_array($indexes) ? $indexes : [], true), 'Missing IW-08 index: ' . $index);
}

$scope = $db->GetRow("SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key,a.admin_status FROM project_company c JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE' WHERE c.company_status='ACTIVE' ORDER BY c.x_id,a.x_id LIMIT 1");
inventory_reconciliation_assert(is_array($scope) && $scope !== [], 'An active company/admin fixture is required.');
$company = ['company_key' => (string) $scope['company_key'], 'company_key_hash' => (string) $scope['company_key_hash'], 'company_name' => (string) $scope['company_name']];
$admin = ['admin_key' => (string) $scope['admin_key'], 'admin_status' => (string) $scope['admin_status']];
$prefix = 'IW08' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 8));
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id),0) FROM builder_audit_log');
$itemKeys = [];
$warehouseKeys = [];
$reconciliationKeys = [];
$typeKey = '';

register_shutdown_function(static function () use ($db, $company, $prefix, $auditFloor, &$itemKeys, &$warehouseKeys, &$reconciliationKeys, &$typeKey): void {
    unset($GLOBALS['yovel_admin_inventory_reconciliation_fault']);
    $hash = $company['company_key_hash'];
    foreach ($reconciliationKeys as $key) {
        $db->Execute('DELETE FROM project_company_inventory_stock_reconciliation_line WHERE company_key_hash=? AND reconciliation_key=?', [$hash, $key]);
        $db->Execute('DELETE FROM project_company_inventory_stock_reconciliation WHERE company_key_hash=? AND reconciliation_key=?', [$hash, $key]);
    }
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
    }
    foreach (array_reverse($warehouseKeys) as $warehouseKey) {
        $db->Execute('DELETE FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_key=?', [$hash, $warehouseKey]);
    }
    if ($typeKey !== '') {
        $db->Execute('DELETE FROM project_company_inventory_warehouse_type WHERE company_key_hash=? AND warehouse_type_key=?', [$hash, $typeKey]);
    }
    $db->Execute('DELETE FROM builder_audit_log WHERE x_id>? AND new_values LIKE ?', [$auditFloor, '%' . $prefix . '%']);
});

$type = yovel_admin_save_warehouse_type($company, $admin, ['warehouse_type_code' => $prefix . '-TYPE', 'warehouse_type_name' => 'Reconciliation storage', 'warehouse_type_status' => 'ACTIVE']);
$typeKey = (string) $type['warehouse_type_key'];
$rootWarehouse = yovel_admin_save_warehouse($company, $admin, ['warehouse_code' => $prefix . '-ROOT', 'warehouse_name' => 'Reconciliation root', 'warehouse_type_key' => $typeKey, 'is_group' => '1', 'warehouse_status' => 'ACTIVE']);
$warehouseKeys[] = (string) $rootWarehouse['warehouse_key'];
$leaf = yovel_admin_save_warehouse($company, $admin, ['warehouse_code' => $prefix . '-LEAF', 'warehouse_name' => 'Reconciliation leaf', 'parent_warehouse_key' => $rootWarehouse['warehouse_key'], 'warehouse_type_key' => $typeKey, 'capacity_qty' => '1000', 'warehouse_status' => 'ACTIVE']);
$warehouseKeys[] = (string) $leaf['warehouse_key'];

$items = [];
foreach (['QTY' => [0, 0], 'VALUE' => [0, 0], 'OPEN' => [0, 0], 'SERIAL' => [1, 0], 'BATCH' => [0, 1]] as $name => [$serialFlag, $batchFlag]) {
    $item = yovel_admin_save_inventory_item($company, $admin, [
        'item_code' => $prefix . '-' . $name,
        'item_name' => $name . ' reconciliation item',
        'item_status' => 'ACTIVE',
        'item_kind' => 'STOCK',
        'stock_uom_code' => 'EA',
        'has_serial_no' => $serialFlag,
        'has_batch_no' => $batchFlag,
        'uoms' => [['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '1']],
    ]);
    $itemKeys[] = (string) $item['item_key'];
    $items[$name] = $item;
}

$serials = [];
foreach (['S1', 'S2'] as $number) {
    $serials[$number] = yovel_admin_save_inventory_serial($company, $admin, ['item_key' => $items['SERIAL']['item_key'], 'serial_number' => $prefix . '-' . $number]);
}
$batch = yovel_admin_save_inventory_batch($company, $admin, ['item_key' => $items['BATCH']['item_key'], 'batch_number' => $prefix . '-B1', 'expiry_date' => '2028-12-31']);

$postLedger = static function (string $key, array $effect) use ($company, $admin, $prefix): array {
    return yovel_admin_inventory_in_transaction(static fn (ADOConnection $db): array => yovel_admin_inventory_post_ledger_effects($db, $company, $admin, [
        'voucher_type' => 'IW08_SEED', 'voucher_key' => $prefix . '-' . $key,
        'posting_datetime' => '2026-01-01 08:00:00.000000', 'valuation_method' => 'FIFO', 'currency_code' => 'PHP',
    ], [$effect]));
};
$postLedger('QTY', ['item_key' => $items['QTY']['item_key'], 'warehouse_key' => $leaf['warehouse_key'], 'actual_qty' => '10', 'incoming_rate' => '5']);
$postLedger('VALUE', ['item_key' => $items['VALUE']['item_key'], 'warehouse_key' => $leaf['warehouse_key'], 'actual_qty' => '4', 'incoming_rate' => '3']);
yovel_admin_inventory_save_bin_source($company, $admin, ['item_key' => $items['QTY']['item_key'], 'warehouse_key' => $leaf['warehouse_key'], 'source_owner' => 'IW08_TEST', 'source_key' => $prefix . '-RESERVE', 'source_type' => 'RESERVED', 'quantity' => '4']);

$seedVoucher = static fn (string $key): array => [
    'voucher_type' => 'IW08_SEED',
    'voucher_key' => $prefix . '-' . $key,
    'voucher_line_key' => 'LINE-1',
    'posting_datetime' => '2026-01-01 09:00:00.000000',
    'valuation_method' => 'FIFO',
    'currency_code' => 'PHP',
];
yovel_admin_inventory_in_transaction(static fn (ADOConnection $db): array => yovel_admin_inventory_post_serial_batch_bundle(
    $db,
    $company,
    $admin,
    $seedVoucher('SERIAL'),
    [
        'item_key' => $items['SERIAL']['item_key'],
        'warehouse_key' => $leaf['warehouse_key'],
        'actual_qty' => '2',
        'incoming_rate' => '7',
        'direction' => 'IN',
        'serials' => [
            ['serial_key' => $serials['S1']['serial_key']],
            ['serial_key' => $serials['S2']['serial_key']],
        ],
    ]
));
yovel_admin_inventory_in_transaction(static fn (ADOConnection $db): array => yovel_admin_inventory_post_serial_batch_bundle(
    $db,
    $company,
    $admin,
    $seedVoucher('BATCH'),
    [
        'item_key' => $items['BATCH']['item_key'],
        'warehouse_key' => $leaf['warehouse_key'],
        'actual_qty' => '5',
        'incoming_rate' => '2',
        'direction' => 'IN',
        'batches' => [['batch_key' => $batch['batch_key'], 'quantity' => '5']],
    ]
));

$baseInput = static fn (string $number, array $lines, array $extra = []): array => $extra + [
    'reconciliation_number' => $prefix . '-' . $number,
    'purpose' => 'STOCK_RECONCILIATION',
    'posting_datetime' => '2026-06-01 08:00:00.000000',
    'valuation_method' => 'FIFO',
    'currency_code' => 'PHP',
    'difference_account_key' => 'FINANCE-PENDING',
    'cost_center_key' => 'COST-CENTER-PENDING',
    'lines' => $lines,
];
$line = static fn (array $item, string $qty, array $extra = []): array => $extra + ['item_key' => $item['item_key'], 'warehouse_key' => $leaf['warehouse_key'], 'dimensions' => [], 'target_qty' => $qty];

inventory_reconciliation_reject(static fn () => yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('DUP', [$line($items['QTY'], '10'), $line($items['QTY'], '9')])), 'duplicate', 'Duplicate reconciliation tuple was accepted.');
inventory_reconciliation_reject(static fn () => yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('GROUP', [['item_key' => $items['QTY']['item_key'], 'warehouse_key' => $rootWarehouse['warehouse_key'], 'target_qty' => '1']])), 'group', 'Group warehouse reconciliation was accepted.');

$noChange = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('NOCHANGE', [$line($items['QTY'], '10', ['target_stock_value' => '50'])]));
$reconciliationKeys[] = (string) $noChange['reconciliation_key'];
inventory_reconciliation_assert($noChange['lines'] === [], 'No-change reconciliation row was persisted.');
inventory_reconciliation_reject(static fn () => yovel_admin_submit_stock_reconciliation($company, $admin, (string) $noChange['reconciliation_key']), 'line', 'Empty reconciliation was submitted.');

$floor = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('FLOOR', [$line($items['QTY'], '3')]));
$reconciliationKeys[] = (string) $floor['reconciliation_key'];
inventory_reconciliation_reject(static fn () => yovel_admin_submit_stock_reconciliation($company, $admin, (string) $floor['reconciliation_key']), 'reserved', 'Reconciliation reduced stock below reservations.');

$target = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('TARGET', [
    $line($items['QTY'], '8'),
    $line($items['VALUE'], '4', ['target_stock_value' => '20']),
]));
$reconciliationKeys[] = (string) $target['reconciliation_key'];
$sameKey = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('TARGET-RENAMED', $target['lines'], ['reconciliation_key' => $target['reconciliation_key']]));
inventory_reconciliation_assert((string) $sameKey['reconciliation_key'] === (string) $target['reconciliation_key'], 'Draft update changed the stable reconciliation key.');
$submitted = yovel_admin_submit_stock_reconciliation($company, $admin, (string) $target['reconciliation_key']);
inventory_reconciliation_assert((string) $submitted['reconciliation_status'] === 'SUBMITTED', 'Reconciliation did not submit.');
inventory_reconciliation_assert(count($submitted['lines']) === 2, 'Submitted reconciliation line read-back is incomplete.');
inventory_reconciliation_assert((string) $submitted['total_quantity_difference'] === '-2.000000000', 'Quantity difference total is incorrect.');
inventory_reconciliation_assert((string) $submitted['total_value_difference'] === '-2.000000000', 'Value difference total is incorrect.');
inventory_reconciliation_assert((string) $submitted['finance_handoff_status'] === 'PENDING' && empty($submitted['finance_handoff']['accepted']), 'Finance handoff was not fail-closed.');
$qtySnapshot = yovel_admin_inventory_stock_snapshot($company, (string) $items['QTY']['item_key'], (string) $leaf['warehouse_key'], '2026-06-01 08:00:00.000000');
$valueSnapshot = yovel_admin_inventory_stock_snapshot($company, (string) $items['VALUE']['item_key'], (string) $leaf['warehouse_key'], '2026-06-01 08:00:00.000000');
inventory_reconciliation_assert($qtySnapshot['qty_after'] === '8.000000000' && $qtySnapshot['stock_value'] === '40.000000000', 'Quantity-only target state is incorrect.');
inventory_reconciliation_assert($valueSnapshot['qty_after'] === '4.000000000' && $valueSnapshot['stock_value'] === '20.000000000', 'Value-only target state is incorrect.');
inventory_reconciliation_reject(static fn () => yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('MUTATE', [$line($items['QTY'], '7')], ['reconciliation_key' => $target['reconciliation_key']])), 'immutable', 'Submitted reconciliation was mutable.');

$serialBad = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('SERIAL-BAD', [$line($items['SERIAL'], '0', ['serials' => [['serial_key' => $serials['S1']['serial_key']]]])]));
$reconciliationKeys[] = (string) $serialBad['reconciliation_key'];
inventory_reconciliation_reject(static fn () => yovel_admin_submit_stock_reconciliation($company, $admin, (string) $serialBad['reconciliation_key']), 'quantity', 'Incomplete Serial reconciliation was accepted.');
$serialDoc = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('SERIAL', [$line($items['SERIAL'], '1', ['serials' => [['serial_key' => $serials['S1']['serial_key']]]])]));
$reconciliationKeys[] = (string) $serialDoc['reconciliation_key'];
inventory_reconciliation_assert((string) yovel_admin_submit_stock_reconciliation($company, $admin, (string) $serialDoc['reconciliation_key'])['reconciliation_status'] === 'SUBMITTED', 'Serial reconciliation failed.');
$batchDoc = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('BATCH', [$line($items['BATCH'], '3', ['batches' => [['batch_key' => $batch['batch_key'], 'quantity' => '2']]])]));
$reconciliationKeys[] = (string) $batchDoc['reconciliation_key'];
inventory_reconciliation_assert((string) yovel_admin_submit_stock_reconciliation($company, $admin, (string) $batchDoc['reconciliation_key'])['reconciliation_status'] === 'SUBMITTED', 'Batch reconciliation failed.');

$opening = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('OPEN', [$line($items['OPEN'], '3', ['target_valuation_rate' => '2', 'target_stock_value' => '6'])], ['purpose' => 'OPENING_STOCK', 'posting_datetime' => '2025-01-01 08:00:00.000000']));
$reconciliationKeys[] = (string) $opening['reconciliation_key'];
inventory_reconciliation_assert((string) yovel_admin_submit_stock_reconciliation($company, $admin, (string) $opening['reconciliation_key'])['purpose'] === 'OPENING_STOCK', 'Opening stock did not submit.');
$badOpening = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('OPEN-BAD', [$line($items['QTY'], '9')], ['purpose' => 'OPENING_STOCK', 'posting_datetime' => '2026-07-01 08:00:00.000000']));
$reconciliationKeys[] = (string) $badOpening['reconciliation_key'];
inventory_reconciliation_reject(static fn () => yovel_admin_submit_stock_reconciliation($company, $admin, (string) $badOpening['reconciliation_key']), 'opening', 'Opening stock was accepted over existing activity.');

$cancelled = yovel_admin_cancel_stock_reconciliation($company, $admin, (string) $target['reconciliation_key'], 'Cycle count correction withdrawn');
inventory_reconciliation_assert((string) $cancelled['reconciliation_status'] === 'CANCELLED', 'Reconciliation cancellation did not complete.');
$originalCount = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_type='STOCK_RECONCILIATION' AND voucher_key=? AND entry_kind='ORIGINAL'", [$company['company_key_hash'], $target['reconciliation_key']]);
$reversalCount = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_type='STOCK_RECONCILIATION' AND voucher_key=? AND entry_kind='REVERSAL'", [$company['company_key_hash'], $target['reconciliation_key']]);
inventory_reconciliation_assert($originalCount === 2 && $reversalCount === 2, 'Cancellation did not preserve originals and append exact reversals.');
$amended = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('AMEND', [$line($items['QTY'], '9')], ['amended_from_reconciliation_key' => $target['reconciliation_key']]));
$reconciliationKeys[] = (string) $amended['reconciliation_key'];
inventory_reconciliation_assert((string) $amended['amended_from_reconciliation_key'] === (string) $target['reconciliation_key'], 'Cancelled reconciliation could not be amended.');

$rollback = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('ROLLBACK', [$line($items['VALUE'], '5', ['target_valuation_rate' => '4'])]));
$reconciliationKeys[] = (string) $rollback['reconciliation_key'];
$GLOBALS['yovel_admin_inventory_reconciliation_fault'] = static function (string $point): void {
    if ($point === 'after_effects') {
        throw new RuntimeException('injected reconciliation rollback');
    }
};
inventory_reconciliation_reject(static fn () => yovel_admin_submit_stock_reconciliation($company, $admin, (string) $rollback['reconciliation_key']), 'injected reconciliation rollback', 'Reconciliation rollback injection did not surface.');
unset($GLOBALS['yovel_admin_inventory_reconciliation_fault']);
inventory_reconciliation_assert((int) $db->GetOne("SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_type='STOCK_RECONCILIATION' AND voucher_key=?", [$company['company_key_hash'], $rollback['reconciliation_key']]) === 0, 'Rollback left reconciliation ledger effects.');
inventory_reconciliation_assert((string) $db->GetOne('SELECT reconciliation_status FROM project_company_inventory_stock_reconciliation WHERE company_key_hash=? AND reconciliation_key=?', [$company['company_key_hash'], $rollback['reconciliation_key']]) === 'DRAFT', 'Rollback changed draft lifecycle state.');

$lockDoc = yovel_admin_save_stock_reconciliation($company, $admin, $baseInput('LOCK', [$line($items['VALUE'], '5', ['target_valuation_rate' => '4']), $line($items['QTY'], '9')]));
$reconciliationKeys[] = (string) $lockDoc['reconciliation_key'];
$firstPartition = $db->GetRow('SELECT partition_key FROM project_company_inventory_ledger_partition WHERE company_key_hash=? AND item_key IN (?,?) ORDER BY item_key,warehouse_key,dimensions_checksum LIMIT 1', [$company['company_key_hash'], $items['VALUE']['item_key'], $items['QTY']['item_key']]);
$payload = base64_encode(json_encode(['company' => $company, 'admin' => $admin, 'reconciliation_key' => $lockDoc['reconciliation_key']], JSON_THROW_ON_ERROR));
$db->BeginTrans();
$db->GetRow('SELECT partition_key FROM project_company_inventory_ledger_partition WHERE company_key_hash=? AND partition_key=? FOR UPDATE', [$company['company_key_hash'], $firstPartition['partition_key']]);
$process = proc_open([PHP_BINARY, __DIR__ . '/inventory-warehouse-reconciliation-worker.php', $payload], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
inventory_reconciliation_assert(is_resource($process), 'Reconciliation lock worker could not start.');
usleep(500000);
inventory_reconciliation_assert((bool) (proc_get_status($process)['running'] ?? false), 'Competing reconciliation submit did not wait for the first stable partition lock.');
$db->RollbackTrans();
$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
inventory_reconciliation_assert(proc_close($process) === 0, 'Reconciliation lock worker failed: ' . trim((string) $error));
inventory_reconciliation_assert((float) (json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR)['elapsed'] ?? 0) >= 0.45, 'Reconciliation partition locks were not deterministic.');

$partition = $db->GetRow('SELECT * FROM project_company_inventory_ledger_partition WHERE company_key_hash=? AND item_key=? AND warehouse_key=?', [$company['company_key_hash'], $items['QTY']['item_key'], $leaf['warehouse_key']]);
$state = $db->GetRow('SELECT * FROM project_company_inventory_ledger_state WHERE company_key_hash=? AND partition_key=? ORDER BY replay_version DESC,x_id DESC LIMIT 1', [$company['company_key_hash'], $partition['partition_key']]);
$batchTrace = $db->GetRow('SELECT * FROM project_company_inventory_trace_event WHERE company_key_hash=? AND batch_key=? ORDER BY x_id LIMIT 1', [$company['company_key_hash'], $batch['batch_key']]);
$serialTrace = $db->GetRow('SELECT * FROM project_company_inventory_trace_event WHERE company_key_hash=? AND serial_key=? ORDER BY x_id LIMIT 1', [$company['company_key_hash'], $serials['S2']['serial_key']]);
$bundle = $db->GetRow('SELECT * FROM project_company_inventory_serial_batch_bundle WHERE company_key_hash=? AND item_key=? ORDER BY x_id LIMIT 1', [$company['company_key_hash'], $items['SERIAL']['item_key']]);
$auditBeforeDiagnostics = (int) $db->GetOne('SELECT COUNT(*) FROM builder_audit_log');
$db->BeginTrans();
$db->Execute('UPDATE project_company_inventory_ledger_partition SET queue_json=?,stock_value=stock_value+1 WHERE company_key_hash=? AND partition_key=?', ['[]', $company['company_key_hash'], $partition['partition_key']]);
$db->Execute('UPDATE project_company_inventory_ledger_state SET qty_after=qty_after+1 WHERE company_key_hash=? AND ledger_state_key=?', [$company['company_key_hash'], $state['ledger_state_key']]);
$db->Execute('UPDATE project_company_inventory_bin SET actual_qty=actual_qty+2 WHERE company_key_hash=? AND bin_key=?', [$company['company_key_hash'], $partition['bin_key']]);
$db->Execute('UPDATE project_company_inventory_trace_event SET quantity=quantity+1 WHERE company_key_hash=? AND trace_event_key=?', [$company['company_key_hash'], $batchTrace['trace_event_key']]);
$db->Execute('UPDATE project_company_inventory_trace_event SET quantity=2 WHERE company_key_hash=? AND trace_event_key=?', [$company['company_key_hash'], $serialTrace['trace_event_key']]);
$db->Execute('UPDATE project_company_inventory_serial_batch_bundle SET ledger_entry_key=? WHERE company_key_hash=? AND bundle_key=?', [bx_uuid(), $company['company_key_hash'], $bundle['bundle_key']]);
$diagnostics = yovel_admin_inventory_integrity_diagnostics($company, ['item_keys' => $itemKeys]);
$diagnosticTypes = array_values(array_unique(array_column($diagnostics, 'type')));
foreach (['FIFO_QUEUE_QTY', 'LEDGER_EQUATION', 'LEDGER_BIN_VARIANCE', 'INCORRECT_STOCK_VALUE', 'BATCH_QTY_VARIANCE', 'SERIAL_COUNT_VARIANCE', 'ORPHANED_BUNDLE_EFFECT'] as $type) {
    inventory_reconciliation_assert(in_array($type, $diagnosticTypes, true), 'Missing integrity diagnostic: ' . $type);
}
inventory_reconciliation_assert((int) $db->GetOne('SELECT COUNT(*) FROM builder_audit_log') === $auditBeforeDiagnostics, 'Read-only diagnostics wrote an audit event.');
$db->RollbackTrans();

$badAdmin = ['admin_key' => '00000000-0000-4000-8000-000000000000', 'admin_status' => 'ACTIVE'];
inventory_reconciliation_reject(static fn () => yovel_admin_save_stock_reconciliation($company, $badAdmin, $baseInput('AUTH', [$line($items['QTY'], '9')])), 'authorized', 'Unauthorized reconciliation write was accepted.');
inventory_reconciliation_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE x_id>? AND (table_name='project_company_inventory_stock_reconciliation' OR new_values LIKE ?)", [$auditFloor, '%' . $prefix . '%']) > 0, 'Reconciliation lifecycle did not write audit evidence.');

$handler = yovel_admin_inventory_warehouse_handle_post($company, $admin, 'save_stock_reconciliation', $baseInput('HANDLER', [$line($items['OPEN'], '4', ['target_valuation_rate' => '2'])]));
$reconciliationKeys[] = (string) ($handler['query']['reconciliation'] ?? '');
inventory_reconciliation_assert($handler['section'] === 'stock-reconciliation' && yovel_admin_is_uuid((string) ($handler['query']['reconciliation'] ?? '')), 'Shared reconciliation POST dispatch is incomplete.');
$activeModuleSections = yovel_admin_inventory_warehouse_sections();
$activeModuleSection = 'stock-reconciliation';
$activeModuleData = yovel_admin_inventory_warehouse_data($company, $admin, 'stock-reconciliation');
$companyName = (string) $company['company_name'];
ob_start();
require $root . '/company/admin/modules/inventory-warehouse/views/workspace.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
foreach (['data-inventory-reconciliations', 'data-inventory-diagnostics', 'data-record-modal-open="inventory-reconciliation-modal-new"', 'name="module_view" value="inventory-warehouse"', 'name="csrf"', 'data-confirm-submit', 'data-confirm-dialog'] as $marker) {
    inventory_reconciliation_assert(str_contains($markup, $marker), 'Reconciliation UI contract is missing: ' . $marker);
}
inventory_reconciliation_assert(strpos($markup, 'data-confirm-dialog') > strrpos($markup, '</form>'), 'Reconciliation confirmation is nested in a write form.');

$source = (string) file_get_contents($root . '/company/admin/modules/inventory-warehouse/reconciliation.php');
inventory_reconciliation_assert(!preg_match('/\b(DELETE|TRUNCATE)\s+FROM\s+project_company_inventory_(stock_ledger_entry|ledger_state|serial_batch_bundle|serial_batch_entry|trace_event)/i', $source), 'Reconciliation mutates immutable Inventory history.');
foreach (['project_company_finance_', 'project_company_sales_', 'project_company_buying_', 'project_company_manufacturing_'] as $foreignPrefix) {
    inventory_reconciliation_assert(!str_contains($source, $foreignPrefix), 'IW-08 references foreign owner table: ' . $foreignPrefix);
}

echo "Inventory/Warehouse IW-08 reconciliation and integrity diagnostic checks passed.\n";
