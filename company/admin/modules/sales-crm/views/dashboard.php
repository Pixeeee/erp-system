<?php
/** Live Sales / CRM dashboard variables are prepared by bootstrap/controller.php. */
$salesCrmDashboard = is_array($salesCrmData['dashboard'] ?? null) ? $salesCrmData['dashboard'] : [];
$salesCrmWorkspace = is_array($salesCrmData['workspace'] ?? null) ? $salesCrmData['workspace'] : [];
$salesCrmSettings = is_array($salesCrmWorkspace['settings'] ?? null) ? $salesCrmWorkspace['settings'] : yovel_admin_sales_crm_settings_defaults();
$salesCrmPreference = is_array($salesCrmWorkspace['preference'] ?? null) ? $salesCrmWorkspace['preference'] : ['preference_version' => 0, 'setup_dismissed' => 0, 'tour_status' => 'NEW'];
$salesCrmAccess = is_array($salesCrmWorkspace['access'] ?? null) ? $salesCrmWorkspace['access'] : ['crm' => false, 'selling' => false, 'manage_settings' => false];
$dashboardLeadSchema = is_array($salesCrmData['schemas']['lead'] ?? null) ? $salesCrmData['schemas']['lead'] : [];
$dashboardLeadFields = array_values(array_filter(
    is_array($dashboardLeadSchema['fields'] ?? null) ? $dashboardLeadSchema['fields'] : [],
    static fn (array $field): bool => filter_var($field['visible'] ?? true, FILTER_VALIDATE_BOOLEAN)
));
$dashboardLeadFieldsBySection = [];
foreach ($dashboardLeadFields as $field) {
    $dashboardLeadFieldsBySection[(string) ($field['section'] ?? 'overview')][] = $field;
}
$dashboardLeadFormState = is_array($activeModuleFormState ?? null)
    && (string) ($activeModuleFormState['section'] ?? '') === 'dashboard'
    && (string) ($activeModuleFormState['action'] ?? '') === 'sales_crm_dashboard_save_lead'
        ? $activeModuleFormState
        : [];
$dashboardLeadRecord = is_array($dashboardLeadFormState['input'] ?? null) ? $dashboardLeadFormState['input'] : [];
$dashboardLeadModalOpen = $dashboardLeadFormState !== [] || (string) ($_GET['modal'] ?? '') === 'lead';
$dashboardSummary = is_array($salesCrmDashboard['summary'] ?? null) ? $salesCrmDashboard['summary'] : [];
$dashboardQueue = is_array($salesCrmDashboard['queue'] ?? null) ? $salesCrmDashboard['queue'] : [];
$dashboardActivity = is_array($salesCrmDashboard['activity'] ?? null) ? $salesCrmDashboard['activity'] : [];
$dashboardSetup = is_array($salesCrmDashboard['setup'] ?? null) ? $salesCrmDashboard['setup'] : [];
$dashboardAlerts = is_array($salesCrmDashboard['alerts'] ?? null) ? $salesCrmDashboard['alerts'] : [];
$dashboardShortcuts = is_array($salesCrmDashboard['shortcuts'] ?? null) ? $salesCrmDashboard['shortcuts'] : [];
$dashboardDirectories = is_array($salesCrmDashboard['directories'] ?? null) ? $salesCrmDashboard['directories'] : [];
$dashboardDependencies = is_array($salesCrmDashboard['dependencies'] ?? null) ? $salesCrmDashboard['dependencies'] : [];
$formatDashboardValue = static function (array $metric): string {
    if (($metric['availability'] ?? '') !== 'AVAILABLE') {
        return 'Unavailable';
    }
    $value = (string) ($metric['value'] ?? '0');
    return (string) ($metric['key'] ?? '') === 'pipeline-value' ? number_format((float) $value, 2) : number_format((int) $value);
};
?>
<style>
    .yovel-sales-dashboard-shell { min-height: 0; }
    @media (min-width: 1280px) {
        .yovel-sales-crm-two-panel {
            grid-template-columns: minmax(0, 12fr) minmax(20rem, 8fr);
            height: min(52rem, calc(100svh - 10.5rem));
        }
    }
</style>
<div class="yovel-sales-dashboard-shell grid min-h-0 gap-4">
    <header class="flex min-h-0 flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium text-muted-foreground">Sales / CRM</p>
            <h2 class="mt-1 text-xl font-semibold tracking-normal">Live Dashboard</h2>
            <p class="mt-1 text-sm text-muted-foreground">Pipeline health, follow-up work, Campaign activity, and setup for <?= bx_h($companyName) ?>.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="./?view=sales-crm&amp;section=leads" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Leads</a>
            <a href="./?view=sales-crm&amp;section=campaigns" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Campaigns</a>
            <?php if (!empty($salesCrmAccess['crm'])): ?>
                <button type="button" data-record-modal-open="yovel-sales-dashboard-lead-modal" class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Add Lead</button>
            <?php endif; ?>
        </div>
    </header>

    <div class="yovel-sales-crm-two-panel grid min-h-0 gap-4 xl:grid-cols-[minmax(0,12fr)_minmax(20rem,8fr)]">
        <main class="flex min-h-0 flex-col overflow-hidden rounded-lg border bg-card" aria-label="Sales dashboard operations">
            <section data-sales-dashboard-summary class="grid shrink-0 grid-cols-2 divide-x divide-y border-b sm:grid-cols-3 xl:grid-cols-6">
                <?php foreach ($dashboardSummary as $metric): ?>
                    <?php $available = (string) ($metric['availability'] ?? '') === 'AVAILABLE'; ?>
                    <div class="min-w-0 px-4 py-3 <?= !$available ? 'bg-muted/25' : '' ?>">
                        <p class="truncate text-xs text-muted-foreground" title="<?= bx_h((string) ($metric['label'] ?? '')) ?>"><?= bx_h((string) ($metric['label'] ?? '')) ?></p>
                        <p class="mt-1 text-lg font-semibold <?= !$available ? 'text-muted-foreground' : '' ?>" data-sales-dashboard-metric="<?= bx_h((string) ($metric['key'] ?? '')) ?>"><?= bx_h($formatDashboardValue($metric)) ?></p>
                        <?php if (!$available): ?><p class="mt-1 text-[11px] text-muted-foreground">Dependency pending</p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>

            <div class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,7fr)_minmax(17rem,5fr)]">
                <section data-sales-dashboard-queue class="min-h-0 overflow-auto border-b lg:border-b-0 lg:border-r">
                    <div class="sticky top-0 z-10 border-b bg-card px-5 py-3"><h3 class="text-sm font-semibold">Follow-up queue</h3></div>
                    <?php if (!$dashboardQueue): ?>
                        <div class="p-5 text-sm text-muted-foreground">No stale Leads or ending Campaigns need attention.</div>
                    <?php else: ?>
                        <div class="divide-y">
                            <?php foreach ($dashboardQueue as $item): ?>
                                <a href="<?= bx_h((string) ($item['href'] ?? '')) ?>" class="flex min-h-16 items-center justify-between gap-4 px-5 py-3 hover:bg-muted/40">
                                    <span class="min-w-0"><span class="block truncate text-sm font-medium"><?= bx_h((string) ($item['label'] ?? '')) ?></span><span class="mt-1 block truncate text-xs text-muted-foreground"><?= bx_h((string) ($item['detail'] ?? '')) ?></span></span>
                                    <span class="shrink-0 text-xs font-medium text-muted-foreground"><?= (string) ($item['status'] ?? '') === 'ENDING_SOON' ? 'Ending soon' : 'Follow up' ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section data-sales-dashboard-activity class="min-h-0 overflow-auto">
                    <div class="sticky top-0 z-10 border-b bg-card px-5 py-3"><h3 class="text-sm font-semibold">Recent Sales activity</h3></div>
                    <?php if (!$dashboardActivity): ?>
                        <div class="p-5 text-sm text-muted-foreground">No Sales activity has been recorded yet.</div>
                    <?php else: ?>
                        <div class="divide-y">
                            <?php foreach ($dashboardActivity as $item): ?>
                                <div class="px-5 py-3">
                                    <div class="flex items-center justify-between gap-3"><p class="truncate text-sm font-medium"><?= bx_h((string) ($item['record_label'] ?? '')) ?></p><span class="text-[11px] text-muted-foreground"><?= bx_h((string) ($item['action'] ?? '')) ?></span></div>
                                    <p class="mt-1 truncate text-xs text-muted-foreground"><?= bx_h((string) ($item['actor_label'] ?? '')) ?> | <?= bx_h((string) ($item['occurred_at'] ?? '')) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section data-sales-dashboard-shortcuts class="shrink-0 border-t">
                <div class="grid grid-cols-2 divide-x sm:grid-cols-3 lg:grid-cols-5">
                    <?php foreach ($dashboardShortcuts as $shortcut): ?>
                        <?php if (!empty($shortcut['available'])): ?>
                            <a <?= (string) ($shortcut['key'] ?? '') === 'form-builder' ? 'data-sales-dashboard-form-builder' : '' ?> href="<?= bx_h((string) ($shortcut['href'] ?? '')) ?>" class="flex min-h-12 items-center justify-center px-3 text-center text-xs font-medium hover:bg-muted/40"><?= bx_h((string) ($shortcut['label'] ?? '')) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section data-sales-dashboard-directory class="max-h-48 shrink-0 overflow-auto border-t">
                <div class="grid divide-y md:grid-cols-3 md:divide-x md:divide-y-0">
                    <?php foreach ($dashboardDirectories as $group): ?>
                        <div class="min-w-0 p-4">
                            <h3 class="text-xs font-semibold"><?= bx_h((string) ($group['group'] ?? '')) ?></h3>
                            <div class="mt-2 grid gap-1">
                                <?php foreach (is_array($group['items'] ?? null) ? $group['items'] : [] as $item): ?>
                                    <?php if (!empty($item['available'])): ?>
                                        <a href="<?= bx_h((string) ($item['href'] ?? '')) ?>" class="truncate rounded-md px-2 py-1.5 text-xs hover:bg-muted"><?= bx_h((string) ($item['label'] ?? '')) ?></a>
                                    <?php else: ?>
                                        <span class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs text-muted-foreground" aria-disabled="true"><span class="truncate"><?= bx_h((string) ($item['label'] ?? '')) ?></span><span class="shrink-0 text-[10px]">Unavailable</span></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>

        <aside class="flex min-h-0 flex-col overflow-auto rounded-lg border bg-card" aria-label="Sales dashboard tools">
            <section data-sales-dashboard-setup class="border-b p-5">
                <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Setup</h3><button type="button" data-sales-dashboard-tour-open class="text-xs font-medium text-primary hover:underline">Show Tour</button></div>
                <div class="mt-3 divide-y">
                    <?php foreach ($dashboardSetup as $step): ?>
                        <a href="<?= bx_h((string) ($step['href'] ?? '')) ?>" class="flex min-h-10 items-center justify-between gap-3 py-2 text-sm hover:text-primary"><span><?= bx_h((string) ($step['label'] ?? '')) ?></span><span class="text-xs <?= !empty($step['complete']) ? 'text-emerald-600' : 'text-muted-foreground' ?>"><?= !empty($step['complete']) ? 'Complete' : 'Pending' ?></span></a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section data-sales-dashboard-alerts class="border-b p-5">
                <h3 class="text-sm font-semibold">Alerts</h3>
                <div class="mt-3 grid gap-3">
                    <?php if (!$dashboardAlerts): ?><p class="text-sm text-muted-foreground">No Sales alerts require attention.</p><?php endif; ?>
                    <?php foreach ($dashboardAlerts as $alert): ?>
                        <div class="border-l-2 <?= (string) ($alert['severity'] ?? '') === 'WARNING' ? 'border-amber-500' : 'border-primary' ?> pl-3"><p class="text-sm leading-5"><?= bx_h((string) ($alert['label'] ?? '')) ?></p></div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="border-b p-5">
                <h3 class="text-sm font-semibold">Quick actions</h3>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <?php if (!empty($salesCrmAccess['crm'])): ?><button type="button" data-record-modal-open="yovel-sales-dashboard-lead-modal" class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Add Lead</button><?php endif; ?>
                    <?php if (!empty($salesCrmAccess['manage_settings'])): ?><button type="button" data-record-modal-open="yovel-sales-crm-settings-modal" class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Settings</button><?php endif; ?>
                    <a data-sales-dashboard-form-builder href="./?view=sales-crm&amp;section=leads#yovel-sales-form-builder" class="col-span-2 inline-flex h-9 items-center justify-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Form Builder</a>
                </div>
            </section>

            <section class="min-h-0 p-5">
                <h3 class="text-sm font-semibold">Service dependencies</h3>
                <div class="mt-3 divide-y">
                    <?php foreach ($dashboardDependencies as $dependency): ?>
                        <div class="py-2"><div class="flex items-center justify-between gap-3"><span class="text-sm"><?= bx_h((string) ($dependency['label'] ?? '')) ?></span><span class="text-[10px] font-medium text-muted-foreground">Unavailable</span></div><code class="mt-1 block truncate text-[10px] text-muted-foreground"><?= bx_h((string) ($dependency['contract'] ?? '')) ?></code></div>
                    <?php endforeach; ?>
                </div>
            </section>
        </aside>
    </div>

    <?php if (!empty($salesCrmAccess['crm'])): ?>
        <div id="yovel-sales-dashboard-lead-modal" data-record-modal <?= $dashboardLeadModalOpen ? 'data-record-modal-open-on-load' : '' ?> class="fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-sales-dashboard-lead-title" aria-describedby="yovel-sales-dashboard-lead-description" hidden>
            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-5xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                <form method="post" data-record-modal-form data-confirm-submit data-confirm-message="Confirm this Lead. It will not be saved until you confirm." class="contents">
                    <header class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4"><div><h3 id="yovel-sales-dashboard-lead-title" class="text-base font-semibold">Add Lead</h3><p id="yovel-sales-dashboard-lead-description" class="mt-1 text-sm text-muted-foreground">Uses the active Sales Form Builder layout.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close Lead form">×</button></header>
                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                    <input type="hidden" name="module_view" value="sales-crm">
                    <input type="hidden" name="action" value="sales_crm_dashboard_save_lead">
                    <input type="hidden" name="section" value="dashboard">
                    <input type="hidden" name="lead_key" value="">
                    <div class="min-h-0 overflow-y-auto p-5">
                        <?php if ($dashboardLeadFormState !== []): ?><div class="mb-5 border-l-2 border-destructive pl-4 text-sm text-destructive" role="alert"><?= bx_h((string) ($dashboardLeadFormState['error'] ?? 'The Lead was not saved.')) ?></div><?php endif; ?>
                        <div class="grid gap-6">
                            <?php foreach ($dashboardLeadFieldsBySection as $sectionKey => $sectionFields): ?>
                                <section class="grid gap-4"><h4 class="text-sm font-semibold"><?= bx_h(yovel_admin_sales_schema_section_label($dashboardLeadSchema, (string) $sectionKey)) ?></h4><div class="grid gap-3 lg:grid-cols-3"><?php foreach ($sectionFields as $field): ?><?php yovel_admin_render_sales_form_field($field, $dashboardLeadRecord, $salesCrmData); ?><?php endforeach; ?></div></section>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <footer class="flex shrink-0 items-center justify-end gap-2 border-t px-5 py-4"><button type="button" data-record-modal-close class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium">Cancel</button><button type="submit" data-confirm-submit-action class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Save Lead</button></footer>
                </form>
            </section>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/settings.php'; ?>

    <div data-sales-dashboard-tour class="fixed inset-0 z-40 grid place-items-center bg-background/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-sales-dashboard-tour-title" aria-describedby="yovel-sales-dashboard-tour-copy" hidden>
        <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-lg flex-col overflow-hidden rounded-lg border bg-popover shadow-lg">
            <header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><p data-sales-dashboard-tour-progress class="text-xs text-muted-foreground">Step 1 of 5</p><h3 id="yovel-sales-dashboard-tour-title" data-sales-dashboard-tour-title class="mt-1 text-base font-semibold">Live summary</h3></div><button type="button" data-sales-dashboard-tour-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close Sales dashboard tour">×</button></header>
            <div class="min-h-36 overflow-y-auto p-5"><p id="yovel-sales-dashboard-tour-copy" data-sales-dashboard-tour-copy class="text-sm leading-6 text-muted-foreground">Read live Lead value and Campaign state without leaving the workspace.</p></div>
            <footer class="flex flex-wrap items-center justify-between gap-2 border-t px-5 py-4">
                <form method="post" data-confirm-submit data-confirm-message="Skip this Sales dashboard tour and remember the choice?"><input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="sales-crm"><input type="hidden" name="action" value="sales_crm_save_preference"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="expected_version" value="<?= (int) ($salesCrmPreference['preference_version'] ?? 0) ?>"><input type="hidden" name="tour_status" value="DISMISSED"><input type="hidden" name="setup_dismissed" value="<?= (int) ($salesCrmPreference['setup_dismissed'] ?? 0) ?>"><button type="submit" data-confirm-submit-action class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium">Skip</button></form>
                <div class="flex gap-2"><button type="button" data-sales-dashboard-tour-back class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium" disabled>Back</button><button type="button" data-sales-dashboard-tour-next class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Next</button><form method="post" data-sales-dashboard-tour-finish-form data-confirm-submit data-confirm-message="Finish this Sales dashboard tour and mark it complete?" hidden><input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="sales-crm"><input type="hidden" name="action" value="sales_crm_save_preference"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="expected_version" value="<?= (int) ($salesCrmPreference['preference_version'] ?? 0) ?>"><input type="hidden" name="tour_status" value="COMPLETED"><input type="hidden" name="setup_dismissed" value="<?= (int) ($salesCrmPreference['setup_dismissed'] ?? 0) ?>"><button type="submit" data-confirm-submit-action class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Finish</button></form></div>
            </footer>
        </section>
    </div>
</div>
<script>
(() => {
    const tour = document.querySelector('[data-sales-dashboard-tour]');
    const openers = Array.from(document.querySelectorAll('[data-sales-dashboard-tour-open]'));
    const closeButton = tour?.querySelector('[data-sales-dashboard-tour-close]');
    const backButton = tour?.querySelector('[data-sales-dashboard-tour-back]');
    const nextButton = tour?.querySelector('[data-sales-dashboard-tour-next]');
    const finishForm = tour?.querySelector('[data-sales-dashboard-tour-finish-form]');
    const title = tour?.querySelector('[data-sales-dashboard-tour-title]');
    const copy = tour?.querySelector('[data-sales-dashboard-tour-copy]');
    const progress = tour?.querySelector('[data-sales-dashboard-tour-progress]');
    const steps = [
        ['Live summary', 'Read live Lead value and Campaign state without leaving the workspace.'],
        ['Follow-up queue', 'Open stale Leads and Campaigns nearing their end date from one bounded queue.'],
        ['Setup and alerts', 'Complete Sales setup and act on operational alerts in the tools panel.'],
        ['Shortcuts and directories', 'Move directly to available masters and reports; pending services stay explicit.'],
        ['Form Builder', 'Adjust the Lead and Campaign layouts through the module-local Form Builder.'],
    ];
    let activeStep = 0;
    let lastTrigger = null;
    const focusable = () => Array.from(tour?.querySelectorAll('button:not([disabled]), [href], input:not([disabled])') || []).filter((element) => !element.hidden && element.getClientRects().length > 0);
    const render = () => {
        if (!tour) return;
        title.textContent = steps[activeStep][0];
        copy.textContent = steps[activeStep][1];
        progress.textContent = `Step ${activeStep + 1} of ${steps.length}`;
        backButton.disabled = activeStep === 0;
        nextButton.hidden = activeStep === steps.length - 1;
        finishForm.hidden = activeStep !== steps.length - 1;
    };
    const open = (trigger) => { if (!tour) return; activeStep = 0; lastTrigger = trigger; render(); tour.hidden = false; document.body.classList.add('yovel-shell-modal-active'); setTimeout(() => closeButton?.focus(), 0); };
    const close = () => { if (!tour) return; tour.hidden = true; document.body.classList.toggle('yovel-shell-modal-active', Boolean(document.querySelector('[data-record-modal]:not([hidden])'))); lastTrigger?.focus(); };
    openers.forEach((button) => button.addEventListener('click', () => open(button)));
    closeButton?.addEventListener('click', close);
    backButton?.addEventListener('click', () => { activeStep = Math.max(0, activeStep - 1); render(); });
    nextButton?.addEventListener('click', () => { activeStep = Math.min(steps.length - 1, activeStep + 1); render(); });
    document.addEventListener('keydown', (event) => {
        if (!tour || tour.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (event.key !== 'Tab') return;
        const items = focusable();
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
})();
</script>
