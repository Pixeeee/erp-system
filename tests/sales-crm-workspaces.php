<?php
declare(strict_types=1);

require_once __DIR__ . '/sales-crm-test-helper.php';

$root = dirname(__DIR__);
sales_crm_assert(is_file($root . '/company/admin/modules/sales-crm/views/settings.php'), 'SC-02 settings modal view is missing.');
sales_crm_assert(function_exists('yovel_admin_sales_crm_workspace'), 'SC-02 workspace service is missing.');

$db = bx_db();
$fixture = sales_crm_create_isolated_company($db);
$workspaceData = yovel_admin_sales_crm_workspace($fixture['company'], $fixture['admin']);
sales_crm_assert(count($workspaceData['setup_steps'] ?? []) >= 4, 'The Sales / CRM setup checklist is incomplete.');
sales_crm_assert(count($workspaceData['shortcuts'] ?? []) >= 1, 'The Sales / CRM shortcuts directory is empty.');
sales_crm_assert(isset($workspaceData['directory']['CRM Masters'], $workspaceData['directory']['Selling Masters'], $workspaceData['directory']['Key Reports']), 'The grouped CRM/Selling directory is incomplete.');

foreach ($workspaceData['shortcuts'] as $shortcut) {
    sales_crm_assert(in_array((string) ($shortcut['scope'] ?? ''), ['crm', 'selling'], true), 'A shortcut is missing its access scope.');
    if (!empty($shortcut['available'])) {
        sales_crm_assert(str_starts_with((string) ($shortcut['href'] ?? ''), './?view=sales-crm&section='), 'An available shortcut has no real Sales / CRM destination.');
    } else {
        sales_crm_assert((string) ($shortcut['href'] ?? '') === '' && (string) ($shortcut['dependency_state'] ?? '') !== '', 'An unavailable shortcut is not explicitly disabled.');
    }
}

$workspaceView = (string) file_get_contents($root . '/company/admin/modules/sales-crm/views/workspace.php');
$settingsView = (string) file_get_contents($root . '/company/admin/modules/sales-crm/views/settings.php');
foreach (['data-sales-crm-setup', 'data-sales-crm-step', 'data-sales-crm-shortcuts', 'data-sales-crm-directory', 'data-sales-crm-tour-open', 'data-sales-crm-tour'] as $marker) {
    sales_crm_assert(str_contains($workspaceView, $marker), 'Workspace markup is missing ' . $marker . '.');
}
sales_crm_assert(!str_contains($workspaceView, 'workspace routed'), 'Placeholder-first workspace copy remains.');
foreach (['data-record-modal', 'data-record-modal-form', 'data-record-modal-close', 'data-confirm-submit', 'data-confirm-submit-action', 'name="expected_version"', 'name="module_view" value="sales-crm"'] as $marker) {
    sales_crm_assert(str_contains($settingsView, $marker), 'Settings modal is missing shared contract marker: ' . $marker);
}
foreach (['CRM Settings', 'Selling Settings', 'Allowed administrators'] as $label) {
    sales_crm_assert(str_contains($settingsView, $label), 'Settings modal is missing ' . $label . '.');
}

echo "Sales / CRM SC-02 workspace checks passed: setup, tour, real destinations, grouped directories, disabled dependencies, and settings modal.\n";
sales_crm_cleanup_isolated_company($db, (string) $fixture['company']['company_key_hash']);
