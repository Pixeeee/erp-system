# Company Admin Auto Provision Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically give every created company a stable admin URL and seeded company admin login using the Yovel East portal pattern.

**Architecture:** Add a persisted `company_slug`, provision a company admin in the existing company save transaction, and route `/company/<slug>/admin/` to a reusable portal. Existing Yovel East URL continues to work through the same shared implementation.

**Tech Stack:** PHP, ADODB transactions, MySQL/MariaDB, React/Vite frontend payload display, Apache rewrite rules.

## Global Constraints

- Administrator page only for company creation changes.
- No password hashes exposed in browser payloads.
- Company admin default username is `admin`.
- Temporary build password is `admin12345`.
- Existing admin passwords must not be reset during company updates.
- Existing Yovel East URL must continue to work.

---

### Task 1: Add Company Slug Schema And Helpers

**Files:**
- Modify: `app/foundation.php`

**Interfaces:**
- Produces: `bx_project_company_slug_candidate(string $name, string $fallback): string`
- Produces: `bx_project_company_unique_slug(string $baseSlug, string $companyKeyHash = ''): string`
- Produces: `bx_project_company_admin_url(string $companySlug): string`

- [x] **Step 1: Add schema column**

Add `company_slug VARCHAR(120) NOT NULL DEFAULT ''` to `project_company`, then backfill existing rows.

- [x] **Step 2: Add unique index**

Add a unique `company_slug` index after backfill.

- [x] **Step 3: Add slug helpers**

Create reusable slug and URL helpers for Administrator and company portal routes.

### Task 2: Provision Company Admin During Company Save

**Files:**
- Modify: `administrator/index.php`

**Interfaces:**
- Consumes: slug helpers from Task 1
- Produces: verified `project_company_admin` row for each saved company

- [x] **Step 1: Compute stable slug**

On create, generate a unique slug. On update, preserve the existing slug.

- [x] **Step 2: Save slug with company**

Include `company_slug` in the project company upsert and read-back check.

- [x] **Step 3: Seed company admin**

Inside the same transaction, insert `admin / admin12345` only if the company does not already have admin login `admin`.

- [x] **Step 4: Verify before commit**

Read back the company and admin rows before committing.

### Task 3: Reuse Company Admin Portal By Slug

**Files:**
- Create: `company/admin/index.php`
- Modify: `company/yovel-east/admin/index.php`
- Modify: `.htaccess`

**Interfaces:**
- Consumes: `company_slug` URL segment

- [x] **Step 1: Create shared portal**

Move the Yovel East portal logic into a shared slug-aware route.

- [x] **Step 2: Keep Yovel East route**

Make `/company/yovel-east/admin/` set slug `yovel-east` and include the shared route.

- [x] **Step 3: Add rewrite**

Route `/company/<slug>/admin/` to the shared portal for future companies.

### Task 4: Update Administrator Payload And Links

**Files:**
- Modify: `administrator/index.php`
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Produces payload field: `company_slug`

- [x] **Step 1: Add slug to company payload**

Include `company_slug` in `companies`, project company joins, and admin joins.

- [x] **Step 2: Use slug for Company Admin links**

Build links from `company_slug` instead of local client guessing.

- [x] **Step 3: Show URL in Company Management**

Add a visible Company Admin URL link to company rows.

### Task 5: Validate

**Files:**
- Test: `app/foundation.php`
- Test: `administrator/index.php`
- Test: `company/admin/index.php`
- Test: `company/yovel-east/admin/index.php`
- Test: `frontend`

- [x] **Step 1: Build frontend**

Run `npm run build`.

- [x] **Step 2: PHP lint**

Run PHP lint for changed PHP files.

- [x] **Step 3: Schema and seed verification**

Verify `company_slug` exists, Yovel East has `yovel-east`, and Yovel East admin still verifies `admin12345`.

- [x] **Step 4: Route checks**

Check `/company/yovel-east/admin/` and `/company/admin/?company_slug=yovel-east` return HTTP 200 without fatal markers.

- [x] **Step 5: Create/update transaction check**

Create or simulate a test company save through the transaction path, verify slug, admin row, URL, password hash verification, and read-back.
