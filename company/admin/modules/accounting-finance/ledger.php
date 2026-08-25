<?php
declare(strict_types=1);

function yovel_admin_general_ledger_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_general_ledger_transaction (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transaction_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            posting_date DATE NOT NULL,
            fiscal_year SMALLINT UNSIGNED NOT NULL,
            voucher_type VARCHAR(80) NOT NULL,
            voucher_no VARCHAR(120) NOT NULL,
            source_module VARCHAR(80) NULL,
            source_record_key CHAR(36) NULL,
            reversal_of_transaction_key CHAR(36) NULL,
            transaction_remarks VARCHAR(1000) NULL,
            entry_count INT UNSIGNED NOT NULL,
            total_debit DECIMAL(20,6) NOT NULL DEFAULT 0,
            total_credit DECIMAL(20,6) NOT NULL DEFAULT 0,
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_gl_voucher (company_key_hash, voucher_type, voucher_no, transaction_key),
            INDEX idx_project_company_gl_transaction_date (company_key_hash, posting_date, transaction_key),
            INDEX idx_project_company_gl_transaction_voucher (company_key_hash, voucher_type, voucher_no),
            INDEX idx_project_company_gl_transaction_reversal (company_key_hash, reversal_of_transaction_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_general_ledger_entry (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ledger_entry_key CHAR(36) NOT NULL UNIQUE,
            transaction_key CHAR(36) NOT NULL,
            line_no INT UNSIGNED NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            posting_date DATE NOT NULL,
            fiscal_year SMALLINT UNSIGNED NOT NULL,
            account_key CHAR(36) NOT NULL,
            account_code VARCHAR(80) NOT NULL,
            account_name VARCHAR(180) NOT NULL,
            voucher_type VARCHAR(80) NOT NULL,
            voucher_no VARCHAR(120) NOT NULL,
            voucher_detail_no VARCHAR(120) NULL,
            party_type VARCHAR(80) NULL,
            party VARCHAR(180) NULL,
            cost_center VARCHAR(180) NULL,
            project VARCHAR(180) NULL,
            finance_book VARCHAR(180) NULL,
            debit DECIMAL(20,6) NOT NULL DEFAULT 0,
            credit DECIMAL(20,6) NOT NULL DEFAULT 0,
            transaction_currency VARCHAR(20) NULL,
            account_currency VARCHAR(20) NULL,
            exchange_rate DECIMAL(20,8) NOT NULL DEFAULT 1,
            entry_remarks VARCHAR(1000) NULL,
            is_reversal TINYINT(1) NOT NULL DEFAULT 0,
            reversal_of_transaction_key CHAR(36) NULL,
            source_module VARCHAR(80) NULL,
            source_record_key CHAR(36) NULL,
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_gl_transaction_line (company_key_hash, transaction_key, line_no),
            INDEX idx_project_company_gl_entry_date (company_key_hash, posting_date, x_id),
            INDEX idx_project_company_gl_entry_account (company_key_hash, account_key, posting_date),
            INDEX idx_project_company_gl_entry_voucher (company_key_hash, voucher_type, voucher_no),
            INDEX idx_project_company_gl_entry_party (company_key_hash, party, posting_date),
            INDEX idx_project_company_gl_entry_dimensions (company_key_hash, cost_center, project, posting_date),
            INDEX idx_project_company_gl_entry_reversal (company_key_hash, is_reversal, reversal_of_transaction_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'General Ledger schema update');
    }
}

function yovel_admin_general_ledger_decimal(mixed $value, string $label, bool $allowZero = true): string
{
    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value)) {
        throw new InvalidArgumentException($label . ' must be a number.');
    }
    $number = (float) $value;
    if ($number < 0 || $number > 999999999999.99 || (!$allowZero && abs($number) < 0.0000005)) {
        throw new InvalidArgumentException($label . ' is outside the allowed range.');
    }
    return number_format($number, 6, '.', '');
}

function yovel_admin_general_ledger_text(mixed $value, int $maxLength, string $label, bool $required = false): string
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

function yovel_admin_post_general_ledger_transaction(ADOConnection $db, array $company, array $admin, array $transaction, bool $manageTransaction = true, bool $ensureSchema = true): array
{
    if ($ensureSchema) {
        yovel_admin_general_ledger_schema();
    }
    $companyKey = (string) ($company['company_key'] ?? '');
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $adminKey = (string) ($admin['admin_key'] ?? '');
    if ($companyKey === '' || $companyKeyHash === '' || $adminKey === '') {
        throw new InvalidArgumentException('General Ledger administrator scope is invalid.');
    }
    $transactionKey = trim((string) ($transaction['transaction_key'] ?? '')) ?: bx_uuid();
    if (!yovel_admin_is_uuid($transactionKey)) {
        throw new InvalidArgumentException('General Ledger transaction key is invalid.');
    }
    $postingDate = yovel_admin_optional_date((string) ($transaction['posting_date'] ?? ''), 'Posting date');
    if ($postingDate === '') {
        throw new InvalidArgumentException('Posting date is required.');
    }
    if (function_exists('yovel_admin_finance_assert_open_period')) {
        yovel_admin_finance_assert_open_period($company, $postingDate, $ensureSchema);
    }
    $fiscalYear = (int) substr($postingDate, 0, 4);
    $voucherType = strtoupper(yovel_admin_general_ledger_text($transaction['voucher_type'] ?? '', 80, 'Voucher type', true));
    $voucherNo = yovel_admin_general_ledger_text($transaction['voucher_no'] ?? '', 120, 'Voucher number', true);
    $sourceModule = strtoupper(yovel_admin_general_ledger_text($transaction['source_module'] ?? '', 80, 'Source module'));
    $sourceRecordKey = trim((string) ($transaction['source_record_key'] ?? ''));
    if ($sourceRecordKey !== '' && !yovel_admin_is_uuid($sourceRecordKey)) {
        throw new InvalidArgumentException('General Ledger source record key is invalid.');
    }
    $reversalOf = trim((string) ($transaction['reversal_of_transaction_key'] ?? ''));
    if ($reversalOf !== '' && !yovel_admin_is_uuid($reversalOf)) {
        throw new InvalidArgumentException('General Ledger reversal reference is invalid.');
    }
    $transactionRemarks = yovel_admin_general_ledger_text($transaction['remarks'] ?? '', 1000, 'Transaction remarks');
    $inputEntries = $transaction['entries'] ?? [];
    if (!is_array($inputEntries) || count($inputEntries) < 2 || count($inputEntries) > 1000) {
        throw new InvalidArgumentException('A General Ledger transaction must contain between 2 and 1000 entries.');
    }

    $accountKeys = [];
    foreach ($inputEntries as $inputEntry) {
        if (!is_array($inputEntry)) {
            throw new InvalidArgumentException('General Ledger entries must be structured rows.');
        }
        $accountKey = trim((string) ($inputEntry['account_key'] ?? ''));
        if (!yovel_admin_is_uuid($accountKey)) {
            throw new InvalidArgumentException('Every General Ledger row requires a valid account.');
        }
        $accountKeys[$accountKey] = true;
    }
    $placeholders = implode(',', array_fill(0, count($accountKeys), '?'));
    $accountRows = $db->GetAll(
        "SELECT account_key, account_code, account_name, account_currency, is_group, freeze_account, account_status
         FROM project_company_accounting_account
         WHERE company_key_hash = ? AND account_key IN ({$placeholders}) AND account_status = 'ACTIVE'",
        array_merge([$companyKeyHash], array_keys($accountKeys))
    );
    $accounts = [];
    foreach (is_array($accountRows) ? $accountRows : [] as $account) {
        $accounts[(string) $account['account_key']] = $account;
    }
    if (count($accounts) !== count($accountKeys)) {
        throw new InvalidArgumentException('Every General Ledger account must be active and belong to this company.');
    }

    $entries = [];
    $debitTotal = 0.0;
    $creditTotal = 0.0;
    foreach (array_values($inputEntries) as $index => $inputEntry) {
        $accountKey = (string) $inputEntry['account_key'];
        $account = $accounts[$accountKey];
        if ((int) ($account['is_group'] ?? 0) === 1) {
            throw new InvalidArgumentException('General Ledger entries can only post to ledger accounts, not groups.');
        }
        if ((int) ($account['freeze_account'] ?? 0) === 1) {
            throw new InvalidArgumentException('General Ledger entries cannot post to a frozen account.');
        }
        $debit = yovel_admin_general_ledger_decimal($inputEntry['debit'] ?? '0', 'Debit');
        $credit = yovel_admin_general_ledger_decimal($inputEntry['credit'] ?? '0', 'Credit');
        $hasDebit = (float) $debit > 0.0000005;
        $hasCredit = (float) $credit > 0.0000005;
        if ($hasDebit === $hasCredit) {
            throw new InvalidArgumentException('Every General Ledger row must contain exactly one positive debit or credit amount.');
        }
        $transactionCurrency = strtoupper(yovel_admin_general_ledger_text($inputEntry['transaction_currency'] ?? ($account['account_currency'] ?? ''), 20, 'Transaction currency'));
        $accountCurrency = strtoupper(yovel_admin_general_ledger_text($inputEntry['account_currency'] ?? ($account['account_currency'] ?? ''), 20, 'Account currency'));
        foreach ([$transactionCurrency, $accountCurrency] as $currency) {
            if ($currency !== '' && preg_match('/^[A-Z]{3,20}$/', $currency) !== 1) {
                throw new InvalidArgumentException('General Ledger currency codes must use uppercase letters.');
            }
        }
        $exchangeRate = yovel_admin_general_ledger_decimal($inputEntry['exchange_rate'] ?? '1', 'Exchange rate', false);
        $entries[] = [
            'ledger_entry_key' => bx_uuid(),
            'line_no' => $index + 1,
            'account_key' => $accountKey,
            'account_code' => (string) $account['account_code'],
            'account_name' => (string) $account['account_name'],
            'voucher_detail_no' => yovel_admin_general_ledger_text($inputEntry['voucher_detail_no'] ?? '', 120, 'Voucher detail number'),
            'party_type' => strtoupper(yovel_admin_general_ledger_text($inputEntry['party_type'] ?? '', 80, 'Party type')),
            'party' => yovel_admin_general_ledger_text($inputEntry['party'] ?? '', 180, 'Party'),
            'cost_center' => yovel_admin_general_ledger_text($inputEntry['cost_center'] ?? '', 180, 'Cost center'),
            'project' => yovel_admin_general_ledger_text($inputEntry['project'] ?? '', 180, 'Project'),
            'finance_book' => yovel_admin_general_ledger_text($inputEntry['finance_book'] ?? '', 180, 'Finance book'),
            'debit' => $debit,
            'credit' => $credit,
            'transaction_currency' => $transactionCurrency,
            'account_currency' => $accountCurrency,
            'exchange_rate' => $exchangeRate,
            'remarks' => yovel_admin_general_ledger_text($inputEntry['remarks'] ?? '', 1000, 'Entry remarks'),
        ];
        $debitTotal += (float) $debit;
        $creditTotal += (float) $credit;
    }
    if (abs($debitTotal - $creditTotal) > 0.0000005) {
        throw new InvalidArgumentException('General Ledger transaction debits and credits must balance.');
    }
    $totalDebit = number_format($debitTotal, 6, '.', '');
    $totalCredit = number_format($creditTotal, 6, '.', '');

    if ($manageTransaction && $db->BeginTrans() === false) {
        throw new RuntimeException('General Ledger transaction could not start.');
    }
    try {
        $duplicate = $db->GetOne(
            'SELECT transaction_key FROM project_company_general_ledger_transaction WHERE company_key_hash = ? AND transaction_key = ? FOR UPDATE',
            [$companyKeyHash, $transactionKey]
        );
        if (is_string($duplicate) && $duplicate !== '') {
            throw new InvalidArgumentException('General Ledger transaction already exists.');
        }
        if ($reversalOf !== '') {
            $original = $db->GetOne(
                'SELECT transaction_key FROM project_company_general_ledger_transaction WHERE company_key_hash = ? AND transaction_key = ? LIMIT 1',
                [$companyKeyHash, $reversalOf]
            );
            if ((string) $original !== $reversalOf) {
                throw new InvalidArgumentException('Original General Ledger transaction was not found for reversal.');
            }
        }
        yovel_admin_db_execute(
            $db,
            'INSERT INTO project_company_general_ledger_transaction (transaction_key, company_key, company_key_hash, posting_date, fiscal_year, voucher_type, voucher_no, source_module, source_record_key, reversal_of_transaction_key, transaction_remarks, entry_count, total_debit, total_credit, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$transactionKey, $companyKey, $companyKeyHash, $postingDate, $fiscalYear, $voucherType, $voucherNo, $sourceModule !== '' ? $sourceModule : null, $sourceRecordKey !== '' ? $sourceRecordKey : null, $reversalOf !== '' ? $reversalOf : null, $transactionRemarks !== '' ? $transactionRemarks : null, count($entries), $totalDebit, $totalCredit, $adminKey],
            'General Ledger transaction save'
        );
        foreach ($entries as $entry) {
            yovel_admin_db_execute(
                $db,
                'INSERT INTO project_company_general_ledger_entry (ledger_entry_key, transaction_key, line_no, company_key, company_key_hash, posting_date, fiscal_year, account_key, account_code, account_name, voucher_type, voucher_no, voucher_detail_no, party_type, party, cost_center, project, finance_book, debit, credit, transaction_currency, account_currency, exchange_rate, entry_remarks, is_reversal, reversal_of_transaction_key, source_module, source_record_key, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$entry['ledger_entry_key'], $transactionKey, $entry['line_no'], $companyKey, $companyKeyHash, $postingDate, $fiscalYear, $entry['account_key'], $entry['account_code'], $entry['account_name'], $voucherType, $voucherNo, $entry['voucher_detail_no'] !== '' ? $entry['voucher_detail_no'] : null, $entry['party_type'] !== '' ? $entry['party_type'] : null, $entry['party'] !== '' ? $entry['party'] : null, $entry['cost_center'] !== '' ? $entry['cost_center'] : null, $entry['project'] !== '' ? $entry['project'] : null, $entry['finance_book'] !== '' ? $entry['finance_book'] : null, $entry['debit'], $entry['credit'], $entry['transaction_currency'] !== '' ? $entry['transaction_currency'] : null, $entry['account_currency'] !== '' ? $entry['account_currency'] : null, $entry['exchange_rate'], $entry['remarks'] !== '' ? $entry['remarks'] : null, $reversalOf !== '' ? 1 : 0, $reversalOf !== '' ? $reversalOf : null, $sourceModule !== '' ? $sourceModule : null, $sourceRecordKey !== '' ? $sourceRecordKey : null, $adminKey],
                'General Ledger entry save'
            );
        }
        $saved = $db->GetRow(
            'SELECT transaction_key, reversal_of_transaction_key, entry_count, CAST(total_debit AS CHAR) AS total_debit, CAST(total_credit AS CHAR) AS total_credit FROM project_company_general_ledger_transaction WHERE company_key_hash = ? AND transaction_key = ? LIMIT 1',
            [$companyKeyHash, $transactionKey]
        );
        $savedEntryCount = (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash = ? AND transaction_key = ?',
            [$companyKeyHash, $transactionKey]
        );
        if (!is_array($saved) || (string) $saved['transaction_key'] !== $transactionKey || (int) $saved['entry_count'] !== count($entries) || $savedEntryCount !== count($entries) || number_format((float) $saved['total_debit'], 6, '.', '') !== $totalDebit || number_format((float) $saved['total_credit'], 6, '.', '') !== $totalCredit) {
            throw new RuntimeException('General Ledger transaction read-back verification failed.');
        }
        bx_audit('POST', 'project_company_general_ledger_transaction', $transactionKey, [
            'company_key' => $companyKey,
            'voucher_type' => $voucherType,
            'voucher_no' => $voucherNo,
            'posting_date' => $postingDate,
            'entry_count' => count($entries),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'reversal_of_transaction_key' => $reversalOf,
            'admin_key' => $adminKey,
        ], $reversalOf !== '' ? 'Company admin posted an immutable General Ledger reversal.' : 'Company admin posted a balanced General Ledger transaction.');
        if ($manageTransaction && $db->CommitTrans() === false) {
            throw new RuntimeException('General Ledger transaction could not commit.');
        }
        return [
            'transaction_key' => $transactionKey,
            'reversal_of_transaction_key' => $reversalOf,
            'entry_count' => count($entries),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
        ];
    } catch (Throwable $error) {
        if ($manageTransaction) {
            $db->RollbackTrans();
        }
        throw $error;
    }
}

function yovel_admin_reverse_general_ledger_transaction(ADOConnection $db, array $company, array $admin, string $transactionKey, string $postingDate, string $reason, bool $manageTransaction = true, bool $ensureSchema = true): array
{
    if (!yovel_admin_is_uuid($transactionKey)) {
        throw new InvalidArgumentException('General Ledger transaction key is invalid.');
    }
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $original = $db->GetRow(
        'SELECT * FROM project_company_general_ledger_transaction WHERE company_key_hash = ? AND transaction_key = ? LIMIT 1',
        [$companyKeyHash, $transactionKey]
    );
    $entries = $db->GetAll(
        'SELECT * FROM project_company_general_ledger_entry WHERE company_key_hash = ? AND transaction_key = ? ORDER BY line_no',
        [$companyKeyHash, $transactionKey]
    );
    if (!is_array($original) || $original === [] || !is_array($entries) || count($entries) < 2) {
        throw new InvalidArgumentException('Original General Ledger transaction was not found.');
    }
    $existingReversal = (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_general_ledger_transaction WHERE company_key_hash = ? AND reversal_of_transaction_key = ?',
        [$companyKeyHash, $transactionKey]
    );
    if ($existingReversal > 0) {
        throw new InvalidArgumentException('General Ledger transaction already has a reversal.');
    }
    $reversalEntries = array_map(static fn (array $entry): array => [
        'account_key' => (string) $entry['account_key'],
        'debit' => (string) $entry['credit'],
        'credit' => (string) $entry['debit'],
        'voucher_detail_no' => (string) ($entry['voucher_detail_no'] ?? ''),
        'party_type' => (string) ($entry['party_type'] ?? ''),
        'party' => (string) ($entry['party'] ?? ''),
        'cost_center' => (string) ($entry['cost_center'] ?? ''),
        'project' => (string) ($entry['project'] ?? ''),
        'finance_book' => (string) ($entry['finance_book'] ?? ''),
        'transaction_currency' => (string) ($entry['transaction_currency'] ?? ''),
        'account_currency' => (string) ($entry['account_currency'] ?? ''),
        'exchange_rate' => (string) ($entry['exchange_rate'] ?? '1'),
        'remarks' => $reason,
    ], $entries);
    return yovel_admin_post_general_ledger_transaction($db, $company, $admin, [
        'transaction_key' => bx_uuid(),
        'posting_date' => $postingDate,
        'voucher_type' => 'REVERSAL',
        'voucher_no' => 'REV-' . substr((string) $original['voucher_no'], 0, 116),
        'source_module' => (string) ($original['source_module'] ?? 'ACCOUNTING_FINANCE'),
        'source_record_key' => (string) ($original['source_record_key'] ?? ''),
        'reversal_of_transaction_key' => $transactionKey,
        'remarks' => $reason,
        'entries' => $reversalEntries,
    ], $manageTransaction, $ensureSchema);
}

function yovel_admin_general_ledger_filters(array $input): array
{
    $dateFrom = yovel_admin_optional_date((string) ($input['date_from'] ?? ''), 'From date');
    $dateTo = yovel_admin_optional_date((string) ($input['date_to'] ?? ''), 'To date');
    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        throw new InvalidArgumentException('General Ledger from date cannot be after the to date.');
    }
    $accountKey = trim((string) ($input['account_key'] ?? ''));
    if ($accountKey !== '' && !yovel_admin_is_uuid($accountKey)) {
        throw new InvalidArgumentException('General Ledger account filter is invalid.');
    }
    return [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'account_key' => $accountKey,
        'voucher_type' => strtoupper(substr(trim((string) ($input['voucher_type'] ?? '')), 0, 80)),
        'voucher_no' => substr(trim((string) ($input['voucher_no'] ?? '')), 0, 120),
        'party' => substr(trim((string) ($input['party'] ?? '')), 0, 180),
        'cost_center' => substr(trim((string) ($input['cost_center'] ?? '')), 0, 180),
        'project' => substr(trim((string) ($input['project'] ?? '')), 0, 180),
        'finance_book' => substr(trim((string) ($input['finance_book'] ?? '')), 0, 180),
        'include_reversals' => array_key_exists('include_reversals', $input) ? filter_var($input['include_reversals'], FILTER_VALIDATE_BOOLEAN) : true,
    ];
}

function yovel_admin_general_ledger_where(string $companyKeyHash, array $filters, bool $opening = false): array
{
    $where = ['company_key_hash = ?'];
    $params = [$companyKeyHash];
    if ($opening) {
        if ($filters['date_from'] !== '') {
            $where[] = 'posting_date < ?';
            $params[] = $filters['date_from'];
        } else {
            $where[] = '1 = 0';
        }
    } else {
        if ($filters['date_from'] !== '') {
            $where[] = 'posting_date >= ?';
            $params[] = $filters['date_from'];
        }
        if ($filters['date_to'] !== '') {
            $where[] = 'posting_date <= ?';
            $params[] = $filters['date_to'];
        }
    }
    foreach (['account_key', 'voucher_type', 'cost_center', 'project', 'finance_book'] as $column) {
        if ($filters[$column] !== '') {
            $where[] = $column . ' = ?';
            $params[] = $filters[$column];
        }
    }
    foreach (['voucher_no', 'party'] as $column) {
        if ($filters[$column] !== '') {
            $where[] = $column . ' LIKE ?';
            $params[] = '%' . $filters[$column] . '%';
        }
    }
    if (!$filters['include_reversals']) {
        $where[] = 'is_reversal = 0';
    }
    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

function yovel_admin_general_ledger_entries(array $company, array $filters = []): array
{
    yovel_admin_general_ledger_schema();
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    if ($companyKeyHash === '') {
        return [];
    }
    $normalized = yovel_admin_general_ledger_filters($filters);
    $where = yovel_admin_general_ledger_where($companyKeyHash, $normalized);
    $rows = bx_db()->GetAll(
        'SELECT * FROM project_company_general_ledger_entry WHERE ' . $where['sql'] . ' ORDER BY posting_date, x_id, line_no',
        $where['params']
    );
    return is_array($rows) ? $rows : [];
}

function yovel_admin_general_ledger_summary(array $company, array $filters = []): array
{
    yovel_admin_general_ledger_schema();
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    if ($companyKeyHash === '') {
        return ['opening_balance' => '0.000000', 'period_debit' => '0.000000', 'period_credit' => '0.000000', 'closing_balance' => '0.000000', 'entry_count' => 0];
    }
    $normalized = yovel_admin_general_ledger_filters($filters);
    $periodWhere = yovel_admin_general_ledger_where($companyKeyHash, $normalized);
    $openingWhere = yovel_admin_general_ledger_where($companyKeyHash, $normalized, true);
    $period = bx_db()->GetRow(
        'SELECT COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit, COUNT(*) AS entry_count FROM project_company_general_ledger_entry WHERE ' . $periodWhere['sql'],
        $periodWhere['params']
    );
    $opening = bx_db()->GetRow(
        'SELECT COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit FROM project_company_general_ledger_entry WHERE ' . $openingWhere['sql'],
        $openingWhere['params']
    );
    $openingBalance = (float) ($opening['debit'] ?? 0) - (float) ($opening['credit'] ?? 0);
    $periodDebit = (float) ($period['debit'] ?? 0);
    $periodCredit = (float) ($period['credit'] ?? 0);
    return [
        'opening_balance' => number_format($openingBalance, 6, '.', ''),
        'period_debit' => number_format($periodDebit, 6, '.', ''),
        'period_credit' => number_format($periodCredit, 6, '.', ''),
        'closing_balance' => number_format($openingBalance + $periodDebit - $periodCredit, 6, '.', ''),
        'entry_count' => (int) ($period['entry_count'] ?? 0),
    ];
}
