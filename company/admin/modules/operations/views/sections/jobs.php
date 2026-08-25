<?php
declare(strict_types=1);

$operationsJobs = is_array($activeModuleData['jobs'] ?? null) ? $activeModuleData['jobs'] : [];
$operationsWorkers = is_array($activeModuleData['workers'] ?? null) ? $activeModuleData['workers'] : [];
$operationsSchedules = is_array($activeModuleData['schedules'] ?? null) ? $activeModuleData['schedules'] : [];
if ($operationsSection === 'scheduled-jobs') {
    $operationsJobs = array_values(array_filter($operationsJobs, static fn (array $job): bool => ($job['source_section'] ?? '') === 'scheduled-jobs'));
} elseif ($operationsSection === 'notifications') {
    $operationsJobs = array_values(array_filter($operationsJobs, static fn (array $job): bool => ($job['source_section'] ?? '') === 'notifications'));
}
ob_start();
$recordModal = [
    'id' => 'operations-run-job-modal',
    'title' => 'Run Operations Job',
    'description' => 'Queue idempotent work for an available company worker.',
    'open_label' => 'Run Job',
    'submit_label' => 'Submit',
    'confirm_message' => 'Confirm this run request. The job is not queued until confirmation.',
    'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="run_operations_job"><input type="hidden" name="section" value="' . bx_h($operationsSection) . '">',
    'body_html' => '<div class="grid gap-1.5"><label class="text-sm font-medium" for="operations-job-idempotency">Idempotency key</label><input id="operations-job-idempotency" name="idempotency_key" required maxlength="160" class="h-9 rounded-md border bg-background px-3 text-sm"></div><div class="grid gap-1.5"><label class="text-sm font-medium" for="operations-job-type">Job type</label><select id="operations-job-type" name="job_type" class="h-9 rounded-md border bg-background px-3 text-sm"><option value="SCHEDULED">Scheduled</option><option value="NOTIFICATION">Notification</option><option value="SYNC">Sync</option><option value="GENERAL">General</option></select></div><input type="hidden" name="payload_json" value="{}"><div class="grid gap-1.5"><label class="text-sm font-medium" for="operations-job-max-attempts">Maximum attempts</label><input id="operations-job-max-attempts" type="number" name="max_attempts" min="1" max="10" value="3" class="h-9 rounded-md border bg-background px-3 text-sm"></div>',
];
require dirname(__DIR__) . '/record-modal.php';
$recordModal = [
    'id' => 'operations-create-schedule-modal',
    'title' => 'Create Scheduled Job',
    'description' => 'Save a recurring company job definition using a five-part schedule expression.',
    'open_label' => 'Create Schedule',
    'submit_label' => 'Submit',
    'confirm_message' => 'Confirm this schedule. It is not persisted before confirmation.',
    'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="save_operations_schedule"><input type="hidden" name="section" value="scheduled-jobs"><input type="hidden" name="job_type" value="SCHEDULED"><input type="hidden" name="schedule_status" value="ACTIVE"><input type="hidden" name="payload_json" value="{}">',
    'body_html' => '<div class="grid gap-1.5"><label class="text-sm font-medium">Schedule name</label><input name="schedule_name" required maxlength="180" class="h-9 rounded-md border bg-background px-3 text-sm"></div><div class="grid gap-1.5"><label class="text-sm font-medium">Schedule expression</label><input name="cron_expression" required maxlength="120" placeholder="15 2 * * *" class="h-9 rounded-md border bg-background px-3 font-mono text-sm"></div>',
];
require dirname(__DIR__) . '/record-modal.php';
?>
<div class="grid gap-3">
    <?php if ($operationsSection === 'scheduled-jobs'): ?>
    <div class="grid gap-2"><h3 class="text-sm font-semibold">Schedules</h3><?php if ($operationsSchedules === []): ?><p class="text-sm text-muted-foreground">No recurring schedules configured.</p><?php else: foreach ($operationsSchedules as $schedule): ?><div class="rounded-md bg-muted/50 px-3 py-2 text-sm"><div class="flex items-center justify-between gap-2"><span class="font-medium"><?= bx_h((string) $schedule['schedule_name']) ?></span><span class="text-xs"><?= bx_h((string) $schedule['schedule_status']) ?></span></div><code class="mt-1 block text-xs text-muted-foreground"><?= bx_h((string) $schedule['cron_expression']) ?></code></div><?php endforeach; endif; ?></div>
    <?php endif; ?>
    <h3 class="text-sm font-semibold">Worker health</h3>
    <?php if ($operationsWorkers === []): ?><p class="text-sm text-muted-foreground">No worker has claimed company work yet.</p><?php else: foreach ($operationsWorkers as $worker): ?><div class="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2 text-sm"><span><?= bx_h((string) $worker['worker_key']) ?></span><span><?= bx_h((string) $worker['worker_status']) ?></span></div><?php endforeach; endif; ?>
</div>
<?php
foreach ($operationsJobs as $job) {
    if (($job['status'] ?? '') === 'FAILED') {
        $recordModal = [
            'id' => 'operations-retry-' . (string) $job['job_key'], 'title' => 'Retry Operations Job', 'description' => 'Start another bounded attempt for this failed job.', 'open_label' => 'Retry', 'submit_label' => 'Submit',
            'confirm_message' => 'Confirm this retry. The job remains failed until confirmation.',
            'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="retry_operations_job"><input type="hidden" name="section" value="' . bx_h($operationsSection) . '"><input type="hidden" name="job_key" value="' . bx_h((string) $job['job_key']) . '">',
            'body_html' => '<p class="text-sm text-muted-foreground">The existing attempt history remains immutable. One additional bounded attempt will be made available.</p>',
        ];
        require dirname(__DIR__) . '/record-modal.php';
    }
    if (in_array((string) ($job['status'] ?? ''), ['QUEUED', 'RUNNING'], true)) {
        $recordModal = [
            'id' => 'operations-cancel-' . (string) $job['job_key'], 'title' => 'Cancel Operations Job', 'description' => 'Stop queued or leased work and retain its history.', 'open_label' => 'Cancel', 'submit_label' => 'Submit',
            'confirm_message' => 'Confirm cancellation. The job is unchanged until confirmation.',
            'hidden_html' => '<input type="hidden" name="csrf" value="' . bx_h(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="operations"><input type="hidden" name="action" value="cancel_operations_job"><input type="hidden" name="section" value="' . bx_h($operationsSection) . '"><input type="hidden" name="job_key" value="' . bx_h((string) $job['job_key']) . '">',
            'body_html' => '<div class="grid gap-1.5"><label class="text-sm font-medium" for="operations-cancel-reason-' . bx_h((string) $job['job_key']) . '">Reason</label><textarea id="operations-cancel-reason-' . bx_h((string) $job['job_key']) . '" name="reason" required maxlength="1000" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm"></textarea></div>',
        ];
        require dirname(__DIR__) . '/record-modal.php';
    }
}
$operationsSectionTools = (string) ob_get_clean();
?>
<div class="grid gap-3">
    <div class="flex items-center justify-between gap-3"><div><h2 class="text-base font-semibold"><?= bx_h((string) ($operationsMeta['label'] ?? 'Jobs')) ?></h2><p class="mt-1 text-sm text-muted-foreground">Lease-aware jobs with immutable attempts and bounded retry.</p></div><span class="rounded-md bg-muted px-2.5 py-1 text-xs font-medium"><?= count($operationsJobs) ?> jobs</span></div>
    <div class="overflow-x-auto rounded-md border"><table class="w-full min-w-[58rem] text-left text-sm"><thead class="bg-muted/50 text-xs text-muted-foreground"><tr><th class="px-3 py-2.5">Job</th><th class="px-3 py-2.5">Type</th><th class="px-3 py-2.5">Status</th><th class="px-3 py-2.5">Attempts</th><th class="px-3 py-2.5">Worker</th><th class="px-3 py-2.5">Lease</th><th class="px-3 py-2.5">Available</th></tr></thead><tbody class="divide-y"><?php if ($operationsJobs === []): ?><tr><td colspan="7" class="px-3 py-10 text-center text-muted-foreground">No job history for this company.</td></tr><?php else: foreach ($operationsJobs as $job): ?><tr><td class="px-3 py-2.5 font-mono text-xs"><?= bx_h((string) $job['job_key']) ?></td><td class="px-3 py-2.5"><?= bx_h((string) $job['job_type']) ?></td><td class="px-3 py-2.5"><?= bx_h((string) $job['status']) ?></td><td class="px-3 py-2.5"><?= (int) $job['attempt_count'] ?>/<?= (int) $job['max_attempts'] ?></td><td class="px-3 py-2.5 text-xs"><?= bx_h((string) ($job['leased_by_worker_key'] ?? '')) ?></td><td class="px-3 py-2.5 text-xs text-muted-foreground"><?= bx_h((string) ($job['lease_expires_at'] ?? '')) ?></td><td class="px-3 py-2.5 text-muted-foreground"><?= bx_h((string) $job['available_at']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
</div>
