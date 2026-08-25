<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once $root . '/company/admin/modules/assets-maintenance/functions.php';

function assets_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = bx_db();
$fixtureSuffix = substr(hash('sha256', bx_uuid()), 0, 16);
$companyKey = 'assets:company/' . $fixtureSuffix . '/opaque';
$companyKeyHash = hash('sha256', $companyKey);
$companyCode = 'AST' . strtoupper(substr($fixtureSuffix, 0, 12));
$companySlug = 'assets-contract-' . $fixtureSuffix;
$adminKey = bx_uuid();
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id), 0) FROM builder_audit_log');

$companyInserted = $db->Execute(
    "INSERT INTO project_company (
        company_key, company_key_hash, company_code, company_slug, company_name, company_status
     ) VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
    [$companyKey, $companyKeyHash, $companyCode, $companySlug, 'Assets Contract Company']
);
assets_contract_assert($companyInserted !== false, 'Opaque company fixture could not be created.');
$adminInserted = $db->Execute(
    "INSERT INTO project_company_admin (
        admin_key, company_key, company_key_hash, admin_login, admin_password_hash,
        admin_name, admin_email, admin_status
     ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
    [$adminKey, $companyKey, $companyKeyHash, 'assets_' . $fixtureSuffix, password_hash('contract-only', PASSWORD_DEFAULT), 'Assets Contract Admin', 'assets-' . $fixtureSuffix . '@example.test']
);
assets_contract_assert($adminInserted !== false, 'Opaque company administrator fixture could not be created.');

$assetTables = [
    'project_company_asset_form_submission',
    'project_company_asset_form_audit',
    'project_company_asset_form_version',
    'project_company_asset_form',
];
register_shutdown_function(static function () use ($db, $assetTables, $companyKeyHash, $companyKey, $adminKey, $auditFloor): void {
    foreach ($assetTables as $table) {
        $exists = (int) $db->GetOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
        if ($exists === 1) {
            $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$companyKeyHash]);
        }
    }
    $db->Execute("DELETE FROM builder_audit_log WHERE x_id > ? AND module = 'project_company_asset_form'", [$auditFloor]);
    $db->Execute('DELETE FROM project_company_admin WHERE admin_key = ? AND company_key_hash = ?', [$adminKey, $companyKeyHash]);
    $db->Execute('DELETE FROM project_company WHERE company_key = ? AND company_key_hash = ?', [$companyKey, $companyKeyHash]);
});

$company = [
    'company_key' => $companyKey,
    'company_key_hash' => $companyKeyHash,
    'company_name' => 'Assets Contract Company',
];
$admin = ['admin_key' => $adminKey, 'admin_status' => 'ACTIVE'];

$route = yovel_admin_module_route('assets-maintenance');
assets_contract_assert(is_array($route), 'Assets / Maintenance is not registered.');
assets_contract_assert(($route['default_section'] ?? '') === 'asset-records', 'Assets default section changed.');
assets_contract_assert(($route['action_provider'] ?? '') === 'yovel_admin_assets_maintenance_handle_post', 'Assets registry action provider changed.');

$expectedSections = [
    'dashboard',
    'asset-records',
    'asset-depreciation-schedule',
    'fixed-asset-register',
    'maintenance-schedules',
    'quality-inspection',
    'maintenance-reports',
    'form-builder',
];
$sections = yovel_admin_assets_maintenance_sections();
assets_contract_assert(array_keys($sections) === $expectedSections, 'Assets section registry is incomplete or unstable.');
assets_contract_assert(yovel_admin_assets_maintenance_section('Fixed Asset Register') === 'fixed-asset-register', 'Assets section normalization failed.');
assets_contract_assert(yovel_admin_assets_maintenance_section('unknown') === 'asset-records', 'Unknown Assets sections must resolve to Asset records.');

$dataReflection = new ReflectionFunction('yovel_admin_assets_maintenance_data');
assets_contract_assert($dataReflection->getNumberOfRequiredParameters() === 2, 'Assets data provider must require company and administrator scope.');
assets_contract_assert($dataReflection->getNumberOfParameters() === 3, 'Assets data provider must retain its optional section argument.');
$postReflection = new ReflectionFunction('yovel_admin_assets_maintenance_handle_post');
assets_contract_assert($postReflection->getNumberOfRequiredParameters() === 4, 'Assets must expose the exact four-argument registry POST handler.');

$scope = yovel_admin_assets_scope($company, $admin);
assets_contract_assert($scope === [$companyKey, $companyKeyHash, $adminKey], 'Opaque Assets company scope did not resolve exact keys.');
$wrongHashCompany = $company;
$wrongHashCompany['company_key_hash'] = str_repeat('0', 64);
try {
    yovel_admin_assets_scope($wrongHashCompany, $admin);
    throw new RuntimeException('Mismatched company key/hash scope must be rejected.');
} catch (RuntimeException $error) {
    assets_contract_assert(str_contains(strtolower($error->getMessage()), 'authorized'), 'Scope rejection must use a safe authorization message.');
}
$db->Execute("UPDATE project_company_admin SET admin_status = 'INACTIVE' WHERE admin_key = ?", [$adminKey]);
try {
    yovel_admin_assets_scope($company, $admin);
    throw new RuntimeException('Inactive administrator membership must be rejected.');
} catch (RuntimeException) {
}
$db->Execute("UPDATE project_company_admin SET admin_status = 'ACTIVE' WHERE admin_key = ?", [$adminKey]);

$tablesBefore = [];
foreach ($assetTables as $table) {
    $tablesBefore[$table] = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$table]
    );
}
yovel_admin_assets_maintenance_schema();
yovel_admin_assets_maintenance_schema();
foreach ($assetTables as $table) {
    assets_contract_assert(
        (int) $db->GetOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]) === 1,
        'Assets schema table is missing after idempotent setup: ' . $table
    );
    assets_contract_assert(
        (int) $db->GetOne("SELECT COUNT(*) FROM {$table} WHERE company_key_hash = ?", [$companyKeyHash]) === 0,
        'Schema setup created business rows for ' . $table . '.'
    );
}

$_GET['section'] = 'maintenance-reports';
$registryData = yovel_admin_assets_maintenance_data($company, $admin);
assets_contract_assert(($registryData['section'] ?? '') === 'maintenance-reports', 'Two-argument data resolution did not honor the request section.');
assets_contract_assert(($registryData['state']['kind'] ?? '') === 'dependency', 'Task 1 feature gaps must render explicit dependency states.');
assets_contract_assert(($registryData['state']['dependencies'] ?? []) !== [], 'Dependency state must name a later work package.');

$_GET['section'] = 'unknown';
$defaultData = yovel_admin_assets_maintenance_data($company, $admin);
assets_contract_assert(($defaultData['section'] ?? '') === 'asset-records', 'Invalid Assets section did not resolve to Asset records.');
assets_contract_assert(($defaultData['state']['kind'] ?? '') === 'empty', 'Asset records must expose a real empty state during Task 1.');

$activeModuleSections = $sections;
$activeModuleSection = 'asset-records';
$activeModuleData = yovel_admin_assets_maintenance_data($company, $admin, 'asset-records');
$activeModuleFormState = [];
$companyName = $company['company_name'];
ob_start();
require $root . '/company/admin/modules/assets-maintenance/views/workspace.php';
$assetMarkup = (string) ob_get_clean();

assets_contract_assert(str_contains($assetMarkup, 'data-assets-maintenance-workspace'), 'Assets workspace marker is missing.');
assets_contract_assert(str_contains($assetMarkup, 'minmax(0, 12fr) minmax(16rem, 8fr)'), 'Assets workspace is not a stable 12/20 and 8/20 grid.');
assets_contract_assert(str_contains($assetMarkup, 'data-assets-main') && str_contains($assetMarkup, 'data-grid-span="12"'), 'Assets main panel is not 12/20.');
assets_contract_assert(str_contains($assetMarkup, 'data-assets-tools') && str_contains($assetMarkup, 'data-grid-span="8"'), 'Assets tools panel is not 8/20.');
assets_contract_assert(strpos($assetMarkup, 'data-assets-main') < strpos($assetMarkup, 'data-assets-tools'), 'Responsive source order must keep the Assets main panel first.');
assets_contract_assert(substr_count($assetMarkup, 'view=assets-maintenance') >= 8, 'Assets workspace does not expose all eight sections.');
assets_contract_assert(str_contains($assetMarkup, 'No asset records yet'), 'Asset records empty state is not rendered.');

$activeModuleSection = 'form-builder';
$activeModuleData = yovel_admin_assets_maintenance_data($company, $admin, 'form-builder');
$activeModuleFormState = [
    'section' => 'form-builder',
    'action' => 'save_assets_form',
    'input' => [
        'target_section' => 'asset-records',
        'record_type' => 'ASSET',
        'form_title' => 'Retained opaque asset form',
        'form_description' => 'Retained after server validation.',
        'schema_json' => '{}',
    ],
    'error' => 'Controller-provided Assets validation error.',
];
ob_start();
require $root . '/company/admin/modules/assets-maintenance/views/workspace.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$builderMarkup = (string) ob_get_clean();

preg_match_all('/<button\b([^>]*)>(.*?)<\/button>/si', $builderMarkup, $buttons, PREG_SET_ORDER);
$creationCommands = 0;
foreach ($buttons as $button) {
    if (preg_match('/(?:Add|New|Create|Insert)/i', strip_tags((string) $button[2])) !== 1) {
        continue;
    }
    $creationCommands++;
    assets_contract_assert(str_contains((string) $button[1], 'data-record-modal-open='), 'Every Assets Add/New/Create/Insert command must open a record modal.');
}
assets_contract_assert($creationCommands > 0, 'Assets Task 1 must expose at least one creation command.');
foreach (['data-record-modal', 'data-record-modal-open-on-load', 'data-record-modal-form', 'data-confirm-submit', 'data-confirm-submit-action'] as $marker) {
    assets_contract_assert(str_contains($builderMarkup, $marker), 'Assets modal contract is missing marker: ' . $marker);
}
assets_contract_assert(str_contains($builderMarkup, 'name="module_view" value="assets-maintenance"'), 'Assets form does not identify its registry POST route.');
$confirmDialogNeedle = '<div id="yovel-confirm-dialog" data-confirm-dialog';
assets_contract_assert(str_contains($builderMarkup, $confirmDialogNeedle), 'Shared sibling confirmation dialog is missing from the contract fixture.');
assets_contract_assert(strpos($builderMarkup, $confirmDialogNeedle) > strrpos($builderMarkup, '</form>'), 'Confirmation dialog must be a body-owned sibling after every Assets form.');
assets_contract_assert(str_contains($builderMarkup, 'Retained opaque asset form'), 'Assets prior input was not server-rehydrated.');
assets_contract_assert(str_contains($builderMarkup, 'Controller-provided Assets validation error.'), 'Assets validation error was not rendered.');

try {
    yovel_admin_assets_maintenance_handle_post($company, $admin, 'unknown_assets_action', []);
    throw new RuntimeException('Unknown Assets actions must be rejected.');
} catch (InvalidArgumentException) {
}

$moduleSource = '';
foreach (glob($root . '/company/admin/modules/assets-maintenance/*.php') ?: [] as $sourcePath) {
    $moduleSource .= (string) file_get_contents($sourcePath);
}
foreach ([
    'project_company_inventory_',
    'project_company_finance_',
    'project_company_accounting_',
    'project_company_hr_',
    'project_company_buying_',
    'project_company_operations_',
] as $forbiddenPrefix) {
    assets_contract_assert(!str_contains($moduleSource, $forbiddenPrefix), 'Assets Task 1 references a foreign authoritative table: ' . $forbiddenPrefix);
}
assets_contract_assert(!str_contains($moduleSource, 'yovel_admin_is_uuid($companyKey'), 'Opaque company keys must not be forced through UUID validation.');
assets_contract_assert(!is_file($root . '/company/admin/modules/assets-maintenance/admin-modal.js'), 'Assets must not duplicate the shared modal controller.');

echo "Assets / Maintenance Task 1 contract checks passed.\n";
