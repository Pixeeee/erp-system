<?php
declare(strict_types=1);

function yovel_admin_inventory_form_record_types(): array
{
    return [
        'items' => 'ITEM',
        'warehouses' => 'WAREHOUSE',
        'stock-entries' => 'STOCK_ENTRY',
        'stock-reconciliation' => 'STOCK_RECONCILIATION',
        'picking' => 'STOCK_RESERVATION',
        'batch-numbers' => 'BATCH',
        'serial-numbers' => 'SERIAL',
        'shipment' => 'SHIPMENT',
        'quality-inspection' => 'QUALITY_INSPECTION',
    ];
}

function yovel_admin_inventory_record_type(string $value): string
{
    $value = strtoupper(trim((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper($value)), '_'));
    if (!in_array($value, array_values(yovel_admin_inventory_form_record_types()), true)) {
        throw new InvalidArgumentException('Inventory Form Builder record type is not registered.');
    }
    return $value;
}

function yovel_admin_inventory_field(
    string $key,
    string $label,
    string $type,
    string $section,
    bool $required,
    int $column,
    int $order
): array {
    return [
        'key' => $key,
        'label' => $label,
        'type' => $type,
        'section' => $section,
        'required' => $required,
        'visible' => true,
        'system' => true,
        'column' => $column,
        'order' => $order,
        'precision' => in_array($type, ['NUMBER', 'QUANTITY', 'CURRENCY'], true) ? 9 : 0,
        'options' => [],
    ];
}

function yovel_admin_inventory_default_form_schemas(): array
{
    $definitions = [
        'ITEM' => [
            ['item_code', 'Item code', 'SHORT_TEXT', 'identity', true, 1, 10],
            ['item_name', 'Item name', 'SHORT_TEXT', 'identity', true, 2, 20],
            ['stock_uom', 'Stock UOM', 'LINK', 'inventory', true, 1, 30],
            ['is_stock_item', 'Maintain stock', 'TOGGLE', 'inventory', true, 2, 40],
        ],
        'WAREHOUSE' => [
            ['warehouse_code', 'Warehouse code', 'SHORT_TEXT', 'identity', true, 1, 10],
            ['warehouse_name', 'Warehouse name', 'SHORT_TEXT', 'identity', true, 2, 20],
            ['is_group', 'Group warehouse', 'TOGGLE', 'structure', true, 1, 30],
        ],
        'STOCK_ENTRY' => [
            ['entry_type', 'Entry type', 'DROPDOWN', 'document', true, 1, 10],
            ['posting_date', 'Posting date', 'DATE', 'document', true, 2, 20],
            ['items', 'Items', 'TABLE', 'lines', true, 1, 30],
        ],
        'STOCK_RECONCILIATION' => [
            ['purpose', 'Purpose', 'DROPDOWN', 'document', true, 1, 10],
            ['posting_date', 'Posting date', 'DATE', 'document', true, 2, 20],
            ['items', 'Items', 'TABLE', 'lines', true, 1, 30],
        ],
        'STOCK_RESERVATION' => [
            ['item_code', 'Item code', 'LINK', 'reservation', true, 1, 10],
            ['warehouse', 'Warehouse', 'LINK', 'reservation', true, 2, 20],
            ['reserved_qty', 'Reserved quantity', 'QUANTITY', 'reservation', true, 3, 30],
        ],
        'BATCH' => [
            ['batch_number', 'Batch number', 'SHORT_TEXT', 'identity', true, 1, 10],
            ['item_code', 'Item code', 'LINK', 'identity', true, 2, 20],
        ],
        'SERIAL' => [
            ['serial_number', 'Serial number', 'SHORT_TEXT', 'identity', true, 1, 10],
            ['item_code', 'Item code', 'LINK', 'identity', true, 2, 20],
        ],
        'SHIPMENT' => [
            ['shipment_number', 'Shipment number', 'SHORT_TEXT', 'identity', true, 1, 10],
            ['status', 'Status', 'DROPDOWN', 'identity', true, 2, 20],
        ],
        'QUALITY_INSPECTION' => [
            ['inspection_number', 'Inspection number', 'SHORT_TEXT', 'identity', true, 1, 10],
            ['status', 'Status', 'DROPDOWN', 'result', true, 2, 20],
        ],
    ];

    $schemas = [];
    foreach ($definitions as $recordType => $fields) {
        $normalizedFields = array_map(
            static fn (array $field): array => yovel_admin_inventory_field(...$field),
            $fields
        );
        $sections = [];
        foreach ($normalizedFields as $field) {
            $sections[(string) $field['section']] = [
                'key' => (string) $field['section'],
                'label' => ucwords(str_replace('_', ' ', (string) $field['section'])),
            ];
        }
        $schemas[$recordType] = [
            'version' => 1,
            'recordType' => $recordType,
            'requiredSystemFields' => array_column($normalizedFields, 'key'),
            'sections' => array_values($sections),
            'fields' => $normalizedFields,
        ];
    }

    return $schemas;
}

function yovel_admin_inventory_form_adapter(): array
{
    $protected = [];
    foreach (yovel_admin_inventory_default_form_schemas() as $recordType => $schema) {
        $protected[$recordType] = array_values(array_map('strval', $schema['requiredSystemFields']));
    }

    return [
        'module' => 'inventory-warehouse',
        'target_record_types' => yovel_admin_inventory_form_record_types(),
        'protected_fields' => $protected,
        'field_types' => [
            'SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'QUANTITY', 'CURRENCY', 'DATE', 'DATETIME',
            'DROPDOWN', 'CHECKBOXES', 'TOGGLE', 'LINK', 'BARCODE', 'TABLE', 'SECTION',
        ],
        'row_column_layout' => ['version' => 1, 'max_columns' => 3, 'stable_keys' => true],
        'normalize' => 'yovel_admin_inventory_normalize_form_schema',
        'version_identity' => 'yovel_admin_inventory_form_checksum',
        'renderer' => 'company/admin/modules/inventory-warehouse/views/form-builder.php',
    ];
}

function yovel_admin_inventory_field_key(string $value): string
{
    $value = strtolower(trim($value));
    if ($value !== '' && preg_match('/^[a-z][a-z0-9_]{1,79}$/', $value) === 1) {
        return $value;
    }
    return 'custom_' . strtolower(substr(str_replace('-', '', bx_uuid()), 0, 24));
}

function yovel_admin_inventory_normalize_form_schema(string $recordType, array $schema, int $version): array
{
    $recordType = yovel_admin_inventory_record_type($recordType);
    if ($version < 1) {
        throw new InvalidArgumentException('Inventory Form Builder version must be positive.');
    }
    $inputFields = $schema['fields'] ?? [];
    if (!is_array($inputFields) || count($inputFields) > 120) {
        throw new InvalidArgumentException('An Inventory form can contain up to 120 fields.');
    }

    $defaults = yovel_admin_inventory_default_form_schemas()[$recordType];
    $protectedKeys = array_fill_keys($defaults['requiredSystemFields'], true);
    $allowedTypes = yovel_admin_inventory_form_adapter()['field_types'];
    $customFields = [];
    $usedKeys = $protectedKeys;
    foreach ($inputFields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }
        $rawKey = trim((string) ($field['key'] ?? ''));
        if ($rawKey !== '' && isset($protectedKeys[$rawKey])) {
            continue;
        }
        $key = yovel_admin_inventory_field_key($rawKey);
        if (isset($usedKeys[$key])) {
            throw new InvalidArgumentException('Inventory form fields require unique stable keys.');
        }
        $usedKeys[$key] = true;

        $label = trim((string) ($field['label'] ?? ''));
        if ($label === '') {
            $label = 'Untitled field';
        }
        if (strlen($label) > 180) {
            throw new InvalidArgumentException('Inventory field labels must be 180 characters or fewer.');
        }
        $type = strtoupper(trim((string) ($field['type'] ?? 'SHORT_TEXT')));
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException('Inventory field type is not supported.');
        }
        $section = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) ($field['section'] ?? 'custom'))), '_');
        $section = $section !== '' ? $section : 'custom';
        $options = $field['options'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }
        $options = array_values(array_filter(array_map(
            static fn ($option): string => substr(trim((string) $option), 0, 160),
            $options
        ), static fn (string $option): bool => $option !== ''));
        if (count($options) > 40) {
            throw new InvalidArgumentException('Inventory fields can contain up to 40 options.');
        }
        if (!in_array($type, ['DROPDOWN', 'CHECKBOXES'], true)) {
            $options = [];
        }
        $precision = in_array($type, ['NUMBER', 'QUANTITY', 'CURRENCY'], true)
            ? max(0, min(9, (int) ($field['precision'] ?? 0)))
            : 0;

        $customFields[] = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'section' => $section,
            'required' => !empty($field['required']) && $type !== 'SECTION',
            'visible' => !array_key_exists('visible', $field) || !empty($field['visible']),
            'system' => false,
            'column' => max(1, min(3, (int) ($field['column'] ?? 1))),
            'order' => max(1, (int) ($field['order'] ?? (($index + 1) * 10))),
            'precision' => $precision,
            'options' => $options,
        ];
    }
    usort($customFields, static fn (array $left, array $right): int => [$left['order'], $left['key']] <=> [$right['order'], $right['key']]);

    $fields = array_merge($defaults['fields'], $customFields);
    $sections = [];
    foreach ($fields as $field) {
        $sectionKey = (string) $field['section'];
        $sections[$sectionKey] = [
            'key' => $sectionKey,
            'label' => ucwords(str_replace('_', ' ', $sectionKey)),
        ];
    }

    return [
        'version' => $version,
        'recordType' => $recordType,
        'requiredSystemFields' => array_values($defaults['requiredSystemFields']),
        'sections' => array_values($sections),
        'fields' => $fields,
    ];
}

function yovel_admin_inventory_form_json(array $schema): string
{
    return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function yovel_admin_inventory_form_checksum(string $schemaJson): string
{
    return hash('sha256', $schemaJson);
}

function yovel_admin_inventory_form_schema(array $company, string $recordType): array
{
    yovel_admin_inventory_warehouse_schema();
    $recordType = yovel_admin_inventory_record_type($recordType);
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('Inventory company scope is invalid.');
    }

    $row = bx_db()->GetRow(
        "SELECT * FROM project_company_inventory_form_schema
         WHERE company_key_hash = ? AND record_type = ? AND schema_status = 'ACTIVE'
         LIMIT 1",
        [$companyKeyHash, $recordType]
    );
    if (!is_array($row) || $row === []) {
        $default = yovel_admin_inventory_normalize_form_schema(
            $recordType,
            yovel_admin_inventory_default_form_schemas()[$recordType],
            1
        );
        return $default + [
            'persisted' => false,
            'form_schema_key' => '',
            'form_version_key' => '',
            'schema_checksum' => yovel_admin_inventory_form_checksum(yovel_admin_inventory_form_json($default)),
        ];
    }

    $decoded = json_decode((string) $row['schema_json'], true, 512, JSON_THROW_ON_ERROR);
    $normalized = yovel_admin_inventory_normalize_form_schema($recordType, $decoded, (int) $row['schema_version']);
    return $normalized + [
        'persisted' => true,
        'form_schema_key' => (string) $row['form_schema_key'],
        'form_version_key' => (string) $row['current_form_version_key'],
        'schema_checksum' => (string) $row['schema_checksum'],
    ];
}

function yovel_admin_save_inventory_form_schema(
    array $company,
    ?array $admin,
    string $recordType,
    array $schema
): array {
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_inventory_scope($company, $admin);
    yovel_admin_inventory_warehouse_schema();
    $recordType = yovel_admin_inventory_record_type($recordType);

    $saved = yovel_admin_inventory_in_transaction(static function (ADOConnection $db) use (
        $company,
        $companyKey,
        $companyKeyHash,
        $adminKey,
        $recordType,
        $schema
    ): array {
            $companyLock = $db->GetRow(
                "SELECT company_key FROM project_company
                 WHERE company_key = ? AND company_key_hash = ? AND company_status = 'ACTIVE'
                 FOR UPDATE",
                [$companyKey, $companyKeyHash]
            );
            if (!is_array($companyLock) || (string) ($companyLock['company_key'] ?? '') !== $companyKey) {
                throw new RuntimeException('Inventory company scope changed before save.');
            }

            $current = $db->GetRow(
                'SELECT * FROM project_company_inventory_form_schema WHERE company_key_hash = ? AND record_type = ? FOR UPDATE',
                [$companyKeyHash, $recordType]
            );
            $hasCurrent = is_array($current) && $current !== [];
            $currentVersion = $hasCurrent ? (int) $current['schema_version'] : 1;
            $candidate = yovel_admin_inventory_normalize_form_schema($recordType, $schema, $currentVersion);
            $candidateJson = yovel_admin_inventory_form_json($candidate);
            $candidateChecksum = yovel_admin_inventory_form_checksum($candidateJson);

            if ($hasCurrent && hash_equals((string) $current['schema_checksum'], $candidateChecksum)) {
                $version = $db->GetRow(
                    'SELECT * FROM project_company_inventory_form_schema_version WHERE company_key_hash = ? AND form_version_key = ? LIMIT 1',
                    [$companyKeyHash, (string) $current['current_form_version_key']]
                );
                if (!is_array($version) || $version === [] || (string) $version['schema_checksum'] !== $candidateChecksum) {
                    throw new RuntimeException('Inventory unchanged form version read-back failed.');
                }
                return $current;
            }

            $nextVersion = $hasCurrent ? $currentVersion + 1 : 1;
            $normalized = yovel_admin_inventory_normalize_form_schema($recordType, $schema, $nextVersion);
            $schemaJson = yovel_admin_inventory_form_json($normalized);
            $checksum = yovel_admin_inventory_form_checksum($schemaJson);

            $formSchemaKey = $hasCurrent ? (string) $current['form_schema_key'] : bx_uuid();
            $formVersionKey = bx_uuid();
            $auditKey = bx_uuid();
            yovel_admin_inventory_execute(
                $db,
                "INSERT INTO project_company_inventory_form_schema_version (
                    form_version_key, form_schema_key, company_key, company_key_hash, record_type,
                    version_number, schema_json, schema_checksum, created_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$formVersionKey, $formSchemaKey, $companyKey, $companyKeyHash, $recordType, $nextVersion, $schemaJson, $checksum, $adminKey],
                'Inventory form version save'
            );

            if ($hasCurrent) {
                yovel_admin_inventory_execute(
                    $db,
                    "UPDATE project_company_inventory_form_schema
                     SET schema_version = ?, schema_json = ?, schema_checksum = ?, current_form_version_key = ?,
                         schema_status = 'ACTIVE', updated_by_admin_key = ?
                     WHERE company_key_hash = ? AND form_schema_key = ?",
                    [$nextVersion, $schemaJson, $checksum, $formVersionKey, $adminKey, $companyKeyHash, $formSchemaKey],
                    'Inventory form schema update'
                );
            } else {
                yovel_admin_inventory_execute(
                    $db,
                    "INSERT INTO project_company_inventory_form_schema (
                        form_schema_key, company_key, company_key_hash, record_type, schema_status,
                        schema_version, schema_json, schema_checksum, current_form_version_key,
                        created_by_admin_key, updated_by_admin_key
                     ) VALUES (?, ?, ?, ?, 'ACTIVE', ?, ?, ?, ?, ?, ?)",
                    [$formSchemaKey, $companyKey, $companyKeyHash, $recordType, $nextVersion, $schemaJson, $checksum, $formVersionKey, $adminKey, $adminKey],
                    'Inventory form schema create'
                );
            }

            $auditAction = $hasCurrent ? 'UPDATE' : 'CREATE';
            yovel_admin_inventory_execute(
                $db,
                "INSERT INTO project_company_inventory_form_schema_audit (
                    form_schema_audit_key, company_key, company_key_hash, form_schema_key,
                    form_version_key, record_type, audit_action, previous_checksum,
                    next_checksum, created_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$auditKey, $companyKey, $companyKeyHash, $formSchemaKey, $formVersionKey, $recordType, $auditAction, $hasCurrent ? (string) $current['schema_checksum'] : null, $checksum, $adminKey],
                'Inventory form schema audit'
            );
            bx_audit($auditAction, 'project_company_inventory_form_schema', $formSchemaKey, [
                'company_key' => $companyKey,
                'company_name' => (string) ($company['company_name'] ?? ''),
                'record_type' => $recordType,
                'schema_version' => $nextVersion,
                'schema_checksum' => $checksum,
                'admin_key' => $adminKey,
            ], 'Company administrator saved an Inventory form schema version.');

            $savedSchema = $db->GetRow(
                'SELECT * FROM project_company_inventory_form_schema WHERE company_key_hash = ? AND form_schema_key = ? LIMIT 1',
                [$companyKeyHash, $formSchemaKey]
            );
            $savedVersion = $db->GetRow(
                'SELECT * FROM project_company_inventory_form_schema_version WHERE company_key_hash = ? AND form_version_key = ? LIMIT 1',
                [$companyKeyHash, $formVersionKey]
            );
            $savedAudit = $db->GetRow(
                'SELECT * FROM project_company_inventory_form_schema_audit WHERE company_key_hash = ? AND form_schema_audit_key = ? LIMIT 1',
                [$companyKeyHash, $auditKey]
            );
            $expectedSchema = [
                'form_schema_key' => $formSchemaKey,
                'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash,
                'record_type' => $recordType,
                'schema_status' => 'ACTIVE',
                'schema_version' => (string) $nextVersion,
                'schema_json' => $schemaJson,
                'schema_checksum' => $checksum,
                'current_form_version_key' => $formVersionKey,
                'updated_by_admin_key' => $adminKey,
            ];
            if (!$hasCurrent) {
                $expectedSchema['created_by_admin_key'] = $adminKey;
            }
            yovel_admin_inventory_assert_readback(
                $expectedSchema,
                is_array($savedSchema) ? $savedSchema : [],
                array_keys($expectedSchema),
                'Inventory form schema'
            );
            $expectedVersion = [
                'form_version_key' => $formVersionKey,
                'form_schema_key' => $formSchemaKey,
                'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash,
                'record_type' => $recordType,
                'version_number' => (string) $nextVersion,
                'schema_json' => $schemaJson,
                'schema_checksum' => $checksum,
                'created_by_admin_key' => $adminKey,
            ];
            yovel_admin_inventory_assert_readback(
                $expectedVersion,
                is_array($savedVersion) ? $savedVersion : [],
                array_keys($expectedVersion),
                'Inventory form version'
            );
            $expectedAudit = [
                'form_schema_audit_key' => $auditKey,
                'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash,
                'form_schema_key' => $formSchemaKey,
                'form_version_key' => $formVersionKey,
                'record_type' => $recordType,
                'audit_action' => $auditAction,
                'previous_checksum' => $hasCurrent ? (string) $current['schema_checksum'] : null,
                'next_checksum' => $checksum,
                'created_by_admin_key' => $adminKey,
            ];
            yovel_admin_inventory_assert_readback(
                $expectedAudit,
                is_array($savedAudit) ? $savedAudit : [],
                array_keys($expectedAudit),
                'Inventory form audit'
            );

            return $savedSchema;
    });
    return is_array($saved) ? $saved : [];
}

function yovel_admin_inventory_schema_from_post(string $recordType, array $input): array
{
    $recordType = yovel_admin_inventory_record_type($recordType);
    $schemaJson = (string) ($input['schema_json'] ?? '');
    $schema = $schemaJson !== '' ? json_decode($schemaJson, true, 512, JSON_THROW_ON_ERROR) : null;
    if (!is_array($schema)) {
        $schema = yovel_admin_inventory_default_form_schemas()[$recordType];
    }
    $newFieldLabel = trim((string) ($input['new_field_label'] ?? ''));
    if ($newFieldLabel !== '') {
        $schema['fields'][] = [
            'key' => yovel_admin_inventory_field_key((string) ($input['new_field_key'] ?? '')),
            'label' => $newFieldLabel,
            'type' => (string) ($input['new_field_type'] ?? 'SHORT_TEXT'),
            'section' => (string) ($input['new_field_section'] ?? 'custom'),
            'required' => !empty($input['new_field_required']),
            'visible' => true,
            'system' => false,
            'column' => (int) ($input['new_field_column'] ?? 1),
            'order' => count($schema['fields']) * 10 + 10,
            'precision' => (int) ($input['new_field_precision'] ?? 0),
            'options' => [],
        ];
    }
    return $schema;
}
