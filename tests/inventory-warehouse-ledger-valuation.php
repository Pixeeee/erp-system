<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/inventory-warehouse/functions.php';

function inventory_ledger_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function inventory_ledger_reject(callable $operation, string $needle, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        inventory_ledger_assert(str_contains(strtolower($error->getMessage()), strtolower($needle)), $message . ' Unexpected error: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException($message);
}

foreach ([
    'yovel_admin_inventory_post_ledger_effects',
    'yovel_admin_inventory_reverse_ledger_effects',
    'yovel_admin_inventory_replay_partition',
    'yovel_admin_inventory_stock_snapshot',
    'yovel_admin_inventory_item_valuation',
    'yovel_admin_inventory_valuation_handoff',
    'yovel_admin_inventory_close_partition',
] as $service) {
    inventory_ledger_assert(function_exists($service), 'Missing IW-04 service: ' . $service);
}

$db = bx_db();
yovel_admin_inventory_catalogue_schema();
yovel_admin_inventory_warehouse_control_schema();
yovel_admin_inventory_ledger_schema();

$ledgerTables = [
    'project_company_inventory_ledger_partition',
    'project_company_inventory_stock_ledger_entry',
    'project_company_inventory_ledger_state',
    'project_company_inventory_stock_closing',
    'project_company_inventory_repost_run',
];
foreach ($ledgerTables as $table) {
    inventory_ledger_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
        [BUILDERX_DB_NAME, $table]
    ) === 1, 'Missing IW-04 table: ' . $table);
}
$indexes = $db->GetCol(
    "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('project_company_inventory_stock_ledger_entry','project_company_inventory_ledger_state','project_company_inventory_stock_closing')",
    [BUILDERX_DB_NAME]
);
foreach (['uq_inventory_ledger_idempotency', 'uq_inventory_ledger_state_version', 'uq_inventory_stock_closing_partition'] as $index) {
    inventory_ledger_assert(is_array($indexes) && in_array($index, $indexes, true), 'Missing IW-04 uniqueness index: ' . $index);
}

$scope = $db->GetRow(
    "SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key,a.admin_status
     FROM project_company c JOIN project_company_admin a
       ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE'
     WHERE c.company_status='ACTIVE' ORDER BY c.x_id,a.x_id LIMIT 1"
);
inventory_ledger_assert(is_array($scope) && $scope !== [], 'An active company/admin fixture is required.');
$company = ['company_key' => (string) $scope['company_key'], 'company_key_hash' => (string) $scope['company_key_hash'], 'company_name' => (string) $scope['company_name']];
$admin = ['admin_key' => (string) $scope['admin_key'], 'admin_status' => (string) $scope['admin_status']];
$prefix = 'IW04' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 8));
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id),0) FROM builder_audit_log');
$itemKeys = [];
$warehouseKeys = [];
$typeKey = '';

register_shutdown_function(static function () use ($db, $company, $prefix, $auditFloor, &$itemKeys, &$warehouseKeys, &$typeKey, $ledgerTables): void {
    unset($GLOBALS['yovel_admin_inventory_ledger_fault']);
    $hash = $company['company_key_hash'];
    if ($itemKeys !== []) {
        $marks = implode(',', array_fill(0, count($itemKeys), '?'));
        $params = array_merge([$hash], $itemKeys);
        foreach ($itemKeys as $itemKey) {
            $db->Execute('DELETE FROM builder_audit_log WHERE x_id>? AND new_values LIKE ?', [$auditFloor, '%' . $itemKey . '%']);
        }
        foreach (array_reverse($ledgerTables) as $table) {
            $db->Execute("DELETE FROM {$table} WHERE company_key_hash=? AND item_key IN ({$marks})", $params);
        }
        $db->Execute("DELETE FROM project_company_inventory_bin_source WHERE company_key_hash=? AND item_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_inventory_bin WHERE company_key_hash=? AND item_key IN ({$marks})", $params);
        foreach ([
            'project_company_inventory_item_reorder', 'project_company_inventory_item_website_spec',
            'project_company_inventory_item_lead_time', 'project_company_inventory_item_default',
            'project_company_inventory_item_tax', 'project_company_inventory_item_party_detail',
            'project_company_inventory_item_alternative', 'project_company_inventory_item_manufacturer',
            'project_company_inventory_item_variant_attribute', 'project_company_inventory_item_barcode',
            'project_company_inventory_item_uom',
        ] as $table) {
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

$type = yovel_admin_save_warehouse_type($company, $admin, [
    'warehouse_type_code' => $prefix . '-TYPE', 'warehouse_type_name' => 'IW-04 storage', 'warehouse_type_status' => 'ACTIVE',
]);
$typeKey = (string) $type['warehouse_type_key'];
$rootWarehouse = yovel_admin_save_warehouse($company, $admin, [
    'warehouse_code' => $prefix . '-ROOT', 'warehouse_name' => 'IW-04 root', 'warehouse_type_key' => $typeKey,
    'is_group' => '1', 'warehouse_status' => 'ACTIVE',
]);
$warehouseKeys[] = (string) $rootWarehouse['warehouse_key'];
$warehouses = [];
foreach (['FIFO', 'LIFO', 'AVERAGE', 'REPLAY', 'REVERSAL', 'CLOSING', 'HISTORICAL', 'DETERMINISTIC', 'NEGATIVE', 'INDEPENDENT'] as $suffix) {
    $warehouse = yovel_admin_save_warehouse($company, $admin, [
        'warehouse_code' => $prefix . '-' . $suffix, 'warehouse_name' => $suffix . ' stock',
        'parent_warehouse_key' => $rootWarehouse['warehouse_key'], 'warehouse_type_key' => $typeKey,
        'capacity_qty' => '100000', 'warehouse_status' => 'ACTIVE',
    ]);
    $warehouseKeys[] = (string) $warehouse['warehouse_key'];
    $warehouses[$suffix] = $warehouse;
}

$items = [];
foreach (['FIFO', 'LIFO', 'AVERAGE', 'REPLAY', 'REVERSAL', 'CLOSING', 'HISTORICAL', 'DETERMINISTIC', 'NEGATIVE', 'INDEPENDENT'] as $suffix) {
    $item = yovel_admin_save_inventory_item($company, $admin, [
        'item_code' => $prefix . '-' . $suffix, 'item_name' => $suffix . ' valuation item',
        'item_status' => 'ACTIVE', 'item_kind' => 'STOCK', 'stock_uom_code' => 'EA',
        'uoms' => [['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '1']],
    ]);
    $itemKeys[] = (string) $item['item_key'];
    $items[$suffix] = $item;
}

$post = static function (array $voucher, array $effects) use ($company, $admin): array {
    return yovel_admin_inventory_in_transaction(static fn (ADOConnection $db): array =>
        yovel_admin_inventory_post_ledger_effects($db, $company, $admin, $voucher, $effects)
    );
};
$voucher = static fn (string $key, string $datetime, string $method): array => [
    'voucher_type' => 'IW04_TEST', 'voucher_key' => $prefix . '-' . $key,
    'posting_datetime' => $datetime, 'valuation_method' => $method, 'currency_code' => 'PHP',
];
$effect = static fn (array $item, array $warehouse, string $qty, ?string $rate = null, int $index = 0, array $extra = []): array => $extra + [
    'effect_index' => $index, 'voucher_line_key' => 'LINE-' . $index,
    'item_key' => (string) $item['item_key'], 'warehouse_key' => (string) $warehouse['warehouse_key'],
    'dimensions' => [], 'actual_qty' => $qty, 'incoming_rate' => $rate,
];

$methodExpectations = [
    'FIFO' => ['value' => '56.000000000', 'rate' => '7.000000000', 'outgoing' => '5.333333333'],
    'LIFO' => ['value' => '40.000000000', 'rate' => '5.000000000', 'outgoing' => '6.666666667'],
    'MOVING_AVERAGE' => ['value' => '48.000000000', 'rate' => '6.000000000', 'outgoing' => '6.000000000'],
];
foreach ($methodExpectations as $method => $expected) {
    $fixtureKey = $method === 'MOVING_AVERAGE' ? 'AVERAGE' : $method;
    $item = $items[$fixtureKey];
    $warehouse = $warehouses[$fixtureKey];
    $post($voucher($fixtureKey . '-R1', '2026-01-01 08:00:00.000000', $method), [$effect($item, $warehouse, '10', '5')]);
    $post($voucher($fixtureKey . '-R2', '2026-01-02 08:00:00.000000', $method), [$effect($item, $warehouse, '10', '7')]);
    $issue = $post($voucher($fixtureKey . '-I1', '2026-01-03 08:00:00.000000', $method), [$effect($item, $warehouse, '-12')]);
    $state = $issue['states'][0] ?? [];
    inventory_ledger_assert((string) ($state['qty_after'] ?? '') === '8.000000000', $method . ' quantity is incorrect.');
    inventory_ledger_assert((string) ($state['stock_value'] ?? '') === $expected['value'], $method . ' stock value is incorrect.');
    inventory_ledger_assert((string) ($state['valuation_rate'] ?? '') === $expected['rate'], $method . ' valuation rate is incorrect.');
    inventory_ledger_assert((string) ($state['outgoing_rate'] ?? '') === $expected['outgoing'], $method . ' outgoing rate is incorrect.');
    foreach ($issue['states'] as $ledgerState) {
        inventory_ledger_assert(
            bccomp((string) $ledgerState['qty_after'], bcadd((string) $ledgerState['qty_before'], (string) $ledgerState['actual_qty'], 9), 9) === 0,
            'Ledger quantity equation failed.'
        );
        inventory_ledger_assert(
            bccomp((string) $ledgerState['stock_value'], bcadd((string) $ledgerState['stock_value_before'], (string) $ledgerState['stock_value_difference'], 9), 9) === 0,
            'Ledger value equation failed.'
        );
    }
}

$adjustment = $post(
    $voucher('AVERAGE-ADJUST', '2026-01-04 08:00:00.000000', 'MOVING_AVERAGE'),
    [$effect($items['AVERAGE'], $warehouses['AVERAGE'], '0', null, 0, ['value_difference' => '8'])]
);
inventory_ledger_assert((string) $adjustment['states'][0]['qty_after'] === '8.000000000', 'Value-only adjustment changed quantity.');
inventory_ledger_assert((string) $adjustment['states'][0]['stock_value'] === '56.000000000', 'Value-only adjustment did not change value exactly.');

$idempotentVoucher = $voucher('FIFO-IDEMPOTENT', '2026-01-05 08:00:00.000000', 'FIFO');
$idempotentEffects = [$effect($items['FIFO'], $warehouses['FIFO'], '2', '8', 0, ['finance_dimensions' => ['cost_center' => 'MAIN']])];
$firstIdempotent = $post($idempotentVoucher, $idempotentEffects);
$entryCountBeforeRetry = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_key=?', [$company['company_key_hash'], $idempotentVoucher['voucher_key']]);
$secondIdempotent = $post($idempotentVoucher, $idempotentEffects);
inventory_ledger_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_key=?', [$company['company_key_hash'], $idempotentVoucher['voucher_key']]) === $entryCountBeforeRetry, 'Idempotent retry appended a duplicate effect.');
inventory_ledger_assert($firstIdempotent['entry_keys'] === $secondIdempotent['entry_keys'], 'Idempotent retry returned unstable keys.');
inventory_ledger_reject(static fn () => $post($idempotentVoucher, [$effect($items['FIFO'], $warehouses['FIFO'], '3', '8')]), 'idempotency', 'Changed retry payload was accepted.');

$post($voucher('REPLAY-R2', '2026-02-02 08:00:00.000000', 'FIFO'), [$effect($items['REPLAY'], $warehouses['REPLAY'], '10', '10')]);
$post($voucher('REPLAY-I1', '2026-02-03 08:00:00.000000', 'FIFO'), [$effect($items['REPLAY'], $warehouses['REPLAY'], '-5')]);
$beforeReplay = yovel_admin_inventory_stock_snapshot($company, (string) $items['REPLAY']['item_key'], (string) $warehouses['REPLAY']['warehouse_key'], '2026-02-03 23:59:59.999999');
inventory_ledger_assert((string) $beforeReplay['stock_value'] === '50.000000000', 'Pre-replay value is incorrect.');
$post($voucher('REPLAY-R1', '2026-02-01 08:00:00.000000', 'FIFO'), [$effect($items['REPLAY'], $warehouses['REPLAY'], '10', '4')]);
$afterReplay = yovel_admin_inventory_stock_snapshot($company, (string) $items['REPLAY']['item_key'], (string) $warehouses['REPLAY']['warehouse_key'], '2026-02-03 23:59:59.999999');
inventory_ledger_assert((string) $afterReplay['qty_after'] === '15.000000000' && (string) $afterReplay['stock_value'] === '120.000000000', 'Backdated FIFO replay is incorrect.');
inventory_ledger_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_ledger_state WHERE company_key_hash=? AND item_key=?', [$company['company_key_hash'], $items['REPLAY']['item_key']]) > 3, 'Replay did not retain append-only prior state versions.');

$sameTimestamp = '2026-02-10 08:00:00.000000';
$post($voucher('DETERMINISTIC-R1', $sameTimestamp, 'FIFO'), [$effect($items['DETERMINISTIC'], $warehouses['DETERMINISTIC'], '1', '4')]);
$post($voucher('DETERMINISTIC-R2', $sameTimestamp, 'FIFO'), [$effect($items['DETERMINISTIC'], $warehouses['DETERMINISTIC'], '1', '10')]);
$deterministicIssue = $post($voucher('DETERMINISTIC-I1', '2026-02-10 08:00:01.000000', 'FIFO'), [$effect($items['DETERMINISTIC'], $warehouses['DETERMINISTIC'], '-1')]);
inventory_ledger_assert((string) $deterministicIssue['states'][0]['stock_value'] === '10.000000000', 'Same-timestamp FIFO ordering is not deterministic by creation sequence.');

$reversalVoucher = $voucher('REVERSAL-R1', '2026-03-01 08:00:00.000000', 'FIFO');
$postedForReversal = $post($reversalVoucher, [$effect($items['REVERSAL'], $warehouses['REVERSAL'], '5', '9')]);
$reversal = yovel_admin_inventory_in_transaction(static fn (ADOConnection $db): array =>
    yovel_admin_inventory_reverse_ledger_effects($db, $company, $admin, $reversalVoucher + ['reversal_posting_datetime' => '2026-03-02 08:00:00.000000'], 'Test cancellation')
);
$reversalSnapshot = yovel_admin_inventory_stock_snapshot($company, (string) $items['REVERSAL']['item_key'], (string) $warehouses['REVERSAL']['warehouse_key'], '2026-03-02 23:59:59.999999');
inventory_ledger_assert((string) $reversalSnapshot['qty_after'] === '0.000000000' && (string) $reversalSnapshot['stock_value'] === '0.000000000', 'Additive reversal did not restore quantity and value.');
inventory_ledger_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_key=?', [$company['company_key_hash'], $reversalVoucher['voucher_key']]) === 2, 'Cancellation did not retain the original effect and one reversal.');
inventory_ledger_assert($reversal['reversal_of_entry_keys'] === $postedForReversal['entry_keys'], 'Reversal identity is unstable.');

$post($voucher('CLOSING-R1', '2026-04-01 08:00:00.000000', 'MOVING_AVERAGE'), [$effect($items['CLOSING'], $warehouses['CLOSING'], '10', '11')]);
$closing = yovel_admin_inventory_in_transaction(static fn (ADOConnection $db): array =>
    yovel_admin_inventory_close_partition($db, $company, $admin, (string) $items['CLOSING']['item_key'], (string) $warehouses['CLOSING']['warehouse_key'], [], '2026-04-30 23:59:59.999999')
);
inventory_ledger_assert((string) $closing['qty_after'] === '10.000000000' && (string) $closing['stock_value'] === '110.000000000', 'Closing snapshot is incorrect.');
inventory_ledger_reject(static fn () => $post($voucher('CLOSING-BACKDATED', '2026-04-15 08:00:00.000000', 'MOVING_AVERAGE'), [$effect($items['CLOSING'], $warehouses['CLOSING'], '1', '11')]), 'closed', 'Posting into a closed period was accepted.');
$post($voucher('CLOSING-NEXT', '2026-05-01 08:00:00.000000', 'MOVING_AVERAGE'), [$effect($items['CLOSING'], $warehouses['CLOSING'], '1', '11')]);

$post($voucher('HISTORICAL-R1', '2026-04-01 08:00:00.000000', 'FIFO'), [$effect($items['HISTORICAL'], $warehouses['HISTORICAL'], '10', '11')]);
$post($voucher('HISTORICAL-R2', '2026-06-01 08:00:00.000000', 'FIFO'), [$effect($items['HISTORICAL'], $warehouses['HISTORICAL'], '5', '13')]);
$historicalClosing = yovel_admin_inventory_in_transaction(static fn (ADOConnection $db): array =>
    yovel_admin_inventory_close_partition($db, $company, $admin, (string) $items['HISTORICAL']['item_key'], (string) $warehouses['HISTORICAL']['warehouse_key'], [], '2026-04-30 23:59:59.999999')
);
$historicalLayers = json_decode((string) $historicalClosing['queue_json'], true, 512, JSON_THROW_ON_ERROR);
$historicalQueueQty = '0.000000000';
$historicalQueueValue = '0.000000000';
foreach ($historicalLayers as $layer) {
    $historicalQueueQty = bcadd($historicalQueueQty, (string) $layer['qty'], 9);
    $historicalQueueValue = bcadd($historicalQueueValue, (string) $layer['value'], 9);
}
inventory_ledger_assert($historicalQueueQty === '10.000000000' && $historicalQueueValue === '110.000000000', 'Historical closing queue does not match its as-of quantity and value.');

inventory_ledger_reject(static fn () => $post($voucher('NEGATIVE-DENIED', '2026-05-01 08:00:00.000000', 'MOVING_AVERAGE'), [$effect($items['NEGATIVE'], $warehouses['NEGATIVE'], '-2', null, 0, ['allow_negative_stock' => false, 'valuation_rate' => '3'])]), 'negative', 'Negative stock was accepted without policy permission.');
$negative = $post($voucher('NEGATIVE-ALLOWED', '2026-05-02 08:00:00.000000', 'MOVING_AVERAGE'), [$effect($items['NEGATIVE'], $warehouses['NEGATIVE'], '-2', null, 0, ['allow_negative_stock' => true, 'valuation_rate' => '3'])]);
inventory_ledger_assert((string) $negative['states'][0]['qty_after'] === '-2.000000000' && (string) $negative['states'][0]['stock_value'] === '-6.000000000', 'Allowed negative stock valuation is incorrect.');
$post($voucher('NEGATIVE-RECOVER', '2026-05-03 08:00:00.000000', 'MOVING_AVERAGE'), [$effect($items['NEGATIVE'], $warehouses['NEGATIVE'], '2', '3')]);
$negativeRecovered = yovel_admin_inventory_stock_snapshot($company, (string) $items['NEGATIVE']['item_key'], (string) $warehouses['NEGATIVE']['warehouse_key'], '2026-05-03 23:59:59.999999');
inventory_ledger_assert((string) $negativeRecovered['qty_after'] === '0.000000000' && (string) $negativeRecovered['stock_value'] === '0.000000000', 'Negative stock recovery did not return to zero.');

$post($voucher('TRANSFER-OPEN', '2026-05-10 08:00:00.000000', 'FIFO'), [$effect($items['INDEPENDENT'], $warehouses['INDEPENDENT'], '5', '2')]);
$transfer = $post($voucher('TRANSFER-MOVE', '2026-05-11 08:00:00.000000', 'FIFO'), [
    $effect($items['INDEPENDENT'], $warehouses['INDEPENDENT'], '-2', null, 0),
    $effect($items['INDEPENDENT'], $warehouses['FIFO'], '2', '2', 1),
]);
inventory_ledger_assert(count($transfer['entry_keys']) === 2 && count($transfer['partitions']) === 2, 'Transfer effects were not posted atomically across both partitions.');
$transferSource = yovel_admin_inventory_stock_snapshot($company, (string) $items['INDEPENDENT']['item_key'], (string) $warehouses['INDEPENDENT']['warehouse_key'], '2026-05-11 23:59:59.999999');
$transferTarget = yovel_admin_inventory_stock_snapshot($company, (string) $items['INDEPENDENT']['item_key'], (string) $warehouses['FIFO']['warehouse_key'], '2026-05-11 23:59:59.999999');
inventory_ledger_assert((string) $transferSource['qty_after'] === '3.000000000' && (string) $transferTarget['qty_after'] === '2.000000000', 'Transfer did not preserve source and target quantity.');

$valuation = yovel_admin_inventory_item_valuation($company, (string) $items['FIFO']['item_key'], (string) $warehouses['FIFO']['warehouse_key'], '2026-01-05');
foreach (['valuation_rate', 'currency_code', 'valuation_method', 'source_timestamp'] as $field) {
    inventory_ledger_assert(trim((string) ($valuation[$field] ?? '')) !== '', 'Authoritative valuation is missing ' . $field . '.');
}
inventory_ledger_assert((string) $valuation['currency_code'] === 'PHP' && (string) $valuation['valuation_method'] === 'FIFO', 'Authoritative valuation metadata is unstable.');
$handoff = yovel_admin_inventory_valuation_handoff($firstIdempotent);
inventory_ledger_assert(($handoff['status'] ?? '') === 'PENDING_FINANCE' && count($handoff['entries'] ?? []) === 1, 'Finance handoff payload is incomplete.');
inventory_ledger_assert(array_key_exists('stock_value_difference', $handoff['entries'][0]), 'Finance handoff lacks signed stock value.');
inventory_ledger_assert(($handoff['posting_date'] ?? '') === '2026-01-05' && ($handoff['entries'][0]['finance_dimensions']['cost_center'] ?? '') === 'MAIN', 'Finance handoff lacks posting date or dimensions.');
$reversalHandoff = yovel_admin_inventory_valuation_handoff($reversal);
inventory_ledger_assert(($reversalHandoff['entries'][0]['entry_kind'] ?? '') === 'REVERSAL' && ($reversalHandoff['entries'][0]['reversal_of_entry_key'] ?? '') === $postedForReversal['entry_keys'][0], 'Finance handoff lacks reversal identity.');

foreach (['after_replay', 'after_audit'] as $faultPoint) {
    $rollbackVoucher = $voucher('ROLLBACK-' . strtoupper($faultPoint), '2026-06-01 08:00:00.000000', 'FIFO');
    $GLOBALS['yovel_admin_inventory_ledger_fault'] = static function (string $point) use ($faultPoint): void {
        if ($point === $faultPoint) {
            throw new RuntimeException('injected ledger rollback ' . $faultPoint);
        }
    };
    inventory_ledger_reject(static fn () => $post($rollbackVoucher, [$effect($items['INDEPENDENT'], $warehouses['INDEPENDENT'], '1', '2')]), 'injected ledger rollback', 'Injected ' . $faultPoint . ' failure did not surface.');
    unset($GLOBALS['yovel_admin_inventory_ledger_fault']);
    inventory_ledger_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_key=?', [$company['company_key_hash'], $rollbackVoucher['voucher_key']]) === 0, 'Rollback left a ledger entry after ' . $faultPoint . '.');
    inventory_ledger_assert((int) $db->GetOne("SELECT COUNT(*) FROM project_company_inventory_bin_source WHERE company_key_hash=? AND source_owner='INVENTORY_LEDGER' AND source_key=?", [$company['company_key_hash'], $rollbackVoucher['voucher_key']]) === 0, 'Rollback left a bin source after ' . $faultPoint . '.');
    inventory_ledger_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE x_id>? AND new_values LIKE ?", [$auditFloor, '%' . $rollbackVoucher['voucher_key'] . '%']) === 0, 'Rollback left an audit event after ' . $faultPoint . '.');
}

$foreignCompany = $company;
$foreignCompany['company_key_hash'] = str_repeat('0', 64);
inventory_ledger_reject(static fn () => yovel_admin_inventory_item_valuation($foreignCompany, (string) $items['FIFO']['item_key']), 'company', 'Cross-company valuation read was accepted.');

$samePartitionPayload = base64_encode(json_encode([
    'company' => $company, 'admin' => $admin,
    'voucher' => $voucher('LOCK-SAME', '2026-07-01 08:00:00.000000', 'FIFO'),
    'effects' => [$effect($items['INDEPENDENT'], $warehouses['INDEPENDENT'], '1', '2')],
], JSON_THROW_ON_ERROR));
$db->BeginTrans();
yovel_admin_inventory_lock_bin($db, $company, (string) $items['INDEPENDENT']['item_key'], (string) $warehouses['INDEPENDENT']['warehouse_key'], []);
$sameProcess = proc_open([PHP_BINARY, __DIR__ . '/inventory-warehouse-ledger-worker.php', $samePartitionPayload], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $samePipes, $root);
inventory_ledger_assert(is_resource($sameProcess), 'Same-partition ledger worker could not start.');
usleep(500000);
inventory_ledger_assert((bool) (proc_get_status($sameProcess)['running'] ?? false), 'Same-partition writer did not wait for the bin lock.');
$db->RollbackTrans();
$sameOutput = stream_get_contents($samePipes[1]);
$sameError = stream_get_contents($samePipes[2]);
fclose($samePipes[1]);
fclose($samePipes[2]);
inventory_ledger_assert(proc_close($sameProcess) === 0, 'Same-partition worker failed: ' . trim((string) $sameError));
$sameResult = json_decode((string) $sameOutput, true, 512, JSON_THROW_ON_ERROR);
inventory_ledger_assert((float) ($sameResult['elapsed'] ?? 0) >= 0.45, 'Same-partition writer did not serialize.');

$differentPayload = base64_encode(json_encode([
    'company' => $company, 'admin' => $admin,
    'voucher' => $voucher('LOCK-DIFFERENT', '2026-07-01 08:00:01.000000', 'FIFO'),
    'effects' => [$effect($items['FIFO'], $warehouses['FIFO'], '1', '2')],
], JSON_THROW_ON_ERROR));
$db->BeginTrans();
yovel_admin_inventory_lock_bin($db, $company, (string) $items['INDEPENDENT']['item_key'], (string) $warehouses['INDEPENDENT']['warehouse_key'], []);
$differentProcess = proc_open([PHP_BINARY, __DIR__ . '/inventory-warehouse-ledger-worker.php', $differentPayload], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $differentPipes, $root);
inventory_ledger_assert(is_resource($differentProcess), 'Different-partition ledger worker could not start.');
usleep(500000);
inventory_ledger_assert(!(bool) (proc_get_status($differentProcess)['running'] ?? true), 'Different-partition writer was blocked by an unrelated bin lock.');
$db->RollbackTrans();
$differentOutput = stream_get_contents($differentPipes[1]);
$differentError = stream_get_contents($differentPipes[2]);
fclose($differentPipes[1]);
fclose($differentPipes[2]);
inventory_ledger_assert(proc_close($differentProcess) === 0, 'Different-partition worker failed: ' . trim((string) $differentError));
inventory_ledger_assert((float) (json_decode((string) $differentOutput, true, 512, JSON_THROW_ON_ERROR)['elapsed'] ?? 1) < 0.45, 'Different partition did not proceed independently.');

$ledgerSource = (string) file_get_contents($root . '/company/admin/modules/inventory-warehouse/ledger.php');
inventory_ledger_assert(!preg_match('/\b(DELETE|TRUNCATE)\s+FROM\s+project_company_inventory_(stock_ledger_entry|ledger_state|stock_closing)/i', $ledgerSource), 'IW-04 history is not append-only.');
foreach (['project_company_finance_', 'project_company_sales_', 'project_company_buying_', 'project_company_manufacturing_'] as $forbiddenPrefix) {
    inventory_ledger_assert(!str_contains($ledgerSource, $forbiddenPrefix), 'IW-04 directly references a foreign owner table: ' . $forbiddenPrefix);
}
inventory_ledger_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE x_id>? AND module='project_company_inventory_repost_run'", [$auditFloor]) > 0, 'Inventory repost runs were not audit logged.');

echo "Inventory/Warehouse IW-04 ledger and valuation checks passed.\n";
