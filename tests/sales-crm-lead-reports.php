<?php
declare(strict_types=1);

require_once __DIR__ . '/sales-crm-test-helper.php';

foreach ([
    'yovel_admin_sales_report_lead_details', 'yovel_admin_sales_report_lead_conversion_time',
    'yovel_admin_sales_report_lead_owner_efficiency', 'yovel_admin_sales_report_prospects_engaged',
    'yovel_admin_sales_report_address_contacts',
] as $function) {
    sales_crm_assert(function_exists($function), 'SC-03 report contract is missing: ' . $function);
}

yovel_admin_sales_crm_schema();
$db = bx_db();
$fixture = sales_crm_create_isolated_company($db);
$company = $fixture['company'];
$admin = $fixture['admin'];
$hash = (string) $company['company_key_hash'];
$leadCodes = [sales_crm_test_code('REPORT_OPEN'), sales_crm_test_code('REPORT_CONVERTED')];

try {
    foreach ([
        [$leadCodes[0], 'Report Open Lead', 'OPEN', 'open@example.test', '2026-08-10 08:00:00', null],
        [$leadCodes[1], 'Report Converted Lead', 'CONVERTED', 'converted@example.test', '2026-08-01 08:00:00', '2026-08-04 10:30:00'],
    ] as [$code, $name, $status, $email, $createdAt, $convertedAt]) {
        sales_crm_with_post([
            'lead_code' => $code, 'lead_name' => $name, 'lead_status' => $status, 'email' => $email,
            'address_line' => '25 Report Avenue', 'city' => 'Taguig', 'country' => 'Philippines',
        ], static fn (): string => yovel_admin_save_sales_lead($company, $admin));
        $db->Execute(
            'UPDATE project_company_sales_lead SET assigned_admin_key = ?, first_response_at = ?, qualified_at = ?, converted_at = ?, created_at = ? WHERE company_key_hash = ? AND lead_code = ?',
            [(string) $admin['admin_key'], '2026-08-01 09:00:00', '2026-08-02 09:00:00', $convertedAt, $createdAt, $hash, $code]
        );
    }
    $openLeadKey = (string) $db->GetOne('SELECT lead_key FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_code = ?', [$hash, $leadCodes[0]]);
    $prospect = yovel_admin_sales_prospect_save($company, $admin, [
        'prospect_code' => sales_crm_test_code('REPORT_PROSPECT'), 'prospect_name' => 'Engaged Prospect',
        'prospect_status' => 'OPEN', 'assigned_admin_key' => (string) $admin['admin_key'],
        'lead_keys' => [$openLeadKey], 'idempotency_key' => bx_uuid(),
    ]);
    yovel_admin_sales_crm_note_save($company, $admin, [
        'subject_type' => 'PROSPECT', 'subject_key' => (string) $prospect['prospect_key'],
        'note_text' => 'Engagement activity', 'idempotency_key' => bx_uuid(),
    ]);

    $filters = ['date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'owner_admin_key' => (string) $admin['admin_key']];
    $details = yovel_admin_sales_report_lead_details($company, $admin, $filters);
    sales_crm_assert(($details['columns'] ?? []) === ['lead_code', 'lead_name', 'lead_status', 'organization_name', 'owner', 'email', 'phone', 'next_contact_date', 'created_at'], 'Lead Details columns changed.');
    sales_crm_assert(count($details['rows'] ?? []) === 2, 'Lead Details filters did not return both company Leads.');
    $conversion = yovel_admin_sales_report_lead_conversion_time($company, $admin, $filters);
    sales_crm_assert(count($conversion['rows'] ?? []) === 1 && (string) ($conversion['rows'][0]['conversion_hours'] ?? '') === '74.50', 'Lead Conversion Time calculation is not deterministic.');
    $efficiency = yovel_admin_sales_report_lead_owner_efficiency($company, $admin, $filters);
    sales_crm_assert(count($efficiency['rows'] ?? []) === 1 && (int) $efficiency['rows'][0]['lead_count'] === 2 && (string) $efficiency['rows'][0]['conversion_rate'] === '50.00', 'Lead Owner Efficiency calculation is incorrect.');
    $engaged = yovel_admin_sales_report_prospects_engaged($company, $admin, $filters);
    sales_crm_assert(count($engaged['rows'] ?? []) === 1 && ($engaged['rows'][0]['prospect_name'] ?? '') === 'Engaged Prospect', 'Engaged Prospect report omitted an active unconverted Prospect.');
    $contacts = yovel_admin_sales_report_address_contacts($company, $admin, $filters);
    sales_crm_assert(($contacts['columns'] ?? []) === ['lead_code', 'lead_name', 'email', 'phone', 'mobile', 'address_line', 'city', 'country'], 'Address and Contacts columns changed.');
    sales_crm_assert(count($contacts['rows'] ?? []) === 2, 'Address and Contacts report is not company/date/owner filtered.');

    $foreign = sales_crm_create_isolated_company($db);
    try {
        sales_crm_with_post(['lead_code' => sales_crm_test_code('FOREIGN_REPORT'), 'lead_name' => 'Foreign report Lead', 'lead_status' => 'OPEN'], static fn (): string => yovel_admin_save_sales_lead($foreign['company'], $foreign['admin']));
        sales_crm_assert(!str_contains(json_encode(yovel_admin_sales_report_lead_details($company, $admin, $filters), JSON_THROW_ON_ERROR), 'Foreign report Lead'), 'Foreign Lead leaked into Sales reports.');
    } finally {
        sales_crm_cleanup_sc03($db, (string) $foreign['company']['company_key_hash']);
        sales_crm_cleanup_isolated_company($db, (string) $foreign['company']['company_key_hash']);
    }
} finally {
    sales_crm_cleanup_sc03($db, $hash);
    sales_crm_cleanup_isolated_company($db, $hash);
}

echo "Sales / CRM SC-03 Lead Details, Conversion Time, Owner Efficiency, engaged Prospect, and Address/Contacts report tests passed.\n";
