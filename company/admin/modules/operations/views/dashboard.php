<?php
declare(strict_types=1);

$dashboard = is_array($activeModuleData['dashboard'] ?? null) ? $activeModuleData['dashboard'] : [];
$dashboardSummary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
$dashboardQueue = is_array($dashboard['queue'] ?? null) ? $dashboard['queue'] : [];
$dashboardActivity = is_array($dashboard['activity'] ?? null) ? $dashboard['activity'] : [];
$dashboardSetup = is_array($dashboard['setup'] ?? null) ? $dashboard['setup'] : [];
$dashboardAlerts = is_array($dashboard['alerts'] ?? null) ? $dashboard['alerts'] : [];
$dashboardShortcuts = is_array($dashboard['shortcuts'] ?? null) ? $dashboard['shortcuts'] : [];
$dashboardDirectories = is_array($dashboard['directories'] ?? null) ? $dashboard['directories'] : [];
$dashboardJobs = is_array($activeModuleData['jobs'] ?? null) ? $activeModuleData['jobs'] : [];
$dashboardConflicts = is_array($activeModuleData['conflicts'] ?? null) ? $activeModuleData['conflicts'] : [];
$dashboardSystemAlerts = is_array($activeModuleData['alerts'] ?? null) ? $activeModuleData['alerts'] : [];
$dashboardFailedAction = (string) ($operationsFormState['action'] ?? '');
$dashboardPrior = $operationsPrior;

ob_start();
$recordModal = [
    'id' => 'operations-dashboard-run-job-modal',
    'title' => 'Run Operations Job',
    'description' => 'Queue idempotent work for an available company worker.',
    'open_label' => 'Run Job',
    'submit_label' => 'Submit',
    'confirm_message' => 'Confirm this run request. The job is not queued until confirmation.',
    'open_on_load' => $dashboardFailedAction === 'run_operations_job',
    'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="run_operations_job"><input type="hidden" name="section" value="dashboard">',
    'body_html' => ($dashboardFailedAction === 'run_operations_job' && ($operationsFormState['error'] ?? '') !== '' ? '<div role="alert" class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">' . bx_h((string) $operationsFormState['error']) . '</div>' : '') .
        '<div class="grid gap-1.5"><label class="text-sm font-medium" for="operations-dashboard-job-idempotency">Idempotency key</label><input id="operations-dashboard-job-idempotency" name="idempotency_key" required maxlength="160" value="' . bx_h((string) ($dashboardPrior['idempotency_key'] ?? '')) . '" class="h-9 rounded-md border bg-background px-3 text-sm"></div>' .
        '<div class="grid gap-1.5"><label class="text-sm font-medium" for="operations-dashboard-job-type">Job type</label><select id="operations-dashboard-job-type" name="job_type" class="h-9 rounded-md border bg-background px-3 text-sm">' . implode('', array_map(static fn (string $type): string => '<option value="' . $type . '"' . ((string) ($dashboardPrior['job_type'] ?? 'GENERAL') === $type ? ' selected' : '') . '>' . ucfirst(strtolower($type)) . '</option>', ['SCHEDULED', 'NOTIFICATION', 'SYNC', 'GENERAL'])) . '</select></div>' .
        '<div class="grid gap-1.5"><label class="text-sm font-medium" for="operations-dashboard-job-payload">Payload JSON</label><textarea id="operations-dashboard-job-payload" name="payload_json" required rows="5" class="rounded-md border bg-background px-3 py-2 font-mono text-xs">' . bx_h((string) ($dashboardPrior['payload_json'] ?? '{}')) . '</textarea></div>' .
        '<div class="grid gap-1.5"><label class="text-sm font-medium" for="operations-dashboard-job-attempts">Maximum attempts</label><input id="operations-dashboard-job-attempts" type="number" name="max_attempts" min="1" max="10" value="' . bx_h((string) ($dashboardPrior['max_attempts'] ?? '3')) . '" class="h-9 rounded-md border bg-background px-3 text-sm"></div>',
];
require __DIR__ . '/record-modal.php';
$dashboardPrimaryAction = (string) ob_get_clean();

ob_start();
?>
<div data-dashboard-setup class="grid gap-2">
    <div class="flex items-center justify-between gap-3"><h4 class="text-sm font-semibold">Setup progress</h4><span class="text-xs text-muted-foreground"><?= count(array_filter($dashboardSetup, static fn (array $step): bool => !empty($step['complete']))) ?>/<?= count($dashboardSetup) ?></span></div>
    <?php foreach ($dashboardSetup as $step): ?><a href="<?= bx_h((string) $step['href']) ?>" class="flex items-center gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm"><span class="material-symbols-rounded text-base" aria-hidden="true"><?= !empty($step['complete']) ? 'check_circle' : 'radio_button_unchecked' ?></span><span><?= bx_h((string) $step['label']) ?></span></a><?php endforeach; ?>
</div>
<div data-dashboard-alerts class="grid gap-2">
    <h4 class="text-sm font-semibold">Alerts and health</h4>
    <?php if ($dashboardAlerts === []): ?><p class="text-sm text-muted-foreground">No active Operations alerts.</p><?php else: foreach ($dashboardAlerts as $alert): ?><a href="<?= bx_h((string) $alert['href']) ?>" class="flex items-start justify-between gap-3 rounded-md bg-muted/50 px-3 py-2 text-sm"><span><?= bx_h((string) $alert['label']) ?></span><span class="shrink-0 text-xs font-medium"><?= bx_h((string) $alert['severity']) ?></span></a><?php endforeach; endif; ?>
</div>
<div class="grid gap-2">
    <h4 class="text-sm font-semibold">Queue actions</h4>
    <?php foreach (array_slice($dashboardJobs, 0, 30) as $job): if (($job['status'] ?? '') !== 'FAILED') continue;
        $recordModal = [
            'id' => 'operations-dashboard-retry-' . (string) $job['job_key'], 'title' => 'Retry Operations Job', 'description' => 'Start another bounded attempt for this failed job.', 'open_label' => 'Retry ' . (string) $job['job_type'], 'submit_label' => 'Submit',
            'confirm_message' => 'Confirm this retry. The job remains failed until confirmation.',
            'open_on_load' => $dashboardFailedAction === 'retry_operations_job' && (string) ($dashboardPrior['job_key'] ?? '') === (string) $job['job_key'],
            'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="retry_operations_job"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="job_key" value="' . bx_h((string) $job['job_key']) . '">',
            'body_html' => '<p class="text-sm text-muted-foreground">Attempt history remains immutable. One additional bounded attempt becomes available only after confirmation.</p>',
        ];
        require __DIR__ . '/record-modal.php';
    endforeach; ?>
    <?php foreach (array_slice($dashboardSystemAlerts, 0, 20) as $alert): if (($alert['status'] ?? '') !== 'ACTIVE') continue;
        $recordModal = [
            'id' => 'operations-dashboard-alert-' . (string) $alert['alert_key'], 'title' => 'Acknowledge System Alert', 'description' => 'Record an accountable response while retaining the original alert.', 'open_label' => 'Acknowledge ' . (string) $alert['alert_code'], 'submit_label' => 'Submit',
            'confirm_message' => 'Confirm this acknowledgment. Alert state is unchanged until confirmation.',
            'open_on_load' => $dashboardFailedAction === 'acknowledge_operations_alert' && (string) ($dashboardPrior['alert_key'] ?? '') === (string) $alert['alert_key'],
            'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="acknowledge_operations_alert"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="alert_key" value="' . bx_h((string) $alert['alert_key']) . '">',
            'body_html' => '<div class="grid gap-1.5"><label class="text-sm font-medium" for="operations-dashboard-alert-note-' . bx_h((string) $alert['alert_key']) . '">Acknowledgment note</label><textarea id="operations-dashboard-alert-note-' . bx_h((string) $alert['alert_key']) . '" name="acknowledgement_note" required maxlength="1000" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm">' . bx_h($dashboardFailedAction === 'acknowledge_operations_alert' ? (string) ($dashboardPrior['acknowledgement_note'] ?? '') : '') . '</textarea></div>',
        ];
        require __DIR__ . '/record-modal.php';
    endforeach; ?>
    <?php foreach (array_slice($dashboardConflicts, 0, 20) as $conflict): if (($conflict['status'] ?? '') !== 'OPEN') continue;
        $recordModal = [
            'id' => 'operations-dashboard-conflict-' . (string) $conflict['conflict_key'], 'title' => 'Resolve Sync Conflict', 'description' => 'Record a reviewed resolution without changing the owner record directly.', 'open_label' => 'Resolve Conflict', 'submit_label' => 'Submit',
            'confirm_message' => 'Confirm this conflict resolution. No state changes before confirmation.',
            'open_on_load' => $dashboardFailedAction === 'resolve_operations_conflict' && (string) ($dashboardPrior['conflict_key'] ?? '') === (string) $conflict['conflict_key'],
            'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="resolve_operations_conflict"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="conflict_key" value="' . bx_h((string) $conflict['conflict_key']) . '">',
            'body_html' => '<div class="grid gap-1.5"><label class="text-sm font-medium">Resolution</label><select name="resolution" class="h-9 rounded-md border bg-background px-3 text-sm"><option value="ACCEPT_REMOTE"' . ((string) ($dashboardPrior['resolution'] ?? '') === 'ACCEPT_REMOTE' ? ' selected' : '') . '>Accept owner value</option><option value="ACCEPT_LOCAL"' . ((string) ($dashboardPrior['resolution'] ?? '') === 'ACCEPT_LOCAL' ? ' selected' : '') . '>Retain local projection</option><option value="MANUAL"' . ((string) ($dashboardPrior['resolution'] ?? '') === 'MANUAL' ? ' selected' : '') . '>Manual review</option></select></div><div class="grid gap-1.5"><label class="text-sm font-medium">Resolution note</label><textarea name="resolution_note" required maxlength="1000" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm">' . bx_h($dashboardFailedAction === 'resolve_operations_conflict' ? (string) ($dashboardPrior['resolution_note'] ?? '') : '') . '</textarea></div>',
        ];
        require __DIR__ . '/record-modal.php';
    endforeach; ?>
    <a href="?view=operations&amp;section=governed-deletion" class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">delete_sweep</span>Review deletion queue</a>
    <button type="button" data-operations-dashboard-tour-start class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">explore</span>Open guided tour</button>
</div>
<?php
$operationsSectionTools = (string) ob_get_clean();
?>
<div data-operations-dashboard class="grid gap-6" data-tour-storage="operations-dashboard-tour:<?= bx_h((string) ($activeModuleData['company_key_hash'] ?? 'company')) ?>">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="text-base font-semibold">Operations dashboard</h2><p class="mt-1 text-sm text-muted-foreground">Live execution, exception, and release state for <?= bx_h((string) ($companyName ?? 'this company')) ?>.</p></div>
        <?= $dashboardPrimaryAction ?>
    </div>
    <div data-dashboard-summary class="grid grid-cols-2 gap-2 lg:grid-cols-3">
        <?php foreach ($dashboardSummary as $item): ?><a href="<?= bx_h((string) $item['href']) ?>" class="min-w-0 rounded-md bg-muted/50 p-3"><div class="break-words text-lg font-semibold"><?= bx_h((string) $item['value']) ?></div><div class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $item['label']) ?></div><div class="mt-2 text-[11px] font-medium"><?= bx_h((string) $item['availability']) ?></div></a><?php endforeach; ?>
    </div>
    <div data-dashboard-queue class="grid gap-2 border-t pt-5">
        <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Action queue</h3><span class="text-xs text-muted-foreground"><?= count($dashboardQueue) ?> items</span></div>
        <?php if ($dashboardQueue === []): ?><p class="py-4 text-sm text-muted-foreground">No failed work, conflicts, deletion reviews, or unacknowledged alerts.</p><?php else: foreach ($dashboardQueue as $item): ?><a href="<?= bx_h((string) $item['href']) ?>" class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 rounded-md bg-muted/40 px-3 py-2.5 text-sm"><span class="min-w-0"><span class="block font-medium"><?= bx_h((string) $item['label']) ?></span><span class="mt-1 block truncate text-xs text-muted-foreground"><?= bx_h((string) $item['detail']) ?></span></span><span class="text-xs font-medium"><?= bx_h((string) $item['status']) ?></span></a><?php endforeach; endif; ?>
    </div>
    <div data-dashboard-activity class="grid gap-2 border-t pt-5">
        <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Recent activity</h3><span class="text-xs text-muted-foreground"><?= count($dashboardActivity) ?> events</span></div>
        <?php if ($dashboardActivity === []): ?><p class="py-4 text-sm text-muted-foreground">Activity appears after the first confirmed Operations action.</p><?php else: foreach (array_slice($dashboardActivity, 0, 10) as $event): ?><div class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 border-b py-2.5 text-sm last:border-b-0"><div class="min-w-0"><div class="font-medium"><?= bx_h((string) $event['action']) ?> · <?= bx_h((string) $event['record_label']) ?></div><div class="mt-1 truncate text-xs text-muted-foreground"><?= bx_h((string) $event['actor_label']) ?> · <?= bx_h((string) $event['status']) ?></div></div><time class="text-xs text-muted-foreground"><?= bx_h((string) $event['occurred_at']) ?></time></div><?php endforeach; endif; ?>
    </div>
    <div data-dashboard-shortcuts class="grid gap-2 border-t pt-5">
        <h3 class="text-sm font-semibold">Shortcuts</h3>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($dashboardShortcuts as $shortcut): ?><a href="<?= bx_h((string) $shortcut['href']) ?>" class="flex min-h-10 items-center justify-between gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm font-medium"><span><?= bx_h((string) $shortcut['label']) ?></span><span class="material-symbols-rounded text-base" aria-hidden="true">arrow_forward</span></a><?php endforeach; ?></div>
    </div>
    <div data-dashboard-directories class="grid gap-3 border-t pt-5">
        <h3 class="text-sm font-semibold">Directories</h3>
        <div class="grid gap-4 sm:grid-cols-3"><?php foreach ($dashboardDirectories as $group): ?><div><h4 class="text-xs font-semibold uppercase text-muted-foreground"><?= bx_h((string) $group['group']) ?></h4><div class="mt-2 grid gap-1"><?php foreach (($group['items'] ?? []) as $item): ?><a href="<?= bx_h((string) $item['href']) ?>" class="inline-flex min-h-9 items-center gap-2 text-sm hover:underline"><span class="material-symbols-rounded text-base" aria-hidden="true">chevron_right</span><?= bx_h((string) $item['label']) ?></a><?php endforeach; ?></div></div><?php endforeach; ?></div>
    </div>
    <div data-operations-dashboard-tour hidden class="fixed inset-0 z-50 bg-black/40 p-4" role="dialog" aria-modal="true" aria-labelledby="operations-dashboard-tour-title" aria-describedby="operations-dashboard-tour-description">
        <div class="mx-auto mt-[12vh] grid w-full max-w-md gap-4 rounded-md border bg-popover p-5 shadow-lg" data-operations-dashboard-tour-panel tabindex="-1">
            <div><p class="text-xs font-medium text-muted-foreground" data-operations-dashboard-tour-progress></p><h2 id="operations-dashboard-tour-title" class="mt-1 text-base font-semibold" data-operations-dashboard-tour-title></h2><p id="operations-dashboard-tour-description" class="mt-2 text-sm text-muted-foreground" data-operations-dashboard-tour-description></p></div>
            <div class="flex flex-wrap items-center justify-between gap-2"><button type="button" data-operations-dashboard-tour-skip class="h-9 px-2 text-sm font-medium">Skip</button><div class="flex gap-2"><button type="button" data-operations-dashboard-tour-back class="h-9 rounded-md border px-3 text-sm font-medium">Back</button><button type="button" data-operations-dashboard-tour-next class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Next</button></div></div>
        </div>
    </div>
</div>
