<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/buying-procurement/schema.php';
require_once $root . '/company/admin/modules/buying-procurement/core.php';
require_once __DIR__ . '/buying-procurement-test-helper.php';

$formsPath = $root . '/company/admin/modules/buying-procurement/forms.php';
if (is_file($formsPath)) {
    require_once $formsPath;
}

$adapter = yovel_admin_buying_procurement_form_adapter();
$expectedTargets = [
    'buying-settings' => 'buying-setting',
    'suppliers' => 'supplier',
    'request-for-quotation' => 'request-for-quotation',
    'supplier-quotations' => 'supplier-quotation',
    'purchase-orders' => 'purchase-order',
    'supplier-scorecards' => 'supplier-scorecard',
    'scorecard-criteria' => 'supplier-scorecard-criteria',
    'scorecard-standings' => 'supplier-scorecard-standing',
    'scorecard-variables' => 'supplier-scorecard-variable',
];

buying_test_assert(($adapter['module'] ?? '') === 'buying-procurement', 'Buying Form Builder adapter returned the wrong owner.');
buying_test_assert(($adapter['target_record_types'] ?? []) === $expectedTargets, 'Buying Form Builder targets are incomplete or unstable.');
buying_test_assert(($adapter['row_column_layout']['stable_keys'] ?? false) === true, 'Buying Form Builder does not guarantee stable field keys.');
buying_test_assert((int) ($adapter['row_column_layout']['max_columns'] ?? 0) === 3, 'Buying Form Builder must support up to three columns.');
buying_test_assert(($adapter['version_contract']['published_immutable'] ?? false) === true, 'Published Buying Form Builder versions are not immutable.');
buying_test_assert(($adapter['version_contract']['statuses'] ?? []) === ['DRAFT', 'PUBLISHED', 'ARCHIVED'], 'Buying Form Builder version states changed unexpectedly.');

$defaultSchemas = yovel_admin_buying_procurement_default_form_schemas();
buying_test_assert(array_keys($defaultSchemas) === array_values($expectedTargets), 'Buying built-in form schemas do not cover every adapter target.');
foreach ($defaultSchemas as $recordType => $schema) {
    buying_test_assert(($schema['recordType'] ?? '') === $recordType, 'Buying schema returned the wrong record type: ' . $recordType);
    buying_test_assert(($schema['requiredSystemFields'] ?? []) !== [], 'Buying schema has no required protected fields: ' . $recordType);
    buying_test_assert(($schema['fields'] ?? []) !== [], 'Buying schema has no fields: ' . $recordType);
    $fieldKeys = array_column($schema['fields'], 'key');
    foreach ($schema['requiredSystemFields'] as $protectedKey) {
        buying_test_assert(in_array($protectedKey, $fieldKeys, true), 'Protected Buying field is absent from its schema: ' . $protectedKey);
        buying_test_assert(in_array($protectedKey, $adapter['protected_fields'][$recordType] ?? [], true), 'Adapter lost protected Buying field: ' . $protectedKey);
    }
}

$supplierFields = $defaultSchemas['supplier']['fields'];
$supplierReordered = yovel_admin_buying_procurement_form_reorder(
    'supplier',
    $supplierFields,
    ['supplier_name', 'renamed_supplier_key', 'supplier_code']
);
$reorderedKeys = array_column($supplierReordered, 'key');
buying_test_assert($reorderedKeys[0] === 'supplier_name', 'Buying Form Builder did not apply a valid reorder.');
buying_test_assert(!in_array('renamed_supplier_key', $reorderedKeys, true), 'Buying Form Builder accepted a renamed stable key.');
buying_test_assert(in_array('supplier_key', $reorderedKeys, true), 'Buying Form Builder removed a protected supplier key.');
buying_test_assert(count($reorderedKeys) === count($supplierFields), 'Buying Form Builder removed an omitted existing field.');

$firstIdentity = yovel_admin_buying_procurement_form_version_identity('supplier', $supplierReordered);
$secondIdentity = yovel_admin_buying_procurement_form_version_identity('supplier', $supplierReordered);
buying_test_assert(preg_match('/^[0-9a-f]{64}$/', $firstIdentity) === 1, 'Buying Form Builder version identity is not a SHA-256 hash.');
buying_test_assert($firstIdentity === $secondIdentity, 'Buying Form Builder version identity is not deterministic.');

yovel_admin_buying_procurement_assert_form_editable(['form_status' => 'DRAFT']);
buying_test_expect_error(
    static fn () => yovel_admin_buying_procurement_assert_form_editable(['form_status' => 'PUBLISHED']),
    'immutable'
);

echo "Buying / Procurement Form Builder checks passed.\n";
