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
        "CREATE TABLE IF NOT EXISTS project_company_finance_journal_template (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            journal_template_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            template_name VARCHAR(180) NOT NULL,
            entry_type ENUM('GENERAL','OPENING','CLOSING','ADJUSTMENT','DEPRECIATION') NOT NULL DEFAULT 'GENERAL',
            remarks VARCHAR(1000) NULL,
            template_status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NOT NULL,
            updated_by_admin_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_journal_template_name (company_key_hash, template_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_journal_template_account (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            journal_template_account_key CHAR(36) NOT NULL UNIQUE,
            journal_template_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            line_no INT UNSIGNED NOT NULL,
            account_key CHAR(36) NOT NULL,
            party_type VARCHAR(80) NULL,
            party VARCHAR(180) NULL,
            cost_center VARCHAR(180) NULL,
            project VARCHAR(180) NULL,
            finance_book VARCHAR(180) NULL,
            transaction_currency VARCHAR(20) NULL,
            exchange_rate DECIMAL(20,8) NOT NULL DEFAULT 1,
            dimensions_json LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_journal_template_line (company_key_hash, journal_template_key, line_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Finance Journal schema update');
    }
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_entry','template_key','CHAR(36) NULL');
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_entry','base_currency',"VARCHAR(20) NOT NULL DEFAULT 'PHP'");
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_entry','inter_company','TINYINT(1) NOT NULL DEFAULT 0');
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_entry','counterparty_company_key','CHAR(36) NULL');
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_entry','counterparty_company_key_hash','CHAR(64) NULL');
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_entry','inter_company_reference','VARCHAR(180) NULL');
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_entry','amends_journal_entry_key','CHAR(36) NULL');
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_entry','amendment_index','INT UNSIGNED NOT NULL DEFAULT 0');
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_row','transaction_debit','DECIMAL(20,6) NOT NULL DEFAULT 0');
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_row','transaction_credit','DECIMAL(20,6) NOT NULL DEFAULT 0');
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_row','transaction_currency','VARCHAR(20) NULL');
    yovel_admin_finance_ensure_column($db,'project_company_finance_journal_row','account_currency','VARCHAR(20) NULL');
    yovel_admin_finance_ensure_index($db,'project_company_finance_journal_entry','uq_project_company_journal_intercompany','UNIQUE KEY','company_key_hash, inter_company_reference');
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
    $baseCurrency = strtoupper((string) (yovel_admin_finance_settings($company)['base_currency'] ?? 'PHP'));
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
        $account = $db->GetRow("SELECT account_key,account_currency,is_group,freeze_account,account_status FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key = ? AND account_status <> 'DELETED' LIMIT 1", [$hash, $accountKey]);
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
        $transactionCurrency = strtoupper(trim((string)($row['transaction_currency'] ?? ($account['account_currency'] ?: $baseCurrency))));
        $accountCurrency = strtoupper(trim((string)($row['account_currency'] ?? ($account['account_currency'] ?: $baseCurrency))));
        if(preg_match('/^[A-Z]{3,20}$/',$transactionCurrency)!==1||preg_match('/^[A-Z]{3,20}$/',$accountCurrency)!==1)throw new InvalidArgumentException('Journal Entry currencies are invalid.');
        $transactionDebit = array_key_exists('transaction_debit',$row) ? yovel_admin_finance_money($row['transaction_debit']) : yovel_admin_finance_money(bcdiv($debit,$exchangeRate,8));
        $transactionCredit = array_key_exists('transaction_credit',$row) ? yovel_admin_finance_money($row['transaction_credit']) : yovel_admin_finance_money(bcdiv($credit,$exchangeRate,8));
        if(($hasDebit&&(bccomp($transactionDebit,'0',6)!==1||bccomp($transactionCredit,'0',6)!==0))||($hasCredit&&(bccomp($transactionCredit,'0',6)!==1||bccomp($transactionDebit,'0',6)!==0)))throw new InvalidArgumentException('Journal Entry foreign amounts must follow the base debit or credit side.');
        $expectedBase=$hasDebit?bcmul($transactionDebit,$exchangeRate,6):bcmul($transactionCredit,$exchangeRate,6);$actualBase=$hasDebit?$debit:$credit;
        if(bccomp($expectedBase,$actualBase,6)!==0)throw new InvalidArgumentException('Journal Entry base amount must equal the foreign amount multiplied by its exchange rate.');
        $partyType=strtoupper(yovel_admin_finance_journal_text($row['party_type']??'',80,'Party type'));$party=yovel_admin_finance_journal_text($row['party']??'',180,'Party');
        if(($partyType==='')!==($party===''))throw new InvalidArgumentException('Journal Entry party type and party reference must be supplied together.');
        $dimensions = $row['dimensions'] ?? ($row['dimensions_json'] ?? []);
        if(is_string($dimensions))$dimensions=trim($dimensions)===''?[]:json_decode($dimensions,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($dimensions)) {
            throw new InvalidArgumentException('Journal Entry dimensions must be structured values.');
        }
        $normalized[] = [
            'journal_row_key' => yovel_admin_is_uuid(trim((string) ($row['journal_row_key'] ?? ''))) ? trim((string) $row['journal_row_key']) : bx_uuid(),
            'line_no' => $index + 1,
            'account_key' => $accountKey,
            'party_type' => $partyType,
            'party' => $party,
            'cost_center' => yovel_admin_finance_journal_text($row['cost_center'] ?? '', 180, 'Cost Center'),
            'project' => yovel_admin_finance_journal_text($row['project'] ?? '', 180, 'Project'),
            'finance_book' => yovel_admin_finance_journal_text($row['finance_book'] ?? '', 180, 'Finance book'),
            'debit' => $debit,
            'credit' => $credit,
            'transaction_debit' => $transactionDebit,
            'transaction_credit' => $transactionCredit,
            'transaction_currency' => $transactionCurrency,
            'account_currency' => $accountCurrency,
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
    $settings=yovel_admin_finance_settings($company,$admin);$baseCurrency=strtoupper((string)($settings['base_currency']??'PHP'));
    $templateKey=trim((string)($input['template_key']??''));if($templateKey!==''&&(!yovel_admin_is_uuid($templateKey)||(int)$db->GetOne("SELECT COUNT(*) FROM project_company_finance_journal_template WHERE company_key_hash=? AND journal_template_key=? AND template_status='ACTIVE'",[$hash,$templateKey])!==1))throw new InvalidArgumentException('Journal Entry Template was not found for this company.');
    $interCompany=filter_var($input['inter_company']??false,FILTER_VALIDATE_BOOLEAN)?1:0;$counterpartyKey=trim((string)($input['counterparty_company_key']??''));$counterpartyHash=trim((string)($input['counterparty_company_key_hash']??''));$interCompanyReference=yovel_admin_finance_journal_text($input['inter_company_reference']??'',180,'Inter-company reference');
    if($interCompany===1){if($counterpartyKey===''||$counterpartyHash===''||$counterpartyHash===$hash||$interCompanyReference==='')throw new InvalidArgumentException('Inter-company Journal requires a different active counterparty company and reference.');$counterparty=(int)$db->GetOne("SELECT COUNT(*) FROM project_company WHERE company_key=? AND company_key_hash=? AND company_status<>'DELETED'",[$counterpartyKey,$counterpartyHash]);if($counterparty!==1)throw new InvalidArgumentException('Inter-company counterparty company is invalid.');}else{$counterpartyKey='';$counterpartyHash='';$interCompanyReference='';}
    $amendsKey=trim((string)($input['amends_journal_entry_key']??''));$amendmentIndex=0;if($amendsKey!==''){if(!yovel_admin_is_uuid($amendsKey))throw new InvalidArgumentException('Journal amendment reference is invalid.');$amended=$db->GetRow("SELECT amendment_index FROM project_company_finance_journal_entry WHERE company_key_hash=? AND journal_entry_key=? AND document_status='CANCELLED'",[$hash,$amendsKey]);if(!$amended)throw new InvalidArgumentException('Only a Cancelled Journal Entry can be amended.');$amendmentIndex=(int)$amended['amendment_index']+1;}
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
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_journal_entry (journal_entry_key,company_key,company_key_hash,journal_no,posting_date,entry_type,remarks,document_status,total_debit,total_credit,template_key,base_currency,inter_company,counterparty_company_key,counterparty_company_key_hash,inter_company_reference,amends_journal_entry_key,amendment_index,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,'DRAFT',?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE posting_date=VALUES(posting_date),entry_type=VALUES(entry_type),remarks=VALUES(remarks),total_debit=VALUES(total_debit),total_credit=VALUES(total_credit),template_key=VALUES(template_key),base_currency=VALUES(base_currency),inter_company=VALUES(inter_company),counterparty_company_key=VALUES(counterparty_company_key),counterparty_company_key_hash=VALUES(counterparty_company_key_hash),inter_company_reference=VALUES(inter_company_reference),updated_by_admin_key=VALUES(updated_by_admin_key)", [$journalKey,$companyKey,$hash,$journalNo,$postingDate,$entryType,$remarks!==''?$remarks:null,$normalized['total_debit'],$normalized['total_credit'],$templateKey!==''?$templateKey:null,$baseCurrency,$interCompany,$counterpartyKey!==''?$counterpartyKey:null,$counterpartyHash!==''?$counterpartyHash:null,$interCompanyReference!==''?$interCompanyReference:null,$amendsKey!==''?$amendsKey:null,$amendmentIndex,$adminKey,$adminKey], 'Journal Entry save');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_finance_journal_row WHERE company_key_hash = ? AND journal_entry_key = ?', [$hash, $journalKey], 'Journal Entry row reset');
        foreach ($normalized['rows'] as $row) {
            yovel_admin_db_execute($db, "INSERT INTO project_company_finance_journal_row (journal_row_key,journal_entry_key,company_key,company_key_hash,line_no,account_key,party_type,party,cost_center,project,finance_book,debit,credit,transaction_debit,transaction_credit,transaction_currency,account_currency,exchange_rate,remarks,dimensions_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [$row['journal_row_key'],$journalKey,$companyKey,$hash,$row['line_no'],$row['account_key'],$row['party_type']!==''?$row['party_type']:null,$row['party']!==''?$row['party']:null,$row['cost_center']!==''?$row['cost_center']:null,$row['project']!==''?$row['project']:null,$row['finance_book']!==''?$row['finance_book']:null,$row['debit'],$row['credit'],$row['transaction_debit'],$row['transaction_credit'],$row['transaction_currency'],$row['account_currency'],$row['exchange_rate'],$row['remarks']!==''?$row['remarks']:null,$row['dimensions_json']], 'Journal Entry row save');
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
    if (function_exists('yovel_admin_assert_budget_available')) {
        foreach ($draft['rows'] as $row) {
            if (bccomp((string) $row['debit'], '0', 6) === 1) {
                $rootType = (string) $db->GetOne('SELECT root_type FROM project_company_accounting_account WHERE company_key_hash=? AND account_key=?', [$hash, $row['account_key']]);
                if ($rootType === 'EXPENSE') {
                    yovel_admin_assert_budget_available($company, (string) $draft['posting_date'], (string) $row['account_key'], (string) $row['debit']);
                }
            }
        }
    }
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
            'base_currency' => (string) ($header['base_currency'] ?? 'PHP'),
            'entries' => array_map(static fn (array $row): array => [
                'account_key' => (string) $row['account_key'],
                'debit' => (string) $row['debit'],
                'credit' => (string) $row['credit'],
                'transaction_debit' => (string) ($row['transaction_debit'] ?? $row['debit']),
                'transaction_credit' => (string) ($row['transaction_credit'] ?? $row['credit']),
                'transaction_currency' => (string) ($row['transaction_currency'] ?? ''),
                'account_currency' => (string) ($row['account_currency'] ?? ''),
                'party_type' => (string) ($row['party_type'] ?? ''),
                'party' => (string) ($row['party'] ?? ''),
                'cost_center' => (string) ($row['cost_center'] ?? ''),
                'project' => (string) ($row['project'] ?? ''),
                'finance_book' => (string) ($row['finance_book'] ?? ''),
                'exchange_rate' => (string) ($row['exchange_rate'] ?? '1'),
                'dimensions' => is_array($row['dimensions'] ?? null) ? $row['dimensions'] : (json_decode((string)($row['dimensions_json']??''),true)?:[]),
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
    if(is_array($rows)){foreach ($rows as &$row) $row['dimensions'] = json_decode((string)($row['dimensions_json'] ?? ''), true) ?: [];unset($row);}
    $header['rows'] = is_array($rows) ? $rows : [];
    return $header;
}

function yovel_admin_persist_journal_template(ADOConnection $db,array $company,array $admin,array $input):array
{
    yovel_admin_finance_journal_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$key=trim((string)($input['journal_template_key']??''))?:bx_uuid();if(!yovel_admin_is_uuid($key))throw new InvalidArgumentException('Journal Entry Template key is invalid.');
    $name=yovel_admin_finance_journal_text($input['template_name']??'',180,'Template name',true);$entryType=yovel_admin_status((string)($input['entry_type']??'GENERAL'),['GENERAL','OPENING','CLOSING','ADJUSTMENT','DEPRECIATION'],'GENERAL');$status=yovel_admin_status((string)($input['status']??'ACTIVE'),['ACTIVE','INACTIVE'],'ACTIVE');$remarks=yovel_admin_finance_journal_text($input['remarks']??'',1000,'Template remarks');$accounts=$input['accounts']??[];if(!is_array($accounts)||count($accounts)<2||count($accounts)>1000)throw new InvalidArgumentException('A Journal Entry Template must contain between 2 and 1000 accounts.');
    $normalized=[];foreach(array_values($accounts) as $index=>$row){if(!is_array($row))throw new InvalidArgumentException('Journal Entry Template accounts must be structured rows.');$accountKey=trim((string)($row['account_key']??''));if(!yovel_admin_is_uuid($accountKey)||(int)$db->GetOne("SELECT COUNT(*) FROM project_company_accounting_account WHERE company_key_hash=? AND account_key=? AND is_group=0 AND account_status='ACTIVE'",[$hash,$accountKey])!==1)throw new InvalidArgumentException('Journal Entry Template account must be active and belong to this company.');$partyType=strtoupper(yovel_admin_finance_journal_text($row['party_type']??'',80,'Template party type'));$party=yovel_admin_finance_journal_text($row['party']??'',180,'Template party');if(($partyType==='')!==($party===''))throw new InvalidArgumentException('Template party type and party reference must be supplied together.');$dimensions=$row['dimensions']??[];if(!is_array($dimensions))throw new InvalidArgumentException('Template dimensions must be structured values.');$currency=strtoupper(trim((string)($row['transaction_currency']??'')));if($currency!==''&&preg_match('/^[A-Z]{3,20}$/',$currency)!==1)throw new InvalidArgumentException('Template transaction currency is invalid.');$rate=yovel_admin_finance_money($row['exchange_rate']??'1',8);if(bccomp($rate,'0',8)!==1)throw new InvalidArgumentException('Template exchange rate must be positive.');$normalized[]=['key'=>yovel_admin_is_uuid((string)($row['journal_template_account_key']??''))?(string)$row['journal_template_account_key']:bx_uuid(),'line_no'=>$index+1,'account_key'=>$accountKey,'party_type'=>$partyType,'party'=>$party,'cost_center'=>yovel_admin_finance_journal_text($row['cost_center']??'',180,'Template Cost Center'),'project'=>yovel_admin_finance_journal_text($row['project']??'',180,'Template Project'),'finance_book'=>yovel_admin_finance_journal_text($row['finance_book']??'',180,'Template Finance Book'),'transaction_currency'=>$currency,'exchange_rate'=>$rate,'dimensions_json'=>$dimensions===[]?null:json_encode($dimensions,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)];}
    if($db->BeginTrans()===false)throw new RuntimeException('Journal Entry Template transaction could not start.');try{$existing=$db->GetRow('SELECT * FROM project_company_finance_journal_template WHERE company_key_hash=? AND journal_template_key=? FOR UPDATE',[$hash,$key]);if(!$existing&&(int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_journal_template WHERE journal_template_key=?',[$key])>0)throw new InvalidArgumentException('Journal Entry Template does not belong to this company.');yovel_admin_db_execute($db,"INSERT INTO project_company_finance_journal_template (journal_template_key,company_key,company_key_hash,template_name,entry_type,remarks,template_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE template_name=VALUES(template_name),entry_type=VALUES(entry_type),remarks=VALUES(remarks),template_status=VALUES(template_status),updated_by_admin_key=VALUES(updated_by_admin_key)",[$key,$companyKey,$hash,$name,$entryType,$remarks!==''?$remarks:null,$status,$adminKey,$adminKey],'Journal Entry Template save');yovel_admin_db_execute($db,'DELETE FROM project_company_finance_journal_template_account WHERE company_key_hash=? AND journal_template_key=?',[$hash,$key],'Journal Entry Template account reset');foreach($normalized as $row)yovel_admin_db_execute($db,'INSERT INTO project_company_finance_journal_template_account (journal_template_account_key,journal_template_key,company_key,company_key_hash,line_no,account_key,party_type,party,cost_center,project,finance_book,transaction_currency,exchange_rate,dimensions_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$row['key'],$key,$companyKey,$hash,$row['line_no'],$row['account_key'],$row['party_type']?:null,$row['party']?:null,$row['cost_center']?:null,$row['project']?:null,$row['finance_book']?:null,$row['transaction_currency']?:null,$row['exchange_rate'],$row['dimensions_json']],'Journal Entry Template account save');$saved=yovel_admin_journal_template($company,$key,false);if(!$saved||count($saved['accounts'])!==count($normalized)||(string)$saved['template_name']!==$name)throw new RuntimeException('Journal Entry Template read-back verification failed.');bx_audit($existing?'UPDATE':'CREATE','project_company_finance_journal_template',$key,['company_key'=>$companyKey,'template_name'=>$name,'account_count'=>count($normalized),'admin_key'=>$adminKey],'Company administrator saved a Journal Entry Template.');if($db->CommitTrans()===false)throw new RuntimeException('Journal Entry Template transaction could not commit.');return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_journal_template(array $company,string $key,bool $ensureSchema=true):?array
{
    if($ensureSchema)yovel_admin_finance_journal_schema();if(!yovel_admin_is_uuid($key))return null;$hash=(string)($company['company_key_hash']??'');$header=bx_db()->GetRow('SELECT * FROM project_company_finance_journal_template WHERE company_key_hash=? AND journal_template_key=?',[$hash,$key]);if(!$header)return null;$accounts=bx_db()->GetAll('SELECT * FROM project_company_finance_journal_template_account WHERE company_key_hash=? AND journal_template_key=? ORDER BY line_no',[$hash,$key])?:[];foreach($accounts as &$row)$row['dimensions']=json_decode((string)($row['dimensions_json']??''),true)?:[];unset($row);$header['accounts']=$accounts;return $header;
}

function yovel_admin_journal_templates(array $company):array
{
    yovel_admin_finance_journal_schema();$rows=bx_db()->GetAll("SELECT t.*,COUNT(a.x_id) account_count FROM project_company_finance_journal_template t LEFT JOIN project_company_finance_journal_template_account a ON a.company_key_hash=t.company_key_hash AND a.journal_template_key=t.journal_template_key WHERE t.company_key_hash=? GROUP BY t.journal_template_key ORDER BY t.template_name",[(string)($company['company_key_hash']??'')]);$rows=is_array($rows)?$rows:[];foreach($rows as &$row){$detail=yovel_admin_journal_template($company,(string)$row['journal_template_key'],false);$row['accounts']=$detail['accounts']??[];}unset($row);return $rows;
}

function yovel_admin_apply_journal_template(array $company,string $key):array
{
    $template=yovel_admin_journal_template($company,$key);if(!$template||(string)$template['template_status']!=='ACTIVE')throw new InvalidArgumentException('Active Journal Entry Template was not found for this company.');$rows=array_map(static fn(array $row):array=>['account_key'=>$row['account_key'],'party_type'=>$row['party_type']??'','party'=>$row['party']??'','cost_center'=>$row['cost_center']??'','project'=>$row['project']??'','finance_book'=>$row['finance_book']??'','transaction_currency'=>$row['transaction_currency']??'','exchange_rate'=>$row['exchange_rate']??'1','dimensions'=>$row['dimensions']??[],'debit'=>'0.000000','credit'=>'0.000000','transaction_debit'=>'0.000000','transaction_credit'=>'0.000000'],$template['accounts']);return ['template_key'=>$key,'template_name'=>$template['template_name'],'entry_type'=>$template['entry_type'],'remarks'=>$template['remarks']??'','rows'=>$rows];
}

function yovel_admin_amend_journal_entry(ADOConnection $db,array $company,array $admin,string $cancelledKey,array $overrides=[]):array
{
    yovel_admin_finance_journal_schema();yovel_admin_finance_scope($company,$admin);if(!yovel_admin_is_uuid($cancelledKey))throw new InvalidArgumentException('Journal amendment reference is invalid.');$original=yovel_admin_journal_entry($company,$cancelledKey);if(!$original||(string)$original['document_status']!=='CANCELLED')throw new InvalidArgumentException('Only a Cancelled Journal Entry can be amended.');$rows=array_map(static fn(array $row):array=>['account_key'=>$row['account_key'],'party_type'=>$row['party_type']??'','party'=>$row['party']??'','cost_center'=>$row['cost_center']??'','project'=>$row['project']??'','finance_book'=>$row['finance_book']??'','debit'=>$row['debit'],'credit'=>$row['credit'],'transaction_debit'=>$row['transaction_debit']??$row['debit'],'transaction_credit'=>$row['transaction_credit']??$row['credit'],'transaction_currency'=>$row['transaction_currency']??'','account_currency'=>$row['account_currency']??'','exchange_rate'=>$row['exchange_rate'],'remarks'=>$row['remarks']??'','dimensions'=>$row['dimensions']??[]],$original['rows']);$input=array_merge(['journal_entry_key'=>bx_uuid(),'journal_no'=>'','posting_date'=>$original['posting_date'],'entry_type'=>$original['entry_type'],'remarks'=>$original['remarks']??'','template_key'=>$original['template_key']??'','inter_company'=>$original['inter_company']??0,'counterparty_company_key'=>$original['counterparty_company_key']??'','counterparty_company_key_hash'=>$original['counterparty_company_key_hash']??'','inter_company_reference'=>($original['inter_company_reference']??'').'-A'.((int)$original['amendment_index']+1),'amends_journal_entry_key'=>$cancelledKey,'rows'=>$rows],$overrides);$input['amends_journal_entry_key']=$cancelledKey;$input['rows']=is_array($overrides['rows']??null)?$overrides['rows']:$rows;return yovel_admin_persist_journal_entry($db,$company,$admin,$input);
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
    $rows=is_array($rows)?$rows:[];foreach($rows as &$row){$detail=yovel_admin_journal_entry($company,(string)$row['journal_entry_key'],false);$row['rows']=$detail['rows']??[];}unset($row);return $rows;
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
            'transaction_debit' => (string) (is_array($_POST['row_transaction_debit'] ?? null) ? ($_POST['row_transaction_debit'][$index] ?? '') : ''),
            'transaction_credit' => (string) (is_array($_POST['row_transaction_credit'] ?? null) ? ($_POST['row_transaction_credit'][$index] ?? '') : ''),
            'transaction_currency' => (string) (is_array($_POST['row_transaction_currency'] ?? null) ? ($_POST['row_transaction_currency'][$index] ?? '') : ''),
            'exchange_rate' => (string) (is_array($_POST['row_exchange_rate'] ?? null) ? ($_POST['row_exchange_rate'][$index] ?? '1') : '1'),
            'party_type' => (string) (is_array($_POST['row_party_type'] ?? null) ? ($_POST['row_party_type'][$index] ?? '') : ''),
            'party' => (string) (is_array($_POST['row_party'] ?? null) ? ($_POST['row_party'][$index] ?? '') : ''),
            'cost_center' => (string) (is_array($_POST['row_cost_center'] ?? null) ? ($_POST['row_cost_center'][$index] ?? '') : ''),
            'project' => (string) (is_array($_POST['row_project'] ?? null) ? ($_POST['row_project'][$index] ?? '') : ''),
            'finance_book' => (string) (is_array($_POST['row_finance_book'] ?? null) ? ($_POST['row_finance_book'][$index] ?? '') : ''),
            'remarks' => (string) (is_array($_POST['row_remarks'] ?? null) ? ($_POST['row_remarks'][$index] ?? '') : ''),
        ];
    }
    return $rows;
}

function yovel_admin_save_finance_journal(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'save_finance_journal', static function () use ($company, $admin): string {
        $operation=strtolower(trim((string)($_POST['journal_operation']??'journal')));
        if($operation==='template'){
            $input=$_POST;$input['accounts']=yovel_admin_finance_journal_rows_from_post();$saved=yovel_admin_persist_journal_template(bx_db(),$company,$admin,$input);$GLOBALS['yovel_admin_saved_finance_document_key']=(string)$saved['journal_template_key'];return 'Journal Entry Template saved.';
        }
        if($operation==='amend'){
            $saved=yovel_admin_amend_journal_entry(bx_db(),$company,$admin,(string)($_POST['journal_entry_key']??''),['journal_no'=>(string)($_POST['journal_no']??''),'posting_date'=>(string)($_POST['posting_date']??date('Y-m-d'))]);$GLOBALS['yovel_admin_saved_finance_document_key']=(string)$saved['journal_entry_key'];return 'Journal amendment Draft created.';
        }
        if($operation==='ledger_health_monitor'){
            yovel_admin_persist_ledger_health_monitor(bx_db(),$company,$admin,$_POST);return 'Ledger Health Monitor saved.';
        }
        if($operation==='ledger_health'){
            $health=yovel_admin_general_ledger_health(bx_db(),$company,$admin,['persist'=>true,'monitor_key'=>(string)($_POST['monitor_key']??'')]);return 'Ledger Health scan completed with '.(int)$health['finding_count'].' finding(s).';
        }
        if($operation==='ledger_merge'){
            yovel_admin_merge_general_ledger_accounts(bx_db(),$company,$admin,$_POST);return 'Ledger accounts merged through an additive balance transfer.';
        }
        if($operation==='ledger_repost'){
            yovel_admin_repost_general_ledger_transaction(bx_db(),$company,$admin,$_POST);return 'General Ledger transaction reposted through additive reversal and replacement.';
        }
        $input = $_POST;
        $input['rows'] = yovel_admin_finance_journal_rows_from_post();
        $saved = yovel_admin_persist_journal_entry(bx_db(), $company, $admin, $input);
        $GLOBALS['yovel_admin_saved_finance_document_key'] = (string) $saved['journal_entry_key'];
        return 'Journal Entry Draft saved.';
    });
}

function yovel_admin_submit_finance_journal(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'submit_finance_journal', static function () use ($company, $admin): string {
        $saved = yovel_admin_submit_journal_entry(bx_db(), $company, $admin, (string) ($_POST['journal_entry_key'] ?? ''));
        $GLOBALS['yovel_admin_saved_finance_document_key'] = (string) $saved['journal_entry_key'];
        return 'Journal Entry submitted and posted to the General Ledger.';
    });
}

function yovel_admin_cancel_finance_journal(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'cancel_finance_journal', static function () use ($company, $admin): string {
        $saved = yovel_admin_cancel_journal_entry(bx_db(), $company, $admin, (string) ($_POST['journal_entry_key'] ?? ''), (string) ($_POST['cancellation_posting_date'] ?? date('Y-m-d')), (string) ($_POST['cancellation_reason'] ?? ''));
        $GLOBALS['yovel_admin_saved_finance_document_key'] = (string) $saved['journal_entry_key'];
        return 'Journal Entry cancelled with an additive General Ledger reversal.';
    });
}
