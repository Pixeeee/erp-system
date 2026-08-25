<?php
declare(strict_types=1);

function yovel_admin_manufacturing_form_targets(): array
{
    return [
        'boms' => 'BOM',
        'operations' => 'OPERATION',
        'workstations' => 'WORKSTATION',
        'production-plans' => 'PRODUCTION_PLAN',
        'material-requirements' => 'MATERIAL_REQUIREMENT',
        'forecasts' => 'FORECAST',
        'work-orders' => 'WORK_ORDER',
        'job-cards' => 'JOB_CARD',
        'quality' => 'QUALITY',
        'subcontracting' => 'SUBCONTRACTING',
        'reports' => 'REPORT_FILTER',
        'settings' => 'MANUFACTURING_SETTINGS',
    ];
}

function yovel_admin_manufacturing_record_type(string $value): string
{
    $value = strtoupper(trim((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper($value)), '_'));
    if (!in_array($value, array_values(yovel_admin_manufacturing_form_targets()), true)) {
        throw new InvalidArgumentException('Manufacturing Form Builder record type is not registered.');
    }
    return $value;
}

function yovel_admin_manufacturing_system_field(string $key, string $label, string $type, bool $visible): array
{
    return [
        'key' => $key, 'label' => $label, 'type' => $type, 'section' => 'system',
        'required' => true, 'visible' => $visible, 'width' => 'half', 'default' => '',
        'validation' => [], 'options' => [], 'system' => true,
    ];
}

function yovel_admin_manufacturing_default_form_schemas(): array
{
    $schemas = [];
    foreach (yovel_admin_manufacturing_form_targets() as $recordType) {
        $fields = [
            yovel_admin_manufacturing_system_field('company_key', 'Company key', 'SHORT_TEXT', false),
            yovel_admin_manufacturing_system_field('record_key', 'Record key', 'SHORT_TEXT', false),
            yovel_admin_manufacturing_system_field('record_status', 'Status', 'DROPDOWN', true),
            yovel_admin_manufacturing_system_field('form_version_key', 'Form version', 'SHORT_TEXT', false),
            yovel_admin_manufacturing_system_field('created_by_admin_key', 'Created by', 'SHORT_TEXT', false),
        ];
        $fields[2]['options'] = ['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED'];
        $schemas[$recordType] = [
            'version' => 1,
            'recordType' => $recordType,
            'title' => ucwords(strtolower(str_replace('_', ' ', $recordType))),
            'description' => '',
            'requiredSystemFields' => array_column($fields, 'key'),
            'sections' => [['key' => 'system', 'label' => 'System']],
            'fields' => $fields,
            'rows' => [],
        ];
    }
    return $schemas;
}

function yovel_admin_manufacturing_field_key(string $value): string
{
    $value = strtolower(trim($value));
    if (preg_match('/^[a-z][a-z0-9_]{1,79}$/', $value) === 1) {
        return $value;
    }
    return 'custom_' . strtolower(substr(str_replace('-', '', bx_uuid()), 0, 24));
}

function yovel_admin_manufacturing_normalize_form_schema(string $recordType, array $schema, int $version): array
{
    $recordType = yovel_admin_manufacturing_record_type($recordType);
    if ($version < 1) {
        throw new InvalidArgumentException('Manufacturing Form Builder version must be positive.');
    }
    $inputFields = $schema['fields'] ?? [];
    if (!is_array($inputFields) || count($inputFields) > 120) {
        throw new InvalidArgumentException('A Manufacturing form can contain up to 120 fields.');
    }
    $defaults = yovel_admin_manufacturing_default_form_schemas()[$recordType];
    $protected = array_fill_keys($defaults['requiredSystemFields'], true);
    $allowedTypes = yovel_admin_manufacturing_form_adapter()['field_types'];
    $allowedWidths = ['full', 'half', 'third'];
    $customFields = [];
    $used = $protected;
    foreach ($inputFields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }
        $rawKey = trim((string) ($field['key'] ?? ''));
        if ($rawKey !== '' && isset($protected[$rawKey])) {
            continue;
        }
        $key = yovel_admin_manufacturing_field_key($rawKey);
        if (isset($used[$key])) {
            throw new InvalidArgumentException('Manufacturing form fields require unique stable keys.');
        }
        $used[$key] = true;
        $label = trim((string) ($field['label'] ?? ''));
        if ($label === '' || strlen($label) > 180) {
            throw new InvalidArgumentException('Manufacturing field labels must contain 1 to 180 characters.');
        }
        $type = strtoupper(trim((string) ($field['type'] ?? 'SHORT_TEXT')));
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException('Manufacturing field type is not supported.');
        }
        $section = strtolower(trim((string) preg_replace('/[^a-z0-9]+/', '_', (string) ($field['section'] ?? 'details')), '_')) ?: 'details';
        $width = strtolower(trim((string) ($field['width'] ?? 'half')));
        if (!in_array($width, $allowedWidths, true)) {
            $width = 'half';
        }
        $options = is_array($field['options'] ?? null) ? array_values(array_filter(array_map(
            static fn ($option): string => substr(trim((string) $option), 0, 160),
            $field['options']
        ), static fn (string $option): bool => $option !== '')) : [];
        if (count($options) > 40) {
            throw new InvalidArgumentException('Manufacturing fields can contain up to 40 options.');
        }
        if (!in_array($type, ['DROPDOWN', 'CHECKBOXES'], true)) {
            $options = [];
        }
        $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
        $validation = array_intersect_key($validation, array_flip(['min', 'max', 'max_length', 'pattern']));
        $customFields[] = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'section' => $section,
            'required' => !empty($field['required']) && $type !== 'SECTION',
            'visible' => !array_key_exists('visible', $field) || !empty($field['visible']),
            'width' => $width,
            'default' => substr((string) ($field['default'] ?? ''), 0, 1000),
            'validation' => $validation,
            'options' => $options,
            'system' => false,
            'order' => max(1, (int) ($field['order'] ?? (($index + 1) * 10))),
        ];
    }
    usort($customFields, static fn (array $left, array $right): int => [$left['order'], $left['key']] <=> [$right['order'], $right['key']]);

    $customByKey = [];
    foreach ($customFields as $field) {
        $customByKey[$field['key']] = true;
    }
    $rows = [];
    $placed = [];
    foreach (is_array($schema['rows'] ?? null) ? array_slice(array_values($schema['rows']), 0, 60) : [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $columns = [];
        foreach (array_slice(array_values(is_array($row['columns'] ?? null) ? $row['columns'] : []), 0, 3) as $column) {
            if (!is_array($column)) {
                continue;
            }
            $fieldKeys = [];
            foreach (is_array($column['field_keys'] ?? null) ? $column['field_keys'] : [] as $fieldKey) {
                $fieldKey = trim((string) $fieldKey);
                if ($fieldKey !== '' && isset($customByKey[$fieldKey]) && !isset($placed[$fieldKey])) {
                    $fieldKeys[] = $fieldKey;
                    $placed[$fieldKey] = true;
                }
            }
            $columns[] = ['key' => yovel_admin_manufacturing_field_key((string) ($column['key'] ?? '')), 'field_keys' => $fieldKeys];
        }
        if ($columns !== []) {
            $rows[] = ['key' => yovel_admin_manufacturing_field_key((string) ($row['key'] ?? '')), 'columns' => $columns];
        }
    }
    foreach ($customFields as $field) {
        if (isset($placed[$field['key']])) {
            continue;
        }
        $rows[] = [
            'key' => yovel_admin_manufacturing_field_key('row_' . $field['key']),
            'columns' => [['key' => yovel_admin_manufacturing_field_key('column_' . $field['key']), 'field_keys' => [$field['key']]]],
        ];
    }

    $fields = array_merge($defaults['fields'], $customFields);
    $sections = ['system' => ['key' => 'system', 'label' => 'System']];
    foreach ($fields as $field) {
        $section = (string) $field['section'];
        $sections[$section] = ['key' => $section, 'label' => ucwords(str_replace('_', ' ', $section))];
    }
    return [
        'version' => $version,
        'recordType' => $recordType,
        'title' => substr(trim((string) ($schema['title'] ?? $defaults['title'])), 0, 180),
        'description' => substr(trim((string) ($schema['description'] ?? '')), 0, 1000),
        'requiredSystemFields' => $defaults['requiredSystemFields'],
        'sections' => array_values($sections),
        'fields' => $fields,
        'rows' => $rows,
    ];
}

function yovel_admin_manufacturing_form_checksum(array $schema): string
{
    return hash('sha256', yovel_admin_manufacturing_json($schema));
}

function yovel_admin_manufacturing_form_schema_input(array $input): array
{
    $schemaInput = $input['schema'] ?? ($input['schema_json'] ?? []);
    if (is_string($schemaInput)) {
        $schemaInput = json_decode($schemaInput !== '' ? $schemaInput : '{}', true, 512, JSON_THROW_ON_ERROR);
    }
    if (!is_array($schemaInput)) {
        throw new InvalidArgumentException('Manufacturing form schema must be structured data.');
    }

    $newFieldLabel = trim((string) ($input['new_field_label'] ?? ''));
    if ($newFieldLabel !== '') {
        $schemaInput['fields'] = is_array($schemaInput['fields'] ?? null) ? $schemaInput['fields'] : [];
        $schemaInput['fields'][] = [
            'key' => trim((string) ($input['new_field_key'] ?? '')),
            'label' => $newFieldLabel,
            'type' => strtoupper(trim((string) ($input['new_field_type'] ?? 'SHORT_TEXT'))),
            'section' => trim((string) ($input['new_field_section'] ?? 'details')),
            'required' => !empty($input['new_field_required']),
            'visible' => true,
            'width' => 'half',
            'order' => (count($schemaInput['fields']) + 1) * 10,
        ];
    }

    return $schemaInput;
}

function yovel_admin_manufacturing_form_adapter(): array
{
    $protected = [];
    foreach (yovel_admin_manufacturing_default_form_schemas() as $recordType => $schema) {
        $protected[$recordType] = $schema['requiredSystemFields'];
    }
    return [
        'module' => 'manufacturing',
        'target_record_types' => yovel_admin_manufacturing_form_targets(),
        'protected_fields' => $protected,
        'field_types' => ['SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'QUANTITY', 'CURRENCY', 'DATE', 'DATETIME', 'DROPDOWN', 'CHECKBOXES', 'TOGGLE', 'LINK', 'BARCODE', 'TABLE', 'SECTION'],
        'row_column_layout' => ['version' => 1, 'max_columns' => 3, 'stable_keys' => true],
        'normalize' => 'yovel_admin_manufacturing_normalize_form_schema',
        'version_identity' => 'yovel_admin_manufacturing_form_checksum',
        'renderer' => 'company/admin/modules/manufacturing/views/form-builder.php',
    ];
}

function yovel_admin_manufacturing_forms(array $company, ?string $targetSection = null): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $sql = "SELECT * FROM project_company_manufacturing_form WHERE company_key_hash = ? AND form_status <> 'ARCHIVED'";
    $params = [$companyKeyHash];
    if ($targetSection !== null && $targetSection !== '') {
        $targetSection = yovel_admin_manufacturing_section($targetSection);
        if (!array_key_exists($targetSection, yovel_admin_manufacturing_form_targets())) {
            return [];
        }
        $sql .= ' AND target_section = ?';
        $params[] = $targetSection;
    }
    $rows = bx_db()->GetAll($sql . ' ORDER BY target_section, form_title, x_id', $params);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_manufacturing_form(array $company, string $formKey): ?array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    if (!yovel_admin_is_uuid($formKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_manufacturing_form WHERE company_key_hash = ? AND form_key = ? LIMIT 1', [$companyKeyHash, strtolower($formKey)]);
    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_manufacturing_form_versions(array $company, string $formKey): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    if (!yovel_admin_is_uuid($formKey)) {
        return [];
    }
    $rows = bx_db()->GetAll('SELECT * FROM project_company_manufacturing_form_version WHERE company_key_hash = ? AND form_key = ? ORDER BY version_number', [$companyKeyHash, strtolower($formKey)]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_manufacturing_form_schema(array $company, string $recordType): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $recordType = yovel_admin_manufacturing_record_type($recordType);
    $version = bx_db()->GetRow(
        "SELECT v.* FROM project_company_manufacturing_form_version v
         INNER JOIN project_company_manufacturing_form f
           ON f.company_key_hash = v.company_key_hash AND f.form_key = v.form_key
         WHERE v.company_key_hash = ? AND v.record_type = ? AND v.version_status = 'PUBLISHED' AND f.form_status = 'PUBLISHED'
         ORDER BY v.version_number DESC, v.x_id DESC LIMIT 1",
        [$companyKeyHash, $recordType]
    );
    if (!is_array($version) || $version === []) {
        return yovel_admin_manufacturing_normalize_form_schema($recordType, yovel_admin_manufacturing_default_form_schemas()[$recordType], 1) + ['persisted' => false, 'form_key' => '', 'form_version_key' => ''];
    }
    $schema = json_decode((string) $version['schema_json'], true, 512, JSON_THROW_ON_ERROR);
    return yovel_admin_manufacturing_normalize_form_schema($recordType, is_array($schema) ? $schema : [], (int) $version['version_number']) + [
        'persisted' => true, 'form_key' => (string) $version['form_key'], 'form_version_key' => (string) $version['form_version_key'],
    ];
}

function yovel_admin_manufacturing_form_schema_by_key(array $company, string $formKey): ?array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    if (!yovel_admin_is_uuid($formKey)) {
        return null;
    }
    $row = bx_db()->GetRow(
        'SELECT f.form_key, f.form_title, f.form_description, f.form_status, f.target_section, v.form_version_key, v.record_type, v.version_number, v.schema_json, v.schema_checksum FROM project_company_manufacturing_form f INNER JOIN project_company_manufacturing_form_version v ON v.company_key_hash=f.company_key_hash AND v.form_version_key=f.current_form_version_key WHERE f.company_key_hash=? AND f.form_key=? LIMIT 1',
        [$companyKeyHash, strtolower($formKey)]
    );
    if (!is_array($row) || $row === []) {
        return null;
    }
    $decoded = json_decode((string) $row['schema_json'], true, 512, JSON_THROW_ON_ERROR);
    return yovel_admin_manufacturing_normalize_form_schema((string) $row['record_type'], is_array($decoded) ? $decoded : [], (int) $row['version_number']) + [
        'persisted' => true,
        'form_key' => (string) $row['form_key'],
        'form_version_key' => (string) $row['form_version_key'],
        'schema_checksum' => (string) $row['schema_checksum'],
        'form_title' => (string) $row['form_title'],
        'form_description' => (string) ($row['form_description'] ?? ''),
        'form_status' => (string) $row['form_status'],
        'target_section' => (string) $row['target_section'],
    ];
}

function yovel_admin_save_manufacturing_form(array $company, array $admin, array $input): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $targetSection = yovel_admin_manufacturing_section((string) ($input['target_section'] ?? ''));
    $recordType = yovel_admin_manufacturing_form_targets()[$targetSection] ?? null;
    if (!is_string($recordType)) {
        throw new InvalidArgumentException('Manufacturing form target is invalid.');
    }
    $formKey = strtolower(trim((string) ($input['form_key'] ?? '')));
    if ($formKey !== '' && !yovel_admin_is_uuid($formKey)) {
        throw new InvalidArgumentException('Manufacturing form key is invalid.');
    }
    $title = trim((string) ($input['form_title'] ?? ''));
    $description = trim((string) ($input['form_description'] ?? ''));
    $status = strtoupper(trim((string) ($input['form_status'] ?? 'DRAFT')));
    if ($title === '' || strlen($title) > 180 || strlen($description) > 1000 || !in_array($status, ['DRAFT', 'PUBLISHED'], true)) {
        throw new InvalidArgumentException('Manufacturing form metadata is invalid.');
    }
    $schemaInput = yovel_admin_manufacturing_form_schema_input($input);

    return yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'FORM_SCHEMA',
        $status === 'PUBLISHED' ? 'PUBLISH' : 'SAVE_DRAFT',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $targetSection, $recordType, $formKey, $title, $description, $status, $schemaInput): array {
            $existing = $formKey !== ''
                ? $db->GetRow('SELECT * FROM project_company_manufacturing_form WHERE company_key_hash = ? AND form_key = ? FOR UPDATE', [$companyKeyHash, $formKey])
                : $db->GetRow('SELECT * FROM project_company_manufacturing_form WHERE company_key_hash = ? AND target_section = ? AND form_title = ? FOR UPDATE', [$companyKeyHash, $targetSection, $title]);
            if ($formKey !== '' && (!is_array($existing) || $existing === [])) {
                $foreign = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_form WHERE form_key = ?', [$formKey]);
                throw new InvalidArgumentException($foreign > 0 ? 'Manufacturing form does not belong to this company.' : 'Manufacturing form was not found.');
            }
            if (is_array($existing) && ($existing['form_status'] ?? '') === 'ARCHIVED') {
                throw new InvalidArgumentException('Archived Manufacturing forms are immutable.');
            }
            $isUpdate = is_array($existing) && $existing !== [];
            $stableKey = $isUpdate ? (string) $existing['form_key'] : bx_uuid();
            $versionNumber = $isUpdate ? (int) $existing['version_count'] + 1 : 1;
            $normalized = yovel_admin_manufacturing_normalize_form_schema($recordType, $schemaInput, $versionNumber);
            $schemaJson = yovel_admin_manufacturing_json($normalized);
            $checksum = hash('sha256', $schemaJson);
            $versionKey = bx_uuid();
            yovel_admin_manufacturing_execute(
                $db,
                'INSERT INTO project_company_manufacturing_form_version (form_version_key, form_key, company_key, company_key_hash, target_section, record_type, version_number, version_status, schema_json, schema_checksum, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$versionKey, $stableKey, $companyKey, $companyKeyHash, $targetSection, $recordType, $versionNumber, $status, $schemaJson, $checksum, $adminKey],
                'Manufacturing form version save'
            );
            yovel_admin_manufacturing_execute(
                $db,
                'INSERT INTO project_company_manufacturing_form (form_key, company_key, company_key_hash, target_section, record_type, form_title, form_description, form_status, current_form_version_key, version_count, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE target_section=VALUES(target_section), record_type=VALUES(record_type), form_title=VALUES(form_title), form_description=VALUES(form_description), form_status=VALUES(form_status), current_form_version_key=VALUES(current_form_version_key), version_count=VALUES(version_count), updated_by_admin_key=VALUES(updated_by_admin_key)',
                [$stableKey, $companyKey, $companyKeyHash, $targetSection, $recordType, $title, $description !== '' ? $description : null, $status, $versionKey, $versionNumber, $adminKey, $adminKey],
                'Manufacturing form save'
            );
            yovel_admin_manufacturing_execute(
                $db,
                'INSERT INTO project_company_manufacturing_form_audit (form_audit_key, company_key, company_key_hash, form_key, form_version_key, audit_action, schema_checksum, admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [bx_uuid(), $companyKey, $companyKeyHash, $stableKey, $versionKey, $status === 'PUBLISHED' ? 'PUBLISH' : 'SAVE_DRAFT', $checksum, $adminKey],
                'Manufacturing form audit save'
            );
            return [
                'record_key' => $stableKey, 'business_key' => $targetSection . ':' . $title, 'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey,
                'persisted_fields' => [
                    'form_key' => $stableKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                    'target_section' => $targetSection, 'record_type' => $recordType, 'form_title' => $title,
                    'form_description' => $description, 'form_status' => $status,
                    'current_form_version_key' => $versionKey, 'version_count' => $versionNumber,
                ],
                'audit_action' => $isUpdate ? ($status === 'PUBLISHED' ? 'PUBLISH' : 'UPDATE') : 'CREATE',
            ];
        },
        static function (ADOConnection $db, array $expected) use ($companyKeyHash): array {
            $row = $db->GetRow('SELECT * FROM project_company_manufacturing_form WHERE company_key_hash = ? AND form_key = ? LIMIT 1', [$companyKeyHash, (string) $expected['record_key']]);
            if (!is_array($row) || $row === []) {
                return [];
            }
            $row['form_description'] = (string) ($row['form_description'] ?? '');
            $version = $db->GetRow('SELECT form_version_key, form_key, version_number, schema_checksum FROM project_company_manufacturing_form_version WHERE company_key_hash = ? AND form_version_key = ? LIMIT 1', [$companyKeyHash, (string) $row['current_form_version_key']]);
            if (!is_array($version) || (string) ($version['form_key'] ?? '') !== (string) $row['form_key'] || (int) ($version['version_number'] ?? 0) !== (int) $row['version_count']) {
                throw new RuntimeException('Manufacturing form version read-back verification failed.');
            }
            return $row;
        }
    );
}

function yovel_admin_archive_manufacturing_form(array $company, array $admin, string $formKey): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    if (!yovel_admin_is_uuid($formKey)) {
        throw new InvalidArgumentException('Manufacturing form key is invalid.');
    }
    return yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'FORM_SCHEMA',
        'ARCHIVE',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $formKey): array {
            $existing = $db->GetRow("SELECT * FROM project_company_manufacturing_form WHERE company_key_hash = ? AND form_key = ? AND form_status <> 'ARCHIVED' FOR UPDATE", [$companyKeyHash, strtolower($formKey)]);
            if (!is_array($existing) || $existing === []) {
                throw new InvalidArgumentException('Active Manufacturing form was not found.');
            }
            yovel_admin_manufacturing_execute($db, "UPDATE project_company_manufacturing_form SET form_status = 'ARCHIVED', updated_by_admin_key = ? WHERE company_key_hash = ? AND form_key = ?", [$adminKey, $companyKeyHash, strtolower($formKey)], 'Manufacturing form archive');
            yovel_admin_manufacturing_execute($db, 'INSERT INTO project_company_manufacturing_form_audit (form_audit_key, company_key, company_key_hash, form_key, form_version_key, audit_action, schema_checksum, admin_key) SELECT ?, f.company_key, f.company_key_hash, f.form_key, f.current_form_version_key, ?, v.schema_checksum, ? FROM project_company_manufacturing_form f INNER JOIN project_company_manufacturing_form_version v ON v.company_key_hash=f.company_key_hash AND v.form_version_key=f.current_form_version_key WHERE f.company_key_hash=? AND f.form_key=?', [bx_uuid(), 'ARCHIVE', $adminKey, $companyKeyHash, strtolower($formKey)], 'Manufacturing form archive audit');
            return [
                'record_key' => strtolower($formKey), 'business_key' => (string) $existing['target_section'] . ':' . (string) $existing['form_title'],
                'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey,
                'persisted_fields' => ['form_key' => strtolower($formKey), 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'form_status' => 'ARCHIVED'],
                'audit_action' => 'ARCHIVE',
            ];
        },
        static fn (ADOConnection $db, array $expected): array => (array) $db->GetRow('SELECT * FROM project_company_manufacturing_form WHERE company_key_hash = ? AND form_key = ? LIMIT 1', [(string) $expected['company_key_hash'], (string) $expected['record_key']])
    );
}

function yovel_admin_manufacturing_bind_form_version(array $company, array $admin, string $recordKey, string $recordType, string $formVersionKey): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $recordKey = trim($recordKey);
    $recordType = yovel_admin_manufacturing_record_type($recordType);
    if ($recordKey === '' || strlen($recordKey) > 1500 || !yovel_admin_is_uuid($formVersionKey)) {
        throw new InvalidArgumentException('Manufacturing record form binding is invalid.');
    }
    $recordKeyHash = hash('sha256', $recordKey);
    return yovel_admin_manufacturing_with_transaction(
        $companyKey,
        'FORM_BINDING',
        'BIND',
        static function (ADOConnection $db) use ($companyKey, $companyKeyHash, $adminKey, $recordKey, $recordKeyHash, $recordType, $formVersionKey): array {
            $version = $db->GetRow("SELECT form_key, form_version_key FROM project_company_manufacturing_form_version WHERE company_key_hash = ? AND form_version_key = ? AND record_type = ? AND version_status = 'PUBLISHED' LIMIT 1", [$companyKeyHash, strtolower($formVersionKey), $recordType]);
            if (!is_array($version) || $version === []) {
                throw new InvalidArgumentException('A published Manufacturing form version is required.');
            }
            $existing = $db->GetRow('SELECT * FROM project_company_manufacturing_record_form_version WHERE company_key_hash = ? AND record_type = ? AND record_key_hash = ? FOR UPDATE', [$companyKeyHash, $recordType, $recordKeyHash]);
            if (is_array($existing) && $existing !== []) {
                if ((string) $existing['form_version_key'] !== strtolower($formVersionKey)) {
                    throw new InvalidArgumentException('Submitted Manufacturing records cannot change form versions.');
                }
                $bindingKey = (string) $existing['binding_key'];
            } else {
                $bindingKey = bx_uuid();
                yovel_admin_manufacturing_execute(
                    $db,
                    'INSERT INTO project_company_manufacturing_record_form_version (binding_key, company_key, company_key_hash, record_type, record_key, record_key_hash, form_key, form_version_key, bound_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$bindingKey, $companyKey, $companyKeyHash, $recordType, $recordKey, $recordKeyHash, (string) $version['form_key'], strtolower($formVersionKey), $adminKey],
                    'Manufacturing record form version bind'
                );
            }
            return [
                'record_key' => $bindingKey, 'business_key' => $recordType . ':' . $recordKeyHash,
                'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'admin_key' => $adminKey,
                'persisted_fields' => [
                    'binding_key' => $bindingKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash,
                    'record_type' => $recordType, 'record_key' => $recordKey, 'record_key_hash' => $recordKeyHash,
                    'form_key' => (string) $version['form_key'], 'form_version_key' => strtolower($formVersionKey),
                ],
                'audit_action' => 'BIND',
            ];
        },
        static fn (ADOConnection $db, array $expected): array => (array) $db->GetRow('SELECT * FROM project_company_manufacturing_record_form_version WHERE company_key_hash = ? AND binding_key = ? LIMIT 1', [(string) $expected['company_key_hash'], (string) $expected['record_key']])
    );
}
