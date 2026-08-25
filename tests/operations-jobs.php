<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

if (($argv[1] ?? '') === 'claim-race-worker') {
    $raceCompany = json_decode(base64_decode((string) ($argv[2] ?? ''), true) ?: '', true, 32, JSON_THROW_ON_ERROR);
    $raceClaim = yovel_admin_operations_claim_job(bx_db(), $raceCompany, (string) ($argv[3] ?? ''), 60);
    echo json_encode(['job_key' => $raceClaim['job_key'] ?? null], JSON_THROW_ON_ERROR);
    exit(0);
}

$company = operations_test_company('Operations Job Company');
$otherCompany = operations_test_company('Other Operations Job Company');
$admin = operations_test_admin();
$inactiveAdmin = [...$admin, 'admin_key' => bx_uuid(), 'admin_status' => 'INACTIVE'];
operations_test_register_cleanup($company);
operations_test_register_cleanup($otherCompany);

yovel_admin_operations_schema();

$input = [
    'idempotency_key' => 'scheduled-daily-close-2026-08-25',
    'job_type' => 'SCHEDULED',
    'source_section' => 'scheduled-jobs',
    'payload' => ['command' => 'daily-close', 'date' => '2026-08-25'],
    'max_attempts' => 3,
    'priority' => 20,
];
$queued = yovel_admin_operations_enqueue_job(bx_db(), $company, $admin, $input);
$duplicate = yovel_admin_operations_enqueue_job(bx_db(), $company, $admin, $input);
operations_test_assert((string) $queued['job_key'] === (string) $duplicate['job_key'], 'Idempotent enqueue produced a second job.');
operations_test_assert((string) $queued['status'] === 'QUEUED', 'New job was not queued.');
operations_test_assert((int) bx_db()->GetOne(
    'SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=? AND idempotency_key=?',
    [$company['company_key_hash'], $input['idempotency_key']]
) === 1, 'Idempotency key is not company-unique.');

operations_test_expect_exception(
    static fn () => yovel_admin_operations_enqueue_job(bx_db(), $company, $inactiveAdmin, $input),
    'authorized'
);
operations_test_expect_exception(
    static fn () => yovel_admin_operations_enqueue_job(bx_db(), $company, $admin, [
        ...$input,
        'idempotency_key' => 'raw-secret-rejected',
        'payload' => ['api_token' => 'must-not-persist'],
    ]),
    'secret'
);

$claimed = yovel_admin_operations_claim_job(bx_db(), $company, 'worker-alpha', 60);
operations_test_assert((string) ($claimed['job_key'] ?? '') === (string) $queued['job_key'], 'Worker did not claim the queued job.');
operations_test_assert((string) $claimed['status'] === 'RUNNING' && (int) $claimed['attempt_count'] === 1, 'Claim did not atomically start attempt one.');
operations_test_assert(yovel_admin_operations_claim_job(bx_db(), $company, 'worker-beta', 60) === null, 'A running leased job was claimed twice.');

operations_test_expect_exception(
    static fn () => yovel_admin_operations_complete_job(bx_db(), $company, $admin, (string) $queued['job_key'], 'worker-beta', ['ok' => true]),
    'lease'
);
$stillRunning = yovel_admin_operations_job($company, (string) $queued['job_key']);
operations_test_assert((string) ($stillRunning['status'] ?? '') === 'RUNNING', 'Rejected completion partially mutated the job.');

$completed = yovel_admin_operations_complete_job(bx_db(), $company, $admin, (string) $queued['job_key'], 'worker-alpha', ['ok' => true, 'rows' => 12]);
operations_test_assert((string) $completed['status'] === 'SUCCEEDED', 'Valid completion did not persist.');
operations_test_assert((int) bx_db()->GetOne(
    'SELECT COUNT(*) FROM project_company_operations_job_attempt WHERE company_key_hash=? AND job_key=? AND attempt_status=\'SUCCEEDED\'',
    [$company['company_key_hash'], $queued['job_key']]
) === 1, 'Successful attempt history was not retained.');
operations_test_assert(yovel_admin_operations_job($otherCompany, (string) $queued['job_key']) === null, 'Job read leaked across company scope.');

$retryJob = yovel_admin_operations_enqueue_job(bx_db(), $company, $admin, [
    'idempotency_key' => 'retry-bounded-job',
    'job_type' => 'NOTIFICATION',
    'source_section' => 'notifications',
    'payload' => ['notification_key' => 'notice-001'],
    'max_attempts' => 2,
]);
$retryClaim = yovel_admin_operations_claim_job(bx_db(), $company, 'worker-alpha', 60);
operations_test_assert((string) ($retryClaim['job_key'] ?? '') === (string) $retryJob['job_key'], 'Retry fixture was not claimed.');
$retryQueued = yovel_admin_operations_fail_job(bx_db(), $company, $admin, (string) $retryJob['job_key'], 'worker-alpha', 'Temporary provider error', true);
operations_test_assert((string) $retryQueued['status'] === 'QUEUED', 'Retryable first failure did not return to the queue.');
operations_test_assert(strtotime((string) $retryQueued['available_at']) > time(), 'Retry backoff was not persisted.');
bx_db()->Execute('UPDATE project_company_operations_job SET available_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND job_key=?', [$company['company_key_hash'], $retryJob['job_key']]);
$retryClaimTwo = yovel_admin_operations_claim_job(bx_db(), $company, 'worker-beta', 60);
operations_test_assert((int) ($retryClaimTwo['attempt_count'] ?? 0) === 2, 'Retry did not create immutable attempt two.');
$retryFailed = yovel_admin_operations_fail_job(bx_db(), $company, $admin, (string) $retryJob['job_key'], 'worker-beta', 'Permanent provider error', true);
operations_test_assert((string) $retryFailed['status'] === 'FAILED', 'Bounded retries exceeded max attempts.');

$leaseJob = yovel_admin_operations_enqueue_job(bx_db(), $company, $admin, [
    'idempotency_key' => 'lease-expiry-job', 'job_type' => 'EXPORT', 'source_section' => 'import-export-jobs',
    'payload' => ['record_type' => 'orders'], 'max_attempts' => 3,
]);
$leaseClaim = yovel_admin_operations_claim_job(bx_db(), $company, 'worker-alpha', 1);
operations_test_assert((string) ($leaseClaim['job_key'] ?? '') === (string) $leaseJob['job_key'], 'Lease fixture was not claimed.');
bx_db()->Execute('UPDATE project_company_operations_job SET lease_expires_at=DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 SECOND) WHERE company_key_hash=? AND job_key=?', [$company['company_key_hash'], $leaseJob['job_key']]);
$reclaimed = yovel_admin_operations_claim_job(bx_db(), $company, 'worker-beta', 60);
operations_test_assert((string) ($reclaimed['job_key'] ?? '') === (string) $leaseJob['job_key'], 'Expired lease was not reclaimed.');
operations_test_assert((int) $reclaimed['attempt_count'] === 2, 'Expired lease did not create a new immutable attempt.');
operations_test_assert((int) bx_db()->GetOne(
    'SELECT COUNT(*) FROM project_company_operations_job_attempt WHERE company_key_hash=? AND job_key=?',
    [$company['company_key_hash'], $leaseJob['job_key']]
) === 2, 'Lease recovery rewrote attempt history.');

$cancelJob = yovel_admin_operations_enqueue_job(bx_db(), $company, $admin, [
    'idempotency_key' => 'cancel-job', 'job_type' => 'IMPORT', 'source_section' => 'import-export-jobs',
    'payload' => ['attachment_ref' => 'opaque-file-reference'],
]);
$cancelled = yovel_admin_operations_cancel_job(bx_db(), $company, $admin, (string) $cancelJob['job_key'], 'Operator withdrew the request.');
operations_test_assert((string) $cancelled['status'] === 'CANCELLED', 'Queued cancellation did not persist.');
operations_test_expect_exception(
    static fn () => yovel_admin_operations_cancel_job(bx_db(), $otherCompany, $admin, (string) $cancelJob['job_key'], 'Cross company'),
    'not found'
);

$raceJob = yovel_admin_operations_enqueue_job(bx_db(), $company, $admin, [
    'idempotency_key' => 'concurrent-claim-job', 'job_type' => 'GENERAL', 'source_section' => 'background-workers',
    'payload' => ['purpose' => 'claim-race'],
]);
$raceCompany = base64_encode(json_encode($company, JSON_THROW_ON_ERROR));
$processes = [];
foreach (['worker-race-a', 'worker-race-b'] as $worker) {
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, __FILE__, 'claim-race-worker', $raceCompany, $worker],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    operations_test_assert(is_resource($process), 'Could not start a concurrent Operations claim worker.');
    fclose($pipes[0]);
    $processes[] = [$process, $pipes];
}
$raceWinners = [];
foreach ($processes as [$process, $pipes]) {
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    operations_test_assert($exitCode === 0, 'Concurrent claim worker failed: ' . trim((string) $stderr));
    $raceResult = json_decode((string) $stdout, true, 32, JSON_THROW_ON_ERROR);
    if (($raceResult['job_key'] ?? null) !== null) {
        $raceWinners[] = (string) $raceResult['job_key'];
    }
}
operations_test_assert($raceWinners === [(string) $raceJob['job_key']], 'Concurrent workers did not produce exactly one claim winner.');

echo "Operations job lifecycle checks passed.\n";
