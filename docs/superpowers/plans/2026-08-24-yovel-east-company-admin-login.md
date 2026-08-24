# Yovel East Company Admin Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `/erpsystem/company/yovel-east/admin/` with a Yovel East-branded login and a seeded company-scoped `admin / admin12345` account.

**Architecture:** Add project-layer company admin persistence in `app/foundation.php`, then create a focused PHP route under `company/yovel-east/admin/index.php`. The route reuses existing BuilderX helpers for CSRF, flash, password hashing, theme-friendly shadcn CSS assets, session handling, and safe ADODB queries.

**Tech Stack:** PHP, ADODB, MySQL/MariaDB, existing Vite-built CSS assets, existing BuilderX auth helper conventions.

## Global Constraints

- Public login route is `/erpsystem/company/yovel-east/admin/`.
- Do not create a duplicate global `builder_user` named `admin`.
- Seed company-scoped username `admin` with password `admin12345`.
- Store only password hashes.
- Use `project_company_admin` as the project-layer table.
- Scope login by Yovel East company row from `project_company`.
- Do not expose `company_key` in visible UI.
- No Platform role or permission verification in this step.
- Use ADODB parameterized SQL and transaction boundaries for the seed/upsert.

---

### Task 1: Add Company Admin Schema And Seed

**Files:**
- Modify: `app/foundation.php`

**Interfaces:**
- Produces: `project_company_admin`
- Produces: `bx_seed_yovel_east_company_admin(): void`

- [x] **Step 1: Add schema**

Create `project_company_admin` with `admin_key`, `company_key`, `company_key_hash`, `admin_login`, `admin_password_hash`, `admin_name`, `admin_email`, `admin_status`, login tracking, and timestamps.

- [x] **Step 2: Seed Yovel East admin**

Find `project_company.company_code = 'YE'` and upsert company-scoped admin login `admin` with hashed password `admin12345`.

- [x] **Step 3: Verify read-back before commit**

After upsert, read the row back and verify company hash, login, status, and password verification.

### Task 2: Add Company Admin Login Route

**Files:**
- Create: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: `project_company`
- Consumes: `project_company_admin`
- Produces: rendered login page and simple dashboard placeholder

- [x] **Step 1: Resolve active Yovel East company**

Load `company_code = 'YE'` and `company_status = 'ACTIVE'`.

- [x] **Step 2: Handle POST login/logout**

Validate CSRF, check `admin_login`, verify password hash, store session values, update login counters, and redirect back to the route.

- [x] **Step 3: Render BuilderX-style layout**

Render the same login structure as BuilderX with Yovel East company admin copy.

### Task 3: Validate

**Files:**
- Test: `app/foundation.php`
- Test: `company/yovel-east/admin/index.php`

- [x] **Step 1: PHP lint**

Run: `php -l app/foundation.php && php -l company/yovel-east/admin/index.php`
Expected: no syntax errors.

- [x] **Step 2: Database read-back**

Run a PHP read-back that checks the Yovel East `admin` row exists and `password_verify('admin12345', admin_password_hash)` is true.

- [x] **Step 3: Route check**

Run: `curl -sS -L -o /tmp/yovel-admin.html -w '%{http_code}' http://localhost/erpsystem/company/yovel-east/admin/`
Expected: HTTP 200 and visible Yovel East login copy.

- [x] **Step 4: Login flow check**

Use curl with cookie jar:
- GET login page and extract CSRF.
- POST invalid password and confirm login is not accepted.
- POST `admin / admin12345` and confirm dashboard copy appears.
