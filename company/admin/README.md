# Shared Company Admin

`company/admin/` is the single shared application used by every company admin
portal. Company-specific directories identify the tenant; they do not contain a
copy of this application.

## Request Flow

1. `company/<company-slug>/admin/index.php` defines
   `BUILDERX_COMPANY_ADMIN_SLUG`.
2. The company entrypoint requires `company/admin/index.php`.
3. The shared front controller loads `bootstrap/app.php`.
4. The bootstrap loads the common foundation, core helpers, and every business
   module before `bootstrap/controller.php` dispatches the request.
5. The controller prepares server-backed view data and renders
   `views/layout.php`.
6. The layout includes the workspace view owned by the active module.

## Folder Ownership

```text
company/admin/
|-- index.php                  Thin front controller only
|-- bootstrap/                 Application loading and request dispatch
|-- core/                      Cross-module company-admin helpers
|-- modules/
|   |-- hr/                    HR schema, forms, records, and views
|   |-- platform/              Company platform management
|   |-- sales-crm/             Sales and CRM records and views
|   `-- accounting-finance/    Finance records and views
|-- views/                     Shared shell and default dashboard
`-- assets/                    Shared browser assets
```

HR is the reference module. Its backend is divided by responsibility:

- `navigation.php`: HR sections and route allow-lists.
- `schema.php`: idempotent HR database and default field schema setup.
- `forms.php`: configurable HR forms, custom fields, and form builder behavior.
- `data.php`: company-scoped HR read models.
- `employees.php`: Employee Profile persistence.
- `departments.php`: Department persistence.
- `job-positions.php`: Job Position persistence.
- `views/`: one template for each active HR workspace.

## Adding a Company

A company entrypoint contains only the company slug and shared application
require:

```php
<?php
declare(strict_types=1);

define('BUILDERX_COMPANY_ADMIN_SLUG', 'future-company');
require __DIR__ . '/../../admin/index.php';
```

The company must already exist in the database. All company records, form
schemas, permissions, and custom values remain isolated by the validated company
key or company key hash.

Never copy `company/admin/` into a company directory. Shared improvements belong
in this application and become available to every company automatically.

## Development Rules

- Do not add business logic, SQL, markup, CSS, or JavaScript to `index.php`.
- Put a change in the module that owns the business behavior.
- Put shared shell markup in `views/` and module workspace markup in the module's
  `views/` directory.
- Keep existing GET parameters and POST action names backward compatible.
- Keep all writes company-scoped and use the existing ADODB transaction, audit,
  authorization, CSRF, and direct read-back conventions.
- Run `php tests/company-admin-modular-architecture.php` after structural changes.
- Lint every PHP file under `company/admin/` and test the existing company route.
