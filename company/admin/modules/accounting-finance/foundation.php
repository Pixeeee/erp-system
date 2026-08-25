<?php
declare(strict_types=1);

function yovel_admin_finance_transaction(ADOConnection $db, callable $operation): mixed
{
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance transaction could not start.');
    }
    try {
        $result = $operation();
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance transaction could not commit.');
        }
        return $result;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_finance_philippine_chart_rows(): array
{
    return [
        ['1000','Assets','', 'ASSET',1,''],
        ['1100','Cash and Cash Equivalents','1000','ASSET',1,'Cash'],
        ['1110','Cash on Hand','1100','ASSET',0,'Cash'],
        ['1120','Petty Cash Fund','1100','ASSET',0,'Cash'],
        ['1200','Bank Accounts','1000','ASSET',1,'Bank'],
        ['1210','Operating Bank Account','1200','ASSET',0,'Bank'],
        ['1300','Trade and Other Receivables','1000','ASSET',1,''],
        ['1310','Accounts Receivable - Trade','1300','ASSET',0,'Receivable'],
        ['1400','Inventories','1000','ASSET',1,''],
        ['1410','Merchandise Inventory','1400','ASSET',0,'Stock'],
        ['1500','Input VAT','1000','ASSET',1,'Tax'],
        ['1510','Input VAT - Goods','1500','ASSET',0,'Tax'],
        ['1520','Input VAT - Services','1500','ASSET',0,'Tax'],
        ['1600','Property, Plant and Equipment','1000','ASSET',1,'Fixed Asset'],
        ['1610','Office and Production Equipment','1600','ASSET',0,'Fixed Asset'],
        ['1690','Accumulated Depreciation','1600','ASSET',0,'Accumulated Depreciation'],
        ['2000','Liabilities','', 'LIABILITY',1,''],
        ['2100','Trade and Other Payables','2000','LIABILITY',1,''],
        ['2110','Accounts Payable - Trade','2100','LIABILITY',0,'Payable'],
        ['2200','Taxes Payable','2000','LIABILITY',1,'Tax'],
        ['2210','Output VAT','2200','LIABILITY',0,'Tax'],
        ['2220','Expanded Withholding Tax Payable','2200','LIABILITY',0,'Tax'],
        ['2300','Statutory Payables','2000','LIABILITY',1,''],
        ['2310','SSS Contributions Payable','2300','LIABILITY',0,''],
        ['2320','PhilHealth Contributions Payable','2300','LIABILITY',0,''],
        ['2330','Pag-IBIG Contributions Payable','2300','LIABILITY',0,''],
        ['3000','Equity','', 'EQUITY',1,'Equity'],
        ['3100','Owner Capital','3000','EQUITY',0,'Equity'],
        ['3200','Retained Earnings','3000','EQUITY',0,'Equity'],
        ['4000','Income','', 'INCOME',1,'Income Account'],
        ['4100','Sales Revenue','4000','INCOME',0,'Income Account'],
        ['4200','Other Operating Income','4000','INCOME',0,'Indirect Income'],
        ['4300','Foreign Exchange Gain','4000','INCOME',0,'Indirect Income'],
        ['5000','Expenses','', 'EXPENSE',1,'Expense Account'],
        ['5100','Cost of Sales','5000','EXPENSE',0,'Cost of Goods Sold'],
        ['5200','Operating Expenses','5000','EXPENSE',1,'Expense Account'],
        ['5210','Salaries and Wages','5200','EXPENSE',0,'Expense Account'],
        ['5220','Rent Expense','5200','EXPENSE',0,'Expense Account'],
        ['5230','Utilities Expense','5200','EXPENSE',0,'Expense Account'],
        ['5240','Professional Fees','5200','EXPENSE',0,'Expense Account'],
        ['5250','Depreciation Expense','5200','EXPENSE',0,'Depreciation'],
        ['5260','Bank Charges','5200','EXPENSE',0,'Expense Account'],
        ['5270','Foreign Exchange Loss','5200','EXPENSE',0,'Indirect Expense'],
    ];
}

function yovel_admin_finance_chart_template_definition(): array
{
    $rows = array_map(static fn (array $row): array => [
        'account_code'=>$row[0], 'account_number'=>$row[0], 'account_name'=>$row[1],
        'parent_account_code'=>$row[2], 'root_type'=>$row[3], 'is_group'=>$row[4], 'account_type'=>$row[5],
    ], yovel_admin_finance_philippine_chart_rows());
    $encoded = json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    return ['template_code'=>'PH_STANDARD','template_name'=>'Philippine Standard Chart','country'=>'Philippines','template_version'=>1,'rows'=>$rows,'schema_json'=>$encoded,'checksum'=>hash('sha256',$encoded)];
}

function yovel_admin_finance_foundation_schema(): void
{
    yovel_admin_accounting_finance_schema();
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_finance_account_category (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_category_key CHAR(36) NOT NULL UNIQUE,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,account_category_name VARCHAR(180) NOT NULL,root_type ENUM('ASSET','LIABILITY','INCOME','EXPENSE','EQUITY') NULL,description TEXT NULL,status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',created_by_admin_key CHAR(36) NULL,updated_by_admin_key CHAR(36) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_account_category(company_key_hash,account_category_name),INDEX idx_finance_account_category_status(company_key_hash,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_account_closing_balance (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_closing_balance_key CHAR(36) NOT NULL UNIQUE,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,closing_date DATE NOT NULL,account_key CHAR(36) NOT NULL,cost_center_key CHAR(36) NULL,finance_book_key CHAR(36) NULL,debit DECIMAL(20,6) NOT NULL DEFAULT 0,credit DECIMAL(20,6) NOT NULL DEFAULT 0,account_currency VARCHAR(20) NOT NULL,debit_in_account_currency DECIMAL(20,6) NOT NULL DEFAULT 0,credit_in_account_currency DECIMAL(20,6) NOT NULL DEFAULT 0,reporting_currency_exchange_rate DECIMAL(20,9) NOT NULL DEFAULT 1,status ENUM('ACTIVE','ARCHIVED','DELETED') NOT NULL DEFAULT 'ACTIVE',created_by_admin_key CHAR(36) NULL,updated_by_admin_key CHAR(36) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_closing_balance(company_key_hash,closing_date,account_key,cost_center_key,finance_book_key),INDEX idx_finance_closing_balance_date(company_key_hash,closing_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_dimension_filter (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,dimension_filter_key CHAR(36) NOT NULL UNIQUE,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,dimension_key CHAR(36) NOT NULL,allow_or_restrict ENUM('ALLOW','RESTRICT') NOT NULL DEFAULT 'ALLOW',apply_restriction_on_values TINYINT(1) NOT NULL DEFAULT 1,status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',created_by_admin_key CHAR(36) NULL,updated_by_admin_key CHAR(36) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_dimension_filter(company_key_hash,dimension_key),INDEX idx_finance_dimension_filter_status(company_key_hash,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_dimension_filter_account (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,dimension_filter_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,account_key CHAR(36) NOT NULL,UNIQUE KEY uq_finance_dimension_filter_account(company_key_hash,dimension_filter_key,account_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_dimension_filter_value (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,dimension_filter_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,dimension_value_key CHAR(36) NOT NULL,UNIQUE KEY uq_finance_dimension_filter_value(company_key_hash,dimension_filter_key,dimension_value_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_accounting_period (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,accounting_period_key CHAR(36) NOT NULL UNIQUE,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,period_name VARCHAR(180) NOT NULL,start_date DATE NOT NULL,end_date DATE NOT NULL,closed_document_types_json LONGTEXT NOT NULL,exempted_role VARCHAR(120) NULL,status ENUM('ACTIVE','INACTIVE','CLOSED','DELETED') NOT NULL DEFAULT 'ACTIVE',created_by_admin_key CHAR(36) NULL,updated_by_admin_key CHAR(36) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_accounting_period(company_key_hash,period_name),INDEX idx_finance_accounting_period_dates(company_key_hash,status,start_date,end_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_fiscal_year (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,fiscal_year_key CHAR(36) NOT NULL UNIQUE,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,year_name VARCHAR(80) NOT NULL,year_start_date DATE NOT NULL,year_end_date DATE NOT NULL,is_short_year TINYINT(1) NOT NULL DEFAULT 0,auto_created TINYINT(1) NOT NULL DEFAULT 0,status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',created_by_admin_key CHAR(36) NULL,updated_by_admin_key CHAR(36) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_fiscal_year(company_key_hash,year_name),INDEX idx_finance_fiscal_year_dates(company_key_hash,status,year_start_date,year_end_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_book (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,finance_book_key CHAR(36) NOT NULL UNIQUE,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,finance_book_name VARCHAR(180) NOT NULL,is_default TINYINT(1) NOT NULL DEFAULT 0,status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',created_by_admin_key CHAR(36) NULL,updated_by_admin_key CHAR(36) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_book(company_key_hash,finance_book_name),INDEX idx_finance_book_status(company_key_hash,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_monthly_distribution (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,monthly_distribution_key CHAR(36) NOT NULL UNIQUE,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,distribution_name VARCHAR(180) NOT NULL,fiscal_year_key CHAR(36) NOT NULL,status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',created_by_admin_key CHAR(36) NULL,updated_by_admin_key CHAR(36) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_monthly_distribution(company_key_hash,distribution_name),INDEX idx_finance_monthly_distribution_status(company_key_hash,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_monthly_distribution_line (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,monthly_distribution_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,month_no TINYINT UNSIGNED NOT NULL,percentage DECIMAL(12,6) NOT NULL,UNIQUE KEY uq_finance_monthly_distribution_line(company_key_hash,monthly_distribution_key,month_no)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_cost_center_allocation (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cost_center_allocation_key CHAR(36) NOT NULL UNIQUE,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,main_cost_center_key CHAR(36) NOT NULL,valid_from DATE NOT NULL,amended_from_key CHAR(36) NULL,status ENUM('DRAFT','ACTIVE','CANCELLED','DELETED') NOT NULL DEFAULT 'ACTIVE',created_by_admin_key CHAR(36) NULL,updated_by_admin_key CHAR(36) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_cost_allocation(company_key_hash,main_cost_center_key,valid_from),INDEX idx_finance_cost_allocation_status(company_key_hash,status,valid_from)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_cost_center_allocation_line (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cost_center_allocation_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,cost_center_key CHAR(36) NOT NULL,percentage DECIMAL(12,6) NOT NULL,UNIQUE KEY uq_finance_cost_allocation_line(company_key_hash,cost_center_allocation_key,cost_center_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_exchange_setting (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,exchange_setting_key CHAR(36) NOT NULL UNIQUE,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,service_provider VARCHAR(80) NOT NULL,api_endpoint VARCHAR(500) NULL,base_currency VARCHAR(20) NOT NULL,use_http TINYINT(1) NOT NULL DEFAULT 0,status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',created_by_admin_key CHAR(36) NULL,updated_by_admin_key CHAR(36) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_exchange_setting(company_key_hash,service_provider),INDEX idx_finance_exchange_setting_status(company_key_hash,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_exchange_rate (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,exchange_rate_key CHAR(36) NOT NULL UNIQUE,exchange_setting_key CHAR(36) NOT NULL,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,from_currency VARCHAR(20) NOT NULL,to_currency VARCHAR(20) NOT NULL,transaction_date DATE NOT NULL,exchange_rate DECIMAL(20,9) NOT NULL,created_by_admin_key CHAR(36) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_exchange_rate(company_key_hash,from_currency,to_currency,transaction_date),INDEX idx_finance_exchange_rate_lookup(company_key_hash,transaction_date,from_currency,to_currency)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_chart_template (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,chart_template_key CHAR(36) NOT NULL UNIQUE,template_code VARCHAR(80) NOT NULL,template_name VARCHAR(180) NOT NULL,country VARCHAR(120) NOT NULL,template_version INT UNSIGNED NOT NULL,schema_json LONGTEXT NOT NULL,checksum CHAR(64) NOT NULL,status ENUM('ACTIVE','INACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_chart_template(template_code,template_version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_chart_template_installation (x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,chart_template_installation_key CHAR(36) NOT NULL UNIQUE,company_key CHAR(36) NOT NULL,company_key_hash CHAR(64) NOT NULL,template_code VARCHAR(80) NOT NULL,template_version INT UNSIGNED NOT NULL,template_checksum CHAR(64) NOT NULL,duplicate_policy ENUM('FAIL','SKIP_EXISTING') NOT NULL,inserted_count INT UNSIGNED NOT NULL DEFAULT 0,skipped_count INT UNSIGNED NOT NULL DEFAULT 0,status ENUM('INSTALLED','FAILED') NOT NULL DEFAULT 'INSTALLED',installed_by_admin_key CHAR(36) NULL,installed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_finance_chart_install(company_key_hash,template_code,template_version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) yovel_admin_db_execute($db,$statement,[],'Finance foundation schema update');

    bx_add_column_if_missing('project_company_accounting_account','account_category_key','CHAR(36) NULL AFTER account_type');
    bx_add_column_if_missing('project_company_accounting_account','template_code','VARCHAR(80) NULL AFTER account_notes');
    bx_add_column_if_missing('project_company_accounting_account','template_version','INT UNSIGNED NULL AFTER template_code');
    bx_add_index_if_missing('project_company_accounting_account','idx_project_company_account_category','INDEX idx_project_company_account_category (company_key_hash, account_category_key)');
    bx_add_index_if_missing('project_company_accounting_account','idx_project_company_account_template','INDEX idx_project_company_account_template (company_key_hash, template_code, template_version)');

    $template = yovel_admin_finance_chart_template_definition();
    yovel_admin_db_execute($db,"INSERT INTO project_company_finance_chart_template(chart_template_key,template_code,template_name,country,template_version,schema_json,checksum,status) VALUES(?,?,?,?,?,?,?,'ACTIVE') ON DUPLICATE KEY UPDATE template_name=VALUES(template_name),country=VALUES(country),schema_json=VALUES(schema_json),checksum=VALUES(checksum),status='ACTIVE'",[bx_uuid(),$template['template_code'],$template['template_name'],$template['country'],$template['template_version'],$template['schema_json'],$template['checksum']],'Philippine chart template seed');
}

function yovel_admin_finance_foundation_master_types(): array
{
    return ['account-category','account-closing-balance','dimension-filter','accounting-period','fiscal-year','finance-book','monthly-distribution','cost-center-allocation','currency-exchange-setting','chart-template-install'];
}

function yovel_admin_finance_foundation_status(mixed $value, array $allowed = ['ACTIVE','INACTIVE','DELETED']): string
{
    $status = strtoupper(trim((string)$value));
    return in_array($status,$allowed,true) ? $status : $allowed[0];
}

function yovel_admin_finance_foundation_date(mixed $value, string $label): string
{
    $date = yovel_admin_optional_date((string)$value,$label);
    if ($date === '') throw new InvalidArgumentException($label.' is required.');
    return $date;
}

function yovel_admin_finance_foundation_uuid(mixed $value): string
{
    $key = trim((string)$value);
    return yovel_admin_is_uuid($key) ? $key : bx_uuid();
}

function yovel_admin_finance_foundation_owned_count(ADOConnection $db, string $table, string $keyColumn, string $hash, string $key, string $extra = ''): int
{
    $allowed = [
        'project_company_accounting_account'=>'account_key','project_company_finance_cost_center'=>'cost_center_key',
        'project_company_finance_dimension'=>'dimension_key','project_company_finance_dimension_value'=>'dimension_value_key',
        'project_company_finance_fiscal_year'=>'fiscal_year_key','project_company_finance_book'=>'finance_book_key',
    ];
    if (($allowed[$table] ?? '') !== $keyColumn) throw new InvalidArgumentException('Finance ownership lookup is invalid.');
    return (int)$db->GetOne("SELECT COUNT(*) FROM {$table} WHERE company_key_hash=? AND {$keyColumn}=? {$extra}",[$hash,$key]);
}

function yovel_admin_finance_foundation_simple_upsert(ADOConnection $db, array $company, array $admin, string $type, string $key, array $payload): array
{
    $configs = [
        'account-category'=>['project_company_finance_account_category','account_category_key','account_category_name'],
        'accounting-period'=>['project_company_finance_accounting_period','accounting_period_key','period_name'],
        'fiscal-year'=>['project_company_finance_fiscal_year','fiscal_year_key','year_name'],
        'finance-book'=>['project_company_finance_book','finance_book_key','finance_book_name'],
        'account-closing-balance'=>['project_company_finance_account_closing_balance','account_closing_balance_key',''],
    ];
    if (!isset($configs[$type])) throw new InvalidArgumentException('Finance foundation upsert type is invalid.');
    [$table,$keyColumn,$businessColumn] = $configs[$type];
    [$companyKey,$hash,$adminKey] = yovel_admin_finance_scope($company,$admin);
    $foreign = (int)$db->GetOne("SELECT COUNT(*) FROM {$table} WHERE {$keyColumn}=? AND company_key_hash<>?",[$key,$hash]);
    if ($foreign > 0) throw new InvalidArgumentException('Finance foundation record belongs to another company.');
    $existing = $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash=? AND {$keyColumn}=?",[$hash,$key]);
    if ($businessColumn !== '') {
        $duplicate = (int)$db->GetOne("SELECT COUNT(*) FROM {$table} WHERE company_key_hash=? AND {$businessColumn}=? AND {$keyColumn}<>?",[$hash,$payload[$businessColumn],$key]);
        if ($duplicate > 0) throw new InvalidArgumentException('Finance foundation business name already exists.');
    }
    $columns = array_keys($payload);
    foreach ($columns as $column) {
        if (preg_match('/^[a-z][a-z0-9_]*$/',$column)!==1) throw new InvalidArgumentException('Finance foundation field is invalid.');
    }
    return yovel_admin_finance_transaction($db, static function () use ($db,$companyKey,$hash,$adminKey,$table,$keyColumn,$key,$payload,$columns,$existing): array {
        $insertColumns = array_merge([$keyColumn,'company_key','company_key_hash'],$columns,['created_by_admin_key','updated_by_admin_key']);
        $marks = implode(',',array_fill(0,count($insertColumns),'?'));
        $updates = implode(',',array_map(static fn(string $column):string=>$column.'=VALUES('.$column.')',array_merge($columns,['updated_by_admin_key'])));
        yovel_admin_db_execute($db,'INSERT INTO '.$table.' ('.implode(',',$insertColumns).") VALUES ({$marks}) ON DUPLICATE KEY UPDATE {$updates}",array_merge([$key,$companyKey,$hash],array_values($payload),[$adminKey,$adminKey]),'Finance foundation save');
        $saved = $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash=? AND {$keyColumn}=?",[$hash,$key]);
        if (!is_array($saved) || (string)($saved[$keyColumn]??'')!==$key) throw new RuntimeException('Finance foundation read-back verification failed.');
        foreach ($payload as $column=>$value) {
            if ((string)($saved[$column]??'')!==(string)$value) throw new RuntimeException('Finance foundation read-back mismatch for '.$column.'.');
        }
        bx_audit($existing?'UPDATE':'CREATE',$table,$key,['company_key'=>$companyKey,'fields'=>$payload,'admin_key'=>$adminKey],$existing?'Company administrator updated a Finance foundation record.':'Company administrator created a Finance foundation record.');
        return $saved;
    });
}

function yovel_admin_persist_finance_account_category(ADOConnection $db,array $company,array $admin,array $input):array
{
    $name=substr(trim((string)($input['account_category_name']??'')),0,180);
    if($name==='')throw new InvalidArgumentException('Account Category name is required.');
    $root=trim((string)($input['root_type']??''));
    if($root!==''&&!in_array($root,['ASSET','LIABILITY','INCOME','EXPENSE','EQUITY'],true))throw new InvalidArgumentException('Account Category root type is invalid.');
    return yovel_admin_finance_foundation_simple_upsert($db,$company,$admin,'account-category',yovel_admin_finance_foundation_uuid($input['account_category_key']??''),[
        'account_category_name'=>$name,'root_type'=>$root!==''?$root:null,'description'=>substr(trim((string)($input['description']??'')),0,2000),'status'=>yovel_admin_finance_foundation_status($input['status']??'ACTIVE'),
    ]);
}

function yovel_admin_persist_finance_fiscal_year(ADOConnection $db,array $company,array $admin,array $input):array
{
    $name=substr(trim((string)($input['year_name']??'')),0,80);$from=yovel_admin_finance_foundation_date($input['year_start_date']??'','Fiscal Year start date');$to=yovel_admin_finance_foundation_date($input['year_end_date']??'','Fiscal Year end date');
    if($name===''||$to<$from)throw new InvalidArgumentException('Fiscal Year name and valid date range are required.');
    $key=yovel_admin_finance_foundation_uuid($input['fiscal_year_key']??'');
    [$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);
    $existing=$db->GetRow('SELECT * FROM project_company_finance_fiscal_year WHERE company_key_hash=? AND fiscal_year_key=?',[$hash,$key]);
    if($existing&&((string)$existing['year_start_date']!==$from||(string)$existing['year_end_date']!==$to))throw new InvalidArgumentException('Fiscal Year dates are set once and cannot be changed.');
    return yovel_admin_finance_foundation_simple_upsert($db,$company,$admin,'fiscal-year',$key,['year_name'=>$name,'year_start_date'=>$from,'year_end_date'=>$to,'is_short_year'=>!empty($input['is_short_year'])?1:0,'auto_created'=>!empty($input['auto_created'])?1:0,'status'=>yovel_admin_finance_foundation_status($input['status']??'ACTIVE')]);
}

function yovel_admin_persist_finance_accounting_period(ADOConnection $db,array $company,array $admin,array $input):array
{
    $name=substr(trim((string)($input['period_name']??'')),0,180);$from=yovel_admin_finance_foundation_date($input['start_date']??'','Accounting Period start date');$to=yovel_admin_finance_foundation_date($input['end_date']??'','Accounting Period end date');
    if($name===''||$to<$from)throw new InvalidArgumentException('Accounting Period name and valid date range are required.');
    $types=array_values(array_unique(array_filter(array_map(static fn($v):string=>strtoupper(trim((string)$v)),is_array($input['closed_document_types']??null)?$input['closed_document_types']:[]))));
    $json=json_encode($types,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    return yovel_admin_finance_foundation_simple_upsert($db,$company,$admin,'accounting-period',yovel_admin_finance_foundation_uuid($input['accounting_period_key']??''),['period_name'=>$name,'start_date'=>$from,'end_date'=>$to,'closed_document_types_json'=>$json,'exempted_role'=>substr(trim((string)($input['exempted_role']??'')),0,120),'status'=>yovel_admin_finance_foundation_status($input['status']??'ACTIVE',['ACTIVE','INACTIVE','CLOSED','DELETED'])]);
}

function yovel_admin_persist_finance_book(ADOConnection $db,array $company,array $admin,array $input):array
{
    [$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);
    $name=substr(trim((string)($input['finance_book_name']??'')),0,180);if($name==='')throw new InvalidArgumentException('Finance Book name is required.');
    $key=yovel_admin_finance_foundation_uuid($input['finance_book_key']??'');$isDefault=!empty($input['is_default'])?1:0;$status=yovel_admin_finance_foundation_status($input['status']??'ACTIVE');
    if((int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_book WHERE finance_book_key=? AND company_key_hash<>?',[$key,$hash])>0)throw new InvalidArgumentException('Finance Book belongs to another company.');
    if((int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_book WHERE company_key_hash=? AND finance_book_name=? AND finance_book_key<>?',[$hash,$name,$key])>0)throw new InvalidArgumentException('Finance Book already exists.');
    $existing=$db->GetRow('SELECT * FROM project_company_finance_book WHERE company_key_hash=? AND finance_book_key=?',[$hash,$key]);
    return yovel_admin_finance_transaction($db,static function()use($db,$companyKey,$hash,$adminKey,$name,$key,$isDefault,$status,$existing):array{
        if($isDefault===1)yovel_admin_db_execute($db,'UPDATE project_company_finance_book SET is_default=0,updated_by_admin_key=? WHERE company_key_hash=? AND finance_book_key<>?',[$adminKey,$hash,$key],'Finance Book default reset');
        yovel_admin_db_execute($db,"INSERT INTO project_company_finance_book(finance_book_key,company_key,company_key_hash,finance_book_name,is_default,status,created_by_admin_key,updated_by_admin_key) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE finance_book_name=VALUES(finance_book_name),is_default=VALUES(is_default),status=VALUES(status),updated_by_admin_key=VALUES(updated_by_admin_key)",[$key,$companyKey,$hash,$name,$isDefault,$status,$adminKey,$adminKey],'Finance Book save');
        $saved=$db->GetRow('SELECT * FROM project_company_finance_book WHERE company_key_hash=? AND finance_book_key=?',[$hash,$key]);
        if(!is_array($saved)||(string)$saved['finance_book_name']!==$name||(int)$saved['is_default']!==$isDefault||(string)$saved['status']!==$status)throw new RuntimeException('Finance Book read-back verification failed.');
        if($isDefault===1&&(int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_book WHERE company_key_hash=? AND is_default=1',[$hash])!==1)throw new RuntimeException('Finance Book default read-back verification failed.');
        bx_audit($existing?'UPDATE':'CREATE','project_company_finance_book',$key,['company_key'=>$companyKey,'finance_book_name'=>$name,'is_default'=>$isDefault,'admin_key'=>$adminKey],'Company administrator saved a Finance Book.');return $saved;
    });
}

function yovel_admin_persist_finance_account_closing_balance(ADOConnection $db,array $company,array $admin,array $input):array
{
    [$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$account=(string)($input['account_key']??'');
    if(!yovel_admin_is_uuid($account)||yovel_admin_finance_foundation_owned_count($db,'project_company_accounting_account','account_key',$hash,$account,"AND account_status<>'DELETED' AND is_group=0")!==1)throw new InvalidArgumentException('Closing Balance account is invalid.');
    $costCenter=trim((string)($input['cost_center_key']??''));$financeBook=trim((string)($input['finance_book_key']??''));
    if($costCenter!==''&&(!yovel_admin_is_uuid($costCenter)||yovel_admin_finance_foundation_owned_count($db,'project_company_finance_cost_center','cost_center_key',$hash,$costCenter,"AND status='ACTIVE'")!==1))throw new InvalidArgumentException('Closing Balance Cost Center is invalid.');
    if($financeBook!==''&&(!yovel_admin_is_uuid($financeBook)||yovel_admin_finance_foundation_owned_count($db,'project_company_finance_book','finance_book_key',$hash,$financeBook,"AND status='ACTIVE'")!==1))throw new InvalidArgumentException('Closing Balance Finance Book is invalid.');
    $debit=yovel_admin_finance_money($input['debit']??0);$credit=yovel_admin_finance_money($input['credit']??0);if(bccomp($debit,'0',6)===1&&bccomp($credit,'0',6)===1)throw new InvalidArgumentException('Closing Balance cannot contain both debit and credit.');
    $currency=strtoupper(trim((string)($input['account_currency']??'PHP')));if(preg_match('/^[A-Z]{3}$/',$currency)!==1)throw new InvalidArgumentException('Closing Balance currency is invalid.');
    return yovel_admin_finance_foundation_simple_upsert($db,$company,$admin,'account-closing-balance',yovel_admin_finance_foundation_uuid($input['account_closing_balance_key']??''),[
        'closing_date'=>yovel_admin_finance_foundation_date($input['closing_date']??'','Closing date'),'account_key'=>$account,'cost_center_key'=>$costCenter?:null,'finance_book_key'=>$financeBook?:null,'debit'=>$debit,'credit'=>$credit,'account_currency'=>$currency,'debit_in_account_currency'=>yovel_admin_finance_money($input['debit_in_account_currency']??$debit),'credit_in_account_currency'=>yovel_admin_finance_money($input['credit_in_account_currency']??$credit),'reporting_currency_exchange_rate'=>number_format((float)($input['reporting_currency_exchange_rate']??1),9,'.',''),'status'=>yovel_admin_finance_foundation_status($input['status']??'ACTIVE',['ACTIVE','ARCHIVED','DELETED']),
    ]);
}

function yovel_admin_persist_finance_monthly_distribution(ADOConnection $db,array $company,array $admin,array $input):array
{
    [$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$key=yovel_admin_finance_foundation_uuid($input['monthly_distribution_key']??'');$name=substr(trim((string)($input['distribution_name']??'')),0,180);$year=(string)($input['fiscal_year_key']??'');
    if($name===''||!yovel_admin_is_uuid($year)||yovel_admin_finance_foundation_owned_count($db,'project_company_finance_fiscal_year','fiscal_year_key',$hash,$year,"AND status='ACTIVE'")!==1)throw new InvalidArgumentException('Monthly Distribution name and active Fiscal Year are required.');
    $raw=is_array($input['percentages']??null)?$input['percentages']:[];$percentages=[];$total='0.000000';
    for($month=1;$month<=12;$month++){$value=yovel_admin_finance_money($raw[$month]??$raw[$month-1]??0);if(bccomp($value,'0',6)<0)throw new InvalidArgumentException('Monthly Distribution percentages cannot be negative.');$percentages[$month]=$value;$total=bcadd($total,$value,6);}
    $difference = bcsub($total, '100.000000', 6);
    if (str_starts_with($difference, '-')) $difference = substr($difference, 1);
    if(bccomp($difference,'0.000100',6)===1)throw new InvalidArgumentException('Monthly Distribution percentages must total 100%.');
    $foreign=(int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_monthly_distribution WHERE monthly_distribution_key=? AND company_key_hash<>?',[$key,$hash]);if($foreign)throw new InvalidArgumentException('Monthly Distribution belongs to another company.');
    $duplicate=(int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_monthly_distribution WHERE company_key_hash=? AND distribution_name=? AND monthly_distribution_key<>?',[$hash,$name,$key]);if($duplicate)throw new InvalidArgumentException('Monthly Distribution already exists.');
    $existing=$db->GetRow('SELECT * FROM project_company_finance_monthly_distribution WHERE company_key_hash=? AND monthly_distribution_key=?',[$hash,$key]);
    return yovel_admin_finance_transaction($db,static function()use($db,$companyKey,$hash,$adminKey,$key,$name,$year,$input,$percentages,$existing):array{
        yovel_admin_db_execute($db,"INSERT INTO project_company_finance_monthly_distribution(monthly_distribution_key,company_key,company_key_hash,distribution_name,fiscal_year_key,status,created_by_admin_key,updated_by_admin_key) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE distribution_name=VALUES(distribution_name),fiscal_year_key=VALUES(fiscal_year_key),status=VALUES(status),updated_by_admin_key=VALUES(updated_by_admin_key)",[$key,$companyKey,$hash,$name,$year,yovel_admin_finance_foundation_status($input['status']??'ACTIVE'),$adminKey,$adminKey],'Monthly Distribution save');
        yovel_admin_db_execute($db,'DELETE FROM project_company_finance_monthly_distribution_line WHERE company_key_hash=? AND monthly_distribution_key=?',[$hash,$key],'Monthly Distribution line reset');
        foreach($percentages as $month=>$percentage)yovel_admin_db_execute($db,'INSERT INTO project_company_finance_monthly_distribution_line(monthly_distribution_key,company_key_hash,month_no,percentage) VALUES(?,?,?,?)',[$key,$hash,$month,$percentage],'Monthly Distribution line save');
        $saved=$db->GetRow('SELECT * FROM project_company_finance_monthly_distribution WHERE company_key_hash=? AND monthly_distribution_key=?',[$hash,$key]);$lines=$db->GetAll('SELECT month_no,percentage FROM project_company_finance_monthly_distribution_line WHERE company_key_hash=? AND monthly_distribution_key=? ORDER BY month_no',[$hash,$key]);
        if(!$saved||count($lines)!==12)throw new RuntimeException('Monthly Distribution read-back verification failed.');$saved['percentages']=array_column($lines,'percentage','month_no');
        bx_audit($existing?'UPDATE':'CREATE','project_company_finance_monthly_distribution',$key,['company_key'=>$companyKey,'distribution_name'=>$name,'percentages'=>$saved['percentages'],'admin_key'=>$adminKey],'Company administrator saved a monthly accounting distribution.');return $saved;
    });
}

function yovel_admin_persist_finance_cost_center_allocation(ADOConnection $db,array $company,array $admin,array $input):array
{
    [$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$key=yovel_admin_finance_foundation_uuid($input['cost_center_allocation_key']??'');$main=(string)($input['main_cost_center_key']??'');$validFrom=yovel_admin_finance_foundation_date($input['valid_from']??'','Allocation valid-from date');
    if(!yovel_admin_is_uuid($main)||yovel_admin_finance_foundation_owned_count($db,'project_company_finance_cost_center','cost_center_key',$hash,$main,"AND status='ACTIVE' AND is_group=0")!==1)throw new InvalidArgumentException('Main Cost Center must be an active posting Cost Center.');
    $raw=is_array($input['allocations']??null)?$input['allocations']:[];$allocations=[];$total='0.000000';
    foreach($raw as $line){if(!is_array($line))continue;$center=(string)($line['cost_center_key']??'');$percentage=yovel_admin_finance_money($line['percentage']??0);if(!yovel_admin_is_uuid($center)||isset($allocations[$center])||yovel_admin_finance_foundation_owned_count($db,'project_company_finance_cost_center','cost_center_key',$hash,$center,"AND status='ACTIVE' AND is_group=0")!==1)throw new InvalidArgumentException('Cost Center Allocation contains an invalid or duplicate Cost Center.');if(bccomp($percentage,'0',6)!==1)throw new InvalidArgumentException('Cost Center Allocation percentages must be positive.');$allocations[$center]=$percentage;$total=bcadd($total,$percentage,6);}
    $difference=bcsub($total,'100.000000',6);if(str_starts_with($difference,'-'))$difference=substr($difference,1);
    if($allocations===[]||bccomp($difference,'0.000100',6)===1)throw new InvalidArgumentException('Cost Center Allocation percentages must total 100%.');
    if((int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_cost_center_allocation WHERE cost_center_allocation_key=? AND company_key_hash<>?',[$key,$hash])>0)throw new InvalidArgumentException('Cost Center Allocation belongs to another company.');
    $existing=$db->GetRow('SELECT * FROM project_company_finance_cost_center_allocation WHERE company_key_hash=? AND cost_center_allocation_key=?',[$hash,$key]);
    return yovel_admin_finance_transaction($db,static function()use($db,$companyKey,$hash,$adminKey,$key,$main,$validFrom,$input,$allocations,$existing):array{
        yovel_admin_db_execute($db,"INSERT INTO project_company_finance_cost_center_allocation(cost_center_allocation_key,company_key,company_key_hash,main_cost_center_key,valid_from,amended_from_key,status,created_by_admin_key,updated_by_admin_key) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE main_cost_center_key=VALUES(main_cost_center_key),valid_from=VALUES(valid_from),amended_from_key=VALUES(amended_from_key),status=VALUES(status),updated_by_admin_key=VALUES(updated_by_admin_key)",[$key,$companyKey,$hash,$main,$validFrom,trim((string)($input['amended_from_key']??''))?:null,yovel_admin_finance_foundation_status($input['status']??'ACTIVE',['DRAFT','ACTIVE','CANCELLED','DELETED']),$adminKey,$adminKey],'Cost Center Allocation save');
        yovel_admin_db_execute($db,'DELETE FROM project_company_finance_cost_center_allocation_line WHERE company_key_hash=? AND cost_center_allocation_key=?',[$hash,$key],'Cost Center Allocation line reset');
        foreach($allocations as $center=>$percentage)yovel_admin_db_execute($db,'INSERT INTO project_company_finance_cost_center_allocation_line(cost_center_allocation_key,company_key_hash,cost_center_key,percentage) VALUES(?,?,?,?)',[$key,$hash,$center,$percentage],'Cost Center Allocation line save');
        $saved=$db->GetRow('SELECT * FROM project_company_finance_cost_center_allocation WHERE company_key_hash=? AND cost_center_allocation_key=?',[$hash,$key]);$lines=$db->GetAll('SELECT cost_center_key,percentage FROM project_company_finance_cost_center_allocation_line WHERE company_key_hash=? AND cost_center_allocation_key=? ORDER BY x_id',[$hash,$key]);if(!$saved||count($lines)!==count($allocations))throw new RuntimeException('Cost Center Allocation read-back verification failed.');$saved['allocations']=$lines;
        bx_audit($existing?'UPDATE':'CREATE','project_company_finance_cost_center_allocation',$key,['company_key'=>$companyKey,'main_cost_center_key'=>$main,'allocations'=>$lines,'admin_key'=>$adminKey],'Company administrator saved a Cost Center Allocation.');return $saved;
    });
}

function yovel_admin_persist_finance_dimension_filter(ADOConnection $db,array $company,array $admin,array $input):array
{
    [$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$key=yovel_admin_finance_foundation_uuid($input['dimension_filter_key']??'');$dimension=(string)($input['dimension_key']??'');
    if(!yovel_admin_is_uuid($dimension)||yovel_admin_finance_foundation_owned_count($db,'project_company_finance_dimension','dimension_key',$hash,$dimension,"AND status='ACTIVE'")!==1)throw new InvalidArgumentException('Accounting Dimension is invalid.');
    $accounts=array_values(array_unique(array_filter(array_map('strval',is_array($input['account_keys']??null)?$input['account_keys']:[]))));$values=array_values(array_unique(array_filter(array_map('strval',is_array($input['dimension_value_keys']??null)?$input['dimension_value_keys']:[]))));
    if((int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_dimension_filter WHERE dimension_filter_key=? AND company_key_hash<>?',[$key,$hash])>0)throw new InvalidArgumentException('Dimension Filter belongs to another company.');
    if($accounts===[])throw new InvalidArgumentException('Dimension Filter requires an applicable account.');
    foreach($accounts as $account)if(!yovel_admin_is_uuid($account)||yovel_admin_finance_foundation_owned_count($db,'project_company_accounting_account','account_key',$hash,$account,"AND account_status<>'DELETED'")!==1)throw new InvalidArgumentException('Dimension Filter account is invalid.');
    foreach($values as $value)if(!yovel_admin_is_uuid($value)||(int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_dimension_value WHERE company_key_hash=? AND dimension_key=? AND dimension_value_key=? AND status=\'ACTIVE\'',[$hash,$dimension,$value])!==1)throw new InvalidArgumentException('Allowed Dimension value is invalid.');
    $existing=$db->GetRow('SELECT * FROM project_company_finance_dimension_filter WHERE company_key_hash=? AND dimension_filter_key=?',[$hash,$key]);
    return yovel_admin_finance_transaction($db,static function()use($db,$companyKey,$hash,$adminKey,$key,$dimension,$input,$accounts,$values,$existing):array{
        $mode=yovel_admin_finance_foundation_status($input['allow_or_restrict']??'ALLOW',['ALLOW','RESTRICT']);$status=yovel_admin_finance_foundation_status($input['status']??'ACTIVE');
        yovel_admin_db_execute($db,"INSERT INTO project_company_finance_dimension_filter(dimension_filter_key,company_key,company_key_hash,dimension_key,allow_or_restrict,apply_restriction_on_values,status,created_by_admin_key,updated_by_admin_key) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE dimension_key=VALUES(dimension_key),allow_or_restrict=VALUES(allow_or_restrict),apply_restriction_on_values=VALUES(apply_restriction_on_values),status=VALUES(status),updated_by_admin_key=VALUES(updated_by_admin_key)",[$key,$companyKey,$hash,$dimension,$mode,!empty($input['apply_restriction_on_values'])?1:0,$status,$adminKey,$adminKey],'Dimension Filter save');
        yovel_admin_db_execute($db,'DELETE FROM project_company_finance_dimension_filter_account WHERE company_key_hash=? AND dimension_filter_key=?',[$hash,$key],'Dimension Filter accounts reset');yovel_admin_db_execute($db,'DELETE FROM project_company_finance_dimension_filter_value WHERE company_key_hash=? AND dimension_filter_key=?',[$hash,$key],'Dimension Filter values reset');
        foreach($accounts as $account)yovel_admin_db_execute($db,'INSERT INTO project_company_finance_dimension_filter_account(dimension_filter_key,company_key_hash,account_key) VALUES(?,?,?)',[$key,$hash,$account],'Dimension Filter account save');foreach($values as $value)yovel_admin_db_execute($db,'INSERT INTO project_company_finance_dimension_filter_value(dimension_filter_key,company_key_hash,dimension_value_key) VALUES(?,?,?)',[$key,$hash,$value],'Dimension Filter value save');
        $saved=$db->GetRow('SELECT * FROM project_company_finance_dimension_filter WHERE company_key_hash=? AND dimension_filter_key=?',[$hash,$key]);$saved['account_keys']=$db->GetCol('SELECT account_key FROM project_company_finance_dimension_filter_account WHERE company_key_hash=? AND dimension_filter_key=? ORDER BY account_key',[$hash,$key]);$saved['dimension_value_keys']=$db->GetCol('SELECT dimension_value_key FROM project_company_finance_dimension_filter_value WHERE company_key_hash=? AND dimension_filter_key=? ORDER BY dimension_value_key',[$hash,$key]);if(!$saved||count($saved['account_keys'])!==count($accounts)||count($saved['dimension_value_keys'])!==count($values))throw new RuntimeException('Dimension Filter read-back verification failed.');
        bx_audit($existing?'UPDATE':'CREATE','project_company_finance_dimension_filter',$key,['company_key'=>$companyKey,'dimension_key'=>$dimension,'account_keys'=>$accounts,'dimension_value_keys'=>$values,'admin_key'=>$adminKey],'Company administrator saved Accounting Dimension restrictions.');return $saved;
    });
}

function yovel_admin_persist_finance_exchange_setting(ADOConnection $db,array $company,array $admin,array $input):array
{
    [$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$key=yovel_admin_finance_foundation_uuid($input['exchange_setting_key']??'');$provider=strtoupper(trim((string)($input['service_provider']??'')));$endpoint=substr(trim((string)($input['api_endpoint']??'')),0,500);$base=strtoupper(trim((string)($input['base_currency']??'PHP')));
    if($provider===''||preg_match('/^[A-Z]{3}$/',$base)!==1)throw new InvalidArgumentException('Currency Exchange provider and base currency are required.');if($provider==='CUSTOM'&&($endpoint===''||filter_var(str_replace(['{transaction_date}','{from_currency}','{to_currency}'],['2026-01-01','USD','PHP'],$endpoint),FILTER_VALIDATE_URL)===false))throw new InvalidArgumentException('Custom Currency Exchange endpoint is invalid.');
    $rates=[];foreach(is_array($input['rates']??null)?$input['rates']:[] as $rate){if(!is_array($rate))continue;$from=strtoupper(trim((string)($rate['from_currency']??'')));$to=strtoupper(trim((string)($rate['to_currency']??'')));$date=yovel_admin_finance_foundation_date($rate['transaction_date']??'','Exchange rate date');$value=number_format((float)($rate['exchange_rate']??0),9,'.','');if(preg_match('/^[A-Z]{3}$/',$from)!==1||preg_match('/^[A-Z]{3}$/',$to)!==1||$from===$to||(float)$value<=0)throw new InvalidArgumentException('Currency Exchange rate row is invalid.');$rateKey=$from.'|'.$to.'|'.$date;if(isset($rates[$rateKey]))throw new InvalidArgumentException('Currency Exchange rate row is duplicated.');$rates[$rateKey]=['from_currency'=>$from,'to_currency'=>$to,'transaction_date'=>$date,'exchange_rate'=>$value];}
    if((int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_exchange_setting WHERE exchange_setting_key=? AND company_key_hash<>?',[$key,$hash])>0)throw new InvalidArgumentException('Currency Exchange Setting belongs to another company.');
    $existing=$db->GetRow('SELECT * FROM project_company_finance_exchange_setting WHERE company_key_hash=? AND exchange_setting_key=?',[$hash,$key]);
    return yovel_admin_finance_transaction($db,static function()use($db,$companyKey,$hash,$adminKey,$key,$provider,$endpoint,$base,$input,$rates,$existing):array{
        yovel_admin_db_execute($db,"INSERT INTO project_company_finance_exchange_setting(exchange_setting_key,company_key,company_key_hash,service_provider,api_endpoint,base_currency,use_http,status,created_by_admin_key,updated_by_admin_key) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE service_provider=VALUES(service_provider),api_endpoint=VALUES(api_endpoint),base_currency=VALUES(base_currency),use_http=VALUES(use_http),status=VALUES(status),updated_by_admin_key=VALUES(updated_by_admin_key)",[$key,$companyKey,$hash,$provider,$endpoint?:null,$base,!empty($input['use_http'])?1:0,yovel_admin_finance_foundation_status($input['status']??'ACTIVE'),$adminKey,$adminKey],'Currency Exchange Setting save');
        yovel_admin_db_execute($db,'DELETE FROM project_company_finance_exchange_rate WHERE company_key_hash=? AND exchange_setting_key=?',[$hash,$key],'Currency Exchange Rate reset');
        foreach($rates as $rate)yovel_admin_db_execute($db,"INSERT INTO project_company_finance_exchange_rate(exchange_rate_key,exchange_setting_key,company_key,company_key_hash,from_currency,to_currency,transaction_date,exchange_rate,created_by_admin_key) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE exchange_setting_key=VALUES(exchange_setting_key),exchange_rate=VALUES(exchange_rate)",[bx_uuid(),$key,$companyKey,$hash,$rate['from_currency'],$rate['to_currency'],$rate['transaction_date'],$rate['exchange_rate'],$adminKey],'Currency Exchange Rate save');
        $saved=$db->GetRow('SELECT * FROM project_company_finance_exchange_setting WHERE company_key_hash=? AND exchange_setting_key=?',[$hash,$key]);$saved['rates']=$db->GetAll('SELECT * FROM project_company_finance_exchange_rate WHERE company_key_hash=? AND exchange_setting_key=? ORDER BY transaction_date DESC,from_currency,to_currency',[$hash,$key]);if(!$saved||count($saved['rates'])!==count($rates))throw new RuntimeException('Currency Exchange Setting read-back verification failed.');
        bx_audit($existing?'UPDATE':'CREATE','project_company_finance_exchange_setting',$key,['company_key'=>$companyKey,'service_provider'=>$provider,'base_currency'=>$base,'rate_count'=>count($rates),'admin_key'=>$adminKey],'Company administrator saved Currency Exchange Settings.');return $saved;
    });
}

function yovel_admin_persist_finance_foundation_master(ADOConnection $db,array $company,array $admin,string $type,array $input):array
{
    yovel_admin_finance_foundation_schema();$type=yovel_admin_slug($type);
    return match($type){
        'account-category'=>yovel_admin_persist_finance_account_category($db,$company,$admin,$input),
        'account-closing-balance'=>yovel_admin_persist_finance_account_closing_balance($db,$company,$admin,$input),
        'dimension-filter'=>yovel_admin_persist_finance_dimension_filter($db,$company,$admin,$input),
        'accounting-period'=>yovel_admin_persist_finance_accounting_period($db,$company,$admin,$input),
        'fiscal-year'=>yovel_admin_persist_finance_fiscal_year($db,$company,$admin,$input),
        'finance-book'=>yovel_admin_persist_finance_book($db,$company,$admin,$input),
        'monthly-distribution'=>yovel_admin_persist_finance_monthly_distribution($db,$company,$admin,$input),
        'cost-center-allocation'=>yovel_admin_persist_finance_cost_center_allocation($db,$company,$admin,$input),
        'currency-exchange-setting'=>yovel_admin_persist_finance_exchange_setting($db,$company,$admin,$input),
        'chart-template-install'=>yovel_admin_install_finance_chart_template($db,$company,$admin,(string)($input['template_code']??'PH_STANDARD'),(int)($input['template_version']??1),(string)($input['duplicate_policy']??'SKIP_EXISTING')),
        default=>throw new InvalidArgumentException('Finance foundation master type is invalid.'),
    };
}

function yovel_admin_finance_foundation_records(array $company):array
{
    yovel_admin_finance_foundation_schema();$hash=trim((string)($company['company_key_hash']??''));if($hash==='')return [];$db=bx_db();
    $load=static fn(string $table,string $order):array=>(array)$db->GetAll("SELECT * FROM {$table} WHERE company_key_hash=? AND status<>'DELETED' ORDER BY {$order}",[$hash]);
    $data=[
        'accountCategories'=>$load('project_company_finance_account_category','account_category_name'),
        'accountClosingBalances'=>$load('project_company_finance_account_closing_balance','closing_date DESC,x_id DESC'),
        'dimensionFilters'=>$load('project_company_finance_dimension_filter','x_id DESC'),
        'accountingPeriods'=>$load('project_company_finance_accounting_period','start_date DESC,x_id DESC'),
        'fiscalYears'=>$load('project_company_finance_fiscal_year','year_start_date DESC'),
        'financeBooks'=>$load('project_company_finance_book','is_default DESC,finance_book_name'),
        'monthlyDistributions'=>$load('project_company_finance_monthly_distribution','distribution_name'),
        'costCenterAllocations'=>$load('project_company_finance_cost_center_allocation','valid_from DESC,x_id DESC'),
        'exchangeSettings'=>$load('project_company_finance_exchange_setting','service_provider'),
    ];
    foreach($data['monthlyDistributions'] as &$row)$row['percentages']=$db->GetAll('SELECT month_no,percentage FROM project_company_finance_monthly_distribution_line WHERE company_key_hash=? AND monthly_distribution_key=? ORDER BY month_no',[$hash,$row['monthly_distribution_key']]);unset($row);
    foreach($data['costCenterAllocations'] as &$row)$row['allocations']=$db->GetAll('SELECT cost_center_key,percentage FROM project_company_finance_cost_center_allocation_line WHERE company_key_hash=? AND cost_center_allocation_key=? ORDER BY x_id',[$hash,$row['cost_center_allocation_key']]);unset($row);
    foreach($data['dimensionFilters'] as &$row){$row['account_keys']=$db->GetCol('SELECT account_key FROM project_company_finance_dimension_filter_account WHERE company_key_hash=? AND dimension_filter_key=?',[$hash,$row['dimension_filter_key']]);$row['dimension_value_keys']=$db->GetCol('SELECT dimension_value_key FROM project_company_finance_dimension_filter_value WHERE company_key_hash=? AND dimension_filter_key=?',[$hash,$row['dimension_filter_key']]);}unset($row);
    foreach($data['exchangeSettings'] as &$row)$row['rates']=$db->GetAll('SELECT * FROM project_company_finance_exchange_rate WHERE company_key_hash=? AND exchange_setting_key=? ORDER BY transaction_date DESC',[$hash,$row['exchange_setting_key']]);unset($row);
    $data['templateInstallations']=(array)$db->GetAll('SELECT * FROM project_company_finance_chart_template_installation WHERE company_key_hash=? ORDER BY installed_at DESC',[$hash]);
    return $data;
}

function yovel_admin_finance_chart_templates():array
{
    yovel_admin_finance_foundation_schema();return (array)bx_db()->GetAll("SELECT template_code,template_name,country,template_version,checksum,status FROM project_company_finance_chart_template WHERE status='ACTIVE' ORDER BY country,template_name,template_version DESC");
}

function yovel_admin_finance_chart_template_preview(array $company,string $templateCode,int $version):array
{
    yovel_admin_finance_foundation_schema();$templateCode=strtoupper(trim($templateCode));$row=bx_db()->GetRow("SELECT * FROM project_company_finance_chart_template WHERE template_code=? AND template_version=? AND status='ACTIVE'",[$templateCode,$version]);if(!$row)throw new InvalidArgumentException('Finance chart template was not found.');$rows=json_decode((string)$row['schema_json'],true,512,JSON_THROW_ON_ERROR);if(!is_array($rows)||$rows===[])throw new RuntimeException('Finance chart template schema is invalid.');
    $codes=[];foreach($rows as $item){$code=(string)($item['account_code']??'');if($code===''||isset($codes[$code]))throw new RuntimeException('Finance chart template contains duplicate account codes.');$parent=(string)($item['parent_account_code']??'');if($parent!==''&&!isset($codes[$parent]))throw new RuntimeException('Finance chart template parent order is invalid.');$codes[$code]=true;}
    $hash=trim((string)($company['company_key_hash']??''));$existing=$hash!==''?(array)bx_db()->GetAll('SELECT account_code,account_number,template_code FROM project_company_accounting_account WHERE company_key_hash=? AND account_status<>\'DELETED\'',[$hash]):[];$byCode=[];$byNumber=[];foreach($existing as $account){$byCode[(string)$account['account_code']]=$account;if((string)($account['account_number']??'')!=='')$byNumber[(string)$account['account_number']]=$account;}
    $conflicts=[];foreach($rows as &$item){$code=(string)$item['account_code'];$number=(string)$item['account_number'];$state=isset($byCode[$code])?'EXISTING':(isset($byNumber[$number])?'NUMBER_CONFLICT':'NEW');$item['preview_state']=$state;if($state==='NUMBER_CONFLICT')$conflicts[]=['account_code'=>$code,'account_number'=>$number,'conflicting_account_code'=>$byNumber[$number]['account_code']];}unset($item);
    return ['template_code'=>$templateCode,'template_version'=>$version,'template_name'=>$row['template_name'],'country'=>$row['country'],'checksum'=>$row['checksum'],'rows'=>$rows,'conflicts'=>$conflicts,'new_count'=>count(array_filter($rows,static fn(array $item):bool=>$item['preview_state']==='NEW')),'existing_count'=>count(array_filter($rows,static fn(array $item):bool=>$item['preview_state']==='EXISTING'))];
}

function yovel_admin_finance_assert_account_update_allowed(ADOConnection $db, string $companyKeyHash, array $existing, array $next): void
{
    $accountKey = trim((string) ($existing['account_key'] ?? ''));
    if (!yovel_admin_is_uuid($accountKey)) {
        throw new InvalidArgumentException('Finance account protection requires a valid account.');
    }
    $postedCount = (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash = ? AND account_key = ?',
        [$companyKeyHash, $accountKey]
    );
    if ($postedCount === 0) return;

    $protectedFields = ['account_code','account_number','parent_account_key','root_type','report_type','account_type','account_currency','is_group'];
    foreach ($protectedFields as $field) {
        if ((string) ($existing[$field] ?? '') !== (string) ($next[$field] ?? '')) {
            throw new InvalidArgumentException('A posted account cannot change protected accounting fields.');
        }
    }
    if (($next['account_status'] ?? $existing['account_status'] ?? '') === 'DELETED') {
        throw new InvalidArgumentException('A posted account cannot be deleted.');
    }
}

function yovel_admin_finance_assert_account_parent_chain(ADOConnection $db, string $companyKeyHash, string $accountKey, string $parentAccountKey): void
{
    if ($parentAccountKey === '') return;
    $visited = [$accountKey => true];
    $cursor = $parentAccountKey;
    while ($cursor !== '') {
        if (isset($visited[$cursor])) {
            throw new InvalidArgumentException('Finance account hierarchy cannot contain a cycle.');
        }
        $visited[$cursor] = true;
        $cursor = (string) $db->GetOne(
            "SELECT COALESCE(parent_account_key, '') FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key = ? AND account_status <> 'DELETED' LIMIT 1",
            [$companyKeyHash, $cursor]
        );
    }
}

function yovel_admin_install_finance_chart_template(ADOConnection $db, array $company, array $admin, string $templateCode, int $version, string $duplicatePolicy): array
{
    yovel_admin_finance_foundation_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $templateCode = strtoupper(trim($templateCode));
    $duplicatePolicy = strtoupper(trim($duplicatePolicy));
    if (!in_array($duplicatePolicy, ['FAIL','SKIP_EXISTING'], true)) {
        throw new InvalidArgumentException('Finance chart duplicate policy is invalid.');
    }
    $preview = yovel_admin_finance_chart_template_preview($company, $templateCode, $version);
    if ($preview['conflicts'] !== []) {
        throw new InvalidArgumentException('Finance chart template has an account number conflict.');
    }
    if ($duplicatePolicy === 'FAIL' && (int) $preview['existing_count'] > 0) {
        throw new InvalidArgumentException('Finance chart template contains existing account codes.');
    }

    return yovel_admin_finance_transaction($db, static function () use ($db, $companyKey, $hash, $adminKey, $templateCode, $version, $duplicatePolicy, $preview): array {
        $lockedRows = (array) $db->GetAll(
            "SELECT * FROM project_company_accounting_account WHERE company_key_hash = ? AND account_status <> 'DELETED' FOR UPDATE",
            [$hash]
        );
        $accountsByCode = [];
        $accountsByNumber = [];
        foreach ($lockedRows as $account) {
            $accountsByCode[(string) $account['account_code']] = $account;
            if ((string) ($account['account_number'] ?? '') !== '') $accountsByNumber[(string) $account['account_number']] = $account;
        }

        $insertedKeys = [];
        $skippedCount = 0;
        foreach ($preview['rows'] as $position => $row) {
            $code = (string) $row['account_code'];
            $number = (string) $row['account_number'];
            if (isset($accountsByCode[$code])) {
                if ($duplicatePolicy === 'FAIL') throw new InvalidArgumentException('Finance chart template contains existing account codes.');
                $skippedCount++;
                continue;
            }
            if (isset($accountsByNumber[$number])) {
                throw new InvalidArgumentException('Finance chart template has an account number conflict.');
            }
            $parentKey = null;
            $parentCode = (string) $row['parent_account_code'];
            if ($parentCode !== '') {
                $parent = $accountsByCode[$parentCode] ?? null;
                if (!is_array($parent) || (int) ($parent['is_group'] ?? 0) !== 1 || (string) ($parent['root_type'] ?? '') !== (string) $row['root_type']) {
                    throw new InvalidArgumentException('Finance chart template parent hierarchy is invalid.');
                }
                $parentKey = (string) $parent['account_key'];
            }
            $accountKey = bx_uuid();
            $reportType = yovel_admin_accounting_report_type_for_root((string) $row['root_type']);
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_accounting_account (account_key,company_key,company_key_hash,account_code,account_number,account_name,parent_account_key,root_type,report_type,account_type,account_currency,is_group,tax_rate,balance_must_be,freeze_account,include_in_gross,account_status,sort_order,account_notes,template_code,template_version,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NULL,'EITHER',0,0,'ACTIVE',?,NULL,?,?,?,?)",
                [$accountKey,$companyKey,$hash,$code,$number,(string)$row['account_name'],$parentKey,(string)$row['root_type'],$reportType,(string)$row['account_type']?:null,'PHP',(int)$row['is_group'],$position,$templateCode,$version,$adminKey,$adminKey],
                'Finance chart template account install'
            );
            $saved = $db->GetRow('SELECT * FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key = ?', [$hash,$accountKey]);
            if (!is_array($saved)
                || (string) $saved['account_code'] !== $code
                || (string) ($saved['account_number'] ?? '') !== $number
                || (string) ($saved['parent_account_key'] ?? '') !== (string) ($parentKey ?? '')
                || (string) ($saved['template_code'] ?? '') !== $templateCode
                || (int) ($saved['template_version'] ?? 0) !== $version
            ) {
                throw new RuntimeException('Finance chart template account read-back verification failed.');
            }
            $accountsByCode[$code] = $saved;
            $accountsByNumber[$number] = $saved;
            $insertedKeys[] = $accountKey;
        }

        $installation = $db->GetRow('SELECT chart_template_installation_key FROM project_company_finance_chart_template_installation WHERE company_key_hash = ? AND template_code = ? AND template_version = ? FOR UPDATE', [$hash,$templateCode,$version]);
        $installationKey = is_array($installation) && isset($installation['chart_template_installation_key']) ? (string) $installation['chart_template_installation_key'] : bx_uuid();
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_finance_chart_template_installation (chart_template_installation_key,company_key,company_key_hash,template_code,template_version,template_checksum,duplicate_policy,inserted_count,skipped_count,status,installed_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,'INSTALLED',?) ON DUPLICATE KEY UPDATE template_checksum=VALUES(template_checksum),duplicate_policy=VALUES(duplicate_policy),inserted_count=VALUES(inserted_count),skipped_count=VALUES(skipped_count),status='INSTALLED',installed_by_admin_key=VALUES(installed_by_admin_key),installed_at=CURRENT_TIMESTAMP",
            [$installationKey,$companyKey,$hash,$templateCode,$version,(string)$preview['checksum'],$duplicatePolicy,count($insertedKeys),$skippedCount,$adminKey],
            'Finance chart template installation save'
        );
        $savedInstallation = $db->GetRow('SELECT * FROM project_company_finance_chart_template_installation WHERE company_key_hash = ? AND chart_template_installation_key = ?', [$hash,$installationKey]);
        if (!is_array($savedInstallation)
            || (int) $savedInstallation['inserted_count'] !== count($insertedKeys)
            || (int) $savedInstallation['skipped_count'] !== $skippedCount
            || (string) $savedInstallation['template_checksum'] !== (string) $preview['checksum']
        ) {
            throw new RuntimeException('Finance chart template installation read-back verification failed.');
        }
        if ($insertedKeys !== []) {
            $marks = implode(',', array_fill(0, count($insertedKeys), '?'));
            $verified = (int) $db->GetOne(
                "SELECT COUNT(*) FROM project_company_accounting_account WHERE company_key_hash = ? AND template_code = ? AND template_version = ? AND account_key IN ({$marks})",
                array_merge([$hash,$templateCode,$version],$insertedKeys)
            );
            if ($verified !== count($insertedKeys)) throw new RuntimeException('Finance chart template exact read-back verification failed.');
        }
        bx_audit('INSTALL','project_company_finance_chart_template_installation',$installationKey,[
            'company_key'=>$companyKey,'template_code'=>$templateCode,'template_version'=>$version,
            'checksum'=>$preview['checksum'],'duplicate_policy'=>$duplicatePolicy,
            'inserted_count'=>count($insertedKeys),'skipped_count'=>$skippedCount,'admin_key'=>$adminKey,
        ],'Company administrator installed a versioned Finance chart template.');
        return $savedInstallation;
    });
}
