# Yovel East Company Admin Dashboard Design

## Goal

After a successful Yovel East company admin login, replace the current placeholder page with a BuilderX-style company dashboard focused only on Yovel East. The page should feel like the existing BuilderX Administrator dashboard, but with Administrator-only controls removed and company-scoped navigation added.

## Scope

This step builds the post-login dashboard shell, navigation, and ERP feature map. It does not yet implement user, role, permission, or ERP CRUD workflows.

The route remains:

- `/erpsystem/company/yovel-east/admin/`

The existing login behavior remains unchanged:

- Resolve the active `Yovel East` company from `project_company`.
- Authenticate against `project_company_admin`.
- Keep the company-admin session separate from the BuilderX system administrator session.
- Do not expose `company_key` or hash fields in visible UI.

## Header

The authenticated dashboard header should copy the BuilderX dashboard proportions and dark/light styling.

Keep:

- Company title: `Yovel East`
- Context line or breadcrumb: `Company Admin > Dashboard` or the selected module
- `shadcn/ui` button
- Light/dark mode toggle
- Sign-out access, positioned in the company-admin account area or header action area

Remove:

- Sharingan
- User Portal
- Foundation
- UI-UX Flow
- Phase Manager

## Sidebar

The sidebar should use the BuilderX admin dashboard visual structure, but be company-scoped.

Brand area:

- Title: `Yovel East`
- Subtitle: `Company Admin Portal`

Primary navigation:

- `Dashboard`
- `Platform`

ERP navigation groups:

- HR Department
- Accounting / Finance
- Sales / CRM
- Buying / Procurement
- Inventory / Warehouse
- Manufacturing
- Projects
- Support / Service
- Assets / Maintenance
- Operations
- Compliance / Localization
- Mobile / Android Stockroom

Remove:

- Phase Builder
- Administrator-only Company Management
- Settings
- Other companies and project tree entries

## Dashboard View

The default authenticated view is `Dashboard`.

It should show a Yovel East company overview with compact BuilderX-style cards and panels:

- Company scope summary
- Branch count
- Admin account status
- Active ERP module count
- Platform access summary
- Pending setup or approval placeholders

Below the overview, show the ERP feature map grouped by department. Each group should list the relevant modules as navigation-ready items or disabled placeholders. The feature list comes from the requested ERP structure:

- HR: employee profiles, departments, positions, attendance, leave, payroll access, recruitment, documents, reports
- Finance: chart of accounts, cost centers, invoices, journals, payments, bank reconciliation, budgets, closing, financial/tax reports
- Sales / CRM: leads, opportunities, campaigns, customers, quotations, sales orders, credit limits, analytics, performance
- Procurement: suppliers, material requests, RFQs, supplier quotations, purchase orders, purchase receipts, analytics, handoff
- Inventory: items, warehouses, stock entries, stock ledger, reconciliation, batch/serial, barcodes, reorder, putaway, picking, packing, shipment, reports
- Manufacturing: BOM, production plan, work orders, job cards, MRP, forecasting, quality handoff, reports
- Projects: projects, tasks, timesheets, collaboration, summaries, delayed task reports, portal access
- Support: tickets, SLA rules, warranty claims, first response, summaries, customer portal
- Assets / Maintenance: asset records, depreciation, fixed asset register, schedules, quality inspection, reports
- Operations: scheduled jobs, notifications, workers, sync conflicts, import/export, alerts, release checklist
- Compliance: tax templates, VAT, e-invoice reports, regional compliance, audit evidence, regulatory exports
- Mobile Stockroom: receiving, barcode scanning, putaway, picking, packing, delivery handoff, stock count, offline sync, conflict review, scanner recovery

## Platform View

The `Platform` view is the company-admin control area. In this step it should render the workspace structure and placeholders for the future persisted workflows.

Show sections for:

- User accounts
- Roles
- Permissions / RBAC
- Cross-department access grants
- Approval rules
- Delegated authority
- Audit logs
- Security and integrations

The page copy should make the permission model clear:

- Admin sees everything.
- Department users see only their department by default.
- Admin can grant selected extra access across departments.
- Every access change is audited.

## Architecture

Keep this implementation in `company/yovel-east/admin/index.php` for now. The route can define small PHP arrays for company navigation and ERP feature metadata, then render authenticated views based on a safe `view` query parameter.

Suggested view routing:

- `?view=dashboard` shows the dashboard overview and ERP feature map.
- `?view=platform` shows the company Platform control placeholders.
- Unknown views fall back to `dashboard`.

No new database writes are required for this shell step. Existing login/logout writes continue using the current transaction and audit behavior.

## Error Handling

- If the company record is missing, keep the existing unavailable state.
- If the session is missing or invalid, show the existing login page.
- If an unsupported view is requested, fall back to Dashboard.
- Preserve CSRF validation for logout.
- Keep internal errors out of the UI.

## Testing

Validation should include:

- PHP lint for `company/yovel-east/admin/index.php`.
- Route returns HTTP 200 for anonymous login page.
- Valid `admin / admin12345` login reaches the dashboard shell.
- Authenticated `?view=dashboard` includes Yovel East, Dashboard, Platform, and ERP groups.
- Authenticated `?view=platform` includes user/role/permission/audit placeholders.
- Authenticated header does not include Sharingan, User Portal, Foundation, UI-UX Flow, or Phase Manager.
- Authenticated sidebar does not include Phase Builder, Company Management, Settings, or other company/project tree entries.
- Logout ends the company-admin session and returns to the login page.
