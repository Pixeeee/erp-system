<?php
declare(strict_types=1);

$assetsBuilderEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetsBuilderSchema = is_array($assetsFormSchema ?? null) ? $assetsFormSchema : [];
$assetsBuilderAdapter = is_array($assetsFormAdapter ?? null) ? $assetsFormAdapter : [];
$assetsBuilderFields = is_array($assetsBuilderSchema['fields'] ?? null) ? $assetsBuilderSchema['fields'] : [];
$assetsBuilderSections = is_array($assetsBuilderSchema['sections'] ?? null) ? $assetsBuilderSchema['sections'] : [];
?>
<div class="grid min-h-0 gap-5 lg:grid-cols-[minmax(11rem,0.7fr)_minmax(18rem,1.5fr)_minmax(13rem,0.9fr)]">
    <section class="min-w-0 lg:border-r lg:pr-4" aria-labelledby="assets-builder-toolbox-title">
        <h3 id="assets-builder-toolbox-title" class="text-sm font-semibold">Field toolbox</h3>
        <div class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-1">
            <?php foreach ($assetsBuilderAdapter['field_types'] ?? [] as $fieldType): ?>
                <span class="rounded-sm bg-muted/50 px-2 py-1.5 text-xs text-muted-foreground"><?= $assetsBuilderEscape(ucwords(strtolower(str_replace('_', ' ', (string) $fieldType)))) ?></span>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="min-w-0 lg:border-r lg:pr-4" aria-labelledby="assets-builder-layout-title">
        <div class="flex items-center justify-between gap-3">
            <h3 id="assets-builder-layout-title" class="text-sm font-semibold">Form layout</h3>
            <span class="text-xs text-muted-foreground">Version <?= (int) ($assetsBuilderSchema['version'] ?? 1) ?></span>
        </div>
        <div class="mt-3 grid gap-4">
            <?php foreach ($assetsBuilderSections as $schemaSection): ?>
                <?php
                $sectionKey = (string) ($schemaSection['key'] ?? 'section');
                $sectionFields = array_values(array_filter($assetsBuilderFields, static fn (array $field): bool => (string) ($field['section'] ?? '') === $sectionKey));
                ?>
                <div class="border-l-2 border-border pl-3" data-assets-form-section="<?= $assetsBuilderEscape($sectionKey) ?>">
                    <div class="flex items-center justify-between gap-2"><h4 class="text-xs font-semibold uppercase text-muted-foreground"><?= $assetsBuilderEscape((string) ($schemaSection['label'] ?? 'Section')) ?></h4><span class="text-[11px] text-muted-foreground"><?= count($sectionFields) ?> fields</span></div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <?php foreach ($sectionFields as $field): ?>
                            <div draggable="<?= empty($field['system']) ? 'true' : 'false' ?>" data-assets-form-field="<?= $assetsBuilderEscape((string) ($field['key'] ?? '')) ?>" class="min-w-0 bg-muted/30 px-3 py-2">
                                <div class="flex items-center justify-between gap-2"><span class="truncate text-sm font-medium"><?= $assetsBuilderEscape((string) ($field['label'] ?? 'Field')) ?></span><span class="text-[11px] text-muted-foreground"><?= $assetsBuilderEscape((string) ($field['type'] ?? 'SHORT_TEXT')) ?></span></div>
                                <p class="mt-1 truncate font-mono text-[11px] text-muted-foreground"><?= $assetsBuilderEscape((string) ($field['key'] ?? '')) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="min-w-0" aria-labelledby="assets-builder-preview-title">
        <h3 id="assets-builder-preview-title" class="text-sm font-semibold">Preview</h3>
        <div class="mt-3 grid gap-3">
            <?php foreach (array_slice($assetsBuilderFields, 0, 8) as $field): ?>
                <?php if (empty($field['visible'])) { continue; } ?>
                <label class="grid gap-1 text-xs font-medium text-muted-foreground"><?= $assetsBuilderEscape((string) ($field['label'] ?? 'Field')) ?>
                    <span class="flex min-h-9 items-center rounded-md border bg-background px-3 text-sm text-foreground"><?= $assetsBuilderEscape((string) (($field['default'] ?? '') !== '' ? $field['default'] : 'Preview value')) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>
</div>
