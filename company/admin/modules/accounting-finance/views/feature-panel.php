<?php
$sectionLabel=(string)($activeAccountingFinanceMeta['label']??'Finance');
?>
<aside class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
    <header class="yovel-finance-sticky-panel-header border-b bg-card px-5 py-4">
        <h3 class="text-base font-semibold">Features / Functions</h3>
        <p class="mt-1 text-sm text-muted-foreground">Controls for <?= bx_h($sectionLabel) ?>.</p>
    </header>
    <div class="yovel-hr-panel-body grid content-start gap-3 p-5">
        <?php if($activeAccountingFinanceRecordType!==''): ?><button type="button" id="yovel-account-builder-modal-open" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">tune</span>Customize Form</button><?php endif; ?>
        <?php require __DIR__.'/grid-operations.php'; ?>
    </div>
</aside>
