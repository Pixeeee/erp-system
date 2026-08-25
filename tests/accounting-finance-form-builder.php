<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/forms.php';

function finance_builder_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$adminCss = file_get_contents($root . '/company/admin/assets/css/admin.css');
finance_builder_assert(is_string($adminCss), 'Unable to read the admin stylesheet.');
finance_builder_assert(
    str_contains($adminCss, ".yovel-finance-builder-dialog {\n            background: var(--card);"),
    'Finance builder dialog must have an opaque card background.'
);
finance_builder_assert(
    str_contains($adminCss, ".yovel-finance-builder-body {\n            background: var(--background);"),
    'Finance builder body must have an opaque background.'
);
finance_builder_assert(
    str_contains($adminCss, ".yovel-finance-builder-tabs {\n            background: var(--background);"),
    'Finance builder tabs must have an opaque background.'
);
finance_builder_assert(
    str_contains($adminCss, ".yovel-finance-builder-dialog > footer {\n            background: var(--popover);"),
    'Finance builder header and footer must have an opaque popover background.'
);
finance_builder_assert(
    str_contains($adminCss, ".yovel-finance-builder-pane {\n            background: var(--background);"),
    'Finance builder workbench panes must have an opaque background.'
);

yovel_admin_finance_builder_schema();
$db = bx_db();

foreach (['project_company_finance_builder_form', 'project_company_finance_builder_form_version'] as $table) {
    $exists = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    finance_builder_assert($exists === 1, 'Missing Finance builder table: ' . $table);
}

$schema = yovel_admin_normalize_finance_builder_schema(json_encode([
    'questions' => [[
        'key' => 'amount',
        'label' => 'Amount',
        'help' => 'Amount in the transaction currency.',
        'type' => 'CURRENCY',
        'required' => true,
        'precision' => 2,
    ]],
], JSON_THROW_ON_ERROR));

finance_builder_assert(($schema['version'] ?? null) === 1, 'Finance builder schema version was not normalized.');
finance_builder_assert(($schema['questions'][0]['type'] ?? '') === 'CURRENCY', 'Currency questions were not normalized.');
finance_builder_assert(($schema['questions'][0]['precision'] ?? null) === 2, 'Currency precision was not preserved.');
finance_builder_assert(($schema['questions'][0]['required'] ?? false) === true, 'Required state was not preserved.');

$targets = yovel_admin_finance_builder_target_sections();
finance_builder_assert(count($targets) === 15, 'Finance builder must expose all 15 Finance features.');
finance_builder_assert(yovel_admin_finance_builder_target_section('sales-invoices') === 'sales-invoices', 'A valid Finance target was rejected.');
finance_builder_assert(yovel_admin_finance_builder_target_section('hr-reports') === 'chart-of-accounts', 'A non-Finance target was accepted.');

finance_builder_assert(function_exists('yovel_admin_persist_finance_builder_form'), 'Finance custom form persistence is not implemented.');

$fixture = $db->GetRow(
    "SELECT company_record.company_key, company_record.company_key_hash, company_record.company_name, admin_record.admin_key
     FROM project_company company_record
     INNER JOIN project_company_admin admin_record
        ON admin_record.company_key_hash = company_record.company_key_hash
       AND admin_record.admin_status = 'ACTIVE'
     WHERE company_record.company_status <> 'DELETED'
     ORDER BY company_record.x_id, admin_record.x_id
     LIMIT 1"
);
finance_builder_assert(is_array($fixture) && $fixture !== [], 'An active company-admin fixture is required.');

$fixtureCompany = [
    'company_key' => (string) $fixture['company_key'],
    'company_key_hash' => (string) $fixture['company_key_hash'],
    'company_name' => (string) $fixture['company_name'],
];
$fixtureAdmin = ['admin_key' => (string) $fixture['admin_key']];
$fixtureFormKey = bx_uuid();
$firstSchema = json_encode([
    'version' => 1,
    'questions' => [['key' => 'reference', 'label' => 'Reference', 'type' => 'SHORT_TEXT', 'required' => true]],
], JSON_THROW_ON_ERROR);
$secondSchema = json_encode([
    'version' => 1,
    'questions' => [
        ['key' => 'reference', 'label' => 'Invoice reference', 'type' => 'SHORT_TEXT', 'required' => true],
        ['key' => 'amount', 'label' => 'Amount', 'type' => 'CURRENCY', 'precision' => 2],
    ],
], JSON_THROW_ON_ERROR);

try {
    $created = yovel_admin_persist_finance_builder_form($db, $fixtureCompany, $fixtureAdmin, [
        'builder_form_key' => $fixtureFormKey,
        'target_section' => 'sales-invoices',
        'form_title' => 'Finance Builder Test',
        'form_description' => 'Create path',
        'form_status' => 'DRAFT',
        'schema_json' => $firstSchema,
    ]);
    $updated = yovel_admin_persist_finance_builder_form($db, $fixtureCompany, $fixtureAdmin, [
        'builder_form_key' => $fixtureFormKey,
        'target_section' => 'sales-invoices',
        'form_title' => 'Finance Builder Test Updated',
        'form_description' => 'Update path',
        'form_status' => 'ACTIVE',
        'schema_json' => $secondSchema,
    ]);

    finance_builder_assert((string) ($created['builder_form_key'] ?? '') === $fixtureFormKey, 'Finance custom form create changed the stable key.');
    finance_builder_assert((string) ($updated['builder_form_key'] ?? '') === $fixtureFormKey, 'Finance custom form update changed the stable key.');
    finance_builder_assert((string) ($updated['form_title'] ?? '') === 'Finance Builder Test Updated', 'Finance custom form update did not read back the title.');
    finance_builder_assert((int) ($updated['question_count'] ?? 0) === 2, 'Finance custom form update did not read back the question count.');
    $versionCount = (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_finance_builder_form_version WHERE builder_form_key = ?',
        [$fixtureFormKey]
    );
    finance_builder_assert($versionCount === 2, 'Finance custom form create/update did not produce two immutable versions.');
} finally {
    $db->Execute('DELETE FROM project_company_finance_builder_form_version WHERE builder_form_key = ?', [$fixtureFormKey]);
    $db->Execute('DELETE FROM project_company_finance_builder_form WHERE builder_form_key = ?', [$fixtureFormKey]);
}

echo "Accounting/Finance form builder checks passed.\n";
