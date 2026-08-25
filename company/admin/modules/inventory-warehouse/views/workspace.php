<?php
declare(strict_types=1);

$inventoryData = is_array($activeModuleData ?? null) ? $activeModuleData : [];
$inventoryControllerState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
if ($inventoryControllerState !== []
    && (string) ($inventoryControllerState['section'] ?? '') === (string) ($inventoryData['section'] ?? $activeModuleSection ?? 'items')) {
    $inventoryPriorInput = is_array($inventoryControllerState['input'] ?? null) ? $inventoryControllerState['input'] : [];
    $inventoryData['form_state'] = (string) ($inventoryData['section'] ?? '') === 'form-builder'
        ? [
            'open' => true,
            'record_type' => (string) ($inventoryPriorInput['record_type'] ?? $inventoryData['record_type'] ?? 'ITEM'),
            'schema_json' => (string) ($inventoryPriorInput['schema_json'] ?? ''),
            'new_field_label' => (string) ($inventoryPriorInput['new_field_label'] ?? ''),
            'error' => (string) ($inventoryControllerState['error'] ?? ''),
        ]
        : ['open' => true, 'action' => (string) ($inventoryControllerState['action'] ?? ''), 'input' => $inventoryPriorInput, 'error' => (string) ($inventoryControllerState['error'] ?? '')];
}
$inventorySections = is_array($inventoryData['sections'] ?? null) ? $inventoryData['sections'] : yovel_admin_inventory_warehouse_sections();
$inventorySection = (string) ($inventoryData['section'] ?? ($activeModuleSection ?? 'items'));
$inventoryState = is_array($inventoryData['state'] ?? null) ? $inventoryData['state'] : [];
$inventorySchema = is_array($inventoryData['active_form_schema'] ?? null) ? $inventoryData['active_form_schema'] : [];
$inventoryDashboardData = is_array($inventoryData['dashboard'] ?? null) ? $inventoryData['dashboard'] : [];
$inventoryEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<style>
    .yovel-inventory-workspace {
        grid-template-columns: minmax(0, 1fr);
    }
    @media (min-width: 1280px) {
        .yovel-inventory-workspace {
            grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr);
        }
    }
</style>
<div class="grid min-h-0 gap-3">
    <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Inventory and Warehouse sections">
        <?php foreach ($inventorySections as $sectionKey => $sectionMeta): ?>
            <a class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $inventorySection === $sectionKey ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=inventory-warehouse&amp;section=<?= $inventoryEscape((string) $sectionKey) ?>">
                <span class="material-symbols-rounded text-base" aria-hidden="true"><?= $inventoryEscape((string) ($sectionMeta['icon'] ?? 'inventory_2')) ?></span>
                <?= $inventoryEscape((string) ($sectionMeta['label'] ?? $sectionKey)) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div data-inventory-workspace class="yovel-inventory-workspace grid min-h-0 gap-4">
        <main data-inventory-main data-grid-span="12" class="flex min-h-[28rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="border-b px-5 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Inventory / Warehouse</p>
                        <h2 class="mt-1 text-base font-semibold"><?= $inventoryEscape((string) ($inventoryData['section_meta']['label'] ?? 'Items')) ?></h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                    <?php if ($inventorySection === 'dashboard'): ?>
                        <button type="button" data-record-modal-open="inventory-item-modal-new" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">
                            <span class="material-symbols-rounded text-base" aria-hidden="true">add</span>Add Item
                        </button>
                    <?php endif; ?>
                    <?php if ($inventorySection !== 'form-builder'): ?>
                        <a class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=inventory-warehouse&amp;section=form-builder&amp;record_type=<?= $inventoryEscape((string) ($inventoryData['record_type'] ?? 'ITEM')) ?>">
                            <span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder
                        </a>
                    <?php endif; ?>
                    </div>
                </div>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if ($inventorySection === 'dashboard'): ?>
                    <?php require __DIR__ . '/dashboard.php'; ?>
                <?php elseif ($inventorySection === 'form-builder'): ?>
                    <div class="grid gap-3">
                        <?php foreach ($inventorySchema['sections'] ?? [] as $schemaSection): ?>
                            <?php
                            $schemaSectionKey = (string) ($schemaSection['key'] ?? '');
                            $sectionFields = array_values(array_filter(
                                $inventorySchema['fields'] ?? [],
                                static fn (array $field): bool => (string) ($field['section'] ?? '') === $schemaSectionKey
                            ));
                            ?>
                            <section class="bg-muted/30 px-4 py-3" aria-labelledby="inventory-schema-<?= $inventoryEscape($schemaSectionKey) ?>">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 id="inventory-schema-<?= $inventoryEscape($schemaSectionKey) ?>" class="text-sm font-semibold"><?= $inventoryEscape((string) ($schemaSection['label'] ?? 'Section')) ?></h3>
                                    <span class="text-xs text-muted-foreground"><?= count($sectionFields) ?> fields</span>
                                </div>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <?php foreach ($sectionFields as $field): ?>
                                        <div class="min-w-0 px-1 py-1">
                                            <p class="truncate text-sm font-medium"><?= $inventoryEscape((string) ($field['label'] ?? 'Field')) ?></p>
                                            <p class="truncate font-mono text-[11px] text-muted-foreground"><?= $inventoryEscape((string) ($field['key'] ?? '')) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($inventorySection === 'items' && ($inventoryData['items'] ?? []) !== []): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[44rem] text-sm">
                            <thead class="border-b text-left text-xs text-muted-foreground"><tr><th class="px-4 py-3">Item</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Stock UOM</th><th class="px-4 py-3">Tracking</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                            <tbody class="divide-y">
                            <?php foreach ($inventoryData['items'] as $item): ?>
                                <tr>
                                    <td class="px-4 py-3"><span class="font-medium"><?= $inventoryEscape((string) $item['item_name']) ?></span><span class="block font-mono text-xs text-muted-foreground"><?= $inventoryEscape((string) $item['item_code']) ?></span></td>
                                    <td class="px-4 py-3"><?= $inventoryEscape((string) $item['item_kind']) ?></td><td class="px-4 py-3"><?= $inventoryEscape((string) $item['stock_uom_code']) ?></td>
                                    <td class="px-4 py-3"><?= (int) $item['has_serial_no'] === 1 ? 'Serial' : ((int) $item['has_batch_no'] === 1 ? 'Batch' : 'None') ?></td><td class="px-4 py-3"><?= $inventoryEscape((string) $item['item_status']) ?></td>
                                    <td class="px-4 py-3 text-right"><button type="button" data-record-modal-open="inventory-item-modal-<?= $inventoryEscape((string) $item['item_key']) ?>" class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Edit <?= $inventoryEscape((string) $item['item_code']) ?>" title="Edit item"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($inventorySection === 'warehouses' && ($inventoryData['warehouses'] ?? []) !== []): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[48rem] text-sm">
                            <thead class="border-b text-left text-xs text-muted-foreground"><tr><th class="px-4 py-3">Warehouse</th><th class="px-4 py-3">Parent / type</th><th class="px-4 py-3">Capacity</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                            <tbody class="divide-y"><?php foreach ($inventoryData['warehouses'] as $warehouse): ?>
                                <tr><td class="px-4 py-3"><span class="font-medium"><?= $inventoryEscape((string) $warehouse['warehouse_name']) ?></span><span class="block font-mono text-xs text-muted-foreground"><?= $inventoryEscape((string) $warehouse['warehouse_code']) ?><?= (int) $warehouse['is_group'] === 1 ? ' / Group' : '' ?></span></td><td class="px-4 py-3"><span><?= $inventoryEscape((string) ($warehouse['parent_warehouse_name'] ?? 'Root')) ?></span><span class="block text-xs text-muted-foreground"><?= $inventoryEscape((string) ($warehouse['warehouse_type_name'] ?? 'No type')) ?></span></td><td class="px-4 py-3 font-mono text-xs"><?= (int) $warehouse['is_group'] === 1 ? 'Not stock-holding' : $inventoryEscape((string) $warehouse['capacity_used']) . ' / ' . ((string) $warehouse['capacity_qty'] === '0.000000000' ? 'Unlimited' : $inventoryEscape((string) $warehouse['capacity_qty'])) ?></td><td class="px-4 py-3"><?= $inventoryEscape((string) $warehouse['warehouse_status']) ?></td><td class="px-4 py-3 text-right"><button type="button" data-record-modal-open="inventory-warehouse-modal-<?= $inventoryEscape((string) $warehouse['warehouse_key']) ?>" class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Edit <?= $inventoryEscape((string) $warehouse['warehouse_code']) ?>" title="Edit warehouse"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button></td></tr>
                            <?php endforeach; ?></tbody>
                        </table>
                    </div>
                <?php elseif ($inventorySection === 'putaway' && ($inventoryData['putaway_rules'] ?? []) !== []): ?>
                    <div class="overflow-x-auto"><table class="w-full min-w-[42rem] text-sm"><thead class="border-b text-left text-xs text-muted-foreground"><tr><th class="px-4 py-3">Priority</th><th class="px-4 py-3">Item</th><th class="px-4 py-3">Warehouse</th><th class="px-4 py-3">Dimensions</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y"><?php foreach ($inventoryData['putaway_rules'] as $rule): ?><tr><td class="px-4 py-3 font-mono"><?= (int) $rule['priority'] ?></td><td class="px-4 py-3"><?= $inventoryEscape((string) ($rule['item_code'] ?? 'All stocked items')) ?></td><td class="px-4 py-3"><?= $inventoryEscape((string) $rule['warehouse_code']) ?></td><td class="px-4 py-3 font-mono text-xs"><?= $inventoryEscape((string) $rule['dimensions_json']) ?></td><td class="px-4 py-3 text-right"><button type="button" data-record-modal-open="inventory-putaway-modal-<?= $inventoryEscape((string) $rule['putaway_rule_key']) ?>" class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Edit putaway <?= $inventoryEscape((string) $rule['warehouse_code']) ?>" title="Edit Putaway Rule"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button></td></tr><?php endforeach; ?></tbody></table></div>
                <?php elseif ($inventorySection === 'reorder-levels' && ($inventoryData['reorder_rules'] ?? []) !== []): ?>
                    <div class="grid gap-5"><div class="overflow-x-auto"><table class="w-full min-w-[42rem] text-sm"><thead class="border-b text-left text-xs text-muted-foreground"><tr><th class="px-4 py-3">Item</th><th class="px-4 py-3">Warehouse</th><th class="px-4 py-3">Level</th><th class="px-4 py-3">Order qty</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y"><?php foreach ($inventoryData['reorder_rules'] as $rule): ?><tr><td class="px-4 py-3"><?= $inventoryEscape((string) $rule['item_code']) ?></td><td class="px-4 py-3"><?= $inventoryEscape((string) $rule['warehouse_code']) ?></td><td class="px-4 py-3 font-mono text-xs"><?= $inventoryEscape((string) $rule['reorder_level']) ?></td><td class="px-4 py-3 font-mono text-xs"><?= $inventoryEscape((string) $rule['reorder_quantity']) ?></td><td class="px-4 py-3 text-right"><button type="button" data-record-modal-open="inventory-reorder-modal-<?= $inventoryEscape((string) $rule['reorder_rule_key']) ?>" class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Edit reorder <?= $inventoryEscape((string) $rule['item_code']) ?>" title="Edit reorder rule"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button></td></tr><?php endforeach; ?></tbody></table></div><?php if (($inventoryData['reorder_recommendations'] ?? []) !== []): ?><section class="border-t pt-4" aria-labelledby="inventory-reorder-recommendations"><h3 id="inventory-reorder-recommendations" class="text-sm font-semibold">Items to be requested</h3><div class="mt-3 grid gap-2"><?php foreach ($inventoryData['reorder_recommendations'] as $recommendation): ?><div class="flex items-center justify-between gap-3 bg-muted/30 px-3 py-2 text-sm"><span><?= $inventoryEscape((string) $recommendation['item_code'] . ' / ' . (string) $recommendation['warehouse_code']) ?></span><span class="font-mono text-xs"><?= $inventoryEscape((string) $recommendation['recommended_quantity']) ?></span></div><?php endforeach; ?></div></section><?php endif; ?></div>
                <?php elseif (in_array($inventorySection, ['batch-numbers', 'serial-numbers'], true) && ($inventoryData[$inventorySection === 'batch-numbers' ? 'batches' : 'serials'] ?? []) !== []): ?>
                    <?php $trackingRecords = $inventoryData[$inventorySection === 'batch-numbers' ? 'batches' : 'serials']; $trackingIsBatch = $inventorySection === 'batch-numbers'; ?>
                    <div data-inventory-<?= $trackingIsBatch ? 'batches' : 'serials' ?> class="grid gap-5">
                        <section data-inventory-availability aria-labelledby="inventory-availability-title"><div class="flex flex-wrap items-center justify-between gap-3"><h3 id="inventory-availability-title" class="text-sm font-semibold">Warehouse availability</h3><span class="text-xs text-muted-foreground"><?= count($inventoryData['availability'] ?? []) ?> available</span></div><form method="get" class="mt-3 grid gap-3 sm:grid-cols-3"><input type="hidden" name="view" value="inventory-warehouse"><input type="hidden" name="section" value="<?= $trackingIsBatch ? 'batch-numbers' : 'serial-numbers' ?>"><label class="grid gap-1.5 text-sm font-medium">Item<select name="item" required class="h-9 rounded-md border bg-background px-3"><option value="">Select item</option><?php foreach ($inventoryData['items'] ?? [] as $item): ?><option value="<?= $inventoryEscape((string) $item['item_key']) ?>"<?= (string) ($inventoryData['availability_filters']['item_key'] ?? '') === (string) $item['item_key'] ? ' selected' : '' ?>><?= $inventoryEscape((string) $item['item_code']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm font-medium">Warehouse<select name="warehouse" required class="h-9 rounded-md border bg-background px-3"><option value="">Select warehouse</option><?php foreach ($inventoryData['warehouses'] ?? [] as $warehouse): ?><option value="<?= $inventoryEscape((string) $warehouse['warehouse_key']) ?>"<?= (string) ($inventoryData['availability_filters']['warehouse_key'] ?? '') === (string) $warehouse['warehouse_key'] ? ' selected' : '' ?>><?= $inventoryEscape((string) $warehouse['warehouse_code']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm font-medium">As of<input type="datetime-local" name="as_of" required class="h-9 rounded-md border bg-background px-3" value="<?= $inventoryEscape(str_replace(' ', 'T', substr((string) ($inventoryData['availability_filters']['as_of'] ?? ''), 0, 16))) ?>"></label><button class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted sm:col-span-3" type="submit"><span class="material-symbols-rounded text-base" aria-hidden="true">search</span>Check availability</button></form><?php if (($inventoryData['availability'] ?? []) !== []): ?><div class="mt-3 divide-y border-y"><?php foreach ($inventoryData['availability'] as $available): ?><div class="flex items-center justify-between gap-3 px-2 py-2 text-sm"><span class="font-mono text-xs"><?= $inventoryEscape((string) $available[$trackingIsBatch ? 'batch_number' : 'serial_number']) ?></span><span><?= $trackingIsBatch ? $inventoryEscape((string) $available['available_qty']) : 'Available' ?></span></div><?php endforeach; ?></div><?php endif; ?></section>
                        <div class="overflow-x-auto"><table class="w-full min-w-[42rem] text-sm"><thead class="border-b text-left text-xs text-muted-foreground"><tr><th class="px-4 py-3"><?= $trackingIsBatch ? 'Batch' : 'Serial' ?></th><th class="px-4 py-3">Item</th><?php if ($trackingIsBatch): ?><th class="px-4 py-3">Expiry</th><?php endif; ?><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y"><?php foreach ($trackingRecords as $record): $recordKey = (string) $record[$trackingIsBatch ? 'batch_key' : 'serial_key']; ?><tr><td class="px-4 py-3 font-mono text-xs"><a class="font-medium hover:underline" href="./?view=inventory-warehouse&amp;section=<?= $trackingIsBatch ? 'batch-numbers' : 'serial-numbers' ?>&amp;trace=<?= $inventoryEscape($recordKey) ?>"><?= $inventoryEscape((string) $record[$trackingIsBatch ? 'batch_number' : 'serial_number']) ?></a></td><td class="px-4 py-3"><?= $inventoryEscape((string) $record['item_code']) ?></td><?php if ($trackingIsBatch): ?><td class="px-4 py-3"><?= $inventoryEscape((string) ($record['expiry_date'] ?? 'No expiry')) ?></td><?php endif; ?><td class="px-4 py-3"><?= $inventoryEscape((string) $record[$trackingIsBatch ? 'batch_status' : 'serial_status']) ?></td><td class="px-4 py-3 text-right"><button type="button" data-record-modal-open="inventory-<?= $trackingIsBatch ? 'batch' : 'serial' ?>-modal-<?= $inventoryEscape($recordKey) ?>" class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Edit <?= $trackingIsBatch ? 'Batch' : 'Serial' ?>" title="Edit <?= $trackingIsBatch ? 'Batch' : 'Serial' ?>"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button></td></tr><?php endforeach; ?></tbody></table></div>
                        <?php if (is_array($inventoryData['trace'] ?? null)): ?><section data-inventory-trace class="border-t pt-4" aria-labelledby="inventory-trace-title"><h3 id="inventory-trace-title" class="text-sm font-semibold">Movement trace</h3><div class="mt-3 divide-y border-y"><?php foreach ($inventoryData['trace']['movements'] as $movement): ?><div class="grid gap-1 px-2 py-3 text-sm sm:grid-cols-3"><span><?= $inventoryEscape((string) $movement['posting_datetime']) ?></span><span><?= $inventoryEscape((string) $movement['warehouse_code']) ?></span><span class="font-mono text-xs sm:text-right"><?= $inventoryEscape((string) $movement['quantity']) ?> / <?= $inventoryEscape((string) $movement['stock_value_difference']) ?></span></div><?php endforeach; ?></div></section><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid min-h-[20rem] place-items-center text-center">
                        <div class="max-w-md">
                            <span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true"><?= $inventoryEscape(($inventoryState['kind'] ?? '') === 'empty' ? 'inventory_2' : 'hourglass_top') ?></span>
                            <h3 class="mt-3 text-base font-semibold"><?= $inventoryEscape((string) ($inventoryState['title'] ?? 'Inventory workspace')) ?></h3>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= $inventoryEscape((string) ($inventoryState['message'] ?? '')) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <aside data-inventory-tools data-grid-span="8" class="flex min-h-[28rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="border-b px-5 py-4">
                <h2 class="text-base font-semibold">Actions and tools</h2>
                <p class="mt-1 text-sm text-muted-foreground"><?= $inventoryEscape((string) ($inventoryData['company_name'] ?? $companyName ?? 'Company')) ?></p>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if (in_array($inventorySection, ['dashboard', 'form-builder', 'items', 'warehouses', 'putaway', 'reorder-levels', 'batch-numbers', 'serial-numbers'], true)): ?>
                    <?php require __DIR__ . '/record-modal.php'; ?>
                    <?php if ($inventorySection === 'dashboard'): ?>
                        <section aria-labelledby="inventory-dashboard-actions-title">
                            <h3 id="inventory-dashboard-actions-title" class="text-sm font-semibold">Quick actions</h3>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="button" data-record-modal-open="inventory-item-modal-new" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>Add Item</button>
                                <button type="button" data-record-modal-open="inventory-price-modal-new" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">sell</span>Add Item Price</button>
                            </div>
                        </section>
                        <section data-inventory-dashboard-setup class="mt-6" aria-labelledby="inventory-dashboard-setup-title">
                            <h3 id="inventory-dashboard-setup-title" class="text-sm font-semibold">Setup</h3>
                            <div class="mt-2 divide-y border-y">
                                <?php foreach ($inventoryDashboardData['setup'] ?? [] as $step): ?>
                                    <a href="<?= $inventoryEscape((string) ($step['href'] ?? '#')) ?>" class="flex items-center justify-between gap-3 px-2 py-2 text-sm hover:bg-muted/40">
                                        <span class="min-w-0"><?= $inventoryEscape((string) ($step['label'] ?? 'Setup step')) ?></span>
                                        <span class="material-symbols-rounded shrink-0 text-base" aria-hidden="true"><?= !empty($step['complete']) ? 'check_circle' : 'radio_button_unchecked' ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <section data-inventory-dashboard-alerts class="mt-6" aria-labelledby="inventory-dashboard-alerts-title">
                            <h3 id="inventory-dashboard-alerts-title" class="text-sm font-semibold">Alerts</h3>
                            <div class="mt-2 divide-y border-y">
                                <?php foreach ($inventoryDashboardData['alerts'] ?? [] as $alert): ?>
                                    <a href="<?= $inventoryEscape((string) ($alert['href'] ?? '#')) ?>" class="flex items-start justify-between gap-3 px-2 py-2 text-sm hover:bg-muted/40"><span><?= $inventoryEscape((string) ($alert['label'] ?? 'Inventory alert')) ?></span><span class="shrink-0 text-xs font-medium"><?= $inventoryEscape((string) ($alert['severity'] ?? 'INFO')) ?></span></a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <section class="mt-6" aria-labelledby="inventory-dashboard-dependencies-title">
                            <h3 id="inventory-dashboard-dependencies-title" class="text-sm font-semibold">Data services</h3>
                            <dl class="mt-2 divide-y border-y">
                                <?php foreach ($inventoryDashboardData['dependencies'] ?? [] as $dependency): ?>
                                    <div class="flex items-center justify-between gap-3 px-2 py-2 text-sm"><dt><?= $inventoryEscape((string) ($dependency['key'] ?? 'Service')) ?></dt><dd class="text-xs font-medium"><?= $inventoryEscape((string) ($dependency['status'] ?? 'ERROR')) ?></dd></div>
                                <?php endforeach; ?>
                            </dl>
                        </section>
                        <div class="mt-6 grid gap-2">
                            <a class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=inventory-warehouse&amp;section=form-builder&amp;record_type=ITEM"><span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder</a>
                            <button type="button" data-inventory-dashboard-tour-open class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">tour</span>Show guided tour</button>
                        </div>
                    <?php elseif ($inventorySection === 'form-builder'): ?>
                    <dl class="mt-5 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">Record type</dt>
                            <dd class="mt-1 font-medium"><?= $inventoryEscape((string) ($inventoryData['record_type'] ?? 'ITEM')) ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Version</dt>
                            <dd class="mt-1 font-medium"><?= (int) ($inventorySchema['version'] ?? 1) ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Fields</dt>
                            <dd class="mt-1 font-medium"><?= count($inventorySchema['fields'] ?? []) ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Columns</dt>
                            <dd class="mt-1 font-medium">Up to 3</dd>
                        </div>
                    </dl>
                    <?php elseif ($inventorySection === 'items'): ?>
                        <dl class="mt-5 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm">
                            <div><dt class="text-xs text-muted-foreground">Items</dt><dd class="mt-1 font-medium"><?= count($inventoryData['items'] ?? []) ?></dd></div>
                            <div><dt class="text-xs text-muted-foreground">Prices</dt><dd class="mt-1 font-medium"><?= count($inventoryData['item_prices'] ?? []) ?></dd></div>
                            <div><dt class="text-xs text-muted-foreground">Active</dt><dd class="mt-1 font-medium"><?= count(array_filter($inventoryData['items'] ?? [], static fn (array $item): bool => (string) $item['item_status'] === 'ACTIVE')) ?></dd></div>
                            <div><dt class="text-xs text-muted-foreground">Templates</dt><dd class="mt-1 font-medium"><?= count(array_filter($inventoryData['items'] ?? [], static fn (array $item): bool => (int) $item['is_template'] === 1)) ?></dd></div>
                        </dl>
                        <?php if (($inventoryData['item_prices'] ?? []) !== []): ?>
                            <div class="mt-4 grid gap-2" aria-label="Item price actions">
                                <?php foreach ($inventoryData['item_prices'] as $price): ?>
                                    <div class="flex items-center justify-between gap-3 border-b py-2 text-sm"><span class="min-w-0 truncate"><?= $inventoryEscape((string) $price['item_code'] . ' / ' . (string) $price['price_list_code']) ?></span><button type="button" data-record-modal-open="inventory-price-modal-<?= $inventoryEscape((string) $price['item_price_key']) ?>" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border" aria-label="Edit price <?= $inventoryEscape((string) $price['item_code']) ?>" title="Edit item price"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($inventorySection === 'warehouses'): ?>
                        <dl class="mt-5 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground">Warehouses</dt><dd class="mt-1 font-medium"><?= count($inventoryData['warehouses'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Stock locations</dt><dd class="mt-1 font-medium"><?= count(array_filter($inventoryData['warehouses'] ?? [], static fn (array $warehouse): bool => (int) $warehouse['is_group'] === 0)) ?></dd></div><div><dt class="text-xs text-muted-foreground">Types</dt><dd class="mt-1 font-medium"><?= count($inventoryData['warehouse_types'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Dimensions</dt><dd class="mt-1 font-medium"><?= count($inventoryData['dimensions'] ?? []) ?></dd></div></dl>
                        <?php if (($inventoryData['warehouse_types'] ?? []) !== [] || ($inventoryData['dimensions'] ?? []) !== []): ?><div class="mt-4 grid gap-2" aria-label="Warehouse setup actions"><?php foreach ($inventoryData['warehouse_types'] ?? [] as $type): ?><div class="flex items-center justify-between gap-3 border-b py-2 text-sm"><span class="min-w-0 truncate"><?= $inventoryEscape((string) $type['warehouse_type_name']) ?></span><button type="button" data-record-modal-open="inventory-warehouse-type-modal-<?= $inventoryEscape((string) $type['warehouse_type_key']) ?>" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border" aria-label="Edit type <?= $inventoryEscape((string) $type['warehouse_type_code']) ?>" title="Edit Warehouse Type"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button></div><?php endforeach; ?><?php foreach ($inventoryData['dimensions'] ?? [] as $dimension): ?><div class="flex items-center justify-between gap-3 border-b py-2 text-sm"><span class="min-w-0 truncate"><?= $inventoryEscape((string) $dimension['dimension_name']) ?></span><button type="button" data-record-modal-open="inventory-dimension-modal-<?= $inventoryEscape((string) $dimension['dimension_key']) ?>" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border" aria-label="Edit dimension <?= $inventoryEscape((string) $dimension['dimension_code']) ?>" title="Edit Inventory Dimension"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button></div><?php endforeach; ?></div><?php endif; ?>
                    <?php elseif (in_array($inventorySection, ['batch-numbers', 'serial-numbers'], true)): ?>
                        <dl class="mt-5 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground"><?= $inventorySection === 'batch-numbers' ? 'Batches' : 'Serials' ?></dt><dd class="mt-1 font-medium"><?= count($inventoryData[$inventorySection === 'batch-numbers' ? 'batches' : 'serials'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Tracked items</dt><dd class="mt-1 font-medium"><?= count($inventoryData['items'] ?? []) ?></dd></div></dl>
                        <a class="mt-4 inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=inventory-warehouse&amp;section=form-builder&amp;record_type=<?= $inventorySection === 'batch-numbers' ? 'BATCH' : 'SERIAL' ?>"><span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder</a>
                    <?php elseif ($inventorySection === 'putaway'): ?>
                        <dl class="mt-5 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground">Rules</dt><dd class="mt-1 font-medium"><?= count($inventoryData['putaway_rules'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Active locations</dt><dd class="mt-1 font-medium"><?= count(array_filter($inventoryData['warehouses'] ?? [], static fn (array $warehouse): bool => (int) $warehouse['is_group'] === 0 && (string) $warehouse['warehouse_status'] === 'ACTIVE')) ?></dd></div></dl>
                    <?php else: ?>
                        <dl class="mt-5 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground">Reorder rules</dt><dd class="mt-1 font-medium"><?= count($inventoryData['reorder_rules'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">To request</dt><dd class="mt-1 font-medium"><?= count($inventoryData['reorder_recommendations'] ?? []) ?></dd></div></dl>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="grid gap-4">
                        <div class="bg-muted/30 p-3">
                            <p class="text-xs font-medium text-muted-foreground">State</p>
                            <p class="mt-1 text-sm font-semibold"><?= $inventoryEscape(ucfirst((string) ($inventoryState['kind'] ?? 'dependency'))) ?></p>
                        </div>
                        <?php if (($inventoryState['dependencies'] ?? []) !== []): ?>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Dependency</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <?php foreach ($inventoryState['dependencies'] as $dependency): ?>
                                        <span class="rounded-sm bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground"><?= $inventoryEscape((string) $dependency) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
<?php if ($inventorySection === 'dashboard'): ?>
<div data-inventory-dashboard-tour class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="inventory-dashboard-tour-title" hidden>
    <div class="w-full max-w-md rounded-lg border bg-card shadow-xl">
        <header class="border-b px-5 py-4">
            <p data-inventory-dashboard-tour-progress class="text-xs font-medium text-muted-foreground">Step 1 of 6</p>
            <h2 id="inventory-dashboard-tour-title" data-inventory-dashboard-tour-title class="mt-1 text-base font-semibold">Live stock signals</h2>
        </header>
        <div class="px-5 py-4">
            <p data-inventory-dashboard-tour-body class="text-sm leading-6 text-muted-foreground">Review current item, warehouse, replenishment, shortage, and capacity counts.</p>
        </div>
        <footer class="flex items-center justify-between gap-3 border-t px-5 py-4">
            <button type="button" data-inventory-dashboard-tour-skip class="h-9 rounded-md px-3 text-sm font-medium hover:bg-muted">Skip</button>
            <div class="flex gap-2">
                <button type="button" data-inventory-dashboard-tour-back class="h-9 rounded-md border px-3 text-sm font-medium hover:bg-muted">Back</button>
                <button type="button" data-inventory-dashboard-tour-next class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Next</button>
            </div>
        </footer>
    </div>
</div>
<script>
(() => {
    const dialog = document.querySelector('[data-inventory-dashboard-tour]');
    const openers = Array.from(document.querySelectorAll('[data-inventory-dashboard-tour-open]'));
    if (!dialog || openers.length === 0) return;
    const steps = [
        { target: '[data-inventory-dashboard-summary]', title: 'Live stock signals', body: 'Review current item, warehouse, replenishment, shortage, and capacity counts.' },
        { target: '[data-inventory-dashboard-queue]', title: 'Operations queue', body: 'Prioritize reorder, projected shortage, capacity, and putaway work.' },
        { target: '[data-inventory-dashboard-activity]', title: 'Recent activity', body: 'Trace company Inventory changes with actor and timestamp context.' },
        { target: '[data-inventory-dashboard-shortcuts]', title: 'Operational shortcuts', body: 'Move directly to implemented Inventory masters and controls.' },
        { target: '[data-inventory-dashboard-directory]', title: 'Inventory directory', body: 'Open operational, reporting, and master-data destinations from one place.' },
        { target: '[data-inventory-dashboard-setup]', title: 'Setup and tools', body: 'Track configuration, review alerts, open Form Builder, or create a record.' },
    ];
    const storageKey = <?= json_encode('builderx:inventory-warehouse:' . (string) ($inventoryData['company_key_hash'] ?? '') . ':dashboard-tour', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const title = dialog.querySelector('[data-inventory-dashboard-tour-title]');
    const body = dialog.querySelector('[data-inventory-dashboard-tour-body]');
    const progress = dialog.querySelector('[data-inventory-dashboard-tour-progress]');
    const back = dialog.querySelector('[data-inventory-dashboard-tour-back]');
    const next = dialog.querySelector('[data-inventory-dashboard-tour-next]');
    const skip = dialog.querySelector('[data-inventory-dashboard-tour-skip]');
    let index = 0;
    let opener = null;
    let highlighted = null;

    const render = () => {
        const step = steps[index];
        if (highlighted) highlighted.classList.remove('ring-2', 'ring-primary', 'ring-offset-2');
        highlighted = document.querySelector(step.target);
        if (highlighted) highlighted.classList.add('ring-2', 'ring-primary', 'ring-offset-2');
        title.textContent = step.title;
        body.textContent = step.body;
        progress.textContent = `Step ${index + 1} of ${steps.length}`;
        back.disabled = index === 0;
        next.textContent = index === steps.length - 1 ? 'Finish' : 'Next';
    };
    const close = (state) => {
        dialog.hidden = true;
        if (highlighted) highlighted.classList.remove('ring-2', 'ring-primary', 'ring-offset-2');
        highlighted = null;
        try { window.localStorage.setItem(storageKey, state); } catch (error) {}
        if (opener) opener.focus();
    };
    const open = (event) => {
        opener = event.currentTarget;
        index = 0;
        dialog.hidden = false;
        render();
        next.focus();
    };
    openers.forEach((button) => button.addEventListener('click', open));
    back.addEventListener('click', () => { if (index > 0) { index -= 1; render(); } });
    next.addEventListener('click', () => { if (index < steps.length - 1) { index += 1; render(); } else { close('completed'); } });
    skip.addEventListener('click', () => close('dismissed'));
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { event.preventDefault(); close('dismissed'); return; }
        if (event.key !== 'Tab') return;
        const focusable = Array.from(dialog.querySelectorAll('button:not([disabled])'));
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
})();
</script>
<?php endif; ?>
