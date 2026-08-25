<?php
declare(strict_types=1);

function yovel_admin_finance_invoice_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_finance_supplier (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            supplier_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            supplier_code VARCHAR(80) NOT NULL,
            supplier_name VARCHAR(200) NOT NULL,
            supplier_tin VARCHAR(30) NULL,
            supplier_address VARCHAR(500) NULL,
            supplier_status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_supplier (company_key_hash, supplier_code),
            INDEX idx_project_company_finance_supplier_status (company_key_hash, supplier_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_invoice (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            document_type ENUM('SALES','PURCHASE') NOT NULL,
            invoice_no VARCHAR(120) NOT NULL,
            is_return TINYINT(1) NOT NULL DEFAULT 0,
            return_against_invoice_key CHAR(36) NULL,
            party_key CHAR(36) NULL,
            party_code VARCHAR(80) NULL,
            party_name VARCHAR(200) NOT NULL,
            party_tin VARCHAR(30) NULL,
            party_address VARCHAR(500) NULL,
            supplier_reference VARCHAR(120) NULL,
            posting_date DATE NOT NULL,
            due_date DATE NOT NULL,
            currency VARCHAR(20) NOT NULL DEFAULT 'PHP',
            exchange_rate DECIMAL(20,8) NOT NULL DEFAULT 1,
            party_account_key CHAR(36) NOT NULL,
            document_status ENUM('DRAFT','SUBMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            net_total DECIMAL(20,6) NOT NULL DEFAULT 0,
            taxable_sales DECIMAL(20,6) NOT NULL DEFAULT 0,
            government_sales DECIMAL(20,6) NOT NULL DEFAULT 0,
            zero_rated_sales DECIMAL(20,6) NOT NULL DEFAULT 0,
            exempt_sales DECIMAL(20,6) NOT NULL DEFAULT 0,
            out_of_scope_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            vat_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            creditable_input_vat DECIMAL(20,6) NOT NULL DEFAULT 0,
            non_creditable_input_vat DECIMAL(20,6) NOT NULL DEFAULT 0,
            creditable_vat_withheld DECIMAL(20,6) NOT NULL DEFAULT 0,
            grand_total DECIMAL(20,6) NOT NULL DEFAULT 0,
            outstanding_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            remarks VARCHAR(1000) NULL,
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
            UNIQUE KEY uq_project_company_finance_invoice_no (company_key_hash, document_type, invoice_no),
            INDEX idx_project_company_finance_invoice_party (company_key_hash, document_type, party_key, document_status),
            INDEX idx_project_company_finance_invoice_due (company_key_hash, document_type, document_status, due_date),
            INDEX idx_project_company_finance_invoice_return (company_key_hash, return_against_invoice_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_invoice_line (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_line_key CHAR(36) NOT NULL UNIQUE,
            invoice_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            line_no INT UNSIGNED NOT NULL,
            description VARCHAR(500) NOT NULL,
            quantity DECIMAL(20,6) NOT NULL,
            unit_amount DECIMAL(20,6) NOT NULL,
            discount_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            account_key CHAR(36) NOT NULL,
            cost_center VARCHAR(180) NULL,
            project VARCHAR(180) NULL,
            tax_code VARCHAR(80) NOT NULL,
            bir_classification VARCHAR(40) NOT NULL,
            taxable_base DECIMAL(20,6) NOT NULL DEFAULT 0,
            vat_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            line_total DECIMAL(20,6) NOT NULL DEFAULT 0,
            price_inclusive TINYINT(1) NOT NULL DEFAULT 0,
            tax_snapshot_json LONGTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_invoice_line (company_key_hash, invoice_key, line_no),
            INDEX idx_project_company_finance_invoice_line_account (company_key_hash, account_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_invoice_tax (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_tax_key CHAR(36) NOT NULL UNIQUE,
            invoice_key CHAR(36) NOT NULL,
            invoice_line_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            tax_code_key CHAR(36) NULL,
            tax_code VARCHAR(80) NOT NULL,
            tax_name VARCHAR(180) NOT NULL,
            tax_kind VARCHAR(40) NOT NULL,
            bir_classification VARCHAR(40) NOT NULL,
            rate DECIMAL(12,6) NOT NULL DEFAULT 0,
            taxable_base DECIMAL(20,6) NOT NULL DEFAULT 0,
            vat_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            creditable_input_vat DECIMAL(20,6) NOT NULL DEFAULT 0,
            non_creditable_input_vat DECIMAL(20,6) NOT NULL DEFAULT 0,
            creditable_vat_withheld DECIMAL(20,6) NOT NULL DEFAULT 0,
            tax_snapshot_json LONGTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_company_finance_invoice_tax_report (company_key_hash, bir_classification, invoice_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_payment_term (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_term_key CHAR(36) NOT NULL UNIQUE,
            invoice_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            term_no INT UNSIGNED NOT NULL,
            due_date DATE NOT NULL,
            due_amount DECIMAL(20,6) NOT NULL,
            description VARCHAR(180) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_payment_term (company_key_hash, invoice_key, term_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Finance Invoice schema update');
    }
}

function yovel_admin_finance_invoice_type(string $type): string
{
    $type = strtoupper(trim($type));
    if (!in_array($type, ['SALES', 'PURCHASE'], true)) {
        throw new InvalidArgumentException('Finance Invoice type is invalid.');
    }
    return $type;
}

function yovel_admin_finance_invoice_party(ADOConnection $db, array $company, string $type, array $input): array
{
    $hash = (string) $company['company_key_hash'];
    $partyKey = trim((string) ($input['party_key'] ?? ''));
    if (!yovel_admin_is_uuid($partyKey)) {
        throw new InvalidArgumentException(($type === 'SALES' ? 'Customer' : 'Supplier') . ' is required.');
    }
    if ($type === 'SALES') {
        $row = $db->GetRow("SELECT customer_key AS party_key, customer_code AS party_code, customer_name AS party_name FROM project_company_sales_customer WHERE company_key_hash=? AND customer_key=? AND customer_status='ACTIVE' LIMIT 1", [$hash, $partyKey]);
    } else {
        $row = $db->GetRow("SELECT supplier_key AS party_key, supplier_code AS party_code, supplier_name AS party_name, supplier_tin AS party_tin, supplier_address AS party_address FROM project_company_finance_supplier WHERE company_key_hash=? AND supplier_key=? AND supplier_status='ACTIVE' LIMIT 1", [$hash, $partyKey]);
    }
    if (!is_array($row) || $row === []) {
        throw new InvalidArgumentException(($type === 'SALES' ? 'Customer' : 'Supplier') . ' must be active and belong to this company.');
    }
    $row['party_tin'] = trim((string) ($input['party_tin'] ?? ($row['party_tin'] ?? '')));
    $row['party_address'] = trim((string) ($input['party_address'] ?? ($row['party_address'] ?? '')));
    return $row;
}

function yovel_admin_finance_invoice_calculate_lines(ADOConnection $db, array $company, string $type, string $postingDate, bool $isReturn, array $lines): array
{
    if ($lines === [] || count($lines) > 1000) {
        throw new InvalidArgumentException('Finance Invoice must contain between 1 and 1000 lines.');
    }
    $hash = (string) $company['company_key_hash'];
    $prepared = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            throw new InvalidArgumentException('Finance Invoice lines must be structured rows.');
        }
        $description = trim((string) ($line['description'] ?? ''));
        if ($description === '' || strlen($description) > 500) {
            throw new InvalidArgumentException('Every Finance Invoice line requires a description.');
        }
        $accountKey = trim((string) ($line['account_key'] ?? ''));
        $root = $type === 'SALES' ? ['INCOME'] : ['EXPENSE', 'ASSET'];
        if (yovel_admin_finance_account_key($db, $hash, $accountKey, 'Invoice line account', $root) === null) {
            throw new InvalidArgumentException('Every Finance Invoice line requires a posting account.');
        }
        $taxInput = $line['tax_meta'] ?? null;
        if (!is_array($taxInput)) {
            $taxInput = yovel_admin_ph_vat_effective_code($company, (string) ($line['tax_code'] ?? 'OUT_OF_SCOPE'), $postingDate);
        }
        $prepared[] = [
            'description' => $description,
            'quantity' => $line['quantity'] ?? '1',
            'unit_amount' => $line['unit_amount'] ?? $line['rate'] ?? '0',
            'discount_amount' => $line['discount_amount'] ?? '0',
            'tax_meta' => $taxInput,
            'price_inclusive' => !empty($line['price_inclusive']),
            'account_key' => $accountKey,
            'cost_center' => substr(trim((string) ($line['cost_center'] ?? '')), 0, 180),
            'project' => substr(trim((string) ($line['project'] ?? '')), 0, 180),
        ];
    }
    $calculated = yovel_admin_ph_vat_calculate_document($prepared, $isReturn);
    foreach ($calculated['lines'] as $index => &$line) {
        $line['invoice_line_key'] = bx_uuid();
        $line['account_key'] = $prepared[$index]['account_key'];
        $line['cost_center'] = $prepared[$index]['cost_center'];
        $line['project'] = $prepared[$index]['project'];
    }
    unset($line);
    return $calculated;
}

function yovel_admin_persist_finance_invoice(ADOConnection $db, array $company, array $admin, string $type, array $input): array
{
    yovel_admin_finance_core_schema();
    yovel_admin_finance_invoice_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $type = yovel_admin_finance_invoice_type($type);
    $key = trim((string) ($input['invoice_key'] ?? ''));
    if ($key !== '' && !yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Finance Invoice key is invalid.');
    }
    $postingDate = yovel_admin_optional_date((string) ($input['posting_date'] ?? ''), 'Invoice posting date');
    $dueDate = yovel_admin_optional_date((string) ($input['due_date'] ?? ''), 'Invoice due date');
    if ($postingDate === '' || $dueDate === '' || $dueDate < $postingDate) {
        throw new InvalidArgumentException('Invoice posting and due dates are required and must be valid.');
    }
    $invoiceNo = substr(trim((string) ($input['invoice_no'] ?? '')), 0, 120);
    if ($invoiceNo === '') {
        $invoiceNo = yovel_admin_finance_next_number($db, $company, $admin, $type . '_INVOICE', $type === 'SALES' ? 'SI-' : 'PI-', (int) substr($postingDate, 0, 4));
    }
    $party = yovel_admin_finance_invoice_party($db, $company, $type, $input);
    $settings = yovel_admin_finance_settings($company, $admin);
    $partyAccountField = $type === 'SALES' ? 'default_receivable_account_key' : 'default_payable_account_key';
    $partyAccount = yovel_admin_finance_account_key($db, $hash, $input['party_account_key'] ?? ($settings[$partyAccountField] ?? ''), $type === 'SALES' ? 'Receivable account' : 'Payable account', $type === 'SALES' ? ['ASSET'] : ['LIABILITY'], [$type === 'SALES' ? 'Receivable' : 'Payable']);
    if ($partyAccount === null) {
        throw new InvalidArgumentException(($type === 'SALES' ? 'Receivable' : 'Payable') . ' account is required.');
    }
    $currency = strtoupper(trim((string) ($input['currency'] ?? ($settings['base_currency'] ?? 'PHP'))));
    $exchangeRate = yovel_admin_finance_money($input['exchange_rate'] ?? '1', 8);
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1 || bccomp($exchangeRate, '0', 8) !== 1) {
        throw new InvalidArgumentException('Invoice currency or exchange rate is invalid.');
    }
    $isReturn = !empty($input['is_return']);
    $returnAgainst = trim((string) ($input['return_against_invoice_key'] ?? ''));
    if ($isReturn && !yovel_admin_is_uuid($returnAgainst)) {
        throw new InvalidArgumentException('A return must reference its original Finance Invoice.');
    }
    $supplierReference = $type === 'PURCHASE' ? substr(trim((string) ($input['supplier_reference'] ?? '')), 0, 120) : '';
    if ($type === 'PURCHASE' && $supplierReference === '') {
        throw new InvalidArgumentException('Supplier reference is required.');
    }
    $calculated = yovel_admin_finance_invoice_calculate_lines($db, $company, $type, $postingDate, $isReturn, is_array($input['lines'] ?? null) ? $input['lines'] : []);
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance Invoice transaction could not start.');
    }
    try {
        $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? FOR UPDATE', [$hash, $key]) : $db->GetRow('SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND document_type=? AND invoice_no=? FOR UPDATE', [$hash, $type, $invoiceNo]);
        if (is_array($existing) && $existing !== [] && (string) $existing['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Submitted or Cancelled Finance Invoices are immutable.');
        }
        if ($type === 'PURCHASE' && (int) $db->GetOne("SELECT COUNT(*) FROM project_company_finance_invoice WHERE company_key_hash=? AND document_type='PURCHASE' AND party_key=? AND supplier_reference=? AND document_status <> 'CANCELLED' AND invoice_key <> ?", [$hash, $party['party_key'], $supplierReference, (string) ($existing['invoice_key'] ?? $key)]) > 0) {
            throw new InvalidArgumentException('Supplier reference already exists for this Supplier.');
        }
        $key = is_array($existing) && $existing !== [] ? (string) $existing['invoice_key'] : ($key !== '' ? $key : bx_uuid());
        $fields = ['net_amount'=>'net_total','taxable_sales'=>'taxable_sales','government_sales'=>'government_sales','zero_rated_sales'=>'zero_rated_sales','exempt_sales'=>'exempt_sales','out_of_scope_amount'=>'out_of_scope_amount','vat_amount'=>'vat_amount','creditable_input_vat'=>'creditable_input_vat','non_creditable_input_vat'=>'non_creditable_input_vat','creditable_vat_withheld'=>'creditable_vat_withheld','grand_total'=>'grand_total'];
        $values = [];
        foreach ($fields as $source => $target) { $values[$target] = $calculated[$source]; }
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_invoice (invoice_key,company_key,company_key_hash,document_type,invoice_no,is_return,return_against_invoice_key,party_key,party_code,party_name,party_tin,party_address,supplier_reference,posting_date,due_date,currency,exchange_rate,party_account_key,document_status,net_total,taxable_sales,government_sales,zero_rated_sales,exempt_sales,out_of_scope_amount,vat_amount,creditable_input_vat,non_creditable_input_vat,creditable_vat_withheld,grand_total,outstanding_amount,remarks,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'DRAFT',?,?,?,?,?,?,?,?,?,?,?,0,?,?,?) ON DUPLICATE KEY UPDATE posting_date=VALUES(posting_date),due_date=VALUES(due_date),party_key=VALUES(party_key),party_code=VALUES(party_code),party_name=VALUES(party_name),party_tin=VALUES(party_tin),party_address=VALUES(party_address),supplier_reference=VALUES(supplier_reference),currency=VALUES(currency),exchange_rate=VALUES(exchange_rate),party_account_key=VALUES(party_account_key),net_total=VALUES(net_total),taxable_sales=VALUES(taxable_sales),government_sales=VALUES(government_sales),zero_rated_sales=VALUES(zero_rated_sales),exempt_sales=VALUES(exempt_sales),out_of_scope_amount=VALUES(out_of_scope_amount),vat_amount=VALUES(vat_amount),creditable_input_vat=VALUES(creditable_input_vat),non_creditable_input_vat=VALUES(non_creditable_input_vat),creditable_vat_withheld=VALUES(creditable_vat_withheld),grand_total=VALUES(grand_total),remarks=VALUES(remarks),updated_by_admin_key=VALUES(updated_by_admin_key)", [$key,$companyKey,$hash,$type,$invoiceNo,$isReturn?1:0,$returnAgainst!==''?$returnAgainst:null,$party['party_key'],$party['party_code'],$party['party_name'],$party['party_tin']!==''?$party['party_tin']:null,$party['party_address']!==''?$party['party_address']:null,$supplierReference!==''?$supplierReference:null,$postingDate,$dueDate,$currency,$exchangeRate,$partyAccount,$values['net_total'],$values['taxable_sales'],$values['government_sales'],$values['zero_rated_sales'],$values['exempt_sales'],$values['out_of_scope_amount'],$values['vat_amount'],$values['creditable_input_vat'],$values['non_creditable_input_vat'],$values['creditable_vat_withheld'],$values['grand_total'],substr(trim((string)($input['remarks']??'')),0,1000)?:null,$adminKey,$adminKey], 'Finance Invoice save');
        foreach (['project_company_finance_invoice_tax','project_company_finance_invoice_line','project_company_finance_payment_term'] as $table) {
            yovel_admin_db_execute($db, "DELETE FROM {$table} WHERE company_key_hash=? AND invoice_key=?", [$hash,$key], 'Finance Invoice detail reset');
        }
        foreach ($calculated['lines'] as $line) {
            $snapshot = json_encode($line['tax_snapshot'], JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            yovel_admin_db_execute($db, "INSERT INTO project_company_finance_invoice_line (invoice_line_key,invoice_key,company_key,company_key_hash,line_no,description,quantity,unit_amount,discount_amount,account_key,cost_center,project,tax_code,bir_classification,taxable_base,vat_amount,line_total,price_inclusive,tax_snapshot_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [$line['invoice_line_key'],$key,$companyKey,$hash,$line['line_no'],$line['description'],$line['quantity'],$line['unit_amount'],$line['discount_amount'],$line['account_key'],$line['cost_center']?:null,$line['project']?:null,$line['tax_code'],$line['bir_classification'],$line['taxable_base'],$line['vat_amount'],$line['line_total'],$line['price_inclusive']?1:0,$snapshot], 'Finance Invoice line save');
            $meta=$line['tax_snapshot'];
            yovel_admin_db_execute($db, "INSERT INTO project_company_finance_invoice_tax (invoice_tax_key,invoice_key,invoice_line_key,company_key,company_key_hash,tax_code_key,tax_code,tax_name,tax_kind,bir_classification,rate,taxable_base,vat_amount,creditable_input_vat,non_creditable_input_vat,creditable_vat_withheld,tax_snapshot_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [bx_uuid(),$key,$line['invoice_line_key'],$companyKey,$hash,$meta['tax_code_key']?:null,$meta['tax_code'],$meta['tax_name'],$meta['tax_kind'],$meta['bir_classification'],$meta['rate'],$line['taxable_base'],$line['vat_amount'],$line['creditable_input_vat'],$line['non_creditable_input_vat'],$line['creditable_vat_withheld'],$snapshot], 'Finance Invoice tax snapshot save');
        }
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_payment_term (payment_term_key,invoice_key,company_key,company_key_hash,term_no,due_date,due_amount,description) VALUES (?,?,?,?,1,?,?,'Full balance')", [bx_uuid(),$key,$companyKey,$hash,$dueDate,$calculated['grand_total']], 'Finance Invoice payment term save');
        $saved=yovel_admin_finance_invoice($company,$key,false);
        if(!is_array($saved)||count($saved['lines'])!==count($calculated['lines'])||(string)$saved['grand_total']!==$calculated['grand_total']) throw new RuntimeException('Finance Invoice read-back verification failed.');
        bx_audit(is_array($existing)&&$existing!==[]?'UPDATE':'CREATE','project_company_finance_invoice',$key,['company_key'=>$companyKey,'document_type'=>$type,'invoice_no'=>$invoiceNo,'grand_total'=>$calculated['grand_total'],'admin_key'=>$adminKey],'Company administrator saved a Finance Invoice Draft.');
        if($db->CommitTrans()===false) throw new RuntimeException('Finance Invoice transaction could not commit.');
        return $saved;
    } catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_finance_invoice_gl_entries(array $header,array $lines,array $settings): array
{
    $type=(string)$header['document_type'];$return=(int)$header['is_return']===1;$entries=[];$partyAmount=yovel_admin_finance_money(abs((float)$header['grand_total']));
    $party=['account_key'=>(string)$header['party_account_key'],'party_type'=>$type==='SALES'?'CUSTOMER':'SUPPLIER','party'=>(string)$header['party_name']];
    if(($type==='SALES'&&!$return)||($type==='PURCHASE'&&$return)){$entries[]=array_merge($party,['debit'=>$partyAmount,'credit'=>'0']);}else{$entries[]=array_merge($party,['debit'=>'0','credit'=>$partyAmount]);}
    foreach($lines as $line){$net=yovel_admin_finance_money(abs((float)$line['line_total'])-abs((float)$line['vat_amount']));$base=['account_key'=>(string)$line['account_key'],'cost_center'=>(string)($line['cost_center']??''),'project'=>(string)($line['project']??''),'remarks'=>(string)$line['description']];if(($type==='SALES'&&!$return)||($type==='PURCHASE'&&$return))$entries[]=array_merge($base,['debit'=>'0','credit'=>$net]);else $entries[]=array_merge($base,['debit'=>$net,'credit'=>'0']);}
    $vat=yovel_admin_finance_money(abs((float)$header['vat_amount']));
    if(bccomp($vat,'0',6)===1){$vatKey=(string)($settings[$type==='SALES'?'default_output_vat_account_key':'default_input_vat_account_key']??'');if(!yovel_admin_is_uuid($vatKey))throw new InvalidArgumentException('Default VAT account is required before invoice submission.');if(($type==='SALES'&&!$return)||($type==='PURCHASE'&&$return))$entries[]=['account_key'=>$vatKey,'debit'=>'0','credit'=>$vat];else $entries[]=['account_key'=>$vatKey,'debit'=>$vat,'credit'=>'0'];}
    return $entries;
}

function yovel_admin_submit_finance_invoice(ADOConnection $db,array $company,array $admin,string $type,string $key):array
{
    yovel_admin_finance_core_schema();yovel_admin_general_ledger_schema();yovel_admin_finance_invoice_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$type=yovel_admin_finance_invoice_type($type);if(!yovel_admin_is_uuid($key))throw new InvalidArgumentException('Finance Invoice key is invalid.');$preview=yovel_admin_finance_invoice($company,$key);if(!$preview||(string)$preview['document_type']!==$type)throw new InvalidArgumentException('Finance Invoice was not found for this company.');yovel_admin_finance_assert_open_period($company,(string)$preview['posting_date'],false);
    if($db->BeginTrans()===false)throw new RuntimeException('Invoice submission transaction could not start.');try{$header=$db->GetRow('SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? FOR UPDATE',[$hash,$key]);if(!$header||(string)$header['document_status']!=='DRAFT')throw new InvalidArgumentException('Only a Draft Finance Invoice can be submitted.');$lines=$db->GetAll('SELECT * FROM project_company_finance_invoice_line WHERE company_key_hash=? AND invoice_key=? ORDER BY line_no',[$hash,$key]);$posted=yovel_admin_post_general_ledger_transaction($db,$company,$admin,['posting_date'=>$header['posting_date'],'voucher_type'=>$type.'_INVOICE','voucher_no'=>$header['invoice_no'],'source_module'=>$type.'_INVOICE','source_record_key'=>$key,'remarks'=>$header['remarks']??'','entries'=>yovel_admin_finance_invoice_gl_entries($header,$lines,yovel_admin_finance_settings($company,$admin))],false,false);yovel_admin_db_execute($db,"UPDATE project_company_finance_invoice SET document_status='SUBMITTED',outstanding_amount=grand_total,gl_transaction_key=?,submitted_by_admin_key=?,submitted_at=CURRENT_TIMESTAMP,updated_by_admin_key=? WHERE company_key_hash=? AND invoice_key=? AND document_status='DRAFT'",[$posted['transaction_key'],$adminKey,$adminKey,$hash,$key],'Finance Invoice submission');$saved=yovel_admin_finance_invoice($company,$key,false);if(!$saved||(string)$saved['document_status']!=='SUBMITTED'||(string)$saved['gl_transaction_key']!==(string)$posted['transaction_key'])throw new RuntimeException('Finance Invoice submission read-back verification failed.');bx_audit('SUBMIT','project_company_finance_invoice',$key,['company_key'=>$companyKey,'invoice_no'=>$header['invoice_no'],'gl_transaction_key'=>$posted['transaction_key'],'admin_key'=>$adminKey],'Company administrator submitted a Finance Invoice.');if($db->CommitTrans()===false)throw new RuntimeException('Invoice submission transaction could not commit.');return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_cancel_finance_invoice(ADOConnection $db,array $company,array $admin,string $type,string $key,string $postingDate,string $reason):array
{
    yovel_admin_finance_core_schema();yovel_admin_general_ledger_schema();yovel_admin_finance_invoice_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$type=yovel_admin_finance_invoice_type($type);$postingDate=yovel_admin_optional_date($postingDate,'Cancellation posting date');$reason=trim($reason);if(!yovel_admin_is_uuid($key)||$postingDate===''||$reason==='')throw new InvalidArgumentException('Invoice cancellation reference, date, and reason are required.');yovel_admin_finance_assert_open_period($company,$postingDate,false);if($db->BeginTrans()===false)throw new RuntimeException('Invoice cancellation transaction could not start.');try{$header=$db->GetRow('SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? AND document_type=? FOR UPDATE',[$hash,$key,$type]);if(!$header||(string)$header['document_status']!=='SUBMITTED')throw new InvalidArgumentException('Only a Submitted Finance Invoice can be cancelled.');if(bccomp((string)$header['outstanding_amount'],(string)$header['grand_total'],6)!==0)throw new InvalidArgumentException('An allocated Finance Invoice cannot be cancelled until its payments are cancelled.');$rev=yovel_admin_reverse_general_ledger_transaction($db,$company,$admin,(string)$header['gl_transaction_key'],$postingDate,$reason,false,false);yovel_admin_db_execute($db,"UPDATE project_company_finance_invoice SET document_status='CANCELLED',outstanding_amount=0,reversal_transaction_key=?,cancelled_by_admin_key=?,cancelled_at=CURRENT_TIMESTAMP,updated_by_admin_key=? WHERE company_key_hash=? AND invoice_key=?",[$rev['transaction_key'],$adminKey,$adminKey,$hash,$key],'Finance Invoice cancellation');$saved=yovel_admin_finance_invoice($company,$key,false);if(!$saved||(string)$saved['document_status']!=='CANCELLED'||(string)$saved['outstanding_amount']!=='0.000000')throw new RuntimeException('Finance Invoice cancellation read-back verification failed.');bx_audit('CANCEL','project_company_finance_invoice',$key,['company_key'=>$companyKey,'invoice_no'=>$header['invoice_no'],'reversal_transaction_key'=>$rev['transaction_key'],'admin_key'=>$adminKey],'Company administrator cancelled a Finance Invoice through an additive reversal.');if($db->CommitTrans()===false)throw new RuntimeException('Invoice cancellation transaction could not commit.');return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_create_finance_return(ADOConnection $db,array $company,array $admin,string $type,string $originalKey,array $overrides=[]):array
{
    $original=yovel_admin_finance_invoice($company,$originalKey);if(!$original||(string)$original['document_status']!=='SUBMITTED'||(int)$original['is_return']===1)throw new InvalidArgumentException('Only a Submitted original Finance Invoice can create a return.');$input=['invoice_key'=>$overrides['invoice_key']??'','invoice_no'=>$overrides['invoice_no']??'','posting_date'=>$overrides['posting_date']??date('Y-m-d'),'due_date'=>$overrides['due_date']??($overrides['posting_date']??date('Y-m-d')),'party_key'=>$original['party_key'],'party_tin'=>$original['party_tin'],'party_address'=>$original['party_address'],'supplier_reference'=>$type==='PURCHASE'?($overrides['supplier_reference']??('RET-'.$original['supplier_reference'])):'','currency'=>$original['currency'],'exchange_rate'=>$original['exchange_rate'],'party_account_key'=>$original['party_account_key'],'is_return'=>1,'return_against_invoice_key'=>$originalKey,'remarks'=>$overrides['remarks']??('Return against '.$original['invoice_no']),'lines'=>array_map(static fn(array $line):array=>['description'=>$line['description'],'quantity'=>$line['quantity'],'unit_amount'=>$line['unit_amount'],'discount_amount'=>$line['discount_amount'],'account_key'=>$line['account_key'],'cost_center'=>$line['cost_center'],'project'=>$line['project'],'tax_meta'=>json_decode((string)$line['tax_snapshot_json'],true,512,JSON_THROW_ON_ERROR),'price_inclusive'=>(int)$line['price_inclusive']===1],$original['lines'])];return yovel_admin_persist_finance_invoice($db,$company,$admin,$type,$input);
}

function yovel_admin_finance_invoice(array $company,string $key,bool $ensureSchema=true):?array
{
    if($ensureSchema)yovel_admin_finance_invoice_schema();if(!yovel_admin_is_uuid($key))return null;$hash=(string)($company['company_key_hash']??'');$header=bx_db()->GetRow('SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? LIMIT 1',[$hash,$key]);if(!$header)return null;$lines=bx_db()->GetAll('SELECT * FROM project_company_finance_invoice_line WHERE company_key_hash=? AND invoice_key=? ORDER BY line_no',[$hash,$key]);$terms=bx_db()->GetAll('SELECT * FROM project_company_finance_payment_term WHERE company_key_hash=? AND invoice_key=? ORDER BY term_no',[$hash,$key]);$header['lines']=is_array($lines)?$lines:[];$header['payment_terms']=is_array($terms)?$terms:[];return $header;
}

function yovel_admin_finance_invoices(array $company,string $type,array $filters=[]):array
{
    yovel_admin_finance_invoice_schema();$type=yovel_admin_finance_invoice_type($type);$params=[(string)($company['company_key_hash']??''),$type];$where=['company_key_hash=?','document_type=?'];$status=strtoupper(trim((string)($filters['document_status']??'')));if(in_array($status,['DRAFT','SUBMITTED','CANCELLED'],true)){$where[]='document_status=?';$params[]=$status;}$rows=bx_db()->GetAll('SELECT * FROM project_company_finance_invoice WHERE '.implode(' AND ',$where).' ORDER BY posting_date DESC,x_id DESC LIMIT 1000',$params);return is_array($rows)?$rows:[];
}

function yovel_admin_invoice_outstanding(array $company,string $key):string
{
    if(!yovel_admin_is_uuid($key))return '0.000000';$value=bx_db()->GetOne("SELECT outstanding_amount FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? AND document_status='SUBMITTED'",[(string)($company['company_key_hash']??''),$key]);return yovel_admin_finance_money($value??'0');
}

function yovel_admin_finance_aging(array $company,string $type,string $asOf):array
{
    yovel_admin_finance_invoice_schema();$type=yovel_admin_finance_invoice_type($type);$asOf=yovel_admin_optional_date($asOf,'Aging date');if($asOf==='')throw new InvalidArgumentException('Aging date is required.');$rows=bx_db()->GetAll("SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND document_type=? AND document_status='SUBMITTED' AND posting_date<=? AND outstanding_amount<>0 ORDER BY due_date,invoice_no",[(string)$company['company_key_hash'],$type,$asOf]);$total='0.000000';$buckets=['current'=>'0.000000','1_30'=>'0.000000','31_60'=>'0.000000','61_90'=>'0.000000','over_90'=>'0.000000'];foreach($rows as &$row){$days=(int)(new DateTimeImmutable((string)$row['due_date']))->diff(new DateTimeImmutable($asOf))->format('%r%a');$bucket=$days<=0?'current':($days<=30?'1_30':($days<=60?'31_60':($days<=90?'61_90':'over_90')));$row['age_days']=max(0,$days);$row['aging_bucket']=$bucket;$total=bcadd($total,(string)$row['outstanding_amount'],6);$buckets[$bucket]=bcadd($buckets[$bucket],(string)$row['outstanding_amount'],6);}unset($row);return ['as_of'=>$asOf,'rows'=>$rows,'total_outstanding'=>yovel_admin_finance_money($total),'buckets'=>$buckets];
}
function yovel_admin_receivable_aging(array $company,string $asOf):array{return yovel_admin_finance_aging($company,'SALES',$asOf);}
function yovel_admin_payable_aging(array $company,string $asOf):array{return yovel_admin_finance_aging($company,'PURCHASE',$asOf);}

function yovel_admin_finance_invoice_lines_from_post():array{$json=trim((string)($_POST['lines_json']??''));if($json==='')return []; $lines=json_decode($json,true,512,JSON_THROW_ON_ERROR);if(!is_array($lines))throw new InvalidArgumentException('Invoice lines JSON is invalid.');return $lines;}
function yovel_admin_save_finance_invoice(array $company,array $admin):string{$input=$_POST;$input['lines']=yovel_admin_finance_invoice_lines_from_post();$type=(string)($_POST['document_type']??'SALES');$saved=yovel_admin_persist_finance_invoice(bx_db(),$company,$admin,$type,$input);$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['invoice_key'];return ucfirst(strtolower($type)).' Invoice Draft saved.';}
function yovel_admin_submit_invoice_action(array $company,array $admin):string{$type=(string)($_POST['document_type']??'SALES');$saved=yovel_admin_submit_finance_invoice(bx_db(),$company,$admin,$type,(string)($_POST['invoice_key']??''));$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['invoice_key'];return ucfirst(strtolower($type)).' Invoice submitted and posted.';}
function yovel_admin_cancel_invoice_action(array $company,array $admin):string{$type=(string)($_POST['document_type']??'SALES');$saved=yovel_admin_cancel_finance_invoice(bx_db(),$company,$admin,$type,(string)($_POST['invoice_key']??''),(string)($_POST['cancellation_posting_date']??date('Y-m-d')),(string)($_POST['cancellation_reason']??''));$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['invoice_key'];return ucfirst(strtolower($type)).' Invoice cancelled with a reversal.';}
