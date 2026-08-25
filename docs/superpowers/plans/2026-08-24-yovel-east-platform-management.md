# Yovel East Platform Management Implementation Plan

## Objective

Build the approved Yovel East Company Platform management workspace in the shared company admin route. The Platform area will let a company admin manage Modules, Users, Roles, Permissions, and Groups with audited, company-scoped database writes.

## Current Route

- `company/yovel-east/admin/index.php` delegates to `company/admin/index.php`.
- Implementation belongs in `company/admin/index.php` so the Yovel East wrapper uses the active shared company-admin shell.

## Data Model

Create route-owned company-scoped tables if missing:

- `project_company_module`
- `project_company_user`
- `project_company_role`
- `project_company_permission`
- `project_company_group`
- `project_company_user_role`
- `project_company_user_group`
- `project_company_user_permission`
- `project_company_role_permission`
- `project_company_group_permission`

Seed ERP modules from the existing ERP group metadata for the active company.

## Platform UI

- Add Platform submenu links: Modules, Users, Roles, Permissions, Groups.
- Route sections through `?view=platform&section=...`.
- Default Platform section is Modules.
- Each section shows existing records plus a create/edit form.
- Forms use native validation and an accessible confirmation modal before submit.

## Save Behavior

- Verify CSRF.
- Require an authenticated company admin.
- Use ADODB transaction boundaries for every create/update.
- Use parameterized SQL.
- Validate selected role/group/permission/module keys against the same `company_key_hash`.
- Audit every persisted access-management change inside the transaction.
- Read back the saved record before commit.

## Verification

- PHP lint edited files.
- Exercise login and Platform pages through localhost.
- Verify the new tables exist and seed modules are present for Yovel East.
- Run a browser screenshot check for the Platform Modules and Users sections.
