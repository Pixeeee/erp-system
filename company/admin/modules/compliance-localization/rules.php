<?php
declare(strict_types=1);

function yovel_admin_compliance_read_scope(array $company): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if ($companyKey === '' || strlen($companyKey) > 1500 || preg_match('/^[a-f0-9]{64}$/', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('Compliance company scope is invalid.');
    }
    $active = (int) bx_db()->GetOne(
        "SELECT COUNT(*) FROM project_company
         WHERE company_key = ? AND company_key_hash = ? AND company_status = 'ACTIVE'",
        [$companyKey, $companyKeyHash]
    );
    if ($active !== 1) {
        throw new InvalidArgumentException('Compliance company scope is unavailable.');
    }
    return [$companyKey, $companyKeyHash];
}

function yovel_admin_compliance_json(array $payload): string
{
    return json_encode(
        yovel_admin_compliance_canonical_value($payload),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    );
}

function yovel_admin_compliance_date(mixed $value, string $label, bool $optional = false): ?string
{
    $date = trim((string) $value);
    if ($date === '' && $optional) {
        return null;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException($label . ' must be a valid date.');
    }
    return $date;
}

function yovel_admin_compliance_dependency_provider(string $contractId, array $providers = []): callable
{
    $allowed = ['accounting-finance.account-reference.v1', 'accounting-finance.invoice-snapshot.v1'];
    if (!in_array($contractId, $allowed, true)) {
        throw new InvalidArgumentException('Compliance dependency contract is not allow-listed.');
    }
    $provider = $providers[$contractId] ?? null;
    if (!is_callable($provider)) {
        throw new LogicException('Compliance dependency provider is unavailable: ' . $contractId);
    }
    return $provider;
}

function yovel_admin_compliance_finance_account_reference(
    array $company,
    string $accountKey,
    callable $provider
): array {
    if (!yovel_admin_is_uuid($accountKey)) {
        throw new InvalidArgumentException('Finance account reference is invalid.');
    }
    $result = $provider($company, $accountKey);
    if (!is_array($result)) {
        throw new RuntimeException('Finance account-reference validation failed company scope or active posting-account requirements.');
    }
    $record = is_array($result['record'] ?? null) ? $result['record'] : [];
    $accountCode = trim((string) ($record['account_code'] ?? ''));
    $accountName = trim((string) ($record['account_name'] ?? ''));
    if (($result['ok'] ?? false) !== true
        || !hash_equals((string) $company['company_key_hash'], strtolower(trim((string) ($result['company_key_hash'] ?? ''))))
        || !is_array($result['errors'] ?? null)
        || $result['errors'] !== []
        || !hash_equals($accountKey, trim((string) ($record['account_key'] ?? '')))
        || strtoupper(trim((string) ($record['account_status'] ?? ''))) !== 'ACTIVE'
        || ($record['is_group'] ?? null) !== false
        || $accountCode === ''
        || strlen($accountCode) > 120
        || $accountName === ''
        || strlen($accountName) > 180) {
        throw new RuntimeException('Finance account-reference validation failed company scope or active posting-account requirements.');
    }
    return [
        'account_key' => $accountKey,
        'account_code' => $accountCode,
        'account_name' => $accountName,
        'account_status' => 'ACTIVE',
        'is_group' => false,
    ];
}

function yovel_admin_compliance_rule_input(array $input): array
{
    $ruleType = yovel_admin_code((string) ($input['rule_type'] ?? ''));
    if (!in_array($ruleType, ['VAT_SETTINGS', 'TAX_POLICY', 'RETENTION_POLICY'], true)) {
        throw new InvalidArgumentException('Compliance rule type is not allow-listed.');
    }
    $ruleCode = yovel_admin_code((string) ($input['rule_code'] ?? ''));
    $ruleName = substr(trim((string) ($input['rule_name'] ?? '')), 0, 180);
    $versionNumber = (int) ($input['version_number'] ?? 0);
    $effectiveFrom = yovel_admin_compliance_date($input['effective_from'] ?? '', 'Effective from');
    $effectiveTo = yovel_admin_compliance_date($input['effective_to'] ?? '', 'Effective to', true);
    if ($ruleCode === '' || strlen($ruleCode) > 80 || $ruleName === '' || $versionNumber < 1 || $versionNumber > 100000) {
        throw new InvalidArgumentException('Rule code, name, and positive version are required.');
    }
    if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
        throw new InvalidArgumentException('Effective-to date cannot precede effective-from date.');
    }
    $authorityReference = substr(trim((string) ($input['authority_reference'] ?? '')), 0, 160);
    $authorityUrl = substr(trim((string) ($input['authority_url'] ?? '')), 0, 500);
    if ($authorityReference === '' || ($authorityUrl !== '' && filter_var($authorityUrl, FILTER_VALIDATE_URL) === false)) {
        throw new InvalidArgumentException('Legal authority reference and a valid optional URL are required.');
    }
    $vatClass = yovel_admin_code((string) ($input['vat_registration_class'] ?? ''));
    if ($vatClass === '' || strlen($vatClass) > 80) {
        throw new InvalidArgumentException('VAT registration class is required.');
    }
    $retentionYears = (int) ($input['retention_years'] ?? 0);
    if ($retentionYears < 1 || $retentionYears > 25) {
        throw new InvalidArgumentException('Retention years must be between 1 and 25.');
    }
    $normalizeCodes = static function (mixed $values, string $label): array {
        if (is_string($values)) {
            $values = array_filter(array_map('trim', explode(',', $values)));
        }
        if (!is_array($values)) {
            throw new InvalidArgumentException($label . ' must be a list.');
        }
        $codes = [];
        foreach ($values as $value) {
            $code = yovel_admin_code((string) $value);
            if ($code === '' || strlen($code) > 120) {
                throw new InvalidArgumentException($label . ' contains an invalid code.');
            }
            $codes[$code] = $code;
        }
        ksort($codes, SORT_STRING);
        return array_values($codes);
    };
    $sourceEvidenceKey = trim((string) ($input['source_evidence_key'] ?? ''));
    if ($sourceEvidenceKey !== '' && !yovel_admin_is_uuid($sourceEvidenceKey)) {
        throw new InvalidArgumentException('Source evidence reference is invalid.');
    }
    return [
        'rule_set_key' => trim((string) ($input['rule_set_key'] ?? '')),
        'rule_type' => $ruleType,
        'rule_code' => $ruleCode,
        'rule_name' => $ruleName,
        'version_number' => $versionNumber,
        'effective_from' => $effectiveFrom,
        'effective_to' => $effectiveTo,
        'authority_reference' => $authorityReference,
        'authority_url' => $authorityUrl,
        'source_evidence_key' => $sourceEvidenceKey,
        'vat_registration_class' => $vatClass,
        'tax_code_policy_refs' => $normalizeCodes($input['tax_code_policy_refs'] ?? [], 'Tax-code policy references'),
        'retention_years' => $retentionYears,
        'invoice_profile_keys' => $normalizeCodes($input['invoice_profile_keys'] ?? [], 'Invoice profile keys'),
    ];
}

function yovel_admin_compliance_normalize_mappings(array $company, mixed $mappings, ?callable $provider): array
{
    if (is_string($mappings)) {
        try {
            $mappings = json_decode($mappings, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Account mappings must be valid JSON.', 0, $error);
        }
    }
    if (!is_array($mappings)) {
        throw new InvalidArgumentException('Account mappings must be structured rows.');
    }
    if ($mappings !== [] && !is_callable($provider)) {
        throw new LogicException('Finance account-reference provider is required for account mappings.');
    }
    $roles = ['OUTPUT_VAT', 'INPUT_VAT', 'WITHHOLDING', 'ADJUSTMENT', 'SETTLEMENT'];
    $normalized = [];
    foreach (array_values($mappings) as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Each account mapping must be a structured row.');
        }
        $mappingCode = yovel_admin_code((string) ($row['mapping_code'] ?? ''));
        $taxRole = yovel_admin_code((string) ($row['tax_role'] ?? ''));
        $accountKey = trim((string) ($row['finance_account_key'] ?? ''));
        if ($mappingCode === '' || strlen($mappingCode) > 80 || !in_array($taxRole, $roles, true)) {
            throw new InvalidArgumentException('Mapping code and tax role are invalid.');
        }
        if (isset($normalized[$mappingCode])) {
            throw new InvalidArgumentException('Duplicate account mapping code: ' . $mappingCode);
        }
        $normalized[$mappingCode] = [
            'tax_account_map_key' => yovel_admin_is_uuid((string) ($row['tax_account_map_key'] ?? '')) ? (string) $row['tax_account_map_key'] : bx_uuid(),
            'mapping_code' => $mappingCode,
            'tax_role' => $taxRole,
            'finance_account_key' => $accountKey,
            'finance_reference' => yovel_admin_compliance_finance_account_reference($company, $accountKey, $provider),
        ];
    }
    ksort($normalized, SORT_STRING);
    return array_values($normalized);
}

function yovel_admin_compliance_rule_set(array $company, string $ruleSetKey, bool $lock = false): ?array
{
    if (!$lock) {
        yovel_admin_compliance_schema();
    }
    [, $companyHash] = yovel_admin_compliance_read_scope($company);
    if (!yovel_admin_is_uuid($ruleSetKey)) {
        return null;
    }
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_compliance_rule_set WHERE company_key_hash = ? AND rule_set_key = ?' . ($lock ? ' FOR UPDATE' : ''),
        [$companyHash, $ruleSetKey]
    );
    if (!is_array($row) || $row === []) {
        return null;
    }
    $row['mappings'] = bx_db()->GetAll(
        'SELECT * FROM project_company_compliance_tax_account_map WHERE company_key_hash = ? AND rule_set_key = ? ORDER BY mapping_code, tax_account_map_key',
        [$companyHash, $ruleSetKey]
    ) ?: [];
    $row['approvals'] = bx_db()->GetAll(
        'SELECT * FROM project_company_compliance_approval WHERE company_key_hash = ? AND record_type = ? AND record_key = ? ORDER BY requested_at, approval_key',
        [$companyHash, 'RULE_SET', $ruleSetKey]
    ) ?: [];
    return $row;
}

function yovel_admin_compliance_rule_sets(array $company): array
{
    yovel_admin_compliance_schema();
    [, $companyHash] = yovel_admin_compliance_read_scope($company);
    return bx_db()->GetAll(
        'SELECT * FROM project_company_compliance_rule_set WHERE company_key_hash = ? ORDER BY rule_type, rule_code, version_number DESC, x_id DESC',
        [$companyHash]
    ) ?: [];
}

function yovel_admin_compliance_save_rule_set(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input,
    ?callable $financeAccountValidator = null,
    ?callable $checkpoint = null
): array {
    yovel_admin_compliance_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    $normalized = yovel_admin_compliance_rule_input($input);
    $mappingsProvided = array_key_exists('mappings', $input) || array_key_exists('mappings_json', $input);
    $mappings = $mappingsProvided
        ? yovel_admin_compliance_normalize_mappings($company, $input['mappings'] ?? $input['mappings_json'], $financeAccountValidator)
        : [];
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Compliance transaction could not start.');
    }
    try {
        $existing = null;
        if ($normalized['rule_set_key'] !== '') {
            $existing = yovel_admin_compliance_rule_set($company, $normalized['rule_set_key'], true);
            if ($existing === null) {
                throw new InvalidArgumentException('Compliance rule version was not found for this company.');
            }
            if ((string) $existing['version_status'] !== 'DRAFT') {
                throw new InvalidArgumentException('Approved, superseded, and archived rule versions are immutable.');
            }
        }
        $ruleSetKey = $existing ? (string) $existing['rule_set_key'] : bx_uuid();
        $mappingReferences = [];
        foreach ($mappings as $mapping) {
            $mappingReferences[(string) $mapping['mapping_code']] = $mapping['finance_reference'];
        }
        if (!$mappingsProvided && $existing) {
            $existingPayload = json_decode((string) $existing['rule_json'], true, 512, JSON_THROW_ON_ERROR);
            $mappingReferences = is_array($existingPayload['account_reference_snapshots'] ?? null)
                ? $existingPayload['account_reference_snapshots']
                : [];
        }
        $payload = [
            'schema_version' => 1,
            'jurisdiction' => 'PH',
            'vat_registration_class' => $normalized['vat_registration_class'],
            'tax_code_policy_refs' => $normalized['tax_code_policy_refs'],
            'retention_years' => $normalized['retention_years'],
            'invoice_profile_keys' => $normalized['invoice_profile_keys'],
            'account_reference_snapshots' => $mappingReferences,
        ];
        $payloadJson = yovel_admin_compliance_json($payload);
        $payloadSha = yovel_admin_compliance_checksum($payload);
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_compliance_rule_set (
                rule_set_key, company_key, company_key_hash, jurisdiction_code, rule_type, rule_code,
                rule_name, version_number, version_status, effective_from, effective_to,
                authority_reference, authority_url, source_evidence_key, rule_json, rule_sha256,
                created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, 'PH', ?, ?, ?, ?, 'DRAFT', ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                rule_name = VALUES(rule_name), effective_from = VALUES(effective_from), effective_to = VALUES(effective_to),
                authority_reference = VALUES(authority_reference), authority_url = VALUES(authority_url),
                source_evidence_key = VALUES(source_evidence_key), rule_json = VALUES(rule_json),
                rule_sha256 = VALUES(rule_sha256), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $ruleSetKey, $companyKey, $companyHash, $normalized['rule_type'], $normalized['rule_code'],
                $normalized['rule_name'], $normalized['version_number'], $normalized['effective_from'],
                $normalized['effective_to'], $normalized['authority_reference'], $normalized['authority_url'] ?: null,
                $normalized['source_evidence_key'] ?: null, $payloadJson, $payloadSha, $adminKey, $adminKey,
            ],
            'Compliance rule Draft save'
        );
        if ($mappingsProvided) {
            $stableMappingKeys = [];
            foreach ((array) ($existing['mappings'] ?? []) as $existingMapping) {
                $stableMappingKeys[(string) $existingMapping['mapping_code']] = (string) $existingMapping['tax_account_map_key'];
            }
            foreach ($mappings as &$mapping) {
                if (isset($stableMappingKeys[$mapping['mapping_code']])) {
                    $mapping['tax_account_map_key'] = $stableMappingKeys[$mapping['mapping_code']];
                }
            }
            unset($mapping);
            yovel_admin_db_execute(
                $db,
                'DELETE FROM project_company_compliance_tax_account_map WHERE company_key_hash = ? AND rule_set_key = ?',
                [$companyHash, $ruleSetKey],
                'Compliance Draft mapping reset'
            );
            foreach ($mappings as $mapping) {
                yovel_admin_db_execute(
                    $db,
                    "INSERT INTO project_company_compliance_tax_account_map (
                        tax_account_map_key, company_key, company_key_hash, rule_set_key, mapping_code,
                        tax_role, finance_account_key, mapping_status, created_by_admin_key, updated_by_admin_key
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                    [
                        $mapping['tax_account_map_key'], $companyKey, $companyHash, $ruleSetKey,
                        $mapping['mapping_code'], $mapping['tax_role'], $mapping['finance_account_key'], $adminKey, $adminKey,
                    ],
                    'Compliance Draft account mapping save'
                );
            }
        }
        if ($checkpoint) {
            $checkpoint('after_mappings');
        }
        $saved = yovel_admin_compliance_rule_set($company, $ruleSetKey, true);
        $expectedMappingCount = $mappingsProvided ? count($mappings) : count((array) ($existing['mappings'] ?? []));
        if (!$saved
            || (string) $saved['rule_set_key'] !== $ruleSetKey
            || (string) $saved['company_key'] !== $companyKey
            || (string) $saved['company_key_hash'] !== $companyHash
            || (string) $saved['rule_type'] !== $normalized['rule_type']
            || (string) $saved['rule_code'] !== $normalized['rule_code']
            || (string) $saved['rule_name'] !== $normalized['rule_name']
            || (int) $saved['version_number'] !== $normalized['version_number']
            || (string) $saved['version_status'] !== 'DRAFT'
            || (string) $saved['effective_from'] !== $normalized['effective_from']
            || (($saved['effective_to'] ?? null) ?: null) !== $normalized['effective_to']
            || (string) $saved['rule_json'] !== $payloadJson
            || (string) $saved['rule_sha256'] !== $payloadSha
            || count($saved['mappings']) !== $expectedMappingCount) {
            throw new RuntimeException('Compliance rule Draft read-back verification failed.');
        }
        bx_audit(
            $existing ? 'UPDATE' : 'CREATE',
            'project_company_compliance_rule_set',
            $ruleSetKey,
            ['company_key_hash' => $companyHash, 'rule_code' => $normalized['rule_code'], 'version_number' => $normalized['version_number'], 'admin_key' => $adminKey],
            'Company administrator saved a Compliance rule Draft.'
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

function yovel_admin_compliance_add_mapping(
    ADOConnection $db,
    array $company,
    array $admin,
    string $ruleSetKey,
    array $input,
    callable $financeAccountValidator
): array {
    yovel_admin_compliance_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    $mapping = yovel_admin_compliance_normalize_mappings($company, [$input], $financeAccountValidator)[0];
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Compliance transaction could not start.');
    }
    try {
        $rule = yovel_admin_compliance_rule_set($company, $ruleSetKey, true);
        if (!$rule) {
            throw new InvalidArgumentException('Compliance rule version was not found for this company.');
        }
        if ((string) $rule['version_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Approved rule account mappings are immutable.');
        }
        $existingMap = $db->GetRow(
            'SELECT * FROM project_company_compliance_tax_account_map WHERE company_key_hash = ? AND rule_set_key = ? AND mapping_code = ? FOR UPDATE',
            [$companyHash, $ruleSetKey, $mapping['mapping_code']]
        );
        $mappingKey = is_array($existingMap) && $existingMap !== [] ? (string) $existingMap['tax_account_map_key'] : $mapping['tax_account_map_key'];
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_compliance_tax_account_map (
                tax_account_map_key, company_key, company_key_hash, rule_set_key, mapping_code,
                tax_role, finance_account_key, mapping_status, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)
             ON DUPLICATE KEY UPDATE tax_role = VALUES(tax_role), finance_account_key = VALUES(finance_account_key),
                mapping_status = 'ACTIVE', updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$mappingKey, $companyKey, $companyHash, $ruleSetKey, $mapping['mapping_code'], $mapping['tax_role'], $mapping['finance_account_key'], $adminKey, $adminKey],
            'Compliance Draft account mapping upsert'
        );
        $payload = json_decode((string) $rule['rule_json'], true, 512, JSON_THROW_ON_ERROR);
        $payload['account_reference_snapshots'][$mapping['mapping_code']] = $mapping['finance_reference'];
        ksort($payload['account_reference_snapshots'], SORT_STRING);
        $payloadJson = yovel_admin_compliance_json($payload);
        $payloadSha = yovel_admin_compliance_checksum($payload);
        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_compliance_rule_set SET rule_json = ?, rule_sha256 = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND rule_set_key = ?',
            [$payloadJson, $payloadSha, $adminKey, $companyHash, $ruleSetKey],
            'Compliance Draft mapping snapshot update'
        );
        $saved = yovel_admin_compliance_rule_set($company, $ruleSetKey, true);
        $savedMap = array_values(array_filter($saved['mappings'] ?? [], static fn (array $row): bool => (string) $row['mapping_code'] === $mapping['mapping_code']));
        if (!$saved || count($savedMap) !== 1
            || (string) $savedMap[0]['finance_account_key'] !== $mapping['finance_account_key']
            || (string) $saved['rule_sha256'] !== $payloadSha) {
            throw new RuntimeException('Compliance account mapping read-back verification failed.');
        }
        bx_audit(
            $existingMap ? 'UPDATE' : 'CREATE',
            'project_company_compliance_tax_account_map',
            $mappingKey,
            ['company_key_hash' => $companyHash, 'rule_set_key' => $ruleSetKey, 'mapping_code' => $mapping['mapping_code'], 'admin_key' => $adminKey],
            'Company administrator saved a Compliance Draft account mapping.'
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

function yovel_admin_compliance_rule_snapshot(array $rule): array
{
    $payload = json_decode((string) $rule['rule_json'], true, 512, JSON_THROW_ON_ERROR);
    $mappings = [];
    foreach ((array) ($rule['mappings'] ?? []) as $mapping) {
        $code = (string) $mapping['mapping_code'];
        $mappings[] = [
            'mapping_code' => $code,
            'tax_role' => (string) $mapping['tax_role'],
            'finance_account_key' => (string) $mapping['finance_account_key'],
            'finance_reference' => $payload['account_reference_snapshots'][$code] ?? [],
        ];
    }
    usort($mappings, static fn (array $left, array $right): int => strcmp($left['mapping_code'], $right['mapping_code']));
    return [
        'schema_version' => 1,
        'rule_set_key' => (string) $rule['rule_set_key'],
        'jurisdiction' => (string) $rule['jurisdiction_code'],
        'rule_type' => (string) $rule['rule_type'],
        'rule_code' => (string) $rule['rule_code'],
        'rule_name' => (string) $rule['rule_name'],
        'version_number' => (int) $rule['version_number'],
        'effective_from' => (string) $rule['effective_from'],
        'effective_to' => ($rule['effective_to'] ?? null) ?: null,
        'authority' => [
            'reference' => (string) $rule['authority_reference'],
            'url' => ($rule['authority_url'] ?? null) ?: null,
            'source_evidence_key' => ($rule['source_evidence_key'] ?? null) ?: null,
        ],
        'policy' => [
            'vat_registration_class' => (string) ($payload['vat_registration_class'] ?? ''),
            'tax_code_policy_refs' => array_values((array) ($payload['tax_code_policy_refs'] ?? [])),
            'retention_years' => (int) ($payload['retention_years'] ?? 0),
            'invoice_profile_keys' => array_values((array) ($payload['invoice_profile_keys'] ?? [])),
        ],
        'account_mappings' => $mappings,
    ];
}

function yovel_admin_compliance_approve_rule_set(
    ADOConnection $db,
    array $company,
    array $admin,
    string $ruleSetKey,
    string $comments = ''
): array {
    yovel_admin_compliance_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    if (!yovel_admin_is_uuid($ruleSetKey)) {
        throw new InvalidArgumentException('Compliance rule reference is invalid.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Compliance transaction could not start.');
    }
    try {
        $rule = yovel_admin_compliance_rule_set($company, $ruleSetKey, true);
        if (!$rule) {
            throw new InvalidArgumentException('Compliance rule version was not found for this company.');
        }
        if ((string) $rule['version_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Only a Draft Compliance rule may be approved.');
        }
        if (hash_equals((string) $rule['created_by_admin_key'], $adminKey)) {
            throw new InvalidArgumentException('Compliance approval requires separation of duties from the creator.');
        }
        if ((string) $rule['rule_type'] === 'VAT_SETTINGS' && ($rule['mappings'] ?? []) === []) {
            throw new InvalidArgumentException('VAT Settings approval requires at least one validated Finance account mapping.');
        }
        $overlap = (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_compliance_rule_set
             WHERE company_key_hash = ? AND jurisdiction_code = ? AND rule_type = ?
               AND version_status = 'APPROVED' AND rule_set_key <> ?
               AND effective_from <= COALESCE(?, '9999-12-31')
               AND COALESCE(effective_to, '9999-12-31') >= ?",
            [$companyHash, (string) $rule['jurisdiction_code'], (string) $rule['rule_type'], $ruleSetKey, ($rule['effective_to'] ?? null) ?: null, (string) $rule['effective_from']]
        );
        if ($overlap > 0) {
            throw new InvalidArgumentException('Approved Compliance rule periods cannot overlap for the same jurisdiction and rule type.');
        }
        $snapshot = yovel_admin_compliance_rule_snapshot($rule);
        $snapshotJson = yovel_admin_compliance_json($snapshot);
        $snapshotSha = yovel_admin_compliance_checksum($snapshot);
        $approvalKey = bx_uuid();
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_compliance_approval (
                approval_key, company_key, company_key_hash, record_type, record_key, approval_action,
                decision_status, record_sha256, comments, requested_by_admin_key, decided_by_admin_key, decided_at
             ) VALUES (?, ?, ?, 'RULE_SET', ?, 'APPROVE', 'APPROVED', ?, ?, ?, ?, CURRENT_TIMESTAMP)",
            [$approvalKey, $companyKey, $companyHash, $ruleSetKey, $snapshotSha, substr(trim($comments), 0, 1000) ?: null, (string) $rule['created_by_admin_key'], $adminKey],
            'Compliance rule approval record'
        );
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_compliance_rule_set
             SET version_status = 'APPROVED', rule_json = ?, rule_sha256 = ?, approved_by_admin_key = ?,
                 approved_at = CURRENT_TIMESTAMP, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND rule_set_key = ? AND version_status = 'DRAFT'",
            [$snapshotJson, $snapshotSha, $adminKey, $adminKey, $companyHash, $ruleSetKey],
            'Compliance rule approval'
        );
        $saved = yovel_admin_compliance_rule_set($company, $ruleSetKey, true);
        if (!$saved || (string) $saved['version_status'] !== 'APPROVED'
            || (string) $saved['approved_by_admin_key'] !== $adminKey
            || (string) $saved['rule_json'] !== $snapshotJson
            || (string) $saved['rule_sha256'] !== $snapshotSha
            || count($saved['approvals']) !== 1) {
            throw new RuntimeException('Compliance rule approval read-back verification failed.');
        }
        bx_audit(
            'APPROVE',
            'project_company_compliance_rule_set',
            $ruleSetKey,
            ['company_key_hash' => $companyHash, 'record_sha256' => $snapshotSha, 'approval_key' => $approvalKey, 'admin_key' => $adminKey],
            'Company administrator approved an immutable Compliance rule snapshot.'
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

function yovel_admin_compliance_transition_rule_set(
    ADOConnection $db,
    array $company,
    array $admin,
    string $ruleSetKey,
    string $toStatus,
    string $comments
): array {
    yovel_admin_compliance_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    if (!yovel_admin_is_uuid($ruleSetKey)) {
        throw new InvalidArgumentException('Compliance rule reference is invalid.');
    }
    $toStatus = strtoupper(trim($toStatus));
    $action = $toStatus === 'SUPERSEDED' ? 'SUPERSEDE' : 'ARCHIVE';
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Compliance transaction could not start.');
    }
    try {
        $rule = yovel_admin_compliance_rule_set($company, $ruleSetKey, true);
        if (!$rule) {
            throw new InvalidArgumentException('Compliance rule version was not found for this company.');
        }
        yovel_admin_compliance_assert_transition('version', (string) $rule['version_status'], $toStatus);
        if ((string) $rule['version_status'] !== 'DRAFT' && hash_equals((string) $rule['created_by_admin_key'], $adminKey)) {
            throw new InvalidArgumentException('Compliance lifecycle approval requires separation of duties from the creator.');
        }
        $approvalKey = bx_uuid();
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_compliance_approval (
                approval_key, company_key, company_key_hash, record_type, record_key, approval_action,
                decision_status, record_sha256, comments, requested_by_admin_key, decided_by_admin_key, decided_at
             ) VALUES (?, ?, ?, 'RULE_SET', ?, ?, 'APPROVED', ?, ?, ?, ?, CURRENT_TIMESTAMP)",
            [$approvalKey, $companyKey, $companyHash, $ruleSetKey, $action, (string) $rule['rule_sha256'], substr(trim($comments), 0, 1000) ?: null, (string) $rule['created_by_admin_key'], $adminKey],
            'Compliance rule lifecycle approval record'
        );
        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_compliance_rule_set SET version_status = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND rule_set_key = ?',
            [$toStatus, $adminKey, $companyHash, $ruleSetKey],
            'Compliance rule lifecycle transition'
        );
        $saved = yovel_admin_compliance_rule_set($company, $ruleSetKey, true);
        if (!$saved || (string) $saved['version_status'] !== $toStatus
            || (string) $saved['rule_sha256'] !== (string) $rule['rule_sha256']
            || (string) $saved['rule_json'] !== (string) $rule['rule_json']) {
            throw new RuntimeException('Compliance rule lifecycle read-back verification failed.');
        }
        bx_audit(
            $action,
            'project_company_compliance_rule_set',
            $ruleSetKey,
            ['company_key_hash' => $companyHash, 'record_sha256' => (string) $rule['rule_sha256'], 'approval_key' => $approvalKey, 'admin_key' => $adminKey],
            'Company administrator applied an immutable Compliance rule lifecycle transition.'
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

function yovel_admin_compliance_supersede_rule_set(ADOConnection $db, array $company, array $admin, string $ruleSetKey, string $comments = ''): array
{
    return yovel_admin_compliance_transition_rule_set($db, $company, $admin, $ruleSetKey, 'SUPERSEDED', $comments);
}

function yovel_admin_compliance_archive_rule_set(ADOConnection $db, array $company, array $admin, string $ruleSetKey, string $comments = ''): array
{
    return yovel_admin_compliance_transition_rule_set($db, $company, $admin, $ruleSetKey, 'ARCHIVED', $comments);
}

function yovel_admin_compliance_effective_rule_set(array $company, string $ruleType, string $effectiveDate): ?array
{
    yovel_admin_compliance_schema();
    [, $companyHash] = yovel_admin_compliance_read_scope($company);
    $ruleType = yovel_admin_code($ruleType);
    $effectiveDate = (string) yovel_admin_compliance_date($effectiveDate, 'Effective date');
    $row = bx_db()->GetRow(
        "SELECT rule_set_key FROM project_company_compliance_rule_set
         WHERE company_key_hash = ? AND jurisdiction_code = 'PH' AND rule_type = ?
           AND version_status IN ('APPROVED','SUPERSEDED')
           AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
         ORDER BY effective_from DESC, version_number DESC, x_id DESC LIMIT 1",
        [$companyHash, $ruleType, $effectiveDate, $effectiveDate]
    );
    return is_array($row) && $row !== []
        ? yovel_admin_compliance_rule_set($company, (string) $row['rule_set_key'])
        : null;
}
