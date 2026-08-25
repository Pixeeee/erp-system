<?php
declare(strict_types=1);

function yovel_admin_operations_safe_payload(array $payload, int $depth = 0): array
{
    if ($depth > 5) {
        throw new InvalidArgumentException('Operations payload nesting is too deep.');
    }
    $safe = [];
    foreach ($payload as $key => $value) {
        $key = (string) $key;
        $isReference = preg_match('/(?:_ref|_reference)$/i', $key) === 1;
        if (!$isReference && preg_match('/(?:password|passwd|secret|token|credential|api[_-]?key)/i', $key) === 1) {
            throw new InvalidArgumentException('Raw secret values are not accepted by Operations. Use an opaque reference.');
        }
        if (is_array($value)) {
            $safe[$key] = yovel_admin_operations_safe_payload($value, $depth + 1);
        } elseif (is_scalar($value) || $value === null) {
            $safe[$key] = is_string($value) ? substr($value, 0, 20000) : $value;
        }
    }
    return $safe;
}

function yovel_admin_operations_job_type(string $value): string
{
    $value = strtoupper(trim($value));
    if (!in_array($value, ['SCHEDULED', 'NOTIFICATION', 'IMPORT', 'EXPORT', 'SYNC', 'RELEASE', 'GENERAL'], true)) {
        throw new InvalidArgumentException('Operations job type is invalid.');
    }
    return $value;
}

function yovel_admin_operations_worker_key(string $value): string
{
    $value = trim($value);
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{1,119}$/', $value) !== 1) {
        throw new InvalidArgumentException('Operations worker key is invalid.');
    }
    return $value;
}

function yovel_admin_operations_database_datetime(ADOConnection $db, int $offsetSeconds = 0): string
{
    $current = trim((string) $db->GetOne('SELECT CURRENT_TIMESTAMP'));
    if ($current === '' || strtotime($current) === false) {
        throw new RuntimeException('Operations could not read the database clock.');
    }
    return date('Y-m-d H:i:s', (int) strtotime($current) + $offsetSeconds);
}

function yovel_admin_operations_hydrate_job(array $row): array
{
    $row['attempt_count'] = (int) ($row['attempt_count'] ?? 0);
    $row['max_attempts'] = (int) ($row['max_attempts'] ?? 0);
    $row['priority'] = (int) ($row['priority'] ?? 0);
    $row['progress_percent'] = (int) ($row['progress_percent'] ?? 0);
    $row['payload'] = yovel_admin_operations_json_array((string) ($row['payload_json'] ?? '{}'), 'Operations job payload');
    $row['result'] = ($row['result_json'] ?? null) !== null
        ? yovel_admin_operations_json_array((string) $row['result_json'], 'Operations job result')
        : null;
    return $row;
}

function yovel_admin_operations_job(array $company, string $jobKey): ?array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    if (!yovel_admin_is_uuid($jobKey)) {
        return null;
    }
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?',
        [$companyKeyHash, $jobKey]
    );
    return $row ? yovel_admin_operations_hydrate_job($row) : null;
}

function yovel_admin_operations_jobs(array $company, ?string $sourceSection = null): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    $params = [$companyKeyHash];
    $where = 'company_key_hash=?';
    if ($sourceSection !== null && trim($sourceSection) !== '') {
        $where .= ' AND source_section=?';
        $params[] = yovel_admin_operations_builder_target($sourceSection);
    }
    $rows = bx_db()->GetAll("SELECT * FROM project_company_operations_job WHERE {$where} ORDER BY created_at DESC,x_id DESC LIMIT 200", $params);
    return array_map('yovel_admin_operations_hydrate_job', $rows);
}

function yovel_admin_operations_enqueue_job(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
    $jobType = yovel_admin_operations_job_type((string) ($input['job_type'] ?? ''));
    $sourceSection = yovel_admin_operations_builder_target((string) ($input['source_section'] ?? 'scheduled-jobs'));
    $payload = yovel_admin_operations_safe_payload(is_array($input['payload'] ?? null) ? $input['payload'] : []);
    $payloadJson = yovel_admin_operations_json($payload);
    $priority = max(0, min(999, (int) ($input['priority'] ?? 50)));
    $maxAttempts = max(1, min(10, (int) ($input['max_attempts'] ?? 3)));
    if ($idempotencyKey === '' || strlen($idempotencyKey) > 160 || strlen($payloadJson) > 500000) {
        throw new InvalidArgumentException('Operations job idempotency or payload is invalid.');
    }
    $availableAt = trim((string) ($input['available_at'] ?? ''));
    if ($availableAt !== '' && strtotime($availableAt) === false) {
        throw new InvalidArgumentException('Operations job availability time is invalid.');
    }
    $availableAt = $availableAt !== '' ? date('Y-m-d H:i:s', (int) strtotime($availableAt)) : yovel_admin_operations_database_datetime($db);

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations enqueue transaction could not start.');
    }
    try {
        $existing = $db->GetRow(
            'SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND idempotency_key=? FOR UPDATE',
            [$companyKeyHash, $idempotencyKey]
        );
        if ($existing) {
            if ((string) $existing['job_type'] !== $jobType
                || (string) $existing['source_section'] !== $sourceSection
                || (string) $existing['payload_json'] !== $payloadJson) {
                throw new InvalidArgumentException('Operations idempotency key already represents different work.');
            }
            if ($db->CommitTrans() === false) {
                throw new RuntimeException('Operations enqueue transaction could not commit.');
            }
            return yovel_admin_operations_hydrate_job($existing);
        }

        $jobKey = bx_uuid();
        yovel_admin_operations_db_execute($db,
            'INSERT INTO project_company_operations_job (job_key,company_key,company_key_hash,idempotency_key,job_type,source_section,payload_json,status,priority,max_attempts,available_at,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,\'QUEUED\',?,?,?,?,?)',
            [$jobKey, $companyKey, $companyKeyHash, $idempotencyKey, $jobType, $sourceSection, $payloadJson, $priority, $maxAttempts, $availableAt, $adminKey, $adminKey],
            'Operations job enqueue'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $jobKey]);
        if (!$saved
            || (string) $saved['company_key'] !== $companyKey
            || (string) $saved['idempotency_key'] !== $idempotencyKey
            || (string) $saved['job_type'] !== $jobType
            || (string) $saved['source_section'] !== $sourceSection
            || (string) $saved['payload_json'] !== $payloadJson
            || (string) $saved['status'] !== 'QUEUED'
            || (int) $saved['max_attempts'] !== $maxAttempts) {
            throw new RuntimeException('Operations job enqueue read-back verification failed.');
        }
        yovel_admin_operations_audit('ENQUEUE', 'project_company_operations_job', $jobKey, $companyKeyHash, (string) $adminKey, [
            'idempotency_key' => $idempotencyKey, 'job_type' => $jobType, 'source_section' => $sourceSection,
        ], 'Company administrator confirmed an Operations job enqueue.');
        $enqueueHook = $GLOBALS['yovel_admin_operations_job_enqueue_hook'] ?? null;
        if (is_callable($enqueueHook)) {
            $enqueueHook($db, $saved);
        }
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Operations enqueue transaction could not commit.');
        }
        return yovel_admin_operations_hydrate_job($saved);
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_operations_expire_stale_leases(ADOConnection $db, string $companyKeyHash): void
{
    $stale = $db->GetAll(
        "SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND status='RUNNING' AND lease_expires_at<CURRENT_TIMESTAMP FOR UPDATE",
        [$companyKeyHash]
    );
    foreach ($stale as $job) {
        yovel_admin_operations_db_execute($db,
            "UPDATE project_company_operations_job_attempt SET attempt_status='EXPIRED',finished_at=CURRENT_TIMESTAMP,error_summary='Worker lease expired.' WHERE company_key_hash=? AND job_key=? AND attempt_number=? AND attempt_status='RUNNING'",
            [$companyKeyHash, $job['job_key'], $job['attempt_count']],
            'Operations stale attempt expiry'
        );
        $status = (int) $job['attempt_count'] < (int) $job['max_attempts'] ? 'QUEUED' : 'FAILED';
        yovel_admin_operations_db_execute($db,
            'UPDATE project_company_operations_job SET status=?,available_at=CURRENT_TIMESTAMP,leased_by_worker_key=NULL,lease_expires_at=NULL,error_summary=?,finished_at=IF(?=\'FAILED\',CURRENT_TIMESTAMP,NULL) WHERE company_key_hash=? AND job_key=? AND status=\'RUNNING\'',
            [$status, 'Worker lease expired.', $status, $companyKeyHash, $job['job_key']],
            'Operations stale job recovery'
        );
        yovel_admin_operations_db_execute($db,
            "UPDATE project_company_operations_worker SET worker_status='OFFLINE',current_job_key=NULL,lease_expires_at=NULL WHERE company_key_hash=? AND worker_key=? AND current_job_key=?",
            [$companyKeyHash, $job['leased_by_worker_key'], $job['job_key']],
            'Operations stale worker release'
        );
        $savedJob = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $job['job_key']]);
        $savedAttempt = $db->GetRow('SELECT * FROM project_company_operations_job_attempt WHERE company_key_hash=? AND job_key=? AND attempt_number=?', [$companyKeyHash, $job['job_key'], $job['attempt_count']]);
        if (!$savedJob || !$savedAttempt
            || (string) $savedJob['status'] !== $status
            || $savedJob['leased_by_worker_key'] !== null
            || $savedJob['lease_expires_at'] !== null
            || (string) $savedAttempt['attempt_status'] !== 'EXPIRED'
            || (string) $savedAttempt['error_summary'] !== 'Worker lease expired.') {
            throw new RuntimeException('Operations stale lease read-back verification failed.');
        }
        yovel_admin_operations_audit('EXPIRE', 'project_company_operations_job', (string) $job['job_key'], $companyKeyHash, 'worker:' . (string) $job['leased_by_worker_key'], [
            'attempt_number' => (int) $job['attempt_count'], 'recovered_status' => $status,
        ], 'Operations recovered an expired worker lease.');
    }
}

function yovel_admin_operations_claim_job(ADOConnection $db, array $company, string $workerKey, int $leaseSeconds): ?array
{
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    $workerKey = yovel_admin_operations_worker_key($workerKey);
    $leaseSeconds = max(1, min(3600, $leaseSeconds));
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations claim transaction could not start.');
    }
    try {
        yovel_admin_operations_expire_stale_leases($db, $companyKeyHash);
        $job = $db->GetRow(
            "SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND status='QUEUED' AND available_at<=CURRENT_TIMESTAMP AND attempt_count<max_attempts ORDER BY priority DESC,x_id ASC LIMIT 1 FOR UPDATE",
            [$companyKeyHash]
        );
        if (!$job) {
            if ($db->CommitTrans() === false) {
                throw new RuntimeException('Operations claim transaction could not commit.');
            }
            return null;
        }
        $attemptNumber = (int) $job['attempt_count'] + 1;
        $leaseExpiresAt = yovel_admin_operations_database_datetime($db, $leaseSeconds);
        yovel_admin_operations_db_execute($db,
            "UPDATE project_company_operations_job SET status='RUNNING',attempt_count=?,leased_by_worker_key=?,lease_expires_at=?,started_at=COALESCE(started_at,CURRENT_TIMESTAMP),error_summary=NULL WHERE company_key_hash=? AND job_key=? AND status='QUEUED'",
            [$attemptNumber, $workerKey, $leaseExpiresAt, $companyKeyHash, $job['job_key']],
            'Operations job claim'
        );
        if ((int) $db->Affected_Rows() !== 1) {
            throw new RuntimeException('Operations job was claimed concurrently.');
        }
        $attemptKey = bx_uuid();
        yovel_admin_operations_db_execute($db,
            "INSERT INTO project_company_operations_job_attempt (attempt_key,job_key,company_key,company_key_hash,attempt_number,worker_key,attempt_status,lease_expires_at,started_at) VALUES (?,?,?,?,?,?,'RUNNING',?,CURRENT_TIMESTAMP)",
            [$attemptKey, $job['job_key'], $companyKey, $companyKeyHash, $attemptNumber, $workerKey, $leaseExpiresAt],
            'Operations job attempt start'
        );
        yovel_admin_operations_db_execute($db,
            "INSERT INTO project_company_operations_worker (worker_record_key,company_key,company_key_hash,worker_key,worker_status,current_job_key,lease_expires_at,last_seen_at) VALUES (?,?,?,?,'BUSY',?,?,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE worker_status='BUSY',current_job_key=VALUES(current_job_key),lease_expires_at=VALUES(lease_expires_at),last_seen_at=CURRENT_TIMESTAMP",
            [bx_uuid(), $companyKey, $companyKeyHash, $workerKey, $job['job_key'], $leaseExpiresAt],
            'Operations worker lease persistence'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $job['job_key']]);
        $attempt = $db->GetRow('SELECT * FROM project_company_operations_job_attempt WHERE company_key_hash=? AND attempt_key=?', [$companyKeyHash, $attemptKey]);
        $worker = $db->GetRow('SELECT * FROM project_company_operations_worker WHERE company_key_hash=? AND worker_key=?', [$companyKeyHash, $workerKey]);
        if (!$saved || !$attempt
            || !$worker
            || (string) $saved['status'] !== 'RUNNING'
            || (string) $saved['leased_by_worker_key'] !== $workerKey
            || (int) $saved['attempt_count'] !== $attemptNumber
            || (string) $attempt['attempt_status'] !== 'RUNNING'
            || (string) $attempt['worker_key'] !== $workerKey
            || (string) $worker['worker_status'] !== 'BUSY'
            || (string) $worker['current_job_key'] !== (string) $job['job_key']
            || (string) $worker['lease_expires_at'] !== $leaseExpiresAt) {
            throw new RuntimeException('Operations job claim read-back verification failed.');
        }
        yovel_admin_operations_audit('CLAIM', 'project_company_operations_job', (string) $job['job_key'], $companyKeyHash, 'worker:' . $workerKey, [
            'attempt_key' => $attemptKey, 'attempt_number' => $attemptNumber, 'lease_expires_at' => $leaseExpiresAt,
        ], 'Operations worker claimed a queued job.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Operations claim transaction could not commit.');
        }
        return yovel_admin_operations_hydrate_job($saved);
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_operations_lock_running_job(ADOConnection $db, string $companyKeyHash, string $jobKey, string $workerKey): array
{
    if (!yovel_admin_is_uuid($jobKey)) {
        throw new InvalidArgumentException('Operations job key is invalid.');
    }
    $workerKey = yovel_admin_operations_worker_key($workerKey);
    $job = $db->GetRow(
        "SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=? AND lease_expires_at>=CURRENT_TIMESTAMP FOR UPDATE",
        [$companyKeyHash, $jobKey]
    );
    if (!$job || (string) $job['status'] !== 'RUNNING' || (string) $job['leased_by_worker_key'] !== $workerKey) {
        throw new InvalidArgumentException('Operations job lease is not active for this worker.');
    }
    return $job;
}

function yovel_admin_operations_complete_job(ADOConnection $db, array $company, array $admin, string $jobKey, string $workerKey, array $result): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $resultJson = yovel_admin_operations_json(yovel_admin_operations_safe_payload($result));
    if (strlen($resultJson) > 500000) {
        throw new InvalidArgumentException('Operations job result is too large.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations completion transaction could not start.');
    }
    try {
        $job = yovel_admin_operations_lock_running_job($db, $companyKeyHash, $jobKey, $workerKey);
        yovel_admin_operations_db_execute($db,
            "UPDATE project_company_operations_job_attempt SET attempt_status='SUCCEEDED',result_json=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND job_key=? AND attempt_number=? AND worker_key=? AND attempt_status='RUNNING'",
            [$resultJson, $companyKeyHash, $jobKey, $job['attempt_count'], $workerKey],
            'Operations attempt completion'
        );
        yovel_admin_operations_db_execute($db,
            "UPDATE project_company_operations_job SET status='SUCCEEDED',progress_percent=100,result_json=?,error_summary=NULL,leased_by_worker_key=NULL,lease_expires_at=NULL,updated_by_admin_key=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND job_key=? AND status='RUNNING'",
            [$resultJson, $adminKey, $companyKeyHash, $jobKey],
            'Operations job completion'
        );
        yovel_admin_operations_db_execute($db,
            "UPDATE project_company_operations_worker SET worker_status='IDLE',current_job_key=NULL,lease_expires_at=NULL,last_seen_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND worker_key=? AND current_job_key=?",
            [$companyKeyHash, $workerKey, $jobKey],
            'Operations worker completion release'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $jobKey]);
        $attemptStatus = $db->GetOne('SELECT attempt_status FROM project_company_operations_job_attempt WHERE company_key_hash=? AND job_key=? AND attempt_number=?', [$companyKeyHash, $jobKey, $job['attempt_count']]);
        if (!$saved || (string) $saved['status'] !== 'SUCCEEDED' || (string) $saved['result_json'] !== $resultJson || (string) $attemptStatus !== 'SUCCEEDED') {
            throw new RuntimeException('Operations job completion read-back verification failed.');
        }
        yovel_admin_operations_audit('COMPLETE', 'project_company_operations_job', $jobKey, $companyKeyHash, (string) $adminKey, ['worker_key' => $workerKey, 'attempt_number' => (int) $job['attempt_count']], 'Company administrator verified an Operations job completion.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Operations completion transaction could not commit.');
        }
        return yovel_admin_operations_hydrate_job($saved);
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_operations_fail_job(ADOConnection $db, array $company, array $admin, string $jobKey, string $workerKey, string $errorSummary, bool $retryable): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $errorSummary = trim($errorSummary);
    if ($errorSummary === '' || strlen($errorSummary) > 1000) {
        throw new InvalidArgumentException('Operations failure summary is required and limited to 1000 characters.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations failure transaction could not start.');
    }
    try {
        $job = yovel_admin_operations_lock_running_job($db, $companyKeyHash, $jobKey, $workerKey);
        $willRetry = $retryable && (int) $job['attempt_count'] < (int) $job['max_attempts'];
        $status = $willRetry ? 'QUEUED' : 'FAILED';
        $backoffSeconds = min(3600, 5 * (2 ** max(0, (int) $job['attempt_count'] - 1)));
        $availableAt = yovel_admin_operations_database_datetime($db, $backoffSeconds);
        yovel_admin_operations_db_execute($db,
            "UPDATE project_company_operations_job_attempt SET attempt_status='FAILED',error_summary=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND job_key=? AND attempt_number=? AND worker_key=? AND attempt_status='RUNNING'",
            [$errorSummary, $companyKeyHash, $jobKey, $job['attempt_count'], $workerKey],
            'Operations attempt failure'
        );
        yovel_admin_operations_db_execute($db,
            'UPDATE project_company_operations_job SET status=?,available_at=?,error_summary=?,leased_by_worker_key=NULL,lease_expires_at=NULL,updated_by_admin_key=?,finished_at=IF(?=\'FAILED\',CURRENT_TIMESTAMP,NULL) WHERE company_key_hash=? AND job_key=? AND status=\'RUNNING\'',
            [$status, $availableAt, $errorSummary, $adminKey, $status, $companyKeyHash, $jobKey],
            'Operations job failure'
        );
        yovel_admin_operations_db_execute($db,
            "UPDATE project_company_operations_worker SET worker_status='IDLE',current_job_key=NULL,lease_expires_at=NULL,last_seen_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND worker_key=? AND current_job_key=?",
            [$companyKeyHash, $workerKey, $jobKey],
            'Operations worker failure release'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $jobKey]);
        $attemptStatus = $db->GetOne('SELECT attempt_status FROM project_company_operations_job_attempt WHERE company_key_hash=? AND job_key=? AND attempt_number=?', [$companyKeyHash, $jobKey, $job['attempt_count']]);
        if (!$saved || (string) $saved['status'] !== $status || (string) $saved['error_summary'] !== $errorSummary || (string) $attemptStatus !== 'FAILED') {
            throw new RuntimeException('Operations job failure read-back verification failed.');
        }
        yovel_admin_operations_audit($willRetry ? 'RETRY_WAIT' : 'FAIL', 'project_company_operations_job', $jobKey, $companyKeyHash, (string) $adminKey, ['worker_key' => $workerKey, 'attempt_number' => (int) $job['attempt_count'], 'retryable' => $willRetry], 'Company administrator recorded an Operations job failure.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Operations failure transaction could not commit.');
        }
        return yovel_admin_operations_hydrate_job($saved);
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_operations_cancel_job(ADOConnection $db, array $company, array $admin, string $jobKey, string $reason): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $reason = trim($reason);
    if (!yovel_admin_is_uuid($jobKey) || $reason === '' || strlen($reason) > 1000) {
        throw new InvalidArgumentException('Operations cancellation reference and reason are required.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations cancellation transaction could not start.');
    }
    try {
        $job = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=? FOR UPDATE', [$companyKeyHash, $jobKey]);
        if (!$job) {
            throw new InvalidArgumentException('Operations job was not found for this company.');
        }
        if (!in_array((string) $job['status'], ['QUEUED', 'RUNNING'], true)) {
            throw new InvalidArgumentException('Only queued or running Operations jobs can be cancelled.');
        }
        if ((string) $job['status'] === 'RUNNING') {
            yovel_admin_operations_db_execute($db,
                "UPDATE project_company_operations_job_attempt SET attempt_status='CANCELLED',error_summary=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND job_key=? AND attempt_number=? AND attempt_status='RUNNING'",
                [$reason, $companyKeyHash, $jobKey, $job['attempt_count']],
                'Operations attempt cancellation'
            );
        }
        yovel_admin_operations_db_execute($db,
            "UPDATE project_company_operations_job SET status='CANCELLED',cancel_reason=?,leased_by_worker_key=NULL,lease_expires_at=NULL,updated_by_admin_key=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND job_key=?",
            [$reason, $adminKey, $companyKeyHash, $jobKey],
            'Operations job cancellation'
        );
        if ((string) ($job['leased_by_worker_key'] ?? '') !== '') {
            yovel_admin_operations_db_execute($db,
                "UPDATE project_company_operations_worker SET worker_status='IDLE',current_job_key=NULL,lease_expires_at=NULL,last_seen_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND worker_key=? AND current_job_key=?",
                [$companyKeyHash, $job['leased_by_worker_key'], $jobKey],
                'Operations cancelled worker release'
            );
        }
        $saved = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $jobKey]);
        if (!$saved || (string) $saved['status'] !== 'CANCELLED' || (string) $saved['cancel_reason'] !== $reason) {
            throw new RuntimeException('Operations cancellation read-back verification failed.');
        }
        yovel_admin_operations_audit('CANCEL', 'project_company_operations_job', $jobKey, $companyKeyHash, (string) $adminKey, ['reason' => $reason], 'Company administrator cancelled an Operations job.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Operations cancellation transaction could not commit.');
        }
        return yovel_admin_operations_hydrate_job($saved);
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_operations_retry_job(ADOConnection $db, array $company, array $admin, string $jobKey): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    if (!yovel_admin_is_uuid($jobKey)) {
        throw new InvalidArgumentException('Operations retry reference is invalid.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations retry transaction could not start.');
    }
    try {
        $job = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=? FOR UPDATE', [$companyKeyHash, $jobKey]);
        if (!$job || (string) $job['status'] !== 'FAILED') {
            throw new InvalidArgumentException('Only a failed Operations job can be retried.');
        }
        $maxAttempts = min(10, max((int) $job['max_attempts'], (int) $job['attempt_count'] + 1));
        if ((int) $job['attempt_count'] >= 10) {
            throw new InvalidArgumentException('Operations job reached the manual retry limit.');
        }
        yovel_admin_operations_db_execute($db,
            "UPDATE project_company_operations_job SET status='QUEUED',max_attempts=?,available_at=CURRENT_TIMESTAMP,error_summary=NULL,finished_at=NULL,updated_by_admin_key=? WHERE company_key_hash=? AND job_key=? AND status='FAILED'",
            [$maxAttempts, $adminKey, $companyKeyHash, $jobKey],
            'Operations manual retry'
        );
        $saved = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $jobKey]);
        if (!$saved || (string) $saved['status'] !== 'QUEUED' || (int) $saved['max_attempts'] !== $maxAttempts) {
            throw new RuntimeException('Operations retry read-back verification failed.');
        }
        yovel_admin_operations_audit('RETRY', 'project_company_operations_job', $jobKey, $companyKeyHash, (string) $adminKey, ['max_attempts' => $maxAttempts], 'Company administrator confirmed a manual Operations retry.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Operations retry transaction could not commit.');
        }
        return yovel_admin_operations_hydrate_job($saved);
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_operations_save_schedule(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $scheduleKey = trim((string) ($input['schedule_key'] ?? ''));
    $name = trim((string) ($input['schedule_name'] ?? ''));
    $jobType = yovel_admin_operations_job_type((string) ($input['job_type'] ?? ''));
    $cron = trim((string) ($input['cron_expression'] ?? ''));
    $status = strtoupper(trim((string) ($input['schedule_status'] ?? 'ACTIVE')));
    $payloadJson = yovel_admin_operations_json(yovel_admin_operations_safe_payload(is_array($input['payload'] ?? null) ? $input['payload'] : []));
    if (($scheduleKey !== '' && !yovel_admin_is_uuid($scheduleKey)) || $name === '' || strlen($name) > 180
        || preg_match('/^\S+\s+\S+\s+\S+\s+\S+\s+\S+$/', $cron) !== 1
        || !in_array($status, ['ACTIVE', 'PAUSED', 'ARCHIVED'], true)) {
        throw new InvalidArgumentException('Operations schedule input is invalid.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations schedule transaction could not start.');
    }
    try {
        $existing = $scheduleKey !== '' ? $db->GetRow('SELECT * FROM project_company_operations_schedule WHERE company_key_hash=? AND schedule_key=? FOR UPDATE', [$companyKeyHash, $scheduleKey]) : false;
        if ($scheduleKey !== '' && !$existing) {
            throw new InvalidArgumentException('Operations schedule was not found for this company.');
        }
        $scheduleKey = $existing ? (string) $existing['schedule_key'] : bx_uuid();
        if ($existing) {
            yovel_admin_operations_db_execute($db, 'UPDATE project_company_operations_schedule SET schedule_name=?,job_type=?,cron_expression=?,payload_json=?,schedule_status=?,updated_by_admin_key=? WHERE company_key_hash=? AND schedule_key=?', [$name, $jobType, $cron, $payloadJson, $status, $adminKey, $companyKeyHash, $scheduleKey], 'Operations schedule update');
        } else {
            yovel_admin_operations_db_execute($db, 'INSERT INTO project_company_operations_schedule (schedule_key,company_key,company_key_hash,schedule_name,job_type,cron_expression,payload_json,schedule_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?)', [$scheduleKey, $companyKey, $companyKeyHash, $name, $jobType, $cron, $payloadJson, $status, $adminKey, $adminKey], 'Operations schedule persistence');
        }
        $saved = $db->GetRow('SELECT * FROM project_company_operations_schedule WHERE company_key_hash=? AND schedule_key=?', [$companyKeyHash, $scheduleKey]);
        if (!$saved || (string) $saved['schedule_name'] !== $name || (string) $saved['job_type'] !== $jobType || (string) $saved['cron_expression'] !== $cron || (string) $saved['payload_json'] !== $payloadJson || (string) $saved['schedule_status'] !== $status) {
            throw new RuntimeException('Operations schedule read-back verification failed.');
        }
        yovel_admin_operations_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_operations_schedule', $scheduleKey, $companyKeyHash, (string) $adminKey, ['schedule_name' => $name, 'job_type' => $jobType, 'cron_expression' => $cron, 'schedule_status' => $status], 'Company administrator saved an Operations schedule.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Operations schedule transaction could not commit.');
        }
        $saved['payload'] = yovel_admin_operations_json_array($payloadJson, 'Operations schedule payload');
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_operations_save_sync_conflict(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $contract = strtolower(trim((string) ($input['source_contract'] ?? '')));
    yovel_admin_operations_contract_meta($contract);
    $ownerKey = trim((string) ($input['owner_record_key'] ?? ''));
    $summary = trim((string) ($input['summary'] ?? ''));
    $localJson = yovel_admin_operations_json(yovel_admin_operations_safe_payload(is_array($input['local_snapshot'] ?? null) ? $input['local_snapshot'] : []));
    $remoteJson = yovel_admin_operations_json(yovel_admin_operations_safe_payload(is_array($input['remote_snapshot'] ?? null) ? $input['remote_snapshot'] : []));
    if ($ownerKey === '' || strlen($ownerKey) > 160 || $summary === '' || strlen($summary) > 1000) {
        throw new InvalidArgumentException('Operations sync conflict input is invalid.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations conflict transaction could not start.');
    }
    try {
        $key = bx_uuid();
        yovel_admin_operations_db_execute($db, "INSERT INTO project_company_operations_sync_conflict (conflict_key,company_key,company_key_hash,source_contract,owner_record_key,local_snapshot_json,remote_snapshot_json,summary,status,created_by_admin_key) VALUES (?,?,?,?,?,?,?,?,'OPEN',?)", [$key, $companyKey, $companyKeyHash, $contract, $ownerKey, $localJson, $remoteJson, $summary, $adminKey], 'Operations sync conflict persistence');
        $saved = $db->GetRow('SELECT * FROM project_company_operations_sync_conflict WHERE company_key_hash=? AND conflict_key=?', [$companyKeyHash, $key]);
        if (!$saved || (string) $saved['status'] !== 'OPEN' || (string) $saved['local_snapshot_json'] !== $localJson || (string) $saved['remote_snapshot_json'] !== $remoteJson) {
            throw new RuntimeException('Operations sync conflict read-back verification failed.');
        }
        yovel_admin_operations_audit('CREATE', 'project_company_operations_sync_conflict', $key, $companyKeyHash, (string) $adminKey, ['source_contract' => $contract, 'owner_record_key' => $ownerKey, 'status' => 'OPEN'], 'Company administrator recorded an Operations sync conflict.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Operations conflict transaction could not commit.');
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_operations_resolve_sync_conflict(ADOConnection $db, array $company, array $admin, string $conflictKey, string $resolution, string $note): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $resolution = strtoupper(trim($resolution));
    $note = trim($note);
    if (!yovel_admin_is_uuid($conflictKey) || !in_array($resolution, ['ACCEPT_LOCAL', 'ACCEPT_REMOTE', 'MANUAL'], true) || $note === '' || strlen($note) > 1000) {
        throw new InvalidArgumentException('Operations sync conflict resolution is invalid.');
    }
    if ($db->BeginTrans() === false) throw new RuntimeException('Operations conflict resolution transaction could not start.');
    try {
        $row = $db->GetRow("SELECT * FROM project_company_operations_sync_conflict WHERE company_key_hash=? AND conflict_key=? AND status='OPEN' FOR UPDATE", [$companyKeyHash, $conflictKey]);
        if (!$row) throw new InvalidArgumentException('Open Operations sync conflict was not found.');
        yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_sync_conflict SET status='RESOLVED',resolution=?,resolution_note=?,resolved_by_admin_key=?,resolved_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND conflict_key=? AND status='OPEN'", [$resolution, $note, $adminKey, $companyKeyHash, $conflictKey], 'Operations conflict resolution');
        $saved = $db->GetRow('SELECT * FROM project_company_operations_sync_conflict WHERE company_key_hash=? AND conflict_key=?', [$companyKeyHash, $conflictKey]);
        if (!$saved || (string) $saved['status'] !== 'RESOLVED' || (string) $saved['resolution'] !== $resolution || (string) $saved['resolution_note'] !== $note) throw new RuntimeException('Operations conflict resolution read-back verification failed.');
        yovel_admin_operations_audit('RESOLVE', 'project_company_operations_sync_conflict', $conflictKey, $companyKeyHash, (string) $adminKey, ['resolution' => $resolution, 'note' => $note], 'Company administrator resolved an Operations sync conflict.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Operations conflict resolution transaction could not commit.');
        return $saved;
    } catch (Throwable $error) { $db->RollbackTrans(); throw $error; }
}

function yovel_admin_operations_save_system_alert(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $code = strtoupper(trim((string) ($input['alert_code'] ?? '')));
    $severity = strtoupper(trim((string) ($input['severity'] ?? '')));
    $title = trim((string) ($input['title'] ?? ''));
    $summary = trim((string) ($input['summary'] ?? ''));
    if (preg_match('/^[A-Z0-9_.:-]{2,120}$/', $code) !== 1 || !in_array($severity, ['INFO', 'WARNING', 'CRITICAL'], true) || $title === '' || strlen($title) > 180 || $summary === '' || strlen($summary) > 1000) throw new InvalidArgumentException('Operations alert input is invalid.');
    if ($db->BeginTrans() === false) throw new RuntimeException('Operations alert transaction could not start.');
    try {
        $existing = $db->GetRow('SELECT * FROM project_company_operations_system_alert WHERE company_key_hash=? AND alert_code=? FOR UPDATE', [$companyKeyHash, $code]);
        $key = $existing ? (string) $existing['alert_key'] : bx_uuid();
        if ($existing) {
            yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_system_alert SET severity=?,title=?,summary=?,status='ACTIVE',acknowledgement_note=NULL,acknowledged_by_admin_key=NULL,acknowledged_at=NULL WHERE company_key_hash=? AND alert_key=?", [$severity, $title, $summary, $companyKeyHash, $key], 'Operations alert update');
        } else {
            yovel_admin_operations_db_execute($db, "INSERT INTO project_company_operations_system_alert (alert_key,company_key,company_key_hash,alert_code,severity,title,summary,status,created_by_admin_key) VALUES (?,?,?,?,?,?,?,'ACTIVE',?)", [$key, $companyKey, $companyKeyHash, $code, $severity, $title, $summary, $adminKey], 'Operations alert persistence');
        }
        $saved = $db->GetRow('SELECT * FROM project_company_operations_system_alert WHERE company_key_hash=? AND alert_key=?', [$companyKeyHash, $key]);
        if (!$saved || (string) $saved['status'] !== 'ACTIVE' || (string) $saved['severity'] !== $severity || (string) $saved['title'] !== $title || (string) $saved['summary'] !== $summary) throw new RuntimeException('Operations alert read-back verification failed.');
        yovel_admin_operations_audit($existing ? 'REOPEN' : 'CREATE', 'project_company_operations_system_alert', $key, $companyKeyHash, (string) $adminKey, ['alert_code' => $code, 'severity' => $severity, 'status' => 'ACTIVE'], 'Company administrator persisted an Operations system alert.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Operations alert transaction could not commit.');
        return $saved;
    } catch (Throwable $error) { $db->RollbackTrans(); throw $error; }
}

function yovel_admin_operations_acknowledge_alert(ADOConnection $db, array $company, array $admin, string $alertKey, string $note): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $note = trim($note);
    if (!yovel_admin_is_uuid($alertKey) || $note === '' || strlen($note) > 1000) throw new InvalidArgumentException('Operations alert acknowledgment is invalid.');
    if ($db->BeginTrans() === false) throw new RuntimeException('Operations alert acknowledgment transaction could not start.');
    try {
        $row = $db->GetRow("SELECT * FROM project_company_operations_system_alert WHERE company_key_hash=? AND alert_key=? AND status='ACTIVE' FOR UPDATE", [$companyKeyHash, $alertKey]);
        if (!$row) throw new InvalidArgumentException('Active Operations alert was not found.');
        yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_system_alert SET status='ACKNOWLEDGED',acknowledgement_note=?,acknowledged_by_admin_key=?,acknowledged_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND alert_key=? AND status='ACTIVE'", [$note, $adminKey, $companyKeyHash, $alertKey], 'Operations alert acknowledgment');
        $saved = $db->GetRow('SELECT * FROM project_company_operations_system_alert WHERE company_key_hash=? AND alert_key=?', [$companyKeyHash, $alertKey]);
        if (!$saved || (string) $saved['status'] !== 'ACKNOWLEDGED' || (string) $saved['acknowledgement_note'] !== $note) throw new RuntimeException('Operations alert acknowledgment read-back verification failed.');
        yovel_admin_operations_audit('ACKNOWLEDGE', 'project_company_operations_system_alert', $alertKey, $companyKeyHash, (string) $adminKey, ['note' => $note, 'status' => 'ACKNOWLEDGED'], 'Company administrator acknowledged an Operations alert.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Operations alert acknowledgment transaction could not commit.');
        return $saved;
    } catch (Throwable $error) { $db->RollbackTrans(); throw $error; }
}

function yovel_admin_operations_save_release_check(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $code = strtoupper(trim((string) ($input['check_code'] ?? '')));
    $label = trim((string) ($input['check_label'] ?? ''));
    $status = strtoupper(trim((string) ($input['status'] ?? '')));
    $evidence = trim((string) ($input['evidence_summary'] ?? ''));
    if (preg_match('/^[A-Z0-9_.:-]{2,120}$/', $code) !== 1 || $label === '' || strlen($label) > 180 || !in_array($status, ['PASS', 'WARN', 'FAIL'], true) || $evidence === '' || strlen($evidence) > 2000) throw new InvalidArgumentException('Operations release check evidence is invalid.');
    if ($db->BeginTrans() === false) throw new RuntimeException('Operations release check transaction could not start.');
    try {
        $key = bx_uuid();
        yovel_admin_operations_db_execute($db, 'INSERT INTO project_company_operations_release_check (release_check_key,company_key,company_key_hash,check_code,check_label,status,evidence_summary,checked_by_admin_key,checked_at) VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)', [$key, $companyKey, $companyKeyHash, $code, $label, $status, $evidence, $adminKey], 'Operations release check persistence');
        $saved = $db->GetRow('SELECT * FROM project_company_operations_release_check WHERE company_key_hash=? AND release_check_key=?', [$companyKeyHash, $key]);
        if (!$saved || (string) $saved['check_code'] !== $code || (string) $saved['status'] !== $status || (string) $saved['evidence_summary'] !== $evidence || (string) $saved['checked_by_admin_key'] !== $adminKey || (string) $saved['checked_at'] === '') throw new RuntimeException('Operations release check read-back verification failed.');
        yovel_admin_operations_audit('CHECK', 'project_company_operations_release_check', $key, $companyKeyHash, (string) $adminKey, ['check_code' => $code, 'status' => $status, 'evidence_summary' => $evidence], 'Company administrator recorded release-readiness evidence.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Operations release check transaction could not commit.');
        return $saved;
    } catch (Throwable $error) { $db->RollbackTrans(); throw $error; }
}

function yovel_admin_operations_workers(array $company): array
{
    [, $hash] = yovel_admin_operations_contract_scope($company);
    return bx_db()->GetAll('SELECT * FROM project_company_operations_worker WHERE company_key_hash=? ORDER BY last_seen_at DESC,x_id DESC', [$hash]);
}

function yovel_admin_operations_schedules(array $company): array
{
    [, $hash] = yovel_admin_operations_contract_scope($company);
    $rows = bx_db()->GetAll('SELECT * FROM project_company_operations_schedule WHERE company_key_hash=? ORDER BY updated_at DESC,x_id DESC', [$hash]);
    foreach ($rows as &$row) {
        $row['payload'] = yovel_admin_operations_json_array((string) $row['payload_json'], 'Operations schedule payload');
    }
    unset($row);
    return $rows;
}

function yovel_admin_operations_sync_conflicts(array $company): array
{
    [, $hash] = yovel_admin_operations_contract_scope($company);
    return bx_db()->GetAll('SELECT * FROM project_company_operations_sync_conflict WHERE company_key_hash=? ORDER BY created_at DESC,x_id DESC', [$hash]);
}

function yovel_admin_operations_system_alerts(array $company): array
{
    [, $hash] = yovel_admin_operations_contract_scope($company);
    return bx_db()->GetAll('SELECT * FROM project_company_operations_system_alert WHERE company_key_hash=? ORDER BY updated_at DESC,x_id DESC', [$hash]);
}

function yovel_admin_operations_release_checks(array $company): array
{
    [, $hash] = yovel_admin_operations_contract_scope($company);
    return bx_db()->GetAll('SELECT * FROM project_company_operations_release_check WHERE company_key_hash=? ORDER BY checked_at DESC,x_id DESC LIMIT 200', [$hash]);
}

function yovel_admin_operations_job_metrics(array $company): array
{
    [, $hash] = yovel_admin_operations_contract_scope($company);
    return [
        'queued' => (int) bx_db()->GetOne("SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=? AND status='QUEUED'", [$hash]),
        'running' => (int) bx_db()->GetOne("SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=? AND status='RUNNING'", [$hash]),
        'failed' => (int) bx_db()->GetOne("SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=? AND status='FAILED'", [$hash]),
        'active_alerts' => (int) bx_db()->GetOne("SELECT COUNT(*) FROM project_company_operations_system_alert WHERE company_key_hash=? AND status='ACTIVE'", [$hash]),
    ];
}

function yovel_admin_operations_handle_job_post(array $company, array $admin, string $action, array $input): array
{
    $section = yovel_admin_operations_section((string) ($input['section'] ?? ''));
    if (in_array($action, ['run_operations_job', 'import_operations_records', 'export_operations_records'], true)) {
        $jobType = $action === 'import_operations_records' ? 'IMPORT' : ($action === 'export_operations_records' ? 'EXPORT' : (string) ($input['job_type'] ?? 'SCHEDULED'));
        $job = yovel_admin_operations_enqueue_job(bx_db(), $company, $admin, [
            'idempotency_key' => $input['idempotency_key'] ?? '',
            'job_type' => $jobType,
            'source_section' => $section,
            'payload' => yovel_admin_operations_input_json($input, 'payload_json', 'Operations job payload'),
            'max_attempts' => $input['max_attempts'] ?? 3,
            'priority' => $input['priority'] ?? 50,
        ]);
        return ['message' => 'Operations job queued.', 'section' => $section, 'query' => ['job' => (string) $job['job_key']]];
    }
    if ($action === 'retry_operations_job') {
        $job = yovel_admin_operations_retry_job(bx_db(), $company, $admin, (string) ($input['job_key'] ?? ''));
        return ['message' => 'Operations job queued for retry.', 'section' => $section, 'query' => ['job' => (string) $job['job_key']]];
    }
    if ($action === 'cancel_operations_job') {
        $job = yovel_admin_operations_cancel_job(bx_db(), $company, $admin, (string) ($input['job_key'] ?? ''), (string) ($input['reason'] ?? ''));
        return ['message' => 'Operations job cancelled.', 'section' => $section, 'query' => ['job' => (string) $job['job_key']]];
    }
    if ($action === 'save_operations_schedule') {
        $saved = yovel_admin_operations_save_schedule(bx_db(), $company, $admin, [
            ...$input,
            'payload' => yovel_admin_operations_input_json($input, 'payload_json', 'Operations schedule payload'),
        ]);
        return ['message' => 'Operations schedule saved.', 'section' => 'scheduled-jobs', 'query' => ['schedule' => (string) $saved['schedule_key']]];
    }
    if ($action === 'resolve_operations_conflict') {
        $saved = yovel_admin_operations_resolve_sync_conflict(bx_db(), $company, $admin, (string) ($input['conflict_key'] ?? ''), (string) ($input['resolution'] ?? ''), (string) ($input['resolution_note'] ?? ''));
        return ['message' => 'Sync conflict resolved.', 'section' => 'sync-conflict-dashboard', 'query' => ['conflict' => (string) $saved['conflict_key']]];
    }
    if ($action === 'acknowledge_operations_alert') {
        $saved = yovel_admin_operations_acknowledge_alert(bx_db(), $company, $admin, (string) ($input['alert_key'] ?? ''), (string) ($input['acknowledgement_note'] ?? ''));
        return ['message' => 'System alert acknowledged.', 'section' => 'system-alerts', 'query' => ['alert' => (string) $saved['alert_key']]];
    }
    if ($action === 'save_operations_release_check') {
        $saved = yovel_admin_operations_save_release_check(bx_db(), $company, $admin, $input);
        return ['message' => 'Release evidence recorded.', 'section' => 'release-checklist', 'query' => ['check' => (string) $saved['release_check_key']]];
    }
    throw new InvalidArgumentException('Unknown Operations action.');
}
