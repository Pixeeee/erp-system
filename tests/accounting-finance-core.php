<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/core.php';
require_once __DIR__ . '/accounting-finance-test-helper.php';

function finance_core_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function finance_core_expect_error(callable $callback, string $needle): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        finance_core_assert(str_contains(strtolower($error->getMessage()), strtolower($needle)), 'Unexpected validation message: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected validation error containing: ' . $needle);
}

yovel_admin_accounting_finance_schema();
yovel_admin_finance_core_schema();
$db = bx_db();

$fixture = $db->GetRow(
    "SELECT company_record.company_key, company_record.company_key_hash, company_record.company_name, admin_record.admin_key
     FROM project_company company_record
     INNER JOIN project_company_admin admin_record
        ON admin_record.company_key_hash = company_record.company_key_hash
       AND admin_record.admin_status = 'ACTIVE'
     WHERE company_record.company_status <> 'DELETED'
     ORDER BY company_record.x_id, admin_record.x_id
     LIMIT 1"
);
finance_core_assert(is_array($fixture) && $fixture !== [], 'An active company-admin fixture is required.');
$company = [
    'company_key' => (string) $fixture['company_key'],
    'company_key_hash' => (string) $fixture['company_key_hash'],
    'company_name' => (string) $fixture['company_name'],
];
$admin = ['admin_key' => (string) $fixture['admin_key']];
$suffix = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$bankLedgerKey = bx_uuid();
$expenseLedgerKey = bx_uuid();
$parentCostCenterKey = bx_uuid();
$childCostCenterKey = bx_uuid();
$dimensionKey = bx_uuid();
$dimensionValueKey = bx_uuid();
$bankAccountKey = bx_uuid();
$taxCodeKey = bx_uuid();
$periodLockKey = bx_uuid();
$originalSettings = finance_test_snapshot_rows($db, 'project_company_finance_setting', 'company_key_hash=?', [$company['company_key_hash']]);
$originalSeries = finance_test_snapshot_rows($db, 'project_company_finance_number_series', 'company_key_hash=? AND series_code=?', [$company['company_key_hash'], 'SALES_INVOICE']);

finance_core_assert(yovel_admin_finance_money('1234.5') === '1234.500000', 'Money normalization failed.');
finance_core_assert(yovel_admin_finance_money('-0.0000004') === '0.000000', 'Negative zero normalization failed.');
finance_core_assert(yovel_admin_finance_abs('-12345678901234.123456') === '12345678901234.123456', 'Exact absolute money normalization failed.');
finance_core_expect_error(static fn () => yovel_admin_finance_money('12.34x'), 'number');

$insertAccountSql = "INSERT INTO project_company_accounting_account (
    account_key, company_key, company_key_hash, account_code, account_name, root_type, report_type,
    account_type, account_currency, is_group, balance_must_be, account_status, created_by_admin_key, updated_by_admin_key
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PHP', 0, 'EITHER', 'ACTIVE', ?, ?)";
$db->Execute($insertAccountSql, [$bankLedgerKey, $company['company_key'], $company['company_key_hash'], 'BANK_' . $suffix, 'Bank Test ' . $suffix, 'ASSET', 'BALANCE_SHEET', 'Bank', $admin['admin_key'], $admin['admin_key']]);
$db->Execute($insertAccountSql, [$expenseLedgerKey, $company['company_key'], $company['company_key_hash'], 'EXP_' . $suffix, 'Expense Test ' . $suffix, 'EXPENSE', 'PROFIT_LOSS', 'Expense Account', $admin['admin_key'], $admin['admin_key']]);

try {
    $defaults = yovel_admin_finance_settings($company, $admin);
    finance_core_assert((string) ($defaults['base_currency'] ?? '') === 'PHP', 'Default currency must be PHP.');
    finance_core_assert((int) ($defaults['currency_precision'] ?? 0) === 2, 'Default currency precision must be 2.');

    $savedSettings = yovel_admin_persist_finance_settings($db, $company, $admin, [
        'base_currency' => 'PHP',
        'currency_precision' => 2,
        'fiscal_year_start_month' => 1,
        'bir_registered_name' => 'Yovel East Test ' . $suffix,
        'bir_tin' => '123-456-789-00000',
        'bir_branch_code' => '00000',
        'bir_rdo_code' => '043',
        'vat_registration_type' => 'VAT',
        'default_receivable_account_key' => '',
        'default_payable_account_key' => '',
        'default_income_account_key' => '',
        'default_expense_account_key' => $expenseLedgerKey,
        'default_output_vat_account_key' => '',
        'default_input_vat_account_key' => '',
        'retained_earnings_account_key' => '',
        'round_off_account_key' => '',
    ]);
    finance_core_assert((string) $savedSettings['bir_tin'] === '123-456-789-00000', 'BIR TIN was not persisted.');

    $parent = yovel_admin_persist_finance_master($db, $company, $admin, 'cost-center', [
        'cost_center_key' => $parentCostCenterKey,
        'cost_center_code' => 'MAIN_' . $suffix,
        'cost_center_name' => 'Main Cost Center ' . $suffix,
        'is_group' => 1,
        'status' => 'ACTIVE',
    ]);
    $child = yovel_admin_persist_finance_master($db, $company, $admin, 'cost-center', [
        'cost_center_key' => $childCostCenterKey,
        'cost_center_code' => 'OPS_' . $suffix,
        'cost_center_name' => 'Operations ' . $suffix,
        'parent_cost_center_key' => $parentCostCenterKey,
        'is_group' => 0,
        'status' => 'ACTIVE',
    ]);
    finance_core_assert((string) $child['parent_cost_center_key'] === $parentCostCenterKey, 'Cost Center hierarchy was not persisted.');
    finance_core_expect_error(fn () => yovel_admin_persist_finance_master($db, $company, $admin, 'cost-center', [
        'cost_center_code' => 'BAD_' . $suffix,
        'cost_center_name' => 'Bad Parent',
        'parent_cost_center_key' => $childCostCenterKey,
        'status' => 'ACTIVE',
    ]), 'group');

    $dimension = yovel_admin_persist_finance_master($db, $company, $admin, 'accounting-dimension', [
        'dimension_key' => $dimensionKey,
        'dimension_code' => 'BRANCH_' . $suffix,
        'dimension_name' => 'Branch ' . $suffix,
        'reference_type' => 'CUSTOM',
        'mandatory_for_profit_loss' => 1,
        'status' => 'ACTIVE',
        'values' => [[
            'dimension_value_key' => $dimensionValueKey,
            'value_code' => 'MNL',
            'value_name' => 'Manila',
            'status' => 'ACTIVE',
        ]],
    ]);
    finance_core_assert(count($dimension['values']) === 1, 'Accounting Dimension values were not persisted.');

    $bank = yovel_admin_persist_finance_master($db, $company, $admin, 'bank-account', [
        'bank_account_key' => $bankAccountKey,
        'bank_account_code' => 'BDO_' . $suffix,
        'bank_name' => 'BDO',
        'account_name' => 'Operating Account',
        'masked_account_number' => '****1234',
        'ledger_account_key' => $bankLedgerKey,
        'currency' => 'PHP',
        'status' => 'ACTIVE',
    ]);
    finance_core_assert((string) $bank['ledger_account_key'] === $bankLedgerKey, 'Bank Account ledger link was not persisted.');
    finance_core_expect_error(fn () => yovel_admin_persist_finance_master($db, $company, $admin, 'bank-account', [
        'bank_account_code' => 'BADBANK_' . $suffix,
        'bank_name' => 'Invalid Bank',
        'account_name' => 'Invalid Account',
        'ledger_account_key' => $expenseLedgerKey,
        'currency' => 'PHP',
        'status' => 'ACTIVE',
    ]), 'bank or cash');

    $tax = yovel_admin_persist_finance_master($db, $company, $admin, 'tax-code', [
        'tax_code_key' => $taxCodeKey,
        'tax_code' => 'VAT12_' . $suffix,
        'tax_name' => 'VAT 12%',
        'tax_kind' => 'VAT',
        'bir_classification' => 'VATABLE',
        'rate' => '12',
        'price_inclusive' => 0,
        'creditable' => 1,
        'effective_from' => '2026-01-01',
        'effective_to' => '2026-12-31',
        'status' => 'ACTIVE',
    ]);
    finance_core_assert((string) $tax['rate'] === '12.000000', 'Tax Code rate was not normalized.');
    finance_core_expect_error(fn () => yovel_admin_persist_finance_master($db, $company, $admin, 'tax-code', [
        'tax_code' => 'BADTAX_' . $suffix,
        'tax_name' => 'Bad Tax',
        'tax_kind' => 'VAT',
        'bir_classification' => 'VATABLE',
        'rate' => '12',
        'effective_from' => '2026-12-31',
        'effective_to' => '2026-01-01',
    ]), 'effective');

    $firstNumber = yovel_admin_finance_next_number($db, $company, $admin, 'SALES_INVOICE', 'SI-', 2026);
    $secondNumber = yovel_admin_finance_next_number($db, $company, $admin, 'SALES_INVOICE', 'SI-', 2026);
    finance_core_assert($firstNumber === 'SI-2026-00001' && $secondNumber === 'SI-2026-00002', 'Document number sequence is not deterministic.');

    yovel_admin_persist_finance_period_lock($db, $company, $admin, [
        'period_lock_key' => $periodLockKey,
        'lock_type' => 'HARD_CLOSE',
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
        'reason' => 'Core test close',
        'status' => 'ACTIVE',
    ]);
    finance_core_expect_error(fn () => yovel_admin_finance_assert_open_period($company, '2026-03-31'), 'closed');
    yovel_admin_finance_assert_open_period($company, '2026-04-01');

    finance_core_assert(count(yovel_admin_finance_masters($company, 'cost-center')) >= 2, 'Cost Center loader did not return persisted records.');
    finance_core_assert(count(yovel_admin_finance_masters($company, 'bank-account')) >= 1, 'Bank Account loader did not return persisted records.');
} finally {
    $hash = $company['company_key_hash'];
    $db->Execute('DELETE FROM project_company_finance_period_lock WHERE company_key_hash = ? AND period_lock_key = ?', [$hash, $periodLockKey]);
    finance_test_restore_rows($db, 'project_company_finance_number_series', 'company_key_hash=? AND series_code=?', [$hash, 'SALES_INVOICE'], $originalSeries);
    $db->Execute('DELETE FROM project_company_finance_tax_code WHERE company_key_hash = ? AND tax_code_key = ?', [$hash, $taxCodeKey]);
    $db->Execute('DELETE FROM project_company_finance_bank_account WHERE company_key_hash = ? AND bank_account_key = ?', [$hash, $bankAccountKey]);
    $db->Execute('DELETE FROM project_company_finance_dimension_value WHERE company_key_hash = ? AND dimension_key = ?', [$hash, $dimensionKey]);
    $db->Execute('DELETE FROM project_company_finance_dimension WHERE company_key_hash = ? AND dimension_key = ?', [$hash, $dimensionKey]);
    $db->Execute('DELETE FROM project_company_finance_cost_center WHERE company_key_hash = ? AND cost_center_key IN (?, ?)', [$hash, $childCostCenterKey, $parentCostCenterKey]);
    finance_test_restore_rows($db, 'project_company_finance_setting', 'company_key_hash=?', [$hash], $originalSettings);
    $db->Execute('DELETE FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key IN (?, ?)', [$hash, $bankLedgerKey, $expenseLedgerKey]);
}

echo "Accounting/Finance core checks passed.\n";
