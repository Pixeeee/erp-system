# Project Company Departments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Add Company Management > Departments with fixed standard departments already available and branch-specific departments created from those standards or custom entries.

**Architecture:** Keep fixed departments in `project_company_department_master` and branch assignments in `project_company_department`. Standard departments are seeded by the foundation setup and always available; each branch assignment is still its own row so Davao HR and Sariaya HR can start from the same standard department and be customized independently. Persistence follows the existing Company Branches and Company Projects transaction, audit, and read-back pattern.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, React, TypeScript, shadcn/ui, Tailwind CSS.

## Global Constraints

- Add the new menu under Company Management only.
- Store saved departments in `project_company_department`.
- Department records must require a selected company and branch.
- Department forms must live inside a modal only.
- Standard ERP departments are fixed master records seeded by the foundation setup.
- Standard ERP departments are not manually typed every time.
- Branch department rows may come from a standard department or from a custom department.
- A custom department can be created for any company branch.
- The main view uses the existing 8/4 left table and right summary panel.
- Panel headers need bottom borders; only panel bodies scroll.
- Do not show company keys or branch keys in the UI.
- Do not add nested bordered cards or bordered boxes inside panels.
- Actions headers and icon groups must align left with the current Company Management tables.

---

### Task 1: Database And Backend Persistence

**Files:**
- Modify: `app/foundation.php`
- Modify: `administrator/index.php`

**Interfaces:**
- Produces table: `project_company_department_master`
- Produces table: `project_company_department`
- Produces POST actions: `save_company_department`, `set_company_department_status`
- Produces payload key: `companyDepartmentMasters`
- Produces payload key: `companyDepartments`

- [x] **Step 1: Add idempotent schema and seeded standards**

Create `project_company_department_master` with fixed standard department definitions, then create `project_company_department` with `department_key`, `company_key`, `company_key_hash`, `branch_key`, `department_code`, `department_name`, `department_status`, `department_type`, `department_source`, `default_department_key`, `department_description`, `is_default`, timestamps, unique branch/code index, and lookup indexes.

- [x] **Step 2: Add save action**

Validate admin auth through the existing action gate, CSRF, optional department UUID, Firestore company key, branch UUID, selected company existence, selected branch ownership, department code/name/type/source/status, and duplicate code within the selected branch. Upsert inside an ADODB transaction, audit create/update, read back every persisted field, then commit.

- [x] **Step 3: Add status action**

Validate `department_key` and target status, update inside an ADODB transaction, audit status/delete changes, read back the saved status, then commit.

- [x] **Step 4: Add payload rows and metric**

Load `companyDepartmentMasters` from `project_company_department_master`, load `companyDepartments` by joining `project_company_department` to `project_company` and `project_company_branch`, and add a `Company Departments` metric.

### Task 2: Company Management Department UI

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes payload: `data.companies`, `data.companyBranches`, `data.companyDepartmentMasters`, `data.companyDepartments`
- Submits actions: `save_company_department`, `set_company_department_status`

- [x] **Step 1: Add navigation and routing labels**

Add `company-departments` to the admin view allowlist, the Company Management submenu, page title mapping, build target mapping, and render tree.

- [x] **Step 2: Add fixed ERP department standards**

Read fixed department options from the backend payload. The standard set includes Administration, Finance, Accounting, Operations, Human Resources, Procurement, Sales / Client Services, Project Management, Compliance / Audit, IT / System Support, Documents / Records, Credit / Loan Review, Collections, and Field Operations.

- [x] **Step 3: Add Departments view**

Create `CompanyDepartmentCrudView` with the same two-panel 8/4 shell as Companies, Branches, and Projects. The left panel shows departments, source/type, status, and left-aligned icon actions. The right panel shows summary only.

- [x] **Step 4: Add modal form**

Create/edit department records in a dialog. Include mode selection for using an ERP department or creating a custom department, required company and branch selectors, code/name/type/status/description fields, and hidden template metadata. Filter branch options by selected company.

- [x] **Step 5: Add status icon actions**

Add activate, deactivate, archive, restore, and delete icon buttons that post to `set_company_department_status` after confirmation.

### Task 3: Verification

**Files:**
- Verify: `app/foundation.php`
- Verify: `administrator/index.php`
- Verify: `frontend/src/App.tsx`

- [x] **Step 1: PHP syntax**

Run `php -l app/foundation.php` and `php -l administrator/index.php`.

- [x] **Step 2: Frontend build**

Run `cd frontend && npm run build`.

- [x] **Step 3: Route and schema checks**

Load `http://localhost/erpsystem/administrator/?tab=company-departments` and confirm the route renders without fatal/PHP/SQL errors. Read `information_schema` for `project_company_department` columns and indexes.
