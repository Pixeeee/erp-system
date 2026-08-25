<?php
declare(strict_types=1);

function yovel_admin_operations_sanitize_evidence(mixed $value, int $depth = 0): mixed
{
    if ($depth > 5) {
        return '[truncated]';
    }
    if (is_array($value)) {
        $safe = [];
        foreach ($value as $key => $item) {
            $key = (string) $key;
            $isReference = preg_match('/(?:_ref|_reference)$/i', $key) === 1;
            if ((!$isReference && preg_match('/(?:password|passwd|secret|token|credential|api[_-]?key)/i', $key) === 1)
                || preg_match('/(?:^|_)(?:sql|query|statement)(?:$|_)/i', $key) === 1) {
                continue;
            }
            $safe[$key] = yovel_admin_operations_sanitize_evidence($item, $depth + 1);
        }
        return $safe;
    }
    if (is_string($value)) {
        $value = substr(trim($value), 0, 20000);
        if (preg_match('/\b(?:select|insert|update|delete)\b.{0,240}\b(?:from|into|set)\b/i', $value) === 1) {
            return '[redacted command detail]';
        }
        return $value;
    }
    return is_scalar($value) || $value === null ? $value : null;
}

function yovel_admin_operations_bounded_text(mixed $value, string $label, int $maximum, bool $required = true): string
{
    $value = trim((string) $value);
    if (($required && $value === '') || strlen($value) > $maximum) {
        throw new InvalidArgumentException($label . ' is required and limited to ' . $maximum . ' characters.');
    }
    return $value;
}

function yovel_admin_operations_workflow_idempotency(string $value): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 150 || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]*$/', $value) !== 1) {
        throw new InvalidArgumentException('Operations workflow idempotency key is invalid.');
    }
    return $value;
}

function yovel_admin_operations_target_idempotency(string $base, int $sequence, string $recordKey): string
{
    return substr($base, 0, 110) . ':' . $sequence . ':' . substr(hash('sha256', $recordKey), 0, 24);
}

function yovel_admin_operations_normalize_owner_targets(array $targets, string $contractId): array
{
    yovel_admin_operations_contract_meta($contractId);
    if ($targets === [] || count($targets) > 200) {
        throw new InvalidArgumentException('Operations workflows require between one and 200 targets.');
    }
    $normalized = [];
    $seen = [];
    foreach (array_values($targets) as $target) {
        if (!is_array($target)) {
            throw new InvalidArgumentException('Operations workflow targets must be objects.');
        }
        $recordKey = yovel_admin_operations_bounded_text($target['record_key'] ?? '', 'Owner record key', 160);
        if (isset($seen[$recordKey])) {
            throw new InvalidArgumentException('Operations workflow targets must be unique.');
        }
        $ownerContract = strtolower(trim((string) ($target['owner_contract'] ?? $contractId)));
        if ($ownerContract !== $contractId) {
            throw new InvalidArgumentException('Operations workflow target uses an unexpected owner contract.');
        }
        $seen[$recordKey] = true;
        $normalized[] = ['record_key' => $recordKey, 'owner_contract' => $ownerContract];
    }
    return $normalized;
}

function yovel_admin_operations_insert_joined_job(
    ADOConnection $db,
    string $companyKey,
    string $companyKeyHash,
    string $adminKey,
    string $idempotencyKey,
    string $sourceSection,
    array $payload,
    string $status
): array {
    $jobKey = bx_uuid();
    $payloadJson = yovel_admin_operations_json(yovel_admin_operations_safe_payload($payload));
    $availableAt = yovel_admin_operations_database_datetime($db);
    $startedAt = $status === 'RUNNING' ? $availableAt : null;
    yovel_admin_operations_db_execute($db,
        'INSERT INTO project_company_operations_job (job_key,company_key,company_key_hash,idempotency_key,job_type,source_section,payload_json,status,priority,max_attempts,available_at,created_by_admin_key,updated_by_admin_key,started_at) VALUES (?,?,?,?,\'GENERAL\',?,?,?,50,3,?,?,?,?)',
        [$jobKey, $companyKey, $companyKeyHash, $idempotencyKey, $sourceSection, $payloadJson, $status, $availableAt, $adminKey, $adminKey, $startedAt],
        'Operations joined workflow job enqueue'
    );
    $saved = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $jobKey]);
    if (!$saved || (string) $saved['idempotency_key'] !== $idempotencyKey || (string) $saved['source_section'] !== $sourceSection
        || (string) $saved['payload_json'] !== $payloadJson || (string) $saved['status'] !== $status) {
        throw new RuntimeException('Operations joined workflow job read-back verification failed.');
    }
    return $saved;
}

function yovel_admin_operations_complete_joined_job(ADOConnection $db, string $companyKeyHash, string $jobKey, string $adminKey, array $result): array
{
    $resultJson = yovel_admin_operations_json((array) yovel_admin_operations_sanitize_evidence($result));
    yovel_admin_operations_db_execute($db,
        "UPDATE project_company_operations_job SET status='SUCCEEDED',progress_percent=100,result_json=?,updated_by_admin_key=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND job_key=? AND status='RUNNING'",
        [$resultJson, $adminKey, $companyKeyHash, $jobKey],
        'Operations joined workflow job completion'
    );
    $saved = $db->GetRow('SELECT * FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $jobKey]);
    if (!$saved || (string) $saved['status'] !== 'SUCCEEDED' || (string) $saved['result_json'] !== $resultJson || (int) $saved['progress_percent'] !== 100) {
        throw new RuntimeException('Operations joined workflow completion read-back verification failed.');
    }
    return $saved;
}

function yovel_admin_operations_bulk_log(array $company, string $logKey): ?array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    if (!yovel_admin_is_uuid($logKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_operations_bulk_log WHERE company_key_hash=? AND bulk_log_key=?', [$companyKeyHash, $logKey]);
    if (!$row) {
        return null;
    }
    foreach (['requested_count', 'completed_count', 'failed_count'] as $column) {
        $row[$column] = (int) $row[$column];
    }
    $row['details'] = bx_db()->GetAll('SELECT * FROM project_company_operations_bulk_log_detail WHERE company_key_hash=? AND bulk_log_key=? ORDER BY sequence_no', [$companyKeyHash, $logKey]);
    return $row;
}

function yovel_admin_operations_bulk_logs(array $company): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    return bx_db()->GetAll('SELECT * FROM project_company_operations_bulk_log WHERE company_key_hash=? ORDER BY created_at DESC,x_id DESC LIMIT 200', [$companyKeyHash]);
}

function yovel_admin_operations_submit_bulk_job(ADOConnection $db, array $company, array $admin, array $input, array $providers = []): array
{
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $idempotencyKey = yovel_admin_operations_workflow_idempotency((string) ($input['idempotency_key'] ?? ''));
    $recordType = yovel_admin_operations_bounded_text($input['record_type'] ?? '', 'Bulk record type', 160);
    $actionCode = strtoupper(yovel_admin_operations_bounded_text($input['action_code'] ?? '', 'Bulk action', 80));
    if (preg_match('/^[A-Z][A-Z0-9_.:-]*$/', $actionCode) !== 1) {
        throw new InvalidArgumentException('Bulk action code is invalid.');
    }
    $evidenceSummary = yovel_admin_operations_bounded_text(
        yovel_admin_operations_sanitize_evidence((string) ($input['evidence_summary'] ?? '')),
        'Bulk evidence summary',
        2000
    );
    $targets = yovel_admin_operations_normalize_owner_targets(is_array($input['targets'] ?? null) ? $input['targets'] : [], 'owners.bulk-command.v1');
    $deferred = filter_var($input['defer_execution'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations bulk transaction could not start.');
    }
    try {
        $existing = $db->GetRow('SELECT bulk_log_key FROM project_company_operations_bulk_log WHERE company_key_hash=? AND idempotency_key=? FOR UPDATE', [$companyKeyHash, $idempotencyKey]);
        if ($existing) {
            $saved = yovel_admin_operations_bulk_log($company, (string) $existing['bulk_log_key']);
            if (!$saved) throw new RuntimeException('Operations bulk idempotent read-back failed.');
            if ($db->CommitTrans() === false) throw new RuntimeException('Operations bulk transaction could not commit.');
            return $saved;
        }

        $logKey = bx_uuid();
        $initialStatus = $deferred ? 'QUEUED' : 'RUNNING';
        $job = yovel_admin_operations_insert_joined_job($db, $companyKey, $companyKeyHash, (string) $adminKey, 'bulk:' . $idempotencyKey, 'bulk-processing', [
            'bulk_log_key' => $logKey, 'record_type' => $recordType, 'action_code' => $actionCode, 'target_count' => count($targets),
        ], $initialStatus);
        yovel_admin_operations_db_execute($db,
            'INSERT INTO project_company_operations_bulk_log (bulk_log_key,company_key,company_key_hash,idempotency_key,job_key,record_type,action_code,requested_count,status,evidence_summary,created_by_admin_key,updated_by_admin_key,started_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$logKey, $companyKey, $companyKeyHash, $idempotencyKey, $job['job_key'], $recordType, $actionCode, count($targets), $initialStatus, $evidenceSummary, $adminKey, $adminKey, $deferred ? null : yovel_admin_operations_database_datetime($db)],
            'Operations bulk log persistence'
        );
        foreach ($targets as $index => $target) {
            $sequence = $index + 1;
            $targetIdempotency = yovel_admin_operations_target_idempotency($idempotencyKey, $sequence, $target['record_key']);
            $detailKey = bx_uuid();
            yovel_admin_operations_db_execute($db,
                "INSERT INTO project_company_operations_bulk_log_detail (detail_key,bulk_log_key,company_key,company_key_hash,sequence_no,owner_contract,target_record_key,target_idempotency_key,action_status) VALUES (?,?,?,?,?,?,?,?,'PENDING')",
                [$detailKey, $logKey, $companyKey, $companyKeyHash, $sequence, $target['owner_contract'], $target['record_key'], $targetIdempotency],
                'Operations bulk detail persistence'
            );
            if ($deferred) continue;
            $ownerResult = yovel_admin_operations_call_command_contract('owners.bulk-command.v1', $db, $company, $admin, [
                'record_key' => $target['record_key'], 'record_type' => $recordType, 'action_code' => $actionCode,
                'idempotency_key' => $targetIdempotency, 'bulk_log_key' => $logKey,
            ], $providers);
            $readBackJson = yovel_admin_operations_json((array) yovel_admin_operations_sanitize_evidence($ownerResult['read_back']));
            yovel_admin_operations_db_execute($db,
                "UPDATE project_company_operations_bulk_log_detail SET action_status='COMPLETED',owner_record_key=?,owner_status=?,read_back_json=?,owner_audit_key=? WHERE company_key_hash=? AND detail_key=? AND action_status='PENDING'",
                [$ownerResult['owner_record_key'], substr((string) ($ownerResult['status'] ?? 'COMPLETED'), 0, 80), $readBackJson, substr((string) $ownerResult['audit_key'], 0, 160), $companyKeyHash, $detailKey],
                'Operations bulk detail completion'
            );
            $detail = $db->GetRow('SELECT * FROM project_company_operations_bulk_log_detail WHERE company_key_hash=? AND detail_key=?', [$companyKeyHash, $detailKey]);
            if (!$detail || (string) $detail['action_status'] !== 'COMPLETED' || (string) $detail['target_idempotency_key'] !== $targetIdempotency
                || (string) $detail['owner_record_key'] !== (string) $ownerResult['owner_record_key'] || (string) $detail['read_back_json'] !== $readBackJson) {
                throw new RuntimeException('Operations bulk detail read-back verification failed.');
            }
        }

        if (!$deferred) {
            yovel_admin_operations_db_execute($db,
                "UPDATE project_company_operations_bulk_log SET status='COMPLETED',completed_count=requested_count,failed_count=0,updated_by_admin_key=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND bulk_log_key=? AND status='RUNNING'",
                [$adminKey, $companyKeyHash, $logKey],
                'Operations bulk log completion'
            );
            yovel_admin_operations_complete_joined_job($db, $companyKeyHash, (string) $job['job_key'], (string) $adminKey, ['bulk_log_key' => $logKey, 'completed_count' => count($targets)]);
        }
        $saved = yovel_admin_operations_bulk_log($company, $logKey);
        if (!$saved || (string) $saved['status'] !== ($deferred ? 'QUEUED' : 'COMPLETED')
            || (int) $saved['requested_count'] !== count($targets)
            || (int) $saved['completed_count'] !== ($deferred ? 0 : count($targets))
            || count($saved['details']) !== count($targets)) {
            throw new RuntimeException('Operations bulk log read-back verification failed.');
        }
        yovel_admin_operations_audit('SUBMIT', 'project_company_operations_bulk_log', $logKey, $companyKeyHash, (string) $adminKey, [
            'idempotency_key' => $idempotencyKey, 'job_key' => $job['job_key'], 'record_type' => $recordType,
            'action_code' => $actionCode, 'requested_count' => count($targets), 'status' => $saved['status'],
        ], 'Company administrator confirmed an Operations bulk workflow.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Operations bulk transaction could not commit.');
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_operations_cancel_bulk_job(ADOConnection $db, array $company, array $admin, string $logKey, string $reason): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $reason = yovel_admin_operations_bounded_text(yovel_admin_operations_sanitize_evidence($reason), 'Bulk cancellation reason', 1000);
    if (!yovel_admin_is_uuid($logKey)) throw new InvalidArgumentException('Bulk log reference is invalid.');
    if ($db->BeginTrans() === false) throw new RuntimeException('Operations bulk cancellation transaction could not start.');
    try {
        $log = $db->GetRow("SELECT * FROM project_company_operations_bulk_log WHERE company_key_hash=? AND bulk_log_key=? AND status='QUEUED' FOR UPDATE", [$companyKeyHash, $logKey]);
        if (!$log) throw new InvalidArgumentException('Queued Operations bulk workflow was not found.');
        yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_bulk_log_detail SET action_status='CANCELLED' WHERE company_key_hash=? AND bulk_log_key=? AND action_status='PENDING'", [$companyKeyHash, $logKey], 'Operations bulk detail cancellation');
        yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_bulk_log SET status='CANCELLED',cancel_reason=?,updated_by_admin_key=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND bulk_log_key=? AND status='QUEUED'", [$reason, $adminKey, $companyKeyHash, $logKey], 'Operations bulk cancellation');
        yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_job SET status='CANCELLED',cancel_reason=?,updated_by_admin_key=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND job_key=? AND status='QUEUED'", [$reason, $adminKey, $companyKeyHash, $log['job_key']], 'Operations bulk job cancellation');
        $saved = yovel_admin_operations_bulk_log($company, $logKey);
        $jobStatus = $db->GetOne('SELECT status FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $log['job_key']]);
        if (!$saved || (string) $saved['status'] !== 'CANCELLED' || (string) $saved['cancel_reason'] !== $reason || (string) $jobStatus !== 'CANCELLED'
            || count(array_filter($saved['details'], static fn (array $detail): bool => $detail['action_status'] !== 'CANCELLED')) !== 0) {
            throw new RuntimeException('Operations bulk cancellation read-back verification failed.');
        }
        yovel_admin_operations_audit('CANCEL', 'project_company_operations_bulk_log', $logKey, $companyKeyHash, (string) $adminKey, ['reason' => $reason, 'job_key' => $log['job_key']], 'Company administrator cancelled a queued Operations bulk workflow.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Operations bulk cancellation transaction could not commit.');
        return $saved;
    } catch (Throwable $error) { $db->RollbackTrans(); throw $error; }
}

function yovel_admin_operations_runtime_providers(): array
{
    $providers = $GLOBALS['yovel_admin_operations_dependency_providers'] ?? [];
    return is_array($providers) ? array_filter($providers, 'is_callable') : [];
}

function yovel_admin_operations_workflow_targets_input(array $input): array
{
    $targets = yovel_admin_operations_input_json($input, 'targets_json', 'Operations workflow targets');
    $normalized = [];
    foreach ($targets as $target) {
        $normalized[] = is_array($target) ? $target : ['record_key' => (string) $target];
    }
    return $normalized;
}

function yovel_admin_operations_handle_workflow_post(array $company, array $admin, string $action, array $input): array
{
    $providers = yovel_admin_operations_runtime_providers();
    if ($action === 'submit_operations_bulk') {
        $saved = yovel_admin_operations_submit_bulk_job(bx_db(), $company, $admin, [
            'idempotency_key' => $input['idempotency_key'] ?? '',
            'record_type' => $input['record_type'] ?? '',
            'action_code' => $input['action_code'] ?? '',
            'targets' => yovel_admin_operations_workflow_targets_input($input),
            'evidence_summary' => $input['evidence_summary'] ?? '',
        ], $providers);
        return ['message' => 'Bulk workflow completed.', 'section' => 'bulk-processing', 'query' => ['bulk_log' => (string) $saved['bulk_log_key']]];
    }
    if ($action === 'cancel_operations_bulk') {
        $saved = yovel_admin_operations_cancel_bulk_job(bx_db(), $company, $admin, (string) ($input['bulk_log_key'] ?? ''), (string) ($input['reason'] ?? ''));
        return ['message' => 'Bulk workflow cancelled.', 'section' => 'bulk-processing', 'query' => ['bulk_log' => (string) $saved['bulk_log_key']]];
    }
    if ($action === 'plan_operations_deletion') {
        $plan = yovel_admin_operations_plan_deletion($company, $admin, [
            'idempotency_key' => $input['idempotency_key'] ?? '',
            'record_type' => $input['record_type'] ?? '',
            'targets' => yovel_admin_operations_workflow_targets_input($input),
            'consequence' => $input['consequence'] ?? '',
        ], $providers);
        return ['message' => 'Governed deletion dry-run is ready.', 'section' => 'governed-deletion', 'query' => [
            'modal' => '1', 'plan' => '1', 'plan_hash' => (string) $plan['plan_hash'],
            'idempotency_key' => (string) $plan['idempotency_key'], 'record_type' => (string) $plan['record_type'],
            'targets_json' => yovel_admin_operations_json($plan['targets']), 'consequence' => (string) $plan['consequence'],
        ]];
    }
    if ($action === 'submit_operations_deletion') {
        $saved = yovel_admin_operations_submit_deletion(bx_db(), $company, $admin, [
            'idempotency_key' => $input['idempotency_key'] ?? '',
            'record_type' => $input['record_type'] ?? '',
            'targets' => yovel_admin_operations_workflow_targets_input($input),
            'consequence' => $input['consequence'] ?? '',
        ], $providers);
        return ['message' => 'Governed deletion completed.', 'section' => 'governed-deletion', 'query' => ['request' => (string) $saved['request_key']]];
    }
    if ($action === 'cancel_operations_deletion') {
        $saved = yovel_admin_operations_cancel_deletion(bx_db(), $company, $admin, (string) ($input['request_key'] ?? ''), (string) ($input['reason'] ?? ''));
        return ['message' => 'Governed deletion cancelled.', 'section' => 'governed-deletion', 'query' => ['request' => (string) $saved['request_key']]];
    }
    throw new InvalidArgumentException('Unknown Operations workflow action.');
}
