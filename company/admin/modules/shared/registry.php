<?php
declare(strict_types=1);

/**
 * Stable owner and route metadata for every browser-based ERP workspace.
 *
 * Android is intentionally excluded until its separately approved phase.
 */
function yovel_admin_module_registry(): array
{
    return [
        'hr' => [
            'view' => 'hr',
            'label' => 'HR Department',
            'function_file' => 'modules/hr/functions.php',
            'workspace_file' => 'modules/hr/views/workspace.php',
            'sections_provider' => 'yovel_admin_hr_sections',
            'data_provider' => 'yovel_admin_hr_data',
            'action_provider' => 'yovel_admin_hr_handle_post',
            'default_section' => 'employee-profiles',
            'owner' => 'hr',
        ],
        'accounting-finance' => [
            'view' => 'accounting-finance',
            'label' => 'Accounting / Finance',
            'function_file' => 'modules/accounting-finance/functions.php',
            'workspace_file' => 'modules/accounting-finance/views/workspace.php',
            'sections_provider' => 'yovel_admin_accounting_finance_sections',
            'data_provider' => 'yovel_admin_accounting_finance_data',
            'action_provider' => 'yovel_admin_accounting_finance_handle_post',
            'default_section' => 'dashboard',
            'owner' => 'accounting-finance',
        ],
        'sales-crm' => [
            'view' => 'sales-crm',
            'label' => 'Sales / CRM',
            'function_file' => 'modules/sales-crm/functions.php',
            'workspace_file' => 'modules/sales-crm/views/workspace.php',
            'sections_provider' => 'yovel_admin_sales_crm_sections',
            'data_provider' => 'yovel_admin_sales_crm_data',
            'action_provider' => 'yovel_admin_sales_crm_handle_post',
            'default_section' => 'leads',
            'owner' => 'sales-crm',
        ],
        'buying-procurement' => [
            'view' => 'buying-procurement',
            'label' => 'Buying / Procurement',
            'function_file' => 'modules/buying-procurement/functions.php',
            'workspace_file' => 'modules/buying-procurement/views/workspace.php',
            'sections_provider' => 'yovel_admin_buying_procurement_sections',
            'data_provider' => 'yovel_admin_buying_procurement_data',
            'action_provider' => 'yovel_admin_buying_procurement_handle_post',
            'default_section' => 'suppliers',
            'owner' => 'buying-procurement',
        ],
        'inventory-warehouse' => [
            'view' => 'inventory-warehouse',
            'label' => 'Inventory / Warehouse',
            'function_file' => 'modules/inventory-warehouse/functions.php',
            'workspace_file' => 'modules/inventory-warehouse/views/workspace.php',
            'sections_provider' => 'yovel_admin_inventory_warehouse_sections',
            'data_provider' => 'yovel_admin_inventory_warehouse_data',
            'action_provider' => 'yovel_admin_inventory_warehouse_handle_post',
            'default_section' => 'items',
            'owner' => 'inventory-warehouse',
        ],
        'manufacturing' => [
            'view' => 'manufacturing',
            'label' => 'Manufacturing',
            'function_file' => 'modules/manufacturing/functions.php',
            'workspace_file' => 'modules/manufacturing/views/workspace.php',
            'sections_provider' => 'yovel_admin_manufacturing_sections',
            'data_provider' => 'yovel_admin_manufacturing_data',
            'action_provider' => 'yovel_admin_manufacturing_handle_post',
            'default_section' => 'dashboard',
            'owner' => 'manufacturing',
        ],
        'projects' => [
            'view' => 'projects',
            'label' => 'Projects',
            'function_file' => 'modules/projects/functions.php',
            'workspace_file' => 'modules/projects/views/workspace.php',
            'sections_provider' => 'yovel_admin_projects_sections',
            'data_provider' => 'yovel_admin_projects_data',
            'action_provider' => 'yovel_admin_projects_handle_post',
            'default_section' => 'projects',
            'owner' => 'projects',
        ],
        'support-service' => [
            'view' => 'support-service',
            'label' => 'Support / Service',
            'function_file' => 'modules/support-service/functions.php',
            'workspace_file' => 'modules/support-service/views/workspace.php',
            'sections_provider' => 'yovel_admin_support_service_sections',
            'data_provider' => 'yovel_admin_support_service_data',
            'action_provider' => 'yovel_admin_support_service_handle_post',
            'default_section' => 'issues-tickets',
            'owner' => 'support-service',
        ],
        'assets-maintenance' => [
            'view' => 'assets-maintenance',
            'label' => 'Assets / Maintenance',
            'function_file' => 'modules/assets-maintenance/functions.php',
            'workspace_file' => 'modules/assets-maintenance/views/workspace.php',
            'sections_provider' => 'yovel_admin_assets_maintenance_sections',
            'data_provider' => 'yovel_admin_assets_maintenance_data',
            'action_provider' => 'yovel_admin_assets_maintenance_handle_post',
            'default_section' => 'asset-records',
            'owner' => 'assets-maintenance',
        ],
        'operations' => [
            'view' => 'operations',
            'label' => 'Operations',
            'function_file' => 'modules/operations/functions.php',
            'workspace_file' => 'modules/operations/views/workspace.php',
            'sections_provider' => 'yovel_admin_operations_sections',
            'data_provider' => 'yovel_admin_operations_data',
            'action_provider' => 'yovel_admin_operations_handle_post',
            'default_section' => 'scheduled-jobs',
            'owner' => 'operations',
        ],
        'compliance-localization' => [
            'view' => 'compliance-localization',
            'label' => 'Compliance / Localization',
            'function_file' => 'modules/compliance-localization/functions.php',
            'workspace_file' => 'modules/compliance-localization/views/workspace.php',
            'sections_provider' => 'yovel_admin_compliance_localization_sections',
            'data_provider' => 'yovel_admin_compliance_localization_data',
            'action_provider' => 'yovel_admin_compliance_localization_handle_post',
            'default_section' => 'dashboard',
            'owner' => 'compliance-localization',
        ],
    ];
}

function yovel_admin_module_route(string $view): ?array
{
    $view = strtolower(trim($view));
    $route = yovel_admin_module_registry()[$view] ?? null;

    return is_array($route) ? $route : null;
}

function yovel_admin_module_route_by_label(string $label): ?array
{
    foreach (yovel_admin_module_registry() as $route) {
        if (strcasecmp((string) $route['label'], trim($label)) === 0) {
            return $route;
        }
    }

    return null;
}

function yovel_admin_module_feature_section(array $route, string $feature): string
{
    $feature = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($feature)), '-');
    if (($route['view'] ?? '') === 'accounting-finance' && $feature === 'finance-dashboard') {
        return 'dashboard';
    }

    return $feature !== '' ? $feature : (string) $route['default_section'];
}

function yovel_admin_module_section(array $route, string $requested): string
{
    $section = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($requested)), '-');
    $provider = (string) ($route['sections_provider'] ?? '');
    if ($provider !== '' && function_exists($provider)) {
        $sections = $provider();
        if (is_array($sections) && array_key_exists($section, $sections)) {
            return $section;
        }
    } elseif ($section !== '') {
        return $section;
    }

    return (string) $route['default_section'];
}

function yovel_admin_module_sections(array $route): array
{
    $provider = (string) ($route['sections_provider'] ?? '');
    if ($provider === '' || !function_exists($provider)) {
        return [];
    }

    $sections = $provider();
    return is_array($sections) ? $sections : [];
}

function yovel_admin_module_data(array $route, array $company, array $admin): array
{
    $provider = (string) ($route['data_provider'] ?? '');
    if ($provider === '' || !function_exists($provider)) {
        return [];
    }

    $reflection = new ReflectionFunction($provider);
    $data = $reflection->getNumberOfParameters() >= 2
        ? $provider($company, $admin)
        : $provider($company);

    return is_array($data) ? $data : [];
}

function yovel_admin_module_post_result(
    array $route,
    array $company,
    array $admin,
    string $action,
    array $input
): array {
    $provider = (string) ($route['action_provider'] ?? '');
    if ($provider === '' || !function_exists($provider)) {
        throw new LogicException('The module action provider is unavailable.');
    }

    $reflection = new ReflectionFunction($provider);
    $parameterCount = $reflection->getNumberOfParameters();
    $result = match (true) {
        $parameterCount >= 4 => $provider($company, $admin, $action, $input),
        $parameterCount === 3 => $provider($company, $admin, $action),
        default => $provider($company, $admin),
    };
    if (is_string($result)) {
        $result = ['message' => $result];
    }
    if (!is_array($result)) {
        throw new RuntimeException('The module action provider returned an invalid response.');
    }

    $section = yovel_admin_module_section(
        $route,
        (string) ($result['section'] ?? $input['section'] ?? $route['default_section'] ?? '')
    );
    $query = [];
    foreach (is_array($result['query'] ?? null) ? $result['query'] : [] as $key => $value) {
        $key = strtolower(trim((string) $key));
        if (preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $key) !== 1 || (!is_scalar($value) && $value !== null)) {
            continue;
        }
        $query[$key] = (string) $value;
    }

    return [
        'message' => trim((string) ($result['message'] ?? 'The module record was saved.')),
        'section' => $section,
        'query' => $query,
    ];
}

function yovel_admin_module_rehydration_input(array $input, int $depth = 0): array
{
    if ($depth > 3) {
        return [];
    }

    $safe = [];
    foreach ($input as $key => $value) {
        $key = trim((string) $key);
        if ($key === '' || preg_match('/(?:csrf|password|secret|token)/i', $key) === 1) {
            continue;
        }
        if (is_array($value)) {
            $safe[$key] = yovel_admin_module_rehydration_input($value, $depth + 1);
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $safe[$key] = substr((string) $value, 0, 20000);
        }
    }

    return $safe;
}

function yovel_admin_take_module_form_state(string $view): array
{
    $state = $_SESSION['builderx_module_form_state'][$view] ?? [];
    unset($_SESSION['builderx_module_form_state'][$view]);

    return is_array($state) ? $state : [];
}
