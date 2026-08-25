# Complete Accounting and Finance Module Design

## Goal

Replace the generic record behavior behind the Accounting/Finance workspace with a complete, company-scoped accounting system inspired by ERPNext Accounting, Payables, Receivables, and Financial Reports. All existing Finance links must become operational and share one auditable posting model.

## Approved Scope

- Implement financial accounting without stock-ledger or warehouse quantity effects.
- Integrate Sales Invoices with existing Customers and Purchase Invoices with existing Suppliers.
- Implement Philippine BIR VAT computation and review exports.
- Do not submit returns directly to eFPS or eBIRForms.
- Preserve the Finance Dashboard, 12/4 two-panel layouts, modal data entry, sticky headers, Finance Form Builder, and spreadsheet-style list operations.
- Preserve company isolation and administrator authorization on every read and write.

## Accounting Model

Each business document has a dedicated header and child tables. Documents use `DRAFT`, `SUBMITTED`, and `CANCELLED` lifecycle states. Drafts are editable and have no accounting effect. Submission validates the complete document and creates one balanced immutable General Ledger transaction inside the same database transaction. Cancellation creates an additive reversal and never edits or deletes posted ledger rows.

The General Ledger remains the accounting source of truth. Receivable, payable, tax, budget, and financial reports are derived from submitted source documents, payment allocations, and immutable ledger entries. Posted accounting fields cannot be bulk-edited or changed through the Form Builder.

## Shared Foundation

### Company Finance Settings

The Finance Dashboard setup area maintains:

- Base currency, defaulting to PHP when not configured.
- Fiscal year start and end.
- Default receivable, payable, income, expense, bank, cash, input VAT, output VAT, and retained earnings accounts.
- Document numbering series.
- BIR registration profile: registered name, TIN, branch code, RDO code, registered address, VAT registration status, and line of business.
- Posting controls: frozen-through date, closed periods, and cancellation permissions.

### Masters

- Chart of Accounts: hierarchical account tree, group and ledger validation, control-account types, currencies, freeze/disable behavior, and transaction-history protection.
- Cost Centers: hierarchical tree, active state, owner, and default allocation.
- Accounting Dimensions: configurable reference dimensions with mandatory rules for Profit/Loss or Balance Sheet accounts.
- Bank Accounts: bank identity, account number, linked Bank/Cash ledger account, currency, and default state.
- Tax Codes: 12% VATable, zero-rated, VAT-exempt, out-of-scope, input VAT, output VAT, and creditable VAT withheld classifications with effective dates.

## Operational Documents

### Sales Invoices and Receivables

Sales Invoices contain Customer, dates, currency and exchange rate, receivable account, item/service rows, quantity, rate, discounts, income accounts, cost centers, projects, accounting dimensions, VAT classifications, tax rows, payment terms, due dates, remarks, and totals.

Submission debits Accounts Receivable and credits income and output VAT accounts. Returns and Credit Notes reference the original invoice and reverse the appropriate quantities, values, and VAT. The page includes invoice status, outstanding amount, payment allocations, overdue state, customer statement, and receivable aging.

### Purchase Invoices and Payables

Purchase Invoices contain Supplier, supplier invoice reference and duplicate control, dates, currency and exchange rate, payable account, item/service rows, expense or asset accounts, cost centers, projects, accounting dimensions, input VAT classifications, tax rows, payment terms, due dates, remarks, and totals.

Submission debits expense, asset, and input VAT accounts and credits Accounts Payable. Returns and Debit Notes reference and reverse the original invoice. The page includes invoice status, outstanding amount, payment allocations, payment hold, supplier statement, and payable aging.

### Journal Entries

Journal Entries provide a line grid with Account, Party, reference document, debit, credit, currency, exchange rate, Cost Center, Project, dimensions, and remarks. Every row contains exactly one positive debit or credit, all accounts are active company ledger accounts, and document totals must balance before submission.

Supported purposes include general adjustment, bank entry, cash entry, opening entry, accrual, write-off, reclassification, and closing adjustment. Templates may prefill draft rows but never bypass validation.

### Payment Entries

Payment Entries support Receive, Pay, and Internal Transfer. They record party, bank/cash accounts, posting and reference dates, reference number, currencies, exchange rates, paid and received amounts, deductions, and allocations across one or more submitted invoices.

Submission creates balanced General Ledger entries and immutable allocation rows. Full, partial, advance, and unallocated payments are supported. Invoice outstanding amounts are calculated from submitted invoices, credit/debit notes, and submitted allocations rather than directly overwritten.

### Bank Reconciliation

Bank statement rows can be entered in a modal or imported from CSV. Each row stores transaction date, value date, description, reference, deposit, withdrawal, and bank balance. Reconciliation matches statement rows to submitted Payment Entries or Journal Entries, supports one-to-many and many-to-one matches, records differences, and preserves match audit history. Reconciliation never creates a ledger posting by itself unless the administrator explicitly creates and submits a separate adjustment document.

## Planning and Close

### Budgets

Budgets are defined by fiscal year and Cost Center or Project with account-level monthly or annual amounts. Actuals are derived from submitted ledger entries. The system shows consumed, committed where available, remaining, and variance amounts. Configurable over-budget actions are Ignore, Warn, and Stop, enforced during document submission.

### Period Closing

Period Closing validates that the selected period is open, calculates Profit/Loss balances, posts a balanced closing transaction to the configured retained earnings or closing account, and then locks the closed-through date. Reopening requires explicit authorization and creates a reversal of the closing transaction before removing the lock. Source documents cannot be submitted, cancelled, or backdated into a closed period.

## Reports

- General Ledger: opening, debit, credit, running and closing balances with voucher, party, dimension, and reversal filters.
- Profit and Loss: Income and Expense account hierarchy, current period, comparison period, variance, and drill-down to ledger rows.
- Balance Sheet: Assets, Liabilities, and Equity hierarchy with opening/current/comparison balances and an out-of-balance guard.
- Cash Flow: operating, investing, and financing classifications derived from configured account mappings and ledger movement.
- Receivables: customer invoice aging, payment allocations, unapplied credits, statements, and drill-down.
- Payables: supplier invoice aging, payment allocations, unapplied debits, statements, and drill-down.
- Budget Variance: budget, actual, remaining, and variance by account and Cost Center or Project.
- Tax Reports: Philippine VAT summary and supporting transaction schedules.

Reports are read-only computed views. Filters belong in the 4-column right panel; report tables and summaries use the 12-column left panel. CSV export uses the filtered report data.

## Philippine BIR VAT

The tax engine supports VAT-exclusive and VAT-inclusive prices and separately records taxable base and tax amount. Invoice lines are classified as 12% VATable, zero-rated, VAT-exempt, or out-of-scope. Purchase taxes distinguish creditable input VAT, non-creditable input VAT, and capital-goods classifications. Sales to government and creditable VAT withheld are recorded separately.

The Tax Reports page produces a quarterly BIR Form 2550Q working computation from submitted documents and adjustments. It includes VATable sales, sales to government, zero-rated sales, exempt sales, output VAT, domestic purchases by classification, importations when entered, input VAT, adjustments, creditable VAT withheld, prior-period credits entered through controlled adjustments, VAT payable or excess input VAT, and amendment metadata.

Exports include:

- 2550Q review worksheet in CSV format.
- Quarterly sales transaction schedule.
- Quarterly purchase transaction schedule.
- Zero-rated, exempt, and VATable sales breakdown.
- Input VAT and creditable VAT withheld schedules.

Exports are working papers for review and filing. The system does not claim to file, transmit, or certify a BIR return. Effective-dated tax-code configuration and report-version metadata allow future BIR changes without rewriting posted invoices.

## Form Builder and Spreadsheet Operations

Every operational modal renders its active customizable schema around a locked accounting core. Administrators may add, reorder, relabel, hide, and validate custom fields. Required posting fields, calculated totals, lifecycle state, ledger references, tax computations, and allocation controls cannot be removed or changed to incompatible types.

List views retain saved columns, sorting, filtering, grouping, and safe formulas. CSV import and bulk edit are permitted for masters and Draft documents only. Submitted and Cancelled documents are read-only except for allowed follow-up actions such as payment allocation, return creation, cancellation, or drill-down.

## Interface Structure

All Finance sections use the existing sticky section navigation and a 12/4 two-panel workspace. The left panel contains the primary list, document, reconciliation table, or report. The right panel contains filters, setup status, document actions, matching tools, summaries, and contextual shortcuts. Add and edit actions open accessible modals with opaque header/body/footer regions. Long item and accounting-entry grids scroll inside the modal body.

The current 17-section navigation remains stable. Receivable functions live within Sales Invoices, payable functions within Purchase Invoices, and their reports are linked from the Dashboard and each related section.

## Persistence and Transactions

Use dedicated company-scoped tables for Finance settings, Cost Centers, Dimensions, Tax Codes, invoice headers and rows, invoice tax rows, Journal Entries and rows, Payment Entries and allocations, Bank statement rows and matches, Budgets and budget lines, period locks, and report configuration. Every create, update, submit, cancel, allocation, reconciliation, close, and reopen operation uses parameterized SQL, authorization checks, ADODB transaction boundaries, audit logging, direct read-back verification, and server-backed rehydration.

The existing generic Finance grid remains a presentation and customization layer. It is not the accounting source of truth and cannot directly modify immutable ledger records.

## Validation and Failure Handling

- Reject cross-company accounts, parties, documents, dimensions, and allocations.
- Reject group, frozen, inactive, or incompatible accounts.
- Reject unbalanced journals and incomplete document totals.
- Reject duplicate supplier invoice references within the configured scope.
- Reject over-allocation, negative outstanding balances, and payments against cancelled documents.
- Reject submission or cancellation inside closed periods.
- Roll back the source document action when ledger posting or read-back verification fails.
- Display validation next to the affected field or row and preserve Draft input after failure.

## Delivery Sequence

1. Finance settings, tax codes, Cost Centers, Dimensions, Bank Accounts, and shared document/posting infrastructure.
2. Journal Entries and immutable submission/cancellation workflow.
3. Sales Invoices, receivables, Credit Notes, and aging.
4. Purchase Invoices, payables, Debit Notes, and aging.
5. Payment Entries, allocations, and statements.
6. Bank statement import and reconciliation.
7. Budgets, budget enforcement, Period Closing, and reopening.
8. Profit/Loss, Balance Sheet, Cash Flow, budget variance, and drill-down.
9. Philippine BIR VAT 2550Q working computation and transaction-schedule exports.
10. Form Builder integration, spreadsheet operations, accessibility, responsive QA, and full regression coverage.

Each sequence produces working end-to-end behavior and must not leave a page presenting uncommitted or demonstration totals as accounting results.

## Verification

- Unit tests for money, tax, totals, aging, allocation, budget, close, and report calculations.
- Database tests for company isolation, authorization, duplicate prevention, rollback, read-back, and immutable reversals.
- End-to-end tests for Draft, Submit, Cancel, Return, Pay, Allocate, Reconcile, Close, Reopen, and export workflows.
- Accounting invariants: every submitted transaction balances and Balance Sheet assets equal liabilities plus equity.
- BIR VAT fixture tests for 12% VATable, zero-rated, exempt, inclusive, exclusive, government, withheld, return, and adjustment cases.
- Browser verification at desktop and mobile widths in dark and light themes, including modal scroll ownership and sticky headers.

## Reference Baseline

- ERPNext Accounting workspace: `https://erpnext-demo.frappe.cloud/app/accounting`
- ERPNext Payables workspace: `https://erpnext-demo.frappe.cloud/app/payables`
- ERPNext Receivables workspace: `https://erpnext-demo.frappe.cloud/app/receivables`
- ERPNext Financial Reports workspace: `https://erpnext-demo.frappe.cloud/app/financial-reports`
- ERPNext source repository: `https://github.com/frappe/erpnext`
- ERPNext official accounting documentation: `https://docs.frappe.io/erpnext/accounting-entries`
- BIR Form 2550Q eFPS reference: `https://efps.bir.gov.ph/efps-war/EFPSWeb_war/forms/2550Q/TaxReturnSearch.xhtml`
- BIR Revenue Regulations No. 7-2024: `https://bir-cdn.bir.gov.ph/BIR/pdf/RR%20No.%207-%202024.pdf`
