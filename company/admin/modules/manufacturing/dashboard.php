<?php
declare(strict_types=1);

function yovel_admin_manufacturing_dashboard_data(array $company, array $admin): array
{
    [, $companyKeyHash] = yovel_admin_manufacturing_scope($company, $admin);
    $db = bx_db();
    $errors = [];
    $readOne = static function (string $key, string $sql, array $parameters, mixed $fallback) use ($db, &$errors): mixed {
        try {
            $value = $db->GetOne($sql, $parameters);
            if ($value === false) {
                throw new RuntimeException('Dashboard read failed.');
            }
            return $value;
        } catch (Throwable) {
            $errors[$key] = true;
            return $fallback;
        }
    };
    $readRow = static function (string $key, string $sql, array $parameters, array $fallback) use ($db, &$errors): array {
        try {
            $row = $db->GetRow($sql, $parameters);
            if ($row === false) {
                throw new RuntimeException('Dashboard read failed.');
            }
            return is_array($row) ? $row : $fallback;
        } catch (Throwable) {
            $errors[$key] = true;
            return $fallback;
        }
    };
    $readAll = static function (string $key, string $sql, array $parameters) use ($db, &$errors): array {
        try {
            $rows = $db->GetAll($sql, $parameters);
            if ($rows === false) {
                throw new RuntimeException('Dashboard read failed.');
            }
            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            $errors[$key] = true;
            return [];
        }
    };

    $settingsConfigured = (int) $readOne(
        'settings',
        "SELECT COUNT(*) FROM project_company_manufacturing_setting WHERE company_key_hash=? AND setting_status='ACTIVE'",
        [$companyKeyHash],
        0
    ) > 0;
    $formCounts = $readRow(
        'forms',
        "SELECT COUNT(*) form_count, SUM(CASE WHEN form_status='PUBLISHED' THEN 1 ELSE 0 END) published_count, SUM(CASE WHEN form_status='DRAFT' THEN 1 ELSE 0 END) draft_count FROM project_company_manufacturing_form WHERE company_key_hash=? AND form_status<>'ARCHIVED'",
        [$companyKeyHash],
        ['form_count' => 0, 'published_count' => 0, 'draft_count' => 0]
    );
    $auditCount = (int) $readOne(
        'audit',
        'SELECT COUNT(*) FROM project_company_manufacturing_audit WHERE company_key_hash=?',
        [$companyKeyHash],
        0
    );
    $auditRows = $readAll(
        'activity',
        'SELECT audit_key,record_type,record_key,business_key,audit_action,admin_key,created_at FROM project_company_manufacturing_audit WHERE company_key_hash=? ORDER BY created_at DESC,x_id DESC LIMIT 10',
        [$companyKeyHash]
    );
    $operationCount = (int) $readOne('operations', "SELECT COUNT(*) FROM project_company_manufacturing_operation WHERE company_key_hash=? AND record_status='ACTIVE'", [$companyKeyHash], 0);
    $workstationCount = (int) $readOne('workstations', "SELECT COUNT(*) FROM project_company_manufacturing_workstation WHERE company_key_hash=? AND record_status='ACTIVE'", [$companyKeyHash], 0);
    $capacityExceptionCount = (int) $readOne('capacity', "SELECT COUNT(*) FROM project_company_manufacturing_downtime WHERE company_key_hash=? AND record_status='ACTIVE' AND starts_at<=NOW() AND ends_at>NOW()", [$companyKeyHash], 0);

    $gateway = yovel_admin_manufacturing_dependency_gateway();
    $dependencies = [];
    foreach ($gateway as $key => $contract) {
        $dependencies[] = [
            'key' => (string) $key,
            'contract' => (string) $contract['owner'] . '.' . (string) $key . '.v1',
            'owner' => (string) $contract['owner'],
            'owner_function' => (string) $contract['owner_function'],
            'signature' => (string) $contract['signature'],
            'status' => !empty($contract['available']) ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY',
        ];
    }
    $dependencyByKey = [];
    foreach ($dependencies as $dependency) {
        $dependencyByKey[(string) $dependency['key']] = $dependency;
    }
    $dependencyAvailable = static fn (string $key): bool => ($dependencyByKey[$key]['status'] ?? '') === 'AVAILABLE';
    $publishedCount = (int) ($formCounts['published_count'] ?? 0);
    $formCount = (int) ($formCounts['form_count'] ?? 0);
    $setup = [
        ['key' => 'manufacturing-settings', 'label' => 'Review manufacturing settings', 'complete' => $settingsConfigured, 'href' => '?view=manufacturing&section=settings'],
        ['key' => 'published-form', 'label' => 'Publish a production form', 'complete' => $publishedCount > 0, 'href' => '?view=manufacturing&section=form-builder'],
        ['key' => 'inventory-catalogue', 'label' => 'Connect item and UOM services', 'complete' => $dependencyAvailable('item_lookup') && $dependencyAvailable('item_uom_resolve'), 'href' => '?view=manufacturing&section=material-requirements'],
        ['key' => 'inventory-locations', 'label' => 'Connect warehouse and quantity services', 'complete' => $dependencyAvailable('warehouses') && $dependencyAvailable('stock_snapshot'), 'href' => '?view=manufacturing&section=material-requirements'],
        ['key' => 'capacity-masters', 'label' => 'Configure operations and workstations', 'complete' => $operationCount > 0 && $workstationCount > 0, 'href' => '?view=manufacturing&section=workstations'],
        ['key' => 'bom-package', 'label' => 'Enable BOM authoring and costing', 'complete' => false, 'href' => '?view=manufacturing&section=boms'],
    ];
    $setupComplete = count(array_filter($setup, static fn (array $step): bool => (bool) $step['complete']));

    $queue = [];
    if (!$settingsConfigured) {
        $queue[] = ['key' => 'settings-required', 'label' => 'Manufacturing settings require review', 'status' => 'OPEN', 'href' => '?view=manufacturing&section=settings'];
    }
    if ($publishedCount === 0) {
        $queue[] = ['key' => 'published-form-required', 'label' => 'A production form must be published', 'status' => 'OPEN', 'href' => '?view=manufacturing&section=form-builder'];
    }
    $queue[] = ['key' => 'bom-package-blocked', 'label' => 'BOM records await the Manufacturing BOM package', 'status' => 'BLOCKED', 'href' => '?view=manufacturing&section=boms'];
    $queue[] = ['key' => 'planning-package-blocked', 'label' => 'Production planning awaits its Manufacturing package', 'status' => 'BLOCKED', 'href' => '?view=manufacturing&section=production-plans'];
    $queue[] = ['key' => 'execution-package-blocked', 'label' => 'Work execution awaits work order and job card packages', 'status' => 'BLOCKED', 'href' => '?view=manufacturing&section=work-orders'];

    $activity = array_map(static function (array $row): array {
        $recordType = trim((string) ($row['record_type'] ?? 'Manufacturing record'));
        $recordKey = trim((string) ($row['record_key'] ?? ''));
        $adminKey = trim((string) ($row['admin_key'] ?? ''));
        return [
            'key' => (string) ($row['audit_key'] ?? ''),
            'action' => (string) ($row['audit_action'] ?? 'UPDATE'),
            'record_label' => ucwords(strtolower(str_replace('_', ' ', $recordType))) . ($recordKey !== '' ? ' ' . substr($recordKey, 0, 8) : ''),
            'business_key' => (string) ($row['business_key'] ?? ''),
            'actor_label' => $adminKey !== '' ? 'Admin ' . substr($adminKey, 0, 8) : 'Company admin',
            'occurred_at' => (string) ($row['created_at'] ?? ''),
        ];
    }, $auditRows);

    $summaryDefinitions = [
        ['active-boms', 'Active BOMs', 'NOT_IMPLEMENTED', '?view=manufacturing&section=boms'],
        ['production-plans', 'Production plans', 'NOT_IMPLEMENTED', '?view=manufacturing&section=production-plans'],
        ['work-orders', 'Work orders', 'NOT_IMPLEMENTED', '?view=manufacturing&section=work-orders'],
        ['job-cards', 'Job cards', 'NOT_IMPLEMENTED', '?view=manufacturing&section=job-cards'],
        ['material-shortages', 'Material shortages', 'UNAVAILABLE_DEPENDENCY', '?view=manufacturing&section=material-requirements'],
        ['capacity-exceptions', 'Capacity exceptions', 'AVAILABLE', '?view=manufacturing&section=workstations'],
    ];
    $summary = array_map(static fn (array $item): array => [
        'key' => $item[0], 'label' => $item[1], 'value' => $item[0] === 'capacity-exceptions' ? (string) $capacityExceptionCount : 'Unavailable', 'availability' => $item[2], 'href' => $item[3],
    ], $summaryDefinitions);

    $alerts = [
        ['key' => 'bom-package-unavailable', 'severity' => 'WARNING', 'label' => 'BOM authoring and revision metrics are not implemented.', 'href' => '?view=manufacturing&section=boms'],
    ];
    if (!$dependencyAvailable('item_valuation')) {
        $alerts[] = ['key' => 'valuation-unavailable', 'severity' => 'WARNING', 'label' => 'Inventory valuation service is unavailable for material costing.', 'href' => '?view=manufacturing&section=boms'];
    }
    if (!$dependencyAvailable('cost_preview')) {
        $alerts[] = ['key' => 'finance-costing-unavailable', 'severity' => 'WARNING', 'label' => 'Finance manufacturing cost preview is unavailable.', 'href' => '?view=manufacturing&section=boms'];
    }
    foreach (array_keys($errors) as $errorKey) {
        $alerts[] = ['key' => 'read-error-' . $errorKey, 'severity' => 'ERROR', 'label' => 'One Manufacturing dashboard section could not be refreshed.', 'href' => '?view=manufacturing&section=dashboard'];
    }

    return [
        'company_key_hash' => $companyKeyHash,
        'summary' => $summary,
        'queue' => array_slice($queue, 0, 6),
        'activity' => array_slice($activity, 0, 10),
        'setup' => $setup,
        'alerts' => array_slice($alerts, 0, 8),
        'shortcuts' => [
            ['key' => 'settings', 'label' => 'Manufacturing settings', 'href' => '?view=manufacturing&section=settings', 'icon' => 'settings', 'available' => true],
            ['key' => 'form-builder', 'label' => 'Form Builder', 'href' => '?view=manufacturing&section=form-builder', 'icon' => 'dynamic_form', 'available' => true],
            ['key' => 'operations', 'label' => 'Operations', 'href' => '?view=manufacturing&section=operations', 'icon' => 'precision_manufacturing', 'available' => true],
            ['key' => 'workstations', 'label' => 'Workstations', 'href' => '?view=manufacturing&section=workstations', 'icon' => 'factory', 'available' => true],
            ['key' => 'boms', 'label' => 'BOM', 'href' => '?view=manufacturing&section=boms', 'icon' => 'account_tree', 'available' => false],
            ['key' => 'work-orders', 'label' => 'Work orders', 'href' => '?view=manufacturing&section=work-orders', 'icon' => 'assignment', 'available' => false],
        ],
        'directories' => [
            ['group' => 'Masters', 'items' => [
                ['key' => 'settings', 'label' => 'Settings', 'href' => '?view=manufacturing&section=settings', 'available' => true],
                ['key' => 'operations', 'label' => 'Operations', 'href' => '?view=manufacturing&section=operations', 'available' => true],
                ['key' => 'workstations', 'label' => 'Workstations', 'href' => '?view=manufacturing&section=workstations', 'available' => true],
            ]],
            ['group' => 'Planning', 'items' => [
                ['key' => 'production-plans', 'label' => 'Production plans', 'href' => '?view=manufacturing&section=production-plans', 'available' => false],
                ['key' => 'material-requirements', 'label' => 'Material requirements', 'href' => '?view=manufacturing&section=material-requirements', 'available' => false],
                ['key' => 'forecasts', 'label' => 'Forecasts', 'href' => '?view=manufacturing&section=forecasts', 'available' => false],
            ]],
            ['group' => 'Execution', 'items' => [
                ['key' => 'work-orders', 'label' => 'Work orders', 'href' => '?view=manufacturing&section=work-orders', 'available' => false],
                ['key' => 'job-cards', 'label' => 'Job cards', 'href' => '?view=manufacturing&section=job-cards', 'available' => false],
                ['key' => 'reports', 'label' => 'Reports', 'href' => '?view=manufacturing&section=reports', 'available' => true],
            ]],
        ],
        'dependencies' => $dependencies,
        'foundation' => [
            'settings_configured' => $settingsConfigured,
            'form_count' => $formCount,
            'published_form_count' => $publishedCount,
            'draft_form_count' => (int) ($formCounts['draft_count'] ?? 0),
            'audit_count' => $auditCount,
            'setup_complete' => $setupComplete,
            'setup_total' => count($setup),
            'dependency_available' => count(array_filter($dependencies, static fn (array $dependency): bool => $dependency['status'] === 'AVAILABLE')),
            'dependency_total' => count($dependencies),
        ],
    ];
}
