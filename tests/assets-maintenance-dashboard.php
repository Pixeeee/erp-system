<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/assets-maintenance/functions.php';

function assets_dashboard_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assets_dashboard_fixture(ADOConnection $db, string $suffix, string $label): array
{
    $companyKey = 'assets:dashboard/' . $suffix . '/opaque';
    $companyKeyHash = hash('sha256', $companyKey);
    $adminKey = bx_uuid();
    assets_dashboard_assert($db->Execute(
        "INSERT INTO project_company (
            company_key, company_key_hash, company_code, company_slug, company_name, company_status
         ) VALUES (?, ?, ?, ?, ?, 'ACTIVE')",
        [$companyKey, $companyKeyHash, 'ADB' . strtoupper(substr(hash('sha256', $suffix), 0, 12)), 'assets-dashboard-' . $suffix, $label]
    ) !== false, 'Assets dashboard company fixture could not be created.');
    assets_dashboard_assert($db->Execute(
        "INSERT INTO project_company_admin (
            admin_key, company_key, company_key_hash, admin_login, admin_password_hash,
            admin_name, admin_email, admin_status
         ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')",
        [$adminKey, $companyKey, $companyKeyHash, 'assets_dashboard_' . $suffix, password_hash('dashboard-only', PASSWORD_DEFAULT), $label . ' Admin', $suffix . '@example.test']
    ) !== false, 'Assets dashboard administrator fixture could not be created.');

    return [
        'company' => [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'company_name' => $label,
            'company_slug' => 'assets-dashboard-' . $suffix,
        ],
        'admin' => ['admin_key' => $adminKey, 'admin_status' => 'ACTIVE'],
    ];
}

$db = bx_db();
$suffix = substr(hash('sha256', bx_uuid()), 0, 14);
$primary = assets_dashboard_fixture($db, $suffix . 'a', 'Assets Dashboard Company');
$other = assets_dashboard_fixture($db, $suffix . 'b', 'Other Assets Dashboard Company');
$auditFloor = (int) $db->GetOne('SELECT COALESCE(MAX(x_id), 0) FROM builder_audit_log');
$assetTables = [
    'project_company_asset_form_submission',
    'project_company_asset_form_audit',
    'project_company_asset_form_version',
    'project_company_asset_form',
];
register_shutdown_function(static function () use ($db, $assetTables, $primary, $other, $auditFloor): void {
    unset($GLOBALS['yovel_admin_assets_dashboard_dependency_providers']);
    foreach ([$primary, $other] as $fixture) {
        $hash = (string) $fixture['company']['company_key_hash'];
        foreach ($assetTables as $table) {
            $exists = (int) $db->GetOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
            if ($exists === 1) {
                $db->Execute("DELETE FROM {$table} WHERE company_key_hash = ?", [$hash]);
            }
        }
        $db->Execute('DELETE FROM project_company_admin WHERE admin_key = ? AND company_key_hash = ?', [$fixture['admin']['admin_key'], $hash]);
        $db->Execute('DELETE FROM project_company WHERE company_key = ? AND company_key_hash = ?', [$fixture['company']['company_key'], $hash]);
    }
    $db->Execute("DELETE FROM builder_audit_log WHERE x_id > ? AND module = 'project_company_asset_form'", [$auditFloor]);
});

assets_dashboard_assert(function_exists('yovel_admin_assets_maintenance_dashboard_data'), 'Assets live dashboard provider is missing.');
$providerReflection = new ReflectionFunction('yovel_admin_assets_maintenance_dashboard_data');
assets_dashboard_assert($providerReflection->getNumberOfRequiredParameters() === 2, 'Assets dashboard provider must require company and administrator scope.');
assets_dashboard_assert($providerReflection->getNumberOfParameters() === 2, 'Assets dashboard provider signature must remain exactly two arguments.');
assets_dashboard_assert(yovel_admin_assets_maintenance_section('unknown') === 'dashboard', 'Unknown Assets sections must resolve to the dashboard.');

yovel_admin_assets_maintenance_schema();
$assetSchema = yovel_admin_assets_default_form_schemas()['ASSET'];
$published = yovel_admin_assets_save_form($primary['company'], $primary['admin'], [
    'target_section' => 'asset-records',
    'record_type' => 'ASSET',
    'form_title' => 'Dashboard published form',
    'form_description' => 'Published form for live dashboard evidence.',
    'schema_json' => yovel_admin_assets_form_json($assetSchema),
]);
$published = yovel_admin_assets_publish_form($primary['company'], $primary['admin'], (string) $published['form_key']);
yovel_admin_assets_save_form_submission($primary['company'], $primary['admin'], [
    'form_key' => (string) $published['form_key'],
    'subject_owner_key' => 'inventory:item/dashboard-owner-001',
    'values_json' => '{}',
]);
yovel_admin_assets_save_form($primary['company'], $primary['admin'], [
    'target_section' => 'maintenance-schedules',
    'record_type' => 'MAINTENANCE_SCHEDULE',
    'form_title' => 'Dashboard draft form',
    'form_description' => 'Draft form waiting for review.',
    'schema_json' => yovel_admin_assets_form_json(yovel_admin_assets_default_form_schemas()['MAINTENANCE_SCHEDULE']),
]);

$dependencyProviders = [
    'inventory-warehouse.catalogue.v1' => static fn (array $company): array => [
        'contract' => 'inventory-warehouse.catalogue.v1',
        'company_key_hash' => (string) $company['company_key_hash'],
        'records' => [['item_key' => 'inventory:item/health-check']],
    ],
    'hr.workforce.v1' => static fn (array $company): array => [
        'contract' => 'hr.workforce.v1',
        'company_key_hash' => (string) $company['company_key_hash'],
        'records' => [['employee_key' => 'hr:employee/health-check']],
    ],
    'accounting-finance.asset-posting.v1' => static fn (array $company): array => [
        'contract' => 'accounting-finance.asset-posting.v1',
        'owner' => 'accounting-finance',
        'owner_function' => 'yovel_admin_finance_asset_posting_request',
        'transaction_owner' => 'CALLER',
        'operations' => ['DRAFT', 'POST', 'REVERSE'],
    ],
];
$GLOBALS['yovel_admin_assets_dashboard_dependency_providers'] = $dependencyProviders;

try {
    yovel_admin_assets_dashboard_dependency_gateway(['foreign.contract.v1' => static fn (): array => []]);
    throw new RuntimeException('Assets dashboard accepted a dependency outside its allow-list.');
} catch (InvalidArgumentException $error) {
    assets_dashboard_assert(str_contains($error->getMessage(), 'allow-listed'), 'Assets dashboard allow-list rejection is not explicit.');
}

$rowsBefore = [];
foreach ($assetTables as $table) {
    $rowsBefore[$table] = (int) $db->GetOne("SELECT COUNT(*) FROM {$table} WHERE company_key_hash = ?", [$primary['company']['company_key_hash']]);
}
$dashboard = yovel_admin_assets_maintenance_dashboard_data($primary['company'], $primary['admin']);
foreach ($assetTables as $table) {
    assets_dashboard_assert(
        (int) $db->GetOne("SELECT COUNT(*) FROM {$table} WHERE company_key_hash = ?", [$primary['company']['company_key_hash']]) === $rowsBefore[$table],
        'Assets dashboard read mutated ' . $table . '.'
    );
}

foreach (['summary', 'queue', 'activity', 'setup', 'alerts', 'shortcuts', 'directories', 'dependencies'] as $envelopeKey) {
    assets_dashboard_assert(is_array($dashboard[$envelopeKey] ?? null), 'Assets dashboard envelope is missing ' . $envelopeKey . '.');
}
$summary = array_column($dashboard['summary'], null, 'key');
assets_dashboard_assert((int) ($summary['forms']['value'] ?? -1) === 2 && ($summary['forms']['availability'] ?? '') === 'AVAILABLE', 'Assets dashboard form total is not live.');
assets_dashboard_assert((int) ($summary['published-forms']['value'] ?? -1) === 1, 'Assets dashboard published form total is incorrect.');
assets_dashboard_assert((int) ($summary['submitted-records']['value'] ?? -1) === 1, 'Assets dashboard submitted record total is incorrect.');
assets_dashboard_assert(($summary['active-assets']['availability'] ?? '') === 'AVAILABLE' && (int) ($summary['active-assets']['value'] ?? -1) === 0, 'Implemented Asset register metric is not live.');
foreach (['maintenance-due', 'open-work-orders'] as $key) {
    assets_dashboard_assert(($summary[$key]['availability'] ?? '') === 'UNAVAILABLE_DEPENDENCY', 'Assets operational KPI fabricated availability: ' . $key);
    assets_dashboard_assert(($summary[$key]['value'] ?? null) === null, 'Unavailable Assets KPI must not fabricate a numeric zero: ' . $key);
}

$dependencies = array_column($dashboard['dependencies'], null, 'contract');
assets_dashboard_assert(($dependencies['inventory-warehouse.catalogue.v1']['status'] ?? '') === 'AVAILABLE', 'Inventory dashboard dependency health is not available.');
assets_dashboard_assert(($dependencies['hr.workforce.v1']['status'] ?? '') === 'AVAILABLE', 'HR dashboard dependency health is not available.');
assets_dashboard_assert(($dependencies['accounting-finance.asset-posting.v1']['status'] ?? '') === 'AVAILABLE', 'Accepted Finance Asset posting dependency is not available.');

$setup = array_column($dashboard['setup'], null, 'key');
assets_dashboard_assert(($setup['foundation']['complete'] ?? false) === true, 'Assets foundation setup state is incomplete.');
assets_dashboard_assert(($setup['publish-form']['complete'] ?? false) === true, 'Assets published-form setup state is incomplete.');
assets_dashboard_assert(($setup['submit-record']['complete'] ?? false) === true, 'Assets submitted-record setup state is incomplete.');
assets_dashboard_assert(count($dashboard['activity']) > 0 && count($dashboard['activity']) <= 8, 'Assets recent activity must be populated and bounded.');
assets_dashboard_assert(in_array('DRAFT', array_column($dashboard['queue'], 'status'), true), 'Assets draft Form Builder queue is missing.');
assets_dashboard_assert(in_array('UNAVAILABLE_DEPENDENCY', array_column($dashboard['queue'], 'status'), true), 'Assets unavailable maintenance package state is missing.');
assets_dashboard_assert(!in_array('Asset posting command: Owner command health check failed safely.', array_column($dashboard['alerts'], 'label'), true), 'Available Finance posting contract produced a false alert.');
assets_dashboard_assert(in_array('form-builder', array_column($dashboard['shortcuts'], 'key'), true), 'Assets Form Builder shortcut is missing.');
assets_dashboard_assert((array_column($dashboard['shortcuts'], null, 'key')['depreciation']['availability'] ?? '') === 'AVAILABLE', 'Implemented depreciation shortcut is not available.');
assets_dashboard_assert(!in_array('UNAVAILABLE_PACKAGE', array_column($dashboard['queue'], 'status'), true), 'Implemented depreciation package remains falsely unavailable.');
assets_dashboard_assert(count($dashboard['directories']) >= 2, 'Assets reports and masters directories are incomplete.');

$otherDashboard = yovel_admin_assets_maintenance_dashboard_data($other['company'], $other['admin']);
$otherSummary = array_column($otherDashboard['summary'], null, 'key');
assets_dashboard_assert((int) ($otherSummary['forms']['value'] ?? -1) === 0, 'Assets dashboard leaked forms across companies.');
assets_dashboard_assert((int) ($otherSummary['submitted-records']['value'] ?? -1) === 0, 'Assets dashboard leaked submissions across companies.');
assets_dashboard_assert($otherDashboard['activity'] === [], 'Assets dashboard leaked activity across companies.');

$GLOBALS['yovel_admin_assets_dashboard_dependency_providers'] = [
    'inventory-warehouse.catalogue.v1' => static function (): array {
        throw new RuntimeException('Foreign SQL details must not escape.');
    },
    'hr.workforce.v1' => $dependencyProviders['hr.workforce.v1'],
];
$degraded = yovel_admin_assets_maintenance_dashboard_data($primary['company'], $primary['admin']);
$degradedDependencies = array_column($degraded['dependencies'], null, 'contract');
assets_dashboard_assert(($degradedDependencies['inventory-warehouse.catalogue.v1']['status'] ?? '') === 'ERROR', 'Assets dependency errors must be bounded.');
assets_dashboard_assert(!str_contains(json_encode($degraded, JSON_THROW_ON_ERROR), 'Foreign SQL details'), 'Assets dashboard exposed dependency exception details.');
assets_dashboard_assert((int) (array_column($degraded['summary'], null, 'key')['forms']['value'] ?? -1) === 2, 'One dependency error prevented Assets-owned dashboard data from rendering.');
$GLOBALS['yovel_admin_assets_dashboard_dependency_providers'] = $dependencyProviders;

$activeModuleSection = 'dashboard';
$activeModuleData = yovel_admin_assets_maintenance_data($primary['company'], $primary['admin'], 'dashboard');
$activeModuleFormState = [];
$companyName = $primary['company']['company_name'];
$companySlug = $primary['company']['company_slug'];
ob_start();
require $root . '/company/admin/modules/assets-maintenance/views/workspace.php';
$markup = (string) ob_get_clean();

foreach ([
    'data-assets-dashboard',
    'data-assets-dashboard-summary',
    'data-assets-dashboard-queue',
    'data-assets-dashboard-activity',
    'data-assets-dashboard-shortcuts',
    'data-assets-dashboard-directories',
    'data-assets-dashboard-setup',
    'data-assets-dashboard-alerts',
    'data-assets-dashboard-dependencies',
    'data-assets-tour-start',
    'data-assets-tour-dialog',
] as $marker) {
    assets_dashboard_assert(str_contains($markup, $marker), 'Assets dashboard markup is missing ' . $marker . '.');
}
assets_dashboard_assert(substr_count($markup, 'data-assets-tour-step=') >= 4 && substr_count($markup, 'data-assets-tour-step=') <= 7, 'Assets guided tour must contain four to seven steps.');
assets_dashboard_assert(str_contains($markup, 'role="dialog"') && str_contains($markup, 'aria-modal="true"'), 'Assets guided tour dialog is not accessible.');
assets_dashboard_assert(str_contains($markup, 'localStorage') && str_contains($markup, 'assets-maintenance'), 'Assets tour completion is not stored per company and module.');
assets_dashboard_assert(str_contains($markup, 'UNAVAILABLE_DEPENDENCY'), 'Assets dashboard does not render explicit unavailable states.');
assets_dashboard_assert(str_contains($markup, 'Form Builder'), 'Assets dashboard does not expose Form Builder.');
assets_dashboard_assert(str_contains($markup, 'minmax(0, 12fr) minmax(16rem, 8fr)'), 'Assets dashboard lost the 12/8 shell.');
assets_dashboard_assert(strpos($markup, 'data-assets-main') < strpos($markup, 'data-assets-tools'), 'Assets dashboard must stack main-first on mobile.');

$dashboardSource = (string) file_get_contents($root . '/company/admin/modules/assets-maintenance/dashboard.php');
foreach (['project_company_inventory_', 'project_company_hr_', 'project_company_finance_', 'project_company_accounting_'] as $foreignPrefix) {
    assets_dashboard_assert(!str_contains($dashboardSource, $foreignPrefix), 'Assets dashboard directly references a foreign owner table: ' . $foreignPrefix);
}
assets_dashboard_assert(!str_contains($dashboardSource, 'CREATE TABLE'), 'Assets dashboard reads must not create schema.');
assets_dashboard_assert(!str_contains($dashboardSource, '$_POST'), 'Assets dashboard provider must not read request globals.');

echo "Assets / Maintenance live dashboard checks passed.\n";
