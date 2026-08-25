<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once __DIR__ . '/buying-procurement-test-helper.php';

$schemaPath = $root . '/company/admin/modules/buying-procurement/schema.php';
$corePath = $root . '/company/admin/modules/buying-procurement/core.php';
if (is_file($schemaPath)) {
    require_once $schemaPath;
}
if (is_file($corePath)) {
    require_once $corePath;
}

yovel_admin_buying_schema();
yovel_admin_buying_schema();

$db = bx_db();
$fixture = buying_test_company_admin_fixture($db);
$company = $fixture['company'];
$admin = $fixture['admin'];
$secondCompany = buying_test_secondary_company($db, (string) $company['company_key_hash']);
$suffix = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$supplierCode = 'FOUNDATION_' . $suffix;
$primarySupplierKey = bx_uuid();
$secondarySupplierKey = bx_uuid();
$inactiveAdminKey = bx_uuid();
$seriesCode = 'BUYING_TEST_' . $suffix;
$seriesKey = '';

$requiredTables = [
    'project_company_buying_setting',
    'project_company_buying_number_series',
    'project_company_buying_supplier',
    'project_company_buying_supplier_customer_number',
    'project_company_buying_supplier_company',
    'project_company_buying_rfq',
    'project_company_buying_rfq_supplier',
    'project_company_buying_rfq_item',
    'project_company_buying_supplier_quotation',
    'project_company_buying_supplier_quotation_item',
    'project_company_buying_purchase_order',
    'project_company_buying_purchase_order_item',
    'project_company_buying_purchase_order_supplied_item',
    'project_company_buying_handoff',
    'project_company_buying_scorecard_definition',
    'project_company_buying_scorecard_criteria',
    'project_company_buying_scorecard_variable',
    'project_company_buying_scorecard_standing',
    'project_company_buying_scorecard_period',
    'project_company_buying_scorecard_period_score',
];

foreach ($requiredTables as $table) {
    $tableCount = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    buying_test_assert($tableCount === 1, 'Buying / Procurement schema table is missing: ' . $table);
}

$requiredIndexes = [
    'uq_project_company_buying_setting_company',
    'uq_project_company_buying_series',
    'uq_project_company_buying_supplier_code',
    'idx_project_company_buying_supplier_status',
    'uq_project_company_buying_rfq_number',
    'uq_project_company_buying_supplier_quotation_number',
    'uq_project_company_buying_purchase_order_number',
    'uq_project_company_buying_handoff_idempotency',
];
foreach ($requiredIndexes as $indexName) {
    $indexCount = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND INDEX_NAME = ?',
        [BUILDERX_DB_NAME, $indexName]
    );
    buying_test_assert($indexCount >= 1, 'Buying / Procurement schema index is missing: ' . $indexName);
}

buying_test_execute(
    $db,
    "INSERT INTO project_company_buying_supplier (
        supplier_key, company_key, company_key_hash, supplier_code, supplier_name,
        supplier_type, supplier_status, created_by_admin_key, updated_by_admin_key
    ) VALUES (?, ?, ?, ?, ?, 'COMPANY', 'ACTIVE', ?, ?)",
    [
        $primarySupplierKey,
        $company['company_key'],
        $company['company_key_hash'],
        $supplierCode,
        'Primary Foundation Supplier',
        $admin['admin_key'],
        $admin['admin_key'],
    ],
    'Primary Buying supplier fixture insertion'
);
buying_test_execute(
    $db,
    "INSERT INTO project_company_buying_supplier (
        supplier_key, company_key, company_key_hash, supplier_code, supplier_name,
        supplier_type, supplier_status, created_by_admin_key, updated_by_admin_key
    ) VALUES (?, ?, ?, ?, ?, 'COMPANY', 'ACTIVE', NULL, NULL)",
    [
        $secondarySupplierKey,
        $secondCompany['company_key'],
        $secondCompany['company_key_hash'],
        $supplierCode,
        'Secondary Foundation Supplier',
    ],
    'Secondary Buying supplier fixture insertion'
);

try {
    buying_test_assert(
        (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_buying_supplier WHERE supplier_code = ?',
            [$supplierCode]
        ) === 2,
        'The same supplier code cannot be isolated across two companies.'
    );
    buying_test_assert(
        (string) $db->GetOne(
            'SELECT supplier_key FROM project_company_buying_supplier WHERE company_key_hash = ? AND supplier_code = ?',
            [$company['company_key_hash'], $supplierCode]
        ) === $primarySupplierKey,
        'Company-scoped supplier lookup returned another company record.'
    );
    buying_test_assert(
        (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_buying_supplier WHERE company_key_hash = ? AND supplier_key = ?',
            [$company['company_key_hash'], $secondarySupplierKey]
        ) === 0,
        'Cross-company supplier lookup was not isolated.'
    );

    [$companyKey, $companyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);
    buying_test_assert($companyKey === $company['company_key'], 'Buying scope changed the company key.');
    buying_test_assert($companyHash === $company['company_key_hash'], 'Buying scope changed the company hash.');
    buying_test_assert($adminKey === $admin['admin_key'], 'Buying scope changed the administrator key.');

    buying_test_expect_error(
        static fn () => yovel_admin_buying_scope($company, ['admin_key' => bx_uuid(), 'admin_status' => 'ACTIVE']),
        'authorized'
    );

    buying_test_execute(
        $db,
        "INSERT INTO project_company_admin (
            admin_key, company_key, company_key_hash, admin_login, admin_password_hash,
            admin_name, admin_email, admin_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'INACTIVE')",
        [
            $inactiveAdminKey,
            $company['company_key'],
            $company['company_key_hash'],
            'buying_inactive_' . strtolower($suffix),
            password_hash('NotUsed-12345', PASSWORD_DEFAULT),
            'Inactive Buying Test',
            'buying-inactive-' . strtolower($suffix) . '@example.test',
        ],
        'Inactive Buying administrator fixture insertion'
    );
    buying_test_expect_error(
        static fn () => yovel_admin_buying_scope($company, ['admin_key' => $inactiveAdminKey, 'admin_status' => 'INACTIVE']),
        'authorized'
    );
    buying_test_assert(
        (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_buying_number_series WHERE company_key_hash = ? AND series_code = ?',
            [$company['company_key_hash'], $seriesCode]
        ) === 0,
        'Unauthorized scope validation mutated the Buying number series.'
    );

    $firstNumber = yovel_admin_buying_number($db, $company, $admin, $seriesCode, 'BUY-');
    $secondNumber = yovel_admin_buying_number($db, $company, $admin, $seriesCode, 'BUY-');
    $year = date('Y');
    buying_test_assert(
        $firstNumber === 'BUY-' . $year . '-00001' && $secondNumber === 'BUY-' . $year . '-00002',
        'Buying document numbering is not deterministic.'
    );
    $seriesRow = $db->GetRow(
        'SELECT * FROM project_company_buying_number_series WHERE company_key_hash = ? AND series_code = ?',
        [$company['company_key_hash'], $seriesCode]
    );
    buying_test_assert(is_array($seriesRow) && $seriesRow !== [], 'Buying number-series read-back is missing.');
    $seriesKey = (string) $seriesRow['number_series_key'];
    buying_test_assert((int) $seriesRow['next_number'] === 3, 'Buying number-series update was not read back exactly.');
    buying_test_assert((string) $seriesRow['updated_by_admin_key'] === $admin['admin_key'], 'Buying number series lost its actor key.');
    buying_test_assert(
        (int) $db->GetOne(
            "SELECT COUNT(*) FROM builder_audit_log
            WHERE module = 'project_company_buying_number_series' AND record_key = ? AND action = 'NUMBER'",
            [$seriesKey]
        ) === 2,
        'Buying number-series transactions did not persist both audit events.'
    );
} finally {
    if ($seriesKey !== '') {
        buying_test_execute(
            $db,
            "DELETE FROM builder_audit_log WHERE module = 'project_company_buying_number_series' AND record_key = ?",
            [$seriesKey],
            'Buying number-series audit cleanup'
        );
    }
    buying_test_execute(
        $db,
        'DELETE FROM project_company_buying_number_series WHERE company_key_hash = ? AND series_code = ?',
        [$company['company_key_hash'], $seriesCode],
        'Buying number-series cleanup'
    );
    buying_test_execute(
        $db,
        'DELETE FROM project_company_buying_supplier WHERE supplier_key IN (?, ?)',
        [$primarySupplierKey, $secondarySupplierKey],
        'Buying supplier fixture cleanup'
    );
    buying_test_execute(
        $db,
        'DELETE FROM project_company_admin WHERE admin_key = ?',
        [$inactiveAdminKey],
        'Buying administrator fixture cleanup'
    );
}

buying_test_assert(
    (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_buying_supplier WHERE supplier_key IN (?, ?)',
        [$primarySupplierKey, $secondarySupplierKey]
    ) === 0,
    'Buying supplier fixtures were not cleaned up.'
);
buying_test_assert(
    (int) $db->GetOne('SELECT COUNT(*) FROM project_company_admin WHERE admin_key = ?', [$inactiveAdminKey]) === 0,
    'Buying administrator fixture was not cleaned up.'
);

echo "Buying / Procurement schema checks passed.\n";
