# Database-Driven Employee Forms Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add historical employee assignments, typed Employee Profile custom values, immutable Form Builder versions, and persisted typed custom-form submissions without changing existing employee UUIDs or creating per-form tables.

**Architecture:** Extend the modular company-admin HR backend. `project_company_hr_employee` remains the employee identity source, assignment history and form responses use focused normalized tables, and compatibility columns remain mirrored during rollout. The project module form table remains a navigation registry only.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, server-rendered PHP, native JavaScript, PHPUnit-style executable PHP regression scripts, Vite/TypeScript frontend assets.

## Global Constraints

- Preserve every existing `project_company_hr_employee.employee_key`.
- Keep every write company-scoped, parameterized, transactional, audited, and directly read back before commit.
- Keep `project_module_form` as a registry; do not store submissions there.
- Do not create physical tables named from `project_module_<index>` or user input.
- Keep legacy employee assignment columns and `field_value` synchronized during compatibility rollout.
- Do not persist empty optional custom values or answers.
- Render existing submissions from their pinned immutable form version.
- Keep `company/admin/index.php` a thin front controller.

---

### Task 1: Schema And Idempotent Migration

**Files:**
- Modify: `company/admin/modules/hr/schema.php`
- Create: `tests/hr-database-driven-forms.php`

**Interfaces:**
- Consumes: `bx_db()`, `bx_uuid()`, `yovel_admin_db_execute()`
- Produces: `yovel_admin_hr_schema()`, `yovel_admin_migrate_hr_database_model(ADOConnection $db): void`, and four new tables plus typed custom-value columns

- [ ] **Step 1: Write the failing schema and migration test**

Assert through `information_schema` that `project_company_hr_employee_assignment`, `project_company_hr_builder_form_version`, `project_form_submission`, and `project_form_submission_value` exist with the approved keys and indexes. Capture all employee keys, call `yovel_admin_hr_schema()` twice, and assert identical employee keys, assignment counts, version counts, and migration keys.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/hr-database-driven-forms.php`

Expected: failure naming the first missing table.

- [ ] **Step 3: Add the idempotent schema**

Create the four approved tables in the existing `$statements` list. Add typed columns to `project_company_hr_custom_value` using a company-admin helper that checks `information_schema.COLUMNS` before executing fixed allow-listed `ALTER TABLE` statements. Add indexes with an equivalent `information_schema.STATISTICS` guard.

- [ ] **Step 4: Implement migration**

Inside one transaction, create `legacy-current:<employee_key>` assignment rows for employees with assignment data, resolve a project only when the branch has exactly one active project, seed immutable version 1 for every non-deleted builder form, delete empty custom values, and type resolvable non-empty values. Use stable existing rows on rerun and verify every write by direct read-back.

- [ ] **Step 5: Run the schema test**

Run: `php tests/hr-database-driven-forms.php`

Expected: schema, key preservation, and idempotency assertions pass.

### Task 2: Employee Assignment Persistence

**Files:**
- Modify: `company/admin/modules/hr/employees.php`
- Modify: `company/admin/modules/hr/data.php`
- Modify: `tests/hr-database-driven-forms.php`

**Interfaces:**
- Consumes: existing employee save transaction and company-scoped reference validation
- Produces: `yovel_admin_upsert_hr_employee_primary_assignment(ADOConnection $db, array $company, array $admin, string $employeeKey, array $assignment): array` and assignment-backed employee read models

- [ ] **Step 1: Add failing assignment tests**

Create a temporary employee inside a transaction-safe fixture, save an initial primary assignment, change its project or branch scope, and assert exactly one active primary assignment, one ended historical assignment, and mirrored legacy employee columns. Assert cross-company references and self-manager references are rejected.

- [ ] **Step 2: Run the focused test and confirm failure**

Run: `php tests/hr-database-driven-forms.php`

Expected: failure because assignment persistence is not connected to employee saves.

- [ ] **Step 3: Implement assignment upsert and read-back**

Lock active assignments with `FOR UPDATE`. Reuse an unchanged assignment, otherwise end the old primary row and create a new stable assignment. Update the employee compatibility columns in the same transaction and compare all persisted assignment fields before returning.

- [ ] **Step 4: Load active assignment data**

Join the active primary assignment in `yovel_admin_hr_data()` and use `COALESCE` only as a compatibility fallback to legacy employee columns. Expose project and assignment identifiers without replacing the stable employee key.

- [ ] **Step 5: Run assignment and architecture tests**

Run: `php tests/hr-database-driven-forms.php && php tests/company-admin-modular-architecture.php`

Expected: assignment and modular-boundary assertions pass.

### Task 3: Typed Employee Profile Custom Values

**Files:**
- Modify: `company/admin/modules/hr/forms.php`
- Modify: `tests/hr-database-driven-forms.php`

**Interfaces:**
- Consumes: `project_company_hr_form_field` definitions and existing `custom_fields` POST shape
- Produces: typed conversion helpers, non-empty upserts, transactional clearing, and legacy string compatibility

- [ ] **Step 1: Add failing typed-value tests**

Exercise text, number, date, checkbox, and cleared optional values. Assert the authoritative field key/type are persisted, exactly one typed column is populated, `field_value` contains the canonical compatibility string, and clearing deletes the row.

- [ ] **Step 2: Run the test and confirm failure**

Run: `php tests/hr-database-driven-forms.php`

Expected: typed-column or empty-row assertion failure.

- [ ] **Step 3: Implement typed value normalization**

Add `yovel_admin_hr_typed_custom_value(array $field, string $value): array` returning the canonical legacy value and typed columns. Reject invalid dates, numbers, emails, and select options using the saved field definition.

- [ ] **Step 4: Replace empty upserts with delete semantics**

For optional blank values, delete an existing row by company, form, record, and field name. For non-empty values, upsert `field_key`, `field_type`, typed columns, and `field_value`; then read the row back and compare every typed column.

- [ ] **Step 5: Run typed-value regression tests**

Run: `php tests/hr-database-driven-forms.php`

Expected: typed persistence and clearing assertions pass.

### Task 4: Stable Questions And Immutable Form Versions

**Files:**
- Modify: `company/admin/modules/hr/forms.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `tests/hr-database-driven-forms.php`

**Interfaces:**
- Consumes: `yovel_admin_normalize_hr_builder_schema()` and `yovel_admin_save_hr_builder_form()`
- Produces: stable question keys, canonical checksums, `yovel_admin_upsert_hr_builder_form_version(...)`, and version read models

- [ ] **Step 1: Add failing question/version tests**

Normalize the same schema twice and after reordering; assert each existing question key remains stable. Save unchanged form content twice and assert one immutable version, then change content and assert version 2 while version 1 remains unchanged.

- [ ] **Step 2: Run the test and confirm failure**

Run: `php tests/hr-database-driven-forms.php`

Expected: unstable question key or missing version failure.

- [ ] **Step 3: Stabilize normalized question keys**

Preserve a valid supplied question key. For missing keys, create a UUID once and return it to the browser schema. Never append the current array index to an existing key; only update `order` during reordering.

- [ ] **Step 4: Implement immutable version writes**

Canonicalize target, title, description, status, and normalized schema; hash with SHA-256; reuse a matching version or insert `MAX(version_number)+1` while holding the builder-form lock. Directly read back checksum, version number, and schema before commit.

- [ ] **Step 5: Expose current version metadata and run tests**

Load current version key/number alongside builder forms and run `php tests/hr-database-driven-forms.php`.

Expected: stability, deduplication, and version preservation assertions pass.

### Task 5: Generic Employee Form Submissions

**Files:**
- Create: `company/admin/modules/hr/submissions.php`
- Modify: `company/admin/bootstrap/app.php`
- Modify: `company/admin/bootstrap/controller.php`
- Modify: `company/admin/modules/hr/data.php`
- Modify: `company/admin/modules/hr/views/dashboard.php`
- Modify: `company/admin/views/partials/scripts.php`
- Modify: `tests/company-admin-modular-architecture.php`
- Modify: `tests/hr-database-driven-forms.php`

**Interfaces:**
- Consumes: immutable builder-form versions, Employee Profile subjects, CSRF/authenticated controller, and module registry context
- Produces: `yovel_admin_save_hr_form_submission(array $company, array $admin): string`, submission read models, and an accessible server-backed Fill Form workflow

- [ ] **Step 1: Add failing submission tests**

Create a versioned form with all supported question types, submit valid answers for an employee, update one answer, clear one optional answer, and assert typed columns plus pinned version. Assert required, unknown key, invalid option, invalid date/number/email, cross-company subject, and mismatched module-form requests fail.

- [ ] **Step 2: Run the test and confirm failure**

Run: `php tests/hr-database-driven-forms.php`

Expected: missing submission service/table integration failure.

- [ ] **Step 3: Implement the submission service**

Load the company-owned builder form and immutable version from the database. Validate the employee subject and optional project/module registry scope. Upsert a stable submission, upsert only non-empty typed values, delete cleared values, audit changed question keys without values, and compare the complete read-back before commit.

- [ ] **Step 4: Wire the controller and read model**

Load `submissions.php` from bootstrap, dispatch `save_hr_form_submission`, retain the selected employee/form after redirect, and load company-scoped submission history and values for the dashboard.

- [ ] **Step 5: Add the Fill Form UI**

For an existing custom form targeting Employee Profiles, render employee selection, saved-version fields, Draft/Submit status, existing submission selection, confirmation, errors, and server-rehydrated values. Use semantic labels and native controls; do not place submission actions inside another form.

- [ ] **Step 6: Run backend, architecture, and frontend checks**

Run: `php tests/hr-database-driven-forms.php && php tests/company-admin-modular-architecture.php && npm run build --prefix frontend && npm run lint --prefix frontend`

Expected: no test/build errors; pre-existing lint warnings may remain documented.

### Task 6: End-To-End Migration And Browser Verification

**Files:**
- Verify: all files above
- Verify: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: completed schema, migrations, assignments, versions, typed values, and UI
- Produces: direct database and visible browser evidence

- [ ] **Step 1: Run all focused verification commands**

Run PHP lint for every file under `company/admin/`, then run `php tests/company-admin-modular-architecture.php`, `php tests/hr-database-driven-forms.php`, and `php tests/project-module-hierarchy.php`.

- [ ] **Step 2: Verify Kim D Danez directly**

Assert employee key `4cf281b7-223f-452e-b6e9-178c0f8abbba` is unchanged, exactly one migrated active primary assignment exists, its branch is Sariaya Branch, and no empty custom-value rows remain.

- [ ] **Step 3: Verify browser workflows**

In the signed-in Yovel East company admin, edit and reload Kim's Employee Profile, inspect assignment history, open the existing custom form, save a test draft submission, reload, update it, and verify persisted values. Confirm Administrator still reports nine Employee Profile forms and does not count submissions as forms.

- [ ] **Step 4: Review final diff and report residual risks**

Run `git diff --check`, inspect only task-owned changes, and report any pre-existing lint warnings or unimplemented non-goals without reverting unrelated user changes.
