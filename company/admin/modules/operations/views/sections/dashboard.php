<?php
declare(strict_types=1);

$operationsRows = is_array($activeModuleData['jobs'] ?? null) ? $activeModuleData['jobs'] : [];
?>
<div class="grid gap-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold"><?= bx_h((string) ($activeModuleMeta['label'] ?? 'Operations')) ?></h2>
            <p class="mt-1 text-sm text-muted-foreground">Company-scoped orchestration records and immutable execution history.</p>
        </div>
        <span class="inline-flex h-7 items-center rounded-md bg-muted px-2.5 text-xs font-medium"><?= count($operationsRows) ?> records</span>
    </div>
    <div class="overflow-x-auto rounded-md border">
        <table class="w-full min-w-[42rem] text-left text-sm">
            <thead class="bg-muted/50 text-xs text-muted-foreground"><tr><th class="px-3 py-2.5 font-medium">Reference</th><th class="px-3 py-2.5 font-medium">Type</th><th class="px-3 py-2.5 font-medium">Status</th><th class="px-3 py-2.5 font-medium">Updated</th></tr></thead>
            <tbody class="divide-y">
            <?php if ($operationsRows === []): ?>
                <tr><td colspan="4" class="px-3 py-10 text-center text-muted-foreground">No operational records in this company yet.</td></tr>
            <?php else: foreach ($operationsRows as $row): ?>
                <tr><td class="px-3 py-2.5 font-mono text-xs"><?= bx_h((string) ($row['job_key'] ?? '')) ?></td><td class="px-3 py-2.5"><?= bx_h((string) ($row['job_type'] ?? '')) ?></td><td class="px-3 py-2.5"><?= bx_h((string) ($row['status'] ?? '')) ?></td><td class="px-3 py-2.5 text-muted-foreground"><?= bx_h((string) ($row['updated_at'] ?? '')) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
