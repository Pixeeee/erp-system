<?php
declare(strict_types=1);

function yovel_admin_compliance_invoice_profile_codes(): array
{
    return [
        'DETAILED' => 'PH_DETAILED_TAX_INVOICE',
        'SIMPLIFIED' => 'PH_SIMPLIFIED_TAX_INVOICE',
        'STANDARD' => 'PH_STANDARD_TAX_INVOICE',
        'PURCHASE' => 'PH_PURCHASE_EINVOICE',
    ];
}

function yovel_admin_compliance_protected_invoice_fields(): array
{
    return [
        'seller_registered_name' => 'Seller registered name',
        'seller_tin' => 'Seller TIN',
        'seller_branch_code' => 'Seller branch code',
        'seller_rdo_code' => 'Seller RDO code',
        'invoice_number' => 'Invoice number',
        'invoice_serial' => 'Invoice serial',
        'issue_date' => 'Issue date',
        'buyer_registered_name' => 'Buyer registered name',
        'buyer_tin' => 'Buyer TIN',
        'line_description' => 'Line description',
        'line_quantity' => 'Line quantity',
        'line_amount' => 'Line amount',
        'vat_class' => 'VAT classification',
        'vat_rate' => 'VAT rate',
        'vat_base' => 'VAT base',
        'vat_amount' => 'VAT amount',
        'discount_amount' => 'Discount amount',
        'subtotal_amount' => 'Subtotal amount',
        'total_amount' => 'Total amount',
        'currency_code' => 'Currency',
        'original_invoice_reference' => 'Original invoice reference',
        'amendment_reference' => 'Amendment reference',
        'finance_document_key' => 'Finance source key',
        'finance_document_sha256' => 'Finance source checksum',
    ];
}

function yovel_admin_compliance_template_schema(string $profileType, mixed $fields = null): array
{
    $profileType = yovel_admin_code($profileType);
    $codes = yovel_admin_compliance_invoice_profile_codes();
    if (!isset($codes[$profileType])) {
        throw new InvalidArgumentException('Compliance invoice profile type is not allow-listed.');
    }
    $protected = yovel_admin_compliance_protected_invoice_fields();
    if ($fields === null || $fields === '' || $fields === []) {
        $fields = [];
        foreach ($protected as $fieldKey => $label) {
            $fields[] = ['field_key' => $fieldKey, 'label' => $label, 'protected' => true, 'required' => true];
        }
    } elseif (is_string($fields)) {
        $fields = json_decode($fields, true, 512, JSON_THROW_ON_ERROR);
    }
    if (!is_array($fields) || !array_is_list($fields) || count($fields) > 80) {
        throw new InvalidArgumentException('Compliance profile fields are invalid.');
    }
    $normalized = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            throw new InvalidArgumentException('Compliance profile fields are invalid.');
        }
        $fieldKey = strtolower(trim((string) ($field['field_key'] ?? '')));
        $label = substr(trim((string) ($field['label'] ?? '')), 0, 180);
        if (preg_match('/^[a-z][a-z0-9_]{1,79}$/', $fieldKey) !== 1 || $label === '' || isset($normalized[$fieldKey])) {
            throw new InvalidArgumentException('Compliance profile field identities are invalid.');
        }
        $isProtected = isset($protected[$fieldKey]);
        if ($isProtected && !hash_equals($protected[$fieldKey], $label)) {
            throw new InvalidArgumentException('Compliance protected invoice fields cannot be renamed.');
        }
        $normalized[$fieldKey] = [
            'field_key' => $fieldKey,
            'label' => $label,
            'protected' => $isProtected,
            'required' => $isProtected || filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }
    foreach (array_keys($protected) as $fieldKey) {
        if (!isset($normalized[$fieldKey])) {
            throw new InvalidArgumentException('Compliance protected invoice fields cannot be removed.');
        }
    }
    return [
        'schema_version' => 'PH_EINVOICE_PROFILE_V1',
        'profile_type' => $profileType,
        'template_code' => $codes[$profileType],
        'fields' => array_values($normalized),
    ];
}

function yovel_admin_compliance_template_version(array $company, string $versionKey, bool $lock = false): ?array
{
    yovel_admin_compliance_schema();
    [, $companyHash] = yovel_admin_compliance_read_scope($company);
    if (!yovel_admin_is_uuid($versionKey)) {
        return null;
    }
    $sql = "SELECT version_record.*, template_record.template_code, template_record.template_name,
                   template_record.document_type, template_record.template_status, template_record.current_version_key
              FROM project_company_compliance_template_version version_record
              INNER JOIN project_company_compliance_template template_record
                ON template_record.company_key_hash = version_record.company_key_hash
               AND template_record.template_key = version_record.template_key
             WHERE version_record.company_key_hash = ? AND version_record.template_version_key = ?" . ($lock ? ' FOR UPDATE' : '');
    $row = bx_db()->GetRow($sql, [$companyHash, $versionKey]);
    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_compliance_templates(array $company): array
{
    yovel_admin_compliance_schema();
    [, $companyHash] = yovel_admin_compliance_read_scope($company);
    return bx_db()->GetAll(
        "SELECT template_record.*, version_record.template_version_key, version_record.version_number,
                version_record.version_status, version_record.effective_from, version_record.effective_to,
                version_record.schema_sha256, version_record.schema_json
           FROM project_company_compliance_template template_record
           LEFT JOIN project_company_compliance_template_version version_record
             ON version_record.company_key_hash = template_record.company_key_hash
            AND version_record.template_key = template_record.template_key
          WHERE template_record.company_key_hash = ?
          ORDER BY template_record.template_code, version_record.version_number DESC",
        [$companyHash]
    ) ?: [];
}

function yovel_admin_compliance_save_template(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input,
    ?callable $checkpoint = null
): array {
    yovel_admin_compliance_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    $profileType = yovel_admin_code((string) ($input['profile_type'] ?? ''));
    $codes = yovel_admin_compliance_invoice_profile_codes();
    $templateCode = yovel_admin_code((string) ($input['template_code'] ?? ($codes[$profileType] ?? '')));
    if (!isset($codes[$profileType]) || !hash_equals($codes[$profileType], $templateCode)) {
        throw new InvalidArgumentException('Compliance profile code must match its protected profile type.');
    }
    $templateName = substr(trim((string) ($input['template_name'] ?? '')), 0, 180);
    $versionNumber = (int) ($input['version_number'] ?? 0);
    $effectiveFrom = yovel_admin_compliance_date($input['effective_from'] ?? '', 'Effective from');
    $effectiveTo = yovel_admin_compliance_date($input['effective_to'] ?? '', 'Effective to', true);
    if ($templateName === '' || $versionNumber < 1 || $versionNumber > 100000 || ($effectiveTo !== null && $effectiveTo < $effectiveFrom)) {
        throw new InvalidArgumentException('Compliance profile name, version, or effective period is invalid.');
    }
    $schema = yovel_admin_compliance_template_schema($profileType, $input['fields'] ?? null);
    $schemaJson = yovel_admin_compliance_json($schema);
    $schemaSha = yovel_admin_compliance_checksum($schema);
    $requestedTemplateKey = trim((string) ($input['template_key'] ?? ''));
    $requestedVersionKey = trim((string) ($input['template_version_key'] ?? ''));

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Compliance transaction could not start.');
    }
    try {
        $template = $requestedTemplateKey !== ''
            ? $db->GetRow('SELECT * FROM project_company_compliance_template WHERE company_key_hash = ? AND template_key = ? FOR UPDATE', [$companyHash, $requestedTemplateKey])
            : $db->GetRow('SELECT * FROM project_company_compliance_template WHERE company_key_hash = ? AND template_code = ? FOR UPDATE', [$companyHash, $templateCode]);
        $template = is_array($template) ? $template : [];
        if ($requestedTemplateKey !== '' && ($template === [] || !yovel_admin_is_uuid($requestedTemplateKey))) {
            throw new InvalidArgumentException('Compliance profile was not found for this company.');
        }
        if ($template !== [] && ((string) $template['template_code'] !== $templateCode || (string) $template['document_type'] !== $profileType)) {
            throw new InvalidArgumentException('Compliance profile identity is immutable.');
        }
        $templateKey = $template !== [] ? (string) $template['template_key'] : bx_uuid();
        if ($template === []) {
            yovel_admin_db_execute($db,
                "INSERT INTO project_company_compliance_template
                 (template_key, company_key, company_key_hash, template_code, template_name, document_type, template_status, created_by_admin_key, updated_by_admin_key)
                 VALUES (?, ?, ?, ?, ?, ?, 'DRAFT', ?, ?)",
                [$templateKey, $companyKey, $companyHash, $templateCode, $templateName, $profileType, $adminKey, $adminKey], 'Compliance profile insert');
        }
        $version = $requestedVersionKey !== ''
            ? $db->GetRow('SELECT * FROM project_company_compliance_template_version WHERE company_key_hash = ? AND template_version_key = ? FOR UPDATE', [$companyHash, $requestedVersionKey])
            : $db->GetRow('SELECT * FROM project_company_compliance_template_version WHERE company_key_hash = ? AND template_key = ? AND version_number = ? FOR UPDATE', [$companyHash, $templateKey, $versionNumber]);
        $version = is_array($version) ? $version : [];
        if ($requestedVersionKey !== '' && ($version === [] || (string) $version['template_key'] !== $templateKey)) {
            throw new InvalidArgumentException('Compliance profile version was not found for this company.');
        }
        if ($version !== [] && (string) $version['version_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Published Compliance profile versions are immutable.');
        }
        $versionKey = $version !== [] ? (string) $version['template_version_key'] : bx_uuid();
        if ($version === []) {
            yovel_admin_db_execute($db,
                "INSERT INTO project_company_compliance_template_version
                 (template_version_key, company_key, company_key_hash, template_key, version_number, version_status, effective_from, effective_to, schema_json, schema_sha256, created_by_admin_key)
                 VALUES (?, ?, ?, ?, ?, 'DRAFT', ?, ?, ?, ?, ?)",
                [$versionKey, $companyKey, $companyHash, $templateKey, $versionNumber, $effectiveFrom, $effectiveTo, $schemaJson, $schemaSha, $adminKey], 'Compliance profile version insert');
        } else {
            yovel_admin_db_execute($db,
                'UPDATE project_company_compliance_template_version SET effective_from = ?, effective_to = ?, schema_json = ?, schema_sha256 = ? WHERE company_key_hash = ? AND template_version_key = ?',
                [$effectiveFrom, $effectiveTo, $schemaJson, $schemaSha, $companyHash, $versionKey], 'Compliance Draft profile update');
        }
        yovel_admin_db_execute($db,
            'UPDATE project_company_compliance_template SET template_name = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND template_key = ?',
            [$templateName, $adminKey, $companyHash, $templateKey], 'Compliance profile parent update');
        if ($checkpoint) {
            $checkpoint('after_version');
        }
        $saved = yovel_admin_compliance_template_version($company, $versionKey, true);
        if (!$saved || (string) $saved['template_key'] !== $templateKey || (int) $saved['version_number'] !== $versionNumber
            || (string) $saved['schema_json'] !== $schemaJson || (string) $saved['schema_sha256'] !== $schemaSha) {
            throw new RuntimeException('Compliance profile read-back verification failed.');
        }
        bx_audit($version === [] ? 'CREATE' : 'UPDATE', 'project_company_compliance_template_version', $versionKey,
            ['company_key_hash' => $companyHash, 'template_key' => $templateKey, 'schema_sha256' => $schemaSha, 'admin_key' => $adminKey],
            'Company administrator saved a Compliance tax-document profile Draft.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Compliance transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_compliance_publish_template(
    ADOConnection $db,
    array $company,
    array $admin,
    string $versionKey,
    string $comments = '',
    ?callable $checkpoint = null
): array {
    yovel_admin_compliance_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    if (!yovel_admin_is_uuid($versionKey) || $db->BeginTrans() === false) {
        throw new RuntimeException('Compliance profile publish could not start.');
    }
    try {
        $version = yovel_admin_compliance_template_version($company, $versionKey, true);
        if (!$version) {
            throw new InvalidArgumentException('Compliance profile version was not found for this company.');
        }
        if ((string) $version['version_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Only a Draft Compliance profile may be published.');
        }
        if (hash_equals((string) $version['created_by_admin_key'], $adminKey)) {
            throw new InvalidArgumentException('Compliance profile publishing requires separation of duties from the creator.');
        }
        $schema = json_decode((string) $version['schema_json'], true, 512, JSON_THROW_ON_ERROR);
        if (yovel_admin_compliance_checksum($schema) !== (string) $version['schema_sha256']) {
            throw new RuntimeException('Compliance profile checksum verification failed.');
        }
        yovel_admin_db_execute($db,
            "UPDATE project_company_compliance_template_version SET version_status = 'SUPERSEDED'
              WHERE company_key_hash = ? AND template_key = ? AND version_status = 'APPROVED'",
            [$companyHash, (string) $version['template_key']], 'Compliance profile supersede previous');
        yovel_admin_db_execute($db,
            "UPDATE project_company_compliance_template_version
                SET version_status = 'APPROVED', approved_by_admin_key = ?, approved_at = CURRENT_TIMESTAMP
              WHERE company_key_hash = ? AND template_version_key = ?",
            [$adminKey, $companyHash, $versionKey], 'Compliance profile publish');
        yovel_admin_db_execute($db,
            "UPDATE project_company_compliance_template SET template_status = 'PUBLISHED', current_version_key = ?, updated_by_admin_key = ?
              WHERE company_key_hash = ? AND template_key = ?",
            [$versionKey, $adminKey, $companyHash, (string) $version['template_key']], 'Compliance profile parent publish');
        $approvalKey = bx_uuid();
        yovel_admin_db_execute($db,
            "INSERT INTO project_company_compliance_approval
             (approval_key, company_key, company_key_hash, record_type, record_key, approval_action, decision_status, record_sha256, comments, requested_by_admin_key, decided_by_admin_key, decided_at)
             VALUES (?, ?, ?, 'TEMPLATE_VERSION', ?, 'PUBLISH', 'APPROVED', ?, ?, ?, ?, CURRENT_TIMESTAMP)",
            [$approvalKey, $companyKey, $companyHash, $versionKey, (string) $version['schema_sha256'], substr(trim($comments), 0, 1000), (string) $version['created_by_admin_key'], $adminKey], 'Compliance profile approval insert');
        if ($checkpoint) {
            $checkpoint('after_publish');
        }
        $saved = yovel_admin_compliance_template_version($company, $versionKey, true);
        if (!$saved || (string) $saved['version_status'] !== 'APPROVED' || (string) $saved['schema_json'] !== (string) $version['schema_json']
            || (string) $saved['schema_sha256'] !== (string) $version['schema_sha256'] || (string) $saved['approved_by_admin_key'] !== $adminKey) {
            throw new RuntimeException('Compliance profile publish read-back verification failed.');
        }
        bx_audit('PUBLISH', 'project_company_compliance_template_version', $versionKey,
            ['company_key_hash' => $companyHash, 'schema_sha256' => (string) $version['schema_sha256'], 'approval_key' => $approvalKey, 'admin_key' => $adminKey],
            'Company administrator published an immutable Compliance tax-document profile.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Compliance transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_compliance_archive_template(ADOConnection $db, array $company, array $admin, string $versionKey, string $comments = ''): array
{
    [$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Compliance transaction could not start.');
    }
    try {
        $version = yovel_admin_compliance_template_version($company, $versionKey, true);
        if (!$version || !in_array((string) $version['version_status'], ['DRAFT', 'SUPERSEDED'], true)) {
            throw new InvalidArgumentException('Only a Draft or Superseded Compliance profile may be archived.');
        }
        yovel_admin_db_execute($db, "UPDATE project_company_compliance_template_version SET version_status = 'ARCHIVED' WHERE company_key_hash = ? AND template_version_key = ?", [$companyHash, $versionKey], 'Compliance profile archive');
        $approvedVersions = (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_compliance_template_version WHERE company_key_hash = ? AND template_key = ? AND version_status = 'APPROVED'",
            [$companyHash, (string) $version['template_key']]
        );
        if ($approvedVersions === 0) {
            yovel_admin_db_execute($db, "UPDATE project_company_compliance_template SET template_status = 'ARCHIVED', current_version_key = NULL, updated_by_admin_key = ? WHERE company_key_hash = ? AND template_key = ?", [$adminKey, $companyHash, (string) $version['template_key']], 'Compliance profile parent archive');
        }
        $saved = yovel_admin_compliance_template_version($company, $versionKey, true);
        if (!$saved || (string) $saved['version_status'] !== 'ARCHIVED' || (string) $saved['schema_sha256'] !== (string) $version['schema_sha256']) {
            throw new RuntimeException('Compliance profile archive read-back verification failed.');
        }
        bx_audit('ARCHIVE', 'project_company_compliance_template_version', $versionKey, ['company_key_hash' => $companyHash, 'comments' => substr(trim($comments), 0, 1000), 'admin_key' => $adminKey], 'Company administrator archived a Compliance profile version.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Compliance transaction could not commit.');
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_compliance_finance_invoice_snapshot(array $company, string $financeDocumentKey, ?callable $provider): array
{
    if (!is_callable($provider)) {
        throw new LogicException('UNAVAILABLE_DEPENDENCY: accounting-finance.invoice-snapshot.v1');
    }
    if (!yovel_admin_is_uuid($financeDocumentKey)) {
        throw new InvalidArgumentException('Finance invoice snapshot reference is invalid.');
    }
    $result = $provider($company, $financeDocumentKey);
    if (!is_array($result)) {
        throw new RuntimeException('Finance immutable invoice-snapshot validation failed.');
    }
    $record = is_array($result['record'] ?? null) ? $result['record'] : [];
    $snapshot = is_array($record['snapshot'] ?? null) ? $record['snapshot'] : [];
    $sourceSha = strtolower(trim((string) ($record['finance_document_sha256'] ?? '')));
    if (($result['ok'] ?? false) !== true || ($result['errors'] ?? null) !== []
        || !hash_equals((string) $company['company_key_hash'], strtolower(trim((string) ($result['company_key_hash'] ?? ''))))
        || !hash_equals($financeDocumentKey, trim((string) ($record['finance_document_key'] ?? '')))
        || strtoupper(trim((string) ($record['document_status'] ?? ''))) !== 'SUBMITTED'
        || preg_match('/^[a-f0-9]{64}$/', $sourceSha) !== 1 || $snapshot === []
        || !hash_equals($sourceSha, yovel_admin_compliance_checksum($snapshot))) {
        throw new RuntimeException('Finance immutable invoice-snapshot validation failed.');
    }
    $documentType = yovel_admin_code((string) ($snapshot['document_type'] ?? ''));
    $invoiceNumber = substr(trim((string) ($snapshot['invoice_number'] ?? '')), 0, 120);
    $issueDate = yovel_admin_compliance_date($snapshot['issue_date'] ?? '', 'Finance invoice issue date');
    if (!in_array($documentType, ['SALES_INVOICE', 'PURCHASE_INVOICE'], true) || $invoiceNumber === '') {
        throw new RuntimeException('Finance immutable invoice-snapshot content is invalid.');
    }
    return ['finance_document_key' => $financeDocumentKey, 'finance_document_sha256' => $sourceSha, 'document_status' => 'SUBMITTED', 'snapshot' => yovel_admin_compliance_canonical_value($snapshot), 'document_type' => $documentType, 'invoice_number' => $invoiceNumber, 'issue_date' => $issueDate];
}

function yovel_admin_compliance_render_invoice_snapshot(array $company, string $financeDocumentKey, ?callable $provider = null): array
{
    yovel_admin_compliance_schema();
    [, $companyHash] = yovel_admin_compliance_read_scope($company);
    $source = yovel_admin_compliance_finance_invoice_snapshot($company, $financeDocumentKey, $provider);
    $rule = yovel_admin_compliance_effective_rule_set($company, 'VAT_SETTINGS', (string) $source['issue_date']);
    if (!$rule) {
        throw new RuntimeException('No approved effective Philippine VAT rule is available for this invoice.');
    }
    $ruleSnapshot = json_decode((string) $rule['rule_json'], true, 512, JSON_THROW_ON_ERROR);
    $profileKeys = array_values(array_filter(array_map('strval', (array) ($ruleSnapshot['policy']['invoice_profile_keys'] ?? []))));
    $requiredProfile = (string) $source['document_type'] === 'PURCHASE_INVOICE' ? 'PH_PURCHASE_EINVOICE' : ($profileKeys[0] ?? '');
    if ($requiredProfile === '' || !in_array($requiredProfile, yovel_admin_compliance_invoice_profile_codes(), true)
        || ((string) $source['document_type'] === 'PURCHASE_INVOICE' && !in_array($requiredProfile, $profileKeys, true))) {
        throw new RuntimeException('Approved Compliance rule does not select an allowed invoice profile.');
    }
    $template = bx_db()->GetRow(
        "SELECT version_record.*, template_record.template_code, template_record.document_type
           FROM project_company_compliance_template template_record
           INNER JOIN project_company_compliance_template_version version_record
             ON version_record.company_key_hash = template_record.company_key_hash AND version_record.template_key = template_record.template_key
          WHERE template_record.company_key_hash = ? AND template_record.template_code = ?
            AND version_record.version_status IN ('APPROVED','SUPERSEDED')
            AND version_record.effective_from <= ? AND (version_record.effective_to IS NULL OR version_record.effective_to >= ?)
          ORDER BY version_record.effective_from DESC, version_record.version_number DESC LIMIT 1",
        [$companyHash, $requiredProfile, (string) $source['issue_date'], (string) $source['issue_date']]
    );
    if (!is_array($template) || $template === []) {
        throw new RuntimeException('The approved rule-selected Compliance invoice profile is unavailable.');
    }
    $schema = json_decode((string) $template['schema_json'], true, 512, JSON_THROW_ON_ERROR);
    if (!hash_equals((string) $template['schema_sha256'], yovel_admin_compliance_checksum($schema))) {
        throw new RuntimeException('Compliance invoice profile checksum verification failed.');
    }
    $payload = [
        'schema_version' => (string) ($schema['schema_version'] ?? ''),
        'profile_type' => (string) ($schema['profile_type'] ?? ''),
        'source' => ['finance_document_key' => $source['finance_document_key'], 'finance_document_sha256' => $source['finance_document_sha256'], 'document_status' => 'SUBMITTED'],
        'governance' => ['rule_set_key' => (string) $rule['rule_set_key'], 'rule_sha256' => (string) $rule['rule_sha256'], 'template_version_key' => (string) $template['template_version_key'], 'schema_sha256' => (string) $template['schema_sha256']],
        'invoice' => $source['snapshot'],
    ];
    return [
        'finance_document_key' => $source['finance_document_key'], 'finance_document_sha256' => $source['finance_document_sha256'],
        'document_type' => $source['document_type'], 'invoice_number' => $source['invoice_number'], 'issue_date' => $source['issue_date'],
        'schema_version' => (string) $payload['schema_version'], 'rule_set_key' => (string) $rule['rule_set_key'],
        'template_version_key' => (string) $template['template_version_key'], 'payload' => $payload,
        'payload_json' => yovel_admin_compliance_json($payload), 'payload_sha256' => yovel_admin_compliance_checksum($payload),
    ];
}

function yovel_admin_compliance_einvoice_record(array $company, string $recordKey, bool $lock = false): ?array
{
    [, $companyHash] = yovel_admin_compliance_read_scope($company);
    if (!yovel_admin_is_uuid($recordKey)) return null;
    $row = bx_db()->GetRow('SELECT * FROM project_company_compliance_einvoice_record WHERE company_key_hash = ? AND einvoice_record_key = ?' . ($lock ? ' FOR UPDATE' : ''), [$companyHash, $recordKey]);
    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_compliance_register_einvoice(ADOConnection $db, array $company, array $admin, string $financeDocumentKey, ?callable $provider = null, ?string $correctionOfRecordKey = null, ?callable $checkpoint = null): array
{
    yovel_admin_compliance_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    $render = yovel_admin_compliance_render_invoice_snapshot($company, $financeDocumentKey, $provider);
    $correctionOfRecordKey = trim((string) $correctionOfRecordKey) ?: null;
    if ($db->BeginTrans() === false) throw new RuntimeException('Compliance transaction could not start.');
    try {
        if ($correctionOfRecordKey !== null) {
            if (!yovel_admin_compliance_einvoice_record($company, $correctionOfRecordKey, true)) {
                throw new InvalidArgumentException('Correction source record was not found for this company.');
            }
            $invoice = is_array($render['payload']['invoice'] ?? null) ? $render['payload']['invoice'] : [];
            if (trim((string) ($invoice['original_invoice_reference'] ?? '')) === ''
                || trim((string) ($invoice['amendment_reference'] ?? '')) === '') {
                throw new InvalidArgumentException('Correction snapshots require original and amendment references.');
            }
        }
        $existing = $db->GetRow(
            'SELECT einvoice_record_key FROM project_company_compliance_einvoice_record WHERE company_key_hash = ? AND finance_document_key = ? AND schema_version = ? AND payload_sha256 = ? FOR UPDATE',
            [$companyHash, $financeDocumentKey, $render['schema_version'], $render['payload_sha256']]
        );
        if (is_array($existing) && $existing !== []) {
            $saved = yovel_admin_compliance_einvoice_record($company, (string) $existing['einvoice_record_key'], true);
            $db->RollbackTrans();
            return $saved ?? [];
        }
        $rule = yovel_admin_compliance_rule_set($company, (string) $render['rule_set_key']);
        $rulePayload = json_decode((string) ($rule['rule_json'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
        $years = max(0, (int) ($rulePayload['policy']['retention_years'] ?? 0));
        $retentionUntil = $years > 0 ? (new DateTimeImmutable((string) $render['issue_date']))->modify('+' . $years . ' years')->format('Y-m-d') : null;
        $recordKey = bx_uuid();
        yovel_admin_db_execute($db,
            "INSERT INTO project_company_compliance_einvoice_record
             (einvoice_record_key, company_key, company_key_hash, finance_document_key, finance_document_sha256, document_type, invoice_number, schema_version, rule_set_key, template_version_key, payload_json, payload_sha256, transmission_status, correction_of_record_key, retention_until, created_by_admin_key, updated_by_admin_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'VALIDATED', ?, ?, ?, ?)",
            [$recordKey, $companyKey, $companyHash, $financeDocumentKey, $render['finance_document_sha256'], $render['document_type'], $render['invoice_number'], $render['schema_version'], $render['rule_set_key'], $render['template_version_key'], $render['payload_json'], $render['payload_sha256'], $correctionOfRecordKey, $retentionUntil, $adminKey, $adminKey], 'Compliance e-invoice register insert');
        if ($checkpoint) $checkpoint('after_insert');
        $saved = yovel_admin_compliance_einvoice_record($company, $recordKey, true);
        if (!$saved || (string) $saved['payload_json'] !== $render['payload_json'] || (string) $saved['payload_sha256'] !== $render['payload_sha256']
            || (string) $saved['finance_document_sha256'] !== $render['finance_document_sha256'] || (string) $saved['transmission_status'] !== 'VALIDATED'
            || (($saved['correction_of_record_key'] ?? null) ?: null) !== $correctionOfRecordKey) {
            throw new RuntimeException('Compliance e-invoice register read-back verification failed.');
        }
        bx_audit('CREATE', 'project_company_compliance_einvoice_record', $recordKey, ['company_key_hash' => $companyHash, 'finance_document_key' => $financeDocumentKey, 'payload_sha256' => $render['payload_sha256'], 'admin_key' => $adminKey], 'Company administrator registered a validated immutable e-invoice snapshot.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Compliance transaction could not commit.');
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_compliance_update_transmission(ADOConnection $db, array $company, array $admin, string $recordKey, string $toStatus, string $acknowledgementReference = '', ?callable $checkpoint = null): array
{
    [, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    $toStatus = yovel_admin_code($toStatus);
    $transitions = ['DRAFT' => ['VALIDATED', 'CANCELLED'], 'VALIDATED' => ['QUEUED', 'REJECTED', 'CANCELLED'], 'QUEUED' => ['PROCESSING', 'REJECTED', 'CANCELLED'], 'PROCESSING' => ['ACKNOWLEDGED', 'REJECTED', 'CANCELLED'], 'REJECTED' => ['QUEUED', 'CANCELLED'], 'ACKNOWLEDGED' => [], 'CANCELLED' => []];
    $acknowledgementReference = substr(trim($acknowledgementReference), 0, 180);
    if ($db->BeginTrans() === false) throw new RuntimeException('Compliance transaction could not start.');
    try {
        $record = yovel_admin_compliance_einvoice_record($company, $recordKey, true);
        if (!$record) throw new InvalidArgumentException('Compliance e-invoice record was not found for this company.');
        $from = (string) $record['transmission_status'];
        if (!isset($transitions[$from]) || !in_array($toStatus, $transitions[$from], true)) throw new InvalidArgumentException('Compliance transmission transition is not allowed.');
        if ($toStatus === 'ACKNOWLEDGED' && $acknowledgementReference === '') throw new InvalidArgumentException('Acknowledgement reference is required.');
        $ack = $toStatus === 'ACKNOWLEDGED' ? $acknowledgementReference : (($record['acknowledgement_reference'] ?? null) ?: null);
        yovel_admin_db_execute($db, 'UPDATE project_company_compliance_einvoice_record SET transmission_status = ?, acknowledgement_reference = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND einvoice_record_key = ?', [$toStatus, $ack, $adminKey, $companyHash, $recordKey], 'Compliance transmission update');
        if ($checkpoint) $checkpoint('after_update');
        $saved = yovel_admin_compliance_einvoice_record($company, $recordKey, true);
        if (!$saved || (string) $saved['transmission_status'] !== $toStatus || (($saved['acknowledgement_reference'] ?? null) ?: null) !== $ack || (string) $saved['payload_sha256'] !== (string) $record['payload_sha256']) throw new RuntimeException('Compliance transmission read-back verification failed.');
        bx_audit('UPDATE', 'project_company_compliance_einvoice_record', $recordKey, ['company_key_hash' => $companyHash, 'from_status' => $from, 'to_status' => $toStatus, 'acknowledgement_reference' => $ack, 'admin_key' => $adminKey], 'Company administrator updated an e-invoice transmission state.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Compliance transaction could not commit.');
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_compliance_einvoice_register(array $company, array $filters = [], ?callable $provider = null): array
{
    [, $companyHash] = yovel_admin_compliance_read_scope($company);
    if (!is_callable($provider)) return ['availability' => 'UNAVAILABLE_DEPENDENCY', 'dependency' => 'accounting-finance.invoice-snapshot.v1', 'rows' => [], 'errors' => ['Finance immutable invoice snapshots are unavailable.']];
    $where = ['company_key_hash = ?'];
    $params = [$companyHash];
    $status = yovel_admin_code((string) ($filters['status'] ?? ''));
    if ($status !== '') {
        if (!in_array($status, ['DRAFT', 'VALIDATED', 'QUEUED', 'PROCESSING', 'ACKNOWLEDGED', 'REJECTED', 'CANCELLED'], true)) throw new InvalidArgumentException('Compliance register status filter is invalid.');
        $where[] = 'transmission_status = ?'; $params[] = $status;
    }
    foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
        if (trim((string) ($filters[$key] ?? '')) !== '') {
            $where[] = 'DATE(created_at) ' . $operator . ' ?';
            $params[] = yovel_admin_compliance_date($filters[$key], str_replace('_', ' ', ucfirst($key)));
        }
    }
    $rows = bx_db()->GetAll('SELECT * FROM project_company_compliance_einvoice_record WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, x_id DESC LIMIT 200', $params) ?: [];
    $verified = [];
    $errors = [];
    foreach ($rows as $row) {
        try {
            $source = yovel_admin_compliance_finance_invoice_snapshot($company, (string) $row['finance_document_key'], $provider);
            if (hash_equals((string) $row['finance_document_sha256'], (string) $source['finance_document_sha256'])) $verified[] = $row;
        } catch (Throwable $error) {
            $errors[] = ['einvoice_record_key' => (string) $row['einvoice_record_key'], 'code' => 'SOURCE_UNAVAILABLE'];
        }
    }
    return ['availability' => 'AVAILABLE', 'dependency' => 'accounting-finance.invoice-snapshot.v1', 'rows' => $verified, 'errors' => $errors];
}
