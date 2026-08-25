<?php
/** HR view variables are prepared by bootstrap/controller.php. */
?>
                                        <div class="yovel-hr-two-panel yovel-hr-approved-layout yovel-employee-workspace grid min-h-0 gap-4 xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]">
                                            <section class="yovel-hr-panel yovel-hr-surface min-w-0 overflow-hidden flex flex-col rounded-lg border bg-card">
                                                <div class="yovel-hr-surface-header border-b px-5 py-4">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <h3 class="text-base font-semibold tracking-normal">Employee</h3>
                                                            <p class="mt-1 text-sm leading-6 text-muted-foreground">Company-scoped employee master records for <?= bx_h($companyName) ?>.</p>
                                                        </div>
	                                                        <div class="flex flex-wrap items-center justify-end gap-2">
	                                                            <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($hrEmployees) ?> <?= count($hrEmployees) === 1 ? 'employee' : 'employees' ?></span>
	                                                            <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($activeHrEmployees) ?> active</span>
	                                                            <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($incompleteHrEmployees) ?> incomplete</span>
	                                                            <button type="button" id="yovel-employee-modal-open" data-record-modal-open="yovel-employee-modal" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><span class="material-symbols-rounded text-base" aria-hidden="true">person_add</span>Add Employee</button>
	                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="grid gap-3 border-b p-4">
                                                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                                                        <label class="grid gap-1 text-xs text-muted-foreground" for="yovel-employee-filter-id">Employee ID
                                                            <input id="yovel-employee-filter-id" data-employee-filter="id" class="h-9 rounded-md border bg-background px-3 text-sm text-foreground" type="search" placeholder="Search ID">
                                                        </label>
                                                        <label class="grid gap-1 text-xs text-muted-foreground" for="yovel-employee-filter-name">Employee name
                                                            <input id="yovel-employee-filter-name" data-employee-filter="name" class="h-9 rounded-md border bg-background px-3 text-sm text-foreground" type="search" placeholder="Search name">
                                                        </label>
                                                        <label class="grid gap-1 text-xs text-muted-foreground" for="yovel-employee-filter-department">Department
                                                            <input id="yovel-employee-filter-department" data-employee-filter="department" class="h-9 rounded-md border bg-background px-3 text-sm text-foreground" type="search" placeholder="Any department">
                                                        </label>
                                                        <label class="grid gap-1 text-xs text-muted-foreground" for="yovel-employee-filter-status">Status
                                                            <select id="yovel-employee-filter-status" data-employee-filter="status" class="h-9 rounded-md border bg-background px-3 text-sm text-foreground">
                                                                <option value="">Any status</option>
                                                                <?php foreach (['ACTIVE', 'DRAFT', 'INACTIVE', 'ON_LEAVE', 'SEPARATED'] as $filterStatus): ?>
                                                                    <option value="<?= bx_h($filterStatus) ?>"><?= bx_h(ucwords(strtolower(str_replace('_', ' ', $filterStatus)))) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label class="grid gap-1 text-xs text-muted-foreground" for="yovel-employee-filter-branch">Branch
                                                            <select id="yovel-employee-filter-branch" data-employee-filter="branch" class="h-9 rounded-md border bg-background px-3 text-sm text-foreground">
                                                                <option value="">Any branch</option>
                                                                <?php foreach (($hrData['branches'] ?? []) as $filterBranch): ?>
                                                                    <option value="<?= bx_h(strtolower((string) $filterBranch['branch_name'])) ?>"><?= bx_h((string) $filterBranch['branch_name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                    </div>
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <p class="text-xs text-muted-foreground"><span data-employee-result-count><?= count($hrEmployees) ?></span> of <?= count($hrEmployees) ?> employees shown</p>
                                                        <div class="flex items-center gap-2">
                                                            <button type="button" data-employee-clear-filters class="inline-flex h-8 items-center gap-2 rounded-md border bg-background px-3 text-xs font-medium hover:bg-muted">
                                                                <span class="material-symbols-rounded text-base" aria-hidden="true">filter_alt_off</span>
                                                                Clear filters
                                                            </button>
                                                            <label class="inline-flex h-8 items-center gap-2 rounded-md border bg-secondary px-2.5 text-xs font-medium text-secondary-foreground" for="yovel-employee-sort">
                                                                <span class="material-symbols-rounded text-base" aria-hidden="true">sort</span>
                                                                <span class="sr-only">Sort employees</span>
                                                                <select id="yovel-employee-sort" data-employee-sort class="bg-transparent pr-1 text-xs font-medium outline-none">
                                                                    <option value="name-asc">Name A-Z</option>
                                                                    <option value="name-desc">Name Z-A</option>
                                                                    <option value="id-asc">Employee ID</option>
                                                                    <option value="department-asc">Department</option>
                                                                    <option value="status-asc">Status</option>
                                                                </select>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if (!$hrEmployees): ?>
                                                    <div class="yovel-hr-panel-body p-5">
                                                        <div class="rounded-md bg-muted/40 p-4">
                                                            <p class="text-sm font-semibold">No employees yet</p>
                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">Create the first HR employee profile for <?= bx_h($companyName) ?>.</p>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="yovel-hr-panel-body yovel-hr-mobile-table-scroll overflow-auto">
                                                        <table class="w-full min-w-[920px] text-left text-sm">
                                                            <thead class="sticky top-0 z-10 border-b bg-card text-xs text-muted-foreground">
                                                                <tr>
                                                                    <th class="w-10 px-4 py-3 font-medium"><span class="sr-only">Select</span></th>
                                                                    <th class="px-4 py-3 font-medium">Full Name</th>
                                                                    <th class="px-4 py-3 font-medium">Status</th>
                                                                    <th class="px-4 py-3 font-medium">Designation</th>
                                                                    <th class="px-4 py-3 font-medium">ID</th>
                                                                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y" data-employee-table-body>
                                                                <?php foreach ($hrEmployees as $employeeIndex => $employee): ?>
                                                                    <?php $employeeKey = (string) $employee['employee_key']; ?>
                                                                    <?php
                                                                        $employeeEmail = trim((string) ($employee['company_email'] ?: $employee['personal_email']));
                                                                        $employeeMissing = [];
                                                                        if (trim((string) ($employee['department_key'] ?? '')) === '') { $employeeMissing[] = 'Department'; }
                                                                        if (trim((string) ($employee['job_position_key'] ?? '')) === '') { $employeeMissing[] = 'Designation'; }
                                                                        if (trim((string) ($employee['branch_key'] ?? '')) === '') { $employeeMissing[] = 'Branch'; }
                                                                        if ($employeeEmail === '') { $employeeMissing[] = 'Email'; }
                                                                        if (trim((string) ($employee['date_of_joining'] ?? '')) === '') { $employeeMissing[] = 'Joining date'; }
                                                                        $employeeCompleteness = (int) round((5 - count($employeeMissing)) / 5 * 100);
                                                                    ?>
                                                                    <tr data-employee-drop-target
                                                                        data-employee-key="<?= bx_h($employeeKey) ?>"
                                                                        data-employee-id="<?= bx_h(strtolower((string) $employee['employee_code'])) ?>"
                                                                        data-employee-name="<?= bx_h(strtolower((string) $employee['employee_name'])) ?>"
                                                                        data-employee-display-name="<?= bx_h((string) $employee['employee_name']) ?>"
                                                                        data-employee-status="<?= bx_h(strtolower((string) $employee['employee_status'])) ?>"
                                                                        data-employee-status-label="<?= bx_h(ucwords(strtolower(str_replace('_', ' ', (string) $employee['employee_status'])))) ?>"
                                                                        data-employee-department="<?= bx_h(strtolower((string) ($employee['department_name'] ?? ''))) ?>"
                                                                        data-employee-department-label="<?= bx_h((string) ($employee['department_name'] ?: 'Unassigned')) ?>"
                                                                        data-employee-branch="<?= bx_h(strtolower((string) ($employee['branch_name'] ?? ''))) ?>"
                                                                        data-employee-branch-label="<?= bx_h((string) ($employee['branch_name'] ?: 'Unassigned')) ?>"
                                                                        data-employee-designation="<?= bx_h((string) ($employee['job_position_name'] ?: 'Unassigned')) ?>"
                                                                        data-employee-email="<?= bx_h($employeeEmail !== '' ? $employeeEmail : 'No email') ?>"
                                                                        data-employee-completeness="<?= $employeeCompleteness ?>"
                                                                        data-employee-missing="<?= bx_h($employeeMissing ? implode(', ', $employeeMissing) : 'Core profile complete') ?>"
                                                                        aria-selected="<?= $employeeIndex === 0 ? 'true' : 'false' ?>"
                                                                        class="transition-colors">
                                                                        <td class="px-4 py-4"><input type="checkbox" class="size-4 rounded border" aria-label="Select <?= bx_h((string) $employee['employee_name']) ?>"></td>
                                                                        <td class="px-4 py-4">
                                                                            <button type="button" class="yovel-employee-name-button font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring" data-employee-select><?= bx_h((string) $employee['employee_name']) ?></button>
                                                                            <p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) ($employee['department_name'] ?: 'Unassigned')) ?> · <?= bx_h($employeeEmail !== '' ? $employeeEmail : 'No email') ?></p>
                                                                        </td>
                                                                        <td class="px-4 py-4"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h(ucwords(strtolower(str_replace('_', ' ', (string) $employee['employee_status'])))) ?></span></td>
                                                                        <td class="px-4 py-4 text-xs text-muted-foreground"><?= bx_h((string) ($employee['job_position_name'] ?: 'Unassigned')) ?></td>
                                                                        <td class="px-4 py-4 text-xs text-muted-foreground"><?= bx_h((string) $employee['employee_code']) ?></td>
                                                                        <td class="px-4 py-4">
                                                                            <div class="flex items-center justify-end gap-1.5">
                                                                                <span class="yovel-employee-drop-indicator text-xs font-medium" data-employee-drop-indicator aria-live="polite">Apply feature here</span>
                                                                                <a class="yovel-action-icon yovel-action-icon--view" href="./?view=hr&amp;section=employee-profiles&amp;edit=<?= bx_h($employeeKey) ?>&amp;employee_section=overview" title="View <?= bx_h((string) $employee['employee_code']) ?>" aria-label="View <?= bx_h((string) $employee['employee_name']) ?>">
                                                                                    <span class="material-symbols-rounded" aria-hidden="true">visibility</span>
                                                                                    <span class="sr-only">View</span>
                                                                                </a>
                                                                                <details class="yovel-employee-action-menu" data-employee-action-menu>
                                                                                    <summary class="yovel-action-icon yovel-action-icon--archive" title="Employee actions" aria-label="Actions for <?= bx_h((string) $employee['employee_name']) ?>">
                                                                                        <span class="material-symbols-rounded" aria-hidden="true">more_horiz</span>
                                                                                    </summary>
                                                                                    <div class="yovel-employee-action-menu-content grid gap-1 rounded-md p-1.5">
                                                                                        <?php foreach (['ACTIVE', 'INACTIVE', 'ON_LEAVE', 'SEPARATED', 'DELETED'] as $statusAction): ?>
                                                                                            <?php if ((string) $employee['employee_status'] === $statusAction) { continue; } ?>
                                                                                            <?php
                                                                                                $employeeActionMeta = [
                                                                                                    'ACTIVE' => ['label' => 'Activate', 'icon' => 'check_circle', 'tone' => 'success'],
                                                                                                    'INACTIVE' => ['label' => 'Deactivate', 'icon' => 'block', 'tone' => 'warning'],
                                                                                                    'ON_LEAVE' => ['label' => 'Mark on leave', 'icon' => 'event_busy', 'tone' => 'info'],
                                                                                                    'SEPARATED' => ['label' => 'Mark separated', 'icon' => 'logout', 'tone' => 'archive'],
                                                                                                    'DELETED' => ['label' => 'Delete employee', 'icon' => 'delete', 'tone' => 'danger'],
                                                                                                ][$statusAction] ?? ['label' => $statusAction, 'icon' => 'settings', 'tone' => 'archive'];
                                                                                            ?>
                                                                                            <form method="post" data-confirm-submit>
                                                                                                <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                                                                <input type="hidden" name="action" value="set_hr_employee_status">
                                                                                                <input type="hidden" name="section" value="employee-profiles">
                                                                                                <input type="hidden" name="employee_key" value="<?= bx_h($employeeKey) ?>">
                                                                                                <input type="hidden" name="employee_status" value="<?= bx_h($statusAction) ?>">
                                                                                                <button type="submit" class="flex h-9 items-center gap-2 rounded-md px-2.5 text-left text-sm hover:bg-muted <?= $statusAction === 'DELETED' ? 'text-red-400' : '' ?>">
                                                                                                    <span class="material-symbols-rounded text-base" aria-hidden="true"><?= bx_h((string) $employeeActionMeta['icon']) ?></span>
                                                                                                    <?= bx_h((string) $employeeActionMeta['label']) ?>
                                                                                                </button>
                                                                                            </form>
                                                                                        <?php endforeach; ?>
                                                                                    </div>
                                                                                </details>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                                <tr data-employee-no-results hidden>
                                                                    <td colspan="6" class="px-5 py-10 text-center">
                                                                        <p class="text-sm font-semibold">No employees match these filters</p>
                                                                        <p class="mt-1 text-xs text-muted-foreground">Clear one or more filters to return to the full employee list.</p>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                            </section>

                                            <?php $employeeContext = $hrEmployees[0] ?? null; ?>
                                            <aside class="yovel-hr-panel yovel-hr-surface min-w-0 overflow-hidden flex flex-col rounded-lg border bg-card" aria-label="Employee workspace context">
                                                <div class="yovel-hr-surface-header border-b px-5 py-4">
                                                    <div class="flex min-w-0 items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <h3 class="text-base font-semibold tracking-normal">Employee Workspace</h3>
                                                            <p class="mt-1 truncate text-sm leading-6 text-muted-foreground" data-employee-context-name><?= bx_h((string) ($employeeContext['employee_name'] ?? 'Select an employee')) ?></p>
                                                        </div>
                                                        <span class="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground" data-employee-context-status><?= bx_h($employeeContext ? ucwords(strtolower(str_replace('_', ' ', (string) $employeeContext['employee_status']))) : 'No selection') ?></span>
                                                    </div>
                                                    <div class="mt-3 flex w-fit gap-1 rounded-md border bg-background/70 p-1" role="tablist" aria-label="Employee workspace views">
                                                        <?php foreach ([['sections', 'view_agenda', 'Sections'], ['related', 'hub', 'Related'], ['insights', 'monitoring', 'Insights']] as [$contextKey, $contextIcon, $contextLabel]): ?>
                                                            <button type="button" class="yovel-employee-context-tab inline-flex h-8 items-center gap-1.5 rounded px-2.5 text-xs font-medium hover:bg-muted" data-employee-context-tab="<?= bx_h($contextKey) ?>" role="tab" aria-selected="<?= $contextKey === 'sections' ? 'true' : 'false' ?>" aria-controls="yovel-employee-context-<?= bx_h($contextKey) ?>">
                                                                <span class="material-symbols-rounded text-base" aria-hidden="true"><?= bx_h($contextIcon) ?></span>
                                                                <?= bx_h($contextLabel) ?>
                                                            </button>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="yovel-hr-panel-body min-h-0 overflow-y-auto">
                                                    <div id="yovel-employee-context-sections" class="yovel-employee-context-panel" data-employee-context-panel="sections" role="tabpanel">
                                                        <div class="border-b px-5 py-3">
                                                            <p class="text-xs leading-5 text-muted-foreground">Click a section to open it, or drag it onto an employee row.</p>
                                                        </div>
                                                        <div id="yovel-employee-widget-board" class="grid content-start px-5" aria-label="Employee record sections">
                                                            <?php foreach ([
                                                                ['key' => 'overview', 'icon' => 'badge', 'title' => 'Overview', 'meta' => 'Identity, status, and company assignment'],
                                                                ['key' => 'joining', 'icon' => 'handshake', 'title' => 'Joining', 'meta' => 'Employment dates and terms'],
                                                                ['key' => 'address-contacts', 'icon' => 'contact_phone', 'title' => 'Address & Contacts', 'meta' => 'Contact and emergency information'],
                                                                ['key' => 'attendance-leaves', 'icon' => 'event_available', 'title' => 'Attendance & Leaves', 'meta' => 'Attendance and leave defaults'],
                                                                ['key' => 'salary', 'icon' => 'payments', 'title' => 'Salary', 'meta' => 'Payroll and bank information'],
                                                                ['key' => 'personal', 'icon' => 'person', 'title' => 'Personal', 'meta' => 'Identity, family, and health'],
                                                                ['key' => 'profile', 'icon' => 'description', 'title' => 'Profile', 'meta' => 'Education, experience, and notes'],
                                                                ['key' => 'exit', 'icon' => 'logout', 'title' => 'Exit', 'meta' => 'Separation and exit interview'],
                                                                ['key' => 'connections', 'icon' => 'hub', 'title' => 'Connections', 'meta' => 'Related HR records and destinations'],
                                                            ] as $widget): ?>
                                                                <section class="yovel-employee-context-card yovel-widget-item flex items-center gap-3 py-3" draggable="true" data-widget-key="<?= bx_h((string) $widget['key']) ?>" aria-grabbed="false" tabindex="0">
                                                                    <span class="material-symbols-rounded inline-flex size-5 shrink-0 items-center justify-center text-lg text-muted-foreground" aria-hidden="true"><?= bx_h((string) $widget['icon']) ?></span>
                                                                    <button type="button" class="yovel-employee-feature-button min-w-0 flex-1 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring" data-employee-section-target="<?= bx_h((string) $widget['key']) ?>" aria-pressed="false">
                                                                        <span class="block text-sm font-semibold"><?= bx_h((string) $widget['title']) ?></span>
                                                                        <span class="mt-0.5 block text-xs leading-5 text-muted-foreground"><?= bx_h((string) $widget['meta']) ?></span>
                                                                    </button>
                                                                    <span class="material-symbols-rounded shrink-0 cursor-grab text-lg text-muted-foreground" aria-hidden="true">drag_indicator</span>
                                                                </section>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>

                                                    <div id="yovel-employee-context-related" class="yovel-employee-context-panel" data-employee-context-panel="related" role="tabpanel" hidden>
                                                        <div class="grid px-5">
                                                            <?php foreach ([
                                                                ['section' => 'attendance', 'icon' => 'schedule', 'title' => 'Attendance', 'meta' => 'Daily attendance and shifts', 'available' => true],
                                                                ['section' => 'leave-requests', 'icon' => 'event_busy', 'title' => 'Leave Requests', 'meta' => 'Employee leave applications', 'available' => true],
                                                                ['section' => 'payroll-access', 'icon' => 'account_balance_wallet', 'title' => 'Payroll Access', 'meta' => 'Salary and payroll permissions', 'available' => true],
                                                                ['section' => 'employee-documents', 'icon' => 'folder_shared', 'title' => 'Employee Documents', 'meta' => 'Employee-owned HR documents', 'available' => true],
                                                                ['section' => 'onboarding', 'icon' => 'assignment_ind', 'title' => 'Onboarding', 'meta' => 'Joining tasks and readiness', 'available' => true],
                                                            ] as $related): ?>
                                                                <a class="yovel-employee-context-card flex items-center gap-3 py-4 hover:text-foreground" href="./?view=hr&amp;section=<?= bx_h((string) $related['section']) ?>" data-employee-related-link>
                                                                    <span class="material-symbols-rounded inline-flex size-5 shrink-0 items-center justify-center text-lg text-muted-foreground" aria-hidden="true"><?= bx_h((string) $related['icon']) ?></span>
                                                                    <span class="min-w-0 flex-1"><span class="block text-sm font-semibold"><?= bx_h((string) $related['title']) ?></span><span class="mt-0.5 block text-xs leading-5 text-muted-foreground"><?= bx_h((string) $related['meta']) ?></span></span>
                                                                    <span class="material-symbols-rounded text-lg text-muted-foreground" aria-hidden="true">arrow_outward</span>
                                                                </a>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>

                                                    <div id="yovel-employee-context-insights" class="yovel-employee-context-panel p-5" data-employee-context-panel="insights" role="tabpanel" hidden>
                                                        <div class="grid gap-5">
                                                            <section>
                                                                <div class="flex items-center justify-between gap-3">
                                                                    <h4 class="text-sm font-semibold">Profile completeness</h4>
                                                                    <span class="text-sm font-semibold text-foreground" data-employee-context-completeness>0%</span>
                                                                </div>
                                                                <div class="yovel-employee-completeness-track mt-2 h-2 overflow-hidden rounded-full" role="progressbar" aria-label="Employee profile completeness" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-employee-context-progress>
                                                                    <div class="yovel-employee-completeness-bar h-full rounded-full" style="width: 0%" data-employee-context-progress-bar></div>
                                                                </div>
                                                            </section>
                                                            <section class="border-t pt-4">
                                                                <p class="text-xs font-medium uppercase text-muted-foreground">Needs attention</p>
                                                                <p class="mt-2 text-sm leading-6" data-employee-context-missing>Select an employee to review profile readiness.</p>
                                                            </section>
                                                            <section class="border-t pt-4">
                                                                <p class="text-xs font-medium uppercase text-muted-foreground">Assignment</p>
                                                                <p class="mt-2 text-sm font-medium" data-employee-context-assignment>No employee selected</p>
                                                                <p class="mt-1 text-xs leading-5 text-muted-foreground" data-employee-context-contact>Contact details will appear here.</p>
                                                            </section>
                                                        </div>
                                                    </div>
                                                </div>
	                                            </aside>

	                                            <?php
	                                                $employeeDocumentName = trim((string) ($editHrEmployee['employee_name'] ?? ''));
	                                                $employeeDocumentName = $employeeDocumentName !== '' ? $employeeDocumentName : 'Add Employee';
	                                                $employeeDocumentInitials = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($employeeDocumentName)) ?: 'EE', 0, 2));
	                                                $employeeDocumentStatus = (string) ($editHrEmployee['employee_status'] ?? 'ACTIVE');
	                                                $employeeDocumentTabs = [
	                                                    ['key' => 'overview', 'label' => 'Overview', 'icon' => 'badge'],
	                                                    ['key' => 'joining', 'label' => 'Joining', 'icon' => 'handshake'],
	                                                    ['key' => 'address-contacts', 'label' => 'Address & Contacts', 'icon' => 'contact_phone'],
	                                                    ['key' => 'attendance-leaves', 'label' => 'Attendance & Leaves', 'icon' => 'event_available'],
	                                                    ['key' => 'salary', 'label' => 'Salary', 'icon' => 'payments'],
	                                                    ['key' => 'personal', 'label' => 'Personal', 'icon' => 'person'],
	                                                    ['key' => 'profile', 'label' => 'Profile', 'icon' => 'description'],
	                                                    ['key' => 'exit', 'label' => 'Exit', 'icon' => 'logout'],
	                                                    ['key' => 'connections', 'label' => 'Connections', 'icon' => 'hub'],
	                                                ];
	                                            ?>
	                                            <div id="yovel-employee-modal" data-record-modal <?= $editHrEmployee ? 'data-record-modal-open-on-load' : '' ?> class="yovel-employee-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-employee-modal-title" aria-describedby="yovel-employee-modal-description" <?= $editHrEmployee ? '' : 'hidden' ?>>
	                                                <section class="yovel-employee-modal-surface flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] <?= $editHrEmployee ? 'max-w-6xl' : 'max-w-5xl' ?> flex-col overflow-hidden rounded-lg border shadow-lg">
	                                                    <div class="yovel-employee-modal-header flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4">
	                                                        <div class="flex min-w-0 items-start gap-3">
	                                                            <span class="yovel-erp-icon-badge yovel-erp-icon-badge--hr inline-flex size-10 shrink-0 items-center justify-center text-sm font-semibold" aria-hidden="true"><?= bx_h($employeeDocumentInitials) ?></span>
	                                                            <div class="min-w-0">
	                                                                <h3 id="yovel-employee-modal-title" class="flex flex-wrap items-center gap-2 text-base font-semibold tracking-normal">
	                                                                    <?= bx_h($editHrEmployee ? $employeeDocumentName : 'Add Employee') ?>
	                                                                    <?php if ($editHrEmployee): ?>
	                                                                        <span class="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"><?= bx_h(ucwords(strtolower(str_replace('_', ' ', $employeeDocumentStatus)))) ?></span>
	                                                                        <span class="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground" data-employee-form-state data-dirty="false">Saved record</span>
	                                                                    <?php else: ?>
	                                                                        <span class="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground" data-employee-form-state data-dirty="true">New record</span>
	                                                                    <?php endif; ?>
	                                                                </h3>
	                                                                <p id="yovel-employee-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground"><?= $editHrEmployee ? bx_h((string) ($editHrEmployee['employee_code'] ?? '')) . ' · ' . bx_h((string) ($editHrEmployee['job_position_name'] ?: 'Unassigned designation')) . ' · ' . bx_h((string) ($editHrEmployee['department_name'] ?: 'Unassigned department')) : 'Create a company-scoped employee master record.' ?></p>
	                                                            </div>
	                                                        </div>
	                                                        <div class="flex items-center gap-2">
	                                                            <?php if ($editHrEmployee): ?>
	                                                                <button type="submit" form="yovel-employee-form" class="inline-flex h-8 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save</button>
	                                                            <?php endif; ?>
	                                                            <button type="button" id="yovel-employee-modal-close" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close employee form">×</button>
	                                                        </div>
	                                                    </div>
	                                                    <form id="yovel-employee-form" method="post" data-confirm-submit class="yovel-hr-panel-body <?= $editHrEmployee ? 'p-0' : 'p-5' ?>">
	                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
	                                                        <input type="hidden" name="action" value="save_hr_employee">
	                                                        <input type="hidden" name="section" value="employee-profiles">
	                                                        <input type="hidden" name="employee_key" value="<?= bx_h((string) ($editHrEmployee['employee_key'] ?? '')) ?>">

	                                                        <?php if ($editHrEmployee): ?>
	                                                            <div class="yovel-employee-document-grid grid min-h-full">
	                                                                <aside class="yovel-employee-profile-rail content-start gap-4 border-b p-5 lg:border-b-0 lg:border-r">
	                                                                    <div class="yovel-employee-avatar flex shrink-0 items-center justify-center rounded-lg border bg-muted font-light text-muted-foreground"><?= bx_h($employeeDocumentInitials) ?></div>
	                                                                    <div class="grid gap-1 text-sm">
	                                                                        <button type="button" class="flex items-center justify-between rounded-md px-1 py-2 text-left hover:bg-muted"><span>Assigned To</span><span class="text-lg text-muted-foreground">+</span></button>
	                                                                        <button type="button" class="flex items-center justify-between rounded-md px-1 py-2 text-left hover:bg-muted"><span>Attachments</span><span class="text-lg text-muted-foreground">+</span></button>
	                                                                        <button type="button" class="flex items-center justify-between rounded-md px-1 py-2 text-left hover:bg-muted"><span>Tags</span><span class="text-lg text-muted-foreground">+</span></button>
	                                                                        <button type="button" class="flex items-center justify-between rounded-md px-1 py-2 text-left hover:bg-muted"><span>Share</span><span class="text-lg text-muted-foreground">+</span></button>
	                                                                    </div>
	                                                                    <div class="mt-4 grid gap-3 text-xs leading-5 text-muted-foreground">
	                                                                        <div class="flex items-center gap-3 text-foreground"><span>♡ 0</span><span>○ 0</span><span class="ml-auto text-xs font-medium">FOLLOW</span></div>
	                                                                        <p>You last edited this employee profile from HR Department.</p>
	                                                                        <p>You created this employee profile in <?= bx_h($companyName) ?>.</p>
	                                                                    </div>
	                                                                </aside>
	                                                                <div class="min-w-0">
	                                                                    <nav class="yovel-employee-modal-tabs sticky top-0 z-20 flex gap-1 overflow-x-auto border-b px-5 py-3" role="tablist" aria-label="Employee document sections">
	                                                                        <?php foreach ($employeeDocumentTabs as $tab): ?>
	                                                                            <button type="button" class="yovel-employee-feature-button inline-flex h-8 shrink-0 items-center gap-1 rounded-md px-2 text-xs font-medium hover:bg-muted" data-employee-section-target="<?= bx_h((string) $tab['key']) ?>" role="tab" aria-selected="<?= (string) $tab['key'] === 'overview' ? 'true' : 'false' ?>" aria-pressed="<?= (string) $tab['key'] === 'overview' ? 'true' : 'false' ?>" aria-controls="yovel-employee-section-<?= bx_h((string) $tab['key']) ?>">
	                                                                                <span class="material-symbols-rounded text-sm" aria-hidden="true"><?= bx_h((string) $tab['icon']) ?></span>
	                                                                                <?= bx_h((string) $tab['label']) ?>
	                                                                            </button>
	                                                                        <?php endforeach; ?>
	                                                                    </nav>
	                                                                    <div class="grid gap-6 p-5">
	                                                        <?php else: ?>
	                                                            <div class="grid gap-6">
	                                                        <?php endif; ?>
                                                            <section id="yovel-employee-section-overview" class="grid gap-4" data-employee-modal-section="overview" role="tabpanel">
                                                                <div>
                                                                    <h4 class="text-sm font-semibold">Overview</h4>
                                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Basic employee identity and company assignment.</p>
                                                                </div>
                                                                <div class="grid gap-3 lg:grid-cols-3">
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="employee_code">Series</label>
                                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="employee_code" name="employee_code" value="<?= bx_h((string) ($editHrEmployee['employee_code'] ?? '')) ?>" pattern="[A-Za-z0-9_.-]{2,80}" maxlength="80" placeholder="HR-EMP-" required>
                                                                    </div>
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="employee_status">Status</label>
                                                                        <select class="h-9 rounded-md border bg-background px-3 text-sm" id="employee_status" name="employee_status">
                                                                            <?php foreach (['DRAFT', 'ACTIVE', 'INACTIVE', 'ON_LEAVE', 'SEPARATED'] as $status): ?>
                                                                                <option value="<?= bx_h($status) ?>" <?= (string) ($editHrEmployee['employee_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="employee_name">Full name</label>
                                                                        <input class="h-9 rounded-md border bg-muted px-3 text-sm" id="employee_name" name="employee_name" value="<?= bx_h((string) ($editHrEmployee['employee_name'] ?? '')) ?>" maxlength="200" readonly>
                                                                    </div>
                                                                </div>
                                                                <div class="grid gap-3 lg:grid-cols-3">
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="first_name">First name</label>
                                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="first_name" name="first_name" value="<?= bx_h((string) ($editHrEmployee['first_name'] ?? '')) ?>" maxlength="120" required>
                                                                    </div>
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="middle_name">Middle name</label>
                                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="middle_name" name="middle_name" value="<?= bx_h((string) ($editHrEmployee['middle_name'] ?? '')) ?>" maxlength="120">
                                                                    </div>
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="last_name">Last name</label>
                                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="last_name" name="last_name" value="<?= bx_h((string) ($editHrEmployee['last_name'] ?? '')) ?>" maxlength="120">
                                                                    </div>
                                                                </div>
                                                                <div class="border-t pt-4">
                                                                    <h5 class="text-sm font-semibold">Company Details</h5>
                                                                    <div class="mt-3 grid gap-3 lg:grid-cols-3">
                                                                        <div class="grid gap-1.5">
                                                                            <label class="text-xs font-medium" for="branch_key">Branch</label>
                                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="branch_key" name="branch_key">
                                                                                <option value="">Unassigned</option>
                                                                                <?php foreach (($hrData['branches'] ?? []) as $branch): ?>
                                                                                    <option value="<?= bx_h((string) $branch['branch_key']) ?>" <?= (string) ($editHrEmployee['branch_key'] ?? '') === (string) $branch['branch_key'] ? 'selected' : '' ?>><?= bx_h((string) $branch['branch_name']) ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="grid gap-1.5">
                                                                            <label class="text-xs font-medium" for="department_key">Department</label>
                                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="department_key" name="department_key">
                                                                                <option value="">Unassigned</option>
                                                                                <?php foreach (($hrData['departments'] ?? []) as $department): ?>
                                                                                    <option value="<?= bx_h((string) $department['department_key']) ?>" <?= (string) ($editHrEmployee['department_key'] ?? '') === (string) $department['department_key'] ? 'selected' : '' ?>><?= bx_h((string) $department['department_name']) ?><?= (string) ($department['branch_name'] ?? '') !== '' ? ' · ' . bx_h((string) $department['branch_name']) : '' ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="grid gap-1.5">
                                                                            <label class="text-xs font-medium" for="job_position_key">Designation</label>
                                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="job_position_key" name="job_position_key">
                                                                                <option value="">Unassigned</option>
                                                                                <?php foreach (($hrData['jobPositions'] ?? []) as $position): ?>
                                                                                    <option value="<?= bx_h((string) $position['job_position_key']) ?>" <?= (string) ($editHrEmployee['job_position_key'] ?? '') === (string) $position['job_position_key'] ? 'selected' : '' ?>><?= bx_h((string) $position['job_position_name']) ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="grid gap-1.5">
                                                                            <label class="text-xs font-medium" for="team_key">Team</label>
                                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="team_key" name="team_key">
                                                                                <option value="">Unassigned</option>
                                                                                <?php foreach (($hrData['teams'] ?? []) as $team): ?>
                                                                                    <option value="<?= bx_h((string) $team['team_key']) ?>" <?= (string) ($editHrEmployee['team_key'] ?? '') === (string) $team['team_key'] ? 'selected' : '' ?>><?= bx_h((string) $team['team_name']) ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="grid gap-1.5 lg:col-span-2">
                                                                            <label class="text-xs font-medium" for="reports_to_employee_key">Reports to</label>
                                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="reports_to_employee_key" name="reports_to_employee_key">
                                                                                <option value="">Unassigned</option>
                                                                                <?php foreach ($hrEmployees as $manager): ?>
                                                                                    <?php if ((string) ($manager['employee_key'] ?? '') === (string) ($editHrEmployee['employee_key'] ?? '')) { continue; } ?>
                                                                                    <option value="<?= bx_h((string) $manager['employee_key']) ?>" <?= (string) ($editHrEmployee['reports_to_employee_key'] ?? '') === (string) $manager['employee_key'] ? 'selected' : '' ?>><?= bx_h((string) $manager['employee_name']) ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="grid gap-3 lg:grid-cols-3">
                                                                    <?php yovel_admin_render_hr_custom_field_controls($employeeFormFields, $employeeCustomValues, 'overview'); ?>
                                                                </div>
                                                            </section>

                                                            <section id="yovel-employee-section-joining" class="grid gap-4" data-employee-modal-section="joining" role="tabpanel" hidden>
                                                                <div>
                                                                    <h4 class="text-sm font-semibold">Joining</h4>
                                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Joining dates and employment terms.</p>
                                                                </div>
                                                                <div class="grid gap-3 lg:grid-cols-3">
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="date_of_joining">Date of joining</label>
                                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="date_of_joining" name="date_of_joining" type="date" value="<?= bx_h((string) ($editHrEmployee['date_of_joining'] ?? '')) ?>">
                                                                    </div>
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="employment_type">Employment type</label>
                                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="employment_type" name="employment_type" value="<?= bx_h((string) ($editHrEmployee['employment_type'] ?? '')) ?>" maxlength="80">
                                                                    </div>
                                                                    <?php yovel_admin_render_hr_custom_field_controls($employeeFormFields, $employeeCustomValues, 'joining'); ?>
                                                                </div>
                                                            </section>

                                                            <section id="yovel-employee-section-address-contacts" class="grid gap-4" data-employee-modal-section="address-contacts" role="tabpanel" hidden>
                                                                <div>
                                                                    <h4 class="text-sm font-semibold">Address & Contacts</h4>
                                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Employee contact channels and address details.</p>
                                                                </div>
                                                                <div class="grid gap-3 lg:grid-cols-3">
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="mobile_number">Mobile</label>
                                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="mobile_number" name="mobile_number" value="<?= bx_h((string) ($editHrEmployee['mobile_number'] ?? '')) ?>" maxlength="60">
                                                                    </div>
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="company_email">Company email</label>
                                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="company_email" name="company_email" type="email" value="<?= bx_h((string) ($editHrEmployee['company_email'] ?? '')) ?>" maxlength="160">
                                                                    </div>
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="personal_email">Personal email</label>
                                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="personal_email" name="personal_email" type="email" value="<?= bx_h((string) ($editHrEmployee['personal_email'] ?? '')) ?>" maxlength="160">
                                                                    </div>
                                                                    <?php yovel_admin_render_hr_custom_field_controls($employeeFormFields, $employeeCustomValues, 'address-contacts'); ?>
                                                                </div>
                                                            </section>

                                                            <section id="yovel-employee-section-attendance-leaves" class="grid gap-4" data-employee-modal-section="attendance-leaves" role="tabpanel" hidden>
                                                                <div>
                                                                    <h4 class="text-sm font-semibold">Attendance & Leaves</h4>
                                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Attendance and leave-related employee defaults.</p>
                                                                </div>
                                                                <div class="grid gap-3 lg:grid-cols-2">
                                                                    <?php yovel_admin_render_hr_custom_field_controls($employeeFormFields, $employeeCustomValues, 'attendance-leaves'); ?>
                                                                </div>
                                                            </section>

                                                            <section id="yovel-employee-section-salary" class="grid gap-4" data-employee-modal-section="salary" role="tabpanel" hidden>
                                                                <div>
                                                                    <h4 class="text-sm font-semibold">Salary</h4>
                                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Salary mode and bank details for payroll readiness.</p>
                                                                </div>
                                                                <div class="grid gap-3 lg:grid-cols-3">
                                                                    <?php yovel_admin_render_hr_custom_field_controls($employeeFormFields, $employeeCustomValues, 'salary'); ?>
                                                                </div>
                                                            </section>

                                                            <section id="yovel-employee-section-personal" class="grid gap-4" data-employee-modal-section="personal" role="tabpanel" hidden>
                                                                <div>
                                                                    <h4 class="text-sm font-semibold">Personal</h4>
                                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Personal identity, family, and health information.</p>
                                                                </div>
                                                                <div class="grid gap-3 lg:grid-cols-3">
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="date_of_birth">Date of birth</label>
                                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="date_of_birth" name="date_of_birth" type="date" value="<?= bx_h((string) ($editHrEmployee['date_of_birth'] ?? '')) ?>">
                                                                    </div>
                                                                    <?php yovel_admin_render_hr_custom_field_controls($employeeFormFields, $employeeCustomValues, 'personal'); ?>
                                                                </div>
                                                            </section>

                                                            <section id="yovel-employee-section-profile" class="grid gap-4" data-employee-modal-section="profile" role="tabpanel" hidden>
                                                                <div>
                                                                    <h4 class="text-sm font-semibold">Profile</h4>
                                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Narrative profile, education, work history, and HR notes.</p>
                                                                </div>
                                                                <div class="grid gap-3">
                                                                    <div class="grid gap-1.5">
                                                                        <label class="text-xs font-medium" for="employee_notes">Notes</label>
                                                                        <textarea class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" id="employee_notes" name="employee_notes"><?= bx_h((string) ($editHrEmployee['employee_notes'] ?? '')) ?></textarea>
                                                                    </div>
                                                                    <div class="grid gap-3 lg:grid-cols-2">
                                                                        <?php yovel_admin_render_hr_custom_field_controls($employeeFormFields, $employeeCustomValues, 'profile'); ?>
                                                                    </div>
                                                                </div>
                                                            </section>

                                                            <section id="yovel-employee-section-exit" class="grid gap-4" data-employee-modal-section="exit" role="tabpanel" hidden>
                                                                <div>
                                                                    <h4 class="text-sm font-semibold">Exit</h4>
                                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Resignation, relieving, and exit interview fields.</p>
                                                                </div>
	                                                            <?php if ($employeeDocumentStatus !== 'SEPARATED'): ?>
	                                                                <div class="flex items-start gap-3 rounded-md border border-amber-400/30 bg-amber-400/10 p-3 text-sm" data-employee-exit-guidance>
	                                                                    <span class="material-symbols-rounded text-amber-300" aria-hidden="true">info</span>
	                                                                    <p><span class="font-semibold">Employee is not separated.</span> Use these fields only when preparing or recording an employee exit.</p>
	                                                                </div>
	                                                            <?php endif; ?>
                                                                <div class="grid gap-3 lg:grid-cols-3">
                                                                    <?php yovel_admin_render_hr_custom_field_controls($employeeFormFields, $employeeCustomValues, 'exit'); ?>
                                                                </div>
                                                            </section>

	                                                        <section id="yovel-employee-section-connections" class="grid gap-4" data-employee-modal-section="connections" role="tabpanel" hidden>
	                                                            <div>
	                                                                <h4 class="text-sm font-semibold">Connections</h4>
	                                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Open the HR workspaces connected to this employee record.</p>
	                                                            </div>
	                                                            <div class="grid gap-x-6 gap-y-0 sm:grid-cols-2">
	                                                                <?php foreach ([
	                                                                    ['section' => 'attendance', 'icon' => 'schedule', 'title' => 'Attendance', 'meta' => 'Daily records and shifts'],
	                                                                    ['section' => 'leave-requests', 'icon' => 'event_busy', 'title' => 'Leave Requests', 'meta' => 'Applications and balances'],
	                                                                    ['section' => 'payroll-access', 'icon' => 'account_balance_wallet', 'title' => 'Payroll Access', 'meta' => 'Payroll visibility and assignment'],
	                                                                    ['section' => 'employee-documents', 'icon' => 'folder_shared', 'title' => 'Documents', 'meta' => 'Employee-owned HR files'],
	                                                                    ['section' => 'onboarding', 'icon' => 'assignment_ind', 'title' => 'Onboarding', 'meta' => 'Joining tasks and readiness'],
	                                                                    ['section' => 'hr-reports', 'icon' => 'monitoring', 'title' => 'HR Reports', 'meta' => 'Employee and lifecycle reporting'],
	                                                                ] as $connection): ?>
	                                                                    <a class="flex items-center gap-3 border-b py-4 hover:text-foreground" href="./?view=hr&amp;section=<?= bx_h((string) $connection['section']) ?>">
	                                                                        <span class="material-symbols-rounded yovel-erp-icon-badge yovel-erp-icon-badge--hr inline-flex size-8 shrink-0 items-center justify-center text-lg" aria-hidden="true"><?= bx_h((string) $connection['icon']) ?></span>
	                                                                        <span class="min-w-0 flex-1"><span class="block text-sm font-semibold"><?= bx_h((string) $connection['title']) ?></span><span class="mt-0.5 block text-xs text-muted-foreground"><?= bx_h((string) $connection['meta']) ?></span></span>
	                                                                        <span class="material-symbols-rounded text-base text-muted-foreground" aria-hidden="true">arrow_outward</span>
	                                                                    </a>
	                                                                <?php endforeach; ?>
	                                                            </div>
	                                                        </section>

	                                                            <?php if ($editHrEmployee): ?>
	                                                                <section class="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
	                                                                    <div><h4 class="text-sm font-semibold">Record activity</h4><p class="mt-1 text-xs text-muted-foreground">Changes are confirmed and written to the company audit trail.</p></div>
	                                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary px-2.5 py-1 text-xs text-secondary-foreground"><span class="material-symbols-rounded text-base" aria-hidden="true">verified_user</span> Audited</span>
	                                                                </section>
	                                                            <?php else: ?>
	                                                                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Employee</button>
	                                                            <?php endif; ?>
	                                                        <?php if ($editHrEmployee): ?>
	                                                                    </div>
	                                                                </div>
	                                                            </div>
	                                                        <?php else: ?>
	                                                            </div>
	                                                        <?php endif; ?>
	                                                </form>
	                                                <div class="yovel-employee-modal-footer flex shrink-0 items-center justify-between gap-3 border-t px-5 py-4">
	                                                    <p class="text-xs leading-5 text-muted-foreground">Employee changes still require confirmation before saving.</p>
	                                                    <button type="button" id="yovel-employee-modal-cancel" data-record-modal-close class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><?= $editHrEmployee ? 'Close' : 'Cancel' ?></button>
	                                                </div>
                                            </section>
                                        </div>
                                        </div>
