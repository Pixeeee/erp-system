<?php
declare(strict_types=1);

require_once __DIR__ . '/sales-crm-test-helper.php';

$root = dirname(__DIR__);
$expectedSections = [
    'leads',
    'opportunities',
    'campaigns',
    'customers',
    'quotations',
    'sales-orders',
    'customer-credit-limits',
    'sales-analytics',
    'salesperson-performance',
    'territory-performance',
];

sales_crm_assert(array_keys(yovel_admin_sales_crm_sections()) === $expectedSections, 'Sales / CRM route contract changed.');
$_GET['section'] = 'not-a-sales-section';
sales_crm_assert(yovel_admin_sales_crm_section() === 'leads', 'Unknown Sales / CRM sections must fall back to Leads.');
unset($_GET['section']);

sales_crm_assert(is_file($root . '/company/admin/modules/sales-crm/schema.php'), 'Sales / CRM schema responsibility was not split.');
sales_crm_assert(is_file($root . '/company/admin/modules/sales-crm/form-builder.php'), 'Sales / CRM Form Builder responsibility was not split.');
sales_crm_assert(function_exists('yovel_admin_sales_crm_form_targets'), 'Sales / CRM form targets adapter is missing.');
sales_crm_assert(function_exists('yovel_admin_sales_crm_form_adapter'), 'Sales / CRM shared Form Builder adapter is missing.');
sales_crm_assert(function_exists('yovel_admin_sales_crm_schema_checksum'), 'Sales / CRM schema version identity is missing.');
sales_crm_assert(function_exists('yovel_admin_sales_crm_shared_schema_state'), 'Legacy schema state compatibility is missing.');
sales_crm_assert(function_exists('yovel_admin_sales_crm_reorder_fields'), 'Sales / CRM shared reorder adapter is missing.');

$route = yovel_admin_module_route('sales-crm');
sales_crm_assert(is_array($route) && ($route['default_section'] ?? '') === 'leads', 'Shared registry does not expose Sales / CRM.');
$targets = yovel_admin_sales_crm_form_targets();
sales_crm_assert($targets === [
    'leads' => 'lead',
    'opportunities' => 'opportunity',
    'campaigns' => 'campaign',
    'customers' => 'customer',
    'quotations' => 'quotation',
    'sales-orders' => 'sales-order',
    'customer-credit-limits' => 'customer-credit-limit',
], 'Sales / CRM Form Builder targets changed.');

$adapter = yovel_admin_sales_crm_form_adapter();
foreach (['module', 'target_record_types', 'protected_fields', 'field_types', 'row_column_layout', 'normalize', 'version_identity', 'renderer', 'persistence_mappings', 'workflow_constraints', 'legacy_status_map'] as $key) {
    sales_crm_assert(array_key_exists($key, $adapter), 'Sales / CRM adapter is missing ' . $key . '.');
}
sales_crm_assert($adapter['module'] === 'sales-crm', 'Sales / CRM adapter has the wrong module.');
sales_crm_assert($adapter['target_record_types'] === $targets, 'Sales / CRM adapter target mapping is incomplete.');
sales_crm_assert(($adapter['row_column_layout']['max_columns'] ?? 0) === 3, 'Sales / CRM adapter must support three columns.');
sales_crm_assert(in_array('lead_code', $adapter['protected_fields']['lead'] ?? [], true), 'Lead code is not protected.');
sales_crm_assert(in_array('lead_name', $adapter['protected_fields']['lead'] ?? [], true), 'Lead name is not protected.');
sales_crm_assert(($adapter['legacy_status_map']['ACTIVE'] ?? '') === 'PUBLISHED', 'ACTIVE schema compatibility changed.');
sales_crm_assert(($adapter['legacy_status_map']['INACTIVE'] ?? '') === 'ARCHIVED', 'INACTIVE schema compatibility changed.');

$leadSchema = yovel_admin_sales_crm_default_form_schemas()['lead'];
$leadFieldKeys = array_column($leadSchema['fields'], 'key');
sales_crm_assert($leadFieldKeys === [
    'lead_code', 'lead_status', 'lead_name', 'organization_name', 'lead_source',
    'campaign_key', 'territory_key', 'salesperson_key', 'email', 'phone', 'mobile',
    'website', 'industry', 'estimated_value', 'next_contact_date', 'notes',
], 'The preserved Lead schema field contract changed.');
$leadStatusField = array_values(array_filter($leadSchema['fields'], static fn (array $field): bool => ($field['key'] ?? '') === 'lead_status'))[0] ?? [];
sales_crm_assert(($leadStatusField['options'] ?? []) === ['DRAFT', 'OPEN', 'QUALIFIED', 'CONVERTED', 'LOST', 'INACTIVE'], 'The preserved Lead form statuses changed.');
$normalizedLead = yovel_admin_sales_crm_normalize_schema('lead', [
    'fields' => [
        ['key' => 'lead_code', 'label' => 'Reference', 'required' => false, 'visible' => false, 'sortOrder' => 999],
        ['key' => 'unregistered_field', 'label' => 'Ignore me'],
    ],
], 8);
$normalizedByKey = array_column($normalizedLead['fields'], null, 'key');
sales_crm_assert(($normalizedLead['version'] ?? 0) === 8, 'Normalized schemas lost their version identity.');
sales_crm_assert(($normalizedByKey['lead_code']['required'] ?? false) === true && ($normalizedByKey['lead_code']['visible'] ?? false) === true, 'Protected Lead fields can be hidden or made optional.');
sales_crm_assert(!isset($normalizedByKey['unregistered_field']), 'Schema normalization accepted an unregistered field definition.');
foreach ([
    'yovel_admin_sales_crm_schema',
    'yovel_admin_sales_crm_default_form_schemas',
    'yovel_admin_sales_crm_active_schema',
    'yovel_admin_write_sales_form_schema',
    'yovel_admin_save_form_schema',
    'yovel_admin_reset_form_schema',
    'yovel_admin_sales_crm_data',
    'yovel_admin_save_sales_lead',
    'yovel_admin_set_sales_lead_status',
] as $publicFunction) {
    sales_crm_assert(function_exists($publicFunction), 'Missing preserved Sales / CRM public function: ' . $publicFunction);
}
$reordered = yovel_admin_sales_crm_reorder_fields('lead', $leadSchema['fields'], ['notes', 'renamed_lead_code', 'lead_name']);
$reorderedKeys = array_column($reordered, 'key');
sales_crm_assert($reorderedKeys[0] === 'notes' && $reorderedKeys[1] === 'lead_name', 'Shared reorder did not preserve requested stable keys.');
sales_crm_assert(in_array('lead_code', $reorderedKeys, true), 'Shared reorder removed a protected Lead field.');
sales_crm_assert(!in_array('renamed_lead_code', $reorderedKeys, true), 'Shared reorder accepted a renamed stable key.');
$checksumA = yovel_admin_sales_crm_schema_checksum($leadSchema);
$checksumB = yovel_admin_sales_crm_schema_checksum($leadSchema);
sales_crm_assert(strlen($checksumA) === 64 && hash_equals($checksumA, $checksumB), 'Sales / CRM schema checksum is not stable.');
sales_crm_assert(yovel_admin_sales_crm_shared_schema_state(['schema_status' => 'ACTIVE']) === 'PUBLISHED', 'ACTIVE schema did not map to PUBLISHED.');
sales_crm_assert(yovel_admin_sales_crm_shared_schema_state(['schema_status' => 'DELETED']) === 'ARCHIVED', 'DELETED schema did not map to ARCHIVED.');

yovel_admin_sales_crm_schema();
$db = bx_db();
foreach ([
    'project_company_form_schema',
    'project_company_form_schema_audit',
    'project_company_sales_campaign',
    'project_company_sales_customer',
    'project_company_sales_territory',
    'project_company_salesperson',
    'project_company_sales_lead',
] as $table) {
    $exists = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    sales_crm_assert($exists === 1, 'Missing Sales / CRM table: ' . $table);
}
$leadIndexes = $db->GetCol(
    'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
    [BUILDERX_DB_NAME, 'project_company_sales_lead']
);
foreach (['uq_project_company_sales_lead_code', 'idx_project_company_sales_lead_status', 'idx_project_company_sales_lead_updated'] as $index) {
    sales_crm_assert(is_array($leadIndexes) && in_array($index, $leadIndexes, true), 'Missing Lead index: ' . $index);
}
$leadCountBeforeSchemaRepeat = (int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_lead');
yovel_admin_sales_crm_schema();
sales_crm_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_lead') === $leadCountBeforeSchemaRepeat, 'Sales / CRM schema setup is not idempotent.');

$fixture = sales_crm_test_company();
$company = $fixture['company'];
$admin = $fixture['admin'];
$leadCode = sales_crm_test_code('SC01');
$rollbackCode = sales_crm_test_code('SC01_ROLLBACK');
$leadKey = '';

try {
    $createMessage = sales_crm_with_post([
        'lead_code' => $leadCode,
        'lead_name' => 'SC-01 Characterization Lead',
        'organization_name' => 'BuilderX Verification',
        'lead_status' => 'OPEN',
        'lead_source' => 'SC-01 Test',
        'email' => 'sc01@example.test',
        'estimated_value' => '1250.50',
        'next_contact_date' => '2026-08-26',
        'notes' => 'Create path',
    ], static fn (): string => yovel_admin_save_sales_lead($company, $admin));
    sales_crm_assert($createMessage === 'Lead created.', 'Lead create path returned an unexpected message.');
    $created = $db->GetRow(
        'SELECT * FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_code = ? LIMIT 1',
        [$company['company_key_hash'], $leadCode]
    );
    sales_crm_assert(is_array($created) && $created !== [], 'Lead create did not commit.');
    $leadKey = (string) $created['lead_key'];
    sales_crm_assert((string) $created['lead_name'] === 'SC-01 Characterization Lead', 'Lead create read-back changed the name.');
    sales_crm_assert((string) $created['estimated_value'] === '1250.50', 'Lead create read-back changed the value.');
    sales_crm_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_sales_lead' AND record_key = ? AND action = 'CREATE'", [$leadKey]) === 1, 'Lead create audit was not committed.');

    $updateMessage = sales_crm_with_post([
        'lead_key' => $leadKey,
        'lead_code' => $leadCode,
        'lead_name' => 'SC-01 Updated Lead',
        'organization_name' => 'BuilderX Verification',
        'lead_status' => 'OPEN',
        'lead_source' => 'SC-01 Test',
        'email' => 'updated-sc01@example.test',
        'estimated_value' => '2400.00',
        'next_contact_date' => '2026-08-27',
        'notes' => 'Update path',
    ], static fn (): string => yovel_admin_save_sales_lead($company, $admin));
    sales_crm_assert($updateMessage === 'Lead updated.', 'Lead update path returned an unexpected message.');
    $updated = $db->GetRow('SELECT * FROM project_company_sales_lead WHERE lead_key = ? LIMIT 1', [$leadKey]);
    sales_crm_assert((string) $updated['lead_key'] === $leadKey, 'Lead update changed the stable key.');
    sales_crm_assert((string) $updated['lead_name'] === 'SC-01 Updated Lead', 'Lead update did not read back the name.');
    sales_crm_assert((string) $updated['estimated_value'] === '2400.00', 'Lead update did not read back the value.');
    sales_crm_assert((int) $db->GetOne("SELECT COUNT(*) FROM builder_audit_log WHERE module = 'project_company_sales_lead' AND record_key = ? AND action = 'UPDATE'", [$leadKey]) === 1, 'Lead update audit was not committed.');

    $statusMessage = sales_crm_with_post([
        'lead_key' => $leadKey,
        'lead_status' => 'QUALIFIED',
    ], static fn (): string => yovel_admin_set_sales_lead_status($company, $admin));
    sales_crm_assert($statusMessage === 'Lead status updated.', 'Lead status path returned an unexpected message.');
    sales_crm_assert((string) $db->GetOne('SELECT lead_status FROM project_company_sales_lead WHERE lead_key = ?', [$leadKey]) === 'QUALIFIED', 'Lead status did not commit.');

    $wrongCompany = $company;
    $wrongCompany['company_key_hash'] = str_repeat('0', 64);
    $isolationRejected = false;
    try {
        sales_crm_with_post([
            'lead_key' => $leadKey,
            'lead_code' => $leadCode,
            'lead_name' => 'Cross-company overwrite',
            'lead_status' => 'OPEN',
        ], static fn (): string => yovel_admin_save_sales_lead($wrongCompany, $admin));
    } catch (InvalidArgumentException) {
        $isolationRejected = true;
    }
    sales_crm_assert($isolationRejected, 'Cross-company Lead update was not rejected.');
    sales_crm_assert((string) $db->GetOne('SELECT lead_name FROM project_company_sales_lead WHERE lead_key = ?', [$leadKey]) === 'SC-01 Updated Lead', 'Cross-company update changed the Lead.');

    $rollbackCompany = $company;
    $rollbackCompany['company_name'] = "\xB1";
    $rollbackThrown = false;
    try {
        sales_crm_with_post([
            'lead_code' => $rollbackCode,
            'lead_name' => 'Rollback Lead',
            'lead_status' => 'OPEN',
        ], static fn (): string => yovel_admin_save_sales_lead($rollbackCompany, $admin));
    } catch (JsonException) {
        $rollbackThrown = true;
    }
    sales_crm_assert($rollbackThrown, 'Audit serialization failure did not reach the rollback path.');
    sales_crm_assert((int) $db->GetOne('SELECT COUNT(*) FROM project_company_sales_lead WHERE company_key_hash = ? AND lead_code = ?', [$company['company_key_hash'], $rollbackCode]) === 0, 'Lead write survived a post-write audit failure.');

    $deleteMessage = sales_crm_with_post([
        'lead_key' => $leadKey,
        'lead_status' => 'DELETED',
    ], static fn (): string => yovel_admin_set_sales_lead_status($company, $admin));
    sales_crm_assert($deleteMessage === 'Lead status updated.', 'Lead soft delete returned an unexpected message.');
    sales_crm_assert((string) $db->GetOne('SELECT lead_status FROM project_company_sales_lead WHERE lead_key = ?', [$leadKey]) === 'DELETED', 'Lead soft delete did not commit.');
} finally {
    yovel_admin_sales_crm_clear_rehydration('lead');
    sales_crm_cleanup_lead($db, (string) $company['company_key_hash'], $leadKey, $leadCode);
    sales_crm_cleanup_lead($db, (string) $company['company_key_hash'], '', $rollbackCode);
}
echo "Sales / CRM DB evidence passed: create/update/status audit committed, company isolation held, and the forced post-write audit failure rolled back.\n";

$workspace = (string) file_get_contents($root . '/company/admin/modules/sales-crm/views/workspace.php');
sales_crm_assert(str_contains($workspace, 'xl:grid-cols-[minmax(0,12fr)_minmax(20rem,8fr)]'), 'Sales / CRM workspace does not use the approved 12/8 grid.');
sales_crm_assert(str_contains($workspace, 'grid-template-columns: minmax(0, 12fr) minmax(20rem, 8fr);'), 'Sales / CRM workspace does not render the approved 12/8 grid.');
foreach (['data-record-modal-open="yovel-sales-lead-modal"', 'data-record-modal', 'data-record-modal-form', 'data-record-modal-close', 'data-confirm-submit', 'data-confirm-message', 'data-confirm-submit-action'] as $marker) {
    sales_crm_assert(str_contains($workspace, $marker), 'Sales / CRM workspace is missing shared modal marker: ' . $marker);
}

echo "Sales / CRM SC-01 foundation checks passed.\n";
