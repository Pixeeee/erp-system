# Yovel East Company Admin Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the authenticated Yovel East company admin placeholder with a BuilderX-style company dashboard shell, simplified header, company-scoped sidebar, Platform view, and ERP feature map.

**Architecture:** Keep the implementation in `company/yovel-east/admin/index.php`. Add small metadata functions for navigation, ERP groups, dashboard metrics, and safe view routing, then render the authenticated shell while preserving the existing login/logout and company-admin session behavior.

**Tech Stack:** PHP, ADODB reads, existing BuilderX foundation helpers, existing Vite-built shadcn/ui CSS assets, curl-based route validation.

## Global Constraints

- Route remains `/erpsystem/company/yovel-east/admin/`.
- Existing login behavior remains unchanged.
- Do not expose `company_key` or hash fields in visible UI.
- Authenticated header keeps only `shadcn/ui`, light/dark mode, and sign-out access.
- Authenticated header removes Sharingan, User Portal, Foundation, UI-UX Flow, and Phase Manager.
- Sidebar keeps Dashboard, Platform, and ERP module groups.
- Sidebar removes Phase Builder, Administrator-only Company Management, Settings, other companies, and project tree entries.
- This step does not implement user, role, permission, or ERP CRUD workflows.
- No new database writes are required for this shell step.

---

### Task 1: Add Company Dashboard Metadata

**Files:**
- Modify: `company/yovel-east/admin/index.php`

**Interfaces:**
- Produces: `yovel_admin_view(): string`
- Produces: `yovel_admin_erp_groups(): array`
- Produces: `yovel_admin_platform_sections(): array`
- Produces: `yovel_admin_dashboard_metrics(array $company, array $admin): array`

- [ ] **Step 1: Add safe view routing**

Add this function near the existing helper functions:

```php
function yovel_admin_view(): string
{
    $view = strtolower(trim((string) ($_GET['view'] ?? 'dashboard')));
    return in_array($view, ['dashboard', 'platform'], true) ? $view : 'dashboard';
}
```

- [ ] **Step 2: Add ERP group metadata**

Add a function returning the department groups and feature labels from the approved design. The returned array must include keys `label`, `description`, and `features`.

- [ ] **Step 3: Add Platform section metadata**

Add a function returning Platform sections for user accounts, roles, permissions/RBAC, cross-department access grants, approval rules, delegated authority, audit logs, and security/integrations.

- [ ] **Step 4: Add dashboard metrics**

Add a function that reads branch count from `project_company_branch`, counts ERP groups from `yovel_admin_erp_groups()`, and returns display metrics without exposing company keys.

- [ ] **Step 5: Run PHP lint**

Run: `php -l company/yovel-east/admin/index.php`

Expected: `No syntax errors detected in company/yovel-east/admin/index.php`

### Task 2: Render Authenticated BuilderX-Style Shell

**Files:**
- Modify: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: `yovel_admin_view(): string`
- Consumes: `yovel_admin_erp_groups(): array`
- Consumes: `yovel_admin_platform_sections(): array`
- Consumes: `yovel_admin_dashboard_metrics(array $company, array $admin): array`

- [ ] **Step 1: Prepare authenticated view variables**

After `$admin = yovel_admin_current($company);`, set `$activeView`, `$erpGroups`, `$platformSections`, and `$dashboardMetrics` only when an admin is authenticated.

- [ ] **Step 2: Replace authenticated placeholder markup**

Replace the current authenticated `<div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">...</div>` with an app shell:

```php
<div class="flex h-[calc(100svh-2rem)] min-h-[720px] overflow-hidden rounded-lg border bg-background">
    <aside class="hidden w-64 shrink-0 border-r bg-sidebar text-sidebar-foreground lg:flex lg:flex-col">
        <!-- Yovel East brand, Dashboard, Platform, ERP groups, account block -->
    </aside>
    <section class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 shrink-0 items-center justify-between border-b px-4">
            <!-- title, breadcrumb, shadcn/ui, theme, sign out -->
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 sm:p-6">
            <!-- dashboard or platform content -->
        </div>
        <footer class="sticky bottom-0 z-10 flex shrink-0 flex-col gap-1 border-t bg-background px-4 py-3 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <!-- footer labels -->
        </footer>
    </section>
</div>
```

- [ ] **Step 3: Add mobile navigation row**

Inside the main content region, add a mobile-only row with Dashboard and Platform links so small screens can navigate without the desktop sidebar.

- [ ] **Step 4: Preserve logout behavior**

Keep logout as a POST form with the existing CSRF token and `action=logout`.

- [ ] **Step 5: Run PHP lint**

Run: `php -l company/yovel-east/admin/index.php`

Expected: `No syntax errors detected in company/yovel-east/admin/index.php`

### Task 3: Render Dashboard And Platform Views

**Files:**
- Modify: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: `$activeView`
- Consumes: `$dashboardMetrics`
- Consumes: `$erpGroups`
- Consumes: `$platformSections`

- [ ] **Step 1: Render Dashboard overview**

For `dashboard`, render a badge, `Yovel East Dashboard` title, compact metric cards, and ERP department panels.

- [ ] **Step 2: Render ERP feature map**

For each ERP group, render the group label, description, feature count, and feature labels as compact items. Items are navigation-ready placeholders and must not perform writes.

- [ ] **Step 3: Render Platform control placeholders**

For `platform`, render user/role/permission/audit/security sections and the permission rule copy:

```text
Admin sees everything.
Department users see only their department by default.
Admin can grant selected extra access across departments.
Every access change is audited.
```

- [ ] **Step 4: Verify removed labels**

Run:

```bash
tmp_cookie="$(mktemp)"
tmp_login="$(mktemp)"
curl -sS -c "$tmp_cookie" http://localhost/erpsystem/company/yovel-east/admin/ -o "$tmp_login"
csrf="$(php -r '$html=file_get_contents($argv[1]); preg_match("/name=\"csrf\" value=\"([^\"]+)\"/", $html, $m); echo $m[1] ?? "";' "$tmp_login")"
curl -sS -b "$tmp_cookie" -c "$tmp_cookie" -L -d "csrf=$csrf&action=login&login=admin&password=admin12345" http://localhost/erpsystem/company/yovel-east/admin/ -o /tmp/yovel-auth-dashboard.html
curl -sS -b "$tmp_cookie" "http://localhost/erpsystem/company/yovel-east/admin/?view=platform" -o /tmp/yovel-auth-platform.html
```

Expected: dashboard and platform HTML files are created for authenticated checks.

- [ ] **Step 5: Run focused content checks**

Run:

```bash
rg -n "Yovel East Dashboard|Dashboard|Platform|HR Department|Accounting / Finance|Mobile / Android Stockroom" /tmp/yovel-auth-dashboard.html
rg -n "User accounts|Roles|Permissions / RBAC|Audit logs|Every access change is audited" /tmp/yovel-auth-platform.html
! rg -n "Sharingan|User Portal|Foundation|UI-UX Flow|Phase Manager|Phase Builder|Company Management|Settings|We Will RIce" /tmp/yovel-auth-dashboard.html
```

Expected: required Yovel East labels are present, removed Administrator labels are absent.

### Task 4: Final Route Verification

**Files:**
- Test: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: completed authenticated shell

- [ ] **Step 1: Check anonymous route**

Run:

```bash
curl -sS -L -o /tmp/yovel-anon.html -w '%{http_code}' http://localhost/erpsystem/company/yovel-east/admin/
```

Expected: `200`

- [ ] **Step 2: Check authenticated dashboard route**

Run the login curl flow from Task 3 and verify `/tmp/yovel-auth-dashboard.html` contains `Yovel East Dashboard`.

- [ ] **Step 3: Check authenticated Platform route**

Run the login curl flow from Task 3 and verify `/tmp/yovel-auth-platform.html` contains `Company Platform`.

- [ ] **Step 4: Check logout**

Post `action=logout` with the authenticated cookie and CSRF token, then verify the response contains `Yovel East Admin Login`.

- [ ] **Step 5: Review git diff**

Run: `git diff -- company/yovel-east/admin/index.php docs/superpowers/plans/2026-08-24-yovel-east-company-admin-dashboard.md`

Expected: diff contains only the plan and Yovel East admin dashboard shell changes.
