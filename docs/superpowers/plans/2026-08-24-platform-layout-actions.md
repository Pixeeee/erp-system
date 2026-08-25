# Platform Layout Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Administrator Platform screens responsive at normal zoom and convert Platform table actions to compact icon-only controls.

**Architecture:** Update the shared Platform two-panel layout classes so panels size from available viewport space and scroll inside panel bodies. Reuse the existing Company Management icon button styling for Users, Groups, and Roles action cells without changing form submission, CSRF, or backend behavior.

**Tech Stack:** React, TypeScript, Tailwind utility classes, shadcn-style controls.

## Global Constraints

- Apply only to Platform screens such as Users, Groups, Roles, and Permissions.
- Keep form methods, hidden fields, CSRF, and confirmation behavior unchanged.
- Use icon-only action buttons with accessible labels and titles.
- Keep panel headers outside scroll regions with bottom borders.
- Do not introduce cards inside cards or nested bordered panels.

---

### Task 1: Fix Shared Platform Panel Sizing

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `platformTwoPanelClass`, `platformLeftPanelStackClass`, `platformRightPanelStackClass`, `platformPanelBodyClass`, `platformTableBodyClass`.
- Produces: Updated shared classes used by Platform screens.

- [x] Change the two-panel wrapper to use a viewport-bounded height that leaves room for the page header and tabs at 100% zoom.
- [x] Make stacked panel columns use `overflow-hidden` so scroll happens inside each panel body.
- [x] Keep `DashboardPanel` headers bordered and fixed outside panel body scroll regions.

### Task 2: Convert Platform Actions To Icon Buttons

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: existing `UserStatusButton`, `GroupStatusButton`, `RoleStatusButton`, and edit buttons.
- Produces: icon-only row actions with accessible labels.

- [x] Change Users edit action to `Button` with `size="icon-sm"` and `companyIconButtonClass('edit')`.
- [x] Change `UserStatusButton` to render `size="icon-sm"`, no visible label text, and tone-based icon styling.
- [x] Apply the same icon-only pattern to Groups edit/status actions.
- [x] Apply the same icon-only pattern to Roles edit/status actions.

### Task 3: Validate

**Files:**
- Test: `frontend/src/App.tsx`
- Test: Administrator route output

**Interfaces:**
- Consumes: patched frontend.
- Produces: build and route evidence.

- [x] Run `npm run build` in `frontend`.
- [x] Load `http://localhost/erpsystem/administrator/?tab=users` and verify HTTP 200 without fatal markers.
- [x] Verify rendered Platform action icon buttons keep accessible labels but no visible text.
