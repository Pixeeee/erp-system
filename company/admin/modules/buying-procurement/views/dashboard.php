<?php
$buyingCounts = is_array($activeModuleData['counts'] ?? null) ? $activeModuleData['counts'] : [];
?>
<div class="grid gap-5">
    <section data-buying-tour-target="setup" class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold">Procurement setup</h3>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">Confirm supplier governance and owner-service readiness before transactional workflows are enabled.</p>
            </div>
            <button type="button" data-buying-tour-start class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">
                <span class="material-symbols-rounded text-base" aria-hidden="true">tour</span>Show Tour
            </button>
        </div>
        <div data-buying-tour-target="checklist" class="divide-y border-y">
            <?php foreach ([
                ['Suppliers', (int) ($buyingCounts['suppliers'] ?? 0) > 0],
                ['Buying settings', is_array($activeModuleData['settings'] ?? null)],
                ['Inventory contract', (bool) ($activeModuleData['dependencies']['inventory']['available'] ?? false)],
                ['Finance contract', (bool) ($activeModuleData['dependencies']['finance']['available'] ?? false)],
            ] as [$label, $complete]): ?>
                <div class="flex items-center justify-between gap-3 py-3 text-sm">
                    <span class="flex items-center gap-2"><span class="material-symbols-rounded text-base <?= $complete ? 'text-emerald-600' : 'text-muted-foreground' ?>" aria-hidden="true"><?= $complete ? 'check_circle' : 'radio_button_unchecked' ?></span><?= bx_h($label) ?></span>
                    <span class="text-xs text-muted-foreground"><?= $complete ? 'Ready' : 'Attention required' ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section data-buying-tour-target="shortcuts" class="border-t pt-5">
        <h3 class="text-sm font-semibold">Shortcuts</h3>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <?php foreach ([
                ['suppliers', 'storefront', 'Suppliers'],
                ['request-for-quotation', 'request_quote', 'Requests for Quotation'],
                ['purchase-orders', 'shopping_cart', 'Purchase Orders'],
                ['form-builder', 'view_quilt', 'Form Builder'],
            ] as [$sectionKey, $icon, $label]): ?>
                <a class="flex min-h-11 items-center justify-between gap-3 rounded-md bg-muted/40 px-3 py-2 text-sm hover:bg-muted" href="./?view=buying-procurement&amp;section=<?= bx_h($sectionKey) ?>">
                    <span class="flex items-center gap-2"><span class="material-symbols-rounded text-base" aria-hidden="true"><?= bx_h($icon) ?></span><?= bx_h($label) ?></span>
                    <span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">arrow_forward</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>
