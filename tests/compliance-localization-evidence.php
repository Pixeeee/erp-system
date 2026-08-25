<?php
declare(strict_types=1);

require __DIR__ . '/compliance-localization-test-helper.php';

yovel_admin_compliance_schema();
$owner = compliance_test_create_company('Compliance Evidence Owner');
$other = compliance_test_create_company('Compliance Evidence Other');
compliance_test_register_cleanup($owner, $other);
$company = $owner['company'];
[$creator, $approver] = $owner['admins'];
$otherCompany = $other['company'];
$otherAdmin = $other['admins'][0];
$db = bx_db();

$retentionDraft = yovel_admin_compliance_save_rule_set($db, $company, $creator, [
    'rule_type' => 'RETENTION_POLICY',
    'rule_code' => 'PH_EVIDENCE_RETENTION',
    'rule_name' => 'Philippine Evidence Retention',
    'version_number' => 1,
    'effective_from' => '2026-01-01',
    'effective_to' => null,
    'authority_reference' => 'BIR Record Retention Policy',
    'authority_url' => 'https://www.bir.gov.ph/',
    'vat_registration_class' => 'NOT_APPLICABLE',
    'tax_code_policy_refs' => [],
    'retention_years' => 10,
    'invoice_profile_keys' => [],
    'mappings' => [],
]);
$retentionRule = yovel_admin_compliance_approve_rule_set($db, $company, $approver, (string) $retentionDraft['rule_set_key'], 'Retention approval');

$upload = compliance_test_upload('Immutable Philippine tax evidence', 'bir-evidence.txt');
$evidence = yovel_admin_compliance_save_evidence($db, $company, $creator, $upload, [
    'evidence_type' => 'LEGAL_SOURCE',
    'source_module' => 'COMPLIANCE_LOCALIZATION',
    'source_record_type' => 'RULE_SET',
    'source_record_key' => (string) $retentionRule['rule_set_key'],
    'retention_rule_set_key' => (string) $retentionRule['rule_set_key'],
    'evidence_date' => '2026-01-15',
    'description' => 'Official retained source evidence',
]);
compliance_test_assert($evidence['retention_until'] === '2036-01-15', 'Evidence retention was not calculated from the approved rule.');
compliance_test_assert(is_file((string) $evidence['storage_path']), 'Evidence file was not moved to generated storage.');
compliance_test_assert(hash_file('sha256', (string) $evidence['storage_path']) === $evidence['sha256'], 'Evidence file checksum does not match metadata.');
compliance_test_assert(yovel_admin_compliance_verify_evidence($company, (string) $evidence['evidence_key']) === true, 'Evidence verification failed.');
compliance_test_assert(yovel_admin_compliance_evidence($otherCompany, (string) $evidence['evidence_key']) === null, 'Cross-company evidence read leaked a row.');

$duplicateUpload = compliance_test_upload('Immutable Philippine tax evidence', 'duplicate.txt');
try {
    compliance_test_expect_exception(
        static fn (): array => yovel_admin_compliance_save_evidence($db, $company, $creator, $duplicateUpload, [
            'evidence_type' => 'LEGAL_SOURCE',
            'source_module' => 'COMPLIANCE_LOCALIZATION',
            'source_record_type' => 'RULE_SET',
            'source_record_key' => (string) $retentionRule['rule_set_key'],
            'retention_rule_set_key' => (string) $retentionRule['rule_set_key'],
            'evidence_date' => '2026-01-15',
        ]),
        'duplicate'
    );
} finally {
    if (is_file((string) $duplicateUpload['tmp_name'])) {
        unlink((string) $duplicateUpload['tmp_name']);
    }
}

$invalidUpload = compliance_test_upload('plain text pretending to be an image', 'invalid.png', 'image/png');
try {
    compliance_test_expect_exception(
        static fn (): array => yovel_admin_compliance_save_evidence($db, $company, $creator, $invalidUpload, [
            'evidence_type' => 'LEGAL_SOURCE',
            'source_module' => 'COMPLIANCE_LOCALIZATION',
            'source_record_type' => 'RULE_SET',
            'source_record_key' => (string) $retentionRule['rule_set_key'],
            'retention_rule_set_key' => (string) $retentionRule['rule_set_key'],
            'evidence_date' => '2026-01-15',
        ]),
        'mime'
    );
} finally {
    if (is_file((string) $invalidUpload['tmp_name'])) {
        unlink((string) $invalidUpload['tmp_name']);
    }
}

$storageRoot = dirname(__DIR__) . '/company/admin/modules/compliance-localization/storage/evidence';
$beforeFiles = is_dir($storageRoot) ? array_values(array_filter(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS))), 'is_file')) : [];
$rollbackUpload = compliance_test_upload('Rollback-only tax evidence', 'rollback.txt');
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_save_evidence(
        $db,
        $company,
        $creator,
        $rollbackUpload,
        [
            'evidence_type' => 'LEGAL_SOURCE',
            'source_module' => 'COMPLIANCE_LOCALIZATION',
            'source_record_type' => 'ROLLBACK_PROBE',
            'source_record_key' => bx_uuid(),
            'retention_rule_set_key' => (string) $retentionRule['rule_set_key'],
            'evidence_date' => '2026-02-01',
        ],
        static function (string $checkpoint): void {
            if ($checkpoint === 'after_final_move') {
                throw new RuntimeException('injected evidence rollback');
            }
        }
    ),
    'injected evidence rollback'
);
$afterFiles = is_dir($storageRoot) ? array_values(array_filter(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS))), 'is_file')) : [];
compliance_test_assert(count($afterFiles) === count($beforeFiles), 'Evidence rollback left a generated file.');
compliance_test_assert((int) $db->GetOne("SELECT COUNT(*) FROM project_company_compliance_evidence WHERE company_key_hash = ? AND source_record_type = 'ROLLBACK_PROBE'", [$company['company_key_hash']]) === 0, 'Evidence rollback left metadata.');

$hold = yovel_admin_compliance_place_hold($db, $company, $approver, (string) $evidence['evidence_key'], 'BIR-CASE-2026-001', 'Open BIR review');
compliance_test_assert($hold['hold_status'] === 'ACTIVE', 'Evidence hold was not activated.');
$heldEvidence = yovel_admin_compliance_evidence($company, (string) $evidence['evidence_key']);
compliance_test_assert($heldEvidence['evidence_status'] === 'HELD' && $heldEvidence['retention_state'] === 'INDEFINITE_HOLD', 'Open hold did not extend retention indefinitely.');
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_archive_evidence($db, $company, $creator, (string) $evidence['evidence_key'], 'Archive while held'),
    'hold'
);
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_place_hold($db, $otherCompany, $otherAdmin, (string) $evidence['evidence_key'], 'FOREIGN-HOLD', 'Foreign hold'),
    'not found'
);
$released = yovel_admin_compliance_release_hold($db, $company, $approver, (string) $hold['retention_hold_key'], 'BIR review closed');
compliance_test_assert($released['hold_status'] === 'RELEASED', 'Evidence hold was not released.');
compliance_test_assert(yovel_admin_compliance_evidence($company, (string) $evidence['evidence_key'])['evidence_status'] === 'ACTIVE', 'Evidence remained Held after its final hold was released.');
$releaseAudit = (string) $db->GetOne("SELECT new_values FROM builder_audit_log WHERE module = 'project_company_compliance_retention_hold' AND record_key = ? AND action = 'RELEASE' ORDER BY x_id DESC LIMIT 1", [(string) $hold['retention_hold_key']]);
compliance_test_assert(str_contains($releaseAudit, (string) $approver['admin_key']), 'Hold release audit lost its actor.');

$activeModuleSections = yovel_admin_compliance_sections();
$activeModuleSection = 'audit-evidence';
$activeModuleData = yovel_admin_compliance_data($company, $creator, 'audit-evidence');
$activeModuleFormState = ['section' => 'audit-evidence', 'action' => 'save_compliance_evidence', 'input' => ['description' => 'Retained evidence description'], 'error' => 'Retained evidence error'];
$companyName = $company['company_name'];
ob_start();
require dirname(__DIR__) . '/company/admin/modules/compliance-localization/views/workspace.php';
require dirname(__DIR__) . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
foreach (['Add evidence', 'Place hold', 'Release hold', 'Archive'] as $actionLabel) {
    compliance_test_assert(str_contains($markup, $actionLabel), 'Evidence workspace is missing action: ' . $actionLabel);
}
compliance_test_assert(substr_count($markup, 'data-confirm-submit') >= 4, 'Evidence actions do not each use sibling confirmation.');
compliance_test_assert(str_contains($markup, 'Retained evidence description'), 'Evidence failure did not rehydrate submitted values.');
compliance_test_assert(str_contains($markup, 'data-record-modal-open-on-load'), 'Evidence failure did not reopen its owning modal.');

echo "Compliance / Localization WP-02 evidence checks passed.\n";
