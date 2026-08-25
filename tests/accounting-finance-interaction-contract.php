<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/accounting-finance/functions.php';

function finance_interaction_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$viewFiles = glob($root . '/company/admin/modules/accounting-finance/views/*.php') ?: [];
$allMarkup = '';
foreach ($viewFiles as $viewFile) {
    $allMarkup .= (string) file_get_contents($viewFile) . "\n";
}

finance_interaction_assert(!str_contains($allMarkup, 'data-finance-modal-open='), 'Finance still duplicates the shared modal opener contract.');
finance_interaction_assert(!preg_match('/\sdata-finance-modal(?:\s|>)/', $allMarkup), 'Finance still duplicates the shared record-modal root contract.');
finance_interaction_assert(substr_count($allMarkup, 'data-record-modal-open=') >= 12, 'Every Finance Add/New/Create/Insert entry point is not mapped to a shared record modal.');
finance_interaction_assert(substr_count($allMarkup, 'data-record-modal') >= 12, 'Finance record modal coverage is incomplete.');
finance_interaction_assert(substr_count($allMarkup, 'data-confirm-submit') >= 20, 'Finance consequential forms do not consistently require confirmation.');
finance_interaction_assert(str_contains($allMarkup, 'data-record-modal-open-on-load'), 'Finance has no server-rendered validation rehydration marker.');
finance_interaction_assert(str_contains($allMarkup, 'data-record-modal-open="yovel-finance-builder-modal"'), 'The Finance dashboard New Form entry does not target the shared record modal.');
finance_interaction_assert(str_contains($allMarkup, ':not([aria-labelledby]):not([aria-label])'), 'Finance dialogs without explicit titles do not receive an accessible fallback label.');

$sharedController = (string) file_get_contents($root . '/company/admin/assets/js/admin-modal.js');
finance_interaction_assert(str_contains($sharedController, 'event.preventDefault()'), 'Shared confirmation does not stop POST before Confirm.');
finance_interaction_assert(substr_count($sharedController, 'requestSubmit()') === 1, 'Shared confirmation does not expose one guarded submit path.');
finance_interaction_assert(str_contains($sharedController, 'pendingSubmitter?.focus()'), 'Shared confirmation Cancel does not restore submitter focus.');

finance_interaction_assert(function_exists('yovel_admin_finance_capture_form_state'), 'Finance validation rehydration capture is missing.');
finance_interaction_assert(function_exists('yovel_admin_finance_take_form_state'), 'Finance validation rehydration read is missing.');

$company = ['company_key_hash' => hash('sha256', 'finance-interaction-contract')];
$_SESSION['builderx_finance_form_state'] = [];
yovel_admin_finance_capture_form_state($company, 'save_finance_budget', 'finance-budget-modal', [
    'csrf' => 'must-not-survive',
    'section' => 'budgets',
    'budget_code' => 'BUD-RETAINED',
    'lines_json' => '[{"amount":"500"}]',
]);
$state = yovel_admin_finance_take_form_state($company);
finance_interaction_assert(($state['action'] ?? '') === 'save_finance_budget', 'Finance rehydration lost the failed action.');
finance_interaction_assert(($state['modal_id'] ?? '') === 'finance-budget-modal', 'Finance rehydration lost the modal identity.');
finance_interaction_assert(($state['values']['budget_code'] ?? '') === 'BUD-RETAINED', 'Finance rehydration lost a valid submitted value.');
finance_interaction_assert(!isset($state['values']['csrf']), 'Finance rehydration retained the CSRF token.');
finance_interaction_assert(yovel_admin_finance_take_form_state($company) === [], 'Finance rehydration state was not one-request consumable.');

$_POST = ['section' => 'journal-entries', 'journal_no' => 'JV-RETAINED'];
$failed = false;
try {
    yovel_admin_finance_run_form_action($company, 'save_finance_journal', static function (): never {
        throw new InvalidArgumentException('Expected validation failure.');
    });
} catch (InvalidArgumentException) {
    $failed = true;
}
finance_interaction_assert($failed, 'Finance form action did not propagate server validation failure.');
$state = yovel_admin_finance_take_form_state($company);
finance_interaction_assert(($state['modal_id'] ?? '') === 'finance-journal-modal', 'A failed Finance action did not retain its modal identity.');
finance_interaction_assert(($state['values']['journal_no'] ?? '') === 'JV-RETAINED', 'A failed Finance action did not retain submitted values.');

$_POST = ['section' => 'journal-entries', 'journal_no' => 'JV-CLEARED'];
$result = yovel_admin_finance_run_form_action($company, 'save_finance_journal', static fn (): string => 'saved');
finance_interaction_assert($result === 'saved', 'Finance form action changed the successful operation result.');
finance_interaction_assert(yovel_admin_finance_take_form_state($company) === [], 'A successful Finance action did not clear temporary rehydration state.');

$actionSources = '';
foreach (['core.php', 'journals.php', 'invoices.php', 'payments.php', 'banking.php', 'planning.php', 'grid.php'] as $sourceFile) {
    $actionSources .= (string) file_get_contents($root . '/company/admin/modules/accounting-finance/' . $sourceFile);
}
foreach ([
    'save_finance_settings', 'save_finance_master', 'save_finance_journal', 'submit_finance_journal',
    'cancel_finance_journal', 'save_finance_invoice', 'save_finance_supplier', 'submit_finance_invoice',
    'cancel_finance_invoice', 'save_finance_payment', 'submit_finance_payment', 'cancel_finance_payment',
    'import_finance_bank_statement', 'reconcile_finance_bank', 'unreconcile_finance_bank',
    'save_finance_budget', 'close_finance_period', 'reopen_finance_period', 'save_finance_grid_view',
    'save_finance_grid_formula', 'import_finance_accounts',
] as $action) {
    finance_interaction_assert(
        str_contains($actionSources, "yovel_admin_finance_run_form_action(\$company, '" . $action . "'")
            || str_contains($actionSources, "yovel_admin_finance_run_form_action(\$company,'" . $action . "'"),
        'Finance action is missing validation rehydration coverage: ' . $action
    );
}

echo "Accounting/Finance interaction contract checks passed.\n";
