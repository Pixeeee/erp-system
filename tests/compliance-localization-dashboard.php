<?php
declare(strict_types=1);

require __DIR__ . '/compliance-localization-test-helper.php';

yovel_admin_compliance_schema();
$owner = compliance_test_create_company('Compliance Dashboard Owner');
$other = compliance_test_create_company('Compliance Dashboard Other');
compliance_test_register_cleanup($owner, $other);
$company = $owner['company'];
[$creator, $approver] = $owner['admins'];
$otherCompany = $other['company'];
$otherAdmin = $other['admins'][0];
$db = bx_db();
$financeValidator = compliance_test_finance_validator();
$GLOBALS['yovel_admin_compliance_dependency_providers'] = [
    'accounting-finance.account-reference.v1' => $financeValidator,
];

$ruleInput = [
    'rule_type' => 'VAT_SETTINGS',
    'rule_code' => 'PH_DASHBOARD_VAT',
    'rule_name' => 'Philippine Dashboard VAT',
    'version_number' => 1,
    'effective_from' => (new DateTimeImmutable('today'))->modify('-30 days')->format('Y-m-d'),
    'effective_to' => (new DateTimeImmutable('today'))->modify('+1 year')->format('Y-m-d'),
    'authority_reference' => 'Dashboard BIR authority',
    'authority_url' => 'https://www.bir.gov.ph/',
    'vat_registration_class' => 'VAT_REGISTERED',
    'tax_code_policy_refs' => ['VAT12'],
    'retention_years' => 10,
    'invoice_profile_keys' => ['PH_STANDARD_TAX_INVOICE'],
    'mappings' => [
        ['mapping_code' => 'OUTPUT_STANDARD', 'tax_role' => 'OUTPUT_VAT', 'finance_account_key' => bx_uuid()],
        ['mapping_code' => 'INPUT_STANDARD', 'tax_role' => 'INPUT_VAT', 'finance_account_key' => bx_uuid()],
    ],
];
$approvedDraft = yovel_admin_compliance_save_rule_set($db, $company, $creator, $ruleInput, $financeValidator);
$approved = yovel_admin_compliance_approve_rule_set(
    $db,
    $company,
    $approver,
    (string) $approvedDraft['rule_set_key'],
    'Dashboard approval fixture'
);

$gapDraft = yovel_admin_compliance_save_rule_set(
    $db,
    $company,
    $creator,
    [
        ...$ruleInput,
        'rule_code' => 'PH_DASHBOARD_GAP',
        'rule_name' => 'Philippine Mapping Gap',
        'version_number' => 1,
        'mappings' => [],
    ],
    $financeValidator
);
$db->Execute(
    "INSERT INTO project_company_compliance_approval (
        approval_key, company_key, company_key_hash, record_type, record_key,
        approval_action, decision_status, record_sha256, comments, requested_by_admin_key
     ) VALUES (?, ?, ?, 'RULE_SET', ?, 'APPROVE', 'PENDING', ?, ?, ?)",
    [
        bx_uuid(),
        (string) $company['company_key'],
        (string) $company['company_key_hash'],
        (string) $gapDraft['rule_set_key'],
        (string) $gapDraft['rule_sha256'],
        'Pending independent review',
        (string) $creator['admin_key'],
    ]
);

$heldEvidence = yovel_admin_compliance_save_evidence(
    $db,
    $company,
    $creator,
    compliance_test_upload('dashboard-held-evidence-' . bx_uuid()),
    [
        'evidence_type' => 'LEGAL_SOURCE',
        'source_module' => 'COMPLIANCE_LOCALIZATION',
        'source_record_type' => 'RULE_SET',
        'evidence_date' => date('Y-m-d'),
        'description' => 'Dashboard held evidence',
    ]
);
$db->Execute(
    'UPDATE project_company_compliance_evidence SET retention_until = ? WHERE company_key_hash = ? AND evidence_key = ?',
    [(new DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d'), (string) $company['company_key_hash'], (string) $heldEvidence['evidence_key']]
);
$hold = yovel_admin_compliance_place_hold(
    $db,
    $company,
    $approver,
    (string) $heldEvidence['evidence_key'],
    'DASHBOARD-HOLD-' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 8)),
    'Open review requires indefinite retention.'
);

$invalidEvidence = yovel_admin_compliance_save_evidence(
    $db,
    $company,
    $creator,
    compliance_test_upload('dashboard-invalid-evidence-' . bx_uuid()),
    [
        'evidence_type' => 'SOURCE_DOCUMENT',
        'source_module' => 'COMPLIANCE_LOCALIZATION',
        'source_record_type' => 'RULE_SET',
        'evidence_date' => date('Y-m-d'),
        'description' => 'Dashboard invalid evidence probe',
    ]
);
unlink((string) $invalidEvidence['storage_path']);

compliance_test_assert(
    function_exists('yovel_admin_compliance_localization_dashboard_data'),
    'Compliance live dashboard provider is missing.'
);
$dashboardFinanceCalls = 0;
$GLOBALS['yovel_admin_compliance_dependency_providers'] = [
    'accounting-finance.account-reference.v1' => static function () use (&$dashboardFinanceCalls): array {
        $dashboardFinanceCalls++;
        throw new RuntimeException('Dashboard must not call the Finance account-reference provider.');
    },
];
$writeCountsBefore = [
    'rules' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_rule_set WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'mappings' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_tax_account_map WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'approvals' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_approval WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'evidence' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_evidence WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'holds' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_retention_hold WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'audits' => (int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module LIKE 'project_company_compliance_%' AND new_values LIKE ?", ['%' . $company['company_key_hash'] . '%']),
];
$dashboard = yovel_admin_compliance_localization_dashboard_data($company, $creator);
$writeCountsAfter = [
    'rules' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_rule_set WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'mappings' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_tax_account_map WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'approvals' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_approval WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'evidence' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_evidence WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'holds' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_retention_hold WHERE company_key_hash = ?', [$company['company_key_hash']]),
    'audits' => (int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module LIKE 'project_company_compliance_%' AND new_values LIKE ?", ['%' . $company['company_key_hash'] . '%']),
];
compliance_test_assert($dashboardFinanceCalls === 0, 'Dashboard invoked the Finance account-reference provider during a read.');
compliance_test_assert($writeCountsAfter === $writeCountsBefore, 'Dashboard reads mutated Compliance or audit records.');
compliance_test_assert(
    (string) $db->GetOne('SELECT decision_status FROM project_company_compliance_approval WHERE company_key_hash = ? AND record_key = ?', [$company['company_key_hash'], $gapDraft['rule_set_key']]) === 'PENDING',
    'Dashboard read bypassed maker-checker approval state.'
);
compliance_test_assert(
    (string) $db->GetOne('SELECT hold_status FROM project_company_compliance_retention_hold WHERE company_key_hash = ? AND retention_hold_key = ?', [$company['company_key_hash'], $hold['retention_hold_key']]) === 'ACTIVE',
    'Dashboard read released a legal hold.'
);
compliance_test_assert(
    (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_evidence WHERE company_key_hash = ? AND evidence_key = ?', [$company['company_key_hash'], $invalidEvidence['evidence_key']]) === 1,
    'Dashboard integrity scan removed invalid evidence metadata.'
);
foreach (['summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directories', 'dependencies'] as $key) {
    compliance_test_assert(is_array($dashboard[$key] ?? null), 'Dashboard envelope is missing: ' . $key);
}

$summary = array_column($dashboard['summary'], null, 'key');
foreach (['active-rule-sets', 'pending-approvals', 'evidence-retention-due', 'invalid-evidence', 'active-legal-holds', 'mapping-gaps'] as $key) {
    compliance_test_assert(isset($summary[$key]), 'Dashboard summary is missing KPI: ' . $key);
    compliance_test_assert(($summary[$key]['availability'] ?? '') === 'AVAILABLE', 'Live Compliance KPI is not available: ' . $key);
}
compliance_test_assert((int) $summary['active-rule-sets']['value'] === 1, 'Active rule-set KPI is not company live data.');
compliance_test_assert((int) $summary['pending-approvals']['value'] === 1, 'Pending approval KPI is not company live data.');
compliance_test_assert((int) $summary['evidence-retention-due']['value'] === 1, 'Retention-due KPI is not company live data.');
compliance_test_assert((int) $summary['invalid-evidence']['value'] === 1, 'Invalid-evidence KPI is not checksum/file live data.');
compliance_test_assert((int) $summary['active-legal-holds']['value'] === 1, 'Legal-hold KPI is not company live data.');
compliance_test_assert((int) $summary['mapping-gaps']['value'] === 1, 'Mapping-gap KPI is not company live data.');

$queueStatuses = array_column($dashboard['queue'], 'status');
foreach (['PENDING_APPROVAL', 'INVALID_EVIDENCE', 'MAPPING_GAP', 'LEGAL_HOLD'] as $status) {
    compliance_test_assert(in_array($status, $queueStatuses, true), 'Dashboard action queue is missing status: ' . $status);
}
compliance_test_assert(count($dashboard['activity']) >= 4, 'Recent Compliance activity is not populated from module audits.');
compliance_test_assert(count($dashboard['activity']) <= 12, 'Recent Compliance activity is not bounded.');
compliance_test_assert(
    ($dashboard['dependencies'][0]['contract'] ?? '') === 'accounting-finance.account-reference.v1'
    && ($dashboard['dependencies'][0]['status'] ?? '') === 'AVAILABLE',
    'Finance account-reference dependency is not live.'
);
compliance_test_assert(
    count(array_filter($dashboard['setup'], static fn (array $step): bool => ($step['complete'] ?? false) === false)) >= 1,
    'Dashboard setup does not identify incomplete Compliance work.'
);
compliance_test_assert(
    count(array_filter($dashboard['shortcuts'], static fn (array $shortcut): bool => ($shortcut['key'] ?? '') === 'form-builder' && ($shortcut['available'] ?? false))) === 1,
    'Dashboard does not expose the module Form Builder shortcut.'
);

$otherDashboard = yovel_admin_compliance_localization_dashboard_data($otherCompany, $otherAdmin);
foreach ($otherDashboard['summary'] as $item) {
    compliance_test_assert(($item['availability'] ?? '') !== 'AVAILABLE' || (int) ($item['value'] ?? 0) === 0, 'Dashboard leaked another company KPI.');
}
compliance_test_assert($otherDashboard['queue'] === [], 'Dashboard leaked another company action queue.');
compliance_test_assert($otherDashboard['activity'] === [], 'Dashboard leaked another company audit activity.');

$GLOBALS['yovel_admin_compliance_dependency_providers'] = [];
$unavailableDashboard = yovel_admin_compliance_localization_dashboard_data($company, $creator);
compliance_test_assert(
    ($unavailableDashboard['dependencies'][0]['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY',
    'Missing Finance provider was converted to a fabricated available state.'
);
$GLOBALS['yovel_admin_compliance_dependency_providers'] = [];

$activeModuleSections = yovel_admin_compliance_sections();
$activeModuleSection = 'dashboard';
$activeModuleData = yovel_admin_compliance_data($company, $creator, 'dashboard');
$activeModuleFormState = [];
$companyName = (string) $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/compliance-localization/views/workspace.php';
require dirname(__DIR__) . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();

foreach ([
    'data-compliance-live-dashboard',
    'data-compliance-dashboard-summary',
    'data-compliance-dashboard-queue',
    'data-compliance-dashboard-activity',
    'data-compliance-dashboard-setup',
    'data-compliance-dashboard-alerts',
    'data-compliance-dashboard-shortcuts',
    'data-compliance-dashboard-directories',
    'data-compliance-dashboard-dependencies',
    'data-compliance-tour-start',
    'data-compliance-tour-dialog',
    'data-compliance-tour-next',
    'data-compliance-tour-back',
    'data-compliance-tour-finish',
    'data-compliance-tour-skip',
    'data-compliance-form-builder',
    'data-record-modal-open="compliance-form-builder-modal"',
    'data-confirm-submit',
    'data-confirm-dialog',
    'data-compliance-two-panel',
    'xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]',
] as $marker) {
    compliance_test_assert(str_contains($markup, $marker), 'Live dashboard markup is missing: ' . $marker);
}
compliance_test_assert(
    strpos($markup, 'data-compliance-main-panel') < strpos($markup, 'data-compliance-tools-panel'),
    'Live dashboard must keep the main panel first in mobile source order.'
);
compliance_test_assert(!str_contains($markup, 'method="post"'), 'Read-only dashboard introduced a pre-confirm mutation form.');
compliance_test_assert(!str_contains($markup, 'ERPNext'), 'Dashboard contains upstream product branding.');
compliance_test_assert(str_contains($markup, 'UNAVAILABLE_DEPENDENCY'), 'Dashboard does not render explicit dependency unavailability.');
compliance_test_assert(str_contains($markup, 'localStorage'), 'Guided tour does not retain company/module dismissal state.');
compliance_test_assert(str_contains($markup, 'DOMContentLoaded'), 'Guided tour binds before the tools-panel trigger exists.');
compliance_test_assert(str_contains($markup, 'complianceFormBuilderEscapeBound'), 'Dashboard Form Builder does not support Escape focus restoration.');
compliance_test_assert(str_contains($markup, 'Compliance action queue'), 'Dashboard does not render its live action queue.');
compliance_test_assert(str_contains($markup, 'Recent activity'), 'Dashboard does not render recent activity.');

echo "Compliance / Localization live dashboard checks passed.\n";
