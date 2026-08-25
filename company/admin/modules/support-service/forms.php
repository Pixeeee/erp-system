<?php
declare(strict_types=1);

function yovel_admin_support_service_form_targets(): array
{
    return [
        'issues-tickets' => 'issue',
        'issue-priorities' => 'issue-priority',
        'issue-types' => 'issue-type',
        'sla-rules' => 'service-level-agreement',
        'warranty-claims' => 'warranty-claim',
        'telephony-call-types' => 'telephony-call-type',
        'incoming-call-settings' => 'incoming-call-settings',
        'voice-call-settings' => 'voice-call-settings',
    ];
}

function yovel_admin_support_service_default_form_schemas(): array
{
    $field = static fn (
        string $key,
        string $label,
        string $type,
        bool $required = false,
        string $width = 'half'
    ): array => [
        'key' => $key,
        'label' => $label,
        'type' => $type,
        'required' => $required,
        'visible' => true,
        'width' => $width,
        'system' => true,
    ];

    return [
        'issue' => [
            'requiredSystemFields' => ['subject', 'status', 'company_key_hash'],
            'fields' => [
                $field('subject', 'Subject', 'SHORT_TEXT', true, 'full'),
                $field('status', 'Status', 'DROPDOWN', true),
                $field('company_key_hash', 'Company scope', 'SHORT_TEXT', true),
                $field('description', 'Description', 'PARAGRAPH', false, 'full'),
                $field('priority', 'Priority', 'DROPDOWN'),
                $field('issue_type', 'Issue type', 'DROPDOWN'),
                $field('customer_key', 'Customer', 'REFERENCE'),
                $field('project_key', 'Project or service work', 'REFERENCE'),
            ],
        ],
        'issue-priority' => [
            'requiredSystemFields' => ['priority_name', 'priority_status', 'company_key_hash'],
            'fields' => [
                $field('priority_name', 'Priority name', 'SHORT_TEXT', true),
                $field('priority_status', 'Status', 'DROPDOWN', true),
                $field('company_key_hash', 'Company scope', 'SHORT_TEXT', true),
                $field('priority_description', 'Description', 'PARAGRAPH', false, 'full'),
            ],
        ],
        'issue-type' => [
            'requiredSystemFields' => ['issue_type_name', 'issue_type_status', 'company_key_hash'],
            'fields' => [
                $field('issue_type_name', 'Issue type', 'SHORT_TEXT', true),
                $field('issue_type_status', 'Status', 'DROPDOWN', true),
                $field('company_key_hash', 'Company scope', 'SHORT_TEXT', true),
                $field('issue_type_description', 'Description', 'PARAGRAPH', false, 'full'),
            ],
        ],
        'service-level-agreement' => [
            'requiredSystemFields' => [
                'sla_code',
                'service_level_name',
                'document_type',
                'timezone_name',
                'enabled',
                'apply_for_resolution',
                'sla_status',
                'company_key_hash',
            ],
            'fields' => [
                $field('sla_code', 'SLA code', 'SHORT_TEXT', true),
                $field('service_level_name', 'Service level name', 'SHORT_TEXT', true),
                $field('document_type', 'Applies to', 'DROPDOWN', true),
                $field('timezone_name', 'Timezone', 'SHORT_TEXT', true),
                $field('enabled', 'Enabled', 'CHECKBOXES', true),
                $field('apply_for_resolution', 'Track resolution', 'CHECKBOXES', true),
                $field('sla_status', 'Status', 'DROPDOWN', true),
                $field('company_key_hash', 'Company scope', 'SHORT_TEXT', true),
                $field('start_date', 'Start date', 'DATE'),
                $field('end_date', 'End date', 'DATE'),
                $field('entity_type', 'Applicability type', 'DROPDOWN'),
                $field('entity_key', 'Customer scope', 'REFERENCE'),
                $field('condition_json', 'Condition', 'PARAGRAPH', false, 'full'),
            ],
        ],
        'warranty-claim' => [
            'requiredSystemFields' => ['complaint_date', 'customer_key', 'complaint', 'claim_status', 'company_key_hash'],
            'fields' => [
                $field('complaint_date', 'Complaint date', 'DATE', true),
                $field('customer_key', 'Customer', 'REFERENCE', true),
                $field('complaint', 'Complaint', 'PARAGRAPH', true, 'full'),
                $field('claim_status', 'Status', 'DROPDOWN', true),
                $field('company_key_hash', 'Company scope', 'SHORT_TEXT', true),
                $field('item_key', 'Item', 'REFERENCE'),
                $field('serial_key', 'Serial number', 'REFERENCE'),
                $field('service_address', 'Service address', 'PARAGRAPH', false, 'full'),
            ],
        ],
        'telephony-call-type' => [
            'requiredSystemFields' => ['call_type_name', 'call_type_status', 'company_key_hash'],
            'fields' => [
                $field('call_type_name', 'Call type', 'SHORT_TEXT', true),
                $field('call_type_status', 'Status', 'DROPDOWN', true),
                $field('company_key_hash', 'Company scope', 'SHORT_TEXT', true),
            ],
        ],
        'incoming-call-settings' => [
            'requiredSystemFields' => ['provider_key', 'call_routing', 'setting_status', 'company_key_hash'],
            'fields' => [
                $field('provider_key', 'Provider', 'SHORT_TEXT', true),
                $field('call_routing', 'Call routing', 'DROPDOWN', true),
                $field('setting_status', 'Status', 'DROPDOWN', true),
                $field('company_key_hash', 'Company scope', 'SHORT_TEXT', true),
                $field('greeting_message', 'Greeting message', 'PARAGRAPH', false, 'full'),
                $field('agent_busy_message', 'Agent busy message', 'PARAGRAPH', false, 'full'),
                $field('agent_unavailable_message', 'Agent unavailable message', 'PARAGRAPH', false, 'full'),
            ],
        ],
        'voice-call-settings' => [
            'requiredSystemFields' => ['user_key', 'receiving_device', 'setting_status', 'company_key_hash'],
            'fields' => [
                $field('user_key', 'User', 'REFERENCE', true),
                $field('receiving_device', 'Receiving device', 'DROPDOWN', true),
                $field('setting_status', 'Status', 'DROPDOWN', true),
                $field('company_key_hash', 'Company scope', 'SHORT_TEXT', true),
                $field('greeting_message', 'Greeting message', 'PARAGRAPH', false, 'full'),
            ],
        ],
    ];
}

function yovel_admin_support_service_form_adapter(): array
{
    $shared = function_exists('yovel_admin_shared_form_adapter')
        ? yovel_admin_shared_form_adapter('support-service')
        : [];
    $schemas = yovel_admin_support_service_default_form_schemas();
    $protected = [];
    $constraints = [];
    foreach ($schemas as $recordType => $schema) {
        $protected[$recordType] = array_values(array_map('strval', $schema['requiredSystemFields']));
        $constraints[$recordType] = [
            'protected_fields' => $protected[$recordType],
            'stable_field_keys' => true,
            'published_immutable' => true,
            'submitted_record_version_bound' => true,
        ];
    }

    return [
        'module' => 'support-service',
        'target_record_types' => yovel_admin_support_service_form_targets(),
        'protected_fields' => $protected,
        'field_types' => array_values(array_unique(array_merge(
            is_array($shared['field_types'] ?? null) ? $shared['field_types'] : [],
            ['SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'DATE', 'DATETIME', 'DROPDOWN', 'CHECKBOXES', 'EMAIL', 'PHONE', 'URL', 'DURATION', 'REFERENCE', 'SECTION']
        ))),
        'row_column_layout' => array_merge(
            ['version' => 2, 'max_columns' => 3, 'stable_keys' => true],
            is_array($shared['row_column_layout'] ?? null) ? $shared['row_column_layout'] : [],
            ['version' => 2, 'max_columns' => 3, 'stable_keys' => true]
        ),
        'normalize' => 'yovel_admin_support_service_normalize_form_schema',
        'version_identity' => 'yovel_admin_support_service_form_checksum',
        'renderer' => 'company/admin/modules/support-service/views/workspace.php',
        'persistence_mappings' => [
            'issue' => ['table' => 'project_company_support_issue', 'key' => 'issue_key', 'business_key' => 'issue_code', 'status' => 'issue_status'],
            'issue-priority' => ['table' => 'project_company_support_issue_priority', 'key' => 'issue_priority_key', 'business_key' => 'priority_name', 'status' => 'priority_status'],
            'issue-type' => ['table' => 'project_company_support_issue_type', 'key' => 'issue_type_key', 'business_key' => 'issue_type_name', 'status' => 'issue_type_status'],
            'service-level-agreement' => ['table' => 'project_company_support_sla', 'key' => 'service_level_agreement_key', 'business_key' => 'sla_code', 'status' => 'sla_status'],
            'warranty-claim' => ['table' => 'project_company_support_warranty_claim', 'key' => 'warranty_claim_key', 'business_key' => 'claim_code', 'status' => 'claim_status'],
            'telephony-call-type' => ['table' => 'project_company_support_telephony_call_type', 'key' => 'telephony_call_type_key', 'business_key' => 'call_type_name', 'status' => 'call_type_status'],
            'incoming-call-settings' => ['table' => 'project_company_support_incoming_call_setting', 'key' => 'incoming_call_setting_key', 'business_key' => 'provider_key', 'status' => 'setting_status'],
            'voice-call-settings' => ['table' => 'project_company_support_voice_call_setting', 'key' => 'voice_call_setting_key', 'business_key' => 'user_key_hash', 'status' => 'setting_status'],
        ],
        'workflow_constraints' => $constraints,
        'version_workflow' => ['DRAFT', 'PUBLISHED', 'ARCHIVED'],
    ];
}

function yovel_admin_support_service_record_type(string $recordType): string
{
    $recordType = strtolower(trim($recordType));
    if (!isset(yovel_admin_support_service_default_form_schemas()[$recordType])) {
        throw new InvalidArgumentException('The Support Form Builder target is not registered.');
    }

    return $recordType;
}

function yovel_admin_support_service_normalize_form_schema(
    string $recordType,
    array $schema,
    ?array $publishedSchema = null
): array {
    $recordType = yovel_admin_support_service_record_type($recordType);
    $defaults = yovel_admin_support_service_default_form_schemas()[$recordType];
    $adapter = yovel_admin_support_service_form_adapter();
    $allowedTypes = array_fill_keys($adapter['field_types'], true);
    $protected = array_fill_keys($defaults['requiredSystemFields'], true);
    $defaultByKey = [];
    foreach ($defaults['fields'] as $field) {
        $defaultByKey[(string) $field['key']] = $field;
    }

    $custom = [];
    $seen = [];
    foreach (is_array($schema['fields'] ?? null) ? $schema['fields'] : [] as $field) {
        if (!is_array($field)) {
            throw new InvalidArgumentException('Support Form Builder fields must be objects.');
        }
        $key = strtolower(trim((string) ($field['key'] ?? '')));
        if ($key === '' || preg_match('/^[a-z][a-z0-9_-]{0,79}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Support Form Builder fields require safe stable keys.');
        }
        if (isset($seen[$key])) {
            throw new InvalidArgumentException('Support Form Builder fields require unique stable keys.');
        }
        $seen[$key] = true;
        if (isset($protected[$key]) || isset($defaultByKey[$key])) {
            continue;
        }
        if (!str_starts_with($key, 'custom_') && !str_starts_with($key, 'custom-')) {
            continue;
        }
        $type = strtoupper(trim((string) ($field['type'] ?? 'SHORT_TEXT')));
        if (!isset($allowedTypes[$type])) {
            throw new InvalidArgumentException('Support Form Builder field type is not supported.');
        }
        $label = trim((string) ($field['label'] ?? ''));
        if ($label === '' || strlen($label) > 160) {
            throw new InvalidArgumentException('Support Form Builder custom fields require a label of at most 160 characters.');
        }
        $width = strtolower(trim((string) ($field['width'] ?? 'half')));
        if (!in_array($width, ['third', 'half', 'full'], true)) {
            throw new InvalidArgumentException('Support Form Builder field width is invalid.');
        }
        $custom[$key] = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'visible' => !array_key_exists('visible', $field) || filter_var($field['visible'], FILTER_VALIDATE_BOOLEAN),
            'width' => $width,
            'system' => false,
            'options' => array_values(array_filter(array_map(
                static fn (mixed $option): string => substr(trim((string) $option), 0, 160),
                is_array($field['options'] ?? null) ? $field['options'] : []
            ), static fn (string $option): bool => $option !== '')),
        ];
    }

    if ($publishedSchema !== null) {
        $published = yovel_admin_support_service_normalize_form_schema($recordType, $publishedSchema, null);
        foreach ($published['fields'] as $publishedField) {
            $key = (string) $publishedField['key'];
            if (!empty($publishedField['system'])) {
                continue;
            }
            if (!isset($custom[$key]) || (string) $custom[$key]['type'] !== (string) $publishedField['type']) {
                throw new InvalidArgumentException('Published Support Form Builder field keys and types are immutable.');
            }
        }
    }

    $fields = [];
    foreach ($defaults['requiredSystemFields'] as $key) {
        $fields[] = $defaultByKey[$key];
    }
    foreach ($custom as $field) {
        $fields[] = $field;
    }

    return [
        'record_type' => $recordType,
        'layout_version' => 2,
        'fields' => $fields,
        'requiredSystemFields' => array_values($defaults['requiredSystemFields']),
    ];
}

function yovel_admin_support_service_form_checksum(array $schema): string
{
    $canonicalize = static function (mixed $value) use (&$canonicalize): mixed {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $canonicalize($item);
        }

        return $value;
    };
    $encoded = json_encode(
        $canonicalize($schema),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    return hash('sha256', $encoded);
}

function yovel_admin_support_service_reorder_fields(string $recordType, array $fields, array $requestedKeys): array
{
    $recordType = yovel_admin_support_service_record_type($recordType);
    $byKey = [];
    $original = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $key = trim((string) ($field['key'] ?? ''));
        if ($key === '' || isset($byKey[$key])) {
            throw new InvalidArgumentException('Support Form Builder fields require unique stable keys.');
        }
        $byKey[$key] = $field;
        $original[] = $key;
    }
    $ordered = [];
    $used = [];
    foreach ($requestedKeys as $key) {
        $key = trim((string) $key);
        if ($key !== '' && isset($byKey[$key]) && !isset($used[$key])) {
            $ordered[] = $byKey[$key];
            $used[$key] = true;
        }
    }
    foreach ($original as $key) {
        if (!isset($used[$key])) {
            $ordered[] = $byKey[$key];
        }
    }

    yovel_admin_support_service_normalize_form_schema($recordType, ['fields' => $ordered]);
    return $ordered;
}
