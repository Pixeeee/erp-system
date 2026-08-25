<?php
declare(strict_types=1);

require_once __DIR__ . '/operations-test-helper.php';

$mode = (string) ($argv[1] ?? '');
if ($mode === 'missing-owner-services') {
    echo json_encode(yovel_admin_operations_catalog_projection(operations_test_company('Missing Catalog Owners')), JSON_THROW_ON_ERROR);
    exit(0);
}

if (!function_exists('yovel_admin_inventory_items')) {
    function yovel_admin_inventory_items(array $company, array $filters = []): array
    {
        return ($GLOBALS['operations_inventory_items_service'])($company, $filters);
    }
}
if (!function_exists('yovel_admin_inventory_item')) {
    function yovel_admin_inventory_item(array $company, string $itemKey): ?array
    {
        return ($GLOBALS['operations_inventory_item_service'])($company, $itemKey);
    }
}
if (!function_exists('yovel_admin_inventory_warehouses')) {
    function yovel_admin_inventory_warehouses(array $company, array $filters = []): array
    {
        return ($GLOBALS['operations_inventory_warehouse_service'])($company, $filters);
    }
}

$company = operations_test_company('Operations Catalog Company');
$otherCompany = operations_test_company('Other Catalog Company');
$keys = [
    'item' => bx_uuid(),
    'each_uom' => bx_uuid(),
    'box_uom' => bx_uuid(),
    'each_conversion' => bx_uuid(),
    'box_conversion' => bx_uuid(),
    'warehouse_root' => bx_uuid(),
    'warehouse_leaf' => bx_uuid(),
];
$calls = ['items' => 0, 'item' => 0, 'warehouses' => 0];
$scopes = [];

$GLOBALS['operations_inventory_items_service'] = static function (array $scope, array $filters) use (&$calls, &$scopes, $company, $keys): array {
    $calls['items']++;
    $scopes['items'] = (string) ($scope['company_key_hash'] ?? '');
    return [[
        'company_key_hash' => $company['company_key_hash'],
        'item_key' => $keys['item'],
        'item_code' => 'ITEM-001',
        'item_name' => 'Catalog Fixture',
        'item_status' => 'ACTIVE',
        'stock_uom_code' => 'EA',
        'api_token' => 'must-not-leak',
    ]];
};
$GLOBALS['operations_inventory_item_service'] = static function (array $scope, string $itemKey) use (&$calls, &$scopes, $company, $keys): array {
    $calls['item']++;
    $scopes['item'] = (string) ($scope['company_key_hash'] ?? '');
    return [
        'company_key_hash' => $company['company_key_hash'],
        'item_key' => $itemKey,
        'item_code' => 'ITEM-001',
        'item_name' => 'Catalog Fixture',
        'item_status' => 'ACTIVE',
        'stock_uom_code' => 'EA',
        'uoms' => [[
            'item_uom_key' => $keys['each_conversion'],
            'uom_key' => $keys['each_uom'],
            'uom_code' => 'EA',
            'uom_name' => 'Each',
            'category' => 'COUNT',
            'conversion_factor' => '1.000000000',
            'is_stock_uom' => 1,
            'secret' => 'must-not-leak',
        ], [
            'item_uom_key' => $keys['box_conversion'],
            'uom_key' => $keys['box_uom'],
            'uom_code' => 'BOX',
            'uom_name' => 'Box',
            'category' => 'COUNT',
            'conversion_factor' => '12.000000000',
            'is_stock_uom' => 0,
        ]],
        'manufacturers' => [[
            'manufacturer_key' => bx_uuid(),
            'manufacturer_name' => 'Not a Brand directory',
        ]],
    ];
};
$GLOBALS['operations_inventory_warehouse_service'] = static function (array $scope, array $filters) use (&$calls, &$scopes, $company, $keys): array {
    $calls['warehouses']++;
    $scopes['warehouses'] = (string) ($scope['company_key_hash'] ?? '');
    return [[
        'company_key_hash' => $company['company_key_hash'],
        'warehouse_key' => $keys['warehouse_root'],
        'warehouse_code' => 'ROOT',
        'warehouse_name' => 'Root Warehouse',
        'parent_warehouse_key' => null,
        'is_group' => 1,
        'warehouse_status' => 'ACTIVE',
    ], [
        'company_key_hash' => $company['company_key_hash'],
        'warehouse_key' => $keys['warehouse_leaf'],
        'warehouse_code' => 'MAIN',
        'warehouse_name' => 'Main Warehouse',
        'parent_warehouse_key' => $keys['warehouse_root'],
        'is_group' => 0,
        'warehouse_status' => 'ACTIVE',
        'password' => 'must-not-leak',
    ]];
};

$projection = yovel_admin_operations_catalog_projection($company);
operations_test_assert(($projection['company_key_hash'] ?? '') === $company['company_key_hash'], 'Catalog projection changed company scope.');
operations_test_assert($calls === ['items' => 1, 'item' => 1, 'warehouses' => 1], 'Catalog projection did not call each verified Inventory owner service exactly once.');
operations_test_assert(count($scopes) === 3 && count(array_filter($scopes, static fn (string $hash): bool => $hash === $company['company_key_hash'])) === 3, 'Catalog projection did not propagate exact company scope.');

$directories = $projection['directories'] ?? [];
operations_test_assert(array_keys($directories) === ['brand', 'item_group', 'uom', 'uom_conversion_factor'], 'Catalog projection does not trace all four OP-05 rows.');
foreach (['brand', 'item_group'] as $directory) {
    operations_test_assert(($directories[$directory]['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', $directory . ' was presented as empty success.');
    operations_test_assert(($directories[$directory]['records'] ?? null) === [], $directory . ' fabricated owner records.');
    operations_test_assert(($directories[$directory]['unavailable_fields'] ?? []) !== [], $directory . ' does not name exact missing owner fields.');
    operations_test_assert(($directories[$directory]['blocking'] ?? true) === false, $directory . ' incorrectly blocks Operations.');
}
operations_test_assert(($directories['uom']['status'] ?? '') === 'PARTIAL', 'UOM projection did not preserve its exact owner limitation.');
operations_test_assert(($directories['uom']['records'] ?? null) === [[
    'uom_key' => $keys['each_uom'], 'uom_code' => 'EA', 'uom_name' => 'Each', 'uom_category' => 'COUNT',
], [
    'uom_key' => $keys['box_uom'], 'uom_code' => 'BOX', 'uom_name' => 'Box', 'uom_category' => 'COUNT',
]], 'UOM projection changed stable owner fields.');
operations_test_assert(in_array('must_be_whole_number', $directories['uom']['unavailable_fields'] ?? [], true), 'UOM whole-number constraint gap is not explicit.');
operations_test_assert(in_array('uom_status', $directories['uom']['unavailable_fields'] ?? [], true), 'UOM enabled-state gap is not explicit.');

operations_test_assert(($directories['uom_conversion_factor']['status'] ?? '') === 'AVAILABLE', 'UOM conversions were not available.');
operations_test_assert(($directories['uom_conversion_factor']['records'][1] ?? null) === [
    'item_uom_key' => $keys['box_conversion'],
    'item_key' => $keys['item'],
    'uom_key' => $keys['box_uom'],
    'from_uom_code' => 'BOX',
    'to_uom_code' => 'EA',
    'uom_category' => 'COUNT',
    'conversion_factor' => '12.000000000',
    'is_stock_uom' => 0,
], 'UOM conversion direction, category, stable keys, or exact factor changed.');
operations_test_assert(!str_contains(json_encode($projection, JSON_THROW_ON_ERROR), 'must-not-leak'), 'Catalog projection exposed an owner secret.');
operations_test_assert(($projection['contexts']['warehouses'][1]['parent_warehouse_key'] ?? '') === $keys['warehouse_root'], 'Warehouse hierarchy context lost its stable parent key.');
operations_test_assert(($projection['contexts']['warehouses'][0]['is_group'] ?? null) === 1, 'Warehouse group flag changed.');

$GLOBALS['operations_inventory_warehouse_service'] = static fn (array $scope, array $filters): array => [[
    'company_key_hash' => $otherCompany['company_key_hash'],
    'warehouse_key' => bx_uuid(),
    'warehouse_code' => 'LEAK',
    'warehouse_name' => 'Wrong Company',
    'is_group' => 0,
    'warehouse_status' => 'ACTIVE',
]];
$warehouseMismatch = yovel_admin_operations_catalog_projection($company);
operations_test_assert(($warehouseMismatch['sources']['warehouses']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Cross-company warehouse response was accepted.');
operations_test_assert(($warehouseMismatch['contexts']['warehouses'] ?? null) === [], 'Cross-company warehouse records leaked.');
operations_test_assert(($warehouseMismatch['directories']['uom_conversion_factor']['status'] ?? '') === 'AVAILABLE', 'Warehouse failure suppressed valid UOM conversions.');

$GLOBALS['operations_inventory_warehouse_service'] = static fn (array $scope, array $filters): array => [];
$originalItemService = $GLOBALS['operations_inventory_item_service'];
$GLOBALS['operations_inventory_item_service'] = static function (array $scope, string $itemKey) use ($originalItemService): array {
    $item = $originalItemService($scope, $itemKey);
    $item['uoms'][] = $item['uoms'][1];
    $item['uoms'][2]['item_uom_key'] = bx_uuid();
    return $item;
};
$duplicate = yovel_admin_operations_catalog_projection($company);
operations_test_assert(($duplicate['directories']['uom']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Duplicate conversion pair was accepted for UOM projection.');
operations_test_assert(($duplicate['directories']['uom_conversion_factor']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Duplicate conversion pair was accepted.');
operations_test_assert(($duplicate['directories']['uom_conversion_factor']['records'] ?? null) === [], 'Malformed conversion response leaked partial owner records.');

$missingJson = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' missing-owner-services');
operations_test_assert(is_string($missingJson) && $missingJson !== '', 'Missing-owner child check did not return a catalog projection.');
$missing = json_decode($missingJson, true, 512, JSON_THROW_ON_ERROR);
foreach (($missing['sources'] ?? []) as $source) {
    operations_test_assert(($source['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Absent Inventory owner service became empty success.');
}
foreach (($missing['directories'] ?? []) as $directory) {
    operations_test_assert(($directory['records'] ?? null) === [], 'Missing owner service fabricated a catalog record.');
}

$sections = yovel_admin_operations_sections();
operations_test_assert(isset($sections['catalog-units']), 'Catalog and Units workspace section is not registered.');
operations_test_assert(count(yovel_admin_operations_builder_targets()) === 16, 'Universal Operations Form Builder does not include Catalog and Units.');
$workspaceSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/workspace.php');
$viewSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/sections/catalog.php');
operations_test_assert(str_contains($workspaceSource, "'catalog-units' => 'catalog.php'"), 'Workspace does not dispatch Catalog and Units.');
operations_test_assert(str_contains($workspaceSource, 'minmax(0,12fr)') && str_contains($workspaceSource, 'minmax(16rem,8fr)'), 'Catalog and Units did not retain the 12/8 shell.');
operations_test_assert(!preg_match('/<(?:form|button)\b/i', $viewSource), 'Read-only Catalog and Units exposes a local create command.');
operations_test_assert(!str_contains($viewSource, '0 records'), 'Catalog view turns unavailable owner data into a fabricated zero.');
operations_test_assert(str_contains($viewSource, "['owner_actions']"), 'Catalog view is missing its Inventory owner-action contract.');
operations_test_assert(!str_contains($viewSource, 'rounded-md border'), 'Catalog view nests bordered cards inside the workspace panel.');

$operationFiles = glob(dirname(__DIR__) . '/company/admin/modules/operations/*.php') ?: [];
foreach ($operationFiles as $operationFile) {
    $source = (string) file_get_contents($operationFile);
    operations_test_assert(!str_contains($source, 'project_company_inventory_'), basename($operationFile) . ' directly queries an Inventory owner table.');
}

echo "Operations catalog and unit tests passed\n";
