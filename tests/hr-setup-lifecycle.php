<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/hr/functions.php';
require_once $root . '/company/admin/modules/shared/forms.php';

function hr_setup_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ([
    'yovel_admin_hr_setup_data',
    'yovel_admin_hr_setup_form_targets',
    'yovel_admin_hr_scope',
    'yovel_admin_persist_hr_setup_record',
    'yovel_admin_persist_employee_transfer',
    'yovel_admin_persist_employee_promotion',
    'yovel_admin_hr_organization_chart',
    'yovel_admin_hr_handle_post',
] as $function) {
    hr_setup_assert(function_exists($function), 'Missing HR-WP-01 interface: ' . $function);
}

$db = bx_db();
yovel_admin_hr_schema();

$requiredTables = [
    'project_company_hr_setting',
    'project_company_hr_setup_master',
    'project_company_hr_employee_lifecycle',
    'project_company_hr_employee_property_history',
];
foreach ($requiredTables as $table) {
    $exists = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    hr_setup_assert($exists === 1, 'Missing HR-WP-01 table: ' . $table);
}

$scope = $db->GetRow("
    SELECT company_record.company_key, company_record.company_key_hash, company_record.company_name,
           admin_record.admin_key, branch_record.branch_key,
           COALESCE(project_record.project_key, '') AS project_key
    FROM project_company company_record
    INNER JOIN project_company_admin admin_record
        ON admin_record.company_key_hash = company_record.company_key_hash
       AND admin_record.company_key = company_record.company_key
       AND admin_record.admin_status = 'ACTIVE'
    INNER JOIN project_company_branch branch_record
        ON branch_record.company_key_hash = company_record.company_key_hash
       AND branch_record.branch_status <> 'DELETED'
    LEFT JOIN project_company_project project_record
        ON project_record.company_key_hash = company_record.company_key_hash
       AND project_record.branch_key = branch_record.branch_key
       AND project_record.project_status <> 'DELETED'
    WHERE company_record.company_status = 'ACTIVE'
    ORDER BY company_record.x_id, branch_record.x_id, project_record.x_id
    LIMIT 1
");
hr_setup_assert(is_array($scope) && $scope !== [], 'An active company/admin/branch fixture is required.');

$company = [
    'company_key' => (string) $scope['company_key'],
    'company_key_hash' => (string) $scope['company_key_hash'],
    'company_name' => (string) $scope['company_name'],
];
$admin = ['admin_key' => (string) $scope['admin_key']];
$settingsBefore = $db->GetRow('SELECT * FROM project_company_hr_setting WHERE company_key_hash = ? LIMIT 1', [(string) $scope['company_key_hash']]);
[$companyKey, $companyKeyHash, $adminKey] = yovel_admin_hr_scope($company, $admin);
hr_setup_assert($companyKey === (string) $scope['company_key'], 'Authorized HR scope changed the company key.');
hr_setup_assert($companyKeyHash === (string) $scope['company_key_hash'], 'Authorized HR scope changed the company hash.');
hr_setup_assert($adminKey === (string) $scope['admin_key'], 'Authorized HR scope changed the administrator key.');

$foreignScopeRejected = false;
try {
    yovel_admin_hr_scope([
        'company_key' => $companyKey,
        'company_key_hash' => str_repeat('f', 64),
    ], $admin);
} catch (Throwable) {
    $foreignScopeRejected = true;
}
hr_setup_assert($foreignScopeRejected, 'HR setup accepted an administrator outside the company scope.');

$token = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$createdKeys = [];
$masterKeys = [];
$auditKeys = [];
$employeeKeys = [];
$lifecycleByType = [];
$jobPositionKey = bx_uuid();
$teamKey = bx_uuid();
$approverUserKey = bx_uuid();
$departmentKey = (string) $db->GetOne(
    "SELECT department_key FROM project_company_department
     WHERE company_key_hash = ? AND department_status <> 'DELETED'
     ORDER BY x_id LIMIT 1",
    [$companyKeyHash]
);
hr_setup_assert(yovel_admin_is_uuid($departmentKey), 'HR-WP-01 requires a company department fixture.');

try {
    foreach ([
        ['EMPLOYMENT_TYPE', 'TYPE_' . $token, 'Seasonal', []],
        ['EMPLOYEE_GRADE', 'GRADE_' . $token, 'Grade A', [
            'default_salary_structure' => 'Monthly Staff',
            'currency' => 'PHP',
            'default_base_pay' => '35000.00',
        ]],
        ['GRIEVANCE_TYPE', 'GRIEV_' . $token, 'Workplace concern', []],
        ['INTEREST', 'INT_' . $token, 'Mentoring', []],
        ['EMPLOYEE_HEALTH_INSURANCE', 'HEALTH_' . $token, 'Fixture Health', []],
    ] as [$type, $code, $name, $metadata]) {
        $saved = yovel_admin_persist_hr_setup_record($db, $company, $admin, $type, [
            'record_code' => $code,
            'record_name' => $name,
            'record_status' => 'ACTIVE',
            'metadata' => $metadata,
        ]);
        hr_setup_assert((string) ($saved['record_type'] ?? '') === $type, $type . ' read-back returned the wrong type.');
        hr_setup_assert((string) ($saved['record_code'] ?? '') === $code, $type . ' read-back returned the wrong code.');
        $createdKeys[] = (string) $saved['setup_master_key'];
        $masterKeys[$type] = (string) $saved['setup_master_key'];
        $auditKeys[] = (string) $saved['setup_master_key'];
    }
    $gradeMetadata = json_decode((string) $db->GetOne(
        'SELECT metadata_json FROM project_company_hr_setup_master WHERE company_key_hash = ? AND setup_master_key = ?',
        [$companyKeyHash, $masterKeys['EMPLOYEE_GRADE']]
    ), true, 512, JSON_THROW_ON_ERROR);
    hr_setup_assert(($gradeMetadata['currency'] ?? '') === 'PHP', 'Employee Grade did not retain its salary metadata.');

    $duplicateRejected = false;
    try {
        yovel_admin_persist_hr_setup_record($db, $company, $admin, 'EMPLOYMENT_TYPE', [
            'record_code' => 'TYPE_' . $token,
            'record_name' => 'Duplicate seasonal',
            'record_status' => 'ACTIVE',
        ]);
    } catch (InvalidArgumentException) {
        $duplicateRejected = true;
    }
    hr_setup_assert($duplicateRejected, 'HR setup allowed duplicate active master codes.');

    $settings = yovel_admin_persist_hr_setup_record($db, $company, $admin, 'HR_SETTINGS', [
        'employee_naming_mode' => 'SERIES',
        'employee_number_prefix' => 'HR-' . substr($token, 0, 4) . '-',
        'default_retirement_age' => '60',
        'allow_employee_self_service' => '1',
        'settings' => [
            'standard_working_hours' => '8',
            'send_birthday_reminders' => true,
            'expense_approver_mandatory' => true,
            'shift_assignment_threshold' => '12',
        ],
        'record_status' => 'ACTIVE',
    ]);
    hr_setup_assert((string) ($settings['employee_naming_mode'] ?? '') === 'SERIES', 'HR Settings read-back failed.');
    $settingsPolicy = json_decode((string) ($settings['settings_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    hr_setup_assert(($settingsPolicy['standard_working_hours'] ?? '') === '8', 'HR Settings policy JSON was not persisted.');
    $auditKeys[] = (string) $settings['hr_setting_key'];

    yovel_admin_db_execute(
        $db,
        "INSERT INTO project_company_user (
            user_key, company_key, company_key_hash, user_login, user_password_hash,
            user_name, user_email, user_status, user_created_by_key, user_updated_by_key
         ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
        [
            $approverUserKey, $companyKey, $companyKeyHash, 'wp01_' . strtolower($token),
            password_hash('HR-WP-01-fixture', PASSWORD_DEFAULT), 'HR Approver',
            'wp01_' . strtolower($token) . '@example.test', $adminKey, $adminKey,
        ],
        'HR-WP-01 fixture approver user'
    );

    yovel_admin_db_execute(
        $db,
        "INSERT INTO project_company_hr_job_position (
            job_position_key, company_key, company_key_hash, job_position_code, job_position_name,
            job_position_status, created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
        [$jobPositionKey, $companyKey, $companyKeyHash, 'WP01_' . $token, 'WP01 Position', $adminKey, $adminKey],
        'HR-WP-01 fixture position'
    );
    yovel_admin_db_execute(
        $db,
        "INSERT INTO project_company_hr_team (
            team_key, company_key, company_key_hash, team_code, team_name, team_status,
            created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
        [$teamKey, $companyKey, $companyKeyHash, 'WP01_' . $token, 'WP01 Team', $adminKey, $adminKey],
        'HR-WP-01 fixture team'
    );

    foreach (['Subject', 'Manager', 'Second Manager'] as $index => $name) {
        $employeeKey = bx_uuid();
        $employeeKeys[] = $employeeKey;
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_hr_employee (
                employee_key, company_key, company_key_hash, employee_code, first_name, employee_name,
                employee_status, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
            [$employeeKey, $companyKey, $companyKeyHash, 'WP01_' . $token . '_' . $index, $name, $name, $adminKey, $adminKey],
            'HR-WP-01 fixture employee'
        );
    }
    [$subjectKey, $managerKey, $secondManagerKey] = $employeeKeys;

    yovel_admin_upsert_hr_employee_primary_assignment($db, $company, $admin, $subjectKey, [
        'branch_key' => (string) $scope['branch_key'],
        'project_key' => (string) $scope['project_key'],
        'department_key' => '',
        'job_position_key' => '',
        'team_key' => '',
        'reports_to_employee_key' => '',
        'effective_from' => '2026-08-01',
    ]);

    $transfer = yovel_admin_persist_employee_transfer($db, $company, $admin, [
        'employee_key' => $subjectKey,
        'effective_date' => '2026-08-25',
        'transfer_status' => 'SUBMITTED',
        'branch_key' => (string) $scope['branch_key'],
        'project_key' => (string) $scope['project_key'],
        'department_key' => '',
        'job_position_key' => '',
        'team_key' => $teamKey,
        'reports_to_employee_key' => $managerKey,
        'property_changes' => [
            ['fieldname' => 'team_key', 'property' => 'Team'],
            ['fieldname' => 'reports_to_employee_key', 'property' => 'Reports To'],
        ],
        'reason' => 'Approved operational transfer.',
    ]);
    hr_setup_assert((string) ($transfer['transfer_status'] ?? '') === 'SUBMITTED', 'Transfer lifecycle was not persisted.');
    $createdKeys[] = (string) $transfer['lifecycle_key'];
    $auditKeys[] = (string) $transfer['lifecycle_key'];
    $activePrimaryAssignments = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_hr_employee_assignment
         WHERE company_key_hash = ? AND employee_key = ? AND assignment_status = 'ACTIVE' AND is_primary = 1",
        [$companyKeyHash, $subjectKey]
    );
    hr_setup_assert($activePrimaryAssignments === 1, 'Transfer left multiple active assignments.');

    $assignmentBeforeRollback = (string) $db->GetOne(
        "SELECT assignment_key FROM project_company_hr_employee_assignment
         WHERE company_key_hash = ? AND employee_key = ? AND assignment_status = 'ACTIVE' AND is_primary = 1",
        [$companyKeyHash, $subjectKey]
    );
    $transferCountBeforeRollback = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_hr_employee_lifecycle
         WHERE company_key_hash = ? AND employee_key = ? AND record_type = 'TRANSFER'",
        [$companyKeyHash, $subjectKey]
    );
    $rollbackTriggered = false;
    try {
        yovel_admin_persist_employee_transfer($db, $company, $admin, [
            'employee_key' => $subjectKey,
            'effective_date' => '2026-08-26',
            'transfer_status' => 'SUBMITTED',
            'branch_key' => (string) $scope['branch_key'],
            'project_key' => (string) $scope['project_key'],
            'department_key' => '',
            'job_position_key' => '',
            'team_key' => '',
            'reports_to_employee_key' => $secondManagerKey,
            'reason' => 'Rollback fixture.',
        ], static function (): void {
            throw new RuntimeException('Injected transfer rollback.');
        });
    } catch (RuntimeException $error) {
        $rollbackTriggered = $error->getMessage() === 'Injected transfer rollback.';
    }
    hr_setup_assert($rollbackTriggered, 'Transfer rollback injection did not execute.');
    hr_setup_assert((string) $db->GetOne(
        "SELECT assignment_key FROM project_company_hr_employee_assignment
         WHERE company_key_hash = ? AND employee_key = ? AND assignment_status = 'ACTIVE' AND is_primary = 1",
        [$companyKeyHash, $subjectKey]
    ) === $assignmentBeforeRollback, 'Transfer rollback changed the active assignment.');
    hr_setup_assert((int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_hr_employee_lifecycle
         WHERE company_key_hash = ? AND employee_key = ? AND record_type = 'TRANSFER'",
        [$companyKeyHash, $subjectKey]
    ) === $transferCountBeforeRollback, 'Transfer rollback left a lifecycle record.');

    yovel_admin_upsert_hr_employee_primary_assignment($db, $company, $admin, $subjectKey, [
        'branch_key' => (string) $scope['branch_key'],
        'project_key' => (string) $scope['project_key'],
        'department_key' => '',
        'job_position_key' => '',
        'team_key' => '',
        'reports_to_employee_key' => $secondManagerKey,
        'effective_from' => '2026-08-25',
    ]);
    yovel_admin_upsert_hr_employee_primary_assignment($db, $company, $admin, $managerKey, [
        'branch_key' => (string) $scope['branch_key'],
        'project_key' => (string) $scope['project_key'],
        'department_key' => '',
        'job_position_key' => '',
        'team_key' => '',
        'reports_to_employee_key' => $subjectKey,
        'effective_from' => '2026-08-25',
    ]);
    $cycleRejected = false;
    try {
        yovel_admin_upsert_hr_employee_primary_assignment($db, $company, $admin, $subjectKey, [
            'branch_key' => (string) $scope['branch_key'],
            'project_key' => (string) $scope['project_key'],
            'department_key' => '',
            'job_position_key' => '',
            'team_key' => '',
            'reports_to_employee_key' => $managerKey,
            'effective_from' => '2026-08-25',
        ]);
    } catch (InvalidArgumentException) {
        $cycleRejected = true;
    }
    hr_setup_assert($cycleRejected, 'Employee hierarchy accepted a reporting cycle.');

    $promotion = yovel_admin_persist_employee_promotion($db, $company, $admin, [
        'employee_key' => $subjectKey,
        'effective_date' => '2026-09-01',
        'promotion_status' => 'APPROVED',
        'job_position_key' => $jobPositionKey,
        'employee_grade_key' => $masterKeys['EMPLOYEE_GRADE'],
        'current_ctc' => '420000.00',
        'revised_ctc' => '510000.00',
        'reason' => 'Promotion fixture.',
    ]);
    hr_setup_assert((string) ($promotion['promotion_status'] ?? '') === 'APPROVED', 'Promotion approval state was not persisted.');
    $createdKeys[] = (string) $promotion['lifecycle_key'];
    $auditKeys[] = (string) $promotion['lifecycle_key'];
    hr_setup_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_hr_employee_property_history WHERE company_key_hash = ? AND source_lifecycle_key = ?',
        [$companyKeyHash, (string) $promotion['lifecycle_key']]
    ) >= 3, 'Promotion did not create source-shaped immutable property history.');
    hr_setup_assert((string) $db->GetOne(
        "SELECT new_value FROM project_company_hr_employee_property_history
         WHERE company_key_hash = ? AND source_lifecycle_key = ? AND property_name = 'annual_ctc'",
        [$companyKeyHash, (string) $promotion['lifecycle_key']]
    ) === '510000.00', 'Promotion CTC property history is missing.');

    $immutableRejected = false;
    try {
        yovel_admin_persist_employee_promotion($db, $company, $admin, [
            'lifecycle_key' => (string) $promotion['lifecycle_key'],
            'employee_key' => $subjectKey,
            'effective_date' => '2026-09-02',
            'promotion_status' => 'APPROVED',
            'job_position_key' => $jobPositionKey,
            'employee_grade_key' => $masterKeys['EMPLOYEE_GRADE'],
            'reason' => 'Mutated promotion fixture.',
        ]);
    } catch (InvalidArgumentException) {
        $immutableRejected = true;
    }
    hr_setup_assert($immutableRejected, 'An approved promotion was mutated in place.');

    foreach ([
        ['DEPARTMENT_APPROVER', [
            'employee_key' => $managerKey, 'department_key' => $departmentKey,
            'approver_user_key' => $approverUserKey, 'effective_date' => '2026-08-25', 'record_status' => 'ACTIVE',
        ]],
        ['APPRAISEE', [
            'employee_key' => $subjectKey, 'appraisal_template_key' => 'ANNUAL-REVIEW-2026',
            'effective_date' => '2026-08-25', 'record_status' => 'ACTIVE',
        ]],
        ['EMPLOYEE_GRIEVANCE', [
            'employee_key' => $subjectKey, 'grievance_type_key' => $masterKeys['GRIEVANCE_TYPE'],
            'effective_date' => '2026-08-25', 'record_status' => 'OPEN',
            'subject' => 'Confidential workplace concern', 'description' => 'Confidential fixture concern.',
            'grievance_against_party' => 'Operations team',
        ]],
    ] as [$type, $input]) {
        $record = yovel_admin_persist_hr_setup_record($db, $company, $admin, $type, $input);
        hr_setup_assert((string) ($record['record_type'] ?? '') === $type, $type . ' did not persist.');
        $createdKeys[] = (string) $record['lifecycle_key'];
        $auditKeys[] = (string) $record['lifecycle_key'];
        $lifecycleByType[$type] = $record;
    }

    $investigated = yovel_admin_persist_hr_setup_record($db, $company, $admin, 'EMPLOYEE_GRIEVANCE', [
        'lifecycle_key' => (string) $lifecycleByType['EMPLOYEE_GRIEVANCE']['lifecycle_key'],
        'employee_key' => $subjectKey,
        'grievance_type_key' => $masterKeys['GRIEVANCE_TYPE'],
        'effective_date' => '2026-08-25',
        'record_status' => 'INVESTIGATED',
        'subject' => 'Confidential workplace concern',
        'description' => 'Confidential fixture concern.',
        'investigation_cause' => 'Verified policy mismatch.',
    ]);
    hr_setup_assert((string) $investigated['record_status'] === 'INVESTIGATED', 'Grievance did not transition to Investigated.');
    $resolved = yovel_admin_persist_hr_setup_record($db, $company, $admin, 'EMPLOYEE_GRIEVANCE', [
        'lifecycle_key' => (string) $investigated['lifecycle_key'],
        'employee_key' => $subjectKey,
        'grievance_type_key' => $masterKeys['GRIEVANCE_TYPE'],
        'effective_date' => '2026-08-25',
        'record_status' => 'RESOLVED',
        'subject' => 'Confidential workplace concern',
        'description' => 'Confidential fixture concern.',
        'investigation_cause' => 'Verified policy mismatch.',
        'resolution_detail' => 'Policy was corrected and communicated.',
        'resolution_date' => '2026-08-27',
    ]);
    hr_setup_assert((string) $resolved['record_status'] === 'RESOLVED', 'Grievance did not transition to Resolved.');
    $resolvedMutationRejected = false;
    try {
        yovel_admin_persist_hr_setup_record($db, $company, $admin, 'EMPLOYEE_GRIEVANCE', [
            'lifecycle_key' => (string) $resolved['lifecycle_key'],
            'employee_key' => $subjectKey,
            'grievance_type_key' => $masterKeys['GRIEVANCE_TYPE'],
            'effective_date' => '2026-08-25',
            'record_status' => 'OPEN',
            'subject' => 'Reopened improperly',
            'description' => 'This mutation must be rejected.',
        ]);
    } catch (InvalidArgumentException) {
        $resolvedMutationRejected = true;
    }
    hr_setup_assert($resolvedMutationRejected, 'A resolved grievance was mutated in place.');

    $grievancePayload = json_decode((string) $db->GetOne(
        "SELECT payload_json FROM project_company_hr_employee_lifecycle
         WHERE company_key_hash = ? AND employee_key = ? AND record_type = 'EMPLOYEE_GRIEVANCE'",
        [$companyKeyHash, $subjectKey]
    ), true, 512, JSON_THROW_ON_ERROR);
    hr_setup_assert(($grievancePayload['subject'] ?? '') === 'Confidential workplace concern', 'Grievance subject was not retained.');
    $approverPayload = json_decode((string) $db->GetOne(
        "SELECT payload_json FROM project_company_hr_employee_lifecycle
         WHERE company_key_hash = ? AND record_type = 'DEPARTMENT_APPROVER' ORDER BY x_id DESC LIMIT 1",
        [$companyKeyHash]
    ), true, 512, JSON_THROW_ON_ERROR);
    hr_setup_assert(($approverPayload['approver_user_key'] ?? '') === $approverUserKey, 'Department Approver did not retain its company user.');

    $setupData = yovel_admin_hr_setup_data($company);
    foreach (['settings', 'masters', 'lifecycle', 'propertyHistory', 'organizationChart', 'formTargets'] as $key) {
        hr_setup_assert(array_key_exists($key, $setupData), 'HR Setup data is missing ' . $key . '.');
    }
    $chartKeys = array_column($setupData['organizationChart'], 'employee_key');
    hr_setup_assert(in_array($subjectKey, $chartKeys, true), 'Organization chart omitted the transferred employee.');

    foreach ($auditKeys as $recordKey) {
        hr_setup_assert((int) $db->GetOne(
            'SELECT COUNT(*) FROM builder_audit_log WHERE record_key = ?',
            [$recordKey]
        ) >= 1, 'HR-WP-01 write has no audit evidence: ' . $recordKey);
    }
} finally {
    if ($employeeKeys !== []) {
        $placeholders = implode(',', array_fill(0, count($employeeKeys), '?'));
        $db->Execute("DELETE FROM project_company_hr_employee_property_history WHERE company_key_hash = ? AND employee_key IN ($placeholders)", array_merge([$companyKeyHash], $employeeKeys));
        $db->Execute("DELETE FROM project_company_hr_employee_lifecycle WHERE company_key_hash = ? AND employee_key IN ($placeholders)", array_merge([$companyKeyHash], $employeeKeys));
        $db->Execute("DELETE FROM project_company_hr_employee_assignment WHERE company_key_hash = ? AND employee_key IN ($placeholders)", array_merge([$companyKeyHash], $employeeKeys));
        $db->Execute("DELETE FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_key IN ($placeholders)", array_merge([$companyKeyHash], $employeeKeys));
    }
    if ($createdKeys !== []) {
        $placeholders = implode(',', array_fill(0, count($createdKeys), '?'));
        $db->Execute("DELETE FROM project_company_hr_setup_master WHERE company_key_hash = ? AND setup_master_key IN ($placeholders)", array_merge([$companyKeyHash], $createdKeys));
    }
    if (is_array($settingsBefore) && $settingsBefore !== []) {
        $db->Execute(
            "UPDATE project_company_hr_setting
             SET employee_naming_mode = ?, employee_number_prefix = ?, default_retirement_age = ?,
                 allow_employee_self_service = ?, settings_json = ?, setting_status = ?, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND hr_setting_key = ?",
            [
                $settingsBefore['employee_naming_mode'], $settingsBefore['employee_number_prefix'],
                $settingsBefore['default_retirement_age'], $settingsBefore['allow_employee_self_service'],
                $settingsBefore['settings_json'] ?? '{}', $settingsBefore['setting_status'], $settingsBefore['updated_by_admin_key'],
                $companyKeyHash, $settingsBefore['hr_setting_key'],
            ]
        );
    } else {
        $db->Execute('DELETE FROM project_company_hr_setting WHERE company_key_hash = ?', [$companyKeyHash]);
    }
    $db->Execute('DELETE FROM project_company_hr_job_position WHERE company_key_hash = ? AND job_position_key = ?', [$companyKeyHash, $jobPositionKey]);
    $db->Execute('DELETE FROM project_company_hr_team WHERE company_key_hash = ? AND team_key = ?', [$companyKeyHash, $teamKey]);
    $db->Execute('DELETE FROM project_company_user WHERE company_key_hash = ? AND user_key = ?', [$companyKeyHash, $approverUserKey]);
    if ($auditKeys !== []) {
        $placeholders = implode(',', array_fill(0, count($auditKeys), '?'));
        $db->Execute("DELETE FROM builder_audit_log WHERE record_key IN ($placeholders)", $auditKeys);
    }
    foreach ($employeeKeys as $employeeKey) {
        $db->Execute(
            "DELETE FROM builder_audit_log
             WHERE module IN ('project_company_hr_employee_assignment', 'project_company_hr_employee_property_history')
               AND new_values LIKE ?",
            ['%' . $employeeKey . '%']
        );
    }
}

$targets = yovel_admin_hr_setup_form_targets();
$defaultSchemas = yovel_admin_hr_default_form_fields();
$sharedHrAdapter = yovel_admin_shared_form_adapter('hr');
foreach ([
    'hr-settings',
    'employment-type',
    'employee-grade',
    'employee-transfer',
    'employee-promotion',
    'employee-grievance',
    'employee-health-insurance',
    'department-approver',
    'appraisee',
] as $target) {
    hr_setup_assert(isset($targets[$target]), 'HR Setup Form Builder target is missing: ' . $target);
    hr_setup_assert(($targets[$target]['protected_fields'] ?? []) !== [], 'HR Setup target has no protected system fields: ' . $target);
    hr_setup_assert(($targets[$target]['versioned'] ?? false) === true, 'HR Setup target is not versioned: ' . $target);
    hr_setup_assert(isset($defaultSchemas[$target]), 'HR Setup target has no module-local field schema: ' . $target);
    hr_setup_assert(
        array_intersect($targets[$target]['protected_fields'], array_column($defaultSchemas[$target], 0)) !== [],
        'HR Setup form schema does not expose any protected workflow field: ' . $target
    );
    hr_setup_assert(isset($sharedHrAdapter['protected_fields'][$target]), 'Shared Form Builder adapter did not receive HR Setup protection metadata: ' . $target);
}

$workspacePath = $root . '/company/admin/modules/hr/views/setup.php';
hr_setup_assert(is_file($workspacePath), 'The HR Setup workspace is missing.');
$workspace = (string) file_get_contents($workspacePath);
foreach ([
    'grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr)',
    'data-record-modal-open=',
    'data-record-modal',
    'data-confirm-submit',
    'name="module_view" value="hr"',
    'name="section" value="dashboard"',
    'name="workspace" value="setup"',
] as $marker) {
    hr_setup_assert(str_contains($workspace, $marker), 'HR Setup workspace is missing contract marker: ' . $marker);
}
hr_setup_assert(!str_contains($workspace, '<form') || str_contains($workspace, 'data-confirm-submit'), 'An HR Setup form bypasses confirmation.');

echo "HR setup and employee lifecycle checks passed.\n";
