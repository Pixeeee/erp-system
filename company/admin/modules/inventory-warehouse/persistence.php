<?php
declare(strict_types=1);

function yovel_admin_inventory_execute(ADOConnection $db, string $sql, array $parameters, string $operation): void
{
    $result = $db->Execute($sql, $parameters);
    if ($result === false) {
        $databaseError = trim((string) $db->ErrorMsg());
        error_log($operation . ' failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
        throw new RuntimeException($operation . ' could not be completed.');
    }
}

function yovel_admin_inventory_scope(array $company, ?array $admin): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $adminKey = trim((string) ($admin['admin_key'] ?? ''));

    if ($companyKey === ''
        || preg_match('/^[a-f0-9]{64}$/', $companyKeyHash) !== 1
        || $adminKey === ''
        || (function_exists('yovel_admin_is_uuid') && !yovel_admin_is_uuid($adminKey))) {
        throw new RuntimeException('An authorized company administrator is required for Inventory/Warehouse.');
    }

    $authorized = bx_db()->GetRow(
        "SELECT company_record.company_key, company_record.company_key_hash, admin_record.admin_key
         FROM project_company company_record
         INNER JOIN project_company_admin admin_record
           ON admin_record.company_key_hash = company_record.company_key_hash
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
        || strtolower((string) ($authorized['company_key_hash'] ?? '')) !== $companyKeyHash
        || (string) ($authorized['admin_key'] ?? '') !== $adminKey) {
        throw new RuntimeException('An authorized company administrator is required for Inventory/Warehouse.');
    }

    return [$companyKey, $companyKeyHash, $adminKey];
}

function yovel_admin_inventory_in_transaction(callable $operation): mixed
{
    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Inventory transaction could not start.');
    }

    try {
        $result = $operation($db);
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Inventory transaction could not commit.');
        }
        return $result;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_inventory_assert_readback(array $expected, array $actual, array $fields, string $label): void
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

function yovel_admin_inventory_decimal(string $value, int $scale = 9): string
{
    $value = trim($value);
    if ($scale < 0 || $scale > 9 || preg_match('/^[+-]?\d+(?:\.\d+)?$/', $value) !== 1) {
        throw new InvalidArgumentException('Inventory values must be plain decimal numbers with up to nine decimal places.');
    }
    $whole = ltrim(explode('.', ltrim($value, '+-'), 2)[0], '0');
    if (strlen($whole) > 15) {
        throw new InvalidArgumentException('Inventory value is outside the supported range.');
    }

    $normalized = bcadd($value, '0', $scale);
    if (bccomp($normalized, '0', $scale) === 0) {
        return $scale > 0 ? '0.' . str_repeat('0', $scale) : '0';
    }
    return $normalized;
}

