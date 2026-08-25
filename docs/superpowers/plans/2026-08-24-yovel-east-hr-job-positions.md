# Yovel East HR Job Positions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Build the HR Department Job positions section so Yovel East can create, edit, deactivate, restore, and assign ERPNext-style designation records to employees.

**Architecture:** Extend the existing single-file company admin HR route in `company/admin/index.php`. Reuse the existing `project_company_hr_job_position` table, ADODB transaction helper, confirmation modal, flash redirects, and server rehydration pattern used by Employee profiles and Departments.

**Tech Stack:** PHP 8, ADODB, MariaDB/MySQL, BuilderX shadcn-like utility classes, native POST forms.

## Global Constraints

- Keep the work scoped to HR Department development only.
- Use the existing `?view=hr&section=job-positions` route.
- Use parameterized ADODB queries, transaction boundaries, audit logging, and direct read-back verification.
- Preserve current Employee profiles and Departments behavior.
- Keep the UI dense, administrative, and consistent with the existing company admin portal.
- ERPNext reference: model this section like the Designation master: required name/code plus description, status, and use as a link from employee records.

---

### Task 1: Job Position Persistence

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `yovel_admin_hr_schema()`, `yovel_admin_code()`, `yovel_admin_status()`, `yovel_admin_db_execute()`, `bx_audit()`.
- Produces: `yovel_admin_save_hr_job_position(array $company, array $admin): string` and `yovel_admin_set_hr_job_position_status(array $company, array $admin): string`.

- [x] Add full job-position fields to `yovel_admin_hr_data()` so edit forms can rehydrate `job_position_description`, timestamps, and audit-managed values.
- [x] Implement `yovel_admin_save_hr_job_position()` as an upsert keyed by stable `job_position_key` or company-scoped `job_position_code`.
- [x] Validate code, name, description length, status, company ownership, and duplicate codes before commit.
- [x] Read the saved row back and compare every persisted field before committing.
- [x] Implement `yovel_admin_set_hr_job_position_status()` with `ACTIVE`, `INACTIVE`, and `DELETED`.

### Task 2: POST Routing and Edit State

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: Task 1 functions.
- Produces: working `save_hr_job_position` and `set_hr_job_position_status` actions redirecting to `view=hr&section=job-positions`.

- [x] Add HR POST dispatch cases for save and status actions.
- [x] Route success and failure redirects back to `job-positions`.
- [x] Add `$editHrJobPosition` lookup from `$hrData['jobPositions']`.

### Task 3: Job Positions UI

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `hrData.jobPositions`, `$editHrJobPosition`, existing confirmation modal.
- Produces: a complete Job positions section before queued HR placeholders.

- [x] Add summary metrics for total, active, and assigned job positions.
- [x] Render a responsive table with position name, code, assigned employees, status, description, and actions.
- [x] Render an add/edit form with code, status, name, and description.
- [x] Use `data-confirm-submit`, CSRF, native validation, and current form styling.

### Task 4: Phase Manager Checklist

**Files:**
- Database rows only

**Interfaces:**
- Consumes: Phase Manager task/checklist tables.
- Produces: checked checklist rows under the HR job-position task when available.

- [x] Find the existing HR Department phase/task rows.
- [x] Add or mark checklist rows for schema/data, transaction workflow, UI, employee selector integration, and verification.

### Task 5: Verification

**Files:**
- Verify: `company/admin/index.php`
- Verify: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: completed Tasks 1-4.
- Produces: evidence before completion.

- [x] Run PHP lint on changed PHP files.
- [x] Request `?view=hr&section=job-positions` and confirm no SQL/PHP warnings.
- [x] Use authenticated POSTs to create, update, and delete a job position.
- [x] Read back the database row after each mutation.
- [x] Confirm the updated job position appears in employee selectors.
