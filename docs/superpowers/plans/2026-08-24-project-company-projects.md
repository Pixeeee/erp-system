# Project Company Projects Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Company Management > Projects screen that stores projects under selected project companies and project company branches in `project_company_project`.

**Architecture:** Keep the project-layer hierarchy explicit as `project_company` -> `project_company_branch` -> `project_company_project`. Store the selected company document ID and hash on each project for Firestore-aligned identity and indexed validation, while storing `branch_key` as the branch relationship. The UI follows the existing Company Management two-panel layout with modal-only CRUD.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, React, TypeScript, shadcn/ui, Tailwind CSS.

## Global Constraints

- Do not change the old global `builder_project` CRUD screen.
- Store new project records in `project_company_project`.
- Project forms must live inside the modal only.
- The main view uses 8/4 columns: left table panel, right summary panel.
- Panel headers need bottom borders; only panel bodies scroll.
- Do not add nested bordered cards or cards inside cards.
- Actions headers and icon groups must align right.
- The server must validate that the selected Branch belongs to the selected Company.

---

### Task 1: Database And Backend Persistence

**Files:**
- Modify: `app/foundation.php`
- Modify: `administrator/index.php`

**Interfaces:**
- Produces table: `project_company_project`
- Produces POST actions: `save_company_project`, `set_company_project_status`
- Produces payload key: `companyProjects`

- [x] **Step 1: Add idempotent schema**

Create `project_company_project` with `project_key`, `company_key`, `company_key_hash`, `branch_key`, project identity fields, status, timestamps, a unique `(company_key_hash, project_code)` key, and indexes for company, branch, status, and name.

- [x] **Step 2: Add save action**

Validate admin auth, CSRF, project UUID, Firestore company key, selected company existence, selected branch existence under that company, project code/name/status, and duplicate project code within the selected company. Upsert inside an ADODB transaction, audit create/update, read back every persisted field, then commit.

- [x] **Step 3: Add status action**

Validate `project_key` and status, update inside an ADODB transaction, audit status/delete changes, read back the saved status, then commit.

- [x] **Step 4: Add payload rows**

Load `companyProjects` by joining `project_company_project` to `project_company` and `project_company_branch`, and add a company project metric.

### Task 2: Company Management Project UI

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes payload: `data.companies`, `data.companyBranches`, `data.companyProjects`
- Submits actions: `save_company_project`, `set_company_project_status`

- [x] **Step 1: Add navigation**

Add `company-projects` to the admin view allowlist and as a Projects submenu item under Company Management.

- [x] **Step 2: Add Projects view**

Create `CompanyProjectCrudView` with the same two-panel 8/4 shell as Companies and Branches. Left panel shows project rows with right-aligned icon actions. Right panel is summary-only.

- [x] **Step 3: Add modal form**

Create/edit project records in a dialog. Include required company and branch selectors, project code, project name, status, and description. Filter branch options by selected company in React before submission.

- [x] **Step 4: Add status icon actions**

Add activate, deactivate, archive, restore, and delete icon buttons that post to `set_company_project_status` after confirmation.

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

Load `http://localhost/erpsystem/administrator/?tab=company-projects` and confirm the route renders without fatal/PHP/SQL errors. Read `information_schema` for `project_company_project` columns and indexes.
