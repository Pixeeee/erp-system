<?php
declare(strict_types=1);

function yovel_admin_operations_commercial_directory_definitions(): array
{
    return [
        'currency_exchange' => ['Currency Exchange', ['for_buying', 'for_selling']],
        'customer_group' => ['Customer Group', ['customer_group_key', 'customer_group_name', 'parent_customer_group_key', 'is_group', 'customer_group_status']],
        'incoterm' => ['Incoterm', ['incoterm_key', 'incoterm_code', 'incoterm_name', 'description', 'incoterm_status']],
        'party_type' => ['Party Type', ['account_type', 'owner_module']],
        'quotation_lost_reason' => ['Quotation Lost Reason', ['lost_reason_key', 'lost_reason_name', 'lost_reason_status']],
        'quotation_lost_reason_detail' => ['Quotation Lost Reason Detail', ['lost_reason_detail_key', 'quotation_key', 'lost_reason_key', 'sequence']],
        'sales_partner' => ['Sales Partner', ['sales_partner_key', 'partner_name', 'commission_rate', 'territory_key', 'partner_status']],
        'sales_person' => ['Sales Person', ['parent_salesperson_key', 'employee_key', 'is_group', 'commission_rate']],
        'supplier_group' => ['Supplier Group', ['supplier_group_code', 'supplier_group_name', 'parent_supplier_group_key', 'is_group', 'supplier_group_status']],
        'target_detail' => ['Target Detail', ['target_detail_key', 'parent_record_key', 'item_group_key', 'fiscal_year_key', 'target_quantity', 'target_amount', 'distribution_key']],
        'terms_and_conditions' => ['Terms and Conditions', ['terms_key', 'terms_title', 'applies_to', 'terms_body', 'attachment_keys', 'terms_status']],
        'territory' => ['Territory', ['parent_territory_key', 'is_group', 'territory_manager_key']],
    ];
}

function yovel_admin_operations_commercial_empty_directories(): array
{
    $directories = [];
    foreach (yovel_admin_operations_commercial_directory_definitions() as $key => [$label, $fields]) {
        $directories[$key] = [
            'label' => $label,
            'status' => 'UNAVAILABLE_DEPENDENCY',
            'blocking' => false,
            'records' => [],
            'unavailable_fields' => $fields,
            'reason' => 'No verified owner service currently exposes this directory.',
        ];
    }
    return $directories;
}

function yovel_admin_operations_commercial_source(string $owner, string $service, string $status, string $message = ''): array
{
    return [
        'owner' => $owner,
        'service' => $service,
        'status' => $status,
        'blocking' => false,
        'message' => $message,
    ];
}

function yovel_admin_operations_commercial_text(mixed $value, string $field, int $maximum): string
{
    $value = trim((string) $value);
    if (strlen($value) > $maximum) {
        throw new RuntimeException('Commercial owner response contains an oversized ' . $field . '.');
    }
    return $value;
}

function yovel_admin_operations_commercial_uuid(mixed $value, string $field, bool $required = true): string
{
    $value = yovel_admin_operations_commercial_text($value, $field, 64);
    if (($required && $value === '') || ($value !== '' && (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($value)))) {
        throw new RuntimeException('Commercial owner response contains an invalid ' . $field . '.');
    }
    return strtolower($value);
}

function yovel_admin_operations_commercial_status(mixed $value, string $field): string
{
    $status = strtoupper(yovel_admin_operations_commercial_text($value, $field, 40));
    if (!in_array($status, ['DRAFT', 'ACTIVE', 'INACTIVE', 'DISABLED', 'ARCHIVED'], true)) {
        throw new RuntimeException('Commercial owner response contains an invalid ' . $field . '.');
    }
    return $status;
}

function yovel_admin_operations_commercial_owner_actions(): array
{
    return [
        ['owner' => 'Finance', 'label' => 'Open Finance setup', 'href' => '?view=accounting-finance&section=dashboard'],
        ['owner' => 'Sales', 'label' => 'Open Sales customers', 'href' => '?view=sales-crm&section=customers'],
        ['owner' => 'Sales', 'label' => 'Open salesperson performance', 'href' => '?view=sales-crm&section=salesperson-performance'],
        ['owner' => 'Sales', 'label' => 'Open territory performance', 'href' => '?view=sales-crm&section=territory-performance'],
        ['owner' => 'Buying', 'label' => 'Open Buying suppliers', 'href' => '?view=buying-procurement&section=suppliers'],
        ['owner' => 'Inventory', 'label' => 'Open Inventory items', 'href' => '?view=inventory-warehouse&section=items'],
        ['owner' => 'HR', 'label' => 'Open HR employees', 'href' => '?view=hr&section=employee-profiles'],
    ];
}

function yovel_admin_operations_commercial_projection(array $company, array $admin, array $providers = []): array
{
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company, $admin);
    $directories = yovel_admin_operations_commercial_empty_directories();
    $sources = [];
    $contexts = ['customers' => [], 'items' => [], 'employees' => []];

    if (function_exists('yovel_admin_finance_foundation_records')) {
        try {
            $finance = yovel_admin_finance_foundation_records($company);
            if (!is_array($finance) || !is_array($finance['exchangeSettings'] ?? null)) {
                throw new RuntimeException('Finance foundation response is invalid.');
            }
            $rates = [];
            foreach ($finance['exchangeSettings'] as $setting) {
                if (!is_array($setting) || !is_array($setting['rates'] ?? null)) {
                    throw new RuntimeException('Finance exchange setting response is invalid.');
                }
                $settingKey = yovel_admin_operations_commercial_uuid($setting['exchange_setting_key'] ?? '', 'exchange setting key');
                $settingStatus = yovel_admin_operations_commercial_status($setting['status'] ?? '', 'exchange setting status');
                foreach ($setting['rates'] as $rate) {
                    if (!is_array($rate)) {
                        throw new RuntimeException('Finance exchange rate response is invalid.');
                    }
                    $rateSettingKey = yovel_admin_operations_commercial_uuid($rate['exchange_setting_key'] ?? $settingKey, 'rate exchange setting key');
                    if (!hash_equals($settingKey, $rateSettingKey)) {
                        throw new RuntimeException('Finance exchange rate setting reference is invalid.');
                    }
                    $from = strtoupper(yovel_admin_operations_commercial_text($rate['from_currency'] ?? '', 'from currency', 20));
                    $to = strtoupper(yovel_admin_operations_commercial_text($rate['to_currency'] ?? '', 'to currency', 20));
                    $date = yovel_admin_operations_commercial_text($rate['transaction_date'] ?? '', 'exchange date', 10);
                    $value = yovel_admin_operations_commercial_text($rate['exchange_rate'] ?? '', 'exchange rate', 40);
                    if (preg_match('/^[A-Z]{3,20}$/', $from) !== 1 || preg_match('/^[A-Z]{3,20}$/', $to) !== 1
                        || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,12})?$/', $value) !== 1) {
                        throw new RuntimeException('Finance exchange rate fields are invalid.');
                    }
                    $rates[] = [
                        'exchange_rate_key' => yovel_admin_operations_commercial_uuid($rate['exchange_rate_key'] ?? '', 'exchange rate key'),
                        'exchange_setting_key' => $settingKey,
                        'from_currency' => $from,
                        'to_currency' => $to,
                        'transaction_date' => $date,
                        'exchange_rate' => $value,
                        'status' => $settingStatus,
                    ];
                }
            }
            $directories['currency_exchange'] = [
                'label' => 'Currency Exchange', 'status' => 'PARTIAL', 'blocking' => false, 'records' => $rates,
                'unavailable_fields' => ['for_buying', 'for_selling'],
                'reason' => 'Finance exposes dated exchange rates, but not buying-versus-selling applicability flags.',
            ];
            $sources['finance'] = yovel_admin_operations_commercial_source('Finance', 'yovel_admin_finance_foundation_records', 'AVAILABLE');
        } catch (Throwable) {
            $sources['finance'] = yovel_admin_operations_commercial_source('Finance', 'yovel_admin_finance_foundation_records', 'UNAVAILABLE_DEPENDENCY', 'Finance exchange records are unavailable or invalid.');
        }
    } else {
        $sources['finance'] = yovel_admin_operations_commercial_source('Finance', 'yovel_admin_finance_foundation_records', 'UNAVAILABLE_DEPENDENCY', 'Finance foundation service is unavailable.');
    }

    if (function_exists('yovel_admin_sales_crm_data')) {
        try {
            $sales = yovel_admin_sales_crm_data($company, $admin);
            if (!is_array($sales) || !is_array($sales['territories'] ?? null) || !is_array($sales['salespersons'] ?? null) || !is_array($sales['customers'] ?? null)) {
                throw new RuntimeException('Sales commercial response is invalid.');
            }
            $territories = [];
            foreach ($sales['territories'] as $territory) {
                if (!is_array($territory)) throw new RuntimeException('Sales territory response is invalid.');
                $territories[] = [
                    'territory_key' => yovel_admin_operations_commercial_uuid($territory['territory_key'] ?? '', 'territory key'),
                    'territory_code' => yovel_admin_operations_commercial_text($territory['territory_code'] ?? '', 'territory code', 80),
                    'territory_name' => yovel_admin_operations_commercial_text($territory['territory_name'] ?? '', 'territory name', 180),
                    'territory_status' => yovel_admin_operations_commercial_status($territory['territory_status'] ?? '', 'territory status'),
                ];
            }
            $salespeople = [];
            foreach ($sales['salespersons'] as $salesperson) {
                if (!is_array($salesperson)) throw new RuntimeException('Sales person response is invalid.');
                $salespeople[] = [
                    'salesperson_key' => yovel_admin_operations_commercial_uuid($salesperson['salesperson_key'] ?? '', 'salesperson key'),
                    'salesperson_code' => yovel_admin_operations_commercial_text($salesperson['salesperson_code'] ?? '', 'salesperson code', 80),
                    'salesperson_name' => yovel_admin_operations_commercial_text($salesperson['salesperson_name'] ?? '', 'salesperson name', 180),
                    'salesperson_status' => yovel_admin_operations_commercial_status($salesperson['salesperson_status'] ?? '', 'salesperson status'),
                ];
            }
            foreach ($sales['customers'] as $customer) {
                if (!is_array($customer)) throw new RuntimeException('Sales customer response is invalid.');
                $contexts['customers'][] = [
                    'customer_key' => yovel_admin_operations_commercial_uuid($customer['customer_key'] ?? '', 'customer key'),
                    'customer_code' => yovel_admin_operations_commercial_text($customer['customer_code'] ?? '', 'customer code', 80),
                    'customer_name' => yovel_admin_operations_commercial_text($customer['customer_name'] ?? '', 'customer name', 180),
                    'customer_status' => yovel_admin_operations_commercial_status($customer['customer_status'] ?? '', 'customer status'),
                ];
            }
            $directories['sales_person'] = [
                'label' => 'Sales Person', 'status' => 'PARTIAL', 'blocking' => false, 'records' => $salespeople,
                'unavailable_fields' => ['parent_salesperson_key', 'employee_key', 'is_group', 'commission_rate'],
                'reason' => 'Sales exposes the active directory identity and status, but not hierarchy, employee linkage, group flag, or commission rate.',
            ];
            $directories['territory'] = [
                'label' => 'Territory', 'status' => 'PARTIAL', 'blocking' => false, 'records' => $territories,
                'unavailable_fields' => ['parent_territory_key', 'is_group', 'territory_manager_key'],
                'reason' => 'Sales exposes territory identity and status, but not parent hierarchy, group flag, or manager.',
            ];
            $directories['customer_group']['reason'] = 'Sales exposes customer identity and status only; no stable Customer Group key, directory, parent hierarchy, or customer-to-group relationship is available.';
            $sources['sales'] = yovel_admin_operations_commercial_source('Sales', 'yovel_admin_sales_crm_data', 'AVAILABLE');
        } catch (Throwable) {
            $contexts['customers'] = [];
            $sources['sales'] = yovel_admin_operations_commercial_source('Sales', 'yovel_admin_sales_crm_data', 'UNAVAILABLE_DEPENDENCY', 'Sales commercial records are unavailable or invalid.');
        }
    } else {
        $sources['sales'] = yovel_admin_operations_commercial_source('Sales', 'yovel_admin_sales_crm_data', 'UNAVAILABLE_DEPENDENCY', 'Sales commercial service is unavailable.');
    }

    if (function_exists('yovel_admin_buying_suppliers')) {
        try {
            $suppliers = yovel_admin_buying_suppliers($company);
            if (!is_array($suppliers)) throw new RuntimeException('Buying supplier response is invalid.');
            $groupReferences = [];
            foreach ($suppliers as $supplier) {
                if (!is_array($supplier)) throw new RuntimeException('Buying supplier response is invalid.');
                $groupKey = yovel_admin_operations_commercial_uuid($supplier['supplier_group_key'] ?? '', 'supplier group key', false);
                if ($groupKey !== '') $groupReferences[$groupKey] = ['supplier_group_key' => $groupKey];
            }
            ksort($groupReferences);
            $directories['supplier_group'] = [
                'label' => 'Supplier Group', 'status' => 'PARTIAL', 'blocking' => false, 'records' => array_values($groupReferences),
                'unavailable_fields' => ['supplier_group_code', 'supplier_group_name', 'parent_supplier_group_key', 'is_group', 'supplier_group_status'],
                'reason' => 'Buying exposes supplier-group keys on suppliers, but not the Supplier Group directory, labels, hierarchy, or status.',
            ];
            $sources['buying'] = yovel_admin_operations_commercial_source('Buying', 'yovel_admin_buying_suppliers', 'AVAILABLE');
        } catch (Throwable) {
            $sources['buying'] = yovel_admin_operations_commercial_source('Buying', 'yovel_admin_buying_suppliers', 'UNAVAILABLE_DEPENDENCY', 'Buying supplier records are unavailable or invalid.');
        }
    } else {
        $sources['buying'] = yovel_admin_operations_commercial_source('Buying', 'yovel_admin_buying_suppliers', 'UNAVAILABLE_DEPENDENCY', 'Buying supplier service is unavailable.');
    }

    if (function_exists('yovel_admin_inventory_items')) {
        try {
            $items = yovel_admin_inventory_items($company, ['status' => 'ACTIVE']);
            if (!is_array($items)) throw new RuntimeException('Inventory item response is invalid.');
            foreach ($items as $item) {
                if (!is_array($item)) throw new RuntimeException('Inventory item response is invalid.');
                $contexts['items'][] = [
                    'item_key' => yovel_admin_operations_commercial_uuid($item['item_key'] ?? '', 'item key'),
                    'item_code' => yovel_admin_operations_commercial_text($item['item_code'] ?? '', 'item code', 80),
                    'item_name' => yovel_admin_operations_commercial_text($item['item_name'] ?? '', 'item name', 180),
                    'item_status' => yovel_admin_operations_commercial_status($item['item_status'] ?? '', 'item status'),
                ];
            }
            $sources['inventory'] = yovel_admin_operations_commercial_source('Inventory', 'yovel_admin_inventory_items', 'AVAILABLE');
        } catch (Throwable) {
            $contexts['items'] = [];
            $sources['inventory'] = yovel_admin_operations_commercial_source('Inventory', 'yovel_admin_inventory_items', 'UNAVAILABLE_DEPENDENCY', 'Inventory item references are unavailable or invalid.');
        }
    } else {
        $sources['inventory'] = yovel_admin_operations_commercial_source('Inventory', 'yovel_admin_inventory_items', 'UNAVAILABLE_DEPENDENCY', 'Inventory item service is unavailable.');
    }

    if (function_exists('yovel_admin_operations_workforce_calendar_projection')) {
        $workforce = yovel_admin_operations_workforce_calendar_projection($company, ['status' => 'ALL']);
        if (($workforce['contract_status'] ?? '') === 'AVAILABLE') {
            $contexts['employees'] = is_array($workforce['directories']['employee']['records'] ?? null) ? $workforce['directories']['employee']['records'] : [];
            $sources['hr'] = yovel_admin_operations_commercial_source('HR', 'yovel_admin_hr_workforce_read_contract', 'AVAILABLE');
        } else {
            $sources['hr'] = yovel_admin_operations_commercial_source('HR', 'yovel_admin_hr_workforce_read_contract', 'UNAVAILABLE_DEPENDENCY', 'HR workforce references are unavailable or invalid.');
        }
    } else {
        $sources['hr'] = yovel_admin_operations_commercial_source('HR', 'yovel_admin_hr_workforce_read_contract', 'UNAVAILABLE_DEPENDENCY', 'HR workforce adapter is unavailable.');
    }

    if (is_callable($providers['shared.record-type-directory.v1'] ?? null)) {
        try {
            $shared = yovel_admin_operations_call_read_contract('shared.record-type-directory.v1', $company, ['directory' => 'party-types'], $providers);
            $partyTypes = [];
            foreach ($shared['records'] as $record) {
                if (!is_array($record)) throw new RuntimeException('Shared Party Type response is invalid.');
                $partyTypes[] = [
                    'record_type_key' => yovel_admin_operations_commercial_uuid($record['record_type_key'] ?? '', 'record type key'),
                    'record_type_code' => strtoupper(yovel_admin_operations_commercial_text($record['record_type_code'] ?? '', 'record type code', 80)),
                    'record_type_label' => yovel_admin_operations_commercial_text($record['record_type_label'] ?? '', 'record type label', 180),
                    'record_type_status' => yovel_admin_operations_commercial_status($record['record_type_status'] ?? '', 'record type status'),
                ];
            }
            $directories['party_type'] = [
                'label' => 'Party Type', 'status' => 'PARTIAL', 'blocking' => false, 'records' => $partyTypes,
                'unavailable_fields' => ['account_type', 'owner_module'],
                'reason' => 'Shared exposes stable record-type identity and status, but not Finance account classification or authoritative owner metadata.',
            ];
            $sources['shared'] = yovel_admin_operations_commercial_source('Shared', 'shared.record-type-directory.v1', 'AVAILABLE');
        } catch (Throwable) {
            $sources['shared'] = yovel_admin_operations_commercial_source('Shared', 'shared.record-type-directory.v1', 'UNAVAILABLE_DEPENDENCY', 'Shared Party Type records are unavailable or invalid.');
        }
    } else {
        $sources['shared'] = yovel_admin_operations_commercial_source('Shared', 'shared.record-type-directory.v1', 'UNAVAILABLE_DEPENDENCY', 'Shared record-type provider is unavailable.');
    }

    $directories['incoterm']['reason'] = 'Neither Sales nor Buying currently exposes a verified Incoterm directory or applicability fields.';
    $directories['quotation_lost_reason']['reason'] = 'Sales currently exposes Leads, customers, salespeople, and territories, but no Quotation Lost Reason directory.';
    $directories['quotation_lost_reason_detail']['reason'] = 'Sales currently exposes no quotation-to-lost-reason detail service or stable detail keys.';
    $directories['sales_partner']['reason'] = 'Sales currently exposes salespeople but no Sales Partner owner service, commission fields, or territory linkage.';
    $directories['target_detail']['reason'] = 'Inventory item and HR employee context may be available, but no owner exposes target periods, quantities, amounts, item groups, or distributions.';
    $directories['terms_and_conditions']['reason'] = 'Sales, Buying, and Shared currently expose no verified Terms and Conditions body, applicability, lifecycle, or attachment-reference service.';

    return [
        'company_key_hash' => $companyKeyHash,
        'sources' => $sources,
        'directories' => $directories,
        'contexts' => $contexts,
        'related_owner_gaps' => [
            'customer_contact_address' => ['status' => 'UNAVAILABLE_DEPENDENCY', 'fields' => ['customer_group_key', 'contact_keys', 'address_keys'], 'message' => 'No verified customer/contact/address directory joins are available.'],
            'opportunity_order' => ['status' => 'UNAVAILABLE_DEPENDENCY', 'fields' => ['opportunity_key', 'quotation_key', 'sales_order_key'], 'message' => 'No verified opportunity, quotation, or sales-order projection service is available.'],
        ],
        'owner_actions' => yovel_admin_operations_commercial_owner_actions(),
    ];
}
