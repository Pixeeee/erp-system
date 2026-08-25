<?php
declare(strict_types=1);

require __DIR__ . '/manufacturing-test-helper.php';

$expected = [
    'dashboard', 'boms', 'operations', 'workstations', 'production-plans', 'material-requirements',
    'forecasts', 'work-orders', 'job-cards', 'quality', 'subcontracting', 'reports', 'settings', 'form-builder',
];
manufacturing_test_assert(array_keys(yovel_admin_manufacturing_sections()) === $expected, 'Manufacturing must expose the approved 14-section foundation in stable order.');
manufacturing_test_assert(yovel_admin_manufacturing_section('BOM') === 'boms', 'BOM alias must resolve to the boms section.');
manufacturing_test_assert(yovel_admin_manufacturing_section('not-real') === 'dashboard', 'Unknown Manufacturing sections must resolve to dashboard.');

$route = yovel_admin_module_route('manufacturing');
manufacturing_test_assert(is_array($route), 'Manufacturing shared route is missing.');
manufacturing_test_assert(($route['action_provider'] ?? '') === 'yovel_admin_manufacturing_handle_post', 'Manufacturing action provider changed.');

$scope = manufacturing_test_create_scope('workspace');
try {
    $data = yovel_admin_manufacturing_data($scope['company'], $scope['admin'], 'dashboard');
    foreach (['section', 'records', 'summary', 'filters', 'form_schema', 'feedback', 'rehydration'] as $key) {
        manufacturing_test_assert(array_key_exists($key, $data), 'Manufacturing workspace data is missing ' . $key . '.');
    }
    $markup = manufacturing_test_render($scope, 'dashboard');
    manufacturing_test_assert(str_contains($markup, 'data-manufacturing-workspace'), 'Manufacturing workspace marker is missing.');
    manufacturing_test_assert(str_contains($markup, 'minmax(0, 12fr) minmax(16rem, 8fr)'), 'Manufacturing workspace must use a stable 12/8 grid.');
    manufacturing_test_assert(str_contains($markup, '@media (min-width:1024px)') && !str_contains($markup, 'style="grid-template-columns'), 'Manufacturing 12/8 panels must stack below the desktop breakpoint.');
    manufacturing_test_assert(str_contains($markup, 'data-manufacturing-main') && str_contains($markup, 'data-grid-span="12"'), 'Manufacturing main panel is not 12/20.');
    manufacturing_test_assert(str_contains($markup, 'data-manufacturing-tools') && str_contains($markup, 'data-grid-span="8"'), 'Manufacturing tools panel is not 8/20.');
    manufacturing_test_assert(strpos($markup, 'data-manufacturing-main') < strpos($markup, 'data-manufacturing-tools'), 'Manufacturing main panel must precede tools for mobile stacking.');
    manufacturing_test_assert(str_contains($markup, 'Setup checklist'), 'Manufacturing dashboard setup checklist is missing.');
    manufacturing_test_assert(str_contains($markup, 'Shortcuts'), 'Manufacturing dashboard shortcuts are missing.');
    manufacturing_test_assert(str_contains($markup, 'Reports and masters'), 'Manufacturing reports/masters directory is missing.');

    preg_match_all('/<button\b([^>]*)>(.*?)<\/button>/si', $markup, $buttons, PREG_SET_ORDER);
    foreach ($buttons as $button) {
        if (preg_match('/\b(Add|New|Create|Insert)\b/i', strip_tags((string) $button[2])) === 1) {
            manufacturing_test_assert(str_contains((string) $button[1], 'data-record-modal-open='), 'Every Manufacturing creation command must open a modal.');
        }
    }

    foreach ($expected as $section) {
        $sectionData = yovel_admin_manufacturing_data($scope['company'], $scope['admin'], $section);
        manufacturing_test_assert(($sectionData['section'] ?? '') === $section, 'Manufacturing section data did not resolve: ' . $section);
    }
} finally {
    manufacturing_test_cleanup_scope($scope);
}

echo "Manufacturing workspace tests passed.\n";
