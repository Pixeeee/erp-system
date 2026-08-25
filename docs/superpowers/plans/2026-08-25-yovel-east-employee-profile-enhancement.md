# Yovel East Employee Profile Enhancement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refine Employee Profiles into an ERPNext-inspired, record-centered HR workspace without changing the existing employee persistence contract.

**Architecture:** Extend the server-rendered Employee Profiles branch in `company/admin/index.php`. Use data attributes and the existing inline JavaScript boundary for filtering, selection, panel tabs, menus, drag targets, and modal tabs. Derive contextual insights from `$hrEmployees`; do not introduce new tables.

**Tech Stack:** PHP 8, ADODB, server-rendered HTML, Tailwind utility classes, Material Symbols, native JavaScript.

## Global Constraints

- Preserve company scoping, CSRF, confirmation, audit, transaction, and read-back behavior.
- Keep Form Builder and Customize Form on the HR Dashboard.
- Keep the main/right panel ratio at 8:4 on wide screens and stacked on narrow screens.
- Every drag action must have a click and keyboard equivalent.
- Do not add broken links to unfinished HR modules.

---

### Task 1: Employee List Controls And Actions

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `$hrEmployees`, existing employee status POST form
- Produces: filterable semantic table with selection and compact actions

- [ ] Add filter data attributes and accessible controls for ID, name, department, status, and branch.
- [ ] Add result count and a Clear Filters action.
- [ ] Add searchable row metadata and distinct selected/no-results states.
- [ ] Replace always-visible status buttons with one accessible actions menu per row.
- [ ] Preserve all existing status forms, CSRF fields, and confirmation behavior inside the menu.

### Task 2: Contextual Right Panel

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: selected employee row and current employee section keys
- Produces: `Sections`, `Related`, and `Insights` panel views

- [ ] Add an accessible three-tab panel header.
- [ ] Render clickable and draggable section rows without reordering controls.
- [ ] Render related HR destinations and disabled states for unfinished sections.
- [ ] Render profile completeness and missing-data insights for the selected employee.
- [ ] Update the panel when a row is selected and preserve feature drag indicators.

### Task 3: Employee Modal Document Experience

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `$editHrEmployee`, `$employeeDocumentTabs`, existing save form
- Produces: sticky document header/tabs, conditional lifecycle sections, Connections view

- [ ] Add employee code, assignment summary, and unsaved-state metadata to the header.
- [ ] Make modal tabs semantic, sticky, and horizontally usable at narrow widths.
- [ ] Add Connections as a read-only tab with available and planned related destinations.
- [ ] Hide exit fields unless the employee is separated or the Exit tab is intentionally selected for review.
- [ ] Preserve the modal's existing submit, confirmation, and close behavior.

### Task 4: Client Interaction Layer

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: employee table and panel data attributes
- Produces: deterministic filtering, selection, tabs, menus, and contextual rendering

- [ ] Implement case-insensitive multi-filter matching and no-results state.
- [ ] Implement selected-row state and right-panel employee context updates.
- [ ] Implement one-open-at-a-time action menus with Escape and outside-click handling.
- [ ] Keep click and drag feature navigation opening the requested modal section.
- [ ] Add modal body scroll locking and conditional exit notice behavior.

### Task 5: Verification

**Files:**
- Verify: `company/admin/index.php`
- Verify: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: updated route and markup
- Produces: lint and route evidence

- [ ] Run `php -l company/admin/index.php`.
- [ ] Run `php -l company/yovel-east/admin/index.php`.
- [ ] Run focused source assertions for the new filters, context tabs, action menu, and Connections section.
- [ ] Run HTTP route checks and report authentication limitations separately from code verification.

