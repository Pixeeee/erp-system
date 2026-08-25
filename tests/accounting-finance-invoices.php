<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/sales-crm/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/core.php';
require_once $root . '/company/admin/modules/accounting-finance/tax.php';
require_once $root . '/company/admin/modules/accounting-finance/ledger.php';
require_once $root . '/company/admin/modules/accounting-finance/invoices.php';
require_once $root . '/company/admin/modules/compliance-localization/core.php';
require_once $root . '/company/admin/modules/compliance-localization/rules.php';
require_once $root . '/company/admin/modules/compliance-localization/templates.php';
require_once __DIR__ . '/accounting-finance-test-helper.php';

function invoice_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function invoice_expect_error(callable $callback, string $needle): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        invoice_assert(str_contains(strtolower($error->getMessage()), strtolower($needle)), 'Unexpected invoice error: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected invoice error containing: ' . $needle);
}

yovel_admin_sales_crm_schema();
yovel_admin_accounting_finance_schema();
yovel_admin_finance_core_schema();
yovel_admin_general_ledger_schema();
yovel_admin_finance_invoice_schema();
$db = bx_db();
$fixture = $db->GetRow("SELECT c.company_key, c.company_key_hash, c.company_name, a.admin_key FROM project_company c INNER JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE' WHERE c.company_status <> 'DELETED' ORDER BY c.x_id, a.x_id LIMIT 1");
invoice_assert(is_array($fixture) && $fixture !== [], 'An active company-admin fixture is required.');
$company = ['company_key' => (string) $fixture['company_key'], 'company_key_hash' => (string) $fixture['company_key_hash'], 'company_name' => (string) $fixture['company_name']];
$admin = ['admin_key' => (string) $fixture['admin_key']];
$suffix = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$customerKey = bx_uuid();
$supplierKey = bx_uuid();
$salesKey = bx_uuid();
$purchaseKey = bx_uuid();
$duplicateKey = bx_uuid();
$returnKey = '';
$amendmentKey = '';
$openingKey = bx_uuid();
$rollbackKey = bx_uuid();
$templateKey = '';
$dunningTypeKey = '';
$dunningKey = '';
$statementKey = '';
$discountingKey = '';
$openingBatchKey = '';
$accountKeys = ['ar' => bx_uuid(), 'ap' => bx_uuid(), 'income' => bx_uuid(), 'expense' => bx_uuid(), 'output' => bx_uuid(), 'input' => bx_uuid()];
$originalSettings = finance_test_snapshot_rows($db, 'project_company_finance_setting', 'company_key_hash=?', [$company['company_key_hash']]);
$accountSql = "INSERT INTO project_company_accounting_account (account_key, company_key, company_key_hash, account_code, account_name, root_type, report_type, account_type, account_currency, is_group, balance_must_be, account_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PHP', 0, 'EITHER', 'ACTIVE', ?, ?)";
$accountFixtures = [
    ['ar', 'AR_', 'Receivable', 'ASSET', 'BALANCE_SHEET'],
    ['ap', 'AP_', 'Payable', 'LIABILITY', 'BALANCE_SHEET'],
    ['income', 'INC_', 'Income Account', 'INCOME', 'PROFIT_LOSS'],
    ['expense', 'EXP_', 'Expense Account', 'EXPENSE', 'PROFIT_LOSS'],
    ['output', 'OVAT_', 'Tax', 'LIABILITY', 'BALANCE_SHEET'],
    ['input', 'IVAT_', 'Tax', 'ASSET', 'BALANCE_SHEET'],
];
foreach ($accountFixtures as [$name, $prefix, $type, $rootType, $reportType]) {
    $db->Execute($accountSql, [$accountKeys[$name], $company['company_key'], $company['company_key_hash'], $prefix . $suffix, ucfirst($name) . ' ' . $suffix, $rootType, $reportType, $type, $admin['admin_key'], $admin['admin_key']]);
}
$db->Execute("INSERT INTO project_company_sales_customer (customer_key, company_key, company_key_hash, customer_code, customer_name, customer_status) VALUES (?, ?, ?, ?, ?, 'ACTIVE')", [$customerKey, $company['company_key'], $company['company_key_hash'], 'CUS_' . $suffix, 'Customer ' . $suffix]);
$savedSupplier = yovel_admin_persist_finance_supplier($db, $company, $admin, ['supplier_key' => $supplierKey, 'supplier_code' => 'SUP_' . $suffix, 'supplier_name' => 'Supplier ' . $suffix, 'supplier_tin' => '123-456-789-00000']);
invoice_assert((string) $savedSupplier['supplier_key'] === $supplierKey, 'Supplier modal persistence failed.');
$GLOBALS['yovel_admin_finance_invoice_dependency_providers'] = [
    'sales-crm.customer-reference.v1' => static fn (array $scope, string $key): array => ['company_key_hash'=>$scope['company_key_hash'],'customer_key'=>$key,'customer_code'=>'CUS_'.$suffix,'customer_name'=>'Customer '.$suffix,'customer_status'=>'ACTIVE'],
    'buying-procurement.supplier-reference.v1' => static fn (array $scope, string $key): array => ['company_key_hash'=>$scope['company_key_hash'],'supplier_key'=>$key,'supplier_code'=>'SUP_'.$suffix,'supplier_name'=>'Supplier '.$suffix,'supplier_status'=>'ACTIVE','supplier_tin'=>'123-456-789-00000'],
];

try {
    yovel_admin_persist_finance_settings($db, $company, $admin, [
        'base_currency' => 'PHP', 'currency_precision' => 2, 'fiscal_year_start_month' => 1,
        'vat_registration_type' => 'VAT', 'default_receivable_account_key' => $accountKeys['ar'],
        'default_payable_account_key' => $accountKeys['ap'], 'default_income_account_key' => $accountKeys['income'],
        'default_expense_account_key' => $accountKeys['expense'], 'default_output_vat_account_key' => $accountKeys['output'],
        'default_input_vat_account_key' => $accountKeys['input'],
    ]);
    $salesPayload = [
        'invoice_key' => $salesKey, 'invoice_no' => 'SI-' . $suffix, 'posting_date' => '2026-06-15', 'due_date' => '2026-07-15',
        'party_key' => $customerKey, 'party_tin' => '111-222-333-00000', 'currency' => 'PHP',
        'payment_terms' => [['due_date'=>'2026-07-15','invoice_portion'=>'50','description'=>'First half'],['due_date'=>'2026-08-15','invoice_portion'=>'50','description'=>'Final half']],
        'advances' => [['payment_entry_key'=>bx_uuid(),'allocated_amount'=>'100']],
        'lines' => [['description' => 'Consulting', 'quantity' => '1', 'unit_amount' => '1000', 'account_key' => $accountKeys['income'], 'tax_code' => 'VAT12']],
    ];
    $sales = yovel_admin_persist_finance_invoice($db, $company, $admin, 'SALES', $salesPayload);
    invoice_assert($sales['grand_total'] === '1120.000000', 'Sales total failed.');
    invoice_assert($sales['document_status'] === 'DRAFT' && count($sales['lines']) === 1 && count($sales['advances']) === 1, 'Sales Draft or advance did not persist completely.');
    unset($salesPayload['advances']);
    $sales = yovel_admin_persist_finance_invoice($db, $company, $admin, 'SALES', $salesPayload);
    $postedSales = yovel_admin_submit_finance_invoice($db, $company, $admin, 'SALES', $salesKey);
    invoice_assert($postedSales['outstanding_amount'] === '1120.000000', 'Receivable opening failed.');
    invoice_assert($postedSales['base_grand_total'] === '1120.000000' && count($postedSales['payment_terms']) === 2, 'Base totals or Payment Schedule were not persisted.');
    $snapshot = yovel_admin_finance_invoice_snapshot($company, $salesKey);
    invoice_assert(($snapshot['ok'] ?? false) === true && ($snapshot['record']['document_status'] ?? '') === 'SUBMITTED', 'Submitted Sales Invoice snapshot was not published.');
    invoice_assert(hash('sha256', yovel_admin_finance_canonical_json($snapshot['record']['snapshot'])) === $snapshot['record']['finance_document_sha256'], 'Submitted Sales Invoice snapshot checksum is not canonical.');
    $complianceSnapshot = yovel_admin_compliance_finance_invoice_snapshot($company, $salesKey, 'yovel_admin_finance_invoice_snapshot');
    invoice_assert($complianceSnapshot['finance_document_key'] === $salesKey && $complianceSnapshot['document_status'] === 'SUBMITTED', 'Compliance could not consume the Finance invoice snapshot read-only.');
    invoice_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash=? AND source_record_key=?', [$company['company_key_hash'], $salesKey]) === 3, 'Sales Invoice GL posting is incomplete.');
    invoice_expect_error(fn () => yovel_admin_persist_finance_invoice($db, $company, $admin, 'SALES', $salesPayload), 'immutable');

    $purchasePayload = [
        'invoice_key' => $purchaseKey, 'invoice_no' => 'PI-' . $suffix, 'posting_date' => '2026-06-20', 'due_date' => '2026-07-20',
        'party_key' => $supplierKey, 'supplier_reference' => 'SUPINV-' . $suffix, 'currency' => 'PHP',
        'advances' => [['journal_entry_key'=>bx_uuid(),'allocated_amount'=>'100']],
        'lines' => [['description' => 'Professional service', 'quantity' => '1', 'unit_amount' => '1000', 'account_key' => $accountKeys['expense'], 'tax_code' => 'INPUT_SERVICE_VAT12']],
    ];
    $purchase = yovel_admin_persist_finance_invoice($db, $company, $admin, 'PURCHASE', $purchasePayload);
    invoice_assert($purchase['grand_total'] === '1120.000000' && count($purchase['advances']) === 1, 'Purchase total or advance failed.');
    unset($purchasePayload['advances']);
    $purchase = yovel_admin_persist_finance_invoice($db, $company, $admin, 'PURCHASE', $purchasePayload);
    $postedPurchase = yovel_admin_submit_finance_invoice($db, $company, $admin, 'PURCHASE', $purchaseKey);
    invoice_assert($postedPurchase['outstanding_amount'] === '1120.000000', 'Payable opening failed.');
    invoice_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash=? AND source_record_key=?', [$company['company_key_hash'], $purchaseKey]) === 3, 'Purchase Invoice GL posting is incomplete.');
    $purchasePayload['invoice_key'] = $duplicateKey;
    $purchasePayload['invoice_no'] = 'PI-DUP-' . $suffix;
    invoice_expect_error(fn () => yovel_admin_persist_finance_invoice($db, $company, $admin, 'PURCHASE', $purchasePayload), 'supplier reference');

    $return = yovel_admin_create_finance_return($db, $company, $admin, 'SALES', $salesKey, ['invoice_no' => 'CN-' . $suffix, 'posting_date' => '2026-06-25', 'due_date' => '2026-06-25']);
    $returnKey = (string) $return['invoice_key'];
    invoice_assert((int) $return['is_return'] === 1 && $return['grand_total'] === '-1120.000000', 'Sales Credit Note creation failed.');
    $postedReturn = yovel_admin_submit_finance_invoice($db, $company, $admin, 'SALES', $returnKey);
    invoice_assert($postedReturn['outstanding_amount'] === '-1120.000000', 'Sales Credit Note outstanding direction failed.');
    invoice_expect_error(fn () => yovel_admin_create_finance_return($db, $company, $admin, 'SALES', $salesKey, ['invoice_no' => 'CN-OVER-' . $suffix]), 'return limit');

    $template = yovel_admin_finance_save_payment_terms_template($db, $company, $admin, ['template_code'=>'NET2-'.$suffix,'template_name'=>'Two installments','terms'=>[['credit_days'=>30,'invoice_portion'=>'50'],['credit_days'=>60,'invoice_portion'=>'50']]]);
    $templateKey = (string) $template['payment_terms_template_key'];
    invoice_assert(count($template['terms']) === 2, 'Payment Terms Template details were not persisted.');
    $dunningType = yovel_admin_finance_save_dunning_type($db, $company, $admin, ['dunning_type_code'=>'DUN-'.$suffix,'dunning_type_name'=>'Thirty day notice','days_overdue'=>30,'interest_rate'=>'1','fee_amount'=>'50','letter_text'=>'Please settle the overdue balance.']);
    $dunningTypeKey = (string) $dunningType['dunning_type_key'];
    $dunning = yovel_admin_finance_create_dunning($db, $company, $admin, $salesKey, $dunningTypeKey, '2026-08-25');
    $dunningKey = (string) $dunning['dunning_key'];
    invoice_assert($dunning['dunning_status'] === 'ISSUED' && $dunning['letter_snapshot'] === 'Please settle the overdue balance.', 'Dunning and letter snapshot failed.');
    $statement = yovel_admin_finance_process_statement_of_accounts($db, $company, $admin, ['statement_date'=>'2026-08-25','date_from'=>'2026-01-01','date_to'=>'2026-08-25','customer_keys'=>[$customerKey],'cc'=>['finance@example.test']]);
    $statementKey = (string) $statement['statement_process_key'];
    invoice_assert(count($statement['customers']) === 1 && count($statement['cc']) === 1, 'Statement of Accounts processing failed.');
    $discounting = yovel_admin_finance_discount_invoice($db, $company, $admin, ['invoice_key'=>$salesKey,'discount_date'=>'2026-07-01','lender_reference'=>'BANK-'.$suffix,'discounted_amount'=>'500','discount_charge'=>'10']);
    $discountingKey = (string) $discounting['invoice_discounting_key'];
    invoice_assert($discounting['net_proceeds'] === '490.000000', 'Invoice Discounting calculation failed.');

    invoice_expect_error(function () use ($db,$company,$admin,$salesPayload,$rollbackKey): void {$payload=$salesPayload;$payload['invoice_key']=$rollbackKey;$payload['invoice_no']='ROLLBACK-'.substr($rollbackKey,0,8);$payload['_checkpoint']=static function(string $checkpoint):void{if($checkpoint==='after_invoice_details')throw new RuntimeException('Injected invoice rollback.');};yovel_admin_persist_finance_invoice($db,$company,$admin,'SALES',$payload);}, 'injected invoice rollback');
    invoice_assert((int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=?',[$company['company_key_hash'],$rollbackKey])===0, 'Invoice rollback left a header.');

    $receivables = yovel_admin_receivable_aging($company, '2026-08-25');
    $payables = yovel_admin_payable_aging($company, '2026-08-25');
    invoice_assert(count($receivables['rows']) >= 2 && $receivables['total_outstanding'] === '0.000000', 'Receivable aging failed to include invoice and credit.');
    invoice_assert(count($payables['rows']) >= 1 && bccomp($payables['total_outstanding'], '1120.000000', 6) >= 0, 'Payable aging failed.');

    $cancelled = yovel_admin_cancel_finance_invoice($db, $company, $admin, 'PURCHASE', $purchaseKey, '2026-06-26', 'Invoice fixture cancellation');
    invoice_assert($cancelled['document_status'] === 'CANCELLED' && $cancelled['outstanding_amount'] === '0.000000', 'Purchase Invoice cancellation failed.');
    $amendment = yovel_admin_amend_finance_invoice($db,$company,$admin,'PURCHASE',$purchaseKey,['invoice_no'=>'PI-AMEND-'.$suffix,'posting_date'=>'2026-06-27','due_date'=>'2026-07-27','supplier_reference'=>'SUPINV-AMEND-'.$suffix]);
    $amendmentKey = (string)$amendment['invoice_key'];
    invoice_assert($amendment['document_status']==='DRAFT' && $amendment['amended_from_invoice_key']===$purchaseKey && (int)$amendment['amendment_index']===1, 'Purchase Invoice amendment lineage failed.');

    $opening = yovel_admin_finance_create_opening_invoices($db,$company,$admin,['batch_reference'=>'OPEN-'.$suffix,'invoices'=>[array_replace($salesPayload,['invoice_key'=>$openingKey,'invoice_no'=>'OPEN-'.$suffix,'document_type'=>'SALES','is_opening'=>1])]]);
    $openingBatchKey = (string)$opening['batch']['opening_invoice_batch_key'];
    invoice_assert((int)$opening['batch']['row_count']===1 && (int)$opening['invoices'][0]['is_opening']===1, 'Opening Invoice Creation Tool failed.');
} finally {
    $hash = $company['company_key_hash'];
    $keys = array_values(array_filter([$salesKey, $purchaseKey, $duplicateKey, $returnKey, $amendmentKey, $openingKey, $rollbackKey]));
    if ($keys !== []) {
        $marks = implode(',', array_fill(0, count($keys), '?'));
        $params = array_merge([$hash], $keys);
        $db->Execute("DELETE FROM project_company_general_ledger_entry WHERE company_key_hash=? AND source_record_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND source_record_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_finance_invoice_reference WHERE company_key_hash=? AND invoice_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_finance_invoice_advance WHERE company_key_hash=? AND invoice_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_finance_invoice_tax WHERE company_key_hash=? AND invoice_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_finance_invoice_line WHERE company_key_hash=? AND invoice_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_finance_payment_term WHERE company_key_hash=? AND invoice_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key IN ({$marks})", $params);
    }
    if ($openingBatchKey !== '') {$db->Execute('DELETE FROM project_company_finance_opening_invoice_item WHERE company_key_hash=? AND opening_invoice_batch_key=?',[$hash,$openingBatchKey]);$db->Execute('DELETE FROM project_company_finance_opening_invoice_batch WHERE company_key_hash=? AND opening_invoice_batch_key=?',[$hash,$openingBatchKey]);}
    if ($discountingKey !== '') $db->Execute('DELETE FROM project_company_finance_invoice_discounting WHERE company_key_hash=? AND invoice_discounting_key=?',[$hash,$discountingKey]);
    if ($statementKey !== '') {$db->Execute('DELETE FROM project_company_finance_statement_cc WHERE company_key_hash=? AND statement_process_key=?',[$hash,$statementKey]);$db->Execute('DELETE FROM project_company_finance_statement_customer WHERE company_key_hash=? AND statement_process_key=?',[$hash,$statementKey]);$db->Execute('DELETE FROM project_company_finance_statement_process WHERE company_key_hash=? AND statement_process_key=?',[$hash,$statementKey]);}
    if ($dunningKey !== '') $db->Execute('DELETE FROM project_company_finance_dunning WHERE company_key_hash=? AND dunning_key=?',[$hash,$dunningKey]);
    if ($dunningTypeKey !== '') $db->Execute('DELETE FROM project_company_finance_dunning_type WHERE company_key_hash=? AND dunning_type_key=?',[$hash,$dunningTypeKey]);
    if ($templateKey !== '') {$db->Execute('DELETE FROM project_company_finance_payment_terms_template_detail WHERE company_key_hash=? AND payment_terms_template_key=?',[$hash,$templateKey]);$db->Execute('DELETE FROM project_company_finance_payment_terms_template WHERE company_key_hash=? AND payment_terms_template_key=?',[$hash,$templateKey]);}
    $db->Execute('DELETE FROM project_company_finance_supplier WHERE company_key_hash=? AND supplier_key=?', [$hash, $supplierKey]);
    $db->Execute('DELETE FROM project_company_sales_customer WHERE company_key_hash=? AND customer_key=?', [$hash, $customerKey]);
    finance_test_restore_rows($db, 'project_company_finance_setting', 'company_key_hash=?', [$hash], $originalSettings);
    $db->Execute('DELETE FROM project_company_accounting_account WHERE company_key_hash=? AND account_key IN (?,?,?,?,?,?)', array_merge([$hash], array_values($accountKeys)));
    unset($GLOBALS['yovel_admin_finance_invoice_dependency_providers']);
}

echo "Accounting/Finance invoice checks passed.\n";
