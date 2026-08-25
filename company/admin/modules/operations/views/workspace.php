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
    'dashboard' => 'dashboard.php',
    'scheduled-jobs', 'notifications', 'background-workers' => 'jobs.php',
    'sync-conflict-dashboard' => 'sync.php',
    'import-export-jobs' => 'import-export.php',
    'system-alerts' => 'alerts.php',
    'release-checklist' => 'release.php',
    'bulk-processing' => 'bulk.php',
    'governed-deletion' => 'deletion.php',
    'authorization-setup' => 'authorization.php',
    'company-defaults' => 'company-defaults.php',
    'workforce-directory' => 'workforce.php',
    'workforce-calendars' => 'calendars.php',
    'commercial-masters' => 'commercial.php',
    'catalog-units' => 'catalog.php',
    default => 'dashboard.php',
};
ob_start();
require $operationsSection === 'dashboard'
    ? __DIR__ . '/dashboard.php'
    : __DIR__ . '/sections/' . $operationsSectionView;
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
                <?php if ($operationsSection !== 'dashboard'): ?><div class="grid grid-cols-2 gap-2"><?php foreach (($operationsData['metrics'] ?? []) as $label => $value): ?><div class="rounded-md bg-muted/50 p-3"><div class="text-lg font-semibold"><?= (int) $value ?></div><div class="text-xs text-muted-foreground"><?= bx_h(ucwords(str_replace('_', ' ', (string) $label))) ?></div></div><?php endforeach; ?></div><?php endif; ?>
                <?php if ($operationsSectionTools !== ''): ?><div class="grid gap-4"><h3 class="text-sm font-semibold"><?= $operationsSection === 'dashboard' ? 'Dashboard tools' : 'Commands' ?></h3><?= $operationsSectionTools ?></div><?php endif; ?>
                <div id="operations-dashboard-form-builder" class="grid gap-2"><h3 class="text-sm font-semibold">Form Builder</h3><p class="text-sm text-muted-foreground">Published layouts are immutable versions and submissions retain their original version.</p><?= $operationsBuilderModal ?></div>
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
  const dashboard = document.querySelector('[data-operations-dashboard]');
  const dashboardTour = document.querySelector('[data-operations-dashboard-tour]');
  if (button && dashboard && dashboardTour) {
    const triggers = [button, ...document.querySelectorAll('[data-operations-dashboard-tour-start]')];
    const panel = dashboardTour.querySelector('[data-operations-dashboard-tour-panel]');
    const title = dashboardTour.querySelector('[data-operations-dashboard-tour-title]');
    const description = dashboardTour.querySelector('[data-operations-dashboard-tour-description]');
    const progress = dashboardTour.querySelector('[data-operations-dashboard-tour-progress]');
    const back = dashboardTour.querySelector('[data-operations-dashboard-tour-back]');
    const next = dashboardTour.querySelector('[data-operations-dashboard-tour-next]');
    const skip = dashboardTour.querySelector('[data-operations-dashboard-tour-skip]');
    const steps = [
      { target: '[data-dashboard-summary]', title: 'Live summary', body: 'Review queued, running, failed, alert, conflict, and release state.' },
      { target: '[data-dashboard-queue]', title: 'Action queue', body: 'Start with failed jobs, alerts, conflicts, and queued deletion reviews.' },
      { target: '[data-dashboard-activity]', title: 'Recent activity', body: 'Trace confirmed actions to their actor, record, status, and time.' },
      { target: '[data-dashboard-setup]', title: 'Setup progress', body: 'Complete schedules, worker checks, release evidence, and Form Builder setup.' },
      { target: '[data-dashboard-shortcuts]', title: 'Shortcuts', body: 'Move directly to implemented Operations destinations.' },
      { target: '[data-dashboard-directories]', title: 'Directories', body: 'Browse execution, assurance, and setup destinations by purpose.' },
    ];
    let index = 0;
    let opener = button;
    let highlighted = null;
    const clearHighlight = () => {
      highlighted?.classList.remove('ring-2', 'ring-primary');
      highlighted = null;
    };
    const render = () => {
      clearHighlight();
      const step = steps[index];
      highlighted = document.querySelector(step.target);
      highlighted?.classList.add('ring-2', 'ring-primary');
      title.textContent = step.title;
      description.textContent = step.body;
      progress.textContent = `Step ${index + 1} of ${steps.length}`;
      back.disabled = index === 0;
      next.textContent = index === steps.length - 1 ? 'Finish' : 'Next';
    };
    const close = (completed) => {
      clearHighlight();
      dashboardTour.hidden = true;
      if (completed) localStorage.setItem(dashboard.dataset.tourStorage || 'operations-dashboard-tour', 'complete');
      opener?.focus();
    };
    const open = (trigger) => {
      opener = trigger;
      index = 0;
      dashboardTour.hidden = false;
      render();
      panel?.focus();
    };
    triggers.forEach((trigger) => trigger.addEventListener('click', () => open(trigger)));
    back?.addEventListener('click', () => { if (index > 0) { index--; render(); } });
    next?.addEventListener('click', () => { if (index === steps.length - 1) close(true); else { index++; render(); } });
    skip?.addEventListener('click', () => close(false));
    dashboardTour.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { event.preventDefault(); close(false); return; }
      if (event.key !== 'Tab') return;
      const focusable = [...dashboardTour.querySelectorAll('button:not([disabled])')];
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    return;
  }
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
