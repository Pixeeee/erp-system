<?php
declare(strict_types=1);

$operationsConflicts = is_array($activeModuleData['conflicts'] ?? null) ? $activeModuleData['conflicts'] : [];
ob_start();
foreach ($operationsConflicts as $conflict) {
    if (($conflict['status'] ?? '') !== 'OPEN') continue;
    $recordModal = [
        'id' => 'operations-conflict-' . (string) $conflict['conflict_key'], 'title' => 'Resolve Sync Conflict', 'description' => 'Record a reviewed resolution without changing the owner record directly.', 'open_label' => 'Resolve', 'submit_label' => 'Submit',
        'confirm_message' => 'Confirm this conflict resolution. No state changes before confirmation.',
        'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="resolve_operations_conflict"><input type="hidden" name="section" value="sync-conflict-dashboard"><input type="hidden" name="conflict_key" value="' . bx_h((string) $conflict['conflict_key']) . '">',
        'body_html' => '<div class="grid gap-1.5"><label class="text-sm font-medium">Resolution</label><select name="resolution" class="h-9 rounded-md border bg-background px-3 text-sm"><option value="ACCEPT_REMOTE">Accept owner value</option><option value="ACCEPT_LOCAL">Retain local projection</option><option value="MANUAL">Manual review</option></select></div><div class="grid gap-1.5"><label class="text-sm font-medium">Resolution note</label><textarea name="resolution_note" required maxlength="1000" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm"></textarea></div>',
    ];
    require dirname(__DIR__) . '/record-modal.php';
}
$operationsSectionTools = (string) ob_get_clean();
?>
<div class="grid gap-3"><div><h2 class="text-base font-semibold">Sync conflict dashboard</h2><p class="mt-1 text-sm text-muted-foreground">Differences are recorded locally; authoritative records remain with their owner.</p></div><div class="overflow-x-auto rounded-md border"><table class="w-full min-w-[44rem] text-left text-sm"><thead class="bg-muted/50 text-xs text-muted-foreground"><tr><th class="px-3 py-2.5">Contract</th><th class="px-3 py-2.5">Owner record</th><th class="px-3 py-2.5">Summary</th><th class="px-3 py-2.5">Status</th></tr></thead><tbody class="divide-y"><?php if ($operationsConflicts === []): ?><tr><td colspan="4" class="px-3 py-10 text-center text-muted-foreground">No sync conflicts recorded.</td></tr><?php else: foreach ($operationsConflicts as $conflict): ?><tr><td class="px-3 py-2.5"><?= bx_h((string) $conflict['source_contract']) ?></td><td class="px-3 py-2.5 font-mono text-xs"><?= bx_h((string) $conflict['owner_record_key']) ?></td><td class="px-3 py-2.5"><?= bx_h((string) $conflict['summary']) ?></td><td class="px-3 py-2.5"><?= bx_h((string) $conflict['status']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
