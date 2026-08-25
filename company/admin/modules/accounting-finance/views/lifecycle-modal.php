<?php
$financeLifecycle = is_array($financeLifecycle ?? null) ? $financeLifecycle : [];
$financeLifecycleModalId = (string) ($financeLifecycle['modal_id'] ?? 'finance-lifecycle-modal');
?>
<div id="<?= bx_h($financeLifecycleModalId) ?>" data-record-modal<?= $financeModalStateAttributes($financeLifecycleModalId) ?> data-finance-lifecycle-modal hidden class="yovel-finance-operation-modal fixed inset-0 z-50 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="<?= bx_h($financeLifecycleModalId) ?>-title">
    <section class="flex w-full max-w-lg flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
        <header class="yovel-finance-sticky-modal-header flex items-start justify-between gap-4 border-b bg-card px-5 py-4">
            <div>
                <h3 id="<?= bx_h($financeLifecycleModalId) ?>-title" class="font-semibold" data-finance-lifecycle-title>Confirm document action</h3>
                <p class="mt-1 text-sm text-muted-foreground" data-finance-lifecycle-description>Review this accounting action before continuing.</p>
            </div>
            <button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close"><span class="material-symbols-rounded text-base">close</span></button>
        </header>
        <form method="post" data-confirm-submit class="contents">
            <div class="grid gap-4 p-5">
                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                <input type="hidden" name="action" data-finance-lifecycle-action>
                <input type="hidden" name="section" value="<?= bx_h((string) ($financeLifecycle['section'] ?? 'dashboard')) ?>">
                <input type="hidden" name="<?= bx_h((string) ($financeLifecycle['key_name'] ?? 'record_key')) ?>" data-finance-lifecycle-key>
                <?php if (!empty($financeLifecycle['document_type'])): ?><input type="hidden" name="document_type" value="<?= bx_h((string) $financeLifecycle['document_type']) ?>"><?php endif; ?>
                <p class="rounded-md border bg-muted/40 p-3 text-sm"><span class="text-muted-foreground">Document</span><strong class="ml-2" data-finance-lifecycle-number></strong></p>
                <div class="grid gap-4" data-finance-cancellation-fields hidden>
                    <label class="grid gap-1.5 text-sm">Cancellation posting date<input class="h-9 rounded-md border bg-background px-3" type="date" name="cancellation_posting_date" value="<?= date('Y-m-d') ?>"></label>
                    <label class="grid gap-1.5 text-sm">Reason<textarea class="min-h-24 rounded-md border bg-background p-3" name="cancellation_reason" maxlength="500"></textarea></label>
                    <p class="text-xs leading-5 text-muted-foreground">Cancellation creates an additive reversal. Posted ledger rows remain immutable.</p>
                </div>
            </div>
            <footer class="flex justify-end gap-2 border-t bg-card px-5 py-4">
                <button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Back</button>
                <button type="submit" class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground" data-finance-lifecycle-submit>Continue</button>
            </footer>
        </form>
    </section>
</div>
