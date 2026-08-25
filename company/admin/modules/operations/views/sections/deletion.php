<?php
declare(strict_types=1);

$operationsDeletionRequests = is_array($activeModuleData['deletion_requests'] ?? null) ? $activeModuleData['deletion_requests'] : [];
$deletionFailedAction = (string) ($operationsFormState['action'] ?? '');
$deletionPrior = in_array($deletionFailedAction, ['plan_operations_deletion', 'submit_operations_deletion', 'cancel_operations_deletion'], true) ? $operationsPrior : [];
$deletionPlanReady = (string) ($_GET['plan'] ?? '') === '1';
if ($deletionPlanReady) {
    foreach (['idempotency_key', 'record_type', 'targets_json', 'consequence', 'plan_hash'] as $key) {
        $deletionPrior[$key] = substr((string) ($_GET[$key] ?? ''), 0, $key === 'targets_json' || $key === 'consequence' ? 20000 : 2000);
    }
}
$deleteProvidersAvailable = !empty($activeModuleData['owner_contracts_available']['record_directory']) && !empty($activeModuleData['owner_contracts_available']['governed_delete']);
$deletionTargets = json_decode((string) ($deletionPrior['targets_json'] ?? '[]'), true);
$deletionCount = is_array($deletionTargets) ? count($deletionTargets) : 0;
$deletionConfirm = 'Confirm governed deletion for ' . (string) ($companyName ?? 'this company') . ': ' . (string) ($deletionPrior['record_type'] ?? 'selected record type') . ', ' . $deletionCount . ' target(s). Consequence: ' . (string) ($deletionPrior['consequence'] ?? 'owner-authorized permanent deletion') . '.';
ob_start();
$recordModal = [
    'id' => 'operations-deletion-plan-modal', 'title' => 'Create Deletion Dry Run', 'description' => 'Validate record ownership and enumerate targets without mutating any record.', 'open_label' => 'Create Dry Run', 'submit_label' => 'Submit',
    'confirm_message' => 'Confirm this dry run for ' . (string) ($companyName ?? 'this company') . '. It validates metadata and performs no mutation.',
    'open_on_load' => $deletionFailedAction === 'plan_operations_deletion',
    'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="plan_operations_deletion"><input type="hidden" name="section" value="governed-deletion">',
    'body_html' => ($deletionFailedAction === 'plan_operations_deletion' && ($operationsFormState['error'] ?? '') !== '' ? '<div role="alert" class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">' . bx_h((string) $operationsFormState['error']) . '</div>' : '') .
        '<div class="grid gap-1.5"><label for="operations-delete-idempotency" class="text-sm font-medium">Idempotency key</label><input id="operations-delete-idempotency" name="idempotency_key" required maxlength="150" value="' . bx_h((string) ($deletionPrior['idempotency_key'] ?? '')) . '" class="h-9 rounded-md border bg-background px-3 text-sm"></div>' .
        '<div class="grid gap-1.5"><label for="operations-delete-record-type" class="text-sm font-medium">Record type</label><input id="operations-delete-record-type" name="record_type" required maxlength="160" value="' . bx_h((string) ($deletionPrior['record_type'] ?? '')) . '" class="h-9 rounded-md border bg-background px-3 text-sm"></div>' .
        '<div class="grid gap-1.5"><label for="operations-delete-targets" class="text-sm font-medium">Target keys JSON</label><textarea id="operations-delete-targets" name="targets_json" required rows="7" class="rounded-md border bg-background px-3 py-2 font-mono text-xs">' . bx_h((string) ($deletionPrior['targets_json'] ?? '[]')) . '</textarea></div>' .
        '<div class="grid gap-1.5"><label for="operations-delete-consequence" class="text-sm font-medium">Consequence</label><textarea id="operations-delete-consequence" name="consequence" required maxlength="2000" rows="5" class="rounded-md border bg-background px-3 py-2 text-sm">' . bx_h((string) ($deletionPrior['consequence'] ?? '')) . '</textarea></div>',
];
require dirname(__DIR__) . '/record-modal.php';
if ($deletionPlanReady) {
    $recordModal = [
        'id' => 'operations-deletion-submit-modal', 'title' => 'Submit Governed Deletion', 'description' => 'Dispatch the verified plan through the authoritative owner contract.', 'open_label' => 'Submit Deletion', 'submit_label' => 'Submit',
        'confirm_message' => $deletionConfirm, 'open_on_load' => true,
        'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="submit_operations_deletion"><input type="hidden" name="section" value="governed-deletion"><input type="hidden" name="idempotency_key" value="' . bx_h((string) ($deletionPrior['idempotency_key'] ?? '')) . '"><input type="hidden" name="record_type" value="' . bx_h((string) ($deletionPrior['record_type'] ?? '')) . '"><input type="hidden" name="targets_json" value="' . bx_h((string) ($deletionPrior['targets_json'] ?? '[]')) . '"><input type="hidden" name="consequence" value="' . bx_h((string) ($deletionPrior['consequence'] ?? '')) . '">',
        'body_html' => '<dl class="grid gap-3 text-sm"><div><dt class="font-medium">Company</dt><dd class="text-muted-foreground">' . bx_h((string) ($companyName ?? '')) . '</dd></div><div><dt class="font-medium">Record type</dt><dd class="text-muted-foreground">' . bx_h((string) ($deletionPrior['record_type'] ?? '')) . '</dd></div><div><dt class="font-medium">Target count</dt><dd class="text-muted-foreground">' . $deletionCount . '</dd></div><div><dt class="font-medium">Consequence</dt><dd class="text-muted-foreground">' . bx_h((string) ($deletionPrior['consequence'] ?? '')) . '</dd></div></dl>',
    ];
    require dirname(__DIR__) . '/record-modal.php';
}
foreach ($operationsDeletionRequests as $request) {
    if (($request['status'] ?? '') !== 'QUEUED') continue;
    $recordModal = [
        'id' => 'operations-deletion-cancel-' . (string) $request['request_key'], 'title' => 'Cancel Governed Deletion', 'description' => 'Cancel this queued request before owner commands begin.', 'open_label' => 'Cancel', 'submit_label' => 'Submit',
        'confirm_message' => 'Confirm cancellation for ' . (string) ($companyName ?? 'this company') . '. No owner record will be changed.',
        'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="cancel_operations_deletion"><input type="hidden" name="section" value="governed-deletion"><input type="hidden" name="request_key" value="' . bx_h((string) $request['request_key']) . '">',
        'body_html' => '<div class="grid gap-1.5"><label for="operations-delete-cancel-reason-' . bx_h((string) $request['request_key']) . '" class="text-sm font-medium">Cancellation reason</label><textarea id="operations-delete-cancel-reason-' . bx_h((string) $request['request_key']) . '" name="reason" required maxlength="1000" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm">' . bx_h($deletionFailedAction === 'cancel_operations_deletion' ? (string) ($deletionPrior['reason'] ?? '') : '') . '</textarea></div>',
        'open_on_load' => $deletionFailedAction === 'cancel_operations_deletion' && (string) ($deletionPrior['request_key'] ?? '') === (string) $request['request_key'],
    ];
    require dirname(__DIR__) . '/record-modal.php';
}
?>
<!-- Shared record-modal.php supplies data-confirm-submit and restores focus after Cancel. -->
<?php if (!$deleteProvidersAvailable): ?><div class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm" role="status">The record directory or governed-delete owner contract is unavailable. Dry-run and submission remain blocked.</div><?php endif; ?>
<?php
$operationsSectionTools = (string) ob_get_clean();
?>
<div class="grid gap-3">
    <div><h2 class="text-base font-semibold">Governed deletion</h2><p class="mt-1 text-sm text-muted-foreground">Dry runs enumerate owner contracts; confirmed requests retain plans, targets, results, and audit references.</p></div>
    <div class="overflow-x-auto rounded-md border"><table class="w-full min-w-[56rem] text-left text-sm"><thead class="bg-muted/50 text-xs text-muted-foreground"><tr><th class="px-3 py-2.5">Request</th><th class="px-3 py-2.5">Record type</th><th class="px-3 py-2.5">Targets</th><th class="px-3 py-2.5">Completed</th><th class="px-3 py-2.5">Status</th></tr></thead><tbody class="divide-y"><?php if ($operationsDeletionRequests === []): ?><tr><td colspan="5" class="px-3 py-10 text-center text-muted-foreground">No governed deletion requests recorded.</td></tr><?php else: foreach ($operationsDeletionRequests as $request): ?><tr><td class="px-3 py-2.5 font-mono text-xs"><?= bx_h((string) $request['request_key']) ?></td><td class="px-3 py-2.5"><?= bx_h((string) $request['record_type']) ?></td><td class="px-3 py-2.5"><?= (int) $request['target_count'] ?></td><td class="px-3 py-2.5"><?= (int) $request['completed_count'] ?></td><td class="px-3 py-2.5"><?= bx_h((string) $request['status']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
</div>
