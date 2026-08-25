<?php
declare(strict_types=1);

require_once __DIR__ . '/operations-test-helper.php';

$mode = (string) ($argv[1] ?? '');
if ($mode === 'missing-owner-services') {
    $projection = yovel_admin_operations_commercial_projection(
        operations_test_company('Missing Commercial Owners'),
        operations_test_admin()
    );
    echo json_encode($projection, JSON_THROW_ON_ERROR);
    exit(0);
}

if (!function_exists('yovel_admin_sales_crm_data')) {
    function yovel_admin_sales_crm_data(array $company, ?array $admin = null): array
    {
        return ($GLOBALS['operations_sales_service'])($company, $admin);
    }
}
if (!function_exists('yovel_admin_buying_suppliers')) {
    function yovel_admin_buying_suppliers(array $company): array
    {
        return ($GLOBALS['operations_buying_service'])($company);
    }
}
if (!function_exists('yovel_admin_finance_foundation_records')) {
    function yovel_admin_finance_foundation_records(array $company): array
    {
        return ($GLOBALS['operations_finance_service'])($company);
    }
}
if (!function_exists('yovel_admin_inventory_items')) {
    function yovel_admin_inventory_items(array $company, array $filters = []): array
    {
        return ($GLOBALS['operations_inventory_service'])($company, $filters);
    }
}
if (!function_exists('yovel_admin_hr_workforce_read_contract')) {
    function yovel_admin_hr_workforce_read_contract(array $company, array $options = []): array
    {
        return ($GLOBALS['operations_hr_service'])($company, $options);
    }
}

$company = operations_test_company('Operations Commercial Company');
$otherCompany = operations_test_company('Other Commercial Company');
$admin = operations_test_admin();
$keys = [
    'territory' => bx_uuid(),
    'salesperson' => bx_uuid(),
    'customer' => bx_uuid(),
    'supplier' => bx_uuid(),
    'supplier_group' => bx_uuid(),
    'exchange_setting' => bx_uuid(),
    'exchange_rate' => bx_uuid(),
    'item' => bx_uuid(),
    'employee' => bx_uuid(),
    'party_type' => bx_uuid(),
];
$calls = ['sales' => 0, 'buying' => 0, 'finance' => 0, 'inventory' => 0, 'hr' => 0, 'shared' => 0];
$seenScopes = [];

$GLOBALS['operations_sales_service'] = static function (array $scope, ?array $actor) use (&$calls, &$seenScopes, $keys): array {
    $calls['sales']++;
    $seenScopes['sales'] = (string) ($scope['company_key_hash'] ?? '');
    return [
        'territories' => [[
            'territory_key' => $keys['territory'], 'territory_code' => 'NCR',
            'territory_name' => 'National Capital Region', 'territory_status' => 'ACTIVE',
            'api_token' => 'sales-secret',
        ]],
        'salespersons' => [[
            'salesperson_key' => $keys['salesperson'], 'salesperson_code' => 'SP-001',
            'salesperson_name' => 'Ada Seller', 'salesperson_status' => 'ACTIVE',
        ]],
        'customers' => [[
            'customer_key' => $keys['customer'], 'customer_code' => 'CUS-001',
            'customer_name' => 'Customer Fixture', 'customer_status' => 'ACTIVE',
        ]],
    ];
};
$GLOBALS['operations_buying_service'] = static function (array $scope) use (&$calls, &$seenScopes, $keys): array {
    $calls['buying']++;
    $seenScopes['buying'] = (string) ($scope['company_key_hash'] ?? '');
    return [[
        'supplier_key' => $keys['supplier'], 'supplier_code' => 'SUP-001', 'supplier_name' => 'Supplier Fixture',
        'supplier_status' => 'ACTIVE', 'supplier_group_key' => $keys['supplier_group'], 'password' => 'buying-secret',
    ]];
};
$GLOBALS['operations_finance_service'] = static function (array $scope) use (&$calls, &$seenScopes, $keys): array {
    $calls['finance']++;
    $seenScopes['finance'] = (string) ($scope['company_key_hash'] ?? '');
    return ['exchangeSettings' => [[
        'exchange_setting_key' => $keys['exchange_setting'], 'service_provider' => 'CUSTOM',
        'base_currency' => 'PHP', 'status' => 'ACTIVE', 'api_endpoint' => 'https://secret.example.test/{date}',
        'rates' => [[
            'exchange_rate_key' => $keys['exchange_rate'], 'exchange_setting_key' => $keys['exchange_setting'],
            'from_currency' => 'USD', 'to_currency' => 'PHP', 'transaction_date' => '2026-08-25',
            'exchange_rate' => '57.125000000', 'credential' => 'finance-secret',
        ]],
    ]]];
};
$GLOBALS['operations_inventory_service'] = static function (array $scope, array $filters) use (&$calls, &$seenScopes, $keys): array {
    $calls['inventory']++;
    $seenScopes['inventory'] = (string) ($scope['company_key_hash'] ?? '');
    return [[
        'item_key' => $keys['item'], 'item_code' => 'ITEM-001', 'item_name' => 'Target Context Item',
        'item_status' => 'ACTIVE', 'secret' => 'inventory-secret',
    ]];
};
$GLOBALS['operations_hr_service'] = static function (array $scope, array $options) use (&$calls, &$seenScopes, $keys): array {
    $calls['hr']++;
    $seenScopes['hr'] = (string) ($scope['company_key_hash'] ?? '');
    return [
        'contract' => 'hr.workforce.v1', 'company_key_hash' => $scope['company_key_hash'],
        'employees' => [[
            'employee_key' => $keys['employee'], 'employee_code' => 'EMP-001', 'employee_name' => 'Target Owner',
            'employee_status' => 'ACTIVE', 'department_key' => '', 'job_position_key' => '',
        ]],
        'availability' => [
            'education' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
            'employment_history' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
            'employee_group' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
            'calendar' => ['available' => false, 'reason' => 'Deferred to HR-WP-10.'],
        ],
    ];
};
$providers = [
    'shared.record-type-directory.v1' => static function (array $scope, array $query) use (&$calls, &$seenScopes, $keys): array {
        $calls['shared']++;
        $seenScopes['shared'] = (string) ($scope['company_key_hash'] ?? '');
        return [
            'ok' => true, 'company_key_hash' => $scope['company_key_hash'], 'errors' => [],
            'records' => [[
                'record_type_key' => $keys['party_type'], 'record_type_code' => 'CUSTOMER',
                'record_type_label' => 'Customer', 'record_type_status' => 'ACTIVE', 'api_token' => 'shared-secret',
            ]],
        ];
    },
];

$projection = yovel_admin_operations_commercial_projection($company, $admin, $providers);
operations_test_assert(($projection['company_key_hash'] ?? '') === $company['company_key_hash'], 'Commercial projection changed company scope.');
operations_test_assert($calls === ['sales' => 1, 'buying' => 1, 'finance' => 1, 'inventory' => 1, 'hr' => 1, 'shared' => 1], 'Commercial projection did not call each verified owner service exactly once.');
operations_test_assert(count($seenScopes) === 6 && count(array_filter($seenScopes, static fn (string $hash): bool => $hash === $company['company_key_hash'])) === 6, 'Commercial projection did not propagate exact company scope to every owner service.');
foreach (($projection['sources'] ?? []) as $source) {
    operations_test_assert(($source['status'] ?? '') === 'AVAILABLE', 'A valid commercial owner source was not available: ' . ($source['owner'] ?? 'unknown'));
}

$directories = $projection['directories'] ?? [];
$expectedDirectories = [
    'currency_exchange', 'customer_group', 'incoterm', 'party_type', 'quotation_lost_reason',
    'quotation_lost_reason_detail', 'sales_partner', 'sales_person', 'supplier_group', 'target_detail',
    'terms_and_conditions', 'territory',
];
operations_test_assert(array_keys($directories) === $expectedDirectories, 'Commercial projection does not trace all twelve OP-04 rows.');

foreach (['currency_exchange', 'party_type', 'sales_person', 'supplier_group', 'territory'] as $directory) {
    operations_test_assert(($directories[$directory]['status'] ?? '') === 'PARTIAL', $directory . ' was not marked PARTIAL.');
    operations_test_assert(($directories[$directory]['records'] ?? []) !== [], $directory . ' lost verified stable owner records.');
}
operations_test_assert(($directories['currency_exchange']['records'][0] ?? null) === [
    'exchange_rate_key' => $keys['exchange_rate'], 'exchange_setting_key' => $keys['exchange_setting'],
    'from_currency' => 'USD', 'to_currency' => 'PHP', 'transaction_date' => '2026-08-25',
    'exchange_rate' => '57.125000000', 'status' => 'ACTIVE',
], 'Currency Exchange projection changed verified Finance fields.');
operations_test_assert(($directories['sales_person']['records'][0]['salesperson_key'] ?? '') === $keys['salesperson'], 'Sales Person stable key was lost.');
operations_test_assert(($directories['territory']['records'][0]['territory_key'] ?? '') === $keys['territory'], 'Territory stable key was lost.');
operations_test_assert(($directories['supplier_group']['records'] ?? []) === [['supplier_group_key' => $keys['supplier_group']]], 'Supplier Group reference was embellished or lost.');
operations_test_assert(($directories['party_type']['records'][0]['record_type_key'] ?? '') === $keys['party_type'], 'Party Type stable shared key was lost.');

foreach (['customer_group', 'incoterm', 'quotation_lost_reason', 'quotation_lost_reason_detail', 'sales_partner', 'target_detail', 'terms_and_conditions'] as $directory) {
    operations_test_assert(($directories[$directory]['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', $directory . ' was presented as empty success.');
    operations_test_assert(($directories[$directory]['records'] ?? null) === [], $directory . ' fabricated owner records.');
    operations_test_assert(($directories[$directory]['unavailable_fields'] ?? []) !== [], $directory . ' does not name exact unavailable fields.');
    operations_test_assert(($directories[$directory]['blocking'] ?? true) === false, $directory . ' incorrectly blocks Operations.');
}
operations_test_assert(($projection['contexts']['customers'][0]['customer_key'] ?? '') === $keys['customer'], 'Available customer context was not retained for the Customer Group gap.');
operations_test_assert(($projection['contexts']['items'][0]['item_key'] ?? '') === $keys['item'], 'Available Inventory context was not retained for Target Detail.');
operations_test_assert(($projection['contexts']['employees'][0]['employee_key'] ?? '') === $keys['employee'], 'Available HR context was not retained for Target Detail.');
operations_test_assert(!str_contains(json_encode($projection, JSON_THROW_ON_ERROR), 'secret'), 'Commercial projection exposed a raw owner secret.');
operations_test_assert(isset($projection['related_owner_gaps']['customer_contact_address'], $projection['related_owner_gaps']['opportunity_order']), 'Related customer/contact/address or opportunity/order gaps are not explicit.');

$mismatchProviders = ['shared.record-type-directory.v1' => static fn (array $scope, array $query): array => [
    'ok' => true, 'company_key_hash' => $otherCompany['company_key_hash'], 'records' => [[
        'record_type_key' => bx_uuid(), 'record_type_code' => 'LEAK', 'record_type_label' => 'Wrong company', 'record_type_status' => 'ACTIVE',
    ]], 'errors' => [],
]];
$mismatch = yovel_admin_operations_commercial_projection($company, $admin, $mismatchProviders);
operations_test_assert(($mismatch['directories']['party_type']['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Cross-company shared Party Type envelope was accepted.');
operations_test_assert(($mismatch['directories']['party_type']['records'] ?? null) === [], 'Cross-company Party Type records leaked.');
operations_test_assert(($mismatch['directories']['sales_person']['records'][0]['salesperson_key'] ?? '') === $keys['salesperson'], 'One failed source suppressed valid owner projections.');

$missingJson = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' missing-owner-services');
operations_test_assert(is_string($missingJson) && $missingJson !== '', 'Missing-owner child check did not return a projection.');
$missing = json_decode($missingJson, true, 512, JSON_THROW_ON_ERROR);
foreach (($missing['sources'] ?? []) as $source) {
    operations_test_assert(($source['status'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Absent owner service became empty success.');
}
foreach (($missing['directories'] ?? []) as $directory) {
    operations_test_assert(($directory['records'] ?? null) === [], 'Missing owner service fabricated a commercial record.');
}

$sections = yovel_admin_operations_sections();
operations_test_assert(isset($sections['commercial-masters']), 'Commercial Masters workspace section is not registered.');
operations_test_assert(count(yovel_admin_operations_builder_targets()) === 16, 'Universal Operations Form Builder does not include Commercial Masters and subsequent projections.');
$workspaceSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/workspace.php');
$viewSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/sections/commercial.php');
operations_test_assert(str_contains($workspaceSource, "'commercial-masters' => 'commercial.php'"), 'Workspace does not dispatch Commercial Masters.');
operations_test_assert(str_contains($workspaceSource, 'minmax(0,12fr)') && str_contains($workspaceSource, 'minmax(16rem,8fr)'), 'Commercial Masters did not retain the 12/8 workspace shell.');
operations_test_assert(!preg_match('/<(?:form|button)\b/i', $viewSource), 'Read-only Commercial Masters exposes a local or foreign mutation command.');
operations_test_assert(!str_contains($viewSource, '0 records'), 'Commercial view turns unavailable owner data into a numeric zero.');
foreach (['sales-crm', 'buying-procurement', 'accounting-finance', 'inventory-warehouse', 'hr'] as $ownerView) {
    operations_test_assert(str_contains($viewSource, 'view=' . $ownerView), 'Commercial view is missing owner route: ' . $ownerView);
}
operations_test_assert(!str_contains($viewSource, 'rounded-md border'), 'Commercial view nests bordered cards inside the workspace panel.');

$operationFiles = glob(dirname(__DIR__) . '/company/admin/modules/operations/*.php') ?: [];
foreach ($operationFiles as $operationFile) {
    $source = (string) file_get_contents($operationFile);
    operations_test_assert(!preg_match('/project_company_(?:sales|buying|finance|accounting|inventory|hr)_/i', $source), basename($operationFile) . ' directly queries a foreign owner table.');
}

echo "Operations commercial master tests passed\n";
