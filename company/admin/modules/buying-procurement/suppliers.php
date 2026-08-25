<?php
declare(strict_types=1);

function yovel_admin_buying_customer_numbers(array $input): array
{
    $rows = $input['customer_numbers'] ?? [];
    if (!is_array($rows)) {
        $rows = [];
    }
    if ($rows === [] && trim((string) ($input['customer_numbers_text'] ?? '')) !== '') {
        foreach (preg_split('/\R/', (string) $input['customer_numbers_text']) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $rows[] = [
                'related_company_key' => $parts[0] ?? '',
                'customer_number' => $parts[1] ?? '',
            ];
        }
    }
    if (count($rows) > 50) {
        throw new InvalidArgumentException('A supplier cannot have more than 50 customer numbers.');
    }

    $normalized = [];
    $companyKeys = [];
    $customerNumbers = [];
    foreach (array_values($rows) as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Supplier customer-number rows are invalid.');
        }
        $companyKey = trim((string) ($row['related_company_key'] ?? ''));
        $customerNumber = trim((string) ($row['customer_number'] ?? ''));
        if ($companyKey === '' || strlen($companyKey) > 1500) {
            throw new InvalidArgumentException('Each customer number requires a valid related company key.');
        }
        if ($customerNumber === '' || strlen($customerNumber) > 120) {
            throw new InvalidArgumentException('Each customer number is required and cannot exceed 120 characters.');
        }
        $companyIdentity = strtolower($companyKey);
        $numberIdentity = strtolower($customerNumber);
        if (isset($companyKeys[$companyIdentity])) {
            throw new InvalidArgumentException('A duplicate company exists in the supplier customer numbers.');
        }
        if (isset($customerNumbers[$numberIdentity])) {
            throw new InvalidArgumentException('A duplicate customer number exists for this supplier.');
        }
        $companyKeys[$companyIdentity] = true;
        $customerNumbers[$numberIdentity] = true;
        $normalized[] = [
            'related_company_key' => $companyKey,
            'customer_number' => $customerNumber,
        ];
    }
    return $normalized;
}

function yovel_admin_buying_allowed_company_keys(array $input): array
{
    $values = $input['allowed_company_keys'] ?? [];
    if (!is_array($values)) {
        $values = [];
    }
    if ($values === [] && trim((string) ($input['allowed_company_keys_text'] ?? '')) !== '') {
        $values = preg_split('/[\r\n,]+/', (string) $input['allowed_company_keys_text']) ?: [];
    }
    $keys = [];
    foreach ($values as $value) {
        $key = trim((string) $value);
        if ($key === '') {
            continue;
        }
        if (strlen($key) > 1500) {
            throw new InvalidArgumentException('An allowed company key is too long.');
        }
        $keys[strtolower($key)] = $key;
    }
    if (count($keys) > 50) {
        throw new InvalidArgumentException('A supplier cannot have more than 50 allowed companies.');
    }
    return array_values($keys);
}

function yovel_admin_buying_active_companies(ADOConnection $db, array $companyKeys): array
{
    $companyKeys = array_values(array_unique(array_filter(array_map('strval', $companyKeys))));
    if ($companyKeys === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($companyKeys), '?'));
    $rows = $db->GetAll(
        "SELECT company_key, company_key_hash, company_name
        FROM project_company
        WHERE company_status = 'ACTIVE' AND company_key IN ({$placeholders})",
        $companyKeys
    );
    $indexed = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $indexed[(string) $row['company_key']] = $row;
    }
    foreach ($companyKeys as $companyKey) {
        if (!isset($indexed[$companyKey])) {
            throw new InvalidArgumentException('A selected supplier company is unavailable or inactive.');
        }
    }
    return $indexed;
}

function yovel_admin_buying_supplier_read(ADOConnection $db, string $companyKeyHash, string $supplierKey): ?array
{
    $supplier = $db->GetRow(
        "SELECT * FROM project_company_buying_supplier
        WHERE company_key_hash = ? AND supplier_key = ? AND supplier_status <> 'DELETED'
        LIMIT 1",
        [$companyKeyHash, $supplierKey]
    );
    if (!is_array($supplier) || $supplier === []) {
        return null;
    }
    $customerNumbers = $db->GetAll(
        "SELECT supplier_customer_number_key, related_company_key, customer_number, row_status,
            created_by_admin_key, updated_by_admin_key, created_at, updated_at
        FROM project_company_buying_supplier_customer_number
        WHERE company_key_hash = ? AND supplier_key = ? AND row_status = 'ACTIVE'
        ORDER BY related_company_key, customer_number",
        [$companyKeyHash, $supplierKey]
    );
    $allowedCompanies = $db->GetAll(
        "SELECT supplier_company_key, allowed_company_key, allowed_company_hash, row_status,
            created_by_admin_key, updated_by_admin_key, created_at, updated_at
        FROM project_company_buying_supplier_company
        WHERE company_key_hash = ? AND supplier_key = ? AND row_status = 'ACTIVE'
        ORDER BY allowed_company_key",
        [$companyKeyHash, $supplierKey]
    );
    $supplier['customer_numbers'] = is_array($customerNumbers) ? $customerNumbers : [];
    $supplier['allowed_companies'] = is_array($allowedCompanies) ? $allowedCompanies : [];
    return $supplier;
}

function yovel_admin_buying_supplier(array $company, string $supplierKey): ?array
{
    yovel_admin_buying_schema();
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    if (!yovel_admin_is_uuid($supplierKey)) {
        throw new InvalidArgumentException('Supplier key is invalid.');
    }
    return yovel_admin_buying_supplier_read(bx_db(), $companyKeyHash, strtolower($supplierKey));
}

function yovel_admin_buying_suppliers(array $company): array
{
    yovel_admin_buying_schema();
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    $rows = bx_db()->GetAll(
        "SELECT supplier_key FROM project_company_buying_supplier
        WHERE company_key_hash = ? AND supplier_status <> 'DELETED'
        ORDER BY supplier_name, supplier_code
        LIMIT 200",
        [$companyKeyHash]
    );
    $suppliers = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $supplier = yovel_admin_buying_supplier_read(bx_db(), $companyKeyHash, (string) $row['supplier_key']);
        if (is_array($supplier)) {
            $suppliers[] = $supplier;
        }
    }
    return $suppliers;
}

function yovel_admin_buying_supplier_series_code(
    ADOConnection $db,
    string $companyKey,
    string $companyKeyHash,
    string $adminKey
): string {
    $fiscalYear = (int) date('Y');
    $series = $db->GetRow(
        "SELECT number_series_key, next_number, padding
        FROM project_company_buying_number_series
        WHERE company_key_hash = ? AND series_code = 'SUPPLIER' AND fiscal_year = ?
        FOR UPDATE",
        [$companyKeyHash, $fiscalYear]
    );
    $seriesKey = is_array($series) && $series !== [] ? (string) $series['number_series_key'] : bx_uuid();
    $number = is_array($series) && $series !== [] ? (int) $series['next_number'] : 1;
    $padding = is_array($series) && $series !== [] ? (int) $series['padding'] : 5;
    if (is_array($series) && $series !== []) {
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_buying_number_series
            SET next_number = ?, prefix = 'SUP-', series_status = 'ACTIVE', updated_by_admin_key = ?
            WHERE company_key_hash = ? AND number_series_key = ?",
            [$number + 1, $adminKey, $companyKeyHash, $seriesKey],
            'Supplier number-series update'
        );
    } else {
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_buying_number_series (
                number_series_key, company_key, company_key_hash, series_code, fiscal_year,
                prefix, next_number, padding, series_status, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, 'SUPPLIER', ?, 'SUP-', ?, ?, 'ACTIVE', ?, ?)",
            [$seriesKey, $companyKey, $companyKeyHash, $fiscalYear, $number + 1, $padding, $adminKey, $adminKey],
            'Supplier number-series creation'
        );
    }
    $savedNext = (int) $db->GetOne(
        "SELECT next_number FROM project_company_buying_number_series
        WHERE company_key_hash = ? AND number_series_key = ?",
        [$companyKeyHash, $seriesKey]
    );
    if ($savedNext !== $number + 1) {
        throw new RuntimeException('Supplier number-series read-back verification failed.');
    }
    return 'SUP-' . $fiscalYear . '-' . str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
}

function yovel_admin_buying_child_write_hook(string $childType, int $index, array $row): void
{
    $hook = $GLOBALS['yovel_admin_buying_child_write_hook'] ?? null;
    if (is_callable($hook)) {
        $hook($childType, $index, $row);
    }
}

function yovel_admin_buying_save_supplier(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input
): array {
    yovel_admin_buying_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);
    $supplierKey = trim((string) ($input['supplier_key'] ?? ''));
    if ($supplierKey !== '' && !yovel_admin_is_uuid($supplierKey)) {
        throw new InvalidArgumentException('Supplier key is invalid.');
    }
    $supplierName = (string) yovel_admin_buying_text($input, 'supplier_name', 'Supplier name', 200, true);
    $requestedCode = yovel_admin_code((string) ($input['supplier_code'] ?? ''));
    if (strlen($requestedCode) > 80) {
        throw new InvalidArgumentException('Supplier code cannot exceed 80 characters.');
    }
    $supplierType = strtoupper(trim((string) ($input['supplier_type'] ?? 'COMPANY')));
    if (!in_array($supplierType, ['COMPANY', 'INDIVIDUAL', 'PARTNERSHIP'], true)) {
        throw new InvalidArgumentException('Supplier type is invalid.');
    }
    $currency = strtoupper((string) (yovel_admin_buying_text($input, 'default_currency', 'Default currency', 20) ?? ''));
    if ($currency !== '' && preg_match('/^[A-Z0-9._-]{2,20}$/', $currency) !== 1) {
        throw new InvalidArgumentException('Default currency is invalid.');
    }
    $email = yovel_admin_buying_text($input, 'email', 'Supplier email', 190);
    if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('Supplier email is invalid.');
    }
    $website = yovel_admin_buying_text($input, 'website', 'Supplier website', 220);
    if ($website !== null && filter_var($website, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('Supplier website is invalid.');
    }
    $customerNumbers = yovel_admin_buying_customer_numbers($input);
    $allowedCompanyKeys = yovel_admin_buying_allowed_company_keys($input);
    $isInternal = yovel_admin_buying_bool($input, 'is_internal_supplier');
    $representsCompanyKey = trim((string) ($input['represents_company_key'] ?? ''));
    if ($isInternal === 1 && ($representsCompanyKey === '' || $representsCompanyKey === $companyKey)) {
        throw new InvalidArgumentException('An internal supplier must represent another active company.');
    }
    if ($isInternal === 1 && $allowedCompanyKeys === []) {
        throw new InvalidArgumentException('An internal supplier requires at least one allowed company.');
    }
    if ($isInternal === 0) {
        $representsCompanyKey = '';
        $allowedCompanyKeys = [];
    }
    $companyReferences = array_merge(
        array_column($customerNumbers, 'related_company_key'),
        $allowedCompanyKeys,
        $representsCompanyKey !== '' ? [$representsCompanyKey] : []
    );
    $activeCompanies = yovel_admin_buying_active_companies($db, $companyReferences);
    if ($isInternal === 1 && in_array($representsCompanyKey, $allowedCompanyKeys, true)) {
        throw new InvalidArgumentException('The represented company cannot also be an allowed purchasing company.');
    }

    $values = [
        'supplier_name' => $supplierName,
        'supplier_type' => $supplierType,
        'supplier_group_key' => yovel_admin_buying_optional_uuid($input, 'supplier_group_key', 'Supplier group'),
        'country_key' => yovel_admin_buying_optional_uuid($input, 'country_key', 'Country'),
        'default_currency' => $currency !== '' ? $currency : null,
        'default_price_list_key' => yovel_admin_buying_optional_uuid($input, 'default_price_list_key', 'Default price list'),
        'payment_terms_key' => yovel_admin_buying_optional_uuid($input, 'payment_terms_key', 'Payment terms'),
        'tax_id' => yovel_admin_buying_text($input, 'tax_id', 'Tax ID', 80),
        'language_code' => yovel_admin_buying_text($input, 'language_code', 'Language code', 20),
        'email' => $email,
        'phone' => yovel_admin_buying_text($input, 'phone', 'Supplier phone', 80),
        'website' => $website,
        'supplier_details' => yovel_admin_buying_text($input, 'supplier_details', 'Supplier details', 10000),
        'is_transporter' => yovel_admin_buying_bool($input, 'is_transporter'),
        'is_internal_supplier' => $isInternal,
        'represents_company_key' => $representsCompanyKey !== '' ? $representsCompanyKey : null,
        'warn_rfqs' => max(yovel_admin_buying_bool($input, 'warn_rfqs'), yovel_admin_buying_bool($input, 'prevent_rfqs')),
        'warn_purchase_orders' => max(yovel_admin_buying_bool($input, 'warn_purchase_orders'), yovel_admin_buying_bool($input, 'prevent_purchase_orders')),
        'prevent_rfqs' => yovel_admin_buying_bool($input, 'prevent_rfqs'),
        'prevent_purchase_orders' => yovel_admin_buying_bool($input, 'prevent_purchase_orders'),
    ];
    $settings = yovel_admin_buying_settings($company);
    $namingMode = (string) $settings['supplier_naming_mode'];

    return yovel_admin_buying_in_transaction($db, static function () use (
        $db,
        $companyKey,
        $companyKeyHash,
        $adminKey,
        $supplierKey,
        $supplierName,
        $requestedCode,
        $namingMode,
        $values,
        $customerNumbers,
        $allowedCompanyKeys,
        $activeCompanies
    ): array {
        $existing = null;
        if ($supplierKey !== '') {
            $existing = $db->GetRow(
                "SELECT * FROM project_company_buying_supplier
                WHERE company_key_hash = ? AND supplier_key = ? AND supplier_status <> 'DELETED'
                FOR UPDATE",
                [$companyKeyHash, strtolower($supplierKey)]
            );
            if (!is_array($existing) || $existing === []) {
                throw new InvalidArgumentException('Supplier was not found for this company.');
            }
            if ((string) $existing['supplier_status'] === 'ARCHIVED') {
                throw new InvalidArgumentException('Archived suppliers are immutable.');
            }
        }
        $isUpdate = is_array($existing) && $existing !== [];
        $stableKey = $isUpdate ? (string) $existing['supplier_key'] : bx_uuid();
        if ($isUpdate) {
            $supplierCode = (string) $existing['supplier_code'];
        } elseif ($namingMode === 'SUPPLIER_NAME') {
            $supplierCode = yovel_admin_code($supplierName);
        } elseif ($requestedCode !== '') {
            $supplierCode = $requestedCode;
        } elseif ($namingMode === 'NAMING_SERIES') {
            $supplierCode = yovel_admin_buying_supplier_series_code($db, $companyKey, $companyKeyHash, $adminKey);
        } else {
            $supplierCode = 'SUP_' . strtoupper(substr(str_replace('-', '', $stableKey), 0, 12));
        }
        if ($supplierCode === '' || strlen($supplierCode) > 80) {
            throw new InvalidArgumentException('Supplier code is required and cannot exceed 80 characters.');
        }
        if (!$isUpdate) {
            $duplicate = $db->GetOne(
                "SELECT supplier_key FROM project_company_buying_supplier
                WHERE company_key_hash = ? AND supplier_code = ? AND supplier_status <> 'DELETED'
                FOR UPDATE",
                [$companyKeyHash, $supplierCode]
            );
            if (is_string($duplicate) && $duplicate !== '') {
                throw new InvalidArgumentException('Supplier code already exists for this company.');
            }
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_buying_supplier (
                supplier_key, company_key, company_key_hash, supplier_code, supplier_name,
                supplier_type, supplier_group_key, country_key, default_currency,
                default_price_list_key, payment_terms_key, tax_id, language_code, email,
                phone, website, supplier_details, is_transporter, is_internal_supplier,
                represents_company_key, on_hold, hold_type, release_date, warn_rfqs,
                warn_purchase_orders, prevent_rfqs, prevent_purchase_orders, supplier_status,
                created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL, ?, ?, ?, ?, 'ACTIVE', ?, ?)
            ON DUPLICATE KEY UPDATE
                supplier_name = VALUES(supplier_name),
                supplier_type = VALUES(supplier_type),
                supplier_group_key = VALUES(supplier_group_key),
                country_key = VALUES(country_key),
                default_currency = VALUES(default_currency),
                default_price_list_key = VALUES(default_price_list_key),
                payment_terms_key = VALUES(payment_terms_key),
                tax_id = VALUES(tax_id),
                language_code = VALUES(language_code),
                email = VALUES(email),
                phone = VALUES(phone),
                website = VALUES(website),
                supplier_details = VALUES(supplier_details),
                is_transporter = VALUES(is_transporter),
                is_internal_supplier = VALUES(is_internal_supplier),
                represents_company_key = VALUES(represents_company_key),
                warn_rfqs = VALUES(warn_rfqs),
                warn_purchase_orders = VALUES(warn_purchase_orders),
                prevent_rfqs = VALUES(prevent_rfqs),
                prevent_purchase_orders = VALUES(prevent_purchase_orders),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $stableKey,
                $companyKey,
                $companyKeyHash,
                $supplierCode,
                $values['supplier_name'],
                $values['supplier_type'],
                $values['supplier_group_key'],
                $values['country_key'],
                $values['default_currency'],
                $values['default_price_list_key'],
                $values['payment_terms_key'],
                $values['tax_id'],
                $values['language_code'],
                $values['email'],
                $values['phone'],
                $values['website'],
                $values['supplier_details'],
                $values['is_transporter'],
                $values['is_internal_supplier'],
                $values['represents_company_key'],
                $values['warn_rfqs'],
                $values['warn_purchase_orders'],
                $values['prevent_rfqs'],
                $values['prevent_purchase_orders'],
                $adminKey,
                $adminKey,
            ],
            'Supplier save'
        );

        yovel_admin_db_execute(
            $db,
            'DELETE FROM project_company_buying_supplier_customer_number WHERE company_key_hash = ? AND supplier_key = ?',
            [$companyKeyHash, $stableKey],
            'Supplier customer-number reset'
        );
        foreach ($customerNumbers as $index => $row) {
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_supplier_customer_number (
                    supplier_customer_number_key, company_key, company_key_hash, supplier_key,
                    related_company_key, customer_number, row_status,
                    created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                [bx_uuid(), $companyKey, $companyKeyHash, $stableKey, $row['related_company_key'], $row['customer_number'], $adminKey, $adminKey],
                'Supplier customer-number save'
            );
            yovel_admin_buying_child_write_hook('customer_number', $index, $row);
        }

        yovel_admin_db_execute(
            $db,
            'DELETE FROM project_company_buying_supplier_company WHERE company_key_hash = ? AND supplier_key = ?',
            [$companyKeyHash, $stableKey],
            'Supplier allowed-company reset'
        );
        foreach ($allowedCompanyKeys as $index => $allowedCompanyKey) {
            $allowedCompany = $activeCompanies[$allowedCompanyKey];
            $row = [
                'allowed_company_key' => $allowedCompanyKey,
                'allowed_company_hash' => (string) $allowedCompany['company_key_hash'],
            ];
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_supplier_company (
                    supplier_company_key, company_key, company_key_hash, supplier_key,
                    allowed_company_key, allowed_company_hash, row_status,
                    created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                [bx_uuid(), $companyKey, $companyKeyHash, $stableKey, $allowedCompanyKey, $row['allowed_company_hash'], $adminKey, $adminKey],
                'Supplier allowed-company save'
            );
            yovel_admin_buying_child_write_hook('allowed_company', $index, $row);
        }

        $saved = yovel_admin_buying_supplier_read($db, $companyKeyHash, $stableKey);
        $exact = is_array($saved)
            && (string) ($saved['supplier_key'] ?? '') === $stableKey
            && (string) ($saved['company_key'] ?? '') === $companyKey
            && (string) ($saved['company_key_hash'] ?? '') === $companyKeyHash
            && (string) ($saved['supplier_code'] ?? '') === $supplierCode
            && (string) ($saved['updated_by_admin_key'] ?? '') === $adminKey;
        foreach ($values as $key => $value) {
            if (!$exact || (string) ($saved[$key] ?? '') !== (string) ($value ?? '')) {
                $exact = false;
                break;
            }
        }
        $savedCustomerNumbers = array_map(
            static fn (array $row): array => ['related_company_key' => (string) $row['related_company_key'], 'customer_number' => (string) $row['customer_number']],
            is_array($saved['customer_numbers'] ?? null) ? $saved['customer_numbers'] : []
        );
        $expectedCustomerNumbers = $customerNumbers;
        usort($expectedCustomerNumbers, static fn (array $left, array $right): int => [$left['related_company_key'], $left['customer_number']] <=> [$right['related_company_key'], $right['customer_number']]);
        $savedAllowedKeys = array_map('strval', array_column(is_array($saved['allowed_companies'] ?? null) ? $saved['allowed_companies'] : [], 'allowed_company_key'));
        $expectedAllowedKeys = $allowedCompanyKeys;
        sort($savedAllowedKeys);
        sort($expectedAllowedKeys);
        if (!$exact || $savedCustomerNumbers !== $expectedCustomerNumbers || $savedAllowedKeys !== $expectedAllowedKeys) {
            throw new RuntimeException('Supplier read-back verification failed.');
        }

        bx_audit($isUpdate ? 'UPDATE' : 'CREATE', 'project_company_buying_supplier', $stableKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'supplier_code' => $supplierCode,
            'supplier_name' => $values['supplier_name'],
            'customer_number_count' => count($customerNumbers),
            'allowed_company_count' => count($allowedCompanyKeys),
            'admin_key' => $adminKey,
        ], 'Company administrator saved a supplier and its governance rows.');
        return $saved;
    });
}

function yovel_admin_buying_set_supplier_status(
    ADOConnection $db,
    array $company,
    array $admin,
    string $supplierKey,
    string $action,
    array $input
): array {
    yovel_admin_buying_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);
    if (!yovel_admin_is_uuid($supplierKey)) {
        throw new InvalidArgumentException('Supplier key is invalid.');
    }
    $action = strtoupper(trim($action));
    if (!in_array($action, ['HOLD', 'RELEASE', 'DISABLE', 'ACTIVATE', 'ARCHIVE'], true)) {
        throw new InvalidArgumentException('Supplier lifecycle action is invalid.');
    }
    $holdType = null;
    $releaseDate = null;
    if ($action === 'HOLD') {
        $holdType = strtoupper(trim((string) ($input['hold_type'] ?? '')));
        if (!in_array($holdType, ['ALL', 'INVOICES', 'PAYMENTS'], true)) {
            throw new InvalidArgumentException('Supplier hold type is invalid.');
        }
        $date = yovel_admin_optional_date((string) ($input['release_date'] ?? ''), 'Supplier release date');
        $releaseDate = $date !== '' ? $date : null;
    }

    return yovel_admin_buying_in_transaction($db, static function () use (
        $db,
        $companyKey,
        $companyKeyHash,
        $adminKey,
        $supplierKey,
        $action,
        $holdType,
        $releaseDate
    ): array {
        $existing = $db->GetRow(
            "SELECT supplier_key, supplier_code, supplier_name, supplier_status, on_hold, hold_type, release_date
            FROM project_company_buying_supplier
            WHERE company_key_hash = ? AND supplier_key = ? AND supplier_status <> 'DELETED'
            FOR UPDATE",
            [$companyKeyHash, strtolower($supplierKey)]
        );
        if (!is_array($existing) || $existing === []) {
            throw new InvalidArgumentException('Supplier was not found for this company.');
        }
        $currentStatus = (string) $existing['supplier_status'];
        $currentHold = (int) $existing['on_hold'];
        if ($currentStatus === 'ARCHIVED') {
            throw new InvalidArgumentException('Archived suppliers cannot be changed.');
        }

        $nextStatus = $currentStatus;
        $nextHold = $currentHold;
        $nextHoldType = $existing['hold_type'];
        $nextReleaseDate = $existing['release_date'];
        if ($action === 'HOLD') {
            if ($currentStatus !== 'ACTIVE' || $currentHold === 1) {
                throw new InvalidArgumentException('Only an active, released supplier can be placed on hold.');
            }
            $nextHold = 1;
            $nextHoldType = $holdType;
            $nextReleaseDate = $releaseDate;
        } elseif ($action === 'RELEASE') {
            if ($currentStatus !== 'ACTIVE' || $currentHold !== 1) {
                throw new InvalidArgumentException('Only an active supplier on hold can be released.');
            }
            $nextHold = 0;
            $nextHoldType = null;
            $nextReleaseDate = null;
        } elseif ($action === 'DISABLE') {
            if ($currentStatus !== 'ACTIVE') {
                throw new InvalidArgumentException('Only an active supplier can be disabled.');
            }
            $nextStatus = 'INACTIVE';
            $nextHold = 0;
            $nextHoldType = null;
            $nextReleaseDate = null;
        } elseif ($action === 'ACTIVATE') {
            if ($currentStatus !== 'INACTIVE') {
                throw new InvalidArgumentException('Only an inactive supplier can be activated.');
            }
            $nextStatus = 'ACTIVE';
        } elseif ($action === 'ARCHIVE') {
            if (!in_array($currentStatus, ['ACTIVE', 'INACTIVE'], true)) {
                throw new InvalidArgumentException('Only an active or inactive supplier can be archived.');
            }
            $nextStatus = 'ARCHIVED';
            $nextHold = 0;
            $nextHoldType = null;
            $nextReleaseDate = null;
        }

        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_buying_supplier
            SET supplier_status = ?, on_hold = ?, hold_type = ?, release_date = ?, updated_by_admin_key = ?
            WHERE company_key_hash = ? AND supplier_key = ?",
            [$nextStatus, $nextHold, $nextHoldType, $nextReleaseDate, $adminKey, $companyKeyHash, strtolower($supplierKey)],
            'Supplier lifecycle update'
        );
        $saved = yovel_admin_buying_supplier_read($db, $companyKeyHash, strtolower($supplierKey));
        if (!is_array($saved)
            || (string) ($saved['supplier_status'] ?? '') !== $nextStatus
            || (int) ($saved['on_hold'] ?? -1) !== $nextHold
            || (string) ($saved['hold_type'] ?? '') !== (string) ($nextHoldType ?? '')
            || (string) ($saved['release_date'] ?? '') !== (string) ($nextReleaseDate ?? '')
            || (string) ($saved['updated_by_admin_key'] ?? '') !== $adminKey) {
            throw new RuntimeException('Supplier lifecycle read-back verification failed.');
        }
        bx_audit($action, 'project_company_buying_supplier', strtolower($supplierKey), [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'supplier_code' => (string) $existing['supplier_code'],
            'supplier_status' => $nextStatus,
            'on_hold' => $nextHold,
            'hold_type' => $nextHoldType,
            'release_date' => $nextReleaseDate,
            'admin_key' => $adminKey,
        ], 'Company administrator changed supplier lifecycle governance.');
        return $saved;
    });
}
