<?php
declare(strict_types=1);

$manufacturingEscape = $manufacturingEscape ?? static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$dashboard = is_array($manufacturingData['dashboard'] ?? null) ? $manufacturingData['dashboard'] : [];
$region = (string) ($manufacturingDashboardRegion ?? 'main');
$availabilityLabel = static fn (string $value): string => match ($value) {
    'AVAILABLE' => 'Available',
    'UNAVAILABLE_DEPENDENCY' => 'Dependency unavailable',
    'NOT_IMPLEMENTED' => 'Not implemented',
    default => 'Error',
};

if ($region === 'tools'):
    $foundation = is_array($dashboard['foundation'] ?? null) ? $dashboard['foundation'] : [];
?>
<div class="grid gap-6">
    <section data-manufacturing-dashboard-setup data-manufacturing-tour-target="setup" aria-labelledby="manufacturing-dashboard-setup-title">
        <div class="flex items-start justify-between gap-3"><div><h3 id="manufacturing-dashboard-setup-title" class="text-sm font-semibold">Setup checklist</h3><p class="mt-1 text-xs leading-5 text-muted-foreground"><?= (int) ($foundation['setup_complete'] ?? 0) ?> of <?= (int) ($foundation['setup_total'] ?? 0) ?> ready</p></div><button type="button" data-manufacturing-tour-start class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">tour</span>Show Tour</button></div>
        <div class="mt-3 divide-y border-y"><?php foreach ($dashboard['setup'] ?? [] as $step): ?><a href="<?= $manufacturingEscape((string) $step['href']) ?>" class="flex items-center gap-2 py-3 text-sm"><span class="material-symbols-rounded text-base <?= !empty($step['complete']) ? 'text-emerald-600' : 'text-muted-foreground' ?>" aria-hidden="true"><?= !empty($step['complete']) ? 'check_circle' : 'radio_button_unchecked' ?></span><span class="min-w-0 flex-1"><?= $manufacturingEscape((string) $step['label']) ?></span><span class="text-xs text-muted-foreground"><?= !empty($step['complete']) ? 'Ready' : 'Pending' ?></span></a><?php endforeach; ?></div>
    </section>

    <section data-manufacturing-dashboard-alerts data-manufacturing-tour-target="alerts" class="border-t pt-5" aria-labelledby="manufacturing-dashboard-alerts-title">
        <h3 id="manufacturing-dashboard-alerts-title" class="text-sm font-semibold">Alerts</h3>
        <div class="mt-3 grid gap-3"><?php foreach ($dashboard['alerts'] ?? [] as $alert): ?><a href="<?= $manufacturingEscape((string) $alert['href']) ?>" class="border-l-2 <?= ($alert['severity'] ?? '') === 'ERROR' ? 'border-destructive' : 'border-amber-500' ?> px-3 py-1"><span class="block text-xs font-medium"><?= $manufacturingEscape((string) $alert['severity']) ?></span><span class="mt-1 block text-sm leading-5 text-muted-foreground"><?= $manufacturingEscape((string) $alert['label']) ?></span></a><?php endforeach; ?></div>
    </section>

    <section data-manufacturing-dashboard-dependencies data-manufacturing-tour-target="dependencies" class="border-t pt-5" aria-labelledby="manufacturing-dashboard-dependencies-title">
        <div class="flex items-center justify-between gap-3"><h3 id="manufacturing-dashboard-dependencies-title" class="text-sm font-semibold">Dependency health</h3><span class="text-xs text-muted-foreground"><?= (int) ($foundation['dependency_available'] ?? 0) ?>/<?= (int) ($foundation['dependency_total'] ?? 0) ?></span></div>
        <div class="mt-3 grid gap-2"><?php foreach (array_filter($dashboard['dependencies'] ?? [], static fn (array $dependency): bool => in_array((string) ($dependency['key'] ?? ''), ['item_lookup', 'item_uom_resolve', 'warehouses', 'stock_snapshot', 'item_valuation', 'supplier_lookup', 'material_request', 'account_validation', 'cost_preview'], true)) as $dependency): ?><div class="flex items-start gap-2 text-sm"><span class="mt-0.5 size-2 shrink-0 rounded-full <?= ($dependency['status'] ?? '') === 'AVAILABLE' ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span><span class="min-w-0 flex-1"><span class="block font-medium"><?= $manufacturingEscape(ucwords(str_replace('_', ' ', (string) $dependency['key']))) ?></span><span class="block truncate text-xs text-muted-foreground" title="<?= $manufacturingEscape((string) $dependency['signature']) ?>"><?= $manufacturingEscape((string) $dependency['owner']) ?></span></span><span class="text-[11px] text-muted-foreground"><?= ($dependency['status'] ?? '') === 'AVAILABLE' ? 'Ready' : 'Blocked' ?></span></div><?php endforeach; ?></div>
    </section>

    <section data-manufacturing-tour-target="form-builder" class="border-t pt-5" aria-labelledby="manufacturing-dashboard-tools-title"><h3 id="manufacturing-dashboard-tools-title" class="text-sm font-semibold">Tools</h3><div class="mt-3 grid gap-2"><a href="?view=manufacturing&amp;section=settings" class="inline-flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">settings</span>Manufacturing settings</a><a href="?view=manufacturing&amp;section=form-builder" class="inline-flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">dynamic_form</span>Form Builder</a></div></section>
</div>
<?php return; endif; ?>

<?php if ($region === 'tour'): $tourStorageKey = 'builderx:manufacturing:' . (string) ($dashboard['company_key_hash'] ?? 'company') . ':dashboard-tour'; ?>
<style>.manufacturing-tour-focus{outline:2px solid hsl(var(--ring));outline-offset:4px}</style>
<div data-manufacturing-tour hidden aria-hidden="true" class="fixed inset-0 z-[80] bg-background/70 p-4" role="dialog" aria-modal="true" aria-labelledby="manufacturing-tour-title">
    <section class="absolute bottom-4 right-4 w-[min(25rem,calc(100vw-2rem))] rounded-lg border bg-popover p-5 text-popover-foreground shadow-lg" role="document">
        <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-medium text-muted-foreground">Manufacturing dashboard tour</p><h2 id="manufacturing-tour-title" data-manufacturing-tour-title class="mt-1 text-base font-semibold"></h2></div><button type="button" data-manufacturing-tour-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border" aria-label="Close tour"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></div>
        <p data-manufacturing-tour-body class="mt-3 text-sm leading-6 text-muted-foreground"></p>
        <div class="mt-5 flex items-center justify-between gap-3"><span data-manufacturing-tour-progress class="text-xs text-muted-foreground"></span><div class="flex gap-2"><button type="button" data-manufacturing-tour-back class="h-9 rounded-md border px-3 text-sm">Back</button><button type="button" data-manufacturing-tour-next class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Next</button></div></div>
    </section>
</div>
<script>
(() => {
    const root = document.querySelector('[data-manufacturing-workspace]');
    const overlay = document.querySelector('[data-manufacturing-tour]');
    const trigger = document.querySelector('[data-manufacturing-tour-start]');
    if (!root || !overlay || !trigger) return;
    const steps = [
        ['summary', 'Production state', 'Production totals stay unavailable until their Manufacturing packages and owner contracts exist.'],
        ['queue', 'Action queue', 'Setup work and blocked production packages are kept separate from real records.'],
        ['activity', 'Recent activity', 'Manufacturing-owned audit events show verified company changes.'],
        ['setup', 'Setup progress', 'Settings, forms, and owner-service readiness determine the next valid step.'],
        ['alerts', 'Alerts', 'Missing valuation, costing, and BOM capabilities remain explicit blockers.'],
        ['dependencies', 'Dependency health', 'Only allow-listed Inventory, Buying, and Finance callables can provide owner data.'],
        ['form-builder', 'Tools', 'Settings and the universal Form Builder remain available from the dashboard.'],
    ];
    const title = overlay.querySelector('[data-manufacturing-tour-title]');
    const body = overlay.querySelector('[data-manufacturing-tour-body]');
    const progress = overlay.querySelector('[data-manufacturing-tour-progress]');
    const back = overlay.querySelector('[data-manufacturing-tour-back]');
    const next = overlay.querySelector('[data-manufacturing-tour-next]');
    const closeButton = overlay.querySelector('[data-manufacturing-tour-close]');
    const storageKey = <?= json_encode($tourStorageKey, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let index = 0;
    let target = null;
    const remember = () => { try { window.localStorage.setItem(storageKey, 'complete'); } catch (_) {} };
    const render = () => {
        target?.classList.remove('manufacturing-tour-focus');
        const step = steps[index];
        target = root.querySelector(`[data-manufacturing-tour-target="${step[0]}"]`);
        target?.classList.add('manufacturing-tour-focus');
        title.textContent = step[1]; body.textContent = step[2]; progress.textContent = `${index + 1} of ${steps.length}`;
        back.disabled = index === 0; next.textContent = index === steps.length - 1 ? 'Finish' : 'Next';
    };
    const close = () => { target?.classList.remove('manufacturing-tour-focus'); target = null; overlay.hidden = true; overlay.setAttribute('aria-hidden', 'true'); remember(); trigger.focus(); };
    const open = () => { index = 0; overlay.hidden = false; overlay.setAttribute('aria-hidden', 'false'); render(); next.focus(); };
    trigger.addEventListener('click', open);
    closeButton.addEventListener('click', close);
    back.addEventListener('click', () => { if (index > 0) { index--; render(); } });
    next.addEventListener('click', () => { if (index === steps.length - 1) { close(); return; } index++; render(); });
    overlay.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (event.key !== 'Tab') return;
        const focusable = Array.from(overlay.querySelectorAll('button:not([disabled]),a[href],[tabindex]:not([tabindex="-1"])')).filter((element) => element.getClientRects().length > 0);
        if (focusable.length === 0) return;
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    try { if (window.localStorage.getItem(storageKey) !== 'complete') window.setTimeout(open, 250); } catch (_) {}
})();
</script>
<?php return; endif; ?>

<div class="grid gap-7">
    <section data-manufacturing-dashboard-summary data-manufacturing-tour-target="summary" aria-labelledby="manufacturing-dashboard-summary-title">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 id="manufacturing-dashboard-summary-title" class="text-base font-semibold">Production state</h2><p class="mt-1 text-sm text-muted-foreground">Live availability without synthetic production totals.</p></div><span class="text-xs text-muted-foreground"><?= (int) ($dashboard['foundation']['published_form_count'] ?? 0) ?> published forms</span></div>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><?php foreach ($dashboard['summary'] ?? [] as $item): ?><div class="border-l-2 <?= ($item['availability'] ?? '') === 'NOT_IMPLEMENTED' ? 'border-muted-foreground/40' : 'border-amber-500' ?> px-3"><dt class="text-xs text-muted-foreground"><?= $manufacturingEscape((string) $item['label']) ?></dt><dd class="mt-1 text-lg font-semibold"><?= $manufacturingEscape((string) $item['value']) ?></dd><dd class="mt-1 text-[11px] text-muted-foreground"><?= $manufacturingEscape($availabilityLabel((string) $item['availability'])) ?></dd></div><?php endforeach; ?></dl>
    </section>

    <section data-manufacturing-dashboard-queue data-manufacturing-tour-target="queue" class="border-t pt-5" aria-labelledby="manufacturing-dashboard-queue-title"><div class="flex items-center justify-between gap-3"><h2 id="manufacturing-dashboard-queue-title" class="text-base font-semibold">Action queue</h2><span class="text-xs text-muted-foreground"><?= count($dashboard['queue'] ?? []) ?> items</span></div><div class="mt-3 divide-y border-y"><?php foreach ($dashboard['queue'] ?? [] as $item): ?><a href="<?= $manufacturingEscape((string) $item['href']) ?>" class="flex items-center gap-3 py-3 text-sm"><span class="material-symbols-rounded text-base <?= ($item['status'] ?? '') === 'OPEN' ? 'text-primary' : 'text-amber-600' ?>" aria-hidden="true"><?= ($item['status'] ?? '') === 'OPEN' ? 'pending_actions' : 'block' ?></span><span class="min-w-0 flex-1 font-medium"><?= $manufacturingEscape((string) $item['label']) ?></span><span class="text-xs text-muted-foreground"><?= $manufacturingEscape((string) $item['status']) ?></span></a><?php endforeach; ?></div></section>

    <section data-manufacturing-dashboard-activity data-manufacturing-tour-target="activity" class="border-t pt-5" aria-labelledby="manufacturing-dashboard-activity-title"><div class="flex items-center justify-between gap-3"><h2 id="manufacturing-dashboard-activity-title" class="text-base font-semibold">Recent activity</h2><span class="text-xs text-muted-foreground"><?= (int) ($dashboard['foundation']['audit_count'] ?? 0) ?> total events</span></div><div class="mt-3 divide-y border-y"><?php foreach ($dashboard['activity'] ?? [] as $activity): ?><div class="grid gap-1 py-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto]"><div class="min-w-0"><p class="font-medium"><?= $manufacturingEscape((string) $activity['action']) ?> · <?= $manufacturingEscape((string) $activity['record_label']) ?></p><p class="mt-1 truncate text-xs text-muted-foreground"><?= $manufacturingEscape((string) $activity['business_key']) ?> · <?= $manufacturingEscape((string) $activity['actor_label']) ?></p></div><time class="text-xs text-muted-foreground"><?= $manufacturingEscape((string) $activity['occurred_at']) ?></time></div><?php endforeach; ?><?php if (($dashboard['activity'] ?? []) === []): ?><p class="py-6 text-sm text-muted-foreground">No Manufacturing changes have been recorded for this company.</p><?php endif; ?></div></section>

    <section data-manufacturing-dashboard-shortcuts data-manufacturing-tour-target="shortcuts" class="border-t pt-5" aria-labelledby="manufacturing-dashboard-shortcuts-title"><h2 id="manufacturing-dashboard-shortcuts-title" class="text-base font-semibold">Shortcuts</h2><div class="mt-3 grid gap-2 sm:grid-cols-2"><?php foreach ($dashboard['shortcuts'] ?? [] as $shortcut): ?><?php if (!empty($shortcut['available'])): ?><a href="<?= $manufacturingEscape((string) $shortcut['href']) ?>" class="flex min-h-11 items-center gap-3 border-b px-1 py-2 text-sm font-medium"><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true"><?= $manufacturingEscape((string) $shortcut['icon']) ?></span><span class="min-w-0 flex-1"><?= $manufacturingEscape((string) $shortcut['label']) ?></span><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">arrow_forward</span></a><?php else: ?><span aria-disabled="true" class="flex min-h-11 items-center gap-3 border-b px-1 py-2 text-sm text-muted-foreground"><span class="material-symbols-rounded text-base" aria-hidden="true"><?= $manufacturingEscape((string) $shortcut['icon']) ?></span><span class="min-w-0 flex-1"><?= $manufacturingEscape((string) $shortcut['label']) ?></span><span class="text-[11px]">Unavailable</span></span><?php endif; ?><?php endforeach; ?></div></section>

    <section data-manufacturing-dashboard-directories data-manufacturing-tour-target="directories" class="border-t pt-5" aria-labelledby="manufacturing-dashboard-directories-title"><h2 id="manufacturing-dashboard-directories-title" class="text-base font-semibold">Reports and masters</h2><div class="mt-3 grid gap-6 sm:grid-cols-3"><?php foreach ($dashboard['directories'] ?? [] as $directory): ?><div><h3 class="text-xs font-semibold uppercase text-muted-foreground"><?= $manufacturingEscape((string) $directory['group']) ?></h3><div class="mt-2 grid gap-1"><?php foreach ($directory['items'] ?? [] as $item): ?><?php if (!empty($item['available'])): ?><a href="<?= $manufacturingEscape((string) $item['href']) ?>" class="py-1.5 text-sm hover:underline"><?= $manufacturingEscape((string) $item['label']) ?></a><?php else: ?><span aria-disabled="true" class="flex items-center justify-between gap-2 py-1.5 text-sm text-muted-foreground"><span><?= $manufacturingEscape((string) $item['label']) ?></span><span class="text-[11px]">Unavailable</span></span><?php endif; ?><?php endforeach; ?></div></div><?php endforeach; ?></div></section>
</div>
