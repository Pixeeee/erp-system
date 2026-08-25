<?php
/** Workspace variables are prepared by bootstrap/controller.php. */
?>
                                <?php
                                    $accountSchemaFields = is_array($activeAccountingFinanceSchema['fields'] ?? null) ? $activeAccountingFinanceSchema['fields'] : [];
                                    $accountFieldMap = [];
                                    foreach ($accountSchemaFields as $field) {
                                        $accountFieldMap[(string) ($field['key'] ?? '')] = $field;
                                    }
                                    $accountLabel = static function (string $key, string $fallback) use ($accountFieldMap): string {
                                        return (string) ($accountFieldMap[$key]['label'] ?? $fallback);
                                    };
                                    $accountVisible = static function (string $key) use ($accountFieldMap): bool {
                                        return filter_var($accountFieldMap[$key]['visible'] ?? true, FILTER_VALIDATE_BOOLEAN);
                                    };
                                    $accountRequired = static function (string $key) use ($accountFieldMap): string {
                                        return filter_var($accountFieldMap[$key]['required'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'required' : '';
                                    };
                                    $accountParentOptions = array_values(array_filter($accountingAccounts, static fn (array $account): bool => (int) ($account['is_group'] ?? 0) === 1 && (string) ($account['account_status'] ?? '') !== 'DELETED'));
                                    $accountRootCounts = array_count_values(array_map(static fn (array $account): string => (string) ($account['root_type'] ?? 'UNSET'), $accountingAccounts));
                                    $financeGridDisplayRegistry = $financeGridColumnRegistry;
                                    $financeGridFormulaAsts = [];
                                    foreach ($financeGridFormulas as $formula) {
                                        $formulaColumnKey = 'formula_' . (string) $formula['formula_code'];
                                        $financeGridDisplayRegistry[$formulaColumnKey] = [
                                            'key' => $formulaColumnKey,
                                            'label' => (string) $formula['formula_label'],
                                            'type' => 'number',
                                        ];
                                        try {
                                            $financeGridFormulaAsts[$formulaColumnKey] = yovel_admin_finance_formula_parse(
                                                (string) $formula['formula_expression'],
                                                yovel_admin_finance_grid_numeric_fields($activeAccountingFinanceSchema)
                                            );
                                        } catch (Throwable) {
                                            $financeGridFormulaAsts[$formulaColumnKey] = null;
                                        }
                                    }
                                    $financeGridDisplayColumns = is_array($activeFinanceGridView['columns'] ?? null) ? $activeFinanceGridView['columns'] : [];
                                    if (!$financeGridDisplayColumns) {
                                        foreach (array_keys($financeGridDisplayRegistry) as $columnIndex => $columnKey) {
                                            $financeGridDisplayColumns[] = ['key' => $columnKey, 'visible' => true, 'width' => 160, 'order' => ($columnIndex + 1) * 10];
                                        }
                                    }
                                    $financeGridDisplayColumns = array_values(array_filter(
                                        $financeGridDisplayColumns,
                                        static fn (array $column): bool => !empty($column['visible']) && isset($financeGridDisplayRegistry[(string) ($column['key'] ?? '')])
                                    ));
                                    $financeGridMinWidth = array_sum(array_map(
                                        static fn (array $column): int => max(80, min(640, (int) ($column['width'] ?? 160))),
                                        $financeGridDisplayColumns
                                    ));
                                    $financeGridAccountValue = static function (array $account, string $columnKey): string {
                                        $value = $account[$columnKey] ?? '';
                                        if (in_array($columnKey, ['is_group', 'freeze_account', 'include_in_gross'], true)) {
                                            return (int) $value === 1 ? 'Yes' : 'No';
                                        }
                                        return is_scalar($value) ? (string) $value : '';
                                    };
                                ?>
                                <div class="yovel-finance-workspace grid min-h-0 gap-3">
                                    <div class="flex min-h-0 items-center">
                                        <span class="inline-flex w-fit items-center rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= bx_h($viewEyebrow) ?></span>
                                    </div>
                                    <div class="yovel-finance-scroll-shell yovel-finance-sticky-nav">
                                        <nav class="yovel-finance-scroll flex gap-2 overflow-x-auto px-0.5 pb-2" aria-label="Accounting Finance sections">
                                            <?php foreach ($accountingFinanceSections as $sectionKey => $sectionMeta): ?>
                                                <a class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $activeAccountingFinanceSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=accounting-finance&amp;section=<?= bx_h((string) $sectionKey) ?>">
                                                    <span aria-hidden="true"><?= bx_h((string) $sectionMeta['icon']) ?></span><?= bx_h((string) $sectionMeta['label']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </nav>
                                    </div>

                                    <?php if ($activeAccountingFinanceSection === 'dashboard'): ?>
                                        <?php require __DIR__ . '/dashboard.php'; ?>
                                    <?php elseif ($activeAccountingFinanceSection === 'general-ledger'): ?>
                                        <?php require __DIR__ . '/general-ledger.php'; ?>
                                    <?php elseif ($activeAccountingFinanceSection === 'chart-of-accounts'): ?>
                                        <div class="yovel-hr-two-panel grid min-h-0 gap-4 xl:grid-cols-[minmax(0,12fr)_minmax(16rem,4fr)]">
                                            <section class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
                                                <div class="yovel-finance-sticky-panel-header border-b bg-card px-5 py-4">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <h3 class="text-base font-semibold tracking-normal">Chart of accounts</h3>
                                                            <p class="mt-1 text-sm leading-6 text-muted-foreground">Account tree setup for <?= bx_h($companyName) ?>. Balances are setup placeholders until posting workflows are built.</p>
                                                        </div>
                                                        <button type="button" id="yovel-account-modal-open" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">New Account</button>
                                                    </div>
                                                </div>
                                                <div class="grid gap-3 border-b p-4">
                                                    <div class="yovel-finance-overview-grid grid gap-2">
                                                        <div class="rounded-md bg-muted/40 p-3">
                                                            <p class="text-xs font-medium text-muted-foreground">Accounts</p>
                                                            <p class="mt-1 text-xl font-semibold tracking-normal"><?= count($accountingAccounts) ?></p>
                                                        </div>
                                                        <div class="rounded-md bg-muted/40 p-3">
                                                            <p class="text-xs font-medium text-muted-foreground">Active</p>
                                                            <p class="mt-1 text-xl font-semibold tracking-normal"><?= count($activeAccountingAccounts) ?></p>
                                                        </div>
                                                        <div class="rounded-md bg-muted/40 p-3">
                                                            <p class="text-xs font-medium text-muted-foreground">Groups</p>
                                                            <p class="mt-1 text-xl font-semibold tracking-normal"><?= count(array_filter($accountingAccounts, static fn (array $account): bool => (int) ($account['is_group'] ?? 0) === 1)) ?></p>
                                                        </div>
                                                        <div class="rounded-md bg-muted/40 p-3">
                                                            <p class="text-xs font-medium text-muted-foreground">Ledger</p>
                                                            <p class="mt-1 text-xl font-semibold tracking-normal"><?= count(array_filter($accountingAccounts, static fn (array $account): bool => (int) ($account['is_group'] ?? 0) !== 1)) ?></p>
                                                        </div>
                                                        <div class="rounded-md bg-muted/40 p-3">
                                                            <p class="text-xs font-medium text-muted-foreground">Frozen</p>
                                                            <p class="mt-1 text-xl font-semibold tracking-normal"><?= count($frozenAccountingAccounts) ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="grid gap-3 border-b p-4 lg:grid-cols-[minmax(0,1fr)_auto]" data-finance-grid-controls>
                                                    <div class="grid gap-2 sm:grid-cols-4">
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Search accounts" aria-label="Search accounts" value="<?= bx_h((string) ($activeFinanceGridView['search_text'] ?? '')) ?>" data-finance-grid-search>
                                                        <select class="h-9 rounded-md border bg-background px-3 text-sm" aria-label="Root type filter" data-finance-grid-filter="root_type">
                                                            <option value="">All root types</option>
                                                            <?php foreach (['ASSET', 'LIABILITY', 'INCOME', 'EXPENSE', 'EQUITY'] as $rootType): ?>
                                                                <option value="<?= bx_h($rootType) ?>" <?= (string) (($activeFinanceGridView['filters']['root_type'] ?? '')) === $rootType ? 'selected' : '' ?>><?= bx_h($rootType) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <select class="h-9 rounded-md border bg-background px-3 text-sm" aria-label="Status filter" data-finance-grid-filter="account_status">
                                                            <option value="">All statuses</option>
                                                            <?php foreach (['DRAFT', 'ACTIVE', 'FROZEN', 'INACTIVE'] as $status): ?>
                                                                <option value="<?= bx_h($status) ?>" <?= (string) (($activeFinanceGridView['filters']['account_status'] ?? '')) === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Account type" aria-label="Filter account type" value="<?= bx_h((string) ($activeFinanceGridView['filters']['account_type'] ?? '')) ?>" data-finance-grid-filter="account_type">
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="inline-flex h-9 items-center rounded-md border bg-secondary px-3 text-sm font-medium text-secondary-foreground" data-finance-grid-count><?= count($accountingAccounts) ?> rows</span>
                                                        <button type="button" class="inline-flex size-9 items-center justify-center rounded-md border bg-background hover:bg-muted" aria-label="Export visible rows" title="Export visible rows" data-finance-grid-export><span class="material-symbols-rounded text-base" aria-hidden="true">download</span></button>
                                                    </div>
                                                </div>
                                                <div class="yovel-hr-panel-body overflow-auto" data-finance-grid data-grid-group="<?= bx_h((string) ($activeFinanceGridView['group_field'] ?? '')) ?>" data-grid-sort='<?= bx_h(json_encode($activeFinanceGridView['sort'] ?? [], JSON_UNESCAPED_SLASHES) ?: '[]') ?>'>
                                                        <table class="w-full table-fixed text-left text-sm" style="min-width: <?= max(480, $financeGridMinWidth + 112) ?>px">
                                                            <thead class="yovel-finance-sticky-table-header border-b bg-card text-xs text-muted-foreground">
                                                                <tr>
                                                                    <?php foreach ($financeGridDisplayColumns as $column): ?><?php $columnKey = (string) $column['key']; $columnMeta = $financeGridDisplayRegistry[$columnKey]; ?>
                                                                        <th scope="col" class="px-4 py-3 font-medium" style="width: <?= max(80, min(640, (int) ($column['width'] ?? 160))) ?>px" data-column-key="<?= bx_h($columnKey) ?>"><button type="button" class="inline-flex items-center gap-1 hover:text-foreground" data-finance-grid-sort="<?= bx_h($columnKey) ?>"><?= bx_h((string) $columnMeta['label']) ?><span class="material-symbols-rounded text-sm" aria-hidden="true">unfold_more</span></button></th>
                                                                    <?php endforeach; ?>
                                                                    <th scope="col" class="w-28 px-4 py-3 font-medium">Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y">
                                                                <?php foreach ($accountingAccounts as $account): ?>
                                                                    <?php $accountKey = (string) $account['account_key']; ?>
                                                                    <tr data-finance-grid-row data-root-type="<?= bx_h((string) $account['root_type']) ?>" data-account-status="<?= bx_h((string) $account['account_status']) ?>" data-account-type="<?= bx_h((string) ($account['account_type'] ?? '')) ?>">
                                                                        <?php foreach ($financeGridDisplayColumns as $column): ?><?php $columnKey = (string) $column['key']; ?>
                                                                            <?php if (str_starts_with($columnKey, 'formula_')): ?>
                                                                                <?php $formula = yovel_admin_find_record($financeGridFormulas, 'formula_code', substr($columnKey, 8)); $ast = $financeGridFormulaAsts[$columnKey] ?? null; $result = ($formula && $ast) ? yovel_admin_finance_formula_evaluate($ast, $account, $accountingAccounts) : ['ok' => false, 'error' => 'FORMULA_ERROR']; $cellValue = $formula ? yovel_admin_finance_formula_format($formula, $result) : '#FORMULA_ERROR'; ?>
                                                                            <?php else: ?>
                                                                                <?php $cellValue = $financeGridAccountValue($account, $columnKey); ?>
                                                                            <?php endif; ?>
                                                                            <td class="truncate px-4 py-3 text-xs text-muted-foreground" data-finance-grid-cell data-column-key="<?= bx_h($columnKey) ?>" data-value="<?= bx_h($cellValue) ?>" title="<?= bx_h($cellValue) ?>"><?= bx_h($cellValue !== '' ? str_replace('_', ' ', $cellValue) : '—') ?></td>
                                                                        <?php endforeach; ?>
                                                                        <td class="px-4 py-4">
                                                                            <a class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-muted" href="./?view=accounting-finance&amp;section=chart-of-accounts&amp;edit=<?= bx_h($accountKey) ?>" aria-label="Edit <?= bx_h((string) $account['account_name']) ?>" title="Edit account"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></a>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                                <tr <?= $accountingAccounts ? 'hidden' : '' ?> data-finance-grid-empty><td class="px-5 py-8 text-center text-sm text-muted-foreground" colspan="<?= count($financeGridDisplayColumns) + 1 ?>">No accounts yet. Use Add Account to create the first record.</td></tr>
                                                                <tr hidden data-finance-grid-filtered-empty><td class="px-5 py-8 text-center text-sm text-muted-foreground" colspan="<?= count($financeGridDisplayColumns) + 1 ?>">No accounts match the current filters.</td></tr>
                                                            </tbody>
                                                        </table>
                                                </div>
                                            </section>

                                            <aside class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
                                                <div class="yovel-finance-sticky-panel-header border-b bg-card px-5 py-4">
                                                    <h3 class="text-base font-semibold tracking-normal">Features / Functions</h3>
                                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">Create, customize, organize, and prepare account setup.</p>
                                                </div>
                                                <div id="yovel-account-widget-board" class="yovel-hr-panel-body grid content-start gap-3 p-5" aria-label="Accounting widgets">
                                                    <button type="button" id="yovel-account-modal-open-secondary" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Add Account</button>
                                                    <button type="button" id="yovel-account-builder-modal-open" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Customize Form</button>
                                                    <?php require __DIR__ . '/grid-operations.php'; ?>
                                                    <?php foreach ([
                                                        ['key' => 'overview-cards', 'title' => 'Overview cards', 'meta' => 'Incoming, outgoing, payments, and bank cards'],
                                                        ['key' => 'setup-links', 'title' => 'Setup links', 'meta' => 'Chart, cost centers, dimensions, currency setup'],
                                                        ['key' => 'opening-closing', 'title' => 'Opening / closing', 'meta' => 'Importer, accounting period, fiscal close'],
                                                        ['key' => 'tax-budgeting', 'title' => 'Tax and budgeting', 'meta' => 'Tax templates, budgets, and controls'],
                                                        ['key' => 'form-builder', 'title' => 'Form builder', 'meta' => count($accountSchemaFields) . ' fields for this section'],
                                                    ] as $widget): ?>
                                                        <section class="yovel-widget-item rounded-md bg-muted/40 p-3 transition-colors hover:bg-muted/70" draggable="true" data-widget-key="<?= bx_h((string) $widget['key']) ?>" aria-grabbed="false">
                                                            <div class="flex items-start justify-between gap-3">
                                                                <div class="min-w-0">
                                                                    <p class="text-sm font-semibold"><?= bx_h((string) $widget['title']) ?></p>
                                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $widget['meta']) ?></p>
                                                                </div>
                                                                <div class="flex shrink-0 items-center gap-1">
                                                                    <button type="button" class="yovel-widget-move-up inline-flex size-7 items-center justify-center rounded-md hover:bg-background" aria-label="Move <?= bx_h((string) $widget['title']) ?> up">↑</button>
                                                                    <button type="button" class="yovel-widget-move-down inline-flex size-7 items-center justify-center rounded-md hover:bg-background" aria-label="Move <?= bx_h((string) $widget['title']) ?> down">↓</button>
                                                                    <span class="inline-flex size-7 cursor-grab items-center justify-center rounded-md text-muted-foreground" aria-hidden="true">☰</span>
                                                                </div>
                                                            </div>
                                                        </section>
                                                    <?php endforeach; ?>
                                                </div>
                                            </aside>
                                        </div>

                                        <div id="yovel-account-modal" class="yovel-account-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-account-modal-title" aria-describedby="yovel-account-modal-description" <?= $editAccountingAccount ? '' : 'hidden' ?>>
                                            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-6xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                                <div class="yovel-finance-sticky-modal-header flex shrink-0 items-start justify-between gap-4 border-b bg-card px-5 py-4">
                                                    <div>
                                                        <h3 id="yovel-account-modal-title" class="text-base font-semibold tracking-normal"><?= $editAccountingAccount ? 'Edit Account' : 'Add Account' ?></h3>
                                                        <p id="yovel-account-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Account setup follows the active customizable Accounting/Finance form.</p>
                                                    </div>
                                                    <button type="button" id="yovel-account-modal-close" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close account form">×</button>
                                                </div>
                                                <div class="yovel-hr-panel-body grid gap-4 p-5">
                                                    <form id="yovel-account-form" method="post" data-confirm-submit class="grid gap-4">
                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="save_accounting_account">
                                                        <input type="hidden" name="section" value="chart-of-accounts">
                                                        <input type="hidden" name="account_key" value="<?= bx_h((string) ($editAccountingAccount['account_key'] ?? '')) ?>">
                                                        <div class="grid gap-3 sm:grid-cols-3">
                                                            <?php if ($accountVisible('account_code')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="account_code"><?= bx_h($accountLabel('account_code', 'Account code')) ?></label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="account_code" name="account_code" value="<?= bx_h((string) ($editAccountingAccount['account_code'] ?? '')) ?>" pattern="[A-Za-z0-9_.-]{2,80}" maxlength="80" <?= $accountRequired('account_code') ?>></div><?php endif; ?>
                                                            <?php if ($accountVisible('account_number')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="account_number"><?= bx_h($accountLabel('account_number', 'Account number')) ?></label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="account_number" name="account_number" value="<?= bx_h((string) ($editAccountingAccount['account_number'] ?? '')) ?>" maxlength="80" <?= $accountRequired('account_number') ?>></div><?php endif; ?>
                                                            <?php if ($accountVisible('account_name')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="account_name"><?= bx_h($accountLabel('account_name', 'Account name')) ?></label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="account_name" name="account_name" value="<?= bx_h((string) ($editAccountingAccount['account_name'] ?? '')) ?>" maxlength="180" <?= $accountRequired('account_name') ?>></div><?php endif; ?>
                                                        </div>
                                                        <div class="grid gap-3 sm:grid-cols-3">
                                                            <?php if ($accountVisible('parent_account_key')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="parent_account_key"><?= bx_h($accountLabel('parent_account_key', 'Parent account')) ?></label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="parent_account_key" name="parent_account_key" <?= $accountRequired('parent_account_key') ?>><option value="">Root account</option><?php foreach ($accountParentOptions as $parentAccount): ?><?php if ((string) ($parentAccount['account_key'] ?? '') === (string) ($editAccountingAccount['account_key'] ?? '')) { continue; } ?><option value="<?= bx_h((string) $parentAccount['account_key']) ?>" <?= (string) ($editAccountingAccount['parent_account_key'] ?? '') === (string) $parentAccount['account_key'] ? 'selected' : '' ?>><?= bx_h((string) $parentAccount['account_name']) ?> · <?= bx_h((string) $parentAccount['root_type']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                                                            <?php if ($accountVisible('root_type')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="root_type"><?= bx_h($accountLabel('root_type', 'Root type')) ?></label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="root_type" name="root_type" <?= $accountRequired('root_type') ?>><?php foreach (['ASSET', 'LIABILITY', 'INCOME', 'EXPENSE', 'EQUITY'] as $rootType): ?><option value="<?= bx_h($rootType) ?>" <?= (string) ($editAccountingAccount['root_type'] ?? 'ASSET') === $rootType ? 'selected' : '' ?>><?= bx_h($rootType) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                                                            <?php if ($accountVisible('report_type')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="report_type"><?= bx_h($accountLabel('report_type', 'Report type')) ?></label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="report_type" name="report_type" <?= $accountRequired('report_type') ?>><?php foreach (['BALANCE_SHEET' => 'Balance Sheet', 'PROFIT_LOSS' => 'Profit/Loss'] as $reportType => $reportLabel): ?><option value="<?= bx_h($reportType) ?>" <?= (string) ($editAccountingAccount['report_type'] ?? 'BALANCE_SHEET') === $reportType ? 'selected' : '' ?>><?= bx_h($reportLabel) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                                                        </div>
                                                        <div class="grid gap-3 sm:grid-cols-3">
                                                            <?php if ($accountVisible('account_type')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="account_type"><?= bx_h($accountLabel('account_type', 'Account type')) ?></label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="account_type" name="account_type" value="<?= bx_h((string) ($editAccountingAccount['account_type'] ?? '')) ?>" maxlength="80" list="account_type_options" <?= $accountRequired('account_type') ?>><datalist id="account_type_options"><?php foreach (['Receivable','Payable','Bank','Cash','Tax','Stock','Expense Account','Income Account','Fixed Asset','Equity'] as $type): ?><option value="<?= bx_h($type) ?>"></option><?php endforeach; ?></datalist></div><?php endif; ?>
                                                            <?php if ($accountVisible('account_currency')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="account_currency"><?= bx_h($accountLabel('account_currency', 'Currency')) ?></label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="account_currency" name="account_currency" value="<?= bx_h((string) ($editAccountingAccount['account_currency'] ?? 'PHP')) ?>" maxlength="20" <?= $accountRequired('account_currency') ?>></div><?php endif; ?>
                                                            <?php if ($accountVisible('tax_rate')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="tax_rate"><?= bx_h($accountLabel('tax_rate', 'Tax rate')) ?></label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="tax_rate" name="tax_rate" type="number" step="0.0001" min="0" value="<?= bx_h((string) ($editAccountingAccount['tax_rate'] ?? '')) ?>" <?= $accountRequired('tax_rate') ?>></div><?php endif; ?>
                                                        </div>
                                                        <div class="grid gap-3 sm:grid-cols-4">
                                                            <?php if ($accountVisible('account_status')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="account_status"><?= bx_h($accountLabel('account_status', 'Status')) ?></label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="account_status" name="account_status" <?= $accountRequired('account_status') ?>><?php foreach (['DRAFT', 'ACTIVE', 'FROZEN', 'INACTIVE'] as $status): ?><option value="<?= bx_h($status) ?>" <?= (string) ($editAccountingAccount['account_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                                                            <?php if ($accountVisible('balance_must_be')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="balance_must_be"><?= bx_h($accountLabel('balance_must_be', 'Balance must be')) ?></label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="balance_must_be" name="balance_must_be" <?= $accountRequired('balance_must_be') ?>><?php foreach (['DEBIT', 'CREDIT', 'EITHER'] as $balanceType): ?><option value="<?= bx_h($balanceType) ?>" <?= (string) ($editAccountingAccount['balance_must_be'] ?? 'EITHER') === $balanceType ? 'selected' : '' ?>><?= bx_h($balanceType) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                                                            <?php if ($accountVisible('sort_order')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="sort_order"><?= bx_h($accountLabel('sort_order', 'Sort order')) ?></label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="sort_order" name="sort_order" type="number" min="0" value="<?= bx_h((string) ($editAccountingAccount['sort_order'] ?? '0')) ?>" <?= $accountRequired('sort_order') ?>></div><?php endif; ?>
                                                            <div class="grid gap-2 pt-6">
                                                                <?php if ($accountVisible('is_group')): ?><label class="inline-flex items-center gap-2 text-xs font-medium"><input type="checkbox" name="is_group" value="1" <?= (int) ($editAccountingAccount['is_group'] ?? 0) === 1 ? 'checked' : '' ?>><?= bx_h($accountLabel('is_group', 'Is group')) ?></label><?php endif; ?>
                                                                <?php if ($accountVisible('freeze_account')): ?><label class="inline-flex items-center gap-2 text-xs font-medium"><input type="checkbox" name="freeze_account" value="1" <?= (int) ($editAccountingAccount['freeze_account'] ?? 0) === 1 ? 'checked' : '' ?>><?= bx_h($accountLabel('freeze_account', 'Freeze account')) ?></label><?php endif; ?>
                                                                <?php if ($accountVisible('include_in_gross')): ?><label class="inline-flex items-center gap-2 text-xs font-medium"><input type="checkbox" name="include_in_gross" value="1" <?= (int) ($editAccountingAccount['include_in_gross'] ?? 0) === 1 ? 'checked' : '' ?>><?= bx_h($accountLabel('include_in_gross', 'Include in gross')) ?></label><?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php if ($accountVisible('account_notes')): ?><div class="grid gap-1.5"><label class="text-xs font-medium" for="account_notes"><?= bx_h($accountLabel('account_notes', 'Notes')) ?></label><textarea class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" id="account_notes" name="account_notes" <?= $accountRequired('account_notes') ?>><?= bx_h((string) ($editAccountingAccount['account_notes'] ?? '')) ?></textarea></div><?php endif; ?>

                                                        <?php foreach (yovel_admin_accounting_finance_custom_fields($activeAccountingFinanceSchema) as $field): ?>
                                                            <?php $fieldKey = (string) ($field['key'] ?? ''); $value = (string) ($accountCustomValues[$fieldKey] ?? ''); ?>
                                                            <div class="grid gap-1.5">
                                                                <label class="text-xs font-medium" for="custom_<?= bx_h($fieldKey) ?>"><?= bx_h((string) ($field['label'] ?? $fieldKey)) ?></label>
                                                                <?php if ((string) ($field['type'] ?? 'text') === 'textarea'): ?>
                                                                    <textarea class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" id="custom_<?= bx_h($fieldKey) ?>" name="custom_fields[<?= bx_h($fieldKey) ?>]" <?= filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'required' : '' ?>><?= bx_h($value) ?></textarea>
                                                                <?php elseif ((string) ($field['type'] ?? 'text') === 'select'): ?>
                                                                    <select class="h-9 rounded-md border bg-background px-3 text-sm" id="custom_<?= bx_h($fieldKey) ?>" name="custom_fields[<?= bx_h($fieldKey) ?>]" <?= filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'required' : '' ?>><option value=""></option><?php foreach (($field['options'] ?? []) as $option): ?><option value="<?= bx_h((string) $option) ?>" <?= $value === (string) $option ? 'selected' : '' ?>><?= bx_h((string) $option) ?></option><?php endforeach; ?></select>
                                                                <?php elseif ((string) ($field['type'] ?? 'text') === 'checkbox'): ?>
                                                                    <label class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm"><input type="checkbox" id="custom_<?= bx_h($fieldKey) ?>" name="custom_fields[<?= bx_h($fieldKey) ?>]" value="1" <?= $value === '1' ? 'checked' : '' ?>>Enabled</label>
                                                                <?php else: ?>
                                                                    <?php $inputType = ['date' => 'date', 'number' => 'number', 'email' => 'email'][$field['type'] ?? 'text'] ?? 'text'; ?>
                                                                    <input class="h-9 rounded-md border bg-background px-3 text-sm" id="custom_<?= bx_h($fieldKey) ?>" name="custom_fields[<?= bx_h($fieldKey) ?>]" type="<?= bx_h($inputType) ?>" value="<?= bx_h($value) ?>" <?= filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'required' : '' ?>>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Account</button>
                                                    </form>
                                                </div>
                                                <div class="flex shrink-0 items-center justify-between gap-3 border-t px-5 py-4">
                                                    <p class="text-xs leading-5 text-muted-foreground">Account and form changes require confirmation before saving.</p>
                                                    <button type="button" id="yovel-account-modal-cancel" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Cancel</button>
                                                </div>
                                            </section>
                                        </div>
                                    <?php else: ?>
                                        <div class="yovel-hr-two-panel grid min-h-0 gap-4 xl:grid-cols-[minmax(0,12fr)_minmax(16rem,4fr)]">
                                            <section class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
                                                <div class="yovel-finance-sticky-panel-header border-b bg-card px-5 py-4">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <h3 class="text-base font-semibold tracking-normal"><?= bx_h((string) ($activeAccountingFinanceMeta['label'] ?? 'Accounting/Finance Section')) ?></h3>
                                                        <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">Routed</span>
                                                    </div>
                                                    <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) ($activeAccountingFinanceMeta['description'] ?? 'Accounting/Finance section.')) ?></p>
                                                </div>
                                                <div class="yovel-hr-panel-body grid content-start gap-4 p-5">
                                                    <div class="yovel-finance-section-metrics grid gap-3">
                                                        <?php foreach ([
                                                            ['label' => 'Workspace', 'value' => (string) ($activeAccountingFinanceMeta['label'] ?? 'Section')],
                                                            ['label' => 'Form fields', 'value' => (string) count($accountSchemaFields)],
                                                            ['label' => 'Status', 'value' => 'Queued'],
                                                            ['label' => 'Pattern', 'value' => 'Modal input'],
                                                        ] as $metric): ?>
                                                            <div class="rounded-md bg-muted/40 p-3">
                                                                <p class="text-xs font-medium text-muted-foreground"><?= bx_h((string) $metric['label']) ?></p>
                                                                <p class="mt-1 truncate text-lg font-semibold tracking-normal"><?= bx_h((string) $metric['value']) ?></p>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="flex flex-wrap items-center justify-between gap-3 border-y py-3" data-finance-grid-controls>
                                                        <input class="h-9 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Search <?= bx_h(strtolower((string) ($activeAccountingFinanceMeta['label'] ?? 'records'))) ?>" aria-label="Search Finance records" value="<?= bx_h((string) ($activeFinanceGridView['search_text'] ?? '')) ?>" data-finance-grid-search>
                                                        <span class="inline-flex h-9 items-center rounded-md border bg-secondary px-3 text-sm font-medium text-secondary-foreground" data-finance-grid-count>0 rows</span>
                                                    </div>
                                                    <div class="overflow-auto" data-finance-grid data-grid-group="<?= bx_h((string) ($activeFinanceGridView['group_field'] ?? '')) ?>" data-grid-sort='<?= bx_h(json_encode($activeFinanceGridView['sort'] ?? [], JSON_UNESCAPED_SLASHES) ?: '[]') ?>'>
                                                        <table class="w-full table-fixed text-left text-sm" style="min-width: <?= max(480, $financeGridMinWidth) ?>px">
                                                            <thead class="yovel-finance-sticky-table-header border-b bg-card text-xs text-muted-foreground"><tr><?php foreach ($financeGridDisplayColumns as $column): ?><?php $columnKey = (string) $column['key']; $columnMeta = $financeGridDisplayRegistry[$columnKey]; ?><th scope="col" class="px-4 py-3 font-medium" style="width: <?= max(80, min(640, (int) ($column['width'] ?? 160))) ?>px" data-column-key="<?= bx_h($columnKey) ?>"><button type="button" class="inline-flex items-center gap-1 hover:text-foreground" data-finance-grid-sort="<?= bx_h($columnKey) ?>"><?= bx_h((string) $columnMeta['label']) ?><span class="material-symbols-rounded text-sm" aria-hidden="true">unfold_more</span></button></th><?php endforeach; ?></tr></thead>
                                                            <tbody><tr data-finance-grid-empty><td class="px-5 py-8 text-center text-sm text-muted-foreground" colspan="<?= max(1, count($financeGridDisplayColumns)) ?>">No <?= bx_h(strtolower((string) ($activeAccountingFinanceMeta['label'] ?? 'Finance'))) ?> records yet. The customizable schema and spreadsheet view are ready for this section.</td></tr><tr hidden data-finance-grid-filtered-empty><td colspan="<?= max(1, count($financeGridDisplayColumns)) ?>"></td></tr></tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </section>
                                            <aside class="yovel-hr-panel flex flex-col rounded-lg border bg-card">
                                                <div class="yovel-finance-sticky-panel-header border-b bg-card px-5 py-4">
                                                    <h3 class="text-base font-semibold tracking-normal">Features / Functions</h3>
                                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">Customize this section before its transactional form is built.</p>
                                                </div>
                                                <div class="yovel-hr-panel-body grid content-start gap-3 p-5">
                                                    <button type="button" id="yovel-account-builder-modal-open" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Customize Form</button>
                                                    <?php require __DIR__ . '/grid-operations.php'; ?>
                                                    <?php foreach ([
                                                        ['title' => 'ERPNext group', 'meta' => 'Setup, transaction, closing, tax, budget, or report area'],
                                                        ['title' => 'Form standard', 'meta' => count($accountSchemaFields) . ' editable fields for this link'],
                                                        ['title' => 'Input behavior', 'meta' => 'Add and edit flows open as modal popups'],
                                                        ['title' => 'Build order', 'meta' => 'Ready for the next one-by-one feature slice'],
                                                    ] as $widget): ?>
                                                        <section class="rounded-md bg-muted/40 p-3">
                                                            <p class="text-sm font-semibold"><?= bx_h((string) $widget['title']) ?></p>
                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $widget['meta']) ?></p>
                                                        </section>
                                                    <?php endforeach; ?>
                                                </div>
                                            </aside>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($activeAccountingFinanceSection === 'dashboard'): ?>
                                        <?php require __DIR__ . '/form-builder.php'; ?>
                                    <?php endif; ?>

                                    <?php if ($activeAccountingFinanceRecordType !== ''): ?>
                                    <div id="yovel-account-builder-modal" class="yovel-account-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-account-builder-modal-title" aria-describedby="yovel-account-builder-modal-description" <?= isset($_GET['customize']) ? '' : 'hidden' ?>>
                                        <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-6xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                            <div class="yovel-finance-sticky-modal-header flex shrink-0 items-start justify-between gap-4 border-b bg-card px-5 py-4">
                                                <div>
                                                    <h3 id="yovel-account-builder-modal-title" class="text-base font-semibold tracking-normal">Customize <?= bx_h((string) ($activeAccountingFinanceMeta['label'] ?? 'Accounting/Finance')) ?> Form</h3>
                                                    <p id="yovel-account-builder-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Adjust labels, sections, required fields, visibility, width, ordering, and custom fields for this Accounting/Finance link.</p>
                                                </div>
                                                <button type="button" id="yovel-account-builder-modal-close" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close form builder">×</button>
                                            </div>
                                            <div class="yovel-hr-panel-body grid gap-4 p-5">
                                                <form method="post" data-confirm-submit data-account-schema-form class="grid gap-4">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="save_accounting_form_schema">
                                                    <input type="hidden" name="section" value="<?= bx_h($activeAccountingFinanceSection) ?>">
                                                    <input type="hidden" name="record_type" value="<?= bx_h($activeAccountingFinanceRecordType) ?>">
                                                    <input type="hidden" name="active_schema_json" value="<?= bx_h(json_encode($activeAccountingFinanceSchema, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">
                                                    <div id="yovel-account-form-builder" class="grid gap-3">
                                                        <?php foreach ($accountSchemaFields as $field): ?>
                                                            <?php $fieldKey = (string) ($field['key'] ?? ''); $isSystemRequired = in_array($fieldKey, $activeAccountingFinanceSchema['requiredSystemFields'] ?? [], true); ?>
                                                            <section class="rounded-md bg-muted/40 p-4" draggable="true" data-account-field>
                                                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                                                    <p class="text-xs font-medium text-muted-foreground"><?= bx_h($fieldKey) ?> · <?= (string) ($field['storage'] ?? 'core') === 'custom' ? 'Custom field' : 'Core field' ?></p>
                                                                    <div class="flex items-center gap-1">
                                                                        <button type="button" class="yovel-account-field-up inline-flex size-7 items-center justify-center rounded-md hover:bg-background" aria-label="Move <?= bx_h((string) ($field['label'] ?? $fieldKey)) ?> up">↑</button>
                                                                        <button type="button" class="yovel-account-field-down inline-flex size-7 items-center justify-center rounded-md hover:bg-background" aria-label="Move <?= bx_h((string) ($field['label'] ?? $fieldKey)) ?> down">↓</button>
                                                                        <span class="inline-flex size-7 cursor-grab items-center justify-center rounded-md text-muted-foreground" aria-hidden="true">☰</span>
                                                                    </div>
                                                                </div>
                                                                <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_8rem_6rem]">
                                                                    <input type="hidden" name="fields[<?= bx_h($fieldKey) ?>][type]" value="<?= bx_h((string) ($field['type'] ?? 'text')) ?>">
                                                                    <input type="hidden" name="fields[<?= bx_h($fieldKey) ?>][storage]" value="<?= bx_h((string) ($field['storage'] ?? 'core')) ?>">
                                                                    <input class="h-8 rounded-md border bg-background px-2 text-xs" name="fields[<?= bx_h($fieldKey) ?>][label]" value="<?= bx_h((string) ($field['label'] ?? $fieldKey)) ?>" maxlength="120" required>
                                                                    <input class="h-8 rounded-md border bg-background px-2 text-xs" name="fields[<?= bx_h($fieldKey) ?>][section]" value="<?= bx_h((string) ($field['section'] ?? 'overview')) ?>" maxlength="80" required>
                                                                    <input class="h-8 rounded-md border bg-background px-2 text-xs" name="fields[<?= bx_h($fieldKey) ?>][sortOrder]" type="number" min="0" value="<?= bx_h((string) ($field['sortOrder'] ?? 0)) ?>">
                                                                </div>
                                                                <div class="mt-3 grid gap-2 sm:grid-cols-[8rem_minmax(0,1fr)_auto_auto]">
                                                                    <select class="h-8 rounded-md border bg-background px-2 text-xs" name="fields[<?= bx_h($fieldKey) ?>][width]"><?php foreach (['third', 'half', 'full'] as $width): ?><option value="<?= bx_h($width) ?>" <?= (string) ($field['width'] ?? 'half') === $width ? 'selected' : '' ?>><?= bx_h($width) ?></option><?php endforeach; ?></select>
                                                                    <textarea class="min-h-8 rounded-md border bg-background px-2 py-1.5 text-xs" name="fields[<?= bx_h($fieldKey) ?>][options_text]" placeholder="Options, one per line"><?= bx_h(implode("\n", array_map('strval', $field['options'] ?? []))) ?></textarea>
                                                                    <label class="inline-flex items-center gap-1 text-xs"><input type="checkbox" name="fields[<?= bx_h($fieldKey) ?>][required]" value="1" <?= filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' ?> <?= $isSystemRequired ? 'disabled' : '' ?>>Required</label>
                                                                    <label class="inline-flex items-center gap-1 text-xs"><input type="checkbox" name="fields[<?= bx_h($fieldKey) ?>][visible]" value="1" <?= filter_var($field['visible'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' ?> <?= $isSystemRequired ? 'disabled' : '' ?>>Visible</label>
                                                                    <?php if ($isSystemRequired): ?><input type="hidden" name="fields[<?= bx_h($fieldKey) ?>][required]" value="1"><input type="hidden" name="fields[<?= bx_h($fieldKey) ?>][visible]" value="1"><?php endif; ?>
                                                                </div>
                                                            </section>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <section class="grid gap-3 rounded-md bg-muted/40 p-4">
                                                        <p class="text-sm font-semibold">Add custom field</p>
                                                        <div class="grid gap-2 sm:grid-cols-3">
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" name="new_field_key" pattern="[a-z][a-z0-9_]{1,79}" placeholder="custom_field">
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" name="new_field_label" maxlength="120" placeholder="Custom Field">
                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" name="new_field_type"><?php foreach (['text', 'textarea', 'date', 'number', 'email', 'select', 'checkbox'] as $type): ?><option value="<?= bx_h($type) ?>"><?= bx_h($type) ?></option><?php endforeach; ?></select>
                                                        </div>
                                                        <div class="grid gap-2 sm:grid-cols-4">
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" name="new_field_section" value="custom" maxlength="80">
                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" name="new_field_width"><?php foreach (['half', 'third', 'full'] as $width): ?><option value="<?= bx_h($width) ?>"><?= bx_h($width) ?></option><?php endforeach; ?></select>
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" name="new_field_sort_order" type="number" min="0" value="500">
                                                            <label class="inline-flex items-center gap-2 text-xs font-medium"><input type="checkbox" name="new_field_required" value="1">Required</label>
                                                        </div>
                                                        <textarea class="min-h-16 rounded-md border bg-background px-3 py-2 text-sm" name="new_field_options" placeholder="Options, one per line"></textarea>
                                                    </section>
                                                    <div class="flex flex-wrap justify-between gap-2">
                                                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Form Layout</button>
                                                    </div>
                                                </form>
                                                <form method="post" data-confirm-submit>
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="reset_accounting_form_schema">
                                                    <input type="hidden" name="section" value="<?= bx_h($activeAccountingFinanceSection) ?>">
                                                    <input type="hidden" name="record_type" value="<?= bx_h($activeAccountingFinanceRecordType) ?>">
                                                    <button type="submit" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Restore Default Layout</button>
                                                </form>
                                            </div>
                                            <div class="flex shrink-0 items-center justify-between gap-3 border-t bg-card px-5 py-4">
                                                <p class="text-xs leading-5 text-muted-foreground">Form changes are company-scoped and require confirmation before saving.</p>
                                                <button type="button" id="yovel-account-builder-modal-cancel" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Cancel</button>
                                            </div>
                                        </section>
                                    </div>
                                    <?php endif; ?>
                                </div>
