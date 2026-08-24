# Administrator Projects Company Table Label Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename the company-level Projects panel to Company and change its table columns to Company, URL Link, Branch, Status.

**Architecture:** Keep the existing `ProjectCompanyOverviewView` route and data flow. Only change the unselected-branch table rendering while preserving branch selection and project modal behavior.

**Tech Stack:** React, TypeScript, Vite, existing BuilderX table and panel components.

## Global Constraints

- Administrator page only.
- No database writes.
- Do not show internal keys.
- Do not change branch-level project details modal behavior.
- Keep the Company Management-style two-panel layout.

---

### Task 1: Update Company-Level Table

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Updates: `ProjectCompanyOverviewView`

- [x] **Step 1: Rename panel title**

Use `Company` when no branch is selected.

- [x] **Step 2: Change table headers**

Use `Company`, `URL Link`, `Branch`, `Status`.

- [x] **Step 3: Render company first**

Show the selected company name in the first column and the clickable branch in the third column.

### Task 2: Validate

**Files:**
- Test: `frontend`
- Test: `administrator/index.php`

- [x] **Step 1: Build frontend**

Run `npm run build`.

- [x] **Step 2: PHP lint**

Run `php -l administrator/index.php && php -l app/foundation.php && php -l company/yovel-east/admin/index.php`.

- [x] **Step 3: Route checks**

Check the company-level project-company URL for HTTP 200, latest bundle, no fatal markers, and no password/hash fields.
