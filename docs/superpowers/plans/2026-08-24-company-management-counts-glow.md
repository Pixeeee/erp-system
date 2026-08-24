# Company Management Counts And Glow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show child record counts in Company Management tables and give icon actions distinct glow colors.

**Architecture:** Use the existing React payload arrays to compute counts in the browser: company rows count non-deleted company branches, and branch rows count non-deleted company projects. Replace the single company-management icon button glow class with a tone-based helper shared by Companies, Branches, and Projects.

**Tech Stack:** React, TypeScript, shadcn/ui, Tailwind CSS.

## Global Constraints

- Do not change database schema or backend queries.
- Do not add nested cards or bordered boxes.
- Keep Actions headers and icon rows right-aligned.
- Keep Company Management two-panel body layout unchanged.

---

### Task 1: Child Counts

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `data.companyBranches`, `data.companyProjects`

- [x] **Step 1: Add company branch counts**

Compute non-deleted branch counts keyed by `company_key`, add a `Branches` column to the Companies table, and render the count as a compact badge.

- [x] **Step 2: Add branch project counts**

Compute non-deleted project counts keyed by `branch_key`, add a `Projects` column to the Branches table, and render the count as a compact badge.

### Task 2: Action Glow Colors

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Produces: `companyIconButtonClass(tone)`

- [x] **Step 1: Add tone helper**

Replace the shared static glow string with a tone helper for `add`, `edit`, `deactivate`, `restore`, `archive`, and `delete`.

- [x] **Step 2: Apply tones**

Apply green to add, blue to edit, amber to deactivate, emerald to restore, violet to archive, and red to delete across Companies, Branches, and Projects.

### Task 3: Verification

**Files:**
- Verify: `frontend/src/App.tsx`

- [x] **Step 1: Frontend build**

Run `cd frontend && npm run build`.

- [x] **Step 2: Route checks**

Load `?tab=companies` and `?tab=company-branches` and confirm the payload renders without fatal/PHP/SQL errors and uses the latest built asset.
