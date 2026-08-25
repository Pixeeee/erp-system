<?php
declare(strict_types=1);

require __DIR__ . '/compliance-localization-test-helper.php';

yovel_admin_compliance_schema();
$owner = compliance_test_create_company('Compliance Rules Owner');
$other = compliance_test_create_company('Compliance Rules Other');
compliance_test_register_cleanup($owner, $other);
$company = $owner['company'];
[$creator, $approver] = $owner['admins'];
$otherCompany = $other['company'];
$otherAdmin = $other['admins'][0];
$db = bx_db();
$financeValidator = compliance_test_finance_validator();
$outputAccountKey = bx_uuid();
$inputAccountKey = bx_uuid();

$draftInput = [
    'rule_type' => 'VAT_SETTINGS',
    'rule_code' => 'PH_VAT_CONFIGURATION',
    'rule_name' => 'Philippine VAT Configuration',
    'version_number' => 1,
    'effective_from' => '2026-01-01',
    'effective_to' => '2026-06-30',
    'authority_reference' => 'BIR VAT Authority 2026',
    'authority_url' => 'https://www.bir.gov.ph/',
    'vat_registration_class' => 'VAT_REGISTERED',
    'tax_code_policy_refs' => ['VAT12', 'ZERO_RATED', 'EXEMPT'],
    'retention_years' => 10,
    'invoice_profile_keys' => ['PH_STANDARD_TAX_INVOICE'],
    'mappings' => [
        ['mapping_code' => 'OUTPUT_STANDARD', 'tax_role' => 'OUTPUT_VAT', 'finance_account_key' => $outputAccountKey],
        ['mapping_code' => 'INPUT_STANDARD', 'tax_role' => 'INPUT_VAT', 'finance_account_key' => $inputAccountKey],
    ],
];

$draft = yovel_admin_compliance_save_rule_set($db, $company, $creator, $draftInput, $financeValidator);
compliance_test_assert($draft['version_status'] === 'DRAFT', 'New rule version is not Draft.');
compliance_test_assert(count($draft['mappings']) === 2, 'Draft account mappings were not persisted.');
$stableKey = (string) $draft['rule_set_key'];
$updated = yovel_admin_compliance_save_rule_set(
    $db,
    $company,
    $creator,
    [...$draftInput, 'rule_set_key' => $stableKey, 'rule_name' => 'Updated Philippine VAT Configuration'],
    $financeValidator
);
compliance_test_assert($updated['rule_set_key'] === $stableKey, 'Draft update changed the stable rule key.');
compliance_test_assert($updated['rule_name'] === 'Updated Philippine VAT Configuration', 'Draft update did not read back the changed name.');
compliance_test_assert(
    array_column($updated['mappings'], 'tax_account_map_key', 'mapping_code') === array_column($draft['mappings'], 'tax_account_map_key', 'mapping_code'),
    'Draft update changed stable account-mapping keys.'
);

$wrongScopeCode = 'WRONG_SCOPE_' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 8));
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_save_rule_set(
        $db,
        $company,
        $creator,
        [...$draftInput, 'rule_code' => $wrongScopeCode],
        static function (array $scope, string $accountKey): array {
            $result = compliance_test_finance_validator()($scope, $accountKey);
            $result['company_key_hash'] = str_repeat('0', 64);
            return $result;
        }
    ),
    'company scope'
);
compliance_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_rule_set WHERE company_key_hash = ? AND rule_code = ?', [$company['company_key_hash'], $wrongScopeCode]) === 0, 'Wrong-company Finance validation persisted a rule.');

compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_finance_account_reference(
        $company,
        bx_uuid(),
        static fn (array $scope, string $accountKey): string => 'malformed'
    ),
    'validation failed'
);

compliance_test_expect_exception(
    static function () use ($company): array {
        $accountKey = bx_uuid();
        return yovel_admin_compliance_finance_account_reference(
            $company,
            $accountKey,
            static function (array $scope, string $requestedKey): array {
                $result = compliance_test_finance_validator()($scope, $requestedKey);
                $result['record']['is_group'] = 'false';
                return $result;
            }
        );
    },
    'validation failed'
);

compliance_test_expect_exception(
    static function () use ($company): array {
        $accountKey = bx_uuid();
        return yovel_admin_compliance_finance_account_reference(
            $company,
            $accountKey,
            static function (array $scope, string $requestedKey): array {
                $result = compliance_test_finance_validator()($scope, $requestedKey);
                $result['record']['account_code'] = '';
                return $result;
            }
        );
    },
    'validation failed'
);

compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_approve_rule_set($db, $company, $creator, $stableKey, 'Creator approval'),
    'separation'
);
compliance_test_assert(yovel_admin_compliance_rule_set($otherCompany, $stableKey) === null, 'Cross-company rule read leaked a record.');
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_approve_rule_set($db, $otherCompany, $otherAdmin, $stableKey, 'Foreign approval'),
    'not found'
);

$approved = yovel_admin_compliance_approve_rule_set($db, $company, $approver, $stableKey, 'Approved for controlled use');
compliance_test_assert($approved['version_status'] === 'APPROVED', 'Rule approval did not persist Approved state.');
compliance_test_assert($approved['created_by_admin_key'] !== $approved['approved_by_admin_key'], 'Rule approval violated separation of duties.');
$approvedSnapshot = json_decode((string) $approved['rule_json'], true, 512, JSON_THROW_ON_ERROR);
compliance_test_assert(($approvedSnapshot['authority']['reference'] ?? '') === 'BIR VAT Authority 2026', 'Approved snapshot lost its legal authority.');
compliance_test_assert(count($approvedSnapshot['account_mappings'] ?? []) === 2, 'Approved snapshot did not freeze account mappings.');
compliance_test_assert(yovel_admin_compliance_checksum($approvedSnapshot) === $approved['rule_sha256'], 'Approved snapshot checksum does not match canonical content.');

compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_save_rule_set($db, $company, $creator, [...$draftInput, 'rule_set_key' => $stableKey], $financeValidator),
    'immutable'
);

$overlap = yovel_admin_compliance_save_rule_set(
    $db,
    $company,
    $creator,
    [...$draftInput, 'version_number' => 2, 'effective_from' => '2026-06-01', 'effective_to' => '2026-12-31'],
    $financeValidator
);
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_approve_rule_set($db, $company, $approver, (string) $overlap['rule_set_key'], 'Overlap probe'),
    'overlap'
);
yovel_admin_compliance_archive_rule_set($db, $company, $approver, (string) $overlap['rule_set_key'], 'Rejected overlapping draft');

$oldChecksum = (string) $approved['rule_sha256'];
$superseded = yovel_admin_compliance_supersede_rule_set($db, $company, $approver, $stableKey, 'Replaced by second-half settings');
compliance_test_assert($superseded['version_status'] === 'SUPERSEDED', 'Approved rule was not superseded.');
$successor = yovel_admin_compliance_save_rule_set(
    $db,
    $company,
    $creator,
    [...$draftInput, 'version_number' => 3, 'effective_from' => '2026-07-01', 'effective_to' => null],
    $financeValidator
);
$successor = yovel_admin_compliance_approve_rule_set($db, $company, $approver, (string) $successor['rule_set_key'], 'Second-half approval');
compliance_test_assert((string) yovel_admin_compliance_effective_rule_set($company, 'VAT_SETTINGS', '2026-03-15')['rule_set_key'] === $stableKey, 'Historical effective resolution lost the superseded version.');
compliance_test_assert((string) yovel_admin_compliance_effective_rule_set($company, 'VAT_SETTINGS', '2026-08-15')['rule_set_key'] === (string) $successor['rule_set_key'], 'Current effective resolution did not select the successor.');
compliance_test_assert((string) yovel_admin_compliance_rule_set($company, $stableKey)['rule_sha256'] === $oldChecksum, 'Superseding a rule mutated its approved snapshot.');

$rollbackCode = 'ROLLBACK_' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 8));
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_save_rule_set(
        $db,
        $company,
        $creator,
        [...$draftInput, 'rule_code' => $rollbackCode, 'version_number' => 1],
        $financeValidator,
        static function (string $checkpoint): void {
            if ($checkpoint === 'after_mappings') {
                throw new RuntimeException('injected rule rollback');
            }
        }
    ),
    'injected rule rollback'
);
compliance_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_rule_set WHERE company_key_hash = ? AND rule_code = ?', [$company['company_key_hash'], $rollbackCode]) === 0, 'Rule rollback left a parent row.');

$GLOBALS['yovel_admin_compliance_dependency_providers'] = ['accounting-finance.account-reference.v1' => $financeValidator];
$handlerDraft = yovel_admin_compliance_localization_handle_post($company, $creator, 'save_compliance_rule', [
    ...$draftInput,
    'rule_code' => 'HANDLER_RULE',
    'version_number' => 1,
    'section' => 'tax-rules',
    'module_view' => 'compliance-localization',
]);
unset($GLOBALS['yovel_admin_compliance_dependency_providers']);
compliance_test_assert($handlerDraft['section'] === 'tax-rules' && yovel_admin_is_uuid((string) ($handlerDraft['query']['rule'] ?? '')), 'Four-argument handler did not dispatch rule persistence.');

$activeModuleSections = yovel_admin_compliance_sections();
$activeModuleSection = 'vat-settings';
$activeModuleData = yovel_admin_compliance_data($company, $creator, 'vat-settings');
$activeModuleFormState = ['section' => 'vat-settings', 'action' => 'save_compliance_rule', 'input' => ['rule_name' => 'Retained invalid value'], 'error' => 'Retained validation error'];
$companyName = $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/compliance-localization/views/workspace.php';
require dirname(__DIR__) . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
foreach (['New rule', 'Add mapping', 'Approve', 'Supersede', 'Archive'] as $actionLabel) {
    compliance_test_assert(str_contains($markup, $actionLabel), 'Rules workspace is missing action: ' . $actionLabel);
}
compliance_test_assert(substr_count($markup, 'data-confirm-submit') >= 5, 'Rules actions do not each use sibling confirmation.');
compliance_test_assert(str_contains($markup, 'value="Retained invalid value"'), 'Rule failure did not rehydrate submitted values.');
compliance_test_assert(str_contains($markup, 'data-record-modal-open-on-load'), 'Rule failure did not reopen its owning modal.');

echo "Compliance / Localization WP-02 rule checks passed.\n";
