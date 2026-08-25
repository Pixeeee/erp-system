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
        'lines' => [['description' => 'Consulting', 'quantity' => '1', 'unit_amount' => '1000', 'account_key' => $accountKeys['income'], 'tax_code' => 'VAT12']],
    ];
    $sales = yovel_admin_persist_finance_invoice($db, $company, $admin, 'SALES', $salesPayload);
    invoice_assert($sales['grand_total'] === '1120.000000', 'Sales total failed.');
    invoice_assert($sales['document_status'] === 'DRAFT' && count($sales['lines']) === 1, 'Sales Draft did not persist completely.');
    $postedSales = yovel_admin_submit_finance_invoice($db, $company, $admin, 'SALES', $salesKey);
    invoice_assert($postedSales['outstanding_amount'] === '1120.000000', 'Receivable opening failed.');
    invoice_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash=? AND source_record_key=?', [$company['company_key_hash'], $salesKey]) === 3, 'Sales Invoice GL posting is incomplete.');
    invoice_expect_error(fn () => yovel_admin_persist_finance_invoice($db, $company, $admin, 'SALES', $salesPayload), 'immutable');

    $purchasePayload = [
        'invoice_key' => $purchaseKey, 'invoice_no' => 'PI-' . $suffix, 'posting_date' => '2026-06-20', 'due_date' => '2026-07-20',
        'party_key' => $supplierKey, 'supplier_reference' => 'SUPINV-' . $suffix, 'currency' => 'PHP',
        'lines' => [['description' => 'Professional service', 'quantity' => '1', 'unit_amount' => '1000', 'account_key' => $accountKeys['expense'], 'tax_code' => 'INPUT_SERVICE_VAT12']],
    ];
    $purchase = yovel_admin_persist_finance_invoice($db, $company, $admin, 'PURCHASE', $purchasePayload);
    invoice_assert($purchase['grand_total'] === '1120.000000', 'Purchase total failed.');
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

    $receivables = yovel_admin_receivable_aging($company, '2026-08-25');
    $payables = yovel_admin_payable_aging($company, '2026-08-25');
    invoice_assert(count($receivables['rows']) >= 2 && $receivables['total_outstanding'] === '0.000000', 'Receivable aging failed to include invoice and credit.');
    invoice_assert(count($payables['rows']) >= 1 && bccomp($payables['total_outstanding'], '1120.000000', 6) >= 0, 'Payable aging failed.');

    $cancelled = yovel_admin_cancel_finance_invoice($db, $company, $admin, 'PURCHASE', $purchaseKey, '2026-06-26', 'Invoice fixture cancellation');
    invoice_assert($cancelled['document_status'] === 'CANCELLED' && $cancelled['outstanding_amount'] === '0.000000', 'Purchase Invoice cancellation failed.');
} finally {
    $hash = $company['company_key_hash'];
    $keys = array_values(array_filter([$salesKey, $purchaseKey, $duplicateKey, $returnKey]));
    if ($keys !== []) {
        $marks = implode(',', array_fill(0, count($keys), '?'));
        $params = array_merge([$hash], $keys);
        $db->Execute("DELETE FROM project_company_general_ledger_entry WHERE company_key_hash=? AND source_record_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND source_record_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_finance_invoice_tax WHERE company_key_hash=? AND invoice_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_finance_invoice_line WHERE company_key_hash=? AND invoice_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_finance_payment_term WHERE company_key_hash=? AND invoice_key IN ({$marks})", $params);
        $db->Execute("DELETE FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key IN ({$marks})", $params);
    }
    $db->Execute('DELETE FROM project_company_finance_supplier WHERE company_key_hash=? AND supplier_key=?', [$hash, $supplierKey]);
    $db->Execute('DELETE FROM project_company_sales_customer WHERE company_key_hash=? AND customer_key=?', [$hash, $customerKey]);
    finance_test_restore_rows($db, 'project_company_finance_setting', 'company_key_hash=?', [$hash], $originalSettings);
    $db->Execute('DELETE FROM project_company_accounting_account WHERE company_key_hash=? AND account_key IN (?,?,?,?,?,?)', array_merge([$hash], array_values($accountKeys)));
}

echo "Accounting/Finance invoice checks passed.\n";
