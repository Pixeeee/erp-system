<?php
declare(strict_types=1);

require __DIR__ . '/operations-test-helper.php';

$expectedSections = [
    'scheduled-jobs',
    'notifications',
    'background-workers',
    'sync-conflict-dashboard',
    'import-export-jobs',
    'system-alerts',
    'release-checklist',
    'bulk-processing',
    'governed-deletion',
    'authorization-setup',
    'company-defaults',
];
$sections = yovel_admin_operations_sections();
operations_test_assert(array_keys($sections) === $expectedSections, 'Operations sections do not match the registered feature order.');

$reflection = new ReflectionFunction('yovel_admin_operations_data');
operations_test_assert($reflection->getNumberOfRequiredParameters() === 2, 'Operations data provider must require company and admin only.');
operations_test_assert($reflection->getNumberOfParameters() === 3, 'Operations data provider must expose optional providers as its third argument.');

$company = operations_test_company();
$admin = operations_test_admin();
operations_test_register_cleanup($company);
$data = yovel_admin_operations_data($company, $admin);
operations_test_assert(($data['company_key_hash'] ?? '') === $company['company_key_hash'], 'Operations data is not company scoped.');
operations_test_assert(isset($data['formBuilder'], $data['workspace'], $data['metrics']), 'Operations data is missing foundation payloads.');

$activeModuleSection = 'scheduled-jobs';
$activeModuleMeta = $sections[$activeModuleSection];
$activeModuleData = $data;
$companyName = (string) $company['company_name'];

ob_start();
require dirname(__DIR__) . '/company/admin/modules/operations/views/workspace.php';
$markup = (string) ob_get_clean();

foreach ([
    'data-operations-workspace',
    'data-operations-setup-card',
    'data-operations-tour-start',
    'Show Tour',
    'xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]',
    'data-operations-main-panel',
    'data-operations-tools-panel',
    'data-record-modal-open=',
    'data-record-modal',
    'data-confirm-submit',
    'Form Builder',
] as $marker) {
    operations_test_assert(str_contains($markup, $marker), 'Operations workspace is missing marker: ' . $marker);
}
operations_test_assert(!str_contains($markup, 'data-confirm-dialog'), 'Operations duplicated the shared confirmation dialog.');
operations_test_assert(!str_contains($markup, 'ERPNext'), 'Operations workspace contains upstream product branding.');

$workspaceSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/workspace.php');
$modalSource = (string) file_get_contents(dirname(__DIR__) . '/company/admin/modules/operations/views/record-modal.php');
operations_test_assert(str_contains($modalSource, '/views/partials/record-modal.php'), 'Operations record modal does not consume the shared modal partial.');
operations_test_assert(!str_contains($modalSource, 'data-confirm-dialog'), 'Operations record modal duplicates confirmation infrastructure.');
operations_test_assert(!str_contains($workspaceSource, '<section') || !str_contains($workspaceSource, '<section class="') || !str_contains($workspaceSource, '<section class="card'), 'Operations workspace introduced a nested Card class hierarchy.');

foreach ($expectedSections as $section) {
    operations_test_assert(
        str_contains($markup, 'view=operations&amp;section=' . $section),
        'Operations workspace shortcut is missing a real route for ' . $section . '.'
    );
}

echo "Operations workspace checks passed.\n";
