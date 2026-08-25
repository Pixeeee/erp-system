<?php
declare(strict_types=1);

require_once __DIR__ . '/operations-test-helper.php';

$company = operations_test_company('Operations Fleet Company');
$otherCompany = operations_test_company('Other Operations Fleet Company');
$admin = operations_test_admin();
$inactiveAdmin = [...$admin, 'admin_key' => bx_uuid(), 'admin_status' => 'INACTIVE'];
operations_test_register_cleanup($company);
operations_test_register_cleanup($otherCompany);
yovel_admin_operations_schema();

$keys = [
    'employee' => bx_uuid(),
    'supplier' => bx_uuid(),
    'address' => bx_uuid(),
    'unit' => bx_uuid(),
    'category' => bx_uuid(),
    'expired_driver' => bx_uuid(),
    'valid_driver' => bx_uuid(),
    'vehicle' => bx_uuid(),
];
$assetRecords = [[
    'record_type' => 'DRIVING_LICENSE_CATEGORY',
    'license_category_key' => $keys['category'],
    'license_category_code' => 'C',
    'license_category_name' => 'Commercial vehicle',
    'license_category_status' => 'ACTIVE',
    'api_token' => 'must-not-leak',
], [
    'record_type' => 'DRIVER',
    'driver_key' => $keys['expired_driver'],
    'driver_code' => 'DRV-001',
    'driver_name' => 'Expired Driver',
    'driver_status' => 'ACTIVE',
    'employee_key' => $keys['employee'],
    'supplier_key' => '',
    'address_key' => $keys['address'],
    'license_category_key' => $keys['category'],
    'license_number' => 'LIC-PRIVATE-001',
    'license_issued_date' => '2022-08-25',
    'license_expiry_date' => '2026-08-24',
    'license_status' => 'EXPIRED',
], [
    'record_type' => 'DRIVER',
    'driver_key' => $keys['valid_driver'],
    'driver_code' => 'DRV-002',
    'driver_name' => 'Supplier Driver',
    'driver_status' => 'ACTIVE',
    'employee_key' => '',
    'supplier_key' => $keys['supplier'],
    'address_key' => '',
    'license_category_key' => $keys['category'],
    'license_number' => 'LIC-PRIVATE-002',
    'license_issued_date' => '2025-01-01',
    'license_expiry_date' => '2027-01-01',
    'license_status' => 'ACTIVE',
], [
    'record_type' => 'VEHICLE',
    'vehicle_key' => $keys['vehicle'],
    'vehicle_code' => 'VEH-001',
    'vehicle_name' => 'Delivery Van',
    'vehicle_status' => 'ACTIVE',
    'company_key' => $company['company_key'],
    'unit_key' => $keys['unit'],
    'fuel_type' => 'DIESEL',
    'driver_key' => $keys['expired_driver'],
    'supplier_key' => $keys['supplier'],
    'password' => 'must-not-leak',
]];
$supportingRecords = [
    'hr.workforce-directory.v1' => [[
        'employee_key' => $keys['employee'], 'employee_code' => 'EMP-001',
        'employee_name' => 'Expired Driver', 'employee_status' => 'ACTIVE',
    ]],
    'buying-procurement.supplier-directory.v1' => [[
        'supplier_key' => $keys['supplier'], 'supplier_code' => 'SUP-001',
        'supplier_name' => 'Fleet Supplier', 'supplier_status' => 'ACTIVE',
    ]],
    'shared.identity-address-directory.v1' => [[
        'address_key' => $keys['address'], 'address_label' => 'Depot', 'address_status' => 'ACTIVE',
    ]],
    'inventory-warehouse.unit-directory.v1' => [[
        'unit_key' => $keys['unit'], 'unit_code' => 'KM', 'unit_name' => 'Kilometre', 'unit_status' => 'ACTIVE',
    ]],
    'platform.company-scope.v1' => [[
        'scope_key' => 'company-scope-' . substr($company['company_key_hash'], 0, 12),
        'company_key' => $company['company_key'], 'company_status' => 'ACTIVE',
    ]],
];
$calls = array_fill_keys([
    'assets-maintenance.fleet-directory.v1',
    'hr.workforce-directory.v1',
    'buying-procurement.supplier-directory.v1',
    'shared.identity-address-directory.v1',
    'inventory-warehouse.unit-directory.v1',
    'platform.company-scope.v1',
], 0);

$providers = [];
foreach (array_keys($calls) as $contractId) {
    $providers[$contractId] = static function (array $scope, array $query) use (
        $contractId, $company, &$assetRecords, &$supportingRecords, &$calls
    ): array {
        $calls[$contractId]++;
        operations_test_assert(($scope['company_key_hash'] ?? '') === $company['company_key_hash'], $contractId . ' received the wrong company scope.');
        operations_test_assert(($query['projection'] ?? '') === 'operations-fleet', $contractId . ' received an unexpected query contract.');
        return [
            'ok' => true,
            'company_key_hash' => $company['company_key_hash'],
            'records' => $contractId === 'assets-maintenance.fleet-directory.v1'
                ? $assetRecords
                : ($supportingRecords[$contractId] ?? []),
            'errors' => [],
        ];
    };
}

$projection = yovel_admin_operations_fleet_projection($company, $providers, '2026-08-25');
operations_test_assert(($projection['contract'] ?? '') === 'operations.fleet-delivery.v1', 'Fleet projection contract identity changed.');
operations_test_assert(($projection['company_key_hash'] ?? '') === $company['company_key_hash'], 'Fleet projection changed company scope.');
operations_test_assert(array_sum($calls) === 6 && count(array_filter($calls, static fn (int $count): bool => $count === 1)) === 6, 'Fleet projection did not call each owner provider exactly once.');

$directories = $projection['directories'] ?? [];
operations_test_assert(array_keys($directories) === ['driver', 'driving_license_category', 'vehicle'], 'Fleet projection does not trace all three OP-06 rows.');
foreach ($directories as $directory) {
    operations_test_assert(($directory['status'] ?? '') === 'AVAILABLE', 'A valid fleet directory was not available.');
    operations_test_assert(($directory['blocking'] ?? true) === false, 'Fleet owner projection incorrectly blocks Operations.');
}
operations_test_assert(($directories['driver']['records'][0] ?? null) === [
    'driver_key' => $keys['expired_driver'],
    'driver_code' => 'DRV-001',
    'driver_name' => 'Expired Driver',
    'driver_status' => 'ACTIVE',
    'employee_key' => $keys['employee'],
    'supplier_key' => '',
    'address_key' => $keys['address'],
    'license_category_key' => $keys['category'],
    'license_issued_date' => '2022-08-25',
    'license_expiry_date' => '2026-08-24',
    'license_status' => 'EXPIRED',
], 'Driver stable references, license dates, or status changed.');
operations_test_assert(($directories['vehicle']['records'][0] ?? null) === [
    'vehicle_key' => $keys['vehicle'],
    'vehicle_code' => 'VEH-001',
    'vehicle_name' => 'Delivery Van',
    'vehicle_status' => 'ACTIVE',
    'company_key' => $company['company_key'],
    'unit_key' => $keys['unit'],
    'unit_code' => 'KM',
    'fuel_type' => 'DIESEL',
    'driver_key' => $keys['expired_driver'],
    'supplier_key' => $keys['supplier'],
], 'Vehicle company, unit, fuel, or owner references changed.');
operations_test_assert(count($projection['expired_license_candidates'] ?? []) === 1, 'Expired license candidate count is incorrect.');
operations_test_assert(($projection['expired_license_candidates'][0]['driver_key'] ?? '') === $keys['expired_driver'], 'Expired license alert lost its stable Driver key.');
$encodedProjection = json_encode($projection, JSON_THROW_ON_ERROR);
operations_test_assert(!str_contains($encodedProjection, 'LIC-PRIVATE') && !str_contains($encodedProjection, 'must-not-leak'), 'Fleet projection exposed raw license or secret values.');

$mismatchProviders = $providers;
$mismatchProviders['assets-maintenance.fleet-directory.v1'] = static fn (array $scope, array $query): array => [
    'ok' => true, 'company_key_hash' => str_repeat('0', 64), 'records' => $assetRecords, 'errors' => [],
];
$mismatch = yovel_admin_operations_fleet_projection($company, $mismatchProviders, '2026-08-25');
operations_test_assert(($mismatch['sources']['assets-maintenance.fleet-directory.v1']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Cross-company Fleet owner response was accepted.');
foreach (($mismatch['directories'] ?? []) as $directory) {
    operations_test_assert(($directory['records'] ?? null) === [], 'Cross-company Fleet owner records leaked.');
}

$missingUnitProviders = $providers;
unset($missingUnitProviders['inventory-warehouse.unit-directory.v1']);
$missingUnit = yovel_admin_operations_fleet_projection($company, $missingUnitProviders, '2026-08-25');
operations_test_assert(($missingUnit['directories']['vehicle']['status'] ?? '') === 'PARTIAL', 'Missing Unit provider became empty Vehicle success.');
operations_test_assert(($missingUnit['directories']['vehicle']['records'] ?? null) === [], 'Vehicle with an unverified Unit reference was projected.');
operations_test_assert(($missingUnit['directories']['driver']['status'] ?? '') === 'AVAILABLE', 'Unit failure suppressed unrelated Driver records.');
operations_test_assert(($missingUnit['stale_owner_records'][0]['reason'] ?? '') === 'unit_reference_unavailable', 'Missing Unit dependency is not explicit.');

$duplicateProviders = $providers;
$duplicateProviders['assets-maintenance.fleet-directory.v1'] = static function (array $scope, array $query) use ($company, $assetRecords): array {
    $records = $assetRecords;
    $records[] = $records[1];
    return ['ok' => true, 'company_key_hash' => $company['company_key_hash'], 'records' => $records, 'errors' => []];
};
$duplicate = yovel_admin_operations_fleet_projection($company, $duplicateProviders, '2026-08-25');
operations_test_assert(($duplicate['directories']['driver']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Duplicate Driver owner key was accepted.');
operations_test_assert(($duplicate['directories']['driver']['records'] ?? null) === [], 'Malformed Driver response leaked partial records.');

$auditFloor = (int) bx_db()->GetOne('SELECT COALESCE(MAX(x_id),0) FROM builder_audit_log');
$queued = yovel_admin_operations_queue_fleet_alert_scan(bx_db(), $company, $admin, ['as_of_date' => '2026-08-25'], $providers);
$duplicateQueue = yovel_admin_operations_queue_fleet_alert_scan(bx_db(), $company, $admin, ['as_of_date' => '2026-08-25'], $providers);
operations_test_assert(($queued['job_key'] ?? '') === ($duplicateQueue['job_key'] ?? ''), 'Fleet scan enqueue is not idempotent.');
operations_test_assert(($queued['source_section'] ?? '') === 'fleet-delivery' && ($queued['status'] ?? '') === 'QUEUED', 'Fleet alert scan was not queued through the Operations runner.');
operations_test_assert((int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_operations_job WHERE company_key_hash=? AND source_section=?', [$company['company_key_hash'], 'fleet-delivery']) === 1, 'Fleet scan enqueue created duplicate jobs.');
operations_test_expect_exception(
    static fn () => yovel_admin_operations_queue_fleet_alert_scan(bx_db(), $company, $inactiveAdmin, ['as_of_date' => '2026-08-25'], $providers),
    'authorized'
);
operations_test_expect_exception(
    static fn () => yovel_admin_operations_queue_fleet_alert_scan(bx_db(), $company, $admin, ['as_of_date' => '2026-08-25', 'api_token' => 'raw-secret'], $providers),
    'secret'
);
operations_test_expect_exception(
    static fn () => yovel_admin_operations_queue_fleet_alert_scan(bx_db(), $company, $admin, ['as_of_date' => '2026-08-25', 'unexpected' => 'value'], $providers),
    'field'
);

$claimed = yovel_admin_operations_claim_job(bx_db(), $company, 'fleet-worker', 60);
operations_test_assert(($claimed['job_key'] ?? '') === ($queued['job_key'] ?? ''), 'Fleet worker did not claim the queued scan.');
$processed = yovel_admin_operations_run_fleet_alert_scan(bx_db(), $company, $admin, (string) $queued['job_key'], 'fleet-worker', $providers);
operations_test_assert(($processed['job']['status'] ?? '') === 'SUCCEEDED', 'Fleet alert worker did not complete the scan job.');
operations_test_assert(count($processed['alerts'] ?? []) === 1, 'Fleet alert worker did not persist exactly one alert.');
$activeAlert = $processed['alerts'][0];
operations_test_assert(($activeAlert['status'] ?? '') === 'ACTIVE' && ($activeAlert['severity'] ?? '') === 'CRITICAL', 'Expired Driver alert is not active and critical.');
operations_test_assert(str_starts_with((string) ($activeAlert['alert_code'] ?? ''), 'FLEET.LICENSE.EXPIRED.'), 'Fleet alert code is not deterministic and namespaced.');
operations_test_assert(!str_contains(json_encode($activeAlert, JSON_THROW_ON_ERROR), 'LIC-PRIVATE'), 'Fleet alert persisted a raw license number.');
operations_test_assert(yovel_admin_operations_system_alerts($otherCompany) === [], 'Fleet alert leaked across company scope.');
operations_test_assert((int) bx_db()->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE x_id>? AND module='project_company_operations_system_alert'", [$auditFloor]) === 1, 'Fleet alert create was not audited exactly once.');

$assetRecords = array_values(array_filter($assetRecords, static fn (array $record): bool => ($record['driver_key'] ?? '') !== $keys['expired_driver']));
$staleQueued = yovel_admin_operations_queue_fleet_alert_scan(bx_db(), $company, $admin, ['as_of_date' => '2026-08-26'], $providers);
$staleClaim = yovel_admin_operations_claim_job(bx_db(), $company, 'fleet-worker', 60);
operations_test_assert(($staleClaim['job_key'] ?? '') === ($staleQueued['job_key'] ?? ''), 'Stale Fleet scan job was not claimed.');
$staleProcessed = yovel_admin_operations_run_fleet_alert_scan(bx_db(), $company, $admin, (string) $staleQueued['job_key'], 'fleet-worker', $providers);
operations_test_assert(($staleProcessed['alerts'][0]['status'] ?? '') === 'RESOLVED', 'Deleted or inaccessible Driver did not resolve its Operations alert.');

$assetRecords[] = [
    'record_type' => 'DRIVER', 'driver_key' => $keys['expired_driver'], 'driver_code' => 'DRV-001',
    'driver_name' => 'Expired Driver', 'driver_status' => 'ACTIVE', 'employee_key' => $keys['employee'],
    'supplier_key' => '', 'address_key' => $keys['address'], 'license_category_key' => $keys['category'],
    'license_number' => 'LIC-PRIVATE-001', 'license_issued_date' => '2022-08-25',
    'license_expiry_date' => '2026-08-24', 'license_status' => 'EXPIRED',
];
$rollbackQueued = yovel_admin_operations_queue_fleet_alert_scan(bx_db(), $company, $admin, ['as_of_date' => '2026-08-27'], $providers);
$rollbackClaim = yovel_admin_operations_claim_job(bx_db(), $company, 'fleet-worker', 60);
operations_test_assert(($rollbackClaim['job_key'] ?? '') === ($rollbackQueued['job_key'] ?? ''), 'Rollback Fleet scan job was not claimed.');
$beforeRollback = yovel_admin_operations_system_alerts($company);
operations_test_expect_exception(
    static fn () => yovel_admin_operations_run_fleet_alert_scan(
        bx_db(), $company, $admin, (string) $rollbackQueued['job_key'], 'fleet-worker', $providers,
        static fn (): never => throw new RuntimeException('Injected Fleet alert rollback')
    ),
    'rollback'
);
$afterRollback = yovel_admin_operations_system_alerts($company);
operations_test_assert($afterRollback === $beforeRollback, 'Fleet alert mutation survived a transaction rollback.');

$GLOBALS['yovel_admin_operations_dependency_providers'] = $providers;
$postResult = yovel_admin_operations_handle_post($company, $admin, 'sync_operations_fleet_alerts', [
    'module_view' => 'operations', 'section' => 'fleet-delivery', 'as_of_date' => '2026-08-28',
]);
unset($GLOBALS['yovel_admin_operations_dependency_providers']);
operations_test_assert(($postResult['message'] ?? '') === 'Fleet alert scan queued.', 'Fleet POST result message changed.');
operations_test_assert(($postResult['section'] ?? '') === 'fleet-delivery', 'Fleet POST did not return to its section.');
operations_test_assert(yovel_admin_is_uuid((string) ($postResult['query']['job'] ?? '')), 'Fleet POST did not return its queued job key.');

$sections = yovel_admin_operations_sections();
operations_test_assert(isset($sections['fleet-delivery']), 'Fleet and Delivery workspace section is not registered.');
operations_test_assert(count(yovel_admin_operations_builder_targets()) === 17, 'Universal Operations Form Builder does not include Fleet and Delivery.');
$workspaceSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/workspace.php');
$viewSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/sections/fleet.php');
operations_test_assert(str_contains($workspaceSource, "'fleet-delivery' => 'fleet.php'"), 'Workspace does not dispatch Fleet and Delivery.');
operations_test_assert(str_contains($workspaceSource, 'minmax(0,12fr)') && str_contains($workspaceSource, 'minmax(16rem,8fr)'), 'Fleet and Delivery did not retain the 12/8 shell.');
foreach (['name="csrf"', 'name="module_view" value="operations"', 'name="action" value="sync_operations_fleet_alerts"', 'data-record-modal'] as $marker) {
    operations_test_assert(str_contains($viewSource, $marker), 'Fleet alert modal contract is missing marker: ' . $marker);
}
operations_test_assert(str_contains($viewSource, 'confirm_message'), 'Fleet alert command has no separate confirmation copy.');
operations_test_assert(!str_contains($viewSource, 'license_number'), 'Fleet UI exposes a raw license number.');
operations_test_assert(!str_contains($viewSource, 'rounded-md border'), 'Fleet view nests bordered cards inside the workspace panel.');

$operationFiles = array_merge(
    glob(dirname(__DIR__) . '/company/admin/modules/operations/*.php') ?: [],
    glob(dirname(__DIR__) . '/company/admin/modules/operations/views/sections/*.php') ?: []
);
foreach ($operationFiles as $operationFile) {
    $source = (string) file_get_contents($operationFile);
    foreach (['project_company_assets_', 'project_company_asset_', 'project_company_hr_', 'project_company_buying_', 'project_company_inventory_'] as $foreignPrefix) {
        operations_test_assert(!str_contains($source, $foreignPrefix), basename($operationFile) . ' directly queries a foreign owner table.');
    }
}

echo "Operations fleet and delivery tests passed\n";
