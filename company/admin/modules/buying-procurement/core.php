<?php
declare(strict_types=1);

function yovel_admin_buying_scope(array $company, array $admin): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $adminKey = trim((string) ($admin['admin_key'] ?? ''));

    if ($companyKey === '' || strlen($companyKey) > 1500 || preg_match('/^[0-9a-f]{64}$/', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('Buying / Procurement company scope is invalid.');
    }
    if (!yovel_admin_is_uuid($adminKey)) {
        throw new InvalidArgumentException('Buying / Procurement administrator scope is invalid.');
    }

    $authorized = bx_db()->GetRow(
        "SELECT admin_record.admin_key
        FROM project_company_admin admin_record
        INNER JOIN project_company company_record
            ON company_record.company_key_hash = admin_record.company_key_hash
           AND company_record.company_key = admin_record.company_key
           AND company_record.company_status = 'ACTIVE'
        WHERE admin_record.admin_key = ?
          AND admin_record.company_key_hash = ?
          AND admin_record.company_key = ?
          AND admin_record.admin_status = 'ACTIVE'
        LIMIT 1",
        [$adminKey, $companyKeyHash, $companyKey]
    );
    if (!is_array($authorized) || $authorized === []) {
        throw new RuntimeException('An authorized active company administrator is required for Buying / Procurement.');
    }

    return [$companyKey, $companyKeyHash, $adminKey];
}

function yovel_admin_buying_number(
    ADOConnection $db,
    array $company,
    array $admin,
    string $seriesCode,
    string $prefix
): string {
    yovel_admin_buying_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);

    $seriesCode = yovel_admin_code($seriesCode);
    $prefix = strtoupper(trim($prefix));
    if ($seriesCode === '' || strlen($seriesCode) > 80) {
        throw new InvalidArgumentException('Buying / Procurement series code is required and cannot exceed 80 characters.');
    }
    if ($prefix === '' || strlen($prefix) > 30 || preg_match('/^[A-Z0-9._\/-]+$/', $prefix) !== 1) {
        throw new InvalidArgumentException('Buying / Procurement number prefix is invalid.');
    }

    $fiscalYear = (int) date('Y');
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Buying / Procurement number transaction could not start.');
    }

    try {
        $existing = $db->GetRow(
            "SELECT * FROM project_company_buying_number_series
            WHERE company_key_hash = ? AND series_code = ? AND fiscal_year = ?
            FOR UPDATE",
            [$companyKeyHash, $seriesCode, $fiscalYear]
        );
        $seriesKey = is_array($existing) && $existing !== []
            ? (string) $existing['number_series_key']
            : bx_uuid();
        $padding = is_array($existing) && $existing !== [] ? (int) $existing['padding'] : 5;
        $currentNumber = is_array($existing) && $existing !== [] ? (int) $existing['next_number'] : 1;
        $nextNumber = $currentNumber + 1;

        if (is_array($existing) && $existing !== []) {
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_buying_number_series
                SET prefix = ?, next_number = ?, series_status = 'ACTIVE', updated_by_admin_key = ?
                WHERE company_key_hash = ? AND number_series_key = ?",
                [$prefix, $nextNumber, $adminKey, $companyKeyHash, $seriesKey],
                'Buying / Procurement number-series update'
            );
        } else {
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_number_series (
                    number_series_key, company_key, company_key_hash, series_code, fiscal_year,
                    prefix, next_number, padding, series_status, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                [
                    $seriesKey,
                    $companyKey,
                    $companyKeyHash,
                    $seriesCode,
                    $fiscalYear,
                    $prefix,
                    $nextNumber,
                    $padding,
                    $adminKey,
                    $adminKey,
                ],
                'Buying / Procurement number-series creation'
            );
        }

        $saved = $db->GetRow(
            "SELECT number_series_key, company_key, company_key_hash, series_code, fiscal_year,
                prefix, next_number, padding, series_status, updated_by_admin_key
            FROM project_company_buying_number_series
            WHERE company_key_hash = ? AND number_series_key = ?
            LIMIT 1",
            [$companyKeyHash, $seriesKey]
        );
        if (!is_array($saved)
            || (string) ($saved['number_series_key'] ?? '') !== $seriesKey
            || (string) ($saved['company_key'] ?? '') !== $companyKey
            || (string) ($saved['company_key_hash'] ?? '') !== $companyKeyHash
            || (string) ($saved['series_code'] ?? '') !== $seriesCode
            || (int) ($saved['fiscal_year'] ?? 0) !== $fiscalYear
            || (string) ($saved['prefix'] ?? '') !== $prefix
            || (int) ($saved['next_number'] ?? 0) !== $nextNumber
            || (int) ($saved['padding'] ?? 0) !== $padding
            || (string) ($saved['series_status'] ?? '') !== 'ACTIVE'
            || (string) ($saved['updated_by_admin_key'] ?? '') !== $adminKey) {
            throw new RuntimeException('Buying / Procurement number-series read-back verification failed.');
        }

        $formatted = $prefix . $fiscalYear . '-' . str_pad((string) $currentNumber, $padding, '0', STR_PAD_LEFT);
        bx_audit('NUMBER', 'project_company_buying_number_series', $seriesKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'series_code' => $seriesCode,
            'fiscal_year' => $fiscalYear,
            'document_number' => $formatted,
            'next_number' => $nextNumber,
            'admin_key' => $adminKey,
        ], 'Company administrator allocated a Buying / Procurement document number.');

        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Buying / Procurement number transaction could not commit.');
        }

        return $formatted;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}
