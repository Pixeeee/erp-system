<?php
$buyingScorecards = is_array($buyingData['scorecards'] ?? null) ? $buyingData['scorecards'] : [];
$buyingScorecardSuppliers = is_array($buyingData['suppliers'] ?? null) ? $buyingData['suppliers'] : [];
$buyingScorecardInput = $buyingReopenScorecardModal ? $buyingFormInput : [];
$buyingPeriodInput = $buyingReopenScorecardPeriodModal ? $buyingFormInput : [];
$scorecardVariablesText = (string) ($buyingScorecardInput['variables_text'] ?? 'PO_COUNT | Purchase order count | purchase_order_count | Orders created in the period');
$scorecardCriteriaText = (string) ($buyingScorecardInput['criteria_text'] ?? 'ACTIVITY | Purchase activity | 100 | 100 | PO_COUNT');
$scorecardStandingsText = (string) ($buyingScorecardInput['standings_text'] ?? "RESTRICTED | Restricted | 0 | 50 | destructive | 1 | 1 | 1 | 1 | 0 | 0 |\nWATCH | Watch | 50 | 80 | warning | 1 | 0 | 1 | 0 | 0 | 0 |\nGOOD | Good | 80 | 100 | success | 0 | 0 | 0 | 0 | 0 | 0 |");
$supplierNames = [];
foreach ($buyingScorecardSuppliers as $supplier) {
    $supplierNames[(string) $supplier['supplier_key']] = (string) $supplier['supplier_name'];
}
?>
<div data-buying-scorecards class="grid gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h3 class="text-sm font-semibold">Supplier performance controls</h3><p class="mt-1 text-sm text-muted-foreground">Scores and restrictions are calculated from registered procurement metrics.</p></div>
        <div class="flex flex-wrap gap-2">
            <button type="button" data-record-modal-open="buying-scorecard-period-modal" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">calculate</span>Calculate Period</button>
            <button type="button" data-record-modal-open="buying-scorecard-modal" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>Add Scorecard</button>
        </div>
    </div>

    <?php if ($buyingScorecards === []): ?>
        <div class="grid min-h-64 place-items-center border-y text-center">
            <div class="max-w-md"><span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true">score</span><h3 class="mt-3 text-sm font-semibold">No supplier scorecards exist for this company.</h3><p class="mt-1 text-sm leading-6 text-muted-foreground">Create a definition after an active supplier is available.</p></div>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto border-y">
            <table class="w-full min-w-[52rem] text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground"><tr><th class="px-3 py-3">Supplier</th><th class="px-3 py-3">Period</th><th class="px-3 py-3">Structure</th><th class="px-3 py-3">Latest standing</th><th class="px-3 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y">
                    <?php foreach ($buyingScorecards as $scorecard): ?>
                        <?php
                        $variableLines = array_map(static fn (array $row): string => implode(' | ', [(string) $row['variable_code'], (string) $row['variable_label'], (string) $row['metric_path'], (string) ($row['description'] ?? '')]), $scorecard['variables']);
                        $criteriaLines = array_map(static fn (array $row): string => implode(' | ', [(string) $row['criteria_code'], (string) $row['criteria_name'], (string) $row['max_score'], (string) $row['weight_percentage'], (string) $row['formula_expression']]), $scorecard['criteria']);
                        $standingLines = array_map(static fn (array $row): string => implode(' | ', [(string) $row['standing_code'], (string) $row['standing_name'], (string) $row['minimum_score'], (string) $row['maximum_score'], (string) $row['color_token'], (string) $row['warn_rfqs'], (string) $row['prevent_rfqs'], (string) $row['warn_purchase_orders'], (string) $row['prevent_purchase_orders'], (string) $row['notify_supplier'], (string) $row['notify_employee'], (string) ($row['employee_key'] ?? '')]), $scorecard['standings']);
                        $payload = [
                            'scorecard_key' => $scorecard['scorecard_key'],
                            'supplier_key' => $scorecard['supplier_key'],
                            'period_type' => $scorecard['period_type'],
                            'weighting_expression' => $scorecard['weighting_expression'],
                            'scorecard_status' => $scorecard['scorecard_status'],
                            'variables_text' => implode("\n", $variableLines),
                            'criteria_text' => implode("\n", $criteriaLines),
                            'standings_text' => implode("\n", $standingLines),
                        ];
                        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
                        $restrictions = is_array($scorecard['restrictions'] ?? null) ? $scorecard['restrictions'] : [];
                        ?>
                        <tr>
                            <td class="px-3 py-3"><span class="font-medium"><?= bx_h($supplierNames[(string) $scorecard['supplier_key']] ?? 'Unavailable supplier') ?></span><span class="block text-xs text-muted-foreground"><?= bx_h((string) $scorecard['scorecard_status']) ?></span></td>
                            <td class="px-3 py-3"><?= bx_h((string) $scorecard['period_type']) ?></td>
                            <td class="px-3 py-3"><?= count($scorecard['criteria']) ?> criteria · <?= count($scorecard['variables']) ?> variables · <?= count($scorecard['standings']) ?> standings</td>
                            <td class="px-3 py-3"><?= bx_h((string) ($restrictions['standing_name'] ?? 'Not calculated')) ?><?php if (!empty($restrictions['prevent_rfqs']) || !empty($restrictions['prevent_purchase_orders'])): ?><span class="block text-xs text-destructive">Purchasing restricted</span><?php endif; ?></td>
                            <td class="px-3 py-3"><div class="flex justify-end gap-2"><button type="button" data-record-modal-open="buying-scorecard-period-modal" data-buying-scorecard-calculate data-scorecard-key="<?= bx_h((string) $scorecard['scorecard_key']) ?>" class="inline-flex size-8 items-center justify-center rounded-md border bg-background" aria-label="Calculate supplier scorecard period" title="Calculate period"><span class="material-symbols-rounded text-base" aria-hidden="true">calculate</span></button><button type="button" data-record-modal-open="buying-scorecard-modal" data-buying-scorecard-edit data-scorecard-payload="<?= bx_h($payloadJson) ?>" class="inline-flex size-8 items-center justify-center rounded-md border bg-background" aria-label="Edit supplier scorecard" title="Edit scorecard"><span class="material-symbols-rounded text-base" aria-hidden="true">edit</span></button></div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="buying-scorecard-modal" data-record-modal <?= $buyingReopenScorecardModal ? 'data-record-modal-open-on-load' : '' ?> hidden class="fixed inset-0 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" style="z-index: 100;" role="dialog" aria-modal="true" aria-labelledby="buying-scorecard-modal-title" aria-describedby="buying-scorecard-modal-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-5xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg" role="document">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4"><div class="min-w-0"><h2 id="buying-scorecard-modal-title" class="text-base font-semibold"><?= !empty($buyingScorecardInput['scorecard_key']) ? 'Edit supplier scorecard' : 'Add Scorecard' ?></h2><p id="buying-scorecard-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Register normalized metrics, weighted criteria, and complete standing ranges.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background" aria-label="Close scorecard dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <form method="post" data-confirm-submit data-confirm-message="Save this supplier scorecard definition?" data-buying-scorecard-form class="contents">
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="buying-procurement"><input type="hidden" name="action" value="save_buying_scorecard"><input type="hidden" name="section" value="supplier-scorecards"><input type="hidden" name="scorecard_key" value="<?= bx_h((string) ($buyingScorecardInput['scorecard_key'] ?? '')) ?>">
            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                <?php if ($buyingReopenScorecardModal): ?><div class="mb-5 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert"><?= bx_h($buyingFormError) ?></div><?php endif; ?>
                <div class="grid gap-5">
                    <fieldset class="grid gap-4 sm:grid-cols-3"><legend class="mb-3 text-sm font-semibold">Definition</legend>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Supplier</span><select name="supplier_key" class="h-10 rounded-md border bg-background px-3" required><option value="">Select supplier</option><?php foreach ($buyingScorecardSuppliers as $supplier): ?><?php if ((string) $supplier['supplier_status'] === 'ACTIVE'): ?><option value="<?= bx_h((string) $supplier['supplier_key']) ?>" <?= (string) ($buyingScorecardInput['supplier_key'] ?? '') === (string) $supplier['supplier_key'] ? 'selected' : '' ?>><?= bx_h((string) $supplier['supplier_name']) ?></option><?php endif; ?><?php endforeach; ?></select></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Period type</span><select name="period_type" class="h-10 rounded-md border bg-background px-3" required><?php foreach (['WEEK', 'MONTH', 'YEAR'] as $value): ?><option value="<?= $value ?>" <?= (string) ($buyingScorecardInput['period_type'] ?? 'MONTH') === $value ? 'selected' : '' ?>><?= ucfirst(strtolower($value)) ?></option><?php endforeach; ?></select></label>
                        <label class="grid gap-1.5 text-sm"><span class="font-medium">Status</span><select name="scorecard_status" class="h-10 rounded-md border bg-background px-3" required><?php foreach (['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED'] as $value): ?><option value="<?= $value ?>" <?= (string) ($buyingScorecardInput['scorecard_status'] ?? 'ACTIVE') === $value ? 'selected' : '' ?>><?= ucfirst(strtolower($value)) ?></option><?php endforeach; ?></select></label>
                        <label class="grid gap-1.5 text-sm sm:col-span-3"><span class="font-medium">Weighting expression</span><input name="weighting_expression" maxlength="500" value="<?= bx_h((string) ($buyingScorecardInput['weighting_expression'] ?? 'PO_COUNT')) ?>" class="h-10 rounded-md border bg-background px-3 font-mono" required></label>
                    </fieldset>
                    <label class="grid gap-1.5 border-t pt-5 text-sm"><span class="font-medium">Variables</span><textarea name="variables_text" rows="4" class="rounded-md border bg-background px-3 py-2 font-mono text-xs" required><?= bx_h($scorecardVariablesText) ?></textarea></label>
                    <label class="grid gap-1.5 border-t pt-5 text-sm"><span class="font-medium">Criteria</span><textarea name="criteria_text" rows="5" class="rounded-md border bg-background px-3 py-2 font-mono text-xs" required><?= bx_h($scorecardCriteriaText) ?></textarea></label>
                    <label class="grid gap-1.5 border-t pt-5 text-sm"><span class="font-medium">Standings and restrictions</span><textarea name="standings_text" rows="6" class="rounded-md border bg-background px-3 py-2 font-mono text-xs" required><?= bx_h($scorecardStandingsText) ?></textarea></label>
                </div>
            </div>
            <footer class="m-0 flex w-full shrink-0 flex-wrap items-center justify-between gap-3 rounded-none border-t bg-popover px-6 py-4"><span class="text-xs text-muted-foreground">Weights and standing coverage are validated before the transaction starts.</span><div class="flex gap-2"><button type="button" data-record-modal-close class="h-9 rounded-md border bg-background px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Save Scorecard</button></div></footer>
        </form>
    </section>
</div>

<div id="buying-scorecard-period-modal" data-record-modal <?= $buyingReopenScorecardPeriodModal ? 'data-record-modal-open-on-load' : '' ?> hidden class="fixed inset-0 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" style="z-index: 100;" role="dialog" aria-modal="true" aria-labelledby="buying-scorecard-period-title" aria-describedby="buying-scorecard-period-description">
    <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg" role="document">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b bg-popover px-6 py-4"><div class="min-w-0"><h2 id="buying-scorecard-period-title" class="text-base font-semibold">Calculate Scorecard Period</h2><p id="buying-scorecard-period-description" class="mt-1 text-sm leading-6 text-muted-foreground">Create one immutable metric and criteria snapshot for a unique date range.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background" aria-label="Close scorecard period dialog"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></button></header>
        <form method="post" data-confirm-submit data-confirm-message="Calculate and persist this supplier scorecard period?" data-buying-scorecard-period-form class="contents">
            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="module_view" value="buying-procurement"><input type="hidden" name="action" value="calculate_buying_scorecard_period"><input type="hidden" name="section" value="supplier-scorecards">
            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                <?php if ($buyingReopenScorecardPeriodModal): ?><div class="mb-5 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert"><?= bx_h($buyingFormError) ?></div><?php endif; ?>
                <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-1.5 text-sm sm:col-span-2"><span class="font-medium">Scorecard</span><select name="scorecard_key" class="h-10 rounded-md border bg-background px-3" required><option value="">Select scorecard</option><?php foreach ($buyingScorecards as $scorecard): ?><option value="<?= bx_h((string) $scorecard['scorecard_key']) ?>" <?= (string) ($buyingPeriodInput['scorecard_key'] ?? '') === (string) $scorecard['scorecard_key'] ? 'selected' : '' ?>><?= bx_h($supplierNames[(string) $scorecard['supplier_key']] ?? (string) $scorecard['scorecard_key']) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5 text-sm"><span class="font-medium">Period start</span><input type="date" name="period_start" value="<?= bx_h((string) ($buyingPeriodInput['period_start'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3" required></label><label class="grid gap-1.5 text-sm"><span class="font-medium">Period end</span><input type="date" name="period_end" value="<?= bx_h((string) ($buyingPeriodInput['period_end'] ?? '')) ?>" class="h-10 rounded-md border bg-background px-3" required></label></div>
            </div>
            <footer class="m-0 flex w-full shrink-0 flex-wrap items-center justify-between gap-3 rounded-none border-t bg-popover px-6 py-4"><span class="text-xs text-muted-foreground">Local score persistence is independent of notification delivery.</span><div class="flex gap-2"><button type="button" data-record-modal-close class="h-9 rounded-md border bg-background px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Calculate Period</button></div></footer>
        </form>
    </section>
</div>

<script>
(() => {
    const definitionForm = document.querySelector('[data-buying-scorecard-form]');
    document.querySelectorAll('[data-buying-scorecard-edit]').forEach((button) => button.addEventListener('click', () => {
        if (!definitionForm) return;
        const record = JSON.parse(button.dataset.scorecardPayload || '{}');
        Object.entries(record).forEach(([key, value]) => { const control = definitionForm.elements.namedItem(key); if (control) control.value = value ?? ''; });
        const title = document.querySelector('#buying-scorecard-modal-title');
        if (title) title.textContent = 'Edit supplier scorecard';
    }));
    const periodForm = document.querySelector('[data-buying-scorecard-period-form]');
    document.querySelectorAll('[data-buying-scorecard-calculate]').forEach((button) => button.addEventListener('click', () => {
        if (periodForm) periodForm.elements.scorecard_key.value = button.dataset.scorecardKey || '';
    }));
})();
</script>
