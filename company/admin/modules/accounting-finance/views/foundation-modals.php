<?php
$financeFoundation = is_array($accountingFinanceData['foundation'] ?? null) ? $accountingFinanceData['foundation'] : [];
$financeChartTemplates = is_array($accountingFinanceData['chartTemplates'] ?? null) ? $accountingFinanceData['chartTemplates'] : [];
$foundationSelectedType = (string) ($financeFailedValues['master_type'] ?? 'account-category');
$foundationTypes = [
    'account-category'=>'Account Category', 'fiscal-year'=>'Fiscal Year', 'accounting-period'=>'Accounting Period',
    'finance-book'=>'Finance Book', 'monthly-distribution'=>'Monthly Distribution', 'cost-center-allocation'=>'Cost Center Allocation',
    'dimension-filter'=>'Dimension Filter', 'currency-exchange-setting'=>'Currency Exchange Setting', 'account-closing-balance'=>'Account Closing Balance',
];
$control = 'h-9 w-full rounded-md border bg-background px-3 text-sm';
?>
<div id="finance-foundation-modal" data-record-modal<?= $financeModalStateAttributes('finance-foundation-modal') ?> hidden class="yovel-finance-operation-modal fixed inset-0 z-50 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" style="z-index:120" role="dialog" aria-modal="true" aria-labelledby="finance-foundation-modal-title">
    <section class="flex max-h-[calc(100dvh-2rem)] w-full max-w-5xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
        <header class="yovel-finance-sticky-modal-header flex items-start justify-between gap-4 border-b bg-card px-5 py-4"><div><h3 id="finance-foundation-modal-title" class="font-semibold">New Finance Foundation Record</h3><p class="mt-1 text-sm text-muted-foreground">Company-scoped accounting structures and posting controls.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close"><span class="material-symbols-rounded text-base">close</span></button></header>
        <form method="post" data-confirm-submit data-finance-foundation-form class="contents">
            <div class="yovel-modal-scroll grid gap-5 overflow-y-auto p-5">
                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="save_finance_master"><input type="hidden" name="section" value="dashboard">
                <?php foreach (['account_category_key','fiscal_year_key','accounting_period_key','finance_book_key','monthly_distribution_key','cost_center_allocation_key','dimension_filter_key','exchange_setting_key','account_closing_balance_key'] as $stableKeyField): ?><input type="hidden" name="<?= bx_h($stableKeyField) ?>"><?php endforeach; ?>
                <label class="grid gap-1.5 text-sm">Record type<select class="<?= $control ?>" name="master_type" data-finance-foundation-type><?php foreach ($foundationTypes as $type=>$label): ?><option value="<?= bx_h($type) ?>" <?= $foundationSelectedType===$type?'selected':'' ?>><?= bx_h($label) ?></option><?php endforeach; ?></select></label>

                <fieldset data-finance-foundation-fields="account-category" class="grid gap-4 sm:grid-cols-2"><legend class="sr-only">Account Category</legend><label class="grid gap-1.5 text-sm">Category name<input class="<?= $control ?>" name="account_category_name" maxlength="180" required></label><label class="grid gap-1.5 text-sm">Root type<select class="<?= $control ?>" name="root_type"><option value="">Any</option><?php foreach (['ASSET','LIABILITY','INCOME','EXPENSE','EQUITY'] as $root): ?><option><?= $root ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm sm:col-span-2">Description<textarea class="min-h-20 rounded-md border bg-background px-3 py-2" name="description"></textarea></label></fieldset>

                <fieldset data-finance-foundation-fields="fiscal-year" class="grid gap-4 sm:grid-cols-3" hidden><legend class="sr-only">Fiscal Year</legend><label class="grid gap-1.5 text-sm">Year name<input class="<?= $control ?>" name="year_name" required></label><label class="grid gap-1.5 text-sm">Starts<input class="<?= $control ?>" type="date" name="year_start_date" required></label><label class="grid gap-1.5 text-sm">Ends<input class="<?= $control ?>" type="date" name="year_end_date" required></label><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_short_year" value="1">Short fiscal year</label></fieldset>

                <fieldset data-finance-foundation-fields="accounting-period" class="grid gap-4 sm:grid-cols-3" hidden><legend class="sr-only">Accounting Period</legend><label class="grid gap-1.5 text-sm">Period name<input class="<?= $control ?>" name="period_name" required></label><label class="grid gap-1.5 text-sm">Starts<input class="<?= $control ?>" type="date" name="start_date" required></label><label class="grid gap-1.5 text-sm">Ends<input class="<?= $control ?>" type="date" name="end_date" required></label><label class="grid gap-1.5 text-sm">Exempt role<input class="<?= $control ?>" name="exempted_role"></label><div class="grid gap-2 text-sm sm:col-span-2"><span>Closed documents</span><?php foreach (['SALES_INVOICE'=>'Sales Invoice','PURCHASE_INVOICE'=>'Purchase Invoice','JOURNAL_ENTRY'=>'Journal Entry','PAYMENT_ENTRY'=>'Payment Entry'] as $value=>$label): ?><label class="inline-flex items-center gap-2"><input type="checkbox" name="closed_document_types[]" value="<?= $value ?>"><?= $label ?></label><?php endforeach; ?></div></fieldset>

                <fieldset data-finance-foundation-fields="finance-book" class="grid gap-4 sm:grid-cols-2" hidden><legend class="sr-only">Finance Book</legend><label class="grid gap-1.5 text-sm">Finance Book name<input class="<?= $control ?>" name="finance_book_name" required></label><label class="inline-flex items-center gap-2 pt-6 text-sm"><input type="checkbox" name="is_default" value="1">Default Finance Book</label></fieldset>

                <fieldset data-finance-foundation-fields="monthly-distribution" class="grid gap-4" hidden><legend class="sr-only">Monthly Distribution</legend><div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm">Distribution name<input class="<?= $control ?>" name="distribution_name" required></label><label class="grid gap-1.5 text-sm">Fiscal Year<select class="<?= $control ?>" name="fiscal_year_key" required><option value="">Select</option><?php foreach ($financeFoundation['fiscalYears'] ?? [] as $year): ?><option value="<?= bx_h($year['fiscal_year_key']) ?>"><?= bx_h($year['year_name']) ?></option><?php endforeach; ?></select></label></div><div class="grid gap-3 sm:grid-cols-4"><?php foreach (range(1,12) as $month): ?><label class="grid gap-1 text-xs"><?= date('M',mktime(0,0,0,$month,1)) ?> %<input class="<?= $control ?>" type="number" min="0" max="100" step="0.000001" name="percentages[<?= $month ?>]" value="<?= $month===12?'8.333337':'8.333333' ?>" required></label><?php endforeach; ?></div></fieldset>

                <fieldset data-finance-foundation-fields="cost-center-allocation" class="grid gap-4" hidden><legend class="sr-only">Cost Center Allocation</legend><div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm">Main Cost Center<select class="<?= $control ?>" name="main_cost_center_key" required><option value="">Select</option><?php foreach ($accountingFinanceData['costCenters'] ?? [] as $center): ?><option value="<?= bx_h($center['cost_center_key']) ?>"><?= bx_h($center['cost_center_name']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm">Valid from<input class="<?= $control ?>" type="date" name="valid_from" required></label></div><?php foreach ([0,1] as $index): ?><div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm">Allocated Cost Center<select class="<?= $control ?>" name="allocations[<?= $index ?>][cost_center_key]" required><option value="">Select</option><?php foreach ($accountingFinanceData['costCenters'] ?? [] as $center): ?><option value="<?= bx_h($center['cost_center_key']) ?>"><?= bx_h($center['cost_center_name']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm">Percentage<input class="<?= $control ?>" type="number" min="0.000001" max="100" step="0.000001" name="allocations[<?= $index ?>][percentage]" value="50" required></label></div><?php endforeach; ?></fieldset>

                <fieldset data-finance-foundation-fields="dimension-filter" class="grid gap-4 sm:grid-cols-2" hidden><legend class="sr-only">Dimension Filter</legend><label class="grid gap-1.5 text-sm">Accounting Dimension<select class="<?= $control ?>" name="dimension_key" required><option value="">Select</option><?php foreach ($accountingFinanceData['accountingDimensions'] ?? [] as $dimension): ?><option value="<?= bx_h($dimension['dimension_key']) ?>"><?= bx_h($dimension['dimension_name']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm">Mode<select class="<?= $control ?>" name="allow_or_restrict"><option>ALLOW</option><option>RESTRICT</option></select></label><label class="grid gap-1.5 text-sm">Accounts<select class="min-h-32 rounded-md border bg-background p-2" name="account_keys[]" multiple required><?php foreach ($accountingAccounts as $account): ?><option value="<?= bx_h($account['account_key']) ?>"><?= bx_h($account['account_code'].' / '.$account['account_name']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm">Dimension values<select class="min-h-32 rounded-md border bg-background p-2" name="dimension_value_keys[]" multiple required><?php foreach ($accountingFinanceData['accountingDimensions'] ?? [] as $dimension): foreach ($dimension['values'] ?? [] as $value): ?><option value="<?= bx_h($value['dimension_value_key']) ?>"><?= bx_h($dimension['dimension_name'].' / '.$value['value_name']) ?></option><?php endforeach; endforeach; ?></select></label><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="apply_restriction_on_values" value="1" checked>Apply restriction to values</label></fieldset>

                <fieldset data-finance-foundation-fields="currency-exchange-setting" class="grid gap-4 sm:grid-cols-2" hidden><legend class="sr-only">Currency Exchange Setting</legend><label class="grid gap-1.5 text-sm">Service provider<input class="<?= $control ?>" name="service_provider" value="CUSTOM" required></label><label class="grid gap-1.5 text-sm">Base currency<input class="<?= $control ?>" name="base_currency" value="PHP" maxlength="3" required></label><label class="grid gap-1.5 text-sm sm:col-span-2">API endpoint<input class="<?= $control ?>" type="url" name="api_endpoint" placeholder="https://rates.example.test/{transaction_date}"></label><label class="grid gap-1.5 text-sm">From<input class="<?= $control ?>" name="rates[0][from_currency]" maxlength="3" value="USD"></label><label class="grid gap-1.5 text-sm">To<input class="<?= $control ?>" name="rates[0][to_currency]" maxlength="3" value="PHP"></label><label class="grid gap-1.5 text-sm">Rate date<input class="<?= $control ?>" type="date" name="rates[0][transaction_date]" value="<?= date('Y-m-d') ?>"></label><label class="grid gap-1.5 text-sm">Exchange rate<input class="<?= $control ?>" type="number" min="0.000000001" step="0.000000001" name="rates[0][exchange_rate]"></label></fieldset>

                <fieldset data-finance-foundation-fields="account-closing-balance" class="grid gap-4 sm:grid-cols-3" hidden><legend class="sr-only">Account Closing Balance</legend><label class="grid gap-1.5 text-sm">Closing date<input class="<?= $control ?>" type="date" name="closing_date" required></label><label class="grid gap-1.5 text-sm sm:col-span-2">Account<select class="<?= $control ?>" name="account_key" required><option value="">Select</option><?php foreach ($accountingAccounts as $account): if ((int)$account['is_group']===1) continue; ?><option value="<?= bx_h($account['account_key']) ?>"><?= bx_h($account['account_code'].' / '.$account['account_name']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm">Debit<input class="<?= $control ?>" type="number" min="0" step="0.000001" name="debit" value="0"></label><label class="grid gap-1.5 text-sm">Credit<input class="<?= $control ?>" type="number" min="0" step="0.000001" name="credit" value="0"></label><label class="grid gap-1.5 text-sm">Currency<input class="<?= $control ?>" name="account_currency" value="PHP" maxlength="3"></label></fieldset>
                <input type="hidden" name="status" value="ACTIVE">
            </div>
            <footer class="flex justify-end gap-2 border-t bg-card px-5 py-4"><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button><button class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Save Foundation Record</button></footer>
        </form>
    </section>
</div>

<div id="finance-chart-template-modal" data-record-modal<?= $financeModalStateAttributes('finance-chart-template-modal') ?> hidden class="yovel-finance-operation-modal fixed inset-0 z-50 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" style="z-index:120" role="dialog" aria-modal="true" aria-labelledby="finance-chart-template-title">
    <section class="flex max-h-[calc(100dvh-2rem)] w-full max-w-4xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg"><header class="yovel-finance-sticky-modal-header flex items-start justify-between gap-4 border-b bg-card px-5 py-4"><div><h3 id="finance-chart-template-title" class="font-semibold">Install Chart Template</h3><p class="mt-1 text-sm text-muted-foreground">Review and install a versioned company Chart of Accounts.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close"><span class="material-symbols-rounded text-base">close</span></button></header>
        <form method="post" data-confirm-submit class="contents"><div class="yovel-modal-scroll grid gap-4 overflow-y-auto p-5"><input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="save_finance_master"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="master_type" value="chart-template-install"><label class="grid gap-1.5 text-sm">Template<select class="<?= $control ?>" name="template_code" required><?php foreach ($financeChartTemplates as $template): ?><option value="<?= bx_h($template['template_code']) ?>" data-version="<?= (int)$template['template_version'] ?>"><?= bx_h($template['template_name'].' v'.$template['template_version'].' / '.$template['country']) ?></option><?php endforeach; ?></select></label><input type="hidden" name="template_version" value="<?= (int)($financeChartTemplates[0]['template_version'] ?? 1) ?>"><label class="grid gap-1.5 text-sm">Existing codes<select class="<?= $control ?>" name="duplicate_policy"><option value="SKIP_EXISTING">Keep existing accounts and add missing rows</option><option value="FAIL">Stop when any code exists</option></select></label><?php $preview = $financeChartTemplates ? yovel_admin_finance_chart_template_preview($company,(string)$financeChartTemplates[0]['template_code'],(int)$financeChartTemplates[0]['template_version']) : null; ?><?php if ($preview): ?><div class="rounded-md border bg-background"><div class="flex items-center justify-between border-b px-3 py-2 text-sm"><strong><?= count($preview['rows']) ?> accounts</strong><span class="text-muted-foreground"><?= (int)$preview['new_count'] ?> new, <?= (int)$preview['existing_count'] ?> existing</span></div><div class="max-h-72 overflow-auto"><table class="w-full text-left text-xs"><thead class="sticky top-0 bg-card"><tr><th class="px-3 py-2">Code</th><th class="px-3 py-2">Account</th><th class="px-3 py-2">Root</th><th class="px-3 py-2">State</th></tr></thead><tbody><?php foreach ($preview['rows'] as $row): ?><tr class="border-t"><td class="px-3 py-2 font-medium"><?= bx_h($row['account_code']) ?></td><td class="px-3 py-2"><?= bx_h($row['account_name']) ?></td><td class="px-3 py-2"><?= bx_h($row['root_type']) ?></td><td class="px-3 py-2"><?= bx_h($row['preview_state']) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?></div><footer class="flex justify-end gap-2 border-t bg-card px-5 py-4"><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button><button class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Install Template</button></footer></form>
    </section>
</div>

<script>
(() => {
    const modal = document.getElementById('finance-foundation-modal');
    if (!modal) return;
    const select = modal.querySelector('[data-finance-foundation-type]');
    const form = modal.querySelector('[data-finance-foundation-form]');
    const title = modal.querySelector('#finance-foundation-modal-title');
    const sync = () => modal.querySelectorAll('[data-finance-foundation-fields]').forEach((group) => {
        const active = group.dataset.financeFoundationFields === select.value;
        group.hidden = !active;
        group.querySelectorAll('input,select,textarea,button').forEach((control) => { control.disabled = !active; });
    });
    select.addEventListener('change', sync);
    const controlsNamed = (name) => Array.from(form.elements).filter((control) => control.name === name);
    const setValue = (name, value) => {
        const controls = controlsNamed(name);
        controls.forEach((control) => {
            if (control instanceof HTMLSelectElement && control.multiple) {
                const selected = new Set(Array.isArray(value) ? value.map(String) : []);
                Array.from(control.options).forEach((option) => { option.selected = selected.has(option.value); });
            } else if (control.type === 'checkbox' || control.type === 'radio') {
                control.checked = Array.isArray(value) ? value.map(String).includes(control.value) : String(value) === String(control.value) || value === true;
            } else if (value !== null && value !== undefined) {
                control.value = String(value);
            }
        });
    };
    document.querySelectorAll('[data-finance-foundation-edit]').forEach((button) => button.addEventListener('click', () => {
        let payload = {};
        try { payload = JSON.parse(button.dataset.financeFoundationEdit || '{}'); } catch (_) { return; }
        const record = payload.record || {};
        form.reset();
        select.value = payload.type || 'account-category';
        sync();
        Object.entries(record).forEach(([name, value]) => {
            if (!Array.isArray(value) && typeof value !== 'object') setValue(name, value);
        });
        if (record.closed_document_types_json) {
            try { setValue('closed_document_types[]', JSON.parse(record.closed_document_types_json)); } catch (_) {}
        }
        (record.percentages || []).forEach((line) => setValue(`percentages[${line.month_no}]`, line.percentage));
        (record.allocations || []).forEach((line, index) => {
            setValue(`allocations[${index}][cost_center_key]`, line.cost_center_key);
            setValue(`allocations[${index}][percentage]`, line.percentage);
        });
        setValue('account_keys[]', record.account_keys || []);
        setValue('dimension_value_keys[]', record.dimension_value_keys || []);
        (record.rates || []).slice(0, 1).forEach((rate, index) => {
            setValue(`rates[${index}][from_currency]`, rate.from_currency);
            setValue(`rates[${index}][to_currency]`, rate.to_currency);
            setValue(`rates[${index}][transaction_date]`, rate.transaction_date);
            setValue(`rates[${index}][exchange_rate]`, rate.exchange_rate);
        });
        title.textContent = `Edit ${select.options[select.selectedIndex].text}`;
    }));
    document.querySelectorAll('[data-record-modal-open="finance-foundation-modal"]:not([data-finance-foundation-edit])').forEach((button) => button.addEventListener('click', () => {
        form.reset();
        select.value = 'account-category';
        title.textContent = 'New Finance Foundation Record';
        sync();
    }));
    sync();
    const failedValues = <?= json_encode($financeFailedValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?: '{}' ?>;
    if (modal.hasAttribute('data-record-modal-open-on-load') && failedValues.master_type) {
        select.value = failedValues.master_type;
        sync();
        if (Array.isArray(failedValues.closed_document_types)) setValue('closed_document_types[]', failedValues.closed_document_types);
        Object.entries(failedValues.percentages || {}).forEach(([month, percentage]) => setValue(`percentages[${month}]`, percentage));
        (failedValues.allocations || []).forEach((line, index) => {
            setValue(`allocations[${index}][cost_center_key]`, line.cost_center_key);
            setValue(`allocations[${index}][percentage]`, line.percentage);
        });
        setValue('account_keys[]', failedValues.account_keys || []);
        setValue('dimension_value_keys[]', failedValues.dimension_value_keys || []);
        (failedValues.rates || []).slice(0, 1).forEach((rate, index) => {
            setValue(`rates[${index}][from_currency]`, rate.from_currency);
            setValue(`rates[${index}][to_currency]`, rate.to_currency);
            setValue(`rates[${index}][transaction_date]`, rate.transaction_date);
            setValue(`rates[${index}][exchange_rate]`, rate.exchange_rate);
        });
    }
})();
</script>
