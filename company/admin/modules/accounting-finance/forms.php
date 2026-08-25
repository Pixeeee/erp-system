<?php
declare(strict_types=1);

function yovel_admin_finance_builder_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_finance_builder_form (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            builder_form_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            form_title VARCHAR(180) NOT NULL,
            form_description TEXT NULL,
            form_status ENUM('DRAFT','ACTIVE','ARCHIVED','DELETED') NOT NULL DEFAULT 'DRAFT',
            schema_json LONGTEXT NOT NULL,
            question_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_company_finance_builder_target (company_key_hash, target_section, form_status),
            INDEX idx_project_company_finance_builder_updated (company_key_hash, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_builder_form_version (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_version_key CHAR(36) NOT NULL UNIQUE,
            builder_form_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            form_title VARCHAR(180) NOT NULL,
            form_description TEXT NULL,
            form_status ENUM('DRAFT','ACTIVE','ARCHIVED') NOT NULL,
            schema_json LONGTEXT NOT NULL,
            schema_checksum CHAR(64) NOT NULL,
            question_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_builder_version_number (builder_form_key, version_number),
            UNIQUE KEY uq_project_company_finance_builder_version_checksum (builder_form_key, schema_checksum),
            INDEX idx_project_company_finance_builder_version_company (company_key_hash, builder_form_key, version_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_builder_submission (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_key CHAR(36) NOT NULL UNIQUE,
            builder_form_key CHAR(36) NOT NULL,
            form_version_key CHAR(36) NOT NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            target_section VARCHAR(80) NOT NULL,
            subject_record_key CHAR(36) NULL,
            submission_status ENUM('DRAFT','SUBMITTED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
            values_json LONGTEXT NOT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            submitted_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_company_finance_submission_form (company_key_hash, builder_form_key, form_version_key),
            INDEX idx_project_company_finance_submission_subject (company_key_hash, target_section, subject_record_key),
            INDEX idx_project_company_finance_submission_status (company_key_hash, submission_status, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Finance form builder schema update');
    }
}

function yovel_admin_finance_builder_target_sections(): array
{
    return array_filter(
        yovel_admin_accounting_finance_sections(),
        static fn (array $section): bool => (string) ($section['record_type'] ?? '') !== ''
    );
}

function yovel_admin_finance_builder_target_section(string $section): string
{
    $section = yovel_admin_slug($section);
    return array_key_exists($section, yovel_admin_finance_builder_target_sections()) ? $section : 'chart-of-accounts';
}

function yovel_admin_finance_builder_adapter(): array
{
    $targets = [];
    foreach (yovel_admin_finance_builder_target_sections() as $section => $metadata) {
        $targets[$section] = (string) ($metadata['record_type'] ?? $section);
    }
    $protected = [];
    foreach (yovel_admin_accounting_finance_default_form_schemas() as $recordType => $schema) {
        $keys = array_map('strval', $schema['requiredSystemFields'] ?? []);
        foreach ($schema['fields'] ?? [] as $field) {
            if (!empty($field['system'])) {
                $keys[] = (string) ($field['key'] ?? '');
            }
        }
        $protected[$recordType] = array_values(array_unique(array_filter($keys)));
    }

    return [
        'module' => 'accounting-finance',
        'target_record_types' => $targets,
        'protected_fields' => $protected,
        'field_types' => ['SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'CURRENCY', 'DATE', 'DROPDOWN', 'CHECKBOXES', 'ACCOUNT', 'PARTY', 'SECTION'],
        'row_column_layout' => ['version' => 2, 'max_columns' => 3, 'stable_keys' => true],
        'linked_record_types' => ['journal-entry-template' => 'journal-entry'],
        'normalize' => 'yovel_admin_normalize_finance_builder_schema',
        'version_identity' => 'yovel_admin_finance_builder_checksum',
        'renderer' => 'company/admin/modules/accounting-finance/views/form-builder.php',
    ];
}

function yovel_admin_finance_builder_question_key(string $value): string
{
    $value = trim($value);
    if ($value !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,119}$/', $value) === 1) {
        return $value;
    }

    return bx_uuid();
}

function yovel_admin_normalize_finance_builder_schema(string $schemaJson): array
{
    if (strlen($schemaJson) > 500000) {
        throw new InvalidArgumentException('Finance form layout is too large.');
    }

    $decoded = json_decode($schemaJson !== '' ? $schemaJson : '{}', true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Finance form layout must be valid JSON.');
    }

    $questions = $decoded['questions'] ?? [];
    if (!is_array($questions)) {
        throw new InvalidArgumentException('Finance form questions must be a list.');
    }
    if (count($questions) > 80) {
        throw new InvalidArgumentException('A Finance form can contain up to 80 fields.');
    }

    $allowedTypes = yovel_admin_finance_builder_adapter()['field_types'];
    $sourceRows = $decoded['rows'] ?? [];
    if (!is_array($sourceRows)) {
        throw new InvalidArgumentException('Finance form rows must be a list.');
    }
    if (count($sourceRows) > 30) {
        throw new InvalidArgumentException('A Finance form can contain up to 30 rows.');
    }

    $rows = [];
    $columnKeys = [];
    foreach (array_values($sourceRows) as $rowIndex => $sourceRow) {
        if (!is_array($sourceRow)) {
            continue;
        }
        $rowKey = yovel_admin_finance_builder_question_key((string) ($sourceRow['key'] ?? ''));
        $sourceColumns = $sourceRow['columns'] ?? [];
        if (!is_array($sourceColumns) || $sourceColumns === []) {
            $sourceColumns = [['key' => $rowKey . '-column', 'width' => 12]];
        }
        if (count($sourceColumns) > 3) {
            throw new InvalidArgumentException('A Finance form row can contain up to three columns.');
        }
        $columns = [];
        foreach (array_values($sourceColumns) as $columnIndex => $sourceColumn) {
            if (!is_array($sourceColumn)) {
                continue;
            }
            $columnKey = yovel_admin_finance_builder_question_key((string) ($sourceColumn['key'] ?? ''));
            if (isset($columnKeys[$columnKey])) {
                throw new InvalidArgumentException('Finance form columns require unique stable keys.');
            }
            $columnKeys[$columnKey] = $rowKey;
            $columns[] = [
                'key' => $columnKey,
                'width' => max(1, min(12, (int) ($sourceColumn['width'] ?? 12))),
                'order' => ($columnIndex + 1) * 10,
            ];
        }
        if ($columns === []) {
            $columnKey = $rowKey . '-column';
            $columnKeys[$columnKey] = $rowKey;
            $columns[] = ['key' => $columnKey, 'width' => 12, 'order' => 10];
        }
        $rows[] = ['key' => $rowKey, 'order' => ($rowIndex + 1) * 10, 'columns' => $columns];
    }
    if ($rows === []) {
        $rows = [['key' => 'default-row', 'order' => 10, 'columns' => [['key' => 'default-column', 'width' => 12, 'order' => 10]]]];
        $columnKeys = ['default-column' => 'default-row'];
    }
    $normalized = [];
    $usedKeys = [];
    foreach (array_values($questions) as $index => $question) {
        if (!is_array($question)) {
            continue;
        }

        $type = strtoupper(trim((string) ($question['type'] ?? 'SHORT_TEXT')));
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'SHORT_TEXT';
        }
        $label = trim((string) ($question['label'] ?? ''));
        if ($label === '') {
            $label = $type === 'SECTION' ? 'Section title' : 'Untitled field';
        }
        if (strlen($label) > 180) {
            throw new InvalidArgumentException('Finance field labels must be 180 characters or fewer.');
        }
        $help = trim((string) ($question['help'] ?? ''));
        if (strlen($help) > 1000) {
            throw new InvalidArgumentException('Finance field help text must be 1000 characters or fewer.');
        }

        $key = yovel_admin_finance_builder_question_key((string) ($question['key'] ?? ''));
        if (isset($usedKeys[$key])) {
            throw new InvalidArgumentException('Finance form fields require unique stable keys.');
        }
        $usedKeys[$key] = true;

        $options = $question['options'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }
        $options = array_values(array_filter(array_map(static fn ($option): string => trim((string) $option), $options), static fn (string $option): bool => $option !== ''));
        if (count($options) > 30) {
            throw new InvalidArgumentException('A Finance dropdown can contain up to 30 options.');
        }
        foreach ($options as $option) {
            if (strlen($option) > 160) {
                throw new InvalidArgumentException('Finance field options must be 160 characters or fewer.');
            }
        }
        if (!in_array($type, ['DROPDOWN', 'CHECKBOXES'], true)) {
            $options = [];
        }

        $precision = (int) ($question['precision'] ?? 2);
        $precision = max(0, min(6, $precision));
        if (!in_array($type, ['NUMBER', 'CURRENCY'], true)) {
            $precision = 0;
        }

        $columnKey = trim((string) ($question['column_key'] ?? ''));
        if (!isset($columnKeys[$columnKey])) {
            $columnKey = (string) $rows[0]['columns'][0]['key'];
        }
        $rowKey = $columnKeys[$columnKey];
        $default = $question['default'] ?? '';
        if (is_array($default)) {
            $default = array_values(array_map('strval', array_slice($default, 0, 30)));
        } else {
            $default = substr((string) $default, 0, 2000);
        }
        $sourceValidation = is_array($question['validation'] ?? null) ? $question['validation'] : [];
        $validation = [];
        foreach (['min', 'max', 'min_length', 'max_length', 'pattern'] as $validationKey) {
            if (array_key_exists($validationKey, $sourceValidation) && is_scalar($sourceValidation[$validationKey])) {
                $validation[$validationKey] = substr(trim((string) $sourceValidation[$validationKey]), 0, 240);
            }
        }

        $normalized[] = [
            'key' => $key,
            'label' => $label,
            'help' => $help,
            'type' => $type,
            'required' => !empty($question['required']) && $type !== 'SECTION',
            'visible' => !array_key_exists('visible', $question) || filter_var($question['visible'], FILTER_VALIDATE_BOOLEAN),
            'options' => $options,
            'precision' => $precision,
            'default' => $default,
            'validation' => $validation,
            'row_key' => $rowKey,
            'column_key' => $columnKey,
            'order' => ($index + 1) * 10,
        ];
    }

    return [
        'version' => 2,
        'rows' => $rows,
        'questions' => $normalized,
    ];
}

function yovel_admin_finance_builder_checksum(array $form, string $schemaJson): string
{
    $canonical = json_encode([
        'target_section' => (string) ($form['target_section'] ?? ''),
        'form_title' => (string) ($form['form_title'] ?? ''),
        'form_description' => (string) ($form['form_description'] ?? ''),
        'form_status' => (string) ($form['form_status'] ?? 'DRAFT'),
        'schema_json' => $schemaJson,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    return hash('sha256', $canonical);
}

function yovel_admin_finance_builder_forms(array $company): array
{
    yovel_admin_finance_builder_schema();

    $rows = bx_db()->GetAll(
        "SELECT
            form_record.*,
            COALESCE(version_record.form_version_key, '') AS current_form_version_key,
            COALESCE(version_record.version_number, 0) AS current_version_number
         FROM project_company_finance_builder_form form_record
         LEFT JOIN project_company_finance_builder_form_version version_record
           ON version_record.builder_form_key = form_record.builder_form_key
          AND version_record.version_number = (
              SELECT MAX(latest_version.version_number)
              FROM project_company_finance_builder_form_version latest_version
              WHERE latest_version.builder_form_key = form_record.builder_form_key
          )
         WHERE form_record.company_key_hash = ? AND form_record.form_status <> 'DELETED'
         ORDER BY form_record.target_section, form_record.updated_at DESC, form_record.form_title",
        [(string) $company['company_key_hash']]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_finance_builder_form_version(array $company, string $builderFormKey, string $versionKey = ''): ?array
{
    if (!yovel_admin_is_uuid($builderFormKey)) {
        return null;
    }

    if ($versionKey !== '' && yovel_admin_is_uuid($versionKey)) {
        $row = bx_db()->GetRow(
            'SELECT * FROM project_company_finance_builder_form_version WHERE company_key_hash = ? AND builder_form_key = ? AND form_version_key = ? LIMIT 1',
            [(string) $company['company_key_hash'], $builderFormKey, $versionKey]
        );
    } else {
        $row = bx_db()->GetRow(
            'SELECT * FROM project_company_finance_builder_form_version WHERE company_key_hash = ? AND builder_form_key = ? ORDER BY version_number DESC LIMIT 1',
            [(string) $company['company_key_hash'], $builderFormKey]
        );
    }

    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_finance_builder_form_versions(array $company, string $builderFormKey): array
{
    if (!yovel_admin_is_uuid($builderFormKey)) {
        return [];
    }
    $rows = bx_db()->GetAll(
        'SELECT * FROM project_company_finance_builder_form_version WHERE company_key_hash = ? AND builder_form_key = ? ORDER BY version_number DESC',
        [(string) ($company['company_key_hash'] ?? ''), $builderFormKey]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_finance_builtin_sections(array $schema): array
{
    $sectionLabels = [];
    foreach (($schema['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }
        $key = yovel_admin_slug((string) ($section['key'] ?? ''));
        if ($key !== '') {
            $sectionLabels[$key] = trim((string) ($section['label'] ?? ''));
        }
    }

    $counts = [];
    foreach (($schema['fields'] ?? []) as $field) {
        if (!is_array($field) || !filter_var($field['visible'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            continue;
        }
        $sectionKey = yovel_admin_slug((string) ($field['section'] ?? 'overview')) ?: 'overview';
        $counts[$sectionKey] = ($counts[$sectionKey] ?? 0) + 1;
    }

    $sections = [];
    foreach ($counts as $sectionKey => $fieldCount) {
        $fallback = ucwords(str_replace('-', ' ', $sectionKey));
        $sectionLabel = trim((string) ($sectionLabels[$sectionKey] ?? ''));
        $sections[$sectionKey] = [
            'label' => $sectionLabel !== '' ? $sectionLabel : $fallback,
            'description' => 'Live fields used by this Finance form section.',
            'field_count' => $fieldCount,
        ];
    }

    return $sections;
}

function yovel_admin_finance_builder_insert_version(ADOConnection $db, array $form, string $adminKey): array
{
    $builderFormKey = (string) $form['builder_form_key'];
    $schemaJson = (string) $form['schema_json'];
    $checksum = yovel_admin_finance_builder_checksum($form, $schemaJson);
    $existing = $db->GetRow(
        'SELECT * FROM project_company_finance_builder_form_version WHERE builder_form_key = ? AND schema_checksum = ? LIMIT 1',
        [$builderFormKey, $checksum]
    );
    if (is_array($existing) && $existing !== []) {
        return $existing;
    }

    $versionNumber = (int) $db->GetOne(
        'SELECT COALESCE(MAX(version_number), 0) + 1 FROM project_company_finance_builder_form_version WHERE builder_form_key = ?',
        [$builderFormKey]
    );
    $versionKey = bx_uuid();
    yovel_admin_db_execute(
        $db,
        "INSERT INTO project_company_finance_builder_form_version (
            form_version_key, builder_form_key, company_key, company_key_hash, version_number,
            target_section, form_title, form_description, form_status, schema_json,
            schema_checksum, question_count, created_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $versionKey,
            $builderFormKey,
            (string) $form['company_key'],
            (string) $form['company_key_hash'],
            $versionNumber,
            (string) $form['target_section'],
            (string) $form['form_title'],
            (string) $form['form_description'],
            (string) $form['form_status'],
            $schemaJson,
            $checksum,
            (int) $form['question_count'],
            $adminKey !== '' ? $adminKey : null,
        ],
        'Finance builder form version create'
    );

    $readBack = $db->GetRow(
        'SELECT * FROM project_company_finance_builder_form_version WHERE form_version_key = ? AND builder_form_key = ? LIMIT 1',
        [$versionKey, $builderFormKey]
    );
    foreach ([
        'form_version_key' => $versionKey,
        'builder_form_key' => $builderFormKey,
        'company_key' => (string) $form['company_key'],
        'company_key_hash' => (string) $form['company_key_hash'],
        'version_number' => (string) $versionNumber,
        'target_section' => (string) $form['target_section'],
        'form_title' => (string) $form['form_title'],
        'form_description' => (string) $form['form_description'],
        'form_status' => (string) $form['form_status'],
        'schema_json' => $schemaJson,
        'schema_checksum' => $checksum,
        'question_count' => (string) (int) $form['question_count'],
    ] as $column => $expected) {
        if (!is_array($readBack) || (string) ($readBack[$column] ?? '') !== $expected) {
            throw new RuntimeException('Finance builder form version read-back verification failed for ' . $column . '.');
        }
    }

    bx_audit('CREATE', 'project_company_finance_builder_form_version', $versionKey, [
        'builder_form_key' => $builderFormKey,
        'company_key' => (string) $form['company_key'],
        'version_number' => $versionNumber,
        'schema_checksum' => $checksum,
    ], 'Created an immutable Finance builder form version.');

    return $readBack;
}

function yovel_admin_persist_finance_builder_form(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_finance_builder_schema();

    $companyKey = (string) ($company['company_key'] ?? '');
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $adminKey = (string) ($admin['admin_key'] ?? '');
    if ($companyKey === '' || strlen($companyKey) > 36 || $companyKeyHash === '' || strlen($companyKeyHash) > 64 || $adminKey === '' || strlen($adminKey) > 36) {
        throw new InvalidArgumentException('Finance builder company administrator scope is invalid.');
    }

    $builderFormKey = trim((string) ($input['builder_form_key'] ?? ''));
    if ($builderFormKey !== '' && !yovel_admin_is_uuid($builderFormKey)) {
        throw new InvalidArgumentException('Finance builder form key is invalid.');
    }
    if ($builderFormKey === '') {
        $builderFormKey = bx_uuid();
    }

    $targetSection = yovel_admin_slug((string) ($input['target_section'] ?? ''));
    if (!array_key_exists($targetSection, yovel_admin_finance_builder_target_sections())) {
        throw new InvalidArgumentException('Finance builder target is invalid.');
    }
    $formTitle = trim((string) ($input['form_title'] ?? ''));
    if ($formTitle === '') {
        $formTitle = 'Untitled Finance Form';
    }
    if (strlen($formTitle) > 180) {
        throw new InvalidArgumentException('Finance form title must be 180 characters or fewer.');
    }
    $formDescription = trim((string) ($input['form_description'] ?? ''));
    if (strlen($formDescription) > 2000) {
        throw new InvalidArgumentException('Finance form description must be 2000 characters or fewer.');
    }
    $formStatus = strtoupper(trim((string) ($input['form_status'] ?? 'DRAFT')));
    if (!in_array($formStatus, ['DRAFT', 'ACTIVE', 'ARCHIVED', 'DELETED'], true)) {
        throw new InvalidArgumentException('Finance form status is invalid.');
    }

    $schema = yovel_admin_normalize_finance_builder_schema((string) ($input['schema_json'] ?? '{}'));
    $schemaJson = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $questionCount = count($schema['questions']);

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance builder form transaction could not start.');
    }
    try {
        $existing = $db->GetRow(
            'SELECT * FROM project_company_finance_builder_form WHERE company_key_hash = ? AND builder_form_key = ? FOR UPDATE',
            [$companyKeyHash, $builderFormKey]
        );

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_finance_builder_form (
                builder_form_key, company_key, company_key_hash, target_section, form_title,
                form_description, form_status, schema_json, question_count,
                created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                target_section = VALUES(target_section),
                form_title = VALUES(form_title),
                form_description = VALUES(form_description),
                form_status = VALUES(form_status),
                schema_json = VALUES(schema_json),
                question_count = VALUES(question_count),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $builderFormKey,
                $companyKey,
                $companyKeyHash,
                $targetSection,
                $formTitle,
                $formDescription,
                $formStatus,
                $schemaJson,
                $questionCount,
                $adminKey,
                $adminKey,
            ],
            'Finance builder form save'
        );

        $version = null;
        if ($formStatus !== 'DELETED') {
            $version = yovel_admin_finance_builder_insert_version($db, [
                'builder_form_key' => $builderFormKey,
                'company_key' => $companyKey,
                'company_key_hash' => $companyKeyHash,
                'target_section' => $targetSection,
                'form_title' => $formTitle,
                'form_description' => $formDescription,
                'form_status' => $formStatus,
                'schema_json' => $schemaJson,
                'question_count' => $questionCount,
            ], $adminKey);
        }

        $saved = $db->GetRow(
            'SELECT * FROM project_company_finance_builder_form WHERE company_key_hash = ? AND builder_form_key = ? LIMIT 1',
            [$companyKeyHash, $builderFormKey]
        );
        foreach ([
            'builder_form_key' => $builderFormKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'target_section' => $targetSection,
            'form_title' => $formTitle,
            'form_description' => $formDescription,
            'form_status' => $formStatus,
            'schema_json' => $schemaJson,
            'question_count' => (string) $questionCount,
        ] as $column => $expected) {
            if (!is_array($saved) || (string) ($saved[$column] ?? '') !== $expected) {
                throw new RuntimeException('Finance builder form read-back verification failed for ' . $column . '.');
            }
        }

        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_finance_builder_form', $builderFormKey, [
            'company_key' => $companyKey,
            'company_name' => (string) ($company['company_name'] ?? ''),
            'target_section' => $targetSection,
            'form_title' => $formTitle,
            'form_status' => $formStatus,
            'question_count' => $questionCount,
            'form_version_key' => (string) ($version['form_version_key'] ?? ''),
            'version_number' => (int) ($version['version_number'] ?? 0),
            'admin_key' => $adminKey,
        ], $existing ? 'Company admin updated a Finance builder form.' : 'Company admin created a Finance builder form.');

        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance builder form transaction could not commit.');
        }

        $saved['current_form_version_key'] = (string) ($version['form_version_key'] ?? '');
        $saved['current_version_number'] = (int) ($version['version_number'] ?? 0);
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_persist_finance_builder_submission(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_finance_builder_schema();

    $companyKey = (string) ($company['company_key'] ?? '');
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $adminKey = (string) ($admin['admin_key'] ?? '');
    if ($companyKey === '' || $companyKeyHash === '' || $adminKey === '') {
        throw new InvalidArgumentException('Finance form submission administrator scope is invalid.');
    }

    $submissionKey = trim((string) ($input['submission_key'] ?? ''));
    if ($submissionKey !== '' && !yovel_admin_is_uuid($submissionKey)) {
        throw new InvalidArgumentException('Finance form submission key is invalid.');
    }
    if ($submissionKey === '') {
        $submissionKey = bx_uuid();
    }
    $builderFormKey = trim((string) ($input['builder_form_key'] ?? ''));
    $versionKey = trim((string) ($input['form_version_key'] ?? ''));
    if (!yovel_admin_is_uuid($builderFormKey) || !yovel_admin_is_uuid($versionKey)) {
        throw new InvalidArgumentException('Finance form and version are required.');
    }
    $status = strtoupper(trim((string) ($input['submission_status'] ?? 'DRAFT')));
    if (!in_array($status, ['DRAFT', 'SUBMITTED', 'ARCHIVED'], true)) {
        throw new InvalidArgumentException('Finance form submission status is invalid.');
    }
    $subjectKey = trim((string) ($input['subject_record_key'] ?? ''));
    if ($subjectKey !== '' && !yovel_admin_is_uuid($subjectKey)) {
        throw new InvalidArgumentException('Finance form submission subject is invalid.');
    }
    $values = $input['values'] ?? [];
    if (!is_array($values)) {
        throw new InvalidArgumentException('Finance form submission values must be an object.');
    }

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance form submission transaction could not start.');
    }
    try {
        $form = $db->GetRow(
            'SELECT * FROM project_company_finance_builder_form WHERE company_key_hash = ? AND builder_form_key = ? FOR UPDATE',
            [$companyKeyHash, $builderFormKey]
        );
        $version = $db->GetRow(
            'SELECT * FROM project_company_finance_builder_form_version WHERE company_key_hash = ? AND builder_form_key = ? AND form_version_key = ? LIMIT 1',
            [$companyKeyHash, $builderFormKey, $versionKey]
        );
        if (!is_array($form) || $form === [] || !is_array($version) || $version === []) {
            throw new InvalidArgumentException('Finance form version was not found for this company.');
        }
        if ($status === 'SUBMITTED' && (string) ($version['form_status'] ?? '') !== 'ACTIVE') {
            throw new InvalidArgumentException('Only a published Finance form version can accept submissions.');
        }

        $schema = yovel_admin_normalize_finance_builder_schema((string) $version['schema_json']);
        $questions = [];
        foreach ($schema['questions'] as $question) {
            if (($question['type'] ?? '') !== 'SECTION') {
                $questions[(string) $question['key']] = $question;
            }
        }
        foreach (array_keys($values) as $fieldKey) {
            if (!isset($questions[(string) $fieldKey])) {
                throw new InvalidArgumentException('Finance form submission contains an unknown field.');
            }
        }
        $normalizedValues = [];
        foreach ($questions as $fieldKey => $question) {
            $value = $values[$fieldKey] ?? ($question['default'] ?? '');
            if (is_array($value)) {
                $value = array_values(array_map(static fn (mixed $item): string => substr(trim((string) $item), 0, 2000), array_slice($value, 0, 30)));
                $isEmpty = $value === [];
            } else {
                $value = substr(trim((string) $value), 0, 20000);
                $isEmpty = $value === '';
            }
            if ($status === 'SUBMITTED' && !empty($question['required']) && $isEmpty) {
                throw new InvalidArgumentException((string) $question['label'] . ' is required.');
            }
            $normalizedValues[$fieldKey] = $value;
        }
        $valuesJson = json_encode($normalizedValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $existing = $db->GetRow(
            'SELECT * FROM project_company_finance_builder_submission WHERE company_key_hash = ? AND submission_key = ? FOR UPDATE',
            [$companyKeyHash, $submissionKey]
        );
        if (is_array($existing) && $existing !== [] && in_array((string) $existing['submission_status'], ['SUBMITTED', 'ARCHIVED'], true)) {
            throw new InvalidArgumentException('Submitted Finance form records are immutable.');
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_finance_builder_submission (
                submission_key, builder_form_key, form_version_key, company_key, company_key_hash,
                target_section, subject_record_key, submission_status, values_json,
                created_by_admin_key, updated_by_admin_key, submitted_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                subject_record_key = VALUES(subject_record_key),
                submission_status = VALUES(submission_status),
                values_json = VALUES(values_json),
                updated_by_admin_key = VALUES(updated_by_admin_key),
                submitted_at = VALUES(submitted_at)",
            [
                $submissionKey,
                $builderFormKey,
                $versionKey,
                $companyKey,
                $companyKeyHash,
                (string) $version['target_section'],
                $subjectKey !== '' ? $subjectKey : null,
                $status,
                $valuesJson,
                $adminKey,
                $adminKey,
                $status === 'SUBMITTED' ? date('Y-m-d H:i:s') : null,
            ],
            'Finance builder submission save'
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_finance_builder_submission WHERE company_key_hash = ? AND submission_key = ? LIMIT 1',
            [$companyKeyHash, $submissionKey]
        );
        foreach ([
            'submission_key' => $submissionKey,
            'builder_form_key' => $builderFormKey,
            'form_version_key' => $versionKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'target_section' => (string) $version['target_section'],
            'submission_status' => $status,
            'values_json' => $valuesJson,
        ] as $column => $expected) {
            if (!is_array($saved) || (string) ($saved[$column] ?? '') !== $expected) {
                throw new RuntimeException('Finance form submission read-back verification failed for ' . $column . '.');
            }
        }

        bx_audit($existing ? 'UPDATE' : $status, 'project_company_finance_builder_submission', $submissionKey, [
            'company_key' => $companyKey,
            'builder_form_key' => $builderFormKey,
            'form_version_key' => $versionKey,
            'target_section' => (string) $version['target_section'],
            'submission_status' => $status,
            'admin_key' => $adminKey,
        ], 'Saved a version-bound Finance form submission.');

        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance form submission transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_save_finance_builder_form(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'save_finance_builder_form', static function () use ($company, $admin): string {
        $saved = yovel_admin_persist_finance_builder_form(bx_db(), $company, $admin, [
            'builder_form_key' => (string) ($_POST['builder_form_key'] ?? ''),
            'target_section' => (string) ($_POST['target_section'] ?? ''),
            'form_title' => (string) ($_POST['form_title'] ?? ''),
            'form_description' => (string) ($_POST['form_description'] ?? ''),
            'form_status' => (string) ($_POST['form_status'] ?? 'DRAFT'),
            'schema_json' => (string) ($_POST['schema_json'] ?? '{}'),
        ]);
        $GLOBALS['yovel_admin_saved_finance_builder_form_key'] = (string) $saved['builder_form_key'];
        return 'Finance form saved.';
    });
}
