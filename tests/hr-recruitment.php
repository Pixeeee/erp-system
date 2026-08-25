<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/hr/functions.php';
require_once $root . '/company/admin/modules/shared/forms.php';

function hr_recruitment_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

foreach ([
    'yovel_admin_hr_recruitment_form_targets', 'yovel_admin_persist_recruitment_master',
    'yovel_admin_persist_staffing_plan', 'yovel_admin_persist_job_requisition',
    'yovel_admin_transition_job_requisition', 'yovel_admin_persist_job_opening',
    'yovel_admin_persist_job_applicant', 'yovel_admin_persist_employee_referral',
    'yovel_admin_persist_interview', 'yovel_admin_submit_interview_feedback',
    'yovel_admin_transition_interview', 'yovel_admin_persist_job_offer',
    'yovel_admin_transition_job_offer', 'yovel_admin_hr_recruitment_print_payload',
    'yovel_admin_convert_accepted_offer_to_onboarding', 'yovel_admin_hr_recruitment_data',
    'yovel_admin_hr_handle_recruitment_post',
] as $function) {
    hr_recruitment_assert(function_exists($function), 'Missing HR-WP-04 interface: ' . $function);
}

$db = bx_db();
yovel_admin_hr_schema();
$tables = [
    'project_company_hr_job_applicant_source', 'project_company_hr_interview_type',
    'project_company_hr_offer_term', 'project_company_hr_job_opening_template',
    'project_company_hr_job_offer_term_template', 'project_company_hr_job_offer_term_template_detail',
    'project_company_hr_appointment_letter_template', 'project_company_hr_appointment_letter_content',
    'project_company_hr_staffing_plan', 'project_company_hr_staffing_plan_detail',
    'project_company_hr_job_requisition', 'project_company_hr_job_opening',
    'project_company_hr_job_applicant', 'project_company_hr_employee_referral',
    'project_company_hr_interview', 'project_company_hr_interview_detail',
    'project_company_hr_interviewer', 'project_company_hr_interview_feedback',
    'project_company_hr_job_offer', 'project_company_hr_job_offer_term',
];
foreach ($tables as $table) {
    hr_recruitment_assert((int) $db->GetOne('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [BUILDERX_DB_NAME, $table]) === 1, 'Missing recruitment table: ' . $table);
}

$scope = $db->GetRow("SELECT company.company_key, company.company_key_hash, company.company_name, admin.admin_key,
    branch.branch_key, department.department_key, position.job_position_key
    FROM project_company company
    INNER JOIN project_company_admin admin ON admin.company_key_hash = company.company_key_hash AND admin.company_key = company.company_key AND admin.admin_status = 'ACTIVE'
    INNER JOIN project_company_branch branch ON branch.company_key_hash = company.company_key_hash AND branch.branch_status <> 'DELETED'
    INNER JOIN project_company_department department ON department.company_key_hash = company.company_key_hash AND department.department_status <> 'DELETED'
    LEFT JOIN project_company_hr_job_position position ON position.company_key_hash = company.company_key_hash AND position.job_position_status <> 'DELETED'
    WHERE company.company_status = 'ACTIVE' ORDER BY company.x_id, branch.x_id, department.x_id, position.x_id LIMIT 1");
hr_recruitment_assert(is_array($scope) && $scope !== [], 'An active HR recruitment company fixture is required.');
$company = ['company_key' => (string) $scope['company_key'], 'company_key_hash' => (string) $scope['company_key_hash'], 'company_name' => (string) $scope['company_name']];
$admin = ['admin_key' => (string) $scope['admin_key']];
$companyKey = (string) $scope['company_key'];
$hash = (string) $scope['company_key_hash'];
$adminKey = (string) $scope['admin_key'];
$token = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$fixturePositionKey = '';
if (trim((string) ($scope['job_position_key'] ?? '')) === '') {
    $fixturePositionKey = bx_uuid();
    yovel_admin_db_execute($db, "INSERT INTO project_company_hr_job_position (job_position_key, company_key, company_key_hash, job_position_code, job_position_name, job_position_description, job_position_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)", [$fixturePositionKey, $companyKey, $hash, 'REC_POS_' . $token, 'Recruitment Fixture Position', 'HR-WP-04 isolated fixture.', $adminKey, $adminKey], 'HR-WP-04 job position fixture');
    $scope['job_position_key'] = $fixturePositionKey;
}
$employeeKeys = [bx_uuid(), bx_uuid()];
$keys = [];
$auditKeys = [];

try {
    foreach ($employeeKeys as $index => $employeeKey) {
        yovel_admin_db_execute($db, "INSERT INTO project_company_hr_employee (employee_key, company_key, company_key_hash, employee_code, first_name, employee_name, employee_status, branch_key, department_key, job_position_key, created_by_admin_key, updated_by_admin_key)
            VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?, ?, ?)", [$employeeKey, $companyKey, $hash, 'REC_' . $token . '_' . $index, 'Recruiter', 'Recruitment Fixture ' . ($index + 1), $scope['branch_key'], $scope['department_key'], $scope['job_position_key'], $adminKey, $adminKey], 'HR-WP-04 employee fixture');
    }

    $source = yovel_admin_persist_recruitment_master($db, $company, $admin, 'JOB_APPLICANT_SOURCE', ['source_code' => 'REF_' . $token, 'source_name' => 'Employee Referral']);
    $keys['source'] = (string) $source['job_applicant_source_key'];
    $interviewType = yovel_admin_persist_recruitment_master($db, $company, $admin, 'INTERVIEW_TYPE', ['interview_type_code' => 'TECH_' . $token, 'interview_type_name' => 'Technical Interview']);
    $keys['interview_type'] = (string) $interviewType['interview_type_key'];
    $offerTerm = yovel_admin_persist_recruitment_master($db, $company, $admin, 'OFFER_TERM', ['offer_term_code' => 'BASE_' . $token, 'offer_term_name' => 'Base Compensation']);
    $keys['offer_term'] = (string) $offerTerm['offer_term_key'];
    $openingTemplate = yovel_admin_persist_recruitment_master($db, $company, $admin, 'JOB_OPENING_TEMPLATE', ['template_code' => 'OPEN_' . $token, 'template_name' => 'Standard Opening', 'description' => 'Verified responsibilities']);
    $keys['opening_template'] = (string) $openingTemplate['job_opening_template_key'];
    $termTemplate = yovel_admin_persist_recruitment_master($db, $company, $admin, 'JOB_OFFER_TERM_TEMPLATE', [
        'template_code' => 'TERM_' . $token, 'template_name' => 'Standard Terms',
        'details' => [['offer_term_key' => $keys['offer_term'], 'term_value' => 'PHP 50000', 'sort_order' => 10]],
    ]);
    $keys['term_template'] = (string) $termTemplate['job_offer_term_template_key'];
    $letterTemplate = yovel_admin_persist_recruitment_master($db, $company, $admin, 'APPOINTMENT_LETTER_TEMPLATE', [
        'template_code' => 'LETTER_' . $token, 'template_name' => 'Standard Appointment Letter',
        'content' => [['content_heading' => 'Appointment', 'content_body' => 'Appointment terms are supplied to the shared print renderer.', 'sort_order' => 10]],
    ]);
    $keys['letter_template'] = (string) $letterTemplate['appointment_letter_template_key'];

    $plan = yovel_admin_persist_staffing_plan($db, $company, $admin, [
        'plan_code' => 'PLAN_' . $token, 'plan_name' => 'FY 2026 Staffing', 'from_date' => '2026-01-01', 'to_date' => '2026-12-31',
        'plan_status' => 'ACTIVE', 'details' => [['job_position_key' => $scope['job_position_key'], 'planned_positions' => 1]],
    ]);
    $keys['staffing_plan'] = (string) $plan['staffing_plan_key'];
    $auditKeys[] = $keys['staffing_plan'];

    $requisition = yovel_admin_persist_job_requisition($db, $company, $admin, [
        'requisition_code' => 'REQ_' . $token, 'staffing_plan_key' => $keys['staffing_plan'],
        'department_key' => $scope['department_key'], 'job_position_key' => $scope['job_position_key'],
        'requested_positions' => 1, 'required_by_date' => '2026-10-01', 'requisition_status' => 'SUBMITTED', 'reason' => 'Approved growth',
    ]);
    $keys['requisition'] = (string) $requisition['job_requisition_key'];
    $requisition = yovel_admin_transition_job_requisition($db, $company, $admin, $keys['requisition'], 'APPROVED');
    hr_recruitment_assert((string) $requisition['requisition_status'] === 'APPROVED', 'Job Requisition approval failed.');

    $opening = yovel_admin_persist_job_opening($db, $company, $admin, [
        'opening_code' => 'OPENING_' . $token, 'job_requisition_key' => $keys['requisition'],
        'job_opening_template_key' => $keys['opening_template'], 'department_key' => $scope['department_key'],
        'job_position_key' => $scope['job_position_key'], 'vacancies' => 1, 'opening_date' => '2026-08-01',
        'closing_date' => '2026-09-30', 'opening_status' => 'OPEN', 'description' => 'One governed vacancy',
    ]);
    $keys['opening'] = (string) $opening['job_opening_key'];

    $limitRejected = false;
    try {
        yovel_admin_persist_job_opening($db, $company, $admin, [
            'opening_code' => 'EXCESS_' . $token, 'job_requisition_key' => $keys['requisition'],
            'department_key' => $scope['department_key'], 'job_position_key' => $scope['job_position_key'],
            'vacancies' => 1, 'opening_date' => '2026-08-01', 'opening_status' => 'OPEN',
        ]);
    } catch (InvalidArgumentException) { $limitRejected = true; }
    hr_recruitment_assert($limitRejected, 'Staffing plan vacancy limit was not enforced.');

    $applicant = yovel_admin_persist_job_applicant($db, $company, $admin, [
        'job_opening_key' => $keys['opening'], 'job_applicant_source_key' => $keys['source'],
        'applicant_name' => 'Ada Applicant', 'email_address' => strtolower($token) . '@example.test',
        'phone_number' => '+639170000001', 'applicant_status' => 'OPEN', 'cover_letter' => 'Qualified applicant',
    ]);
    $keys['applicant'] = (string) $applicant['job_applicant_key'];
    $duplicateRejected = false;
    try {
        yovel_admin_persist_job_applicant($db, $company, $admin, [
            'job_opening_key' => $keys['opening'], 'job_applicant_source_key' => $keys['source'],
            'applicant_name' => 'Duplicate Applicant', 'email_address' => strtoupper($token) . '@EXAMPLE.TEST',
        ]);
    } catch (InvalidArgumentException) { $duplicateRejected = true; }
    hr_recruitment_assert($duplicateRejected, 'Duplicate applicant email policy was not enforced per opening.');

    $referral = yovel_admin_persist_employee_referral($db, $company, $admin, [
        'employee_key' => $employeeKeys[0], 'job_applicant_key' => $keys['applicant'], 'referral_notes' => 'Known professional',
    ]);
    $keys['referral'] = (string) $referral['employee_referral_key'];

    $interview = yovel_admin_persist_interview($db, $company, $admin, [
        'job_applicant_key' => $keys['applicant'], 'interview_type_key' => $keys['interview_type'],
        'scheduled_at' => '2026-08-20 10:00:00', 'timezone_name' => 'Asia/Manila', 'interview_status' => 'SCHEDULED',
        'details' => [['employee_key' => $employeeKeys[0], 'role_label' => 'Lead Interviewer'], ['employee_key' => $employeeKeys[1], 'role_label' => 'Panelist']],
    ]);
    $keys['interview'] = (string) $interview['interview_key'];
    $feedback = yovel_admin_submit_interview_feedback($db, $company, $admin, [
        'interview_key' => $keys['interview'], 'interviewer_employee_key' => $employeeKeys[0],
        'rating' => 4.5, 'feedback' => 'Strong technical and communication skills', 'recommendation' => 'HIRE',
    ]);
    $keys['feedback'] = (string) $feedback['interview_feedback_key'];
    $interview = yovel_admin_transition_interview($db, $company, $admin, $keys['interview'], 'COMPLETED');
    hr_recruitment_assert((string) $interview['interview_status'] === 'COMPLETED', 'Interview completion failed.');

    $offer = yovel_admin_persist_job_offer($db, $company, $admin, [
        'offer_code' => 'OFFER_' . $token, 'job_applicant_key' => $keys['applicant'],
        'appointment_letter_template_key' => $keys['letter_template'], 'offer_date' => '2026-08-22',
        'valid_until' => '2026-09-01', 'designation' => 'HR Specialist', 'offer_status' => 'SUBMITTED',
        'terms' => [['offer_term_key' => $keys['offer_term'], 'term_label' => 'Base Compensation', 'term_value' => 'PHP 50000', 'sort_order' => 10]],
    ]);
    $keys['offer'] = (string) $offer['job_offer_key'];
    $offer = yovel_admin_transition_job_offer($db, $company, $admin, $keys['offer'], 'ACCEPTED');
    hr_recruitment_assert((string) $offer['offer_status'] === 'ACCEPTED', 'Job Offer acceptance failed.');

    $immutableRejected = false;
    try {
        yovel_admin_persist_job_offer($db, $company, $admin, ['job_offer_key' => $keys['offer'], 'offer_code' => 'CHANGED_' . $token, 'job_applicant_key' => $keys['applicant'], 'offer_date' => '2026-08-22', 'designation' => 'Changed']);
    } catch (RuntimeException) { $immutableRejected = true; }
    hr_recruitment_assert($immutableRejected, 'Accepted Job Offer was edited outside an explicit transition.');

    $printPayload = yovel_admin_hr_recruitment_print_payload($company, $keys['offer'], 'JOB_OFFER');
    hr_recruitment_assert(($printPayload['contract'] ?? '') === 'hr.recruitment-print.v1', 'Recruitment print contract changed.');
    hr_recruitment_assert(($printPayload['renderer']['available'] ?? true) === false, 'Recruitment embedded a print renderer instead of requesting the shared owner.');
    hr_recruitment_assert(!str_contains((string) ($printPayload['document']['content'] ?? ''), '<html'), 'Recruitment print payload embedded copied HTML.');
    $appointmentPayload = yovel_admin_hr_recruitment_print_payload($company, $keys['offer'], 'APPOINTMENT_LETTER');
    hr_recruitment_assert(($appointmentPayload['document']['type'] ?? '') === 'APPOINTMENT_LETTER', 'Appointment Letter print payload is missing.');
    hr_recruitment_assert(str_contains((string) ($appointmentPayload['document']['content'] ?? ''), 'Appointment terms'), 'Appointment Letter template content was not projected to the print contract.');

    $onboardingUnavailable = false;
    try { yovel_admin_convert_accepted_offer_to_onboarding($db, $company, $admin, $keys['offer']); }
    catch (RuntimeException) { $onboardingUnavailable = true; }
    hr_recruitment_assert($onboardingUnavailable, 'Recruitment fabricated onboarding while its owner contract is unavailable.');

    $offer = yovel_admin_transition_job_offer($db, $company, $admin, $keys['offer'], 'WITHDRAWN');
    hr_recruitment_assert((string) $offer['offer_status'] === 'WITHDRAWN', 'Explicit accepted-offer withdrawal failed.');
    hr_recruitment_assert((int) $db->GetOne("SELECT COUNT(*) FROM project_company_hr_notification_intent WHERE company_key_hash = ? AND record_key IN (?, ?, ?) AND intent_status = 'PENDING'", [$hash, $keys['requisition'], $keys['interview'], $keys['offer']]) >= 3, 'Recruitment decisions did not persist notification intents.');

    $beforeRollback = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_employee_referral WHERE company_key_hash = ?', [$hash]);
    try {
        yovel_admin_persist_employee_referral($db, $company, $admin, ['employee_key' => $employeeKeys[1], 'job_applicant_key' => $keys['applicant']], static function (): void { throw new RuntimeException('Injected rollback.'); });
    } catch (RuntimeException) {}
    hr_recruitment_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_employee_referral WHERE company_key_hash = ?', [$hash]) === $beforeRollback, 'Recruitment rollback left a referral record.');

    $foreignRejected = false;
    try { yovel_admin_persist_job_applicant($db, ['company_key' => $companyKey, 'company_key_hash' => str_repeat('f', 64)], $admin, ['job_opening_key' => $keys['opening'], 'applicant_name' => 'Foreign', 'email_address' => 'foreign@example.test']); }
    catch (Throwable) { $foreignRejected = true; }
    hr_recruitment_assert($foreignRejected, 'Recruitment accepted a foreign company scope.');
} finally {
    $deleteByKey = static function (string $table, string $column, string $name) use ($db, $hash, $keys): void {
        $key = (string) ($keys[$name] ?? '');
        if ($key !== '') { $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ? AND {$column} = ?", [$hash, $key]); }
    };
    $deleteByKey('project_company_hr_job_offer_term', 'job_offer_key', 'offer');
    $deleteByKey('project_company_hr_job_offer', 'job_offer_key', 'offer');
    $deleteByKey('project_company_hr_interview_feedback', 'interview_key', 'interview');
    $deleteByKey('project_company_hr_interviewer', 'interview_key', 'interview');
    $deleteByKey('project_company_hr_interview_detail', 'interview_key', 'interview');
    $deleteByKey('project_company_hr_interview', 'interview_key', 'interview');
    $deleteByKey('project_company_hr_employee_referral', 'employee_referral_key', 'referral');
    $deleteByKey('project_company_hr_job_applicant', 'job_applicant_key', 'applicant');
    $deleteByKey('project_company_hr_job_opening', 'job_opening_key', 'opening');
    $deleteByKey('project_company_hr_job_requisition', 'job_requisition_key', 'requisition');
    $deleteByKey('project_company_hr_staffing_plan_detail', 'staffing_plan_key', 'staffing_plan');
    $deleteByKey('project_company_hr_staffing_plan', 'staffing_plan_key', 'staffing_plan');
    $deleteByKey('project_company_hr_appointment_letter_content', 'appointment_letter_template_key', 'letter_template');
    $deleteByKey('project_company_hr_appointment_letter_template', 'appointment_letter_template_key', 'letter_template');
    $deleteByKey('project_company_hr_job_offer_term_template_detail', 'job_offer_term_template_key', 'term_template');
    $deleteByKey('project_company_hr_job_offer_term_template', 'job_offer_term_template_key', 'term_template');
    $deleteByKey('project_company_hr_job_opening_template', 'job_opening_template_key', 'opening_template');
    $deleteByKey('project_company_hr_offer_term', 'offer_term_key', 'offer_term');
    $deleteByKey('project_company_hr_interview_type', 'interview_type_key', 'interview_type');
    $deleteByKey('project_company_hr_job_applicant_source', 'job_applicant_source_key', 'source');
    foreach (['requisition', 'interview', 'offer'] as $intentRecord) { $deleteByKey('project_company_hr_notification_intent', 'record_key', $intentRecord); }
    $db->Execute('DELETE FROM project_company_hr_employee WHERE company_key_hash = ? AND employee_key IN (?, ?)', [$hash, $employeeKeys[0], $employeeKeys[1]]);
    if ($fixturePositionKey !== '') { $db->Execute('DELETE FROM project_company_hr_job_position WHERE company_key_hash = ? AND job_position_key = ?', [$hash, $fixturePositionKey]); }
    foreach (array_unique(array_merge($auditKeys, array_values($keys))) as $auditKey) {
        if (is_string($auditKey) && $auditKey !== '') { $db->Execute('DELETE FROM builder_audit_log WHERE record_key = ?', [$auditKey]); }
    }
}

$targets = yovel_admin_hr_recruitment_form_targets();
$schemas = yovel_admin_hr_default_form_fields();
$adapter = yovel_admin_shared_form_adapter('hr');
foreach (['job-requisition', 'job-opening', 'job-applicant', 'interview', 'interview-feedback', 'job-offer', 'staffing-plan'] as $target) {
    hr_recruitment_assert(isset($targets[$target]) && ($targets[$target]['versioned'] ?? false) === true, 'Missing versioned recruitment target: ' . $target);
    hr_recruitment_assert(($targets[$target]['protected_fields'] ?? []) !== [], 'Recruitment target has no protected fields: ' . $target);
    hr_recruitment_assert(isset($schemas[$target]), 'Recruitment target has no default schema: ' . $target);
    hr_recruitment_assert(isset($adapter['protected_fields'][$target]), 'Shared Form Builder omitted recruitment protection: ' . $target);
}

$workspacePath = $root . '/company/admin/modules/hr/views/recruitment.php';
hr_recruitment_assert(is_file($workspacePath), 'Recruitment workspace is missing.');
$workspace = (string) file_get_contents($workspacePath);
foreach (['grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr)', 'data-recruitment-mode="pipeline"', 'data-recruitment-mode="applicants"', 'data-recruitment-mode="interviews"', 'data-recruitment-mode="offers"', 'data-record-modal-open="hr-requisition-modal"', 'data-record-modal-open="hr-opening-modal"', 'data-record-modal-open="hr-applicant-modal"', 'data-record-modal-open="hr-interview-modal"', 'data-record-modal-open="hr-offer-modal"', 'data-record-modal', 'data-confirm-submit'] as $marker) {
    hr_recruitment_assert(str_contains($workspace, $marker), 'Recruitment workspace is missing marker: ' . $marker);
}
hr_recruitment_assert(substr_count($workspace, 'data-confirm-submit') >= 8, 'Every recruitment action must cross confirmation.');
hr_recruitment_assert(!str_contains($workspace, 'data-confirm-dialog'), 'Recruitment copied the shared confirmation dialog.');

echo "HR recruitment pipeline checks passed.\n";
