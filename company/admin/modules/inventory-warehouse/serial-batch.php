<?php
declare(strict_types=1);

function yovel_admin_inventory_serial_batch_fault(string $point): void
{
    $fault = $GLOBALS['yovel_admin_inventory_serial_batch_fault'] ?? null;
    if (is_callable($fault)) {
        $fault($point);
    }
}

function yovel_admin_inventory_tracking_number(mixed $value, string $label): string
{
    $value = strtoupper(trim((string) $value));
    if ($value === '' || strlen($value) > 120 || preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $value) !== 1) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function yovel_admin_inventory_tracking_date(mixed $value, string $label, bool $required = false): ?string
{
    $value = trim((string) $value);
    if ($value === '' && !$required) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function yovel_admin_inventory_tracking_status(mixed $value, string $label): string
{
    $status = strtoupper(trim((string) $value));
    if (!in_array($status, ['ACTIVE', 'DISABLED'], true)) {
        throw new InvalidArgumentException($label . ' status is invalid.');
    }
    return $status;
}

function yovel_admin_inventory_tracking_item(ADOConnection $db, string $hash, string $itemKey, string $kind, bool $lock = true): array
{
    if (!yovel_admin_is_uuid($itemKey)) {
        throw new InvalidArgumentException('Inventory tracking item key is invalid.');
    }
    $row = $db->GetRow(
        'SELECT item_key,item_code,item_name,item_kind,item_status,has_serial_no,has_batch_no FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=?' . ($lock ? ' FOR UPDATE' : ''),
        [$hash, $itemKey]
    );
    $flag = $kind === 'SERIAL' ? 'has_serial_no' : 'has_batch_no';
    if (!is_array($row) || $row === [] || (string) $row['item_kind'] !== 'STOCK' || (string) $row['item_status'] !== 'ACTIVE' || (int) $row[$flag] !== 1) {
        throw new InvalidArgumentException('Inventory ' . strtolower($kind) . ' requires an active tracked stock item in this company.');
    }
    return $row;
}

function yovel_admin_save_inventory_batch(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_catalogue_schema();
    yovel_admin_inventory_serial_batch_schema();
    $key = trim((string) ($input['batch_key'] ?? ''));
    if ($key !== '' && !yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Inventory Batch key is invalid.');
    }
    $expected = [
        'item_key' => trim((string) ($input['item_key'] ?? '')),
        'batch_number' => yovel_admin_inventory_tracking_number($input['batch_number'] ?? '', 'Inventory Batch number'),
        'manufacturing_date' => yovel_admin_inventory_tracking_date($input['manufacturing_date'] ?? '', 'Inventory Batch manufacturing date'),
        'expiry_date' => yovel_admin_inventory_tracking_date($input['expiry_date'] ?? '', 'Inventory Batch expiry date'),
        'batch_status' => yovel_admin_inventory_tracking_status($input['batch_status'] ?? 'ACTIVE', 'Inventory Batch'),
    ];
    if ($expected['manufacturing_date'] !== null && $expected['expiry_date'] !== null && strcmp($expected['expiry_date'], $expected['manufacturing_date']) < 0) {
        throw new InvalidArgumentException('Inventory Batch expiry date cannot precede manufacturing date.');
    }
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($scope, $key, $expected): array {
        yovel_admin_inventory_lock_company($db, $scope);
        yovel_admin_inventory_tracking_item($db, $scope[1], $expected['item_key'], 'BATCH');
        $existing = $key !== ''
            ? $db->GetRow('SELECT * FROM project_company_inventory_batch WHERE company_key_hash=? AND batch_key=? FOR UPDATE', [$scope[1], $key])
            : $db->GetRow('SELECT * FROM project_company_inventory_batch WHERE company_key_hash=? AND batch_number=? FOR UPDATE', [$scope[1], $expected['batch_number']]);
        if ($key !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Inventory Batch does not belong to this company.');
        }
        if (is_array($existing) && $existing !== [] && (int) $existing['stock_activity_count'] > 0
            && ((string) $existing['item_key'] !== $expected['item_key'] || (string) $existing['batch_number'] !== $expected['batch_number'])) {
            throw new InvalidArgumentException('Inventory Batch identity is immutable after stock activity.');
        }
        if (is_array($existing) && $existing !== [] && (string) $existing['item_key'] !== $expected['item_key']) {
            throw new InvalidArgumentException('Inventory Batch number already belongs to another item.');
        }
        $savedKey = is_array($existing) && $existing !== [] ? (string) $existing['batch_key'] : ($key !== '' ? $key : bx_uuid());
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_batch SET manufacturing_date=?,expiry_date=?,batch_status=?,updated_by_admin_key=? WHERE company_key_hash=? AND batch_key=?', [$expected['manufacturing_date'], $expected['expiry_date'], $expected['batch_status'], $scope[2], $scope[1], $savedKey], 'Inventory Batch update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_batch (batch_key,company_key,company_key_hash,item_key,batch_number,manufacturing_date,expiry_date,batch_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?)', [$savedKey, $scope[0], $scope[1], $expected['item_key'], $expected['batch_number'], $expected['manufacturing_date'], $expected['expiry_date'], $expected['batch_status'], $scope[2], $scope[2]], 'Inventory Batch create');
        }
        bx_audit(is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE', 'project_company_inventory_batch', $savedKey, $expected + ['company_key' => $scope[0], 'admin_key' => $scope[2]], 'Company administrator saved an Inventory Batch.');
        $actual = $db->GetRow('SELECT * FROM project_company_inventory_batch WHERE company_key_hash=? AND batch_key=?', [$scope[1], $savedKey]);
        yovel_admin_inventory_assert_readback(array_map(static fn ($value): string => (string) ($value ?? ''), $expected), is_array($actual) ? array_map(static fn ($value): string => (string) ($value ?? ''), $actual) : [], array_keys($expected), 'Inventory Batch');
        yovel_admin_inventory_serial_batch_fault('batch_after_readback');
        return $actual;
    });
}

function yovel_admin_save_inventory_serial(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_catalogue_schema();
    yovel_admin_inventory_serial_batch_schema();
    $key = trim((string) ($input['serial_key'] ?? ''));
    if ($key !== '' && !yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Inventory Serial key is invalid.');
    }
    $expected = [
        'item_key' => trim((string) ($input['item_key'] ?? '')),
        'serial_number' => yovel_admin_inventory_tracking_number($input['serial_number'] ?? '', 'Inventory Serial number'),
        'serial_status' => yovel_admin_inventory_tracking_status($input['serial_status'] ?? 'ACTIVE', 'Inventory Serial'),
    ];
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($scope, $key, $expected): array {
        yovel_admin_inventory_lock_company($db, $scope);
        yovel_admin_inventory_tracking_item($db, $scope[1], $expected['item_key'], 'SERIAL');
        $existing = $key !== ''
            ? $db->GetRow('SELECT * FROM project_company_inventory_serial WHERE company_key_hash=? AND serial_key=? FOR UPDATE', [$scope[1], $key])
            : $db->GetRow('SELECT * FROM project_company_inventory_serial WHERE company_key_hash=? AND serial_number=? FOR UPDATE', [$scope[1], $expected['serial_number']]);
        if ($key !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Inventory Serial does not belong to this company.');
        }
        if (is_array($existing) && $existing !== [] && (int) $existing['stock_activity_count'] > 0
            && ((string) $existing['item_key'] !== $expected['item_key'] || (string) $existing['serial_number'] !== $expected['serial_number'])) {
            throw new InvalidArgumentException('Inventory Serial identity is immutable after stock activity.');
        }
        if (is_array($existing) && $existing !== [] && (string) $existing['item_key'] !== $expected['item_key']) {
            throw new InvalidArgumentException('Inventory Serial number already belongs to another item.');
        }
        $savedKey = is_array($existing) && $existing !== [] ? (string) $existing['serial_key'] : ($key !== '' ? $key : bx_uuid());
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_serial SET serial_status=?,updated_by_admin_key=? WHERE company_key_hash=? AND serial_key=?', [$expected['serial_status'], $scope[2], $scope[1], $savedKey], 'Inventory Serial update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_serial (serial_key,company_key,company_key_hash,item_key,serial_number,serial_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?)', [$savedKey, $scope[0], $scope[1], $expected['item_key'], $expected['serial_number'], $expected['serial_status'], $scope[2], $scope[2]], 'Inventory Serial create');
        }
        bx_audit(is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE', 'project_company_inventory_serial', $savedKey, $expected + ['company_key' => $scope[0], 'admin_key' => $scope[2]], 'Company administrator saved an Inventory Serial.');
        $actual = $db->GetRow('SELECT * FROM project_company_inventory_serial WHERE company_key_hash=? AND serial_key=?', [$scope[1], $savedKey]);
        yovel_admin_inventory_assert_readback($expected, is_array($actual) ? $actual : [], array_keys($expected), 'Inventory Serial');
        yovel_admin_inventory_serial_batch_fault('serial_after_readback');
        return $actual;
    });
}

function yovel_admin_inventory_batches(array $company, array $filters = []): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    $sql = 'SELECT b.*,i.item_code,i.item_name FROM project_company_inventory_batch b JOIN project_company_inventory_item i ON i.company_key_hash=b.company_key_hash AND i.item_key=b.item_key WHERE b.company_key_hash=?';
    $params = [$hash];
    if (trim((string) ($filters['item_key'] ?? '')) !== '') {
        $sql .= ' AND b.item_key=?'; $params[] = trim((string) $filters['item_key']);
    }
    return bx_db()->GetAll($sql . ' ORDER BY b.batch_number', $params);
}

function yovel_admin_inventory_serials(array $company, array $filters = []): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    $sql = 'SELECT s.*,i.item_code,i.item_name FROM project_company_inventory_serial s JOIN project_company_inventory_item i ON i.company_key_hash=s.company_key_hash AND i.item_key=s.item_key WHERE s.company_key_hash=?';
    $params = [$hash];
    if (trim((string) ($filters['item_key'] ?? '')) !== '') {
        $sql .= ' AND s.item_key=?'; $params[] = trim((string) $filters['item_key']);
    }
    return bx_db()->GetAll($sql . ' ORDER BY s.serial_number', $params);
}

function yovel_admin_inventory_tracking_resolve(ADOConnection $db, string $table, string $keyColumn, string $numberColumn, string $hash, array $reference): array
{
    $key = trim((string) ($reference[$keyColumn] ?? ''));
    $number = trim((string) ($reference[$numberColumn] ?? ''));
    $row = $key !== ''
        ? $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash=? AND {$keyColumn}=? FOR UPDATE", [$hash, $key])
        : $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash=? AND {$numberColumn}=? FOR UPDATE", [$hash, strtoupper($number)]);
    if (!is_array($row) || $row === []) {
        throw new InvalidArgumentException('Inventory tracking identity does not belong to this company.');
    }
    return $row;
}

function yovel_admin_inventory_validate_serial_batch_effects($db, array $company, array $item, array $effect): array
{
    if (!$db instanceof ADOConnection) {
        throw new InvalidArgumentException('Inventory tracking validation requires an ADODB connection.');
    }
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    $itemKey = (string) ($item['item_key'] ?? $effect['item_key'] ?? '');
    $target = yovel_admin_inventory_validate_stock_target($db, $company, $itemKey, (string) ($effect['warehouse_key'] ?? ''), true);
    $item = $db->GetRow('SELECT * FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=? FOR UPDATE', [$hash, $itemKey]);
    if (!is_array($item) || $item === [] || ((int) $item['has_serial_no'] !== 1 && (int) $item['has_batch_no'] !== 1)) {
        throw new InvalidArgumentException('Inventory bundle requires a serial- or batch-tracked item.');
    }
    $qty = yovel_admin_inventory_decimal((string) ($effect['actual_qty'] ?? ''));
    if (bccomp($qty, '0', 9) === 0) {
        throw new InvalidArgumentException('Inventory tracking quantity cannot be zero.');
    }
    $direction = strtoupper(trim((string) ($effect['direction'] ?? '')));
    $expectedDirection = bccomp($qty, '0', 9) === 1 ? 'IN' : 'OUT';
    if ($direction !== $expectedDirection) {
        throw new InvalidArgumentException('Inventory bundle direction does not match its quantity sign.');
    }
    $magnitude = bccomp($qty, '0', 9) === 1 ? $qty : yovel_admin_inventory_decimal(bcsub('0', $qty, 9));
    $posting = yovel_admin_inventory_ledger_datetime($effect['posting_datetime'] ?? null);
    $warehouseKey = (string) $effect['warehouse_key'];
    $serialRows = is_array($effect['serials'] ?? null) ? $effect['serials'] : [];
    $batchRows = is_array($effect['batches'] ?? null) ? $effect['batches'] : [];
    $entries = [];
    $seenSerials = [];
    $batchTotals = [];
    if ((int) $item['has_serial_no'] === 1) {
        if (bccomp($magnitude, (string) count($serialRows), 9) !== 0) {
            throw new InvalidArgumentException('Inventory serial count must equal the stock quantity.');
        }
        foreach ($serialRows as $row) {
            if (!is_array($row)) { throw new InvalidArgumentException('Inventory serial row is invalid.'); }
            $serial = yovel_admin_inventory_tracking_resolve($db, 'project_company_inventory_serial', 'serial_key', 'serial_number', $hash, $row);
            if ((string) $serial['item_key'] !== $itemKey || (string) $serial['serial_status'] !== 'ACTIVE' || isset($seenSerials[$serial['serial_key']])) {
                throw new InvalidArgumentException('Inventory serial identity is duplicated, disabled, or belongs to another item.');
            }
            $seenSerials[$serial['serial_key']] = true;
            $globalQty = yovel_admin_inventory_decimal((string) $db->GetOne('SELECT COALESCE(SUM(quantity),0) FROM project_company_inventory_trace_event WHERE company_key_hash=? AND serial_key=? AND posting_datetime<=?', [$hash, $serial['serial_key'], $posting]));
            $warehouseQty = yovel_admin_inventory_decimal((string) $db->GetOne('SELECT COALESCE(SUM(quantity),0) FROM project_company_inventory_trace_event WHERE company_key_hash=? AND serial_key=? AND warehouse_key=? AND posting_datetime<=?', [$hash, $serial['serial_key'], $warehouseKey, $posting]));
            if (($direction === 'IN' && bccomp($globalQty, '0', 9) !== 0) || ($direction === 'OUT' && bccomp($warehouseQty, '1', 9) !== 0)) {
                throw new InvalidArgumentException('Inventory serial is not available for this movement.');
            }
            $batch = null;
            if ((int) $item['has_batch_no'] === 1) {
                $batch = yovel_admin_inventory_tracking_resolve($db, 'project_company_inventory_batch', 'batch_key', 'batch_number', $hash, $row);
                if ((string) $batch['item_key'] !== $itemKey || (string) $batch['batch_status'] !== 'ACTIVE') {
                    throw new InvalidArgumentException('Inventory Batch belongs to another item or is disabled.');
                }
                $batchTotals[$batch['batch_key']] = bcadd($batchTotals[$batch['batch_key']] ?? '0', '1', 9);
            }
            $entries[] = ['serial' => $serial, 'batch' => $batch, 'quantity' => '1.000000000', 'expected_value_difference' => yovel_admin_inventory_ledger_optional_decimal($row['value_difference'] ?? null)];
        }
    } elseif ($serialRows !== []) {
        throw new InvalidArgumentException('Inventory item does not use serial tracking.');
    }
    if ((int) $item['has_batch_no'] === 1 && (int) $item['has_serial_no'] !== 1) {
        foreach ($batchRows as $row) {
            if (!is_array($row)) { throw new InvalidArgumentException('Inventory Batch row is invalid.'); }
            $lineQty = yovel_admin_inventory_decimal((string) ($row['quantity'] ?? ''));
            if (bccomp($lineQty, '0', 9) !== 1) {
                throw new InvalidArgumentException('Inventory Batch line quantity must be positive.');
            }
            $batch = yovel_admin_inventory_tracking_resolve($db, 'project_company_inventory_batch', 'batch_key', 'batch_number', $hash, $row);
            if ((string) $batch['item_key'] !== $itemKey || (string) $batch['batch_status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('Inventory Batch belongs to another item or is disabled.');
            }
            if (isset($batchTotals[$batch['batch_key']])) {
                throw new InvalidArgumentException('Inventory Batch rows must be unique.');
            }
            $batchTotals[$batch['batch_key']] = $lineQty;
            $entries[] = ['serial' => null, 'batch' => $batch, 'quantity' => $lineQty, 'expected_value_difference' => yovel_admin_inventory_ledger_optional_decimal($row['value_difference'] ?? null)];
        }
    }
    $batchTotal = '0.000000000';
    foreach ($batchTotals as $lineQty) { $batchTotal = bcadd($batchTotal, $lineQty, 9); }
    if ((int) $item['has_batch_no'] === 1 && bccomp($batchTotal, $magnitude, 9) !== 0) {
        throw new InvalidArgumentException('Inventory Batch quantities must equal the stock quantity.');
    }
    if ($direction === 'OUT') {
        foreach ($entries as $entry) {
            if ($entry['batch'] !== null) {
                $batch = $entry['batch'];
                $expired = (string) ($batch['expiry_date'] ?? '') !== '' && strcmp((string) $batch['expiry_date'], substr($posting, 0, 10)) < 0;
                if ($expired && empty($effect['expiry_override_authorized'])) {
                    throw new InvalidArgumentException('Inventory Batch is expired and requires an authorized override.');
                }
                if ($expired && trim((string) ($effect['expiry_override_reason'] ?? '')) === '') {
                    throw new InvalidArgumentException('Inventory expired Batch override requires a reason.');
                }
                $available = yovel_admin_inventory_decimal((string) $db->GetOne('SELECT COALESCE(SUM(quantity),0) FROM project_company_inventory_trace_event WHERE company_key_hash=? AND batch_key=? AND warehouse_key=? AND posting_datetime<=?', [$hash, $batch['batch_key'], $warehouseKey, $posting]));
                if (bccomp($available, (string) $entry['quantity'], 9) === -1) {
                    throw new InvalidArgumentException('Inventory Batch quantity is not available in this warehouse.');
                }
            }
        }
    }
    return ['item' => $item, 'warehouse' => $target['warehouse'], 'actual_qty' => $qty, 'magnitude' => $magnitude, 'direction' => $direction, 'posting_datetime' => $posting, 'entries' => $entries];
}

function yovel_admin_inventory_tracking_persist(ADOConnection $db, array $scope, array $company, array $voucher, array $normalized, array $ledger, string $kind = 'ORIGINAL', ?string $reversalOf = null): array
{
    $state = $ledger['states'][0] ?? null;
    if (!is_array($state)) { throw new RuntimeException('Inventory tracking bundle has no ledger valuation state.'); }
    $entryKey = (string) $state['ledger_entry_key'];
    $valueDifference = yovel_admin_inventory_decimal((string) $state['stock_value_difference']);
    $voucherType = strtoupper(yovel_admin_inventory_ledger_text($voucher['voucher_type'] ?? '', 80, 'Voucher type'));
    $voucherKey = yovel_admin_inventory_ledger_text($voucher['voucher_key'] ?? '', 120, 'Voucher key');
    $line = yovel_admin_inventory_ledger_text($voucher['voucher_line_key'] ?? 'LINE-0', 120, 'Voucher line key');
    $effectIndex = (int) ($voucher['effect_index'] ?? 0);
    $identity = hash('sha256', json_encode([$voucherType,$voucherKey,$line,$effectIndex,$kind,$reversalOf], JSON_THROW_ON_ERROR));
    $existing = $db->GetRow('SELECT * FROM project_company_inventory_serial_batch_bundle WHERE company_key_hash=? AND idempotency_key=? FOR UPDATE', [$scope[1], $identity]);
    if (is_array($existing) && $existing !== []) {
        return ['bundle' => $existing, 'entries' => $db->GetAll('SELECT * FROM project_company_inventory_serial_batch_entry WHERE company_key_hash=? AND bundle_key=? ORDER BY x_id', [$scope[1], $existing['bundle_key']]), 'ledger' => $ledger, 'idempotent' => true];
    }
    $tuple = yovel_admin_inventory_dimension_tuple($company, is_array($voucher['dimensions'] ?? null) ? $voucher['dimensions'] : []);
    $bundleKey = bx_uuid();
    $overrideReason = trim((string) ($voucher['expiry_override_reason'] ?? ''));
    yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_serial_batch_bundle (bundle_key,company_key,company_key_hash,item_key,warehouse_key,dimensions_json,dimensions_checksum,voucher_type,voucher_key,voucher_line_key,effect_index,posting_datetime,direction,actual_qty,ledger_entry_key,stock_value_difference,entry_kind,reversal_of_bundle_key,returned_against_bundle_key,expiry_override_authorized,expiry_override_reason,idempotency_key,created_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$bundleKey,$scope[0],$scope[1],$normalized['item']['item_key'],$voucher['warehouse_key'],$tuple['dimensions_json'],$tuple['dimensions_checksum'],$voucherType,$voucherKey,$line,$effectIndex,$normalized['posting_datetime'],$normalized['direction'],$normalized['actual_qty'],$entryKey,$valueDifference,$kind,$reversalOf,$voucher['returned_against_bundle_key'] ?? null,!empty($voucher['expiry_override_authorized']) ? 1 : 0,$overrideReason,$identity,$scope[2]], 'Inventory tracking bundle append');
    $remainingQty = $normalized['magnitude'];
    $remainingValue = $valueDifference;
    $savedEntries = [];
    foreach ($normalized['entries'] as $offset => $entry) {
        $signedQty = $normalized['direction'] === 'IN' ? $entry['quantity'] : yovel_admin_inventory_decimal(bcsub('0', (string) $entry['quantity'], 9));
        $lineValue = $offset === array_key_last($normalized['entries']) ? $remainingValue : yovel_admin_inventory_decimal(bcmul($valueDifference, bcdiv((string) $entry['quantity'], $normalized['magnitude'], 12), 9));
        if ($entry['expected_value_difference'] !== null && bccomp((string) $entry['expected_value_difference'], $lineValue, 9) !== 0) {
            throw new InvalidArgumentException('Inventory tracking valuation does not match its parent ledger effect.');
        }
        $remainingQty = yovel_admin_inventory_decimal(bcsub($remainingQty, (string) $entry['quantity'], 9));
        $remainingValue = yovel_admin_inventory_decimal(bcsub($remainingValue, $lineValue, 9));
        $bundleEntryKey = bx_uuid();
        $serialKey = $entry['serial']['serial_key'] ?? null;
        $batchKey = $entry['batch']['batch_key'] ?? null;
        yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_serial_batch_entry (bundle_entry_key,company_key,company_key_hash,bundle_key,item_key,warehouse_key,serial_key,batch_key,quantity,stock_value_difference,ledger_entry_key) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$bundleEntryKey,$scope[0],$scope[1],$bundleKey,$normalized['item']['item_key'],$voucher['warehouse_key'],$serialKey,$batchKey,$signedQty,$lineValue,$entryKey], 'Inventory tracking bundle entry append');
        yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_trace_event (trace_event_key,company_key,company_key_hash,bundle_key,bundle_entry_key,ledger_entry_key,item_key,warehouse_key,serial_key,batch_key,posting_datetime,event_kind,quantity,stock_value_difference) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [bx_uuid(),$scope[0],$scope[1],$bundleKey,$bundleEntryKey,$entryKey,$normalized['item']['item_key'],$voucher['warehouse_key'],$serialKey,$batchKey,$normalized['posting_datetime'],$kind,$signedQty,$lineValue], 'Inventory trace event append');
        $saved = $db->GetRow('SELECT * FROM project_company_inventory_serial_batch_entry WHERE company_key_hash=? AND bundle_entry_key=?', [$scope[1], $bundleEntryKey]);
        yovel_admin_inventory_assert_readback(['bundle_key'=>$bundleKey,'quantity'=>$signedQty,'stock_value_difference'=>$lineValue,'ledger_entry_key'=>$entryKey], is_array($saved) ? $saved : [], ['bundle_key','quantity','stock_value_difference','ledger_entry_key'], 'Inventory tracking entry');
        $savedEntries[] = $saved;
    }
    if (bccomp($remainingQty, '0', 9) !== 0 || bccomp($remainingValue, '0', 9) !== 0) {
        throw new RuntimeException('Inventory tracking quantity or valuation allocation invariant failed.');
    }
    yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_batch SET stock_activity_count=stock_activity_count+1 WHERE company_key_hash=? AND batch_key IN (SELECT batch_key FROM project_company_inventory_serial_batch_entry WHERE company_key_hash=? AND bundle_key=? AND batch_key IS NOT NULL)', [$scope[1],$scope[1],$bundleKey], 'Inventory Batch activity update');
    yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_serial SET stock_activity_count=stock_activity_count+1 WHERE company_key_hash=? AND serial_key IN (SELECT serial_key FROM project_company_inventory_serial_batch_entry WHERE company_key_hash=? AND bundle_key=? AND serial_key IS NOT NULL)', [$scope[1],$scope[1],$bundleKey], 'Inventory Serial activity update');
    bx_audit('CREATE', 'project_company_inventory_serial_batch_bundle', $bundleKey, ['company_key'=>$scope[0],'item_key'=>$normalized['item']['item_key'],'warehouse_key'=>$voucher['warehouse_key'],'voucher_key'=>$voucherKey,'actual_qty'=>$normalized['actual_qty'],'ledger_entry_key'=>$entryKey,'stock_value_difference'=>$valueDifference,'entry_kind'=>$kind,'admin_key'=>$scope[2]], 'Company administrator posted an immutable Inventory tracking bundle.');
    $bundle = $db->GetRow('SELECT * FROM project_company_inventory_serial_batch_bundle WHERE company_key_hash=? AND bundle_key=?', [$scope[1],$bundleKey]);
    yovel_admin_inventory_assert_readback(['actual_qty'=>$normalized['actual_qty'],'ledger_entry_key'=>$entryKey,'stock_value_difference'=>$valueDifference,'entry_kind'=>$kind], is_array($bundle) ? $bundle : [], ['actual_qty','ledger_entry_key','stock_value_difference','entry_kind'], 'Inventory tracking bundle');
    return ['bundle'=>$bundle,'entries'=>$savedEntries,'ledger'=>$ledger,'idempotent'=>false];
}

function yovel_admin_inventory_post_serial_batch_bundle($db, array $company, ?array $admin, array $voucher, array $bundle): array
{
    if (!$db instanceof ADOConnection) { throw new InvalidArgumentException('Inventory tracking posting requires an ADODB connection.'); }
    $scope = yovel_admin_inventory_scope($company, $admin);
    $voucher['warehouse_key'] = trim((string) ($bundle['warehouse_key'] ?? ''));
    $voucher['dimensions'] = is_array($bundle['dimensions'] ?? null) ? $bundle['dimensions'] : [];
    $voucher['expiry_override_authorized'] = !empty($bundle['expiry_override_authorized']);
    $voucher['expiry_override_reason'] = trim((string) ($bundle['expiry_override_reason'] ?? ''));
    $voucher['returned_against_bundle_key'] = trim((string) ($bundle['returned_against_bundle_key'] ?? '')) ?: null;
    $voucherType = strtoupper(yovel_admin_inventory_ledger_text($voucher['voucher_type'] ?? '', 80, 'Voucher type'));
    $voucherKey = yovel_admin_inventory_ledger_text($voucher['voucher_key'] ?? '', 120, 'Voucher key');
    $line = yovel_admin_inventory_ledger_text($voucher['voucher_line_key'] ?? 'LINE-0', 120, 'Voucher line key');
    $effectIndex = (int) ($voucher['effect_index'] ?? 0);
    $identity = hash('sha256', json_encode([$voucherType,$voucherKey,$line,$effectIndex,'ORIGINAL',null], JSON_THROW_ON_ERROR));
    $existing = $db->GetRow('SELECT * FROM project_company_inventory_serial_batch_bundle WHERE company_key_hash=? AND idempotency_key=? FOR UPDATE', [$scope[1],$identity]);
    if (is_array($existing) && $existing !== []) {
        $expectedPosting = yovel_admin_inventory_ledger_datetime($voucher['posting_datetime'] ?? null);
        $expectedQty = yovel_admin_inventory_decimal((string) ($bundle['actual_qty'] ?? ''));
        $expectedDirection = strtoupper(trim((string) ($bundle['direction'] ?? '')));
        if ((string) $existing['item_key'] !== trim((string) ($bundle['item_key'] ?? ''))
            || (string) $existing['warehouse_key'] !== $voucher['warehouse_key']
            || (string) $existing['posting_datetime'] !== $expectedPosting
            || (string) $existing['actual_qty'] !== $expectedQty
            || (string) $existing['direction'] !== $expectedDirection) {
            throw new InvalidArgumentException('Inventory tracking idempotency key was reused with a changed payload.');
        }
        $state = $db->GetRow('SELECT * FROM project_company_inventory_ledger_state WHERE company_key_hash=? AND ledger_entry_key=? ORDER BY replay_version DESC,x_id DESC LIMIT 1',[$scope[1],$existing['ledger_entry_key']]);
        return ['bundle'=>$existing,'entries'=>$db->GetAll('SELECT * FROM project_company_inventory_serial_batch_entry WHERE company_key_hash=? AND bundle_key=? ORDER BY x_id',[$scope[1],$existing['bundle_key']]),'ledger'=>['voucher_type'=>$voucherType,'voucher_key'=>$voucherKey,'entry_keys'=>[$existing['ledger_entry_key']],'states'=>is_array($state)&&$state!==[]?[$state]:[]],'idempotent'=>true];
    }
    $bundle['posting_datetime'] = $voucher['posting_datetime'] ?? null;
    $normalized = yovel_admin_inventory_validate_serial_batch_effects($db, $company, ['item_key'=>(string) ($bundle['item_key'] ?? '')], $bundle);
    $ledger = yovel_admin_inventory_post_ledger_effects($db, $company, $admin, $voucher, [[
        'effect_index'=>(int) ($voucher['effect_index'] ?? 0), 'voucher_line_key'=>(string) ($voucher['voucher_line_key'] ?? 'LINE-0'),
        'item_key'=>$normalized['item']['item_key'], 'warehouse_key'=>$voucher['warehouse_key'], 'dimensions'=>$voucher['dimensions'],
        'actual_qty'=>$normalized['actual_qty'], 'incoming_rate'=>$bundle['incoming_rate'] ?? null,
        'valuation_rate'=>$bundle['valuation_rate'] ?? null, 'allow_negative_stock'=>false,
        'finance_dimensions'=>is_array($bundle['finance_dimensions'] ?? null) ? $bundle['finance_dimensions'] : [],
    ]]);
    yovel_admin_inventory_serial_batch_fault('after_ledger');
    $result = yovel_admin_inventory_tracking_persist($db,$scope,$company,$voucher,$normalized,$ledger);
    yovel_admin_inventory_serial_batch_fault('after_readback');
    return $result;
}

function yovel_admin_inventory_reverse_serial_batch_bundle($db, array $company, ?array $admin, string $bundleKey, array $voucher, string $reason): array
{
    if (!$db instanceof ADOConnection || !yovel_admin_is_uuid($bundleKey)) { throw new InvalidArgumentException('Inventory tracking reversal target is invalid.'); }
    $scope = yovel_admin_inventory_scope($company, $admin);
    $original = $db->GetRow("SELECT * FROM project_company_inventory_serial_batch_bundle WHERE company_key_hash=? AND bundle_key=? AND entry_kind='ORIGINAL' FOR UPDATE", [$scope[1],$bundleKey]);
    if (!is_array($original) || $original === []) { throw new InvalidArgumentException('Inventory tracking bundle does not belong to this company.'); }
    $existing = $db->GetRow('SELECT * FROM project_company_inventory_serial_batch_bundle WHERE company_key_hash=? AND reversal_of_bundle_key=? FOR UPDATE', [$scope[1],$bundleKey]);
    if (is_array($existing) && $existing !== []) { return ['bundle'=>$existing,'entries'=>$db->GetAll('SELECT * FROM project_company_inventory_serial_batch_entry WHERE company_key_hash=? AND bundle_key=? ORDER BY x_id',[$scope[1],$existing['bundle_key']]),'ledger'=>[],'idempotent'=>true]; }
    if ((int) $db->GetOne("SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND voucher_type=? AND voucher_key=? AND entry_kind='ORIGINAL'", [$scope[1],$original['voucher_type'],$original['voucher_key']]) !== 1) {
        throw new RuntimeException('Inventory tracking reversal requires a dedicated source voucher effect.');
    }
    $ledger = yovel_admin_inventory_reverse_ledger_effects($db,$company,$admin,['voucher_type'=>$original['voucher_type'],'voucher_key'=>$original['voucher_key'],'posting_datetime'=>$original['posting_datetime'],'reversal_posting_datetime'=>$voucher['posting_datetime'] ?? null],$reason);
    $originalEntries = $db->GetAll('SELECT * FROM project_company_inventory_serial_batch_entry WHERE company_key_hash=? AND bundle_key=? ORDER BY x_id',[$scope[1],$bundleKey]);
    $normalizedEntries = [];
    foreach ($originalEntries as $entry) {
        $serial = $entry['serial_key'] ? $db->GetRow('SELECT * FROM project_company_inventory_serial WHERE company_key_hash=? AND serial_key=? FOR UPDATE',[$scope[1],$entry['serial_key']]) : null;
        $batch = $entry['batch_key'] ? $db->GetRow('SELECT * FROM project_company_inventory_batch WHERE company_key_hash=? AND batch_key=? FOR UPDATE',[$scope[1],$entry['batch_key']]) : null;
        $normalizedEntries[] = ['serial'=>$serial,'batch'=>$batch,'quantity'=>yovel_admin_inventory_decimal(ltrim((string) $entry['quantity'],'-')),'expected_value_difference'=>yovel_admin_inventory_decimal(bcsub('0',(string) $entry['stock_value_difference'],9))];
    }
    $qty = yovel_admin_inventory_decimal(bcsub('0',(string) $original['actual_qty'],9));
    $normalized = ['item'=>['item_key'=>$original['item_key']],'actual_qty'=>$qty,'magnitude'=>yovel_admin_inventory_decimal(ltrim($qty,'-')),'direction'=>bccomp($qty,'0',9)===1?'IN':'OUT','posting_datetime'=>yovel_admin_inventory_ledger_datetime($voucher['posting_datetime'] ?? null),'entries'=>$normalizedEntries];
    $voucher['warehouse_key'] = $original['warehouse_key'];
    $voucher['dimensions'] = json_decode((string) $original['dimensions_json'],true,512,JSON_THROW_ON_ERROR);
    return yovel_admin_inventory_tracking_persist($db,$scope,$company,$voucher,$normalized,$ledger,'REVERSAL',$bundleKey);
}

function yovel_admin_inventory_available_batches(array $company, string $itemKey, string $warehouseKey, string $postingDatetime): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    $asOf = yovel_admin_inventory_ledger_datetime($postingDatetime);
    yovel_admin_inventory_validate_stock_target(bx_db(),$company,$itemKey,$warehouseKey,false);
    $rows = bx_db()->GetAll("SELECT b.*,COALESCE(SUM(t.quantity),0) quantity FROM project_company_inventory_batch b LEFT JOIN project_company_inventory_trace_event t ON t.company_key_hash=b.company_key_hash AND t.batch_key=b.batch_key AND t.warehouse_key=? AND t.posting_datetime<=? WHERE b.company_key_hash=? AND b.item_key=? AND b.batch_status='ACTIVE' GROUP BY b.batch_key HAVING quantity>0 ORDER BY b.expiry_date IS NULL,b.expiry_date,b.batch_number",[$warehouseKey,$asOf,$hash,$itemKey]);
    $reserved = yovel_admin_inventory_bin_read(bx_db(),$company,$itemKey,$warehouseKey,[])['reserved'];
    foreach ($rows as &$row) {
        $qty = yovel_admin_inventory_decimal((string) $row['quantity']);
        $deduct = bccomp($reserved,'0',9)===1 ? (bccomp($reserved,$qty,9)>=0 ? $qty : $reserved) : '0.000000000';
        $row['available_qty'] = yovel_admin_inventory_decimal(bcsub($qty,$deduct,9));
        $row['quantity'] = $qty;
        $row['is_expired'] = (string) ($row['expiry_date'] ?? '') !== '' && strcmp((string) $row['expiry_date'],substr($asOf,0,10))<0;
        $reserved = yovel_admin_inventory_decimal(bcsub($reserved,$deduct,9));
    }
    unset($row);
    return array_values(array_filter($rows,static fn(array $row):bool=>bccomp((string)$row['available_qty'],'0',9)===1));
}

function yovel_admin_inventory_available_serials(array $company, string $itemKey, string $warehouseKey, string $postingDatetime): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    $asOf = yovel_admin_inventory_ledger_datetime($postingDatetime);
    yovel_admin_inventory_validate_stock_target(bx_db(),$company,$itemKey,$warehouseKey,false);
    $rows = bx_db()->GetAll("SELECT s.*,SUM(CASE WHEN t.warehouse_key=? THEN t.quantity ELSE 0 END) warehouse_qty,SUM(t.quantity) global_qty FROM project_company_inventory_serial s JOIN project_company_inventory_trace_event t ON t.company_key_hash=s.company_key_hash AND t.serial_key=s.serial_key AND t.posting_datetime<=? WHERE s.company_key_hash=? AND s.item_key=? AND s.serial_status='ACTIVE' GROUP BY s.serial_key HAVING warehouse_qty=1 AND global_qty=1 ORDER BY s.serial_number",[$warehouseKey,$asOf,$hash,$itemKey]);
    $reserved = (int) ceil((float) yovel_admin_inventory_bin_read(bx_db(),$company,$itemKey,$warehouseKey,[])['reserved']);
    return array_slice($rows,min($reserved,count($rows)));
}

function yovel_admin_inventory_trace(array $company, string $serialOrBatchKey): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    if (!yovel_admin_is_uuid($serialOrBatchKey)) { throw new InvalidArgumentException('Inventory trace identity is invalid.'); }
    $serial = bx_db()->GetRow('SELECT s.*,i.item_code,i.item_name FROM project_company_inventory_serial s JOIN project_company_inventory_item i ON i.company_key_hash=s.company_key_hash AND i.item_key=s.item_key WHERE s.company_key_hash=? AND s.serial_key=?',[$hash,$serialOrBatchKey]);
    $batch = bx_db()->GetRow('SELECT b.*,i.item_code,i.item_name FROM project_company_inventory_batch b JOIN project_company_inventory_item i ON i.company_key_hash=b.company_key_hash AND i.item_key=b.item_key WHERE b.company_key_hash=? AND b.batch_key=?',[$hash,$serialOrBatchKey]);
    $kind = is_array($serial)&&$serial!==[]?'SERIAL':(is_array($batch)&&$batch!==[]?'BATCH':'');
    if ($kind==='') { throw new InvalidArgumentException('Inventory trace identity does not belong to this company.'); }
    $column = $kind==='SERIAL'?'serial_key':'batch_key';
    $movements = bx_db()->GetAll("SELECT t.*,b.voucher_type,b.voucher_key,b.voucher_line_key,b.direction,b.reversal_of_bundle_key,b.returned_against_bundle_key,w.warehouse_code,w.warehouse_name FROM project_company_inventory_trace_event t JOIN project_company_inventory_serial_batch_bundle b ON b.company_key_hash=t.company_key_hash AND b.bundle_key=t.bundle_key JOIN project_company_inventory_warehouse w ON w.company_key_hash=t.company_key_hash AND w.warehouse_key=t.warehouse_key WHERE t.company_key_hash=? AND t.{$column}=? ORDER BY t.posting_datetime,t.x_id",[$hash,$serialOrBatchKey]);
    return ['kind'=>$kind,'record'=>$kind==='SERIAL'?$serial:$batch,'movements'=>$movements];
}
