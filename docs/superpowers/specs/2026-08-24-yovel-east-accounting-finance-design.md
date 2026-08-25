# Yovel East Accounting/Finance Design

## Goal

Turn the Yovel East Accounting/Finance dropdown into a real company-admin workspace. All 15 Accounting/Finance items currently point to dashboard anchors. This work traces those links into routed sections, then builds the Accounting/Finance building one feature at a time.

The first completed feature is Chart of accounts.

## References

- Current local route: `/erpsystem/company/yovel-east/admin/`
- Current sidebar source: `company/admin/index.php`
- ERPNext Accounting demo route: `https://erpnext-demo.frappe.cloud/app/accounting`
- ERPNext source: `https://github.com/frappe/erpnext`
- ERPNext Accounting workspace JSON: `https://raw.githubusercontent.com/frappe/erpnext/develop/erpnext/accounts/workspace/accounting/accounting.json`
- ERPNext Chart of Accounts docs: `https://docs.frappe.io/erpnext/chart-of-accounts`
- ERPNext Account DocType: `erpnext/accounts/doctype/account/account.json`
- ERPNext Cost Center DocType: `erpnext/accounts/doctype/cost_center/cost_center.json`

ERPNext organizes Accounting around setup, opening and closing, taxes, budgeting, reports, and settings. BuilderX should follow that workflow shape without copying Frappe internals directly.

ERPNext's Account model is a tree. Important concepts to carry over are account name, account number, group account, parent account, root type, report type, account type, company, currency, freeze state, and tree ordering.

## Scope

Build only the Accounting/Finance building in this cycle. Other ERP departments keep their current behavior unless sidebar routing must be generalized so Accounting/Finance can route cleanly.

The current Accounting/Finance links are:

- `./?view=dashboard#erp-accounting-finance-feature-chart-of-accounts`
- `./?view=dashboard#erp-accounting-finance-feature-cost-centers`
- `./?view=dashboard#erp-accounting-finance-feature-accounting-dimensions`
- `./?view=dashboard#erp-accounting-finance-feature-sales-invoices`
- `./?view=dashboard#erp-accounting-finance-feature-purchase-invoices`
- `./?view=dashboard#erp-accounting-finance-feature-journal-entries`
- `./?view=dashboard#erp-accounting-finance-feature-payment-entries`
- `./?view=dashboard#erp-accounting-finance-feature-bank-accounts`
- `./?view=dashboard#erp-accounting-finance-feature-bank-reconciliation`
- `./?view=dashboard#erp-accounting-finance-feature-budgets`
- `./?view=dashboard#erp-accounting-finance-feature-period-closing`
- `./?view=dashboard#erp-accounting-finance-feature-profit-loss`
- `./?view=dashboard#erp-accounting-finance-feature-balance-sheet`
- `./?view=dashboard#erp-accounting-finance-feature-cash-flow`
- `./?view=dashboard#erp-accounting-finance-feature-tax-reports`

They become:

- `?view=accounting-finance&section=chart-of-accounts`
- `?view=accounting-finance&section=cost-centers`
- `?view=accounting-finance&section=accounting-dimensions`
- `?view=accounting-finance&section=sales-invoices`
- `?view=accounting-finance&section=purchase-invoices`
- `?view=accounting-finance&section=journal-entries`
- `?view=accounting-finance&section=payment-entries`
- `?view=accounting-finance&section=bank-accounts`
- `?view=accounting-finance&section=bank-reconciliation`
- `?view=accounting-finance&section=budgets`
- `?view=accounting-finance&section=period-closing`
- `?view=accounting-finance&section=profit-loss`
- `?view=accounting-finance&section=balance-sheet`
- `?view=accounting-finance&section=cash-flow`
- `?view=accounting-finance&section=tax-reports`

Unknown Accounting/Finance sections fall back to `chart-of-accounts`.

## Build Order

1. Accounting/Finance foundation: routing, sidebar links, shared section metadata, shared form schema tables, and form builder shell.
2. Chart of accounts: first complete CRUD workflow, tree/list view, add/edit popup, editable form schema, status actions, drag and drop widgets, and audit trail.
3. Cost centers: cost center tree/list and assignment-ready master data.
4. Accounting dimensions: dimension definitions used by later invoices, journals, budgets, and reports.
5. Sales invoices: receivable document records.
6. Purchase invoices: payable document records.
7. Journal entries: general ledger adjustment records.
8. Payment entries: incoming and outgoing payment records.
9. Bank accounts: bank and cash account setup.
10. Bank reconciliation: matching bank transactions against payments and ledger entries.
11. Budgets: budget setup by fiscal period, account, cost center, and dimension.
12. Period closing: fiscal closing controls.
13. Profit/loss: income statement report.
14. Balance sheet: asset, liability, and equity report.
15. Cash flow: cash movement report.
16. Tax reports: statutory tax summaries and export-ready reports.

This order is deliberate. Chart of accounts, cost centers, and dimensions are setup foundations. Invoices, journals, payments, bank reconciliation, budgets, closing, and reports depend on clean setup data.

## Standard Layout

Every Accounting/Finance section uses the BuilderX company-admin shell and a two-panel layout:

- Left main panel: 12 fraction units.
- Right feature/function panel: 8 fraction units.

The left panel holds the most important working data: account trees, cost center trees, invoice tables, ledger rows, reconciliation tables, budget matrices, and report outputs.

The right panel holds tools and functions: add/edit actions, form builder controls, drag and drop widgets, section shortcuts, saved filters, import/export actions, validation helpers, and workflow helpers.

On smaller screens, the panels stack with the main panel first and the feature panel second.

Panel headers must stay outside the scroll region. Each panel body owns its own `min-h-0 flex-1 overflow-y-auto overscroll-contain` scroll area.

## Editable Form Standard

All Accounting/Finance forms are editable and customizable by the company admin. No Accounting/Finance form should be treated as permanently hardcoded.

Each record type has a default system form schema. The admin can adjust the active company form without changing source code:

- Reorder fields.
- Rename visible labels.
- Mark fields required or optional when the underlying database allows it.
- Hide non-required fields.
- Group fields into popup sections.
- Move fields between sections.
- Choose compact field widths where the UI supports it.
- Add custom fields that are stored as company-scoped metadata values.
- Restore the default form schema.

The form builder belongs in the right panel and inside add/edit popups as a dedicated builder tab. The normal data-entry tab remains the default so everyday users are not forced through configuration controls.

Form customization is company scoped. A change in Yovel East must not alter other companies.

## Form Builder Data Model

Use one shared company form customization mechanism for ERP buildings. If the shared tables do not exist when Accounting/Finance is implemented, the Accounting/Finance foundation creates them.

Accounting/Finance form schema rows use `module_code = ACCOUNTING_FINANCE`.

### `project_company_form_schema`

Stores the active form definition per company, module, and record type.

Fields:

- `form_schema_key`
- `company_key`
- `company_key_hash`
- `module_code`
- `record_type`
- `schema_status`
- `schema_version`
- `schema_json`
- `created_by_admin_key`
- `updated_by_admin_key`
- timestamps

Unique key:

- `(company_key_hash, module_code, record_type, schema_version)`

The current active schema is the newest `ACTIVE` version for the record type.

### `project_company_form_schema_audit`

Stores each schema change for review and rollback.

Fields:

- `form_schema_audit_key`
- `company_key`
- `company_key_hash`
- `form_schema_key`
- `module_code`
- `record_type`
- `audit_action`
- `previous_schema_json`
- `next_schema_json`
- `created_by_admin_key`
- timestamp

### `project_company_form_custom_value`

Stores custom field values that are not mapped to physical core columns.

Fields:

- `custom_value_key`
- `company_key`
- `company_key_hash`
- `module_code`
- `record_type`
- `record_key`
- `field_key`
- `field_value`
- `created_by_admin_key`
- `updated_by_admin_key`
- timestamps

Unique key:

- `(company_key_hash, module_code, record_type, record_key, field_key)`

## Form Schema Rules

Each default schema is a JSON document with:

- `recordType`
- `version`
- `sections`
- `fields`
- `requiredSystemFields`
- `readonlySystemFields`

Each field includes:

- `key`
- `label`
- `type`
- `section`
- `required`
- `visible`
- `width`
- `sortOrder`
- `options`
- `storage`

The UI may reorder, rename, hide, or regroup fields, but it cannot remove system fields needed for persistence, ownership, audit, accounting integrity, or relational integrity. Required database fields remain required even if the admin tries to hide them.

For Accounting/Finance, protected system fields include record keys, company keys, company hash, posting and ownership fields, audit fields, and ledger-balance fields. Custom fields cannot directly alter posted ledger totals.

## Accounting/Finance Sections

### Chart of Accounts

The first complete section. The left panel shows the account tree/list with root type filters, account type filters, group/ledger state, status, and balance summary placeholders. The right panel shows account actions, import/export controls, drag and drop widgets, account setup shortcuts, and form builder controls.

Default account fields:

- Account number
- Account name
- Parent account
- Is group
- Root type
- Report type
- Account type
- Currency
- Tax rate
- Balance must be
- Freeze account
- Include in gross
- Status
- Notes

Account root types:

- `ASSET`
- `LIABILITY`
- `INCOME`
- `EXPENSE`
- `EQUITY`

Account statuses:

- `DRAFT`
- `ACTIVE`
- `FROZEN`
- `INACTIVE`
- `DELETED`

Chart of accounts actions:

- Create root or child account.
- Edit an account.
- Freeze or unfreeze an account.
- Move an account under a different parent when validation allows it.
- Soft delete an unused account.
- Restore default form schema.
- Reorder right-panel widgets with drag and drop.

The first slice may store balances as placeholders or calculated zeroes until journals, invoices, and payments are implemented. The UI must avoid presenting placeholder balances as posted accounting results.

### Cost Centers

Cost centers are a tree used to track operational spend and later budget controls.

Default fields:

- Cost center code
- Cost center name
- Parent cost center
- Is group
- Status
- Notes

### Accounting Dimensions

Accounting dimensions define additional reporting segments for ledger-impacting records.

Default fields:

- Dimension code
- Dimension name
- Reference type
- Applies to document types
- Mandatory for document types
- Disabled
- Notes

### Sales Invoices

Sales invoices create receivable records and later feed payments, ledgers, profit/loss, tax reports, and customer balances.

Default fields:

- Invoice number
- Customer
- Posting date
- Due date
- Currency
- Receivable account
- Income account
- Cost center
- Dimension values
- Status
- Total
- Tax total
- Grand total
- Notes

### Purchase Invoices

Purchase invoices create payable records and later feed payments, ledgers, profit/loss, tax reports, and supplier balances.

Default fields:

- Invoice number
- Supplier
- Posting date
- Due date
- Currency
- Payable account
- Expense account
- Cost center
- Dimension values
- Status
- Total
- Tax total
- Grand total
- Notes

### Journal Entries

Journal entries are balanced debit and credit adjustments.

Default fields:

- Journal entry number
- Posting date
- Entry type
- Finance book
- Reference number
- Reference date
- Status
- Lines
- Difference
- Notes

### Payment Entries

Payment entries record incoming and outgoing cash or bank movement.

Default fields:

- Payment entry number
- Payment type
- Party type
- Party
- Posting date
- Mode of payment
- Paid from account
- Paid to account
- Paid amount
- Received amount
- Reference document
- Status
- Notes

### Bank Accounts

Bank accounts represent company cash and bank instruments.

Default fields:

- Bank account code
- Bank
- Account name
- Account number
- Linked ledger account
- Currency
- Is default
- Status
- Notes

### Bank Reconciliation

Bank reconciliation matches bank transactions to payment and ledger records.

Default fields:

- Bank account
- Statement date
- From date
- To date
- Statement balance
- Cleared balance
- Difference
- Status
- Notes

### Budgets

Budgets track allowed amounts by fiscal period, account, cost center, and dimension.

Default fields:

- Budget code
- Fiscal year
- Cost center
- Account
- Dimension values
- Budget amount
- Action if exceeded
- Status
- Notes

### Period Closing

Period closing controls fiscal locks and closing vouchers.

Default fields:

- Closing code
- Fiscal year
- Posting date
- Closing account
- Cost center
- Status
- Notes

### Profit/Loss

Profit/loss is a report section. The left panel shows the report table and period filters. The right panel shows report settings, saved filters, export actions, and form/report layout customization.

### Balance Sheet

Balance sheet is a report section. The left panel shows assets, liabilities, and equity. The right panel shows filters, comparison settings, export actions, and report layout customization.

### Cash Flow

Cash flow is a report section. The left panel shows operating, investing, and financing movement. The right panel shows filters, indirect/direct display controls, export actions, and report layout customization.

### Tax Reports

Tax reports summarize taxable sales, purchases, withholding, and exports.

Default report controls:

- Tax period
- Tax category
- Branch
- Output format
- Include draft documents
- Notes

## Architecture

Extend the shared company-admin PHP route because `company/yovel-east/admin/index.php` delegates to `company/admin/index.php`.

Add an Accounting/Finance view beside the existing views:

- `dashboard`
- `platform`
- `hr`
- `accounting-finance`

Add Accounting/Finance section metadata in PHP so the sidebar, mobile navigation, page title, section descriptions, route validation, and queued states share one source of truth.

Use server-rendered pages and native POST forms, matching the current Company Platform and HR implementation. Do not introduce a new JavaScript application for Accounting/Finance in this cycle.

## Data Model

All Accounting/Finance tables must be company scoped by the active Yovel East company record. Persist both `company_key` and `company_key_hash` to match the current company-admin platform tables.

### `project_company_accounting_account`

Stores chart of accounts records.

Fields:

- `account_key`
- `company_key`
- `company_key_hash`
- `account_code`
- `account_number`
- `account_name`
- `parent_account_key`
- `root_type`
- `report_type`
- `account_type`
- `account_currency`
- `is_group`
- `tax_rate`
- `balance_must_be`
- `freeze_account`
- `include_in_gross`
- `account_status`
- `sort_order`
- `account_notes`
- `created_by_admin_key`
- `updated_by_admin_key`
- timestamps

Indexes and uniqueness:

- Unique `(company_key_hash, account_code)`
- Unique `(company_key_hash, account_number)` when account number is present
- Index `(company_key_hash, parent_account_key)`
- Index `(company_key_hash, root_type)`
- Index `(company_key_hash, account_type)`
- Index `(company_key_hash, account_status)`

The first slice can use adjacency-list parent keys and sort order. Nested-set fields can be added later only if report performance requires them.

Blank account numbers are stored as `NULL`, not an empty string, so optional account numbers do not collide under the unique account-number rule.

### Later Accounting/Finance Tables

Add these only when their sections are implemented:

- `project_company_accounting_cost_center`
- `project_company_accounting_dimension`
- `project_company_accounting_sales_invoice`
- `project_company_accounting_sales_invoice_line`
- `project_company_accounting_purchase_invoice`
- `project_company_accounting_purchase_invoice_line`
- `project_company_accounting_journal_entry`
- `project_company_accounting_journal_entry_line`
- `project_company_accounting_payment_entry`
- `project_company_accounting_bank_account`
- `project_company_accounting_bank_reconciliation`
- `project_company_accounting_budget`
- `project_company_accounting_period_closing`
- `project_company_accounting_tax_report_run`

Ledger and payment ledger tables should be introduced when the first posting workflow is implemented. Chart of accounts does not post ledger entries by itself.

## First Slice UI

The Chart of accounts screen uses the 12/8 two-panel layout.

Left main panel:

- Summary metrics for total accounts, active accounts, group accounts, ledger accounts, and frozen accounts.
- Account tree/list with account number, account name, root type, account type, parent, group/ledger flag, and status.
- Filters for root type, account type, status, and search text.
- Empty state that prompts the admin to create the first account.

Right feature/function panel:

- New account button.
- Add/edit account popup trigger.
- Form builder controls for the Account form.
- Drag and drop widget board for quick actions, account health, import/export, tree tools, and setup checklist.
- Restore default form schema action.

Add/edit popup:

- Data Entry tab for the active account form.
- Form Builder tab for field order, labels, visibility, required toggles, sections, width, and custom fields.
- Confirmation before saving form data or form schema changes.

## Data Flow

1. Company admin opens an Accounting/Finance route.
2. Server validates the company and current company-admin session.
3. Accounting/Finance schema is ensured.
4. Shared form schema tables are ensured.
5. Default form schemas are seeded for Accounting/Finance record types when missing.
6. Accounting/Finance data and active company-customized form schemas are loaded.
7. A POST save validates CSRF and company-admin access.
8. The save runs inside an ADODB transaction.
9. Inputs are validated from the active form schema and the protected system rules.
10. Custom fields are persisted to `project_company_form_custom_value`.
11. The core row is created or updated with parameterized SQL.
12. The exact saved row and custom values are read back and compared.
13. An audit record is written.
14. The request redirects back to the Accounting/Finance section and rehydrates from the database.

## Validation Rules

- Account code is required and must use uppercase letters, numbers, underscores, periods, or hyphens.
- Account name is required.
- Root type must be one of `ASSET`, `LIABILITY`, `INCOME`, `EXPENSE`, or `EQUITY`.
- Report type must match root type: balance sheet for asset, liability, and equity; profit/loss for income and expense.
- Parent account, when present, must belong to Yovel East.
- An account cannot be its own parent.
- Group accounts can have child accounts. Ledger accounts cannot have child accounts.
- Frozen accounts cannot be selected for new posting workflows after those workflows exist.
- Soft deletes set status to `DELETED`; no physical delete through the normal UI.
- Form builder cannot hide required system fields or remove protected fields.
- Custom fields cannot override core accounting fields or posted ledger values.

## Error Handling

Validation failures show the existing flash error and return to the Accounting/Finance section. Database failures roll back the transaction and show a concise error. Read-back mismatches are treated as failures and roll back before the user sees success.

Form schema save failures must not partially update the active schema. If schema JSON is invalid or violates protected field rules, the prior active schema remains in use.

## Testing

First slice verification:

- PHP lint for `company/admin/index.php` and `company/yovel-east/admin/index.php`.
- Authenticated HTTP check that `?view=accounting-finance&section=chart-of-accounts` renders.
- Authenticated HTTP check that the Accounting/Finance sidebar link points to the Accounting/Finance route, not the dashboard anchor.
- Create, update, freeze, unfreeze, and soft-delete a temporary account through the real Accounting/Finance POST path.
- Save an Account form schema change through the form builder.
- Add a custom Account form field and save a custom value on a temporary account.
- Verify committed account values, form schema values, and custom values with direct ADODB read-back.
- Confirm the Chart of accounts route rehydrates saved data after redirect.
- Desktop and mobile browser checks for the account list, right-panel widgets, popup tabs, and form builder controls.

Later slices repeat the same pattern with section-specific create, update, status, custom form, and report checks.

## Non-Goals

- Do not build the full Accounting/Finance building in the first implementation slice.
- Do not build ledger posting before invoices, journal entries, or payment entries.
- Do not present placeholder balances as final financial statements.
- Do not convert the whole ERP System sidebar into real modules yet.
- Do not replace the current company-admin PHP route with a frontend SPA.
- Do not let form builder changes alter physical database schema during normal company-admin use.
