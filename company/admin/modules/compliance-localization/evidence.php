<?php
declare(strict_types=1);

function yovel_admin_compliance_evidence_storage_root(): string
{
    return __DIR__ . '/storage/evidence';
}

function yovel_admin_compliance_generated_path(string $path, string $root): bool
{
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
    $normalizedPath = str_replace('\\', '/', $path);
    return str_starts_with($normalizedPath, $normalizedRoot . '/') && !str_contains($normalizedPath, '/../');
}

function yovel_admin_compliance_upload_metadata(array $upload): array
{
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    $tmpName = (string) ($upload['tmp_name'] ?? '');
    $originalName = basename(trim((string) ($upload['name'] ?? '')));
    $declaredMime = strtolower(trim((string) ($upload['type'] ?? '')));
    $declaredSize = (int) ($upload['size'] ?? -1);
    if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
        throw new InvalidArgumentException('Evidence upload did not complete successfully.');
    }
    $actualSize = filesize($tmpName);
    if ($actualSize === false || $actualSize < 1 || $actualSize > 10 * 1024 * 1024 || $actualSize !== $declaredSize) {
        throw new InvalidArgumentException('Evidence upload size is invalid or exceeds 10 MB.');
    }
    if ($originalName === '' || strlen($originalName) > 255) {
        throw new InvalidArgumentException('Evidence file name is invalid.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $actualMime = strtolower((string) $finfo->file($tmpName));
    $allowed = [
        'application/pdf' => ['pdf'],
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'text/plain' => ['txt'],
        'text/csv' => ['csv'],
    ];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!isset($allowed[$actualMime]) || !in_array($extension, $allowed[$actualMime], true)) {
        throw new InvalidArgumentException('Evidence MIME type or extension is not allow-listed.');
    }
    if ($declaredMime !== '' && $declaredMime !== $actualMime) {
        throw new InvalidArgumentException('Evidence MIME metadata does not match the uploaded content.');
    }
    $sha256 = hash_file('sha256', $tmpName);
    if (!is_string($sha256) || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
        throw new RuntimeException('Evidence SHA-256 could not be calculated.');
    }
    return [
        'tmp_name' => $tmpName,
        'original_name' => $originalName,
        'mime_type' => $actualMime,
        'extension' => $extension,
        'byte_size' => $actualSize,
        'sha256' => $sha256,
    ];
}

function yovel_admin_compliance_evidence_input(array $input): array
{
    $evidenceType = yovel_admin_code((string) ($input['evidence_type'] ?? ''));
    $sourceModule = yovel_admin_code((string) ($input['source_module'] ?? 'COMPLIANCE_LOCALIZATION'));
    $sourceRecordType = yovel_admin_code((string) ($input['source_record_type'] ?? ''));
    $sourceRecordKey = trim((string) ($input['source_record_key'] ?? ''));
    $retentionRuleSetKey = trim((string) ($input['retention_rule_set_key'] ?? ''));
    $evidenceDate = yovel_admin_compliance_date($input['evidence_date'] ?? date('Y-m-d'), 'Evidence date');
    if ($evidenceType === '' || strlen($evidenceType) > 80 || $sourceModule === '' || strlen($sourceModule) > 80
        || $sourceRecordType === '' || strlen($sourceRecordType) > 80) {
        throw new InvalidArgumentException('Evidence type and source metadata are required.');
    }
    if ($sourceRecordKey !== '' && !yovel_admin_is_uuid($sourceRecordKey)) {
        throw new InvalidArgumentException('Evidence source record key is invalid.');
    }
    if ($retentionRuleSetKey !== '' && !yovel_admin_is_uuid($retentionRuleSetKey)) {
        throw new InvalidArgumentException('Evidence retention rule reference is invalid.');
    }
    return [
        'evidence_type' => $evidenceType,
        'source_module' => $sourceModule,
        'source_record_type' => $sourceRecordType,
        'source_record_key' => $sourceRecordKey,
        'retention_rule_set_key' => $retentionRuleSetKey,
        'evidence_date' => $evidenceDate,
        'description' => substr(trim((string) ($input['description'] ?? '')), 0, 1000),
    ];
}

function yovel_admin_compliance_retention_until(array $company, string $ruleSetKey, string $evidenceDate): ?string
{
    if ($ruleSetKey === '') {
        return null;
    }
    $rule = yovel_admin_compliance_rule_set($company, $ruleSetKey);
    if (!$rule || !in_array((string) $rule['version_status'], ['APPROVED', 'SUPERSEDED'], true)) {
        throw new InvalidArgumentException('Evidence retention requires an approved company rule version.');
    }
    if ((string) $rule['effective_from'] > $evidenceDate
        || (($rule['effective_to'] ?? null) !== null && (string) $rule['effective_to'] < $evidenceDate)) {
        throw new InvalidArgumentException('Evidence date is outside the retention rule effective period.');
    }
    $snapshot = json_decode((string) $rule['rule_json'], true, 512, JSON_THROW_ON_ERROR);
    $years = (int) ($snapshot['policy']['retention_years'] ?? $snapshot['retention_years'] ?? 0);
    if ($years < 1 || $years > 25) {
        throw new RuntimeException('Approved retention rule snapshot is invalid.');
    }
    return (new DateTimeImmutable($evidenceDate))->modify('+' . $years . ' years')->format('Y-m-d');
}

function yovel_admin_compliance_evidence(array $company, string $evidenceKey, bool $lock = false): ?array
{
    if (!$lock) {
        yovel_admin_compliance_schema();
    }
    [, $companyHash] = yovel_admin_compliance_read_scope($company);
    if (!yovel_admin_is_uuid($evidenceKey)) {
        return null;
    }
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_compliance_evidence WHERE company_key_hash = ? AND evidence_key = ?' . ($lock ? ' FOR UPDATE' : ''),
        [$companyHash, $evidenceKey]
    );
    if (!is_array($row) || $row === []) {
        return null;
    }
    $row['holds'] = bx_db()->GetAll(
        'SELECT * FROM project_company_compliance_retention_hold WHERE company_key_hash = ? AND evidence_key = ? ORDER BY placed_at, retention_hold_key',
        [$companyHash, $evidenceKey]
    ) ?: [];
    $activeHolds = array_values(array_filter($row['holds'], static fn (array $hold): bool => (string) $hold['hold_status'] === 'ACTIVE'));
    $row['retention_state'] = $activeHolds !== [] ? 'INDEFINITE_HOLD' : 'DATED';
    $row['active_hold_count'] = count($activeHolds);
    return $row;
}

function yovel_admin_compliance_evidence_rows(array $company): array
{
    yovel_admin_compliance_schema();
    [, $companyHash] = yovel_admin_compliance_read_scope($company);
    return bx_db()->GetAll(
        'SELECT * FROM project_company_compliance_evidence WHERE company_key_hash = ? ORDER BY created_at DESC, evidence_key DESC',
        [$companyHash]
    ) ?: [];
}

function yovel_admin_compliance_move_upload(string $source, string $target): void
{
    $moved = is_uploaded_file($source) ? move_uploaded_file($source, $target) : rename($source, $target);
    if (!$moved) {
        throw new RuntimeException('Evidence upload could not be moved to generated temporary storage.');
    }
}

function yovel_admin_compliance_save_evidence(
    ADOConnection $db,
    array $company,
    array $admin,
    array $upload,
    array $input,
    ?callable $checkpoint = null
): array {
    yovel_admin_compliance_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    $file = yovel_admin_compliance_upload_metadata($upload);
    $metadata = yovel_admin_compliance_evidence_input($input);
    $retentionUntil = yovel_admin_compliance_retention_until($company, $metadata['retention_rule_set_key'], $metadata['evidence_date']);
    $duplicate = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_compliance_evidence
         WHERE company_key_hash = ? AND sha256 = ? AND evidence_status <> 'ARCHIVED'",
        [$companyHash, $file['sha256']]
    );
    if ($duplicate > 0) {
        throw new InvalidArgumentException('Duplicate evidence content already exists for this company.');
    }

    $evidenceKey = bx_uuid();
    $root = yovel_admin_compliance_evidence_storage_root();
    $temporaryDirectory = $root . '/.tmp';
    $finalDirectory = $root . '/' . substr($companyHash, 0, 16);
    foreach ([$root, $temporaryDirectory, $finalDirectory] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Generated evidence storage directory could not be created.');
        }
    }
    $temporaryPath = $temporaryDirectory . '/' . $evidenceKey . '.part';
    $finalPath = $finalDirectory . '/' . $evidenceKey . '.' . $file['extension'];
    if (!yovel_admin_compliance_generated_path($temporaryPath, $root) || !yovel_admin_compliance_generated_path($finalPath, $root)) {
        throw new RuntimeException('Generated evidence path escaped the allow-listed storage root.');
    }
    yovel_admin_compliance_move_upload($file['tmp_name'], $temporaryPath);
    if (!hash_equals($file['sha256'], (string) hash_file('sha256', $temporaryPath))) {
        @unlink($temporaryPath);
        throw new RuntimeException('Generated temporary evidence checksum verification failed.');
    }

    $transactionStarted = false;
    try {
        if ($db->BeginTrans() === false) {
            throw new RuntimeException('Compliance transaction could not start.');
        }
        $transactionStarted = true;
        $duplicate = (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_compliance_evidence
             WHERE company_key_hash = ? AND sha256 = ? AND evidence_status <> 'ARCHIVED' FOR UPDATE",
            [$companyHash, $file['sha256']]
        );
        if ($duplicate > 0) {
            throw new InvalidArgumentException('Duplicate evidence content already exists for this company.');
        }
        if (!rename($temporaryPath, $finalPath)) {
            throw new RuntimeException('Evidence upload could not be moved to generated final storage.');
        }
        if ($checkpoint) {
            $checkpoint('after_final_move');
        }
        $metadataPayload = [
            'schema_version' => 1,
            'description' => $metadata['description'],
            'evidence_date' => $metadata['evidence_date'],
            'original_file_name' => $file['original_name'],
            'mime_type' => $file['mime_type'],
            'byte_size' => $file['byte_size'],
            'sha256' => $file['sha256'],
        ];
        $metadataJson = yovel_admin_compliance_json($metadataPayload);
        $metadataSha = yovel_admin_compliance_checksum($metadataPayload);
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_compliance_evidence (
                evidence_key, company_key, company_key_hash, evidence_type, source_module,
                source_record_type, source_record_key, original_file_name, mime_type, byte_size,
                storage_path, sha256, metadata_json, metadata_sha256, retention_rule_set_key,
                retention_until, evidence_status, created_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?)",
            [
                $evidenceKey, $companyKey, $companyHash, $metadata['evidence_type'], $metadata['source_module'],
                $metadata['source_record_type'], $metadata['source_record_key'] ?: null, $file['original_name'],
                $file['mime_type'], $file['byte_size'], $finalPath, $file['sha256'], $metadataJson, $metadataSha,
                $metadata['retention_rule_set_key'] ?: null, $retentionUntil, $adminKey,
            ],
            'Compliance evidence metadata save'
        );
        $saved = yovel_admin_compliance_evidence($company, $evidenceKey, true);
        if (!$saved
            || (string) $saved['company_key_hash'] !== $companyHash
            || (string) $saved['storage_path'] !== $finalPath
            || (string) $saved['sha256'] !== $file['sha256']
            || (string) $saved['metadata_json'] !== $metadataJson
            || (string) $saved['metadata_sha256'] !== $metadataSha
            || (int) $saved['byte_size'] !== $file['byte_size']
            || (string) $saved['retention_until'] !== (string) $retentionUntil
            || !is_file($finalPath)
            || !hash_equals($file['sha256'], (string) hash_file('sha256', $finalPath))) {
            throw new RuntimeException('Compliance evidence read-back verification failed.');
        }
        bx_audit(
            'CREATE',
            'project_company_compliance_evidence',
            $evidenceKey,
            ['company_key_hash' => $companyHash, 'sha256' => $file['sha256'], 'retention_until' => $retentionUntil, 'admin_key' => $adminKey],
            'Company administrator retained checksum-verified Compliance evidence.'
        );
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Compliance transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        if ($transactionStarted) {
            $db->RollbackTrans();
        }
        foreach ([$temporaryPath, $finalPath] as $attemptedPath) {
            if (yovel_admin_compliance_generated_path($attemptedPath, $root) && is_file($attemptedPath)) {
                unlink($attemptedPath);
            }
        }
        throw $error;
    }
}

function yovel_admin_compliance_verify_evidence(array $company, string $evidenceKey): bool
{
    $evidence = yovel_admin_compliance_evidence($company, $evidenceKey);
    if (!$evidence) {
        return false;
    }
    $root = yovel_admin_compliance_evidence_storage_root();
    $path = (string) $evidence['storage_path'];
    return yovel_admin_compliance_generated_path($path, $root)
        && is_file($path)
        && hash_equals((string) $evidence['sha256'], (string) hash_file('sha256', $path));
}

function yovel_admin_compliance_place_hold(
    ADOConnection $db,
    array $company,
    array $admin,
    string $evidenceKey,
    string $holdReference,
    string $holdReason
): array {
    yovel_admin_compliance_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    $holdReference = substr(trim($holdReference), 0, 160);
    $holdReason = substr(trim($holdReason), 0, 1000);
    if (!yovel_admin_is_uuid($evidenceKey) || $holdReference === '' || $holdReason === '') {
        throw new InvalidArgumentException('Evidence, hold reference, and hold reason are required.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Compliance transaction could not start.');
    }
    try {
        $evidence = yovel_admin_compliance_evidence($company, $evidenceKey, true);
        if (!$evidence) {
            throw new InvalidArgumentException('Compliance evidence was not found for this company.');
        }
        if ((string) $evidence['evidence_status'] === 'ARCHIVED') {
            throw new InvalidArgumentException('Archived evidence cannot be placed on hold.');
        }
        $holdKey = bx_uuid();
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_compliance_retention_hold (
                retention_hold_key, company_key, company_key_hash, evidence_key, hold_reference,
                hold_reason, hold_status, placed_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?)",
            [$holdKey, $companyKey, $companyHash, $evidenceKey, $holdReference, $holdReason, $adminKey],
            'Compliance retention hold placement'
        );
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_compliance_evidence SET evidence_status = 'HELD'
             WHERE company_key_hash = ? AND evidence_key = ?",
            [$companyHash, $evidenceKey],
            'Compliance evidence Held state'
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_compliance_retention_hold WHERE company_key_hash = ? AND retention_hold_key = ? FOR UPDATE',
            [$companyHash, $holdKey]
        );
        $savedEvidence = yovel_admin_compliance_evidence($company, $evidenceKey, true);
        if (!is_array($saved) || (string) $saved['hold_status'] !== 'ACTIVE'
            || (string) $saved['placed_by_admin_key'] !== $adminKey
            || !$savedEvidence || (string) $savedEvidence['evidence_status'] !== 'HELD') {
            throw new RuntimeException('Compliance retention hold read-back verification failed.');
        }
        bx_audit(
            'HOLD',
            'project_company_compliance_retention_hold',
            $holdKey,
            ['company_key_hash' => $companyHash, 'evidence_key' => $evidenceKey, 'hold_reference' => $holdReference, 'admin_key' => $adminKey],
            'Company administrator placed Compliance evidence on legal hold.'
        );
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Compliance transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_compliance_release_hold(
    ADOConnection $db,
    array $company,
    array $admin,
    string $holdKey,
    string $reason
): array {
    yovel_admin_compliance_schema();
    [, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    $reason = substr(trim($reason), 0, 1000);
    if (!yovel_admin_is_uuid($holdKey) || $reason === '') {
        throw new InvalidArgumentException('Active hold reference and release reason are required.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Compliance transaction could not start.');
    }
    try {
        $hold = $db->GetRow(
            "SELECT * FROM project_company_compliance_retention_hold
             WHERE company_key_hash = ? AND retention_hold_key = ? FOR UPDATE",
            [$companyHash, $holdKey]
        );
        if (!is_array($hold) || $hold === [] || (string) $hold['hold_status'] !== 'ACTIVE') {
            throw new InvalidArgumentException('Active Compliance evidence hold was not found for this company.');
        }
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_compliance_retention_hold
             SET hold_status = 'RELEASED', released_by_admin_key = ?, released_at = CURRENT_TIMESTAMP,
                 hold_reason = CONCAT(hold_reason, '\nRelease: ', ?)
             WHERE company_key_hash = ? AND retention_hold_key = ? AND hold_status = 'ACTIVE'",
            [$adminKey, $reason, $companyHash, $holdKey],
            'Compliance retention hold release'
        );
        $activeCount = (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_compliance_retention_hold
             WHERE company_key_hash = ? AND evidence_key = ? AND hold_status = 'ACTIVE'",
            [$companyHash, (string) $hold['evidence_key']]
        );
        if ($activeCount === 0) {
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_compliance_evidence SET evidence_status = 'ACTIVE'
                 WHERE company_key_hash = ? AND evidence_key = ? AND evidence_status = 'HELD'",
                [$companyHash, (string) $hold['evidence_key']],
                'Compliance evidence hold release state'
            );
        }
        $saved = $db->GetRow(
            'SELECT * FROM project_company_compliance_retention_hold WHERE company_key_hash = ? AND retention_hold_key = ? FOR UPDATE',
            [$companyHash, $holdKey]
        );
        if (!is_array($saved) || (string) $saved['hold_status'] !== 'RELEASED'
            || (string) $saved['released_by_admin_key'] !== $adminKey || ($saved['released_at'] ?? null) === null) {
            throw new RuntimeException('Compliance retention hold release read-back verification failed.');
        }
        bx_audit(
            'RELEASE',
            'project_company_compliance_retention_hold',
            $holdKey,
            ['company_key_hash' => $companyHash, 'evidence_key' => (string) $hold['evidence_key'], 'admin_key' => $adminKey],
            'Company administrator released a Compliance evidence legal hold.'
        );
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Compliance transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_compliance_archive_evidence(
    ADOConnection $db,
    array $company,
    array $admin,
    string $evidenceKey,
    string $reason
): array {
    yovel_admin_compliance_schema();
    [, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    $reason = substr(trim($reason), 0, 1000);
    if (!yovel_admin_is_uuid($evidenceKey) || $reason === '') {
        throw new InvalidArgumentException('Evidence reference and archive reason are required.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Compliance transaction could not start.');
    }
    try {
        $evidence = yovel_admin_compliance_evidence($company, $evidenceKey, true);
        if (!$evidence) {
            throw new InvalidArgumentException('Compliance evidence was not found for this company.');
        }
        if ((int) $evidence['active_hold_count'] > 0 || (string) $evidence['evidence_status'] === 'HELD') {
            throw new InvalidArgumentException('Evidence on active legal hold cannot be archived or removed.');
        }
        if ((string) $evidence['evidence_status'] === 'ARCHIVED') {
            throw new InvalidArgumentException('Compliance evidence is already archived.');
        }
        if (($evidence['retention_until'] ?? null) !== null && (string) $evidence['retention_until'] > date('Y-m-d')) {
            throw new InvalidArgumentException('Evidence cannot be archived before its retention date.');
        }
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_compliance_evidence SET evidence_status = 'ARCHIVED'
             WHERE company_key_hash = ? AND evidence_key = ?",
            [$companyHash, $evidenceKey],
            'Compliance evidence archive'
        );
        $saved = yovel_admin_compliance_evidence($company, $evidenceKey, true);
        if (!$saved || (string) $saved['evidence_status'] !== 'ARCHIVED'
            || (string) $saved['sha256'] !== (string) $evidence['sha256']) {
            throw new RuntimeException('Compliance evidence archive read-back verification failed.');
        }
        bx_audit(
            'ARCHIVE',
            'project_company_compliance_evidence',
            $evidenceKey,
            ['company_key_hash' => $companyHash, 'sha256' => (string) $evidence['sha256'], 'admin_key' => $adminKey],
            $reason
        );
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Compliance transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}
