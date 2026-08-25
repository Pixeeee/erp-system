# Shared Company Admin Modular Architecture Design

## Purpose

Refactor the shared company administration application so it can serve every
current and future company from one maintained codebase. The refactor must
replace the monolithic `company/admin/index.php` with focused modules while
preserving existing URLs, POST action names, company isolation, database
transactions, and user-visible behavior.

The application remains a server-rendered PHP modular monolith. This avoids a
framework migration while creating clear boundaries that can be extracted or
extended safely.

## Current State

- `company/admin/index.php` contains approximately 12,080 lines and combines
  authentication, routing, schema setup, persistence, data loading, HTML, CSS,
  and JavaScript.
- `company/yovel-east/admin/index.php` is already a thin company entrypoint. It
  declares `BUILDERX_COMPANY_ADMIN_SLUG` and loads the shared application.
- Database records and HR form schemas are company-scoped. This tenant boundary
  must remain authoritative.
- Platform, HR, Sales/CRM, and Accounting/Finance currently share helpers and a
  single request dispatcher.

## Goals

1. Keep one shared company-admin application for every company.
2. Make `company/admin/index.php` a thin front controller.
3. Group code by business module and feature, not in one global file.
4. Preserve every existing GET route, query parameter, POST action, redirect,
   authorization check, CSRF check, and database table.
5. Keep company-specific directories limited to the company identifier and any
   explicitly supported configuration overrides.
6. Establish HR as the reference module for future module extraction.
7. Allow a future company to receive all shared improvements without copying
   application code.

## Non-Goals

- Replacing PHP or ADODB with a new framework or database library.
- Redesigning the current HR interface during the architecture extraction.
- Renaming routes, actions, database columns, or business keys.
- Moving tenant data into company-specific source files.
- Extracting independent deployable services.

## Architecture

```text
company/
|-- admin/
|   |-- index.php
|   |-- bootstrap/
|   |   |-- app.php
|   |   |-- auth.php
|   |   `-- context.php
|   |-- config/
|   |   `-- navigation.php
|   |-- core/
|   |   |-- audit.php
|   |   |-- database.php
|   |   |-- http.php
|   |   |-- validation.php
|   |   `-- view.php
|   |-- modules/
|   |   |-- hr/
|   |   |   |-- module.php
|   |   |   |-- schema.php
|   |   |   |-- data.php
|   |   |   |-- actions.php
|   |   |   |-- forms.php
|   |   |   |-- employees.php
|   |   |   |-- departments.php
|   |   |   |-- job-positions.php
|   |   |   `-- views/
|   |   |       |-- dashboard.php
|   |   |       |-- employee-profiles.php
|   |   |       |-- departments.php
|   |   |       `-- job-positions.php
|   |   |-- platform/
|   |   |-- sales-crm/
|   |   `-- accounting-finance/
|   |-- views/
|   |   |-- layout.php
|   |   `-- partials/
|   |       |-- header.php
|   |       |-- sidebar.php
|   |       |-- flash.php
|   |       `-- confirmation-dialog.php
|   `-- assets/
|       |-- css/
|       |   |-- admin.css
|       |   `-- hr.css
|       `-- js/
|           |-- admin.js
|           |-- hr.js
|           `-- hr-form-builder.js
|-- yovel-east/
|   `-- admin/index.php
`-- future-company/
    `-- admin/index.php
```

Files inside a module remain procedural PHP during this refactor. Introducing a
class hierarchy or custom autoloader would add risk without improving the tenant
or feature boundaries.

## Front Controller Contract

`company/admin/index.php` performs only these responsibilities:

1. Load the shared bootstrap.
2. Resolve the company from `BUILDERX_COMPANY_ADMIN_SLUG`.
3. Authenticate the company administrator.
4. Create a request context.
5. Dispatch a POST action to the owning module.
6. Load data for the active module.
7. Render the shared layout with the active module view.

The front controller must not contain SQL, module-specific HTML, embedded CSS,
or module-specific JavaScript.

## Module Contract

Each business module owns:

- its allowed sections and navigation metadata;
- idempotent schema setup;
- request action allow-list and dispatch;
- server-side validation and persistence functions;
- company-scoped data loading;
- module views and browser behavior;
- focused tests for routes and actions.

Modules communicate through existing shared primitives such as `bx_db()`,
authorization context, CSRF validation, redirect helpers, escaping helpers, and
audit logging. A module may not read a company slug directly from the URL when a
validated company context is already available.

## Company Isolation

The source code is shared, but all business data remains tenant-scoped.

- The company is resolved from the thin entrypoint's
  `BUILDERX_COMPANY_ADMIN_SLUG` value.
- Queries continue to use the current company key or company key hash.
- Form schemas, custom fields, custom values, users, roles, and records remain
  scoped to that company.
- Company-specific behavior must be represented by database configuration or an
  explicit allow-listed configuration file, never by copied module code.
- A new company entrypoint may identify the company only. It must not contain
  business rules, SQL, views, CSS, or JavaScript.

## Request and Persistence Flow

For a write request:

1. The front controller creates the authenticated company-admin context.
2. The shared dispatcher validates the CSRF token and selects an allow-listed
   module action.
3. The owning module validates authorization, company scope, and request data.
4. Existing ADODB transaction boundaries and parameterized SQL are preserved.
5. The module writes the audit event and reads the saved record back before
   committing.
6. The module returns a safe flash message and redirect target.
7. The browser reloads server-backed data from the same company scope.

No persistence behavior may be converted to client-only state during the
extraction.

## Asset Strategy

The existing CSS and JavaScript are extracted without redesigning behavior.

- Shared shell styles and interactions live in `assets/css/admin.css` and
  `assets/js/admin.js`.
- HR-only styles and interactions live in `assets/css/hr.css` and the HR
  JavaScript files.
- Assets are loaded only where practical, but the first extraction may load the
  shared and HR bundles together to preserve ordering.
- Inline server values are exposed through escaped `data-*` attributes or a
  small JSON configuration block. User or database values must never be
  interpolated into executable JavaScript.

## Extraction Sequence

### Stage 1: Shared Shell and Compatibility Harness

- Add route, action, and rendered-marker tests for the current application.
- Extract bootstrap, authentication, company context, shared helpers, layout,
  shared partials, CSS, and JavaScript.
- Keep all current request and response contracts unchanged.

### Stage 2: HR Reference Module

- Extract HR schema and data loading.
- Extract HR form schema and form-builder functions.
- Extract Employee Profiles, Departments, and Job Positions actions and views.
- Extract the remaining HR sections behind the same module dispatcher.
- Verify create and update workflows, custom fields, drag-and-drop features,
  modal behavior, redirects, and reload persistence.

### Stage 3: Remaining Modules

- Extract Platform Management.
- Extract Sales/CRM.
- Extract Accounting/Finance.
- Remove compatibility includes only after each module passes its route and
  persistence checks.

### Stage 4: Company Provisioning

- Add a reusable helper or generator that creates the thin company entrypoint.
- Verify two company slugs can use the same shared application while reading and
  writing isolated records.

Each stage must leave the application runnable and independently reversible.

## Compatibility Requirements

- Keep `company/<slug>/admin/` URLs valid.
- Keep `view`, `section`, edit keys, builder parameters, and modal query
  parameters valid.
- Keep every current POST action name valid.
- Keep current redirects and flash message behavior valid.
- Keep existing database schema and stable record keys valid.
- Keep the `company/yovel-east/admin/index.php` entrypoint valid.
- Do not require copied source code for a future company.

## Error Handling

- Unknown modules, sections, and actions fall back to their existing safe route
  or return the existing safe error message.
- Authentication and authorization failures must not reveal tenant data.
- Database exceptions remain caught at the action boundary, roll back active
  transactions, and return safe user-facing feedback.
- Missing module view files fail through one controlled renderer error rather
  than partially rendering the page.

## Testing and Verification

The extraction requires behavior-preserving tests rather than visual inspection
alone.

- PHP lint every extracted PHP file.
- Add a static architecture test that limits responsibilities in
  `company/admin/index.php` and confirms expected module files exist.
- Check authenticated and unauthenticated company-admin routes.
- Check Platform, HR, Sales/CRM, and Accounting/Finance routes return their
  expected page markers.
- Exercise HR create and update actions with direct database read-back.
- Verify CSRF rejection, authorization rejection, invalid input, and transaction
  rollback paths.
- Verify the frontend asset build.
- Use browser checks at desktop and mobile widths for the shared shell and HR
  modal workflows.
- Provision or fixture a second company and verify cross-company reads and writes
  are impossible.

## Completion Criteria

The architecture refactor is complete when:

1. `company/admin/index.php` is a thin front controller with no module SQL,
   module markup, embedded stylesheet, or module interaction script.
2. Every current route and action used by Yovel East remains functional.
3. HR, Platform, Sales/CRM, and Accounting/Finance have explicit module
   boundaries.
4. A second company can use the same shared code with isolated data.
5. No company directory contains copied shared application logic.
6. Lint, route, persistence, authorization, asset-build, and responsive browser
   verification pass.

## Delivery Decision

Implementation uses a staged modular-monolith extraction. Stage 1 and Stage 2
form the first implementation plan because they establish the reusable pattern
and migrate the actively developed HR area. Remaining modules follow in separate
plans after the reference module is verified.
