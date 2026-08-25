<?php
declare(strict_types=1);

function yovel_admin_finance_banking_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_finance_bank_statement_row (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bank_statement_row_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            bank_account_key CHAR(36) NOT NULL,
            transaction_date DATE NOT NULL,
            reference_no VARCHAR(180) NULL,
            description VARCHAR(500) NULL,
            deposit DECIMAL(20,6) NOT NULL DEFAULT 0,
            withdrawal DECIMAL(20,6) NOT NULL DEFAULT 0,
            balance DECIMAL(20,6) NULL,
            row_fingerprint CHAR(64) NOT NULL,
            raw_row_json LONGTEXT NOT NULL,
            reconciliation_status ENUM('UNRECONCILED','RECONCILED') NOT NULL DEFAULT 'UNRECONCILED',
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_bank_fingerprint (company_key_hash, bank_account_key, row_fingerprint),
            INDEX idx_project_company_finance_bank_row (company_key_hash, bank_account_key, transaction_date, reconciliation_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_bank_reconciliation (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reconciliation_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            bank_account_key CHAR(36) NOT NULL,
            reconciliation_date DATE NOT NULL,
            statement_total DECIMAL(20,6) NOT NULL,
            matched_total DECIMAL(20,6) NOT NULL,
            difference_amount DECIMAL(20,6) NOT NULL,
            status ENUM('RECONCILED','UNRECONCILED') NOT NULL DEFAULT 'RECONCILED',
            reason VARCHAR(500) NULL,
            created_by_admin_key CHAR(36) NULL,
            unreconciled_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            unreconciled_at TIMESTAMP NULL,
            INDEX idx_project_company_finance_reconciliation (company_key_hash, bank_account_key, status, reconciliation_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_bank_match (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bank_match_key CHAR(36) NOT NULL UNIQUE,
            reconciliation_key CHAR(36) NOT NULL,
            bank_statement_row_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            source_type ENUM('PAYMENT_ENTRY','JOURNAL_ENTRY') NOT NULL,
            source_record_key CHAR(36) NOT NULL,
            matched_amount DECIMAL(20,6) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_bank_match (company_key_hash, reconciliation_key, bank_statement_row_key, source_type, source_record_key),
            INDEX idx_project_company_finance_bank_match_row (company_key_hash, bank_statement_row_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Finance Banking schema update');
    }
}

function yovel_admin_import_bank_statement(ADOConnection $db, array $company, array $admin, string $bankKey, string $csv, array $mapping): array
{
    yovel_admin_finance_core_schema();
    yovel_admin_finance_banking_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    if (!yovel_admin_is_uuid($bankKey) || (int) $db->GetOne("SELECT COUNT(*) FROM project_company_finance_bank_account WHERE company_key_hash=? AND bank_account_key=? AND status='ACTIVE'", [$hash, $bankKey]) !== 1) {
        throw new InvalidArgumentException('Bank Account is invalid.');
    }
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $csv);
    rewind($stream);
    $headers = fgetcsv($stream, null, ',', '"', '');
    if (!is_array($headers)) {
        throw new InvalidArgumentException('Bank statement CSV header is missing.');
    }
    $indexes = array_flip($headers);
    foreach (['date', 'deposit', 'withdrawal'] as $field) {
        if (!isset($indexes[$mapping[$field] ?? ''])) {
            throw new InvalidArgumentException('Bank statement mapping is incomplete.');
        }
    }
    $prepared = [];
    while (($raw = fgetcsv($stream, null, ',', '"', '')) !== false) {
        if (count(array_filter($raw, static fn ($value): bool => trim((string) $value) !== '')) === 0) {
            continue;
        }
        $get = static fn (string $field): string => trim((string) ($raw[$indexes[$mapping[$field] ?? ''] ?? -1] ?? ''));
        $date = yovel_admin_optional_date($get('date'), 'Bank transaction date');
        $deposit = yovel_admin_finance_money(str_replace([',', 'PHP', '₱'], '', $get('deposit')) ?: '0');
        $withdrawal = yovel_admin_finance_money(str_replace([',', 'PHP', '₱'], '', $get('withdrawal')) ?: '0');
        if ((bccomp($deposit, '0', 6) === 1) === (bccomp($withdrawal, '0', 6) === 1)) {
            throw new InvalidArgumentException('Each bank row requires exactly one positive deposit or withdrawal.');
        }
        $reference = substr($get('reference'), 0, 180);
        $description = substr($get('description'), 0, 500);
        $balanceValue = $get('balance');
        $balance = $balanceValue === '' ? null : yovel_admin_finance_money(str_replace(',', '', $balanceValue));
        $fingerprint = hash('sha256', implode('|', [$hash, $bankKey, $date, $reference, $deposit, $withdrawal, $description]));
        $rawMap = [];
        foreach ($headers as $index => $header) {
            $rawMap[(string) $header] = (string) ($raw[$index] ?? '');
        }
        $prepared[] = ['key' => bx_uuid(), 'date' => $date, 'reference' => $reference, 'description' => $description, 'deposit' => $deposit, 'withdrawal' => $withdrawal, 'balance' => $balance, 'fingerprint' => $fingerprint, 'raw' => json_encode($rawMap, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)];
    }
    fclose($stream);
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Bank import transaction could not start.');
    }
    $imported = 0;
    $duplicates = 0;
    try {
        foreach ($prepared as $row) {
            if ((int) $db->GetOne('SELECT COUNT(*) FROM project_company_finance_bank_statement_row WHERE company_key_hash=? AND bank_account_key=? AND row_fingerprint=?', [$hash, $bankKey, $row['fingerprint']]) > 0) {
                $duplicates++;
                continue;
            }
            yovel_admin_db_execute($db, 'INSERT INTO project_company_finance_bank_statement_row (bank_statement_row_key,company_key,company_key_hash,bank_account_key,transaction_date,reference_no,description,deposit,withdrawal,balance,row_fingerprint,raw_row_json,created_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [$row['key'], $companyKey, $hash, $bankKey, $row['date'], $row['reference'] ?: null, $row['description'] ?: null, $row['deposit'], $row['withdrawal'], $row['balance'], $row['fingerprint'], $row['raw'], $adminKey], 'Bank statement row import');
            $imported++;
        }
        bx_audit('IMPORT', 'project_company_finance_bank_statement_row', $bankKey, ['company_key' => $companyKey, 'imported_count' => $imported, 'duplicate_count' => $duplicates, 'admin_key' => $adminKey], 'Company administrator imported a bank statement.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Bank import transaction could not commit.');
        }
        return ['imported_count' => $imported, 'duplicate_count' => $duplicates, 'row_count' => count($prepared)];
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_bank_statement_rows(array $company, string $bankKey = '', array $filters = []): array
{
    yovel_admin_finance_banking_schema();
    $where = ['company_key_hash=?'];
    $params = [(string) ($company['company_key_hash'] ?? '')];
    if (yovel_admin_is_uuid($bankKey)) {
        $where[] = 'bank_account_key=?';
        $params[] = $bankKey;
    }
    $rows = bx_db()->GetAll('SELECT * FROM project_company_finance_bank_statement_row WHERE ' . implode(' AND ', $where) . ' ORDER BY transaction_date DESC,x_id DESC', $params);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_bank_match_candidates(array $company, string $rowKey): array
{
    yovel_admin_finance_banking_schema();
    $hash = (string) $company['company_key_hash'];
    $row = bx_db()->GetRow('SELECT * FROM project_company_finance_bank_statement_row WHERE company_key_hash=? AND bank_statement_row_key=?', [$hash, $rowKey]);
    if (!$row) {
        return [];
    }
    $amount = bccomp((string) $row['deposit'], '0', 6) === 1 ? (string) $row['deposit'] : (string) $row['withdrawal'];
    $payments = bx_db()->GetAll("SELECT 'PAYMENT_ENTRY' AS source_type,payment_entry_key AS source_record_key,payment_no AS source_no,posting_date,paid_amount AS amount,ABS(DATEDIFF(posting_date,?)) AS date_distance,CASE WHEN payment_no=? THEN 0 ELSE 1 END AS reference_rank FROM project_company_finance_payment_entry WHERE company_key_hash=? AND document_status='SUBMITTED' AND paid_amount=? ORDER BY reference_rank,date_distance,x_id LIMIT 50", [$row['transaction_date'], $row['reference_no'], $hash, $amount]);
    $bankLedgerKey = (string) bx_db()->GetOne("SELECT ledger_account_key FROM project_company_finance_bank_account WHERE company_key_hash=? AND bank_account_key=? AND status='ACTIVE'", [$hash, $row['bank_account_key']]);
    $amountColumn = bccomp((string) $row['deposit'], '0', 6) === 1 ? 'debit' : 'credit';
    $journals = $bankLedgerKey === '' ? [] : bx_db()->GetAll("SELECT 'JOURNAL_ENTRY' AS source_type,j.journal_entry_key AS source_record_key,j.journal_no AS source_no,j.posting_date,e.{$amountColumn} AS amount,ABS(DATEDIFF(j.posting_date,?)) AS date_distance,CASE WHEN j.journal_no=? THEN 0 ELSE 1 END AS reference_rank FROM project_company_finance_journal_entry j JOIN project_company_general_ledger_entry e ON e.company_key_hash=j.company_key_hash AND e.source_record_key=j.journal_entry_key AND e.source_module='JOURNAL_ENTRY' WHERE j.company_key_hash=? AND j.document_status='SUBMITTED' AND e.account_key=? AND e.{$amountColumn}=? ORDER BY reference_rank,date_distance,j.x_id LIMIT 50", [$row['transaction_date'], $row['reference_no'], $hash, $bankLedgerKey, $amount]);
    $rows = array_merge(is_array($payments) ? $payments : [], is_array($journals) ? $journals : []);
    usort($rows, static fn (array $left, array $right): int => [(int) $left['reference_rank'], (int) $left['date_distance'], (string) $left['source_no']] <=> [(int) $right['reference_rank'], (int) $right['date_distance'], (string) $right['source_no']]);
    return array_slice($rows, 0, 50);
}

function yovel_admin_reconcile_bank_rows(ADOConnection $db, array $company, array $admin, array $rowKeys, array $matches): array
{
    yovel_admin_finance_banking_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    if ($rowKeys === [] || $matches === []) {
        throw new InvalidArgumentException('Bank rows and source matches are required.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Bank reconciliation transaction could not start.');
    }
    try {
        $placeholders = implode(',', array_fill(0, count($rowKeys), '?'));
        $rows = $db->GetAll("SELECT * FROM project_company_finance_bank_statement_row WHERE company_key_hash=? AND bank_statement_row_key IN ({$placeholders}) FOR UPDATE", array_merge([$hash], $rowKeys));
        if (count($rows) !== count(array_unique($rowKeys))) {
            throw new InvalidArgumentException('One or more bank rows do not belong to this company.');
        }
        $bankKey = (string) $rows[0]['bank_account_key'];
        $statementTotal = '0.000000';
        foreach ($rows as $row) {
            if ((string) $row['bank_account_key'] !== $bankKey || (string) $row['reconciliation_status'] !== 'UNRECONCILED') {
                throw new InvalidArgumentException('Bank rows must be unreconciled and from one Bank Account.');
            }
            $statementTotal = bcadd($statementTotal, bccomp((string) $row['deposit'], '0', 6) === 1 ? (string) $row['deposit'] : (string) $row['withdrawal'], 6);
        }
        $key = bx_uuid();
        $date = max(array_column($rows, 'transaction_date'));
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_bank_reconciliation (reconciliation_key,company_key,company_key_hash,bank_account_key,reconciliation_date,statement_total,matched_total,difference_amount,status,created_by_admin_key) VALUES (?,?,?,?,?,0,0,0,'RECONCILED',?)", [$key, $companyKey, $hash, $bankKey, $date, $adminKey], 'Bank reconciliation save');
        $matchedTotal = '0.000000';
        foreach ($matches as $index => $match) {
            $sourceType = strtoupper((string) ($match['source_type'] ?? ''));
            $sourceKey = (string) ($match['source_record_key'] ?? '');
            $amount = yovel_admin_finance_money($match['matched_amount'] ?? '0');
            $sourceIsValid = $sourceType === 'PAYMENT_ENTRY'
                ? (int) $db->GetOne("SELECT COUNT(*) FROM project_company_finance_payment_entry WHERE company_key_hash=? AND payment_entry_key=? AND document_status='SUBMITTED'", [$hash, $sourceKey]) === 1
                : ($sourceType === 'JOURNAL_ENTRY' && (int) $db->GetOne("SELECT COUNT(*) FROM project_company_finance_journal_entry WHERE company_key_hash=? AND journal_entry_key=? AND document_status='SUBMITTED'", [$hash, $sourceKey]) === 1);
            if (!yovel_admin_is_uuid($sourceKey) || !$sourceIsValid) {
                throw new InvalidArgumentException('Bank match source is invalid.');
            }
            $rowKey = (string) ($match['bank_statement_row_key'] ?? $rowKeys[min($index, count($rowKeys) - 1)]);
            yovel_admin_db_execute($db, 'INSERT INTO project_company_finance_bank_match (bank_match_key,reconciliation_key,bank_statement_row_key,company_key,company_key_hash,source_type,source_record_key,matched_amount) VALUES (?,?,?,?,?,?,?,?)', [bx_uuid(), $key, $rowKey, $companyKey, $hash, $sourceType, $sourceKey, $amount], 'Bank match save');
            $matchedTotal = bcadd($matchedTotal, $amount, 6);
        }
        $difference = bcsub($statementTotal, $matchedTotal, 6);
        yovel_admin_db_execute($db, 'UPDATE project_company_finance_bank_reconciliation SET statement_total=?,matched_total=?,difference_amount=? WHERE company_key_hash=? AND reconciliation_key=?', [$statementTotal, $matchedTotal, $difference, $hash, $key], 'Bank reconciliation totals');
        yovel_admin_db_execute($db, "UPDATE project_company_finance_bank_statement_row SET reconciliation_status='RECONCILED' WHERE company_key_hash=? AND bank_statement_row_key IN ({$placeholders})", array_merge([$hash], $rowKeys), 'Bank row reconcile');
        $saved = $db->GetRow('SELECT * FROM project_company_finance_bank_reconciliation WHERE company_key_hash=? AND reconciliation_key=?', [$hash, $key]);
        bx_audit('RECONCILE', 'project_company_finance_bank_reconciliation', $key, ['company_key' => $companyKey, 'statement_total' => $statementTotal, 'matched_total' => $matchedTotal, 'difference_amount' => $difference, 'admin_key' => $adminKey], 'Company administrator reconciled bank rows.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Bank reconciliation transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_unreconcile_bank_match(ADOConnection $db, array $company, array $admin, string $key, string $reason): string
{
    yovel_admin_finance_banking_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    if (!yovel_admin_is_uuid($key) || trim($reason) === '') {
        throw new InvalidArgumentException('Reconciliation and reason are required.');
    }
    $db->BeginTrans();
    try {
        $record = $db->GetRow("SELECT * FROM project_company_finance_bank_reconciliation WHERE company_key_hash=? AND reconciliation_key=? AND status='RECONCILED' FOR UPDATE", [$hash, $key]);
        if (!$record) {
            throw new InvalidArgumentException('Active reconciliation was not found.');
        }
        $rowKeys = $db->GetCol('SELECT DISTINCT bank_statement_row_key FROM project_company_finance_bank_match WHERE company_key_hash=? AND reconciliation_key=?', [$hash, $key]);
        foreach ($rowKeys as $rowKey) {
            yovel_admin_db_execute($db, "UPDATE project_company_finance_bank_statement_row SET reconciliation_status='UNRECONCILED' WHERE company_key_hash=? AND bank_statement_row_key=?", [$hash, $rowKey], 'Bank row unreconcile');
        }
        yovel_admin_db_execute($db, "UPDATE project_company_finance_bank_reconciliation SET status='UNRECONCILED',reason=?,unreconciled_by_admin_key=?,unreconciled_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND reconciliation_key=?", [substr(trim($reason), 0, 500), $adminKey, $hash, $key], 'Bank reconciliation undo');
        bx_audit('UNRECONCILE', 'project_company_finance_bank_reconciliation', $key, ['company_key' => $companyKey, 'reason' => $reason, 'admin_key' => $adminKey], 'Company administrator reversed a bank reconciliation link.');
        $db->CommitTrans();
        return 'Bank reconciliation reversed.';
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_import_bank_statement_action(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'import_finance_bank_statement', static function () use ($company, $admin): string {
        $csv = (string) ($_POST['csv_text'] ?? '');
        if ($csv === '' && isset($_FILES['statement_file']['tmp_name']) && is_uploaded_file((string) $_FILES['statement_file']['tmp_name'])) {
            $csv = (string) file_get_contents((string) $_FILES['statement_file']['tmp_name']);
        }
        if ($csv === '') {
            throw new InvalidArgumentException('Choose a bank statement CSV file.');
        }
        $result = yovel_admin_import_bank_statement(bx_db(), $company, $admin, (string) ($_POST['bank_account_key'] ?? ''), $csv, [
            'date' => (string) ($_POST['date_column'] ?? 'Date'),
            'reference' => (string) ($_POST['reference_column'] ?? 'Reference'),
            'description' => (string) ($_POST['description_column'] ?? 'Description'),
            'deposit' => (string) ($_POST['deposit_column'] ?? 'Deposit'),
            'withdrawal' => (string) ($_POST['withdrawal_column'] ?? 'Withdrawal'),
            'balance' => (string) ($_POST['balance_column'] ?? 'Balance'),
        ]);
        return $result['imported_count'] . ' bank rows imported; ' . $result['duplicate_count'] . ' duplicates skipped.';
    });
}

function yovel_admin_reconcile_bank_action(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'reconcile_finance_bank', static function () use ($company, $admin): string {
        $rows = json_decode((string) ($_POST['row_keys_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        $matches = json_decode((string) ($_POST['matches_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        $saved = yovel_admin_reconcile_bank_rows(bx_db(), $company, $admin, is_array($rows) ? $rows : [], is_array($matches) ? $matches : []);
        $GLOBALS['yovel_admin_saved_finance_document_key'] = (string) $saved['reconciliation_key'];
        return 'Bank rows reconciled.';
    });
}

function yovel_admin_unreconcile_bank_action(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'unreconcile_finance_bank', static function () use ($company, $admin): string {
        return yovel_admin_unreconcile_bank_match(bx_db(), $company, $admin, (string) ($_POST['reconciliation_key'] ?? ''), (string) ($_POST['reason'] ?? ''));
    });
}
