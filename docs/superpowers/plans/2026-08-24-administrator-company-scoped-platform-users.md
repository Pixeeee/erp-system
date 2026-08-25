# Administrator Company-Scoped Platform Users Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add company-scoped user management to Administrator Platform without replacing global BuilderX users.

**Architecture:** Create shared `project_company_*` identity tables keyed by `company_key` and `company_key_hash`. Add Administrator actions for company user save/status/reset with transaction read-back. Extend the React Users screen with a System Users / Company Users switch and the existing col-8/col-4 panel layout.

**Tech Stack:** PHP, ADODB, MySQL/MariaDB, React, TypeScript, shadcn/ui, Vite.

## Global Constraints

- Do not create one table per company name.
- Store company users in `project_company_user`.
- Keep Administrator as the higher authority over company admins and company users.
- Keep global `builder_user` CRUD available.
- Scroll only inside panel bodies.
- Do not add nested bordered cards.

---

### Task 1: Schema and Seeds

**Files:**
- Modify: `app/foundation.php`

**Interfaces:**
- Produces: `project_company_user`, `project_company_role`, `project_company_permission`, assignment link tables.

- [ ] Add idempotent table creation in `bx_bootstrap_schema()`.
- [ ] Seed standard company roles and permissions for every active company.
- [ ] Verify schema through route load and direct SQL inspection.

### Task 2: Administrator Persistence

**Files:**
- Modify: `administrator/index.php`

**Interfaces:**
- Consumes: `project_company_user` and assignment tables from Task 1.
- Produces: POST actions `save_company_user`, `set_company_user_status`, `reset_company_user_password`.

- [ ] Add actions to the Administrator allow-list.
- [ ] Validate admin auth, CSRF, company, branch/project ownership, required fields, email, username, password length, and status.
- [ ] Save with ADODB transaction, audit, read-back verification, commit, and safe flash messages.
- [ ] Add payload arrays for company users, company roles, groups, and permissions.

### Task 3: Administrator Users UI

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `companyUsers`, `companyRoles`, `companyGroups`, `companyPermissions`.
- Produces: Company Users mode in `UserCrudView`.

- [ ] Add payload types.
- [ ] Add System Users / Company Users mode.
- [ ] Add company selector and company-scoped user table.
- [ ] Add side-panel create/edit form with role, branch, and project assignment.
- [ ] Add icon-only actions and reset/status forms with confirmation.

### Task 4: Verification

**Files:**
- No source changes.

- [ ] Run PHP lint on changed PHP files.
- [ ] Run `npm run build`.
- [ ] Route-check Administrator Users.
- [ ] Use Playwright to verify the Company Users UI renders, has no outer scroll regression, and can open/edit form state.
