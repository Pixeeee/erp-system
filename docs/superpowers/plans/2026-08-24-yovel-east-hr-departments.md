# Yovel East HR Departments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the next HR Department dropdown item, Departments, as a real HR workspace section.

**Architecture:** Reuse the existing `project_company_department` and `project_company_department_master` data model instead of creating an HR-only department table. Add HR POST actions that call the existing department transaction functions but redirect back to `?view=hr&section=departments`. Render an HR Departments list/form under the HR view while preserving Platform Departments behavior.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, server-rendered HTML, existing shadcn/Tailwind utility classes, native POST forms, existing confirmation dialog JavaScript.

## Global Constraints

- Build only the HR Department area in this cycle.
- Other ERP departments remain dashboard feature anchors.
- Reuse existing `project_company_department` records for HR Departments.
- Persist writes with both `company_key` and `company_key_hash` through the existing department functions.
- Use parameterized ADODB queries, transactions, audit logging, and read-back verification for every persisted write.
- Do not build full payroll processing in this cycle.
- Do not replace the current company-admin PHP route with a frontend SPA.

---

### Task 1: HR Departments Data And POST Actions

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `yovel_admin_hr_data(array $company): array`, `yovel_admin_save_department(array $company, array $admin): string`, `yovel_admin_set_department_status(array $company, array $admin): string`
- Produces: HR data key `departmentMasters`, POST actions `save_hr_department` and `set_hr_department_status`

- [x] Add standard department masters to `yovel_admin_hr_data()` so HR Departments can create from ERP defaults.
- [x] Add `save_hr_department` in the HR POST dispatcher, calling `yovel_admin_save_department($company, $postAdmin)`.
- [x] Add `set_hr_department_status` in the HR POST dispatcher, calling `yovel_admin_set_department_status($company, $postAdmin)`.
- [x] Redirect both HR department actions back to `view=hr&section=departments`.
- [x] Load `$editHrDepartment` from `$hrData['departments']` when the HR section is departments.

### Task 2: HR Departments UI

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `$activeHrSection`, `$hrData['departments']`, `$hrData['branches']`, `$hrData['departmentMasters']`, `$editHrDepartment`
- Produces: Authenticated `?view=hr&section=departments` screen with department list, create/edit form, ERP default picker, and status actions.

- [x] Render HR Departments metrics: total departments, active departments, and branch count.
- [x] Render a departments table with department, code, branch, source/type, status, and edit action.
- [x] Render status action forms for `ACTIVE`, `INACTIVE`, `ARCHIVED`, and `DELETED`.
- [x] Render create/edit form with CSRF, `data-confirm-submit`, branch, source, ERP default, code, name, type, status, and description fields.
- [x] Keep fields and names compatible with the existing `yovel_admin_save_department()` function.
- [x] Keep future HR sections queued while Employee profiles and Departments are active real sections.

### Task 3: Phase Manager And Verification

**Files:**
- Modify through script: Phase Manager database rows only
- Verify: `company/admin/index.php`, `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: Phase Manager task code `HR-DEPARTMENTS`
- Produces: Departments checklist rows and read-back verification

- [x] Add checklist rows to the `HR-DEPARTMENTS` Phase Manager task for routing, UI, create/update/status, and verification.
- [x] Run PHP lint for both company admin entrypoints.
- [x] Run authenticated HTTP check that `?view=hr&section=departments` renders the Departments section.
- [x] Create, update, and soft-delete a temporary department through the real HR POST path using a cookie jar and CSRF token.
- [x] Verify the committed temporary department values with direct ADODB read-back.
- [x] Confirm the HR Departments route rehydrates saved data after redirect.
