<?php
declare(strict_types=1);

$projectsDashboard = is_array($projectsData['dashboard'] ?? null) ? $projectsData['dashboard'] : [];
$projectsAvailabilityLabel = static fn (string $availability): string => match ($availability) {
    'AVAILABLE' => 'Available',
    'NOT_IMPLEMENTED' => 'Not implemented',
    'ERROR' => 'Error',
    default => 'Unavailable dependency',
};
?>
<div data-projects-dashboard class="space-y-6">
    <section data-projects-dashboard-summary data-projects-tour-target="summary" aria-labelledby="projects-dashboard-summary-heading" class="space-y-3">
        <div class="flex items-end justify-between gap-3 border-b pb-3">
            <div>
                <h2 id="projects-dashboard-summary-heading" class="text-lg font-semibold">Operational summary</h2>
                <p class="mt-1 text-sm text-muted-foreground">Live Projects foundation and configuration status.</p>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach (($projectsDashboard['summary'] ?? []) as $summary): ?>
                <?php $availability = (string) ($summary['availability'] ?? 'ERROR'); ?>
                <a href="<?= $projectsEscape($summary['href'] ?? './?view=projects&section=dashboard') ?>" class="min-w-0 rounded-md border p-3 hover:bg-muted/40">
                    <span class="block truncate text-xs text-muted-foreground"><?= $projectsEscape($summary['label'] ?? '') ?></span>
                    <strong class="mt-1 block text-lg"><?= $projectsEscape($summary['value'] ?? '') ?></strong>
                    <span class="mt-2 block text-xs font-medium" data-availability="<?= $projectsEscape($availability) ?>"><?= $projectsEscape($projectsAvailabilityLabel($availability)) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section data-projects-dashboard-queue data-projects-tour-target="queue" aria-labelledby="projects-dashboard-queue-heading" class="space-y-3 border-t pt-5">
            <h2 id="projects-dashboard-queue-heading" class="text-sm font-semibold">Setup queue</h2>
            <?php if (($projectsDashboard['queue'] ?? []) === []): ?>
                <p class="text-sm text-muted-foreground">No pending Projects setup work.</p>
            <?php endif; ?>
            <div class="divide-y">
                <?php foreach (($projectsDashboard['queue'] ?? []) as $item): ?>
                    <a href="<?= $projectsEscape($item['href'] ?? '') ?>" class="flex items-center justify-between gap-3 py-3 text-sm hover:text-primary">
                        <span><?= $projectsEscape($item['label'] ?? '') ?></span>
                        <span class="text-xs font-medium text-muted-foreground"><?= $projectsEscape($item['status'] ?? '') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section data-projects-dashboard-activity data-projects-tour-target="activity" aria-labelledby="projects-dashboard-activity-heading" class="space-y-3 border-t pt-5">
            <h2 id="projects-dashboard-activity-heading" class="text-sm font-semibold">Recent activity</h2>
            <?php if (($projectsDashboard['activity'] ?? []) === []): ?>
                <p class="text-sm text-muted-foreground">No Projects changes have been recorded for this company.</p>
            <?php endif; ?>
            <div class="divide-y">
                <?php foreach (($projectsDashboard['activity'] ?? []) as $activity): ?>
                    <div class="py-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium"><?= $projectsEscape($activity['record_label'] ?? '') ?></span>
                            <span class="text-xs text-muted-foreground"><?= $projectsEscape($activity['action'] ?? '') ?></span>
                        </div>
                        <p class="mt-1 truncate text-xs text-muted-foreground"><?= $projectsEscape($activity['occurred_at'] ?? '') ?> · <?= $projectsEscape($activity['actor_label'] ?? '') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <section data-projects-dashboard-alerts aria-labelledby="projects-dashboard-alerts-heading" class="space-y-3 border-t pt-5">
        <h2 id="projects-dashboard-alerts-heading" class="text-sm font-semibold">Alerts</h2>
        <?php if (($projectsDashboard['alerts'] ?? []) === []): ?>
            <p class="text-sm text-muted-foreground">No Projects alerts.</p>
        <?php else: ?>
            <div class="divide-y border-y">
                <?php foreach (($projectsDashboard['alerts'] ?? []) as $alert): ?>
                    <a href="<?= $projectsEscape($alert['href'] ?? '') ?>" class="flex items-start gap-3 py-3 text-sm">
                        <span class="material-symbols-rounded text-base text-amber-600" aria-hidden="true">warning</span>
                        <span class="min-w-0"><strong class="block text-xs"><?= $projectsEscape($alert['severity'] ?? 'WARNING') ?></strong><span class="text-muted-foreground"><?= $projectsEscape($alert['label'] ?? '') ?></span></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section data-projects-dashboard-shortcuts data-projects-tour-target="shortcuts" aria-labelledby="projects-dashboard-shortcuts-heading" class="space-y-3 border-t pt-5">
        <h2 id="projects-dashboard-shortcuts-heading" class="text-sm font-semibold">Shortcuts</h2>
        <div class="grid gap-2 sm:grid-cols-2">
            <?php foreach (($projectsDashboard['shortcuts'] ?? []) as $shortcut): ?>
                <a href="<?= $projectsEscape($shortcut['href'] ?? '') ?>" class="flex min-h-11 items-center justify-between gap-3 rounded-md bg-muted/40 px-3 py-2 text-sm hover:bg-muted" <?= empty($shortcut['available']) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <span><?= $projectsEscape($shortcut['label'] ?? '') ?></span>
                    <span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">arrow_forward</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section data-projects-dashboard-directories data-projects-tour-target="directories" aria-labelledby="projects-dashboard-directories-heading" class="space-y-3 border-t pt-5">
        <h2 id="projects-dashboard-directories-heading" class="text-sm font-semibold">Reports and masters</h2>
        <div class="grid gap-6 sm:grid-cols-2">
            <?php foreach (($projectsDashboard['directories'] ?? []) as $directory): ?>
                <div>
                    <h3 class="text-sm font-semibold"><?= $projectsEscape($directory['group'] ?? '') ?></h3>
                    <div class="mt-2 divide-y">
                        <?php foreach (($directory['items'] ?? []) as $item): ?>
                            <?php if (!empty($item['available'])): ?>
                                <a href="<?= $projectsEscape($item['href'] ?? '') ?>" class="flex items-center justify-between gap-3 py-2 text-sm hover:text-primary"><span><?= $projectsEscape($item['label'] ?? '') ?></span><span aria-hidden="true">↗</span></a>
                            <?php else: ?>
                                <span class="flex items-center justify-between gap-3 py-2 text-sm text-muted-foreground"><span><?= $projectsEscape($item['label'] ?? '') ?></span><span>Unavailable</span></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div data-projects-dashboard-tour hidden class="fixed inset-0 z-[80] bg-background/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="projects-dashboard-tour-title" aria-describedby="projects-dashboard-tour-description">
        <section class="mx-auto mt-[12vh] w-full max-w-md rounded-md border bg-card shadow-lg" role="document">
            <header class="border-b px-5 py-4">
                <p data-projects-tour-progress class="text-xs font-medium text-muted-foreground">Step 1 of 5</p>
                <h2 id="projects-dashboard-tour-title" data-projects-tour-title class="mt-1 text-base font-semibold">Operational summary</h2>
                <p id="projects-dashboard-tour-description" data-projects-tour-description class="mt-2 text-sm leading-6 text-muted-foreground">Review live foundation and setup signals.</p>
            </header>
            <footer class="flex items-center justify-between gap-3 px-5 py-4">
                <button type="button" data-projects-tour-skip class="h-9 rounded-md border px-3 text-sm">Skip</button>
                <div class="flex gap-2">
                    <button type="button" data-projects-tour-back class="h-9 rounded-md border px-3 text-sm" disabled>Back</button>
                    <button type="button" data-projects-tour-next class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Next</button>
                </div>
            </footer>
        </section>
    </div>
</div>
<script>
(() => {
    const initializeProjectsDashboardTour = () => {
    const workspace = document.querySelector('[data-projects-workspace]');
    const tour = workspace?.querySelector('[data-projects-dashboard-tour]');
    const triggers = Array.from(workspace?.querySelectorAll('[data-projects-tour-start]') || []);
    if (!workspace || !tour || triggers.length === 0) return;
    const storageKey = 'projects-dashboard-tour:' + (workspace.dataset.projectsCompany || 'company');
    const steps = [
        ['summary', 'Operational summary', 'Review live foundation and setup signals.'],
        ['queue', 'Setup queue', 'Finish the configuration work needed to activate Projects.'],
        ['activity', 'Recent activity', 'Trace company-scoped settings and Form Builder changes.'],
        ['shortcuts', 'Shortcuts', 'Open the most-used Projects workspaces.'],
        ['directories', 'Reports and masters', 'Find implemented destinations and explicit unavailable reports.'],
    ];
    const title = tour.querySelector('[data-projects-tour-title]');
    const description = tour.querySelector('[data-projects-tour-description]');
    const progress = tour.querySelector('[data-projects-tour-progress]');
    const back = tour.querySelector('[data-projects-tour-back]');
    const next = tour.querySelector('[data-projects-tour-next]');
    const skip = tour.querySelector('[data-projects-tour-skip]');
    let index = 0;
    let activeTarget = null;
    let returnFocus = null;
    const render = () => {
        activeTarget?.classList.remove('ring-2', 'ring-primary', 'ring-offset-2');
        const step = steps[index];
        activeTarget = workspace.querySelector(`[data-projects-tour-target="${step[0]}"]`);
        activeTarget?.classList.add('ring-2', 'ring-primary', 'ring-offset-2');
        title.textContent = step[1];
        description.textContent = step[2];
        progress.textContent = `Step ${index + 1} of ${steps.length}`;
        back.disabled = index === 0;
        next.textContent = index === steps.length - 1 ? 'Finish' : 'Next';
    };
    const close = (remember) => {
        activeTarget?.classList.remove('ring-2', 'ring-primary', 'ring-offset-2');
        tour.hidden = true;
        if (remember) localStorage.setItem(storageKey, 'complete');
        returnFocus?.focus();
    };
    triggers.forEach((trigger) => trigger.addEventListener('click', () => {
        returnFocus = trigger;
        index = 0;
        render();
        tour.hidden = false;
        skip.focus();
    }));
    back.addEventListener('click', () => { if (index > 0) { index--; render(); } });
    next.addEventListener('click', () => { if (index === steps.length - 1) close(true); else { index++; render(); } });
    skip.addEventListener('click', () => close(true));
    tour.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { event.preventDefault(); close(false); return; }
        if (event.key !== 'Tab') return;
        const focusable = [skip, back, next].filter((item) => !item.disabled);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeProjectsDashboardTour, {once: true});
    } else {
        initializeProjectsDashboardTour();
    }
})();
</script>
