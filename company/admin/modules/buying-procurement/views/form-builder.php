<?php
$buyingAdapter = is_array($activeModuleData['formAdapter'] ?? null)
    ? $activeModuleData['formAdapter']
    : yovel_admin_buying_procurement_form_adapter();
$buyingSchemas = is_array($activeModuleData['formSchemas'] ?? null)
    ? $activeModuleData['formSchemas']
    : yovel_admin_buying_procurement_default_form_schemas();
$selectedFormTarget = (string) ($activeModuleData['selectedFormTarget'] ?? 'supplier');
$selectedSchema = $buyingSchemas[$selectedFormTarget] ?? $buyingSchemas['supplier'];
?>
<div class="grid gap-5">
    <div>
        <h3 class="text-sm font-semibold">Buying Form Builder</h3>
        <p class="mt-1 text-sm leading-6 text-muted-foreground">Inspect the built-in schema contract for each configurable procurement record.</p>
    </div>
    <form method="get" data-confirm-submit data-confirm-message="Load this Buying Form Builder target?" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        <input type="hidden" name="view" value="buying-procurement">
        <input type="hidden" name="module_view" value="buying-procurement">
        <input type="hidden" name="section" value="form-builder">
        <label class="grid gap-1.5 text-sm">
            <span class="font-medium">Record target</span>
            <select name="form_target" class="h-9 rounded-md border bg-background px-3" required>
                <?php foreach ($buyingAdapter['target_record_types'] as $sectionKey => $recordType): ?>
                    <option value="<?= bx_h((string) $recordType) ?>" <?= $selectedFormTarget === $recordType ? 'selected' : '' ?>><?= bx_h((string) (yovel_admin_buying_procurement_sections()[$sectionKey]['label'] ?? $recordType)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">
            <span class="material-symbols-rounded text-base" aria-hidden="true">preview</span>Review target
        </button>
    </form>
    <section class="border-t pt-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h4 class="text-sm font-semibold"><?= bx_h((string) $selectedSchema['recordType']) ?></h4>
                <p class="mt-1 text-xs text-muted-foreground"><?= count($selectedSchema['fields'] ?? []) ?> built-in fields across <?= count($selectedSchema['sections'] ?? []) ?> sections</p>
            </div>
            <span class="inline-flex rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-secondary-foreground">Draft contract</span>
        </div>
        <div class="mt-4 divide-y border-y">
            <?php foreach ($selectedSchema['fields'] ?? [] as $field): ?>
                <div class="grid gap-1 py-3 sm:grid-cols-[minmax(0,1fr)_8rem_6rem] sm:items-center">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium"><?= bx_h((string) $field['label']) ?></p>
                        <p class="truncate text-xs text-muted-foreground"><?= bx_h((string) $field['key']) ?></p>
                    </div>
                    <span class="text-xs text-muted-foreground"><?= bx_h((string) $field['type']) ?></span>
                    <span class="text-xs <?= !empty($field['system']) ? 'font-medium text-foreground' : 'text-muted-foreground' ?>"><?= !empty($field['system']) ? 'Protected' : 'Configurable' ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <div class="border-t pt-4">
        <p class="text-sm font-medium">Protected workflow fields</p>
        <p class="mt-1 text-sm leading-6 text-muted-foreground">Stable keys, company scope, document identity, and lifecycle status remain protected when fields are reordered.</p>
    </div>
</div>
