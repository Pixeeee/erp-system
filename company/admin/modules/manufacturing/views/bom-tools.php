<?php
declare(strict_types=1);

$boms = is_array($manufacturingData['boms'] ?? null) ? $manufacturingData['boms'] : [];
$selectedBom = is_array($manufacturingData['selected_bom'] ?? null) ? $manufacturingData['selected_bom'] : ($boms[0] ?? null);
$editing = is_array($selectedBom) && (string) ($selectedBom['lifecycle_status'] ?? '') === 'DRAFT';
$bomDefaults = [
    'bom_key' => '', 'bom_code' => '', 'item_key' => '', 'revision' => 1, 'quantity' => '1.000000000',
    'uom_key' => 'EA', 'currency' => 'PHP', 'is_alternative' => 0, 'alternative_for_bom_key' => '',
    'effective_from' => date('Y-m-d'), 'effective_to' => '', 'process_loss_percent' => '0.0000',
    'expense_account_key' => '', 'notes' => '', 'components' => [], 'operations' => [], 'secondary_items' => [],
];
$bomValues = array_replace($bomDefaults, $editing ? $selectedBom : [], $manufacturingInput);
$bomJsonRows = static function (array $rows, array $keys): string {
    return yovel_admin_manufacturing_json(array_map(static fn (array $row): array => array_intersect_key($row, array_flip($keys)), $rows));
};
$commonHidden = '<input type="hidden" name="csrf" value="' . $manufacturingEscape(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="manufacturing"><input type="hidden" name="section" value="boms">';
$error = (string) ($manufacturingState['error'] ?? '');
$bomBody = ($error !== '' ? '<div role="alert" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">' . $manufacturingEscape($error) . '</div>' : '')
    . '<div class="grid gap-4 sm:grid-cols-2">'
    . '<label class="grid gap-1.5 text-sm"><span>BOM code</span><input class="h-9 rounded-md border bg-background px-3" name="bom_code" maxlength="80" required value="' . $manufacturingEscape((string) $bomValues['bom_code']) . '"></label>'
    . '<label class="grid gap-1.5 text-sm"><span>Finished item key</span><input class="h-9 rounded-md border bg-background px-3" name="item_key" maxlength="1500" required value="' . $manufacturingEscape((string) $bomValues['item_key']) . '"></label>'
    . '<label class="grid gap-1.5 text-sm"><span>Revision</span><input class="h-9 rounded-md border bg-background px-3" type="number" min="1" max="100000" name="revision" required value="' . (int) $bomValues['revision'] . '"></label>'
    . '<label class="grid gap-1.5 text-sm"><span>Output quantity</span><input class="h-9 rounded-md border bg-background px-3" type="number" min="0.000000001" step="0.000000001" name="quantity" required value="' . $manufacturingEscape((string) $bomValues['quantity']) . '"></label>'
    . '<label class="grid gap-1.5 text-sm"><span>Output UOM</span><input class="h-9 rounded-md border bg-background px-3" name="uom_key" maxlength="80" required value="' . $manufacturingEscape((string) $bomValues['uom_key']) . '"></label>'
    . '<label class="grid gap-1.5 text-sm"><span>Currency</span><input class="h-9 rounded-md border bg-background px-3" name="currency" maxlength="20" required value="' . $manufacturingEscape((string) $bomValues['currency']) . '"></label>'
    . '<label class="grid gap-1.5 text-sm"><span>Effective from</span><input class="h-9 rounded-md border bg-background px-3" type="date" name="effective_from" required value="' . $manufacturingEscape((string) $bomValues['effective_from']) . '"></label>'
    . '<label class="grid gap-1.5 text-sm"><span>Effective to</span><input class="h-9 rounded-md border bg-background px-3" type="date" name="effective_to" value="' . $manufacturingEscape((string) $bomValues['effective_to']) . '"></label>'
    . '<label class="grid gap-1.5 text-sm"><span>Process loss (%)</span><input class="h-9 rounded-md border bg-background px-3" type="number" min="0" max="100" step="0.0001" name="process_loss_percent" required value="' . $manufacturingEscape((string) $bomValues['process_loss_percent']) . '"></label>'
    . '<label class="grid gap-1.5 text-sm"><span>Expense account key</span><input class="h-9 rounded-md border bg-background px-3" name="expense_account_key" maxlength="1500" value="' . $manufacturingEscape((string) $bomValues['expense_account_key']) . '"></label>'
    . '<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_alternative" value="1"' . (!empty($bomValues['is_alternative']) ? ' checked' : '') . '>Alternative BOM</label>'
    . '<label class="grid gap-1.5 text-sm"><span>Base BOM key</span><input class="h-9 rounded-md border bg-background px-3" name="alternative_for_bom_key" maxlength="36" value="' . $manufacturingEscape((string) $bomValues['alternative_for_bom_key']) . '"></label>'
    . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Components</span><textarea class="min-h-32 rounded-md border bg-background p-3 font-mono text-xs" name="components" required>' . $manufacturingEscape($bomJsonRows((array) $bomValues['components'], ['item_key', 'child_bom_key', 'quantity', 'uom_key'])) . '</textarea></label>'
    . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Operations</span><textarea class="min-h-28 rounded-md border bg-background p-3 font-mono text-xs" name="operations">' . $manufacturingEscape($bomJsonRows((array) $bomValues['operations'], ['operation_key', 'sequence', 'duration_minutes'])) . '</textarea></label>'
    . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Secondary items</span><textarea class="min-h-28 rounded-md border bg-background p-3 font-mono text-xs" name="secondary_items">' . $manufacturingEscape($bomJsonRows((array) $bomValues['secondary_items'], ['item_key', 'secondary_type', 'quantity', 'uom_key'])) . '</textarea></label>'
    . '<label class="grid gap-1.5 text-sm sm:col-span-2"><span>Notes</span><textarea class="min-h-20 rounded-md border bg-background p-3" name="notes" maxlength="2000">' . $manufacturingEscape((string) $bomValues['notes']) . '</textarea></label>'
    . '</div>';
$manufacturingRenderModal([
    'id' => 'manufacturing-bom-modal', 'title' => $editing ? 'Edit BOM Draft' : 'New BOM',
    'description' => 'Author output, nested components, operations, alternatives, and recoverable secondary items.',
    'open_label' => $editing ? 'Edit BOM' : 'New BOM', 'submit_label' => 'Submit',
    'confirm_message' => 'Confirm this BOM draft before persistence begins.',
    'hidden_html' => $commonHidden . '<input type="hidden" name="action" value="save_manufacturing_bom"><input type="hidden" name="bom_key" value="' . $manufacturingEscape((string) $bomValues['bom_key']) . '">',
    'body_html' => $bomBody,
], $manufacturingState !== []);

$selectedKey = (string) ($selectedBom['bom_key'] ?? '');
$valuationDate = (string) ($selectedBom['effective_from'] ?? date('Y-m-d'));
$lifecycleModal = static function (string $id, string $title, string $description, string $label, string $action, string $body, string $confirm) use ($manufacturingRenderModal, $commonHidden, $selectedKey, $manufacturingEscape): void {
    $manufacturingRenderModal([
        'id' => $id, 'title' => $title, 'description' => $description, 'open_label' => $label,
        'submit_label' => 'Submit', 'confirm_message' => $confirm,
        'hidden_html' => $commonHidden . '<input type="hidden" name="action" value="' . $manufacturingEscape($action) . '"><input type="hidden" name="bom_key" value="' . $manufacturingEscape($selectedKey) . '">',
        'body_html' => $body,
    ], false);
};
?>
<div class="mt-4 grid gap-4">
    <?php if (is_array($selectedBom)): ?><dl class="grid grid-cols-2 gap-3 bg-muted/30 p-3 text-sm"><div><dt class="text-xs text-muted-foreground">Selected</dt><dd class="mt-1 font-medium"><?= $manufacturingEscape((string) $selectedBom['bom_code']) ?></dd></div><div><dt class="text-xs text-muted-foreground">Status</dt><dd class="mt-1 font-medium"><?= $manufacturingEscape((string) $selectedBom['lifecycle_status']) ?></dd></div><div><dt class="text-xs text-muted-foreground">Material</dt><dd class="mt-1 font-medium"><?= $manufacturingEscape((string) $selectedBom['material_cost']) ?></dd></div><div><dt class="text-xs text-muted-foreground">Total</dt><dd class="mt-1 font-medium"><?= $manufacturingEscape((string) $selectedBom['total_cost']) ?></dd></div></dl><?php endif; ?>
    <div class="flex flex-wrap gap-2">
        <?php
        $dateBody = '<label class="grid gap-1.5 text-sm"><span>Valuation date</span><input class="h-9 rounded-md border bg-background px-3" type="date" name="valuation_date" required value="' . $manufacturingEscape($valuationDate) . '"></label>';
        $lifecycleModal('manufacturing-bom-submit-modal', 'Submit BOM', 'Freeze this revision with reproducible valuation inputs.', 'Submit BOM', 'submit_manufacturing_bom', $dateBody, 'Confirm BOM submission. Persistence begins only after confirmation.');
        $lifecycleModal('manufacturing-bom-cancel-modal', 'Cancel BOM', 'Cancel the immutable submitted revision with a reason.', 'Cancel BOM', 'cancel_manufacturing_bom', '<label class="grid gap-1.5 text-sm"><span>Cancellation reason</span><textarea class="min-h-24 rounded-md border bg-background p-3" name="cancellation_reason" maxlength="1000" required></textarea></label>', 'Confirm cancellation of this BOM revision.');
        $lifecycleModal('manufacturing-bom-amend-modal', 'Amend BOM', 'Create a linked draft revision from a cancelled BOM.', 'Amend BOM', 'amend_manufacturing_bom', '<div class="grid gap-4"><label class="grid gap-1.5 text-sm"><span>New BOM code</span><input class="h-9 rounded-md border bg-background px-3" name="bom_code" maxlength="80" required></label><label class="grid gap-1.5 text-sm"><span>Effective from</span><input class="h-9 rounded-md border bg-background px-3" type="date" name="effective_from" required value="' . $manufacturingEscape(date('Y-m-d')) . '"></label><label class="grid gap-1.5 text-sm"><span>Effective to</span><input class="h-9 rounded-md border bg-background px-3" type="date" name="effective_to"></label></div>', 'Confirm creation of this amended BOM revision.');
        $lifecycleModal('manufacturing-bom-cost-modal', 'Update BOM Cost', 'Refresh deterministic Inventory valuations and Finance preview.', 'Update Cost', 'update_manufacturing_bom_cost', $dateBody, 'Confirm this deterministic BOM cost update.');
        ?>
    </div>
</div>
