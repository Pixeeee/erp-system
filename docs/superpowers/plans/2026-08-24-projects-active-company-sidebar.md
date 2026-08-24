# Projects Active Company Sidebar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Projects sidebar group's Branches/Projects items with active company names that open a company detail overview.

**Architecture:** Keep Company Management CRUD routes unchanged. Add a dynamic project-company view key for sidebar-selected companies and render a read-only overview using existing `companies`, `companyBranches`, and `companyProjects` payload arrays.

**Tech Stack:** React, TypeScript, shadcn/ui, Tailwind CSS.

## Global Constraints

- Do not change database schema or writes.
- Do not change Company Management CRUD menu behavior.
- Show only `ACTIVE` companies in the Projects sidebar group.
- The detail view must show the selected company, its branches, and its projects.
- Keep scroll inside panel bodies and do not add nested bordered cards.

---

### Task 1: Sidebar And Routing

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Produces: `projectCompanyViewKey(companyKey)`, `projectCompanyKeyFromView(view)`

- [x] **Step 1: Add dynamic company view helpers**

Create helpers for `project-company:<company_key>` active views and active-company validation.

- [x] **Step 2: Replace Projects sidebar items**

Remove static Branches/Projects rendering from `NavProjects` and render active companies from `data.companies`.

- [x] **Step 3: Wire URL state**

Store company selection as `?tab=project-company&company_key=<company_key>` and support browser navigation.

### Task 2: Company Detail View

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `ProjectCompanyOverviewView({ companyKey })`

- [x] **Step 1: Add read-only overview**

Render company summary, branches, and projects for the selected company.

- [x] **Step 2: Connect render tree**

Show the overview when the dynamic project company view is active.

### Task 3: Verification

**Files:**
- Verify: `frontend/src/App.tsx`

- [x] **Step 1: Build**

Run `cd frontend && npm run build`.

- [x] **Step 2: Route checks**

Check `?tab=project-company&company_key=<active_company_key>` and existing Company Management routes for latest bundle and no fatal markers.
