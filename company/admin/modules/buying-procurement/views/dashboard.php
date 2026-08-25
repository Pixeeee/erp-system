<?php
$buyingDashboard = is_array($buyingData['dashboard'] ?? null) ? $buyingData['dashboard'] : [
    'summary' => [], 'queue' => [], 'activity' => [], 'shortcuts' => [], 'directories' => [],
];
$buyingDashboardSummary = is_array($buyingDashboard['summary'] ?? null) ? $buyingDashboard['summary'] : [];
$buyingDashboardQueue = is_array($buyingDashboard['queue'] ?? null) ? $buyingDashboard['queue'] : [];
$buyingDashboardActivity = is_array($buyingDashboard['activity'] ?? null) ? $buyingDashboard['activity'] : [];
$buyingDashboardShortcuts = is_array($buyingDashboard['shortcuts'] ?? null) ? $buyingDashboard['shortcuts'] : [];
$buyingDashboardDirectories = is_array($buyingDashboard['directories'] ?? null) ? $buyingDashboard['directories'] : [];
$buyingDashboardIcon = static fn (string $key): string => match ($key) {
    'active-suppliers' => 'storefront',
    'material-requests' => 'inventory_2',
    'requests-for-quotation' => 'request_quote',
    'purchase-orders' => 'shopping_cart',
    'purchase-receipts' => 'move_to_inbox',
    'score-exceptions' => 'score',
    default => 'data_usage',
};
?>
<div data-buying-dashboard class="grid gap-6">
    <section data-buying-tour-target="summary" class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-medium text-muted-foreground"><?= bx_h((string) ($companyName ?? 'Company')) ?></p>
                <h3 class="mt-1 text-sm font-semibold">Procurement position</h3>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">Live supplier governance and scorecard exceptions, with document packages shown only when their owners are ready.</p>
            </div>
            <a href="./?view=buying-procurement&amp;section=suppliers" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><span class="material-symbols-rounded text-base" aria-hidden="true">storefront</span>Review Suppliers</a>
        </div>
        <div data-buying-dashboard-summary class="grid overflow-hidden border-y bg-muted/20 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($buyingDashboardSummary as $summary): ?>
                <?php $available = (string) ($summary['availability'] ?? 'ERROR') === 'AVAILABLE'; ?>
                <div class="min-h-28 p-4 sm:[&:nth-child(even)]:border-l xl:[&:not(:nth-child(3n+1))]:border-l">
                    <div class="flex items-start justify-between gap-3"><span class="material-symbols-rounded text-lg text-muted-foreground" aria-hidden="true"><?= bx_h($buyingDashboardIcon((string) ($summary['key'] ?? ''))) ?></span><span class="text-[11px] font-medium text-muted-foreground"><?= bx_h((string) ($summary['availability'] ?? 'ERROR')) ?></span></div>
                    <p class="mt-3 text-xs text-muted-foreground"><?= bx_h((string) ($summary['label'] ?? 'Metric')) ?></p>
                    <?php if ($available): ?><p class="mt-1 text-2xl font-semibold"><?= (int) ($summary['value'] ?? 0) ?></p><?php else: ?><p class="mt-1 text-sm font-medium">Unavailable</p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section data-buying-dashboard-queue data-buying-tour-target="queue" class="border-t pt-5">
        <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Procurement queue</h3><span class="text-xs text-muted-foreground"><?= count($buyingDashboardQueue) ?> open</span></div>
        <?php if ($buyingDashboardQueue === []): ?>
            <div class="mt-3 py-6 text-center"><span class="material-symbols-rounded text-2xl text-muted-foreground" aria-hidden="true">task_alt</span><p class="mt-2 text-sm font-medium">No supplier or scorecard exceptions require attention.</p></div>
        <?php else: ?>
            <div class="mt-3 divide-y border-y">
                <?php foreach ($buyingDashboardQueue as $item): ?>
                    <a href="<?= bx_h((string) $item['href']) ?>" class="flex min-h-12 items-center justify-between gap-3 px-1 py-3 hover:bg-muted/40">
                        <span class="min-w-0 text-sm"><?= bx_h((string) $item['label']) ?></span>
                        <span class="shrink-0 text-xs font-medium <?= (string) $item['status'] === 'BLOCKED' ? 'text-destructive' : 'text-muted-foreground' ?>"><?= bx_h((string) $item['status']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section data-buying-dashboard-activity data-buying-tour-target="activity" class="border-t pt-5">
        <h3 class="text-sm font-semibold">Recent procurement activity</h3>
        <?php if ($buyingDashboardActivity === []): ?>
            <p class="mt-3 py-5 text-sm text-muted-foreground">Activity appears after Buying Settings, suppliers, or scorecards are saved.</p>
        <?php else: ?>
            <div class="mt-3 divide-y border-y">
                <?php foreach ($buyingDashboardActivity as $activity): ?>
                    <div class="grid gap-1 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-4">
                        <div class="min-w-0"><p class="text-sm"><span class="font-medium"><?= bx_h((string) $activity['action']) ?></span> &middot; <?= bx_h((string) $activity['record_label']) ?></p><p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $activity['actor_label']) ?></p></div>
                        <time class="text-xs text-muted-foreground" datetime="<?= bx_h((string) $activity['occurred_at']) ?>"><?= bx_h((string) $activity['occurred_at']) ?></time>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section data-buying-dashboard-shortcuts data-buying-tour-target="shortcuts" class="border-t pt-5">
        <h3 class="text-sm font-semibold">Shortcuts</h3>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <?php foreach ($buyingDashboardShortcuts as $shortcut): ?>
                <?php if (!empty($shortcut['available']) && is_string($shortcut['href'] ?? null)): ?>
                    <a class="flex min-h-11 items-center justify-between gap-3 rounded-md bg-muted/40 px-3 py-2 text-sm hover:bg-muted" href="<?= bx_h((string) $shortcut['href']) ?>"><span><?= bx_h((string) $shortcut['label']) ?></span><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">arrow_forward</span></a>
                <?php else: ?>
                    <span aria-disabled="true" class="flex min-h-11 items-center justify-between gap-3 rounded-md bg-muted/20 px-3 py-2 text-sm text-muted-foreground"><span><?= bx_h((string) $shortcut['label']) ?></span><span class="text-[11px]">NOT_IMPLEMENTED</span></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section data-buying-dashboard-directories data-buying-tour-target="directories" class="border-t pt-5">
        <h3 class="text-sm font-semibold">Reports and masters</h3>
        <div class="mt-3 grid gap-6 sm:grid-cols-2">
            <?php foreach ($buyingDashboardDirectories as $directory): ?>
                <div><h4 class="text-xs font-semibold uppercase text-muted-foreground"><?= bx_h((string) $directory['group']) ?></h4><div class="mt-2 divide-y"><?php foreach (($directory['items'] ?? []) as $item): ?><?php if ((string) ($item['availability'] ?? '') === 'AVAILABLE' && is_string($item['href'] ?? null)): ?><a href="<?= bx_h((string) $item['href']) ?>" class="flex min-h-10 items-center justify-between gap-3 py-2 text-sm hover:text-primary"><span><?= bx_h((string) $item['label']) ?></span><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">open_in_new</span></a><?php else: ?><span aria-disabled="true" class="flex min-h-10 items-center justify-between gap-3 py-2 text-sm text-muted-foreground"><span><?= bx_h((string) $item['label']) ?></span><span class="text-[11px]"><?= bx_h((string) ($item['availability'] ?? 'ERROR')) ?></span></span><?php endif; ?><?php endforeach; ?></div></div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
