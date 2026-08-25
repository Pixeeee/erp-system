<?php
declare(strict_types=1);

function yovel_admin_assets_execute(ADOConnection $db, string $sql, array $parameters, string $operation): void
{
    $result = $db->Execute($sql, $parameters);
    if ($result === false) {
        $databaseError = trim((string) $db->ErrorMsg());
        error_log($operation . ' failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
        throw new RuntimeException($operation . ' could not be completed.');
    }
}

function yovel_admin_assets_opaque_key(mixed $value, string $label, bool $required = true, int $maxLength = 1500): string
{
    $key = trim((string) $value);
    if ($key === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return '';
    }
    if (strlen($key) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
        throw new InvalidArgumentException($label . ' is outside the supported opaque-key boundary.');
    }
    return $key;
}

function yovel_admin_assets_text(mixed $value, string $label, int $maxLength, bool $required = false): string
{
    $text = trim((string) $value);
    if ($required && $text === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }
    if (strlen($text) > $maxLength) {
        throw new InvalidArgumentException($label . ' must be ' . $maxLength . ' characters or fewer.');
    }
    return $text;
}

function yovel_admin_assets_scope(array $company, ?array $admin): array
{
    $companyKey = yovel_admin_assets_opaque_key($company['company_key'] ?? '', 'Company key');
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $adminKey = yovel_admin_assets_opaque_key($admin['admin_key'] ?? '', 'Administrator key', true, 191);

    if (preg_match('/^[a-f0-9]{64}$/', $companyKeyHash) !== 1) {
        throw new RuntimeException('An authorized company administrator is required for Assets / Maintenance.');
    }

    $authorized = bx_db()->GetRow(
        "SELECT company_record.company_key, company_record.company_key_hash, admin_record.admin_key
         FROM project_company company_record
         INNER JOIN project_company_admin admin_record
           ON admin_record.company_key = company_record.company_key
          AND admin_record.company_key_hash = company_record.company_key_hash
          AND admin_record.admin_key = ?
          AND admin_record.admin_status = 'ACTIVE'
         WHERE company_record.company_key = ?
           AND company_record.company_key_hash = ?
           AND company_record.company_status = 'ACTIVE'
         LIMIT 1",
        [$adminKey, $companyKey, $companyKeyHash]
    );

    if (!is_array($authorized)
        || (string) ($authorized['company_key'] ?? '') !== $companyKey
        || !hash_equals($companyKeyHash, strtolower((string) ($authorized['company_key_hash'] ?? '')))
        || (string) ($authorized['admin_key'] ?? '') !== $adminKey) {
        throw new RuntimeException('An authorized company administrator is required for Assets / Maintenance.');
    }

    return [$companyKey, $companyKeyHash, $adminKey];
}

function yovel_admin_assets_in_transaction(callable $operation): mixed
{
    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Assets / Maintenance transaction could not start.');
    }

    try {
        $result = $operation($db);
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Assets / Maintenance transaction could not commit.');
        }
        return $result;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_assets_assert_readback(array $expected, array $actual, array $fields, string $label): void
{
    foreach ($fields as $field) {
        $field = (string) $field;
        if (!array_key_exists($field, $expected)
            || !array_key_exists($field, $actual)
            || (string) $expected[$field] !== (string) $actual[$field]) {
            throw new RuntimeException($label . ' read-back verification failed for ' . $field . '.');
        }
    }
}

function yovel_admin_assets_lock_company(ADOConnection $db, string $companyKey, string $companyKeyHash): void
{
    $locked = $db->GetRow(
        "SELECT company_key, company_key_hash
         FROM project_company
         WHERE company_key = ? AND company_key_hash = ? AND company_status = 'ACTIVE'
         FOR UPDATE",
        [$companyKey, $companyKeyHash]
    );
    if (!is_array($locked)
        || (string) ($locked['company_key'] ?? '') !== $companyKey
        || !hash_equals($companyKeyHash, strtolower((string) ($locked['company_key_hash'] ?? '')))) {
        throw new RuntimeException('Assets / Maintenance company scope changed before persistence.');
    }
}

function yovel_admin_assets_fault(string $point): void
{
    if (($GLOBALS['yovel_admin_assets_test_fault'] ?? null) === $point) {
        throw new RuntimeException('Assets / Maintenance forced transaction failure at ' . $point . '.');
    }
}

function yovel_admin_assets_write_audit(
    ADOConnection $db,
    array $scope,
    string $action,
    string $formKey,
    string $formVersionKey = '',
    string $submissionKey = '',
    array $details = []
): string {
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $auditKey = bx_uuid();
    $detailsJson = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    yovel_admin_assets_execute(
        $db,
        "INSERT INTO project_company_asset_form_audit (
            audit_key, company_key, company_key_hash, form_key, form_version_key,
            submission_key, audit_action, details_json, created_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$auditKey, $companyKey, $companyKeyHash, $formKey, $formVersionKey !== '' ? $formVersionKey : null, $submissionKey !== '' ? $submissionKey : null, $action, $detailsJson, $adminKey],
        'Assets Form Builder audit save'
    );
    bx_audit($action, 'project_company_asset_form', $formKey, [
        'company_key' => $companyKey,
        'company_key_hash' => $companyKeyHash,
        'form_version_key' => $formVersionKey,
        'submission_key' => $submissionKey,
        'admin_key' => $adminKey,
    ] + $details, 'Company administrator changed an Assets / Maintenance form record.');

    $saved = $db->GetRow(
        'SELECT * FROM project_company_asset_form_audit WHERE company_key_hash = ? AND audit_key = ? LIMIT 1',
        [$companyKeyHash, $auditKey]
    );
    yovel_admin_assets_assert_readback([
        'audit_key' => $auditKey,
        'company_key' => $companyKey,
        'company_key_hash' => $companyKeyHash,
        'form_key' => $formKey,
        'form_version_key' => $formVersionKey,
        'submission_key' => $submissionKey,
        'audit_action' => $action,
        'details_json' => $detailsJson,
        'created_by_admin_key' => $adminKey,
    ], is_array($saved) ? array_map(static fn ($value) => $value ?? '', $saved) : [], [
        'audit_key', 'company_key', 'company_key_hash', 'form_key', 'form_version_key',
        'submission_key', 'audit_action', 'details_json', 'created_by_admin_key',
    ], 'Assets Form Builder audit');

    return $auditKey;
}
