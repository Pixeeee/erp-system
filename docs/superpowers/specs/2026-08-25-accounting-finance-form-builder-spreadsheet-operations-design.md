# Accounting/Finance Form Builder and Spreadsheet Operations Design

## Purpose

Extend the company Accounting/Finance workspace with the same dashboard-owned
form-builder pattern already used by HR, while adding practical spreadsheet
operations to Finance data tables and reports. The work remains scoped to
Accounting/Finance and follows the pinned ERPNext Accounting, Payables,
Receivables, and Financial Reports references.

## Approved Scope

The Finance workspace will provide two related but separate customization paths:

1. `Finance Form Builder` creates and edits company-scoped custom forms for any
   of the 15 Finance features.
2. `Customize Form` edits the active built-in ERP transaction or report-filter
   schema already stored in `project_company_form_schema`.

Finance data grids will provide practical spreadsheet behavior: inline editing
for fields that are safe to edit, search, multi-column sort, filters, grouping,
column visibility and reordering, saved views, controlled calculated columns,
and CSV import/export. This is not a general Excel clone. Arbitrary formulas,
macros, external links, and direct edits that bypass accounting validation are
out of scope.

## Reference and Layout

- ERPNext Accounting provides setup and chart structure.
- ERPNext Payables provides supplier and pay-out workflows.
- ERPNext Receivables provides customer and collection workflows.
- ERPNext Financial Reports provides report filters and statement presentation.
- The existing HR dashboard and HR Form Builder are the local interaction
  reference.

The Accounting/Finance workspace keeps the established two-panel ratio:

- Left `12fr`: primary table, report, filters, summaries, and record work.
- Right `4fr`: operations, saved views, import/export, Form Builder, Customize
  Form, and section-specific actions.

All add, edit, import, calculated-column, saved-view, and configuration flows
open in accessible modal dialogs. The page shell and finance section navigation
remain stable.

## Finance Form Builder

### Entry and Navigation

Every Finance section exposes one `Form Builder` launcher in the right panel.
It opens a dashboard-level modal without replacing the active Finance section.
The modal header contains the Finance title and close action. The body begins
with horizontally scrollable tabs for all 15 Finance features, followed by
`New Form` and `Existing Forms` mode controls.

### New Form

New forms begin with an empty canvas for the selected Finance feature. The
editor uses the same three-area workbench as HR:

- `Field Toolbox`: text, paragraph, number, currency, date, dropdown,
  checkboxes, account reference, party reference, and section header.
- `Form Layout`: ordered question cards with drag/drop and keyboard move
  controls.
- `Field Properties`: label, key, help text, type, options, required state,
  numeric precision, and validation settings.

The form metadata includes title, description, selected Finance feature,
status, and version. Preview renders the respondent-facing form without
leaving the modal.

### Existing Forms

Existing Forms separates two sources:

- `Built-in Forms`: the live section schemas from
  `project_company_form_schema`, grouped by their schema sections with current
  field counts. Opening one launches the built-in field editor.
- `Custom Forms`: forms created by administrators for the selected Finance
  feature. Opening one loads its saved metadata and question schema into the
  custom form editor.

No HR forms or HR targets appear in the Finance builder. Finance custom forms
cannot silently modify built-in transaction schemas.

## Spreadsheet Operations

### Grid Behavior

Finance record and report tables use semantic `<table>` markup with sticky
headers and a sticky identifying column when horizontal scrolling is needed.
The grid supports:

- Global search and per-column filters.
- Ascending and descending multi-column sort.
- Grouping by one supported field with collapsible groups and subtotals.
- Column visibility, width, and drag/keyboard reordering.
- Row selection for allowed bulk actions.
- Copying visible cell text and exporting the current filtered view.
- Inline editing only for allow-listed fields on record types with a complete
  server save path. Report rows and accounting totals remain read-only.

The grid distinguishes first-run empty, filtered empty, and error states.

### Calculated Columns

Calculated columns are display-layer definitions stored per company, Finance
section, and saved view. They do not alter journal balances or transaction
records. Supported operations are allow-listed:

- Aggregates: `SUM`, `AVERAGE`, `MIN`, `MAX`, `COUNT`.
- Row expressions: numeric field references, `+`, `-`, `*`, `/`, parentheses,
  and `IF(condition, value_if_true, value_if_false)`.
- Comparisons inside `IF`: `=`, `!=`, `>`, `>=`, `<`, and `<=`.

Formula parsing must use a tokenizer/parser with an allow-listed grammar. It
must never use `eval`, dynamic PHP execution, SQL expression interpolation, or
JavaScript function construction. Division by zero and invalid references
render a visible cell error without breaking the rest of the grid.

### Import and Export

CSV import opens a modal with file selection, header mapping, validation
preview, and an explicit confirmation before writing. Import is enabled only
for Finance record types whose server-side create/update workflow exists. Each
accepted row uses the same validation and transactional persistence contract as
the native record form; invalid rows are rejected with row-specific messages.

CSV export serializes the current filtered and visible-column view. Export is a
read-only action and never includes hidden technical keys unless explicitly
selected by an administrator.

## Persistence Model

Reuse the existing Finance form-schema tables for built-in form customization.
Add Finance-owned, company-scoped tables for custom forms and spreadsheet
configuration:

- `project_company_finance_builder_form`
- `project_company_finance_builder_form_version`
- `project_company_finance_grid_view`
- `project_company_finance_grid_column`
- `project_company_finance_grid_formula`

Each table includes a stable UUID key, company key and hash, Finance section or
record type, status, creator/updater keys, timestamps, and uniqueness/indexes
appropriate to its business key. Form versions preserve immutable schema JSON.
Grid views persist filter, sort, grouping, and visible-column configuration;
formula definitions are stored separately so they can be validated and audited.

All writes use the existing ADODB connection, parameterized SQL, explicit
transactions, authorization and CSRF checks, audit records, direct read-back
verification before commit, and server-backed rehydration after redirect.

## Request and Data Flow

1. The controller resolves the authenticated company and active Finance
   section.
2. The Finance module loads built-in schemas, custom forms, saved grid views,
   calculated columns, and section records.
3. Query parameters select builder mode, target section, built-in schema, custom
   form, or saved grid view; all values are allow-listed and company-scoped.
4. A valid form first opens the shared confirmation dialog.
5. The Finance module validates and writes through one transaction, records the
   audit event, reads the saved row and JSON definition back, then commits.
6. The redirect restores the active Finance section, modal context, and saved
   selection from database-backed state.

## Error Handling

- Invalid target sections, record types, field keys, formula references, and
  saved-view keys fail closed to the active company and Finance allow-list.
- Persistence failures roll back and display one accessible informational
  modal; successful commits display one dismissing success toast.
- Formula errors remain scoped to the affected calculated cell or definition.
- Import validation reports row and field errors before any database write.
- A failed bulk import rolls back the complete import. The first implementation
  uses all-or-nothing import only and does not expose partial-import behavior.

## Accessibility and Responsive Behavior

- Form Builder and operation dialogs use a header, independently scrollable
  body, and footer with persistent close/cancel controls.
- Feature tabs and wide grids support touch dragging without visible browser
  scrollbars, plus keyboard navigation and visible focus states.
- Table headers expose sort direction and use real header cells.
- Drag operations have move-up and move-down button equivalents.
- On narrow screens, the right operations panel follows the main panel and the
  grid owns horizontal scrolling without widening the page.

## Implementation Boundaries

- Finance code remains under `company/admin/modules/accounting-finance/`.
- Shared shell behavior stays in shared assets only when it is truly reusable.
- HR tables, routes, forms, and labels are not changed.
- Existing Finance URLs, POST actions, account keys, and schema records remain
  compatible.
- Spreadsheet preferences never become a source of truth for accounting data.

## Verification

- Run PHP lint for all company-admin PHP files.
- Run `php tests/company-admin-modular-architecture.php`.
- Route-check all 15 Finance sections and verify the 12/4 layout, one Form
  Builder launcher, one Customize Form launcher, and no fatal output.
- Browser-check Finance Form Builder new/existing modes, built-in/custom
  separation, feature switching, modal focus/escape/scroll behavior, and
  desktop/mobile layouts.
- Exercise custom form create and update with database read-back and reload.
- Exercise saved grid view and formula create/update with read-back and reload.
- Verify invalid CSRF, invalid company scope, malformed formulas, unknown field
  references, divide-by-zero display, and transaction rollback behavior.
- Exercise CSV export and an import preview; enable committed import tests only
  for record types with complete persistence workflows.
- Run the relevant frontend or JavaScript syntax/build checks.
