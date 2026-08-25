<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/core.php';
require_once $root . '/company/admin/modules/accounting-finance/tax.php';
require_once $root . '/company/admin/modules/accounting-finance/ledger.php';
require_once $root . '/company/admin/modules/accounting-finance/invoices.php';
require_once $root . '/company/admin/modules/accounting-finance/payments.php';

function finance_wp03_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function finance_wp03_error(callable $operation, string $needle): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        finance_wp03_assert(str_contains(strtolower($error->getMessage()), strtolower($needle)), 'Unexpected AF-WP03 error: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected AF-WP03 error containing: ' . $needle);
}

foreach ([
    'yovel_admin_finance_invoice_dependency_gateway',
    'yovel_admin_finance_invoice_payment_schedule',
    'yovel_admin_finance_invoice_advances',
    'yovel_admin_finance_calculate_purchase_document',
    'yovel_admin_amend_finance_invoice',
    'yovel_admin_finance_invoice_snapshot',
    'yovel_admin_finance_save_payment_terms_template',
    'yovel_admin_finance_save_dunning_type',
    'yovel_admin_finance_create_dunning',
    'yovel_admin_finance_process_overdue_invoices',
    'yovel_admin_finance_process_statement_of_accounts',
    'yovel_admin_finance_discount_invoice',
    'yovel_admin_finance_create_opening_invoices',
] as $function) {
    finance_wp03_assert(function_exists($function), 'AF-WP03 owner function is missing: ' . $function);
}

$gateway = yovel_admin_finance_invoice_dependency_gateway([
    'sales-crm.customer-reference.v1' => static fn (array $company, string $key): array => [
        'customer_key' => $key,
        'customer_code' => 'CUST-001',
        'customer_name' => 'Customer One',
        'customer_status' => 'ACTIVE',
        'company_key_hash' => $company['company_key_hash'],
    ],
]);
finance_wp03_assert(($gateway['sales-crm.customer-reference.v1']['available'] ?? false) === true, 'Customer owner override was not accepted.');
finance_wp03_error(static fn (): array => yovel_admin_finance_invoice_dependency_gateway(['foreign.contract.v1' => static fn (): array => []]), 'allow-listed');

$schedule = yovel_admin_finance_invoice_payment_schedule('2026-08-25', '1120', [
    ['due_date' => '2026-09-25', 'invoice_portion' => '50', 'description' => 'First half'],
    ['due_date' => '2026-10-25', 'invoice_portion' => '50', 'description' => 'Final half'],
]);
finance_wp03_assert(count($schedule) === 2, 'Payment Schedule did not retain both terms.');
finance_wp03_assert($schedule[0]['due_amount'] === '560.000000' && $schedule[1]['due_amount'] === '560.000000', 'Payment Schedule amount calculation is wrong.');
finance_wp03_error(static fn (): array => yovel_admin_finance_invoice_payment_schedule('2026-08-25', '100', [['due_date' => '2026-08-24', 'invoice_portion' => '100']]), 'posting date');
finance_wp03_error(static fn (): array => yovel_admin_finance_invoice_payment_schedule('2026-08-25', '100', [['due_date' => '2026-09-25', 'invoice_portion' => '80']]), '100');

$advances = yovel_admin_finance_invoice_advances('1120', [
    ['payment_entry_key' => bx_uuid(), 'allocated_amount' => '120'],
    ['journal_entry_key' => bx_uuid(), 'allocated_amount' => '80'],
]);
finance_wp03_assert($advances['total'] === '200.000000' && count($advances['rows']) === 2, 'Invoice advances were not normalized.');
finance_wp03_error(static fn (): array => yovel_admin_finance_invoice_advances('100', [['payment_entry_key' => bx_uuid(), 'allocated_amount' => '101']]), 'exceed');

$invoiceSource = (string) file_get_contents($root . '/company/admin/modules/accounting-finance/invoices.php');
$paymentSource = (string) file_get_contents($root . '/company/admin/modules/accounting-finance/payments.php');
$invoiceView = (string) file_get_contents($root . '/company/admin/modules/accounting-finance/views/invoices.php');
$paymentView = (string) file_get_contents($root . '/company/admin/modules/accounting-finance/views/payment-entries.php');
foreach ([
    'project_company_finance_payment_terms_template',
    'project_company_finance_invoice_advance',
    'project_company_finance_dunning',
    'project_company_finance_statement_process',
    'project_company_finance_invoice_discounting',
    'immutable_snapshot_sha256',
    'amended_from_invoice_key',
    'base_grand_total',
] as $marker) {
    finance_wp03_assert(str_contains($invoiceSource, $marker), 'AF-WP03 invoice persistence marker is missing: ' . $marker);
}
foreach (['write_off_amount', 'exchange_gain_loss', 'base_paid_amount', 'FOR UPDATE'] as $marker) {
    finance_wp03_assert(str_contains($paymentSource, $marker), 'AF-WP03 payment persistence marker is missing: ' . $marker);
}
foreach (['data-confirm-submit', 'finance-invoice-tools-modal', 'invoice_operation', 'payment_terms_json', 'advances_json'] as $marker) {
    finance_wp03_assert(str_contains($invoiceView, $marker), 'AF-WP03 invoice modal marker is missing: ' . $marker);
}
foreach (['data-confirm-submit', 'write_off_amount', 'exchange_gain_loss_account_key'] as $marker) {
    finance_wp03_assert(str_contains($paymentView, $marker), 'AF-WP03 payment modal marker is missing: ' . $marker);
}
$purchaseCalculatorSource = preg_match('/function yovel_admin_finance_calculate_purchase_document\b.*?^}/ms', $invoiceSource, $purchaseCalculatorMatch) === 1
    ? $purchaseCalculatorMatch[0]
    : '';
finance_wp03_assert($purchaseCalculatorSource !== '', 'Purchase calculation source boundary was not found.');
foreach (['yovel_admin_ph_vat_effective_code', '_schema(', 'BeginTrans', 'yovel_admin_db_execute', 'bx_audit'] as $writeMarker) {
    finance_wp03_assert(!str_contains($purchaseCalculatorSource, $writeMarker), 'Purchase calculation invokes a write-capable boundary: ' . $writeMarker);
}

$providers = $GLOBALS['yovel_admin_compliance_dependency_providers'] ?? [];
finance_wp03_assert(is_callable($providers['accounting-finance.invoice-snapshot.v1'] ?? null), 'Finance did not publish accounting-finance.invoice-snapshot.v1.');
finance_wp03_assert(yovel_admin_finance_form_modal_id('save_finance_invoice', ['invoice_operation'=>'dunning']) === 'finance-invoice-tools-modal', 'AF-WP03 validation does not rehydrate the tools modal.');
finance_wp03_assert(yovel_admin_finance_form_modal_id('save_finance_invoice', ['invoice_operation'=>'invoice']) === 'finance-invoice-modal', 'Invoice Draft validation no longer rehydrates the invoice modal.');

yovel_admin_finance_invoice_schema();
yovel_admin_finance_payment_schema();
$db = bx_db();
foreach ([
    'project_company_finance_payment_terms_template',
    'project_company_finance_payment_terms_template_detail',
    'project_company_finance_invoice_advance',
    'project_company_finance_dunning_type',
    'project_company_finance_dunning',
    'project_company_finance_statement_process',
    'project_company_finance_statement_customer',
    'project_company_finance_invoice_discounting',
] as $table) {
    finance_wp03_assert((int) $db->GetOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [$table]) === 1, 'AF-WP03 table was not created: ' . $table);
}

$fixture = $db->GetRow("SELECT company_key,company_key_hash,company_name FROM project_company WHERE company_status='ACTIVE' ORDER BY x_id LIMIT 1");
finance_wp03_assert(is_array($fixture) && $fixture !== [], 'An active company fixture is required.');
$company = ['company_key' => (string) $fixture['company_key'], 'company_key_hash' => (string) $fixture['company_key_hash'], 'company_name' => (string) $fixture['company_name']];
$purchasePayload = [
    'document_type' => 'SUPPLIER_QUOTATION',
    'transaction_date' => '2026-08-25',
    'currency' => 'USD',
    'conversion_rate' => '56.25',
    'additional_discount_amount' => '20',
    'items' => [[
        'line_no' => 1,
        'inventory_item_key' => bx_uuid(),
        'uom_key' => bx_uuid(),
        'quantity' => '2',
        'rate' => '100',
        'discount_percentage' => '10',
        'tax_code' => 'INPUT_GOODS_VAT12',
    ]],
];
$invoiceCountBefore = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_finance_invoice WHERE company_key_hash=?', [$company['company_key_hash']]);
$auditCountBefore = (int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module='project_company_finance_invoice' AND new_values LIKE ?", ['%' . $company['company_key_hash'] . '%']);
$purchaseCalculation = yovel_admin_finance_calculate_purchase_document($company, $purchasePayload);
$repeatCalculation = yovel_admin_finance_calculate_purchase_document($company, $purchasePayload);
finance_wp03_assert($purchaseCalculation === $repeatCalculation, 'Purchase calculation is not deterministic.');
finance_wp03_assert(($purchaseCalculation['contract'] ?? '') === 'finance.purchase-document-calculation.v1', 'Purchase calculation contract identity is wrong.');
finance_wp03_assert(($purchaseCalculation['ok'] ?? false) === true && ($purchaseCalculation['errors'] ?? null) === [], 'Purchase calculation did not return a successful error envelope.');
finance_wp03_assert(hash_equals($company['company_key_hash'], (string) ($purchaseCalculation['company_key_hash'] ?? '')), 'Purchase calculation changed company scope.');
finance_wp03_assert(($purchaseCalculation['currency'] ?? '') === 'USD' && ($purchaseCalculation['base_currency'] ?? '') === 'PHP' && ($purchaseCalculation['conversion_rate'] ?? '') === '56.25000000', 'Purchase calculation currency normalization is wrong.');
finance_wp03_assert(($purchaseCalculation['items'][0]['net_amount'] ?? '') === '160.000000', 'Purchase discounts were not applied before VAT.');
finance_wp03_assert(($purchaseCalculation['items'][0]['tax_amount'] ?? '') === '19.200000' && ($purchaseCalculation['items'][0]['gross_amount'] ?? '') === '179.200000', 'Purchase VAT line calculation is wrong.');
finance_wp03_assert(($purchaseCalculation['net_total'] ?? '') === '160.000000' && ($purchaseCalculation['tax_total'] ?? '') === '19.200000' && ($purchaseCalculation['grand_total'] ?? '') === '179.200000', 'Purchase calculation totals are wrong.');
finance_wp03_assert(($purchaseCalculation['base_net_total'] ?? '') === '9000.000000' && ($purchaseCalculation['base_tax_total'] ?? '') === '1080.000000' && ($purchaseCalculation['base_grand_total'] ?? '') === '10080.000000', 'Purchase base-currency totals are wrong.');
$checksum = (string) ($purchaseCalculation['calculation_checksum'] ?? '');
finance_wp03_assert(preg_match('/^[a-f0-9]{64}$/', $checksum) === 1, 'Purchase calculation checksum is not lowercase SHA-256.');
$checksumInput = $purchaseCalculation;
unset($checksumInput['calculation_checksum']);
finance_wp03_assert(hash_equals(hash('sha256', yovel_admin_finance_canonical_json($checksumInput)), $checksum), 'Purchase calculation checksum is not canonical.');
finance_wp03_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_finance_invoice WHERE company_key_hash=?', [$company['company_key_hash']]) === $invoiceCountBefore, 'Purchase calculation persisted a Finance Invoice.');
finance_wp03_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module='project_company_finance_invoice' AND new_values LIKE ?", ['%' . $company['company_key_hash'] . '%']) === $auditCountBefore, 'Purchase calculation wrote a Finance Invoice audit row.');
finance_wp03_error(static fn (): array => yovel_admin_finance_calculate_purchase_document($company, $purchasePayload + ['company_key_hash' => str_repeat('0', 64)]), 'company scope');
$missing = yovel_admin_finance_invoice_snapshot($company, bx_uuid());
finance_wp03_assert(($missing['ok'] ?? true) === false && array_key_exists('record', $missing) && $missing['record'] === null && ($missing['errors'] ?? []) === ['SUBMITTED_INVOICE_NOT_AVAILABLE'], 'Snapshot provider did not fail closed for an unavailable document.');
finance_wp03_assert(hash_equals($company['company_key_hash'], (string) ($missing['company_key_hash'] ?? '')), 'Snapshot provider changed company scope.');

echo "Accounting/Finance AF-WP03 contract checks passed.\n";
