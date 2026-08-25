<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/core.php';
require_once $root . '/company/admin/modules/accounting-finance/ledger.php';
require_once $root . '/company/admin/modules/manufacturing/dependencies.php';

function finance_dependency_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function finance_dependency_expect_error(callable $operation, string $needle): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        finance_dependency_assert(
            str_contains(strtolower($error->getMessage()), strtolower($needle)),
            'Unexpected dependency error: ' . $error->getMessage()
        );
        return;
    }
    throw new RuntimeException('Expected an error containing: ' . $needle);
}

foreach ([
    'yovel_admin_finance_account_reference',
    'yovel_admin_finance_manufacturing_cost_preview',
    'yovel_admin_finance_asset_posting_contract',
    'yovel_admin_finance_asset_posting_request',
] as $function) {
    finance_dependency_assert(function_exists($function), 'Finance dependency owner function is missing: ' . $function);
}

yovel_admin_accounting_finance_schema();
yovel_admin_finance_core_schema();
yovel_admin_general_ledger_schema();
$db = bx_db();
$fixture = $db->GetRow(
    "SELECT c.company_key,c.company_key_hash,c.company_name,a.admin_key
       FROM project_company c
       JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash AND a.admin_status='ACTIVE'
      WHERE c.company_status='ACTIVE'
      ORDER BY c.x_id,a.x_id LIMIT 1"
);
finance_dependency_assert(is_array($fixture) && $fixture !== [], 'An active Finance company-admin fixture is required.');
$company = [
    'company_key' => (string) $fixture['company_key'],
    'company_key_hash' => (string) $fixture['company_key_hash'],
    'company_name' => (string) $fixture['company_name'],
];
$admin = ['admin_key' => (string) $fixture['admin_key']];
$suffix = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$assetAccountKey = bx_uuid();
$expenseAccountKey = bx_uuid();
$groupAccountKey = bx_uuid();
$inactiveAccountKey = bx_uuid();
$sourceRecordKey = bx_uuid();
$postTransactionKey = bx_uuid();
$rollbackTransactionKey = bx_uuid();
$postedTransactionKeys = [];
$otherCompanyKey = bx_uuid();
$otherCompanyHash = hash('sha256', $otherCompanyKey);
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id),0) FROM builder_audit_log');

finance_dependency_assert($db->Execute(
    'INSERT INTO project_company (company_key,company_key_hash,company_code,company_slug,company_name,company_status) VALUES (?,?,?,?,?,?)',
    [$otherCompanyKey, $otherCompanyHash, 'FDO' . $suffix, 'finance-dependency-' . strtolower($suffix), 'Finance Dependency Other Company', 'ACTIVE']
) !== false, 'Other-company dependency fixture could not be created.');

$insertAccountSql = "INSERT INTO project_company_accounting_account (
    account_key, company_key, company_key_hash, account_code, account_name, root_type, report_type,
    account_currency, is_group, freeze_account, balance_must_be, account_status, created_by_admin_key, updated_by_admin_key
) VALUES (?, ?, ?, ?, ?, ?, ?, 'PHP', ?, 0, 'EITHER', ?, ?, ?)";
$db->Execute($insertAccountSql, [$assetAccountKey, $company['company_key'], $company['company_key_hash'], 'AST_' . $suffix, 'Asset Cost ' . $suffix, 'ASSET', 'BALANCE_SHEET', 0, 'ACTIVE', $admin['admin_key'], $admin['admin_key']]);
$db->Execute($insertAccountSql, [$expenseAccountKey, $company['company_key'], $company['company_key_hash'], 'DEP_' . $suffix, 'Depreciation Expense ' . $suffix, 'EXPENSE', 'PROFIT_LOSS', 0, 'ACTIVE', $admin['admin_key'], $admin['admin_key']]);
$db->Execute($insertAccountSql, [$groupAccountKey, $company['company_key'], $company['company_key_hash'], 'GRP_' . $suffix, 'Asset Group ' . $suffix, 'ASSET', 'BALANCE_SHEET', 1, 'ACTIVE', $admin['admin_key'], $admin['admin_key']]);
$db->Execute($insertAccountSql, [$inactiveAccountKey, $company['company_key'], $company['company_key_hash'], 'INA_' . $suffix, 'Inactive Account ' . $suffix, 'ASSET', 'BALANCE_SHEET', 0, 'INACTIVE', $admin['admin_key'], $admin['admin_key']]);

try {
    $reference = yovel_admin_finance_account_reference($company, $assetAccountKey);
    finance_dependency_assert(($reference['ok'] ?? false) === true, 'Active posting-account lookup did not succeed.');
    finance_dependency_assert(hash_equals($company['company_key_hash'], (string) ($reference['company_key_hash'] ?? '')), 'Account lookup changed company scope.');
    finance_dependency_assert(($reference['errors'] ?? null) === [], 'Successful account lookup returned errors.');
    finance_dependency_assert(($reference['record']['account_key'] ?? '') === $assetAccountKey, 'Account lookup returned the wrong account.');
    finance_dependency_assert(($reference['record']['account_status'] ?? '') === 'ACTIVE', 'Account lookup returned a non-active account.');
    finance_dependency_assert(($reference['record']['is_group'] ?? null) === false, 'Account lookup did not normalize is_group to boolean false.');

    foreach ([$groupAccountKey, $inactiveAccountKey, bx_uuid()] as $unavailableKey) {
        $unavailable = yovel_admin_finance_account_reference($company, $unavailableKey);
        finance_dependency_assert(($unavailable['ok'] ?? true) === false, 'Unavailable Finance account was exposed as valid.');
        finance_dependency_assert(array_key_exists('record', $unavailable) && $unavailable['record'] === null, 'Unavailable Finance account leaked a record.');
        finance_dependency_assert(($unavailable['errors'] ?? []) === ['ACCOUNT_NOT_AVAILABLE'], 'Unavailable account returned an unstable error contract.');
    }
    $foreignReference = yovel_admin_finance_account_reference([
        'company_key' => $otherCompanyKey,
        'company_key_hash' => $otherCompanyHash,
    ], $assetAccountKey);
    finance_dependency_assert(($foreignReference['ok'] ?? true) === false && ($foreignReference['errors'] ?? []) === ['ACCOUNT_NOT_AVAILABLE'], 'Finance account lookup leaked a record across active companies.');

    $costRequest = [
        'currency' => 'PHP',
        'materials' => [
            ['quantity' => '2.5', 'rate' => '40'],
            ['amount' => '25.125'],
        ],
        'operations' => [
            ['hours' => '1.5', 'hourly_rate' => '120'],
            ['amount' => '19.875'],
        ],
        'additional_costs' => [
            ['amount' => '10'],
            ['quantity' => '2', 'rate' => '3.5'],
        ],
        'scrap' => [
            ['quantity' => '1.25', 'rate' => '8'],
        ],
    ];
    $ledgerCountBeforePreview = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_transaction WHERE company_key_hash=?', [$company['company_key_hash']]);
    $preview = yovel_admin_finance_manufacturing_cost_preview($company, $costRequest);
    $repeatPreview = yovel_admin_finance_manufacturing_cost_preview($company, $costRequest);
    finance_dependency_assert($preview === $repeatPreview, 'Manufacturing cost preview is not deterministic.');
    finance_dependency_assert(($preview['material_cost'] ?? '') === '125.125000', 'Material cost is incorrect.');
    finance_dependency_assert(($preview['operation_cost'] ?? '') === '199.875000', 'Operation cost is incorrect.');
    finance_dependency_assert(($preview['additional_cost'] ?? '') === '17.000000', 'Additional cost is incorrect.');
    finance_dependency_assert(($preview['scrap_credit'] ?? '') === '10.000000', 'Scrap credit is incorrect.');
    finance_dependency_assert(($preview['total_cost'] ?? '') === '332.000000', 'Total manufacturing cost is incorrect.');
    finance_dependency_assert(preg_match('/^[a-f0-9]{64}$/', (string) ($preview['calculation_hash'] ?? '')) === 1, 'Cost preview hash is invalid.');
    finance_dependency_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_transaction WHERE company_key_hash=?', [$company['company_key_hash']]) === $ledgerCountBeforePreview, 'Cost preview persisted a ledger transaction.');
    $manufacturingGateway = yovel_admin_manufacturing_dependency_gateway();
    finance_dependency_assert(($manufacturingGateway['cost_preview']['owner_function'] ?? '') === 'yovel_admin_finance_manufacturing_cost_preview', 'Manufacturing gateway points at the wrong Finance cost owner.');
    finance_dependency_assert(($manufacturingGateway['cost_preview']['available'] ?? false) === true && is_callable($manufacturingGateway['cost_preview']['callable'] ?? null), 'Manufacturing gateway did not discover the Finance cost preview.');
    finance_dependency_expect_error(
        static fn (): array => yovel_admin_finance_manufacturing_cost_preview($company, ['materials' => [['amount' => '-1']]]),
        'non-negative'
    );
    finance_dependency_expect_error(
        static fn (): array => yovel_admin_finance_manufacturing_cost_preview($company, ['scrap' => [['amount' => '1']]]),
        'exceed'
    );

    $contract = yovel_admin_finance_asset_posting_contract();
    finance_dependency_assert(($contract['contract'] ?? '') === 'accounting-finance.asset-posting.v1', 'Asset posting contract identifier changed.');
    finance_dependency_assert(($contract['transaction_owner'] ?? '') === 'CALLER', 'Asset posting contract must join the caller transaction.');
    finance_dependency_assert(($contract['operations'] ?? []) === ['DRAFT', 'POST', 'REVERSE'], 'Asset posting operations are incomplete or unstable.');

    $baseRequest = [
        'posting_date' => '2026-08-25',
        'voucher_type' => 'ASSET_DEPRECIATION',
        'voucher_no' => 'AD-' . $suffix,
        'source_record_key' => $sourceRecordKey,
        'remarks' => 'Finance dependency contract fixture',
        'entries' => [
            ['account_key' => $expenseAccountKey, 'debit' => '150.25', 'credit' => '0'],
            ['account_key' => $assetAccountKey, 'debit' => '0', 'credit' => '150.25'],
        ],
    ];
    $draft = yovel_admin_finance_asset_posting_request($db, $company, [], ['operation' => 'DRAFT'] + $baseRequest);
    finance_dependency_assert(($draft['operation'] ?? '') === 'DRAFT' && ($draft['entry_count'] ?? 0) === 2, 'Asset posting draft did not normalize both entries.');
    finance_dependency_assert(($draft['total_debit'] ?? '') === '150.250000' && ($draft['total_credit'] ?? '') === '150.250000', 'Asset posting draft is not balanced.');
    finance_dependency_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND source_record_key=?', [$company['company_key_hash'], $sourceRecordKey]) === 0, 'Asset posting draft persisted data.');
    finance_dependency_expect_error(
        static fn (): array => yovel_admin_finance_asset_posting_request($db, $company, [], array_replace(
            ['operation' => 'DRAFT'],
            $baseRequest,
            ['entries' => [
                ['account_key' => $expenseAccountKey, 'debit' => '150', 'credit' => '0'],
                ['account_key' => $assetAccountKey, 'debit' => '0', 'credit' => '149'],
            ]]
        )),
        'balance'
    );

    finance_dependency_expect_error(
        static fn (): array => yovel_admin_finance_asset_posting_request($db, $company, $admin, ['operation' => 'POST', 'idempotency_key' => 'assets:no-transaction:' . $suffix] + $baseRequest),
        'caller transaction'
    );

    finance_dependency_assert($db->BeginTrans() !== false, 'Asset posting rollback transaction could not start.');
    try {
        yovel_admin_finance_asset_posting_request($db, $company, $admin, [
            'operation' => 'POST',
            'transaction_key' => $rollbackTransactionKey,
            'idempotency_key' => 'assets:rollback:' . $suffix,
            'checkpoint' => static function (string $checkpoint): void {
                if ($checkpoint === 'after_first_entry') {
                    throw new RuntimeException('Injected asset posting failure.');
                }
            },
        ] + $baseRequest);
        throw new RuntimeException('Injected asset posting failure did not stop the write.');
    } catch (RuntimeException $error) {
        finance_dependency_assert(str_contains($error->getMessage(), 'Injected asset posting failure'), 'Unexpected rollback-injection error.');
        $db->RollbackTrans();
    }
    finance_dependency_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND transaction_key=?', [$company['company_key_hash'], $rollbackTransactionKey]) === 0, 'Caller rollback left an asset transaction header.');
    finance_dependency_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=?', [$company['company_key_hash'], $rollbackTransactionKey]) === 0, 'Caller rollback left asset ledger entries.');
    finance_dependency_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE x_id>? AND module='project_company_general_ledger_transaction' AND record_key=?", [$auditFloor, $rollbackTransactionKey]) === 0, 'Caller rollback left an asset posting audit record.');

    finance_dependency_assert($db->BeginTrans() !== false, 'Asset posting transaction could not start.');
    $posted = yovel_admin_finance_asset_posting_request($db, $company, $admin, [
        'operation' => 'POST',
        'transaction_key' => $postTransactionKey,
        'idempotency_key' => 'assets:post:' . $suffix,
    ] + $baseRequest);
    finance_dependency_assert($db->transCnt > 0, 'Finance committed a caller-owned asset transaction.');
    finance_dependency_assert(($posted['transaction_key'] ?? '') === $postTransactionKey && ($posted['idempotent'] ?? true) === false, 'Asset post did not return exact ledger read-back.');
    finance_dependency_assert($db->CommitTrans() !== false, 'Caller could not commit the asset posting transaction.');
    $postedTransactionKeys[] = $postTransactionKey;
    finance_dependency_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=?', [$company['company_key_hash'], $postTransactionKey]) === 2, 'Asset post did not persist exactly two ledger entries.');

    finance_dependency_assert($db->BeginTrans() !== false, 'Asset idempotency transaction could not start.');
    $retried = yovel_admin_finance_asset_posting_request($db, $company, $admin, [
        'operation' => 'POST',
        'idempotency_key' => 'assets:post:' . $suffix,
    ] + $baseRequest);
    finance_dependency_assert(($retried['transaction_key'] ?? '') === $postTransactionKey && ($retried['idempotent'] ?? false) === true, 'Asset post retry was not idempotent.');
    $db->CommitTrans();

    finance_dependency_assert($db->BeginTrans() !== false, 'Asset idempotency conflict transaction could not start.');
    try {
        finance_dependency_expect_error(
            static fn (): array => yovel_admin_finance_asset_posting_request($db, $company, $admin, array_replace(
                ['operation' => 'POST', 'idempotency_key' => 'assets:post:' . $suffix],
                $baseRequest,
                ['entries' => [
                    ['account_key' => $expenseAccountKey, 'debit' => '151.25', 'credit' => '0'],
                    ['account_key' => $assetAccountKey, 'debit' => '0', 'credit' => '151.25'],
                ]]
            )),
            'different content'
        );
    } finally {
        $db->RollbackTrans();
    }

    finance_dependency_assert($db->BeginTrans() !== false, 'Asset authorization transaction could not start.');
    try {
        finance_dependency_expect_error(
            static fn (): array => yovel_admin_finance_asset_posting_request($db, $company, ['admin_key' => bx_uuid()], [
                'operation' => 'POST',
                'idempotency_key' => 'assets:unauthorized:' . $suffix,
            ] + $baseRequest),
            'authorized'
        );
    } finally {
        $db->RollbackTrans();
    }

    finance_dependency_assert($db->BeginTrans() !== false, 'Asset reversal transaction could not start.');
    $reversal = yovel_admin_finance_asset_posting_request($db, $company, $admin, [
        'operation' => 'REVERSE',
        'transaction_key' => $postTransactionKey,
        'posting_date' => '2026-08-26',
        'reason' => 'Reverse dependency fixture',
        'idempotency_key' => 'assets:reverse:' . $suffix,
    ]);
    finance_dependency_assert($db->transCnt > 0, 'Finance committed a caller-owned asset reversal transaction.');
    finance_dependency_assert(($reversal['reversal_of_transaction_key'] ?? '') === $postTransactionKey, 'Asset reversal did not link the immutable original.');
    $db->CommitTrans();
    $postedTransactionKeys[] = (string) $reversal['transaction_key'];
    finance_dependency_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=?', [$company['company_key_hash'], $postTransactionKey]) === 2, 'Asset reversal mutated original entries.');
    $originalTotals = $db->GetRow('SELECT CAST(SUM(debit) AS CHAR) debit,CAST(SUM(credit) AS CHAR) credit FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=?', [$company['company_key_hash'], $postTransactionKey]);
    finance_dependency_assert(number_format((float) ($originalTotals['debit'] ?? 0), 6, '.', '') === '150.250000' && number_format((float) ($originalTotals['credit'] ?? 0), 6, '.', '') === '150.250000', 'Asset reversal changed original entry amounts.');

    finance_dependency_assert($db->BeginTrans() !== false, 'Asset reversal retry transaction could not start.');
    $retriedReversal = yovel_admin_finance_asset_posting_request($db, $company, $admin, [
        'operation' => 'REVERSE',
        'transaction_key' => $postTransactionKey,
        'posting_date' => '2026-08-26',
        'reason' => 'Reverse dependency fixture',
        'idempotency_key' => 'assets:reverse:' . $suffix,
    ]);
    finance_dependency_assert(($retriedReversal['transaction_key'] ?? '') === ($reversal['transaction_key'] ?? '') && ($retriedReversal['idempotent'] ?? false) === true, 'Asset reversal retry was not idempotent.');
    $db->CommitTrans();

    $auditCount = 0;
    foreach ($postedTransactionKeys as $transactionKey) {
        $auditCount += (int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE x_id>? AND module='project_company_general_ledger_transaction' AND record_key=?", [$auditFloor, $transactionKey]);
    }
    finance_dependency_assert($auditCount === 2, 'Asset post and reversal did not create exact transactional audit records.');
} finally {
    if ($db->transCnt > 0) {
        $db->RollbackTrans();
    }
    foreach (array_unique(array_filter(array_merge($postedTransactionKeys, [$postTransactionKey, $rollbackTransactionKey]))) as $transactionKey) {
        $db->Execute('DELETE FROM project_company_general_ledger_entry WHERE company_key_hash=? AND transaction_key=?', [$company['company_key_hash'], $transactionKey]);
        $db->Execute('DELETE FROM project_company_general_ledger_transaction WHERE company_key_hash=? AND transaction_key=?', [$company['company_key_hash'], $transactionKey]);
        $db->Execute("DELETE FROM builder_audit_log WHERE x_id>? AND module='project_company_general_ledger_transaction' AND record_key=?", [$auditFloor, $transactionKey]);
    }
    $db->Execute('DELETE FROM project_company_accounting_account WHERE company_key_hash=? AND account_key IN (?,?,?,?)', [$company['company_key_hash'], $assetAccountKey, $expenseAccountKey, $groupAccountKey, $inactiveAccountKey]);
    $db->Execute('DELETE FROM project_company WHERE company_key=? AND company_key_hash=?', [$otherCompanyKey, $otherCompanyHash]);
}

echo "Accounting/Finance dependency owner contract checks passed.\n";
