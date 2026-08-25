<?php
declare(strict_types=1);

function yovel_admin_manufacturing_bom_context(array $context): array
{
    $company = $context['company'] ?? null;
    $admin = $context['admin'] ?? null;
    if (!is_array($company) || !is_array($admin)) {
        throw new InvalidArgumentException('Manufacturing BOM company and administrator context is required.');
    }
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    $gateway = is_array($context['gateway'] ?? null)
        ? $context['gateway']
        : yovel_admin_manufacturing_dependency_gateway();
    $alternatives = is_array($context['alternatives'] ?? null) ? $context['alternatives'] : [];
    return [$company, $admin, $companyKey, $companyKeyHash, $adminKey, $gateway, $alternatives];
}

function yovel_admin_manufacturing_bom_rows(array $input, string $field): array
{
    $rows = $input[$field] ?? [];
    if (is_string($rows)) {
        $rows = json_decode($rows !== '' ? $rows : '[]', true, 512, JSON_THROW_ON_ERROR);
    }
    if (!is_array($rows) || count($rows) > 500) {
        throw new InvalidArgumentException('Manufacturing BOM ' . str_replace('_', ' ', $field) . ' must be a bounded structured list.');
    }
    return array_values($rows);
}

function yovel_admin_manufacturing_bom_key(mixed $value, string $label, bool $required = true): string
{
    $key = strtolower(trim((string) $value));
    if ($key === '' && !$required) {
        return '';
    }
    if (!yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $key;
}

function yovel_admin_manufacturing_bom_opaque_key(mixed $value, string $label, int $maximum = 1500): string
{
    $key = trim((string) $value);
    if ($key === '' || strlen($key) > $maximum) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $key;
}

function yovel_admin_manufacturing_bom_code(mixed $value): string
{
    $code = strtoupper(trim((string) $value));
    if ($code === '' || strlen($code) > 80 || preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $code) !== 1) {
        throw new InvalidArgumentException('BOM code must be a valid 1 to 80 character code.');
    }
    return $code;
}

function yovel_admin_manufacturing_bom_decimal(mixed $value, string $label, int $scale, bool $positive = false): string
{
    $value = trim((string) $value);
    if ($value === '' || preg_match('/^\d{1,12}(?:\.\d{1,9})?$/', $value) !== 1) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    $normalized = bcadd($value, '0', $scale);
    if ($positive && bccomp($normalized, '0', $scale) !== 1) {
        throw new InvalidArgumentException($label . ' must be greater than zero.');
    }
    return $normalized;
}

function yovel_admin_manufacturing_bom_date(mixed $value, string $label, bool $required = true): string
{
    $value = trim((string) $value);
    if ($value === '' && !$required) {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function yovel_admin_manufacturing_bom_item(array $company, array $gateway, string $itemKey): array
{
    $item = yovel_admin_manufacturing_dependency_call($gateway, 'item_lookup', [$company, $itemKey]);
    if (!is_array($item) || (string) ($item['item_key'] ?? '') !== $itemKey || (string) ($item['item_status'] ?? '') !== 'ACTIVE') {
        throw new InvalidArgumentException('BOM item must be an active company Inventory item.');
    }
    return $item;
}

function yovel_admin_manufacturing_bom_uom(array $company, array $gateway, string $itemKey, string $uomKey, string $quantity): array
{
    $resolved = yovel_admin_manufacturing_dependency_call($gateway, 'item_uom_resolve', [$company, $itemKey, $uomKey, $quantity]);
    foreach (['quantity', 'conversion_factor', 'stock_quantity', 'stock_uom_code'] as $field) {
        if (!is_array($resolved) || trim((string) ($resolved[$field] ?? '')) === '') {
            throw new InvalidArgumentException('BOM item UOM could not be resolved by Inventory.');
        }
    }
    return [
        'quantity' => yovel_admin_manufacturing_bom_decimal($resolved['quantity'], 'BOM quantity', 9, true),
        'conversion_factor' => yovel_admin_manufacturing_bom_decimal($resolved['conversion_factor'], 'BOM conversion factor', 9, true),
        'stock_quantity' => yovel_admin_manufacturing_bom_decimal($resolved['stock_quantity'], 'BOM stock quantity', 9, true),
        'stock_uom_code' => yovel_admin_manufacturing_bom_opaque_key($resolved['stock_uom_code'], 'BOM stock UOM', 80),
    ];
}

function yovel_admin_manufacturing_bom_children_checksum(array $components, array $operations, array $secondaryItems): string
{
    return hash('sha256', yovel_admin_manufacturing_json([
        'components' => array_values($components),
        'operations' => array_values($operations),
        'secondary_items' => array_values($secondaryItems),
    ]));
}

function yovel_admin_manufacturing_bom_row(ADOConnection $db, string $companyKeyHash, string $bomKey): array
{
    $row = $db->GetRow(
        'SELECT * FROM project_company_manufacturing_bom WHERE company_key_hash=? AND bom_key=? LIMIT 1',
        [$companyKeyHash, $bomKey]
    );
    if (!is_array($row) || $row === []) {
        throw new InvalidArgumentException('Manufacturing BOM is not owned by this company.');
    }
    $components = $db->GetAll(
        'SELECT component_key, item_key, child_bom_key, sequence_no AS sequence, quantity, uom_key, conversion_factor, stock_quantity, stock_uom_code FROM project_company_manufacturing_bom_component WHERE company_key_hash=? AND bom_key=? ORDER BY sequence_no, x_id',
        [$companyKeyHash, $bomKey]
    );
    $operations = $db->GetAll(
        'SELECT bom_operation_key, operation_key, sequence_no AS sequence, duration_minutes, hourly_rate FROM project_company_manufacturing_bom_operation WHERE company_key_hash=? AND bom_key=? ORDER BY sequence_no, x_id',
        [$companyKeyHash, $bomKey]
    );
    $secondaryItems = $db->GetAll(
        'SELECT secondary_item_key, item_key, secondary_type, sequence_no AS sequence, quantity, uom_key, conversion_factor, stock_quantity, stock_uom_code FROM project_company_manufacturing_bom_secondary_item WHERE company_key_hash=? AND bom_key=? ORDER BY sequence_no, x_id',
        [$companyKeyHash, $bomKey]
    );
    $row['components'] = is_array($components) ? array_values($components) : [];
    $row['operations'] = is_array($operations) ? array_values($operations) : [];
    $row['secondary_items'] = is_array($secondaryItems) ? array_values($secondaryItems) : [];
    foreach (['alternative_for_bom_key', 'amended_from_bom_key', 'submitted_version_key', 'expense_account_key', 'cost_calculation_hash', 'notes', 'cancellation_reason'] as $field) {
        $row[$field] = (string) ($row[$field] ?? '');
    }
    $decodedInputs = json_decode((string) ($row['cost_inputs_json'] ?? ''), true);
    $row['cost_inputs'] = is_array($decodedInputs) ? $decodedInputs : [];
    $componentChecksumRows = array_map(static fn (array $child): array => [
        'item_key' => (string) $child['item_key'], 'child_bom_key' => (string) ($child['child_bom_key'] ?? ''),
        'sequence' => (int) $child['sequence'], 'quantity' => (string) $child['quantity'],
        'uom_key' => (string) $child['uom_key'], 'conversion_factor' => (string) $child['conversion_factor'],
        'stock_quantity' => (string) $child['stock_quantity'], 'stock_uom_code' => (string) $child['stock_uom_code'],
    ], $row['components']);
    $operationChecksumRows = array_map(static fn (array $child): array => [
        'operation_key' => (string) $child['operation_key'], 'sequence' => (int) $child['sequence'],
        'duration_minutes' => (string) $child['duration_minutes'], 'hourly_rate' => (string) $child['hourly_rate'],
    ], $row['operations']);
    $secondaryChecksumRows = array_map(static fn (array $child): array => [
        'item_key' => (string) $child['item_key'], 'secondary_type' => (string) $child['secondary_type'],
        'sequence' => (int) $child['sequence'], 'quantity' => (string) $child['quantity'],
        'uom_key' => (string) $child['uom_key'], 'conversion_factor' => (string) $child['conversion_factor'],
        'stock_quantity' => (string) $child['stock_quantity'], 'stock_uom_code' => (string) $child['stock_uom_code'],
    ], $row['secondary_items']);
    $row['children_checksum'] = yovel_admin_manufacturing_bom_children_checksum($componentChecksumRows, $operationChecksumRows, $secondaryChecksumRows);
    return $row;
}

function yovel_admin_manufacturing_bom_assert_acyclic(ADOConnection $db, string $companyKeyHash, string $rootBomKey): void
{
    $visiting = [];
    $visited = [];
    $walk = static function (string $bomKey) use (&$walk, &$visiting, &$visited, $db, $companyKeyHash): void {
        if (isset($visiting[$bomKey])) {
            throw new InvalidArgumentException('Manufacturing BOM cycle detected.');
        }
        if (isset($visited[$bomKey])) {
            return;
        }
        $visiting[$bomKey] = true;
        $children = $db->GetAll(
            'SELECT child_bom_key FROM project_company_manufacturing_bom_component WHERE company_key_hash=? AND bom_key=? AND child_bom_key IS NOT NULL ORDER BY x_id',
            [$companyKeyHash, $bomKey]
        );
        foreach (is_array($children) ? $children : [] as $child) {
            $childKey = (string) ($child['child_bom_key'] ?? '');
            if ($childKey !== '') {
                $walk($childKey);
            }
        }
        unset($visiting[$bomKey]);
        $visited[$bomKey] = true;
    };
    $walk($rootBomKey);
}

function yovel_admin_manufacturing_save_bom(array $context, array $input): array
{
    [$company, , $companyKey, $companyKeyHash, $adminKey, $gateway] = yovel_admin_manufacturing_bom_context($context);
    yovel_admin_manufacturing_schema();
    $requestedKey = yovel_admin_manufacturing_bom_key($input['bom_key'] ?? '', 'BOM key', false);
    $code = yovel_admin_manufacturing_bom_code($input['bom_code'] ?? '');
    $itemKey = yovel_admin_manufacturing_bom_opaque_key($input['item_key'] ?? '', 'BOM finished item');
    yovel_admin_manufacturing_bom_item($company, $gateway, $itemKey);
    $quantity = yovel_admin_manufacturing_bom_decimal($input['quantity'] ?? '', 'BOM quantity', 9, true);
    $uomKey = yovel_admin_manufacturing_bom_opaque_key($input['uom_key'] ?? '', 'BOM UOM', 80);
    yovel_admin_manufacturing_bom_uom($company, $gateway, $itemKey, $uomKey, $quantity);
    $revision = (int) ($input['revision'] ?? 1);
    if ($revision < 1 || $revision > 100000) {
        throw new InvalidArgumentException('BOM revision is invalid.');
    }
    $currency = strtoupper(trim((string) ($input['currency'] ?? 'PHP')));
    if (preg_match('/^[A-Z]{3,20}$/', $currency) !== 1) {
        throw new InvalidArgumentException('BOM currency is invalid.');
    }
    $effectiveFrom = yovel_admin_manufacturing_bom_date($input['effective_from'] ?? '', 'BOM effective from date');
    $effectiveTo = yovel_admin_manufacturing_bom_date($input['effective_to'] ?? '', 'BOM effective to date', false);
    if ($effectiveTo !== '' && $effectiveTo < $effectiveFrom) {
        throw new InvalidArgumentException('BOM effective date range is invalid.');
    }
    $processLoss = yovel_admin_manufacturing_bom_decimal($input['process_loss_percent'] ?? '0', 'BOM process loss percent', 4);
    if (bccomp($processLoss, '100.0000', 4) === 1) {
        throw new InvalidArgumentException('BOM process loss percent cannot exceed 100.');
    }
    $isAlternative = in_array($input['is_alternative'] ?? null, [1, '1', true, 'true', 'on', 'yes'], true) ? 1 : 0;
    $alternativeFor = yovel_admin_manufacturing_bom_key($input['alternative_for_bom_key'] ?? '', 'Alternative BOM reference', false);
    if (($isAlternative === 1) !== ($alternativeFor !== '')) {
        throw new InvalidArgumentException('Alternative BOM linkage is invalid.');
    }
    $expenseAccount = trim((string) ($input['expense_account_key'] ?? ''));
    if ($expenseAccount !== '') {
        $expenseAccount = yovel_admin_manufacturing_capacity_account(bx_db(), $company, $expenseAccount, 'BOM expense account', $gateway);
    }
    $notes = trim((string) ($input['notes'] ?? ''));
    if (strlen($notes) > 2000) {
        throw new InvalidArgumentException('BOM notes can contain up to 2000 characters.');
    }

    $components = [];
    $componentItems = [];
    foreach (yovel_admin_manufacturing_bom_rows($input, 'components') as $index => $child) {
        if (!is_array($child)) {
            throw new InvalidArgumentException('BOM component row is invalid.');
        }
        $childItemKey = yovel_admin_manufacturing_bom_opaque_key($child['item_key'] ?? '', 'BOM component item');
        if (isset($componentItems[$childItemKey])) {
            throw new InvalidArgumentException('BOM duplicate component item is not allowed.');
        }
        $componentItems[$childItemKey] = true;
        yovel_admin_manufacturing_bom_item($company, $gateway, $childItemKey);
        $childQuantity = yovel_admin_manufacturing_bom_decimal($child['quantity'] ?? '', 'BOM component quantity', 9, true);
        $childUom = yovel_admin_manufacturing_bom_opaque_key($child['uom_key'] ?? '', 'BOM component UOM', 80);
        $resolved = yovel_admin_manufacturing_bom_uom($company, $gateway, $childItemKey, $childUom, $childQuantity);
        $components[] = [
            'item_key' => $childItemKey,
            'child_bom_key' => yovel_admin_manufacturing_bom_key($child['child_bom_key'] ?? '', 'Nested BOM reference', false),
            'sequence' => ($index + 1) * 10,
            'quantity' => $resolved['quantity'], 'uom_key' => $childUom,
            'conversion_factor' => $resolved['conversion_factor'], 'stock_quantity' => $resolved['stock_quantity'],
            'stock_uom_code' => $resolved['stock_uom_code'],
        ];
    }

    $operations = [];
    $operationKeys = [];
    $operationSequences = [];
    foreach (yovel_admin_manufacturing_bom_rows($input, 'operations') as $index => $child) {
        if (!is_array($child)) {
            throw new InvalidArgumentException('BOM operation row is invalid.');
        }
        $operationKey = yovel_admin_manufacturing_bom_key($child['operation_key'] ?? '', 'BOM operation');
        $sequence = (int) ($child['sequence'] ?? (($index + 1) * 10));
        if ($sequence < 1 || $sequence > 100000 || isset($operationKeys[$operationKey]) || isset($operationSequences[$sequence])) {
            throw new InvalidArgumentException('BOM operations require unique operation and sequence values.');
        }
        $operationKeys[$operationKey] = true;
        $operationSequences[$sequence] = true;
        $operation = yovel_admin_manufacturing_operation_row(bx_db(), $companyKeyHash, $operationKey);
        if ((string) $operation['record_status'] !== 'ACTIVE') {
            throw new InvalidArgumentException('BOM operation must be active.');
        }
        $operations[] = [
            'operation_key' => $operationKey, 'sequence' => $sequence,
            'duration_minutes' => yovel_admin_manufacturing_bom_decimal($child['duration_minutes'] ?? '', 'BOM operation duration', 6, true),
            'hourly_rate' => yovel_admin_manufacturing_bom_decimal($operation['hourly_rate'] ?? '0', 'BOM operation rate', 6),
        ];
    }
    usort($operations, static fn (array $left, array $right): int => [$left['sequence'], $left['operation_key']] <=> [$right['sequence'], $right['operation_key']]);

    $secondaryItems = [];
    $secondaryRefs = [];
    foreach (yovel_admin_manufacturing_bom_rows($input, 'secondary_items') as $index => $child) {
        if (!is_array($child)) {
            throw new InvalidArgumentException('BOM secondary item row is invalid.');
        }
        $secondaryItemKey = yovel_admin_manufacturing_bom_opaque_key($child['item_key'] ?? '', 'BOM secondary item');
        $secondaryType = strtoupper(trim((string) ($child['secondary_type'] ?? 'SCRAP')));
        $reference = $secondaryType . ':' . $secondaryItemKey;
        if (!in_array($secondaryType, ['SCRAP', 'BY_PRODUCT', 'PROCESS_LOSS'], true) || isset($secondaryRefs[$reference])) {
            throw new InvalidArgumentException('BOM secondary item type or duplicate is invalid.');
        }
        $secondaryRefs[$reference] = true;
        yovel_admin_manufacturing_bom_item($company, $gateway, $secondaryItemKey);
        $secondaryQuantity = yovel_admin_manufacturing_bom_decimal($child['quantity'] ?? '', 'BOM secondary item quantity', 9, true);
        $secondaryUom = yovel_admin_manufacturing_bom_opaque_key($child['uom_key'] ?? '', 'BOM secondary item UOM', 80);
        $resolved = yovel_admin_manufacturing_bom_uom($company, $gateway, $secondaryItemKey, $secondaryUom, $secondaryQuantity);
        $secondaryItems[] = [
            'item_key' => $secondaryItemKey, 'secondary_type' => $secondaryType, 'sequence' => ($index + 1) * 10,
            'quantity' => $resolved['quantity'], 'uom_key' => $secondaryUom,
            'conversion_factor' => $resolved['conversion_factor'], 'stock_quantity' => $resolved['stock_quantity'],
            'stock_uom_code' => $resolved['stock_uom_code'],
        ];
    }

    $saved = yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'BOM',
        'CREATE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $requestedKey, $code, $itemKey, $revision, $quantity, $uomKey, $currency, $isAlternative, $alternativeFor, $effectiveFrom, $effectiveTo, $processLoss, $expenseAccount, $notes, $components, $operations, $secondaryItems): array {
            $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_manufacturing_bom WHERE company_key_hash=? AND bom_key=? FOR UPDATE', [$companyKeyHash, $requestedKey]) : false;
            $isUpdate = is_array($existing) && $existing !== [];
            if ($requestedKey !== '' && !$isUpdate) {
                throw new InvalidArgumentException('Manufacturing BOM is not owned by this company.');
            }
            if ($isUpdate && (string) $existing['lifecycle_status'] !== 'DRAFT') {
                throw new InvalidArgumentException('Submitted revision is immutable and cannot be edited.');
            }
            $bomKey = $isUpdate ? (string) $existing['bom_key'] : bx_uuid();
            if ($alternativeFor === $bomKey) {
                throw new InvalidArgumentException('Alternative BOM cannot reference itself.');
            }
            if ($alternativeFor !== '') {
                $base = $db->GetRow('SELECT item_key, is_alternative FROM project_company_manufacturing_bom WHERE company_key_hash=? AND bom_key=? LIMIT 1', [$companyKeyHash, $alternativeFor]);
                if (!is_array($base) || (string) ($base['item_key'] ?? '') !== $itemKey || (int) ($base['is_alternative'] ?? 1) !== 0) {
                    throw new InvalidArgumentException('Alternative BOM must reference a company-owned base BOM for the same item.');
                }
            }
            foreach ($components as $component) {
                $childBomKey = (string) $component['child_bom_key'];
                if ($childBomKey === '') {
                    continue;
                }
                $childBom = $db->GetRow('SELECT item_key FROM project_company_manufacturing_bom WHERE company_key_hash=? AND bom_key=? LIMIT 1', [$companyKeyHash, $childBomKey]);
                if (!is_array($childBom) || (string) ($childBom['item_key'] ?? '') !== (string) $component['item_key']) {
                    throw new InvalidArgumentException('Nested BOM must be company-owned and produce its component item.');
                }
            }
            yovel_admin_manufacturing_execute(
                $db,
                "INSERT INTO project_company_manufacturing_bom (bom_key, company_key, company_key_hash, bom_code, item_key, revision, quantity, uom_key, currency, is_alternative, alternative_for_bom_key, effective_from, effective_to, process_loss_percent, lifecycle_status, expense_account_key, material_cost, operation_cost, process_loss_cost, scrap_credit, total_cost, cost_inputs_json, cost_calculation_hash, notes, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, 0, 0, 0, 0, 0, NULL, NULL, ?, ?, ?) ON DUPLICATE KEY UPDATE bom_code=VALUES(bom_code), item_key=VALUES(item_key), revision=VALUES(revision), quantity=VALUES(quantity), uom_key=VALUES(uom_key), currency=VALUES(currency), is_alternative=VALUES(is_alternative), alternative_for_bom_key=VALUES(alternative_for_bom_key), effective_from=VALUES(effective_from), effective_to=VALUES(effective_to), process_loss_percent=VALUES(process_loss_percent), expense_account_key=VALUES(expense_account_key), material_cost=0, operation_cost=0, process_loss_cost=0, scrap_credit=0, total_cost=0, cost_inputs_json=NULL, cost_calculation_hash=NULL, notes=VALUES(notes), updated_by_admin_key=VALUES(updated_by_admin_key)",
                [$bomKey, $companyKey, $companyKeyHash, $code, $itemKey, $revision, $quantity, $uomKey, $currency, $isAlternative, $alternativeFor !== '' ? $alternativeFor : null, $effectiveFrom, $effectiveTo !== '' ? $effectiveTo : null, $processLoss, $expenseAccount !== '' ? $expenseAccount : null, $notes !== '' ? $notes : null, $adminKey, $adminKey],
                'Manufacturing BOM save'
            );
            foreach (['project_company_manufacturing_bom_component', 'project_company_manufacturing_bom_operation', 'project_company_manufacturing_bom_secondary_item'] as $table) {
                yovel_admin_manufacturing_execute($db, "DELETE FROM `{$table}` WHERE company_key_hash=? AND bom_key=?", [$companyKeyHash, $bomKey], 'Manufacturing BOM child replace');
            }
            foreach ($components as $component) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_bom_component (component_key, company_key, company_key_hash, bom_key, item_key, child_bom_key, sequence_no, quantity, uom_key, conversion_factor, stock_quantity, stock_uom_code, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $bomKey, $component['item_key'], $component['child_bom_key'] !== '' ? $component['child_bom_key'] : null, $component['sequence'], $component['quantity'], $component['uom_key'], $component['conversion_factor'], $component['stock_quantity'], $component['stock_uom_code'], $adminKey], 'Manufacturing BOM component save');
            }
            foreach ($operations as $operation) {
                yovel_admin_manufacturing_operation_row($db, $companyKeyHash, (string) $operation['operation_key']);
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_bom_operation (bom_operation_key, company_key, company_key_hash, bom_key, operation_key, sequence_no, duration_minutes, hourly_rate, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $bomKey, $operation['operation_key'], $operation['sequence'], $operation['duration_minutes'], $operation['hourly_rate'], $adminKey], 'Manufacturing BOM operation save');
            }
            foreach ($secondaryItems as $secondary) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_bom_secondary_item (secondary_item_key, company_key, company_key_hash, bom_key, item_key, secondary_type, sequence_no, quantity, uom_key, conversion_factor, stock_quantity, stock_uom_code, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $bomKey, $secondary['item_key'], $secondary['secondary_type'], $secondary['sequence'], $secondary['quantity'], $secondary['uom_key'], $secondary['conversion_factor'], $secondary['stock_quantity'], $secondary['stock_uom_code'], $adminKey], 'Manufacturing BOM secondary item save');
            }
            yovel_admin_manufacturing_bom_assert_acyclic($db, $companyKeyHash, $bomKey);
            return [
                'record_key' => $bomKey, 'business_key' => $code, 'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey,
                'audit_action' => $isUpdate ? 'UPDATE' : 'CREATE',
                'persisted_fields' => [
                    'bom_key' => $bomKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                    'bom_code' => $code, 'item_key' => $itemKey, 'revision' => $revision, 'quantity' => $quantity,
                    'uom_key' => $uomKey, 'currency' => $currency, 'is_alternative' => $isAlternative,
                    'alternative_for_bom_key' => $alternativeFor, 'effective_from' => $effectiveFrom,
                    'effective_to' => $effectiveTo, 'process_loss_percent' => $processLoss,
                    'lifecycle_status' => 'DRAFT', 'expense_account_key' => $expenseAccount,
                    'material_cost' => '0.000000', 'operation_cost' => '0.000000', 'process_loss_cost' => '0.000000',
                    'scrap_credit' => '0.000000', 'total_cost' => '0.000000', 'cost_calculation_hash' => '',
                    'notes' => $notes, 'children_checksum' => yovel_admin_manufacturing_bom_children_checksum($components, $operations, $secondaryItems),
                ],
            ];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_bom_row($db, (string) $expected['company_key_hash'], (string) $expected['record_key'])
    );
    return yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, (string) $saved['bom_key']) + ['_audit_action' => $saved['_audit_action']];
}

function yovel_admin_manufacturing_boms(array $company): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $keys = bx_db()->GetAll('SELECT bom_key FROM project_company_manufacturing_bom WHERE company_key_hash=? ORDER BY bom_code, revision, x_id', [$companyKeyHash]);
    return array_map(static fn (array $row): array => yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, (string) $row['bom_key']), is_array($keys) ? $keys : []);
}

function yovel_admin_manufacturing_bom(array $company, string $bomKey): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    return yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, yovel_admin_manufacturing_bom_key($bomKey, 'BOM key'));
}

function yovel_admin_manufacturing_bom_effective(array $bom, string $valuationDate): void
{
    if ($valuationDate < (string) $bom['effective_from'] || ((string) ($bom['effective_to'] ?? '') !== '' && $valuationDate > (string) $bom['effective_to'])) {
        throw new InvalidArgumentException('Manufacturing BOM is not effective on the valuation date.');
    }
}

function yovel_admin_manufacturing_explode_bom(array $context, string $bomKey, ?string $valuationDate = null): array
{
    [$company, , , $companyKeyHash, , $gateway, $alternatives] = yovel_admin_manufacturing_bom_context($context);
    $valuationDate = yovel_admin_manufacturing_bom_date($valuationDate ?? date('Y-m-d'), 'BOM valuation date');
    $bomKey = yovel_admin_manufacturing_bom_key($bomKey, 'BOM key');
    $items = [];
    $operations = [];
    $secondaryItems = [];
    $path = [];
    $walk = static function (string $currentKey, string $factor, bool $root = false) use (&$walk, &$items, &$operations, &$secondaryItems, &$path, $company, $companyKeyHash, $gateway, $valuationDate, $alternatives): void {
        $selectedKey = $currentKey;
        if (!$root && isset($alternatives[$currentKey])) {
            $candidate = yovel_admin_manufacturing_bom_key($alternatives[$currentKey], 'Alternative BOM selection');
            $candidateRow = yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, $candidate);
            if ((string) $candidateRow['alternative_for_bom_key'] !== $currentKey) {
                throw new InvalidArgumentException('Selected alternative BOM does not belong to the nested base BOM.');
            }
            $selectedKey = $candidate;
        }
        if (isset($path[$selectedKey])) {
            throw new InvalidArgumentException('Manufacturing BOM cycle detected during explosion.');
        }
        $path[$selectedKey] = true;
        $bom = yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, $selectedKey);
        yovel_admin_manufacturing_bom_effective($bom, $valuationDate);
        if (!$root && (string) $bom['lifecycle_status'] !== 'SUBMITTED') {
            throw new InvalidArgumentException('Nested BOM must be submitted before explosion.');
        }
        foreach ($bom['operations'] as $operation) {
            $hours = bcdiv((string) $operation['duration_minutes'], '60', 9);
            $scaledHours = bcmul($hours, $factor, 9);
            $amount = bcmul($scaledHours, (string) $operation['hourly_rate'], 6);
            $operations[] = [
                'bom_key' => $selectedKey, 'operation_key' => (string) $operation['operation_key'],
                'hours' => $scaledHours, 'hourly_rate' => (string) $operation['hourly_rate'], 'amount' => $amount,
            ];
        }
        foreach ($bom['secondary_items'] as $secondary) {
            $secondaryItems[] = [
                'bom_key' => $selectedKey, 'item_key' => (string) $secondary['item_key'],
                'secondary_type' => (string) $secondary['secondary_type'],
                'quantity' => bcmul((string) $secondary['stock_quantity'], $factor, 9),
                'stock_uom_code' => (string) $secondary['stock_uom_code'],
            ];
        }
        foreach ($bom['components'] as $component) {
            $componentQuantity = bcmul((string) $component['stock_quantity'], $factor, 9);
            $childKey = (string) ($component['child_bom_key'] ?? '');
            if ($childKey !== '') {
                $selectedChildKey = $childKey;
                if (isset($alternatives[$childKey])) {
                    $selectedChildKey = yovel_admin_manufacturing_bom_key($alternatives[$childKey], 'Alternative BOM selection');
                    $alternative = yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, $selectedChildKey);
                    if ((string) $alternative['alternative_for_bom_key'] !== $childKey) {
                        throw new InvalidArgumentException('Selected alternative BOM does not belong to the nested base BOM.');
                    }
                }
                $child = yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, $selectedChildKey);
                $childOutput = yovel_admin_manufacturing_bom_uom($company, $gateway, (string) $child['item_key'], (string) $child['uom_key'], (string) $child['quantity']);
                $walk($selectedChildKey, bcdiv($componentQuantity, (string) $childOutput['stock_quantity'], 9));
                continue;
            }
            $itemKey = (string) $component['item_key'];
            if (!isset($items[$itemKey])) {
                $items[$itemKey] = ['item_key' => $itemKey, 'quantity' => '0.000000000', 'stock_uom_code' => (string) $component['stock_uom_code']];
            } elseif ((string) $items[$itemKey]['stock_uom_code'] !== (string) $component['stock_uom_code']) {
                throw new RuntimeException('Exploded BOM item stock UOM is inconsistent.');
            }
            $items[$itemKey]['quantity'] = bcadd((string) $items[$itemKey]['quantity'], $componentQuantity, 9);
        }
        unset($path[$selectedKey]);
    };
    $walk($bomKey, '1.000000000', true);
    ksort($items, SORT_STRING);
    usort($operations, static fn (array $left, array $right): int => [$left['bom_key'], $left['operation_key'], $left['hours']] <=> [$right['bom_key'], $right['operation_key'], $right['hours']]);
    usort($secondaryItems, static fn (array $left, array $right): int => [$left['bom_key'], $left['secondary_type'], $left['item_key']] <=> [$right['bom_key'], $right['secondary_type'], $right['item_key']]);
    $operationCost = '0.000000';
    foreach ($operations as $operation) {
        $operationCost = bcadd($operationCost, (string) $operation['amount'], 6);
    }
    return [
        'bom_key' => $bomKey, 'valuation_date' => $valuationDate, 'items' => array_values($items),
        'operations' => $operations, 'secondary_items' => $secondaryItems, 'operation_cost' => $operationCost,
    ];
}

function yovel_admin_manufacturing_bom_cost_inputs(array $context, string $bomKey, string $valuationDate): array
{
    [$company, , , $companyKeyHash, , $gateway] = yovel_admin_manufacturing_bom_context($context);
    $bom = yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, $bomKey);
    $explosion = yovel_admin_manufacturing_explode_bom($context, $bomKey, $valuationDate);
    $valuations = [];
    $materials = [];
    foreach ($explosion['items'] as $item) {
        $valuation = yovel_admin_manufacturing_dependency_call($gateway, 'item_valuation', [$company, (string) $item['item_key'], null, $valuationDate]);
        if (!is_array($valuation) || (string) ($valuation['item_key'] ?? '') !== (string) $item['item_key']) {
            throw new RuntimeException('Inventory valuation returned an invalid BOM item contract.');
        }
        $rate = yovel_admin_manufacturing_bom_decimal($valuation['valuation_rate'] ?? '', 'Inventory valuation rate', 9);
        $amount = bcmul((string) $item['quantity'], $rate, 6);
        $valuations[] = [
            'item_key' => (string) $item['item_key'], 'quantity' => (string) $item['quantity'],
            'stock_uom_code' => (string) $item['stock_uom_code'], 'valuation_rate' => $rate,
            'valuation_method' => (string) ($valuation['valuation_method'] ?? ''),
            'source_timestamp' => (string) ($valuation['source_timestamp'] ?? ''), 'amount' => $amount,
        ];
        $materials[] = ['amount' => $amount];
    }
    $scrapInputs = [];
    $scrap = [];
    foreach ($explosion['secondary_items'] as $secondary) {
        if (!in_array((string) $secondary['secondary_type'], ['SCRAP', 'BY_PRODUCT'], true)) {
            continue;
        }
        $valuation = yovel_admin_manufacturing_dependency_call($gateway, 'item_valuation', [$company, (string) $secondary['item_key'], null, $valuationDate]);
        $rate = yovel_admin_manufacturing_bom_decimal($valuation['valuation_rate'] ?? '', 'Inventory secondary valuation rate', 9);
        $amount = bcmul((string) $secondary['quantity'], $rate, 6);
        $scrapInputs[] = $secondary + ['valuation_rate' => $rate, 'valuation_method' => (string) ($valuation['valuation_method'] ?? ''), 'source_timestamp' => (string) ($valuation['source_timestamp'] ?? ''), 'amount' => $amount];
        $scrap[] = ['amount' => $amount];
    }
    $materialSubtotal = '0.000000';
    foreach ($materials as $material) {
        $materialSubtotal = bcadd($materialSubtotal, (string) $material['amount'], 6);
    }
    $operationSubtotal = (string) $explosion['operation_cost'];
    $processLossCost = bcmul(bcadd($materialSubtotal, $operationSubtotal, 6), bcdiv((string) $bom['process_loss_percent'], '100', 9), 6);
    $financeRequest = [
        'currency' => (string) $bom['currency'],
        'materials' => $materials,
        'operations' => array_map(static fn (array $operation): array => ['amount' => (string) $operation['amount']], $explosion['operations']),
        'additional_costs' => bccomp($processLossCost, '0', 6) === 1 ? [['amount' => $processLossCost]] : [],
        'scrap' => $scrap,
    ];
    $preview = yovel_admin_manufacturing_dependency_call($gateway, 'cost_preview', [$company, $financeRequest]);
    foreach (['material_cost', 'operation_cost', 'additional_cost', 'scrap_credit', 'total_cost', 'calculation_hash'] as $field) {
        if (!is_array($preview) || trim((string) ($preview[$field] ?? '')) === '') {
            throw new RuntimeException('Finance manufacturing cost preview returned an invalid contract.');
        }
    }
    $inputs = [
        'valuation_date' => $valuationDate, 'bom_key' => $bomKey, 'revision' => (int) $bom['revision'],
        'currency' => (string) $bom['currency'], 'valuations' => $valuations,
        'operations' => $explosion['operations'], 'secondary_valuations' => $scrapInputs,
        'process_loss_percent' => (string) $bom['process_loss_percent'], 'process_loss_cost' => $processLossCost,
        'finance_request' => $financeRequest, 'finance_calculation_hash' => (string) $preview['calculation_hash'],
    ];
    $calculationHash = hash('sha256', yovel_admin_manufacturing_json($inputs));
    return ['inputs' => $inputs, 'input_json' => yovel_admin_manufacturing_json($inputs), 'input_checksum' => hash('sha256', yovel_admin_manufacturing_json($inputs)), 'calculation_hash' => $calculationHash, 'preview' => $preview];
}

function yovel_admin_manufacturing_update_bom_cost(array $context, string $bomKey, ?string $valuationDate = null): array
{
    [, , $companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_bom_context($context);
    $bomKey = yovel_admin_manufacturing_bom_key($bomKey, 'BOM key');
    $valuationDate = yovel_admin_manufacturing_bom_date($valuationDate ?? date('Y-m-d'), 'BOM valuation date');
    $cost = yovel_admin_manufacturing_bom_cost_inputs($context, $bomKey, $valuationDate);
    $saved = yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'BOM_UPDATE_BATCH',
        'COST_UPDATE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $bomKey, $valuationDate, $cost): array {
            $bom = $db->GetRow('SELECT bom_code, lifecycle_status FROM project_company_manufacturing_bom WHERE company_key_hash=? AND bom_key=? FOR UPDATE', [$companyKeyHash, $bomKey]);
            if (!is_array($bom)) {
                throw new InvalidArgumentException('Manufacturing BOM is not owned by this company.');
            }
            if ((string) $bom['lifecycle_status'] !== 'DRAFT') {
                throw new InvalidArgumentException('Submitted revision is immutable and cannot be costed.');
            }
            $preview = $cost['preview'];
            yovel_admin_manufacturing_execute($db, 'UPDATE project_company_manufacturing_bom SET material_cost=?, operation_cost=?, process_loss_cost=?, scrap_credit=?, total_cost=?, cost_inputs_json=?, cost_calculation_hash=?, updated_by_admin_key=? WHERE company_key_hash=? AND bom_key=?', [(string) $preview['material_cost'], (string) $preview['operation_cost'], (string) $preview['additional_cost'], (string) $preview['scrap_credit'], (string) $preview['total_cost'], $cost['input_json'], $cost['calculation_hash'], $adminKey, $companyKeyHash, $bomKey], 'Manufacturing BOM cost update');
            $batchKey = bx_uuid();
            yovel_admin_manufacturing_execute($db, "INSERT INTO project_company_manufacturing_bom_update_batch (update_batch_key, company_key, company_key_hash, bom_key, valuation_date, input_checksum, calculation_hash, batch_status, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, 'COMPLETED', ?)", [$batchKey, $companyKey, $companyKeyHash, $bomKey, $valuationDate, $cost['input_checksum'], $cost['calculation_hash'], $adminKey], 'Manufacturing BOM update batch save');
            foreach ([['VALUATION_INPUTS', $bomKey, $cost['inputs']['valuations'], ['material_cost' => $preview['material_cost']]], ['OPERATION_INPUTS', $bomKey, $cost['inputs']['operations'], ['operation_cost' => $preview['operation_cost']]], ['FINANCE_PREVIEW', $preview['calculation_hash'], $cost['inputs']['finance_request'], $preview]] as $log) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_bom_update_log (update_log_key, company_key, company_key_hash, update_batch_key, bom_key, log_type, source_key, input_json, result_json, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $batchKey, $bomKey, $log[0], (string) $log[1], yovel_admin_manufacturing_json((array) $log[2]), yovel_admin_manufacturing_json((array) $log[3]), $adminKey], 'Manufacturing BOM update log save');
            }
            return [
                'record_key' => $batchKey, 'business_key' => (string) $bom['bom_code'] . ':' . $valuationDate,
                'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey,
                'persisted_fields' => [
                    'bom_key' => $bomKey, 'material_cost' => (string) $preview['material_cost'],
                    'operation_cost' => (string) $preview['operation_cost'], 'process_loss_cost' => (string) $preview['additional_cost'],
                    'scrap_credit' => (string) $preview['scrap_credit'], 'total_cost' => (string) $preview['total_cost'],
                    'cost_calculation_hash' => (string) $cost['calculation_hash'],
                ],
            ];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_bom_row($db, $companyKeyHash, $bomKey)
    );
    return yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, $bomKey) + ['_audit_action' => $saved['_audit_action']];
}

function yovel_admin_manufacturing_submit_bom(array $context, string $bomKey, ?string $valuationDate = null): array
{
    [, , $companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_bom_context($context);
    $bomKey = yovel_admin_manufacturing_bom_key($bomKey, 'BOM key');
    $valuationDate = yovel_admin_manufacturing_bom_date($valuationDate ?? date('Y-m-d'), 'BOM valuation date');
    $cost = yovel_admin_manufacturing_bom_cost_inputs($context, $bomKey, $valuationDate);
    $saved = yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'BOM',
        'SUBMIT',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $bomKey, $cost): array {
            $current = yovel_admin_manufacturing_bom_row($db, $companyKeyHash, $bomKey);
            if ((string) $current['lifecycle_status'] !== 'DRAFT') {
                throw new InvalidArgumentException('Only a draft BOM can be submitted.');
            }
            $versionKey = bx_uuid();
            $snapshot = $current;
            unset($snapshot['cost_inputs_json'], $snapshot['cost_inputs'], $snapshot['children_checksum']);
            $snapshot['material_cost'] = (string) $cost['preview']['material_cost'];
            $snapshot['operation_cost'] = (string) $cost['preview']['operation_cost'];
            $snapshot['process_loss_cost'] = (string) $cost['preview']['additional_cost'];
            $snapshot['scrap_credit'] = (string) $cost['preview']['scrap_credit'];
            $snapshot['total_cost'] = (string) $cost['preview']['total_cost'];
            $snapshot['cost_inputs'] = $cost['inputs'];
            $snapshotJson = yovel_admin_manufacturing_json($snapshot);
            yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_bom_version (version_key, company_key, company_key_hash, bom_key, revision, snapshot_json, snapshot_checksum, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$versionKey, $companyKey, $companyKeyHash, $bomKey, (int) $current['revision'], $snapshotJson, hash('sha256', $snapshotJson), $adminKey], 'Manufacturing BOM version save');
            yovel_admin_manufacturing_execute($db, "UPDATE project_company_manufacturing_bom SET lifecycle_status='SUBMITTED', submitted_version_key=?, material_cost=?, operation_cost=?, process_loss_cost=?, scrap_credit=?, total_cost=?, cost_inputs_json=?, cost_calculation_hash=?, submitted_by_admin_key=?, updated_by_admin_key=?, submitted_at=NOW() WHERE company_key_hash=? AND bom_key=?", [$versionKey, (string) $cost['preview']['material_cost'], (string) $cost['preview']['operation_cost'], (string) $cost['preview']['additional_cost'], (string) $cost['preview']['scrap_credit'], (string) $cost['preview']['total_cost'], $cost['input_json'], $cost['calculation_hash'], $adminKey, $adminKey, $companyKeyHash, $bomKey], 'Manufacturing BOM submit');
            return [
                'record_key' => $bomKey, 'business_key' => (string) $current['bom_code'], 'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey,
                'persisted_fields' => [
                    'bom_key' => $bomKey, 'lifecycle_status' => 'SUBMITTED', 'submitted_version_key' => $versionKey,
                    'material_cost' => (string) $cost['preview']['material_cost'], 'operation_cost' => (string) $cost['preview']['operation_cost'],
                    'process_loss_cost' => (string) $cost['preview']['additional_cost'], 'scrap_credit' => (string) $cost['preview']['scrap_credit'],
                    'total_cost' => (string) $cost['preview']['total_cost'], 'cost_calculation_hash' => (string) $cost['calculation_hash'],
                ],
            ];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_bom_row($db, $companyKeyHash, $bomKey)
    );
    return yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, $bomKey) + ['_audit_action' => $saved['_audit_action']];
}

function yovel_admin_manufacturing_cancel_bom(array $context, string $bomKey, string $reason): array
{
    [, , $companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_bom_context($context);
    $bomKey = yovel_admin_manufacturing_bom_key($bomKey, 'BOM key');
    $reason = trim($reason);
    if ($reason === '' || strlen($reason) > 1000) {
        throw new InvalidArgumentException('BOM cancellation reason must contain 1 to 1000 characters.');
    }
    $saved = yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'BOM',
        'CANCEL',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $bomKey, $reason): array {
            $row = $db->GetRow('SELECT bom_code, lifecycle_status FROM project_company_manufacturing_bom WHERE company_key_hash=? AND bom_key=? FOR UPDATE', [$companyKeyHash, $bomKey]);
            if (!is_array($row) || (string) ($row['lifecycle_status'] ?? '') !== 'SUBMITTED') {
                throw new InvalidArgumentException('Only a submitted BOM can be cancelled.');
            }
            yovel_admin_manufacturing_execute($db, "UPDATE project_company_manufacturing_bom SET lifecycle_status='CANCELLED', cancellation_reason=?, cancelled_by_admin_key=?, updated_by_admin_key=?, cancelled_at=NOW() WHERE company_key_hash=? AND bom_key=?", [$reason, $adminKey, $adminKey, $companyKeyHash, $bomKey], 'Manufacturing BOM cancel');
            return ['record_key' => $bomKey, 'business_key' => (string) $row['bom_code'], 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey, 'persisted_fields' => ['bom_key' => $bomKey, 'lifecycle_status' => 'CANCELLED', 'cancellation_reason' => $reason]];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_bom_row($db, $companyKeyHash, $bomKey)
    );
    return yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, $bomKey) + ['_audit_action' => $saved['_audit_action']];
}

function yovel_admin_manufacturing_amend_bom(array $context, string $bomKey, array $overrides = []): array
{
    [, , $companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_bom_context($context);
    $bomKey = yovel_admin_manufacturing_bom_key($bomKey, 'BOM key');
    $source = yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, $bomKey);
    if ((string) $source['lifecycle_status'] !== 'CANCELLED') {
        throw new InvalidArgumentException('Only a cancelled BOM can be amended.');
    }
    $newCode = yovel_admin_manufacturing_bom_code($overrides['bom_code'] ?? ((string) $source['bom_code'] . '-AMEND-' . ((int) $source['revision'] + 1)));
    $effectiveFrom = yovel_admin_manufacturing_bom_date($overrides['effective_from'] ?? $source['effective_from'], 'BOM effective from date');
    $effectiveTo = yovel_admin_manufacturing_bom_date($overrides['effective_to'] ?? $source['effective_to'], 'BOM effective to date', false);
    if ($effectiveTo !== '' && $effectiveTo < $effectiveFrom) {
        throw new InvalidArgumentException('BOM effective date range is invalid.');
    }
    $newKey = bx_uuid();
    $revision = (int) $source['revision'] + 1;
    $saved = yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'BOM',
        'AMEND',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $bomKey, $source, $newCode, $effectiveFrom, $effectiveTo, $newKey, $revision): array {
            $locked = $db->GetRow('SELECT lifecycle_status FROM project_company_manufacturing_bom WHERE company_key_hash=? AND bom_key=? FOR UPDATE', [$companyKeyHash, $bomKey]);
            if (!is_array($locked) || (string) $locked['lifecycle_status'] !== 'CANCELLED') {
                throw new InvalidArgumentException('Only a cancelled BOM can be amended.');
            }
            yovel_admin_manufacturing_execute($db, "INSERT INTO project_company_manufacturing_bom (bom_key, company_key, company_key_hash, bom_code, item_key, revision, quantity, uom_key, currency, is_alternative, alternative_for_bom_key, effective_from, effective_to, process_loss_percent, lifecycle_status, amended_from_bom_key, expense_account_key, notes, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, ?, ?, ?, ?)", [$newKey, $companyKey, $companyKeyHash, $newCode, $source['item_key'], $revision, $source['quantity'], $source['uom_key'], $source['currency'], (int) $source['is_alternative'], $source['alternative_for_bom_key'] !== '' ? $source['alternative_for_bom_key'] : null, $effectiveFrom, $effectiveTo !== '' ? $effectiveTo : null, $source['process_loss_percent'], $bomKey, $source['expense_account_key'] !== '' ? $source['expense_account_key'] : null, $source['notes'] !== '' ? $source['notes'] : null, $adminKey, $adminKey], 'Manufacturing BOM amendment save');
            foreach ($source['components'] as $component) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_bom_component (component_key, company_key, company_key_hash, bom_key, item_key, child_bom_key, sequence_no, quantity, uom_key, conversion_factor, stock_quantity, stock_uom_code, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $newKey, $component['item_key'], ($component['child_bom_key'] ?? '') !== '' ? $component['child_bom_key'] : null, $component['sequence'], $component['quantity'], $component['uom_key'], $component['conversion_factor'], $component['stock_quantity'], $component['stock_uom_code'], $adminKey], 'Manufacturing BOM amendment component copy');
            }
            foreach ($source['operations'] as $operation) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_bom_operation (bom_operation_key, company_key, company_key_hash, bom_key, operation_key, sequence_no, duration_minutes, hourly_rate, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $newKey, $operation['operation_key'], $operation['sequence'], $operation['duration_minutes'], $operation['hourly_rate'], $adminKey], 'Manufacturing BOM amendment operation copy');
            }
            foreach ($source['secondary_items'] as $secondary) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_bom_secondary_item (secondary_item_key, company_key, company_key_hash, bom_key, item_key, secondary_type, sequence_no, quantity, uom_key, conversion_factor, stock_quantity, stock_uom_code, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $newKey, $secondary['item_key'], $secondary['secondary_type'], $secondary['sequence'], $secondary['quantity'], $secondary['uom_key'], $secondary['conversion_factor'], $secondary['stock_quantity'], $secondary['stock_uom_code'], $adminKey], 'Manufacturing BOM amendment secondary copy');
            }
            $newRow = yovel_admin_manufacturing_bom_row($db, $companyKeyHash, $newKey);
            return ['record_key' => $newKey, 'business_key' => $newCode, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey, 'persisted_fields' => ['bom_key' => $newKey, 'bom_code' => $newCode, 'revision' => $revision, 'lifecycle_status' => 'DRAFT', 'amended_from_bom_key' => $bomKey, 'effective_from' => $effectiveFrom, 'effective_to' => $effectiveTo, 'children_checksum' => $newRow['children_checksum']]];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_bom_row($db, $companyKeyHash, $newKey)
    );
    return yovel_admin_manufacturing_bom_row(bx_db(), $companyKeyHash, $newKey) + ['_audit_action' => $saved['_audit_action']];
}
