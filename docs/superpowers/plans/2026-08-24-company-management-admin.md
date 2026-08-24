# Company Management Administrator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Company Management under the Administrator Platform menu with MySQL-backed company CRUD and Firestore-compatible `company_key` validation.

**Architecture:** `app/foundation.php` owns schema creation for the new `builder_company` table. `administrator/index.php` owns server-side validation, transactions, audit logging, read-back, and payload rows. `frontend/src/App.tsx` owns the Administrator menu, tab routing, payload type, and `col-8` list plus `col-4` create/edit form.

**Tech Stack:** PHP 8, ADODB, MySQL, React 19, TypeScript, Vite, shadcn/ui components, Lucide icons.

## Global Constraints

- Only `http://localhost/erpsystem/administrator/` is in scope.
- Keep Phase Builder visible and unchanged.
- Add Platform menu group `Company Management` with child `Companies`.
- Use `companies` as the admin tab key.
- Use a 12-column workspace layout: `col-8` list and `col-4` form.
- `company_key` is the Firebase Firestore document ID.
- Validate `company_key`: valid UTF-8, no more than 1,500 bytes, no `/`, not `.` or `..`, and not matching `__.*__`.
- Do not add Firebase credentials or direct Firebase writes.
- Existing unrelated working tree changes must be preserved.

---

### Task 1: Schema And Backend Company Writes

**Files:**
- Modify: `app/foundation.php`
- Modify: `administrator/index.php`

**Interfaces:**
- Consumes: Existing `bx_db()`, `bx_audit()`, `bx_flash()`, `bx_admin_redirect()`, and authenticated admin POST handling.
- Produces: `builder_company` table, `save_company` POST action, `set_company_status` POST action, and `companies` payload rows.

- [ ] **Step 1: Add `builder_company` schema**

Add this table in `app/foundation.php` near `builder_branch` and `builder_project`:

```php
$db->Execute("
    CREATE TABLE IF NOT EXISTS builder_company (
        x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_key VARCHAR(1500) NOT NULL,
        company_key_hash CHAR(64) NOT NULL,
        company_code VARCHAR(40) NOT NULL UNIQUE,
        company_name VARCHAR(160) NOT NULL,
        company_status ENUM('DRAFT','ACTIVE','INACTIVE','ARCHIVED','DELETED') NOT NULL DEFAULT 'ACTIVE',
        company_email VARCHAR(190) NULL,
        company_phone VARCHAR(40) NULL,
        company_address TEXT NULL,
        company_description TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_builder_company_key_hash (company_key_hash),
        INDEX idx_builder_company_status (company_status),
        INDEX idx_builder_company_name (company_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
```

- [ ] **Step 2: Add Firestore document ID validator**

Add this helper in `administrator/index.php` near other admin validation helpers:

```php
function bx_validate_firestore_document_id(string $documentId): bool
{
    if ($documentId === '' || strlen($documentId) > 1500) {
        return false;
    }

    if (str_contains($documentId, '/') || $documentId === '.' || $documentId === '..') {
        return false;
    }

    if (preg_match('/^__.*__$/', $documentId) === 1) {
        return false;
    }

    return mb_check_encoding($documentId, 'UTF-8');
}
```

- [ ] **Step 3: Add transactional `save_company` action**

Inside authenticated admin POST handling, add `save_company`. It must validate `company_key`, `company_code`, `company_name`, `company_status`, and `company_email`; use a SHA-256 `company_key_hash` to reject duplicate `company_key`; reject duplicate `company_code`; use parameterized SQL; audit create/update; read back the saved row before flashing success; commit only after read-back.

- [ ] **Step 4: Add transactional `set_company_status` action**

Inside authenticated admin POST handling, add `set_company_status`. It must validate `company_key` and status, update status with parameterized SQL, audit the change, read back the row before flashing success, and redirect to `companies`.

- [ ] **Step 5: Add companies payload rows**

Add this payload key:

```php
'companies' => bx_admin_payload_rows(bx_db()->GetAll('SELECT company_key, company_code, company_name, company_status, company_email, company_phone, company_address, company_description, created_at, updated_at FROM builder_company ORDER BY company_name ASC')),
```

- [ ] **Step 6: Run backend validation**

Run:

```bash
php -l app/foundation.php
php -l administrator/index.php
```

Expected: both pass.

### Task 2: Administrator Companies UI

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `data.companies: Array<Record<string, string>>`.
- Produces: `companies` admin view under Platform > Company Management with a `col-8` table and `col-4` create/edit form.

- [ ] **Step 1: Add payload field and admin view key**

Add `companies` to `AdminPayload` and `adminViewKeys`:

```ts
companies: Array<Record<string, string>>
```

```ts
const adminViewKeys = ['dashboard', 'companies', 'users', 'groups', 'roles', 'permissions', 'branches', 'projects', 'settings', 'audit', 'health', 'template']
```

- [ ] **Step 2: Add Platform menu group**

Add `Company Management` to `sidebarSections`:

```ts
{
  key: 'company-management',
  label: 'Company Management',
  icon: Building2,
  items: [
    { key: 'companies', label: 'Companies' },
  ],
}
```

- [ ] **Step 3: Add `CompanyCrudView`**

Create a React component that follows `ProjectCrudView` patterns: confirmation modal, edit selection state, server-posted form, and status action buttons. Use `xl:grid-cols-[minmax(0,8fr)_minmax(320px,4fr)]`, with the company list first and the form second.

- [ ] **Step 4: Add view title, build target, and render branch**

Map `companies` to title `Companies`, build target `P1-COMPANY`, and render `<CompanyCrudView />`.

- [ ] **Step 5: Run frontend validation**

Run:

```bash
cd frontend && npm run build
```

Expected: PASS.

### Task 3: End-To-End Verification

**Files:**
- Test only.

**Interfaces:**
- Consumes: Modified backend and frontend.
- Produces: Evidence that Company Management is available and existing Platform items still work.

- [ ] **Step 1: Static route checks**

Run:

```bash
rg -n "Company Management|companies|save_company|set_company_status|builder_company" frontend/src/App.tsx administrator/index.php app/foundation.php
rg -n "Phase Builder" frontend/src/App.tsx
```

Expected: company matches exist and Phase Builder matches remain.

- [ ] **Step 2: Browser render check**

Open:

```text
http://localhost/erpsystem/administrator/?tab=companies
```

Expected: the page renders `Companies`, shows Platform > Company Management, has the company list in the larger left area and the create/edit form in the smaller right area.

## Self-Review

- Spec coverage: all approved behavior is covered by Tasks 1-3.
- Placeholder scan: no `TBD`, `TODO`, or vague implementation steps remain.
- Type consistency: `company_key`, `companies`, `save_company`, `set_company_status`, and `builder_company` are named consistently across tasks.
