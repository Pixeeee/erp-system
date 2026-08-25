<?php
declare(strict_types=1);

function yovel_admin_accounting_finance_sections(): array
{
    return [
        'dashboard' => ['label' => 'Finance Dashboard', 'record_type' => '', 'icon' => '▦', 'description' => 'Review accounting readiness, shortcuts, reports, and the Finance Form Builder workspace.'],
        'chart-of-accounts' => ['label' => 'Chart of accounts', 'record_type' => 'account', 'icon' => '◫', 'description' => 'Build and maintain the account tree used by Accounting/Finance records.'],
        'cost-centers' => ['label' => 'Cost centers', 'record_type' => 'cost-center', 'icon' => '▧', 'description' => 'Organize cost tracking units for budgets, invoices, journals, and reports.'],
        'accounting-dimensions' => ['label' => 'Accounting dimensions', 'record_type' => 'accounting-dimension', 'icon' => '◇', 'description' => 'Define extra accounting segments for reporting and posting controls.'],
        'sales-invoices' => ['label' => 'Sales invoices', 'record_type' => 'sales-invoice', 'icon' => '▤', 'description' => 'Prepare receivable documents for customer billing.'],
        'purchase-invoices' => ['label' => 'Purchase invoices', 'record_type' => 'purchase-invoice', 'icon' => '▥', 'description' => 'Record payable documents for supplier billing.'],
        'journal-entries' => ['label' => 'Journal entries', 'record_type' => 'journal-entry', 'icon' => '▦', 'description' => 'Capture balanced debit and credit accounting adjustments.'],
        'payment-entries' => ['label' => 'Payment entries', 'record_type' => 'payment-entry', 'icon' => '◈', 'description' => 'Record incoming and outgoing payment movement.'],
        'bank-accounts' => ['label' => 'Bank accounts', 'record_type' => 'bank-account', 'icon' => '▣', 'description' => 'Maintain company bank and cash account setup.'],
        'bank-reconciliation' => ['label' => 'Bank reconciliation', 'record_type' => 'bank-reconciliation', 'icon' => '↻', 'description' => 'Match bank transactions to payment and ledger records.'],
        'budgets' => ['label' => 'Budgets', 'record_type' => 'budget', 'icon' => '◌', 'description' => 'Control planned amounts by account, cost center, and fiscal period.'],
        'period-closing' => ['label' => 'Period closing', 'record_type' => 'period-closing', 'icon' => '◷', 'description' => 'Prepare fiscal period locks and closing controls.'],
        'general-ledger' => ['label' => 'General ledger', 'record_type' => '', 'icon' => '▤', 'description' => 'Trace committed debit and credit postings back to their source vouchers.'],
        'profit-loss' => ['label' => 'Profit/loss', 'record_type' => 'profit-loss-report', 'icon' => '▥', 'description' => 'Review income and expense performance for selected periods.'],
        'balance-sheet' => ['label' => 'Balance sheet', 'record_type' => 'balance-sheet-report', 'icon' => '▤', 'description' => 'Review assets, liabilities, and equity balances.'],
        'cash-flow' => ['label' => 'Cash flow', 'record_type' => 'cash-flow-report', 'icon' => '▦', 'description' => 'Review operating, investing, and financing cash movement.'],
        'tax-reports' => ['label' => 'Tax reports', 'record_type' => 'tax-report', 'icon' => '§', 'description' => 'Prepare taxable sales, purchases, withholding, and statutory summaries.'],
    ];
}

function yovel_admin_accounting_finance_section(): string
{
    $section = yovel_admin_slug((string) ($_GET['section'] ?? 'dashboard'));
    return array_key_exists($section, yovel_admin_accounting_finance_sections()) ? $section : 'dashboard';
}

function yovel_admin_finance_form_modal_id(string $action, array $input = []): string
{
    return match ($action) {
        'save_accounting_account' => 'yovel-account-modal',
        'save_accounting_form_schema', 'reset_accounting_form_schema' => 'yovel-account-builder-modal',
        'save_finance_builder_form' => 'yovel-finance-builder-modal',
        'save_finance_grid_view' => 'yovel-finance-grid-view-modal',
        'save_finance_grid_formula' => 'yovel-finance-grid-formula-modal',
        'import_finance_accounts' => 'yovel-finance-import-modal',
        'save_finance_settings' => 'finance-settings-modal',
        'save_finance_master' => match ((string) ($input['master_type'] ?? '')) {
            'tax-code' => 'finance-tax-code-modal',
            'chart-template-install' => 'finance-chart-template-modal',
            'account-category', 'account-closing-balance', 'dimension-filter', 'accounting-period', 'fiscal-year', 'finance-book', 'monthly-distribution', 'cost-center-allocation', 'currency-exchange-setting' => 'finance-foundation-modal',
            default => 'finance-master-modal',
        },
        'save_finance_journal' => match ((string)($input['journal_operation'] ?? 'journal')) {
            'template' => 'finance-journal-template-modal',
            'amend' => 'finance-journal-repair-modal',
            'ledger_health', 'ledger_health_monitor' => 'finance-ledger-health-modal',
            'ledger_merge' => 'finance-ledger-merge-modal',
            'ledger_repost' => 'finance-ledger-repost-modal',
            default => 'finance-journal-modal',
        },
        'save_finance_invoice' => 'finance-invoice-modal',
        'save_finance_supplier' => 'finance-supplier-modal',
        'save_finance_payment' => 'finance-payment-modal',
        'import_finance_bank_statement' => 'finance-bank-import-modal',
        'reconcile_finance_bank' => 'finance-bank-match-modal',
        'unreconcile_finance_bank' => 'finance-unreconcile-modal',
        'save_finance_budget' => 'finance-budget-modal',
        'close_finance_period' => 'finance-close-modal',
        'reopen_finance_period' => 'finance-close-modal',
        'submit_finance_journal', 'cancel_finance_journal' => 'finance-journal-lifecycle',
        'submit_finance_invoice', 'cancel_finance_invoice' => 'finance-invoice-lifecycle',
        'submit_finance_payment', 'cancel_finance_payment' => 'finance-payment-lifecycle',
        default => '',
    };
}

function yovel_admin_finance_form_state_values(array $input): array
{
    $sanitize = static function (mixed $value, int $depth = 0) use (&$sanitize): mixed {
        if ($depth > 4) {
            return null;
        }
        if (is_array($value)) {
            $result = [];
            foreach (array_slice($value, 0, 200, true) as $key => $item) {
                $result[(string) $key] = $sanitize($item, $depth + 1);
            }
            return $result;
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            return substr($value, 0, 20000);
        }
        return '';
    };

    unset($input['csrf'], $input['password'], $input['password_confirmation']);
    return $sanitize($input);
}

function yovel_admin_finance_capture_form_state(array $company, string $action, string $modalId = '', ?array $input = null): void
{
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    if ($companyKeyHash === '' || strlen($companyKeyHash) > 64) {
        return;
    }
    $values = yovel_admin_finance_form_state_values($input ?? $_POST);
    $modalId = $modalId !== '' ? $modalId : yovel_admin_finance_form_modal_id($action, $values);
    if ($modalId === '') {
        return;
    }
    $_SESSION['builderx_finance_form_state'][$companyKeyHash] = [
        'action' => $action,
        'modal_id' => $modalId,
        'values' => $values,
    ];
}

function yovel_admin_finance_clear_form_state(array $company): void
{
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    unset($_SESSION['builderx_finance_form_state'][$companyKeyHash]);
}

function yovel_admin_finance_take_form_state(array $company): array
{
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $state = $_SESSION['builderx_finance_form_state'][$companyKeyHash] ?? [];
    unset($_SESSION['builderx_finance_form_state'][$companyKeyHash]);

    return is_array($state) ? $state : [];
}

function yovel_admin_finance_run_form_action(array $company, string $action, callable $operation): mixed
{
    yovel_admin_finance_capture_form_state($company, $action);
    $result = $operation();
    yovel_admin_finance_clear_form_state($company);

    return $result;
}

function yovel_admin_accounting_finance_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_form_schema (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_schema_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            module_code VARCHAR(80) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            schema_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            schema_version INT UNSIGNED NOT NULL DEFAULT 1,
            schema_json LONGTEXT NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_form_schema_version (company_key_hash, module_code, record_type, schema_version),
            INDEX idx_project_company_form_schema_active (company_key_hash, module_code, record_type, schema_status, schema_version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_form_schema_audit (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_schema_audit_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            form_schema_key CHAR(36) NOT NULL,
            module_code VARCHAR(80) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            audit_action VARCHAR(40) NOT NULL,
            previous_schema_json LONGTEXT NULL,
            next_schema_json LONGTEXT NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project_company_form_schema_audit_form (company_key_hash, form_schema_key),
            INDEX idx_project_company_form_schema_audit_record (company_key_hash, module_code, record_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_form_custom_value (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            custom_value_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            module_code VARCHAR(80) NOT NULL,
            record_type VARCHAR(80) NOT NULL,
            record_key CHAR(36) NOT NULL,
            field_key VARCHAR(80) NOT NULL,
            field_value TEXT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_form_custom_value (company_key_hash, module_code, record_type, record_key, field_key),
            INDEX idx_project_company_form_custom_value_record (company_key_hash, module_code, record_type, record_key),
            INDEX idx_project_company_form_custom_value_field (company_key_hash, module_code, record_type, field_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_accounting_account (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            account_code VARCHAR(80) NOT NULL,
            account_number VARCHAR(80) NULL,
            account_name VARCHAR(180) NOT NULL,
            parent_account_key CHAR(36) NULL,
            root_type ENUM('ASSET','LIABILITY','INCOME','EXPENSE','EQUITY') NOT NULL,
            report_type ENUM('BALANCE_SHEET','PROFIT_LOSS') NOT NULL,
            account_type VARCHAR(80) NULL,
            account_currency VARCHAR(20) NULL,
            is_group TINYINT(1) NOT NULL DEFAULT 0,
            tax_rate DECIMAL(9,4) NULL,
            balance_must_be ENUM('DEBIT','CREDIT','EITHER') NOT NULL DEFAULT 'EITHER',
            freeze_account TINYINT(1) NOT NULL DEFAULT 0,
            include_in_gross TINYINT(1) NOT NULL DEFAULT 0,
            account_status ENUM('DRAFT','ACTIVE','FROZEN','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            account_notes TEXT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_account_code (company_key_hash, account_code),
            UNIQUE KEY uq_project_company_account_number (company_key_hash, account_number),
            INDEX idx_project_company_account_parent (company_key_hash, parent_account_key),
            INDEX idx_project_company_account_root (company_key_hash, root_type),
            INDEX idx_project_company_account_type (company_key_hash, account_type),
            INDEX idx_project_company_account_status (company_key_hash, account_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Accounting/Finance schema update');
    }
}

function yovel_admin_accounting_finance_default_form_schemas(): array
{
    $field = static function (string $key, string $label, string $type, string $section, bool $required, int $sortOrder, string $width = 'half', array $options = [], bool $system = false, string $storage = 'core'): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'section' => $section,
            'required' => $required,
            'visible' => true,
            'width' => $width,
            'sortOrder' => $sortOrder,
            'options' => $options,
            'system' => $system,
            'storage' => $storage,
        ];
    };

    $schemas = [
        'account' => [
            'recordType' => 'account',
            'version' => 1,
            'sections' => [
                ['key' => 'overview', 'label' => 'Overview', 'sortOrder' => 10],
                ['key' => 'classification', 'label' => 'Classification', 'sortOrder' => 20],
                ['key' => 'controls', 'label' => 'Controls', 'sortOrder' => 30],
                ['key' => 'notes', 'label' => 'Notes', 'sortOrder' => 40],
            ],
            'requiredSystemFields' => ['account_code', 'account_name', 'root_type', 'report_type', 'account_status'],
            'readonlySystemFields' => [],
            'fields' => [
                $field('account_code', 'Account code', 'text', 'overview', true, 10, 'third', [], true),
                $field('account_number', 'Account number', 'text', 'overview', false, 20, 'third'),
                $field('account_name', 'Account name', 'text', 'overview', true, 30, 'third', [], true),
                $field('parent_account_key', 'Parent account', 'select', 'overview', false, 40),
                $field('is_group', 'Is group', 'checkbox', 'overview', false, 50, 'third'),
                $field('root_type', 'Root type', 'select', 'classification', true, 60, 'third', ['ASSET', 'LIABILITY', 'INCOME', 'EXPENSE', 'EQUITY'], true),
                $field('report_type', 'Report type', 'select', 'classification', true, 70, 'third', ['BALANCE_SHEET', 'PROFIT_LOSS'], true),
                $field('account_type', 'Account type', 'select', 'classification', false, 80, 'third', ['Receivable', 'Payable', 'Bank', 'Cash', 'Tax', 'Stock', 'Expense Account', 'Income Account', 'Fixed Asset', 'Equity']),
                $field('account_category_key', 'Account Category', 'select', 'classification', false, 85, 'third'),
                $field('account_currency', 'Currency', 'text', 'classification', false, 90, 'third'),
                $field('tax_rate', 'Tax rate', 'number', 'controls', false, 100, 'third'),
                $field('balance_must_be', 'Balance must be', 'select', 'controls', false, 110, 'third', ['DEBIT', 'CREDIT', 'EITHER']),
                $field('freeze_account', 'Freeze account', 'checkbox', 'controls', false, 120, 'third'),
                $field('include_in_gross', 'Include in gross', 'checkbox', 'controls', false, 130, 'third'),
                $field('account_status', 'Status', 'select', 'controls', true, 140, 'third', ['DRAFT', 'ACTIVE', 'FROZEN', 'INACTIVE'], true),
                $field('sort_order', 'Sort order', 'number', 'controls', false, 150, 'third'),
                $field('account_notes', 'Notes', 'textarea', 'notes', false, 160, 'full'),
            ],
        ],
    ];

    $standard = static function (string $recordType, string $title, array $fields) use ($field): array {
        $fieldPrefix = str_replace('-', '_', $recordType);
        $schemaFields = [
            $field($fieldPrefix . '_code', $title . ' code', 'text', 'overview', true, 10, 'third', [], true),
            $field($fieldPrefix . '_name', $title . ' name', 'text', 'overview', true, 20, 'third', [], true),
            $field($fieldPrefix . '_status', 'Status', 'select', 'overview', true, 30, 'third', ['DRAFT', 'ACTIVE', 'INACTIVE'], true),
        ];
        foreach ($fields as $index => $extraField) {
            $schemaFields[] = $field(
                (string) $extraField[0],
                (string) $extraField[1],
                (string) ($extraField[2] ?? 'text'),
                (string) ($extraField[3] ?? 'details'),
                (bool) ($extraField[4] ?? false),
                40 + ($index * 10),
                (string) ($extraField[5] ?? 'half'),
                (array) ($extraField[6] ?? [])
            );
        }

        return [
            'recordType' => $recordType,
            'version' => 1,
            'sections' => [
                ['key' => 'overview', 'label' => 'Overview', 'sortOrder' => 10],
                ['key' => 'details', 'label' => 'Details', 'sortOrder' => 20],
                ['key' => 'controls', 'label' => 'Controls', 'sortOrder' => 30],
            ],
            'requiredSystemFields' => [$fieldPrefix . '_code', $fieldPrefix . '_name', $fieldPrefix . '_status'],
            'readonlySystemFields' => [],
            'fields' => $schemaFields,
        ];
    };

    $document = static function (string $recordType, string $title, array $fields) use ($field): array {
        $fieldPrefix = str_replace('-', '_', $recordType);
        $schemaFields = [
            $field($fieldPrefix . '_number', $title . ' number', 'text', 'overview', true, 10, 'third', [], true),
            $field('posting_date', 'Posting date', 'date', 'overview', true, 20, 'third', [], true),
            $field($fieldPrefix . '_status', 'Status', 'select', 'overview', true, 30, 'third', ['DRAFT', 'SUBMITTED', 'CANCELLED'], true),
        ];
        foreach ($fields as $index => $extraField) {
            $schemaFields[] = $field(
                (string) $extraField[0],
                (string) $extraField[1],
                (string) ($extraField[2] ?? 'text'),
                (string) ($extraField[3] ?? 'details'),
                (bool) ($extraField[4] ?? false),
                40 + ($index * 10),
                (string) ($extraField[5] ?? 'half'),
                (array) ($extraField[6] ?? [])
            );
        }

        return [
            'recordType' => $recordType,
            'version' => 1,
            'sections' => [
                ['key' => 'overview', 'label' => 'Overview', 'sortOrder' => 10],
                ['key' => 'details', 'label' => 'Details', 'sortOrder' => 20],
                ['key' => 'controls', 'label' => 'Controls', 'sortOrder' => 30],
            ],
            'requiredSystemFields' => [$fieldPrefix . '_number', 'posting_date', $fieldPrefix . '_status'],
            'readonlySystemFields' => [],
            'fields' => $schemaFields,
        ];
    };

    $report = static function (string $recordType, string $title, array $fields) use ($field): array {
        $schemaFields = [
            $field('company', 'Company', 'text', 'filters', true, 10, 'third', [], true),
            $field('from_date', 'From date', 'date', 'filters', false, 20, 'third'),
            $field('to_date', 'To date', 'date', 'filters', true, 30, 'third', [], true),
        ];
        foreach ($fields as $index => $extraField) {
            $schemaFields[] = $field(
                (string) $extraField[0],
                (string) $extraField[1],
                (string) ($extraField[2] ?? 'text'),
                (string) ($extraField[3] ?? 'filters'),
                (bool) ($extraField[4] ?? false),
                40 + ($index * 10),
                (string) ($extraField[5] ?? 'half'),
                (array) ($extraField[6] ?? [])
            );
        }

        return [
            'recordType' => $recordType,
            'version' => 1,
            'sections' => [
                ['key' => 'filters', 'label' => $title . ' Filters', 'sortOrder' => 10],
                ['key' => 'display', 'label' => 'Display', 'sortOrder' => 20],
            ],
            'requiredSystemFields' => ['company', 'to_date'],
            'readonlySystemFields' => [],
            'fields' => $schemaFields,
        ];
    };

    $schemas['cost-center'] = $standard('cost-center', 'Cost center', [
        ['parent_cost_center', 'Parent cost center', 'text', 'details', false, 'half'],
        ['is_group', 'Is group', 'checkbox', 'details', false, 'third'],
        ['cost_center_owner', 'Owner', 'text', 'details', false, 'third'],
        ['notes', 'Notes', 'textarea', 'controls', false, 'full'],
    ]);
    $schemas['accounting-dimension'] = $standard('accounting-dimension', 'Accounting dimension', [
        ['reference_document', 'Reference document', 'text', 'details', true, 'half'],
        ['mandatory_for_profit_loss', 'Mandatory for P/L', 'checkbox', 'controls', false, 'third'],
        ['mandatory_for_balance_sheet', 'Mandatory for Balance Sheet', 'checkbox', 'controls', false, 'third'],
        ['disabled', 'Disabled', 'checkbox', 'controls', false, 'third'],
    ]);
    $schemas['sales-invoice'] = $document('sales-invoice', 'Sales invoice', [
        ['customer', 'Customer', 'text', 'details', true, 'half'],
        ['due_date', 'Due date', 'date', 'details', false, 'third'],
        ['receivable_account', 'Receivable account', 'text', 'details', false, 'half'],
        ['remarks', 'Remarks', 'textarea', 'controls', false, 'full'],
    ]);
    $schemas['purchase-invoice'] = $document('purchase-invoice', 'Purchase invoice', [
        ['supplier', 'Supplier', 'text', 'details', true, 'half'],
        ['due_date', 'Due date', 'date', 'details', false, 'third'],
        ['payable_account', 'Payable account', 'text', 'details', false, 'half'],
        ['remarks', 'Remarks', 'textarea', 'controls', false, 'full'],
    ]);
    $schemas['journal-entry'] = $document('journal-entry', 'Journal entry', [
        ['voucher_type', 'Voucher type', 'select', 'details', true, 'third', ['Journal Entry', 'Bank Entry', 'Cash Entry', 'Opening Entry']],
        ['reference_number', 'Reference number', 'text', 'details', false, 'third'],
        ['user_remark', 'User remark', 'textarea', 'controls', false, 'full'],
    ]);
    $schemas['payment-entry'] = $document('payment-entry', 'Payment entry', [
        ['payment_type', 'Payment type', 'select', 'details', true, 'third', ['Receive', 'Pay', 'Internal Transfer']],
        ['party_type', 'Party type', 'select', 'details', false, 'third', ['Customer', 'Supplier', 'Employee']],
        ['paid_amount', 'Paid amount', 'number', 'details', true, 'third'],
        ['reference_no', 'Reference number', 'text', 'controls', false, 'half'],
    ]);
    $schemas['bank-account'] = $standard('bank-account', 'Bank account', [
        ['bank', 'Bank', 'text', 'details', true, 'half'],
        ['account', 'Linked ledger account', 'text', 'details', false, 'half'],
        ['account_number', 'Bank account number', 'text', 'details', false, 'half'],
        ['is_default', 'Default account', 'checkbox', 'controls', false, 'third'],
    ]);
    $schemas['bank-reconciliation'] = $document('bank-reconciliation', 'Bank reconciliation', [
        ['bank_account', 'Bank account', 'text', 'details', true, 'half'],
        ['statement_date', 'Statement date', 'date', 'details', true, 'third'],
        ['difference_amount', 'Difference amount', 'number', 'details', false, 'third'],
        ['reconciliation_notes', 'Notes', 'textarea', 'controls', false, 'full'],
    ]);
    $schemas['budget'] = $standard('budget', 'Budget', [
        ['fiscal_year', 'Fiscal year', 'text', 'details', true, 'third'],
        ['cost_center', 'Cost center', 'text', 'details', false, 'half'],
        ['budget_against', 'Budget against', 'select', 'details', true, 'third', ['Cost Center', 'Project']],
        ['action_if_accumulated_monthly_budget_exceeded', 'Monthly over-budget action', 'select', 'controls', false, 'half', ['Stop', 'Warn', 'Ignore']],
    ]);
    $schemas['period-closing'] = $document('period-closing', 'Period closing', [
        ['fiscal_year', 'Fiscal year', 'text', 'details', true, 'third'],
        ['closing_account_head', 'Closing account', 'text', 'details', true, 'half'],
        ['remarks', 'Remarks', 'textarea', 'controls', false, 'full'],
    ]);
    $schemas['profit-loss-report'] = $report('profit-loss-report', 'Profit/loss', [
        ['cost_center', 'Cost center', 'text', 'filters', false, 'third'],
        ['finance_book', 'Finance book', 'text', 'filters', false, 'third'],
        ['show_zero_values', 'Show zero values', 'checkbox', 'display', false, 'third'],
    ]);
    $schemas['balance-sheet-report'] = $report('balance-sheet-report', 'Balance sheet', [
        ['finance_book', 'Finance book', 'text', 'filters', false, 'third'],
        ['presentation_currency', 'Presentation currency', 'text', 'filters', false, 'third'],
        ['show_unclosed_fy_pl_balances', 'Show unclosed P/L balances', 'checkbox', 'display', false, 'half'],
    ]);
    $schemas['cash-flow-report'] = $report('cash-flow-report', 'Cash flow', [
        ['finance_book', 'Finance book', 'text', 'filters', false, 'third'],
        ['presentation_currency', 'Presentation currency', 'text', 'filters', false, 'third'],
        ['include_default_book_entries', 'Include default book entries', 'checkbox', 'display', false, 'half'],
    ]);
    $schemas['tax-report'] = $report('tax-report', 'Tax report', [
        ['tax_account', 'Tax account', 'text', 'filters', false, 'third'],
        ['party_type', 'Party type', 'select', 'filters', false, 'third', ['Customer', 'Supplier']],
        ['summary_by_account', 'Summary by account', 'checkbox', 'display', false, 'third'],
    ]);

    return $schemas;
}

function yovel_admin_accounting_finance_record_type(string $recordType): string
{
    $recordType = yovel_admin_slug($recordType);
    return array_key_exists($recordType, yovel_admin_accounting_finance_default_form_schemas()) ? $recordType : 'account';
}

function yovel_admin_accounting_finance_field_key(string $value): string
{
    $fieldKey = strtolower(trim((string) preg_replace('/[^A-Za-z0-9_]+/', '_', $value), '_'));
    if (!preg_match('/^[a-z][a-z0-9_]{1,79}$/', $fieldKey)) {
        return '';
    }

    return $fieldKey;
}

function yovel_admin_accounting_finance_normalize_schema(string $recordType, array $schema, int $version): array
{
    $recordType = yovel_admin_accounting_finance_record_type($recordType);
    $default = yovel_admin_accounting_finance_default_form_schemas()[$recordType];
    $defaultFields = [];
    foreach ($default['fields'] as $field) {
        $defaultFields[(string) $field['key']] = $field;
    }
    $submittedFields = [];
    foreach (($schema['fields'] ?? []) as $field) {
        if (is_array($field) && isset($field['key'])) {
            $submittedFields[(string) $field['key']] = $field;
        }
    }

    $fields = [];
    foreach ($defaultFields as $key => $defaultField) {
        $submitted = $submittedFields[$key] ?? [];
        $isSystemRequired = in_array($key, $default['requiredSystemFields'], true);
        $label = trim((string) ($submitted['label'] ?? $defaultField['label']));
        $section = yovel_admin_slug((string) ($submitted['section'] ?? $defaultField['section']));
        $width = (string) ($submitted['width'] ?? $defaultField['width']);
        if (!in_array($width, ['full', 'half', 'third'], true)) {
            $width = (string) $defaultField['width'];
        }
        $fields[] = [
            'key' => $key,
            'label' => $label !== '' ? substr($label, 0, 120) : (string) $defaultField['label'],
            'type' => (string) $defaultField['type'],
            'section' => $section !== '' ? $section : (string) $defaultField['section'],
            'required' => $isSystemRequired || filter_var($submitted['required'] ?? $defaultField['required'], FILTER_VALIDATE_BOOLEAN),
            'visible' => $isSystemRequired || filter_var($submitted['visible'] ?? $defaultField['visible'], FILTER_VALIDATE_BOOLEAN),
            'width' => $width,
            'sortOrder' => max(0, (int) ($submitted['sortOrder'] ?? $defaultField['sortOrder'])),
            'options' => is_array($defaultField['options'] ?? null) ? $defaultField['options'] : [],
            'system' => (bool) ($defaultField['system'] ?? false),
            'storage' => 'core',
        ];
    }

    foreach ($submittedFields as $key => $submitted) {
        if (isset($defaultFields[$key])) {
            continue;
        }
        $customKey = yovel_admin_accounting_finance_field_key($key);
        if ($customKey === '') {
            continue;
        }
        $label = trim((string) ($submitted['label'] ?? $customKey));
        $type = strtolower((string) ($submitted['type'] ?? 'text'));
        if (!in_array($type, ['text', 'textarea', 'date', 'number', 'email', 'select', 'checkbox'], true)) {
            $type = 'text';
        }
        $section = yovel_admin_slug((string) ($submitted['section'] ?? 'custom'));
        $width = (string) ($submitted['width'] ?? 'half');
        if (!in_array($width, ['full', 'half', 'third'], true)) {
            $width = 'half';
        }
        $options = $submitted['options'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }
        $fields[] = [
            'key' => $customKey,
            'label' => $label !== '' ? substr($label, 0, 120) : $customKey,
            'type' => $type,
            'section' => $section !== '' ? $section : 'custom',
            'required' => filter_var($submitted['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'visible' => filter_var($submitted['visible'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'width' => $width,
            'sortOrder' => max(0, (int) ($submitted['sortOrder'] ?? 500)),
            'options' => array_values(array_filter(array_map('strval', $options), static fn (string $option): bool => trim($option) !== '')),
            'system' => false,
            'storage' => 'custom',
        ];
    }

    usort($fields, static fn (array $a, array $b): int => ((int) $a['sortOrder'] <=> (int) $b['sortOrder']) ?: strcmp((string) $a['label'], (string) $b['label']));

    return [
        'recordType' => $recordType,
        'version' => $version,
        'sections' => $default['sections'],
        'requiredSystemFields' => $default['requiredSystemFields'],
        'readonlySystemFields' => $default['readonlySystemFields'],
        'fields' => $fields,
    ];
}

function yovel_admin_accounting_finance_schema_json(array $schema): string
{
    return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function yovel_admin_accounting_finance_active_schema(array $company, string $recordType, ?array $admin = null): array
{
    yovel_admin_accounting_finance_schema();

    $recordType = yovel_admin_accounting_finance_record_type($recordType);
    $companyKeyHash = (string) $company['company_key_hash'];
    $row = bx_db()->GetRow(
        "SELECT * FROM project_company_form_schema
        WHERE company_key_hash = ? AND module_code = 'ACCOUNTING_FINANCE' AND record_type = ? AND schema_status = 'ACTIVE'
        ORDER BY schema_version DESC LIMIT 1",
        [$companyKeyHash, $recordType]
    );
    if (!$row) {
        yovel_admin_write_accounting_finance_form_schema($company, $admin, $recordType, yovel_admin_accounting_finance_default_form_schemas()[$recordType], 'SEED');
        $row = bx_db()->GetRow(
            "SELECT * FROM project_company_form_schema
            WHERE company_key_hash = ? AND module_code = 'ACCOUNTING_FINANCE' AND record_type = ? AND schema_status = 'ACTIVE'
            ORDER BY schema_version DESC LIMIT 1",
            [$companyKeyHash, $recordType]
        );
    }
    $decoded = json_decode((string) ($row['schema_json'] ?? ''), true);
    if (!is_array($decoded)) {
        $decoded = yovel_admin_accounting_finance_default_form_schemas()[$recordType];
    }

    return yovel_admin_accounting_finance_normalize_schema($recordType, $decoded, (int) ($row['schema_version'] ?? 1));
}

function yovel_admin_write_accounting_finance_form_schema(array $company, ?array $admin, string $recordType, array $schema, string $auditAction): string
{
    yovel_admin_accounting_finance_schema();

    $db = bx_db();
    $recordType = yovel_admin_accounting_finance_record_type($recordType);
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = $admin ? (string) $admin['admin_key'] : null;
    $current = $db->GetRow(
        "SELECT * FROM project_company_form_schema
        WHERE company_key_hash = ? AND module_code = 'ACCOUNTING_FINANCE' AND record_type = ? AND schema_status = 'ACTIVE'
        ORDER BY schema_version DESC LIMIT 1",
        [$companyKeyHash, $recordType]
    );
    $nextVersion = $auditAction === 'SEED' ? 1 : max(1, (int) ($current['schema_version'] ?? 0) + 1);
    $normalized = yovel_admin_accounting_finance_normalize_schema($recordType, $schema, $nextVersion);
    $schemaJson = yovel_admin_accounting_finance_schema_json($normalized);
    if ($schemaJson === false) {
        throw new InvalidArgumentException('Accounting/Finance form schema could not be encoded.');
    }
    $schemaKey = bx_uuid();

    $db->BeginTrans();
    try {
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_form_schema
            SET schema_status = 'INACTIVE', updated_by_admin_key = ?
            WHERE company_key_hash = ? AND module_code = 'ACCOUNTING_FINANCE' AND record_type = ? AND schema_status = 'ACTIVE'",
            [$adminKey, $companyKeyHash, $recordType],
            'Accounting/Finance form schema deactivate'
        );
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_form_schema (
                form_schema_key, company_key, company_key_hash, module_code, record_type, schema_status,
                schema_version, schema_json, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, 'ACCOUNTING_FINANCE', ?, 'ACTIVE', ?, ?, ?, ?)",
            [$schemaKey, $companyKey, $companyKeyHash, $recordType, $nextVersion, $schemaJson, $adminKey, $adminKey],
            'Accounting/Finance form schema save'
        );
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_form_schema_audit (
                form_schema_audit_key, company_key, company_key_hash, form_schema_key, module_code,
                record_type, audit_action, previous_schema_json, next_schema_json, created_by_admin_key
            ) VALUES (?, ?, ?, ?, 'ACCOUNTING_FINANCE', ?, ?, ?, ?, ?)",
            [bx_uuid(), $companyKey, $companyKeyHash, $schemaKey, $recordType, $auditAction, $current['schema_json'] ?? null, $schemaJson, $adminKey],
            'Accounting/Finance form schema audit'
        );
        $saved = $db->GetRow(
            'SELECT form_schema_key, record_type, schema_status, schema_version, schema_json FROM project_company_form_schema WHERE company_key_hash = ? AND form_schema_key = ? LIMIT 1',
            [$companyKeyHash, $schemaKey]
        );
        if (!is_array($saved) || (string) $saved['record_type'] !== $recordType || (string) $saved['schema_status'] !== 'ACTIVE' || (int) $saved['schema_version'] !== $nextVersion || (string) $saved['schema_json'] !== $schemaJson) {
            throw new RuntimeException('Accounting/Finance form schema read-back verification failed.');
        }
        if ($admin) {
            bx_audit($auditAction, 'project_company_form_schema', $schemaKey, [
                'company_name' => (string) $company['company_name'],
                'module_code' => 'ACCOUNTING_FINANCE',
                'record_type' => $recordType,
                'schema_version' => $nextVersion,
                'admin_key' => $adminKey,
            ], 'Company admin changed an Accounting/Finance form schema.');
        }
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return $schemaKey;
}

function yovel_admin_accounting_finance_schema_from_post(string $recordType): array
{
    $schemaJson = (string) ($_POST['schema_json'] ?? '');
    if ($schemaJson !== '') {
        $decoded = json_decode($schemaJson, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Accounting/Finance form builder submitted invalid schema JSON.');
        }
        return $decoded;
    }

    $active = json_decode((string) ($_POST['active_schema_json'] ?? ''), true);
    if (!is_array($active)) {
        $active = yovel_admin_accounting_finance_default_form_schemas()[yovel_admin_accounting_finance_record_type($recordType)];
    }
    $fields = [];
    $posted = $_POST['fields'] ?? [];
    if (is_array($posted)) {
        foreach ($posted as $key => $field) {
            if (!is_array($field)) {
                continue;
            }
            $field['key'] = (string) $key;
            $field['visible'] = isset($field['visible']);
            $field['required'] = isset($field['required']);
            $field['options'] = array_filter(array_map('trim', preg_split('/\R/', (string) ($field['options_text'] ?? '')) ?: []));
            $fields[] = $field;
        }
    }
    $newKey = yovel_admin_accounting_finance_field_key((string) ($_POST['new_field_key'] ?? ''));
    $newLabel = trim((string) ($_POST['new_field_label'] ?? ''));
    if ($newKey !== '' && $newLabel !== '') {
        $fields[] = [
            'key' => $newKey,
            'label' => $newLabel,
            'type' => strtolower((string) ($_POST['new_field_type'] ?? 'text')),
            'section' => yovel_admin_slug((string) ($_POST['new_field_section'] ?? 'custom')),
            'required' => isset($_POST['new_field_required']),
            'visible' => true,
            'width' => (string) ($_POST['new_field_width'] ?? 'half'),
            'sortOrder' => max(0, (int) ($_POST['new_field_sort_order'] ?? 500)),
            'options' => array_filter(array_map('trim', preg_split('/\R/', (string) ($_POST['new_field_options'] ?? '')) ?: [])),
            'storage' => 'custom',
        ];
    }
    $active['fields'] = $fields;

    return $active;
}

function yovel_admin_save_accounting_finance_form_schema(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'save_accounting_form_schema', static function () use ($company, $admin): string {
        $recordType = yovel_admin_accounting_finance_record_type((string) ($_POST['record_type'] ?? 'account'));
        $schema = yovel_admin_accounting_finance_schema_from_post($recordType);
        yovel_admin_write_accounting_finance_form_schema($company, $admin, $recordType, $schema, 'UPDATE');
        return 'Accounting/Finance form layout saved.';
    });
}

function yovel_admin_reset_accounting_finance_form_schema(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'reset_accounting_form_schema', static function () use ($company, $admin): string {
        $recordType = yovel_admin_accounting_finance_record_type((string) ($_POST['record_type'] ?? 'account'));
        yovel_admin_write_accounting_finance_form_schema($company, $admin, $recordType, yovel_admin_accounting_finance_default_form_schemas()[$recordType], 'RESET');
        return 'Accounting/Finance form layout restored to default.';
    });
}

function yovel_admin_accounting_finance_data(array $company, ?array $admin = null): array
{
    yovel_admin_finance_foundation_schema();
    yovel_admin_finance_core_schema();

    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $accounts = $db->GetAll("
        SELECT
            a.*,
            COALESCE(p.account_code, '') AS parent_account_code,
            COALESCE(p.account_name, '') AS parent_account_name,
            COALESCE(child_counts.child_count, 0) AS child_count
        FROM project_company_accounting_account a
        LEFT JOIN project_company_accounting_account p ON p.account_key = a.parent_account_key AND p.company_key_hash = a.company_key_hash
        LEFT JOIN (
            SELECT company_key_hash, parent_account_key, COUNT(*) AS child_count
            FROM project_company_accounting_account
            WHERE company_key_hash = ? AND account_status <> 'DELETED' AND parent_account_key IS NOT NULL
            GROUP BY company_key_hash, parent_account_key
        ) child_counts ON child_counts.parent_account_key = a.account_key AND child_counts.company_key_hash = a.company_key_hash
        WHERE a.company_key_hash = ? AND a.account_status <> 'DELETED'
        ORDER BY a.root_type ASC, COALESCE(p.account_name, '') ASC, a.sort_order ASC, a.account_number ASC, a.account_name ASC
    ", [$companyKeyHash, $companyKeyHash]);

    $schemas = [];
    foreach (yovel_admin_accounting_finance_default_form_schemas() as $recordType => $_schema) {
        $schemas[$recordType] = yovel_admin_accounting_finance_active_schema($company, $recordType, $admin);
    }

    $activeSection = yovel_admin_accounting_finance_section();
    $isRecordSection = isset(yovel_admin_finance_builder_target_sections()[$activeSection]);
    $ledgerFilters = $activeSection === 'general-ledger' ? yovel_admin_general_ledger_filters($_GET) : [];
    $ledgerEntries = $activeSection === 'general-ledger' ? yovel_admin_general_ledger_entries($company, $ledgerFilters) : [];
    $ledgerSummary = $activeSection === 'general-ledger'
        ? yovel_admin_general_ledger_summary($company, $ledgerFilters)
        : yovel_admin_general_ledger_summary($company);
    $ledgerEntryCount = (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_general_ledger_entry WHERE company_key_hash = ?',
        [$companyKeyHash]
    );
    $reportDateTo = yovel_admin_optional_date((string) ($_GET['date_to'] ?? date('Y-m-d')), 'Report end date');
    $reportDateFrom = yovel_admin_optional_date((string) ($_GET['date_from'] ?? date('Y-01-01')), 'Report start date');
    $activeReport = match ($activeSection) {
        'profit-loss' => yovel_admin_profit_loss_report($company, $reportDateFrom, $reportDateTo),
        'balance-sheet' => yovel_admin_balance_sheet_report($company, $reportDateTo),
        'cash-flow' => yovel_admin_cash_flow_report($company, $reportDateFrom, $reportDateTo),
        'tax-reports' => yovel_admin_bir_2550q_report($company, (int) ($_GET['year'] ?? date('Y')), max(1, min(4, (int) ($_GET['quarter'] ?? ceil((int) date('n') / 3))))),
        default => [],
    };
    $bankStatementRows = yovel_admin_bank_statement_rows($company, (string) ($_GET['bank_account_key'] ?? ''), $activeSection === 'bank-reconciliation' ? $_GET : []);
    $bankMatchCandidates = [];
    if ($activeSection === 'bank-reconciliation') {
        foreach ($bankStatementRows as $bankRow) {
            if ((string) $bankRow['reconciliation_status'] === 'UNRECONCILED') {
                $bankMatchCandidates[(string) $bankRow['bank_statement_row_key']] = yovel_admin_bank_match_candidates($company, (string) $bankRow['bank_statement_row_key']);
            }
        }
    }
    return [
        'accounts' => is_array($accounts) ? $accounts : [],
        'schemas' => $schemas,
        'builderForms' => function_exists('yovel_admin_finance_builder_forms') ? yovel_admin_finance_builder_forms($company) : [],
        'gridViews' => $isRecordSection && function_exists('yovel_admin_finance_grid_views') ? yovel_admin_finance_grid_views($company, $activeSection) : [],
        'gridFormulas' => $isRecordSection && function_exists('yovel_admin_finance_grid_formulas') ? yovel_admin_finance_grid_formulas($company, $activeSection) : [],
        'ledgerFilters' => $ledgerFilters,
        'ledgerEntries' => $ledgerEntries,
        'ledgerSummary' => $ledgerSummary,
        'ledgerTransactions' => yovel_admin_general_ledger_transactions($company),
        'ledgerEntryCount' => $ledgerEntryCount,
        'financeSettings' => yovel_admin_finance_settings($company, $admin),
        'foundation' => yovel_admin_finance_foundation_records($company),
        'chartTemplates' => yovel_admin_finance_chart_templates(),
        'costCenters' => yovel_admin_finance_masters($company, 'cost-center'),
        'accountingDimensions' => yovel_admin_finance_masters($company, 'accounting-dimension'),
        'bankAccounts' => yovel_admin_finance_masters($company, 'bank-account'),
        'taxCodes' => yovel_admin_finance_masters($company, 'tax-code'),
        'journalEntries' => yovel_admin_journal_entries($company, $activeSection === 'journal-entries' ? $_GET : []),
        'journalTemplates' => yovel_admin_journal_templates($company),
        'ledgerHealthMonitors' => yovel_admin_ledger_health_monitors($company),
        'ledgerRepairs' => yovel_admin_ledger_repair_history($company),
        'salesInvoices' => yovel_admin_finance_invoices($company, 'SALES', $activeSection === 'sales-invoices' ? $_GET : []),
        'purchaseInvoices' => yovel_admin_finance_invoices($company, 'PURCHASE', $activeSection === 'purchase-invoices' ? $_GET : []),
        'suppliers' => bx_db()->GetAll("SELECT * FROM project_company_finance_supplier WHERE company_key_hash = ? AND supplier_status <> 'DELETED' ORDER BY supplier_name", [$companyKeyHash]) ?: [],
        'customers' => bx_db()->GetAll("SELECT * FROM project_company_sales_customer WHERE company_key_hash = ? AND customer_status <> 'DELETED' ORDER BY customer_name", [$companyKeyHash]) ?: [],
        'paymentEntries' => yovel_admin_payment_entries($company, $activeSection === 'payment-entries' ? $_GET : []),
        'openInvoices' => $db->GetAll("SELECT invoice_key,invoice_no,document_type,party_key,party_name,due_date,outstanding_amount FROM project_company_finance_invoice WHERE company_key_hash=? AND document_status='SUBMITTED' AND is_return=0 AND outstanding_amount>0 ORDER BY due_date,invoice_no", [$companyKeyHash]) ?: [],
        'bankStatementRows' => $bankStatementRows,
        'bankMatchCandidates' => $bankMatchCandidates,
        'bankReconciliations' => $db->GetAll('SELECT * FROM project_company_finance_bank_reconciliation WHERE company_key_hash=? ORDER BY reconciliation_date DESC,x_id DESC LIMIT 200', [$companyKeyHash]) ?: [],
        'budgets' => yovel_admin_finance_budgets($company),
        'periodClosings' => yovel_admin_period_closings($company),
        'activeReport' => $activeReport,
        'formState' => yovel_admin_finance_take_form_state($company),
    ];
}

function yovel_admin_accounting_finance_custom_fields(array $schema): array
{
    return array_values(array_filter($schema['fields'] ?? [], static function (array $field): bool {
        return (string) ($field['storage'] ?? 'core') === 'custom' && (bool) ($field['visible'] ?? true);
    }));
}

function yovel_admin_accounting_finance_custom_values(array $company, string $recordType, string $recordKey): array
{
    if (!yovel_admin_is_uuid($recordKey)) {
        return [];
    }

    $rows = bx_db()->GetAll(
        "SELECT field_key, field_value
        FROM project_company_form_custom_value
        WHERE company_key_hash = ? AND module_code = 'ACCOUNTING_FINANCE' AND record_type = ? AND record_key = ?",
        [(string) $company['company_key_hash'], yovel_admin_accounting_finance_record_type($recordType), $recordKey]
    );
    $values = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $values[(string) $row['field_key']] = (string) ($row['field_value'] ?? '');
    }

    return $values;
}

function yovel_admin_validate_accounting_custom_value(array $field, string $value): string
{
    $value = trim($value);
    $label = (string) ($field['label'] ?? 'Custom field');
    $type = (string) ($field['type'] ?? 'text');
    if ((bool) ($field['required'] ?? false) && $value === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }
    if (strlen($value) > 5000) {
        throw new InvalidArgumentException($label . ' exceeds the allowed length.');
    }
    if ($value !== '' && $type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException($label . ' must be a valid email address.');
    }
    if ($value !== '' && $type === 'date') {
        yovel_admin_optional_date($value, $label);
    }
    if ($value !== '' && $type === 'number' && !is_numeric($value)) {
        throw new InvalidArgumentException($label . ' must be a number.');
    }
    if ($value !== '' && $type === 'select') {
        $options = array_values(array_filter(array_map('strval', $field['options'] ?? [])));
        if ($options && !in_array($value, $options, true)) {
            throw new InvalidArgumentException($label . ' has an invalid selected value.');
        }
    }

    return $value;
}

function yovel_admin_save_accounting_custom_values(ADOConnection $db, array $company, array $admin, string $recordType, string $recordKey, array $schema): void
{
    $customPost = $_POST['custom_fields'] ?? [];
    if (!is_array($customPost)) {
        $customPost = [];
    }
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $recordType = yovel_admin_accounting_finance_record_type($recordType);
    foreach (yovel_admin_accounting_finance_custom_fields($schema) as $field) {
        $fieldKey = (string) ($field['key'] ?? '');
        if ($fieldKey === '') {
            continue;
        }
        $value = (string) ($customPost[$fieldKey] ?? '');
        if ((string) ($field['type'] ?? '') === 'checkbox') {
            $value = isset($customPost[$fieldKey]) ? '1' : '0';
        }
        $value = yovel_admin_validate_accounting_custom_value($field, $value);
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_form_custom_value (
                custom_value_key, company_key, company_key_hash, module_code, record_type, record_key,
                field_key, field_value, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, 'ACCOUNTING_FINANCE', ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                field_value = VALUES(field_value),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [bx_uuid(), $companyKey, $companyKeyHash, $recordType, $recordKey, $fieldKey, $value, $adminKey, $adminKey],
            'Accounting/Finance custom field value save'
        );
        $savedValue = (string) $db->GetOne(
            "SELECT field_value
            FROM project_company_form_custom_value
            WHERE company_key_hash = ? AND module_code = 'ACCOUNTING_FINANCE' AND record_type = ? AND record_key = ? AND field_key = ? LIMIT 1",
            [$companyKeyHash, $recordType, $recordKey, $fieldKey]
        );
        if ($savedValue !== $value) {
            throw new RuntimeException('Accounting/Finance custom field read-back verification failed for ' . $fieldKey . '.');
        }
    }
}

function yovel_admin_accounting_report_type_for_root(string $rootType): string
{
    return in_array($rootType, ['ASSET', 'LIABILITY', 'EQUITY'], true) ? 'BALANCE_SHEET' : 'PROFIT_LOSS';
}

function yovel_admin_save_accounting_account(array $company, array $admin): string
{
    yovel_admin_finance_capture_form_state($company, 'save_accounting_account');
    yovel_admin_finance_foundation_schema();

    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $schema = yovel_admin_accounting_finance_active_schema($company, 'account', $admin);
    $accountKey = trim((string) ($_POST['account_key'] ?? ''));
    $accountCode = yovel_admin_code((string) ($_POST['account_code'] ?? ''));
    $accountNumber = trim((string) ($_POST['account_number'] ?? ''));
    $accountName = trim((string) ($_POST['account_name'] ?? ''));
    $parentAccountKey = trim((string) ($_POST['parent_account_key'] ?? ''));
    $rootType = yovel_admin_status((string) ($_POST['root_type'] ?? 'ASSET'), ['ASSET', 'LIABILITY', 'INCOME', 'EXPENSE', 'EQUITY'], 'ASSET');
    $reportType = yovel_admin_status((string) ($_POST['report_type'] ?? yovel_admin_accounting_report_type_for_root($rootType)), ['BALANCE_SHEET', 'PROFIT_LOSS'], yovel_admin_accounting_report_type_for_root($rootType));
    $expectedReportType = yovel_admin_accounting_report_type_for_root($rootType);
    $accountType = trim((string) ($_POST['account_type'] ?? ''));
    $accountCategoryKey = trim((string) ($_POST['account_category_key'] ?? ''));
    $accountCurrency = strtoupper(trim((string) ($_POST['account_currency'] ?? '')));
    $isGroup = isset($_POST['is_group']) ? 1 : 0;
    $taxRate = yovel_admin_optional_decimal((string) ($_POST['tax_rate'] ?? ''), 'Tax rate');
    $balanceMustBe = yovel_admin_status((string) ($_POST['balance_must_be'] ?? 'EITHER'), ['DEBIT', 'CREDIT', 'EITHER'], 'EITHER');
    $freezeAccount = isset($_POST['freeze_account']) ? 1 : 0;
    $includeInGross = isset($_POST['include_in_gross']) ? 1 : 0;
    $accountStatus = yovel_admin_status((string) ($_POST['account_status'] ?? 'ACTIVE'), ['DRAFT', 'ACTIVE', 'FROZEN', 'INACTIVE', 'DELETED'], 'ACTIVE');
    $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));
    $accountNotes = trim((string) ($_POST['account_notes'] ?? ''));

    if ($accountKey !== '' && !yovel_admin_is_uuid($accountKey)) {
        throw new InvalidArgumentException('Invalid account key.');
    }
    if ($accountCode === '' || $accountName === '') {
        throw new InvalidArgumentException('Account code and account name are required.');
    }
    if (strlen($accountName) > 180) {
        throw new InvalidArgumentException('Account name must be 180 characters or fewer.');
    }
    if ($reportType !== $expectedReportType) {
        throw new InvalidArgumentException('Report type must match the selected root type.');
    }
    if ($accountNumber !== '' && strlen($accountNumber) > 80) {
        throw new InvalidArgumentException('Account number must be 80 characters or fewer.');
    }
    if ($accountCurrency !== '' && !preg_match('/^[A-Z]{3,20}$/', $accountCurrency)) {
        throw new InvalidArgumentException('Currency must use uppercase currency letters.');
    }
    if (strlen($accountType) > 80) {
        throw new InvalidArgumentException('Account type must be 80 characters or fewer.');
    }
    if ($accountCategoryKey !== '') {
        if (!yovel_admin_is_uuid($accountCategoryKey)) {
            throw new InvalidArgumentException('Account Category selection is invalid.');
        }
        $category = $db->GetRow("SELECT root_type FROM project_company_finance_account_category WHERE company_key_hash = ? AND account_category_key = ? AND status = 'ACTIVE' LIMIT 1", [$companyKeyHash, $accountCategoryKey]);
        if (!is_array($category) || $category === [] || ((string) ($category['root_type'] ?? '') !== '' && (string) $category['root_type'] !== $rootType)) {
            throw new InvalidArgumentException('Account Category must be active, company-owned, and compatible with the account root type.');
        }
    }
    if (strlen($accountNotes) > 5000) {
        throw new InvalidArgumentException('Notes exceed the allowed length.');
    }
    if ($parentAccountKey !== '') {
        if (!yovel_admin_is_uuid($parentAccountKey)) {
            throw new InvalidArgumentException('Invalid parent account selection.');
        }
        if ($parentAccountKey === $accountKey) {
            throw new InvalidArgumentException('An account cannot be its own parent.');
        }
        $parent = $db->GetRow(
            "SELECT account_key, is_group, root_type
            FROM project_company_accounting_account
            WHERE company_key_hash = ? AND account_key = ? AND account_status <> 'DELETED' LIMIT 1",
            [$companyKeyHash, $parentAccountKey]
        );
        if (!$parent) {
            throw new InvalidArgumentException('Selected parent account does not belong to this company.');
        }
        if ((int) ($parent['is_group'] ?? 0) !== 1) {
            throw new InvalidArgumentException('Selected parent account must be a group account.');
        }
        if ((string) ($parent['root_type'] ?? '') !== $rootType) {
            throw new InvalidArgumentException('Parent account root type must match the child account.');
        }
    }

    $accountNumberForDb = $accountNumber !== '' ? $accountNumber : null;
    $taxRateForDb = $taxRate !== '' ? $taxRate : null;
    $accountCurrencyForDb = $accountCurrency !== '' ? $accountCurrency : null;
    $accountTypeForDb = $accountType !== '' ? $accountType : null;
    $parentForDb = $parentAccountKey !== '' ? $parentAccountKey : null;

    $db->BeginTrans();
    try {
        $existing = null;
        if ($accountKey !== '') {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key = ? FOR UPDATE',
                [$companyKeyHash, $accountKey]
            );
            if (!$existing) {
                throw new InvalidArgumentException('Account record was not found for this company.');
            }
        } else {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_accounting_account WHERE company_key_hash = ? AND account_code = ? FOR UPDATE',
                [$companyKeyHash, $accountCode]
            );
            if ($existing) {
                $accountKey = (string) $existing['account_key'];
            }
        }
        if ($accountKey === '') {
            $accountKey = bx_uuid();
        }
        yovel_admin_finance_assert_account_parent_chain($db, $companyKeyHash, $accountKey, $parentAccountKey);
        $nextAccount = [
            'account_code'=>$accountCode,'account_number'=>$accountNumberForDb,'parent_account_key'=>$parentForDb,
            'root_type'=>$rootType,'report_type'=>$reportType,'account_type'=>$accountTypeForDb,
            'account_currency'=>$accountCurrencyForDb,'is_group'=>$isGroup,'account_status'=>$accountStatus,
        ];
        if (is_array($existing) && $existing !== []) {
            yovel_admin_finance_assert_account_update_allowed($db, $companyKeyHash, $existing, $nextAccount);
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_accounting_account (
                account_key, company_key, company_key_hash, account_code, account_number, account_name,
                parent_account_key, root_type, report_type, account_type, account_category_key, account_currency, is_group,
                tax_rate, balance_must_be, freeze_account, include_in_gross, account_status,
                sort_order, account_notes, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                account_number = VALUES(account_number),
                account_name = VALUES(account_name),
                parent_account_key = VALUES(parent_account_key),
                root_type = VALUES(root_type),
                report_type = VALUES(report_type),
                account_type = VALUES(account_type),
                account_category_key = VALUES(account_category_key),
                account_currency = VALUES(account_currency),
                is_group = VALUES(is_group),
                tax_rate = VALUES(tax_rate),
                balance_must_be = VALUES(balance_must_be),
                freeze_account = VALUES(freeze_account),
                include_in_gross = VALUES(include_in_gross),
                account_status = VALUES(account_status),
                sort_order = VALUES(sort_order),
                account_notes = VALUES(account_notes),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $accountKey, $companyKey, $companyKeyHash, $accountCode, $accountNumberForDb, $accountName,
                $parentForDb, $rootType, $reportType, $accountTypeForDb, $accountCategoryKey !== '' ? $accountCategoryKey : null, $accountCurrencyForDb, $isGroup,
                $taxRateForDb, $balanceMustBe, $freezeAccount, $includeInGross, $accountStatus,
                $sortOrder, $accountNotes, $adminKey, $adminKey,
            ],
            'Accounting account save'
        );
        yovel_admin_save_accounting_custom_values($db, $company, $admin, 'account', $accountKey, $schema);

        $readBack = $db->GetRow(
            'SELECT account_key, account_code, account_number, account_name, parent_account_key, root_type, report_type, account_category_key, account_status FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key = ? LIMIT 1',
            [$companyKeyHash, $accountKey]
        );
        if (
            !$readBack
            || (string) $readBack['account_code'] !== $accountCode
            || (string) ($readBack['account_number'] ?? '') !== (string) ($accountNumberForDb ?? '')
            || (string) $readBack['account_name'] !== $accountName
            || (string) ($readBack['parent_account_key'] ?? '') !== (string) ($parentForDb ?? '')
            || (string) $readBack['root_type'] !== $rootType
            || (string) $readBack['report_type'] !== $reportType
            || (string) ($readBack['account_category_key'] ?? '') !== $accountCategoryKey
            || (string) $readBack['account_status'] !== $accountStatus
        ) {
            throw new RuntimeException('Account read-back verification failed.');
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_accounting_account', $accountKey, [
            'account_code' => $accountCode,
            'account_name' => $accountName,
            'company' => (string) $company['company_name'],
            'admin_key' => $adminKey,
        ], $existing ? 'Company admin updated an Accounting/Finance account.' : 'Company admin created an Accounting/Finance account.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    yovel_admin_finance_clear_form_state($company);
    return 'Account saved.';
}

function yovel_admin_set_accounting_account_status(array $company, array $admin): string
{
    yovel_admin_accounting_finance_schema();

    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $accountKey = trim((string) ($_POST['account_key'] ?? ''));
    $status = yovel_admin_status((string) ($_POST['account_status'] ?? 'ACTIVE'), ['ACTIVE', 'FROZEN', 'INACTIVE', 'DELETED'], 'ACTIVE');
    if (!yovel_admin_is_uuid($accountKey)) {
        throw new InvalidArgumentException('Invalid account key.');
    }

    $db->BeginTrans();
    try {
        $existing = $db->GetRow('SELECT * FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key = ? FOR UPDATE', [$companyKeyHash, $accountKey]);
        if (!$existing) {
            throw new InvalidArgumentException('Account record was not found for this company.');
        }
        if ($status === 'DELETED') {
            $childCount = (int) $db->GetOne(
                "SELECT COUNT(*) FROM project_company_accounting_account WHERE company_key_hash = ? AND parent_account_key = ? AND account_status <> 'DELETED'",
                [$companyKeyHash, $accountKey]
            );
            if ($childCount > 0) {
                throw new InvalidArgumentException('Accounts with child accounts cannot be deleted.');
            }
            $next = $existing;
            $next['account_status'] = 'DELETED';
            yovel_admin_finance_assert_account_update_allowed($db, $companyKeyHash, $existing, $next);
        }
        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_accounting_account SET account_status = ?, freeze_account = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND account_key = ?',
            [$status, $status === 'FROZEN' ? 1 : (int) ($existing['freeze_account'] ?? 0), $adminKey, $companyKeyHash, $accountKey],
            'Accounting account status update'
        );
        $readBackStatus = (string) $db->GetOne('SELECT account_status FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key = ? LIMIT 1', [$companyKeyHash, $accountKey]);
        if ($readBackStatus !== $status) {
            throw new RuntimeException('Account status read-back verification failed.');
        }
        bx_audit($status === 'DELETED' ? 'DELETE' : 'STATUS', 'project_company_accounting_account', $accountKey, [
            'account_code' => (string) $existing['account_code'],
            'account_status' => $status,
            'company' => (string) $company['company_name'],
            'admin_key' => $adminKey,
        ], 'Company admin changed an Accounting/Finance account status.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Account status updated.';
}
