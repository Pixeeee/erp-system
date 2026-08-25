<?php
declare(strict_types=1);

function yovel_admin_finance_invoice_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_finance_supplier (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            supplier_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            supplier_code VARCHAR(80) NOT NULL,
            supplier_name VARCHAR(200) NOT NULL,
            supplier_tin VARCHAR(30) NULL,
            supplier_address VARCHAR(500) NULL,
            supplier_status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_supplier (company_key_hash, supplier_code),
            INDEX idx_project_company_finance_supplier_status (company_key_hash, supplier_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_invoice (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            document_type ENUM('SALES','PURCHASE') NOT NULL,
            invoice_no VARCHAR(120) NOT NULL,
            is_return TINYINT(1) NOT NULL DEFAULT 0,
            return_against_invoice_key CHAR(36) NULL,
            party_key CHAR(36) NULL,
            party_code VARCHAR(80) NULL,
            party_name VARCHAR(200) NOT NULL,
            party_tin VARCHAR(30) NULL,
            party_address VARCHAR(500) NULL,
            supplier_reference VARCHAR(120) NULL,
            posting_date DATE NOT NULL,
            due_date DATE NOT NULL,
            currency VARCHAR(20) NOT NULL DEFAULT 'PHP',
            exchange_rate DECIMAL(20,8) NOT NULL DEFAULT 1,
            party_account_key CHAR(36) NOT NULL,
            document_status ENUM('DRAFT','SUBMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            net_total DECIMAL(20,6) NOT NULL DEFAULT 0,
            taxable_sales DECIMAL(20,6) NOT NULL DEFAULT 0,
            government_sales DECIMAL(20,6) NOT NULL DEFAULT 0,
            zero_rated_sales DECIMAL(20,6) NOT NULL DEFAULT 0,
            exempt_sales DECIMAL(20,6) NOT NULL DEFAULT 0,
            out_of_scope_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            vat_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            creditable_input_vat DECIMAL(20,6) NOT NULL DEFAULT 0,
            non_creditable_input_vat DECIMAL(20,6) NOT NULL DEFAULT 0,
            creditable_vat_withheld DECIMAL(20,6) NOT NULL DEFAULT 0,
            grand_total DECIMAL(20,6) NOT NULL DEFAULT 0,
            outstanding_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            remarks VARCHAR(1000) NULL,
            gl_transaction_key CHAR(36) NULL,
            reversal_transaction_key CHAR(36) NULL,
            submitted_by_admin_key CHAR(36) NULL,
            submitted_at TIMESTAMP NULL,
            cancelled_by_admin_key CHAR(36) NULL,
            cancelled_at TIMESTAMP NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_invoice_no (company_key_hash, document_type, invoice_no),
            INDEX idx_project_company_finance_invoice_party (company_key_hash, document_type, party_key, document_status),
            INDEX idx_project_company_finance_invoice_due (company_key_hash, document_type, document_status, due_date),
            INDEX idx_project_company_finance_invoice_return (company_key_hash, return_against_invoice_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_invoice_line (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_line_key CHAR(36) NOT NULL UNIQUE,
            invoice_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            line_no INT UNSIGNED NOT NULL,
            description VARCHAR(500) NOT NULL,
            quantity DECIMAL(20,6) NOT NULL,
            unit_amount DECIMAL(20,6) NOT NULL,
            discount_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            account_key CHAR(36) NOT NULL,
            cost_center VARCHAR(180) NULL,
            project VARCHAR(180) NULL,
            tax_code VARCHAR(80) NOT NULL,
            bir_classification VARCHAR(40) NOT NULL,
            taxable_base DECIMAL(20,6) NOT NULL DEFAULT 0,
            vat_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            line_total DECIMAL(20,6) NOT NULL DEFAULT 0,
            price_inclusive TINYINT(1) NOT NULL DEFAULT 0,
            tax_snapshot_json LONGTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_invoice_line (company_key_hash, invoice_key, line_no),
            INDEX idx_project_company_finance_invoice_line_account (company_key_hash, account_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_invoice_tax (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_tax_key CHAR(36) NOT NULL UNIQUE,
            invoice_key CHAR(36) NOT NULL,
            invoice_line_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            tax_code_key CHAR(36) NULL,
            tax_code VARCHAR(80) NOT NULL,
            tax_name VARCHAR(180) NOT NULL,
            tax_kind VARCHAR(40) NOT NULL,
            bir_classification VARCHAR(40) NOT NULL,
            rate DECIMAL(12,6) NOT NULL DEFAULT 0,
            taxable_base DECIMAL(20,6) NOT NULL DEFAULT 0,
            vat_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            creditable_input_vat DECIMAL(20,6) NOT NULL DEFAULT 0,
            non_creditable_input_vat DECIMAL(20,6) NOT NULL DEFAULT 0,
            creditable_vat_withheld DECIMAL(20,6) NOT NULL DEFAULT 0,
            tax_snapshot_json LONGTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_company_finance_invoice_tax_report (company_key_hash, bir_classification, invoice_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_payment_term (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_term_key CHAR(36) NOT NULL UNIQUE,
            invoice_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            term_no INT UNSIGNED NOT NULL,
            due_date DATE NOT NULL,
            due_amount DECIMAL(20,6) NOT NULL,
            description VARCHAR(180) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_payment_term (company_key_hash, invoice_key, term_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_payment_terms_template (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_terms_template_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            template_code VARCHAR(80) NOT NULL,
            template_name VARCHAR(180) NOT NULL,
            template_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_finance_payment_terms_template (company_key_hash, template_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_payment_terms_template_detail (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_terms_template_detail_key CHAR(36) NOT NULL UNIQUE,
            payment_terms_template_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            term_no INT UNSIGNED NOT NULL,
            credit_days INT UNSIGNED NOT NULL DEFAULT 0,
            invoice_portion DECIMAL(9,6) NOT NULL,
            discount_percentage DECIMAL(9,6) NOT NULL DEFAULT 0,
            discount_days INT UNSIGNED NOT NULL DEFAULT 0,
            description VARCHAR(180) NULL,
            UNIQUE KEY uq_finance_payment_terms_template_detail (company_key_hash, payment_terms_template_key, term_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_invoice_advance (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_advance_key CHAR(36) NOT NULL UNIQUE,
            invoice_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            reference_type ENUM('PAYMENT_ENTRY','JOURNAL_ENTRY') NOT NULL,
            reference_key CHAR(36) NOT NULL,
            allocated_amount DECIMAL(20,6) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_finance_invoice_advance (company_key_hash, invoice_key, reference_type, reference_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_invoice_reference (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_reference_key CHAR(36) NOT NULL UNIQUE,
            invoice_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            reference_type ENUM('ITEM','STOCK','ASSET','PRICING','TIMESHEET','SALES_REFERENCE','SUBSCRIPTION') NOT NULL,
            reference_key CHAR(36) NOT NULL,
            reference_snapshot_json LONGTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_finance_invoice_reference (company_key_hash, invoice_key, reference_type, reference_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_dunning_type (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dunning_type_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            dunning_type_code VARCHAR(80) NOT NULL,
            dunning_type_name VARCHAR(180) NOT NULL,
            days_overdue INT UNSIGNED NOT NULL,
            interest_rate DECIMAL(9,6) NOT NULL DEFAULT 0,
            fee_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            letter_text TEXT NOT NULL,
            dunning_type_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_finance_dunning_type (company_key_hash, dunning_type_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_dunning (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dunning_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            invoice_key CHAR(36) NOT NULL,
            dunning_type_key CHAR(36) NOT NULL,
            dunning_date DATE NOT NULL,
            overdue_amount DECIMAL(20,6) NOT NULL,
            interest_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            fee_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            total_amount DECIMAL(20,6) NOT NULL,
            letter_snapshot TEXT NOT NULL,
            dunning_status ENUM('DRAFT','ISSUED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_finance_dunning (company_key_hash, invoice_key, dunning_type_key, dunning_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_statement_process (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            statement_process_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            statement_date DATE NOT NULL,
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            aging_basis ENUM('DUE_DATE','POSTING_DATE') NOT NULL DEFAULT 'DUE_DATE',
            process_status ENUM('DRAFT','PROCESSED','CANCELLED') NOT NULL DEFAULT 'PROCESSED',
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_statement_customer (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            statement_customer_key CHAR(36) NOT NULL UNIQUE,
            statement_process_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            customer_key CHAR(36) NOT NULL,
            customer_name VARCHAR(200) NOT NULL,
            opening_balance DECIMAL(20,6) NOT NULL DEFAULT 0,
            invoiced_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            outstanding_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
            statement_snapshot_json LONGTEXT NOT NULL,
            UNIQUE KEY uq_finance_statement_customer (company_key_hash, statement_process_key, customer_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_statement_cc (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            statement_cc_key CHAR(36) NOT NULL UNIQUE,
            statement_process_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            email_address VARCHAR(254) NOT NULL,
            UNIQUE KEY uq_finance_statement_cc (company_key_hash, statement_process_key, email_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_invoice_discounting (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_discounting_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            invoice_key CHAR(36) NOT NULL,
            discount_date DATE NOT NULL,
            lender_reference VARCHAR(180) NOT NULL,
            discounted_amount DECIMAL(20,6) NOT NULL,
            discount_charge DECIMAL(20,6) NOT NULL DEFAULT 0,
            net_proceeds DECIMAL(20,6) NOT NULL,
            discounting_status ENUM('DRAFT','SUBMITTED','SETTLED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_finance_invoice_discounting (company_key_hash, invoice_key, lender_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_opening_invoice_batch (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            opening_invoice_batch_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            batch_reference VARCHAR(120) NOT NULL,
            row_count INT UNSIGNED NOT NULL,
            total_amount DECIMAL(20,6) NOT NULL,
            batch_status ENUM('DRAFT','CREATED','CANCELLED') NOT NULL DEFAULT 'CREATED',
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_finance_opening_invoice_batch (company_key_hash, batch_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_opening_invoice_item (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            opening_invoice_item_key CHAR(36) NOT NULL UNIQUE,
            opening_invoice_batch_key CHAR(36) NOT NULL,
            invoice_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            row_no INT UNSIGNED NOT NULL,
            opening_amount DECIMAL(20,6) NOT NULL,
            UNIQUE KEY uq_finance_opening_invoice_item (company_key_hash, opening_invoice_batch_key, row_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Finance Invoice schema update');
    }
    foreach ([
        ['base_currency', "VARCHAR(20) NOT NULL DEFAULT 'PHP'"],
        ['base_net_total', 'DECIMAL(20,6) NOT NULL DEFAULT 0'],
        ['base_vat_amount', 'DECIMAL(20,6) NOT NULL DEFAULT 0'],
        ['base_grand_total', 'DECIMAL(20,6) NOT NULL DEFAULT 0'],
        ['base_outstanding_amount', 'DECIMAL(20,6) NOT NULL DEFAULT 0'],
        ['additional_discount_amount', 'DECIMAL(20,6) NOT NULL DEFAULT 0'],
        ['discount_application', "ENUM('BEFORE_TAX','AFTER_TAX') NOT NULL DEFAULT 'BEFORE_TAX'"],
        ['payment_terms_template_key', 'CHAR(36) NULL'],
        ['advance_paid', 'DECIMAL(20,6) NOT NULL DEFAULT 0'],
        ['is_opening', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['amended_from_invoice_key', 'CHAR(36) NULL'],
        ['amendment_index', 'INT UNSIGNED NOT NULL DEFAULT 0'],
        ['immutable_snapshot_json', 'LONGTEXT NULL'],
        ['immutable_snapshot_sha256', 'CHAR(64) NULL'],
    ] as [$column, $definition]) {
        yovel_admin_finance_ensure_column($db, 'project_company_finance_invoice', $column, $definition);
    }
    foreach ([
        ['item_reference_key', 'CHAR(36) NULL'],
        ['asset_reference_key', 'CHAR(36) NULL'],
        ['stock_reference_key', 'CHAR(36) NULL'],
        ['pricing_reference_key', 'CHAR(36) NULL'],
        ['timesheet_reference_key', 'CHAR(36) NULL'],
        ['reference_snapshot_json', 'LONGTEXT NULL'],
    ] as [$column, $definition]) {
        yovel_admin_finance_ensure_column($db, 'project_company_finance_invoice_line', $column, $definition);
    }
}

function yovel_admin_finance_invoice_type(string $type): string
{
    $type = strtoupper(trim($type));
    if (!in_array($type, ['SALES', 'PURCHASE'], true)) {
        throw new InvalidArgumentException('Finance Invoice type is invalid.');
    }
    return $type;
}

function yovel_admin_finance_invoice_dependency_gateway(array $overrides = []): array
{
    $definitions = [
        'sales-crm.customer-reference.v1' => 'yovel_admin_sales_crm_customer_reference',
        'buying-procurement.supplier-reference.v1' => 'yovel_admin_buying_supplier',
        'inventory.item-reference.v1' => 'yovel_admin_inventory_item',
        'inventory.invoice-stock-posting.v1' => 'yovel_admin_inventory_finance_invoice_posting_request',
        'assets.invoice-reference.v1' => 'yovel_admin_assets_finance_invoice_reference',
        'sales-crm.pricing-reference.v1' => 'yovel_admin_sales_pricing_reference',
        'hr.timesheet-reference.v1' => 'yovel_admin_hr_timesheet_reference',
        'sales-crm.sales-invoice-reference.v1' => 'yovel_admin_sales_invoice_reference',
        'sales-crm.subscription-reference.v1' => 'yovel_admin_sales_subscription_reference',
    ];
    foreach ($overrides as $contract => $service) {
        if (!array_key_exists($contract, $definitions)) {
            throw new InvalidArgumentException('Finance Invoice dependency override is not allow-listed.');
        }
        if (!is_callable($service)) {
            throw new InvalidArgumentException('Finance Invoice dependency override must be callable.');
        }
    }
    $published = is_array($GLOBALS['yovel_admin_finance_invoice_dependency_providers'] ?? null)
        ? $GLOBALS['yovel_admin_finance_invoice_dependency_providers']
        : [];
    $gateway = [];
    foreach ($definitions as $contract => $ownerFunction) {
        $callable = $overrides[$contract] ?? $published[$contract] ?? (function_exists($ownerFunction) ? $ownerFunction : null);
        $gateway[$contract] = [
            'contract' => $contract,
            'owner_function' => $ownerFunction,
            'available' => is_callable($callable),
            'callable' => is_callable($callable) ? $callable : null,
        ];
    }
    return $gateway;
}

function yovel_admin_finance_invoice_owner_record(array $company, string $contract, string $key, array $services): array
{
    if (!yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Finance Invoice owner reference is invalid.');
    }
    $service = $services[$contract]['callable'] ?? null;
    if (!is_callable($service)) {
        throw new LogicException('UNAVAILABLE_DEPENDENCY: ' . $contract);
    }
    $record = $service($company, $key);
    if (!is_array($record) || $record === []) {
        throw new InvalidArgumentException('Finance Invoice owner reference was not found.');
    }
    $scope = (string) ($record['company_key_hash'] ?? $company['company_key_hash'] ?? '');
    if (!hash_equals((string) ($company['company_key_hash'] ?? ''), $scope)) {
        throw new InvalidArgumentException('Finance Invoice owner reference belongs to another company.');
    }
    return $record;
}

function yovel_admin_finance_invoice_payment_schedule(string $postingDate, string $grandTotal, array $rows): array
{
    $postingDate = yovel_admin_optional_date($postingDate, 'Invoice posting date');
    $grandTotal = yovel_admin_finance_money($grandTotal);
    if ($postingDate === '' || $rows === [] || count($rows) > 100) {
        throw new InvalidArgumentException('Payment Schedule must contain between 1 and 100 terms.');
    }
    $result = [];
    $portionTotal = '0.000000';
    $amountTotal = '0.000000';
    foreach (array_values($rows) as $index => $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Payment Schedule rows must be structured.');
        }
        $dueDate = yovel_admin_optional_date((string) ($row['due_date'] ?? ''), 'Payment Schedule due date');
        if ($dueDate === '' || $dueDate < $postingDate) {
            throw new InvalidArgumentException('Payment Schedule due date cannot precede the posting date.');
        }
        $portion = yovel_admin_finance_money($row['invoice_portion'] ?? '0');
        $dueAmount = trim((string) ($row['due_amount'] ?? '')) !== ''
            ? yovel_admin_finance_money($row['due_amount'])
            : yovel_admin_finance_money(bcdiv(bcmul($grandTotal, $portion, 6), '100', 6));
        if (bccomp($portion, '0', 6) !== 1 || bccomp(yovel_admin_finance_abs($dueAmount), '0', 6) !== 1) {
            throw new InvalidArgumentException('Payment Schedule portions and amounts must be positive.');
        }
        $portionTotal = bcadd($portionTotal, $portion, 6);
        $amountTotal = bcadd($amountTotal, $dueAmount, 6);
        $result[] = [
            'term_no' => $index + 1,
            'due_date' => $dueDate,
            'invoice_portion' => $portion,
            'due_amount' => $dueAmount,
            'description' => substr(trim((string) ($row['description'] ?? '')), 0, 180),
        ];
    }
    if (bccomp($portionTotal, '100.000000', 6) !== 0) {
        throw new InvalidArgumentException('Payment Schedule invoice portions must total 100 percent.');
    }
    if (bccomp($amountTotal, $grandTotal, 6) !== 0) {
        $last = count($result) - 1;
        $result[$last]['due_amount'] = yovel_admin_finance_money(bcadd($result[$last]['due_amount'], bcsub($grandTotal, $amountTotal, 6), 6));
    }
    return $result;
}

function yovel_admin_finance_invoice_advances(string $grandTotal, array $rows): array
{
    if (count($rows) > 100) {
        throw new InvalidArgumentException('Invoice advances exceed the supported row limit.');
    }
    $result = [];
    $seen = [];
    $total = '0.000000';
    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Invoice advances must be structured rows.');
        }
        $paymentKey = trim((string) ($row['payment_entry_key'] ?? ''));
        $journalKey = trim((string) ($row['journal_entry_key'] ?? ''));
        if (($paymentKey === '') === ($journalKey === '')) {
            throw new InvalidArgumentException('Each advance requires exactly one Payment or Journal Entry reference.');
        }
        $type = $paymentKey !== '' ? 'PAYMENT_ENTRY' : 'JOURNAL_ENTRY';
        $key = $paymentKey !== '' ? $paymentKey : $journalKey;
        $amount = yovel_admin_finance_money($row['allocated_amount'] ?? '0');
        if (!yovel_admin_is_uuid($key) || bccomp($amount, '0', 6) !== 1 || isset($seen[$type . ':' . $key])) {
            throw new InvalidArgumentException('Invoice advance reference or amount is invalid.');
        }
        $seen[$type . ':' . $key] = true;
        $total = bcadd($total, $amount, 6);
        $result[] = ['invoice_advance_key' => bx_uuid(), 'reference_type' => $type, 'reference_key' => $key, 'allocated_amount' => $amount];
    }
    if (bccomp($total, yovel_admin_finance_abs($grandTotal), 6) === 1) {
        throw new InvalidArgumentException('Invoice advances cannot exceed the document total.');
    }
    return ['rows' => $result, 'total' => yovel_admin_finance_money($total)];
}

function yovel_admin_finance_invoice_references(array $company, array $rows, array $services): array
{
    if (count($rows) > 200) throw new InvalidArgumentException('Invoice references exceed the supported row limit.');
    $contracts = [
        'SALES_REFERENCE' => 'sales-crm.sales-invoice-reference.v1',
        'SUBSCRIPTION' => 'sales-crm.subscription-reference.v1',
        'TIMESHEET' => 'hr.timesheet-reference.v1',
        'ITEM' => 'inventory.item-reference.v1',
        'ASSET' => 'assets.invoice-reference.v1',
        'PRICING' => 'sales-crm.pricing-reference.v1',
    ];
    $result=[];$seen=[];
    foreach($rows as $row){if(!is_array($row))throw new InvalidArgumentException('Invoice references must be structured rows.');$type=strtoupper(trim((string)($row['reference_type']??'')));$key=trim((string)($row['reference_key']??''));if(!isset($contracts[$type])||isset($seen[$type.':'.$key]))throw new InvalidArgumentException('Invoice reference type or identity is invalid.');$snapshot=yovel_admin_finance_invoice_owner_record($company,$contracts[$type],$key,$services);$seen[$type.':'.$key]=true;$result[]=['invoice_reference_key'=>bx_uuid(),'reference_type'=>$type,'reference_key'=>$key,'reference_snapshot_json'=>yovel_admin_finance_canonical_json($snapshot)];}
    return $result;
}

function yovel_admin_finance_canonical_value(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (!array_is_list($value)) {
        ksort($value, SORT_STRING);
    }
    foreach ($value as $key => $item) {
        $value[$key] = yovel_admin_finance_canonical_value($item);
    }
    return $value;
}

function yovel_admin_finance_canonical_json(array $value): string
{
    return json_encode(yovel_admin_finance_canonical_value($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function yovel_admin_finance_purchase_tax_meta(array $company, string $taxCode, string $transactionDate): array
{
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $taxCode = yovel_admin_code($taxCode);
    $row = null;
    $tableExists = (int) bx_db()->GetOne(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
        ['project_company_finance_tax_code']
    ) === 1;
    if ($tableExists) {
        $row = bx_db()->GetRow(
            "SELECT * FROM project_company_finance_tax_code
              WHERE company_key_hash=? AND tax_code=? AND status='ACTIVE' AND effective_from<=?
                AND (effective_to IS NULL OR effective_to>=?)
              ORDER BY effective_from DESC,x_id DESC LIMIT 1",
            [$companyKeyHash, $taxCode, $transactionDate, $transactionDate]
        );
    }
    if (!is_array($row) || $row === []) {
        $row = yovel_admin_ph_vat_builtin_codes()[$taxCode] ?? null;
    }
    if (!is_array($row)) {
        throw new InvalidArgumentException('No active Purchase Tax Code applies on the transaction date.');
    }
    return yovel_admin_ph_vat_tax_meta($row);
}

function yovel_admin_finance_calculate_purchase_document(array $company, array $payload): array
{
    [, $companyKeyHash] = yovel_admin_finance_dependency_read_scope($company);
    $requestedHash = strtolower(trim((string) ($payload['company_key_hash'] ?? $companyKeyHash)));
    if (!hash_equals($companyKeyHash, $requestedHash)) {
        throw new InvalidArgumentException('Purchase calculation company scope does not match Finance.');
    }

    $documentType = strtoupper(trim((string) ($payload['document_type'] ?? 'PURCHASE_DOCUMENT')));
    if ($documentType === '' || strlen($documentType) > 80 || preg_match('/^[A-Z][A-Z0-9_]*$/', $documentType) !== 1) {
        throw new InvalidArgumentException('Purchase document type is invalid.');
    }
    $transactionDate = yovel_admin_optional_date((string) ($payload['transaction_date'] ?? ''), 'Purchase transaction date');
    if ($transactionDate === '') {
        throw new InvalidArgumentException('Purchase transaction date is required.');
    }
    $baseCurrency = strtoupper(trim((string) bx_db()->GetOne(
        'SELECT base_currency FROM project_company_finance_setting WHERE company_key_hash=? LIMIT 1',
        [$companyKeyHash]
    )));
    if (preg_match('/^[A-Z]{3}$/', $baseCurrency) !== 1) {
        $baseCurrency = 'PHP';
    }
    $currency = strtoupper(trim((string) ($payload['currency'] ?? $baseCurrency)));
    $conversionRate = yovel_admin_finance_money($payload['conversion_rate'] ?? $payload['exchange_rate'] ?? '1', 8);
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1 || bccomp($conversionRate, '0', 8) !== 1) {
        throw new InvalidArgumentException('Purchase currency or conversion rate is invalid.');
    }

    $inputItems = $payload['items'] ?? [];
    if (!is_array($inputItems) || $inputItems === [] || count($inputItems) > 1000) {
        throw new InvalidArgumentException('Purchase calculation requires between 1 and 1000 items.');
    }
    $prepared = [];
    $identities = [];
    foreach (array_values($inputItems) as $index => $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Purchase calculation items must be structured rows.');
        }
        $lineNo = (int) ($item['line_no'] ?? ($index + 1));
        $itemKey = strtolower(trim((string) ($item['inventory_item_key'] ?? '')));
        $uomKey = trim((string) ($item['uom_key'] ?? ''));
        if ($lineNo !== $index + 1 || !yovel_admin_is_uuid($itemKey) || $uomKey === '' || strlen($uomKey) > 80) {
            throw new InvalidArgumentException('Purchase calculation item identity is invalid.');
        }
        $quantity = yovel_admin_finance_money($item['quantity'] ?? '0');
        $rate = yovel_admin_finance_money($item['rate'] ?? '0');
        $discountPercentage = yovel_admin_finance_money($item['discount_percentage'] ?? '0', 4);
        if (bccomp($quantity, '0', 6) === -1 || bccomp($rate, '0', 6) === -1 || bccomp($discountPercentage, '0', 4) === -1 || bccomp($discountPercentage, '100', 4) === 1) {
            throw new InvalidArgumentException('Purchase quantity, rate, and discount must be non-negative and bounded.');
        }
        $gross = bcmul($quantity, $rate, 12);
        $lineDiscount = yovel_admin_finance_money(bcdiv(bcmul($gross, $discountPercentage, 12), '100', 12));
        $taxInput = $item['tax_meta'] ?? null;
        if (!is_array($taxInput)) {
            $taxInput = yovel_admin_finance_purchase_tax_meta(
                $company,
                (string) ($item['tax_code'] ?? $payload['tax_code'] ?? 'INPUT_GOODS_VAT12'),
                $transactionDate
            );
        }
        $prepared[] = [
            'description' => substr(trim((string) ($item['description'] ?? $itemKey)), 0, 500),
            'quantity' => $quantity,
            'unit_amount' => $rate,
            'discount_amount' => $lineDiscount,
            'tax_meta' => $taxInput,
            'price_inclusive' => !empty($item['price_inclusive']),
        ];
        $identities[] = [
            'line_no' => $lineNo,
            'inventory_item_key' => $itemKey,
            'uom_key' => $uomKey,
            'quantity' => $quantity,
            'rate' => $rate,
            'discount_percentage' => $discountPercentage,
        ];
    }

    $additionalDiscount = yovel_admin_finance_money($payload['additional_discount_amount'] ?? '0');
    $prepared = yovel_admin_finance_invoice_apply_discount($prepared, $additionalDiscount);
    $calculated = yovel_admin_ph_vat_calculate_document($prepared, false);
    $items = [];
    $taxesByCode = [];
    foreach ($calculated['lines'] as $index => $line) {
        $items[] = $identities[$index] + [
            'discount_amount' => (string) $line['discount_amount'],
            'net_amount' => (string) $line['net_amount'],
            'tax_amount' => (string) $line['vat_amount'],
            'gross_amount' => (string) $line['line_total'],
            'tax_code' => (string) $line['tax_code'],
            'tax_snapshot' => $line['tax_snapshot'],
        ];
        $taxCode = (string) $line['tax_code'];
        if (!isset($taxesByCode[$taxCode])) {
            $taxesByCode[$taxCode] = [
                'tax_code' => $taxCode,
                'rate' => (string) $line['tax_snapshot']['rate'],
                'amount' => '0.000000',
            ];
        }
        $taxesByCode[$taxCode]['amount'] = yovel_admin_finance_money(bcadd($taxesByCode[$taxCode]['amount'], (string) $line['vat_amount'], 6));
    }
    ksort($taxesByCode, SORT_STRING);
    $netTotal = (string) $calculated['net_amount'];
    $taxTotal = (string) $calculated['vat_amount'];
    $grandTotal = (string) $calculated['grand_total'];
    $result = [
        'ok' => true,
        'contract' => 'finance.purchase-document-calculation.v1',
        'company_key_hash' => $companyKeyHash,
        'document_type' => $documentType,
        'transaction_date' => $transactionDate,
        'currency' => $currency,
        'base_currency' => $baseCurrency,
        'conversion_rate' => $conversionRate,
        'additional_discount_amount' => $additionalDiscount,
        'net_total' => $netTotal,
        'tax_total' => $taxTotal,
        'grand_total' => $grandTotal,
        'base_net_total' => yovel_admin_finance_money(bcmul($netTotal, $conversionRate, 6)),
        'base_tax_total' => yovel_admin_finance_money(bcmul($taxTotal, $conversionRate, 6)),
        'base_grand_total' => yovel_admin_finance_money(bcmul($grandTotal, $conversionRate, 6)),
        'items' => $items,
        'taxes' => array_values($taxesByCode),
        'errors' => [],
    ];
    $result['calculation_checksum'] = hash('sha256', yovel_admin_finance_canonical_json($result));
    return $result;
}

function yovel_admin_persist_finance_supplier(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_finance_invoice_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $key = trim((string) ($input['supplier_key'] ?? ''));
    $code = yovel_admin_code((string) ($input['supplier_code'] ?? ''));
    $name = trim((string) ($input['supplier_name'] ?? ''));
    $tin = trim((string) ($input['supplier_tin'] ?? ''));
    $address = trim((string) ($input['supplier_address'] ?? ''));
    if (($key !== '' && !yovel_admin_is_uuid($key)) || $code === '' || $name === '' || strlen($name) > 200 || strlen($address) > 500) {
        throw new InvalidArgumentException('Supplier code, name, and record identity must be valid.');
    }
    if ($tin !== '' && preg_match('/^\d{3}-\d{3}-\d{3}(?:-\d{3,5})?$/', $tin) !== 1) {
        throw new InvalidArgumentException('Supplier TIN must use the registered numeric format.');
    }
    if ($db->BeginTrans() === false) throw new RuntimeException('Supplier transaction could not start.');
    try {
        $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_finance_supplier WHERE company_key_hash=? AND supplier_key=? FOR UPDATE', [$hash, $key]) : $db->GetRow('SELECT * FROM project_company_finance_supplier WHERE company_key_hash=? AND supplier_code=? FOR UPDATE', [$hash, $code]);
        if ($key !== '' && !$existing && (int) $db->GetOne('SELECT COUNT(*) FROM project_company_finance_supplier WHERE supplier_key=?', [$key]) > 0) throw new InvalidArgumentException('Supplier does not belong to this company.');
        $key = $existing ? (string) $existing['supplier_key'] : ($key !== '' ? $key : bx_uuid());
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_supplier (supplier_key,company_key,company_key_hash,supplier_code,supplier_name,supplier_tin,supplier_address,supplier_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,'ACTIVE',?,?) ON DUPLICATE KEY UPDATE supplier_name=VALUES(supplier_name),supplier_tin=VALUES(supplier_tin),supplier_address=VALUES(supplier_address),supplier_status='ACTIVE',updated_by_admin_key=VALUES(updated_by_admin_key)", [$key, $companyKey, $hash, $code, $name, $tin !== '' ? $tin : null, $address !== '' ? $address : null, $adminKey, $adminKey], 'Finance Supplier save');
        $saved = $db->GetRow('SELECT * FROM project_company_finance_supplier WHERE company_key_hash=? AND supplier_key=?', [$hash, $key]);
        if (!$saved || (string) $saved['supplier_code'] !== $code) throw new RuntimeException('Supplier read-back verification failed.');
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_finance_supplier', $key, ['company_key' => $companyKey, 'supplier_code' => $code, 'admin_key' => $adminKey], 'Company administrator saved a Finance Supplier.');
        if ($db->CommitTrans() === false) throw new RuntimeException('Supplier transaction could not commit.');
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_finance_invoice_party(ADOConnection $db, array $company, string $type, array $input, array $services): array
{
    $partyKey = trim((string) ($input['party_key'] ?? ''));
    if (!yovel_admin_is_uuid($partyKey)) {
        throw new InvalidArgumentException(($type === 'SALES' ? 'Customer' : 'Supplier') . ' is required.');
    }
    if ($type === 'SALES') {
        $owned = yovel_admin_finance_invoice_owner_record($company, 'sales-crm.customer-reference.v1', $partyKey, $services);
        $row = [
            'party_key' => $owned['customer_key'] ?? '',
            'party_code' => $owned['customer_code'] ?? '',
            'party_name' => $owned['customer_name'] ?? '',
            'party_tin' => $owned['customer_tin'] ?? $owned['tax_id'] ?? '',
            'party_address' => $owned['customer_address'] ?? $owned['primary_address'] ?? '',
        ];
    } else {
        $owned = yovel_admin_finance_invoice_owner_record($company, 'buying-procurement.supplier-reference.v1', $partyKey, $services);
        $row = [
            'party_key' => $owned['supplier_key'] ?? '',
            'party_code' => $owned['supplier_code'] ?? '',
            'party_name' => $owned['supplier_name'] ?? '',
            'party_tin' => $owned['tax_id'] ?? $owned['supplier_tin'] ?? '',
            'party_address' => $owned['primary_address'] ?? $owned['supplier_address'] ?? '',
        ];
    }
    if (!yovel_admin_is_uuid((string) $row['party_key']) || trim((string) $row['party_name']) === '') {
        throw new InvalidArgumentException(($type === 'SALES' ? 'Customer' : 'Supplier') . ' must be active and belong to this company.');
    }
    $row['party_tin'] = trim((string) ($input['party_tin'] ?? ($row['party_tin'] ?? '')));
    $row['party_address'] = trim((string) ($input['party_address'] ?? ($row['party_address'] ?? '')));
    return $row;
}

function yovel_admin_finance_invoice_calculate_lines(ADOConnection $db, array $company, string $type, string $postingDate, bool $isReturn, array $lines, array $services = []): array
{
    if ($lines === [] || count($lines) > 1000) {
        throw new InvalidArgumentException('Finance Invoice must contain between 1 and 1000 lines.');
    }
    $hash = (string) $company['company_key_hash'];
    $prepared = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            throw new InvalidArgumentException('Finance Invoice lines must be structured rows.');
        }
        $description = trim((string) ($line['description'] ?? ''));
        if ($description === '' || strlen($description) > 500) {
            throw new InvalidArgumentException('Every Finance Invoice line requires a description.');
        }
        $accountKey = trim((string) ($line['account_key'] ?? ''));
        $root = $type === 'SALES' ? ['INCOME'] : ['EXPENSE', 'ASSET'];
        if (yovel_admin_finance_account_key($db, $hash, $accountKey, 'Invoice line account', $root) === null) {
            throw new InvalidArgumentException('Every Finance Invoice line requires a posting account.');
        }
        $taxInput = $line['tax_meta'] ?? null;
        if (!is_array($taxInput)) {
            $taxInput = yovel_admin_ph_vat_effective_code($company, (string) ($line['tax_code'] ?? 'OUT_OF_SCOPE'), $postingDate);
        }
        $referenceSnapshots = [];
        foreach ([
            'item_reference_key' => 'inventory.item-reference.v1',
            'stock_reference_key' => 'inventory.invoice-stock-posting.v1',
            'asset_reference_key' => 'assets.invoice-reference.v1',
            'pricing_reference_key' => 'sales-crm.pricing-reference.v1',
            'timesheet_reference_key' => 'hr.timesheet-reference.v1',
        ] as $field => $contract) {
            $referenceKey = trim((string) ($line[$field] ?? ''));
            if ($referenceKey !== '') {
                $referenceSnapshots[$field] = yovel_admin_finance_invoice_owner_record($company, $contract, $referenceKey, $services);
            }
        }
        $prepared[] = [
            'description' => $description,
            'quantity' => $line['quantity'] ?? '1',
            'unit_amount' => $line['unit_amount'] ?? $line['rate'] ?? '0',
            'discount_amount' => $line['discount_amount'] ?? '0',
            'tax_meta' => $taxInput,
            'price_inclusive' => !empty($line['price_inclusive']),
            'account_key' => $accountKey,
            'cost_center' => substr(trim((string) ($line['cost_center'] ?? '')), 0, 180),
            'project' => substr(trim((string) ($line['project'] ?? '')), 0, 180),
            'item_reference_key' => trim((string) ($line['item_reference_key'] ?? '')),
            'stock_reference_key' => trim((string) ($line['stock_reference_key'] ?? '')),
            'asset_reference_key' => trim((string) ($line['asset_reference_key'] ?? '')),
            'pricing_reference_key' => trim((string) ($line['pricing_reference_key'] ?? '')),
            'timesheet_reference_key' => trim((string) ($line['timesheet_reference_key'] ?? '')),
            'reference_snapshots' => $referenceSnapshots,
        ];
    }
    $calculated = yovel_admin_ph_vat_calculate_document($prepared, $isReturn);
    foreach ($calculated['lines'] as $index => &$line) {
        $line['invoice_line_key'] = bx_uuid();
        $line['account_key'] = $prepared[$index]['account_key'];
        $line['cost_center'] = $prepared[$index]['cost_center'];
        $line['project'] = $prepared[$index]['project'];
        foreach (['item_reference_key','stock_reference_key','asset_reference_key','pricing_reference_key','timesheet_reference_key','reference_snapshots'] as $field) {
            $line[$field] = $prepared[$index][$field];
        }
    }
    unset($line);
    return $calculated;
}

function yovel_admin_finance_invoice_apply_discount(array $lines, string $discount): array
{
    if (bccomp($discount, '0', 6) === 0) {
        return $lines;
    }
    if (bccomp($discount, '0', 6) === -1 || $lines === []) {
        throw new InvalidArgumentException('Invoice additional discount must be non-negative.');
    }
    $bases = [];
    $baseTotal = '0.000000';
    foreach ($lines as $index => $line) {
        if (!is_array($line)) {
            throw new InvalidArgumentException('Invoice discount requires structured lines.');
        }
        $gross = yovel_admin_finance_money(bcmul((string) ($line['quantity'] ?? '0'), (string) ($line['unit_amount'] ?? $line['rate'] ?? '0'), 6));
        $existing = yovel_admin_finance_money($line['discount_amount'] ?? '0');
        $base = yovel_admin_finance_money(bcsub($gross, $existing, 6));
        if (bccomp($base, '0', 6) !== 1) {
            throw new InvalidArgumentException('Invoice discount requires positive line bases.');
        }
        $bases[$index] = $base;
        $baseTotal = bcadd($baseTotal, $base, 6);
    }
    if (bccomp($discount, $baseTotal, 6) !== -1) {
        throw new InvalidArgumentException('Invoice additional discount must be less than the net line total.');
    }
    $allocated = '0.000000';
    $last = array_key_last($lines);
    foreach ($lines as $index => &$line) {
        $share = $index === $last
            ? bcsub($discount, $allocated, 6)
            : yovel_admin_finance_money(bcdiv(bcmul($discount, $bases[$index], 6), $baseTotal, 6));
        $line['discount_amount'] = yovel_admin_finance_money(bcadd((string) ($line['discount_amount'] ?? '0'), $share, 6));
        $allocated = bcadd($allocated, $share, 6);
    }
    unset($line);
    return $lines;
}

function yovel_admin_persist_finance_invoice(ADOConnection $db, array $company, array $admin, string $type, array $input, ?array $dependencyOverrides = null, bool $ownsTransaction = true): array
{
    yovel_admin_finance_core_schema();
    yovel_admin_finance_invoice_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $type = yovel_admin_finance_invoice_type($type);
    $key = trim((string) ($input['invoice_key'] ?? ''));
    if ($key !== '' && !yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Finance Invoice key is invalid.');
    }
    $postingDate = yovel_admin_optional_date((string) ($input['posting_date'] ?? ''), 'Invoice posting date');
    $dueDate = yovel_admin_optional_date((string) ($input['due_date'] ?? ''), 'Invoice due date');
    if ($postingDate === '' || $dueDate === '' || $dueDate < $postingDate) {
        throw new InvalidArgumentException('Invoice posting and due dates are required and must be valid.');
    }
    $invoiceNo = substr(trim((string) ($input['invoice_no'] ?? '')), 0, 120);
    if ($invoiceNo === '') {
        $invoiceNo = yovel_admin_finance_next_number($db, $company, $admin, $type . '_INVOICE', $type === 'SALES' ? 'SI-' : 'PI-', (int) substr($postingDate, 0, 4));
    }
    $services = yovel_admin_finance_invoice_dependency_gateway($dependencyOverrides ?? []);
    $party = yovel_admin_finance_invoice_party($db, $company, $type, $input, $services);
    $settings = yovel_admin_finance_settings($company, $admin);
    $partyAccountField = $type === 'SALES' ? 'default_receivable_account_key' : 'default_payable_account_key';
    $partyAccount = yovel_admin_finance_account_key($db, $hash, $input['party_account_key'] ?? ($settings[$partyAccountField] ?? ''), $type === 'SALES' ? 'Receivable account' : 'Payable account', $type === 'SALES' ? ['ASSET'] : ['LIABILITY'], [$type === 'SALES' ? 'Receivable' : 'Payable']);
    if ($partyAccount === null) {
        throw new InvalidArgumentException(($type === 'SALES' ? 'Receivable' : 'Payable') . ' account is required.');
    }
    $currency = strtoupper(trim((string) ($input['currency'] ?? ($settings['base_currency'] ?? 'PHP'))));
    $exchangeRate = yovel_admin_finance_money($input['exchange_rate'] ?? '1', 8);
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1 || bccomp($exchangeRate, '0', 8) !== 1) {
        throw new InvalidArgumentException('Invoice currency or exchange rate is invalid.');
    }
    $isReturn = !empty($input['is_return']);
    $returnAgainst = trim((string) ($input['return_against_invoice_key'] ?? ''));
    if ($isReturn && !yovel_admin_is_uuid($returnAgainst)) {
        throw new InvalidArgumentException('A return must reference its original Finance Invoice.');
    }
    $supplierReference = $type === 'PURCHASE' ? substr(trim((string) ($input['supplier_reference'] ?? '')), 0, 120) : '';
    if ($type === 'PURCHASE' && $supplierReference === '') {
        throw new InvalidArgumentException('Supplier reference is required.');
    }
    $additionalDiscount = yovel_admin_finance_money($input['additional_discount_amount'] ?? '0');
    $discountApplication = strtoupper(trim((string) ($input['discount_application'] ?? 'BEFORE_TAX')));
    if (!in_array($discountApplication, ['BEFORE_TAX', 'AFTER_TAX'], true)) {
        throw new InvalidArgumentException('Invoice discount application is invalid.');
    }
    if ($discountApplication === 'AFTER_TAX' && bccomp($additionalDiscount, '0', 6) === 1) {
        throw new InvalidArgumentException('After-tax discounts are not allowed because they would alter the Philippine VAT snapshot ordering.');
    }
    $inputLines = is_array($input['lines'] ?? null) ? $input['lines'] : [];
    $inputLines = yovel_admin_finance_invoice_apply_discount($inputLines, $additionalDiscount);
    $calculated = yovel_admin_finance_invoice_calculate_lines($db, $company, $type, $postingDate, $isReturn, $inputLines, $services);
    $baseCurrency = strtoupper((string) ($settings['base_currency'] ?? 'PHP'));
    $baseNetTotal = yovel_admin_finance_money(bcmul((string) $calculated['net_amount'], $exchangeRate, 6));
    $baseVatAmount = yovel_admin_finance_money(bcmul((string) $calculated['vat_amount'], $exchangeRate, 6));
    $baseGrandTotal = yovel_admin_finance_money(bcmul((string) $calculated['grand_total'], $exchangeRate, 6));
    $isOpening = !empty($input['is_opening']);
    $amendedFrom = trim((string) ($input['amended_from_invoice_key'] ?? ''));
    $amendmentIndex = max(0, (int) ($input['amendment_index'] ?? 0));
    if ($amendedFrom !== '' && !yovel_admin_is_uuid($amendedFrom)) {
        throw new InvalidArgumentException('Invoice amendment reference is invalid.');
    }
    $templateKey = trim((string) ($input['payment_terms_template_key'] ?? ''));
    if ($templateKey !== '' && !yovel_admin_is_uuid($templateKey)) throw new InvalidArgumentException('Payment Terms Template key is invalid.');
    if ($templateKey !== '' && (!is_array($input['payment_terms'] ?? null) || $input['payment_terms'] === [])) {
        $schedule = yovel_admin_finance_template_schedule($company, $templateKey, $postingDate, (string) $calculated['grand_total']);
    } else {
        $scheduleInput = is_array($input['payment_terms'] ?? null) && $input['payment_terms'] !== [] ? $input['payment_terms'] : [['due_date' => $dueDate, 'invoice_portion' => '100', 'description' => 'Full balance']];
        $schedule = yovel_admin_finance_invoice_payment_schedule($postingDate, (string) $calculated['grand_total'], $scheduleInput);
    }
    $advances = yovel_admin_finance_invoice_advances((string) $calculated['grand_total'], is_array($input['advances'] ?? null) ? $input['advances'] : []);
    $references = yovel_admin_finance_invoice_references($company, is_array($input['references'] ?? null) ? $input['references'] : [], $services);
    if ($ownsTransaction && $db->BeginTrans() === false) {
        throw new RuntimeException('Finance Invoice transaction could not start.');
    }
    try {
        $existing = $key !== '' ? $db->GetRow('SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? FOR UPDATE', [$hash, $key]) : $db->GetRow('SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND document_type=? AND invoice_no=? FOR UPDATE', [$hash, $type, $invoiceNo]);
        if (is_array($existing) && $existing !== [] && (string) $existing['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Submitted or Cancelled Finance Invoices are immutable.');
        }
        if ($type === 'PURCHASE' && (int) $db->GetOne("SELECT COUNT(*) FROM project_company_finance_invoice WHERE company_key_hash=? AND document_type='PURCHASE' AND party_key=? AND supplier_reference=? AND document_status <> 'CANCELLED' AND invoice_key <> ?", [$hash, $party['party_key'], $supplierReference, (string) ($existing['invoice_key'] ?? $key)]) > 0) {
            throw new InvalidArgumentException('Supplier reference already exists for this Supplier.');
        }
        $key = is_array($existing) && $existing !== [] ? (string) $existing['invoice_key'] : ($key !== '' ? $key : bx_uuid());
        $fields = ['net_amount'=>'net_total','taxable_sales'=>'taxable_sales','government_sales'=>'government_sales','zero_rated_sales'=>'zero_rated_sales','exempt_sales'=>'exempt_sales','out_of_scope_amount'=>'out_of_scope_amount','vat_amount'=>'vat_amount','creditable_input_vat'=>'creditable_input_vat','non_creditable_input_vat'=>'non_creditable_input_vat','creditable_vat_withheld'=>'creditable_vat_withheld','grand_total'=>'grand_total'];
        $values = [];
        foreach ($fields as $source => $target) { $values[$target] = $calculated[$source]; }
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_invoice (invoice_key,company_key,company_key_hash,document_type,invoice_no,is_return,return_against_invoice_key,party_key,party_code,party_name,party_tin,party_address,supplier_reference,posting_date,due_date,currency,exchange_rate,base_currency,party_account_key,document_status,net_total,taxable_sales,government_sales,zero_rated_sales,exempt_sales,out_of_scope_amount,vat_amount,creditable_input_vat,non_creditable_input_vat,creditable_vat_withheld,grand_total,outstanding_amount,base_net_total,base_vat_amount,base_grand_total,base_outstanding_amount,additional_discount_amount,discount_application,advance_paid,is_opening,amended_from_invoice_key,amendment_index,remarks,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'DRAFT',?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,0,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE posting_date=VALUES(posting_date),due_date=VALUES(due_date),party_key=VALUES(party_key),party_code=VALUES(party_code),party_name=VALUES(party_name),party_tin=VALUES(party_tin),party_address=VALUES(party_address),supplier_reference=VALUES(supplier_reference),currency=VALUES(currency),exchange_rate=VALUES(exchange_rate),base_currency=VALUES(base_currency),party_account_key=VALUES(party_account_key),net_total=VALUES(net_total),taxable_sales=VALUES(taxable_sales),government_sales=VALUES(government_sales),zero_rated_sales=VALUES(zero_rated_sales),exempt_sales=VALUES(exempt_sales),out_of_scope_amount=VALUES(out_of_scope_amount),vat_amount=VALUES(vat_amount),creditable_input_vat=VALUES(creditable_input_vat),non_creditable_input_vat=VALUES(non_creditable_input_vat),creditable_vat_withheld=VALUES(creditable_vat_withheld),grand_total=VALUES(grand_total),base_net_total=VALUES(base_net_total),base_vat_amount=VALUES(base_vat_amount),base_grand_total=VALUES(base_grand_total),additional_discount_amount=VALUES(additional_discount_amount),discount_application=VALUES(discount_application),advance_paid=VALUES(advance_paid),is_opening=VALUES(is_opening),amended_from_invoice_key=VALUES(amended_from_invoice_key),amendment_index=VALUES(amendment_index),remarks=VALUES(remarks),updated_by_admin_key=VALUES(updated_by_admin_key)", [$key,$companyKey,$hash,$type,$invoiceNo,$isReturn?1:0,$returnAgainst!==''?$returnAgainst:null,$party['party_key'],$party['party_code'],$party['party_name'],$party['party_tin']!==''?$party['party_tin']:null,$party['party_address']!==''?$party['party_address']:null,$supplierReference!==''?$supplierReference:null,$postingDate,$dueDate,$currency,$exchangeRate,$baseCurrency,$partyAccount,$values['net_total'],$values['taxable_sales'],$values['government_sales'],$values['zero_rated_sales'],$values['exempt_sales'],$values['out_of_scope_amount'],$values['vat_amount'],$values['creditable_input_vat'],$values['non_creditable_input_vat'],$values['creditable_vat_withheld'],$values['grand_total'],$baseNetTotal,$baseVatAmount,$baseGrandTotal,$additionalDiscount,$discountApplication,$advances['total'],$isOpening?1:0,$amendedFrom!==''?$amendedFrom:null,$amendmentIndex,substr(trim((string)($input['remarks']??'')),0,1000)?:null,$adminKey,$adminKey], 'Finance Invoice save');
        yovel_admin_db_execute($db, 'UPDATE project_company_finance_invoice SET payment_terms_template_key=? WHERE company_key_hash=? AND invoice_key=?', [$templateKey !== '' ? $templateKey : null,$hash,$key], 'Finance Invoice Payment Terms Template link');
        foreach (['project_company_finance_invoice_tax','project_company_finance_invoice_line','project_company_finance_payment_term','project_company_finance_invoice_advance','project_company_finance_invoice_reference'] as $table) {
            yovel_admin_db_execute($db, "DELETE FROM {$table} WHERE company_key_hash=? AND invoice_key=?", [$hash,$key], 'Finance Invoice detail reset');
        }
        foreach ($calculated['lines'] as $line) {
            $snapshot = json_encode($line['tax_snapshot'], JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            $referenceSnapshot = $line['reference_snapshots'] === [] ? null : yovel_admin_finance_canonical_json($line['reference_snapshots']);
            yovel_admin_db_execute($db, "INSERT INTO project_company_finance_invoice_line (invoice_line_key,invoice_key,company_key,company_key_hash,line_no,description,quantity,unit_amount,discount_amount,account_key,cost_center,project,tax_code,bir_classification,taxable_base,vat_amount,line_total,price_inclusive,tax_snapshot_json,item_reference_key,asset_reference_key,stock_reference_key,pricing_reference_key,timesheet_reference_key,reference_snapshot_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [$line['invoice_line_key'],$key,$companyKey,$hash,$line['line_no'],$line['description'],$line['quantity'],$line['unit_amount'],$line['discount_amount'],$line['account_key'],$line['cost_center']?:null,$line['project']?:null,$line['tax_code'],$line['bir_classification'],$line['taxable_base'],$line['vat_amount'],$line['line_total'],$line['price_inclusive']?1:0,$snapshot,$line['item_reference_key']?:null,$line['asset_reference_key']?:null,$line['stock_reference_key']?:null,$line['pricing_reference_key']?:null,$line['timesheet_reference_key']?:null,$referenceSnapshot], 'Finance Invoice line save');
            $meta=$line['tax_snapshot'];
            yovel_admin_db_execute($db, "INSERT INTO project_company_finance_invoice_tax (invoice_tax_key,invoice_key,invoice_line_key,company_key,company_key_hash,tax_code_key,tax_code,tax_name,tax_kind,bir_classification,rate,taxable_base,vat_amount,creditable_input_vat,non_creditable_input_vat,creditable_vat_withheld,tax_snapshot_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [bx_uuid(),$key,$line['invoice_line_key'],$companyKey,$hash,$meta['tax_code_key']?:null,$meta['tax_code'],$meta['tax_name'],$meta['tax_kind'],$meta['bir_classification'],$meta['rate'],$line['taxable_base'],$line['vat_amount'],$line['creditable_input_vat'],$line['non_creditable_input_vat'],$line['creditable_vat_withheld'],$snapshot], 'Finance Invoice tax snapshot save');
        }
        foreach ($schedule as $term) {
            yovel_admin_db_execute($db, "INSERT INTO project_company_finance_payment_term (payment_term_key,invoice_key,company_key,company_key_hash,term_no,due_date,due_amount,description) VALUES (?,?,?,?,?,?,?,?)", [bx_uuid(),$key,$companyKey,$hash,$term['term_no'],$term['due_date'],$term['due_amount'],$term['description']?:null], 'Finance Invoice payment term save');
        }
        foreach ($advances['rows'] as $advance) {
            yovel_admin_db_execute($db, 'INSERT INTO project_company_finance_invoice_advance (invoice_advance_key,invoice_key,company_key,company_key_hash,reference_type,reference_key,allocated_amount) VALUES (?,?,?,?,?,?,?)', [$advance['invoice_advance_key'],$key,$companyKey,$hash,$advance['reference_type'],$advance['reference_key'],$advance['allocated_amount']], 'Finance Invoice advance save');
        }
        foreach ($references as $reference) {
            yovel_admin_db_execute($db, 'INSERT INTO project_company_finance_invoice_reference (invoice_reference_key,invoice_key,company_key,company_key_hash,reference_type,reference_key,reference_snapshot_json) VALUES (?,?,?,?,?,?,?)', [$reference['invoice_reference_key'],$key,$companyKey,$hash,$reference['reference_type'],$reference['reference_key'],$reference['reference_snapshot_json']], 'Finance Invoice reference save');
        }
        if (is_callable($input['_checkpoint'] ?? null)) {
            $input['_checkpoint']('after_invoice_details');
        }
        $saved=yovel_admin_finance_invoice($company,$key,false);
        if(!is_array($saved)||count($saved['lines'])!==count($calculated['lines'])||(string)$saved['grand_total']!==$calculated['grand_total']) throw new RuntimeException('Finance Invoice read-back verification failed.');
        bx_audit(is_array($existing)&&$existing!==[]?'UPDATE':'CREATE','project_company_finance_invoice',$key,['company_key'=>$companyKey,'document_type'=>$type,'invoice_no'=>$invoiceNo,'grand_total'=>$calculated['grand_total'],'admin_key'=>$adminKey],'Company administrator saved a Finance Invoice Draft.');
        if($ownsTransaction && $db->CommitTrans()===false) throw new RuntimeException('Finance Invoice transaction could not commit.');
        return $saved;
    } catch(Throwable $error){if($ownsTransaction)$db->RollbackTrans();throw $error;}
}

function yovel_admin_finance_invoice_gl_entries(array $header,array $lines,array $settings): array
{
    $type=(string)$header['document_type'];$return=(int)$header['is_return']===1;$entries=[];$rate=(string)$header['exchange_rate'];$partyAmount=yovel_admin_finance_abs($header['base_grand_total']);$transactionPartyAmount=yovel_admin_finance_abs($header['grand_total']);
    $party=['account_key'=>(string)$header['party_account_key'],'party_type'=>$type==='SALES'?'CUSTOMER':'SUPPLIER','party'=>(string)$header['party_name'],'transaction_currency'=>(string)$header['currency'],'exchange_rate'=>$rate];
    if(($type==='SALES'&&!$return)||($type==='PURCHASE'&&$return)){$entries[]=array_merge($party,['debit'=>$partyAmount,'credit'=>'0','transaction_debit'=>$transactionPartyAmount,'transaction_credit'=>'0']);}else{$entries[]=array_merge($party,['debit'=>'0','credit'=>$partyAmount,'transaction_debit'=>'0','transaction_credit'=>$transactionPartyAmount]);}
    foreach($lines as $line){$transactionNet=yovel_admin_finance_money(bcsub(yovel_admin_finance_abs($line['line_total']),yovel_admin_finance_abs($line['vat_amount']),6));$net=yovel_admin_finance_money(bcmul($transactionNet,$rate,6));$base=['account_key'=>(string)$line['account_key'],'cost_center'=>(string)($line['cost_center']??''),'project'=>(string)($line['project']??''),'remarks'=>(string)$line['description'],'transaction_currency'=>(string)$header['currency'],'exchange_rate'=>$rate];if(($type==='SALES'&&!$return)||($type==='PURCHASE'&&$return))$entries[]=array_merge($base,['debit'=>'0','credit'=>$net,'transaction_debit'=>'0','transaction_credit'=>$transactionNet]);else $entries[]=array_merge($base,['debit'=>$net,'credit'=>'0','transaction_debit'=>$transactionNet,'transaction_credit'=>'0']);}
    $vat=yovel_admin_finance_abs($header['base_vat_amount']);$transactionVat=yovel_admin_finance_abs($header['vat_amount']);
    if(bccomp($vat,'0',6)===1){$vatKey=(string)($settings[$type==='SALES'?'default_output_vat_account_key':'default_input_vat_account_key']??'');if(!yovel_admin_is_uuid($vatKey))throw new InvalidArgumentException('Default VAT account is required before invoice submission.');if(($type==='SALES'&&!$return)||($type==='PURCHASE'&&$return))$entries[]=['account_key'=>$vatKey,'debit'=>'0','credit'=>$vat];else $entries[]=['account_key'=>$vatKey,'debit'=>$vat,'credit'=>'0'];}
    if (isset($entries[array_key_last($entries)]) && bccomp($vat,'0',6)===1) {
        $last = array_key_last($entries);$entries[$last]['transaction_currency']=(string)$header['currency'];$entries[$last]['exchange_rate']=$rate;
        $entries[$last]['transaction_debit']=(($type==='SALES'&&!$return)||($type==='PURCHASE'&&$return))?'0':$transactionVat;
        $entries[$last]['transaction_credit']=(($type==='SALES'&&!$return)||($type==='PURCHASE'&&$return))?$transactionVat:'0';
    }
    return $entries;
}

function yovel_admin_finance_invoice_snapshot_data(array $header, array $lines, array $terms, array $advances, array $references, array $taxes): array
{
    $documentType = (int) $header['is_return'] === 1
        ? ((string) $header['document_type'] === 'SALES' ? 'CREDIT_NOTE' : 'DEBIT_NOTE')
        : ((string) $header['document_type'] === 'SALES' ? 'SALES_INVOICE' : 'PURCHASE_INVOICE');
    $cleanRows = static function (array $rows, array $fields): array {
        return array_map(static function (array $row) use ($fields): array {
            $clean = [];
            foreach ($fields as $field) $clean[$field] = $row[$field] ?? null;
            return $clean;
        }, array_values($rows));
    };
    return yovel_admin_finance_canonical_value([
        'schema' => 'accounting-finance.invoice-snapshot.v1',
        'document_type' => $documentType,
        'invoice_number' => (string) $header['invoice_no'],
        'issue_date' => (string) $header['posting_date'],
        'due_date' => (string) $header['due_date'],
        'currency' => (string) $header['currency'],
        'exchange_rate' => (string) $header['exchange_rate'],
        'base_currency' => (string) $header['base_currency'],
        'party' => ['key'=>(string)$header['party_key'],'code'=>(string)$header['party_code'],'name'=>(string)$header['party_name'],'tin'=>(string)($header['party_tin']??''),'address'=>(string)($header['party_address']??'')],
        'totals' => ['net_total'=>(string)$header['net_total'],'taxable_sales'=>(string)$header['taxable_sales'],'zero_rated_sales'=>(string)$header['zero_rated_sales'],'exempt_sales'=>(string)$header['exempt_sales'],'vat_amount'=>(string)$header['vat_amount'],'grand_total'=>(string)$header['grand_total'],'base_net_total'=>(string)$header['base_net_total'],'base_vat_amount'=>(string)$header['base_vat_amount'],'base_grand_total'=>(string)$header['base_grand_total'],'advance_paid'=>(string)$header['advance_paid']],
        'line_items' => $cleanRows($lines,['line_no','description','quantity','unit_amount','discount_amount','tax_code','bir_classification','taxable_base','vat_amount','line_total','price_inclusive','tax_snapshot_json','item_reference_key','asset_reference_key','stock_reference_key','pricing_reference_key','timesheet_reference_key','reference_snapshot_json']),
        'taxes' => $cleanRows($taxes,['tax_code','tax_name','tax_kind','bir_classification','rate','taxable_base','vat_amount','creditable_input_vat','non_creditable_input_vat','creditable_vat_withheld','tax_snapshot_json']),
        'payment_schedule' => $cleanRows($terms,['term_no','due_date','due_amount','description']),
        'advances' => $cleanRows($advances,['reference_type','reference_key','allocated_amount']),
        'references' => $cleanRows($references,['reference_type','reference_key','reference_snapshot_json']),
        'lineage' => ['is_return'=>(int)$header['is_return']===1,'return_against_invoice_key'=>(string)($header['return_against_invoice_key']??''),'is_opening'=>(int)$header['is_opening']===1,'amended_from_invoice_key'=>(string)($header['amended_from_invoice_key']??''),'amendment_index'=>(int)$header['amendment_index']],
    ]);
}

function yovel_admin_submit_finance_invoice(ADOConnection $db,array $company,array $admin,string $type,string $key):array
{
    yovel_admin_finance_core_schema();yovel_admin_general_ledger_schema();yovel_admin_finance_invoice_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$type=yovel_admin_finance_invoice_type($type);if(!yovel_admin_is_uuid($key))throw new InvalidArgumentException('Finance Invoice key is invalid.');$preview=yovel_admin_finance_invoice($company,$key);if(!$preview||(string)$preview['document_type']!==$type)throw new InvalidArgumentException('Finance Invoice was not found for this company.');yovel_admin_finance_assert_open_period($company,(string)$preview['posting_date'],false);
    if($type==='PURCHASE'&&(int)$preview['is_return']===0&&function_exists('yovel_admin_assert_budget_available')){foreach($preview['lines'] as $line){$budgetAmount=yovel_admin_finance_money(bcsub((string)$line['line_total'],(string)$line['vat_amount'],6));yovel_admin_assert_budget_available($company,(string)$preview['posting_date'],(string)$line['account_key'],$budgetAmount);}}
    if($db->BeginTrans()===false)throw new RuntimeException('Invoice submission transaction could not start.');try{$header=$db->GetRow('SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? FOR UPDATE',[$hash,$key]);if(!$header||(string)$header['document_status']!=='DRAFT')throw new InvalidArgumentException('Only a Draft Finance Invoice can be submitted.');$lines=$db->GetAll('SELECT * FROM project_company_finance_invoice_line WHERE company_key_hash=? AND invoice_key=? ORDER BY line_no',[$hash,$key]);$terms=$db->GetAll('SELECT * FROM project_company_finance_payment_term WHERE company_key_hash=? AND invoice_key=? ORDER BY term_no',[$hash,$key]);$advances=$db->GetAll('SELECT * FROM project_company_finance_invoice_advance WHERE company_key_hash=? AND invoice_key=? ORDER BY x_id',[$hash,$key]);$references=$db->GetAll('SELECT * FROM project_company_finance_invoice_reference WHERE company_key_hash=? AND invoice_key=? ORDER BY x_id',[$hash,$key]);$taxes=$db->GetAll('SELECT * FROM project_company_finance_invoice_tax WHERE company_key_hash=? AND invoice_key=? ORDER BY x_id',[$hash,$key]);$snapshot=yovel_admin_finance_invoice_snapshot_data($header,$lines,$terms,$advances,$references,$taxes);$snapshotJson=yovel_admin_finance_canonical_json($snapshot);$snapshotSha=hash('sha256',$snapshotJson);$posted=yovel_admin_post_general_ledger_transaction($db,$company,$admin,['posting_date'=>$header['posting_date'],'voucher_type'=>$type.'_INVOICE','voucher_no'=>$header['invoice_no'],'source_module'=>$type.'_INVOICE','source_record_key'=>$key,'remarks'=>$header['remarks']??'','base_currency'=>$header['base_currency'],'entries'=>yovel_admin_finance_invoice_gl_entries($header,$lines,yovel_admin_finance_settings($company,$admin))],false,false);$outstanding=bcsub((string)$header['grand_total'],((int)$header['is_return']===1?'-':'').(string)$header['advance_paid'],6);$baseOutstanding=yovel_admin_finance_money(bcmul($outstanding,(string)$header['exchange_rate'],6));yovel_admin_db_execute($db,"UPDATE project_company_finance_invoice SET document_status='SUBMITTED',outstanding_amount=?,base_outstanding_amount=?,gl_transaction_key=?,immutable_snapshot_json=?,immutable_snapshot_sha256=?,submitted_by_admin_key=?,submitted_at=CURRENT_TIMESTAMP,updated_by_admin_key=? WHERE company_key_hash=? AND invoice_key=? AND document_status='DRAFT'",[$outstanding,$baseOutstanding,$posted['transaction_key'],$snapshotJson,$snapshotSha,$adminKey,$adminKey,$hash,$key],'Finance Invoice submission');$saved=yovel_admin_finance_invoice($company,$key,false);if(!$saved||(string)$saved['document_status']!=='SUBMITTED'||(string)$saved['gl_transaction_key']!==(string)$posted['transaction_key']||(string)$saved['immutable_snapshot_sha256']!==$snapshotSha)throw new RuntimeException('Finance Invoice submission read-back verification failed.');bx_audit('SUBMIT','project_company_finance_invoice',$key,['company_key'=>$companyKey,'invoice_no'=>$header['invoice_no'],'gl_transaction_key'=>$posted['transaction_key'],'snapshot_sha256'=>$snapshotSha,'admin_key'=>$adminKey],'Company administrator submitted a Finance Invoice.');if($db->CommitTrans()===false)throw new RuntimeException('Invoice submission transaction could not commit.');return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_cancel_finance_invoice(ADOConnection $db,array $company,array $admin,string $type,string $key,string $postingDate,string $reason):array
{
    yovel_admin_finance_core_schema();yovel_admin_general_ledger_schema();yovel_admin_finance_invoice_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$type=yovel_admin_finance_invoice_type($type);$postingDate=yovel_admin_optional_date($postingDate,'Cancellation posting date');$reason=trim($reason);if(!yovel_admin_is_uuid($key)||$postingDate===''||$reason==='')throw new InvalidArgumentException('Invoice cancellation reference, date, and reason are required.');yovel_admin_finance_assert_open_period($company,$postingDate,false);if($db->BeginTrans()===false)throw new RuntimeException('Invoice cancellation transaction could not start.');try{$header=$db->GetRow('SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? AND document_type=? FOR UPDATE',[$hash,$key,$type]);if(!$header||(string)$header['document_status']!=='SUBMITTED')throw new InvalidArgumentException('Only a Submitted Finance Invoice can be cancelled.');if(bccomp((string)$header['outstanding_amount'],(string)$header['grand_total'],6)!==0)throw new InvalidArgumentException('An allocated Finance Invoice cannot be cancelled until its payments are cancelled.');$rev=yovel_admin_reverse_general_ledger_transaction($db,$company,$admin,(string)$header['gl_transaction_key'],$postingDate,$reason,false,false);yovel_admin_db_execute($db,"UPDATE project_company_finance_invoice SET document_status='CANCELLED',outstanding_amount=0,reversal_transaction_key=?,cancelled_by_admin_key=?,cancelled_at=CURRENT_TIMESTAMP,updated_by_admin_key=? WHERE company_key_hash=? AND invoice_key=?",[$rev['transaction_key'],$adminKey,$adminKey,$hash,$key],'Finance Invoice cancellation');$saved=yovel_admin_finance_invoice($company,$key,false);if(!$saved||(string)$saved['document_status']!=='CANCELLED'||(string)$saved['outstanding_amount']!=='0.000000')throw new RuntimeException('Finance Invoice cancellation read-back verification failed.');bx_audit('CANCEL','project_company_finance_invoice',$key,['company_key'=>$companyKey,'invoice_no'=>$header['invoice_no'],'reversal_transaction_key'=>$rev['transaction_key'],'admin_key'=>$adminKey],'Company administrator cancelled a Finance Invoice through an additive reversal.');if($db->CommitTrans()===false)throw new RuntimeException('Invoice cancellation transaction could not commit.');return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_create_finance_return(ADOConnection $db,array $company,array $admin,string $type,string $originalKey,array $overrides=[],?array $dependencyOverrides=null):array
{
    $original=yovel_admin_finance_invoice($company,$originalKey);if(!$original||(string)$original['document_status']!=='SUBMITTED'||(int)$original['is_return']===1)throw new InvalidArgumentException('Only a Submitted original Finance Invoice can create a return.');$lines=is_array($overrides['lines']??null)&&$overrides['lines']!==[]?$overrides['lines']:array_map(static fn(array $line):array=>['description'=>$line['description'],'quantity'=>$line['quantity'],'unit_amount'=>$line['unit_amount'],'discount_amount'=>$line['discount_amount'],'account_key'=>$line['account_key'],'cost_center'=>$line['cost_center'],'project'=>$line['project'],'tax_meta'=>json_decode((string)$line['tax_snapshot_json'],true,512,JSON_THROW_ON_ERROR),'price_inclusive'=>(int)$line['price_inclusive']===1],$original['lines']);$existingReturns=yovel_admin_finance_money($db->GetOne("SELECT COALESCE(SUM(ABS(grand_total)),0) FROM project_company_finance_invoice WHERE company_key_hash=? AND return_against_invoice_key=? AND document_status<>'CANCELLED'",[(string)$company['company_key_hash'],$originalKey])??'0');$preview=yovel_admin_finance_invoice_calculate_lines($db,$company,$type,(string)($overrides['posting_date']??date('Y-m-d')),true,$lines,yovel_admin_finance_invoice_dependency_gateway($dependencyOverrides??[]));if(bccomp(bcadd($existingReturns,yovel_admin_finance_abs($preview['grand_total']),6),yovel_admin_finance_abs($original['grand_total']),6)===1)throw new InvalidArgumentException('Credit or Debit Note exceeds the remaining return limit.');$input=['invoice_key'=>$overrides['invoice_key']??'','invoice_no'=>$overrides['invoice_no']??'','posting_date'=>$overrides['posting_date']??date('Y-m-d'),'due_date'=>$overrides['due_date']??($overrides['posting_date']??date('Y-m-d')),'party_key'=>$original['party_key'],'party_tin'=>$original['party_tin'],'party_address'=>$original['party_address'],'supplier_reference'=>$type==='PURCHASE'?($overrides['supplier_reference']??('RET-'.$original['supplier_reference'])):'','currency'=>$original['currency'],'exchange_rate'=>$original['exchange_rate'],'party_account_key'=>$original['party_account_key'],'is_return'=>1,'return_against_invoice_key'=>$originalKey,'remarks'=>$overrides['remarks']??('Return against '.$original['invoice_no']),'lines'=>$lines];return yovel_admin_persist_finance_invoice($db,$company,$admin,$type,$input,$dependencyOverrides);
}

function yovel_admin_amend_finance_invoice(ADOConnection $db,array $company,array $admin,string $type,string $originalKey,array $overrides=[],?array $dependencyOverrides=null):array
{
    $original=yovel_admin_finance_invoice($company,$originalKey);if(!$original||(string)$original['document_type']!==yovel_admin_finance_invoice_type($type)||(string)$original['document_status']!=='CANCELLED')throw new InvalidArgumentException('Only a Cancelled Finance Invoice can be amended.');$next=(int)$db->GetOne('SELECT COALESCE(MAX(amendment_index),0)+1 FROM project_company_finance_invoice WHERE company_key_hash=? AND (invoice_key=? OR amended_from_invoice_key=?)',[(string)$company['company_key_hash'],$originalKey,$originalKey]);$input=['invoice_no'=>$overrides['invoice_no']??((string)$original['invoice_no'].'-'.$next),'posting_date'=>$overrides['posting_date']??date('Y-m-d'),'due_date'=>$overrides['due_date']??date('Y-m-d'),'party_key'=>$original['party_key'],'party_tin'=>$original['party_tin'],'party_address'=>$original['party_address'],'supplier_reference'=>$type==='PURCHASE'?($overrides['supplier_reference']??((string)$original['supplier_reference'].'-'.$next)):'','currency'=>$original['currency'],'exchange_rate'=>$overrides['exchange_rate']??$original['exchange_rate'],'party_account_key'=>$original['party_account_key'],'amended_from_invoice_key'=>$originalKey,'amendment_index'=>$next,'remarks'=>$overrides['remarks']??('Amendment of '.$original['invoice_no']),'payment_terms'=>$overrides['payment_terms']??array_map(static fn(array $row):array=>['due_date'=>$row['due_date'],'due_amount'=>$row['due_amount'],'invoice_portion'=>bcdiv(bcmul(yovel_admin_finance_abs($row['due_amount']),'100',6),yovel_admin_finance_abs($original['grand_total']),6),'description'=>$row['description']],$original['payment_terms']),'lines'=>$overrides['lines']??array_map(static fn(array $line):array=>['description'=>$line['description'],'quantity'=>$line['quantity'],'unit_amount'=>$line['unit_amount'],'discount_amount'=>$line['discount_amount'],'account_key'=>$line['account_key'],'cost_center'=>$line['cost_center'],'project'=>$line['project'],'tax_meta'=>json_decode((string)$line['tax_snapshot_json'],true,512,JSON_THROW_ON_ERROR),'price_inclusive'=>(int)$line['price_inclusive']===1],$original['lines'])];return yovel_admin_persist_finance_invoice($db,$company,$admin,$type,$input,$dependencyOverrides);
}

function yovel_admin_finance_invoice(array $company,string $key,bool $ensureSchema=true):?array
{
    if($ensureSchema)yovel_admin_finance_invoice_schema();if(!yovel_admin_is_uuid($key))return null;$hash=(string)($company['company_key_hash']??'');$header=bx_db()->GetRow('SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? LIMIT 1',[$hash,$key]);if(!$header)return null;$lines=bx_db()->GetAll('SELECT * FROM project_company_finance_invoice_line WHERE company_key_hash=? AND invoice_key=? ORDER BY line_no',[$hash,$key]);$terms=bx_db()->GetAll('SELECT * FROM project_company_finance_payment_term WHERE company_key_hash=? AND invoice_key=? ORDER BY term_no',[$hash,$key]);$advances=bx_db()->GetAll('SELECT * FROM project_company_finance_invoice_advance WHERE company_key_hash=? AND invoice_key=? ORDER BY x_id',[$hash,$key]);$references=bx_db()->GetAll('SELECT * FROM project_company_finance_invoice_reference WHERE company_key_hash=? AND invoice_key=? ORDER BY x_id',[$hash,$key]);$header['lines']=is_array($lines)?$lines:[];$header['payment_terms']=is_array($terms)?$terms:[];$header['advances']=is_array($advances)?$advances:[];$header['references']=is_array($references)?$references:[];return $header;
}

function yovel_admin_finance_invoice_snapshot(array $company,string $financeDocumentKey):array
{
    [, $hash]=yovel_admin_finance_dependency_read_scope($company);if(!yovel_admin_is_uuid($financeDocumentKey))throw new InvalidArgumentException('Finance document key is invalid.');yovel_admin_finance_invoice_schema();$row=bx_db()->GetRow("SELECT invoice_key,document_status,immutable_snapshot_json,immutable_snapshot_sha256 FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? LIMIT 1",[$hash,$financeDocumentKey]);if(!$row||(string)$row['document_status']!=='SUBMITTED'||trim((string)($row['immutable_snapshot_json']??''))===''||preg_match('/^[a-f0-9]{64}$/',(string)($row['immutable_snapshot_sha256']??''))!==1)return ['ok'=>false,'company_key_hash'=>$hash,'record'=>null,'errors'=>['SUBMITTED_INVOICE_NOT_AVAILABLE']];$json=(string)$row['immutable_snapshot_json'];$snapshot=json_decode($json,true,512,JSON_THROW_ON_ERROR);$canonical=yovel_admin_finance_canonical_json($snapshot);$sha=hash('sha256',$canonical);if(!hash_equals((string)$row['immutable_snapshot_sha256'],$sha)||$canonical!==$json)throw new RuntimeException('Finance immutable invoice snapshot verification failed.');return ['ok'=>true,'company_key_hash'=>$hash,'record'=>['finance_document_key'=>$financeDocumentKey,'finance_document_sha256'=>$sha,'document_status'=>'SUBMITTED','snapshot'=>$snapshot],'errors'=>[]];
}

$GLOBALS['yovel_admin_compliance_dependency_providers'] = is_array($GLOBALS['yovel_admin_compliance_dependency_providers'] ?? null) ? $GLOBALS['yovel_admin_compliance_dependency_providers'] : [];
$GLOBALS['yovel_admin_compliance_dependency_providers']['accounting-finance.invoice-snapshot.v1'] = 'yovel_admin_finance_invoice_snapshot';

function yovel_admin_finance_save_payment_terms_template(ADOConnection $db,array $company,array $admin,array $input):array
{
    yovel_admin_finance_invoice_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$key=trim((string)($input['payment_terms_template_key']??''));if($key!==''&&!yovel_admin_is_uuid($key))throw new InvalidArgumentException('Payment Terms Template key is invalid.');$code=yovel_admin_code((string)($input['template_code']??''));$name=substr(trim((string)($input['template_name']??'')),0,180);$details=is_array($input['terms']??null)?$input['terms']:[];if($code===''||$name===''||$details===[]||count($details)>100)throw new InvalidArgumentException('Payment Terms Template code, name, and details are required.');$normalized=[];$portion='0.000000';foreach(array_values($details) as $index=>$row){if(!is_array($row))throw new InvalidArgumentException('Payment Terms Template details must be structured.');$days=max(0,(int)($row['credit_days']??0));$percent=yovel_admin_finance_money($row['invoice_portion']??'0');$discount=yovel_admin_finance_money($row['discount_percentage']??'0');if(bccomp($percent,'0',6)!==1||bccomp($discount,'0',6)===-1||bccomp($discount,'100',6)===1)throw new InvalidArgumentException('Payment Terms Template percentages are invalid.');$portion=bcadd($portion,$percent,6);$normalized[]=['term_no'=>$index+1,'credit_days'=>$days,'invoice_portion'=>$percent,'discount_percentage'=>$discount,'discount_days'=>max(0,(int)($row['discount_days']??0)),'description'=>substr(trim((string)($row['description']??'')),0,180)];}if(bccomp($portion,'100.000000',6)!==0)throw new InvalidArgumentException('Payment Terms Template portions must total 100 percent.');if($db->BeginTrans()===false)throw new RuntimeException('Payment Terms Template transaction could not start.');try{$existing=$key!==''?$db->GetRow('SELECT * FROM project_company_finance_payment_terms_template WHERE company_key_hash=? AND payment_terms_template_key=? FOR UPDATE',[$hash,$key]):$db->GetRow('SELECT * FROM project_company_finance_payment_terms_template WHERE company_key_hash=? AND template_code=? FOR UPDATE',[$hash,$code]);$key=$existing?(string)$existing['payment_terms_template_key']:($key?:bx_uuid());yovel_admin_db_execute($db,"INSERT INTO project_company_finance_payment_terms_template(payment_terms_template_key,company_key,company_key_hash,template_code,template_name,template_status,created_by_admin_key,updated_by_admin_key) VALUES(?,?,?,?,?,'ACTIVE',?,?) ON DUPLICATE KEY UPDATE template_name=VALUES(template_name),template_status='ACTIVE',updated_by_admin_key=VALUES(updated_by_admin_key)",[$key,$companyKey,$hash,$code,$name,$adminKey,$adminKey],'Payment Terms Template save');yovel_admin_db_execute($db,'DELETE FROM project_company_finance_payment_terms_template_detail WHERE company_key_hash=? AND payment_terms_template_key=?',[$hash,$key],'Payment Terms Template details reset');foreach($normalized as $row)yovel_admin_db_execute($db,'INSERT INTO project_company_finance_payment_terms_template_detail(payment_terms_template_detail_key,payment_terms_template_key,company_key,company_key_hash,term_no,credit_days,invoice_portion,discount_percentage,discount_days,description) VALUES(?,?,?,?,?,?,?,?,?,?)',[bx_uuid(),$key,$companyKey,$hash,$row['term_no'],$row['credit_days'],$row['invoice_portion'],$row['discount_percentage'],$row['discount_days'],$row['description']?:null],'Payment Terms Template detail save');$saved=$db->GetRow('SELECT * FROM project_company_finance_payment_terms_template WHERE company_key_hash=? AND payment_terms_template_key=?',[$hash,$key]);$saved['terms']=$db->GetAll('SELECT * FROM project_company_finance_payment_terms_template_detail WHERE company_key_hash=? AND payment_terms_template_key=? ORDER BY term_no',[$hash,$key]);if(!$saved||count($saved['terms'])!==count($normalized))throw new RuntimeException('Payment Terms Template read-back verification failed.');bx_audit($existing?'UPDATE':'CREATE','project_company_finance_payment_terms_template',$key,['company_key'=>$companyKey,'template_code'=>$code,'term_count'=>count($normalized),'admin_key'=>$adminKey],'Company administrator saved a Payment Terms Template.');if($db->CommitTrans()===false)throw new RuntimeException('Payment Terms Template transaction could not commit.');return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_finance_template_schedule(array $company,string $templateKey,string $postingDate,string $grandTotal):array
{
    yovel_admin_finance_invoice_schema();if(!yovel_admin_is_uuid($templateKey))throw new InvalidArgumentException('Payment Terms Template is invalid.');$hash=(string)$company['company_key_hash'];$template=bx_db()->GetRow("SELECT * FROM project_company_finance_payment_terms_template WHERE company_key_hash=? AND payment_terms_template_key=? AND template_status='ACTIVE'",[$hash,$templateKey]);if(!$template)throw new InvalidArgumentException('Payment Terms Template is unavailable.');$rows=bx_db()->GetAll('SELECT * FROM project_company_finance_payment_terms_template_detail WHERE company_key_hash=? AND payment_terms_template_key=? ORDER BY term_no',[$hash,$templateKey]);$schedule=[];foreach($rows as $row)$schedule[]=['due_date'=>(new DateTimeImmutable($postingDate))->modify('+'.(int)$row['credit_days'].' days')->format('Y-m-d'),'invoice_portion'=>$row['invoice_portion'],'description'=>$row['description']];return yovel_admin_finance_invoice_payment_schedule($postingDate,$grandTotal,$schedule);
}

function yovel_admin_finance_save_dunning_type(ADOConnection $db,array $company,array $admin,array $input):array
{
    yovel_admin_finance_invoice_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$key=trim((string)($input['dunning_type_key']??''));$code=yovel_admin_code((string)($input['dunning_type_code']??''));$name=substr(trim((string)($input['dunning_type_name']??'')),0,180);$days=max(0,(int)($input['days_overdue']??0));$interest=yovel_admin_finance_money($input['interest_rate']??'0');$fee=yovel_admin_finance_money($input['fee_amount']??'0');$letter=trim((string)($input['letter_text']??''));if(($key!==''&&!yovel_admin_is_uuid($key))||$code===''||$name===''||$letter===''||bccomp($interest,'0',6)===-1||bccomp($fee,'0',6)===-1)throw new InvalidArgumentException('Dunning Type fields are invalid.');if($db->BeginTrans()===false)throw new RuntimeException('Dunning Type transaction could not start.');try{$existing=$key!==''?$db->GetRow('SELECT * FROM project_company_finance_dunning_type WHERE company_key_hash=? AND dunning_type_key=? FOR UPDATE',[$hash,$key]):$db->GetRow('SELECT * FROM project_company_finance_dunning_type WHERE company_key_hash=? AND dunning_type_code=? FOR UPDATE',[$hash,$code]);$key=$existing?(string)$existing['dunning_type_key']:($key?:bx_uuid());yovel_admin_db_execute($db,"INSERT INTO project_company_finance_dunning_type(dunning_type_key,company_key,company_key_hash,dunning_type_code,dunning_type_name,days_overdue,interest_rate,fee_amount,letter_text,dunning_type_status,created_by_admin_key,updated_by_admin_key) VALUES(?,?,?,?,?,?,?,?,?,'ACTIVE',?,?) ON DUPLICATE KEY UPDATE dunning_type_name=VALUES(dunning_type_name),days_overdue=VALUES(days_overdue),interest_rate=VALUES(interest_rate),fee_amount=VALUES(fee_amount),letter_text=VALUES(letter_text),dunning_type_status='ACTIVE',updated_by_admin_key=VALUES(updated_by_admin_key)",[$key,$companyKey,$hash,$code,$name,$days,$interest,$fee,$letter,$adminKey,$adminKey],'Dunning Type save');$saved=$db->GetRow('SELECT * FROM project_company_finance_dunning_type WHERE company_key_hash=? AND dunning_type_key=?',[$hash,$key]);if(!$saved||(string)$saved['letter_text']!==$letter)throw new RuntimeException('Dunning Type read-back verification failed.');bx_audit($existing?'UPDATE':'CREATE','project_company_finance_dunning_type',$key,['company_key'=>$companyKey,'dunning_type_code'=>$code,'admin_key'=>$adminKey],'Company administrator saved a Dunning Type and letter text.');if($db->CommitTrans()===false)throw new RuntimeException('Dunning Type transaction could not commit.');return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_finance_create_dunning(ADOConnection $db,array $company,array $admin,string $invoiceKey,string $dunningTypeKey,string $date,bool $ownsTransaction=true):array
{
    yovel_admin_finance_invoice_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$date=yovel_admin_optional_date($date,'Dunning date');if(!yovel_admin_is_uuid($invoiceKey)||!yovel_admin_is_uuid($dunningTypeKey)||$date==='')throw new InvalidArgumentException('Dunning references and date are required.');if($ownsTransaction&&$db->BeginTrans()===false)throw new RuntimeException('Dunning transaction could not start.');try{$invoice=$db->GetRow("SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? AND document_type='SALES' AND document_status='SUBMITTED' AND outstanding_amount>0 FOR UPDATE",[$hash,$invoiceKey]);$type=$db->GetRow("SELECT * FROM project_company_finance_dunning_type WHERE company_key_hash=? AND dunning_type_key=? AND dunning_type_status='ACTIVE' FOR UPDATE",[$hash,$dunningTypeKey]);if(!$invoice||!$type)throw new InvalidArgumentException('Dunning requires an overdue Submitted Sales Invoice and active Dunning Type.');$days=(int)(new DateTimeImmutable((string)$invoice['due_date']))->diff(new DateTimeImmutable($date))->format('%r%a');if($days<(int)$type['days_overdue'])throw new InvalidArgumentException('Invoice has not reached the Dunning Type overdue threshold.');$interest=yovel_admin_finance_money(bcdiv(bcmul((string)$invoice['outstanding_amount'],(string)$type['interest_rate'],6),'100',6));$fee=(string)$type['fee_amount'];$total=yovel_admin_finance_money(bcadd(bcadd((string)$invoice['outstanding_amount'],$interest,6),$fee,6));$key=bx_uuid();yovel_admin_db_execute($db,"INSERT INTO project_company_finance_dunning(dunning_key,company_key,company_key_hash,invoice_key,dunning_type_key,dunning_date,overdue_amount,interest_amount,fee_amount,total_amount,letter_snapshot,dunning_status,created_by_admin_key,updated_by_admin_key) VALUES(?,?,?,?,?,?,?,?,?,?,?,'ISSUED',?,?)",[$key,$companyKey,$hash,$invoiceKey,$dunningTypeKey,$date,$invoice['outstanding_amount'],$interest,$fee,$total,$type['letter_text'],$adminKey,$adminKey],'Dunning create');$saved=$db->GetRow('SELECT * FROM project_company_finance_dunning WHERE company_key_hash=? AND dunning_key=?',[$hash,$key]);if(!$saved||(string)$saved['total_amount']!==$total)throw new RuntimeException('Dunning read-back verification failed.');bx_audit('CREATE','project_company_finance_dunning',$key,['company_key'=>$companyKey,'invoice_key'=>$invoiceKey,'total_amount'=>$total,'admin_key'=>$adminKey],'Company administrator issued a Dunning notice.');if($ownsTransaction&&$db->CommitTrans()===false)throw new RuntimeException('Dunning transaction could not commit.');return $saved;}catch(Throwable $error){if($ownsTransaction)$db->RollbackTrans();throw $error;}
}

function yovel_admin_finance_process_overdue_invoices(ADOConnection $db,array $company,array $admin,string $dunningTypeKey,string $date):array
{
    yovel_admin_finance_invoice_schema();[, $hash]=yovel_admin_finance_scope($company,$admin);$date=yovel_admin_optional_date($date,'Overdue processing date');if($date==='')throw new InvalidArgumentException('Overdue processing date is required.');$type=$db->GetRow("SELECT * FROM project_company_finance_dunning_type WHERE company_key_hash=? AND dunning_type_key=? AND dunning_type_status='ACTIVE'",[$hash,$dunningTypeKey]);if(!$type)throw new InvalidArgumentException('Dunning Type is unavailable.');$cutoff=(new DateTimeImmutable($date))->modify('-'.(int)$type['days_overdue'].' days')->format('Y-m-d');$keys=$db->GetCol("SELECT invoice_key FROM project_company_finance_invoice i WHERE company_key_hash=? AND document_type='SALES' AND document_status='SUBMITTED' AND outstanding_amount>0 AND due_date<=? AND NOT EXISTS(SELECT 1 FROM project_company_finance_dunning d WHERE d.company_key_hash=i.company_key_hash AND d.invoice_key=i.invoice_key AND d.dunning_type_key=? AND d.dunning_date=?) ORDER BY due_date,invoice_no",[$hash,$cutoff,$dunningTypeKey,$date]);if($db->BeginTrans()===false)throw new RuntimeException('Overdue processing transaction could not start.');try{$created=[];foreach($keys as $invoiceKey)$created[]=yovel_admin_finance_create_dunning($db,$company,$admin,(string)$invoiceKey,$dunningTypeKey,$date,false);if($db->CommitTrans()===false)throw new RuntimeException('Overdue processing transaction could not commit.');return ['processed_count'=>count($created),'dunnings'=>$created];}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_finance_process_statement_of_accounts(ADOConnection $db,array $company,array $admin,array $input,?array $dependencyOverrides=null):array
{
    yovel_admin_finance_invoice_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$date=yovel_admin_optional_date((string)($input['statement_date']??''),'Statement date');$from=yovel_admin_optional_date((string)($input['date_from']??''),'Statement start date');$to=yovel_admin_optional_date((string)($input['date_to']??''),'Statement end date');$customerKeys=is_array($input['customer_keys']??null)?array_values(array_unique(array_map('strval',$input['customer_keys']))):[];$cc=is_array($input['cc']??null)?array_values(array_unique(array_map('strval',$input['cc']))):[];if($date===''||$from===''||$to===''||$from>$to||$customerKeys===[])throw new InvalidArgumentException('Statement dates and Customers are required.');$services=yovel_admin_finance_invoice_dependency_gateway($dependencyOverrides??[]);$customers=[];foreach($customerKeys as $key)$customers[$key]=yovel_admin_finance_invoice_owner_record($company,'sales-crm.customer-reference.v1',$key,$services);foreach($cc as $email)if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Statement CC address is invalid.');if($db->BeginTrans()===false)throw new RuntimeException('Statement processing transaction could not start.');try{$processKey=bx_uuid();yovel_admin_db_execute($db,"INSERT INTO project_company_finance_statement_process(statement_process_key,company_key,company_key_hash,statement_date,date_from,date_to,aging_basis,process_status,created_by_admin_key) VALUES(?,?,?,?,?,?,'DUE_DATE','PROCESSED',?)",[$processKey,$companyKey,$hash,$date,$from,$to,$adminKey],'Statement process create');$total='0.000000';foreach($customers as $customerKey=>$customer){$rows=$db->GetAll("SELECT invoice_key,invoice_no,posting_date,due_date,grand_total,outstanding_amount FROM project_company_finance_invoice WHERE company_key_hash=? AND document_type='SALES' AND party_key=? AND document_status='SUBMITTED' AND posting_date<=? ORDER BY posting_date,invoice_no",[$hash,$customerKey,$to]);$opening='0.000000';$invoiced='0.000000';$outstanding='0.000000';foreach($rows as $row){if((string)$row['posting_date']<$from)$opening=bcadd($opening,(string)$row['outstanding_amount'],6);else $invoiced=bcadd($invoiced,(string)$row['grand_total'],6);$outstanding=bcadd($outstanding,(string)$row['outstanding_amount'],6);}$snapshot=yovel_admin_finance_canonical_json(['customer_key'=>$customerKey,'date_from'=>$from,'date_to'=>$to,'rows'=>$rows]);yovel_admin_db_execute($db,'INSERT INTO project_company_finance_statement_customer(statement_customer_key,statement_process_key,company_key,company_key_hash,customer_key,customer_name,opening_balance,invoiced_amount,outstanding_amount,statement_snapshot_json) VALUES(?,?,?,?,?,?,?,?,?,?)',[bx_uuid(),$processKey,$companyKey,$hash,$customerKey,$customer['customer_name']??$customer['party_name']??$customerKey,$opening,$invoiced,$outstanding,$snapshot],'Statement Customer create');$total=bcadd($total,$outstanding,6);}foreach($cc as $email)yovel_admin_db_execute($db,'INSERT INTO project_company_finance_statement_cc(statement_cc_key,statement_process_key,company_key,company_key_hash,email_address) VALUES(?,?,?,?,?)',[bx_uuid(),$processKey,$companyKey,$hash,strtolower($email)],'Statement CC create');$saved=$db->GetRow('SELECT * FROM project_company_finance_statement_process WHERE company_key_hash=? AND statement_process_key=?',[$hash,$processKey]);$saved['customers']=$db->GetAll('SELECT * FROM project_company_finance_statement_customer WHERE company_key_hash=? AND statement_process_key=? ORDER BY customer_name',[$hash,$processKey]);$saved['cc']=$db->GetAll('SELECT email_address FROM project_company_finance_statement_cc WHERE company_key_hash=? AND statement_process_key=? ORDER BY email_address',[$hash,$processKey]);if(!$saved||count($saved['customers'])!==count($customers)||count($saved['cc'])!==count($cc))throw new RuntimeException('Statement processing read-back verification failed.');bx_audit('PROCESS','project_company_finance_statement_process',$processKey,['company_key'=>$companyKey,'customer_count'=>count($customers),'outstanding_total'=>$total,'admin_key'=>$adminKey],'Company administrator processed Statements of Account.');if($db->CommitTrans()===false)throw new RuntimeException('Statement processing transaction could not commit.');return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_finance_discount_invoice(ADOConnection $db,array $company,array $admin,array $input):array
{
    yovel_admin_finance_invoice_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$invoiceKey=trim((string)($input['invoice_key']??''));$date=yovel_admin_optional_date((string)($input['discount_date']??''),'Discount date');$lender=substr(trim((string)($input['lender_reference']??'')),0,180);$amount=yovel_admin_finance_money($input['discounted_amount']??'0');$charge=yovel_admin_finance_money($input['discount_charge']??'0');if(!yovel_admin_is_uuid($invoiceKey)||$date===''||$lender===''||bccomp($amount,'0',6)!==1||bccomp($charge,'0',6)===-1||bccomp($charge,$amount,6)!==-1)throw new InvalidArgumentException('Invoice Discounting fields are invalid.');$net=yovel_admin_finance_money(bcsub($amount,$charge,6));if($db->BeginTrans()===false)throw new RuntimeException('Invoice Discounting transaction could not start.');try{$invoice=$db->GetRow("SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? AND document_type='SALES' AND document_status='SUBMITTED' AND outstanding_amount>0 FOR UPDATE",[$hash,$invoiceKey]);if(!$invoice||bccomp($amount,(string)$invoice['outstanding_amount'],6)===1)throw new InvalidArgumentException('Invoice Discounting requires an open Submitted Sales Invoice within its outstanding balance.');$existing=$db->GetRow('SELECT * FROM project_company_finance_invoice_discounting WHERE company_key_hash=? AND invoice_key=? AND lender_reference=? FOR UPDATE',[$hash,$invoiceKey,$lender]);if($existing)throw new InvalidArgumentException('Invoice Discounting reference already exists.');$key=bx_uuid();yovel_admin_db_execute($db,"INSERT INTO project_company_finance_invoice_discounting(invoice_discounting_key,company_key,company_key_hash,invoice_key,discount_date,lender_reference,discounted_amount,discount_charge,net_proceeds,discounting_status,created_by_admin_key,updated_by_admin_key) VALUES(?,?,?,?,?,?,?,?,?,'SUBMITTED',?,?)",[$key,$companyKey,$hash,$invoiceKey,$date,$lender,$amount,$charge,$net,$adminKey,$adminKey],'Invoice Discounting create');$saved=$db->GetRow('SELECT * FROM project_company_finance_invoice_discounting WHERE company_key_hash=? AND invoice_discounting_key=?',[$hash,$key]);if(!$saved||(string)$saved['net_proceeds']!==$net)throw new RuntimeException('Invoice Discounting read-back verification failed.');bx_audit('SUBMIT','project_company_finance_invoice_discounting',$key,['company_key'=>$companyKey,'invoice_key'=>$invoiceKey,'net_proceeds'=>$net,'admin_key'=>$adminKey],'Company administrator submitted Invoice Discounting.');if($db->CommitTrans()===false)throw new RuntimeException('Invoice Discounting transaction could not commit.');return $saved;}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_finance_create_opening_invoices(ADOConnection $db,array $company,array $admin,array $input,?array $dependencyOverrides=null):array
{
    yovel_admin_finance_invoice_schema();[$companyKey,$hash,$adminKey]=yovel_admin_finance_scope($company,$admin);$reference=substr(trim((string)($input['batch_reference']??'')),0,120);$rows=is_array($input['invoices']??null)?$input['invoices']:[];if($reference===''||$rows===[]||count($rows)>500)throw new InvalidArgumentException('Opening Invoice batch reference and rows are required.');if($db->BeginTrans()===false)throw new RuntimeException('Opening Invoice transaction could not start.');try{if((int)$db->GetOne('SELECT COUNT(*) FROM project_company_finance_opening_invoice_batch WHERE company_key_hash=? AND batch_reference=? FOR UPDATE',[$hash,$reference])>0)throw new InvalidArgumentException('Opening Invoice batch reference already exists.');$batchKey=bx_uuid();$created=[];$total='0.000000';foreach(array_values($rows) as $index=>$row){if(!is_array($row))throw new InvalidArgumentException('Opening Invoice rows must be structured.');$type=yovel_admin_finance_invoice_type((string)($row['document_type']??''));$row['is_opening']=1;$saved=yovel_admin_persist_finance_invoice($db,$company,$admin,$type,$row,$dependencyOverrides,false);$created[]=$saved;$total=bcadd($total,yovel_admin_finance_abs((string)$saved['grand_total']),6);yovel_admin_db_execute($db,'INSERT INTO project_company_finance_opening_invoice_item(opening_invoice_item_key,opening_invoice_batch_key,invoice_key,company_key,company_key_hash,row_no,opening_amount) VALUES(?,?,?,?,?,?,?)',[bx_uuid(),$batchKey,$saved['invoice_key'],$companyKey,$hash,$index+1,$saved['grand_total']],'Opening Invoice item create');}yovel_admin_db_execute($db,"INSERT INTO project_company_finance_opening_invoice_batch(opening_invoice_batch_key,company_key,company_key_hash,batch_reference,row_count,total_amount,batch_status,created_by_admin_key) VALUES(?,?,?,?,?,?,'CREATED',?)",[$batchKey,$companyKey,$hash,$reference,count($created),$total,$adminKey],'Opening Invoice batch create');$saved=$db->GetRow('SELECT * FROM project_company_finance_opening_invoice_batch WHERE company_key_hash=? AND opening_invoice_batch_key=?',[$hash,$batchKey]);if(!$saved||(int)$saved['row_count']!==count($created))throw new RuntimeException('Opening Invoice batch read-back verification failed.');bx_audit('CREATE','project_company_finance_opening_invoice_batch',$batchKey,['company_key'=>$companyKey,'batch_reference'=>$reference,'row_count'=>count($created),'admin_key'=>$adminKey],'Company administrator created Opening Invoice drafts.');if($db->CommitTrans()===false)throw new RuntimeException('Opening Invoice transaction could not commit.');return ['batch'=>$saved,'invoices'=>$created];}catch(Throwable $error){$db->RollbackTrans();throw $error;}
}

function yovel_admin_finance_invoices(array $company,string $type,array $filters=[]):array
{
    yovel_admin_finance_invoice_schema();$type=yovel_admin_finance_invoice_type($type);$params=[(string)($company['company_key_hash']??''),$type];$where=['company_key_hash=?','document_type=?'];$status=strtoupper(trim((string)($filters['document_status']??'')));if(in_array($status,['DRAFT','SUBMITTED','CANCELLED'],true)){$where[]='document_status=?';$params[]=$status;}$rows=bx_db()->GetAll('SELECT * FROM project_company_finance_invoice WHERE '.implode(' AND ',$where).' ORDER BY posting_date DESC,x_id DESC LIMIT 1000',$params);return is_array($rows)?$rows:[];
}

function yovel_admin_invoice_outstanding(array $company,string $key):string
{
    if(!yovel_admin_is_uuid($key))return '0.000000';$value=bx_db()->GetOne("SELECT outstanding_amount FROM project_company_finance_invoice WHERE company_key_hash=? AND invoice_key=? AND document_status='SUBMITTED'",[(string)($company['company_key_hash']??''),$key]);return yovel_admin_finance_money($value??'0');
}

function yovel_admin_finance_aging(array $company,string $type,string $asOf):array
{
    yovel_admin_finance_invoice_schema();$type=yovel_admin_finance_invoice_type($type);$asOf=yovel_admin_optional_date($asOf,'Aging date');if($asOf==='')throw new InvalidArgumentException('Aging date is required.');$rows=bx_db()->GetAll("SELECT * FROM project_company_finance_invoice WHERE company_key_hash=? AND document_type=? AND document_status='SUBMITTED' AND posting_date<=? AND outstanding_amount<>0 ORDER BY due_date,invoice_no",[(string)$company['company_key_hash'],$type,$asOf]);$total='0.000000';$buckets=['current'=>'0.000000','1_30'=>'0.000000','31_60'=>'0.000000','61_90'=>'0.000000','over_90'=>'0.000000'];foreach($rows as &$row){$days=(int)(new DateTimeImmutable((string)$row['due_date']))->diff(new DateTimeImmutable($asOf))->format('%r%a');$bucket=$days<=0?'current':($days<=30?'1_30':($days<=60?'31_60':($days<=90?'61_90':'over_90')));$row['age_days']=max(0,$days);$row['aging_bucket']=$bucket;$total=bcadd($total,(string)$row['outstanding_amount'],6);$buckets[$bucket]=bcadd($buckets[$bucket],(string)$row['outstanding_amount'],6);}unset($row);return ['as_of'=>$asOf,'rows'=>$rows,'total_outstanding'=>yovel_admin_finance_money($total),'buckets'=>$buckets];
}
function yovel_admin_receivable_aging(array $company,string $asOf):array{return yovel_admin_finance_aging($company,'SALES',$asOf);}
function yovel_admin_payable_aging(array $company,string $asOf):array{return yovel_admin_finance_aging($company,'PURCHASE',$asOf);}

function yovel_admin_finance_json_rows_from_post(string $field):array{$json=trim((string)($_POST[$field]??''));if($json==='')return []; $rows=json_decode($json,true,512,JSON_THROW_ON_ERROR);if(!is_array($rows))throw new InvalidArgumentException('Finance JSON rows are invalid.');return $rows;}
function yovel_admin_finance_invoice_lines_from_post():array{return yovel_admin_finance_json_rows_from_post('lines_json');}
function yovel_admin_save_finance_invoice(array $company,array $admin):string{return yovel_admin_finance_run_form_action($company,'save_finance_invoice',static function()use($company,$admin):string{$input=$_POST;$operation=strtolower(trim((string)($input['invoice_operation']??'invoice')));$type=(string)($input['document_type']??'SALES');$db=bx_db();if(in_array($operation,['invoice','amend','return'],true)){$input['lines']=yovel_admin_finance_invoice_lines_from_post();$input['payment_terms']=yovel_admin_finance_json_rows_from_post('payment_terms_json');$input['advances']=yovel_admin_finance_json_rows_from_post('advances_json');$input['references']=yovel_admin_finance_json_rows_from_post('references_json');if($operation==='amend')$saved=yovel_admin_amend_finance_invoice($db,$company,$admin,$type,(string)($input['original_invoice_key']??''),$input);elseif($operation==='return')$saved=yovel_admin_create_finance_return($db,$company,$admin,$type,(string)($input['original_invoice_key']??''),$input);else $saved=yovel_admin_persist_finance_invoice($db,$company,$admin,$type,$input);$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['invoice_key'];return ucfirst(strtolower($type)).' Invoice Draft saved.';}if($operation==='payment_terms_template'){$input['terms']=yovel_admin_finance_json_rows_from_post('payment_terms_json');$saved=yovel_admin_finance_save_payment_terms_template($db,$company,$admin,$input);$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['payment_terms_template_key'];return 'Payment Terms Template saved.';}if($operation==='dunning_type'){$saved=yovel_admin_finance_save_dunning_type($db,$company,$admin,$input);$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['dunning_type_key'];return 'Dunning Type and letter saved.';}if($operation==='dunning'){$saved=yovel_admin_finance_create_dunning($db,$company,$admin,(string)($input['invoice_key']??''),(string)($input['dunning_type_key']??''),(string)($input['dunning_date']??date('Y-m-d')));$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['dunning_key'];return 'Dunning notice issued.';}if($operation==='overdue'){$saved=yovel_admin_finance_process_overdue_invoices($db,$company,$admin,(string)($input['dunning_type_key']??''),(string)($input['dunning_date']??date('Y-m-d')));return (string)$saved['processed_count'].' overdue invoice(s) processed.';}if($operation==='statement'){$input['customer_keys']=array_values(array_filter(array_map('trim',explode(',',(string)($input['customer_keys_csv']??'')))));$input['cc']=array_values(array_filter(array_map('trim',explode(',',(string)($input['cc_csv']??'')))));$saved=yovel_admin_finance_process_statement_of_accounts($db,$company,$admin,$input);$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['statement_process_key'];return 'Statements of Account processed.';}if($operation==='discounting'){$saved=yovel_admin_finance_discount_invoice($db,$company,$admin,$input);$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['invoice_discounting_key'];return 'Invoice Discounting submitted.';}if($operation==='opening'){$input['invoices']=yovel_admin_finance_json_rows_from_post('opening_invoices_json');$saved=yovel_admin_finance_create_opening_invoices($db,$company,$admin,$input);$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['batch']['opening_invoice_batch_key'];return 'Opening Invoice batch created.';}throw new InvalidArgumentException('Finance Invoice operation is invalid.');});}
function yovel_admin_save_finance_supplier(array $company,array $admin):string{return yovel_admin_finance_run_form_action($company,'save_finance_supplier',static function()use($company,$admin):string{$saved=yovel_admin_persist_finance_supplier(bx_db(),$company,$admin,$_POST);$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['supplier_key'];return 'Supplier saved.';});}
function yovel_admin_submit_invoice_action(array $company,array $admin):string{return yovel_admin_finance_run_form_action($company,'submit_finance_invoice',static function()use($company,$admin):string{$type=(string)($_POST['document_type']??'SALES');$saved=yovel_admin_submit_finance_invoice(bx_db(),$company,$admin,$type,(string)($_POST['invoice_key']??''));$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['invoice_key'];return ucfirst(strtolower($type)).' Invoice submitted and posted.';});}
function yovel_admin_cancel_invoice_action(array $company,array $admin):string{return yovel_admin_finance_run_form_action($company,'cancel_finance_invoice',static function()use($company,$admin):string{$type=(string)($_POST['document_type']??'SALES');$saved=yovel_admin_cancel_finance_invoice(bx_db(),$company,$admin,$type,(string)($_POST['invoice_key']??''),(string)($_POST['cancellation_posting_date']??date('Y-m-d')),(string)($_POST['cancellation_reason']??''));$GLOBALS['yovel_admin_saved_finance_document_key']=$saved['invoice_key'];return ucfirst(strtolower($type)).' Invoice cancelled with a reversal.';});}
