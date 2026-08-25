# Accounting/Finance ERPNext Parity Gap Plan

> **For Codex:** REQUIRED SUB-SKILL: Use `executing-plans` to implement this plan package by package, stopping at each verification checkpoint.

**Goal:** Close only the evidence-backed gaps in the pinned Accounting/Finance parity ledger while preserving the completed 17-section Finance implementation and its existing data.

**Architecture:** Extend the existing company-scoped Accounting/Finance services and views in place. Public workflow functions own authorization, validation, one ADODB transaction, parameterized writes, audit events, exact read-back verification, commit/rollback, and server rehydration. Shared modal, confirmation, navigation, CSS, JavaScript, and universal Form Builder contracts remain owned by the orchestration task; Finance consumes those contracts through module-local adapters after they land.

**Tech Stack:** PHP 8, ADODB, server-rendered BuilderX admin views, shadcn-style utility classes, module-local JavaScript hooks, JSON parity ledgers, and focused PHP integration tests.

## Audit Baseline

- Pinned ERPNext source commit: `11e0ba0a1c45f217e2e73e885f699102d06da325`.
- Audited local scope: `company/admin/modules/accounting-finance/` and `tests/accounting-finance-*`.
- Ledger rows: 423 total, 65 `PARTIAL`, 358 `MISSING`, 0 `COMPLETE`, 0 `NOT_APPLICABLE`, 0 `DEFERRED`.
- Source-kind split: 272 DocTypes, 51 reports, 75 report-code files, 22 print formats, 2 workspaces, and 1 notification.
- Existing evidence gate passed on 2026-08-25: all 12 focused Accounting/Finance test files passed.
- The 17-section Finance module is an operational preservation baseline. A working route or similar label was not treated as exact ERPNext parity.
- Row-level traceability is carried by the `[AF-WPxx]` prefix in every ledger row's `reason`. A package may change only rows with its own prefix, and a row becomes `COMPLETE` only after its exact implementation files and freshly passing tests are added to `evidence`.

## Non-Negotiable Standards

- Keep all 17 existing section slugs, current company-scoped records, General Ledger behavior, Philippine BIR VAT behavior, and passing regression tests.
- Use a 20-column desktop workspace with 12 columns for the main panel and 8 for tools/actions; stack main first on smaller screens.
- Every Add, New, Create, or Insert action opens an accessible body-owned modal.
- Submit opens a separate body-owned confirmation dialog. No persistence starts before Confirm; Cancel restores the populated form and focus.
- Every write authorizes the actor and company, validates server-side, uses parameterized SQL and schema-safe identifiers, owns one transaction, records audit history, reads back critical state before commit, rolls back fully on failure, and rehydrates valid input.
- Posted accounting and tax records are corrected only by reversal, cancellation, amendment, or versioned replacement.
- Extend the universal Form Builder through a Finance adapter: drag-and-drop rows/columns/sections/fields, stable protected keys, preview, draft, publish, archive, immutable published versions, version-bound submissions, history, authorization, audit, read-back, and rehydration.
- Implement only module-local improvements. Escalate every shared or cross-module change to orchestration.
- Reproduce behavior without copying ERPNext GPL source or using ERPNext branding.
- Android/mobile application work remains deferred and is not present in this ledger.

## Orchestrator Prerequisites

The Finance implementation task must not edit these paths: `app/foundation.php`, `company/admin/bootstrap/`, `company/admin/core/`, `company/admin/views/layout.php`, `company/admin/views/partials/`, `company/admin/assets/`, `company/admin/modules/shared/`, or `tests/erpnext-*`.

Before AF-WP00 interaction work, orchestration must provide the shared record-modal, sibling confirmation-dialog, server-error rehydration, and universal Form Builder adapter contracts described in the master plan. Before non-Philippine chart templates are built, orchestration must decide whether those rows remain Finance-owned or move to Compliance/Localization. Before cross-module invoice, asset, stock, pricing, party, and payment-gateway links are implemented, their owning modules must expose stable service contracts.

## AF-WP00: Workspace And Universal Interaction Contract

**Ledger rows:** 2 `PARTIAL` workspaces (`Financial Reports`, `Invoicing`) plus the cross-cutting local Form Builder and interaction gap.

**Files:**
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Modify: `company/admin/modules/accounting-finance/forms.php`
- Modify: `company/admin/modules/accounting-finance/views/dashboard.php`
- Modify: `company/admin/modules/accounting-finance/views/workspace.php`
- Modify: `company/admin/modules/accounting-finance/views/form-builder.php`
- Modify: `company/admin/modules/accounting-finance/views/masters.php`
- Modify: `company/admin/modules/accounting-finance/views/invoices.php`
- Modify: `company/admin/modules/accounting-finance/views/journal-entries.php`
- Modify: `company/admin/modules/accounting-finance/views/payment-entries.php`
- Modify: `company/admin/modules/accounting-finance/views/bank-reconciliation.php`
- Modify: `company/admin/modules/accounting-finance/views/budgets.php`
- Modify: `company/admin/modules/accounting-finance/views/period-closing.php`
- Modify: `company/admin/modules/accounting-finance/views/general-ledger.php`
- Modify: `company/admin/modules/accounting-finance/views/financial-report.php`
- Modify: `company/admin/modules/accounting-finance/views/tax-reports.php`
- Modify: `tests/accounting-finance-workspaces.php`
- Modify: `tests/accounting-finance-form-builder.php`
- Create: `tests/accounting-finance-interaction-contract.php`

- [ ] Replace each current `12fr/4fr` desktop grid with the approved 12/8 composition and verify stacked mobile order, independent utility scrolling, sticky opaque headers, no horizontal page overflow, and no clipped controls.
- [ ] Adapt all Finance create/edit/import/lifecycle forms to the orchestration-owned modal and sibling confirmation contract without changing route slugs or POST action names.
- [ ] Prove no POST/write occurs before Confirm, Confirm submits once, Cancel preserves values, server validation reopens the populated modal, and focus restoration is deterministic.
- [ ] Complete the Finance Form Builder adapter for all 15 business targets, protected system fields, drag/drop layout, immutable publication, archive/history, and version-bound submitted records.
- [ ] Keep General Ledger and dashboard as workspace/report routes rather than form targets.
- [ ] Run `php tests/accounting-finance-workspaces.php`, `php tests/accounting-finance-form-builder.php`, `php tests/accounting-finance-interaction-contract.php`, and all existing Finance regressions before changing the two workspace rows.

## AF-WP01: Accounting Foundation And Chart Masters

**Ledger rows:** 8 `PARTIAL`, 157 `MISSING`.

**Files:**
- Modify: `company/admin/modules/accounting-finance/core.php`
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Modify: `company/admin/modules/accounting-finance/grid.php`
- Modify: `company/admin/modules/accounting-finance/views/workspace.php`
- Modify: `company/admin/modules/accounting-finance/views/masters.php`
- Create: `tests/accounting-finance-foundation-parity.php`
- Modify: `tests/accounting-finance-core.php`
- Modify: `tests/accounting-finance-spreadsheet-operations.php`

- [ ] Preserve current account tree editing and transactional CSV import, then add missing account categories, closing balances, dimension filters/allowlists, accounting periods, fiscal years, finance books, monthly distributions, cost-center allocations, and currency/exchange settings represented by `[AF-WP01]` rows.
- [ ] Add a selectable, versioned Philippine chart template with deterministic preview, duplicate handling, parent validation, transactional install, audit, read-back, and idempotency.
- [ ] Pause non-Philippine chart-template work until orchestration records Finance or Compliance/Localization ownership; keep those rows `MISSING` until that decision is explicit.
- [ ] Add focused tests for authorization, company isolation, duplicate codes, hierarchy cycles, immutable posted-account constraints, transaction rollback, audit events, exact read-back, and server rehydration.
- [ ] Update each `[AF-WP01]` row separately; never promote the whole package based on the Philippine template or a generic account test.

## AF-WP02: General Ledger And Journal Depth

**Ledger rows:** 4 `PARTIAL`, 9 `MISSING`.

**Files:**
- Modify: `company/admin/modules/accounting-finance/ledger.php`
- Modify: `company/admin/modules/accounting-finance/journals.php`
- Modify: `company/admin/modules/accounting-finance/views/general-ledger.php`
- Modify: `company/admin/modules/accounting-finance/views/journal-entries.php`
- Create: `tests/accounting-finance-journal-ledger-parity.php`
- Modify: `tests/accounting-finance-journals.php`
- Modify: `tests/accounting-finance-dashboard-ledger.php`

- [ ] Extend journal and GL records for multi-currency amounts/rates, finance books, accounting dimensions, party references, inter-company controls, and explicit amendment chains without mutating submitted rows.
- [ ] Add Journal Entry Templates and their accounts through modal create/edit workflows and the Finance Form Builder adapter.
- [ ] Implement ledger-health diagnostics, invalid-entry detection, controlled ledger merge/repost workflows, and audit-safe repair boundaries for the exact `[AF-WP02]` artifacts.
- [ ] Add concurrency and rollback-injection tests proving balanced atomic posting, immutable originals, opposite-entry reversals, company isolation, authorization, and exact read-back.
- [ ] Re-run the current journal and General Ledger suites after every schema/lifecycle change.

## AF-WP03: Receivables, Payables, And Invoice Lifecycle

**Ledger rows:** 8 `PARTIAL`, 15 `MISSING`.

**Files:**
- Modify: `company/admin/modules/accounting-finance/invoices.php`
- Modify: `company/admin/modules/accounting-finance/payments.php`
- Modify: `company/admin/modules/accounting-finance/views/invoices.php`
- Modify: `company/admin/modules/accounting-finance/views/payment-entries.php`
- Create: `tests/accounting-finance-invoice-parity.php`
- Modify: `tests/accounting-finance-invoices.php`
- Modify: `tests/accounting-finance-payments.php`

- [ ] Add payment schedules/terms, invoice advances, credit/debit notes, opening invoices, discounted invoices, dunning, overdue-payment processing, statement-of-accounts processing, and explicit amendment links for the `[AF-WP03]` rows.
- [ ] Add multi-currency/base-currency totals, exchange gain/loss handling, tax and discount ordering, party-account validation, write-off handling, and return limits while preserving Philippine VAT snapshots.
- [ ] Integrate stock, asset, item, pricing, timesheet, supplier, and customer data only through orchestrator-approved owning-module services; do not write their tables directly.
- [ ] Add modal/form-adapter coverage and transaction tests for draft, submit, cancel, amend, return, partial payment, over-allocation rejection, closed periods, rollback, audit, read-back, and rehydration.

## AF-WP04: Payments, Banking, And Reconciliation

**Ledger rows:** 9 `PARTIAL`, 40 `MISSING`.

**Files:**
- Modify: `company/admin/modules/accounting-finance/payments.php`
- Modify: `company/admin/modules/accounting-finance/banking.php`
- Modify: `company/admin/modules/accounting-finance/core.php`
- Modify: `company/admin/modules/accounting-finance/views/payment-entries.php`
- Modify: `company/admin/modules/accounting-finance/views/bank-reconciliation.php`
- Create: `tests/accounting-finance-payment-banking-parity.php`
- Modify: `tests/accounting-finance-payments.php`
- Modify: `tests/accounting-finance-banking.php`

- [ ] Add payment deductions, advances, payment ledger entries, payment orders/requests, reconciliation allocations/logs, multi-currency settlement, write-offs, and party-account mappings.
- [ ] Add bank master/type/subtype, account balances, import logs and column maps, mapping rules, description conditions, clearance, guarantees, multi-row/multi-payment matching, and statement-level reconciliation controls.
- [ ] Keep imported row fingerprints stable and make import, reconcile, unreconcile, and batch processing idempotent under retry and concurrency.
- [ ] Integrate payment gateways only through an orchestrator-approved service contract.
- [ ] Test unauthorized access, cross-company keys, duplicate imports, partial matches, overmatches, rollback at every write boundary, audit trails, read-back, and confirmation-before-mutation.

## AF-WP05: Tax, Withholding, And Philippine BIR Controls

**Ledger rows:** 9 `PARTIAL`, 5 `MISSING`.

**Files:**
- Modify: `company/admin/modules/accounting-finance/tax.php`
- Modify: `company/admin/modules/accounting-finance/core.php`
- Modify: `company/admin/modules/accounting-finance/invoices.php`
- Modify: `company/admin/modules/accounting-finance/reports.php`
- Modify: `company/admin/modules/accounting-finance/views/tax-reports.php`
- Create: `tests/accounting-finance-tax-parity.php`
- Modify: `tests/accounting-finance-ph-vat.php`
- Modify: `tests/accounting-finance-reports.php`

- [ ] Preserve the tested 12% inclusive/exclusive, zero-rated, exempt, out-of-scope, input, output, government withholding, return, and 2550Q behavior.
- [ ] Add reusable tax templates, item/party applicability, category/rule priority, effective-date overlap validation, withholding groups/rates/thresholds/accounts/entries, and certificate-ready source records for every `[AF-WP05]` row.
- [ ] Snapshot every resolved tax rule on submitted documents so later rule edits never rewrite historical tax results.
- [ ] Verify current Philippine BIR rules against authoritative BIR material before changing rates, classifications, forms, or legal calculations; route compliance-wide changes through orchestration.
- [ ] Test rounding, mixed classifications, returns, withholding thresholds, effective dates, rule precedence, locked periods, rollback, audit, read-back, and historical immutability.

## AF-WP06: Budgeting And Period Close

**Ledger rows:** 5 `PARTIAL`, 3 `MISSING`.

**Files:**
- Modify: `company/admin/modules/accounting-finance/planning.php`
- Modify: `company/admin/modules/accounting-finance/ledger.php`
- Modify: `company/admin/modules/accounting-finance/views/budgets.php`
- Modify: `company/admin/modules/accounting-finance/views/period-closing.php`
- Create: `tests/accounting-finance-planning-parity.php`
- Modify: `tests/accounting-finance-planning-close.php`

- [ ] Add monthly distributions, cost-center/project/dimension scopes, warning and stop policies, cumulative controls, amendments, and fiscal-year validation to budgets.
- [ ] Add multi-company/fiscal-year close detail, background-safe process state, retained-earnings validation, reopening authorization, and resumable failure handling without duplicating GL entries.
- [ ] Test budget policy boundaries, concurrent posting, close locks, duplicate close prevention, failed-close rollback, reversal-based reopen, audit, exact read-back, and server rehydration.

## AF-WP07: Reports, Exports, And Print Formats

**Ledger rows:** 20 `PARTIAL`, 128 `MISSING`.

**Files:**
- Modify: `company/admin/modules/accounting-finance/reports.php`
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Modify: `company/admin/modules/accounting-finance/views/general-ledger.php`
- Modify: `company/admin/modules/accounting-finance/views/financial-report.php`
- Modify: `company/admin/modules/accounting-finance/views/invoices.php`
- Modify: `company/admin/modules/accounting-finance/views/bank-reconciliation.php`
- Modify: `company/admin/modules/accounting-finance/views/budgets.php`
- Modify: `company/admin/modules/accounting-finance/views/tax-reports.php`
- Create: `tests/accounting-finance-report-print-parity.php`
- Modify: `tests/accounting-finance-reports.php`
- Modify: `tests/accounting-finance-dashboard-ledger.php`

- [ ] Complete pinned filters, dimensions, currencies, opening/closing logic, grouping, aging ranges, tree totals, consolidation, comparison periods, presentation currency, and drill-through for the 20 partial report/report-code rows.
- [ ] Implement each missing `[AF-WP07]` report as a dedicated query/calculation plus workspace surface; do not promote a row because a similar total exists elsewhere.
- [ ] Add all 22 pinned print-format capabilities as BuilderX-branded printable templates with company identity, pagination, totals, accessible tables, and print/PDF verification without copying upstream markup or branding.
- [ ] Add deterministic CSV/export behavior and confirmation for consequential exports where required by the shared contract.
- [ ] Build fixture matrices for no-data, populated, filters, currencies, dimensions, reversals, returns, consolidation, malformed ranges, company isolation, and exact totals; add render checks for every print format.

## AF-WP09: Notification And Final Governance Gate

**Ledger rows:** 1 `MISSING` notification plus final cross-cutting evidence gaps.

**Files:**
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Create: `tests/accounting-finance-governance.php`
- Modify: every affected `tests/accounting-finance-*.php`
- Modify: `docs/erpnext-parity/ledgers/accounting-finance.json`

- [ ] Consume the orchestrator-owned notification service to implement the new-fiscal-year notification without writing shared notification infrastructure from this task.
- [ ] Verify every persisted workflow has actor/company authorization, parameterized SQL, one transaction owner, audit event, exact read-back, rollback coverage, and server rehydration evidence.
- [ ] Verify every Add/New/Create/Insert and consequential lifecycle action meets the modal and separate-confirmation contract.
- [ ] Run PHP lint on every module file, all `tests/accounting-finance-*.php`, `php tools/erpnext-parity.php validate-ledger`, `git diff --check`, live route checks for all 17 sections, and desktop/mobile browser checks.
- [ ] Promote rows individually only when exact local files and fresh passing tests are recorded. Any remaining `MISSING` or `PARTIAL` row keeps Accounting/Finance parity open.

## Recommended Implementation Sequence

1. Orchestration lands shared modal, confirmation, and Form Builder contracts.
2. AF-WP00 corrects 12/4 to 12/8 and integrates those shared contracts without altering the 17-section routes.
3. AF-WP01 closes Philippine accounting masters and records the ownership decision for other country templates.
4. AF-WP02 deepens the immutable GL and journal foundation.
5. AF-WP05 completes effective-dated Philippine tax/withholding rules before document expansion.
6. AF-WP03 and AF-WP04 complete invoices, payments, banking, and reconciliation against stable party/stock/asset interfaces.
7. AF-WP06 completes budgets and close controls on the strengthened ledger.
8. AF-WP07 builds reports and print formats after source records and lifecycle semantics stabilize.
9. AF-WP09 performs the notification integration, full regression, live UI verification, and row-by-row ledger closure.

## Completion Commands

```bash
set -e
for test in tests/accounting-finance-*.php; do php "$test"; done
find company/admin/modules/accounting-finance -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tools/erpnext-parity.php validate-ledger
git diff --check
```

Expected: every command exits `0`, all 17 routes render real server-backed workspaces, desktop uses 12/8, mobile stacks without overflow, no mutation occurs before confirmation, and every ledger status is supported by row-specific evidence.
