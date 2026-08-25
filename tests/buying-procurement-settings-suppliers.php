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

foreach (['settings.php', 'suppliers.php', 'functions.php'] as $moduleFile) {
    $path = $root . '/company/admin/modules/buying-procurement/' . $moduleFile;
    if (is_file($path)) {
        require_once $path;
    }
}

foreach ([
    'yovel_admin_buying_settings',
    'yovel_admin_buying_save_settings',
    'yovel_admin_buying_supplier',
    'yovel_admin_buying_save_supplier',
    'yovel_admin_buying_set_supplier_status',
] as $service) {
    buying_test_assert(function_exists($service), 'Missing Task 3 Buying service: ' . $service);
}

yovel_admin_buying_schema();
$db = bx_db();
$ownerFixture = buying_test_temporary_company($db, 'Buying Owner');
$relatedFixture = buying_test_temporary_company($db, 'Buying Related');
$owner = $ownerFixture['company'];
$ownerAdmin = $ownerFixture['admin'];
$related = $relatedFixture['company'];
$relatedAdmin = $relatedFixture['admin'];
$suffix = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$supplierKey = '';
$relatedSupplierKey = '';
$rollbackCode = 'ROLLBACK_' . $suffix;

try {
    $defaults = yovel_admin_buying_settings($owner);
    buying_test_assert(($defaults['buying_setting_key'] ?? null) === null, 'Unsaved Buying Settings should expose defaults without a fake key.');
    buying_test_assert(($defaults['supplier_naming_mode'] ?? '') === 'NAMING_SERIES', 'Buying Settings default naming mode changed.');
    buying_test_assert(($defaults['finance_defaults_available'] ?? true) === false, 'Unavailable Finance defaults were reported as available.');

    buying_test_expect_error(
        static fn () => yovel_admin_buying_save_settings($db, $owner, $ownerAdmin, [
            'supplier_naming_mode' => 'INVALID',
            'over_order_allowance' => '101',
        ]),
        'naming'
    );
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_setting WHERE company_key_hash = ?', [$owner['company_key_hash']]) === 0, 'Invalid settings mutated the database.');

    $groupKey = bx_uuid();
    $priceListKey = bx_uuid();
    $createdSettings = yovel_admin_buying_save_settings($db, $owner, $ownerAdmin, [
        'supplier_naming_mode' => 'NAMING_SERIES',
        'default_supplier_group_key' => $groupKey,
        'default_buying_price_list_key' => $priceListKey,
        'purchase_order_required' => '1',
        'purchase_receipt_required' => '1',
        'maintain_same_rate' => '1',
        'maintain_same_rate_action' => 'WARN',
        'over_order_allowance' => '5.2500',
        'over_transfer_allowance' => '3.5000',
        'allow_zero_qty_purchase_order' => '1',
        'allow_zero_qty_rfq' => '1',
        'allow_zero_qty_supplier_quotation' => '0',
    ]);
    $settingsKey = (string) ($createdSettings['buying_setting_key'] ?? '');
    buying_test_assert(yovel_admin_is_uuid($settingsKey), 'Buying Settings create did not return a stable key.');
    buying_test_assert((string) $createdSettings['default_supplier_group_key'] === $groupKey, 'Buying Settings lost the supplier-group key.');
    buying_test_assert((string) $createdSettings['default_buying_price_list_key'] === $priceListKey, 'Buying Settings lost the price-list key.');
    buying_test_assert((int) $createdSettings['purchase_order_required'] === 1 && (int) $createdSettings['purchase_receipt_required'] === 1, 'Buying Settings lost document requirements.');
    buying_test_assert((string) $createdSettings['over_order_allowance'] === '5.2500' && (string) $createdSettings['over_transfer_allowance'] === '3.5000', 'Buying Settings allowances were not read back exactly.');
    buying_test_assert((int) $createdSettings['allow_zero_qty_purchase_order'] === 1 && (int) $createdSettings['allow_zero_qty_rfq'] === 1, 'Buying Settings lost zero-quantity controls.');

    $updatedSettings = yovel_admin_buying_save_settings($db, $owner, $ownerAdmin, [
        'buying_setting_key' => $settingsKey,
        'supplier_naming_mode' => 'SUPPLIER_NAME',
        'maintain_same_rate_action' => 'STOP',
        'over_order_allowance' => '0',
        'over_transfer_allowance' => '1.125',
        'allow_zero_qty_supplier_quotation' => '1',
    ]);
    buying_test_assert((string) $updatedSettings['buying_setting_key'] === $settingsKey, 'Buying Settings update changed the stable key.');
    buying_test_assert((string) $updatedSettings['supplier_naming_mode'] === 'SUPPLIER_NAME', 'Buying Settings update did not persist naming mode.');
    buying_test_assert((int) $updatedSettings['purchase_order_required'] === 0, 'Unchecked settings were not persisted as false.');
    buying_test_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_buying_setting' AND record_key = ? AND action IN ('CREATE','UPDATE')", [$settingsKey]) === 2, 'Buying Settings create/update audits were not committed.');
    $settingsAudit = $db->GetOne("SELECT new_values FROM builder_audit_log WHERE module = 'project_company_buying_setting' AND record_key = ? ORDER BY x_id DESC LIMIT 1", [$settingsKey]);
    buying_test_assert(str_contains((string) $settingsAudit, (string) $ownerAdmin['admin_key']) && str_contains((string) $settingsAudit, (string) $owner['company_key_hash']), 'Buying Settings audit lost actor or company scope.');

    $duplicateCode = 'DUPLICATE_' . $suffix;
    buying_test_expect_error(
        static fn () => yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
            'supplier_code' => $duplicateCode,
            'supplier_name' => 'Duplicate Child Supplier',
            'supplier_type' => 'COMPANY',
            'customer_numbers' => [
                ['related_company_key' => $owner['company_key'], 'customer_number' => 'CUST-ONE'],
                ['related_company_key' => $owner['company_key'], 'customer_number' => 'CUST-TWO'],
            ],
        ]),
        'duplicate company'
    );
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_supplier WHERE company_key_hash = ? AND supplier_code = ?', [$owner['company_key_hash'], $duplicateCode]) === 0, 'Duplicate customer-number validation wrote a supplier.');

    $createdSupplier = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_name' => 'Task Three Internal Supplier',
        'supplier_type' => 'COMPANY',
        'supplier_group_key' => bx_uuid(),
        'country_key' => bx_uuid(),
        'default_currency' => 'PHP',
        'default_price_list_key' => bx_uuid(),
        'payment_terms_key' => bx_uuid(),
        'tax_id' => 'TAX-' . $suffix,
        'language_code' => 'en',
        'email' => 'buying-' . strtolower($suffix) . '@example.test',
        'phone' => '+63 555 0100',
        'website' => 'https://supplier.example.test',
        'supplier_details' => 'Task 3 create path.',
        'is_transporter' => '1',
        'is_internal_supplier' => '1',
        'represents_company_key' => $related['company_key'],
        'allowed_company_keys' => [$owner['company_key']],
        'warn_rfqs' => '0',
        'prevent_rfqs' => '1',
        'warn_purchase_orders' => '1',
        'prevent_purchase_orders' => '0',
        'customer_numbers' => [
            ['related_company_key' => $owner['company_key'], 'customer_number' => 'OWNER-' . $suffix],
            ['related_company_key' => $related['company_key'], 'customer_number' => 'RELATED-' . $suffix],
        ],
    ]);
    $supplierKey = (string) ($createdSupplier['supplier_key'] ?? '');
    buying_test_assert(yovel_admin_is_uuid($supplierKey), 'Supplier create did not return a stable key.');
    buying_test_assert((string) $createdSupplier['supplier_code'] === yovel_admin_code('Task Three Internal Supplier'), 'SUPPLIER_NAME mode did not normalize the supplier code from its name.');
    buying_test_assert((string) $createdSupplier['default_currency'] === 'PHP' && (string) $createdSupplier['payment_terms_key'] !== '', 'Supplier commercial defaults were not read back.');
    buying_test_assert((int) $createdSupplier['is_internal_supplier'] === 1 && (string) $createdSupplier['represents_company_key'] === $related['company_key'], 'Internal-supplier company rules were not persisted.');
    buying_test_assert((int) $createdSupplier['prevent_rfqs'] === 1 && (int) $createdSupplier['warn_rfqs'] === 1, 'RFQ prevent did not preserve the warning control.');
    buying_test_assert(count($createdSupplier['customer_numbers'] ?? []) === 2, 'Supplier customer numbers were not replaced/read back exactly.');
    buying_test_assert(count($createdSupplier['allowed_companies'] ?? []) === 1, 'Internal supplier allowed-company rows were not read back.');

    $createdSupplierCode = (string) $createdSupplier['supplier_code'];
    $updatedSupplier = yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
        'supplier_key' => $supplierKey,
        'supplier_code' => 'IGNORED-RENAME',
        'supplier_name' => 'Task Three Supplier Updated',
        'supplier_type' => 'PARTNERSHIP',
        'default_currency' => 'USD',
        'payment_terms_key' => bx_uuid(),
        'supplier_details' => 'Task 3 update path.',
        'warn_rfqs' => '1',
        'warn_purchase_orders' => '0',
        'prevent_purchase_orders' => '1',
        'customer_numbers' => [
            ['related_company_key' => $owner['company_key'], 'customer_number' => 'UPDATED-' . $suffix],
        ],
    ]);
    buying_test_assert((string) $updatedSupplier['supplier_key'] === $supplierKey, 'Supplier update changed the stable key.');
    buying_test_assert((string) $updatedSupplier['supplier_code'] === $createdSupplierCode, 'Supplier update changed its stable business code.');
    buying_test_assert((string) $updatedSupplier['supplier_name'] === 'Task Three Supplier Updated' && (string) $updatedSupplier['supplier_type'] === 'PARTNERSHIP', 'Supplier update did not read back identity fields.');
    buying_test_assert(count($updatedSupplier['customer_numbers'] ?? []) === 1 && (string) $updatedSupplier['customer_numbers'][0]['customer_number'] === 'UPDATED-' . $suffix, 'Supplier child replacement was not exact.');
    buying_test_assert((int) $updatedSupplier['is_internal_supplier'] === 0 && ($updatedSupplier['allowed_companies'] ?? []) === [], 'Disabling internal supplier did not clear company rows.');

    $relatedSettings = yovel_admin_buying_save_settings($db, $related, $relatedAdmin, [
        'supplier_naming_mode' => 'AUTO_NAME',
        'maintain_same_rate_action' => 'STOP',
    ]);
    buying_test_assert(yovel_admin_is_uuid((string) $relatedSettings['buying_setting_key']), 'Related-company settings fixture was not created.');
    $relatedSupplier = yovel_admin_buying_save_supplier($db, $related, $relatedAdmin, [
        'supplier_code' => $createdSupplierCode,
        'supplier_name' => 'Isolated Related Supplier',
        'supplier_type' => 'INDIVIDUAL',
        'default_currency' => 'PHP',
    ]);
    $relatedSupplierKey = (string) $relatedSupplier['supplier_key'];
    buying_test_assert((string) $relatedSupplier['supplier_code'] === $createdSupplierCode, 'Same supplier code was not allowed in another company.');
    buying_test_assert(yovel_admin_buying_supplier($owner, $relatedSupplierKey) === null, 'Cross-company supplier read leaked a record.');

    $GLOBALS['yovel_admin_buying_child_write_hook'] = static function (string $childType, int $index): void {
        if ($childType === 'customer_number' && $index === 1) {
            throw new RuntimeException('Injected Buying child-row failure.');
        }
    };
    buying_test_expect_error(
        static fn () => yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
            'supplier_code' => $rollbackCode,
            'supplier_name' => 'Rollback Supplier',
            'supplier_type' => 'COMPANY',
            'customer_numbers' => [
                ['related_company_key' => $owner['company_key'], 'customer_number' => 'ROLLBACK-ONE'],
                ['related_company_key' => $related['company_key'], 'customer_number' => 'ROLLBACK-TWO'],
            ],
        ]),
        'injected'
    );
    unset($GLOBALS['yovel_admin_buying_child_write_hook']);
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_supplier WHERE company_key_hash = ? AND supplier_code = ?', [$owner['company_key_hash'], $rollbackCode]) === 0, 'Injected child failure did not roll back the supplier parent.');
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_supplier_customer_number WHERE company_key_hash = ? AND customer_number LIKE ?', [$owner['company_key_hash'], 'ROLLBACK-%']) === 0, 'Injected child failure did not roll back child rows.');
    buying_test_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_buying_supplier' AND new_values LIKE ?", ['%' . $rollbackCode . '%']) === 0, 'Injected child failure committed an audit event.');

    $holdInvoices = yovel_admin_buying_set_supplier_status($db, $owner, $ownerAdmin, $supplierKey, 'HOLD', [
        'hold_type' => 'INVOICES',
        'release_date' => '2026-12-31',
    ]);
    buying_test_assert((int) $holdInvoices['on_hold'] === 1 && (string) $holdInvoices['hold_type'] === 'INVOICES' && (string) $holdInvoices['release_date'] === '2026-12-31', 'Invoice hold did not persist exactly.');
    $released = yovel_admin_buying_set_supplier_status($db, $owner, $ownerAdmin, $supplierKey, 'RELEASE', []);
    buying_test_assert((int) $released['on_hold'] === 0 && $released['hold_type'] === null && $released['release_date'] === null, 'Supplier release did not clear hold fields.');
    $holdPayments = yovel_admin_buying_set_supplier_status($db, $owner, $ownerAdmin, $supplierKey, 'HOLD', ['hold_type' => 'PAYMENTS']);
    buying_test_assert((string) $holdPayments['hold_type'] === 'PAYMENTS', 'Payment hold was not supported.');
    yovel_admin_buying_set_supplier_status($db, $owner, $ownerAdmin, $supplierKey, 'RELEASE', []);

    buying_test_expect_error(
        static fn () => yovel_admin_buying_set_supplier_status($db, $related, $relatedAdmin, $supplierKey, 'DISABLE', []),
        'not found'
    );
    buying_test_assert((string) $db->GetOne('SELECT supplier_status FROM project_company_buying_supplier WHERE supplier_key = ?', [$supplierKey]) === 'ACTIVE', 'Cross-company lifecycle action changed the supplier.');

    $disabled = yovel_admin_buying_set_supplier_status($db, $owner, $ownerAdmin, $supplierKey, 'DISABLE', []);
    buying_test_assert((string) $disabled['supplier_status'] === 'INACTIVE' && (int) $disabled['on_hold'] === 0, 'Supplier disable did not persist exactly.');
    $archived = yovel_admin_buying_set_supplier_status($db, $owner, $ownerAdmin, $supplierKey, 'ARCHIVE', []);
    buying_test_assert((string) $archived['supplier_status'] === 'ARCHIVED', 'Supplier archive did not persist exactly.');
    buying_test_expect_error(
        static fn () => yovel_admin_buying_save_supplier($db, $owner, $ownerAdmin, [
            'supplier_key' => $supplierKey,
            'supplier_name' => 'Archived Supplier Rewrite',
            'supplier_type' => 'COMPANY',
        ]),
        'archived'
    );
    buying_test_assert((string) $db->GetOne('SELECT supplier_name FROM project_company_buying_supplier WHERE supplier_key = ?', [$supplierKey]) === 'Task Three Supplier Updated', 'Archived supplier edit changed persisted identity.');
    buying_test_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_buying_supplier' AND record_key = ? AND action IN ('CREATE','UPDATE','HOLD','RELEASE','DISABLE','ARCHIVE')", [$supplierKey]) === 8, 'Supplier mutation audits are incomplete.');

    $unauthorizedCode = 'UNAUTHORIZED_' . $suffix;
    buying_test_expect_error(
        static fn () => yovel_admin_buying_save_supplier($db, $owner, ['admin_key' => bx_uuid()], [
            'supplier_code' => $unauthorizedCode,
            'supplier_name' => 'Unauthorized Supplier',
            'supplier_type' => 'COMPANY',
        ]),
        'authorized'
    );
    buying_test_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_buying_supplier WHERE company_key_hash = ? AND supplier_code = ?', [$owner['company_key_hash'], $unauthorizedCode]) === 0, 'Unauthorized supplier mutation wrote data.');

    $handlerResult = yovel_admin_buying_procurement_handle_post($owner, $ownerAdmin, 'save_buying_settings', [
        'module_view' => 'buying-procurement',
        'section' => 'buying-settings',
        'buying_setting_key' => $settingsKey,
        'supplier_naming_mode' => 'SUPPLIER_NAME',
        'maintain_same_rate_action' => 'STOP',
    ]);
    buying_test_assert($handlerResult === ['message' => 'Buying Settings saved.', 'section' => 'buying-settings', 'query' => []], 'Settings POST handler broke the shared result contract.');

    $data = yovel_admin_buying_procurement_data($owner, $ownerAdmin);
    buying_test_assert(($data['settings']['buying_setting_key'] ?? '') === $settingsKey, 'Workspace settings are not server-backed.');
    buying_test_assert(count($data['suppliers'] ?? []) >= 1, 'Workspace supplier rows are not server-backed.');

    $sections = yovel_admin_buying_procurement_sections();
    $activeModuleSection = 'suppliers';
    $activeModuleMeta = $sections['suppliers'];
    $activeModuleData = $data;
    $companyName = (string) $owner['company_name'];
    $activeModuleFormState = [];
    ob_start();
    require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
    $supplierMarkup = (string) ob_get_clean();
    foreach (['Add Supplier', 'Edit supplier', 'Hold supplier', 'Release supplier', 'Disable supplier', 'data-record-modal', 'data-confirm-submit'] as $marker) {
        buying_test_assert(str_contains($supplierMarkup, $marker), 'Supplier governance workspace is missing: ' . $marker);
    }

    $activeModuleSection = 'buying-settings';
    $activeModuleMeta = $sections['buying-settings'];
    ob_start();
    require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
    $settingsMarkup = (string) ob_get_clean();
    buying_test_assert(str_contains($settingsMarkup, 'Edit Buying Settings'), 'Buying Settings modal trigger is missing.');
    buying_test_assert(str_contains($settingsMarkup, 'Finance defaults unavailable'), 'Buying Settings does not expose the Finance dependency state.');

    $activeModuleSection = 'suppliers';
    $activeModuleMeta = $sections['suppliers'];
    $activeModuleFormState = [
        'section' => 'suppliers',
        'action' => 'save_buying_supplier',
        'input' => [
            'supplier_name' => 'Retained <script>alert(1)</script>',
            'supplier_type' => 'PARTNERSHIP',
            'customer_numbers_text' => $owner['company_key'] . ' | KEEP-123',
        ],
        'error' => 'Supplier validation failed <script>alert(2)</script>.',
    ];
    ob_start();
    require $root . '/company/admin/modules/buying-procurement/views/workspace.php';
    $rehydratedMarkup = (string) ob_get_clean();
    buying_test_assert(str_contains($rehydratedMarkup, 'id="buying-supplier-modal"') && str_contains($rehydratedMarkup, 'data-record-modal-open-on-load'), 'Supplier validation did not reopen the owning modal.');
    buying_test_assert(str_contains($rehydratedMarkup, 'Retained &lt;script&gt;alert(1)&lt;/script&gt;'), 'Supplier validation did not safely retain values.');
    buying_test_assert(!str_contains($rehydratedMarkup, '<script>alert(2)</script>'), 'Supplier validation error was not escaped.');

    foreach ([
        'yovel_admin_buying_save_settings' => $root . '/company/admin/modules/buying-procurement/settings.php',
        'yovel_admin_buying_save_supplier' => $root . '/company/admin/modules/buying-procurement/suppliers.php',
        'yovel_admin_buying_set_supplier_status' => $root . '/company/admin/modules/buying-procurement/suppliers.php',
    ] as $functionName => $sourcePath) {
        $reflection = new ReflectionFunction($functionName);
        $sourceLines = file($sourcePath);
        $source = implode('', array_slice($sourceLines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
        buying_test_assert(substr_count($source, 'yovel_admin_buying_in_transaction(') === 1, $functionName . ' must own exactly one transaction boundary.');
        buying_test_assert(str_contains($source, 'FOR UPDATE'), $functionName . ' is missing its row lock.');
    }

    $moduleSource = (string) file_get_contents($root . '/company/admin/modules/buying-procurement/functions.php');
    $supplierSource = (string) file_get_contents($root . '/company/admin/modules/buying-procurement/suppliers.php');
    buying_test_assert(!str_contains($moduleSource . $supplierSource, 'project_company_finance_'), 'Buying Task 3 directly accesses a Finance table.');
} finally {
    unset($GLOBALS['yovel_admin_buying_child_write_hook']);
    buying_test_cleanup_temporary_company($db, $ownerFixture);
    buying_test_cleanup_temporary_company($db, $relatedFixture);
}

echo "Buying / Procurement settings and supplier checks passed.\n";
