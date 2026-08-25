<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/assets-maintenance/functions.php';

function assets_lifecycle_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assets_lifecycle_fixture(ADOConnection $db, string $suffix, string $label): array
{
    $companyKey = 'assets:lifecycle/' . $suffix . '/opaque';
    $companyKeyHash = hash('sha256', $companyKey);
    $adminKey = bx_uuid();
    assets_lifecycle_assert($db->Execute(
        "INSERT INTO project_company (
            company_key, company_key_hash, company_code, company_slug, company_name, company_status
         ) VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
        [$companyKey, $companyKeyHash, 'ALC' . strtoupper(substr(hash('sha256', $suffix), 0, 12)), 'assets-lifecycle-' . $suffix, $label]
    ) !== false, 'Assets lifecycle company fixture could not be created.');
    assets_lifecycle_assert($db->Execute(
        "INSERT INTO project_company_admin (
            admin_key, company_key, company_key_hash, admin_login, admin_password_hash,
            admin_name, admin_email, admin_status
         ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
        [$adminKey, $companyKey, $companyKeyHash, 'assets_lifecycle_' . $suffix, password_hash('lifecycle-only', PASSWORD_DEFAULT), $label . ' Admin', $suffix . '@example.test']
    ) !== false, 'Assets lifecycle administrator fixture could not be created.');

    return [
        'company' => [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'company_name' => $label,
            'company_slug' => 'assets-lifecycle-' . $suffix,
        ],
        'admin' => ['admin_key' => $adminKey, 'admin_status' => 'ACTIVE'],
    ];
}

$db = bx_db();
$suffix = substr(hash('sha256', bx_uuid()), 0, 14);
$primary = assets_lifecycle_fixture($db, $suffix . 'a', 'Assets Lifecycle Company');
$other = assets_lifecycle_fixture($db, $suffix . 'b', 'Other Assets Lifecycle Company');
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id), 0) FROM builder_audit_log');
$lifecycleTables = [
    'project_company_asset_movement_item',
    'project_company_asset_movement',
    'project_company_asset_activity',
    'project_company_asset',
    'project_company_asset_category_account',
    'project_company_asset_category',
    'project_company_asset_linked_location',
    'project_company_asset_location',
    'project_company_asset_lifecycle_audit',
];
register_shutdown_function(static function () use ($db, $lifecycleTables, $primary, $other, $auditFloor): void {
    unset($GLOBALS['yovel_admin_assets_test_fault']);
    foreach ([$primary, $other] as $fixture) {
        $hash = (string) $fixture['company']['company_key_hash'];
        foreach ($lifecycleTables as $table) {
            if ((int) $db->GetOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]) === 1) {
                $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$hash]);
            }
        }
        $db->Execute('DELETE FROM project_company_admin WHERE admin_key = ? AND company_key_hash = ?', [$fixture['admin']['admin_key'], $hash]);
        $db->Execute('DELETE FROM project_company WHERE company_key = ? AND company_key_hash = ?', [$fixture['company']['company_key'], $hash]);
    }
    $db->Execute("DELETE FROM builder_audit_log WHERE x_id > ? AND module LIKE 'project_company_asset%'", [$auditFloor]);
});

assets_lifecycle_assert(function_exists('yovel_admin_assets_save_asset'), 'Assets lifecycle service is missing.');
foreach ([
    'yovel_admin_assets_submit_asset',
    'yovel_admin_assets_cancel_asset',
    'yovel_admin_assets_save_movement',
    'yovel_admin_assets_submit_movement',
    'yovel_admin_assets_cancel_movement',
] as $function) {
    assets_lifecycle_assert(function_exists($function), 'Assets lifecycle function is missing: ' . $function . '.');
}

yovel_admin_assets_maintenance_schema();
yovel_admin_assets_maintenance_schema();
foreach ($lifecycleTables as $table) {
    assets_lifecycle_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$table]
    ) === 1, 'Assets lifecycle schema is missing ' . $table . '.');
}

$dependencyCalls = ['inventory' => 0, 'hr' => 0, 'finance' => 0, 'finance_posting' => 0];
$itemKey = 'inventory:item/' . $suffix;
$employeeOneKey = 'hr:employee/' . $suffix . '/one';
$employeeTwoKey = 'hr:employee/' . $suffix . '/two';
$services = [
    'inventory.item.read' => static function (array $company, string $requestedKey) use (&$dependencyCalls, $itemKey): ?array {
        $dependencyCalls['inventory']++;
        if ($requestedKey !== $itemKey) {
            return null;
        }
        return [
            'company_key_hash' => (string) $company['company_key_hash'],
            'item_key' => $itemKey,
            'item_code' => 'FIXED-' . substr(hash('sha256', $itemKey), 0, 8),
            'item_name' => 'Fixed asset item',
            'item_status' => 'ACTIVE',
            'item_kind' => 'STOCK',
        ];
    },
    'hr.employee.read' => static function (array $company, string $requestedKey) use (&$dependencyCalls, $employeeOneKey, $employeeTwoKey): ?array {
        $dependencyCalls['hr']++;
        if (!in_array($requestedKey, [$employeeOneKey, $employeeTwoKey], true)) {
            return null;
        }
        return [
            'company_key_hash' => (string) $company['company_key_hash'],
            'employee_key' => $requestedKey,
            'employee_code' => str_ends_with($requestedKey, '/one') ? 'EMP-ONE' : 'EMP-TWO',
            'employee_name' => str_ends_with($requestedKey, '/one') ? 'Custodian One' : 'Custodian Two',
            'employee_status' => 'ACTIVE',
        ];
    },
    'finance.account.read' => static function (array $company, string $requestedKey) use (&$dependencyCalls): array {
        $dependencyCalls['finance']++;
        return [
            'ok' => true,
            'company_key_hash' => (string) $company['company_key_hash'],
            'record' => [
                'account_key' => $requestedKey,
                'account_name' => str_contains($requestedKey, 'accumulated') ? 'Accumulated depreciation' : 'Fixed assets',
                'root_type' => 'ASSET',
                'account_status' => 'ACTIVE',
                'is_group' => false,
                'freeze_account' => false,
            ],
            'errors' => [],
        ];
    },
    'finance.asset-posting.readiness' => static function (array $company) use (&$dependencyCalls): array {
        $dependencyCalls['finance_posting']++;
        return [
            'contract' => 'accounting-finance.asset-posting.v1',
            'owner' => 'accounting-finance',
            'owner_function' => 'yovel_admin_finance_asset_posting_request',
            'transaction_owner' => 'CALLER',
            'operations' => ['DRAFT', 'POST', 'REVERSE'],
        ];
    },
];

try {
    yovel_admin_assets_dependency_services(['foreign.table.read' => static fn (): array => []]);
    throw new RuntimeException('Assets accepted a dependency outside its allow-list.');
} catch (InvalidArgumentException $error) {
    assets_lifecycle_assert(str_contains($error->getMessage(), 'allow-listed'), 'Assets dependency rejection is not explicit.');
}

$rootLocation = yovel_admin_assets_save_location($primary['company'], $primary['admin'], [
    'location_code' => 'HQ-' . strtoupper(substr($suffix, 0, 6)),
    'location_name' => 'Head Office',
    'location_status' => 'ACTIVE',
]);
$storeLocation = yovel_admin_assets_save_location($primary['company'], $primary['admin'], [
    'location_code' => 'STORE-' . strtoupper(substr($suffix, 0, 6)),
    'location_name' => 'Main Store',
    'parent_location_key' => (string) $rootLocation['location_key'],
    'linked_location_keys' => [(string) $rootLocation['location_key']],
    'location_status' => 'ACTIVE',
]);
$serviceLocation = yovel_admin_assets_save_location($primary['company'], $primary['admin'], [
    'location_code' => 'SERVICE-' . strtoupper(substr($suffix, 0, 6)),
    'location_name' => 'Service Bay',
    'parent_location_key' => (string) $rootLocation['location_key'],
    'location_status' => 'ACTIVE',
]);
assets_lifecycle_assert((string) $storeLocation['parent_location_key'] === (string) $rootLocation['location_key'], 'Location parent was not read back.');
assets_lifecycle_assert(count($storeLocation['linked_locations'] ?? []) === 1, 'Linked Location rows were not read back.');
try {
    yovel_admin_assets_save_location($primary['company'], $primary['admin'], array_replace($rootLocation, [
        'parent_location_key' => (string) $storeLocation['location_key'],
    ]));
    throw new RuntimeException('Location cycle was accepted.');
} catch (InvalidArgumentException $error) {
    assets_lifecycle_assert(str_contains(strtolower($error->getMessage()), 'cycle'), 'Location cycle rejection is not explicit.');
}

$category = yovel_admin_assets_save_category($primary['company'], $primary['admin'], [
    'category_code' => 'EQUIP-' . strtoupper(substr($suffix, 0, 6)),
    'category_name' => 'Equipment',
    'category_status' => 'ACTIVE',
    'accounts' => [
        ['account_role' => 'ASSET', 'account_owner_key' => 'finance:account/assets-' . $suffix, 'account_label' => 'Fixed assets'],
        ['account_role' => 'ACCUMULATED_DEPRECIATION', 'account_owner_key' => 'finance:account/accumulated-' . $suffix, 'account_label' => 'Accumulated depreciation'],
    ],
], $services);
assets_lifecycle_assert(count($category['accounts'] ?? []) === 2, 'Asset Category Account rows were not read back.');
assets_lifecycle_assert($dependencyCalls['finance'] === 2, 'Asset Category Accounts did not use the injected Finance owner callable.');

$assetInput = [
    'asset_code' => 'AST-' . strtoupper(substr($suffix, 0, 8)),
    'asset_name' => 'Packaging Machine',
    'item_owner_key' => $itemKey,
    'category_key' => (string) $category['category_key'],
    'location_key' => (string) $storeLocation['location_key'],
    'custodian_employee_key' => $employeeOneKey,
    'acquisition_date' => '2026-08-01',
    'available_for_use_date' => '2026-08-15',
    'purchase_reference_type' => 'PURCHASE_RECEIPT',
    'purchase_reference_key' => 'buying:receipt/' . $suffix,
    'purchase_quantity' => '1.000000',
    'gross_purchase_amount' => '125000.00',
    'lifecycle_status' => 'IN_USE',
    'notes' => 'Initial company asset.',
];
$asset = yovel_admin_assets_save_asset($primary['company'], $primary['admin'], $assetInput, $services);
assets_lifecycle_assert(($asset['document_status'] ?? '') === 'DRAFT', 'New Asset must begin in DRAFT.');
assets_lifecycle_assert((string) $asset['item_owner_key'] === $itemKey && (string) $asset['item_name_snapshot'] === 'Fixed asset item', 'Inventory Item owner reference was not snapshotted.');
assets_lifecycle_assert((string) $asset['custodian_employee_key'] === $employeeOneKey && (string) $asset['custodian_name_snapshot'] === 'Custodian One', 'HR custodian reference was not snapshotted.');
assets_lifecycle_assert($dependencyCalls['inventory'] > 0 && $dependencyCalls['hr'] > 0, 'Assets did not use injected Inventory and HR owner callables.');

$assetInput['asset_key'] = (string) $asset['asset_key'];
$assetInput['notes'] = 'Updated before submission.';
$assetUpdated = yovel_admin_assets_save_asset($primary['company'], $primary['admin'], $assetInput, $services);
assets_lifecycle_assert((string) $assetUpdated['asset_key'] === (string) $asset['asset_key'] && (string) $assetUpdated['notes'] === 'Updated before submission.', 'Draft Asset upsert did not preserve its stable key and values.');
try {
    yovel_admin_assets_save_asset($other['company'], $other['admin'], array_replace($assetInput, [
        'asset_key' => '',
        'asset_code' => 'FOREIGN-' . strtoupper(substr($suffix, 0, 8)),
    ]), $services);
    throw new RuntimeException('Asset accepted foreign company masters.');
} catch (InvalidArgumentException $error) {
    assets_lifecycle_assert(str_contains(strtolower($error->getMessage()), 'category'), 'Foreign-company Asset rejection did not stop at its local master boundary.');
}
try {
    yovel_admin_assets_save_asset($primary['company'], $primary['admin'], array_replace($assetInput, ['asset_key' => '', 'asset_name' => 'Duplicate code']), $services);
    throw new RuntimeException('Duplicate Asset code was accepted.');
} catch (InvalidArgumentException $error) {
    assets_lifecycle_assert(str_contains(strtolower($error->getMessage()), 'code'), 'Duplicate Asset code rejection is not explicit.');
}

$submitted = yovel_admin_assets_submit_asset($primary['company'], $primary['admin'], (string) $asset['asset_key'], $services);
assets_lifecycle_assert(($submitted['document_status'] ?? '') === 'SUBMITTED' && !empty($submitted['submitted_at']), 'Asset submit did not persist an immutable submitted state.');
assets_lifecycle_assert($dependencyCalls['finance_posting'] === 0, 'Ordinary Asset submission must not require Finance posting readiness.');
try {
    yovel_admin_assets_save_asset($primary['company'], $primary['admin'], $assetInput + ['notes' => 'Illegal submitted edit'], $services);
    throw new RuntimeException('Submitted Asset was edited.');
} catch (InvalidArgumentException $error) {
    assets_lifecycle_assert(str_contains(strtolower($error->getMessage()), 'submitted'), 'Submitted Asset edit rejection is not explicit.');
}
assets_lifecycle_assert(yovel_admin_assets_asset($other['company'], (string) $asset['asset_key']) === null, 'Asset read crossed company scope.');

$disposalAsset = yovel_admin_assets_save_asset($primary['company'], $primary['admin'], array_replace($assetInput, [
    'asset_key' => '',
    'asset_code' => 'SOLD-' . strtoupper(substr($suffix, 0, 8)),
    'asset_name' => 'Sold packaging machine',
    'custodian_employee_key' => '',
    'lifecycle_status' => 'SOLD',
]), $services);
$unreadyServices = $services;
$unreadyServices['finance.asset-posting.readiness'] = static fn (array $company): array => [
    'contract' => 'accounting-finance.asset-posting.v0',
    'transaction_owner' => 'OWNER',
    'operations' => ['POST'],
];
try {
    yovel_admin_assets_submit_asset($primary['company'], $primary['admin'], (string) $disposalAsset['asset_key'], $unreadyServices);
    throw new RuntimeException('Sold Asset submitted without the accepted Finance posting contract.');
} catch (RuntimeException $error) {
    assets_lifecycle_assert(str_contains($error->getMessage(), 'Finance Asset posting readiness'), 'Finance posting readiness failure is not explicit.');
}
$disposalSubmitted = yovel_admin_assets_submit_asset($primary['company'], $primary['admin'], (string) $disposalAsset['asset_key'], $services);
assets_lifecycle_assert(($disposalSubmitted['document_status'] ?? '') === 'SUBMITTED', 'Sold Asset did not submit after Finance posting readiness was verified.');
assets_lifecycle_assert($dependencyCalls['finance_posting'] === 1, 'Sold Asset submission did not verify Finance posting readiness exactly once.');

$movement = yovel_admin_assets_save_movement($primary['company'], $primary['admin'], [
    'movement_code' => 'MOVE-' . strtoupper(substr($suffix, 0, 8)),
    'movement_type' => 'TRANSFER',
    'posting_at' => '2026-08-20 09:30:00',
    'to_location_key' => (string) $serviceLocation['location_key'],
    'to_custodian_employee_key' => $employeeTwoKey,
    'reason' => 'Scheduled service transfer.',
    'asset_keys' => [(string) $asset['asset_key']],
], $services);
assets_lifecycle_assert(($movement['document_status'] ?? '') === 'DRAFT' && count($movement['items'] ?? []) === 1, 'Asset Movement draft and child rows were not read back.');
$GLOBALS['yovel_admin_assets_test_fault'] = 'movement_after_asset_update';
try {
    yovel_admin_assets_submit_movement($primary['company'], $primary['admin'], (string) $movement['movement_key'], $services);
    throw new RuntimeException('Injected Asset Movement rollback did not execute.');
} catch (RuntimeException $error) {
    assets_lifecycle_assert(str_contains($error->getMessage(), 'forced transaction failure'), 'Injected Asset Movement rollback returned an unexpected error.');
}
unset($GLOBALS['yovel_admin_assets_test_fault']);
$rolledBackMovement = yovel_admin_assets_movement($primary['company'], (string) $movement['movement_key']);
$rolledBackAsset = yovel_admin_assets_asset($primary['company'], (string) $asset['asset_key']);
assets_lifecycle_assert(($rolledBackMovement['document_status'] ?? '') === 'DRAFT', 'Movement rollback changed the document state.');
assets_lifecycle_assert((string) ($rolledBackAsset['location_key'] ?? '') === (string) $storeLocation['location_key'], 'Movement rollback left a partial custody update.');
$movementSubmitted = yovel_admin_assets_submit_movement($primary['company'], $primary['admin'], (string) $movement['movement_key'], $services);
$movedAsset = yovel_admin_assets_asset($primary['company'], (string) $asset['asset_key']);
assets_lifecycle_assert(($movementSubmitted['document_status'] ?? '') === 'SUBMITTED', 'Asset Movement did not submit.');
assets_lifecycle_assert((string) ($movedAsset['location_key'] ?? '') === (string) $serviceLocation['location_key'], 'Submitted movement did not update Asset location.');
assets_lifecycle_assert((string) ($movedAsset['custodian_employee_key'] ?? '') === $employeeTwoKey, 'Submitted movement did not update Asset custody.');

try {
    yovel_admin_assets_save_movement($primary['company'], $primary['admin'], [
        'movement_code' => 'OLD-' . strtoupper(substr($suffix, 0, 8)),
        'movement_type' => 'TRANSFER',
        'posting_at' => '2026-08-19 09:30:00',
        'to_location_key' => (string) $storeLocation['location_key'],
        'reason' => 'Backdated transfer must be rejected.',
        'asset_keys' => [(string) $asset['asset_key']],
    ], $services);
    throw new RuntimeException('Backdated Asset Movement was accepted.');
} catch (InvalidArgumentException $error) {
    assets_lifecycle_assert(str_contains(strtolower($error->getMessage()), 'chronological'), 'Movement chronology rejection is not explicit.');
}

$movementCancelled = yovel_admin_assets_cancel_movement($primary['company'], $primary['admin'], (string) $movement['movement_key'], 'Transfer reversed.', $services);
$restoredAsset = yovel_admin_assets_asset($primary['company'], (string) $asset['asset_key']);
assets_lifecycle_assert(($movementCancelled['document_status'] ?? '') === 'CANCELLED', 'Asset Movement cancellation did not persist.');
assets_lifecycle_assert((string) ($restoredAsset['location_key'] ?? '') === (string) $storeLocation['location_key'], 'Movement cancellation did not restore Asset location.');
assets_lifecycle_assert((string) ($restoredAsset['custodian_employee_key'] ?? '') === $employeeOneKey, 'Movement cancellation did not restore Asset custody.');

$cancelled = yovel_admin_assets_cancel_asset($primary['company'], $primary['admin'], (string) $asset['asset_key'], 'Incorrect acquisition reference.', $services);
assets_lifecycle_assert(($cancelled['document_status'] ?? '') === 'CANCELLED' && (string) $cancelled['cancellation_reason'] === 'Incorrect acquisition reference.', 'Asset cancellation was not read back.');
$amendInput = $assetInput;
unset($amendInput['asset_key']);
$amendInput['asset_code'] .= '-A1';
$amendInput['amended_from_asset_key'] = (string) $asset['asset_key'];
$amendInput['purchase_reference_key'] = 'buying:receipt/' . $suffix . '/corrected';
$amended = yovel_admin_assets_save_asset($primary['company'], $primary['admin'], $amendInput, $services);
$cancelledSource = yovel_admin_assets_asset($primary['company'], (string) $asset['asset_key']);
assets_lifecycle_assert((string) $amended['amended_from_asset_key'] === (string) $asset['asset_key'], 'Amended Asset did not retain its source reference.');
assets_lifecycle_assert((string) ($cancelledSource['amended_by_asset_key'] ?? '') === (string) $amended['asset_key'], 'Cancelled source Asset did not retain its amendment reference.');

$beforeRollback = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_asset WHERE company_key_hash = ?', [$primary['company']['company_key_hash']]);
$GLOBALS['yovel_admin_assets_test_fault'] = 'asset_after_write';
try {
    yovel_admin_assets_save_asset($primary['company'], $primary['admin'], array_replace($amendInput, [
        'asset_key' => '',
        'asset_code' => $amendInput['asset_code'] . '-ROLLBACK',
        'amended_from_asset_key' => '',
    ]), $services);
    throw new RuntimeException('Injected Asset rollback did not execute.');
} catch (RuntimeException $error) {
    assets_lifecycle_assert(str_contains($error->getMessage(), 'forced transaction failure'), 'Injected Asset rollback returned an unexpected error.');
}
unset($GLOBALS['yovel_admin_assets_test_fault']);
assets_lifecycle_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_asset WHERE company_key_hash = ?', [$primary['company']['company_key_hash']]) === $beforeRollback, 'Asset rollback left a partial row.');

$activities = yovel_admin_assets_activities($primary['company'], (string) $asset['asset_key']);
assets_lifecycle_assert(count($activities) >= 6, 'Asset Activity timeline is incomplete.');
foreach (['CREATE', 'UPDATE', 'SUBMIT', 'MOVE', 'MOVEMENT_CANCEL', 'CANCEL', 'AMEND'] as $expectedAction) {
    assets_lifecycle_assert(in_array($expectedAction, array_column($activities, 'activity_type'), true), 'Asset Activity is missing ' . $expectedAction . '.');
}
assets_lifecycle_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_asset_lifecycle_audit WHERE company_key_hash = ?', [$primary['company']['company_key_hash']]) >= 10, 'Assets lifecycle audit coverage is incomplete.');
assets_lifecycle_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE x_id > ? AND module LIKE 'project_company_asset%'", [$auditFloor]) >= 10, 'Builder audit evidence is incomplete.');

$data = yovel_admin_assets_maintenance_data($primary['company'], $primary['admin'], 'asset-records');
assets_lifecycle_assert(($data['state']['kind'] ?? '') === 'ready', 'Asset records section did not become ready.');
assets_lifecycle_assert(count($data['assets'] ?? []) === 3, 'Asset records data did not rehydrate company Assets.');
assets_lifecycle_assert(count($data['locations'] ?? []) === 3 && count($data['categories'] ?? []) === 1, 'Asset master data did not rehydrate.');

$activeModuleSection = 'asset-records';
$activeModuleData = $data;
$activeModuleFormState = [
    'section' => 'asset-records',
    'action' => 'save_asset',
    'input' => array_replace($assetInput, ['asset_code' => 'REHYDRATED-' . $suffix, 'asset_name' => 'Retained Asset']),
    'error' => 'Controller-retained lifecycle validation error.',
];
$companyName = $primary['company']['company_name'];
ob_start();
require $root . '/company/admin/modules/assets-maintenance/views/workspace.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();
foreach (['data-assets-record-list', 'data-assets-activity', 'data-assets-lifecycle-modal', 'data-assets-movement-modal', 'data-assets-location-modal', 'data-assets-category-modal'] as $marker) {
    assets_lifecycle_assert(str_contains($markup, $marker), 'Assets lifecycle UI is missing ' . $marker . '.');
}
assets_lifecycle_assert(str_contains($markup, 'REHYDRATED-' . $suffix) && str_contains($markup, 'Controller-retained lifecycle validation error.'), 'Asset modal did not rehydrate submitted values and error state.');
assets_lifecycle_assert(str_contains($markup, 'data-record-modal-open-on-load'), 'Failed Asset action did not reopen its owning modal.');
assets_lifecycle_assert(substr_count($markup, 'data-confirm-submit') >= 4, 'Assets lifecycle forms do not all expose confirmation boundaries.');
assets_lifecycle_assert(strpos($markup, 'data-assets-main') < strpos($markup, 'data-assets-tools'), 'Asset records must preserve main-first responsive order.');
assets_lifecycle_assert(str_contains($markup, 'minmax(0, 12fr) minmax(16rem, 8fr)'), 'Asset records lost the 12/8 shell.');

$source = (string) file_get_contents($root . '/company/admin/modules/assets-maintenance/assets.php');
foreach (['project_company_inventory_', 'project_company_hr_', 'project_company_finance_', 'project_company_accounting_', 'project_company_buying_', 'project_company_operations_'] as $foreignPrefix) {
    assets_lifecycle_assert(!str_contains($source, $foreignPrefix), 'Assets lifecycle directly references a foreign owner table: ' . $foreignPrefix);
}
assets_lifecycle_assert(!str_contains($source, '$_POST'), 'Assets lifecycle service must not read request globals.');
foreach (['yovel_admin_inventory_item', 'yovel_admin_hr_workforce_read_contract', 'yovel_admin_finance_account_reference', 'yovel_admin_finance_asset_posting_contract', 'yovel_admin_finance_asset_posting_request'] as $ownerCallable) {
    assets_lifecycle_assert(str_contains($source, $ownerCallable), 'Assets lifecycle is missing verified owner callable: ' . $ownerCallable . '.');
}

echo "Assets / Maintenance Task 2 lifecycle checks passed.\n";
