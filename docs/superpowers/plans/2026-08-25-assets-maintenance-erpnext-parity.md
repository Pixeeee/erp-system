# Assets / Maintenance ERPNext Parity Gap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the 37 applicable Assets and Maintenance ledger rows from the pinned ERPNext baseline as company-scoped BuilderX workflows without modifying Finance, Inventory, or orchestration-owned shared files.

**Architecture:** Build a module-local service layer beneath `company/admin/modules/assets-maintenance/` with separate lifecycle units for asset masters, depreciation, capitalization, repairs, maintenance, and reports. The module stores only Assets / Maintenance authoritative records and calls owner-provided read or command services for Finance postings, Inventory items/warehouses/stock, Buying receipts, Sales customers/orders, HR employees, Projects, Support issues, Operations assignments, and Manufacturing quality inspections. Shared routing, the universal modal/confirmation controller, and shared Form Builder dispatch remain orchestration-owned; this module exposes adapters for those contracts.

**Tech Stack:** PHP 8.5 runtime with PHP 8.1 compatibility, ADODB/MySQL, server-rendered PHP, Tailwind 4 and shadcn tokens, Material/Lucide icons already present, vanilla JavaScript through shared controllers, and PHP executable tests.

## Global Constraints

- Use ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325` as the Assets and Maintenance parity baseline.
- Preserve every existing behavior and unrelated working-tree change; implement only ledger rows classified `MISSING` or `PARTIAL`.
- Every workspace and feature page uses a 20-column desktop layout with a 12/20 left main panel and an 8/20 right action/tools panel, stacking main-first on smaller screens.
- Every Add, New, Create, or Insert action opens an accessible body-owned modal.
- Submit opens a separate confirmation dialog; persistence begins only after Confirm, Cancel returns to the populated form, and server errors rehydrate submitted values.
- Every write authorizes the administrator and company, validates server-side input, uses parameterized SQL, owns one ADODB transaction at the public workflow boundary, writes an audit event, directly reads critical state back before commit, and rolls back on failure.
- The universal Form Builder supports target selection, rows, columns, sections, stable field keys, protected fields, preview, draft, publish, archive, immutable versions, and version-bound submitted records.
- Finance owns depreciation and general-ledger posting. Inventory owns Item, Warehouse, quantity, valuation, batch, serial, and stock movement data. Assets / Maintenance must use their public services and must not write their tables.
- Module-local backward-compatible improvements are allowed with proportionate tests. Shared or cross-module improvements must be escalated to the orchestration owner.
- Implement functional parity without copying upstream GPL source, product names, logos, visual assets, or branding.
- Mobile / Android work remains deferred and is not represented by any row in this ledger.

## Audit Baseline

The 2026-08-25 audit found no `company/admin/modules/assets-maintenance/` directory and no `tests/assets-maintenance-*` files. Consequently all 37 applicable rows are `MISSING`; there are no `COMPLETE`, `PARTIAL`, `NOT_APPLICABLE`, or `DEFERRED` rows.

The workspace registry entry and generic shared Form Builder fallback currently exist in orchestration-owned files, but they do not provide module behavior or test evidence for any ledger row. Before execution, the orchestrator must finish and verify shared route loading, modal/confirmation behavior, and dispatch from `yovel_admin_shared_form_adapter('assets-maintenance')` to the module-local adapter described below.

## File Structure

- `company/admin/modules/assets-maintenance/functions.php`: module loader, section map, section resolution, data composition, and controller-facing public API.
- `company/admin/modules/assets-maintenance/schema.php`: idempotent company-scoped tables, indexes, immutable form versions, submitted-version links, and lifecycle audit records.
- `company/admin/modules/assets-maintenance/core.php`: authorization, normalization, transaction helpers, direct read-back assertions, and external dependency gateway validation.
- `company/admin/modules/assets-maintenance/forms.php`: module-local Form Builder targets, protected fields, schema normalization, immutable versions, publish/archive workflows, and server rehydration.
- `company/admin/modules/assets-maintenance/assets.php`: locations, categories, asset records, activity timeline, custody, movement, sale/scrap/cancel/amend lifecycle.
- `company/admin/modules/assets-maintenance/depreciation.php`: finance books, depreciation schedules, shift factors/allocations, value adjustments, and Finance posting commands.
- `company/admin/modules/assets-maintenance/capitalization.php`: capitalization headers and stock, asset, and service child rows.
- `company/admin/modules/assets-maintenance/service.php`: maintenance teams/tasks/logs, repairs, consumed items, and linked purchase invoices.
- `company/admin/modules/assets-maintenance/customer-maintenance.php`: customer maintenance schedules, generated visits, completion state, and Support issue handoff.
- `company/admin/modules/assets-maintenance/reports.php`: fixed asset register, activity, maintenance, schedule, depreciation, and quality-inspection read models and exports.
- `company/admin/modules/assets-maintenance/views/workspace.php`: 12/8 responsive module workspace and feature dispatcher.
- `company/admin/modules/assets-maintenance/views/dashboard.php`: setup state, shortcuts, due work, value summaries, and report links.
- `company/admin/modules/assets-maintenance/views/record-list.php`: reusable company-scoped list and filter surface.
- `company/admin/modules/assets-maintenance/views/record-modal.php`: module-local fields rendered inside the shared body-owned record modal.
- `company/admin/modules/assets-maintenance/views/form-builder.php`: builder canvas/preview left panel and tools/settings right panel.
- `company/admin/modules/assets-maintenance/views/reports.php`: report results left panel and filters/export right panel.
- `tests/assets-maintenance-contract.php`: loading, sections, schema idempotency, authorization, company isolation, shell, modal, and confirmation contracts.
- `tests/assets-maintenance-form-builder.php`: protected keys, immutable versions, publish/archive, version-bound records, rollback, and rehydration.
- `tests/assets-maintenance-assets.php`: masters, assets, movement, lifecycle, activity, and audit behavior.
- `tests/assets-maintenance-depreciation.php`: schedule calculations, shifts, adjustments, posting service calls, rollback, and cancellation.
- `tests/assets-maintenance-capitalization.php`: component validation, totals, Inventory/Finance calls, idempotency, and rollback.
- `tests/assets-maintenance-service.php`: maintenance plans/logs, repair costs, stock consumption, assignments, and audit behavior.
- `tests/assets-maintenance-customer-maintenance.php`: schedule generation, visits, customer/order/serial validation, completion, and Support handoff.
- `tests/assets-maintenance-reports.php`: fixed register, activity, maintenance, schedule, quality inspection, filters, company isolation, and export data.

## Dependency Gates

Execution starts only after the orchestrator confirms stable callable contracts for these capabilities. Module tests use injected fakes with the same signatures and assert that no direct dependency-table write occurs.

```php
/** @return array<string, callable> */
function yovel_admin_assets_dependency_services(): array;

// Required capability keys and callable shapes:
// inventory.item.read(array $company, string $itemKey): ?array
// inventory.warehouse.read(array $company, string $warehouseKey): ?array
// inventory.serial_batch.read(array $company, string $itemKey, string $referenceKey): ?array
// inventory.stock.consume(array $company, array $admin, array $command, ADOConnection $db): array
// inventory.stock.capitalize(array $company, array $admin, array $command, ADOConnection $db): array
// finance.account.read(array $company, string $accountKey): ?array
// finance.purchase_invoice.read(array $company, string $invoiceKey): ?array
// finance.asset.post(array $company, array $admin, array $command, ADOConnection $db): array
// finance.asset.reverse(array $company, array $admin, string $postingKey, string $reason, ADOConnection $db): array
// buying.purchase_receipt.read(array $company, string $receiptKey): ?array
// sales.customer.read(array $company, string $customerKey): ?array
// sales.order.read(array $company, string $orderKey): ?array
// sales.salesperson.read(array $company, string $salespersonKey): ?array
// hr.employee.read(array $company, string $employeeKey): ?array
// projects.project.read(array $company, string $projectKey): ?array
// support.issue.record_visit(array $company, array $admin, array $command, ADOConnection $db): array
// operations.assignment.sync(array $company, array $admin, array $command, ADOConnection $db): array
// manufacturing.quality_inspection.list(array $company, string $assetKey): array
```

If any capability is absent, the owning module task must expose it or the orchestrator must provide a shared adapter. The Assets task reports that gap and stops at the affected package; it does not query or mutate another owner's tables.

## Ledger Traceability

| Work package | Ledger rows |
|---|---|
| Task 1 | Assets workspace |
| Task 2 | Asset; Asset Activity doctype; Asset Category; Asset Category Account; Location; Linked Location; Asset Movement; Asset Movement Item |
| Task 3 | Asset Finance Book; Asset Depreciation Schedule; Depreciation Schedule; Asset Shift Factor; Asset Shift Allocation; Asset Value Adjustment |
| Task 4 | Asset Capitalization; Asset Capitalization Asset Item; Asset Capitalization Stock Item; Asset Capitalization Service Item |
| Task 5 | Asset Maintenance; Asset Maintenance Log; Asset Maintenance Task; Asset Maintenance Team; Maintenance Team Member; Asset Repair; Asset Repair Consumed Item; Asset Repair Purchase Invoice |
| Task 6 | Maintenance Schedule; Maintenance Schedule Detail; Maintenance Schedule Item; Maintenance Visit; Maintenance Visit Purpose |
| Task 7 | Asset Activity report; Asset Maintenance report; Fixed Asset Register report metadata; Fixed Asset Register report code; Maintenance Schedules report |

---

### Task 1: Module Contract, Schema, Workspace, And Form Builder

**Files:**

- Create: `company/admin/modules/assets-maintenance/functions.php`
- Create: `company/admin/modules/assets-maintenance/schema.php`
- Create: `company/admin/modules/assets-maintenance/core.php`
- Create: `company/admin/modules/assets-maintenance/forms.php`
- Create: `company/admin/modules/assets-maintenance/views/workspace.php`
- Create: `company/admin/modules/assets-maintenance/views/dashboard.php`
- Create: `company/admin/modules/assets-maintenance/views/record-modal.php`
- Create: `company/admin/modules/assets-maintenance/views/form-builder.php`
- Create: `tests/assets-maintenance-contract.php`
- Create: `tests/assets-maintenance-form-builder.php`

**Interfaces:**

- Produces: `yovel_admin_assets_maintenance_sections(): array`, `yovel_admin_assets_maintenance_section(): string`, `yovel_admin_assets_maintenance_schema(): void`, `yovel_admin_assets_maintenance_data(array $company, ?array $admin = null): array`.
- Produces: `yovel_admin_assets_form_adapter(): array`, `yovel_admin_assets_normalize_form_schema(string $recordType, array $schema, int $version): array`, `yovel_admin_assets_save_form(array $company, array $admin, array $input): array`.
- Consumes: shared registry, record-modal, confirmation-dialog, and Form Builder dispatch contracts from the orchestrator.
- Covers: workspace ledger row and universal module standards; no business row becomes `COMPLETE` in this task.

- [ ] **Step 1: Write contract and Form Builder tests that fail because the module is absent**

```php
assets_assert(array_keys(yovel_admin_assets_maintenance_sections()) === [
    'dashboard', 'asset-records', 'asset-depreciation-schedule',
    'fixed-asset-register', 'maintenance-schedules', 'quality-inspection',
    'maintenance-reports', 'form-builder',
]);
assets_assert($schemaRunOne === $schemaRunTwo, 'Schema setup must be idempotent.');
assets_assert($desktopColumns === [12, 8], 'Workspace must use the 12/8 contract.');
assets_assert($createTriggerHasRecordModal && $submitHasConfirmBoundary);
assets_assert($publishedVersionOneStillMatchesAfterVersionTwo);
assets_assert($submittedRecordVersionKey === $publishedVersionOneKey);
```

- [ ] **Step 2: Run the focused tests and verify the missing-file failures**

Run: `php tests/assets-maintenance-contract.php && php tests/assets-maintenance-form-builder.php`

Expected: non-zero exit because `functions.php` and the module contracts do not exist.

- [ ] **Step 3: Implement the loader, idempotent schema, and Form Builder adapter**

Create company-key/hash columns and company-leading indexes on every table. Form metadata uses `project_company_asset_form`, `project_company_asset_form_version`, and `project_company_asset_form_submission`; published versions are immutable, archived versions remain readable, and submitted records retain their original `form_version_key`. Protected fields include company key, record key/code, lifecycle status, document status, posting references, version key, and audit identity.

```php
return [
    'module' => 'assets-maintenance',
    'target_record_types' => [
        'asset-records' => 'asset',
        'maintenance-schedules' => 'maintenance-schedule',
        'quality-inspection' => 'quality-inspection-link',
    ],
    'protected_fields' => $protectedFields,
    'field_types' => ['SHORT_TEXT', 'PARAGRAPH', 'NUMBER', 'CURRENCY', 'DATE', 'DROPDOWN', 'CHECKBOXES', 'SECTION'],
    'row_column_layout' => ['version' => 1, 'max_columns' => 3, 'stable_keys' => true],
    'normalize' => 'yovel_admin_assets_normalize_form_schema',
    'version_identity' => 'yovel_admin_assets_form_version_checksum',
    'renderer' => 'company/admin/modules/assets-maintenance/views/form-builder.php',
];
```

- [ ] **Step 4: Implement the workspace and modal surfaces**

Render a stable `xl:grid-cols-20` shell with `xl:col-span-12` main content and `xl:col-span-8` utilities. All creation triggers target the shared record modal; each form carries `data-confirm-submit`, and no module JavaScript submits or persists before the shared Confirm event.

- [ ] **Step 5: Run tests twice and request the shared adapter handoff**

Run: `php tests/assets-maintenance-contract.php && php tests/assets-maintenance-form-builder.php && php tests/assets-maintenance-contract.php && php tests/assets-maintenance-form-builder.php`

Expected: both passes succeed, schema counts remain stable, and fixtures leave no rows. Ask the orchestrator to dispatch `yovel_admin_shared_form_adapter('assets-maintenance')` to `yovel_admin_assets_form_adapter()`; do not edit shared files.

- [ ] **Step 6: Commit the module foundation**

```bash
git add company/admin/modules/assets-maintenance tests/assets-maintenance-contract.php tests/assets-maintenance-form-builder.php
git commit -m "Add Assets Maintenance module contracts"
```

### Task 2: Asset, Category, Location, Activity, And Movement Lifecycles

**Files:**

- Create: `company/admin/modules/assets-maintenance/assets.php`
- Create: `company/admin/modules/assets-maintenance/views/record-list.php`
- Modify: `company/admin/modules/assets-maintenance/functions.php`
- Modify: `company/admin/modules/assets-maintenance/schema.php`
- Modify: `company/admin/modules/assets-maintenance/views/workspace.php`
- Modify: `company/admin/modules/assets-maintenance/views/record-modal.php`
- Test: `tests/assets-maintenance-assets.php`

**Interfaces:**

- Produces: `yovel_admin_assets_save_asset(array $company, array $admin, array $input, ?array $services = null): array`, `yovel_admin_assets_submit_asset(array $company, array $admin, string $assetKey, ?array $services = null): array`, and `yovel_admin_assets_cancel_asset(array $company, array $admin, string $assetKey, string $reason, ?array $services = null): array`.
- Produces: `yovel_admin_assets_save_movement(array $company, array $admin, array $input, ?array $services = null): array`, `yovel_admin_assets_submit_movement(array $company, array $admin, string $movementKey, ?array $services = null): array`, and `yovel_admin_assets_cancel_movement(array $company, array $admin, string $movementKey, string $reason, ?array $services = null): array`.
- Consumes: Item, Purchase Receipt, Purchase Invoice, account, posting, and employee read services through `yovel_admin_assets_dependency_services()`.
- Covers eight ledger rows listed for Task 2.

- [ ] **Step 1: Write failing service tests**

Test company isolation, duplicate codes, tree-safe locations, category/account validation, fixed-asset Item validation, purchase-reference quantity limits, draft-to-submitted-to-cancelled/amended asset transitions, sale/scrap guards, chronological movements, custody/location updates, cancellation restoration, activity rows, transaction rollback, audit fields, read-back, and injected dependency calls.

- [ ] **Step 2: Run the test and verify missing service failures**

Run: `php tests/assets-maintenance-assets.php`

Expected: non-zero exit at the first missing asset service.

- [ ] **Step 3: Implement masters and asset lifecycle tables**

Use `project_company_asset_location`, `project_company_asset_category`, `project_company_asset_category_account`, `project_company_asset`, `project_company_asset_finance_book`, `project_company_asset_activity`, `project_company_asset_movement`, and `project_company_asset_movement_item`. Store stable external keys and snapshots needed for historical display, but never duplicate current Inventory quantity/value or Finance ledger entries.

- [ ] **Step 4: Implement transaction-owned workflows and server rehydration**

Each public save/submit/cancel workflow begins one transaction, locks the asset when changing lifecycle or custody, writes child rows and activity/audit events, verifies status/location/custodian through a direct read, and commits. Validation exceptions return normalized field values and errors so the shared modal reopens populated.

- [ ] **Step 5: Run the focused and foundation tests**

Run: `php tests/assets-maintenance-assets.php && php tests/assets-maintenance-contract.php && php tests/assets-maintenance-form-builder.php`

Expected: all commands exit `0` with zero cross-company reads and no dependency-table writes.

- [ ] **Step 6: Commit asset masters and movement**

```bash
git add company/admin/modules/assets-maintenance tests/assets-maintenance-assets.php
git commit -m "Add asset records and custody movement"
```

### Task 3: Depreciation, Shift Allocation, And Value Adjustment

**Files:**

- Create: `company/admin/modules/assets-maintenance/depreciation.php`
- Modify: `company/admin/modules/assets-maintenance/schema.php`
- Modify: `company/admin/modules/assets-maintenance/functions.php`
- Modify: `company/admin/modules/assets-maintenance/views/workspace.php`
- Modify: `company/admin/modules/assets-maintenance/views/record-modal.php`
- Test: `tests/assets-maintenance-depreciation.php`

**Interfaces:**

- Produces: `yovel_admin_assets_calculate_schedule(array $terms): array`, `yovel_admin_assets_submit_depreciation_schedule(array $company, array $admin, string $scheduleKey, ?array $services = null): array`, and `yovel_admin_assets_cancel_depreciation_schedule(array $company, array $admin, string $scheduleKey, string $reason, ?array $services = null): array`.
- Produces: `yovel_admin_assets_save_shift_allocation(array $company, array $admin, array $input, ?array $services = null): array`, `yovel_admin_assets_submit_shift_allocation(array $company, array $admin, string $allocationKey, ?array $services = null): array`, `yovel_admin_assets_save_value_adjustment(array $company, array $admin, array $input, ?array $services = null): array`, `yovel_admin_assets_submit_value_adjustment(array $company, array $admin, string $adjustmentKey, ?array $services = null): array`, and `yovel_admin_assets_cancel_value_adjustment(array $company, array $admin, string $adjustmentKey, string $reason, ?array $services = null): array`.
- Consumes: Finance account/finance-book reads plus `finance.asset.post` and `finance.asset.reverse`; Assets stores schedule authority while Finance stores posting authority.
- Covers six ledger rows listed for Task 3.

- [ ] **Step 1: Write failing calculation and lifecycle tests**

Cover straight-line, double-declining, written-down-value, manual schedules, daily prorating, frequency/count limits, salvage floor, opening booked depreciation, active-schedule uniqueness, shift-factor rescheduling, past-date guards, value increase/decrease, Finance rejection rollback, duplicate posting idempotency, cancellation reversal, and direct read-back.

- [ ] **Step 2: Run the test and verify missing calculation failures**

Run: `php tests/assets-maintenance-depreciation.php`

Expected: non-zero exit because calculation and posting workflows do not exist.

- [ ] **Step 3: Implement deterministic schedules and shift replacement**

Use integer minor units for currency calculations and explicit rounding at schedule boundaries. Store headers in `project_company_asset_depreciation_schedule`, lines in `project_company_asset_depreciation_line`, shift factors in `project_company_asset_shift_factor`, allocations in `project_company_asset_shift_allocation`, and adjustments in `project_company_asset_value_adjustment`. Submitted schedule versions are never silently rewritten; rescheduling creates a replacement linked to the superseded schedule.

- [ ] **Step 4: Implement Finance command and reversal boundaries**

Send deterministic command keys composed from company, asset, schedule/adjustment, and event. Join the public workflow transaction when the Finance service supports an outer `ADOConnection`; otherwise stop and escalate transaction composition to the orchestrator. Store only the returned posting key and verified summary.

- [ ] **Step 5: Run focused regression twice**

Run: `php tests/assets-maintenance-depreciation.php && php tests/assets-maintenance-assets.php && php tests/assets-maintenance-depreciation.php`

Expected: all commands exit `0`; the second depreciation run creates no duplicate schedules or postings.

- [ ] **Step 6: Commit depreciation workflows**

```bash
git add company/admin/modules/assets-maintenance tests/assets-maintenance-depreciation.php
git commit -m "Add asset depreciation and adjustments"
```

### Task 4: Asset Capitalization

**Files:**

- Create: `company/admin/modules/assets-maintenance/capitalization.php`
- Modify: `company/admin/modules/assets-maintenance/schema.php`
- Modify: `company/admin/modules/assets-maintenance/functions.php`
- Modify: `company/admin/modules/assets-maintenance/views/workspace.php`
- Modify: `company/admin/modules/assets-maintenance/views/record-modal.php`
- Test: `tests/assets-maintenance-capitalization.php`

**Interfaces:**

- Produces: `yovel_admin_assets_save_capitalization(array $company, array $admin, array $input, ?array $services = null): array`, `yovel_admin_assets_submit_capitalization(array $company, array $admin, string $capitalizationKey, ?array $services = null): array`, and `yovel_admin_assets_cancel_capitalization(array $company, array $admin, string $capitalizationKey, string $reason, ?array $services = null): array`.
- Consumes: Inventory Item/Warehouse/serial-batch/stock-capitalization services, Finance account/post/reverse services, Project reads, and Task 2 asset locks.
- Covers four capitalization ledger rows.

- [ ] **Step 1: Write failing capitalization tests**

Assert exactly one target Item or target Asset, at least one stock/asset/service source, source uniqueness, positive quantities and rates, Inventory company/warehouse validation, consumed-asset eligibility, service account validity, deterministic totals, draft immutability after submit, target update, cancellation restoration, cross-service rollback, idempotent command keys, audit events, and read-back.

- [ ] **Step 2: Run the test and verify the missing workflow failure**

Run: `php tests/assets-maintenance-capitalization.php`

Expected: non-zero exit because capitalization services are absent.

- [ ] **Step 3: Implement header and child persistence**

Use `project_company_asset_capitalization`, `project_company_asset_capitalization_stock_item`, `project_company_asset_capitalization_asset_item`, and `project_company_asset_capitalization_service_item`. Replace draft children atomically by stable child keys; lock and preserve submitted children.

- [ ] **Step 4: Implement submit/cancel orchestration**

Within one public transaction, lock the capitalization and involved assets, invoke Inventory capitalization, invoke Finance posting, update the target asset, write activity/audit, directly read all child counts, totals, status, Inventory reference, and Finance posting key, then commit. Reverse both owner commands before marking cancelled.

- [ ] **Step 5: Run capitalization and dependency regressions**

Run: `php tests/assets-maintenance-capitalization.php && php tests/assets-maintenance-assets.php && php tests/assets-maintenance-depreciation.php`

Expected: all commands exit `0` and injected owner failures leave no local or external fixture mutations.

- [ ] **Step 6: Commit capitalization**

```bash
git add company/admin/modules/assets-maintenance tests/assets-maintenance-capitalization.php
git commit -m "Add asset capitalization workflow"
```

### Task 5: Internal Maintenance, Teams, Logs, And Repairs

**Files:**

- Create: `company/admin/modules/assets-maintenance/service.php`
- Modify: `company/admin/modules/assets-maintenance/schema.php`
- Modify: `company/admin/modules/assets-maintenance/functions.php`
- Modify: `company/admin/modules/assets-maintenance/views/workspace.php`
- Modify: `company/admin/modules/assets-maintenance/views/record-modal.php`
- Test: `tests/assets-maintenance-service.php`

**Interfaces:**

- Produces: `yovel_admin_assets_save_maintenance_team(array $company, array $admin, array $input, ?array $services = null): array`, `yovel_admin_assets_save_maintenance_plan(array $company, array $admin, array $input, ?array $services = null): array`, `yovel_admin_assets_submit_maintenance_log(array $company, array $admin, string $logKey, ?array $services = null): array`, and `yovel_admin_assets_next_due_date(string $startDate, string $periodicity, string $lastCompletion = ''): string`.
- Produces: `yovel_admin_assets_save_repair(array $company, array $admin, array $input, ?array $services = null): array`, `yovel_admin_assets_submit_repair(array $company, array $admin, string $repairKey, ?array $services = null): array`, and `yovel_admin_assets_cancel_repair(array $company, array $admin, string $repairKey, string $reason, ?array $services = null): array`.
- Consumes: Operations assignment/user services, Inventory item/warehouse/serial-batch/stock-consume services, Finance purchase-invoice/account/post/reverse services, and Project reads.
- Covers eight ledger rows listed for Task 5.

- [ ] **Step 1: Write failing maintenance and repair tests**

Cover team membership/role validation, duplicate tasks, preventive/calibration periodicity, due-date rollover, overdue state, assignment synchronization, planned-to-completed/cancelled log transitions, asset maintenance status, repair date/status/downtime validation, duplicate purchase invoices, allocated repair-cost limits, consumed-stock cost, stock failure rollback, optional capitalization of repair cost, Finance reversal, activity/audit, company isolation, and read-back.

- [ ] **Step 2: Run the test and verify missing service failures**

Run: `php tests/assets-maintenance-service.php`

Expected: non-zero exit at the first missing maintenance function.

- [ ] **Step 3: Implement maintenance and repair tables**

Use `project_company_asset_maintenance_team`, `_team_member`, `_maintenance_plan`, `_maintenance_task`, `_maintenance_log`, `_repair`, `_repair_consumed_item`, and `_repair_purchase_invoice`. Child records use stable keys and company hash; submitted logs and repairs are immutable except through cancel/amend.

- [ ] **Step 4: Implement owner-service workflows**

Synchronize assignments through Operations, consume repair stock through Inventory, and post capitalized repair cost through Finance. Public submit/cancel methods own locking, one transaction, local writes, owner calls, audit/activity, direct read-back, and rollback.

- [ ] **Step 5: Run focused and asset lifecycle regressions twice**

Run: `php tests/assets-maintenance-service.php && php tests/assets-maintenance-assets.php && php tests/assets-maintenance-service.php`

Expected: all commands exit `0`, repeated due processing is idempotent, and no fixture data remains.

- [ ] **Step 6: Commit internal maintenance and repairs**

```bash
git add company/admin/modules/assets-maintenance tests/assets-maintenance-service.php
git commit -m "Add asset maintenance and repair workflows"
```

### Task 6: Customer Maintenance Schedules And Visits

**Files:**

- Create: `company/admin/modules/assets-maintenance/customer-maintenance.php`
- Modify: `company/admin/modules/assets-maintenance/schema.php`
- Modify: `company/admin/modules/assets-maintenance/functions.php`
- Modify: `company/admin/modules/assets-maintenance/views/workspace.php`
- Modify: `company/admin/modules/assets-maintenance/views/record-modal.php`
- Test: `tests/assets-maintenance-customer-maintenance.php`

**Interfaces:**

- Produces: `yovel_admin_assets_save_customer_maintenance_schedule(array $company, array $admin, array $input, ?array $services = null): array`, `yovel_admin_assets_generate_customer_maintenance_details(array $schedule, array $items): array`, `yovel_admin_assets_submit_customer_maintenance_schedule(array $company, array $admin, string $scheduleKey, ?array $services = null): array`, and `yovel_admin_assets_cancel_customer_maintenance_schedule(array $company, array $admin, string $scheduleKey, string $reason, ?array $services = null): array`.
- Produces: `yovel_admin_assets_save_maintenance_visit(array $company, array $admin, array $input, ?array $services = null): array`, `yovel_admin_assets_submit_maintenance_visit(array $company, array $admin, string $visitKey, ?array $services = null): array`, `yovel_admin_assets_cancel_maintenance_visit(array $company, array $admin, string $visitKey, string $reason, ?array $services = null): array`, and `yovel_admin_assets_recalculate_schedule_completion(array $company, string $scheduleKey): array`.
- Consumes: Sales customer/order/salesperson reads, Inventory item/serial-batch reads, and Support issue visit updates.
- Covers five Maintenance-module ledger rows.

- [ ] **Step 1: Write failing schedule and visit tests**

Assert customer/company validity, Sales Order ownership and item match, Item and serial/batch validity, positive visit counts, periodic and random date generation inside start/end bounds, holiday-date shifting, schedule-line immutability after submit, visit purpose requirements, scheduled/unscheduled/breakdown behavior, partial/full completion, cancellation restoration, Support issue rollback, audit, company isolation, and read-back.

- [ ] **Step 2: Run the test and verify missing workflow failures**

Run: `php tests/assets-maintenance-customer-maintenance.php`

Expected: non-zero exit because customer maintenance workflows are absent.

- [ ] **Step 3: Implement schedule and visit persistence**

Use `project_company_maintenance_schedule`, `_schedule_item`, `_schedule_detail`, `_visit`, and `_visit_purpose`. Generated detail rows have deterministic keys based on schedule item, date, and ordinal so retries do not duplicate visits.

- [ ] **Step 4: Implement submit/cancel and Support handoff**

Lock schedule/detail rows during visit completion updates. A submitted visit updates completion status and the linked Support issue inside the public transaction; cancellation reverses those projections. Validation failures retain all item/purpose rows for server rehydration.

- [ ] **Step 5: Run customer maintenance and service regressions**

Run: `php tests/assets-maintenance-customer-maintenance.php && php tests/assets-maintenance-service.php && php tests/assets-maintenance-contract.php`

Expected: all commands exit `0` with no cross-company reads or duplicate generated dates.

- [ ] **Step 6: Commit schedules and visits**

```bash
git add company/admin/modules/assets-maintenance tests/assets-maintenance-customer-maintenance.php
git commit -m "Add maintenance schedules and visits"
```

### Task 7: Reports, Fixed Asset Register, And Quality Inspection Handoff

**Files:**

- Create: `company/admin/modules/assets-maintenance/reports.php`
- Create: `company/admin/modules/assets-maintenance/views/reports.php`
- Modify: `company/admin/modules/assets-maintenance/functions.php`
- Modify: `company/admin/modules/assets-maintenance/views/workspace.php`
- Test: `tests/assets-maintenance-reports.php`

**Interfaces:**

- Produces: `yovel_admin_assets_fixed_register(array $company, array $filters, ?array $services = null): array`, `yovel_admin_assets_activity_report(array $company, array $filters, ?array $services = null): array`, `yovel_admin_assets_maintenance_report(array $company, array $filters, ?array $services = null): array`, `yovel_admin_assets_schedule_report(array $company, array $filters, ?array $services = null): array`, and `yovel_admin_assets_quality_inspections(array $company, array $filters, ?array $services = null): array`.
- Produces: `yovel_admin_assets_export_rows(string $report, array $rows): array`, which returns stable CSV rows and never writes files directly.
- Consumes: local read models, Finance posting summaries, and Manufacturing quality-inspection reads.
- Covers five report ledger rows; the Manufacturing service supplies the registered quality-inspection feature without duplicating inspection authority.

- [ ] **Step 1: Write failing report tests**

Cover company isolation, as-of-date acquisition/depreciation/disposal values, opening/addition/depreciation/closing reconciliation, category/location/status filters, activity ordering, due/overdue/completed maintenance, schedule completion, linked quality inspections, stable CSV columns, authorization, empty states, and no write during report generation.

- [ ] **Step 2: Run the test and verify missing report failures**

Run: `php tests/assets-maintenance-reports.php`

Expected: non-zero exit because report functions do not exist.

- [ ] **Step 3: Implement parameterized read models and exports**

Every query begins with `company_key_hash = ?`, validates date/filter enums, and joins only module-local tables. Finance and Manufacturing data enter through batched service results keyed by stable record keys; no direct cross-module SQL is allowed.

- [ ] **Step 4: Render report pages with the 12/8 contract**

Place results, totals, and charts in the 12-column main panel; filters, export commands, report settings, and context links go in the 8-column utility panel. Export requires confirmation through the shared dialog and records an audit event only after Confirm.

- [ ] **Step 5: Run the complete focused module suite**

Run: `for test_file in tests/assets-maintenance-*.php; do php "$test_file" || exit 1; done`

Expected: every test exits `0` and all report fixtures are cleaned up.

- [ ] **Step 6: Commit reports**

```bash
git add company/admin/modules/assets-maintenance tests/assets-maintenance-reports.php
git commit -m "Add asset and maintenance reports"
```

### Task 8: Evidence Closure And Orchestrator Integration Handoff

**Files:**

- Modify: `docs/erpnext-parity/ledgers/assets-maintenance.json`
- Verify: `company/admin/modules/assets-maintenance/`
- Verify: `tests/assets-maintenance-*`

**Interfaces:**

- Consumes: passing Tasks 1-7, shared route/modal/Form Builder registration, and owner-service integration evidence.
- Produces: row-level `COMPLETE` evidence only for verified rows and an explicit orchestrator handoff for shared and cross-module checks.

- [ ] **Step 1: Run syntax, focused tests twice, and whitespace verification**

```bash
find company/admin/modules/assets-maintenance -name '*.php' -print0 | xargs -0 -n1 php -l
for pass in 1 2; do for test_file in tests/assets-maintenance-*.php; do php "$test_file" || exit 1; done; done
git diff --check -- company/admin/modules/assets-maintenance tests/assets-maintenance-\* docs/erpnext-parity/ledgers/assets-maintenance.json
```

Expected: all commands exit `0`; the second pass exposes no non-idempotent schema, posting, schedule, or fixture behavior.

- [ ] **Step 2: Run authenticated route and browser checks after orchestrator wiring**

Verify every registered section returns non-500 output without PHP warnings. At desktop and mobile widths, assert the 12/8 layout and main-first stack, modal focus containment/restoration, Submit-to-Confirm with no earlier mutation, Cancel value retention, server-error rehydration, no overlap, and no horizontal page scroll.

- [ ] **Step 3: Request orchestration-owned cross-module flow verification**

The orchestrator runs the Asset acquisition-to-capitalization-to-depreciation-to-maintenance-to-disposal flow, verifies Finance and Inventory rollback under injected failure, and confirms no Assets code directly writes dependency tables. The Assets task must not edit `tests/erpnext-asset-lifecycle-flow.php` or shared files.

- [ ] **Step 4: Update each ledger row from fresh evidence**

For each source row, set `COMPLETE` only when `evidence` names the exact module implementation file, exact passing focused test file, and relevant route/integration evidence. Leave a row `PARTIAL` or `MISSING` when any lifecycle, dependency, UI state, or test remains absent; never infer completion from a workspace link.

- [ ] **Step 5: Validate the ledger and commit closure**

```bash
php tools/erpnext-parity.php validate-ledger
git diff --check -- docs/erpnext-parity/ledgers/assets-maintenance.json company/admin/modules/assets-maintenance tests/assets-maintenance-\*
git add docs/erpnext-parity/ledgers/assets-maintenance.json company/admin/modules/assets-maintenance tests/assets-maintenance-\*
git commit -m "Complete Assets Maintenance parity evidence"
```

Expected: ledger validation exits `0`; no applicable row is promoted without exact local file and passing-test evidence.

## Proposed Execution Sequence

1. Orchestrator verifies shared route, modal/confirmation, and Form Builder dispatch contracts.
2. Inventory, Finance, Buying, Sales, HR, Projects, Support, Operations, and Manufacturing publish the capability contracts listed in Dependency Gates.
3. Execute Task 1 to establish the module shell, persistence foundation, and universal Form Builder adapter.
4. Execute Task 2 before all other asset workflows because every downstream package consumes asset, category, and location authority.
5. Execute Task 3, then Task 4, so capitalization can create or update assets whose depreciation terms and Finance posting boundary are already verified.
6. Execute Task 5 for internal maintenance and repairs after Inventory stock-consume and Finance repair-posting commands pass.
7. Execute Task 6 after Sales customer/order and Support issue services pass.
8. Execute Task 7 after all write models are stable, then Task 8 for full evidence closure and orchestrator-owned integration/browser checks.

## Highest-Risk Gaps

- Transaction composition across Assets, Finance, and Inventory is the critical correctness risk. A local commit must never survive a failed posting or stock command, and retries must not duplicate owner records.
- Depreciation schedule calculation and replacement can materially alter book values; currency rounding, dates, manual rows, shifts, cancellation, and Finance reversal need independent tests.
- Capitalization and repair combine child-row totals with stock and ledger effects, creating high rollback and idempotency risk.
- Asset cancellation, sale, scrap, split/amend, and movement reversal can corrupt custody or historical reporting if submitted records are rewritten instead of reversed.
- Customer maintenance schedules combine periodic date generation, serial/item validation, Sales ownership, and Support completion updates across four authorities.
- The generic shared Form Builder fallback does not yet provide module-specific protected fields, normalization, immutable version identity, or a dedicated renderer; orchestration dispatch is a prerequisite for compliance.
