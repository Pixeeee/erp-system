# Accounting/Finance Dashboard and General Ledger Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Accounting/Finance open on a dedicated dashboard, centralize the Finance Form Builder there, add an immutable balanced General Ledger foundation and report, and make all headers sticky in their owning scroll region.

**Architecture:** Extend the existing modular PHP Finance package with a focused `ledger.php` persistence/report service and dedicated `dashboard.php` and `general-ledger.php` views. Keep ledger writes behind one company-scoped ADODB transaction API, and keep the shared workspace responsible only for routing, common navigation, feature pages, and modal includes.

**Tech Stack:** PHP 8, ADODB with MySQL/MariaDB, server-rendered HTML, existing Tailwind/shadcn utility output, vanilla JavaScript, BuilderX audit and CSRF helpers.

## Global Constraints

- Use the existing `12fr / 4fr` Finance layout.
- Finance Form Builder appears on the Finance Dashboard only; feature pages retain only built-in `Customize Form` ownership.
- Ledger rows are immutable; corrections create reversal transactions.
- Do not fabricate accounting values for queued transaction modules.
- All ledger SQL is parameterized and company-scoped, with transaction, audit, and read-back verification.
- Every header sticks only inside the scroll region it owns and must not overlap another header.

---

### Task 1: Finance Navigation and Builder Ownership

**Files:**
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Modify: `company/admin/modules/accounting-finance/forms.php`
- Modify: `company/admin/bootstrap/controller.php`
- Test: `tests/accounting-finance-dashboard-ledger.php`

**Interfaces:**
- Produces: `yovel_admin_accounting_finance_sections()` with `dashboard` and `general-ledger` routes.
- Produces: `yovel_admin_finance_builder_target_sections()` excluding workspace/report-only routes.

- [ ] Add a failing navigation test asserting Dashboard is default, General Ledger exists, and neither route is a builder target.
- [ ] Run `php tests/accounting-finance-dashboard-ledger.php` and confirm the assertions fail.
- [ ] Add Dashboard and General Ledger metadata, change fallback/default section to Dashboard, and filter builder targets to record-form sections.
- [ ] Update controller fallbacks and builder redirects to return to the Dashboard.
- [ ] Run the focused test until the navigation assertions pass.

### Task 2: Immutable General Ledger Persistence

**Files:**
- Create: `company/admin/modules/accounting-finance/ledger.php`
- Modify: `company/admin/bootstrap/app.php`
- Test: `tests/accounting-finance-dashboard-ledger.php`

**Interfaces:**
- Produces: `yovel_admin_general_ledger_schema(): void`.
- Produces: `yovel_admin_post_general_ledger_transaction(ADOConnection $db, array $company, array $admin, array $transaction): array`.
- Produces: `yovel_admin_reverse_general_ledger_transaction(ADOConnection $db, array $company, array $admin, string $transactionKey, string $postingDate, string $reason): array`.
- Produces: `yovel_admin_general_ledger_entries(array $company, array $filters = []): array`.
- Produces: `yovel_admin_general_ledger_summary(array $company, array $filters = []): array`.

- [ ] Add failing tests for table/index creation, balanced posting, unbalanced rejection, duplicate rejection, company isolation, and immutable reversal.
- [ ] Run the focused test and confirm ledger functions are absent.
- [ ] Implement idempotent schema with immutable entry rows and company/date/account/voucher indexes.
- [ ] Implement normalization and full-batch validation before `BeginTrans()`.
- [ ] Implement parameterized inserts, audit, direct count/totals read-back, commit, and rollback.
- [ ] Implement reversal as a new balanced transaction with opposite debit/credit rows and original transaction linkage.
- [ ] Run the focused test until all ledger persistence assertions pass.

### Task 3: Dashboard and General Ledger Data Preparation

**Files:**
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Modify: `company/admin/bootstrap/controller.php`
- Test: `tests/accounting-finance-dashboard-ledger.php`

**Interfaces:**
- Extends `yovel_admin_accounting_finance_data()` with `ledgerEntries`, `ledgerSummary`, and `ledgerEntryCount`.
- Produces controller variables for Dashboard counts and General Ledger filters.

- [ ] Add failing report tests for date, account, voucher, party, cost center, project, reversal filters, and opening/period/closing totals.
- [ ] Implement allow-listed filter normalization and company-scoped reads.
- [ ] Compute opening balance before `date_from`, period debit/credit, and closing balance for the selected scope.
- [ ] Prepare controller variables without loading unrelated module data.
- [ ] Run the focused test until report and summary assertions pass.

### Task 4: Finance Dashboard and Form Builder Ownership UI

**Files:**
- Create: `company/admin/modules/accounting-finance/views/dashboard.php`
- Modify: `company/admin/modules/accounting-finance/views/workspace.php`
- Modify: `company/admin/modules/accounting-finance/views/form-builder.php`
- Modify: `company/admin/views/partials/scripts.php`
- Modify: `company/admin/assets/css/admin.css`

**Interfaces:**
- Dashboard opens Form Builder through `finance_builder=1`, `builder_mode`, and `builder_target`.
- Feature pages expose `Customize Form` but no Finance Form Builder duplicate.

- [ ] Route `section=dashboard` to the new dedicated dashboard view.
- [ ] Build a 12/4 operational workspace with real counts, setup checklist, shortcuts, reports, New Form, Existing Forms, and saved-form list.
- [ ] Move Finance Form Builder modal include and open/close URLs to Dashboard ownership.
- [ ] Remove feature-page Finance Form Builder buttons while preserving Customize Form and spreadsheet operations.
- [ ] Add concise dashboard component CSS that works in both themes and stacked narrow layouts.
- [ ] Verify the Dashboard and Form Builder route manually in the browser.

### Task 5: General Ledger Report UI

**Files:**
- Create: `company/admin/modules/accounting-finance/views/general-ledger.php`
- Modify: `company/admin/modules/accounting-finance/views/workspace.php`
- Modify: `company/admin/assets/css/admin.css`
- Modify: `company/admin/views/partials/scripts.php`

**Interfaces:**
- Reads controller-provided `generalLedgerEntries`, `generalLedgerSummary`, and normalized filter values.
- Uses GET filters and existing CSV export behavior without exposing ledger write controls.

- [ ] Build filter controls for date, account, voucher, party, cost center, project, and reversals.
- [ ] Render opening, period debit, period credit, and closing metrics from database results.
- [ ] Render a semantic, horizontally scrollable ledger table with running balance and truthful empty state.
- [ ] Add CSV export and reset-filter controls.
- [ ] Confirm the report never offers cell editing, row deletion, or generic account CSV import.

### Task 6: Sticky Header Standard

**Files:**
- Modify: `company/admin/views/layout.php`
- Modify: `company/admin/modules/accounting-finance/views/workspace.php`
- Modify: `company/admin/modules/accounting-finance/views/dashboard.php`
- Modify: `company/admin/modules/accounting-finance/views/general-ledger.php`
- Modify: `company/admin/modules/accounting-finance/views/form-builder.php`
- Modify: `company/admin/modules/accounting-finance/views/grid-operations.php`
- Modify: `company/admin/assets/css/admin.css`

**Interfaces:**
- Produces reusable classes `yovel-sticky-shell-header`, `yovel-finance-sticky-nav`, `yovel-finance-sticky-panel-header`, `yovel-finance-sticky-table-header`, and `yovel-finance-sticky-modal-header`.

- [ ] Assign sticky classes to shell, Finance navigation, panel, table, and modal header surfaces.
- [ ] Define opaque backgrounds, borders/shadows, and non-conflicting z-index tiers.
- [ ] Keep panel headers local by ensuring panel bodies own scrolling and sticky elements are inside those bodies where necessary.
- [ ] Test scrolling at desktop and narrow widths and correct any overlap or transparent content bleed.

### Task 7: Final Verification

**Files:**
- Test: `tests/accounting-finance-dashboard-ledger.php`
- Test: `tests/accounting-finance-form-builder.php`
- Test: `tests/accounting-finance-spreadsheet-operations.php`
- Test: `tests/company-admin-modular-architecture.php`

**Interfaces:**
- Consumes all prior tasks; produces verification evidence only.

- [ ] Run all four focused PHP tests.
- [ ] Run PHP lint across `company/admin`.
- [ ] Run `git diff --check` and the no-`eval` security scan.
- [ ] Browser-test Dashboard default routing, Form Builder ownership, General Ledger filters/table, sticky headers, desktop 12/4 layout, and narrow stacked layout.
- [ ] Keep the verified Finance Dashboard open for user review and report any remaining queued transactional posting integrations explicitly.
