<?php
declare(strict_types=1);

function yovel_admin_sales_crm_form_targets(): array
{
    $targets = [];
    foreach (yovel_admin_sales_crm_sections() as $section => $metadata) {
        $recordType = trim((string) ($metadata['record_type'] ?? ''));
        if ($recordType !== '') {
            $targets[(string) $section] = $recordType;
        }
    }

    return $targets;
}

function yovel_admin_sales_crm_form_adapter(): array
{
    if (!function_exists('yovel_admin_shared_form_adapter')) {
        throw new RuntimeException('The shared Form Builder contract is not loaded.');
    }
    $shared = yovel_admin_shared_form_adapter('sales-crm');
    $schemas = yovel_admin_sales_crm_default_form_schemas();
    $protected = [];
    $workflowConstraints = [];
    foreach ($schemas as $recordType => $schema) {
        $keys = array_map('strval', $schema['requiredSystemFields'] ?? []);
        foreach ($schema['fields'] ?? [] as $field) {
            if (!empty($field['system'])) {
                $keys[] = (string) ($field['key'] ?? '');
            }
        }
        $protected[$recordType] = array_values(array_unique(array_filter($keys)));
        $workflowConstraints[$recordType] = [
            'protected_fields' => $protected[$recordType],
            'published_immutable' => true,
            'submitted_record_version_bound' => true,
        ];
    }

    return [
        'module' => 'sales-crm',
        'target_record_types' => yovel_admin_sales_crm_form_targets(),
        'protected_fields' => $protected,
        'field_types' => array_values(array_unique(array_merge(
            is_array($shared['field_types'] ?? null) ? $shared['field_types'] : [],
            ['SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'CURRENCY', 'DATE', 'DROPDOWN', 'CHECKBOXES', 'EMAIL', 'PHONE', 'URL', 'SECTION']
        ))),
        'row_column_layout' => array_merge(
            ['version' => 1, 'max_columns' => 3, 'stable_keys' => true],
            is_array($shared['row_column_layout'] ?? null) ? $shared['row_column_layout'] : [],
            ['max_columns' => 3, 'stable_keys' => true]
        ),
        'normalize' => 'yovel_admin_sales_crm_normalize_schema',
        'version_identity' => 'yovel_admin_sales_crm_schema_checksum',
        'renderer' => 'company/admin/modules/sales-crm/views/workspace.php',
        'persistence_mappings' => [
            'lead' => ['table' => 'project_company_sales_lead', 'key' => 'lead_key', 'business_key' => 'lead_code', 'status' => 'lead_status'],
            'prospect' => ['table' => 'project_company_sales_prospect', 'key' => 'prospect_key', 'business_key' => 'prospect_code', 'status' => 'prospect_status'],
            'appointment' => ['table' => 'project_company_sales_appointment', 'key' => 'appointment_key', 'business_key' => 'appointment_code', 'status' => 'appointment_status'],
            'campaign' => ['table' => 'project_company_sales_campaign', 'key' => 'campaign_key', 'business_key' => 'campaign_code', 'status' => 'campaign_status'],
            'customer' => ['table' => 'project_company_sales_customer', 'key' => 'customer_key', 'business_key' => 'customer_code', 'status' => 'customer_status'],
            'opportunity' => ['table' => null, 'state' => 'SC-04'],
            'quotation' => ['table' => null, 'state' => 'SC-07'],
            'sales-order' => ['table' => null, 'state' => 'SC-08'],
            'customer-credit-limit' => ['table' => null, 'state' => 'SC-06'],
        ],
        'workflow_constraints' => $workflowConstraints,
        'legacy_status_map' => [
            'ACTIVE' => 'PUBLISHED',
            'INACTIVE' => 'ARCHIVED',
            'DELETED' => 'ARCHIVED',
        ],
    ];
}

function yovel_admin_sales_crm_reorder_fields(string $recordType, array $fields, array $requestedKeys): array
{
    $recordType = yovel_admin_sales_crm_record_type($recordType);
    $section = array_search($recordType, yovel_admin_sales_crm_form_targets(), true);
    if (!is_string($section)) {
        throw new InvalidArgumentException('The Sales / CRM Form Builder target is not registered.');
    }

    // The shared generic adapter registers the orchestrator-owned default route;
    // local validation above expands that stable reorder contract to Sales targets.
    $shared = yovel_admin_shared_form_adapter('sales-crm');
    $sharedTarget = (string) array_key_first($shared['target_record_types'] ?? []);
    if ($sharedTarget === '') {
        throw new RuntimeException('The shared Sales / CRM Form Builder target is unavailable.');
    }
    return yovel_admin_shared_form_reorder('sales-crm', $sharedTarget, $fields, $requestedKeys);
}

function yovel_admin_sales_crm_schema_checksum(array $schema): string
{
    $canonicalize = static function (mixed $value) use (&$canonicalize): mixed {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $canonicalize($item);
        }
        return $value;
    };
    $encoded = json_encode($canonicalize($schema), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    return hash('sha256', $encoded);
}

function yovel_admin_sales_crm_shared_schema_state(array $schemaRow): string
{
    $status = strtoupper(trim((string) ($schemaRow['schema_status'] ?? '')));
    $map = yovel_admin_sales_crm_form_adapter()['legacy_status_map'];

    return (string) ($map[$status] ?? 'DRAFT');
}

function yovel_admin_sales_crm_store_rehydration(array $company, string $recordType, array $values): void
{
    $recordType = yovel_admin_sales_crm_record_type($recordType);
    $allowed = ['lead_key' => true];
    foreach (yovel_admin_sales_crm_default_form_schemas()[$recordType]['fields'] ?? [] as $field) {
        $allowed[(string) ($field['key'] ?? '')] = true;
    }
    $filtered = [];
    foreach ($values as $key => $value) {
        if (isset($allowed[(string) $key]) && is_scalar($value)) {
            $filtered[(string) $key] = (string) $value;
        }
    }
    $_SESSION['builderx_sales_crm_rehydration'][$recordType] = [
        'company_key_hash' => (string) ($company['company_key_hash'] ?? ''),
        'values' => $filtered,
    ];
}

function yovel_admin_sales_crm_clear_rehydration(string $recordType): void
{
    $recordType = yovel_admin_sales_crm_record_type($recordType);
    unset($_SESSION['builderx_sales_crm_rehydration'][$recordType]);
    if (empty($_SESSION['builderx_sales_crm_rehydration'])) {
        unset($_SESSION['builderx_sales_crm_rehydration']);
    }
}

function yovel_admin_sales_crm_pull_rehydration(array $company, string $recordType): array
{
    $recordType = yovel_admin_sales_crm_record_type($recordType);
    $state = $_SESSION['builderx_sales_crm_rehydration'][$recordType] ?? null;
    yovel_admin_sales_crm_clear_rehydration($recordType);
    if (!is_array($state) || !hash_equals((string) ($company['company_key_hash'] ?? ''), (string) ($state['company_key_hash'] ?? ''))) {
        return [];
    }

    return ['values' => is_array($state['values'] ?? null) ? $state['values'] : []];
}
