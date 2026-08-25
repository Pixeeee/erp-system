<?php
declare(strict_types=1);

function yovel_admin_operations_dashboard_release_state(array $checks): string
{
    if ($checks === []) {
        return 'NOT CHECKED';
    }
    $latest = [];
    foreach ($checks as $check) {
        $code = (string) ($check['check_code'] ?? '');
        if ($code !== '' && !isset($latest[$code])) {
            $latest[$code] = strtoupper((string) ($check['status'] ?? 'WARN'));
        }
    }
    if (in_array('FAIL', $latest, true)) {
        return 'FAIL';
    }
    return in_array('WARN', $latest, true) ? 'WARN' : 'PASS';
}

function yovel_admin_operations_dashboard_data(array $company, array $admin): array
{
    [, $companyKeyHash] = yovel_admin_operations_scope($company, $admin);
    $db = bx_db();

    $jobCounts = ['QUEUED' => 0, 'RUNNING' => 0, 'FAILED' => 0];
    foreach ($db->GetAll(
        "SELECT status,COUNT(*) AS record_count FROM project_company_operations_job WHERE company_key_hash=? AND status IN ('QUEUED','RUNNING','FAILED') GROUP BY status",
        [$companyKeyHash]
    ) ?: [] as $row) {
        $jobCounts[(string) $row['status']] = (int) $row['record_count'];
    }
    $activeAlertCount = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_operations_system_alert WHERE company_key_hash=? AND status='ACTIVE'",
        [$companyKeyHash]
    );
    $openConflictCount = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_operations_sync_conflict WHERE company_key_hash=? AND status='OPEN'",
        [$companyKeyHash]
    );
    $releaseChecks = $db->GetAll(
        'SELECT * FROM project_company_operations_release_check WHERE company_key_hash=? ORDER BY checked_at DESC,x_id DESC LIMIT 200',
        [$companyKeyHash]
    ) ?: [];
    $releaseState = yovel_admin_operations_dashboard_release_state($releaseChecks);

    $summary = [
        ['key' => 'queued-jobs', 'label' => 'Queued jobs', 'value' => $jobCounts['QUEUED'], 'availability' => 'AVAILABLE', 'href' => '?view=operations&section=scheduled-jobs'],
        ['key' => 'running-jobs', 'label' => 'Running jobs', 'value' => $jobCounts['RUNNING'], 'availability' => 'AVAILABLE', 'href' => '?view=operations&section=background-workers'],
        ['key' => 'failed-jobs', 'label' => 'Failed jobs', 'value' => $jobCounts['FAILED'], 'availability' => 'AVAILABLE', 'href' => '?view=operations&section=scheduled-jobs'],
        ['key' => 'active-alerts', 'label' => 'Active alerts', 'value' => $activeAlertCount, 'availability' => 'AVAILABLE', 'href' => '?view=operations&section=system-alerts'],
        ['key' => 'unresolved-conflicts', 'label' => 'Unresolved conflicts', 'value' => $openConflictCount, 'availability' => 'AVAILABLE', 'href' => '?view=operations&section=sync-conflict-dashboard'],
        ['key' => 'release-readiness', 'label' => 'Release readiness', 'value' => $releaseState, 'availability' => 'AVAILABLE', 'href' => '?view=operations&section=release-checklist'],
    ];

    $queue = [];
    foreach ($db->GetAll(
        "SELECT job_key,job_type,error_summary,updated_at FROM project_company_operations_job WHERE company_key_hash=? AND status='FAILED' ORDER BY updated_at DESC,x_id DESC LIMIT 6",
        [$companyKeyHash]
    ) ?: [] as $job) {
        $queue[] = [
            'key' => 'job:' . (string) $job['job_key'], 'label' => (string) $job['job_type'] . ' job failed',
            'status' => 'FAILED', 'href' => '?view=operations&section=scheduled-jobs&job=' . rawurlencode((string) $job['job_key']),
            'detail' => substr(trim((string) ($job['error_summary'] ?? 'Retry review required.')), 0, 240), 'priority' => 10,
        ];
    }
    $activeAlerts = $db->GetAll(
        "SELECT * FROM project_company_operations_system_alert WHERE company_key_hash=? AND status='ACTIVE' ORDER BY FIELD(severity,'CRITICAL','ERROR','WARNING','INFO'),updated_at DESC,x_id DESC LIMIT 6",
        [$companyKeyHash]
    ) ?: [];
    foreach ($activeAlerts as $alert) {
        $queue[] = [
            'key' => 'alert:' . (string) $alert['alert_key'], 'label' => (string) $alert['title'],
            'status' => (string) $alert['severity'], 'href' => '?view=operations&section=system-alerts&alert=' . rawurlencode((string) $alert['alert_key']),
            'detail' => substr((string) $alert['summary'], 0, 240), 'priority' => 20,
        ];
    }
    $openConflicts = $db->GetAll(
        "SELECT * FROM project_company_operations_sync_conflict WHERE company_key_hash=? AND status='OPEN' ORDER BY created_at DESC,x_id DESC LIMIT 5",
        [$companyKeyHash]
    ) ?: [];
    foreach ($openConflicts as $conflict) {
        $queue[] = [
            'key' => 'conflict:' . (string) $conflict['conflict_key'], 'label' => 'Resolve ' . (string) $conflict['source_contract'],
            'status' => 'OPEN', 'href' => '?view=operations&section=sync-conflict-dashboard&conflict=' . rawurlencode((string) $conflict['conflict_key']),
            'detail' => substr((string) $conflict['summary'], 0, 240), 'priority' => 30,
        ];
    }
    $deletionReviews = $db->GetAll(
        "SELECT * FROM project_company_operations_deletion_request WHERE company_key_hash=? AND status='QUEUED' ORDER BY created_at DESC,x_id DESC LIMIT 5",
        [$companyKeyHash]
    ) ?: [];
    foreach ($deletionReviews as $request) {
        $queue[] = [
            'key' => 'deletion:' . (string) $request['request_key'], 'label' => 'Review deletion: ' . (string) $request['record_type'],
            'status' => 'REVIEW', 'href' => '?view=operations&section=governed-deletion&request=' . rawurlencode((string) $request['request_key']),
            'detail' => (int) $request['target_count'] . ' target(s) remain queued.', 'priority' => 40,
        ];
    }
    usort($queue, static fn (array $left, array $right): int => [$left['priority'], $left['key']] <=> [$right['priority'], $right['key']]);
    $queue = array_slice($queue, 0, 20);

    $activity = [];
    $auditRows = $db->GetAll(
        "SELECT audit_key,action,module,record_key,new_values,created_at FROM builder_audit_log WHERE module LIKE 'project_company_operations_%' AND new_values LIKE ? ORDER BY created_at DESC,x_id DESC LIMIT 20",
        ['%' . $companyKeyHash . '%']
    ) ?: [];
    foreach ($auditRows as $row) {
        $values = [];
        try {
            $values = is_string($row['new_values'] ?? null) && $row['new_values'] !== ''
                ? yovel_admin_operations_json_array((string) $row['new_values'], 'Operations dashboard audit values')
                : [];
        } catch (Throwable) {
            $values = [];
        }
        $module = preg_replace('/^project_company_operations_/', '', (string) $row['module']) ?? 'operations';
        $activity[] = [
            'key' => (string) $row['audit_key'],
            'action' => (string) $row['action'],
            'record_label' => ucwords(str_replace('_', ' ', $module)) . ((string) ($row['record_key'] ?? '') !== '' ? ' ' . (string) $row['record_key'] : ''),
            'actor_label' => substr((string) ($values['admin_key'] ?? 'Operations service'), 0, 160),
            'status' => (string) ($values['status'] ?? $values['recovered_status'] ?? $row['action']),
            'occurred_at' => (string) $row['created_at'],
        ];
    }

    $activeScheduleCount = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_operations_schedule WHERE company_key_hash=? AND schedule_status='ACTIVE'",
        [$companyKeyHash]
    );
    $workerCount = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_operations_worker WHERE company_key_hash=?', [$companyKeyHash]);
    $publishedFormCount = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_operations_builder_form WHERE company_key_hash=? AND form_status='PUBLISHED'",
        [$companyKeyHash]
    );
    $setup = [
        ['key' => 'schedule-ready', 'label' => 'Activate an execution schedule', 'complete' => $activeScheduleCount > 0, 'href' => '?view=operations&section=scheduled-jobs'],
        ['key' => 'worker-ready', 'label' => 'Verify a worker lease', 'complete' => $workerCount > 0, 'href' => '?view=operations&section=background-workers'],
        ['key' => 'release-ready', 'label' => 'Pass the release checklist', 'complete' => $releaseState === 'PASS', 'href' => '?view=operations&section=release-checklist'],
        ['key' => 'form-ready', 'label' => 'Publish an Operations form', 'complete' => $publishedFormCount > 0, 'href' => '?view=operations&section=dashboard#operations-dashboard-form-builder'],
    ];

    $alerts = [];
    foreach (array_slice($activeAlerts, 0, 6) as $alert) {
        $alerts[] = [
            'key' => (string) $alert['alert_key'], 'severity' => (string) $alert['severity'],
            'label' => (string) $alert['title'], 'href' => '?view=operations&section=system-alerts&alert=' . rawurlencode((string) $alert['alert_key']),
        ];
    }
    if (in_array($releaseState, ['FAIL', 'WARN'], true)) {
        $alerts[] = ['key' => 'release-readiness-' . strtolower($releaseState), 'severity' => $releaseState === 'FAIL' ? 'CRITICAL' : 'WARNING', 'label' => 'Release readiness is ' . strtolower($releaseState) . '.', 'href' => '?view=operations&section=release-checklist'];
    }

    $shortcuts = [
        ['key' => 'scheduled-jobs', 'label' => 'Scheduled Jobs', 'href' => '?view=operations&section=scheduled-jobs', 'available' => true],
        ['key' => 'notifications', 'label' => 'Notifications', 'href' => '?view=operations&section=notifications', 'available' => true],
        ['key' => 'workers', 'label' => 'Background Workers', 'href' => '?view=operations&section=background-workers', 'available' => true],
        ['key' => 'conflicts', 'label' => 'Sync Conflicts', 'href' => '?view=operations&section=sync-conflict-dashboard', 'available' => true],
        ['key' => 'imports', 'label' => 'Import / Export', 'href' => '?view=operations&section=import-export-jobs', 'available' => true],
        ['key' => 'alerts', 'label' => 'System Alerts', 'href' => '?view=operations&section=system-alerts', 'available' => true],
        ['key' => 'releases', 'label' => 'Release Checklist', 'href' => '?view=operations&section=release-checklist', 'available' => true],
        ['key' => 'deletions', 'label' => 'Governed Deletion', 'href' => '?view=operations&section=governed-deletion', 'available' => true],
        ['key' => 'form-builder', 'label' => 'Form Builder', 'href' => '?view=operations&section=dashboard#operations-dashboard-form-builder', 'available' => true],
    ];
    $directories = [
        ['group' => 'Execution', 'items' => array_slice($shortcuts, 0, 5)],
        ['group' => 'Assurance', 'items' => array_slice($shortcuts, 5, 3)],
        ['group' => 'Setup', 'items' => [
            ['key' => 'authorization', 'label' => 'Authorization', 'href' => '?view=operations&section=authorization-setup', 'available' => true],
            ['key' => 'company-defaults', 'label' => 'Company & Defaults', 'href' => '?view=operations&section=company-defaults', 'available' => true],
            $shortcuts[8],
        ]],
    ];
    $dependencies = [
        ['contract' => 'operations.job-runner.v1', 'status' => 'AVAILABLE'],
        ['contract' => 'shared.audit-log.v1', 'status' => 'AVAILABLE'],
        ['contract' => 'shared.form-builder.v1', 'status' => 'AVAILABLE'],
        ['contract' => 'shared.modal-confirmation.v1', 'status' => 'AVAILABLE'],
    ];

    return compact('summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directories', 'dependencies');
}
