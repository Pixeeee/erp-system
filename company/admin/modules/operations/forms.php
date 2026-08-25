<?php
declare(strict_types=1);

function yovel_admin_operations_builder_targets(): array
{
    return [
        'dashboard' => 'Operations Dashboard',
        'scheduled-jobs' => 'Scheduled Job',
        'notifications' => 'Notification Handoff',
        'background-workers' => 'Background Worker',
        'sync-conflict-dashboard' => 'Sync Conflict',
        'import-export-jobs' => 'Import or Export Job',
        'system-alerts' => 'System Alert',
        'release-checklist' => 'Release Check',
        'bulk-processing' => 'Bulk Processing Log',
        'governed-deletion' => 'Governed Deletion Request',
        'authorization-setup' => 'Authorization Policy',
        'company-defaults' => 'Company Default Projection',
        'workforce-directory' => 'Workforce Projection',
        'workforce-calendars' => 'Workforce Calendar Projection',
        'commercial-masters' => 'Commercial Master Projection',
        'catalog-units' => 'Catalog and Unit Projection',
    ];
}

function yovel_admin_operations_protected_fields(): array
{
    return [
        'dashboard' => [
            ['key' => 'dashboard_widget_key', 'label' => 'Widget key', 'type' => 'SHORT_TEXT'],
            ['key' => 'widget_type', 'label' => 'Widget type', 'type' => 'DROPDOWN'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'scheduled-jobs' => [
            ['key' => 'job_key', 'label' => 'Job key', 'type' => 'SHORT_TEXT'],
            ['key' => 'job_type', 'label' => 'Job type', 'type' => 'DROPDOWN'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'notifications' => [
            ['key' => 'notification_key', 'label' => 'Notification key', 'type' => 'SHORT_TEXT'],
            ['key' => 'channel', 'label' => 'Channel', 'type' => 'DROPDOWN'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'background-workers' => [
            ['key' => 'worker_key', 'label' => 'Worker key', 'type' => 'SHORT_TEXT'],
            ['key' => 'worker_name', 'label' => 'Worker name', 'type' => 'SHORT_TEXT'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'sync-conflict-dashboard' => [
            ['key' => 'conflict_key', 'label' => 'Conflict key', 'type' => 'SHORT_TEXT'],
            ['key' => 'source_type', 'label' => 'Source type', 'type' => 'SHORT_TEXT'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'import-export-jobs' => [
            ['key' => 'job_key', 'label' => 'Job key', 'type' => 'SHORT_TEXT'],
            ['key' => 'direction', 'label' => 'Direction', 'type' => 'DROPDOWN'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'system-alerts' => [
            ['key' => 'alert_key', 'label' => 'Alert key', 'type' => 'SHORT_TEXT'],
            ['key' => 'severity', 'label' => 'Severity', 'type' => 'DROPDOWN'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'release-checklist' => [
            ['key' => 'release_check_key', 'label' => 'Release check key', 'type' => 'SHORT_TEXT'],
            ['key' => 'check_code', 'label' => 'Check code', 'type' => 'SHORT_TEXT'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'bulk-processing' => [
            ['key' => 'bulk_log_key', 'label' => 'Bulk log key', 'type' => 'SHORT_TEXT'],
            ['key' => 'action_code', 'label' => 'Action', 'type' => 'DROPDOWN'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'governed-deletion' => [
            ['key' => 'request_key', 'label' => 'Request key', 'type' => 'SHORT_TEXT'],
            ['key' => 'record_type', 'label' => 'Record type', 'type' => 'SHORT_TEXT'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'authorization-setup' => [
            ['key' => 'policy_key', 'label' => 'Policy key', 'type' => 'SHORT_TEXT'],
            ['key' => 'document_type', 'label' => 'Document type', 'type' => 'SHORT_TEXT'],
            ['key' => 'policy_status', 'label' => 'Status', 'type' => 'DROPDOWN'],
        ],
        'company-defaults' => [
            ['key' => 'owner_record_key', 'label' => 'Owner record key', 'type' => 'SHORT_TEXT'],
            ['key' => 'owner_contract', 'label' => 'Owner contract', 'type' => 'SHORT_TEXT'],
            ['key' => 'projection_status', 'label' => 'Projection status', 'type' => 'DROPDOWN'],
        ],
        'workforce-directory' => [
            ['key' => 'owner_record_key', 'label' => 'Owner record key', 'type' => 'SHORT_TEXT'],
            ['key' => 'workforce_record_type', 'label' => 'Workforce record type', 'type' => 'DROPDOWN'],
            ['key' => 'projection_status', 'label' => 'Projection status', 'type' => 'DROPDOWN'],
        ],
        'workforce-calendars' => [
            ['key' => 'owner_record_key', 'label' => 'Owner record key', 'type' => 'SHORT_TEXT'],
            ['key' => 'calendar_record_type', 'label' => 'Calendar record type', 'type' => 'DROPDOWN'],
            ['key' => 'projection_status', 'label' => 'Projection status', 'type' => 'DROPDOWN'],
        ],
        'commercial-masters' => [
            ['key' => 'owner_record_key', 'label' => 'Owner record key', 'type' => 'SHORT_TEXT'],
            ['key' => 'commercial_record_type', 'label' => 'Commercial record type', 'type' => 'DROPDOWN'],
            ['key' => 'projection_status', 'label' => 'Projection status', 'type' => 'DROPDOWN'],
        ],
        'catalog-units' => [
            ['key' => 'owner_record_key', 'label' => 'Owner record key', 'type' => 'SHORT_TEXT'],
            ['key' => 'catalog_record_type', 'label' => 'Catalog record type', 'type' => 'DROPDOWN'],
            ['key' => 'projection_status', 'label' => 'Projection status', 'type' => 'DROPDOWN'],
        ],
    ];
}

function yovel_admin_operations_form_adapter(): array
{
    $protected = [];
    foreach (yovel_admin_operations_protected_fields() as $target => $fields) {
        $protected[$target] = array_column($fields, 'key');
    }

    return [
        'module' => 'operations',
        'target_record_types' => yovel_admin_operations_builder_targets(),
        'protected_fields' => $protected,
        'field_types' => ['SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'DATE', 'DATETIME', 'DROPDOWN', 'CHECKBOXES', 'TOGGLE', 'SECTION'],
        'row_column_layout' => ['version' => 2, 'max_columns' => 3, 'stable_keys' => true],
        'normalize' => 'yovel_admin_normalize_operations_builder_schema',
        'version_identity' => 'yovel_admin_operations_builder_checksum',
    ];
}

function yovel_admin_operations_field_key(string $value): string
{
    $value = trim($value);
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,119}$/', $value) === 1 ? $value : bx_uuid();
}

function yovel_admin_operations_builder_target(string $target): string
{
    $target = function_exists('yovel_admin_slug') ? yovel_admin_slug($target) : strtolower(trim($target));
    if (!isset(yovel_admin_operations_builder_targets()[$target])) {
        throw new InvalidArgumentException('Operations Form Builder target is invalid.');
    }
    return $target;
}

function yovel_admin_normalize_operations_builder_schema(string $target, array $schema): array
{
    $target = yovel_admin_operations_builder_target($target);
    if (strlen(yovel_admin_operations_json($schema)) > 500000) {
        throw new InvalidArgumentException('Operations form layout is too large.');
    }

    $sourceFields = $schema['fields'] ?? $schema['questions'] ?? [];
    $sourceRows = $schema['rows'] ?? [];
    if (!is_array($sourceFields) || !is_array($sourceRows)) {
        throw new InvalidArgumentException('Operations form fields and rows must be lists.');
    }
    if (count($sourceFields) > 80 || count($sourceRows) > 30) {
        throw new InvalidArgumentException('Operations forms support up to 80 fields and 30 rows.');
    }

    $rows = [];
    $columnToRow = [];
    $fieldPlacement = [];
    foreach (array_values($sourceRows) as $rowIndex => $sourceRow) {
        if (!is_array($sourceRow)) {
            continue;
        }
        $rowKey = yovel_admin_operations_field_key((string) ($sourceRow['key'] ?? ''));
        $sourceColumns = is_array($sourceRow['columns'] ?? null) ? $sourceRow['columns'] : [];
        if (count($sourceColumns) > 3) {
            throw new InvalidArgumentException('An Operations form row can contain up to three columns.');
        }
        if ($sourceColumns === []) {
            $sourceColumns = [['key' => $rowKey . '-column', 'width' => 12]];
        }
        $columns = [];
        foreach (array_values($sourceColumns) as $columnIndex => $sourceColumn) {
            if (!is_array($sourceColumn)) {
                continue;
            }
            $columnKey = yovel_admin_operations_field_key((string) ($sourceColumn['key'] ?? ''));
            if (isset($columnToRow[$columnKey])) {
                throw new InvalidArgumentException('Operations form columns require unique stable keys.');
            }
            $columnToRow[$columnKey] = $rowKey;
            $fieldKeys = is_array($sourceColumn['field_keys'] ?? null) ? $sourceColumn['field_keys'] : [];
            foreach ($fieldKeys as $fieldKey) {
                $fieldKey = trim((string) $fieldKey);
                if ($fieldKey !== '') {
                    $fieldPlacement[$fieldKey] = [$rowKey, $columnKey];
                }
            }
            $columns[] = [
                'key' => $columnKey,
                'width' => max(1, min(12, (int) ($sourceColumn['width'] ?? 12))),
                'field_keys' => array_values(array_filter(array_map('strval', $fieldKeys))),
                'order' => ($columnIndex + 1) * 10,
            ];
        }
        if ($columns === []) {
            continue;
        }
        $rows[] = ['key' => $rowKey, 'columns' => $columns, 'order' => ($rowIndex + 1) * 10];
    }
    if ($rows === []) {
        $rows = [['key' => 'default-row', 'order' => 10, 'columns' => [[
            'key' => 'default-column', 'width' => 12, 'field_keys' => [], 'order' => 10,
        ]]]];
        $columnToRow = ['default-column' => 'default-row'];
    }

    $allowedTypes = yovel_admin_operations_form_adapter()['field_types'];
    $protectedDefinitions = [];
    foreach (yovel_admin_operations_protected_fields()[$target] as $definition) {
        $protectedDefinitions[$definition['key']] = $definition;
    }
    $fieldsByKey = [];
    foreach (array_values($sourceFields) as $fieldIndex => $sourceField) {
        if (!is_array($sourceField)) {
            continue;
        }
        $key = yovel_admin_operations_field_key((string) ($sourceField['key'] ?? ''));
        if (isset($fieldsByKey[$key])) {
            throw new InvalidArgumentException('Operations form fields require unique stable keys.');
        }
        $definition = $protectedDefinitions[$key] ?? null;
        $type = strtoupper(trim((string) ($definition['type'] ?? $sourceField['type'] ?? 'SHORT_TEXT')));
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'SHORT_TEXT';
        }
        $label = trim((string) ($definition['label'] ?? $sourceField['label'] ?? 'Untitled field'));
        if ($label === '' || strlen($label) > 180) {
            throw new InvalidArgumentException('Operations field labels are required and limited to 180 characters.');
        }
        $placement = $fieldPlacement[$key] ?? [(string) $rows[0]['key'], (string) $rows[0]['columns'][0]['key']];
        $options = is_array($sourceField['options'] ?? null) ? array_values(array_slice(array_filter(array_map(
            static fn ($option): string => substr(trim((string) $option), 0, 160),
            $sourceField['options']
        )), 0, 30)) : [];
        $fieldsByKey[$key] = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $definition !== null || !empty($sourceField['required']),
            'system' => $definition !== null,
            'help' => substr(trim((string) ($sourceField['help'] ?? '')), 0, 1000),
            'options' => in_array($type, ['DROPDOWN', 'CHECKBOXES'], true) ? $options : [],
            'row_key' => $placement[0],
            'column_key' => $placement[1],
            'order' => ($fieldIndex + 1) * 10,
        ];
    }
    foreach ($protectedDefinitions as $key => $definition) {
        if (isset($fieldsByKey[$key])) {
            continue;
        }
        $fieldsByKey[$key] = [
            'key' => $key,
            'label' => $definition['label'],
            'type' => $definition['type'],
            'required' => true,
            'system' => true,
            'help' => '',
            'options' => [],
            'row_key' => (string) $rows[0]['key'],
            'column_key' => (string) $rows[0]['columns'][0]['key'],
            'order' => (count($fieldsByKey) + 1) * 10,
        ];
        $rows[0]['columns'][0]['field_keys'][] = $key;
    }

    return ['version' => 2, 'rows' => $rows, 'fields' => array_values($fieldsByKey)];
}

function yovel_admin_operations_builder_checksum(array $form, string $schemaJson): string
{
    return hash('sha256', yovel_admin_operations_json([
        'target_section' => (string) $form['target_section'],
        'form_title' => (string) $form['form_title'],
        'form_description' => (string) $form['form_description'],
        'form_status' => (string) $form['form_status'],
        'schema_json' => $schemaJson,
    ]));
}

function yovel_admin_operations_builder_form_row(array $company, string $builderFormKey): ?array
{
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    if (!yovel_admin_is_uuid($builderFormKey)) {
        return null;
    }
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_operations_builder_form WHERE company_key_hash = ? AND builder_form_key = ?',
        [$companyKeyHash, $builderFormKey]
    );
    if (!$row) {
        return null;
    }
    $row['version_number'] = (int) $row['version_number'];
    $row['schema'] = yovel_admin_operations_json_array((string) $row['schema_json'], 'Operations form schema');
    return $row;
}

function yovel_admin_operations_builder_forms(array $company): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    $rows = bx_db()->GetAll(
        'SELECT * FROM project_company_operations_builder_form WHERE company_key_hash = ? ORDER BY updated_at DESC, x_id DESC',
        [$companyKeyHash]
    );
    foreach ($rows as &$row) {
        $row['version_number'] = (int) $row['version_number'];
        $row['schema'] = yovel_admin_operations_json_array((string) $row['schema_json'], 'Operations form schema');
    }
    unset($row);
    return $rows;
}

function yovel_admin_operations_builder_form_version(array $company, string $formVersionKey): ?array
{
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_operations_builder_form_version WHERE company_key_hash = ? AND form_version_key = ?',
        [$companyKeyHash, $formVersionKey]
    );
    if (!$row) {
        return null;
    }
    $row['version_number'] = (int) $row['version_number'];
    $row['schema'] = yovel_admin_operations_json_array((string) $row['schema_json'], 'Operations form version schema');
    return $row;
}

function yovel_admin_persist_operations_builder_form(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $target = yovel_admin_operations_builder_target((string) ($input['target_section'] ?? ''));
    $title = trim((string) ($input['form_title'] ?? ''));
    $description = trim((string) ($input['form_description'] ?? ''));
    $status = strtoupper(trim((string) ($input['form_status'] ?? 'DRAFT')));
    if ($title === '' || strlen($title) > 180 || strlen($description) > 500) {
        throw new InvalidArgumentException('Operations form title is required and form text exceeds its limit.');
    }
    if (!in_array($status, ['DRAFT', 'PUBLISHED', 'ARCHIVED'], true)) {
        throw new InvalidArgumentException('Operations form status is invalid.');
    }
    $schema = $input['schema'] ?? [];
    if (!is_array($schema)) {
        throw new InvalidArgumentException('Operations form schema must be an object.');
    }
    $schemaJson = yovel_admin_operations_json(yovel_admin_normalize_operations_builder_schema($target, $schema));
    $identity = compact('target', 'title', 'description', 'status');
    $checksum = yovel_admin_operations_builder_checksum([
        'target_section' => $target,
        'form_title' => $title,
        'form_description' => $description,
        'form_status' => $status,
    ], $schemaJson);
    $requestedKey = trim((string) ($input['builder_form_key'] ?? ''));
    if ($requestedKey !== '' && !yovel_admin_is_uuid($requestedKey)) {
        throw new InvalidArgumentException('Operations form key is invalid.');
    }

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations form transaction could not start.');
    }
    try {
        $existing = $requestedKey !== '' ? $db->GetRow(
            'SELECT * FROM project_company_operations_builder_form WHERE company_key_hash = ? AND builder_form_key = ? FOR UPDATE',
            [$companyKeyHash, $requestedKey]
        ) : false;
        if ($requestedKey !== '' && !$existing) {
            throw new InvalidArgumentException('Operations form was not found for this company.');
        }
        $builderFormKey = $existing ? (string) $existing['builder_form_key'] : bx_uuid();
        $versionNumber = $existing ? (int) $existing['version_number'] : 0;
        $versionKey = $existing ? (string) $existing['current_version_key'] : '';
        if (!$existing || !hash_equals((string) $existing['schema_checksum'], $checksum)) {
            $versionNumber++;
            $versionKey = bx_uuid();
            yovel_admin_operations_db_execute($db,
                'INSERT INTO project_company_operations_builder_form_version (form_version_key,builder_form_key,company_key,company_key_hash,target_section,form_title,form_description,form_status,version_number,schema_json,schema_checksum,created_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$versionKey, $builderFormKey, $companyKey, $companyKeyHash, $target, $title, $description, $status, $versionNumber, $schemaJson, $checksum, $adminKey],
                'Operations form version persistence'
            );
        }
        if ($existing) {
            yovel_admin_operations_db_execute($db,
                'UPDATE project_company_operations_builder_form SET target_section=?,form_title=?,form_description=?,form_status=?,current_version_key=?,version_number=?,schema_json=?,schema_checksum=?,updated_by_admin_key=? WHERE company_key_hash=? AND builder_form_key=?',
                [$target, $title, $description, $status, $versionKey, $versionNumber, $schemaJson, $checksum, $adminKey, $companyKeyHash, $builderFormKey],
                'Operations form update'
            );
        } else {
            yovel_admin_operations_db_execute($db,
                'INSERT INTO project_company_operations_builder_form (builder_form_key,company_key,company_key_hash,target_section,form_title,form_description,form_status,current_version_key,version_number,schema_json,schema_checksum,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$builderFormKey, $companyKey, $companyKeyHash, $target, $title, $description, $status, $versionKey, $versionNumber, $schemaJson, $checksum, $adminKey, $adminKey],
                'Operations form persistence'
            );
        }
        $saved = $db->GetRow(
            'SELECT * FROM project_company_operations_builder_form WHERE company_key_hash=? AND builder_form_key=?',
            [$companyKeyHash, $builderFormKey]
        );
        $version = $db->GetRow(
            'SELECT * FROM project_company_operations_builder_form_version WHERE company_key_hash=? AND form_version_key=?',
            [$companyKeyHash, $versionKey]
        );
        if (!$saved || !$version
            || (string) $saved['company_key'] !== $companyKey
            || (string) $saved['target_section'] !== $target
            || (string) $saved['form_title'] !== $title
            || (string) $saved['form_description'] !== $description
            || (string) $saved['form_status'] !== $status
            || (string) $saved['current_version_key'] !== $versionKey
            || (int) $saved['version_number'] !== $versionNumber
            || (string) $saved['schema_json'] !== $schemaJson
            || (string) $saved['schema_checksum'] !== $checksum
            || (string) $version['builder_form_key'] !== $builderFormKey
            || (int) $version['version_number'] !== $versionNumber
            || (string) $version['schema_json'] !== $schemaJson
            || (string) $version['schema_checksum'] !== $checksum) {
            throw new RuntimeException('Operations form read-back verification failed.');
        }
        yovel_admin_operations_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_operations_builder_form', $builderFormKey, $companyKeyHash, (string) $adminKey, [
            ...$identity,
            'current_version_key' => $versionKey,
            'version_number' => $versionNumber,
            'schema_checksum' => $checksum,
        ], 'Company administrator saved an Operations Form Builder definition.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Operations form transaction could not commit.');
        }
        $saved['version_number'] = $versionNumber;
        $saved['schema'] = yovel_admin_operations_json_array($schemaJson, 'Operations form schema');
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_operations_form_submission(array $company, string $submissionKey): ?array
{
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_operations_form_submission WHERE company_key_hash=? AND submission_key=?',
        [$companyKeyHash, $submissionKey]
    );
    if (!$row) {
        return null;
    }
    $row['values'] = yovel_admin_operations_json_array((string) $row['values_json'], 'Operations form values');
    return $row;
}

function yovel_admin_persist_operations_form_submission(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $formKey = trim((string) ($input['builder_form_key'] ?? ''));
    $versionKey = trim((string) ($input['form_version_key'] ?? ''));
    $submissionKey = trim((string) ($input['submission_key'] ?? ''));
    $subjectKey = trim((string) ($input['subject_key'] ?? ''));
    $values = $input['values'] ?? [];
    if (!yovel_admin_is_uuid($formKey) || !yovel_admin_is_uuid($versionKey)
        || ($submissionKey !== '' && !yovel_admin_is_uuid($submissionKey))
        || $subjectKey === '' || strlen($subjectKey) > 160 || !is_array($values)) {
        throw new InvalidArgumentException('Operations form submission input is invalid.');
    }
    $valuesJson = yovel_admin_operations_json($values);
    if (strlen($valuesJson) > 500000) {
        throw new InvalidArgumentException('Operations form submission is too large.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations form submission transaction could not start.');
    }
    try {
        $form = $db->GetRow(
            'SELECT * FROM project_company_operations_builder_form WHERE company_key_hash=? AND builder_form_key=? FOR UPDATE',
            [$companyKeyHash, $formKey]
        );
        $version = $db->GetRow(
            'SELECT * FROM project_company_operations_builder_form_version WHERE company_key_hash=? AND builder_form_key=? AND form_version_key=?',
            [$companyKeyHash, $formKey, $versionKey]
        );
        if (!$form || !$version) {
            throw new InvalidArgumentException('Operations form version was not found for this company.');
        }
        $existing = $submissionKey !== '' ? $db->GetRow(
            'SELECT * FROM project_company_operations_form_submission WHERE company_key_hash=? AND submission_key=? FOR UPDATE',
            [$companyKeyHash, $submissionKey]
        ) : false;
        if ($submissionKey !== '' && !$existing) {
            throw new InvalidArgumentException('Operations form submission was not found for this company.');
        }
        if ($existing) {
            $versionKey = (string) $existing['form_version_key'];
            $formKey = (string) $existing['builder_form_key'];
            yovel_admin_operations_db_execute($db,
                'UPDATE project_company_operations_form_submission SET subject_key=?,values_json=?,updated_by_admin_key=? WHERE company_key_hash=? AND submission_key=?',
                [$subjectKey, $valuesJson, $adminKey, $companyKeyHash, $submissionKey],
                'Operations form submission update'
            );
        } else {
            $submissionKey = bx_uuid();
            yovel_admin_operations_db_execute($db,
                'INSERT INTO project_company_operations_form_submission (submission_key,builder_form_key,form_version_key,company_key,company_key_hash,subject_key,values_json,submission_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,\'SUBMITTED\',?,?)',
                [$submissionKey, $formKey, $versionKey, $companyKey, $companyKeyHash, $subjectKey, $valuesJson, $adminKey, $adminKey],
                'Operations form submission persistence'
            );
        }
        $saved = $db->GetRow(
            'SELECT * FROM project_company_operations_form_submission WHERE company_key_hash=? AND submission_key=?',
            [$companyKeyHash, $submissionKey]
        );
        if (!$saved
            || (string) $saved['company_key'] !== $companyKey
            || (string) $saved['builder_form_key'] !== $formKey
            || (string) $saved['form_version_key'] !== $versionKey
            || (string) $saved['subject_key'] !== $subjectKey
            || (string) $saved['values_json'] !== $valuesJson
            || (string) $saved['submission_status'] !== 'SUBMITTED') {
            throw new RuntimeException('Operations form submission read-back verification failed.');
        }
        yovel_admin_operations_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_operations_form_submission', $submissionKey, $companyKeyHash, (string) $adminKey, [
            'builder_form_key' => $formKey,
            'form_version_key' => $versionKey,
            'subject_key' => $subjectKey,
        ], 'Company administrator saved an Operations form submission.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Operations form submission transaction could not commit.');
        }
        $saved['values'] = yovel_admin_operations_json_array($valuesJson, 'Operations form values');
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}
