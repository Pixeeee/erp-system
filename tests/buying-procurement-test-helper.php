<?php
declare(strict_types=1);

function buying_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function buying_test_expect_error(callable $callback, string $needle): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        buying_test_assert(
            str_contains(strtolower($error->getMessage()), strtolower($needle)),
            'Unexpected Buying / Procurement error: ' . $error->getMessage()
        );
        return;
    }

    throw new RuntimeException('Expected Buying / Procurement error containing: ' . $needle);
}

function buying_test_execute(ADOConnection $db, string $sql, array $params, string $operation): void
{
    $result = $db->Execute($sql, $params);
    if ($result !== false) {
        return;
    }

    $databaseError = trim((string) $db->ErrorMsg());
    throw new RuntimeException($operation . ' failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
}

function buying_test_company_admin_fixture(ADOConnection $db): array
{
    $row = $db->GetRow(
        "SELECT
            company_record.company_key,
            company_record.company_key_hash,
            company_record.company_name,
            admin_record.admin_key,
            admin_record.admin_status
        FROM project_company company_record
        INNER JOIN project_company_admin admin_record
            ON admin_record.company_key_hash = company_record.company_key_hash
           AND admin_record.admin_status = 'ACTIVE'
        WHERE company_record.company_status = 'ACTIVE'
        ORDER BY company_record.x_id, admin_record.x_id
        LIMIT 1"
    );
    buying_test_assert(is_array($row) && $row !== [], 'An active company administrator fixture is required.');

    return [
        'company' => [
            'company_key' => (string) $row['company_key'],
            'company_key_hash' => (string) $row['company_key_hash'],
            'company_name' => (string) $row['company_name'],
        ],
        'admin' => [
            'admin_key' => (string) $row['admin_key'],
            'admin_status' => (string) $row['admin_status'],
        ],
    ];
}

function buying_test_secondary_company(ADOConnection $db, string $excludedCompanyHash): array
{
    $row = $db->GetRow(
        "SELECT company_key, company_key_hash, company_name
        FROM project_company
        WHERE company_status <> 'DELETED' AND company_key_hash <> ?
        ORDER BY x_id
        LIMIT 1",
        [$excludedCompanyHash]
    );
    buying_test_assert(is_array($row) && $row !== [], 'A second company fixture is required for isolation checks.');

    return [
        'company_key' => (string) $row['company_key'],
        'company_key_hash' => (string) $row['company_key_hash'],
        'company_name' => (string) $row['company_name'],
    ];
}
