<?php
declare(strict_types=1);

$supportDashboard = is_array($supportDashboard ?? null) ? $supportDashboard : [];
$supportEscape = $supportEscape ?? static fn (mixed $value): string => bx_h((string) $value);
?>
<div data-support-dashboard class="grid gap-6">
    <section data-support-tour-target="summary" aria-labelledby="support-summary-heading">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 id="support-summary-heading" class="text-sm font-semibold">Service overview</h3>
                <p class="mt-1 text-sm text-muted-foreground">Operational totals activate only when their owning contracts are installed.</p>
            </div>
            <span class="text-xs text-muted-foreground"><?= (int) ($supportDashboard['foundation']['active_search_sources'] ?? 0) ?> active search source<?= (int) ($supportDashboard['foundation']['active_search_sources'] ?? 0) === 1 ? '' : 's' ?></span>
        </div>
        <dl data-support-dashboard-summary class="mt-4 grid gap-x-5 sm:grid-cols-2">
            <?php foreach (($supportDashboard['summary'] ?? []) as $metric): ?>
                <?php $available = (string) ($metric['availability'] ?? '') === 'AVAILABLE'; ?>
                <div class="min-w-0 border-b py-3">
                    <dt class="text-xs font-medium text-muted-foreground"><?= $supportEscape($metric['label'] ?? '') ?></dt>
                    <dd class="mt-1 min-h-7">
                        <span class="block text-lg font-semibold"><?= $available ? $supportEscape($metric['value'] ?? '') : 'Unavailable' ?></span>
                        <span class="mt-1 block text-[0.7rem] font-medium <?= $available ? 'text-emerald-600' : 'text-muted-foreground' ?>"><?= $available ? 'Live' : $supportEscape($metric['owner'] ?? 'Owner') ?></span>
                    </dd>
                    <?php if (!$available): ?><p class="mt-1 break-all text-xs text-muted-foreground"><?= $supportEscape($metric['dependency'] ?? '') ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

    <section data-support-dashboard-queue data-support-tour-target="queue" class="border-t pt-5" aria-labelledby="support-queue-heading">
        <div class="flex items-center justify-between gap-3">
            <h3 id="support-queue-heading" class="text-sm font-semibold">Action queue</h3>
            <span class="text-xs text-muted-foreground"><?= count($supportDashboard['queue'] ?? []) ?> items</span>
        </div>
        <div class="mt-3 divide-y">
            <?php foreach (($supportDashboard['queue'] ?? []) as $item): ?>
                <?php $itemAvailable = (string) ($item['availability'] ?? '') === 'AVAILABLE' && (string) ($item['href'] ?? '') !== ''; ?>
                <div class="flex min-h-11 items-center justify-between gap-3 py-2.5 text-sm">
                    <?php if ($itemAvailable): ?>
                        <a class="min-w-0 flex-1 font-medium hover:underline" href="<?= $supportEscape($item['href']) ?>"><?= $supportEscape($item['label'] ?? '') ?></a>
                    <?php else: ?>
                        <span class="min-w-0 flex-1 text-muted-foreground"><?= $supportEscape($item['label'] ?? '') ?></span>
                    <?php endif; ?>
                    <span class="shrink-0 text-xs <?= $itemAvailable ? 'text-emerald-600' : 'text-muted-foreground' ?>"><?= $supportEscape(($item['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY' ? 'Unavailable' : ($item['status'] ?? 'Open')) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="support-activity" data-support-dashboard-activity data-support-tour-target="activity" class="border-t pt-5" aria-labelledby="support-activity-heading">
        <h3 id="support-activity-heading" class="text-sm font-semibold">Recent activity</h3>
        <?php if (($supportDashboard['activity'] ?? []) === []): ?>
            <div class="mt-3 py-6 text-center text-sm text-muted-foreground">No Support settings or search-source activity has been recorded for this company.</div>
        <?php else: ?>
            <div class="mt-3 divide-y">
                <?php foreach (($supportDashboard['activity'] ?? []) as $activity): ?>
                    <div class="grid gap-1 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                        <div class="min-w-0"><p class="truncate text-sm font-medium"><?= $supportEscape($activity['record_label'] ?? '') ?></p><p class="text-xs text-muted-foreground"><?= $supportEscape($activity['actor_label'] ?? '') ?> · <?= $supportEscape($activity['action'] ?? '') ?></p></div>
                        <time class="text-xs text-muted-foreground" datetime="<?= $supportEscape($activity['occurred_at'] ?? '') ?>"><?= $supportEscape($activity['occurred_at'] ?? '') ?></time>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section data-support-dashboard-shortcuts data-support-tour-target="shortcuts" class="border-t pt-5" aria-labelledby="support-shortcuts-heading">
        <h3 id="support-shortcuts-heading" class="text-sm font-semibold">Shortcuts</h3>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <?php foreach (($supportDashboard['shortcuts'] ?? []) as $shortcut): ?>
                <?php if (!empty($shortcut['available'])): ?>
                    <a class="flex min-h-11 items-center gap-3 rounded-md bg-muted/40 px-3 py-2 text-sm font-medium hover:bg-muted" href="<?= $supportEscape($shortcut['href'] ?? '') ?>">
                        <span class="material-symbols-rounded text-base" aria-hidden="true"><?= $supportEscape($shortcut['icon'] ?? 'arrow_forward') ?></span><span class="min-w-0 flex-1"><?= $supportEscape($shortcut['label'] ?? '') ?></span><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">arrow_forward</span>
                    </a>
                <?php else: ?>
                    <span class="flex min-h-11 items-center gap-3 rounded-md bg-muted/20 px-3 py-2 text-sm text-muted-foreground" aria-disabled="true">
                        <span class="material-symbols-rounded text-base" aria-hidden="true"><?= $supportEscape($shortcut['icon'] ?? 'block') ?></span><span class="min-w-0 flex-1"><?= $supportEscape($shortcut['label'] ?? '') ?></span><span class="text-xs">Unavailable</span>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section data-support-dashboard-directories data-support-tour-target="directories" class="border-t pt-5" aria-labelledby="support-directories-heading">
        <h3 id="support-directories-heading" class="text-sm font-semibold">Reports and masters</h3>
        <div class="mt-3 grid gap-6 sm:grid-cols-3">
            <?php foreach (($supportDashboard['directories'] ?? []) as $directory): ?>
                <div class="min-w-0">
                    <h4 class="text-xs font-semibold uppercase text-muted-foreground"><?= $supportEscape($directory['group'] ?? '') ?></h4>
                    <div class="mt-2 grid gap-1">
                        <?php foreach (($directory['items'] ?? []) as $item): ?>
                            <?php if (!empty($item['available'])): ?>
                                <a class="py-1.5 text-sm hover:underline" href="<?= $supportEscape($item['href'] ?? '') ?>"><?= $supportEscape($item['label'] ?? '') ?></a>
                            <?php else: ?>
                                <span class="flex items-center justify-between gap-2 py-1.5 text-sm text-muted-foreground" aria-disabled="true"><span><?= $supportEscape($item['label'] ?? '') ?></span><span class="text-[0.65rem]">Unavailable</span></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
