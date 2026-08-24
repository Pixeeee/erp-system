# Project Company Modal Summary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move Company Management to the project-layer `project_company` table and keep all create/edit forms inside a modal while the right panel shows summary only.

**Architecture:** `app/foundation.php` owns the project-layer schema and migration copy from the old table if it exists. `administrator/index.php` persists and reads company records from `project_company`. `frontend/src/App.tsx` keeps the left table, turns the right panel into summary, and opens the same create/edit form inside an accessible dialog.

**Tech Stack:** PHP with ADODB/MySQL, React, shadcn/ui Dialog/Card/Button controls, Lucide icons, Vite/TypeScript build.

## Global Constraints

- Company Management is project-layer data, not a system setting table.
- Use `project_company`.
- Keep `company_key` as the real Firebase Firestore document ID and validate it before writes.
- Right panel is summary only in the main view.
- All create/edit forms must be inside a modal.
- Scroll only inside panel bodies; no `Card` inside `Card` or nested bordered card.

---

### Task 1: Project Company Persistence

**Files:**
- Modify: `app/foundation.php`
- Modify: `administrator/index.php`

**Interfaces:**
- Consumes: existing `bx_db()`, `bx_admin_table_exists()`, `bx_validate_firestore_document_id()`, `bx_audit()`, and `bx_admin_payload_rows()`.
- Produces: `project_company` table, `save_company` writes to `project_company`, `set_company_status` writes to `project_company`, and the `companies` payload reads from `project_company`.

- [x] Replace `builder_company` schema creation with `project_company`.
- [x] Add a migration copy from `builder_company` to `project_company` only when the old table exists.
- [x] Replace every company CRUD query, count, payload, and audit entity with `project_company`.
- [x] Keep `company_key` and `company_key_hash` validation/read-back unchanged.
- [x] Verify with PHP lint and a direct schema read.

### Task 2: Company Modal Form and Summary Panel

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `data.companies`, `save_company`, `set_company_status`, `ConfirmationModal`, shadcn `Dialog` components.
- Produces: left table panel, right read-only summary panel, modal create/edit form, and existing confirmation-before-submit behavior.

- [x] Add modal state for the company form.
- [x] Open the modal from the green Add icon and each row Edit action.
- [x] Move the form from the right panel into a `Dialog` with header, scrollable body, and footer.
- [x] Replace the right panel body with summary metrics derived from `data.companies`.
- [x] Keep no nested bordered cards inside the panels or dialog.
- [x] Verify with frontend build and focused route checks.
