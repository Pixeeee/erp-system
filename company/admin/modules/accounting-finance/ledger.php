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
        "CREATE TABLE IF NOT EXISTS project_company_finance_ledger_health_monitor (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ledger_health_monitor_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            monitor_name VARCHAR(180) NOT NULL,
            schedule_code ENUM('MANUAL','DAILY','WEEKLY','MONTHLY') NOT NULL DEFAULT 'DAILY',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            last_run_at TIMESTAMP NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_ledger_health_monitor (company_key_hash, monitor_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_ledger_health_run (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ledger_health_run_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            monitor_key CHAR(36) NULL,
            health_status ENUM('HEALTHY','ISSUES') NOT NULL,
            checked_transactions INT UNSIGNED NOT NULL DEFAULT 0,
            finding_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_company_ledger_health_run (company_key_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_ledger_health_finding (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ledger_health_finding_key CHAR(36) NOT NULL UNIQUE,
            ledger_health_run_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            finding_code VARCHAR(80) NOT NULL,
            severity ENUM('WARNING','ERROR') NOT NULL,
            transaction_key CHAR(36) NULL,
            finding_message VARCHAR(500) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_company_ledger_health_finding (company_key_hash, ledger_health_run_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_ledger_merge (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ledger_merge_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            posting_date DATE NOT NULL,
            reason VARCHAR(1000) NOT NULL,
            status ENUM('PENDING','COMPLETED','FAILED') NOT NULL DEFAULT 'PENDING',
            transaction_key CHAR(36) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX idx_project_company_ledger_merge (company_key_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_ledger_merge_account (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ledger_merge_account_key CHAR(36) NOT NULL UNIQUE,
            ledger_merge_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            source_account_key CHAR(36) NOT NULL,
            target_account_key CHAR(36) NOT NULL,
            transferred_balance DECIMAL(20,6) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_ledger_merge_account (company_key_hash, ledger_merge_key, source_account_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_ledger_repost (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            repost_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            original_transaction_key CHAR(36) NOT NULL,
            posting_date DATE NOT NULL,
            reason VARCHAR(1000) NOT NULL,
            status ENUM('PENDING','COMPLETED','FAILED') NOT NULL DEFAULT 'PENDING',
            reversal_transaction_key CHAR(36) NULL,
            replacement_transaction_key CHAR(36) NULL,
            created_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            UNIQUE KEY uq_project_company_ledger_repost_original (company_key_hash, original_transaction_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_ledger_repost_item (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            repost_item_key CHAR(36) NOT NULL UNIQUE,
            repost_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            original_transaction_key CHAR(36) NOT NULL,
            reversal_transaction_key CHAR(36) NOT NULL,
            replacement_transaction_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_company_ledger_repost_item (company_key_hash, repost_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'General Ledger schema update');
    }
    yovel_admin_finance_ensure_column($db, 'project_company_general_ledger_transaction', 'base_currency', "VARCHAR(20) NOT NULL DEFAULT 'PHP'");
    yovel_admin_finance_ensure_column($db, 'project_company_general_ledger_transaction', 'idempotency_key', 'VARCHAR(120) NULL');
    yovel_admin_finance_ensure_column($db, 'project_company_general_ledger_transaction', 'content_hash', 'CHAR(64) NULL');
    yovel_admin_finance_ensure_column($db, 'project_company_general_ledger_transaction', 'operation_type', 'VARCHAR(40) NULL');
    yovel_admin_finance_ensure_column($db, 'project_company_general_ledger_transaction', 'operation_key', 'CHAR(36) NULL');
    yovel_admin_finance_ensure_column($db, 'project_company_general_ledger_transaction', 'repost_of_transaction_key', 'CHAR(36) NULL');
    yovel_admin_finance_ensure_column($db, 'project_company_general_ledger_entry', 'transaction_debit', 'DECIMAL(20,6) NOT NULL DEFAULT 0');
    yovel_admin_finance_ensure_column($db, 'project_company_general_ledger_entry', 'transaction_credit', 'DECIMAL(20,6) NOT NULL DEFAULT 0');
    yovel_admin_finance_ensure_column($db, 'project_company_general_ledger_entry', 'dimensions_json', 'LONGTEXT NULL');
    yovel_admin_finance_ensure_index($db, 'project_company_general_ledger_transaction', 'uq_project_company_gl_idempotency', 'UNIQUE KEY', 'company_key_hash, idempotency_key');
}

function yovel_admin_finance_ensure_column(ADOConnection $db, string $table, string $column, string $definition): void
{
    if (preg_match('/^project_company_[a-z0-9_]+$/', $table) !== 1 || preg_match('/^[a-z0-9_]+$/', $column) !== 1) {
        throw new InvalidArgumentException('Unsafe Finance schema identifier.');
    }
    $exists = (int) $db->GetOne('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$table, $column]);
    if ($exists === 0) yovel_admin_db_execute($db, "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}", [], 'Finance schema column update');
}

function yovel_admin_finance_ensure_index(ADOConnection $db, string $table, string $index, string $kind, string $columns): void
{
    if (preg_match('/^project_company_[a-z0-9_]+$/', $table) !== 1 || preg_match('/^[a-z0-9_]+$/', $index) !== 1 || !in_array($kind, ['KEY','UNIQUE KEY'], true) || preg_match('/^[a-z0-9_, ]+$/', $columns) !== 1) {
        throw new InvalidArgumentException('Unsafe Finance index definition.');
    }
    $exists = (int) $db->GetOne('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?', [$table, $index]);
    if ($exists === 0) yovel_admin_db_execute($db, "ALTER TABLE `{$table}` ADD {$kind} `{$index}` ({$columns})", [], 'Finance schema index update');
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

function yovel_admin_post_general_ledger_transaction(ADOConnection $db, array $company, array $admin, array $transaction, bool $manageTransaction = true, bool $ensureSchema = true, ?callable $checkpoint = null): array
{
    if ($ensureSchema) {
        yovel_admin_general_ledger_schema();
    }
    [$companyKey, $companyKeyHash, $adminKey] = function_exists('yovel_admin_finance_scope')
        ? yovel_admin_finance_scope($company, $admin)
        : [(string) ($company['company_key'] ?? ''), (string) ($company['company_key_hash'] ?? ''), (string) ($admin['admin_key'] ?? '')];
    if ($companyKey === '' || $companyKeyHash === '' || $adminKey === '') throw new InvalidArgumentException('General Ledger administrator scope is invalid.');
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
    $repostOf = trim((string) ($transaction['repost_of_transaction_key'] ?? ''));
    if ($repostOf !== '' && !yovel_admin_is_uuid($repostOf)) throw new InvalidArgumentException('General Ledger repost reference is invalid.');
    $operationKey = trim((string) ($transaction['operation_key'] ?? ''));
    if ($operationKey !== '' && !yovel_admin_is_uuid($operationKey)) throw new InvalidArgumentException('General Ledger operation key is invalid.');
    $operationType = strtoupper(yovel_admin_general_ledger_text($transaction['operation_type'] ?? '', 40, 'Operation type'));
    $idempotencyKey = yovel_admin_general_ledger_text($transaction['idempotency_key'] ?? '', 120, 'Idempotency key');
    $baseCurrency = strtoupper(yovel_admin_general_ledger_text($transaction['base_currency'] ?? ($company['base_currency'] ?? 'PHP'),20,'Base currency',true));
    if(preg_match('/^[A-Z]{3,20}$/',$baseCurrency)!==1)throw new InvalidArgumentException('General Ledger base currency is invalid.');
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
    $debitTotal = '0.000000';
    $creditTotal = '0.000000';
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
        $transactionDebit = array_key_exists('transaction_debit', $inputEntry)
            ? yovel_admin_general_ledger_decimal($inputEntry['transaction_debit'], 'Transaction debit')
            : number_format((float) $debit / (float) $exchangeRate, 6, '.', '');
        $transactionCredit = array_key_exists('transaction_credit', $inputEntry)
            ? yovel_admin_general_ledger_decimal($inputEntry['transaction_credit'], 'Transaction credit')
            : number_format((float) $credit / (float) $exchangeRate, 6, '.', '');
        if (($hasDebit && ((float) $transactionDebit <= 0.0000005 || (float) $transactionCredit > 0.0000005)) || ($hasCredit && ((float) $transactionCredit <= 0.0000005 || (float) $transactionDebit > 0.0000005))) {
            throw new InvalidArgumentException('General Ledger foreign-currency amounts must follow the base debit or credit side.');
        }
        $expectedBase = $hasDebit ? bcmul($transactionDebit, $exchangeRate, 6) : bcmul($transactionCredit, $exchangeRate, 6);
        $actualBase = $hasDebit ? $debit : $credit;
        if (bccomp($expectedBase, $actualBase, 6) !== 0) throw new InvalidArgumentException('General Ledger base amount must equal the foreign amount multiplied by its exchange rate.');
        $partyType = strtoupper(yovel_admin_general_ledger_text($inputEntry['party_type'] ?? '', 80, 'Party type'));
        $party = yovel_admin_general_ledger_text($inputEntry['party'] ?? '', 180, 'Party');
        if (($partyType === '') !== ($party === '')) throw new InvalidArgumentException('General Ledger party type and party reference must be supplied together.');
        $dimensions = $inputEntry['dimensions'] ?? [];
        if (is_string($dimensions)) $dimensions = trim($dimensions) === '' ? [] : json_decode($dimensions, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($dimensions)) throw new InvalidArgumentException('General Ledger accounting dimensions must be structured values.');
        $entries[] = [
            'ledger_entry_key' => bx_uuid(),
            'line_no' => $index + 1,
            'account_key' => $accountKey,
            'account_code' => (string) $account['account_code'],
            'account_name' => (string) $account['account_name'],
            'voucher_detail_no' => yovel_admin_general_ledger_text($inputEntry['voucher_detail_no'] ?? '', 120, 'Voucher detail number'),
            'party_type' => $partyType,
            'party' => $party,
            'cost_center' => yovel_admin_general_ledger_text($inputEntry['cost_center'] ?? '', 180, 'Cost center'),
            'project' => yovel_admin_general_ledger_text($inputEntry['project'] ?? '', 180, 'Project'),
            'finance_book' => yovel_admin_general_ledger_text($inputEntry['finance_book'] ?? '', 180, 'Finance book'),
            'debit' => $debit,
            'credit' => $credit,
            'transaction_debit' => $transactionDebit,
            'transaction_credit' => $transactionCredit,
            'transaction_currency' => $transactionCurrency,
            'account_currency' => $accountCurrency,
            'exchange_rate' => $exchangeRate,
            'dimensions_json' => $dimensions === [] ? null : json_encode($dimensions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'remarks' => yovel_admin_general_ledger_text($inputEntry['remarks'] ?? '', 1000, 'Entry remarks'),
        ];
        $debitTotal = bcadd($debitTotal, $debit, 6);
        $creditTotal = bcadd($creditTotal, $credit, 6);
    }
    if (bccomp($debitTotal, $creditTotal, 6) !== 0) {
        throw new InvalidArgumentException('General Ledger transaction debits and credits must balance.');
    }
    $totalDebit = $debitTotal;
    $totalCredit = $creditTotal;
    $canonicalEntries=array_map(static function(array $entry):array{unset($entry['ledger_entry_key']);return $entry;},$entries);
    $contentHash = hash('sha256', json_encode([$postingDate,$voucherType,$voucherNo,$sourceModule,$sourceRecordKey,$reversalOf,$repostOf,$operationType,$operationKey,$canonicalEntries], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

    if ($manageTransaction && $db->BeginTrans() === false) {
        throw new RuntimeException('General Ledger transaction could not start.');
    }
    try {
        if ($idempotencyKey !== '') {
            $idempotent = $db->GetRow('SELECT transaction_key,content_hash,reversal_of_transaction_key,entry_count,CAST(total_debit AS CHAR) total_debit,CAST(total_credit AS CHAR) total_credit FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND idempotency_key=? FOR UPDATE', [$companyKeyHash,$idempotencyKey]);
            if (is_array($idempotent) && $idempotent !== []) {
                if (!hash_equals((string) ($idempotent['content_hash'] ?? ''), $contentHash)) throw new InvalidArgumentException('General Ledger idempotency key was already used for different content.');
                if ($manageTransaction) $db->CommitTrans();
                return ['transaction_key'=>(string)$idempotent['transaction_key'],'reversal_of_transaction_key'=>(string)($idempotent['reversal_of_transaction_key']??''),'entry_count'=>(int)$idempotent['entry_count'],'total_debit'=>number_format((float)$idempotent['total_debit'],6,'.',''),'total_credit'=>number_format((float)$idempotent['total_credit'],6,'.',''),'idempotent'=>true];
            }
        }
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
            'INSERT INTO project_company_general_ledger_transaction (transaction_key, company_key, company_key_hash, posting_date, fiscal_year, voucher_type, voucher_no, source_module, source_record_key, reversal_of_transaction_key, repost_of_transaction_key, transaction_remarks, entry_count, total_debit, total_credit, base_currency, idempotency_key, content_hash, operation_type, operation_key, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$transactionKey, $companyKey, $companyKeyHash, $postingDate, $fiscalYear, $voucherType, $voucherNo, $sourceModule !== '' ? $sourceModule : null, $sourceRecordKey !== '' ? $sourceRecordKey : null, $reversalOf !== '' ? $reversalOf : null, $repostOf !== '' ? $repostOf : null, $transactionRemarks !== '' ? $transactionRemarks : null, count($entries), $totalDebit, $totalCredit, $baseCurrency, $idempotencyKey !== '' ? $idempotencyKey : null, $contentHash, $operationType !== '' ? $operationType : null, $operationKey !== '' ? $operationKey : null, $adminKey],
            'General Ledger transaction save'
        );
        if ($checkpoint) $checkpoint('after_header');
        foreach ($entries as $entryIndex => $entry) {
            yovel_admin_db_execute(
                $db,
                'INSERT INTO project_company_general_ledger_entry (ledger_entry_key, transaction_key, line_no, company_key, company_key_hash, posting_date, fiscal_year, account_key, account_code, account_name, voucher_type, voucher_no, voucher_detail_no, party_type, party, cost_center, project, finance_book, debit, credit, transaction_debit, transaction_credit, transaction_currency, account_currency, exchange_rate, dimensions_json, entry_remarks, is_reversal, reversal_of_transaction_key, source_module, source_record_key, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$entry['ledger_entry_key'], $transactionKey, $entry['line_no'], $companyKey, $companyKeyHash, $postingDate, $fiscalYear, $entry['account_key'], $entry['account_code'], $entry['account_name'], $voucherType, $voucherNo, $entry['voucher_detail_no'] !== '' ? $entry['voucher_detail_no'] : null, $entry['party_type'] !== '' ? $entry['party_type'] : null, $entry['party'] !== '' ? $entry['party'] : null, $entry['cost_center'] !== '' ? $entry['cost_center'] : null, $entry['project'] !== '' ? $entry['project'] : null, $entry['finance_book'] !== '' ? $entry['finance_book'] : null, $entry['debit'], $entry['credit'], $entry['transaction_debit'], $entry['transaction_credit'], $entry['transaction_currency'] !== '' ? $entry['transaction_currency'] : null, $entry['account_currency'] !== '' ? $entry['account_currency'] : null, $entry['exchange_rate'], $entry['dimensions_json'], $entry['remarks'] !== '' ? $entry['remarks'] : null, $reversalOf !== '' ? 1 : 0, $reversalOf !== '' ? $reversalOf : null, $sourceModule !== '' ? $sourceModule : null, $sourceRecordKey !== '' ? $sourceRecordKey : null, $adminKey],
                'General Ledger entry save'
            );
            if ($checkpoint && $entryIndex === 0) $checkpoint('after_first_entry');
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
            'repost_of_transaction_key' => $repostOf,
            'operation_type' => $operationType,
            'operation_key' => $operationKey,
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
            'idempotent' => false,
        ];
    } catch (Throwable $error) {
        if ($manageTransaction) {
            $db->RollbackTrans();
        }
        throw $error;
    }
}

function yovel_admin_general_ledger_foreign_amount(array $entry,string $side):string
{
    $column=$side==='debit'?'transaction_debit':'transaction_credit';$baseColumn=$side==='debit'?'debit':'credit';$stored=(string)($entry[$column]??'0');if(is_numeric($stored)&&bccomp($stored,'0',6)===1)return number_format((float)$stored,6,'.','');$rate=(string)($entry['exchange_rate']??'1');if(!is_numeric($rate)||bccomp($rate,'0',8)!==1)$rate='1';return number_format((float)bcdiv((string)($entry[$baseColumn]??'0'),$rate,8),6,'.','');
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
        'transaction_debit' => yovel_admin_general_ledger_foreign_amount($entry,'credit'),
        'transaction_credit' => yovel_admin_general_ledger_foreign_amount($entry,'debit'),
        'voucher_detail_no' => (string) ($entry['voucher_detail_no'] ?? ''),
        'party_type' => (string) ($entry['party_type'] ?? ''),
        'party' => (string) ($entry['party'] ?? ''),
        'cost_center' => (string) ($entry['cost_center'] ?? ''),
        'project' => (string) ($entry['project'] ?? ''),
        'finance_book' => (string) ($entry['finance_book'] ?? ''),
        'transaction_currency' => (string) ($entry['transaction_currency'] ?? ''),
        'account_currency' => (string) ($entry['account_currency'] ?? ''),
        'exchange_rate' => (string) ($entry['exchange_rate'] ?? '1'),
        'dimensions' => json_decode((string) ($entry['dimensions_json'] ?? ''), true) ?: [],
        'remarks' => $reason,
        'base_currency' => (string)($original['base_currency'] ?? 'PHP'),
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

function yovel_admin_persist_ledger_health_monitor(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_general_ledger_schema();
    [$companyKey,$hash,$adminKey] = yovel_admin_finance_scope($company,$admin);
    $key = trim((string)($input['ledger_health_monitor_key'] ?? '')) ?: bx_uuid();
    if (!yovel_admin_is_uuid($key)) throw new InvalidArgumentException('Ledger Health Monitor key is invalid.');
    $name = yovel_admin_general_ledger_text($input['monitor_name'] ?? '',180,'Monitor name',true);
    $schedule = strtoupper((string)($input['schedule'] ?? 'DAILY'));
    if (!in_array($schedule,['MANUAL','DAILY','WEEKLY','MONTHLY'],true)) throw new InvalidArgumentException('Ledger Health Monitor schedule is invalid.');
    $enabled = filter_var($input['enabled'] ?? true,FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    if ($db->BeginTrans() === false) throw new RuntimeException('Ledger Health Monitor transaction could not start.');
    try {
        $existing = $db->GetRow('SELECT * FROM project_company_finance_ledger_health_monitor WHERE company_key_hash=? AND ledger_health_monitor_key=? FOR UPDATE',[$hash,$key]);
        if (!$existing && (int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_ledger_health_monitor WHERE ledger_health_monitor_key=?',[$key])>0) throw new InvalidArgumentException('Ledger Health Monitor does not belong to this company.');
        yovel_admin_db_execute($db,"INSERT INTO project_company_finance_ledger_health_monitor (ledger_health_monitor_key,company_key,company_key_hash,monitor_name,schedule_code,enabled,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE monitor_name=VALUES(monitor_name),schedule_code=VALUES(schedule_code),enabled=VALUES(enabled),updated_by_admin_key=VALUES(updated_by_admin_key)",[$key,$companyKey,$hash,$name,$schedule,$enabled,$adminKey,$adminKey],'Ledger Health Monitor save');
        $saved=$db->GetRow('SELECT * FROM project_company_finance_ledger_health_monitor WHERE company_key_hash=? AND ledger_health_monitor_key=?',[$hash,$key]);
        if (!$saved || (string)$saved['monitor_name']!==$name || (int)$saved['enabled']!==$enabled) throw new RuntimeException('Ledger Health Monitor read-back verification failed.');
        bx_audit($existing?'UPDATE':'CREATE','project_company_finance_ledger_health_monitor',$key,['company_key'=>$companyKey,'schedule'=>$schedule,'enabled'=>$enabled,'admin_key'=>$adminKey],'Company administrator saved a Ledger Health Monitor.');
        if ($db->CommitTrans()===false) throw new RuntimeException('Ledger Health Monitor transaction could not commit.');
        return $saved;
    } catch(Throwable $error) { $db->RollbackTrans(); throw $error; }
}

function yovel_admin_ledger_health_monitors(array $company): array
{
    yovel_admin_general_ledger_schema();
    $rows=bx_db()->GetAll('SELECT * FROM project_company_finance_ledger_health_monitor WHERE company_key_hash=? ORDER BY monitor_name',[(string)($company['company_key_hash']??'')]);
    return is_array($rows)?$rows:[];
}

function yovel_admin_general_ledger_health(ADOConnection $db, array $company, array $admin, array $options=[]): array
{
    yovel_admin_general_ledger_schema();
    [$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);
    $transactions=$db->GetAll("SELECT t.transaction_key,t.entry_count,t.total_debit,t.total_credit,COUNT(e.x_id) actual_count,COALESCE(SUM(e.debit),0) actual_debit,COALESCE(SUM(e.credit),0) actual_credit,COALESCE(SUM(CASE WHEN (e.debit>0 AND e.credit>0) OR (e.debit=0 AND e.credit=0) OR e.exchange_rate<=0 THEN 1 ELSE 0 END),0) invalid_rows FROM project_company_general_ledger_transaction t LEFT JOIN project_company_general_ledger_entry e ON e.company_key_hash=t.company_key_hash AND e.transaction_key=t.transaction_key WHERE t.company_key_hash=? GROUP BY t.transaction_key,t.entry_count,t.total_debit,t.total_credit ORDER BY t.x_id",[$hash]);
    $findings=[];
    foreach(is_array($transactions)?$transactions:[] as $row){
        $key=(string)$row['transaction_key'];
        if((int)$row['entry_count']!==(int)$row['actual_count'])$findings[]=['code'=>'ENTRY_COUNT_MISMATCH','severity'=>'ERROR','transaction_key'=>$key,'message'=>'Stored entry count does not match immutable ledger rows.'];
        if(bccomp((string)$row['actual_debit'],(string)$row['actual_credit'],6)!==0)$findings[]=['code'=>'UNBALANCED_TRANSACTION','severity'=>'ERROR','transaction_key'=>$key,'message'=>'General Ledger transaction debits and credits do not balance.'];
        if(bccomp((string)$row['total_debit'],(string)$row['actual_debit'],6)!==0||bccomp((string)$row['total_credit'],(string)$row['actual_credit'],6)!==0)$findings[]=['code'=>'HEADER_TOTAL_MISMATCH','severity'=>'ERROR','transaction_key'=>$key,'message'=>'Transaction totals do not match immutable ledger rows.'];
        if((int)$row['invalid_rows']>0)$findings[]=['code'=>'INVALID_ENTRY','severity'=>'ERROR','transaction_key'=>$key,'message'=>'A ledger row has invalid debit, credit, or exchange-rate values.'];
    }
    $orphans=$db->GetAll('SELECT DISTINCT e.transaction_key FROM project_company_general_ledger_entry e LEFT JOIN project_company_general_ledger_transaction t ON t.company_key_hash=e.company_key_hash AND t.transaction_key=e.transaction_key WHERE e.company_key_hash=? AND t.transaction_key IS NULL',[$hash]);
    foreach(is_array($orphans)?$orphans:[] as $row)$findings[]=['code'=>'ORPHAN_ENTRY','severity'=>'ERROR','transaction_key'=>(string)$row['transaction_key'],'message'=>'Ledger rows exist without an owning transaction header.'];
    $result=['status'=>$findings===[]?'HEALTHY':'ISSUES','checked_transactions'=>count(is_array($transactions)?$transactions:[]),'finding_count'=>count($findings),'findings'=>$findings];
    if(!filter_var($options['persist']??false,FILTER_VALIDATE_BOOLEAN))return $result;
    $runKey=bx_uuid();$monitorKey=trim((string)($options['monitor_key']??''));
    if($monitorKey!==''&&!yovel_admin_is_uuid($monitorKey))throw new InvalidArgumentException('Ledger Health Monitor reference is invalid.');
    if($db->BeginTrans()===false)throw new RuntimeException('Ledger Health run transaction could not start.');
    try{
        if($monitorKey!==''&&(int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_ledger_health_monitor WHERE company_key_hash=? AND ledger_health_monitor_key=? FOR UPDATE',[$hash,$monitorKey])!==1)throw new InvalidArgumentException('Ledger Health Monitor was not found for this company.');
        yovel_admin_db_execute($db,'INSERT INTO project_company_finance_ledger_health_run (ledger_health_run_key,company_key,company_key_hash,monitor_key,health_status,checked_transactions,finding_count,created_by_admin_key) VALUES (?,?,?,?,?,?,?,?)',[$runKey,$companyKey,$hash,$monitorKey!==''?$monitorKey:null,$result['status'],$result['checked_transactions'],$result['finding_count'],$adminKey],'Ledger Health run save');
        foreach($findings as $finding)yovel_admin_db_execute($db,'INSERT INTO project_company_finance_ledger_health_finding (ledger_health_finding_key,ledger_health_run_key,company_key,company_key_hash,finding_code,severity,transaction_key,finding_message) VALUES (?,?,?,?,?,?,?,?)',[bx_uuid(),$runKey,$companyKey,$hash,$finding['code'],$finding['severity'],$finding['transaction_key']?:null,$finding['message']],'Ledger Health finding save');
        if($monitorKey!=='')yovel_admin_db_execute($db,'UPDATE project_company_finance_ledger_health_monitor SET last_run_at=CURRENT_TIMESTAMP,updated_by_admin_key=? WHERE company_key_hash=? AND ledger_health_monitor_key=?',[$adminKey,$hash,$monitorKey],'Ledger Health Monitor run stamp');
        $saved=$db->GetRow('SELECT * FROM project_company_finance_ledger_health_run WHERE company_key_hash=? AND ledger_health_run_key=?',[$hash,$runKey]);
        $savedCount=(int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_ledger_health_finding WHERE company_key_hash=? AND ledger_health_run_key=?',[$hash,$runKey]);
        if(!$saved||$savedCount!==count($findings))throw new RuntimeException('Ledger Health run read-back verification failed.');
        bx_audit('CHECK','project_company_finance_ledger_health_run',$runKey,['company_key'=>$companyKey,'status'=>$result['status'],'finding_count'=>count($findings),'admin_key'=>$adminKey],'Company administrator ran immutable General Ledger diagnostics.');
        if($db->CommitTrans()===false)throw new RuntimeException('Ledger Health run transaction could not commit.');
        return $result+['ledger_health_run_key'=>$runKey];
    }catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_merge_general_ledger_accounts(ADOConnection $db,array $company,array $admin,array $input,?callable $checkpoint=null):array
{
    yovel_admin_general_ledger_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);
    $key=trim((string)($input['ledger_merge_key']??''))?:bx_uuid();$source=trim((string)($input['source_account_key']??''));$target=trim((string)($input['target_account_key']??''));
    if(!yovel_admin_is_uuid($key)||!yovel_admin_is_uuid($source)||!yovel_admin_is_uuid($target)||$source===$target)throw new InvalidArgumentException('Ledger Merge requires different valid source and target accounts.');
    $date=yovel_admin_optional_date((string)($input['posting_date']??''),'Ledger Merge posting date');$reason=yovel_admin_general_ledger_text($input['reason']??'',1000,'Ledger Merge reason',true);if($date==='')throw new InvalidArgumentException('Ledger Merge posting date is required.');
    yovel_admin_finance_assert_open_period($company,$date,false);if($db->BeginTrans()===false)throw new RuntimeException('Ledger Merge transaction could not start.');
    try{
        $existing=$db->GetRow('SELECT * FROM project_company_finance_ledger_merge WHERE company_key_hash=? AND ledger_merge_key=? FOR UPDATE',[$hash,$key]);if($existing){if((string)$existing['status']!=='COMPLETED')throw new InvalidArgumentException('Ledger Merge is already being processed.');if($db->CommitTrans()===false)throw new RuntimeException('Ledger Merge retry could not commit.');return $existing;}
        $accounts=$db->GetAll('SELECT account_key,is_group,freeze_account,account_status FROM project_company_accounting_account WHERE company_key_hash=? AND account_key IN (?,?) FOR UPDATE',[$hash,$source,$target]);
        if(count(is_array($accounts)?$accounts:[])!==2)throw new InvalidArgumentException('Ledger Merge accounts must belong to this company.');foreach($accounts as $account)if((int)$account['is_group']===1||(int)$account['freeze_account']===1||(string)$account['account_status']!=='ACTIVE')throw new InvalidArgumentException('Ledger Merge accounts must be active, unfrozen ledger accounts.');
        $balance=yovel_admin_finance_money((string)$db->GetOne('SELECT COALESCE(SUM(debit-credit),0) FROM project_company_general_ledger_entry WHERE company_key_hash=? AND account_key=?',[$hash,$source]));if(bccomp($balance,'0',6)===0)throw new InvalidArgumentException('Ledger Merge source account has no balance to transfer.');
        yovel_admin_db_execute($db,"INSERT INTO project_company_finance_ledger_merge (ledger_merge_key,company_key,company_key_hash,posting_date,reason,status,created_by_admin_key) VALUES (?,?,?,?,?,'PENDING',?)",[$key,$companyKey,$hash,$date,$reason,$adminKey],'Ledger Merge request save');
        $amount=ltrim($balance,'-');$entries=bccomp($balance,'0',6)===1?[['account_key'=>$target,'debit'=>$amount,'credit'=>'0'],['account_key'=>$source,'debit'=>'0','credit'=>$amount]]:[['account_key'=>$source,'debit'=>$amount,'credit'=>'0'],['account_key'=>$target,'debit'=>'0','credit'=>$amount]];
        $posted=yovel_admin_post_general_ledger_transaction($db,$company,$admin,['posting_date'=>$date,'voucher_type'=>'LEDGER_MERGE','voucher_no'=>'LM-'.substr(str_replace('-','',$key),0,16),'source_module'=>'ACCOUNTING_FINANCE','operation_type'=>'LEDGER_MERGE','operation_key'=>$key,'idempotency_key'=>'ledger-merge:'.$key,'remarks'=>$reason,'entries'=>$entries],false,false,$checkpoint);
        yovel_admin_db_execute($db,'INSERT INTO project_company_finance_ledger_merge_account (ledger_merge_account_key,ledger_merge_key,company_key,company_key_hash,source_account_key,target_account_key,transferred_balance) VALUES (?,?,?,?,?,?,?)',[bx_uuid(),$key,$companyKey,$hash,$source,$target,$balance],'Ledger Merge account save');
        yovel_admin_db_execute($db,"UPDATE project_company_finance_ledger_merge SET status='COMPLETED',transaction_key=?,completed_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND ledger_merge_key=? AND status='PENDING'",[$posted['transaction_key'],$hash,$key],'Ledger Merge completion');
        $saved=$db->GetRow('SELECT * FROM project_company_finance_ledger_merge WHERE company_key_hash=? AND ledger_merge_key=?',[$hash,$key]);if(!$saved||(string)$saved['status']!=='COMPLETED'||(string)$saved['transaction_key']!==(string)$posted['transaction_key'])throw new RuntimeException('Ledger Merge read-back verification failed.');
        bx_audit('MERGE','project_company_finance_ledger_merge',$key,['company_key'=>$companyKey,'source_account_key'=>$source,'target_account_key'=>$target,'transaction_key'=>$posted['transaction_key'],'admin_key'=>$adminKey],'Company administrator merged ledger balances through an additive transfer.');if($db->CommitTrans()===false)throw new RuntimeException('Ledger Merge transaction could not commit.');return $saved;
    }catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_repost_general_ledger_transaction(ADOConnection $db,array $company,array $admin,array $input,?callable $checkpoint=null):array
{
    yovel_admin_general_ledger_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$key=trim((string)($input['repost_key']??''))?:bx_uuid();$originalKey=trim((string)($input['transaction_key']??''));
    if(!yovel_admin_is_uuid($key)||!yovel_admin_is_uuid($originalKey))throw new InvalidArgumentException('Ledger repost keys are invalid.');$date=yovel_admin_optional_date((string)($input['posting_date']??''),'Ledger repost posting date');$reason=yovel_admin_general_ledger_text($input['reason']??'',1000,'Ledger repost reason',true);if($date==='')throw new InvalidArgumentException('Ledger repost posting date is required.');
    yovel_admin_finance_assert_open_period($company,$date,false);if($db->BeginTrans()===false)throw new RuntimeException('Ledger repost transaction could not start.');
    try{
        $existing=$db->GetRow('SELECT * FROM project_company_finance_ledger_repost WHERE company_key_hash=? AND repost_key=? FOR UPDATE',[$hash,$key]);if($existing){if((string)$existing['status']!=='COMPLETED')throw new InvalidArgumentException('Ledger repost is already being processed.');if($db->CommitTrans()===false)throw new RuntimeException('Ledger repost retry could not commit.');return $existing;}
        $original=$db->GetRow('SELECT * FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND transaction_key=? FOR UPDATE',[$hash,$originalKey]);$rows=$db->GetAll('SELECT * FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=? ORDER BY line_no FOR UPDATE',[$hash,$originalKey]);if(!$original||count(is_array($rows)?$rows:[])<2)throw new InvalidArgumentException('Original General Ledger transaction was not found for repost.');
        if((int)$db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND reversal_of_transaction_key=?',[$hash,$originalKey])>0)throw new InvalidArgumentException('Original General Ledger transaction already has a reversal.');
        yovel_admin_db_execute($db,"INSERT INTO project_company_finance_ledger_repost (repost_key,company_key,company_key_hash,original_transaction_key,posting_date,reason,status,created_by_admin_key) VALUES (?,?,?,?,?,?,'PENDING',?)",[$key,$companyKey,$hash,$originalKey,$date,$reason,$adminKey],'Ledger repost request save');
        $reversal=yovel_admin_reverse_general_ledger_transaction($db,$company,$admin,$originalKey,$date,$reason,false,false);
        $entries=array_map(static fn(array $row):array=>['account_key'=>$row['account_key'],'debit'=>$row['debit'],'credit'=>$row['credit'],'transaction_debit'=>yovel_admin_general_ledger_foreign_amount($row,'debit'),'transaction_credit'=>yovel_admin_general_ledger_foreign_amount($row,'credit'),'transaction_currency'=>$row['transaction_currency'],'account_currency'=>$row['account_currency'],'exchange_rate'=>$row['exchange_rate'],'party_type'=>$row['party_type'],'party'=>$row['party'],'cost_center'=>$row['cost_center'],'project'=>$row['project'],'finance_book'=>$row['finance_book'],'dimensions'=>json_decode((string)($row['dimensions_json']??''),true)?:[],'remarks'=>$reason],$rows);
        $replacement=yovel_admin_post_general_ledger_transaction($db,$company,$admin,['posting_date'=>$date,'voucher_type'=>(string)$original['voucher_type'],'voucher_no'=>'RP-'.substr((string)$original['voucher_no'],0,117),'source_module'=>(string)($original['source_module']??'ACCOUNTING_FINANCE'),'source_record_key'=>(string)($original['source_record_key']??''),'repost_of_transaction_key'=>$originalKey,'operation_type'=>'LEDGER_REPOST','operation_key'=>$key,'idempotency_key'=>'ledger-repost:'.$key,'base_currency'=>(string)($original['base_currency']??'PHP'),'remarks'=>$reason,'entries'=>$entries],false,false,$checkpoint);
        yovel_admin_db_execute($db,'INSERT INTO project_company_finance_ledger_repost_item (repost_item_key,repost_key,company_key,company_key_hash,original_transaction_key,reversal_transaction_key,replacement_transaction_key) VALUES (?,?,?,?,?,?,?)',[bx_uuid(),$key,$companyKey,$hash,$originalKey,$reversal['transaction_key'],$replacement['transaction_key']],'Ledger repost item save');
        yovel_admin_db_execute($db,"UPDATE project_company_finance_ledger_repost SET status='COMPLETED',reversal_transaction_key=?,replacement_transaction_key=?,completed_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND repost_key=? AND status='PENDING'",[$reversal['transaction_key'],$replacement['transaction_key'],$hash,$key],'Ledger repost completion');
        $saved=$db->GetRow('SELECT * FROM project_company_finance_ledger_repost WHERE company_key_hash=? AND repost_key=?',[$hash,$key]);if(!$saved||(string)$saved['status']!=='COMPLETED')throw new RuntimeException('Ledger repost read-back verification failed.');
        bx_audit('REPOST','project_company_finance_ledger_repost',$key,['company_key'=>$companyKey,'original_transaction_key'=>$originalKey,'reversal_transaction_key'=>$reversal['transaction_key'],'replacement_transaction_key'=>$replacement['transaction_key'],'admin_key'=>$adminKey],'Company administrator reposted a ledger transaction through additive reversal and replacement.');if($db->CommitTrans()===false)throw new RuntimeException('Ledger repost transaction could not commit.');return $saved;
    }catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_ledger_repair_history(array $company):array
{
    yovel_admin_general_ledger_schema();$hash=(string)($company['company_key_hash']??'');
    return ['merges'=>bx_db()->GetAll('SELECT * FROM project_company_finance_ledger_merge WHERE company_key_hash=? ORDER BY x_id DESC LIMIT 100',[$hash])?:[],'reposts'=>bx_db()->GetAll('SELECT * FROM project_company_finance_ledger_repost WHERE company_key_hash=? ORDER BY x_id DESC LIMIT 100',[$hash])?:[],'health_runs'=>bx_db()->GetAll('SELECT * FROM project_company_finance_ledger_health_run WHERE company_key_hash=? ORDER BY x_id DESC LIMIT 20',[$hash])?:[]];
}

function yovel_admin_general_ledger_transactions(array $company,int $limit=200):array
{
    yovel_admin_general_ledger_schema();$limit=max(1,min(1000,$limit));$rows=bx_db()->GetAll("SELECT transaction_key,posting_date,voucher_type,voucher_no,source_module,source_record_key,reversal_of_transaction_key,repost_of_transaction_key,entry_count,total_debit,total_credit FROM project_company_general_ledger_transaction WHERE company_key_hash=? ORDER BY posting_date DESC,x_id DESC LIMIT {$limit}",[(string)($company['company_key_hash']??'')]);return is_array($rows)?$rows:[];
}
