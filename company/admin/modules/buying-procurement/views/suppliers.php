<?php
$buyingSuppliers = is_array($buyingData['suppliers'] ?? null) ? $buyingData['suppliers'] : [];
$buyingSupplierInput = $buyingReopenSupplierModal ? $buyingFormInput : [];
$buyingSupplierCustomerText = (string) ($buyingSupplierInput['customer_numbers_text'] ?? '');
$buyingSupplierAllowedText = (string) ($buyingSupplierInput['allowed_company_keys_text'] ?? '');
$buyingSupplierChecked = static fn (string $key): string => yovel_admin_buying_bool($buyingSupplierInput, $key) === 1 ? 'checked' : '';
$buyingLifecycleInput = $buyingReopenLifecycleModal ? $buyingFormInput : [];
$buyingLifecycleMap = [
    'hold_buying_supplier' => 'HOLD',
    'release_buying_supplier' => 'RELEASE',
    'disable_buying_supplier' => 'DISABLE',
    'activate_buying_supplier' => 'ACTIVATE',
    'archive_buying_supplier' => 'ARCHIVE',
];
$buyingLifecycleSelected = $buyingLifecycleMap[$buyingFormAction] ?? 'HOLD';
?>
<div class="grid gap-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold">Supplier governance</h3>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">Manage company-scoped supplier identity, customer numbers, restrictions, and lifecycle controls.</p>
        </div>
        <button type="button" data-record-modal-open="buying-supplier-modal" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">
            <span class="material-symbols-rounded text-base" aria-hidden="true">add</span>Add Supplier
        </button>
    </div>

    <?php if ($buyingSuppliers === []): ?>
        <div class="grid min-h-64 place-items-center border-y text-center">
            <div class="max-w-md py-8">
                <span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true">storefront</span>
                <h3 class="mt-3 text-sm font-semibold">No supplier records exist for this company.</h3>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">Add a supplier to establish purchasing identity and governance controls.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto border-y">
            <table class="w-full min-w-[58rem] text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground"><tr><th class="px-3 py-3">Supplier</th><th class="px-3 py-3">Type</th><th class="px-3 py-3">Currency</th><th class="px-3 py-3">Governance</th><th class="px-3 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y">
                    <?php foreach ($buyingSuppliers as $supplier): ?>
                        <?php
                        $customerLines = array_map(
                            static fn (array $row): string => (string) $row['related_company_key'] . ' | ' . (string) $row['customer_number'],
                            is_array($supplier['customer_numbers'] ?? null) ? $supplier['customer_numbers'] : []
                        );
                        $allowedLines = array_map(
                            static fn (array $row): string => (string) $row['allowed_company_key'],
                            is_array($supplier['allowed_companies'] ?? null) ? $supplier['allowed_companies'] : []
                        );
                        $editPayload = $supplier;
                        $editPayload['customer_numbers_text'] = implode("\n", $customerLines);
                        $editPayload['allowed_company_keys_text'] = implode("\n", $allowedLines);
                        $editPayloadJson = json_encode($editPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
                        $supplierStatus = (string) $supplier['supplier_status'];
                        $supplierOnHold = (int) $supplier['on_hold'] === 1;
                        ?>
                        <tr>
                            <td class="px-3 py-3"><span class="font-medium"><?= bx_h((string) $supplier['supplier_name']) ?></span><span class="block text-xs text-muted-foreground"><?= bx_h((string) $supplier['supplier_code']) ?></span></td>
                            <td class="px-3 py-3"><?= bx_h((string) $supplier['supplier_type']) ?></td>
                            <td class="px-3 py-3"><?= bx_h((string) ($supplier['default_currency'] ?: 'Not set')) ?></td>
                            <td class="px-3 py-3"><span class="font-medium"><?= bx_h($supplierStatus) ?></span><?php if ($supplierOnHold): ?><span class="block text-xs text-amber-600">On hold: <?= bx_h((string) $supplier['hold_type']) ?></span><?php endif; ?></td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <?php if ($supplierStatus !== 'ARCHIVED'): ?>
                                        <button type="button" data-record-modal-open="buying-supplier-modal" data-buying-supplier-edit data-supplier-payload="<?= bx_h($editPayloadJson) ?>" class="inline-flex size-8 items-center justify-center rounded-md border bg-background" aria-label="Edit supplier <?= bx_h((string) $supplier['supplier_name']) ?>" title="Edit supplier"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button>
                                    <?php endif; ?>
                                    <?php if ($supplierStatus === 'ACTIVE' && !$supplierOnHold): ?>
                                        <button type="button" data-record-modal-open="buying-supplier-governance-modal" data-buying-supplier-governance data-supplier-key="<?= bx_h((string) $supplier['supplier_key']) ?>" data-supplier-name="<?= bx_h((string) $supplier['supplier_name']) ?>" data-lifecycle-action="HOLD" class="h-8 rounded-md border bg-background px-2 text-xs">Hold</button>
                                    <?php elseif ($supplierStatus === 'ACTIVE' && $supplierOnHold): ?>
                                        <button type="button" data-record-modal-open="buying-supplier-governance-modal" data-buying-supplier-governance data-supplier-key="<?= bx_h((string) $supplier['supplier_key']) ?>" data-supplier-name="<?= bx_h((string) $supplier['supplier_name']) ?>" data-lifecycle-action="RELEASE" class="h-8 rounded-md border bg-background px-2 text-xs">Release</button>
                                    <?php endif; ?>
                                    <?php if ($supplierStatus === 'ACTIVE'): ?>
                                        <button type="button" data-record-modal-open="buying-supplier-governance-modal" data-buying-supplier-governance data-supplier-key="<?= bx_h((string) $supplier['supplier_key']) ?>" data-supplier-name="<?= bx_h((string) $supplier['supplier_name']) ?>" data-lifecycle-action="DISABLE" class="h-8 rounded-md border bg-background px-2 text-xs">Disable</button>
                                    <?php elseif ($supplierStatus === 'INACTIVE'): ?>
                                        <button type="button" data-record-modal-open="buying-supplier-governance-modal" data-buying-supplier-governance data-supplier-key="<?= bx_h((string) $supplier['supplier_key']) ?>" data-supplier-name="<?= bx_h((string) $supplier['supplier_name']) ?>" data-lifecycle-action="ACTIVATE" class="h-8 rounded-md border bg-background px-2 text-xs">Activate</button>
                                    <?php endif; ?>
                                    <?php if (in_array($supplierStatus, ['ACTIVE', 'INACTIVE'], true)): ?>
                                        <button type="button" data-record-modal-open="buying-supplier-governance-modal" data-buying-supplier-governance data-supplier-key="<?= bx_h((string) $supplier['supplier_key']) ?>" data-supplier-name="<?= bx_h((string) $supplier['supplier_name']) ?>" data-lifecycle-action="ARCHIVE" class="h-8 rounded-md border bg-background px-2 text-xs">Archive</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="buying-supplier-modal" data-record-modal <?= $buyingReopenSupplierModal ? 'data-record-modal-open-on-load' : '' ?> hidden class="fixed inset-0 z-[70] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="buying-supplier-modal-title" aria-describedby="buying-supplier-modal-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-4xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg" role="document">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4">
            <div class="min-w-0"><h2 id="buying-supplier-modal-title" class="text-base font-semibold"><?= !empty($buyingSupplierInput['supplier_key']) ? 'Edit supplier' : 'Add Supplier' ?></h2><p id="buying-supplier-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Save supplier identity, commercial defaults, customer numbers, and internal-company permissions.</p></div>
            <button type="button" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background" aria-label="Close supplier dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button>
        </header>
        <form method="post" data-confirm-submit data-confirm-message="Save this supplier and its governance rows?" data-buying-supplier-form class="contents">
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
            <input type="hidden" name="module_view" value="buying-procurement">
            <input type="hidden" name="action" value="save_buying_supplier">
            <input type="hidden" name="section" value="suppliers">
            <input type="hidden" name="supplier_key" value="<?= bx_h((string) ($buyingSupplierInput['supplier_key'] ?? '')) ?>">
            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                <?php if ($buyingReopenSupplierModal): ?><div class="mb-5 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert"><?= bx_h($buyingFormError) ?></div><?php endif; ?>
                <div class="grid gap-6">
                    <fieldset class="grid gap-4 sm:grid-cols-2"><legend class="mb-3 text-sm font-semibold">Supplier identity</legend>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Supplier name</span><input name="supplier_name" maxlength="200" value="<?= bx_h((string) ($buyingSupplierInput['supplier_name'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3" required></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Supplier code</span><input name="supplier_code" maxlength="80" value="<?= bx_h((string) ($buyingSupplierInput['supplier_code'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"><span class="text-xs text-muted-foreground">Used when the naming mode accepts an explicit code.</span></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Supplier type</span><select name="supplier_type" class="h-10 rounded-md border bg-background px-3" required><?php foreach (['COMPANY', 'INDIVIDUAL', 'PARTNERSHIP'] as $value): ?><option value="<?= $value ?>" <?= (string) ($buyingSupplierInput['supplier_type'] ?? 'COMPANY') === $value ? 'selected' : '' ?>><?= ucfirst(strtolower($value)) ?></option><?php endforeach; ?></select></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Default currency</span><input name="default_currency" maxlength="20" value="<?= bx_h((string) ($buyingSupplierInput['default_currency'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3" placeholder="PHP"></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Supplier group key</span><input name="supplier_group_key" maxlength="36" value="<?= bx_h((string) ($buyingSupplierInput['supplier_group_key'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Country key</span><input name="country_key" maxlength="36" value="<?= bx_h((string) ($buyingSupplierInput['country_key'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Buying price list key</span><input name="default_price_list_key" maxlength="36" value="<?= bx_h((string) ($buyingSupplierInput['default_price_list_key'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Payment terms key</span><input name="payment_terms_key" maxlength="36" value="<?= bx_h((string) ($buyingSupplierInput['payment_terms_key'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                    </fieldset>
                    <fieldset class="grid gap-4 border-t pt-5 sm:grid-cols-2"><legend class="mb-3 text-sm font-semibold">Contact and tax</legend>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Tax ID</span><input name="tax_id" maxlength="80" value="<?= bx_h((string) ($buyingSupplierInput['tax_id'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Language</span><input name="language_code" maxlength="20" value="<?= bx_h((string) ($buyingSupplierInput['language_code'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Email</span><input type="email" name="email" maxlength="190" value="<?= bx_h((string) ($buyingSupplierInput['email'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Phone</span><input name="phone" maxlength="80" value="<?= bx_h((string) ($buyingSupplierInput['phone'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                        <label class="grid gap-1.5 text-sm sm:col-span-2"><span class="font-medium">Website</span><input type="url" name="website" maxlength="220" value="<?= bx_h((string) ($buyingSupplierInput['website'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                        <label class="grid gap-1.5 text-sm sm:col-span-2"><span class="font-medium">Supplier details</span><textarea name="supplier_details" maxlength="10000" rows="3" class="rounded-md border bg-background px-3 py-2"><?= bx_h((string) ($buyingSupplierInput['supplier_details'] ?? '')) ?></textarea></label>
                    </fieldset>
                    <fieldset class="grid gap-4 border-t pt-5"><legend class="mb-3 text-sm font-semibold">Customer numbers and internal company rules</legend>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Customer numbers</span><textarea name="customer_numbers_text" rows="4" class="rounded-md border bg-background px-3 py-2" placeholder="company-key | customer-number"><?= bx_h($buyingSupplierCustomerText) ?></textarea><span class="text-xs text-muted-foreground">One active company key and customer number per line.</span></label>
                        <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_internal_supplier" value="1" <?= $buyingSupplierChecked('is_internal_supplier') ?> class="mt-0.5 size-4 rounded border"><span>Internal supplier</span></label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="grid gap-1.5 text-sm"><span class="font-medium">Represents company key</span><input name="represents_company_key" maxlength="1500" value="<?= bx_h((string) ($buyingSupplierInput['represents_company_key'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                            <label class="grid gap-1.5 text-sm"><span class="font-medium">Allowed company keys</span><textarea name="allowed_company_keys_text" rows="3" class="rounded-md border bg-background px-3 py-2"><?= bx_h($buyingSupplierAllowedText) ?></textarea></label>
                        </div>
                    </fieldset>
                    <fieldset class="grid gap-3 border-t pt-5 sm:grid-cols-2"><legend class="mb-3 text-sm font-semibold">Purchasing restrictions</legend>
                        <?php foreach ([
                            'is_transporter' => 'Supplier is a transporter',
                            'warn_rfqs' => 'Warn on requests for quotation',
                            'prevent_rfqs' => 'Prevent requests for quotation',
                            'warn_purchase_orders' => 'Warn on purchase orders',
                            'prevent_purchase_orders' => 'Prevent purchase orders',
                        ] as $key => $label): ?><label class="flex items-start gap-3 text-sm"><input type="checkbox" name="<?= bx_h($key) ?>" value="1" <?= $buyingSupplierChecked($key) ?> class="mt-0.5 size-4 rounded border"><span><?= bx_h($label) ?></span></label><?php endforeach; ?>
                    </fieldset>
                </div>
            </div>
            <footer class="m-0 flex w-full shrink-0 flex-wrap items-center justify-between gap-3 rounded-none border-t bg-popover px-6 py-4"><span class="text-xs text-muted-foreground">Customer-number rows save in the supplier transaction.</span><div class="flex gap-2"><button type="button" data-record-modal-close class="h-9 rounded-md border bg-background px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Save Supplier</button></div></footer>
        </form>
    </section>
</div>

<div id="buying-supplier-governance-modal" data-record-modal <?= $buyingReopenLifecycleModal ? 'data-record-modal-open-on-load' : '' ?> hidden class="fixed inset-0 z-[70] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="buying-supplier-governance-title" aria-describedby="buying-supplier-governance-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg" role="document">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4">
            <div class="min-w-0"><h2 id="buying-supplier-governance-title" class="text-base font-semibold">Supplier lifecycle</h2><p id="buying-supplier-governance-description" class="mt-1 text-sm leading-6 text-muted-foreground">Apply a locked, audited lifecycle action to <span data-buying-governance-supplier-name>the selected supplier</span>.</p></div>
            <button type="button" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background" aria-label="Close supplier lifecycle dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button>
        </header>
        <form method="post" data-confirm-submit data-confirm-message="Apply this supplier lifecycle action?" data-buying-governance-form class="contents">
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
            <input type="hidden" name="module_view" value="buying-procurement">
            <input type="hidden" name="action" value="<?= bx_h(strtolower($buyingLifecycleSelected) . '_buying_supplier') ?>">
            <input type="hidden" name="section" value="suppliers">
            <input type="hidden" name="supplier_key" value="<?= bx_h((string) ($buyingLifecycleInput['supplier_key'] ?? '')) ?>">
            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                <?php if ($buyingReopenLifecycleModal): ?><div class="mb-5 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert"><?= bx_h($buyingFormError) ?></div><?php endif; ?>
                <div class="grid gap-4">
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Lifecycle action</span><select data-buying-governance-action class="h-10 rounded-md border bg-background px-3" required><?php foreach (['HOLD' => 'Hold supplier', 'RELEASE' => 'Release supplier', 'DISABLE' => 'Disable supplier', 'ACTIVATE' => 'Activate supplier', 'ARCHIVE' => 'Archive supplier'] as $value => $label): ?><option value="<?= $value ?>" <?= $buyingLifecycleSelected === $value ? 'selected' : '' ?>><?= bx_h($label) ?></option><?php endforeach; ?></select></label>
                    <div data-buying-hold-fields class="grid gap-4 sm:grid-cols-2" <?= $buyingLifecycleSelected === 'HOLD' ? '' : 'hidden' ?>>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Hold type</span><select name="hold_type" class="h-10 rounded-md border bg-background px-3"><?php foreach (['ALL' => 'All purchasing', 'INVOICES' => 'Invoices', 'PAYMENTS' => 'Payments'] as $value => $label): ?><option value="<?= $value ?>" <?= (string) ($buyingLifecycleInput['hold_type'] ?? 'ALL') === $value ? 'selected' : '' ?>><?= bx_h($label) ?></option><?php endforeach; ?></select></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Release date</span><input type="date" name="release_date" value="<?= bx_h((string) ($buyingLifecycleInput['release_date'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                    </div>
                </div>
            </div>
            <footer class="m-0 flex w-full shrink-0 flex-wrap items-center justify-between gap-3 rounded-none border-t bg-popover px-6 py-4"><span class="text-xs text-muted-foreground">The current supplier row is locked before mutation.</span><div class="flex gap-2"><button type="button" data-record-modal-close class="h-9 rounded-md border bg-background px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Apply Action</button></div></footer>
        </form>
    </section>
</div>

<script>
(() => {
    const supplierForm = document.querySelector('[data-buying-supplier-form]');
    document.querySelectorAll('[data-buying-supplier-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!supplierForm) return;
            const record = JSON.parse(button.dataset.supplierPayload || '{}');
            Object.entries(record).forEach(([key, value]) => {
                const control = supplierForm.elements.namedItem(key);
                if (!control || Array.isArray(value) || (typeof value === 'object' && value !== null)) return;
                if (control instanceof HTMLInputElement && control.type === 'checkbox') control.checked = Number(value) === 1;
                else control.value = value ?? '';
            });
            const title = document.querySelector('#buying-supplier-modal-title');
            if (title) title.textContent = 'Edit supplier';
        });
    });

    const governanceForm = document.querySelector('[data-buying-governance-form]');
    const actionSelect = governanceForm?.querySelector('[data-buying-governance-action]');
    const holdFields = governanceForm?.querySelector('[data-buying-hold-fields]');
    const setAction = (action) => {
        if (!governanceForm || !actionSelect) return;
        actionSelect.value = action;
        governanceForm.elements.action.value = `${action.toLowerCase()}_buying_supplier`;
        if (holdFields) holdFields.hidden = action !== 'HOLD';
        governanceForm.dataset.confirmMessage = `${action.charAt(0)}${action.slice(1).toLowerCase()} this supplier?`;
    };
    actionSelect?.addEventListener('change', () => setAction(actionSelect.value));
    document.querySelectorAll('[data-buying-supplier-governance]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!governanceForm) return;
            governanceForm.elements.supplier_key.value = button.dataset.supplierKey || '';
            const name = document.querySelector('[data-buying-governance-supplier-name]');
            if (name) name.textContent = button.dataset.supplierName || 'the selected supplier';
            setAction(button.dataset.lifecycleAction || 'HOLD');
        });
    });
})();
</script>
