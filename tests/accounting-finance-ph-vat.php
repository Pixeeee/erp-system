<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/core.php';
require_once $root . '/company/admin/modules/accounting-finance/tax.php';

function vat_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$exclusive = yovel_admin_ph_vat_calculate_line('1120.00', '1', 'VAT12', false, false);
vat_assert($exclusive['taxable_base'] === '1120.000000', 'Exclusive VAT base failed.');
vat_assert($exclusive['vat_amount'] === '134.400000', 'Exclusive VAT amount failed.');
vat_assert($exclusive['line_total'] === '1254.400000', 'Exclusive VAT total failed.');

$inclusive = yovel_admin_ph_vat_calculate_line('1120.00', '1', 'VAT12', true, false);
vat_assert($inclusive['taxable_base'] === '1000.000000', 'Inclusive VAT base failed.');
vat_assert($inclusive['vat_amount'] === '120.000000', 'Inclusive VAT amount failed.');
vat_assert($inclusive['line_total'] === '1120.000000', 'Inclusive VAT total failed.');

$zeroRated = yovel_admin_ph_vat_calculate_line('500', '2', 'ZERO_RATED', false, false);
vat_assert($zeroRated['taxable_base'] === '1000.000000' && $zeroRated['vat_amount'] === '0.000000', 'Zero-rated sale failed.');
vat_assert($zeroRated['bir_classification'] === 'ZERO_RATED', 'Zero-rated classification failed.');

$exempt = yovel_admin_ph_vat_calculate_line('800', '1', 'EXEMPT', false, false);
vat_assert($exempt['exempt_sales'] === '800.000000' && $exempt['taxable_base'] === '0.000000', 'Exempt sale failed.');

$outOfScope = yovel_admin_ph_vat_calculate_line('250', '1', 'OUT_OF_SCOPE', false, false);
vat_assert($outOfScope['out_of_scope_amount'] === '250.000000' && $outOfScope['vat_amount'] === '0.000000', 'Out-of-scope line failed.');

$returned = yovel_admin_ph_vat_calculate_line('1120', '1', 'VAT12', true, true);
vat_assert($returned['taxable_base'] === '-1000.000000' && $returned['vat_amount'] === '-120.000000' && $returned['line_total'] === '-1120.000000', 'VAT return direction failed.');

$government = yovel_admin_ph_vat_calculate_line('1120', '1', [
    'tax_code' => 'GOV_VAT12',
    'tax_name' => 'Government VAT 12%',
    'tax_kind' => 'VAT',
    'bir_classification' => 'GOVERNMENT',
    'rate' => '12',
    'price_inclusive' => 1,
    'creditable' => 1,
    'withholding_rate' => '5',
], true, false);
vat_assert($government['taxable_base'] === '1000.000000' && $government['vat_amount'] === '120.000000', 'Government VAT calculation failed.');
vat_assert($government['creditable_vat_withheld'] === '56.000000', 'Government creditable VAT withholding failed.');

$nonCreditable = yovel_admin_ph_vat_calculate_line('1000', '1', [
    'tax_code' => 'INPUT_NONCREDIT',
    'tax_name' => 'Non-creditable input VAT',
    'tax_kind' => 'VAT',
    'bir_classification' => 'INPUT_SERVICE',
    'rate' => '12',
    'price_inclusive' => 0,
    'creditable' => 0,
], false, false);
vat_assert($nonCreditable['non_creditable_input_vat'] === '120.000000', 'Non-creditable input VAT failed.');
vat_assert($nonCreditable['creditable_input_vat'] === '0.000000', 'Non-creditable input VAT leaked to the creditable bucket.');

$document = yovel_admin_ph_vat_calculate_document([
    ['description' => 'Taxable', 'unit_amount' => '1000', 'quantity' => '1', 'tax_code' => 'VAT12'],
    ['description' => 'Zero', 'unit_amount' => '500', 'quantity' => '1', 'tax_code' => 'ZERO_RATED'],
    ['description' => 'Exempt', 'unit_amount' => '250', 'quantity' => '1', 'tax_code' => 'EXEMPT'],
], false);
vat_assert($document['taxable_sales'] === '1000.000000', 'Document taxable sales failed.');
vat_assert($document['zero_rated_sales'] === '500.000000', 'Document zero-rated sales failed.');
vat_assert($document['exempt_sales'] === '250.000000', 'Document exempt sales failed.');
vat_assert($document['vat_amount'] === '120.000000' && $document['grand_total'] === '1870.000000', 'Document VAT totals failed.');
vat_assert(count($document['lines']) === 3 && $document['lines'][0]['tax_snapshot']['rate'] === '12.000000', 'Submitted-tax snapshot metadata is incomplete.');

$quarter = yovel_admin_ph_vat_quarter('2026-05-15');
vat_assert($quarter === ['year' => 2026, 'quarter' => 2, 'date_from' => '2026-04-01', 'date_to' => '2026-06-30'], 'VAT quarter boundaries failed.');

$export = yovel_admin_ph_vat_export_rows([[
    'invoice_no' => 'SI-2026-00001',
    'posting_date' => '2026-05-15',
    'party_tin' => '123-456-789-00000',
    'party_name' => 'VAT Customer',
    'document_status' => 'SUBMITTED',
    'document_type' => 'SALES',
    'taxable_sales' => '1000.000000',
    'zero_rated_sales' => '0.000000',
    'exempt_sales' => '0.000000',
    'vat_amount' => '120.000000',
    'grand_total' => '1120.000000',
]]);
vat_assert(count($export) === 1 && $export[0]['invoice_no'] === 'SI-2026-00001', 'VAT export row failed.');

echo "Philippine VAT checks passed.\n";
