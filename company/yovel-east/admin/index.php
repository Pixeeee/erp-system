<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/foundation.php';

function yovel_admin_redirect(): void
{
    header('Location: ./');
    exit;
}

function yovel_admin_company(): ?array
{
    $company = bx_db()->GetRow(
        "SELECT company_key, company_key_hash, company_code, company_name, company_status, company_email, company_phone, company_description
        FROM project_company
        WHERE company_code = ? AND company_status = 'ACTIVE'
        LIMIT 1",
        ['YE']
    );

    return $company ?: null;
}

function yovel_admin_current(?array $company): ?array
{
    if (!$company || empty($_SESSION['builderx_company_admin_key'])) {
        return null;
    }

    $admin = bx_db()->GetRow(
        "SELECT admin_key, company_key_hash, admin_login, admin_name, admin_email, admin_status, admin_last_login_at
        FROM project_company_admin
        WHERE admin_key = ? AND company_key_hash = ? AND admin_status = 'ACTIVE'
        LIMIT 1",
        [$_SESSION['builderx_company_admin_key'], $company['company_key_hash']]
    );

    return $admin ?: null;
}

function yovel_admin_login(array $company, string $login, string $password): bool
{
    $db = bx_db();
    $identity = trim($login);
    if ($identity === '' || $password === '') {
        return false;
    }

    $admin = $db->GetRow(
        "SELECT *
        FROM project_company_admin
        WHERE company_key_hash = ? AND admin_login = ? AND admin_status IN ('ACTIVE','LOCKED')
        LIMIT 1",
        [$company['company_key_hash'], $identity]
    );
    if (!$admin || (string) $admin['admin_status'] === 'LOCKED') {
        return false;
    }

    if (!password_verify($password, (string) $admin['admin_password_hash'])) {
        $failed = (int) $admin['admin_failed_login_count'] + 1;
        $status = $failed >= 5 ? 'LOCKED' : 'ACTIVE';
        $db->BeginTrans();
        try {
            $updated = $db->Execute(
                'UPDATE project_company_admin SET admin_failed_login_count = ?, admin_status = ? WHERE admin_key = ? AND company_key_hash = ?',
                [$failed, $status, $admin['admin_key'], $company['company_key_hash']]
            );
            if ($updated === false) {
                $databaseError = trim((string) $db->ErrorMsg());
                throw new RuntimeException('Company admin failed-login update failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
            }
            $db->CommitTrans();
        } catch (Throwable $error) {
            $db->RollbackTrans();
            throw $error;
        }
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['builderx_company_admin_key'] = $admin['admin_key'];
    $_SESSION['builderx_company_admin_company_hash'] = $company['company_key_hash'];

    $db->BeginTrans();
    try {
        $updated = $db->Execute(
            'UPDATE project_company_admin SET admin_failed_login_count = 0, admin_last_login_at = CURRENT_TIMESTAMP WHERE admin_key = ? AND company_key_hash = ?',
            [$admin['admin_key'], $company['company_key_hash']]
        );
        if ($updated === false) {
            $databaseError = trim((string) $db->ErrorMsg());
            throw new RuntimeException('Company admin login update failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
        }
        bx_audit('LOGIN', 'project_company_admin', (string) $admin['admin_key'], [
            'company_code' => (string) $company['company_code'],
            'company_name' => (string) $company['company_name'],
            'admin_login' => (string) $admin['admin_login'],
        ], 'Yovel East company administrator signed in.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        unset($_SESSION['builderx_company_admin_key'], $_SESSION['builderx_company_admin_company_hash']);
        throw $error;
    }

    return true;
}

function yovel_admin_logout(): void
{
    unset($_SESSION['builderx_company_admin_key'], $_SESSION['builderx_company_admin_company_hash']);
}

function yovel_admin_asset_entry(): array
{
    $manifestPath = __DIR__ . '/../../../frontend/dist/.vite/manifest.json';
    $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : [];
    $entry = is_array($manifest) ? ($manifest['index.html'] ?? []) : [];
    $css = is_array($entry) && isset($entry['css']) && is_array($entry['css']) ? $entry['css'] : [];

    return [
        'css' => $css,
        'base' => '../../../frontend/dist/',
    ];
}

function yovel_admin_view(): string
{
    $view = strtolower(trim((string) ($_GET['view'] ?? 'dashboard')));
    return in_array($view, ['dashboard', 'platform'], true) ? $view : 'dashboard';
}

function yovel_admin_slug(string $value): string
{
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    return $slug !== '' ? $slug : 'item';
}

function yovel_admin_erp_groups(): array
{
    return [
        [
            'label' => 'HR Department',
            'description' => 'Employee operations, attendance, leave, onboarding, and HR reporting.',
            'features' => ['Employee profiles', 'Departments', 'Job positions', 'Teams', 'Attendance', 'Leave requests', 'Leave approvals', 'Payroll access', 'Recruitment', 'Onboarding', 'Employee documents', 'HR reports'],
        ],
        [
            'label' => 'Accounting / Finance',
            'description' => 'Financial books, payment movement, fiscal controls, and statutory reports.',
            'features' => ['Chart of accounts', 'Cost centers', 'Accounting dimensions', 'Sales invoices', 'Purchase invoices', 'Journal entries', 'Payment entries', 'Bank accounts', 'Bank reconciliation', 'Budgets', 'Period closing', 'Profit/loss', 'Balance sheet', 'Cash flow', 'Tax reports'],
        ],
        [
            'label' => 'Sales / CRM',
            'description' => 'Customer pipeline, sales documents, credit limits, and team performance.',
            'features' => ['Leads', 'Opportunities', 'Campaigns', 'Customers', 'Quotations', 'Sales orders', 'Customer credit limits', 'Sales analytics', 'Salesperson performance', 'Territory performance'],
        ],
        [
            'label' => 'Buying / Procurement',
            'description' => 'Supplier sourcing, purchase flow, receiving, and procurement analytics.',
            'features' => ['Suppliers', 'Material requests', 'Request for quotation', 'Supplier quotations', 'Purchase orders', 'Purchase receipts', 'Purchase analytics', 'Supplier material handoff'],
        ],
        [
            'label' => 'Inventory / Warehouse',
            'description' => 'Stock control, traceability, warehouse movement, and shipping preparation.',
            'features' => ['Items', 'Warehouses', 'Stock entries', 'Stock ledger', 'Stock reconciliation', 'Batch numbers', 'Serial numbers', 'Barcode records', 'Reorder levels', 'Putaway', 'Picking', 'Packing', 'Shipment', 'Stock balance reports', 'Traceability reports'],
        ],
        [
            'label' => 'Manufacturing',
            'description' => 'Production planning, work execution, material planning, and quality handoff.',
            'features' => ['BOM', 'Production plan', 'Work orders', 'Job cards', 'Material requirements planning', 'Production forecasting', 'Quality inspection handoff', 'Work order reports'],
        ],
        [
            'label' => 'Projects',
            'description' => 'Project delivery, tasks, timesheets, collaboration, and project reporting.',
            'features' => ['Projects', 'Project tasks', 'Timesheets', 'Project collaboration', 'Project summaries', 'Delayed task reports', 'Customer portal access', 'Project portal access'],
        ],
        [
            'label' => 'Support / Service',
            'description' => 'Issue handling, service commitments, warranty claims, and support portal work.',
            'features' => ['Issues/tickets', 'SLA rules', 'Warranty claims', 'First response tracking', 'Issue summaries', 'Customer support portal'],
        ],
        [
            'label' => 'Assets / Maintenance',
            'description' => 'Asset lifecycle, depreciation, maintenance schedules, and inspection records.',
            'features' => ['Asset records', 'Asset depreciation schedule', 'Fixed asset register', 'Maintenance schedules', 'Quality inspection', 'Maintenance reports'],
        ],
        [
            'label' => 'Operations',
            'description' => 'Background operations, sync controls, imports, alerts, and release readiness.',
            'features' => ['Scheduled jobs', 'Notifications', 'Background workers', 'Sync conflict dashboard', 'Import/export jobs', 'System alerts', 'Release checklist'],
        ],
        [
            'label' => 'Compliance / Localization',
            'description' => 'Regional tax, e-invoice, audit evidence, and regulatory exports.',
            'features' => ['Tax templates', 'VAT settings', 'E-invoice reports', 'Regional compliance reports', 'Audit evidence', 'Regulatory exports'],
        ],
        [
            'label' => 'Mobile / Android Stockroom',
            'description' => 'Mobile warehouse execution, scanner workflows, offline sync, and conflict review.',
            'features' => ['Mobile receiving', 'Barcode scanning', 'Putaway', 'Picking and packing', 'Delivery note handoff', 'Stock count', 'Offline sync queue', 'Conflict review', 'Scanner error recovery'],
        ],
    ];
}

function yovel_admin_platform_sections(): array
{
    return [
        ['label' => 'User accounts', 'description' => 'Create company users, activate accounts, and keep profile ownership inside Yovel East.'],
        ['label' => 'Roles', 'description' => 'Build company roles for department workspaces, management scopes, and reviewer access.'],
        ['label' => 'Permissions / RBAC', 'description' => 'Assign permissions by role so department users start with only their default workspace.'],
        ['label' => 'Cross-department access grants', 'description' => 'Grant selected reports or modules across departments without opening full Finance or Admin access.'],
        ['label' => 'Approval rules', 'description' => 'Define who can submit, review, approve, reject, and delegate operational requests.'],
        ['label' => 'Delegated authority', 'description' => 'Temporarily pass approval authority while preserving audit visibility.'],
        ['label' => 'Audit logs', 'description' => 'Review access changes, login events, setup changes, and sensitive platform activity.'],
        ['label' => 'Security and integrations', 'description' => 'Prepare API access, security settings, and company integration controls.'],
    ];
}

function yovel_admin_dashboard_metrics(array $company, array $admin): array
{
    $branchCount = bx_count(
        'project_company_branch',
        "company_key_hash = " . bx_db()->qstr((string) $company['company_key_hash']) . " AND branch_status <> 'DELETED'"
    );
    $erpGroupCount = count(yovel_admin_erp_groups());

    return [
        ['label' => 'Company Scope', 'value' => 'Yovel East', 'description' => 'Signed in to company administration only.'],
        ['label' => 'Branches', 'value' => (string) $branchCount, 'description' => 'Active company branch records.'],
        ['label' => 'Admin Account', 'value' => (string) $admin['admin_status'], 'description' => (string) $admin['admin_login']],
        ['label' => 'ERP Workspaces', 'value' => (string) $erpGroupCount, 'description' => 'Department groups ready for module setup.'],
    ];
}

$company = yovel_admin_company();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    bx_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login') {
        try {
            if ($company && yovel_admin_login($company, (string) ($_POST['login'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                bx_flash('Signed in to Yovel East Company Admin.', 'success');
            } else {
                bx_flash('Invalid Yovel East admin login or password.', 'error');
            }
        } catch (Throwable) {
            bx_flash('Company admin login could not be completed. Try again.', 'error');
        }
        yovel_admin_redirect();
    }

    if ($action === 'logout') {
        yovel_admin_logout();
        bx_flash('Signed out from Yovel East Company Admin.', 'success');
        yovel_admin_redirect();
    }
}

$flash = bx_take_flash();
$admin = yovel_admin_current($company);
$assets = yovel_admin_asset_entry();
$activeView = $admin ? yovel_admin_view() : 'dashboard';
$erpGroups = $admin ? yovel_admin_erp_groups() : [];
$platformSections = $admin ? yovel_admin_platform_sections() : [];
$dashboardMetrics = ($company && $admin) ? yovel_admin_dashboard_metrics($company, $admin) : [];
$viewTitle = $activeView === 'platform' ? 'Company Platform' : 'Yovel East Dashboard';
$viewDescription = $activeView === 'platform'
    ? 'Create and assign company users, roles, access rules, and audited authority boundaries.'
    : 'Review Yovel East company scope, platform readiness, and the ERP system workspaces.';
$nextThemeLabel = 'Toggle theme';
$pageTitle = 'Yovel East Company Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= bx_h($pageTitle) ?></title>
    <script>
        if (window.localStorage.getItem('builderx:yovel-east-admin:theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>
    <?php foreach ($assets['css'] as $css): ?>
        <link rel="stylesheet" href="<?= bx_h($assets['base'] . $css) ?>">
    <?php endforeach; ?>
    <style>
        @media (min-width: 1024px) {
            .yovel-mobile-nav {
                display: none !important;
            }
            .yovel-desktop-sidebar {
                display: flex !important;
            }
        }
        @media (max-width: 1023px) {
            .yovel-desktop-sidebar {
                display: none !important;
            }
        }
    </style>
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
                            <p class="truncate text-sm font-medium">Yovel East</p>
                            <p class="truncate text-xs text-muted-foreground">Company Admin Portal</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted"
                        aria-label="<?= bx_h($nextThemeLabel) ?>"
                        title="<?= bx_h($nextThemeLabel) ?>"
                        onclick="document.documentElement.classList.toggle('dark'); window.localStorage.setItem('builderx:yovel-east-admin:theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light')"
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
                    <p class="text-sm font-semibold">Yovel East Company Admin is unavailable.</p>
                    <p class="mt-1 text-sm text-muted-foreground">The active Yovel East company record was not found.</p>
                </section>
            <?php elseif ($admin): ?>
                <div class="flex min-h-0 flex-1 overflow-hidden rounded-lg border bg-background">
                    <aside class="yovel-desktop-sidebar hidden w-64 shrink-0 border-r bg-sidebar text-sidebar-foreground lg:flex lg:flex-col">
                        <div class="flex h-16 shrink-0 items-center gap-3 border-b px-4">
                            <div class="flex aspect-square size-9 items-center justify-center rounded-lg bg-primary text-sm font-semibold text-primary-foreground">YE</div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">Yovel East</p>
                                <p class="truncate text-xs text-sidebar-foreground/70">Company Admin Portal</p>
                            </div>
                        </div>
                        <nav class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-3">
                            <p class="px-2 pb-2 text-xs font-medium text-sidebar-foreground/60">Workspace</p>
                            <div class="grid gap-1">
                                <a class="flex h-9 items-center gap-2 rounded-md px-2 text-sm font-medium <?= $activeView === 'dashboard' ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'hover:bg-sidebar-accent/70' ?>" href="./?view=dashboard">
                                    <span aria-hidden="true">▦</span>
                                    <span>Dashboard</span>
                                </a>
                                <a class="flex h-9 items-center gap-2 rounded-md px-2 text-sm font-medium <?= $activeView === 'platform' ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'hover:bg-sidebar-accent/70' ?>" href="./?view=platform">
                                    <span aria-hidden="true">⌘</span>
                                    <span>Platform</span>
                                </a>
                            </div>
                            <div class="mt-5">
                                <p class="px-2 pb-2 text-xs font-medium text-sidebar-foreground/60">ERP System</p>
                                <div class="grid gap-1">
                                    <?php foreach ($erpGroups as $group): ?>
                                        <a class="flex min-h-8 items-center rounded-md px-2 py-1.5 text-sm hover:bg-sidebar-accent/70" href="./?view=dashboard#erp-<?= bx_h(yovel_admin_slug((string) $group['label'])) ?>">
                                            <?= bx_h((string) $group['label']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </nav>
                        <div class="shrink-0 border-t p-3">
                            <div class="flex items-center gap-3 rounded-md bg-sidebar-accent/60 p-2">
                                <div class="flex aspect-square size-8 items-center justify-center rounded-full bg-background text-xs font-semibold">YA</div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold"><?= bx_h((string) $admin['admin_name']) ?></p>
                                    <p class="truncate text-xs text-sidebar-foreground/70"><?= bx_h((string) $admin['admin_login']) ?></p>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <section class="flex min-w-0 flex-1 flex-col">
                        <header class="flex h-16 shrink-0 items-center justify-between gap-3 border-b px-4">
                            <div class="min-w-0">
                                <h1 class="truncate text-base font-semibold tracking-normal"><?= bx_h($viewTitle) ?></h1>
                                <p class="truncate text-xs text-muted-foreground">Company Admin &gt; <?= bx_h($activeView === 'platform' ? 'Platform' : 'Dashboard') ?></p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <a class="hidden h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted sm:inline-flex" href="https://ui.shadcn.com/" target="_blank" rel="noopener noreferrer">↗ shadcn/ui</a>
                                <button
                                    type="button"
                                    class="inline-flex size-9 items-center justify-center rounded-md border bg-background text-sm hover:bg-muted"
                                    aria-label="<?= bx_h($nextThemeLabel) ?>"
                                    title="<?= bx_h($nextThemeLabel) ?>"
                                    onclick="document.documentElement.classList.toggle('dark'); window.localStorage.setItem('builderx:yovel-east-admin:theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light')"
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
                                <div class="flex items-center gap-2">
                                    <a class="inline-flex h-9 flex-1 items-center justify-center rounded-md border px-3 text-sm font-medium <?= $activeView === 'dashboard' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=dashboard">Dashboard</a>
                                    <a class="inline-flex h-9 flex-1 items-center justify-center rounded-md border px-3 text-sm font-medium <?= $activeView === 'platform' ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted' ?>" href="./?view=platform">Platform</a>
                                </div>
                            </div>

                            <?php if ($activeView === 'platform'): ?>
                                <div class="grid gap-4">
                                    <div>
                                        <span class="inline-flex w-fit items-center rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">Platform</span>
                                        <h2 class="mt-3 text-2xl font-semibold tracking-normal">Company Platform</h2>
                                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground"><?= bx_h($viewDescription) ?></p>
                                    </div>

                                    <div class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                                        <section class="rounded-lg border bg-card">
                                            <div class="border-b px-5 py-4">
                                                <h3 class="text-base font-semibold tracking-normal">Admin Controls</h3>
                                                <p class="mt-1 text-sm text-muted-foreground">Company-scoped controls for creating and assigning users.</p>
                                            </div>
                                            <div class="grid gap-3 p-5 md:grid-cols-2">
                                                <?php foreach ($platformSections as $section): ?>
                                                    <div class="rounded-md bg-muted/40 p-3">
                                                        <p class="text-sm font-semibold"><?= bx_h((string) $section['label']) ?></p>
                                                        <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $section['description']) ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </section>

                                        <aside class="rounded-lg border bg-card">
                                            <div class="border-b px-5 py-4">
                                                <h3 class="text-base font-semibold tracking-normal">Permission Rule</h3>
                                                <p class="mt-1 text-sm text-muted-foreground">Access starts narrow and expands only by audited grant.</p>
                                            </div>
                                            <div class="grid gap-3 p-5">
                                                <div class="rounded-md bg-muted/40 p-3 text-sm font-medium">Admin sees everything.</div>
                                                <div class="rounded-md bg-muted/40 p-3 text-sm font-medium">Department users see only their department by default.</div>
                                                <div class="rounded-md bg-muted/40 p-3 text-sm font-medium">Admin can grant selected extra access across departments.</div>
                                                <div class="rounded-md bg-muted/40 p-3 text-sm font-medium">Every access change is audited.</div>
                                                <div class="pt-2 text-xs leading-5 text-muted-foreground">
                                                    HR can receive selected payroll summaries, project staffing, or employee cost reports without receiving full Finance access.
                                                </div>
                                            </div>
                                        </aside>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="grid gap-4">
                                    <div>
                                        <span class="inline-flex w-fit items-center rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">Dashboard</span>
                                        <h2 class="mt-3 text-2xl font-semibold tracking-normal">Yovel East Dashboard</h2>
                                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground"><?= bx_h($viewDescription) ?></p>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <?php foreach ($dashboardMetrics as $metric): ?>
                                            <section class="rounded-lg border bg-card p-4">
                                                <p class="text-xs font-medium text-muted-foreground"><?= bx_h((string) $metric['label']) ?></p>
                                                <p class="mt-2 text-2xl font-semibold tracking-normal"><?= bx_h((string) $metric['value']) ?></p>
                                                <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $metric['description']) ?></p>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>

                                    <section class="rounded-lg border bg-card">
                                        <div class="border-b px-5 py-4">
                                            <h3 class="text-base font-semibold tracking-normal">Platform Access Summary</h3>
                                            <p class="mt-1 text-sm text-muted-foreground">Yovel East Admin can see every ERP workspace. Department users will start limited to their department until Platform grants more access.</p>
                                        </div>
                                        <div class="grid gap-3 p-5 md:grid-cols-4">
                                            <div class="rounded-md bg-muted/40 p-3">
                                                <p class="text-sm font-semibold">Admin</p>
                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Full company access.</p>
                                            </div>
                                            <div class="rounded-md bg-muted/40 p-3">
                                                <p class="text-sm font-semibold">Department Users</p>
                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Department-only by default.</p>
                                            </div>
                                            <div class="rounded-md bg-muted/40 p-3">
                                                <p class="text-sm font-semibold">Extra Access</p>
                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Selected modules by grant.</p>
                                            </div>
                                            <div class="rounded-md bg-muted/40 p-3">
                                                <p class="text-sm font-semibold">Audit</p>
                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Every access change is audited.</p>
                                            </div>
                                        </div>
                                    </section>

                                    <div>
                                        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                                            <div>
                                                <h3 class="text-lg font-semibold tracking-normal">ERP System Features</h3>
                                                <p class="mt-1 text-sm text-muted-foreground">Department workspaces for Yovel East operations.</p>
                                            </div>
                                            <span class="inline-flex rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($erpGroups) ?> ERP groups</span>
                                        </div>
                                        <div class="grid gap-4 xl:grid-cols-2">
                                            <?php foreach ($erpGroups as $group): ?>
                                                <section id="erp-<?= bx_h(yovel_admin_slug((string) $group['label'])) ?>" class="rounded-lg border bg-card">
                                                    <div class="border-b px-5 py-4">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <h4 class="text-base font-semibold tracking-normal"><?= bx_h((string) $group['label']) ?></h4>
                                                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($group['features']) ?> features</span>
                                                        </div>
                                                        <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) $group['description']) ?></p>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2 p-5">
                                                        <?php foreach ($group['features'] as $feature): ?>
                                                            <span class="rounded-md bg-muted/50 px-2.5 py-1.5 text-xs font-medium text-muted-foreground"><?= bx_h((string) $feature) ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </section>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <footer class="sticky bottom-0 z-10 flex shrink-0 flex-col gap-1 border-t bg-background px-4 py-3 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                            <span>Yovel East Company Admin</span>
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
                                <h1 class="mt-3 text-2xl font-semibold tracking-normal">Yovel East</h1>
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">Manage company administration, branches, projects, ERP dashboards, and company-scoped operations from one workspace.</p>
                            </div>
                            <div class="grid gap-3 p-5 pt-0 md:grid-cols-3">
                                <div class="rounded-md border bg-background p-4">
                                    <p class="text-sm font-semibold">Protected Company Portal</p>
                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Yovel East admin access is required.</p>
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
                                <h2 class="text-base font-semibold tracking-normal">Yovel East Admin Login</h2>
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
</body>
</html>
