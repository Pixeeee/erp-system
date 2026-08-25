# Yovel East Accounting/Finance Chart of Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the first Accounting/Finance building slice: routed Chart of accounts with editable/customizable account form support.

**Architecture:** Extend the shared company-admin PHP entrypoint because `company/yovel-east/admin/index.php` delegates to `company/admin/index.php`. Add Accounting/Finance section metadata, schema/data helpers, transactional POST handlers, and a server-rendered 12/8 two-panel Chart of accounts screen with a data-entry popup and form-builder controls.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, server-rendered HTML, existing shadcn/Tailwind utility classes, native POST forms, existing confirmation dialog JavaScript.

## Global Constraints

- Build only the Accounting/Finance building first slice.
- First completed feature is Chart of accounts.
- Accounting/Finance routes use `?view=accounting-finance&section=<section>`.
- Unknown Accounting/Finance sections fall back to `chart-of-accounts`.
- Every Accounting/Finance section uses the BuilderX company-admin shell and a two-panel layout: left main panel 12 fraction units, right feature/function panel 8 fraction units.
- All Accounting/Finance forms are editable and customizable by the company admin.
- Form customization is company scoped. A change in Yovel East must not alter other companies.
- Custom fields cannot directly alter posted ledger totals.
- Use server-rendered pages and native POST forms.
- Use ADODB transactions, parameterized SQL, audit logging, and direct read-back verification for every persisted write.
- Do not build ledger posting in this first slice.
- Do not present placeholder balances as final financial statements.
- Do not replace the current company-admin PHP route with a frontend SPA.

---

### Task 1: Accounting/Finance Routing And Metadata

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Produces: `yovel_admin_accounting_finance_sections(): array`, `yovel_admin_accounting_finance_section(): string`, `yovel_admin_accounting_finance_feature_href(array $group, string $feature): string`
- Modifies: `yovel_admin_view()`, sidebar link routing, page title metadata

- [x] Add `accounting-finance` to allowed company-admin views.
- [x] Add Accounting/Finance section metadata for all 15 sidebar features.
- [x] Add route validation with fallback to `chart-of-accounts`.
- [x] Update ERP feature href generation so Accounting/Finance sidebar items route to `?view=accounting-finance&section=...`.
- [x] Update active sidebar highlighting for Accounting/Finance.
- [x] Update page title, crumb, eyebrow, heading, and description for Accounting/Finance.
- [x] Run `php -l company/admin/index.php`.

### Task 2: Schema, Form Builder Foundation, And Data Loading

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Produces: `yovel_admin_accounting_finance_schema(): void`, `yovel_admin_accounting_finance_default_form_schemas(): array`, `yovel_admin_accounting_finance_active_schema(array $company, string $recordType, ?array $admin = null): array`, `yovel_admin_accounting_finance_data(array $company, ?array $admin = null): array`
- Consumes: `yovel_admin_db_execute()`, `yovel_admin_code()`, `bx_db()`

- [x] Create idempotent schema for `project_company_accounting_account`.
- [x] Create idempotent shared tables `project_company_form_schema`, `project_company_form_schema_audit`, and `project_company_form_custom_value` if missing.
- [x] Add default Account form schema with ERPNext-inspired fields.
- [x] Add active schema seed/read helpers using `module_code = ACCOUNTING_FINANCE`.
- [x] Add account data loader with parent account labels and active schemas.
- [x] Run `php -l company/admin/index.php`.

### Task 3: Transactional Account And Form Saves

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Produces: POST actions `save_accounting_account`, `set_accounting_account_status`, `save_accounting_form_schema`, `reset_accounting_form_schema`
- Consumes: `yovel_admin_accounting_finance_schema()`, active Account schema, shared confirmation modal

- [x] Implement server validation for account code, name, root type, report type, parent ownership, status, optional account number, decimal tax rate, and self-parent prevention.
- [x] Implement account create/update in an ADODB transaction with parameterized SQL and read-back verification.
- [x] Implement account status updates for `ACTIVE`, `FROZEN`, `INACTIVE`, and `DELETED`.
- [x] Implement form schema save/reset with protected system field normalization and audit rows.
- [x] Implement custom field value persistence into `project_company_form_custom_value` with read-back verification.
- [x] Add Accounting/Finance POST dispatcher and redirect back to the active section.
- [x] Run `php -l company/admin/index.php`.

### Task 4: Chart Of Accounts UI

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `$accountingFinanceData`, `$activeAccountingFinanceSection`, `$editAccountingAccount`, `$activeAccountingFinanceMeta`
- Produces: Authenticated `?view=accounting-finance&section=chart-of-accounts` screen

- [x] Render Chart of accounts summary metrics.
- [x] Render a 12/8 two-panel layout with independent panel scrolling.
- [x] Render account list/tree rows with root type, report type, account type, parent, group/ledger state, status, and actions.
- [x] Render right-panel widgets with drag and drop support.
- [x] Render an add/edit Account popup with Data Entry and Form Builder tabs.
- [x] Render queued panels for later Accounting/Finance sections.
- [x] Run `php -l company/admin/index.php`.

### Task 5: Verification

**Files:**
- Verify: `company/admin/index.php`, `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: local HTTP route `/erpsystem/company/yovel-east/admin/?view=accounting-finance&section=chart-of-accounts`

- [x] Run PHP lint for both company admin entrypoints.
- [x] Run authenticated or unauthenticated HTTP route check and confirm the route does not fatal.
- [x] Run CLI-level create/update/status/custom-field transaction smoke test with a temporary account when credentials and DB access are available.
- [x] Confirm `git diff` only contains intended Accounting/Finance implementation plus this plan.
