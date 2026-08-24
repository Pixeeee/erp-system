# Project Company Branches Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Company Management > Branches screen that stores branches under existing project companies in `project_company_branch`.

**Architecture:** Keep `project_company` as the company source of truth and add a project-layer branch table keyed by `branch_key`. Store `company_key` plus `company_key_hash` so branch records can connect to Firestore-style company document IDs without exposing keys in the UI. Add modal-only create/edit forms and a summary-only right panel matching the existing Company Management layout.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, React, TypeScript, shadcn/ui, Tailwind CSS.

## Global Constraints

- Do not change the old global `builder_branch` CRUD screen.
- Store new branch records in `project_company_branch`.
- Branch forms must live inside the modal only.
- The main view uses 8/4 columns: left table panel, right summary panel.
- Panel headers need bottom borders; only panel bodies scroll.
- Do not add nested bordered cards or cards inside cards.
- Actions headers and icon groups must align right.

---

### Task 1: Database And Backend Persistence

**Files:**
- Modify: `app/foundation.php`
- Modify: `administrator/index.php`

**Interfaces:**
- Produces table: `project_company_branch`
- Produces POST actions: `save_company_branch`, `set_company_branch_status`
- Produces payload key: `companyBranches`

- [x] **Step 1: Add idempotent schema**

Create `project_company_branch` with `branch_key`, `company_key`, `company_key_hash`, branch identity fields, status, timestamps, a unique `(company_key_hash, branch_code)` key, and indexes for company, status, and name.

- [x] **Step 2: Add save action**

Validate admin auth, CSRF, Firestore company key, selected company existence, branch code/name/status, and duplicate branch code within the selected company. Upsert inside an ADODB transaction, audit create/update, read back every persisted field, then commit.

- [x] **Step 3: Add status action**

Validate `branch_key` and status, update inside an ADODB transaction, audit status/delete changes, read back the saved status, then commit.

- [x] **Step 4: Add payload rows**

Load `companyBranches` by joining `project_company_branch` to `project_company`, and add a project company branch metric.

### Task 2: Company Management Branch UI

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes payload: `data.companies`, `data.companyBranches`
- Submits actions: `save_company_branch`, `set_company_branch_status`

- [x] **Step 1: Add navigation**

Add `company-branches` to the admin view allowlist and as a Branches submenu item under Company Management.

- [x] **Step 2: Add Branches view**

Create `CompanyBranchCrudView` with the same two-panel 8/4 shell as Companies. Left panel shows branch rows with right-aligned icon actions. Right panel is summary-only.

- [x] **Step 3: Add modal form**

Create/edit branch records in a dialog. Include a required company selector sourced from existing companies, branch code, branch name, status, contact, address, and description. Use confirmation before submit and no Cancel button because the dialog already has a close X.

- [x] **Step 4: Add status icon actions**

Add activate, deactivate, archive, restore, and delete icon buttons that post to `set_company_branch_status` after confirmation.

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

Load `http://localhost/erpsystem/administrator/?tab=company-branches` and confirm the route renders without fatal/PHP/SQL errors. Read `information_schema` for `project_company_branch` columns and indexes.
