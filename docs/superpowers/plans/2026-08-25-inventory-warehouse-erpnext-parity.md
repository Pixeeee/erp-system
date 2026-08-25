# Inventory / Warehouse ERPNext Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the 189 currently missing Inventory / Warehouse ledger rows as a company-scoped stock system with deterministic quantity and valuation integrity, reservations, serial/batch traceability, reconciliation, fulfillment, and reports.

**Architecture:** The module owns its PHP services, company-scoped tables, views, and focused tests under `company/admin/modules/inventory-warehouse/` and `tests/inventory-warehouse-*`. Stock writes flow through one transaction-owning document service into an append-only stock ledger, then directly verify the authoritative rows and rehydrate server state; cross-module consumers call stable Inventory services and never write Inventory tables directly. Shared routing, shared modal JavaScript, shared Form Builder adapters, Finance general-ledger integration, and cross-module wiring remain orchestration-owned.

**Tech Stack:** PHP 8.5 with PHP 8.1 compatibility, ADODB/MySQL, server-rendered PHP, Tailwind 4/shadcn tokens, existing Lucide/Material icons, vanilla JavaScript through the shared modal controller, PHP executable tests, and Playwright 1.62.

## Global Constraints

- Use ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325` and Frappe HR `version-16` commit `f281e8b172ac8836ad89c59df65a922101103097` as the parity baseline.
- Preserve all completed behavior and unrelated working-tree changes; this is gap-only implementation.
- Defer Mobile / Android Stockroom and all Android-specific work.
- Expose the universal module-local Form Builder for configurable Inventory record types.
- Render the workspace as a 20-column desktop grid with the left main panel spanning 12/20 and the right action/tools panel spanning 8/20; stack main-first on smaller screens.
- Every Add, New, Create, or Insert command opens an accessible modal.
- Submit opens a separate confirmation dialog; persistence begins only after Confirm.
- Every persisted write requires authorization, company scope, server validation, parameterized SQL, one owner ADODB transaction, audit logging, exact direct read-back verification, rollback on failure, and server rehydration.
- Module-local backward-compatible improvements are allowed with proportionate tests. Shared or cross-module improvements must be escalated to the orchestration task.
- Implement functional parity without copying upstream GPL code, names, logos, or visual assets into BuilderX branding.
- Do not edit `app/foundation.php`, `company/admin/bootstrap/`, `company/admin/core/`, `company/admin/views/layout.php`, `company/admin/views/partials/`, `company/admin/assets/`, `company/admin/modules/shared/`, another module, or another module's tests/docs from this module task.
- Quantity and valuation calculations use decimal strings with BCMath. Persist quantities/rates as `DECIMAL(24,9)` and currency values as `DECIMAL(24,6)`; never use binary floats for authoritative stock arithmetic.
- Submitted stock effects are immutable. Cancellation posts additive reversal effects; it does not delete or overwrite ledger history.

---

## Audit Snapshot

Audit date: 2026-08-25.

| Status | Count |
|---|---:|
| `COMPLETE` | 0 |
| `PARTIAL` | 0 |
| `MISSING` | 189 |
| `NOT_APPLICABLE` | 0 |
| `DEFERRED` | 0 |

The audited module directory `company/admin/modules/inventory-warehouse/` does not exist, and `tests/inventory-warehouse-*` matches no files. Consequently, no row has exact local implementation evidence or a passing focused test. The ledger remains the row-level authority: every row now has one `gap_package`, and a package's exact rows are obtained with:

```bash
jq -r '.rows[] | select(.gap_package == "IW-04") | .source_id' docs/erpnext-parity/ledgers/inventory-warehouse.json
```

Replace `IW-04` with the package under review. A row may move to `COMPLETE` only when its `evidence` names exact local implementation files and a freshly passing test command. Cross-module rows remain `PARTIAL` until the orchestration-owned integration is connected and verified.

## Gap Packages

| Package | Rows | Scope | Depends on |
|---|---:|---|---|
| `IW-01` | 1 | Workspace shell, module navigation, Form Builder, modal/confirmation contract | Orchestrator shared registry/modal/Form Builder contract |
| `IW-02` | 31 | Item catalogue, variants, UOM, barcode, manufacturers, price lists | `IW-01` |
| `IW-03` | 13 | Warehouses, bins, dimensions, settings, reorder, putaway, projected quantity | `IW-02` |
| `IW-04` | 28 | Append-only stock ledger, FIFO/Moving Average/LIFO valuation, reposting, closings | `IW-02`, `IW-03`, Finance handoff |
| `IW-05` | 24 | Stock Entry, Material Request, Purchase Receipt, Delivery Note, landed cost | `IW-04`, `IW-07`, cross-module source documents |
| `IW-06` | 13 | Reservations, allocation, picking, packed items, shortage and reserved stock | `IW-03`, `IW-05`, `IW-07` |
| `IW-07` | 33 | Batches, serials, bundles, expiry, valuation, traceability | `IW-04` |
| `IW-08` | 15 | Reconciliation, opening stock, balance snapshots, invariant diagnostics | `IW-04`, `IW-07` |
| `IW-09` | 13 | Packing, shipment, delivery trip, print surfaces | `IW-05`, `IW-06` |
| `IW-10` | 6 | Quality inspection masters, readings, acceptance/rejection handoff | `IW-02`, `IW-05` |
| `IW-11` | 12 | Remaining stock analytics and operational reports | `IW-02` through `IW-10` |

## File Map

Create these module-local files during implementation:

| File | Responsibility |
|---|---|
| `company/admin/modules/inventory-warehouse/functions.php` | Module loader, section normalization, workspace data composition, stable public entry points |
| `company/admin/modules/inventory-warehouse/schema.php` | Company-scoped Inventory tables, keys, constraints, and indexes |
| `company/admin/modules/inventory-warehouse/persistence.php` | Authorization/scope checks, transaction ownership, decimal normalization, read-back assertions |
| `company/admin/modules/inventory-warehouse/forms.php` | Module-local Form Builder schemas, protected fields, versions, custom values, audits |
| `company/admin/modules/inventory-warehouse/catalogue.php` | Items, variants, UOM, barcodes, manufacturers, price lists |
| `company/admin/modules/inventory-warehouse/warehouses.php` | Warehouse tree, bins, dimensions, settings, reorder and putaway |
| `company/admin/modules/inventory-warehouse/ledger.php` | Append-only ledger, valuation queues, replay, closing balances, stock snapshots |
| `company/admin/modules/inventory-warehouse/serial-batch.php` | Serial, batch, bundle, expiry, availability and traceability services |
| `company/admin/modules/inventory-warehouse/reconciliation.php` | Reconciliation drafts, submit/cancel, opening stock, invariant checks |
| `company/admin/modules/inventory-warehouse/transactions.php` | Stock Entry, Material Request, receipt/delivery, landed-cost stock effects |
| `company/admin/modules/inventory-warehouse/reservations.php` | Reservation lifecycle, pick allocation, packed-item and shortage calculations |
| `company/admin/modules/inventory-warehouse/logistics.php` | Packing slips, shipments, parcels, delivery trips, print models |
| `company/admin/modules/inventory-warehouse/quality.php` | Inspection templates, readings, status and stock acceptance handoff |
| `company/admin/modules/inventory-warehouse/reports.php` | Filtered report queries, exports and traceability projections |
| `company/admin/modules/inventory-warehouse/views/workspace.php` | 12/20 main and 8/20 tools workspace |
| `company/admin/modules/inventory-warehouse/views/record-modal.php` | Module fields rendered inside the shared accessible record-modal contract |
| `company/admin/modules/inventory-warehouse/views/form-builder.php` | Module-local Form Builder interface |
| `company/admin/modules/inventory-warehouse/views/report.php` | Tabular report and filter surface |
| `company/admin/modules/inventory-warehouse/views/print.php` | Delivery, picking, receipt, serial/batch print layouts |

Focused tests are split by reviewable behavior:

```text
tests/inventory-warehouse-contract.php
tests/inventory-warehouse-form-builder.php
tests/inventory-warehouse-catalogue.php
tests/inventory-warehouse-warehouses.php
tests/inventory-warehouse-ledger-valuation.php
tests/inventory-warehouse-serial-batch.php
tests/inventory-warehouse-reconciliation.php
tests/inventory-warehouse-transactions.php
tests/inventory-warehouse-reservations.php
tests/inventory-warehouse-logistics.php
tests/inventory-warehouse-quality.php
tests/inventory-warehouse-reports.php
tests/inventory-warehouse-ui.spec.js
```

## Shared And Cross-Module Handoffs

| Dependency | Inventory-owned interface | Orchestrator action |
|---|---|---|
| Shared route and modal controller | Module providers and `data-record-modal`/`data-confirm-dialog` markup | Register route and POST dispatch; connect shared JavaScript without module edits to shared files |
| Finance | Immutable valuation effect payload keyed by stock voucher/effect | Connect to Finance GL posting/reversal service and add cross-module tests; Inventory never writes Finance tables |
| Buying / Procurement | Receipt/material-request posting services accepting external source keys | Wire approved purchase orders and supplier receipts; Procurement never writes stock ledger/bin tables |
| Sales / CRM | Delivery and reservation services accepting sales source keys | Wire sales orders/deliveries and cancellation semantics |
| Manufacturing | Stock Entry purpose and material-consumption/production interfaces | Wire BOM/work-order references without direct Inventory table writes |
| Projects | Optional project dimension key on reservations and ledger effects | Validate project ownership through a read-only service |
| Quality | Inspection result handoff for accepted/rejected warehouse quantities | Resolve final cross-module owner and wire inspection requirement checks |
| Operations | Repost/closing background-job request and progress query | Connect queue execution and notifications; synchronous module tests retain deterministic worker functions |

If any handoff requires a shared-file change, stop that package at a tested module-local service, mark affected rows `PARTIAL`, and send the exact requested shared path and interface to the orchestrator.

---

### Task 1: `IW-01` Workspace, Persistence Contract, And Form Builder

**Files:**
- Create: `company/admin/modules/inventory-warehouse/functions.php`
- Create: `company/admin/modules/inventory-warehouse/schema.php`
- Create: `company/admin/modules/inventory-warehouse/persistence.php`
- Create: `company/admin/modules/inventory-warehouse/forms.php`
- Create: `company/admin/modules/inventory-warehouse/views/workspace.php`
- Create: `company/admin/modules/inventory-warehouse/views/record-modal.php`
- Create: `company/admin/modules/inventory-warehouse/views/form-builder.php`
- Create: `tests/inventory-warehouse-contract.php`
- Create: `tests/inventory-warehouse-form-builder.php`
- Create: `tests/inventory-warehouse-ui.spec.js`

**Interfaces:**

```php
function yovel_admin_inventory_warehouse_sections(): array;
function yovel_admin_inventory_warehouse_section(string $requested): string;
function yovel_admin_inventory_warehouse_data(array $company, ?array $admin, string $section): array;
function yovel_admin_inventory_scope(array $company, ?array $admin): array;
function yovel_admin_inventory_in_transaction(callable $operation): mixed;
function yovel_admin_inventory_assert_readback(array $expected, array $actual, array $fields, string $label): void;
function yovel_admin_inventory_form_schema(array $company, string $recordType): array;
function yovel_admin_save_inventory_form_schema(array $company, ?array $admin, string $recordType, array $schema): array;
```

- [ ] **Step 1: Write failing contract and Form Builder tests.** Assert section normalization, the 12/20 plus 8/20 DOM regions, main-first responsive order, protected stable field keys, schema version/audit rows, and company isolation. Render every Add/New/Create/Insert trigger and assert it targets an accessible record modal. Assert Submit opens a body-owned sibling confirmation dialog, Cancel returns to the populated record modal, and Confirm is the first action that submits.
- [ ] **Step 2: Run the focused tests and capture the expected missing-file/function failures.**

```bash
php tests/inventory-warehouse-contract.php
php tests/inventory-warehouse-form-builder.php
npx playwright test tests/inventory-warehouse-ui.spec.js
```

- [ ] **Step 3: Implement the module-local persistence boundary.** `yovel_admin_inventory_scope()` must reject absent company/admin identities and return the company key, company hash, and admin key. `yovel_admin_inventory_in_transaction()` must require `BeginTrans()`, execute the callback once, require `CommitTrans()`, and call `RollbackTrans()` for every `Throwable`. All SQL uses positional parameters; identifiers come only from hard-coded allowlists.
- [ ] **Step 4: Implement versioned Form Builder storage.** Seed protected fields for `ITEM`, `WAREHOUSE`, `STOCK_ENTRY`, `STOCK_RECONCILIATION`, `STOCK_RESERVATION`, `BATCH`, `SERIAL`, `SHIPMENT`, and `QUALITY_INSPECTION`. Persist schema versions and audit rows in the same transaction, verify the exact JSON checksum and version after write, and rehydrate from the database.
- [ ] **Step 5: Implement the workspace and modal markup against the orchestration contract.** Use a 20-column desktop grid, stable main/tools dimensions, existing icons, no nested cards, and no module-local JavaScript copy of the shared modal controller.
- [ ] **Step 6: Ask the orchestrator to register the route and POST actions.** The request must name only the required shared files and the provider/action signatures above. Keep the workspace test `PARTIAL` until the shared route test passes.
- [ ] **Step 7: Run the focused tests and commit the module-local slice.**

```bash
php tests/inventory-warehouse-contract.php
php tests/inventory-warehouse-form-builder.php
npx playwright test tests/inventory-warehouse-ui.spec.js
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-contract.php tests/inventory-warehouse-form-builder.php tests/inventory-warehouse-ui.spec.js
git commit -m "Add Inventory workspace and form contracts"
```

### Task 2: `IW-02` Item Catalogue, Pricing, UOM, Variants, And Barcodes

**Files:**
- Create: `company/admin/modules/inventory-warehouse/catalogue.php`
- Modify: `company/admin/modules/inventory-warehouse/schema.php`
- Modify: `company/admin/modules/inventory-warehouse/forms.php`
- Modify: `company/admin/modules/inventory-warehouse/views/record-modal.php`
- Create: `tests/inventory-warehouse-catalogue.php`

**Interfaces:**

```php
function yovel_admin_save_inventory_item(array $company, ?array $admin, array $input): array;
function yovel_admin_inventory_item(array $company, string $itemKey): ?array;
function yovel_admin_inventory_items(array $company, array $filters = []): array;
function yovel_admin_save_item_price(array $company, ?array $admin, array $input): array;
function yovel_admin_resolve_item_uom(array $company, string $itemKey, string $uomKey, string $qty): array;
function yovel_admin_resolve_item_barcode(array $company, string $barcode): ?array;
```

- [ ] **Step 1: Write failing catalogue tests.** Cover unique item codes and barcodes per company, stock/non-stock rules, valid UOM conversion factors, variant-template attributes, manufacturer details, price-list currency/UOM/date validity, disabled records, company isolation, and prohibition on changing stock UOM/serial/batch flags after ledger activity exists.
- [ ] **Step 2: Run `php tests/inventory-warehouse-catalogue.php` and verify it fails because catalogue services are absent.**
- [ ] **Step 3: Add normalized catalogue tables and indexes.** Store item master data separately from barcodes, UOM conversions, reorder rows, supplier/customer details, tax details, variant attributes, manufacturers, and prices. Enforce company-scoped unique keys in MySQL as well as server validation.
- [ ] **Step 4: Implement complete draft upserts.** Each save locks the existing item or natural key, validates all child rows, replaces child collections within the owner transaction, calls `bx_audit()`, reads the header and every child row back, compares normalized values, commits, and returns the read-back object.
- [ ] **Step 5: Render Item and price-list creation through the shared record-modal/confirmation boundary and server-backed validation state.** Barcode input must identify its supported symbology and reject invalid checksums for EAN/UPC/ISBN formats.
- [ ] **Step 6: Run and commit.**

```bash
php tests/inventory-warehouse-catalogue.php
php tests/inventory-warehouse-form-builder.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-catalogue.php
git commit -m "Add Inventory item catalogue"
```

### Task 3: `IW-03` Warehouses, Bins, Dimensions, Putaway, And Reorder

**Files:**
- Create: `company/admin/modules/inventory-warehouse/warehouses.php`
- Modify: `company/admin/modules/inventory-warehouse/schema.php`
- Modify: `company/admin/modules/inventory-warehouse/views/record-modal.php`
- Create: `tests/inventory-warehouse-warehouses.php`

**Interfaces:**

```php
function yovel_admin_save_warehouse(array $company, ?array $admin, array $input): array;
function yovel_admin_inventory_bin(array $company, string $itemKey, string $warehouseKey, array $dimensions = []): array;
function yovel_admin_inventory_lock_bin($db, array $company, string $itemKey, string $warehouseKey, array $dimensions = []): array;
function yovel_admin_putaway_plan(array $company, string $itemKey, string $qty, array $dimensions = []): array;
function yovel_admin_reorder_recommendations(array $company, ?string $warehouseKey = null): array;
```

- [ ] **Step 1: Write failing warehouse tests.** Cover tree cycles, company ownership, group-versus-leaf behavior, disabled warehouses, warehouse type, dimension uniqueness, capacity, putaway priority/capacity, reorder level/quantity, projected quantity, and row locking under two competing allocations.
- [ ] **Step 2: Run `php tests/inventory-warehouse-warehouses.php` and capture the expected failure.**
- [ ] **Step 3: Implement warehouse hierarchy, bin, dimension, setting, putaway, and reorder tables.** A bin key is the exact company/item/leaf-warehouse/dimension tuple. Group warehouses cannot receive, issue, reserve, reconcile, or hold quantity.
- [ ] **Step 4: Implement projected quantity as an explicit projection.** Return actual, reserved, ordered, requested, planned, projected, and available-to-reserve decimal strings; do not persist derived totals without an authoritative source key.
- [ ] **Step 5: Implement modal upserts with the common transaction/read-back/audit contract, then run and commit.**

```bash
php tests/inventory-warehouse-warehouses.php
php tests/inventory-warehouse-catalogue.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-warehouses.php
git commit -m "Add warehouse and replenishment controls"
```

### Task 4: `IW-04` Stock Ledger And Valuation Integrity

**Files:**
- Create: `company/admin/modules/inventory-warehouse/ledger.php`
- Modify: `company/admin/modules/inventory-warehouse/schema.php`
- Create: `tests/inventory-warehouse-ledger-valuation.php`

**Interfaces:**

```php
function yovel_admin_inventory_post_ledger_effects($db, array $company, ?array $admin, array $voucher, array $effects): array;
function yovel_admin_inventory_reverse_ledger_effects($db, array $company, ?array $admin, array $voucher, string $reason): array;
function yovel_admin_inventory_replay_partition($db, array $company, string $itemKey, string $warehouseKey, array $dimensions, string $fromPostingDatetime): array;
function yovel_admin_inventory_stock_snapshot(array $company, string $itemKey, string $warehouseKey, string $postingDatetime, array $dimensions = []): array;
function yovel_admin_inventory_valuation_handoff(array $postingResult): array;
```

- [ ] **Step 1: Write failing ledger invariants.** Cover FIFO, Moving Average, and LIFO queues; receipts, issues, transfers, zero-quantity value adjustments, item/global negative-stock policy, deterministic same-timestamp ordering, idempotent voucher effects, backdated replay, additive cancellation, closing snapshots, and exact equations `qty_after = prior_qty + actual_qty` and `value_after = prior_value + value_difference`.
- [ ] **Step 2: Add concurrency tests.** Two transactions affecting the same company/item/warehouse/dimension partition must serialize through a locked bin; different partitions may proceed independently. A failed audit/read-back must roll back the ledger rows, queue state, and bin update together.
- [ ] **Step 3: Run `php tests/inventory-warehouse-ledger-valuation.php` and verify the missing-service failure.**
- [ ] **Step 4: Implement append-only ledger and valuation tables.** Use a unique idempotency key over company, voucher type/key, voucher line key, and effect index. Persist posting datetime, actual quantity, incoming/outgoing/valuation rates, quantity after transaction, stock value, stock value difference, queue JSON/checksum, dimensions, and reversal reference.
- [ ] **Step 5: Implement deterministic valuation and replay.** Lock the partition, load the latest closing state before the affected timestamp, replay in `(posting_datetime, creation_sequence, effect_index)` order, verify every queue/quantity/value invariant, update the bin from the final row, and record a repost audit. Do not mutate the source voucher or delete historical effects.
- [ ] **Step 6: Expose a Finance handoff payload without writing Finance tables.** Include source voucher identity, posting date, warehouse/account dimensions, signed stock value difference, and reversal identity. Ask the orchestrator to connect and test Finance posting.
- [ ] **Step 7: Run and commit.**

```bash
php tests/inventory-warehouse-ledger-valuation.php
php tests/inventory-warehouse-warehouses.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-ledger-valuation.php
git commit -m "Add deterministic stock ledger valuation"
```

### Task 5: `IW-07` Batch, Serial, Bundle, And Traceability Integrity

**Files:**
- Create: `company/admin/modules/inventory-warehouse/serial-batch.php`
- Modify: `company/admin/modules/inventory-warehouse/schema.php`
- Modify: `company/admin/modules/inventory-warehouse/views/record-modal.php`
- Create: `tests/inventory-warehouse-serial-batch.php`

**Interfaces:**

```php
function yovel_admin_inventory_validate_serial_batch_effects($db, array $company, array $item, array $effect): array;
function yovel_admin_inventory_post_serial_batch_bundle($db, array $company, ?array $admin, array $voucher, array $bundle): array;
function yovel_admin_inventory_available_batches(array $company, string $itemKey, string $warehouseKey, string $postingDatetime): array;
function yovel_admin_inventory_available_serials(array $company, string $itemKey, string $warehouseKey, string $postingDatetime): array;
function yovel_admin_inventory_trace(array $company, string $serialOrBatchKey): array;
```

- [ ] **Step 1: Write failing serial/batch tests.** Assert globally unique company/item serial identities, serial quantity exactly one per inward/outward effect, batch item ownership and expiry, no outbound expired batch unless an explicit authorized override exists, bundle direction/sign consistency, bundle total equal to stock line quantity, warehouse availability at posting time, cancellation reversal, reservation exclusion, and immutable trace chronology.
- [ ] **Step 2: Add valuation consistency tests.** Serial/batch quantity and value totals must equal their parent ledger effect. Detect duplicate serial use, negative batch quantity, serial count mismatch, bundle/ledger mismatch, and incorrect serial valuation.
- [ ] **Step 3: Run `php tests/inventory-warehouse-serial-batch.php` and verify it fails before implementation.**
- [ ] **Step 4: Implement batch, serial, bundle, entry, and trace tables.** Post bundle entries inside the parent stock document transaction before the ledger effect commits. Store returned-against and reversal references; never rewrite historical ownership.
- [ ] **Step 5: Add accessible Batch and Serial creation modals plus availability/trace views, then run and commit.**

```bash
php tests/inventory-warehouse-serial-batch.php
php tests/inventory-warehouse-ledger-valuation.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-serial-batch.php
git commit -m "Add serial and batch traceability"
```

### Task 6: `IW-08` Stock Reconciliation And Integrity Diagnostics

**Files:**
- Create: `company/admin/modules/inventory-warehouse/reconciliation.php`
- Modify: `company/admin/modules/inventory-warehouse/schema.php`
- Modify: `company/admin/modules/inventory-warehouse/views/record-modal.php`
- Create: `tests/inventory-warehouse-reconciliation.php`

**Interfaces:**

```php
function yovel_admin_save_stock_reconciliation(array $company, ?array $admin, array $input): array;
function yovel_admin_submit_stock_reconciliation(array $company, ?array $admin, string $reconciliationKey): array;
function yovel_admin_cancel_stock_reconciliation(array $company, ?array $admin, string $reconciliationKey, string $reason): array;
function yovel_admin_inventory_integrity_diagnostics(array $company, array $filters = []): array;
```

- [ ] **Step 1: Write failing reconciliation tests.** Cover `OPENING_STOCK` and `STOCK_RECONCILIATION`, posting datetime snapshots, duplicate item/warehouse/dimension rejection, no-change row removal, quantity-only and valuation-only differences, reserved-stock floors, leaf warehouses, serial/batch completeness, difference amount, draft/submit/cancel/amend lifecycle, and reversal-based cancellation.
- [ ] **Step 2: Add diagnostic tests.** Detect FIFO queue versus quantity mismatch, ledger equation violations, ledger/bin variance, incorrect stock value, batch quantity variance, serial-count variance, and orphaned bundle effects without mutating data.
- [ ] **Step 3: Run `php tests/inventory-warehouse-reconciliation.php` and capture the expected failure.**
- [ ] **Step 4: Implement reconciliation as a target-state document.** At submit, lock every affected partition in stable key order, reload current state at the posting datetime, calculate signed quantity/value effects, validate serial/batch replacement sets, post through `yovel_admin_inventory_post_ledger_effects()`, write the audit event, read back the document/lines/ledger effects, and commit once.
- [ ] **Step 5: Expose the difference-account/cost-center payload to Finance.** Keep the affected row `PARTIAL` until the orchestrator verifies the GL side and cancellation reversal.
- [ ] **Step 6: Render reconciliation Add and Submit through separate modal and confirmation layers, then run and commit.**

```bash
php tests/inventory-warehouse-reconciliation.php
php tests/inventory-warehouse-serial-batch.php
php tests/inventory-warehouse-ledger-valuation.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-reconciliation.php
git commit -m "Add stock reconciliation controls"
```

### Task 7: `IW-05A` Stock Entry And Material Request

**Files:**
- Create: `company/admin/modules/inventory-warehouse/transactions.php`
- Modify: `company/admin/modules/inventory-warehouse/schema.php`
- Modify: `company/admin/modules/inventory-warehouse/views/record-modal.php`
- Create: `tests/inventory-warehouse-transactions.php`

**Interfaces:**

```php
function yovel_admin_save_stock_entry(array $company, ?array $admin, array $input): array;
function yovel_admin_submit_stock_entry(array $company, ?array $admin, string $entryKey): array;
function yovel_admin_cancel_stock_entry(array $company, ?array $admin, string $entryKey, string $reason): array;
function yovel_admin_save_material_request(array $company, ?array $admin, array $input): array;
```

- [ ] **Step 1: Write failing Stock Entry tests.** Cover Material Issue, Receipt, Transfer, Transfer for Manufacture, Consumption, Manufacture, Repack, subcontracting send/return, disassembly, customer receipt/return, source/target warehouse requirements, UOM conversion, additional cost allocation, inspection requirement, serial/batch bundles, immutable submitted documents, idempotent submit, and additive cancel.
- [ ] **Step 2: Write failing Material Request tests.** Cover purpose, requested/ordered/received/transferred quantities, schedule date, source/target warehouses, partial completion, close/reopen rules, and projected-quantity contribution.
- [ ] **Step 3: Run `php tests/inventory-warehouse-transactions.php` and verify the expected failures.**
- [ ] **Step 4: Implement draft document upserts and owner-transaction submit/cancel services.** Submit locks the draft, revalidates current item/warehouse/UOM/reservation/serial/batch state, posts all ledger and bundle effects, audits, reads the full document and effects back, and commits. Server errors rehydrate the populated modal.
- [ ] **Step 5: Expose manufacturing/subcontracting source references as opaque validated keys.** Ask the orchestrator to wire source-document authorization; do not query or write another module's tables from Inventory.
- [ ] **Step 6: Run and commit the first `IW-05` review gate.**

```bash
php tests/inventory-warehouse-transactions.php
php tests/inventory-warehouse-ledger-valuation.php
php tests/inventory-warehouse-serial-batch.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-transactions.php
git commit -m "Add stock entry and material requests"
```

### Task 8: `IW-05B` Purchase Receipt, Delivery Note, And Landed Cost

**Files:**
- Modify: `company/admin/modules/inventory-warehouse/transactions.php`
- Modify: `company/admin/modules/inventory-warehouse/schema.php`
- Modify: `company/admin/modules/inventory-warehouse/views/record-modal.php`
- Modify: `tests/inventory-warehouse-transactions.php`

**Interfaces:**

```php
function yovel_admin_save_purchase_receipt(array $company, ?array $admin, array $input): array;
function yovel_admin_submit_purchase_receipt(array $company, ?array $admin, string $receiptKey): array;
function yovel_admin_save_delivery_note(array $company, ?array $admin, array $input): array;
function yovel_admin_submit_delivery_note(array $company, ?array $admin, string $deliveryKey): array;
function yovel_admin_apply_landed_cost(array $company, ?array $admin, array $input): array;
```

- [ ] **Step 1: Extend transaction tests.** Cover accepted/rejected receipt warehouses, supplier delivery references, returned quantities, delivery reservations, packed items, customer returns, serial/batch requirements, inspection gates, landed-cost allocation by quantity/value/equal weighting, and valuation replay after landed-cost application.
- [ ] **Step 2: Run the focused test and verify the new cases fail.**
- [ ] **Step 3: Implement receipt, delivery, return, and landed-cost documents through the same immutable submit/cancel contract.** Landed cost posts zero-quantity value effects and triggers deterministic future replay; it cannot modify original ledger rows.
- [ ] **Step 4: Request Buying and Sales handoff wiring from the orchestrator.** Keep external source-document mapping rows `PARTIAL` until cross-module tests prove authorization, cancellation ordering, and idempotency.
- [ ] **Step 5: Run and commit.**

```bash
php tests/inventory-warehouse-transactions.php
php tests/inventory-warehouse-reconciliation.php
php tests/inventory-warehouse-ledger-valuation.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-transactions.php
git commit -m "Add warehouse receipts deliveries and landed cost"
```

### Task 9: `IW-06` Reservations, Picking, Packed Items, And Shortage

**Files:**
- Create: `company/admin/modules/inventory-warehouse/reservations.php`
- Modify: `company/admin/modules/inventory-warehouse/schema.php`
- Modify: `company/admin/modules/inventory-warehouse/views/record-modal.php`
- Create: `tests/inventory-warehouse-reservations.php`

**Interfaces:**

```php
function yovel_admin_create_stock_reservation(array $company, ?array $admin, array $input): array;
function yovel_admin_update_stock_reservation(array $company, ?array $admin, string $reservationKey, array $input): array;
function yovel_admin_cancel_stock_reservation(array $company, ?array $admin, string $reservationKey, string $reason): array;
function yovel_admin_create_pick_list(array $company, ?array $admin, array $input): array;
function yovel_admin_inventory_shortages(array $company, array $demand): array;
```

- [ ] **Step 1: Write failing reservation tests.** Cover quantity-based and serial/batch-based reservation, integer-only serialized quantities, `available = actual - other active reservations`, group/disabled warehouse rejection, statuses Draft/Partially Reserved/Reserved/Partially Delivered/Partially Used/Delivered/Cancelled/Closed, consumed/transferred quantities, pick-list allocation, cancellation dependencies, and concurrent over-reservation prevention.
- [ ] **Step 2: Add pick/packed-item tests.** Enforce FIFO/FEFO allocation settings, exact picked versus reserved quantities, warehouse/bin order, packed bundle expansion, partial fulfillment, returned quantities, and shortage recommendations.
- [ ] **Step 3: Run `php tests/inventory-warehouse-reservations.php` and capture the expected failure.**
- [ ] **Step 4: Implement reservation writes by locking the bin and active reservation rows in one transaction.** Recalculate availability from authoritative ledger/bin state, reserve exact serials/batches when requested, update projected quantities, audit, read back header/entries/bin totals, and commit.
- [ ] **Step 5: Ask the orchestrator to wire Sales, Manufacturing, and Projects voucher validation.** Inventory stores opaque source keys and status snapshots; it does not write source-module documents.
- [ ] **Step 6: Run and commit.**

```bash
php tests/inventory-warehouse-reservations.php
php tests/inventory-warehouse-serial-batch.php
php tests/inventory-warehouse-warehouses.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-reservations.php
git commit -m "Add stock reservation and picking"
```

### Task 10: `IW-09` Packing, Shipment, Delivery Trips, And Print Models

**Files:**
- Create: `company/admin/modules/inventory-warehouse/logistics.php`
- Modify: `company/admin/modules/inventory-warehouse/schema.php`
- Create: `company/admin/modules/inventory-warehouse/views/print.php`
- Create: `tests/inventory-warehouse-logistics.php`

**Interfaces:**

```php
function yovel_admin_save_packing_slip(array $company, ?array $admin, array $input): array;
function yovel_admin_save_shipment(array $company, ?array $admin, array $input): array;
function yovel_admin_save_delivery_trip(array $company, ?array $admin, array $input): array;
function yovel_admin_inventory_print_model(array $company, string $documentType, string $documentKey, string $format): array;
```

- [ ] **Step 1: Write failing logistics tests.** Cover packing quantities not exceeding picked/deliverable quantities, parcel dimensions/weight, shipment carrier/tracking/status, linked delivery documents, ordered delivery stops, trip status, cancellation, company isolation, and print-model authorization.
- [ ] **Step 2: Run `php tests/inventory-warehouse-logistics.php` and verify it fails before services exist.**
- [ ] **Step 3: Implement draft/upsert/status writes under the common confirmation, transaction, audit, read-back, and rehydration contract.** Shipment and trip status changes must be explicit audited transitions.
- [ ] **Step 4: Implement BuilderX print models for standard delivery, delivery with item image, pick list, and receipt serial/batch bundle.** Use local data and styles; do not copy upstream templates or branding.
- [ ] **Step 5: Run and commit.**

```bash
php tests/inventory-warehouse-logistics.php
php tests/inventory-warehouse-reservations.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-logistics.php
git commit -m "Add warehouse packing and shipment workflows"
```

### Task 11: `IW-10` Quality Inspection

**Files:**
- Create: `company/admin/modules/inventory-warehouse/quality.php`
- Modify: `company/admin/modules/inventory-warehouse/schema.php`
- Modify: `company/admin/modules/inventory-warehouse/forms.php`
- Create: `tests/inventory-warehouse-quality.php`

**Interfaces:**

```php
function yovel_admin_save_quality_template(array $company, ?array $admin, array $input): array;
function yovel_admin_save_quality_inspection(array $company, ?array $admin, array $input): array;
function yovel_admin_submit_quality_inspection(array $company, ?array $admin, string $inspectionKey): array;
function yovel_admin_inventory_inspection_result(array $company, string $sourceType, string $sourceKey, string $sourceLineKey): ?array;
```

- [ ] **Step 1: Write failing inspection tests.** Cover parameter groups/templates, numeric/non-numeric readings, min/max/formula validation, sample size, accepted/rejected quantities, incoming/outgoing/in-process references, Draft/Accepted/Rejected/Cancelled transitions, and prevention of receipt/Stock Entry submit when required inspection is absent or rejected.
- [ ] **Step 2: Run `php tests/inventory-warehouse-quality.php` and capture the expected failure.**
- [ ] **Step 3: Implement module-local masters and inspection lifecycle using Form Builder and the common write contract.** Keep accepted/rejected warehouse effects in the parent stock transaction, not in quality tables.
- [ ] **Step 4: Escalate final cross-module Quality ownership and Manufacturing wiring.** Rows remain `PARTIAL` until the orchestrator confirms one authoritative inspection service and cross-module tests pass.
- [ ] **Step 5: Run and commit.**

```bash
php tests/inventory-warehouse-quality.php
php tests/inventory-warehouse-transactions.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-quality.php
git commit -m "Add inventory quality inspections"
```

### Task 12: `IW-11` Reports, Analytics, And Export Surfaces

**Files:**
- Create: `company/admin/modules/inventory-warehouse/reports.php`
- Create: `company/admin/modules/inventory-warehouse/views/report.php`
- Create: `tests/inventory-warehouse-reports.php`

**Interfaces:**

```php
function yovel_admin_inventory_report_catalogue(): array;
function yovel_admin_inventory_run_report(array $company, string $reportKey, array $filters): array;
function yovel_admin_inventory_export_report(array $company, string $reportKey, array $filters, string $format): array;
```

- [ ] **Step 1: Write failing report tests.** Cover every `IW-04`, `IW-06`, `IW-07`, `IW-08`, and `IW-11` report row with a stable report key, company scope, parameterized filters, deterministic ordering, decimal totals, no mutation, and empty-state behavior. Include stock balance, ledger, ageing, analytics, COGS by item group, item consumption, item where-used, delayed/shortage reports, serial/batch availability and traceability, reserved stock, reconciliation variance, and warehouse-wise value.
- [ ] **Step 2: Add parity fixtures with receipts, issues, transfers, backdated replay, serial/batch moves, reservations, reconciliation, and cancellation.** Assert report totals equal authoritative ledger/bin/bundle/reservation state.
- [ ] **Step 3: Run `php tests/inventory-warehouse-reports.php` and verify the expected failure.**
- [ ] **Step 4: Implement a report allowlist and one query service.** Each report definition declares filters, columns, grouping, sort, and export permissions. Never interpolate user-supplied identifiers or SQL fragments.
- [ ] **Step 5: Render report filters as compact controls in the 12/20 main panel, keep report tools in the 8/20 panel, and use accessible table semantics.** CSV export must preserve decimal strings and server-side company scope.
- [ ] **Step 6: Run and commit.**

```bash
php tests/inventory-warehouse-reports.php
php tests/inventory-warehouse-ledger-valuation.php
php tests/inventory-warehouse-serial-batch.php
php tests/inventory-warehouse-reconciliation.php
php tests/inventory-warehouse-reservations.php
git diff --check
git add company/admin/modules/inventory-warehouse tests/inventory-warehouse-reports.php
git commit -m "Add inventory reports and analytics"
```

### Task 13: Full Acceptance, Cross-Module Evidence, And Ledger Reclassification

**Files:**
- Modify: `docs/erpnext-parity/ledgers/inventory-warehouse.json`
- Verify only: orchestration-owned cross-module tests and shared route/modal tests

- [ ] **Step 1: Run all focused Inventory tests from a fresh process.**

```bash
for test_file in tests/inventory-warehouse-*.php; do php "$test_file" || exit 1; done
npx playwright test tests/inventory-warehouse-ui.spec.js
```

- [ ] **Step 2: Ask the orchestrator to run shared and cross-module acceptance.** Required evidence covers route/POST registration, modal confirmation behavior, Finance valuation/reconciliation posting and reversal, Buying receipt handoff, Sales reservation/delivery handoff, Manufacturing Stock Entry handoff, Projects dimension validation, Quality ownership, and Operations repost jobs.
- [ ] **Step 3: Reclassify ledger rows one by one.** Use `COMPLETE` only when `evidence` contains exact module file paths, the focused passing test command, and any required orchestration-owned integration test. Use `PARTIAL` for implemented module-local behavior lacking a required cross-module connection. Leave unimplemented behavior `MISSING`; do not use `DEFERRED` or `NOT_APPLICABLE` without an approved exclusion/reason.
- [ ] **Step 4: Validate row/package/status integrity.**

```bash
jq -e '[.rows[].gap_package] | all(. == "IW-01" or . == "IW-02" or . == "IW-03" or . == "IW-04" or . == "IW-05" or . == "IW-06" or . == "IW-07" or . == "IW-08" or . == "IW-09" or . == "IW-10" or . == "IW-11")' docs/erpnext-parity/ledgers/inventory-warehouse.json
php tools/erpnext-parity.php validate-ledger
git diff --check
```

- [ ] **Step 5: Commit only evidence-backed ledger changes.**

```bash
git add docs/erpnext-parity/ledgers/inventory-warehouse.json
git commit -m "Record Inventory Warehouse parity evidence"
```

## Proposed Implementation Sequence

Execute in this dependency order:

```text
Orchestrator shared contract checkpoint
  -> IW-01 shell/Form Builder/persistence
  -> IW-02 catalogue
  -> IW-03 warehouses/bins
  -> IW-04 ledger/valuation
  -> IW-07 serial/batch
  -> IW-08 reconciliation
  -> IW-05A stock entry/material request
  -> IW-05B receipt/delivery/landed cost
  -> IW-06 reservations/picking
  -> IW-09 logistics/print
  -> IW-10 quality
  -> IW-11 reports
  -> orchestration cross-module acceptance and row-by-row ledger evidence
```

The highest-risk gate is `IW-04`: no transaction package should submit stock before quantity, valuation, replay, idempotency, and cancellation invariants pass. `IW-07` and `IW-08` follow immediately because serial/batch mismatches and reconciliation can invalidate both physical quantity and accounting value. Reservations must wait until those foundations can prove available-to-reserve under concurrency.
