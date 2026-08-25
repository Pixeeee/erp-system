<?php
$hrSetup = is_array($hrData['setup'] ?? null) ? $hrData['setup'] : [];
$setupSettings = is_array($hrSetup['settings'] ?? null) ? $hrSetup['settings'] : [];
$setupMasters = is_array($hrSetup['masters'] ?? null) ? $hrSetup['masters'] : [];
$setupLifecycle = is_array($hrSetup['lifecycle'] ?? null) ? $hrSetup['lifecycle'] : [];
$setupPropertyHistory = is_array($hrSetup['propertyHistory'] ?? null) ? $hrSetup['propertyHistory'] : [];
$setupChart = is_array($hrSetup['organizationChart'] ?? null) ? $hrSetup['organizationChart'] : [];
$setupFormTargets = is_array($hrSetup['formTargets'] ?? null) ? $hrSetup['formTargets'] : [];
$setupUsers = is_array($hrSetup['users'] ?? null) ? $hrSetup['users'] : [];
$setupEmployees = is_array($hrData['employees'] ?? null) ? $hrData['employees'] : [];
$setupDepartments = is_array($hrData['departments'] ?? null) ? $hrData['departments'] : [];
$setupPositions = is_array($hrData['jobPositions'] ?? null) ? $hrData['jobPositions'] : [];
$setupTeams = is_array($hrData['teams'] ?? null) ? $hrData['teams'] : [];
$setupBranches = is_array($hrData['branches'] ?? null) ? $hrData['branches'] : [];
$setupFormState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
$setupOldInput = is_array($setupFormState['input'] ?? null) ? $setupFormState['input'] : [];
$setupFailedAction = (string) ($setupFormState['action'] ?? '');
$setupEditKey = trim((string) ($_GET['edit_setup'] ?? ''));
$setupEditMaster = $setupEditKey !== '' ? yovel_admin_find_record($setupMasters, 'setup_master_key', $setupEditKey) : null;
$setupOld = static function (string $key, mixed $fallback = '') use ($setupOldInput): mixed {
    return array_key_exists($key, $setupOldInput) ? $setupOldInput[$key] : $fallback;
};
$setupModalOpen = static function (string $action) use ($setupFailedAction): bool {
    return $setupFailedAction === $action;
};
$setupMasterTypes = yovel_admin_hr_setup_master_types();
$setupLifecycleTypes = yovel_admin_hr_setup_lifecycle_types();
?>
<style>
    .yovel-hr-setup-layout > * { min-width: 0; }
    .yovel-hr-setup-table { overflow-x: auto; overscroll-behavior-inline: contain; }
    @media (min-width: 1280px) {
        .yovel-hr-setup-layout { grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr); }
    }
    @media (max-width: 1279px) {
        .yovel-hr-setup-layout { grid-template-columns: minmax(0, 1fr); }
    }
</style>

<div class="mb-1 flex items-center justify-between gap-3">
    <a class="inline-flex h-8 items-center gap-1.5 rounded-md border bg-background px-3 text-xs font-medium hover:bg-muted" href="./?view=hr&amp;section=dashboard">
        <span class="material-symbols-rounded text-base" aria-hidden="true">arrow_back</span>HR Dashboard
    </a>
    <span class="text-xs text-muted-foreground">Company-scoped setup and employee lifecycle</span>
</div>

<div class="yovel-hr-setup-layout grid min-h-0 gap-3">
    <section class="yovel-hr-panel min-w-0 overflow-hidden rounded-lg border bg-card" aria-labelledby="hr-setup-title">
        <header class="border-b px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="hr-setup-title" class="text-base font-semibold">HR Setup</h2>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">Govern employee masters, settings, transfers, promotions, and support records.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-record-modal-open="hr-settings-modal" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">tune</span>Settings</button>
                    <button type="button" data-record-modal-open="hr-setup-master-modal" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>Add Master</button>
                    <button type="button" data-record-modal-open="hr-transfer-modal" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">move_up</span>Transfer</button>
                    <button type="button" data-record-modal-open="hr-promotion-modal" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"><span class="material-symbols-rounded text-base" aria-hidden="true">trending_up</span>Promotion</button>
                </div>
            </div>
        </header>

        <div class="grid gap-5 p-5">
            <section aria-labelledby="hr-setup-masters-title">
                <div class="flex items-end justify-between gap-3 border-b pb-3">
                    <div><h3 id="hr-setup-masters-title" class="text-sm font-semibold">Setup Masters</h3><p class="mt-1 text-xs text-muted-foreground">Employment Types, Employee Grades, Grievance Types, Interests, and Health Insurance.</p></div>
                    <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($setupMasters) ?> records</span>
                </div>
                <div class="yovel-hr-setup-table">
                    <table class="w-full min-w-[42rem] text-left text-sm">
                        <thead class="text-xs text-muted-foreground"><tr><th class="px-3 py-3 font-medium">Type</th><th class="px-3 py-3 font-medium">Code</th><th class="px-3 py-3 font-medium">Name</th><th class="px-3 py-3 font-medium">Status</th><th class="px-3 py-3 text-right font-medium">Action</th></tr></thead>
                        <tbody class="divide-y">
                            <?php if ($setupMasters === []): ?><tr><td colspan="5" class="px-3 py-8 text-center text-sm text-muted-foreground">No setup masters yet.</td></tr><?php endif; ?>
                            <?php foreach ($setupMasters as $master): ?>
                                <tr>
                                    <td class="px-3 py-3 font-medium"><?= bx_h($setupMasterTypes[(string) $master['record_type']] ?? (string) $master['record_type']) ?></td>
                                    <td class="px-3 py-3 font-mono text-xs text-muted-foreground"><?= bx_h((string) $master['record_code']) ?></td>
                                    <td class="px-3 py-3"><?= bx_h((string) $master['record_name']) ?></td>
                                    <td class="px-3 py-3"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $master['record_status']) ?></span></td>
                                    <td class="px-3 py-3 text-right"><a class="inline-flex size-8 items-center justify-center rounded-md hover:bg-muted" href="./?view=hr&amp;section=dashboard&amp;workspace=setup&amp;edit_setup=<?= bx_h((string) $master['setup_master_key']) ?>" aria-label="Edit <?= bx_h((string) $master['record_name']) ?>"><span class="material-symbols-rounded text-lg" aria-hidden="true">edit</span></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="hr-lifecycle-title">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b pb-3">
                    <div><h3 id="hr-lifecycle-title" class="text-sm font-semibold">Employee Lifecycle</h3><p class="mt-1 text-xs text-muted-foreground">Transfer, promotion, grievance, department approver, and appraisee records.</p></div>
                    <button type="button" data-record-modal-open="hr-lifecycle-modal" class="inline-flex h-8 items-center gap-1.5 rounded-md border bg-background px-3 text-xs font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>Add Record</button>
                </div>
                <div class="yovel-hr-setup-table">
                    <table class="w-full min-w-[48rem] text-left text-sm">
                        <thead class="text-xs text-muted-foreground"><tr><th class="px-3 py-3 font-medium">Record</th><th class="px-3 py-3 font-medium">Employee</th><th class="px-3 py-3 font-medium">Effective</th><th class="px-3 py-3 font-medium">Details</th><th class="px-3 py-3 font-medium">Status</th></tr></thead>
                        <tbody class="divide-y">
                            <?php if ($setupLifecycle === []): ?><tr><td colspan="5" class="px-3 py-8 text-center text-sm text-muted-foreground">No lifecycle records yet.</td></tr><?php endif; ?>
                            <?php foreach ($setupLifecycle as $record): ?>
                                <tr>
                                    <td class="px-3 py-3 font-medium"><?= bx_h(ucwords(strtolower(str_replace('_', ' ', (string) $record['record_type'])))) ?></td>
                                    <td class="px-3 py-3"><span class="block font-medium"><?= bx_h((string) ($record['employee_name'] ?? 'Unassigned')) ?></span><span class="text-xs text-muted-foreground"><?= bx_h((string) ($record['employee_code'] ?? '')) ?></span></td>
                                    <td class="px-3 py-3 text-muted-foreground"><?= bx_h((string) $record['effective_date']) ?></td>
                                    <td class="max-w-xs px-3 py-3 text-xs leading-5 text-muted-foreground"><?= bx_h((string) ($record['job_position_name'] ?: $record['department_name'] ?: $record['setup_master_name'] ?: $record['related_employee_name'] ?: $record['reason'])) ?></td>
                                    <td class="px-3 py-3"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $record['record_status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="hr-property-history-title">
                <div class="flex items-end justify-between gap-3 border-b pb-3"><div><h3 id="hr-property-history-title" class="text-sm font-semibold">Property History</h3><p class="mt-1 text-xs text-muted-foreground">Read-only effective-dated changes created by transfer and promotion transactions.</p></div><span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs"><?= count($setupPropertyHistory) ?> changes</span></div>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <?php if ($setupPropertyHistory === []): ?><p class="text-sm text-muted-foreground">No employee property changes yet.</p><?php endif; ?>
                    <?php foreach (array_slice($setupPropertyHistory, 0, 12) as $history): ?>
                        <div class="rounded-md border px-3 py-2.5"><div class="flex items-center justify-between gap-2"><span class="text-sm font-medium"><?= bx_h((string) $history['employee_name']) ?></span><span class="text-xs text-muted-foreground"><?= bx_h((string) $history['effective_date']) ?></span></div><p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) ($history['property_label'] ?: ucwords(str_replace('_', ' ', (string) $history['property_name'])))) ?>: <?= bx_h((string) ($history['previous_value'] ?: 'None')) ?> → <?= bx_h((string) ($history['new_value'] ?: 'None')) ?></p></div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </section>

    <aside class="yovel-hr-panel min-w-0 overflow-hidden rounded-lg border bg-card" aria-labelledby="hr-setup-tools-title">
        <header class="border-b px-5 py-4"><h2 id="hr-setup-tools-title" class="text-base font-semibold">Setup Tools</h2><p class="mt-1 text-sm leading-6 text-muted-foreground">Hierarchy and protected form definitions used by HR Setup.</p></header>
        <div class="grid gap-5 p-5">
            <section>
                <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Organization Chart</h3><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= count($setupChart) ?> employees</span></div>
                <div class="mt-3 grid divide-y rounded-md border">
                    <?php if ($setupChart === []): ?><p class="p-3 text-sm text-muted-foreground">No employees available.</p><?php endif; ?>
                    <?php foreach (array_slice($setupChart, 0, 20) as $node): ?>
                        <div class="flex items-start gap-3 px-3 py-3"><span class="material-symbols-rounded mt-0.5 text-lg text-muted-foreground" aria-hidden="true">account_tree</span><div class="min-w-0"><p class="truncate text-sm font-medium"><?= bx_h((string) $node['employee_name']) ?></p><p class="mt-0.5 text-xs leading-5 text-muted-foreground"><?= bx_h((string) ($node['job_position_name'] ?: 'No position')) ?><?= (string) $node['reports_to_employee_name'] !== '' ? ' · Reports to ' . bx_h((string) $node['reports_to_employee_name']) : ' · Top level' ?></p></div></div>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="border-t pt-5">
                <h3 class="text-sm font-semibold">Form Builder Protection</h3><p class="mt-1 text-xs leading-5 text-muted-foreground">System keys, effective dates, and workflow states stay protected while supplemental fields remain versioned.</p>
                <div class="mt-3 grid gap-2">
                    <?php foreach ($setupFormTargets as $targetKey => $target): ?>
                        <div class="flex items-center justify-between gap-3 rounded-md border px-3 py-2"><span class="text-xs font-medium"><?= bx_h((string) $target['label']) ?></span><span class="text-[11px] text-muted-foreground"><?= count($target['protected_fields'] ?? []) ?> protected</span></div>
                    <?php endforeach; ?>
                </div>
                <a class="mt-3 inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=existing&amp;builder_target=employee-profiles"><span class="material-symbols-rounded text-base" aria-hidden="true">view_quilt</span>Open HR Form Builder</a>
            </section>
        </div>
    </aside>
</div>

<?php
$setupModalClass = 'fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm';
$setupDialogClass = 'flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-3xl flex-col overflow-hidden rounded-lg border bg-popover shadow-lg';
$setupInputClass = 'mt-1 h-10 w-full rounded-md border bg-background px-3 text-sm';
$setupTextareaClass = 'mt-1 min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm';
?>

<div id="hr-settings-modal" data-record-modal <?= $setupModalOpen('hr_setup_save_record') && strtoupper((string) ($setupOldInput['record_type'] ?? '')) === 'HR_SETTINGS' ? 'data-record-modal-open-on-load' : '' ?> class="<?= $setupModalClass ?>" role="dialog" aria-modal="true" aria-labelledby="hr-settings-modal-title" hidden>
    <section class="<?= $setupDialogClass ?>"><header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><h3 id="hr-settings-modal-title" class="text-base font-semibold">HR Settings</h3><p class="mt-1 text-sm text-muted-foreground">Control employee naming and company HR defaults.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close HR Settings">×</button></header>
        <form method="post" data-confirm-submit class="min-h-0 overflow-y-auto p-5"><input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="hr_setup_save_record"><input type="hidden" name="module_view" value="hr"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="workspace" value="setup"><input type="hidden" name="record_type" value="HR_SETTINGS">
            <?php $setupSettingsPolicy = json_decode((string) ($setupSettings['settings_json'] ?? '{}'), true) ?: []; ?>
            <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Naming mode<select name="employee_naming_mode" class="<?= $setupInputClass ?>"><option value="SERIES">Series</option><option value="MANUAL" <?= (string) $setupOld('employee_naming_mode', $setupSettings['employee_naming_mode'] ?? '') === 'MANUAL' ? 'selected' : '' ?>>Manual</option></select></label><label class="text-sm font-medium">Employee prefix<input name="employee_number_prefix" value="<?= bx_h((string) $setupOld('employee_number_prefix', $setupSettings['employee_number_prefix'] ?? 'HR-EMP-')) ?>" class="<?= $setupInputClass ?>" required></label><label class="text-sm font-medium">Default retirement age<input type="number" name="default_retirement_age" min="18" max="100" value="<?= bx_h((string) $setupOld('default_retirement_age', $setupSettings['default_retirement_age'] ?? 60)) ?>" class="<?= $setupInputClass ?>" required></label><label class="text-sm font-medium">Standard working hours<input type="number" min="1" max="24" step="0.5" name="settings[standard_working_hours]" value="<?= bx_h((string) ($setupSettingsPolicy['standard_working_hours'] ?? '8')) ?>" class="<?= $setupInputClass ?>"></label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_employee_self_service" value="1" <?= (int) $setupOld('allow_employee_self_service', $setupSettings['allow_employee_self_service'] ?? 0) === 1 ? 'checked' : '' ?>>Allow employee self-service</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="settings[send_birthday_reminders]" value="1" <?= !empty($setupSettingsPolicy['send_birthday_reminders']) ? 'checked' : '' ?>>Send birthday reminders</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="settings[leave_approver_mandatory]" value="1" <?= !empty($setupSettingsPolicy['leave_approver_mandatory']) ? 'checked' : '' ?>>Require leave approver</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="settings[expense_approver_mandatory]" value="1" <?= !empty($setupSettingsPolicy['expense_approver_mandatory']) ? 'checked' : '' ?>>Require expense approver</label></div>
            <div class="mt-5 flex justify-end gap-2 border-t pt-4"><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">Save Settings</button></div>
        </form>
    </section>
</div>

<?php $masterOpen = $setupEditMaster !== null || ($setupModalOpen('hr_setup_save_record') && isset($setupMasterTypes[strtoupper((string) ($setupOldInput['record_type'] ?? ''))])); ?>
<div id="hr-setup-master-modal" data-record-modal <?= $masterOpen ? 'data-record-modal-open-on-load' : '' ?> class="<?= $setupModalClass ?>" role="dialog" aria-modal="true" aria-labelledby="hr-setup-master-title" <?= $masterOpen ? '' : 'hidden' ?>>
    <section class="<?= $setupDialogClass ?>"><header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><h3 id="hr-setup-master-title" class="text-base font-semibold"><?= $setupEditMaster ? 'Edit Setup Master' : 'Add Setup Master' ?></h3><p class="mt-1 text-sm text-muted-foreground">Create governed values used by employee and lifecycle forms.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close Setup Master">×</button></header>
        <form method="post" data-confirm-submit class="min-h-0 overflow-y-auto p-5"><input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="hr_setup_save_record"><input type="hidden" name="module_view" value="hr"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="workspace" value="setup"><input type="hidden" name="setup_master_key" value="<?= bx_h((string) $setupOld('setup_master_key', $setupEditMaster['setup_master_key'] ?? '')) ?>">
            <?php $setupMasterMetadata = json_decode((string) ($setupEditMaster['metadata_json'] ?? '{}'), true) ?: []; ?>
            <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Master type<select name="record_type" class="<?= $setupInputClass ?>" required><?php foreach ($setupMasterTypes as $typeKey => $typeLabel): ?><option value="<?= bx_h($typeKey) ?>" <?= (string) $setupOld('record_type', $setupEditMaster['record_type'] ?? 'EMPLOYMENT_TYPE') === $typeKey ? 'selected' : '' ?>><?= bx_h($typeLabel) ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Status<select name="record_status" class="<?= $setupInputClass ?>"><?php foreach (['DRAFT','ACTIVE','INACTIVE'] as $status): ?><option value="<?= $status ?>" <?= (string) $setupOld('record_status', $setupEditMaster['record_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Code<input name="record_code" value="<?= bx_h((string) $setupOld('record_code', $setupEditMaster['record_code'] ?? '')) ?>" class="<?= $setupInputClass ?>" required></label><label class="text-sm font-medium">Name<input name="record_name" value="<?= bx_h((string) $setupOld('record_name', $setupEditMaster['record_name'] ?? '')) ?>" class="<?= $setupInputClass ?>" required></label><label class="text-sm font-medium">Default salary structure<input name="metadata[default_salary_structure]" value="<?= bx_h((string) ($setupMasterMetadata['default_salary_structure'] ?? '')) ?>" class="<?= $setupInputClass ?>"></label><label class="text-sm font-medium">Currency<input name="metadata[currency]" maxlength="3" value="<?= bx_h((string) ($setupMasterMetadata['currency'] ?? '')) ?>" class="<?= $setupInputClass ?>"></label><label class="text-sm font-medium">Default base pay<input type="number" min="0" step="0.01" name="metadata[default_base_pay]" value="<?= bx_h((string) ($setupMasterMetadata['default_base_pay'] ?? '')) ?>" class="<?= $setupInputClass ?>"></label><label class="text-sm font-medium sm:col-span-2">Description<textarea name="record_description" class="<?= $setupTextareaClass ?>"><?= bx_h((string) $setupOld('record_description', $setupEditMaster['record_description'] ?? '')) ?></textarea></label></div>
            <div class="mt-5 flex justify-end gap-2 border-t pt-4"><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">Save Master</button></div>
        </form>
    </section>
</div>

<?php
$employeeOptions = static function (array $employees, string $selected = ''): void { foreach ($employees as $employee) { $key = (string) $employee['employee_key']; ?><option value="<?= bx_h($key) ?>" <?= $selected === $key ? 'selected' : '' ?>><?= bx_h((string) $employee['employee_name']) ?> · <?= bx_h((string) $employee['employee_code']) ?></option><?php } };
$branchOptions = static function (array $branches, string $selected = ''): void { foreach ($branches as $branch) { $key = (string) $branch['branch_key']; ?><option value="<?= bx_h($key) ?>" <?= $selected === $key ? 'selected' : '' ?>><?= bx_h((string) $branch['branch_name']) ?></option><?php } };
?>
<div id="hr-transfer-modal" data-record-modal <?= $setupModalOpen('hr_setup_transfer') ? 'data-record-modal-open-on-load' : '' ?> class="<?= $setupModalClass ?>" role="dialog" aria-modal="true" aria-labelledby="hr-transfer-title" hidden>
    <section class="<?= $setupDialogClass ?>"><header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><h3 id="hr-transfer-title" class="text-base font-semibold">Employee Transfer</h3><p class="mt-1 text-sm text-muted-foreground">Create an effective-dated placement change through the assignment owner service.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close Transfer">×</button></header>
        <form method="post" data-confirm-submit class="min-h-0 overflow-y-auto p-5"><input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="hr_setup_transfer"><input type="hidden" name="module_view" value="hr"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="workspace" value="setup">
            <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium sm:col-span-2">Employee<select name="employee_key" class="<?= $setupInputClass ?>" required><option value="">Select employee</option><?php $employeeOptions($setupEmployees, (string) $setupOld('employee_key')); ?></select></label><label class="text-sm font-medium">Effective date<input type="date" name="effective_date" value="<?= bx_h((string) $setupOld('effective_date', date('Y-m-d'))) ?>" class="<?= $setupInputClass ?>" required></label><label class="text-sm font-medium">Status<select name="transfer_status" class="<?= $setupInputClass ?>"><?php foreach (['DRAFT','SUBMITTED','APPROVED'] as $status): ?><option value="<?= $status ?>" <?= (string) $setupOld('transfer_status', 'SUBMITTED') === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Branch<select name="branch_key" class="<?= $setupInputClass ?>"><option value="">Unassigned</option><?php $branchOptions($setupBranches, (string) $setupOld('branch_key')); ?></select></label><label class="text-sm font-medium">Department<select name="department_key" class="<?= $setupInputClass ?>"><option value="">Unassigned</option><?php foreach ($setupDepartments as $department): $key = (string) $department['department_key']; ?><option value="<?= bx_h($key) ?>" <?= (string) $setupOld('department_key') === $key ? 'selected' : '' ?>><?= bx_h((string) $department['department_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Job position<select name="job_position_key" class="<?= $setupInputClass ?>"><option value="">Unassigned</option><?php foreach ($setupPositions as $position): $key = (string) $position['job_position_key']; ?><option value="<?= bx_h($key) ?>" <?= (string) $setupOld('job_position_key') === $key ? 'selected' : '' ?>><?= bx_h((string) $position['job_position_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Team<select name="team_key" class="<?= $setupInputClass ?>"><option value="">Unassigned</option><?php foreach ($setupTeams as $team): $key = (string) $team['team_key']; ?><option value="<?= bx_h($key) ?>" <?= (string) $setupOld('team_key') === $key ? 'selected' : '' ?>><?= bx_h((string) $team['team_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium sm:col-span-2">Reports to<select name="reports_to_employee_key" class="<?= $setupInputClass ?>"><option value="">Top level</option><?php $employeeOptions($setupEmployees, (string) $setupOld('reports_to_employee_key')); ?></select></label><label class="text-sm font-medium sm:col-span-2">Reason<textarea name="reason" class="<?= $setupTextareaClass ?>" required><?= bx_h((string) $setupOld('reason')) ?></textarea></label></div>
            <div class="mt-5 flex justify-end gap-2 border-t pt-4"><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">Save Transfer</button></div>
        </form>
    </section>
</div>

<div id="hr-promotion-modal" data-record-modal <?= $setupModalOpen('hr_setup_promotion') ? 'data-record-modal-open-on-load' : '' ?> class="<?= $setupModalClass ?>" role="dialog" aria-modal="true" aria-labelledby="hr-promotion-title" hidden>
    <section class="<?= $setupDialogClass ?>"><header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><h3 id="hr-promotion-title" class="text-base font-semibold">Employee Promotion</h3><p class="mt-1 text-sm text-muted-foreground">Apply a position and grade change with immutable property history.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close Promotion">×</button></header>
        <form method="post" data-confirm-submit class="min-h-0 overflow-y-auto p-5"><input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="hr_setup_promotion"><input type="hidden" name="module_view" value="hr"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="workspace" value="setup">
            <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium sm:col-span-2">Employee<select name="employee_key" class="<?= $setupInputClass ?>" required><option value="">Select employee</option><?php $employeeOptions($setupEmployees, (string) $setupOld('employee_key')); ?></select></label><label class="text-sm font-medium">Effective date<input type="date" name="effective_date" value="<?= bx_h((string) $setupOld('effective_date', date('Y-m-d'))) ?>" class="<?= $setupInputClass ?>" required></label><label class="text-sm font-medium">Status<select name="promotion_status" class="<?= $setupInputClass ?>"><?php foreach (['DRAFT','SUBMITTED','APPROVED'] as $status): ?><option value="<?= $status ?>" <?= (string) $setupOld('promotion_status', 'APPROVED') === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Job position<select name="job_position_key" class="<?= $setupInputClass ?>" required><option value="">Select position</option><?php foreach ($setupPositions as $position): $key = (string) $position['job_position_key']; ?><option value="<?= bx_h($key) ?>" <?= (string) $setupOld('job_position_key') === $key ? 'selected' : '' ?>><?= bx_h((string) $position['job_position_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Employee grade<select name="employee_grade_key" class="<?= $setupInputClass ?>" required><option value="">Select grade</option><?php foreach ($setupMasters as $master): if ((string) $master['record_type'] !== 'EMPLOYEE_GRADE' || (string) $master['record_status'] !== 'ACTIVE') continue; $key = (string) $master['setup_master_key']; ?><option value="<?= bx_h($key) ?>" <?= (string) $setupOld('employee_grade_key') === $key ? 'selected' : '' ?>><?= bx_h((string) $master['record_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Current CTC<input type="number" min="0" step="0.01" name="current_ctc" value="<?= bx_h((string) $setupOld('current_ctc')) ?>" class="<?= $setupInputClass ?>"></label><label class="text-sm font-medium">Revised CTC<input type="number" min="0" step="0.01" name="revised_ctc" value="<?= bx_h((string) $setupOld('revised_ctc')) ?>" class="<?= $setupInputClass ?>"></label><label class="text-sm font-medium sm:col-span-2">Reason<textarea name="reason" class="<?= $setupTextareaClass ?>" required><?= bx_h((string) $setupOld('reason')) ?></textarea></label></div>
            <div class="mt-5 flex justify-end gap-2 border-t pt-4"><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">Save Promotion</button></div>
        </form>
    </section>
</div>

<div id="hr-lifecycle-modal" data-record-modal <?= $setupModalOpen('hr_setup_save_record') && isset($setupLifecycleTypes[strtoupper((string) ($setupOldInput['record_type'] ?? ''))]) ? 'data-record-modal-open-on-load' : '' ?> class="<?= $setupModalClass ?>" role="dialog" aria-modal="true" aria-labelledby="hr-lifecycle-modal-title" hidden>
    <section class="<?= $setupDialogClass ?>"><header class="flex items-start justify-between gap-4 border-b px-5 py-4"><div><h3 id="hr-lifecycle-modal-title" class="text-base font-semibold">Add Employee Support Record</h3><p class="mt-1 text-sm text-muted-foreground">Record grievance, department approver, or appraisee details.</p></div><button type="button" data-record-modal-close class="inline-flex size-8 items-center justify-center rounded-md border" aria-label="Close Employee Support Record">×</button></header>
        <form method="post" data-confirm-submit class="min-h-0 overflow-y-auto p-5"><input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="hr_setup_save_record"><input type="hidden" name="module_view" value="hr"><input type="hidden" name="section" value="dashboard"><input type="hidden" name="workspace" value="setup">
            <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Record type<select name="record_type" class="<?= $setupInputClass ?>" required><?php foreach ($setupLifecycleTypes as $typeKey => $typeLabel): ?><option value="<?= bx_h($typeKey) ?>" <?= strtoupper((string) $setupOld('record_type', 'EMPLOYEE_GRIEVANCE')) === $typeKey ? 'selected' : '' ?>><?= bx_h($typeLabel) ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Status<select name="record_status" class="<?= $setupInputClass ?>"><?php foreach (['OPEN','INVESTIGATED','RESOLVED','INVALID','DRAFT','ACTIVE','INACTIVE','CANCELLED'] as $status): ?><option value="<?= $status ?>" <?= strtoupper((string) $setupOld('record_status', 'OPEN')) === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Employee / appraisee<select name="employee_key" class="<?= $setupInputClass ?>"><option value="">None</option><?php $employeeOptions($setupEmployees, (string) $setupOld('employee_key')); ?></select></label><label class="text-sm font-medium">Effective date<input type="date" name="effective_date" value="<?= bx_h((string) $setupOld('effective_date', date('Y-m-d'))) ?>" class="<?= $setupInputClass ?>" required></label><label class="text-sm font-medium">Approver user<select name="approver_user_key" class="<?= $setupInputClass ?>"><option value="">None</option><?php foreach ($setupUsers as $user): $key = (string) $user['user_key']; ?><option value="<?= bx_h($key) ?>" <?= (string) $setupOld('approver_user_key') === $key ? 'selected' : '' ?>><?= bx_h((string) $user['user_name']) ?> · <?= bx_h((string) $user['user_email']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Department<select name="department_key" class="<?= $setupInputClass ?>"><option value="">None</option><?php foreach ($setupDepartments as $department): $key = (string) $department['department_key']; ?><option value="<?= bx_h($key) ?>" <?= (string) $setupOld('department_key') === $key ? 'selected' : '' ?>><?= bx_h((string) $department['department_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium">Appraisal template<input name="appraisal_template_key" value="<?= bx_h((string) $setupOld('appraisal_template_key')) ?>" class="<?= $setupInputClass ?>"></label><label class="text-sm font-medium">Grievance type<select name="grievance_type_key" class="<?= $setupInputClass ?>"><option value="">None</option><?php foreach ($setupMasters as $master): if ((string) $master['record_type'] !== 'GRIEVANCE_TYPE') continue; $key = (string) $master['setup_master_key']; ?><option value="<?= bx_h($key) ?>" <?= (string) $setupOld('grievance_type_key') === $key ? 'selected' : '' ?>><?= bx_h((string) $master['record_name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-medium sm:col-span-2">Subject<input name="subject" value="<?= bx_h((string) $setupOld('subject')) ?>" class="<?= $setupInputClass ?>"></label><label class="text-sm font-medium sm:col-span-2">Description<textarea name="description" class="<?= $setupTextareaClass ?>"><?= bx_h((string) $setupOld('description')) ?></textarea></label></div>
            <div class="mt-5 flex justify-end gap-2 border-t pt-4"><button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button><button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">Save Record</button></div>
        </form>
    </section>
</div>
