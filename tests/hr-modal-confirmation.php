<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function hr_modal_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hr_modal_source(string $path): string
{
    hr_modal_assert(is_file($path), 'Required modal contract file is missing: ' . $path);
    $source = file_get_contents($path);
    hr_modal_assert(is_string($source), 'Unable to read modal contract file: ' . $path);
    return $source;
}

$recordModal = [
    'id' => 'hr-contract-modal',
    'title' => 'Add HR Record',
    'description' => 'HR confirmation contract fixture.',
    'open_label' => 'Add HR Record',
    'submit_label' => 'Save HR Record',
    'body_html' => '<label for="hr_contract_name">Name</label><input id="hr_contract_name" name="name" value="Retained value" required>',
    'hidden_html' => '<input type="hidden" name="action" value="save_hr_contract">',
];
$companyName = 'HR Contract Company';

ob_start();
require $root . '/company/admin/views/partials/record-modal.php';
require $root . '/company/admin/views/partials/confirm-dialog.php';
$markup = (string) ob_get_clean();

foreach ([
    'data-record-modal-open="hr-contract-modal"',
    'data-record-modal',
    'role="dialog"',
    'aria-modal="true"',
    'data-confirm-submit',
    'data-confirm-dialog',
    'data-confirm-cancel',
    'data-confirm-action',
] as $marker) {
    hr_modal_assert(str_contains($markup, $marker), 'Shared HR modal contract is missing: ' . $marker);
}
hr_modal_assert(strpos($markup, 'data-confirm-dialog') > strpos($markup, '</form>'), 'The confirmation dialog is nested inside the record form.');
hr_modal_assert(str_contains($markup, 'value="Retained value"'), 'The record modal did not rehydrate its server value.');

$controller = hr_modal_source($root . '/company/admin/assets/js/admin-modal.js');
foreach ([
    "if (!form.checkValidity()) return;",
    'event.preventDefault();',
    "if (!pendingForm || confirmed) return;",
    "form.dataset.confirmed = 'true';",
    'form.requestSubmit();',
    'closeConfirmation(true);',
    'pendingSubmitter?.focus();',
] as $marker) {
    hr_modal_assert(str_contains($controller, $marker), 'Shared confirmation behavior changed: ' . $marker);
}
hr_modal_assert(substr_count($controller, 'form.requestSubmit();') === 1, 'Confirm does not have exactly one guarded submit path.');
hr_modal_assert(!str_contains($controller, '.reset()'), 'Cancel or validation can clear populated HR form values.');
hr_modal_assert(strpos($controller, 'event.preventDefault();') < strpos($controller, 'form.requestSubmit();'), 'Persistence can begin before confirmation.');

$recordViews = [
    'employee-profiles.php' => 'yovel-employee-modal',
    'departments.php' => 'yovel-department-modal',
    'job-positions.php' => 'yovel-job-position-modal',
    'teams.php' => 'yovel-team-modal',
];
foreach ($recordViews as $view => $modalId) {
    $source = hr_modal_source($root . '/company/admin/modules/hr/views/' . $view);
    hr_modal_assert(str_contains($source, 'data-confirm-submit'), $view . ' bypasses the shared confirmation boundary.');
    hr_modal_assert(str_contains($source, 'data-record-modal-open="' . $modalId . '"'), $view . ' does not use the shared record-modal opener contract.');
    hr_modal_assert(str_contains($source, 'id="' . $modalId . '" data-record-modal'), $view . ' does not identify its dialog through the shared record-modal contract.');
    hr_modal_assert(str_contains($source, 'data-record-modal-open-on-load'), $view . ' does not expose its edit-on-load state to the shared controller.');
    hr_modal_assert(substr_count($source, 'data-record-modal-close') >= 2, $view . ' close and cancel commands do not both use the shared close contract.');
}

$dashboard = hr_modal_source($root . '/company/admin/modules/hr/views/dashboard.php');
hr_modal_assert(str_contains($dashboard, 'data-confirm-submit'), 'dashboard.php bypasses the shared confirmation boundary.');

$setup = hr_modal_source($root . '/company/admin/modules/hr/views/setup.php');
foreach ([
    'data-record-modal-open="hr-settings-modal"',
    'data-record-modal-open="hr-setup-master-modal"',
    'data-record-modal-open="hr-transfer-modal"',
    'data-record-modal-open="hr-promotion-modal"',
    'data-record-modal-open="hr-lifecycle-modal"',
    'id="hr-settings-modal" data-record-modal',
    'id="hr-setup-master-modal" data-record-modal',
    'id="hr-transfer-modal" data-record-modal',
    'id="hr-promotion-modal" data-record-modal',
    'id="hr-lifecycle-modal" data-record-modal',
    'name="action" value="hr_setup_save_record"',
    'name="action" value="hr_setup_transfer"',
    'name="action" value="hr_setup_promotion"',
] as $marker) {
    hr_modal_assert(str_contains($setup, $marker), 'HR Setup modal contract is missing: ' . $marker);
}
hr_modal_assert(substr_count($setup, 'data-confirm-submit') === 5, 'Every HR Setup body form must have one confirmation boundary.');
hr_modal_assert(!str_contains($setup, 'data-confirm-dialog'), 'HR Setup copied or nested the shared confirmation dialog.');

$attendance = hr_modal_source($root . '/company/admin/modules/hr/views/attendance.php');
foreach ([
    'data-record-modal-open="hr-checkin-modal"',
    'data-record-modal-open="hr-attendance-modal"',
    'data-record-modal-open="hr-attendance-request-modal"',
    'data-record-modal-open="hr-attendance-tool-modal"',
    'data-record-modal-open="hr-shift-assignment-modal"',
    'data-record-modal-open="hr-shift-assignment-tool-modal"',
    'data-record-modal-open="hr-shift-request-modal"',
    'data-record-modal-open="hr-shift-schedule-modal"',
    'data-record-modal-open="hr-overtime-modal"',
    'id="hr-checkin-modal" data-record-modal',
    'id="hr-attendance-modal" data-record-modal',
    'id="hr-attendance-request-modal" data-record-modal',
    'id="hr-attendance-tool-modal" data-record-modal',
    'id="hr-shift-assignment-modal" data-record-modal',
    'id="hr-shift-assignment-tool-modal" data-record-modal',
    'id="hr-shift-request-modal" data-record-modal',
    'id="hr-shift-schedule-modal" data-record-modal',
    'id="hr-overtime-modal" data-record-modal',
    "\$modalHidden('hr_attendance_save_checkin')",
    "\$modalHidden('hr_attendance_mark')",
    "\$modalHidden('hr_attendance_save_request')",
    "\$modalHidden('hr_attendance_bulk_mark')",
    "\$modalHidden('hr_attendance_save_assignment')",
    "\$modalHidden('hr_attendance_bulk_assignment')",
    "\$modalHidden('hr_attendance_save_shift_request')",
    "\$modalHidden('hr_attendance_save_overtime')",
] as $marker) {
    hr_modal_assert(str_contains($attendance, $marker), 'Shift & Attendance modal contract is missing: ' . $marker);
}
hr_modal_assert(substr_count($attendance, 'data-confirm-submit') >= 12, 'Every Shift & Attendance action must have one confirmation boundary.');
hr_modal_assert(!str_contains($attendance, 'data-confirm-dialog'), 'Shift & Attendance copied or nested the shared confirmation dialog.');

echo "HR modal confirmation contract checks passed.\n";
