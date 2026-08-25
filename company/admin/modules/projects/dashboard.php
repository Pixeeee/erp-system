<?php
declare(strict_types=1);

function yovel_admin_projects_dashboard_dependencies(): array
{
    $definitions = [
        'customers' => [
            'label' => 'Customer coverage',
            'contract' => 'yovel_admin_sales_customer_snapshot',
            'availability' => function_exists('yovel_admin_sales_customer_snapshot') ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY',
        ],
        'employees' => [
            'label' => 'Employee coverage',
            'contract' => 'yovel_admin_hr_workforce_read_contract',
            'availability' => function_exists('yovel_admin_hr_workforce_read_contract') ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY',
        ],
        'tasks' => [
            'label' => 'Task delivery',
            'contract' => 'yovel_admin_project_task_upsert',
            'availability' => function_exists('yovel_admin_project_task_upsert') ? 'AVAILABLE' : 'NOT_IMPLEMENTED',
        ],
        'milestones' => [
            'label' => 'Milestone progress',
            'contract' => 'projects.milestone-package.v1',
            'availability' => function_exists('yovel_admin_project_milestone_snapshot') ? 'AVAILABLE' : 'NOT_IMPLEMENTED',
        ],
        'time' => [
            'label' => 'Recorded time',
            'contract' => 'yovel_admin_hr_project_timesheets',
            'availability' => function_exists('yovel_admin_hr_project_timesheets') ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY',
        ],
        'billing' => [
            'label' => 'Project billing',
            'contract' => 'yovel_admin_finance_project_billing_snapshot',
            'availability' => function_exists('yovel_admin_finance_project_billing_snapshot') ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY',
        ],
        'utilization' => [
            'label' => 'Team utilization',
            'contract' => 'hr.timesheets.v1 + finance.project-billing.v1',
            'availability' => function_exists('yovel_admin_hr_project_timesheets')
                && function_exists('yovel_admin_finance_project_billing_snapshot')
                    ? 'AVAILABLE'
                    : 'UNAVAILABLE_DEPENDENCY',
        ],
        'budget' => [
            'label' => 'Budget performance',
            'contract' => 'yovel_admin_finance_project_billing_snapshot',
            'availability' => function_exists('yovel_admin_finance_project_billing_snapshot') ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY',
        ],
    ];

    $dependencies = [];
    foreach ($definitions as $key => $definition) {
        $dependencies[] = [
            'key' => $key,
            'label' => $definition['label'],
            'contract' => $definition['contract'],
            'status' => $definition['availability'],
        ];
    }
    return $dependencies;
}

function yovel_admin_projects_dashboard_data(array $company, array $admin): array
{
    [, $companyKeyHash, $adminKey] = yovel_admin_projects_scope($company, $admin);
    yovel_admin_projects_schema();
    $db = bx_db();
    $ownedTables = [
        'project_company_project_settings',
        'project_company_project_type',
        'project_company_project_task_type',
        'project_company_project_activity_type',
        'project_company_project_activity_cost',
        'project_company_project_record',
        'project_company_project_template',
        'project_company_project_template_task',
        'project_company_project_task',
        'project_company_project_task_dependency',
        'project_company_project_user',
        'project_company_project_update',
        'project_company_project_form_schema',
    ];
    $placeholders = implode(',', array_fill(0, count($ownedTables), '?'));
    $foundationCount = (int) $db->GetOne(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$placeholders})",
        array_merge([BUILDERX_DB_NAME], $ownedTables)
    );
    $settings = yovel_admin_projects_settings($company, $admin);
    $publishedForms = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_project_form_schema
         WHERE company_key_hash = ? AND schema_status = 'PUBLISHED'",
        [$companyKeyHash]
    );
    $draftForms = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_project_form_schema
         WHERE company_key_hash = ? AND schema_status = 'DRAFT'",
        [$companyKeyHash]
    );
    $taskCount = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company_project_task
         WHERE company_key_hash = ? AND task_status <> 'DELETED'",
        [$companyKeyHash]
    );

    $dependencies = yovel_admin_projects_dashboard_dependencies();
    $summary = [
        [
            'key' => 'foundation', 'label' => 'Foundation tables', 'value' => $foundationCount,
            'availability' => $foundationCount === count($ownedTables) ? 'AVAILABLE' : 'ERROR',
            'href' => './?view=projects&section=dashboard',
        ],
        [
            'key' => 'settings', 'label' => 'Projects settings',
            'value' => !empty($settings['persisted']) ? 'Configured' : 'Not configured',
            'availability' => 'AVAILABLE', 'href' => './?view=projects&section=settings',
        ],
        [
            'key' => 'published-forms', 'label' => 'Published forms', 'value' => $publishedForms,
            'availability' => 'AVAILABLE', 'href' => './?view=projects&section=form-builder',
        ],
    ];
    foreach ($dependencies as $dependency) {
        $summary[] = [
            'key' => $dependency['key'],
            'label' => $dependency['label'],
            'value' => $dependency['key'] === 'tasks' && $dependency['status'] === 'AVAILABLE'
                ? $taskCount
                : ($dependency['status'] === 'AVAILABLE' ? 'Ready' : 'Unavailable'),
            'availability' => $dependency['status'],
            'href' => $dependency['key'] === 'tasks'
                ? './?view=projects&section=project-tasks'
                : './?view=projects&section=dashboard',
        ];
    }

    $setup = [
        [
            'key' => 'foundation', 'label' => 'Verify Projects foundation',
            'complete' => $foundationCount === count($ownedTables),
            'href' => './?view=projects&section=dashboard',
        ],
        [
            'key' => 'settings', 'label' => 'Configure Projects policies',
            'complete' => !empty($settings['persisted']),
            'href' => './?view=projects&section=settings',
        ],
        [
            'key' => 'form-builder', 'label' => 'Publish a record form',
            'complete' => $publishedForms > 0,
            'href' => './?view=projects&section=form-builder',
        ],
    ];
    $queue = [];
    if (empty($settings['persisted'])) {
        $queue[] = [
            'key' => 'configure-settings', 'label' => 'Configure Projects settings',
            'status' => 'OPEN', 'href' => './?view=projects&section=settings',
        ];
    }
    if ($publishedForms === 0) {
        $queue[] = [
            'key' => 'publish-form', 'label' => 'Publish the first Projects form',
            'status' => 'OPEN', 'href' => './?view=projects&section=form-builder',
        ];
    }
    if ($draftForms > 0) {
        $queue[] = [
            'key' => 'review-drafts', 'label' => 'Review ' . $draftForms . ' draft form' . ($draftForms === 1 ? '' : 's'),
            'status' => 'OPEN', 'href' => './?view=projects&section=form-builder',
        ];
    }

    $auditRows = $db->GetAll(
        "SELECT audit_key, action, module, record_key, new_values, created_at
         FROM builder_audit_log
         WHERE module LIKE 'project_company_project_%'
           AND JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.company_key_hash')) = ?
         ORDER BY created_at DESC, x_id DESC
         LIMIT 8",
        [$companyKeyHash]
    );
    $activity = [];
    foreach (is_array($auditRows) ? $auditRows : [] as $row) {
        $values = json_decode((string) ($row['new_values'] ?? '{}'), true);
        $actor = is_array($values) ? (string) ($values['admin_key'] ?? $adminKey) : $adminKey;
        $label = match ((string) ($row['module'] ?? '')) {
            'project_company_project_settings' => 'Projects settings',
            'project_company_project_form_schema' => 'Projects form version',
            default => 'Projects record',
        };
        $activity[] = [
            'key' => 'project-activity-' . (string) ($row['audit_key'] ?? ''),
            'action' => (string) ($row['action'] ?? 'CHANGE'),
            'record_label' => $label,
            'actor_label' => $actor,
            'occurred_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    $alerts = [];
    if ($foundationCount !== count($ownedTables)) {
        $alerts[] = [
            'key' => 'foundation-error', 'severity' => 'ERROR',
            'label' => 'Projects foundation is incomplete.', 'href' => './?view=projects&section=dashboard',
        ];
    }
    foreach ($dependencies as $dependency) {
        if ($dependency['status'] === 'AVAILABLE') {
            continue;
        }
        $alerts[] = [
            'key' => 'dependency-' . $dependency['key'],
            'severity' => 'WARNING',
            'label' => $dependency['label'] . ' is unavailable until ' . $dependency['contract'] . ' is ready.',
            'href' => './?view=projects&section=dashboard',
        ];
    }

    return [
        'summary' => $summary,
        'queue' => $queue,
        'activity' => $activity,
        'setup' => $setup,
        'alerts' => $alerts,
        'shortcuts' => [
            ['key' => 'projects', 'label' => 'Projects', 'href' => './?view=projects&section=projects', 'available' => true],
            ['key' => 'tasks', 'label' => 'Project tasks', 'href' => './?view=projects&section=project-tasks', 'available' => true],
            ['key' => 'settings', 'label' => 'Projects settings', 'href' => './?view=projects&section=settings', 'available' => true],
            ['key' => 'form-builder', 'label' => 'Form Builder', 'href' => './?view=projects&section=form-builder', 'available' => true],
        ],
        'directories' => [
            [
                'group' => 'Reports',
                'items' => [
                    ['label' => 'Project summaries', 'href' => './?view=projects&section=project-summaries', 'available' => true],
                    ['label' => 'Delayed task reports', 'href' => './?view=projects&section=delayed-task-reports', 'available' => true],
                    ['label' => 'Timesheets', 'href' => './?view=projects&section=timesheets', 'available' => false],
                ],
            ],
            [
                'group' => 'Masters',
                'items' => [
                    ['label' => 'Projects', 'href' => './?view=projects&section=projects', 'available' => true],
                    ['label' => 'Project tasks', 'href' => './?view=projects&section=project-tasks', 'available' => true],
                    ['label' => 'Projects settings', 'href' => './?view=projects&section=settings', 'available' => true],
                    ['label' => 'Form Builder', 'href' => './?view=projects&section=form-builder', 'available' => true],
                ],
            ],
        ],
        'dependencies' => $dependencies,
    ];
}
