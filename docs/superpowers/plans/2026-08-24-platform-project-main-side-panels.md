# Platform And Project Main/Side Panels Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Standardize Platform screens and Project company views around the Company Management-style main-left and side-right panel layout.

**Architecture:** Update the shared UI/UX main skill with a permanent main-left side-right panel rule, then apply that rule in `frontend/src/App.tsx`. The main table/work area stays in the left `col-8` panel and secondary forms, summaries, history, or helpers move to the right `col-4` side panel. Panel headers keep bottom borders, panel bodies own scrolling, and Action columns align left like Company Management.

**Tech Stack:** React, TypeScript, shadcn/ui, Tailwind CSS.

## Global Constraints

- Left panel is always the main panel.
- Right panel is always the side panel.
- Use Company Management-style `col-8 / col-4` proportions for Platform and Project company inner screens.
- Panel headers need bottom borders.
- Scroll only inside panel bodies.
- Do not add nested bordered cards or bordered boxes inside panels.
- Align Actions header and icon/button groups to the left when matching Company Management screens.
- Keep the main workspace full width and adaptable at different zoom levels and viewport sizes.

---

### Task 1: Skill Rule

**Files:**
- Modify: `.agents/skills/ui-ux-main/SKILL.md`

**Interfaces:**
- Produces: permanent layout rule for future Platform and Project inner screens

- [x] **Step 1: Add main/side panel rule**

Document that Platform screens and Project company views use left main panel and right side panel with Company Management proportions.

### Task 2: Shared Layout Classes

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Produces: updated `platformTwoPanelClass` and stack/body helper usage

- [x] **Step 1: Change shared two-panel grid**

Make the shared two-panel grid use `minmax(0,8fr)_minmax(320px,4fr)` at `xl` like Company Management.

- [x] **Step 2: Keep panel body scroll ownership**

Keep panel roots `min-h-0 overflow-hidden`, headers fixed outside scroll regions, and body regions `min-h-0 flex-1 overflow-y-auto overscroll-contain`.

### Task 3: Platform Screens

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: existing Users, Groups, Roles, Permissions components

- [x] **Step 1: Users layout**

Place the Users table as the left main panel and place create/edit plus login/reset side surfaces on the right.

- [x] **Step 2: Groups and Roles layout**

Place Groups/Roles tables on the left and create/edit forms on the right.

- [x] **Step 3: Permissions layout**

Keep the matrix/list as the main left panel and move helper/summary content to the side panel.

- [x] **Step 4: Align actions**

Set Platform table `Actions` headers and action button groups to left alignment where they follow Company Management.

### Task 4: Project Company View

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `ProjectCompanyOverviewView({ companyKey })`

- [x] **Step 1: Swap Project company panels**

Render Branches and Projects in the left main panel and Company Summary in the right side panel.

- [x] **Step 2: Match alignment**

Keep panel sizes, scroll ownership, and internal grouping consistent with Company Management.

### Task 5: Verification

**Files:**
- Verify: `.agents/skills/ui-ux-main/SKILL.md`
- Verify: `frontend/src/App.tsx`

- [x] **Step 1: Frontend build**

Run `cd frontend && npm run build`.

- [x] **Step 2: Route checks**

Check `?tab=users`, `?tab=groups`, `?tab=roles`, `?tab=permissions`, and `?tab=project-company&company_key=<active_company_key>` for the latest bundle and no fatal markers.
