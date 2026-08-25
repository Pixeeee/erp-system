<?php
declare(strict_types=1);

require_once __DIR__ . '/core.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/evidence.php';
require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/dashboard.php';

function yovel_admin_compliance_sections(): array
{
    return [
        'dashboard' => ['label' => 'Compliance Dashboard', 'icon' => 'verified_user', 'package' => 'WP-01', 'description' => 'Review Philippine localization readiness, governed records, and dependency health.'],
        'tax-rules' => ['label' => 'Tax Rules', 'icon' => 'gavel', 'package' => 'WP-02', 'record_type' => 'localization-rule', 'description' => 'Manage effective-dated Philippine tax and localization rule packages.'],
        'vat-settings' => ['label' => 'VAT Settings', 'icon' => 'percent', 'package' => 'WP-02', 'record_type' => 'vat-settings', 'description' => 'Map approved VAT controls to Finance-owned account references.'],
        'supplier-einvoice-imports' => ['label' => 'Supplier E-Invoice Imports', 'icon' => 'upload_file', 'package' => 'WP-04', 'record_type' => 'supplier-einvoice-import', 'description' => 'Stage and validate supplier electronic invoice packages before Finance projection.'],
        'tax-document-templates' => ['label' => 'Tax Document Templates', 'icon' => 'description', 'package' => 'WP-03', 'record_type' => 'tax-document-template', 'description' => 'Version Philippine tax document output profiles without copying foreign templates.'],
        'einvoice-register' => ['label' => 'E-Invoice Register', 'icon' => 'receipt_long', 'package' => 'WP-03', 'description' => 'Trace source snapshots, validation, transmission, corrections, and acknowledgements.'],
        'withholding-certificates' => ['label' => 'Withholding Certificates', 'icon' => 'workspace_premium', 'package' => 'WP-05', 'record_type' => 'withholding-certificate', 'description' => 'Control reduced-rate authorities and supplier withholding certificate issuance.'],
        'vat-returns' => ['label' => 'VAT Returns', 'icon' => 'summarize', 'package' => 'WP-06', 'record_type' => 'vat-return', 'description' => 'Prepare reviewed Philippine VAT return packages from immutable Finance snapshots.'],
        'vat-audit' => ['label' => 'VAT Audit', 'icon' => 'fact_check', 'package' => 'WP-06', 'description' => 'Reconcile VAT classifications, mappings, evidence, and source-document totals.'],
        'audit-evidence' => ['label' => 'Audit Evidence', 'icon' => 'policy', 'package' => 'WP-02', 'record_type' => 'audit-evidence', 'description' => 'Retain checksum-verified compliance evidence and legal holds.'],
        'regulatory-exports' => ['label' => 'Regulatory Exports', 'icon' => 'file_export', 'package' => 'WP-07', 'record_type' => 'regulatory-export', 'description' => 'Approve deterministic export manifests before Operations transport.'],
        'form-builder' => ['label' => 'Form Builder', 'icon' => 'dynamic_form', 'package' => 'WP-08', 'description' => 'Inspect protected fields and module record targets through the shared Form Builder contract.'],
    ];
}

function yovel_admin_compliance_section(string $requested = ''): string
{
    $requested = $requested !== '' ? $requested : (string) ($_GET['section'] ?? 'dashboard');
    $requested = yovel_admin_slug($requested);
    return array_key_exists($requested, yovel_admin_compliance_sections()) ? $requested : 'dashboard';
}

function yovel_admin_compliance_form_adapter(): array
{
    $shared = function_exists('yovel_admin_shared_form_adapter')
        ? yovel_admin_shared_form_adapter('compliance-localization')
        : [
            'module' => 'compliance-localization',
            'target_record_types' => ['dashboard' => 'record'],
            'protected_fields' => [],
            'field_types' => ['SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'CURRENCY', 'DATE', 'DROPDOWN', 'CHECKBOXES', 'SECTION'],
            'row_column_layout' => ['version' => 1, 'max_columns' => 3, 'stable_keys' => true],
            'normalize' => null,
            'version_identity' => null,
            'renderer' => 'company/admin/modules/compliance-localization/views/workspace.php',
        ];

    return [
        'module' => 'compliance-localization',
        'target_record_types' => [
            'tax-rules' => 'localization-rule',
            'vat-settings' => 'vat-settings',
            'supplier-einvoice-imports' => 'supplier-einvoice-import',
            'tax-document-templates' => 'tax-document-template',
            'withholding-certificates' => 'withholding-certificate',
            'vat-returns' => 'vat-return',
            'audit-evidence' => 'audit-evidence',
            'regulatory-exports' => 'regulatory-export',
        ],
        'protected_fields' => [
            'localization-rule' => ['rule_set_key', 'company_key_hash', 'jurisdiction_code', 'rule_code', 'version_number', 'version_status', 'rule_sha256'],
            'vat-settings' => ['rule_set_key', 'company_key_hash', 'jurisdiction_code', 'version_number', 'version_status'],
            'supplier-einvoice-import' => ['import_batch_key', 'company_key_hash', 'source_sha256', 'schema_version', 'batch_status', 'idempotency_key'],
            'tax-document-template' => ['template_key', 'company_key_hash', 'template_code', 'template_version_key', 'schema_sha256', 'version_status'],
            'withholding-certificate' => ['withholding_certificate_key', 'company_key_hash', 'certificate_number', 'certificate_status', 'payload_sha256'],
            'vat-return' => ['return_package_key', 'company_key_hash', 'return_type', 'revision_number', 'return_status', 'source_manifest_sha256', 'totals_sha256'],
            'audit-evidence' => ['evidence_key', 'company_key_hash', 'sha256', 'storage_path', 'retention_until', 'evidence_status'],
            'regulatory-export' => ['export_key', 'company_key_hash', 'schema_version', 'source_manifest_sha256', 'payload_sha256', 'idempotency_key', 'export_status'],
        ],
        'field_types' => $shared['field_types'],
        'row_column_layout' => $shared['row_column_layout'],
        'normalize' => null,
        'version_identity' => 'yovel_admin_compliance_checksum',
        'renderer' => 'company/admin/modules/compliance-localization/views/workspace.php',
        'shared_contract' => $shared,
        'persistence_enabled' => false,
    ];
}

function yovel_admin_compliance_state(string $section, array $metadata): array
{
    if ($section === 'dashboard') {
        return ['kind' => 'ready', 'title' => 'Compliance foundation ready', 'message' => 'Company scope, lifecycle contracts, and evidence-ready tables are available.', 'dependencies' => []];
    }
    if ($section === 'form-builder') {
        return ['kind' => 'ready', 'title' => 'Read-only foundation', 'message' => 'The shared Form Builder adapter exposes targets and protected fields; publishing begins in WP-08.', 'dependencies' => ['WP-08']];
    }

    return [
        'kind' => 'foundation',
        'title' => (string) ($metadata['label'] ?? 'Compliance package'),
        'message' => 'The governed schema and authorization boundary are ready. Record persistence begins in the listed approved package.',
        'dependencies' => [(string) ($metadata['package'] ?? '')],
    ];
}

function yovel_admin_compliance_data(array $company, array $admin, string $section = ''): array
{
    [, $companyKeyHash] = yovel_admin_compliance_scope($company, $admin);
    $section = yovel_admin_compliance_section($section);
    if ($section !== 'dashboard') {
        yovel_admin_compliance_schema();
    }
    $sections = yovel_admin_compliance_sections();
    $metadata = $sections[$section];
    $db = bx_db();
    $counts = [
        'rules' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_rule_set WHERE company_key_hash = ?', [$companyKeyHash]),
        'templates' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_template WHERE company_key_hash = ?', [$companyKeyHash]),
        'evidence' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_evidence WHERE company_key_hash = ?', [$companyKeyHash]),
        'exports' => (int) $db->GetOne('SELECT COUNT(*) FROM project_company_compliance_export WHERE company_key_hash = ?', [$companyKeyHash]),
    ];
    $providers = is_array($GLOBALS['yovel_admin_compliance_dependency_providers'] ?? null)
        ? $GLOBALS['yovel_admin_compliance_dependency_providers']
        : [];
    $rules = in_array($section, ['tax-rules', 'vat-settings', 'audit-evidence'], true) ? yovel_admin_compliance_rule_sets($company) : [];
    $evidence = $section === 'audit-evidence' ? yovel_admin_compliance_evidence_rows($company) : [];
    $templates = $section === 'tax-document-templates' ? yovel_admin_compliance_templates($company) : [];
    $invoiceProvider = is_callable($providers['accounting-finance.invoice-snapshot.v1'] ?? null)
        ? $providers['accounting-finance.invoice-snapshot.v1']
        : null;
    $register = $section === 'einvoice-register'
        ? yovel_admin_compliance_einvoice_register($company, [
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to' => (string) ($_GET['date_to'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
        ], $invoiceProvider)
        : null;
    $selectedRuleKey = trim((string) ($_GET['rule'] ?? ''));
    if ($selectedRuleKey === '' && $rules !== []) {
        $selectedRuleKey = (string) $rules[0]['rule_set_key'];
    }
    $selectedEvidenceKey = trim((string) ($_GET['evidence'] ?? ''));
    if ($selectedEvidenceKey === '' && $evidence !== []) {
        $selectedEvidenceKey = (string) $evidence[0]['evidence_key'];
    }
    $dashboard = $section === 'dashboard'
        ? yovel_admin_compliance_localization_dashboard_data($company, $admin)
        : null;

    return [
        'section' => $section,
        'sections' => $sections,
        'section_meta' => $metadata,
        'state' => yovel_admin_compliance_state($section, $metadata),
        'company_key_hash' => $companyKeyHash,
        'company_name' => (string) ($company['company_name'] ?? 'Company'),
        'jurisdiction' => 'PH',
        'metrics' => $counts,
        'form_adapter' => yovel_admin_compliance_form_adapter(),
        'shared_form_adapter' => yovel_admin_shared_form_adapter('compliance-localization'),
        'lifecycle_contracts' => yovel_admin_compliance_lifecycle_contracts(),
        'rules' => $rules,
        'evidence' => $evidence,
        'templates' => $templates,
        'einvoice_register' => $register,
        'selected_rule' => $selectedRuleKey !== '' ? yovel_admin_compliance_rule_set($company, $selectedRuleKey) : null,
        'selected_evidence' => $selectedEvidenceKey !== '' ? yovel_admin_compliance_evidence($company, $selectedEvidenceKey) : null,
        'finance_account_reference_available' => is_callable($providers['accounting-finance.account-reference.v1'] ?? null),
        'finance_invoice_snapshot_available' => is_callable($invoiceProvider),
        'dashboard' => $dashboard,
    ];
}

function yovel_admin_compliance_localization_sections(): array
{
    return yovel_admin_compliance_sections();
}

function yovel_admin_compliance_localization_data(array $company, ?array $admin = null): array
{
    if ($admin === null) {
        throw new InvalidArgumentException('An active company administrator is required.');
    }
    return yovel_admin_compliance_data($company, $admin, (string) ($_GET['section'] ?? 'dashboard'));
}

function yovel_admin_compliance_localization_handle_post(
    array $company,
    array $admin,
    string $action,
    array $input
): array {
    yovel_admin_compliance_scope($company, $admin);
    if (strtolower(trim((string) ($input['module_view'] ?? 'compliance-localization'))) !== 'compliance-localization') {
        throw new InvalidArgumentException('Compliance / Localization module scope is invalid.');
    }
    $action = strtolower(trim($action));
    $section = yovel_admin_compliance_section((string) ($input['section'] ?? 'dashboard'));
    $providers = is_array($GLOBALS['yovel_admin_compliance_dependency_providers'] ?? null)
        ? $GLOBALS['yovel_admin_compliance_dependency_providers']
        : [];
    $financeProvider = $providers['accounting-finance.account-reference.v1'] ?? null;
    $invoiceProvider = $providers['accounting-finance.invoice-snapshot.v1'] ?? null;
    if ($action === 'save_compliance_template') {
        $saved = yovel_admin_compliance_save_template(bx_db(), $company, $admin, $input);
        return ['message' => 'Compliance invoice profile Draft saved.', 'section' => 'tax-document-templates', 'query' => ['template_version' => (string) $saved['template_version_key']]];
    }
    if ($action === 'publish_compliance_template') {
        $saved = yovel_admin_compliance_publish_template(bx_db(), $company, $admin, (string) ($input['template_version_key'] ?? ''), (string) ($input['comments'] ?? ''));
        return ['message' => 'Compliance invoice profile published.', 'section' => 'tax-document-templates', 'query' => ['template_version' => (string) $saved['template_version_key']]];
    }
    if ($action === 'archive_compliance_template') {
        $saved = yovel_admin_compliance_archive_template(bx_db(), $company, $admin, (string) ($input['template_version_key'] ?? ''), (string) ($input['comments'] ?? ''));
        return ['message' => 'Compliance invoice profile archived.', 'section' => 'tax-document-templates', 'query' => ['template_version' => (string) $saved['template_version_key']]];
    }
    if (in_array($action, ['register_compliance_einvoice', 'correct_compliance_einvoice'], true)) {
        $provider = yovel_admin_compliance_dependency_provider('accounting-finance.invoice-snapshot.v1', $providers);
        $saved = yovel_admin_compliance_register_einvoice(bx_db(), $company, $admin, (string) ($input['finance_document_key'] ?? ''), $provider, $action === 'correct_compliance_einvoice' ? (string) ($input['correction_of_record_key'] ?? '') : null);
        return ['message' => $action === 'correct_compliance_einvoice' ? 'E-invoice correction registered.' : 'E-invoice snapshot registered.', 'section' => 'einvoice-register', 'query' => ['einvoice' => (string) $saved['einvoice_record_key']]];
    }
    if (in_array($action, ['queue_compliance_einvoice', 'retry_compliance_einvoice', 'cancel_compliance_einvoice'], true)) {
        $toStatus = $action === 'cancel_compliance_einvoice' ? 'CANCELLED' : 'QUEUED';
        $saved = yovel_admin_compliance_update_transmission(bx_db(), $company, $admin, (string) ($input['einvoice_record_key'] ?? ''), $toStatus);
        return ['message' => $toStatus === 'QUEUED' ? 'E-invoice queued for transmission.' : 'E-invoice transmission cancelled.', 'section' => 'einvoice-register', 'query' => ['einvoice' => (string) $saved['einvoice_record_key']]];
    }
    if ($action === 'save_compliance_rule') {
        $saved = yovel_admin_compliance_save_rule_set(bx_db(), $company, $admin, $input, is_callable($financeProvider) ? $financeProvider : null);
        return ['message' => 'Compliance rule Draft saved.', 'section' => $section, 'query' => ['rule' => (string) $saved['rule_set_key']]];
    }
    if ($action === 'add_compliance_mapping') {
        $provider = yovel_admin_compliance_dependency_provider('accounting-finance.account-reference.v1', $providers);
        $saved = yovel_admin_compliance_add_mapping(bx_db(), $company, $admin, (string) ($input['rule_set_key'] ?? ''), $input, $provider);
        return ['message' => 'Compliance account mapping saved.', 'section' => $section, 'query' => ['rule' => (string) $saved['rule_set_key']]];
    }
    if ($action === 'approve_compliance_rule') {
        $saved = yovel_admin_compliance_approve_rule_set(bx_db(), $company, $admin, (string) ($input['rule_set_key'] ?? ''), (string) ($input['comments'] ?? ''));
        return ['message' => 'Compliance rule approved.', 'section' => $section, 'query' => ['rule' => (string) $saved['rule_set_key']]];
    }
    if ($action === 'supersede_compliance_rule') {
        $saved = yovel_admin_compliance_supersede_rule_set(bx_db(), $company, $admin, (string) ($input['rule_set_key'] ?? ''), (string) ($input['comments'] ?? ''));
        return ['message' => 'Compliance rule superseded.', 'section' => $section, 'query' => ['rule' => (string) $saved['rule_set_key']]];
    }
    if ($action === 'archive_compliance_rule') {
        $saved = yovel_admin_compliance_archive_rule_set(bx_db(), $company, $admin, (string) ($input['rule_set_key'] ?? ''), (string) ($input['comments'] ?? ''));
        return ['message' => 'Compliance rule archived.', 'section' => $section, 'query' => ['rule' => (string) $saved['rule_set_key']]];
    }
    if ($action === 'save_compliance_evidence') {
        $upload = is_array($input['upload'] ?? null) ? $input['upload'] : (is_array($_FILES['evidence_file'] ?? null) ? $_FILES['evidence_file'] : []);
        $saved = yovel_admin_compliance_save_evidence(bx_db(), $company, $admin, $upload, $input);
        return ['message' => 'Compliance evidence retained.', 'section' => 'audit-evidence', 'query' => ['evidence' => (string) $saved['evidence_key']]];
    }
    if ($action === 'place_compliance_hold') {
        $saved = yovel_admin_compliance_place_hold(bx_db(), $company, $admin, (string) ($input['evidence_key'] ?? ''), (string) ($input['hold_reference'] ?? ''), (string) ($input['hold_reason'] ?? ''));
        return ['message' => 'Compliance evidence placed on hold.', 'section' => 'audit-evidence', 'query' => ['evidence' => (string) $saved['evidence_key']]];
    }
    if ($action === 'release_compliance_hold') {
        $saved = yovel_admin_compliance_release_hold(bx_db(), $company, $admin, (string) ($input['retention_hold_key'] ?? ''), (string) ($input['release_reason'] ?? ''));
        return ['message' => 'Compliance evidence hold released.', 'section' => 'audit-evidence', 'query' => ['hold' => (string) $saved['retention_hold_key']]];
    }
    if ($action === 'archive_compliance_evidence') {
        $saved = yovel_admin_compliance_archive_evidence(bx_db(), $company, $admin, (string) ($input['evidence_key'] ?? ''), (string) ($input['archive_reason'] ?? ''));
        return ['message' => 'Compliance evidence archived.', 'section' => 'audit-evidence', 'query' => ['evidence' => (string) $saved['evidence_key']]];
    }
    throw new InvalidArgumentException('This Compliance / Localization action is not available in WP-02 or WP-03.');
}
