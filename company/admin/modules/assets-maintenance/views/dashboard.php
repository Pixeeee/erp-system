<?php
declare(strict_types=1);

$assetsDashboardData = is_array($assetsData ?? null) ? $assetsData : [];
$assetsDashboardEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$assetsDashboardStats = is_array($assetsDashboardData['stats'] ?? null) ? $assetsDashboardData['stats'] : [];
?>
<div class="grid gap-6">
    <section aria-labelledby="assets-foundation-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 id="assets-foundation-title" class="text-sm font-semibold">Foundation readiness</h3>
                <p class="mt-1 text-sm text-muted-foreground">Company form controls are active; lifecycle packages remain isolated behind owner services.</p>
            </div>
            <span class="rounded-sm bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground">AM-01</span>
        </div>
        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="border-l-2 border-primary px-3"><dt class="text-xs text-muted-foreground">Forms</dt><dd class="mt-1 text-lg font-semibold"><?= (int) ($assetsDashboardStats['forms'] ?? 0) ?></dd></div>
            <div class="border-l-2 border-emerald-500 px-3"><dt class="text-xs text-muted-foreground">Published</dt><dd class="mt-1 text-lg font-semibold"><?= (int) ($assetsDashboardStats['published'] ?? 0) ?></dd></div>
            <div class="border-l-2 border-amber-500 px-3"><dt class="text-xs text-muted-foreground">Drafts</dt><dd class="mt-1 text-lg font-semibold"><?= (int) ($assetsDashboardStats['drafts'] ?? 0) ?></dd></div>
        </dl>
    </section>
    <section class="border-t pt-5" aria-labelledby="assets-ownership-title">
        <h3 id="assets-ownership-title" class="text-sm font-semibold">Owner boundaries</h3>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <?php foreach ([['Inventory', 'Items, warehouses, and stock movement'], ['Finance', 'Accounts, depreciation, and posting'], ['HR', 'Employee custody references']] as [$owner, $description]): ?>
                <div class="min-w-0 bg-muted/30 p-3"><p class="text-sm font-medium"><?= $assetsDashboardEscape($owner) ?></p><p class="mt-1 text-xs leading-5 text-muted-foreground"><?= $assetsDashboardEscape($description) ?></p></div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
