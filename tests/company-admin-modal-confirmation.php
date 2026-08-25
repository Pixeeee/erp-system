<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function modal_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$recordModal = [
    'id' => 'fixture-create-modal',
    'title' => 'Create Fixture',
    'description' => 'Fixture record modal.',
    'open_label' => 'Add Fixture',
    'submit_label' => 'Submit Fixture',
    'body_html' => '<label>Fixture name<input name="fixture_name" value="Retained value" required></label>',
    'hidden_html' => '<input type="hidden" name="action" value="save_fixture">',
];
$companyName = 'Contract Company';

ob_start();
require $root . '/company/admin/views/partials/record-modal.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();

modal_contract_assert(str_contains($markup, 'data-record-modal'), 'Record modal contract marker is missing.');
modal_contract_assert(str_contains($markup, 'data-record-modal-open="fixture-create-modal"'), 'Add command does not open the record modal by stable id.');
modal_contract_assert(str_contains($markup, 'data-confirm-submit'), 'Record form does not require confirmation.');
modal_contract_assert(str_contains($markup, 'data-confirm-dialog'), 'Body-owned confirmation dialog marker is missing.');
modal_contract_assert(strpos($markup, 'data-confirm-dialog') > strpos($markup, '</form>'), 'Confirmation dialog is nested inside the record form.');

$scriptPath = $root . '/company/admin/assets/js/admin-modal.js';
modal_contract_assert(is_file($scriptPath), 'Shared modal controller is missing.');
$script = (string) file_get_contents($scriptPath);
foreach (['data-confirm-submit', 'data-confirm-dialog', 'requestSubmit()', 'confirmed', 'inert', 'focus()'] as $marker) {
    modal_contract_assert(str_contains($script, $marker), 'Shared modal controller is missing behavior marker: ' . $marker);
}
modal_contract_assert(str_contains($script, "event.preventDefault()"), 'Submit is not stopped before confirmation.');
modal_contract_assert(substr_count($script, 'requestSubmit()') === 1, 'Shared modal controller does not have one guarded submit path.');

echo "Company admin modal confirmation checks passed.\n";
