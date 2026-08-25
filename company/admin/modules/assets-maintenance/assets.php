<?php
declare(strict_types=1);

function yovel_admin_assets_dependency_services(?array $overrides = null): array
{
    $definitions = [
        'inventory.item.read' => static function (array $company, string $itemKey): ?array {
            if (!function_exists('yovel_admin_inventory_item')) {
                throw new RuntimeException('Inventory Item validation is unavailable.');
            }
            return yovel_admin_inventory_item($company, $itemKey);
        },
        'hr.employee.read' => static function (array $company, string $employeeKey): ?array {
            if (!function_exists('yovel_admin_hr_workforce_read_contract')) {
                throw new RuntimeException('HR workforce validation is unavailable.');
            }
            $contract = yovel_admin_hr_workforce_read_contract($company, ['status' => 'ALL']);
            if ((string) ($contract['contract'] ?? '') !== 'hr.workforce.v1'
                || !hash_equals((string) $company['company_key_hash'], (string) ($contract['company_key_hash'] ?? ''))) {
                throw new RuntimeException('HR workforce validation returned an invalid company scope.');
            }
            foreach ($contract['employees'] ?? [] as $employee) {
                if (is_array($employee) && (string) ($employee['employee_key'] ?? '') === $employeeKey) {
                    return $employee + ['company_key_hash' => (string) $contract['company_key_hash']];
                }
            }
            return null;
        },
        'finance.account.read' => static function (array $company, string $accountKey): ?array {
            if (!function_exists('yovel_admin_finance_account_reference')) {
                throw new RuntimeException('Finance account validation is unavailable.');
            }
            return yovel_admin_finance_account_reference($company, $accountKey);
        },
        'finance.asset-posting.readiness' => static function (array $company): array {
            if (!function_exists('yovel_admin_finance_asset_posting_contract')
                || !function_exists('yovel_admin_finance_asset_posting_request')) {
                throw new RuntimeException('Finance Asset posting readiness is unavailable.');
            }
            return yovel_admin_finance_asset_posting_contract();
        },
        'finance.asset-posting.command' => static function (ADOConnection $db, array $company, array $admin, array $request): array {
            if (!function_exists('yovel_admin_finance_asset_posting_request')) {
                throw new RuntimeException('Finance Asset posting command is unavailable.');
            }
            return yovel_admin_finance_asset_posting_request($db, $company, $admin, $request);
        },
    ];
    foreach ($overrides ?? [] as $capability => $provider) {
        if (!array_key_exists($capability, $definitions)) {
            throw new InvalidArgumentException('Assets dependency is not allow-listed: ' . $capability . '.');
        }
        if (!is_callable($provider)) {
            throw new InvalidArgumentException('Assets dependency provider must be callable: ' . $capability . '.');
        }
        $definitions[$capability] = $provider;
    }
    return $definitions;
}

function yovel_admin_assets_owner_options(array $company): array
{
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('Assets owner-option company scope is invalid.');
    }
    $state = [
        'inventory.item.read' => 'UNAVAILABLE_DEPENDENCY',
        'hr.employee.read' => 'UNAVAILABLE_DEPENDENCY',
        'finance.asset-posting.readiness' => 'UNAVAILABLE_DEPENDENCY',
    ];
    $items = [];
    $employees = [];
    if (function_exists('yovel_admin_inventory_items')) {
        try {
            $rows = yovel_admin_inventory_items($company, ['status' => 'ACTIVE']);
            $items = array_values(array_filter(is_array($rows) ? $rows : [], static fn (array $row): bool => hash_equals($companyKeyHash, (string) ($row['company_key_hash'] ?? ''))));
            $state['inventory.item.read'] = 'AVAILABLE';
        } catch (Throwable) {
            $state['inventory.item.read'] = 'ERROR';
        }
    }
    if (function_exists('yovel_admin_hr_workforce_read_contract')) {
        try {
            $contract = yovel_admin_hr_workforce_read_contract($company, ['status' => 'ACTIVE']);
            if ((string) ($contract['contract'] ?? '') !== 'hr.workforce.v1'
                || !hash_equals($companyKeyHash, (string) ($contract['company_key_hash'] ?? ''))
                || !is_array($contract['employees'] ?? null)) {
                throw new RuntimeException('HR workforce option scope is invalid.');
            }
            $employees = array_values($contract['employees']);
            $state['hr.employee.read'] = 'AVAILABLE';
        } catch (Throwable) {
            $state['hr.employee.read'] = 'ERROR';
        }
    }
    try {
        yovel_admin_assets_owner_posting_readiness($company, yovel_admin_assets_dependency_services());
        $state['finance.asset-posting.readiness'] = 'AVAILABLE';
    } catch (Throwable) {
        $state['finance.asset-posting.readiness'] = 'ERROR';
    }
    return ['inventory_items' => $items, 'employees' => $employees, 'dependency_state' => $state];
}

function yovel_admin_assets_local_key(mixed $value, string $label, bool $required = true): string
{
    $key = trim((string) $value);
    if ($key === '' && !$required) {
        return '';
    }
    if ($key === '' || !function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $key;
}

function yovel_admin_assets_code(mixed $value, string $label, int $maxLength = 100): string
{
    $code = strtoupper(trim((string) $value));
    if ($code === '' || strlen($code) > $maxLength || preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $code) !== 1) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $code;
}

function yovel_admin_assets_date(mixed $value, string $label, bool $withTime = false): string
{
    $text = trim((string) $value);
    $format = $withTime ? 'Y-m-d H:i:s' : 'Y-m-d';
    $date = DateTimeImmutable::createFromFormat('!' . $format, $text);
    if (!$date || $date->format($format) !== $text) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $text;
}

function yovel_admin_assets_decimal(mixed $value, string $label, int $scale = 6, bool $positive = false): string
{
    $text = trim((string) $value);
    if ($text === '' || !is_numeric($text)) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    $number = (float) $text;
    if (!is_finite($number) || ($positive && $number <= 0) || (!$positive && $number < 0)) {
        throw new InvalidArgumentException($label . ($positive ? ' must be greater than zero.' : ' cannot be negative.'));
    }
    return number_format($number, $scale, '.', '');
}

function yovel_admin_assets_owner_item(array $company, string $itemKey, array $services): array
{
    try {
        $item = $services['inventory.item.read']($company, $itemKey);
    } catch (Throwable) {
        throw new RuntimeException('Inventory Item validation is unavailable.');
    }
    $hash = (string) $company['company_key_hash'];
    if (!is_array($item)
        || (string) ($item['item_key'] ?? '') !== $itemKey
        || !hash_equals($hash, (string) ($item['company_key_hash'] ?? ''))
        || (string) ($item['item_status'] ?? '') !== 'ACTIVE') {
        throw new InvalidArgumentException('Inventory Item was not found or is not active for this company.');
    }
    return $item;
}

function yovel_admin_assets_owner_employee(array $company, string $employeeKey, array $services): array
{
    try {
        $employee = $services['hr.employee.read']($company, $employeeKey);
    } catch (Throwable) {
        throw new RuntimeException('HR employee validation is unavailable.');
    }
    $hash = (string) $company['company_key_hash'];
    if (!is_array($employee)
        || (string) ($employee['employee_key'] ?? '') !== $employeeKey
        || !hash_equals($hash, (string) ($employee['company_key_hash'] ?? ''))
        || in_array((string) ($employee['employee_status'] ?? ''), ['INACTIVE', 'SEPARATED', 'DELETED'], true)) {
        throw new InvalidArgumentException('HR employee was not found or is not available for this company.');
    }
    return $employee;
}

function yovel_admin_assets_owner_account(array $company, string $accountKey, string $role, array $services): array
{
    try {
        $reference = $services['finance.account.read']($company, $accountKey);
    } catch (Throwable) {
        throw new RuntimeException('Finance account validation is unavailable.');
    }
    $hash = (string) $company['company_key_hash'];
    $record = is_array($reference) && is_array($reference['record'] ?? null) ? $reference['record'] : [];
    if (($reference['ok'] ?? false) !== true
        || !hash_equals($hash, (string) ($reference['company_key_hash'] ?? ''))
        || (string) ($record['account_key'] ?? '') !== $accountKey
        || (string) ($record['account_status'] ?? '') !== 'ACTIVE'
        || !empty($record['is_group'])
        || !empty($record['freeze_account'])) {
        throw new InvalidArgumentException('Finance account was not found or is not an active posting account for this company.');
    }
    $rootType = strtoupper((string) ($record['root_type'] ?? ''));
    if (in_array($role, ['ASSET', 'ACCUMULATED_DEPRECIATION'], true) && $rootType !== 'ASSET') {
        throw new InvalidArgumentException('Asset Category balance-sheet accounts must use an Asset root type.');
    }
    if ($role === 'DEPRECIATION_EXPENSE' && $rootType !== 'EXPENSE') {
        throw new InvalidArgumentException('Depreciation expense must use an Expense root type.');
    }
    if ($role === 'GAIN_LOSS' && !in_array($rootType, ['INCOME', 'EXPENSE'], true)) {
        throw new InvalidArgumentException('Gain or loss must use an Income or Expense root type.');
    }
    return $record;
}

function yovel_admin_assets_owner_posting_readiness(array $company, array $services): array
{
    try {
        $contract = $services['finance.asset-posting.readiness']($company);
    } catch (Throwable) {
        throw new RuntimeException('Finance Asset posting readiness is unavailable.');
    }
    if (!is_array($contract)
        || (string) ($contract['contract'] ?? '') !== 'accounting-finance.asset-posting.v1'
        || (string) ($contract['owner'] ?? '') !== 'accounting-finance'
        || (string) ($contract['owner_function'] ?? '') !== 'yovel_admin_finance_asset_posting_request'
        || (string) ($contract['transaction_owner'] ?? '') !== 'CALLER'
        || ($contract['operations'] ?? null) !== ['DRAFT', 'POST', 'REVERSE']) {
        throw new RuntimeException('Finance Asset posting readiness returned an invalid contract.');
    }
    return $contract;
}

function yovel_admin_assets_write_lifecycle_audit(
    ADOConnection $db,
    array $scope,
    string $targetKind,
    string $targetKey,
    string $action,
    array $details = []
): string {
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $allowedKinds = ['LOCATION', 'CATEGORY', 'ASSET', 'MOVEMENT'];
    if (!in_array($targetKind, $allowedKinds, true)) {
        throw new LogicException('Assets lifecycle audit target is invalid.');
    }
    $auditKey = bx_uuid();
    $detailsJson = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    yovel_admin_assets_execute($db, "INSERT INTO project_company_asset_lifecycle_audit (
        audit_key, company_key, company_key_hash, target_kind, target_key,
        audit_action, details_json, created_by_admin_key
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [$auditKey, $companyKey, $companyKeyHash, $targetKind, $targetKey, $action, $detailsJson, $adminKey], 'Assets lifecycle audit save');
    $saved = $db->GetRow('SELECT * FROM project_company_asset_lifecycle_audit WHERE company_key_hash = ? AND audit_key = ? LIMIT 1', [$companyKeyHash, $auditKey]);
    yovel_admin_assets_assert_readback([
        'audit_key' => $auditKey,
        'company_key' => $companyKey,
        'company_key_hash' => $companyKeyHash,
        'target_kind' => $targetKind,
        'target_key' => $targetKey,
        'audit_action' => $action,
        'details_json' => $detailsJson,
        'created_by_admin_key' => $adminKey,
    ], is_array($saved) ? $saved : [], ['audit_key', 'company_key', 'company_key_hash', 'target_kind', 'target_key', 'audit_action', 'details_json', 'created_by_admin_key'], 'Assets lifecycle audit');
    $module = match ($targetKind) {
        'LOCATION' => 'project_company_asset_location',
        'CATEGORY' => 'project_company_asset_category',
        'MOVEMENT' => 'project_company_asset_movement',
        default => 'project_company_asset',
    };
    bx_audit($action, $module, $targetKey, ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey] + $details, 'Company administrator changed an Assets / Maintenance lifecycle record.');
    return $auditKey;
}

function yovel_admin_assets_write_activity(
    ADOConnection $db,
    array $scope,
    string $assetKey,
    string $activityType,
    string $activityAt,
    string $movementKey = '',
    array $locations = [],
    array $custodians = [],
    array $details = []
): string {
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $activityKey = bx_uuid();
    $detailsJson = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    yovel_admin_assets_execute($db, "INSERT INTO project_company_asset_activity (
        activity_key, company_key, company_key_hash, asset_key, movement_key,
        activity_type, activity_at, from_location_key, to_location_key,
        from_custodian_employee_key, to_custodian_employee_key, details_json,
        created_by_admin_key
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
        $activityKey, $companyKey, $companyKeyHash, $assetKey, $movementKey !== '' ? $movementKey : null,
        $activityType, $activityAt,
        ($locations['from'] ?? '') !== '' ? $locations['from'] : null,
        ($locations['to'] ?? '') !== '' ? $locations['to'] : null,
        ($custodians['from'] ?? '') !== '' ? $custodians['from'] : null,
        ($custodians['to'] ?? '') !== '' ? $custodians['to'] : null,
        $detailsJson, $adminKey,
    ], 'Asset Activity save');
    $saved = $db->GetRow('SELECT * FROM project_company_asset_activity WHERE company_key_hash = ? AND activity_key = ? LIMIT 1', [$companyKeyHash, $activityKey]);
    yovel_admin_assets_assert_readback([
        'activity_key' => $activityKey,
        'company_key' => $companyKey,
        'company_key_hash' => $companyKeyHash,
        'asset_key' => $assetKey,
        'movement_key' => $movementKey,
        'activity_type' => $activityType,
        'activity_at' => $activityAt,
        'from_location_key' => (string) ($locations['from'] ?? ''),
        'to_location_key' => (string) ($locations['to'] ?? ''),
        'from_custodian_employee_key' => (string) ($custodians['from'] ?? ''),
        'to_custodian_employee_key' => (string) ($custodians['to'] ?? ''),
        'details_json' => $detailsJson,
        'created_by_admin_key' => $adminKey,
    ], is_array($saved) ? array_map(static fn ($value) => $value ?? '', $saved) : [], [
        'activity_key', 'company_key', 'company_key_hash', 'asset_key', 'movement_key',
        'activity_type', 'activity_at', 'from_location_key', 'to_location_key',
        'from_custodian_employee_key', 'to_custodian_employee_key', 'details_json', 'created_by_admin_key',
    ], 'Asset Activity');
    return $activityKey;
}

function yovel_admin_assets_location(array $company, string $locationKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($locationKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_asset_location WHERE company_key = ? AND company_key_hash = ? AND location_key = ? LIMIT 1', [$companyKey, $companyKeyHash, $locationKey]);
    if (!is_array($row) || $row === []) {
        return null;
    }
    $links = bx_db()->GetAll('SELECT * FROM project_company_asset_linked_location WHERE company_key = ? AND company_key_hash = ? AND location_key = ? ORDER BY x_id', [$companyKey, $companyKeyHash, $locationKey]);
    return $row + ['linked_locations' => is_array($links) ? $links : []];
}

function yovel_admin_assets_locations(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $rows = bx_db()->GetAll('SELECT * FROM project_company_asset_location WHERE company_key = ? AND company_key_hash = ? ORDER BY location_path, location_name', [$companyKey, $companyKeyHash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_save_location(array $company, array $admin, array $input): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $requestedKey = yovel_admin_assets_local_key($input['location_key'] ?? '', 'Location key', false);
    $code = yovel_admin_assets_code($input['location_code'] ?? '', 'Location code', 80);
    $name = yovel_admin_assets_text($input['location_name'] ?? '', 'Location name', 180, true);
    $parentKey = yovel_admin_assets_local_key($input['parent_location_key'] ?? '', 'Parent location key', false);
    $status = strtoupper(trim((string) ($input['location_status'] ?? 'ACTIVE')));
    if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
        throw new InvalidArgumentException('Location status is invalid.');
    }
    $linkedKeys = is_array($input['linked_location_keys'] ?? null) ? $input['linked_location_keys'] : [];
    $linkedKeys = array_values(array_unique(array_filter(array_map(static fn ($key): string => yovel_admin_assets_local_key($key, 'Linked location key', false), $linkedKeys))));

    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $requestedKey, $code, $name, $parentKey, $status, $linkedKeys): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_asset_location WHERE company_key_hash = ? AND location_key = ? FOR UPDATE', [$companyKeyHash, $requestedKey]) : [];
        if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Location was not found for this company.');
        }
        $duplicate = $db->GetRow('SELECT location_key FROM project_company_asset_location WHERE company_key_hash = ? AND location_code = ? AND location_key <> ? LIMIT 1', [$companyKeyHash, $code, $requestedKey]);
        if (is_array($duplicate) && $duplicate !== []) {
            throw new InvalidArgumentException('Location code already exists for this company.');
        }
        $locationKey = is_array($existing) && $existing !== [] ? (string) $existing['location_key'] : bx_uuid();
        if ($parentKey === $locationKey) {
            throw new InvalidArgumentException('Location tree cycle is not allowed.');
        }
        $parent = [];
        if ($parentKey !== '') {
            $parent = $db->GetRow('SELECT * FROM project_company_asset_location WHERE company_key_hash = ? AND location_key = ? FOR UPDATE', [$companyKeyHash, $parentKey]);
            if (!is_array($parent) || $parent === [] || (string) $parent['location_status'] !== 'ACTIVE') {
                throw new InvalidArgumentException('Parent location was not found or is inactive.');
            }
            $cursor = $parent;
            for ($depth = 0; $depth < 100 && (string) ($cursor['parent_location_key'] ?? '') !== ''; $depth++) {
                if ((string) $cursor['parent_location_key'] === $locationKey) {
                    throw new InvalidArgumentException('Location tree cycle is not allowed.');
                }
                $cursor = $db->GetRow('SELECT parent_location_key FROM project_company_asset_location WHERE company_key_hash = ? AND location_key = ? LIMIT 1', [$companyKeyHash, (string) $cursor['parent_location_key']]);
                if (!is_array($cursor) || $cursor === []) {
                    throw new RuntimeException('Location tree could not be verified.');
                }
            }
        }
        $path = ($parentKey !== '' ? rtrim((string) $parent['location_path'], '/') : '') . '/' . $code;
        if (is_array($existing) && $existing !== []) {
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset_location SET location_code = ?, location_name = ?, parent_location_key = ?, location_path = ?, location_status = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND location_key = ?', [$code, $name, $parentKey !== '' ? $parentKey : null, $path, $status, $adminKey, $companyKeyHash, $locationKey], 'Asset Location update');
        } else {
            yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_location (location_key, company_key, company_key_hash, location_code, location_name, parent_location_key, location_path, location_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$locationKey, $companyKey, $companyKeyHash, $code, $name, $parentKey !== '' ? $parentKey : null, $path, $status, $adminKey, $adminKey], 'Asset Location create');
        }
        yovel_admin_assets_execute($db, 'DELETE FROM project_company_asset_linked_location WHERE company_key_hash = ? AND location_key = ?', [$companyKeyHash, $locationKey], 'Linked Location reset');
        foreach ($linkedKeys as $linkedKey) {
            if ($linkedKey === $locationKey) {
                throw new InvalidArgumentException('A Location cannot link to itself.');
            }
            $target = $db->GetRow('SELECT location_key FROM project_company_asset_location WHERE company_key_hash = ? AND location_key = ? AND location_status = \'ACTIVE\' LIMIT 1', [$companyKeyHash, $linkedKey]);
            if (!is_array($target) || $target === []) {
                throw new InvalidArgumentException('Linked Location was not found or is inactive.');
            }
            $checksum = hash('sha256', $companyKeyHash . '|' . $locationKey . '|' . $linkedKey);
            yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_linked_location (linked_location_key, company_key, company_key_hash, location_key, target_location_key, link_status, immutable_checksum, created_by_admin_key) VALUES (?, ?, ?, ?, ?, \'ACTIVE\', ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $locationKey, $linkedKey, $checksum, $adminKey], 'Linked Location create');
        }
        $action = is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE';
        yovel_admin_assets_write_lifecycle_audit($db, $scope, 'LOCATION', $locationKey, $action, ['location_code' => $code, 'parent_location_key' => $parentKey, 'linked_location_count' => count($linkedKeys)]);
        $saved = yovel_admin_assets_location($company, $locationKey);
        if (!is_array($saved)) {
            throw new RuntimeException('Asset Location could not be read back.');
        }
        yovel_admin_assets_assert_readback(['location_key' => $locationKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'location_code' => $code, 'location_name' => $name, 'parent_location_key' => $parentKey, 'location_path' => $path, 'location_status' => $status, 'updated_by_admin_key' => $adminKey], array_map(static fn ($value) => $value ?? '', $saved), ['location_key', 'company_key', 'company_key_hash', 'location_code', 'location_name', 'parent_location_key', 'location_path', 'location_status', 'updated_by_admin_key'], 'Asset Location');
        return $saved;
    });
}

function yovel_admin_assets_category(array $company, string $categoryKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($categoryKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_asset_category WHERE company_key = ? AND company_key_hash = ? AND category_key = ? LIMIT 1', [$companyKey, $companyKeyHash, $categoryKey]);
    if (!is_array($row) || $row === []) {
        return null;
    }
    $accounts = bx_db()->GetAll('SELECT * FROM project_company_asset_category_account WHERE company_key = ? AND company_key_hash = ? AND category_key = ? ORDER BY account_role', [$companyKey, $companyKeyHash, $categoryKey]);
    return $row + ['accounts' => is_array($accounts) ? $accounts : []];
}

function yovel_admin_assets_categories(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $rows = bx_db()->GetAll('SELECT * FROM project_company_asset_category WHERE company_key = ? AND company_key_hash = ? ORDER BY category_name', [$companyKey, $companyKeyHash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_save_category(array $company, array $admin, array $input, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $services = yovel_admin_assets_dependency_services($services);
    $requestedKey = yovel_admin_assets_local_key($input['category_key'] ?? '', 'Category key', false);
    $code = yovel_admin_assets_code($input['category_code'] ?? '', 'Category code', 80);
    $name = yovel_admin_assets_text($input['category_name'] ?? '', 'Category name', 180, true);
    $status = strtoupper(trim((string) ($input['category_status'] ?? 'ACTIVE')));
    if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
        throw new InvalidArgumentException('Category status is invalid.');
    }
    $accounts = [];
    foreach (is_array($input['accounts'] ?? null) ? $input['accounts'] : [] as $account) {
        if (!is_array($account)) {
            throw new InvalidArgumentException('Asset Category Account is invalid.');
        }
        if (trim((string) ($account['account_owner_key'] ?? '')) === '' && trim((string) ($account['account_label'] ?? '')) === '') {
            continue;
        }
        $role = strtoupper(trim((string) ($account['account_role'] ?? '')));
        if (!in_array($role, ['ASSET', 'ACCUMULATED_DEPRECIATION', 'DEPRECIATION_EXPENSE', 'GAIN_LOSS'], true) || isset($accounts[$role])) {
            throw new InvalidArgumentException('Asset Category Account role is invalid or duplicated.');
        }
        $accountKey = yovel_admin_assets_opaque_key($account['account_owner_key'] ?? '', 'Account owner key');
        $ownerAccount = yovel_admin_assets_owner_account($company, $accountKey, $role, $services);
        $accounts[$role] = [
            'account_role' => $role,
            'account_owner_key' => $accountKey,
            'account_label_snapshot' => yovel_admin_assets_text($ownerAccount['account_name'] ?? '', 'Finance account name', 180, true),
        ];
    }

    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $requestedKey, $code, $name, $status, $accounts): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_asset_category WHERE company_key_hash = ? AND category_key = ? FOR UPDATE', [$companyKeyHash, $requestedKey]) : [];
        if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Asset Category was not found for this company.');
        }
        $duplicate = $db->GetRow('SELECT category_key FROM project_company_asset_category WHERE company_key_hash = ? AND category_code = ? AND category_key <> ? LIMIT 1', [$companyKeyHash, $code, $requestedKey]);
        if (is_array($duplicate) && $duplicate !== []) {
            throw new InvalidArgumentException('Asset Category code already exists for this company.');
        }
        $categoryKey = is_array($existing) && $existing !== [] ? (string) $existing['category_key'] : bx_uuid();
        if (is_array($existing) && $existing !== []) {
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset_category SET category_code = ?, category_name = ?, category_status = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND category_key = ?', [$code, $name, $status, $adminKey, $companyKeyHash, $categoryKey], 'Asset Category update');
        } else {
            yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_category (category_key, company_key, company_key_hash, category_code, category_name, category_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$categoryKey, $companyKey, $companyKeyHash, $code, $name, $status, $adminKey, $adminKey], 'Asset Category create');
        }
        yovel_admin_assets_execute($db, 'DELETE FROM project_company_asset_category_account WHERE company_key_hash = ? AND category_key = ?', [$companyKeyHash, $categoryKey], 'Asset Category Account reset');
        foreach ($accounts as $account) {
            $checksum = hash('sha256', $companyKeyHash . '|' . $categoryKey . '|' . $account['account_role'] . '|' . $account['account_owner_key'] . '|' . $account['account_label_snapshot']);
            yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_category_account (category_account_key, company_key, company_key_hash, category_key, account_role, account_owner_key, account_label_snapshot, immutable_checksum, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $companyKey, $companyKeyHash, $categoryKey, $account['account_role'], $account['account_owner_key'], $account['account_label_snapshot'], $checksum, $adminKey], 'Asset Category Account create');
        }
        $action = is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE';
        yovel_admin_assets_write_lifecycle_audit($db, $scope, 'CATEGORY', $categoryKey, $action, ['category_code' => $code, 'account_count' => count($accounts)]);
        $saved = yovel_admin_assets_category($company, $categoryKey);
        if (!is_array($saved)) {
            throw new RuntimeException('Asset Category could not be read back.');
        }
        yovel_admin_assets_assert_readback(['category_key' => $categoryKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'category_code' => $code, 'category_name' => $name, 'category_status' => $status, 'updated_by_admin_key' => $adminKey], $saved, ['category_key', 'company_key', 'company_key_hash', 'category_code', 'category_name', 'category_status', 'updated_by_admin_key'], 'Asset Category');
        if (count($saved['accounts']) !== count($accounts)) {
            throw new RuntimeException('Asset Category Account read-back verification failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_asset(array $company, string $assetKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($assetKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_asset WHERE company_key = ? AND company_key_hash = ? AND asset_key = ? LIMIT 1', [$companyKey, $companyKeyHash, $assetKey]);
    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_assets_assets(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $rows = bx_db()->GetAll('SELECT * FROM project_company_asset WHERE company_key = ? AND company_key_hash = ? ORDER BY updated_at DESC, x_id DESC', [$companyKey, $companyKeyHash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_activities(array $company, string $assetKey = ''): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $sql = 'SELECT * FROM project_company_asset_activity WHERE company_key = ? AND company_key_hash = ?';
    $parameters = [$companyKey, $companyKeyHash];
    if ($assetKey !== '') {
        $assetKey = yovel_admin_assets_local_key($assetKey, 'Asset key');
        $sql .= ' AND asset_key = ?';
        $parameters[] = $assetKey;
    }
    $rows = bx_db()->GetAll($sql . ' ORDER BY activity_at DESC, x_id DESC LIMIT 200', $parameters);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_reference_checksum(array $values): string
{
    return hash('sha256', implode('|', array_map(static fn ($value): string => (string) $value, $values)));
}

function yovel_admin_assets_save_asset(array $company, array $admin, array $input, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $services = yovel_admin_assets_dependency_services($services);
    $requestedKey = yovel_admin_assets_local_key($input['asset_key'] ?? '', 'Asset key', false);
    $code = yovel_admin_assets_code($input['asset_code'] ?? '', 'Asset code');
    $name = yovel_admin_assets_text($input['asset_name'] ?? '', 'Asset name', 180, true);
    $itemKey = yovel_admin_assets_opaque_key($input['item_owner_key'] ?? '', 'Inventory Item owner key');
    $item = yovel_admin_assets_owner_item($company, $itemKey, $services);
    $categoryKey = yovel_admin_assets_local_key($input['category_key'] ?? '', 'Asset Category key');
    $category = yovel_admin_assets_category($company, $categoryKey);
    if (!is_array($category) || (string) $category['category_status'] !== 'ACTIVE') {
        throw new InvalidArgumentException('Asset Category was not found or is inactive.');
    }
    $locationKey = yovel_admin_assets_local_key($input['location_key'] ?? '', 'Asset Location key');
    $location = yovel_admin_assets_location($company, $locationKey);
    if (!is_array($location) || (string) $location['location_status'] !== 'ACTIVE') {
        throw new InvalidArgumentException('Asset Location was not found or is inactive.');
    }
    $employeeKey = yovel_admin_assets_opaque_key($input['custodian_employee_key'] ?? '', 'Custodian employee key', false);
    $employee = $employeeKey !== '' ? yovel_admin_assets_owner_employee($company, $employeeKey, $services) : [];
    $acquisitionDate = yovel_admin_assets_date($input['acquisition_date'] ?? '', 'Acquisition date');
    $availableDate = yovel_admin_assets_date($input['available_for_use_date'] ?? '', 'Available-for-use date');
    if ($availableDate < $acquisitionDate) {
        throw new InvalidArgumentException('Available-for-use date cannot precede acquisition date.');
    }
    $referenceType = strtoupper(trim((string) ($input['purchase_reference_type'] ?? 'NONE')));
    if (!in_array($referenceType, ['NONE', 'PURCHASE_RECEIPT', 'PURCHASE_INVOICE', 'OPENING'], true)) {
        throw new InvalidArgumentException('Purchase reference type is invalid.');
    }
    $referenceKey = yovel_admin_assets_opaque_key($input['purchase_reference_key'] ?? '', 'Purchase reference key', $referenceType !== 'NONE');
    if ($referenceType === 'NONE') {
        $referenceKey = '';
    }
    $quantity = yovel_admin_assets_decimal($input['purchase_quantity'] ?? '1', 'Purchase quantity', 6, true);
    $amount = yovel_admin_assets_decimal($input['gross_purchase_amount'] ?? '0', 'Gross purchase amount');
    $lifecycleStatus = strtoupper(trim((string) ($input['lifecycle_status'] ?? 'AVAILABLE')));
    if (!in_array($lifecycleStatus, ['AVAILABLE', 'IN_USE', 'IDLE', 'SOLD', 'SCRAPPED'], true)) {
        throw new InvalidArgumentException('Asset lifecycle status is invalid.');
    }
    $notes = yovel_admin_assets_text($input['notes'] ?? '', 'Asset notes', 5000);
    $amendedFromKey = yovel_admin_assets_local_key($input['amended_from_asset_key'] ?? '', 'Amended-from Asset key', false);
    $snapshots = [
        'item_code' => yovel_admin_assets_text($item['item_code'] ?? '', 'Inventory Item code', 100, true),
        'item_name' => yovel_admin_assets_text($item['item_name'] ?? '', 'Inventory Item name', 180, true),
        'category_name' => (string) $category['category_name'],
        'location_name' => (string) $location['location_name'],
        'employee_code' => $employeeKey !== '' ? yovel_admin_assets_text($employee['employee_code'] ?? '', 'Employee code', 100, true) : '',
        'employee_name' => $employeeKey !== '' ? yovel_admin_assets_text($employee['employee_name'] ?? '', 'Employee name', 180, true) : '',
    ];
    $referenceChecksum = yovel_admin_assets_reference_checksum([$companyKeyHash, $itemKey, $snapshots['item_code'], $categoryKey, $locationKey, $employeeKey, $referenceType, $referenceKey, $quantity, $amount, $acquisitionDate, $availableDate]);

    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $requestedKey, $code, $name, $itemKey, $categoryKey, $locationKey, $employeeKey, $acquisitionDate, $availableDate, $referenceType, $referenceKey, $quantity, $amount, $lifecycleStatus, $notes, $amendedFromKey, $snapshots, $referenceChecksum): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_asset WHERE company_key_hash = ? AND asset_key = ? FOR UPDATE', [$companyKeyHash, $requestedKey]) : [];
        if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Asset was not found for this company.');
        }
        if (is_array($existing) && $existing !== [] && (string) $existing['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException(ucfirst(strtolower((string) $existing['document_status'])) . ' Asset records are immutable.');
        }
        $duplicate = $db->GetRow('SELECT asset_key FROM project_company_asset WHERE company_key_hash = ? AND asset_code = ? AND asset_key <> ? LIMIT 1', [$companyKeyHash, $code, $requestedKey]);
        if (is_array($duplicate) && $duplicate !== []) {
            throw new InvalidArgumentException('Asset code already exists for this company.');
        }
        $assetKey = is_array($existing) && $existing !== [] ? (string) $existing['asset_key'] : bx_uuid();
        $amendedSource = [];
        if ($amendedFromKey !== '') {
            if (is_array($existing) && $existing !== [] && (string) ($existing['amended_from_asset_key'] ?? '') !== $amendedFromKey) {
                throw new InvalidArgumentException('Amended-from Asset reference cannot change.');
            }
            $amendedSource = $db->GetRow('SELECT * FROM project_company_asset WHERE company_key_hash = ? AND asset_key = ? FOR UPDATE', [$companyKeyHash, $amendedFromKey]);
            if (!is_array($amendedSource) || $amendedSource === [] || (string) $amendedSource['document_status'] !== 'CANCELLED' || (string) ($amendedSource['amended_by_asset_key'] ?? '') !== '') {
                throw new InvalidArgumentException('Only an unamended cancelled Asset can be amended.');
            }
        }
        $values = [$code, $name, $itemKey, $snapshots['item_code'], $snapshots['item_name'], $categoryKey, $snapshots['category_name'], $locationKey, $snapshots['location_name'], $employeeKey !== '' ? $employeeKey : null, $snapshots['employee_code'], $snapshots['employee_name'], $referenceType, $referenceKey !== '' ? $referenceKey : null, $quantity, $amount, $acquisitionDate, $availableDate, $lifecycleStatus, $amendedFromKey !== '' ? $amendedFromKey : null, $notes, $referenceChecksum, $adminKey];
        if (is_array($existing) && $existing !== []) {
            yovel_admin_assets_execute($db, "UPDATE project_company_asset SET asset_code = ?, asset_name = ?, item_owner_key = ?, item_code_snapshot = ?, item_name_snapshot = ?, category_key = ?, category_name_snapshot = ?, location_key = ?, location_name_snapshot = ?, custodian_employee_key = ?, custodian_code_snapshot = ?, custodian_name_snapshot = ?, purchase_reference_type = ?, purchase_reference_key = ?, purchase_quantity = ?, gross_purchase_amount = ?, acquisition_date = ?, available_for_use_date = ?, lifecycle_status = ?, amended_from_asset_key = ?, notes = ?, immutable_reference_checksum = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND asset_key = ?", [...$values, $companyKeyHash, $assetKey], 'Asset update');
        } else {
            yovel_admin_assets_execute($db, "INSERT INTO project_company_asset (asset_key, company_key, company_key_hash, asset_code, asset_name, item_owner_key, item_code_snapshot, item_name_snapshot, category_key, category_name_snapshot, location_key, location_name_snapshot, custodian_employee_key, custodian_code_snapshot, custodian_name_snapshot, purchase_reference_type, purchase_reference_key, purchase_quantity, gross_purchase_amount, acquisition_date, available_for_use_date, lifecycle_status, document_status, amended_from_asset_key, notes, immutable_reference_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, ?, ?, ?, ?)", [$assetKey, $companyKey, $companyKeyHash, ...array_slice($values, 0, 20), $notes, $referenceChecksum, $adminKey, $adminKey], 'Asset create');
        }
        yovel_admin_assets_fault('asset_after_write');
        $activityType = is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE';
        yovel_admin_assets_write_activity($db, $scope, $assetKey, $activityType, date('Y-m-d H:i:s'), '', [], [], ['asset_code' => $code, 'document_status' => 'DRAFT']);
        if ($amendedFromKey !== '' && (!is_array($existing) || $existing === [])) {
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset SET amended_by_asset_key = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND asset_key = ?', [$assetKey, $adminKey, $companyKeyHash, $amendedFromKey], 'Cancelled Asset amendment link');
            yovel_admin_assets_write_activity($db, $scope, $amendedFromKey, 'AMEND', date('Y-m-d H:i:s'), '', [], [], ['amended_by_asset_key' => $assetKey]);
        }
        yovel_admin_assets_write_lifecycle_audit($db, $scope, 'ASSET', $assetKey, $activityType, ['asset_code' => $code, 'item_owner_key' => $itemKey, 'category_key' => $categoryKey, 'location_key' => $locationKey, 'custodian_employee_key' => $employeeKey, 'amended_from_asset_key' => $amendedFromKey]);
        $saved = yovel_admin_assets_asset($company, $assetKey);
        if (!is_array($saved)) {
            throw new RuntimeException('Asset could not be read back.');
        }
        yovel_admin_assets_assert_readback(['asset_key' => $assetKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'asset_code' => $code, 'asset_name' => $name, 'item_owner_key' => $itemKey, 'category_key' => $categoryKey, 'location_key' => $locationKey, 'custodian_employee_key' => $employeeKey, 'purchase_reference_type' => $referenceType, 'purchase_reference_key' => $referenceKey, 'purchase_quantity' => $quantity, 'gross_purchase_amount' => $amount, 'acquisition_date' => $acquisitionDate, 'available_for_use_date' => $availableDate, 'lifecycle_status' => $lifecycleStatus, 'document_status' => 'DRAFT', 'amended_from_asset_key' => $amendedFromKey, 'notes' => $notes, 'immutable_reference_checksum' => $referenceChecksum, 'updated_by_admin_key' => $adminKey], array_map(static fn ($value) => $value ?? '', $saved), ['asset_key', 'company_key', 'company_key_hash', 'asset_code', 'asset_name', 'item_owner_key', 'category_key', 'location_key', 'custodian_employee_key', 'purchase_reference_type', 'purchase_reference_key', 'purchase_quantity', 'gross_purchase_amount', 'acquisition_date', 'available_for_use_date', 'lifecycle_status', 'document_status', 'amended_from_asset_key', 'notes', 'immutable_reference_checksum', 'updated_by_admin_key'], 'Asset');
        return $saved;
    });
}

function yovel_admin_assets_submit_asset(array $company, array $admin, string $assetKey, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $assetKey = yovel_admin_assets_local_key($assetKey, 'Asset key');
    $services = yovel_admin_assets_dependency_services($services);
    $current = yovel_admin_assets_asset($company, $assetKey);
    if (!is_array($current)) {
        throw new InvalidArgumentException('Asset was not found for this company.');
    }
    yovel_admin_assets_owner_item($company, (string) $current['item_owner_key'], $services);
    if ((string) ($current['custodian_employee_key'] ?? '') !== '') {
        yovel_admin_assets_owner_employee($company, (string) $current['custodian_employee_key'], $services);
    }

    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $assetKey, $services): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $asset = $db->GetRow('SELECT * FROM project_company_asset WHERE company_key_hash = ? AND asset_key = ? FOR UPDATE', [$companyKeyHash, $assetKey]);
        if (!is_array($asset) || $asset === [] || (string) $asset['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Only a draft Asset can be submitted.');
        }
        if (in_array((string) $asset['lifecycle_status'], ['SOLD', 'SCRAPPED'], true) && ((string) ($asset['purchase_reference_key'] ?? '') === '' || (float) $asset['gross_purchase_amount'] <= 0)) {
            throw new InvalidArgumentException('Sold or scrapped Assets require a valued source reference.');
        }
        if (in_array((string) $asset['lifecycle_status'], ['SOLD', 'SCRAPPED'], true)) {
            yovel_admin_assets_owner_posting_readiness($company, $services);
        }
        yovel_admin_assets_execute($db, "UPDATE project_company_asset SET document_status = 'SUBMITTED', submitted_by_admin_key = ?, submitted_at = CURRENT_TIMESTAMP, updated_by_admin_key = ? WHERE company_key_hash = ? AND asset_key = ?", [$adminKey, $adminKey, $companyKeyHash, $assetKey], 'Asset submit');
        yovel_admin_assets_write_activity($db, $scope, $assetKey, 'SUBMIT', date('Y-m-d H:i:s'), '', [], [], ['asset_code' => (string) $asset['asset_code']]);
        yovel_admin_assets_write_lifecycle_audit($db, $scope, 'ASSET', $assetKey, 'SUBMIT', ['asset_code' => (string) $asset['asset_code'], 'reference_checksum' => (string) $asset['immutable_reference_checksum']]);
        $saved = yovel_admin_assets_asset($company, $assetKey);
        if (!is_array($saved) || (string) $saved['document_status'] !== 'SUBMITTED' || (string) $saved['submitted_by_admin_key'] !== $adminKey || empty($saved['submitted_at'])) {
            throw new RuntimeException('Asset submit read-back verification failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_cancel_asset(array $company, array $admin, string $assetKey, string $reason, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $assetKey = yovel_admin_assets_local_key($assetKey, 'Asset key');
    $reason = yovel_admin_assets_text($reason, 'Cancellation reason', 500, true);
    yovel_admin_assets_dependency_services($services);

    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $assetKey, $reason): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $asset = $db->GetRow('SELECT * FROM project_company_asset WHERE company_key_hash = ? AND asset_key = ? FOR UPDATE', [$companyKeyHash, $assetKey]);
        if (!is_array($asset) || $asset === [] || (string) $asset['document_status'] !== 'SUBMITTED') {
            throw new InvalidArgumentException('Only a submitted Asset can be cancelled.');
        }
        $openMovement = $db->GetOne("SELECT COUNT(*) FROM project_company_asset_movement_item item INNER JOIN project_company_asset_movement movement ON movement.company_key_hash = item.company_key_hash AND movement.movement_key = item.movement_key WHERE item.company_key_hash = ? AND item.asset_key = ? AND movement.document_status = 'SUBMITTED'", [$companyKeyHash, $assetKey]);
        if ((int) $openMovement > 0) {
            throw new InvalidArgumentException('Submitted Asset movements must be cancelled before the Asset.');
        }
        yovel_admin_assets_execute($db, "UPDATE project_company_asset SET document_status = 'CANCELLED', cancellation_reason = ?, cancelled_by_admin_key = ?, cancelled_at = CURRENT_TIMESTAMP, updated_by_admin_key = ? WHERE company_key_hash = ? AND asset_key = ?", [$reason, $adminKey, $adminKey, $companyKeyHash, $assetKey], 'Asset cancel');
        yovel_admin_assets_write_activity($db, $scope, $assetKey, 'CANCEL', date('Y-m-d H:i:s'), '', [], [], ['reason' => $reason]);
        yovel_admin_assets_write_lifecycle_audit($db, $scope, 'ASSET', $assetKey, 'CANCEL', ['asset_code' => (string) $asset['asset_code'], 'reason' => $reason]);
        $saved = yovel_admin_assets_asset($company, $assetKey);
        if (!is_array($saved) || (string) $saved['document_status'] !== 'CANCELLED' || (string) $saved['cancellation_reason'] !== $reason || (string) $saved['cancelled_by_admin_key'] !== $adminKey) {
            throw new RuntimeException('Asset cancellation read-back verification failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_movement(array $company, string $movementKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($movementKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_asset_movement WHERE company_key = ? AND company_key_hash = ? AND movement_key = ? LIMIT 1', [$companyKey, $companyKeyHash, $movementKey]);
    if (!is_array($row) || $row === []) {
        return null;
    }
    $items = bx_db()->GetAll('SELECT * FROM project_company_asset_movement_item WHERE company_key = ? AND company_key_hash = ? AND movement_key = ? ORDER BY x_id', [$companyKey, $companyKeyHash, $movementKey]);
    return $row + ['items' => is_array($items) ? $items : []];
}

function yovel_admin_assets_movements(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $rows = bx_db()->GetAll('SELECT * FROM project_company_asset_movement WHERE company_key = ? AND company_key_hash = ? ORDER BY posting_at DESC, x_id DESC', [$companyKey, $companyKeyHash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_movement_asset_keys(mixed $value): array
{
    $keys = is_array($value) ? $value : preg_split('/[\s,]+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
    $normalized = [];
    foreach ($keys ?: [] as $key) {
        $key = yovel_admin_assets_local_key($key, 'Movement Asset key');
        if (isset($normalized[$key])) {
            throw new InvalidArgumentException('Movement Asset keys cannot be duplicated.');
        }
        $normalized[$key] = $key;
    }
    if ($normalized === []) {
        throw new InvalidArgumentException('At least one Asset is required for a movement.');
    }
    return array_values($normalized);
}

function yovel_admin_assets_save_movement(array $company, array $admin, array $input, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $services = yovel_admin_assets_dependency_services($services);
    $requestedKey = yovel_admin_assets_local_key($input['movement_key'] ?? '', 'Movement key', false);
    $code = yovel_admin_assets_code($input['movement_code'] ?? '', 'Movement code');
    $type = strtoupper(trim((string) ($input['movement_type'] ?? 'TRANSFER')));
    if (!in_array($type, ['TRANSFER', 'CUSTODY', 'LOCATION'], true)) {
        throw new InvalidArgumentException('Movement type is invalid.');
    }
    $postingAt = yovel_admin_assets_date($input['posting_at'] ?? '', 'Movement posting time', true);
    $toLocationKey = yovel_admin_assets_local_key($input['to_location_key'] ?? '', 'Destination Location key', false);
    $toLocation = $toLocationKey !== '' ? yovel_admin_assets_location($company, $toLocationKey) : [];
    if ($toLocationKey !== '' && (!is_array($toLocation) || (string) $toLocation['location_status'] !== 'ACTIVE')) {
        throw new InvalidArgumentException('Destination Location was not found or is inactive.');
    }
    $toEmployeeKey = yovel_admin_assets_opaque_key($input['to_custodian_employee_key'] ?? '', 'Destination custodian key', false);
    $toEmployee = $toEmployeeKey !== '' ? yovel_admin_assets_owner_employee($company, $toEmployeeKey, $services) : [];
    if ($toLocationKey === '' && $toEmployeeKey === '') {
        throw new InvalidArgumentException('A movement requires a destination Location or custodian.');
    }
    $reason = yovel_admin_assets_text($input['reason'] ?? '', 'Movement reason', 500, true);
    $assetKeys = yovel_admin_assets_movement_asset_keys($input['asset_keys'] ?? []);
    $destination = [
        'location_name' => $toLocationKey !== '' ? (string) $toLocation['location_name'] : '',
        'employee_code' => $toEmployeeKey !== '' ? yovel_admin_assets_text($toEmployee['employee_code'] ?? '', 'Employee code', 100, true) : '',
        'employee_name' => $toEmployeeKey !== '' ? yovel_admin_assets_text($toEmployee['employee_name'] ?? '', 'Employee name', 180, true) : '',
    ];
    $checksum = yovel_admin_assets_reference_checksum([$companyKeyHash, $code, $type, $postingAt, $toLocationKey, $toEmployeeKey, $reason, ...$assetKeys]);

    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $requestedKey, $code, $type, $postingAt, $toLocationKey, $toEmployeeKey, $reason, $assetKeys, $destination, $checksum): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_asset_movement WHERE company_key_hash = ? AND movement_key = ? FOR UPDATE', [$companyKeyHash, $requestedKey]) : [];
        if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Asset Movement was not found for this company.');
        }
        if (is_array($existing) && $existing !== [] && (string) $existing['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Submitted or cancelled Asset Movements are immutable.');
        }
        $duplicate = $db->GetRow('SELECT movement_key FROM project_company_asset_movement WHERE company_key_hash = ? AND movement_code = ? AND movement_key <> ? LIMIT 1', [$companyKeyHash, $code, $requestedKey]);
        if (is_array($duplicate) && $duplicate !== []) {
            throw new InvalidArgumentException('Movement code already exists for this company.');
        }
        $movementKey = is_array($existing) && $existing !== [] ? (string) $existing['movement_key'] : bx_uuid();
        $assetRows = [];
        foreach ($assetKeys as $assetKey) {
            $asset = $db->GetRow('SELECT * FROM project_company_asset WHERE company_key_hash = ? AND asset_key = ? FOR UPDATE', [$companyKeyHash, $assetKey]);
            if (!is_array($asset) || $asset === [] || (string) $asset['document_status'] !== 'SUBMITTED' || in_array((string) $asset['lifecycle_status'], ['SOLD', 'SCRAPPED'], true)) {
                throw new InvalidArgumentException('Movement Asset was not found, is not submitted, or cannot move.');
            }
            $latest = $db->GetOne("SELECT MAX(movement.posting_at) FROM project_company_asset_movement movement INNER JOIN project_company_asset_movement_item item ON item.company_key_hash = movement.company_key_hash AND item.movement_key = movement.movement_key WHERE movement.company_key_hash = ? AND item.asset_key = ? AND movement.document_status = 'SUBMITTED' AND movement.movement_key <> ?", [$companyKeyHash, $assetKey, $movementKey]);
            if (is_string($latest) && $latest !== '' && $postingAt < $latest) {
                throw new InvalidArgumentException('Asset Movements must remain chronological.');
            }
            $assetRows[] = $asset;
        }
        if (is_array($existing) && $existing !== []) {
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset_movement SET movement_code = ?, movement_type = ?, posting_at = ?, to_location_key = ?, to_location_name_snapshot = ?, to_custodian_employee_key = ?, to_custodian_code_snapshot = ?, to_custodian_name_snapshot = ?, reason = ?, immutable_checksum = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND movement_key = ?', [$code, $type, $postingAt, $toLocationKey !== '' ? $toLocationKey : null, $destination['location_name'], $toEmployeeKey !== '' ? $toEmployeeKey : null, $destination['employee_code'], $destination['employee_name'], $reason, $checksum, $adminKey, $companyKeyHash, $movementKey], 'Asset Movement update');
        } else {
            yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_movement (movement_key, company_key, company_key_hash, movement_code, movement_type, posting_at, to_location_key, to_location_name_snapshot, to_custodian_employee_key, to_custodian_code_snapshot, to_custodian_name_snapshot, reason, document_status, immutable_checksum, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'DRAFT\', ?, ?, ?)', [$movementKey, $companyKey, $companyKeyHash, $code, $type, $postingAt, $toLocationKey !== '' ? $toLocationKey : null, $destination['location_name'], $toEmployeeKey !== '' ? $toEmployeeKey : null, $destination['employee_code'], $destination['employee_name'], $reason, $checksum, $adminKey, $adminKey], 'Asset Movement create');
        }
        yovel_admin_assets_execute($db, 'DELETE FROM project_company_asset_movement_item WHERE company_key_hash = ? AND movement_key = ?', [$companyKeyHash, $movementKey], 'Asset Movement Item reset');
        foreach ($assetRows as $asset) {
            $itemChecksum = yovel_admin_assets_reference_checksum([$companyKeyHash, $movementKey, $asset['asset_key'], $asset['asset_code'], $asset['location_key'], $asset['custodian_employee_key'] ?? '']);
            yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_movement_item (movement_item_key, movement_key, company_key, company_key_hash, asset_key, asset_code_snapshot, from_location_key, from_location_name_snapshot, from_custodian_employee_key, from_custodian_code_snapshot, from_custodian_name_snapshot, immutable_checksum, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [bx_uuid(), $movementKey, $companyKey, $companyKeyHash, $asset['asset_key'], $asset['asset_code'], $asset['location_key'], $asset['location_name_snapshot'], ($asset['custodian_employee_key'] ?? '') !== '' ? $asset['custodian_employee_key'] : null, $asset['custodian_code_snapshot'], $asset['custodian_name_snapshot'], $itemChecksum, $adminKey], 'Asset Movement Item create');
        }
        $action = is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE';
        yovel_admin_assets_write_lifecycle_audit($db, $scope, 'MOVEMENT', $movementKey, $action, ['movement_code' => $code, 'posting_at' => $postingAt, 'asset_count' => count($assetRows)]);
        $saved = yovel_admin_assets_movement($company, $movementKey);
        if (!is_array($saved) || count($saved['items']) !== count($assetRows)) {
            throw new RuntimeException('Asset Movement read-back verification failed.');
        }
        yovel_admin_assets_assert_readback(['movement_key' => $movementKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'movement_code' => $code, 'movement_type' => $type, 'posting_at' => $postingAt, 'to_location_key' => $toLocationKey, 'to_custodian_employee_key' => $toEmployeeKey, 'reason' => $reason, 'document_status' => 'DRAFT', 'immutable_checksum' => $checksum, 'updated_by_admin_key' => $adminKey], array_map(static fn ($value) => $value ?? '', $saved), ['movement_key', 'company_key', 'company_key_hash', 'movement_code', 'movement_type', 'posting_at', 'to_location_key', 'to_custodian_employee_key', 'reason', 'document_status', 'immutable_checksum', 'updated_by_admin_key'], 'Asset Movement');
        return $saved;
    });
}

function yovel_admin_assets_submit_movement(array $company, array $admin, string $movementKey, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    yovel_admin_assets_dependency_services($services);
    $movementKey = yovel_admin_assets_local_key($movementKey, 'Movement key');

    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $movementKey): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $movement = $db->GetRow('SELECT * FROM project_company_asset_movement WHERE company_key_hash = ? AND movement_key = ? FOR UPDATE', [$companyKeyHash, $movementKey]);
        if (!is_array($movement) || $movement === [] || (string) $movement['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Only a draft Asset Movement can be submitted.');
        }
        $items = $db->GetAll('SELECT * FROM project_company_asset_movement_item WHERE company_key_hash = ? AND movement_key = ? ORDER BY x_id FOR UPDATE', [$companyKeyHash, $movementKey]);
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('Asset Movement has no items to submit.');
        }
        foreach ($items as $item) {
            $asset = $db->GetRow('SELECT * FROM project_company_asset WHERE company_key_hash = ? AND asset_key = ? FOR UPDATE', [$companyKeyHash, $item['asset_key']]);
            if (!is_array($asset) || $asset === [] || (string) $asset['document_status'] !== 'SUBMITTED'
                || (string) $asset['location_key'] !== (string) $item['from_location_key']
                || (string) ($asset['custodian_employee_key'] ?? '') !== (string) ($item['from_custodian_employee_key'] ?? '')) {
                throw new RuntimeException('Asset custody changed before Movement submission.');
            }
            $newLocationKey = (string) ($movement['to_location_key'] ?? '') !== '' ? (string) $movement['to_location_key'] : (string) $asset['location_key'];
            $newLocationName = (string) ($movement['to_location_key'] ?? '') !== '' ? (string) $movement['to_location_name_snapshot'] : (string) $asset['location_name_snapshot'];
            $newEmployeeKey = (string) ($movement['to_custodian_employee_key'] ?? '') !== '' ? (string) $movement['to_custodian_employee_key'] : (string) ($asset['custodian_employee_key'] ?? '');
            $newEmployeeCode = (string) ($movement['to_custodian_employee_key'] ?? '') !== '' ? (string) $movement['to_custodian_code_snapshot'] : (string) $asset['custodian_code_snapshot'];
            $newEmployeeName = (string) ($movement['to_custodian_employee_key'] ?? '') !== '' ? (string) $movement['to_custodian_name_snapshot'] : (string) $asset['custodian_name_snapshot'];
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset SET location_key = ?, location_name_snapshot = ?, custodian_employee_key = ?, custodian_code_snapshot = ?, custodian_name_snapshot = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND asset_key = ?', [$newLocationKey, $newLocationName, $newEmployeeKey !== '' ? $newEmployeeKey : null, $newEmployeeCode, $newEmployeeName, $adminKey, $companyKeyHash, $asset['asset_key']], 'Asset custody movement');
            yovel_admin_assets_write_activity($db, $scope, (string) $asset['asset_key'], 'MOVE', (string) $movement['posting_at'], $movementKey, ['from' => (string) $asset['location_key'], 'to' => $newLocationKey], ['from' => (string) ($asset['custodian_employee_key'] ?? ''), 'to' => $newEmployeeKey], ['movement_code' => (string) $movement['movement_code']]);
            $savedAsset = yovel_admin_assets_asset($company, (string) $asset['asset_key']);
            if (!is_array($savedAsset) || (string) $savedAsset['location_key'] !== $newLocationKey || (string) ($savedAsset['custodian_employee_key'] ?? '') !== $newEmployeeKey) {
                throw new RuntimeException('Asset Movement custody read-back verification failed.');
            }
        }
        yovel_admin_assets_fault('movement_after_asset_update');
        yovel_admin_assets_execute($db, "UPDATE project_company_asset_movement SET document_status = 'SUBMITTED', submitted_by_admin_key = ?, submitted_at = CURRENT_TIMESTAMP, updated_by_admin_key = ? WHERE company_key_hash = ? AND movement_key = ?", [$adminKey, $adminKey, $companyKeyHash, $movementKey], 'Asset Movement submit');
        yovel_admin_assets_write_lifecycle_audit($db, $scope, 'MOVEMENT', $movementKey, 'SUBMIT', ['movement_code' => (string) $movement['movement_code'], 'asset_count' => count($items)]);
        $saved = yovel_admin_assets_movement($company, $movementKey);
        if (!is_array($saved) || (string) $saved['document_status'] !== 'SUBMITTED' || (string) $saved['submitted_by_admin_key'] !== $adminKey) {
            throw new RuntimeException('Asset Movement submit read-back verification failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_cancel_movement(array $company, array $admin, string $movementKey, string $reason, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    yovel_admin_assets_dependency_services($services);
    $movementKey = yovel_admin_assets_local_key($movementKey, 'Movement key');
    $reason = yovel_admin_assets_text($reason, 'Movement cancellation reason', 500, true);

    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $movementKey, $reason): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $movement = $db->GetRow('SELECT * FROM project_company_asset_movement WHERE company_key_hash = ? AND movement_key = ? FOR UPDATE', [$companyKeyHash, $movementKey]);
        if (!is_array($movement) || $movement === [] || (string) $movement['document_status'] !== 'SUBMITTED') {
            throw new InvalidArgumentException('Only a submitted Asset Movement can be cancelled.');
        }
        $items = $db->GetAll('SELECT * FROM project_company_asset_movement_item WHERE company_key_hash = ? AND movement_key = ? ORDER BY x_id FOR UPDATE', [$companyKeyHash, $movementKey]);
        foreach ($items as $item) {
            $later = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_asset_movement later_movement INNER JOIN project_company_asset_movement_item later_item ON later_item.company_key_hash = later_movement.company_key_hash AND later_item.movement_key = later_movement.movement_key WHERE later_movement.company_key_hash = ? AND later_item.asset_key = ? AND later_movement.document_status = 'SUBMITTED' AND later_movement.posting_at > ?", [$companyKeyHash, $item['asset_key'], $movement['posting_at']]);
            if ($later > 0) {
                throw new InvalidArgumentException('Only the latest submitted Asset Movement can be cancelled.');
            }
            $asset = $db->GetRow('SELECT * FROM project_company_asset WHERE company_key_hash = ? AND asset_key = ? FOR UPDATE', [$companyKeyHash, $item['asset_key']]);
            if (!is_array($asset) || $asset === [] || (string) $asset['document_status'] !== 'SUBMITTED') {
                throw new RuntimeException('Movement Asset is unavailable for cancellation.');
            }
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset SET location_key = ?, location_name_snapshot = ?, custodian_employee_key = ?, custodian_code_snapshot = ?, custodian_name_snapshot = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND asset_key = ?', [$item['from_location_key'], $item['from_location_name_snapshot'], ($item['from_custodian_employee_key'] ?? '') !== '' ? $item['from_custodian_employee_key'] : null, $item['from_custodian_code_snapshot'], $item['from_custodian_name_snapshot'], $adminKey, $companyKeyHash, $item['asset_key']], 'Asset Movement custody restore');
            yovel_admin_assets_write_activity($db, $scope, (string) $item['asset_key'], 'MOVEMENT_CANCEL', date('Y-m-d H:i:s'), $movementKey, ['from' => (string) $asset['location_key'], 'to' => (string) $item['from_location_key']], ['from' => (string) ($asset['custodian_employee_key'] ?? ''), 'to' => (string) ($item['from_custodian_employee_key'] ?? '')], ['movement_code' => (string) $movement['movement_code'], 'reason' => $reason]);
            $restored = yovel_admin_assets_asset($company, (string) $item['asset_key']);
            if (!is_array($restored) || (string) $restored['location_key'] !== (string) $item['from_location_key'] || (string) ($restored['custodian_employee_key'] ?? '') !== (string) ($item['from_custodian_employee_key'] ?? '')) {
                throw new RuntimeException('Asset Movement restoration read-back verification failed.');
            }
        }
        yovel_admin_assets_execute($db, "UPDATE project_company_asset_movement SET document_status = 'CANCELLED', cancellation_reason = ?, cancelled_by_admin_key = ?, cancelled_at = CURRENT_TIMESTAMP, updated_by_admin_key = ? WHERE company_key_hash = ? AND movement_key = ?", [$reason, $adminKey, $adminKey, $companyKeyHash, $movementKey], 'Asset Movement cancel');
        yovel_admin_assets_write_lifecycle_audit($db, $scope, 'MOVEMENT', $movementKey, 'CANCEL', ['movement_code' => (string) $movement['movement_code'], 'reason' => $reason]);
        $saved = yovel_admin_assets_movement($company, $movementKey);
        if (!is_array($saved) || (string) $saved['document_status'] !== 'CANCELLED' || (string) $saved['cancellation_reason'] !== $reason || (string) $saved['cancelled_by_admin_key'] !== $adminKey) {
            throw new RuntimeException('Asset Movement cancellation read-back verification failed.');
        }
        return $saved;
    });
}
