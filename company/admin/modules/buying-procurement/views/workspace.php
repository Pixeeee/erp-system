<?php
$buyingSections = yovel_admin_buying_procurement_sections();
$buyingSection = (string) ($activeModuleSection ?? yovel_admin_buying_procurement_section());
if (!array_key_exists($buyingSection, $buyingSections)) {
    $buyingSection = 'suppliers';
}
$buyingMeta = $buyingSections[$buyingSection];
$buyingData = is_array($activeModuleData ?? null) ? $activeModuleData : [];
$buyingCounts = is_array($buyingData['counts'] ?? null) ? $buyingData['counts'] : [];
$buyingDependencies = is_array($buyingData['dependencies'] ?? null) ? $buyingData['dependencies'] : [];
$buyingFormState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
$buyingFormInput = is_array($buyingFormState['input'] ?? null) ? $buyingFormState['input'] : [];
$buyingFormError = trim((string) ($buyingFormState['error'] ?? ''));
$buyingReopenTargetModal = $buyingFormError !== ''
    && (string) ($buyingFormState['action'] ?? '') === 'review_buying_form_target';
$buyingAdapter = is_array($buyingData['formAdapter'] ?? null)
    ? $buyingData['formAdapter']
    : yovel_admin_buying_procurement_form_adapter();
$buyingSelectedTarget = trim((string) ($buyingFormInput['form_target'] ?? $buyingData['selectedFormTarget'] ?? 'supplier'));
if (!in_array($buyingSelectedTarget, $buyingAdapter['target_record_types'], true)) {
    $buyingSelectedTarget = 'supplier';
}
$buyingCountKey = match ($buyingSection) {
    'suppliers' => 'suppliers',
    'request-for-quotation' => 'requests_for_quotation',
    'supplier-quotations' => 'supplier_quotations',
    'purchase-orders' => 'purchase_orders',
    'supplier-scorecards' => 'supplier_scorecards',
    default => '',
};
?>
<style>
    .yovel-buying-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; min-height: 0; }
    @media (min-width: 1280px) {
        .yovel-buying-layout { grid-template-columns: minmax(0, 12fr) minmax(0, 8fr); }
    }
    .yovel-buying-tour-ring { outline: 2px solid hsl(var(--ring)); outline-offset: 4px; }
</style>
<div data-buying-workspace class="grid min-h-0 gap-4">
    <div class="yovel-buying-layout">
        <section data-buying-main-panel class="flex min-h-[32rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="shrink-0 border-b bg-card px-5 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold tracking-normal"><?= bx_h((string) $buyingMeta['label']) ?></h2>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) $buyingMeta['description']) ?></p>
                    </div>
                    <?php if ($buyingCountKey !== ''): ?>
                        <span class="inline-flex rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-secondary-foreground"><?= (int) ($buyingCounts[$buyingCountKey] ?? 0) ?> records</span>
                    <?php endif; ?>
                </div>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <?php if ($buyingSection === 'dashboard'): ?>
                    <?php require __DIR__ . '/dashboard.php'; ?>
                <?php elseif ($buyingSection === 'form-builder'): ?>
                    <?php require __DIR__ . '/form-builder.php'; ?>
                <?php elseif ($buyingSection === 'suppliers'): ?>
                    <?php $buyingSuppliers = is_array($buyingData['suppliers'] ?? null) ? $buyingData['suppliers'] : []; ?>
                    <?php if ($buyingSuppliers === []): ?>
                        <div class="grid min-h-64 place-items-center text-center">
                            <div class="max-w-md">
                                <span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true">storefront</span>
                                <h3 class="mt-3 text-sm font-semibold">No supplier records exist for this company.</h3>
                                <p class="mt-1 text-sm leading-6 text-muted-foreground">The company-scoped supplier foundation is ready. Supplier creation becomes available with the approved supplier workflow slice.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[44rem] text-sm">
                                <thead class="border-b text-left text-xs text-muted-foreground"><tr><th class="px-3 py-3">Supplier</th><th class="px-3 py-3">Type</th><th class="px-3 py-3">Currency</th><th class="px-3 py-3">Status</th></tr></thead>
                                <tbody class="divide-y">
                                    <?php foreach ($buyingSuppliers as $supplier): ?>
                                        <tr><td class="px-3 py-3"><span class="font-medium"><?= bx_h((string) $supplier['supplier_name']) ?></span><span class="block text-xs text-muted-foreground"><?= bx_h((string) $supplier['supplier_code']) ?></span></td><td class="px-3 py-3"><?= bx_h((string) $supplier['supplier_type']) ?></td><td class="px-3 py-3"><?= bx_h((string) ($supplier['default_currency'] ?: 'Not set')) ?></td><td class="px-3 py-3"><?= bx_h((string) $supplier['supplier_status']) ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php elseif (in_array($buyingSection, ['material-requests', 'purchase-receipts', 'purchase-analytics', 'reports'], true)): ?>
                    <?php $ownerDependency = $buyingSection === 'material-requests' || $buyingSection === 'purchase-receipts' ? 'Inventory' : 'Inventory and Finance'; ?>
                    <div class="grid min-h-64 place-items-center text-center">
                        <div class="max-w-lg">
                            <span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true">sync_problem</span>
                            <h3 class="mt-3 text-sm font-semibold"><?= bx_h($ownerDependency) ?> owner service required</h3>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground">This page will read authoritative records through the owning module service. No direct Inventory or Finance table fallback is used.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid min-h-64 place-items-center text-center">
                        <div class="max-w-md">
                            <span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true"><?= bx_h((string) $buyingMeta['icon']) ?></span>
                            <h3 class="mt-3 text-sm font-semibold">No <?= bx_h(strtolower((string) $buyingMeta['label'])) ?> records exist for this company.</h3>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground">The company-scoped schema and form contract are active. Transactional controls remain unavailable until their approved workflow slice is installed.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <aside data-buying-tools-panel class="flex min-h-[32rem] min-w-0 flex-col overflow-hidden rounded-lg border bg-card">
            <header class="shrink-0 border-b bg-card px-5 py-4">
                <h2 class="text-base font-semibold tracking-normal">Actions and Tools</h2>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">Navigate procurement features and review service readiness.</p>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                <div class="grid gap-5">
                    <nav aria-label="Buying and Procurement sections" class="grid gap-1">
                        <?php foreach ($buyingSections as $sectionKey => $sectionMeta): ?>
                            <a class="flex min-h-9 items-center gap-2 rounded-md px-3 text-sm <?= $buyingSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'hover:bg-muted' ?>" href="./?view=buying-procurement&amp;section=<?= bx_h((string) $sectionKey) ?>">
                                <span class="material-symbols-rounded text-base" aria-hidden="true"><?= bx_h((string) $sectionMeta['icon']) ?></span><span class="min-w-0 flex-1 truncate"><?= bx_h((string) $sectionMeta['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <section class="border-t pt-4">
                        <h3 class="text-sm font-semibold">Dependency status</h3>
                        <div class="mt-2 divide-y">
                            <?php foreach (['shared', 'inventory', 'finance'] as $dependencyKey): ?>
                                <?php $dependency = is_array($buyingDependencies[$dependencyKey] ?? null) ? $buyingDependencies[$dependencyKey] : ['available' => false, 'label' => ucfirst($dependencyKey) . ' contract']; ?>
                                <div class="flex items-center justify-between gap-3 py-2.5 text-sm">
                                    <span><?= bx_h((string) $dependency['label']) ?></span>
                                    <span class="text-xs <?= !empty($dependency['available']) ? 'text-emerald-600' : 'text-muted-foreground' ?>"><?= !empty($dependency['available']) ? 'Available' : 'Unavailable' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($buyingDependencies['inventory']['available'])): ?><p class="mt-2 text-xs text-muted-foreground">Inventory contract unavailable</p><?php endif; ?>
                        <?php if (empty($buyingDependencies['finance']['available'])): ?><p class="mt-1 text-xs text-muted-foreground">Finance contract unavailable</p><?php endif; ?>
                    </section>
                    <section class="border-t pt-4">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-record-modal-open="buying-form-target-modal" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">
                                <span class="material-symbols-rounded text-base" aria-hidden="true">tune</span>Configure form
                            </button>
                            <form method="post" data-confirm-submit data-confirm-message="Refresh Buying / Procurement dependency state?">
                                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                <input type="hidden" name="module_view" value="buying-procurement">
                                <input type="hidden" name="action" value="refresh_buying_workspace">
                                <input type="hidden" name="section" value="<?= bx_h($buyingSection) ?>">
                                <button type="submit" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">refresh</span>Check dependencies</button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </aside>
    </div>
</div>

<div id="buying-form-target-modal" data-record-modal <?= $buyingReopenTargetModal ? 'data-record-modal-open-on-load' : '' ?> hidden class="fixed inset-0 z-[70] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="buying-form-target-modal-title" aria-describedby="buying-form-target-modal-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-2xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg" role="document">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-card px-6 py-4">
            <div class="min-w-0"><h2 id="buying-form-target-modal-title" class="text-base font-semibold">Configure procurement form</h2><p id="buying-form-target-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Choose a registered record type to inspect in the module-local Form Builder.</p></div>
            <button type="button" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background" aria-label="Close form target dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button>
        </header>
        <form method="post" data-confirm-submit data-confirm-message="Load this Buying Form Builder target?" class="contents">
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
            <input type="hidden" name="module_view" value="buying-procurement">
            <input type="hidden" name="action" value="review_buying_form_target">
            <input type="hidden" name="section" value="<?= bx_h($buyingSection) ?>">
            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                <?php if ($buyingFormError !== ''): ?><div class="mb-4 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert"><?= bx_h($buyingFormError) ?></div><?php endif; ?>
                <label class="grid gap-1.5 text-sm"><span class="font-medium">Record target</span><select name="form_target" class="h-10 rounded-md border bg-background px-3" required><?php foreach ($buyingAdapter['target_record_types'] as $sectionKey => $recordType): ?><option value="<?= bx_h((string) $recordType) ?>" <?= $buyingSelectedTarget === $recordType ? 'selected' : '' ?>><?= bx_h((string) ($buyingSections[$sectionKey]['label'] ?? $recordType)) ?></option><?php endforeach; ?></select></label>
            </div>
            <footer class="m-0 flex w-full shrink-0 items-center justify-between gap-3 rounded-none border-t bg-card px-6 py-4"><span class="text-xs text-muted-foreground">Protected keys remain immutable.</span><div class="flex gap-2"><button type="button" data-record-modal-close class="h-9 rounded-md border bg-background px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Review target</button></div></footer>
        </form>
    </section>
</div>

<?php if ($buyingSection === 'dashboard'): ?>
<div data-buying-tour hidden class="fixed inset-0 z-[60] bg-background/50" aria-hidden="true">
    <section class="fixed bottom-4 right-4 w-[min(24rem,calc(100vw-2rem))] rounded-lg border bg-popover p-4 text-popover-foreground shadow-lg" role="dialog" aria-modal="true" aria-labelledby="buying-tour-title">
        <p class="text-xs font-medium text-muted-foreground">Procurement workspace tour</p><h2 id="buying-tour-title" data-buying-tour-title class="mt-1 text-base font-semibold"></h2><p data-buying-tour-body class="mt-2 text-sm leading-6 text-muted-foreground"></p>
        <div class="mt-4 flex items-center justify-between gap-3"><button type="button" data-buying-tour-skip class="h-9 rounded-md border px-3 text-sm">Skip</button><div class="flex gap-2"><button type="button" data-buying-tour-back class="h-9 rounded-md border px-3 text-sm">Back</button><button type="button" data-buying-tour-next class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Next</button></div></div>
    </section>
</div>
<script>
(() => {
    const root = document.querySelector('[data-buying-workspace]');
    const overlay = document.querySelector('[data-buying-tour]');
    const trigger = document.querySelector('[data-buying-tour-start]');
    if (!root || !overlay || !trigger) return;
    const steps = [
        ['setup', 'Procurement readiness', 'Start with company-scoped suppliers and verify the owner-service contracts.'],
        ['checklist', 'Setup checklist', 'Each row reflects current server-backed setup and dependency state.'],
        ['shortcuts', 'Operational shortcuts', 'Move directly to sourcing, orders, suppliers, or configurable forms.'],
    ];
    const title = overlay.querySelector('[data-buying-tour-title]');
    const body = overlay.querySelector('[data-buying-tour-body]');
    const back = overlay.querySelector('[data-buying-tour-back]');
    const next = overlay.querySelector('[data-buying-tour-next]');
    const skip = overlay.querySelector('[data-buying-tour-skip]');
    const storageKey = <?= json_encode('builderx:buying-procurement:' . (string) ($buyingData['company_key_hash'] ?? 'company') . ':tour', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let index = 0;
    let target = null;
    const close = () => { overlay.hidden = true; overlay.setAttribute('aria-hidden', 'true'); target?.classList.remove('yovel-buying-tour-ring'); target = null; window.localStorage.setItem(storageKey, 'dismissed'); trigger.focus(); };
    const render = () => { target?.classList.remove('yovel-buying-tour-ring'); const step = steps[index]; target = root.querySelector(`[data-buying-tour-target="${step[0]}"]`); target?.classList.add('yovel-buying-tour-ring'); title.textContent = step[1]; body.textContent = step[2]; back.disabled = index === 0; next.textContent = index === steps.length - 1 ? 'Finish' : 'Next'; };
    trigger.addEventListener('click', () => { index = 0; overlay.hidden = false; overlay.setAttribute('aria-hidden', 'false'); render(); next.focus(); });
    back.addEventListener('click', () => { if (index > 0) index -= 1; render(); });
    next.addEventListener('click', () => { if (index >= steps.length - 1) close(); else { index += 1; render(); } });
    skip.addEventListener('click', close);
    overlay.addEventListener('keydown', (event) => { if (event.key === 'Escape') { event.preventDefault(); close(); } });
})();
</script>
<?php endif; ?>
