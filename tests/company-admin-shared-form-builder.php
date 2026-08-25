<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/hr/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/forms.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';

function shared_form_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (['hr', 'accounting-finance'] as $module) {
    $adapter = yovel_admin_shared_form_adapter($module);
    foreach (['module', 'target_record_types', 'protected_fields', 'field_types', 'row_column_layout', 'normalize', 'version_identity', 'renderer'] as $key) {
        shared_form_assert(array_key_exists($key, $adapter), 'Shared form adapter is missing ' . $key . ' for ' . $module . '.');
    }
    shared_form_assert($adapter['module'] === $module, 'Shared form adapter returned the wrong module owner.');
    shared_form_assert($adapter['target_record_types'] !== [], 'Shared form adapter exposes no target record types for ' . $module . '.');
}

$financeAdapter = yovel_admin_shared_form_adapter('accounting-finance');
shared_form_assert($financeAdapter === yovel_admin_finance_builder_adapter(), 'Shared Finance adapter must delegate to the Finance-owned adapter.');
shared_form_assert(($financeAdapter['row_column_layout']['version'] ?? 0) === 2, 'Shared Finance adapter must expose layout version 2.');
shared_form_assert(($financeAdapter['row_column_layout']['max_columns'] ?? 0) === 3, 'Shared Finance adapter must expose three-column layouts.');

foreach (array_keys(yovel_admin_module_registry()) as $module) {
    $adapter = yovel_admin_shared_form_adapter($module);
    shared_form_assert($adapter['module'] === $module, 'Registered module does not resolve its shared Form Builder adapter: ' . $module);
    shared_form_assert($adapter['target_record_types'] !== [], 'Registered module has no Form Builder target contract: ' . $module);
    shared_form_assert(!empty($adapter['row_column_layout']['stable_keys']), 'Registered module does not protect stable Form Builder keys: ' . $module);
}

$hrFields = [
    ['key' => 'employee_code', 'label' => 'Series'],
    ['key' => 'first_name', 'label' => 'First name'],
    ['key' => 'custom_note', 'label' => 'Custom note'],
];
$hrReordered = yovel_admin_shared_form_reorder('hr', 'employee-profiles', $hrFields, ['custom_note', 'renamed_employee_code', 'first_name']);
$hrKeys = array_column($hrReordered, 'key');
shared_form_assert($hrKeys === ['custom_note', 'first_name', 'employee_code'], 'HR reorder removed a protected field, accepted a renamed key, or changed stable field identity.');

$financeFields = [
    ['key' => 'account_code', 'label' => 'Account code'],
    ['key' => 'account_name', 'label' => 'Account name'],
    ['key' => 'custom_reference', 'label' => 'Custom reference'],
];
$financeReordered = yovel_admin_shared_form_reorder('accounting-finance', 'account', $financeFields, ['custom_reference']);
$financeKeys = array_column($financeReordered, 'key');
shared_form_assert($financeKeys === ['custom_reference', 'account_code', 'account_name'], 'Finance reorder removed protected system fields or changed stable field identity.');

echo "Company admin shared Form Builder checks passed.\n";
