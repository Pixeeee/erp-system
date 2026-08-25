<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/app/foundation.php';
require_once $projectRoot . '/company/admin/core/functions.php';
require_once $projectRoot . '/company/admin/modules/shared/registry.php';

function module_dashboard_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$registry = yovel_admin_module_registry();
module_dashboard_assert(count($registry) === 11, 'The web ERP registry must contain exactly 11 dashboard groups.');
module_dashboard_assert(!isset($registry['mobile-android-stock']), 'Android must remain outside the web dashboard registry.');

foreach ($registry as $view => $route) {
    module_dashboard_assert(
        ($route['default_section'] ?? '') === 'dashboard',
        'Module does not default to dashboard: ' . $view
    );

    $functionFile = $projectRoot . '/company/admin/' . ltrim((string) ($route['function_file'] ?? ''), '/');
    $workspaceFile = $projectRoot . '/company/admin/' . ltrim((string) ($route['workspace_file'] ?? ''), '/');
    module_dashboard_assert(is_file($functionFile), 'Module function entry point is missing: ' . $view);
    module_dashboard_assert(is_file($workspaceFile), 'Module workspace is missing: ' . $view);
    require_once $functionFile;

    $provider = (string) ($route['sections_provider'] ?? '');
    module_dashboard_assert($provider !== '' && function_exists($provider), 'Section provider is unavailable: ' . $view);
    $sections = $provider();
    module_dashboard_assert(is_array($sections) && isset($sections['dashboard']), 'Dashboard section is not registered: ' . $view);
    module_dashboard_assert(
        yovel_admin_module_section($route, 'not-a-real-section') === 'dashboard',
        'Unknown sections do not fall back to dashboard: ' . $view
    );
}

echo "Company admin module dashboard contracts passed.\n";
