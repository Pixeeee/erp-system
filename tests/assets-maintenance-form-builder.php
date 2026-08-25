<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/assets-maintenance/functions.php';

function assets_builder_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = bx_db();
$fixtureSuffix = substr(hash('sha256', bx_uuid()), 0, 16);
$companyKey = 'assets:builder/' . $fixtureSuffix . '/opaque';
$companyKeyHash = hash('sha256', $companyKey);
$companyCode = 'AFB' . strtoupper(substr($fixtureSuffix, 0, 12));
$companySlug = 'assets-builder-' . $fixtureSuffix;
$adminKey = bx_uuid();
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id), 0) FROM builder_audit_log');

assets_builder_assert($db->Execute(
    "INSERT INTO project_company (
        company_key, company_key_hash, company_code, company_slug, company_name, company_status
     ) VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
    [$companyKey, $companyKeyHash, $companyCode, $companySlug, 'Assets Builder Company']
) !== false, 'Assets Builder company fixture could not be created.');
assets_builder_assert($db->Execute(
    "INSERT INTO project_company_admin (
        admin_key, company_key, company_key_hash, admin_login, admin_password_hash,
        admin_name, admin_email, admin_status
     ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
    [$adminKey, $companyKey, $companyKeyHash, 'assets_builder_' . $fixtureSuffix, password_hash('builder-only', PASSWORD_DEFAULT), 'Assets Builder Admin', 'assets-builder-' . $fixtureSuffix . '@example.test']
) !== false, 'Assets Builder administrator fixture could not be created.');

$assetTables = [
    'project_company_asset_form_submission',
    'project_company_asset_form_audit',
    'project_company_asset_form_version',
    'project_company_asset_form',
];
register_shutdown_function(static function () use ($db, $assetTables, $companyKeyHash, $companyKey, $adminKey, $auditFloor): void {
    foreach ($assetTables as $table) {
        $exists = (int) $db->GetOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
        if ($exists === 1) {
            $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$companyKeyHash]);
        }
    }
    $db->Execute("DELETE FROM builder_audit_log WHERE x_id > ? AND module = 'project_company_asset_form'", [$auditFloor]);
    $db->Execute('DELETE FROM project_company_admin WHERE admin_key = ? AND company_key_hash = ?', [$adminKey, $companyKeyHash]);
    $db->Execute('DELETE FROM project_company WHERE company_key = ? AND company_key_hash = ?', [$companyKey, $companyKeyHash]);
});

$company = ['company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'company_name' => 'Assets Builder Company'];
$admin = ['admin_key' => $adminKey, 'admin_status' => 'ACTIVE'];

yovel_admin_assets_maintenance_schema();
$adapter = yovel_admin_assets_form_adapter();
$expectedTargets = [
    'asset-records' => 'ASSET',
    'asset-depreciation-schedule' => 'ASSET_DEPRECIATION_SCHEDULE',
    'maintenance-schedules' => 'MAINTENANCE_SCHEDULE',
    'quality-inspection' => 'QUALITY_INSPECTION_LINK',
];
assets_builder_assert(($adapter['target_record_types'] ?? []) === $expectedTargets, 'Assets Form Builder targets are incomplete.');
assets_builder_assert(($adapter['row_column_layout'] ?? []) === ['version' => 1, 'max_columns' => 3, 'stable_keys' => true], 'Assets Form Builder row/column contract changed.');
assets_builder_assert(($adapter['normalize'] ?? '') === 'yovel_admin_assets_normalize_form_schema', 'Assets adapter normalization callable is missing.');
assets_builder_assert(($adapter['version_identity'] ?? '') === 'yovel_admin_assets_form_version_checksum', 'Assets adapter version identity callable is missing.');

$defaults = yovel_admin_assets_default_form_schemas();
assets_builder_assert(array_keys($defaults) === array_values($expectedTargets), 'Assets default schemas do not cover every configurable target.');
foreach ($defaults as $recordType => $schema) {
    $protected = $adapter['protected_fields'][$recordType] ?? [];
    assets_builder_assert($protected !== [], 'Assets protected fields are missing for ' . $recordType . '.');
    foreach ($protected as $fieldKey) {
        assets_builder_assert(in_array($fieldKey, array_column($schema['fields'], 'key'), true), 'Protected field is absent from its default schema: ' . $fieldKey);
    }
}

$assetSchema = $defaults['ASSET'];
$assetSchema['fields'][] = [
    'key' => 'service_interval_days',
    'label' => 'Service interval days',
    'type' => 'NUMBER',
    'section' => 'maintenance',
    'required' => true,
    'visible' => true,
    'system' => false,
    'row' => 3,
    'column' => 2,
    'width' => 'HALF',
    'order' => 900,
    'default' => '30',
    'options' => [],
    'validation' => ['min' => 1, 'max' => 3650],
];
$normalized = yovel_admin_assets_normalize_form_schema('ASSET', $assetSchema, 1);
$normalizedFields = array_column($normalized['fields'], null, 'key');
assets_builder_assert(($normalizedFields['service_interval_days']['section'] ?? '') === 'maintenance', 'Assets custom section key did not normalize.');
assets_builder_assert(($normalizedFields['service_interval_days']['column'] ?? 0) === 2, 'Assets custom column placement was not retained.');
assets_builder_assert(($normalizedFields['service_interval_days']['default'] ?? '') === '30', 'Assets custom default was not retained.');
assets_builder_assert(($normalizedFields['service_interval_days']['validation']['max'] ?? 0) === 3650, 'Assets custom validation was not retained.');

$withoutProtected = $assetSchema;
$withoutProtected['fields'] = array_values(array_filter(
    $withoutProtected['fields'],
    static fn (array $field): bool => !in_array((string) ($field['key'] ?? ''), $adapter['protected_fields']['ASSET'], true)
));
$normalizedWithoutProtected = yovel_admin_assets_normalize_form_schema('ASSET', $withoutProtected, 1);
foreach ($adapter['protected_fields']['ASSET'] as $fieldKey) {
    assets_builder_assert(in_array($fieldKey, array_column($normalizedWithoutProtected['fields'], 'key'), true), 'Normalization removed protected field: ' . $fieldKey);
}

$saveInput = [
    'target_section' => 'asset-records',
    'record_type' => 'ASSET',
    'form_title' => 'Asset lifecycle form',
    'form_description' => 'Company-specific asset lifecycle fields.',
    'schema_json' => yovel_admin_assets_form_json($normalized),
];
$savedV1 = yovel_admin_assets_save_form($company, $admin, $saveInput);
assets_builder_assert((int) ($savedV1['version_number'] ?? 0) === 1, 'First Assets form save must create version 1.');
assets_builder_assert(($savedV1['form_status'] ?? '') === 'DRAFT', 'New Assets form must remain Draft until publish.');
assets_builder_assert((string) ($savedV1['version_checksum'] ?? '') === yovel_admin_assets_form_version_checksum(
    'ASSET',
    'Asset lifecycle form',
    'Company-specific asset lifecycle fields.',
    (string) $savedV1['schema_json']
), 'Assets form version checksum does not match canonical content.');
$formKey = (string) $savedV1['form_key'];
$v1Key = (string) $savedV1['current_version_key'];

$readV1 = yovel_admin_assets_form($company, $formKey);
assets_builder_assert(($readV1['persisted'] ?? false) === true, 'Saved Assets form was not rehydrated from the database.');
assets_builder_assert((string) ($readV1['form_title'] ?? '') === 'Asset lifecycle form', 'Assets form title read-back is incorrect.');
assets_builder_assert((string) ($readV1['current_version_key'] ?? '') === $v1Key, 'Assets current version read-back is incorrect.');

$publishedV1 = yovel_admin_assets_publish_form($company, $admin, $formKey);
assets_builder_assert(($publishedV1['form_status'] ?? '') === 'PUBLISHED', 'Assets form did not publish.');
assets_builder_assert((string) ($publishedV1['published_version_key'] ?? '') === $v1Key, 'Published Assets version key is incorrect.');

$v2Schema = $normalized;
foreach ($v2Schema['fields'] as &$field) {
    if (($field['key'] ?? '') === 'service_interval_days') {
        $field['label'] = 'Planned service interval';
    }
}
unset($field);
$savedV2 = yovel_admin_assets_save_form($company, $admin, array_merge($saveInput, [
    'form_key' => $formKey,
    'schema_json' => yovel_admin_assets_form_json($v2Schema),
]));
assets_builder_assert((int) ($savedV2['version_number'] ?? 0) === 2, 'Changed Assets form must create version 2.');
assets_builder_assert(($savedV2['form_status'] ?? '') === 'DRAFT', 'Changed Assets form must return to Draft review.');
$v2Key = (string) $savedV2['current_version_key'];
assets_builder_assert($v2Key !== $v1Key, 'Changed Assets form reused its immutable version key.');
$storedV1 = yovel_admin_assets_form_version($company, $v1Key);
assets_builder_assert(($storedV1['version_status'] ?? '') === 'PUBLISHED', 'Creating version 2 mutated published version 1 status.');
assets_builder_assert((string) ($storedV1['schema_json'] ?? '') === (string) $savedV1['schema_json'], 'Creating version 2 mutated published version 1 content.');

$sameV2 = yovel_admin_assets_save_form($company, $admin, [
    'form_key' => $formKey,
    'target_section' => 'asset-records',
    'record_type' => 'ASSET',
    'form_title' => 'Asset lifecycle form',
    'form_description' => 'Company-specific asset lifecycle fields.',
    'schema_json' => (string) $savedV2['schema_json'],
]);
assets_builder_assert((int) ($sameV2['version_number'] ?? 0) === 2, 'Unchanged Assets form save created another version.');
assets_builder_assert((int) $db->GetOne(
    'SELECT COUNT(*) FROM project_company_asset_form_version WHERE company_key_hash = ? AND form_key = ?',
    [$companyKeyHash, $formKey]
) === 2, 'Idempotent Assets form save created a duplicate immutable version.');

$publishedV2 = yovel_admin_assets_publish_form($company, $admin, $formKey);
assets_builder_assert((string) ($publishedV2['published_version_key'] ?? '') === $v2Key, 'Assets version 2 did not publish.');
$submission = yovel_admin_assets_save_form_submission($company, $admin, [
    'form_key' => $formKey,
    'subject_owner_key' => 'inventory:item/opaque-owner-key-001',
    'values_json' => json_encode(['service_interval_days' => '45'], JSON_THROW_ON_ERROR),
]);
$submissionKey = (string) $submission['submission_key'];
assets_builder_assert((string) ($submission['form_version_key'] ?? '') === $v2Key, 'Assets submission was not pinned to published version 2.');

$v3Schema = $v2Schema;
$v3Schema['fields'][] = [
    'key' => 'maintenance_note', 'label' => 'Maintenance note', 'type' => 'PARAGRAPH',
    'section' => 'maintenance', 'required' => false, 'visible' => true, 'system' => false,
    'row' => 4, 'column' => 1, 'width' => 'FULL', 'order' => 1000, 'default' => '',
    'options' => [], 'validation' => ['max_length' => 500],
];
$savedV3 = yovel_admin_assets_save_form($company, $admin, [
    'form_key' => $formKey,
    'target_section' => 'asset-records',
    'record_type' => 'ASSET',
    'form_title' => 'Asset lifecycle form',
    'form_description' => 'Company-specific asset lifecycle fields.',
    'schema_json' => yovel_admin_assets_form_json($v3Schema),
]);
yovel_admin_assets_publish_form($company, $admin, $formKey);
$updatedSubmission = yovel_admin_assets_save_form_submission($company, $admin, [
    'submission_key' => $submissionKey,
    'form_key' => $formKey,
    'subject_owner_key' => 'inventory:item/opaque-owner-key-001',
    'values_json' => json_encode(['service_interval_days' => '60'], JSON_THROW_ON_ERROR),
]);
assets_builder_assert((string) ($updatedSubmission['form_version_key'] ?? '') === $v2Key, 'Updating an Assets submission changed its pinned form version.');
assets_builder_assert((string) ($updatedSubmission['values_json'] ?? '') !== (string) ($submission['values_json'] ?? ''), 'Assets submission update was not read back.');
assets_builder_assert((int) ($savedV3['version_number'] ?? 0) === 3, 'Assets version 3 was not created before submission pinning check.');

$handlerResult = yovel_admin_assets_maintenance_handle_post($company, $admin, 'save_assets_form', [
    'form_key' => $formKey,
    'target_section' => 'asset-records',
    'record_type' => 'ASSET',
    'form_title' => 'Asset lifecycle form',
    'form_description' => 'Company-specific asset lifecycle fields.',
    'schema_json' => (string) $savedV3['schema_json'],
]);
assets_builder_assert(($handlerResult['section'] ?? '') === 'form-builder', 'Assets registry handler did not return to Form Builder.');
assets_builder_assert(($handlerResult['query']['form'] ?? '') === $formKey, 'Assets registry handler dropped the saved form key.');

$archived = yovel_admin_assets_archive_form($company, $admin, $formKey, 'Superseded by a controlled replacement.');
assets_builder_assert(($archived['form_status'] ?? '') === 'ARCHIVED', 'Assets form did not archive.');
assets_builder_assert(yovel_admin_assets_form_version($company, $v1Key) !== null, 'Archived Assets version 1 is no longer readable.');
assets_builder_assert(yovel_admin_assets_form_version($company, $v2Key) !== null, 'Archived Assets version 2 is no longer readable.');

$faultTitle = 'Rollback asset form ' . $fixtureSuffix;
$GLOBALS['yovel_admin_assets_test_fault'] = 'after_version_write';
try {
    yovel_admin_assets_save_form($company, $admin, [
        'target_section' => 'asset-records',
        'record_type' => 'ASSET',
        'form_title' => $faultTitle,
        'form_description' => 'This transaction must roll back.',
        'schema_json' => yovel_admin_assets_form_json($normalized),
    ]);
    throw new RuntimeException('Forced Assets transaction fault did not propagate.');
} catch (RuntimeException $error) {
    assets_builder_assert(str_contains($error->getMessage(), 'forced'), 'Assets rollback returned an unexpected error.');
} finally {
    unset($GLOBALS['yovel_admin_assets_test_fault']);
}
assets_builder_assert((int) $db->GetOne(
    'SELECT COUNT(*) FROM project_company_asset_form WHERE company_key_hash = ? AND form_title = ?',
    [$companyKeyHash, $faultTitle]
) === 0, 'Failed Assets transaction left a form header.');
assets_builder_assert((int) $db->GetOne(
    'SELECT COUNT(*) FROM project_company_asset_form_version WHERE company_key_hash = ? AND form_key NOT IN (SELECT form_key FROM project_company_asset_form WHERE company_key_hash = ?)',
    [$companyKeyHash, $companyKeyHash]
) === 0, 'Failed Assets transaction left an orphan version.');

$auditCount = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_asset_form_audit WHERE company_key_hash = ?', [$companyKeyHash]);
assets_builder_assert($auditCount >= 7, 'Assets Form Builder lifecycle did not persist row-level audit evidence.');

echo "Assets / Maintenance Task 1 Form Builder checks passed.\n";
