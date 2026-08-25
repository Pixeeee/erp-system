# Accounting/Finance Form Builder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an HR-pattern Finance Form Builder for all 15 Accounting/Finance features while preserving the existing built-in `Customize Form` workflow.

**Architecture:** Keep the server-rendered modular PHP application. Add Finance-owned form schema, normalization, persistence, and rendering files; load them through the existing Finance module and controller. Custom forms and immutable versions remain company-scoped and separate from built-in `project_company_form_schema` records.

**Tech Stack:** PHP 8, ADODB/MySQL, server-rendered HTML, Tailwind/shadcn tokens, vanilla JavaScript, PHP route and database tests.

## Global Constraints

- Keep all Finance behavior under `company/admin/modules/accounting-finance/` except shared asset hooks and controller wiring.
- Preserve all existing Finance URLs, POST action names, account keys, and built-in schema records.
- Keep the `12fr / 4fr` Finance layout and modal-only add/edit/configuration standard.
- Use ADODB parameterized SQL, explicit transactions, audit logging, direct read-back verification, and server rehydration for every write.
- Custom Finance forms must never read or modify HR builder tables.
- `Customize Form` continues to edit built-in ERP schemas; `Finance Form Builder` creates separate custom forms.

---

### Task 1: Finance Builder Schema and Normalization

**Files:**
- Create: `company/admin/modules/accounting-finance/forms.php`
- Modify: `company/admin/bootstrap/app.php`
- Test: `tests/accounting-finance-form-builder.php`

**Interfaces:**
- Consumes: `bx_db()`, `yovel_admin_db_execute()`, `yovel_admin_accounting_finance_sections()`, `yovel_admin_slug()`, `bx_uuid()`.
- Produces: `yovel_admin_finance_builder_schema(): void`, `yovel_admin_finance_builder_target_sections(): array`, `yovel_admin_finance_builder_target_section(string): string`, `yovel_admin_normalize_finance_builder_schema(string): array`.

- [ ] **Step 1: Write a failing schema and normalization test**

```php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/foundation.php';
require dirname(__DIR__) . '/company/admin/core/functions.php';
require dirname(__DIR__) . '/company/admin/modules/accounting-finance/functions.php';
require dirname(__DIR__) . '/company/admin/modules/accounting-finance/forms.php';

yovel_admin_finance_builder_schema();
$tables = ['project_company_finance_builder_form', 'project_company_finance_builder_form_version'];
foreach ($tables as $table) {
    if ((int) bx_db()->GetOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]) !== 1) {
        throw new RuntimeException($table . ' was not created.');
    }
}
$schema = yovel_admin_normalize_finance_builder_schema(json_encode([
    'questions' => [['key' => 'amount', 'label' => 'Amount', 'type' => 'CURRENCY', 'required' => true, 'precision' => 2]],
], JSON_THROW_ON_ERROR));
if (($schema['questions'][0]['type'] ?? '') !== 'CURRENCY') {
    throw new RuntimeException('Currency questions were not normalized.');
}
```

- [ ] **Step 2: Run the test and confirm the missing implementation failure**

Run: `php tests/accounting-finance-form-builder.php`

Expected: non-zero exit because `forms.php` or the Finance builder functions do not exist.

- [ ] **Step 3: Implement the schema and strict schema normalizer**

Create both InnoDB tables with UUID keys, company scope, target section, title, description, status, immutable version rows, audit keys, timestamps, and indexes. Normalize at most 80 questions with this allow-list:

```php
$allowedTypes = [
    'SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'CURRENCY', 'DATE',
    'DROPDOWN', 'CHECKBOXES', 'ACCOUNT', 'PARTY', 'SECTION',
];

return [
    'version' => 1,
    'questions' => $normalizedQuestions,
];
```

Validate question keys, 180-character labels, 1,000-character help text, up to 30 options, precision from `0` through `6`, required state, and deterministic order. Account and party references store only field metadata, never selected business data.

- [ ] **Step 4: Load the form module and run the test**

Add this before `functions.php` consumers dispatch requests:

```php
require_once dirname(__DIR__) . '/modules/accounting-finance/functions.php';
require_once dirname(__DIR__) . '/modules/accounting-finance/forms.php';
```

Run: `php tests/accounting-finance-form-builder.php`

Expected: exit `0` with both tables present and the currency field normalized.

- [ ] **Step 5: Commit the schema slice**

```bash
git add company/admin/bootstrap/app.php company/admin/modules/accounting-finance/forms.php tests/accounting-finance-form-builder.php
git commit -m "Add Finance form builder schema"
```

### Task 2: Transactional Custom Form Create and Update

**Files:**
- Modify: `company/admin/modules/accounting-finance/forms.php`
- Modify: `company/admin/bootstrap/controller.php`
- Modify: `tests/accounting-finance-form-builder.php`

**Interfaces:**
- Consumes: Task 1 schema and normalizer, authenticated company/admin arrays, `bx_csrf_validate()`, `bx_audit()`.
- Produces: `yovel_admin_finance_builder_forms(array): array`, `yovel_admin_finance_builder_form_version(array,string,string): ?array`, `yovel_admin_save_finance_builder_form(array,array): string` and POST action `save_finance_builder_form`.

- [ ] **Step 1: Add a failing create/update/read-back test**

Use a temporary company-scoped UUID form key and call a persistence helper with `sales-invoices`, then call it again with the same key and a changed title. Assert one form row, two immutable versions, the stable form key, updated metadata, and the exact normalized JSON read back from the database. Delete only the temporary rows in a `finally` block.

Run: `php tests/accounting-finance-form-builder.php`

Expected: non-zero exit because the persistence helper is absent.

- [ ] **Step 2: Implement one transactional upsert and immutable version insert**

Use these signatures:

```php
function yovel_admin_persist_finance_builder_form(
    object $db,
    array $company,
    array $admin,
    array $input
): array

function yovel_admin_save_finance_builder_form(array $company, array $admin): string
```

Within one transaction: lock the existing form row when a valid key is supplied, upsert metadata, insert the next version with checksum and schema JSON, call `bx_audit()`, read both rows back, compare every persisted field, then commit. Roll back on every exception and on every failed `Execute()`.

- [ ] **Step 3: Add the allow-listed controller action and modal-preserving redirect**

Dispatch `save_finance_builder_form` with the existing Accounting/Finance action family. Redirect to:

```php
'view=accounting-finance&section=' . rawurlencode($section)
    . '&finance_builder=1&builder_mode=existing&builder_target=' . rawurlencode($target)
    . '&form=' . rawurlencode($savedFormKey)
```

On failure, preserve the active section and target without exposing SQL details.

- [ ] **Step 4: Run persistence and syntax verification**

Run:

```bash
php tests/accounting-finance-form-builder.php
php -l company/admin/modules/accounting-finance/forms.php
php -l company/admin/bootstrap/controller.php
```

Expected: all commands exit `0`.

- [ ] **Step 5: Commit the persistence slice**

```bash
git add company/admin/modules/accounting-finance/forms.php company/admin/bootstrap/controller.php tests/accounting-finance-form-builder.php
git commit -m "Persist Finance custom forms"
```

### Task 3: Controller State and Finance Builder Modal

**Files:**
- Create: `company/admin/modules/accounting-finance/views/form-builder.php`
- Modify: `company/admin/bootstrap/controller.php`
- Modify: `company/admin/modules/accounting-finance/functions.php`
- Modify: `company/admin/modules/accounting-finance/views/workspace.php`
- Modify: `tests/accounting-finance-form-builder.php`

**Interfaces:**
- Consumes: Finance custom forms, active built-in form schemas, 15 Finance section records.
- Produces: `$financeBuilderForms`, `$activeFinanceBuilderTarget`, `$activeFinanceBuilderMode`, `$activeFinanceBuilderForm`, `$activeFinanceBuiltInSections`, `$activeFinanceBuiltInSection`, `$activeFinanceBuilderOpen` and the modal partial.

- [ ] **Step 1: Add failing route assertions**

Render each Finance route with a test-authenticated request and assert:

```php
str_contains($html, 'data-finance-form-builder-open')
str_contains($html, 'id="yovel-finance-builder-modal"')
substr_count($html, 'data-finance-form-builder-open') === 1
str_contains($html, 'Built-in Forms')
str_contains($html, 'Custom Forms')
```

Expected before implementation: at least one assertion fails.

- [ ] **Step 2: Load builder records through Finance data**

Extend `yovel_admin_accounting_finance_data()` with:

```php
'builderForms' => yovel_admin_finance_builder_forms($company),
```

Calculate active builder state only when `view=accounting-finance`, validate every query value against Finance sections and company-scoped records, and force custom form records to their stored target.

- [ ] **Step 3: Render one Finance Form Builder launcher per section**

Replace duplicate section-specific launch behavior with one link:

```html
<a data-finance-form-builder-open
   href="./?view=accounting-finance&amp;section=...&amp;finance_builder=1&amp;builder_mode=existing&amp;builder_target=...">
  Form Builder
</a>
```

Keep the existing `Customize Form` button as a distinct action.

- [ ] **Step 4: Build the modal partial**

Render a dialog with exactly three structural regions: header, scrollable body, footer. The body contains:

- A swipeable 15-feature Finance tab row.
- `New Form` and `Existing Forms` controls.
- Existing mode with separate built-in schema-section cards and custom form cards.
- New/edit mode with metadata controls, Field Toolbox, Form Layout, Field Properties, preview, save action, duplicate/delete/move controls, and hidden normalized `schema_json`.

Use semantic buttons, labels, focusable controls, and move-up/move-down alternatives for dragging. Do not nest bordered cards inside the dialog surface.

- [ ] **Step 5: Run route and PHP checks**

Run:

```bash
php tests/accounting-finance-form-builder.php
find company/admin -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/company-admin-modular-architecture.php
```

Expected: all commands exit `0`, all 15 routes render one launcher, and built-in/custom groups are present.

- [ ] **Step 6: Commit the modal slice**

```bash
git add company/admin/bootstrap/controller.php company/admin/modules/accounting-finance/functions.php company/admin/modules/accounting-finance/views/workspace.php company/admin/modules/accounting-finance/views/form-builder.php tests/accounting-finance-form-builder.php
git commit -m "Add Finance form builder workspace"
```

### Task 4: Builder Interaction, Accessibility, and Browser Verification

**Files:**
- Modify: `company/admin/views/partials/scripts.php`
- Modify: `company/admin/assets/css/admin.css`
- Modify: `tests/accounting-finance-form-builder.php`

**Interfaces:**
- Consumes: modal data attributes from Task 3.
- Produces: question creation, selection, property synchronization, duplication, deletion, ordering, preview, focus trap, Escape close, and responsive three-panel layout.

- [ ] **Step 1: Add static behavior assertions**

Assert the rendered/script sources contain the stable hooks:

```php
$requiredHooks = [
    'data-finance-google-builder',
    'data-finance-builder-preset',
    'data-finance-builder-question',
    'data-finance-builder-settings-panel',
    'data-finance-builder-preview',
];
```

Run the focused test and confirm failure before the interaction code exists.

- [ ] **Step 2: Implement builder state synchronization**

Use DOM APIs and JSON serialization only. Adding a toolbox item creates a question card immediately. Selection fills Field Properties; each property input updates the card and hidden JSON. Move, duplicate, and delete update deterministic `order` values. Preview uses disabled native controls and escaped text; do not construct executable code from schema values.

- [ ] **Step 3: Implement modal keyboard and scroll behavior**

Focus the close control or first tab on open, trap Tab within the dialog, close on Escape or backdrop navigation, restore focus to the launcher, and keep header/footer visible while only the body scrolls. Feature tabs retain hidden-scrollbar touch dragging and visible keyboard focus.

- [ ] **Step 4: Add responsive Finance builder styling**

Use a three-column desktop shell with dividers and one-column mobile stacking. Keep `Field Toolbox`, `Form Layout`, and `Field Properties` aligned at the same top row. Use existing neutral tokens, 8px-or-smaller radii, thin borders, and no nested surface cards.

- [ ] **Step 5: Run syntax, route, and browser checks**

Run:

```bash
php tests/accounting-finance-form-builder.php
php tests/company-admin-modular-architecture.php
php -l company/admin/views/partials/scripts.php
```

Then browser-check desktop and mobile widths: open/close, Escape, focus return, all feature tabs, New/Existing modes, built-in/custom separation, add/duplicate/delete/move question, preview, confirmation, and save/reload.

Expected: no console errors, no page-level horizontal overflow, only the modal body scrolls, and the saved custom form rehydrates after reload.

- [ ] **Step 6: Commit the completed Finance Form Builder**

```bash
git add company/admin/views/partials/scripts.php company/admin/assets/css/admin.css tests/accounting-finance-form-builder.php
git commit -m "Complete Finance form builder interactions"
```

