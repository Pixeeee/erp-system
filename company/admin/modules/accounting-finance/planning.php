<?php
declare(strict_types=1);

function yovel_admin_finance_planning_schema(): void
{
    $db = bx_db();
    foreach ([
        "CREATE TABLE IF NOT EXISTS project_company_finance_budget (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            budget_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            budget_code VARCHAR(80) NOT NULL,
            budget_name VARCHAR(180) NOT NULL,
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            cost_center VARCHAR(180) NULL,
            control_action ENUM('WARN','STOP') NOT NULL DEFAULT 'WARN',
            status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_budget (company_key_hash,budget_code),
            INDEX idx_project_company_finance_budget_period (company_key_hash,status,date_from,date_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_budget_line (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            budget_line_key CHAR(36) NOT NULL UNIQUE,
            budget_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            line_no INT UNSIGNED NOT NULL,
            account_key CHAR(36) NOT NULL,
            budget_amount DECIMAL(20,6) NOT NULL,
            monthly_distribution_json LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_budget_line (company_key_hash,budget_key,account_key),
            INDEX idx_project_company_finance_budget_account (company_key_hash,account_key,budget_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_period_close (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            period_close_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            reason VARCHAR(500) NOT NULL,
            profit_loss_amount DECIMAL(20,6) NOT NULL,
            retained_earnings_account_key CHAR(36) NOT NULL,
            close_transaction_key CHAR(36) NOT NULL,
            reversal_transaction_key CHAR(36) NULL,
            period_lock_key CHAR(36) NOT NULL,
            status ENUM('CLOSED','REOPENED') NOT NULL DEFAULT 'CLOSED',
            created_by_admin_key CHAR(36) NULL,
            reopened_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reopened_at TIMESTAMP NULL,
            UNIQUE KEY uq_project_company_finance_close_period (company_key_hash,date_from,date_to,status),
            INDEX idx_project_company_finance_close_status (company_key_hash,status,date_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ] as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Finance planning schema update');
    }
}

function yovel_admin_save_budget_record(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_finance_core_schema();
    yovel_admin_finance_planning_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $key = trim((string) ($input['budget_key'] ?? '')) ?: bx_uuid();
    $code = yovel_admin_code((string) ($input['budget_code'] ?? ''));
    $name = trim((string) ($input['budget_name'] ?? ''));
    $from = yovel_admin_optional_date((string) ($input['date_from'] ?? ''), 'Budget start date');
    $to = yovel_admin_optional_date((string) ($input['date_to'] ?? ''), 'Budget end date');
    $action = yovel_admin_status((string) ($input['control_action'] ?? 'WARN'), ['WARN', 'STOP'], 'WARN');
    $status = yovel_admin_status((string) ($input['status'] ?? 'ACTIVE'), ['DRAFT', 'ACTIVE', 'INACTIVE', 'DELETED'], 'ACTIVE');
    $lines = is_array($input['lines'] ?? null) ? $input['lines'] : [];
    if (!yovel_admin_is_uuid($key) || $code === '' || $name === '' || $from === '' || $to === '' || $to < $from || $lines === []) {
        throw new InvalidArgumentException('Budget code, name, dates, and lines are required.');
    }
    $prepared = [];
    foreach ($lines as $line) {
        $account = yovel_admin_finance_account_key($db, $hash, $line['account_key'] ?? '', 'Budget account');
        $amount = yovel_admin_finance_money($line['budget_amount'] ?? '0');
        if ($account === null || bccomp($amount, '0', 6) !== 1) {
            throw new InvalidArgumentException('Budget line account and positive amount are required.');
        }
        $prepared[$account] = ['account_key' => $account, 'amount' => $amount, 'distribution' => is_array($line['monthly_distribution'] ?? null) ? $line['monthly_distribution'] : []];
    }
    $db->BeginTrans();
    try {
        $existing = $db->GetRow('SELECT * FROM project_company_finance_budget WHERE company_key_hash=? AND budget_key=? FOR UPDATE', [$hash, $key]);
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_budget (budget_key,company_key,company_key_hash,budget_code,budget_name,date_from,date_to,cost_center,control_action,status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE budget_name=VALUES(budget_name),date_from=VALUES(date_from),date_to=VALUES(date_to),cost_center=VALUES(cost_center),control_action=VALUES(control_action),status=VALUES(status),updated_by_admin_key=VALUES(updated_by_admin_key)", [$key,$companyKey,$hash,$code,$name,$from,$to,substr(trim((string)($input['cost_center']??'')),0,180)?:null,$action,$status,$adminKey,$adminKey], 'Budget save');
        yovel_admin_db_execute($db, 'DELETE FROM project_company_finance_budget_line WHERE company_key_hash=? AND budget_key=?', [$hash,$key], 'Budget line reset');
        $lineNo=0;
        foreach ($prepared as $line) {
            yovel_admin_db_execute($db, 'INSERT INTO project_company_finance_budget_line (budget_line_key,budget_key,company_key,company_key_hash,line_no,account_key,budget_amount,monthly_distribution_json) VALUES (?,?,?,?,?,?,?,?)', [bx_uuid(),$key,$companyKey,$hash,++$lineNo,$line['account_key'],$line['amount'],$line['distribution']===[]?null:json_encode($line['distribution'],JSON_THROW_ON_ERROR)], 'Budget line save');
        }
        $saved = $db->GetRow('SELECT * FROM project_company_finance_budget WHERE company_key_hash=? AND budget_key=?', [$hash,$key]);
        $saved['lines']=$db->GetAll('SELECT * FROM project_company_finance_budget_line WHERE company_key_hash=? AND budget_key=? ORDER BY line_no',[$hash,$key]);
        if(count($saved['lines'])!==count($prepared))throw new RuntimeException('Budget read-back verification failed.');
        bx_audit($existing?'UPDATE':'CREATE','project_company_finance_budget',$key,['company_key'=>$companyKey,'budget_code'=>$code,'control_action'=>$action,'admin_key'=>$adminKey],'Company administrator saved a Finance budget.');
        $db->CommitTrans();
        return $saved;
    } catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_budget_actuals(array $company, string $budgetKey): array
{
    yovel_admin_finance_planning_schema();
    $hash=(string)$company['company_key_hash'];
    $header=bx_db()->GetRow('SELECT * FROM project_company_finance_budget WHERE company_key_hash=? AND budget_key=?',[$hash,$budgetKey]);
    if(!$header)throw new InvalidArgumentException('Budget was not found.');
    $lines=bx_db()->GetAll("SELECT l.*,a.account_code,a.account_name,a.root_type,COALESCE(SUM(CASE WHEN e.posting_date BETWEEN ? AND ? THEN e.debit-e.credit ELSE 0 END),0) raw_actual FROM project_company_finance_budget_line l JOIN project_company_accounting_account a ON a.account_key=l.account_key AND a.company_key_hash=l.company_key_hash LEFT JOIN project_company_general_ledger_entry e ON e.account_key=l.account_key AND e.company_key_hash=l.company_key_hash WHERE l.company_key_hash=? AND l.budget_key=? GROUP BY l.x_id ORDER BY l.line_no",[$header['date_from'],$header['date_to'],$hash,$budgetKey]);
    $totalBudget='0.000000';$totalActual='0.000000';
    foreach($lines as &$line){$raw=(string)$line['raw_actual'];$actual=(string)$line['root_type']==='INCOME'?yovel_admin_finance_money(bcmul($raw,'-1',6)):yovel_admin_finance_money($raw);$line['actual_amount']=$actual;$line['remaining_amount']=yovel_admin_finance_money(bcsub((string)$line['budget_amount'],$actual,6));$totalBudget=bcadd($totalBudget,(string)$line['budget_amount'],6);$totalActual=bcadd($totalActual,$actual,6);}unset($line);
    return ['budget'=>$header,'lines'=>$lines,'total_budget'=>yovel_admin_finance_money($totalBudget),'total_actual'=>yovel_admin_finance_money($totalActual),'total_remaining'=>yovel_admin_finance_money(bcsub($totalBudget,$totalActual,6))];
}

function yovel_admin_assert_budget_available(array $company,string $postingDate,string $accountKey,mixed $amount):void
{
    yovel_admin_finance_planning_schema();$postingDate=yovel_admin_optional_date($postingDate,'Posting date');$amount=yovel_admin_finance_money($amount);$hash=(string)$company['company_key_hash'];
    $rows=bx_db()->GetAll("SELECT b.budget_key,b.budget_name,b.control_action,l.budget_amount FROM project_company_finance_budget b JOIN project_company_finance_budget_line l ON l.budget_key=b.budget_key AND l.company_key_hash=b.company_key_hash WHERE b.company_key_hash=? AND b.status='ACTIVE' AND ? BETWEEN b.date_from AND b.date_to AND l.account_key=?",[$hash,$postingDate,$accountKey]);
    foreach($rows as $row){$actual=yovel_admin_budget_actuals($company,(string)$row['budget_key']);$line=array_values(array_filter($actual['lines'],fn(array $l):bool=>(string)$l['account_key']===$accountKey))[0]??null;if($line&&bccomp(bcadd((string)$line['actual_amount'],$amount,6),(string)$line['budget_amount'],6)===1&&(string)$row['control_action']==='STOP')throw new InvalidArgumentException('Budget control stopped this posting because it exceeds '.$row['budget_name'].'.');}
}

function yovel_admin_close_finance_period(ADOConnection $db,array $company,array $admin,array $input):array
{
    yovel_admin_finance_core_schema();yovel_admin_general_ledger_schema();yovel_admin_finance_planning_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$from=yovel_admin_optional_date((string)($input['date_from']??''),'Close start date');$to=yovel_admin_optional_date((string)($input['date_to']??''),'Close end date');$reason=substr(trim((string)($input['reason']??'')),0,500);if($from===''||$to===''||$to<$from||$reason==='')throw new InvalidArgumentException('Period close dates and reason are required.');yovel_admin_finance_assert_open_period($company,$to,false);$settings=yovel_admin_finance_settings($company,$admin);$retained=yovel_admin_finance_account_key($db,$hash,$settings['retained_earnings_account_key']??'','Retained earnings account',['EQUITY']);if($retained===null)throw new InvalidArgumentException('Retained earnings account is required.');
    $balances=$db->GetAll("SELECT a.account_key,a.root_type,COALESCE(SUM(e.debit-e.credit),0) balance FROM project_company_accounting_account a LEFT JOIN project_company_general_ledger_entry e ON e.account_key=a.account_key AND e.company_key_hash=a.company_key_hash AND e.posting_date BETWEEN ? AND ? WHERE a.company_key_hash=? AND a.root_type IN ('INCOME','EXPENSE') AND a.is_group=0 AND a.account_status='ACTIVE' GROUP BY a.account_key,a.root_type HAVING ABS(balance)>0.0000005",[$from,$to,$hash]);$entries=[];$debit='0.000000';$credit='0.000000';$profit='0.000000';foreach($balances as $b){$balance=yovel_admin_finance_money($b['balance']);if(bccomp($balance,'0',6)===1){$entries[]=['account_key'=>$b['account_key'],'debit'=>'0','credit'=>$balance];$credit=bcadd($credit,$balance,6);}else{$amount=yovel_admin_finance_money(bcmul($balance,'-1',6));$entries[]=['account_key'=>$b['account_key'],'debit'=>$amount,'credit'=>'0'];$debit=bcadd($debit,$amount,6);}$profit=bcsub($debit,$credit,6);}if($entries===[])throw new InvalidArgumentException('No Profit/Loss balances are available to close.');$difference=bcsub($debit,$credit,6);if(bccomp($difference,'0',6)===1)$entries[]=['account_key'=>$retained,'debit'=>'0','credit'=>$difference];else $entries[]=['account_key'=>$retained,'debit'=>yovel_admin_finance_money(bcmul($difference,'-1',6)),'credit'=>'0'];$closeKey=bx_uuid();$lockKey=bx_uuid();if($db->BeginTrans()===false)throw new RuntimeException('Period close transaction could not start.');try{$posted=yovel_admin_post_general_ledger_transaction($db,$company,$admin,['posting_date'=>$to,'voucher_type'=>'PERIOD_CLOSE','voucher_no'=>'CLOSE-'.$to,'source_module'=>'PERIOD_CLOSE','source_record_key'=>$closeKey,'remarks'=>$reason,'entries'=>$entries],false,false);yovel_admin_db_execute($db,"INSERT INTO project_company_finance_period_lock(period_lock_key,company_key,company_key_hash,lock_type,date_from,date_to,reason,close_transaction_key,status,created_by_admin_key) VALUES(?,?,?,'HARD_CLOSE',?,?,?,?, 'ACTIVE',?)",[$lockKey,$companyKey,$hash,$from,$to,$reason,$posted['transaction_key'],$adminKey],'Period lock create');yovel_admin_db_execute($db,"INSERT INTO project_company_finance_period_close(period_close_key,company_key,company_key_hash,date_from,date_to,reason,profit_loss_amount,retained_earnings_account_key,close_transaction_key,period_lock_key,status,created_by_admin_key) VALUES(?,?,?,?,?,?,?,?,?,?,'CLOSED',?)",[$closeKey,$companyKey,$hash,$from,$to,$reason,yovel_admin_finance_money($profit),$retained,$posted['transaction_key'],$lockKey,$adminKey],'Period close save');$saved=$db->GetRow('SELECT * FROM project_company_finance_period_close WHERE company_key_hash=? AND period_close_key=?',[$hash,$closeKey]);bx_audit('CLOSE','project_company_finance_period_close',$closeKey,['company_key'=>$companyKey,'date_from'=>$from,'date_to'=>$to,'profit_loss_amount'=>$profit,'admin_key'=>$adminKey],'Company administrator closed a Finance period.');$db->CommitTrans();return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_reopen_finance_period(ADOConnection $db,array $company,array $admin,string $closeKey,string $postingDate,string $reason):array
{
    yovel_admin_finance_core_schema();yovel_admin_general_ledger_schema();yovel_admin_finance_planning_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$postingDate=yovel_admin_optional_date($postingDate,'Reopen posting date');if(!yovel_admin_is_uuid($closeKey)||$postingDate===''||trim($reason)==='')throw new InvalidArgumentException('Period reopen reference, date, and reason are required.');yovel_admin_finance_assert_open_period($company,$postingDate,false);$db->BeginTrans();try{$close=$db->GetRow("SELECT * FROM project_company_finance_period_close WHERE company_key_hash=? AND period_close_key=? AND status='CLOSED' FOR UPDATE",[$hash,$closeKey]);if(!$close)throw new InvalidArgumentException('Closed Finance period was not found.');$reversal=yovel_admin_reverse_general_ledger_transaction($db,$company,$admin,(string)$close['close_transaction_key'],$postingDate,trim($reason),false,false);yovel_admin_db_execute($db,"UPDATE project_company_finance_period_close SET status='REOPENED',reversal_transaction_key=?,reopened_by_admin_key=?,reopened_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND period_close_key=?",[$reversal['transaction_key'],$adminKey,$hash,$closeKey],'Period close reopen');yovel_admin_db_execute($db,"UPDATE project_company_finance_period_lock SET status='REOPENED',reopened_by_admin_key=?,reopened_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND period_lock_key=?",[$adminKey,$hash,$close['period_lock_key']],'Period lock reopen');$saved=$db->GetRow('SELECT * FROM project_company_finance_period_close WHERE company_key_hash=? AND period_close_key=?',[$hash,$closeKey]);bx_audit('REOPEN','project_company_finance_period_close',$closeKey,['company_key'=>$companyKey,'reversal_transaction_key'=>$reversal['transaction_key'],'admin_key'=>$adminKey],'Company administrator reopened a Finance period through reversal.');$db->CommitTrans();return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_period_closings(array $company):array{yovel_admin_finance_planning_schema();$r=bx_db()->GetAll('SELECT * FROM project_company_finance_period_close WHERE company_key_hash=? ORDER BY date_to DESC,x_id DESC',[(string)($company['company_key_hash']??'')]);return is_array($r)?$r:[];}
function yovel_admin_finance_budgets(array $company):array{yovel_admin_finance_planning_schema();$r=bx_db()->GetAll("SELECT * FROM project_company_finance_budget WHERE company_key_hash=? AND status<>'DELETED' ORDER BY date_from DESC,x_id DESC",[(string)($company['company_key_hash']??'')]);return is_array($r)?$r:[];}

function yovel_admin_save_budget_action(array $company,array $admin):string{return yovel_admin_finance_run_form_action($company,'save_finance_budget',static function()use($company,$admin):string{$input=$_POST;$input['lines']=json_decode((string)($_POST['lines_json']??'[]'),true,512,JSON_THROW_ON_ERROR);$saved=yovel_admin_save_budget_record(bx_db(),$company,$admin,$input);$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['budget_key'];return 'Budget saved.';});}
function yovel_admin_close_period_action(array $company,array $admin):string{return yovel_admin_finance_run_form_action($company,'close_finance_period',static function()use($company,$admin):string{$saved=yovel_admin_close_finance_period(bx_db(),$company,$admin,$_POST);$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['period_close_key'];return 'Finance period closed and Profit/Loss transferred.';});}
function yovel_admin_reopen_period_action(array $company,array $admin):string{return yovel_admin_finance_run_form_action($company,'reopen_finance_period',static function()use($company,$admin):string{$saved=yovel_admin_reopen_finance_period(bx_db(),$company,$admin,(string)($_POST['period_close_key']??''),(string)($_POST['reopen_posting_date']??date('Y-m-d')),(string)($_POST['reason']??''));$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['period_close_key'];return 'Finance period reopened through a General Ledger reversal.';});}
