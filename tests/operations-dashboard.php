<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

$company = operations_test_company('Operations Dashboard Company');
$otherCompany = operations_test_company('Other Dashboard Company');
$admin = operations_test_admin();
$otherAdmin = operations_test_admin();
operations_test_register_cleanup($company);
operations_test_register_cleanup($otherCompany);
yovel_admin_operations_schema();
$db = bx_db();

$running = yovel_admin_operations_enqueue_job($db, $company, $admin, [
    'idempotency_key' => 'dashboard-running-job', 'job_type' => 'GENERAL', 'source_section' => 'background-workers',
    'payload' => ['purpose' => 'dashboard-running'],
]);
$running = yovel_admin_operations_claim_job($db, $company, 'dashboard-worker-running', 300);
operations_test_assert((string) ($running['status'] ?? '') === 'RUNNING', 'Dashboard running fixture was not claimed.');

$failed = yovel_admin_operations_enqueue_job($db, $company, $admin, [
    'idempotency_key' => 'dashboard-failed-job', 'job_type' => 'SYNC', 'source_section' => 'sync-conflict-dashboard',
    'payload' => ['purpose' => 'dashboard-failure'], 'max_attempts' => 1,
]);
$failedClaim = yovel_admin_operations_claim_job($db, $company, 'dashboard-worker-failed', 300);
$failed = yovel_admin_operations_fail_job($db, $company, $admin, (string) $failedClaim['job_key'], 'dashboard-worker-failed', 'Dashboard fixture failure.', false);
operations_test_assert((string) $failed['status'] === 'FAILED', 'Dashboard failed fixture did not fail.');

$queued = yovel_admin_operations_enqueue_job($db, $company, $admin, [
    'idempotency_key' => 'dashboard-queued-job', 'job_type' => 'SCHEDULED', 'source_section' => 'scheduled-jobs',
    'payload' => ['purpose' => 'dashboard-queued'],
]);
$alert = yovel_admin_operations_save_system_alert($db, $company, $admin, [
    'alert_code' => 'DASHBOARD_QUEUE_LAG', 'severity' => 'CRITICAL', 'title' => 'Queue delay', 'summary' => 'Queued work exceeded the operating target.',
]);
$conflict = yovel_admin_operations_save_sync_conflict($db, $company, $admin, [
    'source_contract' => 'owners.workspace-directory.v1', 'owner_record_key' => 'dashboard-workspace',
    'local_snapshot' => ['label' => 'Local'], 'remote_snapshot' => ['label' => 'Owner'], 'summary' => 'Workspace labels differ.',
]);
yovel_admin_operations_save_schedule($db, $company, $admin, [
    'schedule_name' => 'Dashboard integrity scan', 'job_type' => 'SCHEDULED', 'cron_expression' => '15 2 * * *',
    'payload' => ['check' => 'integrity'], 'schedule_status' => 'ACTIVE',
]);
yovel_admin_operations_save_release_check($db, $company, $admin, [
    'check_code' => 'DASHBOARD_RELEASE', 'check_label' => 'Dashboard release gate', 'status' => 'FAIL',
    'evidence_summary' => 'A dashboard release blocker remains open.',
]);
yovel_admin_persist_operations_builder_form($db, $company, $admin, [
    'target_section' => 'dashboard', 'form_title' => 'Dashboard review', 'form_description' => 'Dashboard review fields.',
    'form_status' => 'PUBLISHED', 'schema' => ['rows' => [['key' => 'row', 'columns' => [['key' => 'column', 'width' => 12, 'field_keys' => []]]]], 'fields' => []],
]);
$directoryProvider = static fn (array $scope, array $query): array => [
    'ok' => true, 'company_key_hash' => $scope['company_key_hash'],
    'records' => [['record_type' => 'Dashboard Fixture', 'owner_contract' => 'owners.governed-delete.v1', 'deletable' => true]], 'errors' => [],
];
$deletion = yovel_admin_operations_submit_deletion($db, $company, $admin, [
    'idempotency_key' => 'dashboard-deletion-review', 'record_type' => 'Dashboard Fixture',
    'targets' => [['record_key' => 'dashboard-target-001']], 'consequence' => 'Fixture deletion requires review.', 'defer_execution' => true,
], ['shared.record-type-directory.v1' => $directoryProvider]);
operations_test_assert((string) $deletion['status'] === 'QUEUED', 'Dashboard deletion fixture was not queued.');
yovel_admin_operations_save_system_alert($db, $otherCompany, $otherAdmin, [
    'alert_code' => 'OTHER_COMPANY_ALERT', 'severity' => 'WARNING', 'title' => 'Other company', 'summary' => 'Must remain isolated.',
]);

$dashboard = yovel_admin_operations_dashboard_data($company, $admin);
operations_test_assert(array_keys($dashboard) === ['summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directories', 'dependencies'], 'Operations dashboard returned the wrong shared envelope.');
$summary = array_column($dashboard['summary'], null, 'key');
operations_test_assert((int) ($summary['queued-jobs']['value'] ?? -1) === 2, 'Dashboard queued-job KPI is not live.');
operations_test_assert((int) ($summary['running-jobs']['value'] ?? -1) === 1, 'Dashboard running-job KPI is not live.');
operations_test_assert((int) ($summary['failed-jobs']['value'] ?? -1) === 1, 'Dashboard failed-job KPI is not live.');
operations_test_assert((int) ($summary['active-alerts']['value'] ?? -1) === 1, 'Dashboard active-alert KPI is not live or company isolated.');
operations_test_assert((int) ($summary['unresolved-conflicts']['value'] ?? -1) === 1, 'Dashboard unresolved-conflict KPI is not live.');
operations_test_assert((string) ($summary['release-readiness']['value'] ?? '') === 'FAIL', 'Dashboard release-readiness KPI ignored the latest evidence.');
foreach ($dashboard['summary'] as $item) {
    operations_test_assert(in_array($item['availability'] ?? '', ['AVAILABLE', 'UNAVAILABLE_DEPENDENCY', 'NOT_IMPLEMENTED', 'ERROR'], true), 'Dashboard KPI has an invalid availability state.');
}

$queueKeys = array_column($dashboard['queue'], 'key');
operations_test_assert(in_array('job:' . $failed['job_key'], $queueKeys, true), 'Dashboard action queue omitted a failed job.');
operations_test_assert(in_array('alert:' . $alert['alert_key'], $queueKeys, true), 'Dashboard action queue omitted an active alert.');
operations_test_assert(in_array('conflict:' . $conflict['conflict_key'], $queueKeys, true), 'Dashboard action queue omitted an open conflict.');
operations_test_assert(in_array('deletion:' . $deletion['request_key'], $queueKeys, true), 'Dashboard action queue omitted a deletion review.');
operations_test_assert(count($dashboard['queue']) <= 20, 'Dashboard action queue is not bounded.');
operations_test_assert($dashboard['activity'] !== [] && count($dashboard['activity']) <= 20, 'Dashboard recent activity is empty or unbounded.');
operations_test_assert(!str_contains(json_encode($dashboard, JSON_THROW_ON_ERROR), 'OTHER_COMPANY_ALERT'), 'Dashboard leaked another company through activity or alerts.');
operations_test_assert(count($dashboard['setup']) >= 4, 'Dashboard setup progress is incomplete.');
operations_test_assert(count($dashboard['shortcuts']) >= 8, 'Dashboard shortcuts are incomplete.');
operations_test_assert(count($dashboard['directories']) >= 3, 'Dashboard reports and masters directories are incomplete.');
operations_test_assert(count($dashboard['dependencies']) >= 3, 'Dashboard dependency health is incomplete.');

$sections = yovel_admin_operations_sections();
operations_test_assert(array_key_first($sections) === 'dashboard', 'Operations dashboard is not the first registered section.');
operations_test_assert(yovel_admin_operations_section('unknown-section') === 'dashboard', 'Unknown Operations sections do not fall back to dashboard.');
$data = yovel_admin_operations_data($company, $admin);
operations_test_assert(($data['dashboard']['summary'][0]['key'] ?? '') === 'queued-jobs', 'Operations data dispatch omitted the live dashboard provider.');

$activeModuleSection = 'dashboard';
$activeModuleMeta = $sections['dashboard'];
$activeModuleData = $data;
$activeModuleFormState = [];
$companyName = (string) $company['company_name'];
ob_start();
require $operationsTestRoot . '/company/admin/modules/operations/views/workspace.php';
$markup = (string) ob_get_clean();
foreach ([
    'data-operations-dashboard', 'data-dashboard-summary', 'data-dashboard-queue', 'data-dashboard-activity',
    'data-dashboard-setup', 'data-dashboard-alerts', 'data-dashboard-shortcuts', 'data-dashboard-directories',
    'data-operations-dashboard-tour', 'Show Tour', 'Form Builder', 'data-record-modal', 'data-confirm-submit',
    'retry_operations_job', 'acknowledge_operations_alert', 'resolve_operations_conflict',
    'xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]',
] as $marker) {
    operations_test_assert(str_contains($markup, $marker), 'Operations dashboard markup is missing: ' . $marker);
}
operations_test_assert(!str_contains($markup, 'name="action" value="submit_operations_deletion"'), 'Dashboard invented a deletion approval action without an existing handler contract.');
operations_test_assert(str_contains($markup, '?view=operations&amp;section=governed-deletion'), 'Dashboard deletion review does not route to its owning Operations section.');
$mainPosition = strpos($markup, 'data-operations-main-panel');
$toolsPosition = strpos($markup, 'data-operations-tools-panel');
operations_test_assert($mainPosition !== false && $toolsPosition !== false && $mainPosition < $toolsPosition, 'Operations dashboard does not stack main-first in DOM order.');
operations_test_assert(is_file($operationsTestRoot . '/company/admin/modules/operations/views/dashboard.php'), 'Operations dashboard view file is missing.');
operations_test_assert(!str_contains($markup, 'ERPNext'), 'Operations dashboard contains upstream branding.');

echo "Operations live dashboard checks passed.\n";
