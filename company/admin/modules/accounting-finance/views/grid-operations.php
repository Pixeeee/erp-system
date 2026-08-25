<?php
/** Spreadsheet operation variables are prepared by bootstrap/controller.php. */
$financeOperationColumnRegistry = is_array($financeGridDisplayRegistry ?? null) ? $financeGridDisplayRegistry : $financeGridColumnRegistry;
$financeDefaultColumnKeys = array_keys($financeOperationColumnRegistry);
$financeViewColumns = is_array($activeFinanceGridView['columns'] ?? null) ? $activeFinanceGridView['columns'] : [];
if (!$financeViewColumns) {
    foreach ($financeDefaultColumnKeys as $index => $columnKey) {
        $financeViewColumns[] = ['key' => $columnKey, 'visible' => true, 'width' => 160, 'order' => ($index + 1) * 10];
    }
}
$financeViewSort = is_array($activeFinanceGridView['sort'] ?? null) ? $activeFinanceGridView['sort'] : [];
$financeViewFilters = is_array($activeFinanceGridView['filters'] ?? null) ? $activeFinanceGridView['filters'] : [];
$financeViewColumnsJson = json_encode($financeViewColumns, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
$financeViewSortJson = json_encode($financeViewSort, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
$financeViewFiltersJson = json_encode($financeViewFilters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
$financeNumericFields = yovel_admin_finance_grid_numeric_fields($activeAccountingFinanceSchema);
?>
<section class="grid gap-2 border-b pb-3" data-finance-grid-operations>
    <div class="flex items-center justify-between gap-2">
        <p class="text-xs font-semibold uppercase text-muted-foreground">Spreadsheet Operations</p>
        <span class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold"><?= count($financeGridViews) ?> views</span>
    </div>
    <?php if ($financeGridViews): ?>
        <select class="h-9 rounded-md border bg-background px-3 text-sm" aria-label="Saved Finance view" data-finance-grid-view-select>
            <option value="./?view=accounting-finance&amp;section=<?= bx_h($activeAccountingFinanceSection) ?>">Default view</option>
            <?php foreach ($financeGridViews as $view): ?>
                <option value="./?view=accounting-finance&amp;section=<?= bx_h($activeAccountingFinanceSection) ?>&amp;grid_view=<?= bx_h((string) $view['grid_view_key']) ?>" <?= (string) ($activeFinanceGridView['grid_view_key'] ?? '') === (string) $view['grid_view_key'] ? 'selected' : '' ?>><?= bx_h((string) $view['view_title']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <div class="grid grid-cols-2 gap-2">
        <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-2 text-xs font-medium hover:bg-muted" data-record-modal-open="yovel-finance-grid-view-modal" data-finance-grid-view-open><span class="material-symbols-rounded text-base" aria-hidden="true">view_column</span>View</button>
        <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-2 text-xs font-medium hover:bg-muted" data-record-modal-open="yovel-finance-grid-formula-modal" data-finance-grid-formula-open><span class="material-symbols-rounded text-base" aria-hidden="true">function</span>Formula</button>
        <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-2 text-xs font-medium hover:bg-muted" data-finance-grid-export><span class="material-symbols-rounded text-base" aria-hidden="true">download</span>Export</button>
        <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-2 text-xs font-medium hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50" data-record-modal-open="yovel-finance-import-modal" data-finance-grid-import-open <?= $activeAccountingFinanceSection === 'chart-of-accounts' ? '' : 'disabled' ?>><span class="material-symbols-rounded text-base" aria-hidden="true">upload</span>Import</button>
    </div>
    <?php if ($financeGridFormulas): ?>
        <div class="grid gap-1 pt-1">
            <?php foreach ($financeGridFormulas as $formula): ?>
                <div class="flex items-center justify-between gap-2 text-xs"><span class="truncate"><?= bx_h((string) $formula['formula_label']) ?></span><code class="truncate text-[10px] text-muted-foreground"><?= bx_h((string) $formula['formula_expression']) ?></code></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div id="yovel-finance-grid-view-modal" data-record-modal<?= $financeModalStateAttributes('yovel-finance-grid-view-modal') ?> class="yovel-finance-operation-modal fixed inset-0 z-[90] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-finance-grid-view-title" hidden data-finance-grid-view-modal>
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-4xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg">
        <header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><h3 id="yovel-finance-grid-view-title" class="text-base font-semibold">Customize Spreadsheet View</h3><p class="mt-1 text-sm text-muted-foreground">Save columns, widths, sorting, filters, and grouping for this Finance section.</p></div><button type="button" class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-muted" aria-label="Close spreadsheet view" data-record-modal-close data-finance-operation-close><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <div class="yovel-modal-scroll min-h-0 flex-1 overflow-auto p-6">
            <form method="post" data-confirm-submit class="grid gap-5" data-finance-grid-view-form>
                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                <input type="hidden" name="action" value="save_finance_grid_view">
                <input type="hidden" name="section" value="<?= bx_h($activeAccountingFinanceSection) ?>">
                <input type="hidden" name="grid_view_key" value="<?= bx_h((string) ($activeFinanceGridView['grid_view_key'] ?? '')) ?>">
                <input type="hidden" name="columns_json" value="<?= bx_h($financeViewColumnsJson) ?>" data-finance-grid-columns-json>
                <input type="hidden" name="sort_json" value="<?= bx_h($financeViewSortJson) ?>" data-finance-grid-sort-json>
                <input type="hidden" name="filter_json" value="<?= bx_h($financeViewFiltersJson) ?>" data-finance-grid-filter-json>
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="finance_grid_view_title">View name</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_grid_view_title" name="view_title" maxlength="160" required value="<?= bx_h((string) ($activeFinanceGridView['view_title'] ?? 'My Finance View')) ?>"></div>
                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="finance_grid_search_text">Saved search</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_grid_search_text" name="search_text" maxlength="240" value="<?= bx_h((string) ($activeFinanceGridView['search_text'] ?? '')) ?>"></div>
                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="finance_grid_group_field">Group by</label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_grid_group_field" name="group_field"><option value="">No grouping</option><?php foreach ($financeOperationColumnRegistry as $column): ?><option value="<?= bx_h((string) $column['key']) ?>" <?= (string) ($activeFinanceGridView['group_field'] ?? '') === (string) $column['key'] ? 'selected' : '' ?>><?= bx_h((string) $column['label']) ?></option><?php endforeach; ?></select></div>
                    <label class="inline-flex items-end gap-2 pb-2 text-sm"><input type="checkbox" name="is_default" value="1" <?= (int) ($activeFinanceGridView['is_default'] ?? 0) === 1 ? 'checked' : '' ?>>Use as the default view</label>
                </div>
                <section class="grid gap-2"><div><h4 class="text-sm font-semibold">Columns</h4><p class="mt-1 text-xs text-muted-foreground">Show, resize, and reorder fields. Dragging and arrow controls stay synchronized.</p></div><div class="grid divide-y rounded-md border" data-finance-grid-column-list><?php foreach ($financeViewColumns as $column): ?><?php $meta = $financeOperationColumnRegistry[(string) $column['key']] ?? ['label' => (string) $column['key']]; ?><div class="yovel-finance-grid-column grid gap-2 p-3" draggable="true" data-finance-grid-column data-column-key="<?= bx_h((string) $column['key']) ?>"><input type="checkbox" <?= !empty($column['visible']) ? 'checked' : '' ?> data-finance-grid-column-toggle aria-label="Show <?= bx_h((string) $meta['label']) ?>"><span class="text-sm font-medium"><?= bx_h((string) $meta['label']) ?></span><input class="h-8 rounded-md border bg-background px-2 text-xs" type="number" min="80" max="640" value="<?= (int) ($column['width'] ?? 160) ?>" data-finance-grid-column-width aria-label="<?= bx_h((string) $meta['label']) ?> width"><span class="flex gap-1"><button type="button" class="size-7 rounded-md hover:bg-muted" data-finance-grid-column-move="up" aria-label="Move <?= bx_h((string) $meta['label']) ?> up">↑</button><button type="button" class="size-7 rounded-md hover:bg-muted" data-finance-grid-column-move="down" aria-label="Move <?= bx_h((string) $meta['label']) ?> down">↓</button></span></div><?php endforeach; ?></div></section>
                <div class="flex justify-end border-t pt-4"><button type="submit" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"><span class="material-symbols-rounded text-base" aria-hidden="true">save</span>Save View</button></div>
            </form>
        </div>
        <footer class="flex items-center justify-between gap-3 border-t px-5 py-4"><span class="text-xs text-muted-foreground">View settings are company- and section-scoped.</span><button type="button" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted" data-record-modal-close data-finance-operation-close>Cancel</button></footer>
    </section>
</div>

<div id="yovel-finance-grid-formula-modal" data-record-modal<?= $financeModalStateAttributes('yovel-finance-grid-formula-modal') ?> class="yovel-finance-operation-modal fixed inset-0 z-[90] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-finance-formula-title" hidden data-finance-grid-formula-modal>
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-2xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg">
        <header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><h3 id="yovel-finance-formula-title" class="text-base font-semibold">Calculated Column</h3><p class="mt-1 text-sm text-muted-foreground">Build a controlled display formula without changing accounting records.</p></div><button type="button" class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-muted" aria-label="Close calculated column" data-record-modal-close data-finance-operation-close><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <div class="yovel-modal-scroll min-h-0 flex-1 overflow-auto p-6">
            <form method="post" data-confirm-submit class="grid gap-4">
                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="save_finance_grid_formula"><input type="hidden" name="section" value="<?= bx_h($activeAccountingFinanceSection) ?>"><input type="hidden" name="grid_view_key" value="<?= bx_h((string) ($activeFinanceGridView['grid_view_key'] ?? '')) ?>">
                <div class="grid gap-3 sm:grid-cols-2"><div class="grid gap-1.5"><label class="text-xs font-medium" for="finance_formula_label">Column label</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_formula_label" name="formula_label" maxlength="160" required></div><div class="grid gap-1.5"><label class="text-xs font-medium" for="finance_formula_code">Column key</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_formula_code" name="formula_code" pattern="[a-z][a-z0-9_]{1,79}" placeholder="net_amount" required></div></div>
                <div class="grid gap-1.5"><label class="text-xs font-medium" for="finance_formula_expression">Formula</label><input class="h-9 rounded-md border bg-background px-3 font-mono text-sm" id="finance_formula_expression" name="formula_expression" maxlength="500" placeholder="IF([tax_rate] > 0, [tax_rate] * 2, 0)" required><p class="text-xs leading-5 text-muted-foreground">Fields: <?= bx_h(implode(', ', array_map(static fn (string $field): string => '[' . $field . ']', $financeNumericFields))) ?>. Functions: SUM, AVERAGE, MIN, MAX, COUNT, IF.</p></div>
                <div class="grid gap-3 sm:grid-cols-3"><div class="grid gap-1.5"><label class="text-xs font-medium" for="finance_formula_format">Format</label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_formula_format" name="output_format"><option>NUMBER</option><option>CURRENCY</option><option>PERCENT</option></select></div><div class="grid gap-1.5"><label class="text-xs font-medium" for="finance_formula_precision">Precision</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_formula_precision" name="decimal_precision" type="number" min="0" max="6" value="2"></div><div class="grid gap-1.5"><label class="text-xs font-medium" for="finance_formula_order">Order</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="finance_formula_order" name="sort_order" type="number" min="0" value="100"></div></div>
                <div class="flex justify-end border-t pt-4"><button type="submit" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"><span class="material-symbols-rounded text-base" aria-hidden="true">save</span>Save Column</button></div>
            </form>
        </div>
        <footer class="flex items-center justify-between gap-3 border-t px-5 py-4"><span class="text-xs text-muted-foreground">Formulas affect display only.</span><button type="button" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted" data-record-modal-close data-finance-operation-close>Cancel</button></footer>
    </section>
</div>

<div id="yovel-finance-import-modal" data-record-modal<?= $financeModalStateAttributes('yovel-finance-import-modal') ?> class="yovel-finance-operation-modal fixed inset-0 z-[90] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-finance-import-title" hidden data-finance-grid-import-modal>
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-3xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg">
        <header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><h3 id="yovel-finance-import-title" class="text-base font-semibold">Import Chart of Accounts</h3><p class="mt-1 text-sm text-muted-foreground">Select a CSV, inspect its headers and rows, then map it before a confirmed import.</p></div><button type="button" class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-muted" aria-label="Close CSV import" data-record-modal-close data-finance-operation-close><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <div class="yovel-modal-scroll min-h-0 flex-1 overflow-auto p-6">
            <form method="post" class="grid gap-4" data-confirm-submit data-finance-grid-import-form>
                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                <input type="hidden" name="action" value="import_finance_accounts">
                <input type="hidden" name="section" value="chart-of-accounts">
                <input type="hidden" name="rows_json" value="[]" data-finance-grid-import-json>
                <input class="block w-full rounded-md border bg-background p-3 text-sm" type="file" accept=".csv,text/csv" data-finance-grid-import-file>
                <div class="rounded-md border border-dashed p-4 text-sm text-muted-foreground" data-finance-grid-import-preview>Select a CSV file up to 2 MB to preview its first 20 rows.</div>
                <button type="submit" class="inline-flex h-9 w-fit items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground disabled:opacity-50" disabled data-finance-grid-import-confirm>Import Valid Rows</button>
            </form>
        </div>
        <footer class="flex items-center justify-between gap-3 border-t px-5 py-4"><span class="text-xs text-muted-foreground">Imports are all-or-nothing.</span><button type="button" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted" data-record-modal-close data-finance-operation-close>Cancel</button></footer>
    </section>
</div>
