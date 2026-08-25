<?php
declare(strict_types=1);

function yovel_admin_manufacturing_sections(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'record_type' => '', 'package' => 'WP-01'],
        'boms' => ['label' => 'BOM', 'icon' => 'account_tree', 'record_type' => 'BOM', 'package' => 'WP-02'],
        'operations' => ['label' => 'Operations', 'icon' => 'precision_manufacturing', 'record_type' => 'OPERATION', 'package' => 'WP-03'],
        'workstations' => ['label' => 'Workstations', 'icon' => 'factory', 'record_type' => 'WORKSTATION', 'package' => 'WP-03'],
        'production-plans' => ['label' => 'Production plans', 'icon' => 'event_note', 'record_type' => 'PRODUCTION_PLAN', 'package' => 'WP-04'],
        'material-requirements' => ['label' => 'Material requirements', 'icon' => 'inventory', 'record_type' => 'MATERIAL_REQUIREMENT', 'package' => 'WP-04'],
        'forecasts' => ['label' => 'Forecasts', 'icon' => 'trending_up', 'record_type' => 'FORECAST', 'package' => 'WP-04'],
        'work-orders' => ['label' => 'Work orders', 'icon' => 'assignment', 'record_type' => 'WORK_ORDER', 'package' => 'WP-05'],
        'job-cards' => ['label' => 'Job cards', 'icon' => 'badge', 'record_type' => 'JOB_CARD', 'package' => 'WP-05'],
        'quality' => ['label' => 'Quality', 'icon' => 'verified', 'record_type' => 'QUALITY', 'package' => 'WP-06'],
        'subcontracting' => ['label' => 'Subcontracting', 'icon' => 'handshake', 'record_type' => 'SUBCONTRACTING', 'package' => 'WP-07'],
        'reports' => ['label' => 'Reports', 'icon' => 'analytics', 'record_type' => 'REPORT_FILTER', 'package' => 'WP-01'],
        'settings' => ['label' => 'Settings', 'icon' => 'settings', 'record_type' => 'MANUFACTURING_SETTINGS', 'package' => 'WP-01'],
        'form-builder' => ['label' => 'Form Builder', 'icon' => 'dynamic_form', 'record_type' => '', 'package' => 'WP-01'],
    ];
}

function yovel_admin_manufacturing_section(?string $requested = null): string
{
    $requested ??= (string) ($_GET['section'] ?? 'dashboard');
    $section = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($requested)), '-');
    if ($section === 'bom') {
        $section = 'boms';
    }
    return array_key_exists($section, yovel_admin_manufacturing_sections()) ? $section : 'dashboard';
}

function yovel_admin_manufacturing_workspace_data(string $section): array
{
    $section = yovel_admin_manufacturing_section($section);
    $metadata = yovel_admin_manufacturing_sections()[$section];
    $implemented = in_array($section, ['dashboard', 'boms', 'operations', 'workstations', 'reports', 'settings', 'form-builder'], true);
    return [
        'section' => $section,
        'records' => [],
        'summary' => ['record_count' => 0, 'package' => (string) $metadata['package']],
        'filters' => [],
        'form_schema' => [],
        'feedback' => [],
        'rehydration' => [],
        'state' => $implemented
            ? ['kind' => 'ready', 'title' => (string) $metadata['label'], 'message' => '', 'dependencies' => []]
            : ['kind' => 'dependency', 'title' => (string) $metadata['label'], 'message' => 'This operational package is not part of the current foundation checkpoint.', 'dependencies' => [(string) $metadata['package']]],
    ];
}
