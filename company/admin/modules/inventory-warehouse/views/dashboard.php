<?php
declare(strict_types=1);

$inventoryDashboard = is_array($inventoryData['dashboard'] ?? null) ? $inventoryData['dashboard'] : [];
$inventoryDashboardSummary = is_array($inventoryDashboard['summary'] ?? null) ? $inventoryDashboard['summary'] : [];
$inventoryDashboardQueue = is_array($inventoryDashboard['queue'] ?? null) ? $inventoryDashboard['queue'] : [];
$inventoryDashboardActivity = is_array($inventoryDashboard['activity'] ?? null) ? $inventoryDashboard['activity'] : [];
?>
<div data-inventory-dashboard class="grid gap-7">
    <section data-inventory-dashboard-summary aria-labelledby="inventory-dashboard-summary-title">
        <div class="flex items-center justify-between gap-3">
            <h3 id="inventory-dashboard-summary-title" class="text-sm font-semibold">Stock operations</h3>
            <span class="text-xs text-muted-foreground">Live company data</span>
        </div>
        <div class="mt-3 grid grid-cols-2 border sm:grid-cols-3">
            <?php foreach ($inventoryDashboardSummary as $metric): ?>
                <a href="<?= $inventoryEscape((string) ($metric['href'] ?? '#')) ?>" class="min-w-0 border-b border-r px-3 py-3 hover:bg-muted/40">
                    <span class="block truncate text-xs text-muted-foreground"><?= $inventoryEscape((string) ($metric['label'] ?? 'Metric')) ?></span>
                    <?php if (($metric['availability'] ?? '') === 'AVAILABLE'): ?>
                        <strong class="mt-1 block text-lg font-semibold"><?= $inventoryEscape((string) ($metric['value'] ?? '0')) ?></strong>
                    <?php else: ?>
                        <strong class="mt-1 block text-sm font-semibold text-muted-foreground">Unavailable</strong>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section data-inventory-dashboard-queue aria-labelledby="inventory-dashboard-queue-title">
        <div class="flex items-center justify-between gap-3">
            <h3 id="inventory-dashboard-queue-title" class="text-sm font-semibold">Operations queue</h3>
            <span class="text-xs text-muted-foreground"><?= count($inventoryDashboardQueue) ?> open</span>
        </div>
        <?php if ($inventoryDashboardQueue === []): ?>
            <p class="mt-3 border-y px-3 py-4 text-sm text-muted-foreground">No reorder, shortage, capacity, or putaway actions need attention.</p>
        <?php else: ?>
            <div class="mt-3 divide-y border-y">
                <?php foreach ($inventoryDashboardQueue as $queueItem): ?>
                    <a href="<?= $inventoryEscape((string) ($queueItem['href'] ?? '#')) ?>" class="flex min-w-0 items-start justify-between gap-3 px-3 py-3 hover:bg-muted/40">
                        <span class="min-w-0">
                            <strong class="block truncate text-sm font-medium"><?= $inventoryEscape((string) ($queueItem['label'] ?? 'Inventory action')) ?></strong>
                            <span class="mt-0.5 block text-xs text-muted-foreground"><?= $inventoryEscape((string) ($queueItem['detail'] ?? '')) ?></span>
                        </span>
                        <span class="shrink-0 text-xs font-medium"><?= $inventoryEscape((string) ($queueItem['status'] ?? 'OPEN')) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section data-inventory-dashboard-activity aria-labelledby="inventory-dashboard-activity-title">
        <h3 id="inventory-dashboard-activity-title" class="text-sm font-semibold">Recent activity</h3>
        <?php if ($inventoryDashboardActivity === []): ?>
            <p class="mt-3 border-y px-3 py-4 text-sm text-muted-foreground">No Inventory changes have been recorded for this company.</p>
        <?php else: ?>
            <div class="mt-3 divide-y border-y">
                <?php foreach ($inventoryDashboardActivity as $activity): ?>
                    <div class="flex min-w-0 items-start justify-between gap-3 px-3 py-3 text-sm">
                        <span class="min-w-0">
                            <strong class="block truncate font-medium"><?= $inventoryEscape((string) ($activity['action'] ?? 'UPDATE')) ?> &middot; <?= $inventoryEscape((string) ($activity['record_label'] ?? 'Inventory record')) ?></strong>
                            <span class="mt-0.5 block text-xs text-muted-foreground"><?= $inventoryEscape((string) ($activity['actor_label'] ?? 'Company administrator')) ?></span>
                        </span>
                        <time class="shrink-0 text-xs text-muted-foreground" datetime="<?= $inventoryEscape((string) ($activity['occurred_at'] ?? '')) ?>"><?= $inventoryEscape((string) ($activity['occurred_at'] ?? '')) ?></time>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section data-inventory-dashboard-shortcuts aria-labelledby="inventory-dashboard-shortcuts-title">
        <h3 id="inventory-dashboard-shortcuts-title" class="text-sm font-semibold">Shortcuts</h3>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($inventoryDashboard['shortcuts'] ?? [] as $shortcut): ?>
                <a href="<?= $inventoryEscape((string) ($shortcut['href'] ?? '#')) ?>" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><?= $inventoryEscape((string) ($shortcut['label'] ?? 'Open')) ?></a>
            <?php endforeach; ?>
        </div>
    </section>

    <section data-inventory-dashboard-directory aria-labelledby="inventory-dashboard-directory-title">
        <h3 id="inventory-dashboard-directory-title" class="text-sm font-semibold">Inventory directory</h3>
        <div class="mt-3 grid gap-5 sm:grid-cols-3">
            <?php foreach ($inventoryDashboard['directories'] ?? [] as $directory): ?>
                <div class="min-w-0">
                    <h4 class="text-xs font-semibold uppercase text-muted-foreground"><?= $inventoryEscape((string) ($directory['group'] ?? 'Directory')) ?></h4>
                    <div class="mt-2 grid divide-y border-y">
                        <?php foreach ($directory['items'] ?? [] as $item): ?>
                            <?php if (($item['availability'] ?? '') === 'AVAILABLE'): ?>
                                <a class="min-w-0 px-2 py-2 text-sm hover:bg-muted/40" href="<?= $inventoryEscape((string) ($item['href'] ?? '#')) ?>"><?= $inventoryEscape((string) ($item['label'] ?? 'Open')) ?></a>
                            <?php else: ?>
                                <span class="min-w-0 px-2 py-2 text-sm text-muted-foreground" aria-disabled="true"><?= $inventoryEscape((string) ($item['label'] ?? 'Unavailable')) ?> <span class="block text-xs">Unavailable</span></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
