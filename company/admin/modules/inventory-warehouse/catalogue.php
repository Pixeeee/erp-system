<?php
declare(strict_types=1);

function yovel_admin_inventory_catalogue_text(array $input, string $key, int $max, bool $required = false): string
{
    $value = trim((string) ($input[$key] ?? ''));
    if (($required && $value === '') || strlen($value) > $max) {
        throw new InvalidArgumentException('Inventory ' . str_replace('_', ' ', $key) . ' is invalid.');
    }
    return $value;
}

function yovel_admin_inventory_catalogue_code(string $value, int $max = 80): string
{
    $value = strtoupper(trim($value));
    if ($value === '' || strlen($value) > $max || preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $value) !== 1) {
        throw new InvalidArgumentException('Inventory code format is invalid.');
    }
    return $value;
}

function yovel_admin_inventory_catalogue_rows(array $input, string $key): array
{
    $rows = $input[$key] ?? [];
    if (is_string($rows) && trim($rows) !== '') {
        $rows = json_decode($rows, true, 512, JSON_THROW_ON_ERROR);
    }
    if (!is_array($rows) || count($rows) > 100) {
        throw new InvalidArgumentException('Inventory ' . str_replace('_', ' ', $key) . ' rows are invalid.');
    }
    return array_values(array_filter($rows, 'is_array'));
}

function yovel_admin_inventory_catalogue_flag(mixed $value): int
{
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function yovel_admin_inventory_catalogue_date(mixed $value, bool $required = false): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        if ($required) {
            throw new InvalidArgumentException('Inventory date is required.');
        }
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Inventory date must use YYYY-MM-DD.');
    }
    return $value;
}

function yovel_admin_inventory_catalogue_fault(string $point): void
{
    $fault = $GLOBALS['yovel_admin_inventory_catalogue_fault'] ?? null;
    if (is_callable($fault)) {
        $fault($point);
    }
}

function yovel_admin_inventory_barcode_mod10(string $digits): bool
{
    $check = (int) substr($digits, -1);
    $body = substr($digits, 0, -1);
    $sum = 0;
    $distance = 1;
    for ($index = strlen($body) - 1; $index >= 0; $index--, $distance++) {
        $sum += (int) $body[$index] * ($distance % 2 === 1 ? 3 : 1);
    }
    return ((10 - ($sum % 10)) % 10) === $check;
}

function yovel_admin_inventory_validate_barcode(string $barcode, string $type): array
{
    $barcode = strtoupper(trim((string) preg_replace('/[\s-]+/', '', $barcode)));
    $type = strtoupper(trim($type));
    $valid = match ($type) {
        'EAN13', 'ISBN13' => preg_match('/^\d{13}$/', $barcode) === 1 && yovel_admin_inventory_barcode_mod10($barcode),
        'EAN8' => preg_match('/^\d{8}$/', $barcode) === 1 && yovel_admin_inventory_barcode_mod10($barcode),
        'UPCA' => preg_match('/^\d{12}$/', $barcode) === 1 && yovel_admin_inventory_barcode_mod10($barcode),
        'ISBN10' => preg_match('/^\d{9}[\dX]$/', $barcode) === 1 && array_sum(array_map(
            static fn (int $index): int => ($barcode[$index] === 'X' ? 10 : (int) $barcode[$index]) * (10 - $index),
            range(0, 9)
        )) % 11 === 0,
        'UPCE' => yovel_admin_inventory_validate_upce($barcode),
        'CODE128' => $barcode !== '' && strlen($barcode) <= 80 && preg_match('/^[\x20-\x7E]+$/', $barcode) === 1,
        default => false,
    };
    if (!$valid) {
        throw new InvalidArgumentException('Inventory barcode format or checksum is invalid.');
    }
    return ['barcode' => $barcode, 'barcode_type' => $type];
}

function yovel_admin_inventory_validate_upce(string $barcode): bool
{
    if (preg_match('/^[01]\d{7}$/', $barcode) !== 1) {
        return false;
    }
    [$numberSystem, $d1, $d2, $d3, $d4, $d5, $d6, $check] = str_split($barcode);
    $body = match ($d6) {
        '0', '1', '2' => $numberSystem . $d1 . $d2 . $d6 . '0000' . $d3 . $d4 . $d5,
        '3' => $numberSystem . $d1 . $d2 . $d3 . '00000' . $d4 . $d5,
        '4' => $numberSystem . $d1 . $d2 . $d3 . $d4 . '00000' . $d5,
        default => $numberSystem . $d1 . $d2 . $d3 . $d4 . $d5 . '0000' . $d6,
    };
    return yovel_admin_inventory_barcode_mod10($body . $check);
}

function yovel_admin_inventory_normalize_item_input(array $input): array
{
    $itemKey = trim((string) ($input['item_key'] ?? ''));
    if ($itemKey !== '' && !yovel_admin_is_uuid($itemKey)) {
        throw new InvalidArgumentException('Inventory item key is invalid.');
    }
    $status = strtoupper(trim((string) ($input['item_status'] ?? 'ACTIVE')));
    $kind = strtoupper(trim((string) ($input['item_kind'] ?? 'STOCK')));
    if (!in_array($status, ['ACTIVE', 'DISABLED'], true) || !in_array($kind, ['STOCK', 'NON_STOCK'], true)) {
        throw new InvalidArgumentException('Inventory item status or kind is invalid.');
    }
    $stockUom = yovel_admin_inventory_catalogue_code((string) ($input['stock_uom_code'] ?? ''), 40);
    $serial = yovel_admin_inventory_catalogue_flag($input['has_serial_no'] ?? 0);
    $batch = yovel_admin_inventory_catalogue_flag($input['has_batch_no'] ?? 0);
    if ($kind === 'NON_STOCK' && ($serial === 1 || $batch === 1)) {
        throw new InvalidArgumentException('Non-stock items cannot use serial or batch tracking.');
    }
    $isTemplate = yovel_admin_inventory_catalogue_flag($input['is_template'] ?? 0);
    $variantOf = trim((string) ($input['variant_of_item_key'] ?? ''));
    if ($variantOf !== '' && (!yovel_admin_is_uuid($variantOf) || $isTemplate === 1)) {
        throw new InvalidArgumentException('Inventory variant template reference is invalid.');
    }

    $uoms = [];
    foreach (yovel_admin_inventory_catalogue_rows($input, 'uoms') as $row) {
        $code = yovel_admin_inventory_catalogue_code((string) ($row['uom_code'] ?? ''), 40);
        if (isset($uoms[$code])) {
            throw new InvalidArgumentException('Inventory UOM rows require unique codes.');
        }
        $factor = yovel_admin_inventory_decimal((string) ($row['conversion_factor'] ?? ''), 9);
        if (bccomp($factor, '0', 9) !== 1) {
            throw new InvalidArgumentException('Inventory UOM conversion factors must be greater than zero.');
        }
        $uoms[$code] = [
            'uom_code' => $code,
            'uom_name' => yovel_admin_inventory_catalogue_text($row, 'uom_name', 120, true),
            'uom_category' => yovel_admin_inventory_catalogue_code((string) ($row['category'] ?? 'GENERAL'), 80),
            'conversion_factor' => $factor,
            'is_stock_uom' => $code === $stockUom ? 1 : 0,
        ];
    }
    if (!isset($uoms[$stockUom]) || $uoms[$stockUom]['conversion_factor'] !== '1.000000000') {
        throw new InvalidArgumentException('The stock UOM must be present with conversion factor 1.');
    }

    $barcodes = [];
    foreach (yovel_admin_inventory_catalogue_rows($input, 'barcodes') as $row) {
        $validated = yovel_admin_inventory_validate_barcode((string) ($row['barcode'] ?? ''), (string) ($row['barcode_type'] ?? 'CODE128'));
        if (isset($barcodes[$validated['barcode']])) {
            throw new InvalidArgumentException('Inventory barcode rows must be unique.');
        }
        $barcodes[$validated['barcode']] = $validated;
    }

    $attributes = [];
    foreach (yovel_admin_inventory_catalogue_rows($input, 'variant_attributes') as $row) {
        $name = yovel_admin_inventory_catalogue_text($row, 'attribute_name', 120, true);
        if (isset($attributes[strtolower($name)])) {
            throw new InvalidArgumentException('Inventory variant attribute names must be unique.');
        }
        $attributes[strtolower($name)] = ['attribute_name' => $name, 'attribute_value' => yovel_admin_inventory_catalogue_text($row, 'attribute_value', 160, true)];
    }
    if (($isTemplate === 1 || $variantOf !== '') && $attributes === []) {
        throw new InvalidArgumentException('Inventory templates and variants require attributes.');
    }

    return [
        'item_key' => $itemKey,
        'item_code' => yovel_admin_inventory_catalogue_code((string) ($input['item_code'] ?? '')),
        'item_name' => yovel_admin_inventory_catalogue_text($input, 'item_name', 160, true),
        'item_description' => yovel_admin_inventory_catalogue_text(['item_description' => $input['description'] ?? $input['item_description'] ?? ''], 'item_description', 1000),
        'item_status' => $status,
        'item_kind' => $kind,
        'stock_uom_code' => $stockUom,
        'has_serial_no' => $serial,
        'has_batch_no' => $batch,
        'is_template' => $isTemplate,
        'variant_of_item_key' => $variantOf,
        'customs_tariff_code' => trim((string) ($input['customs_tariff_code'] ?? '')),
        'customs_tariff_description' => yovel_admin_inventory_catalogue_text($input, 'customs_tariff_description', 255),
        'uoms' => array_values($uoms),
        'barcodes' => array_values($barcodes),
        'variant_attributes' => array_values($attributes),
        'manufacturers' => yovel_admin_inventory_catalogue_rows($input, 'manufacturers'),
        'alternatives' => yovel_admin_inventory_catalogue_rows($input, 'alternatives'),
        'party_details' => yovel_admin_inventory_catalogue_rows($input, 'party_details'),
        'taxes' => yovel_admin_inventory_catalogue_rows($input, 'taxes'),
        'defaults' => yovel_admin_inventory_catalogue_rows($input, 'defaults'),
        'lead_times' => yovel_admin_inventory_catalogue_rows($input, 'lead_times'),
        'website_specs' => yovel_admin_inventory_catalogue_rows($input, 'website_specs'),
        'reorder_rows' => yovel_admin_inventory_catalogue_rows($input, 'reorder_rows'),
    ];
}

function yovel_admin_inventory_catalogue_child_config(string $name): array
{
    return match ($name) {
        'uoms' => ['project_company_inventory_item_uom', 'item_uom_key', ['uom_key', 'uom_code', 'conversion_factor', 'is_stock_uom']],
        'barcodes' => ['project_company_inventory_item_barcode', 'item_barcode_key', ['barcode', 'barcode_type']],
        'variant_attributes' => ['project_company_inventory_item_variant_attribute', 'item_variant_attribute_key', ['attribute_name', 'attribute_value']],
        'manufacturers' => ['project_company_inventory_item_manufacturer', 'item_manufacturer_key', ['manufacturer_key', 'manufacturer_part_no']],
        'alternatives' => ['project_company_inventory_item_alternative', 'item_alternative_key', ['alternative_item_key', 'two_way']],
        'party_details' => ['project_company_inventory_item_party_detail', 'item_party_detail_key', ['party_type', 'party_reference_key', 'party_item_code']],
        'taxes' => ['project_company_inventory_item_tax', 'item_tax_key', ['tax_template_reference', 'tax_rate']],
        'defaults' => ['project_company_inventory_item_default', 'item_default_key', ['scope_key', 'default_warehouse_reference', 'default_price_list_reference']],
        'lead_times' => ['project_company_inventory_item_lead_time', 'item_lead_time_key', ['lead_time_context', 'lead_time_days']],
        'website_specs' => ['project_company_inventory_item_website_spec', 'item_website_spec_key', ['specification_label', 'specification_value']],
        'reorder_rows' => ['project_company_inventory_item_reorder', 'item_reorder_key', ['warehouse_reference', 'reorder_level', 'reorder_qty']],
        default => throw new LogicException('Unknown Inventory catalogue child collection.'),
    };
}

function yovel_admin_inventory_replace_children(ADOConnection $db, array $scope, string $itemKey, string $collection, array $rows): void
{
    [$companyKey, $companyKeyHash] = $scope;
    [$table, $keyColumn, $businessColumns] = yovel_admin_inventory_catalogue_child_config($collection);
    yovel_admin_inventory_execute($db, "DELETE FROM {$table} WHERE company_key_hash=? AND item_key=?", [$companyKeyHash, $itemKey], 'Inventory ' . $collection . ' replace');
    $expected = [];
    foreach ($rows as $row) {
        $saved = [$keyColumn => bx_uuid(), 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'item_key' => $itemKey];
        foreach ($businessColumns as $column) {
            $saved[$column] = $row[$column];
        }
        $columns = array_keys($saved);
        $sql = "INSERT INTO {$table} (`" . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        yovel_admin_inventory_execute($db, $sql, array_values($saved), 'Inventory ' . $collection . ' save');
        $expected[] = $saved;
    }
    $actual = $db->GetAll("SELECT * FROM {$table} WHERE company_key_hash=? AND item_key=? ORDER BY x_id", [$companyKeyHash, $itemKey]);
    if (!is_array($actual) || count($actual) !== count($expected)) {
        throw new RuntimeException('Inventory ' . $collection . ' read-back count failed.');
    }
    foreach ($expected as $index => $row) {
        yovel_admin_inventory_assert_readback($row, $actual[$index], array_keys($row), 'Inventory ' . $collection);
    }
}

function yovel_admin_inventory_normalize_item_children(array $item, ADOConnection $db, array $scope): array
{
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $uoms = [];
    foreach ($item['uoms'] as $row) {
        $existing = $db->GetRow('SELECT * FROM project_company_inventory_uom WHERE company_key_hash=? AND uom_code=? FOR UPDATE', [$companyKeyHash, $row['uom_code']]);
        $uomKey = is_array($existing) && $existing !== [] ? (string) $existing['uom_key'] : bx_uuid();
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_uom SET uom_name=?,uom_category=?,uom_status=\'ACTIVE\' WHERE company_key_hash=? AND uom_key=?', [$row['uom_name'], $row['uom_category'], $companyKeyHash, $uomKey], 'Inventory UOM update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_uom (uom_key,company_key,company_key_hash,uom_code,uom_name,uom_category,created_by_admin_key) VALUES (?,?,?,?,?,?,?)', [$uomKey, $companyKey, $companyKeyHash, $row['uom_code'], $row['uom_name'], $row['uom_category'], $adminKey], 'Inventory UOM create');
        }
        $savedUom = $db->GetRow('SELECT * FROM project_company_inventory_uom WHERE company_key_hash=? AND uom_key=? LIMIT 1', [$companyKeyHash, $uomKey]);
        yovel_admin_inventory_assert_readback(['uom_key' => $uomKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'uom_code' => $row['uom_code'], 'uom_name' => $row['uom_name'], 'uom_category' => $row['uom_category'], 'uom_status' => 'ACTIVE'], is_array($savedUom) ? $savedUom : [], ['uom_key','company_key','company_key_hash','uom_code','uom_name','uom_category','uom_status'], 'Inventory UOM master');
        $uoms[] = ['uom_key' => $uomKey, 'uom_code' => $row['uom_code'], 'conversion_factor' => $row['conversion_factor'], 'is_stock_uom' => $row['is_stock_uom']];
    }

    $manufacturers = [];
    foreach ($item['manufacturers'] as $row) {
        $code = yovel_admin_inventory_catalogue_code((string) ($row['manufacturer_code'] ?? ''));
        $name = yovel_admin_inventory_catalogue_text($row, 'manufacturer_name', 160, true);
        $website = yovel_admin_inventory_catalogue_text($row, 'website', 255);
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Inventory manufacturer website is invalid.');
        }
        $existing = $db->GetRow('SELECT * FROM project_company_inventory_manufacturer WHERE company_key_hash=? AND manufacturer_code=? FOR UPDATE', [$companyKeyHash, $code]);
        $key = is_array($existing) && $existing !== [] ? (string) $existing['manufacturer_key'] : bx_uuid();
        if (is_array($existing) && $existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_manufacturer SET manufacturer_name=?,website=?,manufacturer_status=\'ACTIVE\' WHERE company_key_hash=? AND manufacturer_key=?', [$name, $website, $companyKeyHash, $key], 'Inventory manufacturer update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_manufacturer (manufacturer_key,company_key,company_key_hash,manufacturer_code,manufacturer_name,website,created_by_admin_key) VALUES (?,?,?,?,?,?,?)', [$key, $companyKey, $companyKeyHash, $code, $name, $website, $adminKey], 'Inventory manufacturer create');
        }
        $savedManufacturer = $db->GetRow('SELECT * FROM project_company_inventory_manufacturer WHERE company_key_hash=? AND manufacturer_key=? LIMIT 1', [$companyKeyHash, $key]);
        yovel_admin_inventory_assert_readback(['manufacturer_key' => $key, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'manufacturer_code' => $code, 'manufacturer_name' => $name, 'website' => $website, 'manufacturer_status' => 'ACTIVE'], is_array($savedManufacturer) ? $savedManufacturer : [], ['manufacturer_key','company_key','company_key_hash','manufacturer_code','manufacturer_name','website','manufacturer_status'], 'Inventory manufacturer master');
        $manufacturers[] = ['manufacturer_key' => $key, 'manufacturer_part_no' => yovel_admin_inventory_catalogue_text($row, 'manufacturer_part_no', 120)];
    }

    $simple = [
        'barcodes' => $item['barcodes'],
        'variant_attributes' => $item['variant_attributes'],
        'manufacturers' => $manufacturers,
    ];
    $simple['uoms'] = $uoms;
    $simple['alternatives'] = array_map(static function (array $row) use ($db, $companyKeyHash, $item): array {
        $key = trim((string) ($row['alternative_item_key'] ?? ''));
        if (!yovel_admin_is_uuid($key) || $key === $item['item_key']) {
            throw new InvalidArgumentException('Inventory alternative item reference is invalid.');
        }
        $owned = $db->GetOne('SELECT item_key FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=?', [$companyKeyHash, $key]);
        if ((string) $owned !== $key) {
            throw new InvalidArgumentException('Inventory alternative item belongs to another company.');
        }
        return ['alternative_item_key' => $key, 'two_way' => yovel_admin_inventory_catalogue_flag($row['two_way'] ?? 0)];
    }, $item['alternatives']);
    $simple['party_details'] = array_map(static function (array $row): array {
        $type = strtoupper(trim((string) ($row['party_type'] ?? '')));
        $key = trim((string) ($row['party_reference_key'] ?? ''));
        if (!in_array($type, ['CUSTOMER', 'SUPPLIER'], true) || !yovel_admin_is_uuid($key)) {
            throw new InvalidArgumentException('Inventory party detail is invalid.');
        }
        return ['party_type' => $type, 'party_reference_key' => $key, 'party_item_code' => yovel_admin_inventory_catalogue_text($row, 'party_item_code', 120)];
    }, $item['party_details']);
    $simple['taxes'] = array_map(static fn (array $row): array => [
        'tax_template_reference' => yovel_admin_inventory_catalogue_text($row, 'tax_template_reference', 120, true),
        'tax_rate' => yovel_admin_inventory_decimal((string) ($row['tax_rate'] ?? ''), 6),
    ], $item['taxes']);
    $simple['defaults'] = array_map(static fn (array $row): array => [
        'scope_key' => yovel_admin_inventory_catalogue_text($row, 'scope_key', 120, true),
        'default_warehouse_reference' => yovel_admin_inventory_catalogue_text($row, 'default_warehouse_reference', 120),
        'default_price_list_reference' => yovel_admin_inventory_catalogue_text($row, 'default_price_list_reference', 120),
    ], $item['defaults']);
    $simple['lead_times'] = array_map(static function (array $row): array {
        $days = filter_var($row['lead_time_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 36500]]);
        if ($days === false) {
            throw new InvalidArgumentException('Inventory lead time is invalid.');
        }
        return ['lead_time_context' => yovel_admin_inventory_catalogue_text(['lead_time_context' => $row['context'] ?? $row['lead_time_context'] ?? ''], 'lead_time_context', 80, true), 'lead_time_days' => $days];
    }, $item['lead_times']);
    $simple['website_specs'] = array_map(static fn (array $row): array => [
        'specification_label' => yovel_admin_inventory_catalogue_text(['specification_label' => $row['label'] ?? $row['specification_label'] ?? ''], 'specification_label', 160, true),
        'specification_value' => yovel_admin_inventory_catalogue_text(['specification_value' => $row['value'] ?? $row['specification_value'] ?? ''], 'specification_value', 500, true),
    ], $item['website_specs']);
    $simple['reorder_rows'] = array_map(static fn (array $row): array => [
        'warehouse_reference' => yovel_admin_inventory_catalogue_text($row, 'warehouse_reference', 120, true),
        'reorder_level' => yovel_admin_inventory_decimal((string) ($row['reorder_level'] ?? ''), 9),
        'reorder_qty' => yovel_admin_inventory_decimal((string) ($row['reorder_qty'] ?? ''), 9),
    ], $item['reorder_rows']);
    return $simple;
}

function yovel_admin_inventory_sync_variant_masters(ADOConnection $db, array $scope, string $itemKey, bool $isTemplate, array $attributes): void
{
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    if ($attributes !== []) {
        $setting = $db->GetRow('SELECT * FROM project_company_inventory_variant_setting WHERE company_key_hash=? FOR UPDATE', [$companyKeyHash]);
        $settingKey = is_array($setting) && $setting !== [] ? (string) $setting['variant_setting_key'] : bx_uuid();
        if (!is_array($setting) || $setting === []) {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_variant_setting (variant_setting_key,company_key,company_key_hash,naming_mode,setting_status,updated_by_admin_key) VALUES (?,?,?,\'ITEM_CODE\',\'ACTIVE\',?)', [$settingKey, $companyKey, $companyKeyHash, $adminKey], 'Inventory variant setting create');
        }
        $savedSetting = $db->GetRow('SELECT * FROM project_company_inventory_variant_setting WHERE company_key_hash=? LIMIT 1', [$companyKeyHash]);
        yovel_admin_inventory_assert_readback(['variant_setting_key' => $settingKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'naming_mode' => 'ITEM_CODE', 'setting_status' => 'ACTIVE'], is_array($savedSetting) ? $savedSetting : [], ['variant_setting_key','company_key','company_key_hash','naming_mode','setting_status'], 'Inventory variant setting');
    }

    yovel_admin_inventory_execute($db, 'DELETE FROM project_company_inventory_variant_field WHERE company_key_hash=? AND template_item_key=?', [$companyKeyHash, $itemKey], 'Inventory variant fields replace');
    $expectedFields = [];
    foreach (array_values($attributes) as $index => $attribute) {
        $master = $db->GetRow('SELECT * FROM project_company_inventory_item_attribute WHERE company_key_hash=? AND attribute_name=? FOR UPDATE', [$companyKeyHash, $attribute['attribute_name']]);
        $attributeKey = is_array($master) && $master !== [] ? (string) $master['item_attribute_key'] : bx_uuid();
        if (!is_array($master) || $master === []) {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_item_attribute (item_attribute_key,company_key,company_key_hash,attribute_name,attribute_status,created_by_admin_key) VALUES (?,?,?, ?,\'ACTIVE\',?)', [$attributeKey, $companyKey, $companyKeyHash, $attribute['attribute_name'], $adminKey], 'Inventory item attribute create');
        }
        $savedMaster = $db->GetRow('SELECT * FROM project_company_inventory_item_attribute WHERE company_key_hash=? AND item_attribute_key=? LIMIT 1', [$companyKeyHash, $attributeKey]);
        yovel_admin_inventory_assert_readback(['item_attribute_key' => $attributeKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'attribute_name' => $attribute['attribute_name'], 'attribute_status' => 'ACTIVE'], is_array($savedMaster) ? $savedMaster : [], ['item_attribute_key','company_key','company_key_hash','attribute_name','attribute_status'], 'Inventory item attribute');

        $value = $db->GetRow('SELECT * FROM project_company_inventory_item_attribute_value WHERE company_key_hash=? AND item_attribute_key=? AND attribute_value=? FOR UPDATE', [$companyKeyHash, $attributeKey, $attribute['attribute_value']]);
        $valueKey = is_array($value) && $value !== [] ? (string) $value['item_attribute_value_key'] : bx_uuid();
        $abbreviation = substr((string) preg_replace('/[^A-Z0-9]+/', '', strtoupper($attribute['attribute_value'])), 0, 20);
        $abbreviation = $abbreviation !== '' ? $abbreviation : 'VALUE';
        if (!is_array($value) || $value === []) {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_item_attribute_value (item_attribute_value_key,company_key,company_key_hash,item_attribute_key,attribute_value,abbreviation) VALUES (?,?,?,?,?,?)', [$valueKey, $companyKey, $companyKeyHash, $attributeKey, $attribute['attribute_value'], $abbreviation], 'Inventory item attribute value create');
        }
        $savedValue = $db->GetRow('SELECT * FROM project_company_inventory_item_attribute_value WHERE company_key_hash=? AND item_attribute_value_key=? LIMIT 1', [$companyKeyHash, $valueKey]);
        yovel_admin_inventory_assert_readback(['item_attribute_value_key' => $valueKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'item_attribute_key' => $attributeKey, 'attribute_value' => $attribute['attribute_value'], 'abbreviation' => $abbreviation], is_array($savedValue) ? $savedValue : [], ['item_attribute_value_key','company_key','company_key_hash','item_attribute_key','attribute_value','abbreviation'], 'Inventory item attribute value');

        if ($isTemplate) {
            $field = ['variant_field_key' => bx_uuid(), 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'template_item_key' => $itemKey, 'item_attribute_key' => $attributeKey, 'field_order' => ($index + 1) * 10];
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_variant_field (variant_field_key,company_key,company_key_hash,template_item_key,item_attribute_key,field_order) VALUES (?,?,?,?,?,?)', array_values($field), 'Inventory variant field save');
            $expectedFields[] = $field;
        }
    }
    $savedFields = $db->GetAll('SELECT * FROM project_company_inventory_variant_field WHERE company_key_hash=? AND template_item_key=? ORDER BY field_order', [$companyKeyHash, $itemKey]);
    if (!is_array($savedFields) || count($savedFields) !== count($expectedFields)) {
        throw new RuntimeException('Inventory variant fields read-back count failed.');
    }
    foreach ($expectedFields as $index => $field) {
        yovel_admin_inventory_assert_readback($field, $savedFields[$index], array_keys($field), 'Inventory variant field');
    }
}

function yovel_admin_save_inventory_item(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_catalogue_schema();
    $item = yovel_admin_inventory_normalize_item_input($input);
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($company, $scope, $item): array {
        [$companyKey, $companyKeyHash, $adminKey] = $scope;
        $lock = $db->GetRow("SELECT company_key FROM project_company WHERE company_key=? AND company_key_hash=? AND company_status='ACTIVE' FOR UPDATE", [$companyKey, $companyKeyHash]);
        if (!is_array($lock) || $lock === []) {
            throw new RuntimeException('Inventory company scope changed before save.');
        }
        $byKey = $item['item_key'] !== '' ? $db->GetRow('SELECT * FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=? FOR UPDATE', [$companyKeyHash, $item['item_key']]) : [];
        $byCode = $db->GetRow('SELECT * FROM project_company_inventory_item WHERE company_key_hash=? AND item_code=? FOR UPDATE', [$companyKeyHash, $item['item_code']]);
        if ($item['item_key'] !== '' && (!is_array($byKey) || $byKey === [])) {
            throw new InvalidArgumentException('Inventory item was not found in this company.');
        }
        if (is_array($byKey) && $byKey !== [] && is_array($byCode) && $byCode !== [] && (string) $byKey['item_key'] !== (string) $byCode['item_key']) {
            throw new InvalidArgumentException('Inventory item code already exists in this company.');
        }
        $existing = is_array($byKey) && $byKey !== [] ? $byKey : (is_array($byCode) && $byCode !== [] ? $byCode : []);
        if ($existing !== [] && (int) $existing['stock_activity_count'] > 0) {
            foreach (['stock_uom_code', 'has_serial_no', 'has_batch_no'] as $field) {
                if ((string) $existing[$field] !== (string) $item[$field]) {
                    throw new RuntimeException('Inventory stock activity prevents changing stock UOM, serial, or batch controls.');
                }
            }
        }
        if ($item['variant_of_item_key'] !== '') {
            $template = $db->GetRow('SELECT item_key FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=? AND is_template=1 FOR UPDATE', [$companyKeyHash, $item['variant_of_item_key']]);
            if (!is_array($template) || $template === []) {
                throw new InvalidArgumentException('Inventory variant template belongs to another company or is not a template.');
            }
            $templateAttributes = $db->GetCol('SELECT attribute_name FROM project_company_inventory_item_variant_attribute WHERE company_key_hash=? AND item_key=? ORDER BY attribute_name', [$companyKeyHash, $item['variant_of_item_key']]);
            $variantAttributes = array_column($item['variant_attributes'], 'attribute_name');
            sort($templateAttributes);
            sort($variantAttributes);
            if ($templateAttributes !== $variantAttributes) {
                throw new InvalidArgumentException('Inventory variant attributes must match the template.');
            }
        }
        foreach ($item['barcodes'] as $barcode) {
            $duplicate = $db->GetRow('SELECT item_key FROM project_company_inventory_item_barcode WHERE company_key_hash=? AND barcode=? FOR UPDATE', [$companyKeyHash, $barcode['barcode']]);
            if (is_array($duplicate) && $duplicate !== [] && (string) $duplicate['item_key'] !== (string) ($existing['item_key'] ?? '')) {
                throw new InvalidArgumentException('Inventory barcode already belongs to another item.');
            }
        }
        if ($item['customs_tariff_code'] !== '') {
            $tariffCode = strtoupper($item['customs_tariff_code']);
            $tariff = $db->GetRow('SELECT tariff_key FROM project_company_inventory_customs_tariff WHERE company_key_hash=? AND tariff_code=? FOR UPDATE', [$companyKeyHash, $tariffCode]);
            if (is_array($tariff) && $tariff !== []) {
                yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_customs_tariff SET tariff_description=? WHERE company_key_hash=? AND tariff_code=?', [$item['customs_tariff_description'], $companyKeyHash, $tariffCode], 'Inventory tariff update');
            } else {
                yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_customs_tariff (tariff_key,company_key,company_key_hash,tariff_code,tariff_description,created_by_admin_key) VALUES (?,?,?,?,?,?)', [bx_uuid(), $companyKey, $companyKeyHash, $tariffCode, $item['customs_tariff_description'], $adminKey], 'Inventory tariff create');
            }
            $item['customs_tariff_code'] = $tariffCode;
            $savedTariff = $db->GetRow('SELECT * FROM project_company_inventory_customs_tariff WHERE company_key_hash=? AND tariff_code=? LIMIT 1', [$companyKeyHash, $tariffCode]);
            yovel_admin_inventory_assert_readback(['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'tariff_code' => $tariffCode, 'tariff_description' => $item['customs_tariff_description']], is_array($savedTariff) ? $savedTariff : [], ['company_key','company_key_hash','tariff_code','tariff_description'], 'Inventory customs tariff');
        }
        $itemKey = $existing !== [] ? (string) $existing['item_key'] : bx_uuid();
        $action = $existing !== [] ? 'UPDATE' : 'CREATE';
        $headerValues = [$item['item_code'], $item['item_name'], $item['item_description'], $item['item_status'], $item['item_kind'], $item['stock_uom_code'], $item['has_serial_no'], $item['has_batch_no'], $item['is_template'], $item['variant_of_item_key'] !== '' ? $item['variant_of_item_key'] : null, $item['customs_tariff_code'], $adminKey];
        if ($existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_item SET item_code=?,item_name=?,item_description=?,item_status=?,item_kind=?,stock_uom_code=?,has_serial_no=?,has_batch_no=?,is_template=?,variant_of_item_key=?,customs_tariff_code=?,updated_by_admin_key=? WHERE company_key_hash=? AND item_key=?', [...$headerValues, $companyKeyHash, $itemKey], 'Inventory item update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_item (item_key,company_key,company_key_hash,item_code,item_name,item_description,item_status,item_kind,stock_uom_code,has_serial_no,has_batch_no,is_template,variant_of_item_key,customs_tariff_code,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$itemKey, $companyKey, $companyKeyHash, ...array_slice($headerValues, 0, 11), $adminKey, $adminKey], 'Inventory item create');
        }
        yovel_admin_inventory_catalogue_fault('item_after_header');
        $item['item_key'] = $itemKey;
        $children = yovel_admin_inventory_normalize_item_children($item, $db, $scope);
        yovel_admin_inventory_sync_variant_masters($db, $scope, $itemKey, $item['is_template'] === 1, $children['variant_attributes']);
        foreach ($children as $collection => $rows) {
            yovel_admin_inventory_replace_children($db, $scope, $itemKey, $collection, $rows);
        }
        bx_audit($action, 'project_company_inventory_item', $itemKey, ['company_key' => $companyKey, 'item_code' => $item['item_code'], 'item_name' => $item['item_name'], 'admin_key' => $adminKey], 'Company administrator saved an Inventory item catalogue record.');
        $saved = yovel_admin_inventory_item($company, $itemKey);
        if (!is_array($saved)) {
            throw new RuntimeException('Inventory item read-back failed.');
        }
        $expectedHeader = [
            'item_key' => $itemKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
            'item_code' => $item['item_code'], 'item_name' => $item['item_name'], 'item_description' => $item['item_description'],
            'item_status' => $item['item_status'], 'item_kind' => $item['item_kind'], 'stock_uom_code' => $item['stock_uom_code'],
            'has_serial_no' => (string) $item['has_serial_no'], 'has_batch_no' => (string) $item['has_batch_no'],
            'is_template' => (string) $item['is_template'], 'variant_of_item_key' => $item['variant_of_item_key'],
            'customs_tariff_code' => $item['customs_tariff_code'], 'updated_by_admin_key' => $adminKey,
        ];
        yovel_admin_inventory_assert_readback($expectedHeader, $saved, array_keys($expectedHeader), 'Inventory item');
        yovel_admin_inventory_catalogue_fault('item_before_commit');
        return $saved;
    });
}

function yovel_admin_inventory_item(array $company, string $itemKey): ?array
{
    yovel_admin_inventory_catalogue_schema();
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1 || !yovel_admin_is_uuid($itemKey)) {
        return null;
    }
    $db = bx_db();
    $row = $db->GetRow('SELECT * FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=? LIMIT 1', [$hash, $itemKey]);
    if (!is_array($row) || $row === []) {
        return null;
    }
    $row['description'] = (string) $row['item_description'];
    $row['uoms'] = $db->GetAll('SELECT iu.item_uom_key,iu.uom_key,iu.uom_code,u.uom_name,u.uom_category AS category,iu.conversion_factor,iu.is_stock_uom FROM project_company_inventory_item_uom iu JOIN project_company_inventory_uom u ON u.company_key_hash=iu.company_key_hash AND u.uom_key=iu.uom_key WHERE iu.company_key_hash=? AND iu.item_key=? ORDER BY iu.x_id', [$hash, $itemKey]);
    $row['barcodes'] = $db->GetAll('SELECT item_barcode_key,barcode,barcode_type FROM project_company_inventory_item_barcode WHERE company_key_hash=? AND item_key=? ORDER BY x_id', [$hash, $itemKey]);
    $row['variant_attributes'] = $db->GetAll('SELECT item_variant_attribute_key,attribute_name,attribute_value FROM project_company_inventory_item_variant_attribute WHERE company_key_hash=? AND item_key=? ORDER BY x_id', [$hash, $itemKey]);
    $row['manufacturers'] = $db->GetAll('SELECT im.item_manufacturer_key,m.manufacturer_key,m.manufacturer_code,m.manufacturer_name,m.website,im.manufacturer_part_no FROM project_company_inventory_item_manufacturer im JOIN project_company_inventory_manufacturer m ON m.company_key_hash=im.company_key_hash AND m.manufacturer_key=im.manufacturer_key WHERE im.company_key_hash=? AND im.item_key=? ORDER BY im.x_id', [$hash, $itemKey]);
    foreach (['alternatives' => ['project_company_inventory_item_alternative', 'item_alternative_key,alternative_item_key,two_way'], 'party_details' => ['project_company_inventory_item_party_detail', 'item_party_detail_key,party_type,party_reference_key,party_item_code'], 'taxes' => ['project_company_inventory_item_tax', 'item_tax_key,tax_template_reference,tax_rate'], 'defaults' => ['project_company_inventory_item_default', 'item_default_key,scope_key,default_warehouse_reference,default_price_list_reference'], 'lead_times' => ['project_company_inventory_item_lead_time', 'item_lead_time_key,lead_time_context AS context,lead_time_days'], 'website_specs' => ['project_company_inventory_item_website_spec', 'item_website_spec_key,specification_label AS label,specification_value AS value'], 'reorder_rows' => ['project_company_inventory_item_reorder', 'item_reorder_key,warehouse_reference,reorder_level,reorder_qty']] as $key => [$table, $columns]) {
        $row[$key] = $db->GetAll("SELECT {$columns} FROM {$table} WHERE company_key_hash=? AND item_key=? ORDER BY x_id", [$hash, $itemKey]);
    }
    return $row;
}

function yovel_admin_inventory_items(array $company, array $filters = []): array
{
    yovel_admin_inventory_catalogue_schema();
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
        return [];
    }
    $sql = 'SELECT * FROM project_company_inventory_item WHERE company_key_hash=?';
    $params = [$hash];
    $status = strtoupper(trim((string) ($filters['status'] ?? '')));
    if (in_array($status, ['ACTIVE', 'DISABLED'], true)) {
        $sql .= ' AND item_status=?';
        $params[] = $status;
    }
    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $sql .= ' AND (item_code LIKE ? OR item_name LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $rows = bx_db()->GetAll($sql . ' ORDER BY item_code LIMIT 200', $params);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_resolve_item_uom(array $company, string $itemKey, string $uomKey, string $qty): array
{
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $uom = strtoupper(trim($uomKey));
    $row = bx_db()->GetRow("SELECT i.stock_uom_code,iu.conversion_factor FROM project_company_inventory_item i JOIN project_company_inventory_item_uom iu ON iu.company_key_hash=i.company_key_hash AND iu.item_key=i.item_key WHERE i.company_key_hash=? AND i.item_key=? AND i.item_status='ACTIVE' AND iu.uom_code=? LIMIT 1", [$hash, $itemKey, $uom]);
    if (!is_array($row) || $row === []) {
        throw new InvalidArgumentException('Inventory item UOM was not found or is disabled.');
    }
    $quantity = yovel_admin_inventory_decimal($qty, 9);
    return ['quantity' => $quantity, 'conversion_factor' => (string) $row['conversion_factor'], 'stock_quantity' => bcmul($quantity, (string) $row['conversion_factor'], 9), 'stock_uom_code' => (string) $row['stock_uom_code']];
}

function yovel_admin_resolve_item_barcode(array $company, string $barcode): ?array
{
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $barcode = strtoupper(trim((string) preg_replace('/[\s-]+/', '', $barcode)));
    $row = bx_db()->GetRow("SELECT i.*,b.item_barcode_key,b.barcode,b.barcode_type FROM project_company_inventory_item_barcode b JOIN project_company_inventory_item i ON i.company_key_hash=b.company_key_hash AND i.item_key=b.item_key AND i.item_status='ACTIVE' WHERE b.company_key_hash=? AND b.barcode=? LIMIT 1", [$hash, $barcode]);
    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_inventory_normalize_price_input(array $input): array
{
    $key = trim((string) ($input['item_price_key'] ?? ''));
    $itemKey = trim((string) ($input['item_key'] ?? ''));
    if (($key !== '' && !yovel_admin_is_uuid($key)) || !yovel_admin_is_uuid($itemKey)) {
        throw new InvalidArgumentException('Inventory item price key is invalid.');
    }
    $currency = strtoupper(trim((string) ($input['currency_code'] ?? '')));
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
        throw new InvalidArgumentException('Inventory item price currency is invalid.');
    }
    $from = yovel_admin_inventory_catalogue_date($input['valid_from'] ?? '', true);
    $to = yovel_admin_inventory_catalogue_date($input['valid_to'] ?? '');
    if ($to !== null && $to < $from) {
        throw new InvalidArgumentException('Inventory item price validity range is invalid.');
    }
    $selling = yovel_admin_inventory_catalogue_flag($input['is_selling'] ?? 0);
    $buying = yovel_admin_inventory_catalogue_flag($input['is_buying'] ?? 0);
    if ($selling === 0 && $buying === 0) {
        throw new InvalidArgumentException('Inventory price list must be buying, selling, or both.');
    }
    $countries = $input['countries'] ?? [];
    if (is_string($countries)) {
        $decoded = json_decode($countries, true);
        $countries = is_array($decoded) ? $decoded : preg_split('/[,\s]+/', $countries, -1, PREG_SPLIT_NO_EMPTY);
    }
    $countries = array_values(array_unique(array_map('strtoupper', array_map('trim', is_array($countries) ? $countries : []))));
    foreach ($countries as $country) {
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            throw new InvalidArgumentException('Inventory price list country is invalid.');
        }
    }
    $status = strtoupper(trim((string) ($input['price_status'] ?? 'ACTIVE')));
    if (!in_array($status, ['ACTIVE', 'DISABLED'], true)) {
        throw new InvalidArgumentException('Inventory item price status is invalid.');
    }
    return [
        'item_price_key' => $key, 'item_key' => $itemKey,
        'price_list_code' => yovel_admin_inventory_catalogue_code((string) ($input['price_list_code'] ?? '')),
        'price_list_name' => yovel_admin_inventory_catalogue_text($input, 'price_list_name', 160, true),
        'currency_code' => $currency, 'uom_code' => yovel_admin_inventory_catalogue_code((string) ($input['uom_code'] ?? ''), 40),
        'rate' => yovel_admin_inventory_decimal((string) ($input['rate'] ?? ''), 9),
        'minimum_qty' => yovel_admin_inventory_decimal((string) ($input['minimum_qty'] ?? '0'), 9),
        'valid_from' => $from, 'valid_to' => $to, 'is_selling' => $selling, 'is_buying' => $buying,
        'countries' => $countries, 'price_status' => $status,
    ];
}

function yovel_admin_save_item_price(array $company, ?array $admin, array $input): array
{
    $scope = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_catalogue_schema();
    $price = yovel_admin_inventory_normalize_price_input($input);
    return yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use ($scope, $price): array {
        [$companyKey, $companyKeyHash, $adminKey] = $scope;
        $lock = $db->GetRow("SELECT company_key FROM project_company WHERE company_key=? AND company_key_hash=? AND company_status='ACTIVE' FOR UPDATE", [$companyKey, $companyKeyHash]);
        $item = $db->GetRow("SELECT item_key FROM project_company_inventory_item WHERE company_key_hash=? AND item_key=? AND item_status='ACTIVE' FOR UPDATE", [$companyKeyHash, $price['item_key']]);
        $uom = $db->GetRow('SELECT item_uom_key FROM project_company_inventory_item_uom WHERE company_key_hash=? AND item_key=? AND uom_code=? FOR UPDATE', [$companyKeyHash, $price['item_key'], $price['uom_code']]);
        if (!is_array($lock) || $lock === [] || !is_array($item) || $item === [] || !is_array($uom) || $uom === []) {
            throw new InvalidArgumentException('Inventory item price requires an active company item and valid UOM.');
        }
        $list = $db->GetRow('SELECT * FROM project_company_inventory_price_list WHERE company_key_hash=? AND price_list_code=? FOR UPDATE', [$companyKeyHash, $price['price_list_code']]);
        $priceListKey = is_array($list) && $list !== [] ? (string) $list['price_list_key'] : bx_uuid();
        if (is_array($list) && $list !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_price_list SET price_list_name=?,currency_code=?,is_selling=?,is_buying=?,price_list_status=\'ACTIVE\' WHERE company_key_hash=? AND price_list_key=?', [$price['price_list_name'], $price['currency_code'], $price['is_selling'], $price['is_buying'], $companyKeyHash, $priceListKey], 'Inventory price list update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_price_list (price_list_key,company_key,company_key_hash,price_list_code,price_list_name,currency_code,is_selling,is_buying,created_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?)', [$priceListKey, $companyKey, $companyKeyHash, $price['price_list_code'], $price['price_list_name'], $price['currency_code'], $price['is_selling'], $price['is_buying'], $adminKey], 'Inventory price list create');
        }
        yovel_admin_inventory_execute($db, 'DELETE FROM project_company_inventory_price_list_country WHERE company_key_hash=? AND price_list_key=?', [$companyKeyHash, $priceListKey], 'Inventory price list countries replace');
        foreach ($price['countries'] as $country) {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_price_list_country (price_list_country_key,company_key,company_key_hash,price_list_key,country_code) VALUES (?,?,?,?,?)', [bx_uuid(), $companyKey, $companyKeyHash, $priceListKey, $country], 'Inventory price list country save');
        }
        $savedList = $db->GetRow('SELECT * FROM project_company_inventory_price_list WHERE company_key_hash=? AND price_list_key=? LIMIT 1', [$companyKeyHash, $priceListKey]);
        yovel_admin_inventory_assert_readback(['price_list_key' => $priceListKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'price_list_code' => $price['price_list_code'], 'price_list_name' => $price['price_list_name'], 'currency_code' => $price['currency_code'], 'is_selling' => (string) $price['is_selling'], 'is_buying' => (string) $price['is_buying'], 'price_list_status' => 'ACTIVE'], is_array($savedList) ? $savedList : [], ['price_list_key','company_key','company_key_hash','price_list_code','price_list_name','currency_code','is_selling','is_buying','price_list_status'], 'Inventory price list');
        $savedCountries = $db->GetCol('SELECT country_code FROM project_company_inventory_price_list_country WHERE company_key_hash=? AND price_list_key=? ORDER BY country_code', [$companyKeyHash, $priceListKey]);
        $expectedCountries = $price['countries'];
        sort($expectedCountries);
        if ($savedCountries !== $expectedCountries) {
            throw new RuntimeException('Inventory price list countries read-back failed.');
        }
        $byKey = $price['item_price_key'] !== '' ? $db->GetRow('SELECT * FROM project_company_inventory_item_price WHERE company_key_hash=? AND item_price_key=? FOR UPDATE', [$companyKeyHash, $price['item_price_key']]) : [];
        $natural = $db->GetRow('SELECT * FROM project_company_inventory_item_price WHERE company_key_hash=? AND item_key=? AND price_list_key=? AND uom_code=? AND valid_from=? FOR UPDATE', [$companyKeyHash, $price['item_key'], $priceListKey, $price['uom_code'], $price['valid_from']]);
        if ($price['item_price_key'] !== '' && (!is_array($byKey) || $byKey === [])) {
            throw new InvalidArgumentException('Inventory item price was not found in this company.');
        }
        if (is_array($byKey) && $byKey !== [] && is_array($natural) && $natural !== [] && (string) $byKey['item_price_key'] !== (string) $natural['item_price_key']) {
            throw new InvalidArgumentException('Inventory item price already exists for this validity date.');
        }
        $existing = is_array($byKey) && $byKey !== [] ? $byKey : (is_array($natural) && $natural !== [] ? $natural : []);
        $key = $existing !== [] ? (string) $existing['item_price_key'] : bx_uuid();
        $values = [$price['item_key'], $priceListKey, $price['uom_code'], $price['currency_code'], $price['rate'], $price['minimum_qty'], $price['valid_from'], $price['valid_to'], $price['price_status'], $adminKey];
        if ($existing !== []) {
            yovel_admin_inventory_execute($db, 'UPDATE project_company_inventory_item_price SET item_key=?,price_list_key=?,uom_code=?,currency_code=?,rate=?,minimum_qty=?,valid_from=?,valid_to=?,price_status=?,updated_by_admin_key=? WHERE company_key_hash=? AND item_price_key=?', [...$values, $companyKeyHash, $key], 'Inventory item price update');
        } else {
            yovel_admin_inventory_execute($db, 'INSERT INTO project_company_inventory_item_price (item_price_key,company_key,company_key_hash,item_key,price_list_key,uom_code,currency_code,rate,minimum_qty,valid_from,valid_to,price_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$key, $companyKey, $companyKeyHash, ...array_slice($values, 0, 9), $adminKey, $adminKey], 'Inventory item price create');
        }
        yovel_admin_inventory_catalogue_fault('price_after_header');
        bx_audit($existing !== [] ? 'UPDATE' : 'CREATE', 'project_company_inventory_item_price', $key, ['company_key' => $companyKey, 'item_key' => $price['item_key'], 'price_list_code' => $price['price_list_code'], 'rate' => $price['rate'], 'admin_key' => $adminKey], 'Company administrator saved an Inventory item price.');
        $saved = yovel_admin_inventory_item_price($scope, $key);
        if (!is_array($saved)) {
            throw new RuntimeException('Inventory item price read-back failed.');
        }
        $expected = ['item_price_key' => $key, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'item_key' => $price['item_key'], 'price_list_key' => $priceListKey, 'uom_code' => $price['uom_code'], 'currency_code' => $price['currency_code'], 'rate' => $price['rate'], 'minimum_qty' => $price['minimum_qty'], 'valid_from' => $price['valid_from'], 'valid_to' => $price['valid_to'], 'price_status' => $price['price_status'], 'updated_by_admin_key' => $adminKey];
        yovel_admin_inventory_assert_readback($expected, $saved, array_keys($expected), 'Inventory item price');
        if ($saved['countries'] !== $price['countries']) {
            throw new RuntimeException('Inventory item price countries read-back failed.');
        }
        return $saved;
    });
}

function yovel_admin_inventory_item_price(array $company, string $priceKey): ?array
{
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? $company[1] ?? '')));
    if (!yovel_admin_is_uuid($priceKey) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT p.*,l.price_list_code,l.price_list_name,l.is_selling,l.is_buying FROM project_company_inventory_item_price p JOIN project_company_inventory_price_list l ON l.company_key_hash=p.company_key_hash AND l.price_list_key=p.price_list_key WHERE p.company_key_hash=? AND p.item_price_key=? LIMIT 1', [$hash, $priceKey]);
    if (!is_array($row) || $row === []) {
        return null;
    }
    $row['countries'] = bx_db()->GetCol('SELECT country_code FROM project_company_inventory_price_list_country WHERE company_key_hash=? AND price_list_key=? ORDER BY country_code', [$hash, $row['price_list_key']]);
    return $row;
}

function yovel_admin_inventory_item_prices(array $company, array $filters = []): array
{
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $sql = 'SELECT p.*,i.item_code,i.item_name,l.price_list_code,l.price_list_name,l.is_selling,l.is_buying FROM project_company_inventory_item_price p JOIN project_company_inventory_item i ON i.company_key_hash=p.company_key_hash AND i.item_key=p.item_key JOIN project_company_inventory_price_list l ON l.company_key_hash=p.company_key_hash AND l.price_list_key=p.price_list_key WHERE p.company_key_hash=?';
    $params = [$hash];
    if (yovel_admin_is_uuid((string) ($filters['item_key'] ?? ''))) {
        $sql .= ' AND p.item_key=?';
        $params[] = (string) $filters['item_key'];
    }
    $rows = bx_db()->GetAll($sql . ' ORDER BY i.item_code,l.price_list_code,p.valid_from LIMIT 500', $params);
    if (!is_array($rows)) {
        return [];
    }
    foreach ($rows as &$row) {
        $row['countries'] = bx_db()->GetCol('SELECT country_code FROM project_company_inventory_price_list_country WHERE company_key_hash=? AND price_list_key=? ORDER BY country_code', [$hash, $row['price_list_key']]);
    }
    unset($row);
    return $rows;
}

function yovel_admin_inventory_item_variant_details(array $company, string $templateItemKey): array
{
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $variants = bx_db()->GetAll("SELECT * FROM project_company_inventory_item WHERE company_key_hash=? AND variant_of_item_key=? ORDER BY item_code", [$hash, $templateItemKey]);
    foreach ($variants as &$variant) {
        $variant['variant_attributes'] = bx_db()->GetAll('SELECT attribute_name,attribute_value FROM project_company_inventory_item_variant_attribute WHERE company_key_hash=? AND item_key=? ORDER BY attribute_name', [$hash, $variant['item_key']]);
    }
    unset($variant);
    return is_array($variants) ? $variants : [];
}

function yovel_admin_inventory_item_input_from_post(array $input): array
{
    foreach (['uoms', 'barcodes', 'variant_attributes', 'manufacturers', 'alternatives', 'party_details', 'taxes', 'defaults', 'lead_times', 'website_specs', 'reorder_rows'] as $collection) {
        if (!array_key_exists($collection, $input) && array_key_exists($collection . '_json', $input)) {
            $input[$collection] = (string) $input[$collection . '_json'];
        }
    }
    $stockUom = strtoupper(trim((string) ($input['stock_uom_code'] ?? '')));
    $uoms = array_key_exists('uoms', $input) ? yovel_admin_inventory_catalogue_rows($input, 'uoms') : [];
    $uomCodes = array_map(static fn (array $row): string => strtoupper(trim((string) ($row['uom_code'] ?? ''))), $uoms);
    if (!in_array($stockUom, $uomCodes, true)) {
        $uoms[] = [
            'uom_code' => $stockUom,
            'uom_name' => trim((string) ($input['stock_uom_name'] ?? $stockUom)),
            'category' => trim((string) ($input['uom_category'] ?? 'GENERAL')),
            'conversion_factor' => '1',
        ];
    }
    $input['uoms'] = $uoms;
    $append = static function (array $rows, array $row, string $required): array {
        if (trim((string) ($row[$required] ?? '')) !== '') {
            $rows[] = $row;
        }
        return $rows;
    };
    $barcodes = yovel_admin_inventory_catalogue_rows($input, 'barcodes');
    $input['barcodes'] = $append($barcodes, ['barcode' => $input['barcode'] ?? '', 'barcode_type' => $input['barcode_type'] ?? 'EAN13'], 'barcode');
    $manufacturers = yovel_admin_inventory_catalogue_rows($input, 'manufacturers');
    $input['manufacturers'] = $append($manufacturers, [
        'manufacturer_code' => $input['manufacturer_code'] ?? '', 'manufacturer_name' => $input['manufacturer_name'] ?? '',
        'manufacturer_part_no' => $input['manufacturer_part_no'] ?? '', 'website' => $input['manufacturer_website'] ?? '',
    ], 'manufacturer_code');
    $attributes = yovel_admin_inventory_catalogue_rows($input, 'variant_attributes');
    $input['variant_attributes'] = $append($attributes, ['attribute_name' => $input['attribute_name'] ?? '', 'attribute_value' => $input['attribute_value'] ?? ''], 'attribute_name');
    return $input;
}
