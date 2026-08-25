<?php
declare(strict_types=1);

function yovel_admin_buying_dashboard_read_one(
    ADOConnection $db,
    string $key,
    string $sql,
    array $parameters,
    array &$errors
): mixed
{
    try {
        $value = $db->GetOne($sql, $parameters);
        if ($value === false) {
            throw new RuntimeException('Dashboard read failed.');
        }
        return $value;
    } catch (Throwable) {
        $errors[$key] = true;
        return null;
    }
}

function yovel_admin_buying_dashboard_read_all(
    ADOConnection $db,
    string $key,
    string $sql,
    array $parameters,
    array &$errors
): array
{
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
}

function yovel_admin_buying_dashboard_score_exceptions(ADOConnection $db, string $companyKeyHash, int $limit = 6): array
{
    $limit = max(1, min(20, $limit));
    $rows = $db->GetAll(
        "SELECT period.scorecard_period_key, period.supplier_key, period.total_score,
            period.period_start, period.period_end, supplier.supplier_name,
            standing.standing_code, standing.standing_name,
            standing.warn_rfqs, standing.warn_purchase_orders,
            standing.prevent_rfqs, standing.prevent_purchase_orders
        FROM project_company_buying_scorecard_period period
        INNER JOIN project_company_buying_scorecard_standing standing
            ON standing.company_key_hash = period.company_key_hash
           AND standing.scorecard_standing_key = period.standing_key
           AND standing.row_status = 'ACTIVE'
        INNER JOIN project_company_buying_supplier supplier
            ON supplier.company_key_hash = period.company_key_hash
           AND supplier.supplier_key = period.supplier_key
           AND supplier.supplier_status <> 'DELETED'
        WHERE period.company_key_hash = ?
          AND period.period_status IN ('CALCULATED','PUBLISHED')
          AND period.x_id = (
              SELECT latest.x_id
              FROM project_company_buying_scorecard_period latest
              WHERE latest.company_key_hash = period.company_key_hash
                AND latest.supplier_key = period.supplier_key
                AND latest.period_status IN ('CALCULATED','PUBLISHED')
              ORDER BY latest.period_end DESC, latest.x_id DESC
              LIMIT 1
          )
          AND (
              standing.warn_rfqs = 1 OR standing.warn_purchase_orders = 1
              OR standing.prevent_rfqs = 1 OR standing.prevent_purchase_orders = 1
          )
        ORDER BY
            GREATEST(standing.prevent_rfqs, standing.prevent_purchase_orders) DESC,
            period.period_end DESC,
            period.x_id DESC
        LIMIT {$limit}",
        [$companyKeyHash]
    );
    if ($rows === false) {
        throw new RuntimeException('Score exception read failed.');
    }
    return is_array($rows) ? $rows : [];
}

function yovel_admin_buying_dashboard_score_exception_count(ADOConnection $db, string $companyKeyHash): int
{
    $count = $db->GetOne(
        "SELECT COUNT(*)
        FROM project_company_buying_scorecard_period period
        INNER JOIN project_company_buying_scorecard_standing standing
            ON standing.company_key_hash = period.company_key_hash
           AND standing.scorecard_standing_key = period.standing_key
           AND standing.row_status = 'ACTIVE'
        WHERE period.company_key_hash = ?
          AND period.period_status IN ('CALCULATED','PUBLISHED')
          AND period.x_id = (
              SELECT latest.x_id
              FROM project_company_buying_scorecard_period latest
              WHERE latest.company_key_hash = period.company_key_hash
                AND latest.supplier_key = period.supplier_key
                AND latest.period_status IN ('CALCULATED','PUBLISHED')
              ORDER BY latest.period_end DESC, latest.x_id DESC
              LIMIT 1
          )
          AND (
              standing.warn_rfqs = 1 OR standing.warn_purchase_orders = 1
              OR standing.prevent_rfqs = 1 OR standing.prevent_purchase_orders = 1
          )",
        [$companyKeyHash]
    );
    if ($count === false) {
        throw new RuntimeException('Score exception count failed.');
    }
    return (int) $count;
}

function yovel_admin_buying_dashboard_activity(ADOConnection $db, string $companyKeyHash): array
{
    $adminRows = $db->GetAll(
        "SELECT admin_key, admin_name, admin_login
        FROM project_company_admin
        WHERE company_key_hash = ? AND admin_status = 'ACTIVE'",
        [$companyKeyHash]
    );
    if ($adminRows === false) {
        throw new RuntimeException('Dashboard administrator read failed.');
    }
    $adminLabels = [];
    foreach (is_array($adminRows) ? $adminRows : [] as $adminRow) {
        $adminKey = (string) $adminRow['admin_key'];
        $adminLabels[$adminKey] = trim((string) $adminRow['admin_name']) !== ''
            ? (string) $adminRow['admin_name']
            : (string) $adminRow['admin_login'];
    }

    $rows = $db->GetAll(
        "SELECT audit_key, action, module, record_key, new_values, created_at
        FROM builder_audit_log
        WHERE module IN (
            'project_company_buying_setting',
            'project_company_buying_supplier',
            'project_company_buying_scorecard_definition',
            'project_company_buying_scorecard_period',
            'project_company_buying_rfq',
            'project_company_buying_number_series'
        )
          AND new_values LIKE ?
        ORDER BY created_at DESC, x_id DESC
        LIMIT 8",
        ['%' . $companyKeyHash . '%']
    );
    if ($rows === false) {
        throw new RuntimeException('Dashboard activity read failed.');
    }
    $labels = [
        'project_company_buying_setting' => 'Buying Settings',
        'project_company_buying_supplier' => 'Supplier',
        'project_company_buying_scorecard_definition' => 'Supplier Scorecard',
        'project_company_buying_scorecard_period' => 'Scorecard Period',
        'project_company_buying_rfq' => 'Request for Quotation',
        'project_company_buying_number_series' => 'Document Number',
    ];
    $activity = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        try {
            $values = json_decode((string) ($row['new_values'] ?? ''), true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            continue;
        }
        if (!is_array($values) || (string) ($values['company_key_hash'] ?? '') !== $companyKeyHash) {
            continue;
        }
        $actorKey = (string) ($values['admin_key'] ?? '');
        $activity[] = [
            'key' => (string) $row['audit_key'],
            'action' => (string) $row['action'],
            'record_key' => $row['record_key'] === null ? null : (string) $row['record_key'],
            'record_label' => $labels[(string) $row['module']] ?? 'Procurement Record',
            'actor_label' => $adminLabels[$actorKey] ?? 'Company administrator',
            'status' => 'COMPLETED',
            'occurred_at' => (string) $row['created_at'],
        ];
    }
    return $activity;
}

function yovel_admin_buying_procurement_dashboard_data(array $company, array $admin): array
{
    [, $companyKeyHash] = yovel_admin_buying_scope($company, $admin);
    $db = bx_db();
    $errors = [];
    $inventoryAvailable = function_exists('yovel_admin_inventory_buying_snapshot');
    $financeAvailable = function_exists('yovel_admin_finance_buying_defaults')
        && function_exists('yovel_admin_finance_buying_snapshot');

    $activeSuppliersValue = yovel_admin_buying_dashboard_read_one(
        $db,
        'active-suppliers',
        "SELECT COUNT(*) FROM project_company_buying_supplier
        WHERE company_key_hash = ? AND supplier_status = 'ACTIVE'",
        [$companyKeyHash],
        $errors
    );
    $activeSuppliers = $activeSuppliersValue === null ? null : (int) $activeSuppliersValue;
    $heldSuppliersValue = yovel_admin_buying_dashboard_read_one(
        $db,
        'supplier-holds',
        "SELECT COUNT(*) FROM project_company_buying_supplier
        WHERE company_key_hash = ? AND supplier_status = 'ACTIVE' AND on_hold = 1",
        [$companyKeyHash],
        $errors
    );
    $heldSuppliers = $heldSuppliersValue === null ? null : (int) $heldSuppliersValue;
    $activeScorecardsValue = yovel_admin_buying_dashboard_read_one(
        $db,
        'active-scorecards',
        "SELECT COUNT(*) FROM project_company_buying_scorecard_definition
        WHERE company_key_hash = ? AND scorecard_status = 'ACTIVE'",
        [$companyKeyHash],
        $errors
    );
    $activeScorecards = $activeScorecardsValue === null ? null : (int) $activeScorecardsValue;
    $rfqCountValue = yovel_admin_buying_dashboard_read_one(
        $db,
        'requests-for-quotation',
        "SELECT COUNT(*) FROM project_company_buying_rfq
        WHERE company_key_hash = ? AND document_status <> 'ARCHIVED'",
        [$companyKeyHash],
        $errors
    );
    $rfqCount = $rfqCountValue === null ? null : (int) $rfqCountValue;
    $settingsSavedValue = yovel_admin_buying_dashboard_read_one(
        $db,
        'buying-settings',
        "SELECT COUNT(*) FROM project_company_buying_setting
        WHERE company_key_hash = ? AND setting_status = 'ACTIVE'",
        [$companyKeyHash],
        $errors
    );
    $settingsSaved = $settingsSavedValue !== null && (int) $settingsSavedValue > 0;
    $supplierSetupGapsValue = yovel_admin_buying_dashboard_read_one(
        $db,
        'supplier-setup-gaps',
        "SELECT COUNT(*) FROM project_company_buying_supplier
        WHERE company_key_hash = ? AND supplier_status = 'ACTIVE' AND on_hold = 0
          AND (supplier_group_key IS NULL OR default_currency IS NULL OR payment_terms_key IS NULL)",
        [$companyKeyHash],
        $errors
    );
    $supplierSetupGaps = $supplierSetupGapsValue === null ? null : (int) $supplierSetupGapsValue;
    try {
        $scoreExceptionCount = yovel_admin_buying_dashboard_score_exception_count($db, $companyKeyHash);
        $scoreExceptions = yovel_admin_buying_dashboard_score_exceptions($db, $companyKeyHash);
    } catch (Throwable) {
        $errors['score-exceptions'] = true;
        $scoreExceptionCount = null;
        $scoreExceptions = [];
    }

    $queue = [];
    $holdRows = yovel_admin_buying_dashboard_read_all(
        $db,
        'supplier-hold-queue',
        "SELECT supplier_key, supplier_name, hold_type, release_date
        FROM project_company_buying_supplier
        WHERE company_key_hash = ? AND supplier_status = 'ACTIVE' AND on_hold = 1
        ORDER BY release_date IS NULL, release_date, updated_at DESC
        LIMIT 4",
        [$companyKeyHash],
        $errors
    );
    foreach (is_array($holdRows) ? $holdRows : [] as $row) {
        $queue[] = [
            'key' => 'supplier-hold-' . (string) $row['supplier_key'],
            'label' => (string) $row['supplier_name'] . ' is on ' . strtolower((string) ($row['hold_type'] ?: 'all')) . ' hold',
            'status' => 'HOLD',
            'href' => './?view=buying-procurement&section=suppliers&supplier=' . rawurlencode((string) $row['supplier_key']),
        ];
    }
    foreach ($scoreExceptions as $row) {
        $prevented = (int) $row['prevent_rfqs'] === 1 || (int) $row['prevent_purchase_orders'] === 1;
        $queue[] = [
            'key' => 'score-exception-' . (string) $row['scorecard_period_key'],
            'label' => (string) $row['supplier_name'] . ': ' . (string) $row['standing_name'] . ' at ' . (string) $row['total_score'],
            'status' => $prevented ? 'BLOCKED' : 'WARNING',
            'href' => './?view=buying-procurement&section=supplier-scorecards&supplier=' . rawurlencode((string) $row['supplier_key']),
        ];
    }
    $gapRows = yovel_admin_buying_dashboard_read_all(
        $db,
        'supplier-setup-queue',
        "SELECT supplier_key, supplier_name
        FROM project_company_buying_supplier
        WHERE company_key_hash = ? AND supplier_status = 'ACTIVE' AND on_hold = 0
          AND (supplier_group_key IS NULL OR default_currency IS NULL OR payment_terms_key IS NULL)
        ORDER BY updated_at DESC, x_id DESC
        LIMIT 4",
        [$companyKeyHash],
        $errors
    );
    foreach (is_array($gapRows) ? $gapRows : [] as $row) {
        $queue[] = [
            'key' => 'supplier-setup-' . (string) $row['supplier_key'],
            'label' => (string) $row['supplier_name'] . ' needs procurement defaults',
            'status' => 'SETUP',
            'href' => './?view=buying-procurement&section=suppliers&supplier=' . rawurlencode((string) $row['supplier_key']),
        ];
    }
    $draftRfqRows = yovel_admin_buying_dashboard_read_all(
        $db,
        'draft-rfq-queue',
        "SELECT rfq_key, rfq_number, subject, schedule_date
        FROM project_company_buying_rfq
        WHERE company_key_hash = ? AND document_status = 'DRAFT'
        ORDER BY schedule_date, updated_at DESC, x_id DESC
        LIMIT 4",
        [$companyKeyHash],
        $errors
    );
    foreach ($draftRfqRows as $row) {
        $queue[] = [
            'key' => 'draft-rfq-' . (string) $row['rfq_key'],
            'label' => (string) $row['rfq_number'] . ': ' . (string) $row['subject'],
            'status' => 'DRAFT',
            'href' => './?view=buying-procurement&section=request-for-quotation&rfq=' . rawurlencode((string) $row['rfq_key']),
        ];
    }
    $queue = array_slice($queue, 0, 10);

    $setup = [
        ['key' => 'suppliers', 'label' => 'Add an active supplier', 'complete' => $activeSuppliers !== null && $activeSuppliers > 0, 'href' => './?view=buying-procurement&section=suppliers'],
        ['key' => 'buying-settings', 'label' => 'Save Buying Settings', 'complete' => $settingsSaved, 'href' => './?view=buying-procurement&section=buying-settings'],
        ['key' => 'supplier-scorecards', 'label' => 'Configure a supplier scorecard', 'complete' => $activeScorecards !== null && $activeScorecards > 0, 'href' => './?view=buying-procurement&section=supplier-scorecards'],
        ['key' => 'requests-for-quotation', 'label' => 'Create a Request for Quotation', 'complete' => $rfqCount !== null && $rfqCount > 0, 'href' => './?view=buying-procurement&section=request-for-quotation'],
        ['key' => 'inventory-contract', 'label' => 'Connect the Inventory buying snapshot', 'complete' => $inventoryAvailable, 'href' => null],
        ['key' => 'form-builder', 'label' => 'Review procurement forms', 'complete' => function_exists('yovel_admin_shared_form_adapter'), 'href' => './?view=buying-procurement&section=form-builder'],
    ];

    $alerts = [];
    if (!$settingsSaved) {
        $alerts[] = ['key' => 'settings-missing', 'severity' => 'INFO', 'label' => 'Buying Settings have not been saved for this company.', 'href' => './?view=buying-procurement&section=buying-settings'];
    }
    if ($heldSuppliers !== null && $heldSuppliers > 0) {
        $alerts[] = ['key' => 'supplier-holds', 'severity' => 'WARNING', 'label' => $heldSuppliers . ' active supplier' . ($heldSuppliers === 1 ? ' is' : 's are') . ' on hold.', 'href' => './?view=buying-procurement&section=suppliers'];
    }
    if ($scoreExceptionCount !== null && $scoreExceptionCount > 0) {
        $alerts[] = ['key' => 'score-exceptions', 'severity' => 'CRITICAL', 'label' => $scoreExceptionCount . ' supplier score exception' . ($scoreExceptionCount === 1 ? ' requires' : 's require') . ' review.', 'href' => './?view=buying-procurement&section=supplier-scorecards'];
    }
    if ($supplierSetupGaps !== null && $supplierSetupGaps > 0) {
        $alerts[] = ['key' => 'supplier-setup-gaps', 'severity' => 'WARNING', 'label' => $supplierSetupGaps . ' supplier record' . ($supplierSetupGaps === 1 ? ' needs' : 's need') . ' procurement defaults.', 'href' => './?view=buying-procurement&section=suppliers'];
    }
    if (!$inventoryAvailable) {
        $alerts[] = ['key' => 'inventory-unavailable', 'severity' => 'DEPENDENCY', 'label' => 'Inventory buying snapshot is unavailable; material requests and receipts remain disabled.', 'href' => null];
    }
    if (!$financeAvailable) {
        $alerts[] = ['key' => 'finance-unavailable', 'severity' => 'DEPENDENCY', 'label' => 'Finance buying defaults and snapshot are unavailable.', 'href' => null];
    }
    foreach (array_keys($errors) as $errorKey) {
        $alerts[] = ['key' => 'read-error-' . $errorKey, 'severity' => 'ERROR', 'label' => 'One procurement dashboard section could not be refreshed.', 'href' => './?view=buying-procurement&section=dashboard'];
    }

    try {
        $activity = yovel_admin_buying_dashboard_activity($db, $companyKeyHash);
    } catch (Throwable) {
        $errors['activity'] = true;
        $activity = [];
        $alerts[] = ['key' => 'read-error-activity', 'severity' => 'ERROR', 'label' => 'Recent procurement activity could not be refreshed.', 'href' => './?view=buying-procurement&section=dashboard'];
    }

    return [
        'summary' => [
            ['key' => 'active-suppliers', 'label' => 'Active suppliers', 'value' => $activeSuppliers, 'availability' => isset($errors['active-suppliers']) ? 'ERROR' : 'AVAILABLE', 'href' => './?view=buying-procurement&section=suppliers'],
            ['key' => 'material-requests', 'label' => 'Pending material requests', 'value' => null, 'availability' => 'UNAVAILABLE_DEPENDENCY', 'href' => null],
            ['key' => 'requests-for-quotation', 'label' => 'Requests for quotation', 'value' => $rfqCount, 'availability' => isset($errors['requests-for-quotation']) ? 'ERROR' : 'AVAILABLE', 'href' => './?view=buying-procurement&section=request-for-quotation'],
            ['key' => 'purchase-orders', 'label' => 'Purchase orders', 'value' => null, 'availability' => 'NOT_IMPLEMENTED', 'href' => null],
            ['key' => 'purchase-receipts', 'label' => 'Purchase receipts', 'value' => null, 'availability' => 'UNAVAILABLE_DEPENDENCY', 'href' => null],
            ['key' => 'score-exceptions', 'label' => 'Score exceptions', 'value' => $scoreExceptionCount, 'availability' => isset($errors['score-exceptions']) ? 'ERROR' : 'AVAILABLE', 'href' => './?view=buying-procurement&section=supplier-scorecards'],
        ],
        'queue' => $queue,
        'activity' => $activity,
        'setup' => $setup,
        'alerts' => array_slice($alerts, 0, 8),
        'shortcuts' => [
            ['key' => 'suppliers', 'label' => 'Suppliers', 'href' => './?view=buying-procurement&section=suppliers', 'available' => true],
            ['key' => 'supplier-scorecards', 'label' => 'Supplier Scorecards', 'href' => './?view=buying-procurement&section=supplier-scorecards', 'available' => true],
            ['key' => 'buying-settings', 'label' => 'Buying Settings', 'href' => './?view=buying-procurement&section=buying-settings', 'available' => true],
            ['key' => 'form-builder', 'label' => 'Form Builder', 'href' => './?view=buying-procurement&section=form-builder', 'available' => true],
            ['key' => 'requests-for-quotation', 'label' => 'Requests for Quotation', 'href' => './?view=buying-procurement&section=request-for-quotation', 'available' => true],
            ['key' => 'purchase-orders', 'label' => 'Purchase Orders', 'href' => null, 'available' => false],
        ],
        'directories' => [
            [
                'group' => 'Reports',
                'items' => [
                    ['key' => 'supplier-scorecards', 'label' => 'Supplier Scorecards', 'href' => './?view=buying-procurement&section=supplier-scorecards', 'availability' => 'AVAILABLE'],
                    ['key' => 'purchase-analytics', 'label' => 'Purchase Analytics', 'href' => null, 'availability' => 'UNAVAILABLE_DEPENDENCY'],
                    ['key' => 'procurement-reports', 'label' => 'Procurement Reports', 'href' => null, 'availability' => 'NOT_IMPLEMENTED'],
                ],
            ],
            [
                'group' => 'Masters',
                'items' => [
                    ['key' => 'suppliers', 'label' => 'Suppliers', 'href' => './?view=buying-procurement&section=suppliers', 'availability' => 'AVAILABLE'],
                    ['key' => 'buying-settings', 'label' => 'Buying Settings', 'href' => './?view=buying-procurement&section=buying-settings', 'availability' => 'AVAILABLE'],
                    ['key' => 'form-builder', 'label' => 'Form Builder', 'href' => './?view=buying-procurement&section=form-builder', 'availability' => 'AVAILABLE'],
                ],
            ],
        ],
        'dependencies' => [
            ['contract' => 'buying.suppliers.v1', 'status' => 'AVAILABLE'],
            ['contract' => 'buying.scorecards.v1', 'status' => 'AVAILABLE'],
            ['contract' => 'inventory.buying-snapshot.v1', 'status' => $inventoryAvailable ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY'],
            ['contract' => 'finance.buying-snapshot.v1', 'status' => $financeAvailable ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY'],
            ['contract' => 'buying.rfq.v1', 'status' => 'AVAILABLE'],
            ['contract' => 'buying.purchase-orders.v1', 'status' => 'NOT_IMPLEMENTED'],
            ['contract' => 'inventory.purchase-receipts.v1', 'status' => 'UNAVAILABLE_DEPENDENCY'],
        ],
    ];
}
