<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/hr/functions.php';
require_once $root . '/company/admin/modules/shared/forms.php';

function hr_onboarding_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ([
    'yovel_admin_hr_onboarding_form_targets',
    'yovel_admin_persist_identification_document_type',
    'yovel_admin_persist_boarding_template',
    'yovel_admin_start_employee_onboarding',
    'yovel_admin_complete_boarding_activity',
    'yovel_admin_persist_employee_document_requirement',
    'yovel_admin_complete_employee_onboarding',
    'yovel_admin_persist_employee_separation',
    'yovel_admin_transition_employee_separation',
    'yovel_admin_schedule_exit_interview',
    'yovel_admin_transition_exit_interview',
    'yovel_admin_persist_full_and_final_statement',
    'yovel_admin_create_employee_onboarding_from_offer',
    'yovel_admin_hr_onboarding_data',
    'yovel_admin_hr_handle_onboarding_post',
] as $function) {
    hr_onboarding_assert(function_exists($function), 'Missing HR-WP-05 interface: ' . $function);
}

$db = bx_db();
yovel_admin_hr_schema();

foreach ([
    'project_company_hr_identification_document_type',
    'project_company_hr_boarding_template',
    'project_company_hr_boarding_template_activity',
    'project_company_hr_employee_onboarding',
    'project_company_hr_employee_separation',
    'project_company_hr_employee_boarding_activity',
    'project_company_hr_employee_document_requirement',
    'project_company_hr_exit_interview',
    'project_company_hr_full_and_final_statement',
    'project_company_hr_full_and_final_asset',
    'project_company_hr_full_and_final_outstanding_statement',
] as $table) {
    hr_onboarding_assert((int) $db->GetOne('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [BUILDERX_DB_NAME, $table]) === 1, 'Missing HR-WP-05 table: ' . $table);
}

$scope = $db->GetRow("
    SELECT company.company_key, company.company_key_hash, company.company_name, admin.admin_key,
           branch.branch_key, COALESCE(department.department_key, '') AS department_key,
           COALESCE(position.job_position_key, '') AS job_position_key
    FROM project_company company
    INNER JOIN project_company_admin admin ON admin.company_key_hash = company.company_key_hash AND admin.company_key = company.company_key AND admin.admin_status = 'ACTIVE'
    INNER JOIN project_company_branch branch ON branch.company_key_hash = company.company_key_hash AND branch.branch_status <> 'DELETED'
    LEFT JOIN project_company_department department ON department.company_key_hash = company.company_key_hash AND department.department_status <> 'DELETED'
    LEFT JOIN project_company_hr_job_position position ON position.company_key_hash = company.company_key_hash AND position.job_position_status <> 'DELETED'
    WHERE company.company_status = 'ACTIVE'
    ORDER BY company.x_id, branch.x_id, department.x_id, position.x_id
    LIMIT 1
");
hr_onboarding_assert(is_array($scope) && $scope !== [], 'An active HR onboarding company fixture is required.');

$company = ['company_key' => (string) $scope['company_key'], 'company_key_hash' => (string) $scope['company_key_hash'], 'company_name' => (string) $scope['company_name']];
$admin = ['admin_key' => (string) $scope['admin_key']];
$companyKey = (string) $scope['company_key'];
$hash = (string) $scope['company_key_hash'];
$adminKey = (string) $scope['admin_key'];
$token = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$employeeKey = bx_uuid();
$managerKey = bx_uuid();
$keys = [];

try {
    foreach ([[$employeeKey, 'Boarding Employee'], [$managerKey, 'Boarding Manager']] as $index => [$fixtureKey, $name]) {
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_employee (
            employee_key, company_key, company_key_hash, employee_code, first_name, employee_name,
            employee_status, branch_key, department_key, job_position_key, created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?, ?, ?)", [
            $fixtureKey, $companyKey, $hash, 'WP05_' . $token . '_' . $index,
            'WP05', $name, (string) $scope['branch_key'], ($scope['department_key'] ?: null),
            ($scope['job_position_key'] ?: null), $adminKey, $adminKey,
        ], 'HR-WP-05 employee fixture');
    }

    $documentType = yovel_admin_persist_identification_document_type($db, $company, $admin, [
        'document_type_code' => 'GOV_' . $token,
        'document_type_name' => 'Government ID',
        'requires_expiry_date' => '1',
    ]);
    $keys['document_type'] = (string) $documentType['identification_document_type_key'];

    $onboardingTemplate = yovel_admin_persist_boarding_template($db, $company, $admin, 'ONBOARDING', [
        'template_code' => 'ONB_' . $token,
        'template_name' => 'Standard Onboarding',
        'activities' => [
            ['activity_name' => 'Collect employee documents', 'owner_employee_key' => $managerKey, 'due_days_offset' => 2, 'sort_order' => 10],
            ['activity_name' => 'Prepare workstation', 'owner_employee_key' => $managerKey, 'due_days_offset' => 3, 'sort_order' => 20],
        ],
    ]);
    $keys['onboarding_template'] = (string) $onboardingTemplate['boarding_template_key'];

    $separationTemplate = yovel_admin_persist_boarding_template($db, $company, $admin, 'SEPARATION', [
        'template_code' => 'SEP_' . $token,
        'template_name' => 'Standard Separation',
        'activities' => [
            ['activity_name' => 'Collect assets', 'owner_employee_key' => $managerKey, 'due_days_offset' => 1, 'sort_order' => 10],
        ],
    ]);
    $keys['separation_template'] = (string) $separationTemplate['boarding_template_key'];

    $onboarding = yovel_admin_start_employee_onboarding($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'boarding_template_key' => $keys['onboarding_template'],
        'start_date' => '2026-09-01',
        'expected_completion_date' => '2026-09-07',
        'onboarding_status' => 'IN_PROGRESS',
    ]);
    $keys['onboarding'] = (string) $onboarding['employee_onboarding_key'];
    hr_onboarding_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_employee_boarding_activity WHERE company_key_hash = ? AND parent_key = ?', [$hash, $keys['onboarding']]) === 2, 'Onboarding template activities were not cloned.');

    $activityKey = (string) $db->GetOne('SELECT employee_boarding_activity_key FROM project_company_hr_employee_boarding_activity WHERE company_key_hash = ? AND parent_key = ? ORDER BY sort_order LIMIT 1', [$hash, $keys['onboarding']]);
    $activity = yovel_admin_complete_boarding_activity($db, $company, $admin, $activityKey, 'Employee documents collected.');
    hr_onboarding_assert((string) $activity['activity_status'] === 'COMPLETED', 'Boarding activity completion failed.');

    $requirement = yovel_admin_persist_employee_document_requirement($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'identification_document_type_key' => $keys['document_type'],
        'context_type' => 'ONBOARDING',
        'context_key' => $keys['onboarding'],
        'requirement_status' => 'SUBMITTED',
        'received_at' => '2026-09-02 09:00:00',
        'expiry_date' => '2030-12-31',
    ]);
    $keys['requirement'] = (string) $requirement['employee_document_requirement_key'];

    $notCompleteYet = false;
    try { yovel_admin_complete_employee_onboarding($db, $company, $admin, $keys['onboarding']); }
    catch (RuntimeException) { $notCompleteYet = true; }
    hr_onboarding_assert($notCompleteYet, 'Onboarding completed while an activity was still open.');

    foreach ($db->GetAll('SELECT employee_boarding_activity_key FROM project_company_hr_employee_boarding_activity WHERE company_key_hash = ? AND parent_key = ? AND activity_status <> ?', [$hash, $keys['onboarding'], 'COMPLETED']) ?: [] as $openActivity) {
        yovel_admin_complete_boarding_activity($db, $company, $admin, (string) $openActivity['employee_boarding_activity_key'], 'Done.');
    }
    $onboarding = yovel_admin_complete_employee_onboarding($db, $company, $admin, $keys['onboarding']);
    hr_onboarding_assert((string) $onboarding['onboarding_status'] === 'COMPLETED', 'Onboarding completion failed.');

    $separation = yovel_admin_persist_employee_separation($db, $company, $admin, [
        'employee_key' => $employeeKey,
        'boarding_template_key' => $keys['separation_template'],
        'resignation_date' => '2026-10-01',
        'relieving_date' => '2026-10-31',
        'reason' => 'Voluntary resignation',
        'separation_status' => 'SUBMITTED',
    ]);
    $keys['separation'] = (string) $separation['employee_separation_key'];
    hr_onboarding_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_employee_boarding_activity WHERE company_key_hash = ? AND parent_key = ?', [$hash, $keys['separation']]) === 1, 'Separation template activities were not cloned.');

    $exit = yovel_admin_schedule_exit_interview($db, $company, $admin, [
        'employee_separation_key' => $keys['separation'],
        'interviewer_employee_key' => $managerKey,
        'scheduled_at' => '2026-10-15 14:00:00',
        'timezone_name' => 'Asia/Manila',
        'questionnaire_json' => json_encode(['reason_for_leaving' => 'Career move'], JSON_THROW_ON_ERROR),
    ]);
    $keys['exit'] = (string) $exit['exit_interview_key'];
    $exit = yovel_admin_transition_exit_interview($db, $company, $admin, $keys['exit'], 'COMPLETED', ['summary' => 'Knowledge transfer complete']);
    hr_onboarding_assert((string) $exit['exit_interview_status'] === 'COMPLETED', 'Exit Interview completion failed.');

    $final = yovel_admin_persist_full_and_final_statement($db, $company, $admin, [
        'employee_separation_key' => $keys['separation'],
        'asset_clearance_status' => 'CLEARED',
        'outstanding_status' => 'CLEARED',
        'statement_status' => 'CLEARED',
        'assets' => [['asset_reference' => 'LAPTOP-' . $token, 'clearance_status' => 'CLEARED', 'remarks' => 'Returned']],
        'outstandings' => [['source_reference' => 'LOAN-' . $token, 'amount' => '0.00', 'clearance_status' => 'CLEARED', 'remarks' => 'No balance']],
    ]);
    $keys['final'] = (string) $final['full_and_final_statement_key'];
    $finalAgain = yovel_admin_persist_full_and_final_statement($db, $company, $admin, [
        'employee_separation_key' => $keys['separation'],
        'asset_clearance_status' => 'CLEARED',
        'outstanding_status' => 'CLEARED',
        'statement_status' => 'CLEARED',
        'assets' => [['asset_reference' => 'LAPTOP-' . $token, 'clearance_status' => 'CLEARED', 'remarks' => 'Returned']],
        'outstandings' => [['source_reference' => 'LOAN-' . $token, 'amount' => '0.00', 'clearance_status' => 'CLEARED', 'remarks' => 'No balance']],
    ]);
    hr_onboarding_assert((string) $finalAgain['full_and_final_statement_key'] === $keys['final'], 'Full-and-final statement is not idempotent per separation.');

    $separation = yovel_admin_transition_employee_separation($db, $company, $admin, $keys['separation'], 'APPROVED');
    hr_onboarding_assert((string) $separation['separation_status'] === 'APPROVED', 'Separation approval failed.');
    hr_onboarding_assert((string) $db->GetOne('SELECT employee_status FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_key = ?', [$hash, $employeeKey]) === 'SEPARATED', 'Approved separation did not synchronize employee status.');
    hr_onboarding_assert((int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_notification_intent WHERE company_key_hash = ? AND record_key IN (?, ?) AND intent_status = 'PENDING'", [$hash, $keys['exit'], $keys['separation']]) >= 2, 'Onboarding/separation notification intents were not persisted.');

    $beforeRollback = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_employee_document_requirement WHERE company_key_hash = ?', [$hash]);
    try {
        yovel_admin_persist_employee_document_requirement($db, $company, $admin, [
            'employee_key' => $employeeKey,
            'identification_document_type_key' => $keys['document_type'],
            'context_type' => 'ONBOARDING',
            'context_key' => $keys['onboarding'],
            'requirement_status' => 'DRAFT',
        ], static function (): void { throw new RuntimeException('Injected rollback.'); });
    } catch (RuntimeException) {}
    hr_onboarding_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_employee_document_requirement WHERE company_key_hash = ?', [$hash]) === $beforeRollback, 'Document rollback left a record.');

    $foreignRejected = false;
    try { yovel_admin_start_employee_onboarding($db, ['company_key' => $companyKey, 'company_key_hash' => str_repeat('f', 64)], $admin, ['employee_key' => $employeeKey, 'boarding_template_key' => $keys['onboarding_template'], 'start_date' => '2026-09-01']); }
    catch (Throwable) { $foreignRejected = true; }
    hr_onboarding_assert($foreignRejected, 'Onboarding accepted a foreign company scope.');
} finally {
    foreach (['exit' => ['project_company_hr_exit_interview', 'exit_interview_key'], 'final' => ['project_company_hr_full_and_final_statement', 'full_and_final_statement_key'], 'separation' => ['project_company_hr_employee_separation', 'employee_separation_key'], 'onboarding' => ['project_company_hr_employee_onboarding', 'employee_onboarding_key'], 'requirement' => ['project_company_hr_employee_document_requirement', 'employee_document_requirement_key'], 'document_type' => ['project_company_hr_identification_document_type', 'identification_document_type_key'], 'onboarding_template' => ['project_company_hr_boarding_template', 'boarding_template_key'], 'separation_template' => ['project_company_hr_boarding_template', 'boarding_template_key']] as $name => [$table, $column]) {
        $key = (string) ($keys[$name] ?? '');
        if ($key !== '') { $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ? AND {$column} = ?", [$hash, $key]); }
    }
    foreach (['onboarding', 'separation'] as $parentName) {
        $key = (string) ($keys[$parentName] ?? '');
        if ($key !== '') { $db->Execute('DELETE FROM project_company_hr_employee_boarding_activity WHERE company_key_hash = ? AND parent_key = ?', [$hash, $key]); }
    }
    foreach (['onboarding_template', 'separation_template'] as $templateName) {
        $key = (string) ($keys[$templateName] ?? '');
        if ($key !== '') { $db->Execute('DELETE FROM project_company_hr_boarding_template_activity WHERE company_key_hash = ? AND boarding_template_key = ?', [$hash, $key]); }
    }
    $finalKey = (string) ($keys['final'] ?? '');
    if ($finalKey !== '') {
        $db->Execute('DELETE FROM project_company_hr_full_and_final_asset WHERE company_key_hash = ? AND full_and_final_statement_key = ?', [$hash, $finalKey]);
        $db->Execute('DELETE FROM project_company_hr_full_and_final_outstanding_statement WHERE company_key_hash = ? AND full_and_final_statement_key = ?', [$hash, $finalKey]);
    }
    foreach (['exit', 'separation'] as $intentName) {
        $key = (string) ($keys[$intentName] ?? '');
        if ($key !== '') { $db->Execute('DELETE FROM project_company_hr_notification_intent WHERE company_key_hash = ? AND record_key = ?', [$hash, $key]); }
    }
    $db->Execute('DELETE FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_key IN (?, ?)', [$hash, $employeeKey, $managerKey]);
    foreach (array_values($keys) as $auditKey) {
        if (is_string($auditKey) && $auditKey !== '') { $db->Execute('DELETE FROM builder_audit_log WHERE record_key = ?', [$auditKey]); }
    }
}

$targets = yovel_admin_hr_onboarding_form_targets();
$schemas = yovel_admin_hr_default_form_fields();
$adapter = yovel_admin_shared_form_adapter('hr');
foreach (['employee-onboarding-template', 'employee-onboarding', 'employee-separation-template', 'employee-separation', 'employee-boarding-activity', 'exit-interview', 'employee-document-requirement', 'identification-document-type', 'full-and-final-statement'] as $target) {
    hr_onboarding_assert(isset($targets[$target]) && ($targets[$target]['versioned'] ?? false) === true, 'Missing versioned onboarding target: ' . $target);
    hr_onboarding_assert(($targets[$target]['protected_fields'] ?? []) !== [], 'Onboarding target has no protected fields: ' . $target);
    hr_onboarding_assert(isset($schemas[$target]), 'Onboarding target has no default schema: ' . $target);
    hr_onboarding_assert(isset($adapter['protected_fields'][$target]), 'Shared Form Builder omitted onboarding protection: ' . $target);
}

$workspacePath = $root . '/company/admin/modules/hr/views/onboarding.php';
hr_onboarding_assert(is_file($workspacePath), 'Onboarding workspace is missing.');
$workspace = (string) file_get_contents($workspacePath);
foreach (['grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr)', 'data-onboarding-mode="onboarding"', 'data-onboarding-mode="separation"', 'data-onboarding-mode="documents"', 'data-record-modal-open="hr-onboarding-modal"', 'data-record-modal-open="hr-separation-modal"', 'data-record-modal-open="hr-document-modal"', 'data-record-modal', 'data-confirm-submit'] as $marker) {
    hr_onboarding_assert(str_contains($workspace, $marker), 'Onboarding workspace is missing marker: ' . $marker);
}
hr_onboarding_assert(str_contains($workspace, 'data-confirm-submit') && str_contains($workspace, '$renderModal'), 'Every onboarding action must cross the shared modal confirmation renderer.');
hr_onboarding_assert(!str_contains($workspace, 'data-confirm-dialog'), 'Onboarding copied the shared confirmation dialog.');
hr_onboarding_assert(str_contains($workspace, 'orchestration.assets-clearance.v1') && str_contains($workspace, 'orchestration.finance-settlement.v1'), 'Onboarding did not expose external owner blocker contracts.');

echo "HR onboarding and separation checks passed.\n";
