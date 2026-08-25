<?php
declare(strict_types=1);

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/settings.php';

function yovel_admin_support_service_sections(): array
{
    return [
        'issues-tickets' => ['label' => 'Issues and tickets', 'icon' => 'confirmation_number'],
        'sla-rules' => ['label' => 'Service level rules', 'icon' => 'timer'],
        'warranty-claims' => ['label' => 'Warranty claims', 'icon' => 'verified_user'],
        'first-response-tracking' => ['label' => 'First response tracking', 'icon' => 'speed'],
        'issue-summaries' => ['label' => 'Issue summaries', 'icon' => 'assessment'],
        'customer-support-portal' => ['label' => 'Customer support portal', 'icon' => 'support_agent'],
    ];
}

function yovel_admin_support_service_section(string $requested = ''): string
{
    if ($requested === '') {
        $requested = (string) ($_GET['section'] ?? '');
    }
    $requested = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($requested)), '-');

    return array_key_exists($requested, yovel_admin_support_service_sections())
        ? $requested
        : 'issues-tickets';
}

function yovel_admin_support_service_scope(array $company, array $admin): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $adminKey = trim((string) ($admin['admin_key'] ?? ''));

    if ($companyKey === '' || strlen($companyKey) > 1500) {
        throw new InvalidArgumentException('Support / Service requires a bounded active company key.');
    }
    if (preg_match('/^[0-9a-f]{64}$/D', $companyHash) !== 1) {
        throw new InvalidArgumentException('Support / Service requires a 64-character lowercase company key hash.');
    }
    if (!hash_equals(hash('sha256', $companyKey), $companyHash)) {
        throw new InvalidArgumentException('Support / Service requires an active company with a matching key and hash.');
    }
    if ($adminKey === '' || (function_exists('yovel_admin_is_uuid') && !yovel_admin_is_uuid($adminKey))) {
        throw new InvalidArgumentException('Support / Service requires an active company administrator.');
    }

    $authorized = bx_db()->GetRow(
        "SELECT company_record.company_key, company_record.company_key_hash, admin_record.admin_key
         FROM project_company company_record
         INNER JOIN project_company_admin admin_record
           ON admin_record.company_key = company_record.company_key
          AND admin_record.company_key_hash = company_record.company_key_hash
          AND admin_record.admin_key = ?
          AND admin_record.admin_status = 'ACTIVE'
         WHERE company_record.company_key = ?
           AND company_record.company_key_hash = ?
           AND company_record.company_status = 'ACTIVE'
         LIMIT 1",
        [$adminKey, $companyKey, $companyHash]
    );
    if (!is_array($authorized)
        || !hash_equals($companyKey, (string) ($authorized['company_key'] ?? ''))
        || !hash_equals($companyHash, strtolower((string) ($authorized['company_key_hash'] ?? '')))
        || !hash_equals($adminKey, (string) ($authorized['admin_key'] ?? ''))) {
        throw new InvalidArgumentException('Support / Service requires an active company administrator membership.');
    }

    return [$companyKey, $companyHash, $adminKey];
}

function yovel_admin_support_service_verify_read_back(?array $actual, array $expected, string $label): void
{
    if (!is_array($actual) || $actual === []) {
        throw new RuntimeException($label . ' could not be read back after persistence.');
    }
    foreach ($expected as $column => $value) {
        if (!array_key_exists($column, $actual) || (string) $actual[$column] !== (string) $value) {
            throw new RuntimeException($label . ' read-back verification failed for ' . $column . '.');
        }
    }
}

function yovel_admin_support_service_dependencies(): array
{
    return [
        'operations_search' => [
            'contract' => 'operations.support-search.v1',
            'callable' => 'yovel_admin_operations_support_search',
            'signature' => 'yovel_admin_operations_support_search(array $company, array $request): array',
            'available' => function_exists('yovel_admin_operations_support_search'),
        ],
        'operations_jobs' => [
            'contract' => 'operations.enqueue-job.v1',
            'callable' => 'yovel_admin_operations_enqueue_job',
            'signature' => 'yovel_admin_operations_enqueue_job(array $company, array $admin, array $job): array',
            'available' => function_exists('yovel_admin_operations_enqueue_job'),
        ],
        'operations_communications' => [
            'contract' => 'operations.append-communication.v1',
            'callable' => 'yovel_admin_operations_append_communication',
            'signature' => 'yovel_admin_operations_append_communication(array $company, array $admin, array $message): array',
            'available' => function_exists('yovel_admin_operations_append_communication'),
        ],
        'sales_customer' => [
            'contract' => 'sales-crm.customer-reference.v1',
            'callable' => 'yovel_admin_sales_crm_customer_reference',
            'signature' => 'yovel_admin_sales_crm_customer_reference(array $company, string $customerKey): ?array',
            'available' => function_exists('yovel_admin_sales_crm_customer_reference'),
        ],
        'sales_contact' => [
            'contract' => 'sales-crm.contact-reference.v1',
            'callable' => 'yovel_admin_sales_crm_contact_reference',
            'signature' => 'yovel_admin_sales_crm_contact_reference(array $company, string $contactKey): ?array',
            'available' => function_exists('yovel_admin_sales_crm_contact_reference'),
        ],
        'projects_service_work' => [
            'contract' => 'projects.service-work-reference.v1',
            'callable' => 'yovel_admin_projects_service_work_reference',
            'signature' => 'yovel_admin_projects_service_work_reference(array $company, string $serviceWorkKey): ?array',
            'available' => function_exists('yovel_admin_projects_service_work_reference'),
        ],
    ];
}

function yovel_admin_support_service_data(array $company, array $admin): array
{
    yovel_admin_support_service_scope($company, $admin);

    return [
        'sections' => yovel_admin_support_service_sections(),
        'section' => yovel_admin_support_service_section(),
        'settings' => yovel_admin_support_settings($company, $admin),
        'search_sources' => yovel_admin_support_search_sources($company, $admin),
        'form_adapter' => yovel_admin_support_service_form_adapter(),
        'dependencies' => yovel_admin_support_service_dependencies(),
    ];
}

function yovel_admin_support_service_rehydration(string $formId, array $values): array
{
    $safeValues = function_exists('yovel_admin_module_rehydration_input')
        ? yovel_admin_module_rehydration_input($values)
        : $values;

    return [
        'form_id' => $formId,
        'values' => $safeValues,
        'source' => 'committed-read-back',
    ];
}

function yovel_admin_support_service_handle_post(
    array $company,
    array $admin,
    string $action,
    array $input
): array {
    if (!in_array($action, ['support_save_settings', 'support_save_search_source'], true)) {
        throw new InvalidArgumentException('Unknown Support / Service module action.');
    }
    yovel_admin_support_service_assert_no_secrets($input);

    if ($action === 'support_save_settings') {
        $saved = yovel_admin_save_support_settings($company, $admin, $input);

        return [
            'message' => 'Support settings were saved.',
            'section' => yovel_admin_support_service_section((string) ($input['section'] ?? 'customer-support-portal')),
            'query' => ['settings' => (string) $saved['support_setting_key']],
            'rehydration' => yovel_admin_support_service_rehydration('support-settings', $saved),
        ];
    }

    $saved = yovel_admin_save_support_search_source($company, $admin, $input);

    return [
        'message' => 'Support search source was saved.',
        'section' => yovel_admin_support_service_section((string) ($input['section'] ?? 'customer-support-portal')),
        'query' => ['search_source' => (string) $saved['search_source_key']],
        'rehydration' => yovel_admin_support_service_rehydration('support-search-source', $saved),
    ];
}
