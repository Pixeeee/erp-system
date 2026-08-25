<?php
declare(strict_types=1);

function yovel_admin_shared_form_adapter(string $module): array
{
    $module = strtolower(trim($module));

    if ($module === 'hr') {
        $targets = function_exists('yovel_admin_hr_builder_target_sections')
            ? array_fill_keys(array_keys(yovel_admin_hr_builder_target_sections()), 'record')
            : ['employee-profiles' => 'record'];
        $protected = [];
        if (function_exists('yovel_admin_hr_default_form_fields')) {
            foreach (yovel_admin_hr_default_form_fields() as $recordType => $fields) {
                $protected[$recordType] = [];
                foreach ($fields as $field) {
                    if ((int) ($field[6] ?? 0) === 1) {
                        $protected[$recordType][] = (string) $field[0];
                    }
                }
            }
        }

        return [
            'module' => 'hr',
            'target_record_types' => $targets,
            'protected_fields' => $protected,
            'field_types' => ['SHORT_TEXT', 'PARAGRAPH', 'DROPDOWN', 'CHECKBOXES', 'DATE', 'NUMBER', 'EMAIL', 'PHONE', 'SECTION'],
            'row_column_layout' => ['version' => 2, 'max_columns' => 3, 'stable_keys' => true],
            'normalize' => 'yovel_admin_normalize_hr_builder_schema',
            'version_identity' => 'yovel_admin_hr_builder_version_checksum',
            'renderer' => 'company/admin/modules/hr/forms.php',
        ];
    }

    if ($module === 'accounting-finance') {
        if (function_exists('yovel_admin_finance_builder_adapter')) {
            return yovel_admin_finance_builder_adapter();
        }

        $targets = [];
        if (function_exists('yovel_admin_finance_builder_target_sections')) {
            foreach (yovel_admin_finance_builder_target_sections() as $section => $metadata) {
                $targets[$section] = (string) ($metadata['record_type'] ?? $section);
            }
        }
        $protected = [];
        if (function_exists('yovel_admin_accounting_finance_default_form_schemas')) {
            foreach (yovel_admin_accounting_finance_default_form_schemas() as $recordType => $schema) {
                $keys = array_map('strval', $schema['requiredSystemFields'] ?? []);
                foreach ($schema['fields'] ?? [] as $field) {
                    if (!empty($field['system'])) {
                        $keys[] = (string) ($field['key'] ?? '');
                    }
                }
                $protected[$recordType] = array_values(array_unique(array_filter($keys)));
            }
        }

        return [
            'module' => 'accounting-finance',
            'target_record_types' => $targets !== [] ? $targets : ['chart-of-accounts' => 'account'],
            'protected_fields' => $protected,
            'field_types' => ['SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'CURRENCY', 'DATE', 'DROPDOWN', 'CHECKBOXES', 'ACCOUNT', 'PARTY', 'SECTION'],
            'row_column_layout' => ['version' => 2, 'max_columns' => 3, 'stable_keys' => true],
            'normalize' => 'yovel_admin_normalize_finance_builder_schema',
            'version_identity' => 'yovel_admin_finance_builder_checksum',
            'renderer' => 'company/admin/modules/accounting-finance/views/form-builder.php',
        ];
    }

    $route = function_exists('yovel_admin_module_route') ? yovel_admin_module_route($module) : null;
    if (!$route) {
        throw new InvalidArgumentException('Unknown shared Form Builder module: ' . $module);
    }

    return [
        'module' => $module,
        'target_record_types' => [(string) $route['default_section'] => 'record'],
        'protected_fields' => [],
        'field_types' => ['SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'CURRENCY', 'DATE', 'DROPDOWN', 'CHECKBOXES', 'SECTION'],
        'row_column_layout' => ['version' => 1, 'max_columns' => 3, 'stable_keys' => true],
        'normalize' => null,
        'version_identity' => null,
        'renderer' => (string) $route['workspace_file'],
    ];
}

/**
 * Reorder existing fields without treating client-provided keys as definitions.
 * Unknown keys are ignored and omitted existing fields are retained.
 */
function yovel_admin_shared_form_reorder(string $module, string $recordType, array $fields, array $requestedKeys): array
{
    $adapter = yovel_admin_shared_form_adapter($module);
    if (!array_key_exists($recordType, $adapter['target_record_types'])
        && !in_array($recordType, $adapter['target_record_types'], true)) {
        throw new InvalidArgumentException('The Form Builder target is not registered for ' . $module . '.');
    }

    $byKey = [];
    $originalOrder = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $key = trim((string) ($field['key'] ?? $field['field_key'] ?? ''));
        if ($key === '' || isset($byKey[$key])) {
            throw new InvalidArgumentException('Form fields require unique stable keys.');
        }
        $byKey[$key] = $field;
        $originalOrder[] = $key;
    }

    $ordered = [];
    $used = [];
    foreach ($requestedKeys as $requestedKey) {
        $requestedKey = trim((string) $requestedKey);
        if ($requestedKey === '' || isset($used[$requestedKey]) || !isset($byKey[$requestedKey])) {
            continue;
        }
        $ordered[] = $byKey[$requestedKey];
        $used[$requestedKey] = true;
    }
    foreach ($originalOrder as $key) {
        if (!isset($used[$key])) {
            $ordered[] = $byKey[$key];
        }
    }

    return $ordered;
}
