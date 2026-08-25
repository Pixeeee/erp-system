<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/forms.php';
require_once $root . '/company/admin/modules/accounting-finance/grid.php';

function finance_grid_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

yovel_admin_finance_grid_schema();
$db = bx_db();
foreach ([
    'project_company_finance_grid_view',
    'project_company_finance_grid_column',
    'project_company_finance_grid_formula',
] as $table) {
    $exists = (int) $db->GetOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    finance_grid_assert($exists === 1, 'Missing Finance grid table: ' . $table);
}

$allowedFields = ['tax_rate', 'sort_order'];
$doubleAst = yovel_admin_finance_formula_parse('[tax_rate] * 2', $allowedFields);
$doubleResult = yovel_admin_finance_formula_evaluate($doubleAst, ['tax_rate' => 6, 'sort_order' => 10]);
finance_grid_assert(($doubleResult['ok'] ?? false) === true && (float) $doubleResult['value'] === 12.0, 'Multiplication formula returned the wrong value.');

$ifAst = yovel_admin_finance_formula_parse('IF([tax_rate] > 5, [tax_rate], 0)', $allowedFields);
$ifResult = yovel_admin_finance_formula_evaluate($ifAst, ['tax_rate' => 6, 'sort_order' => 10]);
finance_grid_assert(($ifResult['ok'] ?? false) === true && (float) $ifResult['value'] === 6.0, 'IF formula returned the wrong branch.');

$divideAst = yovel_admin_finance_formula_parse('[sort_order] / 0', $allowedFields);
$divideResult = yovel_admin_finance_formula_evaluate($divideAst, ['tax_rate' => 6, 'sort_order' => 10]);
finance_grid_assert(($divideResult['ok'] ?? true) === false && ($divideResult['error'] ?? '') === 'DIVIDE_BY_ZERO', 'Division by zero did not return a scoped formula error.');

$unknownRejected = false;
try {
    yovel_admin_finance_formula_parse('[unknown] + 1', $allowedFields);
} catch (InvalidArgumentException $error) {
    $unknownRejected = $error->getMessage() === 'UNKNOWN_FIELD';
}
finance_grid_assert($unknownRejected, 'An unknown Finance formula field was accepted.');

$unsafeRejected = false;
try {
    yovel_admin_finance_formula_parse('SYSTEM([tax_rate])', $allowedFields);
} catch (InvalidArgumentException) {
    $unsafeRejected = true;
}
finance_grid_assert($unsafeRejected, 'An unapproved Finance formula function was accepted.');

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
finance_grid_assert(is_array($fixture) && $fixture !== [], 'An active company-admin fixture is required.');
$fixtureCompany = [
    'company_key' => (string) $fixture['company_key'],
    'company_key_hash' => (string) $fixture['company_key_hash'],
    'company_name' => (string) $fixture['company_name'],
];
$fixtureAdmin = ['admin_key' => (string) $fixture['admin_key']];
$viewKey = bx_uuid();
$formulaKey = bx_uuid();
$importSuffix = strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 10));
$importParentCode = 'TST_PARENT_' . $importSuffix;
$importChildCode = 'TST_CHILD_' . $importSuffix;
$invalidCode = 'TST_INVALID_' . $importSuffix;

try {
    $createdView = yovel_admin_persist_finance_grid_view($db, $fixtureCompany, $fixtureAdmin, [
        'grid_view_key' => $viewKey,
        'section_key' => 'chart-of-accounts',
        'view_title' => 'Finance Grid Test',
        'search_text' => 'asset',
        'sort' => [['column' => 'account_name', 'direction' => 'ASC']],
        'filters' => ['root_type' => 'ASSET'],
        'group_field' => 'root_type',
        'is_default' => false,
        'columns' => [
            ['key' => 'account_name', 'visible' => true, 'width' => 240, 'order' => 10],
            ['key' => 'root_type', 'visible' => true, 'width' => 120, 'order' => 20],
        ],
        'allowed_columns' => ['account_name', 'root_type'],
    ]);
    $updatedView = yovel_admin_persist_finance_grid_view($db, $fixtureCompany, $fixtureAdmin, [
        'grid_view_key' => $viewKey,
        'section_key' => 'chart-of-accounts',
        'view_title' => 'Finance Grid Test Updated',
        'search_text' => 'income',
        'sort' => [['column' => 'root_type', 'direction' => 'DESC']],
        'filters' => ['root_type' => 'INCOME'],
        'group_field' => 'root_type',
        'is_default' => true,
        'columns' => [
            ['key' => 'root_type', 'visible' => true, 'width' => 140, 'order' => 10],
            ['key' => 'account_name', 'visible' => false, 'width' => 260, 'order' => 20],
        ],
        'allowed_columns' => ['account_name', 'root_type'],
    ]);
    finance_grid_assert((string) ($createdView['grid_view_key'] ?? '') === $viewKey, 'Saved view create changed the stable key.');
    finance_grid_assert((string) ($updatedView['view_title'] ?? '') === 'Finance Grid Test Updated', 'Saved view update did not read back the title.');
    finance_grid_assert((string) ($updatedView['search_text'] ?? '') === 'income', 'Saved view update did not read back search text.');
    finance_grid_assert(count($updatedView['columns'] ?? []) === 2, 'Saved view columns were not read back.');

    $savedFormula = yovel_admin_persist_finance_grid_formula($db, $fixtureCompany, $fixtureAdmin, [
        'grid_formula_key' => $formulaKey,
        'grid_view_key' => $viewKey,
        'section_key' => 'chart-of-accounts',
        'formula_code' => 'double_tax',
        'formula_label' => 'Double tax',
        'formula_expression' => '[tax_rate] * 2',
        'output_format' => 'PERCENT',
        'decimal_precision' => 2,
        'sort_order' => 10,
        'allowed_fields' => $allowedFields,
    ]);
    finance_grid_assert((string) ($savedFormula['formula_expression'] ?? '') === '[tax_rate] * 2', 'Saved formula expression was not read back.');

    $_POST['rows_json'] = json_encode([
        ['account_code' => $importParentCode, 'account_name' => 'Import parent', 'root_type' => 'ASSET', 'is_group' => 'yes', 'account_currency' => 'PHP'],
        ['account_code' => $importChildCode, 'account_name' => 'Import child', 'root_type' => 'ASSET', 'parent_account_code' => $importParentCode, 'tax_rate' => '12'],
    ], JSON_THROW_ON_ERROR);
    $importMessage = yovel_admin_import_finance_accounts($fixtureCompany, $fixtureAdmin);
    finance_grid_assert($importMessage === '2 Finance accounts imported.', 'Finance import returned the wrong result message.');
    $imported = $db->GetAll(
        'SELECT account_code, parent_account_key, is_group FROM project_company_accounting_account WHERE company_key_hash = ? AND account_code IN (?, ?) ORDER BY account_code',
        [$fixtureCompany['company_key_hash'], $importParentCode, $importChildCode]
    );
    finance_grid_assert(is_array($imported) && count($imported) === 2, 'Finance import did not read back both rows.');
    $parentKey = (string) $db->GetOne(
        'SELECT account_key FROM project_company_accounting_account WHERE company_key_hash = ? AND account_code = ? LIMIT 1',
        [$fixtureCompany['company_key_hash'], $importParentCode]
    );
    $childParentKey = (string) $db->GetOne(
        'SELECT parent_account_key FROM project_company_accounting_account WHERE company_key_hash = ? AND account_code = ? LIMIT 1',
        [$fixtureCompany['company_key_hash'], $importChildCode]
    );
    finance_grid_assert($parentKey !== '' && $childParentKey === $parentKey, 'Finance import did not preserve the parent relationship.');

    $_POST['rows_json'] = json_encode([
        ['account_code' => $invalidCode, 'account_name' => 'Invalid import', 'root_type' => 'ASSET', 'parent_account_code' => 'MISSING_PARENT'],
    ], JSON_THROW_ON_ERROR);
    $invalidImportRejected = false;
    try {
        yovel_admin_import_finance_accounts($fixtureCompany, $fixtureAdmin);
    } catch (InvalidArgumentException) {
        $invalidImportRejected = true;
    }
    finance_grid_assert($invalidImportRejected, 'Finance import accepted an invalid parent account.');
    $invalidCount = (int) $db->GetOne(
        'SELECT COUNT(*) FROM project_company_accounting_account WHERE company_key_hash = ? AND account_code = ?',
        [$fixtureCompany['company_key_hash'], $invalidCode]
    );
    finance_grid_assert($invalidCount === 0, 'Rejected Finance import wrote a partial account row.');
} finally {
    unset($_POST['rows_json']);
    $db->Execute(
        'DELETE FROM project_company_accounting_account WHERE company_key_hash = ? AND account_code IN (?, ?, ?)',
        [$fixtureCompany['company_key_hash'], $importParentCode, $importChildCode, $invalidCode]
    );
    $db->Execute('DELETE FROM project_company_finance_grid_formula WHERE grid_formula_key = ?', [$formulaKey]);
    $db->Execute('DELETE FROM project_company_finance_grid_column WHERE grid_view_key = ?', [$viewKey]);
    $db->Execute('DELETE FROM project_company_finance_grid_view WHERE grid_view_key = ?', [$viewKey]);
}

echo "Accounting/Finance spreadsheet operation checks passed.\n";
