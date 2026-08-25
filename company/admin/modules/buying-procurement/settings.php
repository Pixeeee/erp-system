<?php
declare(strict_types=1);

function yovel_admin_buying_settings_defaults(): array
{
    return [
        'buying_setting_key' => null,
        'supplier_naming_mode' => 'NAMING_SERIES',
        'default_supplier_group_key' => null,
        'default_buying_price_list_key' => null,
        'purchase_order_required' => 0,
        'purchase_receipt_required' => 0,
        'maintain_same_rate' => 0,
        'maintain_same_rate_action' => 'STOP',
        'over_order_allowance' => '0.0000',
        'over_transfer_allowance' => '0.0000',
        'allow_zero_qty_purchase_order' => 0,
        'allow_zero_qty_rfq' => 0,
        'allow_zero_qty_supplier_quotation' => 0,
        'setting_status' => 'ACTIVE',
        'created_by_admin_key' => null,
        'updated_by_admin_key' => null,
        'created_at' => null,
        'updated_at' => null,
    ];
}

function yovel_admin_buying_settings(array $company): array
{
    yovel_admin_buying_schema();
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    $row = bx_db()->GetRow(
        "SELECT buying_setting_key, supplier_naming_mode, default_supplier_group_key,
            default_buying_price_list_key, purchase_order_required, purchase_receipt_required,
            maintain_same_rate, maintain_same_rate_action, over_order_allowance,
            over_transfer_allowance, allow_zero_qty_purchase_order, allow_zero_qty_rfq,
            allow_zero_qty_supplier_quotation, setting_status, created_by_admin_key,
            updated_by_admin_key, created_at, updated_at
        FROM project_company_buying_setting
        WHERE company_key_hash = ?
        LIMIT 1",
        [$companyKeyHash]
    );

    $settings = is_array($row) && $row !== [] ? array_replace(yovel_admin_buying_settings_defaults(), $row) : yovel_admin_buying_settings_defaults();
    $settings['finance_defaults_available'] = false;
    $settings['finance_defaults'] = [];
    if (function_exists('yovel_admin_finance_buying_defaults')) {
        try {
            $financeDefaults = yovel_admin_finance_buying_defaults($company);
            if (is_array($financeDefaults)) {
                $settings['finance_defaults_available'] = true;
                $settings['finance_defaults'] = $financeDefaults;
            }
        } catch (Throwable $error) {
            $settings['finance_defaults_error'] = 'Finance defaults are temporarily unavailable.';
        }
    }

    return $settings;
}

function yovel_admin_buying_save_settings(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input
): array {
    yovel_admin_buying_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);
    $requestedKey = trim((string) ($input['buying_setting_key'] ?? ''));
    if ($requestedKey !== '' && !yovel_admin_is_uuid($requestedKey)) {
        throw new InvalidArgumentException('Buying Settings key is invalid.');
    }
    $namingMode = strtoupper(trim((string) ($input['supplier_naming_mode'] ?? 'NAMING_SERIES')));
    if (!in_array($namingMode, ['SUPPLIER_NAME', 'NAMING_SERIES', 'AUTO_NAME'], true)) {
        throw new InvalidArgumentException('Supplier naming mode is invalid.');
    }
    $supplierGroupKey = yovel_admin_buying_optional_uuid($input, 'default_supplier_group_key', 'Default supplier group');
    $priceListKey = yovel_admin_buying_optional_uuid($input, 'default_buying_price_list_key', 'Default buying price list');
    $sameRateAction = strtoupper(trim((string) ($input['maintain_same_rate_action'] ?? 'STOP')));
    if (!in_array($sameRateAction, ['STOP', 'WARN'], true)) {
        throw new InvalidArgumentException('Maintain-same-rate action is invalid.');
    }
    $values = [
        'supplier_naming_mode' => $namingMode,
        'default_supplier_group_key' => $supplierGroupKey,
        'default_buying_price_list_key' => $priceListKey,
        'purchase_order_required' => yovel_admin_buying_bool($input, 'purchase_order_required'),
        'purchase_receipt_required' => yovel_admin_buying_bool($input, 'purchase_receipt_required'),
        'maintain_same_rate' => yovel_admin_buying_bool($input, 'maintain_same_rate'),
        'maintain_same_rate_action' => $sameRateAction,
        'over_order_allowance' => yovel_admin_buying_allowance($input, 'over_order_allowance', 'Over-order allowance'),
        'over_transfer_allowance' => yovel_admin_buying_allowance($input, 'over_transfer_allowance', 'Over-transfer allowance'),
        'allow_zero_qty_purchase_order' => yovel_admin_buying_bool($input, 'allow_zero_qty_purchase_order'),
        'allow_zero_qty_rfq' => yovel_admin_buying_bool($input, 'allow_zero_qty_rfq'),
        'allow_zero_qty_supplier_quotation' => yovel_admin_buying_bool($input, 'allow_zero_qty_supplier_quotation'),
    ];

    return yovel_admin_buying_in_transaction($db, static function () use (
        $db,
        $companyKey,
        $companyKeyHash,
        $adminKey,
        $requestedKey,
        $values
    ): array {
        $existing = $db->GetRow(
            "SELECT buying_setting_key FROM project_company_buying_setting
            WHERE company_key_hash = ?
            FOR UPDATE",
            [$companyKeyHash]
        );
        $isUpdate = is_array($existing) && $existing !== [];
        if ($isUpdate && $requestedKey !== '' && (string) $existing['buying_setting_key'] !== $requestedKey) {
            throw new InvalidArgumentException('Buying Settings key does not belong to this company.');
        }
        if (!$isUpdate && $requestedKey !== '') {
            throw new InvalidArgumentException('Buying Settings record was not found for this company.');
        }
        $settingsKey = $isUpdate ? (string) $existing['buying_setting_key'] : bx_uuid();

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_buying_setting (
                buying_setting_key, company_key, company_key_hash, supplier_naming_mode,
                default_supplier_group_key, default_buying_price_list_key,
                purchase_order_required, purchase_receipt_required, maintain_same_rate,
                maintain_same_rate_action, over_order_allowance, over_transfer_allowance,
                allow_zero_qty_purchase_order, allow_zero_qty_rfq,
                allow_zero_qty_supplier_quotation, setting_status,
                created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)
            ON DUPLICATE KEY UPDATE
                supplier_naming_mode = VALUES(supplier_naming_mode),
                default_supplier_group_key = VALUES(default_supplier_group_key),
                default_buying_price_list_key = VALUES(default_buying_price_list_key),
                purchase_order_required = VALUES(purchase_order_required),
                purchase_receipt_required = VALUES(purchase_receipt_required),
                maintain_same_rate = VALUES(maintain_same_rate),
                maintain_same_rate_action = VALUES(maintain_same_rate_action),
                over_order_allowance = VALUES(over_order_allowance),
                over_transfer_allowance = VALUES(over_transfer_allowance),
                allow_zero_qty_purchase_order = VALUES(allow_zero_qty_purchase_order),
                allow_zero_qty_rfq = VALUES(allow_zero_qty_rfq),
                allow_zero_qty_supplier_quotation = VALUES(allow_zero_qty_supplier_quotation),
                setting_status = 'ACTIVE',
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $settingsKey,
                $companyKey,
                $companyKeyHash,
                $values['supplier_naming_mode'],
                $values['default_supplier_group_key'],
                $values['default_buying_price_list_key'],
                $values['purchase_order_required'],
                $values['purchase_receipt_required'],
                $values['maintain_same_rate'],
                $values['maintain_same_rate_action'],
                $values['over_order_allowance'],
                $values['over_transfer_allowance'],
                $values['allow_zero_qty_purchase_order'],
                $values['allow_zero_qty_rfq'],
                $values['allow_zero_qty_supplier_quotation'],
                $adminKey,
                $adminKey,
            ],
            'Buying Settings save'
        );

        $saved = $db->GetRow(
            "SELECT buying_setting_key, company_key, company_key_hash, supplier_naming_mode,
                default_supplier_group_key, default_buying_price_list_key,
                purchase_order_required, purchase_receipt_required, maintain_same_rate,
                maintain_same_rate_action, over_order_allowance, over_transfer_allowance,
                allow_zero_qty_purchase_order, allow_zero_qty_rfq,
                allow_zero_qty_supplier_quotation, setting_status, created_by_admin_key,
                updated_by_admin_key, created_at, updated_at
            FROM project_company_buying_setting
            WHERE company_key_hash = ? AND buying_setting_key = ?
            LIMIT 1",
            [$companyKeyHash, $settingsKey]
        );
        $exact = is_array($saved)
            && (string) ($saved['buying_setting_key'] ?? '') === $settingsKey
            && (string) ($saved['company_key'] ?? '') === $companyKey
            && (string) ($saved['company_key_hash'] ?? '') === $companyKeyHash
            && (string) ($saved['setting_status'] ?? '') === 'ACTIVE'
            && (string) ($saved['updated_by_admin_key'] ?? '') === $adminKey;
        foreach ($values as $key => $value) {
            if (!$exact || (string) ($saved[$key] ?? '') !== (string) ($value ?? '')) {
                $exact = false;
                break;
            }
        }
        if (!$exact) {
            throw new RuntimeException('Buying Settings read-back verification failed.');
        }

        bx_audit($isUpdate ? 'UPDATE' : 'CREATE', 'project_company_buying_setting', $settingsKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'supplier_naming_mode' => $values['supplier_naming_mode'],
            'admin_key' => $adminKey,
        ], 'Company administrator saved Buying Settings.');

        return array_replace(yovel_admin_buying_settings_defaults(), $saved, [
            'finance_defaults_available' => false,
            'finance_defaults' => [],
        ]);
    });
}
