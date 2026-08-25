<?php
/** HR view variables are prepared by bootstrap/controller.php. */
?>
                                        <?php
                                            $formsForTarget = array_values(array_filter($hrBuilderForms, static fn (array $form): bool => (string) ($form['target_section'] ?? '') === $activeHrBuilderTarget));
                                            $activeTargetMeta = $hrBuilderTargetSections[$activeHrBuilderTarget] ?? ['label' => 'Employee profiles', 'description' => 'Create and maintain employee master records.'];
                                            $hrBuilderQuestionTypes = [
                                                'SHORT_TEXT' => 'Short answer',
                                                'PARAGRAPH' => 'Paragraph',
                                                'DROPDOWN' => 'Dropdown',
                                                'CHECKBOXES' => 'Checkboxes',
                                                'DATE' => 'Date',
                                                'NUMBER' => 'Number',
                                                'EMAIL' => 'Email',
                                                'PHONE' => 'Phone',
                                                'SECTION' => 'Section header',
                                            ];
                                            $hrBuilderQuestionTools = [
                                                ['type' => 'SHORT_TEXT', 'label' => 'Short answer', 'icon' => 'short_text'],
                                                ['type' => 'PARAGRAPH', 'label' => 'Paragraph', 'icon' => 'notes'],
                                                ['type' => 'DROPDOWN', 'label' => 'Dropdown', 'icon' => 'arrow_drop_down_circle'],
                                                ['type' => 'CHECKBOXES', 'label' => 'Checkboxes', 'icon' => 'check_box'],
                                                ['type' => 'DATE', 'label' => 'Date', 'icon' => 'calendar_month'],
                                                ['type' => 'NUMBER', 'label' => 'Number', 'icon' => 'tag'],
                                                ['type' => 'EMAIL', 'label' => 'Email', 'icon' => 'alternate_email'],
                                                ['type' => 'PHONE', 'label' => 'Phone', 'icon' => 'call'],
                                                ['type' => 'SECTION', 'label' => 'Section', 'icon' => 'view_agenda'],
                                            ];
                                            $hrDashboardShortcuts = [
                                                ['section' => 'employee-profiles', 'label' => 'Employee Profiles', 'meta' => count($hrEmployees) . ' employees', 'icon' => 'badge'],
                                                ['section' => 'departments', 'label' => 'Departments', 'meta' => count($hrData['departments'] ?? []) . ' departments', 'icon' => 'account_tree'],
                                                ['section' => 'job-positions', 'label' => 'Job Positions', 'meta' => count($hrData['jobPositions'] ?? []) . ' positions', 'icon' => 'work'],
                                                ['section' => 'teams', 'label' => 'Teams', 'meta' => count($hrData['teams'] ?? []) . ' teams', 'icon' => 'groups'],
                                                ['section' => 'attendance', 'label' => 'Attendance', 'meta' => 'Daily records', 'icon' => 'schedule'],
                                                ['section' => 'leave-requests', 'label' => 'Leave Requests', 'meta' => 'Employee requests', 'icon' => 'event_available'],
                                            ];
                                        ?>
                                        <section class="yovel-hr-dashboard-home grid min-h-0 gap-4">
                                            <section class="yovel-hr-dashboard-setup rounded-lg border">
                                                <div class="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4">
                                                    <div>
                                                        <h3 class="text-base font-semibold tracking-normal">Human Resources Workspace</h3>
                                                        <p class="mt-1 text-sm leading-6 text-muted-foreground">Manage employee records, HR setup, daily work, reports, and company forms from one place.</p>
                                                    </div>
                                                    <span class="rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-secondary-foreground"><?= bx_h($companyName) ?></span>
                                                </div>
                                                <div class="grid gap-5 p-5 lg:grid-cols-[minmax(15rem,4fr)_minmax(0,8fr)]">
                                                    <div class="grid content-start gap-1">
                                                        <a class="yovel-hr-dashboard-task" href="./?view=hr&amp;section=employee-profiles"><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">check_circle</span><span class="text-sm font-medium">Review employee profiles</span></a>
                                                        <a class="yovel-hr-dashboard-task" href="./?view=hr&amp;section=departments"><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">check_circle</span><span class="text-sm font-medium">Organize departments</span></a>
                                                        <a class="yovel-hr-dashboard-task" href="./?view=hr&amp;section=job-positions"><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">check_circle</span><span class="text-sm font-medium">Define job positions</span></a>
                                                        <a class="yovel-hr-dashboard-task" aria-current="step" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=existing&amp;builder_target=employee-profiles"><span class="material-symbols-rounded text-base text-foreground" aria-hidden="true">radio_button_checked</span><span class="text-sm font-medium">Manage HR forms</span></a>
                                                    </div>
                                                    <div class="border-t pt-4 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0">
                                                        <div class="flex items-start gap-3">
                                                            <span class="material-symbols-rounded rounded-md border bg-muted p-2 text-xl text-foreground" aria-hidden="true">dashboard_customize</span>
                                                            <div>
                                                                <h4 class="text-sm font-semibold">Form Builder</h4>
                                                                <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">Create blank HR questionnaires or open the built-in Employee Profile forms and saved custom forms for editing.</p>
                                                                <div class="mt-3 flex flex-wrap gap-2">
                                                                    <a class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=new&amp;builder_target=employee-profiles">New Form</a>
                                                                    <a class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=existing&amp;builder_target=employee-profiles">Existing Forms</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </section>

                                            <section class="grid gap-3">
                                                <div class="flex items-end justify-between gap-3">
                                                    <div>
                                                        <h3 class="text-base font-semibold">Your Shortcuts</h3>
                                                        <p class="mt-1 text-sm text-muted-foreground">Open frequently used HR workspaces.</p>
                                                    </div>
                                                </div>
                                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                                    <?php foreach ($hrDashboardShortcuts as $shortcut): ?>
                                                        <a class="yovel-hr-dashboard-feature" href="./?view=hr&amp;section=<?= bx_h((string) $shortcut['section']) ?>">
                                                            <span class="material-symbols-rounded text-xl text-muted-foreground" aria-hidden="true"><?= bx_h((string) $shortcut['icon']) ?></span>
                                                            <span class="text-sm font-semibold"><?= bx_h((string) $shortcut['label']) ?></span>
                                                            <span class="text-xs leading-5 text-muted-foreground"><?= bx_h((string) $shortcut['meta']) ?></span>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </section>

                                            <section class="grid gap-4 border-t pt-4 md:grid-cols-3">
                                                <div class="grid content-start gap-2">
                                                    <h3 class="text-sm font-semibold">Setup</h3>
                                                    <a class="text-sm text-muted-foreground hover:text-foreground" href="./?view=hr&amp;section=departments">Departments ↗</a>
                                                    <a class="text-sm text-muted-foreground hover:text-foreground" href="./?view=hr&amp;section=job-positions">Job Positions ↗</a>
                                                    <a class="text-sm text-muted-foreground hover:text-foreground" href="./?view=hr&amp;section=teams">Teams ↗</a>
                                                </div>
                                                <div class="grid content-start gap-2">
                                                    <h3 class="text-sm font-semibold">Employee</h3>
                                                    <a class="text-sm text-muted-foreground hover:text-foreground" href="./?view=hr&amp;section=employee-profiles">Employee Profiles ↗</a>
                                                    <a class="text-sm text-muted-foreground hover:text-foreground" href="./?view=hr&amp;section=onboarding">Onboarding ↗</a>
                                                    <a class="text-sm text-muted-foreground hover:text-foreground" href="./?view=hr&amp;section=employee-documents">Employee Documents ↗</a>
                                                </div>
                                                <div class="grid content-start gap-2">
                                                    <h3 class="text-sm font-semibold">Tools & Reports</h3>
                                                    <a class="text-sm text-muted-foreground hover:text-foreground" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=existing&amp;builder_target=employee-profiles">Form Builder ↗</a>
                                                    <a class="text-sm text-muted-foreground hover:text-foreground" href="./?view=hr&amp;section=attendance">Attendance ↗</a>
                                                    <a class="text-sm text-muted-foreground hover:text-foreground" href="./?view=hr&amp;section=hr-reports">HR Reports ↗</a>
                                                </div>
                                            </section>
                                        </section>

                                        <?php if ($activeHrBuilderOpen): ?>
                                            <div id="yovel-hr-dashboard-builder-modal" class="yovel-hr-dashboard-builder-modal yovel-form-builder-modal" role="dialog" aria-modal="true" aria-labelledby="yovel-hr-dashboard-builder-title" aria-describedby="yovel-hr-dashboard-builder-description">
                                                <section class="yovel-hr-dashboard-builder-dialog" tabindex="-1">
                                                    <header class="flex items-start justify-between gap-4 border-b bg-popover px-5 py-4">
                                                        <div>
                                                            <h3 id="yovel-hr-dashboard-builder-title" class="text-base font-semibold">HR Form Builder</h3>
                                                            <p id="yovel-hr-dashboard-builder-description" class="mt-1 text-sm leading-6 text-muted-foreground">Create new forms or maintain forms already used by HR.</p>
                                                        </div>
                                                        <a class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border bg-background hover:bg-muted" href="./?view=hr&amp;section=dashboard" aria-label="Close Form Builder" title="Close"><span class="material-symbols-rounded text-base" aria-hidden="true">close</span></a>
                                                    </header>

                                                    <div class="yovel-hr-dashboard-builder-body yovel-modal-scroll">
                                                        <div class="yovel-hr-dashboard-builder-tabs grid gap-3">
                                                            <nav class="yovel-hr-builder-feature-tabs flex gap-2 overflow-x-auto" aria-label="HR feature forms">
                                                                <?php foreach ($hrBuilderTargetSections as $sectionKey => $sectionMeta): ?>
                                                                    <a class="inline-flex h-8 shrink-0 items-center rounded-md border px-3 text-xs font-semibold <?= $activeHrBuilderTarget === (string) $sectionKey ? 'bg-secondary text-secondary-foreground' : 'bg-background text-muted-foreground hover:bg-muted hover:text-foreground' ?>" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=<?= bx_h($activeHrBuilderMode) ?>&amp;builder_target=<?= bx_h((string) $sectionKey) ?>"><?= bx_h((string) $sectionMeta['label']) ?></a>
                                                                <?php endforeach; ?>
                                                            </nav>
                                                            <div class="flex flex-wrap gap-2 border-t pt-3" role="tablist" aria-label="Form Builder mode">
                                                                <a role="tab" aria-selected="<?= $activeHrBuilderMode === 'new' && !$activeHrBuilderForm ? 'true' : 'false' ?>" class="yovel-hr-builder-mode-link inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $activeHrBuilderMode === 'new' && !$activeHrBuilderForm ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=new&amp;builder_target=<?= bx_h($activeHrBuilderTarget) ?>"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>New Form</a>
                                                                <a role="tab" aria-selected="<?= $activeHrBuilderMode === 'existing' ? 'true' : 'false' ?>" class="yovel-hr-builder-mode-link inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $activeHrBuilderMode === 'existing' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=existing&amp;builder_target=<?= bx_h($activeHrBuilderTarget) ?>"><span class="material-symbols-rounded text-base" aria-hidden="true">folder_open</span>Existing Forms</a>
                                                                <?php if ($activeHrBuilderForm && $activeHrBuilderTarget === 'employee-profiles'): ?>
                                                                    <a role="tab" aria-selected="<?= $activeHrBuilderMode === 'submit' ? 'true' : 'false' ?>" class="yovel-hr-builder-mode-link inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $activeHrBuilderMode === 'submit' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=submit&amp;builder_target=employee-profiles&amp;form=<?= bx_h((string) $activeHrBuilderForm['builder_form_key']) ?>"><span class="material-symbols-rounded text-base" aria-hidden="true">edit_document</span>Fill Form</a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <?php if ($activeHrBuilderMode === 'existing' && !$activeHrBuilderForm && $activeHrBuiltInSection === ''): ?>
                                                            <div class="grid gap-6">
                                                                <section>
                                                                    <div class="flex flex-wrap items-end justify-between gap-3 border-b pb-3">
                                                                        <div>
                                                                            <h4 class="text-sm font-semibold">Built-in <?= bx_h((string) $activeTargetMeta['label']) ?> Forms</h4>
                                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">These are the live ERP form sections currently used by this feature.</p>
                                                                        </div>
                                                                        <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($activeHrBuiltInSections) ?> forms</span>
                                                                    </div>
                                                                    <div class="grid gap-2 pt-4 md:grid-cols-2 xl:grid-cols-4">
                                                                        <?php foreach ($activeHrBuiltInSections as $sectionKey => $sectionMeta): ?>
                                                                            <a class="yovel-hr-builder-existing-card rounded-md border bg-card p-3" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=existing&amp;builder_target=<?= bx_h($activeHrBuilderTarget) ?>&amp;builtin_form=<?= bx_h((string) $sectionKey) ?>">
                                                                                <span class="flex items-center justify-between gap-2"><span class="text-sm font-semibold"><?= bx_h((string) $sectionMeta['label']) ?></span><span class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold text-secondary-foreground">BUILT-IN</span></span>
                                                                                <span class="mt-1 block text-xs leading-5 text-muted-foreground"><?= (int) $sectionMeta['field_count'] ?> fields</span>
                                                                                <span class="mt-2 block text-xs leading-5 text-muted-foreground"><?= bx_h((string) $sectionMeta['description']) ?></span>
                                                                            </a>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </section>

                                                                <section>
                                                                    <div class="flex flex-wrap items-end justify-between gap-3 border-b pb-3">
                                                                        <div>
                                                                            <h4 class="text-sm font-semibold">Custom Forms</h4>
                                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">Forms created by administrators with the blank Form Builder.</p>
                                                                        </div>
                                                                        <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($formsForTarget) ?> forms</span>
                                                                    </div>
                                                                    <div class="grid gap-2 pt-4 md:grid-cols-2 xl:grid-cols-3">
                                                                        <?php if (!$formsForTarget): ?>
                                                                            <div class="rounded-md border border-dashed p-4 md:col-span-2 xl:col-span-3">
                                                                                <p class="text-sm font-semibold">No custom forms yet</p>
                                                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">New Form opens a blank canvas for <?= bx_h((string) $activeTargetMeta['label']) ?>.</p>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <?php foreach ($formsForTarget as $form): ?>
                                                                            <a class="yovel-hr-builder-existing-card rounded-md border bg-card p-3" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=existing&amp;builder_target=<?= bx_h((string) $form['target_section']) ?>&amp;form=<?= bx_h((string) $form['builder_form_key']) ?>">
                                                                                <span class="flex items-center justify-between gap-2"><span class="text-sm font-semibold"><?= bx_h((string) $form['form_title']) ?></span><span class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold">CUSTOM</span></span>
                                                                                <span class="mt-1 block text-xs leading-5 text-muted-foreground"><?= (int) ($form['question_count'] ?? 0) ?> questions · <?= bx_h((string) $form['form_status']) ?></span>
                                                                                <?php if (trim((string) ($form['form_description'] ?? '')) !== ''): ?><span class="mt-2 block line-clamp-2 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $form['form_description']) ?></span><?php endif; ?>
                                                                            </a>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </section>
                                                            </div>
                                                        <?php elseif ($activeHrBuiltInSection !== ''): ?>
                                                            <?php $builtInMeta = $activeHrBuiltInSections[$activeHrBuiltInSection]; ?>
                                                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b pb-3">
                                                                <div>
                                                                    <p class="text-xs font-semibold uppercase text-muted-foreground">Built-in form</p>
                                                                    <h4 class="mt-1 text-base font-semibold"><?= bx_h((string) $activeTargetMeta['label']) ?> · <?= bx_h((string) $builtInMeta['label']) ?></h4>
                                                                    <p class="mt-1 text-sm text-muted-foreground"><?= bx_h((string) $builtInMeta['description']) ?></p>
                                                                </div>
                                                                <a class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=existing&amp;builder_target=<?= bx_h($activeHrBuilderTarget) ?>"><span class="material-symbols-rounded text-base" aria-hidden="true">arrow_back</span>All Forms</a>
                                                            </div>
                                                            <?php yovel_admin_render_hr_form_builder($activeHrBuilderTarget, $activeHrBuiltInSectionFields, false, 'customize', [
                                                                'section' => 'dashboard',
                                                                'return_to' => 'hr-dashboard-builder',
                                                                'return_builder_target' => $activeHrBuilderTarget,
                                                                'return_builtin_form' => $activeHrBuiltInSection,
                                                            ]); ?>
                                                        <?php elseif ($activeHrBuilderMode === 'submit' && $activeHrBuilderForm): ?>
                                                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b pb-3">
                                                                <div>
                                                                    <p class="text-xs font-semibold uppercase text-muted-foreground">Database-backed submission</p>
                                                                    <h4 class="mt-1 text-base font-semibold"><?= bx_h((string) $activeHrBuilderForm['form_title']) ?></h4>
                                                                    <p class="mt-1 text-sm text-muted-foreground">Version <?= (int) ($activeHrSubmissionVersion['version_number'] ?? 0) ?> · <?= bx_h((string) ($activeHrSubmissionVersion['form_status'] ?? 'DRAFT')) ?></p>
                                                                </div>
                                                                <a class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=existing&amp;builder_target=employee-profiles"><span class="material-symbols-rounded text-base" aria-hidden="true">arrow_back</span>All Forms</a>
                                                            </div>

                                                            <div class="grid gap-5 xl:grid-cols-[minmax(0,8fr)_minmax(16rem,4fr)]">
                                                                <form method="post" data-confirm-submit class="grid content-start gap-4">
                                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                                    <input type="hidden" name="action" value="save_hr_form_submission">
                                                                    <input type="hidden" name="section" value="dashboard">
                                                                    <input type="hidden" name="builder_form_key" value="<?= bx_h((string) $activeHrBuilderForm['builder_form_key']) ?>">
                                                                    <input type="hidden" name="form_version_key" value="<?= bx_h((string) ($activeHrSubmissionVersion['form_version_key'] ?? '')) ?>">
                                                                    <input type="hidden" name="submission_key" value="<?= bx_h((string) ($activeHrSubmission['submission_key'] ?? '')) ?>">

                                                                    <section class="grid gap-3 rounded-md border bg-card p-4">
                                                                        <div class="grid gap-1.5">
                                                                            <label class="text-xs font-medium" for="hr_submission_employee">Employee</label>
                                                                            <?php if ($activeHrSubmission): ?>
                                                                                <input type="hidden" name="subject_key" value="<?= bx_h((string) $activeHrSubmission['subject_key']) ?>">
                                                                                <div class="flex min-h-10 items-center justify-between gap-3 rounded-md border bg-muted/40 px-3 text-sm">
                                                                                    <span class="font-medium"><?= bx_h((string) ($activeHrSubmission['employee_name'] ?? 'Employee')) ?></span>
                                                                                    <span class="text-xs text-muted-foreground"><?= bx_h((string) ($activeHrSubmission['employee_code'] ?? '')) ?></span>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <select class="h-10 rounded-md border bg-background px-3 text-sm" id="hr_submission_employee" name="subject_key" required>
                                                                                    <option value="">Select employee</option>
                                                                                    <?php foreach ($hrEmployees as $employee): ?>
                                                                                        <option value="<?= bx_h((string) $employee['employee_key']) ?>" <?= $activeHrSubmissionEmployeeKey === (string) $employee['employee_key'] ? 'selected' : '' ?>><?= bx_h((string) $employee['employee_name']) ?> · <?= bx_h((string) $employee['employee_code']) ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <?php if (trim((string) ($activeHrBuilderForm['form_description'] ?? '')) !== ''): ?>
                                                                            <p class="text-sm leading-6 text-muted-foreground"><?= bx_h((string) $activeHrBuilderForm['form_description']) ?></p>
                                                                        <?php endif; ?>
                                                                    </section>

                                                                    <section class="grid gap-3">
                                                                        <?php if (!$activeHrSubmissionSchema['questions']): ?>
                                                                            <div class="rounded-md border border-dashed p-6 text-center">
                                                                                <span class="material-symbols-rounded text-2xl text-muted-foreground" aria-hidden="true">edit_note</span>
                                                                                <p class="mt-2 text-sm font-semibold">This form has no questions</p>
                                                                                <p class="mt-1 text-xs text-muted-foreground">You can save an employee-linked draft, or return to Existing Forms and add questions.</p>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <?php
                                                                            $submissionQuestionsByKey = [];
                                                                            foreach (($activeHrSubmissionSchema['questions'] ?? []) as $submissionQuestion) {
                                                                                $submissionQuestionsByKey[(string) ($submissionQuestion['key'] ?? '')] = $submissionQuestion;
                                                                            }
                                                                        ?>
                                                                        <?php foreach (($activeHrSubmissionSchema['rows'] ?? []) as $submissionLayoutRow): ?>
                                                                            <?php
                                                                                $submissionLayoutColumns = is_array($submissionLayoutRow['columns'] ?? null) ? $submissionLayoutRow['columns'] : [];
                                                                                $submissionLayoutQuestionCount = 0;
                                                                                foreach ($submissionLayoutColumns as $submissionLayoutColumn) {
                                                                                    foreach (($submissionLayoutColumn['question_keys'] ?? []) as $submissionLayoutQuestionKey) {
                                                                                        $submissionLayoutQuestionCount += isset($submissionQuestionsByKey[(string) $submissionLayoutQuestionKey]) ? 1 : 0;
                                                                                    }
                                                                                }
                                                                                if ($submissionLayoutQuestionCount === 0) {
                                                                                    continue;
                                                                                }
                                                                            ?>
                                                                            <div class="yovel-hr-google-submission-row" style="--hr-google-column-count: <?= max(1, min(3, count($submissionLayoutColumns))) ?>">
                                                                                <?php foreach ($submissionLayoutColumns as $submissionLayoutColumn): ?>
                                                                                    <div class="grid min-w-0 content-start gap-3">
                                                                                        <?php foreach (($submissionLayoutColumn['question_keys'] ?? []) as $submissionLayoutQuestionKey): ?>
                                                                                            <?php
                                                                                                $question = $submissionQuestionsByKey[(string) $submissionLayoutQuestionKey] ?? null;
                                                                                                if (!is_array($question)) {
                                                                                                    continue;
                                                                                                }
                                                                                                $questionKey = (string) ($question['key'] ?? '');
                                                                                                $questionType = (string) ($question['type'] ?? 'SHORT_TEXT');
                                                                                                $questionLabel = (string) ($question['label'] ?? 'Question');
                                                                                                $questionValue = $activeHrSubmissionValues[$questionKey] ?? ($questionType === 'CHECKBOXES' ? [] : '');
                                                                                                $questionId = 'hr_submission_' . yovel_admin_slug($questionKey);
                                                                                                $questionRequired = !empty($question['required']);
                                                                                            ?>
                                                                                            <?php if ($questionType === 'SECTION'): ?>
                                                                                                <div class="border-b pb-2 pt-3">
                                                                                                    <h5 class="text-sm font-semibold"><?= bx_h($questionLabel) ?></h5>
                                                                                                    <?php if (trim((string) ($question['help'] ?? '')) !== ''): ?><p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $question['help']) ?></p><?php endif; ?>
                                                                                                </div>
                                                                                            <?php else: ?>
                                                                                                <div class="grid gap-1.5 rounded-md border bg-background/70 p-3">
                                                                                                    <label class="text-sm font-medium" for="<?= bx_h($questionId) ?>"><?= bx_h($questionLabel) ?><?= $questionRequired ? ' *' : '' ?></label>
                                                                                                    <?php if ($questionType === 'PARAGRAPH'): ?>
                                                                                                        <textarea class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" id="<?= bx_h($questionId) ?>" name="answers[<?= bx_h($questionKey) ?>]" <?= $questionRequired ? 'required' : '' ?>><?= bx_h((string) $questionValue) ?></textarea>
                                                                                                    <?php elseif ($questionType === 'DROPDOWN'): ?>
                                                                                                        <select class="h-10 rounded-md border bg-background px-3 text-sm" id="<?= bx_h($questionId) ?>" name="answers[<?= bx_h($questionKey) ?>]" <?= $questionRequired ? 'required' : '' ?>><option value=""></option><?php foreach (($question['options'] ?? []) as $option): ?><option value="<?= bx_h((string) $option) ?>" <?= (string) $questionValue === (string) $option ? 'selected' : '' ?>><?= bx_h((string) $option) ?></option><?php endforeach; ?></select>
                                                                                                    <?php elseif ($questionType === 'CHECKBOXES'): ?>
                                                                                                        <fieldset class="grid gap-2" id="<?= bx_h($questionId) ?>"><?php foreach (($question['options'] ?? []) as $option): ?><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="answers[<?= bx_h($questionKey) ?>][]" value="<?= bx_h((string) $option) ?>" <?= is_array($questionValue) && in_array((string) $option, $questionValue, true) ? 'checked' : '' ?>><span><?= bx_h((string) $option) ?></span></label><?php endforeach; ?></fieldset>
                                                                                                    <?php else: ?>
                                                                                                        <?php $submissionInputType = ['DATE' => 'date', 'NUMBER' => 'number', 'EMAIL' => 'email', 'PHONE' => 'tel'][$questionType] ?? 'text'; ?>
                                                                                                        <input class="h-10 rounded-md border bg-background px-3 text-sm" id="<?= bx_h($questionId) ?>" name="answers[<?= bx_h($questionKey) ?>]" type="<?= bx_h($submissionInputType) ?>" <?= $questionType === 'NUMBER' ? 'step="any"' : '' ?> value="<?= bx_h((string) $questionValue) ?>" <?= $questionRequired ? 'required' : '' ?>>
                                                                                                    <?php endif; ?>
                                                                                                    <?php if (trim((string) ($question['help'] ?? '')) !== ''): ?><p class="text-xs leading-5 text-muted-foreground"><?= bx_h((string) $question['help']) ?></p><?php endif; ?>
                                                                                                </div>
                                                                                            <?php endif; ?>
                                                                                        <?php endforeach; ?>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </section>

                                                                    <div class="flex flex-wrap justify-end gap-2 border-t pt-4">
                                                                        <button type="submit" name="submission_status" value="DRAFT" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">save</span>Save Draft</button>
                                                                        <button type="submit" name="submission_status" value="SUBMITTED" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50" <?= (string) ($activeHrSubmissionVersion['form_status'] ?? '') === 'ACTIVE' ? '' : 'disabled' ?>><span class="material-symbols-rounded text-base" aria-hidden="true">send</span>Submit</button>
                                                                    </div>
                                                                </form>

                                                                <aside class="grid content-start gap-3 border-l pl-4" aria-label="Saved form submissions">
                                                                    <div>
                                                                        <h5 class="text-sm font-semibold">Saved submissions</h5>
                                                                        <p class="mt-1 text-xs leading-5 text-muted-foreground">Each record remains pinned to the form version used when it was created.</p>
                                                                    </div>
                                                                    <?php if (!$activeHrFormSubmissions): ?>
                                                                        <div class="rounded-md border border-dashed p-3 text-xs text-muted-foreground">No submissions have been saved for this form.</div>
                                                                    <?php endif; ?>
                                                                    <?php foreach ($activeHrFormSubmissions as $submission): ?>
                                                                        <a class="rounded-md border p-3 hover:bg-muted" href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_mode=submit&amp;builder_target=employee-profiles&amp;form=<?= bx_h((string) $activeHrBuilderForm['builder_form_key']) ?>&amp;submission=<?= bx_h((string) $submission['submission_key']) ?>">
                                                                            <span class="flex items-center justify-between gap-2"><span class="text-sm font-semibold"><?= bx_h((string) ($submission['employee_name'] ?? 'Employee')) ?></span><span class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold"><?= bx_h((string) $submission['submission_status']) ?></span></span>
                                                                            <span class="mt-1 block text-xs text-muted-foreground"><?= bx_h((string) ($submission['employee_code'] ?? '')) ?> · Version <?= (int) ($submission['version_number'] ?? 0) ?></span>
                                                                        </a>
                                                                    <?php endforeach; ?>
                                                                </aside>
                                                            </div>
                                                        <?php else: ?>
                                                            <form method="post" data-confirm-submit class="yovel-hr-google-builder" data-hr-google-builder>
                                                                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                                <input type="hidden" name="action" value="save_hr_builder_form">
                                                                <input type="hidden" name="section" value="dashboard">
                                                                <input type="hidden" name="return_to" value="hr-dashboard-builder">
                                                                <input type="hidden" name="builder_form_key" value="<?= bx_h((string) ($activeHrBuilderForm['builder_form_key'] ?? '')) ?>">
                                                                <input type="hidden" name="schema_json" data-hr-google-schema-json value="<?= bx_h(json_encode($activeHrBuilderSchema, JSON_UNESCAPED_SLASHES) ?: '{"version":2,"questions":[],"rows":[]}') ?>">
                                                                <div class="yovel-hr-google-workbench border-y">
                                                                    <aside class="yovel-hr-google-workbench-aside grid content-start gap-3" aria-label="Question toolbox">
                                                                        <div>
                                                                            <h4 class="text-sm font-semibold">Question Toolbox</h4>
                                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">Click a type to add it to the blank form.</p>
                                                                        </div>
                                                                        <div class="grid gap-2">
                                                                            <?php foreach ($hrBuilderQuestionTools as $tool): ?>
                                                                                <div class="yovel-hr-google-tool-button text-xs font-medium" data-hr-google-tool>
                                                                                    <button type="button" class="yovel-hr-google-tool-add" data-hr-google-add-type="<?= bx_h((string) $tool['type']) ?>" data-hr-google-tool-label="<?= bx_h((string) $tool['label']) ?>"><span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true"><?= bx_h((string) $tool['icon']) ?></span><span class="min-w-0 flex-1"><?= bx_h((string) $tool['label']) ?></span></button>
                                                                                    <button type="button" class="yovel-hr-google-tool-drag text-muted-foreground" data-hr-google-tool-drag title="Drag <?= bx_h((string) $tool['label']) ?>" aria-label="Drag <?= bx_h((string) $tool['label']) ?>"><span class="material-symbols-rounded text-base" aria-hidden="true">drag_indicator</span></button>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </aside>

                                                                    <main class="grid content-start gap-4">
                                                                        <div class="yovel-hr-google-form-head grid gap-3 rounded-md border bg-card p-4">
                                                                            <div class="grid gap-2">
                                                                                <label class="text-xs font-medium" for="hr_builder_form_title">Form title</label>
                                                                                <input class="h-10 rounded-md border bg-background px-3 text-sm font-semibold" id="hr_builder_form_title" name="form_title" maxlength="180" value="<?= bx_h((string) ($activeHrBuilderForm['form_title'] ?? 'Untitled form')) ?>" required>
                                                                            </div>
                                                                            <div class="grid gap-2">
                                                                                <label class="text-xs font-medium" for="hr_builder_form_description">Form description</label>
                                                                                <textarea class="min-h-16 rounded-md border bg-background px-3 py-2 text-sm" id="hr_builder_form_description" name="form_description" maxlength="2000" placeholder="Tell people what this form is for."><?= bx_h((string) ($activeHrBuilderForm['form_description'] ?? '')) ?></textarea>
                                                                            </div>
                                                                        </div>
                                                                        <div class="grid gap-3" data-hr-google-build-panel>
                                                                            <div class="yovel-hr-google-empty rounded-md border border-dashed bg-card/45 p-8 text-center" <?= $activeHrBuilderSchema['questions'] ? 'hidden' : '' ?> data-hr-google-empty>
                                                                                <span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true">post_add</span>
                                                                                <p class="mt-2 text-sm font-semibold">This form is blank</p>
                                                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Add a row, then place questions into one, two, or three columns.</p>
                                                                            </div>
                                                                            <div class="grid gap-3" data-hr-google-row-list></div>
                                                                        </div>
                                                                        <div class="yovel-hr-google-preview rounded-md border bg-card p-4" data-hr-google-preview-panel hidden>
                                                                            <div class="mb-4 border-b pb-3"><h4 class="text-base font-semibold" data-hr-google-preview-title><?= bx_h((string) ($activeHrBuilderForm['form_title'] ?? 'Untitled form')) ?></h4><p class="mt-1 text-sm text-muted-foreground" data-hr-google-preview-description><?= bx_h((string) ($activeHrBuilderForm['form_description'] ?? '')) ?></p></div>
                                                                            <div class="grid gap-3" data-hr-google-preview-list></div>
                                                                        </div>
                                                                    </main>

                                                                    <aside class="yovel-hr-google-workbench-aside grid content-start gap-4" aria-label="Form settings">
                                                                        <div>
                                                                            <h4 class="text-sm font-semibold">Form Settings</h4>
                                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">Choose where the form belongs, preview it, then save.</p>
                                                                        </div>
                                                                        <div class="grid gap-2">
                                                                            <label class="text-xs font-medium" for="hr_builder_target_section">HR feature</label>
                                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="hr_builder_target_section" name="target_section" data-hr-google-target-field><?php foreach ($hrBuilderTargetSections as $sectionKey => $sectionMeta): ?><option value="<?= bx_h((string) $sectionKey) ?>" <?= $activeHrBuilderTarget === (string) $sectionKey ? 'selected' : '' ?>><?= bx_h((string) $sectionMeta['label']) ?></option><?php endforeach; ?></select>
                                                                        </div>
                                                                        <div class="grid gap-2">
                                                                            <label class="text-xs font-medium" for="hr_builder_form_status">Status</label>
                                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="hr_builder_form_status" name="form_status"><?php foreach (['DRAFT', 'ACTIVE', 'ARCHIVED'] as $status): ?><option value="<?= bx_h($status) ?>" <?= (string) ($activeHrBuilderForm['form_status'] ?? 'DRAFT') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option><?php endforeach; ?></select>
                                                                        </div>
                                                                        <div class="inline-flex w-fit rounded-md border bg-background p-1">
                                                                            <button type="button" class="yovel-hr-google-mode-button rounded-sm px-3 py-1.5 text-xs font-semibold" data-hr-google-mode="build" aria-pressed="true">Build</button>
                                                                            <button type="button" class="yovel-hr-google-mode-button rounded-sm px-3 py-1.5 text-xs font-semibold" data-hr-google-mode="preview" aria-pressed="false">Preview</button>
                                                                        </div>
                                                                        <div class="grid gap-2 border-t pt-4">
                                                                            <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" data-hr-google-add-row><span class="material-symbols-rounded text-base" aria-hidden="true">view_column_2</span>Add Row</button>
                                                                            <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" data-hr-google-add-question><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>Add Question</button>
                                                                            <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" data-hr-google-add-section><span class="material-symbols-rounded text-base" aria-hidden="true">view_agenda</span>Add Section</button>
                                                                            <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"><span class="material-symbols-rounded text-base" aria-hidden="true">save</span>Save Form</button>
                                                                        </div>
                                                                    </aside>
                                                                </div>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>

                                                    <footer class="m-0 flex w-full items-center justify-between gap-3 border-t bg-popover px-5 py-3">
                                                        <span class="text-xs text-muted-foreground"><?= count($activeHrBuiltInSections) ?> built-in forms · <?= count($formsForTarget) ?> custom forms</span>
                                                        <a class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="./?view=hr&amp;section=dashboard">Close</a>
                                                    </footer>
                                                </section>
                                            </div>
                                        <?php endif; ?>
