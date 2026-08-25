<?php
declare(strict_types=1);

$manufacturingEscape = $manufacturingEscape ?? static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="grid gap-6">
    <div><h2 class="text-base font-semibold">Manufacturing reports</h2><p class="mt-1 text-sm text-muted-foreground">Report definitions are registered now; operational calculations arrive with their owning work packages.</p></div>
    <div class="grid gap-5 sm:grid-cols-2">
        <?php foreach ([
            'BOM' => ['BOM explorer', 'Operations time', 'Stock analysis', 'Variance'],
            'Planning' => ['Material requirements', 'Forecasting', 'Production plan summary', 'Production analytics'],
            'Execution' => ['Open work orders', 'Completed work orders', 'Job card summary', 'Consumed materials'],
            'Quality' => ['Inspection summary', 'Process loss', 'Cost of poor quality', 'Quality review'],
        ] as $group => $reports): ?>
            <section aria-labelledby="mfg-report-<?= $manufacturingEscape(strtolower($group)) ?>"><h3 id="mfg-report-<?= $manufacturingEscape(strtolower($group)) ?>" class="text-xs font-semibold uppercase text-muted-foreground"><?= $manufacturingEscape($group) ?></h3><div class="mt-2 divide-y"><?php foreach ($reports as $report): ?><div class="py-2 text-sm"><?= $manufacturingEscape($report) ?></div><?php endforeach; ?></div></section>
        <?php endforeach; ?>
    </div>
</div>
