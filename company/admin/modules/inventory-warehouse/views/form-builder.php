<?php
declare(strict_types=1);

$inventoryEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$inventorySchema = is_array($inventoryFormSchema ?? null) ? $inventoryFormSchema : [];
$inventoryAdapter = is_array($inventoryFormAdapter ?? null) ? $inventoryFormAdapter : [];
$inventoryState = is_array($inventoryFormState ?? null) ? $inventoryFormState : [];
$inventoryFields = is_array($inventorySchema['fields'] ?? null) ? $inventorySchema['fields'] : [];
$inventoryRecordType = (string) ($inventorySchema['recordType'] ?? 'ITEM');
?>
<?php if (trim((string) ($inventoryState['error'] ?? '')) !== ''): ?>
    <div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
        <?= $inventoryEscape((string) $inventoryState['error']) ?>
    </div>
<?php endif; ?>
<div class="grid min-h-0 gap-0 lg:grid-cols-[minmax(10rem,0.8fr)_minmax(16rem,1.5fr)_minmax(14rem,1fr)]">
    <section class="min-w-0 py-1 pr-4 lg:border-r" aria-labelledby="inventory-field-toolbox-title">
        <h3 id="inventory-field-toolbox-title" class="text-sm font-semibold">Field Toolbox</h3>
        <div class="mt-3 grid gap-1 text-xs text-muted-foreground">
            <?php foreach (array_slice($inventoryAdapter['field_types'] ?? [], 0, 10) as $fieldType): ?>
                <span class="rounded-sm bg-muted/50 px-2 py-1.5"><?= $inventoryEscape(ucwords(strtolower(str_replace('_', ' ', (string) $fieldType)))) ?></span>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="min-w-0 px-4 py-1 lg:border-r" aria-labelledby="inventory-form-layout-title">
        <div class="flex items-center justify-between gap-3">
            <h3 id="inventory-form-layout-title" class="text-sm font-semibold">Form Layout</h3>
            <span class="text-xs text-muted-foreground"><?= count($inventoryFields) ?> fields</span>
        </div>
        <div class="mt-3 divide-y rounded-md bg-muted/30">
            <?php foreach ($inventoryFields as $field): ?>
                <div class="grid gap-1 px-3 py-2.5" data-inventory-form-field="<?= $inventoryEscape((string) ($field['key'] ?? '')) ?>">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-medium"><?= $inventoryEscape((string) ($field['label'] ?? 'Field')) ?></span>
                        <span class="text-xs text-muted-foreground"><?= $inventoryEscape((string) ($field['type'] ?? 'SHORT_TEXT')) ?></span>
                    </div>
                    <span class="font-mono text-[11px] text-muted-foreground"><?= $inventoryEscape((string) ($field['key'] ?? '')) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="min-w-0 py-1 pl-4" aria-labelledby="inventory-field-properties-title">
        <h3 id="inventory-field-properties-title" class="text-sm font-semibold">Field Properties</h3>
        <div class="mt-3 grid gap-3">
            <label class="grid gap-1.5 text-sm font-medium">
                Label
                <input class="h-9 rounded-md border bg-background px-3 text-sm" name="new_field_label" maxlength="180" value="<?= $inventoryEscape((string) ($inventoryState['new_field_label'] ?? '')) ?>" placeholder="Custom field label">
            </label>
            <label class="grid gap-1.5 text-sm font-medium">
                Type
                <select class="h-9 rounded-md border bg-background px-3 text-sm" name="new_field_type">
                    <?php foreach ($inventoryAdapter['field_types'] ?? [] as $fieldType): ?>
                        <option value="<?= $inventoryEscape((string) $fieldType) ?>"><?= $inventoryEscape(ucwords(strtolower(str_replace('_', ' ', (string) $fieldType)))) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="grid gap-1.5 text-sm font-medium">
                Section
                <input class="h-9 rounded-md border bg-background px-3 text-sm" name="new_field_section" maxlength="80" value="custom">
            </label>
            <label class="grid gap-1.5 text-sm font-medium">
                Column
                <select class="h-9 rounded-md border bg-background px-3 text-sm" name="new_field_column">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                </select>
            </label>
            <label class="flex items-center gap-2 text-sm font-medium">
                <input class="size-4 rounded border" type="checkbox" name="new_field_required" value="1">
                Required field
            </label>
        </div>
    </section>
</div>

