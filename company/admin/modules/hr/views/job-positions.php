<?php
/** HR view variables are prepared by bootstrap/controller.php. */
?>
                                        <?php
                                            $hrJobPositions = $hrData['jobPositions'] ?? [];
                                            $hrJobPositionSummary = [
                                                'total' => count($hrJobPositions),
                                                'active' => count(array_filter($hrJobPositions, static fn (array $row): bool => (string) ($row['job_position_status'] ?? '') === 'ACTIVE')),
                                                'assigned' => array_sum(array_map(static fn (array $row): int => (int) ($row['assigned_employee_count'] ?? 0), $hrJobPositions)),
                                            ];
                                        ?>
                                        <div class="yovel-hr-two-panel yovel-hr-approved-layout yovel-job-position-layout grid min-h-0 gap-3 xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]">
                                            <section class="yovel-hr-panel yovel-hr-surface min-w-0 overflow-hidden flex flex-col rounded-lg border bg-card" data-job-position-tour-target="position-list">
                                                <div class="yovel-hr-surface-header border-b px-4 py-3">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <h3 class="text-base font-semibold tracking-normal">Job positions</h3>
                                                            <p class="mt-1 text-sm leading-6 text-muted-foreground">Designation-style HR roles used by employee profiles for <?= bx_h($companyName) ?>.</p>
                                                        </div>
                                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                                            <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= $hrJobPositionSummary['total'] ?> positions</span>
                                                            <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= $hrJobPositionSummary['active'] ?> active</span>
                                                            <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= $hrJobPositionSummary['assigned'] ?> assignments</span>
                                                            <button type="button" id="yovel-job-position-modal-open" data-record-modal-open="yovel-job-position-modal" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" data-job-position-tour-target="add-position">Add Position</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="yovel-hr-panel-body grid content-start gap-3 p-3">
                                                    <section id="yovel-job-position-setup-card" class="yovel-erpnext-hub yovel-erpnext-card rounded-lg p-3" data-job-position-setup-card data-job-position-tour-target="setup-card">
                                                        <div class="flex flex-wrap items-start justify-between gap-4">
                                                            <div>
                                                                <h3 class="text-base font-semibold tracking-normal">Let's Set Up Job Positions.</h3>
                                                                <p class="mt-1 text-sm leading-6 text-muted-foreground">Designations, assignments, and responsibilities.</p>
                                                            </div>
                                                            <button type="button" id="yovel-job-position-setup-dismiss" class="yovel-erpnext-soft-button inline-flex h-8 items-center rounded-md px-3 text-sm font-medium">Dismiss</button>
                                                        </div>
                                                        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(14rem,0.82fr)_minmax(0,1.18fr)]">
                                                            <div class="grid content-start gap-1" data-job-position-tour-target="setup-steps">
                                                                <?php foreach ([
                                                                    ['label' => 'Confirm HR designation scope', 'state' => 'done'],
                                                                    ['label' => 'Create Job Position', 'state' => 'active'],
                                                                    ['label' => 'Customize Job Position Form', 'state' => 'ready'],
                                                                    ['label' => 'Assign employees to positions', 'state' => 'ready'],
                                                                    ['label' => 'Review position coverage', 'state' => 'ready'],
                                                                ] as $step): ?>
                                                                    <div class="yovel-erpnext-step flex items-center justify-between gap-3 px-2.5 py-2 text-sm" <?= $step['state'] === 'active' ? 'aria-current="step"' : '' ?>>
                                                                        <span class="flex min-w-0 items-center gap-2">
                                                                            <span class="yovel-erpnext-step-icon inline-flex size-4 shrink-0 items-center justify-center rounded-full text-[10px]"><?= $step['state'] === 'done' ? '✓' : '' ?></span>
                                                                            <span class="truncate"><?= bx_h((string) $step['label']) ?></span>
                                                                        </span>
                                                                        <?php if ($step['state'] === 'active'): ?><button type="button" class="yovel-erpnext-muted-button rounded-md px-2 py-1 text-xs" data-job-position-tour-start>Skip</button><?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="min-w-0" data-job-position-tour-target="setup-detail">
                                                                <h4 class="text-base font-semibold tracking-normal">Create Job Position.</h4>
                                                                <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">Create designation records that can be assigned to employee profiles. Job positions keep role titles, status, responsibility notes, and custom position fields in one HR scope.</p>
                                                                <div class="mt-4 flex flex-wrap gap-2">
                                                                    <button type="button" id="yovel-job-position-tour-start" class="yovel-erpnext-soft-button inline-flex h-9 items-center rounded-md px-3 text-sm font-medium" data-job-position-tour-start>Show Tour</button>
                                                                    <button type="button" class="yovel-erpnext-soft-button inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium" data-record-modal-open="yovel-job-position-modal" data-job-position-open-from-setup>Add Position</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </section>
                                                    <div class="grid gap-3 rounded-md bg-background/40 p-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                                                        <div class="grid gap-2 sm:grid-cols-3">
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="ID" aria-label="Filter job position ID">
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Position Name" aria-label="Filter job position name">
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Status" aria-label="Filter job position status">
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <button type="button" class="inline-flex h-9 items-center rounded-md border bg-secondary px-3 text-sm font-medium text-secondary-foreground">Filters <span class="ml-2 rounded-full bg-background px-1.5 text-xs">0</span></button>
                                                            <button type="button" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Last Updated On</button>
                                                        </div>
                                                    </div>
                                                <?php if (!$hrJobPositions): ?>
                                                    <div class="rounded-md bg-muted/40 p-4">
                                                        <p class="text-sm font-semibold">No job positions yet</p>
                                                        <p class="mt-1 text-xs leading-5 text-muted-foreground">Create the first HR job position before assigning designations on employee profiles.</p>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="overflow-auto rounded-md">
                                                        <table class="yovel-job-position-table w-full text-left text-sm">
                                                            <thead class="sticky top-0 z-10 border-b bg-card text-xs text-muted-foreground">
                                                                <tr>
                                                                    <th scope="col" class="w-10 px-4 py-3 font-medium"><span class="sr-only">Select</span></th>
                                                                    <th scope="col" class="px-4 py-3 font-medium">Position</th>
                                                                    <th scope="col" class="px-4 py-3 font-medium">Assigned</th>
                                                                    <th scope="col" class="px-4 py-3 font-medium">Status</th>
                                                                    <th scope="col" class="px-4 py-3 text-right font-medium">Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y">
                                                                <?php foreach ($hrJobPositions as $jobPosition): ?>
                                                                    <?php $jobPositionKey = (string) $jobPosition['job_position_key']; ?>
                                                                    <tr data-job-position-drop-target data-job-position-key="<?= bx_h($jobPositionKey) ?>" class="transition-colors">
                                                                        <td class="px-4 py-4"><input type="checkbox" class="size-4 rounded border" aria-label="Select <?= bx_h((string) $jobPosition['job_position_name']) ?>"></td>
                                                                        <td class="px-4 py-4">
                                                                            <p class="truncate font-medium"><?= bx_h((string) $jobPosition['job_position_name']) ?></p>
                                                                            <p class="mt-1 truncate text-xs text-muted-foreground"><?= bx_h((string) $jobPosition['job_position_code']) ?></p>
                                                                            <?php if ((string) ($jobPosition['job_position_description'] ?? '') !== ''): ?><p class="mt-1 truncate text-xs text-muted-foreground" title="<?= bx_h((string) $jobPosition['job_position_description']) ?>"><?= bx_h((string) $jobPosition['job_position_description']) ?></p><?php endif; ?>
                                                                        </td>
                                                                        <td class="px-4 py-4 text-xs text-muted-foreground"><?= (int) ($jobPosition['assigned_employee_count'] ?? 0) ?> employees</td>
                                                                        <td class="px-4 py-4"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $jobPosition['job_position_status']) ?></span></td>
                                                                        <td class="px-4 py-4">
                                                                            <div class="flex flex-nowrap justify-end gap-1.5">
                                                                                <span class="yovel-employee-drop-indicator text-xs font-medium" data-job-position-drop-indicator aria-live="polite">Apply feature here</span>
                                                                                <a class="yovel-action-icon yovel-action-icon--view" href="./?view=hr&amp;section=job-positions&amp;edit=<?= bx_h($jobPositionKey) ?>&amp;job_position_section=overview" title="View <?= bx_h((string) $jobPosition['job_position_code']) ?>" aria-label="View <?= bx_h((string) $jobPosition['job_position_name']) ?>">
                                                                                    <span class="material-symbols-rounded" aria-hidden="true">visibility</span>
                                                                                    <span class="sr-only">View</span>
                                                                                </a>
                                                                                <?php foreach ([['INACTIVE', 'Deactivate'], ['ACTIVE', 'Restore'], ['DELETED', 'Delete']] as $jobPositionAction): ?>
                                                                                    <?php if ((string) $jobPosition['job_position_status'] === $jobPositionAction[0]) { continue; } ?>
                                                                                    <?php
                                                                                        $jobPositionActionIcon = [
                                                                                            'ACTIVE' => 'check_circle',
                                                                                            'INACTIVE' => 'block',
                                                                                            'DELETED' => 'delete',
                                                                                        ][$jobPositionAction[0]] ?? 'settings';
                                                                                        $jobPositionActionTone = [
                                                                                            'ACTIVE' => 'success',
                                                                                            'INACTIVE' => 'warning',
                                                                                            'DELETED' => 'danger',
                                                                                        ][$jobPositionAction[0]] ?? 'archive';
                                                                                    ?>
                                                                                    <form method="post" data-confirm-submit>
                                                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                                                        <input type="hidden" name="action" value="set_hr_job_position_status">
                                                                                        <input type="hidden" name="section" value="job-positions">
                                                                                        <input type="hidden" name="job_position_key" value="<?= bx_h($jobPositionKey) ?>">
                                                                                        <input type="hidden" name="job_position_status" value="<?= bx_h($jobPositionAction[0]) ?>">
                                                                                        <button type="submit" class="yovel-action-icon yovel-action-icon--<?= bx_h($jobPositionActionTone) ?>" title="<?= bx_h($jobPositionAction[1] . ' ' . (string) $jobPosition['job_position_code']) ?>" aria-label="<?= bx_h($jobPositionAction[1] . ' ' . (string) $jobPosition['job_position_name']) ?>">
                                                                                            <span class="material-symbols-rounded" aria-hidden="true"><?= bx_h($jobPositionActionIcon) ?></span>
                                                                                            <span class="sr-only"><?= bx_h($jobPositionAction[1]) ?></span>
                                                                                        </button>
                                                                                    </form>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                                </div>
                                            </section>

                                            <aside class="yovel-hr-panel yovel-hr-surface min-w-0 overflow-hidden flex flex-col rounded-lg border bg-card">
                                                <div class="yovel-hr-surface-header yovel-hr-feature-header border-b px-4 py-3">
                                                    <h3 class="text-base font-semibold tracking-normal">Job Position Tools</h3>
                                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">Quick actions and the sections used by the position form.</p>
                                                </div>
                                                <div class="yovel-hr-panel-body yovel-job-position-side-panel grid content-start p-3" data-job-position-tour-target="directory">
                                                    <details class="yovel-job-position-control-group" open>
                                                        <summary class="flex items-center justify-between gap-3 px-3 py-2.5">
                                                            <span>
                                                                <span class="block text-sm font-semibold">Quick Actions</span>
                                                                <span class="mt-0.5 block text-xs text-muted-foreground">Common position tasks</span>
                                                            </span>
                                                            <span class="material-symbols-rounded yovel-job-position-control-chevron text-base text-muted-foreground" aria-hidden="true">expand_more</span>
                                                        </summary>
                                                        <div class="yovel-job-position-shortcuts border-t p-3">
                                                            <button type="button" data-record-modal-open="yovel-job-position-modal" data-job-position-open-shortcut>Add Position ↗</button>
                                                            <a href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_target=job-positions&amp;builder_mode=existing" data-job-position-tour-target="forms-dashboard">Manage Forms ↗</a>
                                                            <a href="./?view=hr&amp;section=employee-profiles">Employee Profiles ↗</a>
                                                        </div>
                                                    </details>
                                                    <details class="yovel-job-position-control-group" open>
                                                        <summary class="flex items-center justify-between gap-3 px-3 py-2.5">
                                                            <span>
                                                                <span class="block text-sm font-semibold">Form Sections</span>
                                                                <span class="mt-0.5 block text-xs text-muted-foreground">Open or reorder sections</span>
                                                            </span>
                                                            <span class="material-symbols-rounded yovel-job-position-control-chevron text-base text-muted-foreground" aria-hidden="true">expand_more</span>
                                                        </summary>
                                                        <div id="yovel-job-position-widget-board" class="grid content-start gap-2 border-t p-3" aria-label="Job position feature widgets" data-job-position-tour-target="feature-board">
                                                            <?php foreach ([
                                                                ['key' => 'overview', 'section' => 'overview', 'title' => 'Overview', 'meta' => 'Position code, name, and status'],
                                                                ['key' => 'role-details', 'section' => 'details', 'title' => 'Role Details', 'meta' => 'Description, scope, responsibilities, and custom fields'],
                                                            ] as $widget): ?>
                                                                <section class="yovel-widget-item rounded-md bg-muted/40 p-3 transition-colors hover:bg-muted/70" draggable="true" data-job-position-widget-key="<?= bx_h((string) $widget['key']) ?>" aria-grabbed="false">
                                                                    <div class="flex items-start justify-between gap-3">
                                                                        <div class="min-w-0">
                                                                            <button type="button" class="yovel-job-position-feature-button rounded-sm px-1.5 py-0.5 text-left text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring" data-job-position-section-target="<?= bx_h((string) $widget['section']) ?>" aria-pressed="<?= (string) $widget['section'] === 'overview' ? 'true' : 'false' ?>"><?= bx_h((string) $widget['title']) ?></button>
                                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $widget['meta']) ?></p>
                                                                        </div>
                                                                        <div class="flex shrink-0 items-center gap-1">
                                                                            <button type="button" class="yovel-widget-move-up inline-flex size-7 items-center justify-center rounded-md hover:bg-muted hover:text-foreground" aria-label="Move <?= bx_h((string) $widget['title']) ?> up"><span class="material-symbols-rounded text-base" aria-hidden="true">keyboard_arrow_up</span></button>
                                                                            <button type="button" class="yovel-widget-move-down inline-flex size-7 items-center justify-center rounded-md hover:bg-muted hover:text-foreground" aria-label="Move <?= bx_h((string) $widget['title']) ?> down"><span class="material-symbols-rounded text-base" aria-hidden="true">keyboard_arrow_down</span></button>
                                                                            <span class="material-symbols-rounded inline-flex size-7 cursor-grab items-center justify-center text-base text-muted-foreground" aria-hidden="true">drag_indicator</span>
                                                                        </div>
                                                                    </div>
                                                                </section>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </details>
                                                </div>
                                            </aside>
                                        </div>

                                        <div id="yovel-job-position-tour" class="yovel-tour-overlay fixed inset-0 bg-black/45 p-4 backdrop-blur-[1px]" role="dialog" aria-modal="true" aria-labelledby="yovel-job-position-tour-title" aria-describedby="yovel-job-position-tour-body" hidden>
                                            <section class="yovel-tour-popover absolute bottom-5 right-5 flex w-[min(24rem,calc(100vw-2rem))] flex-col rounded-lg border p-4">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p id="yovel-job-position-tour-count" class="text-xs font-medium text-muted-foreground">Step 1 of 6</p>
                                                        <h3 id="yovel-job-position-tour-title" class="mt-1 text-base font-semibold tracking-normal">Job Position setup</h3>
                                                    </div>
                                                    <button type="button" id="yovel-job-position-tour-close" class="yovel-tour-soft-button inline-flex size-8 shrink-0 items-center justify-center rounded-md border text-sm" aria-label="Close job position tour">×</button>
                                                </div>
                                                <p id="yovel-job-position-tour-body" class="mt-3 text-sm leading-6 text-muted-foreground">Follow the setup card to create and organize job positions.</p>
                                                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                                                    <button type="button" id="yovel-job-position-tour-skip" class="yovel-tour-muted-button inline-flex h-8 items-center rounded-md px-2 text-sm font-medium">Skip</button>
                                                    <div class="flex gap-2">
                                                        <button type="button" id="yovel-job-position-tour-back" class="yovel-tour-soft-button inline-flex h-8 items-center rounded-md border px-3 text-sm font-medium">Back</button>
                                                        <button type="button" id="yovel-job-position-tour-next" class="yovel-tour-primary-button inline-flex h-8 items-center rounded-md px-3 text-sm font-medium">Next</button>
                                                    </div>
                                                </div>
                                            </section>
                                        </div>

                                        <div id="yovel-job-position-form-builder-modal" class="yovel-employee-modal yovel-form-builder-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-job-position-form-builder-modal-title" aria-describedby="yovel-job-position-form-builder-modal-description" hidden>
                                            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-6xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                                <div class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4">
                                                    <div>
                                                        <h3 id="yovel-job-position-form-builder-modal-title" class="text-base font-semibold tracking-normal">Customize Job Position Form</h3>
                                                        <p id="yovel-job-position-form-builder-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Adjust Job Position fields, sections, order, visibility, and validation.</p>
                                                    </div>
                                                    <button type="button" id="yovel-job-position-form-builder-modal-close" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close job position form builder">×</button>
                                                </div>
                                                <div class="yovel-hr-panel-body yovel-modal-scroll yovel-form-builder-scroll p-5 pt-0">
                                                    <?php yovel_admin_render_hr_form_builder('job-positions', $jobPositionFormFields, false, 'customize'); ?>
                                                </div>
                                                <div class="flex shrink-0 items-center justify-between gap-3 border-t px-5 py-4">
                                                    <p class="text-xs leading-5 text-muted-foreground"><?= count($jobPositionFormFields) ?> Job Position fields available.</p>
                                                    <button type="button" id="yovel-job-position-form-builder-modal-cancel" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Close</button>
                                                </div>
                                            </section>
                                        </div>

                                        <div id="yovel-job-position-form-create-modal" class="yovel-employee-modal yovel-form-builder-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-job-position-form-create-modal-title" aria-describedby="yovel-job-position-form-create-modal-description" hidden>
                                            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-6xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                                <div class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4">
                                                    <div>
                                                        <h3 id="yovel-job-position-form-create-modal-title" class="text-base font-semibold tracking-normal">Job Position Form Builder</h3>
                                                        <p id="yovel-job-position-form-create-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Create Job Position fields and place them into ERP sections.</p>
                                                    </div>
                                                    <button type="button" id="yovel-job-position-form-create-modal-close" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close job position form builder">×</button>
                                                </div>
                                                <div class="yovel-hr-panel-body yovel-modal-scroll yovel-form-builder-scroll p-5 pt-0">
                                                    <?php yovel_admin_render_hr_form_builder('job-positions', $jobPositionFormFields, false, 'builder'); ?>
                                                </div>
                                                <div class="flex shrink-0 items-center justify-between gap-3 border-t px-5 py-4">
                                                    <p class="text-xs leading-5 text-muted-foreground">New Job Position fields save into this company's HR form schema.</p>
                                                    <button type="button" id="yovel-job-position-form-create-modal-cancel" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Close</button>
                                                </div>
                                            </section>
                                        </div>

                                        <div id="yovel-job-position-modal" data-record-modal <?= $editHrJobPosition ? 'data-record-modal-open-on-load' : '' ?> class="yovel-employee-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-job-position-modal-title" aria-describedby="yovel-job-position-modal-description" <?= $editHrJobPosition ? '' : 'hidden' ?>>
                                            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-5xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                                <div class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4">
                                                    <div>
                                                        <h3 id="yovel-job-position-modal-title" class="flex flex-wrap items-center gap-2 text-base font-semibold tracking-normal">
                                                            <span><?= $editHrJobPosition ? bx_h((string) $editHrJobPosition['job_position_name']) : 'Add Job Position' ?></span>
                                                            <?php if ($editHrJobPosition): ?><span class="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground"><?= bx_h((string) $editHrJobPosition['job_position_status']) ?></span><?php endif; ?>
                                                        </h3>
                                                        <p id="yovel-job-position-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Job position master data follows the HR Department scope.</p>
                                                    </div>
                                                    <button type="button" id="yovel-job-position-modal-close" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close job position form">×</button>
                                                </div>
                                                <form id="yovel-job-position-form" method="post" data-confirm-submit class="yovel-hr-panel-body yovel-modal-scroll p-0">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="save_hr_job_position">
                                                    <input type="hidden" name="section" value="job-positions">
                                                    <input type="hidden" name="job_position_key" value="<?= bx_h((string) ($editHrJobPosition['job_position_key'] ?? '')) ?>">
                                                    <nav class="sticky top-0 z-20 flex gap-2 overflow-x-auto border-b bg-card px-5 py-3" role="tablist" aria-label="Job position sections">
                                                        <?php foreach (['overview' => 'Overview', 'details' => 'Details'] as $sectionKey => $sectionLabel): ?>
                                                            <button type="button" class="yovel-job-position-feature-button inline-flex h-8 shrink-0 items-center rounded-md border bg-background px-3 text-xs font-medium hover:bg-muted" role="tab" data-job-position-section-target="<?= bx_h($sectionKey) ?>" aria-selected="<?= $activeJobPositionModalSection === $sectionKey ? 'true' : 'false' ?>" aria-pressed="<?= $activeJobPositionModalSection === $sectionKey ? 'true' : 'false' ?>"><?= bx_h($sectionLabel) ?></button>
                                                        <?php endforeach; ?>
                                                    </nav>
                                                    <div class="grid gap-6 p-5">
                                                        <section data-job-position-modal-section="overview" class="grid gap-4" <?= $activeJobPositionModalSection === 'overview' ? '' : 'hidden' ?>>
                                                            <div>
                                                                <h4 class="text-sm font-semibold">Overview</h4>
                                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Core job position identity, status, and assignment readiness.</p>
                                                            </div>
                                                            <div class="grid gap-3 lg:grid-cols-3">
                                                                <div class="grid gap-1.5">
                                                                    <label class="text-xs font-medium" for="job_position_code">Position code</label>
                                                                    <input class="h-9 rounded-md border bg-background px-3 text-sm" id="job_position_code" name="job_position_code" value="<?= bx_h((string) ($editHrJobPosition['job_position_code'] ?? '')) ?>" maxlength="80" pattern="[A-Za-z0-9_.-]{2,80}" required>
                                                                </div>
                                                                <div class="grid gap-1.5 lg:col-span-2">
                                                                    <label class="text-xs font-medium" for="job_position_name">Position name</label>
                                                                    <input class="h-9 rounded-md border bg-background px-3 text-sm" id="job_position_name" name="job_position_name" value="<?= bx_h((string) ($editHrJobPosition['job_position_name'] ?? '')) ?>" maxlength="160" required>
                                                                </div>
                                                                <div class="grid gap-1.5">
                                                                    <label class="text-xs font-medium" for="job_position_status">Status</label>
                                                                    <select class="h-9 rounded-md border bg-background px-3 text-sm" id="job_position_status" name="job_position_status">
                                                                        <?php foreach (['DRAFT', 'ACTIVE', 'INACTIVE'] as $status): ?>
                                                                            <option value="<?= bx_h($status) ?>" <?= (string) ($editHrJobPosition['job_position_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="grid gap-3 lg:grid-cols-2">
                                                                <?php yovel_admin_render_hr_custom_field_controls($jobPositionFormFields, $jobPositionCustomValues, 'overview'); ?>
                                                            </div>
                                                        </section>
                                                        <section data-job-position-modal-section="details" class="grid gap-4" <?= $activeJobPositionModalSection === 'details' ? '' : 'hidden' ?>>
                                                            <div>
                                                                <h4 class="text-sm font-semibold">Role Details</h4>
                                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Description, responsibility notes, and job position custom fields.</p>
                                                            </div>
                                                            <div class="grid gap-3">
                                                                <div class="grid gap-1.5">
                                                                    <label class="text-xs font-medium" for="job_position_description">Description</label>
                                                                    <textarea class="min-h-32 rounded-md border bg-background px-3 py-2 text-sm" id="job_position_description" name="job_position_description" maxlength="5000"><?= bx_h((string) ($editHrJobPosition['job_position_description'] ?? '')) ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="grid gap-3 lg:grid-cols-2">
                                                                <?php yovel_admin_render_hr_custom_field_controls($jobPositionFormFields, $jobPositionCustomValues, 'details'); ?>
                                                            </div>
                                                        </section>
                                                    </div>
                                                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t px-5 py-4">
                                                        <p class="text-xs leading-5 text-muted-foreground">Job position changes require confirmation before saving.</p>
                                                        <div class="flex gap-2">
                                                            <button type="button" id="yovel-job-position-modal-cancel" data-record-modal-close class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><?= $editHrJobPosition ? 'Close' : 'Cancel' ?></button>
                                                            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Job Position</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </section>
                                        </div>
