<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

$company = operations_test_company('Operations Authorization Company');
$otherCompany = operations_test_company('Other Authorization Company');
$admin = operations_test_admin();
$inactiveAdmin = [...$admin, 'admin_key' => bx_uuid(), 'admin_status' => 'INACTIVE'];
operations_test_register_cleanup($company);
operations_test_register_cleanup($otherCompany);
yovel_admin_operations_schema();

$provider = static function (array $records): callable {
    return static function (array $scope, array $query) use ($records): array {
        return [
            'ok' => true,
            'company_key_hash' => $scope['company_key_hash'],
            'records' => $records,
            'errors' => [],
        ];
    };
};
$providers = [
    'shared.identity-permission-directory.v1' => $provider([
        ['user_key' => 'user-approver-001', 'role_key' => 'role-operations-manager', 'permission_key' => 'permission-approve-001', 'display_name' => 'Operations Approver', 'api_token' => 'must-not-leak'],
    ]),
    'platform.company-scope.v1' => $provider([
        ['company_key' => $company['company_key'], 'scope_key' => 'scope-operations-001', 'scope_name' => 'Primary company'],
    ]),
    'owners.document-lifecycle-catalog.v1' => $provider([
        ['lifecycle_key' => 'lifecycle-purchase-order-submit', 'document_type' => 'Purchase Order', 'action' => 'SUBMIT'],
    ]),
    'hr.workforce-directory.v1' => $provider([
        ['employee_key' => 'employee-approver-001', 'user_key' => 'user-approver-001', 'display_name' => 'Operations Approver'],
    ]),
];

$projection = yovel_admin_operations_authorization_projection($company, $providers);
foreach (array_keys($providers) as $contractId) {
    operations_test_assert(($projection['contracts'][$contractId]['status'] ?? '') === 'AVAILABLE', 'Authorization projection did not expose an available owner contract: ' . $contractId);
}
operations_test_assert(($projection['contracts']['shared.identity-permission-directory.v1']['records'][0]['user_key'] ?? '') === 'user-approver-001', 'Authorization projection changed a stable owner reference.');
operations_test_assert(!str_contains(json_encode($projection, JSON_THROW_ON_ERROR), 'must-not-leak'), 'Authorization projection exposed a raw secret.');

$saved = yovel_admin_operations_persist_authorization_policy(bx_db(), $company, $admin, [
    'policy_name' => 'Purchase approval threshold',
    'lifecycle_key' => 'lifecycle-purchase-order-submit',
    'document_type' => 'Purchase Order',
    'action_code' => 'SUBMIT',
    'threshold_amount' => '5000.00',
    'currency_key' => 'currency-php',
    'approver_user_key' => 'user-approver-001',
    'approver_role_key' => 'role-operations-manager',
    'approver_employee_key' => 'employee-approver-001',
    'policy_status' => 'ACTIVE',
], $providers);
operations_test_assert(yovel_admin_is_uuid((string) ($saved['policy_key'] ?? '')), 'Authorization policy did not return a stable key.');
operations_test_assert((string) $saved['threshold_amount'] === '5000.000000', 'Authorization policy threshold was not exactly read back.');
operations_test_assert((string) $saved['approver_user_key'] === 'user-approver-001', 'Authorization policy lost its approver reference.');
operations_test_assert(count(yovel_admin_operations_authorization_policies($company)) === 1, 'Authorization policy was not company-scoped and rehydrated.');
operations_test_assert(yovel_admin_operations_authorization_policies($otherCompany) === [], 'Authorization policy leaked across companies.');
operations_test_expect_exception(static fn () => yovel_admin_operations_persist_authorization_policy(bx_db(), $company, $inactiveAdmin, ['policy_name' => 'Denied'], $providers), 'authorized');
operations_test_expect_exception(static fn () => yovel_admin_operations_persist_authorization_policy(bx_db(), $company, $admin, [
    'policy_name' => 'Unknown approver', 'lifecycle_key' => 'lifecycle-purchase-order-submit', 'document_type' => 'Purchase Order',
    'action_code' => 'SUBMIT', 'threshold_amount' => '1', 'approver_user_key' => 'foreign-user', 'policy_status' => 'ACTIVE',
], $providers), 'approver');

$missing = yovel_admin_operations_authorization_projection($company, []);
operations_test_assert(($missing['contracts']['shared.identity-permission-directory.v1']['status'] ?? '') === 'UNAVAILABLE', 'Missing authorization provider was not explicit.');
operations_test_assert(($missing['contracts']['shared.identity-permission-directory.v1']['blocking'] ?? true) === false, 'Missing authorization provider incorrectly blocked the workspace.');
$mismatch = $providers;
$mismatch['platform.company-scope.v1'] = static fn (array $scope, array $query): array => ['ok' => true, 'company_key_hash' => str_repeat('0', 64), 'records' => [['scope_key' => 'leak']], 'errors' => []];
$mismatchProjection = yovel_admin_operations_authorization_projection($company, $mismatch);
operations_test_assert(($mismatchProjection['contracts']['platform.company-scope.v1']['status'] ?? '') === 'UNAVAILABLE', 'Company-scope envelope mismatch was accepted.');
operations_test_assert(!str_contains(json_encode($mismatchProjection, JSON_THROW_ON_ERROR), 'leak'), 'Rejected company-scope records leaked into the projection.');
operations_test_expect_exception(static fn () => yovel_admin_operations_call_read_contract('platform.company-scope.v1', $company, [], $mismatch), 'company scope');

$GLOBALS['yovel_admin_operations_dependency_providers'] = $providers;
$post = yovel_admin_operations_handle_post($company, $admin, 'save_operations_authorization_policy', [
    'section' => 'authorization-setup', 'policy_name' => 'Expense approval threshold',
    'lifecycle_key' => 'lifecycle-purchase-order-submit', 'document_type' => 'Expense Claim', 'action_code' => 'SUBMIT',
    'threshold_amount' => '2500', 'currency_key' => 'currency-php', 'approver_user_key' => 'user-approver-001',
    'approver_role_key' => 'role-operations-manager', 'approver_employee_key' => 'employee-approver-001', 'policy_status' => 'ACTIVE',
]);
unset($GLOBALS['yovel_admin_operations_dependency_providers']);
operations_test_assert(array_keys($post) === ['message', 'section', 'query'], 'Authorization POST handler returned the wrong shared envelope.');
operations_test_assert((string) $post['section'] === 'authorization-setup' && yovel_admin_is_uuid((string) ($post['query']['policy'] ?? '')), 'Authorization POST handler did not return the saved key.');

$viewSource = file_get_contents($operationsTestRoot . '/company/admin/modules/operations/views/sections/authorization.php');
operations_test_assert(str_contains((string) $viewSource, 'name="module_view" value="operations"'), 'Authorization modal is missing shared module dispatch.');
operations_test_assert(str_contains((string) $viewSource, "'open_on_load'"), 'Authorization validation state cannot reopen the populated modal.');
operations_test_assert(!preg_match('/name="action" value="(?:save|update|delete)_(?:company|branch|user|role|employee)/i', (string) $viewSource), 'Authorization UI exposes a foreign-owner mutation.');

echo "Operations authorization setup checks passed.\n";
