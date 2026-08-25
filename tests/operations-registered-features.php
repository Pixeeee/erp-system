<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

$company = operations_test_company('Operations Feature Company');
$admin = operations_test_admin();
operations_test_register_cleanup($company);
yovel_admin_operations_schema();

$schedule = yovel_admin_operations_save_schedule(bx_db(), $company, $admin, [
    'schedule_name' => 'Nightly integrity scan',
    'job_type' => 'SCHEDULED',
    'cron_expression' => '15 2 * * *',
    'payload' => ['check' => 'integrity'],
    'schedule_status' => 'ACTIVE',
]);
operations_test_assert(yovel_admin_is_uuid((string) ($schedule['schedule_key'] ?? '')), 'Schedule did not return a stable key.');
operations_test_assert((string) $schedule['cron_expression'] === '15 2 * * *', 'Schedule read-back changed its expression.');

$conflict = yovel_admin_operations_save_sync_conflict(bx_db(), $company, $admin, [
    'source_contract' => 'owners.workspace-directory.v1',
    'owner_record_key' => 'workspace-orders',
    'local_snapshot' => ['label' => 'Orders'],
    'remote_snapshot' => ['label' => 'Order workspace'],
    'summary' => 'Workspace labels diverged.',
]);
operations_test_assert((string) $conflict['status'] === 'OPEN', 'Sync conflict did not open.');
$resolved = yovel_admin_operations_resolve_sync_conflict(bx_db(), $company, $admin, (string) $conflict['conflict_key'], 'ACCEPT_REMOTE', 'Owner directory is authoritative.');
operations_test_assert((string) $resolved['status'] === 'RESOLVED' && (string) $resolved['resolution'] === 'ACCEPT_REMOTE', 'Sync conflict resolution did not read back.');

$alert = yovel_admin_operations_save_system_alert(bx_db(), $company, $admin, [
    'alert_code' => 'WORKER_LAG', 'severity' => 'WARNING', 'title' => 'Worker queue delay',
    'summary' => 'Oldest queued work is outside the service target.',
]);
operations_test_assert((string) $alert['status'] === 'ACTIVE', 'System alert did not become active.');
$acknowledged = yovel_admin_operations_acknowledge_alert(bx_db(), $company, $admin, (string) $alert['alert_key'], 'Operations team investigating.');
operations_test_assert((string) $acknowledged['status'] === 'ACKNOWLEDGED', 'System alert acknowledgment did not persist.');

$release = yovel_admin_operations_save_release_check(bx_db(), $company, $admin, [
    'check_code' => 'JOB_LIFECYCLE', 'check_label' => 'Job lifecycle suite', 'status' => 'PASS',
    'evidence_summary' => 'Queue, claim, retry, cancellation, and cleanup checks passed twice.',
]);
operations_test_assert(yovel_admin_is_uuid((string) ($release['release_check_key'] ?? '')), 'Release check did not return a stable key.');
operations_test_assert((string) $release['status'] === 'PASS' && (string) $release['checked_by_admin_key'] === (string) $admin['admin_key'], 'Release check lacks real actor/status evidence.');

$import = yovel_admin_operations_enqueue_job(bx_db(), $company, $admin, [
    'idempotency_key' => 'import-customers-001', 'job_type' => 'IMPORT', 'source_section' => 'import-export-jobs',
    'payload' => ['attachment_ref' => 'attachment-ref-001', 'record_type' => 'customer'],
]);
$export = yovel_admin_operations_enqueue_job(bx_db(), $company, $admin, [
    'idempotency_key' => 'export-customers-001', 'job_type' => 'EXPORT', 'source_section' => 'import-export-jobs',
    'payload' => ['record_type' => 'customer', 'format' => 'csv'],
]);
operations_test_assert((string) $import['job_type'] === 'IMPORT' && (string) $export['job_type'] === 'EXPORT', 'Import/export surfaces are not backed by real jobs.');

$sourceFiles = [
    'jobs.php',
    'sync.php',
    'import-export.php',
    'alerts.php',
    'release.php',
    'bulk.php',
    'deletion.php',
];
foreach ($sourceFiles as $viewFile) {
    $path = dirname(__DIR__) . '/company/admin/modules/operations/views/sections/' . $viewFile;
    operations_test_assert(is_file($path), 'Registered Operations surface is missing: ' . $viewFile);
    $source = (string) file_get_contents($path);
    operations_test_assert(str_contains($source, 'module_view') && str_contains($source, 'operations'), $viewFile . ' is missing module_view=operations.');
    operations_test_assert(str_contains($source, 'data-record-modal') || str_contains($source, 'record-modal.php'), $viewFile . ' does not use a record modal.');
    operations_test_assert(str_contains($source, 'data-confirm-submit') || str_contains($source, 'record-modal.php'), $viewFile . ' does not consume confirmation behavior.');
    operations_test_assert(!str_contains($source, 'data-confirm-dialog'), $viewFile . ' duplicates the shared confirmation dialog.');
}

$functionReflection = new ReflectionFunction('yovel_admin_operations_handle_post');
operations_test_assert($functionReflection->getNumberOfRequiredParameters() === 4, 'Operations POST provider does not expose the exact shared dispatch signature.');
$workspaceSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/workspace.php');
operations_test_assert(str_contains($workspaceSource, 'module_view') && str_contains($workspaceSource, 'operations'), 'Operations workspace forms omit module_view=operations.');
operations_test_assert(!preg_match('/(?:password|secret|api[_-]?key)\s*[=:]\s*[\'\"][^\'\"]+/i', $workspaceSource), 'Operations views contain a raw secret-like value.');

echo "Operations registered feature checks passed.\n";
