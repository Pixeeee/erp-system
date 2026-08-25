<?php
declare(strict_types=1);

require __DIR__ . '/compliance-localization-test-helper.php';

[$company, $admin] = compliance_test_scope();
$sections = yovel_admin_compliance_sections();
$activeModuleSections = $sections;
$activeModuleSection = 'dashboard';
$activeModuleData = yovel_admin_compliance_data($company, $admin, 'dashboard');
$activeModuleFormState = [];
$companyName = (string) $company['company_name'];

ob_start();
require dirname(__DIR__) . '/company/admin/modules/compliance-localization/views/workspace.php';
require dirname(__DIR__) . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();

foreach ([
    'data-compliance-workspace',
    'data-compliance-two-panel',
    'xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]',
    'grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr)',
    'data-compliance-main-panel',
    'data-grid-span="12"',
    'data-compliance-tools-panel',
    'data-grid-span="8"',
    'data-compliance-form-builder',
    'data-record-modal-open="compliance-form-builder-modal"',
    'data-record-modal',
    'data-confirm-submit',
    'data-confirm-dialog',
] as $marker) {
    compliance_test_assert(str_contains($markup, $marker), 'Compliance workspace is missing marker: ' . $marker);
}
compliance_test_assert(
    strpos($markup, 'data-compliance-main-panel') < strpos($markup, 'data-compliance-tools-panel'),
    'Responsive source order must keep the main panel first.'
);
compliance_test_assert(
    strpos($markup, 'data-confirm-dialog') > strpos($markup, '</form>'),
    'Confirmation dialog must remain a body-owned sibling of the non-mutating form.'
);
compliance_test_assert(!str_contains($markup, 'method="post"'), 'WP-01 must not expose a POST form.');
compliance_test_assert(!str_contains($markup, 'name="action"'), 'WP-01 must not expose a persistence action.');
compliance_test_assert(!str_contains($markup, 'ERPNext'), 'Workspace contains upstream product branding.');

foreach (array_keys($sections) as $section) {
    compliance_test_assert(
        str_contains($markup, 'view=compliance-localization&amp;section=' . $section),
        'Compliance workspace route is missing for section: ' . $section
    );
}

$moduleAdapter = yovel_admin_compliance_form_adapter();
$sharedAdapter = yovel_admin_shared_form_adapter('compliance-localization');
compliance_test_assert(($moduleAdapter['module'] ?? '') === 'compliance-localization', 'Module Form Builder adapter has the wrong owner.');
compliance_test_assert(($sharedAdapter['module'] ?? '') === 'compliance-localization', 'Shared Form Builder adapter has the wrong owner.');
compliance_test_assert(($moduleAdapter['shared_contract']['row_column_layout']['stable_keys'] ?? false) === true, 'Module adapter does not consume shared stable-key behavior.');
compliance_test_assert(isset($moduleAdapter['target_record_types']['vat-settings']), 'Module adapter is missing VAT settings.');
compliance_test_assert(in_array('rule_set_key', $moduleAdapter['protected_fields']['localization-rule'] ?? [], true), 'Module adapter does not protect rule identity.');

preg_match_all('/<button\b([^>]*)>(.*?)<\/button>/si', $markup, $buttons, PREG_SET_ORDER);
foreach ($buttons as $button) {
    if (preg_match('/(?:Add|New|Create|Insert)/i', strip_tags((string) $button[2])) !== 1) {
        continue;
    }
    compliance_test_assert(
        str_contains((string) $button[1], 'data-record-modal-open='),
        'Every Add/New/Create/Insert command must open a record modal.'
    );
}

$activeModuleSection = 'form-builder';
$activeModuleData = yovel_admin_compliance_data($company, $admin, 'form-builder');
ob_start();
require dirname(__DIR__) . '/company/admin/modules/compliance-localization/views/workspace.php';
$builderMarkup = (string) ob_get_clean();
compliance_test_assert(str_contains($builderMarkup, 'Field Toolbox'), 'Form Builder does not expose its toolbox panel.');
compliance_test_assert(str_contains($builderMarkup, 'Form Layout'), 'Form Builder does not expose its layout panel.');
compliance_test_assert(str_contains($builderMarkup, 'Field Properties'), 'Form Builder does not expose its properties panel.');
compliance_test_assert(str_contains($builderMarkup, 'Read-only foundation'), 'WP-01 Form Builder state is not clearly non-persistent.');

echo "Compliance / Localization WP-01 workspace checks passed.\n";
