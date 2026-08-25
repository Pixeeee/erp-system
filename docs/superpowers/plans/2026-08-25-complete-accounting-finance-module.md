# Complete Accounting and Finance Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn every existing Accounting/Finance section into a functional, company-scoped accounting workflow with immutable ledger posting, receivables, payables, treasury, close, financial reports, and Philippine BIR VAT working papers.

**Architecture:** Add focused domain modules behind the existing Finance controller and 17-section workspace. Dedicated source-document tables own business state; the existing immutable General Ledger owns posted accounting state; reports derive from submitted documents and ledger entries. Existing customizable schemas and spreadsheet controls remain presentation extensions and cannot mutate posted accounting data.

**Tech Stack:** PHP 8, MariaDB, ADODB, server-rendered HTML, existing BuilderX utility classes, vanilla JavaScript, CSV exports

## Global Constraints

- Financial accounting only; do not create Stock Ledger or warehouse quantity effects.
- Use existing Customers and Suppliers, company isolation, administrator authorization, parameterized SQL, ADODB transactions, audit logging, read-back verification, and server rehydration.
- Drafts are editable and unposted; Submitted documents are immutable; cancellation posts additive reversals.
- Preserve the 12/4 two-panel layout, modal entry, sticky headers, Finance Form Builder, and safe spreadsheet operations.
- Implement Philippine BIR VAT working computations and exports without direct eFPS/eBIRForms transmission.
- Never use floating-point arithmetic as the persisted source for money or tax amounts; normalize decimal strings and round tax at explicit currency precision.

---

### Task 1: Finance Settings, Masters, and Posting Controls

**Files:**
- Create: `company/admin/modules/accounting-finance/core.php`
- Modify: `company/admin/bootstrap/app.php`
- Modify: `company/admin/bootstrap/controller.php`
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Create: `tests/accounting-finance-core.php`

**Interfaces:**
- Produces: `yovel_admin_finance_core_schema()`, `yovel_admin_finance_settings()`, `yovel_admin_save_finance_settings()`, `yovel_admin_save_finance_master()`, `yovel_admin_finance_assert_open_period()`, `yovel_admin_finance_next_number()`, `yovel_admin_finance_money()`.
- Consumes: `bx_db()`, `yovel_admin_db_execute()`, `bx_audit()`, company/admin arrays, existing Chart of Accounts.

- [ ] **Step 1: Write failing foundation tests**

Create fixtures that assert company-scoped Finance settings, default PHP currency, BIR profile fields, Cost Center hierarchy, Accounting Dimension rules, Bank Account linkage to active Bank/Cash ledger accounts, Tax Code effective dates, document-number sequencing, and closed-period rejection.

```php
finance_core_assert(yovel_admin_finance_money('1234.5') === '1234.500000', 'Money normalization failed.');
$settings = yovel_admin_finance_settings($company, $admin);
finance_core_assert(($settings['base_currency'] ?? '') === 'PHP', 'Default currency must be PHP.');
finance_core_expect_error(fn () => yovel_admin_finance_assert_open_period($company, '2026-03-31'), 'closed');
```

- [ ] **Step 2: Run the foundation test and verify failure**

Run: `php tests/accounting-finance-core.php`

Expected: FAIL because `yovel_admin_finance_core_schema()` is undefined.

- [ ] **Step 3: Implement dedicated foundation tables and helpers**

Create company-scoped tables:

```text
project_company_finance_setting
project_company_finance_number_series
project_company_finance_cost_center
project_company_finance_dimension
project_company_finance_dimension_value
project_company_finance_bank_account
project_company_finance_tax_code
project_company_finance_period_lock
```

Use `DECIMAL(20,6)` for amounts, effective start/end dates for Tax Codes, UUID public keys, unique company-scoped codes, and status indexes. Validate account type, group state, active status, parent ownership, date ranges, BIR TIN/branch/RDO formatting, and period locks before writes.

- [ ] **Step 4: Wire core actions and data loading**

Add controller actions `save_finance_settings`, `save_finance_master`, and `set_finance_master_status`; route them back to the posted Finance section. Require `core.php` before transaction modules in `bootstrap/app.php`.

- [ ] **Step 5: Run the foundation test**

Run: `php tests/accounting-finance-core.php`

Expected: `Accounting/Finance core checks passed.`

---

### Task 2: Philippine VAT Calculation Engine

**Files:**
- Create: `company/admin/modules/accounting-finance/tax.php`
- Modify: `company/admin/bootstrap/app.php`
- Create: `tests/accounting-finance-ph-vat.php`

**Interfaces:**
- Produces: `yovel_admin_ph_vat_calculate_line()`, `yovel_admin_ph_vat_calculate_document()`, `yovel_admin_ph_vat_quarter()`, `yovel_admin_ph_vat_export_rows()`.
- Consumes: active Tax Codes from Task 1 and normalized decimal strings.

- [ ] **Step 1: Write failing VAT fixtures**

Cover VAT-exclusive 12%, VAT-inclusive 12%, zero-rated, exempt, out-of-scope, returns, government sales, input VAT, non-creditable input VAT, and creditable VAT withheld.

```php
$exclusive = yovel_admin_ph_vat_calculate_line('1120.00', '1', 'VAT12', false, false);
vat_assert($exclusive['taxable_base'] === '1120.000000', 'Exclusive VAT base failed.');
vat_assert($exclusive['vat_amount'] === '134.400000', 'Exclusive VAT amount failed.');
$inclusive = yovel_admin_ph_vat_calculate_line('1120.00', '1', 'VAT12', true, false);
vat_assert($inclusive['taxable_base'] === '1000.000000', 'Inclusive VAT base failed.');
vat_assert($inclusive['vat_amount'] === '120.000000', 'Inclusive VAT amount failed.');
```

- [ ] **Step 2: Run and verify failure**

Run: `php tests/accounting-finance-ph-vat.php`

Expected: FAIL because the VAT calculator is undefined.

- [ ] **Step 3: Implement deterministic VAT calculation**

Use integer minor units or decimal-string operations for taxable base, discount, VAT, withholding, and line total. Store classification and effective Tax Code metadata on submitted invoice tax snapshots so later Tax Code edits do not rewrite history.

- [ ] **Step 4: Run VAT tests**

Run: `php tests/accounting-finance-ph-vat.php`

Expected: `Philippine VAT checks passed.`

---

### Task 3: Journal Entry Lifecycle and Ledger Posting

**Files:**
- Create: `company/admin/modules/accounting-finance/journals.php`
- Modify: `company/admin/bootstrap/app.php`
- Modify: `company/admin/bootstrap/controller.php`
- Create: `tests/accounting-finance-journals.php`

**Interfaces:**
- Produces: `yovel_admin_save_journal_entry()`, `yovel_admin_submit_journal_entry()`, `yovel_admin_cancel_journal_entry()`, `yovel_admin_journal_entries()`, `yovel_admin_journal_entry()`.
- Consumes: `yovel_admin_post_general_ledger_transaction()`, `yovel_admin_reverse_general_ledger_transaction()`, posting controls from Task 1.

- [ ] **Step 1: Write failing lifecycle tests**

Assert Draft save without GL rows, balanced submission with GL rows, rejection of unbalanced/cross-company/group/frozen accounts, immutable Submitted rows, additive cancellation reversal, and closed-period rejection.

```php
$draft = yovel_admin_persist_journal_entry($db, $company, $admin, $payload);
journal_assert($draft['document_status'] === 'DRAFT', 'Journal draft status failed.');
$submitted = yovel_admin_submit_journal_entry($db, $company, $admin, $draft['journal_entry_key']);
journal_assert($submitted['document_status'] === 'SUBMITTED', 'Journal submission failed.');
```

- [ ] **Step 2: Run and verify failure**

Run: `php tests/accounting-finance-journals.php`

Expected: FAIL because journal persistence is undefined.

- [ ] **Step 3: Implement journal header/row tables and atomic actions**

Create `project_company_finance_journal_entry` and `project_company_finance_journal_row`. Persist Draft header and rows in one transaction. During submission lock the Draft, revalidate rows, post GL entries with `source_module='JOURNAL_ENTRY'`, update the source with the returned transaction key, read back both sides, and commit. Cancellation posts a reversal then marks the source Cancelled.

- [ ] **Step 4: Wire controller actions**

Add `save_finance_journal`, `submit_finance_journal`, and `cancel_finance_journal`; redirect to `section=journal-entries` with the active document key when appropriate.

- [ ] **Step 5: Run journal and ledger tests**

Run: `php tests/accounting-finance-journals.php && php tests/accounting-finance-dashboard-ledger.php`

Expected: both suites pass.

---

### Task 4: Sales and Purchase Invoices, Receivables, and Payables

**Files:**
- Create: `company/admin/modules/accounting-finance/invoices.php`
- Modify: `company/admin/bootstrap/app.php`
- Modify: `company/admin/bootstrap/controller.php`
- Create: `tests/accounting-finance-invoices.php`

**Interfaces:**
- Produces: `yovel_admin_save_finance_invoice()`, `yovel_admin_submit_finance_invoice()`, `yovel_admin_cancel_finance_invoice()`, `yovel_admin_create_finance_return()`, `yovel_admin_finance_invoices()`, `yovel_admin_finance_invoice()`, `yovel_admin_invoice_outstanding()`, `yovel_admin_receivable_aging()`, `yovel_admin_payable_aging()`.
- Consumes: VAT engine, Finance settings, Customer/Supplier lookups, General Ledger posting.

- [ ] **Step 1: Write failing invoice tests**

Test Sales and Purchase Drafts with multiple lines, discounts, 12%/zero/exempt VAT, inclusive pricing, due dates, duplicate supplier references, company-party scope, submission postings, VAT snapshots, outstanding balances, Credit/Debit Notes, cancellation reversals, and aging buckets.

```php
$sales = yovel_admin_persist_finance_invoice($db, $company, $admin, 'SALES', $salesPayload);
invoice_assert($sales['grand_total'] === '1120.000000', 'Sales total failed.');
$posted = yovel_admin_submit_finance_invoice($db, $company, $admin, 'SALES', $sales['invoice_key']);
invoice_assert($posted['outstanding_amount'] === '1120.000000', 'Receivable opening failed.');
```

- [ ] **Step 2: Run and verify failure**

Run: `php tests/accounting-finance-invoices.php`

Expected: FAIL because invoice persistence is undefined.

- [ ] **Step 3: Implement invoice tables and calculations**

Create:

```text
project_company_finance_invoice
project_company_finance_invoice_line
project_company_finance_invoice_tax
project_company_finance_payment_term
```

Store invoice type, return reference, party snapshot, supplier reference, dates, currency, totals, document status, GL transaction key, and immutable submitted snapshots. Recalculate all client-provided totals on the server.

- [ ] **Step 4: Implement submission and returns**

Sales submission debits Receivable and credits income/output VAT. Purchase submission debits expense/asset/input VAT and credits Payable. Return documents use negative business quantities but generate correctly directed positive debit/credit GL rows and reference the original invoice.

- [ ] **Step 5: Run invoice, VAT, and ledger tests**

Run: `php tests/accounting-finance-invoices.php && php tests/accounting-finance-ph-vat.php && php tests/accounting-finance-dashboard-ledger.php`

Expected: all suites pass.

---

### Task 5: Payment Entries and Immutable Allocations

**Files:**
- Create: `company/admin/modules/accounting-finance/payments.php`
- Modify: `company/admin/bootstrap/app.php`
- Modify: `company/admin/bootstrap/controller.php`
- Create: `tests/accounting-finance-payments.php`

**Interfaces:**
- Produces: `yovel_admin_save_payment_entry()`, `yovel_admin_submit_payment_entry()`, `yovel_admin_cancel_payment_entry()`, `yovel_admin_payment_entries()`, `yovel_admin_open_invoice_references()`, `yovel_admin_invoice_allocations()`.
- Consumes: invoices from Task 4, Bank/Cash accounts from Task 1, GL posting.

- [ ] **Step 1: Write failing payment tests**

Test Receive, Pay, Internal Transfer, partial allocation, multi-invoice allocation, advance/unallocated amount, over-allocation rejection, wrong-party rejection, currency/exchange-rate validation, cancellation reversal, and restored outstanding amounts.

```php
$payment = yovel_admin_persist_payment_entry($db, $company, $admin, $receivePayload);
$submitted = yovel_admin_submit_payment_entry($db, $company, $admin, $payment['payment_entry_key']);
payment_assert(yovel_admin_invoice_outstanding($company, $invoiceKey) === '620.000000', 'Partial allocation failed.');
```

- [ ] **Step 2: Run and verify failure**

Run: `php tests/accounting-finance-payments.php`

Expected: FAIL because payment persistence is undefined.

- [ ] **Step 3: Implement payment and allocation tables**

Create `project_company_finance_payment_entry`, `project_company_finance_payment_allocation`, and `project_company_finance_payment_deduction`. Lock referenced invoices during submission, validate aggregate allocation against current outstanding, post GL rows, insert immutable allocation rows, and read back all totals before commit.

- [ ] **Step 4: Run payment and invoice tests**

Run: `php tests/accounting-finance-payments.php && php tests/accounting-finance-invoices.php`

Expected: both suites pass.

---

### Task 6: Bank Statement Import and Reconciliation

**Files:**
- Create: `company/admin/modules/accounting-finance/banking.php`
- Modify: `company/admin/bootstrap/app.php`
- Modify: `company/admin/bootstrap/controller.php`
- Create: `tests/accounting-finance-banking.php`

**Interfaces:**
- Produces: `yovel_admin_import_bank_statement()`, `yovel_admin_save_bank_statement_row()`, `yovel_admin_reconcile_bank_rows()`, `yovel_admin_unreconcile_bank_match()`, `yovel_admin_bank_statement_rows()`, `yovel_admin_bank_match_candidates()`.
- Consumes: submitted Payment and Journal Entries and Bank Accounts.

- [ ] **Step 1: Write failing reconciliation tests**

Test CSV parsing, duplicate row fingerprint rejection, deposit/withdrawal exclusivity, one-to-one, one-to-many, many-to-one matching, amount/date/reference candidate ranking, difference recording, company isolation, and unreconcile audit.

- [ ] **Step 2: Run and verify failure**

Run: `php tests/accounting-finance-banking.php`

Expected: FAIL because bank import is undefined.

- [ ] **Step 3: Implement bank statement and match tables**

Create `project_company_finance_bank_statement_row`, `project_company_finance_bank_reconciliation`, and `project_company_finance_bank_match`. Import CSV with structured column mapping, generate a company/bank/date/reference/amount fingerprint, and preserve raw row text for audit. Reconciliation only links rows to submitted source records; adjustment postings require a separate Journal or Payment Entry.

- [ ] **Step 4: Run banking tests**

Run: `php tests/accounting-finance-banking.php`

Expected: `Accounting/Finance banking checks passed.`

---

### Task 7: Budgets and Period Closing

**Files:**
- Create: `company/admin/modules/accounting-finance/planning.php`
- Modify: `company/admin/bootstrap/app.php`
- Modify: `company/admin/bootstrap/controller.php`
- Create: `tests/accounting-finance-planning-close.php`

**Interfaces:**
- Produces: `yovel_admin_save_budget()`, `yovel_admin_budget_actuals()`, `yovel_admin_assert_budget_available()`, `yovel_admin_close_finance_period()`, `yovel_admin_reopen_finance_period()`, `yovel_admin_period_closings()`.
- Consumes: GL totals, settings, Cost Centers, Projects/dimensions, period locks.

- [ ] **Step 1: Write failing planning tests**

Test monthly/annual budgets, actual and remaining totals, Warn/Stop behavior, period Profit/Loss calculation, closing transfer to retained earnings, closed-date enforcement across submit/cancel, and reopening through reversal.

- [ ] **Step 2: Run and verify failure**

Run: `php tests/accounting-finance-planning-close.php`

Expected: FAIL because budget persistence is undefined.

- [ ] **Step 3: Implement budget and closing tables**

Create `project_company_finance_budget`, `project_company_finance_budget_line`, and `project_company_finance_period_close`. Enforce budget Stop inside invoice/journal submission before GL posting. Close only through the maximum posted date, create one balanced closing transaction, then insert the period lock. Reopen by reversing the close transaction before marking the lock inactive.

- [ ] **Step 4: Run planning and transaction tests**

Run: `php tests/accounting-finance-planning-close.php && php tests/accounting-finance-journals.php && php tests/accounting-finance-invoices.php`

Expected: all suites pass.

---

### Task 8: Financial, Receivable, Payable, and BIR VAT Reports

**Files:**
- Create: `company/admin/modules/accounting-finance/reports.php`
- Modify: `company/admin/bootstrap/app.php`
- Create: `tests/accounting-finance-reports.php`

**Interfaces:**
- Produces: `yovel_admin_profit_loss_report()`, `yovel_admin_balance_sheet_report()`, `yovel_admin_cash_flow_report()`, `yovel_admin_budget_variance_report()`, `yovel_admin_receivables_report()`, `yovel_admin_payables_report()`, `yovel_admin_bir_2550q_report()`.
- Consumes: account hierarchy, GL, invoice/allocation tables, budgets, VAT snapshots.

- [ ] **Step 1: Write failing report fixtures**

Seed a balanced accounting story: owner funding, taxable sale, customer receipt, taxable purchase, supplier payment, expense adjustment, return, and close. Assert P/L, Balance Sheet equation, Cash Flow classes, receivable/payable aging, budget variance, 2550Q sales/input/output/withheld/net VAT, and voucher drill-down.

- [ ] **Step 2: Run and verify failure**

Run: `php tests/accounting-finance-reports.php`

Expected: FAIL because report functions are undefined.

- [ ] **Step 3: Implement read-only report queries**

Aggregate only submitted source documents and GL entries, with optional reversal inclusion. Preserve account-tree ordering and roll child balances into groups. Return structured arrays with summary, rows, filters, and export rows. Reject a Balance Sheet result whose Assets do not equal Liabilities plus Equity within currency precision.

- [ ] **Step 4: Implement 2550Q working computation**

Use submitted VAT snapshots grouped by quarter and BIR classification. Return VATable private sales, government sales, zero-rated, exempt, output VAT, purchase classifications, input VAT, adjustments, creditable VAT withheld, prior credits, and net payable/excess input VAT. Attach report version and amendment metadata.

- [ ] **Step 5: Run report and VAT tests**

Run: `php tests/accounting-finance-reports.php && php tests/accounting-finance-ph-vat.php`

Expected: both suites pass.

---

### Task 9: Dedicated 12/4 Workspaces and Modal Forms

**Files:**
- Create: `company/admin/modules/accounting-finance/views/masters.php`
- Create: `company/admin/modules/accounting-finance/views/invoices.php`
- Create: `company/admin/modules/accounting-finance/views/journal-entries.php`
- Create: `company/admin/modules/accounting-finance/views/payment-entries.php`
- Create: `company/admin/modules/accounting-finance/views/bank-reconciliation.php`
- Create: `company/admin/modules/accounting-finance/views/budgets.php`
- Create: `company/admin/modules/accounting-finance/views/period-closing.php`
- Create: `company/admin/modules/accounting-finance/views/financial-report.php`
- Create: `company/admin/modules/accounting-finance/views/tax-reports.php`
- Modify: `company/admin/modules/accounting-finance/views/workspace.php`
- Modify: `company/admin/bootstrap/controller.php`
- Modify: `company/admin/assets/css/admin.css`
- Create: `tests/accounting-finance-workspaces.php`

**Interfaces:**
- Produces: complete route rendering for every existing Finance section.
- Consumes: domain loaders/actions from Tasks 1-8 and active customizable form schemas.

- [ ] **Step 1: Write failing route/view assertions**

Assert every section maps to a dedicated view, no section renders `Status: Queued`, all Add/Edit flows are modal, all operational modals contain header/body/footer, all primary workspaces use 12/4 panels, and report filters render in the right panel.

- [ ] **Step 2: Run and verify failure**

Run: `php tests/accounting-finance-workspaces.php`

Expected: FAIL because queued Finance sections remain.

- [ ] **Step 3: Build master and transaction lists**

Render dense, scan-friendly lists with truthful status/amount metrics, search, saved views, filters, and row actions. Keep Customize Form and spreadsheet operations in the right panel. Restrict bulk edit/import controls to masters and Draft documents.

- [ ] **Step 4: Build accessible operational modals**

Use opaque header, independently scrolling body, footer Close action, confirmation on save/submit/cancel, server validation feedback, and item/account/allocation grids with stable dimensions. Locked accounting fields and calculated totals remain visible but non-editable when Submitted or Cancelled.

- [ ] **Step 5: Build report workspaces and CSV actions**

Render P/L, Balance Sheet, Cash Flow, Receivables, Payables, Budget Variance, and Tax Reports from server data. Use the existing CSV escaping helper and filtered rows; label 2550Q output as a review worksheet, not a filed return.

- [ ] **Step 6: Run workspace tests**

Run: `php tests/accounting-finance-workspaces.php && php tests/company-admin-modular-architecture.php`

Expected: both suites pass.

---

### Task 10: Form Builder Locks, Spreadsheet Safety, and End-to-End Verification

**Files:**
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Modify: `company/admin/modules/accounting-finance/forms.php`
- Modify: `company/admin/modules/accounting-finance/grid.php`
- Modify: `company/admin/views/partials/scripts.php`
- Modify: `tests/accounting-finance-form-builder.php`
- Modify: `tests/accounting-finance-spreadsheet-operations.php`
- Create: `tests/accounting-finance-end-to-end.php`

**Interfaces:**
- Produces: locked accounting-core schema controls, Draft-only spreadsheet mutation, and complete workflow coverage.
- Consumes: all prior domain functions and views.

- [ ] **Step 1: Add failing safety assertions**

Assert required posting fields cannot be removed, hidden, or type-changed; calculated totals and lifecycle fields are read-only; Submitted/Cancelled records reject grid mutation; CSV import cannot submit documents; and formulas never execute arbitrary code.

- [ ] **Step 2: Run and verify failure**

Run: `php tests/accounting-finance-form-builder.php && php tests/accounting-finance-spreadsheet-operations.php`

Expected: at least one new safety assertion fails.

- [ ] **Step 3: Enforce form/grid boundaries**

Extend required/readonly schema registries per record type, render custom fields around dedicated core forms, persist custom values with each source record, and reject mutation requests whose source document is not Draft.

- [ ] **Step 4: Add complete workflow test**

Create Customer/Supplier/account fixtures, submit Sales and Purchase Invoices, allocate partial/full payments, post a Journal, reconcile a bank row, enforce a budget, close/reopen a period, and assert GL, P/L, Balance Sheet, aging, and VAT outputs before cleaning up all fixtures.

- [ ] **Step 5: Run the full Finance suite**

Run:

```bash
php tests/accounting-finance-core.php
php tests/accounting-finance-ph-vat.php
php tests/accounting-finance-journals.php
php tests/accounting-finance-invoices.php
php tests/accounting-finance-payments.php
php tests/accounting-finance-banking.php
php tests/accounting-finance-planning-close.php
php tests/accounting-finance-reports.php
php tests/accounting-finance-workspaces.php
php tests/accounting-finance-end-to-end.php
php tests/accounting-finance-dashboard-ledger.php
php tests/accounting-finance-form-builder.php
php tests/accounting-finance-spreadsheet-operations.php
php tests/company-admin-modular-architecture.php
```

Expected: every command exits 0.

- [ ] **Step 6: Run syntax and structural verification**

Run: `find company/admin/modules/accounting-finance company/admin/bootstrap company/admin/views -name '*.php' -print0 | xargs -0 -n1 php -l`

Run: `git diff --check`

Expected: no syntax errors and no diff errors.

- [ ] **Step 7: Perform browser QA**

Verify every Finance route, representative create/edit/submit/cancel workflows, invoice item calculations, allocation controls, bank matching, close/reopen, report drill-down, 2550Q export, Form Builder, sticky regions, light/dark themes, and desktop/mobile widths. Leave the Finance Dashboard open with no modal obscuring the workspace.
