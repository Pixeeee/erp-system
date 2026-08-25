<?php
declare(strict_types=1);

$operationsAlerts = is_array($activeModuleData['alerts'] ?? null) ? $activeModuleData['alerts'] : [];
ob_start();
foreach ($operationsAlerts as $alert) {
    if (($alert['status'] ?? '') !== 'ACTIVE') continue;
    $recordModal = [
        'id' => 'operations-alert-' . (string) $alert['alert_key'], 'title' => 'Acknowledge System Alert', 'description' => 'Record an accountable response while retaining the original alert.', 'open_label' => 'Acknowledge', 'submit_label' => 'Submit',
        'confirm_message' => 'Confirm this acknowledgment. Alert state is unchanged until confirmation.',
        'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="acknowledge_operations_alert"><input type="hidden" name="section" value="system-alerts"><input type="hidden" name="alert_key" value="' . bx_h((string) $alert['alert_key']) . '">',
        'body_html' => '<div class="grid gap-1.5"><label class="text-sm font-medium">Acknowledgment note</label><textarea name="acknowledgement_note" required maxlength="1000" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm"></textarea></div>',
    ];
    require dirname(__DIR__) . '/record-modal.php';
}
$operationsSectionTools = (string) ob_get_clean();
?>
<div class="grid gap-3"><div><h2 class="text-base font-semibold">System alerts</h2><p class="mt-1 text-sm text-muted-foreground">Persisted operational conditions with severity, ownership, and acknowledgment evidence.</p></div><div class="overflow-x-auto rounded-md border"><table class="w-full min-w-[42rem] text-left text-sm"><thead class="bg-muted/50 text-xs text-muted-foreground"><tr><th class="px-3 py-2.5">Code</th><th class="px-3 py-2.5">Severity</th><th class="px-3 py-2.5">Alert</th><th class="px-3 py-2.5">Status</th></tr></thead><tbody class="divide-y"><?php if ($operationsAlerts === []): ?><tr><td colspan="4" class="px-3 py-10 text-center text-muted-foreground">No system alerts recorded.</td></tr><?php else: foreach ($operationsAlerts as $alert): ?><tr><td class="px-3 py-2.5 font-mono text-xs"><?= bx_h((string) $alert['alert_code']) ?></td><td class="px-3 py-2.5"><?= bx_h((string) $alert['severity']) ?></td><td class="px-3 py-2.5"><div class="font-medium"><?= bx_h((string) $alert['title']) ?></div><div class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $alert['summary']) ?></div></td><td class="px-3 py-2.5"><?= bx_h((string) $alert['status']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
