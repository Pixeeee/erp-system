# Shared Company Admin Modular Extraction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the shared company-admin monolith into a reusable modular PHP application while preserving every current company URL and behavior.

**Architecture:** Keep the existing server-rendered PHP and ADODB stack. A thin front controller loads shared bootstrap code, focused business modules, a request controller, and a shared layout whose workspace bodies are owned by their modules. Company folders remain five-line tenant identifiers.

**Tech Stack:** PHP 8, ADODB, server-rendered HTML, Tailwind/shadcn tokens, vanilla JavaScript, PHP static architecture tests, curl route checks.

## Global Constraints

- Preserve all existing GET parameters, POST action names, redirects, database tables, and stable record keys.
- Preserve existing ADODB transaction, audit, authorization, CSRF, and read-back behavior.
- Do not copy shared application code into company-specific directories.
- Do not redesign screens while extracting architecture.
- Keep `company/yovel-east/admin/index.php` compatible.

---

### Task 1: Architecture Characterization Test

**Files:**
- Create: `tests/company-admin-modular-architecture.php`
- Verify: `company/admin/index.php`

**Interfaces:**
- Consumes: the current shared company-admin filesystem.
- Produces: executable assertions for the thin front controller and required module boundaries.

- [ ] Write a test that requires the front controller, bootstrap, controller, shared layout, core functions, and four module function/view files.
- [ ] Assert the front controller contains no function declaration, SQL statement, style block, script block, or module markup.
- [ ] Run `php tests/company-admin-modular-architecture.php` and confirm it fails before extraction.

### Task 2: Shared Front Controller and Backend Modules

**Files:**
- Modify: `company/admin/index.php`
- Create: `company/admin/bootstrap/app.php`
- Create: `company/admin/bootstrap/controller.php`
- Create: `company/admin/core/functions.php`
- Create: `company/admin/modules/platform/functions.php`
- Create: `company/admin/modules/hr/functions.php`
- Create: `company/admin/modules/sales-crm/functions.php`
- Create: `company/admin/modules/accounting-finance/functions.php`

**Interfaces:**
- Consumes: all existing `yovel_admin_*` function contracts and request globals.
- Produces: a thin `index.php`; `bootstrap/app.php` loads every function before `bootstrap/controller.php` dispatches the request.

- [ ] Extract named functions without changing their bodies or signatures.
- [ ] Classify shared helpers under `core` and business functions under their owning module.
- [ ] Move POST dispatch and page-context preparation into `bootstrap/controller.php`.
- [ ] Load the shared layout only after the request context is complete.
- [ ] Run PHP lint across every extracted backend file.

### Task 3: Shared Layout, Module Views, and Assets

**Files:**
- Create: `company/admin/views/layout.php`
- Create: `company/admin/views/dashboard.php`
- Create: `company/admin/views/partials/scripts.php`
- Create: `company/admin/modules/platform/views/workspace.php`
- Create: `company/admin/modules/hr/views/workspace.php`
- Create: `company/admin/modules/sales-crm/views/workspace.php`
- Create: `company/admin/modules/accounting-finance/views/workspace.php`
- Create: `company/admin/assets/css/admin.css`

**Interfaces:**
- Consumes: variables prepared by `bootstrap/controller.php`.
- Produces: the same rendered shell and workspaces with module-owned template files and a shared stylesheet URL.

- [ ] Extract the existing stylesheet byte-for-byte and replace the inline block with a shared asset link.
- [ ] Extract the dynamic browser script to a shared PHP partial without changing its JavaScript.
- [ ] Move each active-view workspace body into the owning module view.
- [ ] Move the default authenticated dashboard body into `views/dashboard.php`.
- [ ] Keep login, header, sidebar, flash, confirmation dialog, and footer in the shared layout.
- [ ] Run PHP lint across all views and verify the CSS asset is reachable.

### Task 4: Compatibility and Route Verification

**Files:**
- Verify: `company/yovel-east/admin/index.php`
- Verify: all files under `company/admin/`
- Test: `tests/company-admin-modular-architecture.php`

**Interfaces:**
- Consumes: the extracted shared application through the existing Yovel East entrypoint.
- Produces: evidence that the architecture changed without changing public behavior.

- [ ] Run the architecture test and require all assertions to pass.
- [ ] Run `php -l` on every company-admin PHP file.
- [ ] Request dashboard, Platform, HR, Sales/CRM, and Accounting/Finance URLs and require non-500 responses with no fatal-error text.
- [ ] Request `company/admin/assets/css/admin.css` and require a 200 response with CSS content.
- [ ] Run the existing frontend build.
- [ ] Inspect the final tree and confirm no shared business code was added beneath `company/yovel-east/`.

### Task 5: Extraction Documentation

**Files:**
- Create: `company/admin/README.md`
- Modify: `docs/project/rules.md`

**Interfaces:**
- Consumes: the verified modular architecture.
- Produces: rules for future company provisioning and module ownership.

- [ ] Document the front-controller flow and module ownership rules.
- [ ] Document the five-line company entrypoint contract.
- [ ] State that future features must be added to their module, not `company/admin/index.php`.
- [ ] Re-run the architecture test after documentation changes.
