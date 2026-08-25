# HR Form Builder Row and Column Layout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add persistent, draggable one-, two-, and three-column rows to the HR Dashboard Form Builder and render the same responsive layout in Build, Preview, and employee submissions.

**Architecture:** Upgrade the normalized form schema from version 1 to version 2 by retaining canonical questions and adding ordered rows that reference stable question keys. The server owns validation and backward-compatible normalization; the dashboard builder owns row and column interactions; preview and submission rendering consume the same normalized layout.

**Tech Stack:** PHP 8, ADODB-backed existing HR form persistence, server-rendered PHP templates, vanilla JavaScript drag and drop, project shadcn-style CSS tokens, Material Symbols.

## Global Constraints

- Keep the HR Dashboard as the only custom HR Form Builder entry point.
- Support exactly one, two, or three equal-width columns per row.
- Stack all columns into one reading-order column below the tablet breakpoint.
- Preserve all existing question keys, answer persistence, confirmation, audit, and form-version behavior.
- Treat section questions as full-width rows.
- Do not add arbitrary widths, nested grids, branching, formulas, or page-based surveys.
- Do not modify unrelated dirty worktree files.

---

### Task 1: Version 2 Schema Normalization

**Files:**
- Modify: `company/admin/modules/hr/forms.php:87-157`
- Modify: `tests/hr-database-driven-forms.php:357-385`

**Interfaces:**
- Consumes: `yovel_admin_hr_builder_question_key(string): string` and JSON submitted through `schema_json`.
- Produces: `yovel_admin_normalize_hr_builder_schema(string): array{version:int,questions:list<array>,rows:list<array>}` where every row has `key` and one to three columns containing `key` and `question_keys`.

- [ ] **Step 1: Add failing normalization assertions**

Add assertions that a version 1 schema becomes one row per question, a two-column version 2 row retains both question references, duplicate references occur only once, unknown references are removed, unreferenced questions are appended, and a `SECTION` question receives its own one-column row.

```php
$layoutSchema = yovel_admin_normalize_hr_builder_schema(json_encode([
    'version' => 2,
    'questions' => [
        ['key' => 'first_name', 'label' => 'First Name', 'type' => 'SHORT_TEXT'],
        ['key' => 'last_name', 'label' => 'Last Name', 'type' => 'SHORT_TEXT'],
    ],
    'rows' => [[
        'key' => 'name_row',
        'columns' => [
            ['key' => 'first_column', 'question_keys' => ['first_name']],
            ['key' => 'last_column', 'question_keys' => ['last_name']],
        ],
    ]],
], JSON_THROW_ON_ERROR));
hr_form_assert($layoutSchema['version'] === 2, 'HR form schema version was not upgraded.');
hr_form_assert(count($layoutSchema['rows'][0]['columns']) === 2, 'Two-column row was not preserved.');
```

- [ ] **Step 2: Run the focused test and confirm failure**

Run: `php tests/hr-database-driven-forms.php`

Expected: FAIL because normalized schemas do not yet include `rows` or version 2.

- [ ] **Step 3: Implement defensive version 2 normalization**

Update `yovel_admin_normalize_hr_builder_schema()` to normalize questions first, then normalize up to 60 rows with up to three columns each. Accept only known question keys, prevent duplicate placement, preserve empty rows, isolate section questions, append unreferenced questions, and recalculate question order from visual reading order.

- [ ] **Step 4: Run the focused test**

Run: `php tests/hr-database-driven-forms.php`

Expected: PASS with the existing transaction and submission checks plus the new layout assertions.

### Task 2: Row and Column Builder Canvas

**Files:**
- Modify: `company/admin/modules/hr/views/dashboard.php:305-395`
- Modify: `company/admin/views/partials/scripts.php:251-370,1898-2000`
- Modify: `company/admin/assets/css/admin.css:1004-1093,1866-1908`

**Interfaces:**
- Consumes: normalized version 2 schema in `data-hr-google-schema-json`.
- Produces: builder DOM attributes `data-hr-google-row`, `data-hr-google-column`, and `data-hr-google-question`, plus serialized version 2 schema.

- [ ] **Step 1: Render existing rows and visible column drop zones**

Replace the flat question list with row containers. Each row renders a row handle, a `1 / 2 / 3` segmented selector, accessible move/delete buttons, and one to three column drop zones. Keep current question editor controls inside their assigned column.

- [ ] **Step 2: Add row creation and selected-column behavior**

Add an `Add Row` command beside Add Question and Add Section. Clicking a column marks it selected. New questions enter the selected column; if no selection exists, use the final column of the final row; if no row exists, create a one-column row.

- [ ] **Step 3: Add drag/drop and non-pointer movement controls**

Make question cards draggable between column drop zones and reorderable within a column. Make rows reorderable. During drag, set explicit active states on the source and valid target. Keep up/down controls working within visual reading order for keyboard and non-pointer users.

- [ ] **Step 4: Serialize stable keys and layout references**

Preserve existing question keys in DOM data attributes. Generate collision-resistant client keys only for new questions, rows, and columns. Serialize `{version:2, questions:[...], rows:[...]}` without regenerating saved question keys.

- [ ] **Step 5: Add responsive and interaction styling**

Use equal CSS grid tracks for one to three columns, clear dashed empty drop zones, restrained selected and drop-target borders, and mobile single-column stacking. Keep the current neutral shadcn theme and avoid decorative colors.

- [ ] **Step 6: Run syntax and route checks**

Run:

```bash
php -l company/admin/modules/hr/views/dashboard.php
php -l company/admin/modules/hr/forms.php
php -l company/yovel-east/admin/index.php
curl -fsS -o /tmp/hr-dashboard-layout.html -w '%{http_code}\n' 'http://localhost/erpsystem/company/yovel-east/admin/?view=hr&section=dashboard&builder=1&builder_mode=new&builder_target=employee-profiles'
```

Expected: all PHP files report no syntax errors and the route returns HTTP 200.

### Task 3: Shared Preview and Submission Layout

**Files:**
- Modify: `company/admin/views/partials/scripts.php:302-370`
- Modify: `company/admin/modules/hr/views/dashboard.php:241-280,367-370`
- Modify: `company/admin/assets/css/admin.css`
- Modify: `tests/hr-database-driven-forms.php`

**Interfaces:**
- Consumes: normalized schema rows and canonical question lookup keyed by `question.key`.
- Produces: matching responsive row grids in JavaScript Preview and server-rendered submission forms.

- [ ] **Step 1: Add a test for visual reading order and answer compatibility**

Create a two-row schema with a two-column name row and a one-column email row. Assert normalized question order is First Name, Last Name, Email and that typed submission values continue to use the same question keys.

- [ ] **Step 2: Run the focused test and confirm the new assertion fails if layout order is ignored**

Run: `php tests/hr-database-driven-forms.php`

Expected: the new assertion identifies any mismatch between row reading order and question order.

- [ ] **Step 3: Render Preview from rows**

Build a question lookup from serialized questions, iterate rows and columns, omit empty rows, and render question previews inside responsive row grids. Render section questions in a full-width row.

- [ ] **Step 4: Render saved submissions from rows**

Update the dashboard submission form to iterate normalized rows and columns while preserving the existing input names, values, required attributes, option rendering, and employee-linked save actions.

- [ ] **Step 5: Run focused persistence and syntax tests**

Run:

```bash
php tests/hr-database-driven-forms.php
php -l company/admin/modules/hr/views/dashboard.php
php -l company/admin/modules/hr/submissions.php
```

Expected: all checks pass and submission answer persistence remains unchanged.

### Task 4: End-to-End Browser Verification

**Files:**
- Modify only when a verified visual or interaction defect requires a focused correction: `company/admin/modules/hr/views/dashboard.php`, `company/admin/views/partials/scripts.php`, `company/admin/assets/css/admin.css`

**Interfaces:**
- Consumes: the running local company admin route and existing authenticated session.
- Produces: verified row creation, drag/drop, persistence, preview parity, and responsive behavior.

- [ ] **Step 1: Open the HR Dashboard Form Builder at desktop width**

Use the in-app browser at 1440 by 900. Open a new Employee Profiles form and verify the toolbox, canvas, and settings panels remain aligned.

- [ ] **Step 2: Build the representative name layout**

Create a two-column row, add First Name and Last Name, drag one into each column, add a one-column Email row, and verify visible source and destination indicators.

- [ ] **Step 3: Verify Preview and persistence**

Switch to Preview and confirm the two name fields share one row. Save through the confirmation dialog, reopen through Existing Forms, and confirm row count, column count, question keys, labels, and placement are rehydrated.

- [ ] **Step 4: Verify responsive behavior**

Check 1440 by 900, 1024 by 768, and 390 by 844. Confirm two- and three-column rows stack on mobile, no text overlaps, controls remain reachable, and no horizontal page scrolling appears at common zoom levels.

- [ ] **Step 5: Run final verification suite**

Run:

```bash
php tests/hr-database-driven-forms.php
php tests/company-admin-modular-architecture.php
php -l company/admin/modules/hr/forms.php
php -l company/admin/modules/hr/views/dashboard.php
php -l company/admin/modules/hr/submissions.php
php -l company/yovel-east/admin/index.php
git diff --check
```

Expected: all tests and syntax checks exit 0 and `git diff --check` reports no whitespace errors.
