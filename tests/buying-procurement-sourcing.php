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

foreach (['settings.php', 'suppliers.php', 'scorecards.php', 'sourcing.php', 'dashboard.php', 'functions.php'] as $moduleFile) {
    $path = $root . '/company/admin/modules/buying-procurement/' . $moduleFile;
    if (is_file($path)) {
        require_once $path;
    }
}

$options = getopt('', ['group:']);
$group = strtolower(trim((string) ($options['group'] ?? 'all')));
if (!in_array($group, ['rfq', 'supplier-quotation', 'all'], true)) {
    throw new InvalidArgumentException('Unknown Buying sourcing test group.');
}
if ($group === 'supplier-quotation') {
    buying_test_run_supplier_quotation_group($root);
    exit(0);
}

foreach ([
    'yovel_admin_buying_sourcing_dependency_gateway',
    'yovel_admin_buying_save_rfq',
    'yovel_admin_buying_transition_rfq',
    'yovel_admin_buying_rfq',
    'yovel_admin_buying_map_material_requests_to_rfq',
] as $service) {
    buying_test_assert(function_exists($service), 'Missing Task 5 RFQ service: ' . $service);
}

$saveReflection = new ReflectionFunction('yovel_admin_buying_save_rfq');
$transitionReflection = new ReflectionFunction('yovel_admin_buying_transition_rfq');
$readReflection = new ReflectionFunction('yovel_admin_buying_rfq');
$mapReflection = new ReflectionFunction('yovel_admin_buying_map_material_requests_to_rfq');
buying_test_assert($saveReflection->getNumberOfRequiredParameters() === 4, 'RFQ save signature changed.');
buying_test_assert($transitionReflection->getNumberOfRequiredParameters() === 6, 'RFQ lifecycle signature changed.');
buying_test_assert($readReflection->getNumberOfRequiredParameters() === 2, 'RFQ read signature changed.');
buying_test_assert($mapReflection->getNumberOfRequiredParameters() === 3, 'Material-request mapping signature changed.');

yovel_admin_buying_schema();
$db = bx_db();
$ownerFixture = buying_test_temporary_company($db, 'Buying RFQ Owner');
$relatedFixture = buying_test_temporary_company($db, 'Buying RFQ Related');
$owner = $ownerFixture['company'];
$ownerAdmin = $ownerFixture['admin'];
$related = $relatedFixture['company'];
$relatedAdmin = $relatedFixture['admin'];
$itemKey = bx_uuid();
$uomKey = bx_uuid();
$materialRequestKey = bx_uuid();
$materialRequestItemKey = bx_uuid();
$deliveryCalls = [];

$itemProvider = static function (array $company, string $requestedItemKey) use ($itemKey, $uomKey): ?array {
    if ($requestedItemKey !== $itemKey) {
        return null;
    }
    return [
        'company_key_hash' => (string) $company['company_key_hash'],
        'item_key' => $itemKey,
        'item_code' => 'RFQ-ITEM-001',
        'item_name' => 'RFQ verified item',
        'item_description' => 'Inventory-owned item snapshot.',
        'item_status' => 'ACTIVE',
        'uoms' => [[
            'uom_key' => $uomKey,
            'uom_code' => 'BOX',
            'uom_name' => 'Box',
            'conversion_factor' => '12.000000000',
        ]],
    ];
};
$uomProvider = static function (array $company, string $requestedItemKey, string $uomCode, string $quantity) use ($itemKey): array {
    buying_test_assert($requestedItemKey === $itemKey && $uomCode === 'BOX', 'RFQ called Inventory UOM resolution with the wrong identity.');
    $normalized = number_format((float) $quantity, 9, '.', '');
    return [
        'quantity' => $normalized,
        'conversion_factor' => '12.000000000',
        'stock_quantity' => number_format((float) $quantity * 12, 9, '.', ''),
        'stock_uom_code' => 'EA',
    ];
};
$materialProvider = static function (array $company, array $filters) use ($itemKey, $uomKey, $materialRequestKey, $materialRequestItemKey): array {
    return [
        'contract' => 'inventory.buying-snapshot.v1',
        'company_key_hash' => (string) $company['company_key_hash'],
        'material_requests' => [[
            'material_request_key' => $materialRequestKey,
            'transaction_date' => '2026-08-20',
            'schedule_date' => '2026-09-05',
            'subject' => 'Mapped material request',
            'items' => [[
                'material_request_item_key' => $materialRequestItemKey,
                'inventory_item_key' => $itemKey,
                'uom_key' => $uomKey,
                'quantity' => '4.000000',
                'schedule_date' => '2026-09-05',
                'warehouse_key' => null,
            ]],
        ]],
        'filters' => $filters,
    ];
};
$deliveryProvider = static function (array $company, array $payload) use (&$deliveryCalls): array {
    $deliveryCalls[] = $payload;
    return [
        'job_key' => substr(hash('sha256', (string) $payload['idempotency_key']), 0, 8) . '-0000-4000-8000-000000000000',
        'idempotency_key' => (string) $payload['idempotency_key'],
        'status' => 'QUEUED',
        'checksum' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
    ];
};

$GLOBALS['yovel_admin_buying_sourcing_dependency_overrides'] = [
    'item_lookup' => $itemProvider,
    'item_uom_resolve' => $uomProvider,
    'material_request_snapshot' => $materialProvider,
    'delivery_register' => $deliveryProvider,
];

$supplierKeys = [];
try {
    $gateway = yovel_admin_buying_sourcing_dependency_gateway($GLOBALS['yovel_admin_buying_sourcing_dependency_overrides']);
    buying_test_assert(array_keys($gateway) === ['item_lookup', 'item_uom_resolve', 'material_request_snapshot', 'delivery_register', 'finance_purchase_calculation'], 'RFQ dependency allow-list is incomplete or unstable.');
    buying_test_expect_error(static fn () => yovel_admin_buying_sourcing_dependency_gateway(['foreign' => static fn (): array => []]), 'not allow-listed');

    yovel_admin_buying_save_settings($db, $owner, $ownerAdmin, [
        'supplier_naming_mode' => 'SUPPLIER_NAME',
        'maintain_same_rate_action' => 'STOP',
        'allow_zero_qty_rfq' => '0',
    ]);
    yovel_admin_buying_save_settings($db, $related, $relatedAdmin, [
        'supplier_naming_mode' => 'SUPPLIER_NAME',
        'maintain_same_rate_action' => 'STOP',
    ]);

    $supplierOne = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'RFQ Supplier One', 'supplier_type' => 'COMPANY', 'email' => 'rfq-one@example.test',
    ]);
    $supplierTwo = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'RFQ Supplier Two', 'supplier_type' => 'COMPANY', 'email' => 'rfq-two@example.test',
    ]);
    $missingRecipient = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'RFQ Missing Recipient', 'supplier_type' => 'COMPANY',
    ]);
    $inactiveSupplier = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'RFQ Inactive Supplier', 'supplier_type' => 'COMPANY', 'email' => 'inactive@example.test',
    ]);
    yovel_admin_buying_set_supplier_status($db, $owner, $ownerAdmin, (string) $inactiveSupplier['supplier_key'], 'DISABLE', []);
    $preventedSupplier = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'RFQ Prevented Supplier', 'supplier_type' => 'COMPANY', 'email' => 'prevented@example.test', 'prevent_rfqs' => '1',
    ]);
    $relatedSupplier = yovel_admin_buying_save_supplier($db, $related, $relatedAdmin, [
        'supplier_name' => 'RFQ Related Supplier', 'supplier_type' => 'COMPANY', 'email' => 'related@example.test',
    ]);
    $supplierKeys = array_map(static fn (array $supplier): string => (string) $supplier['supplier_key'], [
        $supplierOne, $supplierTwo, $missingRecipient, $inactiveSupplier, $preventedSupplier,
    ]);

    $baseInput = static function (array $overrides = []) use (
        $supplierOne,
        $supplierTwo,
        $itemKey,
        $uomKey,
        $materialRequestKey,
        $materialRequestItemKey
    ): array {
        return array_replace_recursive([
            'transaction_date' => '2026-08-25',
            'schedule_date' => '2026-09-05',
            'subject' => 'Task 5 supplier invitation',
            'terms' => 'Quote using the requested UOM.',
            'message_for_supplier' => 'Please respond before the schedule date.',
            'suppliers' => [
                ['supplier_key' => $supplierOne['supplier_key'], 'email' => 'rfq-one@example.test', 'delivery_channel' => 'EMAIL'],
                ['supplier_key' => $supplierTwo['supplier_key'], 'email' => 'rfq-two@example.test', 'delivery_channel' => 'PORTAL'],
            ],
            'items' => [[
                'inventory_item_key' => $itemKey,
                'uom_key' => $uomKey,
                'quantity' => '4.500000',
                'schedule_date' => '2026-09-05',
                'material_request_key' => $materialRequestKey,
                'material_request_item_key' => $materialRequestItemKey,
            ]],
        ], $overrides);
    };

    foreach ([
        ['suppliers' => [
            ['supplier_key' => $supplierOne['supplier_key'], 'email' => 'rfq-one@example.test'],
            ['supplier_key' => $supplierOne['supplier_key'], 'email' => 'rfq-one@example.test'],
        ], 'error' => 'duplicate supplier'],
        ['suppliers' => [['supplier_key' => $inactiveSupplier['supplier_key'], 'email' => 'inactive@example.test']], 'error' => 'active'],
        ['suppliers' => [['supplier_key' => $preventedSupplier['supplier_key'], 'email' => 'prevented@example.test']], 'error' => 'prevented'],
        ['suppliers' => [['supplier_key' => $missingRecipient['supplier_key'], 'email' => '', 'delivery_channel' => 'EMAIL']], 'error' => 'recipient'],
        ['items' => [[
            'inventory_item_key' => bx_uuid(), 'uom_key' => $uomKey, 'quantity' => '1', 'schedule_date' => '2026-09-05',
        ]], 'error' => 'item'],
        ['items' => [[
            'inventory_item_key' => $itemKey, 'uom_key' => bx_uuid(), 'quantity' => '1', 'schedule_date' => '2026-09-05',
        ]], 'error' => 'uom'],
        ['items' => [[
            'inventory_item_key' => $itemKey, 'uom_key' => $uomKey, 'quantity' => '0', 'schedule_date' => '2026-09-05',
        ]], 'error' => 'zero'],
        ['items' => [[
            'inventory_item_key' => $itemKey, 'uom_key' => $uomKey, 'quantity' => '1', 'schedule_date' => '2026-08-24',
        ]], 'error' => 'schedule'],
    ] as $invalid) {
        $overrides = $invalid;
        $needle = (string) $overrides['error'];
        unset($overrides['error']);
        buying_test_expect_error(static fn () => yovel_admin_buying_save_rfq($db, $owner, $ownerAdmin, $baseInput($overrides)), $needle);
    }
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_rfq WHERE company_key_hash = ?', [$owner['company_key_hash']]) === 0, 'Invalid RFQ validation wrote data.');

    $created = yovel_admin_buying_save_rfq($db, $owner, $ownerAdmin, $baseInput());
    $rfqKey = (string) $created['rfq_key'];
    buying_test_assert(yovel_admin_is_uuid($rfqKey) && str_starts_with((string) $created['rfq_number'], 'RFQ-'), 'RFQ create did not assign stable identities.');
    buying_test_assert((string) $created['document_status'] === 'DRAFT' && (int) $created['row_version'] === 1, 'RFQ create did not return a versioned draft.');
    buying_test_assert(count($created['suppliers']) === 2 && count($created['items']) === 1, 'RFQ children were not read back exactly.');
    buying_test_assert((string) $created['items'][0]['item_code_snapshot'] === 'RFQ-ITEM-001' && (string) $created['items'][0]['uom_snapshot'] === 'BOX', 'RFQ lost verified Inventory snapshots.');
    buying_test_assert((string) $created['items'][0]['material_request_item_key'] === $materialRequestItemKey, 'RFQ lost its stable material-request item reference.');
    $supplierChildKeys = array_column($created['suppliers'], 'rfq_supplier_key', 'supplier_key');
    $itemChildKey = (string) $created['items'][0]['rfq_item_key'];

    $updatedInput = $baseInput([
        'rfq_key' => $rfqKey,
        'expected_version' => '1',
        'subject' => 'Task 5 supplier invitation updated',
    ]);
    $updated = yovel_admin_buying_save_rfq($db, $owner, $ownerAdmin, $updatedInput);
    buying_test_assert((int) $updated['row_version'] === 2 && (string) $updated['subject'] === 'Task 5 supplier invitation updated', 'RFQ optimistic update was not read back.');
    buying_test_assert(array_column($updated['suppliers'], 'rfq_supplier_key', 'supplier_key') === $supplierChildKeys, 'RFQ supplier child keys changed during update.');
    buying_test_assert((string) $updated['items'][0]['rfq_item_key'] === $itemChildKey, 'RFQ item key changed during update.');
    buying_test_expect_error(static fn () => yovel_admin_buying_save_rfq($db, $owner, $ownerAdmin, $updatedInput), 'changed');
    buying_test_assert((int) yovel_admin_buying_rfq($owner, $rfqKey)['row_version'] === 2, 'Stale RFQ write changed the persisted version.');

    buying_test_assert(yovel_admin_buying_rfq($related, $rfqKey) === null, 'Cross-company RFQ read leaked a record.');
    buying_test_expect_error(
        static fn () => yovel_admin_buying_transition_rfq($db, $related, $relatedAdmin, $rfqKey, 'SUBMIT', ['expected_version' => 2]),
        'not found'
    );

    $rollbackSubject = 'Injected RFQ child rollback';
    $GLOBALS['yovel_admin_buying_sourcing_write_hook'] = static function (string $childType, int $index): void {
        if ($childType === 'item' && $index === 0) {
            throw new RuntimeException('Injected RFQ item failure.');
        }
    };
    buying_test_expect_error(static fn () => yovel_admin_buying_save_rfq($db, $owner, $ownerAdmin, $baseInput(['subject' => $rollbackSubject])), 'injected');
    unset($GLOBALS['yovel_admin_buying_sourcing_write_hook']);
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_rfq WHERE company_key_hash = ? AND subject = ?', [$owner['company_key_hash'], $rollbackSubject]) === 0, 'Injected RFQ child failure committed its parent.');

    $transportAttempt = 0;
    $GLOBALS['yovel_admin_buying_sourcing_dependency_overrides']['delivery_register'] = static function (array $company, array $payload) use (&$transportAttempt, $deliveryProvider): array {
        $transportAttempt++;
        if ($transportAttempt === 2) {
            throw new RuntimeException('Injected RFQ transport registration failure.');
        }
        return $deliveryProvider($company, $payload);
    };
    buying_test_expect_error(
        static fn () => yovel_admin_buying_transition_rfq($db, $owner, $ownerAdmin, $rfqKey, 'SUBMIT', ['expected_version' => 2]),
        'transport'
    );
    $afterTransportFailure = yovel_admin_buying_rfq($owner, $rfqKey);
    buying_test_assert((string) $afterTransportFailure['document_status'] === 'DRAFT' && (int) $afterTransportFailure['row_version'] === 2, 'Transport failure did not roll back the RFQ header.');
    buying_test_assert(array_unique(array_column($afterTransportFailure['suppliers'], 'delivery_status')) === ['NOT_REQUESTED'], 'Transport failure committed recipient state.');

    $GLOBALS['yovel_admin_buying_sourcing_dependency_overrides']['delivery_register'] = $deliveryProvider;
    $deliveryCalls = [];
    $submitted = yovel_admin_buying_transition_rfq($db, $owner, $ownerAdmin, $rfqKey, 'SUBMIT', ['expected_version' => 2]);
    buying_test_assert((string) $submitted['document_status'] === 'SUBMITTED' && (int) $submitted['row_version'] === 3, 'Draft RFQ did not submit exactly.');
    buying_test_assert(count($deliveryCalls) === 2 && array_unique(array_column($submitted['suppliers'], 'delivery_status')) === ['PENDING'], 'RFQ submit did not register every recipient.');
    foreach ($submitted['suppliers'] as $supplierRow) {
        buying_test_assert(str_starts_with((string) $supplierRow['dispatch_idempotency_key'], 'buying-rfq-delivery:'), 'RFQ delivery idempotency key is missing.');
        buying_test_assert(yovel_admin_is_uuid((string) $supplierRow['dispatch_job_key']), 'RFQ delivery job key was not read back.');
    }
    buying_test_expect_error(static fn () => yovel_admin_buying_save_rfq($db, $owner, $ownerAdmin, $baseInput(['rfq_key' => $rfqKey, 'expected_version' => 3])), 'submitted');

    $beforeRetryKeys = array_column($submitted['suppliers'], 'dispatch_job_key', 'supplier_key');
    $retried = yovel_admin_buying_transition_rfq($db, $owner, $ownerAdmin, $rfqKey, 'RETRY_DISPATCH', [
        'expected_version' => 3,
        'supplier_key' => (string) $supplierOne['supplier_key'],
    ]);
    buying_test_assert(count($retried['suppliers']) === 2 && array_column($retried['suppliers'], 'dispatch_job_key', 'supplier_key') === $beforeRetryKeys, 'Idempotent RFQ retry duplicated or changed local delivery rows.');
    $received = yovel_admin_buying_transition_rfq($db, $owner, $ownerAdmin, $rfqKey, 'MARK_RECEIVED', [
        'expected_version' => (int) $retried['row_version'],
        'supplier_key' => (string) $supplierOne['supplier_key'],
    ]);
    $receivedBySupplier = array_column($received['suppliers'], 'delivery_status', 'supplier_key');
    buying_test_assert(($receivedBySupplier[(string) $supplierOne['supplier_key']] ?? '') === 'RECEIVED', 'RFQ recipient status did not move to Received.');
    $cancelled = yovel_admin_buying_transition_rfq($db, $owner, $ownerAdmin, $rfqKey, 'CANCEL', ['expected_version' => (int) $received['row_version']]);
    buying_test_assert((string) $cancelled['document_status'] === 'CANCELLED', 'Submitted RFQ did not cancel.');
    $amended = yovel_admin_buying_transition_rfq($db, $owner, $ownerAdmin, $rfqKey, 'AMEND', ['expected_version' => (int) $cancelled['row_version']]);
    buying_test_assert((string) $amended['document_status'] === 'DRAFT' && (string) $amended['amended_from_key'] === $rfqKey, 'Cancelled RFQ amendment was not created.');
    buying_test_assert((string) $amended['rfq_key'] !== $rfqKey && (string) $amended['items'][0]['material_request_item_key'] === $materialRequestItemKey, 'RFQ amendment lost identity or source traceability.');

    $mapped = yovel_admin_buying_map_material_requests_to_rfq(
        $owner,
        [$materialRequestKey],
        [(string) $supplierOne['supplier_key']]
    );
    buying_test_assert(count($mapped['items']) === 1 && (string) $mapped['items'][0]['material_request_item_key'] === $materialRequestItemKey, 'Material-request mapping lost its stable item reference.');
    buying_test_assert((string) $mapped['suppliers'][0]['supplier_key'] === (string) $supplierOne['supplier_key'], 'Material-request mapping lost its supplier selection.');

    $auditRows = $db->GetAll(
        "SELECT action, new_values FROM builder_audit_log WHERE module = 'project_company_buying_rfq' AND record_key IN (?, ?) ORDER BY x_id",
        [$rfqKey, (string) $amended['rfq_key']]
    );
    $auditText = json_encode($auditRows, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    buying_test_assert(str_contains($auditText, (string) $ownerAdmin['admin_key']) && str_contains($auditText, (string) $owner['company_key_hash']), 'RFQ audit lost actor or company scope.');
    foreach (['CREATE', 'UPDATE', 'SUBMIT', 'RETRY_DISPATCH', 'MARK_RECEIVED', 'CANCEL', 'AMEND'] as $action) {
        buying_test_assert(in_array($action, array_column($auditRows, 'action'), true), 'RFQ audit omitted lifecycle action: ' . $action);
    }

    $data = yovel_admin_buying_procurement_data($owner, $ownerAdmin);
    buying_test_assert(count($data['rfqs'] ?? []) >= 2, 'Buying data provider omitted RFQs.');
    $activeModuleSection = 'request-for-quotation';
    $activeModuleMeta = yovel_admin_buying_procurement_sections()[$activeModuleSection];
    $activeModuleData = $data;
    $activeModuleFormState = [
        'section' => 'request-for-quotation',
        'action' => 'save_buying_rfq',
        'input' => [
            'subject' => 'Rehydrated RFQ subject',
            'transaction_date' => '2026-08-25',
            'schedule_date' => '2026-09-05',
            'suppliers_text' => (string) $supplierOne['supplier_key'] . ' | rehydrated@example.test | EMAIL',
            'items_text' => $itemKey . ' | ' . $uomKey . ' | 2 | 2026-09-05 | | |',
        ],
        'error' => 'Server RFQ validation failed <script>alert(1)</script>.',
    ];
    $companyName = (string) $owner['company_name'];
    ob_start();
    require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
    $markup = (string) ob_get_clean();
    foreach ([
        'data-buying-sourcing', 'data-buying-rfq-open', 'buying-rfq-modal', 'data-record-modal',
        'data-confirm-submit', 'name="module_view" value="buying-procurement"', 'name="action" value="save_buying_rfq"',
        'data-record-modal-open-on-load', 'Rehydrated RFQ subject', '&lt;script&gt;alert(1)&lt;/script&gt;',
        'Submit RFQ', 'Cancel RFQ', 'Amend RFQ', 'Mark Received', 'Retry Dispatch',
    ] as $marker) {
        buying_test_assert(str_contains($markup, $marker), 'RFQ workspace is missing marker: ' . $marker);
    }
    buying_test_assert(!str_contains($markup, '<script>alert(1)</script>'), 'RFQ server error was not escaped.');
    buying_test_assert(strpos($markup, 'data-buying-main-panel') < strpos($markup, 'data-buying-tools-panel'), 'RFQ workspace no longer stacks main-first.');

    $source = (string) file_get_contents($root . '/company/admin/modules/buying-procurement/sourcing.php');
    buying_test_assert(preg_match('/project_company_(inventory|operations|finance)_/i', $source) !== 1, 'RFQ source contains foreign-owner table SQL.');
    buying_test_assert(substr_count($source, 'yovel_admin_buying_in_transaction(') >= 2, 'RFQ public mutations do not own one explicit transaction.');
    buying_test_assert(str_contains($source, 'FOR UPDATE'), 'RFQ mutations do not lock owned rows.');
    buying_test_assert(str_contains($source, 'yovel_admin_inventory_item') && str_contains($source, 'yovel_admin_resolve_item_uom'), 'RFQ gateway omits verified Inventory item/UOM callables.');
} finally {
    unset(
        $GLOBALS['yovel_admin_buying_sourcing_dependency_overrides'],
        $GLOBALS['yovel_admin_buying_sourcing_write_hook']
    );
    foreach ([$ownerFixture, $relatedFixture] as $fixture) {
        $companyHash = (string) $fixture['company']['company_key_hash'];
        buying_test_execute($db, "DELETE FROM builder_audit_log WHERE module IN ('project_company_buying_rfq','project_company_buying_rfq_supplier','project_company_buying_rfq_item') AND new_values LIKE ?", ['%' . $companyHash . '%'], 'Temporary RFQ audit cleanup');
        buying_test_execute($db, 'DELETE FROM project_company_buying_rfq_item WHERE company_key_hash = ?', [$companyHash], 'Temporary RFQ item cleanup');
        buying_test_execute($db, 'DELETE FROM project_company_buying_rfq_supplier WHERE company_key_hash = ?', [$companyHash], 'Temporary RFQ supplier cleanup');
        buying_test_execute($db, 'DELETE FROM project_company_buying_rfq WHERE company_key_hash = ?', [$companyHash], 'Temporary RFQ cleanup');
    }
    buying_test_cleanup_temporary_company($db, $ownerFixture);
    buying_test_cleanup_temporary_company($db, $relatedFixture);
}

echo "Buying / Procurement RFQ checks passed.\n";
if ($group === 'all') {
    buying_test_run_supplier_quotation_group($root);
    echo "Buying / Procurement sourcing checks passed.\n";
}

function buying_test_run_supplier_quotation_group(string $root): void
{
    foreach ([
        'yovel_admin_buying_save_supplier_quotation',
        'yovel_admin_buying_transition_supplier_quotation',
        'yovel_admin_buying_supplier_quotation',
        'yovel_admin_buying_map_rfq_to_supplier_quotation',
        'yovel_admin_buying_supplier_quotation_comparison_inputs',
    ] as $service) {
        buying_test_assert(function_exists($service), 'Missing Task 6 Supplier Quotation service: ' . $service);
    }
    buying_test_assert((new ReflectionFunction('yovel_admin_buying_save_supplier_quotation'))->getNumberOfRequiredParameters() === 4, 'Supplier Quotation save signature changed.');
    buying_test_assert((new ReflectionFunction('yovel_admin_buying_transition_supplier_quotation'))->getNumberOfRequiredParameters() === 6, 'Supplier Quotation lifecycle signature changed.');
    buying_test_assert((new ReflectionFunction('yovel_admin_buying_map_rfq_to_supplier_quotation'))->getNumberOfRequiredParameters() === 3, 'RFQ-to-quotation map signature changed.');

    yovel_admin_buying_schema();
    $db = bx_db();
    $ownerFixture = buying_test_temporary_company($db, 'Buying Quotation Owner');
    $relatedFixture = buying_test_temporary_company($db, 'Buying Quotation Related');
    $owner = $ownerFixture['company'];
    $ownerAdmin = $ownerFixture['admin'];
    $related = $relatedFixture['company'];
    $relatedAdmin = $relatedFixture['admin'];
    $itemKey = bx_uuid();
    $uomKey = bx_uuid();
    $financeCalls = [];

    $itemProvider = static fn (array $company, string $requestedItemKey): ?array => $requestedItemKey === $itemKey ? [
        'company_key_hash' => (string) $company['company_key_hash'],
        'item_key' => $itemKey,
        'item_code' => 'SQ-ITEM-001',
        'item_name' => 'Quotation verified item',
        'item_description' => 'Inventory-owned quotation item.',
        'item_status' => 'ACTIVE',
        'uoms' => [['uom_key' => $uomKey, 'uom_code' => 'BOX', 'uom_name' => 'Box', 'conversion_factor' => '12.000000000']],
    ] : null;
    $uomProvider = static function (array $company, string $requestedItemKey, string $uomCode, string $quantity) use ($itemKey): array {
        buying_test_assert($requestedItemKey === $itemKey && $uomCode === 'BOX', 'Supplier Quotation used the wrong Inventory item/UOM identity.');
        return [
            'quantity' => number_format((float) $quantity, 9, '.', ''),
            'conversion_factor' => '12.000000000',
            'stock_quantity' => number_format((float) $quantity * 12, 9, '.', ''),
            'stock_uom_code' => 'EA',
        ];
    };
    $deliveryProvider = static fn (array $company, array $payload): array => [
        'job_key' => substr(hash('sha256', (string) $payload['idempotency_key']), 0, 8) . '-0000-4000-8000-000000000000',
        'idempotency_key' => (string) $payload['idempotency_key'],
        'status' => 'QUEUED',
        'checksum' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
    ];
    $financeProvider = static function (array $company, array $payload) use (&$financeCalls): array {
        $financeCalls[] = $payload;
        $items = [];
        $net = 0.0;
        foreach ($payload['items'] as $line) {
            $gross = (float) $line['quantity'] * (float) $line['rate'];
            $lineNet = $gross * (1 - ((float) $line['discount_percentage'] / 100));
            $lineTax = $lineNet * 0.12;
            $net += $lineNet;
            $items[] = [
                'line_no' => (int) $line['line_no'],
                'inventory_item_key' => (string) $line['inventory_item_key'],
                'uom_key' => (string) $line['uom_key'],
                'quantity' => number_format((float) $line['quantity'], 6, '.', ''),
                'rate' => number_format((float) $line['rate'], 6, '.', ''),
                'discount_percentage' => number_format((float) $line['discount_percentage'], 4, '.', ''),
                'net_amount' => number_format($lineNet, 6, '.', ''),
                'tax_amount' => number_format($lineTax, 6, '.', ''),
                'gross_amount' => number_format($lineNet + $lineTax, 6, '.', ''),
            ];
        }
        $tax = $net * 0.12;
        return [
            'contract' => 'finance.purchase-document-calculation.v1',
            'company_key_hash' => (string) $company['company_key_hash'],
            'currency' => (string) $payload['currency'],
            'conversion_rate' => number_format((float) $payload['conversion_rate'], 8, '.', ''),
            'net_total' => number_format($net, 6, '.', ''),
            'tax_total' => number_format($tax, 6, '.', ''),
            'grand_total' => number_format($net + $tax, 6, '.', ''),
            'items' => $items,
            'taxes' => [['tax_code' => 'VAT12', 'rate' => '12.0000', 'amount' => number_format($tax, 6, '.', '')]],
        ];
    };

    $GLOBALS['yovel_admin_buying_sourcing_dependency_overrides'] = [
        'item_lookup' => $itemProvider,
        'item_uom_resolve' => $uomProvider,
        'delivery_register' => $deliveryProvider,
        'finance_purchase_calculation' => $financeProvider,
    ];
    try {
        $gateway = yovel_admin_buying_sourcing_dependency_gateway($GLOBALS['yovel_admin_buying_sourcing_dependency_overrides']);
        buying_test_assert(array_keys($gateway) === ['item_lookup', 'item_uom_resolve', 'material_request_snapshot', 'delivery_register', 'finance_purchase_calculation'], 'Supplier Quotation dependency allow-list is incomplete or unstable.');

        yovel_admin_buying_save_settings($db, $owner, $ownerAdmin, [
            'supplier_naming_mode' => 'SUPPLIER_NAME',
            'maintain_same_rate_action' => 'WARN',
            'allow_zero_qty_supplier_quotation' => '0',
        ]);
        yovel_admin_buying_save_settings($db, $related, $relatedAdmin, [
            'supplier_naming_mode' => 'SUPPLIER_NAME',
            'maintain_same_rate_action' => 'WARN',
        ]);
        $supplierOne = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
            'supplier_name' => 'Quotation Supplier One', 'supplier_type' => 'COMPANY', 'email' => 'quote-one@example.test',
        ]);
        $supplierTwo = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
            'supplier_name' => 'Quotation Supplier Two', 'supplier_type' => 'COMPANY', 'email' => 'quote-two@example.test',
        ]);
        $relatedSupplier = yovel_admin_buying_save_supplier($db, $related, $relatedAdmin, [
            'supplier_name' => 'Related Quotation Supplier', 'supplier_type' => 'COMPANY', 'email' => 'related-quote@example.test',
        ]);
        $rfq = yovel_admin_buying_save_rfq($db, $owner, $ownerAdmin, [
            'transaction_date' => '2026-08-01',
            'schedule_date' => '2026-09-15',
            'subject' => 'Supplier quotation source RFQ',
            'suppliers' => [
                ['supplier_key' => $supplierOne['supplier_key'], 'email' => 'quote-one@example.test', 'delivery_channel' => 'EMAIL'],
                ['supplier_key' => $supplierTwo['supplier_key'], 'email' => 'quote-two@example.test', 'delivery_channel' => 'EMAIL'],
            ],
            'items' => [[
                'inventory_item_key' => $itemKey,
                'uom_key' => $uomKey,
                'quantity' => '5',
                'schedule_date' => '2026-09-15',
            ]],
        ]);
        $rfq = yovel_admin_buying_transition_rfq($db, $owner, $ownerAdmin, (string) $rfq['rfq_key'], 'SUBMIT', ['expected_version' => 1]);
        $mapped = yovel_admin_buying_map_rfq_to_supplier_quotation($owner, $rfq, (string) $supplierOne['supplier_key']);
        buying_test_assert((string) $mapped['rfq_key'] === (string) $rfq['rfq_key'] && (string) $mapped['items'][0]['rfq_item_key'] === (string) $rfq['items'][0]['rfq_item_key'], 'RFQ mapping lost stable header/item identity.');
        buying_test_assert((string) $mapped['supplier_key'] === (string) $supplierOne['supplier_key'] && (string) $mapped['items'][0]['quantity'] === '5.000000', 'RFQ mapping changed supplier or quantity.');
        buying_test_expect_error(static fn () => yovel_admin_buying_map_rfq_to_supplier_quotation($owner, $rfq, (string) $relatedSupplier['supplier_key']), 'supplier');
        $draftRfq = $rfq;
        $draftRfq['document_status'] = 'DRAFT';
        buying_test_expect_error(static fn () => yovel_admin_buying_map_rfq_to_supplier_quotation($owner, $draftRfq, (string) $supplierOne['supplier_key']), 'submitted');

        $baseInput = static function (array $overrides = []) use ($mapped): array {
            $base = array_replace($mapped, [
                'supplier_reference' => 'SUP-REF-001',
                'transaction_date' => '2026-08-02',
                'valid_until' => '2026-09-30',
                'currency' => 'USD',
                'conversion_rate' => '56.25000000',
            ]);
            $base['items'] = array_replace_recursive($mapped['items'], [[
                'rate' => '100.000000',
                'discount_percentage' => '10.0000',
                'expected_delivery_date' => '2026-09-10',
            ]]);
            return array_replace_recursive($base, $overrides);
        };

        foreach ([
            ['override' => ['currency' => 'US$'], 'error' => 'currency'],
            ['override' => ['conversion_rate' => '0'], 'error' => 'conversion'],
            ['override' => ['valid_until' => '2026-07-31'], 'error' => 'valid'],
            ['override' => ['items' => [['quantity' => '0']]], 'error' => 'zero'],
            ['override' => ['items' => [['rfq_item_key' => bx_uuid()]]], 'error' => 'rfq item'],
            ['override' => ['supplier_key' => $relatedSupplier['supplier_key']], 'error' => 'supplier'],
        ] as $case) {
            buying_test_expect_error(static fn () => yovel_admin_buying_save_supplier_quotation($db, $owner, $ownerAdmin, $baseInput($case['override'])), $case['error']);
        }
        buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_supplier_quotation WHERE company_key_hash = ?', [$owner['company_key_hash']]) === 0, 'Invalid Supplier Quotation input wrote a header.');

        $GLOBALS['yovel_admin_buying_sourcing_dependency_overrides']['finance_purchase_calculation'] = static function (): array {
            throw new RuntimeException('Injected Finance calculation failure.');
        };
        buying_test_expect_error(static fn () => yovel_admin_buying_save_supplier_quotation($db, $owner, $ownerAdmin, $baseInput()), 'injected finance');
        $GLOBALS['yovel_admin_buying_sourcing_dependency_overrides']['finance_purchase_calculation'] = $financeProvider;
        buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_supplier_quotation WHERE company_key_hash = ?', [$owner['company_key_hash']]) === 0, 'Finance calculation failure wrote a Supplier Quotation header.');

        $saved = yovel_admin_buying_save_supplier_quotation($db, $owner, $ownerAdmin, $baseInput());
        $quotationKey = (string) $saved['supplier_quotation_key'];
        buying_test_assert((string) $saved['document_status'] === 'DRAFT' && (int) $saved['row_version'] === 1, 'Supplier Quotation draft status/version read-back failed.');
        buying_test_assert((string) $saved['net_total'] === '450.000000' && (string) $saved['tax_total'] === '54.000000' && (string) $saved['grand_total'] === '504.000000', 'Finance totals were not persisted exactly.');
        buying_test_assert((string) $saved['items'][0]['net_amount'] === '450.000000' && (string) $saved['items'][0]['tax_amount'] === '54.000000', 'Finance line calculation was not read back exactly.');
        buying_test_assert(count($financeCalls) === 1 && (string) $financeCalls[0]['currency'] === 'USD', 'Supplier Quotation did not use the Finance calculation owner service exactly once; calls=' . count($financeCalls) . '.');
        buying_test_assert(yovel_admin_buying_supplier_quotation($owner, $quotationKey) === $saved, 'Supplier Quotation public read changed exact values.');
        buying_test_assert(yovel_admin_buying_supplier_quotation($related, $quotationKey) === null, 'Supplier Quotation read leaked across companies.');
        buying_test_expect_error(static fn () => yovel_admin_buying_save_supplier_quotation($db, $related, $relatedAdmin, $baseInput()), 'supplier');
        buying_test_expect_error(static fn () => yovel_admin_buying_save_supplier_quotation($db, $owner, $ownerAdmin, $baseInput(['supplier_quotation_key' => '', 'supplier_reference' => 'SUP-REF-001'])), 'reference');

        $updated = yovel_admin_buying_save_supplier_quotation($db, $owner, $ownerAdmin, $baseInput([
            'supplier_quotation_key' => $quotationKey,
            'expected_version' => 1,
            'supplier_reference' => 'SUP-REF-001',
            'items' => [['supplier_quotation_item_key' => $saved['items'][0]['supplier_quotation_item_key'], 'rate' => '110.000000']],
        ]));
        buying_test_assert((int) $updated['row_version'] === 2 && (string) $updated['items'][0]['supplier_quotation_item_key'] === (string) $saved['items'][0]['supplier_quotation_item_key'], 'Supplier Quotation update lost version or stable child identity.');
        buying_test_assert((string) $updated['grand_total'] === '554.400000', 'Supplier Quotation update did not persist recalculated totals.');
        buying_test_expect_error(static fn () => yovel_admin_buying_save_supplier_quotation($db, $owner, $ownerAdmin, $baseInput(['supplier_quotation_key' => $quotationKey, 'expected_version' => 1])), 'stale');

        $beforeRollback = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_supplier_quotation WHERE company_key_hash = ?', [$owner['company_key_hash']]);
        $GLOBALS['yovel_admin_buying_sourcing_write_hook'] = static function (string $point): void {
            if ($point === 'supplier-quotation-after-item-write') {
                throw new RuntimeException('Injected Supplier Quotation child failure.');
            }
        };
        buying_test_expect_error(static fn () => yovel_admin_buying_save_supplier_quotation($db, $owner, $ownerAdmin, $baseInput(['supplier_reference' => 'ROLLBACK-REF'])), 'injected');
        unset($GLOBALS['yovel_admin_buying_sourcing_write_hook']);
        buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_supplier_quotation WHERE company_key_hash = ?', [$owner['company_key_hash']]) === $beforeRollback, 'Supplier Quotation child failure did not roll back its header.');

        $submitRollback = yovel_admin_buying_save_supplier_quotation($db, $owner, $ownerAdmin, $baseInput([
            'supplier_key' => $supplierTwo['supplier_key'],
            'supplier_reference' => 'SUBMIT-ROLLBACK-REF',
        ]));
        $GLOBALS['yovel_admin_buying_sourcing_write_hook'] = static function (string $point): void {
            if ($point === 'supplier-quotation-after-rfq-receipt') {
                throw new RuntimeException('Injected Supplier Quotation receipt projection failure.');
            }
        };
        buying_test_expect_error(
            static fn () => yovel_admin_buying_transition_supplier_quotation($db, $owner, $ownerAdmin, (string) $submitRollback['supplier_quotation_key'], 'SUBMIT', ['expected_version' => 1]),
            'injected'
        );
        unset($GLOBALS['yovel_admin_buying_sourcing_write_hook']);
        buying_test_assert((string) yovel_admin_buying_supplier_quotation($owner, (string) $submitRollback['supplier_quotation_key'])['document_status'] === 'DRAFT', 'Failed quotation submission did not roll back its header state.');
        $rfqAfterRollback = yovel_admin_buying_rfq($owner, (string) $rfq['rfq_key']);
        $rollbackRecipients = array_column($rfqAfterRollback['suppliers'], 'delivery_status', 'supplier_key');
        buying_test_assert(($rollbackRecipients[(string) $supplierTwo['supplier_key']] ?? '') === 'PENDING', 'Failed quotation submission did not roll back RFQ receipt projection.');

        $submitted = yovel_admin_buying_transition_supplier_quotation($db, $owner, $ownerAdmin, $quotationKey, 'SUBMIT', ['expected_version' => 2]);
        buying_test_assert((string) $submitted['document_status'] === 'SUBMITTED' && (int) $submitted['row_version'] === 3, 'Supplier Quotation did not submit exactly.');
        $rfqAfterSubmit = yovel_admin_buying_rfq($owner, (string) $rfq['rfq_key']);
        $rfqRecipients = array_column($rfqAfterSubmit['suppliers'], 'delivery_status', 'supplier_key');
        buying_test_assert(($rfqRecipients[(string) $supplierOne['supplier_key']] ?? '') === 'RECEIVED', 'Quotation submission did not atomically project RFQ receipt.');
        buying_test_expect_error(static fn () => yovel_admin_buying_save_supplier_quotation($db, $owner, $ownerAdmin, $baseInput(['supplier_quotation_key' => $quotationKey, 'expected_version' => 3])), 'submitted');
        buying_test_expect_error(static fn () => yovel_admin_buying_transition_supplier_quotation($db, $related, $relatedAdmin, $quotationKey, 'STOP', ['expected_version' => 3]), 'not found');

        $comparison = yovel_admin_buying_supplier_quotation_comparison_inputs($owner, (string) $rfq['rfq_key']);
        buying_test_assert(count($comparison['quotations']) === 1 && (string) $comparison['quotations'][0]['supplier_quotation_key'] === $quotationKey, 'Quotation comparison omitted the submitted quote.');
        buying_test_assert((string) $comparison['items'][0]['rfq_item_key'] === (string) $rfq['items'][0]['rfq_item_key'] && (string) $comparison['items'][0]['quotations'][0]['grand_amount'] === '554.400000', 'Quotation comparison inputs lost exact RFQ/item totals.');

        $stopped = yovel_admin_buying_transition_supplier_quotation($db, $owner, $ownerAdmin, $quotationKey, 'STOP', ['expected_version' => 3]);
        buying_test_assert((string) $stopped['document_status'] === 'STOPPED', 'Supplier Quotation did not stop.');
        $resumed = yovel_admin_buying_transition_supplier_quotation($db, $owner, $ownerAdmin, $quotationKey, 'RESUME', ['expected_version' => 4]);
        buying_test_assert((string) $resumed['document_status'] === 'SUBMITTED', 'Supplier Quotation did not resume.');
        $cancelled = yovel_admin_buying_transition_supplier_quotation($db, $owner, $ownerAdmin, $quotationKey, 'CANCEL', ['expected_version' => 5]);
        buying_test_assert((string) $cancelled['document_status'] === 'CANCELLED', 'Supplier Quotation did not cancel.');
        $amended = yovel_admin_buying_transition_supplier_quotation($db, $owner, $ownerAdmin, $quotationKey, 'AMEND', ['expected_version' => 6]);
        buying_test_assert((string) $amended['document_status'] === 'DRAFT' && (string) $amended['amended_from_key'] === $quotationKey && (string) $amended['supplier_quotation_key'] !== $quotationKey, 'Supplier Quotation amendment identity failed.');
        buying_test_assert((string) $amended['items'][0]['rfq_item_key'] === (string) $rfq['items'][0]['rfq_item_key'], 'Supplier Quotation amendment lost RFQ mapping.');

        $expiring = yovel_admin_buying_save_supplier_quotation($db, $owner, $ownerAdmin, $baseInput([
            'supplier_key' => $supplierTwo['supplier_key'],
            'supplier_reference' => 'EXPIRING-REF',
            'transaction_date' => '2026-08-01',
            'valid_until' => '2026-08-10',
        ]));
        $expiring = yovel_admin_buying_transition_supplier_quotation($db, $owner, $ownerAdmin, (string) $expiring['supplier_quotation_key'], 'SUBMIT', ['expected_version' => 1]);
        buying_test_expect_error(static fn () => yovel_admin_buying_transition_supplier_quotation($db, $owner, $ownerAdmin, (string) $expiring['supplier_quotation_key'], 'EXPIRE', ['expected_version' => 2, 'as_of' => '2026-08-09']), 'valid');
        $expired = yovel_admin_buying_transition_supplier_quotation($db, $owner, $ownerAdmin, (string) $expiring['supplier_quotation_key'], 'EXPIRE', ['expected_version' => 2, 'as_of' => '2026-08-11']);
        buying_test_assert((string) $expired['document_status'] === 'EXPIRED', 'Supplier Quotation expiry transition failed.');

        $audits = $db->GetAll("SELECT action, new_values FROM builder_audit_log WHERE module = 'project_company_buying_supplier_quotation' AND record_key IN (?, ?) ORDER BY x_id", [$quotationKey, (string) $amended['supplier_quotation_key']]);
        $auditText = json_encode($audits, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        buying_test_assert(str_contains($auditText, (string) $ownerAdmin['admin_key']) && str_contains($auditText, (string) $owner['company_key_hash']), 'Supplier Quotation audit lost actor/company scope.');
        foreach (['CREATE', 'UPDATE', 'SUBMIT', 'STOP', 'RESUME', 'CANCEL', 'AMEND'] as $action) {
            buying_test_assert(in_array($action, array_column($audits, 'action'), true), 'Supplier Quotation audit omitted ' . $action . '.');
        }

        $data = yovel_admin_buying_procurement_data($owner, $ownerAdmin);
        buying_test_assert(count($data['supplierQuotations'] ?? []) >= 3, 'Buying data provider omitted Supplier Quotations.');
        $activeModuleSection = 'supplier-quotations';
        $activeModuleMeta = yovel_admin_buying_procurement_sections()[$activeModuleSection];
        $activeModuleData = $data;
        $activeModuleFormState = [
            'section' => 'supplier-quotations',
            'action' => 'save_buying_supplier_quotation',
            'input' => [
                'supplier_reference' => 'REHYDRATED-REF',
                'transaction_date' => '2026-08-25',
                'valid_until' => '2026-09-30',
                'currency' => 'USD',
                'conversion_rate' => '56.25',
                'items_text' => $itemKey . ' | BOX | 2 | 95 | 5 | 2026-09-10 | | ' . $rfq['rfq_key'] . ' | ' . $rfq['items'][0]['rfq_item_key'],
            ],
            'error' => 'Quotation validation failed <script>alert(1)</script>.',
        ];
        $companyName = (string) $owner['company_name'];
        ob_start();
        require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
        $markup = (string) ob_get_clean();
        foreach ([
            'data-buying-supplier-quotations', 'data-buying-supplier-quotation-open', 'buying-supplier-quotation-modal',
            'data-record-modal', 'data-confirm-submit', 'name="module_view" value="buying-procurement"',
            'name="action" value="save_buying_supplier_quotation"', 'data-record-modal-open-on-load',
            'REHYDRATED-REF', '&lt;script&gt;alert(1)&lt;/script&gt;', 'Submit Quotation', 'Stop Quotation',
            'Resume Quotation', 'Cancel Quotation', 'Amend Quotation', 'Expire Quotation', 'Compare Quotations',
        ] as $marker) {
            buying_test_assert(str_contains($markup, $marker), 'Supplier Quotation workspace is missing marker: ' . $marker);
        }
        buying_test_assert(!str_contains($markup, '<script>alert(1)</script>'), 'Supplier Quotation server error was not escaped.');

        $source = (string) file_get_contents($root . '/company/admin/modules/buying-procurement/sourcing.php');
        buying_test_assert(preg_match('/project_company_(inventory|finance|operations)_/i', $source) !== 1, 'Supplier Quotation source contains foreign-owner SQL.');
        buying_test_assert(str_contains($source, 'yovel_admin_finance_calculate_purchase_document'), 'Supplier Quotation gateway omits the Finance owner callable.');
        buying_test_assert(substr_count($source, 'yovel_admin_buying_in_transaction(') >= 4 && str_contains($source, 'FOR UPDATE'), 'Supplier Quotation mutations do not own locked transactions.');
    } finally {
        unset($GLOBALS['yovel_admin_buying_sourcing_dependency_overrides'], $GLOBALS['yovel_admin_buying_sourcing_write_hook']);
        foreach ([$ownerFixture, $relatedFixture] as $fixture) {
            $hash = (string) $fixture['company']['company_key_hash'];
            buying_test_execute($db, "DELETE FROM builder_audit_log WHERE module IN ('project_company_buying_supplier_quotation','project_company_buying_rfq') AND new_values LIKE ?", ['%' . $hash . '%'], 'Temporary sourcing audit cleanup');
            buying_test_execute($db, 'DELETE FROM project_company_buying_supplier_quotation_item WHERE company_key_hash = ?', [$hash], 'Temporary Supplier Quotation item cleanup');
            buying_test_execute($db, 'DELETE FROM project_company_buying_supplier_quotation WHERE company_key_hash = ?', [$hash], 'Temporary Supplier Quotation cleanup');
            buying_test_execute($db, 'DELETE FROM project_company_buying_rfq_item WHERE company_key_hash = ?', [$hash], 'Temporary RFQ item cleanup');
            buying_test_execute($db, 'DELETE FROM project_company_buying_rfq_supplier WHERE company_key_hash = ?', [$hash], 'Temporary RFQ supplier cleanup');
            buying_test_execute($db, 'DELETE FROM project_company_buying_rfq WHERE company_key_hash = ?', [$hash], 'Temporary RFQ cleanup');
        }
        buying_test_cleanup_temporary_company($db, $ownerFixture);
        buying_test_cleanup_temporary_company($db, $relatedFixture);
    }

    echo "Buying / Procurement supplier quotation checks passed.\n";
}
