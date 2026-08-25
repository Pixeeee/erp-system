<?php
declare(strict_types=1);

function yovel_admin_compliance_canonical_value(mixed $value): mixed
{
    if (!is_array($value)) {
        if (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException('Compliance checksum values must be finite.');
        }
        if (is_object($value) || is_resource($value)) {
            throw new InvalidArgumentException('Compliance checksum values must contain JSON-compatible scalars and arrays.');
        }
        return $value;
    }

    if (array_is_list($value)) {
        return array_map('yovel_admin_compliance_canonical_value', $value);
    }

    $canonical = [];
    $keys = array_map('strval', array_keys($value));
    sort($keys, SORT_STRING);
    foreach ($keys as $key) {
        $canonical[$key] = yovel_admin_compliance_canonical_value($value[$key]);
    }
    return $canonical;
}

function yovel_admin_compliance_checksum(array $payload): string
{
    $canonical = yovel_admin_compliance_canonical_value($payload);
    $json = json_encode(
        $canonical,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    );
    return hash('sha256', $json);
}

function yovel_admin_compliance_lifecycle_contracts(): array
{
    return [
        'version' => [
            'statuses' => ['DRAFT', 'APPROVED', 'SUPERSEDED', 'ARCHIVED'],
            'transitions' => [
                'DRAFT' => ['APPROVED', 'ARCHIVED'],
                'APPROVED' => ['SUPERSEDED', 'ARCHIVED'],
                'SUPERSEDED' => ['ARCHIVED'],
                'ARCHIVED' => [],
            ],
        ],
        'batch' => [
            'statuses' => ['UPLOADED', 'VALIDATED', 'REJECTED', 'APPROVED', 'PROJECTING', 'PROJECTED', 'FAILED'],
            'transitions' => [
                'UPLOADED' => ['VALIDATED', 'REJECTED', 'FAILED'],
                'VALIDATED' => ['APPROVED', 'REJECTED', 'FAILED'],
                'REJECTED' => [],
                'APPROVED' => ['PROJECTING', 'REJECTED'],
                'PROJECTING' => ['PROJECTED', 'FAILED'],
                'PROJECTED' => [],
                'FAILED' => ['VALIDATED', 'PROJECTING', 'REJECTED'],
            ],
        ],
        'certificate' => [
            'statuses' => ['DRAFT', 'ISSUED', 'CANCELLED'],
            'transitions' => [
                'DRAFT' => ['ISSUED', 'CANCELLED'],
                'ISSUED' => ['CANCELLED'],
                'CANCELLED' => [],
            ],
        ],
        'return' => [
            'statuses' => ['DRAFT', 'REVIEWED', 'APPROVED', 'SUPERSEDED'],
            'transitions' => [
                'DRAFT' => ['REVIEWED', 'SUPERSEDED'],
                'REVIEWED' => ['DRAFT', 'APPROVED', 'SUPERSEDED'],
                'APPROVED' => ['SUPERSEDED'],
                'SUPERSEDED' => [],
            ],
        ],
        'export' => [
            'statuses' => ['DRAFT', 'APPROVED', 'QUEUED', 'PROCESSING', 'SUCCEEDED', 'FAILED', 'CANCELLED'],
            'transitions' => [
                'DRAFT' => ['APPROVED', 'CANCELLED'],
                'APPROVED' => ['QUEUED', 'CANCELLED'],
                'QUEUED' => ['PROCESSING', 'FAILED', 'CANCELLED'],
                'PROCESSING' => ['SUCCEEDED', 'FAILED'],
                'SUCCEEDED' => [],
                'FAILED' => ['QUEUED', 'CANCELLED'],
                'CANCELLED' => [],
            ],
        ],
        'einvoice' => [
            'statuses' => ['DRAFT', 'VALIDATED', 'QUEUED', 'PROCESSING', 'ACKNOWLEDGED', 'REJECTED', 'CANCELLED'],
            'transitions' => [
                'DRAFT' => ['VALIDATED', 'CANCELLED'],
                'VALIDATED' => ['QUEUED', 'REJECTED', 'CANCELLED'],
                'QUEUED' => ['PROCESSING', 'REJECTED', 'CANCELLED'],
                'PROCESSING' => ['ACKNOWLEDGED', 'REJECTED', 'CANCELLED'],
                'ACKNOWLEDGED' => [],
                'REJECTED' => ['QUEUED', 'CANCELLED'],
                'CANCELLED' => [],
            ],
        ],
    ];
}

function yovel_admin_compliance_assert_transition(string $lifecycle, string $from, string $to): void
{
    $lifecycle = strtolower(trim($lifecycle));
    $contracts = yovel_admin_compliance_lifecycle_contracts();
    if (!isset($contracts[$lifecycle])) {
        throw new InvalidArgumentException('Unknown lifecycle contract.');
    }

    $from = strtoupper(trim($from));
    $to = strtoupper(trim($to));
    $transitions = $contracts[$lifecycle]['transitions'];
    if (!array_key_exists($from, $transitions) || !in_array($to, $transitions[$from], true)) {
        throw new InvalidArgumentException('Compliance lifecycle transition is not allowed.');
    }
}

function yovel_admin_compliance_scope(array $company, array $admin): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $adminKey = trim((string) ($admin['admin_key'] ?? ''));
    $adminStatus = strtoupper(trim((string) ($admin['admin_status'] ?? '')));

    if ($companyKey === ''
        || strlen($companyKey) > 1500
        || preg_match('/^[a-f0-9]{64}$/', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('Compliance company scope is invalid.');
    }
    if (!yovel_admin_is_uuid($adminKey) || $adminStatus !== 'ACTIVE') {
        throw new InvalidArgumentException('An active company administrator is required.');
    }
    $adminCompanyHash = strtolower(trim((string) ($admin['company_key_hash'] ?? '')));
    if ($adminCompanyHash !== '' && !hash_equals($companyKeyHash, $adminCompanyHash)) {
        throw new InvalidArgumentException('The company administrator does not belong to this company scope.');
    }

    $db = bx_db();
    $membershipExists = (int) $db->GetOne(
        "SELECT COUNT(*)
           FROM project_company company_record
           INNER JOIN project_company_admin admin_record
             ON admin_record.company_key = company_record.company_key
            AND admin_record.company_key_hash = company_record.company_key_hash
          WHERE company_record.company_key = ?
            AND company_record.company_key_hash = ?
            AND company_record.company_status = 'ACTIVE'
            AND admin_record.admin_key = ?
            AND admin_record.admin_status = 'ACTIVE'",
        [$companyKey, $companyKeyHash, $adminKey]
    );
    if ($membershipExists !== 1) {
        throw new InvalidArgumentException('An authorized company administrator is required.');
    }

    return [$companyKey, $companyKeyHash, $adminKey];
}
