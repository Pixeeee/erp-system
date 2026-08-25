<?php
declare(strict_types=1);

require __DIR__ . '/manufacturing-test-helper.php';

$requiredFunctions = [
    'yovel_admin_manufacturing_save_bom',
    'yovel_admin_manufacturing_submit_bom',
    'yovel_admin_manufacturing_cancel_bom',
    'yovel_admin_manufacturing_amend_bom',
    'yovel_admin_manufacturing_explode_bom',
    'yovel_admin_manufacturing_update_bom_cost',
    'yovel_admin_manufacturing_boms',
    'yovel_admin_manufacturing_bom',
];
foreach ($requiredFunctions as $requiredFunction) {
    manufacturing_test_assert(function_exists($requiredFunction), 'Missing WP-02 interface: ' . $requiredFunction);
}

$scopeA = manufacturing_test_create_scope('bom-a');
$scopeB = manufacturing_test_create_scope('bom-b');
try {
    $_SESSION['builderx_csrf'] = $scopeA['csrf'];
    yovel_admin_manufacturing_schema();

    $itemKeys = [
        'finished' => bx_uuid(), 'subassembly' => bx_uuid(), 'raw_one' => bx_uuid(), 'raw_two' => bx_uuid(),
        'raw_alternative' => bx_uuid(), 'scrap' => bx_uuid(), 'cycle_a' => bx_uuid(), 'cycle_b' => bx_uuid(),
    ];
    $rates = [
        $itemKeys['raw_one'] => '5.000000000', $itemKeys['raw_two'] => '10.000000000',
        $itemKeys['raw_alternative'] => '50.000000000', $itemKeys['scrap'] => '2.000000000',
        $itemKeys['finished'] => '0.000000000', $itemKeys['subassembly'] => '0.000000000',
        $itemKeys['cycle_a'] => '1.000000000', $itemKeys['cycle_b'] => '1.000000000',
    ];
    $itemLookup = static function (array $company, string $itemKey) use ($rates): ?array {
        if (!isset($rates[$itemKey])) {
            return null;
        }
        return ['item_key' => $itemKey, 'item_code' => 'ITEM-' . substr($itemKey, 0, 8), 'item_name' => 'BOM fixture item', 'item_status' => 'ACTIVE', 'stock_uom_code' => 'EA'];
    };
    $uomResolve = static function (array $company, string $itemKey, string $uomKey, string $qty) use ($rates): array {
        if (!isset($rates[$itemKey]) || strtoupper($uomKey) !== 'EA' || bccomp($qty, '0', 9) !== 1) {
            throw new InvalidArgumentException('Fixture UOM is invalid.');
        }
        return ['quantity' => number_format((float) $qty, 9, '.', ''), 'conversion_factor' => '1.000000000', 'stock_quantity' => number_format((float) $qty, 9, '.', ''), 'stock_uom_code' => 'EA'];
    };
    $valuation = static function (array $company, string $itemKey, ?string $warehouseKey = null, ?string $asOfDate = null) use ($rates): array {
        if (!isset($rates[$itemKey])) {
            throw new InvalidArgumentException('Fixture valuation item is invalid.');
        }
        return ['item_key' => $itemKey, 'warehouse_key' => $warehouseKey, 'as_of_date' => ($asOfDate ?? '') . ' 23:59:59.999999', 'actual_qty' => '100.000000000', 'stock_value' => '100.000000000', 'valuation_rate' => $rates[$itemKey], 'currency_code' => 'PHP', 'valuation_method' => 'MOVING_AVERAGE', 'source_timestamp' => '2026-06-01 12:00:00.000000'];
    };
    $gateway = yovel_admin_manufacturing_dependency_gateway([
        'item_lookup' => $itemLookup,
        'item_uom_resolve' => $uomResolve,
        'item_valuation' => $valuation,
    ]);
    $contextA = ['company' => $scopeA['company'], 'admin' => $scopeA['admin'], 'gateway' => $gateway];
    $contextB = ['company' => $scopeB['company'], 'admin' => $scopeB['admin'], 'gateway' => $gateway];

    $operation = yovel_admin_save_manufacturing_operation($scopeA['company'], $scopeA['admin'], [
        'operation_code' => 'BOM-CUT', 'operation_name' => 'BOM cutting', 'hourly_rate' => '120',
        'cost_account_key' => '', 'record_status' => 'ACTIVE',
    ]);

    $base = [
        'csrf' => $scopeA['csrf'], 'module_view' => 'manufacturing', 'revision' => 1,
        'quantity' => '1', 'uom_key' => 'EA', 'currency' => 'PHP',
        'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31',
        'process_loss_percent' => '0',
    ];
    $child = yovel_admin_manufacturing_save_bom($contextA, array_replace($base, [
        'bom_code' => 'SUB-R1', 'item_key' => $itemKeys['subassembly'],
        'components' => [['item_key' => $itemKeys['raw_two'], 'quantity' => '2', 'uom_key' => 'EA']],
        'operations' => [['operation_key' => $operation['operation_key'], 'sequence' => 10, 'duration_minutes' => '30']],
    ]));
    $childStableKey = $child['bom_key'];
    $childUpdated = yovel_admin_manufacturing_save_bom($contextA, array_replace($base, [
        'bom_key' => $childStableKey, 'bom_code' => 'SUB-R1', 'item_key' => $itemKeys['subassembly'],
        'components' => [['item_key' => $itemKeys['raw_two'], 'quantity' => '2', 'uom_key' => 'EA']],
        'operations' => [['operation_key' => $operation['operation_key'], 'sequence' => 10, 'duration_minutes' => '30']],
        'notes' => 'Updated draft',
    ]));
    manufacturing_test_assert($childUpdated['bom_key'] === $childStableKey && $childUpdated['notes'] === 'Updated draft', 'BOM draft update did not preserve its stable key and exact read-back.');
    $childCost = yovel_admin_manufacturing_update_bom_cost($contextA, $childStableKey, '2026-06-01');
    manufacturing_test_assert($childCost['material_cost'] === '20.000000' && $childCost['operation_cost'] === '60.000000' && $childCost['total_cost'] === '80.000000', 'Child BOM deterministic roll-up is incorrect.');
    $childSubmitted = yovel_admin_manufacturing_submit_bom($contextA, $childStableKey, '2026-06-01');
    manufacturing_test_assert($childSubmitted['lifecycle_status'] === 'SUBMITTED' && yovel_admin_is_uuid($childSubmitted['submitted_version_key']), 'BOM submit did not create immutable submitted-version identity.');

    $alternative = yovel_admin_manufacturing_save_bom($contextA, array_replace($base, [
        'bom_code' => 'SUB-ALT-R1', 'item_key' => $itemKeys['subassembly'], 'is_alternative' => '1',
        'quantity' => '2',
        'alternative_for_bom_key' => $childStableKey,
        'components' => [['item_key' => $itemKeys['raw_alternative'], 'quantity' => '1', 'uom_key' => 'EA']],
    ]));
    $alternative = yovel_admin_manufacturing_submit_bom($contextA, $alternative['bom_key'], '2026-06-01');
    manufacturing_test_assert($alternative['lifecycle_status'] === 'SUBMITTED' && $alternative['alternative_for_bom_key'] === $childStableKey, 'Alternative BOM linkage or submission failed.');

    $parent = yovel_admin_manufacturing_save_bom($contextA, array_replace($base, [
        'bom_code' => 'FINISHED-R1', 'item_key' => $itemKeys['finished'], 'process_loss_percent' => '10',
        'components' => [
            ['item_key' => $itemKeys['subassembly'], 'quantity' => '2', 'uom_key' => 'EA', 'child_bom_key' => $childStableKey],
            ['item_key' => $itemKeys['raw_one'], 'quantity' => '3', 'uom_key' => 'EA'],
        ],
        'operations' => [['operation_key' => $operation['operation_key'], 'sequence' => 10, 'duration_minutes' => '60']],
        'secondary_items' => [['item_key' => $itemKeys['scrap'], 'secondary_type' => 'SCRAP', 'quantity' => '1', 'uom_key' => 'EA']],
    ]));
    manufacturing_test_assert(count($parent['components']) === 2 && count($parent['operations']) === 1 && count($parent['secondary_items']) === 1, 'BOM child tables were not read back exactly.');

    $explosion = yovel_admin_manufacturing_explode_bom($contextA, $parent['bom_key'], '2026-06-01');
    $explodedByItem = [];
    foreach ($explosion['items'] as $item) {
        $explodedByItem[$item['item_key']] = $item;
    }
    manufacturing_test_assert(($explodedByItem[$itemKeys['raw_two']]['quantity'] ?? '') === '4.000000000', 'Nested BOM explosion quantity is incorrect.');
    manufacturing_test_assert(($explodedByItem[$itemKeys['raw_one']]['quantity'] ?? '') === '3.000000000', 'Leaf component explosion quantity is incorrect.');
    manufacturing_test_assert(($explosion['operation_cost'] ?? '') === '240.000000', 'Nested operation roll-up is incorrect.');

    $alternativeExplosion = yovel_admin_manufacturing_explode_bom($contextA + ['alternatives' => [$childStableKey => $alternative['bom_key']]], $parent['bom_key'], '2026-06-01');
    $alternativeByItem = [];
    foreach ($alternativeExplosion['items'] as $item) {
        $alternativeByItem[$item['item_key']] = $item;
    }
    manufacturing_test_assert(($alternativeByItem[$itemKeys['raw_alternative']]['quantity'] ?? '') === '1.000000000' && !isset($alternativeByItem[$itemKeys['raw_two']]), 'Explicit alternative BOM selection did not use the selected revision output quantity.');

    $costed = yovel_admin_manufacturing_update_bom_cost($contextA, $parent['bom_key'], '2026-06-01');
    $repeatCost = yovel_admin_manufacturing_update_bom_cost($contextA, $parent['bom_key'], '2026-06-01');
    manufacturing_test_assert($costed['material_cost'] === '55.000000' && $costed['operation_cost'] === '240.000000', 'Parent BOM material or operation roll-up is incorrect.');
    manufacturing_test_assert($costed['process_loss_cost'] === '29.500000' && $costed['scrap_credit'] === '2.000000' && $costed['total_cost'] === '322.500000', 'BOM process loss, scrap, or Finance total is incorrect.');
    manufacturing_test_assert($repeatCost['cost_calculation_hash'] === $costed['cost_calculation_hash'] && preg_match('/^[a-f0-9]{64}$/', $costed['cost_calculation_hash']) === 1, 'BOM cost calculation is not reproducible.');
    manufacturing_test_assert(($costed['cost_inputs']['valuation_date'] ?? '') === '2026-06-01' && ($costed['cost_inputs']['finance_calculation_hash'] ?? '') !== '', 'BOM reproducible owner inputs were not persisted.');

    $submitted = yovel_admin_manufacturing_submit_bom($contextA, $parent['bom_key'], '2026-06-01');
    manufacturing_test_assert((int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_bom_version WHERE company_key_hash=? AND bom_key=?', [$scopeA['company']['company_key_hash'], $parent['bom_key']]) === 1, 'BOM submit did not persist one immutable version snapshot.');
    manufacturing_test_assert((int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_bom_update_batch WHERE company_key_hash=? AND bom_key=?', [$scopeA['company']['company_key_hash'], $parent['bom_key']]) === 2, 'BOM cost updates did not persist reproducible update batches.');
    manufacturing_test_assert((int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_bom_update_log WHERE company_key_hash=? AND bom_key=?', [$scopeA['company']['company_key_hash'], $parent['bom_key']]) === 6, 'BOM cost updates did not persist row-level owner input logs.');
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_save_bom($contextA, array_replace($base, ['bom_key' => $parent['bom_key'], 'bom_code' => 'FINISHED-R1', 'item_key' => $itemKeys['finished']])), 'submitted revision is immutable');
    $cancelled = yovel_admin_manufacturing_cancel_bom($contextA, $parent['bom_key'], 'Superseded by revision 2');
    manufacturing_test_assert($cancelled['lifecycle_status'] === 'CANCELLED' && $cancelled['cancellation_reason'] === 'Superseded by revision 2', 'BOM cancel lifecycle failed exact read-back.');
    $amended = yovel_admin_manufacturing_amend_bom($contextA, $parent['bom_key'], ['bom_code' => 'FINISHED-R2', 'effective_from' => '2026-07-01']);
    manufacturing_test_assert($amended['lifecycle_status'] === 'DRAFT' && (int) $amended['revision'] === 2 && $amended['amended_from_bom_key'] === $parent['bom_key'], 'BOM amendment did not create linked revision 2.');
    manufacturing_test_assert(count($amended['components']) === 2 && $amended['bom_key'] !== $parent['bom_key'], 'BOM amendment did not copy the immutable revision content.');

    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_save_bom($contextA, array_replace($base, [
        'bom_code' => 'DUPLICATE', 'item_key' => $itemKeys['finished'], 'components' => [
            ['item_key' => $itemKeys['raw_one'], 'quantity' => '1', 'uom_key' => 'EA'],
            ['item_key' => $itemKeys['raw_one'], 'quantity' => '2', 'uom_key' => 'EA'],
        ],
    ])), 'duplicate component');
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_save_bom($contextA, array_replace($base, [
        'bom_code' => 'DATES', 'item_key' => $itemKeys['finished'], 'effective_from' => '2026-12-31', 'effective_to' => '2026-01-01',
    ])), 'effective date');

    $cycleA = yovel_admin_manufacturing_save_bom($contextA, array_replace($base, [
        'bom_code' => 'CYCLE-A', 'item_key' => $itemKeys['cycle_a'],
        'components' => [['item_key' => $itemKeys['raw_one'], 'quantity' => '1', 'uom_key' => 'EA']],
    ]));
    $cycleB = yovel_admin_manufacturing_save_bom($contextA, array_replace($base, [
        'bom_code' => 'CYCLE-B', 'item_key' => $itemKeys['cycle_b'],
        'components' => [['item_key' => $itemKeys['cycle_a'], 'quantity' => '1', 'uom_key' => 'EA', 'child_bom_key' => $cycleA['bom_key']]],
    ]));
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_save_bom($contextA, array_replace($base, [
        'bom_key' => $cycleA['bom_key'], 'bom_code' => 'CYCLE-A', 'item_key' => $itemKeys['cycle_a'],
        'components' => [['item_key' => $itemKeys['cycle_b'], 'quantity' => '1', 'uom_key' => 'EA', 'child_bom_key' => $cycleB['bom_key']]],
    ])), 'cycle');

    manufacturing_test_assert(yovel_admin_manufacturing_boms($scopeB['company']) === [], 'BOM records leaked across company scope.');
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_bom($scopeB['company'], $childStableKey), 'owned by this company');

    $postInput = array_replace($base, [
        'csrf' => $scopeA['csrf'], 'section' => 'boms', 'bom_code' => 'HANDLER-R1',
        'item_key' => $itemKeys['finished'], 'components' => [['item_key' => $itemKeys['raw_one'], 'quantity' => '1', 'uom_key' => 'EA']],
    ]);
    $GLOBALS['yovel_admin_manufacturing_dependency_overrides'] = [
        'item_lookup' => $itemLookup,
        'item_uom_resolve' => $uomResolve,
        'item_valuation' => $valuation,
    ];
    $handler = yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_bom', $postInput);
    manufacturing_test_assert(($handler['section'] ?? '') === 'boms' && yovel_admin_is_uuid((string) ($handler['query']['bom'] ?? '')), 'Exact POST handler did not route BOM save.');
    $handlerBomKey = (string) $handler['query']['bom'];
    $costHandler = yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'update_manufacturing_bom_cost', ['csrf' => $scopeA['csrf'], 'module_view' => 'manufacturing', 'bom_key' => $handlerBomKey, 'valuation_date' => '2026-06-01']);
    $submitHandler = yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'submit_manufacturing_bom', ['csrf' => $scopeA['csrf'], 'module_view' => 'manufacturing', 'bom_key' => $handlerBomKey, 'valuation_date' => '2026-06-01']);
    $cancelHandler = yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'cancel_manufacturing_bom', ['csrf' => $scopeA['csrf'], 'module_view' => 'manufacturing', 'bom_key' => $handlerBomKey, 'cancellation_reason' => 'Handler lifecycle test']);
    $amendHandler = yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'amend_manufacturing_bom', ['csrf' => $scopeA['csrf'], 'module_view' => 'manufacturing', 'bom_key' => $handlerBomKey, 'bom_code' => 'HANDLER-R2', 'effective_from' => '2026-07-01', 'effective_to' => '2026-12-31']);
    manufacturing_test_assert(($costHandler['query']['bom'] ?? '') === $handlerBomKey && ($submitHandler['query']['bom'] ?? '') === $handlerBomKey && ($cancelHandler['query']['bom'] ?? '') === $handlerBomKey && yovel_admin_is_uuid((string) ($amendHandler['query']['bom'] ?? '')), 'Exact POST handler did not route every BOM lifecycle action.');
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_handle_post($scopeA['company'], $scopeA['admin'], 'save_manufacturing_bom', array_replace($postInput, ['csrf' => 'wrong'])), 'request token');

    $bomFormSchema = yovel_admin_manufacturing_form_schema($scopeA['company'], 'BOM');
    foreach (['bom_key', 'item_key', 'revision', 'lifecycle_status', 'quantity', 'uom_key', 'currency', 'submitted_version_key'] as $protectedField) {
        manufacturing_test_assert(in_array($protectedField, $bomFormSchema['requiredSystemFields'] ?? [], true), 'BOM Form Builder did not protect system field: ' . $protectedField . '.');
    }

    $markup = manufacturing_test_render($scopeA, 'boms');
    manufacturing_test_assert(str_contains($markup, 'data-manufacturing-bom-workspace'), 'BOM authoring workspace is missing.');
    foreach (['manufacturing-bom-modal', 'manufacturing-bom-submit-modal', 'manufacturing-bom-cancel-modal', 'manufacturing-bom-amend-modal', 'manufacturing-bom-cost-modal'] as $modalId) {
        manufacturing_test_assert(str_contains($markup, 'id="' . $modalId . '"'), 'BOM lifecycle modal is missing: ' . $modalId . '.');
    }
    manufacturing_test_assert(substr_count($markup, 'data-confirm-submit') >= 5 && str_contains($markup, 'data-confirm-dialog'), 'Every BOM create/lifecycle action must use modal plus sibling confirmation.');

    $beforeFault = count(yovel_admin_manufacturing_boms($scopeA['company']));
    $GLOBALS['yovel_admin_manufacturing_fault'] = static function (string $point): void {
        if ($point === 'before_readback') {
            throw new RuntimeException('Injected BOM exact read-back failure.');
        }
    };
    manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_save_bom($contextA, array_replace($base, [
        'bom_code' => 'ROLLBACK-R1', 'item_key' => $itemKeys['finished'],
        'components' => [['item_key' => $itemKeys['raw_one'], 'quantity' => '1', 'uom_key' => 'EA']],
    ])), 'read-back');
    unset($GLOBALS['yovel_admin_manufacturing_fault']);
    manufacturing_test_assert(count(yovel_admin_manufacturing_boms($scopeA['company'])) === $beforeFault, 'BOM exact read-back failure did not roll back record and audit state.');
} finally {
    unset($GLOBALS['yovel_admin_manufacturing_dependency_overrides']);
    manufacturing_test_cleanup_scope($scopeA);
    manufacturing_test_cleanup_scope($scopeB);
}

echo "Manufacturing BOM tests passed.\n";
