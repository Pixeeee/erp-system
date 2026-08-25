<?php
$buyingSettings = is_array($buyingData['settings'] ?? null) ? $buyingData['settings'] : yovel_admin_buying_settings_defaults();
$buyingSettingsInput = $buyingReopenSettingsModal ? array_replace($buyingSettings, $buyingFormInput) : $buyingSettings;
$buyingSettingChecked = static fn (string $key): string => yovel_admin_buying_bool($buyingSettingsInput, $key) === 1 ? 'checked' : '';
?>
<div class="grid gap-5">
    <section class="grid gap-3" aria-labelledby="buying-settings-summary-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 id="buying-settings-summary-title" class="text-sm font-semibold">Procurement controls</h3>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">Current company defaults are loaded from the Buying-owned settings record.</p>
            </div>
            <button type="button" data-record-modal-open="buying-settings-modal" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">
                <span class="material-symbols-rounded text-base" aria-hidden="true">edit</span>Edit Buying Settings
            </button>
        </div>
        <dl class="grid gap-x-6 gap-y-3 border-y py-4 sm:grid-cols-2">
            <div><dt class="text-xs text-muted-foreground">Supplier naming</dt><dd class="mt-1 text-sm font-medium"><?= bx_h((string) $buyingSettings['supplier_naming_mode']) ?></dd></div>
            <div><dt class="text-xs text-muted-foreground">Rate mismatch</dt><dd class="mt-1 text-sm font-medium"><?= !empty($buyingSettings['maintain_same_rate']) ? bx_h((string) $buyingSettings['maintain_same_rate_action']) : 'Not enforced' ?></dd></div>
            <div><dt class="text-xs text-muted-foreground">Over-order allowance</dt><dd class="mt-1 text-sm font-medium"><?= bx_h((string) $buyingSettings['over_order_allowance']) ?>%</dd></div>
            <div><dt class="text-xs text-muted-foreground">Over-transfer allowance</dt><dd class="mt-1 text-sm font-medium"><?= bx_h((string) $buyingSettings['over_transfer_allowance']) ?>%</dd></div>
        </dl>
    </section>
    <section class="border-t pt-4" aria-labelledby="buying-finance-defaults-title">
        <h3 id="buying-finance-defaults-title" class="text-sm font-semibold">Finance owner service</h3>
        <?php if (!empty($buyingSettings['finance_defaults_available'])): ?>
            <p class="mt-1 text-sm text-emerald-600">Finance defaults available through the owner-service contract.</p>
        <?php else: ?>
            <p class="mt-1 text-sm text-muted-foreground">Finance defaults unavailable. Local supplier and Buying Settings controls remain available.</p>
        <?php endif; ?>
    </section>
</div>

<div id="buying-settings-modal" data-record-modal <?= $buyingReopenSettingsModal ? 'data-record-modal-open-on-load' : '' ?> hidden class="fixed inset-0 z-[70] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="buying-settings-modal-title" aria-describedby="buying-settings-modal-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-3xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg" role="document">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4">
            <div class="min-w-0"><h2 id="buying-settings-modal-title" class="text-base font-semibold">Edit Buying Settings</h2><p id="buying-settings-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Update supplier naming, document controls, and purchasing allowances for this company.</p></div>
            <button type="button" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background" aria-label="Close Buying Settings dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button>
        </header>
        <form method="post" data-confirm-submit data-confirm-message="Save these company Buying Settings?" class="contents">
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
            <input type="hidden" name="module_view" value="buying-procurement">
            <input type="hidden" name="action" value="save_buying_settings">
            <input type="hidden" name="section" value="buying-settings">
            <input type="hidden" name="buying_setting_key" value="<?= bx_h((string) ($buyingSettingsInput['buying_setting_key'] ?? '')) ?>">
            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                <?php if ($buyingReopenSettingsModal): ?><div class="mb-5 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert"><?= bx_h($buyingFormError) ?></div><?php endif; ?>
                <div class="grid gap-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Supplier naming</span><select name="supplier_naming_mode" class="h-10 rounded-md border bg-background px-3" required><?php foreach (['SUPPLIER_NAME' => 'Supplier name', 'NAMING_SERIES' => 'Naming series', 'AUTO_NAME' => 'Automatic code'] as $value => $label): ?><option value="<?= $value ?>" <?= (string) ($buyingSettingsInput['supplier_naming_mode'] ?? '') === $value ? 'selected' : '' ?>><?= bx_h($label) ?></option><?php endforeach; ?></select></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Rate mismatch action</span><select name="maintain_same_rate_action" class="h-10 rounded-md border bg-background px-3" required><?php foreach (['STOP', 'WARN'] as $value): ?><option value="<?= $value ?>" <?= (string) ($buyingSettingsInput['maintain_same_rate_action'] ?? '') === $value ? 'selected' : '' ?>><?= ucfirst(strtolower($value)) ?></option><?php endforeach; ?></select></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Default supplier group key</span><input name="default_supplier_group_key" maxlength="36" value="<?= bx_h((string) ($buyingSettingsInput['default_supplier_group_key'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Default buying price list key</span><input name="default_buying_price_list_key" maxlength="36" value="<?= bx_h((string) ($buyingSettingsInput['default_buying_price_list_key'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3"></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Over-order allowance (%)</span><input type="number" name="over_order_allowance" min="0" max="100" step="0.0001" value="<?= bx_h((string) ($buyingSettingsInput['over_order_allowance'] ?? '0.0000')) ?>" class="h-10 rounded-md border bg-background px-3" required></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Over-transfer allowance (%)</span><input type="number" name="over_transfer_allowance" min="0" max="100" step="0.0001" value="<?= bx_h((string) ($buyingSettingsInput['over_transfer_allowance'] ?? '0.0000')) ?>" class="h-10 rounded-md border bg-background px-3" required></label>
                    </div>
                    <fieldset class="grid gap-3 border-t pt-4"><legend class="text-sm font-semibold">Document and quantity controls</legend>
                        <?php foreach ([
                            'purchase_order_required' => 'Purchase order required',
                            'purchase_receipt_required' => 'Purchase receipt required',
                            'maintain_same_rate' => 'Maintain the same purchase rate',
                            'allow_zero_qty_purchase_order' => 'Allow zero quantity on purchase orders',
                            'allow_zero_qty_rfq' => 'Allow zero quantity on requests for quotation',
                            'allow_zero_qty_supplier_quotation' => 'Allow zero quantity on supplier quotations',
                        ] as $key => $label): ?><label class="flex items-start gap-3 text-sm"><input type="checkbox" name="<?= bx_h($key) ?>" value="1" <?= $buyingSettingChecked($key) ?> class="mt-0.5 size-4 rounded border"><span><?= bx_h($label) ?></span></label><?php endforeach; ?>
                    </fieldset>
                </div>
            </div>
            <footer class="m-0 flex w-full shrink-0 flex-wrap items-center justify-between gap-3 rounded-none border-t bg-popover px-6 py-4"><span class="text-xs text-muted-foreground">Saved values are verified before commit.</span><div class="flex gap-2"><button type="button" data-record-modal-close class="h-9 rounded-md border bg-background px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Save Settings</button></div></footer>
        </form>
    </section>
</div>
