<?php
declare(strict_types=1);

function yovel_admin_inventory_dashboard_try(callable $read): array
{
    try {
        $value = $read();
        return ['value' => is_array($value) ? $value : [], 'error' => null];
    } catch (Throwable $error) {
        error_log('Inventory dashboard read failed: ' . $error->getMessage());
        return ['value' => [], 'error' => 'Inventory data is temporarily unavailable.'];
    }
}

function yovel_admin_inventory_dashboard_activity(array $company, int $limit = 12): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if ($companyKey === '' || preg_match('/^[a-f0-9]{64}$/', $companyHash) !== 1) {
        return [];
    }
    $limit = max(1, min(12, $limit));
    $rows = bx_db()->GetAll(
        "SELECT audit_key,action,module,record_key,new_values,created_at
         FROM builder_audit_log
         WHERE module IN (
             'project_company_inventory_item','project_company_inventory_item_price',
             'project_company_inventory_warehouse','project_company_inventory_warehouse_type',
             'project_company_inventory_dimension','project_company_inventory_stock_setting',
             'project_company_inventory_putaway_rule','project_company_inventory_reorder_rule',
             'project_company_inventory_bin_source','project_company_inventory_form_schema'
         )
           AND JSON_VALID(new_values)
           AND JSON_UNQUOTE(JSON_EXTRACT(new_values,'$.company_key'))=?
         ORDER BY x_id DESC LIMIT 12",
        [$companyKey]
    );
    $admins = bx_db()->GetAll(
        "SELECT admin_key,admin_name FROM project_company_admin
         WHERE company_key_hash=? AND admin_status='ACTIVE' ORDER BY x_id",
        [$companyHash]
    );
    if (!is_array($rows) || !is_array($admins)) {
        throw new RuntimeException('Inventory activity is unavailable.');
    }
    $actorLabels = [];
    foreach (is_array($admins) ? $admins : [] as $admin) {
        $actorLabels[(string) $admin['admin_key']] = (string) $admin['admin_name'];
    }

    $activity = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $values = [];
        if (trim((string) ($row['new_values'] ?? '')) !== '') {
            try {
                $decoded = json_decode((string) $row['new_values'], true, 64, JSON_THROW_ON_ERROR);
                $values = is_array($decoded) ? $decoded : [];
            } catch (JsonException) {
                continue;
            }
        }
        if ((string) ($values['company_key'] ?? '') !== $companyKey) {
            continue;
        }
        $label = '';
        foreach (['item_code', 'warehouse_code', 'warehouse_type_code', 'dimension_code', 'price_list_code', 'source_key', 'record_type'] as $candidate) {
            if (trim((string) ($values[$candidate] ?? '')) !== '') {
                $label = (string) $values[$candidate];
                break;
            }
        }
        if ($label === '') {
            $label = str_replace(['project_company_inventory_', '_'], ['', ' '], (string) $row['module']);
        }
        $adminKey = (string) ($values['admin_key'] ?? '');
        $activity[] = [
            'key' => 'audit-' . (string) $row['audit_key'],
            'action' => strtoupper((string) $row['action']),
            'record_label' => $label,
            'actor_label' => (string) ($actorLabels[$adminKey] ?? 'Company administrator'),
            'status' => 'RECORDED',
            'occurred_at' => (string) $row['created_at'],
        ];
        if (count($activity) >= $limit) {
            break;
        }
    }
    return $activity;
}

function yovel_admin_inventory_dashboard_form_builder_count(string $companyHash): int
{
    $count = bx_db()->GetOne(
        "SELECT COUNT(*) FROM project_company_inventory_form_schema
         WHERE company_key_hash=? AND schema_status='ACTIVE'",
        [$companyHash]
    );
    if ($count === false) {
        throw new RuntimeException('Inventory Form Builder status is unavailable.');
    }
    return (int) $count;
}

function yovel_admin_inventory_warehouse_dashboard_data(array $company, array $admin): array
{
    [, $companyHash] = yovel_admin_inventory_scope($company, $admin);

    $itemsRead = yovel_admin_inventory_dashboard_try(static fn (): array => yovel_admin_inventory_items($company, ['status' => 'ACTIVE']));
    $itemCountRead = yovel_admin_inventory_dashboard_try(static fn (): array => ['count' => yovel_admin_inventory_item_count($company, 'ACTIVE')]);
    $warehousesRead = yovel_admin_inventory_dashboard_try(static fn (): array => yovel_admin_inventory_warehouses($company, ['status' => 'ACTIVE', 'leaf_only' => true]));
    $reorderRead = yovel_admin_inventory_dashboard_try(static fn (): array => yovel_admin_reorder_recommendations($company));
    $reorderRulesRead = yovel_admin_inventory_dashboard_try(static fn (): array => yovel_admin_inventory_reorder_rules($company));
    $shortageRead = yovel_admin_inventory_dashboard_try(static fn (): array => yovel_admin_inventory_projected_shortages($company, 20));
    $shortageCountRead = yovel_admin_inventory_dashboard_try(static fn (): array => ['count' => yovel_admin_inventory_projected_shortage_count($company)]);
    $capacityRead = yovel_admin_inventory_dashboard_try(static fn (): array => yovel_admin_inventory_warehouse_capacity_summary($company));
    $putawayRead = yovel_admin_inventory_dashboard_try(static fn (): array => yovel_admin_inventory_putaway_rules($company));
    $settingsRead = yovel_admin_inventory_dashboard_try(static fn (): array => yovel_admin_inventory_settings($company));
    $activityRead = yovel_admin_inventory_dashboard_try(static fn (): array => yovel_admin_inventory_dashboard_activity($company));
    $formBuilderRead = yovel_admin_inventory_dashboard_try(static fn (): array => ['count' => yovel_admin_inventory_dashboard_form_builder_count($companyHash)]);

    $items = $itemsRead['value'];
    $warehouses = array_values(array_filter(
        $warehousesRead['value'],
        static fn (array $warehouse): bool => (string) ($warehouse['warehouse_status'] ?? '') === 'ACTIVE' && (int) ($warehouse['is_group'] ?? 0) === 0
    ));
    $reorder = $reorderRead['value'];
    $shortages = $shortageRead['value'];
    $putaway = array_values(array_filter($putawayRead['value'], static fn (array $rule): bool => (string) ($rule['putaway_rule_status'] ?? '') === 'ACTIVE'));
    $capacityAlerts = [];
    foreach ($capacityRead['value'] as $warehouse) {
        if ((string) ($warehouse['warehouse_status'] ?? '') !== 'ACTIVE' || (int) ($warehouse['is_group'] ?? 0) === 1) {
            continue;
        }
        $capacity = yovel_admin_inventory_decimal((string) ($warehouse['capacity_qty'] ?? '0'));
        if (bccomp($capacity, '0', 9) !== 1) {
            continue;
        }
        $used = yovel_admin_inventory_decimal((string) ($warehouse['capacity_used'] ?? '0'));
        $ratio = bcdiv($used, $capacity, 4);
        if (bccomp($ratio, '0.9000', 4) >= 0) {
            $capacityAlerts[] = $warehouse + [
                'capacity_ratio' => $ratio,
                'severity' => bccomp($used, $capacity, 9) === 1 ? 'CRITICAL' : 'WARNING',
            ];
        }
    }

    $summary = [
        ['key' => 'active-items', 'label' => 'Active items', 'value' => $itemCountRead['error'] === null ? (int) ($itemCountRead['value']['count'] ?? 0) : null, 'availability' => $itemCountRead['error'] === null ? 'AVAILABLE' : 'ERROR', 'href' => './?view=inventory-warehouse&section=items'],
        ['key' => 'active-warehouses', 'label' => 'Active warehouses', 'value' => $warehousesRead['error'] === null ? count($warehouses) : null, 'availability' => $warehousesRead['error'] === null ? 'AVAILABLE' : 'ERROR', 'href' => './?view=inventory-warehouse&section=warehouses'],
        ['key' => 'reorder-actions', 'label' => 'Reorder actions', 'value' => $reorderRead['error'] === null ? count($reorder) : null, 'availability' => $reorderRead['error'] === null ? 'AVAILABLE' : 'ERROR', 'href' => './?view=inventory-warehouse&section=reorder-levels'],
        ['key' => 'projected-shortages', 'label' => 'Projected shortages', 'value' => $shortageCountRead['error'] === null ? (int) ($shortageCountRead['value']['count'] ?? 0) : null, 'availability' => $shortageCountRead['error'] === null ? 'AVAILABLE' : 'ERROR', 'href' => './?view=inventory-warehouse&section=reorder-levels'],
        ['key' => 'capacity-alerts', 'label' => 'Capacity alerts', 'value' => $capacityRead['error'] === null ? count($capacityAlerts) : null, 'availability' => $capacityRead['error'] === null ? 'AVAILABLE' : 'ERROR', 'href' => './?view=inventory-warehouse&section=warehouses'],
        ['key' => 'stock-value', 'label' => 'Stock value', 'value' => null, 'availability' => 'UNAVAILABLE_DEPENDENCY', 'href' => './?view=inventory-warehouse&section=stock-ledger'],
    ];

    $queue = [];
    foreach (array_slice($reorder, 0, 8) as $row) {
        $queue[] = [
            'key' => 'reorder-' . (string) $row['item_key'] . '-' . (string) $row['warehouse_key'],
            'label' => (string) $row['item_code'] . ' at ' . (string) $row['warehouse_code'],
            'detail' => 'Request ' . (string) $row['recommended_quantity'] . ' ' . (string) ($row['stock_uom_code'] ?? 'units'),
            'status' => 'OPEN', 'kind' => 'REORDER',
            'href' => './?view=inventory-warehouse&section=reorder-levels',
        ];
    }
    foreach (array_slice($shortages, 0, 8) as $row) {
        $queue[] = [
            'key' => 'shortage-' . (string) $row['item_key'] . '-' . (string) $row['warehouse_key'],
            'label' => (string) $row['item_code'] . ' projected below zero',
            'detail' => (string) $row['projected'] . ' at ' . (string) $row['warehouse_code'],
            'status' => 'CRITICAL', 'kind' => 'SHORTAGE',
            'href' => './?view=inventory-warehouse&section=reorder-levels',
        ];
    }
    foreach (array_slice($capacityAlerts, 0, 8) as $row) {
        $queue[] = [
            'key' => 'capacity-' . (string) $row['warehouse_key'],
            'label' => (string) $row['warehouse_code'] . ' nearing capacity',
            'detail' => (string) $row['capacity_used'] . ' of ' . (string) $row['capacity_qty'],
            'status' => (string) $row['severity'], 'kind' => 'CAPACITY',
            'href' => './?view=inventory-warehouse&section=warehouses',
        ];
    }
    if ($items !== [] && $warehouses !== [] && $putaway === [] && $putawayRead['error'] === null) {
        $queue[] = ['key' => 'putaway-setup', 'label' => 'Configure putaway priorities', 'detail' => 'No active Putaway Rules cover stocked items.', 'status' => 'SETUP', 'kind' => 'PUTAWAY', 'href' => './?view=inventory-warehouse&section=putaway'];
    }

    $settings = $settingsRead['value'];
    $formBuilderCount = (int) ($formBuilderRead['value']['count'] ?? 0);
    $setup = [
        ['key' => 'items-ready', 'label' => 'Create an active stock item', 'complete' => $items !== [], 'href' => './?view=inventory-warehouse&section=items'],
        ['key' => 'warehouses-ready', 'label' => 'Create an active stock warehouse', 'complete' => $warehouses !== [], 'href' => './?view=inventory-warehouse&section=warehouses'],
        ['key' => 'stock-settings-ready', 'label' => 'Review Stock Settings', 'complete' => trim((string) ($settings['stock_setting_key'] ?? '')) !== '', 'href' => './?view=inventory-warehouse&section=warehouses'],
        ['key' => 'putaway-ready', 'label' => 'Set Putaway Rules', 'complete' => $putaway !== [], 'href' => './?view=inventory-warehouse&section=putaway'],
        ['key' => 'reorder-ready', 'label' => 'Set reorder levels', 'complete' => $reorderRulesRead['error'] === null && $reorderRulesRead['value'] !== [], 'href' => './?view=inventory-warehouse&section=reorder-levels'],
        ['key' => 'form-builder-ready', 'label' => 'Review Inventory forms', 'complete' => $formBuilderRead['error'] === null && $formBuilderCount > 0, 'href' => './?view=inventory-warehouse&section=form-builder'],
    ];

    $alerts = [];
    foreach ($capacityAlerts as $row) {
        $alerts[] = ['key' => 'capacity-' . (string) $row['warehouse_key'], 'severity' => (string) $row['severity'], 'label' => (string) $row['warehouse_code'] . ' is at ' . bcmul((string) $row['capacity_ratio'], '100', 1) . '% capacity', 'href' => './?view=inventory-warehouse&section=warehouses'];
    }
    foreach (array_slice($shortages, 0, 5) as $row) {
        $alerts[] = ['key' => 'shortage-' . (string) $row['item_key'] . '-' . (string) $row['warehouse_key'], 'severity' => 'CRITICAL', 'label' => (string) $row['item_code'] . ' has projected quantity ' . (string) $row['projected'], 'href' => './?view=inventory-warehouse&section=reorder-levels'];
    }
    foreach ([$itemsRead, $itemCountRead, $warehousesRead, $reorderRead, $reorderRulesRead, $shortageRead, $shortageCountRead, $capacityRead, $putawayRead, $settingsRead, $activityRead, $formBuilderRead] as $index => $read) {
        if ($read['error'] !== null) {
            $alerts[] = ['key' => 'read-error-' . $index, 'severity' => 'ERROR', 'label' => (string) $read['error'], 'href' => './?view=inventory-warehouse&section=dashboard'];
        }
    }
    $alerts[] = ['key' => 'valuation-unavailable', 'severity' => 'INFO', 'label' => 'Stock value awaits the authoritative Inventory valuation service.', 'href' => './?view=inventory-warehouse&section=stock-ledger'];

    $shortcuts = [
        ['key' => 'items', 'label' => 'Items', 'href' => './?view=inventory-warehouse&section=items', 'available' => true],
        ['key' => 'warehouses', 'label' => 'Warehouses', 'href' => './?view=inventory-warehouse&section=warehouses', 'available' => true],
        ['key' => 'reorder-levels', 'label' => 'Reorder levels', 'href' => './?view=inventory-warehouse&section=reorder-levels', 'available' => true],
        ['key' => 'putaway', 'label' => 'Putaway', 'href' => './?view=inventory-warehouse&section=putaway', 'available' => true],
        ['key' => 'form-builder', 'label' => 'Form Builder', 'href' => './?view=inventory-warehouse&section=form-builder', 'available' => true],
    ];
    $directories = [
        ['group' => 'Operations', 'items' => [
            ['key' => 'directory-items', 'label' => 'Item catalogue', 'href' => './?view=inventory-warehouse&section=items', 'availability' => 'AVAILABLE'],
            ['key' => 'directory-warehouses', 'label' => 'Warehouse directory', 'href' => './?view=inventory-warehouse&section=warehouses', 'availability' => 'AVAILABLE'],
            ['key' => 'directory-reorder', 'label' => 'Replenishment controls', 'href' => './?view=inventory-warehouse&section=reorder-levels', 'availability' => 'AVAILABLE'],
            ['key' => 'directory-putaway', 'label' => 'Putaway rules', 'href' => './?view=inventory-warehouse&section=putaway', 'availability' => 'AVAILABLE'],
        ]],
        ['group' => 'Reports', 'items' => [
            ['key' => 'directory-price-stock', 'label' => 'Item Price Stock', 'href' => './?view=inventory-warehouse&section=items', 'availability' => 'AVAILABLE'],
            ['key' => 'directory-projected', 'label' => 'Projected quantity', 'href' => './?view=inventory-warehouse&section=reorder-levels', 'availability' => 'AVAILABLE'],
            ['key' => 'directory-capacity', 'label' => 'Warehouse capacity', 'href' => './?view=inventory-warehouse&section=warehouses', 'availability' => 'AVAILABLE'],
            ['key' => 'directory-stock-value', 'label' => 'Stock value', 'href' => './?view=inventory-warehouse&section=stock-ledger', 'availability' => 'UNAVAILABLE_DEPENDENCY'],
        ]],
        ['group' => 'Masters', 'items' => [
            ['key' => 'directory-item-master', 'label' => 'Items and UOM', 'href' => './?view=inventory-warehouse&section=items', 'availability' => 'AVAILABLE'],
            ['key' => 'directory-warehouse-master', 'label' => 'Warehouses, types, and dimensions', 'href' => './?view=inventory-warehouse&section=warehouses', 'availability' => 'AVAILABLE'],
            ['key' => 'directory-forms', 'label' => 'Inventory Form Builder', 'href' => './?view=inventory-warehouse&section=form-builder', 'availability' => 'AVAILABLE'],
        ]],
    ];
    $dependencies = [
        ['key' => 'inventory-catalogue', 'contract' => 'inventory.catalogue.v1', 'status' => $itemsRead['error'] === null && $itemCountRead['error'] === null ? 'AVAILABLE' : 'ERROR'],
        ['key' => 'inventory-warehouse-bin', 'contract' => 'inventory.warehouse-bin.v1', 'status' => $warehousesRead['error'] === null && $capacityRead['error'] === null && $shortageRead['error'] === null && $shortageCountRead['error'] === null ? 'AVAILABLE' : 'ERROR'],
        ['key' => 'inventory-valuation', 'contract' => 'inventory.valuation.v1', 'status' => 'UNAVAILABLE_DEPENDENCY'],
    ];

    return [
        'summary' => $summary,
        'queue' => array_slice($queue, 0, 20),
        'activity' => $activityRead['value'],
        'setup' => $setup,
        'alerts' => array_slice($alerts, 0, 16),
        'shortcuts' => $shortcuts,
        'directories' => $directories,
        'dependencies' => $dependencies,
    ];
}
