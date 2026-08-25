<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/core.php';
require_once $root . '/company/admin/modules/accounting-finance/ledger.php';
require_once $root . '/company/admin/modules/accounting-finance/journals.php';

function journal_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function journal_expect_error(callable $callback, string $needle): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        journal_assert(str_contains(strtolower($error->getMessage()), strtolower($needle)), 'Unexpected journal error: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected journal error containing: ' . $needle);
}

yovel_admin_accounting_finance_schema();
yovel_admin_finance_core_schema();
yovel_admin_general_ledger_schema();
yovel_admin_finance_journal_schema();
$db = bx_db();
$fixture = $db->GetRow("SELECT c.company_key, c.company_key_hash, c.company_name, a.admin_key FROM project_company c INNER JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE' WHERE c.company_status <> 'DELETED' ORDER BY c.x_id, a.x_id LIMIT 1");
journal_assert(is_array($fixture) && $fixture !== [], 'An active company-admin fixture is required.');
$company = ['company_key' => (string) $fixture['company_key'], 'company_key_hash' => (string) $fixture['company_key_hash'], 'company_name' => (string) $fixture['company_name']];
$admin = ['admin_key' => (string) $fixture['admin_key']];
$suffix = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$debitAccountKey = bx_uuid();
$creditAccountKey = bx_uuid();
$frozenAccountKey = bx_uuid();
$journalKey = bx_uuid();
$unbalancedKey = bx_uuid();
$frozenJournalKey = bx_uuid();
$lockKey = bx_uuid();
$postingDate = '2026-08-25';
$accountSql = "INSERT INTO project_company_accounting_account (account_key, company_key, company_key_hash, account_code, account_name, root_type, report_type, account_type, account_currency, is_group, balance_must_be, freeze_account, account_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PHP', 0, 'EITHER', ?, 'ACTIVE', ?, ?)";
$db->Execute($accountSql, [$debitAccountKey, $company['company_key'], $company['company_key_hash'], 'JVD_' . $suffix, 'Journal Debit ' . $suffix, 'EXPENSE', 'PROFIT_LOSS', 'Expense Account', 0, $admin['admin_key'], $admin['admin_key']]);
$db->Execute($accountSql, [$creditAccountKey, $company['company_key'], $company['company_key_hash'], 'JVC_' . $suffix, 'Journal Credit ' . $suffix, 'ASSET', 'BALANCE_SHEET', 'Cash', 0, $admin['admin_key'], $admin['admin_key']]);
$db->Execute($accountSql, [$frozenAccountKey, $company['company_key'], $company['company_key_hash'], 'JVF_' . $suffix, 'Journal Frozen ' . $suffix, 'ASSET', 'BALANCE_SHEET', 'Cash', 1, $admin['admin_key'], $admin['admin_key']]);

try {
    $payload = [
        'journal_entry_key' => $journalKey,
        'journal_no' => 'JV-' . $suffix,
        'posting_date' => $postingDate,
        'entry_type' => 'GENERAL',
        'remarks' => 'Journal lifecycle fixture',
        'rows' => [
            ['account_key' => $debitAccountKey, 'debit' => '500', 'credit' => '0', 'cost_center' => 'MAIN'],
            ['account_key' => $creditAccountKey, 'debit' => '0', 'credit' => '500'],
        ],
    ];
    $draft = yovel_admin_persist_journal_entry($db, $company, $admin, $payload);
    journal_assert($draft['document_status'] === 'DRAFT', 'Journal draft status failed.');
    journal_assert(count($draft['rows']) === 2, 'Journal draft rows were not persisted.');
    journal_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash = ? AND source_record_key = ?', [$company['company_key_hash'], $journalKey]) === 0, 'Draft Journal created General Ledger rows.');

    $submitted = yovel_admin_submit_journal_entry($db, $company, $admin, $journalKey);
    journal_assert($submitted['document_status'] === 'SUBMITTED', 'Journal submission failed.');
    journal_assert(yovel_admin_is_uuid((string) $submitted['gl_transaction_key']), 'Journal submission did not retain its GL transaction key.');
    journal_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash = ? AND source_record_key = ?', [$company['company_key_hash'], $journalKey]) === 2, 'Journal submission did not create two GL rows.');
    journal_expect_error(fn () => yovel_admin_persist_journal_entry($db, $company, $admin, $payload), 'immutable');

    $cancelled = yovel_admin_cancel_journal_entry($db, $company, $admin, $journalKey, '2026-08-26', 'Journal fixture cancellation');
    journal_assert($cancelled['document_status'] === 'CANCELLED', 'Journal cancellation failed.');
    journal_assert(yovel_admin_is_uuid((string) $cancelled['reversal_transaction_key']), 'Journal cancellation did not retain its reversal key.');
    journal_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash = ? AND source_record_key = ?', [$company['company_key_hash'], $journalKey]) === 4, 'Journal cancellation did not preserve original rows and add reversal rows.');

    $unbalanced = yovel_admin_persist_journal_entry($db, $company, $admin, [
        'journal_entry_key' => $unbalancedKey,
        'journal_no' => 'JV-BAD-' . $suffix,
        'posting_date' => $postingDate,
        'rows' => [
            ['account_key' => $debitAccountKey, 'debit' => '100', 'credit' => '0'],
            ['account_key' => $creditAccountKey, 'debit' => '0', 'credit' => '99'],
        ],
    ]);
    journal_assert($unbalanced['document_status'] === 'DRAFT', 'Unbalanced Journal could not be retained as Draft.');
    journal_expect_error(fn () => yovel_admin_submit_journal_entry($db, $company, $admin, $unbalancedKey), 'balance');
    journal_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash = ? AND source_record_key = ?', [$company['company_key_hash'], $unbalancedKey]) === 0, 'Rejected Journal wrote partial GL rows.');

    yovel_admin_persist_journal_entry($db, $company, $admin, [
        'journal_entry_key' => $frozenJournalKey,
        'journal_no' => 'JV-FROZEN-' . $suffix,
        'posting_date' => $postingDate,
        'rows' => [
            ['account_key' => $debitAccountKey, 'debit' => '10', 'credit' => '0'],
            ['account_key' => $frozenAccountKey, 'debit' => '0', 'credit' => '10'],
        ],
    ]);
    journal_expect_error(fn () => yovel_admin_submit_journal_entry($db, $company, $admin, $frozenJournalKey), 'frozen');

    yovel_admin_persist_finance_period_lock($db, $company, $admin, ['period_lock_key' => $lockKey, 'date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'reason' => 'Journal close fixture', 'status' => 'ACTIVE']);
    journal_expect_error(fn () => yovel_admin_submit_journal_entry($db, $company, $admin, $unbalancedKey), 'closed');
} finally {
    $hash = $company['company_key_hash'];
    $db->Execute('DELETE FROM project_company_finance_period_lock WHERE company_key_hash = ? AND period_lock_key = ?', [$hash, $lockKey]);
    $db->Execute('DELETE FROM project_company_general_ledger_entry WHERE company_key_hash = ? AND source_record_key IN (?, ?, ?)', [$hash, $journalKey, $unbalancedKey, $frozenJournalKey]);
    $db->Execute('DELETE FROM project_company_general_ledger_transaction WHERE company_key_hash = ? AND source_record_key IN (?, ?, ?)', [$hash, $journalKey, $unbalancedKey, $frozenJournalKey]);
    $db->Execute('DELETE FROM project_company_finance_journal_row WHERE company_key_hash = ? AND journal_entry_key IN (?, ?, ?)', [$hash, $journalKey, $unbalancedKey, $frozenJournalKey]);
    $db->Execute('DELETE FROM project_company_finance_journal_entry WHERE company_key_hash = ? AND journal_entry_key IN (?, ?, ?)', [$hash, $journalKey, $unbalancedKey, $frozenJournalKey]);
    $db->Execute('DELETE FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key IN (?, ?, ?)', [$hash, $debitAccountKey, $creditAccountKey, $frozenAccountKey]);
}

echo "Accounting/Finance Journal Entry checks passed.\n";
