<?php
declare(strict_types=1);

require_once __DIR__ . '/sales-crm-test-helper.php';

$root = dirname(__DIR__);
foreach (['leads.php', 'views/leads.php', 'views/prospects.php', 'views/appointments.php'] as $path) {
    sales_crm_assert(is_file($root . '/company/admin/modules/sales-crm/' . $path), 'SC-03 responsibility is missing: ' . $path);
}
$leadServiceSource = (string) file_get_contents($root . '/company/admin/modules/sales-crm/leads.php');
sales_crm_assert(
    substr_count($leadServiceSource, "fgetcsv(\$stream, null, ',', '\"', '')") === 2,
    'Lead CSV import must pass an explicit empty escape argument to every fgetcsv call.'
);
foreach ([
    'yovel_admin_sales_crm_leads_schema', 'yovel_admin_sales_reference_save',
    'yovel_admin_sales_lead_assign', 'yovel_admin_sales_crm_note_save',
    'yovel_admin_sales_prospect_save', 'yovel_admin_sales_appointment_settings_save',
    'yovel_admin_sales_appointment_slot_save', 'yovel_admin_sales_appointment_availability',
    'yovel_admin_sales_appointment_save', 'yovel_admin_sales_lead_conversion_request',
    'yovel_admin_sales_lead_conversion_process', 'yovel_admin_sales_lead_timeline',
    'yovel_admin_sales_lead_import', 'yovel_admin_sales_lead_export',
] as $function) {
    sales_crm_assert(function_exists($function), 'SC-03 public contract is missing: ' . $function);
}
$sections = yovel_admin_sales_crm_sections();
sales_crm_assert(($sections['prospects']['record_type'] ?? '') === 'prospect' && ($sections['appointments']['record_type'] ?? '') === 'appointment', 'Prospect or Appointment route is not registered with its Form Builder target.');
$adapter = yovel_admin_sales_crm_form_adapter();
sales_crm_assert(($adapter['persistence_mappings']['prospect']['table'] ?? '') === 'project_company_sales_prospect', 'Prospect Form Builder persistence mapping is missing.');
sales_crm_assert(($adapter['persistence_mappings']['appointment']['table'] ?? '') === 'project_company_sales_appointment', 'Appointment Form Builder persistence mapping is missing.');
foreach (['views/leads.php', 'views/prospects.php', 'views/appointments.php'] as $viewPath) {
    $view = (string) file_get_contents($root . '/company/admin/modules/sales-crm/' . $viewPath);
    foreach (['data-record-modal', 'data-record-modal-form', 'data-confirm-submit', 'data-confirm-submit-action'] as $marker) {
        sales_crm_assert(str_contains($view, $marker), $viewPath . ' is missing modal/confirmation marker: ' . $marker);
    }
}
sales_crm_assert(str_contains((string) file_get_contents($root . '/company/admin/modules/sales-crm/views/leads.php'), 'data-sales-lead-timeline'), 'Lead timeline view is missing.');

yovel_admin_sales_crm_schema();
$db = bx_db();
foreach ([
    'project_company_sales_market_segment', 'project_company_sales_industry_type',
    'project_company_sales_prospect', 'project_company_sales_prospect_lead',
    'project_company_sales_prospect_opportunity', 'project_company_sales_appointment_booking_settings',
    'project_company_sales_appointment_booking_slot', 'project_company_sales_appointment_availability',
    'project_company_sales_appointment', 'project_company_sales_crm_note',
    'project_company_sales_lead_communication', 'project_company_sales_lead_conversion',
    'project_company_sales_integration_outbox',
] as $table) {
    sales_crm_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    ) === 1, 'Missing SC-03 table: ' . $table);
}
foreach (['lead_version', 'assigned_admin_key', 'first_response_at', 'qualified_at', 'converted_at', 'address_line', 'city', 'country'] as $column) {
    sales_crm_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [BUILDERX_DB_NAME, 'project_company_sales_lead', $column]
    ) === 1, 'Missing SC-03 Lead column: ' . $column);
}

$fixture = sales_crm_create_isolated_company($db);
$company = $fixture['company'];
$admin = $fixture['admin'];
$foreignFixture = sales_crm_create_isolated_company($db);
$foreignCompany = $foreignFixture['company'];
$foreignAdmin = $foreignFixture['admin'];
$companyHash = (string) $company['company_key_hash'];
$foreignHash = (string) $foreignCompany['company_key_hash'];
$leadCode = sales_crm_test_code('SC03_LEAD');
$foreignLeadCode = sales_crm_test_code('SC03_FOREIGN');
$rollbackNoteCode = sales_crm_test_code('SC03_NOTE_ROLLBACK');

try {
    sales_crm_with_post([
        'lead_code' => $leadCode,
        'lead_name' => 'SC-03 Lead',
        'organization_name' => 'SC-03 Organization',
        'lead_status' => 'OPEN',
        'email' => 'sc03@example.test',
        'address_line' => '10 Pipeline Street',
        'city' => 'Makati',
        'country' => 'Philippines',
    ], static fn (): string => yovel_admin_save_sales_lead($company, $admin));
    sales_crm_with_post([
        'lead_code' => $foreignLeadCode,
        'lead_name' => 'Foreign SC-03 Lead',
        'lead_status' => 'OPEN',
    ], static fn (): string => yovel_admin_save_sales_lead($foreignCompany, $foreignAdmin));
    $lead = $db->GetRow('SELECT * FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_code = ?', [$companyHash, $leadCode]);
    $foreignLead = $db->GetRow('SELECT * FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_code = ?', [$foreignHash, $foreignLeadCode]);
    sales_crm_assert(is_array($lead) && is_array($foreignLead), 'SC-03 Lead fixtures were not created.');
    $leadKey = (string) $lead['lead_key'];

    $unauthorizedRejected = false;
    try {
        yovel_admin_sales_crm_note_save($company, $foreignAdmin, [
            'subject_type' => 'LEAD', 'subject_key' => $leadKey,
            'note_text' => 'Unauthorized cross-company note.', 'idempotency_key' => bx_uuid(),
        ]);
    } catch (InvalidArgumentException) {
        $unauthorizedRejected = true;
    }
    sales_crm_assert($unauthorizedRejected, 'SC-03 accepted an administrator from another company.');

    $duplicateLeadCode = sales_crm_test_code('SC03_DUPLICATE');
    sales_crm_with_post([
        'lead_code' => $duplicateLeadCode,
        'lead_name' => 'Duplicate-code guard Lead',
        'lead_status' => 'OPEN',
    ], static fn (): string => yovel_admin_save_sales_lead($company, $admin));
    $duplicateRejected = false;
    try {
        sales_crm_with_post([
            'lead_key' => $leadKey,
            'expected_version' => (string) ($lead['lead_version'] ?? 1),
            'lead_code' => $duplicateLeadCode,
            'lead_name' => 'Must not overwrite',
            'lead_status' => 'OPEN',
        ], static fn (): string => yovel_admin_save_sales_lead($company, $admin));
    } catch (InvalidArgumentException) {
        $duplicateRejected = true;
    }
    sales_crm_assert($duplicateRejected, 'Lead update accepted another Lead record code.');
    sales_crm_assert(
        (string) $db->GetOne('SELECT lead_name FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ?', [$companyHash, $leadKey]) === 'SC-03 Lead',
        'Duplicate-code rejection did not preserve the original Lead.'
    );

    $segment = yovel_admin_sales_reference_save($company, $admin, 'market-segment', [
        'reference_code' => sales_crm_test_code('SEG'), 'reference_name' => 'Enterprise', 'reference_status' => 'ACTIVE',
    ]);
    $industry = yovel_admin_sales_reference_save($company, $admin, 'industry-type', [
        'reference_code' => sales_crm_test_code('IND'), 'reference_name' => 'Technology', 'reference_status' => 'ACTIVE',
    ]);
    sales_crm_assert(($segment['reference_key'] ?? '') !== '' && ($industry['reference_key'] ?? '') !== '', 'SC-03 taxonomy records were not saved.');

    $assignmentIdempotencyKey = bx_uuid();
    $assignmentInput = [
        'lead_key' => $leadKey,
        'assigned_admin_key' => (string) $admin['admin_key'],
        'next_contact_date' => '2026-09-02',
        'expected_version' => (string) ($lead['lead_version'] ?? 1),
        'idempotency_key' => $assignmentIdempotencyKey,
    ];
    $assigned = yovel_admin_sales_lead_assign($company, $admin, $assignmentInput);
    sales_crm_assert((string) ($assigned['assigned_admin_key'] ?? '') === (string) $admin['admin_key'], 'Lead assignment did not read back the owner.');
    sales_crm_assert((string) ($assigned['next_contact_date'] ?? '') === '2026-09-02', 'Lead follow-up did not read back the date.');
    $assignedAgain = yovel_admin_sales_lead_assign($company, $admin, $assignmentInput);
    sales_crm_assert((int) $assignedAgain['lead_version'] === (int) $assigned['lead_version'], 'Lead assignment replay advanced the Lead version.');

    $foreignRejected = false;
    try {
        yovel_admin_sales_prospect_save($company, $admin, [
            'prospect_code' => sales_crm_test_code('PROSPECT_BAD'),
            'prospect_name' => 'Cross-company Prospect',
            'prospect_status' => 'OPEN',
            'lead_keys' => [(string) $foreignLead['lead_key']],
        ]);
    } catch (InvalidArgumentException) {
        $foreignRejected = true;
    }
    sales_crm_assert($foreignRejected, 'Prospect accepted a foreign-company Lead key.');

    $prospectIdempotencyKey = bx_uuid();
    $prospectInput = [
        'prospect_code' => sales_crm_test_code('PROSPECT'),
        'prospect_name' => 'SC-03 Prospect',
        'prospect_status' => 'OPEN',
        'market_segment_key' => (string) $segment['reference_key'],
        'industry_type_key' => (string) $industry['reference_key'],
        'lead_keys' => [$leadKey, $leadKey],
        'notes' => 'Prospect grouping',
        'idempotency_key' => $prospectIdempotencyKey,
    ];
    $prospect = yovel_admin_sales_prospect_save($company, $admin, $prospectInput);
    sales_crm_assert(count($prospect['lead_keys'] ?? []) === 1 && ($prospect['lead_keys'][0] ?? '') === $leadKey, 'Prospect Lead children were not deduplicated and read back.');
    $prospectAgain = yovel_admin_sales_prospect_save($company, $admin, $prospectInput);
    sales_crm_assert((int) $prospectAgain['prospect_version'] === (int) $prospect['prospect_version'], 'Prospect replay advanced the record version.');

    $note = yovel_admin_sales_crm_note_save($company, $admin, [
        'subject_type' => 'LEAD', 'subject_key' => $leadKey, 'note_text' => 'Qualified discovery call completed.', 'idempotency_key' => bx_uuid(),
    ]);
    sales_crm_assert((string) ($note['note_text'] ?? '') === 'Qualified discovery call completed.', 'CRM Note did not read back exact text.');
    $timeline = yovel_admin_sales_lead_timeline($company, $admin, $leadKey);
    sales_crm_assert(count($timeline) >= 2 && !str_contains(json_encode($timeline, JSON_THROW_ON_ERROR), 'Foreign SC-03 Lead'), 'Lead timeline is incomplete or not company-scoped.');

    $settings = yovel_admin_sales_appointment_settings_save($company, $admin, [
        'timezone' => 'Asia/Manila', 'minimum_notice_minutes' => '30', 'booking_horizon_days' => '45',
        'appointment_duration_minutes' => '30', 'expected_version' => '0',
    ]);
    sales_crm_assert((int) ($settings['settings_version'] ?? 0) === 1, 'Appointment settings version did not advance.');
    $slotInput = ['weekday_number' => '2', 'start_time' => '09:00', 'end_time' => '12:00', 'slot_status' => 'ACTIVE', 'idempotency_key' => bx_uuid()];
    $slot = yovel_admin_sales_appointment_slot_save($company, $admin, $slotInput);
    sales_crm_assert((string) ($slot['start_time'] ?? '') === '09:00:00', 'Appointment slot did not read back exact time.');
    $slotAgain = yovel_admin_sales_appointment_slot_save($company, $admin, $slotInput);
    sales_crm_assert((string) $slotAgain['booking_slot_key'] === (string) $slot['booking_slot_key'], 'Appointment slot replay changed its stable key.');
    $availability = yovel_admin_sales_appointment_availability($company, $admin, '2026-09-01');
    sales_crm_assert(in_array('09:00', array_column($availability, 'start_time'), true), 'Appointment availability did not produce the configured slot.');

    $appointmentInput = [
        'appointment_code' => sales_crm_test_code('APPT'),
        'appointment_status' => 'SCHEDULED',
        'lead_key' => $leadKey,
        'prospect_key' => (string) $prospect['prospect_key'],
        'starts_at' => '2026-09-01 09:00:00',
        'ends_at' => '2026-09-01 09:30:00',
        'contact_name' => 'SC-03 Lead',
        'contact_email' => 'sc03@example.test',
        'notes' => 'Initial consultation',
        'idempotency_key' => bx_uuid(),
    ];
    $appointment = yovel_admin_sales_appointment_save($company, $admin, $appointmentInput);
    sales_crm_assert((string) ($appointment['lead_key'] ?? '') === $leadKey, 'Appointment did not preserve its Lead reference.');
    $appointmentAgain = yovel_admin_sales_appointment_save($company, $admin, $appointmentInput);
    sales_crm_assert((int) $appointmentAgain['appointment_version'] === (int) $appointment['appointment_version'], 'Appointment replay advanced the record version.');
    $overlapRejected = false;
    try {
        yovel_admin_sales_appointment_save($company, $admin, [
            'appointment_code' => sales_crm_test_code('APPT_OVERLAP'), 'appointment_status' => 'SCHEDULED',
            'lead_key' => $leadKey, 'starts_at' => '2026-09-01 09:15:00', 'ends_at' => '2026-09-01 09:45:00',
        ]);
    } catch (RuntimeException) {
        $overlapRejected = true;
    }
    sales_crm_assert($overlapRejected, 'Overlapping Appointment was not rejected under a row lock.');

    $requestKey = bx_uuid();
    $conversion = yovel_admin_sales_lead_conversion_request($company, $admin, [
        'lead_key' => $leadKey, 'target_type' => 'BOTH', 'idempotency_key' => $requestKey,
    ]);
    sales_crm_assert((string) ($conversion['conversion_status'] ?? '') === 'PENDING', 'Conversion request did not remain pending for unavailable dependencies.');
    sales_crm_assert((string) ($conversion['dependency_status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Conversion dependency did not fail closed.');
    $sameConversion = yovel_admin_sales_lead_conversion_request($company, $admin, [
        'lead_key' => $leadKey, 'target_type' => 'BOTH', 'idempotency_key' => $requestKey,
    ]);
    sales_crm_assert((string) $sameConversion['conversion_key'] === (string) $conversion['conversion_key'], 'Conversion idempotency changed the stable request key.');
    $processRejected = false;
    try {
        yovel_admin_sales_lead_conversion_process($company, $admin, (string) $conversion['conversion_key']);
    } catch (RuntimeException $error) {
        $processRejected = str_contains($error->getMessage(), 'owner service');
    }
    sales_crm_assert($processRejected, 'Conversion did not fail closed when owner services were absent.');
    sales_crm_assert((string) $db->GetOne('SELECT lead_status FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ?', [$companyHash, $leadKey]) !== 'CONVERTED', 'Unavailable conversion services changed the Lead status.');

    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }, E_DEPRECATED);
    try {
        $import = yovel_admin_sales_lead_import($company, $admin, "lead_code,lead_name,lead_status,email\n{$leadCode},SC-03 Imported Update,OPEN,imported@example.test\n");
    } finally {
        restore_error_handler();
    }
    sales_crm_assert(($import['updated'] ?? 0) === 1 && ($import['created'] ?? 0) === 0, 'Lead import was not idempotent by company and Lead code.');
    $export = yovel_admin_sales_lead_export($company, $admin, ['lead_code' => $leadCode]);
    sales_crm_assert(($export['columns'] ?? []) === ['lead_code', 'lead_name', 'lead_status', 'organization_name', 'email', 'phone', 'mobile', 'assigned_admin_key', 'next_contact_date'], 'Lead export columns are not deterministic.');
    sales_crm_assert(count($export['rows'] ?? []) === 1 && ($export['rows'][0]['lead_name'] ?? '') === 'SC-03 Imported Update', 'Lead export did not return the persisted import value.');

    $customerTargetKey = bx_uuid();
    $opportunityTargetKey = bx_uuid();
    yovel_admin_sales_crm_register_owner_service('sales.customer.create-from-lead.v1', static function () use ($customerTargetKey): array {
        return ['customer_key' => $customerTargetKey];
    });
    yovel_admin_sales_crm_register_owner_service('sales.opportunity.create-from-lead.v1', static function () use ($opportunityTargetKey): array {
        return ['opportunity_key' => $opportunityTargetKey];
    });
    $completedConversion = yovel_admin_sales_lead_conversion_process($company, $admin, (string) $conversion['conversion_key']);
    sales_crm_assert((string) $completedConversion['conversion_status'] === 'COMPLETED', 'Registered owner services did not complete Lead conversion.');
    sales_crm_assert((string) $completedConversion['customer_key'] === $customerTargetKey && (string) $completedConversion['opportunity_key'] === $opportunityTargetKey, 'Lead conversion did not read back owner-service target keys.');
    sales_crm_assert((string) $db->GetOne('SELECT lead_status FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ?', [$companyHash, $leadKey]) === 'CONVERTED', 'Completed conversion did not mark the Lead converted.');
    sales_crm_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_prospect_opportunity WHERE company_key_hash = ? AND prospect_key = ? AND opportunity_key = ?', [$companyHash, (string) $prospect['prospect_key'], $opportunityTargetKey]) === 1, 'Conversion did not persist the Prospect Opportunity reference.');
    $completedAgain = yovel_admin_sales_lead_conversion_process($company, $admin, (string) $conversion['conversion_key']);
    sales_crm_assert((string) $completedAgain['customer_key'] === $customerTargetKey && (string) $completedAgain['opportunity_key'] === $opportunityTargetKey, 'Completed conversion was not idempotent.');

    $rollbackCompany = $company;
    $rollbackCompany['company_name'] = "\xB1";
    $rollbackThrown = false;
    try {
        yovel_admin_sales_crm_note_save($rollbackCompany, $admin, [
            'note_code' => $rollbackNoteCode, 'subject_type' => 'LEAD', 'subject_key' => $leadKey,
            'note_text' => 'This write must roll back.', 'idempotency_key' => bx_uuid(),
        ]);
    } catch (JsonException) {
        $rollbackThrown = true;
    }
    sales_crm_assert($rollbackThrown, 'SC-03 audit failure did not reach rollback.');
    sales_crm_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_crm_note WHERE company_key_hash = ? AND note_code = ?', [$companyHash, $rollbackNoteCode]) === 0, 'SC-03 Note survived a post-write audit failure.');
} finally {
    sales_crm_cleanup_sc03($db, $companyHash);
    sales_crm_cleanup_sc03($db, $foreignHash);
    sales_crm_cleanup_isolated_company($db, $companyHash);
    sales_crm_cleanup_isolated_company($db, $foreignHash);
}

echo "Sales / CRM SC-03 Lead, Prospect, Appointment, Note, import/export, conversion-gateway, isolation, idempotency, and rollback tests passed.\n";
