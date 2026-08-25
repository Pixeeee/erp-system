<?php
$activeModuleRoute = yovel_admin_module_route($activeView);
$activeModuleWorkspace = $activeModuleRoute
    ? dirname(__DIR__) . '/' . (string) $activeModuleRoute['workspace_file']
    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= bx_h($pageTitle) ?></title>
    <script>
        if (window.localStorage.getItem('<?= bx_h($companyThemeKey) ?>') === 'dark') {
            document.documentElement.classList.add('dark');
        }
        if (window.localStorage.getItem('<?= bx_h($companySidebarKey) ?>') === 'collapsed') {
            document.documentElement.dataset.sidebarCollapsed = 'true';
        }
    </script>
    <?php foreach ($assets['css'] as $css): ?>
        <link rel="stylesheet" href="<?= bx_h($assets['base'] . $css) ?>">
    <?php endforeach; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20,500,0,0">
    <link rel="stylesheet" href="<?= bx_h(bx_project_base_path() . 'company/admin/assets/css/admin.css?v=' . (string) filemtime(dirname(__DIR__) . '/assets/css/admin.css')) ?>">
</head>
<body class="bg-background text-foreground">
    <main class="<?= $admin ? 'h-svh min-h-0 overflow-hidden bg-background p-2 sm:p-3' : 'min-h-svh bg-background p-4 sm:p-6' ?>">
        <div class="<?= $admin ? 'flex h-full min-h-0 w-full flex-col gap-3' : 'mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-6xl flex-col justify-center gap-4' ?>">
            <?php if (!$admin): ?>
                <div class="flex items-center justify-between gap-3 border-b pb-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex aspect-square size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <div class="grid gap-1" aria-hidden="true">
                                <span class="block h-0.5 w-5 rounded bg-current"></span>
                                <span class="block h-3.5 w-5 rounded-sm border border-current"></span>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium"><?= bx_h($companyName) ?></p>
                            <p class="truncate text-xs text-muted-foreground">Company Admin Portal</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted"
                        aria-label="<?= bx_h($nextThemeLabel) ?>"
                        title="<?= bx_h($nextThemeLabel) ?>"
                        onclick="document.documentElement.classList.toggle('dark'); window.localStorage.setItem('<?= bx_h($companyThemeKey) ?>', document.documentElement.classList.contains('dark') ? 'dark' : 'light')"
                    >◐</button>
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="rounded-lg border <?= ($flash['type'] ?? '') === 'error' ? 'border-destructive/40 bg-destructive/10 text-destructive' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' ?> px-4 py-3 text-sm" role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>">
                    <?= bx_h((string) ($flash['message'] ?? '')) ?>
                </div>
            <?php endif; ?>

            <?php if (!$company): ?>
                <section class="rounded-lg border bg-card p-5">
                    <p class="text-sm font-semibold">Company Admin is unavailable.</p>
                    <p class="mt-1 text-sm text-muted-foreground">The active company record for <?= bx_h($companySlug !== '' ? $companySlug : 'this URL') ?> was not found.</p>
                </section>
            <?php elseif ($admin): ?>
                <div class="flex min-h-0 flex-1 overflow-hidden rounded-lg border bg-background">
                    <aside class="yovel-desktop-sidebar hidden w-64 shrink-0 border-r bg-sidebar text-sidebar-foreground lg:flex lg:flex-col">
                        <div class="yovel-sidebar-brand flex h-16 shrink-0 items-center gap-3 border-b px-4">
                            <div class="flex aspect-square size-9 items-center justify-center rounded-lg bg-primary text-sm font-semibold text-primary-foreground"><?= bx_h($companyInitials) ?></div>
                            <div class="yovel-sidebar-label min-w-0">
                                <p class="truncate text-sm font-semibold"><?= bx_h($companyName) ?></p>
                                <p class="truncate text-xs text-sidebar-foreground/70">Company Admin Portal</p>
                            </div>
                        </div>
                        <nav class="yovel-sidebar-nav min-h-0 flex-1 overflow-hidden overscroll-contain p-2">
                            <p class="yovel-sidebar-section px-2 pb-2 text-xs font-medium text-sidebar-foreground/60">Workspace</p>
                            <div class="grid gap-1">
                                <a class="yovel-sidebar-link flex h-9 items-center gap-2 rounded-md px-2 text-sm font-medium <?= $activeView === 'dashboard' ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'hover:bg-sidebar-accent/70' ?>" href="./?view=dashboard" title="Dashboard">
                                    <span class="inline-flex size-4 items-center justify-center" aria-hidden="true">▦</span>
                                    <span class="yovel-sidebar-label">Dashboard</span>
                                </a>
                                <details class="group rounded-md" <?= $activeView === 'platform' && $activePlatformSection !== 'modules' ? 'open' : '' ?>>
                                    <summary class="yovel-erp-summary yovel-sidebar-summary flex h-9 cursor-pointer select-none items-center gap-2 rounded-md px-2 text-sm font-medium <?= $activeView === 'platform' && $activePlatformSection !== 'modules' ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'hover:bg-sidebar-accent/70' ?>" title="Platform">
                                        <span class="inline-flex size-4 items-center justify-center" aria-hidden="true">⌘</span>
                                        <span class="yovel-sidebar-label min-w-0 flex-1 truncate">Platform</span>
                                        <span class="yovel-erp-chevron yovel-sidebar-chevron text-xs text-sidebar-foreground/60" aria-hidden="true">›</span>
                                    </summary>
                                    <div class="yovel-sidebar-submenu ml-6 mt-1 grid gap-1 border-l pl-2">
                                        <?php foreach ($platformNav as $sectionKey => $sectionMeta): ?>
                                            <?php if ($sectionKey === 'modules') { continue; } ?>
                                            <a class="block rounded-md px-2 py-1.5 text-xs leading-4 <?= $activeView === 'platform' && $activePlatformSection === $sectionKey ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'text-sidebar-foreground/75 hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground' ?>" href="./?view=platform&amp;section=<?= bx_h((string) $sectionKey) ?>">
                                                <span class="mr-1" aria-hidden="true"><?= bx_h((string) $sectionMeta['icon']) ?></span><?= bx_h((string) $sectionMeta['label']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                                <a class="yovel-sidebar-link flex h-9 items-center gap-2 rounded-md px-2 text-sm font-medium <?= $activeView === 'platform' && $activePlatformSection === 'modules' ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'hover:bg-sidebar-accent/70' ?>" href="./?view=platform&amp;section=modules" title="Modules">
                                    <span class="inline-flex size-4 items-center justify-center" aria-hidden="true">▥</span>
                                    <span class="yovel-sidebar-label">Modules</span>
                                </a>
                            </div>
                            <div class="mt-5">
                                <p class="yovel-sidebar-section px-2 pb-2 text-xs font-medium text-sidebar-foreground/60">ERP System</p>
                                <div class="grid gap-1">
                                    <?php foreach ($erpGroups as $group): ?>
                                        <?php $groupRoute = yovel_admin_module_route_by_label((string) $group['label']); ?>
                                        <?php $isHrGroup = (string) $group['label'] === 'HR Department'; ?>
                                        <?php $isActiveErpGroup = $groupRoute && (string) $groupRoute['view'] === $activeView; ?>
                                        <details class="group rounded-md" <?= $isActiveErpGroup ? 'open' : '' ?>>
                                            <summary class="yovel-erp-summary yovel-sidebar-summary flex min-h-8 cursor-pointer select-none items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium <?= $isActiveErpGroup ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'hover:bg-sidebar-accent/70' ?>" title="<?= bx_h((string) $group['label']) ?>">
                                                <span class="yovel-erp-icon-badge <?= $isHrGroup ? 'yovel-erp-icon-badge--hr' : '' ?> <?= $isActiveErpGroup ? 'yovel-erp-icon-badge--active' : '' ?> inline-flex size-5 shrink-0 items-center justify-center text-xs" aria-hidden="true"><?= bx_h((string) $group['icon']) ?></span>
                                                <span class="yovel-sidebar-label min-w-0 flex-1 truncate"><?= bx_h((string) $group['label']) ?></span>
                                                <span class="yovel-sidebar-count rounded-full bg-sidebar-accent px-1.5 py-0.5 text-[10px] leading-none text-sidebar-foreground/70"><?= count($group['features']) ?></span>
                                                <span class="yovel-erp-chevron yovel-sidebar-chevron text-xs text-sidebar-foreground/60" aria-hidden="true">›</span>
                                            </summary>
                                            <div class="yovel-sidebar-submenu ml-6 mt-1 grid gap-1 border-l pl-2">
                                                <?php foreach ($group['features'] as $feature): ?>
                                                    <?php $featureSlug = $groupRoute ? yovel_admin_module_feature_section($groupRoute, (string) $feature) : yovel_admin_slug((string) $feature); ?>
                                                    <?php $isActiveFeature = $isActiveErpGroup && $activeModuleSection === $featureSlug; ?>
                                                    <a class="block rounded-md px-2 py-1.5 text-xs leading-4 <?= $isActiveFeature ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'text-sidebar-foreground/75 hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground' ?>" href="<?= bx_h(yovel_admin_erp_feature_href($group, (string) $feature)) ?>">
                                                        <?= bx_h((string) $feature) ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </nav>
                        <div class="shrink-0 border-t p-3">
                            <div class="yovel-sidebar-user flex items-center gap-3 rounded-md bg-sidebar-accent/60 p-2">
                                <div class="flex aspect-square size-8 items-center justify-center rounded-full bg-background text-xs font-semibold"><?= bx_h(substr((string) $admin['admin_name'], 0, 1) . 'A') ?></div>
                                <div class="yovel-sidebar-label min-w-0">
                                    <p class="truncate text-sm font-semibold"><?= bx_h((string) $admin['admin_name']) ?></p>
                                    <p class="truncate text-xs text-sidebar-foreground/70"><?= bx_h((string) $admin['admin_login']) ?></p>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <section class="flex min-w-0 flex-1 flex-col">
                        <header class="yovel-sticky-shell-header flex h-16 shrink-0 items-center justify-between gap-3 border-b px-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <button
                                    type="button"
                                    id="yovel-sidebar-toggle"
                                    class="yovel-sidebar-toggle size-9 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted"
                                    aria-label="Toggle sidebar"
                                    title="Toggle sidebar"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                        <path d="M9 4v16"></path>
                                    </svg>
                                </button>
                                <div class="min-w-0">
                                    <h1 class="truncate text-base font-semibold tracking-normal"><?= bx_h($viewTitle) ?></h1>
                                    <p class="truncate text-xs text-muted-foreground">Company Admin &gt; <?= bx_h($viewCrumb) ?></p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <a class="hidden h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted sm:inline-flex" href="https://ui.shadcn.com/" target="_blank" rel="noopener noreferrer">↗ shadcn/ui</a>
                                <button
                                    type="button"
                                    class="inline-flex size-9 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted"
                                    aria-label="<?= bx_h($nextThemeLabel) ?>"
                                    title="<?= bx_h($nextThemeLabel) ?>"
                                    onclick="document.documentElement.classList.toggle('dark'); window.localStorage.setItem('<?= bx_h($companyThemeKey) ?>', document.documentElement.classList.contains('dark') ? 'dark' : 'light')"
                                >◐</button>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="logout">
                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Sign Out</button>
                                </form>
                            </div>
                        </header>

                        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 sm:p-6">
                            <div class="yovel-mobile-nav mb-4 grid gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a class="inline-flex h-9 min-w-fit flex-1 items-center justify-center rounded-md border px-3 text-sm font-medium <?= $activeView === 'dashboard' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=dashboard">Dashboard</a>
                                    <a class="inline-flex h-9 min-w-fit flex-1 items-center justify-center rounded-md border px-3 text-sm font-medium <?= $activeView === 'platform' && $activePlatformSection !== 'modules' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=platform">Platform</a>
                                    <a class="inline-flex h-9 min-w-fit flex-1 items-center justify-center rounded-md border px-3 text-sm font-medium <?= $activeView === 'platform' && $activePlatformSection === 'modules' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=platform&amp;section=modules">Modules</a>
                                    <a class="inline-flex h-9 min-w-fit flex-1 items-center justify-center rounded-md border px-3 text-sm font-medium <?= $activeView === 'hr' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=hr&amp;section=employee-profiles">HR</a>
                                    <a class="inline-flex h-9 min-w-fit flex-1 items-center justify-center rounded-md border px-3 text-sm font-medium <?= $activeView === 'sales-crm' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=sales-crm&amp;section=leads">Sales</a>
                                    <a class="inline-flex h-9 min-w-fit flex-1 items-center justify-center rounded-md border px-3 text-sm font-medium <?= $activeView === 'accounting-finance' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=accounting-finance&amp;section=chart-of-accounts">Finance</a>
                                </div>
                                <?php if ($activeView === 'accounting-finance'): ?>
                                    <div class="yovel-finance-scroll-shell">
                                        <div class="yovel-finance-scroll flex gap-2 overflow-x-auto px-0.5 pb-2">
                                            <?php foreach ($accountingFinanceSections as $sectionKey => $sectionMeta): ?>
                                                <a class="inline-flex h-8 shrink-0 items-center gap-1 rounded-md border px-3 text-xs font-medium <?= $activeAccountingFinanceSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=accounting-finance&amp;section=<?= bx_h((string) $sectionKey) ?>">
                                                    <span aria-hidden="true"><?= bx_h((string) $sectionMeta['icon']) ?></span><?= bx_h((string) $sectionMeta['label']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($activeView === 'platform' && !$isModulesView): ?>
                                    <div class="flex gap-2 overflow-x-auto pb-1">
                                        <?php foreach ($platformNav as $sectionKey => $sectionMeta): ?>
                                            <?php if ($sectionKey === 'modules') { continue; } ?>
                                            <a class="inline-flex h-8 shrink-0 items-center gap-1 rounded-md border px-3 text-xs font-medium <?= $activePlatformSection === $sectionKey ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=platform&amp;section=<?= bx_h((string) $sectionKey) ?>">
                                                <span aria-hidden="true"><?= bx_h((string) $sectionMeta['icon']) ?></span><?= bx_h((string) $sectionMeta['label']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($activeView === 'platform'): ?>
                                <?php require dirname(__DIR__) . '/modules/platform/views/workspace.php'; ?>
                            <?php elseif ($activeModuleRoute && is_file($activeModuleWorkspace)): ?>
                                <?php require $activeModuleWorkspace; ?>
                            <?php else: ?>
                                <?php require __DIR__ . '/dashboard.php'; ?>
                            <?php endif; ?>
                        </div>

                        <footer class="yovel-shell-footer sticky bottom-0 z-10 flex shrink-0 flex-col gap-1 border-t bg-background px-4 py-3 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                            <span><?= bx_h($companyName) ?> Company Admin</span>
                            <span>Build Target COMPANY-ERP-PLATFORM</span>
                        </footer>
                    </section>
                </div>
            <?php else: ?>
                <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_420px]">
                    <section class="grid gap-4">
                        <div class="rounded-lg border bg-card">
                            <div class="px-5 py-4">
                                <span class="inline-flex w-fit items-center rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">Company Admin Portal</span>
                                <h1 class="mt-3 text-2xl font-semibold tracking-normal"><?= bx_h($companyName) ?></h1>
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">Manage company administration, branches, projects, ERP dashboards, and company-scoped operations from one workspace.</p>
                            </div>
                            <div class="grid gap-3 p-5 pt-0 md:grid-cols-3">
                                <div class="rounded-md border bg-background p-4">
                                    <p class="text-sm font-semibold">Protected Company Portal</p>
                                    <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= bx_h($companyName) ?> admin access is required.</p>
                                </div>
                                <div class="rounded-md border bg-background p-4">
                                    <p class="text-sm font-semibold">Company Scope</p>
                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">This login is separate from the BuilderX system administrator.</p>
                                </div>
                                <div class="rounded-md border bg-background p-4">
                                    <p class="text-sm font-semibold">ERP Workspace</p>
                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Project ERP tools will open here after setup.</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="../../../">User Portal</a>
                            <a class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted" href="../../../administrator/">Administrator Portal</a>
                        </div>
                    </section>

                    <aside class="lg:sticky lg:top-6">
                        <div class="rounded-lg border bg-card">
                            <div class="px-5 py-4">
                                <h2 class="text-base font-semibold tracking-normal"><?= bx_h($companyName) ?> Admin Login</h2>
                                <p class="mt-1 text-sm text-muted-foreground">Company admin access is required to access this portal.</p>
                            </div>
                            <div class="p-5 pt-0">
                                <form method="post" class="grid gap-3">
                                    <input type="hidden" name="csrf" value="<?= bx_h(bx_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="login">
                                    <div class="grid gap-2">
                                        <label class="text-sm font-medium" for="login">Username</label>
                                        <input class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" id="login" name="login" autocomplete="username" required>
                                    </div>
                                    <div class="grid gap-2">
                                        <label class="text-sm font-medium" for="password">Password</label>
                                        <input class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" id="password" name="password" type="password" autocomplete="current-password" required>
                                    </div>
                                    <button type="submit" class="mt-5 inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Login</button>
                                </form>
                            </div>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/partials/confirm-dialog.php'; ?>
    <?php require __DIR__ . '/partials/scripts.php'; ?>
    <script src="<?= bx_h(bx_project_base_path() . 'company/admin/assets/js/admin-modal.js?v=' . (string) filemtime(dirname(__DIR__) . '/assets/js/admin-modal.js')) ?>"></script>
    <script src="<?= bx_h(bx_project_base_path() . 'company/admin/assets/js/hr-teams.js?v=' . (string) filemtime(dirname(__DIR__) . '/assets/js/hr-teams.js')) ?>"></script>
</body>
</html>
