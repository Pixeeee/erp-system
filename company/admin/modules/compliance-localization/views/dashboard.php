<?php
declare(strict_types=1);

$complianceDashboard = is_array($complianceData['dashboard'] ?? null) ? $complianceData['dashboard'] : [];
$complianceSummary = is_array($complianceDashboard['summary'] ?? null) ? $complianceDashboard['summary'] : [];
$complianceQueue = is_array($complianceDashboard['queue'] ?? null) ? $complianceDashboard['queue'] : [];
$complianceActivity = is_array($complianceDashboard['activity'] ?? null) ? $complianceDashboard['activity'] : [];
$complianceShortcuts = is_array($complianceDashboard['shortcuts'] ?? null) ? $complianceDashboard['shortcuts'] : [];
$complianceDirectories = is_array($complianceDashboard['directories'] ?? null) ? $complianceDashboard['directories'] : [];
$complianceStatusClasses = [
    'PENDING_APPROVAL' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    'INVALID_EVIDENCE' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    'MAPPING_GAP' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    'LEGAL_HOLD' => 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
];
?>
<div
    data-compliance-live-dashboard
    data-compliance-tour-storage-key="builderx:compliance-localization:<?= bx_h((string) ($complianceData['company_key_hash'] ?? 'company')) ?>:tour"
    class="grid min-h-0"
>
    <header class="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4">
        <div>
            <h2 class="text-base font-semibold">Philippine compliance operations</h2>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">Live rules, approvals, evidence integrity, legal holds, and Finance mapping readiness.</p>
        </div>
        <a href="#compliance-action-queue" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">
            <span class="material-symbols-rounded text-base" aria-hidden="true">checklist</span>
            Review queue
        </a>
    </header>

    <section data-compliance-dashboard-summary data-compliance-tour-target="summary" class="border-b" aria-labelledby="compliance-dashboard-summary-title">
        <div class="px-5 pt-5">
            <h3 id="compliance-dashboard-summary-title" class="text-sm font-semibold">Live summary</h3>
        </div>
        <div class="mt-3 grid divide-y sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-3">
            <?php foreach ($complianceSummary as $index => $item): ?>
                <?php $available = (string) ($item['availability'] ?? 'ERROR') === 'AVAILABLE'; ?>
                <a
                    href="<?= bx_h((string) ($item['href'] ?? './?view=compliance-localization&section=dashboard')) ?>"
                    data-availability="<?= bx_h((string) ($item['availability'] ?? 'ERROR')) ?>"
                    class="min-w-0 px-5 py-4 hover:bg-muted/40 sm:border-b <?= $index % 2 === 0 ? 'sm:border-r lg:border-r' : '' ?> <?= $index % 3 === 1 ? 'lg:border-r' : '' ?>"
                >
                    <p class="truncate text-xs font-medium text-muted-foreground"><?= bx_h((string) ($item['label'] ?? 'Metric')) ?></p>
                    <p class="mt-1 text-xl font-semibold"><?= $available ? (int) ($item['value'] ?? 0) : bx_h((string) ($item['availability'] ?? 'ERROR')) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="compliance-action-queue" data-compliance-dashboard-queue data-compliance-tour-target="queue" class="border-b px-5 py-5" aria-labelledby="compliance-dashboard-queue-title">
        <div class="flex items-center justify-between gap-3">
            <h3 id="compliance-dashboard-queue-title" class="text-sm font-semibold">Compliance action queue</h3>
            <span class="text-xs text-muted-foreground"><?= count($complianceQueue) ?> open</span>
        </div>
        <div class="mt-3 divide-y border-y">
            <?php if ($complianceQueue === []): ?>
                <div class="py-4 text-sm text-muted-foreground">No approval, evidence, mapping, or hold actions require attention.</div>
            <?php else: ?>
                <?php foreach ($complianceQueue as $item): ?>
                    <?php $queueStatus = (string) ($item['status'] ?? 'OPEN'); ?>
                    <a href="<?= bx_h((string) ($item['href'] ?? '#')) ?>" class="flex min-h-12 items-center justify-between gap-3 py-3 hover:text-primary">
                        <span class="min-w-0 truncate text-sm font-medium"><?= bx_h((string) ($item['label'] ?? 'Compliance action')) ?></span>
                        <span class="shrink-0 rounded-md px-2 py-1 text-[11px] font-semibold <?= bx_h($complianceStatusClasses[$queueStatus] ?? 'bg-muted text-muted-foreground') ?>"><?= bx_h(str_replace('_', ' ', $queueStatus)) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section data-compliance-dashboard-activity data-compliance-tour-target="activity" class="border-b px-5 py-5" aria-labelledby="compliance-dashboard-activity-title">
        <div class="flex items-center justify-between gap-3">
            <h3 id="compliance-dashboard-activity-title" class="text-sm font-semibold">Recent activity</h3>
            <span class="text-xs text-muted-foreground">Latest <?= count($complianceActivity) ?></span>
        </div>
        <div class="mt-3 divide-y border-y">
            <?php if ($complianceActivity === []): ?>
                <div class="py-4 text-sm text-muted-foreground">Activity appears after the first governed Compliance action.</div>
            <?php else: ?>
                <?php foreach ($complianceActivity as $item): ?>
                    <div class="grid gap-1 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-4">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium"><?= bx_h((string) ($item['record_label'] ?? 'Compliance record')) ?></p>
                            <p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) ($item['actor_label'] ?? 'Company administrator')) ?> · <?= bx_h((string) ($item['action'] ?? 'UPDATE')) ?></p>
                        </div>
                        <time class="text-xs text-muted-foreground" datetime="<?= bx_h((string) ($item['occurred_at'] ?? '')) ?>"><?= bx_h((string) ($item['occurred_at'] ?? '')) ?></time>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section data-compliance-dashboard-shortcuts data-compliance-tour-target="shortcuts" class="border-b px-5 py-5" aria-labelledby="compliance-dashboard-shortcuts-title">
        <h3 id="compliance-dashboard-shortcuts-title" class="text-sm font-semibold">Shortcuts</h3>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <?php foreach ($complianceShortcuts as $shortcut): ?>
                <a href="<?= bx_h((string) ($shortcut['href'] ?? '#')) ?>" class="flex min-h-11 items-center justify-between gap-3 rounded-md bg-muted/40 px-3 py-2 text-sm font-medium hover:bg-muted">
                    <span><?= bx_h((string) ($shortcut['label'] ?? 'Destination')) ?></span>
                    <span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">arrow_forward</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section data-compliance-dashboard-directories data-compliance-tour-target="directories" class="px-5 py-5" aria-labelledby="compliance-dashboard-directories-title">
        <h3 id="compliance-dashboard-directories-title" class="text-sm font-semibold">Directories</h3>
        <div class="mt-3 grid gap-5 md:grid-cols-2">
            <?php foreach ($complianceDirectories as $directory): ?>
                <div>
                    <h4 class="text-xs font-semibold uppercase text-muted-foreground"><?= bx_h((string) ($directory['group'] ?? 'Directory')) ?></h4>
                    <div class="mt-2 divide-y border-y">
                        <?php foreach ((array) ($directory['items'] ?? []) as $item): ?>
                            <?php if (!empty($item['available']) && is_string($item['href'] ?? null)): ?>
                                <a href="<?= bx_h((string) $item['href']) ?>" class="flex min-h-10 items-center justify-between gap-3 py-2 text-sm hover:text-primary">
                                    <span><?= bx_h((string) ($item['label'] ?? 'Destination')) ?></span>
                                    <span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">open_in_new</span>
                                </a>
                            <?php else: ?>
                                <div class="flex min-h-10 items-center justify-between gap-3 py-2 text-sm text-muted-foreground" aria-disabled="true">
                                    <span><?= bx_h((string) ($item['label'] ?? 'Destination')) ?></span>
                                    <span class="text-[11px] font-medium"><?= bx_h((string) ($item['status'] ?? 'NOT_IMPLEMENTED')) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div
        data-compliance-tour-dialog
        hidden
        class="fixed inset-0 z-[80] bg-background/70"
        role="dialog"
        aria-modal="true"
        aria-labelledby="compliance-tour-title"
        aria-describedby="compliance-tour-description"
    >
        <section class="absolute inset-x-4 bottom-4 z-[90] ml-auto max-w-sm rounded-lg border bg-popover shadow-lg sm:inset-x-auto sm:right-5 sm:w-[24rem]">
            <header class="flex items-start justify-between gap-3 border-b px-5 py-4">
                <div>
                    <p data-compliance-tour-progress class="text-xs font-medium text-muted-foreground">1 of 6</p>
                    <h2 id="compliance-tour-title" data-compliance-tour-title class="mt-1 text-base font-semibold">Live summary</h2>
                </div>
                <button type="button" data-compliance-tour-skip class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close Compliance dashboard tour">
                    <span class="material-symbols-rounded text-base" aria-hidden="true">close</span>
                </button>
            </header>
            <p id="compliance-tour-description" data-compliance-tour-description class="px-5 py-4 text-sm leading-6 text-muted-foreground">Read the current company Compliance position without opening each register.</p>
            <footer class="flex items-center justify-between gap-3 border-t px-5 py-4">
                <button type="button" data-compliance-tour-skip class="h-9 rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-muted">Skip</button>
                <div class="flex gap-2">
                    <button type="button" data-compliance-tour-back class="h-9 rounded-md border px-3 text-sm font-medium">Back</button>
                    <button type="button" data-compliance-tour-next class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Next</button>
                    <button type="button" data-compliance-tour-finish hidden class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Finish</button>
                </div>
            </footer>
        </section>
    </div>
</div>

<script>
(() => {
    const initializeComplianceTour = () => {
    const dashboard = document.querySelector('[data-compliance-live-dashboard]');
    const dialog = dashboard?.querySelector('[data-compliance-tour-dialog]');
    if (!dashboard || !dialog || dialog.dataset.bound === '1') return;
    dialog.dataset.bound = '1';
    const formBuilderModal = document.getElementById('compliance-form-builder-modal');
    if (formBuilderModal && formBuilderModal.dataset.complianceFormBuilderEscapeBound !== '1') {
        formBuilderModal.dataset.complianceFormBuilderEscapeBound = '1';
        formBuilderModal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (document.querySelector('[data-confirm-dialog]:not([hidden])')) return;
                event.preventDefault();
                const opener = formBuilderModal.dataset.recordModalOpener
                    ? document.getElementById(formBuilderModal.dataset.recordModalOpener)
                    : null;
                formBuilderModal.hidden = true;
                formBuilderModal.setAttribute('inert', '');
                formBuilderModal.setAttribute('aria-hidden', 'true');
                document.body.classList.toggle('yovel-shell-modal-active', Boolean(document.querySelector('[data-record-modal]:not([hidden])')));
                opener?.focus();
                return;
            }
            if (event.key !== 'Tab') return;
            const focusable = [...formBuilderModal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
                .filter((element) => !element.hidden && element.getClientRects().length > 0);
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }
    const steps = [
        { target: '[data-compliance-tour-target="summary"]', title: 'Live summary', body: 'Read active rules, approval work, evidence integrity, legal holds, and mapping readiness.' },
        { target: '[data-compliance-tour-target="queue"]', title: 'Action queue', body: 'Prioritize maker-checker reviews, invalid evidence, Finance mapping gaps, and open holds.' },
        { target: '[data-compliance-tour-target="activity"]', title: 'Recent activity', body: 'Trace the latest company-scoped governed actions and their recorded actors.' },
        { target: '[data-compliance-tour-target="shortcuts"]', title: 'Operational shortcuts', body: 'Open implemented Compliance workspaces without passing through unavailable packages.' },
        { target: '[data-compliance-tour-target="directories"]', title: 'Directories', body: 'Use live controls now and see future registers and filings as explicitly unavailable.' },
        { target: '[data-compliance-tour-target="tools"]', title: 'Setup and tools', body: 'Review setup progress, dependency health, alerts, and the universal Form Builder.' },
    ];
    const title = dialog.querySelector('[data-compliance-tour-title]');
    const description = dialog.querySelector('[data-compliance-tour-description]');
    const progress = dialog.querySelector('[data-compliance-tour-progress]');
    const back = dialog.querySelector('[data-compliance-tour-back]');
    const next = dialog.querySelector('[data-compliance-tour-next]');
    const finish = dialog.querySelector('[data-compliance-tour-finish]');
    const storageKey = dashboard.dataset.complianceTourStorageKey;
    let index = 0;
    let highlighted = null;
    let returnFocus = null;

    const clearHighlight = () => {
        if (!highlighted) return;
        highlighted.classList.remove('relative', 'z-[81]', 'ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-background');
        highlighted = null;
    };
    const showStep = () => {
        clearHighlight();
        const step = steps[index];
        highlighted = document.querySelector(step.target);
        highlighted?.classList.add('relative', 'z-[81]', 'ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-background');
        highlighted?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        title.textContent = step.title;
        description.textContent = step.body;
        progress.textContent = `${index + 1} of ${steps.length}`;
        back.hidden = index === 0;
        next.hidden = index === steps.length - 1;
        finish.hidden = index !== steps.length - 1;
        (index === steps.length - 1 ? finish : next).focus();
    };
    const closeTour = (remember) => {
        clearHighlight();
        dialog.hidden = true;
        if (remember && storageKey) {
            try { localStorage.setItem(storageKey, 'dismissed'); } catch (_) {}
        }
        returnFocus?.focus();
    };
    document.querySelectorAll('[data-compliance-tour-start]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            returnFocus = trigger;
            index = 0;
            dialog.hidden = false;
            showStep();
        });
    });
    back.addEventListener('click', () => { if (index > 0) { index -= 1; showStep(); } });
    next.addEventListener('click', () => { if (index < steps.length - 1) { index += 1; showStep(); } });
    finish.addEventListener('click', () => closeTour(true));
    dialog.querySelectorAll('[data-compliance-tour-skip]').forEach((button) => button.addEventListener('click', () => closeTour(true)));
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeTour(false);
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...dialog.querySelectorAll('button:not([hidden])')].filter((button) => !button.disabled);
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeComplianceTour, { once: true });
    } else {
        initializeComplianceTour();
    }
})();
</script>
