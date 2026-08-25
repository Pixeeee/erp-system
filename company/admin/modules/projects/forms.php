<?php
declare(strict_types=1);

function yovel_admin_projects_builder_targets(): array
{
    return [
        'PROJECT' => 'Project',
        'PROJECT_TEMPLATE' => 'Project Template',
        'TASK' => 'Task',
        'PROJECT_UPDATE' => 'Project Update',
        'ACTIVITY_TYPE' => 'Activity Type',
        'ACTIVITY_COST' => 'Activity Cost',
    ];
}

function yovel_admin_projects_target(string $target): string
{
    $target = strtoupper(trim($target));
    if (!isset(yovel_admin_projects_builder_targets()[$target])) {
        throw new InvalidArgumentException('Projects Form Builder target is invalid.');
    }
    return $target;
}

function yovel_admin_projects_field(
    string $key,
    string $label,
    string $type,
    bool $required = true,
    array $options = []
): array {
    return [
        'key' => $key,
        'label' => $label,
        'type' => $type,
        'section' => 'details',
        'row' => 'details-row',
        'column' => 1,
        'width' => 12,
        'required' => $required,
        'visible' => true,
        'system' => true,
        'default' => '',
        'options' => $options,
        'validation' => [],
    ];
}

function yovel_admin_projects_default_form_schemas(): array
{
    $definitions = [
        'PROJECT' => [
            yovel_admin_projects_field('project_code', 'Project code', 'SHORT_TEXT'),
            yovel_admin_projects_field('project_name', 'Project name', 'SHORT_TEXT'),
            yovel_admin_projects_field('project_status', 'Status', 'DROPDOWN', true, ['OPEN', 'ON_HOLD', 'COMPLETED', 'CANCELLED']),
        ],
        'PROJECT_TEMPLATE' => [
            yovel_admin_projects_field('template_code', 'Template code', 'SHORT_TEXT'),
            yovel_admin_projects_field('template_name', 'Template name', 'SHORT_TEXT'),
            yovel_admin_projects_field('template_status', 'Status', 'DROPDOWN', true, ['DRAFT', 'ACTIVE', 'ARCHIVED']),
        ],
        'TASK' => [
            yovel_admin_projects_field('task_code', 'Task code', 'SHORT_TEXT'),
            yovel_admin_projects_field('task_subject', 'Subject', 'SHORT_TEXT'),
            yovel_admin_projects_field('task_status', 'Status', 'DROPDOWN', true, ['OPEN', 'WORKING', 'PENDING_REVIEW', 'COMPLETED']),
        ],
        'PROJECT_UPDATE' => [
            yovel_admin_projects_field('project_key', 'Project', 'LINK'),
            yovel_admin_projects_field('update_title', 'Update title', 'SHORT_TEXT'),
            yovel_admin_projects_field('update_body', 'Update', 'PARAGRAPH'),
        ],
        'ACTIVITY_TYPE' => [
            yovel_admin_projects_field('activity_type_code', 'Activity code', 'SHORT_TEXT'),
            yovel_admin_projects_field('activity_type_name', 'Activity name', 'SHORT_TEXT'),
            yovel_admin_projects_field('activity_type_status', 'Status', 'DROPDOWN', true, ['ACTIVE', 'INACTIVE']),
        ],
        'ACTIVITY_COST' => [
            yovel_admin_projects_field('activity_type_key', 'Activity type', 'LINK'),
            yovel_admin_projects_field('employee_key', 'Employee', 'LINK'),
            yovel_admin_projects_field('costing_rate', 'Costing rate', 'NUMBER'),
            yovel_admin_projects_field('billing_rate', 'Billing rate', 'NUMBER'),
        ],
    ];
    $schemas = [];
    foreach ($definitions as $target => $fields) {
        $fields[0]['system'] = false;
        $schemas[$target] = [
            'schemaVersion' => 1,
            'targetType' => $target,
            'requiredSystemFields' => array_slice(array_column($fields, 'key'), 1),
            'sections' => [[
                'key' => 'details',
                'label' => 'Details',
                'rows' => [[
                    'key' => 'details-row',
                    'columns' => [
                        ['key' => 'details-primary', 'width' => 6],
                        ['key' => 'details-secondary', 'width' => 6],
                    ],
                ]],
            ]],
            'fields' => $fields,
        ];
    }
    return $schemas;
}

function yovel_admin_projects_form_adapter(): array
{
    return [
        'module' => 'projects',
        'target_record_types' => array_keys(yovel_admin_projects_builder_targets()),
        'field_types' => ['SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'DATE', 'DATETIME', 'DROPDOWN', 'CHECKBOXES', 'TOGGLE', 'LINK'],
        'row_column_layout' => ['version' => 1, 'max_columns' => 3, 'stable_keys' => true],
        'normalize' => 'yovel_admin_projects_normalize_form_schema',
    ];
}

function yovel_admin_projects_form_json(array $schema): string
{
    return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function yovel_admin_projects_normalize_form_schema(string $target, array $schema, int $version = 1): array
{
    $target = yovel_admin_projects_target($target);
    if (strlen(yovel_admin_projects_form_json($schema)) > 500000) {
        throw new InvalidArgumentException('Projects form schema is too large.');
    }
    $default = yovel_admin_projects_default_form_schemas()[$target];
    $allowedTypes = yovel_admin_projects_form_adapter()['field_types'];
    $sourceFields = is_array($schema['fields'] ?? null) ? array_values($schema['fields']) : [];
    if (count($sourceFields) > 120) {
        throw new InvalidArgumentException('Projects forms support up to 120 fields.');
    }
    $protected = [];
    foreach ($default['fields'] as $field) {
        if (in_array((string) $field['key'], $default['requiredSystemFields'], true)) {
            $protected[(string) $field['key']] = $field;
        }
    }
    $fields = [];
    foreach ($sourceFields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $key = strtolower(trim((string) ($field['key'] ?? '')));
        if (preg_match('/^[a-z][a-z0-9_]{1,79}$/', $key) !== 1 || isset($fields[$key])) {
            throw new InvalidArgumentException('Projects fields require unique stable keys.');
        }
        if (isset($protected[$key])) {
            $fields[$key] = $protected[$key];
            continue;
        }
        $label = trim((string) ($field['label'] ?? ''));
        if ($label === '' || strlen($label) > 180) {
            throw new InvalidArgumentException('Projects field labels are required and limited to 180 characters.');
        }
        $type = strtoupper(trim((string) ($field['type'] ?? 'SHORT_TEXT')));
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'SHORT_TEXT';
        }
        $options = is_array($field['options'] ?? null) ? array_values(array_slice(array_map(
            static fn (mixed $option): string => substr(trim((string) $option), 0, 160),
            $field['options']
        ), 0, 40)) : [];
        $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
        $fields[$key] = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'section' => substr(trim((string) ($field['section'] ?? 'details')), 0, 80) ?: 'details',
            'row' => substr(trim((string) ($field['row'] ?? 'details-row')), 0, 80) ?: 'details-row',
            'column' => max(1, min(3, (int) ($field['column'] ?? 1))),
            'width' => max(1, min(12, (int) ($field['width'] ?? 12))),
            'required' => !empty($field['required']),
            'visible' => !array_key_exists('visible', $field) || !empty($field['visible']),
            'system' => false,
            'default' => is_scalar($field['default'] ?? '') ? (string) ($field['default'] ?? '') : '',
            'options' => $options,
            'validation' => $validation,
        ];
    }
    foreach ($protected as $key => $field) {
        $fields[$key] = $field;
    }
    return [
        'schemaVersion' => max(1, $version),
        'targetType' => $target,
        'requiredSystemFields' => $default['requiredSystemFields'],
        'sections' => is_array($schema['sections'] ?? null) && $schema['sections'] !== [] ? array_values($schema['sections']) : $default['sections'],
        'fields' => array_values($fields),
    ];
}

function yovel_admin_projects_form_company_scope(array $company): array
{
    $key = trim((string) ($company['company_key'] ?? ''));
    $hash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    if ($key === '' || strlen($key) > 1500 || preg_match('/^[0-9a-f]{64}$/', $hash) !== 1 || !hash_equals(hash('sha256', $key), $hash)) {
        throw new InvalidArgumentException('Projects company scope is invalid.');
    }
    return [$key, $hash];
}

function yovel_admin_projects_schema_row(array $row, bool $persisted = true): array
{
    $schema = json_decode((string) ($row['schema_json'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($schema)) {
        throw new RuntimeException('Projects form schema could not be rehydrated.');
    }
    $schema['persisted'] = $persisted;
    $schema['form_schema_key'] = (string) ($row['form_schema_key'] ?? '');
    $schema['version'] = (int) ($row['version_number'] ?? $schema['schemaVersion'] ?? 1);
    $schema['schema_status'] = (string) ($row['schema_status'] ?? 'DEFAULT');
    return $schema;
}

function yovel_admin_projects_default_schema_result(string $target): array
{
    $schema = yovel_admin_projects_default_form_schemas()[yovel_admin_projects_target($target)];
    $schema['persisted'] = false;
    $schema['form_schema_key'] = '';
    $schema['version'] = 0;
    $schema['schema_status'] = 'DEFAULT';
    return $schema;
}

function yovel_admin_projects_active_schema(array $company, string $target): array
{
    [, $hash] = yovel_admin_projects_form_company_scope($company);
    $target = yovel_admin_projects_target($target);
    yovel_admin_projects_schema();
    $row = bx_db()->GetRow(
        "SELECT * FROM project_company_project_form_schema
         WHERE company_key_hash = ? AND target_type = ? AND schema_status = 'PUBLISHED'
         ORDER BY version_number DESC LIMIT 1",
        [$hash, $target]
    );
    return is_array($row) && $row !== [] ? yovel_admin_projects_schema_row($row) : yovel_admin_projects_default_schema_result($target);
}

function yovel_admin_projects_editable_schema(array $company, string $target): array
{
    [, $hash] = yovel_admin_projects_form_company_scope($company);
    $target = yovel_admin_projects_target($target);
    yovel_admin_projects_schema();
    $row = bx_db()->GetRow(
        "SELECT * FROM project_company_project_form_schema
         WHERE company_key_hash = ? AND target_type = ? AND schema_status IN ('DRAFT','PUBLISHED')
         ORDER BY (schema_status = 'DRAFT') DESC, version_number DESC LIMIT 1",
        [$hash, $target]
    );
    return is_array($row) && $row !== [] ? yovel_admin_projects_schema_row($row) : yovel_admin_projects_default_schema_result($target);
}

function yovel_admin_projects_schema_history(array $company, string $target): array
{
    [, $hash] = yovel_admin_projects_form_company_scope($company);
    $target = yovel_admin_projects_target($target);
    yovel_admin_projects_schema();
    $rows = bx_db()->GetAll(
        'SELECT form_schema_key, target_type, version_number, schema_status, schema_checksum, created_by_admin_key, created_at
         FROM project_company_project_form_schema
         WHERE company_key_hash = ? AND target_type = ?
         ORDER BY version_number DESC',
        [$hash, $target]
    );
    if (!is_array($rows)) {
        return [];
    }
    foreach ($rows as &$row) {
        $row['version_number'] = (string) ($row['version_number'] ?? '');
    }
    unset($row);
    return $rows;
}

function yovel_admin_projects_save_form_schema(
    array $company,
    array $admin,
    string $target,
    array $schema,
    string $status,
    string $csrf
): array {
    yovel_admin_projects_assert_csrf(['csrf' => $csrf]);
    yovel_admin_projects_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    $target = yovel_admin_projects_target($target);
    $status = strtoupper(trim($status));
    if (!in_array($status, ['DRAFT', 'PUBLISHED', 'ARCHIVED'], true)) {
        throw new InvalidArgumentException('Projects form lifecycle is invalid.');
    }

    return yovel_admin_projects_in_transaction(static function (ADOConnection $db) use (
        $companyKey, $companyKeyHash, $adminKey, $target, $schema, $status
    ): array {
        $companyLock = $db->GetRow(
            "SELECT company_key, company_key_hash FROM project_company
             WHERE company_key = ? AND company_key_hash = ? AND company_status = 'ACTIVE' FOR UPDATE",
            [$companyKey, $companyKeyHash]
        );
        yovel_admin_projects_assert_readback(
            ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash],
            is_array($companyLock) ? $companyLock : [],
            ['company_key', 'company_key_hash'],
            'Projects company lock'
        );
        $latest = $db->GetRow(
            'SELECT version_number FROM project_company_project_form_schema
             WHERE company_key_hash = ? AND target_type = ? ORDER BY version_number DESC LIMIT 1 FOR UPDATE',
            [$companyKeyHash, $target]
        );
        $version = (int) ($latest['version_number'] ?? 0) + 1;
        $normalized = yovel_admin_projects_normalize_form_schema($target, $schema, $version);
        $json = yovel_admin_projects_form_json($normalized);
        $identity = $normalized;
        $identity['schemaVersion'] = 0;
        $checksum = hash('sha256', yovel_admin_projects_form_json($identity));
        $existing = $db->GetRow(
            'SELECT * FROM project_company_project_form_schema
             WHERE company_key_hash = ? AND target_type = ? AND schema_status = ? AND schema_checksum = ?
             LIMIT 1 FOR UPDATE',
            [$companyKeyHash, $target, $status, $checksum]
        );
        if (is_array($existing) && $existing !== []) {
            return yovel_admin_projects_schema_row($existing);
        }
        $schemaKey = bx_uuid();
        yovel_admin_projects_db_execute(
            $db,
            'INSERT INTO project_company_project_form_schema (
                form_schema_key, company_key, company_key_hash, target_type,
                version_number, schema_status, schema_json, schema_checksum, created_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$schemaKey, $companyKey, $companyKeyHash, $target, $version, $status, $json, $checksum, $adminKey],
            'Projects form schema save'
        );
        yovel_admin_projects_audit(
            'CREATE', 'project_company_project_form_schema', $schemaKey, $companyKeyHash, $adminKey,
            ['target_type' => $target, 'version_number' => $version, 'schema_status' => $status, 'schema_checksum' => $checksum]
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_project_form_schema
             WHERE form_schema_key = ? AND company_key_hash = ? LIMIT 1',
            [$schemaKey, $companyKeyHash]
        );
        yovel_admin_projects_assert_readback(
            [
                'form_schema_key' => $schemaKey, 'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash, 'target_type' => $target,
                'version_number' => $version, 'schema_status' => $status,
                'schema_json' => $json, 'schema_checksum' => $checksum,
                'created_by_admin_key' => $adminKey,
            ],
            is_array($saved) ? $saved : [],
            [
                'form_schema_key', 'company_key', 'company_key_hash', 'target_type',
                'version_number', 'schema_status', 'schema_json', 'schema_checksum', 'created_by_admin_key',
            ],
            'Projects form schema'
        );
        return yovel_admin_projects_schema_row($saved);
    });
}

function yovel_admin_projects_publish_schema(array $company, array $admin, string $target, array $schema, string $csrf = ''): array
{
    return yovel_admin_projects_save_form_schema($company, $admin, $target, $schema, 'PUBLISHED', $csrf);
}

function yovel_admin_projects_archive_schema(array $company, array $admin, string $target, array $schema, string $csrf = ''): array
{
    return yovel_admin_projects_save_form_schema($company, $admin, $target, $schema, 'ARCHIVED', $csrf);
}
