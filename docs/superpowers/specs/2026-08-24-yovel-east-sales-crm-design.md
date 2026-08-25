# Yovel East Sales/CRM Design

## Goal

Turn the Yovel East Sales/CRM dropdown into a real company-admin workspace. All 10 Sales/CRM items currently point to dashboard anchors. This work traces them into routed sections, then builds the module one feature at a time while keeping the Sales/CRM sidebar focused only on customer pipeline, sales documents, credit controls, and sales performance.

## References

- Current local route: `/erpsystem/company/yovel-east/admin/`
- Current sidebar source: `company/admin/index.php`
- ERPNext CRM demo route: `https://erpnext-demo.frappe.cloud/app/crm`
- ERPNext source: `https://github.com/frappe/erpnext`
- ERPNext CRM and Selling docs: Leads, Opportunities, Customers, Quotations, Sales Orders, Sales Team, Selling Settings, and Territory.

ERPNext separates early CRM records from selling documents. BuilderX should follow that workflow shape without copying Frappe internals directly:

1. Capture a Lead.
2. Qualify it into an Opportunity.
3. Link Campaign, Customer, Contact, Territory, and Salesperson information.
4. Create a Quotation.
5. Convert a won quote into a Sales Order.
6. Track credit limits and performance reports from the same company-scoped data.

## Scope

Build only the Sales/CRM building in this cycle. Other ERP departments keep their current behavior unless their sidebar rendering must be generalized so Sales/CRM can route cleanly.

The current Sales/CRM links are:

- `./?view=dashboard#erp-sales-crm-feature-leads`
- `./?view=dashboard#erp-sales-crm-feature-opportunities`
- `./?view=dashboard#erp-sales-crm-feature-campaigns`
- `./?view=dashboard#erp-sales-crm-feature-customers`
- `./?view=dashboard#erp-sales-crm-feature-quotations`
- `./?view=dashboard#erp-sales-crm-feature-sales-orders`
- `./?view=dashboard#erp-sales-crm-feature-customer-credit-limits`
- `./?view=dashboard#erp-sales-crm-feature-sales-analytics`
- `./?view=dashboard#erp-sales-crm-feature-salesperson-performance`
- `./?view=dashboard#erp-sales-crm-feature-territory-performance`

They become:

- `?view=sales-crm&section=leads`
- `?view=sales-crm&section=opportunities`
- `?view=sales-crm&section=campaigns`
- `?view=sales-crm&section=customers`
- `?view=sales-crm&section=quotations`
- `?view=sales-crm&section=sales-orders`
- `?view=sales-crm&section=customer-credit-limits`
- `?view=sales-crm&section=sales-analytics`
- `?view=sales-crm&section=salesperson-performance`
- `?view=sales-crm&section=territory-performance`

Unknown Sales/CRM sections fall back to `leads`.

## Build Order

1. Sales/CRM foundation: routing, sidebar links, shared section metadata, form schema tables, and form builder shell.
2. Leads: first complete CRUD workflow, list, add/edit popup, editable form schema, status actions, and audit trail.
3. Opportunities: qualification pipeline linked to Leads and Customers.
4. Campaigns: campaign source tracking for Leads, Opportunities, Quotations, and Sales Orders.
5. Customers: reusable customer master data for quotations, sales orders, credit limits, and reports.
6. Quotations: itemized quote records that can later convert into Sales Orders.
7. Sales orders: confirmed orders linked to Customer and optional Quotation.
8. Customer credit limits: customer credit profile and limit tracking.
9. Sales analytics: summary cards and report tables.
10. Salesperson performance: sales team contribution and salesperson report.
11. Territory performance: territory assignment and report.

This order is deliberate. Leads establish the first record and the form-builder standard. Opportunities, Campaigns, and Customers provide the references needed before quotations, orders, credit limits, and reports become meaningful.

## Standard Layout

Every Sales/CRM section uses the same BuilderX company-admin shell and a two-panel layout:

- Left main panel: 12 fraction units.
- Right feature/function panel: 8 fraction units.

The left panel holds the most important working data: lists, kanban boards, document tables, pipeline rows, detail summaries, and report outputs.

The right panel holds tools and functions: add/edit actions, form builder controls, drag and drop widgets, section shortcuts, saved filters, stage controls, import/export actions, and workflow helpers.

On smaller screens, the panels stack with the main panel first and the feature panel second.

## Editable Form Standard

All Sales/CRM forms are editable and customizable by the company admin. No Sales/CRM form should be treated as permanently hardcoded.

Each record type has a default system form schema. The admin can adjust the active company form without changing source code:

- Reorder fields.
- Rename visible labels.
- Mark fields required or optional when the underlying database allows it.
- Hide non-required fields.
- Group fields into popup sections.
- Move fields between sections.
- Choose compact field widths where the UI supports it.
- Restore the default form schema.

The form builder belongs in the right panel or inside the add/edit popup as a dedicated builder tab. The normal data-entry tab remains the default so everyday users are not forced through configuration controls.

Form customization is company scoped. A change in Yovel East must not alter other companies.

## Form Builder Data Model

Add shared Sales/CRM form customization tables so every section can use one standard mechanism.

### `project_company_form_schema`

Stores the active form definition per company, module, and record type.

Fields:

- `form_schema_key`
- `company_key`
- `company_key_hash`
- `module_code`
- `record_type`
- `schema_status`
- `schema_version`
- `schema_json`
- `created_by_admin_key`
- `updated_by_admin_key`
- timestamps

Unique key:

- `(company_key_hash, module_code, record_type, schema_version)`

Current active schema is the newest `ACTIVE` version for the record type.

### `project_company_form_schema_audit`

Stores each schema change for review and rollback.

Fields:

- `form_schema_audit_key`
- `company_key`
- `company_key_hash`
- `form_schema_key`
- `module_code`
- `record_type`
- `audit_action`
- `previous_schema_json`
- `next_schema_json`
- `created_by_admin_key`
- timestamp

## Form Schema Rules

Each default schema is a JSON document with:

- `recordType`
- `version`
- `sections`
- `fields`
- `requiredSystemFields`
- `readonlySystemFields`

Each field includes:

- `key`
- `label`
- `type`
- `section`
- `required`
- `visible`
- `width`
- `sortOrder`
- `options`

The UI may reorder, rename, hide, or regroup fields, but it cannot remove system fields needed for persistence, ownership, audit, or relational integrity. Required database fields remain required even if the admin tries to hide them.

## Sales/CRM Sections

### Leads

The first complete section. The left panel shows lead records with filters for lead name, organization, status, source, territory, and owner. The right panel shows lead actions, pipeline widgets, and form builder controls.

Default lead fields:

- Lead code
- Lead name
- Organization
- Lead status
- Lead source
- Campaign
- Territory
- Salesperson or owner
- Email
- Phone
- Mobile
- Website
- Industry
- Estimated value
- Next contact date
- Notes

Lead statuses:

- `DRAFT`
- `OPEN`
- `QUALIFIED`
- `CONVERTED`
- `LOST`
- `INACTIVE`
- `DELETED`

### Opportunities

Opportunities track qualified selling chances. They can link to a Lead or Customer and later feed Quotations.

Default fields:

- Opportunity code
- Opportunity title
- Party type
- Lead
- Customer
- Opportunity status
- Sales stage
- Expected closing date
- Probability
- Estimated value
- Campaign
- Territory
- Salesperson
- Notes

### Campaigns

Campaigns organize lead sources and sales attribution.

Default fields:

- Campaign code
- Campaign name
- Campaign status
- Campaign type
- Start date
- End date
- Budget
- Expected revenue
- Notes

### Customers

Customers are reusable commercial parties used by quotations, orders, credit limits, and reports.

Default fields:

- Customer code
- Customer name
- Customer type
- Customer group
- Territory
- Tax ID
- Default currency
- Primary contact name
- Email
- Phone
- Billing address
- Shipping address
- Customer status
- Notes

### Quotations

Quotations represent proposed sales terms and item lines.

Default fields:

- Quotation code
- Customer
- Opportunity
- Quotation status
- Quotation date
- Valid until
- Currency
- Price list
- Salesperson
- Territory
- Items
- Taxes
- Discount
- Terms
- Notes

### Sales Orders

Sales orders represent confirmed customer orders.

Default fields:

- Sales order code
- Customer
- Quotation
- Order status
- Order date
- Delivery date
- Customer purchase order
- Currency
- Items
- Taxes
- Discount
- Salesperson
- Territory
- Notes

### Customer Credit Limits

Credit limits track sales risk by customer.

Default fields:

- Customer
- Credit limit
- Currency
- Credit hold status
- Effective date
- Expiry date
- Approved by
- Notes

### Sales Analytics

Analytics is a reporting section, not a CRUD master. The left panel shows totals, funnel conversion, revenue by status, campaign contribution, and order value summaries. The right panel holds date range, salesperson, territory, campaign, and customer filters.

### Salesperson Performance

Salesperson performance reports contribution, open pipeline, won revenue, lost value, and activity follow-up. The right panel holds filters and report customization controls.

### Territory Performance

Territory performance reports lead count, opportunity value, quotation value, sales order value, conversion rate, and customer count by territory. The right panel holds filters and grouping controls.

## Architecture

Extend `company/admin/index.php`, because `company/yovel-east/admin/index.php` delegates to it.

Add a Sales/CRM view beside the existing dashboard, platform, and HR views:

- `dashboard`
- `platform`
- `hr`
- `sales-crm`

Add shared Sales/CRM metadata functions so routing, sidebar active state, mobile navigation, titles, descriptions, section links, and default form schemas use one source of truth.

Keep the implementation server-rendered with native POST forms, ADODB transactions, existing confirmation dialogs, and compact JavaScript only where needed for modals, drag and drop, and form builder interactions.

## Data Model

All Sales/CRM tables are company scoped by the active Yovel East company record. Persist both `company_key` and `company_key_hash`.

Create these foundation tables first:

- `project_company_form_schema`
- `project_company_form_schema_audit`
- `project_company_sales_lead`
- `project_company_sales_campaign`
- `project_company_sales_customer`
- `project_company_sales_territory`
- `project_company_salesperson`

Add section tables as each feature is implemented:

- `project_company_sales_opportunity`
- `project_company_sales_quotation`
- `project_company_sales_quotation_item`
- `project_company_sales_order`
- `project_company_sales_order_item`
- `project_company_customer_credit_limit`

Reports should read from these company-scoped tables. They should not create report-specific source-of-truth tables unless cached reporting becomes necessary later.

## Data Flow

1. Company admin opens a Sales/CRM route.
2. Server validates the company and company-admin session.
3. Sales/CRM schema is ensured.
4. Default form schemas are seeded when missing.
5. Active company-customized form schemas are loaded.
6. The current section loads its records and reference data.
7. Add/Edit opens a popup using the active schema.
8. Form builder changes save through a schema version write, not source code changes.
9. Record saves validate CSRF, company ownership, schema fields, required system fields, and field-specific rules.
10. Writes run inside ADODB transactions with parameterized SQL.
11. The saved record is read back and compared.
12. Audit is written.
13. Request redirects back to the same Sales/CRM section and rehydrates from the database.

## Validation Rules

- Every persisted record must belong to the active company hash.
- Codes must be unique per company and record type.
- System required fields cannot be hidden or skipped.
- Emails must be valid when present.
- Dates must be valid `YYYY-MM-DD` dates when present.
- Numeric values such as estimated value, probability, budget, credit limit, quantities, rates, taxes, and discounts must be validated before persistence.
- Relationship keys must point to records under the same company hash.
- Soft deletes set status to `DELETED`; normal UI actions do not physically delete Sales/CRM records.
- Form schema JSON must be decoded, normalized, and checked against allowed field keys before saving.

## Error Handling

Validation failures show the existing flash error and return to the active Sales/CRM section. Database failures roll back and show a concise message. Read-back mismatches roll back before success is shown. Invalid form schema updates are rejected without changing the active form version.

## Testing

Foundation verification:

- PHP lint for `company/admin/index.php` and `company/yovel-east/admin/index.php`.
- Authenticated HTTP check that each Sales/CRM sidebar link points to `?view=sales-crm&section=<section>`.
- Authenticated HTTP check that every Sales/CRM route renders and unknown sections fall back to Leads.
- Database schema check for form schema and first-slice Sales/CRM tables.

Leads verification:

- Create, update, and soft-delete a temporary Lead through the real POST path.
- Verify direct database read-back.
- Save a Lead form schema change through the form builder.
- Verify the Lead add/edit popup renders with the customized label/order after redirect.
- Restore default Lead schema and verify the popup returns to defaults.

Later sections repeat the same route, CRUD, form-builder, direct read-back, and report verification pattern.

## Non-Goals

- Do not build full accounting or invoicing inside Sales/CRM.
- Do not convert non-Sales/CRM departments into real modules in this cycle.
- Do not replace the company-admin PHP route with a frontend SPA.
- Do not let form builder changes alter physical database schema.
- Do not allow admins to remove system fields required for persistence, ownership, audit, or relational integrity.
