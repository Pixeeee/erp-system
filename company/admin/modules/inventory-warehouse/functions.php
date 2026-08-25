<?php
declare(strict_types=1);

require_once __DIR__ . '/persistence.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/catalogue.php';
require_once __DIR__ . '/warehouses.php';
require_once __DIR__ . '/ledger.php';
require_once __DIR__ . '/serial-batch.php';
require_once __DIR__ . '/reconciliation.php';
require_once __DIR__ . '/dashboard.php';

function yovel_admin_inventory_warehouse_sections(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'package' => 'DASHBOARD', 'record_type' => 'ITEM'],
        'items' => ['label' => 'Items', 'icon' => 'inventory_2', 'package' => 'IW-02', 'record_type' => 'ITEM'],
        'warehouses' => ['label' => 'Warehouses', 'icon' => 'warehouse', 'package' => 'IW-03', 'record_type' => 'WAREHOUSE'],
        'stock-entries' => ['label' => 'Stock entries', 'icon' => 'swap_horiz', 'package' => 'IW-05', 'record_type' => 'STOCK_ENTRY'],
        'stock-ledger' => ['label' => 'Stock ledger', 'icon' => 'receipt_long', 'package' => 'IW-04'],
        'stock-reconciliation' => ['label' => 'Stock reconciliation', 'icon' => 'rule', 'package' => 'IW-08', 'record_type' => 'STOCK_RECONCILIATION'],
        'batch-numbers' => ['label' => 'Batch numbers', 'icon' => 'deployed_code', 'package' => 'IW-07', 'record_type' => 'BATCH'],
        'serial-numbers' => ['label' => 'Serial numbers', 'icon' => 'tag', 'package' => 'IW-07', 'record_type' => 'SERIAL'],
        'barcode-records' => ['label' => 'Barcode records', 'icon' => 'barcode_scanner', 'package' => 'IW-02', 'record_type' => 'ITEM'],
        'reorder-levels' => ['label' => 'Reorder levels', 'icon' => 'low_priority', 'package' => 'IW-03', 'record_type' => 'ITEM'],
        'putaway' => ['label' => 'Putaway', 'icon' => 'move_to_inbox', 'package' => 'IW-03', 'record_type' => 'WAREHOUSE'],
        'picking' => ['label' => 'Picking', 'icon' => 'pan_tool_alt', 'package' => 'IW-06', 'record_type' => 'STOCK_RESERVATION'],
        'packing' => ['label' => 'Packing', 'icon' => 'package_2', 'package' => 'IW-09'],
        'shipment' => ['label' => 'Shipment', 'icon' => 'local_shipping', 'package' => 'IW-09', 'record_type' => 'SHIPMENT'],
        'stock-balance-reports' => ['label' => 'Stock balance reports', 'icon' => 'assessment', 'package' => 'IW-11'],
        'traceability-reports' => ['label' => 'Traceability reports', 'icon' => 'timeline', 'package' => 'IW-11'],
        'form-builder' => ['label' => 'Form Builder', 'icon' => 'dynamic_form', 'package' => 'IW-01', 'record_type' => 'ITEM'],
    ];
}

function yovel_admin_inventory_warehouse_section(string $requested = ''): string
{
    $requested = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($requested)), '-');
    return array_key_exists($requested, yovel_admin_inventory_warehouse_sections()) ? $requested : 'dashboard';
}

function yovel_admin_inventory_state(string $section, array $metadata): array
{
    if ($section === 'dashboard') {
        return [
            'kind' => 'ready',
            'title' => 'Inventory operations',
            'message' => '',
            'dependencies' => [],
        ];
    }
    if ($section === 'items') {
        return [
            'kind' => 'empty',
            'title' => 'No item records yet',
            'message' => 'Add the first stock or non-stock item for this company.',
            'dependencies' => [],
        ];
    }
    if ($section === 'form-builder') {
        return [
            'kind' => 'ready',
            'title' => 'Inventory forms',
            'message' => 'Company-specific field layouts are available for Inventory record types.',
            'dependencies' => [],
        ];
    }
    if (in_array($section, ['warehouses', 'reorder-levels', 'putaway', 'batch-numbers', 'serial-numbers'], true)) {
        return [
            'kind' => 'ready',
            'title' => (string) ($metadata['label'] ?? 'Warehouse controls'),
            'message' => '',
            'dependencies' => [],
        ];
    }

    $package = (string) ($metadata['package'] ?? '');
    return [
        'kind' => 'dependency',
        'title' => (string) ($metadata['label'] ?? 'Inventory workspace') . ' is not active yet',
        'message' => 'This workspace is waiting for its approved Inventory package.',
        'dependencies' => $package !== '' ? [$package] : [],
    ];
}

function yovel_admin_inventory_warehouse_data(array $company, ?array $admin, string $section = ''): array
{
    yovel_admin_inventory_scope($company, $admin);
    if (trim($section) === '') {
        $section = (string) ($_GET['section'] ?? 'dashboard');
    }
    $section = yovel_admin_inventory_warehouse_section($section);
    if ($section !== 'dashboard') {
        yovel_admin_inventory_warehouse_schema();
    }
    $sections = yovel_admin_inventory_warehouse_sections();
    $metadata = $sections[$section];
    $requestedRecordType = (string) ($_GET['record_type'] ?? ($metadata['record_type'] ?? 'ITEM'));
    try {
        $recordType = yovel_admin_inventory_record_type($requestedRecordType);
    } catch (InvalidArgumentException) {
        $recordType = 'ITEM';
    }

    $data = [
        'section' => $section,
        'sections' => $sections,
        'section_meta' => $metadata,
        'state' => yovel_admin_inventory_state($section, $metadata),
        'form_adapter' => yovel_admin_inventory_form_adapter(),
        'record_type' => $recordType,
        'active_form_schema' => $section === 'dashboard' ? [] : yovel_admin_inventory_form_schema($company, $recordType),
        'form_state' => [],
        'company_name' => (string) ($company['company_name'] ?? 'Company'),
        'company_key_hash' => (string) ($company['company_key_hash'] ?? ''),
    ];
    if ($section === 'dashboard') {
        $data['dashboard'] = yovel_admin_inventory_warehouse_dashboard_data($company, is_array($admin) ? $admin : []);
        try {
            $data['items'] = yovel_admin_inventory_items($company, ['status' => 'ACTIVE']);
        } catch (Throwable) {
            $data['items'] = [];
        }
        $data['item_prices'] = [];
    }
    if ($section === 'items') {
        yovel_admin_inventory_catalogue_schema();
        $data['items'] = yovel_admin_inventory_items($company, ['search' => (string) ($_GET['search'] ?? '')]);
        $data['item_prices'] = yovel_admin_inventory_item_prices($company);
        $data['item_price_stock'] = yovel_admin_inventory_item_price_stock($company);
        $selectedItemKey = trim((string) ($_GET['item'] ?? ''));
        $data['selected_item'] = yovel_admin_is_uuid($selectedItemKey) ? yovel_admin_inventory_item($company, $selectedItemKey) : null;
        $data['state'] = $data['items'] === [] ? yovel_admin_inventory_state('items', $metadata) : ['kind' => 'ready', 'title' => 'Item catalogue', 'message' => '', 'dependencies' => []];
    }
    if (in_array($section, ['warehouses', 'reorder-levels', 'putaway'], true)) {
        yovel_admin_inventory_catalogue_schema();
        yovel_admin_inventory_warehouse_control_schema();
        $data['warehouses'] = yovel_admin_inventory_warehouses($company);
        $data['warehouse_types'] = yovel_admin_inventory_warehouse_types($company);
        $data['dimensions'] = yovel_admin_inventory_dimensions($company);
        $data['settings'] = yovel_admin_inventory_settings($company);
        $data['items'] = yovel_admin_inventory_items($company, ['status' => 'ACTIVE']);
        $data['putaway_rules'] = yovel_admin_inventory_putaway_rules($company);
        $data['reorder_rules'] = yovel_admin_inventory_reorder_rules($company);
        $data['reorder_recommendations'] = yovel_admin_reorder_recommendations($company);
        $data['capacity_summary'] = yovel_admin_inventory_warehouse_capacity_summary($company);
        $records = match ($section) {
            'warehouses' => $data['warehouses'],
            'putaway' => $data['putaway_rules'],
            default => $data['reorder_rules'],
        };
        $data['state'] = $records === []
            ? ['kind' => 'empty', 'title' => 'No ' . strtolower((string) $metadata['label']) . ' yet', 'message' => 'Add the first company-owned record.', 'dependencies' => []]
            : ['kind' => 'ready', 'title' => (string) $metadata['label'], 'message' => '', 'dependencies' => []];
    }
    if (in_array($section, ['batch-numbers', 'serial-numbers'], true)) {
        yovel_admin_inventory_catalogue_schema();
        yovel_admin_inventory_warehouse_control_schema();
        yovel_admin_inventory_serial_batch_schema();
        $data['items'] = array_values(array_filter(
            yovel_admin_inventory_items($company, ['status' => 'ACTIVE']),
            static fn (array $item): bool => (int) $item[$section === 'batch-numbers' ? 'has_batch_no' : 'has_serial_no'] === 1
        ));
        $data['batches'] = yovel_admin_inventory_batches($company);
        $data['serials'] = yovel_admin_inventory_serials($company);
        $data['warehouses'] = yovel_admin_inventory_warehouses($company, ['status' => 'ACTIVE', 'leaf_only' => true]);
        $availabilityItem = trim((string) ($_GET['item'] ?? ''));
        $availabilityWarehouse = trim((string) ($_GET['warehouse'] ?? ''));
        $availabilityAsOf = str_replace('T', ' ', trim((string) ($_GET['as_of'] ?? '')));
        if (strlen($availabilityAsOf) === 16) { $availabilityAsOf .= ':00'; }
        if ($availabilityAsOf !== '' && !str_contains($availabilityAsOf, '.')) { $availabilityAsOf .= '.000000'; }
        $data['availability_filters'] = ['item_key' => $availabilityItem, 'warehouse_key' => $availabilityWarehouse, 'as_of' => $availabilityAsOf];
        $data['availability'] = yovel_admin_is_uuid($availabilityItem) && yovel_admin_is_uuid($availabilityWarehouse) && $availabilityAsOf !== ''
            ? ($section === 'batch-numbers'
                ? yovel_admin_inventory_available_batches($company, $availabilityItem, $availabilityWarehouse, $availabilityAsOf)
                : yovel_admin_inventory_available_serials($company, $availabilityItem, $availabilityWarehouse, $availabilityAsOf))
            : [];
        $traceKey = trim((string) ($_GET['trace'] ?? ''));
        $data['trace'] = yovel_admin_is_uuid($traceKey) ? yovel_admin_inventory_trace($company, $traceKey) : null;
        $records = $section === 'batch-numbers' ? $data['batches'] : $data['serials'];
        $data['state'] = $records === []
            ? ['kind' => 'empty', 'title' => 'No ' . strtolower((string) $metadata['label']) . ' yet', 'message' => 'Add the first tracked identity for this company.', 'dependencies' => []]
            : ['kind' => 'ready', 'title' => (string) $metadata['label'], 'message' => '', 'dependencies' => []];
    }
    if ($section === 'stock-reconciliation') {
        yovel_admin_inventory_catalogue_schema();
        yovel_admin_inventory_warehouse_control_schema();
        yovel_admin_inventory_ledger_schema();
        yovel_admin_inventory_serial_batch_schema();
        yovel_admin_inventory_reconciliation_schema();
        $data['items'] = yovel_admin_inventory_items($company, ['status' => 'ACTIVE']);
        $data['warehouses'] = yovel_admin_inventory_warehouses($company, ['status' => 'ACTIVE', 'leaf_only' => true]);
        $data['reconciliations'] = yovel_admin_inventory_stock_reconciliations($company);
        $data['diagnostics'] = yovel_admin_inventory_integrity_diagnostics($company);
        $data['state'] = [
            'kind' => 'dependency',
            'title' => (string) $metadata['label'],
            'message' => 'IW-08 is available through the guarded stock reconciliation workflow.',
            'dependencies' => ['IW-08'],
        ];
    }
    return $data;
}

function yovel_admin_inventory_warehouse_handle_post(
    array $company,
    array $admin,
    string $action,
    array $input
): array {
    if ($action === 'save_inventory_form_schema') {
        $recordType = yovel_admin_inventory_record_type((string) ($input['record_type'] ?? 'ITEM'));
        $schema = yovel_admin_inventory_schema_from_post($recordType, $input);
        yovel_admin_save_inventory_form_schema($company, $admin, $recordType, $schema);
        return ['message' => 'Inventory form version saved.', 'section' => 'form-builder', 'query' => ['record_type' => $recordType]];
    }
    if ($action === 'save_inventory_item') {
        $saved = yovel_admin_save_inventory_item($company, $admin, yovel_admin_inventory_item_input_from_post($input));
        $section = (string) ($input['section'] ?? '') === 'dashboard' ? 'dashboard' : 'items';
        return ['message' => 'Inventory item saved.', 'section' => $section, 'query' => ['item' => (string) $saved['item_key']]];
    }
    if ($action === 'save_inventory_item_price') {
        $saved = yovel_admin_save_item_price($company, $admin, $input);
        $section = (string) ($input['section'] ?? '') === 'dashboard' ? 'dashboard' : 'items';
        return ['message' => 'Inventory item price saved.', 'section' => $section, 'query' => ['item' => (string) $saved['item_key']]];
    }
    if ($action === 'save_inventory_warehouse') {
        $saved = yovel_admin_save_warehouse($company, $admin, $input);
        return ['message' => 'Warehouse saved.', 'section' => 'warehouses', 'query' => ['warehouse' => (string) $saved['warehouse_key']]];
    }
    if ($action === 'save_inventory_warehouse_type') {
        $saved = yovel_admin_save_warehouse_type($company, $admin, $input);
        return ['message' => 'Warehouse Type saved.', 'section' => 'warehouses', 'query' => ['warehouse_type' => (string) $saved['warehouse_type_key']]];
    }
    if ($action === 'save_inventory_dimension') {
        $saved = yovel_admin_save_inventory_dimension($company, $admin, $input);
        return ['message' => 'Inventory Dimension saved.', 'section' => 'warehouses', 'query' => ['dimension' => (string) $saved['dimension_key']]];
    }
    if ($action === 'save_inventory_settings') {
        yovel_admin_save_inventory_settings($company, $admin, $input);
        return ['message' => 'Stock Settings saved.', 'section' => 'warehouses', 'query' => []];
    }
    if ($action === 'save_inventory_putaway_rule') {
        $saved = yovel_admin_save_putaway_rule($company, $admin, $input);
        return ['message' => 'Putaway rule saved.', 'section' => 'putaway', 'query' => ['putaway_rule' => (string) $saved['putaway_rule_key']]];
    }
    if ($action === 'save_inventory_reorder_rule') {
        $saved = yovel_admin_save_inventory_reorder_rule($company, $admin, $input);
        return ['message' => 'Reorder rule saved.', 'section' => 'reorder-levels', 'query' => ['reorder_rule' => (string) $saved['reorder_rule_key']]];
    }
    if ($action === 'save_inventory_batch') {
        $saved = yovel_admin_save_inventory_batch($company, $admin, $input);
        return ['message' => 'Inventory Batch saved.', 'section' => 'batch-numbers', 'query' => ['batch' => (string) $saved['batch_key']]];
    }
    if ($action === 'save_inventory_serial') {
        $saved = yovel_admin_save_inventory_serial($company, $admin, $input);
        return ['message' => 'Inventory Serial saved.', 'section' => 'serial-numbers', 'query' => ['serial' => (string) $saved['serial_key']]];
    }
    if ($action === 'save_stock_reconciliation') {
        $saved = yovel_admin_save_stock_reconciliation($company, $admin, $input);
        return ['message' => 'Stock reconciliation saved.', 'section' => 'stock-reconciliation', 'query' => ['reconciliation' => (string) $saved['reconciliation_key']]];
    }
    throw new InvalidArgumentException('Unknown Inventory/Warehouse action.');
}
