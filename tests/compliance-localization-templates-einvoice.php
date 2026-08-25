<?php
declare(strict_types=1);

require __DIR__ . '/compliance-localization-test-helper.php';

yovel_admin_compliance_schema();
$owner = compliance_test_create_company('Compliance E-Invoice Owner');
$other = compliance_test_create_company('Compliance E-Invoice Other');
compliance_test_register_cleanup($owner, $other);
$company = $owner['company'];
[$creator, $approver] = $owner['admins'];
$otherCompany = $other['company'];
$otherAdmin = $other['admins'][0];
$db = bx_db();

$profileCodes = [
    'DETAILED' => 'PH_DETAILED_TAX_INVOICE',
    'SIMPLIFIED' => 'PH_SIMPLIFIED_TAX_INVOICE',
    'STANDARD' => 'PH_STANDARD_TAX_INVOICE',
    'PURCHASE' => 'PH_PURCHASE_EINVOICE',
];
$profiles = [];
foreach ($profileCodes as $profileType => $profileCode) {
    $draft = yovel_admin_compliance_save_template($db, $company, $creator, [
        'template_code' => $profileCode,
        'template_name' => ucwords(strtolower($profileType)) . ' Philippine E-Invoice',
        'profile_type' => $profileType,
        'version_number' => 1,
        'effective_from' => '2026-01-01',
    ]);
    compliance_test_assert($draft['version_status'] === 'DRAFT', 'Profile was not saved as Draft.');
    $stable = yovel_admin_compliance_save_template($db, $company, $creator, [
        'template_key' => $draft['template_key'],
        'template_version_key' => $draft['template_version_key'],
        'template_code' => $profileCode,
        'template_name' => $draft['template_name'],
        'profile_type' => $profileType,
        'version_number' => 1,
        'effective_from' => '2026-01-01',
    ]);
    compliance_test_assert($stable['template_key'] === $draft['template_key'], 'Draft update changed the stable template key.');
    compliance_test_expect_exception(
        static fn (): array => yovel_admin_compliance_save_template($db, $company, $creator, [
            'template_key' => $draft['template_key'],
            'template_version_key' => $draft['template_version_key'],
            'template_code' => $profileCode,
            'template_name' => $draft['template_name'],
            'profile_type' => $profileType,
            'version_number' => 1,
            'effective_from' => '2026-01-01',
            'fields' => [['field_key' => 'seller_registered_name', 'label' => 'Renamed seller']],
        ]),
        'protected'
    );
    compliance_test_expect_exception(
        static fn (): array => yovel_admin_compliance_publish_template($db, $company, $creator, (string) $draft['template_version_key'], 'Self approval'),
        'separation'
    );
    $published = yovel_admin_compliance_publish_template($db, $company, $approver, (string) $draft['template_version_key'], 'Approved output profile');
    compliance_test_assert($published['version_status'] === 'APPROVED', 'Profile publish did not approve its immutable version.');
    compliance_test_assert(yovel_admin_compliance_checksum(json_decode($published['schema_json'], true, 512, JSON_THROW_ON_ERROR)) === $published['schema_sha256'], 'Profile checksum is not canonical.');
    $profiles[$profileType] = $published;
}

$accountKey = bx_uuid();
$rule = yovel_admin_compliance_save_rule_set($db, $company, $creator, [
    'rule_type' => 'VAT_SETTINGS',
    'rule_code' => 'PH_EINVOICE_2026',
    'rule_name' => 'Philippine E-Invoice Rule',
    'version_number' => 1,
    'effective_from' => '2026-01-01',
    'authority_reference' => 'BIR E-Invoice Authority',
    'authority_url' => 'https://www.bir.gov.ph/',
    'vat_registration_class' => 'VAT_REGISTERED',
    'tax_code_policy_refs' => ['VAT12'],
    'retention_years' => 10,
    'invoice_profile_keys' => ['PH_STANDARD_TAX_INVOICE'],
    'mappings' => [['mapping_code' => 'OUTPUT_STANDARD', 'tax_role' => 'OUTPUT_VAT', 'finance_account_key' => $accountKey]],
], compliance_test_finance_validator());
$rule = yovel_admin_compliance_approve_rule_set($db, $company, $approver, (string) $rule['rule_set_key'], 'Approved for rendering');

$financeKey = bx_uuid();
$snapshot = [
    'document_type' => 'SALES_INVOICE',
    'invoice_number' => 'SI-2026-0001',
    'issue_date' => '2026-08-25',
    'currency_code' => 'PHP',
    'seller' => ['registered_name' => 'Seller Inc', 'tin' => '123-456-789-000', 'branch_code' => '00000', 'rdo_code' => '047'],
    'buyer' => ['registered_name' => 'Buyer Inc', 'tin' => '987-654-321-000'],
    'lines' => [['description' => 'Service', 'quantity' => '1.0000', 'amount' => '1000.00', 'vat_class' => 'VATABLE', 'vat_rate' => '12.000000', 'vat_base' => '1000.00', 'vat_amount' => '120.00']],
    'discount_amount' => '0.00',
    'subtotal_amount' => '1000.00',
    'total_amount' => '1120.00',
    'original_invoice_reference' => null,
    'amendment_reference' => null,
];
$provider = static function (array $scope, string $requestedKey) use ($company, $financeKey, $snapshot): array {
    return [
        'ok' => true,
        'company_key_hash' => $company['company_key_hash'],
        'record' => [
            'finance_document_key' => $financeKey,
            'finance_document_sha256' => yovel_admin_compliance_checksum($snapshot),
            'document_status' => 'SUBMITTED',
            'snapshot' => $snapshot,
        ],
        'errors' => [],
    ];
};

$unavailable = yovel_admin_compliance_einvoice_register($company);
compliance_test_assert($unavailable['availability'] === 'UNAVAILABLE_DEPENDENCY' && $unavailable['rows'] === [], 'Register did not fail closed without Finance.');
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_register_einvoice($db, $company, $creator, $financeKey),
    'UNAVAILABLE_DEPENDENCY'
);
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_render_invoice_snapshot($company, $financeKey, static fn (): string => 'malformed'),
    'validation failed'
);
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_render_invoice_snapshot($company, $financeKey, static function (array $scope, string $key) use ($snapshot): array {
        return ['ok' => true, 'company_key_hash' => str_repeat('0', 64), 'record' => ['finance_document_key' => $key, 'finance_document_sha256' => yovel_admin_compliance_checksum($snapshot), 'document_status' => 'SUBMITTED', 'snapshot' => $snapshot], 'errors' => []];
    }),
    'validation failed'
);
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_render_invoice_snapshot($company, $financeKey, static function (array $scope, string $key) use ($snapshot): array {
        return ['ok' => true, 'company_key_hash' => $scope['company_key_hash'], 'record' => ['finance_document_key' => $key, 'finance_document_sha256' => yovel_admin_compliance_checksum($snapshot), 'document_status' => 'DRAFT', 'snapshot' => $snapshot], 'errors' => []];
    }),
    'validation failed'
);
$renderA = yovel_admin_compliance_render_invoice_snapshot($company, $financeKey, $provider);
$renderB = yovel_admin_compliance_render_invoice_snapshot($company, $financeKey, $provider);
compliance_test_assert($renderA['payload_sha256'] === $renderB['payload_sha256'], 'Identical inputs did not render deterministically.');
compliance_test_assert($renderA['rule_set_key'] === $rule['rule_set_key'], 'Render did not bind the approved effective rule.');
compliance_test_assert($renderA['template_version_key'] === $profiles['STANDARD']['template_version_key'], 'Render did not select the approved rule profile.');
compliance_test_assert($renderA['finance_document_sha256'] === yovel_admin_compliance_checksum($snapshot), 'Render lost the Finance source checksum.');

$registered = yovel_admin_compliance_register_einvoice($db, $company, $creator, $financeKey, $provider);
$duplicate = yovel_admin_compliance_register_einvoice($db, $company, $creator, $financeKey, $provider);
compliance_test_assert($duplicate['einvoice_record_key'] === $registered['einvoice_record_key'], 'Register source/schema idempotency failed.');
$GLOBALS['yovel_admin_compliance_dependency_providers'] = ['accounting-finance.invoice-snapshot.v1' => $provider];
$handlerRegistered = yovel_admin_compliance_localization_handle_post($company, $creator, 'register_compliance_einvoice', ['module_view' => 'compliance-localization', 'section' => 'einvoice-register', 'finance_document_key' => $financeKey]);
unset($GLOBALS['yovel_admin_compliance_dependency_providers']);
compliance_test_assert($handlerRegistered['section'] === 'einvoice-register' && $handlerRegistered['query']['einvoice'] === $registered['einvoice_record_key'], 'Four-argument handler did not dispatch idempotent register persistence.');
$queued = yovel_admin_compliance_update_transmission($db, $company, $creator, (string) $registered['einvoice_record_key'], 'QUEUED');
$processing = yovel_admin_compliance_update_transmission($db, $company, $creator, (string) $registered['einvoice_record_key'], 'PROCESSING');
$acknowledged = yovel_admin_compliance_update_transmission($db, $company, $creator, (string) $registered['einvoice_record_key'], 'ACKNOWLEDGED', 'ACK-2026-0001');
compliance_test_assert($queued['transmission_status'] === 'QUEUED' && $processing['transmission_status'] === 'PROCESSING', 'Transmission lifecycle was not persisted.');
compliance_test_assert($acknowledged['acknowledgement_reference'] === 'ACK-2026-0001', 'Acknowledgement exact read-back failed.');
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_update_transmission($db, $company, $creator, (string) $registered['einvoice_record_key'], 'QUEUED'),
    'not allowed'
);

$correctionFinanceKey = bx_uuid();
$correctionSnapshot = [...$snapshot, 'invoice_number' => 'SI-2026-0001-A', 'original_invoice_reference' => 'SI-2026-0001', 'amendment_reference' => 'AMEND-1'];
$correctionProvider = static function (array $scope, string $requestedKey) use ($company, $correctionFinanceKey, $correctionSnapshot): array {
    return ['ok' => true, 'company_key_hash' => $company['company_key_hash'], 'record' => ['finance_document_key' => $correctionFinanceKey, 'finance_document_sha256' => yovel_admin_compliance_checksum($correctionSnapshot), 'document_status' => 'SUBMITTED', 'snapshot' => $correctionSnapshot], 'errors' => []];
};
$correction = yovel_admin_compliance_register_einvoice($db, $company, $creator, $correctionFinanceKey, $correctionProvider, (string) $registered['einvoice_record_key']);
compliance_test_assert($correction['correction_of_record_key'] === $registered['einvoice_record_key'], 'Correction lineage was not retained.');

$rows = yovel_admin_compliance_einvoice_register($company, ['date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'status' => 'ACKNOWLEDGED'], $provider);
compliance_test_assert($rows['availability'] === 'AVAILABLE' && count($rows['rows']) === 1, 'Parameterized status/date register filters failed.');
compliance_test_assert(yovel_admin_compliance_einvoice_register($otherCompany, [], $provider)['rows'] === [], 'Cross-company register read leaked records.');
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_update_transmission($db, $otherCompany, $otherAdmin, (string) $correction['einvoice_record_key'], 'QUEUED'),
    'not found'
);

$rollbackFinanceKey = bx_uuid();
$rollbackProvider = static function (array $scope, string $requestedKey) use ($company, $rollbackFinanceKey, $snapshot): array {
    $changed = [...$snapshot, 'invoice_number' => 'SI-ROLLBACK'];
    return ['ok' => true, 'company_key_hash' => $company['company_key_hash'], 'record' => ['finance_document_key' => $rollbackFinanceKey, 'finance_document_sha256' => yovel_admin_compliance_checksum($changed), 'document_status' => 'SUBMITTED', 'snapshot' => $changed], 'errors' => []];
};
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_register_einvoice($db, $company, $creator, $rollbackFinanceKey, $rollbackProvider, null, static function (string $point): void { if ($point === 'after_insert') throw new RuntimeException('injected register rollback'); }),
    'injected register rollback'
);
compliance_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_einvoice_record WHERE company_key_hash = ? AND finance_document_key = ?', [$company['company_key_hash'], $rollbackFinanceKey]) === 0, 'Register rollback left a row.');

$oldSchema = $profiles['STANDARD']['schema_sha256'];
$v2 = yovel_admin_compliance_save_template($db, $company, $creator, ['template_key' => $profiles['STANDARD']['template_key'], 'template_code' => 'PH_STANDARD_TAX_INVOICE', 'template_name' => 'Standard Philippine E-Invoice', 'profile_type' => 'STANDARD', 'version_number' => 2, 'effective_from' => '2027-01-01']);
yovel_admin_compliance_publish_template($db, $company, $approver, (string) $v2['template_version_key'], 'Publish successor');
$oldRow = yovel_admin_compliance_template_version($company, (string) $profiles['STANDARD']['template_version_key']);
compliance_test_assert($oldRow['schema_sha256'] === $oldSchema, 'Publishing a successor changed historical profile content.');

$GLOBALS['yovel_admin_compliance_dependency_providers'] = ['accounting-finance.invoice-snapshot.v1' => $provider];
$activeModuleSections = yovel_admin_compliance_sections();
$activeModuleSection = 'tax-document-templates';
$activeModuleData = yovel_admin_compliance_data($company, $creator, 'tax-document-templates');
$activeModuleFormState = ['section' => 'tax-document-templates', 'action' => 'save_compliance_template', 'input' => ['template_name' => 'Retained profile name'], 'error' => 'Retained profile error'];
$companyName = $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/compliance-localization/views/workspace.php';
require dirname(__DIR__) . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
unset($GLOBALS['yovel_admin_compliance_dependency_providers']);
foreach (['New profile', 'Publish', 'Archive', 'data-record-modal-open-on-load', 'value="Retained profile name"'] as $marker) {
    compliance_test_assert(str_contains($markup, $marker), 'Template workspace is missing marker: ' . $marker);
}
compliance_test_assert(substr_count($markup, 'data-confirm-submit') >= 3, 'Template actions lack sibling confirmation.');

echo "Compliance / Localization WP-03 template and e-invoice checks passed.\n";
