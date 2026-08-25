# Yovel East HR Teams Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a complete, database-backed Teams workspace using the established HR 8/4 layout, modal workflow, customizable form sections, and focused right panel.

**Architecture:** Add a dedicated Teams service beside the existing employee, department, and job-position services. Extend shared HR data and form metadata, then render a Teams-specific view whose interactions are wired through the shared company-admin script and neutral shadcn-style CSS.

**Tech Stack:** PHP 8 strict types, ADODB/MySQL transactions, server-rendered HTML, Tailwind utility classes, shared vanilla JavaScript, Material Symbols.

## Global Constraints

- Reuse `project_company_hr_team` and employee `team_key`; do not create parallel membership storage.
- All writes are company-scoped, parameterized, transactional, audited, and directly read back before commit.
- Keep only working Team actions and real Team form sections in the right panel.
- Preserve the responsive desktop 8/4 layout without page-level horizontal overflow.

---

### Task 1: Teams Persistence And Form Metadata

**Files:**
- Create: `company/admin/modules/hr/teams.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/data.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/bootstrap/controller.php`
- Test: `tests/hr-database-driven-forms.php`

**Interfaces:**
- Produces: `yovel_admin_save_hr_team(array $company, array $admin): string`
- Produces: `yovel_admin_set_hr_team_status(array $company, array $admin): string`
- Produces: `$hrData['teams']` rows with `assigned_employee_count`
- Produces: `$hrData['formFields']['teams']`

- [ ] Add Team form defaults for `team_code`, `team_name`, `team_status`, and `team_description` in Overview and Details.
- [ ] Query Team records with assigned employee counts grouped from `project_company_hr_employee`.
- [ ] Implement validated create/update upsert with a transaction, unique-code check, typed custom values, audit log, and read-back verification.
- [ ] Implement ACTIVE, INACTIVE, and DELETED status transitions with the same company scope and verification guarantees.
- [ ] Register Team actions, redirects, edit record state, modal section state, and custom values in the controller.
- [ ] Run `php tests/hr-database-driven-forms.php` and require a passing JSON result.

### Task 2: Teams Workspace And Modal

**Files:**
- Create: `company/admin/modules/hr/views/teams.php`
- Modify: `company/admin/modules/hr/views/workspace.php`

**Interfaces:**
- Consumes: `$hrData['teams']`, `$teamFormFields`, `$editHrTeam`, `$teamCustomValues`, and `$activeTeamModalSection`
- Produces: IDs and data attributes prefixed with `yovel-team-` for shared scripts

- [ ] Render aligned desktop panels using `xl:grid-cols-12`, with the directory spanning eight columns and Team Tools spanning four.
- [ ] Add total, active, and assigned-member metrics plus compact Team, Members, Status, and Actions columns.
- [ ] Add icon-only view/status/delete actions with labels and confirmation-backed forms.
- [ ] Add a modal with switchable Overview and Details sections, server-populated edit state, custom fields, assigned-member summary, and a confirmation-backed Save Team action.
- [ ] Add only Add Team, Manage Forms, Employee Profiles, Overview, and Details to the right panel.
- [ ] Add row drop indicators and section widgets that open the selected Team record at the chosen modal section.

### Task 3: Teams Interactions And Responsive Styling

**Files:**
- Modify: `company/admin/views/partials/scripts.php`
- Modify: `company/admin/assets/css/admin.css`

**Interfaces:**
- Consumes: `yovel-team-*` element IDs and `data-team-*` attributes from Task 2
- Produces: modal open/close, tab switching, row targeting, click-to-open, drag/drop, reorder, and keyboard-safe focus restoration

- [ ] Bind Team modal controls and section tabs using the Department and Job Position interaction pattern.
- [ ] Support click and drag/drop from Team form sections to the checked Team row.
- [ ] Persist right-panel section order in company-scoped local storage.
- [ ] Reuse neutral panel surfaces, compact icon actions, sticky modal regions, and responsive stacking.
- [ ] Ensure the right panel fits at 100% zoom and the page has no horizontal overflow.

### Task 4: Verification

**Files:**
- Test: `tests/company-admin-modular-architecture.php`
- Test: `tests/hr-database-driven-forms.php`

**Interfaces:**
- Consumes: completed Teams route and assets
- Produces: verification evidence for syntax, architecture, persistence, HTTP, and browser behavior

- [ ] Run PHP lint for every modified PHP file and require zero syntax errors.
- [ ] Run `php tests/company-admin-modular-architecture.php` and require a pass.
- [ ] Run `php tests/hr-database-driven-forms.php` and require all verification flags to be true.
- [ ] Request the Teams route and require HTTP 200.
- [ ] Verify desktop and compact layouts, modal opening, section switching, and absence of horizontal overflow in the browser.
