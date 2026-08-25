<?php
declare(strict_types=1);

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/form-builder.php';
require_once __DIR__ . '/crm-settings.php';
require_once __DIR__ . '/campaigns.php';

function yovel_admin_sales_crm_sections(): array
{
    return [
        'leads' => ['label' => 'Leads', 'record_type' => 'lead', 'icon' => '◇', 'description' => 'Capture and qualify prospects before they become opportunities or customers.'],
        'opportunities' => ['label' => 'Opportunities', 'record_type' => 'opportunity', 'icon' => '◈', 'description' => 'Track qualified sales chances, expected value, stage, and close dates.'],
        'campaigns' => ['label' => 'Campaigns', 'record_type' => 'campaign', 'icon' => '◎', 'description' => 'Organize marketing and sales attribution for leads and deals.'],
        'customers' => ['label' => 'Customers', 'record_type' => 'customer', 'icon' => '▧', 'description' => 'Maintain reusable customer master data for selling documents and reporting.'],
        'quotations' => ['label' => 'Quotations', 'record_type' => 'quotation', 'icon' => '▤', 'description' => 'Prepare proposed prices, terms, and itemized offers for customers.'],
        'sales-orders' => ['label' => 'Sales orders', 'record_type' => 'sales-order', 'icon' => '▥', 'description' => 'Track confirmed customer orders and delivery commitments.'],
        'customer-credit-limits' => ['label' => 'Customer credit limits', 'record_type' => 'customer-credit-limit', 'icon' => '◫', 'description' => 'Control customer credit exposure and hold status.'],
        'sales-analytics' => ['label' => 'Sales analytics', 'record_type' => '', 'icon' => '▦', 'description' => 'Review funnel, revenue, campaign, and order summaries.'],
        'salesperson-performance' => ['label' => 'Salesperson performance', 'record_type' => '', 'icon' => '♙', 'description' => 'Compare salesperson pipeline, conversion, won value, and follow-up.'],
        'territory-performance' => ['label' => 'Territory performance', 'record_type' => '', 'icon' => '◌', 'description' => 'Measure lead, opportunity, customer, and order results by territory.'],
    ];
}

function yovel_admin_sales_crm_section(): string
{
    $section = yovel_admin_slug((string) ($_GET['section'] ?? 'leads'));
    return array_key_exists($section, yovel_admin_sales_crm_sections()) ? $section : 'leads';
}

function yovel_admin_sales_crm_schema(): void
{
    yovel_admin_sales_crm_ensure_schema();
}

function yovel_admin_sales_crm_default_form_schemas(): array
{
    $field = static function (string $key, string $label, string $type, string $section, bool $required, int $sortOrder, string $width = 'half', array $options = [], bool $system = false): array {
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
        ];
    };

    return [
        'lead' => [
            'recordType' => 'lead',
            'version' => 1,
            'sections' => [
                ['key' => 'overview', 'label' => 'Overview', 'sortOrder' => 10],
                ['key' => 'contact', 'label' => 'Contact', 'sortOrder' => 20],
                ['key' => 'qualification', 'label' => 'Qualification', 'sortOrder' => 30],
                ['key' => 'notes', 'label' => 'Notes', 'sortOrder' => 40],
            ],
            'requiredSystemFields' => ['lead_code', 'lead_name', 'lead_status'],
            'readonlySystemFields' => [],
            'fields' => [
                $field('lead_code', 'Lead code', 'text', 'overview', true, 10, 'third', [], true),
                $field('lead_status', 'Status', 'select', 'overview', true, 20, 'third', ['DRAFT', 'OPEN', 'QUALIFIED', 'CONVERTED', 'LOST', 'INACTIVE'], true),
                $field('lead_name', 'Lead name', 'text', 'overview', true, 30, 'third', [], true),
                $field('organization_name', 'Organization', 'text', 'overview', false, 40),
                $field('lead_source', 'Lead source', 'text', 'overview', false, 50),
                $field('campaign_key', 'Campaign', 'select', 'qualification', false, 60),
                $field('territory_key', 'Territory', 'select', 'qualification', false, 70),
                $field('salesperson_key', 'Salesperson / owner', 'select', 'qualification', false, 80),
                $field('email', 'Email', 'email', 'contact', false, 90),
                $field('phone', 'Phone', 'text', 'contact', false, 100),
                $field('mobile', 'Mobile', 'text', 'contact', false, 110),
                $field('website', 'Website', 'url', 'contact', false, 120),
                $field('industry', 'Industry', 'text', 'qualification', false, 130),
                $field('estimated_value', 'Estimated value', 'number', 'qualification', false, 140),
                $field('next_contact_date', 'Next contact date', 'date', 'qualification', false, 150),
                $field('notes', 'Notes', 'textarea', 'notes', false, 160, 'full'),
            ],
        ],
        'opportunity' => [
            'recordType' => 'opportunity',
            'version' => 1,
            'sections' => [['key' => 'overview', 'label' => 'Overview', 'sortOrder' => 10], ['key' => 'value', 'label' => 'Value', 'sortOrder' => 20]],
            'requiredSystemFields' => ['opportunity_code', 'opportunity_title', 'opportunity_status'],
            'readonlySystemFields' => [],
            'fields' => [
                $field('opportunity_code', 'Opportunity code', 'text', 'overview', true, 10, 'third', [], true),
                $field('opportunity_title', 'Opportunity title', 'text', 'overview', true, 20, 'third', [], true),
                $field('opportunity_status', 'Status', 'select', 'overview', true, 30, 'third', ['OPEN', 'WON', 'LOST', 'INACTIVE'], true),
                $field('party_type', 'Party type', 'select', 'overview', false, 40, 'half', ['Lead', 'Customer']),
                $field('expected_closing_date', 'Expected closing date', 'date', 'value', false, 50),
                $field('probability', 'Probability', 'number', 'value', false, 60),
                $field('estimated_value', 'Estimated value', 'number', 'value', false, 70),
                $field('notes', 'Notes', 'textarea', 'value', false, 80, 'full'),
            ],
        ],
        'campaign' => [
            'recordType' => 'campaign',
            'version' => 1,
            'sections' => [
                ['key' => 'overview', 'label' => 'Overview', 'sortOrder' => 10],
                ['key' => 'email-schedule', 'label' => 'Email schedule', 'sortOrder' => 20],
            ],
            'requiredSystemFields' => [
                'campaign_code', 'campaign_name', 'campaign_status',
                'campaign_email_schedule_code', 'campaign_email_subject', 'campaign_email_scheduled_at',
            ],
            'readonlySystemFields' => [],
            'fields' => [
                $field('campaign_code', 'Campaign code', 'text', 'overview', true, 10, 'third', [], true),
                $field('campaign_name', 'Campaign name', 'text', 'overview', true, 20, 'third', [], true),
                $field('campaign_status', 'Status', 'select', 'overview', true, 30, 'third', ['DRAFT', 'ACTIVE', 'INACTIVE', 'COMPLETED'], true),
                $field('campaign_type', 'Campaign type', 'text', 'overview', false, 40),
                $field('start_date', 'Start date', 'date', 'overview', false, 50),
                $field('end_date', 'End date', 'date', 'overview', false, 60),
                $field('budget', 'Budget', 'number', 'overview', false, 70),
                $field('expected_revenue', 'Expected revenue', 'number', 'overview', false, 80),
                $field('notes', 'Notes', 'textarea', 'overview', false, 90, 'full'),
                $field('campaign_email_schedule_code', 'Schedule code', 'text', 'email-schedule', true, 100, 'third', [], true),
                $field('campaign_email_subject', 'Email subject', 'text', 'email-schedule', true, 110, 'half', [], true),
                $field('campaign_email_recipient_segment', 'Recipient segment', 'text', 'email-schedule', false, 120, 'half'),
                $field('campaign_email_scheduled_at', 'Scheduled at', 'datetime-local', 'email-schedule', true, 130, 'half', [], true),
                $field('campaign_email_send_status', 'Send status', 'select', 'email-schedule', false, 140, 'third', ['DRAFT', 'SCHEDULED', 'CANCELLED']),
            ],
        ],
        'customer' => [
            'recordType' => 'customer',
            'version' => 1,
            'sections' => [['key' => 'overview', 'label' => 'Overview', 'sortOrder' => 10], ['key' => 'contact', 'label' => 'Contact', 'sortOrder' => 20]],
            'requiredSystemFields' => ['customer_code', 'customer_name', 'customer_status'],
            'readonlySystemFields' => [],
            'fields' => [
                $field('customer_code', 'Customer code', 'text', 'overview', true, 10, 'third', [], true),
                $field('customer_name', 'Customer name', 'text', 'overview', true, 20, 'third', [], true),
                $field('customer_status', 'Status', 'select', 'overview', true, 30, 'third', ['DRAFT', 'ACTIVE', 'INACTIVE'], true),
                $field('customer_type', 'Customer type', 'text', 'overview', false, 40),
                $field('customer_group', 'Customer group', 'text', 'overview', false, 50),
                $field('email', 'Email', 'email', 'contact', false, 60),
                $field('phone', 'Phone', 'text', 'contact', false, 70),
                $field('billing_address', 'Billing address', 'textarea', 'contact', false, 80, 'full'),
                $field('shipping_address', 'Shipping address', 'textarea', 'contact', false, 90, 'full'),
            ],
        ],
        'quotation' => [
            'recordType' => 'quotation',
            'version' => 1,
            'sections' => [['key' => 'overview', 'label' => 'Overview', 'sortOrder' => 10], ['key' => 'commercial', 'label' => 'Commercial', 'sortOrder' => 20]],
            'requiredSystemFields' => ['quotation_code', 'customer_key', 'quotation_status'],
            'readonlySystemFields' => [],
            'fields' => [
                $field('quotation_code', 'Quotation code', 'text', 'overview', true, 10, 'third', [], true),
                $field('customer_key', 'Customer', 'select', 'overview', true, 20, 'third', [], true),
                $field('quotation_status', 'Status', 'select', 'overview', true, 30, 'third', ['DRAFT', 'OPEN', 'SUBMITTED', 'ORDERED', 'LOST'], true),
                $field('quotation_date', 'Quotation date', 'date', 'commercial', false, 40),
                $field('valid_until', 'Valid until', 'date', 'commercial', false, 50),
                $field('currency', 'Currency', 'text', 'commercial', false, 60),
                $field('terms', 'Terms', 'textarea', 'commercial', false, 70, 'full'),
            ],
        ],
        'sales-order' => [
            'recordType' => 'sales-order',
            'version' => 1,
            'sections' => [['key' => 'overview', 'label' => 'Overview', 'sortOrder' => 10], ['key' => 'fulfillment', 'label' => 'Fulfillment', 'sortOrder' => 20]],
            'requiredSystemFields' => ['sales_order_code', 'customer_key', 'order_status'],
            'readonlySystemFields' => [],
            'fields' => [
                $field('sales_order_code', 'Sales order code', 'text', 'overview', true, 10, 'third', [], true),
                $field('customer_key', 'Customer', 'select', 'overview', true, 20, 'third', [], true),
                $field('order_status', 'Status', 'select', 'overview', true, 30, 'third', ['DRAFT', 'SUBMITTED', 'TO_DELIVER', 'COMPLETED', 'CLOSED'], true),
                $field('order_date', 'Order date', 'date', 'fulfillment', false, 40),
                $field('delivery_date', 'Delivery date', 'date', 'fulfillment', false, 50),
                $field('customer_purchase_order', 'Customer purchase order', 'text', 'fulfillment', false, 60),
                $field('notes', 'Notes', 'textarea', 'fulfillment', false, 70, 'full'),
            ],
        ],
        'customer-credit-limit' => [
            'recordType' => 'customer-credit-limit',
            'version' => 1,
            'sections' => [['key' => 'overview', 'label' => 'Overview', 'sortOrder' => 10]],
            'requiredSystemFields' => ['customer_key', 'credit_limit', 'credit_hold_status'],
            'readonlySystemFields' => [],
            'fields' => [
                $field('customer_key', 'Customer', 'select', 'overview', true, 10, 'third', [], true),
                $field('credit_limit', 'Credit limit', 'number', 'overview', true, 20, 'third', [], true),
                $field('credit_hold_status', 'Credit hold status', 'select', 'overview', true, 30, 'third', ['CLEAR', 'HOLD', 'REVIEW'], true),
                $field('currency', 'Currency', 'text', 'overview', false, 40),
                $field('effective_date', 'Effective date', 'date', 'overview', false, 50),
                $field('expiry_date', 'Expiry date', 'date', 'overview', false, 60),
                $field('approved_by', 'Approved by', 'text', 'overview', false, 70),
                $field('notes', 'Notes', 'textarea', 'overview', false, 80, 'full'),
            ],
        ],
    ];
}

function yovel_admin_sales_crm_record_type(string $recordType): string
{
    $recordType = yovel_admin_slug($recordType);
    return array_key_exists($recordType, yovel_admin_sales_crm_default_form_schemas()) ? $recordType : 'lead';
}

function yovel_admin_sales_crm_normalize_schema(string $recordType, array $schema, int $version): array
{
    $recordType = yovel_admin_sales_crm_record_type($recordType);
    $default = yovel_admin_sales_crm_default_form_schemas()[$recordType];
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

function yovel_admin_sales_crm_schema_json(array $schema): string
{
    return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function yovel_admin_sales_crm_active_schema(array $company, string $recordType, ?array $admin = null): array
{
    yovel_admin_sales_crm_schema();

    $recordType = yovel_admin_sales_crm_record_type($recordType);
    $companyKeyHash = (string) $company['company_key_hash'];
    $row = bx_db()->GetRow(
        "SELECT * FROM project_company_form_schema
        WHERE company_key_hash = ? AND module_code = 'sales-crm' AND record_type = ? AND schema_status = 'ACTIVE'
        ORDER BY schema_version DESC LIMIT 1",
        [$companyKeyHash, $recordType]
    );
    if (!$row) {
        yovel_admin_write_sales_form_schema($company, $admin, $recordType, yovel_admin_sales_crm_default_form_schemas()[$recordType], 'SEED');
        $row = bx_db()->GetRow(
            "SELECT * FROM project_company_form_schema
            WHERE company_key_hash = ? AND module_code = 'sales-crm' AND record_type = ? AND schema_status = 'ACTIVE'
            ORDER BY schema_version DESC LIMIT 1",
            [$companyKeyHash, $recordType]
        );
    }
    $decoded = json_decode((string) ($row['schema_json'] ?? ''), true);
    if (!is_array($decoded)) {
        $decoded = yovel_admin_sales_crm_default_form_schemas()[$recordType];
    }

    return yovel_admin_sales_crm_normalize_schema($recordType, $decoded, (int) ($row['schema_version'] ?? 1));
}

function yovel_admin_write_sales_form_schema(array $company, ?array $admin, string $recordType, array $schema, string $auditAction): string
{
    yovel_admin_sales_crm_schema();

    $db = bx_db();
    $recordType = yovel_admin_sales_crm_record_type($recordType);
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = $admin ? (string) $admin['admin_key'] : null;
    $current = $db->GetRow(
        "SELECT * FROM project_company_form_schema
        WHERE company_key_hash = ? AND module_code = 'sales-crm' AND record_type = ? AND schema_status = 'ACTIVE'
        ORDER BY schema_version DESC LIMIT 1",
        [$companyKeyHash, $recordType]
    );
    $nextVersion = max(1, (int) ($current['schema_version'] ?? 0) + 1);
    if ($auditAction === 'SEED') {
        $nextVersion = 1;
    }
    $normalized = yovel_admin_sales_crm_normalize_schema($recordType, $schema, $nextVersion);
    $schemaJson = yovel_admin_sales_crm_schema_json($normalized);
    if ($schemaJson === false) {
        throw new InvalidArgumentException('Sales/CRM form schema could not be encoded.');
    }
    $schemaKey = bx_uuid();

    $db->BeginTrans();
    try {
        yovel_admin_db_execute(
            $db,
            "UPDATE project_company_form_schema
            SET schema_status = 'INACTIVE', updated_by_admin_key = ?
            WHERE company_key_hash = ? AND module_code = 'sales-crm' AND record_type = ? AND schema_status = 'ACTIVE'",
            [$adminKey, $companyKeyHash, $recordType],
            'Sales/CRM form schema deactivate'
        );
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_form_schema (
                form_schema_key, company_key, company_key_hash, module_code, record_type, schema_status,
                schema_version, schema_json, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, 'sales-crm', ?, 'ACTIVE', ?, ?, ?, ?)",
            [$schemaKey, $companyKey, $companyKeyHash, $recordType, $nextVersion, $schemaJson, $adminKey, $adminKey],
            'Sales/CRM form schema save'
        );
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_form_schema_audit (
                form_schema_audit_key, company_key, company_key_hash, form_schema_key, module_code,
                record_type, audit_action, previous_schema_json, next_schema_json, created_by_admin_key
            ) VALUES (?, ?, ?, ?, 'sales-crm', ?, ?, ?, ?, ?)",
            [bx_uuid(), $companyKey, $companyKeyHash, $schemaKey, $recordType, $auditAction, $current['schema_json'] ?? null, $schemaJson, $adminKey],
            'Sales/CRM form schema audit'
        );
        $saved = $db->GetRow(
            'SELECT form_schema_key, record_type, schema_status, schema_version, schema_json FROM project_company_form_schema WHERE company_key_hash = ? AND form_schema_key = ? LIMIT 1',
            [$companyKeyHash, $schemaKey]
        );
        if (!is_array($saved) || (string) $saved['record_type'] !== $recordType || (string) $saved['schema_status'] !== 'ACTIVE' || (int) $saved['schema_version'] !== $nextVersion || (string) $saved['schema_json'] !== $schemaJson) {
            throw new RuntimeException('Sales/CRM form schema read-back verification failed.');
        }
        if ($admin) {
            bx_audit($auditAction, 'project_company_form_schema', $schemaKey, [
                'company_name' => (string) $company['company_name'],
                'module_code' => 'sales-crm',
                'record_type' => $recordType,
                'schema_version' => $nextVersion,
                'admin_key' => $adminKey,
            ], 'Company admin changed a Sales/CRM form schema.');
        }
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return $schemaKey;
}

function yovel_admin_save_form_schema(array $company, array $admin): string
{
    $recordType = yovel_admin_sales_crm_record_type((string) ($_POST['record_type'] ?? 'lead'));
    yovel_admin_sales_crm_require_scope($company, $admin, in_array($recordType, ['lead', 'opportunity', 'campaign'], true) ? 'crm' : 'selling');
    $schemaJson = (string) ($_POST['schema_json'] ?? '');
    $decoded = json_decode($schemaJson, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Sales/CRM form builder submitted invalid schema JSON.');
    }
    yovel_admin_write_sales_form_schema($company, $admin, $recordType, $decoded, 'UPDATE');
    return 'Sales/CRM form layout saved.';
}

function yovel_admin_reset_form_schema(array $company, array $admin): string
{
    $recordType = yovel_admin_sales_crm_record_type((string) ($_POST['record_type'] ?? 'lead'));
    yovel_admin_sales_crm_require_scope($company, $admin, in_array($recordType, ['lead', 'opportunity', 'campaign'], true) ? 'crm' : 'selling');
    yovel_admin_write_sales_form_schema($company, $admin, $recordType, yovel_admin_sales_crm_default_form_schemas()[$recordType], 'RESET');
    return 'Sales/CRM form layout restored to default.';
}

function yovel_admin_sales_crm_data(array $company, ?array $admin = null): array
{
    yovel_admin_sales_crm_schema();
    $leadRehydration = yovel_admin_sales_crm_pull_rehydration($company, 'lead');

    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $workspace = $admin !== null ? yovel_admin_sales_crm_workspace($company, $admin) : [];
    $access = $workspace['access'] ?? ['crm' => true, 'selling' => true, 'manage_settings' => true];
    $canCrm = !empty($access['crm']);
    $canSelling = !empty($access['selling']);
    $campaigns = $canCrm ? $db->GetAll("SELECT * FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_status <> 'DELETED' ORDER BY updated_at DESC, campaign_name ASC", [$companyKeyHash]) : [];
    $campaignEfficiencies = [];
    if ($canCrm && $admin !== null) {
        foreach (is_array($campaigns) ? $campaigns : [] as &$campaign) {
            $campaign['email_schedules'] = yovel_admin_sales_campaign_schedules($company, (string) $campaign['campaign_key']);
            $campaign['campaign_version'] = (int) $campaign['campaign_version'];
            $campaign['budget'] = yovel_admin_sales_campaign_decimal_readback($campaign['budget']);
            $campaign['expected_revenue'] = yovel_admin_sales_campaign_decimal_readback($campaign['expected_revenue']);
            $campaignEfficiencies[(string) $campaign['campaign_key']] = yovel_admin_sales_campaign_efficiency($company, $admin, (string) $campaign['campaign_key']);
        }
        unset($campaign);
    }
    $territories = ($canCrm || $canSelling) ? $db->GetAll("SELECT territory_key, territory_code, territory_name, territory_status FROM project_company_sales_territory WHERE company_key_hash = ? AND territory_status <> 'DELETED' ORDER BY territory_name ASC", [$companyKeyHash]) : [];
    $salespersons = ($canCrm || $canSelling) ? $db->GetAll("SELECT salesperson_key, salesperson_code, salesperson_name, salesperson_status FROM project_company_salesperson WHERE company_key_hash = ? AND salesperson_status <> 'DELETED' ORDER BY salesperson_name ASC", [$companyKeyHash]) : [];
    $customers = $canSelling ? $db->GetAll("SELECT customer_key, customer_code, customer_name, customer_status FROM project_company_sales_customer WHERE company_key_hash = ? AND customer_status <> 'DELETED' ORDER BY customer_name ASC", [$companyKeyHash]) : [];
    $leads = $canCrm ? $db->GetAll("
        SELECT
            l.*,
            COALESCE(c.campaign_name, '') AS campaign_name,
            COALESCE(t.territory_name, '') AS territory_name,
            COALESCE(s.salesperson_name, '') AS salesperson_name
        FROM project_company_sales_lead l
        LEFT JOIN project_company_sales_campaign c ON c.campaign_key = l.campaign_key AND c.company_key_hash = l.company_key_hash
        LEFT JOIN project_company_sales_territory t ON t.territory_key = l.territory_key AND t.company_key_hash = l.company_key_hash
        LEFT JOIN project_company_salesperson s ON s.salesperson_key = l.salesperson_key AND s.company_key_hash = l.company_key_hash
        WHERE l.company_key_hash = ? AND l.lead_status <> 'DELETED'
        ORDER BY l.updated_at DESC, l.lead_name ASC
    ", [$companyKeyHash]) : [];

    $schemas = [];
    foreach (yovel_admin_sales_crm_default_form_schemas() as $recordType => $_schema) {
        $scope = in_array($recordType, ['lead', 'opportunity', 'campaign'], true) ? 'crm' : 'selling';
        if (!empty($access[$scope])) {
            $schemas[$recordType] = yovel_admin_sales_crm_active_schema($company, $recordType, $admin);
        }
    }
    if ($workspace !== []) {
        foreach ($workspace['setup_steps'] as &$setupStep) {
            if ((string) ($setupStep['key'] ?? '') === 'lead') {
                $setupStep['complete'] = count(is_array($leads) ? $leads : []) > 0;
            }
        }
        unset($setupStep);
    }

    return [
        'campaigns' => is_array($campaigns) ? $campaigns : [],
        'campaign_efficiencies' => $campaignEfficiencies,
        'territories' => is_array($territories) ? $territories : [],
        'salespersons' => is_array($salespersons) ? $salespersons : [],
        'customers' => is_array($customers) ? $customers : [],
        'leads' => is_array($leads) ? $leads : [],
        'schemas' => $schemas,
        'rehydration' => ['lead' => $leadRehydration],
        'workspace' => $workspace,
    ];
}

function yovel_admin_save_sales_lead(array $company, array $admin): string
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    yovel_admin_sales_crm_store_rehydration($company, 'lead', $_POST);

    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $leadKey = trim((string) ($_POST['lead_key'] ?? ''));
    $leadCode = yovel_admin_code((string) ($_POST['lead_code'] ?? ''));
    $leadName = trim((string) ($_POST['lead_name'] ?? ''));
    $organizationName = trim((string) ($_POST['organization_name'] ?? ''));
    $leadStatus = yovel_admin_status((string) ($_POST['lead_status'] ?? 'OPEN'), ['DRAFT', 'OPEN', 'QUALIFIED', 'CONVERTED', 'LOST', 'INACTIVE', 'DELETED'], 'OPEN');
    $leadSource = trim((string) ($_POST['lead_source'] ?? ''));
    $campaignKey = yovel_admin_optional_company_key($db, 'project_company_sales_campaign', 'campaign_key', $companyKeyHash, (string) ($_POST['campaign_key'] ?? ''), 'campaign');
    $territoryKey = yovel_admin_optional_company_key($db, 'project_company_sales_territory', 'territory_key', $companyKeyHash, (string) ($_POST['territory_key'] ?? ''), 'territory');
    $salespersonKey = yovel_admin_optional_company_key($db, 'project_company_salesperson', 'salesperson_key', $companyKeyHash, (string) ($_POST['salesperson_key'] ?? ''), 'salesperson');
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $mobile = trim((string) ($_POST['mobile'] ?? ''));
    $website = trim((string) ($_POST['website'] ?? ''));
    $industry = trim((string) ($_POST['industry'] ?? ''));
    $estimatedValue = yovel_admin_optional_decimal((string) ($_POST['estimated_value'] ?? ''), 'Estimated value');
    $nextContactDate = yovel_admin_optional_date((string) ($_POST['next_contact_date'] ?? ''), 'Next contact date');
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($leadKey !== '' && !yovel_admin_is_uuid($leadKey)) {
        throw new InvalidArgumentException('Invalid lead key.');
    }
    if ($leadCode === '' || !preg_match('/^[A-Z0-9_.-]{2,80}$/', $leadCode)) {
        throw new InvalidArgumentException('Lead code must use 2-80 uppercase letters, numbers, underscores, periods, or hyphens.');
    }
    if ($leadName === '') {
        throw new InvalidArgumentException('Lead name is required.');
    }
    foreach ([
        'Lead name' => [$leadName, 200],
        'Organization' => [$organizationName, 200],
        'Lead source' => [$leadSource, 120],
        'Email' => [$email, 180],
        'Phone' => [$phone, 80],
        'Mobile' => [$mobile, 80],
        'Website' => [$website, 220],
        'Industry' => [$industry, 120],
    ] as $label => [$value, $max]) {
        if (strlen((string) $value) > $max) {
            throw new InvalidArgumentException($label . ' exceeds the allowed length.');
        }
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email must be a valid email address.');
    }
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Website must be a valid URL.');
    }
    if (strlen($notes) > 10000) {
        throw new InvalidArgumentException('Notes exceeds the allowed length.');
    }

    $db->BeginTrans();
    try {
        $existing = null;
        if ($leadKey !== '') {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_lead WHERE lead_key = ? AND company_key_hash = ? FOR UPDATE', [$leadKey, $companyKeyHash]);
            if (!$existing) {
                throw new InvalidArgumentException('Lead was not found for this company.');
            }
        } else {
            $existing = $db->GetRow('SELECT * FROM project_company_sales_lead WHERE lead_code = ? AND company_key_hash = ? FOR UPDATE', [$leadCode, $companyKeyHash]);
            if ($existing) {
                $leadKey = (string) $existing['lead_key'];
            }
        }
        if ($leadKey === '') {
            $leadKey = bx_uuid();
        }
        $duplicateCode = (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_code = ? AND lead_key <> ?',
            [$companyKeyHash, $leadCode, $leadKey]
        );
        if ($duplicateCode > 0) {
            throw new InvalidArgumentException('Lead code already belongs to another lead.');
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_sales_lead (
                lead_key, company_key, company_key_hash, lead_code, lead_name, organization_name,
                lead_status, lead_source, campaign_key, territory_key, salesperson_key, email,
                phone, mobile, website, industry, estimated_value, next_contact_date, notes,
                created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                company_key = VALUES(company_key),
                company_key_hash = VALUES(company_key_hash),
                lead_code = VALUES(lead_code),
                lead_name = VALUES(lead_name),
                organization_name = VALUES(organization_name),
                lead_status = VALUES(lead_status),
                lead_source = VALUES(lead_source),
                campaign_key = VALUES(campaign_key),
                territory_key = VALUES(territory_key),
                salesperson_key = VALUES(salesperson_key),
                email = VALUES(email),
                phone = VALUES(phone),
                mobile = VALUES(mobile),
                website = VALUES(website),
                industry = VALUES(industry),
                estimated_value = VALUES(estimated_value),
                next_contact_date = VALUES(next_contact_date),
                notes = VALUES(notes),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $leadKey, $companyKey, $companyKeyHash, $leadCode, $leadName, $organizationName, $leadStatus, $leadSource,
                $campaignKey !== '' ? $campaignKey : null, $territoryKey !== '' ? $territoryKey : null, $salespersonKey !== '' ? $salespersonKey : null,
                $email, $phone, $mobile, $website, $industry, $estimatedValue !== '' ? $estimatedValue : null,
                $nextContactDate !== '' ? $nextContactDate : null, $notes, $adminKey, $adminKey,
            ],
            'Sales/CRM lead save'
        );

        $savedRow = $db->GetRow(
            'SELECT lead_key, company_key, company_key_hash, lead_code, lead_name, organization_name, lead_status, lead_source, campaign_key, territory_key, salesperson_key, email, phone, mobile, website, industry, estimated_value, next_contact_date, notes FROM project_company_sales_lead WHERE lead_key = ? AND company_key_hash = ? LIMIT 1',
            [$leadKey, $companyKeyHash]
        );
        foreach ([
            'lead_key' => $leadKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'lead_code' => $leadCode,
            'lead_name' => $leadName,
            'organization_name' => $organizationName,
            'lead_status' => $leadStatus,
            'lead_source' => $leadSource,
            'campaign_key' => $campaignKey,
            'territory_key' => $territoryKey,
            'salesperson_key' => $salespersonKey,
            'email' => $email,
            'phone' => $phone,
            'mobile' => $mobile,
            'website' => $website,
            'industry' => $industry,
            'estimated_value' => $estimatedValue,
            'next_contact_date' => $nextContactDate,
            'notes' => $notes,
        ] as $column => $expectedValue) {
            if (!is_array($savedRow) || (string) ($savedRow[$column] ?? '') !== (string) $expectedValue) {
                throw new RuntimeException('Sales/CRM lead read-back verification failed for ' . $column . '.');
            }
        }

        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_sales_lead', $leadKey, [
            'company_key' => $companyKey,
            'company_name' => (string) $company['company_name'],
            'lead_code' => $leadCode,
            'lead_name' => $leadName,
            'lead_status' => $leadStatus,
            'admin_key' => $adminKey,
        ], $existing ? 'Company admin updated Sales/CRM lead.' : 'Company admin created Sales/CRM lead.');

        $db->CommitTrans();
        yovel_admin_sales_crm_clear_rehydration('lead');
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return $existing ? 'Lead updated.' : 'Lead created.';
}

function yovel_admin_set_sales_lead_status(array $company, array $admin): string
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');

    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $leadKey = trim((string) ($_POST['lead_key'] ?? ''));
    $leadStatus = yovel_admin_status((string) ($_POST['lead_status'] ?? ''), ['DRAFT', 'OPEN', 'QUALIFIED', 'CONVERTED', 'LOST', 'INACTIVE', 'DELETED'], '');
    if (!yovel_admin_is_uuid($leadKey) || $leadStatus === '') {
        throw new InvalidArgumentException('Invalid lead status request.');
    }

    $db->BeginTrans();
    try {
        $existing = $db->GetRow('SELECT lead_key, company_key, lead_code, lead_name FROM project_company_sales_lead WHERE lead_key = ? AND company_key_hash = ? FOR UPDATE', [$leadKey, $companyKeyHash]);
        if (!$existing) {
            throw new InvalidArgumentException('Lead was not found for this company.');
        }
        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_sales_lead SET lead_status = ?, updated_by_admin_key = ? WHERE lead_key = ? AND company_key_hash = ?',
            [$leadStatus, (string) $admin['admin_key'], $leadKey, $companyKeyHash],
            'Sales/CRM lead status update'
        );
        $savedStatus = (string) $db->GetOne('SELECT lead_status FROM project_company_sales_lead WHERE lead_key = ? AND company_key_hash = ? LIMIT 1', [$leadKey, $companyKeyHash]);
        if ($savedStatus !== $leadStatus) {
            throw new RuntimeException('Sales/CRM lead status read-back verification failed.');
        }
        bx_audit($leadStatus === 'DELETED' ? 'DELETE' : 'STATUS', 'project_company_sales_lead', $leadKey, [
            'company_key' => (string) ($existing['company_key'] ?? ''),
            'company_name' => (string) $company['company_name'],
            'lead_code' => (string) ($existing['lead_code'] ?? ''),
            'lead_name' => (string) ($existing['lead_name'] ?? ''),
            'lead_status' => $leadStatus,
            'admin_key' => (string) $admin['admin_key'],
        ], 'Company admin changed Sales/CRM lead status.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Lead status updated.';
}

function yovel_admin_sales_field_value(array $record, string $fieldKey): string
{
    return (string) ($record[$fieldKey] ?? '');
}

function yovel_admin_sales_field_options(string $fieldKey, array $salesCrmData): array
{
    if ($fieldKey === 'campaign_key') {
        return array_map(static fn (array $row): array => ['value' => (string) $row['campaign_key'], 'label' => (string) $row['campaign_name']], $salesCrmData['campaigns'] ?? []);
    }
    if ($fieldKey === 'territory_key') {
        return array_map(static fn (array $row): array => ['value' => (string) $row['territory_key'], 'label' => (string) $row['territory_name']], $salesCrmData['territories'] ?? []);
    }
    if ($fieldKey === 'salesperson_key') {
        return array_map(static fn (array $row): array => ['value' => (string) $row['salesperson_key'], 'label' => (string) $row['salesperson_name']], $salesCrmData['salespersons'] ?? []);
    }
    if ($fieldKey === 'customer_key') {
        return array_map(static fn (array $row): array => ['value' => (string) $row['customer_key'], 'label' => (string) $row['customer_name']], $salesCrmData['customers'] ?? []);
    }

    return [];
}

function yovel_admin_render_sales_form_field(array $field, array $record, array $salesCrmData): void
{
    if (!filter_var($field['visible'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
        return;
    }
    $key = (string) ($field['key'] ?? '');
    $label = (string) ($field['label'] ?? $key);
    $type = (string) ($field['type'] ?? 'text');
    $required = filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'required' : '';
    $value = yovel_admin_sales_field_value($record, $key);
    $width = (string) ($field['width'] ?? 'half');
    $class = $width === 'full' ? 'lg:col-span-3' : ($width === 'third' ? '' : 'lg:col-span-1');
    ?>
    <div class="grid gap-1.5 <?= bx_h($class) ?>">
        <label class="text-xs font-medium" for="sales_<?= bx_h($key) ?>"><?= bx_h($label) ?></label>
        <?php if ($type === 'textarea'): ?>
            <textarea class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" id="sales_<?= bx_h($key) ?>" name="<?= bx_h($key) ?>" <?= $required ?>><?= bx_h($value) ?></textarea>
        <?php elseif ($type === 'select'): ?>
            <?php $options = yovel_admin_sales_field_options($key, $salesCrmData); ?>
            <?php if (!$options && is_array($field['options'] ?? null)): ?>
                <?php foreach ($field['options'] as $option): ?>
                    <?php $options[] = ['value' => (string) $option, 'label' => (string) $option]; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="sales_<?= bx_h($key) ?>" name="<?= bx_h($key) ?>" <?= $required ?>>
                <option value=""></option>
                <?php foreach ($options as $option): ?>
                    <option value="<?= bx_h((string) $option['value']) ?>" <?= $value === (string) $option['value'] ? 'selected' : '' ?>><?= bx_h((string) $option['label']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <?php $inputType = in_array($type, ['email', 'date', 'number', 'url'], true) ? $type : 'text'; ?>
            <input class="h-9 rounded-md border bg-background px-3 text-sm" id="sales_<?= bx_h($key) ?>" name="<?= bx_h($key) ?>" type="<?= bx_h($inputType) ?>" value="<?= bx_h($value) ?>" <?= $inputType === 'number' ? 'step="0.01" min="0"' : '' ?> <?= $required ?>>
        <?php endif; ?>
    </div>
    <?php
}

function yovel_admin_sales_schema_section_label(array $schema, string $sectionKey): string
{
    foreach (($schema['sections'] ?? []) as $section) {
        if ((string) ($section['key'] ?? '') === $sectionKey) {
            return (string) ($section['label'] ?? $sectionKey);
        }
    }

    return ucwords(str_replace('-', ' ', $sectionKey));
}
