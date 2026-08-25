<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/assets-maintenance/functions.php';

function assets_depreciation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assets_depreciation_error(callable $operation, string $needle): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        assets_depreciation_assert(str_contains(strtolower($error->getMessage()), strtolower($needle)), 'Unexpected Task 3 error: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected Task 3 error containing: ' . $needle);
}

function assets_depreciation_fixture(ADOConnection $db, string $suffix, string $label): array
{
    $companyKey = 'assets:depreciation/' . $suffix . '/opaque';
    $companyKeyHash = hash('sha256', $companyKey);
    $adminKey = bx_uuid();
    assets_depreciation_assert($db->Execute(
        "INSERT INTO project_company (company_key,company_key_hash,company_code,company_slug,company_name,company_status) VALUES (?,?,?,?,?,'ACTIVE')",
        [$companyKey, $companyKeyHash, 'ADP' . strtoupper(substr(hash('sha256', $suffix), 0, 12)), 'assets-depreciation-' . $suffix, $label]
    ) !== false, 'Task 3 company fixture could not be created.');
    assets_depreciation_assert($db->Execute(
        "INSERT INTO project_company_admin (admin_key,company_key,company_key_hash,admin_login,admin_password_hash,admin_name,admin_email,admin_status) VALUES (?,?,?,?,?,?,?,'ACTIVE')",
        [$adminKey, $companyKey, $companyKeyHash, 'assets_depreciation_' . $suffix, password_hash('depreciation-only', PASSWORD_DEFAULT), $label . ' Admin', $suffix . '@example.test']
    ) !== false, 'Task 3 administrator fixture could not be created.');
    return [
        'company' => ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'company_name' => $label, 'company_slug' => 'assets-depreciation-' . $suffix, 'base_currency' => 'PHP'],
        'admin' => ['admin_key' => $adminKey, 'admin_status' => 'ACTIVE'],
    ];
}

assets_depreciation_assert(function_exists('yovel_admin_assets_calculate_schedule'), 'Assets depreciation calculator is missing.');
foreach ([
    'yovel_admin_assets_save_finance_book',
    'yovel_admin_assets_save_depreciation_schedule',
    'yovel_admin_assets_submit_depreciation_schedule',
    'yovel_admin_assets_post_depreciation_catchup',
    'yovel_admin_assets_cancel_depreciation_schedule',
    'yovel_admin_assets_save_shift_factor',
    'yovel_admin_assets_save_shift_allocation',
    'yovel_admin_assets_submit_shift_allocation',
    'yovel_admin_assets_save_value_adjustment',
    'yovel_admin_assets_submit_value_adjustment',
    'yovel_admin_assets_cancel_value_adjustment',
] as $function) {
    assets_depreciation_assert(function_exists($function), 'Assets Task 3 service is missing: ' . $function . '.');
}

$straightLine = yovel_admin_assets_calculate_schedule([
    'method' => 'STRAIGHT_LINE', 'gross_amount' => '1000.00', 'salvage_value' => '100.00',
    'opening_depreciation' => '100.00', 'period_count' => 3, 'frequency_months' => 1, 'start_date' => '2026-01-01',
]);
assets_depreciation_assert(array_column($straightLine['lines'], 'depreciation_amount') === ['266.67', '266.67', '266.66'], 'Straight-line remainder allocation is not exact.');
assets_depreciation_assert(($straightLine['total_depreciation'] ?? '') === '800.00' && ($straightLine['closing_value'] ?? '') === '100.00', 'Straight-line totals or salvage floor are incorrect.');

$doubleDeclining = yovel_admin_assets_calculate_schedule([
    'method' => 'DOUBLE_DECLINING', 'gross_amount' => '1000.00', 'salvage_value' => '100.00',
    'opening_depreciation' => '0', 'period_count' => 4, 'frequency_months' => 1, 'start_date' => '2026-01-01',
]);
assets_depreciation_assert(array_column($doubleDeclining['lines'], 'depreciation_amount') === ['500.00', '250.00', '125.00', '25.00'], 'Double-declining calculation or salvage clamp is incorrect.');

$writtenDown = yovel_admin_assets_calculate_schedule([
    'method' => 'WRITTEN_DOWN_VALUE', 'gross_amount' => '1000.00', 'salvage_value' => '100.00',
    'opening_depreciation' => '0', 'period_count' => 4, 'frequency_months' => 1, 'start_date' => '2026-01-01', 'rate_basis_points' => 2500,
]);
assets_depreciation_assert(array_column($writtenDown['lines'], 'depreciation_amount') === ['250.00', '187.50', '140.63', '321.87'], 'Written-down-value rounding is not deterministic.');

$manual = yovel_admin_assets_calculate_schedule([
    'method' => 'MANUAL', 'gross_amount' => '700.00', 'salvage_value' => '100.00', 'opening_depreciation' => '0',
    'period_count' => 3, 'frequency_months' => 1, 'start_date' => '2026-01-01',
    'manual_lines' => [
        ['posting_date' => '2026-01-10', 'amount' => '100.00'],
        ['posting_date' => '2026-02-10', 'amount' => '200.00'],
        ['posting_date' => '2026-03-10', 'amount' => '300.00'],
    ],
]);
assets_depreciation_assert(array_column($manual['lines'], 'depreciation_amount') === ['100.00', '200.00', '300.00'], 'Manual schedule rows were not preserved exactly.');
assets_depreciation_error(static fn (): array => yovel_admin_assets_calculate_schedule([
    'method' => 'MANUAL', 'gross_amount' => '700.00', 'salvage_value' => '100.00', 'opening_depreciation' => '0',
    'period_count' => 2, 'frequency_months' => 1, 'start_date' => '2026-01-01',
    'manual_lines' => [['posting_date' => '2026-01-01', 'amount' => '200.00'], ['posting_date' => '2026-02-01', 'amount' => '200.00']],
]), 'depreciable');

$daily = yovel_admin_assets_calculate_schedule([
    'method' => 'STRAIGHT_LINE', 'gross_amount' => '1200.00', 'salvage_value' => '0', 'opening_depreciation' => '0',
    'period_count' => 12, 'frequency_months' => 1, 'start_date' => '2026-01-16', 'daily_prorata' => true,
]);
assets_depreciation_assert((int) $daily['lines'][0]['depreciation_amount_minor'] < (int) $daily['lines'][1]['depreciation_amount_minor'], 'Daily prorating did not reduce the first partial period.');
assets_depreciation_assert(($daily['closing_value'] ?? '') === '0.00', 'Daily prorating did not preserve the exact depreciable total.');
assets_depreciation_error(static fn (): array => yovel_admin_assets_calculate_schedule([
    'method' => 'STRAIGHT_LINE', 'gross_amount' => '100.00', 'salvage_value' => '0', 'opening_depreciation' => '0',
    'period_count' => 1001, 'frequency_months' => 1, 'start_date' => '2026-01-01',
]), 'period count');

$db = bx_db();
$suffix = substr(hash('sha256', bx_uuid()), 0, 14);
$primary = assets_depreciation_fixture($db, $suffix . 'a', 'Assets Depreciation Company');
$other = assets_depreciation_fixture($db, $suffix . 'b', 'Other Depreciation Company');
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id),0) FROM builder_audit_log');
$taskTables = [
    'project_company_asset_value_adjustment',
    'project_company_asset_shift_allocation',
    'project_company_asset_shift_factor',
    'project_company_asset_depreciation_line',
    'project_company_asset_depreciation_schedule',
    'project_company_asset_finance_book',
];
$baseTables = [
    'project_company_asset_activity', 'project_company_asset', 'project_company_asset_category_account',
    'project_company_asset_category', 'project_company_asset_location', 'project_company_asset_lifecycle_audit',
];
register_shutdown_function(static function () use ($db, $taskTables, $baseTables, $primary, $other, $auditFloor): void {
    unset($GLOBALS['yovel_admin_assets_test_fault']);
    foreach ([$primary, $other] as $fixture) {
        $hash = (string) $fixture['company']['company_key_hash'];
        foreach (array_merge($taskTables, $baseTables) as $table) {
            if ((int) $db->GetOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [$table]) === 1) {
                $db->Execute("DELETE FROM {$table} WHERE company_key_hash=?", [$hash]);
            }
        }
        $db->Execute('DELETE FROM project_company_admin WHERE company_key_hash=?', [$hash]);
        $db->Execute('DELETE FROM project_company WHERE company_key_hash=?', [$hash]);
    }
    $db->Execute("DELETE FROM builder_audit_log WHERE x_id>? AND module LIKE 'project_company_asset%'", [$auditFloor]);
});

yovel_admin_assets_maintenance_schema();
yovel_admin_assets_maintenance_schema();
foreach ($taskTables as $table) {
    assets_depreciation_assert((int) $db->GetOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [$table]) === 1, 'Task 3 schema is missing ' . $table . '.');
}

$itemKey = 'inventory:item/' . $suffix;
$postingCalls = ['DRAFT' => 0, 'POST' => 0, 'REVERSE' => 0];
$postingKeys = [];
$postingRequests = [];
$services = [
    'inventory.item.read' => static fn (array $company, string $key): ?array => $key === $itemKey ? [
        'company_key_hash' => (string) $company['company_key_hash'], 'item_key' => $key,
        'item_code' => 'DEP-ITEM', 'item_name' => 'Depreciable machine', 'item_status' => 'ACTIVE', 'item_kind' => 'STOCK',
    ] : null,
    'hr.employee.read' => static fn (array $company, string $key): ?array => null,
    'finance.account.read' => static function (array $company, string $key): array {
        $root = str_contains($key, 'expense') || str_contains($key, 'gain') ? 'EXPENSE' : 'ASSET';
        return ['ok' => true, 'company_key_hash' => (string) $company['company_key_hash'], 'record' => [
            'account_key' => $key, 'account_name' => strtoupper(basename(str_replace(':', '/', $key))), 'root_type' => $root,
            'account_status' => 'ACTIVE', 'is_group' => false, 'freeze_account' => false,
        ]];
    },
    'finance.asset-posting.readiness' => static fn (array $company): array => [
        'contract' => 'accounting-finance.asset-posting.v1', 'owner' => 'accounting-finance',
        'owner_function' => 'yovel_admin_finance_asset_posting_request', 'transaction_owner' => 'CALLER',
        'operations' => ['DRAFT', 'POST', 'REVERSE'],
    ],
    'finance.asset-posting.command' => static function (ADOConnection $connection, array $company, array $admin, array $request) use (&$postingCalls, &$postingKeys, &$postingRequests): array {
        $operation = (string) ($request['operation'] ?? '');
        $postingCalls[$operation]++;
        $postingRequests[] = $request;
        assets_depreciation_assert($connection->transCnt > 0, 'Finance posting command was not called inside the Assets transaction.');
        if (($GLOBALS['yovel_admin_assets_test_fault'] ?? '') === 'finance_post_reject' && $operation === 'POST') {
            throw new RuntimeException('Injected Finance posting rejection.');
        }
        if ($operation === 'REVERSE') {
            $idempotency = (string) ($request['idempotency_key'] ?? '');
            $postingKeys[$idempotency] ??= bx_uuid();
            return [
                'contract' => 'accounting-finance.asset-posting.v1', 'operation' => 'REVERSE',
                'company_key_hash' => (string) $company['company_key_hash'], 'transaction_key' => $postingKeys[$idempotency],
                'reversal_of_transaction_key' => (string) $request['transaction_key'], 'entry_count' => 2,
                'total_debit' => '0.000000', 'total_credit' => '0.000000', 'idempotent' => false,
            ];
        }
        $entries = (array) ($request['entries'] ?? []);
        $debit = array_sum(array_map(static fn (array $row): float => (float) ($row['debit'] ?? 0), $entries));
        $credit = array_sum(array_map(static fn (array $row): float => (float) ($row['credit'] ?? 0), $entries));
        assets_depreciation_assert(count($entries) === 2 && abs($debit - $credit) < 0.000001, 'Assets sent an unbalanced Finance posting command.');
        if ($operation === 'DRAFT') {
            return [
                'contract' => 'accounting-finance.asset-posting.v1', 'operation' => 'DRAFT',
                'company_key_hash' => (string) $company['company_key_hash'], 'entry_count' => 2,
                'total_debit' => number_format($debit, 6, '.', ''), 'total_credit' => number_format($credit, 6, '.', ''),
                'calculation_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
            ];
        }
        $idempotency = (string) ($request['idempotency_key'] ?? '');
        $wasKnown = array_key_exists($idempotency, $postingKeys);
        $postingKeys[$idempotency] ??= (string) ($request['transaction_key'] ?? bx_uuid());
        return [
            'contract' => 'accounting-finance.asset-posting.v1', 'operation' => 'POST',
            'company_key_hash' => (string) $company['company_key_hash'], 'transaction_key' => $postingKeys[$idempotency],
            'entry_count' => 2, 'total_debit' => number_format($debit, 6, '.', ''),
            'total_credit' => number_format($credit, 6, '.', ''), 'idempotent' => $wasKnown,
            'calculation_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
        ];
    },
];

$location = yovel_admin_assets_save_location($primary['company'], $primary['admin'], [
    'location_code' => 'DEP-' . strtoupper(substr($suffix, 0, 6)), 'location_name' => 'Depreciation Lab', 'location_status' => 'ACTIVE',
]);
$category = yovel_admin_assets_save_category($primary['company'], $primary['admin'], [
    'category_code' => 'DEP-' . strtoupper(substr($suffix, 0, 6)), 'category_name' => 'Depreciable Equipment', 'category_status' => 'ACTIVE',
    'accounts' => [
        ['account_role' => 'ASSET', 'account_owner_key' => 'finance:asset/' . $suffix, 'account_label' => 'Fixed assets'],
        ['account_role' => 'ACCUMULATED_DEPRECIATION', 'account_owner_key' => 'finance:accumulated/' . $suffix, 'account_label' => 'Accumulated depreciation'],
        ['account_role' => 'DEPRECIATION_EXPENSE', 'account_owner_key' => 'finance:expense/' . $suffix, 'account_label' => 'Depreciation expense'],
        ['account_role' => 'GAIN_LOSS', 'account_owner_key' => 'finance:gain/' . $suffix, 'account_label' => 'Asset gain or loss'],
    ],
], $services);
$asset = yovel_admin_assets_save_asset($primary['company'], $primary['admin'], [
    'asset_code' => 'DEP-AST-' . strtoupper(substr($suffix, 0, 6)), 'asset_name' => 'Depreciable Machine',
    'item_owner_key' => $itemKey, 'category_key' => (string) $category['category_key'], 'location_key' => (string) $location['location_key'],
    'acquisition_date' => '2026-01-01', 'available_for_use_date' => '2026-01-01', 'purchase_reference_type' => 'OPENING',
    'purchase_reference_key' => 'opening:' . $suffix, 'purchase_quantity' => '1', 'gross_purchase_amount' => '1200.00',
    'lifecycle_status' => 'IN_USE', 'notes' => 'Task 3 fixture',
], $services);
$asset = yovel_admin_assets_submit_asset($primary['company'], $primary['admin'], (string) $asset['asset_key'], $services);

$book = yovel_admin_assets_save_finance_book($primary['company'], $primary['admin'], [
    'asset_key' => (string) $asset['asset_key'], 'book_code' => 'PRIMARY', 'book_name' => 'Primary Finance Book',
    'method' => 'STRAIGHT_LINE', 'gross_amount' => '1200.00', 'salvage_value' => '0', 'opening_depreciation' => '0',
    'period_count' => 6, 'frequency_months' => 1, 'daily_prorata' => false, 'rate_basis_points' => 0,
], $services);
assets_depreciation_assert(($book['book_status'] ?? '') === 'ACTIVE' && (string) $book['gross_amount_minor'] === '120000', 'Asset Finance Book exact read-back failed.');
assets_depreciation_assert(yovel_admin_assets_finance_book($other['company'], (string) $book['finance_book_key']) === null, 'Finance Book crossed company scope.');

$schedule = yovel_admin_assets_save_depreciation_schedule($primary['company'], $primary['admin'], [
    'finance_book_key' => (string) $book['finance_book_key'], 'start_date' => '2026-01-01',
    'posting_through_date' => '2026-03-01', 'notes' => 'Initial schedule',
], $services);
assets_depreciation_assert(($schedule['document_status'] ?? '') === 'DRAFT' && count($schedule['lines'] ?? []) === 6, 'Depreciation schedule draft or line read-back failed.');
assets_depreciation_error(static fn (): array => yovel_admin_assets_save_depreciation_schedule($primary['company'], $primary['admin'], [
    'finance_book_key' => (string) $book['finance_book_key'], 'start_date' => '2026-02-01', 'posting_through_date' => '2026-03-01',
], $services), 'active schedule');

$submitted = yovel_admin_assets_submit_depreciation_schedule($primary['company'], $primary['admin'], (string) $schedule['schedule_key'], $services);
assets_depreciation_assert(($submitted['document_status'] ?? '') === 'SUBMITTED', 'Depreciation schedule did not submit.');
assets_depreciation_assert(count(array_filter($submitted['lines'], static fn (array $line): bool => ($line['posting_status'] ?? '') === 'POSTED')) === 3, 'Due depreciation catch-up did not post exactly three lines.');
assets_depreciation_assert($postingCalls['DRAFT'] === 3 && $postingCalls['POST'] === 3, 'Depreciation did not call balanced Finance draft and post once per due line.');
$retry = yovel_admin_assets_submit_depreciation_schedule($primary['company'], $primary['admin'], (string) $schedule['schedule_key'], $services);
assets_depreciation_assert(($retry['schedule_key'] ?? '') === ($submitted['schedule_key'] ?? '') && $postingCalls['POST'] === 3, 'Duplicate schedule submission was not idempotent.');
assets_depreciation_error(static fn (): array => yovel_admin_assets_save_depreciation_schedule($primary['company'], $primary['admin'], [
    'schedule_key' => (string) $schedule['schedule_key'], 'finance_book_key' => (string) $book['finance_book_key'],
    'start_date' => '2026-01-01', 'posting_through_date' => '2026-04-01',
], $services), 'immutable');

$catchup = yovel_admin_assets_post_depreciation_catchup($primary['company'], $primary['admin'], (string) $schedule['schedule_key'], '2026-03-01', $services);
assets_depreciation_assert($postingCalls['POST'] === 3 && count(array_filter($catchup['lines'], static fn (array $line): bool => ($line['posting_status'] ?? '') === 'POSTED')) === 3, 'Catch-up retry duplicated Finance postings.');

$beforeRejected = yovel_admin_assets_depreciation_schedule($primary['company'], (string) $schedule['schedule_key']);
$GLOBALS['yovel_admin_assets_test_fault'] = 'finance_post_reject';
assets_depreciation_error(static fn (): array => yovel_admin_assets_post_depreciation_catchup($primary['company'], $primary['admin'], (string) $schedule['schedule_key'], '2026-04-01', $services), 'Finance posting rejection');
unset($GLOBALS['yovel_admin_assets_test_fault']);
$afterRejected = yovel_admin_assets_depreciation_schedule($primary['company'], (string) $schedule['schedule_key']);
assets_depreciation_assert(($afterRejected['lines'][3]['posting_status'] ?? '') === 'PENDING' && ($beforeRejected['lines'][3]['finance_transaction_key'] ?? '') === ($afterRejected['lines'][3]['finance_transaction_key'] ?? ''), 'Finance rejection left a partial depreciation post.');

$cancelled = yovel_admin_assets_cancel_depreciation_schedule($primary['company'], $primary['admin'], (string) $schedule['schedule_key'], 'Replace useful life.', $services);
assets_depreciation_assert(($cancelled['document_status'] ?? '') === 'CANCELLED' && $postingCalls['REVERSE'] === 3, 'Schedule cancellation did not reverse every posted line.');
assets_depreciation_assert(count(array_filter($cancelled['lines'], static fn (array $line): bool => ($line['posting_status'] ?? '') === 'REVERSED')) === 3, 'Schedule reversal state was not read back.');
$cancelRetry = yovel_admin_assets_cancel_depreciation_schedule($primary['company'], $primary['admin'], (string) $schedule['schedule_key'], 'Replace useful life.', $services);
assets_depreciation_assert(($cancelRetry['schedule_key'] ?? '') === ($cancelled['schedule_key'] ?? '') && $postingCalls['REVERSE'] === 3, 'Schedule reversal retry was not idempotent.');

$amendment = yovel_admin_assets_save_depreciation_schedule($primary['company'], $primary['admin'], [
    'finance_book_key' => (string) $book['finance_book_key'], 'start_date' => '2026-04-01',
    'posting_through_date' => '2026-04-01', 'replacement_of_schedule_key' => (string) $schedule['schedule_key'],
    'period_count' => 6, 'gross_amount' => '1200.00', 'salvage_value' => '0', 'opening_depreciation' => '600.00',
    'notes' => 'Amended remaining schedule',
], $services);
assets_depreciation_assert((string) $amendment['replacement_of_schedule_key'] === (string) $schedule['schedule_key'], 'Schedule amendment link was not retained.');
$amendment = yovel_admin_assets_submit_depreciation_schedule($primary['company'], $primary['admin'], (string) $amendment['schedule_key'], $services);

$factor = yovel_admin_assets_save_shift_factor($primary['company'], $primary['admin'], [
    'factor_code' => 'DOUBLE', 'factor_name' => 'Double shift', 'multiplier' => '2.0000', 'factor_status' => 'ACTIVE',
]);
$allocation = yovel_admin_assets_save_shift_allocation($primary['company'], $primary['admin'], [
    'asset_key' => (string) $asset['asset_key'], 'finance_book_key' => (string) $book['finance_book_key'],
    'schedule_key' => (string) $amendment['schedule_key'], 'shift_factor_key' => (string) $factor['shift_factor_key'],
    'effective_date' => '2026-05-01', 'reason' => 'Move to double shift.',
]);
$allocation = yovel_admin_assets_submit_shift_allocation($primary['company'], $primary['admin'], (string) $allocation['shift_allocation_key'], $services);
assets_depreciation_assert(($allocation['document_status'] ?? '') === 'SUBMITTED' && yovel_admin_is_uuid((string) ($allocation['replacement_schedule_key'] ?? '')), 'Shift allocation did not create a replacement schedule.');
$superseded = yovel_admin_assets_depreciation_schedule($primary['company'], (string) $amendment['schedule_key']);
$shiftReplacement = yovel_admin_assets_depreciation_schedule($primary['company'], (string) $allocation['replacement_schedule_key']);
assets_depreciation_assert((string) ($superseded['replaced_by_schedule_key'] ?? '') === (string) $shiftReplacement['schedule_key'], 'Submitted schedule was not linked to its shift replacement.');
assets_depreciation_assert(count($shiftReplacement['lines'] ?? []) < count(array_filter($amendment['lines'], static fn (array $line): bool => ($line['posting_status'] ?? '') === 'PENDING')), 'Shift factor did not accelerate the remaining schedule.');

$adjustment = yovel_admin_assets_save_value_adjustment($primary['company'], $primary['admin'], [
    'asset_key' => (string) $asset['asset_key'], 'adjustment_type' => 'INCREASE', 'posting_date' => '2026-06-01',
    'amount' => '75.25', 'reason' => 'Revaluation increase.',
]);
$GLOBALS['yovel_admin_assets_test_fault'] = 'finance_post_reject';
assets_depreciation_error(static fn (): array => yovel_admin_assets_submit_value_adjustment($primary['company'], $primary['admin'], (string) $adjustment['adjustment_key'], $services), 'Finance posting rejection');
unset($GLOBALS['yovel_admin_assets_test_fault']);
assets_depreciation_assert((yovel_admin_assets_value_adjustment($primary['company'], (string) $adjustment['adjustment_key'])['document_status'] ?? '') === 'DRAFT', 'Rejected value adjustment did not roll back.');
$adjustment = yovel_admin_assets_submit_value_adjustment($primary['company'], $primary['admin'], (string) $adjustment['adjustment_key'], $services);
assets_depreciation_assert(($adjustment['document_status'] ?? '') === 'SUBMITTED' && yovel_admin_is_uuid((string) ($adjustment['finance_transaction_key'] ?? '')), 'Value adjustment posting read-back failed.');
$adjustmentRetry = yovel_admin_assets_submit_value_adjustment($primary['company'], $primary['admin'], (string) $adjustment['adjustment_key'], $services);
assets_depreciation_assert((string) $adjustmentRetry['finance_transaction_key'] === (string) $adjustment['finance_transaction_key'], 'Value adjustment retry duplicated posting.');
$adjustmentCancelled = yovel_admin_assets_cancel_value_adjustment($primary['company'], $primary['admin'], (string) $adjustment['adjustment_key'], 'Adjustment superseded.', $services);
assets_depreciation_assert(($adjustmentCancelled['document_status'] ?? '') === 'CANCELLED' && yovel_admin_is_uuid((string) ($adjustmentCancelled['finance_reversal_transaction_key'] ?? '')), 'Value adjustment reversal failed.');
$decrease = yovel_admin_assets_save_value_adjustment($primary['company'], $primary['admin'], [
    'asset_key' => (string) $asset['asset_key'], 'adjustment_type' => 'DECREASE', 'posting_date' => '2026-06-02',
    'amount' => '10.50', 'reason' => 'Revaluation decrease.',
]);
$decrease = yovel_admin_assets_submit_value_adjustment($primary['company'], $primary['admin'], (string) $decrease['adjustment_key'], $services);
$decreasePost = end($postingRequests);
assets_depreciation_assert(($decrease['document_status'] ?? '') === 'SUBMITTED'
    && str_contains((string) ($decreasePost['entries'][0]['account_key'] ?? ''), 'gain')
    && str_contains((string) ($decreasePost['entries'][1]['account_key'] ?? ''), 'asset'), 'Value decrease did not debit gain/loss and credit the Asset account.');
assets_depreciation_error(static fn (): array => yovel_admin_assets_save_value_adjustment($primary['company'], $primary['admin'], [
    'asset_key' => (string) $asset['asset_key'], 'adjustment_type' => 'DECREASE', 'posting_date' => '2025-12-31', 'amount' => '1.00', 'reason' => 'Past date',
]), 'acquisition');
assets_depreciation_assert(yovel_admin_assets_value_adjustment($other['company'], (string) $adjustment['adjustment_key']) === null, 'Value Adjustment crossed company scope.');

$activities = yovel_admin_assets_activities($primary['company'], (string) $asset['asset_key']);
foreach (['DEPRECIATION_SUBMIT', 'DEPRECIATION_POST', 'DEPRECIATION_CANCEL', 'SHIFT_ALLOCATE', 'VALUE_ADJUST', 'VALUE_ADJUST_CANCEL'] as $action) {
    assets_depreciation_assert(in_array($action, array_column($activities, 'activity_type'), true), 'Asset Activity is missing Task 3 action ' . $action . '.');
}
assets_depreciation_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE x_id>? AND module LIKE 'project_company_asset%'", [$auditFloor]) >= 10, 'Task 3 transactional audit evidence is incomplete.');

$data = yovel_admin_assets_maintenance_data($primary['company'], $primary['admin'], 'asset-depreciation-schedule');
assets_depreciation_assert(($data['state']['kind'] ?? '') === 'ready', 'Depreciation workspace is not ready.');
assets_depreciation_assert(count($data['finance_books'] ?? []) === 1 && count($data['depreciation_schedules'] ?? []) >= 3, 'Task 3 server data did not rehydrate.');
$activeModuleSection = 'asset-depreciation-schedule';
$activeModuleData = $data;
$activeModuleFormState = [
    'section' => 'asset-depreciation-schedule', 'action' => 'save_asset_finance_book',
    'input' => ['book_code' => 'REHYDRATED-BOOK', 'book_name' => 'Retained Finance Book'],
    'error' => 'Controller-retained depreciation validation error.',
];
$companyName = $primary['company']['company_name'];
ob_start();
require $root . '/company/admin/modules/assets-maintenance/views/workspace.php';
$renderedTask3 = (string) ob_get_clean();
assets_depreciation_assert(str_contains($renderedTask3, 'REHYDRATED-BOOK') && str_contains($renderedTask3, 'Controller-retained depreciation validation error.') && str_contains($renderedTask3, 'data-record-modal-open-on-load'), 'Task 3 server rehydration did not reopen the populated modal.');
$activeModuleFormState = [];
$workspace = file_get_contents($root . '/company/admin/modules/assets-maintenance/views/workspace.php');
$modal = file_get_contents($root . '/company/admin/modules/assets-maintenance/views/record-modal.php');
foreach (['data-assets-depreciation-list', 'data-assets-depreciation-main', 'data-assets-depreciation-tools'] as $marker) {
    assets_depreciation_assert(str_contains($workspace, $marker), 'Task 3 workspace marker is missing: ' . $marker . '.');
}
foreach (['assets-finance-book-modal', 'assets-depreciation-modal', 'assets-shift-factor-modal', 'assets-shift-allocation-modal', 'assets-value-adjustment-modal'] as $marker) {
    assets_depreciation_assert(str_contains($modal, $marker), 'Task 3 modal/confirmation marker is missing: ' . $marker . '.');
}
$depreciationList = file_get_contents($root . '/company/admin/modules/assets-maintenance/views/depreciation-list.php');
assets_depreciation_assert(str_contains($depreciationList, 'data-confirm-submit') && str_contains($depreciationList, 'data-confirm-submit-action'), 'Task 3 lifecycle actions do not use sibling confirmation.');
assets_depreciation_assert(str_contains($workspace, 'data-grid-span="12"') && str_contains($workspace, 'data-grid-span="8"'), 'Task 3 did not preserve the 12/8 shell.');

$runtime = file_get_contents($root . '/company/admin/modules/assets-maintenance/depreciation.php');
$dependencyRuntime = file_get_contents($root . '/company/admin/modules/assets-maintenance/assets.php');
assets_depreciation_assert(str_contains($dependencyRuntime, 'yovel_admin_finance_asset_posting_request'), 'Task 3 default gateway does not call the Finance owner command.');
assets_depreciation_assert(str_contains($runtime, 'FOR UPDATE'), 'Task 3 does not lock mutable workflow records.');
foreach (['project_company_inventory_', 'project_company_hr_', 'project_company_finance_', 'project_company_accounting_', 'project_company_general_ledger_'] as $foreignPrefix) {
    assets_depreciation_assert(!str_contains($runtime, $foreignPrefix), 'Task 3 contains a foreign owner-table reference: ' . $foreignPrefix . '.');
}
assets_depreciation_assert(!str_contains($runtime, '$_POST') && !str_contains($runtime, '$_REQUEST'), 'Task 3 service reads request globals directly.');

echo "Assets / Maintenance Task 3 depreciation checks passed.\n";
