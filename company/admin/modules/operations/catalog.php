<?php
declare(strict_types=1);

function yovel_admin_operations_catalog_empty_directories(): array
{
    return [
        'brand' => [
            'label' => 'Brand', 'status' => 'UNAVAILABLE_DEPENDENCY', 'blocking' => false, 'records' => [],
            'unavailable_fields' => ['brand_key', 'brand_code', 'brand_name', 'description', 'brand_status'],
            'reason' => 'Inventory exposes item manufacturers, but no verified Brand directory. Operations does not treat Manufacturer as Brand.',
        ],
        'item_group' => [
            'label' => 'Item Group', 'status' => 'UNAVAILABLE_DEPENDENCY', 'blocking' => false, 'records' => [],
            'unavailable_fields' => ['item_group_key', 'item_group_code', 'item_group_name', 'parent_item_group_key', 'is_group', 'item_group_status'],
            'reason' => 'Inventory exposes no verified Item Group hierarchy callable. Warehouse and item labels are not substitutes.',
        ],
        'uom' => [
            'label' => 'UOM', 'status' => 'UNAVAILABLE_DEPENDENCY', 'blocking' => false, 'records' => [],
            'unavailable_fields' => ['uom_key', 'uom_code', 'uom_name', 'uom_category', 'uom_status', 'must_be_whole_number'],
            'reason' => 'Inventory UOM owner services are unavailable or invalid.',
        ],
        'uom_conversion_factor' => [
            'label' => 'UOM Conversion Factor', 'status' => 'UNAVAILABLE_DEPENDENCY', 'blocking' => false, 'records' => [],
            'unavailable_fields' => ['item_uom_key', 'item_key', 'uom_key', 'from_uom_code', 'to_uom_code', 'uom_category', 'conversion_factor', 'is_stock_uom'],
            'reason' => 'Inventory UOM conversion owner services are unavailable or invalid.',
        ],
    ];
}

function yovel_admin_operations_catalog_source(string $owner, string $service, string $status, string $reason = ''): array
{
    return [
        'owner' => $owner,
        'service' => $service,
        'status' => $status,
        'blocking' => false,
        'reason' => $reason,
    ];
}

function yovel_admin_operations_catalog_text(mixed $value, string $field, int $maximum, bool $required = true): string
{
    $value = trim((string) $value);
    if (($required && $value === '') || strlen($value) > $maximum) {
        throw new RuntimeException('Inventory owner response contains an invalid ' . $field . '.');
    }
    return $value;
}

function yovel_admin_operations_catalog_uuid(mixed $value, string $field, bool $required = true): string
{
    $value = yovel_admin_operations_catalog_text($value, $field, 64, $required);
    if ($value !== '' && (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($value))) {
        throw new RuntimeException('Inventory owner response contains an invalid ' . $field . '.');
    }
    return $value;
}

function yovel_admin_operations_catalog_company_row(array $record, string $companyKeyHash, string $label): void
{
    $recordHash = strtolower(trim((string) ($record['company_key_hash'] ?? '')));
    if (!hash_equals($companyKeyHash, $recordHash)) {
        throw new RuntimeException('Inventory ' . $label . ' response changed company scope.');
    }
}

function yovel_admin_operations_catalog_status(mixed $value, string $field): string
{
    $status = strtoupper(yovel_admin_operations_catalog_text($value, $field, 20));
    if (!in_array($status, ['ACTIVE', 'DISABLED'], true)) {
        throw new RuntimeException('Inventory owner response contains an invalid ' . $field . '.');
    }
    return $status;
}

function yovel_admin_operations_catalog_flag(mixed $value, string $field): int
{
    if (!in_array($value, [0, 1, '0', '1'], true)) {
        throw new RuntimeException('Inventory owner response contains an invalid ' . $field . '.');
    }
    return (int) $value;
}

function yovel_admin_operations_catalog_factor(mixed $value): string
{
    $factor = yovel_admin_operations_catalog_text($value, 'conversion factor', 40);
    if (preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,9})?$/', $factor) !== 1
        || (function_exists('bccomp') ? bccomp($factor, '0', 9) <= 0 : (float) $factor <= 0)) {
        throw new RuntimeException('Inventory owner response contains an invalid conversion factor.');
    }
    return $factor;
}

function yovel_admin_operations_catalog_owner_actions(): array
{
    return [
        ['label' => 'Open Inventory items', 'href' => '?view=inventory-warehouse&section=items'],
        ['label' => 'Open Inventory warehouses', 'href' => '?view=inventory-warehouse&section=warehouses'],
    ];
}

function yovel_admin_operations_catalog_projection(array $company): array
{
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    $directories = yovel_admin_operations_catalog_empty_directories();
    $sources = [
        'brand_directory' => yovel_admin_operations_catalog_source('Inventory', 'inventory-warehouse.catalog-directory.v1', 'UNAVAILABLE_DEPENDENCY', 'No verified Brand directory callable is available.'),
        'item_groups' => yovel_admin_operations_catalog_source('Inventory', 'inventory-warehouse.catalog-directory.v1', 'UNAVAILABLE_DEPENDENCY', 'No verified Item Group hierarchy callable is available.'),
    ];
    $contexts = ['warehouses' => []];

    if (function_exists('yovel_admin_inventory_items') && function_exists('yovel_admin_inventory_item')) {
        try {
            $items = yovel_admin_inventory_items($company, []);
            if (!is_array($items) || count($items) > 200) {
                throw new RuntimeException('Inventory item directory response is invalid.');
            }
            $uoms = [];
            $uomCodes = [];
            $conversions = [];
            $conversionPairs = [];
            $conversionKeys = [];
            foreach (array_values($items) as $item) {
                if (!is_array($item)) {
                    throw new RuntimeException('Inventory item directory contains an invalid record.');
                }
                yovel_admin_operations_catalog_company_row($item, $companyKeyHash, 'item directory');
                $itemKey = yovel_admin_operations_catalog_uuid($item['item_key'] ?? '', 'item key');
                $stockUomCode = strtoupper(yovel_admin_operations_catalog_text($item['stock_uom_code'] ?? '', 'stock UOM code', 40));
                yovel_admin_operations_catalog_status($item['item_status'] ?? '', 'item status');
                $detail = yovel_admin_inventory_item($company, $itemKey);
                if (!is_array($detail)) {
                    throw new RuntimeException('Inventory item detail response is unavailable.');
                }
                yovel_admin_operations_catalog_company_row($detail, $companyKeyHash, 'item detail');
                if (!hash_equals($itemKey, yovel_admin_operations_catalog_uuid($detail['item_key'] ?? '', 'detail item key'))
                    || strtoupper(yovel_admin_operations_catalog_text($detail['stock_uom_code'] ?? '', 'detail stock UOM code', 40)) !== $stockUomCode
                    || !is_array($detail['uoms'] ?? null)
                    || count($detail['uoms']) > 500) {
                    throw new RuntimeException('Inventory item detail response failed identity or UOM validation.');
                }
                $stockRows = 0;
                foreach (array_values($detail['uoms']) as $row) {
                    if (!is_array($row)) {
                        throw new RuntimeException('Inventory item detail contains an invalid UOM row.');
                    }
                    $itemUomKey = yovel_admin_operations_catalog_uuid($row['item_uom_key'] ?? '', 'item UOM key');
                    $uomKey = yovel_admin_operations_catalog_uuid($row['uom_key'] ?? '', 'UOM key');
                    $uomCode = strtoupper(yovel_admin_operations_catalog_text($row['uom_code'] ?? '', 'UOM code', 40));
                    $uomName = yovel_admin_operations_catalog_text($row['uom_name'] ?? '', 'UOM name', 120);
                    $category = strtoupper(yovel_admin_operations_catalog_text($row['category'] ?? '', 'UOM category', 80));
                    $factor = yovel_admin_operations_catalog_factor($row['conversion_factor'] ?? '');
                    $isStock = yovel_admin_operations_catalog_flag($row['is_stock_uom'] ?? null, 'stock UOM flag');
                    $definition = ['uom_key' => $uomKey, 'uom_code' => $uomCode, 'uom_name' => $uomName, 'uom_category' => $category];
                    if ((isset($uoms[$uomKey]) && $uoms[$uomKey] !== $definition)
                        || (isset($uomCodes[$uomCode]) && !hash_equals($uomCodes[$uomCode], $uomKey))) {
                        throw new RuntimeException('Inventory UOM definitions conflict across item records.');
                    }
                    $uoms[$uomKey] = $definition;
                    $uomCodes[$uomCode] = $uomKey;
                    if ($isStock === 1) {
                        $stockRows++;
                        if ($uomCode !== $stockUomCode || bccomp($factor, '1', 9) !== 0) {
                            throw new RuntimeException('Inventory stock UOM direction or factor is invalid.');
                        }
                    }
                    $pair = $itemKey . '|' . $uomCode . '|' . $stockUomCode;
                    if (isset($conversionPairs[$pair]) || isset($conversionKeys[$itemUomKey])) {
                        throw new RuntimeException('Inventory UOM conversion pairs or keys must be unique.');
                    }
                    $conversionPairs[$pair] = true;
                    $conversionKeys[$itemUomKey] = true;
                    $conversions[] = [
                        'item_uom_key' => $itemUomKey,
                        'item_key' => $itemKey,
                        'uom_key' => $uomKey,
                        'from_uom_code' => $uomCode,
                        'to_uom_code' => $stockUomCode,
                        'uom_category' => $category,
                        'conversion_factor' => $factor,
                        'is_stock_uom' => $isStock,
                    ];
                }
                if ($stockRows !== 1) {
                    throw new RuntimeException('Inventory item detail must expose exactly one stock UOM.');
                }
            }
            $directories['uom'] = [
                'label' => 'UOM', 'status' => 'PARTIAL', 'blocking' => false, 'records' => array_values($uoms),
                'unavailable_fields' => ['uom_status', 'must_be_whole_number'],
                'reason' => 'Inventory exposes stable UOM identity, code, name, and category through item details, but not UOM lifecycle or whole-number constraints.',
            ];
            $directories['uom_conversion_factor'] = [
                'label' => 'UOM Conversion Factor', 'status' => 'AVAILABLE', 'blocking' => false, 'records' => $conversions,
                'unavailable_fields' => [], 'reason' => '',
            ];
            $sources['catalogue'] = yovel_admin_operations_catalog_source('Inventory', 'yovel_admin_inventory_items + yovel_admin_inventory_item', 'AVAILABLE');
        } catch (Throwable) {
            $sources['catalogue'] = yovel_admin_operations_catalog_source('Inventory', 'yovel_admin_inventory_items + yovel_admin_inventory_item', 'UNAVAILABLE_DEPENDENCY', 'Inventory item/UOM response is unavailable or invalid for this company.');
        }
    } else {
        $sources['catalogue'] = yovel_admin_operations_catalog_source('Inventory', 'yovel_admin_inventory_items + yovel_admin_inventory_item', 'UNAVAILABLE_DEPENDENCY', 'Inventory item/UOM owner callables are unavailable.');
    }

    if (function_exists('yovel_admin_inventory_warehouses')) {
        try {
            $warehouseRows = yovel_admin_inventory_warehouses($company, []);
            if (!is_array($warehouseRows) || count($warehouseRows) > 2000) {
                throw new RuntimeException('Inventory warehouse response is invalid.');
            }
            $warehouses = [];
            $warehouseKeys = [];
            foreach (array_values($warehouseRows) as $warehouse) {
                if (!is_array($warehouse)) {
                    throw new RuntimeException('Inventory warehouse response contains an invalid record.');
                }
                yovel_admin_operations_catalog_company_row($warehouse, $companyKeyHash, 'warehouse');
                $warehouseKey = yovel_admin_operations_catalog_uuid($warehouse['warehouse_key'] ?? '', 'warehouse key');
                if (isset($warehouseKeys[$warehouseKey])) {
                    throw new RuntimeException('Inventory warehouse keys must be unique.');
                }
                $warehouseKeys[$warehouseKey] = true;
                $warehouses[] = [
                    'warehouse_key' => $warehouseKey,
                    'warehouse_code' => yovel_admin_operations_catalog_text($warehouse['warehouse_code'] ?? '', 'warehouse code', 80),
                    'warehouse_name' => yovel_admin_operations_catalog_text($warehouse['warehouse_name'] ?? '', 'warehouse name', 180),
                    'parent_warehouse_key' => yovel_admin_operations_catalog_uuid($warehouse['parent_warehouse_key'] ?? '', 'parent warehouse key', false),
                    'is_group' => yovel_admin_operations_catalog_flag($warehouse['is_group'] ?? null, 'warehouse group flag'),
                    'warehouse_status' => yovel_admin_operations_catalog_status($warehouse['warehouse_status'] ?? '', 'warehouse status'),
                ];
            }
            foreach ($warehouses as $warehouse) {
                if ($warehouse['parent_warehouse_key'] !== '' && !isset($warehouseKeys[$warehouse['parent_warehouse_key']])) {
                    throw new RuntimeException('Inventory warehouse parent reference is invalid.');
                }
            }
            $contexts['warehouses'] = $warehouses;
            $sources['warehouses'] = yovel_admin_operations_catalog_source('Inventory', 'yovel_admin_inventory_warehouses', 'AVAILABLE');
        } catch (Throwable) {
            $sources['warehouses'] = yovel_admin_operations_catalog_source('Inventory', 'yovel_admin_inventory_warehouses', 'UNAVAILABLE_DEPENDENCY', 'Inventory warehouse response is unavailable or invalid for this company.');
        }
    } else {
        $sources['warehouses'] = yovel_admin_operations_catalog_source('Inventory', 'yovel_admin_inventory_warehouses', 'UNAVAILABLE_DEPENDENCY', 'Inventory warehouse owner callable is unavailable.');
    }

    return [
        'contract' => 'operations.catalog-units.v1',
        'company_key_hash' => $companyKeyHash,
        'directories' => $directories,
        'sources' => $sources,
        'contexts' => $contexts,
        'owner_actions' => yovel_admin_operations_catalog_owner_actions(),
    ];
}
