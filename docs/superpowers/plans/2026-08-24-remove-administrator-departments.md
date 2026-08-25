# Remove Administrator Departments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove Company Management departments from the main Administrator area because department setup belongs inside each company admin portal.

**Architecture:** Keep `project_company_department_master` and `project_company_department` schema support in `app/foundation.php`. Remove only Administrator-facing department actions, payload, metrics, route keys, menu entries, and React screens.

**Tech Stack:** PHP, ADODB, React, TypeScript, Vite.

## Global Constraints

- Keep department database tables and standard department seed support.
- Remove Administrator UI access to Company Departments.
- Do not expose company keys or password hashes.
- Do not alter company, branch, project, or company admin portal behavior.

---

### Task 1: Remove Administrator Department Backend Surface

**Files:**
- Modify: `administrator/index.php`

**Interfaces:**
- Consumes: Existing POST action routing and payload builder.
- Produces: Administrator payload without department-only keys.

- [x] Remove `save_company_department` and `set_company_department_status` from the POST allowlist.
- [x] Remove the `save_company_department` action handler.
- [x] Remove the `set_company_department_status` action handler.
- [x] Remove `Company Departments` and `Standard Departments` metrics from Administrator payload.
- [x] Remove `companyDepartmentMasters` and `companyDepartments` from Administrator payload.

### Task 2: Remove Administrator Department Frontend Surface

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: Existing Company Management navigation and render switch.
- Produces: Administrator React app without the `company-departments` view.

- [x] Remove `companyDepartmentMasters` and `companyDepartments` from the payload type.
- [x] Remove `company-departments` from `adminViewKeys`.
- [x] Remove `Departments` from the Company Management submenu.
- [x] Remove `CompanyDepartmentCrudView` and `CompanyDepartmentStatusButton`.
- [x] Remove `Company Departments` title, build target, and render branch.

### Task 3: Validate

**Files:**
- Test: `administrator/index.php`
- Test: `frontend/src/App.tsx`
- Test: `app/foundation.php`

**Interfaces:**
- Consumes: Updated backend and frontend.
- Produces: Build and route evidence.

- [x] Run PHP lint for changed PHP files.
- [x] Run `npm run build` in `frontend`.
- [x] Load `http://localhost/erpsystem/administrator/?tab=companies` and verify HTTP 200 without fatal markers.
- [x] Load `http://localhost/erpsystem/administrator/?tab=company-departments` and verify it does not render the removed department screen.
- [x] Verify department schema still exists for future company-admin work.
