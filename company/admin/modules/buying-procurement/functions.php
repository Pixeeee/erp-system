<?php
declare(strict_types=1);

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/suppliers.php';
require_once __DIR__ . '/scorecards.php';

function yovel_admin_buying_procurement_sections(): array
{
    return [
        'dashboard' => ['label' => 'Procurement Dashboard', 'icon' => 'dashboard', 'description' => 'Review procurement readiness, document activity, and owner-service dependencies.'],
        'suppliers' => ['label' => 'Suppliers', 'icon' => 'storefront', 'description' => 'Maintain company-scoped supplier masters and procurement controls.'],
        'material-requests' => ['label' => 'Material Requests', 'icon' => 'inventory_2', 'description' => 'Review Inventory-owned demand that is available for sourcing.'],
        'request-for-quotation' => ['label' => 'Requests for Quotation', 'icon' => 'request_quote', 'description' => 'Prepare supplier invitations and trace requested items.'],
        'supplier-quotations' => ['label' => 'Supplier Quotations', 'icon' => 'price_check', 'description' => 'Compare supplier responses, validity, and commercial terms.'],
        'purchase-orders' => ['label' => 'Purchase Orders', 'icon' => 'shopping_cart', 'description' => 'Control committed orders and their receipt and billing progress.'],
        'purchase-receipts' => ['label' => 'Purchase Receipts', 'icon' => 'move_to_inbox', 'description' => 'Review Inventory-owned receipts linked to procurement orders.'],
        'supplier-scorecards' => ['label' => 'Supplier Scorecards', 'icon' => 'score', 'description' => 'Define supplier measures, periods, standings, and purchasing restrictions.'],
        'purchase-analytics' => ['label' => 'Purchase Analytics', 'icon' => 'monitoring', 'description' => 'Analyze procurement value, quantity, suppliers, and item trends.'],
        'reports' => ['label' => 'Procurement Reports', 'icon' => 'summarize', 'description' => 'Run procurement tracking, order, quotation, and subcontract reports.'],
        'buying-settings' => ['label' => 'Buying Settings', 'icon' => 'tune', 'description' => 'Control supplier naming, document requirements, and quantity allowances.'],
        'form-builder' => ['label' => 'Form Builder', 'icon' => 'view_quilt', 'description' => 'Review configurable procurement forms and protected workflow fields.'],
    ];
}
function yovel_admin_buying_procurement_section(): string
{
    $requested = (string) ($_GET['section'] ?? 'suppliers');
    $route = function_exists('yovel_admin_module_route')
        ? yovel_admin_module_route('buying-procurement')
        : null;
    if (is_array($route) && function_exists('yovel_admin_module_section')) {
        return yovel_admin_module_section($route, $requested);
    }

    $section = yovel_admin_slug($requested);
    return array_key_exists($section, yovel_admin_buying_procurement_sections()) ? $section : 'suppliers';
}

function yovel_admin_buying_procurement_dependency_state(): array
{
    $inventoryFunctions = [
        'yovel_admin_inventory_buying_snapshot',
        'yovel_admin_inventory_create_purchase_receipt',
        'yovel_admin_inventory_purchase_receipt',
    ];
    $financeFunctions = [
        'yovel_admin_finance_buying_defaults',
        'yovel_admin_finance_calculate_purchase_document',
        'yovel_admin_finance_create_purchase_invoice_from_buying',
        'yovel_admin_finance_buying_snapshot',
    ];
    $operationsNotificationFunctions = ['yovel_admin_operations_create_notification_handoff'];
    $allAvailable = static fn (array $functions): bool => array_reduce(
        $functions,
        static fn (bool $available, string $function): bool => $available && function_exists($function),
        true
    );

    return [
        'shared' => [
            'available' => function_exists('yovel_admin_module_registry')
                && function_exists('yovel_admin_shared_form_adapter')
                && function_exists('yovel_admin_shared_form_reorder'),
            'label' => 'Shared workspace contracts',
        ],
        'inventory' => [
            'available' => $allAvailable($inventoryFunctions),
            'label' => 'Inventory contract',
            'required_functions' => $inventoryFunctions,
        ],
        'finance' => [
            'available' => $allAvailable($financeFunctions),
            'label' => 'Finance contract',
            'required_functions' => $financeFunctions,
        ],
        'operations_notification' => [
            'available' => $allAvailable($operationsNotificationFunctions),
            'label' => 'Operations notification contract',
            'required_functions' => $operationsNotificationFunctions,
            'blocking' => false,
        ],
    ];
}

function yovel_admin_buying_procurement_data(array $company, ?array $admin = null): array
{
    yovel_admin_buying_schema();
    if (!is_array($admin)) {
        throw new RuntimeException('An authorized company administrator is required for Buying / Procurement.');
    }
    [, $companyKeyHash] = yovel_admin_buying_scope($company, $admin);
    $db = bx_db();

    $counts = [
        'suppliers' => (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_buying_supplier WHERE company_key_hash = ? AND supplier_status <> 'DELETED'",
            [$companyKeyHash]
        ),
        'requests_for_quotation' => (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_buying_rfq WHERE company_key_hash = ? AND document_status <> 'ARCHIVED'",
            [$companyKeyHash]
        ),
        'supplier_quotations' => (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_buying_supplier_quotation WHERE company_key_hash = ? AND document_status <> 'ARCHIVED'",
            [$companyKeyHash]
        ),
        'purchase_orders' => (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_buying_purchase_order WHERE company_key_hash = ? AND document_status <> 'ARCHIVED'",
            [$companyKeyHash]
        ),
        'supplier_scorecards' => (int) $db->GetOne(
            "SELECT COUNT(*) FROM project_company_buying_scorecard_definition WHERE company_key_hash = ? AND scorecard_status <> 'DELETED'",
            [$companyKeyHash]
        ),
    ];
    $suppliers = yovel_admin_buying_suppliers($company);
    $scorecards = yovel_admin_buying_scorecards($company);
    foreach ($scorecards as &$scorecard) {
        $scorecard['restrictions'] = yovel_admin_buying_supplier_restrictions($company, (string) $scorecard['supplier_key']);
    }
    unset($scorecard);
    $settings = yovel_admin_buying_settings($company);
    $adapter = yovel_admin_buying_procurement_form_adapter();
    $schemas = yovel_admin_buying_procurement_default_form_schemas();
    $selectedTarget = trim((string) ($_GET['form_target'] ?? 'supplier'));
    if (!in_array($selectedTarget, $adapter['target_record_types'], true)) {
        $selectedTarget = 'supplier';
    }

    return [
        'state' => 'ready',
        'company_key_hash' => $companyKeyHash,
        'counts' => $counts,
        'suppliers' => $suppliers,
        'scorecards' => $scorecards,
        'settings' => $settings,
        'dependencies' => yovel_admin_buying_procurement_dependency_state(),
        'formAdapter' => $adapter,
        'formSchemas' => $schemas,
        'selectedFormTarget' => $selectedTarget,
    ];
}

function yovel_admin_buying_procurement_handle_post(
    array $company,
    array $admin,
    string $action,
    array $input
): array {
    yovel_admin_buying_scope($company, $admin);
    $moduleView = strtolower(trim((string) ($input['module_view'] ?? 'buying-procurement')));
    if ($moduleView !== 'buying-procurement') {
        throw new InvalidArgumentException('Buying / Procurement module scope is invalid.');
    }

    $action = trim($action);
    if ($action === 'save_buying_settings') {
        yovel_admin_buying_save_settings(bx_db(), $company, $admin, $input);
        return [
            'message' => 'Buying Settings saved.',
            'section' => 'buying-settings',
            'query' => [],
        ];
    }
    if ($action === 'save_buying_supplier') {
        $saved = yovel_admin_buying_save_supplier(bx_db(), $company, $admin, $input);
        return [
            'message' => 'Supplier saved.',
            'section' => 'suppliers',
            'query' => ['supplier' => (string) $saved['supplier_key']],
        ];
    }
    if ($action === 'save_buying_scorecard') {
        $saved = yovel_admin_buying_save_scorecard_definition(bx_db(), $company, $admin, $input);
        return [
            'message' => 'Supplier scorecard saved.',
            'section' => 'supplier-scorecards',
            'query' => ['scorecard' => (string) $saved['scorecard_key']],
        ];
    }
    if ($action === 'calculate_buying_scorecard_period') {
        $scorecardKey = trim((string) ($input['scorecard_key'] ?? ''));
        yovel_admin_buying_calculate_scorecard_period(
            bx_db(),
            $company,
            $admin,
            $scorecardKey,
            trim((string) ($input['period_start'] ?? '')),
            trim((string) ($input['period_end'] ?? ''))
        );
        return [
            'message' => 'Supplier scorecard period calculated.',
            'section' => 'supplier-scorecards',
            'query' => ['scorecard' => $scorecardKey],
        ];
    }
    $supplierLifecycleActions = [
        'hold_buying_supplier' => 'HOLD',
        'release_buying_supplier' => 'RELEASE',
        'disable_buying_supplier' => 'DISABLE',
        'activate_buying_supplier' => 'ACTIVATE',
        'archive_buying_supplier' => 'ARCHIVE',
    ];
    if (isset($supplierLifecycleActions[$action])) {
        $supplierKey = trim((string) ($input['supplier_key'] ?? ''));
        $lifecycle = $supplierLifecycleActions[$action];
        yovel_admin_buying_set_supplier_status(bx_db(), $company, $admin, $supplierKey, $lifecycle, $input);
        return [
            'message' => match ($lifecycle) {
                'HOLD' => 'Supplier placed on hold.',
                'RELEASE' => 'Supplier released.',
                'DISABLE' => 'Supplier disabled.',
                'ACTIVATE' => 'Supplier activated.',
                'ARCHIVE' => 'Supplier archived.',
            },
            'section' => 'suppliers',
            'query' => ['supplier' => $supplierKey],
        ];
    }
    if ($action === 'review_buying_form_target') {
        $target = trim((string) ($input['form_target'] ?? ''));
        $adapter = yovel_admin_buying_procurement_form_adapter();
        if (!in_array($target, $adapter['target_record_types'], true)) {
            throw new InvalidArgumentException('Select a registered Buying Form Builder target.');
        }

        return [
            'message' => 'Buying Form Builder target loaded.',
            'section' => 'form-builder',
            'query' => ['form_target' => $target],
        ];
    }
    if ($action === 'refresh_buying_workspace') {
        $section = yovel_admin_slug((string) ($input['section'] ?? 'suppliers'));
        if (!array_key_exists($section, yovel_admin_buying_procurement_sections())) {
            $section = 'suppliers';
        }

        return [
            'message' => 'Buying / Procurement dependency state refreshed.',
            'section' => $section,
            'query' => [],
        ];
    }

    throw new InvalidArgumentException('This Buying / Procurement action is not available in the current implementation slice.');
}
