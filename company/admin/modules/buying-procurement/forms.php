<?php
declare(strict_types=1);

function yovel_admin_buying_procurement_default_form_schemas(): array
{
    $field = static function (
        string $key,
        string $label,
        string $type,
        string $section,
        bool $required,
        int $sortOrder,
        string $width = 'half',
        bool $system = false,
        array $options = []
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'section' => $section,
            'required' => $required,
            'visible' => true,
            'width' => $width,
            'sortOrder' => $sortOrder,
            'system' => $system,
            'options' => $options,
        ];
    };

    return [
        'buying-setting' => [
            'recordType' => 'buying-setting',
            'version' => 1,
            'sections' => [
                ['key' => 'defaults', 'label' => 'Defaults', 'sortOrder' => 10],
                ['key' => 'controls', 'label' => 'Order controls', 'sortOrder' => 20],
                ['key' => 'quantities', 'label' => 'Quantity controls', 'sortOrder' => 30],
            ],
            'requiredSystemFields' => ['buying_setting_key', 'supplier_naming_mode', 'setting_status'],
            'readonlySystemFields' => ['buying_setting_key'],
            'fields' => [
                $field('buying_setting_key', 'Settings key', 'SHORT_TEXT', 'defaults', true, 10, 'full', true),
                $field('supplier_naming_mode', 'Supplier naming', 'DROPDOWN', 'defaults', true, 20, 'half', true, ['SUPPLIER_NAME', 'NAMING_SERIES', 'AUTO_NAME']),
                $field('setting_status', 'Status', 'DROPDOWN', 'defaults', true, 30, 'half', true, ['ACTIVE', 'INACTIVE', 'ARCHIVED']),
                $field('default_supplier_group_key', 'Default supplier group', 'LINK', 'defaults', false, 40),
                $field('default_buying_price_list_key', 'Default buying price list', 'LINK', 'defaults', false, 50),
                $field('purchase_order_required', 'Purchase order required', 'CHECKBOXES', 'controls', false, 60),
                $field('purchase_receipt_required', 'Purchase receipt required', 'CHECKBOXES', 'controls', false, 70),
                $field('maintain_same_rate', 'Maintain same rate', 'CHECKBOXES', 'controls', false, 80),
                $field('maintain_same_rate_action', 'Rate mismatch action', 'DROPDOWN', 'controls', true, 90, 'half', false, ['STOP', 'WARN']),
                $field('over_order_allowance', 'Over-order allowance', 'NUMBER', 'quantities', true, 100),
                $field('over_transfer_allowance', 'Over-transfer allowance', 'NUMBER', 'quantities', true, 110),
                $field('allow_zero_qty_purchase_order', 'Allow zero quantity on purchase orders', 'CHECKBOXES', 'quantities', false, 120),
                $field('allow_zero_qty_rfq', 'Allow zero quantity on RFQs', 'CHECKBOXES', 'quantities', false, 130),
                $field('allow_zero_qty_supplier_quotation', 'Allow zero quantity on supplier quotations', 'CHECKBOXES', 'quantities', false, 140),
            ],
        ],
        'supplier' => [
            'recordType' => 'supplier',
            'version' => 1,
            'sections' => [
                ['key' => 'identity', 'label' => 'Identity', 'sortOrder' => 10],
                ['key' => 'terms', 'label' => 'Terms and controls', 'sortOrder' => 20],
                ['key' => 'contact', 'label' => 'Contact and tax', 'sortOrder' => 30],
                ['key' => 'governance', 'label' => 'Governance', 'sortOrder' => 40],
            ],
            'requiredSystemFields' => ['supplier_key', 'supplier_code', 'supplier_name', 'supplier_status'],
            'readonlySystemFields' => ['supplier_key'],
            'fields' => [
                $field('supplier_key', 'Supplier key', 'SHORT_TEXT', 'identity', true, 10, 'full', true),
                $field('supplier_code', 'Supplier code', 'SHORT_TEXT', 'identity', true, 20, 'half', true),
                $field('supplier_name', 'Supplier name', 'SHORT_TEXT', 'identity', true, 30, 'half', true),
                $field('supplier_type', 'Supplier type', 'DROPDOWN', 'identity', true, 40, 'half', false, ['COMPANY', 'INDIVIDUAL', 'PARTNERSHIP']),
                $field('supplier_group_key', 'Supplier group', 'LINK', 'identity', false, 50),
                $field('country_key', 'Country', 'LINK', 'identity', false, 60),
                $field('default_currency', 'Default currency', 'SHORT_TEXT', 'terms', false, 70),
                $field('default_price_list_key', 'Buying price list', 'LINK', 'terms', false, 80),
                $field('payment_terms_key', 'Payment terms', 'LINK', 'terms', false, 90),
                $field('tax_id', 'Tax ID', 'SHORT_TEXT', 'contact', false, 100),
                $field('language_code', 'Language', 'SHORT_TEXT', 'contact', false, 110),
                $field('email', 'Email', 'SHORT_TEXT', 'contact', false, 120),
                $field('phone', 'Phone', 'SHORT_TEXT', 'contact', false, 130),
                $field('website', 'Website', 'SHORT_TEXT', 'contact', false, 140, 'full'),
                $field('supplier_details', 'Supplier details', 'PARAGRAPH', 'contact', false, 150, 'full'),
                $field('customer_numbers', 'Customer numbers', 'TABLE', 'governance', false, 160, 'full'),
                $field('is_transporter', 'Transporter', 'CHECKBOXES', 'governance', false, 170),
                $field('is_internal_supplier', 'Internal supplier', 'CHECKBOXES', 'governance', false, 180),
                $field('represents_company_key', 'Represents company', 'LINK', 'governance', false, 190),
                $field('allowed_company_keys', 'Allowed companies', 'TABLE', 'governance', false, 200, 'full'),
                $field('warn_rfqs', 'Warn on RFQs', 'CHECKBOXES', 'governance', false, 210),
                $field('prevent_rfqs', 'Prevent RFQs', 'CHECKBOXES', 'governance', false, 220),
                $field('warn_purchase_orders', 'Warn on purchase orders', 'CHECKBOXES', 'governance', false, 230),
                $field('prevent_purchase_orders', 'Prevent purchase orders', 'CHECKBOXES', 'governance', false, 240),
                $field('on_hold', 'On hold', 'CHECKBOXES', 'governance', false, 250, 'half', true),
                $field('hold_type', 'Hold type', 'DROPDOWN', 'governance', false, 260, 'half', true, ['ALL', 'INVOICES', 'PAYMENTS']),
                $field('release_date', 'Release date', 'DATE', 'governance', false, 270, 'half', true),
                $field('supplier_status', 'Status', 'DROPDOWN', 'governance', true, 280, 'half', true, ['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED']),
            ],
        ],
        'request-for-quotation' => [
            'recordType' => 'request-for-quotation',
            'version' => 1,
            'sections' => [
                ['key' => 'document', 'label' => 'Document', 'sortOrder' => 10],
                ['key' => 'suppliers', 'label' => 'Suppliers and items', 'sortOrder' => 20],
            ],
            'requiredSystemFields' => ['rfq_key', 'rfq_number', 'transaction_date', 'subject', 'document_status'],
            'readonlySystemFields' => ['rfq_key', 'document_status'],
            'fields' => [
                $field('rfq_key', 'RFQ key', 'SHORT_TEXT', 'document', true, 10, 'full', true),
                $field('rfq_number', 'RFQ number', 'SHORT_TEXT', 'document', true, 20, 'half', true),
                $field('transaction_date', 'Transaction date', 'DATE', 'document', true, 30),
                $field('subject', 'Subject', 'SHORT_TEXT', 'document', true, 40, 'full', true),
                $field('supplier_keys', 'Suppliers', 'LINK', 'suppliers', true, 50, 'full'),
                $field('items', 'Items', 'TABLE', 'suppliers', true, 60, 'full'),
                $field('document_status', 'Status', 'DROPDOWN', 'document', true, 70, 'half', true, ['DRAFT', 'SUBMITTED', 'CANCELLED']),
            ],
        ],
        'supplier-quotation' => [
            'recordType' => 'supplier-quotation',
            'version' => 1,
            'sections' => [
                ['key' => 'document', 'label' => 'Document', 'sortOrder' => 10],
                ['key' => 'commercial', 'label' => 'Commercial details', 'sortOrder' => 20],
            ],
            'requiredSystemFields' => ['supplier_quotation_key', 'quotation_number', 'supplier_key', 'currency', 'document_status'],
            'readonlySystemFields' => ['supplier_quotation_key', 'document_status'],
            'fields' => [
                $field('supplier_quotation_key', 'Quotation key', 'SHORT_TEXT', 'document', true, 10, 'full', true),
                $field('quotation_number', 'Quotation number', 'SHORT_TEXT', 'document', true, 20, 'half', true),
                $field('supplier_key', 'Supplier', 'LINK', 'document', true, 30, 'half', true),
                $field('currency', 'Currency', 'SHORT_TEXT', 'commercial', true, 40, 'half', true),
                $field('items', 'Items', 'TABLE', 'commercial', true, 50, 'full'),
                $field('document_status', 'Status', 'DROPDOWN', 'document', true, 60, 'half', true, ['DRAFT', 'SUBMITTED', 'STOPPED', 'EXPIRED', 'CANCELLED']),
            ],
        ],
        'purchase-order' => [
            'recordType' => 'purchase-order',
            'version' => 1,
            'sections' => [
                ['key' => 'document', 'label' => 'Document', 'sortOrder' => 10],
                ['key' => 'fulfillment', 'label' => 'Fulfillment', 'sortOrder' => 20],
            ],
            'requiredSystemFields' => ['purchase_order_key', 'purchase_order_number', 'supplier_key', 'currency', 'document_status'],
            'readonlySystemFields' => ['purchase_order_key', 'document_status'],
            'fields' => [
                $field('purchase_order_key', 'Order key', 'SHORT_TEXT', 'document', true, 10, 'full', true),
                $field('purchase_order_number', 'Order number', 'SHORT_TEXT', 'document', true, 20, 'half', true),
                $field('supplier_key', 'Supplier', 'LINK', 'document', true, 30, 'half', true),
                $field('currency', 'Currency', 'SHORT_TEXT', 'document', true, 40, 'half', true),
                $field('schedule_date', 'Schedule date', 'DATE', 'fulfillment', false, 50),
                $field('items', 'Items', 'TABLE', 'fulfillment', true, 60, 'full'),
                $field('document_status', 'Status', 'DROPDOWN', 'document', true, 70, 'half', true, ['DRAFT', 'SUBMITTED', 'ON_HOLD', 'CLOSED', 'CANCELLED']),
            ],
        ],
        'supplier-scorecard' => [
            'recordType' => 'supplier-scorecard',
            'version' => 1,
            'sections' => [['key' => 'setup', 'label' => 'Scorecard setup', 'sortOrder' => 10]],
            'requiredSystemFields' => ['scorecard_key', 'supplier_key', 'period_type', 'scorecard_status'],
            'readonlySystemFields' => ['scorecard_key'],
            'fields' => [
                $field('scorecard_key', 'Scorecard key', 'SHORT_TEXT', 'setup', true, 10, 'full', true),
                $field('supplier_key', 'Supplier', 'LINK', 'setup', true, 20, 'half', true),
                $field('period_type', 'Period', 'DROPDOWN', 'setup', true, 30, 'half', true, ['WEEK', 'MONTH', 'YEAR']),
                $field('scorecard_status', 'Status', 'DROPDOWN', 'setup', true, 40, 'half', true, ['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED']),
            ],
        ],
        'supplier-scorecard-criteria' => [
            'recordType' => 'supplier-scorecard-criteria',
            'version' => 1,
            'sections' => [['key' => 'criteria', 'label' => 'Scoring criteria', 'sortOrder' => 10]],
            'requiredSystemFields' => ['scorecard_criteria_key', 'criteria_code', 'criteria_name', 'formula_expression'],
            'readonlySystemFields' => ['scorecard_criteria_key'],
            'fields' => [
                $field('scorecard_criteria_key', 'Criteria key', 'SHORT_TEXT', 'criteria', true, 10, 'full', true),
                $field('criteria_code', 'Criteria code', 'SHORT_TEXT', 'criteria', true, 20, 'half', true),
                $field('criteria_name', 'Criteria name', 'SHORT_TEXT', 'criteria', true, 30, 'half', true),
                $field('weight_percentage', 'Weight', 'NUMBER', 'criteria', true, 40),
                $field('formula_expression', 'Formula', 'PARAGRAPH', 'criteria', true, 50, 'full', true),
            ],
        ],
        'supplier-scorecard-standing' => [
            'recordType' => 'supplier-scorecard-standing',
            'version' => 1,
            'sections' => [['key' => 'standing', 'label' => 'Standing', 'sortOrder' => 10]],
            'requiredSystemFields' => ['scorecard_standing_key', 'standing_code', 'standing_name', 'minimum_score', 'maximum_score'],
            'readonlySystemFields' => ['scorecard_standing_key'],
            'fields' => [
                $field('scorecard_standing_key', 'Standing key', 'SHORT_TEXT', 'standing', true, 10, 'full', true),
                $field('standing_code', 'Standing code', 'SHORT_TEXT', 'standing', true, 20, 'half', true),
                $field('standing_name', 'Standing name', 'SHORT_TEXT', 'standing', true, 30, 'half', true),
                $field('minimum_score', 'Minimum score', 'NUMBER', 'standing', true, 40),
                $field('maximum_score', 'Maximum score', 'NUMBER', 'standing', true, 50),
            ],
        ],
        'supplier-scorecard-variable' => [
            'recordType' => 'supplier-scorecard-variable',
            'version' => 1,
            'sections' => [['key' => 'variable', 'label' => 'Metric variable', 'sortOrder' => 10]],
            'requiredSystemFields' => ['scorecard_variable_key', 'variable_code', 'variable_label', 'metric_path'],
            'readonlySystemFields' => ['scorecard_variable_key'],
            'fields' => [
                $field('scorecard_variable_key', 'Variable key', 'SHORT_TEXT', 'variable', true, 10, 'full', true),
                $field('variable_code', 'Variable code', 'SHORT_TEXT', 'variable', true, 20, 'half', true),
                $field('variable_label', 'Variable label', 'SHORT_TEXT', 'variable', true, 30, 'half', true),
                $field('metric_path', 'Metric path', 'SHORT_TEXT', 'variable', true, 40, 'full', true),
                $field('description', 'Description', 'PARAGRAPH', 'variable', false, 50, 'full'),
            ],
        ],
    ];
}
function yovel_admin_buying_procurement_form_adapter(): array
{
    $shared = yovel_admin_shared_form_adapter('buying-procurement');
    $targets = [
        'buying-settings' => 'buying-setting',
        'suppliers' => 'supplier',
        'request-for-quotation' => 'request-for-quotation',
        'supplier-quotations' => 'supplier-quotation',
        'purchase-orders' => 'purchase-order',
        'supplier-scorecards' => 'supplier-scorecard',
        'scorecard-criteria' => 'supplier-scorecard-criteria',
        'scorecard-standings' => 'supplier-scorecard-standing',
        'scorecard-variables' => 'supplier-scorecard-variable',
    ];
    $protected = [];
    foreach (yovel_admin_buying_procurement_default_form_schemas() as $recordType => $schema) {
        $keys = array_map('strval', $schema['requiredSystemFields'] ?? []);
        foreach ($schema['fields'] ?? [] as $field) {
            if (!empty($field['system'])) {
                $keys[] = (string) ($field['key'] ?? '');
            }
        }
        $protected[$recordType] = array_values(array_unique(array_filter($keys)));
    }

    return array_replace($shared, [
        'module' => 'buying-procurement',
        'target_record_types' => $targets,
        'protected_fields' => $protected,
        'field_types' => array_values(array_unique(array_merge(
            $shared['field_types'] ?? [],
            ['LINK', 'TABLE']
        ))),
        'row_column_layout' => ['version' => 1, 'max_columns' => 3, 'stable_keys' => true],
        'normalize' => 'yovel_admin_buying_procurement_form_reorder',
        'version_identity' => 'yovel_admin_buying_procurement_form_version_identity',
        'renderer' => 'company/admin/modules/buying-procurement/views/form-builder.php',
        'version_contract' => [
            'statuses' => ['DRAFT', 'PUBLISHED', 'ARCHIVED'],
            'published_immutable' => true,
        ],
    ]);
}

function yovel_admin_buying_procurement_form_reorder(
    string $recordType,
    array $fields,
    array $requestedKeys
): array {
    $adapter = yovel_admin_buying_procurement_form_adapter();
    if (!in_array($recordType, $adapter['target_record_types'], true)) {
        throw new InvalidArgumentException('The Buying Form Builder target is not registered.');
    }

    $route = function_exists('yovel_admin_module_route') ? yovel_admin_module_route('buying-procurement') : null;
    $sharedTarget = is_array($route) ? (string) ($route['default_section'] ?? '') : '';
    if ($sharedTarget === '') {
        throw new RuntimeException('The Buying Form Builder shared route is unavailable.');
    }

    return yovel_admin_shared_form_reorder('buying-procurement', $sharedTarget, $fields, $requestedKeys);
}

function yovel_admin_buying_procurement_form_version_identity(string $recordType, array $fields): string
{
    $adapter = yovel_admin_buying_procurement_form_adapter();
    if (!in_array($recordType, $adapter['target_record_types'], true)) {
        throw new InvalidArgumentException('The Buying Form Builder target is not registered.');
    }

    return hash('sha256', json_encode([
        'module' => 'buying-procurement',
        'recordType' => $recordType,
        'fields' => array_values($fields),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function yovel_admin_buying_procurement_assert_form_editable(array $version): void
{
    $status = strtoupper(trim((string) ($version['form_status'] ?? $version['status'] ?? 'DRAFT')));
    if ($status === 'PUBLISHED') {
        throw new LogicException('Published Buying Form Builder versions are immutable.');
    }
    if ($status === 'ARCHIVED') {
        throw new LogicException('Archived Buying Form Builder versions are not editable.');
    }
    if ($status !== 'DRAFT') {
        throw new InvalidArgumentException('Buying Form Builder version status is invalid.');
    }
}
