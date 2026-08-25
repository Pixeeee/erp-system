<?php
declare(strict_types=1);

function yovel_admin_hr_form_key(string $formKey): string
{
    $formKey = yovel_admin_slug($formKey);
    return array_key_exists($formKey, yovel_admin_hr_default_form_fields()) ? $formKey : 'employee-profiles';
}

function yovel_admin_hr_form_section_meta(string $formKey, array $fields): array
{
    $formKey = yovel_admin_hr_form_key($formKey);
    $knownSections = [
        'overview' => ['label' => 'Overview', 'description' => 'Identity, status, and company assignment fields.'],
        'joining' => ['label' => 'Joining', 'description' => 'Joining dates, employment type, confirmation, and contract details.'],
        'address-contacts' => ['label' => 'Address & Contacts', 'description' => 'Phone, email, address, and emergency contact fields.'],
        'attendance-leaves' => ['label' => 'Attendance & Leaves', 'description' => 'Attendance device and leave-related defaults.'],
        'salary' => ['label' => 'Salary', 'description' => 'Salary mode, compensation, currency, and bank details.'],
        'personal' => ['label' => 'Personal', 'description' => 'Birth date, identity, family, and health information.'],
        'profile' => ['label' => 'Profile', 'description' => 'Notes, education, and previous work experience.'],
        'exit' => ['label' => 'Exit', 'description' => 'Resignation, relieving, interview, and departure details.'],
        'details' => ['label' => 'Details', 'description' => 'Description, classification, and supporting record details.'],
    ];
    $preferredOrder = $formKey === 'employee-profiles'
        ? ['overview', 'joining', 'address-contacts', 'attendance-leaves', 'salary', 'personal', 'profile', 'exit']
        : ['overview', 'details'];
    $counts = [];
    foreach ($fields as $field) {
        $sectionKey = yovel_admin_slug((string) ($field['field_section'] ?? 'overview')) ?: 'overview';
        $counts[$sectionKey] = ($counts[$sectionKey] ?? 0) + 1;
    }

    $sectionKeys = array_values(array_unique(array_merge($preferredOrder, array_keys($counts))));
    $sections = [];
    foreach ($sectionKeys as $sectionKey) {
        if (!isset($counts[$sectionKey])) {
            continue;
        }
        $fallbackLabel = ucwords(str_replace(['-', '_'], ' ', $sectionKey));
        $sections[$sectionKey] = [
            'label' => (string) ($knownSections[$sectionKey]['label'] ?? $fallbackLabel),
            'description' => (string) ($knownSections[$sectionKey]['description'] ?? ('Fields used by the ' . $fallbackLabel . ' form section.')),
            'field_count' => (int) $counts[$sectionKey],
        ];
    }

    return $sections;
}

function yovel_admin_hr_field_name(string $value): string
{
    $fieldName = strtolower(trim((string) preg_replace('/[^A-Za-z0-9_]+/', '_', $value), '_'));
    if ($fieldName === '') {
        return '';
    }
    if (!preg_match('/^[a-z][a-z0-9_]{1,79}$/', $fieldName)) {
        return '';
    }

    return $fieldName;
}

function yovel_admin_hr_builder_target_sections(): array
{
    $sections = yovel_admin_hr_sections();
    unset($sections['dashboard']);

    return $sections;
}

function yovel_admin_hr_builder_target_section(string $section): string
{
    $section = yovel_admin_slug($section);
    return array_key_exists($section, yovel_admin_hr_builder_target_sections()) ? $section : 'employee-profiles';
}

function yovel_admin_hr_builder_question_key(string $value): string
{
    $value = trim($value);
    if ($value !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,119}$/', $value) === 1) {
        return $value;
    }

    return bx_uuid();
}

function yovel_admin_normalize_hr_builder_schema(string $schemaJson): array
{
    $decoded = json_decode($schemaJson !== '' ? $schemaJson : '{}', true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Form builder layout must be valid JSON.');
    }

    $allowedTypes = ['SHORT_TEXT', 'PARAGRAPH', 'DROPDOWN', 'CHECKBOXES', 'DATE', 'NUMBER', 'EMAIL', 'PHONE', 'SECTION'];
    $questions = $decoded['questions'] ?? [];
    if (!is_array($questions)) {
        $questions = [];
    }
    if (count($questions) > 60) {
        throw new InvalidArgumentException('A form can contain up to 60 questions.');
    }

    $normalizedQuestions = [];
    $usedQuestionKeys = [];
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
            $label = $type === 'SECTION' ? 'Section title' : 'Untitled question';
        }
        if (strlen($label) > 180) {
            throw new InvalidArgumentException('Question labels must be 180 characters or fewer.');
        }
        $help = trim((string) ($question['help'] ?? ''));
        if (strlen($help) > 1000) {
            throw new InvalidArgumentException('Question help text must be 1000 characters or fewer.');
        }
        $options = $question['options'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }
        $options = array_values(array_filter(array_map(static function ($option): string {
            return trim((string) $option);
        }, $options), static fn (string $option): bool => $option !== ''));
        if (count($options) > 30) {
            throw new InvalidArgumentException('A question can contain up to 30 options.');
        }
        foreach ($options as $option) {
            if (strlen($option) > 160) {
                throw new InvalidArgumentException('Question options must be 160 characters or fewer.');
            }
        }
        if (!in_array($type, ['DROPDOWN', 'CHECKBOXES'], true)) {
            $options = [];
        }

        $questionKey = yovel_admin_hr_builder_question_key((string) ($question['key'] ?? ''));
        while (isset($usedQuestionKeys[$questionKey])) {
            $questionKey = bx_uuid();
        }
        $usedQuestionKeys[$questionKey] = true;

        $normalizedQuestions[] = [
            'key' => $questionKey,
            'label' => $label,
            'help' => $help,
            'type' => $type,
            'required' => !empty($question['required']) && $type !== 'SECTION',
            'options' => $options,
            'order' => ($index + 1) * 10,
        ];
    }

    $questionsByKey = [];
    foreach ($normalizedQuestions as $question) {
        $questionsByKey[(string) $question['key']] = $question;
    }

    $sourceRows = $decoded['rows'] ?? null;
    if (!is_array($sourceRows)) {
        $sourceRows = [];
        foreach ($normalizedQuestions as $question) {
            $sourceRows[] = [
                'key' => bx_uuid(),
                'columns' => [[
                    'key' => bx_uuid(),
                    'question_keys' => [(string) $question['key']],
                ]],
            ];
        }
    }
    if (count($sourceRows) > 60) {
        throw new InvalidArgumentException('A form layout can contain up to 60 rows.');
    }

    $normalizedRows = [];
    $placedQuestionKeys = [];
    $usedRowKeys = [];
    $usedColumnKeys = [];
    foreach (array_values($sourceRows) as $sourceRow) {
        if (!is_array($sourceRow)) {
            continue;
        }

        $rowKey = yovel_admin_hr_builder_question_key((string) ($sourceRow['key'] ?? ''));
        while (isset($usedRowKeys[$rowKey])) {
            $rowKey = bx_uuid();
        }
        $usedRowKeys[$rowKey] = true;

        $sourceColumns = $sourceRow['columns'] ?? [];
        if (!is_array($sourceColumns)) {
            $sourceColumns = [];
        }
        $sourceColumns = array_slice(array_values($sourceColumns), 0, 3);
        if ($sourceColumns === []) {
            $sourceColumns = [['key' => '', 'question_keys' => []]];
        }

        $normalizedColumns = [];
        $sectionQuestionKeys = [];
        $rowHasNonSectionQuestion = false;
        foreach ($sourceColumns as $sourceColumn) {
            if (!is_array($sourceColumn)) {
                $sourceColumn = [];
            }
            $columnKey = yovel_admin_hr_builder_question_key((string) ($sourceColumn['key'] ?? ''));
            while (isset($usedColumnKeys[$columnKey])) {
                $columnKey = bx_uuid();
            }
            $usedColumnKeys[$columnKey] = true;

            $columnQuestionKeys = $sourceColumn['question_keys'] ?? [];
            if (!is_array($columnQuestionKeys)) {
                $columnQuestionKeys = [];
            }
            $normalizedColumnQuestionKeys = [];
            foreach ($columnQuestionKeys as $questionKeyValue) {
                $questionKey = trim((string) $questionKeyValue);
                if ($questionKey === '' || !isset($questionsByKey[$questionKey]) || isset($placedQuestionKeys[$questionKey])) {
                    continue;
                }
                $placedQuestionKeys[$questionKey] = true;
                if ((string) $questionsByKey[$questionKey]['type'] === 'SECTION') {
                    $sectionQuestionKeys[] = $questionKey;
                    continue;
                }
                $normalizedColumnQuestionKeys[] = $questionKey;
                $rowHasNonSectionQuestion = true;
            }
            $normalizedColumns[] = [
                'key' => $columnKey,
                'question_keys' => $normalizedColumnQuestionKeys,
            ];
        }

        if ($rowHasNonSectionQuestion || $sectionQuestionKeys === []) {
            $normalizedRows[] = [
                'key' => $rowKey,
                'columns' => $normalizedColumns,
            ];
        }
        foreach ($sectionQuestionKeys as $sectionIndex => $sectionQuestionKey) {
            $sectionRowKey = $rowHasNonSectionQuestion || count($sectionQuestionKeys) > 1
                ? yovel_admin_hr_builder_question_key($rowKey . '-section-' . ($sectionIndex + 1))
                : $rowKey;
            while (isset($usedRowKeys[$sectionRowKey]) && $sectionRowKey !== $rowKey) {
                $sectionRowKey = bx_uuid();
            }
            $usedRowKeys[$sectionRowKey] = true;
            $sectionColumnKey = !$rowHasNonSectionQuestion && count($sectionQuestionKeys) === 1
                ? (string) $normalizedColumns[0]['key']
                : bx_uuid();
            $usedColumnKeys[$sectionColumnKey] = true;
            $normalizedRows[] = [
                'key' => $sectionRowKey,
                'columns' => [[
                    'key' => $sectionColumnKey,
                    'question_keys' => [$sectionQuestionKey],
                ]],
            ];
        }
    }

    foreach ($normalizedQuestions as $question) {
        $questionKey = (string) $question['key'];
        if (isset($placedQuestionKeys[$questionKey])) {
            continue;
        }
        $placedQuestionKeys[$questionKey] = true;
        $rowKey = bx_uuid();
        $columnKey = bx_uuid();
        $usedRowKeys[$rowKey] = true;
        $usedColumnKeys[$columnKey] = true;
        $normalizedRows[] = [
            'key' => $rowKey,
            'columns' => [[
                'key' => $columnKey,
                'question_keys' => [$questionKey],
            ]],
        ];
    }
    if (count($normalizedRows) > 60) {
        throw new InvalidArgumentException('A form layout can contain up to 60 rows.');
    }

    $orderedQuestions = [];
    foreach ($normalizedRows as $row) {
        foreach ($row['columns'] as $column) {
            foreach ($column['question_keys'] as $questionKey) {
                if (isset($questionsByKey[$questionKey])) {
                    $orderedQuestions[] = $questionsByKey[$questionKey];
                }
            }
        }
    }
    foreach ($orderedQuestions as $index => &$question) {
        $question['order'] = ($index + 1) * 10;
    }
    unset($question);

    return [
        'version' => 2,
        'questions' => $orderedQuestions,
        'rows' => $normalizedRows,
    ];
}

function yovel_admin_hr_builder_forms(array $company): array
{
    yovel_admin_hr_schema();

    $rows = bx_db()->GetAll(
        "SELECT
            form_record.*,
            COALESCE(version_record.form_version_key, '') AS current_form_version_key,
            COALESCE(version_record.version_number, 0) AS current_version_number
        FROM project_company_hr_builder_form form_record
        LEFT JOIN project_company_hr_builder_form_version version_record
            ON version_record.builder_form_key = form_record.builder_form_key
           AND version_record.version_number = (
                SELECT MAX(latest_version.version_number)
                FROM project_company_hr_builder_form_version latest_version
                WHERE latest_version.builder_form_key = form_record.builder_form_key
           )
        WHERE form_record.company_key_hash = ? AND form_record.form_status <> 'DELETED'
        ORDER BY form_record.target_section ASC, form_record.updated_at DESC, form_record.form_title ASC",
        [(string) $company['company_key_hash']]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_hr_form_fields(array $company, string $formKey, ?array $admin = null): array
{
    yovel_admin_seed_hr_form_fields($company, $admin);

    $rows = bx_db()->GetAll(
        "SELECT *
        FROM project_company_hr_form_field
        WHERE company_key_hash = ? AND form_key = ? AND field_status = 'ACTIVE'
        ORDER BY field_section ASC, sort_order ASC, field_label ASC",
        [(string) $company['company_key_hash'], yovel_admin_hr_form_key($formKey)]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_hr_form_field_by_name(array $fields, string $fieldName): ?array
{
    foreach ($fields as $field) {
        if ((string) ($field['field_name'] ?? '') === $fieldName) {
            return $field;
        }
    }

    return null;
}

function yovel_admin_hr_custom_fields(array $fields, string $section = ''): array
{
    return array_values(array_filter($fields, static function (array $field) use ($section): bool {
        if ((int) ($field['is_core'] ?? 0) === 1 || (int) ($field['is_visible'] ?? 1) !== 1) {
            return false;
        }
        return $section === '' || (string) ($field['field_section'] ?? '') === $section;
    }));
}

function yovel_admin_hr_custom_values(array $company, string $formKey, string $recordKey): array
{
    if (!yovel_admin_is_uuid($recordKey)) {
        return [];
    }

    $rows = bx_db()->GetAll(
        'SELECT field_name, field_value FROM project_company_hr_custom_value WHERE company_key_hash = ? AND form_key = ? AND record_key = ?',
        [(string) $company['company_key_hash'], yovel_admin_hr_form_key($formKey), $recordKey]
    );
    $values = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $values[(string) $row['field_name']] = (string) ($row['field_value'] ?? '');
    }

    return $values;
}

function yovel_admin_hr_custom_value(array $values, string $fieldName): string
{
    return (string) ($values[$fieldName] ?? '');
}

function yovel_admin_render_hr_custom_field_controls(array $fields, array $values, string $section): void
{
    foreach (yovel_admin_hr_custom_fields($fields, $section) as $field):
        $fieldName = (string) ($field['field_name'] ?? '');
        $fieldLabel = (string) ($field['field_label'] ?? $fieldName);
        $fieldType = (string) ($field['field_type'] ?? 'TEXT');
        $value = yovel_admin_hr_custom_value($values, $fieldName);
        $required = (int) ($field['is_required'] ?? 0) === 1 ? 'required' : '';
        $placeholder = (string) ($field['field_placeholder'] ?? '');
        $help = (string) ($field['field_help'] ?? '');
        ?>
        <div class="grid gap-1.5">
            <label class="text-xs font-medium" for="custom_<?= bx_h($fieldName) ?>"><?= bx_h($fieldLabel) ?></label>
            <?php if ($fieldType === 'TEXTAREA'): ?>
                <textarea class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" id="custom_<?= bx_h($fieldName) ?>" name="custom_fields[<?= bx_h($fieldName) ?>]" placeholder="<?= bx_h($placeholder) ?>" <?= $required ?>><?= bx_h($value) ?></textarea>
            <?php elseif ($fieldType === 'SELECT'): ?>
                <select class="h-9 rounded-md border bg-background px-3 text-sm" id="custom_<?= bx_h($fieldName) ?>" name="custom_fields[<?= bx_h($fieldName) ?>]" <?= $required ?>>
                    <option value=""></option>
                    <?php foreach (array_filter(array_map('trim', preg_split('/\R/', (string) ($field['field_options'] ?? '')) ?: [])) as $option): ?>
                        <option value="<?= bx_h($option) ?>" <?= $value === $option ? 'selected' : '' ?>><?= bx_h($option) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($fieldType === 'CHECKBOX'): ?>
                <label class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm">
                    <input type="checkbox" id="custom_<?= bx_h($fieldName) ?>" name="custom_fields[<?= bx_h($fieldName) ?>]" value="1" <?= $value === '1' ? 'checked' : '' ?>>
                    <span>Enabled</span>
                </label>
            <?php else: ?>
                <?php $inputType = ['DATE' => 'date', 'NUMBER' => 'number', 'EMAIL' => 'email', 'PHONE' => 'tel'][$fieldType] ?? 'text'; ?>
                <input class="h-9 rounded-md border bg-background px-3 text-sm" id="custom_<?= bx_h($fieldName) ?>" name="custom_fields[<?= bx_h($fieldName) ?>]" type="<?= bx_h($inputType) ?>" value="<?= bx_h($value) ?>" placeholder="<?= bx_h($placeholder) ?>" <?= $required ?>>
            <?php endif; ?>
            <?php if ($help !== ''): ?>
                <p class="text-xs leading-5 text-muted-foreground"><?= bx_h($help) ?></p>
            <?php endif; ?>
        </div>
        <?php
    endforeach;
}

function yovel_admin_render_hr_form_builder(string $formKey, array $fields, bool $withDisclosure = true, string $mode = 'builder', array $returnContext = []): void
{
    $formKey = yovel_admin_hr_form_key($formKey);
    $formTitle = yovel_admin_hr_sections()[$formKey]['label'] ?? ucwords(str_replace('-', ' ', $formKey));
    $isBuilderMode = $mode === 'builder';
    $sectionLabels = [
        'overview' => 'Overview',
        'joining' => 'Joining',
        'address-contacts' => 'Address & Contacts',
        'attendance-leaves' => 'Attendance & Leaves',
        'salary' => 'Salary',
        'personal' => 'Personal',
        'profile' => 'Profile',
        'exit' => 'Exit',
        'details' => 'Details',
    ];
    $fieldGroups = [];
    foreach ($fields as $field) {
        $sectionKey = yovel_admin_slug((string) ($field['field_section'] ?? 'overview'));
        $sectionKey = $sectionKey !== '' ? $sectionKey : 'overview';
        $fieldGroups[$sectionKey][] = $field;
    }
    $preferredSectionOrder = ['overview', 'joining', 'address-contacts', 'attendance-leaves', 'salary', 'personal', 'profile', 'exit', 'details'];
    $orderedGroups = [];
    foreach ($preferredSectionOrder as $sectionKey) {
        if (isset($fieldGroups[$sectionKey])) {
            $orderedGroups[$sectionKey] = $fieldGroups[$sectionKey];
            unset($fieldGroups[$sectionKey]);
        }
    }
    foreach ($fieldGroups as $sectionKey => $sectionFields) {
        $orderedGroups[$sectionKey] = $sectionFields;
    }
    $fieldGroups = $orderedGroups;
    $sectionTitle = static function (string $sectionKey) use ($sectionLabels): string {
        return $sectionLabels[$sectionKey] ?? ucwords(str_replace(['-', '_'], ' ', $sectionKey));
    };
    $fieldTypes = ['TEXT', 'TEXTAREA', 'DATE', 'NUMBER', 'EMAIL', 'PHONE', 'SELECT', 'CHECKBOX'];
    $toolbox = [
        ['type' => 'TEXT', 'label' => 'Short Text', 'icon' => 'short_text'],
        ['type' => 'TEXTAREA', 'label' => 'Long Text', 'icon' => 'notes'],
        ['type' => 'SELECT', 'label' => 'Dropdown', 'icon' => 'arrow_drop_down_circle'],
        ['type' => 'CHECKBOX', 'label' => 'Checkbox', 'icon' => 'check_box'],
        ['type' => 'DATE', 'label' => 'Date', 'icon' => 'calendar_month'],
        ['type' => 'NUMBER', 'label' => 'Number', 'icon' => 'tag'],
        ['type' => 'EMAIL', 'label' => 'Email', 'icon' => 'alternate_email'],
        ['type' => 'PHONE', 'label' => 'Phone', 'icon' => 'call'],
    ];
    $previewControl = static function (array $field): string {
        $type = (string) ($field['field_type'] ?? 'TEXT');
        $placeholder = trim((string) ($field['field_placeholder'] ?? ''));
        if ($placeholder === '') {
            $placeholder = match ($type) {
                'DATE' => 'mm/dd/yyyy',
                'NUMBER' => '0',
                'SELECT' => 'Select option',
                'CHECKBOX' => 'Enabled',
                default => 'Enter value',
            };
        }
        if ($type === 'TEXTAREA') {
            return '<div class="yovel-hr-builder-preview-control min-h-16">' . bx_h($placeholder) . '</div>';
        }
        if ($type === 'CHECKBOX') {
            return '<div class="yovel-hr-builder-preview-control inline-flex items-center gap-2"><span class="inline-block size-3 rounded-sm border"></span><span>' . bx_h($placeholder) . '</span></div>';
        }
        return '<div class="yovel-hr-builder-preview-control">' . bx_h($placeholder) . '</div>';
    };
    $firstFieldKey = '';
    foreach ($fieldGroups as $sectionFields) {
        if ($sectionFields) {
            $firstFieldKey = (string) ($sectionFields[0]['field_key'] ?? '');
            break;
        }
    }
    ?>
    <?php if ($withDisclosure): ?>
        <details class="border-t">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-semibold">
                <span>Customize Form</span>
                <span class="text-xs text-muted-foreground"><?= count($fields) ?> fields</span>
            </summary>
            <div class="grid gap-4 px-5 pb-5">
    <?php else: ?>
        <div class="grid gap-4">
    <?php endif; ?>
            <section class="yovel-hr-form-builder" data-hr-form-builder="<?= bx_h($formKey) ?>" data-hr-form-builder-mode="<?= $isBuilderMode ? 'builder' : 'customize' ?>">
                <div class="yovel-hr-form-builder-shell">
                    <?php if ($isBuilderMode): ?>
                        <aside class="yovel-hr-builder-pane yovel-hr-builder-toolbox grid gap-4 p-3" aria-label="Field Toolbox">
                            <div>
                                <h5 class="text-xs font-semibold uppercase">Field Toolbox</h5>
                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Pick a field type, then place it in the form layout.</p>
                            </div>
                            <div class="yovel-hr-builder-tool-grid">
                                <?php foreach ($toolbox as $tool): ?>
                                    <button type="button" class="yovel-hr-builder-tool text-xs font-semibold" data-hr-builder-preset="<?= bx_h($tool['type']) ?>" data-hr-builder-preset-label="<?= bx_h($tool['label']) ?>">
                                        <span class="material-symbols-rounded text-base" aria-hidden="true"><?= bx_h($tool['icon']) ?></span>
                                        <span><?= bx_h($tool['label']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="grid gap-2 border-t pt-3">
                                <h5 class="text-xs font-semibold uppercase">Form Sections</h5>
                                <?php foreach (array_keys($fieldGroups) as $sectionKey): ?>
                                    <button type="button" class="inline-flex min-h-8 items-center justify-between rounded-md border bg-background/70 px-2 text-left text-xs font-medium hover:bg-muted" data-hr-builder-section="<?= bx_h($sectionKey) ?>">
                                        <span><?= bx_h($sectionTitle($sectionKey)) ?></span>
                                        <span class="text-muted-foreground"><?= count($fieldGroups[$sectionKey]) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </aside>
                    <?php endif; ?>

                    <div class="yovel-hr-builder-pane yovel-hr-builder-canvas-pane p-3">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <h5 class="text-sm font-semibold"><?= $isBuilderMode ? 'Form Layout' : 'Current Form Layout' ?></h5>
                                <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= $isBuilderMode ? 'Click a field to inspect it, or drag it into another section.' : 'Choose an existing field, then edit it in Field Properties.' ?></p>
                            </div>
                            <span class="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground"><?= $isBuilderMode ? 'Builder' : 'Editor' ?></span>
                        </div>
                        <div class="yovel-hr-builder-canvas">
                            <?php foreach ($fieldGroups as $sectionKey => $sectionFields): ?>
                                <section class="yovel-hr-builder-section" data-hr-builder-canvas-section="<?= bx_h($sectionKey) ?>">
                                    <div class="yovel-hr-builder-section-header">
                                        <div class="flex items-center justify-between gap-3">
                                            <h5 class="text-sm font-semibold"><?= bx_h($sectionTitle($sectionKey)) ?></h5>
                                            <span class="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground"><?= count($sectionFields) ?> fields</span>
                                        </div>
                                    </div>
                                    <div class="yovel-hr-builder-field-list">
                                        <?php foreach ($sectionFields as $field): ?>
                                            <?php
                                                $fieldKey = (string) ($field['field_key'] ?? '');
                                                $isSelected = !$isBuilderMode && $fieldKey !== '' && $fieldKey === $firstFieldKey;
                                                $isCore = (int) ($field['is_core'] ?? 0) === 1;
                                                $isVisible = (int) ($field['is_visible'] ?? 1) === 1;
                                                $isRequired = (int) ($field['is_required'] ?? 0) === 1;
                                            ?>
                                            <button type="button" class="yovel-hr-builder-field-card" data-hr-builder-field-card="<?= bx_h($fieldKey) ?>" aria-selected="<?= $isSelected ? 'true' : 'false' ?>" draggable="true">
                                                <span class="yovel-hr-builder-field-grip material-symbols-rounded" aria-hidden="true">drag_indicator</span>
                                                <span class="yovel-hr-builder-field-content">
                                                    <span class="yovel-hr-builder-field-main">
                                                        <span class="yovel-hr-builder-field-title"><?= bx_h((string) $field['field_label']) ?></span>
                                                        <span class="yovel-hr-builder-field-badges">
                                                            <?php if ($isCore): ?><span class="yovel-hr-builder-status-pill">Core</span><?php endif; ?>
                                                            <?php if ($isRequired): ?><span class="yovel-hr-builder-status-pill">Required</span><?php endif; ?>
                                                            <?php if (!$isVisible): ?><span class="yovel-hr-builder-status-pill">Hidden</span><?php endif; ?>
                                                        </span>
                                                    </span>
                                                    <span class="yovel-hr-builder-field-meta">
                                                        <span>Field key: <?= bx_h((string) $field['field_name']) ?></span>
                                                        <span>Type: <?= bx_h((string) $field['field_type']) ?></span>
                                                    </span>
                                                    <span class="yovel-hr-builder-field-divider" aria-hidden="true"></span>
                                                    <span class="yovel-hr-builder-field-preview"><?= $previewControl($field) ?></span>
                                                </span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <aside class="yovel-hr-builder-pane yovel-hr-builder-settings p-3" aria-label="Field Properties">
                        <div class="mb-3">
                            <h5 class="text-sm font-semibold">Field Properties</h5>
                            <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= $isBuilderMode ? 'Set up the new field, choose where it appears, then save.' : 'Edit the selected field from the current form layout.' ?></p>
                        </div>
                        <?php if ($isBuilderMode): ?>
                            <form id="<?= bx_h($formKey) ?>_builder_add_field" method="post" data-confirm-submit class="yovel-hr-builder-settings-panel grid gap-3" data-hr-builder-add-form>
                                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                <input type="hidden" name="action" value="save_hr_form_field">
                                <input type="hidden" name="form_key" value="<?= bx_h($formKey) ?>">
                                <?php foreach ($returnContext as $returnName => $returnValue): ?>
                                    <input type="hidden" name="<?= bx_h((string) $returnName) ?>" value="<?= bx_h((string) $returnValue) ?>">
                                <?php endforeach; ?>
                                <div class="flex items-center justify-between gap-2">
                                    <h6 class="text-xs font-semibold uppercase">Create Field</h6>
                                    <button type="submit" class="inline-flex h-8 items-center rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground hover:bg-primary/90">Add Field</button>
                                </div>
                                <div class="yovel-hr-builder-property-group">
                                    <div class="yovel-hr-builder-property-heading">
                                        <span>1</span>
                                        <div>
                                            <h6>Field identity</h6>
                                            <p>Name the field the way admins will see it.</p>
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <label class="text-xs font-medium" for="<?= bx_h($formKey) ?>_new_field_label">Label</label>
                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="<?= bx_h($formKey) ?>_new_field_label" name="field_label" maxlength="160" required>
                                    </div>
                                    <div class="grid gap-2">
                                        <label class="text-xs font-medium" for="<?= bx_h($formKey) ?>_new_field_name">Field key</label>
                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="<?= bx_h($formKey) ?>_new_field_name" name="field_name" pattern="[a-z][a-z0-9_]{1,79}" placeholder="custom_field" required>
                                        <p class="text-[11px] leading-4 text-muted-foreground">Used by the system. Lowercase letters, numbers, and underscores only.</p>
                                    </div>
                                </div>

                                <div class="yovel-hr-builder-property-group">
                                    <div class="yovel-hr-builder-property-heading">
                                        <span>2</span>
                                        <div>
                                            <h6>Display and placement</h6>
                                            <p>Choose the input type and where it belongs.</p>
                                        </div>
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <label class="text-xs font-medium" for="<?= bx_h($formKey) ?>_new_field_type">Type</label>
                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="<?= bx_h($formKey) ?>_new_field_type" name="field_type">
                                                <?php foreach ($fieldTypes as $type): ?><option value="<?= bx_h($type) ?>"><?= bx_h($type) ?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="grid gap-2">
                                            <label class="text-xs font-medium" for="<?= bx_h($formKey) ?>_new_field_section">Section</label>
                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="<?= bx_h($formKey) ?>_new_field_section" name="field_section">
                                                <?php foreach (array_keys($fieldGroups) as $sectionKey): ?><option value="<?= bx_h($sectionKey) ?>"><?= bx_h($sectionTitle($sectionKey)) ?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <label class="text-xs font-medium" for="<?= bx_h($formKey) ?>_new_sort_order">Sort order</label>
                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="<?= bx_h($formKey) ?>_new_sort_order" name="sort_order" type="number" min="0" value="500">
                                    </div>
                                </div>

                                <div class="yovel-hr-builder-property-group">
                                    <div class="yovel-hr-builder-property-heading">
                                        <span>3</span>
                                        <div>
                                            <h6>Options and helper text</h6>
                                            <p>Add dropdown choices, placeholder text, or help text.</p>
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <label class="text-xs font-medium" for="<?= bx_h($formKey) ?>_new_field_options">Options</label>
                                        <textarea class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" id="<?= bx_h($formKey) ?>_new_field_options" name="field_options" placeholder="One option per line"></textarea>
                                    </div>
                                    <div class="grid gap-2">
                                        <label class="text-xs font-medium" for="<?= bx_h($formKey) ?>_new_field_placeholder">Placeholder</label>
                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="<?= bx_h($formKey) ?>_new_field_placeholder" name="field_placeholder" maxlength="200">
                                    </div>
                                    <div class="grid gap-2">
                                        <label class="text-xs font-medium" for="<?= bx_h($formKey) ?>_new_field_help">Help text</label>
                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="<?= bx_h($formKey) ?>_new_field_help" name="field_help" maxlength="1000">
                                    </div>
                                </div>

                                <div class="yovel-hr-builder-property-group">
                                    <div class="yovel-hr-builder-property-heading">
                                        <span>4</span>
                                        <div>
                                            <h6>Validation</h6>
                                            <p>Decide if the field is mandatory and visible.</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-4">
                                        <label class="inline-flex items-center gap-2 text-xs font-medium"><input type="checkbox" name="is_required" value="1"> Required</label>
                                        <label class="inline-flex items-center gap-2 text-xs font-medium"><input type="checkbox" name="is_visible" value="1" checked> Visible</label>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>

                        <?php foreach ($fieldGroups as $sectionKey => $sectionFields): ?>
                            <?php foreach ($sectionFields as $field): ?>
                                <?php
                                    $fieldKey = (string) ($field['field_key'] ?? '');
                                    $isSelected = !$isBuilderMode && $fieldKey !== '' && $fieldKey === $firstFieldKey;
                                    $isCore = (int) ($field['is_core'] ?? 0) === 1;
                                ?>
                                <form method="post" data-confirm-submit class="yovel-hr-builder-settings-panel grid gap-3" data-hr-builder-settings-panel="<?= bx_h($fieldKey) ?>" <?= $isSelected ? '' : 'hidden' ?>>
                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="save_hr_form_field">
                                    <input type="hidden" name="form_key" value="<?= bx_h($formKey) ?>">
                                    <?php foreach ($returnContext as $returnName => $returnValue): ?>
                                        <input type="hidden" name="<?= bx_h((string) $returnName) ?>" value="<?= bx_h((string) $returnValue) ?>">
                                    <?php endforeach; ?>
                                    <input type="hidden" name="field_key" value="<?= bx_h($fieldKey) ?>">
                                    <input type="hidden" name="field_name" value="<?= bx_h((string) $field['field_name']) ?>">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <h6 class="text-xs font-semibold uppercase"><?= $isCore ? 'Protected Core Field' : 'Custom Field' ?></h6>
                                            <p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $field['field_name']) ?></p>
                                        </div>
                                        <button type="submit" class="inline-flex h-8 items-center rounded-md border bg-background px-3 text-xs font-medium hover:bg-muted">Save</button>
                                    </div>
                                    <div class="yovel-hr-builder-property-group">
                                        <div class="yovel-hr-builder-property-heading">
                                            <span>1</span>
                                            <div>
                                                <h6>Field identity</h6>
                                                <p>Change the label admins see on the form.</p>
                                            </div>
                                        </div>
                                        <div class="grid gap-2">
                                            <label class="text-xs font-medium">Label</label>
                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" name="field_label" value="<?= bx_h((string) $field['field_label']) ?>" maxlength="160" required>
                                        </div>
                                        <p class="rounded-md bg-muted/40 px-3 py-2 text-[11px] leading-4 text-muted-foreground">Field key: <?= bx_h((string) $field['field_name']) ?></p>
                                    </div>

                                    <div class="yovel-hr-builder-property-group">
                                        <div class="yovel-hr-builder-property-heading">
                                            <span>2</span>
                                            <div>
                                                <h6>Display and placement</h6>
                                                <p>Choose how the field appears and where it belongs.</p>
                                            </div>
                                        </div>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <div class="grid gap-2">
                                                <label class="text-xs font-medium">Type</label>
                                                <select class="h-9 rounded-md border bg-background px-3 text-sm" name="field_type" <?= $isCore ? 'disabled' : '' ?>>
                                                    <?php foreach ($fieldTypes as $type): ?><option value="<?= bx_h($type) ?>" <?= (string) $field['field_type'] === $type ? 'selected' : '' ?>><?= bx_h($type) ?></option><?php endforeach; ?>
                                                </select>
                                                <?php if ($isCore): ?><input type="hidden" name="field_type" value="<?= bx_h((string) $field['field_type']) ?>"><?php endif; ?>
                                            </div>
                                            <div class="grid gap-2">
                                                <label class="text-xs font-medium">Section</label>
                                                <select class="h-9 rounded-md border bg-background px-3 text-sm" name="field_section">
                                                    <?php foreach (array_keys($fieldGroups) as $optionSectionKey): ?><option value="<?= bx_h($optionSectionKey) ?>" <?= (string) $field['field_section'] === $optionSectionKey ? 'selected' : '' ?>><?= bx_h($sectionTitle($optionSectionKey)) ?></option><?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="grid gap-2">
                                            <label class="text-xs font-medium">Sort order</label>
                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" name="sort_order" type="number" min="0" value="<?= bx_h((string) $field['sort_order']) ?>">
                                        </div>
                                    </div>

                                    <div class="yovel-hr-builder-property-group">
                                        <div class="yovel-hr-builder-property-heading">
                                            <span>3</span>
                                            <div>
                                                <h6>Options and helper text</h6>
                                                <p>Support dropdown choices and user-facing guidance.</p>
                                            </div>
                                        </div>
                                        <div class="grid gap-2">
                                            <label class="text-xs font-medium">Options</label>
                                            <textarea class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" name="field_options" placeholder="One option per line"><?= bx_h((string) ($field['field_options'] ?? '')) ?></textarea>
                                        </div>
                                        <div class="grid gap-2">
                                            <label class="text-xs font-medium">Placeholder</label>
                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" name="field_placeholder" value="<?= bx_h((string) ($field['field_placeholder'] ?? '')) ?>" maxlength="200">
                                        </div>
                                        <div class="grid gap-2">
                                            <label class="text-xs font-medium">Help text</label>
                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" name="field_help" value="<?= bx_h((string) ($field['field_help'] ?? '')) ?>" maxlength="1000">
                                        </div>
                                    </div>

                                    <div class="yovel-hr-builder-property-group">
                                        <div class="yovel-hr-builder-property-heading">
                                            <span>4</span>
                                            <div>
                                                <h6>Validation</h6>
                                                <p>Control required status and visibility.</p>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-4">
                                            <label class="inline-flex items-center gap-2 text-xs font-medium"><input type="checkbox" name="is_required" value="1" <?= (int) ($field['is_required'] ?? 0) === 1 ? 'checked' : '' ?>> Required</label>
                                            <label class="inline-flex items-center gap-2 text-xs font-medium"><input type="checkbox" name="is_visible" value="1" <?= (int) ($field['is_visible'] ?? 0) === 1 ? 'checked' : '' ?>> Visible</label>
                                        </div>
                                    </div>
                                </form>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </aside>
                </div>
            </section>
        </div>
    <?php if ($withDisclosure): ?>
        </details>
    <?php endif; ?>
    <?php
}

function yovel_admin_validate_hr_custom_value(array $field, string $value): string
{
    $value = trim($value);
    $label = (string) ($field['field_label'] ?? 'Custom field');
    $type = (string) ($field['field_type'] ?? 'TEXT');
    if ((int) ($field['is_required'] ?? 0) === 1 && $value === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }
    if (strlen($value) > 5000) {
        throw new InvalidArgumentException($label . ' exceeds the allowed length.');
    }
    if ($value !== '' && $type === 'EMAIL' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException($label . ' must be a valid email address.');
    }
    if ($value !== '' && $type === 'DATE') {
        yovel_admin_optional_date($value, $label);
    }
    if ($value !== '' && $type === 'NUMBER' && !is_numeric($value)) {
        throw new InvalidArgumentException($label . ' must be a number.');
    }
    if ($value !== '' && $type === 'SELECT') {
        $options = array_filter(array_map('trim', preg_split('/\R/', (string) ($field['field_options'] ?? '')) ?: []));
        if ($options && !in_array($value, $options, true)) {
            throw new InvalidArgumentException($label . ' has an invalid selected value.');
        }
    }

    return $value;
}

function yovel_admin_hr_typed_custom_value(array $field, string $value): array
{
    $value = yovel_admin_validate_hr_custom_value($field, $value);
    $type = strtoupper((string) ($field['field_type'] ?? 'TEXT'));
    $typed = [
        'field_value' => $value,
        'value_text' => null,
        'value_number' => null,
        'value_date' => null,
        'value_boolean' => null,
        'value_json' => null,
    ];
    if ($value === '') {
        return $typed;
    }
    if ($type === 'NUMBER') {
        $typed['value_number'] = $value;
    } elseif ($type === 'DATE') {
        $typed['value_date'] = $value;
    } elseif ($type === 'CHECKBOX') {
        $typed['field_value'] = in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        $typed['value_boolean'] = $typed['field_value'] === '1' ? 1 : 0;
    } else {
        $typed['value_text'] = $value;
    }

    return $typed;
}

function yovel_admin_save_hr_custom_values(ADOConnection $db, array $company, array $admin, string $formKey, string $recordKey, array $fields): void
{
    $customPost = $_POST['custom_fields'] ?? [];
    if (!is_array($customPost)) {
        $customPost = [];
    }
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    foreach (yovel_admin_hr_custom_fields($fields) as $field) {
        $fieldName = (string) ($field['field_name'] ?? '');
        if ($fieldName === '') {
            continue;
        }
        $value = (string) ($customPost[$fieldName] ?? '');
        if ((string) ($field['field_type'] ?? '') === 'CHECKBOX') {
            $value = isset($customPost[$fieldName]) ? '1' : '0';
        }
        $typed = yovel_admin_hr_typed_custom_value($field, $value);
        $normalizedFormKey = yovel_admin_hr_form_key($formKey);
        if ($typed['field_value'] === '' && (int) ($field['is_required'] ?? 0) !== 1) {
            yovel_admin_db_execute(
                $db,
                'DELETE FROM project_company_hr_custom_value WHERE company_key_hash = ? AND form_key = ? AND record_key = ? AND field_name = ?',
                [$companyKeyHash, $normalizedFormKey, $recordKey, $fieldName],
                'HR cleared custom field value delete'
            );
            $remaining = (int) $db->GetOne(
                'SELECT COUNT(*) FROM project_company_hr_custom_value WHERE company_key_hash = ? AND form_key = ? AND record_key = ? AND field_name = ?',
                [$companyKeyHash, $normalizedFormKey, $recordKey, $fieldName]
            );
            if ($remaining !== 0) {
                throw new RuntimeException('HR cleared custom field read-back verification failed for ' . $fieldName . '.');
            }
            continue;
        }
        $fieldKey = (string) ($field['field_key'] ?? '');
        $fieldType = strtoupper((string) ($field['field_type'] ?? 'TEXT'));
        if (!yovel_admin_is_uuid($fieldKey)) {
            throw new RuntimeException('HR custom field definition is missing a stable field key.');
        }
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_hr_custom_value (
                value_key, company_key, company_key_hash, form_key, record_key, field_name, field_key,
                field_type, field_value, value_text, value_number, value_date, value_boolean, value_json,
                created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                field_key = VALUES(field_key),
                field_type = VALUES(field_type),
                field_value = VALUES(field_value),
                value_text = VALUES(value_text),
                value_number = VALUES(value_number),
                value_date = VALUES(value_date),
                value_boolean = VALUES(value_boolean),
                value_json = VALUES(value_json),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                bx_uuid(), $companyKey, $companyKeyHash, $normalizedFormKey, $recordKey, $fieldName,
                $fieldKey, $fieldType, $typed['field_value'], $typed['value_text'], $typed['value_number'],
                $typed['value_date'], $typed['value_boolean'], $typed['value_json'], $adminKey, $adminKey,
            ],
            'HR custom field value save'
        );
        $savedValue = $db->GetRow(
            'SELECT field_key, field_type, field_value, value_text, value_number, value_date, value_boolean, value_json FROM project_company_hr_custom_value WHERE company_key_hash = ? AND form_key = ? AND record_key = ? AND field_name = ? LIMIT 1',
            [$companyKeyHash, $normalizedFormKey, $recordKey, $fieldName]
        );
        if (!is_array($savedValue)
            || (string) ($savedValue['field_key'] ?? '') !== $fieldKey
            || (string) ($savedValue['field_type'] ?? '') !== $fieldType
            || (string) ($savedValue['field_value'] ?? '') !== (string) $typed['field_value']
            || (string) ($savedValue['value_text'] ?? '') !== (string) ($typed['value_text'] ?? '')
            || (string) ($savedValue['value_date'] ?? '') !== (string) ($typed['value_date'] ?? '')
            || (string) ($savedValue['value_boolean'] ?? '') !== (string) ($typed['value_boolean'] ?? '')
            || (string) ($savedValue['value_json'] ?? '') !== (string) ($typed['value_json'] ?? '')
            || ($typed['value_number'] === null
                ? (string) ($savedValue['value_number'] ?? '') !== ''
                : (float) ($savedValue['value_number'] ?? 0) !== (float) $typed['value_number'])) {
            throw new RuntimeException('HR custom field read-back verification failed for ' . $fieldName . '.');
        }
    }
}

function yovel_admin_save_hr_form_field(array $company, array $admin): string
{
    yovel_admin_hr_schema();
    yovel_admin_seed_hr_form_fields($company, $admin);

    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $formKey = yovel_admin_hr_form_key((string) ($_POST['form_key'] ?? ''));
    $fieldKey = trim((string) ($_POST['field_key'] ?? ''));
    $fieldName = yovel_admin_hr_field_name((string) ($_POST['field_name'] ?? ''));
    $fieldLabel = trim((string) ($_POST['field_label'] ?? ''));
    $fieldType = yovel_admin_status((string) ($_POST['field_type'] ?? 'TEXT'), ['TEXT', 'TEXTAREA', 'DATE', 'NUMBER', 'EMAIL', 'PHONE', 'SELECT', 'CHECKBOX'], 'TEXT');
    $fieldSection = yovel_admin_slug((string) ($_POST['field_section'] ?? 'overview'));
    $fieldPlaceholder = trim((string) ($_POST['field_placeholder'] ?? ''));
    $fieldHelp = trim((string) ($_POST['field_help'] ?? ''));
    $fieldOptions = trim((string) ($_POST['field_options'] ?? ''));
    $isRequired = isset($_POST['is_required']) ? 1 : 0;
    $isVisible = isset($_POST['is_visible']) ? 1 : 0;
    $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));

    if ($fieldKey !== '' && !yovel_admin_is_uuid($fieldKey)) {
        throw new InvalidArgumentException('Invalid HR form field key.');
    }
    if ($fieldLabel === '' || strlen($fieldLabel) > 160) {
        throw new InvalidArgumentException('Field label is required and must be 160 characters or fewer.');
    }
    if ($fieldPlaceholder !== '' && strlen($fieldPlaceholder) > 200) {
        throw new InvalidArgumentException('Field placeholder must be 200 characters or fewer.');
    }
    if ($fieldHelp !== '' && strlen($fieldHelp) > 1000) {
        throw new InvalidArgumentException('Field help must be 1000 characters or fewer.');
    }
    if ($fieldOptions !== '' && strlen($fieldOptions) > 2000) {
        throw new InvalidArgumentException('Field options must be 2000 characters or fewer.');
    }

    $db->BeginTrans();
    try {
        $existing = null;
        if ($fieldKey !== '') {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_hr_form_field WHERE company_key_hash = ? AND field_key = ? FOR UPDATE',
                [$companyKeyHash, $fieldKey]
            );
            if (!$existing) {
                throw new InvalidArgumentException('HR form field was not found.');
            }
            $formKey = (string) $existing['form_key'];
            if ((int) ($existing['is_core'] ?? 0) === 1) {
                $fieldName = (string) $existing['field_name'];
                $fieldType = (string) $existing['field_type'];
            }
        } else {
            if ($fieldName === '') {
                throw new InvalidArgumentException('Field name must start with a letter and use letters, numbers, or underscores.');
            }
            $existing = $db->GetRow(
                'SELECT * FROM project_company_hr_form_field WHERE company_key_hash = ? AND form_key = ? AND field_name = ? FOR UPDATE',
                [$companyKeyHash, $formKey, $fieldName]
            );
            if ($existing) {
                $fieldKey = (string) $existing['field_key'];
                if ((int) ($existing['is_core'] ?? 0) === 1) {
                    $fieldType = (string) $existing['field_type'];
                }
            }
        }
        if ($fieldKey === '') {
            $fieldKey = bx_uuid();
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_hr_form_field (
                field_key, company_key, company_key_hash, form_key, field_name, field_label, field_type,
                field_section, field_placeholder, field_help, field_options, is_required, is_visible,
                is_core, sort_order, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                field_label = VALUES(field_label),
                field_type = IF(is_core = 1, field_type, VALUES(field_type)),
                field_section = VALUES(field_section),
                field_placeholder = VALUES(field_placeholder),
                field_help = VALUES(field_help),
                field_options = VALUES(field_options),
                is_required = VALUES(is_required),
                is_visible = VALUES(is_visible),
                sort_order = VALUES(sort_order),
                field_status = 'ACTIVE',
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $fieldKey, $companyKey, $companyKeyHash, $formKey, $fieldName, $fieldLabel, $fieldType,
                $fieldSection, $fieldPlaceholder, $fieldHelp, $fieldOptions, $isRequired, $isVisible,
                $sortOrder, $adminKey, $adminKey,
            ],
            'HR form field save'
        );
	        $savedField = $db->GetRow(
	            'SELECT field_key, form_key, field_name, field_label, field_type, field_section, field_placeholder, field_help, field_options, is_required, is_visible, sort_order FROM project_company_hr_form_field WHERE company_key_hash = ? AND field_key = ? LIMIT 1',
	            [$companyKeyHash, $fieldKey]
	        );
	        foreach ([
	            'field_key' => $fieldKey,
	            'form_key' => $formKey,
	            'field_name' => $fieldName,
	            'field_label' => $fieldLabel,
	            'field_type' => $fieldType,
	            'field_section' => $fieldSection,
	            'field_placeholder' => $fieldPlaceholder,
	            'field_help' => $fieldHelp,
	            'field_options' => $fieldOptions,
	            'is_required' => (string) $isRequired,
	            'is_visible' => (string) $isVisible,
	            'sort_order' => (string) $sortOrder,
        ] as $column => $expected) {
            if (!is_array($savedField) || (string) ($savedField[$column] ?? '') !== (string) $expected) {
                throw new RuntimeException('HR form field read-back verification failed for ' . $column . '.');
            }
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_form_field', $fieldKey, [
            'company_key' => $companyKey,
            'company_name' => (string) $company['company_name'],
            'form_key' => $formKey,
            'field_name' => $fieldName,
            'field_label' => $fieldLabel,
            'admin_key' => $adminKey,
        ], $existing ? 'Company admin updated HR form field.' : 'Company admin created HR form field.');

        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'HR form field saved.';
}

function yovel_admin_upsert_hr_builder_form_version(ADOConnection $db, array $form, string $adminKey): array
{
    $builderFormKey = (string) ($form['builder_form_key'] ?? '');
    $companyKey = (string) ($form['company_key'] ?? '');
    $companyKeyHash = (string) ($form['company_key_hash'] ?? '');
    $schemaJson = (string) ($form['schema_json'] ?? '');
    $formStatus = strtoupper((string) ($form['form_status'] ?? 'DRAFT'));
    if (!yovel_admin_is_uuid($builderFormKey) || $companyKey === '' || $companyKeyHash === '' || $schemaJson === '') {
        throw new RuntimeException('HR builder form version source is incomplete.');
    }
    if (!in_array($formStatus, ['DRAFT', 'ACTIVE', 'ARCHIVED'], true)) {
        throw new RuntimeException('HR builder form version status is invalid.');
    }
    $checksum = yovel_admin_hr_builder_version_checksum($form, $schemaJson);
    $existing = $db->GetRow(
        'SELECT * FROM project_company_hr_builder_form_version WHERE builder_form_key = ? AND schema_checksum = ? LIMIT 1',
        [$builderFormKey, $checksum]
    );
    if (is_array($existing) && $existing !== []) {
        return $existing;
    }

    $versionNumber = (int) $db->GetOne(
        'SELECT COALESCE(MAX(version_number), 0) + 1 FROM project_company_hr_builder_form_version WHERE builder_form_key = ?',
        [$builderFormKey]
    );
    $versionKey = bx_uuid();
    yovel_admin_db_execute(
        $db,
        "INSERT INTO project_company_hr_builder_form_version (
            form_version_key, builder_form_key, company_key, company_key_hash, version_number,
            target_section, form_title, form_description, form_status, schema_json,
            schema_checksum, question_count, created_by_admin_key
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $versionKey, $builderFormKey, $companyKey, $companyKeyHash, $versionNumber,
            (string) ($form['target_section'] ?? ''), (string) ($form['form_title'] ?? ''),
            (string) ($form['form_description'] ?? ''), $formStatus, $schemaJson, $checksum,
            (int) ($form['question_count'] ?? 0), $adminKey !== '' ? $adminKey : null,
        ],
        'HR builder form immutable version create'
    );

    $readBack = $db->GetRow(
        'SELECT * FROM project_company_hr_builder_form_version WHERE form_version_key = ? AND builder_form_key = ? LIMIT 1',
        [$versionKey, $builderFormKey]
    );
    $expected = [
        'form_version_key' => $versionKey,
        'builder_form_key' => $builderFormKey,
        'company_key' => $companyKey,
        'company_key_hash' => $companyKeyHash,
        'version_number' => (string) $versionNumber,
        'target_section' => (string) ($form['target_section'] ?? ''),
        'form_title' => (string) ($form['form_title'] ?? ''),
        'form_description' => (string) ($form['form_description'] ?? ''),
        'form_status' => $formStatus,
        'schema_json' => $schemaJson,
        'schema_checksum' => $checksum,
        'question_count' => (string) (int) ($form['question_count'] ?? 0),
    ];
    foreach ($expected as $column => $value) {
        if (!is_array($readBack) || (string) ($readBack[$column] ?? '') !== $value) {
            throw new RuntimeException('HR builder form version read-back verification failed for ' . $column . '.');
        }
    }

    bx_audit('CREATE', 'project_company_hr_builder_form_version', $versionKey, [
        'builder_form_key' => $builderFormKey,
        'company_key' => $companyKey,
        'version_number' => $versionNumber,
        'schema_checksum' => $checksum,
    ], 'Created an immutable HR builder form version.');

    return $readBack;
}

function yovel_admin_persist_hr_builder_form(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input,
    ?callable $projector = null
): array
{
    yovel_admin_hr_schema();

    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $builderFormKey = trim((string) ($input['builder_form_key'] ?? ''));
    $targetSection = yovel_admin_hr_builder_target_section((string) ($input['target_section'] ?? 'employee-profiles'));
    $formTitle = trim((string) ($input['form_title'] ?? ''));
    $formDescription = trim((string) ($input['form_description'] ?? ''));
    $formStatus = yovel_admin_status((string) ($input['form_status'] ?? 'DRAFT'), ['DRAFT', 'ACTIVE', 'ARCHIVED', 'DELETED'], 'DRAFT');
    $schema = yovel_admin_normalize_hr_builder_schema((string) ($input['schema_json'] ?? '{}'));
    $schemaJson = json_encode($schema, JSON_UNESCAPED_SLASHES);
    if ($schemaJson === false) {
        throw new RuntimeException('Unable to encode HR builder form layout.');
    }
    $questionCount = count($schema['questions']);

    if ($builderFormKey !== '' && !yovel_admin_is_uuid($builderFormKey)) {
        throw new InvalidArgumentException('Invalid HR builder form key.');
    }
    if ($formTitle === '') {
        $formTitle = 'Untitled HR Form';
    }
    if (strlen($formTitle) > 180) {
        throw new InvalidArgumentException('Form title must be 180 characters or fewer.');
    }
    if (strlen($formDescription) > 2000) {
        throw new InvalidArgumentException('Form description must be 2000 characters or fewer.');
    }

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('HR builder form transaction could not start.');
    }
    try {
        $existing = null;
        if ($builderFormKey !== '') {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_hr_builder_form WHERE company_key_hash = ? AND builder_form_key = ? FOR UPDATE',
                [$companyKeyHash, $builderFormKey]
            );
            if (!$existing) {
                throw new InvalidArgumentException('HR builder form was not found for this company.');
            }
        }
        if ($builderFormKey === '') {
            $builderFormKey = bx_uuid();
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_hr_builder_form (
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
                $builderFormKey, $companyKey, $companyKeyHash, $targetSection, $formTitle,
                $formDescription, $formStatus, $schemaJson, $questionCount, $adminKey, $adminKey,
            ],
            'HR builder form save'
        );

        $version = null;
        if ($formStatus !== 'DELETED') {
            $version = yovel_admin_upsert_hr_builder_form_version($db, [
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
            'SELECT * FROM project_company_hr_builder_form WHERE company_key_hash = ? AND builder_form_key = ? LIMIT 1',
            [$companyKeyHash, $builderFormKey]
        );
        foreach ([
            'builder_form_key' => $builderFormKey,
            'target_section' => $targetSection,
            'form_title' => $formTitle,
            'form_description' => $formDescription,
            'form_status' => $formStatus,
            'schema_json' => $schemaJson,
            'question_count' => (string) $questionCount,
        ] as $column => $expected) {
            if (!is_array($saved) || (string) ($saved[$column] ?? '') !== (string) $expected) {
                throw new RuntimeException('HR builder form read-back verification failed for ' . $column . '.');
            }
        }

        $projector ??= 'bx_project_module_sync_hr_builder_form';
        $projection = $projector($db, $company, $saved);
        if (!is_array($projection)
            || !isset($projection['projected'], $projection['retired'])
            || !is_array($projection['projected'])
            || !is_array($projection['retired'])) {
            throw new RuntimeException('HR builder form projection returned an invalid result.');
        }

        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_hr_builder_form', $builderFormKey, [
            'company_key' => $companyKey,
            'company_name' => (string) $company['company_name'],
            'target_section' => $targetSection,
            'form_title' => $formTitle,
            'form_status' => $formStatus,
            'question_count' => $questionCount,
            'form_version_key' => (string) ($version['form_version_key'] ?? ''),
            'version_number' => (int) ($version['version_number'] ?? 0),
            'projected_form_count' => count($projection['projected']),
            'retired_form_count' => count($projection['retired']),
            'admin_key' => $adminKey,
        ], $existing ? 'Company admin updated an HR builder form.' : 'Company admin created an HR builder form.');

        if ($db->CommitTrans() === false) {
            throw new RuntimeException('HR builder form transaction could not commit.');
        }
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    $saved['version'] = $version;
    $saved['projection'] = $projection;

    return $saved;
}

function yovel_admin_save_hr_builder_form(array $company, array $admin): string
{
    $saved = yovel_admin_persist_hr_builder_form(bx_db(), $company, $admin, [
        'builder_form_key' => (string) ($_POST['builder_form_key'] ?? ''),
        'target_section' => (string) ($_POST['target_section'] ?? 'employee-profiles'),
        'form_title' => (string) ($_POST['form_title'] ?? ''),
        'form_description' => (string) ($_POST['form_description'] ?? ''),
        'form_status' => (string) ($_POST['form_status'] ?? 'DRAFT'),
        'schema_json' => (string) ($_POST['schema_json'] ?? '{}'),
    ]);
    $GLOBALS['yovel_admin_saved_hr_builder_form_key'] = (string) ($saved['builder_form_key'] ?? '');

    return 'HR builder form saved.';
}
