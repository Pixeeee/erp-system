<?php
declare(strict_types=1);

function yovel_admin_support_service_dashboard_contracts(): array
{
    return [
        'open-tickets' => [
            'label' => 'Open tickets',
            'owner' => 'Support / Service',
            'contract' => 'support.issue-dashboard.v1',
            'callable' => 'yovel_admin_support_issue_dashboard_metrics',
            'queue_label' => 'Unassigned and overdue tickets',
        ],
        'sla-risks' => [
            'label' => 'SLA risks',
            'owner' => 'Support / Service',
            'contract' => 'support.sla-dashboard.v1',
            'callable' => 'yovel_admin_support_sla_dashboard_metrics',
            'queue_label' => 'SLA breaches and escalations',
        ],
        'customer-context' => [
            'label' => 'Customer follow-ups',
            'owner' => 'Sales / CRM',
            'contract' => 'sales-crm.support-customer-metrics.v1',
            'callable' => 'yovel_admin_sales_crm_support_customer_metrics',
            'queue_label' => 'Customer follow-ups',
        ],
        'assignment-backlog' => [
            'label' => 'Assignment backlog',
            'owner' => 'Operations',
            'contract' => 'operations.support-assignment-metrics.v1',
            'callable' => 'yovel_admin_operations_support_assignment_metrics',
            'queue_label' => 'Unresolved assignments',
        ],
        'communications' => [
            'label' => 'Response activity',
            'owner' => 'Operations',
            'contract' => 'operations.support-communication-metrics.v1',
            'callable' => 'yovel_admin_operations_support_communication_metrics',
            'queue_label' => 'Pending communication responses',
        ],
        'project-service-work' => [
            'label' => 'Service work',
            'owner' => 'Projects',
            'contract' => 'projects.support-service-work-metrics.v1',
            'callable' => 'yovel_admin_projects_support_service_work_metrics',
            'queue_label' => 'Linked project service work',
        ],
    ];
}

function yovel_admin_support_service_dashboard_metric(array $company, string $key, array $contract): array
{
    $callable = (string) $contract['callable'];
    $metric = [
        'key' => $key,
        'label' => (string) $contract['label'],
        'value' => null,
        'availability' => 'UNAVAILABLE_DEPENDENCY',
        'dependency' => (string) $contract['contract'],
        'owner' => (string) $contract['owner'],
        'href' => '',
    ];
    if (!function_exists($callable)) {
        return $metric;
    }

    try {
        $response = $callable($company);
        if (!is_array($response) || !array_key_exists('value', $response) || !is_numeric($response['value'])) {
            throw new RuntimeException('The owner metric response is invalid.');
        }
        $metric['value'] = $response['value'] + 0;
        $metric['availability'] = 'AVAILABLE';
        $metric['href'] = trim((string) ($response['href'] ?? ''));
    } catch (Throwable) {
        $metric['availability'] = 'ERROR';
    }

    return $metric;
}

function yovel_admin_support_service_dashboard_data(array $company, array $admin): array
{
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $db = bx_db();
    $errors = [];
    $settings = yovel_admin_support_settings_defaults();
    $searchSources = [];
    $auditRows = [];
    $auditCount = 0;

    try {
        $row = $db->GetRow(
            'SELECT support_setting_key, company_key, company_key_hash, setting_version,
                    close_issue_after_days, portal_enabled, track_service_level_agreement,
                    allow_resetting_service_level_agreement, greeting_title, greeting_subtitle,
                    operations_search_contract, operations_job_contract
             FROM project_company_support_setting
             WHERE company_key_hash = ? LIMIT 1',
            [$companyHash]
        );
        if ($row === false) {
            throw new RuntimeException('Support settings could not be read.');
        }
        if (is_array($row) && $row !== []) {
            $settings = array_merge($settings, $row);
        }
    } catch (Throwable) {
        $errors['settings'] = 'Support settings are temporarily unavailable.';
    }

    try {
        $rows = $db->GetAll(
            "SELECT search_source_key, source_name, source_type, source_status, source_version
             FROM project_company_support_search_source
             WHERE company_key_hash = ? AND source_status <> 'ARCHIVED'
             ORDER BY source_name, x_id",
            [$companyHash]
        );
        if ($rows === false) {
            throw new RuntimeException('Support search sources could not be read.');
        }
        $searchSources = is_array($rows) ? $rows : [];
    } catch (Throwable) {
        $errors['search_sources'] = 'Support search-source readiness is temporarily unavailable.';
    }

    $auditPattern = '%"company_key_hash":"' . $companyHash . '"%';
    try {
        $count = $db->GetOne(
            "SELECT COUNT(*)
             FROM builder_audit_log
             WHERE module IN (?, ?) AND new_values LIKE ?",
            ['project_company_support_setting', 'project_company_support_search_source', $auditPattern]
        );
        if ($count === false) {
            throw new RuntimeException('Support audit count could not be read.');
        }
        $auditCount = (int) $count;
        $rows = $db->GetAll(
            "SELECT audit_key, user_key, action, module, record_key, new_values, created_at
             FROM builder_audit_log
             WHERE module IN (?, ?) AND new_values LIKE ?
             ORDER BY created_at DESC, x_id DESC
             LIMIT 8",
            ['project_company_support_setting', 'project_company_support_search_source', $auditPattern]
        );
        if ($rows === false) {
            throw new RuntimeException('Support audit activity could not be read.');
        }
        $auditRows = is_array($rows) ? $rows : [];
    } catch (Throwable) {
        $errors['activity'] = 'Recent Support activity is temporarily unavailable.';
    }

    $activeSearchSources = count(array_filter(
        $searchSources,
        static fn (array $source): bool => (string) ($source['source_status'] ?? '') === 'ACTIVE'
    ));
    $formAdapter = yovel_admin_support_service_form_adapter();
    $metricContracts = yovel_admin_support_service_dashboard_contracts();
    $summary = [];
    $summaryByKey = [];
    foreach ($metricContracts as $key => $contract) {
        $metric = yovel_admin_support_service_dashboard_metric($company, $key, $contract);
        $summary[] = $metric;
        $summaryByKey[$key] = $metric;
    }

    $dependencies = [];
    foreach ($metricContracts as $metricKey => $contract) {
        $dependencies[] = [
            'key' => strtolower(str_replace(['.', '/'], '-', (string) $contract['contract'])),
            'label' => (string) $contract['label'],
            'owner' => (string) $contract['owner'],
            'contract' => (string) $contract['contract'],
            'callable' => (string) $contract['callable'],
            'status' => (string) ($summaryByKey[$metricKey]['availability'] ?? 'UNAVAILABLE_DEPENDENCY'),
        ];
    }
    foreach (yovel_admin_support_service_dependencies() as $key => $dependency) {
        $dependencies[] = [
            'key' => str_replace('_', '-', (string) $key),
            'label' => ucwords(str_replace('_', ' ', (string) $key)),
            'owner' => str_starts_with((string) $key, 'sales_') ? 'Sales / CRM' : (str_starts_with((string) $key, 'projects_') ? 'Projects' : 'Operations'),
            'contract' => (string) $dependency['contract'],
            'callable' => (string) $dependency['callable'],
            'status' => !empty($dependency['available']) ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY',
        ];
    }

    $settingsConfigured = (string) ($settings['support_setting_key'] ?? '') !== '';
    $setup = [
        ['key' => 'support-settings', 'label' => 'Configure Support settings', 'complete' => $settingsConfigured, 'status' => $settingsConfigured ? 'COMPLETE' : 'OPEN', 'href' => './?view=support-service&section=customer-support-portal'],
        ['key' => 'search-source', 'label' => 'Connect a support search source', 'complete' => $activeSearchSources > 0, 'status' => $activeSearchSources > 0 ? 'COMPLETE' : 'OPEN', 'href' => './?view=support-service&section=customer-support-portal'],
        ['key' => 'form-builder', 'label' => 'Review configurable Support forms', 'complete' => count($formAdapter['target_record_types'] ?? []) > 0, 'status' => 'COMPLETE', 'href' => './?view=support-service&section=form-builder'],
        ['key' => 'issue-contract', 'label' => 'Install the Issue lifecycle package', 'complete' => function_exists('yovel_admin_support_issue_dashboard_metrics'), 'status' => function_exists('yovel_admin_support_issue_dashboard_metrics') ? 'COMPLETE' : 'UNAVAILABLE_DEPENDENCY', 'href' => ''],
        ['key' => 'owner-contracts', 'label' => 'Connect customer, assignment, communication, and service-work metrics', 'complete' => count(array_filter($summary, static fn (array $metric): bool => $metric['availability'] === 'AVAILABLE')) === count($summary), 'status' => count(array_filter($summary, static fn (array $metric): bool => $metric['availability'] === 'AVAILABLE')) === count($summary) ? 'COMPLETE' : 'UNAVAILABLE_DEPENDENCY', 'href' => ''],
    ];

    $queue = [];
    if (!$settingsConfigured) {
        $queue[] = ['key' => 'configure-settings', 'label' => 'Configure Support settings', 'status' => 'OPEN', 'availability' => 'AVAILABLE', 'href' => './?view=support-service&section=customer-support-portal'];
    }
    if ($activeSearchSources === 0) {
        $queue[] = ['key' => 'connect-search', 'label' => 'Connect a support search source', 'status' => 'OPEN', 'availability' => 'AVAILABLE', 'href' => './?view=support-service&section=customer-support-portal'];
    }
    if ($settingsConfigured && $activeSearchSources > 0) {
        $queue[] = ['key' => 'foundation-ready', 'label' => 'Support foundation is ready for workflow packages', 'status' => 'READY', 'availability' => 'AVAILABLE', 'href' => './?view=support-service&section=form-builder'];
    }
    foreach ($metricContracts as $key => $contract) {
        $queue[] = [
            'key' => $key . '-queue',
            'label' => (string) $contract['queue_label'],
            'status' => 'UNAVAILABLE_DEPENDENCY',
            'availability' => 'UNAVAILABLE_DEPENDENCY',
            'dependency' => (string) $contract['contract'],
            'href' => '',
        ];
    }

    $activity = [];
    foreach ($auditRows as $row) {
        $values = json_decode((string) ($row['new_values'] ?? ''), true);
        $values = is_array($values) ? $values : [];
        $isSearch = (string) ($row['module'] ?? '') === 'project_company_support_search_source';
        $userKey = trim((string) ($row['user_key'] ?? ''));
        $activity[] = [
            'key' => (string) ($row['audit_key'] ?? ''),
            'action' => (string) ($row['action'] ?? 'UPDATE'),
            'record_label' => $isSearch ? (string) ($values['source_name'] ?? 'Support search source') : 'Support settings',
            'actor_label' => $userKey !== '' ? 'Administrator ' . substr($userKey, 0, 8) : 'System',
            'status' => 'AVAILABLE',
            'occurred_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    $alerts = [];
    foreach ($errors as $key => $message) {
        $alerts[] = ['key' => 'error-' . str_replace('_', '-', $key), 'severity' => 'ERROR', 'label' => $message, 'href' => ''];
    }
    if (!$settingsConfigured) {
        $alerts[] = ['key' => 'settings-required', 'severity' => 'WARNING', 'label' => 'Support settings require configuration.', 'href' => './?view=support-service&section=customer-support-portal'];
    }
    if ($activeSearchSources === 0) {
        $alerts[] = ['key' => 'search-required', 'severity' => 'WARNING', 'label' => 'No active support search source is configured.', 'href' => './?view=support-service&section=customer-support-portal'];
    }
    $unavailableCount = count(array_filter($summary, static fn (array $metric): bool => $metric['availability'] !== 'AVAILABLE'));
    if ($unavailableCount > 0) {
        $alerts[] = ['key' => 'metric-contracts', 'severity' => 'INFO', 'label' => $unavailableCount . ' operational metric contracts are unavailable.', 'href' => ''];
    }

    return [
        'summary' => $summary,
        'queue' => $queue,
        'activity' => $activity,
        'setup' => $setup,
        'alerts' => $alerts,
        'shortcuts' => [
            ['key' => 'portal-settings', 'label' => 'Portal settings', 'icon' => 'settings', 'href' => './?view=support-service&section=customer-support-portal', 'available' => true, 'availability' => 'AVAILABLE'],
            ['key' => 'search-sources', 'label' => 'Search sources', 'icon' => 'manage_search', 'href' => './?view=support-service&section=customer-support-portal', 'available' => true, 'availability' => 'AVAILABLE'],
            ['key' => 'form-builder', 'label' => 'Form Builder', 'icon' => 'dynamic_form', 'href' => './?view=support-service&section=form-builder', 'available' => true, 'availability' => 'AVAILABLE'],
            ['key' => 'issues', 'label' => 'Issues and tickets', 'icon' => 'confirmation_number', 'href' => '', 'available' => false, 'availability' => 'UNAVAILABLE_DEPENDENCY', 'dependency' => 'support.issue-dashboard.v1'],
        ],
        'directories' => [
            ['group' => 'Support setup', 'items' => [
                ['key' => 'settings', 'label' => 'Support settings', 'href' => './?view=support-service&section=customer-support-portal', 'available' => true],
                ['key' => 'search', 'label' => 'Search sources', 'href' => './?view=support-service&section=customer-support-portal', 'available' => true],
                ['key' => 'forms', 'label' => 'Form Builder', 'href' => './?view=support-service&section=form-builder', 'available' => true],
            ]],
            ['group' => 'Service operations', 'items' => [
                ['key' => 'tickets', 'label' => 'Issues and tickets', 'href' => '', 'available' => false],
                ['key' => 'sla', 'label' => 'Service level rules', 'href' => '', 'available' => false],
                ['key' => 'warranty', 'label' => 'Warranty claims', 'href' => '', 'available' => false],
            ]],
            ['group' => 'Insights', 'items' => [
                ['key' => 'response', 'label' => 'First response tracking', 'href' => '', 'available' => false],
                ['key' => 'summaries', 'label' => 'Issue summaries', 'href' => '', 'available' => false],
            ]],
        ],
        'dependencies' => $dependencies,
        'foundation' => [
            'status' => $errors === [] ? 'AVAILABLE' : 'ERROR',
            'settings_configured' => $settingsConfigured,
            'active_search_sources' => $activeSearchSources,
            'form_targets' => count($formAdapter['target_record_types'] ?? []),
            'audit_events' => $auditCount,
            'settings' => $settings,
            'search_sources' => $searchSources,
            'errors' => array_values($errors),
        ],
    ];
}
