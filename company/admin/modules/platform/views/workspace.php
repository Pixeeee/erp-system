<?php
/** Workspace variables are prepared by bootstrap/controller.php. */
?>
                                <div class="grid gap-4">
                                    <div>
                                        <span class="inline-flex w-fit items-center rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= bx_h($viewEyebrow) ?></span>
                                        <h2 class="mt-3 text-2xl font-semibold tracking-normal"><?= bx_h($viewHeading) ?></h2>
                                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground"><?= bx_h($viewDescription) ?></p>
                                    </div>

                                    <?php if (!$isModulesView): ?>
                                        <nav class="flex flex-wrap gap-2" aria-label="Platform sections">
                                            <?php foreach ($platformNav as $sectionKey => $sectionMeta): ?>
                                                <?php if ($sectionKey === 'modules') { continue; } ?>
                                                <a class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium <?= $activePlatformSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=platform&amp;section=<?= bx_h((string) $sectionKey) ?>">
                                                    <span aria-hidden="true"><?= bx_h((string) $sectionMeta['icon']) ?></span><?= bx_h((string) $sectionMeta['label']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </nav>
                                    <?php endif; ?>

                                    <?php if ($isModulesView): ?>
                                        <div class="grid gap-4 xl:grid-cols-[minmax(0,8fr)_minmax(320px,4fr)]">
                                            <section class="rounded-lg border bg-card">
                                                <div class="border-b px-5 py-4">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <h3 class="text-base font-semibold tracking-normal">Modules</h3>
                                                            <p class="mt-1 text-sm leading-6 text-muted-foreground">ERP workspaces available to <?= bx_h($companyName) ?>.</p>
                                                        </div>
                                                        <span class="inline-flex rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($platformData['modules'] ?? []) ?> modules</span>
                                                    </div>
                                                </div>
                                                <div class="overflow-x-auto p-5">
                                                    <table class="w-full min-w-[720px] text-left text-sm">
                                                        <thead class="border-b text-xs text-muted-foreground">
                                                            <tr>
                                                                <th class="py-2 pr-3 font-medium">Module</th>
                                                                <th class="py-2 pr-3 font-medium">Code</th>
                                                                <th class="py-2 pr-3 font-medium">Status</th>
                                                                <th class="py-2 pr-3 font-medium">What It Does</th>
                                                                <th class="py-2 text-left font-medium">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y">
                                                            <?php foreach (($platformData['modules'] ?? []) as $module): ?>
                                                                <tr>
                                                                    <td class="py-3 pr-3 font-medium"><span class="mr-2" aria-hidden="true"><?= bx_h((string) $module['module_icon']) ?></span><?= bx_h((string) $module['module_name']) ?></td>
                                                                    <td class="py-3 pr-3 text-xs text-muted-foreground"><?= bx_h((string) $module['module_code']) ?></td>
                                                                    <td class="py-3 pr-3"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $module['module_status']) ?></span></td>
                                                                    <td class="max-w-md py-3 pr-3 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $module['module_description']) ?></td>
                                                                    <td class="py-3"><a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted" href="./?view=platform&amp;section=modules&amp;edit=<?= bx_h((string) $module['module_key']) ?>">Edit</a></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </section>

                                            <aside class="rounded-lg border bg-card">
                                                <div class="border-b px-5 py-4">
                                                    <h3 class="text-base font-semibold tracking-normal"><?= $editModule ? 'Edit Module' : 'Add Module' ?></h3>
                                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">Manage the module record, status, icon, order, and description.</p>
                                                </div>
                                                <div class="grid gap-5 p-5">
                                                    <form method="post" data-confirm-submit class="grid gap-3">
                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="save_company_module">
                                                        <input type="hidden" name="section" value="modules">
                                                        <input type="hidden" name="module_key" value="<?= bx_h((string) ($editModule['module_key'] ?? '')) ?>">
                                                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                                            <div class="grid gap-1.5">
                                                                <label class="text-xs font-medium" for="module_code">Code</label>
                                                                <input class="h-9 rounded-md border bg-background px-3 text-sm" id="module_code" name="module_code" value="<?= bx_h((string) ($editModule['module_code'] ?? '')) ?>" required>
                                                            </div>
                                                            <div class="grid gap-1.5">
                                                                <label class="text-xs font-medium" for="module_icon">Icon</label>
                                                                <input class="h-9 rounded-md border bg-background px-3 text-sm" id="module_icon" name="module_icon" value="<?= bx_h((string) ($editModule['module_icon'] ?? '▥')) ?>" maxlength="20">
                                                            </div>
                                                        </div>
                                                        <div class="grid gap-1.5">
                                                            <label class="text-xs font-medium" for="module_name">Name</label>
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" id="module_name" name="module_name" value="<?= bx_h((string) ($editModule['module_name'] ?? '')) ?>" required>
                                                        </div>
                                                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                                            <div class="grid gap-1.5">
                                                                <label class="text-xs font-medium" for="module_status">Status</label>
                                                                <select class="h-9 rounded-md border bg-background px-3 text-sm" id="module_status" name="module_status">
                                                                    <?php foreach (['DRAFT', 'ACTIVE', 'INACTIVE', 'DELETED'] as $status): ?>
                                                                        <option value="<?= bx_h($status) ?>" <?= (string) ($editModule['module_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="grid gap-1.5">
                                                                <label class="text-xs font-medium" for="module_sort_order">Sort</label>
                                                                <input class="h-9 rounded-md border bg-background px-3 text-sm" id="module_sort_order" name="module_sort_order" type="number" min="0" value="<?= bx_h((string) ($editModule['module_sort_order'] ?? '0')) ?>">
                                                            </div>
                                                        </div>
                                                        <div class="grid gap-1.5">
                                                            <label class="text-xs font-medium" for="module_description">Description</label>
                                                            <textarea class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" id="module_description" name="module_description"><?= bx_h((string) ($editModule['module_description'] ?? '')) ?></textarea>
                                                        </div>
                                                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Module</button>
                                                    </form>

                                                    <div class="grid gap-3 rounded-md bg-muted/40 p-3">
                                                        <div>
                                                            <h4 class="text-sm font-semibold">How Modules Work</h4>
                                                            <p class="mt-1 text-xs leading-5 text-muted-foreground">Modules are top-level ERP workspaces. They define which business areas exist, which permissions belong to them, and what can be granted to users, roles, or groups.</p>
                                                        </div>
                                                        <div class="grid gap-2 text-xs font-medium">
                                                            <div>Turn ERP areas on or off per company.</div>
                                                            <div>Attach permissions to the correct workspace.</div>
                                                            <div>Grant access through roles, groups, or direct user overrides.</div>
                                                            <div>Keep every access change in the audit trail.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </aside>
                                        </div>
                                    <?php else: ?>
                                    <section class="rounded-lg border bg-card">
                                        <div class="border-b px-5 py-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <h3 class="text-base font-semibold tracking-normal"><?= bx_h((string) $platformNav[$activePlatformSection]['label']) ?></h3>
                                                    <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) $platformNav[$activePlatformSection]['description']) ?></p>
                                                </div>
                                                <span class="inline-flex rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= bx_h($companyName) ?></span>
                                            </div>
                                        </div>

                                        <?php if ($activePlatformSection === 'modules'): ?>
                                            <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.85fr)]">
                                                <div class="overflow-x-auto">
                                                    <table class="w-full min-w-[720px] text-left text-sm">
                                                        <thead class="border-b text-xs text-muted-foreground">
                                                            <tr>
                                                                <th class="py-2 pr-3 font-medium">Module</th>
                                                                <th class="py-2 pr-3 font-medium">Code</th>
                                                                <th class="py-2 pr-3 font-medium">Status</th>
                                                                <th class="py-2 pr-3 font-medium">What It Does</th>
                                                                <th class="py-2 text-right font-medium">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y">
                                                            <?php foreach (($platformData['modules'] ?? []) as $module): ?>
                                                                <tr>
                                                                    <td class="py-3 pr-3 font-medium"><span class="mr-2" aria-hidden="true"><?= bx_h((string) $module['module_icon']) ?></span><?= bx_h((string) $module['module_name']) ?></td>
                                                                    <td class="py-3 pr-3 text-xs text-muted-foreground"><?= bx_h((string) $module['module_code']) ?></td>
                                                                    <td class="py-3 pr-3"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $module['module_status']) ?></span></td>
                                                                    <td class="max-w-md py-3 pr-3 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $module['module_description']) ?></td>
                                                                    <td class="py-3 text-right"><a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted" href="./?view=platform&amp;section=modules&amp;edit=<?= bx_h((string) $module['module_key']) ?>">Edit</a></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <form method="post" data-confirm-submit class="grid gap-3 rounded-md bg-muted/30 p-4">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="save_company_module">
                                                    <input type="hidden" name="section" value="modules">
                                                    <input type="hidden" name="module_key" value="<?= bx_h((string) ($editModule['module_key'] ?? '')) ?>">
                                                    <div>
                                                        <h4 class="text-sm font-semibold"><?= $editModule ? 'Edit Module' : 'Add Module' ?></h4>
                                                        <p class="mt-1 text-xs leading-5 text-muted-foreground">A module is an ERP workspace, like HR, Finance, Inventory, or Mobile Stockroom. It can later be enabled, assigned, reported on, and granted through roles, groups, or direct user permission.</p>
                                                    </div>
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div class="grid gap-1.5">
                                                            <label class="text-xs font-medium" for="module_code">Code</label>
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" id="module_code" name="module_code" value="<?= bx_h((string) ($editModule['module_code'] ?? '')) ?>" required>
                                                        </div>
                                                        <div class="grid gap-1.5">
                                                            <label class="text-xs font-medium" for="module_icon">Icon</label>
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" id="module_icon" name="module_icon" value="<?= bx_h((string) ($editModule['module_icon'] ?? '▥')) ?>" maxlength="20">
                                                        </div>
                                                    </div>
                                                    <div class="grid gap-1.5">
                                                        <label class="text-xs font-medium" for="module_name">Name</label>
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="module_name" name="module_name" value="<?= bx_h((string) ($editModule['module_name'] ?? '')) ?>" required>
                                                    </div>
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div class="grid gap-1.5">
                                                            <label class="text-xs font-medium" for="module_status">Status</label>
                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="module_status" name="module_status">
                                                                <?php foreach (['DRAFT', 'ACTIVE', 'INACTIVE', 'DELETED'] as $status): ?>
                                                                    <option value="<?= bx_h($status) ?>" <?= (string) ($editModule['module_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="grid gap-1.5">
                                                            <label class="text-xs font-medium" for="module_sort_order">Sort</label>
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" id="module_sort_order" name="module_sort_order" type="number" min="0" value="<?= bx_h((string) ($editModule['module_sort_order'] ?? '0')) ?>">
                                                        </div>
                                                    </div>
                                                    <div class="grid gap-1.5">
                                                        <label class="text-xs font-medium" for="module_description">Description</label>
                                                        <textarea class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" id="module_description" name="module_description"><?= bx_h((string) ($editModule['module_description'] ?? '')) ?></textarea>
                                                    </div>
                                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Module</button>
                                                </form>
                                            </div>
                                        <?php elseif ($activePlatformSection === 'departments'): ?>
                                            <?php
                                                $departmentRows = $platformData['departments'] ?? [];
                                                $departmentMasters = array_values(array_filter($platformData['departmentMasters'] ?? [], static fn (array $row): bool => (string) ($row['department_status'] ?? '') === 'ACTIVE'));
                                                $departmentBranches = $platformData['branches'] ?? [];
                                                $selectedBranchKey = (string) ($editDepartment['branch_key'] ?? ($departmentBranches[0]['branch_key'] ?? ''));
                                                $selectedSource = (string) ($editDepartment['department_source'] ?? 'ERP_DEFAULT');
                                                $selectedTemplateKey = (string) ($editDepartment['default_department_key'] ?? ($departmentMasters[0]['department_master_key'] ?? ''));
                                                $selectedTemplate = yovel_admin_find_record($departmentMasters, 'department_master_key', $selectedTemplateKey) ?? ($departmentMasters[0] ?? []);
                                                $departmentSummary = [
                                                    'total' => count($departmentRows),
                                                    'active' => count(array_filter($departmentRows, static fn (array $row): bool => (string) ($row['department_status'] ?? '') === 'ACTIVE')),
                                                    'draft' => count(array_filter($departmentRows, static fn (array $row): bool => (string) ($row['department_status'] ?? '') === 'DRAFT')),
                                                    'inactive' => count(array_filter($departmentRows, static fn (array $row): bool => (string) ($row['department_status'] ?? '') === 'INACTIVE')),
                                                    'archived' => count(array_filter($departmentRows, static fn (array $row): bool => (string) ($row['department_status'] ?? '') === 'ARCHIVED')),
                                                    'standard' => count(array_filter($departmentRows, static fn (array $row): bool => (string) ($row['department_source'] ?? '') === 'ERP_DEFAULT')),
                                                    'custom' => count(array_filter($departmentRows, static fn (array $row): bool => (string) ($row['department_source'] ?? '') === 'CUSTOM')),
                                                ];
                                            ?>
                                            <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.85fr)]">
                                                <div class="grid gap-4">
                                                    <div class="overflow-x-auto">
                                                        <table class="w-full min-w-[780px] text-left text-sm">
                                                            <thead class="border-b text-xs text-muted-foreground">
                                                                <tr>
                                                                    <th class="py-2 pr-3 font-medium">Branch</th>
                                                                    <th class="py-2 pr-3 font-medium">Department</th>
                                                                    <th class="py-2 pr-3 font-medium">Type</th>
                                                                    <th class="py-2 pr-3 font-medium">Source</th>
                                                                    <th class="py-2 pr-3 font-medium">Status</th>
                                                                    <th class="py-2 text-left font-medium">Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y">
                                                                <?php if (!$departmentRows): ?>
                                                                    <tr>
                                                                        <td class="py-10 text-center text-muted-foreground" colspan="6">No company departments have been created yet.</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                                <?php foreach ($departmentRows as $department): ?>
                                                                    <tr>
                                                                        <td class="py-3 pr-3"><p class="font-medium"><?= bx_h((string) ($department['branch_name'] ?: 'Branch unavailable')) ?></p><p class="text-xs text-muted-foreground"><?= bx_h((string) $department['branch_code']) ?></p></td>
                                                                        <td class="py-3 pr-3"><p class="font-medium"><?= bx_h((string) $department['department_name']) ?></p><p class="text-xs text-muted-foreground"><?= bx_h((string) $department['department_code']) ?></p><?php if ((string) $department['department_description'] !== ''): ?><p class="mt-1 max-w-md text-xs leading-5 text-muted-foreground"><?= bx_h((string) $department['department_description']) ?></p><?php endif; ?></td>
                                                                        <td class="py-3 pr-3 text-xs text-muted-foreground"><?= bx_h((string) $department['department_type']) ?></td>
                                                                        <td class="py-3 pr-3"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= (string) $department['department_source'] === 'ERP_DEFAULT' ? 'Standard' : 'Custom' ?></span></td>
                                                                        <td class="py-3 pr-3"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $department['department_status']) ?></span></td>
                                                                        <td class="py-3">
                                                                            <div class="flex flex-wrap justify-start gap-1.5">
                                                                                <a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted" href="./?view=platform&amp;section=departments&amp;edit=<?= bx_h((string) $department['department_key']) ?>">Edit</a>
                                                                                <?php foreach ([['INACTIVE', 'Deactivate'], ['ACTIVE', 'Restore'], ['ARCHIVED', 'Archive'], ['DELETED', 'Delete']] as $departmentAction): ?>
                                                                                    <form method="post" data-confirm-submit>
                                                                                        <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                                                        <input type="hidden" name="action" value="set_company_department_status">
                                                                                        <input type="hidden" name="section" value="departments">
                                                                                        <input type="hidden" name="department_key" value="<?= bx_h((string) $department['department_key']) ?>">
                                                                                        <input type="hidden" name="department_status" value="<?= bx_h($departmentAction[0]) ?>">
                                                                                        <button type="submit" class="inline-flex h-8 items-center rounded-md border px-2.5 text-xs font-medium hover:bg-muted" title="<?= bx_h($departmentAction[1] . ' ' . (string) $department['department_code']) ?>"><?= bx_h($departmentAction[1]) ?></button>
                                                                                    </form>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    <div class="grid gap-3 sm:grid-cols-4">
                                                        <div class="rounded-md bg-muted/40 p-3"><p class="text-xs text-muted-foreground">Total</p><p class="mt-1 text-xl font-semibold"><?= $departmentSummary['total'] ?></p></div>
                                                        <div class="rounded-md bg-muted/40 p-3"><p class="text-xs text-muted-foreground">Active</p><p class="mt-1 text-xl font-semibold"><?= $departmentSummary['active'] ?></p></div>
                                                        <div class="rounded-md bg-muted/40 p-3"><p class="text-xs text-muted-foreground">Standard</p><p class="mt-1 text-xl font-semibold"><?= $departmentSummary['standard'] ?></p></div>
                                                        <div class="rounded-md bg-muted/40 p-3"><p class="text-xs text-muted-foreground">Custom</p><p class="mt-1 text-xl font-semibold"><?= $departmentSummary['custom'] ?></p></div>
                                                    </div>
                                                </div>

                                                <form method="post" data-confirm-submit class="grid gap-3 rounded-md bg-muted/30 p-4">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="save_company_department">
                                                    <input type="hidden" name="section" value="departments">
                                                    <input type="hidden" name="department_key" value="<?= bx_h((string) ($editDepartment['department_key'] ?? '')) ?>">
                                                    <div>
                                                        <h4 class="text-sm font-semibold"><?= $editDepartment ? 'Edit Department' : 'Add Department' ?></h4>
                                                        <p class="mt-1 text-xs leading-5 text-muted-foreground">Departments belong to a Yovel East branch. Use a standard ERP department when it matches the organization, or choose Custom for a company-specific team.</p>
                                                    </div>
                                                    <div class="grid gap-1.5">
                                                        <label class="text-xs font-medium" for="department_branch_key">Branch</label>
                                                        <select class="h-9 rounded-md border bg-background px-3 text-sm" id="department_branch_key" name="branch_key" required>
                                                            <?php if (!$departmentBranches): ?><option value="">Create a branch first</option><?php endif; ?>
                                                            <?php foreach ($departmentBranches as $branch): ?>
                                                                <option value="<?= bx_h((string) $branch['branch_key']) ?>" <?= $selectedBranchKey === (string) $branch['branch_key'] ? 'selected' : '' ?>><?= bx_h((string) $branch['branch_code'] . ' - ' . (string) $branch['branch_name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div class="grid gap-1.5">
                                                            <label class="text-xs font-medium" for="department_source">Source</label>
                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="department_source" name="department_source">
                                                                <option value="ERP_DEFAULT" <?= $selectedSource === 'ERP_DEFAULT' ? 'selected' : '' ?>>Standard ERP</option>
                                                                <option value="CUSTOM" <?= $selectedSource === 'CUSTOM' ? 'selected' : '' ?>>Custom</option>
                                                            </select>
                                                        </div>
                                                        <div class="grid gap-1.5">
                                                            <label class="text-xs font-medium" for="department_status">Status</label>
                                                            <select class="h-9 rounded-md border bg-background px-3 text-sm" id="department_status" name="department_status">
                                                                <?php foreach (['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED', 'DELETED'] as $status): ?>
                                                                    <option value="<?= bx_h($status) ?>" <?= (string) ($editDepartment['department_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="grid gap-1.5">
                                                        <label class="text-xs font-medium" for="default_department_key">Standard Department</label>
                                                        <select class="h-9 rounded-md border bg-background px-3 text-sm" id="default_department_key" name="default_department_key">
                                                            <?php if (!$departmentMasters): ?><option value="">No standard departments available</option><?php endif; ?>
                                                            <?php foreach ($departmentMasters as $master): ?>
                                                                <option
                                                                    value="<?= bx_h((string) $master['department_master_key']) ?>"
                                                                    data-code="<?= bx_h((string) $master['department_code']) ?>"
                                                                    data-name="<?= bx_h((string) $master['department_name']) ?>"
                                                                    data-type="<?= bx_h((string) $master['department_type']) ?>"
                                                                    data-description="<?= bx_h((string) $master['department_description']) ?>"
                                                                    <?= $selectedTemplateKey === (string) $master['department_master_key'] ? 'selected' : '' ?>
                                                                ><?= bx_h((string) $master['department_name'] . ' (' . (string) $master['department_code'] . ')') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div class="grid gap-1.5">
                                                            <label class="text-xs font-medium" for="department_code">Code</label>
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" id="department_code" name="department_code" value="<?= bx_h((string) ($editDepartment['department_code'] ?? ($selectedTemplate['department_code'] ?? ''))) ?>" maxlength="40" pattern="[A-Za-z0-9_\-]{2,40}" placeholder="HR" required>
                                                        </div>
                                                        <div class="grid gap-1.5">
                                                            <label class="text-xs font-medium" for="department_type">Type</label>
                                                            <input class="h-9 rounded-md border bg-background px-3 text-sm" id="department_type" name="department_type" value="<?= bx_h((string) ($editDepartment['department_type'] ?? ($selectedTemplate['department_type'] ?? 'OPERATIONS'))) ?>" maxlength="60" pattern="[A-Za-z0-9_ \-]{2,60}" required>
                                                        </div>
                                                    </div>
                                                    <div class="grid gap-1.5">
                                                        <label class="text-xs font-medium" for="department_name">Name</label>
                                                        <input class="h-9 rounded-md border bg-background px-3 text-sm" id="department_name" name="department_name" value="<?= bx_h((string) ($editDepartment['department_name'] ?? ($selectedTemplate['department_name'] ?? ''))) ?>" maxlength="160" placeholder="Human Resources" required>
                                                    </div>
                                                    <div class="grid gap-1.5">
                                                        <label class="text-xs font-medium" for="department_description">Description</label>
                                                        <textarea class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" id="department_description" name="department_description"><?= bx_h((string) ($editDepartment['department_description'] ?? ($selectedTemplate['department_description'] ?? ''))) ?></textarea>
                                                    </div>
                                                    <div class="grid gap-2 rounded-md bg-background/70 p-3">
                                                        <p class="text-xs font-medium text-muted-foreground">Standard Departments</p>
                                                        <div class="grid max-h-40 gap-2 overflow-y-auto">
                                                            <?php foreach ($departmentMasters as $master): ?>
                                                                <div class="text-xs"><span class="font-medium"><?= bx_h((string) $master['department_name']) ?></span><span class="text-muted-foreground"> · <?= bx_h((string) $master['department_type']) ?></span></div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Department</button>
                                                </form>
                                            </div>
                                        <?php elseif ($activePlatformSection === 'users'): ?>
                                            <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(380px,0.8fr)]">
                                                <div class="overflow-x-auto">
                                                    <table class="w-full min-w-[680px] text-left text-sm">
                                                        <thead class="border-b text-xs text-muted-foreground">
                                                            <tr><th class="py-2 pr-3 font-medium">User</th><th class="py-2 pr-3 font-medium">Department</th><th class="py-2 pr-3 font-medium">Status</th><th class="py-2 pr-3 font-medium">Assignments</th><th class="py-2 text-right font-medium">Action</th></tr>
                                                        </thead>
                                                        <tbody class="divide-y">
                                                            <?php foreach (($platformData['users'] ?? []) as $user): ?>
                                                                <?php $userKey = (string) $user['user_key']; ?>
                                                                <tr>
                                                                    <td class="py-3 pr-3"><p class="font-medium"><?= bx_h((string) $user['user_name']) ?></p><p class="text-xs text-muted-foreground"><?= bx_h((string) $user['user_login']) ?> · <?= bx_h((string) $user['user_email']) ?></p></td>
                                                                    <td class="py-3 pr-3 text-xs text-muted-foreground"><?= bx_h((string) $user['user_department']) ?></td>
                                                                    <td class="py-3 pr-3"><span class="rounded-full bg-secondary px-2 py-0.5 text-xs"><?= bx_h((string) $user['user_status']) ?></span></td>
                                                                    <td class="py-3 pr-3 text-xs text-muted-foreground"><?= count($platformData['userRoles'][$userKey] ?? []) ?> roles · <?= count($platformData['userGroups'][$userKey] ?? []) ?> groups · <?= count($platformData['userPermissions'][$userKey] ?? []) ?> direct permissions</td>
                                                                    <td class="py-3 text-right"><a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted" href="./?view=platform&amp;section=users&amp;edit=<?= bx_h($userKey) ?>">Edit</a></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <?php $selectedUserKey = (string) ($editUser['user_key'] ?? ''); ?>
                                                <form method="post" data-confirm-submit class="grid gap-3 rounded-md bg-muted/30 p-4">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="save_company_user">
                                                    <input type="hidden" name="section" value="users">
                                                    <input type="hidden" name="user_key" value="<?= bx_h($selectedUserKey) ?>">
                                                    <h4 class="text-sm font-semibold"><?= $editUser ? 'Edit User' : 'Create User' ?></h4>
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div class="grid gap-1.5"><label class="text-xs font-medium" for="user_login">Login</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="user_login" name="user_login" value="<?= bx_h((string) ($editUser['user_login'] ?? '')) ?>" required></div>
                                                        <div class="grid gap-1.5"><label class="text-xs font-medium" for="user_status">Status</label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="user_status" name="user_status"><?php foreach (['DRAFT', 'ACTIVE', 'INACTIVE', 'LOCKED', 'DELETED'] as $status): ?><option value="<?= bx_h($status) ?>" <?= (string) ($editUser['user_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option><?php endforeach; ?></select></div>
                                                    </div>
                                                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="user_name">Name</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="user_name" name="user_name" value="<?= bx_h((string) ($editUser['user_name'] ?? '')) ?>" required></div>
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div class="grid gap-1.5"><label class="text-xs font-medium" for="user_email">Email</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="user_email" name="user_email" type="email" value="<?= bx_h((string) ($editUser['user_email'] ?? '')) ?>" required></div>
                                                        <div class="grid gap-1.5"><label class="text-xs font-medium" for="user_department">Department</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="user_department" name="user_department" value="<?= bx_h((string) ($editUser['user_department'] ?? '')) ?>"></div>
                                                    </div>
                                                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="user_password">Password</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="user_password" name="user_password" type="password" autocomplete="new-password" placeholder="<?= $editUser ? 'Leave blank to keep current password' : 'Optional until login is wired' ?>"></div>
                                                    <div class="grid gap-3">
                                                        <p class="text-xs font-medium">Roles</p>
                                                        <div class="grid max-h-36 gap-2 overflow-y-auto rounded-md border bg-background p-3"><?php foreach (($platformData['roles'] ?? []) as $role): ?><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="role_keys[]" value="<?= bx_h((string) $role['role_key']) ?>" <?= in_array((string) $role['role_key'], $platformData['userRoles'][$selectedUserKey] ?? [], true) ? 'checked' : '' ?>><?= bx_h((string) $role['role_name']) ?></label><?php endforeach; ?></div>
                                                        <p class="text-xs font-medium">Groups</p>
                                                        <div class="grid max-h-36 gap-2 overflow-y-auto rounded-md border bg-background p-3"><?php foreach (($platformData['groups'] ?? []) as $group): ?><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="group_keys[]" value="<?= bx_h((string) $group['group_key']) ?>" <?= in_array((string) $group['group_key'], $platformData['userGroups'][$selectedUserKey] ?? [], true) ? 'checked' : '' ?>><?= bx_h((string) $group['group_name']) ?></label><?php endforeach; ?></div>
                                                        <p class="text-xs font-medium">Direct Permissions</p>
                                                        <div class="grid max-h-36 gap-2 overflow-y-auto rounded-md border bg-background p-3"><?php foreach (($platformData['permissions'] ?? []) as $permission): ?><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="permission_keys[]" value="<?= bx_h((string) $permission['permission_key']) ?>" <?= in_array((string) $permission['permission_key'], $platformData['userPermissions'][$selectedUserKey] ?? [], true) ? 'checked' : '' ?>><?= bx_h((string) $permission['permission_code']) ?></label><?php endforeach; ?></div>
                                                    </div>
                                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save User</button>
                                                </form>
                                            </div>
                                        <?php elseif ($activePlatformSection === 'roles'): ?>
                                            <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(380px,0.8fr)]">
                                                <div class="grid gap-2">
                                                    <?php foreach (($platformData['roles'] ?? []) as $role): ?>
                                                        <?php $roleKey = (string) $role['role_key']; ?>
                                                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-background p-3">
                                                            <div><p class="text-sm font-semibold"><?= bx_h((string) $role['role_name']) ?></p><p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $role['role_code']) ?> · <?= count($platformData['rolePermissions'][$roleKey] ?? []) ?> permissions</p></div>
                                                            <a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted" href="./?view=platform&amp;section=roles&amp;edit=<?= bx_h($roleKey) ?>">Edit</a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php $selectedRoleKey = (string) ($editRole['role_key'] ?? ''); ?>
                                                <form method="post" data-confirm-submit class="grid gap-3 rounded-md bg-muted/30 p-4">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="save_company_role"><input type="hidden" name="section" value="roles"><input type="hidden" name="role_key" value="<?= bx_h($selectedRoleKey) ?>">
                                                    <h4 class="text-sm font-semibold"><?= $editRole ? 'Edit Role' : 'Add Role' ?></h4>
                                                    <div class="grid gap-2 sm:grid-cols-2"><div class="grid gap-1.5"><label class="text-xs font-medium" for="role_code">Code</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="role_code" name="role_code" value="<?= bx_h((string) ($editRole['role_code'] ?? '')) ?>" required></div><div class="grid gap-1.5"><label class="text-xs font-medium" for="role_status">Status</label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="role_status" name="role_status"><?php foreach (['ACTIVE', 'INACTIVE', 'DELETED'] as $status): ?><option value="<?= bx_h($status) ?>" <?= (string) ($editRole['role_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option><?php endforeach; ?></select></div></div>
                                                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="role_name">Name</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="role_name" name="role_name" value="<?= bx_h((string) ($editRole['role_name'] ?? '')) ?>" required></div>
                                                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="role_description">Description</label><textarea class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" id="role_description" name="role_description"><?= bx_h((string) ($editRole['role_description'] ?? '')) ?></textarea></div>
                                                    <p class="text-xs font-medium">Permissions</p>
                                                    <div class="grid max-h-56 gap-2 overflow-y-auto rounded-md border bg-background p-3"><?php foreach (($platformData['permissions'] ?? []) as $permission): ?><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="permission_keys[]" value="<?= bx_h((string) $permission['permission_key']) ?>" <?= in_array((string) $permission['permission_key'], $platformData['rolePermissions'][$selectedRoleKey] ?? [], true) ? 'checked' : '' ?>><?= bx_h((string) $permission['permission_code']) ?></label><?php endforeach; ?></div>
                                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Role</button>
                                                </form>
                                            </div>
                                        <?php elseif ($activePlatformSection === 'permissions'): ?>
                                            <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(380px,0.8fr)]">
                                                <div class="grid gap-2">
                                                    <?php foreach (($platformData['permissions'] ?? []) as $permission): ?>
                                                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-background p-3">
                                                            <div><p class="text-sm font-semibold"><?= bx_h((string) $permission['permission_name']) ?></p><p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $permission['permission_code']) ?> · <?= bx_h((string) ($permission['module_name'] ?? 'No module')) ?></p></div>
                                                            <a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted" href="./?view=platform&amp;section=permissions&amp;edit=<?= bx_h((string) $permission['permission_key']) ?>">Edit</a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <form method="post" data-confirm-submit class="grid gap-3 rounded-md bg-muted/30 p-4">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="save_company_permission"><input type="hidden" name="section" value="permissions"><input type="hidden" name="permission_key" value="<?= bx_h((string) ($editPermission['permission_key'] ?? '')) ?>">
                                                    <h4 class="text-sm font-semibold"><?= $editPermission ? 'Edit Permission' : 'Add Permission' ?></h4>
                                                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="permission_code">Code</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="permission_code" name="permission_code" value="<?= bx_h((string) ($editPermission['permission_code'] ?? '')) ?>" placeholder="hr.employee.view" required></div>
                                                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="permission_name">Name</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="permission_name" name="permission_name" value="<?= bx_h((string) ($editPermission['permission_name'] ?? '')) ?>" required></div>
                                                    <div class="grid gap-2 sm:grid-cols-2"><div class="grid gap-1.5"><label class="text-xs font-medium" for="permission_scope">Scope</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="permission_scope" name="permission_scope" value="<?= bx_h((string) ($editPermission['permission_scope'] ?? 'module')) ?>"></div><div class="grid gap-1.5"><label class="text-xs font-medium" for="permission_status">Status</label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="permission_status" name="permission_status"><?php foreach (['ACTIVE', 'INACTIVE', 'DELETED'] as $status): ?><option value="<?= bx_h($status) ?>" <?= (string) ($editPermission['permission_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option><?php endforeach; ?></select></div></div>
                                                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="permission_module_key">Module</label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="permission_module_key" name="module_key"><option value="">No module</option><?php foreach (($platformData['modules'] ?? []) as $module): ?><option value="<?= bx_h((string) $module['module_key']) ?>" <?= (string) ($editPermission['module_key'] ?? '') === (string) $module['module_key'] ? 'selected' : '' ?>><?= bx_h((string) $module['module_name']) ?></option><?php endforeach; ?></select></div>
                                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Permission</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(380px,0.8fr)]">
                                                <div class="grid gap-2">
                                                    <?php foreach (($platformData['groups'] ?? []) as $group): ?>
                                                        <?php $groupKey = (string) $group['group_key']; ?>
                                                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-background p-3">
                                                            <div><p class="text-sm font-semibold"><?= bx_h((string) $group['group_name']) ?></p><p class="mt-1 text-xs text-muted-foreground"><?= bx_h((string) $group['group_code']) ?> · <?= count($platformData['groupPermissions'][$groupKey] ?? []) ?> permissions</p></div>
                                                            <a class="inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium hover:bg-muted" href="./?view=platform&amp;section=groups&amp;edit=<?= bx_h($groupKey) ?>">Edit</a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php $selectedGroupKey = (string) ($editGroup['group_key'] ?? ''); ?>
                                                <form method="post" data-confirm-submit class="grid gap-3 rounded-md bg-muted/30 p-4">
                                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>"><input type="hidden" name="action" value="save_company_group"><input type="hidden" name="section" value="groups"><input type="hidden" name="group_key" value="<?= bx_h($selectedGroupKey) ?>">
                                                    <h4 class="text-sm font-semibold"><?= $editGroup ? 'Edit Group' : 'Add Group' ?></h4>
                                                    <div class="grid gap-2 sm:grid-cols-2"><div class="grid gap-1.5"><label class="text-xs font-medium" for="group_code">Code</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="group_code" name="group_code" value="<?= bx_h((string) ($editGroup['group_code'] ?? '')) ?>" required></div><div class="grid gap-1.5"><label class="text-xs font-medium" for="group_status">Status</label><select class="h-9 rounded-md border bg-background px-3 text-sm" id="group_status" name="group_status"><?php foreach (['ACTIVE', 'INACTIVE', 'DELETED'] as $status): ?><option value="<?= bx_h($status) ?>" <?= (string) ($editGroup['group_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= bx_h($status) ?></option><?php endforeach; ?></select></div></div>
                                                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="group_name">Name</label><input class="h-9 rounded-md border bg-background px-3 text-sm" id="group_name" name="group_name" value="<?= bx_h((string) ($editGroup['group_name'] ?? '')) ?>" required></div>
                                                    <div class="grid gap-1.5"><label class="text-xs font-medium" for="group_description">Description</label><textarea class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" id="group_description" name="group_description"><?= bx_h((string) ($editGroup['group_description'] ?? '')) ?></textarea></div>
                                                    <p class="text-xs font-medium">Users</p>
                                                    <div class="grid max-h-40 gap-2 overflow-y-auto rounded-md border bg-background p-3"><?php foreach (($platformData['users'] ?? []) as $user): ?><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="user_keys[]" value="<?= bx_h((string) $user['user_key']) ?>" <?= in_array($selectedGroupKey, $platformData['userGroups'][(string) $user['user_key']] ?? [], true) ? 'checked' : '' ?>><?= bx_h((string) $user['user_name']) ?></label><?php endforeach; ?></div>
                                                    <p class="text-xs font-medium">Permissions</p>
                                                    <div class="grid max-h-40 gap-2 overflow-y-auto rounded-md border bg-background p-3"><?php foreach (($platformData['permissions'] ?? []) as $permission): ?><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="permission_keys[]" value="<?= bx_h((string) $permission['permission_key']) ?>" <?= in_array((string) $permission['permission_key'], $platformData['groupPermissions'][$selectedGroupKey] ?? [], true) ? 'checked' : '' ?>><?= bx_h((string) $permission['permission_code']) ?></label><?php endforeach; ?></div>
                                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save Group</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </section>
                                    <?php endif; ?>
                                </div>
