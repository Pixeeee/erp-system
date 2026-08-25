<?php
/** HR view variables are prepared by bootstrap/controller.php. */
?>
                                        <?php
                                            $hrDepartmentRows = $hrData['departments'] ?? [];
                                            $hrDepartmentMasters = array_values(array_filter($hrData['departmentMasters'] ?? [], static fn (array $row): bool => (string) ($row['department_status'] ?? '') === 'ACTIVE'));
                                            $hrDepartmentBranches = $hrData['branches'] ?? [];
                                            $selectedHrBranchKey = (string) ($editHrDepartment['branch_key'] ?? ($hrDepartmentBranches[0]['branch_key'] ?? ''));
                                            $selectedHrSource = (string) ($editHrDepartment['department_source'] ?? 'ERP_DEFAULT');
                                            $selectedHrTemplateKey = (string) ($editHrDepartment['default_department_key'] ?? ($hrDepartmentMasters[0]['department_master_key'] ?? ''));
                                            $selectedHrTemplate = yovel_admin_find_record($hrDepartmentMasters, 'department_master_key', $selectedHrTemplateKey) ?? ($hrDepartmentMasters[0] ?? []);
                                            $hrDepartmentSummary = [
                                                'total' => count($hrDepartmentRows),
                                                'active' => count(array_filter($hrDepartmentRows, static fn (array $row): bool => (string) ($row['department_status'] ?? '') === 'ACTIVE')),
                                                'branches' => count($hrDepartmentBranches),
                                            ];
                                        ?>
                                        <div class="yovel-hr-two-panel yovel-department-layout grid min-h-0 gap-3 xl:grid-cols-[minmax(0,8fr)_minmax(18rem,4fr)]">
                                            <section class="yovel-hr-panel yovel-hr-surface flex flex-col rounded-lg border bg-card">
                                                <div class="yovel-hr-surface-header border-b px-5 py-4">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <h3 class="text-base font-semibold tracking-normal">Departments</h3>
                                                            <p class="mt-1 text-sm leading-6 text-muted-foreground">Branch departments used by HR assignments for <?= bx_h($companyName) ?>.</p>
                                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                                <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= $hrDepartmentSummary['total'] ?> departments</span>
                                                                <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= $hrDepartmentSummary['active'] ?> active</span>
                                                                <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= $hrDepartmentSummary['branches'] ?> branches</span>
                                                            </div>
                                                        </div>
                                                        <button type="button" id="yovel-department-modal-open" class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">add</span>Add Department</button>
                                                    </div>
                                                </div>
                                                <div class="grid gap-3 border-b p-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                                                    <div class="grid gap-2 sm:grid-cols-3">
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Department ID" aria-label="Filter department ID">
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Name" aria-label="Filter department name">
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Branch" aria-label="Filter department branch">
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" class="inline-flex h-9 items-center rounded-md border bg-secondary px-3 text-sm font-medium text-secondary-foreground">Filters <span class="ml-2 rounded-full bg-background px-1.5 text-xs">0</span></button>
                                                        <button type="button" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Last Updated On</button>
                                                    </div>
                                                </div>
                                                <?php if (!$hrDepartmentRows): ?>
                                                    <div class="yovel-hr-panel-body p-5">
                                                        <div class="rounded-md bg-muted/40 p-4">
                                                            <p class="text-sm font-semibold">No departments yet</p>
                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">Create the first HR department for <?= bx_h($companyName) ?>.</p>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="yovel-hr-panel-body overflow-auto">
                                                        <table class="yovel-department-table w-full text-left text-sm">
                                                            <thead class="sticky top-0 z-10 border-b bg-card text-xs text-muted-foreground">
                                                            <tr>
                                                                <th scope="col" class="w-10 px-4 py-3 font-medium"><span class="sr-only">Select</span></th>
                                                                <th scope="col" class="w-[38%] px-3 py-3 font-medium">Department</th>
                                                                <th scope="col" class="w-[18%] px-3 py-3 font-medium">Branch</th>
                                                                <th scope="col" class="w-[12%] px-3 py-3 font-medium">Status</th>
                                                                <th scope="col" class="w-40 px-3 py-3 text-right font-medium">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y">
                                                            <?php foreach ($hrDepartmentRows as $department): ?>
                                                                <tr data-department-drop-target data-department-key="<?= bx_h((string) $department['department_key']) ?>" class="transition-colors">
                                                                    <td class="px-4 py-3"><input type="checkbox" class="size-4 rounded border" aria-label="Select <?= bx_h((string) $department['department_name']) ?>"></td>
                                                                    <td class="px-3 py-3">
                                                                        <p class="truncate font-medium"><?= bx_h((string) $department['department_name']) ?></p>
                                                                        <p class="truncate text-xs text-muted-foreground"><?= bx_h((string) $department['department_code']) ?> · <?= (string) $department['department_source'] === 'ERP_DEFAULT' ? 'Standard' : 'Custom' ?> · <?= bx_h((string) $department['department_type']) ?></p>
                                                                        <?php if ((string) $department['department_description'] !== ''): ?><p class="mt-1 truncate text-xs text-muted-foreground" title="<?= bx_h((string) $department['department_description']) ?>"><?= bx_h((string) $department['department_description']) ?></p><?php endif; ?>
                                                                    </td>
                                                                    <td class="px-3 py-3"><p class="truncate font-medium"><?= bx_h((string) ($department['branch_name'] ?: 'Unavailable')) ?></p><p class="truncate text-xs text-muted-foreground"><?= bx_h((string) ($department['branch_code'] ?? '')) ?></p></td>
                                                                    <td class="px-3 py-3"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $department['department_status']) ?></span></td>
                                                                    <td class="px-3 py-3">
                                                                        <div class="flex flex-nowrap justify-end gap-1.5">
                                                                            <span class="yovel-employee-drop-indicator text-xs font-medium" data-department-drop-indicator aria-live="polite">Apply feature here</span>
                                                                            <a class="yovel-action-icon yovel-action-icon--view" href="./?view=hr&amp;section=departments&amp;edit=<?= bx_h((string) $department['department_key']) ?>&amp;department_section=overview" title="View <?= bx_h((string) $department['department_code']) ?>" aria-label="View <?= bx_h((string) $department['department_name']) ?>">
                                                                                <span class="material-symbols-rounded" aria-hidden="true">visibility</span>
                                                                                <span class="sr-only">View</span>
                                                                            </a>
                                                                            <?php foreach ([['INACTIVE', 'Deactivate'], ['ACTIVE', 'Restore'], ['ARCHIVED', 'Archive'], ['DELETED', 'Delete']] as $departmentAction): ?>
                                                                                <?php if ((string) $department['department_status'] === $departmentAction[0]) { continue; } ?>
                                                                                <?php
                                                                                    $departmentActionIcon = [
                                                                                        'ACTIVE' => 'check_circle',
                                                                                        'INACTIVE' => 'block',
                                                                                        'ARCHIVED' => 'archive',
                                                                                        'DELETED' => 'delete',
                                                                                    ][$departmentAction[0]] ?? 'settings';
                                                                                    $departmentActionTone = [
                                                                                        'ACTIVE' => 'success',
                                                                                        'INACTIVE' => 'warning',
                                                                                        'ARCHIVED' => 'archive',
                                                                                        'DELETED' => 'danger',
                                                                                    ][$departmentAction[0]] ?? 'archive';
                                                                                ?>
                                                                                <form method="post" data-confirm-submit>
                                                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                                                    <input type="hidden" name="action" value="set_hr_department_status">
                                                                                    <input type="hidden" name="section" value="departments">
                                                                                    <input type="hidden" name="department_key" value="<?= bx_h((string) $department['department_key']) ?>">
                                                                                    <input type="hidden" name="department_status" value="<?= bx_h($departmentAction[0]) ?>">
                                                                                    <button type="submit" class="yovel-action-icon yovel-action-icon--<?= bx_h($departmentActionTone) ?>" title="<?= bx_h($departmentAction[1] . ' ' . (string) $department['department_code']) ?>" aria-label="<?= bx_h($departmentAction[1] . ' ' . (string) $department['department_name']) ?>">
                                                                                        <span class="material-symbols-rounded" aria-hidden="true"><?= bx_h($departmentActionIcon) ?></span>
                                                                                        <span class="sr-only"><?= bx_h($departmentAction[1]) ?></span>
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
                                            </section>

                                            <aside class="yovel-hr-panel yovel-hr-surface flex flex-col rounded-lg border bg-card">
                                                <div class="yovel-hr-surface-header yovel-hr-feature-header border-b px-5 py-4">
                                                    <h3 class="text-base font-semibold tracking-normal">Department Sections</h3>
                                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">Open or reorder the sections used by the department form.</p>
                                                </div>
                                                <div id="yovel-department-widget-board" class="yovel-hr-panel-body grid content-start px-4" aria-label="Department feature widgets">
                                                    <?php foreach ([
                                                        ['key' => 'overview', 'section' => 'overview', 'icon' => 'domain', 'title' => 'Overview', 'meta' => 'Code, name, source, and status'],
                                                        ['key' => 'branch-details', 'section' => 'details', 'icon' => 'account_tree', 'title' => 'Branch & Details', 'meta' => 'Branch, type, description, and custom fields'],
                                                    ] as $widget): ?>
                                                        <section class="yovel-department-feature-row yovel-widget-item flex items-center gap-3 border-b py-3 last:border-b-0" draggable="true" data-department-widget-key="<?= bx_h((string) $widget['key']) ?>" aria-grabbed="false">
                                                            <span class="material-symbols-rounded inline-flex size-5 shrink-0 items-center justify-center text-lg text-muted-foreground" aria-hidden="true"><?= bx_h((string) $widget['icon']) ?></span>
                                                            <div class="min-w-0 flex-1">
                                                                    <button type="button" class="yovel-department-feature-button block w-full text-left text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring" data-department-section-target="<?= bx_h((string) $widget['section']) ?>" aria-pressed="<?= (string) $widget['section'] === 'overview' ? 'true' : 'false' ?>"><?= bx_h((string) $widget['title']) ?></button>
                                                                    <p class="mt-1 text-xs leading-4 text-muted-foreground"><?= bx_h((string) $widget['meta']) ?></p>
                                                            </div>
                                                            <div class="flex shrink-0 items-center gap-0.5 text-muted-foreground">
                                                                <button type="button" class="yovel-widget-move-up inline-flex size-7 items-center justify-center rounded-md hover:bg-muted hover:text-foreground" aria-label="Move <?= bx_h((string) $widget['title']) ?> up"><span class="material-symbols-rounded text-base" aria-hidden="true">keyboard_arrow_up</span></button>
                                                                <button type="button" class="yovel-widget-move-down inline-flex size-7 items-center justify-center rounded-md hover:bg-muted hover:text-foreground" aria-label="Move <?= bx_h((string) $widget['title']) ?> down"><span class="material-symbols-rounded text-base" aria-hidden="true">keyboard_arrow_down</span></button>
                                                                <span class="material-symbols-rounded inline-flex size-7 cursor-grab items-center justify-center text-base" aria-hidden="true">drag_indicator</span>
                                                            </div>
                                                        </section>
                                                    <?php endforeach; ?>
                                                </div>
                                            </aside>
                                        </div>

                                        <div id="yovel-department-form-builder-modal" class="yovel-employee-modal yovel-form-builder-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-department-form-builder-modal-title" aria-describedby="yovel-department-form-builder-modal-description" hidden>
                                            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-6xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                                <div class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4">
                                                    <div>
                                                        <h3 id="yovel-department-form-builder-modal-title" class="text-base font-semibold tracking-normal">Customize Department Form</h3>
                                                        <p id="yovel-department-form-builder-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Adjust Department fields, sections, order, visibility, and validation.</p>
                                                    </div>
                                                    <button type="button" id="yovel-department-form-builder-modal-close" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close department form builder">×</button>
                                                </div>
                                                <div class="yovel-hr-panel-body yovel-modal-scroll yovel-form-builder-scroll p-5 pt-0">
                                                    <?php yovel_admin_render_hr_form_builder('departments', $departmentFormFields, false, 'customize'); ?>
                                                </div>
                                                <div class="flex shrink-0 items-center justify-between gap-3 border-t px-5 py-4">
                                                    <p class="text-xs leading-5 text-muted-foreground"><?= count($departmentFormFields) ?> Department fields available.</p>
                                                    <button type="button" id="yovel-department-form-builder-modal-cancel" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Close</button>
                                                </div>
                                            </section>
                                        </div>

                                        <div id="yovel-department-form-create-modal" class="yovel-employee-modal yovel-form-builder-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-department-form-create-modal-title" aria-describedby="yovel-department-form-create-modal-description" hidden>
                                            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-6xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                                <div class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4">
                                                    <div>
                                                        <h3 id="yovel-department-form-create-modal-title" class="text-base font-semibold tracking-normal">Department Form Builder</h3>
                                                        <p id="yovel-department-form-create-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Create Department fields and place them into ERP sections.</p>
                                                    </div>
                                                    <button type="button" id="yovel-department-form-create-modal-close" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close department form builder">×</button>
                                                </div>
                                                <div class="yovel-hr-panel-body yovel-modal-scroll yovel-form-builder-scroll p-5 pt-0">
                                                    <?php yovel_admin_render_hr_form_builder('departments', $departmentFormFields, false, 'builder'); ?>
                                                </div>
                                                <div class="flex shrink-0 items-center justify-between gap-3 border-t px-5 py-4">
                                                    <p class="text-xs leading-5 text-muted-foreground">New Department fields save into this company's HR form schema.</p>
                                                    <button type="button" id="yovel-department-form-create-modal-cancel" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Close</button>
                                                </div>
                                            </section>
                                        </div>

                                        <div id="yovel-department-modal" class="yovel-employee-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-department-modal-title" aria-describedby="yovel-department-modal-description" <?= $editHrDepartment ? '' : 'hidden' ?>>
                                            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-5xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                                <div class="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4">
                                                    <div>
                                                        <h3 id="yovel-department-modal-title" class="flex flex-wrap items-center gap-2 text-base font-semibold tracking-normal">
                                                            <span><?= $editHrDepartment ? bx_h((string) $editHrDepartment['department_name']) : 'Add Department' ?></span>
                                                            <?php if ($editHrDepartment): ?><span class="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground"><?= bx_h((string) $editHrDepartment['department_status']) ?></span><?php endif; ?>
                                                        </h3>
                                                        <p id="yovel-department-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Department master data follows the HR Department scope.</p>
                                                    </div>
                                                    <button type="button" id="yovel-department-modal-close" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close department form">×</button>
                                                </div>
                                                <form id="yovel-department-form" method="post" data-confirm-submit class="yovel-hr-panel-body yovel-modal-scroll p-0">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="save_hr_department">
                                                    <input type="hidden" name="section" value="departments">
                                                    <input type="hidden" name="department_key" value="<?= bx_h((string) ($editHrDepartment['department_key'] ?? '')) ?>">
                                                    <nav class="sticky top-0 z-20 flex gap-2 overflow-x-auto border-b bg-card px-5 py-3" role="tablist" aria-label="Department sections">
                                                        <?php foreach (['overview' => 'Overview', 'details' => 'Details'] as $sectionKey => $sectionLabel): ?>
                                                            <button type="button" class="yovel-department-feature-button inline-flex h-8 shrink-0 items-center rounded-md border bg-background px-3 text-xs font-medium hover:bg-muted" role="tab" data-department-section-target="<?= bx_h($sectionKey) ?>" aria-selected="<?= $activeDepartmentModalSection === $sectionKey ? 'true' : 'false' ?>" aria-pressed="<?= $activeDepartmentModalSection === $sectionKey ? 'true' : 'false' ?>"><?= bx_h($sectionLabel) ?></button>
                                                        <?php endforeach; ?>
                                                    </nav>
                                                    <div class="grid gap-6 p-5">
                                                        <section data-department-modal-section="overview" class="grid gap-4" <?= $activeDepartmentModalSection === 'overview' ? '' : 'hidden' ?>>
                                                            <div>
                                                                <h4 class="text-sm font-semibold">Overview</h4>
                                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Core department identity, status, and standard template mapping.</p>
                                                            </div>
                                                            <div class="grid gap-3 lg:grid-cols-3">
                                                                <div class="grid gap-1.5">
                                                                    <label class="text-xs font-medium" for="department_code">Department code</label>
                                                                    <input class="h-9 rounded-md border bg-background px-3 text-sm" id="department_code" name="department_code" value="<?= bx_h((string) ($editHrDepartment['department_code'] ?? ($selectedHrTemplate['department_code'] ?? ''))) ?>" maxlength="40" pattern="[A-Za-z0-9_\-]{2,40}" required>
                                                                </div>
                                                                <div class="grid gap-1.5">
                                                                    <label class="text-xs font-medium" for="department_name">Department name</label>
                                                                    <input class="h-9 rounded-md border bg-background px-3 text-sm" id="department_name" name="department_name" value="<?= bx_h((string) ($editHrDepartment['department_name'] ?? ($selectedHrTemplate['department_name'] ?? ''))) ?>" maxlength="160" required>
                                                                </div>
                                                                <div class="grid gap-1.5">
                                                                    <label class="text-xs font-medium" for="department_status">Status</label>
                                                                    <select class="h-9 rounded-md border bg-background px-3 text-sm" id="department_status" name="department_status">
                                                                        <?php foreach (['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED', 'DELETED'] as $status): ?>
                                                                            <option value="<?= bx_h($status) ?>" <?= (string) ($editHrDepartment['department_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="grid gap-1.5">
                                                                    <label class="text-xs font-medium" for="department_source">Source</label>
                                                                    <select class="h-9 rounded-md border bg-background px-3 text-sm" id="department_source" name="department_source">
                                                                        <option value="ERP_DEFAULT" <?= $selectedHrSource === 'ERP_DEFAULT' ? 'selected' : '' ?>>Standard ERP</option>
                                                                        <option value="CUSTOM" <?= $selectedHrSource === 'CUSTOM' ? 'selected' : '' ?>>Custom</option>
                                                                    </select>
                                                                </div>
                                                                <div class="grid gap-1.5 lg:col-span-2">
                                                                    <label class="text-xs font-medium" for="default_department_key">Standard Department</label>
                                                                    <select class="h-9 rounded-md border bg-background px-3 text-sm" id="default_department_key" name="default_department_key">
                                                                        <?php if (!$hrDepartmentMasters): ?><option value="">No standard departments available</option><?php endif; ?>
                                                                        <?php foreach ($hrDepartmentMasters as $master): ?>
                                                                            <option value="<?= bx_h((string) $master['department_master_key']) ?>" data-code="<?= bx_h((string) $master['department_code']) ?>" data-name="<?= bx_h((string) $master['department_name']) ?>" data-type="<?= bx_h((string) $master['department_type']) ?>" data-description="<?= bx_h((string) $master['department_description']) ?>" <?= $selectedHrTemplateKey === (string) $master['department_master_key'] ? 'selected' : '' ?>><?= bx_h((string) $master['department_name'] . ' (' . (string) $master['department_code'] . ')') ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="grid gap-3 lg:grid-cols-2">
                                                                <?php yovel_admin_render_hr_custom_field_controls($departmentFormFields, $departmentCustomValues, 'overview'); ?>
                                                            </div>
                                                        </section>
                                                        <section data-department-modal-section="details" class="grid gap-4" <?= $activeDepartmentModalSection === 'details' ? '' : 'hidden' ?>>
                                                            <div>
                                                                <h4 class="text-sm font-semibold">Branch & Details</h4>
                                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Branch ownership, operational type, notes, and department custom fields.</p>
                                                            </div>
                                                            <div class="grid gap-3 lg:grid-cols-3">
                                                                <div class="grid gap-1.5 lg:col-span-2">
                                                                    <label class="text-xs font-medium" for="department_branch_key">Branch</label>
                                                                    <select class="h-9 rounded-md border bg-background px-3 text-sm" id="department_branch_key" name="branch_key" required>
                                                                        <?php if (!$hrDepartmentBranches): ?><option value="">Create a branch first</option><?php endif; ?>
                                                                        <?php foreach ($hrDepartmentBranches as $branch): ?>
                                                                            <option value="<?= bx_h((string) $branch['branch_key']) ?>" <?= $selectedHrBranchKey === (string) $branch['branch_key'] ? 'selected' : '' ?>><?= bx_h((string) $branch['branch_code'] . ' - ' . (string) $branch['branch_name']) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="grid gap-1.5">
                                                                    <label class="text-xs font-medium" for="department_type">Department type</label>
                                                                    <input class="h-9 rounded-md border bg-background px-3 text-sm" id="department_type" name="department_type" value="<?= bx_h((string) ($editHrDepartment['department_type'] ?? ($selectedHrTemplate['department_type'] ?? 'OPERATIONS'))) ?>" maxlength="60" pattern="[A-Za-z0-9_ \-]{2,60}" required>
                                                                </div>
                                                                <div class="grid gap-1.5 lg:col-span-3">
                                                                    <label class="text-xs font-medium" for="department_description">Description</label>
                                                                    <textarea class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" id="department_description" name="department_description"><?= bx_h((string) ($editHrDepartment['department_description'] ?? ($selectedHrTemplate['department_description'] ?? ''))) ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="grid gap-3 lg:grid-cols-2">
                                                                <?php yovel_admin_render_hr_custom_field_controls($departmentFormFields, $departmentCustomValues, 'details'); ?>
                                                            </div>
                                                        </section>
                                                    </div>
                                                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t px-5 py-4">
                                                        <p class="text-xs leading-5 text-muted-foreground">Department changes require confirmation before saving.</p>
                                                        <div class="flex gap-2">
                                                            <button type="button" id="yovel-department-modal-cancel" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><?= $editHrDepartment ? 'Close' : 'Cancel' ?></button>
                                                            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Department</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </section>
                                        </div>
