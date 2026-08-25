<?php
declare(strict_types=1);

function yovel_admin_manufacturing_dependency_definitions(): array
{
    return [
        'item_lookup' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_inventory_item', 'signature' => 'yovel_admin_inventory_item(array $company, string $itemKey): ?array'],
        'item_uom_resolve' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_resolve_item_uom', 'signature' => 'yovel_admin_resolve_item_uom(array $company, string $itemKey, string $uomKey, string $qty): array'],
        'barcode_resolve' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_resolve_item_barcode', 'signature' => 'yovel_admin_resolve_item_barcode(array $company, string $barcode): ?array'],
        'warehouses' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_inventory_warehouses', 'signature' => 'yovel_admin_inventory_warehouses(array $company, array $filters = []): array'],
        'bin_snapshot' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_inventory_bin', 'signature' => 'yovel_admin_inventory_bin(array $company, string $itemKey, string $warehouseKey, array $dimensions = []): array'],
        'stock_snapshot' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_inventory_projection_for_warehouse_item', 'signature' => 'yovel_admin_inventory_projection_for_warehouse_item(array $company, string $itemKey, string $warehouseKey): array'],
        'item_valuation' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_inventory_item_valuation', 'signature' => 'yovel_admin_inventory_item_valuation(array $company, string $itemKey, ?string $warehouseKey = null, ?string $asOfDate = null): array'],
        'putaway_plan' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_putaway_plan', 'signature' => 'yovel_admin_putaway_plan(array $company, string $itemKey, string $qty, array $dimensions = []): array'],
        'reorder_recommendations' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_reorder_recommendations', 'signature' => 'yovel_admin_reorder_recommendations(array $company, ?string $warehouseKey = null): array'],
        'capacity_summary' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_inventory_warehouse_capacity_summary', 'signature' => 'yovel_admin_inventory_warehouse_capacity_summary(array $company): array'],
        'locked_bin' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_inventory_lock_bin', 'signature' => 'yovel_admin_inventory_lock_bin(ADOConnection $db, array $company, string $itemKey, string $warehouseKey, array $dimensions = []): array'],
        'stock_reservation' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_inventory_reserve_stock', 'signature' => 'yovel_admin_inventory_reserve_stock(array $company, array $admin, array $request): array'],
        'material_issue' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_inventory_issue_materials', 'signature' => 'yovel_admin_inventory_issue_materials(array $company, array $admin, array $request): array'],
        'finished_goods_receipt' => ['owner' => 'inventory-warehouse', 'owner_function' => 'yovel_admin_inventory_receive_finished_goods', 'signature' => 'yovel_admin_inventory_receive_finished_goods(array $company, array $admin, array $request): array'],
        'material_request' => ['owner' => 'buying-procurement', 'owner_function' => 'yovel_admin_buying_create_material_request', 'signature' => 'yovel_admin_buying_create_material_request(array $company, array $admin, array $request): array'],
        'supplier_lookup' => ['owner' => 'buying-procurement', 'owner_function' => 'yovel_admin_buying_supplier', 'signature' => 'yovel_admin_buying_supplier(array $company, string $supplierKey): ?array'],
        'account_validation' => ['owner' => 'accounting-finance', 'owner_function' => 'yovel_admin_finance_account_key', 'signature' => 'yovel_admin_finance_account_key(ADOConnection $db, string $companyKeyHash, mixed $value, string $label, array $rootTypes = [], array $accountTypes = []): ?string'],
        'cost_preview' => ['owner' => 'accounting-finance', 'owner_function' => 'yovel_admin_finance_manufacturing_cost_preview', 'signature' => 'yovel_admin_finance_manufacturing_cost_preview(array $company, array $request): array'],
        'posting_request' => ['owner' => 'accounting-finance', 'owner_function' => 'yovel_admin_finance_manufacturing_posting_request', 'signature' => 'yovel_admin_finance_manufacturing_posting_request(array $company, array $admin, array $request): array'],
    ];
}

function yovel_admin_manufacturing_dependency_gateway(array $overrides = []): array
{
    $definitions = yovel_admin_manufacturing_dependency_definitions();
    foreach ($overrides as $key => $callable) {
        if (!array_key_exists($key, $definitions)) {
            throw new InvalidArgumentException('Manufacturing dependency key is not allow-listed: ' . $key . '.');
        }
        if (!is_callable($callable)) {
            throw new InvalidArgumentException('Manufacturing dependency override must be callable: ' . $key . '.');
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

function yovel_admin_manufacturing_dependency_call(array $gateway, string $key, array $arguments): mixed
{
    if (!array_key_exists($key, yovel_admin_manufacturing_dependency_definitions())) {
        throw new InvalidArgumentException('Manufacturing dependency key is not allow-listed: ' . $key . '.');
    }
    $contract = $gateway[$key] ?? null;
    if (!is_array($contract) || empty($contract['available']) || !is_callable($contract['callable'] ?? null)) {
        throw new RuntimeException('Manufacturing ' . $key . ' owner service is unavailable.');
    }
    return ($contract['callable'])(...$arguments);
}
