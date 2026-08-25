<?php
declare(strict_types=1);

$supportData = is_array($activeModuleData ?? null) ? $activeModuleData : [];
$supportSections = is_array($activeModuleSections ?? null) && $activeModuleSections !== []
    ? $activeModuleSections
    : yovel_admin_support_service_sections();
$supportRequestedSection = (string) ($activeModuleSection ?? $supportData['section'] ?? 'dashboard');
$supportSection = array_key_exists($supportRequestedSection, $supportSections) ? $supportRequestedSection : 'dashboard';
$supportMeta = $supportSections[$supportSection] ?? $supportSections['dashboard'];
$supportDashboard = is_array($supportData['dashboard'] ?? null) ? $supportData['dashboard'] : [];
$supportEscape = static fn (mixed $value): string => bx_h((string) $value);
$supportSettings = is_array($supportData['settings'] ?? null) ? $supportData['settings'] : [];
$supportSearchSources = is_array($supportData['search_sources'] ?? null) ? $supportData['search_sources'] : [];
$supportAdapter = is_array($supportData['form_adapter'] ?? null) ? $supportData['form_adapter'] : yovel_admin_support_service_form_adapter();
?>
<div data-support-service-workspace class="grid min-h-0 gap-4">
    <style>
        @media (min-width:1024px){[data-support-service-panels]{grid-template-columns:minmax(0,12fr) minmax(16rem,8fr)}}
        .yovel-support-tour-ring{outline:2px solid hsl(var(--ring));outline-offset:3px}
    </style>
    <header class="flex flex-wrap items-start justify-between gap-4 border-b pb-4">
        <div class="min-w-0"><p class="text-xs font-medium uppercase text-muted-foreground">Support / Service</p><h1 class="mt-1 text-xl font-semibold"><?= $supportEscape($supportMeta['label'] ?? 'Dashboard') ?></h1><p class="mt-1 truncate text-sm text-muted-foreground"><?= $supportEscape($supportData['company_name'] ?? $companyName ?? 'Company') ?></p></div>
        <div class="flex flex-wrap gap-2">
            <a href="./?view=support-service&amp;section=dashboard" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">dashboard</span>Dashboard</a>
            <a href="./?view=support-service&amp;section=form-builder" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder</a>
        </div>
    </header>

    <div data-support-service-panels class="grid min-h-0 gap-4">
        <main data-support-service-main data-grid-span="12" class="flex min-h-[32rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="shrink-0 border-b px-5 py-4"><h2 class="text-base font-semibold"><?= $supportEscape($supportMeta['label'] ?? 'Dashboard') ?></h2><p class="mt-1 text-sm text-muted-foreground"><?= $supportSection === 'dashboard' ? 'Company-scoped service readiness and operational contract health.' : 'Support-owned foundation data and dependency status.' ?></p></header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if ($supportSection === 'dashboard'): ?>
                    <?php require __DIR__ . '/dashboard.php'; ?>
                <?php elseif ($supportSection === 'issues-tickets'): ?>
                    <?php $supportIssueViewRegion = 'main'; require __DIR__ . '/issues.php'; ?>
                <?php elseif (in_array($supportSection, ['sla-rules', 'first-response-tracking'], true)): ?>
                    <?php $supportSlaViewRegion = 'main'; require __DIR__ . '/sla.php'; ?>
                <?php elseif ($supportSection === 'form-builder'): ?>
                    <section aria-labelledby="support-form-builder-heading">
                        <h3 id="support-form-builder-heading" class="text-sm font-semibold">Configurable record types</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Stable target and protected-field contracts for Support forms.</p>
                        <div class="mt-4 divide-y">
                            <?php foreach (($supportAdapter['target_record_types'] ?? []) as $sectionKey => $recordType): ?>
                                <div class="grid gap-1 py-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                                    <div><p class="text-sm font-medium"><?= $supportEscape($supportSections[$sectionKey]['label'] ?? $recordType) ?></p><code class="text-xs text-muted-foreground"><?= $supportEscape($recordType) ?></code></div>
                                    <p class="break-words text-xs text-muted-foreground">Protected: <?= $supportEscape(implode(', ', $supportAdapter['protected_fields'][$recordType] ?? [])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php elseif ($supportSection === 'customer-support-portal'): ?>
                    <section aria-labelledby="support-portal-heading">
                        <h3 id="support-portal-heading" class="text-sm font-semibold">Portal foundation</h3>
                        <dl class="mt-4 grid gap-x-5 sm:grid-cols-2">
                            <div class="border-b py-3"><dt class="text-xs text-muted-foreground">Settings</dt><dd class="mt-1 text-sm font-medium"><?= (string) ($supportSettings['support_setting_key'] ?? '') !== '' ? 'Configured' : 'Not configured' ?></dd></div>
                            <div class="border-b py-3"><dt class="text-xs text-muted-foreground">Portal</dt><dd class="mt-1 text-sm font-medium"><?= !empty($supportSettings['portal_enabled']) ? 'Enabled' : 'Disabled' ?></dd></div>
                            <div class="border-b py-3"><dt class="text-xs text-muted-foreground">Close policy</dt><dd class="mt-1 text-sm font-medium"><?= (int) ($supportSettings['close_issue_after_days'] ?? 0) ?> days</dd></div>
                            <div class="border-b py-3"><dt class="text-xs text-muted-foreground">Search sources</dt><dd class="mt-1 text-sm font-medium"><?= count($supportSearchSources) ?></dd></div>
                        </dl>
                        <div class="mt-5 divide-y" aria-label="Support search sources">
                            <?php foreach ($supportSearchSources as $source): ?><div class="flex items-center justify-between gap-3 py-3 text-sm"><span class="min-w-0 truncate font-medium"><?= $supportEscape($source['source_name'] ?? '') ?></span><span class="text-xs text-muted-foreground"><?= $supportEscape($source['source_status'] ?? '') ?></span></div><?php endforeach; ?>
                        </div>
                    </section>
                <?php else: ?>
                    <div class="grid min-h-64 place-items-center text-center">
                        <div class="max-w-md"><span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true"><?= $supportEscape($supportMeta['icon'] ?? 'hourglass_top') ?></span><h3 class="mt-3 text-sm font-semibold"><?= $supportEscape($supportMeta['label'] ?? 'Support workflow') ?> is unavailable</h3><p class="mt-1 text-sm leading-6 text-muted-foreground">The Support foundation is ready, but this operational package remains disabled until its approved owner contracts are installed.</p></div>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <aside data-support-service-tools data-grid-span="8" class="flex min-h-[32rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="shrink-0 border-b px-5 py-4"><h2 class="text-base font-semibold">Actions and tools</h2><p class="mt-1 text-sm text-muted-foreground">Setup, alerts, navigation, and dependency health.</p></header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if ($supportSection === 'dashboard'): ?>
                    <section data-support-dashboard-setup data-support-tour-target="setup" aria-labelledby="support-setup-heading">
                        <div class="flex items-center justify-between gap-3"><h3 id="support-setup-heading" class="text-sm font-semibold">Setup progress</h3><span class="text-xs text-muted-foreground"><?= count(array_filter($supportDashboard['setup'] ?? [], static fn (array $step): bool => !empty($step['complete']))) ?>/<?= count($supportDashboard['setup'] ?? []) ?></span></div>
                        <div class="mt-3 divide-y">
                            <?php foreach (($supportDashboard['setup'] ?? []) as $step): ?>
                                <?php if ((string) ($step['href'] ?? '') !== ''): ?><a href="<?= $supportEscape($step['href']) ?>" class="flex items-center gap-3 py-3 text-sm hover:underline"><?php else: ?><div class="flex items-center gap-3 py-3 text-sm text-muted-foreground"><?php endif; ?>
                                    <span class="material-symbols-rounded text-base <?= !empty($step['complete']) ? 'text-emerald-600' : 'text-muted-foreground' ?>" aria-hidden="true"><?= !empty($step['complete']) ? 'check_circle' : 'radio_button_unchecked' ?></span><span class="min-w-0 flex-1"><?= $supportEscape($step['label'] ?? '') ?></span>
                                <?= (string) ($step['href'] ?? '') !== '' ? '</a>' : '</div>' ?>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section data-support-dashboard-alerts class="mt-5 border-t pt-5" aria-labelledby="support-alerts-heading">
                        <h3 id="support-alerts-heading" class="text-sm font-semibold">Alerts</h3>
                        <?php if (($supportDashboard['alerts'] ?? []) === []): ?><p class="mt-2 text-sm text-muted-foreground">No foundation alerts.</p><?php else: ?><div class="mt-2 divide-y"><?php foreach (($supportDashboard['alerts'] ?? []) as $alert): ?><div class="flex items-start gap-2 py-2.5 text-sm"><span class="material-symbols-rounded mt-0.5 text-base text-muted-foreground" aria-hidden="true"><?= ($alert['severity'] ?? '') === 'ERROR' ? 'error' : (($alert['severity'] ?? '') === 'WARNING' ? 'warning' : 'info') ?></span><span><?= $supportEscape($alert['label'] ?? '') ?></span></div><?php endforeach; ?></div><?php endif; ?>
                    </section>

                    <section data-support-dashboard-dependencies data-support-tour-target="dependencies" class="mt-5 border-t pt-5" aria-labelledby="support-dependencies-heading">
                        <h3 id="support-dependencies-heading" class="text-sm font-semibold">Dependency health</h3>
                        <div class="mt-2 divide-y">
                            <?php foreach (($supportDashboard['dependencies'] ?? []) as $dependency): ?>
                                <div class="grid gap-1 py-2.5 text-sm sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <div class="min-w-0"><p class="truncate font-medium"><?= $supportEscape($dependency['label'] ?? '') ?></p><code class="block break-all text-[0.7rem] text-muted-foreground"><?= $supportEscape($dependency['contract'] ?? '') ?></code></div>
                                    <span class="text-xs <?= ($dependency['status'] ?? '') === 'AVAILABLE' ? 'text-emerald-600' : 'text-muted-foreground' ?>"><?= ($dependency['status'] ?? '') === 'AVAILABLE' ? 'Available' : 'Unavailable' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="mt-5 border-t pt-5" data-support-tour-target="tools">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-support-tour-start class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">tour</span>Show Tour</button>
                            <a href="./?view=support-service&amp;section=form-builder" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder</a>
                        </div>
                    </section>
                <?php elseif ($supportSection === 'issues-tickets'): ?>
                    <?php $supportIssueViewRegion = 'tools'; require __DIR__ . '/issues.php'; ?>
                <?php elseif (in_array($supportSection, ['sla-rules', 'first-response-tracking'], true)): ?>
                    <?php $supportSlaViewRegion = 'tools'; require __DIR__ . '/sla.php'; ?>
                <?php else: ?>
                    <nav aria-label="Support / Service sections" class="grid gap-1">
                        <?php foreach ($supportSections as $sectionKey => $sectionMeta): ?><a href="./?view=support-service&amp;section=<?= $supportEscape($sectionKey) ?>" class="flex min-h-9 items-center gap-2 rounded-md px-3 text-sm <?= $supportSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'hover:bg-muted' ?>"<?= $supportSection === $sectionKey ? ' aria-current="page"' : '' ?>><span class="material-symbols-rounded text-base" aria-hidden="true"><?= $supportEscape($sectionMeta['icon'] ?? 'arrow_forward') ?></span><span class="min-w-0 flex-1 truncate"><?= $supportEscape($sectionMeta['label'] ?? $sectionKey) ?></span></a><?php endforeach; ?>
                    </nav>
                    <section class="mt-5 border-t pt-5"><h3 class="text-sm font-semibold">Foundation</h3><dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-xs text-muted-foreground">Settings</dt><dd class="mt-1 font-medium"><?= (string) ($supportSettings['support_setting_key'] ?? '') !== '' ? 'Ready' : 'Open' ?></dd></div><div><dt class="text-xs text-muted-foreground">Search sources</dt><dd class="mt-1 font-medium"><?= count($supportSearchSources) ?></dd></div><div><dt class="text-xs text-muted-foreground">Form targets</dt><dd class="mt-1 font-medium"><?= count($supportAdapter['target_record_types'] ?? []) ?></dd></div><div><dt class="text-xs text-muted-foreground">Stable keys</dt><dd class="mt-1 font-medium">Protected</dd></div></dl></section>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<?php if ($supportSection === 'dashboard'): ?>
<div data-support-tour hidden class="fixed inset-0 z-[70] bg-background/55" aria-hidden="true">
    <section class="fixed bottom-4 right-4 w-[min(24rem,calc(100vw-2rem))] rounded-lg border bg-popover p-4 text-popover-foreground shadow-lg" role="dialog" aria-modal="true" aria-labelledby="support-tour-title" aria-describedby="support-tour-body">
        <p class="text-xs font-medium text-muted-foreground">Support workspace tour</p><h2 id="support-tour-title" data-support-tour-title class="mt-1 text-base font-semibold"></h2><p id="support-tour-body" data-support-tour-body class="mt-2 text-sm leading-6 text-muted-foreground"></p>
        <div class="mt-4 flex items-center justify-between gap-3"><button type="button" data-support-tour-skip class="h-9 rounded-md border px-3 text-sm">Skip</button><div class="flex gap-2"><button type="button" data-support-tour-back class="h-9 rounded-md border px-3 text-sm">Back</button><button type="button" data-support-tour-next class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Next</button></div></div>
    </section>
</div>
<script>
(() => {
    const root = document.querySelector('[data-support-service-workspace]');
    const overlay = document.querySelector('[data-support-tour]');
    const trigger = document.querySelector('[data-support-tour-start]');
    if (!root || !overlay || !trigger) return;
    const steps = [
        ['summary', 'Service overview', 'Review operational metrics without treating missing owner contracts as zero.'],
        ['queue', 'Action queue', 'Start with foundation setup while workflow queues remain visibly unavailable.'],
        ['activity', 'Recent activity', 'Track company-scoped settings and search-source changes.'],
        ['setup', 'Setup progress', 'Complete the Support foundation before enabling ticket and SLA packages.'],
        ['dependencies', 'Dependency health', 'See the exact Support, Sales, Projects, and Operations contracts still required.'],
        ['tools', 'Support tools', 'Reopen this tour or move to the module-local Form Builder.'],
    ];
    const title = overlay.querySelector('[data-support-tour-title]');
    const body = overlay.querySelector('[data-support-tour-body]');
    const back = overlay.querySelector('[data-support-tour-back]');
    const next = overlay.querySelector('[data-support-tour-next]');
    const skip = overlay.querySelector('[data-support-tour-skip]');
    const storageKey = <?= json_encode('builderx:support-service:' . (string) ($supportData['company_key_hash'] ?? 'company') . ':tour', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let index = 0;
    let target = null;
    const focusable = () => Array.from(overlay.querySelectorAll('button:not([disabled])'));
    const close = (state = 'dismissed') => {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        target?.classList.remove('yovel-support-tour-ring');
        target = null;
        window.localStorage.setItem(storageKey, state);
        trigger.focus();
    };
    const render = () => {
        target?.classList.remove('yovel-support-tour-ring');
        const step = steps[index];
        target = root.querySelector(`[data-support-tour-target="${step[0]}"]`);
        target?.classList.add('yovel-support-tour-ring');
        target?.scrollIntoView({block: 'nearest'});
        title.textContent = step[1];
        body.textContent = step[2];
        back.disabled = index === 0;
        next.textContent = index === steps.length - 1 ? 'Finish' : 'Next';
    };
    trigger.addEventListener('click', () => { index = 0; overlay.hidden = false; overlay.setAttribute('aria-hidden', 'false'); render(); next.focus(); });
    back.addEventListener('click', () => { if (index > 0) index -= 1; render(); });
    next.addEventListener('click', () => { if (index >= steps.length - 1) close('completed'); else { index += 1; render(); } });
    skip.addEventListener('click', () => close('dismissed'));
    overlay.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { event.preventDefault(); close('dismissed'); return; }
        if (event.key !== 'Tab') return;
        const controls = focusable();
        if (controls.length === 0) return;
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
})();
</script>
<?php endif; ?>
