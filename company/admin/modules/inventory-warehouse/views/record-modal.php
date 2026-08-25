<?php
declare(strict_types=1);

$inventoryModuleData = is_array($inventoryData ?? null) ? $inventoryData : (is_array($activeModuleData ?? null) ? $activeModuleData : []);
$inventoryFormState = is_array($inventoryModuleData['form_state'] ?? null) ? $inventoryModuleData['form_state'] : [];
$inventoryViewSection = (string) ($inventoryModuleData['section'] ?? 'items');
$inventoryModalOriginSection = $inventoryViewSection;
if ($inventoryViewSection === 'dashboard') {
    $inventoryViewSection = 'items';
}

if ($inventoryViewSection === 'form-builder') {
    $inventoryFormSchema = is_array($inventoryModuleData['active_form_schema'] ?? null) ? $inventoryModuleData['active_form_schema'] : [];
    $inventoryFormAdapter = is_array($inventoryModuleData['form_adapter'] ?? null) ? $inventoryModuleData['form_adapter'] : [];
    $inventoryRecordType = (string) ($inventoryFormState['record_type'] ?? ($inventoryModuleData['record_type'] ?? 'ITEM'));
    ob_start();
    require __DIR__ . '/form-builder.php';
    $inventoryBuilderBody = (string) ob_get_clean();
    $inventorySchemaJson = isset($inventoryFormState['schema_json']) && (string) $inventoryFormState['schema_json'] !== ''
        ? (string) $inventoryFormState['schema_json']
        : yovel_admin_inventory_form_json($inventoryFormSchema);
    $recordModal = [
        'id' => 'inventory-form-builder-modal', 'title' => 'Inventory Form Builder',
        'description' => 'Configure the selected company record layout.', 'open_label' => 'Insert field',
        'submit_label' => 'Save form version', 'confirm_message' => 'Confirm this Inventory form version before saving it.',
        'body_html' => $inventoryBuilderBody,
        'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="inventory-warehouse"><input type="hidden" name="action" value="save_inventory_form_schema"><input type="hidden" name="section" value="form-builder"><input type="hidden" name="record_type" value="' . bx_h($inventoryRecordType) . '"><input type="hidden" name="schema_json" value="' . bx_h($inventorySchemaJson) . '">',
    ];
    ob_start();
    require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
    $markup = (string) ob_get_clean();
    if (!empty($inventoryFormState['open'])) {
        $markup = preg_replace('/data-record-modal hidden/', 'data-record-modal data-record-modal-open-on-load hidden', $markup, 1) ?? $markup;
    }
    echo $markup;
    return;
}

if (in_array($inventoryViewSection, ['warehouses', 'putaway', 'reorder-levels'], true)) {
    $inventoryEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $inventoryStateAction = (string) ($inventoryFormState['action'] ?? '');
    $inventoryPrior = is_array($inventoryFormState['input'] ?? null) ? $inventoryFormState['input'] : [];
    $inventoryStateError = (string) ($inventoryFormState['error'] ?? '');
    $inventoryWarehouses = is_array($inventoryModuleData['warehouses'] ?? null) ? $inventoryModuleData['warehouses'] : [];
    $inventoryTypes = is_array($inventoryModuleData['warehouse_types'] ?? null) ? $inventoryModuleData['warehouse_types'] : [];
    $inventoryDimensions = is_array($inventoryModuleData['dimensions'] ?? null) ? $inventoryModuleData['dimensions'] : [];
    $inventoryItems = is_array($inventoryModuleData['items'] ?? null) ? $inventoryModuleData['items'] : [];
    $inventoryPutawayRules = is_array($inventoryModuleData['putaway_rules'] ?? null) ? $inventoryModuleData['putaway_rules'] : [];
    $inventoryReorderRules = is_array($inventoryModuleData['reorder_rules'] ?? null) ? $inventoryModuleData['reorder_rules'] : [];
    $inventoryRenderModal = static function (array $modal, bool $open, bool $showOpener = true): void {
        $recordModal = $modal;
        ob_start();
        require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
        $markup = (string) ob_get_clean();
        if (!$showOpener) {
            $markup = preg_replace('/^<button\b.*?<\/button>\s*/s', '', $markup, 1) ?? $markup;
        }
        if ($open) {
            $markup = preg_replace('/data-record-modal hidden/', 'data-record-modal data-record-modal-open-on-load hidden', $markup, 1) ?? $markup;
        }
        echo $markup;
    };
    $commonHidden = static fn (string $action, string $section): string => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="inventory-warehouse"><input type="hidden" name="action" value="' . bx_h($action) . '"><input type="hidden" name="section" value="' . bx_h($section) . '">';
    $alert = static fn (string $error): string => $error !== '' ? '<div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">' . bx_h($error) . '</div>' : '';
    $value = static fn (array $record, array $prior, string $key, string $fallback = ''): string => bx_h((string) ($prior[$key] ?? $record[$key] ?? $fallback));

    if ($inventoryViewSection === 'warehouses') {
        $warehouseBody = static function (array $record, array $prior, string $error) use ($inventoryWarehouses, $inventoryTypes, $alert, $value): string {
            $status = (string) ($prior['warehouse_status'] ?? $record['warehouse_status'] ?? 'ACTIVE');
            $isGroup = yovel_admin_inventory_warehouse_flag($prior['is_group'] ?? $record['is_group'] ?? 0) === 1;
            ob_start(); ?><?= $alert($error) ?><div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1.5 text-sm font-medium">Code<input name="warehouse_code" required maxlength="80" class="h-9 rounded-md border bg-background px-3" value="<?= $value($record, $prior, 'warehouse_code') ?>"></label>
                <label class="grid gap-1.5 text-sm font-medium">Name<input name="warehouse_name" required maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $value($record, $prior, 'warehouse_name') ?>"></label>
                <label class="grid gap-1.5 text-sm font-medium">Parent<select name="parent_warehouse_key" class="h-9 rounded-md border bg-background px-3"><option value="">No parent</option><?php foreach ($inventoryWarehouses as $warehouse): if ((int) $warehouse['is_group'] !== 1 || (string) $warehouse['warehouse_status'] !== 'ACTIVE' || (string) ($record['warehouse_key'] ?? '') === (string) $warehouse['warehouse_key']) continue; ?><option value="<?= bx_h((string) $warehouse['warehouse_key']) ?>"<?= $value($record, $prior, 'parent_warehouse_key') === (string) $warehouse['warehouse_key'] ? ' selected' : '' ?>><?= bx_h((string) $warehouse['warehouse_code'] . ' - ' . (string) $warehouse['warehouse_name']) ?></option><?php endforeach; ?></select></label>
                <label class="grid gap-1.5 text-sm font-medium">Type<select name="warehouse_type_key" class="h-9 rounded-md border bg-background px-3"><option value="">No type</option><?php foreach ($inventoryTypes as $type): if ((string) $type['warehouse_type_status'] !== 'ACTIVE') continue; ?><option value="<?= bx_h((string) $type['warehouse_type_key']) ?>"<?= $value($record, $prior, 'warehouse_type_key') === (string) $type['warehouse_type_key'] ? ' selected' : '' ?>><?= bx_h((string) $type['warehouse_type_name']) ?></option><?php endforeach; ?></select></label>
                <label class="grid gap-1.5 text-sm font-medium">Capacity<input name="capacity_qty" required inputmode="decimal" class="h-9 rounded-md border bg-background px-3" value="<?= $value($record, $prior, 'capacity_qty', '0') ?>"></label>
                <label class="grid gap-1.5 text-sm font-medium">Putaway priority<input name="putaway_priority" required type="number" min="1" max="1000000" class="h-9 rounded-md border bg-background px-3" value="<?= $value($record, $prior, 'putaway_priority', '100') ?>"></label>
                <label class="grid gap-1.5 text-sm font-medium">Status<select name="warehouse_status" class="h-9 rounded-md border bg-background px-3"><option value="ACTIVE"<?= $status === 'ACTIVE' ? ' selected' : '' ?>>Active</option><option value="DISABLED"<?= $status === 'DISABLED' ? ' selected' : '' ?>>Disabled</option></select></label>
                <label class="flex items-center gap-2 self-end pb-2 text-sm font-medium"><input type="hidden" name="is_group" value="0"><input type="checkbox" name="is_group" value="1"<?= $isGroup ? ' checked' : '' ?>>Group warehouse</label>
            </div><?php return (string) ob_get_clean();
        };
        $warehouseHidden = static fn (array $record, array $prior): string => $commonHidden('save_inventory_warehouse', 'warehouses') . '<input type="hidden" name="warehouse_key" value="' . $value($record, $prior, 'warehouse_key') . '">';
        $open = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_warehouse' && trim((string) ($inventoryPrior['warehouse_key'] ?? '')) === '';
        $inventoryRenderModal(['id' => 'inventory-warehouse-modal-new', 'title' => 'Add Warehouse', 'description' => 'Create a company-owned group or stock-holding location.', 'open_label' => 'Add Warehouse', 'submit_label' => 'Save warehouse', 'confirm_message' => 'Confirm this warehouse before saving it.', 'body_html' => $warehouseBody([], $open ? $inventoryPrior : [], $open ? $inventoryStateError : ''), 'hidden_html' => $warehouseHidden([], $open ? $inventoryPrior : [])], $open);
        foreach ($inventoryWarehouses as $record) {
            $open = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_warehouse' && (string) ($inventoryPrior['warehouse_key'] ?? '') === (string) $record['warehouse_key'];
            $inventoryRenderModal(['id' => 'inventory-warehouse-modal-' . (string) $record['warehouse_key'], 'title' => 'Edit Warehouse', 'description' => 'Update hierarchy, type, status, and capacity.', 'open_label' => 'Edit Warehouse', 'submit_label' => 'Save warehouse', 'confirm_message' => 'Confirm these warehouse changes before saving them.', 'body_html' => $warehouseBody($record, $open ? $inventoryPrior : [], $open ? $inventoryStateError : ''), 'hidden_html' => $warehouseHidden($record, $open ? $inventoryPrior : [])], $open, false);
        }

        $typeOpen = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_warehouse_type' && trim((string) ($inventoryPrior['warehouse_type_key'] ?? '')) === '';
        $typeRecord = [];
        ob_start(); ?><?= $alert($typeOpen ? $inventoryStateError : '') ?><div class="grid gap-4"><label class="grid gap-1.5 text-sm font-medium">Code<input name="warehouse_type_code" required maxlength="80" class="h-9 rounded-md border bg-background px-3" value="<?= $value($typeRecord, $typeOpen ? $inventoryPrior : [], 'warehouse_type_code') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Name<input name="warehouse_type_name" required maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $value($typeRecord, $typeOpen ? $inventoryPrior : [], 'warehouse_type_name') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Status<select name="warehouse_type_status" class="h-9 rounded-md border bg-background px-3"><option value="ACTIVE">Active</option><option value="DISABLED">Disabled</option></select></label></div><?php $typeBody = (string) ob_get_clean();
        $inventoryRenderModal(['id' => 'inventory-warehouse-type-modal-new', 'title' => 'Add Warehouse Type', 'description' => 'Create a company Warehouse Type classification.', 'open_label' => 'Add Warehouse Type', 'submit_label' => 'Save type', 'confirm_message' => 'Confirm this Warehouse Type before saving it.', 'body_html' => $typeBody, 'hidden_html' => $commonHidden('save_inventory_warehouse_type', 'warehouses') . '<input type="hidden" name="warehouse_type_key" value="' . $value([], $typeOpen ? $inventoryPrior : [], 'warehouse_type_key') . '">'], $typeOpen);
        foreach ($inventoryTypes as $typeRecord) {
            $editOpen = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_warehouse_type' && (string) ($inventoryPrior['warehouse_type_key'] ?? '') === (string) $typeRecord['warehouse_type_key'];
            $editPrior = $editOpen ? $inventoryPrior : [];
            $editStatus = (string) ($editPrior['warehouse_type_status'] ?? $typeRecord['warehouse_type_status'] ?? 'ACTIVE');
            ob_start(); ?><?= $alert($editOpen ? $inventoryStateError : '') ?><div class="grid gap-4"><label class="grid gap-1.5 text-sm font-medium">Code<input name="warehouse_type_code" required maxlength="80" class="h-9 rounded-md border bg-background px-3" value="<?= $value($typeRecord, $editPrior, 'warehouse_type_code') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Name<input name="warehouse_type_name" required maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $value($typeRecord, $editPrior, 'warehouse_type_name') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Status<select name="warehouse_type_status" class="h-9 rounded-md border bg-background px-3"><option value="ACTIVE"<?= $editStatus === 'ACTIVE' ? ' selected' : '' ?>>Active</option><option value="DISABLED"<?= $editStatus === 'DISABLED' ? ' selected' : '' ?>>Disabled</option></select></label></div><?php $editTypeBody = (string) ob_get_clean();
            $inventoryRenderModal(['id' => 'inventory-warehouse-type-modal-' . (string) $typeRecord['warehouse_type_key'], 'title' => 'Edit Warehouse Type', 'description' => 'Update this company Warehouse Type.', 'open_label' => 'Edit Warehouse Type', 'submit_label' => 'Save type', 'confirm_message' => 'Confirm these Warehouse Type changes.', 'body_html' => $editTypeBody, 'hidden_html' => $commonHidden('save_inventory_warehouse_type', 'warehouses') . '<input type="hidden" name="warehouse_type_key" value="' . bx_h((string) $typeRecord['warehouse_type_key']) . '">'], $editOpen, false);
        }

        $dimensionOpen = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_dimension' && trim((string) ($inventoryPrior['dimension_key'] ?? '')) === '';
        $dimensionValues = (string) (($dimensionOpen ? $inventoryPrior['values_json'] ?? '' : '') ?: '[]');
        ob_start(); ?><?= $alert($dimensionOpen ? $inventoryStateError : '') ?><div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium">Code<input name="dimension_code" required maxlength="80" class="h-9 rounded-md border bg-background px-3" value="<?= $value([], $dimensionOpen ? $inventoryPrior : [], 'dimension_code') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Name<input name="dimension_name" required maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $value([], $dimensionOpen ? $inventoryPrior : [], 'dimension_name') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Sort order<input type="number" min="1" name="sort_order" class="h-9 rounded-md border bg-background px-3" value="<?= $value([], $dimensionOpen ? $inventoryPrior : [], 'sort_order', '100') ?>"></label><label class="flex items-center gap-2 self-end pb-2 text-sm font-medium"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1"<?= yovel_admin_inventory_warehouse_flag($dimensionOpen ? $inventoryPrior['is_required'] ?? 0 : 0) ? ' checked' : '' ?>>Required for stock</label><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Values JSON<textarea name="values_json" required rows="5" class="rounded-md border bg-background px-3 py-2 font-mono text-xs"><?= bx_h($dimensionValues) ?></textarea></label></div><?php $dimensionBody = (string) ob_get_clean();
        $inventoryRenderModal(['id' => 'inventory-dimension-modal-new', 'title' => 'Add Inventory Dimension', 'description' => 'Define a company stock dimension and its allowed values.', 'open_label' => 'Add Inventory Dimension', 'submit_label' => 'Save dimension', 'confirm_message' => 'Confirm this Inventory Dimension before saving it.', 'body_html' => $dimensionBody, 'hidden_html' => $commonHidden('save_inventory_dimension', 'warehouses') . '<input type="hidden" name="dimension_key" value="' . $value([], $dimensionOpen ? $inventoryPrior : [], 'dimension_key') . '"><input type="hidden" name="dimension_status" value="ACTIVE">'], $dimensionOpen);
        foreach ($inventoryDimensions as $dimensionRecord) {
            $editOpen = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_dimension' && (string) ($inventoryPrior['dimension_key'] ?? '') === (string) $dimensionRecord['dimension_key'];
            $editPrior = $editOpen ? $inventoryPrior : [];
            $editValues = $editOpen && isset($editPrior['values_json']) ? (string) $editPrior['values_json'] : json_encode(array_map(static fn (array $row): array => ['dimension_value_code' => (string) $row['dimension_value_code'], 'dimension_value_name' => (string) $row['dimension_value_name'], 'dimension_value_status' => (string) $row['dimension_value_status']], $dimensionRecord['values'] ?? []), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            ob_start(); ?><?= $alert($editOpen ? $inventoryStateError : '') ?><div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium">Code<input name="dimension_code" required maxlength="80" class="h-9 rounded-md border bg-background px-3" value="<?= $value($dimensionRecord, $editPrior, 'dimension_code') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Name<input name="dimension_name" required maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $value($dimensionRecord, $editPrior, 'dimension_name') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Sort order<input type="number" min="1" name="sort_order" class="h-9 rounded-md border bg-background px-3" value="<?= $value($dimensionRecord, $editPrior, 'sort_order', '100') ?>"></label><label class="flex items-center gap-2 self-end pb-2 text-sm font-medium"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1"<?= yovel_admin_inventory_warehouse_flag($editPrior['is_required'] ?? $dimensionRecord['is_required'] ?? 0) ? ' checked' : '' ?>>Required for stock</label><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Values JSON<textarea name="values_json" required rows="5" class="rounded-md border bg-background px-3 py-2 font-mono text-xs"><?= bx_h($editValues) ?></textarea></label></div><?php $editDimensionBody = (string) ob_get_clean();
            $inventoryRenderModal(['id' => 'inventory-dimension-modal-' . (string) $dimensionRecord['dimension_key'], 'title' => 'Edit Inventory Dimension', 'description' => 'Update this stock dimension and its allowed values.', 'open_label' => 'Edit Inventory Dimension', 'submit_label' => 'Save dimension', 'confirm_message' => 'Confirm these Inventory Dimension changes.', 'body_html' => $editDimensionBody, 'hidden_html' => $commonHidden('save_inventory_dimension', 'warehouses') . '<input type="hidden" name="dimension_key" value="' . bx_h((string) $dimensionRecord['dimension_key']) . '"><input type="hidden" name="dimension_status" value="' . bx_h((string) $dimensionRecord['dimension_status']) . '">'], $editOpen, false);
        }

        $settings = is_array($inventoryModuleData['settings'] ?? null) ? $inventoryModuleData['settings'] : [];
        $settingsOpen = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_settings';
        $settingsPrior = $settingsOpen ? $inventoryPrior : [];
        ob_start(); ?><?= $alert($settingsOpen ? $inventoryStateError : '') ?><div class="grid gap-4"><label class="flex items-center gap-2 text-sm font-medium"><input type="hidden" name="allow_negative_stock" value="0"><input type="checkbox" name="allow_negative_stock" value="1"<?= yovel_admin_inventory_warehouse_flag($settingsPrior['allow_negative_stock'] ?? $settings['allow_negative_stock'] ?? 0) ? ' checked' : '' ?>>Allow negative stock</label><label class="flex items-center gap-2 text-sm font-medium"><input type="hidden" name="capacity_enforcement" value="0"><input type="checkbox" name="capacity_enforcement" value="1"<?= yovel_admin_inventory_warehouse_flag($settingsPrior['capacity_enforcement'] ?? $settings['capacity_enforcement'] ?? 1) ? ' checked' : '' ?>>Enforce warehouse capacity</label><label class="grid gap-1.5 text-sm font-medium">Putaway strategy<select name="default_putaway_strategy" class="h-9 rounded-md border bg-background px-3"><option value="PRIORITY">Priority</option><option value="CAPACITY">Capacity</option></select></label></div><?php $settingsBody = (string) ob_get_clean();
        $inventoryRenderModal(['id' => 'inventory-stock-settings-modal', 'title' => 'Stock Settings', 'description' => 'Set company stock integrity and putaway defaults.', 'open_label' => 'Configure Stock Settings', 'submit_label' => 'Save settings', 'confirm_message' => 'Confirm these Stock Settings before saving them.', 'body_html' => $settingsBody, 'hidden_html' => $commonHidden('save_inventory_settings', 'warehouses')], $settingsOpen);
    }

    if ($inventoryViewSection === 'putaway') {
        $body = static function (array $record, array $prior, string $error) use ($inventoryItems, $inventoryWarehouses, $alert, $value): string { ob_start(); ?><?= $alert($error) ?><div class="grid gap-4"><label class="grid gap-1.5 text-sm font-medium">Item<select name="item_key" required class="h-9 rounded-md border bg-background px-3"><option value="">Select item</option><?php foreach ($inventoryItems as $item): ?><option value="<?= bx_h((string) $item['item_key']) ?>"<?= $value($record, $prior, 'item_key') === (string) $item['item_key'] ? ' selected' : '' ?>><?= bx_h((string) $item['item_code'] . ' - ' . (string) $item['item_name']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm font-medium">Warehouse<select name="warehouse_key" required class="h-9 rounded-md border bg-background px-3"><option value="">Select warehouse</option><?php foreach ($inventoryWarehouses as $warehouse): if ((int) $warehouse['is_group'] === 1 || (string) $warehouse['warehouse_status'] !== 'ACTIVE') continue; ?><option value="<?= bx_h((string) $warehouse['warehouse_key']) ?>"<?= $value($record, $prior, 'warehouse_key') === (string) $warehouse['warehouse_key'] ? ' selected' : '' ?>><?= bx_h((string) $warehouse['warehouse_code']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm font-medium">Priority<input name="priority" type="number" min="1" required class="h-9 rounded-md border bg-background px-3" value="<?= $value($record, $prior, 'priority', '100') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Dimensions JSON<input name="dimensions_json" required class="h-9 rounded-md border bg-background px-3 font-mono text-xs" value="<?= $value($record, $prior, 'dimensions_json', '{}') ?>"></label></div><?php return (string) ob_get_clean(); };
        $hidden = static fn (array $record, array $prior): string => $commonHidden('save_inventory_putaway_rule', 'putaway') . '<input type="hidden" name="putaway_rule_key" value="' . $value($record, $prior, 'putaway_rule_key') . '"><input type="hidden" name="putaway_rule_status" value="ACTIVE">';
        $open = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_putaway_rule' && trim((string) ($inventoryPrior['putaway_rule_key'] ?? '')) === '';
        $inventoryRenderModal(['id' => 'inventory-putaway-modal-new', 'title' => 'Add Putaway Rule', 'description' => 'Prioritize an active leaf warehouse for an item and dimension tuple.', 'open_label' => 'Add Putaway Rule', 'submit_label' => 'Save rule', 'confirm_message' => 'Confirm this Putaway Rule before saving it.', 'body_html' => $body([], $open ? $inventoryPrior : [], $open ? $inventoryStateError : ''), 'hidden_html' => $hidden([], $open ? $inventoryPrior : [])], $open);
        foreach ($inventoryPutawayRules as $record) { $open = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_putaway_rule' && (string) ($inventoryPrior['putaway_rule_key'] ?? '') === (string) $record['putaway_rule_key']; $inventoryRenderModal(['id' => 'inventory-putaway-modal-' . (string) $record['putaway_rule_key'], 'title' => 'Edit Putaway Rule', 'description' => 'Update warehouse priority for this tuple.', 'open_label' => 'Edit Putaway Rule', 'submit_label' => 'Save rule', 'confirm_message' => 'Confirm these Putaway Rule changes.', 'body_html' => $body($record, $open ? $inventoryPrior : [], $open ? $inventoryStateError : ''), 'hidden_html' => $hidden($record, $open ? $inventoryPrior : [])], $open, false); }
    }

    if ($inventoryViewSection === 'reorder-levels') {
        $body = static function (array $record, array $prior, string $error) use ($inventoryItems, $inventoryWarehouses, $alert, $value): string { ob_start(); ?><?= $alert($error) ?><div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Item<select name="item_key" required class="h-9 rounded-md border bg-background px-3"><option value="">Select item</option><?php foreach ($inventoryItems as $item): ?><option value="<?= bx_h((string) $item['item_key']) ?>"<?= $value($record, $prior, 'item_key') === (string) $item['item_key'] ? ' selected' : '' ?>><?= bx_h((string) $item['item_code'] . ' - ' . (string) $item['item_name']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Warehouse<select name="warehouse_key" required class="h-9 rounded-md border bg-background px-3"><option value="">Select warehouse</option><?php foreach ($inventoryWarehouses as $warehouse): if ((int) $warehouse['is_group'] === 1 || (string) $warehouse['warehouse_status'] !== 'ACTIVE') continue; ?><option value="<?= bx_h((string) $warehouse['warehouse_key']) ?>"<?= $value($record, $prior, 'warehouse_key') === (string) $warehouse['warehouse_key'] ? ' selected' : '' ?>><?= bx_h((string) $warehouse['warehouse_code']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm font-medium">Reorder level<input name="reorder_level" required inputmode="decimal" class="h-9 rounded-md border bg-background px-3" value="<?= $value($record, $prior, 'reorder_level', '0') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Reorder quantity<input name="reorder_quantity" required inputmode="decimal" class="h-9 rounded-md border bg-background px-3" value="<?= $value($record, $prior, 'reorder_quantity', '1') ?>"></label></div><?php return (string) ob_get_clean(); };
        $hidden = static fn (array $record, array $prior): string => $commonHidden('save_inventory_reorder_rule', 'reorder-levels') . '<input type="hidden" name="reorder_rule_key" value="' . $value($record, $prior, 'reorder_rule_key') . '"><input type="hidden" name="reorder_rule_status" value="ACTIVE">';
        $open = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_reorder_rule' && trim((string) ($inventoryPrior['reorder_rule_key'] ?? '')) === '';
        $inventoryRenderModal(['id' => 'inventory-reorder-modal-new', 'title' => 'Add Reorder Rule', 'description' => 'Set an item and warehouse replenishment threshold.', 'open_label' => 'Add Reorder Rule', 'submit_label' => 'Save rule', 'confirm_message' => 'Confirm this Item Reorder rule before saving it.', 'body_html' => $body([], $open ? $inventoryPrior : [], $open ? $inventoryStateError : ''), 'hidden_html' => $hidden([], $open ? $inventoryPrior : [])], $open);
        foreach ($inventoryReorderRules as $record) { $open = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_reorder_rule' && (string) ($inventoryPrior['reorder_rule_key'] ?? '') === (string) $record['reorder_rule_key']; $inventoryRenderModal(['id' => 'inventory-reorder-modal-' . (string) $record['reorder_rule_key'], 'title' => 'Edit Reorder Rule', 'description' => 'Update the replenishment threshold and quantity.', 'open_label' => 'Edit Reorder Rule', 'submit_label' => 'Save rule', 'confirm_message' => 'Confirm these Item Reorder changes.', 'body_html' => $body($record, $open ? $inventoryPrior : [], $open ? $inventoryStateError : ''), 'hidden_html' => $hidden($record, $open ? $inventoryPrior : [])], $open, false); }
    }
    return;
}

if (in_array($inventoryViewSection, ['batch-numbers', 'serial-numbers'], true)) {
    $isBatch = $inventoryViewSection === 'batch-numbers';
    $records = is_array($inventoryModuleData[$isBatch ? 'batches' : 'serials'] ?? null) ? $inventoryModuleData[$isBatch ? 'batches' : 'serials'] : [];
    $items = is_array($inventoryModuleData['items'] ?? null) ? $inventoryModuleData['items'] : [];
    $action = $isBatch ? 'save_inventory_batch' : 'save_inventory_serial';
    $keyField = $isBatch ? 'batch_key' : 'serial_key';
    $numberField = $isBatch ? 'batch_number' : 'serial_number';
    $label = $isBatch ? 'Batch' : 'Serial';
    $prior = is_array($inventoryFormState['input'] ?? null) ? $inventoryFormState['input'] : [];
    $stateAction = (string) ($inventoryFormState['action'] ?? '');
    $error = (string) ($inventoryFormState['error'] ?? '');
    $render = static function (array $modal, bool $open, bool $showOpener = true): void {
        $recordModal = $modal;
        ob_start(); require dirname(__DIR__, 3) . '/views/partials/record-modal.php'; $markup = (string) ob_get_clean();
        if (!$showOpener) { $markup = preg_replace('/^<button\b.*?<\/button>\s*/s', '', $markup, 1) ?? $markup; }
        if ($open) { $markup = preg_replace('/data-record-modal hidden/', 'data-record-modal data-record-modal-open-on-load hidden', $markup, 1) ?? $markup; }
        echo $markup;
    };
    $body = static function (array $record, array $priorValues, string $formError) use ($items, $isBatch, $numberField, $label): string {
        $value = static fn (string $key, string $fallback = ''): string => bx_h((string) ($priorValues[$key] ?? $record[$key] ?? $fallback));
        $status = (string) ($priorValues[$isBatch ? 'batch_status' : 'serial_status'] ?? $record[$isBatch ? 'batch_status' : 'serial_status'] ?? 'ACTIVE');
        ob_start(); ?>
        <?php if ($formError !== ''): ?><div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= bx_h($formError) ?></div><?php endif; ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Item<select name="item_key" required class="h-9 rounded-md border bg-background px-3"><option value="">Select tracked item</option><?php foreach ($items as $item): ?><option value="<?= bx_h((string) $item['item_key']) ?>"<?= $value('item_key') === (string) $item['item_key'] ? ' selected' : '' ?>><?= bx_h((string) $item['item_code'] . ' - ' . (string) $item['item_name']) ?></option><?php endforeach; ?></select></label>
            <label class="grid gap-1.5 text-sm font-medium sm:col-span-2"><?= bx_h($label) ?> number<input name="<?= bx_h($numberField) ?>" required maxlength="120" class="h-9 rounded-md border bg-background px-3" value="<?= $value($numberField) ?>"></label>
            <?php if ($isBatch): ?><label class="grid gap-1.5 text-sm font-medium">Manufacturing date<input type="date" name="manufacturing_date" class="h-9 rounded-md border bg-background px-3" value="<?= $value('manufacturing_date') ?>"></label><label class="grid gap-1.5 text-sm font-medium">Expiry date<input type="date" name="expiry_date" class="h-9 rounded-md border bg-background px-3" value="<?= $value('expiry_date') ?>"></label><?php endif; ?>
            <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Status<select name="<?= $isBatch ? 'batch_status' : 'serial_status' ?>" class="h-9 rounded-md border bg-background px-3"><option value="ACTIVE"<?= $status === 'ACTIVE' ? ' selected' : '' ?>>Active</option><option value="DISABLED"<?= $status === 'DISABLED' ? ' selected' : '' ?>>Disabled</option></select></label>
        </div><?php return (string) ob_get_clean();
    };
    $hidden = static fn (array $record, array $priorValues): string => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="inventory-warehouse"><input type="hidden" name="action" value="' . bx_h($action) . '"><input type="hidden" name="section" value="' . bx_h($inventoryViewSection) . '"><input type="hidden" name="' . bx_h($keyField) . '" value="' . bx_h((string) ($priorValues[$keyField] ?? $record[$keyField] ?? '')) . '">';
    $newOpen = !empty($inventoryFormState['open']) && $stateAction === $action && trim((string) ($prior[$keyField] ?? '')) === '';
    $render(['id' => 'inventory-' . strtolower($label) . '-modal-new', 'title' => 'Add ' . $label, 'description' => 'Create a company-owned ' . strtolower($label) . ' identity.', 'open_label' => 'Add ' . $label, 'submit_label' => 'Save ' . strtolower($label), 'confirm_message' => 'Confirm this Inventory ' . $label . ' before saving it.', 'body_html' => $body([], $newOpen ? $prior : [], $newOpen ? $error : ''), 'hidden_html' => $hidden([], $newOpen ? $prior : [])], $newOpen);
    foreach ($records as $record) {
        $open = !empty($inventoryFormState['open']) && $stateAction === $action && (string) ($prior[$keyField] ?? '') === (string) $record[$keyField];
        $render(['id' => 'inventory-' . strtolower($label) . '-modal-' . (string) $record[$keyField], 'title' => 'Edit ' . $label, 'description' => 'Update status and lifecycle dates without changing movement history.', 'open_label' => 'Edit ' . $label, 'submit_label' => 'Save ' . strtolower($label), 'confirm_message' => 'Confirm these Inventory ' . $label . ' changes.', 'body_html' => $body($record, $open ? $prior : [], $open ? $error : ''), 'hidden_html' => $hidden($record, $open ? $prior : [])], $open, false);
    }
    return;
}

if ($inventoryViewSection !== 'items') {
    return;
}

$inventoryEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$inventoryStateAction = (string) ($inventoryFormState['action'] ?? '');
$inventoryPrior = is_array($inventoryFormState['input'] ?? null) ? $inventoryFormState['input'] : [];
$inventoryStateError = (string) ($inventoryFormState['error'] ?? '');
$inventoryItems = is_array($inventoryModuleData['items'] ?? null) ? $inventoryModuleData['items'] : [];
$inventoryPrices = is_array($inventoryModuleData['item_prices'] ?? null) ? $inventoryModuleData['item_prices'] : [];

$inventoryRenderModal = static function (array $modal, bool $open, bool $showOpener = true): void {
    $recordModal = $modal;
    ob_start();
    require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
    $markup = (string) ob_get_clean();
    if (!$showOpener) {
        $markup = preg_replace('/^<button\b.*?<\/button>\s*/s', '', $markup, 1) ?? $markup;
    }
    if ($open) {
        $markup = preg_replace('/data-record-modal hidden/', 'data-record-modal data-record-modal-open-on-load hidden', $markup, 1) ?? $markup;
    }
    echo $markup;
};

$inventoryItemBody = static function (array $record, array $prior, string $error) use ($inventoryEscape): string {
    $value = static fn (string $key, string $fallback = ''): string => $inventoryEscape((string) ($prior[$key] ?? $record[$key] ?? $fallback));
    $status = (string) ($prior['item_status'] ?? $record['item_status'] ?? 'ACTIVE');
    $kind = (string) ($prior['item_kind'] ?? $record['item_kind'] ?? 'STOCK');
    $checked = static fn (string $key): string => yovel_admin_inventory_catalogue_flag($prior[$key] ?? $record[$key] ?? 0) === 1 ? ' checked' : '';
    ob_start();
    if ($error !== ''): ?><div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $inventoryEscape($error) ?></div><?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium">Item code<input name="item_code" required maxlength="80" class="h-9 rounded-md border bg-background px-3" value="<?= $value('item_code') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Item name<input name="item_name" required maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $value('item_name') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Type<select name="item_kind" class="h-9 rounded-md border bg-background px-3"><option value="STOCK"<?= $kind === 'STOCK' ? ' selected' : '' ?>>Stock</option><option value="NON_STOCK"<?= $kind === 'NON_STOCK' ? ' selected' : '' ?>>Non-stock</option></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Status<select name="item_status" class="h-9 rounded-md border bg-background px-3"><option value="ACTIVE"<?= $status === 'ACTIVE' ? ' selected' : '' ?>>Active</option><option value="DISABLED"<?= $status === 'DISABLED' ? ' selected' : '' ?>>Disabled</option></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Stock UOM<input name="stock_uom_code" required maxlength="40" class="h-9 rounded-md border bg-background px-3" value="<?= $value('stock_uom_code', 'EA') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Tariff number<input name="customs_tariff_code" maxlength="40" class="h-9 rounded-md border bg-background px-3" value="<?= $value('customs_tariff_code') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Barcode<input name="barcode" maxlength="80" class="h-9 rounded-md border bg-background px-3" value="<?= $value('barcode') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Barcode type<select name="barcode_type" class="h-9 rounded-md border bg-background px-3"><?php foreach (['EAN13','EAN8','UPCA','UPCE','ISBN10','ISBN13','CODE128'] as $type): ?><option value="<?= $type ?>"<?= $value('barcode_type', 'EAN13') === $type ? ' selected' : '' ?>><?= $type ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Manufacturer code<input name="manufacturer_code" maxlength="80" class="h-9 rounded-md border bg-background px-3" value="<?= $value('manufacturer_code') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Manufacturer name<input name="manufacturer_name" maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $value('manufacturer_name') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Variant template key<input name="variant_of_item_key" maxlength="36" class="h-9 rounded-md border bg-background px-3" value="<?= $value('variant_of_item_key') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Attribute<input name="attribute_name" maxlength="120" class="h-9 rounded-md border bg-background px-3" value="<?= $value('attribute_name') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Attribute value<input name="attribute_value" maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $value('attribute_value') ?>"></label>
        <div class="flex flex-wrap items-center gap-4 sm:col-span-2"><label class="flex items-center gap-2 text-sm"><input type="hidden" name="has_serial_no" value="0"><input type="checkbox" name="has_serial_no" value="1"<?= $checked('has_serial_no') ?>>Serial tracking</label><label class="flex items-center gap-2 text-sm"><input type="hidden" name="has_batch_no" value="0"><input type="checkbox" name="has_batch_no" value="1"<?= $checked('has_batch_no') ?>>Batch tracking</label><label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_template" value="0"><input type="checkbox" name="is_template" value="1"<?= $checked('is_template') ?>>Variant template</label></div>
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Description<textarea name="description" maxlength="1000" rows="3" class="rounded-md border bg-background px-3 py-2"><?= $value('description', (string) ($record['item_description'] ?? '')) ?></textarea></label>
    </div>
    <?php return (string) ob_get_clean();
};

$inventoryItemReturnSection = $inventoryModalOriginSection === 'dashboard' ? 'dashboard' : 'items';
$inventoryItemHidden = static function (array $record, array $prior) use ($inventoryEscape, $inventoryItemReturnSection): string {
    $hidden = '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="inventory-warehouse"><input type="hidden" name="action" value="save_inventory_item"><input type="hidden" name="section" value="' . $inventoryEscape($inventoryItemReturnSection) . '"><input type="hidden" name="item_key" value="' . $inventoryEscape((string) ($prior['item_key'] ?? $record['item_key'] ?? '')) . '">';
    foreach (['uoms','barcodes','variant_attributes','manufacturers','alternatives','party_details','taxes','defaults','lead_times','website_specs','reorder_rows'] as $collection) {
        $json = (string) ($prior[$collection . '_json'] ?? json_encode($record[$collection] ?? [], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $hidden .= '<input type="hidden" name="' . $collection . '_json" value="' . $inventoryEscape($json) . '">';
    }
    return $hidden;
};

$addItemOpen = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_item' && trim((string) ($inventoryPrior['item_key'] ?? '')) === '';
$inventoryRenderModal([
    'id' => 'inventory-item-modal-new', 'title' => 'Add Item', 'description' => 'Create a company-owned stock or non-stock catalogue record.',
    'open_label' => 'Add Item', 'submit_label' => 'Save item', 'confirm_message' => 'Confirm this Inventory item before saving it.',
    'body_html' => $inventoryItemBody([], $addItemOpen ? $inventoryPrior : [], $addItemOpen ? $inventoryStateError : ''),
    'hidden_html' => $inventoryItemHidden([], $addItemOpen ? $inventoryPrior : []),
], $addItemOpen, $inventoryModalOriginSection !== 'dashboard');

if ($inventoryModalOriginSection !== 'dashboard') {
    foreach ($inventoryItems as $itemHeader) {
        $item = yovel_admin_inventory_item(['company_key_hash' => (string) $itemHeader['company_key_hash']], (string) $itemHeader['item_key']) ?? $itemHeader;
        $open = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_item' && (string) ($inventoryPrior['item_key'] ?? '') === (string) $item['item_key'];
        $inventoryRenderModal([
            'id' => 'inventory-item-modal-' . (string) $item['item_key'], 'title' => 'Edit Item', 'description' => 'Update this company catalogue record.',
            'open_label' => 'Edit Item', 'submit_label' => 'Save item', 'confirm_message' => 'Confirm these Inventory item changes before saving them.',
            'body_html' => $inventoryItemBody($item, $open ? $inventoryPrior : [], $open ? $inventoryStateError : ''),
            'hidden_html' => $inventoryItemHidden($item, $open ? $inventoryPrior : []),
        ], $open, false);
    }
}

$inventoryPriceBody = static function (array $record, array $prior, string $error, array $items) use ($inventoryEscape): string {
    $value = static fn (string $key, string $fallback = ''): string => $inventoryEscape((string) ($prior[$key] ?? $record[$key] ?? $fallback));
    $countriesValue = array_key_exists('countries', $prior) ? (string) $prior['countries'] : implode(',', is_array($record['countries'] ?? null) ? $record['countries'] : []);
    ob_start();
    if ($error !== ''): ?><div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $inventoryEscape($error) ?></div><?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Item<select name="item_key" required class="h-9 rounded-md border bg-background px-3"><option value="">Select item</option><?php foreach ($items as $item): ?><option value="<?= $inventoryEscape((string) $item['item_key']) ?>"<?= $value('item_key') === (string) $item['item_key'] ? ' selected' : '' ?>><?= $inventoryEscape((string) $item['item_code'] . ' - ' . (string) $item['item_name']) ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-1.5 text-sm font-medium">Price list code<input name="price_list_code" required maxlength="80" class="h-9 rounded-md border bg-background px-3" value="<?= $value('price_list_code', 'STANDARD-SELLING') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Price list name<input name="price_list_name" required maxlength="160" class="h-9 rounded-md border bg-background px-3" value="<?= $value('price_list_name', 'Standard Selling') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Currency<input name="currency_code" required maxlength="3" class="h-9 rounded-md border bg-background px-3" value="<?= $value('currency_code', 'PHP') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">UOM<input name="uom_code" required maxlength="40" class="h-9 rounded-md border bg-background px-3" value="<?= $value('uom_code', 'EA') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Rate<input name="rate" required inputmode="decimal" class="h-9 rounded-md border bg-background px-3" value="<?= $value('rate', '0.00') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Minimum quantity<input name="minimum_qty" required inputmode="decimal" class="h-9 rounded-md border bg-background px-3" value="<?= $value('minimum_qty', '0') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Valid from<input type="date" name="valid_from" required class="h-9 rounded-md border bg-background px-3" value="<?= $value('valid_from', date('Y-m-d')) ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium">Valid to<input type="date" name="valid_to" class="h-9 rounded-md border bg-background px-3" value="<?= $value('valid_to') ?>"></label>
        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">Countries<input name="countries" maxlength="200" class="h-9 rounded-md border bg-background px-3" value="<?= $inventoryEscape($countriesValue) ?>" placeholder="PH, SG"></label>
        <div class="flex items-center gap-4 sm:col-span-2"><label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_selling" value="0"><input type="checkbox" name="is_selling" value="1"<?= yovel_admin_inventory_catalogue_flag($prior['is_selling'] ?? $record['is_selling'] ?? 1) ? ' checked' : '' ?>>Selling</label><label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_buying" value="0"><input type="checkbox" name="is_buying" value="1"<?= yovel_admin_inventory_catalogue_flag($prior['is_buying'] ?? $record['is_buying'] ?? 0) ? ' checked' : '' ?>>Buying</label></div>
    </div>
    <?php return (string) ob_get_clean();
};
$inventoryPriceHidden = static fn (array $record, array $prior): string => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="inventory-warehouse"><input type="hidden" name="action" value="save_inventory_item_price"><input type="hidden" name="section" value="' . bx_h($inventoryItemReturnSection) . '"><input type="hidden" name="item_price_key" value="' . bx_h((string) ($prior['item_price_key'] ?? $record['item_price_key'] ?? '')) . '"><input type="hidden" name="price_status" value="' . bx_h((string) ($prior['price_status'] ?? $record['price_status'] ?? 'ACTIVE')) . '">';

$addPriceOpen = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_item_price' && trim((string) ($inventoryPrior['item_price_key'] ?? '')) === '';
$inventoryRenderModal([
    'id' => 'inventory-price-modal-new', 'title' => 'Add Item Price', 'description' => 'Set a dated company price-list rate and UOM.',
    'open_label' => 'Add Item Price', 'submit_label' => 'Save price', 'confirm_message' => 'Confirm this Inventory item price before saving it.',
    'body_html' => $inventoryPriceBody([], $addPriceOpen ? $inventoryPrior : [], $addPriceOpen ? $inventoryStateError : '', $inventoryItems),
    'hidden_html' => $inventoryPriceHidden([], $addPriceOpen ? $inventoryPrior : []),
], $addPriceOpen, $inventoryModalOriginSection !== 'dashboard');

if ($inventoryModalOriginSection !== 'dashboard') {
    foreach ($inventoryPrices as $price) {
        $open = !empty($inventoryFormState['open']) && $inventoryStateAction === 'save_inventory_item_price' && (string) ($inventoryPrior['item_price_key'] ?? '') === (string) $price['item_price_key'];
        $inventoryRenderModal([
            'id' => 'inventory-price-modal-' . (string) $price['item_price_key'], 'title' => 'Edit Item Price', 'description' => 'Update this dated company price-list rate.',
            'open_label' => 'Edit Item Price', 'submit_label' => 'Save price', 'confirm_message' => 'Confirm these Inventory item price changes before saving them.',
            'body_html' => $inventoryPriceBody($price, $open ? $inventoryPrior : [], $open ? $inventoryStateError : '', $inventoryItems),
            'hidden_html' => $inventoryPriceHidden($price, $open ? $inventoryPrior : []),
        ], $open, false);
    }
}
