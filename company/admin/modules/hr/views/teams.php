<?php
/** HR view variables are prepared by bootstrap/controller.php. */
$hrTeams = $hrData['teams'] ?? [];
$hrTeamSummary = [
    'total' => count($hrTeams),
    'active' => count(array_filter($hrTeams, static fn (array $row): bool => (string) ($row['team_status'] ?? '') === 'ACTIVE')),
    'assigned' => array_sum(array_map(static fn (array $row): int => (int) ($row['assigned_employee_count'] ?? 0), $hrTeams)),
];
$editTeamMembers = $editHrTeam ? array_values(array_filter(
    $hrEmployees,
    static fn (array $employee): bool => (string) ($employee['team_key'] ?? '') === (string) $editHrTeam['team_key']
)) : [];
?>
                                        <div class="yovel-hr-two-panel yovel-job-position-layout yovel-team-layout grid min-h-0 gap-3 xl:grid-cols-12" data-team-widget-storage-key="<?= bx_h($companySidebarKey . ':team-widgets') ?>">
                                            <section class="yovel-hr-panel yovel-hr-surface flex flex-col rounded-lg border bg-card xl:col-span-8">
                                                <div class="yovel-hr-surface-header border-b px-4 py-3">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <h3 class="text-base font-semibold tracking-normal">Teams</h3>
                                                            <p class="mt-1 text-sm leading-6 text-muted-foreground">Operational employee groups used by <?= bx_h($companyName) ?>.</p>
                                                        </div>
                                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                                            <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= $hrTeamSummary['total'] ?> teams</span>
                                                            <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= $hrTeamSummary['active'] ?> active</span>
                                                            <span class="yovel-hr-metric rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= $hrTeamSummary['assigned'] ?> members</span>
                                                            <button type="button" id="yovel-team-modal-open" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Add Team</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="yovel-hr-panel-body grid content-start gap-3 p-3">
                                                    <div class="grid gap-3 rounded-md bg-background/40 p-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                                                        <div class="grid gap-2 sm:grid-cols-3">
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="ID" aria-label="Filter team ID" data-team-filter="code">
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" type="search" placeholder="Team Name" aria-label="Filter team name" data-team-filter="name">
                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" aria-label="Filter team status" data-team-filter="status">
                                                                <option value="">All statuses</option>
                                                                <option value="active">Active</option>
                                                                <option value="draft">Draft</option>
                                                                <option value="inactive">Inactive</option>
                                                            </select>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <span class="inline-flex h-9 items-center rounded-md border bg-secondary px-3 text-sm font-medium text-secondary-foreground">Results <span class="ml-2 rounded-full bg-background px-1.5 text-xs" data-team-result-count><?= count($hrTeams) ?></span></span>
                                                            <button type="button" class="inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" data-team-clear-filters>Clear</button>
                                                        </div>
                                                    </div>

                                                    <?php if (!$hrTeams): ?>
                                                        <div class="rounded-md bg-muted/40 p-4">
                                                            <p class="text-sm font-semibold">No teams yet</p>
                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">Create the first operational team before assigning employees from Employee Profiles.</p>
                                                            <button type="button" class="mt-3 inline-flex h-9 items-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" data-team-open-shortcut>Add Team</button>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="min-h-0 overflow-auto overscroll-contain">
                                                            <table class="yovel-team-table w-full min-w-0 table-fixed text-left text-sm">
                                                                <thead class="sticky top-0 z-10 bg-card text-xs text-muted-foreground">
                                                                    <tr class="border-y">
                                                                        <th scope="col" class="w-10 px-3 py-3"><span class="sr-only">Select</span></th>
                                                                        <th scope="col" class="px-3 py-3 font-medium">Team</th>
                                                                        <th scope="col" class="px-3 py-3 font-medium">Members</th>
                                                                        <th scope="col" class="px-3 py-3 font-medium">Status</th>
                                                                        <th scope="col" class="px-3 py-3 text-right font-medium">Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y" data-team-table-body>
                                                                    <?php foreach ($hrTeams as $team): ?>
                                                                        <?php $teamKey = (string) $team['team_key']; ?>
                                                                        <tr data-team-row data-team-drop-target data-team-key="<?= bx_h($teamKey) ?>" data-team-code="<?= bx_h(strtolower((string) $team['team_code'])) ?>" data-team-name="<?= bx_h(strtolower((string) $team['team_name'])) ?>" data-team-status="<?= bx_h(strtolower((string) $team['team_status'])) ?>" class="transition-colors">
                                                                            <td class="px-3 py-4"><input type="checkbox" class="size-4 rounded border" aria-label="Select <?= bx_h((string) $team['team_name']) ?>"></td>
                                                                            <td class="px-3 py-4">
                                                                                <p class="truncate font-medium"><?= bx_h((string) $team['team_name']) ?></p>
                                                                                <p class="mt-1 truncate text-xs text-muted-foreground"><?= bx_h((string) $team['team_code']) ?></p>
                                                                                <?php if ((string) ($team['team_description'] ?? '') !== ''): ?><p class="mt-1 truncate text-xs text-muted-foreground" title="<?= bx_h((string) $team['team_description']) ?>"><?= bx_h((string) $team['team_description']) ?></p><?php endif; ?>
                                                                            </td>
                                                                            <td class="px-3 py-4 text-xs text-muted-foreground"><?= (int) ($team['assigned_employee_count'] ?? 0) ?> employees</td>
                                                                            <td class="px-3 py-4"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $team['team_status']) ?></span></td>
                                                                            <td class="px-3 py-4">
                                                                                <div class="flex flex-nowrap justify-end gap-1.5">
                                                                                    <span class="yovel-employee-drop-indicator text-xs font-medium" data-team-drop-indicator aria-live="polite">Apply section here</span>
                                                                                    <a class="yovel-action-icon yovel-action-icon--view" href="./?view=hr&amp;section=teams&amp;edit=<?= bx_h($teamKey) ?>&amp;team_section=overview" title="View <?= bx_h((string) $team['team_code']) ?>" aria-label="View <?= bx_h((string) $team['team_name']) ?>">
                                                                                        <span class="material-symbols-rounded" aria-hidden="true">visibility</span><span class="sr-only">View</span>
                                                                                    </a>
                                                                                    <?php foreach ([['INACTIVE', 'Deactivate'], ['ACTIVE', 'Restore'], ['DELETED', 'Delete']] as $teamAction): ?>
                                                                                        <?php if ((string) $team['team_status'] === $teamAction[0]) { continue; } ?>
                                                                                        <?php
                                                                                            $teamActionIcon = ['ACTIVE' => 'check_circle', 'INACTIVE' => 'block', 'DELETED' => 'delete'][$teamAction[0]] ?? 'settings';
                                                                                            $teamActionTone = ['ACTIVE' => 'success', 'INACTIVE' => 'warning', 'DELETED' => 'danger'][$teamAction[0]] ?? 'archive';
                                                                                        ?>
                                                                                        <form method="post" data-confirm-submit>
                                                                                            <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                                                            <input type="hidden" name="action" value="set_hr_team_status">
                                                                                            <input type="hidden" name="section" value="teams">
                                                                                            <input type="hidden" name="team_key" value="<?= bx_h($teamKey) ?>">
                                                                                            <input type="hidden" name="team_status" value="<?= bx_h($teamAction[0]) ?>">
                                                                                            <button type="submit" class="yovel-action-icon yovel-action-icon--<?= bx_h($teamActionTone) ?>" title="<?= bx_h($teamAction[1] . ' ' . (string) $team['team_code']) ?>" aria-label="<?= bx_h($teamAction[1] . ' ' . (string) $team['team_name']) ?>">
                                                                                                <span class="material-symbols-rounded" aria-hidden="true"><?= bx_h($teamActionIcon) ?></span><span class="sr-only"><?= bx_h($teamAction[1]) ?></span>
                                                                                            </button>
                                                                                        </form>
                                                                                    <?php endforeach; ?>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                    <tr data-team-no-results hidden>
                                                                        <td colspan="5" class="px-4 py-8 text-center text-sm text-muted-foreground">No teams match these filters.</td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </section>

                                            <aside class="yovel-hr-panel yovel-hr-surface flex flex-col rounded-lg border bg-card xl:col-span-4">
                                                <div class="yovel-hr-surface-header yovel-hr-feature-header border-b px-4 py-3">
                                                    <h3 class="text-base font-semibold tracking-normal">Team Tools</h3>
                                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">Quick actions and the sections used by the Team form.</p>
                                                </div>
                                                <div class="yovel-hr-panel-body yovel-job-position-side-panel grid content-start p-3">
                                                    <details class="yovel-job-position-control-group" open>
                                                        <summary class="flex items-center justify-between gap-3 px-3 py-2.5">
                                                            <span><span class="block text-sm font-semibold">Quick Actions</span><span class="mt-0.5 block text-xs text-muted-foreground">Common Team tasks</span></span>
                                                            <span class="material-symbols-rounded yovel-job-position-control-chevron text-base text-muted-foreground" aria-hidden="true">expand_more</span>
                                                        </summary>
                                                        <div class="yovel-job-position-shortcuts border-t p-3">
                                                            <button type="button" data-team-open-shortcut>Add Team ↗</button>
                                                            <a href="./?view=hr&amp;section=dashboard&amp;builder=1&amp;builder_target=teams&amp;builder_mode=existing">Manage Forms ↗</a>
                                                            <a href="./?view=hr&amp;section=employee-profiles">Employee Profiles ↗</a>
                                                        </div>
                                                    </details>
                                                    <details class="yovel-job-position-control-group" open>
                                                        <summary class="flex items-center justify-between gap-3 px-3 py-2.5">
                                                            <span><span class="block text-sm font-semibold">Form Sections</span><span class="mt-0.5 block text-xs text-muted-foreground">Open or reorder sections</span></span>
                                                            <span class="material-symbols-rounded yovel-job-position-control-chevron text-base text-muted-foreground" aria-hidden="true">expand_more</span>
                                                        </summary>
                                                        <div id="yovel-team-widget-board" class="grid content-start gap-0 border-t p-3" aria-label="Team form sections">
                                                            <?php foreach ([
                                                                ['key' => 'overview', 'section' => 'overview', 'title' => 'Overview', 'meta' => 'Team code, name, and status'],
                                                                ['key' => 'details', 'section' => 'details', 'title' => 'Details', 'meta' => 'Description, members, and custom fields'],
                                                            ] as $widget): ?>
                                                                <section class="yovel-widget-item border-b bg-transparent p-2.5 last:border-b-0" draggable="true" data-team-widget-key="<?= bx_h((string) $widget['key']) ?>" aria-grabbed="false">
                                                                    <div class="flex items-start justify-between gap-3">
                                                                        <div class="min-w-0">
                                                                            <button type="button" class="yovel-team-feature-button rounded-sm px-1.5 py-0.5 text-left text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring" data-team-section-target="<?= bx_h((string) $widget['section']) ?>" aria-pressed="<?= (string) $widget['section'] === 'overview' ? 'true' : 'false' ?>"><?= bx_h((string) $widget['title']) ?></button>
                                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $widget['meta']) ?></p>
                                                                        </div>
                                                                        <div class="flex shrink-0 items-center gap-1 text-muted-foreground">
                                                                            <button type="button" class="yovel-widget-move-up inline-flex size-7 items-center justify-center rounded-md hover:bg-muted hover:text-foreground" aria-label="Move <?= bx_h((string) $widget['title']) ?> up"><span class="material-symbols-rounded text-base" aria-hidden="true">keyboard_arrow_up</span></button>
                                                                            <button type="button" class="yovel-widget-move-down inline-flex size-7 items-center justify-center rounded-md hover:bg-muted hover:text-foreground" aria-label="Move <?= bx_h((string) $widget['title']) ?> down"><span class="material-symbols-rounded text-base" aria-hidden="true">keyboard_arrow_down</span></button>
                                                                            <span class="material-symbols-rounded inline-flex size-7 cursor-grab items-center justify-center text-base" aria-hidden="true">drag_indicator</span>
                                                                        </div>
                                                                    </div>
                                                                </section>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </details>
                                                </div>
                                            </aside>
                                        </div>

                                        <div id="yovel-team-modal" class="yovel-employee-modal fixed inset-0 z-40 grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-team-modal-title" aria-describedby="yovel-team-modal-description" data-team-edit-mode="<?= $editHrTeam ? 'true' : 'false' ?>" data-team-initial-section="<?= bx_h($activeTeamModalSection) ?>" <?= $editHrTeam ? '' : 'hidden' ?>>
                                            <section class="flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-5xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg">
                                                <div class="flex shrink-0 items-start justify-between gap-4 border-b bg-card px-5 py-4">
                                                    <div>
                                                        <h3 id="yovel-team-modal-title" class="flex flex-wrap items-center gap-2 text-base font-semibold tracking-normal">
                                                            <span><?= $editHrTeam ? bx_h((string) $editHrTeam['team_name']) : 'Add Team' ?></span>
                                                            <?php if ($editHrTeam): ?><span class="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground"><?= bx_h((string) $editHrTeam['team_status']) ?></span><?php endif; ?>
                                                        </h3>
                                                        <p id="yovel-team-modal-description" class="mt-1 text-sm leading-6 text-muted-foreground">Team master data follows the HR Department scope.</p>
                                                    </div>
                                                    <button type="button" id="yovel-team-modal-close" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted" aria-label="Close Team form">×</button>
                                                </div>
                                                <form id="yovel-team-form" method="post" data-confirm-submit class="yovel-hr-panel-body yovel-modal-scroll p-0">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="save_hr_team">
                                                    <input type="hidden" name="section" value="teams">
                                                    <input type="hidden" name="team_key" value="<?= bx_h((string) ($editHrTeam['team_key'] ?? '')) ?>">
                                                    <nav class="sticky top-0 z-20 flex gap-2 overflow-x-auto border-b bg-card px-5 py-3" role="tablist" aria-label="Team sections">
                                                        <?php foreach (['overview' => 'Overview', 'details' => 'Details'] as $sectionKey => $sectionLabel): ?>
                                                            <button type="button" class="yovel-team-feature-button inline-flex h-8 shrink-0 items-center rounded-md border bg-background px-3 text-xs font-medium hover:bg-muted" role="tab" data-team-section-target="<?= bx_h($sectionKey) ?>" aria-selected="<?= $activeTeamModalSection === $sectionKey ? 'true' : 'false' ?>" aria-pressed="<?= $activeTeamModalSection === $sectionKey ? 'true' : 'false' ?>"><?= bx_h($sectionLabel) ?></button>
                                                        <?php endforeach; ?>
                                                    </nav>
                                                    <div class="grid gap-6 p-5">
                                                        <section data-team-modal-section="overview" class="grid gap-4" <?= $activeTeamModalSection === 'overview' ? '' : 'hidden' ?>>
                                                            <div><h4 class="text-sm font-semibold">Overview</h4><p class="mt-1 text-xs leading-5 text-muted-foreground">Core Team identity and availability.</p></div>
                                                            <div class="grid gap-3 lg:grid-cols-3">
                                                                <div class="grid gap-1.5">
                                                                    <label class="text-xs font-medium" for="team_code">Team code</label>
                                                                    <input class="h-9 rounded-md border bg-background px-3 text-sm" id="team_code" name="team_code" value="<?= bx_h((string) ($editHrTeam['team_code'] ?? '')) ?>" maxlength="80" pattern="[A-Za-z0-9_.-]{2,80}" required>
                                                                </div>
                                                                <div class="grid gap-1.5 lg:col-span-2">
                                                                    <label class="text-xs font-medium" for="team_name">Team name</label>
                                                                    <input class="h-9 rounded-md border bg-background px-3 text-sm" id="team_name" name="team_name" value="<?= bx_h((string) ($editHrTeam['team_name'] ?? '')) ?>" maxlength="160" required>
                                                                </div>
                                                                <div class="grid gap-1.5">
                                                                    <label class="text-xs font-medium" for="team_status">Status</label>
                                                                    <select class="h-9 rounded-md border bg-background px-3 text-sm" id="team_status" name="team_status">
                                                                        <?php foreach (['DRAFT', 'ACTIVE', 'INACTIVE'] as $status): ?>
                                                                            <option value="<?= bx_h($status) ?>" <?= (string) ($editHrTeam['team_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="grid gap-3 lg:grid-cols-2"><?php yovel_admin_render_hr_custom_field_controls($teamFormFields, $teamCustomValues, 'overview'); ?></div>
                                                        </section>
                                                        <section data-team-modal-section="details" class="grid gap-4" <?= $activeTeamModalSection === 'details' ? '' : 'hidden' ?>>
                                                            <div><h4 class="text-sm font-semibold">Team Details</h4><p class="mt-1 text-xs leading-5 text-muted-foreground">Description, current membership, and custom fields.</p></div>
                                                            <div class="grid gap-1.5">
                                                                <label class="text-xs font-medium" for="team_description">Description</label>
                                                                <textarea class="min-h-32 rounded-md border bg-background px-3 py-2 text-sm" id="team_description" name="team_description" maxlength="5000"><?= bx_h((string) ($editHrTeam['team_description'] ?? '')) ?></textarea>
                                                            </div>
                                                            <div class="border-t pt-4">
                                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                                    <div><h5 class="text-sm font-semibold">Assigned Members</h5><p class="mt-1 text-xs text-muted-foreground">Membership is managed from Employee Profiles.</p></div>
                                                                    <a class="inline-flex h-8 items-center rounded-md border bg-background px-3 text-xs font-medium hover:bg-muted" href="./?view=hr&amp;section=employee-profiles"><?= count($editTeamMembers) ?> employees ↗</a>
                                                                </div>
                                                                <?php if ($editTeamMembers): ?>
                                                                    <div class="mt-3 divide-y border-y">
                                                                        <?php foreach ($editTeamMembers as $member): ?>
                                                                            <div class="flex items-center justify-between gap-3 py-2.5 text-sm">
                                                                                <span class="truncate font-medium"><?= bx_h((string) $member['employee_name']) ?></span>
                                                                                <span class="shrink-0 text-xs text-muted-foreground"><?= bx_h((string) ($member['job_position_name'] ?: 'Unassigned position')) ?></span>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php elseif ($editHrTeam): ?>
                                                                    <p class="mt-3 text-sm text-muted-foreground">No employees are assigned to this Team.</p>
                                                                <?php else: ?>
                                                                    <p class="mt-3 text-sm text-muted-foreground">Save the Team before assigning employees.</p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="grid gap-3 lg:grid-cols-2"><?php yovel_admin_render_hr_custom_field_controls($teamFormFields, $teamCustomValues, 'details'); ?></div>
                                                        </section>
                                                    </div>
                                                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t bg-card px-5 py-4">
                                                        <p class="text-xs leading-5 text-muted-foreground">Team changes require confirmation before saving.</p>
                                                        <div class="flex gap-2">
                                                            <button type="button" id="yovel-team-modal-cancel" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"><?= $editHrTeam ? 'Close' : 'Cancel' ?></button>
                                                            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Team</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </section>
                                        </div>
