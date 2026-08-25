<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/hr/navigation.php';
require_once $root . '/company/admin/modules/hr/schema.php';
require_once $root . '/company/admin/modules/hr/forms.php';
require_once $root . '/company/admin/modules/hr/employees.php';
$submissionServicePath = $root . '/company/admin/modules/hr/submissions.php';
if (is_file($submissionServicePath)) {
    require_once $submissionServicePath;
}

function hr_database_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = bx_db();
$employeeKeysBefore = $db->GetCol('SELECT employee_key FROM project_company_hr_employee ORDER BY employee_key');
yovel_admin_hr_schema();

$requiredTables = [
    'project_company_hr_employee_assignment',
    'project_company_hr_builder_form_version',
    'project_form_submission',
    'project_form_submission_value',
];
foreach ($requiredTables as $table) {
    $exists = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    hr_database_assert($exists === 1, 'Missing database-driven HR table: ' . $table);
}

$requiredCustomValueColumns = [
    'field_key',
    'field_type',
    'value_text',
    'value_number',
    'value_date',
    'value_boolean',
    'value_json',
];
$customValueColumns = $db->GetCol(
    'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
    [BUILDERX_DB_NAME, 'project_company_hr_custom_value']
);
foreach ($requiredCustomValueColumns as $column) {
    hr_database_assert(is_array($customValueColumns) && in_array($column, $customValueColumns, true), 'Missing typed custom-value column: ' . $column);
}

$requiredIndexes = [
    'project_company_hr_employee_assignment' => [
        'uq_project_company_hr_assignment_migration',
        'idx_project_company_hr_assignment_employee',
        'idx_project_company_hr_assignment_scope',
    ],
    'project_company_hr_builder_form_version' => [
        'uq_project_company_hr_builder_version_number',
        'uq_project_company_hr_builder_version_checksum',
    ],
    'project_form_submission' => [
        'idx_project_form_submission_scope',
        'idx_project_form_submission_subject',
    ],
    'project_form_submission_value' => [
        'uq_project_form_submission_question',
    ],
];
foreach ($requiredIndexes as $table => $indexes) {
    $actualIndexes = $db->GetCol(
        'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    foreach ($indexes as $index) {
        hr_database_assert(is_array($actualIndexes) && in_array($index, $actualIndexes, true), 'Missing database index: ' . $index);
    }
}

$assignmentCountBeforeSecondRun = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_employee_assignment');
$versionCountBeforeSecondRun = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_builder_form_version');
yovel_admin_hr_schema();
$employeeKeysAfter = $db->GetCol('SELECT employee_key FROM project_company_hr_employee ORDER BY employee_key');
$assignmentCountAfterSecondRun = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_employee_assignment');
$versionCountAfterSecondRun = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_builder_form_version');

hr_database_assert($employeeKeysBefore === $employeeKeysAfter, 'HR schema migration changed an employee key.');
hr_database_assert($assignmentCountBeforeSecondRun === $assignmentCountAfterSecondRun, 'Employee assignment migration is not idempotent.');
hr_database_assert($versionCountBeforeSecondRun === $versionCountAfterSecondRun, 'Builder form version migration is not idempotent.');

hr_database_assert(
    function_exists('yovel_admin_upsert_hr_employee_primary_assignment'),
    'Employee primary-assignment persistence is not implemented.'
);

$fixtureScope = $db->GetRow("
    SELECT
        company_record.company_key,
        company_record.company_key_hash,
        branch_record.branch_key,
        project_record.project_key,
        admin_record.admin_key
    FROM project_company company_record
    INNER JOIN project_company_branch branch_record
        ON branch_record.company_key_hash = company_record.company_key_hash
       AND branch_record.branch_status <> 'DELETED'
    INNER JOIN project_company_project project_record
        ON project_record.company_key_hash = company_record.company_key_hash
       AND project_record.branch_key = branch_record.branch_key
       AND project_record.project_status <> 'DELETED'
    INNER JOIN project_company_admin admin_record
        ON admin_record.company_key_hash = company_record.company_key_hash
       AND admin_record.admin_status = 'ACTIVE'
    WHERE company_record.company_status <> 'DELETED'
    ORDER BY company_record.x_id, branch_record.x_id, project_record.x_id
    LIMIT 1
");
hr_database_assert(is_array($fixtureScope) && $fixtureScope !== [], 'An active company assignment fixture scope is required.');

hr_database_assert($db->BeginTrans() !== false, 'Assignment fixture transaction could not start.');
try {
    $fixtureEmployeeKey = bx_uuid();
    $fixtureEmployeeCode = 'TEST_ASSIGN_' . strtoupper(substr(str_replace('-', '', $fixtureEmployeeKey), 0, 10));
    $savedFixture = $db->Execute(
        "INSERT INTO project_company_hr_employee (
            employee_key, company_key, company_key_hash, employee_code, first_name, employee_name,
            employee_status, created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, 'Assignment', 'Assignment Fixture', 'ACTIVE', ?, ?)",
        [
            $fixtureEmployeeKey,
            (string) $fixtureScope['company_key'],
            (string) $fixtureScope['company_key_hash'],
            $fixtureEmployeeCode,
            (string) $fixtureScope['admin_key'],
            (string) $fixtureScope['admin_key'],
        ]
    );
    hr_database_assert($savedFixture !== false, 'Assignment fixture employee could not be created.');

    $fixtureCompany = [
        'company_key' => (string) $fixtureScope['company_key'],
        'company_key_hash' => (string) $fixtureScope['company_key_hash'],
        'company_name' => 'Assignment Fixture Company',
    ];
    $fixtureAdmin = ['admin_key' => (string) $fixtureScope['admin_key']];
    $initialAssignment = [
        'branch_key' => (string) $fixtureScope['branch_key'],
        'project_key' => (string) $fixtureScope['project_key'],
        'department_key' => '',
        'job_position_key' => '',
        'team_key' => '',
        'reports_to_employee_key' => '',
        'effective_from' => '2026-08-25',
    ];
    $firstAssignment = yovel_admin_upsert_hr_employee_primary_assignment(
        $db,
        $fixtureCompany,
        $fixtureAdmin,
        $fixtureEmployeeKey,
        $initialAssignment
    );
    $sameAssignment = yovel_admin_upsert_hr_employee_primary_assignment(
        $db,
        $fixtureCompany,
        $fixtureAdmin,
        $fixtureEmployeeKey,
        $initialAssignment
    );
    hr_database_assert(
        (string) ($firstAssignment['assignment_key'] ?? '') === (string) ($sameAssignment['assignment_key'] ?? ''),
        'An unchanged employee assignment created a duplicate history row.'
    );

    $changedAssignment = $initialAssignment;
    $changedAssignment['project_key'] = '';
    $secondAssignment = yovel_admin_upsert_hr_employee_primary_assignment(
        $db,
        $fixtureCompany,
        $fixtureAdmin,
        $fixtureEmployeeKey,
        $changedAssignment
    );
    hr_database_assert(
        (string) ($secondAssignment['assignment_key'] ?? '') !== (string) ($firstAssignment['assignment_key'] ?? ''),
        'A changed employee assignment did not create a history row.'
    );
    $activePrimaryCount = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_hr_employee_assignment
         WHERE employee_key = ? AND assignment_status = 'ACTIVE' AND is_primary = 1",
        [$fixtureEmployeeKey]
    );
    $endedCount = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_hr_employee_assignment
         WHERE employee_key = ? AND assignment_status = 'ENDED' AND is_primary = 0",
        [$fixtureEmployeeKey]
    );
    hr_database_assert($activePrimaryCount === 1 && $endedCount === 1, 'Employee assignment history status is inconsistent.');

    $mirroredEmployee = $db->GetRow(
        'SELECT branch_key, department_key, job_position_key, team_key, reports_to_employee_key FROM project_company_hr_employee WHERE employee_key = ?',
        [$fixtureEmployeeKey]
    );
    hr_database_assert(
        is_array($mirroredEmployee)
        && (string) ($mirroredEmployee['branch_key'] ?? '') === (string) $changedAssignment['branch_key'],
        'The employee compatibility assignment columns were not mirrored.'
    );

    $selfManagerRejected = false;
    try {
        $invalidAssignment = $changedAssignment;
        $invalidAssignment['reports_to_employee_key'] = $fixtureEmployeeKey;
        yovel_admin_upsert_hr_employee_primary_assignment(
            $db,
            $fixtureCompany,
            $fixtureAdmin,
            $fixtureEmployeeKey,
            $invalidAssignment
        );
    } catch (InvalidArgumentException) {
        $selfManagerRejected = true;
    }
    hr_database_assert($selfManagerRejected, 'An employee was allowed to report to themselves.');
} finally {
    $db->RollbackTrans();
}

hr_database_assert(function_exists('yovel_admin_hr_typed_custom_value'), 'Typed Employee Profile custom values are not implemented.');
hr_database_assert($db->BeginTrans() !== false, 'Typed custom-value fixture transaction could not start.');
$originalPost = $_POST;
try {
    $typedEmployeeKey = bx_uuid();
    $typedEmployeeCode = 'TEST_TYPED_' . strtoupper(substr(str_replace('-', '', $typedEmployeeKey), 0, 10));
    hr_database_assert($db->Execute(
        "INSERT INTO project_company_hr_employee (
            employee_key, company_key, company_key_hash, employee_code, first_name, employee_name,
            employee_status, created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, 'Typed', 'Typed Fixture', 'ACTIVE', ?, ?)",
        [
            $typedEmployeeKey,
            (string) $fixtureScope['company_key'],
            (string) $fixtureScope['company_key_hash'],
            $typedEmployeeCode,
            (string) $fixtureScope['admin_key'],
            (string) $fixtureScope['admin_key'],
        ]
    ) !== false, 'Typed custom-value fixture employee could not be created.');

    $typedFields = [];
    foreach ([
        ['fixture_text', 'Fixture text', 'TEXT'],
        ['fixture_number', 'Fixture number', 'NUMBER'],
        ['fixture_date', 'Fixture date', 'DATE'],
        ['fixture_checkbox', 'Fixture checkbox', 'CHECKBOX'],
    ] as $offset => [$fieldName, $fieldLabel, $fieldType]) {
        $fieldKey = bx_uuid();
        hr_database_assert($db->Execute(
            "INSERT INTO project_company_hr_form_field (
                field_key, company_key, company_key_hash, form_key, field_name, field_label,
                field_type, field_section, is_required, is_visible, is_core, sort_order,
                created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, 'employee-profiles', ?, ?, ?, 'profile', 0, 1, 0, ?, ?, ?)",
            [
                $fieldKey,
                (string) $fixtureScope['company_key'],
                (string) $fixtureScope['company_key_hash'],
                $fieldName,
                $fieldLabel,
                $fieldType,
                900 + ($offset * 10),
                (string) $fixtureScope['admin_key'],
                (string) $fixtureScope['admin_key'],
            ]
        ) !== false, 'Typed custom-value fixture field could not be created.');
        $typedFields[] = [
            'field_key' => $fieldKey,
            'field_name' => $fieldName,
            'field_label' => $fieldLabel,
            'field_type' => $fieldType,
            'field_options' => '',
            'is_required' => 0,
            'is_core' => 0,
        ];
    }

    $_POST = ['custom_fields' => [
        'fixture_text' => 'Database driven',
        'fixture_number' => '1250.50',
        'fixture_date' => '2026-08-25',
        'fixture_checkbox' => '1',
    ]];
    yovel_admin_save_hr_custom_values(
        $db,
        $fixtureCompany,
        $fixtureAdmin,
        'employee-profiles',
        $typedEmployeeKey,
        $typedFields
    );
    $typedRows = $db->GetAll(
        'SELECT field_name, field_key, field_type, field_value, value_text, value_number, value_date, value_boolean, value_json FROM project_company_hr_custom_value WHERE company_key_hash = ? AND record_key = ? ORDER BY field_name',
        [(string) $fixtureScope['company_key_hash'], $typedEmployeeKey]
    );
    hr_database_assert(is_array($typedRows) && count($typedRows) === 4, 'Typed custom values were not persisted once per field.');
    $typedByName = [];
    foreach ($typedRows as $typedRow) {
        $typedByName[(string) $typedRow['field_name']] = $typedRow;
    }
    hr_database_assert((string) ($typedByName['fixture_text']['value_text'] ?? '') === 'Database driven', 'Text custom value was not typed.');
    hr_database_assert((float) ($typedByName['fixture_number']['value_number'] ?? 0) === 1250.5, 'Number custom value was not typed.');
    hr_database_assert((string) ($typedByName['fixture_date']['value_date'] ?? '') === '2026-08-25', 'Date custom value was not typed.');
    hr_database_assert((int) ($typedByName['fixture_checkbox']['value_boolean'] ?? -1) === 1, 'Checkbox custom value was not typed.');
    foreach ($typedFields as $typedField) {
        $saved = $typedByName[(string) $typedField['field_name']] ?? [];
        hr_database_assert((string) ($saved['field_key'] ?? '') === (string) $typedField['field_key'], 'Typed custom value lost its field key.');
        hr_database_assert((string) ($saved['field_type'] ?? '') === (string) $typedField['field_type'], 'Typed custom value lost its field type.');
    }

    $_POST = ['custom_fields' => [
        'fixture_text' => '',
        'fixture_number' => '1250.50',
        'fixture_date' => '2026-08-25',
        'fixture_checkbox' => '1',
    ]];
    yovel_admin_save_hr_custom_values(
        $db,
        $fixtureCompany,
        $fixtureAdmin,
        'employee-profiles',
        $typedEmployeeKey,
        $typedFields
    );
    $clearedTextCount = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_hr_custom_value
         WHERE company_key_hash = ? AND record_key = ? AND field_name = 'fixture_text'",
        [(string) $fixtureScope['company_key_hash'], $typedEmployeeKey]
    );
    hr_database_assert($clearedTextCount === 0, 'Clearing an optional custom value left an empty database row.');
} finally {
    $_POST = $originalPost;
    $db->RollbackTrans();
}

$stableSchemaInput = json_encode([
    'version' => 1,
    'questions' => [
        ['key' => 'employee-alias', 'type' => 'SHORT_TEXT', 'label' => 'Employee alias', 'required' => true],
        ['key' => 'start-date', 'type' => 'DATE', 'label' => 'Start date', 'required' => false],
    ],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$stableSchema = yovel_admin_normalize_hr_builder_schema($stableSchemaInput);
$stableSchemaAgain = yovel_admin_normalize_hr_builder_schema(json_encode($stableSchema, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$reorderedSchema = yovel_admin_normalize_hr_builder_schema(json_encode([
    'version' => 1,
    'questions' => array_reverse($stableSchema['questions']),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$stableKeys = array_column($stableSchema['questions'], 'key');
$stableKeysAgain = array_column($stableSchemaAgain['questions'], 'key');
$reorderedKeys = array_column($reorderedSchema['questions'], 'key');
sort($stableKeys);
sort($stableKeysAgain);
sort($reorderedKeys);
hr_database_assert($stableKeys === $stableKeysAgain, 'Saving a Form Builder schema changed its question keys.');
hr_database_assert($stableKeys === $reorderedKeys, 'Reordering a Form Builder schema changed its question keys.');
hr_database_assert((int) ($stableSchema['version'] ?? 0) === 2, 'Version 1 Form Builder schema was not upgraded to version 2.');
hr_database_assert(count($stableSchema['rows'] ?? []) === 2, 'Version 1 Form Builder questions were not converted into one-column rows.');

$layoutSchema = yovel_admin_normalize_hr_builder_schema(json_encode([
    'version' => 2,
    'questions' => [
        ['key' => 'first-name', 'type' => 'SHORT_TEXT', 'label' => 'First Name', 'required' => true],
        ['key' => 'last-name', 'type' => 'SHORT_TEXT', 'label' => 'Last Name', 'required' => true],
        ['key' => 'email', 'type' => 'EMAIL', 'label' => 'Email'],
        ['key' => 'details-section', 'type' => 'SECTION', 'label' => 'Details'],
    ],
    'rows' => [
        [
            'key' => 'name-row',
            'columns' => [
                ['key' => 'first-column', 'question_keys' => ['first-name']],
                ['key' => 'last-column', 'question_keys' => ['last-name']],
            ],
        ],
        [
            'key' => 'invalid-reference-row',
            'columns' => [
                ['key' => 'invalid-reference-column', 'question_keys' => ['first-name', 'missing-question']],
            ],
        ],
        [
            'key' => 'section-row',
            'columns' => [
                ['key' => 'section-column', 'question_keys' => ['details-section']],
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
hr_database_assert(count($layoutSchema['rows'][0]['columns'] ?? []) === 2, 'A two-column Form Builder row was not preserved.');
$layoutReferences = [];
foreach ($layoutSchema['rows'] as $layoutRow) {
    foreach (($layoutRow['columns'] ?? []) as $layoutColumn) {
        foreach (($layoutColumn['question_keys'] ?? []) as $layoutQuestionKey) {
            $layoutReferences[] = (string) $layoutQuestionKey;
        }
    }
}
hr_database_assert(count(array_keys($layoutReferences, 'first-name', true)) === 1, 'A duplicate layout question reference was not removed.');
hr_database_assert(!in_array('missing-question', $layoutReferences, true), 'An unknown layout question reference was retained.');
hr_database_assert(in_array('email', $layoutReferences, true), 'An unreferenced Form Builder question was not appended to the layout.');
$sectionRows = array_values(array_filter($layoutSchema['rows'], static function (array $layoutRow): bool {
    $columns = $layoutRow['columns'] ?? [];
    return count($columns) === 1 && ($columns[0]['question_keys'] ?? []) === ['details-section'];
}));
hr_database_assert(count($sectionRows) === 1, 'A section question was not isolated in a full-width row.');
hr_database_assert(
    array_column($layoutSchema['questions'], 'key') === ['first-name', 'last-name', 'details-section', 'email'],
    'Form Builder question order does not follow row and column reading order.'
);
$layoutSchemaAgain = yovel_admin_normalize_hr_builder_schema(json_encode($layoutSchema, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
hr_database_assert($layoutSchema === $layoutSchemaAgain, 'Repeated Form Builder layout normalization changed stable row or column keys.');

hr_database_assert(function_exists('yovel_admin_upsert_hr_builder_form_version'), 'Immutable Form Builder version persistence is not implemented.');
hr_database_assert($db->BeginTrans() !== false, 'Form version fixture transaction could not start.');
try {
    $versionFixtureFormKey = bx_uuid();
    $versionFixtureSchemaJson = json_encode($stableSchema, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $versionFixture = [
        'builder_form_key' => $versionFixtureFormKey,
        'company_key' => (string) $fixtureScope['company_key'],
        'company_key_hash' => (string) $fixtureScope['company_key_hash'],
        'target_section' => 'employee-profiles',
        'form_title' => 'Version Fixture',
        'form_description' => 'Immutable version test.',
        'form_status' => 'DRAFT',
        'schema_json' => $versionFixtureSchemaJson,
        'question_count' => count($stableSchema['questions']),
    ];
    $firstVersion = yovel_admin_upsert_hr_builder_form_version(
        $db,
        $versionFixture,
        (string) $fixtureScope['admin_key']
    );
    $sameVersion = yovel_admin_upsert_hr_builder_form_version(
        $db,
        $versionFixture,
        (string) $fixtureScope['admin_key']
    );
    hr_database_assert(
        (string) ($firstVersion['form_version_key'] ?? '') === (string) ($sameVersion['form_version_key'] ?? ''),
        'Saving unchanged form content created a duplicate immutable version.'
    );
    $changedVersionFixture = $versionFixture;
    $changedVersionFixture['form_title'] = 'Version Fixture Updated';
    $secondVersion = yovel_admin_upsert_hr_builder_form_version(
        $db,
        $changedVersionFixture,
        (string) $fixtureScope['admin_key']
    );
    hr_database_assert((int) ($firstVersion['version_number'] ?? 0) === 1, 'The first immutable form version was not version 1.');
    hr_database_assert((int) ($secondVersion['version_number'] ?? 0) === 2, 'Changed form content did not create version 2.');
    $preservedFirstTitle = (string) $db->GetOne(
        'SELECT form_title FROM project_company_hr_builder_form_version WHERE form_version_key = ? LIMIT 1',
        [(string) $firstVersion['form_version_key']]
    );
    hr_database_assert($preservedFirstTitle === 'Version Fixture', 'Creating version 2 modified immutable version 1.');
} finally {
    $db->RollbackTrans();
}

hr_database_assert(function_exists('yovel_admin_persist_hr_form_submission'), 'Generic HR form submission persistence is not implemented.');
hr_database_assert($db->BeginTrans() !== false, 'Form submission fixture transaction could not start.');
try {
    $submissionEmployeeKey = bx_uuid();
    $submissionEmployeeCode = 'TEST_SUBMIT_' . strtoupper(substr(str_replace('-', '', $submissionEmployeeKey), 0, 10));
    hr_database_assert($db->Execute(
        "INSERT INTO project_company_hr_employee (
            employee_key, company_key, company_key_hash, employee_code, first_name, employee_name,
            employee_status, branch_key, created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, 'Submission', 'Submission Fixture', 'ACTIVE', ?, ?, ?)",
        [
            $submissionEmployeeKey,
            (string) $fixtureScope['company_key'],
            (string) $fixtureScope['company_key_hash'],
            $submissionEmployeeCode,
            (string) $fixtureScope['branch_key'],
            (string) $fixtureScope['admin_key'],
            (string) $fixtureScope['admin_key'],
        ]
    ) !== false, 'Form submission fixture employee could not be created.');

    $submissionFormKey = bx_uuid();
    $submissionSchema = yovel_admin_normalize_hr_builder_schema(json_encode([
        'version' => 1,
        'questions' => [
            ['key' => 'short-name', 'type' => 'SHORT_TEXT', 'label' => 'Short name', 'required' => true],
            ['key' => 'notes', 'type' => 'PARAGRAPH', 'label' => 'Notes'],
            ['key' => 'choice', 'type' => 'DROPDOWN', 'label' => 'Choice', 'options' => ['A', 'B']],
            ['key' => 'checks', 'type' => 'CHECKBOXES', 'label' => 'Checks', 'options' => ['One', 'Two']],
            ['key' => 'event-date', 'type' => 'DATE', 'label' => 'Event date'],
            ['key' => 'amount', 'type' => 'NUMBER', 'label' => 'Amount'],
            ['key' => 'email', 'type' => 'EMAIL', 'label' => 'Email'],
            ['key' => 'phone', 'type' => 'PHONE', 'label' => 'Phone'],
            ['key' => 'section', 'type' => 'SECTION', 'label' => 'Details'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $submissionSchemaJson = json_encode($submissionSchema, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    hr_database_assert($db->Execute(
        "INSERT INTO project_company_hr_builder_form (
            builder_form_key, company_key, company_key_hash, target_section, form_title,
            form_description, form_status, schema_json, question_count,
            created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, 'employee-profiles', 'Submission Fixture Form', 'Submission persistence test.', 'ACTIVE', ?, ?, ?, ?)",
        [
            $submissionFormKey,
            (string) $fixtureScope['company_key'],
            (string) $fixtureScope['company_key_hash'],
            $submissionSchemaJson,
            count($submissionSchema['questions']),
            (string) $fixtureScope['admin_key'],
            (string) $fixtureScope['admin_key'],
        ]
    ) !== false, 'Form submission fixture definition could not be created.');
    $submissionForm = [
        'builder_form_key' => $submissionFormKey,
        'company_key' => (string) $fixtureScope['company_key'],
        'company_key_hash' => (string) $fixtureScope['company_key_hash'],
        'target_section' => 'employee-profiles',
        'form_title' => 'Submission Fixture Form',
        'form_description' => 'Submission persistence test.',
        'form_status' => 'ACTIVE',
        'schema_json' => $submissionSchemaJson,
        'question_count' => count($submissionSchema['questions']),
    ];
    $submissionVersionOne = yovel_admin_upsert_hr_builder_form_version(
        $db,
        $submissionForm,
        (string) $fixtureScope['admin_key']
    );
    $submissionPayload = [
        'submission_key' => '',
        'builder_form_key' => $submissionFormKey,
        'form_version_key' => (string) $submissionVersionOne['form_version_key'],
        'module_form_key' => '',
        'subject_type' => 'EMPLOYEE',
        'subject_key' => $submissionEmployeeKey,
        'submission_status' => 'SUBMITTED',
        'answers' => [
            'short-name' => 'Kim',
            'notes' => 'Database driven response',
            'choice' => 'B',
            'checks' => ['One', 'Two'],
            'event-date' => '2026-08-25',
            'amount' => '88.50',
            'email' => 'kim@example.test',
            'phone' => '+63 900 000 0000',
        ],
    ];
    $savedSubmission = yovel_admin_persist_hr_form_submission(
        $db,
        $fixtureCompany,
        $fixtureAdmin,
        $submissionPayload
    );
    $savedSubmissionKey = (string) ($savedSubmission['submission_key'] ?? '');
    hr_database_assert(yovel_admin_is_uuid($savedSubmissionKey), 'Form submission did not preserve a stable key.');
    hr_database_assert((string) ($savedSubmission['form_version_key'] ?? '') === (string) $submissionVersionOne['form_version_key'], 'Form submission was not pinned to version 1.');
    $savedSubmissionValues = $db->GetAll(
        'SELECT question_key, field_type, value_text, value_number, value_date, value_json FROM project_form_submission_value WHERE submission_key = ? ORDER BY question_key',
        [$savedSubmissionKey]
    );
    hr_database_assert(is_array($savedSubmissionValues) && count($savedSubmissionValues) === 8, 'Form submission values were not persisted once per answered question.');
    $savedSubmissionByQuestion = [];
    foreach ($savedSubmissionValues as $savedSubmissionValue) {
        $savedSubmissionByQuestion[(string) $savedSubmissionValue['question_key']] = $savedSubmissionValue;
    }
    hr_database_assert((string) ($savedSubmissionByQuestion['short-name']['value_text'] ?? '') === 'Kim', 'Short text submission value was not typed.');
    hr_database_assert((float) ($savedSubmissionByQuestion['amount']['value_number'] ?? 0) === 88.5, 'Number submission value was not typed.');
    hr_database_assert((string) ($savedSubmissionByQuestion['event-date']['value_date'] ?? '') === '2026-08-25', 'Date submission value was not typed.');
    hr_database_assert(json_decode((string) ($savedSubmissionByQuestion['checks']['value_json'] ?? ''), true) === ['One', 'Two'], 'Checkbox submission values were not normalized.');

    $submissionFormVersionTwo = $submissionForm;
    $submissionFormVersionTwo['form_title'] = 'Submission Fixture Form v2';
    $versionTwo = yovel_admin_upsert_hr_builder_form_version(
        $db,
        $submissionFormVersionTwo,
        (string) $fixtureScope['admin_key']
    );
    $updatePayload = $submissionPayload;
    $updatePayload['submission_key'] = $savedSubmissionKey;
    $updatePayload['form_version_key'] = (string) $versionTwo['form_version_key'];
    $updatePayload['submission_status'] = 'DRAFT';
    $updatePayload['answers']['notes'] = '';
    $updatedSubmission = yovel_admin_persist_hr_form_submission(
        $db,
        $fixtureCompany,
        $fixtureAdmin,
        $updatePayload
    );
    hr_database_assert((string) ($updatedSubmission['form_version_key'] ?? '') === (string) $submissionVersionOne['form_version_key'], 'Updating a submission changed its pinned form version.');
    $clearedSubmissionValue = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_form_submission_value WHERE submission_key = ? AND question_key = 'notes'",
        [$savedSubmissionKey]
    );
    hr_database_assert($clearedSubmissionValue === 0, 'Clearing an optional submission answer left an empty row.');

    foreach ([
        'unknown question' => array_replace_recursive($submissionPayload, ['answers' => ['unknown-key' => 'value']]),
        'missing required answer' => array_replace_recursive($submissionPayload, ['answers' => ['short-name' => '']]),
        'invalid dropdown option' => array_replace_recursive($submissionPayload, ['answers' => ['choice' => 'C']]),
        'invalid number' => array_replace_recursive($submissionPayload, ['answers' => ['amount' => 'not-a-number']]),
        'invalid date' => array_replace_recursive($submissionPayload, ['answers' => ['event-date' => '2026-02-31']]),
        'invalid email' => array_replace_recursive($submissionPayload, ['answers' => ['email' => 'not-an-email']]),
        'unknown employee' => array_replace($submissionPayload, ['subject_key' => bx_uuid()]),
        'mismatched module form' => array_replace($submissionPayload, ['module_form_key' => bx_uuid()]),
    ] as $case => $invalidPayload) {
        $rejected = false;
        try {
            yovel_admin_persist_hr_form_submission($db, $fixtureCompany, $fixtureAdmin, $invalidPayload);
        } catch (InvalidArgumentException) {
            $rejected = true;
        }
        hr_database_assert($rejected, 'Form submission accepted an invalid case: ' . $case);
    }
} finally {
    $db->RollbackTrans();
}

echo json_encode([
    'schema_verified' => true,
    'employee_keys_preserved' => true,
    'assignment_migration_idempotent' => true,
    'form_version_migration_idempotent' => true,
    'assignment_persistence_verified' => true,
    'typed_custom_values_verified' => true,
    'stable_question_keys_verified' => true,
    'immutable_form_versions_verified' => true,
    'form_submissions_verified' => true,
    'employee_count' => count(is_array($employeeKeysAfter) ? $employeeKeysAfter : []),
    'assignment_count' => $assignmentCountAfterSecondRun,
    'form_version_count' => $versionCountAfterSecondRun,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
