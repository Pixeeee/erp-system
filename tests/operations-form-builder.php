<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

$company = operations_test_company('Operations Form Builder Company');
$otherCompany = operations_test_company('Other Operations Company');
$admin = operations_test_admin();
operations_test_register_cleanup($company);
operations_test_register_cleanup($otherCompany);

yovel_admin_operations_schema();
yovel_admin_operations_schema();

$adapter = yovel_admin_operations_form_adapter();
operations_test_assert(($adapter['module'] ?? '') === 'operations', 'Operations Form Builder adapter has the wrong module.');
operations_test_assert(count($adapter['target_record_types'] ?? []) === 16, 'Operations Form Builder does not expose all registered features.');
operations_test_assert(($adapter['row_column_layout']['version'] ?? 0) === 2, 'Operations Form Builder layout is not version 2.');
operations_test_assert(($adapter['row_column_layout']['max_columns'] ?? 0) === 3, 'Operations Form Builder must support up to three columns.');

$schema = [
    'version' => 2,
    'fields' => [
        ['key' => 'custom_note', 'label' => 'Operator note', 'type' => 'PARAGRAPH', 'required' => false],
        ['key' => 'status', 'label' => 'Renamed status', 'type' => 'SHORT_TEXT'],
    ],
    'rows' => [
        ['key' => 'row-main', 'columns' => [
            ['key' => 'column-left', 'width' => 6, 'field_keys' => ['custom_note']],
            ['key' => 'column-right', 'width' => 6, 'field_keys' => ['status']],
        ]],
    ],
];
$normalized = yovel_admin_normalize_operations_builder_schema('scheduled-jobs', $schema);
$keys = array_column($normalized['fields'], 'key');
foreach (['job_key', 'job_type', 'status', 'custom_note'] as $key) {
    operations_test_assert(in_array($key, $keys, true), 'Operations Form Builder lost protected/stable field: ' . $key);
}
$statusField = array_values(array_filter($normalized['fields'], static fn (array $field): bool => ($field['key'] ?? '') === 'status'))[0];
operations_test_assert(($statusField['system'] ?? false) === true, 'Protected status field is not marked as a system field.');
operations_test_assert(($statusField['label'] ?? '') === 'Status', 'Client input renamed a protected field.');

$reorderedSchema = $schema;
$reorderedSchema['fields'] = array_reverse($schema['fields']);
$reordered = yovel_admin_normalize_operations_builder_schema('scheduled-jobs', $reorderedSchema);
$normalizedKeys = array_column($normalized['fields'], 'key');
$reorderedKeys = array_column($reordered['fields'], 'key');
sort($normalizedKeys);
sort($reorderedKeys);
operations_test_assert($normalizedKeys === $reorderedKeys, 'Reordering changed stable Operations field keys.');

$input = [
    'target_section' => 'scheduled-jobs',
    'form_title' => 'Scheduled Job Intake',
    'form_description' => 'Capture a reviewed scheduled job request.',
    'form_status' => 'DRAFT',
    'schema' => $schema,
];
$created = yovel_admin_persist_operations_builder_form(bx_db(), $company, $admin, $input);
operations_test_assert(yovel_admin_is_uuid((string) ($created['builder_form_key'] ?? '')), 'Form create did not return a stable key.');
operations_test_assert(yovel_admin_is_uuid((string) ($created['current_version_key'] ?? '')), 'Form create did not return an immutable version key.');
operations_test_assert((int) ($created['version_number'] ?? 0) === 1, 'First Operations form version is not version 1.');
operations_test_assert(($created['form_title'] ?? '') === $input['form_title'], 'Form create read-back changed the title.');

$unchanged = yovel_admin_persist_operations_builder_form(bx_db(), $company, $admin, [
    ...$input,
    'builder_form_key' => (string) $created['builder_form_key'],
]);
operations_test_assert((string) $unchanged['current_version_key'] === (string) $created['current_version_key'], 'Unchanged form content created a duplicate version.');

$updatedSchema = $schema;
$updatedSchema['fields'][] = ['key' => 'run_context', 'label' => 'Run context', 'type' => 'SHORT_TEXT'];
$published = yovel_admin_persist_operations_builder_form(bx_db(), $company, $admin, [
    ...$input,
    'builder_form_key' => (string) $created['builder_form_key'],
    'form_status' => 'PUBLISHED',
    'schema' => $updatedSchema,
]);
operations_test_assert((string) $published['builder_form_key'] === (string) $created['builder_form_key'], 'Form update replaced the stable form key.');
operations_test_assert((int) $published['version_number'] === 2, 'Changed form content did not create immutable version 2.');
operations_test_assert((string) $published['form_status'] === 'PUBLISHED', 'Published form did not rehydrate its committed status.');

$versionCount = (int) bx_db()->GetOne(
    'SELECT COUNT(*) FROM project_company_operations_builder_form_version WHERE company_key_hash = ? AND builder_form_key = ?',
    [(string) $company['company_key_hash'], (string) $created['builder_form_key']]
);
operations_test_assert($versionCount === 2, 'Operations form version history is not immutable/deduplicated.');

$submission = yovel_admin_persist_operations_form_submission(bx_db(), $company, $admin, [
    'builder_form_key' => (string) $published['builder_form_key'],
    'form_version_key' => (string) $published['current_version_key'],
    'subject_key' => 'scheduled-job-request-001',
    'values' => ['custom_note' => 'Confirmed request', 'run_context' => 'Nightly'],
]);
operations_test_assert(yovel_admin_is_uuid((string) ($submission['submission_key'] ?? '')), 'Form submission did not return a stable key.');
operations_test_assert((string) $submission['form_version_key'] === (string) $published['current_version_key'], 'Submission was not pinned to the published version.');

$submissionUpdate = yovel_admin_persist_operations_form_submission(bx_db(), $company, $admin, [
    'submission_key' => (string) $submission['submission_key'],
    'builder_form_key' => (string) $published['builder_form_key'],
    'form_version_key' => (string) $created['current_version_key'],
    'subject_key' => 'scheduled-job-request-001',
    'values' => ['custom_note' => 'Updated note'],
]);
operations_test_assert((string) $submissionUpdate['form_version_key'] === (string) $published['current_version_key'], 'Submission update changed its pinned immutable version.');

$forms = yovel_admin_operations_builder_forms($company);
operations_test_assert(count($forms) === 1 && (string) $forms[0]['builder_form_key'] === (string) $created['builder_form_key'], 'Server rehydration did not return the committed form.');
operations_test_assert(yovel_admin_operations_builder_forms($otherCompany) === [], 'Operations forms leaked across company scope.');

$archived = yovel_admin_persist_operations_builder_form(bx_db(), $company, $admin, [
    ...$input,
    'builder_form_key' => (string) $created['builder_form_key'],
    'form_status' => 'ARCHIVED',
    'schema' => $updatedSchema,
]);
operations_test_assert((string) $archived['form_status'] === 'ARCHIVED', 'Form archive status did not persist and rehydrate.');

echo "Operations Form Builder checks passed.\n";
