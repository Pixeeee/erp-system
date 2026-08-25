<?php
declare(strict_types=1);

require __DIR__ . '/projects-test-helper.php';

foreach ([
    'yovel_admin_projects_builder_targets',
    'yovel_admin_projects_default_form_schemas',
    'yovel_admin_projects_form_adapter',
    'yovel_admin_projects_normalize_form_schema',
    'yovel_admin_projects_active_schema',
    'yovel_admin_projects_editable_schema',
    'yovel_admin_projects_schema_history',
    'yovel_admin_projects_save_form_schema',
    'yovel_admin_projects_publish_schema',
    'yovel_admin_projects_archive_schema',
] as $function) {
    projects_assert(function_exists($function), 'Projects Form Builder function is missing: ' . $function);
}

$scope = projects_test_scope();
$company = $scope['company'];
$admin = $scope['admin'];
register_shutdown_function(static fn () => projects_cleanup($company, $admin));

$targets = yovel_admin_projects_builder_targets();
$expectedTargets = ['PROJECT', 'PROJECT_TEMPLATE', 'TASK', 'PROJECT_UPDATE', 'ACTIVITY_TYPE', 'ACTIVITY_COST'];
projects_assert(array_keys($targets) === $expectedTargets, 'Projects Form Builder targets are incomplete or unstable.');

$defaults = yovel_admin_projects_default_form_schemas();
projects_assert(array_keys($defaults) === $expectedTargets, 'Projects default schemas do not cover every target.');
$adapter = yovel_admin_projects_form_adapter();
projects_assert(($adapter['module'] ?? '') === 'projects', 'Projects Form Builder adapter has the wrong owner.');
projects_assert(array_values($adapter['target_record_types'] ?? []) === $expectedTargets, 'Projects adapter target mapping is incomplete.');
projects_assert(($adapter['row_column_layout']['max_columns'] ?? 0) === 3, 'Projects Form Builder must support three columns.');
projects_assert(!empty($adapter['row_column_layout']['stable_keys']), 'Projects Form Builder must preserve stable field keys.');

foreach ($expectedTargets as $target) {
    $schema = $defaults[$target];
    projects_assert(($schema['targetType'] ?? '') === $target, 'Default target identity is wrong for ' . $target . '.');
    projects_assert(($schema['requiredSystemFields'] ?? []) !== [], 'Protected fields are missing for ' . $target . '.');
    projects_assert(($schema['sections'] ?? []) !== [], 'Sections are missing for ' . $target . '.');
    foreach ($schema['sections'] as $section) {
        projects_assert(($section['rows'] ?? []) !== [], 'Rows are missing in ' . $target . '.');
        foreach ($section['rows'] as $row) {
            projects_assert(($row['columns'] ?? []) !== [], 'Columns are missing in ' . $target . '.');
        }
    }
    foreach ($schema['fields'] as $field) {
        projects_assert(preg_match('/^[a-z][a-z0-9_]{1,79}$/', (string) $field['key']) === 1, 'Field key is unstable: ' . (string) $field['key']);
        foreach (['label', 'type', 'section', 'row', 'column', 'width', 'required', 'visible', 'system', 'default', 'options', 'validation'] as $property) {
            projects_assert(array_key_exists($property, $field), 'Field metadata is missing ' . $property . ' for ' . $target . '.');
        }
    }
}

$projectSchema = $defaults['PROJECT'];
$projectSchema['fields'][] = [
    'key' => 'delivery_note',
    'label' => 'Delivery note',
    'type' => 'PARAGRAPH',
    'section' => 'delivery',
    'row' => 'delivery-row',
    'column' => 2,
    'width' => 6,
    'required' => false,
    'visible' => true,
    'system' => false,
    'default' => '',
    'options' => [],
    'validation' => ['max_length' => 500],
];

$withoutProtected = $projectSchema;
$withoutProtected['fields'] = array_values(array_filter(
    $withoutProtected['fields'],
    static fn (array $field): bool => !in_array((string) $field['key'], $defaults['PROJECT']['requiredSystemFields'], true)
));
$normalized = yovel_admin_projects_normalize_form_schema('PROJECT', $withoutProtected, 1);
foreach ($defaults['PROJECT']['requiredSystemFields'] as $fieldKey) {
    projects_assert(in_array($fieldKey, array_column($normalized['fields'], 'key'), true), 'Protected Project field was removed: ' . $fieldKey);
}
projects_assert(in_array('delivery_note', array_column($normalized['fields'], 'key'), true), 'Custom Project field was not retained.');

projects_with_session_token(static function (string $csrf) use ($company, $admin, $projectSchema): void {
    $db = bx_db();
    projects_expect_exception(
        static fn () => yovel_admin_projects_handle_post($company, $admin, 'save_projects_form_schema', [
            'csrf' => 'wrong-token',
            'section' => 'form-builder',
            'target_type' => 'PROJECT',
            'schema_status' => 'DRAFT',
            'schema_json' => yovel_admin_projects_form_json($projectSchema),
        ]),
        'token'
    );
    projects_assert(
        (int) $db->GetOne('SELECT COUNT(*) FROM project_company_project_form_schema WHERE company_key_hash = ?', [$company['company_key_hash']]) === 0,
        'Invalid CSRF wrote a Projects form version.'
    );

    $draftResult = yovel_admin_projects_handle_post($company, $admin, 'save_projects_form_schema', [
        'csrf' => $csrf,
        'section' => 'form-builder',
        'target_type' => 'PROJECT',
        'schema_status' => 'DRAFT',
        'schema_json' => yovel_admin_projects_form_json($projectSchema),
    ]);
    projects_assert(($draftResult['section'] ?? '') === 'form-builder', 'Draft handler returned the wrong section.');
    $history = yovel_admin_projects_schema_history($company, 'PROJECT');
    projects_assert(count($history) === 1 && (string) $history[0]['schema_status'] === 'DRAFT', 'Draft version was not persisted.');
    projects_assert((int) $history[0]['version_number'] === 1, 'First Projects form version must be version 1.');

    $activeBeforePublish = yovel_admin_projects_active_schema($company, 'PROJECT');
    projects_assert(empty($activeBeforePublish['persisted']), 'Draft schema must not become the active submitted-record schema.');
    $editable = yovel_admin_projects_editable_schema($company, 'PROJECT');
    projects_assert(!empty($editable['persisted']) && (int) $editable['version'] === 1, 'Draft schema was not rehydrated for editing.');

    $publishedResult = yovel_admin_projects_handle_post($company, $admin, 'save_projects_form_schema', [
        'csrf' => $csrf,
        'section' => 'form-builder',
        'target_type' => 'PROJECT',
        'schema_status' => 'PUBLISHED',
        'schema_json' => yovel_admin_projects_form_json($projectSchema),
    ]);
    projects_assert(($publishedResult['message'] ?? '') === 'Projects form version published.', 'Published handler feedback is unstable.');
    $active = yovel_admin_projects_active_schema($company, 'PROJECT');
    projects_assert(!empty($active['persisted']), 'Published schema was not loaded from the server.');
    projects_assert((int) $active['version'] === 2 && (string) $active['schema_status'] === 'PUBLISHED', 'Published schema version is incorrect.');
    projects_assert(in_array('delivery_note', array_column($active['fields'], 'key'), true), 'Published custom field was not rehydrated.');

    $publishedRow = $db->GetRow(
        "SELECT * FROM project_company_project_form_schema
         WHERE company_key_hash = ? AND target_type = 'PROJECT' AND version_number = 2",
        [$company['company_key_hash']]
    );
    projects_assert(is_array($publishedRow), 'Published Projects form row is missing.');
    $publishedJson = (string) $publishedRow['schema_json'];
    $publishedChecksum = (string) $publishedRow['schema_checksum'];

    $changed = $projectSchema;
    $changed['fields'][0]['label'] = 'Project code retained';
    yovel_admin_projects_handle_post($company, $admin, 'save_projects_form_schema', [
        'csrf' => $csrf,
        'section' => 'form-builder',
        'target_type' => 'PROJECT',
        'schema_status' => 'DRAFT',
        'schema_json' => yovel_admin_projects_form_json($changed),
    ]);
    $publishedAfterDraft = $db->GetRow(
        "SELECT schema_json, schema_checksum, schema_status FROM project_company_project_form_schema
         WHERE company_key_hash = ? AND target_type = 'PROJECT' AND version_number = 2",
        [$company['company_key_hash']]
    );
    projects_assert((string) $publishedAfterDraft['schema_json'] === $publishedJson, 'A published schema was mutated by a later draft.');
    projects_assert((string) $publishedAfterDraft['schema_checksum'] === $publishedChecksum, 'A published checksum changed.');
    projects_assert((string) $publishedAfterDraft['schema_status'] === 'PUBLISHED', 'A published lifecycle changed in place.');

    $sameDraft = yovel_admin_projects_handle_post($company, $admin, 'save_projects_form_schema', [
        'csrf' => $csrf,
        'section' => 'form-builder',
        'target_type' => 'PROJECT',
        'schema_status' => 'DRAFT',
        'schema_json' => yovel_admin_projects_form_json($changed),
    ]);
    projects_assert(($sameDraft['query']['version'] ?? '') === '3', 'Idempotent draft save did not return the existing version.');
    projects_assert(count(yovel_admin_projects_schema_history($company, 'PROJECT')) === 3, 'Idempotent draft save created another version.');

    yovel_admin_projects_handle_post($company, $admin, 'save_projects_form_schema', [
        'csrf' => $csrf,
        'section' => 'form-builder',
        'target_type' => 'PROJECT',
        'schema_status' => 'ARCHIVED',
        'schema_json' => yovel_admin_projects_form_json($changed),
    ]);
    $historyAfterArchive = yovel_admin_projects_schema_history($company, 'PROJECT');
    projects_assert(array_column($historyAfterArchive, 'version_number') === ['4', '3', '2', '1'], 'Form history is not newest-first and monotonic.');
    projects_assert((string) $historyAfterArchive[0]['schema_status'] === 'ARCHIVED', 'Archive did not create an immutable archive version.');
});

echo "Projects Task 2 Form Builder checks passed.\n";
