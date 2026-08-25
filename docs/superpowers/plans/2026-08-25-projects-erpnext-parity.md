# Projects ERPNext Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement only the 27 evidence-backed Projects parity gaps from the pinned ERPNext baseline, while keeping HR timesheets and assignments, Finance billing, and every other module's authoritative data behind owner-provided service boundaries.

**Architecture:** Build a company-scoped Projects module under `company/admin/modules/projects/` with focused schema, service, Form Builder, workspace, collaboration, time-integration, portal, and report files. Projects owns project records and calculations; it consumes Customer, Employee, assignment, timesheet, billing, stock, purchasing, delivery, notification, and portal data through explicit owner services and never writes another module's tables.

**Tech Stack:** PHP 8.5 runtime with PHP 8.1 compatibility, ADODB/MySQL, server-rendered PHP, existing BuilderX shadcn-style controls and tokens, vanilla JavaScript supplied by the orchestration-owned shared layer, and executable PHP tests.

## Global Constraints

- Baseline: ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325` and Frappe HR `version-16` commit `f281e8b172ac8836ad89c59df65a922101103097`.
- Preserve all existing completed behavior and all user or other-task changes.
- Edit only `company/admin/modules/projects/`, `tests/projects-*`, and the Projects ledger during implementation.
- Do not edit `app/foundation.php`, `company/admin/bootstrap/`, `company/admin/core/`, `company/admin/views/layout.php`, `company/admin/views/partials/`, `company/admin/assets/`, `company/admin/modules/shared/`, another module, or another module's tests or docs.
- Use a universal module-local Form Builder adapter with protected stable field keys, rows, columns, sections, preview, draft, publish, archive, immutable published versions, and version-bound submitted records.
- Use a 20-column workspace: left main panel `12/20`, right action/tools panel `8/20`; stack main-first on smaller screens.
- Every Add, New, Create, or Insert command opens an accessible modal.
- A valid Submit opens a separate confirmation dialog. Persistence starts only after Confirm; Cancel returns to the populated form; Confirm submits exactly once.
- Every write validates administrator authorization, company scope, CSRF, required values, lengths, stable external keys, and lifecycle state before beginning one owner-controlled ADODB transaction.
- Every write uses parameterized SQL and fixed identifiers, checks every write result, writes the audit event inside the same transaction, reads back every persisted field and stable key, commits only after exact verification, rolls back on failure, and rehydrates server values.
- Module-local backward-compatible improvements are permitted with focused tests. Shared or cross-module improvements must be escalated to orchestration.
- Implement functional behavior without copying upstream GPL source, names, logos, images, or product branding.
- Android and mobile application work remains deferred; responsive web behavior remains required.

## Audit Baseline

The 2026-08-25 audit found that `company/admin/modules/projects/` does not exist and no `tests/projects-*` files exist. The baseline and ledger validators pass, but they validate source identity and ledger shape, not Projects behavior. Therefore the ledger contains `0 COMPLETE`, `0 PARTIAL`, `27 MISSING`, `0 NOT_APPLICABLE`, and `0 DEFERRED` rows.

No package below may change a row to `COMPLETE` until the listed local implementation files exist and the focused test command for that package passes in the implementation worktree.

## Required Owner Contracts

These contracts are prerequisites, not Projects-owned implementation work. If a callable is unavailable, stop that dependent package and send the exact contract request to orchestration; do not query or update the owner's tables directly.

| Owner | Required service boundary | Projects use |
|---|---|---|
| Sales/CRM | `yovel_admin_sales_customer_snapshot(array $company, string $customerKey): ?array` and `yovel_admin_sales_order_snapshot(array $company, string $salesOrderKey): ?array` | Validate and display stable customer and sales-order references. |
| HR | `yovel_admin_hr_employee_snapshot(array $company, string $employeeKey): ?array`, `yovel_admin_hr_project_timesheets(array $company, array $filters): array`, and `yovel_admin_hr_assign_project_task(array $company, array $admin, array $assignment): array` | Employee validation, read-only timesheet projections, and HR-owned task assignments. |
| Finance | `yovel_admin_finance_project_billing_snapshot(array $company, string $projectKey, array $filters = []): array` and `yovel_admin_finance_create_project_invoice_draft(array $company, array $admin, array $request): array` | Read billing state and request a Finance-owned invoice draft after confirmation. |
| Buying | `yovel_admin_buying_project_receipt_costs(array $company, array $projectKeys): array` | Read submitted purchase-receipt costs for stock tracking. |
| Inventory | `yovel_admin_inventory_project_stock_costs(array $company, array $projectKeys): array` | Read submitted material issue and receipt costs. |
| Sales/CRM | `yovel_admin_sales_project_delivery_values(array $company, array $projectKeys): array` | Read submitted delivery values. |
| Operations | `yovel_admin_operations_user_snapshot(array $company, string $userKey): ?array`, `yovel_admin_operations_notify(array $company, array $admin, array $event): array`, and `yovel_admin_operations_sync_portal_access(array $company, array $admin, array $grant): array` | Validate users, send update/reminder events, and synchronize project portal grants. |
| Orchestration | Shared registry, record-modal, confirmation-dialog, Form Builder adapter, and route wiring from the approved orchestration plan | Make the module reachable and enforce shared interaction contracts without Projects editing shared paths. |

Owner services that join a Projects transaction must accept an orchestration-approved transaction context. Until that contract exists, Projects calls owner services only outside its write transaction and stores verified stable references or returned snapshots, never half-completed cross-module state.

## Ledger Traceability

| Package | Pinned ledger rows | Count |
|---|---|---:|
| Workspace, settings, and Form Builder | `Projects Settings`, `Projects` workspace | 2 |
| Project masters and templates | `Project`, `Project Type`, `Project Template`, `Project Template Task` | 4 |
| Task graph and assignments | `Task`, `Task Type`, `Task Depends On`, `Dependent Task` | 4 |
| Collaboration and portal | `Project Update`, `Project User` | 2 |
| Activity and HR time integration | `Activity Type`, `Activity Cost`, `Timesheet`, `Timesheet Detail` | 4 |
| Core project reports | `Delayed Tasks Summary` report, code, and test; `Project Summary` report and code | 5 |
| Owner-composed reports | `Daily Timesheet Summary` report and code; `Timesheet Billing Summary` report and code; `Project wise Stock Tracking` report and code | 6 |
| **Total** | All Projects ledger rows | **27** |

---

### Task 1: Establish The Projects Schema And Module Boundary

**Files:**
- Create: `company/admin/modules/projects/functions.php`
- Create: `company/admin/modules/projects/schema.php`
- Create: `tests/projects-test-helper.php`
- Create: `tests/projects-schema.php`

**Interfaces:**
- Produces: `yovel_admin_projects_sections(): array`, `yovel_admin_projects_schema(): void`, `yovel_admin_projects_scope(array $company, array $admin): array`, `yovel_admin_projects_data(array $company, array $admin, string $section): array`, and `yovel_admin_projects_handle_post(array $company, array $admin): array`.
- Produces test helpers: `projects_assert(bool $condition, string $message): void`, `projects_test_company(): array`, `projects_test_admin(): array`, and `projects_cleanup(array $company): void`.
- Creates company-scoped tables prefixed `project_company_project_`; it creates no Timesheet, Employee, assignment, Sales Invoice, Customer, stock, purchase, delivery, notification, or portal-authority table.

- [ ] **Step 1: Write the failing schema and boundary test**

Assert the four module entry functions exist; schema setup is idempotent; every Projects table contains `company_key` and `company_key_hash`; stable record keys are unique within a company; and no table name claims HR, Finance, Sales, Buying, Inventory, Operations, or Support authority.

Expected owned tables:

```php
$expected = [
    'project_company_project_settings',
    'project_company_project_type',
    'project_company_project_task_type',
    'project_company_project_activity_type',
    'project_company_project_activity_cost',
    'project_company_project_record',
    'project_company_project_template',
    'project_company_project_template_task',
    'project_company_project_task',
    'project_company_project_task_dependency',
    'project_company_project_user',
    'project_company_project_update',
    'project_company_project_form_schema',
];
```

- [ ] **Step 2: Run the test and verify the missing-module failure**

Run: `php tests/projects-schema.php`

Expected: non-zero exit because the Projects module files and tables do not exist.

- [ ] **Step 3: Implement idempotent schema and shared module helpers**

Use `bx_db()` and fixed `CREATE TABLE IF NOT EXISTS` statements. Define explicit primary keys, company indexes, business-key uniqueness, lifecycle status constraints represented by allow-lists in PHP, ownership keys, created/updated actor keys, and timestamps. Add a small `yovel_admin_projects_db_execute()` wrapper that throws immediately with a safe message when ADODB `Execute()` returns `false`.

`yovel_admin_projects_scope()` returns:

```php
[
    'company_key' => (string) $company['company_key'],
    'company_key_hash' => hash('sha256', (string) $company['company_key']),
    'admin_key' => (string) $admin['admin_key'],
]
```

It rejects unauthenticated administrators and missing company keys before any transaction.

- [ ] **Step 4: Run the schema test twice**

Run: `php tests/projects-schema.php && php tests/projects-schema.php`

Expected: both runs pass and fixture cleanup leaves no Projects rows.

- [ ] **Step 5: Commit the schema boundary**

```bash
git add company/admin/modules/projects/functions.php company/admin/modules/projects/schema.php tests/projects-test-helper.php tests/projects-schema.php
git commit -m "feat(projects): establish module schema boundary"
```

### Task 2: Build The Workspace, Settings, And Module-Local Form Builder

**Ledger rows:** `Projects Settings`; `Projects` workspace.

**Files:**
- Create: `company/admin/modules/projects/forms.php`
- Create: `company/admin/modules/projects/settings.php`
- Create: `company/admin/modules/projects/views/workspace.php`
- Create: `tests/projects-form-builder.php`
- Create: `tests/projects-workspace.php`

**Interfaces:**
- Produces: `yovel_admin_projects_builder_targets(): array`, `yovel_admin_projects_active_schema(array $company, string $target): array`, `yovel_admin_projects_publish_schema(array $company, array $admin, string $target, array $schema): array`, `yovel_admin_projects_settings(array $company): array`, and `yovel_admin_projects_save_settings(array $company, array $admin, array $input): array`.
- Builder targets: `project`, `project_template`, `task`, `project_update`, `activity_type`, and `activity_cost`.
- Consumes orchestration-owned modal, confirmation, and shared Form Builder contracts; Projects supplies only its local adapter metadata and persistence.

- [ ] **Step 1: Write failing builder and workspace tests**

Assert every target exposes protected stable keys, supported types, sections, rows, columns, width, visibility, required state, defaults, options, and validation. Assert draft, publish, archive, and version history are company-scoped; published versions are immutable; and records retain the schema version used at submission.

Render the workspace and assert:

```php
projects_assert(str_contains($html, 'data-projects-main-panel'), 'Projects main panel is missing.');
projects_assert(str_contains($html, 'data-projects-tools-panel'), 'Projects tools panel is missing.');
projects_assert(str_contains($html, 'xl:col-span-12'), 'Projects main panel is not 12/20.');
projects_assert(str_contains($html, 'xl:col-span-8'), 'Projects tools panel is not 8/20.');
projects_assert(str_contains($html, 'data-record-modal'), 'Create actions do not target the shared record modal.');
```

Also assert no nested bordered cards, no inline create form, no mutation before confirmation, and main-first source order for responsive stacking.

- [ ] **Step 2: Run the tests and verify missing interfaces**

Run: `php tests/projects-form-builder.php && php tests/projects-workspace.php`

Expected: non-zero exit at the missing builder and workspace functions.

- [ ] **Step 3: Implement transactional Form Builder and Projects Settings persistence**

Store normalized JSON plus monotonic version, status `DRAFT|PUBLISHED|ARCHIVED`, schema hash, actor, and timestamps. On publish, lock the target's latest version, insert a new immutable version, write `bx_audit('PUBLISH', ...)`, read back target/version/status/hash/schema exactly, then commit. Settings persist `ignore_employee_time_overlap` and `fetch_timesheet_in_sales_invoice` as Projects-side policy flags; HR and Finance decide how their authoritative workflows consume those flags.

- [ ] **Step 4: Implement the workspace shell and modal triggers**

Render real section navigation, number cards backed by server arrays with explicit empty states, project/task/report surfaces, and a right-side tools panel. Every Add/New/Create/Insert trigger references the shared record modal. Every form keeps its Submit inside the form, and the shared confirmation controller remains the only path to mutation.

- [ ] **Step 5: Verify persistence and rendering**

Run: `php tests/projects-form-builder.php && php tests/projects-workspace.php && php tests/projects-schema.php`

Expected: all pass; create and update read back exact values; invalid CSRF and unauthorized requests write nothing; published schemas cannot be updated in place.

- [ ] **Step 6: Request shared route wiring from orchestration**

Request registration of the Projects route, function loader, workspace view, shared modal, confirmation guard, and Form Builder adapter. Do not edit the shared registry, bootstrap, layout, partials, assets, or shared module files.

- [ ] **Step 7: Commit the workspace slice**

```bash
git add company/admin/modules/projects/forms.php company/admin/modules/projects/settings.php company/admin/modules/projects/views/workspace.php tests/projects-form-builder.php tests/projects-workspace.php
git commit -m "feat(projects): add workspace settings and form builder"
```

### Task 3: Implement Project Masters And Template Instantiation

**Ledger rows:** `Project`; `Project Type`; `Project Template`; `Project Template Task`.

**Files:**
- Create: `company/admin/modules/projects/projects.php`
- Create: `company/admin/modules/projects/templates.php`
- Create: `tests/projects-projects.php`
- Create: `tests/projects-templates.php`

**Interfaces:**
- Produces: `yovel_admin_project_upsert(array $company, array $admin, array $input): array`, `yovel_admin_project_transition(array $company, array $admin, string $projectKey, string $action): array`, `yovel_admin_project_recalculate(array $company, array $admin, string $projectKey): array`, and `yovel_admin_project_create_from_template(array $company, array $admin, array $input): array`.
- Project statuses: `OPEN`, `ON_HOLD`, `COMPLETED`, `CANCELLED`.
- Completion methods: `MANUAL`, `TASK_COMPLETION`, `TASK_PROGRESS`, `TASK_WEIGHT`.

- [ ] **Step 1: Write failing project and template tests**

Cover create/update stable-key preservation, project type uniqueness, customer/employee external-key validation, date ordering, manual percentage bounds, automatic task completion/progress/weight calculations, on-hold/cancelled status preservation, completed percentage `100`, and company isolation.

For template creation, assert parent tasks, child tasks, relative dates, weights, and dependency edges are copied in one transaction and remapped to the new task keys. Force a dependency insert failure and assert the project, tasks, and audit event all roll back.

- [ ] **Step 2: Run tests and verify missing services**

Run: `php tests/projects-projects.php && php tests/projects-templates.php`

Expected: non-zero exit at missing project functions.

- [ ] **Step 3: Implement complete project and master upserts**

Use one create/update path per business key, fixed parameterized SQL, row locks for lifecycle transitions, owner-service validation for Customer and Employee references, audit events inside the transaction, and exact post-write comparison of every persisted field. Preserve project keys on update and reject cross-company keys.

- [ ] **Step 4: Implement template graph instantiation and project recalculation**

Create all generated task keys before inserting parent and dependency references. Recalculate completion and status under the same transaction as a task-affecting change. Cost and billing totals consume owner snapshots; they are never calculated by direct reads from Finance, HR, Buying, Inventory, or Sales tables.

- [ ] **Step 5: Verify project behavior**

Run: `php tests/projects-projects.php && php tests/projects-templates.php && php tests/projects-schema.php`

Expected: all pass, including rollback, isolation, read-back, and audit assertions.

- [ ] **Step 6: Commit project masters**

```bash
git add company/admin/modules/projects/projects.php company/admin/modules/projects/templates.php tests/projects-projects.php tests/projects-templates.php
git commit -m "feat(projects): implement project and template workflows"
```

### Task 4: Implement The Task Tree, Dependency Graph, And HR Assignment Handoff

**Ledger rows:** `Task`; `Task Type`; `Task Depends On`; `Dependent Task`.

**Files:**
- Create: `company/admin/modules/projects/tasks.php`
- Create: `tests/projects-tasks.php`
- Create: `tests/projects-assignments.php`

**Interfaces:**
- Produces: `yovel_admin_project_task_upsert(array $company, array $admin, array $input): array`, `yovel_admin_project_task_transition(array $company, array $admin, string $taskKey, string $action): array`, `yovel_admin_project_task_move(array $company, array $admin, string $taskKey, ?string $parentTaskKey): array`, and `yovel_admin_project_task_reschedule(array $company, array $admin, string $taskKey, string $startDate, string $endDate): array`.
- Task statuses: `OPEN`, `WORKING`, `PENDING_REVIEW`, `OVERDUE`, `TEMPLATE`, `COMPLETED`, `CANCELLED`.
- Priorities: `LOW`, `MEDIUM`, `HIGH`, `URGENT`.

- [ ] **Step 1: Write failing task graph tests**

Cover task CRUD, task type uniqueness, tree ordering, group-parent enforcement, project date bounds, progress `0..100`, completed timestamp rules, circular parent and dependency rejection, blocked completion until dependencies are completed or cancelled, dependent rescheduling, overdue calculation, and project recalculation after every task change.

- [ ] **Step 2: Write the failing assignment boundary test**

Inject an HR assignment service spy and assert Projects sends company key, task key, stable employee key, due date, priority, and actor. Assert Projects creates no assignment table, does not call HR before the task transaction commits, records the returned assignment key through a verified module-local reference update, and reports a failed handoff without claiming assignment success.

- [ ] **Step 3: Run the tests and verify missing task services**

Run: `php tests/projects-tasks.php && php tests/projects-assignments.php`

Expected: non-zero exit at missing task and assignment adapter functions.

- [ ] **Step 4: Implement task transactions and graph validation**

Lock the task, parent, dependency, and project rows in a deterministic key order. Reject cycles before writes. Insert/update the task and dependency set, read back the header and ordered dependency keys, recalculate the project, write the audit event, then commit. Complete/cancel/archive actions use the same confirmation and lifecycle boundary.

- [ ] **Step 5: Implement post-commit HR assignment handoff**

Call only `yovel_admin_hr_assign_project_task()`. Store the returned stable assignment key in the task's module-local external reference through a second audited Projects transaction with exact read-back. A failed HR call leaves the task saved but unassigned, returns a persistent failure state, and supports an idempotent retry keyed by company plus task plus employee.

- [ ] **Step 6: Verify task and assignment behavior**

Run: `php tests/projects-tasks.php && php tests/projects-assignments.php && php tests/projects-projects.php`

Expected: all pass; no HR table appears in the Projects schema.

- [ ] **Step 7: Commit task workflows**

```bash
git add company/admin/modules/projects/tasks.php tests/projects-tasks.php tests/projects-assignments.php
git commit -m "feat(projects): add task graph and assignment handoff"
```

### Task 5: Implement Project Collaboration And Portal Access

**Ledger rows:** `Project Update`; `Project User`.

**Files:**
- Create: `company/admin/modules/projects/collaboration.php`
- Create: `company/admin/modules/projects/portal.php`
- Create: `tests/projects-collaboration.php`
- Create: `tests/projects-portal.php`

**Interfaces:**
- Produces: `yovel_admin_project_users_replace(array $company, array $admin, string $projectKey, array $userKeys): array`, `yovel_admin_project_update_save_draft(array $company, array $admin, array $input): array`, `yovel_admin_project_update_transition(array $company, array $admin, string $updateKey, string $action): array`, and `yovel_admin_projects_portal_snapshot(array $company, string $portalUserKey): array`.
- Update statuses: `DRAFT`, `SUBMITTED`, `CANCELLED`, with amendment by a new stable key linked to the cancelled update.

- [ ] **Step 1: Write failing collaboration tests**

Cover project-user add/remove diffing, duplicate rejection, company isolation, draft update edits, submit/cancel/amend rules, immutable submitted content, recipient snapshots, transaction rollback, audit evidence, and server rehydration after validation failure.

- [ ] **Step 2: Write failing portal permission tests**

Assert a portal user sees only explicitly granted projects, permitted tasks, submitted updates, and safe summary fields. Confirm revoked users lose access, unrelated customers cannot infer record existence, and project users cannot invoke administrator mutations.

- [ ] **Step 3: Run the tests and verify missing collaboration services**

Run: `php tests/projects-collaboration.php && php tests/projects-portal.php`

Expected: non-zero exit at missing collaboration and portal functions.

- [ ] **Step 4: Implement module-local membership and updates**

Persist memberships and update documents transactionally. After commit, request Operations portal grant/revoke and notification synchronization with idempotency keys. Never write Operations users, access-control, email, or notification tables. Record failed external synchronization as a retryable module-local state without reviving revoked local access.

- [ ] **Step 5: Verify collaboration and portal isolation**

Run: `php tests/projects-collaboration.php && php tests/projects-portal.php && php tests/projects-projects.php`

Expected: all pass with exact read-back and no cross-company leakage.

- [ ] **Step 6: Commit collaboration workflows**

```bash
git add company/admin/modules/projects/collaboration.php company/admin/modules/projects/portal.php tests/projects-collaboration.php tests/projects-portal.php
git commit -m "feat(projects): add collaboration and portal projections"
```

### Task 6: Implement Activity Masters And Read-Only HR Timesheet Integration

**Ledger rows:** `Activity Type`; `Activity Cost`; `Timesheet`; `Timesheet Detail`.

**Files:**
- Create: `company/admin/modules/projects/time.php`
- Create: `tests/projects-time.php`
- Create: `tests/projects-timesheet-integration.php`

**Interfaces:**
- Produces: `yovel_admin_projects_activity_type_upsert(array $company, array $admin, array $input): array`, `yovel_admin_projects_activity_cost_upsert(array $company, array $admin, array $input): array`, `yovel_admin_projects_timesheets(array $company, array $filters): array`, and `yovel_admin_projects_request_billing(array $company, array $admin, array $request): array`.
- Consumes HR timesheet header/detail snapshots and Finance billing snapshots; stores no authoritative timesheet, employee, invoice, or invoice-line row.

- [ ] **Step 1: Write failing activity master tests**

Cover activity-type uniqueness and status, one effective employee/activity/company rate per date interval, non-overlapping effective ranges, non-negative costing and billing rates, HR employee validation, authorization, CSRF, create/update, audit, rollback, exact read-back, and server rehydration.

- [ ] **Step 2: Write failing HR and Finance adapter tests**

Use service spies to return Draft, Submitted, Partially Billed, Billed, Completed, and Cancelled timesheets with detail rows. Assert project/task keys are company-scoped, hours and amounts are displayed from owner snapshots, overlap policy is passed to HR, and a billing request calls Finance only after a separate confirmed action. Assert Projects never changes HR status or Finance invoice state.

- [ ] **Step 3: Run the tests and verify missing time services**

Run: `php tests/projects-time.php && php tests/projects-timesheet-integration.php`

Expected: non-zero exit at missing activity and timesheet adapter functions.

- [ ] **Step 4: Implement activity transactions and owner adapters**

Activity masters use complete upserts with audit and read-back. Timesheet list/detail methods normalize only owner-returned snapshots, reject mismatched company/project/task keys, and expose explicit dependency-unavailable errors. Billing requests validate unbilled positive hours, stable customer/project references, and idempotency before calling the Finance draft service.

- [ ] **Step 5: Verify time ownership and behavior**

Run: `php tests/projects-time.php && php tests/projects-timesheet-integration.php && php tests/projects-schema.php`

Expected: all pass; schema inspection confirms no HR timesheet, assignment, or Finance billing authority is duplicated.

- [ ] **Step 6: Commit time integration**

```bash
git add company/admin/modules/projects/time.php tests/projects-time.php tests/projects-timesheet-integration.php
git commit -m "feat(projects): add activity and timesheet adapters"
```

### Task 7: Implement Project Summary And Delayed Task Reports

**Ledger rows:** `Delayed Tasks Summary` report; `Delayed Tasks Summary` report code; `Test Delayed Tasks Summary`; `Project Summary` report; `Project Summary` report code.

**Files:**
- Create: `company/admin/modules/projects/reports.php`
- Create: `tests/projects-delayed-tasks.php`
- Create: `tests/projects-summary-report.php`

**Interfaces:**
- Produces: `yovel_admin_projects_delayed_tasks_report(array $company, array $filters): array` and `yovel_admin_projects_summary_report(array $company, array $filters): array`.
- Report result shape: `['columns' => array, 'rows' => array, 'chart' => array, 'summary' => array, 'filters' => array]`.

- [ ] **Step 1: Write the delayed-task regression test**

Create open, completed, on-track, delayed, and cross-company tasks. Verify filters for project, status, priority, start date, and end date; deterministic ordering; positive delayed days, negative/zero on-track values, chart totals, safe empty results, and export data matching visible filtered rows.

- [ ] **Step 2: Write the project-summary test**

Verify total, completed, and overdue task counts; completion percentage; average-completion summary; project/status/type/date filters; chart truncation to 30 project labels; empty-state summaries; company isolation; and no unfiltered query path.

- [ ] **Step 3: Run the tests and verify missing report functions**

Run: `php tests/projects-delayed-tasks.php && php tests/projects-summary-report.php`

Expected: non-zero exit at missing report functions.

- [ ] **Step 4: Implement parameterized report queries**

Build allow-listed filters and parameter arrays, always include `company_key_hash`, calculate date differences consistently in PHP after fetching normalized dates, and return columns, rows, chart, summary, and canonical filters. Export uses the same report result and requires confirmation before generation.

- [ ] **Step 5: Verify reports**

Run: `php tests/projects-delayed-tasks.php && php tests/projects-summary-report.php && php tests/projects-tasks.php`

Expected: all pass, including upstream-equivalent delayed-task cases and empty datasets.

- [ ] **Step 6: Commit core reports**

```bash
git add company/admin/modules/projects/reports.php tests/projects-delayed-tasks.php tests/projects-summary-report.php
git commit -m "feat(projects): add summary and delayed task reports"
```

### Task 8: Implement Owner-Composed Timesheet, Billing, And Stock Reports

**Ledger rows:** `Daily Timesheet Summary` report and code; `Timesheet Billing Summary` report and code; `Project wise Stock Tracking` report and code.

**Files:**
- Modify: `company/admin/modules/projects/reports.php`
- Create: `tests/projects-timesheet-reports.php`
- Create: `tests/projects-stock-report.php`

**Interfaces:**
- Produces: `yovel_admin_projects_daily_timesheet_report(array $company, array $filters): array`, `yovel_admin_projects_timesheet_billing_report(array $company, array $filters): array`, and `yovel_admin_projects_stock_tracking_report(array $company, array $filters): array`.
- Consumes only the owner contracts in `Required Owner Contracts`.

- [ ] **Step 1: Write failing daily-timesheet and billing-report tests**

Verify from/to date bounds, employee and project filters, submitted-only default, explicit draft inclusion, grouping by date/project/employee, working hours, billing hours, billing amount, group totals, stable timesheet references, company isolation, and dependency-unavailable errors that do not show stale success data.

- [ ] **Step 2: Write the failing stock-tracking test**

Inject Buying, Inventory, Sales, and Finance snapshots for two projects and two companies. Verify estimated cost, purchase-receipt cost, material issue cost, delivered value, stock-updating invoice value, total consumed/delivered value, deterministic project ordering, missing-owner handling, and no direct SQL reference to another module's tables.

- [ ] **Step 3: Run tests and verify missing report adapters**

Run: `php tests/projects-timesheet-reports.php && php tests/projects-stock-report.php`

Expected: non-zero exit at missing report functions or explicit missing owner contracts.

- [ ] **Step 4: Implement service-composed reports**

Validate filters before owner calls, reject snapshots with mismatched company or project keys, join only by stable keys in memory, and derive totals from owner-returned decimal strings with the project's established money helper. Export and consequential billing actions use separate confirmation; viewing a report never writes.

- [ ] **Step 5: Verify composed reports**

Run: `php tests/projects-timesheet-reports.php && php tests/projects-stock-report.php && php tests/projects-timesheet-integration.php`

Expected: all pass with no authoritative cross-module writes.

- [ ] **Step 6: Request orchestration integration coverage**

Ask orchestration to add or enable `tests/erpnext-project-billing-flow.php` after HR and Finance contracts are green. Projects does not edit that orchestration-owned test.

- [ ] **Step 7: Commit owner-composed reports**

```bash
git add company/admin/modules/projects/reports.php tests/projects-timesheet-reports.php tests/projects-stock-report.php
git commit -m "feat(projects): add service-composed project reports"
```

### Task 9: Close The Projects Ledger With Fresh Evidence

**Files:**
- Modify: `docs/erpnext-parity/ledgers/projects.json`
- Verify: every file under `company/admin/modules/projects/`
- Verify: every `tests/projects-*` file

**Interfaces:**
- Consumes: completed Tasks 1-8, orchestration-owned shared route/modal/Form Builder wiring, and green owner-service dependencies.
- Produces: row-level `COMPLETE`, `PARTIAL`, or `MISSING` statuses with exact implementation and test evidence. It does not force closure when a dependency remains unavailable.

- [ ] **Step 1: Run PHP syntax checks**

```bash
find company/admin/modules/projects -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: every file reports no syntax errors.

- [ ] **Step 2: Run the focused suite twice**

```bash
for pass in 1 2; do
  for test_file in tests/projects-*.php; do
    php "$test_file" || exit 1
  done
done
```

Expected: every test exits `0` on both passes and fixtures leave no persisted rows.

- [ ] **Step 3: Run route and browser verification after orchestration wiring**

Verify the authenticated Projects workspace and every section return non-500 responses without warning/fatal text. At desktop and narrow widths, verify the 12/8 split, main-first stacking, no overlap or horizontal scroll, modal focus and Escape behavior, retained values after Cancel/error, no request before Confirm, one request after Confirm, server-backed refresh, and accessible success/failure feedback.

- [ ] **Step 4: Update each ledger row from evidence**

For each row, add exact local file/function evidence and the passing focused test command. Keep a row `PARTIAL` when only part of its behavior is verified and `MISSING` when its dependency or implementation is absent. Do not mark the Timesheet, Timesheet Detail, billing, stock, portal, workspace, or assignment-related rows complete from a mocked test alone; require the corresponding owner and orchestration integration checks.

- [ ] **Step 5: Validate the ledger and working tree**

```bash
php tests/erpnext-parity-ledger.php
php tools/erpnext-parity.php validate-ledger
git diff --check
git status --short
```

Expected: validators pass; only Projects-owned implementation, `tests/projects-*`, and the Projects ledger are changed by this implementation sequence.

- [ ] **Step 6: Commit verified parity evidence**

```bash
git add docs/erpnext-parity/ledgers/projects.json company/admin/modules/projects tests/projects-*
git commit -m "docs(projects): record verified parity evidence"
```

## Proposed Implementation Sequence

1. Orchestration confirms the shared registry/modal/Form Builder contracts and owner-service signatures.
2. Tasks 1-3 establish the module boundary, workspace, Form Builder, Projects masters, and templates.
3. Task 4 adds the task graph and HR assignment handoff.
4. Task 5 adds collaboration and project/customer portal authorization.
5. Task 6 waits for HR timesheet and Finance billing contracts, then adds read-only time integration.
6. Task 7 adds self-contained Projects reports.
7. Task 8 waits for HR, Finance, Buying, Inventory, and Sales report services, then adds composed reports.
8. Task 9 runs the complete evidence gate and updates ledger statuses without overstating dependency-backed parity.

## Highest-Risk Gaps

- HR owns timesheets and assignments, but four ledger rows and three reports depend on them; inventing Projects-side authority would violate ownership and make later integration unsafe.
- Finance owns billing and invoice lifecycle. Billing requests must be idempotent, confirmed, and transactionally coordinated without Projects changing Finance rows.
- Project/task/template graphs require cycle detection, deterministic locks, dependency remapping, rollback, and project-progress recalculation in one coherent workflow.
- Portal membership and Project Update notifications cross the Operations boundary and can leak project/customer information if revocation, company scope, or recipient snapshots are incomplete.
- Stock tracking combines Buying, Inventory, Sales, and Finance facts; direct table joins would violate module boundaries and make report totals sensitive to independent schema changes.
- The entire module, focused suite, route registration, modal behavior, and responsive workspace are currently absent, so there is no low-risk incremental parity claim to preserve.
