<?php
declare(strict_types=1);

function yovel_admin_assets_dashboard_dependency_definitions(): array
{
    return [
        'inventory-warehouse.catalogue.v1' => [
            'owner' => 'inventory-warehouse',
            'owner_function' => 'yovel_admin_inventory_items',
            'label' => 'Inventory item catalogue',
        ],
        'hr.workforce.v1' => [
            'owner' => 'hr',
            'owner_function' => 'yovel_admin_hr_workforce_read_contract',
            'label' => 'HR workforce directory',
        ],
        'accounting-finance.asset-posting.v1' => [
            'owner' => 'accounting-finance',
            'owner_function' => 'yovel_admin_finance_asset_posting_contract',
            'label' => 'Asset posting command',
        ],
    ];
}

function yovel_admin_assets_dashboard_dependency_gateway(array $overrides = []): array
{
    $definitions = yovel_admin_assets_dashboard_dependency_definitions();
    foreach ($overrides as $contract => $provider) {
        if (!array_key_exists($contract, $definitions)) {
            throw new InvalidArgumentException('Assets dashboard dependency is not allow-listed: ' . $contract . '.');
        }
        if (!is_callable($provider)) {
            throw new InvalidArgumentException('Assets dashboard dependency provider must be callable: ' . $contract . '.');
        }
    }

    $gateway = [];
    foreach ($definitions as $contract => $definition) {
        $hasOverride = array_key_exists($contract, $overrides);
        $ownerFunction = (string) $definition['owner_function'];
        $gateway[$contract] = $definition + [
            'contract' => $contract,
            'available' => $hasOverride || function_exists($ownerFunction),
            'callable' => $hasOverride ? $overrides[$contract] : null,
            'source' => $hasOverride ? 'test-override' : 'owner-service',
        ];
    }
    return $gateway;
}

function yovel_admin_assets_dashboard_dependency_health(array $company, array $gateway): array
{
    [, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $health = [];
    foreach (yovel_admin_assets_dashboard_dependency_definitions() as $contract => $definition) {
        $entry = is_array($gateway[$contract] ?? null) ? $gateway[$contract] : [];
        $status = empty($entry['available']) ? 'UNAVAILABLE_DEPENDENCY' : 'AVAILABLE';
        $recordCount = null;
        $detail = $status === 'AVAILABLE' ? 'Owner read contract registered.' : 'Owner read contract is not registered.';
        if ($contract === 'accounting-finance.asset-posting.v1' && $status === 'AVAILABLE') {
            try {
                $provider = is_callable($entry['callable'] ?? null)
                    ? $entry['callable']
                    : (string) ($entry['owner_function'] ?? '');
                if (!is_callable($provider)) {
                    throw new RuntimeException('Finance Asset posting contract is unavailable.');
                }
                $result = $provider($company);
                if (!is_array($result)
                    || (string) ($result['contract'] ?? '') !== $contract
                    || (string) ($result['owner'] ?? '') !== 'accounting-finance'
                    || (string) ($result['owner_function'] ?? '') !== 'yovel_admin_finance_asset_posting_request'
                    || (string) ($result['transaction_owner'] ?? '') !== 'CALLER'
                    || ($result['operations'] ?? null) !== ['DRAFT', 'POST', 'REVERSE']) {
                    throw new RuntimeException('Finance Asset posting contract is invalid.');
                }
                $detail = 'Caller-owned draft, post, and reversal contract verified.';
            } catch (Throwable) {
                $status = 'ERROR';
                $detail = 'Owner command health check failed safely.';
            }
        }
        if ($status === 'AVAILABLE' && is_callable($entry['callable'] ?? null)) {
            if ($contract === 'accounting-finance.asset-posting.v1') {
                $health[] = [
                    'contract' => $contract,
                    'owner' => (string) $definition['owner'],
                    'label' => (string) $definition['label'],
                    'status' => $status,
                    'record_count' => null,
                    'detail' => $detail,
                ];
                continue;
            }
            try {
                $result = ($entry['callable'])($company);
                if (!is_array($result)
                    || (string) ($result['contract'] ?? '') !== $contract
                    || !hash_equals($companyKeyHash, strtolower(trim((string) ($result['company_key_hash'] ?? ''))))
                    || !is_array($result['records'] ?? null)) {
                    throw new RuntimeException('Dependency response scope is invalid.');
                }
                $recordCount = count($result['records']);
                $detail = 'Company-scoped read verified.';
            } catch (Throwable) {
                $status = 'ERROR';
                $recordCount = null;
                $detail = 'Owner read health check failed safely.';
            }
        }
        $health[] = [
            'contract' => $contract,
            'owner' => (string) $definition['owner'],
            'label' => (string) $definition['label'],
            'status' => $status,
            'record_count' => $recordCount,
            'detail' => $detail,
        ];
    }
    return $health;
}

function yovel_admin_assets_dashboard_tables(): array
{
    return [
        'forms' => 'project_company_asset_form',
        'versions' => 'project_company_asset_form_version',
        'submissions' => 'project_company_asset_form_submission',
        'audit' => 'project_company_asset_form_audit',
        'assets' => 'project_company_asset',
        'asset_activity' => 'project_company_asset_activity',
        'movements' => 'project_company_asset_movement',
        'finance_books' => 'project_company_asset_finance_book',
        'depreciation_schedules' => 'project_company_asset_depreciation_schedule',
        'depreciation_lines' => 'project_company_asset_depreciation_line',
    ];
}

function yovel_admin_assets_dashboard_table_health(ADOConnection $db): array
{
    $health = [];
    foreach (yovel_admin_assets_dashboard_tables() as $key => $table) {
        $health[$key] = (int) $db->GetOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        ) === 1;
    }
    return $health;
}

function yovel_admin_assets_dashboard_href(string $section): string
{
    return './?view=assets-maintenance&section=' . rawurlencode($section);
}

function yovel_admin_assets_maintenance_dashboard_data(array $company, array $admin): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_scope($company, $admin);
    $db = bx_db();
    $tableHealth = yovel_admin_assets_dashboard_table_health($db);
    $metricsReady = !empty($tableHealth['forms']) && !empty($tableHealth['versions']) && !empty($tableHealth['submissions']);
    $foundationReady = $metricsReady && !empty($tableHealth['audit']);
    $metrics = ['forms' => null, 'published_forms' => null, 'draft_forms' => null, 'versions' => null, 'submissions' => null, 'active_assets' => null, 'draft_assets' => null, 'draft_movements' => null, 'finance_books' => null, 'draft_schedules' => null, 'due_depreciation' => null];
    $metricsStatus = $metricsReady ? 'AVAILABLE' : 'ERROR';
    $metricError = !$metricsReady;

    if ($metricsReady) {
        try {
            $formMetrics = $db->GetRow(
                "SELECT COUNT(*) AS form_count,
                        SUM(CASE WHEN form_status = 'PUBLISHED' THEN 1 ELSE 0 END) AS published_count,
                        SUM(CASE WHEN form_status = 'DRAFT' THEN 1 ELSE 0 END) AS draft_count
                 FROM project_company_asset_form
                 WHERE company_key = ? AND company_key_hash = ?",
                [$companyKey, $companyKeyHash]
            );
            if (!is_array($formMetrics)) {
                throw new RuntimeException('Assets form metrics could not be read.');
            }
            $metrics = array_merge($metrics, [
                'forms' => (int) ($formMetrics['form_count'] ?? 0),
                'published_forms' => (int) ($formMetrics['published_count'] ?? 0),
                'draft_forms' => (int) ($formMetrics['draft_count'] ?? 0),
                'versions' => (int) $db->GetOne(
                    'SELECT COUNT(*) FROM project_company_asset_form_version WHERE company_key = ? AND company_key_hash = ?',
                    [$companyKey, $companyKeyHash]
                ),
                'submissions' => (int) $db->GetOne(
                    "SELECT COUNT(*) FROM project_company_asset_form_submission WHERE company_key = ? AND company_key_hash = ? AND submission_status = 'SUBMITTED'",
                    [$companyKey, $companyKeyHash]
                ),
            ]);
        } catch (Throwable) {
            $metrics = array_merge($metrics, ['forms' => null, 'published_forms' => null, 'draft_forms' => null, 'versions' => null, 'submissions' => null]);
            $metricsStatus = 'ERROR';
            $metricError = true;
        }
    }

    $assetMetricsStatus = !empty($tableHealth['assets']) ? 'AVAILABLE' : 'ERROR';
    if ($assetMetricsStatus === 'AVAILABLE') {
        try {
            $assetMetrics = $db->GetRow(
                "SELECT SUM(CASE WHEN document_status = 'SUBMITTED' AND lifecycle_status NOT IN ('SOLD','SCRAPPED') THEN 1 ELSE 0 END) AS active_count,
                        SUM(CASE WHEN document_status = 'DRAFT' THEN 1 ELSE 0 END) AS draft_count
                 FROM project_company_asset WHERE company_key = ? AND company_key_hash = ?",
                [$companyKey, $companyKeyHash]
            );
            $metrics['active_assets'] = (int) ($assetMetrics['active_count'] ?? 0);
            $metrics['draft_assets'] = (int) ($assetMetrics['draft_count'] ?? 0);
            $metrics['draft_movements'] = !empty($tableHealth['movements']) ? (int) $db->GetOne("SELECT COUNT(*) FROM project_company_asset_movement WHERE company_key = ? AND company_key_hash = ? AND document_status = 'DRAFT'", [$companyKey, $companyKeyHash]) : null;
        } catch (Throwable) {
            $assetMetricsStatus = 'ERROR';
            $metrics['active_assets'] = null;
            $metrics['draft_assets'] = null;
            $metrics['draft_movements'] = null;
        }
    }
    if (!empty($tableHealth['finance_books']) && !empty($tableHealth['depreciation_schedules']) && !empty($tableHealth['depreciation_lines'])) {
        try {
            $metrics['finance_books'] = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_asset_finance_book WHERE company_key=? AND company_key_hash=? AND book_status='ACTIVE'", [$companyKey, $companyKeyHash]);
            $metrics['draft_schedules'] = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_asset_depreciation_schedule WHERE company_key=? AND company_key_hash=? AND document_status='DRAFT' AND lifecycle_status='ACTIVE'", [$companyKey, $companyKeyHash]);
            $metrics['due_depreciation'] = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_asset_depreciation_line line INNER JOIN project_company_asset_depreciation_schedule schedule ON schedule.company_key_hash=line.company_key_hash AND schedule.schedule_key=line.schedule_key WHERE line.company_key=? AND line.company_key_hash=? AND line.posting_status='PENDING' AND line.posting_date<=CURRENT_DATE AND schedule.document_status='SUBMITTED' AND schedule.lifecycle_status='ACTIVE'", [$companyKey, $companyKeyHash]);
        } catch (Throwable) {
            $metrics['finance_books'] = null;
            $metrics['draft_schedules'] = null;
            $metrics['due_depreciation'] = null;
        }
    }

    $activity = [];
    $activityError = false;
    if (!empty($tableHealth['audit']) && !empty($tableHealth['forms'])) {
        try {
            $rows = $db->GetAll(
                "SELECT audit.audit_key, audit.audit_action, audit.form_key, audit.form_version_key,
                        audit.submission_key, audit.created_by_admin_key, audit.created_at,
                        COALESCE(form.form_title, 'Assets form') AS record_label
                 FROM project_company_asset_form_audit audit
                 LEFT JOIN project_company_asset_form form
                   ON form.company_key = audit.company_key
                  AND form.company_key_hash = audit.company_key_hash
                  AND form.form_key = audit.form_key
                 WHERE audit.company_key = ? AND audit.company_key_hash = ?
                 ORDER BY audit.x_id DESC
                 LIMIT 8",
                [$companyKey, $companyKeyHash]
            );
            foreach (is_array($rows) ? $rows : [] as $row) {
                $activity[] = [
                    'key' => (string) $row['audit_key'],
                    'action' => (string) $row['audit_action'],
                    'record_label' => (string) $row['record_label'],
                    'actor_label' => 'Company administrator',
                    'occurred_at' => (string) $row['created_at'],
                    'href' => yovel_admin_assets_dashboard_href('form-builder') . '&form=' . rawurlencode((string) $row['form_key']),
                ];
            }
        } catch (Throwable) {
            $activity = [];
            $activityError = true;
        }
    } elseif (empty($tableHealth['audit'])) {
        $activityError = true;
    }
    if (!empty($tableHealth['asset_activity']) && !empty($tableHealth['assets'])) {
        try {
            $rows = $db->GetAll(
                "SELECT activity.activity_key, activity.activity_type, activity.asset_key,
                        activity.created_by_admin_key, activity.activity_at,
                        COALESCE(asset.asset_code, 'Asset') AS record_label
                 FROM project_company_asset_activity activity
                 LEFT JOIN project_company_asset asset
                   ON asset.company_key = activity.company_key
                  AND asset.company_key_hash = activity.company_key_hash
                  AND asset.asset_key = activity.asset_key
                 WHERE activity.company_key = ? AND activity.company_key_hash = ?
                 ORDER BY activity.x_id DESC LIMIT 8",
                [$companyKey, $companyKeyHash]
            );
            foreach (is_array($rows) ? $rows : [] as $row) {
                $activity[] = [
                    'key' => (string) $row['activity_key'],
                    'action' => (string) $row['activity_type'],
                    'record_label' => (string) $row['record_label'],
                    'actor_label' => 'Company administrator',
                    'occurred_at' => (string) $row['activity_at'],
                    'href' => yovel_admin_assets_dashboard_href('asset-records') . '&asset=' . rawurlencode((string) $row['asset_key']),
                ];
            }
            usort($activity, static fn (array $left, array $right): int => strcmp((string) $right['occurred_at'], (string) $left['occurred_at']));
            $activity = array_slice($activity, 0, 8);
        } catch (Throwable) {
            $activityError = true;
        }
    }

    $overrides = is_array($GLOBALS['yovel_admin_assets_dashboard_dependency_providers'] ?? null)
        ? $GLOBALS['yovel_admin_assets_dashboard_dependency_providers']
        : [];
    $dependencies = yovel_admin_assets_dashboard_dependency_health(
        $company,
        yovel_admin_assets_dashboard_dependency_gateway($overrides)
    );
    $dependencyByContract = array_column($dependencies, null, 'contract');

    $summary = [
        ['key' => 'forms', 'label' => 'Company forms', 'value' => $metrics['forms'], 'availability' => $metricsStatus, 'href' => yovel_admin_assets_dashboard_href('form-builder')],
        ['key' => 'published-forms', 'label' => 'Published forms', 'value' => $metrics['published_forms'], 'availability' => $metricsStatus, 'href' => yovel_admin_assets_dashboard_href('form-builder')],
        ['key' => 'submitted-records', 'label' => 'Submitted records', 'value' => $metrics['submissions'], 'availability' => $metricsStatus, 'href' => yovel_admin_assets_dashboard_href('form-builder')],
        ['key' => 'active-assets', 'label' => 'Active assets', 'value' => $metrics['active_assets'], 'availability' => $assetMetricsStatus, 'href' => yovel_admin_assets_dashboard_href('asset-records')],
        ['key' => 'maintenance-due', 'label' => 'Maintenance due', 'value' => null, 'availability' => 'UNAVAILABLE_DEPENDENCY', 'href' => yovel_admin_assets_dashboard_href('maintenance-schedules')],
        ['key' => 'open-work-orders', 'label' => 'Open work orders', 'value' => null, 'availability' => 'UNAVAILABLE_DEPENDENCY', 'href' => yovel_admin_assets_dashboard_href('maintenance-schedules')],
    ];

    $queue = [];
    if (is_int($metrics['draft_forms']) && $metrics['draft_forms'] > 0) {
        $queue[] = ['key' => 'draft-forms', 'label' => $metrics['draft_forms'] . ' draft form' . ($metrics['draft_forms'] === 1 ? '' : 's') . ' awaiting review', 'status' => 'DRAFT', 'href' => yovel_admin_assets_dashboard_href('form-builder')];
    }
    if (is_int($metrics['draft_assets']) && $metrics['draft_assets'] > 0) {
        $queue[] = ['key' => 'draft-assets', 'label' => $metrics['draft_assets'] . ' draft Asset' . ($metrics['draft_assets'] === 1 ? '' : 's') . ' awaiting submission', 'status' => 'DRAFT', 'href' => yovel_admin_assets_dashboard_href('asset-records')];
    }
    if (is_int($metrics['draft_movements']) && $metrics['draft_movements'] > 0) {
        $queue[] = ['key' => 'draft-movements', 'label' => $metrics['draft_movements'] . ' draft movement' . ($metrics['draft_movements'] === 1 ? '' : 's') . ' awaiting submission', 'status' => 'DRAFT', 'href' => yovel_admin_assets_dashboard_href('asset-records')];
    }
    if (is_int($metrics['draft_schedules']) && $metrics['draft_schedules'] > 0) {
        $queue[] = ['key' => 'draft-schedules', 'label' => $metrics['draft_schedules'] . ' draft depreciation Schedule' . ($metrics['draft_schedules'] === 1 ? '' : 's') . ' awaiting submission', 'status' => 'DRAFT', 'href' => yovel_admin_assets_dashboard_href('asset-depreciation-schedule')];
    }
    if (is_int($metrics['due_depreciation']) && $metrics['due_depreciation'] > 0) {
        $queue[] = ['key' => 'due-depreciation', 'label' => $metrics['due_depreciation'] . ' depreciation line' . ($metrics['due_depreciation'] === 1 ? '' : 's') . ' due for catch-up', 'status' => 'DUE', 'href' => yovel_admin_assets_dashboard_href('asset-depreciation-schedule')];
    }
    $queue[] = ['key' => 'maintenance-work', 'label' => 'Maintenance schedules and work orders', 'status' => 'UNAVAILABLE_DEPENDENCY', 'href' => yovel_admin_assets_dashboard_href('maintenance-schedules')];

    $setup = [
        ['key' => 'foundation', 'label' => 'Verify Assets foundation', 'complete' => $foundationReady, 'href' => yovel_admin_assets_dashboard_href('dashboard')],
        ['key' => 'create-form', 'label' => 'Create a company form', 'complete' => is_int($metrics['forms']) && $metrics['forms'] > 0, 'href' => yovel_admin_assets_dashboard_href('form-builder')],
        ['key' => 'publish-form', 'label' => 'Publish a form version', 'complete' => is_int($metrics['published_forms']) && $metrics['published_forms'] > 0, 'href' => yovel_admin_assets_dashboard_href('form-builder')],
        ['key' => 'submit-record', 'label' => 'Verify a version-bound submission', 'complete' => is_int($metrics['submissions']) && $metrics['submissions'] > 0, 'href' => yovel_admin_assets_dashboard_href('form-builder')],
        ['key' => 'owner-contracts', 'label' => 'Verify Inventory, HR, and Finance owners', 'complete' => ($dependencyByContract['inventory-warehouse.catalogue.v1']['status'] ?? '') === 'AVAILABLE' && ($dependencyByContract['hr.workforce.v1']['status'] ?? '') === 'AVAILABLE' && ($dependencyByContract['accounting-finance.asset-posting.v1']['status'] ?? '') === 'AVAILABLE', 'href' => yovel_admin_assets_dashboard_href('dashboard')],
        ['key' => 'asset-register', 'label' => 'Create the Asset register', 'complete' => is_int($metrics['active_assets']) && ($metrics['active_assets'] > 0 || (int) ($metrics['draft_assets'] ?? 0) > 0), 'href' => yovel_admin_assets_dashboard_href('asset-records')],
        ['key' => 'depreciation', 'label' => 'Configure an Asset Finance Book', 'complete' => is_int($metrics['finance_books']) && $metrics['finance_books'] > 0, 'href' => yovel_admin_assets_dashboard_href('asset-depreciation-schedule')],
    ];

    $alerts = [];
    if ($metricError) {
        $alerts[] = ['key' => 'foundation-read', 'severity' => 'ERROR', 'label' => 'Assets foundation metrics are unavailable.', 'availability' => 'ERROR', 'href' => yovel_admin_assets_dashboard_href('dashboard')];
    }
    if ($activityError) {
        $alerts[] = ['key' => 'activity-read', 'severity' => 'WARNING', 'label' => 'Recent Assets activity is unavailable.', 'availability' => 'ERROR', 'href' => yovel_admin_assets_dashboard_href('dashboard')];
    }
    foreach ($dependencies as $dependency) {
        $status = (string) $dependency['status'];
        if ($status === 'AVAILABLE') {
            continue;
        }
        $alerts[] = [
            'key' => 'dependency-' . substr(hash('sha256', (string) $dependency['contract']), 0, 16),
            'severity' => $status === 'ERROR' ? 'ERROR' : 'WARNING',
            'label' => (string) $dependency['label'] . ': ' . (string) $dependency['detail'],
            'availability' => $status,
            'href' => yovel_admin_assets_dashboard_href('dashboard'),
        ];
    }

    $shortcuts = [
        ['key' => 'form-builder', 'label' => 'Form Builder', 'href' => yovel_admin_assets_dashboard_href('form-builder'), 'available' => true, 'availability' => 'AVAILABLE', 'icon' => 'dynamic_form'],
        ['key' => 'asset-records', 'label' => 'Asset records', 'href' => yovel_admin_assets_dashboard_href('asset-records'), 'available' => true, 'availability' => 'AVAILABLE', 'icon' => 'precision_manufacturing'],
        ['key' => 'depreciation', 'label' => 'Depreciation', 'href' => yovel_admin_assets_dashboard_href('asset-depreciation-schedule'), 'available' => true, 'availability' => 'AVAILABLE', 'icon' => 'trending_down'],
        ['key' => 'maintenance-schedules', 'label' => 'Maintenance schedules', 'href' => yovel_admin_assets_dashboard_href('maintenance-schedules'), 'available' => false, 'availability' => 'NOT_IMPLEMENTED', 'icon' => 'event_repeat'],
        ['key' => 'fixed-asset-register', 'label' => 'Fixed asset register', 'href' => yovel_admin_assets_dashboard_href('fixed-asset-register'), 'available' => false, 'availability' => 'UNAVAILABLE_DEPENDENCY', 'icon' => 'table_view'],
    ];

    $directories = [
        ['group' => 'Lifecycle', 'items' => [
            ['key' => 'assets', 'label' => 'Asset records', 'href' => yovel_admin_assets_dashboard_href('asset-records'), 'available' => true, 'availability' => 'AVAILABLE'],
            ['key' => 'depreciation', 'label' => 'Depreciation schedules', 'href' => yovel_admin_assets_dashboard_href('asset-depreciation-schedule'), 'available' => true, 'availability' => 'AVAILABLE'],
            ['key' => 'maintenance', 'label' => 'Maintenance schedules', 'href' => yovel_admin_assets_dashboard_href('maintenance-schedules'), 'available' => false, 'availability' => 'NOT_IMPLEMENTED'],
        ]],
        ['group' => 'Reports', 'items' => [
            ['key' => 'fixed-register', 'label' => 'Fixed asset register', 'href' => yovel_admin_assets_dashboard_href('fixed-asset-register'), 'available' => false, 'availability' => 'UNAVAILABLE_DEPENDENCY'],
            ['key' => 'maintenance-reports', 'label' => 'Maintenance reports', 'href' => yovel_admin_assets_dashboard_href('maintenance-reports'), 'available' => false, 'availability' => 'NOT_IMPLEMENTED'],
            ['key' => 'quality-inspection', 'label' => 'Quality inspection', 'href' => yovel_admin_assets_dashboard_href('quality-inspection'), 'available' => false, 'availability' => 'UNAVAILABLE_DEPENDENCY'],
        ]],
        ['group' => 'Configuration', 'items' => [
            ['key' => 'forms', 'label' => 'Form Builder', 'href' => yovel_admin_assets_dashboard_href('form-builder'), 'available' => true, 'availability' => 'AVAILABLE'],
        ]],
    ];

    return [
        'summary' => $summary,
        'queue' => $queue,
        'activity' => $activity,
        'setup' => $setup,
        'alerts' => $alerts,
        'shortcuts' => $shortcuts,
        'directories' => $directories,
        'dependencies' => $dependencies,
        'foundation' => ['available' => $foundationReady, 'tables' => $tableHealth, 'versions' => $metrics['versions']],
        'tour_storage_key' => 'builderx:assets-maintenance:tour:' . substr($companyKeyHash, 0, 24),
    ];
}
