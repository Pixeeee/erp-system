<?php
declare(strict_types=1);

require_once __DIR__ . '/core.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/forms.php';

function yovel_admin_assets_maintenance_sections(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'space_dashboard', 'package' => 'AM-01'],
        'asset-records' => ['label' => 'Asset records', 'icon' => 'precision_manufacturing', 'package' => 'AM-02', 'record_type' => 'ASSET'],
        'asset-depreciation-schedule' => ['label' => 'Depreciation', 'icon' => 'trending_down', 'package' => 'AM-03', 'record_type' => 'ASSET_DEPRECIATION_SCHEDULE'],
        'fixed-asset-register' => ['label' => 'Fixed asset register', 'icon' => 'table_view', 'package' => 'AM-07'],
        'maintenance-schedules' => ['label' => 'Maintenance schedules', 'icon' => 'event_repeat', 'package' => 'AM-05', 'record_type' => 'MAINTENANCE_SCHEDULE'],
        'quality-inspection' => ['label' => 'Quality inspection', 'icon' => 'fact_check', 'package' => 'AM-06', 'record_type' => 'QUALITY_INSPECTION_LINK'],
        'maintenance-reports' => ['label' => 'Maintenance reports', 'icon' => 'assessment', 'package' => 'AM-07'],
        'form-builder' => ['label' => 'Form Builder', 'icon' => 'dynamic_form', 'package' => 'AM-01', 'record_type' => 'ASSET'],
    ];
}

function yovel_admin_assets_maintenance_section(string $requested = ''): string
{
    $section = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($requested)), '-');
    return array_key_exists($section, yovel_admin_assets_maintenance_sections()) ? $section : 'asset-records';
}

function yovel_admin_assets_maintenance_state(string $section, array $metadata): array
{
    if ($section === 'dashboard') {
        return ['kind' => 'ready', 'title' => 'Assets workspace', 'message' => 'Module controls and dependency readiness.', 'dependencies' => []];
    }
    if ($section === 'asset-records') {
        return ['kind' => 'empty', 'title' => 'No asset records yet', 'message' => 'Asset lifecycle records become available in AM-02.', 'dependencies' => ['AM-02']];
    }
    if ($section === 'form-builder') {
        return ['kind' => 'ready', 'title' => 'Assets forms', 'message' => 'Company-owned immutable form versions are available.', 'dependencies' => []];
    }

    return [
        'kind' => 'dependency',
        'title' => (string) ($metadata['label'] ?? 'Assets feature') . ' is awaiting its lifecycle package',
        'message' => 'This surface is reserved until its owner services and Assets workflow are implemented.',
        'dependencies' => [(string) ($metadata['package'] ?? 'AM-02')],
    ];
}

function yovel_admin_assets_maintenance_data(array $company, ?array $admin, string $section = ''): array
{
    yovel_admin_assets_scope($company, $admin);
    yovel_admin_assets_maintenance_schema();
    if (trim($section) === '') {
        $section = (string) ($_GET['section'] ?? 'asset-records');
    }
    $section = yovel_admin_assets_maintenance_section($section);
    $sections = yovel_admin_assets_maintenance_sections();
    $metadata = $sections[$section];
    $recordTypes = yovel_admin_assets_form_record_types();
    $requestedRecordType = (string) ($_GET['record_type'] ?? ($metadata['record_type'] ?? 'ASSET'));
    try {
        $recordType = yovel_admin_assets_record_type($requestedRecordType);
    } catch (InvalidArgumentException) {
        $recordType = 'ASSET';
    }

    $forms = yovel_admin_assets_forms($company);
    $selectedForm = null;
    $selectedFormKey = trim((string) ($_GET['form'] ?? ''));
    if ($selectedFormKey !== '') {
        $selectedForm = yovel_admin_assets_form($company, $selectedFormKey);
    }
    if (is_array($selectedForm)) {
        $recordType = (string) $selectedForm['record_type'];
    }
    $activeSchema = is_array($selectedForm)
        ? (array) ($selectedForm['schema'] ?? [])
        : yovel_admin_assets_default_form_schemas()[$recordType];

    return [
        'section' => $section,
        'sections' => $sections,
        'section_meta' => $metadata,
        'state' => yovel_admin_assets_maintenance_state($section, $metadata),
        'form_adapter' => yovel_admin_assets_form_adapter(),
        'record_type' => $recordType,
        'forms' => $forms,
        'selected_form' => $selectedForm,
        'active_form_schema' => $activeSchema,
        'form_state' => [],
        'company_name' => (string) ($company['company_name'] ?? 'Company'),
        'stats' => [
            'forms' => count($forms),
            'published' => count(array_filter($forms, static fn (array $form): bool => (string) ($form['form_status'] ?? '') === 'PUBLISHED')),
            'drafts' => count(array_filter($forms, static fn (array $form): bool => (string) ($form['form_status'] ?? '') === 'DRAFT')),
        ],
    ];
}

function yovel_admin_assets_maintenance_handle_post(
    array $company,
    array $admin,
    string $action,
    array $input
): array {
    if ($action === 'save_assets_form') {
        $saved = yovel_admin_assets_save_form($company, $admin, $input);
        return ['message' => 'Assets form version saved.', 'section' => 'form-builder', 'query' => ['form' => (string) $saved['form_key']]];
    }
    if ($action === 'publish_assets_form') {
        $saved = yovel_admin_assets_publish_form($company, $admin, (string) ($input['form_key'] ?? ''));
        return ['message' => 'Assets form version published.', 'section' => 'form-builder', 'query' => ['form' => (string) $saved['form_key']]];
    }
    if ($action === 'archive_assets_form') {
        $saved = yovel_admin_assets_archive_form($company, $admin, (string) ($input['form_key'] ?? ''), (string) ($input['archive_reason'] ?? ''));
        return ['message' => 'Assets form archived.', 'section' => 'form-builder', 'query' => ['form' => (string) $saved['form_key']]];
    }
    if ($action === 'submit_assets_form_record') {
        $saved = yovel_admin_assets_save_form_submission($company, $admin, $input);
        return ['message' => 'Assets form record submitted.', 'section' => 'form-builder', 'query' => ['form' => (string) $saved['form_key']]];
    }
    throw new InvalidArgumentException('Unknown Assets / Maintenance action.');
}
