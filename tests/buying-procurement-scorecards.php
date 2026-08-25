<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/shared/registry.php';
require_once $root . '/company/admin/modules/shared/forms.php';
require_once __DIR__ . '/buying-procurement-test-helper.php';
require_once $root . '/company/admin/modules/buying-procurement/schema.php';
require_once $root . '/company/admin/modules/buying-procurement/core.php';
require_once $root . '/company/admin/modules/buying-procurement/forms.php';

foreach (['settings.php', 'suppliers.php', 'scorecards.php', 'functions.php'] as $moduleFile) {
    $path = $root . '/company/admin/modules/buying-procurement/' . $moduleFile;
    if (is_file($path)) {
        require_once $path;
    }
}

foreach ([
    'yovel_admin_buying_scorecard_evaluate_expression',
    'yovel_admin_buying_scorecard_definition',
    'yovel_admin_buying_save_scorecard_definition',
    'yovel_admin_buying_calculate_scorecard_period',
    'yovel_admin_buying_supplier_restrictions',
] as $service) {
    buying_test_assert(function_exists($service), 'Missing Task 4 Buying service: ' . $service);
}

buying_test_assert(
    yovel_admin_buying_scorecard_evaluate_expression('(DELIVERED / ORDERED) * 100 + -5', ['DELIVERED' => 9, 'ORDERED' => 10]) === '85.0000',
    'The scorecard expression grammar is not deterministic.'
);
foreach ([
    'phpinfo()',
    'system("id")',
    '1; DROP TABLE project_company',
    '$GLOBALS',
    'VALUE->property',
    'VALUE.property',
    'VALUE::property',
    'file_get_contents("/etc/passwd")',
    'SELECT 1',
    '`VALUE`',
    'VALUE[0]',
    '1e309',
] as $expression) {
    buying_test_expect_error(
        static fn () => yovel_admin_buying_scorecard_evaluate_expression($expression, ['VALUE' => 1]),
        'expression'
    );
}
buying_test_expect_error(
    static fn () => yovel_admin_buying_scorecard_evaluate_expression('KNOWN + UNKNOWN', ['KNOWN' => 1]),
    'registered'
);
buying_test_expect_error(
    static fn () => yovel_admin_buying_scorecard_evaluate_expression('10 / ZERO', ['ZERO' => 0]),
    'zero'
);

yovel_admin_buying_schema();
$db = bx_db();
$ownerFixture = buying_test_temporary_company($db, 'Buying Scorecard Owner');
$relatedFixture = buying_test_temporary_company($db, 'Buying Scorecard Related');
$owner = $ownerFixture['company'];
$ownerAdmin = $ownerFixture['admin'];
$related = $relatedFixture['company'];
$relatedAdmin = $relatedFixture['admin'];
$scorecardKey = '';
$rollbackScorecardKey = '';

$definitionInput = static function (string $supplierKey): array {
    return [
        'supplier_key' => $supplierKey,
        'period_type' => 'MONTH',
        'weighting_expression' => 'DELIVERED + ORDERED + QUALITY',
        'scorecard_status' => 'ACTIVE',
        'variables' => [
            ['variable_code' => 'DELIVERED', 'variable_label' => 'Delivered orders', 'metric_path' => 'delivered_order_count'],
            ['variable_code' => 'ORDERED', 'variable_label' => 'Ordered deliveries', 'metric_path' => 'ordered_delivery_count'],
            ['variable_code' => 'QUALITY', 'variable_label' => 'Quality score', 'metric_path' => 'quality_score'],
        ],
        'criteria' => [
            ['criteria_code' => 'DELIVERY', 'criteria_name' => 'Delivery', 'max_score' => '100', 'weight_percentage' => '60', 'formula_expression' => 'DELIVERED / ORDERED * 100'],
            ['criteria_code' => 'QUALITY', 'criteria_name' => 'Quality', 'max_score' => '100', 'weight_percentage' => '40', 'formula_expression' => 'QUALITY'],
        ],
        'standings' => [
            ['standing_code' => 'BLOCKED', 'standing_name' => 'Blocked', 'color_token' => 'destructive', 'minimum_score' => '0', 'maximum_score' => '60', 'prevent_rfqs' => '1', 'prevent_purchase_orders' => '1', 'notify_supplier' => '1'],
            ['standing_code' => 'WATCH', 'standing_name' => 'Watch', 'color_token' => 'warning', 'minimum_score' => '60', 'maximum_score' => '80', 'warn_rfqs' => '1', 'warn_purchase_orders' => '1'],
            ['standing_code' => 'GOOD', 'standing_name' => 'Good', 'color_token' => 'success', 'minimum_score' => '80', 'maximum_score' => '100'],
        ],
    ];
};

try {
    yovel_admin_buying_save_settings($db, $owner, $ownerAdmin, [
        'supplier_naming_mode' => 'SUPPLIER_NAME',
        'maintain_same_rate_action' => 'WARN',
    ]);
    yovel_admin_buying_save_settings($db, $related, $relatedAdmin, [
        'supplier_naming_mode' => 'SUPPLIER_NAME',
        'maintain_same_rate_action' => 'WARN',
    ]);
    $supplier = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'Scorecard Supplier',
        'supplier_type' => 'COMPANY',
    ]);
    $supplierKey = (string) $supplier['supplier_key'];
    $rollbackSupplier = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'Rollback Scorecard Supplier',
        'supplier_type' => 'COMPANY',
    ]);

    $badWeights = $definitionInput($supplierKey);
    $badWeights['criteria'][1]['weight_percentage'] = '39.9999';
    buying_test_expect_error(
        static fn () => yovel_admin_buying_save_scorecard_definition($db, $owner, $ownerAdmin, $badWeights),
        '100'
    );
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_scorecard_definition WHERE company_key_hash = ?', [$owner['company_key_hash']]) === 0, 'Invalid scorecard weights wrote a definition.');

    $badRanges = $definitionInput($supplierKey);
    $badRanges['standings'][1]['minimum_score'] = '59';
    buying_test_expect_error(
        static fn () => yovel_admin_buying_save_scorecard_definition($db, $owner, $ownerAdmin, $badRanges),
        'standing'
    );
    $badFormula = $definitionInput($supplierKey);
    $badFormula['criteria'][0]['formula_expression'] = 'system("id")';
    buying_test_expect_error(
        static fn () => yovel_admin_buying_save_scorecard_definition($db, $owner, $ownerAdmin, $badFormula),
        'expression'
    );

    $saved = yovel_admin_buying_save_scorecard_definition($db, $owner, $ownerAdmin, $definitionInput($supplierKey));
    $scorecardKey = (string) $saved['scorecard_key'];
    buying_test_assert(yovel_admin_is_uuid($scorecardKey), 'Scorecard definition did not return a stable key.');
    buying_test_assert(count($saved['variables']) === 3 && count($saved['criteria']) === 2 && count($saved['standings']) === 3, 'Scorecard children were not read back exactly.');
    buying_test_assert((string) $saved['criteria'][0]['weight_percentage'] === '60.0000', 'Scorecard decimal read-back was not exact.');
    buying_test_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_buying_scorecard_definition' AND record_key = ? AND action = 'CREATE'", [$scorecardKey]) === 1, 'Scorecard definition audit was not committed.');
    $stableChildKeys = [
        'variable' => array_column($saved['variables'], 'scorecard_variable_key', 'variable_code'),
        'criteria' => array_column($saved['criteria'], 'scorecard_criteria_key', 'criteria_code'),
        'standing' => array_column($saved['standings'], 'scorecard_standing_key', 'standing_code'),
    ];
    $updateInput = $definitionInput($supplierKey);
    $updateInput['scorecard_key'] = $scorecardKey;
    $updated = yovel_admin_buying_save_scorecard_definition($db, $owner, $ownerAdmin, $updateInput);
    buying_test_assert(array_column($updated['variables'], 'scorecard_variable_key', 'variable_code') === $stableChildKeys['variable'], 'Scorecard variable stable keys changed on update.');
    buying_test_assert(array_column($updated['criteria'], 'scorecard_criteria_key', 'criteria_code') === $stableChildKeys['criteria'], 'Scorecard criteria stable keys changed on update.');
    buying_test_assert(array_column($updated['standings'], 'scorecard_standing_key', 'standing_code') === $stableChildKeys['standing'], 'Scorecard standing stable keys changed on update.');
    buying_test_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_buying_scorecard_definition' AND record_key = ? AND action = 'UPDATE'", [$scorecardKey]) === 1, 'Scorecard definition update audit was not committed.');

    buying_test_assert(yovel_admin_buying_scorecard_definition($related, $scorecardKey) === null, 'Cross-company scorecard read exposed a definition.');
    buying_test_expect_error(
        static fn () => yovel_admin_buying_save_scorecard_definition($db, $owner, ['admin_key' => bx_uuid()], $definitionInput($supplierKey)),
        'authorized'
    );

    $GLOBALS['yovel_admin_buying_scorecard_child_write_hook'] = static function (string $childType, int $index): void {
        if ($childType === 'criteria' && $index === 0) {
            throw new RuntimeException('Injected scorecard child failure.');
        }
    };
    buying_test_expect_error(
        static fn () => yovel_admin_buying_save_scorecard_definition($db, $owner, $ownerAdmin, $definitionInput((string) $rollbackSupplier['supplier_key'])),
        'injected'
    );
    unset($GLOBALS['yovel_admin_buying_scorecard_child_write_hook']);
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_scorecard_definition WHERE company_key_hash = ? AND supplier_key = ?', [$owner['company_key_hash'], (string) $rollbackSupplier['supplier_key']]) === 0, 'Injected definition failure committed the scorecard parent.');

    $GLOBALS['yovel_admin_buying_scorecard_metric_provider'] = static fn (): array => [
        'delivered_order_count' => 8,
        'ordered_delivery_count' => 10,
        'quality_score' => 90,
    ];
    $period = yovel_admin_buying_calculate_scorecard_period($db, $owner, $ownerAdmin, $scorecardKey, '2026-06-01', '2026-06-30');
    buying_test_assert((string) $period['total_score'] === '84.0000', 'Deterministic scorecard total is incorrect.');
    buying_test_assert((string) $period['standing']['standing_code'] === 'GOOD', 'Scorecard total mapped to the wrong standing.');
    buying_test_assert(count($period['scores']) === 2, 'Scorecard period did not persist every criteria score.');
    buying_test_assert(($period['notification']['available'] ?? true) === false && ($period['notification']['blocking'] ?? true) === false, 'Unavailable Operations handoff did not expose a non-blocking dependency state.');
    buying_test_expect_error(
        static fn () => yovel_admin_buying_calculate_scorecard_period($db, $owner, $ownerAdmin, $scorecardKey, '2026-06-01', '2026-06-30'),
        'period'
    );

    $GLOBALS['yovel_admin_buying_scorecard_metric_provider'] = static fn (): array => [
        'delivered_order_count' => 8,
        'ordered_delivery_count' => 0,
        'quality_score' => 90,
    ];
    buying_test_expect_error(
        static fn () => yovel_admin_buying_calculate_scorecard_period($db, $owner, $ownerAdmin, $scorecardKey, '2026-07-01', '2026-07-31'),
        'zero'
    );
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_scorecard_period WHERE company_key_hash = ? AND period_start = ?', [$owner['company_key_hash'], '2026-07-01']) === 0, 'Zero-division scoring committed a period.');

    $GLOBALS['yovel_admin_buying_scorecard_metric_provider'] = static fn (): array => [
        'delivered_order_count' => 8,
        'ordered_delivery_count' => 10,
        'quality_score' => 90,
    ];
    $GLOBALS['yovel_admin_buying_scorecard_child_write_hook'] = static function (string $childType, int $index): void {
        if ($childType === 'period_score' && $index === 0) {
            throw new RuntimeException('Injected scorecard period failure.');
        }
    };
    buying_test_expect_error(
        static fn () => yovel_admin_buying_calculate_scorecard_period($db, $owner, $ownerAdmin, $scorecardKey, '2026-08-01', '2026-08-31'),
        'injected'
    );
    unset($GLOBALS['yovel_admin_buying_scorecard_child_write_hook']);
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_scorecard_period WHERE company_key_hash = ? AND period_start = ?', [$owner['company_key_hash'], '2026-08-01']) === 0, 'Injected period failure committed its parent.');

    if (!function_exists('yovel_admin_operations_create_notification_handoff')) {
        function yovel_admin_operations_create_notification_handoff(array $company, array $payload): array
        {
            $GLOBALS['buying_scorecard_notification_payload'] = $payload;
            return ['notification_key' => bx_uuid(), 'status' => 'ACCEPTED'];
        }
    }
    $GLOBALS['yovel_admin_buying_scorecard_metric_provider'] = static fn (): array => [
        'delivered_order_count' => 2,
        'ordered_delivery_count' => 10,
        'quality_score' => 50,
    ];
    $blockedPeriod = yovel_admin_buying_calculate_scorecard_period($db, $owner, $ownerAdmin, $scorecardKey, '2026-09-01', '2026-09-30');
    buying_test_assert((string) $blockedPeriod['total_score'] === '32.0000' && (string) $blockedPeriod['standing']['standing_code'] === 'BLOCKED', 'Low score did not map to the blocking standing.');
    buying_test_assert(($blockedPeriod['notification']['delivered'] ?? false) === true, 'Allow-listed Operations notification was not delivered after local persistence.');
    buying_test_assert((string) ($GLOBALS['buying_scorecard_notification_payload']['source_record_key'] ?? '') === (string) $blockedPeriod['scorecard_period_key'], 'Operations notification received the wrong period key.');

    $restrictions = yovel_admin_buying_supplier_restrictions($owner, $supplierKey);
    buying_test_assert($restrictions['prevent_rfqs'] === true && $restrictions['prevent_purchase_orders'] === true, 'Latest scorecard restrictions do not prevent RFQs and purchase orders.');
    buying_test_assert($restrictions['warn_rfqs'] === true && $restrictions['warn_purchase_orders'] === true, 'Preventing restrictions do not also warn callers.');
    buying_test_expect_error(
        static fn () => yovel_admin_buying_assert_supplier_purchase_allowed($owner, $supplierKey, 'PURCHASE_ORDER'),
        'prevented'
    );
    buying_test_expect_error(
        static fn () => yovel_admin_buying_calculate_scorecard_period($db, $related, $relatedAdmin, $scorecardKey, '2026-10-01', '2026-10-31'),
        'not found'
    );

    $handler = yovel_admin_buying_procurement_handle_post($owner, $ownerAdmin, 'calculate_buying_scorecard_period', [
        'module_view' => 'buying-procurement',
        'scorecard_key' => $scorecardKey,
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-31',
    ]);
    buying_test_assert($handler['section'] === 'supplier-scorecards' && isset($handler['query']['scorecard']), 'Scorecard POST handler broke the shared result contract.');

    $data = yovel_admin_buying_procurement_data($owner, $ownerAdmin);
    buying_test_assert(count($data['scorecards'] ?? []) === 1, 'Buying workspace data does not expose scorecard definitions.');
    buying_test_assert(array_key_exists('operations_notification', $data['dependencies'] ?? []), 'Operations notification dependency state is absent.');

    $activeModuleSection = 'supplier-scorecards';
    $activeModuleMeta = yovel_admin_buying_procurement_sections()[$activeModuleSection];
    $activeModuleData = $data;
    $companyName = (string) $owner['company_name'];
    ob_start();
    require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
    $markup = (string) ob_get_clean();
    foreach ([
        'data-buying-scorecards',
        'data-record-modal-open="buying-scorecard-modal"',
        'data-record-modal-open="buying-scorecard-period-modal"',
        'name="module_view" value="buying-procurement"',
        'name="action" value="save_buying_scorecard"',
        'name="action" value="calculate_buying_scorecard_period"',
        'data-confirm-submit',
        'z-index: 100',
    ] as $marker) {
        buying_test_assert(str_contains($markup, $marker), 'Scorecard workspace is missing marker: ' . $marker);
    }

    $activeModuleFormState = [
        'section' => 'supplier-scorecards',
        'action' => 'save_buying_scorecard',
        'input' => ['supplier_key' => $supplierKey, 'criteria_text' => 'DELIVERY | <script>alert(1)</script>'],
        'error' => 'Unsafe <script>alert(2)</script>',
    ];
    ob_start();
    require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
    $rehydrated = (string) ob_get_clean();
    buying_test_assert(str_contains($rehydrated, 'data-record-modal-open-on-load'), 'Scorecard validation failure did not reopen the owned modal.');
    buying_test_assert(str_contains($rehydrated, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($rehydrated, '<script>alert(1)</script>'), 'Scorecard server rehydration is not escaped.');

    $source = (string) file_get_contents($root . '/company/admin/modules/buying-procurement/scorecards.php');
    buying_test_assert(!preg_match('/\beval\s*\(/i', $source), 'Scorecard runtime source uses eval.');
    buying_test_assert(!str_contains($source, 'project_company_operations_'), 'Scorecards directly access Operations tables.');
    buying_test_assert(!str_contains($source, 'project_company_finance_'), 'Scorecards directly access Finance tables.');
    buying_test_assert(!str_contains($source, 'project_company_inventory_'), 'Scorecards directly access Inventory tables.');
    buying_test_assert(substr_count($source, 'yovel_admin_buying_in_transaction(') >= 2, 'Scorecard public mutations do not own explicit transactions.');
    buying_test_assert(str_contains($source, 'FOR UPDATE'), 'Scorecard mutations do not lock owned rows.');
} finally {
    unset(
        $GLOBALS['yovel_admin_buying_scorecard_child_write_hook'],
        $GLOBALS['yovel_admin_buying_scorecard_metric_provider'],
        $GLOBALS['buying_scorecard_notification_payload']
    );
    buying_test_cleanup_temporary_company($db, $ownerFixture);
    buying_test_cleanup_temporary_company($db, $relatedFixture);
}

echo "Buying / Procurement scorecard checks passed.\n";
