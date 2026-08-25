<?php
declare(strict_types=1);

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/dashboard.php';
require_once __DIR__ . '/projects.php';
require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/tasks.php';

function yovel_admin_projects_sections(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'owner_state' => null],
        'projects' => ['label' => 'Projects', 'icon' => 'work', 'owner_state' => null],
        'project-tasks' => ['label' => 'Project tasks', 'icon' => 'account_tree', 'owner_state' => null],
        'timesheets' => ['label' => 'Timesheets', 'icon' => 'schedule', 'owner_state' => 'timesheets'],
        'project-collaboration' => ['label' => 'Project collaboration', 'icon' => 'forum', 'owner_state' => 'notifications'],
        'project-summaries' => ['label' => 'Project summaries', 'icon' => 'assessment', 'owner_state' => null],
        'delayed-task-reports' => ['label' => 'Delayed task reports', 'icon' => 'pending_actions', 'owner_state' => null],
        'customer-portal-access' => ['label' => 'Customer portal access', 'icon' => 'person', 'owner_state' => 'customer'],
        'project-portal-access' => ['label' => 'Project portal access', 'icon' => 'language', 'owner_state' => 'portal'],
        'settings' => ['label' => 'Projects settings', 'icon' => 'settings', 'owner_state' => null],
        'form-builder' => ['label' => 'Form Builder', 'icon' => 'dynamic_form', 'owner_state' => null],
    ];
}

function yovel_admin_projects_section(string $requested = ''): string
{
    $requested = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($requested)), '-');
    return array_key_exists($requested, yovel_admin_projects_sections()) ? $requested : 'projects';
}

function yovel_admin_projects_db_execute(
    ADOConnection $db,
    string $sql,
    array $parameters,
    string $operation
): void {
    if ($db->Execute($sql, $parameters) !== false) {
        return;
    }

    $databaseError = trim((string) $db->ErrorMsg());
    error_log($operation . ' failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
    throw new RuntimeException($operation . ' could not be completed.');
}

function yovel_admin_projects_scope(array $company, array $admin): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyKeyHash = strtolower(trim((string) ($company['company_key_hash'] ?? '')));
    $adminKey = trim((string) ($admin['admin_key'] ?? ''));

    if ($companyKey === ''
        || strlen($companyKey) > 1500
        || preg_match('/^[0-9a-f]{64}$/', $companyKeyHash) !== 1
        || !hash_equals(hash('sha256', $companyKey), $companyKeyHash)
        || $adminKey === ''
        || (function_exists('yovel_admin_is_uuid') && !yovel_admin_is_uuid($adminKey))) {
        throw new InvalidArgumentException('Projects company scope is invalid.');
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
        [$adminKey, $companyKey, $companyKeyHash]
    );

    if (!is_array($authorized)
        || (string) ($authorized['company_key'] ?? '') !== $companyKey
        || strtolower((string) ($authorized['company_key_hash'] ?? '')) !== $companyKeyHash
        || (string) ($authorized['admin_key'] ?? '') !== $adminKey) {
        throw new RuntimeException('An authorized active company administrator is required for Projects.');
    }

    return [$companyKey, $companyKeyHash, $adminKey];
}

function yovel_admin_projects_assert_csrf(array $input): void
{
    $submitted = (string) ($input['csrf'] ?? '');
    if ($submitted === '' || !hash_equals(bx_csrf_token(), $submitted)) {
        throw new InvalidArgumentException('Projects request token is invalid.');
    }
}

function yovel_admin_projects_in_transaction(callable $operation): mixed
{
    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Projects transaction could not start.');
    }

    try {
        $result = $operation($db);
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Projects transaction could not commit.');
        }
        return $result;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_projects_assert_readback(
    array $expected,
    array $actual,
    array $fields,
    string $label
): void {
    foreach ($fields as $field) {
        $field = (string) $field;
        if (!array_key_exists($field, $expected)
            || !array_key_exists($field, $actual)
            || (string) $expected[$field] !== (string) $actual[$field]) {
            throw new RuntimeException($label . ' read-back verification failed for ' . $field . '.');
        }
    }
}

function yovel_admin_projects_audit(
    string $action,
    string $module,
    string $recordKey,
    string $companyKeyHash,
    string $adminKey,
    array $values = []
): void {
    bx_audit($action, $module, $recordKey, [
        'company_key_hash' => $companyKeyHash,
        'admin_key' => $adminKey,
    ] + $values, 'Company administrator changed a Projects record.');
}

function yovel_admin_projects_flag(mixed $value): int
{
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function yovel_admin_projects_owner_states(): array
{
    $contracts = [
        'customer' => ['owner' => 'Sales / CRM', 'contract' => 'yovel_admin_sales_customer_snapshot'],
        'employee' => ['owner' => 'HR', 'contract' => 'yovel_admin_hr_workforce_read_contract'],
        'timesheets' => ['owner' => 'HR', 'contract' => 'yovel_admin_hr_project_timesheets'],
        'assignments' => ['owner' => 'HR', 'contract' => 'yovel_admin_hr_assign_project_task'],
        'finance' => ['owner' => 'Finance', 'contract' => 'yovel_admin_finance_project_billing_snapshot'],
        'notifications' => ['owner' => 'Operations', 'contract' => 'yovel_admin_operations_notify'],
        'portal' => ['owner' => 'Operations', 'contract' => 'yovel_admin_operations_sync_portal_access'],
    ];

    foreach ($contracts as $key => $contract) {
        $contracts[$key]['available'] = function_exists((string) $contract['contract']);
        $contracts[$key]['state'] = $contracts[$key]['available'] ? 'AVAILABLE' : 'UNAVAILABLE';
    }
    return $contracts;
}

function yovel_admin_projects_state(string $section, array $ownerStates): array
{
    $meta = yovel_admin_projects_sections()[$section] ?? yovel_admin_projects_sections()['projects'];
    $ownerStateKey = (string) ($meta['owner_state'] ?? '');
    if ($ownerStateKey !== '' && empty($ownerStates[$ownerStateKey]['available'])) {
        return [
            'kind' => 'dependency',
            'title' => (string) $meta['label'] . ' is unavailable',
            'message' => (string) ($ownerStates[$ownerStateKey]['owner'] ?? 'Owner') . ' must expose its approved service contract.',
            'dependencies' => [(string) ($ownerStates[$ownerStateKey]['contract'] ?? '')],
        ];
    }

    if (in_array($section, ['projects', 'project-tasks'], true)) {
        return [
            'kind' => 'empty',
            'title' => $section === 'projects' ? 'No project records yet' : 'No project tasks yet',
            'message' => 'This company has no records in the selected Projects workspace.',
            'dependencies' => [],
        ];
    }

    return [
        'kind' => 'ready',
        'title' => (string) $meta['label'],
        'message' => '',
        'dependencies' => [],
    ];
}

function yovel_admin_projects_data(array $company, array $admin, string $section = ''): array
{
    [, $companyKeyHash] = yovel_admin_projects_scope($company, $admin);
    yovel_admin_projects_schema();
    $section = yovel_admin_projects_section($section !== '' ? $section : (string) ($_GET['section'] ?? 'projects'));
    $ownerStates = yovel_admin_projects_owner_states();
    $data = [
        'section' => $section,
        'sections' => yovel_admin_projects_sections(),
        'section_meta' => yovel_admin_projects_sections()[$section],
        'owner_states' => $ownerStates,
        'state' => yovel_admin_projects_state($section, $ownerStates),
        'company_name' => (string) ($company['company_name'] ?? 'Company'),
        'metrics' => [
            'projects' => (int) bx_db()->GetOne(
                "SELECT COUNT(*) FROM project_company_project_record WHERE company_key_hash = ? AND project_status <> 'DELETED'",
                [$companyKeyHash]
            ),
            'tasks' => (int) bx_db()->GetOne(
                "SELECT COUNT(*) FROM project_company_project_task WHERE company_key_hash = ? AND task_status <> 'DELETED'",
                [$companyKeyHash]
            ),
            'published_forms' => (int) bx_db()->GetOne(
                "SELECT COUNT(*) FROM project_company_project_form_schema WHERE company_key_hash = ? AND schema_status = 'PUBLISHED'",
                [$companyKeyHash]
            ),
        ],
        'form_state' => [],
    ];

    if (function_exists('yovel_admin_projects_settings')) {
        $data['settings'] = yovel_admin_projects_settings($company, $admin);
    }
    if (function_exists('yovel_admin_projects_builder_targets')) {
        $target = (string) ($_GET['target'] ?? 'PROJECT');
        try {
            $data['form_schema'] = yovel_admin_projects_active_schema($company, $target);
            $data['form_target'] = yovel_admin_projects_target($target);
        } catch (Throwable) {
            $data['form_schema'] = yovel_admin_projects_active_schema($company, 'PROJECT');
            $data['form_target'] = 'PROJECT';
        }
        $data['form_adapter'] = yovel_admin_projects_form_adapter();
        $data['form_history'] = yovel_admin_projects_schema_history($company, (string) $data['form_target']);
    }
    if ($section === 'dashboard') {
        $data['dashboard'] = yovel_admin_projects_dashboard_data($company, $admin);
    }
    if ($section === 'projects') {
        $data['project_types'] = yovel_admin_projects_project_types($company, $admin);
        $data['projects'] = yovel_admin_projects_records($company, $admin);
        $data['project_templates'] = yovel_admin_projects_templates($company, $admin);
        $data['workforce'] = [
            'available' => false,
            'contract' => 'hr.workforce.v1',
            'employees' => [],
        ];
        if (function_exists('yovel_admin_hr_workforce_read_contract')) {
            try {
                $workforce = yovel_admin_hr_workforce_read_contract($company, ['status' => 'ACTIVE']);
                if (($workforce['contract'] ?? '') === 'hr.workforce.v1'
                    && (string) ($workforce['company_key_hash'] ?? '') === $companyKeyHash
                    && is_array($workforce['employees'] ?? null)) {
                    $data['workforce'] = [
                        'available' => true,
                        'contract' => 'hr.workforce.v1',
                        'employees' => $workforce['employees'],
                    ];
                }
            } catch (Throwable) {
                $data['workforce']['error'] = 'HR workforce is temporarily unavailable.';
            }
        }
    }
    if ($section === 'project-tasks') {
        $data['projects'] = yovel_admin_projects_records($company, $admin);
        $data['task_types'] = yovel_admin_projects_task_types($company, $admin);
        $data['tasks'] = yovel_admin_projects_tasks($company, $admin);
        $data['assignment_dependency'] = [
            'contract' => 'yovel_admin_hr_assign_project_task',
            'available' => function_exists('yovel_admin_hr_assign_project_task'),
            'state' => function_exists('yovel_admin_hr_assign_project_task') ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY',
        ];
    }

    return $data;
}

function yovel_admin_projects_handle_post(
    array $company,
    array $admin,
    string $action,
    array $input
): array {
    yovel_admin_projects_assert_csrf($input);
    yovel_admin_projects_scope($company, $admin);

    if ($action === 'save_projects_settings') {
        yovel_admin_projects_save_settings($company, $admin, $input);
        return [
            'message' => 'Projects settings saved.',
            'section' => 'settings',
            'query' => [],
        ];
    }
    if ($action === 'save_projects_form_schema') {
        $schema = json_decode((string) ($input['schema_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($schema)) {
            throw new InvalidArgumentException('Projects form schema is invalid.');
        }
        $status = strtoupper(trim((string) ($input['schema_status'] ?? 'DRAFT')));
        $saved = yovel_admin_projects_save_form_schema(
            $company,
            $admin,
            (string) ($input['target_type'] ?? ''),
            $schema,
            $status,
            (string) ($input['csrf'] ?? '')
        );
        $messages = [
            'DRAFT' => 'Projects form draft saved.',
            'PUBLISHED' => 'Projects form version published.',
            'ARCHIVED' => 'Projects form version archived.',
        ];
        return [
            'message' => $messages[$status] ?? 'Projects form version saved.',
            'section' => 'form-builder',
            'query' => [
                'target' => (string) ($saved['targetType'] ?? ''),
                'version' => (string) ($saved['version'] ?? ''),
            ],
        ];
    }
    if ($action === 'save_project_type') {
        $saved = yovel_admin_project_type_upsert($company, $admin, $input);
        return [
            'message' => 'Project Type saved.',
            'section' => 'projects',
            'query' => ['project_type_key' => (string) $saved['project_type_key']],
        ];
    }
    if ($action === 'save_project') {
        $saved = yovel_admin_project_upsert($company, $admin, $input);
        return [
            'message' => 'Project saved.',
            'section' => 'projects',
            'query' => ['project_key' => (string) $saved['project_key']],
        ];
    }
    if ($action === 'transition_project') {
        $saved = yovel_admin_project_transition(
            $company,
            $admin,
            (string) ($input['project_key'] ?? ''),
            (string) ($input['transition'] ?? ''),
            (string) ($input['csrf'] ?? '')
        );
        return [
            'message' => 'Project status updated.',
            'section' => 'projects',
            'query' => ['project_key' => (string) $saved['project_key']],
        ];
    }
    if ($action === 'save_project_template') {
        $saved = yovel_admin_project_template_upsert($company, $admin, $input);
        return [
            'message' => 'Project Template saved.',
            'section' => 'projects',
            'query' => ['project_template_key' => (string) $saved['project_template_key']],
        ];
    }
    if ($action === 'create_project_from_template') {
        $saved = yovel_admin_project_create_from_template($company, $admin, $input);
        return [
            'message' => 'Project created from template.',
            'section' => 'projects',
            'query' => ['project_key' => (string) $saved['project_key']],
        ];
    }
    if ($action === 'save_task_type') {
        $saved = yovel_admin_task_type_upsert($company, $admin, $input);
        return [
            'message' => 'Task Type saved.',
            'section' => 'project-tasks',
            'query' => ['task_type_key' => (string) $saved['task_type_key']],
        ];
    }
    if ($action === 'save_project_task') {
        $saved = yovel_admin_project_task_upsert($company, $admin, $input);
        $assignment = is_array($saved['assignment'] ?? null) ? $saved['assignment'] : [];
        return [
            'message' => !empty($assignment['retryable'])
                ? 'Task saved. HR assignment is unavailable and can be retried.'
                : 'Task saved.',
            'section' => 'project-tasks',
            'query' => ['task_key' => (string) $saved['task_key']],
            'dependency_state' => (string) ($assignment['status'] ?? ''),
            'retryable' => !empty($assignment['retryable']),
        ];
    }
    if ($action === 'transition_project_task') {
        $saved = yovel_admin_project_task_transition(
            $company,
            $admin,
            (string) ($input['task_key'] ?? ''),
            (string) ($input['transition'] ?? ''),
            (string) ($input['csrf'] ?? '')
        );
        return [
            'message' => 'Task status updated.',
            'section' => 'project-tasks',
            'query' => ['task_key' => (string) $saved['task_key']],
        ];
    }
    if ($action === 'move_project_task') {
        $parentTaskKey = trim((string) ($input['parent_task_key'] ?? ''));
        $saved = yovel_admin_project_task_move(
            $company,
            $admin,
            (string) ($input['task_key'] ?? ''),
            $parentTaskKey === '' ? null : $parentTaskKey,
            (string) ($input['csrf'] ?? '')
        );
        return [
            'message' => 'Task moved.',
            'section' => 'project-tasks',
            'query' => ['task_key' => (string) $saved['task_key']],
        ];
    }
    if ($action === 'reschedule_project_task') {
        $saved = yovel_admin_project_task_reschedule(
            $company,
            $admin,
            (string) ($input['task_key'] ?? ''),
            (string) ($input['expected_start_date'] ?? ''),
            (string) ($input['expected_end_date'] ?? ''),
            (string) ($input['csrf'] ?? '')
        );
        return [
            'message' => 'Task and dependent schedule updated.',
            'section' => 'project-tasks',
            'query' => ['task_key' => (string) $saved['task_key']],
        ];
    }
    if ($action === 'retry_project_task_assignment') {
        $saved = yovel_admin_project_task_retry_assignment(
            $company,
            $admin,
            (string) ($input['task_key'] ?? ''),
            (string) ($input['csrf'] ?? '')
        );
        return [
            'message' => !empty($saved['assignment']['retryable'])
                ? 'HR assignment remains unavailable and can be retried.'
                : 'HR assignment synchronized.',
            'section' => 'project-tasks',
            'query' => ['task_key' => (string) $saved['task_key']],
            'dependency_state' => (string) ($saved['assignment']['status'] ?? ''),
            'retryable' => !empty($saved['assignment']['retryable']),
        ];
    }

    throw new InvalidArgumentException('Unknown Projects action.');
}
