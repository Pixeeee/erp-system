<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/core.php';
require_once $root . '/company/admin/modules/accounting-finance/ledger.php';
require_once __DIR__ . '/accounting-finance-test-helper.php';

function finance_foundation_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function finance_foundation_expect_error(callable $operation, string $needle): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        finance_foundation_assert(str_contains(strtolower($error->getMessage()), strtolower($needle)), 'Unexpected validation message: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected an error containing: ' . $needle);
}

finance_foundation_assert(function_exists('yovel_admin_finance_foundation_schema'), 'AF-WP01 foundation schema service is missing.');
yovel_admin_accounting_finance_schema();
yovel_admin_general_ledger_schema();
yovel_admin_finance_foundation_schema();
$db = bx_db();

$fixture = $db->GetRow(
    "SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key
       FROM project_company c
       JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE'
      WHERE c.company_status<>'DELETED'
      ORDER BY c.x_id,a.x_id LIMIT 1"
);
finance_foundation_assert(is_array($fixture) && $fixture !== [], 'An active company-admin fixture is required.');
$company = ['company_key'=>(string)$fixture['company_key'],'company_key_hash'=>(string)$fixture['company_key_hash'],'company_name'=>(string)$fixture['company_name']];
$admin = ['admin_key'=>(string)$fixture['admin_key']];
$hash = $company['company_key_hash'];
$suffix = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 9));

$tables = [
    'project_company_finance_account_category', 'project_company_finance_account_closing_balance',
    'project_company_finance_dimension_filter', 'project_company_finance_dimension_filter_account',
    'project_company_finance_dimension_filter_value', 'project_company_finance_accounting_period',
    'project_company_finance_fiscal_year', 'project_company_finance_book',
    'project_company_finance_monthly_distribution', 'project_company_finance_monthly_distribution_line',
    'project_company_finance_cost_center_allocation', 'project_company_finance_cost_center_allocation_line',
    'project_company_finance_exchange_setting', 'project_company_finance_exchange_rate',
    'project_company_finance_chart_template', 'project_company_finance_chart_template_installation',
];
foreach ($tables as $table) {
    finance_foundation_assert((int)$db->GetOne('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [BUILDERX_DB_NAME,$table]) === 1, 'Missing AF-WP01 table: '.$table);
}

$categoryKey = bx_uuid();
$periodKey = bx_uuid();
$yearKey = bx_uuid();
$bookKey = bx_uuid();
$distributionKey = bx_uuid();
$sourceCostCenterKey = bx_uuid();
$targetCostCenterKey = bx_uuid();
$allocationKey = bx_uuid();
$dimensionKey = bx_uuid();
$dimensionValueKey = bx_uuid();
$filterKey = bx_uuid();
$exchangeKey = bx_uuid();
$closingKey = bx_uuid();
$postedAccountKey = bx_uuid();
$counterAccountKey = bx_uuid();
$transactionKey = '';
$templateCode = 'PH_STANDARD';
$originalTemplateAccounts = finance_test_snapshot_rows($db, 'project_company_accounting_account', 'company_key_hash=? AND template_code=?', [$hash,$templateCode]);
$originalInstallations = finance_test_snapshot_rows($db, 'project_company_finance_chart_template_installation', 'company_key_hash=? AND template_code=?', [$hash,$templateCode]);

try {
    finance_foundation_expect_error(
        fn () => yovel_admin_persist_finance_foundation_master($db,$company,['admin_key'=>bx_uuid()],'finance-book',['finance_book_name'=>'Unauthorized']),
        'authorized'
    );

    $category = yovel_admin_persist_finance_foundation_master($db,$company,$admin,'account-category',[
        'account_category_key'=>$categoryKey,'account_category_name'=>'Current Assets '.$suffix,'root_type'=>'ASSET','description'=>'Liquidity classification','status'=>'ACTIVE',
    ]);
    $categoryUpdated = yovel_admin_persist_finance_foundation_master($db,$company,$admin,'account-category',[
        'account_category_key'=>$categoryKey,'account_category_name'=>'Current Assets '.$suffix,'root_type'=>'ASSET','description'=>'Updated classification','status'=>'ACTIVE',
    ]);
    finance_foundation_assert((string)$categoryUpdated['account_category_key']===$categoryKey && (string)$categoryUpdated['description']==='Updated classification', 'Account Category stable update/read-back failed.');
    finance_foundation_expect_error(fn () => yovel_admin_persist_finance_foundation_master($db,$company,$admin,'account-category',[
        'account_category_key'=>bx_uuid(),'account_category_name'=>'Current Assets '.$suffix,'root_type'=>'ASSET',
    ]), 'already exists');

    $year = yovel_admin_persist_finance_foundation_master($db,$company,$admin,'fiscal-year',[
        'fiscal_year_key'=>$yearKey,'year_name'=>'FY-'.$suffix,'year_start_date'=>'2026-01-01','year_end_date'=>'2026-12-31','status'=>'ACTIVE',
    ]);
    finance_foundation_assert((string)$year['year_start_date']==='2026-01-01', 'Fiscal Year read-back failed.');
    $period = yovel_admin_persist_finance_foundation_master($db,$company,$admin,'accounting-period',[
        'accounting_period_key'=>$periodKey,'period_name'=>'Q1-'.$suffix,'start_date'=>'2026-01-01','end_date'=>'2026-03-31','status'=>'ACTIVE','closed_document_types'=>['SALES_INVOICE','PURCHASE_INVOICE'],
    ]);
    finance_foundation_assert(str_contains((string)$period['closed_document_types_json'],'SALES_INVOICE'), 'Accounting Period closed-document scope was not persisted.');
    $book = yovel_admin_persist_finance_foundation_master($db,$company,$admin,'finance-book',[
        'finance_book_key'=>$bookKey,'finance_book_name'=>'Statutory '.$suffix,'is_default'=>1,'status'=>'ACTIVE',
    ]);
    finance_foundation_assert((int)$book['is_default']===1, 'Finance Book default state was not persisted.');

    $distribution = yovel_admin_persist_finance_foundation_master($db,$company,$admin,'monthly-distribution',[
        'monthly_distribution_key'=>$distributionKey,'distribution_name'=>'Even '.$suffix,'fiscal_year_key'=>$yearKey,
        'percentages'=>array_fill(1,12,'8.333333'), 'status'=>'ACTIVE',
    ]);
    finance_foundation_assert(count($distribution['percentages'])===12, 'Monthly Distribution did not read back 12 months.');
    finance_foundation_expect_error(fn () => yovel_admin_persist_finance_foundation_master($db,$company,$admin,'monthly-distribution',[
        'distribution_name'=>'Bad '.$suffix,'fiscal_year_key'=>$yearKey,'percentages'=>array_fill(1,12,'5'),
    ]), '100');

    yovel_admin_persist_finance_master($db,$company,$admin,'cost-center',['cost_center_key'=>$sourceCostCenterKey,'cost_center_code'=>'SRC_'.$suffix,'cost_center_name'=>'Source '.$suffix,'status'=>'ACTIVE']);
    yovel_admin_persist_finance_master($db,$company,$admin,'cost-center',['cost_center_key'=>$targetCostCenterKey,'cost_center_code'=>'TGT_'.$suffix,'cost_center_name'=>'Target '.$suffix,'status'=>'ACTIVE']);
    $allocation = yovel_admin_persist_finance_foundation_master($db,$company,$admin,'cost-center-allocation',[
        'cost_center_allocation_key'=>$allocationKey,'main_cost_center_key'=>$sourceCostCenterKey,'valid_from'=>'2026-01-01',
        'allocations'=>[['cost_center_key'=>$sourceCostCenterKey,'percentage'=>'25'],['cost_center_key'=>$targetCostCenterKey,'percentage'=>'75']], 'status'=>'ACTIVE',
    ]);
    finance_foundation_assert(count($allocation['allocations'])===2, 'Cost Center Allocation lines were not read back.');

    yovel_admin_persist_finance_master($db,$company,$admin,'accounting-dimension',[
        'dimension_key'=>$dimensionKey,'dimension_code'=>'REGION_'.$suffix,'dimension_name'=>'Region '.$suffix,'reference_type'=>'CUSTOM','status'=>'ACTIVE',
        'values'=>[['dimension_value_key'=>$dimensionValueKey,'value_code'=>'NCR','value_name'=>'NCR','status'=>'ACTIVE']],
    ]);

    $insertAccount = "INSERT INTO project_company_accounting_account (account_key,company_key,company_key_hash,account_code,account_name,root_type,report_type,account_currency,is_group,balance_must_be,account_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,'PHP',0,'EITHER','ACTIVE',?,?)";
    $db->Execute($insertAccount,[$postedAccountKey,$company['company_key'],$hash,'POST_'.$suffix,'Posted '.$suffix,'ASSET','BALANCE_SHEET',$admin['admin_key'],$admin['admin_key']]);
    $db->Execute($insertAccount,[$counterAccountKey,$company['company_key'],$hash,'COUNTER_'.$suffix,'Counter '.$suffix,'EQUITY','BALANCE_SHEET',$admin['admin_key'],$admin['admin_key']]);

    $filter = yovel_admin_persist_finance_foundation_master($db,$company,$admin,'dimension-filter',[
        'dimension_filter_key'=>$filterKey,'dimension_key'=>$dimensionKey,'allow_or_restrict'=>'ALLOW','apply_restriction_on_values'=>1,
        'account_keys'=>[$postedAccountKey],'dimension_value_keys'=>[$dimensionValueKey],'status'=>'ACTIVE',
    ]);
    finance_foundation_assert(count($filter['account_keys'])===1 && count($filter['dimension_value_keys'])===1, 'Dimension filter allowlists were not read back.');
    finance_foundation_expect_error(fn () => yovel_admin_persist_finance_foundation_master($db,['company_key'=>$company['company_key'],'company_key_hash'=>hash('sha256','other-company')],$admin,'dimension-filter',[
        'dimension_key'=>$dimensionKey,'account_keys'=>[$postedAccountKey],'dimension_value_keys'=>[$dimensionValueKey],
    ]), 'authorized');

    $exchange = yovel_admin_persist_finance_foundation_master($db,$company,$admin,'currency-exchange-setting',[
        'exchange_setting_key'=>$exchangeKey,'service_provider'=>'CUSTOM','api_endpoint'=>'https://rates.example.test/{transaction_date}','base_currency'=>'PHP','status'=>'ACTIVE',
        'rates'=>[['from_currency'=>'USD','to_currency'=>'PHP','transaction_date'=>'2026-08-25','exchange_rate'=>'57.125']],
    ]);
    finance_foundation_assert((string)$exchange['rates'][0]['exchange_rate']==='57.125000000', 'Currency exchange rate precision/read-back failed.');

    $closing = yovel_admin_persist_finance_foundation_master($db,$company,$admin,'account-closing-balance',[
        'account_closing_balance_key'=>$closingKey,'closing_date'=>'2025-12-31','account_key'=>$postedAccountKey,'debit'=>'1250','credit'=>'0','account_currency'=>'PHP','debit_in_account_currency'=>'1250','credit_in_account_currency'=>'0','status'=>'ACTIVE',
    ]);
    finance_foundation_assert((string)$closing['debit']==='1250.000000', 'Account Closing Balance exact read-back failed.');

    $previewA = yovel_admin_finance_chart_template_preview($company,$templateCode,1);
    $previewB = yovel_admin_finance_chart_template_preview($company,$templateCode,1);
    finance_foundation_assert($previewA['checksum']===$previewB['checksum'] && $previewA['rows']===$previewB['rows'], 'Philippine chart preview is not deterministic.');
    finance_foundation_assert(count($previewA['rows']) >= 35, 'Philippine chart template is not operationally complete.');
    $installed = yovel_admin_install_finance_chart_template($db,$company,$admin,$templateCode,1,'SKIP_EXISTING');
    $reinstalled = yovel_admin_install_finance_chart_template($db,$company,$admin,$templateCode,1,'SKIP_EXISTING');
    finance_foundation_assert((int)$installed['inserted_count'] > 0 && (int)$reinstalled['inserted_count']===0, 'Philippine chart installation is not idempotent.');
    finance_foundation_assert((int)$db->GetOne('SELECT COUNT(*) FROM project_company_accounting_account WHERE company_key_hash=? AND template_code=?',[$hash,$templateCode])===(int)$installed['inserted_count'], 'Template install read-back count failed.');
    finance_foundation_expect_error(fn () => yovel_admin_install_finance_chart_template($db,$company,$admin,$templateCode,1,'FAIL'), 'existing');

    $db->Execute('UPDATE project_company_accounting_account SET parent_account_key=? WHERE company_key_hash=? AND account_key=?',[$postedAccountKey,$hash,$counterAccountKey]);
    finance_foundation_expect_error(fn () => yovel_admin_finance_assert_account_parent_chain($db,$hash,$postedAccountKey,$counterAccountKey), 'cycle');
    $db->Execute('UPDATE project_company_accounting_account SET parent_account_key=NULL WHERE company_key_hash=? AND account_key=?',[$hash,$counterAccountKey]);

    $posted = yovel_admin_post_general_ledger_transaction($db,$company,$admin,[
        'posting_date'=>'2026-08-25','voucher_type'=>'JOURNAL_ENTRY','voucher_no'=>'AF-WP01-'.$suffix,'source_module'=>'AF_WP01_TEST','source_record_key'=>bx_uuid(),
        'entries'=>[['account_key'=>$postedAccountKey,'debit'=>'1','credit'=>'0'],['account_key'=>$counterAccountKey,'debit'=>'0','credit'=>'1']],
    ]);
    $transactionKey = (string)$posted['transaction_key'];
    $existingAccount = $db->GetRow('SELECT * FROM project_company_accounting_account WHERE company_key_hash=? AND account_key=?',[$hash,$postedAccountKey]);
    $changedAccount = $existingAccount;
    $changedAccount['root_type'] = 'LIABILITY';
    finance_foundation_expect_error(fn () => yovel_admin_finance_assert_account_update_allowed($db,$hash,$existingAccount,$changedAccount), 'posted');

    $rollbackKey = bx_uuid();
    finance_foundation_expect_error(function () use ($db,$company,$admin,$hash,$rollbackKey,$suffix): void {
        yovel_admin_finance_transaction($db, static function () use ($db,$company,$admin,$hash,$rollbackKey,$suffix): void {
            yovel_admin_db_execute($db,'INSERT INTO project_company_finance_account_category (account_category_key,company_key,company_key_hash,account_category_name,root_type,status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,\'ACTIVE\',?,?)',[$rollbackKey,$company['company_key'],$hash,'Rollback '.$suffix,'ASSET',$admin['admin_key'],$admin['admin_key']],'AF-WP01 rollback probe');
            throw new RuntimeException('rollback probe');
        });
    }, 'rollback probe');
    finance_foundation_assert((int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_account_category WHERE company_key_hash=? AND account_category_key=?',[$hash,$rollbackKey])===0, 'AF-WP01 transaction did not roll back the partial write.');

    finance_foundation_assert((int)$db->GetOne('SELECT COUNT(*) FROM builder_audit_log WHERE module=? AND record_key=?',['project_company_finance_account_category',$categoryKey])>=2, 'Account Category create/update audit evidence is missing.');
    $loaded = yovel_admin_finance_foundation_records($company);
    finance_foundation_assert(count($loaded['fiscalYears'])>=1 && count($loaded['financeBooks'])>=1, 'AF-WP01 server rehydration loader is incomplete.');

    $functionsSource = (string)file_get_contents($root.'/company/admin/modules/accounting-finance/functions.php');
    $gridSource = (string)file_get_contents($root.'/company/admin/modules/accounting-finance/grid.php');
    $workspaceSource = (string)file_get_contents($root.'/company/admin/modules/accounting-finance/views/workspace.php');
    $modalSource = (string)file_get_contents($root.'/company/admin/modules/accounting-finance/views/foundation-modals.php');
    finance_foundation_assert(str_contains($functionsSource,'yovel_admin_finance_assert_account_update_allowed'), 'Account save does not enforce posted-account immutability.');
    finance_foundation_assert(str_contains($functionsSource,"'foundation' => yovel_admin_finance_foundation_records") && str_contains($functionsSource,"'chartTemplates' => yovel_admin_finance_chart_templates"), 'AF-WP01 server payload is not integrated.');
    finance_foundation_assert(str_contains($gridSource,'yovel_admin_finance_assert_account_update_allowed'), 'CSV account import does not enforce posted-account immutability.');
    finance_foundation_assert(str_contains($workspaceSource,"require __DIR__ . '/foundation-modals.php'"), 'AF-WP01 modal workspace is not integrated.');
    foreach (['finance-foundation-modal','finance-chart-template-modal','data-confirm-submit','financeModalStateAttributes'] as $marker) {
        finance_foundation_assert(str_contains($modalSource,$marker), 'AF-WP01 modal contract is missing: '.$marker);
    }
    finance_foundation_assert(str_contains($modalSource,'failedValues.percentages') && str_contains($modalSource,'failedValues.allocations') && str_contains($modalSource,'failedValues.rates'), 'AF-WP01 nested server rehydration is incomplete.');
} finally {
    if ($transactionKey !== '') {
        $db->Execute('DELETE FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=?',[$hash,$transactionKey]);
        $db->Execute('DELETE FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND transaction_key=?',[$hash,$transactionKey]);
    }
    finance_test_restore_rows($db,'project_company_accounting_account','company_key_hash=? AND template_code=?',[$hash,$templateCode],$originalTemplateAccounts);
    finance_test_restore_rows($db,'project_company_finance_chart_template_installation','company_key_hash=? AND template_code=?',[$hash,$templateCode],$originalInstallations);
    $db->Execute('DELETE FROM project_company_finance_account_closing_balance WHERE company_key_hash=? AND account_closing_balance_key=?',[$hash,$closingKey]);
    $db->Execute('DELETE FROM project_company_finance_exchange_rate WHERE company_key_hash=? AND exchange_setting_key=?',[$hash,$exchangeKey]);
    $db->Execute('DELETE FROM project_company_finance_exchange_setting WHERE company_key_hash=? AND exchange_setting_key=?',[$hash,$exchangeKey]);
    $db->Execute('DELETE FROM project_company_finance_dimension_filter_value WHERE company_key_hash=? AND dimension_filter_key=?',[$hash,$filterKey]);
    $db->Execute('DELETE FROM project_company_finance_dimension_filter_account WHERE company_key_hash=? AND dimension_filter_key=?',[$hash,$filterKey]);
    $db->Execute('DELETE FROM project_company_finance_dimension_filter WHERE company_key_hash=? AND dimension_filter_key=?',[$hash,$filterKey]);
    $db->Execute('DELETE FROM project_company_finance_cost_center_allocation_line WHERE company_key_hash=? AND cost_center_allocation_key=?',[$hash,$allocationKey]);
    $db->Execute('DELETE FROM project_company_finance_cost_center_allocation WHERE company_key_hash=? AND cost_center_allocation_key=?',[$hash,$allocationKey]);
    $db->Execute('DELETE FROM project_company_finance_monthly_distribution_line WHERE company_key_hash=? AND monthly_distribution_key=?',[$hash,$distributionKey]);
    $db->Execute('DELETE FROM project_company_finance_monthly_distribution WHERE company_key_hash=? AND monthly_distribution_key=?',[$hash,$distributionKey]);
    $db->Execute('DELETE FROM project_company_finance_book WHERE company_key_hash=? AND finance_book_key=?',[$hash,$bookKey]);
    $db->Execute('DELETE FROM project_company_finance_accounting_period WHERE company_key_hash=? AND accounting_period_key=?',[$hash,$periodKey]);
    $db->Execute('DELETE FROM project_company_finance_fiscal_year WHERE company_key_hash=? AND fiscal_year_key=?',[$hash,$yearKey]);
    $db->Execute('DELETE FROM project_company_finance_account_category WHERE company_key_hash=? AND account_category_key=?',[$hash,$categoryKey]);
    $db->Execute('DELETE FROM project_company_finance_dimension_value WHERE company_key_hash=? AND dimension_key=?',[$hash,$dimensionKey]);
    $db->Execute('DELETE FROM project_company_finance_dimension WHERE company_key_hash=? AND dimension_key=?',[$hash,$dimensionKey]);
    $db->Execute('DELETE FROM project_company_finance_cost_center WHERE company_key_hash=? AND cost_center_key IN (?,?)',[$hash,$sourceCostCenterKey,$targetCostCenterKey]);
    $db->Execute('DELETE FROM project_company_accounting_account WHERE company_key_hash=? AND account_key IN (?,?)',[$hash,$postedAccountKey,$counterAccountKey]);
    $db->Execute('DELETE FROM builder_audit_log WHERE record_key IN (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$categoryKey,$periodKey,$yearKey,$bookKey,$distributionKey,$allocationKey,$filterKey,$exchangeKey,$closingKey,$sourceCostCenterKey,$targetCostCenterKey,$postedAccountKey,$counterAccountKey]);
}

echo "Accounting/Finance AF-WP01 foundation parity checks passed.\n";
