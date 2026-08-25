<?php
declare(strict_types=1);

$assetsDashboard = is_array($assetsData['dashboard'] ?? null) ? $assetsData['dashboard'] : [];
$assetsDashboardEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetsAvailabilityLabel = static fn (string $availability): string => match ($availability) {
    'AVAILABLE' => 'Available',
    'ERROR' => 'Read error',
    'NOT_IMPLEMENTED' => 'Not implemented',
    default => 'Unavailable dependency',
};
$assetsStatusClass = static fn (string $status): string => match ($status) {
    'AVAILABLE', 'PUBLISHED', 'COMPLETE' => 'text-emerald-600',
    'ERROR' => 'text-destructive',
    'DRAFT', 'OPEN' => 'text-amber-600',
    default => 'text-muted-foreground',
};
?>
<div data-assets-dashboard class="grid gap-7">
    <section data-assets-dashboard-summary data-assets-tour-target="summary" aria-labelledby="assets-dashboard-summary-title">
        <div class="flex items-end justify-between gap-4">
            <div><h3 id="assets-dashboard-summary-title" class="text-sm font-semibold">Operational summary</h3><p class="mt-1 text-sm text-muted-foreground">Live foundation records and explicit lifecycle availability.</p></div>
            <span class="text-xs text-muted-foreground">Company scoped</span>
        </div>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($assetsDashboard['summary'] ?? [] as $item): ?>
                <?php $availability = (string) ($item['availability'] ?? 'ERROR'); ?>
                <div class="min-w-0 bg-muted/30 px-3 py-3">
                    <dt class="truncate text-xs text-muted-foreground"><?= $assetsDashboardEscape((string) ($item['label'] ?? 'Metric')) ?></dt>
                    <dd class="mt-1 flex min-h-8 items-end justify-between gap-2">
                        <?php if ($availability === 'AVAILABLE'): ?><span class="text-xl font-semibold"><?= (int) ($item['value'] ?? 0) ?></span><?php else: ?><span class="text-sm font-semibold">Unavailable</span><?php endif; ?>
                        <span class="text-[10px] font-medium <?= $assetsStatusClass($availability) ?>" title="<?= $assetsDashboardEscape($availability) ?>"><?= $assetsDashboardEscape($assetsAvailabilityLabel($availability)) ?></span>
                    </dd>
                    <?php if ($availability !== 'AVAILABLE'): ?><span class="sr-only"><?= $assetsDashboardEscape($availability) ?></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

    <section data-assets-dashboard-queue data-assets-tour-target="queue" class="border-t pt-5" aria-labelledby="assets-dashboard-queue-title">
        <div class="flex items-center justify-between gap-3"><h3 id="assets-dashboard-queue-title" class="text-sm font-semibold">Action queue</h3><span class="text-xs text-muted-foreground"><?= count($assetsDashboard['queue'] ?? []) ?> items</span></div>
        <div class="mt-3 divide-y border-y">
            <?php foreach ($assetsDashboard['queue'] ?? [] as $item): ?>
                <?php $available = (string) ($item['status'] ?? '') !== 'UNAVAILABLE_DEPENDENCY'; ?>
                <?php if ($available): ?><a class="flex min-h-12 items-center gap-3 px-1 py-2.5 text-sm hover:bg-muted/40" href="<?= $assetsDashboardEscape((string) ($item['href'] ?? '#')) ?>"><?php else: ?><div class="flex min-h-12 items-center gap-3 px-1 py-2.5 text-sm" aria-disabled="true"><?php endif; ?>
                    <span class="material-symbols-rounded text-base <?= $assetsStatusClass((string) ($item['status'] ?? '')) ?>" aria-hidden="true"><?= $available ? 'pending_actions' : 'block' ?></span>
                    <span class="min-w-0 flex-1"><?= $assetsDashboardEscape((string) ($item['label'] ?? 'Queue item')) ?></span>
                    <span class="shrink-0 text-[10px] font-medium <?= $assetsStatusClass((string) ($item['status'] ?? '')) ?>"><?= $assetsDashboardEscape((string) ($item['status'] ?? 'OPEN')) ?></span>
                <?= $available ? '</a>' : '</div>' ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section data-assets-dashboard-activity data-assets-tour-target="activity" class="border-t pt-5" aria-labelledby="assets-dashboard-activity-title">
        <div class="flex items-center justify-between gap-3"><h3 id="assets-dashboard-activity-title" class="text-sm font-semibold">Recent activity</h3><span class="text-xs text-muted-foreground">Latest 8</span></div>
        <?php if (($assetsDashboard['activity'] ?? []) === []): ?>
            <div class="mt-3 py-6 text-center"><span class="material-symbols-rounded text-2xl text-muted-foreground" aria-hidden="true">history</span><p class="mt-2 text-sm font-medium">No Assets activity yet</p><p class="mt-1 text-xs text-muted-foreground">Form lifecycle changes will appear here.</p></div>
        <?php else: ?>
            <div class="mt-3 divide-y border-y">
                <?php foreach ($assetsDashboard['activity'] as $item): ?>
                    <a class="grid gap-1 px-1 py-3 text-sm hover:bg-muted/40 sm:grid-cols-[minmax(0,1fr)_auto]" href="<?= $assetsDashboardEscape((string) ($item['href'] ?? '#')) ?>">
                        <span class="min-w-0"><span class="font-medium"><?= $assetsDashboardEscape((string) ($item['record_label'] ?? 'Assets form')) ?></span><span class="ml-2 text-xs <?= $assetsStatusClass((string) ($item['action'] ?? '')) ?>"><?= $assetsDashboardEscape((string) ($item['action'] ?? 'UPDATE')) ?></span><span class="block text-xs text-muted-foreground"><?= $assetsDashboardEscape((string) ($item['actor_label'] ?? 'Company administrator')) ?></span></span>
                        <time class="text-xs text-muted-foreground" datetime="<?= $assetsDashboardEscape((string) ($item['occurred_at'] ?? '')) ?>"><?= $assetsDashboardEscape((string) ($item['occurred_at'] ?? '')) ?></time>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section data-assets-dashboard-shortcuts data-assets-tour-target="shortcuts" class="border-t pt-5" aria-labelledby="assets-dashboard-shortcuts-title">
        <h3 id="assets-dashboard-shortcuts-title" class="text-sm font-semibold">Shortcuts</h3>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <?php foreach ($assetsDashboard['shortcuts'] ?? [] as $item): ?>
                <?php if (!empty($item['available'])): ?><a class="flex min-h-11 items-center gap-3 bg-muted/30 px-3 py-2 text-sm font-medium hover:bg-muted" href="<?= $assetsDashboardEscape((string) ($item['href'] ?? '#')) ?>"><?php else: ?><div class="flex min-h-11 items-center gap-3 bg-muted/20 px-3 py-2 text-sm text-muted-foreground" aria-disabled="true"><?php endif; ?>
                    <span class="material-symbols-rounded text-base" aria-hidden="true"><?= $assetsDashboardEscape((string) ($item['icon'] ?? 'arrow_forward')) ?></span><span class="min-w-0 flex-1"><?= $assetsDashboardEscape((string) ($item['label'] ?? 'Shortcut')) ?></span><span class="text-[10px]"><?= $assetsDashboardEscape((string) ($item['availability'] ?? 'AVAILABLE')) ?></span>
                <?= !empty($item['available']) ? '</a>' : '</div>' ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section data-assets-dashboard-directories data-assets-tour-target="directories" class="border-t pt-5" aria-labelledby="assets-dashboard-directories-title">
        <h3 id="assets-dashboard-directories-title" class="text-sm font-semibold">Reports and masters</h3>
        <div class="mt-3 grid gap-6 sm:grid-cols-3">
            <?php foreach ($assetsDashboard['directories'] ?? [] as $group): ?>
                <div class="min-w-0"><h4 class="text-xs font-semibold uppercase text-muted-foreground"><?= $assetsDashboardEscape((string) ($group['group'] ?? 'Directory')) ?></h4><div class="mt-2 grid gap-1"><?php foreach ($group['items'] ?? [] as $item): ?><?php if (!empty($item['available'])): ?><a class="flex items-center justify-between gap-2 py-1.5 text-sm hover:underline" href="<?= $assetsDashboardEscape((string) ($item['href'] ?? '#')) ?>"><span><?= $assetsDashboardEscape((string) ($item['label'] ?? 'Item')) ?></span><span class="material-symbols-rounded text-sm text-muted-foreground" aria-hidden="true">arrow_forward</span></a><?php else: ?><span class="flex items-center justify-between gap-2 py-1.5 text-sm text-muted-foreground" aria-disabled="true"><span><?= $assetsDashboardEscape((string) ($item['label'] ?? 'Item')) ?></span><span class="text-[9px]"><?= $assetsDashboardEscape((string) ($item['availability'] ?? 'NOT_IMPLEMENTED')) ?></span></span><?php endif; ?><?php endforeach; ?></div></div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
