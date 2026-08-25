# Company, Branch, Project, and Module Hierarchy Design

Date: 2026-08-25
Status: Approved design

## Objective

Represent the BuilderX ERP hierarchy consistently in navigation and persistence:

```text
Company
  Branch
    Project
      Module Group
        Project Module
          Form
```

The administrator sidebar must expose the same Company -> Branch -> Project hierarchy. A selected project must expose its ERP module groups and their project modules without creating a physical MySQL table for every module.

## Approved Decisions

1. Rename the administrator sidebar group label from `Projects` to `Company`.
2. Render active companies as dropdowns, including Yovel East.
3. Render each branch as a nested dropdown under its company.
4. Render each project as a selectable leaf under its branch.
5. Store ERP systems such as HR, Accounting, Sales, Inventory, and Tracking as project-owned module groups.
6. Store features such as Employee Profiles and Departments as project modules belonging to a module group.
7. Store forms in one shared form registry linked to the project module.
8. Start project-module numbering at `100` and map it directly: index `100` has logical table name `project_module_100`, index `101` has `project_module_101`, and so on.
9. Treat `project_module_N` as a logical registry name. Do not create a separate physical table for each number.
10. Keep the migration additive and preserve all existing company, branch, project, company-module, form, and permission records.

## Considered Approaches

### Additive shared registry (selected)

Keep the current company hierarchy and company-module template, then add project-owned module-group, project-module, and form registries. This preserves existing records and provides one queryable structure for permissions, reporting, backups, and migrations.

### Repurpose `project_company_module` (rejected)

Changing the existing company-scoped module table into a project-scoped table would mix template and project ownership, disrupt current permission links, and require a risky backfill of live records.

### Physical table per project module (rejected)

Creating `project_module_100`, `project_module_101`, and later tables physically would make schema migrations, authorization, cross-module reports, backups, and safe parameterized access increasingly difficult. Logical names provide the requested numbering without dynamic SQL identifiers.

## Existing Data Compatibility

The existing tables remain authoritative for the upper hierarchy:

- `project_company`
- `project_company_branch`
- `project_company_project`

The existing `project_company_module` table remains the company-level ERP module template and permission source. It is not renamed or destructively repurposed. Its active entries provide the initial module-group definitions copied into each project.

Existing feature lists in `yovel_admin_erp_groups()` provide the initial project-module definitions. Existing specialized HR, Sales/CRM, and Accounting tables remain unchanged and continue to own their business records.

## New Tables

### `project_module_group`

Stores a project-specific ERP system or department group.

Required columns:

- `x_id`: unsigned auto-increment internal primary key
- `module_group_key`: stable UUID business key
- `company_key_hash`: company scope
- `branch_key`: owning branch
- `project_key`: owning project
- `source_company_module_key`: nullable link to the company-level module template
- `module_group_code`: normalized code such as `HR_DEPARTMENT`
- `module_group_name`: display name such as `HR Department`
- `module_group_index`: project-local display order
- `module_group_icon`: optional icon reference
- `module_group_description`: optional description
- `module_group_status`: `DRAFT`, `ACTIVE`, `INACTIVE`, or `DELETED`
- creator, updater, and timestamp audit columns

Constraints and indexes:

- Unique `module_group_key`
- Unique `(project_key, module_group_code)`
- Index `(company_key_hash, branch_key, project_key, module_group_status)`
- Index `(project_key, module_group_index)`

### `project_module`

Stores a feature or workspace inside a project module group.

Required columns:

- `module_index`: unsigned auto-increment primary numbering value, initialized at `100`
- `module_key`: stable UUID business key
- `company_key_hash`: company scope
- `branch_key`: owning branch
- `project_key`: owning project
- `module_group_key`: owning module group
- `module_code`: normalized code such as `EMPLOYEE_PROFILES`
- `module_name`: display name such as `Employee Profiles`
- `module_table_name`: logical name set to `project_module_` plus `module_index` immediately after the insert
- `module_sort_order`: order inside the module group
- `module_description`: optional description
- `module_status`: `DRAFT`, `ACTIVE`, `INACTIVE`, or `DELETED`
- creator, updater, and timestamp audit columns

Constraints and indexes:

- Unique `module_key`
- Unique `module_table_name`
- Unique `(project_key, module_group_key, module_code)`
- Index `(company_key_hash, branch_key, project_key, module_group_key, module_status)`
- Index `(module_group_key, module_sort_order)`

The application inserts the row first, obtains `module_index`, derives `module_table_name`, updates the same row in the transaction, and verifies both values before commit. The numbering is global so logical names cannot collide across projects.

### `project_module_form`

Stores forms belonging to project modules.

Required columns:

- `x_id`: unsigned auto-increment internal primary key
- `form_key`: stable UUID business key
- `company_key_hash`: company scope
- `branch_key`: owning branch
- `project_key`: owning project
- `module_group_key`: owning module group
- `module_key`: owning project module
- `form_code`: normalized form code
- `form_name`: display name
- `form_description`: optional description
- `form_schema_json`: validated form definition
- `form_status`: `DRAFT`, `ACTIVE`, `ARCHIVED`, or `DELETED`
- `form_sort_order`: order inside the module
- creator, updater, and timestamp audit columns

Constraints and indexes:

- Unique `form_key`
- Unique `(module_key, form_code)`
- Index `(project_key, module_group_key, module_key, form_status)`
- Index `(module_key, form_sort_order)`

The initial migration creates registry rows only for existing forms that already have a stable schema source. Active records from `project_company_form_schema` map by ERP module code and record type. Active records from `project_company_hr_builder_form` map by HR target section. Hard-coded screens, dashboards, and reports without a standalone form schema remain modules with zero forms. No existing business record or schema row is moved or deleted.

## Seed and Migration Behavior

Schema setup is idempotent. It creates missing tables and indexes without dropping, renaming, or truncating existing structures.

For every non-deleted project:

1. Confirm its branch belongs to the same company.
2. Upsert each active `project_company_module` entry as one `project_module_group` row.
3. Match the company ERP group by normalized code.
4. Upsert each feature in the matched group as one `project_module` row.
5. Preserve existing stable keys and module indexes on rerun.
6. Create new module indexes only for previously absent modules.
7. Record audit events for created or updated registry records.
8. Read all saved values back before committing.

The seed is safe to rerun and must not create duplicate groups or modules.

## Administrator Navigation

The administrator sidebar hierarchy becomes:

```text
Company
  Yovel East
    Sariaya Branch
      Agri Financing Officer
```

Behavior:

- Company rows expand and collapse.
- Branch rows expand and collapse independently.
- Project rows are selectable leaves.
- The active company, branch, and project remain expanded.
- The URL stores `company_key`, `branch_key`, and `project_key` so reload and browser navigation restore the same selection.
- Empty companies, branches, or projects show concise disabled empty states.
- Existing company and branch overview routes continue to work.

## Selected Project Workspace

Selecting a project shows:

- Company, branch, and project identity
- Active module-group count
- Active project-module count
- Module groups in stable order
- Project modules nested beneath each group
- Logical table name for database traceability
- Form count for each project module

This first change is read-only for module groups, project modules, and forms. Existing company-management create/edit flows continue to manage companies, branches, and projects. Module editing can be added later through a dedicated persisted form workflow.

## Persistence and Safety

All schema and seed writes use `bx_db()` with ADODB parameterized operations.

Each multi-row migration or seed run must:

1. Validate project ownership and fixed table identifiers.
2. Begin a transaction.
3. Check every write result immediately.
4. Use complete create/update behavior while preserving stable keys.
5. Write an audit event inside the same transaction.
6. Read back keys, ownership fields, codes, names, indexes, logical table names, and statuses.
7. Roll back on any mismatch.
8. Commit only after direct read-back succeeds.

No request value may be interpolated into a table name. Logical table names are generated only from the trusted numeric `module_index`.

## Error Handling

- Reject a project whose branch or company scope does not match.
- Reject duplicate module codes within the same project and module group.
- Reject duplicate form codes within the same project module.
- Roll back if a logical table name does not match its module index.
- Preserve existing navigation if a stale company, branch, or project key is requested, then show an unavailable-state message.
- Return user-friendly errors without SQL text, credentials, or internal connection details.

## Verification

Database verification must confirm:

- All three new tables and required indexes exist.
- The first generated module index is at least `100`.
- Every logical table name equals `project_module_<module_index>`.
- Every module group belongs to an existing project, branch, and company scope.
- Every project module belongs to a valid group in the same project.
- Every form belongs to a valid project module in the same scope.
- Rerunning the seed does not change stable keys or create duplicates.
- Direct ADODB read-back matches all persisted fields.

UI verification must confirm:

- The sidebar group reads `Company`.
- Yovel East expands to its branches.
- Each branch expands to its projects.
- Selecting a project updates the route and workspace.
- Reload restores the selected project.
- Keyboard activation and focus indicators work for each hierarchy level.
- Sidebar text remains readable at desktop and narrow widths.
- Existing company and branch views still load.

Code verification includes PHP lint, focused schema/data tests, the frontend build, and a browser route check at desktop and mobile widths.
