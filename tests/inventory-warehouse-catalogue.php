<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/inventory-warehouse/functions.php';

function inventory_catalogue_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = bx_db();
yovel_admin_inventory_catalogue_schema();
$tables = [
    'project_company_inventory_customs_tariff',
    'project_company_inventory_uom',
    'project_company_inventory_manufacturer',
    'project_company_inventory_price_list',
    'project_company_inventory_price_list_country',
    'project_company_inventory_item',
    'project_company_inventory_item_uom',
    'project_company_inventory_item_barcode',
    'project_company_inventory_item_variant_attribute',
    'project_company_inventory_item_attribute',
    'project_company_inventory_item_attribute_value',
    'project_company_inventory_variant_setting',
    'project_company_inventory_variant_field',
    'project_company_inventory_item_manufacturer',
    'project_company_inventory_item_alternative',
    'project_company_inventory_item_party_detail',
    'project_company_inventory_item_tax',
    'project_company_inventory_item_default',
    'project_company_inventory_item_lead_time',
    'project_company_inventory_item_website_spec',
    'project_company_inventory_item_reorder',
    'project_company_inventory_item_price',
];
foreach ($tables as $table) {
    inventory_catalogue_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    ) === 1, 'Missing catalogue table: ' . $table);
}
$indexes = $db->GetCol(
    "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ('project_company_inventory_item','project_company_inventory_item_barcode','project_company_inventory_item_price')",
    [BUILDERX_DB_NAME]
);
foreach (['uq_inventory_item_code', 'uq_inventory_barcode', 'uq_inventory_item_price_natural'] as $index) {
    inventory_catalogue_assert(is_array($indexes) && in_array($index, $indexes, true), 'Missing catalogue uniqueness index: ' . $index);
}

$scope = $db->GetRow(
    "SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key,a.admin_status
     FROM project_company c JOIN project_company_admin a
       ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE'
     WHERE c.company_status='ACTIVE' ORDER BY c.x_id,a.x_id LIMIT 1"
);
inventory_catalogue_assert(is_array($scope) && $scope !== [], 'An active company/admin fixture is required.');
$company = ['company_key' => (string) $scope['company_key'], 'company_key_hash' => (string) $scope['company_key_hash'], 'company_name' => (string) $scope['company_name']];
$admin = ['admin_key' => (string) $scope['admin_key'], 'admin_status' => (string) $scope['admin_status']];

$original = [];
foreach (array_reverse($tables) as $table) {
    $original[$table] = $db->GetAll("SELECT * FROM {$table} WHERE company_key_hash = ? ORDER BY x_id", [$company['company_key_hash']]);
}
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id),0) FROM builder_audit_log');
register_shutdown_function(static function () use ($db, $tables, $original, $company, $auditFloor): void {
    unset($GLOBALS['yovel_admin_inventory_catalogue_fault']);
    foreach ($tables as $table) {
        $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$company['company_key_hash']]);
    }
    foreach (array_reverse($tables) as $table) {
        foreach ($original[$table] ?? [] as $row) {
            $columns = array_keys($row);
            $quoted = implode(',', array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns));
            $marks = implode(',', array_fill(0, count($columns), '?'));
            $db->Execute("INSERT INTO {$table} ({$quoted}) VALUES ({$marks})", array_values($row));
        }
    }
    $db->Execute("DELETE FROM builder_audit_log WHERE x_id > ? AND module IN ('project_company_inventory_item','project_company_inventory_item_price')", [$auditFloor]);
});

$prefix = 'IW02' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 8));
$baseInput = [
    'item_code' => $prefix . '-BASE',
    'item_name' => 'IW-02 stocked item',
    'description' => 'Catalogue transaction fixture',
    'item_status' => 'ACTIVE',
    'item_kind' => 'STOCK',
    'stock_uom_code' => 'EA',
    'has_serial_no' => '0',
    'has_batch_no' => '1',
    'is_template' => '0',
    'customs_tariff_code' => '8471.30',
    'customs_tariff_description' => 'Portable digital goods',
    'uoms' => [
        ['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '1'],
        ['uom_code' => 'BOX', 'uom_name' => 'Box', 'category' => 'COUNT', 'conversion_factor' => '12'],
    ],
    'barcodes' => [['barcode' => '4006381333931', 'barcode_type' => 'EAN13']],
    'manufacturers' => [[
        'manufacturer_code' => $prefix . '-MFG',
        'manufacturer_name' => 'IW-02 Manufacturer',
        'manufacturer_part_no' => 'PART-001',
        'website' => 'https://example.invalid/manufacturer',
    ]],
    'variant_attributes' => [],
    'alternatives' => [],
    'party_details' => [
        ['party_type' => 'CUSTOMER', 'party_reference_key' => bx_uuid(), 'party_item_code' => 'CUSTOMER-SKU'],
        ['party_type' => 'SUPPLIER', 'party_reference_key' => bx_uuid(), 'party_item_code' => 'SUPPLIER-SKU'],
    ],
    'taxes' => [['tax_template_reference' => 'VAT-12', 'tax_rate' => '12']],
    'defaults' => [['scope_key' => 'COMPANY', 'default_warehouse_reference' => 'MAIN', 'default_price_list_reference' => 'STANDARD']],
    'lead_times' => [['context' => 'PURCHASE', 'lead_time_days' => '7']],
    'website_specs' => [['label' => 'Material', 'value' => 'Aluminium']],
    'reorder_rows' => [['warehouse_reference' => 'MAIN', 'reorder_level' => '10', 'reorder_qty' => '24']],
];

$saved = yovel_admin_save_inventory_item($company, $admin, $baseInput);
inventory_catalogue_assert(yovel_admin_is_uuid((string) ($saved['item_key'] ?? '')), 'Item create did not return a stable key.');
inventory_catalogue_assert((string) $saved['item_code'] === $baseInput['item_code'], 'Item code read-back failed.');
inventory_catalogue_assert(count($saved['uoms'] ?? []) === 2, 'UOM rows were not read back exactly.');
inventory_catalogue_assert(count($saved['party_details'] ?? []) === 2, 'Customer/supplier details were not read back.');
inventory_catalogue_assert(count($saved['taxes'] ?? []) === 1 && count($saved['website_specs'] ?? []) === 1, 'Tax or website details were not read back.');
inventory_catalogue_assert((string) ($saved['manufacturers'][0]['manufacturer_part_no'] ?? '') === 'PART-001', 'Manufacturer details were not persisted.');

$sameNatural = $baseInput;
$sameNatural['item_name'] = 'IW-02 stocked item updated';
$updated = yovel_admin_save_inventory_item($company, $admin, $sameNatural);
inventory_catalogue_assert((string) $updated['item_key'] === (string) $saved['item_key'], 'Natural-key upsert did not preserve the item key.');
inventory_catalogue_assert((string) $updated['item_name'] === 'IW-02 stocked item updated', 'Item update was not read back.');
inventory_catalogue_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_item WHERE company_key_hash=? AND item_code=?', [$company['company_key_hash'], $baseInput['item_code']]) === 1, 'Database item-code uniqueness failed.');

$resolvedUom = yovel_admin_resolve_item_uom($company, (string) $saved['item_key'], 'BOX', '2.5');
inventory_catalogue_assert($resolvedUom === ['quantity' => '2.500000000', 'conversion_factor' => '12.000000000', 'stock_quantity' => '30.000000000', 'stock_uom_code' => 'EA'], 'UOM conversion must use BCMath decimal strings.');
$resolvedBarcode = yovel_admin_resolve_item_barcode($company, '4006381333931');
inventory_catalogue_assert((string) ($resolvedBarcode['item_key'] ?? '') === (string) $saved['item_key'] && ($resolvedBarcode['barcode_type'] ?? '') === 'EAN13', 'Barcode lookup failed.');
foreach ([
    ['96385074', 'EAN8'], ['036000291452', 'UPCA'], ['04252614', 'UPCE'], ['0306406152', 'ISBN10'], ['9780306406157', 'ISBN13'],
] as [$barcode, $type]) {
    inventory_catalogue_assert(yovel_admin_inventory_validate_barcode($barcode, $type)['barcode_type'] === $type, $type . ' checksum validation failed.');
}
try {
    yovel_admin_inventory_validate_barcode('4006381333932', 'EAN13');
    throw new RuntimeException('Invalid EAN checksum was accepted.');
} catch (InvalidArgumentException $error) {
    inventory_catalogue_assert(str_contains($error->getMessage(), 'checksum'), 'Invalid barcode error must identify the checksum.');
}

$template = yovel_admin_save_inventory_item($company, $admin, [
    'item_code' => $prefix . '-TPL', 'item_name' => 'Variant template', 'item_status' => 'ACTIVE',
    'item_kind' => 'STOCK', 'stock_uom_code' => 'EA', 'is_template' => '1',
    'uoms' => [['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '1']],
    'variant_attributes' => [['attribute_name' => 'Colour', 'attribute_value' => 'Any'], ['attribute_name' => 'Size', 'attribute_value' => 'Any']],
]);
inventory_catalogue_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_item_attribute WHERE company_key_hash=?', [$company['company_key_hash']]) === 2, 'Reusable Item Attribute masters were not created.');
inventory_catalogue_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_item_attribute_value WHERE company_key_hash=?', [$company['company_key_hash']]) === 2, 'Reusable Item Attribute Values were not created.');
inventory_catalogue_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_variant_setting WHERE company_key_hash=? AND setting_status=\'ACTIVE\'', [$company['company_key_hash']]) === 1, 'Variant Settings were not initialized.');
inventory_catalogue_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_inventory_variant_field WHERE company_key_hash=? AND template_item_key=?', [$company['company_key_hash'], $template['item_key']]) === 2, 'Template Variant Fields were not persisted.');
$variant = yovel_admin_save_inventory_item($company, $admin, [
    'item_code' => $prefix . '-RED-L', 'item_name' => 'Red large variant', 'item_status' => 'ACTIVE',
    'item_kind' => 'STOCK', 'stock_uom_code' => 'EA', 'variant_of_item_key' => $template['item_key'],
    'uoms' => [['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '1']],
    'variant_attributes' => [['attribute_name' => 'Colour', 'attribute_value' => 'Red'], ['attribute_name' => 'Size', 'attribute_value' => 'Large']],
]);
inventory_catalogue_assert((string) $variant['variant_of_item_key'] === (string) $template['item_key'] && count($variant['variant_attributes']) === 2, 'Variant/template attributes were not enforced and read back.');
$withAlternative = $sameNatural;
$withAlternative['item_key'] = $saved['item_key'];
$withAlternative['alternatives'] = [['alternative_item_key' => $variant['item_key'], 'two_way' => '1']];
$alternativeSaved = yovel_admin_save_inventory_item($company, $admin, $withAlternative);
inventory_catalogue_assert((string) ($alternativeSaved['alternatives'][0]['alternative_item_key'] ?? '') === (string) $variant['item_key'], 'Alternative item relation was not read back.');

$nonStockInput = [
    'item_code' => $prefix . '-SERVICE', 'item_name' => 'Invalid tracked service', 'item_status' => 'ACTIVE',
    'item_kind' => 'NON_STOCK', 'stock_uom_code' => 'EA', 'has_serial_no' => '1',
    'uoms' => [['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '1']],
];
try {
    yovel_admin_save_inventory_item($company, $admin, $nonStockInput);
    throw new RuntimeException('Tracked non-stock item was accepted.');
} catch (InvalidArgumentException $error) {
    inventory_catalogue_assert(str_contains($error->getMessage(), 'Non-stock'), 'Non-stock rule rejection must be explicit.');
}
$badUomInput = $nonStockInput;
$badUomInput['item_kind'] = 'STOCK';
$badUomInput['has_serial_no'] = '0';
$badUomInput['uoms'][0]['conversion_factor'] = '0';
try {
    yovel_admin_save_inventory_item($company, $admin, $badUomInput);
    throw new RuntimeException('Zero UOM factor was accepted.');
} catch (InvalidArgumentException $error) {
    inventory_catalogue_assert(str_contains($error->getMessage(), 'greater than zero'), 'UOM factor rejection must be explicit.');
}

$price = yovel_admin_save_item_price($company, $admin, [
    'item_key' => $saved['item_key'], 'price_list_code' => $prefix . '-SELL', 'price_list_name' => 'Standard Selling',
    'currency_code' => 'PHP', 'uom_code' => 'BOX', 'rate' => '1250.50', 'minimum_qty' => '1',
    'valid_from' => '2026-08-01', 'valid_to' => '2026-12-31', 'is_selling' => '1', 'is_buying' => '0',
    'countries' => ['PH', 'SG'], 'price_status' => 'ACTIVE',
]);
inventory_catalogue_assert((string) $price['rate'] === '1250.500000000' && count($price['countries']) === 2, 'Item price/country read-back failed.');
$price['rate'] = '1299.95';
$priceUpdated = yovel_admin_save_item_price($company, $admin, $price);
inventory_catalogue_assert((string) $priceUpdated['item_price_key'] === (string) $price['item_price_key'] && (string) $priceUpdated['rate'] === '1299.950000000', 'Item price upsert did not preserve its stable key.');
inventory_catalogue_assert(count(yovel_admin_inventory_item_prices($company, ['item_key' => $saved['item_key']])) === 1, 'Item price report query did not return the saved price.');
inventory_catalogue_assert(count(yovel_admin_inventory_item_variant_details($company, (string) $template['item_key'])) === 1, 'Variant details report did not return the variant.');
try {
    yovel_admin_save_item_price($company, $admin, [
        'item_key' => $saved['item_key'], 'price_list_code' => $prefix . '-BAD', 'price_list_name' => 'Bad validity',
        'currency_code' => 'PHP', 'uom_code' => 'EA', 'rate' => '1', 'minimum_qty' => '0',
        'valid_from' => '2026-12-31', 'valid_to' => '2026-01-01', 'is_selling' => '1',
    ]);
    throw new RuntimeException('Invalid item price validity was accepted.');
} catch (InvalidArgumentException $error) {
    inventory_catalogue_assert(str_contains($error->getMessage(), 'validity'), 'Price validity rejection must be explicit.');
}
$GLOBALS['yovel_admin_inventory_catalogue_fault'] = static function (string $point): void {
    if ($point === 'price_after_header') {
        throw new RuntimeException('injected price rollback');
    }
};
$rollbackPrice = $priceUpdated;
unset($rollbackPrice['item_price_key']);
$rollbackPrice['valid_from'] = '2027-01-01';
$rollbackPrice['valid_to'] = '';
try {
    yovel_admin_save_item_price($company, $admin, $rollbackPrice);
    throw new RuntimeException('Injected price rollback did not fail.');
} catch (RuntimeException $error) {
    inventory_catalogue_assert($error->getMessage() === 'injected price rollback', 'Unexpected price rollback error.');
}
unset($GLOBALS['yovel_admin_inventory_catalogue_fault']);
inventory_catalogue_assert(count(yovel_admin_inventory_item_prices($company, ['item_key' => $saved['item_key']])) === 1, 'Price rollback left a persisted header.');

$disabledInput = $sameNatural;
$disabledInput['item_key'] = $saved['item_key'];
$disabledInput['item_status'] = 'DISABLED';
yovel_admin_save_inventory_item($company, $admin, $disabledInput);
inventory_catalogue_assert(yovel_admin_resolve_item_barcode($company, '4006381333931') === null, 'Disabled items must not resolve through barcode lookup.');
$disabledInput['item_status'] = 'ACTIVE';
yovel_admin_save_inventory_item($company, $admin, $disabledInput);

$db->Execute('UPDATE project_company_inventory_item SET stock_activity_count=1 WHERE company_key_hash=? AND item_key=?', [$company['company_key_hash'], $saved['item_key']]);
$immutableInput = $disabledInput;
$immutableInput['stock_uom_code'] = 'BOX';
$immutableInput['has_serial_no'] = '1';
$immutableInput['uoms'] = [
    ['uom_code' => 'BOX', 'uom_name' => 'Box', 'category' => 'COUNT', 'conversion_factor' => '1'],
    ['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '0.083333333'],
];
try {
    yovel_admin_save_inventory_item($company, $admin, $immutableInput);
    throw new RuntimeException('Stock-affecting fields changed after ledger activity.');
} catch (RuntimeException $error) {
    inventory_catalogue_assert(str_contains($error->getMessage(), 'stock activity'), 'Immutable stock-field error must be explicit.');
}
$db->Execute('UPDATE project_company_inventory_item SET stock_activity_count=0 WHERE company_key_hash=? AND item_key=?', [$company['company_key_hash'], $saved['item_key']]);

$duplicateBarcodeInput = $baseInput;
$duplicateBarcodeInput['item_code'] = $prefix . '-DUP-BARCODE';
$duplicateBarcodeInput['item_name'] = 'Duplicate barcode item';
try {
    yovel_admin_save_inventory_item($company, $admin, $duplicateBarcodeInput);
    throw new RuntimeException('Duplicate company barcode was accepted.');
} catch (InvalidArgumentException $error) {
    inventory_catalogue_assert(str_contains($error->getMessage(), 'barcode'), 'Duplicate barcode rejection must be safe.');
}
inventory_catalogue_assert(yovel_admin_inventory_items($company, ['search' => $prefix . '-DUP-BARCODE']) === [], 'Failed duplicate-barcode transaction left an item header.');

$rollbackCode = $prefix . '-ROLLBACK';
$rollbackInput = $baseInput;
$rollbackInput['item_code'] = $rollbackCode;
$rollbackInput['item_name'] = 'Rollback item';
$rollbackInput['barcodes'] = [];
$GLOBALS['yovel_admin_inventory_catalogue_fault'] = static function (string $point): void {
    if ($point === 'item_after_header') {
        throw new RuntimeException('injected catalogue rollback');
    }
};
try {
    yovel_admin_save_inventory_item($company, $admin, $rollbackInput);
    throw new RuntimeException('Injected catalogue rollback did not fail.');
} catch (RuntimeException $error) {
    inventory_catalogue_assert($error->getMessage() === 'injected catalogue rollback', 'Unexpected rollback-injection error.');
}
unset($GLOBALS['yovel_admin_inventory_catalogue_fault']);
inventory_catalogue_assert(yovel_admin_inventory_items($company, ['search' => $rollbackCode]) === [], 'Rollback injection left an item header.');

$foreign = $company;
$foreign['company_key_hash'] = str_repeat('0', 64);
inventory_catalogue_assert(yovel_admin_inventory_item($foreign, (string) $saved['item_key']) === null, 'Item read leaked across company scope.');
try {
    yovel_admin_save_inventory_item($foreign, $admin, $baseInput);
    throw new RuntimeException('Cross-company item save was accepted.');
} catch (RuntimeException $error) {
    inventory_catalogue_assert(str_contains($error->getMessage(), 'authorized'), 'Cross-company write rejection must be safe.');
}

$itemPost = yovel_admin_inventory_warehouse_handle_post($company, $admin, 'save_inventory_item', $sameNatural + ['section' => 'items']);
inventory_catalogue_assert($itemPost === ['message' => 'Inventory item saved.', 'section' => 'items', 'query' => ['item' => (string) $saved['item_key']]], 'Item POST result is not stable.');
$pricePost = yovel_admin_inventory_warehouse_handle_post($company, $admin, 'save_inventory_item_price', $priceUpdated + ['section' => 'items']);
inventory_catalogue_assert($pricePost === ['message' => 'Inventory item price saved.', 'section' => 'items', 'query' => ['item' => (string) $saved['item_key']]], 'Price POST result is not stable.');

$_GET['section'] = 'items';
$activeModuleSections = yovel_admin_inventory_warehouse_sections();
$activeModuleSection = 'items';
$activeModuleData = yovel_admin_inventory_warehouse_data($company, $admin, 'items');
$companyName = (string) $company['company_name'];
ob_start();
require $root . '/company/admin/modules/inventory-warehouse/views/workspace.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
foreach (['Add Item', 'Add Item Price'] as $label) {
    inventory_catalogue_assert(preg_match('/<button[^>]*data-record-modal-open="[^"]+"[^>]*>.*?' . preg_quote($label, '/') . '.*?<\/button>/si', $markup) === 1, $label . ' must open a shared record modal.');
}
inventory_catalogue_assert(str_contains($markup, 'aria-label="Edit ' . bx_h($updated['item_code']) . '"'), 'Item edit modal trigger is missing.');
inventory_catalogue_assert(str_contains($markup, 'aria-label="Edit price ' . bx_h($updated['item_code']) . '"'), 'Item price edit modal trigger is missing.');
inventory_catalogue_assert(substr_count($markup, 'name="module_view" value="inventory-warehouse"') >= 3, 'Catalogue forms must identify the module POST route.');
inventory_catalogue_assert(substr_count($markup, 'data-confirm-submit') >= 3, 'Catalogue forms must use the shared confirmation boundary.');
inventory_catalogue_assert(strpos($markup, 'data-confirm-dialog') > strrpos($markup, '</form>'), 'Confirmation dialog must remain a body-owned sibling.');

$source = (string) file_get_contents($root . '/company/admin/modules/inventory-warehouse/catalogue.php');
foreach (['project_company_finance_', 'project_company_sales_', 'project_company_buying_', 'project_company_manufacturing_'] as $prefixName) {
    inventory_catalogue_assert(!str_contains($source, $prefixName), 'Catalogue directly references another module table: ' . $prefixName);
}

echo "Inventory/Warehouse IW-02 catalogue checks passed.\n";
