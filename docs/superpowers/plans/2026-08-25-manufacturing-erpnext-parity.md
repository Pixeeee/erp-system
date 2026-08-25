# Manufacturing ERPNext Parity Gap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement only the 122 applicable gaps in the pinned Manufacturing, Quality Management, and Subcontracting parity ledger while preserving all existing BuilderX behavior and dependency ownership.

**Architecture:** Manufacturing owns company-scoped domain records, workflows, views, Form Builder adapters, and focused tests beneath its assigned paths. Inventory/Warehouse remains authoritative for item and stock movements, Buying/Procurement for supplier and purchasing records, and Accounting/Finance for account validation and postings; Manufacturing consumes those services through explicit gateways and never writes their tables. Shared routing, modal JavaScript, global layout, and cross-module wiring remain orchestration-owned.

**Tech Stack:** PHP 8.5 runtime with PHP 8.1 compatibility, ADODB/MySQL, server-rendered PHP, Tailwind 4 and existing shadcn-style tokens, vanilla JavaScript through orchestration-owned shared controllers, PHP executable tests, and Playwright/browser verification at the orchestration integration gate.

## Global Constraints

- Use ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325` as the Manufacturing functional baseline.
- Preserve completed behavior and every current user or other-task change; implement only ledger rows marked `MISSING` or `PARTIAL`.
- Defer Mobile / Android Stockroom and all Android-specific work.
- Expose the universal module-local Form Builder adapter for every configurable Manufacturing record type.
- Use a 20-column desktop shell with a 12/20 left main panel and 8/20 right action/tools panel; stack main-first on narrow screens.
- Every Add, New, Create, or Insert command opens an accessible body-owned record modal.
- A valid Submit opens a separate sibling confirmation dialog; persistence begins only after Confirm, Cancel retains values, and Confirm submits exactly once.
- Every persisted write authorizes the administrator and company, validates CSRF and input, uses fixed identifiers and parameterized ADODB SQL, owns one transaction, checks every write, records audit history, directly verifies exact saved fields, rolls back on failure, and rehydrates from server state.
- Submitted or controlled records are corrected through explicit cancel, reverse, amend, or versioned replacement workflows, never silent rewrites.
- Module-local backward-compatible improvements are allowed with tests; shared or cross-module changes must be reported to the orchestrator and not implemented here.
- Do not modify Inventory/Warehouse, Buying/Procurement, or Accounting/Finance files or write directly to their authoritative tables.
- Reproduce functional behavior without copying upstream GPL source and without ERPNext names, logos, assets, or branding in BuilderX UI.
- Do not edit `app/foundation.php`, `company/admin/bootstrap/`, `company/admin/core/`, `company/admin/views/layout.php`, `company/admin/views/partials/`, `company/admin/assets/`, `company/admin/modules/shared/`, another module, or another module's tests/docs.

## Audit Baseline

The 2026-08-25 audit found no `company/admin/modules/manufacturing/` directory and no `tests/manufacturing-*` files. Therefore no applicable row has local implementation plus passing focused-test evidence.

| Status | Count | Audit decision |
|---|---:|---|
| `COMPLETE` | 0 | No row met the file-and-passing-test evidence gate. |
| `PARTIAL` | 0 | No module-local runtime implementation exists to classify as partial. |
| `MISSING` | 122 | All applicable runtime capabilities require implementation. |
| `NOT_APPLICABLE` | 4 | Upstream-only test fixtures/helpers: ledger rows 4, 55, 60, and 86. |
| `DEFERRED` | 0 | No Android/mobile source identity appears in this ledger. |

The ledger is the row-level traceability source. Every row now has a `work_package` key. Before and after each package, enumerate its exact pinned identities with:

```bash
jq -r '.rows[] | select(.work_package == "WP-01") | .source_id' docs/erpnext-parity/ledgers/manufacturing.json
```

Replace `WP-01` with the current package. A row may move to `COMPLETE` only after its implementation evidence names exact local files/functions and its focused test evidence names a freshly passing command. The four `N/A-TEST` rows remain `NOT_APPLICABLE`.

## File Map

**Module entry and contracts**

- Create `company/admin/modules/manufacturing/functions.php`: require module-local units and expose stable module entry points.
- Create `company/admin/modules/manufacturing/navigation.php`: section registry and section resolver.
- Create `company/admin/modules/manufacturing/dependencies.php`: allow-listed Inventory, Buying, and Finance gateway contracts; no dependency-table SQL.
- Create `company/admin/modules/manufacturing/schema.php`: idempotent company-scoped Manufacturing tables, keys, indexes, statuses, and audit tables.
- Create `company/admin/modules/manufacturing/repository.php`: parameterized reads and the shared module-local transaction/read-back boundary.
- Create `company/admin/modules/manufacturing/forms.php`: universal Form Builder adapter metadata, protected fields, stable keys, version binding, and server mappings.
- Create `company/admin/modules/manufacturing/submissions.php`: action allow-list, authorization, CSRF, validation, workflow dispatch, feedback, and rehydration.

**Domain units**

- Create `company/admin/modules/manufacturing/bom.php`: blanket/BOM structures, revisions, explosions, costing, and update operations.
- Create `company/admin/modules/manufacturing/capacity.php`: operations, routings, workstations, schedules, downtime, and plant-floor projections.
- Create `company/admin/modules/manufacturing/planning.php`: schedules, production plans, demand, MRP, forecasts, and planning notifications.
- Create `company/admin/modules/manufacturing/execution.php`: work orders, operations, job cards, time logs, and stock/cost handoff orchestration.
- Create `company/admin/modules/manufacturing/quality.php`: quality masters, non-conformance, actions, feedback, goals, meetings, procedures, and reviews.
- Create `company/admin/modules/manufacturing/subcontracting.php`: subcontract BOM, inward orders, orders, supplied items, and receipts.
- Create `company/admin/modules/manufacturing/reports.php`: allow-listed report definitions, filters, calculations, export payloads, and print payloads.

**Views**

- Create `company/admin/modules/manufacturing/views/workspace.php`: 12/8 module shell and section dispatch.
- Create `company/admin/modules/manufacturing/views/dashboard.php`: setup checklist, shortcuts, package status, and reports/masters directories.
- Create `company/admin/modules/manufacturing/views/records.php`: reusable list/detail surface with empty, filtered, populated, unauthorized, and archived states.
- Create `company/admin/modules/manufacturing/views/reports.php`: results-left and filters/tools-right report surface.
- Create `company/admin/modules/manufacturing/views/form-builder.php`: module adapter host for the universal Form Builder.

**Focused tests**

- Create `tests/manufacturing-test-helper.php`: isolated company/admin fixtures, fake dependency gateways, database cleanup, and assertions.
- Create `tests/manufacturing-workspace.php`: sections, 12/8 shell, setup hub, route payload, and state rendering.
- Create `tests/manufacturing-modal-confirmation.php`: Add-to-modal, validation, Submit-to-Confirm, cancel retention, one-shot confirm, and focus contracts.
- Create `tests/manufacturing-form-builder.php`: targets, protected fields, stable keys, draft/publish/archive/version history, and version-bound records.
- Create `tests/manufacturing-schema.php`: idempotency, keys, indexes, company isolation, and cleanup.
- Create `tests/manufacturing-persistence-contract.php`: authorization, CSRF, parameterization, create/update, rollback, audit, exact read-back, and rehydration.
- Create `tests/manufacturing-bom.php`, `tests/manufacturing-capacity.php`, `tests/manufacturing-planning.php`, `tests/manufacturing-execution.php`, `tests/manufacturing-quality.php`, and `tests/manufacturing-subcontracting.php`: domain lifecycle and calculation coverage.
- Create `tests/manufacturing-reports.php`: report filters, calculations, company isolation, export/print payloads, and row coverage.
- Create `tests/manufacturing-dependency-contracts.php`: fake-gateway interaction tests and assertions that no dependency-owned table is queried or mutated.

---

### Task 1: WP-01 Module Foundation And Interaction Contract

**Ledger selector:** `work_package == "WP-01"` (2 rows: Manufacturing Settings and Manufacturing workspace).

**Files:** Create `functions.php`, `navigation.php`, `dependencies.php`, `forms.php`, `submissions.php`, all five view files, `tests/manufacturing-workspace.php`, `tests/manufacturing-modal-confirmation.php`, and `tests/manufacturing-form-builder.php`.

**Interfaces:**

- Produce `yovel_admin_manufacturing_sections(): array` with stable keys `dashboard`, `boms`, `operations`, `workstations`, `production-plans`, `material-requirements`, `forecasts`, `work-orders`, `job-cards`, `quality`, `subcontracting`, `reports`, `settings`, and `form-builder`.
- Produce `yovel_admin_manufacturing_section(): string`, defaulting to `dashboard` for an unknown section.
- Produce `yovel_admin_manufacturing_workspace_data(string $section): array` with `section`, `records`, `summary`, `filters`, `form_schema`, `feedback`, and `rehydration` keys.
- Produce `yovel_admin_manufacturing_form_targets(): array` and `yovel_admin_manufacturing_default_form_schemas(): array`; protected keys include company identity, stable record key, lifecycle status, version key, and audit identity.
- Consume orchestration-owned shared modal, confirmation, and Form Builder contracts only through their published API/markup hooks.

- [ ] **Step 1: Write failing workspace, modal, and Form Builder tests.** Assert the exact section order, 12/8 data hooks, main-first DOM order, setup/shortcuts/reports directories, accessible record-modal title/description, sibling confirmation dialog, no request before Confirm, cancel/escape value retention, one request after Confirm, focus restoration, stable field keys, protected fields, immutable published versions, and server-backed rehydration.
- [ ] **Step 2: Run the failing tests.**

```bash
php tests/manufacturing-workspace.php
php tests/manufacturing-modal-confirmation.php
php tests/manufacturing-form-builder.php
```

Expected before implementation: each exits non-zero because the Manufacturing entry points and views do not exist.

- [ ] **Step 3: Create the module entry, navigation, dependency interfaces, and view dispatch.** Keep all copy BuilderX-specific, use one quiet operational setup hub, render the left main panel before the right tools panel, and omit or disable destinations that are not yet implemented.
- [ ] **Step 4: Implement the universal Form Builder adapter.** Define valid targets and field mappings per record type; support rows, columns, sections, field types, options, required/visible/width/default/validation settings, preview, draft, publish, archive, version history, stable keys, and immutable published versions.
- [ ] **Step 5: Wire module markup to shared modal and confirmation hooks.** Every create trigger uses the body-owned record modal; valid Submit opens the separate confirmation layer; Cancel restores the populated form; Confirm replays `requestSubmit()` exactly once; server errors reopen from the returned payload.
- [ ] **Step 6: Rerun the three tests and lint every new PHP file.** Record exact file/function and passing command evidence on the two WP-01 rows only.
- [ ] **Step 7: Commit the independently green foundation.** Stage only Manufacturing module files, `tests/manufacturing-*`, and the Manufacturing ledger.

### Task 2: Persistence And Dependency Safety Foundation

**Files:** Create `schema.php`, `repository.php`, `tests/manufacturing-test-helper.php`, `tests/manufacturing-schema.php`, `tests/manufacturing-persistence-contract.php`, and `tests/manufacturing-dependency-contracts.php`; modify `functions.php` and `submissions.php`.

**Interfaces:**

- Produce `yovel_admin_manufacturing_schema(): void` using fixed `project_company_manufacturing_*` identifiers and idempotent DDL.
- Produce `yovel_admin_manufacturing_with_transaction(string $companyKey, string $recordType, string $auditAction, callable $mutation, callable $readBack): array`.
- Produce `yovel_admin_manufacturing_dependency_gateway(array $overrides = []): array` with allow-listed callable keys for item lookup, stock snapshot/reservation/issue/receipt, material request, supplier lookup, account validation, cost preview, and posting request.
- Produce `yovel_admin_manufacturing_handle_submission(string $action): array`, rejecting every action outside the module allow-list before transaction start.

- [ ] **Step 1: Write failing schema and persistence-contract tests.** Cover two companies using the same business code, create and update preserving the stable key, invalid CSRF, unauthorized company, missing fields, overlong input, failed dependency call, failed ADODB write, failed exact read-back, rollback of domain plus audit rows, and post-redirect rehydration.
- [ ] **Step 2: Write failing dependency tests.** Use fakes to assert exact gateway payloads and add a source scan that rejects SQL references to Inventory, Buying, or Finance table prefixes from Manufacturing PHP files.
- [ ] **Step 3: Run all four foundation tests and confirm the expected failures.**
- [ ] **Step 4: Implement idempotent schemas.** Every table has a stable UUID key, `company_key`, `company_key_hash`, status, creator/updater, timestamps, scoped unique business key, lifecycle indexes, and no cross-module foreign key to dependency-owned tables.
- [ ] **Step 5: Implement the public transaction boundary.** Authorize and validate before `BeginTrans()`, use parameterized `Execute`/`GetRow`/`GetAll`/`GetOne`, check every write for `false`, write the audit event in the same transaction, compare stable key/business key/every persisted field in direct read-back, `CommitTrans()` only after equality, and `RollbackTrans()` on every failure.
- [ ] **Step 6: Implement server feedback and rehydration.** A committed result yields one accessible success status after reload; a failure yields one persistent error dialog and returns valid submitted values plus field/form errors.
- [ ] **Step 7: Run the foundation test set twice, including fixture cleanup on the second run, then commit only the green persistence foundation.**

### Task 3: WP-02 BOM Authoring, Revision, Explosion, And Costing

**Ledger selector:** `work_package == "WP-02"` (21 rows; the excluded BOM test-record fixture is `N/A-TEST`).

**Files:** Create `bom.php` and `tests/manufacturing-bom.php`; modify `schema.php`, `forms.php`, `submissions.php`, `repository.php`, `views/records.php`, and the ledger.

**Interfaces:**

- Produce `yovel_admin_manufacturing_save_bom(array $context, array $input): array`, `yovel_admin_manufacturing_submit_bom(...)`, `yovel_admin_manufacturing_cancel_bom(...)`, `yovel_admin_manufacturing_amend_bom(...)`, `yovel_admin_manufacturing_explode_bom(...)`, and `yovel_admin_manufacturing_update_bom_cost(...)`.
- Persist BOM header, components, operations, secondary items, revision/version identity, update batch/log, and audit rows in one public workflow transaction.
- Resolve item identity and stock/valuation inputs only through Inventory and validate cost-account references only through Finance.

- [ ] **Step 1: Write failing tests for nested and alternative BOMs, scrap/process loss, operations, quantities/UOM, duplicate components, cycles, effective dates, revision immutability, create/update, submit/cancel/amend, recursive explosion, and rolled-up material/operation cost.**
- [ ] **Step 2: Run `php tests/manufacturing-bom.php` and confirm it fails at missing BOM interfaces.**
- [ ] **Step 3: Add the BOM schema and Form Builder targets.** Protect stable key, item key, revision, lifecycle status, quantity/UOM, currency, and submitted-version identity from destructive customization.
- [ ] **Step 4: Implement BOM validation and lifecycle.** Reject self-reference/cycles, unavailable item keys, non-positive quantities, duplicate child identities, and edits to submitted revisions; amendment creates a new revision linked to the cancelled source.
- [ ] **Step 5: Implement explosion and cost roll-up with deterministic rounding.** Use only gateway-supplied valuation and operation rates; store calculation inputs and result audit data so later reports are reproducible.
- [ ] **Step 6: Verify authorized create/update and each lifecycle action through Submit-to-Confirm, rollback, exact read-back, server reload, and company isolation.** Keep WP-02 report/page rows `MISSING` until Task 4.
- [ ] **Step 7: Commit the green BOM core without dependency-module edits.**

### Task 4: WP-02 BOM Tools And Reports

**Files:** Modify `bom.php`, `reports.php`, `views/reports.php`, `views/records.php`, `tests/manufacturing-bom.php`, `tests/manufacturing-reports.php`, and the ledger.

**Interfaces:**

- Produce `yovel_admin_manufacturing_compare_boms(string $companyKey, string $leftBomKey, string $rightBomKey): array`.
- Produce allow-listed report keys `bom-explorer`, `bom-operations-time`, `bom-stock-analysis`, and `bom-variance` through `yovel_admin_manufacturing_run_report(string $reportKey, array $filters): array`.

- [ ] **Step 1: Add failing comparison/report tests.** Cover recursive hierarchy, changed/added/removed components, operation-time totals, current stock from Inventory gateways, planned-versus-actual variance, filters, empty state, pagination, export shape, company isolation, and no report-time writes.
- [ ] **Step 2: Run the BOM and report tests and verify the new assertions fail.**
- [ ] **Step 3: Implement comparison and read-only reports with parameterized filters and stable column metadata.** The result-left/filter-right view must expose print/export commands through confirmation where the action is consequential.
- [ ] **Step 4: Run both test files and lint touched PHP.** When all 21 WP-02 identities have exact implementation and passing evidence, mark those rows `COMPLETE`; leave the upstream test fixture `NOT_APPLICABLE`.
- [ ] **Step 5: Commit the completed WP-02 package.**

### Task 5: WP-03 Operations, Routings, Workstations, And Capacity

**Ledger selector:** `work_package == "WP-03"` (14 rows).

**Files:** Create `capacity.php` and `tests/manufacturing-capacity.php`; modify `schema.php`, `forms.php`, `submissions.php`, `repository.php`, `reports.php`, `views/records.php`, `views/reports.php`, and the ledger.

**Interfaces:**

- Produce save/lifecycle functions for Operation, Routing, Workstation Type, Workstation, working hours, costs, operating components, Plant Floor, and Downtime Entry.
- Produce `yovel_admin_manufacturing_schedule_capacity(string $companyKey, array $operations, DateTimeImmutable $start): array` and report key `downtime-analysis`.

- [ ] **Step 1: Write failing tests for operation/sub-operation ordering, routing reuse, workstation calendars, holiday and overlap rejection, capacity scheduling, operating cost/account validation, downtime intervals, utilization, plant-floor state, and downtime analysis.**
- [ ] **Step 2: Run `php tests/manufacturing-capacity.php` and confirm the missing-interface failures.**
- [ ] **Step 3: Implement company-scoped masters and schedules with modal-confirmed writes, exact read-back, and immutable references once used by a submitted BOM or work order.**
- [ ] **Step 4: Implement deterministic capacity allocation and read-only visual plant-floor projection.** Avoid decorative-only output: every station state must derive from persisted schedule/job-card/downtime data.
- [ ] **Step 5: Run capacity, persistence, modal, and report tests.** Mark all 14 rows `COMPLETE` only with exact evidence, then commit WP-03.

### Task 6: WP-04 Demand, Production Planning, MRP, And Forecasting

**Ledger selector:** `work_package == "WP-04"` (25 rows).

**Files:** Create `planning.php` and `tests/manufacturing-planning.php`; modify `schema.php`, `forms.php`, `submissions.php`, `repository.php`, `reports.php`, related views, dependency tests, and the ledger.

**Interfaces:**

- Produce lifecycle functions for Blanket Order, Master Production Schedule, Production Plan, Material Requirement, and Sales Forecast records.
- Produce `yovel_admin_manufacturing_calculate_mrp(string $companyKey, array $demand, DateTimeImmutable $asOf): array` and `yovel_admin_manufacturing_forecast(string $companyKey, array $series, float $alpha): array`.
- Produce report keys `material-requirements`, `exponential-smoothing-forecast`, `production-analytics`, `production-plan-summary`, and `production-planning`.

- [ ] **Step 1: Write failing planning tests.** Cover blanket quantities/date windows, MPS periods, production-plan item references and subassemblies, BOM explosion into gross requirements, on-hand/reserved/projected stock, safety stock, reorder/lead time, netting, warehouse allocation, material-request payloads, shortage states, exponential smoothing, filters, and reproducible report totals.
- [ ] **Step 2: Run planning and dependency-contract tests and confirm failures.**
- [ ] **Step 3: Implement planning persistence and lifecycle.** Submission freezes the source snapshot and form-schema version; cancellation/amendment is explicit and audited.
- [ ] **Step 4: Implement MRP as a pure calculation over persisted Manufacturing demand plus Inventory snapshots.** Buying material requests are emitted through the Buying gateway only after confirmed plan submission; failed handoff rolls back the Manufacturing workflow.
- [ ] **Step 5: Implement forecasting and planning reports.** Finance values come from Finance gateway responses and never from Finance table reads.
- [ ] **Step 6: Emit the material-receipt notification event through an orchestration-owned notification boundary.** Record a shared-integration request if that boundary is unavailable; do not edit Operations or shared files.
- [ ] **Step 7: Run planning, persistence, modal, dependency, and report tests.** Close all 25 rows only when gateway and report evidence passes, then commit WP-04.

### Task 7: WP-05 Work Orders, Job Cards, And Material Handoffs

**Ledger selector:** `work_package == "WP-05"` (22 rows).

**Files:** Create `execution.php` and `tests/manufacturing-execution.php`; modify `schema.php`, `forms.php`, `submissions.php`, `repository.php`, `reports.php`, relevant views, dependency tests, and the ledger.

**Interfaces:**

- Produce work-order actions `save`, `submit`, `start`, `stop`, `complete`, `cancel`, and `amend` with allowed transitions.
- Produce job-card actions `save`, `start`, `pause`, `resume`, `complete`, and `cancel`; enforce one active time interval per worker/workstation and quantity limits.
- Produce Inventory gateway commands for reservation, material issue/transfer, finished-goods receipt, scrap/secondary item receipt, and reversal; produce Finance posting-request payloads after verified stock handoffs.
- Produce report keys `completed-work-orders`, `issued-items`, `job-card-summary`, `open-work-orders`, `consumed-materials`, `work-order-stock`, `work-order-summary`, and `work-orders-in-progress`.

- [ ] **Step 1: Write failing execution tests.** Cover BOM-derived quantities, additional items, operation sequence, capacity dates, overproduction limits, transferred/consumed/produced quantities, job-card timers, downtime interaction, partial completion, status derivation, cancel/reversal, concurrency conflict, gateway idempotency keys, and all eight report families.
- [ ] **Step 2: Run execution, dependency, and report tests and confirm failures.**
- [ ] **Step 3: Implement work-order and job-card schemas, forms, and lifecycles.** Lock controlled rows during transitions; reject stale versions and impossible transitions before mutation.
- [ ] **Step 4: Implement atomic orchestration of module records, Inventory handoffs, audit events, exact read-back, and Finance posting requests.** Never emulate stock or ledger movements in Manufacturing tables; retain only stable external keys and immutable request/result snapshots.
- [ ] **Step 5: Implement execution reports from Manufacturing records plus read-only dependency service results.** Apply company scope and parameterized filters to every query.
- [ ] **Step 6: Run execution, persistence, modal, dependency, capacity, and report suites.** Mark all 22 rows `COMPLETE` only with exact evidence, then commit WP-05.

### Task 8: WP-06 Quality Management And Handoff

**Ledger selector:** `work_package == "WP-06"` (24 rows).

**Files:** Create `quality.php` and `tests/manufacturing-quality.php`; modify `schema.php`, `forms.php`, `submissions.php`, `repository.php`, `reports.php`, relevant views, dependency tests, and the ledger.

**Interfaces:**

- Produce company-scoped lifecycle functions for Non Conformance, Quality Action/Resolution, Feedback/Template/Parameters, Goal/Objectives, Meeting/Agenda/Minutes, Procedure/Process, and Review/Objectives.
- Produce quality handoff requests linked by stable keys to Inventory receipts/issues, Buying receipts/suppliers, and Manufacturing work orders/job cards.
- Produce report keys `cost-of-poor-quality`, `process-loss`, `quality-inspection-summary`, and `quality-review`.

- [ ] **Step 1: Write failing quality tests.** Cover source linkage, severity/status, owner and due date, corrective/preventive action, resolution approval/rejection, template-driven feedback, objective measurement, meeting minutes, procedure versioning, review scoring, quality hold/release, process-loss quantities, poor-quality cost inputs, report filtering, and company isolation.
- [ ] **Step 2: Run quality, dependency, persistence, and report tests and confirm failures.**
- [ ] **Step 3: Implement quality records and explicit lifecycles.** Published procedures/templates and approved resolutions are immutable; revisions create linked versions. Holds/releases call owning-module services and are auditable.
- [ ] **Step 4: Implement the Quality workspace and 12/8 record/report surfaces using the same module shell and modal-confirmation contract.**
- [ ] **Step 5: Implement quality reports using verified Manufacturing facts plus dependency service results.** Finance supplies cost values; Inventory/Buying supply inspection/source references.
- [ ] **Step 6: Run the full WP-06 focused matrix, close all 24 rows with exact evidence, and commit WP-06.**

### Task 9: WP-07 Subcontracting Lifecycles

**Ledger selector:** `work_package == "WP-07"` (14 rows).

**Files:** Create `subcontracting.php` and `tests/manufacturing-subcontracting.php`; modify `schema.php`, `forms.php`, `submissions.php`, `repository.php`, `reports.php`, relevant views, dependency tests, and the ledger.

**Interfaces:**

- Produce lifecycle functions for Subcontracting BOM, Inward Order and child rows, Subcontracting Order and supplied/service items, and Subcontracting Receipt and supplied items.
- Produce dependency payloads for supplier/order validation through Buying, raw-material issue/return and finished-item receipt through Inventory, and valuation/posting requests through Finance.

- [ ] **Step 1: Write failing subcontracting tests.** Cover supplier and item validation, service and supplied-item quantities, warehouse/source links, outward issue, inward customer material, partial receipt, excess/shortage rejection, returned material, serial/batch reference preservation, completion, cancellation/reversal, idempotent gateway calls, valuation snapshots, and company isolation.
- [ ] **Step 2: Run subcontracting and dependency-contract tests and confirm failures.**
- [ ] **Step 3: Implement records, child rows, modal forms, and DRAFT/SUBMITTED/PARTIALLY_RECEIVED/COMPLETED/CANCELLED lifecycles with transaction-owned audit/read-back.**
- [ ] **Step 4: Implement external handoffs only through dependency gateways.** Any missing Buying, Inventory, or Finance production adapter is an orchestrator blocker; retain passing fake-contract tests and do not modify dependency modules.
- [ ] **Step 5: Implement the Subcontracting workspace and operational summaries.** The left panel owns records/detail; the right panel owns state, actions, linked dependency keys, activity, and Form Builder tools.
- [ ] **Step 6: Run subcontracting, persistence, modal, dependency, and workspace tests.** Close all 14 rows with exact evidence, then commit WP-07.

### Task 10: Module Verification, Shared Integration Request, And Ledger Closure

**Files:** Modify only Manufacturing module files, `tests/manufacturing-*`, and `docs/erpnext-parity/ledgers/manufacturing.json`. Shared integration changes are requested from the orchestrator, not made by this task.

- [ ] **Step 1: Validate row closure and evidence.** Require exactly 122 `COMPLETE`, 4 `NOT_APPLICABLE`, and zero `MISSING`, `PARTIAL`, or `DEFERRED`. Every `COMPLETE` row must name exact module file/function evidence and at least one freshly passing focused test command.

```bash
jq '.rows | group_by(.status) | map({status: .[0].status, count: length})' docs/erpnext-parity/ledgers/manufacturing.json
php tools/erpnext-parity.php validate-ledger
php tests/erpnext-parity-ledger.php
```

- [ ] **Step 2: Run the complete focused suite.**

```bash
for test_file in tests/manufacturing-*.php; do php "$test_file" || exit 1; done
find company/admin/modules/manufacturing -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check -- company/admin/modules/manufacturing tests docs/erpnext-parity/ledgers/manufacturing.json
```

Expected: every command exits `0`, all test fixtures are removed, and a second complete run is also green.

- [ ] **Step 3: Submit one bounded orchestration request.** Request shared registry/loader/controller/layout wiring for the Manufacturing route, production dependency adapters, shared notification dispatch, and authenticated live-route/browser coverage. Include exact module entry points and passing module-local tests; do not edit shared files.
- [ ] **Step 4: After orchestration wiring, run live desktop and mobile checks.** Verify non-500 routes, 12/8 desktop layout, main-first mobile stacking, no horizontal overflow, accessible modal focus, valid Submit-to-Confirm, no mutation before Confirm, one mutation after Confirm, retained values on Cancel/error, server-backed refresh, report filters/export, and dependency handoff errors.
- [ ] **Step 5: Run required regressions selected by the orchestrator for shared routing, Inventory, Buying, Finance, HR, and existing company-admin behavior.** Manufacturing does not alter those suites; any regression is returned to the owning task.
- [ ] **Step 6: Commit parity closure only after module-local and orchestration-owned integration evidence are both fresh.** Stage no unrelated working-tree changes.

## Highest-Risk Gaps

1. Inventory, Buying, and Finance production service contracts do not yet exist in the audited Manufacturing scope. Work orders, MRP, subcontracting, costing, and quality handoffs cannot be accepted with fake-only adapters.
2. The entire Manufacturing runtime and test surface is absent, so schema/lifecycle choices have no backward-compatible implementation anchor; stable keys and transaction boundaries must be fixed early.
3. Work-order and subcontracting flows span stock movement and financial posting. Idempotency, rollback ownership, cancellation/reversal, and stale/concurrent submissions are the dominant correctness risks.
4. BOM recursion, revision immutability, capacity scheduling, MRP netting, and forecast/report reproducibility require calculation-focused tests before UI completion claims.
5. Shared route, modal, confirmation, Form Builder, notification, and browser integration remain orchestration-owned. Module-local success is necessary but not sufficient for route-level parity.

## Proposed Sequence

Execute WP-01 and the persistence foundation first; then WP-02 BOM and WP-03 capacity because planning and execution consume both. Implement WP-04 planning before WP-05 execution, then WP-06 quality and WP-07 subcontracting after the Inventory/Buying/Finance adapters are available. Finish with the focused verification matrix, a bounded shared-integration request, live browser checks, dependency regressions, and evidence-backed ledger closure.
