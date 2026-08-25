<?php
declare(strict_types=1);

function yovel_admin_buying_sourcing_dependency_definitions(): array
{
    return [
        'item_lookup' => [
            'owner' => 'inventory-warehouse',
            'owner_function' => 'yovel_admin_inventory_item',
            'signature' => 'yovel_admin_inventory_item(array $company, string $itemKey): ?array',
        ],
        'item_uom_resolve' => [
            'owner' => 'inventory-warehouse',
            'owner_function' => 'yovel_admin_resolve_item_uom',
            'signature' => 'yovel_admin_resolve_item_uom(array $company, string $itemKey, string $uomKey, string $qty): array',
        ],
        'material_request_snapshot' => [
            'owner' => 'inventory-warehouse',
            'owner_function' => 'yovel_admin_inventory_buying_snapshot',
            'signature' => 'yovel_admin_inventory_buying_snapshot(array $company, array $filters = []): array',
        ],
        'delivery_register' => [
            'owner' => 'operations',
            'owner_function' => 'yovel_admin_operations_register_procurement_delivery',
            'signature' => 'yovel_admin_operations_register_procurement_delivery(array $company, array $payload): array',
        ],
        'finance_purchase_calculation' => [
            'owner' => 'accounting-finance',
            'owner_function' => 'yovel_admin_finance_calculate_purchase_document',
            'signature' => 'yovel_admin_finance_calculate_purchase_document(array $company, array $payload): array',
        ],
    ];
}

function yovel_admin_buying_sourcing_dependency_gateway(array $overrides = []): array
{
    $definitions = yovel_admin_buying_sourcing_dependency_definitions();
    foreach ($overrides as $key => $callable) {
        if (!array_key_exists($key, $definitions)) {
            throw new InvalidArgumentException('Buying sourcing dependency is not allow-listed: ' . $key . '.');
        }
        if (!is_callable($callable)) {
            throw new InvalidArgumentException('Buying sourcing dependency override must be callable: ' . $key . '.');
        }
    }

    $gateway = [];
    foreach ($definitions as $key => $definition) {
        $ownerFunction = (string) $definition['owner_function'];
        $callable = array_key_exists($key, $overrides)
            ? $overrides[$key]
            : (function_exists($ownerFunction) ? Closure::fromCallable($ownerFunction) : null);
        $gateway[$key] = $definition + [
            'available' => is_callable($callable),
            'callable' => $callable,
            'source' => array_key_exists($key, $overrides) ? 'test-override' : 'owner-service',
        ];
    }
    return $gateway;
}

function yovel_admin_buying_sourcing_gateway(): array
{
    $overrides = is_array($GLOBALS['yovel_admin_buying_sourcing_dependency_overrides'] ?? null)
        ? $GLOBALS['yovel_admin_buying_sourcing_dependency_overrides']
        : [];
    return yovel_admin_buying_sourcing_dependency_gateway($overrides);
}

function yovel_admin_buying_sourcing_dependency_call(array $gateway, string $key, array $arguments): mixed
{
    if (!array_key_exists($key, yovel_admin_buying_sourcing_dependency_definitions())) {
        throw new InvalidArgumentException('Buying sourcing dependency is not allow-listed: ' . $key . '.');
    }
    $contract = $gateway[$key] ?? null;
    if (!is_array($contract) || empty($contract['available']) || !is_callable($contract['callable'] ?? null)) {
        throw new RuntimeException('The ' . $key . ' owner service is unavailable for Buying sourcing.');
    }
    return ($contract['callable'])(...$arguments);
}

function yovel_admin_buying_sourcing_date(mixed $value, string $label, bool $required = true): ?string
{
    $date = trim((string) $value);
    if ($date === '' && !$required) {
        return null;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException($label . ' must be a valid date.');
    }
    return $date;
}

function yovel_admin_buying_sourcing_quantity(mixed $value): string
{
    $quantity = trim((string) $value);
    if (preg_match('/^\d{1,12}(?:\.\d{1,6})?$/', $quantity) !== 1) {
        throw new InvalidArgumentException('RFQ quantity must be a non-negative number with up to six decimals.');
    }
    return number_format((float) $quantity, 6, '.', '');
}

function yovel_admin_buying_parse_rfq_suppliers(string $text): array
{
    $rows = [];
    foreach (preg_split('/\R/', trim($text)) ?: [] as $line) {
        if (trim($line) === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $rows[] = [
            'supplier_key' => $parts[0] ?? '',
            'email' => $parts[1] ?? '',
            'delivery_channel' => $parts[2] ?? 'EMAIL',
        ];
    }
    return $rows;
}

function yovel_admin_buying_parse_rfq_items(string $text): array
{
    $rows = [];
    foreach (preg_split('/\R/', trim($text)) ?: [] as $line) {
        if (trim($line) === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $rows[] = [
            'inventory_item_key' => $parts[0] ?? '',
            'uom_key' => $parts[1] ?? '',
            'quantity' => $parts[2] ?? '',
            'schedule_date' => $parts[3] ?? '',
            'warehouse_key' => $parts[4] ?? '',
            'material_request_key' => $parts[5] ?? '',
            'material_request_item_key' => $parts[6] ?? '',
        ];
    }
    return $rows;
}

function yovel_admin_buying_rfq_supplier_input(array $company, array $row, array $seen): array
{
    $supplierKey = trim((string) ($row['supplier_key'] ?? ''));
    if (!yovel_admin_is_uuid($supplierKey)) {
        throw new InvalidArgumentException('RFQ supplier key is invalid.');
    }
    $supplierKey = strtolower($supplierKey);
    if (isset($seen[$supplierKey])) {
        throw new InvalidArgumentException('RFQ contains a duplicate supplier.');
    }
    $supplier = yovel_admin_buying_supplier($company, $supplierKey);
    if (!is_array($supplier) || (string) ($supplier['supplier_status'] ?? '') !== 'ACTIVE') {
        throw new RuntimeException('RFQ suppliers must be active for this company.');
    }
    if ((int) ($supplier['on_hold'] ?? 0) === 1 && (string) ($supplier['hold_type'] ?? '') === 'ALL') {
        throw new RuntimeException('RFQ supplier is on a purchasing hold.');
    }
    $restriction = yovel_admin_buying_assert_supplier_purchase_allowed($company, $supplierKey, 'RFQ');
    $channel = strtoupper(trim((string) ($row['delivery_channel'] ?? 'EMAIL')));
    if (!in_array($channel, ['EMAIL', 'PORTAL', 'EDI'], true)) {
        throw new InvalidArgumentException('RFQ delivery channel is invalid.');
    }
    $email = strtolower(trim((string) ($row['email'] ?? $supplier['email'] ?? '')));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) {
        throw new InvalidArgumentException('RFQ supplier recipient email is required and must be valid.');
    }
    $contactKey = yovel_admin_buying_optional_uuid($row, 'contact_key', 'RFQ supplier contact');
    $childKey = yovel_admin_buying_optional_uuid($row, 'rfq_supplier_key', 'RFQ supplier row');
    return [
        'rfq_supplier_key' => $childKey,
        'supplier_key' => $supplierKey,
        'contact_key' => $contactKey,
        'email' => $email,
        'send_email' => $channel === 'EMAIL' ? 1 : 0,
        'delivery_channel' => $channel,
        'warning' => !empty($restriction['warning']),
    ];
}

function yovel_admin_buying_verify_material_request_reference(
    array $company,
    array $gateway,
    string $requestKey,
    string $itemKey,
    string $inventoryItemKey,
    string $uomKey
): void {
    $snapshot = yovel_admin_buying_sourcing_dependency_call($gateway, 'material_request_snapshot', [
        $company,
        ['material_request_keys' => [$requestKey], 'material_request_item_keys' => [$itemKey]],
    ]);
    $companyHash = strtolower((string) ($company['company_key_hash'] ?? ''));
    if (!is_array($snapshot)
        || (string) ($snapshot['contract'] ?? '') !== 'inventory.buying-snapshot.v1'
        || !hash_equals($companyHash, strtolower((string) ($snapshot['company_key_hash'] ?? '')))) {
        throw new RuntimeException('Inventory material-request snapshot scope could not be verified.');
    }
    foreach (is_array($snapshot['material_requests'] ?? null) ? $snapshot['material_requests'] : [] as $request) {
        if ((string) ($request['material_request_key'] ?? '') !== $requestKey) {
            continue;
        }
        foreach (is_array($request['items'] ?? null) ? $request['items'] : [] as $item) {
            if ((string) ($item['material_request_item_key'] ?? '') === $itemKey
                && (string) ($item['inventory_item_key'] ?? '') === $inventoryItemKey
                && (string) ($item['uom_key'] ?? '') === $uomKey) {
                return;
            }
        }
    }
    throw new InvalidArgumentException('RFQ material-request item reference was not found in Inventory.');
}

function yovel_admin_buying_rfq_item_input(
    array $company,
    array $row,
    int $lineNo,
    string $transactionDate,
    bool $allowZero,
    array $gateway
): array {
    $itemKey = trim((string) ($row['inventory_item_key'] ?? ''));
    $uomKey = trim((string) ($row['uom_key'] ?? ''));
    if (!yovel_admin_is_uuid($itemKey)) {
        throw new InvalidArgumentException('RFQ item key is invalid.');
    }
    if (!yovel_admin_is_uuid($uomKey)) {
        throw new InvalidArgumentException('RFQ item UOM key is invalid.');
    }
    $itemKey = strtolower($itemKey);
    $uomKey = strtolower($uomKey);
    $item = yovel_admin_buying_sourcing_dependency_call($gateway, 'item_lookup', [$company, $itemKey]);
    $companyHash = strtolower((string) ($company['company_key_hash'] ?? ''));
    if (!is_array($item)
        || !hash_equals($companyHash, strtolower((string) ($item['company_key_hash'] ?? '')))
        || (string) ($item['item_key'] ?? '') !== $itemKey
        || (string) ($item['item_status'] ?? '') !== 'ACTIVE') {
        throw new InvalidArgumentException('RFQ item was not found or is inactive in Inventory.');
    }
    $selectedUom = null;
    foreach (is_array($item['uoms'] ?? null) ? $item['uoms'] : [] as $uom) {
        if (strtolower((string) ($uom['uom_key'] ?? '')) === $uomKey) {
            $selectedUom = $uom;
            break;
        }
    }
    if (!is_array($selectedUom)) {
        throw new InvalidArgumentException('RFQ item UOM was not found in Inventory.');
    }
    $quantity = yovel_admin_buying_sourcing_quantity($row['quantity'] ?? '');
    if (!$allowZero && bccomp($quantity, '0.000000', 6) === 0) {
        throw new InvalidArgumentException('RFQ zero quantity is disabled in Buying Settings.');
    }
    $uomCode = strtoupper(trim((string) ($selectedUom['uom_code'] ?? '')));
    $resolved = yovel_admin_buying_sourcing_dependency_call($gateway, 'item_uom_resolve', [$company, $itemKey, $uomCode, $quantity]);
    if (!is_array($resolved)
        || (string) ($resolved['quantity'] ?? '') !== number_format((float) $quantity, 9, '.', '')
        || trim((string) ($resolved['conversion_factor'] ?? '')) === ''
        || trim((string) ($resolved['stock_uom_code'] ?? '')) === '') {
        throw new RuntimeException('Inventory UOM resolution response could not be verified.');
    }
    $scheduleDate = (string) yovel_admin_buying_sourcing_date($row['schedule_date'] ?? '', 'RFQ item schedule date');
    if ($scheduleDate < $transactionDate) {
        throw new InvalidArgumentException('RFQ item schedule date cannot precede the transaction date.');
    }
    $warehouseKey = yovel_admin_buying_optional_uuid($row, 'warehouse_key', 'RFQ warehouse');
    $requestKey = yovel_admin_buying_optional_uuid($row, 'material_request_key', 'RFQ material request');
    $requestItemKey = yovel_admin_buying_optional_uuid($row, 'material_request_item_key', 'RFQ material-request item');
    if (($requestKey === null) !== ($requestItemKey === null)) {
        throw new InvalidArgumentException('RFQ material-request header and item references must be provided together.');
    }
    if ($requestKey !== null && $requestItemKey !== null) {
        yovel_admin_buying_verify_material_request_reference($company, $gateway, $requestKey, $requestItemKey, $itemKey, $uomKey);
    }
    return [
        'rfq_item_key' => yovel_admin_buying_optional_uuid($row, 'rfq_item_key', 'RFQ item row'),
        'line_no' => $lineNo,
        'inventory_item_key' => $itemKey,
        'item_code_snapshot' => (string) yovel_admin_buying_text($item, 'item_code', 'Inventory item code', 120, true),
        'item_name_snapshot' => (string) yovel_admin_buying_text($item, 'item_name', 'Inventory item name', 220, true),
        'description' => yovel_admin_buying_text(
            ['description' => $row['description'] ?? $item['description'] ?? $item['item_description'] ?? ''],
            'description',
            'RFQ item description',
            4000
        ),
        'quantity' => $quantity,
        'uom_key' => $uomKey,
        'uom_snapshot' => $uomCode,
        'schedule_date' => $scheduleDate,
        'warehouse_key' => $warehouseKey,
        'material_request_key' => $requestKey,
        'material_request_item_key' => $requestItemKey,
    ];
}

function yovel_admin_buying_normalize_rfq(array $company, array $input): array
{
    $transactionDate = (string) yovel_admin_buying_sourcing_date($input['transaction_date'] ?? '', 'RFQ transaction date');
    $scheduleDate = yovel_admin_buying_sourcing_date($input['schedule_date'] ?? '', 'RFQ schedule date', false);
    if ($scheduleDate !== null && $scheduleDate < $transactionDate) {
        throw new InvalidArgumentException('RFQ schedule date cannot precede the transaction date.');
    }
    $settings = yovel_admin_buying_settings($company);
    $gateway = yovel_admin_buying_sourcing_gateway();
    $supplierRows = is_array($input['suppliers'] ?? null)
        ? $input['suppliers']
        : yovel_admin_buying_parse_rfq_suppliers((string) ($input['suppliers_text'] ?? ''));
    if ($supplierRows === []) {
        throw new InvalidArgumentException('RFQ requires at least one supplier recipient.');
    }
    $suppliers = [];
    $seenSuppliers = [];
    foreach ($supplierRows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('RFQ supplier row is invalid.');
        }
        $supplier = yovel_admin_buying_rfq_supplier_input($company, $row, $seenSuppliers);
        $seenSuppliers[$supplier['supplier_key']] = true;
        $suppliers[] = $supplier;
    }
    $itemRows = is_array($input['items'] ?? null)
        ? $input['items']
        : yovel_admin_buying_parse_rfq_items((string) ($input['items_text'] ?? ''));
    if ($itemRows === []) {
        throw new InvalidArgumentException('RFQ requires at least one item.');
    }
    $items = [];
    foreach ($itemRows as $index => $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('RFQ item row is invalid.');
        }
        $items[] = yovel_admin_buying_rfq_item_input(
            $company,
            $row,
            $index + 1,
            $transactionDate,
            (int) ($settings['allow_zero_qty_rfq'] ?? 0) === 1,
            $gateway
        );
    }
    return [
        'transaction_date' => $transactionDate,
        'schedule_date' => $scheduleDate,
        'subject' => (string) yovel_admin_buying_text($input, 'subject', 'RFQ subject', 220, true),
        'terms' => yovel_admin_buying_text($input, 'terms', 'RFQ terms', 12000),
        'message_for_supplier' => yovel_admin_buying_text($input, 'message_for_supplier', 'RFQ supplier message', 12000),
        'source_opportunity_key' => yovel_admin_buying_optional_uuid($input, 'source_opportunity_key', 'RFQ opportunity'),
        'suppliers' => $suppliers,
        'items' => $items,
    ];
}

function yovel_admin_buying_rfq_read(ADOConnection $db, string $companyKeyHash, string $rfqKey): ?array
{
    $header = $db->GetRow(
        'SELECT * FROM project_company_buying_rfq WHERE company_key_hash = ? AND rfq_key = ? LIMIT 1',
        [$companyKeyHash, $rfqKey]
    );
    if (!is_array($header) || $header === []) {
        return null;
    }
    $suppliers = $db->GetAll(
        "SELECT * FROM project_company_buying_rfq_supplier
        WHERE company_key_hash = ? AND rfq_key = ? AND row_status = 'ACTIVE'
        ORDER BY x_id",
        [$companyKeyHash, $rfqKey]
    );
    $items = $db->GetAll(
        "SELECT * FROM project_company_buying_rfq_item
        WHERE company_key_hash = ? AND rfq_key = ? AND row_status = 'ACTIVE'
        ORDER BY line_no, x_id",
        [$companyKeyHash, $rfqKey]
    );
    $header['row_version'] = (int) ($header['row_version'] ?? 0);
    $header['revision_no'] = (int) ($header['revision_no'] ?? 0);
    $header['suppliers'] = is_array($suppliers) ? $suppliers : [];
    $header['items'] = is_array($items) ? $items : [];
    return $header;
}

function yovel_admin_buying_rfq(array $company, string $rfqKey): ?array
{
    yovel_admin_buying_schema();
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    if (!yovel_admin_is_uuid($rfqKey)) {
        throw new InvalidArgumentException('RFQ key is invalid.');
    }
    return yovel_admin_buying_rfq_read(bx_db(), $companyKeyHash, strtolower($rfqKey));
}

function yovel_admin_buying_rfqs(array $company): array
{
    yovel_admin_buying_schema();
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    $keys = bx_db()->GetCol(
        "SELECT rfq_key FROM project_company_buying_rfq
        WHERE company_key_hash = ? AND document_status <> 'ARCHIVED'
        ORDER BY transaction_date DESC, x_id DESC LIMIT 200",
        [$companyKeyHash]
    );
    $records = [];
    foreach (is_array($keys) ? $keys : [] as $key) {
        $record = yovel_admin_buying_rfq_read(bx_db(), $companyKeyHash, (string) $key);
        if (is_array($record)) {
            $records[] = $record;
        }
    }
    return $records;
}

function yovel_admin_buying_rfq_number(
    ADOConnection $db,
    string $companyKey,
    string $companyKeyHash,
    string $adminKey
): string {
    $year = (int) date('Y');
    $series = $db->GetRow(
        "SELECT number_series_key, next_number, padding FROM project_company_buying_number_series
        WHERE company_key_hash = ? AND series_code = 'RFQ' AND fiscal_year = ? FOR UPDATE",
        [$companyKeyHash, $year]
    );
    $seriesKey = is_array($series) && $series !== [] ? (string) $series['number_series_key'] : bx_uuid();
    $number = is_array($series) && $series !== [] ? (int) $series['next_number'] : 1;
    $padding = is_array($series) && $series !== [] ? (int) $series['padding'] : 5;
    if (is_array($series) && $series !== []) {
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_buying_number_series SET next_number = ?, prefix = 'RFQ-',
                series_status = 'ACTIVE', updated_by_admin_key = ?
            WHERE company_key_hash = ? AND number_series_key = ?",
            [$number + 1, $adminKey, $companyKeyHash, $seriesKey],
            'RFQ number-series update'
        );
    } else {
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_buying_number_series (
                number_series_key, company_key, company_key_hash, series_code, fiscal_year,
                prefix, next_number, padding, series_status, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, 'RFQ', ?, 'RFQ-', ?, ?, 'ACTIVE', ?, ?)",
            [$seriesKey, $companyKey, $companyKeyHash, $year, $number + 1, $padding, $adminKey, $adminKey],
            'RFQ number-series creation'
        );
    }
    $savedNext = $db->GetOne(
        'SELECT next_number FROM project_company_buying_number_series WHERE company_key_hash = ? AND number_series_key = ?',
        [$companyKeyHash, $seriesKey]
    );
    if ((int) $savedNext !== $number + 1) {
        throw new RuntimeException('RFQ number-series read-back verification failed.');
    }
    return 'RFQ-' . $year . '-' . str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
}

function yovel_admin_buying_sourcing_fault(string $type, int $index, array $row): void
{
    $hook = $GLOBALS['yovel_admin_buying_sourcing_write_hook'] ?? null;
    if (is_callable($hook)) {
        $hook($type, $index, $row);
    }
}

function yovel_admin_buying_assert_rfq_readback(array $expected, ?array $saved): void
{
    if (!is_array($saved)) {
        throw new RuntimeException('RFQ exact read-back failed.');
    }
    foreach ([
        'rfq_key', 'company_key', 'company_key_hash', 'rfq_number', 'transaction_date', 'schedule_date',
        'subject', 'terms', 'message_for_supplier', 'source_opportunity_key', 'amended_from_key',
        'document_status', 'revision_no', 'row_version', 'updated_by_admin_key',
    ] as $field) {
        if ((string) ($saved[$field] ?? '') !== (string) ($expected[$field] ?? '')) {
            throw new RuntimeException('RFQ header read-back mismatch: ' . $field . '.');
        }
    }
    $supplierFields = [
        'rfq_supplier_key', 'supplier_key', 'contact_key', 'email', 'send_email', 'delivery_channel',
        'delivery_status', 'dispatch_idempotency_key', 'dispatch_job_key', 'dispatch_checksum', 'row_status',
    ];
    $itemFields = [
        'rfq_item_key', 'line_no', 'inventory_item_key', 'item_code_snapshot', 'item_name_snapshot',
        'description', 'quantity', 'uom_key', 'uom_snapshot', 'schedule_date', 'warehouse_key',
        'material_request_key', 'material_request_item_key', 'row_status',
    ];
    foreach ([['suppliers', $supplierFields], ['items', $itemFields]] as [$collection, $fields]) {
        if (count($expected[$collection] ?? []) !== count($saved[$collection] ?? [])) {
            throw new RuntimeException('RFQ ' . $collection . ' read-back count mismatch.');
        }
        foreach ($expected[$collection] as $index => $row) {
            foreach ($fields as $field) {
                if ((string) ($saved[$collection][$index][$field] ?? '') !== (string) ($row[$field] ?? '')) {
                    throw new RuntimeException('RFQ ' . $collection . ' read-back mismatch: ' . $field . '.');
                }
            }
        }
    }
}

function yovel_admin_buying_save_rfq(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input
): array {
    yovel_admin_buying_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);
    $requestedKey = trim((string) ($input['rfq_key'] ?? ''));
    if ($requestedKey !== '' && !yovel_admin_is_uuid($requestedKey)) {
        throw new InvalidArgumentException('RFQ key is invalid.');
    }
    $requestedKey = strtolower($requestedKey);
    $expectedVersion = (int) ($input['expected_version'] ?? 0);
    if ($requestedKey !== '' && $expectedVersion < 1) {
        throw new InvalidArgumentException('RFQ expected version is required for update.');
    }
    $normalized = yovel_admin_buying_normalize_rfq($company, $input);

    return yovel_admin_buying_in_transaction($db, static function () use (
        $db,
        $companyKey,
        $companyKeyHash,
        $adminKey,
        $requestedKey,
        $expectedVersion,
        $normalized
    ): array {
        $existing = $requestedKey === '' ? [] : $db->GetRow(
            'SELECT * FROM project_company_buying_rfq WHERE company_key_hash = ? AND rfq_key = ? FOR UPDATE',
            [$companyKeyHash, $requestedKey]
        );
        if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
            throw new RuntimeException('RFQ was not found for this company.');
        }
        if ($existing !== [] && (string) $existing['document_status'] !== 'DRAFT') {
            throw new RuntimeException('Submitted or cancelled RFQs are immutable.');
        }
        if ($existing !== [] && (int) $existing['row_version'] !== $expectedVersion) {
            throw new RuntimeException('RFQ changed after it was opened. Reload the server record.');
        }
        $rfqKey = $existing !== [] ? (string) $existing['rfq_key'] : bx_uuid();
        $rfqNumber = $existing !== []
            ? (string) $existing['rfq_number']
            : yovel_admin_buying_rfq_number($db, $companyKey, $companyKeyHash, $adminKey);
        $rowVersion = $existing !== [] ? (int) $existing['row_version'] + 1 : 1;
        $revision = $existing !== [] ? (int) $existing['revision_no'] : 1;
        if ($existing !== []) {
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_buying_rfq SET transaction_date = ?, schedule_date = ?, subject = ?,
                    terms = ?, message_for_supplier = ?, source_opportunity_key = ?, row_version = ?, updated_by_admin_key = ?
                WHERE company_key_hash = ? AND rfq_key = ? AND row_version = ?",
                [
                    $normalized['transaction_date'], $normalized['schedule_date'], $normalized['subject'],
                    $normalized['terms'], $normalized['message_for_supplier'], $normalized['source_opportunity_key'],
                    $rowVersion, $adminKey, $companyKeyHash, $rfqKey, $expectedVersion,
                ],
                'RFQ update'
            );
        } else {
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_rfq (
                    rfq_key, company_key, company_key_hash, rfq_number, transaction_date, schedule_date,
                    subject, terms, message_for_supplier, source_opportunity_key, document_status,
                    revision_no, row_version, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', 1, 1, ?, ?)",
                [
                    $rfqKey, $companyKey, $companyKeyHash, $rfqNumber, $normalized['transaction_date'],
                    $normalized['schedule_date'], $normalized['subject'], $normalized['terms'],
                    $normalized['message_for_supplier'], $normalized['source_opportunity_key'], $adminKey, $adminKey,
                ],
                'RFQ creation'
            );
        }

        $existingSupplierRows = $db->GetAll(
            'SELECT rfq_supplier_key, supplier_key FROM project_company_buying_rfq_supplier WHERE company_key_hash = ? AND rfq_key = ? FOR UPDATE',
            [$companyKeyHash, $rfqKey]
        );
        $existingSupplierKeys = array_column(is_array($existingSupplierRows) ? $existingSupplierRows : [], 'rfq_supplier_key', 'supplier_key');
        $existingItemRows = $db->GetAll(
            'SELECT rfq_item_key, line_no FROM project_company_buying_rfq_item WHERE company_key_hash = ? AND rfq_key = ? FOR UPDATE',
            [$companyKeyHash, $rfqKey]
        );
        $existingItemKeys = array_column(is_array($existingItemRows) ? $existingItemRows : [], 'rfq_item_key', 'line_no');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_buying_rfq_supplier WHERE company_key_hash = ? AND rfq_key = ?', [$companyKeyHash, $rfqKey], 'RFQ supplier replacement');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_buying_rfq_item WHERE company_key_hash = ? AND rfq_key = ?', [$companyKeyHash, $rfqKey], 'RFQ item replacement');

        $expectedSuppliers = [];
        foreach ($normalized['suppliers'] as $index => $supplier) {
            $childKey = (string) ($supplier['rfq_supplier_key']
                ?? $existingSupplierKeys[$supplier['supplier_key']]
                ?? bx_uuid());
            $expectedSupplier = array_replace($supplier, [
                'rfq_supplier_key' => $childKey,
                'delivery_status' => 'NOT_REQUESTED',
                'dispatch_idempotency_key' => null,
                'dispatch_job_key' => null,
                'dispatch_checksum' => null,
                'row_status' => 'ACTIVE',
            ]);
            yovel_admin_buying_sourcing_fault('supplier', $index, $expectedSupplier);
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_rfq_supplier (
                    rfq_supplier_key, company_key, company_key_hash, rfq_key, supplier_key, contact_key,
                    email, send_email, delivery_channel, delivery_status, row_status,
                    created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'NOT_REQUESTED', 'ACTIVE', ?, ?)",
                [
                    $childKey, $companyKey, $companyKeyHash, $rfqKey, $supplier['supplier_key'],
                    $supplier['contact_key'], $supplier['email'], $supplier['send_email'],
                    $supplier['delivery_channel'], $adminKey, $adminKey,
                ],
                'RFQ supplier creation'
            );
            $expectedSuppliers[] = $expectedSupplier;
        }
        $expectedItems = [];
        foreach ($normalized['items'] as $index => $item) {
            $childKey = (string) ($item['rfq_item_key'] ?? $existingItemKeys[$item['line_no']] ?? bx_uuid());
            $expectedItem = array_replace($item, ['rfq_item_key' => $childKey, 'row_status' => 'ACTIVE']);
            yovel_admin_buying_sourcing_fault('item', $index, $expectedItem);
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_rfq_item (
                    rfq_item_key, company_key, company_key_hash, rfq_key, line_no, inventory_item_key,
                    item_code_snapshot, item_name_snapshot, description, quantity, uom_key, uom_snapshot,
                    schedule_date, warehouse_key, material_request_key, material_request_item_key,
                    row_status, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                [
                    $childKey, $companyKey, $companyKeyHash, $rfqKey, $item['line_no'],
                    $item['inventory_item_key'], $item['item_code_snapshot'], $item['item_name_snapshot'],
                    $item['description'], $item['quantity'], $item['uom_key'], $item['uom_snapshot'],
                    $item['schedule_date'], $item['warehouse_key'], $item['material_request_key'],
                    $item['material_request_item_key'], $adminKey, $adminKey,
                ],
                'RFQ item creation'
            );
            $expectedItems[] = $expectedItem;
        }

        $saved = yovel_admin_buying_rfq_read($db, $companyKeyHash, $rfqKey);
        $expected = [
            'rfq_key' => $rfqKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'rfq_number' => $rfqNumber,
            'transaction_date' => $normalized['transaction_date'],
            'schedule_date' => $normalized['schedule_date'],
            'subject' => $normalized['subject'],
            'terms' => $normalized['terms'],
            'message_for_supplier' => $normalized['message_for_supplier'],
            'source_opportunity_key' => $normalized['source_opportunity_key'],
            'amended_from_key' => null,
            'document_status' => 'DRAFT',
            'revision_no' => $revision,
            'row_version' => $rowVersion,
            'updated_by_admin_key' => $adminKey,
            'suppliers' => $expectedSuppliers,
            'items' => $expectedItems,
        ];
        yovel_admin_buying_assert_rfq_readback($expected, $saved);
        $action = $existing !== [] ? 'UPDATE' : 'CREATE';
        bx_audit($action, 'project_company_buying_rfq', $rfqKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'rfq_number' => $rfqNumber,
            'subject' => $normalized['subject'],
            'document_status' => 'DRAFT',
            'row_version' => $rowVersion,
            'supplier_count' => count($expectedSuppliers),
            'item_count' => count($expectedItems),
            'admin_key' => $adminKey,
        ], 'Company administrator saved a request for quotation.');
        yovel_admin_buying_sourcing_fault('rfq_before_commit', 0, $expected);
        return $saved;
    });
}

function yovel_admin_buying_rfq_delivery_payload(
    array $company,
    string $adminKey,
    array $rfq,
    array $supplier
): array {
    $idempotencyKey = 'buying-rfq-delivery:' . hash('sha256', implode('|', [
        strtolower((string) $company['company_key_hash']),
        (string) $rfq['rfq_key'],
        (string) $supplier['supplier_key'],
        (string) $rfq['revision_no'],
        (string) $supplier['delivery_channel'],
    ]));
    return [
        'contract' => 'operations.procurement-delivery.v1',
        'actor_admin_key' => $adminKey,
        'source_module' => 'buying-procurement',
        'source_record_type' => 'request-for-quotation',
        'source_record_key' => (string) $rfq['rfq_key'],
        'source_number' => (string) $rfq['rfq_number'],
        'supplier_key' => (string) $supplier['supplier_key'],
        'recipient' => (string) $supplier['email'],
        'delivery_channel' => (string) $supplier['delivery_channel'],
        'idempotency_key' => $idempotencyKey,
    ];
}

function yovel_admin_buying_validate_delivery_response(array $payload, mixed $response): array
{
    if (!is_array($response)
        || !yovel_admin_is_uuid((string) ($response['job_key'] ?? ''))
        || !hash_equals((string) $payload['idempotency_key'], (string) ($response['idempotency_key'] ?? ''))
        || !in_array((string) ($response['status'] ?? ''), ['QUEUED', 'SENT'], true)
        || preg_match('/^[a-f0-9]{64}$/', (string) ($response['checksum'] ?? '')) !== 1) {
        throw new RuntimeException('Operations RFQ transport registration response could not be verified.');
    }
    return $response;
}

function yovel_admin_buying_transition_rfq(
    ADOConnection $db,
    array $company,
    array $admin,
    string $rfqKey,
    string $action,
    array $input
): array {
    yovel_admin_buying_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);
    if (!yovel_admin_is_uuid($rfqKey)) {
        throw new InvalidArgumentException('RFQ key is invalid.');
    }
    $rfqKey = strtolower($rfqKey);
    $action = strtoupper(trim($action));
    if (!in_array($action, ['SUBMIT', 'CANCEL', 'AMEND', 'MARK_RECEIVED', 'RETRY_DISPATCH'], true)) {
        throw new InvalidArgumentException('RFQ lifecycle action is invalid.');
    }
    $expectedVersion = (int) ($input['expected_version'] ?? 0);
    if ($expectedVersion < 1) {
        throw new InvalidArgumentException('RFQ expected version is required.');
    }
    $before = yovel_admin_buying_rfq($company, $rfqKey);
    if (!is_array($before)) {
        throw new RuntimeException('RFQ was not found for this company.');
    }
    if (in_array($action, ['SUBMIT', 'RETRY_DISPATCH'], true)) {
        foreach ($before['suppliers'] as $supplierRow) {
            if ($action === 'RETRY_DISPATCH'
                && (string) $supplierRow['supplier_key'] !== strtolower(trim((string) ($input['supplier_key'] ?? '')))) {
                continue;
            }
            yovel_admin_buying_rfq_supplier_input($company, $supplierRow, []);
        }
    }
    $gateway = yovel_admin_buying_sourcing_gateway();

    return yovel_admin_buying_in_transaction($db, static function () use (
        $db,
        $company,
        $companyKey,
        $companyKeyHash,
        $adminKey,
        $rfqKey,
        $action,
        $input,
        $expectedVersion,
        $gateway
    ): array {
        $header = $db->GetRow(
            'SELECT * FROM project_company_buying_rfq WHERE company_key_hash = ? AND rfq_key = ? FOR UPDATE',
            [$companyKeyHash, $rfqKey]
        );
        if (!is_array($header) || $header === []) {
            throw new RuntimeException('RFQ was not found for this company.');
        }
        if ((int) $header['row_version'] !== $expectedVersion) {
            throw new RuntimeException('RFQ changed after it was opened. Reload the server record.');
        }
        $supplierRows = $db->GetAll(
            "SELECT * FROM project_company_buying_rfq_supplier
            WHERE company_key_hash = ? AND rfq_key = ? AND row_status = 'ACTIVE' ORDER BY x_id FOR UPDATE",
            [$companyKeyHash, $rfqKey]
        );
        $itemRows = $db->GetAll(
            "SELECT * FROM project_company_buying_rfq_item
            WHERE company_key_hash = ? AND rfq_key = ? AND row_status = 'ACTIVE' ORDER BY line_no FOR UPDATE",
            [$companyKeyHash, $rfqKey]
        );
        if (!is_array($supplierRows) || $supplierRows === [] || !is_array($itemRows) || $itemRows === []) {
            throw new RuntimeException('RFQ requires persisted suppliers and items before lifecycle changes.');
        }
        $nextVersion = $expectedVersion + 1;

        if ($action === 'SUBMIT') {
            if ((string) $header['document_status'] !== 'DRAFT') {
                throw new RuntimeException('Only Draft RFQs can be submitted.');
            }
            foreach ($supplierRows as $supplier) {
                $payload = yovel_admin_buying_rfq_delivery_payload($company, $adminKey, $header, $supplier);
                $response = yovel_admin_buying_validate_delivery_response(
                    $payload,
                    yovel_admin_buying_sourcing_dependency_call($gateway, 'delivery_register', [$company, $payload])
                );
                yovel_admin_db_execute(
                    $db,
                    "UPDATE project_company_buying_rfq_supplier SET delivery_status = 'PENDING',
                        dispatch_idempotency_key = ?, dispatch_job_key = ?, dispatch_checksum = ?,
                        updated_by_admin_key = ?
                    WHERE company_key_hash = ? AND rfq_supplier_key = ?",
                    [
                        $payload['idempotency_key'], $response['job_key'], $response['checksum'], $adminKey,
                        $companyKeyHash, $supplier['rfq_supplier_key'],
                    ],
                    'RFQ recipient dispatch registration'
                );
            }
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_buying_rfq SET document_status = 'SUBMITTED', row_version = ?,
                    submitted_by_admin_key = ?, submitted_at = CURRENT_TIMESTAMP, updated_by_admin_key = ?
                WHERE company_key_hash = ? AND rfq_key = ? AND row_version = ?",
                [$nextVersion, $adminKey, $adminKey, $companyKeyHash, $rfqKey, $expectedVersion],
                'RFQ submission'
            );
        } elseif ($action === 'CANCEL') {
            if ((string) $header['document_status'] !== 'SUBMITTED') {
                throw new RuntimeException('Only Submitted RFQs can be cancelled.');
            }
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_buying_rfq SET document_status = 'CANCELLED', row_version = ?,
                    cancelled_by_admin_key = ?, cancelled_at = CURRENT_TIMESTAMP, updated_by_admin_key = ?
                WHERE company_key_hash = ? AND rfq_key = ? AND row_version = ?",
                [$nextVersion, $adminKey, $adminKey, $companyKeyHash, $rfqKey, $expectedVersion],
                'RFQ cancellation'
            );
        } elseif ($action === 'MARK_RECEIVED') {
            if ((string) $header['document_status'] !== 'SUBMITTED') {
                throw new RuntimeException('Only Submitted RFQs can receive supplier responses.');
            }
            $supplierKey = strtolower(trim((string) ($input['supplier_key'] ?? '')));
            $matches = array_values(array_filter($supplierRows, static fn (array $row): bool => (string) $row['supplier_key'] === $supplierKey));
            if (!yovel_admin_is_uuid($supplierKey) || count($matches) !== 1) {
                throw new RuntimeException('RFQ supplier recipient was not found.');
            }
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_buying_rfq_supplier SET delivery_status = 'RECEIVED',
                    received_at = CURRENT_TIMESTAMP, updated_by_admin_key = ?
                WHERE company_key_hash = ? AND rfq_key = ? AND supplier_key = ?",
                [$adminKey, $companyKeyHash, $rfqKey, $supplierKey],
                'RFQ recipient received status'
            );
            yovel_admin_db_execute(
                $db,
                'UPDATE project_company_buying_rfq SET row_version = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND rfq_key = ? AND row_version = ?',
                [$nextVersion, $adminKey, $companyKeyHash, $rfqKey, $expectedVersion],
                'RFQ received version update'
            );
        } elseif ($action === 'RETRY_DISPATCH') {
            if ((string) $header['document_status'] !== 'SUBMITTED') {
                throw new RuntimeException('Only Submitted RFQs can retry delivery.');
            }
            $supplierKey = strtolower(trim((string) ($input['supplier_key'] ?? '')));
            $matches = array_values(array_filter($supplierRows, static fn (array $row): bool => (string) $row['supplier_key'] === $supplierKey));
            if (!yovel_admin_is_uuid($supplierKey) || count($matches) !== 1) {
                throw new RuntimeException('RFQ supplier recipient was not found.');
            }
            $supplier = $matches[0];
            $payload = yovel_admin_buying_rfq_delivery_payload($company, $adminKey, $header, $supplier);
            $response = yovel_admin_buying_validate_delivery_response(
                $payload,
                yovel_admin_buying_sourcing_dependency_call($gateway, 'delivery_register', [$company, $payload])
            );
            if ((string) ($supplier['dispatch_idempotency_key'] ?? '') !== ''
                && !hash_equals((string) $supplier['dispatch_idempotency_key'], (string) $payload['idempotency_key'])) {
                throw new RuntimeException('RFQ dispatch idempotency identity changed unexpectedly.');
            }
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_buying_rfq_supplier SET delivery_status = 'PENDING',
                    dispatch_idempotency_key = ?, dispatch_job_key = ?, dispatch_checksum = ?, updated_by_admin_key = ?
                WHERE company_key_hash = ? AND rfq_supplier_key = ?",
                [
                    $payload['idempotency_key'], $response['job_key'], $response['checksum'], $adminKey,
                    $companyKeyHash, $supplier['rfq_supplier_key'],
                ],
                'RFQ recipient dispatch retry'
            );
            yovel_admin_db_execute(
                $db,
                'UPDATE project_company_buying_rfq SET row_version = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND rfq_key = ? AND row_version = ?',
                [$nextVersion, $adminKey, $companyKeyHash, $rfqKey, $expectedVersion],
                'RFQ retry version update'
            );
        } else {
            if ((string) $header['document_status'] !== 'CANCELLED') {
                throw new RuntimeException('Only Cancelled RFQs can be amended.');
            }
            $newKey = bx_uuid();
            $newRevision = (int) $header['revision_no'] + 1;
            $newNumber = (string) $header['rfq_number'] . '-A' . ($newRevision - 1);
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_rfq (
                    rfq_key, company_key, company_key_hash, rfq_number, transaction_date, schedule_date,
                    subject, terms, message_for_supplier, source_opportunity_key, amended_from_key,
                    document_status, revision_no, row_version, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, 1, ?, ?)",
                [
                    $newKey, $companyKey, $companyKeyHash, $newNumber, $header['transaction_date'],
                    $header['schedule_date'], $header['subject'], $header['terms'], $header['message_for_supplier'],
                    $header['source_opportunity_key'], $rfqKey, $newRevision, $adminKey, $adminKey,
                ],
                'RFQ amendment creation'
            );
            foreach ($supplierRows as $supplier) {
                yovel_admin_db_execute(
                    $db,
                    "INSERT INTO project_company_buying_rfq_supplier (
                        rfq_supplier_key, company_key, company_key_hash, rfq_key, supplier_key, contact_key,
                        email, send_email, delivery_channel, delivery_status, row_status,
                        created_by_admin_key, updated_by_admin_key
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'NOT_REQUESTED', 'ACTIVE', ?, ?)",
                    [
                        bx_uuid(), $companyKey, $companyKeyHash, $newKey, $supplier['supplier_key'],
                        $supplier['contact_key'], $supplier['email'], $supplier['send_email'],
                        $supplier['delivery_channel'], $adminKey, $adminKey,
                    ],
                    'RFQ amendment supplier creation'
                );
            }
            foreach ($itemRows as $item) {
                yovel_admin_db_execute(
                    $db,
                    "INSERT INTO project_company_buying_rfq_item (
                        rfq_item_key, company_key, company_key_hash, rfq_key, line_no, inventory_item_key,
                        item_code_snapshot, item_name_snapshot, description, quantity, uom_key, uom_snapshot,
                        schedule_date, warehouse_key, material_request_key, material_request_item_key,
                        row_status, created_by_admin_key, updated_by_admin_key
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                    [
                        bx_uuid(), $companyKey, $companyKeyHash, $newKey, $item['line_no'],
                        $item['inventory_item_key'], $item['item_code_snapshot'], $item['item_name_snapshot'],
                        $item['description'], $item['quantity'], $item['uom_key'], $item['uom_snapshot'],
                        $item['schedule_date'], $item['warehouse_key'], $item['material_request_key'],
                        $item['material_request_item_key'], $adminKey, $adminKey,
                    ],
                    'RFQ amendment item creation'
                );
            }
            $amended = yovel_admin_buying_rfq_read($db, $companyKeyHash, $newKey);
            if (!is_array($amended)
                || (string) $amended['amended_from_key'] !== $rfqKey
                || (string) $amended['document_status'] !== 'DRAFT'
                || (int) $amended['revision_no'] !== $newRevision
                || count($amended['suppliers']) !== count($supplierRows)
                || count($amended['items']) !== count($itemRows)) {
                throw new RuntimeException('RFQ amendment exact read-back failed.');
            }
            bx_audit('AMEND', 'project_company_buying_rfq', $rfqKey, [
                'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash,
                'rfq_number' => $header['rfq_number'],
                'amended_rfq_key' => $newKey,
                'amended_rfq_number' => $newNumber,
                'admin_key' => $adminKey,
            ], 'Company administrator amended a cancelled request for quotation.');
            return $amended;
        }

        $saved = yovel_admin_buying_rfq_read($db, $companyKeyHash, $rfqKey);
        if (!is_array($saved) || (int) $saved['row_version'] !== $nextVersion) {
            throw new RuntimeException('RFQ lifecycle exact read-back failed.');
        }
        $expectedStatus = match ($action) {
            'SUBMIT' => 'SUBMITTED',
            'CANCEL' => 'CANCELLED',
            default => (string) $header['document_status'],
        };
        if ((string) $saved['document_status'] !== $expectedStatus) {
            throw new RuntimeException('RFQ lifecycle status read-back failed.');
        }
        bx_audit($action, 'project_company_buying_rfq', $rfqKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'rfq_number' => $header['rfq_number'],
            'document_status' => $saved['document_status'],
            'row_version' => $saved['row_version'],
            'supplier_key' => $input['supplier_key'] ?? null,
            'admin_key' => $adminKey,
        ], 'Company administrator changed a request-for-quotation lifecycle state.');
        return $saved;
    });
}

function yovel_admin_buying_map_material_requests_to_rfq(
    array $company,
    array $materialRequestKeys,
    array $supplierKeys
): array {
    yovel_admin_buying_read_scope($company);
    $materialRequestKeys = array_values(array_unique(array_map('strtolower', array_map('trim', $materialRequestKeys))));
    $supplierKeys = array_values(array_unique(array_map('strtolower', array_map('trim', $supplierKeys))));
    if ($materialRequestKeys === [] || $supplierKeys === []) {
        throw new InvalidArgumentException('RFQ mapping requires material requests and suppliers.');
    }
    foreach ([...$materialRequestKeys, ...$supplierKeys] as $key) {
        if (!yovel_admin_is_uuid($key)) {
            throw new InvalidArgumentException('RFQ mapping contains an invalid record key.');
        }
    }
    $gateway = yovel_admin_buying_sourcing_gateway();
    $snapshot = yovel_admin_buying_sourcing_dependency_call($gateway, 'material_request_snapshot', [
        $company,
        ['material_request_keys' => $materialRequestKeys],
    ]);
    $companyHash = strtolower((string) ($company['company_key_hash'] ?? ''));
    if (!is_array($snapshot)
        || (string) ($snapshot['contract'] ?? '') !== 'inventory.buying-snapshot.v1'
        || !hash_equals($companyHash, strtolower((string) ($snapshot['company_key_hash'] ?? '')))) {
        throw new RuntimeException('Inventory material-request snapshot scope could not be verified.');
    }
    $requestsByKey = [];
    foreach (is_array($snapshot['material_requests'] ?? null) ? $snapshot['material_requests'] : [] as $request) {
        $requestsByKey[(string) ($request['material_request_key'] ?? '')] = $request;
    }
    $items = [];
    $scheduleDate = null;
    foreach ($materialRequestKeys as $requestKey) {
        $request = $requestsByKey[$requestKey] ?? null;
        if (!is_array($request)) {
            throw new InvalidArgumentException('Material request was not found in the Inventory snapshot.');
        }
        foreach (is_array($request['items'] ?? null) ? $request['items'] : [] as $item) {
            $itemSchedule = (string) yovel_admin_buying_sourcing_date($item['schedule_date'] ?? '', 'Material-request schedule date');
            $scheduleDate = $scheduleDate === null || $itemSchedule > $scheduleDate ? $itemSchedule : $scheduleDate;
            $items[] = [
                'inventory_item_key' => (string) ($item['inventory_item_key'] ?? ''),
                'uom_key' => (string) ($item['uom_key'] ?? ''),
                'quantity' => (string) ($item['quantity'] ?? ''),
                'schedule_date' => $itemSchedule,
                'warehouse_key' => $item['warehouse_key'] ?? null,
                'material_request_key' => $requestKey,
                'material_request_item_key' => (string) ($item['material_request_item_key'] ?? ''),
            ];
        }
    }
    $suppliers = [];
    foreach ($supplierKeys as $supplierKey) {
        $supplier = yovel_admin_buying_supplier($company, $supplierKey);
        if (!is_array($supplier)) {
            throw new RuntimeException('RFQ mapping supplier was not found.');
        }
        $suppliers[] = [
            'supplier_key' => $supplierKey,
            'email' => (string) ($supplier['email'] ?? ''),
            'delivery_channel' => 'EMAIL',
        ];
    }
    return [
        'transaction_date' => date('Y-m-d'),
        'schedule_date' => $scheduleDate,
        'subject' => count($materialRequestKeys) === 1 ? 'Quotation request for material request' : 'Quotation request for material requests',
        'terms' => null,
        'message_for_supplier' => null,
        'suppliers' => $suppliers,
        'items' => $items,
    ];
}

function yovel_admin_buying_parse_supplier_quotation_items(string $text): array
{
    $items = [];
    foreach (preg_split('/\R/', trim($text)) ?: [] as $line) {
        if (trim($line) === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $items[] = [
            'inventory_item_key' => $parts[0] ?? '',
            'uom_key' => $parts[1] ?? '',
            'quantity' => $parts[2] ?? '',
            'rate' => $parts[3] ?? '',
            'discount_percentage' => $parts[4] ?? '0',
            'expected_delivery_date' => $parts[5] ?? '',
            'warehouse_key' => $parts[6] ?? '',
            'rfq_key' => $parts[7] ?? '',
            'rfq_item_key' => $parts[8] ?? '',
        ];
    }
    return $items;
}

function yovel_admin_buying_quotation_decimal(
    mixed $value,
    string $label,
    int $scale,
    bool $allowZero = true
): string {
    $text = trim((string) $value);
    if ($text === '' || preg_match('/^\d{1,12}(?:\.\d{1,8})?$/', $text) !== 1) {
        throw new InvalidArgumentException($label . ' must be a finite non-negative number.');
    }
    $normalized = number_format((float) $text, $scale, '.', '');
    if (!$allowZero && bccomp($normalized, str_repeat('0', 1) . '.' . str_repeat('0', $scale), $scale) <= 0) {
        throw new InvalidArgumentException($label . ' must be greater than zero.');
    }
    return $normalized;
}

function yovel_admin_buying_supplier_quotation_item_input(
    array $company,
    array $row,
    int $lineNo,
    string $transactionDate,
    bool $allowZero,
    array $gateway,
    ?array $rfq
): array {
    $itemKey = trim((string) ($row['inventory_item_key'] ?? ''));
    $uomKey = trim((string) ($row['uom_key'] ?? ''));
    if (!yovel_admin_is_uuid($itemKey)) {
        throw new InvalidArgumentException('Supplier Quotation item key is invalid.');
    }
    $item = yovel_admin_buying_sourcing_dependency_call($gateway, 'item_lookup', [$company, strtolower($itemKey)]);
    if (!is_array($item)
        || (string) ($item['item_status'] ?? '') !== 'ACTIVE'
        || !hash_equals(strtolower((string) $company['company_key_hash']), strtolower((string) ($item['company_key_hash'] ?? '')))
        || (string) ($item['item_key'] ?? '') !== strtolower($itemKey)) {
        throw new InvalidArgumentException('Supplier Quotation item was not found through Inventory.');
    }
    $uom = null;
    foreach (is_array($item['uoms'] ?? null) ? $item['uoms'] : [] as $candidate) {
        if ((string) ($candidate['uom_key'] ?? '') === $uomKey || strtoupper((string) ($candidate['uom_code'] ?? '')) === strtoupper($uomKey)) {
            $uom = $candidate;
            break;
        }
    }
    if (!is_array($uom)) {
        throw new InvalidArgumentException('Supplier Quotation UOM was not found for the Inventory item.');
    }
    $quantity = yovel_admin_buying_sourcing_quantity($row['quantity'] ?? '');
    if (!$allowZero && bccomp($quantity, '0.000000', 6) === 0) {
        throw new InvalidArgumentException('Supplier Quotation zero quantity is disabled by Buying Settings.');
    }
    $uomCode = strtoupper((string) $uom['uom_code']);
    $resolved = yovel_admin_buying_sourcing_dependency_call($gateway, 'item_uom_resolve', [
        $company,
        strtolower($itemKey),
        $uomCode,
        $quantity,
    ]);
    if (!is_array($resolved) || bccomp(number_format((float) ($resolved['quantity'] ?? -1), 6, '.', ''), $quantity, 6) !== 0) {
        throw new RuntimeException('Inventory did not verify the Supplier Quotation quantity/UOM.');
    }
    $deliveryDate = yovel_admin_buying_sourcing_date($row['expected_delivery_date'] ?? '', 'Supplier Quotation delivery date', false);
    if ($deliveryDate !== null && $deliveryDate < $transactionDate) {
        throw new InvalidArgumentException('Supplier Quotation delivery date cannot precede its transaction date.');
    }
    $rate = yovel_admin_buying_quotation_decimal($row['rate'] ?? '', 'Supplier Quotation rate', 6);
    $discount = yovel_admin_buying_quotation_decimal($row['discount_percentage'] ?? '0', 'Supplier Quotation discount', 4);
    if (bccomp($discount, '100.0000', 4) > 0) {
        throw new InvalidArgumentException('Supplier Quotation discount cannot exceed 100 percent.');
    }
    $rfqItemKey = yovel_admin_buying_optional_uuid($row, 'rfq_item_key', 'Supplier Quotation RFQ item');
    $rfqKey = yovel_admin_buying_optional_uuid($row, 'rfq_key', 'Supplier Quotation RFQ');
    $sourceItem = null;
    if ($rfq !== null) {
        if ($rfqKey !== null && $rfqKey !== (string) $rfq['rfq_key']) {
            throw new InvalidArgumentException('Supplier Quotation RFQ item header does not match the selected RFQ.');
        }
        foreach ($rfq['items'] as $candidate) {
            if ((string) $candidate['rfq_item_key'] === (string) $rfqItemKey) {
                $sourceItem = $candidate;
                break;
            }
        }
        if (!is_array($sourceItem)
            || (string) $sourceItem['inventory_item_key'] !== strtolower($itemKey)
            || (string) $sourceItem['uom_key'] !== (string) $uom['uom_key']) {
            throw new InvalidArgumentException('Supplier Quotation RFQ item mapping is invalid.');
        }
        if (bccomp($quantity, (string) $sourceItem['quantity'], 6) > 0) {
            throw new InvalidArgumentException('Supplier Quotation quantity exceeds the RFQ item quantity.');
        }
    } elseif ($rfqItemKey !== null || $rfqKey !== null) {
        throw new InvalidArgumentException('Supplier Quotation RFQ item requires a selected RFQ.');
    }

    return [
        'supplier_quotation_item_key' => yovel_admin_buying_optional_uuid($row, 'supplier_quotation_item_key', 'Supplier Quotation item row'),
        'line_no' => $lineNo,
        'inventory_item_key' => strtolower($itemKey),
        'item_code_snapshot' => (string) ($item['item_code'] ?? ''),
        'item_name_snapshot' => (string) ($item['item_name'] ?? ''),
        'description' => yovel_admin_buying_text(
            ['description' => $row['description'] ?? $item['item_description'] ?? null],
            'description',
            'Supplier Quotation item description',
            4000
        ),
        'quantity' => $quantity,
        'uom_key' => (string) $uom['uom_key'],
        'uom_snapshot' => $uomCode,
        'rate' => $rate,
        'discount_percentage' => $discount,
        'expected_delivery_date' => $deliveryDate,
        'warehouse_key' => yovel_admin_buying_optional_uuid($row, 'warehouse_key', 'Supplier Quotation warehouse'),
        'rfq_item_key' => $rfqItemKey,
        'material_request_key' => $sourceItem['material_request_key'] ?? null,
        'material_request_item_key' => $sourceItem['material_request_item_key'] ?? null,
    ];
}

function yovel_admin_buying_normalize_supplier_quotation(array $company, array $input): array
{
    $transactionDate = (string) yovel_admin_buying_sourcing_date($input['transaction_date'] ?? '', 'Supplier Quotation transaction date');
    $validUntil = yovel_admin_buying_sourcing_date($input['valid_until'] ?? '', 'Supplier Quotation valid-until date', false);
    if ($validUntil !== null && $validUntil < $transactionDate) {
        throw new InvalidArgumentException('Supplier Quotation valid-until date cannot precede the transaction date.');
    }
    $currency = strtoupper(trim((string) ($input['currency'] ?? '')));
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
        throw new InvalidArgumentException('Supplier Quotation currency must be a three-letter code.');
    }
    $conversionRate = yovel_admin_buying_quotation_decimal($input['conversion_rate'] ?? '', 'Supplier Quotation conversion rate', 8, false);
    $supplierKey = trim((string) ($input['supplier_key'] ?? ''));
    if (!yovel_admin_is_uuid($supplierKey)) {
        throw new InvalidArgumentException('Supplier Quotation supplier key is invalid.');
    }
    $supplier = yovel_admin_buying_supplier($company, strtolower($supplierKey));
    if (!is_array($supplier) || (string) ($supplier['supplier_status'] ?? '') !== 'ACTIVE') {
        throw new RuntimeException('Supplier Quotation requires an active company supplier.');
    }
    $rfqKey = yovel_admin_buying_optional_uuid($input, 'rfq_key', 'Supplier Quotation RFQ');
    $rfq = $rfqKey === null ? null : yovel_admin_buying_rfq($company, $rfqKey);
    if ($rfqKey !== null) {
        if (!is_array($rfq) || (string) $rfq['document_status'] !== 'SUBMITTED') {
            throw new RuntimeException('Supplier Quotation requires a submitted RFQ.');
        }
        $invited = false;
        foreach ($rfq['suppliers'] as $recipient) {
            if ((string) $recipient['supplier_key'] === strtolower($supplierKey)) {
                $invited = true;
                break;
            }
        }
        if (!$invited) {
            throw new RuntimeException('Supplier Quotation supplier was not invited to the RFQ.');
        }
    }
    $settings = yovel_admin_buying_settings($company);
    $gateway = yovel_admin_buying_sourcing_gateway();
    $itemRows = is_array($input['items'] ?? null)
        ? $input['items']
        : yovel_admin_buying_parse_supplier_quotation_items((string) ($input['items_text'] ?? ''));
    if ($itemRows === []) {
        throw new InvalidArgumentException('Supplier Quotation requires at least one item.');
    }
    $items = [];
    foreach ($itemRows as $index => $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Supplier Quotation item row is invalid.');
        }
        $items[] = yovel_admin_buying_supplier_quotation_item_input(
            $company,
            $row,
            $index + 1,
            $transactionDate,
            (int) ($settings['allow_zero_qty_supplier_quotation'] ?? 0) === 1,
            $gateway,
            $rfq
        );
    }
    $calculationPayload = [
        'document_type' => 'SUPPLIER_QUOTATION',
        'supplier_key' => strtolower($supplierKey),
        'transaction_date' => $transactionDate,
        'currency' => $currency,
        'conversion_rate' => $conversionRate,
        'items' => array_map(static fn (array $item): array => [
            'line_no' => $item['line_no'],
            'inventory_item_key' => $item['inventory_item_key'],
            'uom_key' => $item['uom_key'],
            'quantity' => $item['quantity'],
            'rate' => $item['rate'],
            'discount_percentage' => $item['discount_percentage'],
        ], $items),
    ];
    $calculation = yovel_admin_buying_sourcing_dependency_call($gateway, 'finance_purchase_calculation', [$company, $calculationPayload]);
    if (!is_array($calculation)
        || (string) ($calculation['contract'] ?? '') !== 'finance.purchase-document-calculation.v1'
        || !hash_equals(strtolower((string) $company['company_key_hash']), strtolower((string) ($calculation['company_key_hash'] ?? '')))
        || (string) ($calculation['currency'] ?? '') !== $currency
        || (string) yovel_admin_buying_quotation_decimal($calculation['conversion_rate'] ?? '', 'Finance conversion rate', 8, false) !== $conversionRate
        || count($calculation['items'] ?? []) !== count($items)) {
        throw new RuntimeException('Finance Supplier Quotation calculation scope or shape is invalid.');
    }
    $calculatedItems = [];
    $sumNet = '0.000000';
    $sumTax = '0.000000';
    $sumGross = '0.000000';
    foreach ($items as $index => $item) {
        $calculated = $calculation['items'][$index] ?? null;
        if (!is_array($calculated)
            || (int) ($calculated['line_no'] ?? 0) !== $item['line_no']
            || (string) ($calculated['inventory_item_key'] ?? '') !== $item['inventory_item_key']
            || (string) ($calculated['uom_key'] ?? '') !== $item['uom_key']
            || (string) yovel_admin_buying_quotation_decimal($calculated['quantity'] ?? '', 'Finance item quantity', 6) !== $item['quantity']
            || (string) yovel_admin_buying_quotation_decimal($calculated['rate'] ?? '', 'Finance item rate', 6) !== $item['rate']
            || (string) yovel_admin_buying_quotation_decimal($calculated['discount_percentage'] ?? '', 'Finance item discount', 4) !== $item['discount_percentage']) {
            throw new RuntimeException('Finance Supplier Quotation item identity is invalid.');
        }
        $net = yovel_admin_buying_quotation_decimal($calculated['net_amount'] ?? '', 'Finance item net amount', 6);
        $tax = yovel_admin_buying_quotation_decimal($calculated['tax_amount'] ?? '', 'Finance item tax amount', 6);
        $gross = yovel_admin_buying_quotation_decimal($calculated['gross_amount'] ?? '', 'Finance item gross amount', 6);
        if (bccomp(bcadd($net, $tax, 6), $gross, 6) !== 0) {
            throw new RuntimeException('Finance Supplier Quotation item totals do not reconcile.');
        }
        $calculatedItems[] = $item + ['net_amount' => $net, 'tax_amount' => $tax, 'gross_amount' => $gross];
        $sumNet = bcadd($sumNet, $net, 6);
        $sumTax = bcadd($sumTax, $tax, 6);
        $sumGross = bcadd($sumGross, $gross, 6);
    }
    $netTotal = yovel_admin_buying_quotation_decimal($calculation['net_total'] ?? '', 'Finance net total', 6);
    $taxTotal = yovel_admin_buying_quotation_decimal($calculation['tax_total'] ?? '', 'Finance tax total', 6);
    $grandTotal = yovel_admin_buying_quotation_decimal($calculation['grand_total'] ?? '', 'Finance grand total', 6);
    if (bccomp($netTotal, $sumNet, 6) !== 0 || bccomp($taxTotal, $sumTax, 6) !== 0 || bccomp($grandTotal, $sumGross, 6) !== 0) {
        throw new RuntimeException('Finance Supplier Quotation totals do not match calculated lines.');
    }
    return [
        'supplier_reference' => yovel_admin_buying_text($input, 'supplier_reference', 'Supplier Quotation supplier reference', 120),
        'supplier_key' => strtolower($supplierKey),
        'rfq_key' => $rfqKey,
        'transaction_date' => $transactionDate,
        'valid_until' => $validUntil,
        'currency' => $currency,
        'conversion_rate' => $conversionRate,
        'net_total' => $netTotal,
        'tax_total' => $taxTotal,
        'grand_total' => $grandTotal,
        'calculation_snapshot_json' => json_encode($calculation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'items' => $calculatedItems,
    ];
}

function yovel_admin_buying_supplier_quotation_read(ADOConnection $db, string $companyKeyHash, string $quotationKey): ?array
{
    $header = $db->GetRow(
        'SELECT * FROM project_company_buying_supplier_quotation WHERE company_key_hash = ? AND supplier_quotation_key = ? LIMIT 1',
        [$companyKeyHash, $quotationKey]
    );
    if (!is_array($header) || $header === []) {
        return null;
    }
    $items = $db->GetAll(
        "SELECT * FROM project_company_buying_supplier_quotation_item
        WHERE company_key_hash = ? AND supplier_quotation_key = ? AND row_status = 'ACTIVE'
        ORDER BY line_no, x_id",
        [$companyKeyHash, $quotationKey]
    );
    $header['row_version'] = (int) ($header['row_version'] ?? 0);
    $header['revision_no'] = (int) ($header['revision_no'] ?? 0);
    $header['items'] = is_array($items) ? $items : [];
    return $header;
}

function yovel_admin_buying_supplier_quotation(array $company, string $quotationKey): ?array
{
    yovel_admin_buying_schema();
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    if (!yovel_admin_is_uuid($quotationKey)) {
        throw new InvalidArgumentException('Supplier Quotation key is invalid.');
    }
    return yovel_admin_buying_supplier_quotation_read(bx_db(), $companyKeyHash, strtolower($quotationKey));
}

function yovel_admin_buying_supplier_quotations(array $company): array
{
    yovel_admin_buying_schema();
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    $keys = bx_db()->GetCol(
        "SELECT supplier_quotation_key FROM project_company_buying_supplier_quotation
        WHERE company_key_hash = ? AND document_status <> 'ARCHIVED'
        ORDER BY transaction_date DESC, x_id DESC LIMIT 200",
        [$companyKeyHash]
    );
    $records = [];
    foreach (is_array($keys) ? $keys : [] as $key) {
        $record = yovel_admin_buying_supplier_quotation_read(bx_db(), $companyKeyHash, (string) $key);
        if (is_array($record)) {
            $records[] = $record;
        }
    }
    return $records;
}

function yovel_admin_buying_supplier_quotation_number(
    ADOConnection $db,
    string $companyKey,
    string $companyKeyHash,
    string $adminKey
): string {
    $year = (int) date('Y');
    $series = $db->GetRow(
        "SELECT number_series_key, next_number, padding FROM project_company_buying_number_series
        WHERE company_key_hash = ? AND series_code = 'SUPPLIER_QUOTATION' AND fiscal_year = ? FOR UPDATE",
        [$companyKeyHash, $year]
    );
    $seriesKey = is_array($series) && $series !== [] ? (string) $series['number_series_key'] : bx_uuid();
    $number = is_array($series) && $series !== [] ? (int) $series['next_number'] : 1;
    $padding = is_array($series) && $series !== [] ? (int) $series['padding'] : 5;
    if (is_array($series) && $series !== []) {
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_buying_number_series SET next_number = ?, prefix = 'SQ-', series_status = 'ACTIVE', updated_by_admin_key = ?
            WHERE company_key_hash = ? AND number_series_key = ?",
            [$number + 1, $adminKey, $companyKeyHash, $seriesKey],
            'Supplier Quotation number-series update'
        );
    } else {
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_buying_number_series (
                number_series_key, company_key, company_key_hash, series_code, fiscal_year,
                prefix, next_number, padding, series_status, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, 'SUPPLIER_QUOTATION', ?, 'SQ-', ?, ?, 'ACTIVE', ?, ?)",
            [$seriesKey, $companyKey, $companyKeyHash, $year, $number + 1, $padding, $adminKey, $adminKey],
            'Supplier Quotation number-series creation'
        );
    }
    if ((int) $db->GetOne(
        'SELECT next_number FROM project_company_buying_number_series WHERE company_key_hash = ? AND number_series_key = ?',
        [$companyKeyHash, $seriesKey]
    ) !== $number + 1) {
        throw new RuntimeException('Supplier Quotation number-series read-back failed.');
    }
    return 'SQ-' . $year . '-' . str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
}

function yovel_admin_buying_assert_supplier_quotation_readback(array $expected, ?array $saved): void
{
    if (!is_array($saved)) {
        throw new RuntimeException('Supplier Quotation exact read-back failed.');
    }
    foreach ([
        'supplier_quotation_key', 'company_key', 'company_key_hash', 'quotation_number', 'supplier_reference',
        'supplier_key', 'rfq_key', 'transaction_date', 'valid_until', 'currency', 'conversion_rate',
        'net_total', 'tax_total', 'grand_total', 'calculation_snapshot_json', 'amended_from_key',
        'document_status', 'revision_no', 'row_version', 'updated_by_admin_key',
    ] as $field) {
        if ((string) ($saved[$field] ?? '') !== (string) ($expected[$field] ?? '')) {
            throw new RuntimeException('Supplier Quotation header read-back mismatch: ' . $field . '.');
        }
    }
    $fields = [
        'supplier_quotation_item_key', 'line_no', 'inventory_item_key', 'item_code_snapshot', 'item_name_snapshot',
        'description', 'quantity', 'uom_key', 'uom_snapshot', 'rate', 'discount_percentage', 'net_amount',
        'tax_amount', 'gross_amount', 'expected_delivery_date', 'warehouse_key', 'rfq_item_key',
        'material_request_key', 'material_request_item_key', 'row_status',
    ];
    if (count($expected['items'] ?? []) !== count($saved['items'] ?? [])) {
        throw new RuntimeException('Supplier Quotation item read-back count mismatch.');
    }
    foreach ($expected['items'] as $index => $item) {
        foreach ($fields as $field) {
            if ((string) ($saved['items'][$index][$field] ?? '') !== (string) ($item[$field] ?? '')) {
                throw new RuntimeException('Supplier Quotation item read-back mismatch: ' . $field . '.');
            }
        }
    }
}

function yovel_admin_buying_save_supplier_quotation(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input
): array {
    yovel_admin_buying_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);
    $normalized = yovel_admin_buying_normalize_supplier_quotation($company, $input);
    $quotationKey = yovel_admin_buying_optional_uuid($input, 'supplier_quotation_key', 'Supplier Quotation') ?? bx_uuid();
    $expectedVersion = (int) ($input['expected_version'] ?? 0);

    return yovel_admin_buying_in_transaction($db, static function () use (
        $db, $companyKey, $companyKeyHash, $adminKey, $quotationKey, $expectedVersion, $normalized
    ): array {
        $existing = $db->GetRow(
            'SELECT * FROM project_company_buying_supplier_quotation WHERE company_key_hash = ? AND supplier_quotation_key = ? FOR UPDATE',
            [$companyKeyHash, $quotationKey]
        );
        $existing = is_array($existing) ? $existing : [];
        if ($existing !== [] && (string) $existing['document_status'] !== 'DRAFT') {
            throw new RuntimeException('A submitted Supplier Quotation is immutable.');
        }
        if ($existing !== [] && ($expectedVersion < 1 || (int) $existing['row_version'] !== $expectedVersion)) {
            throw new RuntimeException('Supplier Quotation update is stale.');
        }
        if ($existing === [] && $expectedVersion !== 0) {
            throw new RuntimeException('New Supplier Quotation version must be empty.');
        }
        if ($normalized['rfq_key'] !== null) {
            $rfq = $db->GetRow(
                "SELECT rfq_key, document_status FROM project_company_buying_rfq
                WHERE company_key_hash = ? AND rfq_key = ? FOR UPDATE",
                [$companyKeyHash, $normalized['rfq_key']]
            );
            $recipient = $db->GetRow(
                "SELECT rfq_supplier_key FROM project_company_buying_rfq_supplier
                WHERE company_key_hash = ? AND rfq_key = ? AND supplier_key = ? AND row_status = 'ACTIVE' FOR UPDATE",
                [$companyKeyHash, $normalized['rfq_key'], $normalized['supplier_key']]
            );
            if (!is_array($rfq) || (string) ($rfq['document_status'] ?? '') !== 'SUBMITTED' || !is_array($recipient) || $recipient === []) {
                throw new RuntimeException('Supplier Quotation RFQ mapping changed before save.');
            }
        }
        if ($normalized['supplier_reference'] !== null) {
            $duplicate = $db->GetRow(
                "SELECT supplier_quotation_key FROM project_company_buying_supplier_quotation
                WHERE company_key_hash = ? AND supplier_key = ? AND supplier_reference = ? AND supplier_quotation_key <> ? FOR UPDATE",
                [$companyKeyHash, $normalized['supplier_key'], $normalized['supplier_reference'], $quotationKey]
            );
            if (is_array($duplicate) && $duplicate !== []) {
                throw new RuntimeException('Supplier Quotation supplier reference already exists.');
            }
        }
        $quotationNumber = $existing !== []
            ? (string) $existing['quotation_number']
            : yovel_admin_buying_supplier_quotation_number($db, $companyKey, $companyKeyHash, $adminKey);
        $revisionNo = $existing !== [] ? (int) $existing['revision_no'] : 1;
        $rowVersion = $existing !== [] ? (int) $existing['row_version'] + 1 : 1;
        if ($existing !== []) {
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_buying_supplier_quotation SET
                    supplier_reference = ?, supplier_key = ?, rfq_key = ?, transaction_date = ?, valid_until = ?,
                    currency = ?, conversion_rate = ?, net_total = ?, tax_total = ?, grand_total = ?,
                    calculation_snapshot_json = ?, row_version = ?, updated_by_admin_key = ?
                WHERE company_key_hash = ? AND supplier_quotation_key = ?",
                [
                    $normalized['supplier_reference'], $normalized['supplier_key'], $normalized['rfq_key'],
                    $normalized['transaction_date'], $normalized['valid_until'], $normalized['currency'],
                    $normalized['conversion_rate'], $normalized['net_total'], $normalized['tax_total'],
                    $normalized['grand_total'], $normalized['calculation_snapshot_json'], $rowVersion, $adminKey,
                    $companyKeyHash, $quotationKey,
                ],
                'Supplier Quotation header update'
            );
        } else {
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_supplier_quotation (
                    supplier_quotation_key, company_key, company_key_hash, quotation_number, supplier_reference,
                    supplier_key, rfq_key, transaction_date, valid_until, currency, conversion_rate,
                    net_total, tax_total, grand_total, calculation_snapshot_json, document_status,
                    revision_no, row_version, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, ?, ?, ?)",
                [
                    $quotationKey, $companyKey, $companyKeyHash, $quotationNumber, $normalized['supplier_reference'],
                    $normalized['supplier_key'], $normalized['rfq_key'], $normalized['transaction_date'],
                    $normalized['valid_until'], $normalized['currency'], $normalized['conversion_rate'],
                    $normalized['net_total'], $normalized['tax_total'], $normalized['grand_total'],
                    $normalized['calculation_snapshot_json'], $revisionNo, $rowVersion, $adminKey, $adminKey,
                ],
                'Supplier Quotation header creation'
            );
        }

        $existingItems = $db->GetAll(
            'SELECT supplier_quotation_item_key, line_no FROM project_company_buying_supplier_quotation_item WHERE company_key_hash = ? AND supplier_quotation_key = ? FOR UPDATE',
            [$companyKeyHash, $quotationKey]
        );
        $existingByLine = [];
        foreach (is_array($existingItems) ? $existingItems : [] as $item) {
            $existingByLine[(int) $item['line_no']] = (string) $item['supplier_quotation_item_key'];
        }
        yovel_admin_db_execute(
            $db,
            'DELETE FROM project_company_buying_supplier_quotation_item WHERE company_key_hash = ? AND supplier_quotation_key = ?',
            [$companyKeyHash, $quotationKey],
            'Supplier Quotation item replacement'
        );
        $expectedItems = [];
        foreach ($normalized['items'] as $index => $item) {
            $itemKey = $item['supplier_quotation_item_key'] ?? $existingByLine[$item['line_no']] ?? bx_uuid();
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_supplier_quotation_item (
                    supplier_quotation_item_key, company_key, company_key_hash, supplier_quotation_key, line_no,
                    inventory_item_key, item_code_snapshot, item_name_snapshot, description, quantity,
                    uom_key, uom_snapshot, rate, discount_percentage, net_amount, tax_amount, gross_amount,
                    expected_delivery_date, warehouse_key, rfq_item_key, material_request_key,
                    material_request_item_key, row_status, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                [
                    $itemKey, $companyKey, $companyKeyHash, $quotationKey, $item['line_no'],
                    $item['inventory_item_key'], $item['item_code_snapshot'], $item['item_name_snapshot'],
                    $item['description'], $item['quantity'], $item['uom_key'], $item['uom_snapshot'],
                    $item['rate'], $item['discount_percentage'], $item['net_amount'], $item['tax_amount'],
                    $item['gross_amount'], $item['expected_delivery_date'], $item['warehouse_key'],
                    $item['rfq_item_key'], $item['material_request_key'], $item['material_request_item_key'],
                    $adminKey, $adminKey,
                ],
                'Supplier Quotation item creation'
            );
            yovel_admin_buying_sourcing_fault('supplier-quotation-after-item-write', $index, $item);
            $expectedItems[] = array_replace($item, [
                'supplier_quotation_item_key' => $itemKey,
                'row_status' => 'ACTIVE',
            ]);
        }
        $expected = $normalized + [
            'supplier_quotation_key' => $quotationKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'quotation_number' => $quotationNumber,
            'amended_from_key' => null,
            'document_status' => 'DRAFT',
            'revision_no' => $revisionNo,
            'row_version' => $rowVersion,
            'updated_by_admin_key' => $adminKey,
        ];
        $expected['items'] = $expectedItems;
        $saved = yovel_admin_buying_supplier_quotation_read($db, $companyKeyHash, $quotationKey);
        yovel_admin_buying_assert_supplier_quotation_readback($expected, $saved);
        bx_audit($existing !== [] ? 'UPDATE' : 'CREATE', 'project_company_buying_supplier_quotation', $quotationKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'quotation_number' => $quotationNumber,
            'supplier_key' => $normalized['supplier_key'],
            'rfq_key' => $normalized['rfq_key'],
            'document_status' => 'DRAFT',
            'row_version' => $rowVersion,
            'admin_key' => $adminKey,
        ], 'Company administrator saved a Supplier Quotation.');
        return $saved;
    });
}

function yovel_admin_buying_transition_supplier_quotation(
    ADOConnection $db,
    array $company,
    array $admin,
    string $quotationKey,
    string $action,
    array $input
): array {
    yovel_admin_buying_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);
    if (!yovel_admin_is_uuid($quotationKey)) {
        throw new InvalidArgumentException('Supplier Quotation key is invalid.');
    }
    $quotationKey = strtolower($quotationKey);
    $action = strtoupper(trim($action));
    if (!in_array($action, ['SUBMIT', 'STOP', 'RESUME', 'EXPIRE', 'CANCEL', 'AMEND'], true)) {
        throw new InvalidArgumentException('Supplier Quotation lifecycle action is invalid.');
    }
    $expectedVersion = (int) ($input['expected_version'] ?? 0);
    $asOf = $action === 'EXPIRE'
        ? (string) yovel_admin_buying_sourcing_date($input['as_of'] ?? date('Y-m-d'), 'Supplier Quotation expiry date')
        : date('Y-m-d');

    return yovel_admin_buying_in_transaction($db, static function () use (
        $db, $companyKey, $companyKeyHash, $adminKey, $quotationKey, $action, $expectedVersion, $asOf
    ): array {
        $header = $db->GetRow(
            'SELECT * FROM project_company_buying_supplier_quotation WHERE company_key_hash = ? AND supplier_quotation_key = ? FOR UPDATE',
            [$companyKeyHash, $quotationKey]
        );
        if (!is_array($header) || $header === []) {
            throw new RuntimeException('Supplier Quotation was not found for this company.');
        }
        if ($expectedVersion < 1 || (int) $header['row_version'] !== $expectedVersion) {
            throw new RuntimeException('Supplier Quotation lifecycle update is stale.');
        }
        $items = $db->GetAll(
            "SELECT * FROM project_company_buying_supplier_quotation_item
            WHERE company_key_hash = ? AND supplier_quotation_key = ? AND row_status = 'ACTIVE' FOR UPDATE",
            [$companyKeyHash, $quotationKey]
        );
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('Supplier Quotation has no active items.');
        }
        $current = (string) $header['document_status'];
        $nextVersion = (int) $header['row_version'] + 1;

        if ($action === 'AMEND') {
            if ($current !== 'CANCELLED') {
                throw new RuntimeException('Only a cancelled Supplier Quotation can be amended.');
            }
            $newKey = bx_uuid();
            $newNumber = yovel_admin_buying_supplier_quotation_number($db, $companyKey, $companyKeyHash, $adminKey);
            $newRevision = (int) $header['revision_no'] + 1;
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_supplier_quotation (
                    supplier_quotation_key, company_key, company_key_hash, quotation_number, supplier_reference,
                    supplier_key, rfq_key, transaction_date, valid_until, currency, conversion_rate,
                    net_total, tax_total, grand_total, calculation_snapshot_json, amended_from_key,
                    document_status, revision_no, row_version, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, 1, ?, ?)",
                [
                    $newKey, $companyKey, $companyKeyHash, $newNumber, $header['supplier_key'], $header['rfq_key'],
                    $header['transaction_date'], $header['valid_until'], $header['currency'], $header['conversion_rate'],
                    $header['net_total'], $header['tax_total'], $header['grand_total'], $header['calculation_snapshot_json'],
                    $quotationKey, $newRevision, $adminKey, $adminKey,
                ],
                'Supplier Quotation amendment creation'
            );
            foreach ($items as $item) {
                yovel_admin_db_execute(
                    $db,
                    "INSERT INTO project_company_buying_supplier_quotation_item (
                        supplier_quotation_item_key, company_key, company_key_hash, supplier_quotation_key, line_no,
                        inventory_item_key, item_code_snapshot, item_name_snapshot, description, quantity,
                        uom_key, uom_snapshot, rate, discount_percentage, net_amount, tax_amount, gross_amount,
                        expected_delivery_date, warehouse_key, rfq_item_key, material_request_key,
                        material_request_item_key, row_status, created_by_admin_key, updated_by_admin_key
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                    [
                        bx_uuid(), $companyKey, $companyKeyHash, $newKey, $item['line_no'], $item['inventory_item_key'],
                        $item['item_code_snapshot'], $item['item_name_snapshot'], $item['description'], $item['quantity'],
                        $item['uom_key'], $item['uom_snapshot'], $item['rate'], $item['discount_percentage'],
                        $item['net_amount'], $item['tax_amount'], $item['gross_amount'], $item['expected_delivery_date'],
                        $item['warehouse_key'], $item['rfq_item_key'], $item['material_request_key'],
                        $item['material_request_item_key'], $adminKey, $adminKey,
                    ],
                    'Supplier Quotation amendment item creation'
                );
            }
            $amended = yovel_admin_buying_supplier_quotation_read($db, $companyKeyHash, $newKey);
            if (!is_array($amended) || (string) $amended['amended_from_key'] !== $quotationKey || (int) $amended['row_version'] !== 1) {
                throw new RuntimeException('Supplier Quotation amendment exact read-back failed.');
            }
            bx_audit('AMEND', 'project_company_buying_supplier_quotation', $quotationKey, [
                'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash,
                'quotation_number' => $header['quotation_number'],
                'amended_quotation_key' => $newKey,
                'amended_quotation_number' => $newNumber,
                'admin_key' => $adminKey,
            ], 'Company administrator amended a cancelled Supplier Quotation.');
            return $amended;
        }

        $nextStatus = match ($action) {
            'SUBMIT' => $current === 'DRAFT' ? 'SUBMITTED' : throw new RuntimeException('Only a draft Supplier Quotation can be submitted.'),
            'STOP' => $current === 'SUBMITTED' ? 'STOPPED' : throw new RuntimeException('Only a submitted Supplier Quotation can be stopped.'),
            'RESUME' => $current === 'STOPPED' ? 'SUBMITTED' : throw new RuntimeException('Only a stopped Supplier Quotation can be resumed.'),
            'EXPIRE' => in_array($current, ['SUBMITTED', 'STOPPED'], true) ? 'EXPIRED' : throw new RuntimeException('Only an active Supplier Quotation can expire.'),
            'CANCEL' => in_array($current, ['SUBMITTED', 'STOPPED', 'EXPIRED'], true) ? 'CANCELLED' : throw new RuntimeException('Only an active Supplier Quotation can be cancelled.'),
        };
        if ($action === 'EXPIRE' && ($header['valid_until'] === null || $asOf <= (string) $header['valid_until'])) {
            throw new RuntimeException('Supplier Quotation is still valid and cannot expire.');
        }
        if ($action === 'SUBMIT' && $header['rfq_key'] !== null) {
            $recipient = $db->GetRow(
                "SELECT rfq_supplier_key FROM project_company_buying_rfq_supplier
                WHERE company_key_hash = ? AND rfq_key = ? AND supplier_key = ? AND row_status = 'ACTIVE' FOR UPDATE",
                [$companyKeyHash, $header['rfq_key'], $header['supplier_key']]
            );
            if (!is_array($recipient) || $recipient === []) {
                throw new RuntimeException('Supplier Quotation RFQ recipient mapping changed before submit.');
            }
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_buying_rfq_supplier SET delivery_status = 'RECEIVED', received_at = CURRENT_TIMESTAMP,
                    updated_by_admin_key = ? WHERE company_key_hash = ? AND rfq_supplier_key = ?",
                [$adminKey, $companyKeyHash, $recipient['rfq_supplier_key']],
                'Supplier Quotation RFQ receipt projection'
            );
            yovel_admin_buying_sourcing_fault('supplier-quotation-after-rfq-receipt', 0, $header);
        }
        $submittedBy = $action === 'SUBMIT' ? $adminKey : $header['submitted_by_admin_key'];
        $cancelledBy = $action === 'CANCEL' ? $adminKey : $header['cancelled_by_admin_key'];
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_buying_supplier_quotation SET document_status = ?, row_version = ?,
                updated_by_admin_key = ?, submitted_by_admin_key = ?, cancelled_by_admin_key = ?,
                submitted_at = CASE WHEN ? = 'SUBMIT' THEN CURRENT_TIMESTAMP ELSE submitted_at END,
                cancelled_at = CASE WHEN ? = 'CANCEL' THEN CURRENT_TIMESTAMP ELSE cancelled_at END
            WHERE company_key_hash = ? AND supplier_quotation_key = ?",
            [$nextStatus, $nextVersion, $adminKey, $submittedBy, $cancelledBy, $action, $action, $companyKeyHash, $quotationKey],
            'Supplier Quotation lifecycle update'
        );
        $saved = yovel_admin_buying_supplier_quotation_read($db, $companyKeyHash, $quotationKey);
        if (!is_array($saved) || (string) $saved['document_status'] !== $nextStatus || (int) $saved['row_version'] !== $nextVersion) {
            throw new RuntimeException('Supplier Quotation lifecycle exact read-back failed.');
        }
        bx_audit($action, 'project_company_buying_supplier_quotation', $quotationKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'quotation_number' => $header['quotation_number'],
            'document_status' => $nextStatus,
            'row_version' => $nextVersion,
            'admin_key' => $adminKey,
        ], 'Company administrator changed a Supplier Quotation lifecycle state.');
        return $saved;
    });
}

function yovel_admin_buying_map_rfq_to_supplier_quotation(array $company, array $rfq, string $supplierKey): array
{
    yovel_admin_buying_read_scope($company);
    $rfqKey = trim((string) ($rfq['rfq_key'] ?? ''));
    if (!yovel_admin_is_uuid($rfqKey) || (string) ($rfq['document_status'] ?? '') !== 'SUBMITTED') {
        throw new InvalidArgumentException('Supplier Quotation mapping requires a submitted RFQ.');
    }
    if (!yovel_admin_is_uuid($supplierKey)) {
        throw new InvalidArgumentException('Supplier Quotation mapping supplier is invalid.');
    }
    $savedRfq = yovel_admin_buying_rfq($company, strtolower($rfqKey));
    if (!is_array($savedRfq) || (string) $savedRfq['document_status'] !== 'SUBMITTED') {
        throw new RuntimeException('Supplier Quotation source RFQ is not submitted for this company.');
    }
    $invited = false;
    foreach ($savedRfq['suppliers'] as $recipient) {
        if ((string) $recipient['supplier_key'] === strtolower($supplierKey)) {
            $invited = true;
            break;
        }
    }
    $supplier = yovel_admin_buying_supplier($company, strtolower($supplierKey));
    if (!$invited || !is_array($supplier) || (string) $supplier['supplier_status'] !== 'ACTIVE') {
        throw new RuntimeException('Supplier Quotation mapping supplier was not invited and active.');
    }
    return [
        'supplier_key' => strtolower($supplierKey),
        'rfq_key' => strtolower($rfqKey),
        'transaction_date' => date('Y-m-d'),
        'valid_until' => null,
        'currency' => (string) ($supplier['default_currency'] ?: 'PHP'),
        'conversion_rate' => '1.00000000',
        'items' => array_map(static fn (array $item): array => [
            'inventory_item_key' => (string) $item['inventory_item_key'],
            'uom_key' => (string) $item['uom_key'],
            'quantity' => (string) $item['quantity'],
            'rate' => '0.000000',
            'discount_percentage' => '0.0000',
            'expected_delivery_date' => (string) ($item['schedule_date'] ?? ''),
            'warehouse_key' => $item['warehouse_key'] ?? null,
            'rfq_key' => strtolower($rfqKey),
            'rfq_item_key' => (string) $item['rfq_item_key'],
            'material_request_key' => $item['material_request_key'] ?? null,
            'material_request_item_key' => $item['material_request_item_key'] ?? null,
        ], $savedRfq['items']),
    ];
}

function yovel_admin_buying_supplier_quotation_comparison_inputs(array $company, string $rfqKey): array
{
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    if (!yovel_admin_is_uuid($rfqKey)) {
        throw new InvalidArgumentException('Supplier Quotation comparison RFQ key is invalid.');
    }
    $rfq = yovel_admin_buying_rfq($company, strtolower($rfqKey));
    if (!is_array($rfq)) {
        throw new RuntimeException('Supplier Quotation comparison RFQ was not found.');
    }
    $keys = bx_db()->GetCol(
        "SELECT supplier_quotation_key FROM project_company_buying_supplier_quotation
        WHERE company_key_hash = ? AND rfq_key = ? AND document_status = 'SUBMITTED'
          AND (valid_until IS NULL OR valid_until >= CURRENT_DATE)
        ORDER BY grand_total, x_id",
        [$companyKeyHash, strtolower($rfqKey)]
    );
    $quotations = [];
    foreach (is_array($keys) ? $keys : [] as $key) {
        $quotation = yovel_admin_buying_supplier_quotation_read(bx_db(), $companyKeyHash, (string) $key);
        if (is_array($quotation)) {
            $quotations[] = $quotation;
        }
    }
    $items = [];
    foreach ($rfq['items'] as $rfqItem) {
        $offers = [];
        foreach ($quotations as $quotation) {
            foreach ($quotation['items'] as $quotationItem) {
                if ((string) $quotationItem['rfq_item_key'] === (string) $rfqItem['rfq_item_key']) {
                    $offers[] = [
                        'supplier_quotation_key' => (string) $quotation['supplier_quotation_key'],
                        'supplier_key' => (string) $quotation['supplier_key'],
                        'currency' => (string) $quotation['currency'],
                        'rate' => (string) $quotationItem['rate'],
                        'discount_percentage' => (string) $quotationItem['discount_percentage'],
                        'net_amount' => (string) $quotationItem['net_amount'],
                        'tax_amount' => (string) $quotationItem['tax_amount'],
                        'grand_amount' => (string) $quotationItem['gross_amount'],
                        'expected_delivery_date' => $quotationItem['expected_delivery_date'],
                        'valid_until' => $quotation['valid_until'],
                    ];
                }
            }
        }
        $items[] = [
            'rfq_item_key' => (string) $rfqItem['rfq_item_key'],
            'inventory_item_key' => (string) $rfqItem['inventory_item_key'],
            'item_code_snapshot' => (string) $rfqItem['item_code_snapshot'],
            'item_name_snapshot' => (string) $rfqItem['item_name_snapshot'],
            'quantity' => (string) $rfqItem['quantity'],
            'uom_snapshot' => (string) $rfqItem['uom_snapshot'],
            'quotations' => $offers,
        ];
    }
    return ['rfq' => $rfq, 'quotations' => $quotations, 'items' => $items];
}
