<?php
declare(strict_types=1);

$complianceMetrics = is_array($complianceData['metrics'] ?? null) ? $complianceData['metrics'] : [];
$complianceReadiness = [
    ['label' => 'Company scope', 'status' => 'Ready', 'icon' => 'verified_user'],
    ['label' => 'Lifecycle contracts', 'status' => 'Ready', 'icon' => 'account_tree'],
    ['label' => 'Evidence schema', 'status' => 'Ready', 'icon' => 'fingerprint'],
    ['label' => 'Finance snapshot services', 'status' => 'Dependency', 'icon' => 'sync_alt'],
];
?>
<header class="border-b px-5 py-4">
    <h2 class="text-base font-semibold">Philippine compliance readiness</h2>
    <p class="mt-1 text-sm leading-6 text-muted-foreground">Foundation controls for effective rules, immutable evidence, approvals, and regulatory outputs.</p>
</header>
<div class="grid gap-0 sm:grid-cols-2">
    <?php foreach ($complianceReadiness as $item): ?>
        <div class="flex items-center justify-between gap-3 border-b px-5 py-4 sm:odd:border-r">
            <span class="inline-flex min-w-0 items-center gap-3">
                <span class="material-symbols-rounded text-lg text-muted-foreground" aria-hidden="true"><?= bx_h($item['icon']) ?></span>
                <span class="truncate text-sm font-medium"><?= bx_h($item['label']) ?></span>
            </span>
            <span class="text-xs font-medium <?= $item['status'] === 'Ready' ? 'text-emerald-700 dark:text-emerald-300' : 'text-muted-foreground' ?>"><?= bx_h($item['status']) ?></span>
        </div>
    <?php endforeach; ?>
</div>
<section class="px-5 py-5" aria-labelledby="compliance-foundation-counts">
    <h3 id="compliance-foundation-counts" class="text-sm font-semibold">Governed records</h3>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach (['rules' => 'Rules', 'templates' => 'Templates', 'evidence' => 'Evidence', 'exports' => 'Exports'] as $key => $label): ?>
            <div class="rounded-md bg-muted/40 p-3">
                <p class="text-xs font-medium text-muted-foreground"><?= bx_h($label) ?></p>
                <p class="mt-1 text-xl font-semibold"><?= (int) ($complianceMetrics[$key] ?? 0) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>
