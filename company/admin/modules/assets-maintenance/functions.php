<?php
declare(strict_types=1);

require_once __DIR__ . '/core.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/depreciation.php';
require_once __DIR__ . '/dashboard.php';

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
    return array_key_exists($section, yovel_admin_assets_maintenance_sections()) ? $section : 'dashboard';
}

function yovel_admin_assets_maintenance_state(string $section, array $metadata): array
{
    if ($section === 'dashboard') {
        return ['kind' => 'ready', 'title' => 'Assets workspace', 'message' => 'Module controls and dependency readiness.', 'dependencies' => []];
    }
    if ($section === 'asset-records') {
        return ['kind' => 'ready', 'title' => 'Asset records', 'message' => 'Company-owned asset, location, category, activity, and movement lifecycles.', 'dependencies' => []];
    }
    if ($section === 'asset-depreciation-schedule') {
        return ['kind' => 'ready', 'title' => 'Depreciation', 'message' => 'Finance Books, immutable schedules, shifts, and value adjustments.', 'dependencies' => []];
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
    if (trim($section) === '') {
        $section = (string) ($_GET['section'] ?? 'dashboard');
    }
    $section = yovel_admin_assets_maintenance_section($section);
    $sections = yovel_admin_assets_maintenance_sections();
    $metadata = $sections[$section];
    if ($section === 'dashboard') {
        return [
            'section' => $section,
            'sections' => $sections,
            'section_meta' => $metadata,
            'state' => yovel_admin_assets_maintenance_state($section, $metadata),
            'dashboard' => yovel_admin_assets_maintenance_dashboard_data($company, (array) $admin),
            'form_state' => [],
            'company_name' => (string) ($company['company_name'] ?? 'Company'),
        ];
    }

    yovel_admin_assets_maintenance_schema();
    if ($section === 'asset-records') {
        $ownerOptions = yovel_admin_assets_owner_options($company);
        return [
            'section' => $section,
            'sections' => $sections,
            'section_meta' => $metadata,
            'state' => yovel_admin_assets_maintenance_state($section, $metadata),
            'assets' => yovel_admin_assets_assets($company),
            'locations' => yovel_admin_assets_locations($company),
            'categories' => yovel_admin_assets_categories($company),
            'movements' => yovel_admin_assets_movements($company),
            'activities' => yovel_admin_assets_activities($company),
            'inventory_items' => $ownerOptions['inventory_items'],
            'employees' => $ownerOptions['employees'],
            'dependency_state' => $ownerOptions['dependency_state'],
            'form_state' => [],
            'company_name' => (string) ($company['company_name'] ?? 'Company'),
        ];
    }
    if ($section === 'asset-depreciation-schedule') {
        return [
            'section' => $section,
            'sections' => $sections,
            'section_meta' => $metadata,
            'state' => yovel_admin_assets_maintenance_state($section, $metadata),
            'assets' => yovel_admin_assets_assets($company),
            'finance_books' => yovel_admin_assets_finance_books($company),
            'depreciation_schedules' => yovel_admin_assets_depreciation_schedules($company),
            'shift_factors' => yovel_admin_assets_shift_factors($company),
            'shift_allocations' => yovel_admin_assets_shift_allocations($company),
            'value_adjustments' => yovel_admin_assets_value_adjustments($company),
            'activities' => yovel_admin_assets_activities($company),
            'form_state' => [],
            'company_name' => (string) ($company['company_name'] ?? 'Company'),
        ];
    }

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
    if ($action === 'save_asset') {
        $saved = yovel_admin_assets_save_asset($company, $admin, $input);
        return ['message' => 'Asset draft saved.', 'section' => 'asset-records', 'query' => ['asset' => (string) $saved['asset_key']]];
    }
    if ($action === 'submit_asset') {
        $saved = yovel_admin_assets_submit_asset($company, $admin, (string) ($input['asset_key'] ?? ''));
        return ['message' => 'Asset submitted.', 'section' => 'asset-records', 'query' => ['asset' => (string) $saved['asset_key']]];
    }
    if ($action === 'cancel_asset') {
        $saved = yovel_admin_assets_cancel_asset($company, $admin, (string) ($input['asset_key'] ?? ''), (string) ($input['cancellation_reason'] ?? ''));
        return ['message' => 'Asset cancelled.', 'section' => 'asset-records', 'query' => ['asset' => (string) $saved['asset_key']]];
    }
    if ($action === 'save_asset_location') {
        $saved = yovel_admin_assets_save_location($company, $admin, $input);
        return ['message' => 'Asset location saved.', 'section' => 'asset-records', 'query' => ['location' => (string) $saved['location_key']]];
    }
    if ($action === 'save_asset_category') {
        $saved = yovel_admin_assets_save_category($company, $admin, $input);
        return ['message' => 'Asset category saved.', 'section' => 'asset-records', 'query' => ['category' => (string) $saved['category_key']]];
    }
    if ($action === 'save_asset_movement') {
        $saved = yovel_admin_assets_save_movement($company, $admin, $input);
        return ['message' => 'Asset movement draft saved.', 'section' => 'asset-records', 'query' => ['movement' => (string) $saved['movement_key']]];
    }
    if ($action === 'submit_asset_movement') {
        $saved = yovel_admin_assets_submit_movement($company, $admin, (string) ($input['movement_key'] ?? ''));
        return ['message' => 'Asset movement submitted.', 'section' => 'asset-records', 'query' => ['movement' => (string) $saved['movement_key']]];
    }
    if ($action === 'cancel_asset_movement') {
        $saved = yovel_admin_assets_cancel_movement($company, $admin, (string) ($input['movement_key'] ?? ''), (string) ($input['cancellation_reason'] ?? ''));
        return ['message' => 'Asset movement cancelled.', 'section' => 'asset-records', 'query' => ['movement' => (string) $saved['movement_key']]];
    }
    if ($action === 'save_asset_finance_book') {
        $saved = yovel_admin_assets_save_finance_book($company, $admin, $input);
        return ['message' => 'Asset Finance Book saved.', 'section' => 'asset-depreciation-schedule', 'query' => ['book' => (string) $saved['finance_book_key']]];
    }
    if ($action === 'save_asset_depreciation_schedule') {
        $saved = yovel_admin_assets_save_depreciation_schedule($company, $admin, $input);
        return ['message' => 'Depreciation Schedule draft saved.', 'section' => 'asset-depreciation-schedule', 'query' => ['schedule' => (string) $saved['schedule_key']]];
    }
    if ($action === 'submit_asset_depreciation_schedule') {
        $saved = yovel_admin_assets_submit_depreciation_schedule($company, $admin, (string) ($input['schedule_key'] ?? ''));
        return ['message' => 'Depreciation Schedule submitted.', 'section' => 'asset-depreciation-schedule', 'query' => ['schedule' => (string) $saved['schedule_key']]];
    }
    if ($action === 'catchup_asset_depreciation_schedule') {
        $saved = yovel_admin_assets_post_depreciation_catchup($company, $admin, (string) ($input['schedule_key'] ?? ''), (string) ($input['through_date'] ?? ''));
        return ['message' => 'Due depreciation posted.', 'section' => 'asset-depreciation-schedule', 'query' => ['schedule' => (string) $saved['schedule_key']]];
    }
    if ($action === 'cancel_asset_depreciation_schedule') {
        $saved = yovel_admin_assets_cancel_depreciation_schedule($company, $admin, (string) ($input['schedule_key'] ?? ''), (string) ($input['cancellation_reason'] ?? ''));
        return ['message' => 'Depreciation Schedule reversed.', 'section' => 'asset-depreciation-schedule', 'query' => ['schedule' => (string) $saved['schedule_key']]];
    }
    if ($action === 'save_asset_shift_factor') {
        $saved = yovel_admin_assets_save_shift_factor($company, $admin, $input);
        return ['message' => 'Asset Shift Factor saved.', 'section' => 'asset-depreciation-schedule', 'query' => ['factor' => (string) $saved['shift_factor_key']]];
    }
    if ($action === 'save_asset_shift_allocation') {
        $saved = yovel_admin_assets_save_shift_allocation($company, $admin, $input);
        return ['message' => 'Asset Shift Allocation draft saved.', 'section' => 'asset-depreciation-schedule', 'query' => ['allocation' => (string) $saved['shift_allocation_key']]];
    }
    if ($action === 'submit_asset_shift_allocation') {
        $saved = yovel_admin_assets_submit_shift_allocation($company, $admin, (string) ($input['shift_allocation_key'] ?? ''));
        return ['message' => 'Shift Allocation submitted with a replacement Schedule.', 'section' => 'asset-depreciation-schedule', 'query' => ['allocation' => (string) $saved['shift_allocation_key']]];
    }
    if ($action === 'save_asset_value_adjustment') {
        $saved = yovel_admin_assets_save_value_adjustment($company, $admin, $input);
        return ['message' => 'Asset Value Adjustment draft saved.', 'section' => 'asset-depreciation-schedule', 'query' => ['adjustment' => (string) $saved['adjustment_key']]];
    }
    if ($action === 'submit_asset_value_adjustment') {
        $saved = yovel_admin_assets_submit_value_adjustment($company, $admin, (string) ($input['adjustment_key'] ?? ''));
        return ['message' => 'Asset Value Adjustment posted.', 'section' => 'asset-depreciation-schedule', 'query' => ['adjustment' => (string) $saved['adjustment_key']]];
    }
    if ($action === 'cancel_asset_value_adjustment') {
        $saved = yovel_admin_assets_cancel_value_adjustment($company, $admin, (string) ($input['adjustment_key'] ?? ''), (string) ($input['cancellation_reason'] ?? ''));
        return ['message' => 'Asset Value Adjustment reversed.', 'section' => 'asset-depreciation-schedule', 'query' => ['adjustment' => (string) $saved['adjustment_key']]];
    }
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
