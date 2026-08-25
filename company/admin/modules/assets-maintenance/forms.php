<?php
declare(strict_types=1);

function yovel_admin_assets_form_record_types(): array
{
    return [
        'asset-records' => 'ASSET',
        'asset-depreciation-schedule' => 'ASSET_DEPRECIATION_SCHEDULE',
        'maintenance-schedules' => 'MAINTENANCE_SCHEDULE',
        'quality-inspection' => 'QUALITY_INSPECTION_LINK',
    ];
}

function yovel_admin_assets_record_type(string $value): string
{
    $recordType = strtoupper(trim((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper($value)), '_'));
    if (!in_array($recordType, array_values(yovel_admin_assets_form_record_types()), true)) {
        throw new InvalidArgumentException('Assets Form Builder record type is not registered.');
    }
    return $recordType;
}

function yovel_admin_assets_target_section(string $value): string
{
    $section = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    if (!array_key_exists($section, yovel_admin_assets_form_record_types())) {
        throw new InvalidArgumentException('Assets Form Builder target is not registered.');
    }
    return $section;
}

function yovel_admin_assets_field(
    string $key,
    string $label,
    string $type,
    string $section,
    bool $required,
    int $row,
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
        'row' => $row,
        'column' => $column,
        'width' => $column === 1 ? 'FULL' : 'HALF',
        'order' => $order,
        'default' => '',
        'options' => [],
        'validation' => [],
    ];
}

function yovel_admin_assets_default_form_schemas(): array
{
    $definitions = [
        'ASSET' => [
            ['asset_key', 'Asset key', 'SHORT_TEXT', 'identity', true, 1, 1, 10],
            ['asset_code', 'Asset code', 'SHORT_TEXT', 'identity', true, 1, 2, 20],
            ['company_key', 'Company key', 'SHORT_TEXT', 'identity', true, 2, 1, 30],
            ['item_owner_key', 'Inventory item key', 'LINK', 'ownership', true, 2, 2, 40],
            ['employee_owner_key', 'Custodian employee key', 'LINK', 'ownership', false, 3, 1, 50],
            ['lifecycle_status', 'Lifecycle status', 'DROPDOWN', 'control', true, 3, 2, 60],
            ['document_status', 'Document status', 'DROPDOWN', 'control', true, 4, 1, 70],
            ['finance_posting_key', 'Finance posting key', 'LINK', 'control', false, 4, 2, 80],
            ['form_version_key', 'Form version key', 'SHORT_TEXT', 'control', true, 5, 1, 90],
            ['created_by_admin_key', 'Created by administrator', 'SHORT_TEXT', 'control', true, 5, 2, 100],
        ],
        'ASSET_DEPRECIATION_SCHEDULE' => [
            ['schedule_key', 'Schedule key', 'SHORT_TEXT', 'identity', true, 1, 1, 10],
            ['asset_key', 'Asset key', 'LINK', 'identity', true, 1, 2, 20],
            ['company_key', 'Company key', 'SHORT_TEXT', 'identity', true, 2, 1, 30],
            ['finance_book_owner_key', 'Finance book key', 'LINK', 'posting', false, 2, 2, 40],
            ['posting_owner_key', 'Posting key', 'LINK', 'posting', false, 3, 1, 50],
            ['document_status', 'Document status', 'DROPDOWN', 'control', true, 3, 2, 60],
            ['form_version_key', 'Form version key', 'SHORT_TEXT', 'control', true, 4, 1, 70],
            ['created_by_admin_key', 'Created by administrator', 'SHORT_TEXT', 'control', true, 4, 2, 80],
        ],
        'MAINTENANCE_SCHEDULE' => [
            ['schedule_key', 'Schedule key', 'SHORT_TEXT', 'identity', true, 1, 1, 10],
            ['asset_key', 'Asset key', 'LINK', 'identity', true, 1, 2, 20],
            ['company_key', 'Company key', 'SHORT_TEXT', 'identity', true, 2, 1, 30],
            ['maintenance_team_owner_key', 'Maintenance team key', 'LINK', 'assignment', false, 2, 2, 40],
            ['document_status', 'Document status', 'DROPDOWN', 'control', true, 3, 1, 50],
            ['form_version_key', 'Form version key', 'SHORT_TEXT', 'control', true, 3, 2, 60],
            ['created_by_admin_key', 'Created by administrator', 'SHORT_TEXT', 'control', true, 4, 1, 70],
        ],
        'QUALITY_INSPECTION_LINK' => [
            ['inspection_link_key', 'Inspection link key', 'SHORT_TEXT', 'identity', true, 1, 1, 10],
            ['asset_key', 'Asset key', 'LINK', 'identity', true, 1, 2, 20],
            ['company_key', 'Company key', 'SHORT_TEXT', 'identity', true, 2, 1, 30],
            ['inspection_owner_key', 'Quality inspection key', 'LINK', 'inspection', true, 2, 2, 40],
            ['inspection_status', 'Inspection status', 'DROPDOWN', 'inspection', true, 3, 1, 50],
            ['form_version_key', 'Form version key', 'SHORT_TEXT', 'control', true, 3, 2, 60],
            ['created_by_admin_key', 'Created by administrator', 'SHORT_TEXT', 'control', true, 4, 1, 70],
        ],
    ];

    $schemas = [];
    foreach ($definitions as $recordType => $definition) {
        $fields = array_map(static fn (array $field): array => yovel_admin_assets_field(...$field), $definition);
        $sections = [];
        foreach ($fields as $field) {
            $sectionKey = (string) $field['section'];
            $sections[$sectionKey] = ['key' => $sectionKey, 'label' => ucwords(str_replace('_', ' ', $sectionKey)), 'order' => count($sections) * 10 + 10];
        }
        $schemas[$recordType] = [
            'version' => 1,
            'recordType' => $recordType,
            'requiredSystemFields' => array_values(array_map(
                static fn (array $field): string => (string) $field['key'],
                array_filter($fields, static fn (array $field): bool => (bool) $field['required'])
            )),
            'protectedSystemFields' => array_column($fields, 'key'),
            'sections' => array_values($sections),
            'fields' => $fields,
        ];
    }
    return $schemas;
}

function yovel_admin_assets_form_adapter(): array
{
    $protected = [];
    foreach (yovel_admin_assets_default_form_schemas() as $recordType => $schema) {
        $protected[$recordType] = array_values(array_map('strval', $schema['protectedSystemFields']));
    }
    return [
        'module' => 'assets-maintenance',
        'target_record_types' => yovel_admin_assets_form_record_types(),
        'protected_fields' => $protected,
        'field_types' => [
            'SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'CURRENCY', 'DATE', 'DATETIME',
            'DROPDOWN', 'CHECKBOXES', 'TOGGLE', 'LINK', 'TABLE', 'SECTION',
        ],
        'row_column_layout' => ['version' => 1, 'max_columns' => 3, 'stable_keys' => true],
        'normalize' => 'yovel_admin_assets_normalize_form_schema',
        'version_identity' => 'yovel_admin_assets_form_version_checksum',
        'renderer' => 'company/admin/modules/assets-maintenance/views/form-builder.php',
    ];
}

function yovel_admin_assets_field_key(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return 'custom_' . strtolower(substr(str_replace('-', '', bx_uuid()), 0, 24));
    }
    if (preg_match('/^[a-z][a-z0-9_]{1,79}$/', $value) !== 1) {
        throw new InvalidArgumentException('Assets fields require stable lowercase field keys.');
    }
    return $value;
}

function yovel_admin_assets_normalize_form_schema(string $recordType, array $schema, int $version): array
{
    $recordType = yovel_admin_assets_record_type($recordType);
    if ($version < 1) {
        throw new InvalidArgumentException('Assets Form Builder version must be positive.');
    }
    $inputFields = $schema['fields'] ?? [];
    if (!is_array($inputFields) || count($inputFields) > 120) {
        throw new InvalidArgumentException('An Assets form can contain up to 120 fields.');
    }

    $default = yovel_admin_assets_default_form_schemas()[$recordType];
    $protectedKeys = array_fill_keys($default['protectedSystemFields'], true);
    $allowedTypes = yovel_admin_assets_form_adapter()['field_types'];
    $usedKeys = $protectedKeys;
    $customFields = [];
    foreach ($inputFields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }
        $rawKey = trim((string) ($field['key'] ?? ''));
        if ($rawKey !== '' && isset($protectedKeys[$rawKey])) {
            continue;
        }
        $key = yovel_admin_assets_field_key($rawKey);
        if (isset($usedKeys[$key])) {
            throw new InvalidArgumentException('Assets form fields require unique stable keys.');
        }
        $usedKeys[$key] = true;
        $label = yovel_admin_assets_text($field['label'] ?? '', 'Assets field label', 180, true);
        $type = strtoupper(trim((string) ($field['type'] ?? 'SHORT_TEXT')));
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException('Assets field type is not supported.');
        }
        $section = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) ($field['section'] ?? 'custom'))), '_');
        $section = $section !== '' ? $section : 'custom';
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $options = array_values(array_filter(array_map(
            static fn ($option): string => substr(trim((string) $option), 0, 160),
            $options
        ), static fn (string $option): bool => $option !== ''));
        if (count($options) > 40) {
            throw new InvalidArgumentException('Assets option fields can contain up to 40 options.');
        }
        if (!in_array($type, ['DROPDOWN', 'CHECKBOXES'], true)) {
            $options = [];
        }
        $width = strtoupper(trim((string) ($field['width'] ?? 'FULL')));
        if (!in_array($width, ['THIRD', 'HALF', 'FULL'], true)) {
            $width = 'FULL';
        }
        $defaultValue = $field['default'] ?? '';
        if (!is_scalar($defaultValue) && $defaultValue !== null) {
            throw new InvalidArgumentException('Assets field defaults must be scalar values.');
        }
        $defaultValue = substr((string) $defaultValue, 0, 1000);
        $validationInput = is_array($field['validation'] ?? null) ? $field['validation'] : [];
        $validation = [];
        foreach (['min', 'max', 'max_length', 'pattern'] as $validationKey) {
            if (!array_key_exists($validationKey, $validationInput)) {
                continue;
            }
            $value = $validationInput[$validationKey];
            if ($validationKey === 'pattern') {
                $validation[$validationKey] = substr(trim((string) $value), 0, 240);
            } elseif (is_numeric($value)) {
                $validation[$validationKey] = $value + 0;
            }
        }
        $customFields[] = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'section' => $section,
            'required' => !empty($field['required']) && $type !== 'SECTION',
            'visible' => !array_key_exists('visible', $field) || !empty($field['visible']),
            'system' => false,
            'row' => max(1, min(1000, (int) ($field['row'] ?? ($index + 1)))),
            'column' => max(1, min(3, (int) ($field['column'] ?? 1))),
            'width' => $width,
            'order' => max(1, (int) ($field['order'] ?? (($index + 1) * 10))),
            'default' => $defaultValue,
            'options' => $options,
            'validation' => $validation,
        ];
    }
    usort($customFields, static fn (array $left, array $right): int => [$left['order'], $left['key']] <=> [$right['order'], $right['key']]);

    $fields = array_merge($default['fields'], $customFields);
    $sections = [];
    foreach ($fields as $field) {
        $sectionKey = (string) $field['section'];
        if (!isset($sections[$sectionKey])) {
            $sections[$sectionKey] = [
                'key' => $sectionKey,
                'label' => ucwords(str_replace('_', ' ', $sectionKey)),
                'order' => count($sections) * 10 + 10,
            ];
        }
    }

    return [
        'version' => $version,
        'recordType' => $recordType,
        'requiredSystemFields' => array_values($default['requiredSystemFields']),
        'protectedSystemFields' => array_values($default['protectedSystemFields']),
        'sections' => array_values($sections),
        'fields' => $fields,
    ];
}

function yovel_admin_assets_form_json(array $schema): string
{
    return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function yovel_admin_assets_form_version_checksum(
    string $recordType,
    string $title,
    string $description,
    string $schemaJson
): string {
    return hash('sha256', implode("\n", [$recordType, $title, $description, $schemaJson]));
}

function yovel_admin_assets_schema_from_input(string $recordType, array $input): array
{
    $recordType = yovel_admin_assets_record_type($recordType);
    $schemaJson = trim((string) ($input['schema_json'] ?? ''));
    $schema = $schemaJson !== '' ? json_decode($schemaJson, true, 512, JSON_THROW_ON_ERROR) : [];
    if (!is_array($schema) || !is_array($schema['fields'] ?? null)) {
        $schema = yovel_admin_assets_default_form_schemas()[$recordType];
    }
    $newLabel = trim((string) ($input['new_field_label'] ?? ''));
    if ($newLabel !== '') {
        $options = preg_split('/\r\n|\r|\n|,/', (string) ($input['new_field_options'] ?? '')) ?: [];
        $validation = [];
        if (trim((string) ($input['new_field_max_length'] ?? '')) !== '') {
            $validation['max_length'] = (int) $input['new_field_max_length'];
        }
        $schema['fields'][] = [
            'key' => yovel_admin_assets_field_key((string) ($input['new_field_key'] ?? '')),
            'label' => $newLabel,
            'type' => (string) ($input['new_field_type'] ?? 'SHORT_TEXT'),
            'section' => (string) ($input['new_field_section'] ?? 'custom'),
            'required' => !empty($input['new_field_required']),
            'visible' => !array_key_exists('new_field_visible', $input) || !empty($input['new_field_visible']),
            'system' => false,
            'row' => max(1, (int) ($input['new_field_row'] ?? count($schema['fields']) + 1)),
            'column' => max(1, min(3, (int) ($input['new_field_column'] ?? 1))),
            'width' => (string) ($input['new_field_width'] ?? 'FULL'),
            'order' => count($schema['fields']) * 10 + 10,
            'default' => (string) ($input['new_field_default'] ?? ''),
            'options' => $options,
            'validation' => $validation,
        ];
    }
    return $schema;
}

function yovel_admin_assets_read_company_scope(array $company): array
{
    $companyKey = yovel_admin_assets_opaque_key($company['company_key'] ?? '', 'Company key');
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('Assets company scope is invalid.');
    }
    return [$companyKey, $companyKeyHash];
}

function yovel_admin_assets_forms(array $company, bool $includeArchived = true): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    yovel_admin_assets_maintenance_schema();
    $sql = "SELECT form_key, company_key, company_key_hash, target_section, record_type,
                   form_title, form_description, form_status, current_version_key,
                   published_version_key, created_by_admin_key, updated_by_admin_key,
                   created_at, updated_at
            FROM project_company_asset_form
            WHERE company_key = ? AND company_key_hash = ?";
    $parameters = [$companyKey, $companyKeyHash];
    if (!$includeArchived) {
        $sql .= " AND form_status <> 'ARCHIVED'";
    }
    $sql .= ' ORDER BY updated_at DESC, x_id DESC';
    $rows = bx_db()->GetAll($sql, $parameters);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_form_version(array $company, string $formVersionKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($formVersionKey)) {
        return null;
    }
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_asset_form_version WHERE company_key = ? AND company_key_hash = ? AND form_version_key = ? LIMIT 1',
        [$companyKey, $companyKeyHash, $formVersionKey]
    );
    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_assets_form(array $company, string $formKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($formKey)) {
        return null;
    }
    $header = bx_db()->GetRow(
        'SELECT * FROM project_company_asset_form WHERE company_key = ? AND company_key_hash = ? AND form_key = ? LIMIT 1',
        [$companyKey, $companyKeyHash, $formKey]
    );
    if (!is_array($header) || $header === []) {
        return null;
    }
    $current = bx_db()->GetRow(
        'SELECT * FROM project_company_asset_form_version WHERE company_key = ? AND company_key_hash = ? AND form_version_key = ? LIMIT 1',
        [$companyKey, $companyKeyHash, (string) $header['current_version_key']]
    );
    if (!is_array($current) || $current === []) {
        throw new RuntimeException('Assets form current version could not be rehydrated.');
    }
    $versions = bx_db()->GetAll(
        'SELECT form_version_key, version_number, version_status, version_checksum, created_by_admin_key, published_by_admin_key, published_at, archived_at, created_at FROM project_company_asset_form_version WHERE company_key = ? AND company_key_hash = ? AND form_key = ? ORDER BY version_number DESC',
        [$companyKey, $companyKeyHash, $formKey]
    );
    return $header + [
        'persisted' => true,
        'version_number' => (int) $current['version_number'],
        'version_status' => (string) $current['version_status'],
        'schema_json' => (string) $current['schema_json'],
        'schema_checksum' => (string) $current['schema_checksum'],
        'version_checksum' => (string) $current['version_checksum'],
        'schema' => json_decode((string) $current['schema_json'], true, 512, JSON_THROW_ON_ERROR),
        'versions' => is_array($versions) ? $versions : [],
    ];
}

function yovel_admin_assets_save_form(array $company, array $admin, array $input): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $formKey = trim((string) ($input['form_key'] ?? ''));
    if ($formKey !== '' && (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($formKey))) {
        throw new InvalidArgumentException('Assets form key is invalid.');
    }
    $targetSection = yovel_admin_assets_target_section((string) ($input['target_section'] ?? 'asset-records'));
    $recordType = yovel_admin_assets_record_type((string) ($input['record_type'] ?? yovel_admin_assets_form_record_types()[$targetSection]));
    if (yovel_admin_assets_form_record_types()[$targetSection] !== $recordType) {
        throw new InvalidArgumentException('Assets form target and record type do not match.');
    }
    $title = yovel_admin_assets_text($input['form_title'] ?? '', 'Form title', 180, true);
    $description = yovel_admin_assets_text($input['form_description'] ?? '', 'Form description', 2000);
    $schemaInput = yovel_admin_assets_schema_from_input($recordType, $input);

    $saved = yovel_admin_assets_in_transaction(static function (ADOConnection $db) use (
        $company,
        $scope,
        $companyKey,
        $companyKeyHash,
        $adminKey,
        $formKey,
        $targetSection,
        $recordType,
        $title,
        $description,
        $schemaInput
    ): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $currentForm = $formKey !== '' ? $db->GetRow(
            'SELECT * FROM project_company_asset_form WHERE company_key_hash = ? AND form_key = ? FOR UPDATE',
            [$companyKeyHash, $formKey]
        ) : [];
        $hasCurrent = is_array($currentForm) && $currentForm !== [];
        if ($formKey !== '' && !$hasCurrent) {
            throw new InvalidArgumentException('Assets form was not found for this company.');
        }
        if ($hasCurrent) {
            if ((string) $currentForm['form_status'] === 'ARCHIVED') {
                throw new InvalidArgumentException('Archived Assets forms cannot be edited.');
            }
            if ((string) $currentForm['target_section'] !== $targetSection || (string) $currentForm['record_type'] !== $recordType) {
                throw new InvalidArgumentException('Assets form target and record type cannot change after creation.');
            }
        }

        $stableFormKey = $hasCurrent ? (string) $currentForm['form_key'] : bx_uuid();
        $currentVersion = $hasCurrent ? $db->GetRow(
            'SELECT * FROM project_company_asset_form_version WHERE company_key_hash = ? AND form_version_key = ? FOR UPDATE',
            [$companyKeyHash, (string) $currentForm['current_version_key']]
        ) : [];
        $currentVersionNumber = is_array($currentVersion) && $currentVersion !== [] ? (int) $currentVersion['version_number'] : 0;
        $candidateVersion = max(1, $currentVersionNumber);
        $candidateSchema = yovel_admin_assets_normalize_form_schema($recordType, $schemaInput, $candidateVersion);
        $candidateJson = yovel_admin_assets_form_json($candidateSchema);
        $candidateChecksum = yovel_admin_assets_form_version_checksum($recordType, $title, $description, $candidateJson);
        if ($hasCurrent
            && is_array($currentVersion)
            && $currentVersion !== []
            && hash_equals((string) $currentVersion['version_checksum'], $candidateChecksum)) {
            $unchanged = yovel_admin_assets_form($company, $stableFormKey);
            if (!is_array($unchanged)) {
                throw new RuntimeException('Unchanged Assets form could not be read back.');
            }
            return $unchanged;
        }

        $nextVersion = $currentVersionNumber + 1;
        $normalized = yovel_admin_assets_normalize_form_schema($recordType, $schemaInput, $nextVersion);
        $schemaJson = yovel_admin_assets_form_json($normalized);
        $schemaChecksum = hash('sha256', $schemaJson);
        $versionChecksum = yovel_admin_assets_form_version_checksum($recordType, $title, $description, $schemaJson);
        $versionKey = bx_uuid();
        yovel_admin_assets_execute(
            $db,
            "INSERT INTO project_company_asset_form_version (
                form_version_key, form_key, company_key, company_key_hash, target_section,
                record_type, form_title, form_description, version_number, version_status,
                schema_json, schema_checksum, version_checksum, created_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, ?, ?, ?)",
            [$versionKey, $stableFormKey, $companyKey, $companyKeyHash, $targetSection, $recordType, $title, $description, $nextVersion, $schemaJson, $schemaChecksum, $versionChecksum, $adminKey],
            'Assets form version save'
        );
        yovel_admin_assets_fault('after_version_write');

        if ($hasCurrent) {
            yovel_admin_assets_execute(
                $db,
                "UPDATE project_company_asset_form
                 SET form_title = ?, form_description = ?, form_status = 'DRAFT',
                     current_version_key = ?, updated_by_admin_key = ?
                 WHERE company_key_hash = ? AND form_key = ?",
                [$title, $description, $versionKey, $adminKey, $companyKeyHash, $stableFormKey],
                'Assets form update'
            );
        } else {
            yovel_admin_assets_execute(
                $db,
                "INSERT INTO project_company_asset_form (
                    form_key, company_key, company_key_hash, target_section, record_type,
                    form_title, form_description, form_status, current_version_key,
                    created_by_admin_key, updated_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, 'DRAFT', ?, ?, ?)",
                [$stableFormKey, $companyKey, $companyKeyHash, $targetSection, $recordType, $title, $description, $versionKey, $adminKey, $adminKey],
                'Assets form create'
            );
        }

        $auditAction = $hasCurrent ? 'UPDATE' : 'CREATE';
        yovel_admin_assets_write_audit($db, $scope, $auditAction, $stableFormKey, $versionKey, '', [
            'target_section' => $targetSection,
            'record_type' => $recordType,
            'version_number' => $nextVersion,
            'version_checksum' => $versionChecksum,
        ]);
        $savedHeader = $db->GetRow(
            'SELECT * FROM project_company_asset_form WHERE company_key_hash = ? AND form_key = ? LIMIT 1',
            [$companyKeyHash, $stableFormKey]
        );
        $savedVersion = $db->GetRow(
            'SELECT * FROM project_company_asset_form_version WHERE company_key_hash = ? AND form_version_key = ? LIMIT 1',
            [$companyKeyHash, $versionKey]
        );
        yovel_admin_assets_assert_readback([
            'form_key' => $stableFormKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'target_section' => $targetSection,
            'record_type' => $recordType,
            'form_title' => $title,
            'form_description' => $description,
            'form_status' => 'DRAFT',
            'current_version_key' => $versionKey,
            'updated_by_admin_key' => $adminKey,
        ], is_array($savedHeader) ? $savedHeader : [], [
            'form_key', 'company_key', 'company_key_hash', 'target_section', 'record_type',
            'form_title', 'form_description', 'form_status', 'current_version_key', 'updated_by_admin_key',
        ], 'Assets form');
        yovel_admin_assets_assert_readback([
            'form_version_key' => $versionKey,
            'form_key' => $stableFormKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'target_section' => $targetSection,
            'record_type' => $recordType,
            'form_title' => $title,
            'form_description' => $description,
            'version_number' => (string) $nextVersion,
            'version_status' => 'DRAFT',
            'schema_json' => $schemaJson,
            'schema_checksum' => $schemaChecksum,
            'version_checksum' => $versionChecksum,
            'created_by_admin_key' => $adminKey,
        ], is_array($savedVersion) ? $savedVersion : [], [
            'form_version_key', 'form_key', 'company_key', 'company_key_hash', 'target_section',
            'record_type', 'form_title', 'form_description', 'version_number', 'version_status',
            'schema_json', 'schema_checksum', 'version_checksum', 'created_by_admin_key',
        ], 'Assets form version');
        $rehydrated = yovel_admin_assets_form($company, $stableFormKey);
        if (!is_array($rehydrated)) {
            throw new RuntimeException('Saved Assets form could not be rehydrated.');
        }
        return $rehydrated;
    });

    return is_array($saved) ? $saved : [];
}

function yovel_admin_assets_publish_form(array $company, array $admin, string $formKey): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($formKey)) {
        throw new InvalidArgumentException('Assets form key is invalid.');
    }
    $saved = yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $formKey): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $form = $db->GetRow('SELECT * FROM project_company_asset_form WHERE company_key_hash = ? AND form_key = ? FOR UPDATE', [$companyKeyHash, $formKey]);
        if (!is_array($form) || $form === []) {
            throw new InvalidArgumentException('Assets form was not found for this company.');
        }
        if ((string) $form['form_status'] === 'ARCHIVED') {
            throw new InvalidArgumentException('Archived Assets forms cannot be published.');
        }
        $versionKey = (string) $form['current_version_key'];
        $version = $db->GetRow('SELECT * FROM project_company_asset_form_version WHERE company_key_hash = ? AND form_version_key = ? FOR UPDATE', [$companyKeyHash, $versionKey]);
        if (!is_array($version) || $version === []) {
            throw new RuntimeException('Assets form current version is missing.');
        }
        if ((string) $form['form_status'] === 'PUBLISHED'
            && (string) $form['published_version_key'] === $versionKey
            && (string) $version['version_status'] === 'PUBLISHED') {
            $unchanged = yovel_admin_assets_form($company, $formKey);
            if (!is_array($unchanged)) {
                throw new RuntimeException('Published Assets form could not be rehydrated.');
            }
            return $unchanged;
        }
        yovel_admin_assets_execute(
            $db,
            "UPDATE project_company_asset_form_version
             SET version_status = 'PUBLISHED', published_by_admin_key = ?, published_at = CURRENT_TIMESTAMP
             WHERE company_key_hash = ? AND form_version_key = ?",
            [$adminKey, $companyKeyHash, $versionKey],
            'Assets form version publish'
        );
        yovel_admin_assets_execute(
            $db,
            "UPDATE project_company_asset_form
             SET form_status = 'PUBLISHED', published_version_key = ?, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND form_key = ?",
            [$versionKey, $adminKey, $companyKeyHash, $formKey],
            'Assets form publish'
        );
        yovel_admin_assets_write_audit($db, $scope, 'PUBLISH', $formKey, $versionKey, '', [
            'version_number' => (int) $version['version_number'],
            'version_checksum' => (string) $version['version_checksum'],
        ]);
        $readback = $db->GetRow('SELECT form_status, published_version_key, updated_by_admin_key FROM project_company_asset_form WHERE company_key_hash = ? AND form_key = ? LIMIT 1', [$companyKeyHash, $formKey]);
        yovel_admin_assets_assert_readback([
            'form_status' => 'PUBLISHED', 'published_version_key' => $versionKey, 'updated_by_admin_key' => $adminKey,
        ], is_array($readback) ? $readback : [], ['form_status', 'published_version_key', 'updated_by_admin_key'], 'Published Assets form');
        $rehydrated = yovel_admin_assets_form($company, $formKey);
        if (!is_array($rehydrated)) {
            throw new RuntimeException('Published Assets form could not be rehydrated.');
        }
        return $rehydrated;
    });
    return is_array($saved) ? $saved : [];
}

function yovel_admin_assets_archive_form(array $company, array $admin, string $formKey, string $reason): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $reason = yovel_admin_assets_text($reason, 'Archive reason', 500, true);
    yovel_admin_assets_maintenance_schema();
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($formKey)) {
        throw new InvalidArgumentException('Assets form key is invalid.');
    }
    $saved = yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $formKey, $reason): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $form = $db->GetRow('SELECT * FROM project_company_asset_form WHERE company_key_hash = ? AND form_key = ? FOR UPDATE', [$companyKeyHash, $formKey]);
        if (!is_array($form) || $form === []) {
            throw new InvalidArgumentException('Assets form was not found for this company.');
        }
        if ((string) $form['form_status'] !== 'ARCHIVED') {
            yovel_admin_assets_execute(
                $db,
                "UPDATE project_company_asset_form SET form_status = 'ARCHIVED', updated_by_admin_key = ? WHERE company_key_hash = ? AND form_key = ?",
                [$adminKey, $companyKeyHash, $formKey],
                'Assets form archive'
            );
            if ((string) ($form['published_version_key'] ?? '') !== (string) $form['current_version_key']) {
                yovel_admin_assets_execute(
                    $db,
                    "UPDATE project_company_asset_form_version SET version_status = 'ARCHIVED', archived_at = CURRENT_TIMESTAMP WHERE company_key_hash = ? AND form_version_key = ? AND version_status = 'DRAFT'",
                    [$companyKeyHash, (string) $form['current_version_key']],
                    'Assets draft version archive'
                );
            }
            yovel_admin_assets_write_audit($db, $scope, 'ARCHIVE', $formKey, (string) $form['current_version_key'], '', ['reason' => $reason]);
        }
        $readback = $db->GetRow('SELECT form_status, current_version_key FROM project_company_asset_form WHERE company_key_hash = ? AND form_key = ? LIMIT 1', [$companyKeyHash, $formKey]);
        yovel_admin_assets_assert_readback(['form_status' => 'ARCHIVED', 'current_version_key' => (string) $form['current_version_key']], is_array($readback) ? $readback : [], ['form_status', 'current_version_key'], 'Archived Assets form');
        $rehydrated = yovel_admin_assets_form($company, $formKey);
        if (!is_array($rehydrated)) {
            throw new RuntimeException('Archived Assets form could not be rehydrated.');
        }
        return $rehydrated;
    });
    return is_array($saved) ? $saved : [];
}

function yovel_admin_assets_normalize_submission_values(array $schema, array $values): array
{
    $normalized = [];
    foreach ($schema['fields'] ?? [] as $field) {
        if (!is_array($field) || !empty($field['system']) || empty($field['visible']) || ($field['type'] ?? '') === 'SECTION') {
            continue;
        }
        $key = (string) ($field['key'] ?? '');
        $raw = $values[$key] ?? null;
        $missing = $raw === null || $raw === '' || $raw === [];
        if (!empty($field['required']) && $missing) {
            throw new InvalidArgumentException((string) ($field['label'] ?? $key) . ' is required.');
        }
        if ($missing) {
            continue;
        }
        if (is_array($raw)) {
            $value = array_values(array_map(static fn ($item): string => substr(trim((string) $item), 0, 1000), $raw));
        } elseif (is_scalar($raw)) {
            $value = substr(trim((string) $raw), 0, 20000);
        } else {
            throw new InvalidArgumentException('Assets form values must be scalar or list values.');
        }
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        if ($options !== []) {
            foreach ((array) $value as $selected) {
                if (!in_array($selected, $options, true)) {
                    throw new InvalidArgumentException((string) ($field['label'] ?? $key) . ' contains an unsupported option.');
                }
            }
        }
        $normalized[$key] = $value;
    }
    ksort($normalized);
    return $normalized;
}

function yovel_admin_assets_save_form_submission(array $company, array $admin, array $input): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $formKey = trim((string) ($input['form_key'] ?? ''));
    $submissionKey = trim((string) ($input['submission_key'] ?? ''));
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($formKey) || ($submissionKey !== '' && !yovel_admin_is_uuid($submissionKey))) {
        throw new InvalidArgumentException('Assets form or submission key is invalid.');
    }
    $subjectOwnerKey = yovel_admin_assets_opaque_key($input['subject_owner_key'] ?? '', 'Subject owner key', false);
    $values = json_decode((string) ($input['values_json'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($values)) {
        throw new InvalidArgumentException('Assets form values are invalid.');
    }

    $saved = yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($scope, $companyKey, $companyKeyHash, $adminKey, $formKey, $submissionKey, $subjectOwnerKey, $values): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $form = $db->GetRow('SELECT * FROM project_company_asset_form WHERE company_key_hash = ? AND form_key = ? FOR UPDATE', [$companyKeyHash, $formKey]);
        if (!is_array($form) || $form === [] || (string) $form['form_status'] !== 'PUBLISHED' || trim((string) $form['published_version_key']) === '') {
            throw new InvalidArgumentException('Only a published Assets form can receive submissions.');
        }
        $existing = $submissionKey !== '' ? $db->GetRow(
            'SELECT * FROM project_company_asset_form_submission WHERE company_key_hash = ? AND submission_key = ? FOR UPDATE',
            [$companyKeyHash, $submissionKey]
        ) : [];
        $hasExisting = is_array($existing) && $existing !== [];
        if ($submissionKey !== '' && !$hasExisting) {
            throw new InvalidArgumentException('Assets form submission was not found for this company.');
        }
        if ($hasExisting && (string) $existing['form_key'] !== $formKey) {
            throw new InvalidArgumentException('Assets form submission does not belong to this form.');
        }
        $versionKey = $hasExisting ? (string) $existing['form_version_key'] : (string) $form['published_version_key'];
        $version = $db->GetRow(
            'SELECT * FROM project_company_asset_form_version WHERE company_key_hash = ? AND form_key = ? AND form_version_key = ? LIMIT 1',
            [$companyKeyHash, $formKey, $versionKey]
        );
        if (!is_array($version) || $version === []) {
            throw new RuntimeException('Assets submission form version is unavailable.');
        }
        $schema = json_decode((string) $version['schema_json'], true, 512, JSON_THROW_ON_ERROR);
        $normalizedValues = yovel_admin_assets_normalize_submission_values($schema, $values);
        $valuesJson = json_encode($normalizedValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $valuesChecksum = hash('sha256', $valuesJson);
        $stableSubmissionKey = $hasExisting ? (string) $existing['submission_key'] : bx_uuid();
        if ($hasExisting) {
            yovel_admin_assets_execute(
                $db,
                "UPDATE project_company_asset_form_submission
                 SET subject_owner_key = ?, submission_status = 'SUBMITTED', values_json = ?,
                     values_checksum = ?, updated_by_admin_key = ?
                 WHERE company_key_hash = ? AND submission_key = ?",
                [$subjectOwnerKey !== '' ? $subjectOwnerKey : null, $valuesJson, $valuesChecksum, $adminKey, $companyKeyHash, $stableSubmissionKey],
                'Assets form submission update'
            );
        } else {
            yovel_admin_assets_execute(
                $db,
                "INSERT INTO project_company_asset_form_submission (
                    submission_key, company_key, company_key_hash, form_key, form_version_key,
                    target_section, record_type, subject_owner_key, submission_status,
                    values_json, values_checksum, created_by_admin_key, updated_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'SUBMITTED', ?, ?, ?, ?)",
                [$stableSubmissionKey, $companyKey, $companyKeyHash, $formKey, $versionKey, (string) $form['target_section'], (string) $form['record_type'], $subjectOwnerKey !== '' ? $subjectOwnerKey : null, $valuesJson, $valuesChecksum, $adminKey, $adminKey],
                'Assets form submission create'
            );
        }
        $auditAction = $hasExisting ? 'SUBMISSION_UPDATE' : 'SUBMIT';
        yovel_admin_assets_write_audit($db, $scope, $auditAction, $formKey, $versionKey, $stableSubmissionKey, [
            'subject_owner_key' => $subjectOwnerKey,
            'values_checksum' => $valuesChecksum,
        ]);
        $readback = $db->GetRow(
            'SELECT * FROM project_company_asset_form_submission WHERE company_key_hash = ? AND submission_key = ? LIMIT 1',
            [$companyKeyHash, $stableSubmissionKey]
        );
        $expected = [
            'submission_key' => $stableSubmissionKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'form_key' => $formKey,
            'form_version_key' => $versionKey,
            'target_section' => (string) $form['target_section'],
            'record_type' => (string) $form['record_type'],
            'subject_owner_key' => $subjectOwnerKey,
            'submission_status' => 'SUBMITTED',
            'values_json' => $valuesJson,
            'values_checksum' => $valuesChecksum,
            'updated_by_admin_key' => $adminKey,
        ];
        yovel_admin_assets_assert_readback($expected, is_array($readback) ? array_map(static fn ($value) => $value ?? '', $readback) : [], array_keys($expected), 'Assets form submission');
        return is_array($readback) ? $readback : [];
    });
    return is_array($saved) ? $saved : [];
}
