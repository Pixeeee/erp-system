# Yovel East Platform Management Design

## Goal

Copy the BuilderX Platform management concept into the Yovel East Company Admin portal so a company admin can manage company-scoped Modules, Users, Roles, Permissions, and Groups from the `Platform` area.

## Route

- Existing route: `/erpsystem/company/yovel-east/admin/`
- Platform view: `/erpsystem/company/yovel-east/admin/?view=platform`
- The existing company admin login, dashboard, ERP sidebar, dark mode, and logout behavior remain unchanged.

## Scope

This is the first real Platform management slice for Yovel East. It creates company-scoped management tables and forms for:

- Modules
- Users
- Roles
- Permissions
- Groups

The feature copies the BuilderX Administrator concepts, but does not reuse global Administrator users as company users. Yovel East Platform data belongs to the Yovel East company and must be scoped by the active company record.

## Platform Navigation

Under the existing `Platform` sidebar item, add a dropdown with:

- Modules
- Users
- Roles
- Permissions
- Groups

Each item opens the Platform view with an active tab or section:

- `?view=platform&section=modules`
- `?view=platform&section=users`
- `?view=platform&section=roles`
- `?view=platform&section=permissions`
- `?view=platform&section=groups`

Unknown sections fall back to `modules`.

## What Modules Can Do

A Module represents one ERP workspace or functional area available to Yovel East, such as HR Department, Accounting / Finance, Inventory / Warehouse, Manufacturing, Projects, or Mobile / Android Stockroom.

Modules let the company admin:

- enable or disable ERP areas for Yovel East
- organize features under each department
- connect permissions to modules
- grant module access by role, group, or user
- audit who changed module access and when

Modules are the bridge between the ERP feature map and access control. A role or group can only grant meaningful access when it is connected to specific modules and permissions.

## Data Model

Add company-scoped project tables. All tables include stable keys, company ownership, status fields, timestamps, and indexes by company.

### `project_company_module`

Stores the ERP modules enabled for Yovel East.

Fields:

- `module_key`
- `company_key_hash`
- `module_code`
- `module_name`
- `module_description`
- `module_icon`
- `module_status`
- timestamps

Unique key:

- `(company_key_hash, module_code)`

Initial modules are seeded from the existing ERP group metadata.

### `project_company_user`

Stores Yovel East company users created by the company admin.

Fields:

- `company_user_key`
- `company_key_hash`
- `user_login`
- `user_name`
- `user_email`
- `user_status`
- optional `user_password_hash` reserved for company-user login credentials
- timestamps

Unique key:

- `(company_key_hash, user_login)`
- `(company_key_hash, user_email)`

This step creates users for access planning and assignment. It does not add a separate company-user login flow.

### `project_company_role`

Stores company roles such as HR Manager, Finance Viewer, Warehouse Staff, and Project Coordinator.

Fields:

- `role_key`
- `company_key_hash`
- `role_name`
- `role_description`
- `role_status`
- timestamps

Unique key:

- `(company_key_hash, role_name)`

### `project_company_permission`

Stores permission actions that can be attached to modules.

Fields:

- `permission_key`
- `company_key_hash`
- `module_key`
- `permission_code`
- `permission_name`
- `permission_description`
- `permission_status`
- timestamps

Unique key:

- `(company_key_hash, permission_code)`

Example permissions:

- `hr.view`
- `hr.create`
- `finance.view`
- `inventory.scan`
- `inventory.adjust`
- `projects.approve`

### `project_company_group`

Stores user groups such as HR Team, Warehouse Team, Finance Reviewers, and Project Leads.

Fields:

- `group_key`
- `company_key_hash`
- `group_name`
- `group_description`
- `group_status`
- timestamps

Unique key:

- `(company_key_hash, group_name)`

### Link Tables

Add link tables for access assignment:

- `project_company_user_role`
- `project_company_user_group`
- `project_company_role_permission`
- `project_company_group_permission`
- `project_company_user_permission`

Each link table includes:

- stable relationship key
- company hash
- source key
- target key
- created timestamp

These relationships support both default role/group access and direct exceptions.

## Forms

Each Platform section has a two-panel layout:

- left panel: table/list of records for the active section
- right panel: create/update form and selected record summary

Every persisted form must:

- use the existing CSRF token
- open a confirmation dialog before submit
- submit via POST to the same route
- use ADODB transactions
- use parameterized SQL
- audit creates and updates
- read back the saved row before commit
- rehydrate the list from the server after redirect

## Section Behavior

### Modules

The Modules section shows the ERP modules for Yovel East.

Company admin can:

- create a module
- update module name, description, icon, and status
- enable or disable a module
- review how many permissions are attached

### Users

The Users section shows company users.

Company admin can:

- create a company user
- update name, email, login, and status
- assign roles
- assign groups
- grant direct permissions when needed

### Roles

The Roles section shows reusable access roles.

Company admin can:

- create a role
- update role description and status
- assign module permissions to the role

### Permissions

The Permissions section shows module actions.

Company admin can:

- create a permission under a module
- update permission name, description, and status
- use permissions in roles, groups, and direct user grants

### Groups

The Groups section shows collections of users.

Company admin can:

- create a group
- update group description and status
- assign users to the group
- assign module permissions to the group

## Permission Model

- Company admin sees and manages all Yovel East Platform data.
- Department users see only their department by default.
- Extra access can be granted through roles, groups, or direct permissions.
- Direct permissions are exceptions and should be visible in the user summary.
- Every create, update, status change, and access assignment is audited.

## Error Handling

- Missing company record keeps the existing unavailable state.
- Missing or invalid company-admin session returns to the login page.
- Invalid section falls back to Modules.
- Invalid CSRF shows a safe error and does not write.
- Invalid keys, duplicate names, duplicate login/email, and missing required fields show safe user-facing errors.
- SQL/internal details stay out of the UI.

## Testing

Validation should include:

- PHP lint for `app/foundation.php` and `company/yovel-east/admin/index.php`.
- Schema check confirms company Platform tables and unique indexes exist.
- Modules are seeded from the ERP metadata for Yovel East.
- Authenticated Platform page shows Modules, Users, Roles, Permissions, and Groups navigation.
- Create and update work for Modules, Users, Roles, Permissions, and Groups.
- Assignment writes work for user-role, user-group, role-permission, group-permission, and user-permission links.
- Invalid CSRF does not write.
- Duplicate names/logins/emails are rejected safely.
- After each save, the committed row is visible after redirect and full reload.
- Audit rows are written for creates, updates, status changes, and access assignment changes.
