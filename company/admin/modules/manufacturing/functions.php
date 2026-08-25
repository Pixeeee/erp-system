<?php
declare(strict_types=1);

require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/dependencies.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/submissions.php';

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
    return $data;
}
