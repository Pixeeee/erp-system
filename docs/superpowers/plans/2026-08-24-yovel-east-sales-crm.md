# Yovel East Sales/CRM Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Convert all 10 Yovel East Sales/CRM sidebar links into real routed sections and implement the first complete Leads workflow with a reusable company-scoped form builder foundation.

**Architecture:** Extend `company/admin/index.php`, because `company/yovel-east/admin/index.php` delegates to it. Add a `sales-crm` view with section metadata, idempotent Sales/CRM schema setup, form schema persistence, Lead CRUD, and two-panel server-rendered UI. Keep all writes in ADODB transactions with parameterized SQL, audit logging, direct read-back, and redirect rehydration.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, server-rendered HTML, existing BuilderX utility classes, native POST forms, compact vanilla JavaScript for modal and drag/drop form-builder behavior.

## Global Constraints

- Build only the Sales/CRM building in this cycle.
- Convert all 10 Sales/CRM links to `?view=sales-crm&section=<section>`.
- Use the two-panel layout: left main panel 12 fraction units, right feature/function panel 8 fraction units.
- Make every Sales/CRM form editable and customizable through a company-scoped form schema.
- Do not let form builder changes alter physical database schema.
- Do not allow admins to remove system fields required for persistence, ownership, audit, or relational integrity.
- Use parameterized ADODB writes, transactions, audit logging, direct read-back verification, and server rehydration for persisted changes.
- Keep non-Sales/CRM departments unchanged.

---

### Task 1: Sales/CRM Routing And Link Trace

**Files:**
- Modify: `company/admin/index.php`
- Verify: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: `yovel_admin_view(): string`, `yovel_admin_erp_groups(): array`, `yovel_admin_feature_anchor(array $group, string $feature): string`
- Produces: `yovel_admin_sales_crm_sections(): array`, `yovel_admin_sales_crm_section(): string`, `yovel_admin_erp_feature_href(array $group, string $feature): string`

- [x] Add `sales-crm` to the allowed view list in `yovel_admin_view()`.
- [x] Add `yovel_admin_sales_crm_sections()` with these keys: `leads`, `opportunities`, `campaigns`, `customers`, `quotations`, `sales-orders`, `customer-credit-limits`, `sales-analytics`, `salesperson-performance`, `territory-performance`.
- [x] Add `yovel_admin_sales_crm_section()` that falls back to `leads`.
- [x] Replace HR-only link routing with `yovel_admin_erp_feature_href()`, returning Sales/CRM routes for Sales/CRM features, HR routes for HR features, and dashboard anchors for every other group.
- [x] Update sidebar active/open state and mobile navigation so Sales/CRM is reachable and highlighted.
- [x] Verify all 10 authenticated sidebar hrefs contain `?view=sales-crm&section=`.

### Task 2: Sales/CRM Schema And Form Schema Utilities

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `bx_db(): ADOConnection`, `yovel_admin_db_execute(ADOConnection $db, string $sql, array $params, string $operation): void`, `bx_uuid(): string`, `bx_audit(...)`
- Produces: `yovel_admin_sales_crm_schema(): void`, `yovel_admin_sales_crm_default_form_schemas(): array`, `yovel_admin_sales_crm_active_schema(array $company, string $recordType): array`, `yovel_admin_save_form_schema(array $company, array $admin): string`, `yovel_admin_reset_form_schema(array $company, array $admin): string`

- [x] Add idempotent `CREATE TABLE IF NOT EXISTS` statements for `project_company_form_schema`, `project_company_form_schema_audit`, `project_company_sales_lead`, `project_company_sales_campaign`, `project_company_sales_customer`, `project_company_sales_territory`, and `project_company_salesperson`.
- [x] Add explicit indexes for company hash, status, record type, code, lead source, campaign, territory, salesperson, and updated-at lookups.
- [x] Define default form schemas for `lead`, `opportunity`, `campaign`, `customer`, `quotation`, `sales-order`, and `customer-credit-limit`.
- [x] Implement active schema loading that seeds the default schema when a record type has no active company schema.
- [x] Implement form schema save with JSON normalization, allowed field-key validation, required system field enforcement, transaction, audit row, read-back, and redirect rehydration.
- [x] Implement form schema reset that writes a new active default version for the selected record type.

### Task 3: Leads Persistence

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `yovel_admin_sales_crm_schema()`, `yovel_admin_sales_crm_active_schema(array $company, string $recordType): array`, `yovel_admin_optional_company_key(...)`, `yovel_admin_optional_date(...)`
- Produces: `yovel_admin_sales_crm_data(array $company): array`, `yovel_admin_save_sales_lead(array $company, array $admin): string`, `yovel_admin_set_sales_lead_status(array $company, array $admin): string`

- [x] Load leads joined to campaign, territory, and salesperson display names.
- [x] Validate lead code, lead name, status, email, next contact date, numeric estimated value, campaign ownership, territory ownership, and salesperson ownership.
- [x] Implement create/update as one complete upsert path that preserves `lead_key` on duplicate lead code.
- [x] Check every ADODB write for `false`.
- [x] Read back every submitted persisted field before commit.
- [x] Audit create, update, and status changes inside the same transaction.
- [x] Wire POST actions `save_sales_lead`, `set_sales_lead_status`, `save_sales_form_schema`, and `reset_sales_form_schema`.

### Task 4: Sales/CRM UI

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: `$activeView`, `$activeSalesCrmSection`, `$salesCrmSections`, `$salesCrmData`, `$salesCrmSchemas`, `$editSalesLead`
- Produces: Authenticated `?view=sales-crm&section=*` screens, Leads list, Add/Edit Lead popup, and right-panel form builder.

- [x] Add Sales/CRM view heading, description, mobile nav item, and section nav.
- [x] Render every Sales/CRM section in a two-panel `xl:grid-cols-[minmax(0,12fr)_minmax(24rem,8fr)]` layout.
- [x] For Leads, render summary metrics, filters, lead table, empty state, status actions, and Add Lead trigger.
- [x] Render Add/Edit Lead as an accessible popup with header, scrollable body, and footer.
- [x] Render Lead fields from the active form schema, respecting label, visible, required, section, width, and sort order.
- [x] Render the right-panel form builder for the active record type with reorder buttons, drag/drop, label editing, required toggles, visible toggles, section assignment, save, and restore default.
- [x] For later sections, render routed placeholders with the active default form builder where the section represents a future CRUD form.

### Task 5: JavaScript And Verification

**Files:**
- Modify: `company/admin/index.php`

**Interfaces:**
- Consumes: existing confirmation modal JavaScript, `data-confirm-submit`
- Produces: Sales/CRM modal controls and form-builder JSON serialization.

- [x] Add Sales/CRM modal open/close behavior with Escape and backdrop close.
- [x] Add focus return for the Add/Edit Lead popup.
- [x] Add form-builder reorder and drag/drop behavior.
- [x] Serialize the form-builder state into `schema_json` before submitting.
- [x] Run `php -l company/admin/index.php` and `php -l company/yovel-east/admin/index.php`.
- [x] Run authenticated HTTP checks for all 10 Sales/CRM routes.
- [x] Create, update, and soft-delete a temporary Lead through the real POST path.
- [x] Save a temporary Lead form schema label/order change, verify rendered output, then reset the schema.
