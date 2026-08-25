<?php
declare(strict_types=1);

require_once __DIR__ . '/sales-crm-test-helper.php';

$root = dirname(__DIR__);
sales_crm_assert(is_file($root . '/company/admin/modules/sales-crm/dashboard.php'), 'Sales / CRM live dashboard provider is missing.');
sales_crm_assert(is_file($root . '/company/admin/modules/sales-crm/views/dashboard.php'), 'Sales / CRM live dashboard view is missing.');
sales_crm_assert(function_exists('yovel_admin_sales_crm_dashboard_data'), 'Sales / CRM live dashboard provider contract is missing.');

$sections = yovel_admin_sales_crm_sections();
sales_crm_assert(isset($sections['dashboard']), 'Sales / CRM dashboard section is not registered.');
$previousGet = $_GET;
$_GET = [];
sales_crm_assert(yovel_admin_sales_crm_section() === 'dashboard', 'Sales / CRM does not default unknown or empty sections to dashboard.');
$_GET = ['section' => 'not-a-sales-section'];
sales_crm_assert(yovel_admin_sales_crm_section() === 'dashboard', 'Unknown Sales / CRM sections do not resolve to dashboard.');
$_GET = $previousGet;

$db = bx_db();
$fixture = sales_crm_create_isolated_company($db);
$foreignFixture = sales_crm_create_isolated_company($db);
$company = $fixture['company'];
$admin = $fixture['admin'];
$companyHash = (string) $company['company_key_hash'];
$foreignHash = (string) $foreignFixture['company']['company_key_hash'];
$leadKeys = [bx_uuid(), bx_uuid()];
$foreignLeadKey = bx_uuid();
$campaignKey = bx_uuid();
$foreignCampaignKey = bx_uuid();
$auditKeys = [bx_uuid(), bx_uuid()];

$cleanup = static function () use ($db, $companyHash, $foreignHash, $leadKeys, $foreignLeadKey, $campaignKey, $foreignCampaignKey, $auditKeys): void {
    foreach ($auditKeys as $auditKey) {
        $db->Execute('DELETE FROM builder_audit_log WHERE audit_key = ?', [$auditKey]);
    }
    $db->Execute('DELETE FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key IN (?, ?)', [$companyHash, $leadKeys[0], $leadKeys[1]]);
    $db->Execute('DELETE FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_key = ?', [$foreignHash, $foreignLeadKey]);
    $db->Execute('DELETE FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_key = ?', [$companyHash, $campaignKey]);
    $db->Execute('DELETE FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_key = ?', [$foreignHash, $foreignCampaignKey]);
    sales_crm_cleanup_sc02($db, $companyHash, [], '');
    sales_crm_cleanup_sc02($db, $foreignHash, [], '');
    sales_crm_cleanup_isolated_company($db, $companyHash);
    sales_crm_cleanup_isolated_company($db, $foreignHash);
};

try {
    yovel_admin_sales_crm_schema();
    sales_crm_assert($db->Execute(
        "INSERT INTO project_company_sales_lead (
            lead_key, company_key, company_key_hash, lead_code, lead_name, lead_status,
            estimated_value, next_contact_date, created_by_admin_key, updated_by_admin_key, created_at, updated_at
         ) VALUES
            (?, ?, ?, ?, ?, 'OPEN', 1000.00, NULL, ?, ?, UTC_TIMESTAMP() - INTERVAL 12 DAY, UTC_TIMESTAMP() - INTERVAL 12 DAY),
            (?, ?, ?, ?, ?, 'QUALIFIED', 500.25, CURDATE() + INTERVAL 3 DAY, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
        [
            $leadKeys[0], $company['company_key'], $companyHash, sales_crm_test_code('DASH_STALE'), 'Stale dashboard lead', $admin['admin_key'], $admin['admin_key'],
            $leadKeys[1], $company['company_key'], $companyHash, sales_crm_test_code('DASH_FRESH'), 'Current dashboard lead', $admin['admin_key'], $admin['admin_key'],
        ]
    ) !== false, 'Dashboard Lead fixtures could not be created.');
    sales_crm_assert($db->Execute(
        "INSERT INTO project_company_sales_campaign (
            campaign_key, company_key, company_key_hash, campaign_code, campaign_name, campaign_status,
            campaign_version, start_date, end_date, budget, expected_revenue, created_by_admin_key, updated_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, 'ACTIVE', 1, CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 5 DAY, 750.00, 3000.00, ?, ?)",
        [$campaignKey, $company['company_key'], $companyHash, sales_crm_test_code('DASH_CAMPAIGN'), 'Dashboard active campaign', $admin['admin_key'], $admin['admin_key']]
    ) !== false, 'Dashboard Campaign fixture could not be created.');

    sales_crm_assert($db->Execute(
        "INSERT INTO project_company_sales_lead (
            lead_key, company_key, company_key_hash, lead_code, lead_name, lead_status,
            estimated_value, created_by_admin_key, updated_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, 'OPEN', 9999.99, ?, ?)",
        [$foreignLeadKey, $foreignFixture['company']['company_key'], $foreignHash, sales_crm_test_code('DASH_FOREIGN'), 'Foreign dashboard lead', $foreignFixture['admin']['admin_key'], $foreignFixture['admin']['admin_key']]
    ) !== false, 'Foreign Lead fixture could not be created.');
    sales_crm_assert($db->Execute(
        "INSERT INTO project_company_sales_campaign (
            campaign_key, company_key, company_key_hash, campaign_code, campaign_name, campaign_status,
            campaign_version, budget, expected_revenue, created_by_admin_key, updated_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, 'ACTIVE', 1, 9999.99, 99999.99, ?, ?)",
        [$foreignCampaignKey, $foreignFixture['company']['company_key'], $foreignHash, sales_crm_test_code('DASH_FOREIGN_CAMPAIGN'), 'Foreign active campaign', $foreignFixture['admin']['admin_key'], $foreignFixture['admin']['admin_key']]
    ) !== false, 'Foreign Campaign fixture could not be created.');

    $activityPayload = json_encode([
        'company_key' => (string) $company['company_key'],
        'admin_key' => (string) $admin['admin_key'],
        'lead_code' => 'DASH-ACTIVITY',
        'lead_name' => 'Dashboard activity lead',
        'lead_status' => 'OPEN',
    ], JSON_THROW_ON_ERROR);
    $foreignActivityPayload = json_encode([
        'company_key' => (string) $foreignFixture['company']['company_key'],
        'admin_key' => (string) $foreignFixture['admin']['admin_key'],
        'lead_code' => 'FOREIGN-ACTIVITY',
        'lead_name' => 'Foreign activity lead',
        'lead_status' => 'OPEN',
    ], JSON_THROW_ON_ERROR);
    sales_crm_assert($db->Execute(
        "INSERT INTO builder_audit_log (audit_key, action, module, record_key, new_values, reason, created_at)
         VALUES (?, 'UPDATE', 'project_company_sales_lead', ?, ?, 'Dashboard activity fixture.', UTC_TIMESTAMP()),
                (?, 'CREATE', 'project_company_sales_lead', ?, ?, 'Foreign activity fixture.', UTC_TIMESTAMP())",
        [$auditKeys[0], $leadKeys[0], $activityPayload, $auditKeys[1], $foreignLeadKey, $foreignActivityPayload]
    ) !== false, 'Dashboard audit fixtures could not be created.');

    $beforeLeadCount = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_lead WHERE company_key_hash = ?', [$companyHash]);
    $beforeCampaignCount = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_campaign WHERE company_key_hash = ?', [$companyHash]);
    $dashboard = yovel_admin_sales_crm_dashboard_data($company, $admin);
    sales_crm_assert(array_keys($dashboard) === ['summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directories', 'dependencies'], 'Dashboard provider does not return the shared envelope in stable order.');
    sales_crm_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_lead WHERE company_key_hash = ?', [$companyHash]) === $beforeLeadCount, 'Dashboard read changed Lead records.');
    sales_crm_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_campaign WHERE company_key_hash = ?', [$companyHash]) === $beforeCampaignCount, 'Dashboard read changed Campaign records.');

    $summaryByKey = array_column($dashboard['summary'], null, 'key');
    sales_crm_assert((int) ($summaryByKey['open-leads']['value'] ?? -1) === 2, 'Dashboard open Lead KPI is not live or company-scoped.');
    sales_crm_assert((string) ($summaryByKey['pipeline-value']['value'] ?? '') === '1500.25', 'Dashboard pipeline value is not deterministic.');
    sales_crm_assert((int) ($summaryByKey['active-campaigns']['value'] ?? -1) === 1, 'Dashboard active Campaign KPI is not live or company-scoped.');
    foreach (['qualified-opportunities', 'open-quotations', 'active-sales-orders'] as $dependencyKpi) {
        sales_crm_assert(($summaryByKey[$dependencyKpi]['availability'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Missing dashboard KPI dependency is not explicit: ' . $dependencyKpi);
        sales_crm_assert(array_key_exists('value', $summaryByKey[$dependencyKpi]) && $summaryByKey[$dependencyKpi]['value'] === null, 'Missing dashboard KPI dependency fabricated a numeric value: ' . $dependencyKpi);
    }

    sales_crm_assert(count($dashboard['queue']) === 2, 'Dashboard queue did not include one stale Lead and one ending Campaign.');
    sales_crm_assert(count($dashboard['queue']) <= 8, 'Dashboard queue is not bounded.');
    sales_crm_assert(str_contains((string) $dashboard['queue'][0]['href'], 'section=leads'), 'Stale Lead queue item does not open its Sales record.');
    sales_crm_assert(!str_contains(json_encode($dashboard['queue'], JSON_THROW_ON_ERROR), 'Foreign dashboard lead'), 'Foreign Lead leaked into the dashboard queue.');
    sales_crm_assert(count($dashboard['activity']) >= 1 && count($dashboard['activity']) <= 8, 'Dashboard activity is empty or unbounded.');
    sales_crm_assert((string) $dashboard['activity'][0]['record_label'] === 'Dashboard activity lead', 'Dashboard did not resolve the latest Sales audit label.');
    sales_crm_assert(!str_contains(json_encode($dashboard['activity'], JSON_THROW_ON_ERROR), 'Foreign activity lead'), 'Foreign audit activity leaked into the dashboard.');

    $setupByKey = array_column($dashboard['setup'], null, 'key');
    sales_crm_assert(($setupByKey['capture-lead']['complete'] ?? false) === true, 'Dashboard setup did not detect Lead readiness.');
    sales_crm_assert(($setupByKey['plan-campaign']['complete'] ?? false) === true, 'Dashboard setup did not detect Campaign readiness.');
    $shortcutByKey = array_column($dashboard['shortcuts'], null, 'key');
    sales_crm_assert(($shortcutByKey['form-builder']['available'] ?? false) === true && str_contains((string) $shortcutByKey['form-builder']['href'], 'section=leads'), 'Dashboard does not expose the Sales Form Builder.');
    $dependencyByContract = array_column($dashboard['dependencies'], null, 'contract');
    sales_crm_assert(($dependencyByContract['sales.opportunity.summary.v1']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Opportunity dependency status is missing.');
    sales_crm_assert(($dependencyByContract['sales.quotation.summary.v1']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Quotation dependency status is missing.');
    sales_crm_assert(($dependencyByContract['sales.order.summary.v1']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Sales Order dependency status is missing.');

    $unauthorized = $admin;
    $unauthorized['admin_key'] = bx_uuid();
    $unauthorizedRejected = false;
    try {
        yovel_admin_sales_crm_dashboard_data($company, $unauthorized);
    } catch (InvalidArgumentException) {
        $unauthorizedRejected = true;
    }
    sales_crm_assert($unauthorizedRejected, 'Unauthorized administrator could read the Sales / CRM dashboard.');

    $dashboardView = (string) file_get_contents($root . '/company/admin/modules/sales-crm/views/dashboard.php');
    $workspaceView = (string) file_get_contents($root . '/company/admin/modules/sales-crm/views/workspace.php');
    foreach ([
        'data-sales-dashboard-summary', 'data-sales-dashboard-queue', 'data-sales-dashboard-activity',
        'data-sales-dashboard-setup', 'data-sales-dashboard-alerts', 'data-sales-dashboard-shortcuts',
        'data-sales-dashboard-directory', 'data-sales-dashboard-tour', 'data-sales-dashboard-form-builder',
        'data-record-modal', 'data-record-modal-form', 'data-confirm-submit', 'data-confirm-submit-action',
    ] as $marker) {
        sales_crm_assert(str_contains($dashboardView, $marker), 'Dashboard view is missing contract marker: ' . $marker);
    }
    sales_crm_assert(str_contains($dashboardView, 'yovel-sales-crm-two-panel'), 'Dashboard view is missing the approved 12/8 workspace hook.');
    sales_crm_assert(str_contains($workspaceView, "activeSalesCrmSection === 'dashboard'"), 'Sales workspace does not dispatch the dashboard view.');
} finally {
    $cleanup();
}

echo "Sales / CRM live dashboard provider and workspace tests passed.\n";
