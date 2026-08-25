<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/core.php';
require_once $root . '/company/admin/modules/accounting-finance/ledger.php';
require_once $root . '/company/admin/modules/accounting-finance/journals.php';

function finance_wp02_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function finance_wp02_error(callable $callback, string $needle): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        finance_wp02_assert(str_contains(strtolower($error->getMessage()), strtolower($needle)), 'Unexpected AF-WP02 error: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected AF-WP02 error containing: ' . $needle);
}

foreach ([
    'yovel_admin_persist_journal_template',
    'yovel_admin_apply_journal_template',
    'yovel_admin_amend_journal_entry',
    'yovel_admin_general_ledger_health',
    'yovel_admin_persist_ledger_health_monitor',
    'yovel_admin_merge_general_ledger_accounts',
    'yovel_admin_repost_general_ledger_transaction',
] as $function) {
    finance_wp02_assert(function_exists($function), 'Missing AF-WP02 service: ' . $function);
}

yovel_admin_accounting_finance_schema();
yovel_admin_finance_core_schema();
yovel_admin_general_ledger_schema();
yovel_admin_finance_journal_schema();
$db = bx_db();
$fixture = $db->GetRow("SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key FROM project_company c JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE' WHERE c.company_status<>'DELETED' ORDER BY c.x_id,a.x_id LIMIT 1");
finance_wp02_assert(is_array($fixture) && $fixture !== [], 'An active company-admin fixture is required.');
$company = ['company_key'=>(string)$fixture['company_key'],'company_key_hash'=>(string)$fixture['company_key_hash'],'company_name'=>(string)$fixture['company_name']];
$admin = ['admin_key'=>(string)$fixture['admin_key']];
$hash = $company['company_key_hash'];
$suffix = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$keys = ['usd'=>bx_uuid(),'php'=>bx_uuid(),'target'=>bx_uuid(),'journal'=>bx_uuid(),'intercompany'=>bx_uuid(),'template'=>bx_uuid(),'monitor'=>bx_uuid(),'merge'=>bx_uuid(),'repost'=>bx_uuid(),'rollback'=>bx_uuid(),'invalid'=>bx_uuid(),'invalid_entry'=>bx_uuid()];
$accountSql = "INSERT INTO project_company_accounting_account (account_key,company_key,company_key_hash,account_code,account_name,root_type,report_type,account_type,account_currency,is_group,balance_must_be,account_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,0,'EITHER','ACTIVE',?,?)";
foreach ([['usd','USD','ASSET','BALANCE_SHEET','Bank','USD'],['php','PHP','LIABILITY','BALANCE_SHEET','Payable','PHP'],['target','TARGET','ASSET','BALANCE_SHEET','Bank','PHP']] as [$name,$code,$rootType,$reportType,$accountType,$currency]) {
    $db->Execute($accountSql,[$keys[$name],$company['company_key'],$hash,$code.'_'.$suffix,$code.' '.$suffix,$rootType,$reportType,$accountType,$currency,$admin['admin_key'],$admin['admin_key']]);
}

try {
    $template = yovel_admin_persist_journal_template($db,$company,$admin,[
        'journal_template_key'=>$keys['template'],'template_name'=>'FX Template '.$suffix,'entry_type'=>'ADJUSTMENT','status'=>'ACTIVE',
        'accounts'=>[['account_key'=>$keys['usd'],'party_type'=>'CUSTOMER','party'=>'Acme','finance_book'=>'STATUTORY','cost_center'=>'MAIN'],['account_key'=>$keys['php'],'finance_book'=>'STATUTORY']],
    ]);
    finance_wp02_assert(count($template['accounts'])===2 && $template['accounts'][0]['party']==='Acme', 'Journal Entry Template account read-back failed.');
    $applied = yovel_admin_apply_journal_template($company,$keys['template']);
    finance_wp02_assert($applied['entry_type']==='ADJUSTMENT' && count($applied['rows'])===2, 'Journal Entry Template application failed.');

    $draft = yovel_admin_persist_journal_entry($db,$company,$admin,[
        'journal_entry_key'=>$keys['journal'],'journal_no'=>'JV-FX-'.$suffix,'posting_date'=>'2026-08-25','entry_type'=>'ADJUSTMENT','remarks'=>'AF-WP02 FX journal',
        'rows'=>[
            ['account_key'=>$keys['usd'],'transaction_currency'=>'USD','transaction_debit'=>'100','transaction_credit'=>'0','exchange_rate'=>'56','debit'=>'5600','credit'=>'0','party_type'=>'CUSTOMER','party'=>'Acme','finance_book'=>'STATUTORY','cost_center'=>'MAIN','dimensions'=>['branch'=>'MNL']],
            ['account_key'=>$keys['php'],'transaction_currency'=>'PHP','transaction_debit'=>'0','transaction_credit'=>'5600','exchange_rate'=>'1','debit'=>'0','credit'=>'5600','finance_book'=>'STATUTORY'],
        ],
    ]);
    finance_wp02_assert($draft['rows'][0]['transaction_debit']==='100.000000' && $draft['rows'][0]['dimensions']['branch']==='MNL', 'Journal multi-currency or dimensions were not read back exactly.');
    $submitted = yovel_admin_submit_journal_entry($db,$company,$admin,$keys['journal']);
    finance_wp02_assert($submitted['document_status']==='SUBMITTED', 'Multi-currency Journal submission failed.');
    $glFx = $db->GetRow('SELECT CAST(transaction_debit AS CHAR) transaction_debit,CAST(debit AS CHAR) debit,transaction_currency,finance_book,party,dimensions_json FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=? AND line_no=1',[$hash,$submitted['gl_transaction_key']]);
    finance_wp02_assert(number_format((float)$glFx['transaction_debit'],6,'.','')==='100.000000' && number_format((float)$glFx['debit'],6,'.','')==='5600.000000' && $glFx['transaction_currency']==='USD', 'General Ledger foreign/base amounts are incorrect.');
    finance_wp02_assert(json_decode((string)$glFx['dimensions_json'],true)['branch']==='MNL', 'General Ledger dimensions were not snapshotted.');
    $idempotentPayload=['posting_date'=>'2026-08-25','voucher_type'=>'IDEMPOTENCY','voucher_no'=>'IDEMP-'.$suffix,'source_module'=>'AF_WP02','idempotency_key'=>'af-wp02-'.$suffix,'entries'=>[['account_key'=>$keys['target'],'debit'=>'3','credit'=>'0'],['account_key'=>$keys['php'],'debit'=>'0','credit'=>'3']]];
    $idempotentA=yovel_admin_post_general_ledger_transaction($db,$company,$admin,$idempotentPayload);$idempotentB=yovel_admin_post_general_ledger_transaction($db,$company,$admin,$idempotentPayload);
    finance_wp02_assert($idempotentA['transaction_key']===$idempotentB['transaction_key'] && $idempotentB['idempotent']===true, 'General Ledger idempotent retry did not return the exact committed transaction.');
    $originalRows = json_encode($db->GetAll('SELECT * FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=? ORDER BY line_no',[$hash,$submitted['gl_transaction_key']]), JSON_THROW_ON_ERROR);
    yovel_admin_cancel_journal_entry($db,$company,$admin,$keys['journal'],'2026-08-26','Amend FX journal');
    $amendment = yovel_admin_amend_journal_entry($db,$company,$admin,$keys['journal'],['journal_no'=>'JV-FX-A1-'.$suffix,'posting_date'=>'2026-08-27']);
    finance_wp02_assert($amendment['document_status']==='DRAFT' && $amendment['amends_journal_entry_key']===$keys['journal'] && (int)$amendment['amendment_index']===1, 'Journal amendment chain failed.');
    finance_wp02_assert(json_encode($db->GetAll('SELECT * FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=? ORDER BY line_no',[$hash,$submitted['gl_transaction_key']]), JSON_THROW_ON_ERROR)===$originalRows, 'Amendment mutated submitted General Ledger rows.');

    finance_wp02_error(fn()=>yovel_admin_persist_journal_entry($db,$company,$admin,[
        'journal_entry_key'=>$keys['intercompany'],'posting_date'=>'2026-08-25','inter_company'=>1,
        'rows'=>[['account_key'=>$keys['usd'],'debit'=>'1','credit'=>'0'],['account_key'=>$keys['php'],'debit'=>'0','credit'=>'1']],
    ]),'counterparty');
    finance_wp02_error(fn()=>yovel_admin_persist_ledger_health_monitor($db,$company,['admin_key'=>bx_uuid()],['monitor_name'=>'Unauthorized '.$suffix]),'authorized');

    $monitor = yovel_admin_persist_ledger_health_monitor($db,$company,$admin,['ledger_health_monitor_key'=>$keys['monitor'],'monitor_name'=>'Daily '.$suffix,'schedule'=>'DAILY','enabled'=>1]);
    finance_wp02_assert((int)$monitor['enabled']===1, 'Ledger Health Monitor did not read back.');
    $db->Execute("INSERT INTO project_company_general_ledger_transaction (transaction_key,company_key,company_key_hash,posting_date,fiscal_year,voucher_type,voucher_no,entry_count,total_debit,total_credit,created_by_admin_key) VALUES (?,?,?,'2026-08-25',2026,'INVALID_PROBE',?,1,1,1,?)",[$keys['invalid'],$company['company_key'],$hash,'INVALID-'.$suffix,$admin['admin_key']]);
    $db->Execute("INSERT INTO project_company_general_ledger_entry (ledger_entry_key,transaction_key,line_no,company_key,company_key_hash,posting_date,fiscal_year,account_key,account_code,account_name,voucher_type,voucher_no,debit,credit,exchange_rate,created_by_admin_key) SELECT ?,?,1,?,?, '2026-08-25',2026,account_key,account_code,account_name,'INVALID_PROBE',?,1,1,1,? FROM project_company_accounting_account WHERE company_key_hash=? AND account_key=?",[$keys['invalid_entry'],$keys['invalid'],$company['company_key'],$hash,'INVALID-'.$suffix,$admin['admin_key'],$hash,$keys['target']]);
    $health = yovel_admin_general_ledger_health($db,$company,$admin,['persist'=>true,'monitor_key'=>$keys['monitor']]);
    finance_wp02_assert($health['status']==='ISSUES' && in_array('INVALID_ENTRY',array_column($health['findings'],'code'),true), 'Ledger Health invalid-entry detection failed.');

    $seed = yovel_admin_post_general_ledger_transaction($db,$company,$admin,[
        'posting_date'=>'2026-08-25','voucher_type'=>'MERGE_SEED','voucher_no'=>'MERGE-'.$suffix,'source_module'=>'AF_WP02','entries'=>[['account_key'=>$keys['usd'],'debit'=>'20','credit'=>'0'],['account_key'=>$keys['php'],'debit'=>'0','credit'=>'20']],
    ]);
    finance_wp02_error(fn()=>yovel_admin_merge_general_ledger_accounts($db,$company,$admin,['ledger_merge_key'=>$keys['merge'],'source_account_key'=>$keys['usd'],'target_account_key'=>$keys['target'],'posting_date'=>'2026-08-28','reason'=>'Consolidate accounts'],static function(string $checkpoint):void{if($checkpoint==='after_first_entry')throw new RuntimeException('merge rollback injection');}),'merge rollback injection');
    finance_wp02_assert((int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_ledger_merge WHERE company_key_hash=? AND ledger_merge_key=?',[$hash,$keys['merge']])===0, 'Ledger Merge rollback retained its request.');
    $merge = yovel_admin_merge_general_ledger_accounts($db,$company,$admin,['ledger_merge_key'=>$keys['merge'],'source_account_key'=>$keys['usd'],'target_account_key'=>$keys['target'],'posting_date'=>'2026-08-28','reason'=>'Consolidate accounts']);
    finance_wp02_assert($merge['status']==='COMPLETED' && yovel_admin_is_uuid((string)$merge['transaction_key']), 'Controlled Ledger Merge failed.');
    finance_wp02_assert((int)$db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=?',[$hash,$seed['transaction_key']])===2, 'Ledger Merge mutated source history.');

    $repostSeed = yovel_admin_post_general_ledger_transaction($db,$company,$admin,[
        'posting_date'=>'2026-08-25','voucher_type'=>'REPOST_SEED','voucher_no'=>'REPOST-'.$suffix,'source_module'=>'AF_WP02','entries'=>[['account_key'=>$keys['target'],'debit'=>'7','credit'=>'0'],['account_key'=>$keys['php'],'debit'=>'0','credit'=>'7']],
    ]);
    finance_wp02_error(fn()=>yovel_admin_repost_general_ledger_transaction($db,$company,$admin,['repost_key'=>$keys['repost'],'transaction_key'=>$repostSeed['transaction_key'],'posting_date'=>'2026-08-29','reason'=>'Controlled repost'],static function(string $checkpoint):void{if($checkpoint==='after_first_entry')throw new RuntimeException('repost rollback injection');}),'repost rollback injection');
    finance_wp02_assert((int)$db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND reversal_of_transaction_key=?',[$hash,$repostSeed['transaction_key']])===0, 'Ledger repost rollback retained its additive reversal.');
    $repost = yovel_admin_repost_general_ledger_transaction($db,$company,$admin,['repost_key'=>$keys['repost'],'transaction_key'=>$repostSeed['transaction_key'],'posting_date'=>'2026-08-29','reason'=>'Controlled repost']);
    finance_wp02_assert($repost['status']==='COMPLETED' && yovel_admin_is_uuid((string)$repost['reversal_transaction_key']) && yovel_admin_is_uuid((string)$repost['replacement_transaction_key']), 'Controlled Ledger repost failed.');
    finance_wp02_assert((int)$db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=?',[$hash,$repostSeed['transaction_key']])===2, 'Ledger repost mutated source history.');

    finance_wp02_error(function() use($db,$company,$admin,$keys,$suffix): void {
        yovel_admin_post_general_ledger_transaction($db,$company,$admin,[
            'transaction_key'=>$keys['rollback'],'posting_date'=>'2026-08-25','voucher_type'=>'ROLLBACK','voucher_no'=>'ROLLBACK-'.$suffix,'source_module'=>'AF_WP02',
            'entries'=>[['account_key'=>$keys['target'],'debit'=>'1','credit'=>'0'],['account_key'=>$keys['php'],'debit'=>'0','credit'=>'1']],
        ],true,true,static function(string $checkpoint): void { if($checkpoint==='after_first_entry') throw new RuntimeException('rollback injection'); });
    },'rollback injection');
    finance_wp02_assert((int)$db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND transaction_key=?',[$hash,$keys['rollback']])===0, 'Injected ledger failure did not roll back the header.');
    finance_wp02_assert((int)$db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=?',[$hash,$keys['rollback']])===0, 'Injected ledger failure did not roll back entries.');

    $view = file_get_contents($root.'/company/admin/modules/accounting-finance/views/journal-entries.php');
    $ledgerView = file_get_contents($root.'/company/admin/modules/accounting-finance/views/general-ledger.php');
    foreach (['finance-journal-template-modal','finance-journal-repair-modal','data-confirm-submit'] as $marker) finance_wp02_assert(str_contains($view,$marker), 'Journal modal contract missing '.$marker);
    foreach (['finance-ledger-health-modal','finance-ledger-merge-modal','finance-ledger-repost-modal','data-confirm-submit'] as $marker) finance_wp02_assert(str_contains($ledgerView,$marker), 'Ledger modal contract missing '.$marker);
} finally {
    $db->Execute('DELETE FROM project_company_finance_ledger_health_finding WHERE company_key_hash=? AND ledger_health_run_key IN (SELECT ledger_health_run_key FROM project_company_finance_ledger_health_run WHERE company_key_hash=? AND monitor_key=?)',[$hash,$hash,$keys['monitor']]);
    $db->Execute('DELETE FROM project_company_finance_ledger_health_run WHERE company_key_hash=? AND monitor_key=?',[$hash,$keys['monitor']]);
    $db->Execute('DELETE FROM project_company_finance_ledger_health_monitor WHERE company_key_hash=? AND ledger_health_monitor_key=?',[$hash,$keys['monitor']]);
    $db->Execute('DELETE FROM project_company_finance_ledger_repost_item WHERE company_key_hash=? AND repost_key=?',[$hash,$keys['repost']]);
    $db->Execute('DELETE FROM project_company_finance_ledger_repost WHERE company_key_hash=? AND repost_key=?',[$hash,$keys['repost']]);
    $db->Execute('DELETE FROM project_company_finance_ledger_merge_account WHERE company_key_hash=? AND ledger_merge_key=?',[$hash,$keys['merge']]);
    $db->Execute('DELETE FROM project_company_finance_ledger_merge WHERE company_key_hash=? AND ledger_merge_key=?',[$hash,$keys['merge']]);
    $db->Execute('DELETE FROM project_company_finance_journal_template_account WHERE company_key_hash=? AND journal_template_key=?',[$hash,$keys['template']]);
    $db->Execute('DELETE FROM project_company_finance_journal_template WHERE company_key_hash=? AND journal_template_key=?',[$hash,$keys['template']]);
    $db->Execute("DELETE FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key IN (SELECT transaction_key FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND (voucher_no LIKE ? OR operation_key IN (?,?)))",[$hash,$hash,'%'.$suffix.'%',$keys['merge'],$keys['repost']]);
    $db->Execute("DELETE FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND (voucher_no LIKE ? OR operation_key IN (?,?))",[$hash,'%'.$suffix.'%',$keys['merge'],$keys['repost']]);
    $db->Execute('DELETE FROM project_company_finance_journal_row WHERE company_key_hash=? AND (journal_entry_key=? OR journal_entry_key IN (SELECT journal_entry_key FROM project_company_finance_journal_entry WHERE company_key_hash=? AND amends_journal_entry_key=?))',[$hash,$keys['journal'],$hash,$keys['journal']]);
    $db->Execute('DELETE FROM project_company_finance_journal_entry WHERE company_key_hash=? AND (journal_entry_key IN (?,?) OR amends_journal_entry_key=?)',[$hash,$keys['journal'],$keys['intercompany'],$keys['journal']]);
    $db->Execute('DELETE FROM project_company_accounting_account WHERE company_key_hash=? AND account_key IN (?,?,?)',[$hash,$keys['usd'],$keys['php'],$keys['target']]);
}

echo "Accounting/Finance AF-WP02 journal and ledger parity checks passed.\n";
