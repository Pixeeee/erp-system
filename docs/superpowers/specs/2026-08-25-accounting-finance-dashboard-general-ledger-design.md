# Accounting/Finance Dashboard and General Ledger Design

## Objective

Give Accounting/Finance its own ERPNext-inspired landing dashboard, make that dashboard the only owner of the Finance Form Builder, add General Ledger as a first-class report, and keep every visible header sticky inside the scroll container it governs.

## References

- ERPNext Accounting workspace, Payables, Receivables, and Financial Reports workspaces.
- ERPNext Accounts architecture: accounting transactions generate balanced General Ledger entries.
- Existing BuilderX HR Dashboard and HR Dashboard Form Builder ownership pattern.
- Existing BuilderX Finance 12/4 workspace, customizable forms, saved grid views, formulas, and Chart of Accounts persistence.

## Navigation and Ownership

- Add `dashboard` as the first Accounting/Finance route and make it the default when no section is supplied.
- Add `general-ledger` as a Finance report route.
- Keep the existing operational and reporting routes after those entries.
- Finance Form Builder targets remain the 15 business features. `dashboard` and `general-ledger` are workspace/report routes and are not form-builder targets.
- Finance Form Builder appears on the Finance Dashboard only. Individual feature pages retain `Customize Form`, which edits the built-in record schema, but do not duplicate the dashboard Form Builder entry.
- Builder links and close links always return to `section=dashboard` while retaining the selected form target in `builder_target`.

## Finance Dashboard

Use the existing `12fr / 4fr` module layout.

### Left Main Panel

- Compact status row using real counts only: active accounts, ledger accounts, group accounts, saved Finance forms, and committed GL entries.
- Accounting setup checklist for Chart of Accounts, dimensions/cost centers, bank setup, and opening/posting readiness.
- Operational shortcuts for invoices, journals, payments, banking, and reconciliation.
- Financial report links for General Ledger, Profit/Loss, Balance Sheet, Cash Flow, and Tax Reports.
- No fake balances, revenue, expenses, receivables, or payables. Amount cards remain absent until actual committed posting data exists.

### Right Functions Panel

- Primary `New Form` and `Existing Forms` actions opening the Finance Form Builder modal.
- Compact setup progress and recent/saved custom-form list.
- Direct links to Customize Chart of Accounts form and General Ledger.
- The panel is independently scrollable and does not duplicate dashboard content as decorative cards.

## General Ledger Foundation

### Database

Add `project_company_general_ledger_entry` with:

- immutable UUID row key and transaction key;
- company key and company hash scope;
- posting date, fiscal year, account key/code/name snapshot;
- voucher type, voucher number, voucher detail number;
- party type and party, cost center, project, finance book;
- debit, credit, transaction currency, account currency, exchange rate;
- remarks, cancellation/reversal markers, source document metadata;
- creator and timestamp indexes for company/date/account/voucher/report filtering.

No update or delete workflow is exposed. Corrections use a new reversal transaction, preserving ledger history.

### Posting API

Provide one transaction-aware persistence function that accepts a complete posting batch.

- Validate company/admin scope, posting date, voucher identity, active leaf accounts, non-negative decimal amounts, and currency format.
- Require every row to contain exactly one positive side: debit or credit.
- Require total debit and total credit to match at configured precision before opening the transaction.
- Reject duplicate transaction keys and duplicate voucher-detail keys.
- Insert every row in one ADODB transaction, audit the transaction, read back the row count and totals, then commit.
- Roll back the entire batch on any error.
- Provide a reversal helper that creates equal opposite rows linked to the original transaction. Original rows remain unchanged.

This slice establishes the posting foundation and tests it with isolated fixtures. Existing draft Chart of Accounts records are not converted into ledger entries, and queued invoice/payment modules do not fabricate postings.

## General Ledger Report

- Read-only report route using committed company-scoped ledger rows.
- Filters: date range, account, voucher type, voucher number, party, cost center, project, and inclusion of reversals.
- Columns: posting date, account, debit, credit, running balance, voucher, party, dimensions, and remarks.
- Show opening balance for the selected filtered scope, period debit, period credit, and closing balance.
- Sort chronologically with a stable row-key tie-breaker.
- Voucher identifiers are plain text until their source document route exists; no broken links.
- Use the shared spreadsheet operations for search, sort, saved views, grouping, formulas, and CSV export where applicable.
- Empty state explicitly says no committed accounting postings exist for the selected filters.

## Sticky Header Standard

Every header sticks only within its owning scroll region:

- application shell header stays at the top of the page workspace;
- Finance section navigation stays below the shell header;
- left and right panel headers stick at the top of their independently scrolling panel bodies;
- table column headers stick inside table scroll containers;
- modal headers and footers remain fixed while modal bodies scroll;
- Finance Form Builder target/mode tabs remain sticky below its modal header;
- grouped table labels may be sticky only below the table header and must not cover it.

Sticky surfaces use opaque theme backgrounds, a bottom border or subtle shadow, explicit z-index tiers, and no overlapping text. On narrow screens, panels stack and each header remains local to its panel instead of sticking to the viewport indefinitely.

## Security and Data Integrity

- All ledger reads and writes require `company_key_hash` scope.
- All writes use parameterized ADODB SQL, authorization already established by the company-admin controller, CSRF-protected forms where exposed, transactions, audit logging, and direct read-back verification.
- Ledger entries are never edited from the report grid or imported through the generic Chart of Accounts CSV importer.
- Calculated display columns remain allow-listed and never use dynamic code execution.

## Verification

- Navigation test proves Dashboard is default, General Ledger is routable, and builder targets exclude both workspace/report routes.
- Ledger tests cover balanced posting, unbalanced rejection with no partial rows, company isolation, duplicate rejection, immutable reversal, and filtered report totals.
- Existing Finance Form Builder, spreadsheet operation, and modular architecture tests remain green.
- Browser verification covers dashboard, Form Builder ownership, General Ledger empty and populated fixture states, sticky headers while scrolling, horizontal Finance navigation, desktop 12/4 layout, and narrow stacked layout.
- PHP lint, `git diff --check`, and dynamic-code security scans pass.
