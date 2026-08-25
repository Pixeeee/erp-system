<?php
declare(strict_types=1);

function yovel_admin_inventory_ledger_fault(string $point): void
{
    $fault = $GLOBALS['yovel_admin_inventory_ledger_fault'] ?? null;
    if (is_callable($fault)) {
        $fault($point);
    }
}

function yovel_admin_inventory_ledger_datetime(mixed $value, string $label = 'Posting datetime'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value)
        ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    if (!$date) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $date->format('Y-m-d H:i:s.u');
}

function yovel_admin_inventory_ledger_method(mixed $value): string
{
    $method = strtoupper(trim((string) $value));
    if (!in_array($method, ['FIFO', 'MOVING_AVERAGE', 'LIFO'], true)) {
        throw new InvalidArgumentException('Inventory valuation method is invalid.');
    }
    return $method;
}

function yovel_admin_inventory_ledger_currency(mixed $value): string
{
    $currency = strtoupper(trim((string) $value));
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
        throw new InvalidArgumentException('Inventory valuation currency is invalid.');
    }
    return $currency;
}

function yovel_admin_inventory_ledger_text(mixed $value, int $max, string $label): string
{
    $value = trim((string) $value);
    if ($value === '' || strlen($value) > $max) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function yovel_admin_inventory_ledger_optional_decimal(mixed $value): ?string
{
    return $value === null || trim((string) $value) === ''
        ? null
        : yovel_admin_inventory_decimal((string) $value);
}

function yovel_admin_inventory_ledger_rate(string $numerator, string $denominator): string
{
    if (bccomp($denominator, '0', 9) === 0) {
        return '0.000000000';
    }
    return yovel_admin_inventory_decimal(bcround(bcdiv($numerator, $denominator, 10), 9, RoundingMode::HalfAwayFromZero));
}

function yovel_admin_inventory_ledger_queue(array $layers): array
{
    $normalized = [];
    foreach ($layers as $layer) {
        $qty = yovel_admin_inventory_decimal((string) ($layer['qty'] ?? '0'));
        $value = yovel_admin_inventory_decimal((string) ($layer['value'] ?? '0'));
        if (bccomp($qty, '0', 9) === 0 && bccomp($value, '0', 9) === 0) {
            continue;
        }
        $normalized[] = [
            'qty' => $qty,
            'value' => $value,
            'rate' => yovel_admin_inventory_ledger_rate($value, $qty),
            'source_entry_key' => trim((string) ($layer['source_entry_key'] ?? '')),
            'posting_datetime' => trim((string) ($layer['posting_datetime'] ?? '')),
        ];
    }
    $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return ['layers' => $normalized, 'json' => $json, 'checksum' => hash('sha256', $json)];
}

function yovel_admin_inventory_ledger_queue_totals(array $layers): array
{
    $qty = '0.000000000';
    $value = '0.000000000';
    foreach ($layers as $layer) {
        $qty = bcadd($qty, (string) $layer['qty'], 9);
        $value = bcadd($value, (string) $layer['value'], 9);
    }
    return [yovel_admin_inventory_decimal($qty), yovel_admin_inventory_decimal($value)];
}

function yovel_admin_inventory_ledger_company(array $company): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if ($companyKey === '' || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
        throw new InvalidArgumentException('Inventory company scope is invalid.');
    }
    $row = bx_db()->GetRow(
        "SELECT company_key FROM project_company WHERE company_key=? AND company_key_hash=? AND company_status='ACTIVE' LIMIT 1",
        [$companyKey, $hash]
    );
    if (!is_array($row) || (string) ($row['company_key'] ?? '') !== $companyKey) {
        throw new InvalidArgumentException('Inventory company scope is not active.');
    }
    return [$companyKey, $hash];
}

function yovel_admin_inventory_ledger_partition(
    ADOConnection $db,
    array $company,
    ?string $adminKey,
    string $itemKey,
    string $warehouseKey,
    array $dimensions,
    string $method,
    string $currency
): array {
    $bin = yovel_admin_inventory_lock_bin($db, $company, $itemKey, $warehouseKey, $dimensions);
    $hash = strtolower((string) $company['company_key_hash']);
    $partition = $db->GetRow(
        'SELECT * FROM project_company_inventory_ledger_partition WHERE company_key_hash=? AND item_key=? AND warehouse_key=? AND dimensions_checksum=? FOR UPDATE',
        [$hash, $itemKey, $warehouseKey, $bin['dimensions_checksum']]
    );
    if (!is_array($partition) || $partition === []) {
        $other = $db->GetRow(
            'SELECT valuation_method,currency_code FROM project_company_inventory_ledger_partition WHERE company_key_hash=? AND item_key=? LIMIT 1 FOR UPDATE',
            [$hash, $itemKey]
        );
        if (is_array($other) && $other !== []
            && ((string) $other['valuation_method'] !== $method || (string) $other['currency_code'] !== $currency)) {
            throw new InvalidArgumentException('An item must use one valuation method and currency across Inventory warehouses.');
        }
        $externalActual = yovel_admin_inventory_decimal((string) $db->GetOne(
            "SELECT COALESCE(SUM(quantity),0) FROM project_company_inventory_bin_source WHERE company_key_hash=? AND bin_key=? AND source_status='ACTIVE' AND source_type='ACTUAL'",
            [$hash, $bin['bin_key']]
        ));
        if (bccomp($externalActual, '0', 9) !== 0) {
            throw new RuntimeException('Existing actual stock must be valued before this ledger partition can be opened.');
        }
        $queue = yovel_admin_inventory_ledger_queue([]);
        $partitionKey = bx_uuid();
        yovel_admin_inventory_execute(
            $db,
            'INSERT INTO project_company_inventory_ledger_partition (partition_key,company_key,company_key_hash,bin_key,item_key,warehouse_key,dimensions_json,dimensions_checksum,valuation_method,currency_code,queue_json,queue_checksum,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$partitionKey, (string) $company['company_key'], $hash, $bin['bin_key'], $itemKey, $warehouseKey, (string) $bin['dimensions_json'], $bin['dimensions_checksum'], $method, $currency, $queue['json'], $queue['checksum'], $adminKey],
            'Inventory ledger partition create'
        );
        $partition = $db->GetRow('SELECT * FROM project_company_inventory_ledger_partition WHERE company_key_hash=? AND partition_key=? FOR UPDATE', [$hash, $partitionKey]);
    }
    if (!is_array($partition) || $partition === []) {
        throw new RuntimeException('Inventory ledger partition could not be locked.');
    }
    if ((string) $partition['valuation_method'] !== $method || (string) $partition['currency_code'] !== $currency) {
        throw new InvalidArgumentException('Inventory ledger valuation method or currency cannot change after activity.');
    }
    $partition['dimensions'] = $bin['dimensions'];
    $partition['warehouse'] = $bin['warehouse'];
    return $partition;
}

function yovel_admin_inventory_ledger_apply(array $entry, array $layers, string $qtyBefore, string $valueBefore): array
{
    $qty = yovel_admin_inventory_decimal((string) $entry['actual_qty']);
    $method = (string) $entry['valuation_method'];
    $incoming = yovel_admin_inventory_ledger_optional_decimal($entry['incoming_rate'] ?? null);
    $fallback = yovel_admin_inventory_ledger_optional_decimal($entry['fallback_valuation_rate'] ?? null) ?? '0.000000000';
    $adjustment = yovel_admin_inventory_ledger_optional_decimal($entry['value_adjustment'] ?? null);
    $forced = yovel_admin_inventory_ledger_optional_decimal($entry['forced_value_difference'] ?? null);
    $newQty = yovel_admin_inventory_decimal(bcadd($qtyBefore, $qty, 9));
    if ((int) $entry['allow_negative_stock'] !== 1 && bccomp($newQty, '0', 9) === -1) {
        throw new InvalidArgumentException('Inventory negative stock is not allowed for this effect.');
    }

    $difference = '0.000000000';
    $outgoingRate = '0.000000000';
    if (bccomp($qty, '0', 9) === 1) {
        if ($incoming === null || bccomp($incoming, '0', 9) === -1) {
            throw new InvalidArgumentException('Positive Inventory stock effects require a non-negative incoming rate.');
        }
        $difference = yovel_admin_inventory_decimal(bcmul($qty, $incoming, 9));
        $layers[] = ['qty' => $qty, 'value' => $difference, 'source_entry_key' => $entry['ledger_entry_key'], 'posting_datetime' => $entry['posting_datetime']];
    } elseif (bccomp($qty, '0', 9) === -1) {
        $remaining = yovel_admin_inventory_decimal(bcsub('0', $qty, 9));
        $consumed = '0.000000000';
        while (bccomp($remaining, '0', 9) === 1 && $layers !== []) {
            $index = $method === 'LIFO' ? array_key_last($layers) : array_key_first($layers);
            $layerQty = yovel_admin_inventory_decimal((string) $layers[$index]['qty']);
            if (bccomp($layerQty, '0', 9) !== 1) {
                break;
            }
            $take = bccomp($layerQty, $remaining, 9) === 1 ? $remaining : $layerQty;
            $layerValue = yovel_admin_inventory_decimal((string) $layers[$index]['value']);
            $takeValue = bccomp($take, $layerQty, 9) === 0
                ? $layerValue
                : yovel_admin_inventory_decimal(bcmul($take, bcdiv($layerValue, $layerQty, 9), 9));
            $layers[$index]['qty'] = yovel_admin_inventory_decimal(bcsub($layerQty, $take, 9));
            $layers[$index]['value'] = yovel_admin_inventory_decimal(bcsub($layerValue, $takeValue, 9));
            $remaining = yovel_admin_inventory_decimal(bcsub($remaining, $take, 9));
            $consumed = yovel_admin_inventory_decimal(bcadd($consumed, $takeValue, 9));
            if (bccomp((string) $layers[$index]['qty'], '0', 9) === 0) {
                array_splice($layers, (int) $index, 1);
            }
        }
        if (bccomp($remaining, '0', 9) === 1) {
            if ((int) $entry['allow_negative_stock'] !== 1) {
                throw new InvalidArgumentException('Inventory negative stock is not allowed for this effect.');
            }
            $negativeValue = yovel_admin_inventory_decimal(bcmul($remaining, $fallback, 9));
            $layers[] = [
                'qty' => yovel_admin_inventory_decimal(bcsub('0', $remaining, 9)),
                'value' => yovel_admin_inventory_decimal(bcsub('0', $negativeValue, 9)),
                'source_entry_key' => $entry['ledger_entry_key'],
                'posting_datetime' => $entry['posting_datetime'],
            ];
            $consumed = yovel_admin_inventory_decimal(bcadd($consumed, $negativeValue, 9));
        }
        $difference = yovel_admin_inventory_decimal(bcsub('0', $consumed, 9));
        $absoluteQty = yovel_admin_inventory_decimal(bcsub('0', $qty, 9));
            $outgoingRate = yovel_admin_inventory_ledger_rate($consumed, $absoluteQty);
    } else {
        if ($adjustment === null || bccomp($qtyBefore, '0', 9) === 0) {
            throw new InvalidArgumentException('A zero-quantity Inventory effect requires a value adjustment and non-zero stock.');
        }
        $difference = $adjustment;
    }

    if ($forced !== null) {
        $difference = $forced;
    }
    $expectedValue = yovel_admin_inventory_decimal(bcadd($valueBefore, $difference, 9));
    [$layerQty, $layerValue] = yovel_admin_inventory_ledger_queue_totals($layers);
    $queueCorrection = yovel_admin_inventory_decimal(bcsub($expectedValue, $layerValue, 9));
    if (bccomp($queueCorrection, '0', 9) !== 0) {
        if ($layers === []) {
            if (bccomp($newQty, '0', 9) !== 0) {
                $layers[] = ['qty' => $newQty, 'value' => $expectedValue, 'source_entry_key' => $entry['ledger_entry_key'], 'posting_datetime' => $entry['posting_datetime']];
            } elseif (bccomp($expectedValue, '0', 9) !== 0) {
                throw new RuntimeException('Inventory stock value cannot remain when quantity is zero.');
            }
        } else {
            $index = array_key_last($layers);
            $layers[$index]['value'] = yovel_admin_inventory_decimal(bcadd((string) $layers[$index]['value'], $queueCorrection, 9));
        }
    }
    if ($method === 'MOVING_AVERAGE') {
        $layers = bccomp($newQty, '0', 9) === 0 ? [] : [[
            'qty' => $newQty,
            'value' => $expectedValue,
            'source_entry_key' => $entry['ledger_entry_key'],
            'posting_datetime' => $entry['posting_datetime'],
        ]];
    }
    $queue = yovel_admin_inventory_ledger_queue($layers);
    [$queueQty, $queueValue] = yovel_admin_inventory_ledger_queue_totals($queue['layers']);
    if (bccomp($queueQty, $newQty, 9) !== 0 || bccomp($queueValue, $expectedValue, 9) !== 0) {
        throw new RuntimeException('Inventory valuation queue invariant failed.');
    }
    $rate = yovel_admin_inventory_ledger_rate($expectedValue, $newQty);
    return [
        'actual_qty' => $qty, 'qty_before' => $qtyBefore, 'qty_after' => $newQty,
        'stock_value_before' => $valueBefore, 'stock_value' => $expectedValue,
        'stock_value_difference' => $difference, 'incoming_rate' => $incoming ?? '0.000000000',
        'outgoing_rate' => $outgoingRate, 'valuation_rate' => $rate,
        'queue' => $queue,
    ];
}

function yovel_admin_inventory_replay_partition($db, array $company, string $itemKey, string $warehouseKey, array $dimensions, string $fromPostingDatetime): array
{
    if (!$db instanceof ADOConnection) {
        throw new InvalidArgumentException('Inventory ledger replay requires an ADODB connection.');
    }
    [$companyKey, $hash] = yovel_admin_inventory_ledger_company($company);
    $from = yovel_admin_inventory_ledger_datetime($fromPostingDatetime, 'Repost datetime');
    $tuple = yovel_admin_inventory_dimension_tuple($company, $dimensions);
    $existing = $db->GetRow(
        'SELECT valuation_method,currency_code FROM project_company_inventory_ledger_partition WHERE company_key_hash=? AND item_key=? AND warehouse_key=? AND dimensions_checksum=?',
        [$hash, $itemKey, $warehouseKey, $tuple['dimensions_checksum']]
    );
    if (!is_array($existing) || $existing === []) {
        throw new InvalidArgumentException('Inventory ledger partition does not exist.');
    }
    $partition = yovel_admin_inventory_ledger_partition($db, $company, null, $itemKey, $warehouseKey, $tuple['dimensions'], (string) $existing['valuation_method'], (string) $existing['currency_code']);
    $closing = $db->GetRow(
        'SELECT * FROM project_company_inventory_stock_closing WHERE company_key_hash=? AND partition_key=? AND closing_datetime<? ORDER BY closing_datetime DESC,x_id DESC LIMIT 1',
        [$hash, $partition['partition_key'], $from]
    );
    $baselineDate = is_array($closing) && $closing !== [] ? (string) $closing['closing_datetime'] : null;
    $qty = is_array($closing) && $closing !== [] ? yovel_admin_inventory_decimal((string) $closing['qty_after']) : '0.000000000';
    $value = is_array($closing) && $closing !== [] ? yovel_admin_inventory_decimal((string) $closing['stock_value']) : '0.000000000';
    $layers = is_array($closing) && $closing !== [] ? json_decode((string) $closing['queue_json'], true, 512, JSON_THROW_ON_ERROR) : [];
    $entries = $db->GetAll(
        'SELECT * FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND partition_key=?' . ($baselineDate !== null ? ' AND posting_datetime>?' : '') . ' ORDER BY posting_datetime,x_id,effect_index',
        $baselineDate !== null ? [$hash, $partition['partition_key'], $baselineDate] : [$hash, $partition['partition_key']]
    );
    if (!is_array($entries)) {
        throw new RuntimeException('Inventory ledger entries could not be loaded for replay.');
    }
    $version = (int) $partition['state_version'] + 1;
    $runKey = bx_uuid();
    $states = [];
    foreach ($entries as $entry) {
        $calculation = yovel_admin_inventory_ledger_apply($entry, $layers, $qty, $value);
        $stateKey = bx_uuid();
        yovel_admin_inventory_execute(
            $db,
            'INSERT INTO project_company_inventory_ledger_state (ledger_state_key,company_key,company_key_hash,partition_key,ledger_entry_key,item_key,warehouse_key,replay_version,actual_qty,qty_before,qty_after,stock_value_before,stock_value,stock_value_difference,incoming_rate,outgoing_rate,valuation_rate,valuation_method,currency_code,queue_json,queue_checksum,source_timestamp,repost_run_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$stateKey, $companyKey, $hash, $partition['partition_key'], $entry['ledger_entry_key'], $itemKey, $warehouseKey, $version, $calculation['actual_qty'], $calculation['qty_before'], $calculation['qty_after'], $calculation['stock_value_before'], $calculation['stock_value'], $calculation['stock_value_difference'], $calculation['incoming_rate'], $calculation['outgoing_rate'], $calculation['valuation_rate'], $entry['valuation_method'], $entry['currency_code'], $calculation['queue']['json'], $calculation['queue']['checksum'], $entry['posting_datetime'], $runKey],
            'Inventory ledger state append'
        );
        $state = $db->GetRow('SELECT * FROM project_company_inventory_ledger_state WHERE company_key_hash=? AND ledger_state_key=?', [$hash, $stateKey]);
        yovel_admin_inventory_assert_readback(
            ['ledger_entry_key' => $entry['ledger_entry_key'], 'qty_after' => $calculation['qty_after'], 'stock_value' => $calculation['stock_value'], 'queue_checksum' => $calculation['queue']['checksum']],
            is_array($state) ? $state : [],
            ['ledger_entry_key', 'qty_after', 'stock_value', 'queue_checksum'],
            'Inventory ledger state'
        );
        $states[] = $state;
        $qty = $calculation['qty_after'];
        $value = $calculation['stock_value'];
        $layers = $calculation['queue']['layers'];
    }
    $queue = yovel_admin_inventory_ledger_queue($layers);
    $latest = $entries === [] ? $baselineDate : (string) $entries[array_key_last($entries)]['posting_datetime'];
    $rate = yovel_admin_inventory_ledger_rate($value, $qty);
    $resultChecksum = hash('sha256', json_encode([$version, $qty, $value, $queue['checksum'], $latest], JSON_THROW_ON_ERROR));
    yovel_admin_inventory_execute(
        $db,
        'INSERT INTO project_company_inventory_repost_run (repost_run_key,company_key,company_key_hash,partition_key,item_key,warehouse_key,from_posting_datetime,replay_version,entry_count,result_checksum,created_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$runKey, $companyKey, $hash, $partition['partition_key'], $itemKey, $warehouseKey, $from, $version, count($entries), $resultChecksum, $partition['updated_by_admin_key'] ?: null],
        'Inventory repost run append'
    );
    $run = $db->GetRow('SELECT * FROM project_company_inventory_repost_run WHERE company_key_hash=? AND repost_run_key=?', [$hash, $runKey]);
    yovel_admin_inventory_assert_readback(
        ['partition_key' => $partition['partition_key'], 'replay_version' => (string) $version, 'entry_count' => (string) count($entries), 'result_checksum' => $resultChecksum],
        is_array($run) ? $run : [],
        ['partition_key', 'replay_version', 'entry_count', 'result_checksum'],
        'Inventory repost run'
    );
    yovel_admin_inventory_execute(
        $db,
        'UPDATE project_company_inventory_ledger_partition SET state_version=?,qty_after=?,stock_value=?,valuation_rate=?,queue_json=?,queue_checksum=?,latest_posting_datetime=?,updated_by_admin_key=? WHERE company_key_hash=? AND partition_key=?',
        [$version, $qty, $value, $rate, $queue['json'], $queue['checksum'], $latest, $partition['updated_by_admin_key'] ?: null, $hash, $partition['partition_key']],
        'Inventory ledger partition state update'
    );
    $sourceActual = yovel_admin_inventory_decimal((string) $db->GetOne(
        "SELECT COALESCE(SUM(quantity),0) FROM project_company_inventory_bin_source WHERE company_key_hash=? AND bin_key=? AND source_status='ACTIVE' AND source_type='ACTUAL'",
        [$hash, $partition['bin_key']]
    ));
    if (bccomp($sourceActual, $qty, 9) !== 0) {
        throw new RuntimeException('Inventory ledger quantity does not match authoritative source rows.');
    }
    $settings = $db->GetRow('SELECT capacity_enforcement FROM project_company_inventory_stock_setting WHERE company_key_hash=?', [$hash]);
    $capacity = yovel_admin_inventory_decimal((string) $partition['warehouse']['capacity_qty']);
    if ((int) ($settings['capacity_enforcement'] ?? 1) === 1 && bccomp($capacity, '0', 9) === 1) {
        $otherActual = yovel_admin_inventory_decimal((string) $db->GetOne(
            'SELECT COALESCE(SUM(actual_qty),0) FROM project_company_inventory_bin WHERE company_key_hash=? AND warehouse_key=? AND bin_key<>?',
            [$hash, $warehouseKey, $partition['bin_key']]
        ));
        if (bccomp(bcadd($otherActual, $qty, 9), $capacity, 9) === 1) {
            throw new InvalidArgumentException('Inventory warehouse capacity would be exceeded.');
        }
    }
    yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_bin SET actual_qty=? WHERE company_key_hash=? AND bin_key=?', [$qty, $hash, $partition['bin_key']], 'Inventory ledger bin update');
    bx_audit('CREATE', 'project_company_inventory_repost_run', $runKey, ['company_key' => $companyKey, 'item_key' => $itemKey, 'warehouse_key' => $warehouseKey, 'from_posting_datetime' => $from, 'replay_version' => $version, 'result_checksum' => $resultChecksum], 'Inventory valuation partition was replayed deterministically.');
    return [
        'partition_key' => (string) $partition['partition_key'], 'repost_run_key' => $runKey,
        'replay_version' => $version, 'states' => $states, 'qty_after' => $qty,
        'stock_value' => $value, 'valuation_rate' => $rate,
        'valuation_method' => (string) $partition['valuation_method'], 'currency_code' => (string) $partition['currency_code'],
        'source_timestamp' => $latest,
    ];
}

function yovel_admin_inventory_post_ledger_effects($db, array $company, ?array $admin, array $voucher, array $effects): array
{
    if (!$db instanceof ADOConnection) {
        throw new InvalidArgumentException('Inventory ledger posting requires an ADODB connection.');
    }
    $scope = yovel_admin_inventory_scope($company, $admin);
    if ($effects === [] || !array_is_list($effects)) {
        throw new InvalidArgumentException('Inventory ledger effects must be a non-empty list.');
    }
    $voucherType = strtoupper(yovel_admin_inventory_ledger_text($voucher['voucher_type'] ?? '', 80, 'Voucher type'));
    $voucherKey = yovel_admin_inventory_ledger_text($voucher['voucher_key'] ?? '', 120, 'Voucher key');
    $posting = yovel_admin_inventory_ledger_datetime($voucher['posting_datetime'] ?? null);
    $method = yovel_admin_inventory_ledger_method($voucher['valuation_method'] ?? '');
    $currency = yovel_admin_inventory_ledger_currency($voucher['currency_code'] ?? '');
    $normalized = [];
    foreach ($effects as $offset => $effect) {
        if (!is_array($effect)) {
            throw new InvalidArgumentException('Inventory ledger effect is invalid.');
        }
        $itemKey = trim((string) ($effect['item_key'] ?? ''));
        $warehouseKey = trim((string) ($effect['warehouse_key'] ?? ''));
        if (!yovel_admin_is_uuid($itemKey) || !yovel_admin_is_uuid($warehouseKey)) {
            throw new InvalidArgumentException('Inventory ledger item or warehouse key is invalid.');
        }
        $index = filter_var($effect['effect_index'] ?? $offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($index === false) {
            throw new InvalidArgumentException('Inventory ledger effect index is invalid.');
        }
        $dimensions = is_array($effect['dimensions'] ?? null) ? $effect['dimensions'] : [];
        $tuple = yovel_admin_inventory_dimension_tuple($company, $dimensions);
        $qty = yovel_admin_inventory_decimal((string) ($effect['actual_qty'] ?? ''));
        $incoming = yovel_admin_inventory_ledger_optional_decimal($effect['incoming_rate'] ?? null);
        $fallback = yovel_admin_inventory_ledger_optional_decimal($effect['valuation_rate'] ?? null);
        $adjustment = yovel_admin_inventory_ledger_optional_decimal($effect['value_difference'] ?? null);
        $line = yovel_admin_inventory_ledger_text($effect['voucher_line_key'] ?? ('LINE-' . $index), 120, 'Voucher line key');
        $financeDimensions = is_array($effect['finance_dimensions'] ?? null) ? $effect['finance_dimensions'] : [];
        ksort($financeDimensions, SORT_STRING);
        $identity = [$voucherType, $voucherKey, $line, (int) $index, 'ORIGINAL'];
        $payload = [$identity, $posting, $itemKey, $warehouseKey, $tuple['dimensions'], $qty, $incoming, $fallback, $adjustment, (bool) ($effect['allow_negative_stock'] ?? false), $method, $currency, $financeDimensions];
        $normalized[] = [
            'item_key' => $itemKey, 'warehouse_key' => $warehouseKey, 'dimensions' => $tuple['dimensions'],
            'dimensions_json' => $tuple['dimensions_json'], 'dimensions_checksum' => $tuple['dimensions_checksum'],
            'effect_index' => (int) $index, 'voucher_line_key' => $line, 'actual_qty' => $qty,
            'incoming_rate' => $incoming, 'fallback_valuation_rate' => $fallback, 'value_adjustment' => $adjustment,
            'forced_value_difference' => null, 'allow_negative_stock' => (bool) ($effect['allow_negative_stock'] ?? false) ? 1 : 0,
            'finance_dimensions_json' => json_encode($financeDimensions, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'entry_kind' => 'ORIGINAL', 'reversal_of_entry_key' => null, 'reversal_reason' => '',
            'idempotency_key' => hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR)),
            'payload_checksum' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        ];
    }
    usort($normalized, static fn (array $a, array $b): int => [$a['item_key'], $a['warehouse_key'], $a['dimensions_checksum'], $a['effect_index']] <=> [$b['item_key'], $b['warehouse_key'], $b['dimensions_checksum'], $b['effect_index']]);
    $partitions = [];
    foreach ($normalized as $effect) {
        $tupleKey = implode('|', [$effect['item_key'], $effect['warehouse_key'], $effect['dimensions_checksum']]);
        $partitions[$tupleKey] ??= yovel_admin_inventory_ledger_partition($db, $company, $scope[2], $effect['item_key'], $effect['warehouse_key'], $effect['dimensions'], $method, $currency);
    }
    $entryKeys = [];
    $earliest = [];
    $allExisting = true;
    foreach ($normalized as $effect) {
        $existing = $db->GetRow('SELECT * FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND idempotency_key=? FOR UPDATE', [$scope[1], $effect['idempotency_key']]);
        if (is_array($existing) && $existing !== []) {
            $actualChecksum = hash('sha256', json_encode([
                [$existing['voucher_type'], $existing['voucher_key'], $existing['voucher_line_key'], (int) $existing['effect_index'], $existing['entry_kind']],
                (string) $existing['posting_datetime'], (string) $existing['item_key'], (string) $existing['warehouse_key'],
                json_decode((string) $existing['dimensions_json'], true, 512, JSON_THROW_ON_ERROR),
                yovel_admin_inventory_decimal((string) $existing['actual_qty']),
                yovel_admin_inventory_ledger_optional_decimal($existing['incoming_rate']),
                yovel_admin_inventory_ledger_optional_decimal($existing['fallback_valuation_rate']),
                yovel_admin_inventory_ledger_optional_decimal($existing['value_adjustment']),
                (bool) $existing['allow_negative_stock'], $existing['valuation_method'], $existing['currency_code'],
                json_decode((string) $existing['finance_dimensions_json'], true, 512, JSON_THROW_ON_ERROR),
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (!hash_equals($effect['payload_checksum'], $actualChecksum)) {
                throw new InvalidArgumentException('Inventory ledger idempotency key was reused with a changed payload.');
            }
            $entryKeys[] = (string) $existing['ledger_entry_key'];
            continue;
        }
        $allExisting = false;
        $tupleKey = implode('|', [$effect['item_key'], $effect['warehouse_key'], $effect['dimensions_checksum']]);
        $partition = $partitions[$tupleKey];
        if ((string) ($partition['closing_datetime'] ?? '') !== '' && strcmp($posting, (string) $partition['closing_datetime']) <= 0) {
            throw new InvalidArgumentException('Inventory ledger posting datetime is in a closed period.');
        }
        $entryKey = bx_uuid();
        yovel_admin_inventory_execute(
            $db,
            'INSERT INTO project_company_inventory_stock_ledger_entry (ledger_entry_key,company_key,company_key_hash,partition_key,bin_key,item_key,warehouse_key,dimensions_json,dimensions_checksum,voucher_type,voucher_key,voucher_line_key,effect_index,posting_datetime,actual_qty,incoming_rate,fallback_valuation_rate,value_adjustment,forced_value_difference,allow_negative_stock,valuation_method,currency_code,finance_dimensions_json,entry_kind,reversal_of_entry_key,reversal_reason,idempotency_key,created_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$entryKey, $scope[0], $scope[1], $partition['partition_key'], $partition['bin_key'], $effect['item_key'], $effect['warehouse_key'], $effect['dimensions_json'], $effect['dimensions_checksum'], $voucherType, $voucherKey, $effect['voucher_line_key'], $effect['effect_index'], $posting, $effect['actual_qty'], $effect['incoming_rate'], $effect['fallback_valuation_rate'], $effect['value_adjustment'], null, $effect['allow_negative_stock'], $method, $currency, $effect['finance_dimensions_json'], 'ORIGINAL', null, '', $effect['idempotency_key'], $scope[2]],
            'Inventory stock ledger entry append'
        );
        yovel_admin_inventory_execute(
            $db,
            'INSERT INTO project_company_inventory_bin_source (bin_source_key,company_key,company_key_hash,bin_key,item_key,warehouse_key,dimensions_checksum,source_owner,source_key,source_line_key,source_type,source_status,quantity,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [bx_uuid(), $scope[0], $scope[1], $partition['bin_key'], $effect['item_key'], $effect['warehouse_key'], $effect['dimensions_checksum'], 'INVENTORY_LEDGER', $voucherKey, $entryKey, 'ACTUAL', 'ACTIVE', $effect['actual_qty'], $scope[2], $scope[2]],
            'Inventory ledger bin source append'
        );
        $readback = $db->GetRow('SELECT * FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND ledger_entry_key=?', [$scope[1], $entryKey]);
        yovel_admin_inventory_assert_readback(
            ['voucher_type' => $voucherType, 'voucher_key' => $voucherKey, 'actual_qty' => $effect['actual_qty'], 'idempotency_key' => $effect['idempotency_key']],
            is_array($readback) ? $readback : [],
            ['voucher_type', 'voucher_key', 'actual_qty', 'idempotency_key'],
            'Inventory stock ledger entry'
        );
        $entryKeys[] = $entryKey;
        $earliest[$tupleKey] = !isset($earliest[$tupleKey]) || strcmp($posting, $earliest[$tupleKey]) < 0 ? $posting : $earliest[$tupleKey];
    }
    if ($allExisting) {
        $states = [];
        foreach ($entryKeys as $entryKey) {
            $state = $db->GetRow('SELECT * FROM project_company_inventory_ledger_state WHERE company_key_hash=? AND ledger_entry_key=? ORDER BY replay_version DESC,x_id DESC LIMIT 1', [$scope[1], $entryKey]);
            if (is_array($state) && $state !== []) {
                $states[] = $state;
            }
        }
        return ['voucher_type' => $voucherType, 'voucher_key' => $voucherKey, 'entry_keys' => $entryKeys, 'states' => $states, 'partitions' => [], 'idempotent' => true];
    }
    yovel_admin_inventory_ledger_fault('after_entries');
    $replays = [];
    $statesByEntry = [];
    foreach ($earliest as $tupleKey => $from) {
        $effect = current(array_filter($normalized, static fn (array $row): bool => implode('|', [$row['item_key'], $row['warehouse_key'], $row['dimensions_checksum']]) === $tupleKey));
        $replay = yovel_admin_inventory_replay_partition($db, $company, $effect['item_key'], $effect['warehouse_key'], $effect['dimensions'], $from);
        $replays[] = $replay;
        foreach ($replay['states'] as $state) {
            $statesByEntry[(string) $state['ledger_entry_key']] = $state;
        }
    }
    yovel_admin_inventory_ledger_fault('after_replay');
    yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_item SET stock_activity_count=GREATEST(stock_activity_count,1) WHERE company_key_hash=? AND item_key IN (' . implode(',', array_fill(0, count(array_unique(array_column($normalized, 'item_key'))), '?')) . ')', array_merge([$scope[1]], array_values(array_unique(array_column($normalized, 'item_key')))), 'Inventory item ledger activity update');
    bx_audit('CREATE', 'project_company_inventory_stock_ledger_entry', $entryKeys[0], ['company_key' => $scope[0], 'voucher_type' => $voucherType, 'voucher_key' => $voucherKey, 'entry_keys' => $entryKeys, 'admin_key' => $scope[2]], 'Company administrator posted immutable Inventory stock ledger effects.');
    yovel_admin_inventory_ledger_fault('after_audit');
    return [
        'voucher_type' => $voucherType, 'voucher_key' => $voucherKey, 'posting_datetime' => $posting,
        'entry_keys' => $entryKeys, 'states' => array_values(array_filter(array_map(static fn (string $key): ?array => $statesByEntry[$key] ?? null, $entryKeys))),
        'partitions' => $replays, 'idempotent' => false,
    ];
}

function yovel_admin_inventory_reverse_ledger_effects($db, array $company, ?array $admin, array $voucher, string $reason): array
{
    if (!$db instanceof ADOConnection) {
        throw new InvalidArgumentException('Inventory ledger reversal requires an ADODB connection.');
    }
    $scope = yovel_admin_inventory_scope($company, $admin);
    $voucherType = strtoupper(yovel_admin_inventory_ledger_text($voucher['voucher_type'] ?? '', 80, 'Voucher type'));
    $voucherKey = yovel_admin_inventory_ledger_text($voucher['voucher_key'] ?? '', 120, 'Voucher key');
    $posting = yovel_admin_inventory_ledger_datetime($voucher['reversal_posting_datetime'] ?? $voucher['posting_datetime'] ?? null, 'Reversal posting datetime');
    $reason = trim($reason);
    if ($reason === '' || strlen($reason) > 500) {
        throw new InvalidArgumentException('Inventory reversal reason is invalid.');
    }
    $originals = $db->GetAll(
        "SELECT * FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_type=? AND voucher_key=? AND entry_kind='ORIGINAL' ORDER BY x_id FOR UPDATE",
        [$scope[1], $voucherType, $voucherKey]
    );
    if (!is_array($originals) || $originals === []) {
        throw new InvalidArgumentException('Inventory ledger voucher does not exist for this company.');
    }
    $partitions = [];
    foreach ($originals as $entry) {
        $dimensions = json_decode((string) $entry['dimensions_json'], true, 512, JSON_THROW_ON_ERROR);
        $tupleKey = implode('|', [$entry['item_key'], $entry['warehouse_key'], $entry['dimensions_checksum']]);
        $partitions[$tupleKey] ??= yovel_admin_inventory_ledger_partition($db, $company, $scope[2], (string) $entry['item_key'], (string) $entry['warehouse_key'], $dimensions, (string) $entry['valuation_method'], (string) $entry['currency_code']);
        if ((string) ($partitions[$tupleKey]['closing_datetime'] ?? '') !== '' && strcmp($posting, (string) $partitions[$tupleKey]['closing_datetime']) <= 0) {
            throw new InvalidArgumentException('Inventory ledger reversal datetime is in a closed period.');
        }
    }
    $entryKeys = [];
    $reversalOf = [];
    $earliest = [];
    foreach ($originals as $original) {
        $idempotency = hash('sha256', json_encode([$voucherType, $voucherKey, $original['ledger_entry_key'], 'REVERSAL'], JSON_THROW_ON_ERROR));
        $existing = $db->GetRow('SELECT * FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND idempotency_key=? FOR UPDATE', [$scope[1], $idempotency]);
        if (is_array($existing) && $existing !== []) {
            $entryKeys[] = (string) $existing['ledger_entry_key'];
            $reversalOf[] = (string) $original['ledger_entry_key'];
            continue;
        }
        $latestState = $db->GetRow('SELECT * FROM project_company_inventory_ledger_state WHERE company_key_hash=? AND ledger_entry_key=? ORDER BY replay_version DESC,x_id DESC LIMIT 1', [$scope[1], $original['ledger_entry_key']]);
        if (!is_array($latestState) || $latestState === []) {
            throw new RuntimeException('Inventory ledger effect has no valuation state to reverse.');
        }
        $qty = yovel_admin_inventory_decimal(bcsub('0', (string) $original['actual_qty'], 9));
        $forcedDifference = yovel_admin_inventory_decimal(bcsub('0', (string) $latestState['stock_value_difference'], 9));
        $incoming = bccomp($qty, '0', 9) === 1
            ? yovel_admin_inventory_ledger_rate($forcedDifference, $qty)
            : null;
        $dimensions = json_decode((string) $original['dimensions_json'], true, 512, JSON_THROW_ON_ERROR);
        $tupleKey = implode('|', [$original['item_key'], $original['warehouse_key'], $original['dimensions_checksum']]);
        $partition = $partitions[$tupleKey];
        $entryKey = bx_uuid();
        yovel_admin_inventory_execute(
            $db,
            'INSERT INTO project_company_inventory_stock_ledger_entry (ledger_entry_key,company_key,company_key_hash,partition_key,bin_key,item_key,warehouse_key,dimensions_json,dimensions_checksum,voucher_type,voucher_key,voucher_line_key,effect_index,posting_datetime,actual_qty,incoming_rate,fallback_valuation_rate,value_adjustment,forced_value_difference,allow_negative_stock,valuation_method,currency_code,finance_dimensions_json,entry_kind,reversal_of_entry_key,reversal_reason,idempotency_key,created_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$entryKey, $scope[0], $scope[1], $partition['partition_key'], $partition['bin_key'], $original['item_key'], $original['warehouse_key'], $original['dimensions_json'], $original['dimensions_checksum'], $voucherType, $voucherKey, $original['voucher_line_key'], $original['effect_index'], $posting, $qty, $incoming, $original['fallback_valuation_rate'], null, $forcedDifference, 1, $original['valuation_method'], $original['currency_code'], $original['finance_dimensions_json'], 'REVERSAL', $original['ledger_entry_key'], $reason, $idempotency, $scope[2]],
            'Inventory stock ledger reversal append'
        );
        yovel_admin_inventory_execute(
            $db,
            'INSERT INTO project_company_inventory_bin_source (bin_source_key,company_key,company_key_hash,bin_key,item_key,warehouse_key,dimensions_checksum,source_owner,source_key,source_line_key,source_type,source_status,quantity,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [bx_uuid(), $scope[0], $scope[1], $partition['bin_key'], $original['item_key'], $original['warehouse_key'], $original['dimensions_checksum'], 'INVENTORY_LEDGER', $voucherKey, $entryKey, 'ACTUAL', 'ACTIVE', $qty, $scope[2], $scope[2]],
            'Inventory ledger reversal source append'
        );
        $entryKeys[] = $entryKey;
        $reversalOf[] = (string) $original['ledger_entry_key'];
        $earliest[$tupleKey] = !isset($earliest[$tupleKey]) || strcmp($posting, $earliest[$tupleKey]) < 0 ? $posting : $earliest[$tupleKey];
    }
    $statesByEntry = [];
    foreach ($earliest as $tupleKey => $from) {
        $original = current(array_filter($originals, static fn (array $row): bool => implode('|', [$row['item_key'], $row['warehouse_key'], $row['dimensions_checksum']]) === $tupleKey));
        $replay = yovel_admin_inventory_replay_partition($db, $company, (string) $original['item_key'], (string) $original['warehouse_key'], json_decode((string) $original['dimensions_json'], true, 512, JSON_THROW_ON_ERROR), $from);
        foreach ($replay['states'] as $state) {
            $statesByEntry[(string) $state['ledger_entry_key']] = $state;
        }
    }
    bx_audit('CREATE', 'project_company_inventory_stock_ledger_entry', $entryKeys[0], ['company_key' => $scope[0], 'voucher_type' => $voucherType, 'voucher_key' => $voucherKey, 'reversal_of_entry_keys' => $reversalOf, 'reason' => $reason, 'admin_key' => $scope[2]], 'Company administrator appended Inventory stock ledger reversals.');
    return ['voucher_type' => $voucherType, 'voucher_key' => $voucherKey, 'entry_keys' => $entryKeys, 'reversal_of_entry_keys' => $reversalOf, 'states' => array_values(array_filter(array_map(static fn (string $key): ?array => $statesByEntry[$key] ?? null, $entryKeys)))];
}

function yovel_admin_inventory_stock_snapshot(array $company, string $itemKey, string $warehouseKey, string $postingDatetime, array $dimensions = []): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    if (!yovel_admin_is_uuid($itemKey) || !yovel_admin_is_uuid($warehouseKey)) {
        throw new InvalidArgumentException('Inventory valuation target is invalid.');
    }
    $asOf = yovel_admin_inventory_ledger_datetime($postingDatetime, 'Inventory snapshot datetime');
    $tuple = yovel_admin_inventory_dimension_tuple($company, $dimensions);
    $partition = bx_db()->GetRow('SELECT * FROM project_company_inventory_ledger_partition WHERE company_key_hash=? AND item_key=? AND warehouse_key=? AND dimensions_checksum=?', [$hash, $itemKey, $warehouseKey, $tuple['dimensions_checksum']]);
    $zero = [
        'partition_key' => '', 'item_key' => $itemKey, 'warehouse_key' => $warehouseKey,
        'dimensions' => $tuple['dimensions'], 'qty_after' => '0.000000000', 'stock_value' => '0.000000000',
        'valuation_rate' => '0.000000000', 'valuation_method' => '', 'currency_code' => '', 'source_timestamp' => null,
    ];
    if (!is_array($partition) || $partition === []) {
        return $zero;
    }
    $state = bx_db()->GetRow(
        'SELECT s.* FROM project_company_inventory_ledger_state s JOIN project_company_inventory_stock_ledger_entry e ON e.company_key_hash=s.company_key_hash AND e.ledger_entry_key=s.ledger_entry_key WHERE s.company_key_hash=? AND s.partition_key=? AND e.posting_datetime<=? ORDER BY s.replay_version DESC,e.posting_datetime DESC,e.x_id DESC LIMIT 1',
        [$hash, $partition['partition_key'], $asOf]
    );
    $closing = bx_db()->GetRow('SELECT * FROM project_company_inventory_stock_closing WHERE company_key_hash=? AND partition_key=? AND closing_datetime<=? ORDER BY closing_datetime DESC,x_id DESC LIMIT 1', [$hash, $partition['partition_key'], $asOf]);
    $source = is_array($state) ? $state : [];
    $timestamp = (string) ($state['source_timestamp'] ?? '');
    if (is_array($closing) && $closing !== [] && ($timestamp === '' || strcmp((string) $closing['closing_datetime'], $timestamp) >= 0)) {
        $source = $closing;
        $timestamp = (string) $closing['closing_datetime'];
    }
    if ($source === []) {
        return $zero + ['partition_key' => (string) $partition['partition_key'], 'valuation_method' => (string) $partition['valuation_method'], 'currency_code' => (string) $partition['currency_code']];
    }
    return [
        'partition_key' => (string) $partition['partition_key'], 'item_key' => $itemKey, 'warehouse_key' => $warehouseKey,
        'dimensions' => $tuple['dimensions'], 'qty_after' => yovel_admin_inventory_decimal((string) $source['qty_after']),
        'stock_value' => yovel_admin_inventory_decimal((string) $source['stock_value']),
        'valuation_rate' => yovel_admin_inventory_decimal((string) $source['valuation_rate']),
        'valuation_method' => (string) $partition['valuation_method'], 'currency_code' => (string) $partition['currency_code'],
        'source_timestamp' => $timestamp,
    ];
}

function yovel_admin_inventory_close_partition($db, array $company, ?array $admin, string $itemKey, string $warehouseKey, array $dimensions, string $closingDatetime): array
{
    if (!$db instanceof ADOConnection) {
        throw new InvalidArgumentException('Inventory stock closing requires an ADODB connection.');
    }
    $scope = yovel_admin_inventory_scope($company, $admin);
    $closingDate = yovel_admin_inventory_ledger_datetime($closingDatetime, 'Stock closing datetime');
    $tuple = yovel_admin_inventory_dimension_tuple($company, $dimensions);
    $row = $db->GetRow('SELECT valuation_method,currency_code FROM project_company_inventory_ledger_partition WHERE company_key_hash=? AND item_key=? AND warehouse_key=? AND dimensions_checksum=?', [$scope[1], $itemKey, $warehouseKey, $tuple['dimensions_checksum']]);
    if (!is_array($row) || $row === []) {
        throw new InvalidArgumentException('Inventory ledger partition does not exist.');
    }
    $partition = yovel_admin_inventory_ledger_partition($db, $company, $scope[2], $itemKey, $warehouseKey, $dimensions, (string) $row['valuation_method'], (string) $row['currency_code']);
    if ((string) ($partition['closing_datetime'] ?? '') !== '' && strcmp($closingDate, (string) $partition['closing_datetime']) < 0) {
        throw new InvalidArgumentException('Inventory stock closings must move forward.');
    }
    $snapshot = yovel_admin_inventory_stock_snapshot($company, $itemKey, $warehouseKey, $closingDate, $dimensions);
    $state = $db->GetRow(
        'SELECT s.queue_json,s.queue_checksum FROM project_company_inventory_ledger_state s JOIN project_company_inventory_stock_ledger_entry e ON e.company_key_hash=s.company_key_hash AND e.ledger_entry_key=s.ledger_entry_key WHERE s.company_key_hash=? AND s.partition_key=? AND e.posting_datetime<=? ORDER BY s.replay_version DESC,e.posting_datetime DESC,e.x_id DESC LIMIT 1',
        [$scope[1], $partition['partition_key'], $closingDate]
    );
    $queue = is_array($state) && $state !== [] ? ['json' => (string) $state['queue_json'], 'checksum' => (string) $state['queue_checksum']] : yovel_admin_inventory_ledger_queue([]);
    $checksum = hash('sha256', json_encode([$closingDate, $snapshot['qty_after'], $snapshot['stock_value'], $snapshot['valuation_rate'], $queue['checksum']], JSON_THROW_ON_ERROR));
    $existing = $db->GetRow('SELECT * FROM project_company_inventory_stock_closing WHERE company_key_hash=? AND partition_key=? AND closing_datetime=? FOR UPDATE', [$scope[1], $partition['partition_key'], $closingDate]);
    if (is_array($existing) && $existing !== []) {
        if (!hash_equals((string) $existing['snapshot_checksum'], $checksum)) {
            throw new RuntimeException('Inventory stock closing idempotency verification failed.');
        }
        return $existing;
    }
    $key = bx_uuid();
    yovel_admin_inventory_execute(
        $db,
        'INSERT INTO project_company_inventory_stock_closing (stock_closing_key,company_key,company_key_hash,partition_key,item_key,warehouse_key,dimensions_json,dimensions_checksum,closing_datetime,qty_after,stock_value,valuation_rate,valuation_method,currency_code,queue_json,queue_checksum,snapshot_checksum,created_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$key, $scope[0], $scope[1], $partition['partition_key'], $itemKey, $warehouseKey, $tuple['dimensions_json'], $tuple['dimensions_checksum'], $closingDate, $snapshot['qty_after'], $snapshot['stock_value'], $snapshot['valuation_rate'], $partition['valuation_method'], $partition['currency_code'], $queue['json'], $queue['checksum'], $checksum, $scope[2]],
        'Inventory stock closing append'
    );
    yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_ledger_partition SET closing_datetime=?,updated_by_admin_key=? WHERE company_key_hash=? AND partition_key=?', [$closingDate, $scope[2], $scope[1], $partition['partition_key']], 'Inventory stock closing pointer update');
    bx_audit('CREATE', 'project_company_inventory_stock_closing', $key, ['company_key' => $scope[0], 'item_key' => $itemKey, 'warehouse_key' => $warehouseKey, 'closing_datetime' => $closingDate, 'snapshot_checksum' => $checksum, 'admin_key' => $scope[2]], 'Company administrator closed an Inventory valuation partition.');
    $actual = $db->GetRow('SELECT * FROM project_company_inventory_stock_closing WHERE company_key_hash=? AND stock_closing_key=?', [$scope[1], $key]);
    yovel_admin_inventory_assert_readback(['closing_datetime' => $closingDate, 'qty_after' => $snapshot['qty_after'], 'stock_value' => $snapshot['stock_value'], 'snapshot_checksum' => $checksum], is_array($actual) ? $actual : [], ['closing_datetime', 'qty_after', 'stock_value', 'snapshot_checksum'], 'Inventory stock closing');
    return $actual;
}

function yovel_admin_inventory_item_valuation(array $company, string $itemKey, ?string $warehouseKey = null, ?string $asOfDate = null): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    if (!yovel_admin_is_uuid($itemKey) || ($warehouseKey !== null && !yovel_admin_is_uuid($warehouseKey))) {
        throw new InvalidArgumentException('Inventory item valuation target is invalid.');
    }
    $item = bx_db()->GetRow('SELECT item_key FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=?', [$hash, $itemKey]);
    if (!is_array($item) || $item === []) {
        throw new InvalidArgumentException('Inventory item does not belong to this company.');
    }
    $asOf = $asOfDate === null || trim($asOfDate) === ''
        ? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s.u')
        : (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($asOfDate)) === 1
            ? trim($asOfDate) . ' 23:59:59.999999'
            : yovel_admin_inventory_ledger_datetime($asOfDate, 'Inventory valuation date'));
    $sql = 'SELECT * FROM project_company_inventory_ledger_partition WHERE company_key_hash=? AND item_key=?';
    $params = [$hash, $itemKey];
    if ($warehouseKey !== null) {
        $sql .= ' AND warehouse_key=?';
        $params[] = $warehouseKey;
    }
    $partitions = bx_db()->GetAll($sql . ' ORDER BY warehouse_key,dimensions_checksum', $params);
    if (!is_array($partitions)) {
        throw new RuntimeException('Inventory valuation partitions could not be loaded.');
    }
    $qty = '0.000000000';
    $value = '0.000000000';
    $method = '';
    $currency = '';
    $timestamp = null;
    foreach ($partitions as $partition) {
        if (($method !== '' && $method !== (string) $partition['valuation_method']) || ($currency !== '' && $currency !== (string) $partition['currency_code'])) {
            throw new RuntimeException('Inventory item valuation metadata is inconsistent across warehouses.');
        }
        $method = (string) $partition['valuation_method'];
        $currency = (string) $partition['currency_code'];
        $snapshot = yovel_admin_inventory_stock_snapshot($company, $itemKey, (string) $partition['warehouse_key'], $asOf, json_decode((string) $partition['dimensions_json'], true, 512, JSON_THROW_ON_ERROR));
        $qty = yovel_admin_inventory_decimal(bcadd($qty, (string) $snapshot['qty_after'], 9));
        $value = yovel_admin_inventory_decimal(bcadd($value, (string) $snapshot['stock_value'], 9));
        if ($snapshot['source_timestamp'] !== null && ($timestamp === null || strcmp((string) $snapshot['source_timestamp'], $timestamp) > 0)) {
            $timestamp = (string) $snapshot['source_timestamp'];
        }
    }
    return [
        'item_key' => $itemKey, 'warehouse_key' => $warehouseKey, 'as_of_date' => $asOf,
        'actual_qty' => $qty, 'stock_value' => $value,
        'valuation_rate' => yovel_admin_inventory_ledger_rate($value, $qty),
        'currency_code' => $currency, 'valuation_method' => $method, 'source_timestamp' => $timestamp,
    ];
}

function yovel_admin_inventory_valuation_handoff(array $postingResult): array
{
    $entries = [];
    $postingDate = '';
    foreach (($postingResult['states'] ?? []) as $state) {
        if (!is_array($state)) {
            continue;
        }
        $ledgerEntry = bx_db()->GetRow('SELECT * FROM project_company_inventory_stock_ledger_entry WHERE ledger_entry_key=? LIMIT 1', [(string) ($state['ledger_entry_key'] ?? '')]);
        if (!is_array($ledgerEntry) || $ledgerEntry === []) {
            throw new RuntimeException('Inventory valuation handoff could not read back its ledger effect.');
        }
        $entryPostingDate = substr((string) $ledgerEntry['posting_datetime'], 0, 10);
        if ($postingDate === '') {
            $postingDate = $entryPostingDate;
        } elseif ($postingDate !== $entryPostingDate) {
            throw new RuntimeException('Inventory valuation handoff effects must share a posting date.');
        }
        $entries[] = [
            'ledger_entry_key' => (string) $ledgerEntry['ledger_entry_key'],
            'item_key' => (string) $ledgerEntry['item_key'],
            'warehouse_key' => (string) $ledgerEntry['warehouse_key'],
            'dimensions' => json_decode((string) $ledgerEntry['dimensions_json'], true, 512, JSON_THROW_ON_ERROR),
            'finance_dimensions' => json_decode((string) $ledgerEntry['finance_dimensions_json'], true, 512, JSON_THROW_ON_ERROR),
            'stock_value_difference' => yovel_admin_inventory_decimal((string) ($state['stock_value_difference'] ?? '0')),
            'currency_code' => (string) ($state['currency_code'] ?? ''),
            'valuation_method' => (string) ($state['valuation_method'] ?? ''),
            'source_timestamp' => (string) ($state['source_timestamp'] ?? ''),
            'entry_kind' => (string) $ledgerEntry['entry_kind'],
            'reversal_of_entry_key' => $ledgerEntry['reversal_of_entry_key'] !== null ? (string) $ledgerEntry['reversal_of_entry_key'] : null,
        ];
    }
    return [
        'status' => 'PENDING_FINANCE',
        'source' => ['voucher_type' => (string) ($postingResult['voucher_type'] ?? ''), 'voucher_key' => (string) ($postingResult['voucher_key'] ?? '')],
        'posting_date' => $postingDate,
        'entries' => $entries,
    ];
}
