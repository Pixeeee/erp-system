<?php
declare(strict_types=1);

function yovel_admin_inventory_reconciliation_fault(string $point): void
{
    $fault = $GLOBALS['yovel_admin_inventory_reconciliation_fault'] ?? null;
    if (is_callable($fault)) {
        $fault($point);
    }
}

function yovel_admin_inventory_reconciliation_schema(): void
{
    yovel_admin_inventory_catalogue_schema();
    yovel_admin_inventory_warehouse_control_schema();
    yovel_admin_inventory_ledger_schema();
    yovel_admin_inventory_serial_batch_schema();
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_inventory_stock_reconciliation (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reconciliation_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            reconciliation_number VARCHAR(120) NOT NULL,
            purpose ENUM('STOCK_RECONCILIATION','OPENING_STOCK') NOT NULL DEFAULT 'STOCK_RECONCILIATION',
            reconciliation_status ENUM('DRAFT','SUBMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            posting_datetime DATETIME(6) NOT NULL,
            valuation_method ENUM('FIFO','MOVING_AVERAGE','LIFO') NOT NULL,
            currency_code CHAR(3) NOT NULL,
            difference_account_key VARCHAR(120) NOT NULL DEFAULT '',
            cost_center_key VARCHAR(120) NOT NULL DEFAULT '',
            total_quantity_difference DECIMAL(24,9) NOT NULL DEFAULT 0,
            total_value_difference DECIMAL(24,9) NOT NULL DEFAULT 0,
            finance_handoff_status ENUM('PENDING','ACCEPTED','REJECTED') NOT NULL DEFAULT 'PENDING',
            finance_handoff_json LONGTEXT NOT NULL,
            amended_from_reconciliation_key CHAR(36) NULL,
            cancellation_reason VARCHAR(500) NOT NULL DEFAULT '',
            submitted_by_admin_key CHAR(36) NULL,
            cancelled_by_admin_key CHAR(36) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            UNIQUE KEY uq_inventory_reconciliation_number (company_key_hash,reconciliation_number),
            INDEX idx_inventory_reconciliation_status (company_key_hash,reconciliation_status,posting_datetime),
            INDEX idx_inventory_reconciliation_amendment (company_key_hash,amended_from_reconciliation_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_inventory_stock_reconciliation_line (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reconciliation_line_key CHAR(36) NOT NULL UNIQUE,
            reconciliation_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            item_key CHAR(36) NOT NULL,
            warehouse_key CHAR(36) NOT NULL,
            dimensions_json TEXT NOT NULL,
            dimensions_checksum CHAR(64) NOT NULL,
            current_qty DECIMAL(24,9) NOT NULL DEFAULT 0,
            current_stock_value DECIMAL(24,9) NOT NULL DEFAULT 0,
            current_valuation_rate DECIMAL(24,9) NOT NULL DEFAULT 0,
            target_qty DECIMAL(24,9) NOT NULL,
            target_stock_value DECIMAL(24,9) NOT NULL,
            target_valuation_rate DECIMAL(24,9) NOT NULL,
            quantity_difference DECIMAL(24,9) NOT NULL,
            value_difference DECIMAL(24,9) NOT NULL,
            serials_json LONGTEXT NOT NULL,
            batches_json LONGTEXT NOT NULL,
            line_status ENUM('DRAFT','SUBMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            UNIQUE KEY uq_inventory_reconciliation_tuple (company_key_hash,reconciliation_key,item_key,warehouse_key,dimensions_checksum),
            INDEX idx_inventory_reconciliation_line_item (company_key_hash,item_key,warehouse_key,line_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_inventory_execute($db, $statement, [], 'Inventory stock reconciliation schema update');
    }
    $auditTableNameColumn = (int) $db->GetOne('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [BUILDERX_DB_NAME, 'builder_audit_log', 'table_name']);
    if ($auditTableNameColumn === 0) {
        yovel_admin_inventory_execute($db, 'ALTER TABLE builder_audit_log ADD COLUMN table_name VARCHAR(80) NULL AFTER module', [], 'Builder audit compatibility schema update');
        yovel_admin_inventory_execute($db, 'UPDATE builder_audit_log SET table_name=module WHERE table_name IS NULL', [], 'Builder audit compatibility backfill');
    }
}

function yovel_admin_inventory_reconciliation_text(mixed $value, int $max, string $label): string
{
    $value = strtoupper(trim((string) $value));
    if ($value === '' || strlen($value) > $max || preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $value) !== 1) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function yovel_admin_inventory_reconciliation_purpose(mixed $value): string
{
    $purpose = strtoupper(trim((string) $value));
    if (!in_array($purpose, ['STOCK_RECONCILIATION', 'OPENING_STOCK'], true)) {
        throw new InvalidArgumentException('Stock reconciliation purpose is invalid.');
    }
    return $purpose;
}

function yovel_admin_inventory_reconciliation_json_list(mixed $value): array
{
    if (is_array($value)) {
        return array_is_list($value) ? $value : [];
    }
    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
}

function yovel_admin_inventory_reconciliation_line(array $company, ADOConnection $db, array $input, string $posting, string $method, string $currency): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    $itemKey = trim((string) ($input['item_key'] ?? ''));
    $warehouseKey = trim((string) ($input['warehouse_key'] ?? ''));
    if (!yovel_admin_is_uuid($itemKey) || !yovel_admin_is_uuid($warehouseKey)) {
        throw new InvalidArgumentException('Stock reconciliation item and warehouse are required.');
    }
    $item = $db->GetRow("SELECT * FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=? AND item_status='ACTIVE' AND item_kind='STOCK' FOR UPDATE", [$hash, $itemKey]);
    if (!is_array($item) || $item === []) {
        throw new InvalidArgumentException('Stock reconciliation requires an active stock item.');
    }
    $target = yovel_admin_inventory_validate_stock_target($db, $company, $itemKey, $warehouseKey, true);
    if ((int) ($target['warehouse']['is_group'] ?? 0) === 1) {
        throw new InvalidArgumentException('Stock reconciliation cannot target a group warehouse.');
    }
    $dimensions = is_array($input['dimensions'] ?? null) ? $input['dimensions'] : [];
    $tuple = yovel_admin_inventory_dimension_tuple($company, $dimensions);
    $snapshot = yovel_admin_inventory_stock_snapshot($company, $itemKey, $warehouseKey, $posting, $tuple['dimensions']);
    $currentQty = yovel_admin_inventory_decimal((string) $snapshot['qty_after']);
    $currentValue = yovel_admin_inventory_decimal((string) $snapshot['stock_value']);
    $currentRate = yovel_admin_inventory_decimal((string) $snapshot['valuation_rate']);
    $targetQty = yovel_admin_inventory_decimal((string) ($input['target_qty'] ?? ''));
    $targetValueInput = yovel_admin_inventory_ledger_optional_decimal($input['target_stock_value'] ?? null);
    $targetRateInput = yovel_admin_inventory_ledger_optional_decimal($input['target_valuation_rate'] ?? null);
    if ($targetValueInput !== null) {
        $targetValue = $targetValueInput;
    } else {
        $rate = $targetRateInput ?? $currentRate;
        $targetValue = yovel_admin_inventory_decimal(bcmul($targetQty, $rate, 9));
    }
    $targetRate = $targetRateInput ?? yovel_admin_inventory_ledger_rate($targetValue, $targetQty);
    $qtyDifference = yovel_admin_inventory_decimal(bcsub($targetQty, $currentQty, 9));
    $valueDifference = yovel_admin_inventory_decimal(bcsub($targetValue, $currentValue, 9));
    return [
        'item' => $item,
        'item_key' => $itemKey,
        'warehouse_key' => $warehouseKey,
        'dimensions' => $tuple['dimensions'],
        'dimensions_json' => $tuple['dimensions_json'],
        'dimensions_checksum' => $tuple['dimensions_checksum'],
        'current_qty' => $currentQty,
        'current_stock_value' => $currentValue,
        'current_valuation_rate' => $currentRate,
        'target_qty' => $targetQty,
        'target_stock_value' => $targetValue,
        'target_valuation_rate' => $targetRate,
        'quantity_difference' => $qtyDifference,
        'value_difference' => $valueDifference,
        'serials' => yovel_admin_inventory_reconciliation_json_list($input['serials'] ?? []),
        'batches' => yovel_admin_inventory_reconciliation_json_list($input['batches'] ?? []),
        'valuation_method' => $method,
        'currency_code' => $currency,
    ];
}

function yovel_admin_save_stock_reconciliation(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_reconciliation_schema();
    $key = trim((string) ($input['reconciliation_key'] ?? ''));
    if ($key !== '' && !yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Stock reconciliation key is invalid.');
    }
    $number = yovel_admin_inventory_reconciliation_text($input['reconciliation_number'] ?? '', 120, 'Stock reconciliation number');
    $purpose = yovel_admin_inventory_reconciliation_purpose($input['purpose'] ?? 'STOCK_RECONCILIATION');
    $posting = yovel_admin_inventory_ledger_datetime($input['posting_datetime'] ?? null);
    $method = yovel_admin_inventory_ledger_method($input['valuation_method'] ?? '');
    $currency = yovel_admin_inventory_ledger_currency($input['currency_code'] ?? '');
    $differenceAccount = trim((string) ($input['difference_account_key'] ?? ''));
    $costCenter = trim((string) ($input['cost_center_key'] ?? ''));
    $amendedFrom = trim((string) ($input['amended_from_reconciliation_key'] ?? ''));
    if ($amendedFrom !== '' && !yovel_admin_is_uuid($amendedFrom)) {
        throw new InvalidArgumentException('Amended stock reconciliation reference is invalid.');
    }
    $lineInputs = is_array($input['lines'] ?? null) && array_is_list($input['lines']) ? $input['lines'] : [];
    if ($lineInputs === []) {
        throw new InvalidArgumentException('Stock reconciliation requires at least one line.');
    }
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($company, $scope, $key, $number, $purpose, $posting, $method, $currency, $differenceAccount, $costCenter, $amendedFrom, $lineInputs): array {
        yovel_admin_inventory_lock_company($db, $scope);
        $existing = $key !== ''
            ? $db->GetRow('SELECT * FROM project_company_inventory_stock_reconciliation WHERE company_key_hash=? AND reconciliation_key=? FOR UPDATE', [$scope[1], $key])
            : $db->GetRow('SELECT * FROM project_company_inventory_stock_reconciliation WHERE company_key_hash=? AND reconciliation_number=? FOR UPDATE', [$scope[1], $number]);
        if ($key !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Stock reconciliation does not belong to this company.');
        }
        if (is_array($existing) && $existing !== [] && (string) $existing['reconciliation_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Submitted stock reconciliation records are immutable.');
        }
        if ($amendedFrom !== '') {
            $cancelled = $db->GetRow("SELECT reconciliation_key FROM project_company_inventory_stock_reconciliation WHERE company_key_hash=? AND reconciliation_key=? AND reconciliation_status='CANCELLED' FOR UPDATE", [$scope[1], $amendedFrom]);
            if (!is_array($cancelled) || $cancelled === []) {
                throw new InvalidArgumentException('Only a cancelled stock reconciliation can be amended.');
            }
        }
        $seen = [];
        $lines = [];
        $totalQty = '0.000000000';
        $totalValue = '0.000000000';
        foreach ($lineInputs as $inputLine) {
            if (!is_array($inputLine)) {
                throw new InvalidArgumentException('Stock reconciliation line is invalid.');
            }
            $line = yovel_admin_inventory_reconciliation_line($company, $db, $inputLine, $posting, $method, $currency);
            $tuple = implode('|', [$line['item_key'], $line['warehouse_key'], $line['dimensions_checksum']]);
            if (isset($seen[$tuple])) {
                throw new InvalidArgumentException('Duplicate stock reconciliation item, warehouse, and dimension tuple.');
            }
            $seen[$tuple] = true;
            if (bccomp($line['quantity_difference'], '0', 9) === 0 && bccomp($line['value_difference'], '0', 9) === 0) {
                continue;
            }
            $totalQty = yovel_admin_inventory_decimal(bcadd($totalQty, $line['quantity_difference'], 9));
            $totalValue = yovel_admin_inventory_decimal(bcadd($totalValue, $line['value_difference'], 9));
            $lines[] = $line;
        }
        $savedKey = is_array($existing) && $existing !== [] ? (string) $existing['reconciliation_key'] : ($key !== '' ? $key : bx_uuid());
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_stock_reconciliation SET reconciliation_number=?,purpose=?,posting_datetime=?,valuation_method=?,currency_code=?,difference_account_key=?,cost_center_key=?,total_quantity_difference=?,total_value_difference=?,amended_from_reconciliation_key=?,updated_by_admin_key=? WHERE company_key_hash=? AND reconciliation_key=?', [$number, $purpose, $posting, $method, $currency, $differenceAccount, $costCenter, $totalQty, $totalValue, $amendedFrom !== '' ? $amendedFrom : null, $scope[2], $scope[1], $savedKey], 'Inventory stock reconciliation update');
            yovel_admin_inventory_execute($db, 'DELETE FROM project_company_inventory_stock_reconciliation_line WHERE company_key_hash=? AND reconciliation_key=?', [$scope[1], $savedKey], 'Inventory stock reconciliation draft line refresh');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_stock_reconciliation (reconciliation_key,company_key,company_key_hash,reconciliation_number,purpose,reconciliation_status,posting_datetime,valuation_method,currency_code,difference_account_key,cost_center_key,total_quantity_difference,total_value_difference,finance_handoff_json,amended_from_reconciliation_key,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$savedKey, $scope[0], $scope[1], $number, $purpose, 'DRAFT', $posting, $method, $currency, $differenceAccount, $costCenter, $totalQty, $totalValue, '{}', $amendedFrom !== '' ? $amendedFrom : null, $scope[2], $scope[2]], 'Inventory stock reconciliation create');
        }
        foreach ($lines as $line) {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_stock_reconciliation_line (reconciliation_line_key,reconciliation_key,company_key,company_key_hash,item_key,warehouse_key,dimensions_json,dimensions_checksum,current_qty,current_stock_value,current_valuation_rate,target_qty,target_stock_value,target_valuation_rate,quantity_difference,value_difference,serials_json,batches_json,line_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [bx_uuid(), $savedKey, $scope[0], $scope[1], $line['item_key'], $line['warehouse_key'], $line['dimensions_json'], $line['dimensions_checksum'], $line['current_qty'], $line['current_stock_value'], $line['current_valuation_rate'], $line['target_qty'], $line['target_stock_value'], $line['target_valuation_rate'], $line['quantity_difference'], $line['value_difference'], json_encode($line['serials'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), json_encode($line['batches'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'DRAFT'], 'Inventory stock reconciliation line create');
        }
        bx_audit(is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE', 'project_company_inventory_stock_reconciliation', $savedKey, ['company_key' => $scope[0], 'reconciliation_number' => $number, 'purpose' => $purpose, 'total_quantity_difference' => $totalQty, 'total_value_difference' => $totalValue, 'admin_key' => $scope[2]], 'Company administrator saved an Inventory stock reconciliation.');
        return yovel_admin_inventory_stock_reconciliation($company, $savedKey, true);
    });
}

function yovel_admin_inventory_stock_reconciliation(array $company, string $reconciliationKey, bool $lock = false): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    if (!yovel_admin_is_uuid($reconciliationKey)) {
        throw new InvalidArgumentException('Stock reconciliation key is invalid.');
    }
    $suffix = $lock ? ' FOR UPDATE' : '';
    $row = bx_db()->GetRow('SELECT * FROM project_company_inventory_stock_reconciliation WHERE company_key_hash=? AND reconciliation_key=?' . $suffix, [$hash, $reconciliationKey]);
    if (!is_array($row) || $row === []) {
        throw new InvalidArgumentException('Stock reconciliation does not belong to this company.');
    }
    $lines = bx_db()->GetAll('SELECT l.*,i.item_code,i.item_name,i.has_serial_no,i.has_batch_no,w.warehouse_code,w.warehouse_name FROM project_company_inventory_stock_reconciliation_line l JOIN project_company_inventory_item i ON i.company_key_hash=l.company_key_hash AND i.item_key=l.item_key JOIN project_company_inventory_warehouse w ON w.company_key_hash=l.company_key_hash AND w.warehouse_key=l.warehouse_key WHERE l.company_key_hash=? AND l.reconciliation_key=? ORDER BY l.x_id', [$hash, $reconciliationKey]);
    foreach ($lines as &$line) {
        $line['dimensions'] = json_decode((string) $line['dimensions_json'], true, 512, JSON_THROW_ON_ERROR);
        $line['serials'] = json_decode((string) $line['serials_json'], true, 512, JSON_THROW_ON_ERROR);
        $line['batches'] = json_decode((string) $line['batches_json'], true, 512, JSON_THROW_ON_ERROR);
        $line['item'] = [
            'item_key' => (string) $line['item_key'],
            'item_code' => (string) ($line['item_code'] ?? ''),
            'item_name' => (string) ($line['item_name'] ?? ''),
            'has_serial_no' => (int) ($line['has_serial_no'] ?? 0),
            'has_batch_no' => (int) ($line['has_batch_no'] ?? 0),
        ];
    }
    unset($line);
    $row['lines'] = $lines;
    $row['finance_handoff'] = json_decode((string) ($row['finance_handoff_json'] ?? '{}'), true) ?: [];
    return $row;
}

function yovel_admin_inventory_stock_reconciliations(array $company): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    $rows = bx_db()->GetAll('SELECT * FROM project_company_inventory_stock_reconciliation WHERE company_key_hash=? ORDER BY posting_datetime DESC,x_id DESC LIMIT 100', [$hash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_submit_stock_reconciliation(array $company, ?array $admin, string $reconciliationKey): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_reconciliation_schema();
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($company, $scope, $reconciliationKey): array {
        yovel_admin_inventory_lock_company($db, $scope);
        $doc = yovel_admin_inventory_stock_reconciliation($company, $reconciliationKey, true);
        if ((string) $doc['reconciliation_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Stock reconciliation is immutable unless it is a draft.');
        }
        if (($doc['lines'] ?? []) === []) {
            throw new InvalidArgumentException('Stock reconciliation requires at least one persisted line.');
        }
        $effects = [];
        foreach ($doc['lines'] as $offset => $line) {
            $snapshot = yovel_admin_inventory_stock_snapshot($company, (string) $line['item_key'], (string) $line['warehouse_key'], (string) $doc['posting_datetime'], $line['dimensions']);
            $targetQty = yovel_admin_inventory_decimal((string) $line['target_qty']);
            $currentQty = yovel_admin_inventory_decimal((string) $snapshot['qty_after']);
            $currentValue = yovel_admin_inventory_decimal((string) $snapshot['stock_value']);
            $qtyDifference = yovel_admin_inventory_decimal(bcsub($targetQty, $currentQty, 9));
            $valueDifference = yovel_admin_inventory_decimal(bcsub((string) $line['target_stock_value'], $currentValue, 9));
            if ((string) $doc['purpose'] === 'OPENING_STOCK') {
                $activity = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_inventory_stock_ledger_entry WHERE company_key_hash=? AND item_key=? AND warehouse_key=? AND dimensions_checksum=?", [$scope[1], $line['item_key'], $line['warehouse_key'], $line['dimensions_checksum']]);
                if ($activity > 0) {
                    throw new InvalidArgumentException('Opening stock cannot be posted after existing activity.');
                }
            }
            $bin = yovel_admin_inventory_bin_read($db, $company, (string) $line['item_key'], (string) $line['warehouse_key'], $line['dimensions']);
            if (bccomp($targetQty, (string) $bin['reserved'], 9) === -1) {
                throw new InvalidArgumentException('Stock reconciliation target quantity is below reserved stock.');
            }
            if (bccomp($qtyDifference, '0', 9) === 0 && bccomp($valueDifference, '0', 9) === 0) {
                continue;
            }
            $common = [
                'effect_index' => $offset,
                'voucher_line_key' => (string) $line['reconciliation_line_key'],
                'item_key' => (string) $line['item_key'],
                'warehouse_key' => (string) $line['warehouse_key'],
                'dimensions' => $line['dimensions'],
                'actual_qty' => $qtyDifference,
                'valuation_rate' => (string) $line['target_valuation_rate'],
                'allow_negative_stock' => false,
                'finance_dimensions' => ['difference_account_key' => (string) $doc['difference_account_key'], 'cost_center_key' => (string) $doc['cost_center_key']],
            ];
            if ((int) $line['item']['has_serial_no'] === 1 || (int) $line['item']['has_batch_no'] === 1) {
                $bundle = $common + [
                    'direction' => bccomp($qtyDifference, '0', 9) === 1 ? 'IN' : 'OUT',
                    'serials' => $line['serials'],
                    'batches' => $line['batches'],
                    'incoming_rate' => (string) $line['target_valuation_rate'],
                ];
                yovel_admin_inventory_post_serial_batch_bundle($db, $company, ['admin_key' => $scope[2]], [
                    'voucher_type' => 'STOCK_RECONCILIATION',
                    'voucher_key' => $reconciliationKey,
                    'voucher_line_key' => (string) $line['reconciliation_line_key'],
                    'effect_index' => $offset,
                    'posting_datetime' => (string) $doc['posting_datetime'],
                    'valuation_method' => (string) $doc['valuation_method'],
                    'currency_code' => (string) $doc['currency_code'],
                ], $bundle);
                continue;
            }
            if (bccomp($qtyDifference, '0', 9) === 1) {
                $common['incoming_rate'] = (string) $line['target_valuation_rate'];
            }
            if (bccomp($qtyDifference, '0', 9) === 0) {
                $common['value_difference'] = $valueDifference;
            }
            $effects[] = $common;
        }
        if ($effects !== []) {
            yovel_admin_inventory_post_ledger_effects($db, $company, ['admin_key' => $scope[2]], [
                'voucher_type' => 'STOCK_RECONCILIATION',
                'voucher_key' => $reconciliationKey,
                'posting_datetime' => (string) $doc['posting_datetime'],
                'valuation_method' => (string) $doc['valuation_method'],
                'currency_code' => (string) $doc['currency_code'],
            ], $effects);
        }
        yovel_admin_inventory_reconciliation_fault('after_effects');
        $handoff = ['accepted' => false, 'reason' => 'Finance integration is not configured for this company-owned Inventory document.'];
        yovel_admin_inventory_execute($db, "UPDATE project_company_inventory_stock_reconciliation SET reconciliation_status='SUBMITTED',finance_handoff_status='PENDING',finance_handoff_json=?,submitted_by_admin_key=?,updated_by_admin_key=? WHERE company_key_hash=? AND reconciliation_key=?", [json_encode($handoff, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $scope[2], $scope[2], $scope[1], $reconciliationKey], 'Inventory stock reconciliation submit');
        yovel_admin_inventory_execute($db, "UPDATE project_company_inventory_stock_reconciliation_line SET line_status='SUBMITTED' WHERE company_key_hash=? AND reconciliation_key=?", [$scope[1], $reconciliationKey], 'Inventory stock reconciliation line submit');
        bx_audit('UPDATE', 'project_company_inventory_stock_reconciliation', $reconciliationKey, ['company_key' => $scope[0], 'status' => 'SUBMITTED', 'admin_key' => $scope[2]], 'Company administrator submitted an Inventory stock reconciliation.');
        return yovel_admin_inventory_stock_reconciliation($company, $reconciliationKey, false);
    });
}

function yovel_admin_cancel_stock_reconciliation(array $company, ?array $admin, string $reconciliationKey, string $reason): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    $reason = trim($reason);
    if ($reason === '' || strlen($reason) > 500) {
        throw new InvalidArgumentException('Stock reconciliation cancellation reason is invalid.');
    }
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($company, $scope, $reconciliationKey, $reason): array {
        yovel_admin_inventory_lock_company($db, $scope);
        $doc = yovel_admin_inventory_stock_reconciliation($company, $reconciliationKey, true);
        if ((string) $doc['reconciliation_status'] !== 'SUBMITTED') {
            throw new InvalidArgumentException('Only submitted stock reconciliation records can be cancelled.');
        }
        yovel_admin_inventory_reverse_ledger_effects($db, $company, ['admin_key' => $scope[2]], [
            'voucher_type' => 'STOCK_RECONCILIATION',
            'voucher_key' => $reconciliationKey,
            'posting_datetime' => (string) $doc['posting_datetime'],
            'reversal_posting_datetime' => (string) $doc['posting_datetime'],
        ], $reason);
        yovel_admin_inventory_execute($db, "UPDATE project_company_inventory_stock_reconciliation SET reconciliation_status='CANCELLED',cancellation_reason=?,cancelled_by_admin_key=?,updated_by_admin_key=? WHERE company_key_hash=? AND reconciliation_key=?", [$reason, $scope[2], $scope[2], $scope[1], $reconciliationKey], 'Inventory stock reconciliation cancel');
        yovel_admin_inventory_execute($db, "UPDATE project_company_inventory_stock_reconciliation_line SET line_status='CANCELLED' WHERE company_key_hash=? AND reconciliation_key=?", [$scope[1], $reconciliationKey], 'Inventory stock reconciliation line cancel');
        bx_audit('UPDATE', 'project_company_inventory_stock_reconciliation', $reconciliationKey, ['company_key' => $scope[0], 'status' => 'CANCELLED', 'reason' => $reason, 'admin_key' => $scope[2]], 'Company administrator cancelled an Inventory stock reconciliation.');
        return yovel_admin_inventory_stock_reconciliation($company, $reconciliationKey, false);
    });
}

function yovel_admin_inventory_integrity_diagnostics(array $company, array $filters = []): array
{
    [, $hash] = yovel_admin_inventory_ledger_company($company);
    $itemKeys = array_values(array_filter(array_map('strval', is_array($filters['item_keys'] ?? null) ? $filters['item_keys'] : []), static fn (string $key): bool => yovel_admin_is_uuid($key)));
    $itemClause = '';
    $params = [$hash];
    if ($itemKeys !== []) {
        $itemClause = ' AND item_key IN (' . implode(',', array_fill(0, count($itemKeys), '?')) . ')';
        $params = array_merge($params, $itemKeys);
    }
    $db = bx_db();
    $diagnostics = [];
    $partitions = $db->GetAll('SELECT * FROM project_company_inventory_ledger_partition WHERE company_key_hash=?' . $itemClause, $params);
    foreach (is_array($partitions) ? $partitions : [] as $partition) {
        $layers = json_decode((string) $partition['queue_json'], true);
        $queueQty = '0.000000000';
        $queueValue = '0.000000000';
        if (is_array($layers)) {
            foreach ($layers as $layer) {
                $queueQty = yovel_admin_inventory_decimal(bcadd($queueQty, (string) ($layer['qty'] ?? '0'), 9));
                $queueValue = yovel_admin_inventory_decimal(bcadd($queueValue, (string) ($layer['value'] ?? '0'), 9));
            }
        }
        if (bccomp($queueQty, (string) $partition['qty_after'], 9) !== 0) {
            $diagnostics[] = ['type' => 'FIFO_QUEUE_QTY', 'partition_key' => (string) $partition['partition_key']];
        }
        if (bccomp($queueValue, (string) $partition['stock_value'], 9) !== 0) {
            $diagnostics[] = ['type' => 'INCORRECT_STOCK_VALUE', 'partition_key' => (string) $partition['partition_key']];
        }
        $binQty = yovel_admin_inventory_decimal((string) $db->GetOne("SELECT COALESCE(SUM(quantity),0) FROM project_company_inventory_bin_source WHERE company_key_hash=? AND bin_key=? AND source_status='ACTIVE' AND source_type='ACTUAL'", [$hash, $partition['bin_key']]));
        $actualBin = yovel_admin_inventory_decimal((string) $db->GetOne('SELECT actual_qty FROM project_company_inventory_bin WHERE company_key_hash=? AND bin_key=?', [$hash, $partition['bin_key']]));
        if (bccomp($binQty, $actualBin, 9) !== 0 || bccomp($binQty, (string) $partition['qty_after'], 9) !== 0) {
            $diagnostics[] = ['type' => 'LEDGER_BIN_VARIANCE', 'partition_key' => (string) $partition['partition_key']];
        }
        $latestState = $db->GetRow('SELECT * FROM project_company_inventory_ledger_state WHERE company_key_hash=? AND partition_key=? ORDER BY replay_version DESC,x_id DESC LIMIT 1', [$hash, $partition['partition_key']]);
        if (is_array($latestState) && $latestState !== [] && (bccomp((string) $latestState['qty_after'], (string) $partition['qty_after'], 9) !== 0 || bccomp((string) $latestState['stock_value'], (string) $partition['stock_value'], 9) !== 0)) {
            $diagnostics[] = ['type' => 'LEDGER_EQUATION', 'partition_key' => (string) $partition['partition_key']];
        }
    }
    $batchRows = $db->GetAll('SELECT batch_key,item_key FROM project_company_inventory_batch WHERE company_key_hash=?' . $itemClause, $params);
    foreach (is_array($batchRows) ? $batchRows : [] as $batch) {
        $traceQty = yovel_admin_inventory_decimal((string) $db->GetOne('SELECT COALESCE(SUM(quantity),0) FROM project_company_inventory_trace_event WHERE company_key_hash=? AND batch_key=?', [$hash, $batch['batch_key']]));
        $entryQty = yovel_admin_inventory_decimal((string) $db->GetOne('SELECT COALESCE(SUM(quantity),0) FROM project_company_inventory_serial_batch_entry WHERE company_key_hash=? AND batch_key=?', [$hash, $batch['batch_key']]));
        if (bccomp($traceQty, $entryQty, 9) !== 0) {
            $diagnostics[] = ['type' => 'BATCH_QTY_VARIANCE', 'batch_key' => (string) $batch['batch_key']];
        }
    }
    $serialRows = $db->GetAll('SELECT serial_key,item_key FROM project_company_inventory_serial WHERE company_key_hash=?' . $itemClause, $params);
    foreach (is_array($serialRows) ? $serialRows : [] as $serial) {
        $traceQty = yovel_admin_inventory_decimal((string) $db->GetOne('SELECT COALESCE(SUM(quantity),0) FROM project_company_inventory_trace_event WHERE company_key_hash=? AND serial_key=?', [$hash, $serial['serial_key']]));
        if (!in_array($traceQty, ['0.000000000', '1.000000000'], true)) {
            $diagnostics[] = ['type' => 'SERIAL_COUNT_VARIANCE', 'serial_key' => (string) $serial['serial_key']];
        }
    }
    $bundleItemClause = $itemKeys !== [] ? ' AND b.item_key IN (' . implode(',', array_fill(0, count($itemKeys), '?')) . ')' : '';
    $orphaned = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_inventory_serial_batch_bundle b LEFT JOIN project_company_inventory_stock_ledger_entry e ON e.company_key_hash=b.company_key_hash AND e.ledger_entry_key=b.ledger_entry_key WHERE b.company_key_hash=? AND e.ledger_entry_key IS NULL" . $bundleItemClause, array_merge([$hash], $itemKeys));
    if ($orphaned > 0) {
        $diagnostics[] = ['type' => 'ORPHANED_BUNDLE_EFFECT', 'count' => $orphaned];
    }
    return $diagnostics;
}
