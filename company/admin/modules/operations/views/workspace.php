<?php
declare(strict_types=1);

$operationsData = is_array($activeModuleData ?? null) ? $activeModuleData : [];
$operationsSections = yovel_admin_operations_sections();
$operationsSection = yovel_admin_operations_section((string) ($activeModuleSection ?? ''));
$operationsMeta = is_array($activeModuleMeta ?? null) ? $activeModuleMeta : $operationsSections[$operationsSection];
$operationsFormState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
$operationsPrior = is_array($operationsFormState['input'] ?? null) ? $operationsFormState['input'] : [];
$operationsReopenBuilder = ($operationsFormState['action'] ?? '') === 'save_operations_builder_form';
$operationsSchemaDefault = yovel_admin_operations_json(yovel_admin_normalize_operations_builder_schema($operationsSection, [
    'rows' => [['key' => 'primary-row', 'columns' => [['key' => 'primary-column', 'width' => 12, 'field_keys' => []]]]],
    'fields' => [],
]));
$operationsHidden =
    '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '">' .
    '<input type="hidden" name="module_view" value="operations">' .
    '<input type="hidden" name="action" value="save_operations_builder_form">' .
    '<input type="hidden" name="section" value="' . bx_h($operationsSection) . '">' .
    '<input type="hidden" name="builder_form_key" value="' . bx_h((string) ($operationsPrior['builder_form_key'] ?? '')) . '">';
ob_start();
?>
<?php if ($operationsReopenBuilder && ($operationsFormState['error'] ?? '') !== ''): ?>
    <div role="alert" class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= bx_h((string) $operationsFormState['error']) ?></div>
<?php endif; ?>
<div class="grid gap-4">
    <div class="grid gap-1.5"><label for="operations-form-target" class="text-sm font-medium">Target</label><select id="operations-form-target" name="target_section" class="h-9 rounded-md border bg-background px-3 text-sm"><?php foreach (yovel_admin_operations_builder_targets() as $target => $label): ?><option value="<?= bx_h($target) ?>" <?= (string) ($operationsPrior['target_section'] ?? $operationsSection) === $target ? 'selected' : '' ?>><?= bx_h($label) ?></option><?php endforeach; ?></select></div>
    <div class="grid gap-1.5"><label for="operations-form-title" class="text-sm font-medium">Form title</label><input id="operations-form-title" name="form_title" required maxlength="180" class="h-9 rounded-md border bg-background px-3 text-sm" value="<?= bx_h((string) ($operationsPrior['form_title'] ?? '')) ?>"></div>
    <div class="grid gap-1.5"><label for="operations-form-description" class="text-sm font-medium">Description</label><textarea id="operations-form-description" name="form_description" maxlength="500" rows="3" class="rounded-md border bg-background px-3 py-2 text-sm"><?= bx_h((string) ($operationsPrior['form_description'] ?? '')) ?></textarea></div>
    <div class="grid gap-1.5"><label for="operations-form-status" class="text-sm font-medium">Lifecycle</label><select id="operations-form-status" name="form_status" class="h-9 rounded-md border bg-background px-3 text-sm"><?php foreach (['DRAFT' => 'Draft', 'PUBLISHED' => 'Published', 'ARCHIVED' => 'Archived'] as $value => $label): ?><option value="<?= $value ?>" <?= (string) ($operationsPrior['form_status'] ?? 'DRAFT') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
    <div class="grid gap-1.5"><label for="operations-schema-json" class="text-sm font-medium">Layout schema</label><textarea id="operations-schema-json" name="schema_json" rows="9" spellcheck="false" class="rounded-md border bg-background px-3 py-2 font-mono text-xs"><?= bx_h((string) ($operationsPrior['schema_json'] ?? $operationsSchemaDefault)) ?></textarea></div>
</div>
<?php
$operationsBuilderBody = (string) ob_get_clean();
$recordModal = [
    'id' => 'operations-form-builder-modal',
    'title' => 'Operations Form Builder',
    'description' => 'Build a versioned company form while protected execution fields remain stable.',
    'open_label' => 'Create Form',
    'submit_label' => 'Submit',
    'confirm_message' => 'Confirm this Operations form version. Persistence begins only after confirmation.',
    'body_html' => $operationsBuilderBody,
    'hidden_html' => $operationsHidden,
    'open_on_load' => $operationsReopenBuilder,
];
ob_start();
require __DIR__ . '/record-modal.php';
$operationsBuilderModal = (string) ob_get_clean();
$operationsSectionTools = '';
$operationsSectionView = match ($operationsSection) {
    'scheduled-jobs', 'notifications', 'background-workers' => 'jobs.php',
    'sync-conflict-dashboard' => 'sync.php',
    'import-export-jobs' => 'import-export.php',
    'system-alerts' => 'alerts.php',
    'release-checklist' => 'release.php',
    'bulk-processing' => 'bulk.php',
    'governed-deletion' => 'deletion.php',
    'authorization-setup' => 'authorization.php',
    'company-defaults' => 'company-defaults.php',
    default => 'dashboard.php',
};
ob_start();
require __DIR__ . '/sections/' . $operationsSectionView;
$operationsSectionMain = (string) ob_get_clean();
?>
<div data-operations-workspace class="grid min-h-0 gap-4">
    <div data-operations-setup-card class="rounded-md border bg-card p-4">
        <?php require __DIR__ . '/sections/setup.php'; ?>
    </div>
    <nav aria-label="Operations features" class="flex gap-1 overflow-x-auto border-b pb-2">
        <?php foreach ($operationsSections as $sectionKey => $sectionMeta): ?>
            <a href="?view=operations&amp;section=<?= bx_h($sectionKey) ?>" class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-medium <?= $operationsSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'hover:bg-muted' ?>"><span class="material-symbols-rounded text-base" aria-hidden="true"><?= bx_h((string) $sectionMeta['icon']) ?></span><?= bx_h((string) $sectionMeta['label']) ?></a>
        <?php endforeach; ?>
    </nav>
    <div data-operations-split class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]">
        <main data-operations-main-panel class="min-w-0 rounded-md border bg-card p-5">
            <?= $operationsSectionMain ?>
        </main>
        <aside data-operations-tools-panel class="min-w-0 rounded-md border bg-card p-5">
            <div class="grid gap-5">
                <div><h2 class="text-base font-semibold">Actions and tools</h2><p class="mt-1 text-sm text-muted-foreground">Commands remain scoped to <?= bx_h((string) ($companyName ?? 'this company')) ?>.</p></div>
                <div class="grid grid-cols-2 gap-2"><?php foreach (($operationsData['metrics'] ?? []) as $label => $value): ?><div class="rounded-md bg-muted/50 p-3"><div class="text-lg font-semibold"><?= (int) $value ?></div><div class="text-xs text-muted-foreground"><?= bx_h(ucwords(str_replace('_', ' ', (string) $label))) ?></div></div><?php endforeach; ?></div>
                <?php if ($operationsSectionTools !== ''): ?><div class="grid gap-2"><h3 class="text-sm font-semibold">Commands</h3><?= $operationsSectionTools ?></div><?php endif; ?>
                <div class="grid gap-2"><h3 class="text-sm font-semibold">Form Builder</h3><p class="text-sm text-muted-foreground">Published layouts are immutable versions and submissions retain their original version.</p><?= $operationsBuilderModal ?></div>
            </div>
        </aside>
    </div>
</div>
<style>
@media (min-width: 1280px) {
  [data-operations-split] { grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr); }
}
</style>
<script>
(() => {
  const button = document.querySelector('[data-operations-tour-start]');
  const tour = document.querySelector('[data-operations-tour]');
  if (!button || !tour) return;
  button.addEventListener('click', () => {
    const active = tour.getAttribute('data-active') === '1';
    tour.setAttribute('data-active', active ? '0' : '1');
    tour.classList.toggle('ring-2', !active);
    tour.classList.toggle('ring-primary', !active);
    localStorage.setItem('operations-tour-seen', '1');
    if (!active) tour.querySelector('li')?.setAttribute('tabindex', '-1');
    if (!active) tour.querySelector('li')?.focus();
  });
})();
</script>
