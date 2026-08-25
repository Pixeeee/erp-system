# HR Form Project Module Projection Design

## Goal

Make every HR Form Builder save immediately synchronize the company-owned form into the matching project module registries. The save must leave `project_company_hr_builder_form`, its immutable version, and every affected `project_module_form` row consistent before the request succeeds.

The company HR form remains the authoritative definition. `project_module_form` remains a project-scoped navigation and discovery projection, not the owner of the form or its answers.

## Current Behavior

`yovel_admin_save_hr_builder_form()` currently saves the mutable definition to `project_company_hr_builder_form` and creates or reuses a row in `project_company_hr_builder_form_version`. The general project hierarchy seed later copies non-deleted HR builder forms into `project_module_form`.

Because the hierarchy seed runs separately from the form write, a newly saved form is not guaranteed to have its project registry mappings before the Save Form request completes. Deleted forms can also leave stale projected rows because the general seed excludes deleted source forms.

## Chosen Architecture

Add a targeted project-registry synchronization function owned by the project module layer and call it from the existing HR form transaction.

The transaction order is:

1. Validate the company, administrator, form metadata, target HR feature, status, and normalized schema.
2. Lock and upsert `project_company_hr_builder_form` using its stable `builder_form_key`.
3. Create or reuse the immutable `project_company_hr_builder_form_version` when the source status is not `DELETED`.
4. Synchronize the source form into every matching active project and HR feature module.
5. Retire stale project mappings that no longer belong to the source form's current target or active project scope.
6. Read the source form, version, and every affected registry mapping back and verify their keys and persisted values.
7. Write audit events and commit.

Any failure rolls back the source form write, version write, registry mappings, and audit events together.

## Ownership and Relationships

### `project_company_hr_builder_form`

This is the mutable company-level master form. It owns:

- `builder_form_key`
- company scope
- target HR feature
- title and description
- status
- normalized row, column, and question schema
- question count

### `project_company_hr_builder_form_version`

This is the immutable version history. Each unique definition checksum receives a version for the same `builder_form_key`. Submissions remain pinned to a specific version.

### `project_module_form`

This is a project-specific registry projection. A company form may produce zero, one, or many registry rows because the company may have several projects or branches.

Every projected row must contain:

- the target project, branch, module group, and feature module keys
- `source_builder_form_key` equal to the source `builder_form_key`
- a deterministic form code derived from the source key
- the current title, description, schema, status, and sort order
- no `source_form_schema_key`

The `form_key` remains stable when the same source form is updated within the same project module.

### Submissions

`project_form_submission` continues to reference the authoritative `builder_form_key` and immutable `form_version_key`. `module_form_key` remains optional and is populated only when a submission originates from a specific project module workspace. Answers remain in `project_form_submission_value`.

## Projection Scope

For a non-deleted form, the synchronizer examines active projects whose ownership chain matches the form's `company_key_hash`. For each project, it locates the active `HR Department` module group and the active feature module matching `target_section`.

If a project has no matching HR feature module, no projection is created for that project. This is valid because there is no project workspace capable of hosting the form.

The deterministic registry identity is the pair of target `module_key` and form code derived from `builder_form_key`. The same source can therefore be projected into several projects without sharing project-owned `form_key` values.

## Status and Target Changes

- `DRAFT`, `ACTIVE`, and `ARCHIVED` source states are copied to every expected registry mapping.
- `DELETED` creates no expected mappings and marks every existing mapping for the same `source_builder_form_key` as `DELETED`.
- Changing `target_section` creates or updates mappings in the new feature modules and marks mappings in the former feature modules as `DELETED`.
- Registry rows outside the current active project scope are marked `DELETED` rather than physically removed.
- Re-activating or retargeting a form may reuse a matching registry row and stable `form_key` when its module and deterministic form code match.

## Function Boundaries

The project module layer will expose a focused function equivalent to:

```php
bx_project_module_sync_hr_builder_form(
    ADOConnection $db,
    array $company,
    array $builderForm
): array
```

It accepts the existing database connection and never starts or commits its own transaction. The caller owns the transaction boundary.

The function returns the verified active and retired registry rows so the HR save path can include projection counts and keys in its audit context.

The existing project module form upsert helper may return its verified read-back row instead of `void`. Existing hierarchy-seed callers may ignore that return value.

## Registry Writes

All writes use fixed identifiers and parameterized ADODB statements. Existing rows are locked before comparison. Creates and updates preserve stable keys, and every write result is checked immediately.

New registry rows receive the next available sort position in their target module. Existing rows preserve their sort position. Synchronization updates only fields owned by the projection contract.

Retirement updates `form_status` to `DELETED`, records the update actor, verifies the saved source key and status, and writes an audit event inside the same transaction.

## Read-Back Verification

Before commit, the save path verifies:

- the source `builder_form_key`, target, title, description, status, schema, and question count
- the immutable version key, version number, checksum, and source key when a version is expected
- every projected row's project ownership chain, module key, source key, form code, metadata, schema, status, and stable form key
- every retired row's source key and `DELETED` status

An affected-row count or successful redirect is not accepted as persistence proof.

## Error Handling

- Invalid source keys, target sections, statuses, and ownership chains fail before commit.
- Database write failures capture the database error before any subsequent query.
- Missing project feature modules are skipped because they are not valid projection targets.
- A failed project mapping or read-back check rolls back the complete HR form save.
- User-facing errors remain concise and do not expose SQL, schema internals, or credentials.

## Verification

Automated coverage will verify:

1. Creating one HR form for a company with two active projects produces one company master, one immutable version, and two linked project registry rows.
2. Updating title, description, schema, or status updates each registry row while preserving its `form_key`.
3. Saving the same checksum reuses the current immutable version.
4. Changing the target HR feature creates mappings in the new modules and retires the old mappings.
5. Archiving propagates `ARCHIVED` and deleting propagates `DELETED` without creating a new form version.
6. A company with no matching project feature module still saves its company-owned form without creating an invalid registry row.
7. A forced projection failure rolls back the master, version, registry, and audit writes.
8. The general hierarchy seed remains idempotent after immediate projection.
9. Form submissions continue to validate `module_form_key` against `source_builder_form_key` and remain pinned to their immutable version.

Verification commands include the focused HR database tests, project module hierarchy tests, PHP syntax checks, route checks, and direct database read-back assertions.

## Scope Boundaries

This change synchronizes HR Form Builder definitions into the existing project registry. It does not move form ownership into `project_module_form`, duplicate submissions, introduce database triggers, alter built-in HR form fields, or change the Form Builder UI.
