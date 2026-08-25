# Accounting/Finance Spreadsheet Operations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add company-scoped saved grid views, controlled calculated columns, spreadsheet-style table operations, and safe CSV import/export to Accounting/Finance.

**Architecture:** Add a Finance-owned grid module containing persistence and a tokenizer/parser/evaluator with no dynamic execution. Render semantic tables and operation modals through focused Finance view partials. Client behavior manages visible rows and columns; database records remain authoritative and record writes continue through validated Finance persistence paths.

**Tech Stack:** PHP 8, ADODB/MySQL, semantic HTML tables, vanilla JavaScript, CSV browser/File APIs plus PHP `fgetcsv`, PHP and browser tests.

## Global Constraints

- Keep the left main workspace at `12fr` and the right operations panel at `4fr`.
- All grid configuration, formulas, cell edits, and import mapping use modal dialogs.
- Never use PHP `eval`, JavaScript `eval`, `Function`, SQL formula interpolation, or executable schema content.
- Saved views and formulas are company- and Finance-section scoped and never become accounting source data.
- Report rows and accounting totals are read-only.
- CSV import is all-or-nothing and enabled only for a record type with a complete transactional save path.
- Preserve real `<table>`, `<th scope="col">`, sort direction, keyboard focus, first-run empty, filtered empty, and error states.

---

### Task 1: Grid Persistence Schema and Saved Views

**Files:**
- Create: `company/admin/modules/accounting-finance/grid.php`
- Modify: `company/admin/bootstrap/app.php`
- Modify: `company/admin/bootstrap/controller.php`
- Test: `tests/accounting-finance-spreadsheet-operations.php`

**Interfaces:**
- Consumes: company/admin context and Finance section metadata.
- Produces: `yovel_admin_finance_grid_schema(): void`, `yovel_admin_finance_grid_views(array,string): array`, `yovel_admin_save_finance_grid_view(array,array): string`, `yovel_admin_delete_finance_grid_view(array,array): string`.

- [ ] **Step 1: Write failing table and saved-view tests**

Assert these tables exist after schema setup:

```php
$tables = [
    'project_company_finance_grid_view',
    'project_company_finance_grid_column',
    'project_company_finance_grid_formula',
];
```

Create a temporary `chart-of-accounts` view, update it with the same stable key, and assert the read-back includes exact search, sort, group, visible column, width, and order settings.

- [ ] **Step 2: Run the test and confirm failure**

Run: `php tests/accounting-finance-spreadsheet-operations.php`

Expected: non-zero exit because the grid module does not exist.

- [ ] **Step 3: Implement schema and transactional saved-view upsert**

Use UUID keys, company scope, section, title, default flag, status, audit keys, timestamps, and unique `(company_key_hash, section_key, view_title)` identity. Store individual core/custom column settings in the column table. Normalize sort to `ASC|DESC`, widths to `80..640`, and column keys against the active built-in schema plus validated formula keys.

- [ ] **Step 4: Wire save/delete actions and redirects**

Add allow-listed actions `save_finance_grid_view` and `delete_finance_grid_view`. Save/delete within explicit transactions, audit, read back before commit, then redirect to the active Finance section with `grid_view=<uuid>`.

- [ ] **Step 5: Run tests and commit**

```bash
php tests/accounting-finance-spreadsheet-operations.php
php -l company/admin/modules/accounting-finance/grid.php
php -l company/admin/bootstrap/controller.php
git add company/admin/bootstrap/app.php company/admin/bootstrap/controller.php company/admin/modules/accounting-finance/grid.php tests/accounting-finance-spreadsheet-operations.php
git commit -m "Add Finance saved grid views"
```

### Task 2: Controlled Formula Parser and Calculated Columns

**Files:**
- Modify: `company/admin/modules/accounting-finance/grid.php`
- Modify: `company/admin/bootstrap/controller.php`
- Modify: `tests/accounting-finance-spreadsheet-operations.php`

**Interfaces:**
- Consumes: active schema numeric fields and row arrays.
- Produces: `yovel_admin_finance_formula_tokens(string): array`, `yovel_admin_finance_formula_parse(string,array): array`, `yovel_admin_finance_formula_evaluate(array,array): array`, `yovel_admin_save_finance_grid_formula(array,array): string`.

- [ ] **Step 1: Add failing parser/evaluator tests**

Test exact results for:

```php
['formula' => '[tax_rate] * 2', 'row' => ['tax_rate' => 6], 'value' => 12.0]
['formula' => 'IF([tax_rate] > 5, [tax_rate], 0)', 'row' => ['tax_rate' => 6], 'value' => 6.0]
['formula' => '[sort_order] / 0', 'error' => 'DIVIDE_BY_ZERO']
['formula' => '[unknown] + 1', 'error' => 'UNKNOWN_FIELD']
```

Also reject function names outside `SUM`, `AVERAGE`, `MIN`, `MAX`, `COUNT`, and `IF`; reject quotes, semicolons, object/property access, and expressions longer than 500 characters.

- [ ] **Step 2: Run focused tests and confirm failure**

Run: `php tests/accounting-finance-spreadsheet-operations.php`

Expected: parser function missing or assertions failing.

- [ ] **Step 3: Implement tokenizer, recursive-descent parser, and evaluator**

Tokenize numbers, bracketed allow-listed field names, operators, comparison operators, commas, parentheses, and uppercase function identifiers. Parse with precedence levels: comparison, additive, multiplicative, unary, primary/function. Return an AST array; never compile or execute source text.

Evaluator result contract:

```php
['ok' => true, 'value' => 12.0, 'error' => null]
['ok' => false, 'value' => null, 'error' => 'DIVIDE_BY_ZERO']
```

Aggregate functions receive the visible row set at render time; row formulas receive one row.

- [ ] **Step 4: Persist validated formula definitions**

Store key, label, expression, output format (`NUMBER|CURRENCY|PERCENT`), precision `0..6`, sort order, and status. Parse before opening the transaction, upsert by stable key, audit, read back exact values, then commit. Add `save_finance_grid_formula` and `delete_finance_grid_formula` actions.

- [ ] **Step 5: Run security tests and commit**

```bash
php tests/accounting-finance-spreadsheet-operations.php
rg -n "eval\(|new Function|Function\(" company/admin/modules/accounting-finance company/admin/views/partials/scripts.php
git add company/admin/bootstrap/controller.php company/admin/modules/accounting-finance/grid.php tests/accounting-finance-spreadsheet-operations.php
git commit -m "Add controlled Finance formulas"
```

Expected: test exit `0`; the source scan returns no executable formula construction.

### Task 3: Semantic Finance Grid and Operations Panel

**Files:**
- Create: `company/admin/modules/accounting-finance/views/grid.php`
- Create: `company/admin/modules/accounting-finance/views/grid-operations.php`
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Modify: `company/admin/modules/accounting-finance/views/workspace.php`
- Modify: `company/admin/bootstrap/controller.php`
- Test: `tests/accounting-finance-spreadsheet-operations.php`

**Interfaces:**
- Consumes: Finance accounts, active built-in schema, saved view, calculated columns, evaluated values.
- Produces: one semantic grid in the left panel and operations/saved views in the right panel for every Finance section.

- [ ] **Step 1: Add failing rendered-grid assertions**

Require each Finance section to render:

```php
$markers = [
    'data-finance-grid',
    'data-finance-grid-search',
    'data-finance-grid-columns-open',
    'data-finance-grid-formula-open',
    'data-finance-grid-export',
    'scope="col"',
];
```

For Chart of Accounts also assert account rows use `data-finance-grid-row` and cells expose allow-listed `data-column-key` values.

- [ ] **Step 2: Load saved view and formula context**

Resolve `grid_view` only from the active company and section. If absent, select the section default or create an in-memory default view. Build the column registry from built-in schema fields and formula definitions; never accept a query-provided column list.

- [ ] **Step 3: Render the grid partial**

Use a real table, sticky header, sticky first identifying column, visible sort indicator, row selection checkbox, data attributes for filtering/sorting, and formula cells with scoped error labels. Render three distinct states: first-run no records, filtered no matches with Clear Filters, and server error with retry guidance.

Chart of Accounts renders real records. Sections without a completed record store render a schema-shaped empty grid and keep create/import disabled with an explicit unavailable state instead of broken controls.

- [ ] **Step 4: Render the right operations panel and modals**

Include compact controls for Saved Views, Columns, Sort & Filter, Group, Calculated Column, Export, and Import. Each configuration control opens a modal with header/body/footer. Keep `Finance Form Builder` and `Customize Form` launchers in the same right panel without duplicating them.

- [ ] **Step 5: Run route and architecture tests**

```bash
php tests/accounting-finance-spreadsheet-operations.php
php tests/company-admin-modular-architecture.php
find company/admin -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: all 15 Finance routes render the markers without fatal output and keep the `12fr / 4fr` layout.

- [ ] **Step 6: Commit the grid UI**

```bash
git add company/admin/bootstrap/controller.php company/admin/modules/accounting-finance/functions.php company/admin/modules/accounting-finance/views/workspace.php company/admin/modules/accounting-finance/views/grid.php company/admin/modules/accounting-finance/views/grid-operations.php tests/accounting-finance-spreadsheet-operations.php
git commit -m "Add Finance spreadsheet workspace"
```

### Task 4: Client-Side Table Operations and Saved View Rehydration

**Files:**
- Modify: `company/admin/views/partials/scripts.php`
- Modify: `company/admin/assets/css/admin.css`
- Modify: `tests/accounting-finance-spreadsheet-operations.php`

**Interfaces:**
- Consumes: data attributes and server-backed saved view from Task 3.
- Produces: search, per-column filter, multi-sort, grouping, column visibility/order, copy, export, modal state, and responsive grid scrolling.

- [ ] **Step 1: Add static script hook assertions**

Require hooks for `data-finance-grid-row`, `data-finance-grid-cell`, `data-finance-grid-sort`, `data-finance-grid-filter`, `data-finance-grid-group`, `data-finance-grid-column-toggle`, and `data-finance-grid-export`.

- [ ] **Step 2: Implement deterministic row projection**

Build one `applyFinanceGridState()` pipeline: read server-backed state, apply global/per-column filters, stable multi-sort, grouping, row visibility, group subtotals, and filtered-empty state. Preserve row order ties by original server index. Update `aria-sort` and result counts after each operation.

- [ ] **Step 3: Implement column and export behavior**

Column move buttons and drag/drop update hidden order controls; visibility toggles update headers and cells without changing stored records. Export uses the current visible rows and columns, quotes RFC 4180 CSV values, emits a UTF-8 BOM, and downloads a section-named `.csv` file.

- [ ] **Step 4: Implement modal and responsive behavior**

Use the shared confirmation guard for persisted view/formula forms. Trap focus, restore the triggering control, close on Escape, and keep modal body scrolling independent. The grid owns horizontal touch scrolling and keeps the first identity column visible on desktop.

- [ ] **Step 5: Run script and browser verification**

Extract the rendered inline JavaScript and run `node --check`. Browser-check search, filtered empty, Clear Filters, sort direction, grouping, column hide/reorder, CSV export content, saved-view save/reload, formula error display, keyboard focus, and mobile overflow.

- [ ] **Step 6: Commit the interaction layer**

```bash
git add company/admin/views/partials/scripts.php company/admin/assets/css/admin.css tests/accounting-finance-spreadsheet-operations.php
git commit -m "Complete Finance grid operations"
```

### Task 5: Chart of Accounts Cell Editor and CSV Import

**Files:**
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Modify: `company/admin/modules/accounting-finance/grid.php`
- Modify: `company/admin/modules/accounting-finance/views/grid.php`
- Modify: `company/admin/modules/accounting-finance/views/grid-operations.php`
- Modify: `company/admin/bootstrap/controller.php`
- Modify: `company/admin/views/partials/scripts.php`
- Modify: `tests/accounting-finance-spreadsheet-operations.php`

**Interfaces:**
- Consumes: complete Chart of Accounts account persistence and active schema.
- Produces: `yovel_admin_save_finance_grid_cell(array,array): string`, `yovel_admin_import_finance_accounts(array,array): string`, modal cell editor, CSV mapping preview, and all-or-nothing account import.

- [ ] **Step 1: Add failing cell allow-list and CSV transaction tests**

Allow compact cell edits only for `account_name`, `account_type`, `account_currency`, `tax_rate`, `sort_order`, and `account_notes`. Assert attempts to edit `account_key`, company keys, root type, report type, or balance controls through the cell action are rejected.

Import two valid temporary accounts and assert both commit. Repeat with one invalid row and assert neither row commits. Clean up only test account keys in `finally`.

- [ ] **Step 2: Refactor account persistence behind a transaction-aware helper**

Use:

```php
function yovel_admin_persist_account(
    object $db,
    array $company,
    array $admin,
    array $input,
    bool $writeAudit = true
): array
```

The existing single-record save begins a transaction, calls the helper, reads back, and commits. Import begins one transaction, calls the same helper for every validated row, records audit entries, verifies every row, then commits once.

- [ ] **Step 3: Implement modal cell editing**

Clicking an editable grid cell opens a compact modal containing the field label, current value, native validation, Cancel, and Save. The POST includes fixed section, record key, and allow-listed field key. Server code reloads the full account row, changes only the selected field, and persists through `yovel_admin_persist_account()`.

- [ ] **Step 4: Implement CSV mapping preview and all-or-nothing import**

The modal reads a local CSV, parses quoted records, shows the first 20 rows, maps headers only to active account schema fields, and serializes validated rows to a hidden JSON field after explicit confirmation. Server code caps import at 500 rows and 2 MB, validates every field before `BeginTrans()`, then performs the all-or-nothing write.

- [ ] **Step 5: Run complete verification**

```bash
php tests/accounting-finance-form-builder.php
php tests/accounting-finance-spreadsheet-operations.php
php tests/company-admin-modular-architecture.php
find company/admin -name '*.php' -print0 | xargs -0 -n1 php -l
```

Browser-check cell-edit cancel/save/reload, rejected protected fields, CSV preview, invalid-row rollback, valid import, account search/sort/group, formula values, export, and no console errors.

- [ ] **Step 6: Run UI taste review and commit**

Run:

```bash
python3 /home/kimz/.codex/skills/ui-ux-maxi/scripts/search.py "finance spreadsheet modal dashboard" --domain taste -n 12
```

Apply any relevant findings without changing the approved neutral ERPNext direction, then commit:

```bash
git add company/admin/bootstrap/controller.php company/admin/modules/accounting-finance company/admin/views/partials/scripts.php company/admin/assets/css/admin.css tests/accounting-finance-spreadsheet-operations.php
git commit -m "Add Finance cell editing and CSV import"
```

