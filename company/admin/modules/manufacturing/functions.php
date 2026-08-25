<?php
declare(strict_types=1);

require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/dependencies.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/bom.php';
require_once __DIR__ . '/capacity.php';
require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/submissions.php';
require_once __DIR__ . '/dashboard.php';

function yovel_admin_manufacturing_data(array $company, array $admin, ?string $section = null): array
{
    yovel_admin_manufacturing_scope($company, $admin);
    yovel_admin_manufacturing_schema();
    $section = yovel_admin_manufacturing_section($section);
    $data = yovel_admin_manufacturing_workspace_data($section);
    $settings = yovel_admin_manufacturing_settings($company);
    $forms = yovel_admin_manufacturing_forms($company);
    $recordType = (string) (yovel_admin_manufacturing_sections()[$section]['record_type'] ?? '');
    if ($section === 'form-builder') {
        $requestedTarget = yovel_admin_manufacturing_section((string) ($_GET['target'] ?? 'boms'));
        $recordType = (string) (yovel_admin_manufacturing_form_targets()[$requestedTarget] ?? 'BOM');
        $data['selected_target'] = $requestedTarget;
    }
    $data['company_name'] = (string) ($company['company_name'] ?? 'Company');
    $data['settings'] = $settings;
    $data['forms'] = $forms;
    $selectedFormSchema = $section === 'form-builder'
        ? yovel_admin_manufacturing_form_schema_by_key($company, (string) ($_GET['form'] ?? ''))
        : null;
    if (is_array($selectedFormSchema)) {
        $data['selected_target'] = (string) $selectedFormSchema['target_section'];
        $data['selected_form'] = $selectedFormSchema;
        $data['form_schema'] = $selectedFormSchema;
    } else {
        $data['selected_form'] = null;
        $data['form_schema'] = $recordType !== '' ? yovel_admin_manufacturing_form_schema($company, $recordType) : [];
    }
    $data['dependencies'] = array_map(
        static fn (array $contract): array => [
            'owner' => (string) $contract['owner'],
            'owner_function' => (string) $contract['owner_function'],
            'signature' => (string) $contract['signature'],
            'available' => (bool) $contract['available'],
        ],
        yovel_admin_manufacturing_dependency_gateway()
    );
    $data['summary']['form_count'] = count($forms);
    $data['summary']['dependency_ready'] = count(array_filter($data['dependencies'], static fn (array $contract): bool => $contract['available']));
    if ($section === 'dashboard') {
        $data['dashboard'] = yovel_admin_manufacturing_dashboard_data($company, $admin);
    } elseif ($section === 'boms') {
        $data['boms'] = yovel_admin_manufacturing_boms($company);
        $selectedBomKey = trim((string) ($_GET['bom'] ?? ''));
        $data['selected_bom'] = $selectedBomKey !== '' ? yovel_admin_manufacturing_bom($company, $selectedBomKey) : null;
        $data['summary']['record_count'] = count($data['boms']);
    } elseif ($section === 'operations') {
        $data['operations'] = yovel_admin_manufacturing_operations($company);
        $data['routings'] = yovel_admin_manufacturing_routings($company);
        $data['summary']['record_count'] = count($data['operations']) + count($data['routings']);
    } elseif ($section === 'workstations') {
        $data['workstation_types'] = yovel_admin_manufacturing_workstation_types($company);
        $data['plant_floors'] = yovel_admin_manufacturing_plant_floors($company);
        $data['workstations'] = yovel_admin_manufacturing_workstations($company);
        $data['downtimes'] = yovel_admin_manufacturing_downtimes($company);
        $data['plant_floor'] = yovel_admin_manufacturing_plant_floor($company);
        $data['summary']['record_count'] = count($data['workstations']);
    } elseif ($section === 'reports') {
        $data['report_definitions'] = yovel_admin_manufacturing_report_definitions();
        $data['downtime_analysis'] = yovel_admin_manufacturing_report($company, 'downtime-analysis', [
            'date_from' => (string) ($_GET['date_from'] ?? date('Y-m-01')),
            'date_to' => (string) ($_GET['date_to'] ?? date('Y-m-d')),
        ]);
    }
    return $data;
}
