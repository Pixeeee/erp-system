# Yovel East HR Employee Profiles Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the Yovel East HR Department dropdown into a real HR workspace route and implement the Employee profiles section first.

**Architecture:** Extend `company/admin/index.php`, because `company/yovel-east/admin/index.php` delegates to it. Add an `hr` view, HR section routing metadata, idempotent HR schema setup, company-scoped employee persistence, and a server-rendered Employee profiles list/form. Keep all writes in ADODB transactions with audit and direct read-back.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, server-rendered HTML, existing shadcn/Tailwind utility classes, native POST forms, existing confirmation dialog JavaScript.

## Global Constraints

- Build only the HR Department area in this cycle.
- Other ERP departments remain dashboard feature anchors.
- First completed HR section is Employee profiles.
- Reuse existing `project_company_department` records for HR Departments.
- Persist HR tables with both `company_key` and `company_key_hash`.
- Use parameterized ADODB queries, transactions, audit logging, and read-back verification for every persisted write.
- Do not build full payroll processing in this cycle.
- Do not replace the current company-admin PHP route with a frontend SPA.

---

### Task 1: HR Routing And Sidebar Links

**Files:**
- Modify: `company/admin/index.php`
- Verify: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: `yovel_admin_view(): string`, `yovel_admin_erp_groups(): array`, `yovel_admin_feature_anchor(array $group, string $feature): string`
- Produces: `yovel_admin_hr_sections(): array`, `yovel_admin_hr_section(): string`, `yovel_admin_hr_feature_href(array $group, string $feature): string`

- [x] Add `hr` to the allowed view list in `yovel_admin_view()`.
- [x] Add `yovel_admin_hr_sections()` returning section metadata for all 12 HR dropdown items.
- [x] Add `yovel_admin_hr_section()` that reads `$_GET['section']` and falls back to `employee-profiles`.
- [x] Add `yovel_admin_hr_feature_href()` that returns `./?view=hr&section=<slug>` only for HR Department features and keeps non-HR links on dashboard anchors.
- [x] Replace sidebar HR feature href rendering with `yovel_admin_hr_feature_href()`.
- [x] Update mobile view selectors so HR is reachable at `?view=hr`.
- [x] Run `php -l company/admin/index.php` and `php -l company/yovel-east/admin/index.php`.

### Task 2: HR Schema And Data Loading

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `bx_db(): ADOConnection`, `BUILDERX_DB_NAME`, company rows with `company_key` and `company_key_hash`
- Produces: `yovel_admin_hr_schema(): void`, `yovel_admin_hr_data(array $company): array`

- [x] Add idempotent `CREATE TABLE IF NOT EXISTS` statements for `project_company_hr_job_position`, `project_company_hr_team`, and `project_company_hr_employee`.
- [x] Add explicit indexes for company hash, status, branch, department, job position, team, and reporting manager lookups.
- [x] Add `yovel_admin_hr_data()` that loads employees joined to branch, department, job position, team, and manager display names.
- [x] Load branches and existing company departments for Employee profile selectors.
- [x] Load job positions and teams even when they are empty so selectors render deterministically.
- [x] Run a schema check through PHP that confirms the three HR tables exist.

### Task 3: Employee Profile Persistence

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `yovel_admin_hr_schema()`, `yovel_admin_hr_data()`, `yovel_admin_valid_keys()`, `yovel_admin_existing_key()`, `bx_uuid()`, `bx_audit()`
- Produces: `yovel_admin_save_hr_employee(array $company, array $admin): string`, `yovel_admin_set_hr_employee_status(array $company, array $admin): string`

- [x] Add server validation for employee code, names, status, emails, dates, branch ownership, department ownership, job position ownership, team ownership, and reporting manager ownership.
- [x] Implement employee create/update as a complete upsert path that preserves `employee_key` when updating by key or duplicate employee code.
- [x] Check every ADODB write for `false` and throw a safe exception with the captured database error.
- [x] Read back the employee row and compare every submitted persisted field before commit.
- [x] Audit create/update/status changes inside the transaction.
- [x] Wire POST actions `save_hr_employee` and `set_hr_employee_status` to redirect back to `view=hr&section=employee-profiles`.

### Task 4: Employee Profiles UI

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `$activeView`, `$activeHrSection`, `$hrSections`, `$hrData`, `$editHrEmployee`
- Produces: Authenticated `?view=hr&section=employee-profiles` screen with employee list, summary metrics, create/edit form, and status actions.

- [x] Add HR page rendering branch beside the existing Platform and Dashboard branches.
- [x] Render section navigation for the 12 HR sections, with Employee profiles active and future sections shown as quiet placeholder panels.
- [x] Render Employee profiles summary metrics: total employees, active employees, and incomplete profiles.
- [x] Render employee table with code, name, department, job position, branch, status, and Edit.
- [x] Render create/edit form with CSRF, `data-confirm-submit`, native validation, and fields from the design spec.
- [x] Add status action buttons for `ACTIVE`, `INACTIVE`, `ON_LEAVE`, `SEPARATED`, and `DELETED`.
- [x] Keep confirmation modal behavior shared with existing forms.

### Task 5: Phase Manager Tracking And Verification

**Files:**
- Modify through script: Phase Manager database rows only
- Verify: `company/admin/index.php`, `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: `builder_phase`, `builder_phase_task`, `builder_phase_task_checklist`
- Produces: Phase Manager phase `Yovel East HR Department` and first task `Employee profiles`

- [x] Create or reuse a Phase Manager phase for `Yovel East HR Department`.
- [x] Create or reuse ordered Phase Manager tasks for the 12 HR dropdown items.
- [x] Add checklist rows to the Employee profiles task for schema, routing, UI, persistence, and verification.
- [x] Run PHP lint for both company admin entrypoints.
- [x] Run authenticated HTTP checks for the HR route and sidebar HR links.
- [x] Create, update, and soft-delete a temporary employee through the real POST path using a cookie jar and CSRF token.
- [x] Verify the committed temporary employee values with direct ADODB read-back.
- [x] Confirm the HR route rehydrates saved data after redirect.
