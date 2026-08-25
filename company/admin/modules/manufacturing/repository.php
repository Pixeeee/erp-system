<?php
declare(strict_types=1);

function yovel_admin_manufacturing_execute(ADOConnection $db, string $sql, array $parameters, string $operation): void
{
    $result = $db->Execute($sql, $parameters);
    if ($result === false) {
        $databaseError = trim((string) $db->ErrorMsg());
        error_log($operation . ' failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
        throw new RuntimeException($operation . ' could not be completed.');
    }
}

function yovel_admin_manufacturing_company_identity(array $company): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if ($companyKey === '' || strlen($companyKey) > 1500 || preg_match('/^[a-f0-9]{64}$/', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('Manufacturing company scope is invalid.');
    }
    return [$companyKey, $companyKeyHash];
}

function yovel_admin_manufacturing_scope(array $company, array $admin): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_manufacturing_company_identity($company);
    $adminKey = strtolower(trim((string) ($admin['admin_key'] ?? '')));
    if (!yovel_admin_is_uuid($adminKey)) {
        throw new InvalidArgumentException('Manufacturing administrator scope is invalid.');
    }
    $authorized = bx_db()->GetRow(
        "SELECT c.company_key, c.company_key_hash, a.admin_key
         FROM project_company c
         INNER JOIN project_company_admin a
           ON a.company_key = c.company_key
          AND a.company_key_hash = c.company_key_hash
          AND a.admin_key = ?
          AND a.admin_status = 'ACTIVE'
         WHERE c.company_key = ?
           AND c.company_key_hash = ?
           AND c.company_status = 'ACTIVE'
         LIMIT 1",
        [$adminKey, $companyKey, $companyKeyHash]
    );
    if (!is_array($authorized)
        || (string) ($authorized['company_key'] ?? '') !== $companyKey
        || strtolower((string) ($authorized['company_key_hash'] ?? '')) !== $companyKeyHash
        || strtolower((string) ($authorized['admin_key'] ?? '')) !== $adminKey) {
        throw new RuntimeException('An authorized active company administrator is required for Manufacturing.');
    }
    return [$companyKey, $companyKeyHash, $adminKey];
}

function yovel_admin_manufacturing_read_scope(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_manufacturing_company_identity($company);
    $active = bx_db()->GetRow(
        "SELECT company_key, company_key_hash FROM project_company
         WHERE company_key = ? AND company_key_hash = ? AND company_status = 'ACTIVE' LIMIT 1",
        [$companyKey, $companyKeyHash]
    );
    if (!is_array($active)
        || (string) ($active['company_key'] ?? '') !== $companyKey
        || strtolower((string) ($active['company_key_hash'] ?? '')) !== $companyKeyHash) {
        throw new RuntimeException('An active company is required for Manufacturing.');
    }
    return [$companyKey, $companyKeyHash];
}

function yovel_admin_manufacturing_assert_csrf(array $input): void
{
    $token = (string) ($input['csrf'] ?? '');
    if ($token === '' || !hash_equals(bx_csrf_token(), $token)) {
        throw new InvalidArgumentException('Manufacturing request token is invalid.');
    }
}

function yovel_admin_manufacturing_fault(string $point): void
{
    $fault = $GLOBALS['yovel_admin_manufacturing_fault'] ?? null;
    if (is_callable($fault)) {
        $fault($point);
    }
}

function yovel_admin_manufacturing_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function yovel_admin_manufacturing_rehydration_input(array $input, int $depth = 0): array
{
    if ($depth > 3) {
        return [];
    }
    $safe = [];
    foreach ($input as $key => $value) {
        $key = trim((string) $key);
        if ($key === '' || preg_match('/(?:csrf|password|secret|token)/i', $key) === 1) {
            continue;
        }
        if (is_array($value)) {
            $safe[$key] = yovel_admin_manufacturing_rehydration_input($value, $depth + 1);
        } elseif (is_scalar($value) || $value === null) {
            $safe[$key] = substr((string) $value, 0, 20000);
        }
    }
    return $safe;
}

function yovel_admin_manufacturing_with_transaction(
    string $companyKey,
    string $recordType,
    string $auditAction,
    callable $mutation,
    callable $readBack
): array {
    $companyKey = trim($companyKey);
    $recordType = strtoupper(trim($recordType));
    $auditAction = strtoupper(trim($auditAction));
    if ($companyKey === '' || strlen($companyKey) > 1500
        || preg_match('/^[A-Z][A-Z0-9_]{1,79}$/', $recordType) !== 1
        || preg_match('/^[A-Z][A-Z0-9_]{1,39}$/', $auditAction) !== 1) {
        throw new InvalidArgumentException('Manufacturing transaction metadata is invalid.');
    }
    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Manufacturing transaction could not start.');
    }
    try {
        $expected = $mutation($db);
        if (!is_array($expected)) {
            throw new RuntimeException('Manufacturing mutation returned an invalid persistence contract.');
        }
        foreach (['record_key', 'business_key', 'company_key', 'company_key_hash', 'admin_key', 'persisted_fields'] as $field) {
            if (!array_key_exists($field, $expected)) {
                throw new RuntimeException('Manufacturing mutation contract is missing ' . $field . '.');
            }
        }
        if ((string) $expected['company_key'] !== $companyKey
            || preg_match('/^[a-f0-9]{64}$/', (string) $expected['company_key_hash']) !== 1
            || !yovel_admin_is_uuid((string) $expected['admin_key'])
            || !is_array($expected['persisted_fields'])) {
            throw new RuntimeException('Manufacturing mutation scope verification failed.');
        }

        $effectiveAction = strtoupper(trim((string) ($expected['audit_action'] ?? $auditAction)));
        if (preg_match('/^[A-Z][A-Z0-9_]{1,39}$/', $effectiveAction) !== 1) {
            throw new RuntimeException('Manufacturing audit action is invalid.');
        }
        yovel_admin_manufacturing_execute(
            $db,
            'INSERT INTO project_company_manufacturing_audit (audit_key, company_key, company_key_hash, record_type, record_key, business_key, audit_action, persisted_values_json, admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                bx_uuid(), $companyKey, (string) $expected['company_key_hash'], $recordType,
                (string) $expected['record_key'], (string) $expected['business_key'], $effectiveAction,
                yovel_admin_manufacturing_json($expected['persisted_fields']), (string) $expected['admin_key'],
            ],
            'Manufacturing audit save'
        );
        yovel_admin_manufacturing_fault('before_readback');
        $actual = $readBack($db, $expected);
        if (!is_array($actual) || $actual === []) {
            throw new RuntimeException('Manufacturing exact read-back verification failed.');
        }
        foreach ($expected['persisted_fields'] as $field => $value) {
            if (!array_key_exists($field, $actual) || (string) $actual[$field] !== (string) $value) {
                throw new RuntimeException('Manufacturing exact read-back verification failed for ' . $field . '.');
            }
        }
        yovel_admin_manufacturing_fault('before_commit');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Manufacturing transaction could not commit.');
        }
        return $actual + ['_audit_action' => $effectiveAction];
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_manufacturing_settings(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $row = bx_db()->GetRow('SELECT * FROM project_company_manufacturing_setting WHERE company_key_hash = ? LIMIT 1', [$companyKeyHash]);
    if (is_array($row) && $row !== []) {
        return $row;
    }
    return [
        'setting_key' => '', 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
        'allow_overproduction_percent' => '0.0000', 'capacity_planning_enabled' => 1,
        'default_wip_warehouse_key' => '', 'default_finished_goods_warehouse_key' => '',
        'notes' => '', 'setting_status' => 'ACTIVE',
    ];
}

function yovel_admin_save_manufacturing_settings(array $company, array $admin, array $input): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $overproduction = trim((string) ($input['allow_overproduction_percent'] ?? '0'));
    if (preg_match('/^\d{1,3}(?:\.\d{1,4})?$/', $overproduction) !== 1) {
        throw new InvalidArgumentException('Manufacturing overproduction percentage is invalid.');
    }
    $overproduction = number_format((float) $overproduction, 4, '.', '');
    if (bccomp($overproduction, '100.0000', 4) === 1) {
        throw new InvalidArgumentException('Manufacturing overproduction percentage cannot exceed 100.');
    }
    $capacity = in_array($input['capacity_planning_enabled'] ?? null, [1, '1', true, 'true', 'on', 'yes'], true) ? 1 : 0;
    $wipWarehouse = trim((string) ($input['default_wip_warehouse_key'] ?? ''));
    $finishedWarehouse = trim((string) ($input['default_finished_goods_warehouse_key'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));
    if (strlen($wipWarehouse) > 1500 || strlen($finishedWarehouse) > 1500 || strlen($notes) > 2000) {
        throw new InvalidArgumentException('Manufacturing settings contain an overlong value.');
    }
    if ($wipWarehouse !== '' || $finishedWarehouse !== '') {
        $warehouses = yovel_admin_manufacturing_dependency_call(yovel_admin_manufacturing_dependency_gateway(), 'warehouses', [$company, ['leaf_only' => true, 'status' => 'ACTIVE']]);
        $ownedKeys = array_fill_keys(array_map(static fn (array $row): string => (string) ($row['warehouse_key'] ?? ''), $warehouses), true);
        foreach ([$wipWarehouse, $finishedWarehouse] as $warehouseKey) {
            if ($warehouseKey !== '' && !isset($ownedKeys[$warehouseKey])) {
                throw new InvalidArgumentException('Manufacturing default warehouse must be an active company Inventory location.');
            }
        }
    }

    return yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'MANUFACTURING_SETTINGS',
        'SAVE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $overproduction, $capacity, $wipWarehouse, $finishedWarehouse, $notes): array {
            $existing = $db->GetRow('SELECT * FROM project_company_manufacturing_setting WHERE company_key_hash = ? FOR UPDATE', [$companyKeyHash]);
            $isUpdate = is_array($existing) && $existing !== [];
            $settingKey = $isUpdate ? (string) $existing['setting_key'] : bx_uuid();
            yovel_admin_manufacturing_execute(
                $db,
                "INSERT INTO project_company_manufacturing_setting (setting_key, company_key, company_key_hash, allow_overproduction_percent, capacity_planning_enabled, default_wip_warehouse_key, default_finished_goods_warehouse_key, notes, setting_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?) ON DUPLICATE KEY UPDATE allow_overproduction_percent=VALUES(allow_overproduction_percent), capacity_planning_enabled=VALUES(capacity_planning_enabled), default_wip_warehouse_key=VALUES(default_wip_warehouse_key), default_finished_goods_warehouse_key=VALUES(default_finished_goods_warehouse_key), notes=VALUES(notes), setting_status='ACTIVE', updated_by_admin_key=VALUES(updated_by_admin_key)",
                [$settingKey, $companyKey, $companyKeyHash, $overproduction, $capacity, $wipWarehouse !== '' ? $wipWarehouse : null, $finishedWarehouse !== '' ? $finishedWarehouse : null, $notes !== '' ? $notes : null, $adminKey, $adminKey],
                'Manufacturing settings save'
            );
            $persisted = [
                'setting_key' => $settingKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                'allow_overproduction_percent' => $overproduction, 'capacity_planning_enabled' => $capacity,
                'default_wip_warehouse_key' => $wipWarehouse, 'default_finished_goods_warehouse_key' => $finishedWarehouse,
                'notes' => $notes, 'setting_status' => 'ACTIVE',
            ];
            return [
                'record_key' => $settingKey, 'business_key' => 'manufacturing-settings', 'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey, 'persisted_fields' => $persisted,
                'audit_action' => $isUpdate ? 'UPDATE' : 'CREATE',
            ];
        },
        static function (ADOConnection $db, array $expected) use ($companyKeyHash): array {
            $row = $db->GetRow('SELECT * FROM project_company_manufacturing_setting WHERE company_key_hash = ? AND setting_key = ? LIMIT 1', [$companyKeyHash, (string) $expected['record_key']]);
            if (is_array($row)) {
                foreach (['default_wip_warehouse_key', 'default_finished_goods_warehouse_key', 'notes'] as $field) {
                    $row[$field] = (string) ($row[$field] ?? '');
                }
            }
            return is_array($row) ? $row : [];
        }
    );
}
