<?php
declare(strict_types=1);

$operationsBulkLogs = is_array($activeModuleData['bulk_logs'] ?? null) ? $activeModuleData['bulk_logs'] : [];
$bulkFailedAction = (string) ($operationsFormState['action'] ?? '');
$bulkPrior = in_array($bulkFailedAction, ['submit_operations_bulk', 'cancel_operations_bulk'], true) ? $operationsPrior : [];
$bulkProviderAvailable = !empty($activeModuleData['owner_contracts_available']['bulk']);
ob_start();
$recordModal = [
    'id' => 'operations-bulk-submit-modal',
    'title' => 'Create Bulk Workflow',
    'description' => 'Dispatch one idempotent owner command for each stable target key.',
    'open_label' => 'Create Bulk Workflow',
    'submit_label' => 'Submit',
    'confirm_message' => 'Confirm this bulk workflow for ' . (string) ($companyName ?? 'this company') . '. No owner command runs before confirmation.',
    'open_on_load' => $bulkFailedAction === 'submit_operations_bulk',
    'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="submit_operations_bulk"><input type="hidden" name="section" value="bulk-processing">',
    'body_html' => ($bulkFailedAction === 'submit_operations_bulk' && ($operationsFormState['error'] ?? '') !== '' ? '<div role="alert" class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">' . bx_h((string) $operationsFormState['error']) . '</div>' : '') .
        '<div class="grid gap-1.5"><label for="operations-bulk-idempotency" class="text-sm font-medium">Idempotency key</label><input id="operations-bulk-idempotency" name="idempotency_key" required maxlength="150" value="' . bx_h((string) ($bulkPrior['idempotency_key'] ?? '')) . '" class="h-9 rounded-md border bg-background px-3 text-sm"></div>' .
        '<div class="grid gap-1.5"><label for="operations-bulk-record-type" class="text-sm font-medium">Record type</label><input id="operations-bulk-record-type" name="record_type" required maxlength="160" value="' . bx_h((string) ($bulkPrior['record_type'] ?? '')) . '" class="h-9 rounded-md border bg-background px-3 text-sm"></div>' .
        '<div class="grid gap-1.5"><label for="operations-bulk-action" class="text-sm font-medium">Action</label><select id="operations-bulk-action" name="action_code" class="h-9 rounded-md border bg-background px-3 text-sm">' . implode('', array_map(static fn (string $value): string => '<option value="' . $value . '"' . ((string) ($bulkPrior['action_code'] ?? 'ARCHIVE') === $value ? ' selected' : '') . '>' . ucfirst(strtolower($value)) . '</option>', ['ARCHIVE', 'UPDATE', 'SUBMIT', 'CANCEL'])) . '</select></div>' .
        '<div class="grid gap-1.5"><label for="operations-bulk-targets" class="text-sm font-medium">Target keys JSON</label><textarea id="operations-bulk-targets" name="targets_json" required rows="7" class="rounded-md border bg-background px-3 py-2 font-mono text-xs">' . bx_h((string) ($bulkPrior['targets_json'] ?? '[]')) . '</textarea></div>' .
        '<div class="grid gap-1.5"><label for="operations-bulk-evidence" class="text-sm font-medium">Evidence summary</label><textarea id="operations-bulk-evidence" name="evidence_summary" required maxlength="2000" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm">' . bx_h((string) ($bulkPrior['evidence_summary'] ?? '')) . '</textarea></div>',
];
require dirname(__DIR__) . '/record-modal.php';
foreach ($operationsBulkLogs as $log) {
    if (($log['status'] ?? '') !== 'QUEUED') continue;
    $recordModal = [
        'id' => 'operations-bulk-cancel-' . (string) $log['bulk_log_key'], 'title' => 'Cancel Bulk Workflow', 'description' => 'Cancel this queued workflow before an owner command begins.', 'open_label' => 'Cancel', 'submit_label' => 'Submit',
        'confirm_message' => 'Confirm cancellation for ' . (string) ($companyName ?? 'this company') . '. Completed owner work is never reversed here.',
        'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="cancel_operations_bulk"><input type="hidden" name="section" value="bulk-processing"><input type="hidden" name="bulk_log_key" value="' . bx_h((string) $log['bulk_log_key']) . '">',
        'body_html' => '<div class="grid gap-1.5"><label for="operations-bulk-cancel-reason-' . bx_h((string) $log['bulk_log_key']) . '" class="text-sm font-medium">Cancellation reason</label><textarea id="operations-bulk-cancel-reason-' . bx_h((string) $log['bulk_log_key']) . '" name="reason" required maxlength="1000" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm">' . bx_h($bulkFailedAction === 'cancel_operations_bulk' ? (string) ($bulkPrior['reason'] ?? '') : '') . '</textarea></div>',
        'open_on_load' => $bulkFailedAction === 'cancel_operations_bulk' && (string) ($bulkPrior['bulk_log_key'] ?? '') === (string) $log['bulk_log_key'],
    ];
    require dirname(__DIR__) . '/record-modal.php';
}
?>
<!-- Shared record-modal.php supplies data-confirm-submit and restores focus after Cancel. -->
<?php if (!$bulkProviderAvailable): ?><div class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm" role="status">The owner bulk-command contract is unavailable. Submissions remain blocked until its provider is injected.</div><?php endif; ?>
<?php
$operationsSectionTools = (string) ob_get_clean();
?>
<div class="grid gap-3">
    <div><h2 class="text-base font-semibold">Bulk processing</h2><p class="mt-1 text-sm text-muted-foreground">Operations logs each target while authoritative changes remain with the owning module.</p></div>
    <div class="overflow-x-auto rounded-md border"><table class="w-full min-w-[54rem] text-left text-sm"><thead class="bg-muted/50 text-xs text-muted-foreground"><tr><th class="px-3 py-2.5">Log</th><th class="px-3 py-2.5">Record type</th><th class="px-3 py-2.5">Action</th><th class="px-3 py-2.5">Progress</th><th class="px-3 py-2.5">Status</th></tr></thead><tbody class="divide-y"><?php if ($operationsBulkLogs === []): ?><tr><td colspan="5" class="px-3 py-10 text-center text-muted-foreground">No bulk workflows recorded.</td></tr><?php else: foreach ($operationsBulkLogs as $log): ?><tr><td class="px-3 py-2.5 font-mono text-xs"><?= bx_h((string) $log['bulk_log_key']) ?></td><td class="px-3 py-2.5"><?= bx_h((string) $log['record_type']) ?></td><td class="px-3 py-2.5"><?= bx_h((string) $log['action_code']) ?></td><td class="px-3 py-2.5"><?= (int) $log['completed_count'] ?>/<?= (int) $log['requested_count'] ?></td><td class="px-3 py-2.5"><?= bx_h((string) $log['status']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
</div>
