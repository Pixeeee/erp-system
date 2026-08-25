# Buying / Procurement ERPNext Parity Gap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the 50 currently missing Buying / Procurement parity rows from the pinned ERPNext baseline as company-scoped BuilderX workflows without taking ownership of Inventory, Finance, Sales, Manufacturing, Operations, or shared application infrastructure.

**Architecture:** Buying / Procurement owns supplier governance, sourcing documents, purchase orders, scorecards, printable procurement documents, module-local reports, and idempotent cross-module handoff records. Inventory remains authoritative for items, warehouses, material requests, stock quantities, and purchase receipts; Accounting / Finance remains authoritative for tax/account defaults, purchase invoices, payables, and postings. Shared routing, modal confirmation, and Form Builder infrastructure are consumed through orchestrator-owned contracts.

**Tech Stack:** PHP 8 strict procedural modules, ADODB/MySQL, server-rendered HTML, existing BuilderX shadcn-style controls, shared modal/Form Builder APIs, focused PHP CLI tests, and browser verification at desktop and mobile viewports.

## Global Constraints

- Baseline only: ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325`, captured `2026-08-25`.
- Functional parity only: do not copy upstream GPL source, templates, JavaScript, HTML, images, names, logos, or branding.
- Allowed implementation writes: `company/admin/modules/buying-procurement/`, `tests/buying-procurement-*`, this plan, and `docs/erpnext-parity/ledgers/buying-procurement.json`.
- Shared files, another module, and another module's tests/docs remain orchestration-owned and must not be modified by this module task.
- Inventory and Finance contracts are hard dependencies. Never query or write their tables directly.
- Immediate backward-compatible improvements inside Buying / Procurement are allowed with proportionate tests. Shared or cross-module improvements must be escalated to the orchestrator.
- Every configurable Buying record type must be exposed through the universal module-local Form Builder adapter.
- Every workspace and feature page uses a desktop `12/20` left main panel and `8/20` right action/tools panel, stacking main-first on smaller screens.
- Every Add, New, Create, or Insert command opens an accessible body-owned modal.
- Submit opens a separate confirmation dialog. Persistence begins only after Confirm; Cancel returns to the populated modal.
- Every write must authorize the administrator and company, validate server-side input, use parameterized SQL and schema-safe identifiers, own one ADODB transaction at the public boundary, write audit history, directly read back critical state, roll back on failure, and rehydrate server-backed values and errors.
- Submitted controlled documents are immutable. Corrections use cancel, amend, reopen, or reversal-style lifecycle operations as appropriate.
- Android/mobile application work remains deferred. Responsive web behavior is in scope.

---

## Audit Result

The local module directory `company/admin/modules/buying-procurement/` does not exist, and `tests/buying-procurement-*` matches no files. The audit therefore records `0 COMPLETE`, `0 PARTIAL`, `50 MISSING`, `0 NOT_APPLICABLE`, and `0 DEFERRED`.

The following commands passed before this plan was written:

```bash
php tests/erpnext-parity-ledger.php
php tools/erpnext-parity.php validate-baseline
php tools/erpnext-parity.php validate-ledger
```

These commands prove baseline and ledger structure only. They do not provide completion evidence for any Buying / Procurement row. Finance supplier and purchase-invoice behavior outside the assigned audit scope is intentionally not credited.

## Dependency Gates

The orchestrator must land and verify the shared contracts before Tasks 2 and 11 can close:

```php
yovel_admin_module_registry(): array
yovel_admin_module_route(string $view): ?array
yovel_admin_shared_form_adapter(string $module): array
```

The Inventory owner must provide service boundaries with company-scoped stable keys before Tasks 5, 7, 8, 10, or 11 can be marked complete:

```php
yovel_admin_inventory_buying_snapshot(array $company, array $filters = []): array
yovel_admin_inventory_create_purchase_receipt(
    ADOConnection $db,
    array $company,
    array $admin,
    array $payload,
    bool $manageTransaction = true
): array
yovel_admin_inventory_purchase_receipt(array $company, string $receiptKey): ?array
```

The Finance owner must provide service boundaries before Tasks 3, 6, 7, 8, 9, or 10 can be marked complete:

```php
yovel_admin_finance_buying_defaults(array $company, string $supplierKey = ''): array
yovel_admin_finance_calculate_purchase_document(array $company, array $payload): array
yovel_admin_finance_create_purchase_invoice_from_buying(
    ADOConnection $db,
    array $company,
    array $admin,
    array $payload,
    bool $manageTransaction = true
): array
yovel_admin_finance_buying_snapshot(array $company, array $filters = []): array
```

The signatures are requested interfaces, not permission for this task to implement or edit Inventory or Finance. An outer cross-module coordinator owns the transaction and calls dependency services with `manageTransaction: false`. Missing contracts are escalated to the orchestrator; Buying code must not add table-query fallbacks.

Supplier portal delivery, email/EDI transport, drop-ship Sales linkage, and subcontracting execution require Operations, Sales/CRM, and Manufacturing owner services. Those integrations remain explicit gates and never justify direct foreign-table access.

## File Map

- Create `company/admin/modules/buying-procurement/functions.php`: sections, route metadata, data provider, and POST action dispatch exposed to the shared registry.
- Create `company/admin/modules/buying-procurement/schema.php`: idempotent company-scoped Buying tables, indexes, and constraints.
- Create `company/admin/modules/buying-procurement/core.php`: scope checks, normalization, numbering, lifecycle guards, transaction helpers, and exact read-back helpers.
- Create `company/admin/modules/buying-procurement/forms.php`: module adapter metadata for the shared Form Builder and protected system fields.
- Create `company/admin/modules/buying-procurement/settings.php`: Buying Settings reads and transactional updates.
- Create `company/admin/modules/buying-procurement/suppliers.php`: supplier master, customer numbers, holds, internal-supplier rules, and company permissions.
- Create `company/admin/modules/buying-procurement/sourcing.php`: RFQ, RFQ supplier/item children, supplier quotation, quotation items, and mappings.
- Create `company/admin/modules/buying-procurement/orders.php`: purchase-order header/items/supplied-items, totals, lifecycle, and mappings.
- Create `company/admin/modules/buying-procurement/handoffs.php`: idempotent Inventory receipt and Finance invoice handoff orchestration and local link projections.
- Create `company/admin/modules/buying-procurement/scorecards.php`: criteria, variables, standings, periods, safe scoring, and supplier restrictions.
- Create `company/admin/modules/buying-procurement/reports.php`: report registry, filter validation, result builders, chart payloads, and exports.
- Create `company/admin/modules/buying-procurement/printing.php`: four escaped, BuilderX-branded procurement print renderers.
- Create `company/admin/modules/buying-procurement/views/workspace.php`: module workspace composition and feature dispatch.
- Create `company/admin/modules/buying-procurement/views/dashboard.php`: counts, shortcuts, dependency health, and onboarding state.
- Create `company/admin/modules/buying-procurement/views/settings.php`: settings feature and modal trigger.
- Create `company/admin/modules/buying-procurement/views/suppliers.php`: supplier and scorecard workspaces.
- Create `company/admin/modules/buying-procurement/views/sourcing.php`: RFQ and supplier-quotation lists/details/actions.
- Create `company/admin/modules/buying-procurement/views/purchase-orders.php`: order list/detail/lifecycle actions.
- Create `company/admin/modules/buying-procurement/views/purchase-receipts.php`: Inventory-backed receipt list/detail and handoff action.
- Create `company/admin/modules/buying-procurement/views/reports.php`: report results in the left panel and filters/export controls in the right panel.
- Create `company/admin/modules/buying-procurement/views/form-builder.php`: module-local target selection and shared builder rendering.
- Create `company/admin/modules/buying-procurement/views/print.php`: print-format selection and printable output.
- Create `tests/buying-procurement-test-helper.php`: fixture creation, audit snapshots, cleanup, dependency test doubles, and assertions.
- Create `tests/buying-procurement-schema.php`: schema idempotency, indexes, and company isolation.
- Create `tests/buying-procurement-settings-suppliers.php`: settings, suppliers, customer numbers, holds, restrictions, rollback, audit, and read-back.
- Create `tests/buying-procurement-form-builder.php`: adapter targets, immutable versions, stable keys, protected fields, and server rehydration.
- Create `tests/buying-procurement-sourcing.php`: RFQ and supplier-quotation calculations, mappings, and lifecycles.
- Create `tests/buying-procurement-orders.php`: order calculations, lifecycle, child rows, and dependency validation.
- Create `tests/buying-procurement-handoffs.php`: idempotent receipt/invoice handoffs and full rollback.
- Create `tests/buying-procurement-scorecards.php`: safe formulas, periods, standings, restrictions, and audit.
- Create `tests/buying-procurement-reports.php`: all ten report datasets, filters, charts, exports, and upstream test-identity equivalents.
- Create `tests/buying-procurement-printing.php`: all four formats, escaping, images, and print authorization.
- Create `tests/buying-procurement-workspace.php`: route sections, 12/8 layout, modal/confirmation markup, empty/error/populated states, and no placeholders.

## Row-Level Traceability

| Package | Ledger source identities | Current status | Completion evidence required |
|---|---|---:|---|
| Settings and suppliers | `Buying Settings`; `Customer Number At Supplier`; `Supplier` | 3 MISSING | `settings.php`, `suppliers.php`, schema files, and passing settings/supplier tests |
| Purchase orders | `Purchase Order`; `Purchase Order Item`; `Purchase Order Item Supplied` | 3 MISSING | `orders.php`, Inventory/Finance contract tests, and passing lifecycle tests |
| Inventory receipt projection | `Purchase Receipt Item Supplied` | 1 MISSING | `handoffs.php`, verified Inventory service contract, idempotency and rollback tests |
| RFQ | `Request for Quotation`; `Request for Quotation Item`; `Request for Quotation Supplier` | 3 MISSING | `sourcing.php`, transport handoff contract tests, and RFQ lifecycle tests |
| Supplier quotation | `Supplier Quotation`; `Test Records`; `Supplier Quotation Item` | 3 MISSING | `sourcing.php` plus local fixture/equivalent behavior tests and mapping tests |
| Supplier scorecards | `Supplier Scorecard`; `Supplier Scorecard Criteria`; `Supplier Scorecard Period`; `Supplier Scorecard Scoring Criteria`; `Supplier Scorecard Scoring Standing`; `Supplier Scorecard Scoring Variable`; `Supplier Scorecard Standing`; `Supplier Scorecard Variable` | 8 MISSING | `scorecards.php` and passing scoring, restriction, notification-handoff, and security tests |
| Print formats | `Drop Shipping Format`; `Purchase Order Standard`; `Purchase Order with Item Image`; `Request for Quotation with Item Image` | 4 MISSING | `printing.php`, print view, and passing escaping/authorization/render tests |
| Purchase history | `Item-wise Purchase History`; `Item Wise Purchase History` | 2 MISSING | report registry/result code plus passing dataset/filter/chart tests |
| Procurement tracker | `Procurement Tracker` report; `Procurement Tracker` report code; `Test Procurement Tracker` | 3 MISSING | combined Buying/Inventory/Finance projection tests including upstream-equivalent scenarios |
| Purchase analytics | `Purchase Analytics` report; `Purchase Analytics` report code | 2 MISSING | value/quantity, hierarchy, period, company aggregation, and chart tests |
| Purchase-order analytics | `Purchase Order Analysis` report/code; `Purchase Order Trends` report/code | 4 MISSING | status/date/project/grouping, receipt values, open/closed trend, and chart tests |
| Requested items | `Requested Items to Order and Receive` report; `Requested Items To Order And Receive` report code; `Test Requested Items To Order And Receive` | 3 MISSING | Inventory material-request contract plus grouped quantity and test-equivalent scenarios |
| Subcontract reports | `Subcontract Order Summary` report/code; `Subcontracted Item To Be Received` report/code/test; `Subcontracted Raw Materials To Be Transferred` report/code/test | 8 MISSING | Manufacturing/Inventory contracts plus supplied/consumed/remaining quantity tests |
| Quotation comparison | `Supplier Quotation Comparison` report/code | 2 MISSING | comparison matrix, expiry/category/filter/chart, preferred-supplier command and confirmation tests |
| Workspace | `Buying` workspace | 1 MISSING | workspace files, shared route integration, Form Builder, 12/8 responsive layout, and browser evidence |

Every ledger row is represented exactly once above: 21 DocType identities, 4 print formats, 10 report metadata identities, 14 report-code/test identities, and 1 workspace identity.

The exact source-to-task mapping, in ledger order, is:

| Row | Exact pinned `source_id` | Work package |
|---:|---|---|
| BP-001 | `erpnext:erpnext/buying/doctype/buying_settings/buying_settings.json:doctype:Buying Settings` | Task 3 |
| BP-002 | `erpnext:erpnext/buying/doctype/customer_number_at_supplier/customer_number_at_supplier.json:doctype:Customer Number At Supplier` | Task 3 |
| BP-003 | `erpnext:erpnext/buying/doctype/purchase_order/purchase_order.json:doctype:Purchase Order` | Task 7 |
| BP-004 | `erpnext:erpnext/buying/doctype/purchase_order_item/purchase_order_item.json:doctype:Purchase Order Item` | Task 7 |
| BP-005 | `erpnext:erpnext/buying/doctype/purchase_order_item_supplied/purchase_order_item_supplied.json:doctype:Purchase Order Item Supplied` | Task 7 |
| BP-006 | `erpnext:erpnext/buying/doctype/purchase_receipt_item_supplied/purchase_receipt_item_supplied.json:doctype:Purchase Receipt Item Supplied` | Task 8 |
| BP-007 | `erpnext:erpnext/buying/doctype/request_for_quotation/request_for_quotation.json:doctype:Request for Quotation` | Task 5 |
| BP-008 | `erpnext:erpnext/buying/doctype/request_for_quotation_item/request_for_quotation_item.json:doctype:Request for Quotation Item` | Task 5 |
| BP-009 | `erpnext:erpnext/buying/doctype/request_for_quotation_supplier/request_for_quotation_supplier.json:doctype:Request for Quotation Supplier` | Task 5 |
| BP-010 | `erpnext:erpnext/buying/doctype/supplier/supplier.json:doctype:Supplier` | Task 3 |
| BP-011 | `erpnext:erpnext/buying/doctype/supplier_quotation/supplier_quotation.json:doctype:Supplier Quotation` | Task 6 |
| BP-012 | `erpnext:erpnext/buying/doctype/supplier_quotation/test_records.json:doctype:Test Records` | Task 6 fixture evidence |
| BP-013 | `erpnext:erpnext/buying/doctype/supplier_quotation_item/supplier_quotation_item.json:doctype:Supplier Quotation Item` | Task 6 |
| BP-014 | `erpnext:erpnext/buying/doctype/supplier_scorecard/supplier_scorecard.json:doctype:Supplier Scorecard` | Task 4 |
| BP-015 | `erpnext:erpnext/buying/doctype/supplier_scorecard_criteria/supplier_scorecard_criteria.json:doctype:Supplier Scorecard Criteria` | Task 4 |
| BP-016 | `erpnext:erpnext/buying/doctype/supplier_scorecard_period/supplier_scorecard_period.json:doctype:Supplier Scorecard Period` | Task 4 |
| BP-017 | `erpnext:erpnext/buying/doctype/supplier_scorecard_scoring_criteria/supplier_scorecard_scoring_criteria.json:doctype:Supplier Scorecard Scoring Criteria` | Task 4 |
| BP-018 | `erpnext:erpnext/buying/doctype/supplier_scorecard_scoring_standing/supplier_scorecard_scoring_standing.json:doctype:Supplier Scorecard Scoring Standing` | Task 4 |
| BP-019 | `erpnext:erpnext/buying/doctype/supplier_scorecard_scoring_variable/supplier_scorecard_scoring_variable.json:doctype:Supplier Scorecard Scoring Variable` | Task 4 |
| BP-020 | `erpnext:erpnext/buying/doctype/supplier_scorecard_standing/supplier_scorecard_standing.json:doctype:Supplier Scorecard Standing` | Task 4 |
| BP-021 | `erpnext:erpnext/buying/doctype/supplier_scorecard_variable/supplier_scorecard_variable.json:doctype:Supplier Scorecard Variable` | Task 4 |
| BP-022 | `erpnext:erpnext/buying/print_format/drop_shipping_format/drop_shipping_format.json:print_format:Drop Shipping Format` | Task 9 |
| BP-023 | `erpnext:erpnext/buying/print_format/purchase_order_standard/purchase_order_standard.json:print_format:Purchase Order Standard` | Task 9 |
| BP-024 | `erpnext:erpnext/buying/print_format/purchase_order_with_item_image/purchase_order_with_item_image.json:print_format:Purchase Order with Item Image` | Task 9 |
| BP-025 | `erpnext:erpnext/buying/print_format/request_for_quotation_with_item_image/request_for_quotation_with_item_image.json:print_format:Request for Quotation with Item Image` | Task 9 |
| BP-026 | `erpnext:erpnext/buying/report/item_wise_purchase_history/item_wise_purchase_history.json:report:Item-wise Purchase History` | Task 10 |
| BP-027 | `erpnext:erpnext/buying/report/item_wise_purchase_history/item_wise_purchase_history.py:report_code:Item Wise Purchase History` | Task 10 |
| BP-028 | `erpnext:erpnext/buying/report/procurement_tracker/procurement_tracker.json:report:Procurement Tracker` | Task 10 |
| BP-029 | `erpnext:erpnext/buying/report/procurement_tracker/procurement_tracker.py:report_code:Procurement Tracker` | Task 10 |
| BP-030 | `erpnext:erpnext/buying/report/procurement_tracker/test_procurement_tracker.py:report_code:Test Procurement Tracker` | Task 10 scenario evidence |
| BP-031 | `erpnext:erpnext/buying/report/purchase_analytics/purchase_analytics.json:report:Purchase Analytics` | Task 10 |
| BP-032 | `erpnext:erpnext/buying/report/purchase_analytics/purchase_analytics.py:report_code:Purchase Analytics` | Task 10 |
| BP-033 | `erpnext:erpnext/buying/report/purchase_order_analysis/purchase_order_analysis.json:report:Purchase Order Analysis` | Task 10 |
| BP-034 | `erpnext:erpnext/buying/report/purchase_order_analysis/purchase_order_analysis.py:report_code:Purchase Order Analysis` | Task 10 |
| BP-035 | `erpnext:erpnext/buying/report/purchase_order_trends/purchase_order_trends.json:report:Purchase Order Trends` | Task 10 |
| BP-036 | `erpnext:erpnext/buying/report/purchase_order_trends/purchase_order_trends.py:report_code:Purchase Order Trends` | Task 10 |
| BP-037 | `erpnext:erpnext/buying/report/requested_items_to_order_and_receive/requested_items_to_order_and_receive.json:report:Requested Items to Order and Receive` | Task 10 |
| BP-038 | `erpnext:erpnext/buying/report/requested_items_to_order_and_receive/requested_items_to_order_and_receive.py:report_code:Requested Items To Order And Receive` | Task 10 |
| BP-039 | `erpnext:erpnext/buying/report/requested_items_to_order_and_receive/test_requested_items_to_order_and_receive.py:report_code:Test Requested Items To Order And Receive` | Task 10 scenario evidence |
| BP-040 | `erpnext:erpnext/buying/report/subcontract_order_summary/subcontract_order_summary.json:report:Subcontract Order Summary` | Task 10 |
| BP-041 | `erpnext:erpnext/buying/report/subcontract_order_summary/subcontract_order_summary.py:report_code:Subcontract Order Summary` | Task 10 |
| BP-042 | `erpnext:erpnext/buying/report/subcontracted_item_to_be_received/subcontracted_item_to_be_received.json:report:Subcontracted Item To Be Received` | Task 10 |
| BP-043 | `erpnext:erpnext/buying/report/subcontracted_item_to_be_received/subcontracted_item_to_be_received.py:report_code:Subcontracted Item To Be Received` | Task 10 |
| BP-044 | `erpnext:erpnext/buying/report/subcontracted_item_to_be_received/test_subcontracted_item_to_be_received.py:report_code:Test Subcontracted Item To Be Received` | Task 10 scenario evidence |
| BP-045 | `erpnext:erpnext/buying/report/subcontracted_raw_materials_to_be_transferred/subcontracted_raw_materials_to_be_transferred.json:report:Subcontracted Raw Materials To Be Transferred` | Task 10 |
| BP-046 | `erpnext:erpnext/buying/report/subcontracted_raw_materials_to_be_transferred/subcontracted_raw_materials_to_be_transferred.py:report_code:Subcontracted Raw Materials To Be Transferred` | Task 10 |
| BP-047 | `erpnext:erpnext/buying/report/subcontracted_raw_materials_to_be_transferred/test_subcontracted_raw_materials_to_be_transferred.py:report_code:Test Subcontracted Raw Materials To Be Transferred` | Task 10 scenario evidence |
| BP-048 | `erpnext:erpnext/buying/report/supplier_quotation_comparison/supplier_quotation_comparison.json:report:Supplier Quotation Comparison` | Task 10 |
| BP-049 | `erpnext:erpnext/buying/report/supplier_quotation_comparison/supplier_quotation_comparison.py:report_code:Supplier Quotation Comparison` | Task 10 |
| BP-050 | `erpnext:erpnext/buying/workspace/buying/buying.json:workspace:Buying` | Tasks 2 and 11 |

### Task 1: Establish Company-Scoped Buying Persistence

**Files:**
- Create: `company/admin/modules/buying-procurement/schema.php`
- Create: `company/admin/modules/buying-procurement/core.php`
- Create: `tests/buying-procurement-test-helper.php`
- Create: `tests/buying-procurement-schema.php`

**Interfaces:**
- Produces: `yovel_admin_buying_schema(): void`, `yovel_admin_buying_scope(array,array): array{0:string,1:string,2:string}`, `yovel_admin_buying_number(ADOConnection,array,array,string,string): string`, and module-private read-back helpers.
- Consumes: `bx_db()`, `bx_uuid()`, `bx_audit()`, `yovel_admin_db_execute()`, and `yovel_admin_is_uuid()`.

- [ ] **Step 1: Write the failing schema and isolation tests**

Assert two calls to `yovel_admin_buying_schema()` succeed; required tables and unique company-key indexes exist; the same supplier/document code can exist in two companies; cross-company reads return no record; an invalid or inactive administrator is rejected before SQL mutation; and fixture cleanup restores all pre-test rows.

- [ ] **Step 2: Run the test and verify the missing-function failure**

```bash
php tests/buying-procurement-schema.php
```

Expected: non-zero with `Call to undefined function yovel_admin_buying_schema()`.

- [ ] **Step 3: Implement the minimal schema and scope layer**

Create company-scoped tables for settings, suppliers, supplier customer numbers and company permissions, RFQs and children, supplier quotations and items, purchase orders and children, handoff links, scorecard definitions and periods, and number series. Every table includes stable UUID keys, `company_key`, `company_key_hash`, actor keys, timestamps, status indexes, and composite uniqueness inside company scope. Do not add foreign keys or queries to Inventory or Finance tables.

- [ ] **Step 4: Run the focused test**

```bash
php tests/buying-procurement-schema.php
```

Expected: `Buying / Procurement schema checks passed.`

- [ ] **Step 5: Commit the independently testable persistence foundation**

```bash
git add company/admin/modules/buying-procurement/schema.php company/admin/modules/buying-procurement/core.php tests/buying-procurement-test-helper.php tests/buying-procurement-schema.php
git commit -m "Add Buying procurement persistence foundation"
```

### Task 2: Add the Workspace Contract and Universal Form Builder Adapter

**Files:**
- Create: `company/admin/modules/buying-procurement/functions.php`
- Create: `company/admin/modules/buying-procurement/forms.php`
- Create: `company/admin/modules/buying-procurement/views/workspace.php`
- Create: `company/admin/modules/buying-procurement/views/dashboard.php`
- Create: `company/admin/modules/buying-procurement/views/form-builder.php`
- Create: `tests/buying-procurement-form-builder.php`
- Create: `tests/buying-procurement-workspace.php`

**Interfaces:**
- Produces: `yovel_admin_buying_procurement_sections(): array`, `yovel_admin_buying_procurement_section(): string`, `yovel_admin_buying_procurement_data(array,?array): array`, `yovel_admin_buying_procurement_form_adapter(): array`, and `yovel_admin_buying_procurement_handle_post(array,array): array`.
- Consumes: shared registry, record-modal, confirmation-dialog, and Form Builder contracts from the orchestrator.

- [ ] **Step 1: Write failing adapter and workspace tests**

Assert target metadata exists for settings, supplier, RFQ, supplier quotation, purchase order, scorecard, criteria, standing, and variable records; protected workflow/key fields cannot be removed; reordering preserves stable keys; published versions are immutable; the shell contains a `12fr/8fr` desktop grid and main-first stack; every create trigger uses `data-record-modal`; every submit form uses `data-confirm-submit`; and no queued/decorative placeholder exists.

- [ ] **Step 2: Run both tests and verify missing interfaces**

```bash
php tests/buying-procurement-form-builder.php
php tests/buying-procurement-workspace.php
```

Expected: both fail because the module adapter and workspace are absent.

- [ ] **Step 3: Implement the module-private adapter and workspace**

Register sections `dashboard`, `suppliers`, `material-requests`, `request-for-quotation`, `supplier-quotations`, `purchase-orders`, `purchase-receipts`, `supplier-scorecards`, `purchase-analytics`, `reports`, `buying-settings`, and `form-builder`. Render real counts or explicit empty/dependency-error states. The Form Builder adapter delegates normalization, versioning, publishing, archiving, and rendering to the shared service while declaring Buying targets and protected fields.

- [ ] **Step 4: Verify adapter persistence and interaction markup**

```bash
php tests/buying-procurement-form-builder.php
php tests/buying-procurement-workspace.php
```

Expected: both print their `checks passed` messages.

- [ ] **Step 5: Request shared integration without editing shared files**

Send the orchestrator the registry entry values `view=buying-procurement`, `function_file=company/admin/modules/buying-procurement/functions.php`, `workspace_file=company/admin/modules/buying-procurement/views/workspace.php`, `sections_provider=yovel_admin_buying_procurement_sections`, `data_provider=yovel_admin_buying_procurement_data`, `default_section=dashboard`, and `owner=buying-procurement`.

- [ ] **Step 6: Commit the module-private workspace contract**

```bash
git add company/admin/modules/buying-procurement/functions.php company/admin/modules/buying-procurement/forms.php company/admin/modules/buying-procurement/views/workspace.php company/admin/modules/buying-procurement/views/dashboard.php company/admin/modules/buying-procurement/views/form-builder.php tests/buying-procurement-form-builder.php tests/buying-procurement-workspace.php
git commit -m "Add Buying workspace and form adapter"
```

### Task 3: Implement Buying Settings and Supplier Governance

**Files:**
- Create: `company/admin/modules/buying-procurement/settings.php`
- Create: `company/admin/modules/buying-procurement/suppliers.php`
- Create: `company/admin/modules/buying-procurement/views/settings.php`
- Create: `company/admin/modules/buying-procurement/views/suppliers.php`
- Create: `tests/buying-procurement-settings-suppliers.php`

**Interfaces:**
- Produces: `yovel_admin_buying_settings(array): array`, `yovel_admin_buying_save_settings(ADOConnection,array,array,array): array`, `yovel_admin_buying_supplier(array,string): ?array`, `yovel_admin_buying_save_supplier(ADOConnection,array,array,array): array`, and `yovel_admin_buying_set_supplier_status(ADOConnection,array,array,string,string,array): array`.
- Consumes: Finance buying defaults by service contract; no Finance table access.

- [ ] **Step 1: Write failing settings and supplier tests**

Cover naming mode, default supplier group/price list keys, PO/receipt requirements, rate/over-transfer allowances, zero-quantity switches, duplicate customer-number rejection per company, supplier type/currency/payment terms, internal-supplier company rules, RFQ/PO warn/prevent flags, invoice/payment holds, disable/archive, and cross-company isolation. For every mutator assert no write after validation failure, one transaction, audit actor/company/action, exact read-back, and rollback after an injected child-row failure.

- [ ] **Step 2: Run the test and verify missing services**

```bash
php tests/buying-procurement-settings-suppliers.php
```

Expected: non-zero at the first undefined settings service.

- [ ] **Step 3: Implement transactional settings and supplier services**

Normalize codes and dates server-side, parameterize every value, lock existing rows for updates/status changes, replace child rows only inside the owning transaction, audit only after direct read-back succeeds, and return the saved server record. Render Add/Edit/Hold/Release/Disable actions in accessible modals with separate confirmation dialogs and server rehydration after errors.

- [ ] **Step 4: Run the focused test**

```bash
php tests/buying-procurement-settings-suppliers.php
```

Expected: `Buying / Procurement settings and supplier checks passed.`

- [ ] **Step 5: Commit settings and supplier governance**

```bash
git add company/admin/modules/buying-procurement/settings.php company/admin/modules/buying-procurement/suppliers.php company/admin/modules/buying-procurement/views/settings.php company/admin/modules/buying-procurement/views/suppliers.php tests/buying-procurement-settings-suppliers.php
git commit -m "Add Buying settings and suppliers"
```

### Task 4: Implement Supplier Scorecards Safely

**Files:**
- Create: `company/admin/modules/buying-procurement/scorecards.php`
- Modify: `company/admin/modules/buying-procurement/views/suppliers.php`
- Create: `tests/buying-procurement-scorecards.php`

**Interfaces:**
- Produces: `yovel_admin_buying_scorecard_definition(array,string): ?array`, `yovel_admin_buying_save_scorecard_definition(ADOConnection,array,array,array): array`, `yovel_admin_buying_calculate_scorecard_period(ADOConnection,array,array,string,string,string): array`, and `yovel_admin_buying_supplier_restrictions(array,string): array`.
- Consumes: read-only normalized procurement metrics and Operations notification handoff when available.

- [ ] **Step 1: Write failing scorecard tests**

Assert criteria weights total 100, standing ranges do not overlap and cover 0-100, periods do not duplicate, score calculations are deterministic, zero-denominator handling is explicit, scores map to the correct standing, warn/prevent flags affect RFQ/PO validation, and malicious formula input cannot execute PHP, SQL, functions, property access, or file access.

- [ ] **Step 2: Run the test and verify missing scorecard services**

```bash
php tests/buying-procurement-scorecards.php
```

Expected: non-zero at the first undefined scorecard function.

- [ ] **Step 3: Implement scorecard definitions and periods**

Use a small deterministic expression grammar limited to numeric literals, registered variable identifiers, parentheses, and `+`, `-`, `*`, `/`. Reject every other token. Store criteria, variables, standings, period snapshots, score, and applied restriction flags transactionally; do not evaluate arbitrary PHP or SQL. Queue notification intent through the Operations service only after score read-back.

- [ ] **Step 4: Run the scorecard test**

```bash
php tests/buying-procurement-scorecards.php
```

Expected: `Buying / Procurement scorecard checks passed.`

- [ ] **Step 5: Commit supplier scorecards**

```bash
git add company/admin/modules/buying-procurement/scorecards.php company/admin/modules/buying-procurement/views/suppliers.php tests/buying-procurement-scorecards.php
git commit -m "Add supplier scorecards"
```

### Task 5: Implement Request-for-Quotation Workflow

**Files:**
- Create: `company/admin/modules/buying-procurement/sourcing.php`
- Create: `company/admin/modules/buying-procurement/views/sourcing.php`
- Create: `tests/buying-procurement-sourcing.php`

**Interfaces:**
- Produces: `yovel_admin_buying_save_rfq(ADOConnection,array,array,array): array`, `yovel_admin_buying_transition_rfq(ADOConnection,array,array,string,string,array): array`, `yovel_admin_buying_rfq(array,string): ?array`, and `yovel_admin_buying_map_material_requests_to_rfq(array,array,array): array`.
- Consumes: Inventory item/material-request snapshots, supplier restrictions, and Operations email/portal/EDI delivery service.

- [ ] **Step 1: Write failing RFQ tests**

Make the test script accept `--group=rfq` and `--group=supplier-quotation`, then cover duplicate suppliers, inactive/prevented suppliers, missing recipients, item/UOM/quantity/schedule validation, zero-quantity setting, stable material-request item references, Draft to Submitted to Cancelled plus amendment, recipient status Pending to Received, no dispatch before Confirm, idempotent dispatch retries, and full rollback when transport registration fails.

- [ ] **Step 2: Run the sourcing test and verify the RFQ failure**

```bash
php tests/buying-procurement-sourcing.php
```

Expected: non-zero at `yovel_admin_buying_save_rfq()`.

- [ ] **Step 3: Implement RFQ draft, lifecycle, and delivery handoff**

Persist header, supplier, and item snapshots inside one transaction. Validate source keys through Inventory/Supplier services. Keep submitted rows immutable, create amendments with a new key and `amended_from_key`, and register delivery through Operations without implementing email, portal, or EDI transport locally.

- [ ] **Step 4: Run the RFQ-focused assertions**

```bash
php tests/buying-procurement-sourcing.php --group=rfq
```

Expected: `Buying / Procurement RFQ checks passed.`

- [ ] **Step 5: Commit the RFQ slice**

```bash
git add company/admin/modules/buying-procurement/sourcing.php company/admin/modules/buying-procurement/views/sourcing.php tests/buying-procurement-sourcing.php
git commit -m "Add request for quotation workflow"
```

### Task 6: Implement Supplier Quotations and RFQ Mapping

**Files:**
- Modify: `company/admin/modules/buying-procurement/sourcing.php`
- Modify: `company/admin/modules/buying-procurement/views/sourcing.php`
- Modify: `tests/buying-procurement-sourcing.php`

**Interfaces:**
- Produces: `yovel_admin_buying_save_supplier_quotation(ADOConnection,array,array,array): array`, `yovel_admin_buying_transition_supplier_quotation(ADOConnection,array,array,string,string,array): array`, and `yovel_admin_buying_map_rfq_to_supplier_quotation(array,array,string): array`.
- Consumes: Finance purchase-document calculation and Buying RFQ/supplier services.

- [ ] **Step 1: Add failing supplier-quotation scenarios**

Cover currencies/conversion, quantities/UOM, item rates/discounts/taxes, totals, valid-through expiry, supplier reference uniqueness, RFQ item mapping, Draft/Submitted/Stopped/Expired/Cancelled/amended lifecycles, no edits after submit, quote-received projection, and the pinned `test_records.json` identity through deterministic local quotation fixtures.

- [ ] **Step 2: Run the quotation group and verify failure**

```bash
php tests/buying-procurement-sourcing.php --group=supplier-quotation
```

Expected: non-zero at the undefined quotation service.

- [ ] **Step 3: Implement quotation calculation and lifecycle**

Delegate tax and accounting-sensitive totals to Finance, persist returned calculation snapshots, directly read back header/items/totals before commit, and update RFQ supplier receipt status in the same Buying-owned transaction. Expiry is an explicit status transition with audit evidence.

- [ ] **Step 4: Run all sourcing checks**

```bash
php tests/buying-procurement-sourcing.php
```

Expected: `Buying / Procurement sourcing checks passed.`

- [ ] **Step 5: Commit supplier quotations**

```bash
git add company/admin/modules/buying-procurement/sourcing.php company/admin/modules/buying-procurement/views/sourcing.php tests/buying-procurement-sourcing.php
git commit -m "Add supplier quotation workflow"
```

### Task 7: Implement Purchase Orders and Controlled Lifecycles

**Files:**
- Create: `company/admin/modules/buying-procurement/orders.php`
- Create: `company/admin/modules/buying-procurement/views/purchase-orders.php`
- Create: `tests/buying-procurement-orders.php`

**Interfaces:**
- Produces: `yovel_admin_buying_save_purchase_order(ADOConnection,array,array,array): array`, `yovel_admin_buying_transition_purchase_order(ADOConnection,array,array,string,string,array): array`, `yovel_admin_buying_purchase_order(array,string): ?array`, and `yovel_admin_buying_map_supplier_quotation_to_order(array,array,string): array`.
- Consumes: Inventory item/warehouse availability snapshots, Finance calculation/defaults, supplier restrictions, and optional Sales/Manufacturing stable references.

- [ ] **Step 1: Write failing purchase-order tests**

Cover supplier status, minimum/zero quantity, schedule dates, item/UOM/conversion, warehouse keys, quotation/material-request traceability, discounts/taxes/currency totals, supplied raw-material rows, Draft/Submitted/On Hold/To Receive and Bill/To Bill/To Receive/Completed/Closed/Cancelled/Delivered status derivation, hold/resume/close/reopen/cancel/amend transitions, optimistic locking, and immutable submitted commercial terms.

- [ ] **Step 2: Run the test and verify missing order services**

```bash
php tests/buying-procurement-orders.php
```

Expected: non-zero at `yovel_admin_buying_save_purchase_order()`.

- [ ] **Step 3: Implement order persistence and lifecycle**

Persist header/items/supplied-items and Finance calculation snapshots under one transaction. Use Inventory service results for item, UOM, warehouse, ordered, received, and returned quantities. Store only stable foreign keys and read-only snapshots. Every lifecycle action locks the order, validates the current state, audits actor/company/action/reason, reads the result back, then commits.

- [ ] **Step 4: Run purchase-order checks**

```bash
php tests/buying-procurement-orders.php
```

Expected: `Buying / Procurement purchase order checks passed.`

- [ ] **Step 5: Commit purchase orders**

```bash
git add company/admin/modules/buying-procurement/orders.php company/admin/modules/buying-procurement/views/purchase-orders.php tests/buying-procurement-orders.php
git commit -m "Add purchase order workflow"
```

### Task 8: Implement Inventory Receipt and Finance Invoice Handoffs

**Files:**
- Create: `company/admin/modules/buying-procurement/handoffs.php`
- Create: `company/admin/modules/buying-procurement/views/purchase-receipts.php`
- Create: `tests/buying-procurement-handoffs.php`

**Interfaces:**
- Produces: `yovel_admin_buying_create_receipt_handoff(ADOConnection,array,array,array): array`, `yovel_admin_buying_create_invoice_handoff(ADOConnection,array,array,array): array`, and `yovel_admin_buying_handoff(array,string): ?array`.
- Consumes: transaction-joinable Inventory receipt creation/read and Finance invoice creation/read services.

- [ ] **Step 1: Write failing handoff tests**

Assert no receipt/invoice is created before Confirm; retries with the same idempotency key return the same external key; over-receipt/over-billing and company mismatch are rejected; accepted/rejected/supplied quantities reconcile; external service failures roll back both owner and Buying projection writes; direct read-back verifies external and local stable keys; and status refresh uses owner services rather than foreign tables.

- [ ] **Step 2: Run the test and verify missing handoff services**

```bash
php tests/buying-procurement-handoffs.php
```

Expected: non-zero at the first undefined handoff function.

- [ ] **Step 3: Implement outer-transaction coordinators**

Lock the order and handoff key, call Inventory or Finance with `manageTransaction: false`, persist the local link projection and audit event, read the owner record through its public service, read the local projection, and commit only when keys/company/quantities/status all match. The receipt screen is Inventory-backed and does not create a Buying-owned purchase-receipt table.

- [ ] **Step 4: Run handoff checks**

```bash
php tests/buying-procurement-handoffs.php
```

Expected: `Buying / Procurement handoff checks passed.`

- [ ] **Step 5: Commit owner-service handoffs**

```bash
git add company/admin/modules/buying-procurement/handoffs.php company/admin/modules/buying-procurement/views/purchase-receipts.php tests/buying-procurement-handoffs.php
git commit -m "Add procurement owner service handoffs"
```

### Task 9: Add Four Safe Print Formats

**Files:**
- Create: `company/admin/modules/buying-procurement/printing.php`
- Create: `company/admin/modules/buying-procurement/views/print.php`
- Create: `tests/buying-procurement-printing.php`

**Interfaces:**
- Produces: `yovel_admin_buying_print_formats(): array` and `yovel_admin_buying_render_print(string,array,array): string`.
- Consumes: authorized Buying documents plus Inventory item-image and Sales drop-ship snapshots where applicable.

- [ ] **Step 1: Write failing printing tests**

Assert Standard Purchase Order, Purchase Order with Item Image, RFQ with Item Image, and Drop Shipping outputs contain company/document/item/totals/terms data; unauthorized and cross-company records fail; text and URLs are escaped; missing images use a stable non-decorative empty state; no ERPNext branding appears; and print/export actions require confirmation.

- [ ] **Step 2: Run the printing test and verify missing renderer failure**

```bash
php tests/buying-procurement-printing.php
```

Expected: non-zero at `yovel_admin_buying_print_formats()`.

- [ ] **Step 3: Implement BuilderX-native print renderers**

Build four original templates from normalized document arrays. Do not copy upstream markup. Keep print rendering read-only; audit the confirmed print/export command separately if it creates an export artifact.

- [ ] **Step 4: Run printing checks**

```bash
php tests/buying-procurement-printing.php
```

Expected: `Buying / Procurement printing checks passed.`

- [ ] **Step 5: Commit print formats**

```bash
git add company/admin/modules/buying-procurement/printing.php company/admin/modules/buying-procurement/views/print.php tests/buying-procurement-printing.php
git commit -m "Add procurement print formats"
```

### Task 10: Implement Procurement Reports and Upstream-Equivalent Scenarios

**Files:**
- Create: `company/admin/modules/buying-procurement/reports.php`
- Create: `company/admin/modules/buying-procurement/views/reports.php`
- Create: `tests/buying-procurement-reports.php`

**Interfaces:**
- Produces: `yovel_admin_buying_report_registry(): array`, `yovel_admin_buying_report(string,array,array): array{columns:array,rows:array,chart:array,summary:array}`, and `yovel_admin_buying_report_csv(array): string`.
- Consumes: Buying records plus normalized Inventory, Finance, and Manufacturing snapshots supplied through owner services.

- [ ] **Step 1: Write failing report registry and filter tests**

Register exactly ten report keys and validate company, date range, supplier, item, material request, purchase order, project, cost center, status, grouping, hierarchy, value/quantity, period, include-closed, include-expired, and subcontract order-type filters. Reject cross-company stable keys before any report query.

- [ ] **Step 2: Add exact dataset tests for the non-subcontract reports**

Create deterministic fixtures and assert Item-wise Purchase History, Procurement Tracker, Purchase Analytics, Purchase Order Analysis, Purchase Order Trends, Requested Items to Order and Receive, and Supplier Quotation Comparison totals, quantities, statuses, groupings, charts, empty states, and CSV escaping. Include local equivalents of `Test Procurement Tracker` and `Test Requested Items To Order And Receive` scenarios.

- [ ] **Step 3: Add exact subcontract report tests**

Using Manufacturing/Inventory test doubles, assert Subcontract Order Summary, Subcontracted Item To Be Received, and Subcontracted Raw Materials To Be Transferred calculate ordered, supplied, consumed, returned, received, and remaining quantities without direct foreign-table reads. Include local equivalents of both pinned subcontract test-code identities.

- [ ] **Step 4: Run the test and verify missing report services**

```bash
php tests/buying-procurement-reports.php
```

Expected: non-zero at `yovel_admin_buying_report_registry()`.

- [ ] **Step 5: Implement registry, filters, datasets, charts, and CSV**

Return server-derived column metadata, rows, chart series, and summaries. Use parameterized Buying queries and owner-service snapshots. Supplier preference changes from quotation comparison are write commands with modal confirmation, authorization, a transaction, audit, read-back, and rehydration; running filters remains a GET/read operation.

- [ ] **Step 6: Run all report checks**

```bash
php tests/buying-procurement-reports.php
```

Expected: `Buying / Procurement report checks passed.`

- [ ] **Step 7: Commit procurement reports**

```bash
git add company/admin/modules/buying-procurement/reports.php company/admin/modules/buying-procurement/views/reports.php tests/buying-procurement-reports.php
git commit -m "Add procurement reports"
```

### Task 11: Complete Workspace Integration and Interaction Coverage

**Files:**
- Modify: `company/admin/modules/buying-procurement/functions.php`
- Modify: `company/admin/modules/buying-procurement/views/workspace.php`
- Modify: `company/admin/modules/buying-procurement/views/dashboard.php`
- Modify: `tests/buying-procurement-workspace.php`

**Interfaces:**
- Consumes: all completed module-private services and orchestrator-owned registry/modal/Form Builder APIs.
- Produces: a real Buying workspace with no placeholder section and stable dependency-error states.

- [ ] **Step 1: Expand workspace tests to every section and UI state**

Assert empty, populated, validation-error, persistence-error, unauthorized, archived/cancelled, and dependency-unavailable states; 12/8 layout; main-first stacking hooks; accessible labels/descriptions/focus targets; Add/New/Create/Insert modal ownership; sibling confirmation dialog; no POST before Confirm; exactly one POST after Confirm; retained values on Cancel/error; and server-refreshed values on success.

- [ ] **Step 2: Run workspace tests and verify incomplete integration**

```bash
php tests/buying-procurement-workspace.php
```

Expected: non-zero until every section and state is wired.

- [ ] **Step 3: Wire module-private section dispatch and POST commands**

Route each action to one public service boundary, preserve valid submitted values and errors in the returned view data, expose dependency failures as actionable right-panel status, and leave shared bootstrap/controller/layout changes to the orchestrator.

- [ ] **Step 4: Run all focused module tests**

```bash
for test_file in tests/buying-procurement-*.php; do php "$test_file" || exit 1; done
```

Expected: every Buying / Procurement test exits `0` and prints a pass message.

- [ ] **Step 5: Request orchestrator live-route and browser verification**

Require an authenticated `view=buying-procurement` route check plus desktop/mobile browser checks for `12/20` and `8/20`, main-first stacking, no overlap or horizontal scroll, modal focus trapping/restoration, Submit-to-Confirm sequencing, Cancel/error value retention, and server refresh after success.

- [ ] **Step 6: Commit final module-private workspace wiring**

```bash
git add company/admin/modules/buying-procurement/functions.php company/admin/modules/buying-procurement/views/workspace.php company/admin/modules/buying-procurement/views/dashboard.php tests/buying-procurement-workspace.php
git commit -m "Complete Buying workspace wiring"
```

### Task 12: Close Ledger Rows Only with Exact Evidence

**Files:**
- Modify: `docs/erpnext-parity/ledgers/buying-procurement.json`
- Verify only: `company/admin/modules/buying-procurement/`
- Verify only: `tests/buying-procurement-*`

**Interfaces:**
- Consumes: implementation files, focused test output, dependency contract output, authenticated route results, and browser evidence.
- Produces: evidence-backed row classifications without unsupported completion claims.

- [ ] **Step 1: Update each row independently**

For each source identity, set `COMPLETE` only when its `evidence` names exact local implementation file paths and exact passing test command/results. Leave a row `PARTIAL` when its local implementation exists but a dependency, lifecycle, interaction, route, or test gate remains. Leave it `MISSING` when no functional local implementation exists.

- [ ] **Step 2: Validate the ledger and all module tests**

```bash
php tools/erpnext-parity.php validate-ledger
for test_file in tests/buying-procurement-*.php; do php "$test_file" || exit 1; done
git diff --check -- company/admin/modules/buying-procurement tests docs/erpnext-parity/ledgers/buying-procurement.json
```

Expected: structural validation passes, all focused tests pass, and no whitespace errors are reported.

- [ ] **Step 3: Run the orchestration acceptance gate**

The orchestrator runs shared registry/modal/Form Builder tests, Inventory and Finance contract tests, authenticated routes, browser viewports, and cross-module purchase-receipt-to-stock and purchase-invoice matching tests. Any failed gate keeps affected rows `PARTIAL`.

- [ ] **Step 4: Commit only the verified gap slice**

```bash
git add company/admin/modules/buying-procurement tests/buying-procurement-* docs/erpnext-parity/ledgers/buying-procurement.json
git commit -m "Implement Buying procurement ERP parity"
```

## Proposed Implementation Sequence

1. Orchestrator lands shared registry, modal/confirmation, and Form Builder contracts; Inventory and Finance owners publish the service boundaries above.
2. Build Task 1 schema/core, then Task 2 workspace/Form Builder adapter so every later feature has consistent company scope and interaction contracts.
3. Implement dependency-light supplier/settings and scorecard packages in Tasks 3-4.
4. Implement sourcing in dependency order: RFQ first, then supplier quotations in Tasks 5-6.
5. Implement purchase orders in Task 7 after Inventory validation and Finance calculation contracts pass.
6. Implement receipt/invoice handoffs in Task 8 only after transaction-joinable Inventory and Finance services pass their owner tests.
7. Add print formats and reports in Tasks 9-10, with subcontract reports waiting for Manufacturing/Inventory snapshots.
8. Complete workspace wiring and browser checks in Task 11, then close ledger rows individually in Task 12.

## Highest-Risk Gaps

- Purchase receipt and supplied-item parity crosses the Inventory ownership boundary and must remain atomic without a Buying-owned receipt table.
- Purchase-invoice mapping and procurement reports require Finance-owned tax, payable, and posting state without direct Finance queries.
- Procurement Tracker and analytics combine material requests, receipts, purchase invoices, and orders; inconsistent snapshot keys or dates could produce plausible but incorrect results.
- Purchase-order lifecycle percentages and statuses depend on idempotent receipt/invoice projections and concurrency-safe locking.
- Supplier scorecard formulas are an injection surface unless the implementation uses the restricted numeric grammar and allowlisted variables described above.
- RFQ portal/email/EDI delivery and supplier notifications depend on Operations transport and retry semantics; local UI success must not imply delivery success.
- Drop shipping and subcontract reports require Sales and Manufacturing contracts that are outside this task's write authority.
