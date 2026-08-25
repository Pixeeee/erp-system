<?php
/** Finance Dashboard variables are prepared by bootstrap/controller.php. */
$financeRecentForms = array_slice($financeBuilderForms, 0, 5);
$financeLedgerAccounts = array_values(array_filter($accountingAccounts, static fn (array $account): bool => (int) ($account['is_group'] ?? 0) !== 1));
$financeSetupSteps = [
    ['label' => 'Build the Chart of Accounts', 'section' => 'chart-of-accounts', 'complete' => count($accountingAccounts) > 0, 'meta' => count($accountingAccounts) . ' accounts'],
    ['label' => 'Define cost tracking', 'section' => 'cost-centers', 'complete' => false, 'meta' => 'Cost centers and dimensions'],
    ['label' => 'Configure banking', 'section' => 'bank-accounts', 'complete' => false, 'meta' => 'Bank and cash accounts'],
    ['label' => 'Review committed postings', 'section' => 'general-ledger', 'complete' => $generalLedgerEntryCount > 0, 'meta' => $generalLedgerEntryCount . ' ledger entries'],
];
$financeSetupComplete = count(array_filter($financeSetupSteps, static fn (array $step): bool => $step['complete']));
?>
<div class="yovel-finance-dashboard yovel-hr-two-panel grid min-h-0 gap-4 xl:grid-cols-[minmax(0,12fr)_minmax(16rem,4fr)]">
    <section class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
        <header class="yovel-finance-sticky-panel-header border-b bg-card px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold">Finance Dashboard</h2>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">Accounting setup, transaction access, and financial reports for <?= bx_h($companyName) ?>.</p>
                </div>
                <a class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=accounting-finance&amp;section=general-ledger"><span class="material-symbols-rounded text-base" aria-hidden="true">menu_book</span>General Ledger</a>
            </div>
        </header>
        <div class="yovel-hr-panel-body grid content-start gap-5 p-5">
            <section aria-labelledby="finance-overview-title">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 id="finance-overview-title" class="text-sm font-semibold">Accounting Status</h3>
                    <span class="text-xs text-muted-foreground">Committed company data</span>
                </div>
                <div class="yovel-finance-dashboard-metrics grid gap-2">
                    <?php foreach ([
                        ['label' => 'Active accounts', 'value' => count($activeAccountingAccounts)],
                        ['label' => 'Ledger accounts', 'value' => count($financeLedgerAccounts)],
                        ['label' => 'Group accounts', 'value' => count($accountingAccounts) - count($financeLedgerAccounts)],
                        ['label' => 'Custom forms', 'value' => count($financeBuilderForms)],
                        ['label' => 'GL entries', 'value' => $generalLedgerEntryCount],
                    ] as $metric): ?>
                        <div class="rounded-md border bg-background px-3 py-3">
                            <p class="text-xs text-muted-foreground"><?= bx_h((string) $metric['label']) ?></p>
                            <p class="mt-1 text-xl font-semibold"><?= (int) $metric['value'] ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="yovel-finance-dashboard-setup border-y py-4" aria-labelledby="finance-setup-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 id="finance-setup-title" class="text-sm font-semibold">Accounting Setup</h3>
                        <p class="mt-1 text-xs text-muted-foreground"><?= $financeSetupComplete ?> of <?= count($financeSetupSteps) ?> readiness steps complete</p>
                    </div>
                    <div class="h-1.5 w-36 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuemin="0" aria-valuemax="<?= count($financeSetupSteps) ?>" aria-valuenow="<?= $financeSetupComplete ?>" aria-label="Finance setup progress"><span class="block h-full bg-primary" style="width: <?= count($financeSetupSteps) > 0 ? ($financeSetupComplete / count($financeSetupSteps)) * 100 : 0 ?>%"></span></div>
                </div>
                <div class="mt-4 grid divide-y rounded-md border">
                    <?php foreach ($financeSetupSteps as $step): ?>
                        <a class="flex items-center gap-3 bg-background px-3 py-3 hover:bg-muted" href="./?view=accounting-finance&amp;section=<?= bx_h((string) $step['section']) ?>">
                            <span class="material-symbols-rounded text-lg <?= $step['complete'] ? 'text-emerald-500' : 'text-muted-foreground' ?>" aria-hidden="true"><?= $step['complete'] ? 'check_circle' : 'radio_button_unchecked' ?></span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-medium"><?= bx_h((string) $step['label']) ?></span><span class="block text-xs text-muted-foreground"><?= bx_h((string) $step['meta']) ?></span></span>
                            <span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">chevron_right</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section aria-labelledby="finance-shortcuts-title">
                <h3 id="finance-shortcuts-title" class="text-sm font-semibold">Operations</h3>
                <div class="yovel-finance-dashboard-links mt-3 grid gap-2">
                    <?php foreach ([
                        ['section' => 'sales-invoices', 'label' => 'Sales Invoices', 'meta' => 'Customer billing', 'icon' => 'receipt_long'],
                        ['section' => 'purchase-invoices', 'label' => 'Purchase Invoices', 'meta' => 'Supplier billing', 'icon' => 'request_quote'],
                        ['section' => 'journal-entries', 'label' => 'Journal Entries', 'meta' => 'Balanced adjustments', 'icon' => 'library_books'],
                        ['section' => 'payment-entries', 'label' => 'Payment Entries', 'meta' => 'Incoming and outgoing', 'icon' => 'payments'],
                        ['section' => 'bank-accounts', 'label' => 'Bank Accounts', 'meta' => 'Bank and cash setup', 'icon' => 'account_balance'],
                        ['section' => 'bank-reconciliation', 'label' => 'Bank Reconciliation', 'meta' => 'Match bank movement', 'icon' => 'sync_alt'],
                    ] as $shortcut): ?>
                        <a class="flex min-w-0 items-center gap-3 rounded-md border bg-background p-3 hover:bg-muted" href="./?view=accounting-finance&amp;section=<?= bx_h((string) $shortcut['section']) ?>">
                            <span class="material-symbols-rounded text-lg text-muted-foreground" aria-hidden="true"><?= bx_h((string) $shortcut['icon']) ?></span>
                            <span class="min-w-0"><span class="block truncate text-sm font-medium"><?= bx_h((string) $shortcut['label']) ?></span><span class="block truncate text-xs text-muted-foreground"><?= bx_h((string) $shortcut['meta']) ?></span></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section aria-labelledby="finance-reports-title">
                <h3 id="finance-reports-title" class="text-sm font-semibold">Financial Reports</h3>
                <div class="mt-3 grid divide-y rounded-md border bg-background sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                    <?php foreach ([
                        ['section' => 'general-ledger', 'label' => 'General Ledger'],
                        ['section' => 'profit-loss', 'label' => 'Profit / Loss'],
                        ['section' => 'balance-sheet', 'label' => 'Balance Sheet'],
                        ['section' => 'cash-flow', 'label' => 'Cash Flow'],
                        ['section' => 'tax-reports', 'label' => 'Tax Reports'],
                        ['section' => 'budgets', 'label' => 'Budgets'],
                    ] as $report): ?>
                        <a class="flex items-center justify-between gap-3 px-3 py-3 text-sm font-medium hover:bg-muted" href="./?view=accounting-finance&amp;section=<?= bx_h((string) $report['section']) ?>"><span><?= bx_h((string) $report['label']) ?></span><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">arrow_outward</span></a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </section>

    <aside class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
        <header class="yovel-finance-sticky-panel-header border-b bg-card px-5 py-4">
            <h2 class="text-base font-semibold">Finance Form Builder</h2>
            <p class="mt-1 text-sm leading-6 text-muted-foreground">Create custom forms or maintain forms already used by Finance.</p>
        </header>
        <div class="yovel-hr-panel-body grid content-start gap-4 p-5">
            <div class="grid gap-2">
                <a data-finance-form-builder-open class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90" href="./?view=accounting-finance&amp;section=dashboard&amp;finance_builder=1&amp;builder_mode=new&amp;builder_target=chart-of-accounts"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>New Form</a>
                <a data-finance-form-builder-open class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=accounting-finance&amp;section=dashboard&amp;finance_builder=1&amp;builder_mode=existing&amp;builder_target=chart-of-accounts"><span class="material-symbols-rounded text-base" aria-hidden="true">folder_open</span>Existing Forms</a>
            </div>
            <div class="grid gap-2 border-y py-4">
                <a class="flex items-center justify-between gap-2 rounded-md px-2 py-2 text-sm hover:bg-muted" href="./?view=accounting-finance&amp;section=chart-of-accounts&amp;customize=1"><span>Customize account form</span><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">tune</span></a>
                <a class="flex items-center justify-between gap-2 rounded-md px-2 py-2 text-sm hover:bg-muted" href="./?view=accounting-finance&amp;section=general-ledger"><span>Open General Ledger</span><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">menu_book</span></a>
            </div>
            <section aria-labelledby="finance-saved-forms-title">
                <div class="flex items-center justify-between gap-2"><h3 id="finance-saved-forms-title" class="text-xs font-semibold uppercase text-muted-foreground">Saved Forms</h3><span class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold"><?= count($financeBuilderForms) ?></span></div>
                <?php if (!$financeRecentForms): ?>
                    <p class="mt-3 rounded-md border border-dashed p-3 text-xs leading-5 text-muted-foreground">No custom Finance forms yet.</p>
                <?php else: ?>
                    <div class="mt-2 grid divide-y rounded-md border bg-background">
                        <?php foreach ($financeRecentForms as $form): ?>
                            <a class="px-3 py-3 hover:bg-muted" href="./?view=accounting-finance&amp;section=dashboard&amp;finance_builder=1&amp;builder_mode=existing&amp;builder_target=<?= bx_h((string) $form['target_section']) ?>&amp;form=<?= bx_h((string) $form['builder_form_key']) ?>"><span class="block truncate text-sm font-medium"><?= bx_h((string) $form['form_title']) ?></span><span class="mt-1 block text-xs text-muted-foreground"><?= bx_h((string) ($financeBuilderTargetSections[(string) $form['target_section']]['label'] ?? $form['target_section'])) ?> · <?= (int) ($form['question_count'] ?? 0) ?> fields</span></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </aside>
</div>
