<?php
declare(strict_types=1);

require __DIR__ . '/compliance-localization-test-helper.php';

$expectedSections = [
    'dashboard',
    'tax-rules',
    'vat-settings',
    'supplier-einvoice-imports',
    'tax-document-templates',
    'einvoice-register',
    'withholding-certificates',
    'vat-returns',
    'vat-audit',
    'audit-evidence',
    'regulatory-exports',
    'form-builder',
];
compliance_test_assert(
    array_keys(yovel_admin_compliance_sections()) === $expectedSections,
    'Compliance sections are incomplete or out of order.'
);
compliance_test_assert(
    yovel_admin_compliance_localization_sections() === yovel_admin_compliance_sections(),
    'Registry section alias diverges from the module API.'
);
compliance_test_assert(yovel_admin_compliance_section('VAT Settings') === 'vat-settings', 'Section normalization failed.');
compliance_test_assert(yovel_admin_compliance_section('not-a-section') === 'dashboard', 'Unknown sections must resolve to Dashboard.');

$expectedTables = [
    'project_company_compliance_rule_set',
    'project_company_compliance_tax_account_map',
    'project_company_compliance_template',
    'project_company_compliance_template_version',
    'project_company_compliance_einvoice_record',
    'project_company_compliance_import_batch',
    'project_company_compliance_import_item',
    'project_company_compliance_withholding_authority',
    'project_company_compliance_withholding_certificate',
    'project_company_compliance_return_package',
    'project_company_compliance_evidence',
    'project_company_compliance_approval',
    'project_company_compliance_export',
    'project_company_compliance_retention_hold',
];

yovel_admin_compliance_schema();
yovel_admin_compliance_schema();
$db = bx_db();
$availableTables = array_fill_keys(array_map('strtolower', $db->MetaTables('TABLES') ?: []), true);
foreach ($expectedTables as $table) {
    compliance_test_assert(isset($availableTables[strtolower($table)]), 'Compliance schema table is missing: ' . $table);
    $companyIndexCount = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [BUILDERX_DB_NAME, $table, 'company_key_hash']
    );
    compliance_test_assert($companyIndexCount > 0, 'Compliance table lacks a company_key_hash index: ' . $table);
    $companyKeyLength = (int) $db->GetOne(
        'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [BUILDERX_DB_NAME, $table, 'company_key']
    );
    compliance_test_assert($companyKeyLength === 1500, 'Compliance table does not preserve the host company-key width: ' . $table);
}

$contracts = yovel_admin_compliance_lifecycle_contracts();
compliance_test_assert($contracts['version']['statuses'] === ['DRAFT', 'APPROVED', 'SUPERSEDED', 'ARCHIVED'], 'Version lifecycle statuses changed.');
compliance_test_assert($contracts['batch']['statuses'] === ['UPLOADED', 'VALIDATED', 'REJECTED', 'APPROVED', 'PROJECTING', 'PROJECTED', 'FAILED'], 'Batch lifecycle statuses changed.');
compliance_test_assert($contracts['certificate']['statuses'] === ['DRAFT', 'ISSUED', 'CANCELLED'], 'Certificate lifecycle statuses changed.');
compliance_test_assert($contracts['return']['statuses'] === ['DRAFT', 'REVIEWED', 'APPROVED', 'SUPERSEDED'], 'Return lifecycle statuses changed.');
compliance_test_assert($contracts['export']['statuses'] === ['DRAFT', 'APPROVED', 'QUEUED', 'PROCESSING', 'SUCCEEDED', 'FAILED', 'CANCELLED'], 'Export lifecycle statuses changed.');
yovel_admin_compliance_assert_transition('version', 'DRAFT', 'APPROVED');
yovel_admin_compliance_assert_transition('batch', 'PROJECTING', 'FAILED');
compliance_test_expect_exception(
    static fn (): null => yovel_admin_compliance_assert_transition('version', 'APPROVED', 'DRAFT'),
    'not allowed'
);
compliance_test_expect_exception(
    static fn (): null => yovel_admin_compliance_assert_transition('unknown', 'DRAFT', 'APPROVED'),
    'unknown lifecycle'
);

$payloadA = [
    'z' => 3,
    'nested' => ['beta' => true, 'alpha' => ['second' => 2, 'first' => 1]],
    'list' => [['b' => 2, 'a' => 1], 'x'],
];
$payloadB = [
    'list' => [['a' => 1, 'b' => 2], 'x'],
    'nested' => ['alpha' => ['first' => 1, 'second' => 2], 'beta' => true],
    'z' => 3,
];
compliance_test_assert(
    yovel_admin_compliance_checksum($payloadA) === yovel_admin_compliance_checksum($payloadB),
    'Canonical checksum changes when object keys are reordered.'
);
compliance_test_assert(
    yovel_admin_compliance_checksum(['list' => [1, 2]]) !== yovel_admin_compliance_checksum(['list' => [2, 1]]),
    'Canonical checksum must preserve list order.'
);
compliance_test_assert(
    preg_match('/^[a-f0-9]{64}$/', yovel_admin_compliance_checksum($payloadA)) === 1,
    'Canonical checksum is not lowercase SHA-256.'
);

[$company, $admin] = compliance_test_scope();
$scope = yovel_admin_compliance_scope($company, $admin);
compliance_test_assert($scope === [$company['company_key'], $company['company_key_hash'], $admin['admin_key']], 'Compliance scope returned unexpected keys.');

$opaqueSuffix = strtolower(substr(str_replace('-', '', bx_uuid()), 0, 12));
$opaqueCompanyKey = 'compliance:opaque/' . $opaqueSuffix;
$opaqueCompanyHash = hash('sha256', 'stored-membership:' . $opaqueCompanyKey);
$opaqueAdminKey = bx_uuid();
$db->Execute(
    "INSERT INTO project_company (company_key, company_key_hash, company_code, company_slug, company_name, company_status)
     VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
    [$opaqueCompanyKey, $opaqueCompanyHash, 'CMP' . strtoupper(substr($opaqueSuffix, 0, 8)), 'compliance-' . $opaqueSuffix, 'Compliance Opaque Scope']
);
$db->Execute(
    "INSERT INTO project_company_admin (admin_key, company_key, company_key_hash, admin_login, admin_password_hash, admin_name, admin_email, admin_status)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
    [$opaqueAdminKey, $opaqueCompanyKey, $opaqueCompanyHash, 'compliance_' . $opaqueSuffix, password_hash('Compliance-Test-Only-12345', PASSWORD_DEFAULT), 'Compliance Scope Administrator', 'compliance-' . $opaqueSuffix . '@example.test']
);
try {
    $opaqueScope = yovel_admin_compliance_scope(
        ['company_key' => $opaqueCompanyKey, 'company_key_hash' => $opaqueCompanyHash],
        ['admin_key' => $opaqueAdminKey, 'admin_status' => 'ACTIVE', 'company_key_hash' => $opaqueCompanyHash]
    );
    compliance_test_assert($opaqueScope === [$opaqueCompanyKey, $opaqueCompanyHash, $opaqueAdminKey], 'Opaque company identity was not authorized by exact membership.');
} finally {
    $db->Execute('DELETE FROM project_company_admin WHERE admin_key = ? AND company_key_hash = ?', [$opaqueAdminKey, $opaqueCompanyHash]);
    $db->Execute('DELETE FROM project_company WHERE company_key = ? AND company_key_hash = ?', [$opaqueCompanyKey, $opaqueCompanyHash]);
}

$badCompany = $company;
$badCompany['company_key_hash'] = str_repeat('0', 64);
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_scope($badCompany, $admin),
    'company scope'
);
$badAdmin = $admin;
$badAdmin['admin_status'] = 'LOCKED';
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_scope($company, $badAdmin),
    'administrator'
);
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_scope($company, ['admin_key' => bx_uuid(), 'admin_status' => 'ACTIVE']),
    'administrator'
);

$dataReflection = new ReflectionFunction('yovel_admin_compliance_localization_data');
compliance_test_assert($dataReflection->getNumberOfRequiredParameters() === 1, 'Registry data alias must accept company with optional admin.');
compliance_test_assert($dataReflection->getNumberOfParameters() === 2, 'Registry data alias signature changed.');
$postReflection = new ReflectionFunction('yovel_admin_compliance_localization_handle_post');
compliance_test_assert($postReflection->getNumberOfRequiredParameters() === 4, 'Registry POST alias must expose four required parameters.');

$_GET['section'] = 'vat-settings';
$registryData = yovel_admin_compliance_localization_data($company, $admin);
compliance_test_assert(($registryData['section'] ?? '') === 'vat-settings', 'Registry data alias did not honor the request section.');
compliance_test_assert(($registryData['company_key_hash'] ?? '') === $company['company_key_hash'], 'Registry data is not company scoped.');
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_localization_data($company, null),
    'administrator'
);
compliance_test_expect_exception(
    static fn (): array => yovel_admin_compliance_localization_handle_post($company, $admin, 'unavailable_compliance_action', []),
    'not available in WP-02'
);

$source = '';
foreach (glob(dirname(__DIR__) . '/company/admin/modules/compliance-localization/*.php') ?: [] as $path) {
    $source .= (string) file_get_contents($path);
}
foreach (['project_company_finance_', 'project_company_sales_', 'project_company_buying_', 'project_company_inventory_'] as $foreignPrefix) {
    compliance_test_assert(!str_contains($source, $foreignPrefix), 'Compliance WP-01 references a foreign authoritative table: ' . $foreignPrefix);
}

echo "Compliance / Localization WP-01 schema checks passed.\n";
