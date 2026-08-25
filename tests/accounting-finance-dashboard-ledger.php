<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/forms.php';
if (is_file($root . '/company/admin/modules/accounting-finance/ledger.php')) {
    require_once $root . '/company/admin/modules/accounting-finance/ledger.php';
}

function finance_ledger_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$originalGet = $_GET;
$_GET = [];
finance_ledger_assert(yovel_admin_accounting_finance_section() === 'dashboard', 'Accounting/Finance must open on Dashboard by default.');
$sections = yovel_admin_accounting_finance_sections();
finance_ledger_assert(isset($sections['dashboard']), 'Finance Dashboard route is missing.');
finance_ledger_assert(isset($sections['general-ledger']), 'General Ledger route is missing.');
$builderTargets = yovel_admin_finance_builder_target_sections();
finance_ledger_assert(count($builderTargets) === 15, 'Finance Form Builder must keep exactly 15 business feature targets.');
finance_ledger_assert(!isset($builderTargets['dashboard']) && !isset($builderTargets['general-ledger']), 'Workspace/report routes must not become Finance form targets.');
$_GET = $originalGet;

finance_ledger_assert(function_exists('yovel_admin_general_ledger_schema'), 'General Ledger schema service is missing.');
finance_ledger_assert(function_exists('yovel_admin_post_general_ledger_transaction'), 'General Ledger posting service is missing.');
finance_ledger_assert(function_exists('yovel_admin_reverse_general_ledger_transaction'), 'General Ledger reversal service is missing.');

yovel_admin_accounting_finance_schema();
yovel_admin_general_ledger_schema();
$db = bx_db();
foreach (['project_company_general_ledger_transaction', 'project_company_general_ledger_entry'] as $table) {
    $exists = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    finance_ledger_assert($exists === 1, 'Missing General Ledger table: ' . $table);
}

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
finance_ledger_assert(is_array($fixture) && $fixture !== [], 'An active company-admin fixture is required.');
$company = [
    'company_key' => (string) $fixture['company_key'],
    'company_key_hash' => (string) $fixture['company_key_hash'],
    'company_name' => (string) $fixture['company_name'],
];
$admin = ['admin_key' => (string) $fixture['admin_key']];
$suffix = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$debitAccountKey = bx_uuid();
$creditAccountKey = bx_uuid();
$transactionKey = bx_uuid();
$openingTransactionKey = bx_uuid();
$unbalancedKey = bx_uuid();
$postingDate = '2026-08-25';

$insertAccountSql = "INSERT INTO project_company_accounting_account (
    account_key, company_key, company_key_hash, account_code, account_name, root_type, report_type,
    account_currency, is_group, balance_must_be, account_status, created_by_admin_key, updated_by_admin_key
) VALUES (?, ?, ?, ?, ?, ?, ?, 'PHP', 0, 'EITHER', 'ACTIVE', ?, ?)";
$db->Execute($insertAccountSql, [$debitAccountKey, $company['company_key'], $company['company_key_hash'], 'GL_DEBIT_' . $suffix, 'GL Debit Test', 'ASSET', 'BALANCE_SHEET', $admin['admin_key'], $admin['admin_key']]);
$db->Execute($insertAccountSql, [$creditAccountKey, $company['company_key'], $company['company_key_hash'], 'GL_CREDIT_' . $suffix, 'GL Credit Test', 'LIABILITY', 'BALANCE_SHEET', $admin['admin_key'], $admin['admin_key']]);

try {
    yovel_admin_post_general_ledger_transaction($db, $company, $admin, [
        'transaction_key' => $openingTransactionKey,
        'posting_date' => '2026-08-24',
        'voucher_type' => 'JOURNAL_ENTRY',
        'voucher_no' => 'OPEN-JV-' . $suffix,
        'source_module' => 'TEST',
        'entries' => [
            ['account_key' => $debitAccountKey, 'debit' => '500', 'credit' => '0'],
            ['account_key' => $creditAccountKey, 'debit' => '0', 'credit' => '500'],
        ],
    ]);
    $posted = yovel_admin_post_general_ledger_transaction($db, $company, $admin, [
        'transaction_key' => $transactionKey,
        'posting_date' => $postingDate,
        'voucher_type' => 'JOURNAL_ENTRY',
        'voucher_no' => 'TEST-JV-' . $suffix,
        'source_module' => 'TEST',
        'source_record_key' => bx_uuid(),
        'entries' => [
            ['account_key' => $debitAccountKey, 'debit' => '1250.50', 'credit' => '0', 'party_type' => 'CUSTOMER', 'party' => 'Test Party', 'cost_center' => 'MAIN', 'remarks' => 'Debit fixture'],
            ['account_key' => $creditAccountKey, 'debit' => '0', 'credit' => '1250.50', 'party_type' => 'SUPPLIER', 'party' => 'Test Party', 'cost_center' => 'MAIN', 'remarks' => 'Credit fixture'],
        ],
    ]);
    finance_ledger_assert((string) ($posted['transaction_key'] ?? '') === $transactionKey, 'Balanced ledger posting changed the stable transaction key.');
    finance_ledger_assert((int) ($posted['entry_count'] ?? 0) === 2, 'Balanced ledger posting did not read back both entries.');
    finance_ledger_assert((string) ($posted['total_debit'] ?? '') === '1250.500000', 'Balanced ledger debit read-back is incorrect.');
    finance_ledger_assert((string) ($posted['total_credit'] ?? '') === '1250.500000', 'Balanced ledger credit read-back is incorrect.');

    $unbalancedRejected = false;
    try {
        yovel_admin_post_general_ledger_transaction($db, $company, $admin, [
            'transaction_key' => $unbalancedKey,
            'posting_date' => $postingDate,
            'voucher_type' => 'JOURNAL_ENTRY',
            'voucher_no' => 'BAD-JV-' . $suffix,
            'entries' => [
                ['account_key' => $debitAccountKey, 'debit' => '50', 'credit' => '0'],
                ['account_key' => $creditAccountKey, 'debit' => '0', 'credit' => '49'],
            ],
        ]);
    } catch (InvalidArgumentException) {
        $unbalancedRejected = true;
    }
    finance_ledger_assert($unbalancedRejected, 'Unbalanced General Ledger transaction was accepted.');
    finance_ledger_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE transaction_key = ?', [$unbalancedKey]) === 0, 'Unbalanced transaction wrote partial ledger rows.');

    $duplicateRejected = false;
    try {
        yovel_admin_post_general_ledger_transaction($db, $company, $admin, [
            'transaction_key' => $transactionKey,
            'posting_date' => $postingDate,
            'voucher_type' => 'JOURNAL_ENTRY',
            'voucher_no' => 'TEST-JV-' . $suffix,
            'entries' => [
                ['account_key' => $debitAccountKey, 'debit' => '1', 'credit' => '0'],
                ['account_key' => $creditAccountKey, 'debit' => '0', 'credit' => '1'],
            ],
        ]);
    } catch (InvalidArgumentException) {
        $duplicateRejected = true;
    }
    finance_ledger_assert($duplicateRejected, 'Duplicate General Ledger transaction key was accepted.');

    $entries = yovel_admin_general_ledger_entries($company, [
        'date_from' => $postingDate,
        'date_to' => $postingDate,
        'voucher_type' => 'JOURNAL_ENTRY',
        'party' => 'Test Party',
        'cost_center' => 'MAIN',
    ]);
    finance_ledger_assert(count($entries) === 2, 'General Ledger filters did not return the posted fixture rows.');
    $summary = yovel_admin_general_ledger_summary($company, ['date_from' => $postingDate, 'date_to' => $postingDate]);
    finance_ledger_assert((string) ($summary['period_debit'] ?? '') === '1250.500000', 'General Ledger period debit is incorrect.');
    finance_ledger_assert((string) ($summary['period_credit'] ?? '') === '1250.500000', 'General Ledger period credit is incorrect.');
    finance_ledger_assert((string) ($summary['closing_balance'] ?? '') === '0.000000', 'General Ledger closing balance is incorrect.');
    $accountSummary = yovel_admin_general_ledger_summary($company, ['date_from' => $postingDate, 'date_to' => $postingDate, 'account_key' => $debitAccountKey]);
    finance_ledger_assert((string) ($accountSummary['opening_balance'] ?? '') === '500.000000', 'General Ledger account opening balance is incorrect.');
    finance_ledger_assert((string) ($accountSummary['closing_balance'] ?? '') === '1750.500000', 'General Ledger account closing balance is incorrect.');
    finance_ledger_assert(yovel_admin_general_ledger_entries(['company_key_hash' => hash('sha256', 'other-company')]) === [], 'General Ledger leaked rows across company scope.');

    $reversal = yovel_admin_reverse_general_ledger_transaction($db, $company, $admin, $transactionKey, '2026-08-26', 'Fixture reversal');
    finance_ledger_assert((string) ($reversal['reversal_of_transaction_key'] ?? '') === $transactionKey, 'Ledger reversal is not linked to its original transaction.');
    finance_ledger_assert((int) ($reversal['entry_count'] ?? 0) === 2, 'Ledger reversal did not create both opposite rows.');
    $originalCount = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE transaction_key = ?', [$transactionKey]);
    finance_ledger_assert($originalCount === 2, 'Ledger reversal mutated or removed original entries.');
    $withReversal = yovel_admin_general_ledger_summary($company, ['date_from' => $postingDate, 'date_to' => '2026-08-26', 'include_reversals' => true]);
    finance_ledger_assert((string) ($withReversal['period_debit'] ?? '') === '2501.000000' && (string) ($withReversal['period_credit'] ?? '') === '2501.000000', 'Ledger reversal totals are incorrect.');
    $withoutReversal = yovel_admin_general_ledger_entries($company, ['include_reversals' => false]);
    finance_ledger_assert(count(array_filter($withoutReversal, static fn (array $entry): bool => (string) $entry['transaction_key'] === $transactionKey)) === 2, 'Original rows disappeared when reversals were excluded.');
} finally {
    $db->Execute('DELETE FROM project_company_general_ledger_entry WHERE company_key_hash = ? AND account_key IN (?, ?)', [$company['company_key_hash'], $debitAccountKey, $creditAccountKey]);
    $db->Execute('DELETE FROM project_company_general_ledger_transaction WHERE company_key_hash = ? AND (transaction_key = ? OR reversal_of_transaction_key = ?)', [$company['company_key_hash'], $transactionKey, $transactionKey]);
    $db->Execute('DELETE FROM project_company_general_ledger_transaction WHERE company_key_hash = ? AND transaction_key = ?', [$company['company_key_hash'], $openingTransactionKey]);
    $db->Execute('DELETE FROM project_company_general_ledger_transaction WHERE company_key_hash = ? AND transaction_key = ?', [$company['company_key_hash'], $unbalancedKey]);
    $db->Execute('DELETE FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key IN (?, ?)', [$company['company_key_hash'], $debitAccountKey, $creditAccountKey]);
}

echo "Accounting/Finance dashboard and General Ledger checks passed.\n";
