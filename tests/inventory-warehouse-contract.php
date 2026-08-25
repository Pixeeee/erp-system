<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/inventory-warehouse/functions.php';

function inventory_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$route = yovel_admin_module_route('inventory-warehouse');
inventory_contract_assert(is_array($route), 'Inventory/Warehouse is not registered.');
inventory_contract_assert(($route['default_section'] ?? '') === 'dashboard', 'Inventory/Warehouse default section changed.');

$expectedSections = [
    'dashboard',
    'items',
    'warehouses',
    'stock-entries',
    'stock-ledger',
    'stock-reconciliation',
    'batch-numbers',
    'serial-numbers',
    'barcode-records',
    'reorder-levels',
    'putaway',
    'picking',
    'packing',
    'shipment',
    'stock-balance-reports',
    'traceability-reports',
    'form-builder',
];
$sections = yovel_admin_inventory_warehouse_sections();
inventory_contract_assert(array_keys($sections) === $expectedSections, 'Inventory/Warehouse section registry is incomplete or unstable.');
inventory_contract_assert(yovel_admin_inventory_warehouse_section('Stock Ledger') === 'stock-ledger', 'Section normalization failed.');
inventory_contract_assert(yovel_admin_inventory_warehouse_section('not-a-section') === 'dashboard', 'Unknown sections must resolve to Dashboard.');
inventory_contract_assert(yovel_admin_inventory_warehouse_section('items') === 'items', 'Explicit Items section must remain stable.');

$reflection = new ReflectionFunction('yovel_admin_inventory_warehouse_data');
inventory_contract_assert($reflection->getNumberOfRequiredParameters() === 2, 'The shared registry must be able to call Inventory data with two arguments.');
inventory_contract_assert($reflection->getNumberOfParameters() === 3, 'Inventory data must retain the optional section parameter.');
$postReflection = new ReflectionFunction('yovel_admin_inventory_warehouse_handle_post');
inventory_contract_assert($postReflection->getNumberOfRequiredParameters() === 4, 'Inventory must expose the four-argument shared POST interface.');

$scope = bx_db()->GetRow(
    "SELECT company_record.company_key, company_record.company_key_hash, company_record.company_name,
            admin_record.admin_key, admin_record.admin_status
     FROM project_company company_record
     INNER JOIN project_company_admin admin_record
       ON admin_record.company_key_hash = company_record.company_key_hash
      AND admin_record.admin_status = 'ACTIVE'
     WHERE company_record.company_status = 'ACTIVE'
     ORDER BY company_record.x_id, admin_record.x_id
     LIMIT 1"
);
inventory_contract_assert(is_array($scope) && $scope !== [], 'An active company/admin fixture is required.');
$company = [
    'company_key' => (string) $scope['company_key'],
    'company_key_hash' => (string) $scope['company_key_hash'],
    'company_name' => (string) $scope['company_name'],
];
$admin = [
    'admin_key' => (string) $scope['admin_key'],
    'admin_status' => (string) $scope['admin_status'],
];

$_GET['section'] = 'stock-reconciliation';
$registryData = yovel_admin_inventory_warehouse_data($company, $admin);
inventory_contract_assert(($registryData['section'] ?? '') === 'stock-reconciliation', 'Two-argument data resolution did not honor the request section.');
inventory_contract_assert(($registryData['state']['kind'] ?? '') === 'dependency', 'Unimplemented stock reconciliation must render a real dependency state.');
inventory_contract_assert(in_array('IW-08', $registryData['state']['dependencies'] ?? [], true), 'Reconciliation dependency state does not identify IW-08.');

$_GET['section'] = 'unknown';
$defaultData = yovel_admin_inventory_warehouse_data($company, $admin);
inventory_contract_assert(($defaultData['section'] ?? '') === 'dashboard', 'Invalid request section did not resolve to Dashboard.');
inventory_contract_assert(($defaultData['state']['kind'] ?? '') === 'ready', 'Dashboard fallback must render a ready state.');
unset($_GET['section']);
$defaultData = yovel_admin_inventory_warehouse_data($company, $admin);
inventory_contract_assert(($defaultData['section'] ?? '') === 'dashboard', 'Absent request section did not resolve to Dashboard.');

inventory_contract_assert(yovel_admin_inventory_decimal('12.3456789014') === '12.345678901', 'Inventory quantity normalization must use nine decimal places.');
inventory_contract_assert(yovel_admin_inventory_decimal('-0.0000000004') === '0.000000000', 'Inventory decimal normalization must canonicalize negative zero.');
try {
    yovel_admin_inventory_decimal('12e3');
    throw new RuntimeException('Scientific notation must not be accepted for authoritative stock decimals.');
} catch (InvalidArgumentException) {
}

$activeModuleSections = $sections;
$activeModuleSection = 'items';
$activeModuleData = yovel_admin_inventory_warehouse_data($company, $admin, 'items');
$companyName = (string) $company['company_name'];
ob_start();
require $root . '/company/admin/modules/inventory-warehouse/views/workspace.php';
$itemsMarkup = (string) ob_get_clean();

inventory_contract_assert(str_contains($itemsMarkup, 'data-inventory-workspace'), 'Inventory workspace marker is missing.');
inventory_contract_assert(str_contains($itemsMarkup, 'yovel-inventory-workspace'), 'Inventory workspace class is missing.');
inventory_contract_assert(str_contains($itemsMarkup, 'minmax(0, 12fr) minmax(16rem, 8fr)'), 'Inventory workspace is not a stable 12/20 and 8/20 grid.');
inventory_contract_assert(str_contains($itemsMarkup, 'data-inventory-main') && str_contains($itemsMarkup, 'data-grid-span="12"'), 'Inventory main panel is not 12/20.');
inventory_contract_assert(str_contains($itemsMarkup, 'data-inventory-tools') && str_contains($itemsMarkup, 'data-grid-span="8"'), 'Inventory tools panel is not 8/20.');
inventory_contract_assert(strpos($itemsMarkup, 'data-inventory-main') < strpos($itemsMarkup, 'data-inventory-tools'), 'Responsive source order must keep the main panel first.');
if (($activeModuleData['items'] ?? []) === []) {
    inventory_contract_assert(str_contains($itemsMarkup, 'No item records'), 'Items empty state is not rendered.');
} else {
    inventory_contract_assert(str_contains($itemsMarkup, (string) $activeModuleData['items'][0]['item_code']), 'Live Items catalogue state is not rendered.');
}

$activeModuleSection = 'form-builder';
$activeModuleData = yovel_admin_inventory_warehouse_data($company, $admin, 'form-builder');
$activeModuleFormState = [
    'section' => 'form-builder',
    'action' => 'save_inventory_form_schema',
    'input' => [
        'record_type' => 'ITEM',
        'schema_json' => '{}',
        'new_field_label' => 'Controller retained value',
    ],
    'error' => 'Controller-provided validation error.',
];
ob_start();
require $root . '/company/admin/modules/inventory-warehouse/views/workspace.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$builderMarkup = (string) ob_get_clean();

preg_match_all('/<button\b([^>]*)>(.*?)<\/button>/si', $builderMarkup, $buttons, PREG_SET_ORDER);
$creationCommands = 0;
foreach ($buttons as $button) {
    if (preg_match('/(?:Add|New|Create|Insert)/i', strip_tags((string) $button[2])) !== 1) {
        continue;
    }
    $creationCommands++;
    inventory_contract_assert(str_contains((string) $button[1], 'data-record-modal-open='), 'Every Add/New/Create/Insert command must open a record modal.');
}
inventory_contract_assert($creationCommands > 0, 'IW-01 must expose at least one creation command.');
inventory_contract_assert(str_contains($builderMarkup, 'data-record-modal'), 'Inventory record modal contract marker is missing.');
inventory_contract_assert(str_contains($builderMarkup, 'data-confirm-submit'), 'Inventory form does not use the shared confirmation boundary.');
inventory_contract_assert(str_contains($builderMarkup, 'name="module_view" value="inventory-warehouse"'), 'Inventory form does not identify its shared module POST route.');
inventory_contract_assert(str_contains($builderMarkup, 'data-confirm-dialog'), 'Shared sibling confirmation dialog is missing from the rendered contract fixture.');
inventory_contract_assert(strpos($builderMarkup, 'data-confirm-dialog') > strpos($builderMarkup, '</form>'), 'Confirmation dialog must be a body-owned sibling, not nested in the form.');
inventory_contract_assert(str_contains($builderMarkup, 'Controller retained value'), 'Shared controller prior input was not rehydrated.');
inventory_contract_assert(str_contains($builderMarkup, 'Controller-provided validation error.'), 'Shared controller validation error was not rendered.');

try {
    yovel_admin_inventory_warehouse_handle_post($company, $admin, 'unknown_inventory_action', []);
    throw new RuntimeException('Unknown Inventory actions must be rejected.');
} catch (InvalidArgumentException) {
}

$moduleSource = '';
foreach (glob($root . '/company/admin/modules/inventory-warehouse/*.php') ?: [] as $sourcePath) {
    $moduleSource .= (string) file_get_contents($sourcePath);
}
foreach (['project_company_finance_', 'project_company_sales_', 'project_company_buying_', 'project_company_manufacturing_'] as $forbiddenPrefix) {
    inventory_contract_assert(!str_contains($moduleSource, $forbiddenPrefix), 'Inventory IW-01 directly references a foreign authoritative table: ' . $forbiddenPrefix);
}
inventory_contract_assert(!is_file($root . '/company/admin/modules/inventory-warehouse/admin-modal.js'), 'Inventory must not duplicate the shared modal controller.');

echo "Inventory/Warehouse IW-01 contract checks passed.\n";
