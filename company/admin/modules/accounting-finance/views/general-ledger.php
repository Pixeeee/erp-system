<?php
/** General Ledger variables are prepared by bootstrap/controller.php. */
$generalLedgerRunningBalance = (float) ($generalLedgerSummary['opening_balance'] ?? 0);
$generalLedgerVisibleAccounts = array_values(array_filter($accountingAccounts, static fn (array $account): bool => (int) ($account['is_group'] ?? 0) !== 1));
?>
<div class="yovel-general-ledger yovel-hr-two-panel grid min-h-0 gap-4 xl:grid-cols-[minmax(0,12fr)_minmax(16rem,4fr)]">
    <section class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
        <header class="yovel-finance-sticky-panel-header border-b bg-card px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><h2 class="text-base font-semibold">General Ledger</h2><p class="mt-1 text-sm leading-6 text-muted-foreground">Committed debit and credit postings with source voucher traceability.</p></div>
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-md border bg-background hover:bg-muted" aria-label="Export General Ledger" title="Export CSV" data-general-ledger-export><span class="material-symbols-rounded text-base" aria-hidden="true">download</span></button>
            </div>
        </header>
        <div class="yovel-hr-panel-body grid content-start gap-4 p-5">
            <div class="yovel-finance-dashboard-metrics grid gap-2">
                <?php foreach ([
                    ['label' => 'Opening balance', 'value' => $generalLedgerSummary['opening_balance'] ?? '0'],
                    ['label' => 'Period debit', 'value' => $generalLedgerSummary['period_debit'] ?? '0'],
                    ['label' => 'Period credit', 'value' => $generalLedgerSummary['period_credit'] ?? '0'],
                    ['label' => 'Closing balance', 'value' => $generalLedgerSummary['closing_balance'] ?? '0'],
                    ['label' => 'Entries', 'value' => (string) ($generalLedgerSummary['entry_count'] ?? 0), 'count' => true],
                ] as $metric): ?>
                    <div class="rounded-md border bg-background px-3 py-3"><p class="text-xs text-muted-foreground"><?= bx_h((string) $metric['label']) ?></p><p class="mt-1 truncate text-base font-semibold"><?= !empty($metric['count']) ? (int) $metric['value'] : bx_h(number_format((float) $metric['value'], 2, '.', ',')) ?></p></div>
                <?php endforeach; ?>
            </div>
            <div class="yovel-general-ledger-table overflow-auto rounded-md border" data-general-ledger-table>
                <table class="w-full table-fixed text-left text-sm" style="min-width: 1680px">
                    <thead class="yovel-finance-sticky-table-header border-b bg-card text-xs text-muted-foreground">
                        <tr>
                            <?php foreach ([['Posting date',110],['Account',220],['Debit',130],['Credit',130],['Running balance',150],['Voucher type',150],['Voucher no.',170],['Party',180],['Cost center',150],['Project',150],['Remarks',240]] as $column): ?><th scope="col" class="px-3 py-3 font-medium" style="width: <?= (int) $column[1] ?>px"><?= bx_h((string) $column[0]) ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y" data-general-ledger-body>
                        <?php foreach ($generalLedgerEntries as $entry): ?>
                            <?php $generalLedgerRunningBalance += (float) $entry['debit'] - (float) $entry['credit']; ?>
                            <tr data-general-ledger-row>
                                <td class="px-3 py-3 text-xs" data-ledger-value="<?= bx_h((string) $entry['posting_date']) ?>"><?= bx_h((string) $entry['posting_date']) ?></td>
                                <td class="px-3 py-3"><span class="block truncate text-sm font-medium"><?= bx_h((string) $entry['account_name']) ?></span><span class="block truncate text-xs text-muted-foreground"><?= bx_h((string) $entry['account_code']) ?></span></td>
                                <td class="px-3 py-3 text-right font-mono text-xs" data-ledger-value="<?= bx_h((string) $entry['debit']) ?>"><?= number_format((float) $entry['debit'], 2, '.', ',') ?></td>
                                <td class="px-3 py-3 text-right font-mono text-xs" data-ledger-value="<?= bx_h((string) $entry['credit']) ?>"><?= number_format((float) $entry['credit'], 2, '.', ',') ?></td>
                                <td class="px-3 py-3 text-right font-mono text-xs" data-ledger-value="<?= bx_h(number_format($generalLedgerRunningBalance, 6, '.', '')) ?>"><?= number_format($generalLedgerRunningBalance, 2, '.', ',') ?></td>
                                <td class="px-3 py-3 text-xs"><?= bx_h(str_replace('_', ' ', (string) $entry['voucher_type'])) ?><?= (int) ($entry['is_reversal'] ?? 0) === 1 ? ' · Reversal' : '' ?></td>
                                <td class="px-3 py-3 text-xs font-medium"><?= bx_h((string) $entry['voucher_no']) ?></td>
                                <td class="px-3 py-3 text-xs"><?= bx_h((string) (($entry['party'] ?? '') ?: '—')) ?></td>
                                <td class="px-3 py-3 text-xs"><?= bx_h((string) (($entry['cost_center'] ?? '') ?: '—')) ?></td>
                                <td class="px-3 py-3 text-xs"><?= bx_h((string) (($entry['project'] ?? '') ?: '—')) ?></td>
                                <td class="px-3 py-3 text-xs text-muted-foreground"><?= bx_h((string) (($entry['entry_remarks'] ?? '') ?: '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$generalLedgerEntries): ?><tr><td class="px-5 py-10 text-center text-sm text-muted-foreground" colspan="11">No committed accounting postings exist for the selected filters.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <aside class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
        <header class="yovel-finance-sticky-panel-header border-b bg-card px-5 py-4"><h2 class="text-base font-semibold">Report Filters</h2><p class="mt-1 text-sm leading-6 text-muted-foreground">Narrow the committed ledger without changing accounting records.</p></header>
        <div class="yovel-hr-panel-body p-5">
            <form method="get" class="grid gap-3">
                <input type="hidden" name="view" value="accounting-finance"><input type="hidden" name="section" value="general-ledger">
                <div class="grid grid-cols-2 gap-2"><div class="grid gap-1.5"><label class="text-xs font-medium" for="gl_date_from">From</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="gl_date_from" name="date_from" type="date" value="<?= bx_h((string) $generalLedgerFilters['date_from']) ?>"></div><div class="grid gap-1.5"><label class="text-xs font-medium" for="gl_date_to">To</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="gl_date_to" name="date_to" type="date" value="<?= bx_h((string) $generalLedgerFilters['date_to']) ?>"></div></div>
                <div class="grid gap-1.5"><label class="text-xs font-medium" for="gl_account">Account</label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="gl_account" name="account_key"><option value="">All ledger accounts</option><?php foreach ($generalLedgerVisibleAccounts as $account): ?><option value="<?= bx_h((string) $account['account_key']) ?>" <?= (string) $generalLedgerFilters['account_key'] === (string) $account['account_key'] ? 'selected' : '' ?>><?= bx_h((string) $account['account_code']) ?> · <?= bx_h((string) $account['account_name']) ?></option><?php endforeach; ?></select></div>
                <div class="grid gap-1.5"><label class="text-xs font-medium" for="gl_voucher_type">Voucher type</label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="gl_voucher_type" name="voucher_type"><option value="">All voucher types</option><?php foreach (['JOURNAL_ENTRY','SALES_INVOICE','PURCHASE_INVOICE','PAYMENT_ENTRY','REVERSAL'] as $type): ?><option value="<?= bx_h($type) ?>" <?= (string) $generalLedgerFilters['voucher_type'] === $type ? 'selected' : '' ?>><?= bx_h(str_replace('_', ' ', $type)) ?></option><?php endforeach; ?></select></div>
                <?php foreach ([['voucher_no','Voucher number'],['party','Party'],['cost_center','Cost center'],['project','Project'],['finance_book','Finance book']] as $filter): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="gl_<?= bx_h($filter[0]) ?>"><?= bx_h($filter[1]) ?></label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="gl_<?= bx_h($filter[0]) ?>" name="<?= bx_h($filter[0]) ?>" maxlength="180" value="<?= bx_h((string) $generalLedgerFilters[$filter[0]]) ?>"></div><?php endforeach; ?>
                <input type="hidden" name="include_reversals" value="0"><label class="inline-flex items-center gap-2 py-1 text-sm"><input type="checkbox" name="include_reversals" value="1" <?= !empty($generalLedgerFilters['include_reversals']) ? 'checked' : '' ?>>Include reversal entries</label>
                <div class="grid grid-cols-2 gap-2 border-t pt-4"><button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Apply</button><a class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=accounting-finance&amp;section=general-ledger">Reset</a></div>
            </form>
        </div>
    </aside>
</div>
