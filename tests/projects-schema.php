<?php
declare(strict_types=1);

require __DIR__ . '/projects-test-helper.php';

$route = yovel_admin_module_route('projects');
projects_assert(is_array($route), 'Projects is not registered.');
projects_assert(($route['default_section'] ?? '') === 'projects', 'Projects default section changed.');
projects_assert(($route['action_provider'] ?? '') === 'yovel_admin_projects_handle_post', 'Projects action provider changed.');

foreach ([
    'yovel_admin_projects_sections',
    'yovel_admin_projects_schema',
    'yovel_admin_projects_scope',
    'yovel_admin_projects_data',
    'yovel_admin_projects_handle_post',
    'yovel_admin_projects_in_transaction',
    'yovel_admin_projects_assert_readback',
] as $function) {
    projects_assert(function_exists($function), 'Projects foundation function is missing: ' . $function);
}

$handler = new ReflectionFunction('yovel_admin_projects_handle_post');
projects_assert($handler->getNumberOfRequiredParameters() === 4, 'Projects handler must require company, admin, action, and input.');
projects_assert($handler->getNumberOfParameters() === 4, 'Projects handler signature must remain exactly four arguments.');
$dataProvider = new ReflectionFunction('yovel_admin_projects_data');
projects_assert($dataProvider->getNumberOfRequiredParameters() === 2, 'Projects data provider must support the registry two-argument call.');
projects_assert($dataProvider->getNumberOfParameters() === 3, 'Projects data provider may expose only one optional section argument.');

yovel_admin_projects_schema();
yovel_admin_projects_schema();
$db = bx_db();
$expectedTables = projects_table_names();
$actualTables = $db->GetCol(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE 'project_company_project_%'
     ORDER BY TABLE_NAME",
    [BUILDERX_DB_NAME]
);
projects_assert(is_array($actualTables), 'Projects schema table discovery failed.');
sort($actualTables);
$sortedExpected = $expectedTables;
sort($sortedExpected);
projects_assert($actualTables === $sortedExpected, 'Projects must own exactly the approved 13 tables.');

foreach ($expectedTables as $table) {
    $columns = $db->GetAssoc(
        'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    projects_assert(isset($columns['company_key'], $columns['company_key_hash']), $table . ' is missing company scope columns.');
    projects_assert(str_starts_with(strtolower((string) $columns['company_key']), 'varchar(1500)'), $table . ' does not support bounded opaque company keys.');
    projects_assert(str_starts_with(strtolower((string) $columns['company_key_hash']), 'char(64)'), $table . ' does not store a 64-hex company hash.');
}

$scope = projects_test_scope();
$company = $scope['company'];
$admin = $scope['admin'];
register_shutdown_function(static fn () => projects_cleanup($company, $admin));

projects_assert(
    yovel_admin_projects_scope($company, $admin) === [
        $company['company_key'],
        $company['company_key_hash'],
        $admin['admin_key'],
    ],
    'Projects scope did not return the exact opaque key, hash, and active administrator key.'
);

$badHash = $company;
$badHash['company_key_hash'] = str_repeat('0', 64);
projects_expect_exception(static fn () => yovel_admin_projects_scope($badHash, $admin), 'scope');
$wrongDerivedHash = $company;
$wrongDerivedHash['company_key'] .= '-changed';
projects_expect_exception(static fn () => yovel_admin_projects_scope($wrongDerivedHash, $admin), 'scope');
$tooLong = $company;
$tooLong['company_key'] = str_repeat('x', 1501);
$tooLong['company_key_hash'] = hash('sha256', $tooLong['company_key']);
projects_expect_exception(static fn () => yovel_admin_projects_scope($tooLong, $admin), 'scope');

$db->Execute(
    "UPDATE project_company_admin SET admin_status = 'INACTIVE' WHERE admin_key = ? AND company_key_hash = ?",
    [$admin['admin_key'], $company['company_key_hash']]
);
projects_expect_exception(static fn () => yovel_admin_projects_scope($company, $admin), 'authorized');
$db->Execute(
    "UPDATE project_company_admin SET admin_status = 'ACTIVE' WHERE admin_key = ? AND company_key_hash = ?",
    [$admin['admin_key'], $company['company_key_hash']]
);

$rollbackKey = bx_uuid();
projects_expect_exception(
    static function () use ($company, $admin, $rollbackKey): void {
        yovel_admin_projects_in_transaction(
            static function (ADOConnection $transactionDb) use ($company, $admin, $rollbackKey): void {
                yovel_admin_projects_db_execute(
                    $transactionDb,
                    "INSERT INTO project_company_project_type (
                        project_type_key, company_key, company_key_hash, project_type_code,
                        project_type_name, project_type_status, created_by_admin_key, updated_by_admin_key
                     ) VALUES (?, ?, ?, 'ROLLBACK', 'Rollback', 'ACTIVE', ?, ?)",
                    [$rollbackKey, $company['company_key'], $company['company_key_hash'], $admin['admin_key'], $admin['admin_key']],
                    'Projects rollback fixture'
                );
                throw new RuntimeException('forced rollback');
            }
        );
    },
    'forced rollback'
);
projects_assert(
    (int) $db->GetOne('SELECT COUNT(*) FROM project_company_project_type WHERE project_type_key = ?', [$rollbackKey]) === 0,
    'Projects transaction failure did not roll back its write.'
);

$source = '';
foreach (glob(dirname(__DIR__) . '/company/admin/modules/projects/*.php') ?: [] as $path) {
    $source .= (string) file_get_contents($path);
}
foreach ([
    'project_company_hr_',
    'project_company_finance_',
    'project_company_sales_',
    'project_company_buying_',
    'project_company_inventory_',
    'project_company_operations_',
    'project_company_support_',
] as $forbiddenPrefix) {
    projects_assert(!str_contains($source, $forbiddenPrefix), 'Projects directly references a foreign table prefix: ' . $forbiddenPrefix);
}

echo "Projects Task 1 schema and foundation checks passed.\n";

