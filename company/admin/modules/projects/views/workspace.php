<?php
declare(strict_types=1);

$projectsData = is_array($activeModuleData ?? null) ? $activeModuleData : [];
$projectsSection = (string) ($activeModuleSection ?? $projectsData['section'] ?? 'projects');
$projectsSections = is_array($activeModuleSections ?? null)
    ? $activeModuleSections
    : (is_array($projectsData['sections'] ?? null) ? $projectsData['sections'] : yovel_admin_projects_sections());
$projectsFormState = is_array($activeModuleFormState ?? null) ? $activeModuleFormState : [];
$projectsPrior = is_array($projectsFormState['input'] ?? null) ? $projectsFormState['input'] : [];
$projectsError = trim((string) ($projectsFormState['error'] ?? ''));
$projectsAction = (string) ($projectsFormState['action'] ?? '');
$projectsEscape = static fn (mixed $value): string => bx_h((string) $value);
$projectsOwnerStates = is_array($projectsData['owner_states'] ?? null) ? $projectsData['owner_states'] : yovel_admin_projects_owner_states();
$projectsState = is_array($projectsData['state'] ?? null) ? $projectsData['state'] : yovel_admin_projects_state($projectsSection, $projectsOwnerStates);
$projectsMetrics = is_array($projectsData['metrics'] ?? null) ? $projectsData['metrics'] : [];
?>
<style>@media (min-width:1024px){[data-projects-workspace]{grid-template-columns:minmax(0, 12fr) minmax(16rem, 8fr)}}</style>
<div data-projects-workspace data-projects-company="<?= $projectsEscape($company['company_key_hash'] ?? '') ?>" class="grid min-h-0 gap-5">
    <main data-projects-main-panel data-grid-span="12" class="min-w-0 space-y-5">
        <header class="border-b pb-4">
            <p class="text-sm text-muted-foreground"><?= $projectsEscape($projectsData['company_name'] ?? $companyName ?? 'Company') ?></p>
            <h1 class="mt-1 text-2xl font-semibold">Projects</h1>
        </header>

        <nav aria-label="Projects sections" class="flex gap-1 overflow-x-auto border-b pb-2">
            <?php foreach ($projectsSections as $sectionKey => $sectionMeta): ?>
                <a href="?view=projects&amp;section=<?= $projectsEscape($sectionKey) ?>"
                   class="shrink-0 rounded-md px-3 py-2 text-sm <?= $sectionKey === $projectsSection ? 'bg-muted font-medium' : 'text-muted-foreground' ?>">
                    <?= $projectsEscape($sectionMeta['label'] ?? $sectionKey) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($projectsSection === 'dashboard'): ?>
            <?php require __DIR__ . '/dashboard.php'; ?>
        <?php elseif ($projectsSection === 'projects'): ?>
            <?php require __DIR__ . '/projects.php'; ?>
        <?php elseif ($projectsSection === 'project-tasks'): ?>
            <?php require __DIR__ . '/tasks.php'; ?>
        <?php elseif ($projectsSection === 'form-builder'): ?>
            <?php
            $projectsTarget = yovel_admin_projects_target((string) ($projectsPrior['target_type'] ?? $projectsData['form_target'] ?? 'PROJECT'));
            $projectsEditable = yovel_admin_projects_editable_schema($company, $projectsTarget);
            $projectsSchemaJson = (string) ($projectsPrior['schema_json'] ?? yovel_admin_projects_form_json($projectsEditable));
            $projectsFieldLabel = (string) ($projectsPrior['new_field_label'] ?? '');
            ?>
            <section aria-labelledby="projects-form-layout-heading" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="projects-form-layout-heading" class="text-lg font-semibold">Form Layout</h2>
                        <p class="text-sm text-muted-foreground"><?= $projectsEscape(yovel_admin_projects_builder_targets()[$projectsTarget]) ?> version <?= (int) ($projectsEditable['version'] ?? 0) ?></p>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <?php foreach (($projectsEditable['fields'] ?? []) as $field): ?>
                        <div class="rounded-md border p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium"><?= $projectsEscape($field['label'] ?? '') ?></span>
                                <span class="text-xs text-muted-foreground"><?= $projectsEscape($field['type'] ?? '') ?></span>
                            </div>
                            <code class="mt-1 block text-xs text-muted-foreground"><?= $projectsEscape($field['key'] ?? '') ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php elseif ($projectsSection === 'settings'): ?>
            <section class="space-y-3">
                <h2 class="text-lg font-semibold">Projects settings</h2>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="border-b pb-3"><dt class="text-sm text-muted-foreground">Employee time overlap</dt><dd class="font-medium"><?= !empty($projectsData['settings']['ignore_employee_time_overlap']) ? 'Ignored' : 'Validated' ?></dd></div>
                    <div class="border-b pb-3"><dt class="text-sm text-muted-foreground">Timesheets in sales invoices</dt><dd class="font-medium"><?= !empty($projectsData['settings']['fetch_timesheet_in_sales_invoice']) ? 'Enabled' : 'Disabled' ?></dd></div>
                </dl>
            </section>
        <?php else: ?>
            <section class="space-y-3">
                <h2 class="text-lg font-semibold"><?= $projectsEscape($projectsState['title'] ?? 'Projects') ?></h2>
                <?php if (($projectsState['message'] ?? '') !== ''): ?><p class="text-sm text-muted-foreground"><?= $projectsEscape($projectsState['message']) ?></p><?php endif; ?>
                <?php foreach (($projectsState['dependencies'] ?? []) as $contract): ?><code class="block rounded-md border px-3 py-2 text-xs"><?= $projectsEscape($contract) ?></code><?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <aside data-projects-tools-panel data-grid-span="8" class="min-w-0 space-y-5 lg:sticky lg:top-4 lg:self-start">
        <section class="space-y-3 border-b pb-5">
            <h2 class="text-sm font-semibold">Actions and tools</h2>
            <?php if ($projectsSection === 'dashboard'): ?>
                <button type="button" data-projects-tour-start class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">
                    <span class="material-symbols-rounded text-base" aria-hidden="true">tour</span>Show Tour
                </button>
            <?php elseif ($projectsSection === 'projects'): ?>
                <p class="text-sm text-muted-foreground">Use the master actions to add Projects, classifications, templates, and complete template graphs.</p>
            <?php elseif ($projectsSection === 'form-builder'): ?>
                <?php
                ob_start();
                if ($projectsError !== ''): ?><div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $projectsEscape($projectsError) ?></div><?php endif; ?>
                <div class="grid gap-4 lg:grid-cols-3">
                    <section class="space-y-2"><h3 class="text-sm font-semibold">Field Toolbox</h3><label class="grid gap-1 text-sm">Field label<input name="new_field_label" maxlength="180" class="h-9 rounded-md border px-3" value="<?= $projectsEscape($projectsFieldLabel) ?>"></label></section>
                    <section class="space-y-2"><h3 class="text-sm font-semibold">Form Layout</h3><textarea name="schema_json" rows="12" required class="min-h-48 w-full rounded-md border p-3 font-mono text-xs"><?= $projectsEscape($projectsSchemaJson) ?></textarea></section>
                    <section class="space-y-2"><h3 class="text-sm font-semibold">Field Properties</h3><label class="grid gap-1 text-sm">Lifecycle<select name="schema_status" class="h-9 rounded-md border px-3"><?php foreach (['DRAFT' => 'Save draft', 'PUBLISHED' => 'Publish version', 'ARCHIVED' => 'Archive version'] as $value => $label): ?><option value="<?= $value ?>"<?= (string) ($projectsPrior['schema_status'] ?? 'DRAFT') === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label></section>
                </div>
                <?php
                $projectsBuilderBody = (string) ob_get_clean();
                $projectsRecordModal = [
                    'id' => 'projects-form-builder-modal',
                    'title' => 'Create Form Version',
                    'description' => 'Configure a versioned Projects record form.',
                    'open_label' => 'Create Form Version',
                    'submit_label' => 'Submit',
                    'confirm_message' => 'Confirm this Projects form version before saving it.',
                    'body_html' => $projectsBuilderBody,
                    'hidden_html' => '<input type="hidden" name="csrf" value="' . $projectsEscape(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="projects"><input type="hidden" name="action" value="save_projects_form_schema"><input type="hidden" name="section" value="form-builder"><input type="hidden" name="target_type" value="' . $projectsEscape($projectsTarget) . '">',
                ];
                $projectsModalOpen = $projectsAction === 'save_projects_form_schema' && $projectsError !== '';
                require __DIR__ . '/record-modal.php';
                ?>
            <?php elseif ($projectsSection === 'settings'): ?>
                <?php
                $settings = is_array($projectsData['settings'] ?? null) ? $projectsData['settings'] : [];
                $priorOverlap = $projectsPrior['ignore_employee_time_overlap'] ?? $settings['ignore_employee_time_overlap'] ?? 0;
                $priorInvoice = $projectsPrior['fetch_timesheet_in_sales_invoice'] ?? $settings['fetch_timesheet_in_sales_invoice'] ?? 0;
                ob_start();
                if ($projectsError !== ''): ?><div role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"><?= $projectsEscape($projectsError) ?></div><?php endif; ?>
                <label class="flex items-start gap-3 text-sm"><input type="hidden" name="ignore_employee_time_overlap" value="0"><input type="checkbox" name="ignore_employee_time_overlap" value="1"<?= yovel_admin_projects_flag($priorOverlap) ? ' checked' : '' ?>><span><strong class="block">Ignore employee time overlap</strong><span class="text-muted-foreground">Allow the HR owner to accept overlapping project time.</span></span></label>
                <label class="flex items-start gap-3 text-sm"><input type="hidden" name="fetch_timesheet_in_sales_invoice" value="0"><input type="checkbox" name="fetch_timesheet_in_sales_invoice" value="1"<?= yovel_admin_projects_flag($priorInvoice) ? ' checked' : '' ?>><span><strong class="block">Fetch timesheets in sales invoices</strong><span class="text-muted-foreground">Expose the policy to the Finance billing contract.</span></span></label>
                <?php $settingsBody = (string) ob_get_clean();
                $projectsRecordModal = [
                    'id' => 'projects-settings-modal', 'title' => 'Edit Projects Settings',
                    'description' => 'Set company-level Projects policies.', 'open_label' => 'Edit Projects Settings',
                    'submit_label' => 'Submit', 'confirm_message' => 'Confirm these Projects settings before saving them.',
                    'body_html' => $settingsBody,
                    'hidden_html' => '<input type="hidden" name="csrf" value="' . $projectsEscape(bx_csrf_token()) . '"><input type="hidden" name="module_view" value="projects"><input type="hidden" name="action" value="save_projects_settings"><input type="hidden" name="section" value="settings">',
                ];
                $projectsModalOpen = $projectsAction === 'save_projects_settings' && $projectsError !== '';
                require __DIR__ . '/record-modal.php';
                ?>
            <?php else: ?>
                <p class="text-sm text-muted-foreground">Use the Task tree actions to create, classify, schedule, transition, and retry Project work.</p>
            <?php endif; ?>
        </section>

        <?php if ($projectsSection === 'dashboard'): ?>
            <?php $projectsDashboard = is_array($projectsData['dashboard'] ?? null) ? $projectsData['dashboard'] : []; ?>
            <section data-projects-dashboard-setup data-projects-tour-target="setup" class="space-y-3">
                <div>
                    <h2 class="text-sm font-semibold">Projects setup</h2>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Complete the module-owned foundation before operational packages are enabled.</p>
                </div>
                <div class="divide-y border-y">
                    <?php foreach (($projectsDashboard['setup'] ?? []) as $step): ?>
                        <a href="<?= $projectsEscape($step['href'] ?? '') ?>" class="flex items-center justify-between gap-3 py-3 text-sm">
                            <span class="flex min-w-0 items-center gap-2"><span class="material-symbols-rounded text-base" aria-hidden="true"><?= !empty($step['complete']) ? 'check_circle' : 'radio_button_unchecked' ?></span><span><?= $projectsEscape($step['label'] ?? '') ?></span></span>
                            <span class="text-xs text-muted-foreground"><?= !empty($step['complete']) ? 'Ready' : 'Open' ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section data-projects-dashboard-dependencies class="space-y-3 border-t pt-5">
                <h2 class="text-sm font-semibold">Dependency health</h2>
                <?php foreach (($projectsDashboard['dependencies'] ?? []) as $dependency): ?>
                    <div class="flex items-start justify-between gap-3 border-b pb-2 text-sm">
                        <span class="min-w-0"><strong class="block"><?= $projectsEscape($dependency['label'] ?? '') ?></strong><code class="block truncate text-xs text-muted-foreground"><?= $projectsEscape($dependency['contract'] ?? '') ?></code></span>
                        <span class="shrink-0 text-xs font-medium" data-dependency-status="<?= $projectsEscape($dependency['status'] ?? '') ?>"><?= $projectsEscape($dependency['status'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <section class="space-y-3">
                <h2 class="text-sm font-semibold">Owner contracts</h2>
                <?php foreach ($projectsOwnerStates as $ownerState): ?>
                    <div class="flex items-start justify-between gap-3 border-b pb-2 text-sm">
                        <span><strong class="block"><?= $projectsEscape($ownerState['owner'] ?? '') ?></strong><code class="text-xs text-muted-foreground"><?= $projectsEscape($ownerState['contract'] ?? '') ?></code></span>
                        <span class="text-xs font-medium"><?= $projectsEscape($ownerState['state'] ?? 'UNAVAILABLE') ?></span>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="grid grid-cols-3 gap-2 text-center">
                <div><strong class="block text-lg"><?= (int) ($projectsMetrics['projects'] ?? 0) ?></strong><span class="text-xs text-muted-foreground">Projects</span></div>
                <div><strong class="block text-lg"><?= (int) ($projectsMetrics['tasks'] ?? 0) ?></strong><span class="text-xs text-muted-foreground">Tasks</span></div>
                <div><strong class="block text-lg"><?= (int) ($projectsMetrics['published_forms'] ?? 0) ?></strong><span class="text-xs text-muted-foreground">Forms</span></div>
            </section>
        <?php endif; ?>
    </aside>
</div>
