<?php
declare(strict_types=1);

require __DIR__ . '/manufacturing-test-helper.php';

$targets = yovel_admin_manufacturing_form_targets();
manufacturing_test_assert(count($targets) === 12, 'Manufacturing Form Builder must expose twelve configurable record targets.');
$adapter = yovel_admin_manufacturing_form_adapter();
manufacturing_test_assert(($adapter['module'] ?? '') === 'manufacturing', 'Manufacturing Form Builder adapter module is invalid.');
manufacturing_test_assert(($adapter['row_column_layout']['stable_keys'] ?? false) === true, 'Manufacturing Form Builder must preserve stable field keys.');
manufacturing_test_assert(($adapter['row_column_layout']['max_columns'] ?? 0) === 3, 'Manufacturing Form Builder must support up to three columns.');
$fieldByKey = static function (array $schema, string $key): ?array {
    foreach ($schema['fields'] ?? [] as $field) {
        if (is_array($field) && (string) ($field['key'] ?? '') === $key) {
            return $field;
        }
    }
    return null;
};

$defaults = yovel_admin_manufacturing_default_form_schemas();
foreach ($targets as $section => $recordType) {
    manufacturing_test_assert(isset($defaults[$recordType]), 'Missing default form schema for ' . $section . '.');
    $required = $defaults[$recordType]['requiredSystemFields'] ?? [];
    foreach (['company_key', 'record_key', 'record_status', 'form_version_key', 'created_by_admin_key'] as $protected) {
        manufacturing_test_assert(in_array($protected, $required, true), 'Manufacturing protected system field is missing: ' . $protected);
    }
}

$normalized = yovel_admin_manufacturing_normalize_form_schema('BOM', [
    'title' => 'BOM custom form',
    'description' => 'A test layout',
    'fields' => [
        ['key' => 'custom_batch_note', 'label' => 'Batch note', 'type' => 'PARAGRAPH', 'section' => 'details', 'required' => true, 'visible' => true, 'width' => 'full', 'default' => '', 'validation' => ['max_length' => 400], 'options' => []],
    ],
    'rows' => [['key' => 'row_custom', 'columns' => [['key' => 'column_custom', 'field_keys' => ['custom_batch_note']]]]],
], 2);
manufacturing_test_assert(is_array($fieldByKey($normalized, 'custom_batch_note')), 'Custom field stable key was not preserved.');
manufacturing_test_assert(($normalized['rows'][0]['columns'][0]['field_keys'][0] ?? '') === 'custom_batch_note', 'Form row/column placement was not preserved.');
manufacturing_test_assert(yovel_admin_manufacturing_form_checksum($normalized) === yovel_admin_manufacturing_form_checksum($normalized), 'Form version identity is unstable.');

$scope = manufacturing_test_create_scope('form-builder');
try {
    yovel_admin_manufacturing_schema();
    $draft = yovel_admin_save_manufacturing_form($scope['company'], $scope['admin'], [
        'target_section' => 'boms',
        'form_title' => 'BOM intake',
        'form_description' => 'Draft intake layout',
        'form_status' => 'DRAFT',
        'schema' => $normalized,
    ]);
    manufacturing_test_assert(($draft['form_status'] ?? '') === 'DRAFT', 'Manufacturing form draft was not saved.');
    $draftVersionKey = (string) $draft['current_form_version_key'];
    $draftVersionJson = (string) bx_db()->GetOne('SELECT schema_json FROM project_company_manufacturing_form_version WHERE company_key_hash = ? AND form_version_key = ?', [$scope['company']['company_key_hash'], $draftVersionKey]);
    $_GET['form'] = (string) $draft['form_key'];
    $_GET['target'] = 'boms';
    $selectedData = yovel_admin_manufacturing_data($scope['company'], $scope['admin'], 'form-builder');
    manufacturing_test_assert(is_array($fieldByKey($selectedData['form_schema'], 'custom_batch_note')), 'Editing an existing draft did not load its exact current form version.');
    unset($_GET['form'], $_GET['target']);

    $published = yovel_admin_save_manufacturing_form($scope['company'], $scope['admin'], [
        'form_key' => $draft['form_key'],
        'target_section' => 'boms',
        'form_title' => 'BOM intake',
        'form_description' => 'Published intake layout',
        'form_status' => 'PUBLISHED',
        'schema' => $normalized,
    ]);
    manufacturing_test_assert(($published['form_status'] ?? '') === 'PUBLISHED', 'Manufacturing form was not published.');
    manufacturing_test_assert($published['current_form_version_key'] !== $draftVersionKey, 'Publishing must create an immutable version.');
    manufacturing_test_assert((string) bx_db()->GetOne('SELECT schema_json FROM project_company_manufacturing_form_version WHERE company_key_hash = ? AND form_version_key = ?', [$scope['company']['company_key_hash'], $draftVersionKey]) === $draftVersionJson, 'Published save mutated an earlier form version.');
    manufacturing_test_assert(count(yovel_admin_manufacturing_form_versions($scope['company'], (string) $published['form_key'])) === 2, 'Manufacturing form version history is incomplete.');

    $fieldInput = yovel_admin_manufacturing_form_schema_input([
        'schema' => yovel_admin_manufacturing_default_form_schemas()['BOM'],
        'new_field_label' => 'Inspection note',
        'new_field_key' => 'inspection_note',
        'new_field_type' => 'PARAGRAPH',
        'new_field_section' => 'quality',
        'new_field_required' => '1',
    ]);
    $inspectionField = $fieldByKey($fieldInput, 'inspection_note');
    manufacturing_test_assert(is_array($inspectionField) && !empty($inspectionField['required']), 'Form Builder field controls did not produce structured schema input.');

    $binding = yovel_admin_manufacturing_bind_form_version($scope['company'], $scope['admin'], 'test-record-key', 'BOM', (string) $published['current_form_version_key']);
    manufacturing_test_assert(($binding['form_version_key'] ?? '') === $published['current_form_version_key'], 'Submitted record did not bind to the published form version.');

    $archived = yovel_admin_archive_manufacturing_form($scope['company'], $scope['admin'], (string) $published['form_key']);
    manufacturing_test_assert(($archived['form_status'] ?? '') === 'ARCHIVED', 'Manufacturing form was not archived.');

    $markup = manufacturing_test_render($scope, 'form-builder');
    foreach (['Field Toolbox', 'Form Layout', 'Field Properties', 'New Form', 'Existing Forms', 'Preview'] as $label) {
        manufacturing_test_assert(str_contains($markup, $label), 'Manufacturing Form Builder UI is missing ' . $label . '.');
    }
} finally {
    manufacturing_test_cleanup_scope($scope);
}

echo "Manufacturing Form Builder tests passed.\n";
