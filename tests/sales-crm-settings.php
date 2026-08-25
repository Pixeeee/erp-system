<?php
declare(strict_types=1);

require_once __DIR__ . '/sales-crm-test-helper.php';

$root = dirname(__DIR__);
sales_crm_assert(is_file($root . '/company/admin/modules/sales-crm/crm-settings.php'), 'SC-02 CRM settings service is missing.');
foreach ([
    'yovel_admin_sales_crm_settings',
    'yovel_admin_save_sales_crm_settings',
    'yovel_admin_sales_crm_preference',
    'yovel_admin_save_sales_crm_preference',
    'yovel_admin_sales_crm_workspace',
    'yovel_admin_sales_crm_handle_post',
] as $function) {
    sales_crm_assert(function_exists($function), 'SC-02 public contract is missing: ' . $function);
}

yovel_admin_sales_crm_schema();
$db = bx_db();
foreach (['project_company_sales_crm_settings', 'project_company_sales_crm_allowed_user', 'project_company_sales_crm_preference'] as $table) {
    sales_crm_assert((int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    ) === 1, 'Missing SC-02 table: ' . $table);
}

$fixture = sales_crm_create_isolated_company($db);
$company = $fixture['company'];
$admin = $fixture['admin'];
$companyHash = (string) $company['company_key_hash'];
$testAdmin = sales_crm_create_test_admin($db, $company);
$settingsKey = '';
$preferenceKey = '';

sales_crm_cleanup_sc02($db, $companyHash, [], '');
try {
    $defaults = yovel_admin_sales_crm_settings($company, $admin);
    sales_crm_assert((int) $defaults['settings_version'] === 0, 'New companies must begin at settings version zero.');
    sales_crm_assert((int) $defaults['crm_enabled'] === 1 && (int) $defaults['selling_enabled'] === 1, 'CRM and Selling must default to enabled.');

    $created = yovel_admin_save_sales_crm_settings($company, $admin, [
        'expected_version' => '0',
        'crm_enabled' => '1',
        'selling_enabled' => '1',
        'restrict_to_allowed_users' => '1',
        'default_lead_status' => 'QUALIFIED',
        'default_opportunity_stage' => 'Discovery',
        'default_customer_group' => 'Commercial',
        'default_price_list' => 'Standard Selling',
        'allow_duplicate_lead_email' => '1',
        'validate_selling_price' => '1',
        'crm_user_keys' => [$admin['admin_key'], $testAdmin['admin_key']],
        'selling_user_keys' => [$admin['admin_key']],
        'settings_manager_keys' => [$admin['admin_key']],
    ]);
    sales_crm_assert($created === 'Sales / CRM settings saved.', 'Settings create returned an unexpected message.');
    $saved = yovel_admin_sales_crm_settings($company, $admin);
    $settingsKey = (string) $saved['settings_key'];
    sales_crm_assert((int) $saved['settings_version'] === 1, 'Settings create did not advance the version.');
    sales_crm_assert((string) $saved['default_lead_status'] === 'QUALIFIED', 'Settings create did not read back the Lead status.');
    sales_crm_assert((string) $saved['default_opportunity_stage'] === 'Discovery', 'Settings create did not read back the opportunity stage.');
    sales_crm_assert((int) $saved['allow_duplicate_lead_email'] === 1, 'Settings create did not read back the duplicate-email policy.');
    sales_crm_assert(count($saved['allowed_users']) === 2, 'Allowed-user rows were not read back exactly.');
    sales_crm_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_sales_crm_settings' AND record_key = ? AND action = 'CREATE'", [$settingsKey]) === 1, 'Settings create audit did not commit.');

    $updated = yovel_admin_save_sales_crm_settings($company, $admin, [
        'expected_version' => '1',
        'crm_enabled' => '1',
        'selling_enabled' => '1',
        'restrict_to_allowed_users' => '1',
        'default_lead_status' => 'OPEN',
        'default_opportunity_stage' => 'Proposal',
        'default_customer_group' => 'Commercial',
        'default_price_list' => 'Standard Selling',
        'crm_user_keys' => [$admin['admin_key'], $testAdmin['admin_key']],
        'selling_user_keys' => [$admin['admin_key']],
        'settings_manager_keys' => [$admin['admin_key']],
    ]);
    sales_crm_assert($updated === 'Sales / CRM settings saved.', 'Settings update returned an unexpected message.');
    $saved = yovel_admin_sales_crm_settings($company, $admin);
    sales_crm_assert((string) $saved['settings_key'] === $settingsKey && (int) $saved['settings_version'] === 2, 'Settings update changed the stable key or version incorrectly.');
    sales_crm_assert((int) $saved['selling_enabled'] === 1 && (string) $saved['default_opportunity_stage'] === 'Proposal', 'Settings update did not read back exact values.');

    $staleRejected = false;
    try {
        yovel_admin_save_sales_crm_settings($company, $admin, ['expected_version' => '1']);
    } catch (RuntimeException $error) {
        $staleRejected = str_contains($error->getMessage(), 'changed since');
    }
    sales_crm_assert($staleRejected, 'A stale settings version was not rejected.');
    sales_crm_assert((int) yovel_admin_sales_crm_settings($company, $admin)['settings_version'] === 2, 'A stale save changed settings.');

    $wrongCompanyAdmin = $admin;
    $wrongCompanyAdmin['admin_key'] = bx_uuid();
    $unauthorizedRejected = false;
    try {
        yovel_admin_save_sales_crm_settings($company, $wrongCompanyAdmin, ['expected_version' => '2']);
    } catch (InvalidArgumentException) {
        $unauthorizedRejected = true;
    }
    sales_crm_assert($unauthorizedRejected, 'An unauthorized administrator could write Sales / CRM settings.');

    $foreignAllowedUserRejected = false;
    try {
        yovel_admin_save_sales_crm_settings($company, $admin, [
            'expected_version' => '2',
            'restrict_to_allowed_users' => '1',
            'crm_user_keys' => [bx_uuid()],
            'settings_manager_keys' => [$admin['admin_key']],
        ]);
    } catch (InvalidArgumentException) {
        $foreignAllowedUserRejected = true;
    }
    sales_crm_assert($foreignAllowedUserRejected, 'A foreign allowed-user key was accepted.');

    $rollbackThrown = false;
    try {
        yovel_admin_save_sales_crm_settings($company, $admin, [
            'expected_version' => '2',
            'crm_enabled' => '0',
            'selling_enabled' => '1',
            'restrict_to_allowed_users' => '1',
            'crm_user_keys' => [$admin['admin_key']],
            'selling_user_keys' => [$admin['admin_key']],
            'settings_manager_keys' => [$admin['admin_key']],
        ], static function (): void {
            throw new RuntimeException('SC-02 rollback injection.');
        });
    } catch (RuntimeException $error) {
        $rollbackThrown = $error->getMessage() === 'SC-02 rollback injection.';
    }
    sales_crm_assert($rollbackThrown, 'The settings rollback injection did not execute.');
    $afterRollback = yovel_admin_sales_crm_settings($company, $admin);
    sales_crm_assert((int) $afterRollback['settings_version'] === 2 && (int) $afterRollback['crm_enabled'] === 1 && (int) $afterRollback['selling_enabled'] === 1, 'A failed settings transaction survived rollback.');

    $crmOnlyWorkspace = yovel_admin_sales_crm_workspace($company, $testAdmin);
    sales_crm_assert(($crmOnlyWorkspace['access']['crm'] ?? false) === true, 'CRM allowed-user access was not granted.');
    sales_crm_assert(($crmOnlyWorkspace['access']['selling'] ?? true) === false, 'Selling access was not role-filtered.');
    sales_crm_assert(!in_array('sales-orders', array_column($crmOnlyWorkspace['shortcuts'], 'section'), true), 'Selling shortcuts leaked to a CRM-only administrator.');
    $sellingWriteRejected = false;
    try {
        yovel_admin_sales_crm_require_scope($company, $testAdmin, 'selling');
    } catch (InvalidArgumentException) {
        $sellingWriteRejected = true;
    }
    sales_crm_assert($sellingWriteRejected, 'A CRM-only administrator passed Selling write authorization.');

    $preferenceMessage = yovel_admin_save_sales_crm_preference($company, $admin, [
        'expected_version' => '0',
        'tour_status' => 'DISMISSED',
        'setup_dismissed' => '1',
    ]);
    sales_crm_assert($preferenceMessage === 'Sales / CRM workspace preference saved.', 'Tour preference create returned an unexpected message.');
    $preference = yovel_admin_sales_crm_preference($company, $admin);
    $preferenceKey = (string) $preference['preference_key'];
    sales_crm_assert((int) $preference['preference_version'] === 1 && (string) $preference['tour_status'] === 'DISMISSED', 'Dismissed tour state was not persisted.');
    yovel_admin_save_sales_crm_preference($company, $admin, [
        'expected_version' => '1',
        'tour_status' => 'COMPLETED',
        'setup_dismissed' => '0',
    ]);
    $preference = yovel_admin_sales_crm_preference($company, $admin);
    sales_crm_assert((int) $preference['preference_version'] === 2 && (string) $preference['tour_status'] === 'COMPLETED', 'Completed tour state was not persisted.');

    echo "Sales / CRM SC-02 settings checks passed: authorized versioned writes, role filtering, audits, exact read-back, rollback, and preferences.\n";
} finally {
    sales_crm_cleanup_sc02($db, $companyHash, [$settingsKey, $preferenceKey], (string) $testAdmin['admin_key']);
    sales_crm_cleanup_isolated_company($db, $companyHash);
}
