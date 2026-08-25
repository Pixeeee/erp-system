<?php
declare(strict_types=1);

$defaultsProjection = is_array($activeModuleData['company_defaults_projection'] ?? null) ? $activeModuleData['company_defaults_projection'] : ['contracts' => [], 'owner_actions' => []];
ob_start();
?>
<div class="grid gap-2"><h3 class="text-sm font-semibold">Owner actions</h3><?php foreach (($defaultsProjection['owner_actions'] ?? []) as $action): ?><a class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted" href="<?= bx_h((string) $action['href']) ?>"><span class="material-symbols-rounded text-base" aria-hidden="true">open_in_new</span><?= bx_h((string) $action['label']) ?></a><?php endforeach; ?></div>
<?php
$operationsSectionTools = (string) ob_get_clean();
?>
<div class="grid gap-5">
    <div><h2 class="text-base font-semibold">Company and defaults</h2><p class="mt-1 text-sm text-muted-foreground">Company, branch, currency, unit, finance, warehouse, and asset settings remain read-only projections from their owners.</p></div>
    <div class="grid gap-3 sm:grid-cols-2"><?php foreach (($defaultsProjection['contracts'] ?? []) as $state): ?><section class="rounded-md border p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="break-all text-sm font-semibold"><?= bx_h((string) $state['contract_id']) ?></h3><p class="mt-1 text-xs text-muted-foreground"><?= count($state['records'] ?? []) ?> records</p></div><span class="shrink-0 text-xs font-medium"><?= bx_h((string) $state['status']) ?></span></div><?php if (($state['status'] ?? '') === 'AVAILABLE'): ?><dl class="mt-3 grid gap-2"><?php foreach (($state['records'] ?? []) as $record): ?><div class="rounded-md bg-muted/50 px-3 py-2 text-xs"><?php foreach ($record as $key => $value): if (is_scalar($value) || $value === null): ?><div class="grid grid-cols-[minmax(7rem,1fr)_minmax(0,2fr)] gap-2"><dt class="font-medium"><?= bx_h(ucwords(str_replace('_', ' ', (string) $key))) ?></dt><dd class="break-all text-muted-foreground"><?= bx_h((string) $value) ?></dd></div><?php endif; endforeach; ?></div><?php endforeach; ?></dl><?php else: ?><p role="status" class="mt-3 text-sm text-muted-foreground"><?= bx_h((string) $state['message']) ?> This source is non-blocking.</p><?php endif; ?></section><?php endforeach; ?></div>
</div>
