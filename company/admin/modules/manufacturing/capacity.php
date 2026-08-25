<?php
declare(strict_types=1);

function yovel_admin_manufacturing_capacity_rows(array $input, string $field): array
{
    $rows = $input[$field] ?? [];
    if (is_string($rows)) {
        $rows = json_decode($rows !== '' ? $rows : '[]', true, 512, JSON_THROW_ON_ERROR);
    }
    if (!is_array($rows) || count($rows) > 200) {
        throw new InvalidArgumentException('Manufacturing ' . str_replace('_', ' ', $field) . ' are invalid.');
    }
    return array_values($rows);
}

function yovel_admin_manufacturing_capacity_code(mixed $value, string $label): string
{
    $code = strtoupper(trim((string) $value));
    if ($code === '' || strlen($code) > 80 || preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $code) !== 1) {
        throw new InvalidArgumentException($label . ' must be a valid 1 to 80 character code.');
    }
    return $code;
}

function yovel_admin_manufacturing_capacity_text(mixed $value, string $label, int $maximum, bool $required = true): string
{
    $text = trim((string) $value);
    if (($required && $text === '') || strlen($text) > $maximum) {
        throw new InvalidArgumentException($label . ' must contain ' . ($required ? '1 to ' : 'up to ') . $maximum . ' characters.');
    }
    return $text;
}

function yovel_admin_manufacturing_capacity_decimal(mixed $value, string $label, int $scale = 6, bool $positive = false): string
{
    $value = trim((string) $value);
    if ($value === '' || preg_match('/^\d{1,12}(?:\.\d{1,6})?$/', $value) !== 1) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    $normalized = number_format((float) $value, $scale, '.', '');
    if ($positive && bccomp($normalized, '0', $scale) !== 1) {
        throw new InvalidArgumentException($label . ' must be greater than zero.');
    }
    return $normalized;
}

function yovel_admin_manufacturing_capacity_status(mixed $value): string
{
    $status = strtoupper(trim((string) ($value ?: 'DRAFT')));
    if (!in_array($status, ['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED'], true)) {
        throw new InvalidArgumentException('Manufacturing record status is invalid.');
    }
    return $status;
}

function yovel_admin_manufacturing_capacity_key(mixed $value, string $label, bool $required = true): string
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

function yovel_admin_manufacturing_capacity_account(
    ADOConnection $db,
    array $company,
    mixed $value,
    string $label,
    ?array $gateway
): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    unset($db);
    $resolved = yovel_admin_manufacturing_dependency_call(
        $gateway ?? yovel_admin_manufacturing_dependency_gateway(),
        'account_validation',
        [$company, $value]
    );
    $record = is_array($resolved) && ($resolved['ok'] ?? false) === true && is_array($resolved['record'] ?? null) ? $resolved['record'] : null;
    if (!is_array($record)
        || !yovel_admin_is_uuid((string) ($record['account_key'] ?? ''))
        || (string) ($record['root_type'] ?? '') !== 'EXPENSE'
        || !empty($record['is_group'])
        || !empty($record['freeze_account'])
        || (string) ($record['account_status'] ?? '') !== 'ACTIVE') {
        throw new InvalidArgumentException($label . ' must be an active unfrozen Finance expense posting account.');
    }
    return strtolower((string) $record['account_key']);
}

function yovel_admin_manufacturing_capacity_child_checksum(array $rows): string
{
    return hash('sha256', yovel_admin_manufacturing_json(array_values($rows)));
}

function yovel_admin_manufacturing_capacity_owned_row(ADOConnection $db, string $table, string $keyColumn, string $companyKeyHash, string $key): array
{
    $allowed = [
        'project_company_manufacturing_operation' => 'operation_key',
        'project_company_manufacturing_routing' => 'routing_key',
        'project_company_manufacturing_workstation_type' => 'workstation_type_key',
        'project_company_manufacturing_plant_floor' => 'plant_floor_key',
        'project_company_manufacturing_workstation' => 'workstation_key',
        'project_company_manufacturing_downtime' => 'downtime_key',
    ];
    if (($allowed[$table] ?? null) !== $keyColumn) {
        throw new InvalidArgumentException('Manufacturing owned-row mapping is invalid.');
    }
    $row = $db->GetRow("SELECT * FROM `{$table}` WHERE company_key_hash = ? AND `{$keyColumn}` = ? LIMIT 1", [$companyKeyHash, $key]);
    if (!is_array($row) || $row === []) {
        throw new InvalidArgumentException('Manufacturing referenced record is not owned by this company.');
    }
    return $row;
}

function yovel_admin_manufacturing_operation_row(ADOConnection $db, string $companyKeyHash, string $operationKey): array
{
    $row = yovel_admin_manufacturing_capacity_owned_row($db, 'project_company_manufacturing_operation', 'operation_key', $companyKeyHash, $operationKey);
    $children = $db->GetAll(
        'SELECT sub_operation_key, operation_key, sequence_no AS sequence, duration_minutes FROM project_company_manufacturing_sub_operation WHERE company_key_hash = ? AND parent_operation_key = ? ORDER BY sequence_no, x_id',
        [$companyKeyHash, $operationKey]
    );
    $row['description'] = (string) ($row['description'] ?? '');
    $row['cost_account_key'] = (string) ($row['cost_account_key'] ?? '');
    $row['sub_operations'] = is_array($children) ? $children : [];
    $checksumRows = array_map(static fn (array $child): array => ['operation_key' => (string) $child['operation_key'], 'sequence' => (int) $child['sequence'], 'duration_minutes' => (string) $child['duration_minutes']], $row['sub_operations']);
    $row['children_checksum'] = yovel_admin_manufacturing_capacity_child_checksum($checksumRows);
    return $row;
}

function yovel_admin_save_manufacturing_operation(array $company, array $admin, array $input, ?array $gateway = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $code = yovel_admin_manufacturing_capacity_code($input['operation_code'] ?? '', 'Operation code');
    $name = yovel_admin_manufacturing_capacity_text($input['operation_name'] ?? '', 'Operation name', 180);
    $description = yovel_admin_manufacturing_capacity_text($input['description'] ?? '', 'Operation description', 1000, false);
    $rate = yovel_admin_manufacturing_capacity_decimal($input['hourly_rate'] ?? '0', 'Operation hourly rate');
    $status = yovel_admin_manufacturing_capacity_status($input['record_status'] ?? 'DRAFT');
    $requestedKey = yovel_admin_manufacturing_capacity_key($input['operation_key'] ?? '', 'Operation key', false);
    $rawChildren = yovel_admin_manufacturing_capacity_rows($input, 'sub_operations');
    $children = [];
    $sequences = [];
    $references = [];
    foreach ($rawChildren as $child) {
        if (!is_array($child)) {
            throw new InvalidArgumentException('Sub-operation row is invalid.');
        }
        $operationKey = yovel_admin_manufacturing_capacity_key($child['operation_key'] ?? '', 'Sub-operation');
        $sequence = (int) ($child['sequence'] ?? 0);
        $duration = yovel_admin_manufacturing_capacity_decimal($child['duration_minutes'] ?? '', 'Sub-operation duration', 4, true);
        if ($sequence < 1 || $sequence > 100000 || isset($sequences[$sequence]) || isset($references[$operationKey])) {
            throw new InvalidArgumentException('Sub-operations require unique positive sequence and operation values.');
        }
        $sequences[$sequence] = true;
        $references[$operationKey] = true;
        $children[] = ['operation_key' => $operationKey, 'sequence' => $sequence, 'duration_minutes' => $duration];
    }
    usort($children, static fn (array $left, array $right): int => [$left['sequence'], $left['operation_key']] <=> [$right['sequence'], $right['operation_key']]);

    $saved = yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'OPERATION',
        'SAVE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $code, $name, $description, $rate, $status, $requestedKey, $children, $gateway, $input): array {
            $existing = $requestedKey !== ''
                ? $db->GetRow('SELECT * FROM project_company_manufacturing_operation WHERE company_key_hash = ? AND operation_key = ? FOR UPDATE', [$companyKeyHash, $requestedKey])
                : $db->GetRow('SELECT * FROM project_company_manufacturing_operation WHERE company_key_hash = ? AND operation_code = ? FOR UPDATE', [$companyKeyHash, $code]);
            if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
                throw new InvalidArgumentException('Operation is not owned by this company.');
            }
            $isUpdate = is_array($existing) && $existing !== [];
            $operationKey = $isUpdate ? (string) $existing['operation_key'] : bx_uuid();
            if ($isUpdate && (string) $existing['operation_code'] !== $code) {
                $used = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_manufacturing_routing_operation ro INNER JOIN project_company_manufacturing_routing r ON r.company_key_hash=ro.company_key_hash AND r.routing_key=ro.routing_key WHERE ro.company_key_hash=? AND ro.operation_key=? AND r.record_status='ACTIVE'", [$companyKeyHash, $operationKey]);
                if ($used > 0) {
                    throw new InvalidArgumentException('Operation code is immutable while referenced by an active routing.');
                }
            }
            foreach ($children as $child) {
                if ($child['operation_key'] === $operationKey) {
                    throw new InvalidArgumentException('An operation cannot contain itself as a sub-operation.');
                }
                $referenced = yovel_admin_manufacturing_operation_row($db, $companyKeyHash, $child['operation_key']);
                if ((string) $referenced['record_status'] !== 'ACTIVE') {
                    throw new InvalidArgumentException('Sub-operations must reference active company operations.');
                }
            }
            $account = yovel_admin_manufacturing_capacity_account($db, ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash], $input['cost_account_key'] ?? '', 'Operation cost account', $gateway);
            yovel_admin_manufacturing_execute(
                $db,
                "INSERT INTO project_company_manufacturing_operation (operation_key, company_key, company_key_hash, operation_code, operation_name, description, hourly_rate, cost_account_key, record_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE operation_code=VALUES(operation_code), operation_name=VALUES(operation_name), description=VALUES(description), hourly_rate=VALUES(hourly_rate), cost_account_key=VALUES(cost_account_key), record_status=VALUES(record_status), updated_by_admin_key=VALUES(updated_by_admin_key)",
                [$operationKey, $companyKey, $companyKeyHash, $code, $name, $description !== '' ? $description : null, $rate, $account !== '' ? $account : null, $status, $adminKey, $adminKey],
                'Manufacturing operation save'
            );
            yovel_admin_manufacturing_execute($db, 'DELETE FROM project_company_manufacturing_sub_operation WHERE company_key_hash = ? AND parent_operation_key = ?', [$companyKeyHash, $operationKey], 'Manufacturing sub-operation replace');
            foreach ($children as $child) {
                yovel_admin_manufacturing_execute(
                    $db,
                    'INSERT INTO project_company_manufacturing_sub_operation (sub_operation_key, company_key, company_key_hash, parent_operation_key, operation_key, sequence_no, duration_minutes, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [bx_uuid(), $companyKey, $companyKeyHash, $operationKey, $child['operation_key'], $child['sequence'], $child['duration_minutes'], $adminKey],
                    'Manufacturing sub-operation save'
                );
            }
            $expectedChildren = array_map(static fn (array $row): array => [
                'operation_key' => $row['operation_key'], 'sequence' => $row['sequence'], 'duration_minutes' => $row['duration_minutes'],
            ], $children);
            return [
                'record_key' => $operationKey, 'business_key' => $code, 'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey, 'audit_action' => $isUpdate ? 'UPDATE' : 'CREATE',
                'persisted_fields' => [
                    'operation_key' => $operationKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                    'operation_code' => $code, 'operation_name' => $name, 'description' => $description,
                    'hourly_rate' => $rate, 'cost_account_key' => $account, 'record_status' => $status,
                    'children_checksum' => yovel_admin_manufacturing_capacity_child_checksum($expectedChildren),
                ],
            ];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_operation_row($db, (string) $expected['company_key_hash'], (string) $expected['record_key'])
    );
    return yovel_admin_manufacturing_operation_row(bx_db(), $companyKeyHash, (string) $saved['operation_key']) + ['_audit_action' => $saved['_audit_action']];
}

function yovel_admin_manufacturing_operations(array $company): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $keys = bx_db()->GetAll("SELECT operation_key FROM project_company_manufacturing_operation WHERE company_key_hash=? AND record_status<>'ARCHIVED' ORDER BY operation_code, x_id", [$companyKeyHash]);
    return array_map(static fn (array $row): array => yovel_admin_manufacturing_operation_row(bx_db(), $companyKeyHash, (string) $row['operation_key']), is_array($keys) ? $keys : []);
}

function yovel_admin_manufacturing_routing_row(ADOConnection $db, string $companyKeyHash, string $routingKey): array
{
    $row = yovel_admin_manufacturing_capacity_owned_row($db, 'project_company_manufacturing_routing', 'routing_key', $companyKeyHash, $routingKey);
    $children = $db->GetAll(
        'SELECT ro.routing_operation_key, ro.operation_key, o.operation_code, o.operation_name, ro.sequence_no AS sequence, ro.duration_minutes FROM project_company_manufacturing_routing_operation ro INNER JOIN project_company_manufacturing_operation o ON o.company_key_hash=ro.company_key_hash AND o.operation_key=ro.operation_key WHERE ro.company_key_hash=? AND ro.routing_key=? ORDER BY ro.sequence_no, ro.x_id',
        [$companyKeyHash, $routingKey]
    );
    $row['operations'] = is_array($children) ? $children : [];
    $checksumRows = array_map(static fn (array $child): array => ['operation_key' => (string) $child['operation_key'], 'sequence' => (int) $child['sequence'], 'duration_minutes' => (string) $child['duration_minutes']], $row['operations']);
    $row['children_checksum'] = yovel_admin_manufacturing_capacity_child_checksum($checksumRows);
    return $row;
}

function yovel_admin_save_manufacturing_routing(array $company, array $admin, array $input): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $code = yovel_admin_manufacturing_capacity_code($input['routing_code'] ?? '', 'Routing code');
    $name = yovel_admin_manufacturing_capacity_text($input['routing_name'] ?? '', 'Routing name', 180);
    $status = yovel_admin_manufacturing_capacity_status($input['record_status'] ?? 'DRAFT');
    $requestedKey = yovel_admin_manufacturing_capacity_key($input['routing_key'] ?? '', 'Routing key', false);
    $children = [];
    $sequences = [];
    $references = [];
    foreach (yovel_admin_manufacturing_capacity_rows($input, 'operations') as $child) {
        if (!is_array($child)) {
            throw new InvalidArgumentException('Routing operation row is invalid.');
        }
        $operationKey = yovel_admin_manufacturing_capacity_key($child['operation_key'] ?? '', 'Routing operation');
        $sequence = (int) ($child['sequence'] ?? 0);
        $duration = yovel_admin_manufacturing_capacity_decimal($child['duration_minutes'] ?? '', 'Routing operation duration', 4, true);
        if ($sequence < 1 || $sequence > 100000 || isset($sequences[$sequence]) || isset($references[$operationKey])) {
            throw new InvalidArgumentException('Routing operations require unique positive sequence and operation values.');
        }
        $sequences[$sequence] = true;
        $references[$operationKey] = true;
        $children[] = ['operation_key' => $operationKey, 'sequence' => $sequence, 'duration_minutes' => $duration];
    }
    if ($children === []) {
        throw new InvalidArgumentException('A routing requires at least one operation.');
    }
    usort($children, static fn (array $left, array $right): int => [$left['sequence'], $left['operation_key']] <=> [$right['sequence'], $right['operation_key']]);
    $saved = yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'ROUTING',
        'SAVE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $code, $name, $status, $requestedKey, $children): array {
            $existing = $requestedKey !== ''
                ? $db->GetRow('SELECT * FROM project_company_manufacturing_routing WHERE company_key_hash=? AND routing_key=? FOR UPDATE', [$companyKeyHash, $requestedKey])
                : $db->GetRow('SELECT * FROM project_company_manufacturing_routing WHERE company_key_hash=? AND routing_code=? FOR UPDATE', [$companyKeyHash, $code]);
            if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
                throw new InvalidArgumentException('Routing is not owned by this company.');
            }
            $isUpdate = is_array($existing) && $existing !== [];
            $routingKey = $isUpdate ? (string) $existing['routing_key'] : bx_uuid();
            foreach ($children as $child) {
                $operation = yovel_admin_manufacturing_operation_row($db, $companyKeyHash, $child['operation_key']);
                if ((string) $operation['record_status'] !== 'ACTIVE') {
                    throw new InvalidArgumentException('Routing operations must be active company operations.');
                }
            }
            yovel_admin_manufacturing_execute(
                $db,
                "INSERT INTO project_company_manufacturing_routing (routing_key, company_key, company_key_hash, routing_code, routing_name, record_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE routing_code=VALUES(routing_code), routing_name=VALUES(routing_name), record_status=VALUES(record_status), updated_by_admin_key=VALUES(updated_by_admin_key)",
                [$routingKey, $companyKey, $companyKeyHash, $code, $name, $status, $adminKey, $adminKey],
                'Manufacturing routing save'
            );
            yovel_admin_manufacturing_execute($db, 'DELETE FROM project_company_manufacturing_routing_operation WHERE company_key_hash=? AND routing_key=?', [$companyKeyHash, $routingKey], 'Manufacturing routing operation replace');
            foreach ($children as $child) {
                yovel_admin_manufacturing_execute(
                    $db,
                    'INSERT INTO project_company_manufacturing_routing_operation (routing_operation_key, company_key, company_key_hash, routing_key, operation_key, sequence_no, duration_minutes, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [bx_uuid(), $companyKey, $companyKeyHash, $routingKey, $child['operation_key'], $child['sequence'], $child['duration_minutes'], $adminKey],
                    'Manufacturing routing operation save'
                );
            }
            return [
                'record_key' => $routingKey, 'business_key' => $code, 'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey, 'audit_action' => $isUpdate ? 'UPDATE' : 'CREATE',
                'persisted_fields' => [
                    'routing_key' => $routingKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                    'routing_code' => $code, 'routing_name' => $name, 'record_status' => $status,
                    'children_checksum' => yovel_admin_manufacturing_capacity_child_checksum($children),
                ],
            ];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_routing_row($db, (string) $expected['company_key_hash'], (string) $expected['record_key'])
    );
    return yovel_admin_manufacturing_routing_row(bx_db(), $companyKeyHash, (string) $saved['routing_key']) + ['_audit_action' => $saved['_audit_action']];
}

function yovel_admin_manufacturing_routings(array $company): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $keys = bx_db()->GetAll("SELECT routing_key FROM project_company_manufacturing_routing WHERE company_key_hash=? AND record_status<>'ARCHIVED' ORDER BY routing_code, x_id", [$companyKeyHash]);
    return array_map(static fn (array $row): array => yovel_admin_manufacturing_routing_row(bx_db(), $companyKeyHash, (string) $row['routing_key']), is_array($keys) ? $keys : []);
}

function yovel_admin_manufacturing_workstation_type_row(ADOConnection $db, string $companyKeyHash, string $typeKey): array
{
    $row = yovel_admin_manufacturing_capacity_owned_row($db, 'project_company_manufacturing_workstation_type', 'workstation_type_key', $companyKeyHash, $typeKey);
    $operations = $db->GetAll('SELECT operation_key FROM project_company_manufacturing_workstation_type_operation WHERE company_key_hash=? AND workstation_type_key=? ORDER BY x_id', [$companyKeyHash, $typeKey]);
    $hours = $db->GetAll('SELECT working_hour_key, weekday_no AS weekday, start_time, end_time FROM project_company_manufacturing_working_hour WHERE company_key_hash=? AND workstation_type_key=? ORDER BY weekday_no, start_time, end_time, x_id', [$companyKeyHash, $typeKey]);
    $components = $db->GetAll('SELECT component_key, component_name, hourly_cost, cost_account_key FROM project_company_manufacturing_workstation_type_component WHERE company_key_hash=? AND workstation_type_key=? ORDER BY component_name, x_id', [$companyKeyHash, $typeKey]);
    $row['operation_keys'] = array_map(static fn (array $item): string => (string) $item['operation_key'], is_array($operations) ? $operations : []);
    $row['working_hours'] = is_array($hours) ? $hours : [];
    $row['operating_components'] = array_map(static function (array $item): array {
        $item['cost_account_key'] = (string) ($item['cost_account_key'] ?? '');
        return $item;
    }, is_array($components) ? $components : []);
    $row['children_checksum'] = yovel_admin_manufacturing_capacity_child_checksum([
        'operation_keys' => $row['operation_keys'],
        'working_hours' => array_map(static fn (array $item): array => ['weekday' => (int) $item['weekday'], 'start_time' => (string) $item['start_time'], 'end_time' => (string) $item['end_time']], $row['working_hours']),
        'operating_components' => array_map(static fn (array $item): array => ['component_name' => (string) $item['component_name'], 'hourly_cost' => (string) $item['hourly_cost'], 'cost_account_key' => (string) $item['cost_account_key']], $row['operating_components']),
    ]);
    return $row;
}

function yovel_admin_save_manufacturing_workstation_type(array $company, array $admin, array $input, ?array $gateway = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $code = yovel_admin_manufacturing_capacity_code($input['workstation_type_code'] ?? '', 'Workstation type code');
    $name = yovel_admin_manufacturing_capacity_text($input['workstation_type_name'] ?? '', 'Workstation type name', 180);
    $status = yovel_admin_manufacturing_capacity_status($input['record_status'] ?? 'DRAFT');
    $requestedKey = yovel_admin_manufacturing_capacity_key($input['workstation_type_key'] ?? '', 'Workstation type key', false);
    $operationKeys = [];
    foreach (yovel_admin_manufacturing_capacity_rows($input, 'operation_keys') as $operationKey) {
        $operationKey = yovel_admin_manufacturing_capacity_key($operationKey, 'Workstation type operation');
        $operationKeys[$operationKey] = $operationKey;
    }
    $operationKeys = array_values($operationKeys);
    sort($operationKeys, SORT_STRING);
    $workingHours = [];
    foreach (yovel_admin_manufacturing_capacity_rows($input, 'working_hours') as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Workstation working hour is invalid.');
        }
        $weekday = (int) ($item['weekday'] ?? 0);
        $start = trim((string) ($item['start_time'] ?? ''));
        $end = trim((string) ($item['end_time'] ?? ''));
        if ($weekday < 1 || $weekday > 7 || preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $start) !== 1 || preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $end) !== 1) {
            throw new InvalidArgumentException('Workstation working hour requires a weekday and valid times.');
        }
        $start = strlen($start) === 5 ? $start . ':00' : $start;
        $end = strlen($end) === 5 ? $end . ':00' : $end;
        if ($start >= $end) {
            throw new InvalidArgumentException('Workstation working hour must end after it starts.');
        }
        foreach ($workingHours as $existing) {
            if ($existing['weekday'] === $weekday && $start < $existing['end_time'] && $end > $existing['start_time']) {
                throw new InvalidArgumentException('Workstation working hours cannot overlap.');
            }
        }
        $workingHours[] = ['weekday' => $weekday, 'start_time' => $start, 'end_time' => $end];
    }
    if ($workingHours === []) {
        throw new InvalidArgumentException('A workstation type requires working hours.');
    }
    usort($workingHours, static fn (array $left, array $right): int => [$left['weekday'], $left['start_time']] <=> [$right['weekday'], $right['start_time']]);
    $components = [];
    foreach (yovel_admin_manufacturing_capacity_rows($input, 'operating_components') as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Workstation type operating component is invalid.');
        }
        $componentName = yovel_admin_manufacturing_capacity_text($item['component_name'] ?? '', 'Operating component name', 180);
        $components[] = ['component_name' => $componentName, 'hourly_cost' => yovel_admin_manufacturing_capacity_decimal($item['hourly_cost'] ?? '0', 'Operating component hourly cost'), 'cost_account_key' => trim((string) ($item['cost_account_key'] ?? ''))];
    }
    usort($components, static fn (array $left, array $right): int => $left['component_name'] <=> $right['component_name']);
    $saved = yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'WORKSTATION_TYPE',
        'SAVE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $code, $name, $status, $requestedKey, $operationKeys, $workingHours, $components, $gateway): array {
            $existing = $requestedKey !== ''
                ? $db->GetRow('SELECT * FROM project_company_manufacturing_workstation_type WHERE company_key_hash=? AND workstation_type_key=? FOR UPDATE', [$companyKeyHash, $requestedKey])
                : $db->GetRow('SELECT * FROM project_company_manufacturing_workstation_type WHERE company_key_hash=? AND workstation_type_code=? FOR UPDATE', [$companyKeyHash, $code]);
            if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
                throw new InvalidArgumentException('Workstation type is not owned by this company.');
            }
            $isUpdate = is_array($existing) && $existing !== [];
            $typeKey = $isUpdate ? (string) $existing['workstation_type_key'] : bx_uuid();
            foreach ($operationKeys as $operationKey) {
                $operation = yovel_admin_manufacturing_operation_row($db, $companyKeyHash, $operationKey);
                if ((string) $operation['record_status'] !== 'ACTIVE') {
                    throw new InvalidArgumentException('Workstation types require active operations.');
                }
            }
            $resolvedComponents = [];
            foreach ($components as $component) {
                $component['cost_account_key'] = yovel_admin_manufacturing_capacity_account($db, ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash], $component['cost_account_key'], 'Operating component account', $gateway);
                $resolvedComponents[] = $component;
            }
            yovel_admin_manufacturing_execute(
                $db,
                "INSERT INTO project_company_manufacturing_workstation_type (workstation_type_key, company_key, company_key_hash, workstation_type_code, workstation_type_name, record_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE workstation_type_code=VALUES(workstation_type_code), workstation_type_name=VALUES(workstation_type_name), record_status=VALUES(record_status), updated_by_admin_key=VALUES(updated_by_admin_key)",
                [$typeKey, $companyKey, $companyKeyHash, $code, $name, $status, $adminKey, $adminKey],
                'Manufacturing workstation type save'
            );
            foreach (['project_company_manufacturing_workstation_type_operation', 'project_company_manufacturing_working_hour', 'project_company_manufacturing_workstation_type_component'] as $table) {
                yovel_admin_manufacturing_execute($db, "DELETE FROM `{$table}` WHERE company_key_hash=? AND workstation_type_key=?", [$companyKeyHash, $typeKey], 'Manufacturing workstation type child replace');
            }
            foreach ($operationKeys as $operationKey) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_workstation_type_operation (workstation_type_operation_key, company_key, company_key_hash, workstation_type_key, operation_key, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $typeKey, $operationKey, $adminKey], 'Manufacturing workstation type operation save');
            }
            foreach ($workingHours as $hour) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_working_hour (working_hour_key, company_key, company_key_hash, workstation_type_key, weekday_no, start_time, end_time, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $typeKey, $hour['weekday'], $hour['start_time'], $hour['end_time'], $adminKey], 'Manufacturing working hour save');
            }
            foreach ($resolvedComponents as $component) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_workstation_type_component (component_key, company_key, company_key_hash, workstation_type_key, component_name, hourly_cost, cost_account_key, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $typeKey, $component['component_name'], $component['hourly_cost'], $component['cost_account_key'] !== '' ? $component['cost_account_key'] : null, $adminKey], 'Manufacturing workstation type component save');
            }
            $children = ['operation_keys' => $operationKeys, 'working_hours' => $workingHours, 'operating_components' => $resolvedComponents];
            return [
                'record_key' => $typeKey, 'business_key' => $code, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                'admin_key' => $adminKey, 'audit_action' => $isUpdate ? 'UPDATE' : 'CREATE',
                'persisted_fields' => ['workstation_type_key' => $typeKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'workstation_type_code' => $code, 'workstation_type_name' => $name, 'record_status' => $status, 'children_checksum' => yovel_admin_manufacturing_capacity_child_checksum($children)],
            ];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_workstation_type_row($db, (string) $expected['company_key_hash'], (string) $expected['record_key'])
    );
    return yovel_admin_manufacturing_workstation_type_row(bx_db(), $companyKeyHash, (string) $saved['workstation_type_key']) + ['_audit_action' => $saved['_audit_action']];
}

function yovel_admin_manufacturing_workstation_types(array $company): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $keys = bx_db()->GetAll("SELECT workstation_type_key FROM project_company_manufacturing_workstation_type WHERE company_key_hash=? AND record_status<>'ARCHIVED' ORDER BY workstation_type_code, x_id", [$companyKeyHash]);
    return array_map(static fn (array $row): array => yovel_admin_manufacturing_workstation_type_row(bx_db(), $companyKeyHash, (string) $row['workstation_type_key']), is_array($keys) ? $keys : []);
}

function yovel_admin_manufacturing_plant_floor_row(ADOConnection $db, string $companyKeyHash, string $floorKey): array
{
    return yovel_admin_manufacturing_capacity_owned_row($db, 'project_company_manufacturing_plant_floor', 'plant_floor_key', $companyKeyHash, $floorKey);
}

function yovel_admin_save_manufacturing_plant_floor(array $company, array $admin, array $input): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $code = yovel_admin_manufacturing_capacity_code($input['plant_floor_code'] ?? '', 'Plant Floor code');
    $name = yovel_admin_manufacturing_capacity_text($input['plant_floor_name'] ?? '', 'Plant Floor name', 180);
    $status = yovel_admin_manufacturing_capacity_status($input['record_status'] ?? 'DRAFT');
    $requestedKey = yovel_admin_manufacturing_capacity_key($input['plant_floor_key'] ?? '', 'Plant Floor key', false);
    return yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'PLANT_FLOOR',
        'SAVE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $code, $name, $status, $requestedKey): array {
            $existing = $requestedKey !== ''
                ? $db->GetRow('SELECT * FROM project_company_manufacturing_plant_floor WHERE company_key_hash=? AND plant_floor_key=? FOR UPDATE', [$companyKeyHash, $requestedKey])
                : $db->GetRow('SELECT * FROM project_company_manufacturing_plant_floor WHERE company_key_hash=? AND plant_floor_code=? FOR UPDATE', [$companyKeyHash, $code]);
            if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
                throw new InvalidArgumentException('Plant Floor is not owned by this company.');
            }
            $isUpdate = is_array($existing) && $existing !== [];
            $key = $isUpdate ? (string) $existing['plant_floor_key'] : bx_uuid();
            yovel_admin_manufacturing_execute($db, "INSERT INTO project_company_manufacturing_plant_floor (plant_floor_key, company_key, company_key_hash, plant_floor_code, plant_floor_name, record_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE plant_floor_code=VALUES(plant_floor_code), plant_floor_name=VALUES(plant_floor_name), record_status=VALUES(record_status), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $companyKeyHash, $code, $name, $status, $adminKey, $adminKey], 'Manufacturing Plant Floor save');
            return ['record_key' => $key, 'business_key' => $code, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey, 'audit_action' => $isUpdate ? 'UPDATE' : 'CREATE', 'persisted_fields' => ['plant_floor_key' => $key, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'plant_floor_code' => $code, 'plant_floor_name' => $name, 'record_status' => $status]];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_plant_floor_row($db, (string) $expected['company_key_hash'], (string) $expected['record_key'])
    );
}

function yovel_admin_manufacturing_plant_floors(array $company): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $rows = bx_db()->GetAll("SELECT * FROM project_company_manufacturing_plant_floor WHERE company_key_hash=? AND record_status<>'ARCHIVED' ORDER BY plant_floor_code, x_id", [$companyKeyHash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_manufacturing_workstation_row(ADOConnection $db, string $companyKeyHash, string $workstationKey): array
{
    $row = yovel_admin_manufacturing_capacity_owned_row($db, 'project_company_manufacturing_workstation', 'workstation_key', $companyKeyHash, $workstationKey);
    $type = yovel_admin_manufacturing_workstation_type_row($db, $companyKeyHash, (string) $row['workstation_type_key']);
    $floor = yovel_admin_manufacturing_plant_floor_row($db, $companyKeyHash, (string) $row['plant_floor_key']);
    $costs = $db->GetAll('SELECT workstation_cost_key, cost_type, hourly_cost, cost_account_key FROM project_company_manufacturing_workstation_cost WHERE company_key_hash=? AND workstation_key=? ORDER BY cost_type, x_id', [$companyKeyHash, $workstationKey]);
    $components = $db->GetAll('SELECT component_key, component_name, hourly_cost, cost_account_key FROM project_company_manufacturing_workstation_component WHERE company_key_hash=? AND workstation_key=? ORDER BY component_name, x_id', [$companyKeyHash, $workstationKey]);
    $row['cost_account_key'] = (string) ($row['cost_account_key'] ?? '');
    $row['holiday_dates'] = json_decode((string) ($row['holiday_dates_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
    $row['workstation_type_code'] = (string) $type['workstation_type_code'];
    $row['workstation_type_name'] = (string) $type['workstation_type_name'];
    $row['plant_floor_code'] = (string) $floor['plant_floor_code'];
    $row['plant_floor_name'] = (string) $floor['plant_floor_name'];
    $row['working_hours'] = $type['working_hours'];
    $row['operation_keys'] = $type['operation_keys'];
    $row['costs'] = array_map(static function (array $item): array {
        $item['cost_account_key'] = (string) ($item['cost_account_key'] ?? '');
        return $item;
    }, is_array($costs) ? $costs : []);
    $row['operating_components'] = array_map(static function (array $item): array {
        $item['cost_account_key'] = (string) ($item['cost_account_key'] ?? '');
        return $item;
    }, is_array($components) ? $components : []);
    $row['children_checksum'] = yovel_admin_manufacturing_capacity_child_checksum([
        'holiday_dates' => $row['holiday_dates'],
        'costs' => array_map(static fn (array $item): array => ['cost_type' => (string) $item['cost_type'], 'hourly_cost' => (string) $item['hourly_cost'], 'cost_account_key' => (string) $item['cost_account_key']], $row['costs']),
        'operating_components' => array_map(static fn (array $item): array => ['component_name' => (string) $item['component_name'], 'hourly_cost' => (string) $item['hourly_cost'], 'cost_account_key' => (string) $item['cost_account_key']], $row['operating_components']),
    ]);
    return $row;
}

function yovel_admin_save_manufacturing_workstation(array $company, array $admin, array $input, ?array $gateway = null): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $code = yovel_admin_manufacturing_capacity_code($input['workstation_code'] ?? '', 'Workstation code');
    $name = yovel_admin_manufacturing_capacity_text($input['workstation_name'] ?? '', 'Workstation name', 180);
    $typeKey = yovel_admin_manufacturing_capacity_key($input['workstation_type_key'] ?? '', 'Workstation type');
    $floorKey = yovel_admin_manufacturing_capacity_key($input['plant_floor_key'] ?? '', 'Plant Floor');
    $capacity = (int) ($input['capacity_units'] ?? 0);
    if ($capacity < 1 || $capacity > 100000) {
        throw new InvalidArgumentException('Workstation capacity units must be between 1 and 100000.');
    }
    $rate = yovel_admin_manufacturing_capacity_decimal($input['hourly_rate'] ?? '0', 'Workstation hourly rate');
    $status = yovel_admin_manufacturing_capacity_status($input['record_status'] ?? 'DRAFT');
    $requestedKey = yovel_admin_manufacturing_capacity_key($input['workstation_key'] ?? '', 'Workstation key', false);
    $holidays = [];
    foreach (yovel_admin_manufacturing_capacity_rows($input, 'holiday_dates') as $date) {
        $date = trim((string) $date);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Workstation holiday date is invalid.');
        }
        $holidays[$date] = $date;
    }
    $holidays = array_values($holidays);
    sort($holidays, SORT_STRING);
    $costs = [];
    foreach (yovel_admin_manufacturing_capacity_rows($input, 'costs') as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Workstation cost row is invalid.');
        }
        $costType = yovel_admin_manufacturing_capacity_code($item['cost_type'] ?? '', 'Workstation cost type');
        if (isset($costs[$costType])) {
            throw new InvalidArgumentException('Workstation cost types must be unique.');
        }
        $costs[$costType] = ['cost_type' => $costType, 'hourly_cost' => yovel_admin_manufacturing_capacity_decimal($item['hourly_cost'] ?? '0', 'Workstation cost'), 'cost_account_key' => trim((string) ($item['cost_account_key'] ?? ''))];
    }
    $costs = array_values($costs);
    $components = [];
    foreach (yovel_admin_manufacturing_capacity_rows($input, 'operating_components') as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Workstation operating component row is invalid.');
        }
        $componentName = yovel_admin_manufacturing_capacity_text($item['component_name'] ?? '', 'Workstation operating component', 180);
        if (isset($components[$componentName])) {
            throw new InvalidArgumentException('Workstation operating component names must be unique.');
        }
        $components[$componentName] = ['component_name' => $componentName, 'hourly_cost' => yovel_admin_manufacturing_capacity_decimal($item['hourly_cost'] ?? '0', 'Workstation operating component cost'), 'cost_account_key' => trim((string) ($item['cost_account_key'] ?? ''))];
    }
    $components = array_values($components);
    $saved = yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'WORKSTATION',
        'SAVE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $code, $name, $typeKey, $floorKey, $capacity, $rate, $status, $requestedKey, $holidays, $costs, $components, $gateway, $input): array {
            $type = yovel_admin_manufacturing_workstation_type_row($db, $companyKeyHash, $typeKey);
            $floor = yovel_admin_manufacturing_plant_floor_row($db, $companyKeyHash, $floorKey);
            if ((string) $type['record_status'] !== 'ACTIVE' || (string) $floor['record_status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('Workstation type and Plant Floor must be active.');
            }
            $existing = $requestedKey !== ''
                ? $db->GetRow('SELECT * FROM project_company_manufacturing_workstation WHERE company_key_hash=? AND workstation_key=? FOR UPDATE', [$companyKeyHash, $requestedKey])
                : $db->GetRow('SELECT * FROM project_company_manufacturing_workstation WHERE company_key_hash=? AND workstation_code=? FOR UPDATE', [$companyKeyHash, $code]);
            if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
                throw new InvalidArgumentException('Workstation is not owned by this company.');
            }
            $isUpdate = is_array($existing) && $existing !== [];
            $key = $isUpdate ? (string) $existing['workstation_key'] : bx_uuid();
            $account = yovel_admin_manufacturing_capacity_account($db, ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash], $input['cost_account_key'] ?? '', 'Workstation cost account', $gateway);
            $resolvedCosts = [];
            foreach ($costs as $cost) {
                $cost['cost_account_key'] = yovel_admin_manufacturing_capacity_account($db, ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash], $cost['cost_account_key'], 'Workstation cost account', $gateway);
                $resolvedCosts[] = $cost;
            }
            $resolvedComponents = [];
            foreach ($components as $component) {
                $component['cost_account_key'] = yovel_admin_manufacturing_capacity_account($db, ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash], $component['cost_account_key'], 'Workstation operating component account', $gateway);
                $resolvedComponents[] = $component;
            }
            $holidayJson = yovel_admin_manufacturing_json($holidays);
            yovel_admin_manufacturing_execute($db, "INSERT INTO project_company_manufacturing_workstation (workstation_key, company_key, company_key_hash, workstation_code, workstation_name, workstation_type_key, plant_floor_key, capacity_units, hourly_rate, cost_account_key, holiday_dates_json, record_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE workstation_code=VALUES(workstation_code), workstation_name=VALUES(workstation_name), workstation_type_key=VALUES(workstation_type_key), plant_floor_key=VALUES(plant_floor_key), capacity_units=VALUES(capacity_units), hourly_rate=VALUES(hourly_rate), cost_account_key=VALUES(cost_account_key), holiday_dates_json=VALUES(holiday_dates_json), record_status=VALUES(record_status), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $companyKeyHash, $code, $name, $typeKey, $floorKey, $capacity, $rate, $account !== '' ? $account : null, $holidayJson, $status, $adminKey, $adminKey], 'Manufacturing workstation save');
            foreach (['project_company_manufacturing_workstation_cost', 'project_company_manufacturing_workstation_component'] as $table) {
                yovel_admin_manufacturing_execute($db, "DELETE FROM `{$table}` WHERE company_key_hash=? AND workstation_key=?", [$companyKeyHash, $key], 'Manufacturing workstation child replace');
            }
            foreach ($resolvedCosts as $cost) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_workstation_cost (workstation_cost_key, company_key, company_key_hash, workstation_key, cost_type, hourly_cost, cost_account_key, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $key, $cost['cost_type'], $cost['hourly_cost'], $cost['cost_account_key'] !== '' ? $cost['cost_account_key'] : null, $adminKey], 'Manufacturing workstation cost save');
            }
            foreach ($resolvedComponents as $component) {
                yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_workstation_component (component_key, company_key, company_key_hash, workstation_key, component_name, hourly_cost, cost_account_key, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $key, $component['component_name'], $component['hourly_cost'], $component['cost_account_key'] !== '' ? $component['cost_account_key'] : null, $adminKey], 'Manufacturing workstation component save');
            }
            $children = ['holiday_dates' => $holidays, 'costs' => $resolvedCosts, 'operating_components' => $resolvedComponents];
            return [
                'record_key' => $key, 'business_key' => $code, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                'admin_key' => $adminKey, 'audit_action' => $isUpdate ? 'UPDATE' : 'CREATE',
                'persisted_fields' => ['workstation_key' => $key, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'workstation_code' => $code, 'workstation_name' => $name, 'workstation_type_key' => $typeKey, 'plant_floor_key' => $floorKey, 'capacity_units' => $capacity, 'hourly_rate' => $rate, 'cost_account_key' => $account, 'holiday_dates_json' => $holidayJson, 'record_status' => $status, 'children_checksum' => yovel_admin_manufacturing_capacity_child_checksum($children)],
            ];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_workstation_row($db, (string) $expected['company_key_hash'], (string) $expected['record_key'])
    );
    return yovel_admin_manufacturing_workstation_row(bx_db(), $companyKeyHash, (string) $saved['workstation_key']) + ['_audit_action' => $saved['_audit_action']];
}

function yovel_admin_manufacturing_workstations(array $company): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $keys = bx_db()->GetAll("SELECT workstation_key FROM project_company_manufacturing_workstation WHERE company_key_hash=? AND record_status<>'ARCHIVED' ORDER BY workstation_code, x_id", [$companyKeyHash]);
    return array_map(static fn (array $row): array => yovel_admin_manufacturing_workstation_row(bx_db(), $companyKeyHash, (string) $row['workstation_key']), is_array($keys) ? $keys : []);
}

function yovel_admin_manufacturing_downtime_row(ADOConnection $db, string $companyKeyHash, string $downtimeKey): array
{
    $row = yovel_admin_manufacturing_capacity_owned_row($db, 'project_company_manufacturing_downtime', 'downtime_key', $companyKeyHash, $downtimeKey);
    $station = yovel_admin_manufacturing_workstation_row($db, $companyKeyHash, (string) $row['workstation_key']);
    $row['workstation_code'] = (string) $station['workstation_code'];
    $row['workstation_name'] = (string) $station['workstation_name'];
    return $row;
}

function yovel_admin_save_manufacturing_downtime(array $company, array $admin, array $input): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $stationKey = yovel_admin_manufacturing_capacity_key($input['workstation_key'] ?? '', 'Downtime workstation');
    $startsText = trim((string) ($input['starts_at'] ?? ''));
    $endsText = trim((string) ($input['ends_at'] ?? ''));
    $starts = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $startsText) ?: DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $startsText);
    $ends = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $endsText) ?: DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $endsText);
    if (!$starts || !$ends || $ends <= $starts) {
        throw new InvalidArgumentException('Downtime interval is invalid.');
    }
    $startsText = $starts->format('Y-m-d H:i:s');
    $endsText = $ends->format('Y-m-d H:i:s');
    $duration = intdiv($ends->getTimestamp() - $starts->getTimestamp(), 60);
    if ($duration < 1 || $duration > 5256000) {
        throw new InvalidArgumentException('Downtime duration is invalid.');
    }
    $reason = yovel_admin_manufacturing_capacity_text($input['reason'] ?? '', 'Downtime reason', 500);
    $status = yovel_admin_manufacturing_capacity_status($input['record_status'] ?? 'DRAFT');
    $requestedKey = yovel_admin_manufacturing_capacity_key($input['downtime_key'] ?? '', 'Downtime key', false);
    return yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'DOWNTIME_ENTRY',
        'SAVE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $stationKey, $startsText, $endsText, $duration, $reason, $status, $requestedKey): array {
            $station = yovel_admin_manufacturing_workstation_row($db, $companyKeyHash, $stationKey);
            if ((string) $station['record_status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('Downtime requires an active workstation.');
            }
            $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_manufacturing_downtime WHERE company_key_hash=? AND downtime_key=? FOR UPDATE', [$companyKeyHash, $requestedKey]) : [];
            if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
                throw new InvalidArgumentException('Downtime Entry is not owned by this company.');
            }
            $overlap = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_manufacturing_downtime WHERE company_key_hash=? AND workstation_key=? AND record_status='ACTIVE' AND starts_at < ? AND ends_at > ? AND downtime_key <> ?", [$companyKeyHash, $stationKey, $endsText, $startsText, $requestedKey]);
            if ($status === 'ACTIVE' && $overlap > 0) {
                throw new InvalidArgumentException('Downtime interval overlaps an active entry.');
            }
            $isUpdate = is_array($existing) && $existing !== [];
            $key = $isUpdate ? (string) $existing['downtime_key'] : bx_uuid();
            yovel_admin_manufacturing_execute($db, "INSERT INTO project_company_manufacturing_downtime (downtime_key, company_key, company_key_hash, workstation_key, starts_at, ends_at, duration_minutes, reason, record_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE workstation_key=VALUES(workstation_key), starts_at=VALUES(starts_at), ends_at=VALUES(ends_at), duration_minutes=VALUES(duration_minutes), reason=VALUES(reason), record_status=VALUES(record_status), updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $companyKeyHash, $stationKey, $startsText, $endsText, $duration, $reason, $status, $adminKey, $adminKey], 'Manufacturing downtime save');
            return ['record_key' => $key, 'business_key' => $station['workstation_code'] . ':' . $startsText, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey, 'audit_action' => $isUpdate ? 'UPDATE' : 'CREATE', 'persisted_fields' => ['downtime_key' => $key, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'workstation_key' => $stationKey, 'starts_at' => $startsText, 'ends_at' => $endsText, 'duration_minutes' => $duration, 'reason' => $reason, 'record_status' => $status]];
        },
        static fn (ADOConnection $db, array $expected): array => yovel_admin_manufacturing_downtime_row($db, (string) $expected['company_key_hash'], (string) $expected['record_key'])
    );
}

function yovel_admin_manufacturing_downtimes(array $company, array $filters = []): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $sql = "SELECT downtime_key FROM project_company_manufacturing_downtime WHERE company_key_hash=? AND record_status<>'ARCHIVED'";
    $params = [$companyKeyHash];
    if (trim((string) ($filters['workstation_key'] ?? '')) !== '') {
        $sql .= ' AND workstation_key=?';
        $params[] = yovel_admin_manufacturing_capacity_key($filters['workstation_key'], 'Downtime workstation');
    }
    $keys = bx_db()->GetAll($sql . ' ORDER BY starts_at DESC, x_id DESC', $params);
    return array_map(static fn (array $row): array => yovel_admin_manufacturing_downtime_row(bx_db(), $companyKeyHash, (string) $row['downtime_key']), is_array($keys) ? $keys : []);
}

function yovel_admin_manufacturing_set_status(array $company, array $admin, string $recordType, string $recordKey, string $status): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $recordType = strtoupper(trim($recordType));
    $status = yovel_admin_manufacturing_capacity_status($status);
    $recordKey = yovel_admin_manufacturing_capacity_key($recordKey, 'Manufacturing record key');
    $map = [
        'OPERATION' => ['project_company_manufacturing_operation', 'operation_key', 'operation_code'],
        'ROUTING' => ['project_company_manufacturing_routing', 'routing_key', 'routing_code'],
        'WORKSTATION_TYPE' => ['project_company_manufacturing_workstation_type', 'workstation_type_key', 'workstation_type_code'],
        'WORKSTATION' => ['project_company_manufacturing_workstation', 'workstation_key', 'workstation_code'],
        'PLANT_FLOOR' => ['project_company_manufacturing_plant_floor', 'plant_floor_key', 'plant_floor_code'],
        'DOWNTIME_ENTRY' => ['project_company_manufacturing_downtime', 'downtime_key', 'downtime_key'],
    ];
    if (!isset($map[$recordType])) {
        throw new InvalidArgumentException('Manufacturing lifecycle record type is invalid.');
    }
    [$table, $keyColumn, $businessColumn] = $map[$recordType];
    return yovel_admin_manufacturing_with_transaction(
        $companyKey,
        $recordType,
        'STATUS_CHANGE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $recordType, $recordKey, $status, $table, $keyColumn, $businessColumn): array {
            $row = yovel_admin_manufacturing_capacity_owned_row($db, $table, $keyColumn, $companyKeyHash, $recordKey);
            if (in_array($status, ['INACTIVE', 'ARCHIVED'], true)) {
                if ($recordType === 'OPERATION') {
                    $references = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_manufacturing_routing_operation ro INNER JOIN project_company_manufacturing_routing r ON r.company_key_hash=ro.company_key_hash AND r.routing_key=ro.routing_key WHERE ro.company_key_hash=? AND ro.operation_key=? AND r.record_status='ACTIVE'", [$companyKeyHash, $recordKey]);
                    if ($references > 0) {
                        throw new InvalidArgumentException('Operation is referenced by an active routing and cannot be archived.');
                    }
                } elseif ($recordType === 'WORKSTATION_TYPE') {
                    $references = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_manufacturing_workstation WHERE company_key_hash=? AND workstation_type_key=? AND record_status='ACTIVE'", [$companyKeyHash, $recordKey]);
                    if ($references > 0) {
                        throw new InvalidArgumentException('Workstation Type is referenced by an active workstation.');
                    }
                } elseif ($recordType === 'PLANT_FLOOR') {
                    $references = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_manufacturing_workstation WHERE company_key_hash=? AND plant_floor_key=? AND record_status='ACTIVE'", [$companyKeyHash, $recordKey]);
                    if ($references > 0) {
                        throw new InvalidArgumentException('Plant Floor is referenced by an active workstation.');
                    }
                }
            }
            yovel_admin_manufacturing_execute($db, "UPDATE `{$table}` SET record_status=?, updated_by_admin_key=? WHERE company_key_hash=? AND `{$keyColumn}`=?", [$status, $adminKey, $companyKeyHash, $recordKey], 'Manufacturing lifecycle update');
            return ['record_key' => $recordKey, 'business_key' => (string) $row[$businessColumn], 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey, 'persisted_fields' => [$keyColumn => $recordKey, 'record_status' => $status]];
        },
        static function (ADOConnection $db, array $expected) use ($table, $keyColumn): array {
            $row = $db->GetRow("SELECT `{$keyColumn}`, record_status FROM `{$table}` WHERE company_key_hash=? AND `{$keyColumn}`=? LIMIT 1", [(string) $expected['company_key_hash'], (string) $expected['record_key']]);
            return is_array($row) ? $row : [];
        }
    );
}

function yovel_admin_manufacturing_capacity_company(string $companyKey): array
{
    $companyKey = trim($companyKey);
    if ($companyKey === '' || strlen($companyKey) > 1500) {
        throw new InvalidArgumentException('Manufacturing company key is invalid.');
    }
    $row = bx_db()->GetRow("SELECT company_key, company_key_hash FROM project_company WHERE company_key=? AND company_status='ACTIVE' LIMIT 1", [$companyKey]);
    if (!is_array($row) || $row === [] || preg_match('/^[a-f0-9]{64}$/', strtolower((string) ($row['company_key_hash'] ?? ''))) !== 1) {
        throw new RuntimeException('An active company is required for Manufacturing capacity.');
    }
    return ['company_key' => (string) $row['company_key'], 'company_key_hash' => strtolower((string) $row['company_key_hash'])];
}

function yovel_admin_manufacturing_capacity_minute_available(array $station, DateTimeImmutable $minute, array $downtimes, array $bookedSegments): bool
{
    if (in_array($minute->format('Y-m-d'), $station['holiday_dates'], true)) {
        return false;
    }
    $time = $minute->format('H:i:s');
    $weekday = (int) $minute->format('N');
    $withinHours = false;
    foreach ($station['working_hours'] as $hours) {
        if ((int) $hours['weekday'] === $weekday && $time >= (string) $hours['start_time'] && $time < (string) $hours['end_time']) {
            $withinHours = true;
            break;
        }
    }
    if (!$withinHours) {
        return false;
    }
    $timestamp = $minute->getTimestamp();
    foreach (array_merge($downtimes, $bookedSegments) as $interval) {
        if ($timestamp < $interval['end'] && $timestamp + 60 > $interval['start']) {
            return false;
        }
    }
    return true;
}

function yovel_admin_manufacturing_capacity_allocate(array $station, DateTimeImmutable $start, int $durationMinutes, array $downtimes, array $bookedSegments): array
{
    $cursor = $start->setTime((int) $start->format('H'), (int) $start->format('i'), 0);
    if ((int) $start->format('s') > 0) {
        $cursor = $cursor->modify('+1 minute');
    }
    $remaining = $durationMinutes;
    $segments = [];
    $iterations = 0;
    while ($remaining > 0) {
        if (++$iterations > 1051200) {
            throw new RuntimeException('Manufacturing capacity could not be allocated within two years.');
        }
        if (yovel_admin_manufacturing_capacity_minute_available($station, $cursor, $downtimes, $bookedSegments)) {
            $next = $cursor->modify('+1 minute');
            $last = count($segments) - 1;
            if ($last >= 0 && $segments[$last]['end'] === $cursor->getTimestamp()) {
                $segments[$last]['end'] = $next->getTimestamp();
            } else {
                $segments[] = ['start' => $cursor->getTimestamp(), 'end' => $next->getTimestamp()];
            }
            $remaining--;
        }
        $cursor = $cursor->modify('+1 minute');
    }
    return $segments;
}

function yovel_admin_manufacturing_schedule_capacity(string $companyKey, array $operations, DateTimeImmutable $start): array
{
    if ($operations === [] || count($operations) > 500) {
        throw new InvalidArgumentException('Manufacturing capacity operations are invalid.');
    }
    $company = yovel_admin_manufacturing_capacity_company($companyKey);
    yovel_admin_manufacturing_schema();
    $companyKeyHash = $company['company_key_hash'];
    $stationKeys = bx_db()->GetAll("SELECT workstation_key FROM project_company_manufacturing_workstation WHERE company_key_hash=? AND record_status='ACTIVE' ORDER BY workstation_code, workstation_key", [$companyKeyHash]);
    $stations = [];
    foreach (is_array($stationKeys) ? $stationKeys : [] as $item) {
        $station = yovel_admin_manufacturing_workstation_row(bx_db(), $companyKeyHash, (string) $item['workstation_key']);
        $stations[(string) $station['workstation_key']] = $station;
    }
    $downtimeRows = bx_db()->GetAll("SELECT workstation_key, starts_at, ends_at FROM project_company_manufacturing_downtime WHERE company_key_hash=? AND record_status='ACTIVE' ORDER BY starts_at, x_id", [$companyKeyHash]);
    $downtimes = [];
    foreach (is_array($downtimeRows) ? $downtimeRows : [] as $item) {
        $downtimes[(string) $item['workstation_key']][] = ['start' => strtotime((string) $item['starts_at']), 'end' => strtotime((string) $item['ends_at'])];
    }
    $booked = [];
    $result = [];
    $seenRefs = [];
    foreach ($operations as $operation) {
        if (!is_array($operation)) {
            throw new InvalidArgumentException('Manufacturing capacity operation row is invalid.');
        }
        $reference = yovel_admin_manufacturing_capacity_text($operation['operation_ref'] ?? '', 'Capacity operation reference', 180);
        if (isset($seenRefs[$reference])) {
            throw new InvalidArgumentException('Capacity operation references must be unique.');
        }
        $seenRefs[$reference] = true;
        $operationKey = yovel_admin_manufacturing_capacity_key($operation['operation_key'] ?? '', 'Capacity operation');
        $ownedOperation = yovel_admin_manufacturing_operation_row(bx_db(), $companyKeyHash, $operationKey);
        if ((string) $ownedOperation['record_status'] !== 'ACTIVE') {
            throw new InvalidArgumentException('Capacity scheduling requires active company operations.');
        }
        $duration = (int) ($operation['duration_minutes'] ?? 0);
        if ($duration < 1 || $duration > 525600) {
            throw new InvalidArgumentException('Capacity operation duration is invalid.');
        }
        $requestedStation = yovel_admin_manufacturing_capacity_key($operation['workstation_key'] ?? '', 'Capacity workstation', false);
        $requestedType = yovel_admin_manufacturing_capacity_key($operation['workstation_type_key'] ?? '', 'Capacity workstation type', false);
        $candidates = [];
        foreach ($stations as $stationKey => $station) {
            if ($requestedStation !== '' && $stationKey !== $requestedStation) {
                continue;
            }
            if ($requestedType !== '' && (string) $station['workstation_type_key'] !== $requestedType) {
                continue;
            }
            if (!in_array($operationKey, $station['operation_keys'], true)) {
                continue;
            }
            $segments = yovel_admin_manufacturing_capacity_allocate($station, $start, $duration, $downtimes[$stationKey] ?? [], $booked[$stationKey] ?? []);
            $candidates[] = ['station' => $station, 'segments' => $segments, 'end' => $segments[count($segments) - 1]['end']];
        }
        if ($candidates === []) {
            throw new RuntimeException('No eligible Manufacturing workstation has capacity for ' . $reference . '.');
        }
        usort($candidates, static fn (array $left, array $right): int => [$left['end'], $left['station']['workstation_code']] <=> [$right['end'], $right['station']['workstation_code']]);
        $selected = $candidates[0];
        $stationKey = (string) $selected['station']['workstation_key'];
        $booked[$stationKey] = array_merge($booked[$stationKey] ?? [], $selected['segments']);
        $segments = array_map(static fn (array $segment): array => ['starts_at' => date('Y-m-d H:i:s', $segment['start']), 'ends_at' => date('Y-m-d H:i:s', $segment['end'])], $selected['segments']);
        $result[] = [
            'operation_ref' => $reference, 'operation_key' => $operationKey,
            'workstation_key' => $stationKey, 'workstation_code' => (string) $selected['station']['workstation_code'],
            'starts_at' => $segments[0]['starts_at'], 'ends_at' => $segments[count($segments) - 1]['ends_at'],
            'duration_minutes' => $duration, 'segments' => $segments,
        ];
    }
    return $result;
}

function yovel_admin_manufacturing_plant_floor(array $company, ?DateTimeImmutable $at = null): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $at ??= new DateTimeImmutable('now');
    $atText = $at->format('Y-m-d H:i:s');
    $keys = bx_db()->GetAll("SELECT workstation_key FROM project_company_manufacturing_workstation WHERE company_key_hash=? AND record_status='ACTIVE' ORDER BY plant_floor_key, workstation_code", [$companyKeyHash]);
    $result = [];
    foreach (is_array($keys) ? $keys : [] as $item) {
        $station = yovel_admin_manufacturing_workstation_row(bx_db(), $companyKeyHash, (string) $item['workstation_key']);
        $down = bx_db()->GetRow("SELECT downtime_key, reason, starts_at, ends_at FROM project_company_manufacturing_downtime WHERE company_key_hash=? AND workstation_key=? AND record_status='ACTIVE' AND starts_at<=? AND ends_at>? ORDER BY starts_at, x_id LIMIT 1", [$companyKeyHash, $station['workstation_key'], $atText, $atText]);
        $result[] = [
            'plant_floor_key' => (string) $station['plant_floor_key'], 'plant_floor_code' => (string) $station['plant_floor_code'], 'plant_floor_name' => (string) $station['plant_floor_name'],
            'workstation_key' => (string) $station['workstation_key'], 'workstation_code' => (string) $station['workstation_code'], 'workstation_name' => (string) $station['workstation_name'],
            'state' => is_array($down) && $down !== [] ? 'DOWN' : 'AVAILABLE', 'reason' => is_array($down) ? (string) ($down['reason'] ?? '') : '',
            'state_source' => is_array($down) && $down !== [] ? 'PERSISTED_DOWNTIME' : 'PERSISTED_WORKSTATION', 'as_at' => $atText,
        ];
    }
    return $result;
}
