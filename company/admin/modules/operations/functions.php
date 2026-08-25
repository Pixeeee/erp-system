<?php
declare(strict_types=1);

require_once __DIR__ . '/contracts.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/forms.php';
if (is_file(__DIR__ . '/jobs.php')) {
    require_once __DIR__ . '/jobs.php';
}
if (is_file(__DIR__ . '/notifications.php')) {
    require_once __DIR__ . '/notifications.php';
}
if (is_file(__DIR__ . '/dashboard.php')) {
    require_once __DIR__ . '/dashboard.php';
}
if (is_file(__DIR__ . '/bulk.php')) {
    require_once __DIR__ . '/bulk.php';
}
if (is_file(__DIR__ . '/deletion.php')) {
    require_once __DIR__ . '/deletion.php';
}
if (is_file(__DIR__ . '/setup.php')) {
    require_once __DIR__ . '/setup.php';
}
if (is_file(__DIR__ . '/workforce.php')) {
    require_once __DIR__ . '/workforce.php';
}
if (is_file(__DIR__ . '/commercial.php')) {
    require_once __DIR__ . '/commercial.php';
}
if (is_file(__DIR__ . '/catalog.php')) {
    require_once __DIR__ . '/catalog.php';
}

function yovel_admin_operations_sections(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'space_dashboard', 'record_type' => 'Operations Dashboard'],
        'scheduled-jobs' => ['label' => 'Scheduled Jobs', 'icon' => 'schedule', 'record_type' => 'Scheduled Job'],
        'notifications' => ['label' => 'Notifications', 'icon' => 'notifications', 'record_type' => 'Notification Handoff'],
        'background-workers' => ['label' => 'Background Workers', 'icon' => 'memory', 'record_type' => 'Background Worker'],
        'sync-conflict-dashboard' => ['label' => 'Sync Conflicts', 'icon' => 'sync_problem', 'record_type' => 'Sync Conflict'],
        'import-export-jobs' => ['label' => 'Import / Export', 'icon' => 'swap_vert', 'record_type' => 'Import or Export Job'],
        'system-alerts' => ['label' => 'System Alerts', 'icon' => 'warning', 'record_type' => 'System Alert'],
        'release-checklist' => ['label' => 'Release Checklist', 'icon' => 'fact_check', 'record_type' => 'Release Check'],
        'bulk-processing' => ['label' => 'Bulk Processing', 'icon' => 'library_add_check', 'record_type' => 'Bulk Processing Log'],
        'governed-deletion' => ['label' => 'Governed Deletion', 'icon' => 'delete_sweep', 'record_type' => 'Governed Deletion Request'],
        'authorization-setup' => ['label' => 'Authorization', 'icon' => 'policy', 'record_type' => 'Authorization Policy'],
        'company-defaults' => ['label' => 'Company & Defaults', 'icon' => 'domain', 'record_type' => 'Company Default Projection'],
        'workforce-directory' => ['label' => 'Workforce Directory', 'icon' => 'groups', 'record_type' => 'Workforce Projection'],
        'workforce-calendars' => ['label' => 'Workforce Calendars', 'icon' => 'calendar_month', 'record_type' => 'Workforce Calendar Projection'],
        'commercial-masters' => ['label' => 'Commercial Masters', 'icon' => 'storefront', 'record_type' => 'Commercial Master Projection'],
        'catalog-units' => ['label' => 'Catalog & Units', 'icon' => 'category', 'record_type' => 'Catalog and Unit Projection'],
    ];
}

function yovel_admin_operations_section(string $requested = ''): string
{
    $requested = $requested !== '' ? $requested : (string) ($_GET['section'] ?? '');
    $requested = function_exists('yovel_admin_slug') ? yovel_admin_slug($requested) : strtolower(trim($requested));
    return isset(yovel_admin_operations_sections()[$requested]) ? $requested : 'dashboard';
}

function yovel_admin_operations_data(array $company, array $admin, array $providers = []): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash] = yovel_admin_operations_scope($company, $admin);
    $forms = yovel_admin_operations_builder_forms($company);
    $workspace = [];
    $dependencyErrors = [];
    if (isset($providers['owners.workspace-directory.v1'])) {
        try {
            $workspace = yovel_admin_operations_call_read_contract(
                'owners.workspace-directory.v1',
                $company,
                ['module' => 'operations'],
                $providers
            )['records'];
        } catch (Throwable $error) {
            $dependencyErrors['owners.workspace-directory.v1'] = $error->getMessage();
        }
    }

    $jobMetrics = function_exists('yovel_admin_operations_job_metrics')
        ? yovel_admin_operations_job_metrics($company)
        : [];
    return [
        'company_key_hash' => $companyKeyHash,
        'dashboard' => function_exists('yovel_admin_operations_dashboard_data') ? yovel_admin_operations_dashboard_data($company, $admin) : [],
        'formBuilder' => ['adapter' => yovel_admin_operations_form_adapter(), 'forms' => $forms],
        'workspace' => $workspace,
        'metrics' => [
            'builder_forms' => count($forms),
            'published_forms' => count(array_filter($forms, static fn (array $form): bool => ($form['form_status'] ?? '') === 'PUBLISHED')),
            'queued_jobs' => (int) ($jobMetrics['queued'] ?? 0),
            'active_alerts' => (int) ($jobMetrics['active_alerts'] ?? 0),
        ],
        'dependency_errors' => $dependencyErrors,
        'jobs' => function_exists('yovel_admin_operations_jobs') ? yovel_admin_operations_jobs($company) : [],
        'workers' => function_exists('yovel_admin_operations_workers') ? yovel_admin_operations_workers($company) : [],
        'schedules' => function_exists('yovel_admin_operations_schedules') ? yovel_admin_operations_schedules($company) : [],
        'conflicts' => function_exists('yovel_admin_operations_sync_conflicts') ? yovel_admin_operations_sync_conflicts($company) : [],
        'alerts' => function_exists('yovel_admin_operations_system_alerts') ? yovel_admin_operations_system_alerts($company) : [],
        'release_checks' => function_exists('yovel_admin_operations_release_checks') ? yovel_admin_operations_release_checks($company) : [],
        'bulk_logs' => function_exists('yovel_admin_operations_bulk_logs') ? yovel_admin_operations_bulk_logs($company) : [],
        'deletion_requests' => function_exists('yovel_admin_operations_deletion_requests') ? yovel_admin_operations_deletion_requests($company) : [],
        'authorization_projection' => function_exists('yovel_admin_operations_authorization_projection') ? yovel_admin_operations_authorization_projection($company, $providers) : [],
        'authorization_policies' => function_exists('yovel_admin_operations_authorization_policies') ? yovel_admin_operations_authorization_policies($company) : [],
        'company_defaults_projection' => function_exists('yovel_admin_operations_company_defaults_projection') ? yovel_admin_operations_company_defaults_projection($company, $providers) : [],
        'workforce_calendar_projection' => function_exists('yovel_admin_operations_workforce_calendar_projection')
            ? yovel_admin_operations_workforce_calendar_projection($company, [
                'status' => yovel_admin_operations_workforce_status((string) ($_GET['workforce_status'] ?? 'ALL')),
                'from_date' => (string) ($_GET['calendar_from'] ?? ''),
                'to_date' => (string) ($_GET['calendar_to'] ?? ''),
            ])
            : [],
        'commercial_projection' => yovel_admin_operations_section() === 'commercial-masters' && function_exists('yovel_admin_operations_commercial_projection')
            ? yovel_admin_operations_commercial_projection($company, $admin, $providers)
            : [],
        'catalog_projection' => yovel_admin_operations_section() === 'catalog-units' && function_exists('yovel_admin_operations_catalog_projection')
            ? yovel_admin_operations_catalog_projection($company)
            : [],
        'owner_contracts_available' => [
            'bulk' => is_callable($providers['owners.bulk-command.v1'] ?? null),
            'record_directory' => is_callable($providers['shared.record-type-directory.v1'] ?? null),
            'governed_delete' => is_callable($providers['owners.governed-delete.v1'] ?? null),
        ],
    ];
}

function yovel_admin_operations_input_json(array $input, string $key, string $label): array
{
    $value = $input[$key] ?? [];
    if (is_array($value)) {
        return $value;
    }
    try {
        return yovel_admin_operations_json_array((string) $value, $label);
    } catch (JsonException $error) {
        throw new InvalidArgumentException($label . ' must be valid JSON.', 0, $error);
    }
}

function yovel_admin_operations_handle_post(array $company, array $admin, string $action, array $input): array
{
    $action = strtolower(trim($action));
    $section = yovel_admin_operations_section((string) ($input['section'] ?? ''));
    if ($action === 'save_operations_builder_form') {
        $saved = yovel_admin_persist_operations_builder_form(bx_db(), $company, $admin, [
            'builder_form_key' => $input['builder_form_key'] ?? '',
            'target_section' => $input['target_section'] ?? $section,
            'form_title' => $input['form_title'] ?? '',
            'form_description' => $input['form_description'] ?? '',
            'form_status' => $input['form_status'] ?? 'DRAFT',
            'schema' => yovel_admin_operations_input_json($input, 'schema_json', 'Operations form schema'),
        ]);
        return ['message' => 'Operations form version saved.', 'section' => $section, 'query' => ['form' => (string) $saved['builder_form_key']]];
    }
    if ($action === 'save_operations_form_submission') {
        $saved = yovel_admin_persist_operations_form_submission(bx_db(), $company, $admin, [
            'submission_key' => $input['submission_key'] ?? '',
            'builder_form_key' => $input['builder_form_key'] ?? '',
            'form_version_key' => $input['form_version_key'] ?? '',
            'subject_key' => $input['subject_key'] ?? '',
            'values' => yovel_admin_operations_input_json($input, 'values_json', 'Operations form values'),
        ]);
        return ['message' => 'Operations form submission saved.', 'section' => $section, 'query' => ['submission' => (string) $saved['submission_key']]];
    }
    if ($action === 'save_operations_authorization_policy' && function_exists('yovel_admin_operations_persist_authorization_policy')) {
        $saved = yovel_admin_operations_persist_authorization_policy(
            bx_db(), $company, $admin, $input, yovel_admin_operations_runtime_providers()
        );
        return ['message' => 'Operations authorization policy saved.', 'section' => 'authorization-setup', 'query' => ['policy' => (string) $saved['policy_key']]];
    }
    if (function_exists('yovel_admin_operations_handle_workflow_post') && in_array($action, [
        'submit_operations_bulk', 'cancel_operations_bulk', 'plan_operations_deletion',
        'submit_operations_deletion', 'cancel_operations_deletion',
    ], true)) {
        return yovel_admin_operations_handle_workflow_post($company, $admin, $action, $input);
    }
    if (function_exists('yovel_admin_operations_handle_job_post')) {
        return yovel_admin_operations_handle_job_post($company, $admin, $action, $input);
    }
    throw new InvalidArgumentException('Unknown Operations action.');
}
