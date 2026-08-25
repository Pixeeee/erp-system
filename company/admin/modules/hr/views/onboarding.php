<?php
declare(strict_types=1);

$onboardingData = is_array($hrData['onboarding'] ?? null) ? $hrData['onboarding'] : [];
$onboardingEmployees = is_array($hrData['employees'] ?? null) ? $hrData['employees'] : [];
$templates = is_array($onboardingData['templates'] ?? null) ? $onboardingData['templates'] : [];
$onboardings = is_array($onboardingData['onboardings'] ?? null) ? $onboardingData['onboardings'] : [];
$separations = is_array($onboardingData['separations'] ?? null) ? $onboardingData['separations'] : [];
$activities = is_array($onboardingData['activities'] ?? null) ? $onboardingData['activities'] : [];
$documents = is_array($onboardingData['documents'] ?? null) ? $onboardingData['documents'] : [];
$documentTypes = is_array($onboardingData['documentTypes'] ?? null) ? $onboardingData['documentTypes'] : [];
$exitInterviews = is_array($onboardingData['exitInterviews'] ?? null) ? $onboardingData['exitInterviews'] : [];
$finalStatements = is_array($onboardingData['fullFinalStatements'] ?? null) ? $onboardingData['fullFinalStatements'] : [];
$dependencies = is_array($onboardingData['dependencies'] ?? null) ? $onboardingData['dependencies'] : [];
$state = is_array($activeModuleFormState ?? null) && str_starts_with((string) ($activeModuleFormState['action'] ?? ''), 'hr_onboarding_') ? $activeModuleFormState : [];
$old = is_array($state['input'] ?? null) ? $state['input'] : [];
$mode = yovel_admin_slug((string) ($_GET['onboarding_mode'] ?? ($activeHrSection === 'employee-documents' ? 'documents' : 'onboarding')));
if (!in_array($mode, ['onboarding', 'separation', 'documents'], true)) { $mode = 'onboarding'; }
$onboardingInput = 'mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-sm';
$onboardingTextarea = 'mt-1 min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm';
$modalClass = 'fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-3 backdrop-blur-sm sm:p-6';
$dialogClass = 'flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-3xl flex-col overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-xl';
$selectedTemplates = static fn (string $type): array => array_values(array_filter($templates, static fn (array $row): bool => (string) ($row['template_type'] ?? '') === $type && (string) ($row['template_status'] ?? '') === 'ACTIVE'));
$open = static fn (string $action): bool => (string) ($state['action'] ?? '') === $action;
$oldValue = static fn (string $key, mixed $default = ''): mixed => $old[$key] ?? $default;
$options = static function (array $rows, string $valueKey, string $labelKey, string $selected = ''): void {
    foreach ($rows as $row) {
        $value = (string) ($row[$valueKey] ?? '');
        if ($value === '') { continue; }
        ?><option value="<?= bx_h($value) ?>" <?= hash_equals($selected, $value) ? 'selected' : '' ?>><?= bx_h((string) ($row[$labelKey] ?? $value)) ?></option><?php
    }
};
$hidden = static function (string $action, string $section = 'onboarding') use ($mode): void { ?>
    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= bx_h($action) ?>">
    <input type="hidden" name="module_view" value="hr">
    <input type="hidden" name="section" value="<?= bx_h($section) ?>">
    <input type="hidden" name="onboarding_mode" value="<?= bx_h($mode) ?>">
<?php };
$header = static function (string $id, string $title, string $description): void { ?>
    <header class="border-b p-5"><div class="flex items-start justify-between gap-4"><div><h2 id="<?= bx_h($id) ?>" class="text-lg font-semibold"><?= bx_h($title) ?></h2><p class="mt-1 text-sm text-muted-foreground"><?= bx_h($description) ?></p></div><button type="button" class="rounded-md border px-3 py-2 text-sm" data-record-modal-close aria-label="Close">Close</button></div></header>
<?php };
$actions = static function (string $label): void { ?><footer class="mt-5 flex items-center justify-between border-t pt-4"><p class="text-xs text-muted-foreground">Confirmation opens before HR saves anything.</p><button class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground" type="submit"><?= bx_h($label) ?></button></footer><?php };
$renderModal = static function (string $id, string $title, string $description, string $action, callable $body, string $section = 'onboarding') use ($modalClass, $dialogClass, $header, $hidden, $actions, $open): void { ?>
    <div id="<?= bx_h($id) ?>" data-record-modal <?= $open($action) ? 'data-record-modal-open-on-load' : '' ?> class="<?= $modalClass ?>" role="dialog" aria-modal="true" aria-labelledby="<?= bx_h($id) ?>-title" hidden>
        <section class="<?= $dialogClass ?>">
            <?php $header($id . '-title', $title, $description); ?>
            <form method="post" data-confirm-submit class="min-h-0 overflow-y-auto p-5">
                <?php $hidden($action, $section); ?>
                <?php $body(); ?>
                <?php $actions(str_starts_with($title, 'Complete') ? $title : 'Save ' . $title); ?>
            </form>
        </section>
    </div>
<?php };
?>
<style>
    .yovel-hr-onboarding-layout > .yovel-hr-panel { min-width: 0; }
    @media (min-width: 1280px) { .yovel-hr-onboarding-layout { grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr); } }
</style>

<?php
$renderModal('hr-document-type-modal', 'Document Type', 'Define employee identification document requirements.', 'hr_onboarding_save_document_type', function () use ($onboardingInput, $oldValue): void { ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Code<input name="document_type_code" value="<?= bx_h((string) $oldValue('document_type_code')) ?>" class="<?= $onboardingInput ?>" required></label><label class="text-sm font-medium">Name<input name="document_type_name" value="<?= bx_h((string) $oldValue('document_type_name')) ?>" class="<?= $onboardingInput ?>" required></label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_expiry_date" value="1">Requires expiry date</label><input type="hidden" name="document_type_status" value="ACTIVE"></div>
<?php }, 'employee-documents');
$renderModal('hr-boarding-template-modal', 'Boarding Template', 'Create an onboarding or separation checklist template.', 'hr_onboarding_save_template', function () use ($onboardingInput, $onboardingTextarea): void { ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Type<select name="template_type" class="<?= $onboardingInput ?>"><option value="ONBOARDING">Onboarding</option><option value="SEPARATION">Separation</option></select></label><label class="text-sm font-medium">Code<input name="template_code" class="<?= $onboardingInput ?>" required></label><label class="text-sm font-medium sm:col-span-2">Name<input name="template_name" class="<?= $onboardingInput ?>" required></label><label class="text-sm font-medium sm:col-span-2">Description<textarea name="description" class="<?= $onboardingTextarea ?>"></textarea></label><label class="text-sm font-medium sm:col-span-2">Activities JSON<textarea name="activities_json" class="<?= $onboardingTextarea ?>" required>[{"activity_name":"Prepare documents","due_days_offset":1,"sort_order":10}]</textarea></label><input type="hidden" name="template_status" value="ACTIVE"></div>
<?php });
$renderModal('hr-onboarding-modal', 'Employee Onboarding', 'Start a checklist for a new hire.', 'hr_onboarding_start', function () use ($onboardingInput, $onboardingEmployees, $selectedTemplates, $options, $oldValue): void { ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium sm:col-span-2">Employee<select name="employee_key" class="<?= $onboardingInput ?>" required><option value="">Select employee</option><?php $options($onboardingEmployees, 'employee_key', 'employee_name', (string) $oldValue('employee_key')); ?></select></label><label class="text-sm font-medium">Template<select name="boarding_template_key" class="<?= $onboardingInput ?>"><option value="">No template</option><?php $options($selectedTemplates('ONBOARDING'), 'boarding_template_key', 'template_name'); ?></select></label><label class="text-sm font-medium">Status<select name="onboarding_status" class="<?= $onboardingInput ?>"><option value="IN_PROGRESS">In Progress</option><option value="DRAFT">Draft</option></select></label><label class="text-sm font-medium">Start date<input type="date" name="start_date" value="<?= date('Y-m-d') ?>" class="<?= $onboardingInput ?>" required></label><label class="text-sm font-medium">Expected completion<input type="date" name="expected_completion_date" class="<?= $onboardingInput ?>"></label></div>
<?php });
$renderModal('hr-activity-modal', 'Complete Activity', 'Close an onboarding or separation activity.', 'hr_onboarding_complete_activity', function () use ($onboardingInput, $onboardingTextarea, $activities, $options): void { ?>
    <label class="text-sm font-medium">Activity<select name="employee_boarding_activity_key" class="<?= $onboardingInput ?>" required><option value="">Select open activity</option><?php $options(array_values(array_filter($activities, static fn (array $row): bool => (string) $row['activity_status'] !== 'COMPLETED')), 'employee_boarding_activity_key', 'activity_name'); ?></select></label><label class="mt-4 block text-sm font-medium">Completion notes<textarea name="completion_notes" class="<?= $onboardingTextarea ?>"></textarea></label>
<?php });
$renderModal('hr-document-modal', 'Employee Document', 'Track required documents against employee onboarding or separation.', 'hr_onboarding_save_document', function () use ($onboardingInput, $onboardingTextarea, $onboardingEmployees, $documentTypes, $onboardings, $options): void { ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Employee<select name="employee_key" class="<?= $onboardingInput ?>" required><option value="">Select employee</option><?php $options($onboardingEmployees, 'employee_key', 'employee_name'); ?></select></label><label class="text-sm font-medium">Document type<select name="identification_document_type_key" class="<?= $onboardingInput ?>" required><option value="">Select document type</option><?php $options($documentTypes, 'identification_document_type_key', 'document_type_name'); ?></select></label><label class="text-sm font-medium">Context<select name="context_type" class="<?= $onboardingInput ?>"><option value="EMPLOYEE">Employee</option><option value="ONBOARDING">Onboarding</option><option value="SEPARATION">Separation</option></select></label><label class="text-sm font-medium">Onboarding<select name="context_key" class="<?= $onboardingInput ?>"><option value="">Optional context</option><?php $options($onboardings, 'employee_onboarding_key', 'employee_name'); ?></select></label><label class="text-sm font-medium">Status<select name="requirement_status" class="<?= $onboardingInput ?>"><option value="DRAFT">Draft</option><option value="SUBMITTED">Submitted</option><option value="VERIFIED">Verified</option><option value="REJECTED">Rejected</option></select></label><label class="text-sm font-medium">Received at<input name="received_at" placeholder="YYYY-MM-DD HH:MM:SS" class="<?= $onboardingInput ?>"></label><label class="text-sm font-medium">Expiry date<input type="date" name="expiry_date" class="<?= $onboardingInput ?>"></label><label class="text-sm font-medium sm:col-span-2">Notes<textarea name="verification_notes" class="<?= $onboardingTextarea ?>"></textarea></label></div>
<?php }, 'employee-documents');
$renderModal('hr-complete-onboarding-modal', 'Complete Onboarding', 'Complete only after all activities and document checks are clear.', 'hr_onboarding_complete', function () use ($onboardingInput, $onboardings, $options): void { ?>
    <label class="text-sm font-medium">Onboarding<select name="employee_onboarding_key" class="<?= $onboardingInput ?>" required><option value="">Select onboarding</option><?php $options(array_values(array_filter($onboardings, static fn (array $row): bool => (string) $row['onboarding_status'] !== 'COMPLETED')), 'employee_onboarding_key', 'employee_name'); ?></select></label>
<?php });
$renderModal('hr-separation-modal', 'Employee Separation', 'Start a governed separation and clearance workflow.', 'hr_onboarding_save_separation', function () use ($onboardingInput, $onboardingTextarea, $onboardingEmployees, $selectedTemplates, $options): void { ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium sm:col-span-2">Employee<select name="employee_key" class="<?= $onboardingInput ?>" required><option value="">Select employee</option><?php $options($onboardingEmployees, 'employee_key', 'employee_name'); ?></select></label><label class="text-sm font-medium">Template<select name="boarding_template_key" class="<?= $onboardingInput ?>"><option value="">No template</option><?php $options($selectedTemplates('SEPARATION'), 'boarding_template_key', 'template_name'); ?></select></label><label class="text-sm font-medium">Status<select name="separation_status" class="<?= $onboardingInput ?>"><option value="SUBMITTED">Submitted</option><option value="DRAFT">Draft</option></select></label><label class="text-sm font-medium">Resignation date<input type="date" name="resignation_date" value="<?= date('Y-m-d') ?>" class="<?= $onboardingInput ?>" required></label><label class="text-sm font-medium">Relieving date<input type="date" name="relieving_date" class="<?= $onboardingInput ?>" required></label><label class="text-sm font-medium sm:col-span-2">Reason<textarea name="reason" class="<?= $onboardingTextarea ?>"></textarea></label></div>
<?php });
$renderModal('hr-exit-interview-modal', 'Exit Interview', 'Schedule an exit interview and generate a notification intent.', 'hr_onboarding_schedule_exit', function () use ($onboardingInput, $onboardingTextarea, $separations, $onboardingEmployees, $options): void { ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Separation<select name="employee_separation_key" class="<?= $onboardingInput ?>" required><option value="">Select separation</option><?php $options($separations, 'employee_separation_key', 'employee_name'); ?></select></label><label class="text-sm font-medium">Interviewer<select name="interviewer_employee_key" class="<?= $onboardingInput ?>" required><option value="">Select interviewer</option><?php $options($onboardingEmployees, 'employee_key', 'employee_name'); ?></select></label><label class="text-sm font-medium">Scheduled at<input name="scheduled_at" value="<?= date('Y-m-d H:i:s') ?>" class="<?= $onboardingInput ?>" required></label><label class="text-sm font-medium">Time zone<input name="timezone_name" value="Asia/Manila" class="<?= $onboardingInput ?>" required></label><label class="text-sm font-medium sm:col-span-2">Questionnaire JSON<textarea name="questionnaire_json" class="<?= $onboardingTextarea ?>">{}</textarea></label></div>
<?php });
$renderModal('hr-exit-complete-modal', 'Complete Exit Interview', 'Record the final exit interview result.', 'hr_onboarding_transition_exit', function () use ($onboardingInput, $onboardingTextarea, $exitInterviews, $options): void { ?>
    <label class="text-sm font-medium">Exit interview<select name="exit_interview_key" class="<?= $onboardingInput ?>" required><option value="">Select interview</option><?php $options(array_values(array_filter($exitInterviews, static fn (array $row): bool => (string) $row['exit_interview_status'] === 'SCHEDULED')), 'exit_interview_key', 'scheduled_at'); ?></select></label><input type="hidden" name="exit_interview_status" value="COMPLETED"><label class="mt-4 block text-sm font-medium">Summary<textarea name="summary" class="<?= $onboardingTextarea ?>"></textarea></label>
<?php });
$renderModal('hr-full-final-modal', 'Full And Final', 'Track Assets and Finance clearance without writing owner tables.', 'hr_onboarding_save_final', function () use ($onboardingInput, $onboardingTextarea, $separations, $options): void { ?>
    <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium sm:col-span-2">Separation<select name="employee_separation_key" class="<?= $onboardingInput ?>" required><option value="">Select separation</option><?php $options($separations, 'employee_separation_key', 'employee_name'); ?></select></label><label class="text-sm font-medium">Asset clearance<select name="asset_clearance_status" class="<?= $onboardingInput ?>"><option value="PENDING">Pending</option><option value="CLEARED">Cleared</option><option value="BLOCKED">Blocked</option></select></label><label class="text-sm font-medium">Outstanding<select name="outstanding_status" class="<?= $onboardingInput ?>"><option value="PENDING">Pending</option><option value="CLEARED">Cleared</option><option value="BLOCKED">Blocked</option></select></label><label class="text-sm font-medium">Statement<select name="statement_status" class="<?= $onboardingInput ?>"><option value="DRAFT">Draft</option><option value="CLEARED">Cleared</option><option value="BLOCKED">Blocked</option></select></label><label class="text-sm font-medium sm:col-span-2">Assets JSON<textarea name="assets_json" class="<?= $onboardingTextarea ?>">[]</textarea></label><label class="text-sm font-medium sm:col-span-2">Outstandings JSON<textarea name="outstandings_json" class="<?= $onboardingTextarea ?>">[]</textarea></label></div>
<?php });
$renderModal('hr-separation-review-modal', 'Review Separation', 'Approve only after full-and-final clearance is complete.', 'hr_onboarding_transition_separation', function () use ($onboardingInput, $separations, $options): void { ?>
    <label class="text-sm font-medium">Separation<select name="employee_separation_key" class="<?= $onboardingInput ?>" required><option value="">Select separation</option><?php $options(array_values(array_filter($separations, static fn (array $row): bool => (string) $row['separation_status'] === 'SUBMITTED')), 'employee_separation_key', 'employee_name'); ?></select></label><label class="mt-4 block text-sm font-medium">Decision<select name="separation_status" class="<?= $onboardingInput ?>"><option value="APPROVED">Approve</option><option value="REJECTED">Reject</option><option value="CANCELLED">Cancel</option></select></label>
<?php });
?>

<div class="yovel-hr-onboarding-layout grid min-h-0 gap-4">
    <section class="yovel-hr-panel overflow-hidden rounded-lg border bg-card">
        <header class="border-b bg-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><h2 class="text-lg font-semibold"><?= $activeHrSection === 'employee-documents' ? 'Employee documents' : 'Onboarding' ?></h2><p class="mt-1 text-sm text-muted-foreground">Govern employee onboarding, separation, exit interviews, and document requirements.</p></div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-md border px-3 py-2 text-sm" data-record-modal-open="hr-onboarding-modal">Start Onboarding</button>
                    <button type="button" class="rounded-md border px-3 py-2 text-sm" data-record-modal-open="hr-separation-modal">Start Separation</button>
                    <button type="button" class="rounded-md border px-3 py-2 text-sm" data-record-modal-open="hr-document-modal">Add Document</button>
                </div>
            </div>
            <nav class="mt-4 flex flex-wrap gap-2">
                <a data-onboarding-mode="onboarding" class="rounded-md border px-3 py-2 text-sm <?= $mode === 'onboarding' ? 'bg-primary text-primary-foreground' : '' ?>" href="?view=hr&section=onboarding&onboarding_mode=onboarding">Onboarding</a>
                <a data-onboarding-mode="separation" class="rounded-md border px-3 py-2 text-sm <?= $mode === 'separation' ? 'bg-primary text-primary-foreground' : '' ?>" href="?view=hr&section=onboarding&onboarding_mode=separation">Separation</a>
                <a data-onboarding-mode="documents" class="rounded-md border px-3 py-2 text-sm <?= $mode === 'documents' ? 'bg-primary text-primary-foreground' : '' ?>" href="?view=hr&section=employee-documents&onboarding_mode=documents">Documents</a>
            </nav>
        </header>
        <div class="max-h-[calc(100dvh-17rem)] overflow-y-auto p-4">
            <?php if ($mode === 'documents'): ?>
                <div class="grid gap-3">
                    <?php foreach ($documents as $document): ?>
                        <article class="rounded-md border p-4"><div class="flex items-start justify-between gap-3"><div><h3 class="font-medium"><?= bx_h((string) $document['employee_name']) ?></h3><p class="text-sm text-muted-foreground"><?= bx_h((string) $document['document_type_name']) ?></p></div><span class="rounded-full bg-muted px-2 py-1 text-xs"><?= bx_h((string) $document['requirement_status']) ?></span></div></article>
                    <?php endforeach; ?>
                    <?php if ($documents === []): ?><p class="rounded-md bg-muted/40 p-4 text-sm text-muted-foreground">No employee document requirements yet.</p><?php endif; ?>
                </div>
            <?php elseif ($mode === 'separation'): ?>
                <div class="grid gap-3">
                    <?php foreach ($separations as $separation): ?>
                        <article class="rounded-md border p-4"><div class="flex items-start justify-between gap-3"><div><h3 class="font-medium"><?= bx_h((string) $separation['employee_name']) ?></h3><p class="text-sm text-muted-foreground">Relieving <?= bx_h((string) $separation['relieving_date']) ?></p></div><span class="rounded-full bg-muted px-2 py-1 text-xs"><?= bx_h((string) $separation['separation_status']) ?></span></div></article>
                    <?php endforeach; ?>
                    <?php if ($separations === []): ?><p class="rounded-md bg-muted/40 p-4 text-sm text-muted-foreground">No separation records yet.</p><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid gap-3">
                    <?php foreach ($onboardings as $onboarding): ?>
                        <article class="rounded-md border p-4"><div class="flex items-start justify-between gap-3"><div><h3 class="font-medium"><?= bx_h((string) $onboarding['employee_name']) ?></h3><p class="text-sm text-muted-foreground">Started <?= bx_h((string) $onboarding['start_date']) ?></p></div><span class="rounded-full bg-muted px-2 py-1 text-xs"><?= bx_h((string) $onboarding['onboarding_status']) ?></span></div></article>
                    <?php endforeach; ?>
                    <?php if ($onboardings === []): ?><p class="rounded-md bg-muted/40 p-4 text-sm text-muted-foreground">No onboarding records yet.</p><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <aside class="yovel-hr-panel overflow-hidden rounded-lg border bg-card">
        <header class="border-b bg-card p-5"><h2 class="text-lg font-semibold">Onboarding Tools</h2><p class="mt-1 text-sm text-muted-foreground">Checklists, documents, exit, and clearance actions.</p></header>
        <div class="max-h-[calc(100dvh-17rem)] overflow-y-auto p-4">
            <div class="grid gap-2">
                <?php foreach ([['hr-boarding-template-modal','assignment','Templates'],['hr-activity-modal','task_alt','Complete activity'],['hr-complete-onboarding-modal','verified','Complete onboarding'],['hr-exit-interview-modal','event','Schedule exit'],['hr-exit-complete-modal','fact_check','Complete exit'],['hr-full-final-modal','account_balance','Full and final'],['hr-separation-review-modal','approval','Review separation'],['hr-document-type-modal','badge','Document types']] as [$modal, $icon, $label]): ?>
                    <button type="button" data-record-modal-open="<?= bx_h($modal) ?>" class="flex items-center gap-3 rounded-md border px-3 py-3 text-left text-sm hover:bg-muted"><span class="material-symbols-outlined text-[18px]"><?= bx_h($icon) ?></span><span><?= bx_h($label) ?></span></button>
                <?php endforeach; ?>
            </div>
            <div class="mt-5 rounded-md border p-4">
                <h3 class="text-sm font-semibold">Owner contracts</h3>
                <?php foreach ($dependencies as $dependency): ?>
                    <p class="mt-2 text-xs text-muted-foreground"><?= bx_h((string) ($dependency['contract'] ?? '')) ?> · <?= !empty($dependency['available']) ? 'available' : 'pending' ?></p>
                <?php endforeach; ?>
                <p class="mt-2 text-xs text-muted-foreground">orchestration.assets-clearance.v1 and orchestration.finance-settlement.v1 are read-only blockers until their owners publish services.</p>
            </div>
            <div class="mt-5 rounded-md border p-4">
                <h3 class="text-sm font-semibold">Progress</h3>
                <p class="mt-2 text-sm text-muted-foreground"><?= count($onboardings) ?> onboardings · <?= count($separations) ?> separations · <?= count($documents) ?> documents · <?= count($finalStatements) ?> final statements</p>
            </div>
        </div>
    </aside>
</div>
