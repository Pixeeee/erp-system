<?php
declare(strict_types=1);

$manufacturingEscape = $manufacturingEscape ?? static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$schema = is_array($manufacturingData['form_schema'] ?? null) ? $manufacturingData['form_schema'] : [];
$forms = is_array($manufacturingData['forms'] ?? null) ? $manufacturingData['forms'] : [];
?>
<div class="grid gap-7" data-manufacturing-form-builder>
    <section aria-labelledby="manufacturing-existing-forms">
        <div class="flex items-center justify-between gap-3"><h2 id="manufacturing-existing-forms" class="text-base font-semibold">Existing Forms</h2><span class="text-xs text-muted-foreground"><?= count($forms) ?> forms</span></div>
        <div class="mt-3 divide-y border-y">
            <?php foreach ($forms as $form): ?>
                <a href="?view=manufacturing&section=form-builder&target=<?= $manufacturingEscape((string) $form['target_section']) ?>&form=<?= $manufacturingEscape((string) $form['form_key']) ?>" class="flex items-center gap-3 py-3 text-sm"><span class="min-w-0 flex-1"><span class="block font-medium"><?= $manufacturingEscape((string) $form['form_title']) ?></span><span class="block text-xs text-muted-foreground"><?= $manufacturingEscape((string) $form['record_type']) ?> / Version <?= (int) $form['version_count'] ?></span></span><span class="text-xs font-medium"><?= $manufacturingEscape((string) $form['form_status']) ?></span></a>
            <?php endforeach; ?>
            <?php if ($forms === []): ?><p class="py-5 text-sm text-muted-foreground">No company forms have been saved.</p><?php endif; ?>
        </div>
    </section>

    <section aria-labelledby="manufacturing-form-layout">
        <div class="flex items-center justify-between gap-3"><h2 id="manufacturing-form-layout" class="text-base font-semibold">Form Layout</h2><span class="text-xs text-muted-foreground">Version <?= (int) ($schema['version'] ?? 1) ?></span></div>
        <div class="mt-3 grid gap-4">
            <?php foreach ($schema['sections'] ?? [] as $section): ?>
                <?php $sectionKey = (string) ($section['key'] ?? ''); $fields = array_values(array_filter($schema['fields'] ?? [], static fn (array $field): bool => (string) ($field['section'] ?? '') === $sectionKey && !empty($field['visible']))); ?>
                <section class="bg-muted/30 px-4 py-3" aria-label="<?= $manufacturingEscape((string) ($section['label'] ?? 'Section')) ?>"><div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold"><?= $manufacturingEscape((string) ($section['label'] ?? 'Section')) ?></h3><span class="text-xs text-muted-foreground"><?= count($fields) ?> fields</span></div><div class="mt-3 grid gap-2 sm:grid-cols-2"><?php foreach ($fields as $field): ?><div class="min-w-0 bg-background px-3 py-2"><p class="truncate text-sm font-medium"><?= $manufacturingEscape((string) $field['label']) ?></p><p class="truncate font-mono text-[11px] text-muted-foreground"><?= $manufacturingEscape((string) $field['key']) ?> / <?= $manufacturingEscape((string) $field['type']) ?></p></div><?php endforeach; ?></div></section>
            <?php endforeach; ?>
        </div>
    </section>

    <section aria-labelledby="manufacturing-form-preview"><h2 id="manufacturing-form-preview" class="text-base font-semibold">Preview</h2><div class="mt-3 grid gap-4 border-t pt-4 sm:grid-cols-2"><?php foreach ($schema['fields'] ?? [] as $field): if (empty($field['visible']) || !empty($field['system'])) continue; ?><label class="grid gap-1.5 text-sm <?= ($field['width'] ?? '') === 'full' ? 'sm:col-span-2' : '' ?>"><span class="font-medium"><?= $manufacturingEscape((string) $field['label']) ?></span><input class="h-9 rounded-md border bg-background px-3" disabled value="<?= $manufacturingEscape((string) ($field['default'] ?? '')) ?>"></label><?php endforeach; ?><?php if (count(array_filter($schema['fields'] ?? [], static fn (array $field): bool => empty($field['system']) && !empty($field['visible']))) === 0): ?><p class="text-sm text-muted-foreground sm:col-span-2">No custom preview fields are published for this target.</p><?php endif; ?></div></section>
</div>
