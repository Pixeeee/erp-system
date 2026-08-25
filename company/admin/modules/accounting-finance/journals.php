<?php
declare(strict_types=1);

function yovel_admin_finance_journal_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_finance_journal_entry (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            journal_entry_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            journal_no VARCHAR(120) NOT NULL,
            posting_date DATE NOT NULL,
            entry_type ENUM('GENERAL','OPENING','CLOSING','ADJUSTMENT','DEPRECIATION') NOT NULL DEFAULT 'GENERAL',
            remarks VARCHAR(1000) NULL,
            document_status ENUM('DRAFT','SUBMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            total_debit DECIMAL(20,6) NOT NULL DEFAULT 0,
            total_credit DECIMAL(20,6) NOT NULL DEFAULT 0,
            gl_transaction_key CHAR(36) NULL,
            reversal_transaction_key CHAR(36) NULL,
            submitted_by_admin_key CHAR(36) NULL,
            submitted_at TIMESTAMP NULL,
            cancelled_by_admin_key CHAR(36) NULL,
            cancelled_at TIMESTAMP NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_journal_no (company_key_hash, journal_no),
            INDEX idx_project_company_finance_journal_date (company_key_hash, posting_date, document_status),
            INDEX idx_project_company_finance_journal_gl (company_key_hash, gl_transaction_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_journal_row (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            journal_row_key CHAR(36) NOT NULL UNIQUE,
            journal_entry_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            line_no INT UNSIGNED NOT NULL,
            account_key CHAR(36) NOT NULL,
            party_type VARCHAR(80) NULL,
            party VARCHAR(180) NULL,
            cost_center VARCHAR(180) NULL,
            project VARCHAR(180) NULL,
            finance_book VARCHAR(180) NULL,
            debit DECIMAL(20,6) NOT NULL DEFAULT 0,
            credit DECIMAL(20,6) NOT NULL DEFAULT 0,
            exchange_rate DECIMAL(20,8) NOT NULL DEFAULT 1,
            remarks VARCHAR(1000) NULL,
            dimensions_json LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_journal_line (company_key_hash, journal_entry_key, line_no),
            INDEX idx_project_company_finance_journal_row_account (company_key_hash, account_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Finance Journal schema update');
    }
}

function yovel_admin_finance_journal_text(mixed $value, int $maxLength, string $label, bool $required = false): string
{
    $value = trim((string) $value);
    if ($required && $value === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }
    if (strlen($value) > $maxLength) {
        throw new InvalidArgumentException($label . ' must be ' . $maxLength . ' characters or fewer.');
    }
    return $value;
}

function yovel_admin_finance_normalize_journal_rows(ADOConnection $db, array $company, array $rows): array
{
    if (count($rows) < 2 || count($rows) > 1000) {
        throw new InvalidArgumentException('A Journal Entry must contain between 2 and 1000 rows.');
    }
    $hash = (string) ($company['company_key_hash'] ?? '');
    $normalized = [];
    $totalDebit = '0.000000';
    $totalCredit = '0.000000';
    foreach (array_values($rows) as $index => $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Journal Entry rows must be structured records.');
        }
        $accountKey = trim((string) ($row['account_key'] ?? ''));
        if (!yovel_admin_is_uuid($accountKey)) {
            throw new InvalidArgumentException('Every Journal Entry row requires a valid account.');
        }
        $account = $db->GetRow("SELECT account_key, is_group, account_status FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key = ? AND account_status <> 'DELETED' LIMIT 1", [$hash, $accountKey]);
        if (!is_array($account) || $account === []) {
            throw new InvalidArgumentException('Every Journal Entry account must belong to this company.');
        }
        if ((int) ($account['is_group'] ?? 0) === 1) {
            throw new InvalidArgumentException('Journal Entry rows cannot use group accounts.');
        }
        $debit = yovel_admin_finance_money($row['debit'] ?? '0');
        $credit = yovel_admin_finance_money($row['credit'] ?? '0');
        $hasDebit = bccomp($debit, '0', 6) === 1;
        $hasCredit = bccomp($credit, '0', 6) === 1;
        if ($hasDebit === $hasCredit) {
            throw new InvalidArgumentException('Every Journal Entry row must have exactly one positive debit or credit.');
        }
        $exchangeRate = yovel_admin_finance_money($row['exchange_rate'] ?? '1', 8);
        if (bccomp($exchangeRate, '0', 8) !== 1) {
            throw new InvalidArgumentException('Journal Entry exchange rate must be positive.');
        }
        $dimensions = $row['dimensions'] ?? [];
        if (!is_array($dimensions)) {
            throw new InvalidArgumentException('Journal Entry dimensions must be structured values.');
        }
        $normalized[] = [
            'journal_row_key' => yovel_admin_is_uuid(trim((string) ($row['journal_row_key'] ?? ''))) ? trim((string) $row['journal_row_key']) : bx_uuid(),
            'line_no' => $index + 1,
            'account_key' => $accountKey,
            'party_type' => strtoupper(yovel_admin_finance_journal_text($row['party_type'] ?? '', 80, 'Party type')),
            'party' => yovel_admin_finance_journal_text($row['party'] ?? '', 180, 'Party'),
            'cost_center' => yovel_admin_finance_journal_text($row['cost_center'] ?? '', 180, 'Cost Center'),
            'project' => yovel_admin_finance_journal_text($row['project'] ?? '', 180, 'Project'),
            'finance_book' => yovel_admin_finance_journal_text($row['finance_book'] ?? '', 180, 'Finance book'),
            'debit' => $debit,
            'credit' => $credit,
            'exchange_rate' => $exchangeRate,
            'remarks' => yovel_admin_finance_journal_text($row['remarks'] ?? '', 1000, 'Journal row remarks'),
            'dimensions_json' => $dimensions === [] ? null : json_encode($dimensions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ];
        $totalDebit = bcadd($totalDebit, $debit, 6);
        $totalCredit = bcadd($totalCredit, $credit, 6);
    }
    return ['rows' => $normalized, 'total_debit' => yovel_admin_finance_money($totalDebit), 'total_credit' => yovel_admin_finance_money($totalCredit)];
}

function yovel_admin_persist_journal_entry(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_finance_core_schema();
    yovel_admin_finance_journal_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $journalKey = trim((string) ($input['journal_entry_key'] ?? ''));
    if ($journalKey !== '' && !yovel_admin_is_uuid($journalKey)) {
        throw new InvalidArgumentException('Journal Entry key is invalid.');
    }
    $postingDate = yovel_admin_optional_date((string) ($input['posting_date'] ?? ''), 'Journal posting date');
    if ($postingDate === '') {
        throw new InvalidArgumentException('Journal posting date is required.');
    }
    $journalNo = yovel_admin_finance_journal_text($input['journal_no'] ?? '', 120, 'Journal number');
    if ($journalNo === '') {
        $journalNo = yovel_admin_finance_next_number($db, $company, $admin, 'JOURNAL_ENTRY', 'JV-', (int) substr($postingDate, 0, 4));
    }
    $entryType = yovel_admin_status((string) ($input['entry_type'] ?? 'GENERAL'), ['GENERAL', 'OPENING', 'CLOSING', 'ADJUSTMENT', 'DEPRECIATION'], 'GENERAL');
    $remarks = yovel_admin_finance_journal_text($input['remarks'] ?? '', 1000, 'Journal remarks');
    $normalized = yovel_admin_finance_normalize_journal_rows($db, $company, is_array($input['rows'] ?? null) ? $input['rows'] : []);
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Journal Entry transaction could not start.');
    }
    try {
        $existing = $journalKey !== ''
            ? $db->GetRow('SELECT * FROM project_company_finance_journal_entry WHERE company_key_hash = ? AND journal_entry_key = ? FOR UPDATE', [$hash, $journalKey])
            : $db->GetRow('SELECT * FROM project_company_finance_journal_entry WHERE company_key_hash = ? AND journal_no = ? FOR UPDATE', [$hash, $journalNo]);
        if (is_array($existing) && $existing !== [] && (string) $existing['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Submitted or Cancelled Journal Entries are immutable.');
        }
        if ($journalKey !== '' && (!is_array($existing) || $existing === [])) {
            $foreignOwner = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_finance_journal_entry WHERE journal_entry_key = ?', [$journalKey]);
            if ($foreignOwner > 0) {
                throw new InvalidArgumentException('Journal Entry does not belong to this company.');
            }
        }
        $journalKey = is_array($existing) && $existing !== [] ? (string) $existing['journal_entry_key'] : ($journalKey !== '' ? $journalKey : bx_uuid());
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_journal_entry (journal_entry_key, company_key, company_key_hash, journal_no, posting_date, entry_type, remarks, document_status, total_debit, total_credit, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, ?, ?, ?) ON DUPLICATE KEY UPDATE posting_date=VALUES(posting_date), entry_type=VALUES(entry_type), remarks=VALUES(remarks), total_debit=VALUES(total_debit), total_credit=VALUES(total_credit), updated_by_admin_key=VALUES(updated_by_admin_key)", [$journalKey, $companyKey, $hash, $journalNo, $postingDate, $entryType, $remarks !== '' ? $remarks : null, $normalized['total_debit'], $normalized['total_credit'], $adminKey, $adminKey], 'Journal Entry save');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_finance_journal_row WHERE company_key_hash = ? AND journal_entry_key = ?', [$hash, $journalKey], 'Journal Entry row reset');
        foreach ($normalized['rows'] as $row) {
            yovel_admin_db_execute($db, "INSERT INTO project_company_finance_journal_row (journal_row_key, journal_entry_key, company_key, company_key_hash, line_no, account_key, party_type, party, cost_center, project, finance_book, debit, credit, exchange_rate, remarks, dimensions_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [$row['journal_row_key'], $journalKey, $companyKey, $hash, $row['line_no'], $row['account_key'], $row['party_type'] !== '' ? $row['party_type'] : null, $row['party'] !== '' ? $row['party'] : null, $row['cost_center'] !== '' ? $row['cost_center'] : null, $row['project'] !== '' ? $row['project'] : null, $row['finance_book'] !== '' ? $row['finance_book'] : null, $row['debit'], $row['credit'], $row['exchange_rate'], $row['remarks'] !== '' ? $row['remarks'] : null, $row['dimensions_json']], 'Journal Entry row save');
        }
        $saved = yovel_admin_journal_entry($company, $journalKey, false);
        if (!is_array($saved) || count($saved['rows'] ?? []) !== count($normalized['rows']) || (string) $saved['total_debit'] !== $normalized['total_debit'] || (string) $saved['total_credit'] !== $normalized['total_credit']) {
            throw new RuntimeException('Journal Entry read-back verification failed.');
        }
        bx_audit(is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE', 'project_company_finance_journal_entry', $journalKey, ['company_key' => $companyKey, 'journal_no' => $journalNo, 'posting_date' => $postingDate, 'document_status' => 'DRAFT', 'admin_key' => $adminKey], 'Company administrator saved a Journal Entry Draft.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Journal Entry transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_submit_journal_entry(ADOConnection $db, array $company, array $admin, string $journalKey): array
{
    yovel_admin_finance_core_schema();
    yovel_admin_general_ledger_schema();
    yovel_admin_finance_journal_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    if (!yovel_admin_is_uuid($journalKey)) {
        throw new InvalidArgumentException('Journal Entry key is invalid.');
    }
    $draft = yovel_admin_journal_entry($company, $journalKey);
    if (!is_array($draft)) {
        throw new InvalidArgumentException('Journal Entry was not found for this company.');
    }
    yovel_admin_finance_assert_open_period($company, (string) $draft['posting_date'], false);
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Journal submission transaction could not start.');
    }
    try {
        $header = $db->GetRow('SELECT * FROM project_company_finance_journal_entry WHERE company_key_hash = ? AND journal_entry_key = ? FOR UPDATE', [$hash, $journalKey]);
        if (!is_array($header) || $header === [] || (string) $header['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Only a Draft Journal Entry can be submitted.');
        }
        $rows = $db->GetAll('SELECT * FROM project_company_finance_journal_row WHERE company_key_hash = ? AND journal_entry_key = ? ORDER BY line_no', [$hash, $journalKey]);
        $normalized = yovel_admin_finance_normalize_journal_rows($db, $company, is_array($rows) ? $rows : []);
        if (bccomp($normalized['total_debit'], $normalized['total_credit'], 6) !== 0) {
            throw new InvalidArgumentException('Journal Entry debits and credits must balance before submission.');
        }
        $posted = yovel_admin_post_general_ledger_transaction($db, $company, $admin, [
            'posting_date' => (string) $header['posting_date'],
            'voucher_type' => 'JOURNAL_ENTRY',
            'voucher_no' => (string) $header['journal_no'],
            'source_module' => 'JOURNAL_ENTRY',
            'source_record_key' => $journalKey,
            'remarks' => (string) ($header['remarks'] ?? ''),
            'entries' => array_map(static fn (array $row): array => [
                'account_key' => (string) $row['account_key'],
                'debit' => (string) $row['debit'],
                'credit' => (string) $row['credit'],
                'party_type' => (string) ($row['party_type'] ?? ''),
                'party' => (string) ($row['party'] ?? ''),
                'cost_center' => (string) ($row['cost_center'] ?? ''),
                'project' => (string) ($row['project'] ?? ''),
                'finance_book' => (string) ($row['finance_book'] ?? ''),
                'exchange_rate' => (string) ($row['exchange_rate'] ?? '1'),
                'remarks' => (string) ($row['remarks'] ?? ''),
            ], $normalized['rows']),
        ], false, false);
        yovel_admin_db_execute($db, "UPDATE project_company_finance_journal_entry SET document_status='SUBMITTED', gl_transaction_key=?, submitted_by_admin_key=?, submitted_at=CURRENT_TIMESTAMP, updated_by_admin_key=? WHERE company_key_hash=? AND journal_entry_key=? AND document_status='DRAFT'", [$posted['transaction_key'], $adminKey, $adminKey, $hash, $journalKey], 'Journal Entry submission');
        $saved = yovel_admin_journal_entry($company, $journalKey, false);
        if (!is_array($saved) || (string) $saved['document_status'] !== 'SUBMITTED' || (string) $saved['gl_transaction_key'] !== (string) $posted['transaction_key']) {
            throw new RuntimeException('Journal Entry submission read-back verification failed.');
        }
        bx_audit('SUBMIT', 'project_company_finance_journal_entry', $journalKey, ['company_key' => $companyKey, 'journal_no' => (string) $header['journal_no'], 'gl_transaction_key' => (string) $posted['transaction_key'], 'admin_key' => $adminKey], 'Company administrator submitted a balanced Journal Entry.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Journal submission transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_cancel_journal_entry(ADOConnection $db, array $company, array $admin, string $journalKey, string $postingDate, string $reason): array
{
    yovel_admin_finance_core_schema();
    yovel_admin_general_ledger_schema();
    yovel_admin_finance_journal_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $postingDate = yovel_admin_optional_date($postingDate, 'Cancellation posting date');
    $reason = yovel_admin_finance_journal_text($reason, 1000, 'Cancellation reason', true);
    if (!yovel_admin_is_uuid($journalKey) || $postingDate === '') {
        throw new InvalidArgumentException('Journal cancellation reference and posting date are required.');
    }
    yovel_admin_finance_assert_open_period($company, $postingDate, false);
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Journal cancellation transaction could not start.');
    }
    try {
        $header = $db->GetRow('SELECT * FROM project_company_finance_journal_entry WHERE company_key_hash = ? AND journal_entry_key = ? FOR UPDATE', [$hash, $journalKey]);
        if (!is_array($header) || $header === [] || (string) $header['document_status'] !== 'SUBMITTED' || !yovel_admin_is_uuid((string) ($header['gl_transaction_key'] ?? ''))) {
            throw new InvalidArgumentException('Only a Submitted Journal Entry can be cancelled.');
        }
        $reversal = yovel_admin_reverse_general_ledger_transaction($db, $company, $admin, (string) $header['gl_transaction_key'], $postingDate, $reason, false, false);
        yovel_admin_db_execute($db, "UPDATE project_company_finance_journal_entry SET document_status='CANCELLED', reversal_transaction_key=?, cancelled_by_admin_key=?, cancelled_at=CURRENT_TIMESTAMP, updated_by_admin_key=? WHERE company_key_hash=? AND journal_entry_key=? AND document_status='SUBMITTED'", [$reversal['transaction_key'], $adminKey, $adminKey, $hash, $journalKey], 'Journal Entry cancellation');
        $saved = yovel_admin_journal_entry($company, $journalKey, false);
        if (!is_array($saved) || (string) $saved['document_status'] !== 'CANCELLED' || (string) $saved['reversal_transaction_key'] !== (string) $reversal['transaction_key']) {
            throw new RuntimeException('Journal Entry cancellation read-back verification failed.');
        }
        bx_audit('CANCEL', 'project_company_finance_journal_entry', $journalKey, ['company_key' => $companyKey, 'journal_no' => (string) $header['journal_no'], 'reversal_transaction_key' => (string) $reversal['transaction_key'], 'admin_key' => $adminKey], 'Company administrator cancelled a Journal Entry through an additive reversal.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Journal cancellation transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_journal_entry(array $company, string $journalKey, bool $ensureSchema = true): ?array
{
    if ($ensureSchema) {
        yovel_admin_finance_journal_schema();
    }
    if (!yovel_admin_is_uuid($journalKey)) {
        return null;
    }
    $hash = (string) ($company['company_key_hash'] ?? '');
    $header = bx_db()->GetRow('SELECT * FROM project_company_finance_journal_entry WHERE company_key_hash = ? AND journal_entry_key = ? LIMIT 1', [$hash, $journalKey]);
    if (!is_array($header) || $header === []) {
        return null;
    }
    $rows = bx_db()->GetAll('SELECT * FROM project_company_finance_journal_row WHERE company_key_hash = ? AND journal_entry_key = ? ORDER BY line_no', [$hash, $journalKey]);
    $header['rows'] = is_array($rows) ? $rows : [];
    return $header;
}

function yovel_admin_journal_entries(array $company, array $filters = []): array
{
    yovel_admin_finance_journal_schema();
    $where = ['company_key_hash = ?'];
    $params = [(string) ($company['company_key_hash'] ?? '')];
    $status = strtoupper(trim((string) ($filters['document_status'] ?? '')));
    if (in_array($status, ['DRAFT', 'SUBMITTED', 'CANCELLED'], true)) {
        $where[] = 'document_status = ?';
        $params[] = $status;
    }
    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(journal_no LIKE ? OR remarks LIKE ?)';
        $params[] = '%' . substr($search, 0, 120) . '%';
        $params[] = '%' . substr($search, 0, 120) . '%';
    }
    $rows = bx_db()->GetAll('SELECT * FROM project_company_finance_journal_entry WHERE ' . implode(' AND ', $where) . ' ORDER BY posting_date DESC, x_id DESC LIMIT 1000', $params);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_finance_journal_rows_from_post(): array
{
    $json = trim((string) ($_POST['rows_json'] ?? ''));
    if ($json !== '') {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Journal Entry rows JSON is invalid.');
        }
        return $decoded;
    }
    $accountKeys = $_POST['row_account_key'] ?? [];
    $debits = $_POST['row_debit'] ?? [];
    $credits = $_POST['row_credit'] ?? [];
    if (!is_array($accountKeys)) {
        return [];
    }
    $rows = [];
    foreach (array_values($accountKeys) as $index => $accountKey) {
        $rows[] = [
            'account_key' => (string) $accountKey,
            'debit' => is_array($debits) ? (string) ($debits[$index] ?? '0') : '0',
            'credit' => is_array($credits) ? (string) ($credits[$index] ?? '0') : '0',
            'party_type' => (string) (is_array($_POST['row_party_type'] ?? null) ? ($_POST['row_party_type'][$index] ?? '') : ''),
            'party' => (string) (is_array($_POST['row_party'] ?? null) ? ($_POST['row_party'][$index] ?? '') : ''),
            'cost_center' => (string) (is_array($_POST['row_cost_center'] ?? null) ? ($_POST['row_cost_center'][$index] ?? '') : ''),
            'project' => (string) (is_array($_POST['row_project'] ?? null) ? ($_POST['row_project'][$index] ?? '') : ''),
            'remarks' => (string) (is_array($_POST['row_remarks'] ?? null) ? ($_POST['row_remarks'][$index] ?? '') : ''),
        ];
    }
    return $rows;
}

function yovel_admin_save_finance_journal(array $company, array $admin): string
{
    $input = $_POST;
    $input['rows'] = yovel_admin_finance_journal_rows_from_post();
    $saved = yovel_admin_persist_journal_entry(bx_db(), $company, $admin, $input);
    $GLOBALS['yovel_admin_saved_finance_document_key'] = (string) $saved['journal_entry_key'];
    return 'Journal Entry Draft saved.';
}

function yovel_admin_submit_finance_journal(array $company, array $admin): string
{
    $saved = yovel_admin_submit_journal_entry(bx_db(), $company, $admin, (string) ($_POST['journal_entry_key'] ?? ''));
    $GLOBALS['yovel_admin_saved_finance_document_key'] = (string) $saved['journal_entry_key'];
    return 'Journal Entry submitted and posted to the General Ledger.';
}

function yovel_admin_cancel_finance_journal(array $company, array $admin): string
{
    $saved = yovel_admin_cancel_journal_entry(bx_db(), $company, $admin, (string) ($_POST['journal_entry_key'] ?? ''), (string) ($_POST['cancellation_posting_date'] ?? date('Y-m-d')), (string) ($_POST['cancellation_reason'] ?? ''));
    $GLOBALS['yovel_admin_saved_finance_document_key'] = (string) $saved['journal_entry_key'];
    return 'Journal Entry cancelled with an additive General Ledger reversal.';
}
