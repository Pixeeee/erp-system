<?php
declare(strict_types=1);

function yovel_admin_inventory_warehouse_fault(string $point): void
{
    $fault = $GLOBALS['yovel_admin_inventory_warehouse_fault'] ?? null;
    if (is_callable($fault)) {
        $fault($point);
    }
}

function yovel_admin_inventory_warehouse_flag(mixed $value): int
{
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function yovel_admin_inventory_warehouse_code(mixed $value, string $label): string
{
    $value = strtoupper(trim((string) $value));
    if ($value === '' || strlen($value) > 80 || preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $value) !== 1) {
        throw new InvalidArgumentException($label . ' code is invalid.');
    }
    return $value;
}

function yovel_admin_inventory_warehouse_text(mixed $value, int $max, string $label, bool $required = true): string
{
    $value = trim((string) $value);
    if (($required && $value === '') || strlen($value) > $max) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function yovel_admin_inventory_warehouse_status(mixed $value, string $label): string
{
    $value = strtoupper(trim((string) $value));
    if (!in_array($value, ['ACTIVE', 'DISABLED'], true)) {
        throw new InvalidArgumentException($label . ' status is invalid.');
    }
    return $value;
}

function yovel_admin_inventory_lock_company(ADOConnection $db, array $scope): void
{
    $row = $db->GetRow(
        'SELECT company_key FROM project_company WHERE company_key=? AND company_key_hash=? AND company_status=\'ACTIVE\' FOR UPDATE',
        [$scope[0], $scope[1]]
    );
    if (!is_array($row) || (string) ($row['company_key'] ?? '') !== (string) $scope[0]) {
        throw new RuntimeException('Inventory company scope could not be locked.');
    }
}

function yovel_admin_inventory_zero_projection(array $identity = []): array
{
    return $identity + [
        'bin_key' => '', 'actual' => '0.000000000', 'reserved' => '0.000000000',
        'ordered' => '0.000000000', 'requested' => '0.000000000', 'planned' => '0.000000000',
        'projected' => '0.000000000', 'available_to_reserve' => '0.000000000',
    ];
}

function yovel_admin_inventory_projection_from_totals(array $totals, array $identity = []): array
{
    $actual = yovel_admin_inventory_decimal((string) ($totals['actual'] ?? '0'));
    $reserved = yovel_admin_inventory_decimal((string) ($totals['reserved'] ?? '0'));
    $ordered = yovel_admin_inventory_decimal((string) ($totals['ordered'] ?? '0'));
    $requested = yovel_admin_inventory_decimal((string) ($totals['requested'] ?? '0'));
    $planned = yovel_admin_inventory_decimal((string) ($totals['planned'] ?? '0'));
    $projected = bcsub(bcadd(bcadd(bcadd($actual, $ordered, 9), $requested, 9), $planned, 9), $reserved, 9);
    return $identity + [
        'actual' => $actual, 'reserved' => $reserved, 'ordered' => $ordered,
        'requested' => $requested, 'planned' => $planned, 'projected' => yovel_admin_inventory_decimal($projected),
        'available_to_reserve' => yovel_admin_inventory_decimal(bcsub($actual, $reserved, 9)),
    ];
}

function yovel_admin_inventory_dimension_tuple(array $company, array $dimensions, bool $validate = true): array
{
    $companyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $companyHash) !== 1) {
        throw new InvalidArgumentException('Inventory company scope is invalid.');
    }

    $normalized = [];
    if (array_is_list($dimensions)) {
        foreach ($dimensions as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Inventory dimension rows are invalid.');
            }
            $code = yovel_admin_inventory_warehouse_code($row['dimension_code'] ?? '', 'Inventory dimension');
            if (array_key_exists($code, $normalized)) {
                throw new InvalidArgumentException('Inventory dimension tuples cannot contain duplicate dimensions.');
            }
            $normalized[$code] = yovel_admin_inventory_warehouse_code($row['dimension_value_code'] ?? '', 'Inventory dimension value');
        }
    } else {
        foreach ($dimensions as $code => $value) {
            $code = yovel_admin_inventory_warehouse_code($code, 'Inventory dimension');
            if (array_key_exists($code, $normalized)) {
                throw new InvalidArgumentException('Inventory dimension tuples cannot contain duplicate dimensions.');
            }
            $normalized[$code] = yovel_admin_inventory_warehouse_code($value, 'Inventory dimension value');
        }
    }
    ksort($normalized, SORT_STRING);

    if ($validate) {
        $definitions = bx_db()->GetAll(
            "SELECT d.dimension_key,d.dimension_code,d.is_required,v.dimension_value_code
             FROM project_company_inventory_dimension d
             LEFT JOIN project_company_inventory_dimension_value v
               ON v.company_key_hash=d.company_key_hash AND v.dimension_key=d.dimension_key AND v.dimension_value_status='ACTIVE'
             WHERE d.company_key_hash=? AND d.dimension_status='ACTIVE'
             ORDER BY d.sort_order,d.dimension_code,v.dimension_value_code",
            [$companyHash]
        );
        $allowed = [];
        $required = [];
        foreach ($definitions as $definition) {
            $code = (string) $definition['dimension_code'];
            $allowed[$code] ??= [];
            if ((string) ($definition['dimension_value_code'] ?? '') !== '') {
                $allowed[$code][(string) $definition['dimension_value_code']] = true;
            }
            if ((int) $definition['is_required'] === 1) {
                $required[$code] = true;
            }
        }
        foreach ($normalized as $code => $value) {
            if (!isset($allowed[$code][$value])) {
                throw new InvalidArgumentException('Inventory dimension value is not active for this company.');
            }
        }
        foreach (array_keys($required) as $code) {
            if (!isset($normalized[$code])) {
                throw new InvalidArgumentException('Inventory required dimension ' . $code . ' is missing.');
            }
        }
    }

    $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return ['dimensions' => $normalized, 'dimensions_json' => $json, 'dimensions_checksum' => hash('sha256', $json)];
}

function yovel_admin_save_inventory_settings(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_warehouse_control_schema();
    $strategy = strtoupper(trim((string) ($input['default_putaway_strategy'] ?? 'PRIORITY')));
    if (!in_array($strategy, ['PRIORITY', 'CAPACITY'], true)) {
        throw new InvalidArgumentException('Inventory putaway strategy is invalid.');
    }
    $expected = [
        'allow_negative_stock' => yovel_admin_inventory_warehouse_flag($input['allow_negative_stock'] ?? 0),
        'capacity_enforcement' => yovel_admin_inventory_warehouse_flag($input['capacity_enforcement'] ?? 1),
        'default_putaway_strategy' => $strategy,
    ];
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($scope, $expected): array {
        yovel_admin_inventory_lock_company($db, $scope);
        $existing = $db->GetRow('SELECT * FROM project_company_inventory_stock_setting WHERE company_key_hash=? FOR UPDATE', [$scope[1]]);
        $key = is_array($existing) && $existing !== [] ? (string) $existing['stock_setting_key'] : bx_uuid();
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_stock_setting SET allow_negative_stock=?,capacity_enforcement=?,default_putaway_strategy=?,updated_by_admin_key=? WHERE company_key_hash=? AND stock_setting_key=?', [$expected['allow_negative_stock'], $expected['capacity_enforcement'], $expected['default_putaway_strategy'], $scope[2], $scope[1], $key], 'Inventory Stock Settings update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_stock_setting (stock_setting_key,company_key,company_key_hash,allow_negative_stock,capacity_enforcement,default_putaway_strategy,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?)', [$key, $scope[0], $scope[1], $expected['allow_negative_stock'], $expected['capacity_enforcement'], $expected['default_putaway_strategy'], $scope[2], $scope[2]], 'Inventory Stock Settings create');
        }
        yovel_admin_inventory_warehouse_fault('settings_after_write');
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_inventory_stock_setting', $key, $expected + ['company_key' => $scope[0], 'admin_key' => $scope[2]], 'Company administrator saved Inventory Stock Settings.');
        $actual = $db->GetRow('SELECT * FROM project_company_inventory_stock_setting WHERE company_key_hash=? AND stock_setting_key=?', [$scope[1], $key]);
        yovel_admin_inventory_assert_readback(array_map('strval', $expected), is_array($actual) ? $actual : [], array_keys($expected), 'Inventory Stock Settings');
        return $actual;
    });
}

function yovel_admin_inventory_settings(array $company): array
{
    yovel_admin_inventory_warehouse_control_schema();
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $row = preg_match('/^[a-f0-9]{64}$/', $hash) === 1 ? bx_db()->GetRow('SELECT * FROM project_company_inventory_stock_setting WHERE company_key_hash=?', [$hash]) : [];
    return is_array($row) && $row !== [] ? $row : ['stock_setting_key' => '', 'allow_negative_stock' => '0', 'capacity_enforcement' => '1', 'default_putaway_strategy' => 'PRIORITY'];
}

function yovel_admin_save_warehouse_type(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_warehouse_control_schema();
    $key = trim((string) ($input['warehouse_type_key'] ?? ''));
    if ($key !== '' && !yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Warehouse Type key is invalid.');
    }
    $expected = [
        'warehouse_type_code' => yovel_admin_inventory_warehouse_code($input['warehouse_type_code'] ?? '', 'Warehouse Type'),
        'warehouse_type_name' => yovel_admin_inventory_warehouse_text($input['warehouse_type_name'] ?? '', 160, 'Warehouse Type name'),
        'warehouse_type_status' => yovel_admin_inventory_warehouse_status($input['warehouse_type_status'] ?? 'ACTIVE', 'Warehouse Type'),
    ];
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($scope, $key, $expected): array {
        yovel_admin_inventory_lock_company($db, $scope);
        $existing = $key !== ''
            ? $db->GetRow('SELECT * FROM project_company_inventory_warehouse_type WHERE company_key_hash=? AND warehouse_type_key=? FOR UPDATE', [$scope[1], $key])
            : $db->GetRow('SELECT * FROM project_company_inventory_warehouse_type WHERE company_key_hash=? AND warehouse_type_code=? FOR UPDATE', [$scope[1], $expected['warehouse_type_code']]);
        if ($key !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Warehouse Type does not belong to this company.');
        }
        $key = is_array($existing) && $existing !== [] ? (string) $existing['warehouse_type_key'] : ($key !== '' ? $key : bx_uuid());
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_warehouse_type SET warehouse_type_code=?,warehouse_type_name=?,warehouse_type_status=?,updated_by_admin_key=? WHERE company_key_hash=? AND warehouse_type_key=?', [$expected['warehouse_type_code'], $expected['warehouse_type_name'], $expected['warehouse_type_status'], $scope[2], $scope[1], $key], 'Inventory Warehouse Type update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_warehouse_type (warehouse_type_key,company_key,company_key_hash,warehouse_type_code,warehouse_type_name,warehouse_type_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?)', [$key, $scope[0], $scope[1], $expected['warehouse_type_code'], $expected['warehouse_type_name'], $expected['warehouse_type_status'], $scope[2], $scope[2]], 'Inventory Warehouse Type create');
        }
        yovel_admin_inventory_warehouse_fault('warehouse_type_after_write');
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_inventory_warehouse_type', $key, $expected + ['company_key' => $scope[0], 'admin_key' => $scope[2]], 'Company administrator saved an Inventory Warehouse Type.');
        $actual = $db->GetRow('SELECT * FROM project_company_inventory_warehouse_type WHERE company_key_hash=? AND warehouse_type_key=?', [$scope[1], $key]);
        yovel_admin_inventory_assert_readback($expected, is_array($actual) ? $actual : [], array_keys($expected), 'Inventory Warehouse Type');
        return $actual;
    });
}

function yovel_admin_inventory_warehouse_types(array $company): array
{
    yovel_admin_inventory_warehouse_control_schema();
    return bx_db()->GetAll('SELECT * FROM project_company_inventory_warehouse_type WHERE company_key_hash=? ORDER BY warehouse_type_name,warehouse_type_code', [strtolower(trim((string) ($company['company_key_hash'] ?? '')))]);
}

function yovel_admin_save_warehouse(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_warehouse_control_schema();
    $key = trim((string) ($input['warehouse_key'] ?? ''));
    $parentKey = trim((string) ($input['parent_warehouse_key'] ?? ''));
    $typeKey = trim((string) ($input['warehouse_type_key'] ?? ''));
    foreach ([$key, $parentKey, $typeKey] as $candidate) {
        if ($candidate !== '' && !yovel_admin_is_uuid($candidate)) {
            throw new InvalidArgumentException('Warehouse reference key is invalid.');
        }
    }
    $priority = filter_var($input['putaway_priority'] ?? 100, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($priority === false) {
        throw new InvalidArgumentException('Warehouse putaway priority is invalid.');
    }
    $capacity = yovel_admin_inventory_decimal((string) ($input['capacity_qty'] ?? '0'));
    if (bccomp($capacity, '0', 9) === -1) {
        throw new InvalidArgumentException('Warehouse capacity is invalid.');
    }
    $expected = [
        'warehouse_code' => yovel_admin_inventory_warehouse_code($input['warehouse_code'] ?? '', 'Warehouse'),
        'warehouse_name' => yovel_admin_inventory_warehouse_text($input['warehouse_name'] ?? '', 160, 'Warehouse name'),
        'parent_warehouse_key' => $parentKey, 'warehouse_type_key' => $typeKey,
        'is_group' => yovel_admin_inventory_warehouse_flag($input['is_group'] ?? 0),
        'warehouse_status' => yovel_admin_inventory_warehouse_status($input['warehouse_status'] ?? 'ACTIVE', 'Warehouse'),
        'capacity_qty' => $capacity, 'putaway_priority' => (string) $priority,
    ];
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($scope, $key, $expected): array {
        yovel_admin_inventory_lock_company($db, $scope);
        $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_key=? FOR UPDATE', [$scope[1], $key]) : [];
        if ($key !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Warehouse does not belong to this company.');
        }
        $duplicate = $db->GetRow('SELECT warehouse_key FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_code=? FOR UPDATE', [$scope[1], $expected['warehouse_code']]);
        if (is_array($duplicate) && $duplicate !== [] && (string) $duplicate['warehouse_key'] !== $key) {
            throw new InvalidArgumentException('Warehouse code must be unique within the company.');
        }
        $key = $key !== '' ? $key : bx_uuid();

        if ($expected['warehouse_type_key'] !== '') {
            $type = $db->GetRow("SELECT warehouse_type_key FROM project_company_inventory_warehouse_type WHERE company_key_hash=? AND warehouse_type_key=? AND warehouse_type_status='ACTIVE' FOR UPDATE", [$scope[1], $expected['warehouse_type_key']]);
            if (!is_array($type) || $type === []) {
                throw new InvalidArgumentException('Warehouse Type does not belong to this company or is disabled.');
            }
        }
        if ($expected['parent_warehouse_key'] !== '') {
            if ($expected['parent_warehouse_key'] === $key) {
                throw new InvalidArgumentException('Warehouse hierarchy cycle detected.');
            }
            $parent = $db->GetRow('SELECT warehouse_key,parent_warehouse_key,is_group,warehouse_status FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_key=? FOR UPDATE', [$scope[1], $expected['parent_warehouse_key']]);
            if (!is_array($parent) || $parent === []) {
                $foreign = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_warehouse WHERE warehouse_key=?', [$expected['parent_warehouse_key']]);
                throw new InvalidArgumentException($foreign > 0 ? 'Warehouse parent belongs to another company.' : 'Warehouse parent does not exist.');
            }
            $cursor = (string) ($parent['parent_warehouse_key'] ?? '');
            $visited = [$expected['parent_warehouse_key'] => true];
            while ($cursor !== '') {
                if ($cursor === $key || isset($visited[$cursor])) {
                    throw new InvalidArgumentException('Warehouse hierarchy cycle detected.');
                }
                $visited[$cursor] = true;
                $ancestor = $db->GetRow('SELECT parent_warehouse_key FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_key=? FOR UPDATE', [$scope[1], $cursor]);
                if (!is_array($ancestor) || $ancestor === []) {
                    throw new InvalidArgumentException('Warehouse hierarchy contains a cross-company key.');
                }
                $cursor = (string) ($ancestor['parent_warehouse_key'] ?? '');
            }
            if ((int) $parent['is_group'] !== 1 || (string) $parent['warehouse_status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('Warehouse parent must be an active group warehouse.');
            }
        }
        if (is_array($existing) && $existing !== [] && ((int) $existing['is_group'] !== (int) $expected['is_group'] || $expected['warehouse_status'] === 'DISABLED')) {
            $activity = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_bin_source WHERE company_key_hash=? AND warehouse_key=?', [$scope[1], $key]);
            if ($activity > 0) {
                throw new InvalidArgumentException('Warehouse with stock activity cannot change group status or be disabled.');
            }
        }
        if ((int) $expected['is_group'] === 0 && bccomp((string) $expected['capacity_qty'], '0', 9) === 1) {
            $capacityUsed = yovel_admin_inventory_decimal((string) $db->GetOne('SELECT COALESCE(SUM(actual_qty),0) FROM project_company_inventory_bin WHERE company_key_hash=? AND warehouse_key=?', [$scope[1], $key]));
            if (bccomp($capacityUsed, (string) $expected['capacity_qty'], 9) === 1) {
                throw new InvalidArgumentException('Warehouse capacity cannot be lower than its actual stock.');
            }
        }

        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_warehouse SET warehouse_code=?,warehouse_name=?,parent_warehouse_key=?,warehouse_type_key=?,is_group=?,warehouse_status=?,capacity_qty=?,putaway_priority=?,updated_by_admin_key=? WHERE company_key_hash=? AND warehouse_key=?', [$expected['warehouse_code'], $expected['warehouse_name'], $expected['parent_warehouse_key'] ?: null, $expected['warehouse_type_key'] ?: null, $expected['is_group'], $expected['warehouse_status'], $expected['capacity_qty'], $expected['putaway_priority'], $scope[2], $scope[1], $key], 'Inventory Warehouse update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_warehouse (warehouse_key,company_key,company_key_hash,warehouse_code,warehouse_name,parent_warehouse_key,warehouse_type_key,is_group,warehouse_status,capacity_qty,putaway_priority,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [$key, $scope[0], $scope[1], $expected['warehouse_code'], $expected['warehouse_name'], $expected['parent_warehouse_key'] ?: null, $expected['warehouse_type_key'] ?: null, $expected['is_group'], $expected['warehouse_status'], $expected['capacity_qty'], $expected['putaway_priority'], $scope[2], $scope[2]], 'Inventory Warehouse create');
        }
        yovel_admin_inventory_warehouse_fault('warehouse_after_write');
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_inventory_warehouse', $key, $expected + ['company_key' => $scope[0], 'admin_key' => $scope[2]], 'Company administrator saved an Inventory Warehouse.');
        $actual = $db->GetRow('SELECT * FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_key=?', [$scope[1], $key]);
        $readbackExpected = $expected;
        $readbackExpected['parent_warehouse_key'] = $expected['parent_warehouse_key'];
        $readbackExpected['warehouse_type_key'] = $expected['warehouse_type_key'];
        $actual = is_array($actual) ? $actual : [];
        $actual['parent_warehouse_key'] = (string) ($actual['parent_warehouse_key'] ?? '');
        $actual['warehouse_type_key'] = (string) ($actual['warehouse_type_key'] ?? '');
        yovel_admin_inventory_assert_readback($readbackExpected, $actual, array_keys($readbackExpected), 'Inventory Warehouse');
        return $actual;
    });
}

function yovel_admin_inventory_warehouses(array $company, array $filters = []): array
{
    yovel_admin_inventory_warehouse_control_schema();
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $sql = "SELECT w.*,p.warehouse_name parent_warehouse_name,t.warehouse_type_name,
                   COALESCE((SELECT SUM(b.actual_qty) FROM project_company_inventory_bin b WHERE b.company_key_hash=w.company_key_hash AND b.warehouse_key=w.warehouse_key),0) capacity_used
            FROM project_company_inventory_warehouse w
            LEFT JOIN project_company_inventory_warehouse p ON p.company_key_hash=w.company_key_hash AND p.warehouse_key=w.parent_warehouse_key
            LEFT JOIN project_company_inventory_warehouse_type t ON t.company_key_hash=w.company_key_hash AND t.warehouse_type_key=w.warehouse_type_key
            WHERE w.company_key_hash=?";
    $params = [$hash];
    if (isset($filters['status'])) {
        $sql .= ' AND w.warehouse_status=?';
        $params[] = strtoupper(trim((string) $filters['status']));
    }
    if (!empty($filters['leaf_only'])) {
        $sql .= ' AND w.is_group=0';
    }
    return bx_db()->GetAll($sql . ' ORDER BY w.warehouse_code,w.x_id', $params);
}

function yovel_admin_save_inventory_dimension(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_warehouse_control_schema();
    $key = trim((string) ($input['dimension_key'] ?? ''));
    if ($key !== '' && !yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Inventory Dimension key is invalid.');
    }
    $valuesInput = $input['values'] ?? $input['values_json'] ?? [];
    if (is_string($valuesInput)) {
        $valuesInput = trim($valuesInput) === '' ? [] : json_decode($valuesInput, true, 512, JSON_THROW_ON_ERROR);
    }
    if (!is_array($valuesInput) || count($valuesInput) > 200) {
        throw new InvalidArgumentException('Inventory Dimension values are invalid.');
    }
    $values = [];
    foreach ($valuesInput as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Inventory Dimension value is invalid.');
        }
        $code = yovel_admin_inventory_warehouse_code($row['dimension_value_code'] ?? '', 'Inventory Dimension value');
        if (isset($values[$code])) {
            throw new InvalidArgumentException('Inventory Dimension value tuples must be unique.');
        }
        $values[$code] = ['dimension_value_code' => $code, 'dimension_value_name' => yovel_admin_inventory_warehouse_text($row['dimension_value_name'] ?? '', 160, 'Inventory Dimension value name'), 'dimension_value_status' => yovel_admin_inventory_warehouse_status($row['dimension_value_status'] ?? 'ACTIVE', 'Inventory Dimension value')];
    }
    ksort($values, SORT_STRING);
    $sortOrder = filter_var($input['sort_order'] ?? 100, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($sortOrder === false) {
        throw new InvalidArgumentException('Inventory Dimension sort order is invalid.');
    }
    $expected = [
        'dimension_code' => yovel_admin_inventory_warehouse_code($input['dimension_code'] ?? '', 'Inventory Dimension'),
        'dimension_name' => yovel_admin_inventory_warehouse_text($input['dimension_name'] ?? '', 160, 'Inventory Dimension name'),
        'dimension_status' => yovel_admin_inventory_warehouse_status($input['dimension_status'] ?? 'ACTIVE', 'Inventory Dimension'),
        'is_required' => yovel_admin_inventory_warehouse_flag($input['is_required'] ?? 0), 'sort_order' => (string) $sortOrder,
    ];
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($scope, $key, $expected, $values): array {
        yovel_admin_inventory_lock_company($db, $scope);
        $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_inventory_dimension WHERE company_key_hash=? AND dimension_key=? FOR UPDATE', [$scope[1], $key]) : [];
        if ($key !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Inventory Dimension does not belong to this company.');
        }
        $duplicate = $db->GetRow('SELECT dimension_key FROM project_company_inventory_dimension WHERE company_key_hash=? AND dimension_code=? FOR UPDATE', [$scope[1], $expected['dimension_code']]);
        if (is_array($duplicate) && $duplicate !== [] && (string) $duplicate['dimension_key'] !== $key) {
            throw new InvalidArgumentException('Inventory Dimension code must be unique within the company.');
        }
        $key = $key !== '' ? $key : bx_uuid();
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_dimension SET dimension_code=?,dimension_name=?,dimension_status=?,is_required=?,sort_order=?,updated_by_admin_key=? WHERE company_key_hash=? AND dimension_key=?', [$expected['dimension_code'], $expected['dimension_name'], $expected['dimension_status'], $expected['is_required'], $expected['sort_order'], $scope[2], $scope[1], $key], 'Inventory Dimension update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_dimension (dimension_key,company_key,company_key_hash,dimension_code,dimension_name,dimension_status,is_required,sort_order,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?)', [$key, $scope[0], $scope[1], $expected['dimension_code'], $expected['dimension_name'], $expected['dimension_status'], $expected['is_required'], $expected['sort_order'], $scope[2], $scope[2]], 'Inventory Dimension create');
        }
        yovel_admin_inventory_execute($db, 'DELETE FROM project_company_inventory_dimension_value WHERE company_key_hash=? AND dimension_key=?', [$scope[1], $key], 'Inventory Dimension values replace');
        foreach ($values as $value) {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_dimension_value (dimension_value_key,company_key,company_key_hash,dimension_key,dimension_value_code,dimension_value_name,dimension_value_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?)', [bx_uuid(), $scope[0], $scope[1], $key, $value['dimension_value_code'], $value['dimension_value_name'], $value['dimension_value_status'], $scope[2], $scope[2]], 'Inventory Dimension value save');
        }
        yovel_admin_inventory_warehouse_fault('dimension_after_write');
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_inventory_dimension', $key, $expected + ['company_key' => $scope[0], 'admin_key' => $scope[2], 'value_count' => count($values)], 'Company administrator saved an Inventory Dimension.');
        $actual = $db->GetRow('SELECT * FROM project_company_inventory_dimension WHERE company_key_hash=? AND dimension_key=?', [$scope[1], $key]);
        $actual = is_array($actual) ? $actual : [];
        $actual['values'] = $db->GetAll('SELECT * FROM project_company_inventory_dimension_value WHERE company_key_hash=? AND dimension_key=? ORDER BY dimension_value_code', [$scope[1], $key]);
        yovel_admin_inventory_assert_readback($expected, $actual, array_keys($expected), 'Inventory Dimension');
        if (count($actual['values']) !== count($values)) {
            throw new RuntimeException('Inventory Dimension value read-back verification failed.');
        }
        foreach (array_values($values) as $index => $expectedValue) {
            yovel_admin_inventory_assert_readback($expectedValue, $actual['values'][$index] ?? [], array_keys($expectedValue), 'Inventory Dimension value');
        }
        return $actual;
    });
}

function yovel_admin_inventory_dimensions(array $company): array
{
    yovel_admin_inventory_warehouse_control_schema();
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $rows = bx_db()->GetAll('SELECT * FROM project_company_inventory_dimension WHERE company_key_hash=? ORDER BY sort_order,dimension_code', [$hash]);
    foreach ($rows as &$row) {
        $row['values'] = bx_db()->GetAll('SELECT * FROM project_company_inventory_dimension_value WHERE company_key_hash=? AND dimension_key=? ORDER BY dimension_value_code', [$hash, $row['dimension_key']]);
    }
    unset($row);
    return $rows;
}

function yovel_admin_inventory_validate_stock_target(ADOConnection $db, array $company, string $itemKey, string $warehouseKey, bool $lock = false): array
{
    if (!yovel_admin_is_uuid($itemKey) || !yovel_admin_is_uuid($warehouseKey)) {
        throw new InvalidArgumentException('Inventory item or warehouse key is invalid.');
    }
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $suffix = $lock ? ' FOR UPDATE' : '';
    $item = $db->GetRow("SELECT item_key,item_kind,item_status FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=?{$suffix}", [$hash, $itemKey]);
    if (!is_array($item) || $item === [] || (string) $item['item_kind'] !== 'STOCK' || (string) $item['item_status'] !== 'ACTIVE') {
        throw new InvalidArgumentException('Inventory stock item does not belong to this company or is disabled.');
    }
    $warehouse = $db->GetRow("SELECT * FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_key=?{$suffix}", [$hash, $warehouseKey]);
    if (!is_array($warehouse) || $warehouse === []) {
        throw new InvalidArgumentException('Inventory warehouse does not belong to this company.');
    }
    if ((int) $warehouse['is_group'] === 1) {
        throw new InvalidArgumentException('Group warehouses cannot receive stock actions.');
    }
    if ((string) $warehouse['warehouse_status'] !== 'ACTIVE') {
        throw new InvalidArgumentException('Disabled warehouses cannot receive stock actions.');
    }
    return ['item' => $item, 'warehouse' => $warehouse];
}

function yovel_admin_inventory_lock_bin($db, array $company, string $itemKey, string $warehouseKey, array $dimensions = []): array
{
    if (!$db instanceof ADOConnection) {
        throw new InvalidArgumentException('Inventory bin locking requires an ADODB connection.');
    }
    $target = yovel_admin_inventory_validate_stock_target($db, $company, $itemKey, $warehouseKey, true);
    $tuple = yovel_admin_inventory_dimension_tuple($company, $dimensions);
    $hash = strtolower(trim((string) $company['company_key_hash']));
    $existing = $db->GetRow('SELECT * FROM project_company_inventory_bin WHERE company_key_hash=? AND item_key=? AND warehouse_key=? AND dimensions_checksum=? FOR UPDATE', [$hash, $itemKey, $warehouseKey, $tuple['dimensions_checksum']]);
    if (!is_array($existing) || $existing === []) {
        $key = bx_uuid();
        yovel_admin_inventory_execute($db, 'INSERT IGNORE INTO project_company_inventory_bin (bin_key,company_key,company_key_hash,item_key,warehouse_key,dimensions_json,dimensions_checksum,actual_qty) VALUES (?,?,?,?,?,?,?,?)', [$key, (string) ($company['company_key'] ?? ''), $hash, $itemKey, $warehouseKey, $tuple['dimensions_json'], $tuple['dimensions_checksum'], '0.000000000'], 'Inventory bin create');
        $existing = $db->GetRow('SELECT * FROM project_company_inventory_bin WHERE company_key_hash=? AND item_key=? AND warehouse_key=? AND dimensions_checksum=? FOR UPDATE', [$hash, $itemKey, $warehouseKey, $tuple['dimensions_checksum']]);
    }
    if (!is_array($existing) || $existing === []) {
        throw new RuntimeException('Inventory bin could not be locked.');
    }
    $existing['dimensions'] = $tuple['dimensions'];
    $existing['warehouse'] = $target['warehouse'];
    return $existing;
}

function yovel_admin_inventory_bin(array $company, string $itemKey, string $warehouseKey, array $dimensions = []): array
{
    yovel_admin_inventory_warehouse_control_schema();
    return yovel_admin_inventory_bin_read(bx_db(), $company, $itemKey, $warehouseKey, $dimensions);
}

function yovel_admin_inventory_bin_read(ADOConnection $db, array $company, string $itemKey, string $warehouseKey, array $dimensions = []): array
{
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $identity = ['item_key' => $itemKey, 'warehouse_key' => $warehouseKey, 'dimensions' => []];
    if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1 || !yovel_admin_is_uuid($itemKey) || !yovel_admin_is_uuid($warehouseKey)) {
        return yovel_admin_inventory_zero_projection($identity);
    }
    $targetCount = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_item i JOIN project_company_inventory_warehouse w ON w.company_key_hash=i.company_key_hash WHERE i.company_key_hash=? AND i.item_key=? AND w.warehouse_key=?', [$hash, $itemKey, $warehouseKey]);
    if ($targetCount !== 1) {
        return yovel_admin_inventory_zero_projection($identity);
    }
    $tuple = yovel_admin_inventory_dimension_tuple($company, $dimensions);
    $identity['dimensions'] = $tuple['dimensions'];
    $bin = $db->GetRow('SELECT * FROM project_company_inventory_bin WHERE company_key_hash=? AND item_key=? AND warehouse_key=? AND dimensions_checksum=?', [$hash, $itemKey, $warehouseKey, $tuple['dimensions_checksum']]);
    if (!is_array($bin) || $bin === []) {
        return yovel_admin_inventory_zero_projection($identity);
    }
    $totals = $db->GetRow(
        "SELECT
           COALESCE(SUM(CASE WHEN source_type='ACTUAL' THEN quantity ELSE 0 END),0) actual,
           COALESCE(SUM(CASE WHEN source_type='RESERVED' THEN quantity ELSE 0 END),0) reserved,
           COALESCE(SUM(CASE WHEN source_type='ORDERED' THEN quantity ELSE 0 END),0) ordered,
           COALESCE(SUM(CASE WHEN source_type='REQUESTED' THEN quantity ELSE 0 END),0) requested,
           COALESCE(SUM(CASE WHEN source_type='PLANNED' THEN quantity ELSE 0 END),0) planned
         FROM project_company_inventory_bin_source WHERE company_key_hash=? AND bin_key=? AND source_status='ACTIVE'",
        [$hash, $bin['bin_key']]
    );
    $projection = yovel_admin_inventory_projection_from_totals(is_array($totals) ? $totals : [], $identity + ['bin_key' => (string) $bin['bin_key'], 'dimensions_checksum' => $tuple['dimensions_checksum']]);
    if ((string) $bin['actual_qty'] !== (string) $projection['actual']) {
        throw new RuntimeException('Inventory bin actual quantity does not match its authoritative source rows.');
    }
    return $projection;
}

function yovel_admin_inventory_save_bin_source(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_warehouse_control_schema();
    $itemKey = trim((string) ($input['item_key'] ?? ''));
    $warehouseKey = trim((string) ($input['warehouse_key'] ?? ''));
    $dimensions = is_array($input['dimensions'] ?? null) ? $input['dimensions'] : (trim((string) ($input['dimensions_json'] ?? '')) !== '' ? json_decode((string) $input['dimensions_json'], true, 512, JSON_THROW_ON_ERROR) : []);
    $sourceOwner = strtoupper(yovel_admin_inventory_warehouse_text($input['source_owner'] ?? '', 80, 'Inventory source owner'));
    $sourceKey = yovel_admin_inventory_warehouse_text($input['source_key'] ?? '', 120, 'Inventory source key');
    $sourceLineKey = yovel_admin_inventory_warehouse_text($input['source_line_key'] ?? '', 120, 'Inventory source line key', false);
    $sourceType = strtoupper(trim((string) ($input['source_type'] ?? '')));
    $sourceStatus = strtoupper(trim((string) ($input['source_status'] ?? 'ACTIVE')));
    if (!in_array($sourceType, ['ACTUAL', 'RESERVED', 'ORDERED', 'REQUESTED', 'PLANNED'], true) || !in_array($sourceStatus, ['ACTIVE', 'CANCELLED'], true)) {
        throw new InvalidArgumentException('Inventory bin source type or status is invalid.');
    }
    $quantity = yovel_admin_inventory_decimal((string) ($input['quantity'] ?? ''));

    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($company, $scope, $itemKey, $warehouseKey, $dimensions, $sourceOwner, $sourceKey, $sourceLineKey, $sourceType, $sourceStatus, $quantity): array {
        yovel_admin_inventory_lock_company($db, $scope);
        $bin = yovel_admin_inventory_lock_bin($db, $company, $itemKey, $warehouseKey, $dimensions);
        $existing = $db->GetRow('SELECT * FROM project_company_inventory_bin_source WHERE company_key_hash=? AND source_owner=? AND source_key=? AND source_line_key=? AND source_type=? FOR UPDATE', [$scope[1], $sourceOwner, $sourceKey, $sourceLineKey, $sourceType]);
        if (is_array($existing) && $existing !== [] && ((string) $existing['bin_key'] !== (string) $bin['bin_key'] || (string) $existing['item_key'] !== $itemKey || (string) $existing['warehouse_key'] !== $warehouseKey)) {
            throw new InvalidArgumentException('Inventory source keys cannot move between bin tuples.');
        }
        $sourceRowKey = is_array($existing) && $existing !== [] ? (string) $existing['bin_source_key'] : bx_uuid();
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_bin_source SET source_status=?,quantity=?,updated_by_admin_key=? WHERE company_key_hash=? AND bin_source_key=?', [$sourceStatus, $quantity, $scope[2], $scope[1], $sourceRowKey], 'Inventory bin source update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_bin_source (bin_source_key,company_key,company_key_hash,bin_key,item_key,warehouse_key,dimensions_checksum,source_owner,source_key,source_line_key,source_type,source_status,quantity,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$sourceRowKey, $scope[0], $scope[1], $bin['bin_key'], $itemKey, $warehouseKey, $bin['dimensions_checksum'], $sourceOwner, $sourceKey, $sourceLineKey, $sourceType, $sourceStatus, $quantity, $scope[2], $scope[2]], 'Inventory bin source create');
        }
        yovel_admin_inventory_warehouse_fault('bin_source_after_write');

        $actual = yovel_admin_inventory_decimal((string) $db->GetOne("SELECT COALESCE(SUM(quantity),0) FROM project_company_inventory_bin_source WHERE company_key_hash=? AND bin_key=? AND source_status='ACTIVE' AND source_type='ACTUAL'", [$scope[1], $bin['bin_key']]));
        $settings = $db->GetRow('SELECT allow_negative_stock,capacity_enforcement FROM project_company_inventory_stock_setting WHERE company_key_hash=? FOR UPDATE', [$scope[1]]);
        $allowNegative = (int) ($settings['allow_negative_stock'] ?? 0);
        $enforceCapacity = !is_array($settings) || $settings === [] ? 1 : (int) $settings['capacity_enforcement'];
        if ($allowNegative !== 1 && bccomp($actual, '0', 9) === -1) {
            throw new InvalidArgumentException('Inventory Stock Settings prohibit negative actual quantity.');
        }
        $capacity = yovel_admin_inventory_decimal((string) $bin['warehouse']['capacity_qty']);
        if ($enforceCapacity === 1 && bccomp($capacity, '0', 9) === 1) {
            $otherActual = yovel_admin_inventory_decimal((string) $db->GetOne('SELECT COALESCE(SUM(actual_qty),0) FROM project_company_inventory_bin WHERE company_key_hash=? AND warehouse_key=? AND bin_key<>?', [$scope[1], $warehouseKey, $bin['bin_key']]));
            if (bccomp(bcadd($otherActual, $actual, 9), $capacity, 9) === 1) {
                throw new InvalidArgumentException('Inventory warehouse capacity would be exceeded.');
            }
        }
        yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_bin SET actual_qty=? WHERE company_key_hash=? AND bin_key=?', [$actual, $scope[1], $bin['bin_key']], 'Inventory bin actual update');
        yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_item SET stock_activity_count=GREATEST(stock_activity_count,1) WHERE company_key_hash=? AND item_key=?', [$scope[1], $itemKey], 'Inventory item stock activity update');
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_inventory_bin_source', $sourceRowKey, ['company_key' => $scope[0], 'item_key' => $itemKey, 'warehouse_key' => $warehouseKey, 'source_owner' => $sourceOwner, 'source_key' => $sourceKey, 'source_line_key' => $sourceLineKey, 'source_type' => $sourceType, 'source_status' => $sourceStatus, 'quantity' => $quantity, 'admin_key' => $scope[2]], 'Company administrator saved an authoritative Inventory bin source.');
        $readback = $db->GetRow('SELECT * FROM project_company_inventory_bin_source WHERE company_key_hash=? AND bin_source_key=?', [$scope[1], $sourceRowKey]);
        yovel_admin_inventory_assert_readback(['source_owner' => $sourceOwner, 'source_key' => $sourceKey, 'source_line_key' => $sourceLineKey, 'source_type' => $sourceType, 'source_status' => $sourceStatus, 'quantity' => $quantity], is_array($readback) ? $readback : [], ['source_owner', 'source_key', 'source_line_key', 'source_type', 'source_status', 'quantity'], 'Inventory bin source');
        $projection = yovel_admin_inventory_bin_read($db, $company, $itemKey, $warehouseKey, $dimensions);
        if ((string) $projection['actual'] !== $actual) {
            throw new RuntimeException('Inventory bin projection read-back verification failed.');
        }
        yovel_admin_inventory_warehouse_fault('bin_source_after_readback');
        return $projection + ['bin_source_key' => $sourceRowKey, 'source_type' => $sourceType, 'source_key' => $sourceKey];
    });
}

function yovel_admin_save_putaway_rule(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_warehouse_control_schema();
    $key = trim((string) ($input['putaway_rule_key'] ?? ''));
    $itemKey = trim((string) ($input['item_key'] ?? ''));
    $warehouseKey = trim((string) ($input['warehouse_key'] ?? ''));
    if (($key !== '' && !yovel_admin_is_uuid($key)) || ($itemKey !== '' && !yovel_admin_is_uuid($itemKey)) || !yovel_admin_is_uuid($warehouseKey)) {
        throw new InvalidArgumentException('Putaway Rule reference is invalid.');
    }
    $priority = filter_var($input['priority'] ?? 100, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($priority === false) {
        throw new InvalidArgumentException('Putaway Rule priority is invalid.');
    }
    $dimensions = is_array($input['dimensions'] ?? null) ? $input['dimensions'] : (trim((string) ($input['dimensions_json'] ?? '')) !== '' ? json_decode((string) $input['dimensions_json'], true, 512, JSON_THROW_ON_ERROR) : []);
    $status = yovel_admin_inventory_warehouse_status($input['putaway_rule_status'] ?? 'ACTIVE', 'Putaway Rule');
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($company, $scope, $key, $itemKey, $warehouseKey, $priority, $dimensions, $status): array {
        yovel_admin_inventory_lock_company($db, $scope);
        if ($itemKey !== '') {
            yovel_admin_inventory_validate_stock_target($db, $company, $itemKey, $warehouseKey, true);
        } else {
            $warehouse = $db->GetRow("SELECT is_group,warehouse_status FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_key=? FOR UPDATE", [$scope[1], $warehouseKey]);
            if (!is_array($warehouse) || $warehouse === [] || (int) $warehouse['is_group'] === 1 || (string) $warehouse['warehouse_status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('Putaway Rule requires an active leaf warehouse in this company.');
            }
        }
        $tuple = yovel_admin_inventory_dimension_tuple($company, $dimensions);
        $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_inventory_putaway_rule WHERE company_key_hash=? AND putaway_rule_key=? FOR UPDATE', [$scope[1], $key]) : $db->GetRow('SELECT * FROM project_company_inventory_putaway_rule WHERE company_key_hash=? AND item_key <=> ? AND warehouse_key=? AND dimensions_checksum=? FOR UPDATE', [$scope[1], $itemKey !== '' ? $itemKey : null, $warehouseKey, $tuple['dimensions_checksum']]);
        if ($key !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Putaway Rule does not belong to this company.');
        }
        $key = is_array($existing) && $existing !== [] ? (string) $existing['putaway_rule_key'] : ($key !== '' ? $key : bx_uuid());
        $expected = ['item_key' => $itemKey, 'warehouse_key' => $warehouseKey, 'dimensions_json' => $tuple['dimensions_json'], 'dimensions_checksum' => $tuple['dimensions_checksum'], 'priority' => (string) $priority, 'putaway_rule_status' => $status];
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_putaway_rule SET item_key=?,warehouse_key=?,dimensions_json=?,dimensions_checksum=?,priority=?,putaway_rule_status=?,updated_by_admin_key=? WHERE company_key_hash=? AND putaway_rule_key=?', [$itemKey !== '' ? $itemKey : null, $warehouseKey, $tuple['dimensions_json'], $tuple['dimensions_checksum'], $priority, $status, $scope[2], $scope[1], $key], 'Inventory Putaway Rule update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_putaway_rule (putaway_rule_key,company_key,company_key_hash,item_key,warehouse_key,dimensions_json,dimensions_checksum,priority,putaway_rule_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$key, $scope[0], $scope[1], $itemKey !== '' ? $itemKey : null, $warehouseKey, $tuple['dimensions_json'], $tuple['dimensions_checksum'], $priority, $status, $scope[2], $scope[2]], 'Inventory Putaway Rule create');
        }
        yovel_admin_inventory_warehouse_fault('putaway_after_write');
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_inventory_putaway_rule', $key, $expected + ['company_key' => $scope[0], 'admin_key' => $scope[2]], 'Company administrator saved an Inventory Putaway Rule.');
        $actual = $db->GetRow('SELECT * FROM project_company_inventory_putaway_rule WHERE company_key_hash=? AND putaway_rule_key=?', [$scope[1], $key]);
        $actual = is_array($actual) ? $actual : [];
        $actual['item_key'] = (string) ($actual['item_key'] ?? '');
        yovel_admin_inventory_assert_readback($expected, $actual, array_keys($expected), 'Inventory Putaway Rule');
        $actual['dimensions'] = $tuple['dimensions'];
        return $actual;
    });
}

function yovel_admin_inventory_putaway_rules(array $company): array
{
    yovel_admin_inventory_warehouse_control_schema();
    return bx_db()->GetAll("SELECT r.*,i.item_code,i.item_name,w.warehouse_code,w.warehouse_name FROM project_company_inventory_putaway_rule r LEFT JOIN project_company_inventory_item i ON i.company_key_hash=r.company_key_hash AND i.item_key=r.item_key JOIN project_company_inventory_warehouse w ON w.company_key_hash=r.company_key_hash AND w.warehouse_key=r.warehouse_key WHERE r.company_key_hash=? ORDER BY r.priority,r.x_id", [strtolower(trim((string) ($company['company_key_hash'] ?? '')))]);
}

function yovel_admin_putaway_plan(array $company, string $itemKey, string $qty, array $dimensions = []): array
{
    yovel_admin_inventory_warehouse_control_schema();
    $quantity = yovel_admin_inventory_decimal($qty);
    if (bccomp($quantity, '0', 9) !== 1) {
        throw new InvalidArgumentException('Putaway quantity must be greater than zero.');
    }
    $tuple = yovel_admin_inventory_dimension_tuple($company, $dimensions);
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $item = bx_db()->GetRow("SELECT item_key FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=? AND item_kind='STOCK' AND item_status='ACTIVE'", [$hash, $itemKey]);
    if (!is_array($item) || $item === []) {
        throw new InvalidArgumentException('Putaway item does not belong to this company or is disabled.');
    }
    $rules = bx_db()->GetAll(
        "SELECT r.*,w.warehouse_code,w.warehouse_name,w.capacity_qty,
                COALESCE((SELECT SUM(b.actual_qty) FROM project_company_inventory_bin b WHERE b.company_key_hash=w.company_key_hash AND b.warehouse_key=w.warehouse_key),0) capacity_used
         FROM project_company_inventory_putaway_rule r
         JOIN project_company_inventory_warehouse w ON w.company_key_hash=r.company_key_hash AND w.warehouse_key=r.warehouse_key
         WHERE r.company_key_hash=? AND r.putaway_rule_status='ACTIVE' AND (r.item_key=? OR r.item_key IS NULL)
           AND r.dimensions_checksum=? AND w.is_group=0 AND w.warehouse_status='ACTIVE'
         ORDER BY r.priority,w.putaway_priority,r.x_id",
        [$hash, $itemKey, $tuple['dimensions_checksum']]
    );
    $remaining = $quantity;
    $allocations = [];
    foreach ($rules as $rule) {
        if (bccomp($remaining, '0', 9) !== 1) {
            break;
        }
        $capacity = yovel_admin_inventory_decimal((string) $rule['capacity_qty']);
        $available = bccomp($capacity, '0', 9) === 1 ? bcsub($capacity, yovel_admin_inventory_decimal((string) $rule['capacity_used']), 9) : $remaining;
        if (bccomp($available, '0', 9) !== 1) {
            continue;
        }
        $allocated = bccomp($remaining, $available, 9) === 1 ? $available : $remaining;
        $allocations[] = ['putaway_rule_key' => (string) $rule['putaway_rule_key'], 'warehouse_key' => (string) $rule['warehouse_key'], 'warehouse_code' => (string) $rule['warehouse_code'], 'quantity' => yovel_admin_inventory_decimal($allocated)];
        $remaining = bcsub($remaining, $allocated, 9);
    }
    if (bccomp($remaining, '0', 9) === 1) {
        throw new InvalidArgumentException('Putaway rules do not provide enough warehouse capacity.');
    }
    return ['item_key' => $itemKey, 'requested_quantity' => $quantity, 'dimensions' => $tuple['dimensions'], 'allocations' => $allocations, 'unallocated_quantity' => '0.000000000'];
}

function yovel_admin_save_inventory_reorder_rule(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_warehouse_control_schema();
    $key = trim((string) ($input['reorder_rule_key'] ?? ''));
    $itemKey = trim((string) ($input['item_key'] ?? ''));
    $warehouseKey = trim((string) ($input['warehouse_key'] ?? ''));
    if (($key !== '' && !yovel_admin_is_uuid($key)) || !yovel_admin_is_uuid($itemKey) || !yovel_admin_is_uuid($warehouseKey)) {
        throw new InvalidArgumentException('Item Reorder reference is invalid.');
    }
    $level = yovel_admin_inventory_decimal((string) ($input['reorder_level'] ?? ''));
    $quantity = yovel_admin_inventory_decimal((string) ($input['reorder_quantity'] ?? $input['reorder_qty'] ?? ''));
    if (bccomp($level, '0', 9) === -1 || bccomp($quantity, '0', 9) !== 1) {
        throw new InvalidArgumentException('Item Reorder quantities are invalid.');
    }
    $status = yovel_admin_inventory_warehouse_status($input['reorder_rule_status'] ?? 'ACTIVE', 'Item Reorder');
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($company, $scope, $key, $itemKey, $warehouseKey, $level, $quantity, $status): array {
        yovel_admin_inventory_lock_company($db, $scope);
        yovel_admin_inventory_validate_stock_target($db, $company, $itemKey, $warehouseKey, true);
        $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_inventory_reorder_rule WHERE company_key_hash=? AND reorder_rule_key=? FOR UPDATE', [$scope[1], $key]) : $db->GetRow('SELECT * FROM project_company_inventory_reorder_rule WHERE company_key_hash=? AND item_key=? AND warehouse_key=? FOR UPDATE', [$scope[1], $itemKey, $warehouseKey]);
        if ($key !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Item Reorder rule does not belong to this company.');
        }
        $key = is_array($existing) && $existing !== [] ? (string) $existing['reorder_rule_key'] : ($key !== '' ? $key : bx_uuid());
        $expected = ['item_key' => $itemKey, 'warehouse_key' => $warehouseKey, 'reorder_level' => $level, 'reorder_quantity' => $quantity, 'reorder_rule_status' => $status];
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_reorder_rule SET item_key=?,warehouse_key=?,reorder_level=?,reorder_quantity=?,reorder_rule_status=?,updated_by_admin_key=? WHERE company_key_hash=? AND reorder_rule_key=?', [$itemKey, $warehouseKey, $level, $quantity, $status, $scope[2], $scope[1], $key], 'Inventory Item Reorder update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_reorder_rule (reorder_rule_key,company_key,company_key_hash,item_key,warehouse_key,reorder_level,reorder_quantity,reorder_rule_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?)', [$key, $scope[0], $scope[1], $itemKey, $warehouseKey, $level, $quantity, $status, $scope[2], $scope[2]], 'Inventory Item Reorder create');
        }
        yovel_admin_inventory_warehouse_fault('reorder_after_write');
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_inventory_reorder_rule', $key, $expected + ['company_key' => $scope[0], 'admin_key' => $scope[2]], 'Company administrator saved an Inventory Item Reorder rule.');
        $actual = $db->GetRow('SELECT * FROM project_company_inventory_reorder_rule WHERE company_key_hash=? AND reorder_rule_key=?', [$scope[1], $key]);
        yovel_admin_inventory_assert_readback($expected, is_array($actual) ? $actual : [], array_keys($expected), 'Inventory Item Reorder');
        return $actual;
    });
}

function yovel_admin_inventory_reorder_rules(array $company): array
{
    yovel_admin_inventory_warehouse_control_schema();
    return bx_db()->GetAll("SELECT r.*,i.item_code,i.item_name,w.warehouse_code,w.warehouse_name FROM project_company_inventory_reorder_rule r JOIN project_company_inventory_item i ON i.company_key_hash=r.company_key_hash AND i.item_key=r.item_key JOIN project_company_inventory_warehouse w ON w.company_key_hash=r.company_key_hash AND w.warehouse_key=r.warehouse_key WHERE r.company_key_hash=? ORDER BY i.item_code,w.warehouse_code", [strtolower(trim((string) ($company['company_key_hash'] ?? '')))]);
}

function yovel_admin_inventory_projection_for_warehouse_item(array $company, string $itemKey, string $warehouseKey): array
{
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $totals = bx_db()->GetRow(
        "SELECT
           COALESCE(SUM(CASE WHEN source_type='ACTUAL' THEN quantity ELSE 0 END),0) actual,
           COALESCE(SUM(CASE WHEN source_type='RESERVED' THEN quantity ELSE 0 END),0) reserved,
           COALESCE(SUM(CASE WHEN source_type='ORDERED' THEN quantity ELSE 0 END),0) ordered,
           COALESCE(SUM(CASE WHEN source_type='REQUESTED' THEN quantity ELSE 0 END),0) requested,
           COALESCE(SUM(CASE WHEN source_type='PLANNED' THEN quantity ELSE 0 END),0) planned
         FROM project_company_inventory_bin_source WHERE company_key_hash=? AND item_key=? AND warehouse_key=? AND source_status='ACTIVE'",
        [$hash, $itemKey, $warehouseKey]
    );
    return yovel_admin_inventory_projection_from_totals(is_array($totals) ? $totals : [], ['item_key' => $itemKey, 'warehouse_key' => $warehouseKey]);
}

function yovel_admin_reorder_recommendations(array $company, ?string $warehouseKey = null): array
{
    yovel_admin_inventory_warehouse_control_schema();
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $sql = "SELECT r.*,i.item_code,i.item_name,w.warehouse_code,w.warehouse_name
            FROM project_company_inventory_reorder_rule r
            JOIN project_company_inventory_item i ON i.company_key_hash=r.company_key_hash AND i.item_key=r.item_key AND i.item_status='ACTIVE' AND i.item_kind='STOCK'
            JOIN project_company_inventory_warehouse w ON w.company_key_hash=r.company_key_hash AND w.warehouse_key=r.warehouse_key AND w.warehouse_status='ACTIVE' AND w.is_group=0
            WHERE r.company_key_hash=? AND r.reorder_rule_status='ACTIVE'";
    $params = [$hash];
    if ($warehouseKey !== null && trim($warehouseKey) !== '') {
        $sql .= ' AND r.warehouse_key=?';
        $params[] = trim($warehouseKey);
    }
    $rows = bx_db()->GetAll($sql . ' ORDER BY i.item_code,w.warehouse_code', $params);
    $recommendations = [];
    foreach ($rows as $row) {
        $projection = yovel_admin_inventory_projection_for_warehouse_item($company, (string) $row['item_key'], (string) $row['warehouse_key']);
        if (bccomp((string) $projection['projected'], (string) $row['reorder_level'], 9) === -1) {
            $recommendations[] = $row + $projection + ['recommended_quantity' => yovel_admin_inventory_decimal((string) $row['reorder_quantity'])];
        }
    }
    return $recommendations;
}

function yovel_admin_inventory_warehouse_capacity_summary(array $company): array
{
    yovel_admin_inventory_warehouse_control_schema();
    $rows = yovel_admin_inventory_warehouses($company, ['leaf_only' => true]);
    foreach ($rows as &$row) {
        $capacity = yovel_admin_inventory_decimal((string) $row['capacity_qty']);
        $used = yovel_admin_inventory_decimal((string) $row['capacity_used']);
        $row['capacity_qty'] = $capacity;
        $row['capacity_used'] = $used;
        $row['capacity_available'] = bccomp($capacity, '0', 9) === 1 ? yovel_admin_inventory_decimal(bcsub($capacity, $used, 9)) : null;
    }
    unset($row);
    return $rows;
}

function yovel_admin_inventory_item_price_stock(array $company, array $filters = []): array
{
    yovel_admin_inventory_catalogue_schema();
    yovel_admin_inventory_warehouse_control_schema();
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
        return [];
    }
    $sql = "SELECT p.*,i.item_code,i.item_name,i.stock_uom_code,l.price_list_code,l.price_list_name,l.is_selling,l.is_buying,
                   COALESCE(s.actual,0) actual,COALESCE(s.reserved,0) reserved,
                   COALESCE(s.ordered,0) ordered,COALESCE(s.requested,0) requested,COALESCE(s.planned,0) planned
            FROM project_company_inventory_item_price p
            JOIN project_company_inventory_item i ON i.company_key_hash=p.company_key_hash AND i.item_key=p.item_key
            JOIN project_company_inventory_price_list l ON l.company_key_hash=p.company_key_hash AND l.price_list_key=p.price_list_key
            LEFT JOIN (
                SELECT company_key_hash,item_key,
                       SUM(CASE WHEN source_type='ACTUAL' THEN quantity ELSE 0 END) actual,
                       SUM(CASE WHEN source_type='RESERVED' THEN quantity ELSE 0 END) reserved,
                       SUM(CASE WHEN source_type='ORDERED' THEN quantity ELSE 0 END) ordered,
                       SUM(CASE WHEN source_type='REQUESTED' THEN quantity ELSE 0 END) requested,
                       SUM(CASE WHEN source_type='PLANNED' THEN quantity ELSE 0 END) planned
                FROM project_company_inventory_bin_source WHERE source_status='ACTIVE' GROUP BY company_key_hash,item_key
            ) s ON s.company_key_hash=p.company_key_hash AND s.item_key=p.item_key
            WHERE p.company_key_hash=?";
    $params = [$hash];
    $itemKey = trim((string) ($filters['item_key'] ?? ''));
    if ($itemKey !== '') {
        $sql .= ' AND p.item_key=?';
        $params[] = $itemKey;
    }
    $rows = bx_db()->GetAll($sql . ' ORDER BY i.item_code,l.price_list_code,p.valid_from', $params);
    if (!is_array($rows)) {
        throw new RuntimeException('Inventory Item Price Stock report could not be loaded.');
    }
    foreach ($rows as &$row) {
        $row = $row + yovel_admin_inventory_projection_from_totals($row, ['item_key' => (string) $row['item_key']]);
    }
    unset($row);
    return $rows;
}
