<?php
if ($buyingSection === 'supplier-quotations') {
    require __DIR__ . '/supplier-quotations.php';
    return;
}
$buyingRfqs = is_array($buyingData['rfqs'] ?? null) ? $buyingData['rfqs'] : [];
$buyingSourcingDependencies = is_array($buyingData['sourcingDependencies'] ?? null)
    ? $buyingData['sourcingDependencies']
    : [];
$buyingRfqInput = $buyingReopenRfqModal ? $buyingFormInput : [];
$buyingRfqLifecycleInput = $buyingReopenRfqLifecycleModal ? $buyingFormInput : [];
$buyingItemContractReady = !empty($buyingSourcingDependencies['item_lookup']['available'])
    && !empty($buyingSourcingDependencies['item_uom_resolve']['available']);
$buyingMaterialRequestContractReady = !empty($buyingSourcingDependencies['material_request_snapshot']['available']);
$buyingDeliveryContractReady = !empty($buyingSourcingDependencies['delivery_register']['available']);
$buyingRfqActionLabels = [
    'submit_buying_rfq' => 'Submit RFQ',
    'cancel_buying_rfq' => 'Cancel RFQ',
    'amend_buying_rfq' => 'Amend RFQ',
    'mark_buying_rfq_received' => 'Mark Received',
    'retry_buying_rfq_dispatch' => 'Retry Dispatch',
];
$buyingRfqLifecycleAction = (string) ($buyingRfqLifecycleInput['action'] ?? $buyingFormAction ?: 'submit_buying_rfq');
if (!isset($buyingRfqActionLabels[$buyingRfqLifecycleAction])) {
    $buyingRfqLifecycleAction = 'submit_buying_rfq';
}
?>
<div data-buying-sourcing class="grid gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold">Supplier invitation workflow</h3>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">Prepare company-scoped requests, preserve source references, and track each supplier dispatch.</p>
        </div>
        <button type="button" data-buying-rfq-open data-record-modal-open="buying-rfq-modal" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground" <?= $buyingItemContractReady ? '' : 'disabled aria-disabled="true" title="Inventory item and UOM services are unavailable"' ?>>
            <span class="material-symbols-rounded text-base" aria-hidden="true">add</span>New RFQ
        </button>
    </div>

    <?php if (!$buyingItemContractReady || !$buyingMaterialRequestContractReady || !$buyingDeliveryContractReady): ?>
        <div class="border-y py-3" role="status" data-buying-sourcing-dependencies>
            <p class="text-sm font-medium">Owner-service availability</p>
            <div class="mt-2 grid gap-1 text-sm text-muted-foreground">
                <?php if (!$buyingItemContractReady): ?><p>Inventory item/UOM contract unavailable. RFQ item validation and draft creation are disabled.</p><?php endif; ?>
                <?php if (!$buyingMaterialRequestContractReady): ?><p>Inventory material-request snapshot unavailable. Manual RFQs remain available; source mapping is unavailable.</p><?php endif; ?>
                <?php if (!$buyingDeliveryContractReady): ?><p>Operations delivery registration unavailable. Drafts remain available; submission cannot dispatch invitations.</p><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($buyingRfqs === []): ?>
        <div class="grid min-h-64 place-items-center border-y text-center">
            <div class="max-w-md py-8">
                <span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true">request_quote</span>
                <h3 class="mt-3 text-sm font-semibold">No requests for quotation exist for this company.</h3>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">Create a draft after Inventory item and UOM services are available.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto border-y">
            <table class="w-full min-w-[68rem] text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground"><tr><th class="px-3 py-3">RFQ</th><th class="px-3 py-3">Dates</th><th class="px-3 py-3">Suppliers</th><th class="px-3 py-3">Items</th><th class="px-3 py-3">Status</th><th class="px-3 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y">
                    <?php foreach ($buyingRfqs as $rfq): ?>
                        <?php
                        $supplierLines = array_map(
                            static fn (array $row): string => implode(' | ', [(string) $row['supplier_key'], (string) $row['email'], (string) $row['delivery_channel']]),
                            is_array($rfq['suppliers'] ?? null) ? $rfq['suppliers'] : []
                        );
                        $itemLines = array_map(
                            static fn (array $row): string => implode(' | ', [
                                (string) $row['inventory_item_key'],
                                (string) $row['uom_key'],
                                (string) $row['quantity'],
                                (string) $row['schedule_date'],
                                (string) ($row['warehouse_key'] ?? ''),
                                (string) ($row['material_request_key'] ?? ''),
                                (string) ($row['material_request_item_key'] ?? ''),
                            ]),
                            is_array($rfq['items'] ?? null) ? $rfq['items'] : []
                        );
                        $editPayload = [
                            'rfq_key' => (string) $rfq['rfq_key'],
                            'expected_version' => (string) $rfq['row_version'],
                            'subject' => (string) $rfq['subject'],
                            'transaction_date' => (string) $rfq['transaction_date'],
                            'schedule_date' => (string) $rfq['schedule_date'],
                            'terms' => (string) ($rfq['terms'] ?? ''),
                            'message_for_supplier' => (string) ($rfq['message_for_supplier'] ?? ''),
                            'suppliers_text' => implode("\n", $supplierLines),
                            'items_text' => implode("\n", $itemLines),
                        ];
                        $editPayloadJson = json_encode($editPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
                        $status = (string) $rfq['document_status'];
                        ?>
                        <tr id="buying-rfq-<?= bx_h((string) $rfq['rfq_key']) ?>">
                            <td class="px-3 py-3"><span class="font-medium"><?= bx_h((string) $rfq['rfq_number']) ?></span><span class="block max-w-64 truncate text-xs text-muted-foreground"><?= bx_h((string) $rfq['subject']) ?></span><?php if (!empty($rfq['amended_from_key'])): ?><span class="block text-xs text-muted-foreground">Amendment <?= (int) $rfq['revision_no'] ?></span><?php endif; ?></td>
                            <td class="px-3 py-3"><span><?= bx_h((string) $rfq['transaction_date']) ?></span><span class="block text-xs text-muted-foreground">Due <?= bx_h((string) $rfq['schedule_date']) ?></span></td>
                            <td class="px-3 py-3"><?= count($rfq['suppliers']) ?> invited<?php foreach ($rfq['suppliers'] as $recipient): ?><span class="block text-xs text-muted-foreground"><?= bx_h((string) $recipient['delivery_status']) ?></span><?php endforeach; ?></td>
                            <td class="px-3 py-3"><?= count($rfq['items']) ?> line<?= count($rfq['items']) === 1 ? '' : 's' ?></td>
                            <td class="px-3 py-3"><span class="font-medium"><?= bx_h($status) ?></span><span class="block text-xs text-muted-foreground">Version <?= (int) $rfq['row_version'] ?></span></td>
                            <td class="px-3 py-3"><div class="flex flex-wrap justify-end gap-1.5">
                                <?php if ($status === 'DRAFT'): ?>
                                    <button type="button" data-buying-rfq-open data-record-modal-open="buying-rfq-modal" data-buying-rfq-edit data-rfq-payload="<?= bx_h($editPayloadJson) ?>" class="inline-flex size-8 items-center justify-center rounded-md border bg-background" aria-label="Edit RFQ <?= bx_h((string) $rfq['rfq_number']) ?>" title="Edit RFQ"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button>
                                    <button type="button" data-record-modal-open="buying-rfq-action-modal" data-buying-rfq-action="submit_buying_rfq" data-rfq-key="<?= bx_h((string) $rfq['rfq_key']) ?>" data-rfq-number="<?= bx_h((string) $rfq['rfq_number']) ?>" data-rfq-version="<?= (int) $rfq['row_version'] ?>" class="h-8 rounded-md border bg-background px-2 text-xs" <?= $buyingDeliveryContractReady ? '' : 'disabled aria-disabled="true" title="Operations delivery service unavailable"' ?>>Submit RFQ</button>
                                <?php elseif ($status === 'SUBMITTED'): ?>
                                    <button type="button" data-record-modal-open="buying-rfq-action-modal" data-buying-rfq-action="cancel_buying_rfq" data-rfq-key="<?= bx_h((string) $rfq['rfq_key']) ?>" data-rfq-number="<?= bx_h((string) $rfq['rfq_number']) ?>" data-rfq-version="<?= (int) $rfq['row_version'] ?>" class="h-8 rounded-md border bg-background px-2 text-xs">Cancel RFQ</button>
                                    <?php foreach ($rfq['suppliers'] as $recipient): ?>
                                        <?php if ((string) $recipient['delivery_status'] !== 'RECEIVED'): ?><button type="button" data-record-modal-open="buying-rfq-action-modal" data-buying-rfq-action="mark_buying_rfq_received" data-rfq-key="<?= bx_h((string) $rfq['rfq_key']) ?>" data-rfq-number="<?= bx_h((string) $rfq['rfq_number']) ?>" data-rfq-version="<?= (int) $rfq['row_version'] ?>" data-supplier-key="<?= bx_h((string) $recipient['supplier_key']) ?>" class="h-8 rounded-md border bg-background px-2 text-xs">Mark Received</button><?php endif; ?>
                                        <?php if ((string) $recipient['delivery_status'] !== 'RECEIVED'): ?><button type="button" data-record-modal-open="buying-rfq-action-modal" data-buying-rfq-action="retry_buying_rfq_dispatch" data-rfq-key="<?= bx_h((string) $rfq['rfq_key']) ?>" data-rfq-number="<?= bx_h((string) $rfq['rfq_number']) ?>" data-rfq-version="<?= (int) $rfq['row_version'] ?>" data-supplier-key="<?= bx_h((string) $recipient['supplier_key']) ?>" class="h-8 rounded-md border bg-background px-2 text-xs" <?= $buyingDeliveryContractReady ? '' : 'disabled aria-disabled="true" title="Operations delivery service unavailable"' ?>>Retry Dispatch</button><?php endif; ?>
                                    <?php endforeach; ?>
                                <?php elseif ($status === 'CANCELLED'): ?>
                                    <button type="button" data-record-modal-open="buying-rfq-action-modal" data-buying-rfq-action="amend_buying_rfq" data-rfq-key="<?= bx_h((string) $rfq['rfq_key']) ?>" data-rfq-number="<?= bx_h((string) $rfq['rfq_number']) ?>" data-rfq-version="<?= (int) $rfq['row_version'] ?>" class="h-8 rounded-md border bg-background px-2 text-xs">Amend RFQ</button>
                                <?php endif; ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="buying-rfq-modal" data-record-modal <?= $buyingReopenRfqModal ? 'data-record-modal-open-on-load' : '' ?> hidden class="fixed inset-0 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" style="z-index: 100;" role="dialog" aria-modal="true" aria-labelledby="buying-rfq-modal-title" aria-describedby="buying-rfq-modal-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-5xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg" role="document">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4"><div class="min-w-0"><h2 id="buying-rfq-modal-title" class="text-base font-semibold"><?= !empty($buyingRfqInput['rfq_key']) ? 'Edit Request for Quotation' : 'New Request for Quotation' ?></h2><p id="buying-rfq-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Validate suppliers through Buying and item/UOM identities through Inventory owner services.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background" aria-label="Close RFQ dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <form method="post" data-confirm-submit data-confirm-message="Save this Request for Quotation?" data-buying-rfq-form class="contents">
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="buying-procurement"><input type="hidden" name="action" value="save_buying_rfq"><input type="hidden" name="section" value="request-for-quotation"><input type="hidden" name="rfq_key" value="<?= bx_h((string) ($buyingRfqInput['rfq_key'] ?? '')) ?>"><input type="hidden" name="expected_version" value="<?= bx_h((string) ($buyingRfqInput['expected_version'] ?? '')) ?>">
            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                <?php if ($buyingReopenRfqModal): ?><div class="mb-5 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert"><?= bx_h($buyingFormError) ?></div><?php endif; ?>
                <div class="grid gap-5">
                    <fieldset class="grid gap-4 sm:grid-cols-2"><legend class="mb-3 text-sm font-semibold">Request details</legend><label class="grid gap-1.5 text-sm sm:col-span-2"><span class="font-medium">Subject</span><input name="subject" maxlength="190" value="<?= bx_h((string) ($buyingRfqInput['subject'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3" required></label><label class="grid gap-1.5 text-sm"><span class="font-medium">Transaction date</span><input type="date" name="transaction_date" value="<?= bx_h((string) ($buyingRfqInput['transaction_date'] ?? date('Y-m-d'))) ?>" class="h-10 rounded-md border bg-background px-3" required></label><label class="grid gap-1.5 text-sm"><span class="font-medium">Required by</span><input type="date" name="schedule_date" value="<?= bx_h((string) ($buyingRfqInput['schedule_date'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3" required></label></fieldset>
                    <label class="grid gap-1.5 border-t pt-5 text-sm"><span class="font-medium">Suppliers</span><span class="text-xs text-muted-foreground">One per line: supplier key | recipient email | EMAIL, PORTAL, or EDI</span><textarea name="suppliers_text" rows="4" class="rounded-md border bg-background px-3 py-2 font-mono text-xs" required><?= bx_h((string) ($buyingRfqInput['suppliers_text'] ?? '')) ?></textarea></label>
                    <label class="grid gap-1.5 border-t pt-5 text-sm"><span class="font-medium">Items</span><span class="text-xs text-muted-foreground">One per line: item key | UOM key | quantity | schedule date | warehouse key | material request key | request item key</span><textarea name="items_text" rows="5" class="rounded-md border bg-background px-3 py-2 font-mono text-xs" required><?= bx_h((string) ($buyingRfqInput['items_text'] ?? '')) ?></textarea></label>
                    <div class="grid gap-4 border-t pt-5 sm:grid-cols-2"><label class="grid gap-1.5 text-sm"><span class="font-medium">Supplier message</span><textarea name="message_for_supplier" rows="4" maxlength="4000" class="rounded-md border bg-background px-3 py-2"><?= bx_h((string) ($buyingRfqInput['message_for_supplier'] ?? '')) ?></textarea></label><label class="grid gap-1.5 text-sm"><span class="font-medium">Terms</span><textarea name="terms" rows="4" maxlength="8000" class="rounded-md border bg-background px-3 py-2"><?= bx_h((string) ($buyingRfqInput['terms'] ?? '')) ?></textarea></label></div>
                </div>
            </div>
            <footer class="m-0 flex w-full shrink-0 flex-wrap items-center justify-between gap-3 rounded-none border-t bg-popover px-6 py-4"><span class="text-xs text-muted-foreground">No record is written until the separate confirmation is accepted.</span><div class="flex gap-2"><button type="button" data-record-modal-close class="h-9 rounded-md border bg-background px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Save RFQ</button></div></footer>
        </form>
    </section>
</div>

<div id="buying-rfq-action-modal" data-record-modal <?= $buyingReopenRfqLifecycleModal ? 'data-record-modal-open-on-load' : '' ?> hidden class="fixed inset-0 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" style="z-index: 100;" role="dialog" aria-modal="true" aria-labelledby="buying-rfq-action-title" aria-describedby="buying-rfq-action-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg" role="document">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4"><div class="min-w-0"><h2 id="buying-rfq-action-title" data-buying-rfq-action-title class="text-base font-semibold"><?= bx_h($buyingRfqActionLabels[$buyingRfqLifecycleAction]) ?></h2><p id="buying-rfq-action-description" class="mt-1 text-sm leading-6 text-muted-foreground">Review this lifecycle change before opening the separate confirmation dialog.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background" aria-label="Close RFQ action dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <form method="post" data-confirm-submit data-confirm-message="Confirm this Request for Quotation action?" data-buying-rfq-action-form class="contents">
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="buying-procurement"><input type="hidden" name="section" value="request-for-quotation"><input type="hidden" name="action" value="<?= bx_h($buyingRfqLifecycleAction) ?>"><input type="hidden" name="rfq_key" value="<?= bx_h((string) ($buyingRfqLifecycleInput['rfq_key'] ?? '')) ?>"><input type="hidden" name="expected_version" value="<?= bx_h((string) ($buyingRfqLifecycleInput['expected_version'] ?? '')) ?>"><input type="hidden" name="supplier_key" value="<?= bx_h((string) ($buyingRfqLifecycleInput['supplier_key'] ?? '')) ?>">
            <div class="min-h-0 flex-1 overflow-y-auto p-6"><?php if ($buyingReopenRfqLifecycleModal): ?><div class="mb-4 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert"><?= bx_h($buyingFormError) ?></div><?php endif; ?><p class="text-sm">Apply <strong data-buying-rfq-action-label><?= bx_h($buyingRfqActionLabels[$buyingRfqLifecycleAction]) ?></strong> to <span data-buying-rfq-action-record class="font-medium"><?= bx_h((string) ($buyingRfqLifecycleInput['rfq_number'] ?? 'this RFQ')) ?></span>?</p><p class="mt-2 text-sm text-muted-foreground">The current RFQ and recipient rows are locked and the expected version is verified inside one transaction.</p></div>
            <footer class="m-0 flex w-full shrink-0 items-center justify-end gap-2 rounded-none border-t bg-popover px-6 py-4"><button type="button" data-record-modal-close class="h-9 rounded-md border bg-background px-3 text-sm">Cancel</button><button type="submit" data-buying-rfq-action-submit class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><?= bx_h($buyingRfqActionLabels[$buyingRfqLifecycleAction]) ?></button></footer>
        </form>
    </section>
</div>

<script>
(() => {
    const form = document.querySelector('[data-buying-rfq-form]');
    const formTitle = document.querySelector('#buying-rfq-modal-title');
    document.querySelectorAll('[data-buying-rfq-open]').forEach((button) => button.addEventListener('click', () => {
        if (!form || button.hasAttribute('data-buying-rfq-edit')) return;
        form.reset();
        form.elements.rfq_key.value = '';
        form.elements.expected_version.value = '';
        form.elements.transaction_date.value = <?= json_encode(date('Y-m-d')) ?>;
        if (formTitle) formTitle.textContent = 'New Request for Quotation';
    }));
    document.querySelectorAll('[data-buying-rfq-edit]').forEach((button) => button.addEventListener('click', () => {
        if (!form) return;
        const record = JSON.parse(button.dataset.rfqPayload || '{}');
        Object.entries(record).forEach(([key, value]) => { const control = form.elements.namedItem(key); if (control) control.value = value ?? ''; });
        if (formTitle) formTitle.textContent = 'Edit Request for Quotation';
    }));

    const actionLabels = <?= json_encode($buyingRfqActionLabels, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const actionForm = document.querySelector('[data-buying-rfq-action-form]');
    document.querySelectorAll('[data-buying-rfq-action]').forEach((button) => button.addEventListener('click', () => {
        if (!actionForm) return;
        const action = button.dataset.buyingRfqAction || '';
        actionForm.elements.action.value = action;
        actionForm.elements.rfq_key.value = button.dataset.rfqKey || '';
        actionForm.elements.expected_version.value = button.dataset.rfqVersion || '';
        actionForm.elements.supplier_key.value = button.dataset.supplierKey || '';
        const label = actionLabels[action] || 'Apply RFQ Action';
        document.querySelectorAll('[data-buying-rfq-action-title], [data-buying-rfq-action-label], [data-buying-rfq-action-submit]').forEach((node) => { node.textContent = label; });
        const record = document.querySelector('[data-buying-rfq-action-record]');
        if (record) record.textContent = button.dataset.rfqNumber || 'this RFQ';
    }));
})();
</script>
