# Sales / CRM ERPNext Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close only the verified Sales / CRM gaps against ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325`, preserving the existing Lead workflow and integrating with authoritative Finance and other module contracts.

**Architecture:** Keep `company/admin/modules/sales-crm/functions.php` as the module entry point and compatibility surface while moving new behavior into focused module-local services and views. Use company-scoped records, stable UUID keys, explicit document lifecycles, shared Form Builder and modal/confirmation adapters supplied by orchestration, and service boundaries for Finance, Inventory, Buying, Compliance, Projects, and Support. The Sales / CRM module never writes another module's authoritative tables.

**Tech Stack:** PHP 8.5 runtime with PHP 8.1 compatibility, ADODB/MySQL, server-rendered PHP, Tailwind 4/shadcn tokens already present, vanilla JavaScript through orchestration-owned shared interaction contracts, PHP executable tests, Playwright browser checks, and curl route checks.

## Global Constraints

- Use ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325` as the Sales / CRM parity baseline.
- Preserve every current Sales / CRM route, the existing Lead CRUD/status behavior, persisted form-schema data, and unrelated working-tree changes.
- Every Sales / CRM configurable record type uses the universal module-local Form Builder adapter: target selection, rows, columns, sections, fields, labels, types, options, required/visible state, width, defaults, validation, preview, draft, publish, archive, and version history.
- Published form versions are immutable, submitted records retain their bound form version, and protected workflow/system fields cannot be removed or hidden.
- Every workspace and feature page uses a 20-column desktop composition: left main panel `12/20`, right action/tools panel `8/20`, stacking main-first on narrower screens.
- Every Add, New, Create, or Insert action opens an accessible modal owned by the page body.
- Submit opens a separate confirmation dialog. Persistence begins only after Confirm; Cancel returns to the populated form.
- Every write authorizes company/admin scope, validates server-side input, uses parameterized SQL, owns one ADODB transaction, locks conflicting lifecycle rows, writes audit history, performs exact read-back verification before commit, rolls back on failure, and rehydrates server values and errors.
- Accounting / Finance remains authoritative for receivables, payments, taxes, GL postings, credit exposure, closing, and financial statements. Sales / CRM consumes Finance contracts and does not modify Finance files or tables.
- Inventory remains authoritative for items, stock, availability, reservations, deliveries, batches, and serials. Buying remains authoritative for procurement requests and orders.
- Immediate backward-compatible improvements are allowed only inside `company/admin/modules/sales-crm/` and `tests/sales-crm-*`. Shared or cross-module changes must be requested from orchestration.
- Implement functional parity without copying ERPNext GPL source code, UI copy, logos, or branding.
- Android/mobile application work remains deferred; responsive web behavior is required.

---

## Audit Snapshot

Audit scope contained two runtime files and no focused Sales / CRM tests:

- `company/admin/modules/sales-crm/functions.php` (819 lines)
- `company/admin/modules/sales-crm/views/workspace.php` (242 lines)
- `tests/sales-crm-*` (no files found)

Current verified behavior to preserve:

- Ten Sales / CRM route keys with unknown-section fallback to Leads.
- Company-scoped Lead table, create/update upsert, status mutation, server validation, row locking, parameterized writes, audit calls, exact read-back, and rollback.
- Company-scoped active/inactive form-schema versions for Lead, Opportunity, Campaign, Customer, Quotation, Sales Order, and Customer Credit Limit.
- Lead list, filters, Add/Edit modal markup, status actions, and schema-driven field rendering.
- PHP lint passes for both module files; `php tests/company-admin-modular-architecture.php` passes.

Verified gaps affecting every package:

- No ledger row qualifies as `COMPLETE` because no `tests/sales-crm-*` behavioral evidence exists.
- The current desktop grid is `8fr/4fr`, not the approved `12fr/8fr` composition.
- Nine non-Lead sections render an explicit placeholder; report controls do not execute reports.
- Form Builder can reorder known fields and change label/section/required/visible/width, but cannot add rows, columns, sections, or fields and lacks preview, draft, publish, archive, and visible version history.
- The database-backed contract test could not run during audit because the local MySQL connection returned `mysqli_query(... false ...)`; this is not accepted as passing evidence.

## Ledger Work Packages

Every row in `docs/erpnext-parity/ledgers/sales-crm.json` starts its `reason` with one package ID, preserving exact source-row traceability.

| Package | Capability group | Rows | PARTIAL | MISSING |
|---|---|---:|---:|---:|
| `SC-02` | CRM/Selling workspaces and settings | 5 | 2 | 3 |
| `SC-03` | Leads, prospects, appointments, notes, and lead reports | 21 | 1 | 20 |
| `SC-04` | Opportunities, contracts, competitors, pipeline, and opportunity reports | 24 | 1 | 23 |
| `SC-05` | Campaigns and campaign efficiency | 5 | 1 | 4 |
| `SC-06` | Customers, credit, party/sales-team masters, and customer reports | 16 | 2 | 14 |
| `SC-07` | Quotations, items, print formats, and quotation reports | 9 | 1 | 8 |
| `SC-08` | Sales orders, fulfillment support, print formats, and order reports | 26 | 1 | 25 |
| `SC-09` | Sales-facing point of sale | 25 | 0 | 25 |
| `SC-10` | Sales, salesperson, partner, and territory analytics | 25 | 0 | 25 |
| **Total** |  | **156** | **9** | **147** |

`SC-01` is the cross-cutting characterization and module-contract package. It closes no upstream identity by itself; it supplies the evidence required before later packages may move rows to `COMPLETE`.

## File Map

The implementation session may create only module-owned runtime files and focused Sales / CRM tests. `functions.php` remains the include entry point and requires the focused files in dependency order.

- `company/admin/modules/sales-crm/functions.php`: stable public entry point and compatibility wrappers.
- `company/admin/modules/sales-crm/schema.php`: idempotent module-owned tables and schema checks only.
- `company/admin/modules/sales-crm/form-builder.php`: Sales / CRM adapter metadata for the orchestration-owned universal Form Builder.
- `company/admin/modules/sales-crm/crm-settings.php`: CRM/Selling settings and allowed-user policies.
- `company/admin/modules/sales-crm/campaigns.php`: Campaign and email-schedule workflows.
- `company/admin/modules/sales-crm/leads.php`: Lead, Prospect, Appointment, CRM Note, conversion, assignment, and communication services.
- `company/admin/modules/sales-crm/opportunities.php`: Opportunity, competitor, lost reason, stage, item, and contract services.
- `company/admin/modules/sales-crm/customers.php`: Customer, address/contact links, Sales Team/Partner metadata, and credit-policy projections.
- `company/admin/modules/sales-crm/quotations.php`: Quotation header/items, calculations, lifecycle, print projection, and order conversion request.
- `company/admin/modules/sales-crm/sales-orders.php`: Sales Order header/items/schedules, lifecycle, fulfillment/invoice requests, and installation records.
- `company/admin/modules/sales-crm/pos.php`: sales-facing POS sessions and invoices using Finance and Inventory contracts.
- `company/admin/modules/sales-crm/reports.php`: parameterized report definitions, aggregations, exports, and report authorization.
- `company/admin/modules/sales-crm/views/workspace.php`: ERPNext-inspired module hub and section dispatcher using the 12/8 contract.
- `company/admin/modules/sales-crm/views/*.php`: module-local list/detail/report/modal bodies grouped by capability.
- `tests/sales-crm-test-helper.php`: isolated company/admin fixtures, exact cleanup, audit assertions, and failure injection.
- `tests/sales-crm-*.php`: focused schema, authorization, CRUD, lifecycle, rollback, read-back, report, and integration-contract suites.
- `tests/sales-crm-browser.mjs`: modal, confirmation, keyboard, focus, responsive, and workspace interaction checks.

Shared files, Finance files, another module's files, and cross-module tests remain orchestration-owned and are not part of this plan's write scope.

---

### Task 1: `SC-01` Characterize And Lock The Sales / CRM Contracts

**Files:**
- Create: `tests/sales-crm-test-helper.php`
- Create: `tests/sales-crm-foundation.php`
- Create: `tests/sales-crm-browser.mjs`
- Modify: `company/admin/modules/sales-crm/functions.php`
- Create: `company/admin/modules/sales-crm/schema.php`
- Create: `company/admin/modules/sales-crm/form-builder.php`
- Modify: `company/admin/modules/sales-crm/views/workspace.php`

**Interfaces:**
- Consumes: orchestration-owned module registry, modal/confirmation controller, shared Form Builder API, authenticated company/admin context, `bx_db()`, and `bx_audit()`.
- Produces: `yovel_admin_sales_crm_form_targets(): array`, `yovel_admin_sales_crm_form_adapter(string): array`, `sales_crm_test_company(): array`, and deterministic fixture cleanup by exact UUID/company hash.

- [ ] **Step 1: Write characterization tests for all preserved behavior**

Assert the ten current section slugs, unknown fallback, existing Lead schema fields/statuses, Lead create/update/status/delete behavior, active schema version lookup, protected-field normalization, company isolation, and current public function names. The test must fail if a later refactor drops an existing field or route.

```php
sales_crm_assert(array_keys(yovel_admin_sales_crm_sections()) === [
    'leads', 'opportunities', 'campaigns', 'customers', 'quotations',
    'sales-orders', 'customer-credit-limits', 'sales-analytics',
    'salesperson-performance', 'territory-performance',
], 'Sales / CRM route contract changed.');
```

- [ ] **Step 2: Run characterization before refactoring**

Run: `php tests/sales-crm-foundation.php`

Expected: route/static assertions pass; database assertions pass only when MySQL is available. A connection failure blocks implementation rather than becoming ledger evidence.

- [ ] **Step 3: Split module-local responsibilities behind the existing entry point**

Move only Sales / CRM code into the files in the File Map. Keep wrappers with existing function signatures in `functions.php`, and load files with explicit `require_once __DIR__ . '/<file>.php';` statements. Do not modify bootstrap or shared registry files.

- [ ] **Step 4: Implement the Sales / CRM Form Builder adapter**

Expose exact target keys, protected fields, supported field types, persistence mappings, workflow constraints, and version-binding callbacks. Consume the shared Form Builder service after orchestration provides it; do not create a copied shared implementation. Preserve existing schema rows and provide a read-compatible migration path from `ACTIVE/INACTIVE` versions to draft/published/archived shared states.

- [ ] **Step 5: Align the workspace and interaction contract**

Change the module-owned desktop grid to `xl:grid-cols-[minmax(0,12fr)_minmax(20rem,8fr)]`, stack main-first, and ensure no horizontal overflow. Every module Add/New/Create/Insert trigger must use the shared body-owned record modal; every Submit and lifecycle action must use a body-owned sibling confirmation dialog with no write before Confirm.

- [ ] **Step 6: Prove foundation behavior**

Run:

```bash
php tests/sales-crm-foundation.php
node tests/sales-crm-browser.mjs --project=sales-crm-foundation
php -l company/admin/modules/sales-crm/functions.php
php -l company/admin/modules/sales-crm/schema.php
php -l company/admin/modules/sales-crm/form-builder.php
git diff --check
```

Expected: all commands exit `0`; browser checks cover desktop and narrow widths, modal focus trapping/return, Cancel value retention, and Submit-to-Confirm with zero pre-confirm writes.

### Task 2: `SC-02` Build CRM And Selling Workspaces And Settings

**Files:**
- Create: `company/admin/modules/sales-crm/crm-settings.php`
- Modify: `company/admin/modules/sales-crm/views/workspace.php`
- Create: `company/admin/modules/sales-crm/views/settings.php`
- Create: `tests/sales-crm-workspaces.php`
- Create: `tests/sales-crm-settings.php`

**Ledger rows:** Every row whose `reason` begins `[SC-02]` (5 exact source identities).

**Interfaces:**
- Produces: `yovel_admin_sales_crm_settings(array): array`, `yovel_admin_save_sales_crm_settings(array,array,array): string`, and role-filtered workspace shortcuts/reports/masters.

- [ ] Write failing tests for company-scoped CRM/Selling settings, allowed-user enforcement, workspace destinations, unavailable-destination disabling, and audit/read-back/rollback.
- [ ] Implement module-owned settings records with one row per company, optimistic version checks, parameterized writes, audit, and exact read-back.
- [ ] Replace placeholder-first navigation with a quiet ERP workspace: setup checklist, selected-step details, `Show Tour`, shortcuts, and grouped CRM/Selling masters/reports. Keep BuilderX naming and copy.
- [ ] Wire tour targets to real elements and persist dismissed/completed state through the orchestration-approved preference mechanism; request orchestration work if the shared preference/tour contract is absent.
- [ ] Run `php tests/sales-crm-workspaces.php`, `php tests/sales-crm-settings.php`, and the workspace browser project. Move a ledger row to `COMPLETE` only when its exact source identity has local file paths and passing test evidence.

### Task 3: `SC-05` Complete Campaigns Before Attribution Consumers

**Files:**
- Create: `company/admin/modules/sales-crm/campaigns.php`
- Create: `company/admin/modules/sales-crm/views/campaigns.php`
- Create: `tests/sales-crm-campaigns.php`

**Ledger rows:** Every row whose `reason` begins `[SC-05]` (5 exact source identities).

**Interfaces:**
- Produces: `yovel_admin_sales_campaign_save(...)`, `yovel_admin_sales_campaign_schedule_save(...)`, `yovel_admin_sales_campaign_transition(...)`, and `yovel_admin_sales_campaign_efficiency(...)`.

- [ ] Write failing tests for campaign CRUD, date ordering, budget/revenue decimal validation, email schedule child rows, draft/active/completed/inactive transitions, company isolation, rollback, audit, and efficiency calculations.
- [ ] Expand the existing Campaign schema without destructive replacement; preserve `campaign_key`, company keys, code, name, and status.
- [ ] Implement Add/Edit in an accessible modal rendered from the published Campaign form schema, with a separate confirmation dialog and server rehydration on validation errors.
- [ ] Implement attribution reads for Leads, Opportunities, Quotations, and Sales Orders through stable keys, without duplicating those records.
- [ ] Run `php tests/sales-crm-campaigns.php`, focused browser checks, PHP lint, and `git diff --check`; update all five exact ledger rows with evidence.

### Task 4: `SC-03` Complete Leads, Prospects, Appointments, Notes, And Conversion

**Files:**
- Create: `company/admin/modules/sales-crm/leads.php`
- Create: `company/admin/modules/sales-crm/views/leads.php`
- Create: `company/admin/modules/sales-crm/views/prospects.php`
- Create: `company/admin/modules/sales-crm/views/appointments.php`
- Create: `tests/sales-crm-leads.php`
- Create: `tests/sales-crm-lead-reports.php`

**Ledger rows:** Every row whose `reason` begins `[SC-03]` (21 exact source identities).

**Interfaces:**
- Preserves: `yovel_admin_save_sales_lead(...)` and `yovel_admin_set_sales_lead_status(...)`.
- Produces: prospect grouping, appointments/availability, CRM notes/timeline, assignment, communication references, Lead-to-Opportunity/Customer conversion requests, and parameterized Lead reports.

- [ ] Write regression tests around the existing Lead workflow before modifying it, including duplicate-code locking, invalid related-company keys, read-back mismatch rollback, audit rollback, and soft delete.
- [ ] Add Prospect, Prospect Lead, Appointment, booking-slot/settings, availability, CRM Note, Market Segment, and industry-reference records with explicit ownership and lifecycle rules.
- [ ] Implement Lead assignment, follow-up, communication/timeline, import/export, and conversion as idempotent transactions. Conversion must call Customer/Opportunity services and persist source/target keys in the same Sales / CRM transaction; external side effects use an outbox request.
- [ ] Complete the Lead form adapter with version binding and server error rehydration. Add/Edit/Create remains modal-only and confirmation-gated.
- [ ] Implement Lead Details, Conversion Time, Owner Efficiency, Prospects Engaged but Not Converted, and Address/Contacts reports with company/date/owner filters and deterministic export columns.
- [ ] Run both Lead suites plus browser create/edit/convert tests. Record exact file/test evidence for each of the 21 ledger identities.

### Task 5: `SC-06` Complete Customers, Sales Parties, And Finance-Backed Credit

**Files:**
- Create: `company/admin/modules/sales-crm/customers.php`
- Create: `company/admin/modules/sales-crm/views/customers.php`
- Create: `company/admin/modules/sales-crm/views/customer-credit.php`
- Create: `tests/sales-crm-customers.php`
- Create: `tests/sales-crm-credit.php`
- Create: `tests/sales-crm-customer-reports.php`

**Ledger rows:** Every row whose `reason` begins `[SC-06]` (16 exact source identities).

**Dependencies:** Accounting / Finance service contract for receivable exposure and credit balance. Sales / CRM must not query or write Finance authoritative tables directly.

**Interfaces:**
- Produces: Customer master, address/contact links, party-specific item mappings, Sales Team/Partner metadata, credit-policy records, and customer reports.
- Consumes: `finance_customer_credit_exposure(company_key_hash, customer_key, as_of_date)` or the equivalent orchestration-approved Finance read contract.

- [ ] Write failing tests for complete Customer upsert, addresses/contacts, duplicate tax/contact data handling, inactive/archive behavior, company isolation, sales-team splits, party-item mapping, and report filters.
- [ ] Implement Customer Add/Edit/Create modal flows from the published schema, with separate confirmation and server rehydration.
- [ ] Implement effective-dated credit policies locally; calculate utilization and hold decisions from the Finance exposure contract. Never cache Finance balances as Sales-owned truth.
- [ ] If the Finance exposure contract is absent, stop only credit integration, document the exact requested signature to orchestration, and continue Customer behavior that does not fabricate exposure.
- [ ] Implement acquisition/loyalty, credit balance, customer item price, no-sales, inactive customer, and contact/address reports using stable Customer keys and parameterized filters.
- [ ] Run all three suites and focused browser tests; add Finance contract-test evidence supplied by orchestration before closing credit rows.

### Task 6: `SC-04` Complete Opportunity, Pipeline, Competitor, And Contract Workflows

**Files:**
- Create: `company/admin/modules/sales-crm/opportunities.php`
- Create: `company/admin/modules/sales-crm/views/opportunities.php`
- Create: `company/admin/modules/sales-crm/views/contracts.php`
- Create: `tests/sales-crm-opportunities.php`
- Create: `tests/sales-crm-opportunity-reports.php`

**Ledger rows:** Every row whose `reason` begins `[SC-04]` (24 exact source identities).

**Interfaces:**
- Produces: Opportunity header/items, stages, types, competitors, lost reasons, contracts/templates/fulfilment checks, funnel view, response-time and pipeline reports.
- Consumes: Lead and Customer stable keys from Tasks 4 and 5.

- [ ] Write failing tests for Lead/Customer party validation, item rows, weighted value, stage progression, won/lost/closed transitions, mandatory lost reason, competitor links, contract fulfilment, locking, rollback, audit, and company isolation.
- [ ] Implement Opportunity modal CRUD and a drag-capable kanban/funnel that persists stage moves only after confirmation; stale stage versions must fail without overwriting newer data.
- [ ] Implement contract/template records and fulfilment checks without treating a template edit as a rewrite of existing contracts.
- [ ] Implement First Response Time, Lost Opportunity, Stage Summary, and Pipeline Analytics reports with stable date/time calculations and exact fixture expectations.
- [ ] Run Opportunity/report/browser suites and attach exact evidence to all 24 ledger rows.

### Task 7: `SC-07` Complete Quotations And Conversion

**Files:**
- Create: `company/admin/modules/sales-crm/quotations.php`
- Create: `company/admin/modules/sales-crm/views/quotations.php`
- Create: `tests/sales-crm-quotations.php`
- Create: `tests/sales-crm-quotation-reports.php`

**Ledger rows:** Every row whose `reason` begins `[SC-07]` (9 exact source identities).

**Dependencies:** Finance contracts for currency/tax/rounding policy and Inventory contracts for item identity/pricing availability. No Finance or Inventory table writes.

**Interfaces:**
- Produces: immutable submitted Quotation snapshots, item rows, totals, draft/submit/lost/cancel/amend/expire/order lifecycle, print model, and idempotent Sales Order conversion.

- [ ] Write failing tests for items, quantities/rates/discounts/taxes, currency precision, totals, validity dates, lifecycle guards, amendment lineage, form-version binding, conversion idempotency, audit, rollback, and isolation.
- [ ] Implement modal create/edit for drafts and confirmation dialogs for submit, lost, cancel, amend, and convert. Submitted records cannot be edited in place.
- [ ] Request Finance/Inventory policy reads through orchestration when contracts are absent; do not reproduce accounting or stock logic locally.
- [ ] Build BuilderX print projections for standard and item-image layouts without copying ERPNext templates or branding.
- [ ] Implement Lost Quotations and Quotation Trends reports, then run both suites and print/browser checks before closing the nine rows.

### Task 8: `SC-08` Complete Sales Orders And Fulfillment Requests

**Files:**
- Create: `company/admin/modules/sales-crm/sales-orders.php`
- Create: `company/admin/modules/sales-crm/views/sales-orders.php`
- Create: `tests/sales-crm-sales-orders.php`
- Create: `tests/sales-crm-sales-order-reports.php`

**Ledger rows:** Every row whose `reason` begins `[SC-08]` (26 exact source identities).

**Dependencies:** Finance for invoice/payment-term projections, Inventory for item availability/reservation/fulfillment, and Buying for material-request handoff.

**Interfaces:**
- Produces: Sales Order header/items/delivery schedules, bundles, draft/submit/cancel/amend/close/complete lifecycle, installation notes, and idempotent outbox requests to owning modules.

- [ ] Write failing tests for quote conversion, item/schedule totals, delivery dates, bundles, customer PO uniqueness policy, submit/cancel/amend/close transitions, stale locks, audit/read-back rollback, and company isolation.
- [ ] Implement modal draft create/edit and confirmation-gated lifecycle actions. Submitted orders become immutable snapshots with amendment lineage.
- [ ] Implement durable, idempotent requests for inventory reservation/fulfillment, Finance invoice creation, Buying material request, and project delivery; retain request/result keys without writing external tables.
- [ ] Implement installation notes and BuilderX print models, plus Payment Terms Status, Pending SO Items, Item Sales History, Sales Order Analysis, and Sales Order Trends reports.
- [ ] Run both suites, integration-contract fixtures, print/browser checks, PHP lint, and `git diff --check`; Finance/Inventory/Buying dependent rows require orchestration-owned contract evidence.

### Task 9: `SC-09` Build Sales-Facing Point Of Sale Last

**Files:**
- Create: `company/admin/modules/sales-crm/pos.php`
- Create: `company/admin/modules/sales-crm/views/point-of-sale.php`
- Create: `tests/sales-crm-pos.php`
- Create: `tests/sales-crm-pos-contracts.php`

**Ledger rows:** Every row whose `reason` begins `[SC-09]` (25 exact source identities).

**Dependencies:** Accounting / Finance owns payment, tax, receivable, and GL results; Inventory owns item stock and movements. POS implementation waits for explicit orchestration-approved service contracts.

**Interfaces:**
- Produces: POS profile/settings, opening session, sale/return request, tender breakdown, offline-safe client idempotency key, closing reconciliation request, print projection, and POS Register.

- [ ] Write contract tests proving one opening session per user/profile, authorized profile users, idempotent invoice submission, return/reference validation, tender totals, tax read contracts, stock reservation, closing variance, retry behavior, and zero local GL/stock writes.
- [ ] Implement the sales-facing modal/checkout surface with stable keyboard/focus behavior and responsive 12/8 layout; payment submission remains confirmation-gated.
- [ ] Send sale/return/closing requests to Finance and Inventory through durable idempotent contracts; persist only Sales-owned session/projection state.
- [ ] Implement three distinct BuilderX print projections and POS Register output without copying upstream templates.
- [ ] Run POS service/browser/contract tests. Do not mark any POS row `COMPLETE` until Finance and Inventory integration evidence passes.

### Task 10: `SC-10` Complete Analytics, Performance, Export, And Final Ledger Closure

**Files:**
- Create: `company/admin/modules/sales-crm/reports.php`
- Create: `company/admin/modules/sales-crm/views/reports.php`
- Create: `tests/sales-crm-analytics.php`
- Create: `tests/sales-crm-performance-reports.php`
- Modify: `docs/erpnext-parity/ledgers/sales-crm.json`

**Ledger rows:** Every row whose `reason` begins `[SC-10]` (25 exact source identities), followed by re-verification of all 156 rows.

**Dependencies:** Finance-authoritative revenue/receivable facts for financial metrics; Sales / CRM operational facts for pipeline, ownership, partner, campaign, and territory metrics.

**Interfaces:**
- Produces: Sales Analytics, salesperson/partner/territory summaries and target variances, saved filters, deterministic CSV export, and evidence-backed ledger closure.

- [ ] Write fixture-driven report tests with exact expected rows/totals for date ranges, grouping, salesperson, partner, territory, item group, campaign, customer, status, currency, empty state, and unauthorized access.
- [ ] Implement parameterized report definitions and aggregation services. Label operational order values separately from Finance-posted revenue and never merge currencies without an explicit conversion policy.
- [ ] Replace the three report placeholders with real results/charts in the 12-column panel and filters/export/report settings in the 8-column panel.
- [ ] Run complete module verification:

```bash
php tests/sales-crm-foundation.php
php tests/sales-crm-workspaces.php
php tests/sales-crm-settings.php
php tests/sales-crm-campaigns.php
php tests/sales-crm-leads.php
php tests/sales-crm-lead-reports.php
php tests/sales-crm-customers.php
php tests/sales-crm-credit.php
php tests/sales-crm-customer-reports.php
php tests/sales-crm-opportunities.php
php tests/sales-crm-opportunity-reports.php
php tests/sales-crm-quotations.php
php tests/sales-crm-quotation-reports.php
php tests/sales-crm-sales-orders.php
php tests/sales-crm-sales-order-reports.php
php tests/sales-crm-pos.php
php tests/sales-crm-pos-contracts.php
php tests/sales-crm-analytics.php
php tests/sales-crm-performance-reports.php
node tests/sales-crm-browser.mjs
php tools/erpnext-parity.php validate-ledger docs/erpnext-parity/ledgers/sales-crm.json
git diff --check
```

- [ ] For each of the 156 rows, set `COMPLETE` only when `evidence` contains exact local implementation path(s), exact passing test command(s), and route/browser evidence where applicable. Keep unverified rows `PARTIAL` or `MISSING`; a route, label, placeholder, or similar record name is never sufficient.

## Orchestration Requests Before Dependent Implementation

These requests are intentionally not implemented by the Sales / CRM task:

- Shared Form Builder API and compatibility migration ownership for the existing `project_company_form_schema*` tables.
- Shared body-owned record modal, sibling confirmation dialog, focus/rehydration controller, and module asset registration.
- Finance read/write service contracts for credit exposure, currency/tax/rounding, payment terms, POS posting/returns/closing, and invoice creation requests.
- Inventory contracts for item identity, price/availability, reservation, fulfillment, product bundles, and POS stock movements.
- Buying contract for Pending Sales Order Items to Material Request.
- Compliance contract for effective-dated tax rules and regulatory print/export fields.
- Cross-module integration tests and shared route/sidebar registry changes.

## Proposed Execution Sequence

Execute `SC-01`, `SC-02`, `SC-05`, `SC-03`, `SC-06`, `SC-04`, `SC-07`, `SC-08`, `SC-09`, then `SC-10`.

This order establishes the test and interaction contracts first, then settings/campaign attribution, Leads, Customers/credit, Opportunities, Quotations, Sales Orders, POS, and finally reports/ledger closure. `SC-06` credit, `SC-07`, `SC-08`, `SC-09`, and Finance-backed portions of `SC-10` pause at their explicit service boundaries until orchestration supplies passing dependency contracts; unrelated module-local work can continue.

## Audit Acceptance Gate

The module remains incomplete while any applicable ledger row is `PARTIAL` or `MISSING`. Final acceptance also requires successful schema idempotency, company isolation, unauthorized/failure/rollback cases, audit persistence, exact read-back, server rehydration, modal/confirmation interaction, desktop/mobile 12/8 rendering, all ten live routes, cross-module contract tests, fixture cleanup assertions, PHP lint, and `git diff --check`.
