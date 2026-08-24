# Administrator Projects Branch Detail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show company branches in Administrator → Projects and let branch clicks reveal project/admin context in the right panel.

**Architecture:** Extend the project-company route state to carry an optional `branch_key`, add read-only `companyAdmins` to the administrator payload, and update `ProjectCompanyOverviewView` to render a branch table on the left plus selected branch details on the right.

**Tech Stack:** PHP ADODB read payload, React, TypeScript, Vite, shadcn/ui table/panel components.

## Global Constraints

- Administrator page only.
- No database writes in this change.
- Do not show `company_key`.
- Do not show password hashes.
- Use optional `branch_key` in the existing project-company URL.
- Left panel is the main branch list; right panel is selected branch detail.
- No nested bordered cards.
- Scroll only inside panel bodies.

---

### Task 1: Expose Company Admins Read Payload

**Files:**
- Modify: `administrator/index.php`
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Produces payload key: `companyAdmins: Array<Record<string, string>>`

- [x] **Step 1: Add payload query**

Read public admin fields such as `admin_key`, `company_key`, `admin_login`, `admin_name`, `admin_email`, `admin_status`, and `admin_last_login_at` from `project_company_admin`, joining `project_company`. Do not expose password hashes or internal hash fields.

- [x] **Step 2: Add frontend payload type**

Add `companyAdmins` to `AdminPayload`.

### Task 2: Add Optional Branch Route State

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Produces: `projectCompanyViewKey(companyKey: string, branchKey?: string): string`
- Produces: `projectCompanyBranchKeyFromView(view: string): string`

- [x] **Step 1: Encode optional branch key**

Use `project-company:<company_key>::<branch_key>` internally.

- [x] **Step 2: Sync URL**

When branch key exists, write `branch_key` to the query string. When absent, remove it.

- [x] **Step 3: Read URL**

Initial load and browser navigation read `branch_key`.

### Task 3: Make Sidebar Branch Click Select The Branch

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: updated route helpers from Task 2

- [x] **Step 1: Track active branch key**

Use `projectCompanyBranchKeyFromView(activeView)` in `NavProjects`.

- [x] **Step 2: Branch click**

Clicking a branch under a company calls `onViewChange(projectCompanyViewKey(company.company_key, branch.branch_key))`.

- [x] **Step 3: Project click**

Clicking a project under a branch selects the branch for now.

### Task 4: Render Branch Table And Detail Panel

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `companyAdmins`, branch key, company admin URL

- [x] **Step 1: Left branch table**

Render columns Branch, URL Link, Projects, Status.

- [x] **Step 2: Right selected branch details**

Render selected branch status, projects, company admin rows, and placeholders for company users and roles.

### Task 5: Validate

**Files:**
- Test: `frontend`
- Test: `administrator/index.php`
- Test: `app/foundation.php`

- [x] **Step 1: Build frontend**

Run `npm run build`.

- [x] **Step 2: PHP lint**

Run `php -l administrator/index.php && php -l app/foundation.php`.

- [x] **Step 3: Route checks**

Check company-only and branch-selected project-company URLs for HTTP 200, latest bundle, and no fatal markers.
