<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$requiredFiles = [
    'company/admin/index.php',
    'company/admin/bootstrap/app.php',
    'company/admin/bootstrap/controller.php',
    'company/admin/core/functions.php',
    'company/admin/views/layout.php',
    'company/admin/views/dashboard.php',
    'company/admin/views/partials/scripts.php',
    'company/admin/assets/css/admin.css',
    'company/admin/modules/platform/functions.php',
    'company/admin/modules/platform/views/workspace.php',
    'company/admin/modules/hr/functions.php',
    'company/admin/modules/hr/views/workspace.php',
    'company/admin/modules/hr/navigation.php',
    'company/admin/modules/hr/schema.php',
    'company/admin/modules/hr/forms.php',
    'company/admin/modules/hr/submissions.php',
    'company/admin/modules/hr/data.php',
    'company/admin/modules/hr/employees.php',
    'company/admin/modules/hr/departments.php',
    'company/admin/modules/hr/job-positions.php',
    'company/admin/modules/hr/views/dashboard.php',
    'company/admin/modules/hr/views/employee-profiles.php',
    'company/admin/modules/hr/views/departments.php',
    'company/admin/modules/hr/views/job-positions.php',
    'company/admin/modules/hr/views/queued.php',
    'company/admin/modules/sales-crm/functions.php',
    'company/admin/modules/sales-crm/views/workspace.php',
    'company/admin/modules/accounting-finance/functions.php',
    'company/admin/modules/accounting-finance/views/workspace.php',
];

$failures = [];
foreach ($requiredFiles as $relativePath) {
    if (!is_file($root . '/' . $relativePath)) {
        $failures[] = 'Missing required architecture file: ' . $relativePath;
    }
}

$frontControllerPath = $root . '/company/admin/index.php';
$frontController = is_file($frontControllerPath) ? (string) file_get_contents($frontControllerPath) : '';
$frontControllerLines = preg_split('/\R/', trim($frontController)) ?: [];
if (count($frontControllerLines) > 12) {
    $failures[] = 'company/admin/index.php must remain a thin front controller of at most 12 lines.';
}

$forbiddenPatterns = [
    '/\bfunction\s+yovel_admin_/i' => 'function declaration',
    '/\b(?:SELECT|INSERT|UPDATE|DELETE|CREATE TABLE)\b/i' => 'SQL statement',
    '/<style\b/i' => 'inline style block',
    '/<script\b/i' => 'inline script block',
    '/Employee profiles|Job Position Features|Accounting \/ Finance/i' => 'module-specific markup',
];
foreach ($forbiddenPatterns as $pattern => $label) {
    if (preg_match($pattern, $frontController) === 1) {
        $failures[] = 'company/admin/index.php contains a forbidden ' . $label . '.';
    }
}

$functionFiles = [
    'company/admin/core/functions.php',
    'company/admin/modules/platform/functions.php',
    'company/admin/modules/hr/navigation.php',
    'company/admin/modules/hr/schema.php',
    'company/admin/modules/hr/forms.php',
    'company/admin/modules/hr/submissions.php',
    'company/admin/modules/hr/data.php',
    'company/admin/modules/hr/employees.php',
    'company/admin/modules/hr/departments.php',
    'company/admin/modules/hr/job-positions.php',
    'company/admin/modules/sales-crm/functions.php',
    'company/admin/modules/accounting-finance/functions.php',
];
$functionOwners = [];
foreach ($functionFiles as $relativePath) {
    $contents = (string) file_get_contents($root . '/' . $relativePath);
    preg_match_all('/^function\s+(yovel_admin_[a-z0-9_]+)\s*\(/mi', $contents, $matches);
    foreach ($matches[1] as $functionName) {
        if (isset($functionOwners[$functionName])) {
            $failures[] = sprintf(
                'Function %s is declared in both %s and %s.',
                $functionName,
                $functionOwners[$functionName],
                $relativePath
            );
        }
        $functionOwners[$functionName] = $relativePath;
    }
}
if (count($functionOwners) < 100) {
    $failures[] = 'Expected at least 100 extracted company-admin functions; found ' . count($functionOwners) . '.';
}
foreach (['yovel_admin_company', 'yovel_admin_hr_data', 'yovel_admin_save_hr_employee', 'yovel_admin_save_sales_lead', 'yovel_admin_save_accounting_account'] as $functionName) {
    if (!isset($functionOwners[$functionName])) {
        $failures[] = 'Missing required extracted function: ' . $functionName;
    }
}

$coreFunctions = (string) file_get_contents($root . '/company/admin/core/functions.php');
if (!str_contains($coreFunctions, "dirname(__DIR__, 3) . '/frontend/dist/.vite/manifest.json'")) {
    $failures[] = 'The shared frontend manifest path is not rooted from the extracted core directory.';
}

$companyEntrypoint = (string) file_get_contents($root . '/company/yovel-east/admin/index.php');
if (!str_contains($companyEntrypoint, "define('BUILDERX_COMPANY_ADMIN_SLUG', 'yovel-east')")
    || !str_contains($companyEntrypoint, "require __DIR__ . '/../../admin/index.php'")) {
    $failures[] = 'The Yovel East entrypoint no longer follows the shared company-admin contract.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Company admin modular architecture checks passed.\n");
