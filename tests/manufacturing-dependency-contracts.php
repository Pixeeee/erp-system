<?php
declare(strict_types=1);

require __DIR__ . '/manufacturing-test-helper.php';

$gateway = yovel_admin_manufacturing_dependency_gateway();
$expected = [
    'item_lookup', 'item_uom_resolve', 'barcode_resolve', 'warehouses', 'bin_snapshot', 'stock_snapshot', 'item_valuation',
    'putaway_plan', 'reorder_recommendations', 'capacity_summary', 'locked_bin', 'stock_reservation',
    'material_issue', 'finished_goods_receipt', 'material_request', 'supplier_lookup', 'account_validation',
    'cost_preview', 'posting_request',
];
manufacturing_test_assert(array_keys($gateway) === $expected, 'Manufacturing dependency allow-list is incomplete or unstable.');

$available = [
    'item_lookup' => 'yovel_admin_inventory_item',
    'item_uom_resolve' => 'yovel_admin_resolve_item_uom',
    'barcode_resolve' => 'yovel_admin_resolve_item_barcode',
    'warehouses' => 'yovel_admin_inventory_warehouses',
    'bin_snapshot' => 'yovel_admin_inventory_bin',
    'stock_snapshot' => 'yovel_admin_inventory_projection_for_warehouse_item',
    'item_valuation' => 'yovel_admin_inventory_item_valuation',
    'putaway_plan' => 'yovel_admin_putaway_plan',
    'reorder_recommendations' => 'yovel_admin_reorder_recommendations',
    'capacity_summary' => 'yovel_admin_inventory_warehouse_capacity_summary',
    'locked_bin' => 'yovel_admin_inventory_lock_bin',
    'supplier_lookup' => 'yovel_admin_buying_supplier',
    'account_validation' => 'yovel_admin_finance_account_reference',
    'cost_preview' => 'yovel_admin_finance_manufacturing_cost_preview',
];
foreach ($available as $key => $function) {
    manufacturing_test_assert(($gateway[$key]['owner_function'] ?? '') === $function, 'Manufacturing gateway mapped the wrong owner function for ' . $key . '.');
    manufacturing_test_assert(($gateway[$key]['available'] ?? false) === true && is_callable($gateway[$key]['callable'] ?? null), 'Verified owner callable is unavailable: ' . $key . '.');
}
foreach (['stock_reservation', 'material_issue', 'finished_goods_receipt', 'material_request', 'posting_request'] as $key) {
    manufacturing_test_assert(($gateway[$key]['available'] ?? true) === false && ($gateway[$key]['callable'] ?? null) === null, 'Unverified dependency callable must remain unavailable: ' . $key . '.');
    manufacturing_test_assert(str_starts_with((string) ($gateway[$key]['signature'] ?? ''), (string) ($gateway[$key]['owner_function'] ?? '') . '('), 'Missing dependency must publish its exact owner callable signature: ' . $key . '.');
}

$called = [];
$fake = yovel_admin_manufacturing_dependency_gateway([
    'item_lookup' => static function (array $company, string $itemKey) use (&$called): array {
        $called = [$company['company_key_hash'], $itemKey];
        return ['item_key' => $itemKey];
    },
]);
$result = yovel_admin_manufacturing_dependency_call($fake, 'item_lookup', [['company_key_hash' => str_repeat('a', 64)], 'item-key']);
manufacturing_test_assert(($result['item_key'] ?? '') === 'item-key' && $called === [str_repeat('a', 64), 'item-key'], 'Manufacturing dependency fake was not invoked with the exact payload.');
manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_dependency_call($gateway, 'material_issue', []), 'owner service is unavailable');
manufacturing_test_expect_error(fn () => yovel_admin_manufacturing_dependency_gateway(['not_allowed' => static fn () => null]), 'not allow-listed');

$previewScope = manufacturing_test_create_scope('dependency-preview');
try {
    $beforePreviewAudit = (int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_audit WHERE company_key_hash=?', [$previewScope['company']['company_key_hash']]);
    $previewRequest = ['currency' => 'PHP', 'materials' => [['amount' => '25']], 'operations' => [['hours' => '2', 'hourly_rate' => '50']], 'additional_costs' => [], 'scrap' => [['amount' => '5']]];
    $preview = yovel_admin_manufacturing_dependency_call($gateway, 'cost_preview', [$previewScope['company'], $previewRequest]);
    $repeatPreview = yovel_admin_manufacturing_dependency_call($gateway, 'cost_preview', [$previewScope['company'], $previewRequest]);
    manufacturing_test_assert($preview === $repeatPreview && ($preview['total_cost'] ?? '') === '120.000000', 'Finance cost preview gateway is not deterministic.');
    manufacturing_test_assert((int) bx_db()->GetOne('SELECT COUNT(*) FROM project_company_manufacturing_audit WHERE company_key_hash=?', [$previewScope['company']['company_key_hash']]) === $beforePreviewAudit, 'Finance cost preview wrote Manufacturing audit state.');
} finally {
    manufacturing_test_cleanup_scope($previewScope);
}

$source = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/company/admin/modules/manufacturing', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $source .= (string) file_get_contents($file->getPathname());
    }
}
manufacturing_test_assert(preg_match('/project_company_(inventory|buying|finance|accounting|general_ledger)_/i', $source) !== 1, 'Manufacturing source contains foreign owner-table SQL.');

echo "Manufacturing dependency contract tests passed.\n";
