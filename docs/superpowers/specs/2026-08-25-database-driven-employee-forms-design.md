# Database-Driven Employee Forms Design

## Goal

Evolve Employee Profiles into a database-driven model that preserves stable employee identity, records historical organizational assignments, versions Form Builder definitions, and stores custom-form answers as typed submissions. Existing employee keys, including Kim D Danez's record, must remain unchanged and readable throughout the migration.

## Approved Direction

Use a hybrid normalized model:

- Keep durable, frequently queried identity fields in `project_company_hr_employee`.
- Move organizational placement into an assignment history table while temporarily mirroring the active primary assignment into the legacy employee columns for compatibility.
- Keep built-in and custom form definitions in their current authoritative tables.
- Add immutable versions for custom Form Builder definitions.
- Add generic submission and typed submission-value tables for answers.
- Keep `project_module_form` as a project navigation and discovery registry only.
- Keep `project_module_101` as a logical module name; do not create physical tables per module or form.

## Current State

`project_company_hr_employee` owns employee identity and currently also stores the current branch, department, job position, team, and manager. `project_company_hr_form_field` defines built-in HR fields. `project_company_hr_builder_form` stores custom form layout JSON. `project_company_hr_custom_value` stores built-in Employee Profile custom values as untyped text. `project_module_form` projects forms into project modules but does not own employee records or submitted answers.

The existing custom Form Builder saves definitions only. It has no generic persisted response model. Empty custom Employee Profile values are currently stored as rows, which adds noise without representing meaningful answers.

## Data Model

### Employee Identity

`project_company_hr_employee` remains the authoritative employee master. Existing keys and business identifiers are preserved. Core columns include employee code, legal/display name, lifecycle status, dates, contact details, and notes.

The current assignment columns remain during compatibility rollout:

- `branch_key`
- `department_key`
- `job_position_key`
- `team_key`
- `reports_to_employee_key`

New writes update these columns from the active primary assignment in the same transaction. They are compatibility mirrors, not the long-term assignment history source.

### Employee Assignment History

Create `project_company_hr_employee_assignment` with:

- `x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `assignment_key CHAR(36) NOT NULL UNIQUE`
- `migration_key VARCHAR(120) NULL UNIQUE`
- `company_key CHAR(36) NOT NULL`
- `company_key_hash CHAR(64) NOT NULL`
- `employee_key CHAR(36) NOT NULL`
- `branch_key CHAR(36) NULL`
- `project_key CHAR(36) NULL`
- `department_key CHAR(36) NULL`
- `job_position_key CHAR(36) NULL`
- `team_key CHAR(36) NULL`
- `reports_to_employee_key CHAR(36) NULL`
- `assignment_status ENUM('ACTIVE','ENDED','DELETED') NOT NULL DEFAULT 'ACTIVE'`
- `is_primary TINYINT(1) NOT NULL DEFAULT 0`
- `effective_from DATE NULL`
- `effective_until DATE NULL`
- `assignment_notes TEXT NULL`
- creator/updater administrator keys and timestamps

Indexes cover company, employee, branch, project, status, and effective dates. Application validation allows at most one active primary assignment per employee. The save transaction locks the employee and its active assignments before changing primary status.

### Immutable Form Versions

Create `project_company_hr_builder_form_version` with:

- `x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `form_version_key CHAR(36) NOT NULL UNIQUE`
- `builder_form_key CHAR(36) NOT NULL`
- `company_key CHAR(36) NOT NULL`
- `company_key_hash CHAR(64) NOT NULL`
- `version_number INT UNSIGNED NOT NULL`
- `target_section VARCHAR(80) NOT NULL`
- `form_title VARCHAR(180) NOT NULL`
- `form_description TEXT NULL`
- `form_status ENUM('DRAFT','ACTIVE','ARCHIVED') NOT NULL`
- `schema_json LONGTEXT NOT NULL`
- `schema_checksum CHAR(64) NOT NULL`
- `question_count INT UNSIGNED NOT NULL DEFAULT 0`
- creator administrator key and creation timestamp

Unique constraints cover `(builder_form_key, version_number)` and `(builder_form_key, schema_checksum)`. A content checksum includes target section, title, description, status, and normalized schema. Saving unchanged content reuses the existing version. Changed content creates the next immutable version and updates the mutable builder-form row.

Every non-section question receives a stable question key. Reordering a question changes only its order and does not change its key. Legacy schemas are normalized once and retain their resulting keys in version 1.

### Generic Form Submissions

Create `project_form_submission` with:

- `x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `submission_key CHAR(36) NOT NULL UNIQUE`
- `company_key_hash CHAR(64) NOT NULL`
- `branch_key CHAR(36) NULL`
- `project_key CHAR(36) NULL`
- `module_group_key CHAR(36) NULL`
- `module_key CHAR(36) NULL`
- `module_form_key CHAR(36) NULL`
- `builder_form_key CHAR(36) NOT NULL`
- `form_version_key CHAR(36) NOT NULL`
- `subject_type VARCHAR(80) NOT NULL`
- `subject_key CHAR(36) NOT NULL`
- `submission_status ENUM('DRAFT','SUBMITTED','VOID') NOT NULL DEFAULT 'DRAFT'`
- `submitted_by_admin_key CHAR(36) NULL`
- `submitted_by_user_key CHAR(36) NULL`
- `submitted_at DATETIME NULL`
- creator/updater keys and timestamps

`subject_type = 'EMPLOYEE'` and `subject_key = employee_key` bind an Employee Profile submission to a durable employee record. Scope indexes cover company, project, form, subject, and status. The server verifies that all referenced scope records belong to the same company before writing.

`builder_form_key` and `form_version_key` are the authoritative definition references. `module_form_key` is optional because a company-level HR form can be projected into several projects. It is populated only when the submission originates from a specific project workspace; when present, the server verifies that its `source_builder_form_key` matches `builder_form_key`.

### Typed Submission Values

Create `project_form_submission_value` with:

- `x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `submission_value_key CHAR(36) NOT NULL UNIQUE`
- `submission_key CHAR(36) NOT NULL`
- `question_key VARCHAR(120) NOT NULL`
- `field_type ENUM('SHORT_TEXT','PARAGRAPH','DROPDOWN','CHECKBOXES','DATE','NUMBER','EMAIL','PHONE') NOT NULL`
- `value_text TEXT NULL`
- `value_number DECIMAL(20,6) NULL`
- `value_date DATE NULL`
- `value_json LONGTEXT NULL`
- creator/updater keys and timestamps

The unique key `(submission_key, question_key)` prevents duplicate answers. Server validation permits exactly one value column for the declared field type. `CHECKBOXES` uses a normalized JSON array.

Blank optional answers do not create value rows. Clearing an existing answer deletes its value row inside the submission transaction and records the change in the audit event.

### Typed Employee Custom Values

The built-in Employee Profile custom fields remain distinct from independent Form Builder questionnaires. Extend `project_company_hr_custom_value` with:

- `field_key CHAR(36) NULL`
- `field_type ENUM('TEXT','TEXTAREA','DATE','NUMBER','EMAIL','PHONE','SELECT','CHECKBOX') NOT NULL DEFAULT 'TEXT'`
- `value_text TEXT NULL`
- `value_number DECIMAL(20,6) NULL`
- `value_date DATE NULL`
- `value_boolean TINYINT(1) NULL`
- `value_json LONGTEXT NULL`

Keep `field_name` and `field_value` during compatibility rollout. New writes resolve the authoritative `project_company_hr_form_field` row, populate `field_key`, validate its type, write one typed value column, and mirror a canonical string into `field_value` for the existing UI. Reads prefer typed columns and fall back to `field_value` only for unmigrated rows. Empty optional values create no row; clearing a value removes the row transactionally.

## Relationships

```text
project_company
  -> project_company_branch
    -> project_company_project
      -> project_module_group
        -> project_module
          -> project_module_form

project_company_hr_employee
  -> project_company_hr_employee_assignment
  -> project_company_hr_custom_value

project_company_hr_builder_form
  -> project_company_hr_builder_form_version
    -> project_form_submission
      -> project_form_submission_value

project_form_submission.subject_key
  -> project_company_hr_employee.employee_key when subject_type = EMPLOYEE
```

The application enforces polymorphic `subject_type` ownership because MySQL cannot express one foreign key that targets several subject tables. All identifiers remain fixed schema columns; user input never selects a SQL table name.

## Write Flows

### Employee Create Or Update

1. Authenticate the company administrator, validate CSRF, and validate company ownership.
2. Normalize employee identity and assignment input.
3. Begin one ADODB transaction.
4. Lock the employee and its active assignments.
5. Upsert the employee identity using its stable employee key.
6. End the previous primary assignment when its scope changes, then create or update the active primary assignment.
7. Mirror the active primary assignment into the legacy employee assignment columns.
8. Persist only non-empty typed built-in custom values; delete cleared optional values.
9. Record audit events.
10. Directly read back the employee, assignment, and custom-value rows.
11. Commit only after all comparisons pass.

### Custom Form Definition Save

1. Normalize the form and stable question keys.
2. Calculate the canonical SHA-256 content checksum.
3. Begin one ADODB transaction and lock the mutable builder-form row.
4. Upsert the mutable current definition.
5. Reuse an identical immutable version or create the next version.
6. Synchronize the project module form registry while preserving source status.
7. Audit and directly read back both current and version rows.
8. Commit after verification.

### Custom Form Submission Save

1. Authenticate, validate CSRF, and load the builder form plus immutable form version from the database.
2. Verify company, branch, project, module, subject, and form ownership.
3. Validate answers against the saved version, not browser-provided field metadata.
4. Begin one ADODB transaction.
5. Upsert the submission while preserving its stable submission key.
6. Upsert non-empty typed values and delete cleared values.
7. Audit the changed question keys without placing sensitive answer content in the audit payload.
8. Directly read back the submission and typed values.
9. Commit after verification and rehydrate the UI from server data.

## Migration

Schema creation is idempotent. Existing rows are migrated as follows:

- Preserve every `project_company_hr_employee.employee_key`.
- Create one primary active assignment per employee with `migration_key = 'legacy-current:' + employee_key` when any current assignment column is populated.
- Resolve `project_key` only when the branch has exactly one active project; otherwise leave it null rather than guessing.
- Create immutable version 1 for each non-deleted existing custom builder form.
- Delete empty `project_company_hr_custom_value` rows inside the migration transaction because they represent unanswered optional fields.
- Resolve every non-empty legacy custom value to its company-scoped `project_company_hr_form_field`, populate `field_key` and `field_type`, and convert it into the matching typed value column. Unresolvable rows remain readable through `field_value` and are reported by the migration test instead of being guessed or deleted.
- Do not delete legacy columns or tables in this change.

For Kim D Danez, migration preserves employee key `4cf281b7-223f-452e-b6e9-178c0f8abbba`, creates an idempotent primary assignment for Sariaya Branch, and removes the current empty custom-value rows without creating submission answers.

## Read Behavior And UI

Employee Profile lists continue to read core identity from `project_company_hr_employee`. Assignment labels come from the active primary assignment with a compatibility fallback to legacy employee columns. The edit modal loads identity, active assignment, and custom values independently.

Existing Forms shows current definitions from `project_company_hr_builder_form`. Opening a custom form submission loads the current immutable version and renders controls from its saved schema. Existing submissions always render against their pinned version even after the form is edited.

The Administrator project module count continues to come from `project_module_form`; it never counts employee records or submissions as forms.

## Authorization And Audit

- Company administrators can only read and write records whose `company_key_hash` matches their authenticated company.
- Branch, project, department, position, team, manager, module, form, version, and subject references are verified server-side.
- Assignment and submission writes use parameterized ADODB statements and transaction boundaries.
- Audit events record operation, stable keys, scope, status, version, and changed field keys. Sensitive field values are excluded.
- Soft status changes are preferred for assignments, form definitions, and submissions. Cleared answer rows may be physically deleted inside the audited transaction because absence represents no answer.

## Failure Handling

Any failed write, audit event, ownership check, or read-back comparison rolls back the complete transaction. The UI receives a safe error message without SQL or credentials. A failed migration leaves existing tables and reads untouched and can be rerun safely.

## Verification

- Prove schema columns, unique keys, and indexes through `information_schema`.
- Run migration twice and verify identical assignment and version keys/counts.
- Verify Kim D Danez retains the same employee key and receives exactly one primary active assignment.
- Verify empty legacy custom values are removed and create no typed custom-value rows.
- Exercise employee create, update, reassignment, and read-back.
- Exercise custom form create, unchanged save, changed save, version pinning, submission create, submission update, and cleared answers.
- Verify cross-company, invalid CSRF, invalid project scope, unknown question key, and type mismatch rejection.
- Run PHP lint, focused database tests, frontend build, browser create/update/reload checks, and direct database read-back.

## Non-Goals

- Do not create a physical table for each project module or custom form.
- Do not remove the current employee assignment columns or legacy custom-value table in this migration.
- Do not treat built-in Employee Profile custom fields as independent Form Builder submissions.
- Do not add payroll, attendance, or recruitment business workflows.
- Do not make `project_module_form` the owner of form answers.
