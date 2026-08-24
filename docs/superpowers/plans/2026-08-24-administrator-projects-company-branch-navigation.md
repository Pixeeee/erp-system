# Administrator Projects Company Branch Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Administrator → Projects show company admin context on company click and branch project context on branch click.

**Architecture:** Reuse the existing project-company route and optional `branch_key` state. Simplify the sidebar to company-to-branches only, then conditionally render branch tables or project tables inside `ProjectCompanyOverviewView`.

**Tech Stack:** React, TypeScript, Vite, existing BuilderX shadcn/ui layout components.

## Global Constraints

- Administrator page only.
- No database writes in this change.
- Do not show `company_key`.
- Do not show password hashes.
- Branches are not dropdowns in the Projects sidebar.
- Projects are not listed in the Projects sidebar.
- Left panel is the main panel; right panel is the side panel.
- No nested bordered cards.
- Scroll only inside panel bodies.

---

### Task 1: Simplify Projects Sidebar

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `projectCompanyViewKey(companyKey: string, branchKey?: string): string`
- Produces: sidebar branch buttons that select `project-company:<company_key>::<branch_key>`

- [x] **Step 1: Remove branch project dropdown**

Remove the nested project list under each branch in `NavProjects`.

- [x] **Step 2: Keep company dropdown only**

Keep each company as the only collapsible item. Render branches as direct `SidebarMenuSubButton` entries.

### Task 2: Update Company And Branch Panels

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Produces: `CompanyAdminSummary`
- Updates: `ProjectCompanyOverviewView`

- [x] **Step 1: Add reusable company admin summary**

Render login, name, email, status, and Company Dashboard link without exposing password/hash fields.

- [x] **Step 2: Show company admin on company click**

When `selectedBranchKey` is empty, render branch list on the left and company admin summary on the right.

- [x] **Step 3: Show branch projects on branch click**

When `selectedBranchKey` exists, render selected branch projects on the left and branch/admin/user/role context on the right.

### Task 3: Validate

**Files:**
- Test: `frontend`
- Test: `administrator/index.php`

- [x] **Step 1: Build frontend**

Run `npm run build`.

- [x] **Step 2: PHP lint**

Run `php -l administrator/index.php && php -l app/foundation.php && php -l company/yovel-east/admin/index.php`.

- [x] **Step 3: Route checks**

Check company-only and branch-selected project-company URLs for HTTP 200, latest bundle, no fatal markers, and no password/hash fields.
