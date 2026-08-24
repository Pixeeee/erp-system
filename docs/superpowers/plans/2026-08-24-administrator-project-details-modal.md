# Administrator Project Details Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Open a read-only project details modal when an administrator clicks a project inside a selected branch.

**Architecture:** Add local selected-project state inside `ProjectCompanyOverviewView`, render project names as buttons in the branch project table, and introduce a focused `ProjectDetailsDialog` component using existing shadcn Dialog primitives.

**Tech Stack:** React, TypeScript, Vite, shadcn/ui Dialog, existing BuilderX table and badge components.

## Global Constraints

- Administrator page only.
- No database writes.
- Do not show `company_key`, `branch_key`, `project_key`, hash fields, or password fields.
- Modal is read-only.
- Modal closes with header `X`.
- No Cancel button in the modal footer.
- No nested bordered cards.
- Modal body owns scrolling.

---

### Task 1: Add Project Details Dialog

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Produces: `ProjectDetailsDialog`
- Consumes: `project`, `company`, `branch`, `companyAdmins`, `open`, and `onOpenChange`

- [x] **Step 1: Add dialog component**

Create a read-only dialog with header, scrollable body, and informational footer.

- [x] **Step 2: Hide internal keys**

Render human fields only: project code, name, status, description, company name, branch name, created date, updated date, admin login/name/email/status.

### Task 2: Wire Project Click

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `ProjectDetailsDialog`
- Updates: `ProjectCompanyOverviewView`

- [x] **Step 1: Add selected project state**

Use local state to track the clicked project key without displaying it.

- [x] **Step 2: Make project names clickable**

Replace static project names in the selected branch project table with buttons that open the details dialog.

- [x] **Step 3: Render dialog**

Render `ProjectDetailsDialog` at the end of `ProjectCompanyOverviewView`.

### Task 3: Validate

**Files:**
- Test: `frontend`
- Test: `administrator/index.php`

- [x] **Step 1: Build frontend**

Run `npm run build`.

- [x] **Step 2: PHP lint**

Run `php -l administrator/index.php && php -l app/foundation.php && php -l company/yovel-east/admin/index.php`.

- [x] **Step 3: Route checks**

Check the branch-selected project-company URL for HTTP 200, latest bundle, no fatal markers, and no password/hash fields.
