<?php
declare(strict_types=1);

function yovel_admin_operations_plan_deletion(array $company, array $admin, array $input, array $providers = []): array
{
    [, $companyKeyHash] = yovel_admin_operations_scope($company, $admin);
    $idempotencyKey = yovel_admin_operations_workflow_idempotency((string) ($input['idempotency_key'] ?? ''));
    $recordType = yovel_admin_operations_bounded_text($input['record_type'] ?? '', 'Deletion record type', 160);
    $consequence = yovel_admin_operations_bounded_text(
        yovel_admin_operations_sanitize_evidence((string) ($input['consequence'] ?? '')),
        'Deletion consequence',
        2000
    );
    $targets = yovel_admin_operations_normalize_owner_targets(is_array($input['targets'] ?? null) ? $input['targets'] : [], 'owners.governed-delete.v1');
    $directory = yovel_admin_operations_call_read_contract('shared.record-type-directory.v1', $company, ['record_type' => $recordType], $providers);
    $record = $directory['records'][0] ?? null;
    if (!is_array($record)
        || strcasecmp(trim((string) ($record['record_type'] ?? '')), $recordType) !== 0
        || strtolower(trim((string) ($record['owner_contract'] ?? ''))) !== 'owners.governed-delete.v1'
        || ($record['deletable'] ?? false) !== true) {
        throw new RuntimeException('Deletion record directory did not authorize the requested owner contract.');
    }
    $normalizedTargets = [];
    foreach ($targets as $index => $target) {
        $normalizedTargets[] = [
            ...$target,
            'sequence_no' => $index + 1,
            'target_idempotency_key' => yovel_admin_operations_target_idempotency($idempotencyKey, $index + 1, $target['record_key']),
        ];
    }
    $planHash = hash('sha256', yovel_admin_operations_json([
        'company_key_hash' => $companyKeyHash,
        'idempotency_key' => $idempotencyKey,
        'record_type' => $recordType,
        'owner_contract' => 'owners.governed-delete.v1',
        'targets' => $normalizedTargets,
        'consequence' => $consequence,
    ]));
    return [
        'plan_hash' => $planHash,
        'company_key_hash' => $companyKeyHash,
        'idempotency_key' => $idempotencyKey,
        'record_type' => $recordType,
        'owner_contract' => 'owners.governed-delete.v1',
        'target_count' => count($normalizedTargets),
        'targets' => $normalizedTargets,
        'consequence' => $consequence,
    ];
}

function yovel_admin_operations_deletion_request(array $company, string $requestKey): ?array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    if (!yovel_admin_is_uuid($requestKey)) return null;
    $row = bx_db()->GetRow('SELECT * FROM project_company_operations_deletion_request WHERE company_key_hash=? AND request_key=?', [$companyKeyHash, $requestKey]);
    if (!$row) return null;
    foreach (['target_count', 'completed_count', 'failed_count'] as $column) $row[$column] = (int) $row[$column];
    $row['items'] = bx_db()->GetAll('SELECT * FROM project_company_operations_deletion_item WHERE company_key_hash=? AND request_key=? ORDER BY x_id', [$companyKeyHash, $requestKey]);
    $row['targets'] = bx_db()->GetAll('SELECT * FROM project_company_operations_deletion_target WHERE company_key_hash=? AND request_key=? ORDER BY sequence_no', [$companyKeyHash, $requestKey]);
    return $row;
}

function yovel_admin_operations_deletion_requests(array $company): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    return bx_db()->GetAll('SELECT * FROM project_company_operations_deletion_request WHERE company_key_hash=? ORDER BY created_at DESC,x_id DESC LIMIT 200', [$companyKeyHash]);
}

function yovel_admin_operations_submit_deletion(ADOConnection $db, array $company, array $admin, array $input, array $providers = []): array
{
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $plan = yovel_admin_operations_plan_deletion($company, $admin, $input, $providers);
    $deferred = filter_var($input['defer_execution'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($db->BeginTrans() === false) throw new RuntimeException('Operations deletion transaction could not start.');
    try {
        $existing = $db->GetRow('SELECT request_key FROM project_company_operations_deletion_request WHERE company_key_hash=? AND idempotency_key=? FOR UPDATE', [$companyKeyHash, $plan['idempotency_key']]);
        if ($existing) {
            $saved = yovel_admin_operations_deletion_request($company, (string) $existing['request_key']);
            if (!$saved) throw new RuntimeException('Operations deletion idempotent read-back failed.');
            if ((string) $saved['plan_hash'] !== (string) $plan['plan_hash']) throw new InvalidArgumentException('Deletion idempotency key already represents a different plan.');
            if ($db->CommitTrans() === false) throw new RuntimeException('Operations deletion transaction could not commit.');
            return $saved;
        }

        $requestKey = bx_uuid();
        $itemKey = bx_uuid();
        $initialStatus = $deferred ? 'QUEUED' : 'RUNNING';
        $job = yovel_admin_operations_insert_joined_job($db, $companyKey, $companyKeyHash, (string) $adminKey, 'delete:' . $plan['idempotency_key'], 'governed-deletion', [
            'request_key' => $requestKey, 'plan_hash' => $plan['plan_hash'], 'record_type' => $plan['record_type'], 'target_count' => $plan['target_count'],
        ], $initialStatus);
        yovel_admin_operations_db_execute($db,
            'INSERT INTO project_company_operations_deletion_request (request_key,company_key,company_key_hash,idempotency_key,job_key,plan_hash,record_type,target_count,consequence,status,created_by_admin_key,updated_by_admin_key,started_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$requestKey, $companyKey, $companyKeyHash, $plan['idempotency_key'], $job['job_key'], $plan['plan_hash'], $plan['record_type'], $plan['target_count'], $plan['consequence'], $initialStatus, $adminKey, $adminKey, $deferred ? null : yovel_admin_operations_database_datetime($db)],
            'Operations deletion request persistence'
        );
        yovel_admin_operations_db_execute($db,
            "INSERT INTO project_company_operations_deletion_item (item_key,request_key,company_key,company_key_hash,owner_contract,record_type,target_count,item_status) VALUES (?,?,?,?,?,?,?,'PENDING')",
            [$itemKey, $requestKey, $companyKey, $companyKeyHash, $plan['owner_contract'], $plan['record_type'], $plan['target_count']],
            'Operations deletion item persistence'
        );
        foreach ($plan['targets'] as $target) {
            $targetKey = bx_uuid();
            yovel_admin_operations_db_execute($db,
                "INSERT INTO project_company_operations_deletion_target (target_key,request_key,item_key,company_key,company_key_hash,sequence_no,owner_contract,owner_record_key,target_idempotency_key,target_status) VALUES (?,?,?,?,?,?,?,?,?,'PENDING')",
                [$targetKey, $requestKey, $itemKey, $companyKey, $companyKeyHash, $target['sequence_no'], $target['owner_contract'], $target['record_key'], $target['target_idempotency_key']],
                'Operations deletion target persistence'
            );
            if ($deferred) continue;
            $ownerResult = yovel_admin_operations_call_command_contract('owners.governed-delete.v1', $db, $company, $admin, [
                'record_key' => $target['record_key'], 'record_type' => $plan['record_type'], 'idempotency_key' => $target['target_idempotency_key'],
                'request_key' => $requestKey, 'plan_hash' => $plan['plan_hash'], 'dry_run' => false,
            ], $providers);
            $readBackJson = yovel_admin_operations_json((array) yovel_admin_operations_sanitize_evidence($ownerResult['read_back']));
            yovel_admin_operations_db_execute($db,
                "UPDATE project_company_operations_deletion_target SET target_status='COMPLETED',owner_status=?,read_back_json=?,owner_audit_key=? WHERE company_key_hash=? AND target_key=? AND target_status='PENDING'",
                [substr((string) ($ownerResult['status'] ?? 'DELETED'), 0, 80), $readBackJson, substr((string) $ownerResult['audit_key'], 0, 160), $companyKeyHash, $targetKey],
                'Operations deletion target completion'
            );
            $savedTarget = $db->GetRow('SELECT * FROM project_company_operations_deletion_target WHERE company_key_hash=? AND target_key=?', [$companyKeyHash, $targetKey]);
            if (!$savedTarget || (string) $savedTarget['target_status'] !== 'COMPLETED'
                || (string) $savedTarget['owner_record_key'] !== (string) $ownerResult['owner_record_key']
                || (string) $savedTarget['target_idempotency_key'] !== (string) $target['target_idempotency_key']
                || (string) $savedTarget['read_back_json'] !== $readBackJson) {
                throw new RuntimeException('Operations deletion target read-back verification failed.');
            }
        }
        if (!$deferred) {
            yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_deletion_item SET item_status='COMPLETED' WHERE company_key_hash=? AND item_key=? AND item_status='PENDING'", [$companyKeyHash, $itemKey], 'Operations deletion item completion');
            yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_deletion_request SET status='COMPLETED',completed_count=target_count,failed_count=0,updated_by_admin_key=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND request_key=? AND status='RUNNING'", [$adminKey, $companyKeyHash, $requestKey], 'Operations deletion request completion');
            yovel_admin_operations_complete_joined_job($db, $companyKeyHash, (string) $job['job_key'], (string) $adminKey, ['request_key' => $requestKey, 'completed_count' => $plan['target_count']]);
        }
        $saved = yovel_admin_operations_deletion_request($company, $requestKey);
        if (!$saved || (string) $saved['status'] !== ($deferred ? 'QUEUED' : 'COMPLETED') || (string) $saved['plan_hash'] !== (string) $plan['plan_hash']
            || (int) $saved['target_count'] !== (int) $plan['target_count'] || (int) $saved['completed_count'] !== ($deferred ? 0 : (int) $plan['target_count'])
            || count($saved['items']) !== 1 || count($saved['targets']) !== (int) $plan['target_count']) {
            throw new RuntimeException('Operations deletion request read-back verification failed.');
        }
        yovel_admin_operations_audit('SUBMIT', 'project_company_operations_deletion_request', $requestKey, $companyKeyHash, (string) $adminKey, [
            'idempotency_key' => $plan['idempotency_key'], 'job_key' => $job['job_key'], 'plan_hash' => $plan['plan_hash'],
            'record_type' => $plan['record_type'], 'target_count' => $plan['target_count'], 'status' => $saved['status'],
        ], 'Company administrator confirmed a governed deletion request.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Operations deletion transaction could not commit.');
        return $saved;
    } catch (Throwable $error) { $db->RollbackTrans(); throw $error; }
}

function yovel_admin_operations_cancel_deletion(ADOConnection $db, array $company, array $admin, string $requestKey, string $reason): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $reason = yovel_admin_operations_bounded_text(yovel_admin_operations_sanitize_evidence($reason), 'Deletion cancellation reason', 1000);
    if (!yovel_admin_is_uuid($requestKey)) throw new InvalidArgumentException('Deletion request reference is invalid.');
    if ($db->BeginTrans() === false) throw new RuntimeException('Operations deletion cancellation transaction could not start.');
    try {
        $request = $db->GetRow("SELECT * FROM project_company_operations_deletion_request WHERE company_key_hash=? AND request_key=? AND status='QUEUED' FOR UPDATE", [$companyKeyHash, $requestKey]);
        if (!$request) throw new InvalidArgumentException('Queued governed deletion request was not found.');
        yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_deletion_target SET target_status='CANCELLED' WHERE company_key_hash=? AND request_key=? AND target_status='PENDING'", [$companyKeyHash, $requestKey], 'Operations deletion target cancellation');
        yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_deletion_item SET item_status='CANCELLED' WHERE company_key_hash=? AND request_key=? AND item_status='PENDING'", [$companyKeyHash, $requestKey], 'Operations deletion item cancellation');
        yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_deletion_request SET status='CANCELLED',cancel_reason=?,updated_by_admin_key=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND request_key=? AND status='QUEUED'", [$reason, $adminKey, $companyKeyHash, $requestKey], 'Operations deletion cancellation');
        yovel_admin_operations_db_execute($db, "UPDATE project_company_operations_job SET status='CANCELLED',cancel_reason=?,updated_by_admin_key=?,finished_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND job_key=? AND status='QUEUED'", [$reason, $adminKey, $companyKeyHash, $request['job_key']], 'Operations deletion job cancellation');
        $saved = yovel_admin_operations_deletion_request($company, $requestKey);
        $jobStatus = $db->GetOne('SELECT status FROM project_company_operations_job WHERE company_key_hash=? AND job_key=?', [$companyKeyHash, $request['job_key']]);
        if (!$saved || (string) $saved['status'] !== 'CANCELLED' || (string) $saved['cancel_reason'] !== $reason || (string) $jobStatus !== 'CANCELLED'
            || count(array_filter($saved['items'], static fn (array $item): bool => $item['item_status'] !== 'CANCELLED')) !== 0
            || count(array_filter($saved['targets'], static fn (array $target): bool => $target['target_status'] !== 'CANCELLED')) !== 0) {
            throw new RuntimeException('Operations deletion cancellation read-back verification failed.');
        }
        yovel_admin_operations_audit('CANCEL', 'project_company_operations_deletion_request', $requestKey, $companyKeyHash, (string) $adminKey, ['reason' => $reason, 'job_key' => $request['job_key']], 'Company administrator cancelled a queued governed deletion request.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Operations deletion cancellation transaction could not commit.');
        return $saved;
    } catch (Throwable $error) { $db->RollbackTrans(); throw $error; }
}
