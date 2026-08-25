<?php
declare(strict_types=1);

$assetsData = is_array($activeModuleData ?? null) ? $activeModuleData : [];
$assetsControllerState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
$assetsSection = (string) ($assetsData['section'] ?? ($activeModuleSection ?? 'asset-records'));
if ($assetsControllerState !== [] && (string) ($assetsControllerState['section'] ?? '') === $assetsSection) {
    $assetsData['form_state'] = [
        'open' => true,
        'action' => (string) ($assetsControllerState['action'] ?? ''),
        'input' => is_array($assetsControllerState['input'] ?? null) ? $assetsControllerState['input'] : [],
        'error' => (string) ($assetsControllerState['error'] ?? ''),
    ];
}
$assetsSections = is_array($assetsData['sections'] ?? null) ? $assetsData['sections'] : yovel_admin_assets_maintenance_sections();
$assetsState = is_array($assetsData['state'] ?? null) ? $assetsData['state'] : [];
$assetsEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetsSelectedForm = is_array($assetsData['selected_form'] ?? null) ? $assetsData['selected_form'] : [];
$assetsDashboard = is_array($assetsData['dashboard'] ?? null) ? $assetsData['dashboard'] : [];
?>
<style>
    .yovel-assets-approved-layout { grid-template-columns: minmax(0, 1fr); }
    [data-assets-maintenance-workspace] [data-record-modal] { z-index: 70; }
    [data-assets-tour-dialog] { z-index: 80; }
    .yovel-assets-tour-active { outline: 2px solid hsl(var(--primary)); outline-offset: 4px; }
    [data-confirm-dialog] { z-index: 100; }
    @media (min-width: 1280px) { .yovel-assets-approved-layout { grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr); } }
</style>
<div data-assets-maintenance-workspace class="grid min-h-0 gap-3">
    <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Assets and Maintenance sections">
        <?php foreach ($assetsSections as $sectionKey => $sectionMeta): ?>
            <a class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $assetsSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=assets-maintenance&amp;section=<?= $assetsEscape((string) $sectionKey) ?>"><span class="material-symbols-rounded text-base" aria-hidden="true"><?= $assetsEscape((string) ($sectionMeta['icon'] ?? 'handyman')) ?></span><?= $assetsEscape((string) ($sectionMeta['label'] ?? $sectionKey)) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="yovel-assets-approved-layout grid min-h-0 gap-4">
        <main data-assets-main<?= $assetsSection === 'asset-depreciation-schedule' ? ' data-assets-depreciation-main' : '' ?> data-grid-span="12" class="flex min-h-[28rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><p class="text-xs font-medium text-muted-foreground">Assets / Maintenance</p><h2 class="mt-1 text-base font-semibold"><?= $assetsEscape((string) ($assetsData['section_meta']['label'] ?? 'Asset records')) ?></h2></div><?php if ($assetsSection === 'dashboard'): ?><a class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=assets-maintenance&amp;section=form-builder"><span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder</a><?php endif; ?></header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if ($assetsSection === 'dashboard'): ?>
                    <?php require __DIR__ . '/dashboard.php'; ?>
                <?php elseif ($assetsSection === 'asset-records'): ?>
                    <?php require __DIR__ . '/record-list.php'; ?>
                <?php elseif ($assetsSection === 'asset-depreciation-schedule'): ?>
                    <div data-assets-depreciation-list><?php require __DIR__ . '/depreciation-list.php'; ?></div>
                <?php elseif ($assetsSection === 'form-builder'): ?>
                    <?php $assetsFormSchema = is_array($assetsData['active_form_schema'] ?? null) ? $assetsData['active_form_schema'] : []; $assetsFormAdapter = is_array($assetsData['form_adapter'] ?? null) ? $assetsData['form_adapter'] : []; require __DIR__ . '/form-builder.php'; ?>
                <?php else: ?>
                    <div class="grid min-h-[20rem] place-items-center text-center"><div class="max-w-md"><span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true"><?= ($assetsState['kind'] ?? '') === 'empty' ? 'precision_manufacturing' : 'hourglass_top' ?></span><h3 class="mt-3 text-base font-semibold"><?= $assetsEscape((string) ($assetsState['title'] ?? 'Assets workspace')) ?></h3><p class="mt-1 text-sm leading-6 text-muted-foreground"><?= $assetsEscape((string) ($assetsState['message'] ?? '')) ?></p></div></div>
                <?php endif; ?>
            </div>
        </main>
        <aside data-assets-tools<?= $assetsSection === 'asset-depreciation-schedule' ? ' data-assets-depreciation-tools' : '' ?> data-grid-span="8" class="flex min-h-[28rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="border-b px-5 py-4"><h2 class="text-base font-semibold">Actions and tools</h2><p class="mt-1 text-sm text-muted-foreground"><?= $assetsEscape((string) ($assetsData['company_name'] ?? $companyName ?? 'Company')) ?></p></header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if ($assetsSection === 'asset-records'): ?>
                    <div class="grid gap-6">
                        <section aria-labelledby="assets-record-actions-title">
                            <h3 id="assets-record-actions-title" class="text-sm font-semibold">Create</h3>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="button" data-record-modal-open="assets-lifecycle-modal" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>New Asset</button>
                                <button type="button" data-record-modal-open="assets-movement-modal" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">swap_horiz</span>New Movement</button>
                                <button type="button" data-record-modal-open="assets-location-modal" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">location_on</span>New Location</button>
                                <button type="button" data-record-modal-open="assets-category-modal" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">category</span>New Category</button>
                            </div>
                        </section>
                        <section class="border-t pt-5" aria-labelledby="assets-master-summary-title">
                            <h3 id="assets-master-summary-title" class="text-sm font-semibold">Masters</h3>
                            <dl class="mt-3 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground">Locations</dt><dd class="mt-1 font-medium"><?= count($assetsData['locations'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Categories</dt><dd class="mt-1 font-medium"><?= count($assetsData['categories'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Assets</dt><dd class="mt-1 font-medium"><?= count($assetsData['assets'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Movements</dt><dd class="mt-1 font-medium"><?= count($assetsData['movements'] ?? []) ?></dd></div></dl>
                        </section>
                        <section class="border-t pt-5" aria-labelledby="assets-owner-health-title">
                            <h3 id="assets-owner-health-title" class="text-sm font-semibold">Owner services</h3>
                            <div class="mt-3 divide-y border-y"><?php foreach (($assetsData['dependency_state'] ?? []) as $capability => $status): ?><div class="flex items-center justify-between gap-3 py-2.5"><span class="min-w-0 truncate text-xs"><?= $assetsEscape((string) $capability) ?></span><span class="text-[10px] <?= $status === 'AVAILABLE' ? 'text-emerald-600' : ($status === 'ERROR' ? 'text-destructive' : 'text-muted-foreground') ?>"><?= $assetsEscape((string) $status) ?></span></div><?php endforeach; ?></div>
                            <p class="mt-3 text-xs leading-5 text-muted-foreground">Inventory and HR values use owner readers. Finance readiness uses its caller-owned posting contract.</p>
                        </section>
                    </div>
                <?php elseif ($assetsSection === 'asset-depreciation-schedule'): ?>
                    <div class="grid gap-6">
                        <section aria-labelledby="assets-depreciation-actions-title"><h3 id="assets-depreciation-actions-title" class="text-sm font-semibold">Create</h3><div class="mt-3 grid grid-cols-2 gap-2"><button type="button" data-record-modal-open="assets-finance-book-modal" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><span class="material-symbols-rounded text-base" aria-hidden="true">menu_book</span>Finance Book</button><button type="button" data-record-modal-open="assets-depreciation-modal" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">trending_down</span>Schedule</button><button type="button" data-record-modal-open="assets-shift-factor-modal" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">speed</span>Shift Factor</button><button type="button" data-record-modal-open="assets-shift-allocation-modal" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">swap_calls</span>Allocation</button><button type="button" data-record-modal-open="assets-value-adjustment-modal" class="col-span-2 inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">price_change</span>Value Adjustment</button></div></section>
                        <section class="border-t pt-5" aria-labelledby="assets-depreciation-summary-title"><h3 id="assets-depreciation-summary-title" class="text-sm font-semibold">Depreciation control</h3><dl class="mt-3 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground">Books</dt><dd class="mt-1 font-medium"><?= count($assetsData['finance_books'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Schedules</dt><dd class="mt-1 font-medium"><?= count($assetsData['depreciation_schedules'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Shift factors</dt><dd class="mt-1 font-medium"><?= count($assetsData['shift_factors'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Adjustments</dt><dd class="mt-1 font-medium"><?= count($assetsData['value_adjustments'] ?? []) ?></dd></div></dl><p class="mt-3 text-xs leading-5 text-muted-foreground">Assets owns schedules. Finance owns balanced postings and additive reversals.</p></section>
                    </div>
                <?php elseif ($assetsSection === 'dashboard'): ?>
                    <div class="grid gap-6">
                        <section data-assets-dashboard-setup data-assets-tour-target="setup" aria-labelledby="assets-dashboard-setup-title">
                            <div class="flex items-center justify-between gap-3"><h3 id="assets-dashboard-setup-title" class="text-sm font-semibold">Setup progress</h3><span class="text-xs text-muted-foreground"><?= count(array_filter($assetsDashboard['setup'] ?? [], static fn (array $step): bool => !empty($step['complete']))) ?>/<?= count($assetsDashboard['setup'] ?? []) ?></span></div>
                            <div class="mt-3 divide-y border-y"><?php foreach ($assetsDashboard['setup'] ?? [] as $step): ?><a class="flex min-h-11 items-center gap-3 py-2 text-sm hover:bg-muted/40" href="<?= $assetsEscape((string) ($step['href'] ?? '#')) ?>"><span class="material-symbols-rounded text-base <?= !empty($step['complete']) ? 'text-emerald-600' : 'text-muted-foreground' ?>" aria-hidden="true"><?= !empty($step['complete']) ? 'check_circle' : 'radio_button_unchecked' ?></span><span class="min-w-0 flex-1"><?= $assetsEscape((string) ($step['label'] ?? 'Setup step')) ?></span></a><?php endforeach; ?></div>
                        </section>
                        <section data-assets-dashboard-alerts data-assets-tour-target="alerts" class="border-t pt-5" aria-labelledby="assets-dashboard-alerts-title">
                            <h3 id="assets-dashboard-alerts-title" class="text-sm font-semibold">Alerts</h3>
                            <div class="mt-3 grid gap-3"><?php foreach ($assetsDashboard['alerts'] ?? [] as $alert): ?><div class="flex items-start gap-3 bg-muted/30 px-3 py-2.5"><span class="material-symbols-rounded mt-0.5 text-base <?= ($alert['severity'] ?? '') === 'ERROR' ? 'text-destructive' : 'text-amber-600' ?>" aria-hidden="true"><?= ($alert['severity'] ?? '') === 'ERROR' ? 'error' : 'warning' ?></span><div class="min-w-0"><p class="text-sm leading-5"><?= $assetsEscape((string) ($alert['label'] ?? 'Dashboard alert')) ?></p><p class="mt-1 text-[10px] text-muted-foreground"><?= $assetsEscape((string) ($alert['availability'] ?? 'AVAILABLE')) ?></p></div></div><?php endforeach; ?></div>
                        </section>
                        <section data-assets-dashboard-dependencies class="border-t pt-5" aria-labelledby="assets-dashboard-dependencies-title">
                            <h3 id="assets-dashboard-dependencies-title" class="text-sm font-semibold">Dependency health</h3>
                            <div class="mt-3 divide-y border-y"><?php foreach ($assetsDashboard['dependencies'] ?? [] as $dependency): ?><div class="flex items-center justify-between gap-3 py-2.5"><div class="min-w-0"><p class="truncate text-sm font-medium"><?= $assetsEscape((string) ($dependency['label'] ?? 'Owner contract')) ?></p><p class="truncate text-[10px] text-muted-foreground"><?= $assetsEscape((string) ($dependency['contract'] ?? '')) ?></p></div><span class="shrink-0 text-[10px] <?= ($dependency['status'] ?? '') === 'AVAILABLE' ? 'text-emerald-600' : (($dependency['status'] ?? '') === 'ERROR' ? 'text-destructive' : 'text-muted-foreground') ?>"><?= $assetsEscape((string) ($dependency['status'] ?? 'UNAVAILABLE_DEPENDENCY')) ?></span></div><?php endforeach; ?></div>
                        </section>
                        <section data-assets-tour-target="form-builder" class="border-t pt-5" aria-labelledby="assets-dashboard-tools-title">
                            <h3 id="assets-dashboard-tools-title" class="text-sm font-semibold">Tools</h3>
                            <div class="mt-3 grid gap-2"><a class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted" href="./?view=assets-maintenance&amp;section=form-builder"><span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder</a><button type="button" data-assets-tour-start class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">tour</span>Show Tour</button></div>
                        </section>
                    </div>
                <?php elseif ($assetsSection === 'form-builder'): ?>
                    <button type="button" data-record-modal-open="assets-form-modal" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>New Form</button>
                    <dl class="mt-5 grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground">Forms</dt><dd class="mt-1 font-medium"><?= count($assetsData['forms'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Record type</dt><dd class="mt-1 break-all font-medium"><?= $assetsEscape((string) ($assetsData['record_type'] ?? 'ASSET')) ?></dd></div><div><dt class="text-xs text-muted-foreground">Version</dt><dd class="mt-1 font-medium"><?= (int) ($assetsData['active_form_schema']['version'] ?? 1) ?></dd></div><div><dt class="text-xs text-muted-foreground">Fields</dt><dd class="mt-1 font-medium"><?= count($assetsData['active_form_schema']['fields'] ?? []) ?></dd></div></dl>
                    <?php if (($assetsData['forms'] ?? []) !== []): ?><div class="mt-5 grid gap-2" aria-label="Assets forms"><?php foreach ($assetsData['forms'] as $form): ?><a class="flex items-center justify-between gap-3 border-b py-2 text-sm" href="./?view=assets-maintenance&amp;section=form-builder&amp;form=<?= $assetsEscape((string) $form['form_key']) ?>"><span class="min-w-0 truncate"><?= $assetsEscape((string) $form['form_title']) ?></span><span class="text-xs text-muted-foreground"><?= $assetsEscape((string) $form['form_status']) ?></span></a><?php endforeach; ?></div><?php endif; ?>
                    <?php if ($assetsSelectedForm !== [] && (string) ($assetsSelectedForm['form_status'] ?? '') !== 'ARCHIVED'): ?>
                        <div class="mt-5 grid gap-2">
                            <?php if ((string) ($assetsSelectedForm['form_status'] ?? '') !== 'PUBLISHED'): ?><form method="post" data-confirm-submit data-confirm-message="Confirm publication of this immutable Assets form version."><input type="hidden" name="csrf" value="<?= $assetsEscape(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="action" value="publish_assets_form"><input type="hidden" name="section" value="form-builder"><input type="hidden" name="form_key" value="<?= $assetsEscape((string) $assetsSelectedForm['form_key']) ?>"><button type="submit" data-confirm-submit-action class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">publish</span>Publish</button></form><?php endif; ?>
                            <form method="post" data-confirm-submit data-confirm-message="Confirm archival of this Assets form. Published versions remain readable."><input type="hidden" name="csrf" value="<?= $assetsEscape(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="assets-maintenance"><input type="hidden" name="action" value="archive_assets_form"><input type="hidden" name="section" value="form-builder"><input type="hidden" name="form_key" value="<?= $assetsEscape((string) $assetsSelectedForm['form_key']) ?>"><input type="hidden" name="archive_reason" value="Archived from Form Builder"><button type="submit" data-confirm-submit-action class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">archive</span>Archive</button></form>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="grid gap-4"><div class="bg-muted/30 p-3"><p class="text-xs font-medium text-muted-foreground">State</p><p class="mt-1 text-sm font-semibold"><?= $assetsEscape(ucfirst((string) ($assetsState['kind'] ?? 'dependency'))) ?></p></div><?php if (($assetsState['dependencies'] ?? []) !== []): ?><div><p class="text-xs font-medium text-muted-foreground">Work package</p><div class="mt-2 flex flex-wrap gap-2"><?php foreach ($assetsState['dependencies'] as $dependency): ?><span class="rounded-sm bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground"><?= $assetsEscape((string) $dependency) ?></span><?php endforeach; ?></div></div><?php endif; ?><a class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium" href="./?view=assets-maintenance&amp;section=form-builder"><span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder</a></div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
    <?php if (in_array($assetsSection, ['asset-records', 'asset-depreciation-schedule', 'form-builder'], true)): ?><?php require __DIR__ . '/record-modal.php'; ?><?php endif; ?>
    <?php if ($assetsSection === 'dashboard'): ?>
        <div data-assets-tour-dialog data-tour-storage-key="<?= $assetsEscape((string) ($assetsDashboard['tour_storage_key'] ?? 'builderx:assets-maintenance:tour')) ?>" hidden class="pointer-events-none fixed inset-0 p-4" role="dialog" aria-modal="true" aria-labelledby="assets-tour-title" aria-describedby="assets-tour-description">
            <section class="pointer-events-auto ml-auto mt-auto flex max-h-[calc(100dvh-2rem)] w-full max-w-md flex-col overflow-hidden rounded-lg border bg-card shadow-lg" role="document">
                <header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div class="min-w-0"><p class="text-xs font-medium text-muted-foreground" data-assets-tour-progress>1 of 6</p><h2 id="assets-tour-title" class="mt-1 text-base font-semibold" data-assets-tour-title>Operational summary</h2></div><button type="button" data-assets-tour-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border" aria-label="Close tour"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
                <div class="overflow-y-auto px-5 py-4"><p id="assets-tour-description" class="text-sm leading-6 text-muted-foreground" data-assets-tour-description>Review live foundation metrics and explicit unavailable lifecycle states.</p><div hidden aria-hidden="true"><span data-assets-tour-step="0" data-target="summary" data-title="Operational summary" data-description="Review live foundation metrics and explicit unavailable lifecycle states."></span><span data-assets-tour-step="1" data-target="queue" data-title="Action queue" data-description="Draft forms are actionable while lifecycle queues stay blocked until their packages exist."></span><span data-assets-tour-step="2" data-target="activity" data-title="Recent activity" data-description="See the latest company-scoped Form Builder and submission audit events."></span><span data-assets-tour-step="3" data-target="setup" data-title="Setup progress" data-description="Complete the Assets foundation and verify its owner contracts."></span><span data-assets-tour-step="4" data-target="shortcuts" data-title="Shortcuts and directories" data-description="Open implemented tools and distinguish disabled future destinations."></span><span data-assets-tour-step="5" data-target="alerts" data-title="Alerts and dependencies" data-description="Review owner-service health and pending Assets lifecycle packages."></span></div></div>
                <footer class="flex items-center justify-between gap-3 border-t px-5 py-4"><button type="button" data-assets-tour-skip class="h-9 rounded-md border px-3 text-sm">Skip</button><div class="flex gap-2"><button type="button" data-assets-tour-back class="h-9 rounded-md border px-3 text-sm">Back</button><button type="button" data-assets-tour-next class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Next</button></div></footer>
            </section>
        </div>
        <script>
        (() => {
            const root = document.querySelector('[data-assets-maintenance-workspace]');
            const dialog = root?.querySelector('[data-assets-tour-dialog]');
            if (!root || !dialog) return;
            const steps = [...dialog.querySelectorAll('[data-assets-tour-step]')];
            const starts = [...root.querySelectorAll('[data-assets-tour-start]')];
            const title = dialog.querySelector('[data-assets-tour-title]');
            const description = dialog.querySelector('[data-assets-tour-description]');
            const progress = dialog.querySelector('[data-assets-tour-progress]');
            const back = dialog.querySelector('[data-assets-tour-back]');
            const next = dialog.querySelector('[data-assets-tour-next]');
            const storageKey = dialog.dataset.tourStorageKey || 'builderx:assets-maintenance:tour';
            let index = 0;
            let returnFocus = null;
            let activeTarget = null;
            const store = value => { try { localStorage.setItem(storageKey, value); } catch (_) {} };
            const render = () => {
                const step = steps[index];
                if (!step) return;
                activeTarget?.classList.remove('yovel-assets-tour-active');
                activeTarget = root.querySelector(`[data-assets-tour-target="${step.dataset.target}"]`);
                activeTarget?.classList.add('yovel-assets-tour-active');
                activeTarget?.scrollIntoView({block: 'nearest'});
                title.textContent = step.dataset.title || 'Assets dashboard';
                description.textContent = step.dataset.description || '';
                progress.textContent = `${index + 1} of ${steps.length}`;
                back.disabled = index === 0;
                next.textContent = index === steps.length - 1 ? 'Finish' : 'Next';
            };
            const close = state => {
                if (state) store(state);
                dialog.hidden = true;
                activeTarget?.classList.remove('yovel-assets-tour-active');
                activeTarget = null;
                returnFocus?.focus();
            };
            const open = (trigger = starts[0]) => {
                returnFocus = trigger;
                index = 0;
                dialog.hidden = false;
                render();
                dialog.querySelector('[data-assets-tour-close]')?.focus();
            };
            starts.forEach(trigger => trigger.addEventListener('click', () => open(trigger)));
            dialog.querySelector('[data-assets-tour-close]')?.addEventListener('click', () => close('dismissed'));
            dialog.querySelector('[data-assets-tour-skip]')?.addEventListener('click', () => close('dismissed'));
            back?.addEventListener('click', () => { if (index > 0) { index -= 1; render(); } });
            next?.addEventListener('click', () => { if (index >= steps.length - 1) { close('complete'); return; } index += 1; render(); });
            dialog.addEventListener('keydown', event => {
                if (event.key === 'Escape') { event.preventDefault(); close('dismissed'); return; }
                if (event.key !== 'Tab') return;
                const focusable = [...dialog.querySelectorAll('button:not([disabled])')];
                if (focusable.length === 0) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            });
            let seen = null;
            try { seen = localStorage.getItem(storageKey); } catch (_) {}
            if (!seen && starts[0]) queueMicrotask(() => open(starts[0]));
        })();
        </script>
    <?php endif; ?>
</div>
