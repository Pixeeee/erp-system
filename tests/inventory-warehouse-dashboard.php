<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/inventory-warehouse/functions.php';

function inventory_dashboard_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function inventory_dashboard_by_key(array $rows): array
{
    $indexed = [];
    foreach ($rows as $row) {
        if (is_array($row) && trim((string) ($row['key'] ?? '')) !== '') {
            $indexed[(string) $row['key']] = $row;
        }
    }
    return $indexed;
}

$db = bx_db();
yovel_admin_inventory_warehouse_schema();
yovel_admin_inventory_catalogue_schema();
yovel_admin_inventory_warehouse_control_schema();

$scope = $db->GetRow(
    "SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key,a.admin_name,a.admin_status
     FROM project_company c JOIN project_company_admin a
       ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE'
     WHERE c.company_status='ACTIVE' ORDER BY c.x_id,a.x_id LIMIT 1"
);
inventory_dashboard_assert(is_array($scope) && $scope !== [], 'An active company/admin fixture is required.');
$company = ['company_key' => (string) $scope['company_key'], 'company_key_hash' => (string) $scope['company_key_hash'], 'company_name' => (string) $scope['company_name']];
$admin = ['admin_key' => (string) $scope['admin_key'], 'admin_name' => (string) $scope['admin_name'], 'admin_status' => (string) $scope['admin_status']];
$baselineSummary = inventory_dashboard_by_key(yovel_admin_inventory_warehouse_dashboard_data($company, $admin)['summary']);
$prefix = 'IWDASH' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 8));
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id),0) FROM builder_audit_log');
$itemKeys = [];
$auditKeys = [];

register_shutdown_function(static function () use ($db, $company, $prefix, &$itemKeys, &$auditKeys): void {
    $hash = $company['company_key_hash'];
    $db->Execute("DELETE FROM project_company_inventory_bin_source WHERE company_key_hash=? AND source_owner='DASHBOARD_TEST'", [$hash]);
    foreach ($itemKeys as $itemKey) {
        $db->Execute('DELETE FROM project_company_inventory_putaway_rule WHERE company_key_hash=? AND item_key=?', [$hash, $itemKey]);
        $db->Execute('DELETE FROM project_company_inventory_reorder_rule WHERE company_key_hash=? AND item_key=?', [$hash, $itemKey]);
        $db->Execute('DELETE FROM project_company_inventory_bin WHERE company_key_hash=? AND item_key=?', [$hash, $itemKey]);
        foreach ([
            'project_company_inventory_item_reorder', 'project_company_inventory_item_website_spec',
            'project_company_inventory_item_lead_time', 'project_company_inventory_item_default',
            'project_company_inventory_item_tax', 'project_company_inventory_item_party_detail',
            'project_company_inventory_item_alternative', 'project_company_inventory_item_manufacturer',
            'project_company_inventory_item_variant_attribute', 'project_company_inventory_item_barcode',
            'project_company_inventory_item_uom',
        ] as $table) {
            $db->Execute("DELETE FROM {$table} WHERE company_key_hash=? AND item_key=?", [$hash, $itemKey]);
        }
        $db->Execute('DELETE FROM project_company_inventory_variant_field WHERE company_key_hash=? AND template_item_key=?', [$hash, $itemKey]);
        $db->Execute('DELETE FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=?', [$hash, $itemKey]);
    }
    $warehouses = $db->GetCol('SELECT warehouse_key FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_code LIKE ? ORDER BY is_group ASC,x_id DESC', [$hash, $prefix . '%']);
    foreach ($warehouses as $warehouseKey) {
        $db->Execute('DELETE FROM project_company_inventory_warehouse WHERE company_key_hash=? AND warehouse_key=?', [$hash, $warehouseKey]);
    }
    $db->Execute('DELETE FROM project_company_inventory_warehouse_type WHERE company_key_hash=? AND warehouse_type_code LIKE ?', [$hash, $prefix . '%']);
    foreach ($auditKeys as $auditKey) {
        $db->Execute('DELETE FROM builder_audit_log WHERE audit_key=?', [$auditKey]);
    }
});

$activeItem = yovel_admin_save_inventory_item($company, $admin, [
    'item_code' => $prefix . '-ACTIVE', 'item_name' => 'Dashboard active item', 'item_status' => 'ACTIVE',
    'item_kind' => 'STOCK', 'stock_uom_code' => 'EA',
    'uoms' => [['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '1']],
]);
$itemKeys[] = (string) $activeItem['item_key'];
$disabledItem = yovel_admin_save_inventory_item($company, $admin, [
    'item_code' => $prefix . '-DISABLED', 'item_name' => 'Dashboard disabled item', 'item_status' => 'DISABLED',
    'item_kind' => 'STOCK', 'stock_uom_code' => 'EA',
    'uoms' => [['uom_code' => 'EA', 'uom_name' => 'Each', 'category' => 'COUNT', 'conversion_factor' => '1']],
]);
$itemKeys[] = (string) $disabledItem['item_key'];

$type = yovel_admin_save_warehouse_type($company, $admin, [
    'warehouse_type_code' => $prefix . '-TYPE', 'warehouse_type_name' => 'Dashboard storage', 'warehouse_type_status' => 'ACTIVE',
]);
$rootWarehouse = yovel_admin_save_warehouse($company, $admin, [
    'warehouse_code' => $prefix . '-ROOT', 'warehouse_name' => 'Dashboard root', 'warehouse_type_key' => $type['warehouse_type_key'],
    'is_group' => '1', 'warehouse_status' => 'ACTIVE',
]);
$capacityWarehouse = yovel_admin_save_warehouse($company, $admin, [
    'warehouse_code' => $prefix . '-CAP', 'warehouse_name' => 'Near capacity', 'parent_warehouse_key' => $rootWarehouse['warehouse_key'],
    'warehouse_type_key' => $type['warehouse_type_key'], 'capacity_qty' => '100', 'putaway_priority' => '10', 'warehouse_status' => 'ACTIVE',
]);
$shortageWarehouse = yovel_admin_save_warehouse($company, $admin, [
    'warehouse_code' => $prefix . '-SHORT', 'warehouse_name' => 'Projected shortage', 'parent_warehouse_key' => $rootWarehouse['warehouse_key'],
    'warehouse_type_key' => $type['warehouse_type_key'], 'capacity_qty' => '50', 'putaway_priority' => '20', 'warehouse_status' => 'ACTIVE',
]);
$binSources = [];
foreach ([
    [$capacityWarehouse['warehouse_key'], 'CAP-ACTUAL', 'ACTUAL', '95'],
    [$shortageWarehouse['warehouse_key'], 'SHORT-ACTUAL', 'ACTUAL', '2'],
    [$shortageWarehouse['warehouse_key'], 'SHORT-RESERVED', 'RESERVED', '10'],
] as [$warehouseKey, $sourceKey, $sourceType, $quantity]) {
    $binSources[] = yovel_admin_inventory_save_bin_source($company, $admin, [
        'item_key' => $activeItem['item_key'], 'warehouse_key' => $warehouseKey, 'dimensions' => [],
        'source_owner' => 'DASHBOARD_TEST', 'source_key' => $prefix . '-' . $sourceKey, 'source_type' => $sourceType, 'quantity' => $quantity,
    ]);
}
$reorderRule = yovel_admin_save_inventory_reorder_rule($company, $admin, [
    'item_key' => $activeItem['item_key'], 'warehouse_key' => $shortageWarehouse['warehouse_key'],
    'reorder_level' => '0', 'reorder_quantity' => '20', 'reorder_rule_status' => 'ACTIVE',
]);
$putawayRule = yovel_admin_save_putaway_rule($company, $admin, [
    'item_key' => $activeItem['item_key'], 'warehouse_key' => $capacityWarehouse['warehouse_key'],
    'dimensions' => [], 'priority' => '10', 'putaway_rule_status' => 'ACTIVE',
]);
$auditRecordKeys = [
    (string) $activeItem['item_key'], (string) $disabledItem['item_key'],
    (string) $type['warehouse_type_key'], (string) $rootWarehouse['warehouse_key'],
    (string) $capacityWarehouse['warehouse_key'], (string) $shortageWarehouse['warehouse_key'],
    (string) $reorderRule['reorder_rule_key'], (string) $putawayRule['putaway_rule_key'],
];
foreach ($binSources as $binSource) {
    $auditRecordKeys[] = (string) $binSource['bin_source_key'];
}
$auditMarks = implode(',', array_fill(0, count($auditRecordKeys), '?'));
$auditKeys = $db->GetCol(
    "SELECT audit_key FROM builder_audit_log WHERE x_id>? AND record_key IN ({$auditMarks})",
    array_merge([$auditFloor], $auditRecordKeys)
);
$auditKeys = is_array($auditKeys) ? array_values(array_map('strval', $auditKeys)) : [];

$sections = yovel_admin_inventory_warehouse_sections();
inventory_dashboard_assert(isset($sections['dashboard']) && (string) $sections['dashboard']['label'] === 'Dashboard', 'Inventory dashboard is not registered.');
$dashboard = yovel_admin_inventory_warehouse_dashboard_data($company, $admin);
$expectedEnvelope = ['summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directories', 'dependencies'];
inventory_dashboard_assert(array_keys($dashboard) === $expectedEnvelope, 'Inventory dashboard envelope is incomplete or unstable.');

$summary = inventory_dashboard_by_key($dashboard['summary']);
inventory_dashboard_assert((int) ($summary['active-items']['value'] ?? -1) === (int) ($baselineSummary['active-items']['value'] ?? 0) + 1 && ($summary['active-items']['availability'] ?? '') === 'AVAILABLE', 'Active item KPI is not live.');
inventory_dashboard_assert((int) ($summary['active-warehouses']['value'] ?? -1) === (int) ($baselineSummary['active-warehouses']['value'] ?? 0) + 2, 'Active leaf warehouse KPI is not live.');
inventory_dashboard_assert((int) ($summary['reorder-actions']['value'] ?? -1) === (int) ($baselineSummary['reorder-actions']['value'] ?? 0) + 1, 'Reorder KPI is not live.');
inventory_dashboard_assert((int) ($summary['projected-shortages']['value'] ?? -1) === (int) ($baselineSummary['projected-shortages']['value'] ?? 0) + 1, 'Projected shortage KPI is not live.');
inventory_dashboard_assert(yovel_admin_inventory_projected_shortage_count($company) === (int) ($baselineSummary['projected-shortages']['value'] ?? 0) + 1, 'Projected shortage count must be exact and independent of the queue limit.');
inventory_dashboard_assert((int) ($summary['capacity-alerts']['value'] ?? -1) === (int) ($baselineSummary['capacity-alerts']['value'] ?? 0) + 1, 'Capacity alert KPI is not live.');
inventory_dashboard_assert(
    ($summary['stock-value']['availability'] ?? '') === 'UNAVAILABLE_DEPENDENCY'
    && array_key_exists('value', $summary['stock-value'])
    && $summary['stock-value']['value'] === null,
    'Stock value must remain explicitly unavailable and nonnumeric.'
);

$queue = inventory_dashboard_by_key($dashboard['queue']);
inventory_dashboard_assert(isset($queue['reorder-' . $activeItem['item_key'] . '-' . $shortageWarehouse['warehouse_key']]), 'Reorder action is missing from the dashboard queue.');
inventory_dashboard_assert(isset($queue['shortage-' . $activeItem['item_key'] . '-' . $shortageWarehouse['warehouse_key']]), 'Projected shortage is missing from the dashboard queue.');
inventory_dashboard_assert(isset($queue['capacity-' . $capacityWarehouse['warehouse_key']]), 'Capacity exception is missing from the dashboard queue.');
inventory_dashboard_assert(count($dashboard['activity']) > 0 && count($dashboard['activity']) <= 12, 'Recent Inventory activity must be live and bounded.');
foreach ($dashboard['activity'] as $activity) {
    inventory_dashboard_assert(trim((string) ($activity['actor_label'] ?? '')) !== '' && trim((string) ($activity['occurred_at'] ?? '')) !== '', 'Dashboard activity is missing actor or timestamp data.');
}

$setup = inventory_dashboard_by_key($dashboard['setup']);
foreach (['items-ready', 'warehouses-ready', 'stock-settings-ready', 'putaway-ready', 'reorder-ready', 'form-builder-ready'] as $key) {
    inventory_dashboard_assert(isset($setup[$key]), 'Dashboard setup step is missing: ' . $key);
}
$shortcuts = inventory_dashboard_by_key($dashboard['shortcuts']);
foreach (['items', 'warehouses', 'reorder-levels', 'putaway', 'form-builder'] as $key) {
    inventory_dashboard_assert(isset($shortcuts[$key]) && ($shortcuts[$key]['available'] ?? false) === true, 'Implemented dashboard shortcut is missing: ' . $key);
}
$dependencies = inventory_dashboard_by_key($dashboard['dependencies']);
inventory_dashboard_assert(($dependencies['inventory-valuation']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Valuation dependency is not explicit.');
inventory_dashboard_assert(count($dashboard['directories']) >= 3, 'Inventory directories must group operations, reports, and masters.');

$foreignCompany = $company;
$foreignCompany['company_key_hash'] = str_repeat('0', 64);
try {
    yovel_admin_inventory_warehouse_dashboard_data($foreignCompany, $admin);
    throw new RuntimeException('Cross-company dashboard access was accepted.');
} catch (RuntimeException $error) {
    inventory_dashboard_assert(str_contains($error->getMessage(), 'authorized'), 'Cross-company dashboard rejection must be safe.');
}

$_GET['section'] = 'dashboard';
$activeModuleSections = $sections;
$activeModuleSection = 'dashboard';
$activeModuleData = yovel_admin_inventory_warehouse_data($company, $admin, 'dashboard');
$companyName = (string) $company['company_name'];
ob_start();
require $root . '/company/admin/modules/inventory-warehouse/views/workspace.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
foreach (['data-inventory-dashboard', 'data-inventory-dashboard-summary', 'data-inventory-dashboard-queue', 'data-inventory-dashboard-activity', 'data-inventory-dashboard-setup', 'data-inventory-dashboard-alerts', 'data-inventory-dashboard-shortcuts', 'data-inventory-dashboard-directory', 'data-inventory-dashboard-tour-open', 'data-inventory-dashboard-tour'] as $marker) {
    inventory_dashboard_assert(str_contains($markup, $marker), 'Inventory dashboard marker is missing: ' . $marker);
}
inventory_dashboard_assert(str_contains($markup, 'Stock value') && str_contains($markup, 'Unavailable'), 'Dashboard must render valuation as unavailable.');
inventory_dashboard_assert(str_contains($markup, './?view=inventory-warehouse&amp;section=form-builder'), 'Dashboard Form Builder destination is missing.');
inventory_dashboard_assert(str_contains($markup, 'data-record-modal-open="inventory-item-modal-new"'), 'Dashboard Add Item action must open the shared record modal.');
inventory_dashboard_assert(!str_contains($markup, 'inventory-item-modal-' . $activeItem['item_key']), 'Dashboard must not render hidden catalogue edit modals.');
inventory_dashboard_assert(str_contains($markup, 'name="module_view" value="inventory-warehouse"'), 'Dashboard modal must use the shared module dispatcher.');
inventory_dashboard_assert(str_contains($markup, 'data-confirm-submit'), 'Dashboard modal must use sibling confirmation.');
inventory_dashboard_assert(strpos($markup, 'data-confirm-dialog') > strrpos($markup, '</form>'), 'Dashboard confirmation must remain outside record forms.');
inventory_dashboard_assert(str_contains($markup, 'minmax(0, 12fr) minmax(16rem, 8fr)'), 'Dashboard must preserve the desktop 12/8 grid.');
inventory_dashboard_assert(strpos($markup, 'data-inventory-main') < strpos($markup, 'data-inventory-tools'), 'Dashboard main panel must precede tools in mobile source order.');

$dashboardSource = (string) file_get_contents($root . '/company/admin/modules/inventory-warehouse/dashboard.php');
inventory_dashboard_assert(!str_contains($dashboardSource, '_schema(') && !str_contains($dashboardSource, 'CREATE TABLE'), 'Dashboard reads must not create or migrate schema.');
foreach (['project_company_finance_', 'project_company_sales_', 'project_company_buying_', 'project_company_manufacturing_'] as $forbiddenPrefix) {
    inventory_dashboard_assert(!str_contains($dashboardSource, $forbiddenPrefix), 'Dashboard reads a foreign owner table: ' . $forbiddenPrefix);
}

echo "Inventory/Warehouse live dashboard checks passed.\n";
