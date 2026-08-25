# Yovel East Sidebar Toggle And Departments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a collapsible company-admin sidebar and copy Administrator Company Departments into the Yovel East Platform workspace.

**Architecture:** Extend the existing shared PHP company-admin route because `company/yovel-east/admin/index.php` delegates there. Reuse existing `project_company_department_master` and `project_company_department` tables from the Administrator Company Management feature, scoped by the active company hash. Keep POST saves inside the existing CSRF, confirmation, transaction, audit, and read-back pattern.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, shadcn/Tailwind utility classes, native forms, browser localStorage.

## Global Constraints

- Do not touch unrelated dirty files.
- Use fixed SQL identifiers and parameterized ADODB values.
- Every department write must verify active company admin, active company scope, branch ownership, transaction commit, audit, and direct read-back.
- Sidebar collapsed state is a browser preference only and must not change server routing.
- Department UI must be Yovel East scoped with no company selector.

---

### Task 1: Sidebar Collapse

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `$companyThemeKey`, existing sidebar/header shell.
- Produces: `data-sidebar-collapsed` body state, header toggle button, and localStorage key `builderx:company-admin:<slug>:sidebar`.

- [ ] Add CSS rules for expanded/collapsed sidebar width, hiding labels/details while collapsed, and showing an icon-only rail.
- [ ] Add a header button with the sidebar-panel icon from the user screenshot.
- [ ] Add JavaScript to load, toggle, and persist collapsed state in localStorage.
- [ ] Verify desktop collapse/expand with browser automation.

### Task 2: Company Departments Data And Saves

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `project_company_department_master`, `project_company_department`, `project_company_branch`, `bx_db()`, `bx_audit()`, `bx_uuid()`.
- Produces: `yovel_admin_department_data(array $company): array`, `yovel_admin_save_department(array $company, array $admin): string`, `yovel_admin_set_department_status(array $company, array $admin): string`.

- [ ] Load standard department masters, company branches, and Yovel East departments with branch names.
- [ ] Implement department create/update with Administrator-compatible fields: branch, code, name, status, type, source, default template, description.
- [ ] Implement status updates for ACTIVE, INACTIVE, ARCHIVED, and DELETED.
- [ ] Validate UUIDs, codes, lengths, branch scope, template scope, duplicates per branch, and allowed statuses/sources.
- [ ] Audit and read back before commit.

### Task 3: Platform Departments UI

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `$platformNav`, `$platformData`, `$activePlatformSection`, `$editDepartment`.
- Produces: `Departments` Platform section with list, create/edit form, standard template picker, and status actions.

- [ ] Add `Departments` to Platform navigation.
- [ ] Render the Departments table scoped to Yovel East.
- [ ] Render create/edit department form with confirmation via the existing `data-confirm-submit` guard.
- [ ] Preserve the existing footer, header, and mobile layout behavior.

### Task 4: Verification

**Files:**
- Verify: `company/admin/index.php`, `company/yovel-east/admin/index.php`

- [ ] Run PHP lint.
- [ ] Log in through localhost and verify `?view=platform&section=departments`.
- [ ] Create/update a temporary DELETED department and verify database read-back.
- [ ] Screenshot desktop and mobile layouts, including collapsed sidebar.
