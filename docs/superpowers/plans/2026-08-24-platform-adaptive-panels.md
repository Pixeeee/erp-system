# Platform Adaptive Panels Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Platform left/right panels responsive to zoom and viewport size while following the BuilderX UI/UX panel rules.

**Architecture:** Add shared panel/layout classes in `frontend/src/App.tsx`, then apply them to Users, Groups, Roles, and Permissions. Keep behavior and database forms unchanged; only layout, scroll ownership, and panel sizing change.

**Tech Stack:** React, TypeScript, shadcn/ui, Tailwind CSS.

## Global Constraints

- Keep Platform data behavior unchanged.
- Do not add nested cards or bordered boxes inside bordered panels.
- Left and right panel headers must keep a bottom border.
- Scroll only inside panel bodies.
- The layout must stack at smaller widths and high zoom.

---

### Task 1: Shared Platform Panel Layout

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Produces: `platformTwoPanelClass`, `platformLeftPanelStackClass`, `platformRightPanelStackClass`, `platformPanelBodyClass`, `platformTableBodyClass`

- [x] **Step 1: Add shared classes**

Create shared class constants near `DashboardPanel`.

- [x] **Step 2: Make DashboardPanel flex-safe**

Allow `DashboardPanel` to receive `className` and make its root/header compatible with internal scroll regions.

### Task 2: Apply To Platform Views

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: shared Platform layout classes from Task 1

- [x] **Step 1: Update Users**

Apply the adaptive two-panel layout, internal panel scrolling, and table body scroll regions.

- [x] **Step 2: Update Groups**

Apply the same adaptive two-panel layout and table body scroll region.

- [x] **Step 3: Update Roles**

Apply the same adaptive two-panel layout and table body scroll region.

- [x] **Step 4: Update Permissions**

Make permission tabs/panels flex-safe and keep matrix/table overflow inside panel bodies.

### Task 3: Verification

**Files:**
- Verify: `frontend/src/App.tsx`

- [x] **Step 1: Build**

Run `cd frontend && npm run build`.

- [x] **Step 2: Route checks**

Check `?tab=users`, `?tab=groups`, `?tab=roles`, and `?tab=permissions` for latest bundle and no PHP/SQL fatal markers.
