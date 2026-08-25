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

function buying_test_temporary_company(ADOConnection $db, string $prefix): array
{
    $suffix = strtolower(substr(str_replace('-', '', bx_uuid()), 0, 12));
    $companyKey = substr(hash('sha256', $prefix . ':' . $suffix), 0, 20);
    $companyHash = hash('sha256', $companyKey);
    $companyCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $prefix), 0, 12)) . strtoupper(substr($suffix, 0, 8));
    $companySlug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $prefix . '-' . $suffix), '-'));
    $companyName = $prefix . ' ' . strtoupper(substr($suffix, 0, 6));
    $adminKey = bx_uuid();

    buying_test_execute(
        $db,
        "INSERT INTO project_company (
            company_key, company_key_hash, company_code, company_slug, company_name, company_status
        ) VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
        [$companyKey, $companyHash, $companyCode, $companySlug, $companyName],
        'Temporary Buying company creation'
    );
    buying_test_execute(
        $db,
        "INSERT INTO project_company_admin (
            admin_key, company_key, company_key_hash, admin_login, admin_password_hash,
            admin_name, admin_email, admin_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
        [
            $adminKey,
            $companyKey,
            $companyHash,
            'buying_' . $suffix,
            password_hash('Buying-Test-Only-12345', PASSWORD_DEFAULT),
            $companyName . ' Administrator',
            'buying-' . $suffix . '@example.test',
        ],
        'Temporary Buying administrator creation'
    );

    return [
        'company' => [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'company_name' => $companyName,
        ],
        'admin' => [
            'admin_key' => $adminKey,
            'admin_status' => 'ACTIVE',
        ],
    ];
}

function buying_test_cleanup_temporary_company(ADOConnection $db, array $fixture): void
{
    $company = $fixture['company'];
    $companyHash = (string) $company['company_key_hash'];
    $supplierKeys = $db->GetCol(
        'SELECT supplier_key FROM project_company_buying_supplier WHERE company_key_hash = ?',
        [$companyHash]
    );
    foreach (is_array($supplierKeys) ? $supplierKeys : [] as $supplierKey) {
        buying_test_execute(
            $db,
            "DELETE FROM builder_audit_log
            WHERE record_key = ? AND module IN (
                'project_company_buying_supplier',
                'project_company_buying_supplier_customer_number',
                'project_company_buying_supplier_company'
            )",
            [(string) $supplierKey],
            'Temporary Buying supplier audit cleanup'
        );
    }
    buying_test_execute($db, 'DELETE FROM project_company_buying_supplier_customer_number WHERE company_key_hash = ?', [$companyHash], 'Temporary Buying customer-number cleanup');
    buying_test_execute($db, 'DELETE FROM project_company_buying_supplier_company WHERE company_key_hash = ?', [$companyHash], 'Temporary Buying supplier-company cleanup');
    buying_test_execute($db, 'DELETE FROM project_company_buying_supplier WHERE company_key_hash = ?', [$companyHash], 'Temporary Buying supplier cleanup');
    buying_test_execute($db, "DELETE FROM builder_audit_log WHERE module = 'project_company_buying_setting' AND new_values LIKE ?", ['%' . $companyHash . '%'], 'Temporary Buying settings audit cleanup');
    buying_test_execute($db, "DELETE FROM builder_audit_log WHERE module = 'project_company_buying_number_series' AND new_values LIKE ?", ['%' . $companyHash . '%'], 'Temporary Buying series audit cleanup');
    buying_test_execute($db, 'DELETE FROM project_company_buying_setting WHERE company_key_hash = ?', [$companyHash], 'Temporary Buying settings cleanup');
    buying_test_execute($db, 'DELETE FROM project_company_buying_number_series WHERE company_key_hash = ?', [$companyHash], 'Temporary Buying series cleanup');
    buying_test_execute($db, 'DELETE FROM project_company_admin WHERE company_key_hash = ?', [$companyHash], 'Temporary Buying administrator cleanup');
    buying_test_execute($db, 'DELETE FROM project_company WHERE company_key_hash = ?', [$companyHash], 'Temporary Buying company cleanup');
}
