<?php
declare(strict_types=1);

function yovel_admin_ph_vat_builtin_codes(): array
{
    return [
        'VAT12' => ['tax_code' => 'VAT12', 'tax_name' => 'VAT 12%', 'tax_kind' => 'VAT', 'bir_classification' => 'VATABLE', 'rate' => '12', 'price_inclusive' => 0, 'creditable' => 1, 'withholding_rate' => '0'],
        'ZERO_RATED' => ['tax_code' => 'ZERO_RATED', 'tax_name' => 'VAT Zero-Rated', 'tax_kind' => 'VAT', 'bir_classification' => 'ZERO_RATED', 'rate' => '0', 'price_inclusive' => 0, 'creditable' => 1, 'withholding_rate' => '0'],
        'EXEMPT' => ['tax_code' => 'EXEMPT', 'tax_name' => 'VAT Exempt', 'tax_kind' => 'VAT', 'bir_classification' => 'EXEMPT', 'rate' => '0', 'price_inclusive' => 0, 'creditable' => 0, 'withholding_rate' => '0'],
        'OUT_OF_SCOPE' => ['tax_code' => 'OUT_OF_SCOPE', 'tax_name' => 'Out of Scope', 'tax_kind' => 'OTHER', 'bir_classification' => 'OUT_OF_SCOPE', 'rate' => '0', 'price_inclusive' => 0, 'creditable' => 0, 'withholding_rate' => '0'],
        'GOV_VAT12' => ['tax_code' => 'GOV_VAT12', 'tax_name' => 'Government VAT 12%', 'tax_kind' => 'VAT', 'bir_classification' => 'GOVERNMENT', 'rate' => '12', 'price_inclusive' => 0, 'creditable' => 1, 'withholding_rate' => '5'],
        'INPUT_SERVICE_VAT12' => ['tax_code' => 'INPUT_SERVICE_VAT12', 'tax_name' => 'Input VAT on Services 12%', 'tax_kind' => 'VAT', 'bir_classification' => 'INPUT_SERVICE', 'rate' => '12', 'price_inclusive' => 0, 'creditable' => 1, 'withholding_rate' => '0'],
        'INPUT_GOODS_VAT12' => ['tax_code' => 'INPUT_GOODS_VAT12', 'tax_name' => 'Input VAT on Goods 12%', 'tax_kind' => 'VAT', 'bir_classification' => 'INPUT_GOODS', 'rate' => '12', 'price_inclusive' => 0, 'creditable' => 1, 'withholding_rate' => '0'],
        'INPUT_CAPITAL_VAT12' => ['tax_code' => 'INPUT_CAPITAL_VAT12', 'tax_name' => 'Input VAT on Capital Goods 12%', 'tax_kind' => 'VAT', 'bir_classification' => 'INPUT_CAPITAL', 'rate' => '12', 'price_inclusive' => 0, 'creditable' => 1, 'withholding_rate' => '0'],
    ];
}

function yovel_admin_ph_vat_tax_meta(string|array $taxCode): array
{
    $meta = is_array($taxCode) ? $taxCode : (yovel_admin_ph_vat_builtin_codes()[strtoupper(trim($taxCode))] ?? null);
    if (!is_array($meta)) {
        throw new InvalidArgumentException('Philippine VAT Tax Code is not recognized.');
    }
    $code = yovel_admin_code((string) ($meta['tax_code'] ?? ''));
    $name = trim((string) ($meta['tax_name'] ?? $code));
    $kind = yovel_admin_status((string) ($meta['tax_kind'] ?? 'VAT'), ['VAT', 'WITHHOLDING', 'OTHER'], 'VAT');
    $classification = yovel_admin_status((string) ($meta['bir_classification'] ?? 'VATABLE'), ['VATABLE', 'ZERO_RATED', 'EXEMPT', 'OUT_OF_SCOPE', 'GOVERNMENT', 'INPUT_SERVICE', 'INPUT_CAPITAL', 'INPUT_GOODS'], 'VATABLE');
    $rate = yovel_admin_finance_money($meta['rate'] ?? '0');
    $withholdingRate = yovel_admin_finance_money($meta['withholding_rate'] ?? ($classification === 'GOVERNMENT' ? '5' : '0'));
    if ($code === '' || $name === '' || bccomp($rate, '0', 6) === -1 || bccomp($rate, '100', 6) === 1 || bccomp($withholdingRate, '0', 6) === -1 || bccomp($withholdingRate, '100', 6) === 1) {
        throw new InvalidArgumentException('Philippine VAT Tax Code metadata is invalid.');
    }
    return [
        'tax_code_key' => (string) ($meta['tax_code_key'] ?? ''),
        'tax_code' => $code,
        'tax_name' => $name,
        'tax_kind' => $kind,
        'bir_classification' => $classification,
        'rate' => $rate,
        'price_inclusive' => !empty($meta['price_inclusive']) ? 1 : 0,
        'creditable' => !empty($meta['creditable']) ? 1 : 0,
        'withholding_rate' => $withholdingRate,
        'effective_from' => (string) ($meta['effective_from'] ?? ''),
        'effective_to' => (string) ($meta['effective_to'] ?? ''),
    ];
}

function yovel_admin_ph_vat_effective_code(array $company, string $taxCode, string $postingDate): array
{
    yovel_admin_finance_core_schema();
    $postingDate = yovel_admin_optional_date($postingDate, 'Posting date');
    if ($postingDate === '') {
        throw new InvalidArgumentException('Posting date is required to resolve a Tax Code.');
    }
    $row = bx_db()->GetRow("SELECT * FROM project_company_finance_tax_code WHERE company_key_hash = ? AND tax_code = ? AND status = 'ACTIVE' AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?) ORDER BY effective_from DESC, x_id DESC LIMIT 1", [(string) ($company['company_key_hash'] ?? ''), yovel_admin_code($taxCode), $postingDate, $postingDate]);
    if (!is_array($row) || $row === []) {
        $builtIn = yovel_admin_ph_vat_builtin_codes()[strtoupper(trim($taxCode))] ?? null;
        if (!is_array($builtIn)) {
            throw new InvalidArgumentException('No active Tax Code applies on the posting date.');
        }
        return yovel_admin_ph_vat_tax_meta($builtIn);
    }
    return yovel_admin_ph_vat_tax_meta($row);
}

function yovel_admin_ph_vat_calculate_line(mixed $unitAmount, mixed $quantity, string|array $taxCode, bool $priceInclusive = false, bool $isReturn = false, mixed $discountAmount = '0'): array
{
    $unitAmount = yovel_admin_finance_money($unitAmount);
    $quantity = yovel_admin_finance_money($quantity);
    $discount = yovel_admin_finance_money($discountAmount);
    if (bccomp($unitAmount, '0', 6) === -1 || bccomp($quantity, '0', 6) === -1 || bccomp($discount, '0', 6) === -1) {
        throw new InvalidArgumentException('VAT line amount, quantity, and discount cannot be negative. Use the return indicator for reversals.');
    }
    $gross = yovel_admin_finance_decimal_round(bcmul($unitAmount, $quantity, 12), 6);
    if (bccomp($discount, $gross, 6) === 1) {
        throw new InvalidArgumentException('VAT line discount cannot exceed the gross amount.');
    }
    $afterDiscount = yovel_admin_finance_decimal_round(bcsub($gross, $discount, 12), 6);
    $meta = yovel_admin_ph_vat_tax_meta($taxCode);
    $classification = $meta['bir_classification'];
    $taxApplies = in_array($classification, ['VATABLE', 'GOVERNMENT', 'INPUT_SERVICE', 'INPUT_CAPITAL', 'INPUT_GOODS'], true) && bccomp($meta['rate'], '0', 6) === 1;
    $inclusive = $priceInclusive || (bool) $meta['price_inclusive'];
    $taxableBase = '0.000000';
    $vat = '0.000000';
    $lineTotal = $afterDiscount;
    if ($taxApplies) {
        if ($inclusive) {
            $divisor = bcadd('1', bcdiv($meta['rate'], '100', 12), 12);
            $taxableBase = yovel_admin_finance_decimal_round(bcdiv($afterDiscount, $divisor, 12), 6);
            $vat = yovel_admin_finance_decimal_round(bcsub($afterDiscount, $taxableBase, 12), 6);
        } else {
            $taxableBase = $afterDiscount;
            $vat = yovel_admin_finance_decimal_round(bcmul($taxableBase, bcdiv($meta['rate'], '100', 12), 12), 6);
            $lineTotal = yovel_admin_finance_decimal_round(bcadd($taxableBase, $vat, 12), 6);
        }
    } elseif ($classification === 'ZERO_RATED') {
        $taxableBase = $afterDiscount;
    }
    $zeroRated = $classification === 'ZERO_RATED' ? $afterDiscount : '0.000000';
    $exempt = $classification === 'EXEMPT' ? $afterDiscount : '0.000000';
    $outOfScope = $classification === 'OUT_OF_SCOPE' ? $afterDiscount : '0.000000';
    $governmentSales = $classification === 'GOVERNMENT' ? $taxableBase : '0.000000';
    $withheld = $classification === 'GOVERNMENT'
        ? yovel_admin_finance_decimal_round(bcmul($lineTotal, bcdiv($meta['withholding_rate'], '100', 12), 12), 6)
        : '0.000000';
    $isInput = str_starts_with($classification, 'INPUT_');
    $creditableInput = $isInput && (bool) $meta['creditable'] ? $vat : '0.000000';
    $nonCreditableInput = $isInput && !(bool) $meta['creditable'] ? $vat : '0.000000';
    $sign = $isReturn ? '-1' : '1';
    $signed = static fn (string $amount): string => yovel_admin_finance_decimal_round(bcmul($amount, $sign, 6), 6);
    return [
        'unit_amount' => $unitAmount,
        'quantity' => $quantity,
        'gross_amount' => $signed($gross),
        'discount_amount' => $signed($discount),
        'net_amount' => $signed($afterDiscount),
        'taxable_base' => $signed($taxableBase),
        'vat_amount' => $signed($vat),
        'line_total' => $signed($lineTotal),
        'zero_rated_sales' => $signed($zeroRated),
        'exempt_sales' => $signed($exempt),
        'out_of_scope_amount' => $signed($outOfScope),
        'government_sales' => $signed($governmentSales),
        'creditable_input_vat' => $signed($creditableInput),
        'non_creditable_input_vat' => $signed($nonCreditableInput),
        'creditable_vat_withheld' => $signed($withheld),
        'net_collectible' => $signed(yovel_admin_finance_decimal_round(bcsub($lineTotal, $withheld, 12), 6)),
        'tax_code' => $meta['tax_code'],
        'bir_classification' => $classification,
        'price_inclusive' => $inclusive,
        'is_return' => $isReturn,
        'tax_snapshot' => $meta,
    ];
}

function yovel_admin_ph_vat_calculate_document(array $inputLines, bool $isReturn = false): array
{
    if ($inputLines === [] || count($inputLines) > 1000) {
        throw new InvalidArgumentException('VAT document must contain between 1 and 1000 lines.');
    }
    $totals = [
        'net_amount' => '0.000000',
        'taxable_sales' => '0.000000',
        'government_sales' => '0.000000',
        'zero_rated_sales' => '0.000000',
        'exempt_sales' => '0.000000',
        'out_of_scope_amount' => '0.000000',
        'vat_amount' => '0.000000',
        'creditable_input_vat' => '0.000000',
        'non_creditable_input_vat' => '0.000000',
        'creditable_vat_withheld' => '0.000000',
        'grand_total' => '0.000000',
        'net_collectible' => '0.000000',
    ];
    $lines = [];
    foreach (array_values($inputLines) as $index => $input) {
        if (!is_array($input)) {
            throw new InvalidArgumentException('VAT document lines must be structured rows.');
        }
        $line = yovel_admin_ph_vat_calculate_line(
            $input['unit_amount'] ?? $input['rate'] ?? '0',
            $input['quantity'] ?? '1',
            $input['tax_meta'] ?? $input['tax_code'] ?? 'OUT_OF_SCOPE',
            !empty($input['price_inclusive']),
            $isReturn || !empty($input['is_return']),
            $input['discount_amount'] ?? '0'
        );
        $line['line_no'] = $index + 1;
        $line['description'] = substr(trim((string) ($input['description'] ?? '')), 0, 500);
        $classification = (string) $line['bir_classification'];
        $totals['net_amount'] = bcadd($totals['net_amount'], $line['net_amount'], 6);
        if ($classification === 'VATABLE') {
            $totals['taxable_sales'] = bcadd($totals['taxable_sales'], $line['taxable_base'], 6);
        }
        if ($classification === 'GOVERNMENT') {
            $totals['government_sales'] = bcadd($totals['government_sales'], $line['government_sales'], 6);
        }
        foreach (['zero_rated_sales', 'exempt_sales', 'out_of_scope_amount', 'vat_amount', 'creditable_input_vat', 'non_creditable_input_vat', 'creditable_vat_withheld'] as $field) {
            $totals[$field] = bcadd($totals[$field], $line[$field], 6);
        }
        $totals['grand_total'] = bcadd($totals['grand_total'], $line['line_total'], 6);
        $totals['net_collectible'] = bcadd($totals['net_collectible'], $line['net_collectible'], 6);
        $lines[] = $line;
    }
    foreach ($totals as $field => $value) {
        $totals[$field] = yovel_admin_finance_money($value);
    }
    $totals['lines'] = $lines;
    return $totals;
}

function yovel_admin_ph_vat_quarter(string $date): array
{
    $date = yovel_admin_optional_date($date, 'VAT date');
    if ($date === '') {
        throw new InvalidArgumentException('VAT date is required.');
    }
    $year = (int) substr($date, 0, 4);
    $month = (int) substr($date, 5, 2);
    $quarter = intdiv($month - 1, 3) + 1;
    $startMonth = (($quarter - 1) * 3) + 1;
    $dateFrom = sprintf('%04d-%02d-01', $year, $startMonth);
    $dateTo = (new DateTimeImmutable($dateFrom))->modify('+3 months -1 day')->format('Y-m-d');
    return ['year' => $year, 'quarter' => $quarter, 'date_from' => $dateFrom, 'date_to' => $dateTo];
}

function yovel_admin_ph_vat_export_rows(array $documents): array
{
    $rows = [];
    foreach ($documents as $document) {
        if (!is_array($document) || strtoupper((string) ($document['document_status'] ?? '')) !== 'SUBMITTED') {
            continue;
        }
        $rows[] = [
            'document_type' => strtoupper(substr(trim((string) ($document['document_type'] ?? '')), 0, 20)),
            'invoice_no' => substr(trim((string) ($document['invoice_no'] ?? '')), 0, 120),
            'posting_date' => yovel_admin_optional_date((string) ($document['posting_date'] ?? ''), 'VAT export posting date'),
            'party_tin' => substr(trim((string) ($document['party_tin'] ?? '')), 0, 30),
            'party_name' => substr(trim((string) ($document['party_name'] ?? '')), 0, 180),
            'taxable_sales' => yovel_admin_finance_money($document['taxable_sales'] ?? '0'),
            'government_sales' => yovel_admin_finance_money($document['government_sales'] ?? '0'),
            'zero_rated_sales' => yovel_admin_finance_money($document['zero_rated_sales'] ?? '0'),
            'exempt_sales' => yovel_admin_finance_money($document['exempt_sales'] ?? '0'),
            'output_or_input_vat' => yovel_admin_finance_money($document['vat_amount'] ?? '0'),
            'creditable_vat_withheld' => yovel_admin_finance_money($document['creditable_vat_withheld'] ?? '0'),
            'grand_total' => yovel_admin_finance_money($document['grand_total'] ?? '0'),
        ];
    }
    return $rows;
}
