# Support / Service ERPNext Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement every applicable `MISSING` Support and service-Telephony ledger row against the pinned ERPNext baseline while preserving company isolation and consuming Sales/CRM customer and Projects service-work data only through owner-provided services.

**Architecture:** The Support/Service module owns company-scoped issue, SLA, warranty, reporting, support settings, and telephony persistence beneath `company/admin/modules/support-service/`. It consumes stable external keys and read/service APIs from Sales/CRM, Projects, Inventory/Warehouse, Assets/Maintenance, HR, and Operations; it never writes those modules' tables. Shared routing, modal/confirmation behavior, the universal Form Builder runtime, portal shell, and shared assets remain orchestrator-owned.

**Tech Stack:** PHP 8.5 runtime with PHP 8.1 compatibility, ADODB/MySQL, server-rendered PHP, Tailwind 4 and shadcn tokens, Lucide/Material icons already present, vanilla JavaScript through orchestrator-owned shared assets, PHP executable tests, curl route checks, and Playwright browser verification.

## Global Constraints

- Use ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325` as the Support and Telephony functional baseline.
- Use `gpt-5.6-sol` with `high` reasoning for dispatched implementation work.
- Implement only ledger gaps; there is no existing Support/Service runtime behavior to replace.
- Do not edit `app/foundation.php`, `company/admin/bootstrap/`, `company/admin/core/`, `company/admin/views/layout.php`, `company/admin/views/partials/`, `company/admin/assets/`, `company/admin/modules/shared/`, another module, or another module's tests/docs.
- Treat Sales/CRM customer data and Projects/service work as dependencies. Consume owner services and stable keys; do not read or write their authoritative tables directly.
- Every configurable record type uses the universal module-local Form Builder adapter with stable keys, protected system fields, draft/publish/archive/version history, immutable published versions, and version-bound submissions.
- Every workspace and feature page uses a desktop 20-column composition with left main panel `12/20`, right action/tools panel `8/20`, and main-first stacking on smaller screens.
- Every Add, New, Create, or Insert command opens an accessible body-owned modal.
- A form Submit opens a separate body-owned sibling confirmation dialog. Persistence starts only after Confirm, Confirm submits once, and Cancel restores the populated modal and focus.
- Every write requires an authorized active company admin, company scoping, server validation, parameterized SQL, one owner-controlled ADODB transaction, `bx_audit(...)`, exact read-back verification, rollback on failure, and server rehydration.
- Empty, loading, populated, validation, success, failure, unauthorized, and archived states must be functional; placeholders do not satisfy a ledger row.
- Module-local backward-compatible improvements may be implemented with focused tests. Shared or cross-module improvements must be returned to orchestration.
- Reproduce behavior and information architecture without copying upstream GPL source, assets, names, logos, or product branding.
- Mobile/Android work remains deferred and is not part of this plan.

---

## Audit Result

The audit found no `company/admin/modules/support-service/` directory and no `tests/support-service-*` files. Therefore all 26 applicable rows remain `MISSING`; none has local implementation or passing focused-test evidence.

| Package | Pinned ledger rows | Count | Audit status |
|---|---|---:|---|
| WP-WORKSPACE | `Support Search Source`, `Support Settings`, `Support` workspace | 3 | MISSING |
| WP-ISSUE | `Issue`, `Issue Priority`, `Issue Type` | 3 | MISSING |
| WP-SLA | `Pause SLA On Status`, `Service Day`, `Service Level Agreement`, `Service Level Priority`, `SLA Fulfilled On Status` | 5 | MISSING |
| WP-WARRANTY | `Warranty Claim` | 1 | MISSING |
| WP-REPORTS | First Response definition/code; Issue Analytics definition/code/upstream test; Issue Summary definition/code; Support Hour Distribution definition/code | 9 | MISSING |
| WP-TELEPHONY | `Call Log`, `Incoming Call Handling Schedule`, `Incoming Call Settings`, `Telephony Call Type`, `Voice Call Settings` | 5 | MISSING |

The pinned discovery ledger has no separate web-form row for the customer issue portal even though portal behavior is part of the approved Support/Service scope. This plan binds portal list/create/view/reply authorization to the `Issue` row and portal/search configuration to `Support Settings` and `Support Search Source`. Orchestration should decide whether its shared discovery tool needs a new `web_form` artifact kind; the Support owner must not edit that tool.

## Required Dependency Gates

Implementation starts only after orchestration confirms the shared registry/modal/Form Builder contracts and the following owner-service interfaces or equivalent reviewed adapters. The Support module must not add direct-table fallbacks.

```php
yovel_admin_sales_crm_customer_reference(array $company, string $customerKey): ?array;
yovel_admin_sales_crm_contact_reference(array $company, string $contactKey): ?array;
yovel_admin_sales_crm_party_by_phone(array $company, string $normalizedPhone): array;
yovel_admin_projects_service_work_reference(array $company, string $serviceWorkKey): ?array;
yovel_admin_projects_open_service_work(array $company, array $admin, array $request): array;
yovel_admin_inventory_warranty_reference(array $company, string $itemKey, string $serialKey): ?array;
yovel_admin_assets_warranty_reference(array $company, string $assetKey): ?array;
yovel_admin_hr_employee_reference(array $company, string $employeeKey): ?array;
yovel_admin_hr_employee_group_reference(array $company, string $groupKey): ?array;
yovel_admin_operations_append_communication(array $company, array $admin, array $message): array;
yovel_admin_operations_enqueue_job(array $company, array $admin, array $job): array;
yovel_admin_operations_publish_event(array $company, array $event): void;
```

If an owner exposes a differently named stable interface, orchestration supplies the adapter outside this module's write scope before Support implementation begins.

## File Map

**Module files to create later:**

- `company/admin/modules/support-service/functions.php`: section metadata, module data aggregation, dependency checks, and stable public entry points.
- `company/admin/modules/support-service/schema.php`: idempotent company-scoped schema only.
- `company/admin/modules/support-service/forms.php`: universal Form Builder adapter targets, protected fields, normalization, and published-version binding.
- `company/admin/modules/support-service/settings.php`: support settings and allowlisted search-source services.
- `company/admin/modules/support-service/issues.php`: issue masters, issue lifecycle, portal-safe reads, and communication callbacks.
- `company/admin/modules/support-service/sla.php`: SLA validation, applicability, business-time calculations, hold/resume, and fulfillment state.
- `company/admin/modules/support-service/warranty.php`: warranty claim lifecycle and Projects service-work handoff.
- `company/admin/modules/support-service/reports.php`: four authorized, company-scoped report services.
- `company/admin/modules/support-service/telephony.php`: call types/settings/schedules, idempotent call-log ingestion, participant linking, and provider-event handling.
- `company/admin/modules/support-service/actions.php`: CSRF-checked module POST dispatcher and server rehydration state.
- `company/admin/modules/support-service/views/workspace.php`: 12/8 shell and feature navigation.
- `company/admin/modules/support-service/views/issues.php`: issue list/detail, timeline, filters, and record modals.
- `company/admin/modules/support-service/views/sla.php`: SLA list/detail and modal editor.
- `company/admin/modules/support-service/views/warranty.php`: warranty list/detail and modal editor.
- `company/admin/modules/support-service/views/reports.php`: filters, tables, summaries, and chart-ready data output.
- `company/admin/modules/support-service/views/telephony.php`: call log and settings surfaces.
- `company/admin/modules/support-service/views/settings.php`: settings/search-source surfaces.

**Focused tests to create later:**

- `tests/support-service-test-helper.php`: fixture preservation, rollback cleanup, dependency fakes, and assertion helpers.
- `tests/support-service-foundation.php`: schema, scope, authorization, Form Builder adapter, and settings.
- `tests/support-service-issues.php`: masters, lifecycle, communications, portal isolation, and rehydration.
- `tests/support-service-sla.php`: schedule validation, SLA selection, clocks, pauses, and status transitions.
- `tests/support-service-warranty.php`: warranty snapshots, lifecycle, cancellation guard, and service-work handoff.
- `tests/support-service-reports.php`: filters and exact aggregate calculations.
- `tests/support-service-telephony.php`: settings, overlap validation, idempotent ingestion, linking, and events.
- `tests/support-service-workspace.php`: 12/8 markup, accessible modal/confirmation hooks, feature routes, and non-placeholder states.

---

### Task 1: Establish The Module Foundation, Settings, And Form Adapter

**Ledger rows:** `Support Settings`, `Support Search Source`.

**Files:**
- Create: `company/admin/modules/support-service/schema.php`
- Create: `company/admin/modules/support-service/functions.php`
- Create: `company/admin/modules/support-service/forms.php`
- Create: `company/admin/modules/support-service/settings.php`
- Create: `tests/support-service-test-helper.php`
- Create: `tests/support-service-foundation.php`

**Interfaces:**
- Produces: `yovel_admin_support_service_schema(): void`, `yovel_admin_support_service_scope(array,array): array`, `yovel_admin_support_service_sections(): array`, `yovel_admin_support_service_section(): string`, `yovel_admin_support_service_form_adapter(): array`, `yovel_admin_support_settings(array): array`, `yovel_admin_save_support_settings(array,array,array): array`, and `yovel_admin_save_support_search_source(array,array,array): array`.
- Consumes: orchestration-owned shared Form Builder/version services and Operations portal/search/job services.

- [ ] **Step 1: Write failing foundation tests**

Assert the module functions are absent, then specify idempotent schema creation, exact table/index inventory, active-admin scope validation, company-isolated reads, rejection of unsafe search URLs/routes, and Form Builder targets for `issue`, `issue-priority`, `issue-type`, `service-level-agreement`, `warranty-claim`, `telephony-call-type`, `incoming-call-settings`, and `voice-call-settings`.

```php
support_assert(function_exists('yovel_admin_support_service_schema'), 'Support schema service is missing.');
support_assert(function_exists('yovel_admin_support_service_form_adapter'), 'Support Form Builder adapter is missing.');
support_assert($adapter['targets']['issue']['protected_fields'] === ['subject', 'status', 'company_key_hash'], 'Issue system fields are not protected.');
```

- [ ] **Step 2: Run the focused test and verify the expected missing-service failure**

Run: `php tests/support-service-foundation.php`

Expected: non-zero exit naming the first missing Support service; no database rows are changed.

- [ ] **Step 3: Implement idempotent module-owned schema**

Create normalized tables prefixed `project_company_support_` for settings, search sources, issue priorities/types/issues/events, SLA headers/service days/priorities/pause statuses/fulfilled statuses, warranty claims, call types/incoming settings/schedules/voice settings/call logs/call links. Every mutable table carries a stable UUID key, `company_key`, `company_key_hash`, actor keys, timestamps, company-first indexes, and status/archive columns where applicable. Store external owner keys and immutable display snapshots; do not add foreign keys or writes into owner tables.

- [ ] **Step 4: Implement scope and settings writes with the approved transaction boundary**

`yovel_admin_support_service_scope()` rejects empty/mismatched company/admin data. Each save validates before `BeginTrans()`, locks the scoped current row, executes parameterized SQL, performs an exact scoped read-back, calls `bx_audit(...)` before `CommitTrans()`, rolls back every exception, and returns the read-back row for server rendering.

- [ ] **Step 5: Implement the module-local Form Builder adapter**

Return stable target and field keys, supported field types/options/defaults/visibility/width rules, protected workflow fields, and persistence mappings. Delegate draft/publish/archive/version history to the orchestrator-owned shared runtime; never copy HR or Finance persistence tables.

- [ ] **Step 6: Run foundation tests twice**

```bash
php tests/support-service-foundation.php
php tests/support-service-foundation.php
php -l company/admin/modules/support-service/schema.php
php -l company/admin/modules/support-service/functions.php
php -l company/admin/modules/support-service/forms.php
php -l company/admin/modules/support-service/settings.php
```

Expected: every command exits `0`; the second run creates no duplicate schema/configuration rows.

### Task 2: Implement Issue Masters, Lifecycle, Communications, And Portal Services

**Ledger rows:** `Issue`, `Issue Priority`, `Issue Type`.

**Files:**
- Create: `company/admin/modules/support-service/issues.php`
- Create: `company/admin/modules/support-service/actions.php`
- Create: `company/admin/modules/support-service/views/issues.php`
- Create: `tests/support-service-issues.php`

**Interfaces:**
- Produces: `yovel_admin_save_support_issue_priority(...)`, `yovel_admin_save_support_issue_type(...)`, `yovel_admin_save_support_issue(...)`, `yovel_admin_transition_support_issue(...)`, `yovel_admin_support_issue_record_communication(...)`, `yovel_admin_support_issue_split(...)`, `yovel_admin_support_portal_issues(...)`, and `yovel_admin_support_portal_issue(...)`.
- Consumes: Sales/CRM customer/contact references, Projects service-work references, Operations communications, and Task 3 SLA callbacks.

- [ ] **Step 1: Write failing issue tests**

Cover company isolation; required subject; allowed statuses `OPEN`, `REPLIED`, `ON_HOLD`, `RESOLVED`, `CLOSED`; priority/type ownership; customer/project lookup through dependency fakes; generated issue numbers; portal creator/customer visibility; external reply re-opening; first agent response capture; issue splitting with selected communications; soft archive; transaction rollback; audit metadata; exact read-back; and rehydrated invalid input.

- [ ] **Step 2: Run the issue test and verify the missing-service failure**

Run: `php tests/support-service-issues.php`

Expected: non-zero exit naming `yovel_admin_save_support_issue`; fixture cleanup leaves the database unchanged.

- [ ] **Step 3: Implement priority and type masters**

Use case-insensitive company-scoped uniqueness, stable UUID keys, `ACTIVE`/`INACTIVE`/`ARCHIVED` states, authorized modal saves, confirmation before mutation, audit logging, and server-backed list refresh.

- [ ] **Step 4: Implement issue create/update and lifecycle rules**

Persist the customer/project keys returned by owner services plus immutable names/codes for historical display. Set opening timestamps server-side, reject cross-company references, record every status/SLA change as an issue event, and calculate resolution/hold timestamps through Task 3 services. Add/update forms open in the shared modal and retain submitted values plus field errors after failure.

- [ ] **Step 5: Implement communication and portal boundaries**

Operations owns message persistence/delivery. Support passes a stable Issue reference and receives the saved communication identity, then records only its Support projection and SLA event. Portal reads filter by verified Sales/CRM customer key or authenticated raiser identity; portal users cannot enumerate another customer, change administrative fields, or bypass confirmation.

- [ ] **Step 6: Run and commit the issue slice after review**

```bash
php tests/support-service-foundation.php
php tests/support-service-issues.php
php -l company/admin/modules/support-service/issues.php
php -l company/admin/modules/support-service/actions.php
php -l company/admin/modules/support-service/views/issues.php
```

Expected: all commands exit `0`; dependency fakes show no direct Sales/CRM or Projects table access.

### Task 3: Implement The SLA Engine And First-Response Tracking

**Ledger rows:** `Pause SLA On Status`, `Service Day`, `Service Level Agreement`, `Service Level Priority`, `SLA Fulfilled On Status`.

**Files:**
- Create: `company/admin/modules/support-service/sla.php`
- Create: `company/admin/modules/support-service/views/sla.php`
- Create: `tests/support-service-sla.php`

**Interfaces:**
- Produces: `yovel_admin_save_support_sla(...)`, `yovel_admin_support_sla_resolve(...)`, `yovel_admin_support_sla_expected_at(...)`, `yovel_admin_support_sla_on_issue_created(...)`, `yovel_admin_support_sla_on_communication(...)`, `yovel_admin_support_sla_on_status_change(...)`, and `yovel_admin_reset_support_sla(...)`.
- Consumes: Issue, Issue Priority, Sales/CRM customer segment, Operations calendar/holiday, and communication event interfaces.

- [ ] **Step 1: Write failing SLA tests from the pinned behavior**

Test unique workdays, start-before-end, no duplicate priorities, exactly one default priority, response time not greater than resolution time, one default SLA per company/document target, validity dates, customer/group/territory applicability, conditions from an allowlisted expression model, holidays, before/during/after-hours calculations, multi-day calculations, hold/resume extensions, first-response state, resolution state, failure/fulfillment, closed-to-open reset, and authorized reset with reason.

- [ ] **Step 2: Run and verify the missing-engine failure**

Run: `php tests/support-service-sla.php`

Expected: non-zero exit naming `yovel_admin_support_sla_expected_at`.

- [ ] **Step 3: Implement validated SLA persistence**

Save header and all child rows in one transaction. Replace child collections only after locking the scoped header, reject duplicate/invalid rows before writes, read the full aggregate back in deterministic order, audit the aggregate version, and return it for rehydration.

- [ ] **Step 4: Implement deterministic SLA selection and business-time math**

Select the most specific active agreement in this order: Customer, Customer Group/Territory, condition, default. Use the issue's captured SLA start, configured workdays, time windows, and Operations holiday dates. Store timestamps in UTC and render in company/user timezone. Tests use fixed clocks; no calculation reads wall time implicitly.

- [ ] **Step 5: Implement communication/status transitions**

An agent's first sent communication captures first response and changes agreement state from `FIRST_RESPONSE_DUE` to `RESOLUTION_DUE` when resolution tracking is enabled. Pause statuses freeze resolution consumption; resume extends deadlines by hold duration. Fulfilled statuses capture resolution time; reopening clears resolution metrics and resumes the remaining clock.

- [ ] **Step 6: Run foundation, issue, and SLA suites twice**

```bash
for run in 1 2; do php tests/support-service-foundation.php && php tests/support-service-issues.php && php tests/support-service-sla.php || exit 1; done
php -l company/admin/modules/support-service/sla.php
php -l company/admin/modules/support-service/views/sla.php
```

Expected: all commands exit `0` on both runs with identical calculated deadlines.

### Task 4: Implement Warranty Claims And Projects Service-Work Handoff

**Ledger row:** `Warranty Claim`.

**Files:**
- Create: `company/admin/modules/support-service/warranty.php`
- Create: `company/admin/modules/support-service/views/warranty.php`
- Create: `tests/support-service-warranty.php`

**Interfaces:**
- Produces: `yovel_admin_save_support_warranty_claim(...)`, `yovel_admin_transition_support_warranty_claim(...)`, and `yovel_admin_support_warranty_create_service_work(...)`.
- Consumes: Sales/CRM customer reference, Inventory item/serial warranty reference, Assets warranty reference, and Projects service-work create/reference services.

- [ ] **Step 1: Write failing warranty tests**

Cover required customer/company/complaint/date; owner-service validation; immutable customer/item/serial/warranty snapshots; statuses `OPEN`, `WORK_IN_PROGRESS`, `CLOSED`, `CANCELLED`; automatic resolution timestamp/actor on close; cancel rejection while active Projects service work exists; idempotent service-work handoff; company isolation; rollback; audit; read-back; modal confirmation; and failure rehydration.

- [ ] **Step 2: Run and verify the expected missing-service failure**

Run: `php tests/support-service-warranty.php`

Expected: non-zero exit naming `yovel_admin_save_support_warranty_claim`.

- [ ] **Step 3: Implement claim persistence and lifecycle**

Use one transaction per create/update/transition, preserve dependency snapshots, require resolution details for close, and archive without destroying historical claims. Do not reproduce Maintenance-owned records inside Support.

- [ ] **Step 4: Implement the Projects handoff as an idempotent service call**

Build a request carrying company key, warranty claim key/code, customer key, item/serial/asset keys, complaint, service address, and idempotency key. Persist only the returned Projects service-work key and snapshot after exact read-back; retries must return the same link.

- [ ] **Step 5: Run the dependency chain**

```bash
php tests/support-service-foundation.php
php tests/support-service-issues.php
php tests/support-service-sla.php
php tests/support-service-warranty.php
php -l company/admin/modules/support-service/warranty.php
php -l company/admin/modules/support-service/views/warranty.php
```

Expected: all commands exit `0`; dependency fakes record service calls and no foreign-table writes.

### Task 5: Implement The Four Support Reports

**Ledger rows:** all nine WP-REPORTS rows.

**Files:**
- Create: `company/admin/modules/support-service/reports.php`
- Create: `company/admin/modules/support-service/views/reports.php`
- Create: `tests/support-service-reports.php`

**Interfaces:**
- Produces: `yovel_admin_support_first_response_report(array,array): array`, `yovel_admin_support_issue_analytics(array,array): array`, `yovel_admin_support_issue_summary(array,array): array`, and `yovel_admin_support_hour_distribution(array,array): array`.
- Consumes: company-scoped issue/SLA projections and owner-reference labels only.

- [ ] **Step 1: Write failing report tests with deterministic fixtures**

Assert date bounds and company scope on every query. Verify daily average first-response seconds; weekly/monthly/quarterly/yearly issue counts grouped by customer, assignee, type, or priority; status and SLA-state counts plus average response/resolution metrics; and date-by-hour issue distribution with totals. Cover customer/project/status/priority/assignee filters, empty results, invalid ranges, unauthorized access, and no cross-company leakage.

- [ ] **Step 2: Run and verify the missing-report failure**

Run: `php tests/support-service-reports.php`

Expected: non-zero exit naming `yovel_admin_support_first_response_report`.

- [ ] **Step 3: Implement parameterized aggregate queries and normalized outputs**

Return `{columns, rows, totals, chart}` arrays with stable machine keys and localized display labels. Build filter predicates from a fixed allowlist; never interpolate field names, sort keys, or grouping expressions from raw input. Use stored external snapshots for historical labels and owner services only for optional current-link decoration.

- [ ] **Step 4: Render report states in the 12/8 contract**

The main panel holds data/chart content; the utility panel holds filters, export/print commands supplied by shared services, and saved views. Loading, empty, populated, invalid-filter, unauthorized, and failure states remain within stable dimensions.

- [ ] **Step 5: Run report and regression suites**

```bash
php tests/support-service-issues.php
php tests/support-service-sla.php
php tests/support-service-reports.php
php -l company/admin/modules/support-service/reports.php
php -l company/admin/modules/support-service/views/reports.php
```

Expected: all commands exit `0` and fixture totals match exact expected values.

### Task 6: Implement Service Telephony

**Ledger rows:** `Call Log`, `Incoming Call Handling Schedule`, `Incoming Call Settings`, `Telephony Call Type`, `Voice Call Settings`.

**Files:**
- Create: `company/admin/modules/support-service/telephony.php`
- Create: `company/admin/modules/support-service/views/telephony.php`
- Create: `tests/support-service-telephony.php`

**Interfaces:**
- Produces: `yovel_admin_save_support_call_type(...)`, `yovel_admin_save_support_incoming_call_settings(...)`, `yovel_admin_save_support_voice_settings(...)`, `yovel_admin_support_ingest_call(...)`, `yovel_admin_support_update_call(...)`, `yovel_admin_support_add_call_summary(...)`, and `yovel_admin_support_call_logs_for_reference(...)`.
- Consumes: Sales/CRM party-by-phone, HR employee/group/user references, and Operations provider/realtime/communication contracts.

- [ ] **Step 1: Write failing telephony tests**

Cover routing values `SEQUENTIAL`/`SIMULTANEOUS`; valid non-overlapping day schedules; per-user receiving device `COMPUTER`/`PHONE`; call states `RINGING`, `IN_PROGRESS`, `COMPLETED`, `FAILED`, `BUSY`, `NO_ANSWER`, `QUEUED`, `CANCELLED`; incoming/outgoing direction; unique provider call ID; retry idempotency; normalized phone matching through Sales/CRM; recipient assignment through HR; deduplicated reference links; call summary/type updates; end/missed events; company isolation; audit; rollback; read-back; and rehydration.

- [ ] **Step 2: Run and verify the missing-service failure**

Run: `php tests/support-service-telephony.php`

Expected: non-zero exit naming `yovel_admin_support_ingest_call`.

- [ ] **Step 3: Implement settings and schedule aggregates**

Validate each time slot before writes, reject same-day overlap, lock and replace child rows in one transaction, read back ordered schedules, audit changes, and expose modal-backed settings forms through the Form Builder adapter.

- [ ] **Step 4: Implement idempotent call ingestion and lifecycle**

Use `(company_key_hash, provider_key, provider_call_id)` as the idempotency boundary. Keep provider payload fields allowlisted, store normalized participant values and immutable names, resolve owner references only through services, deduplicate links, and publish Operations events only after the Support transaction commits.

- [ ] **Step 5: Run telephony tests twice**

```bash
for run in 1 2; do php tests/support-service-telephony.php || exit 1; done
php -l company/admin/modules/support-service/telephony.php
php -l company/admin/modules/support-service/views/telephony.php
```

Expected: all commands exit `0`; duplicate provider fixtures produce one call log and one set of links.

### Task 7: Assemble The Workspace And Verify Interaction Contracts

**Ledger row:** `Support` workspace, plus UI acceptance for every package.

**Files:**
- Create: `company/admin/modules/support-service/views/workspace.php`
- Create: `company/admin/modules/support-service/views/settings.php`
- Create: `tests/support-service-workspace.php`
- Modify only if needed within scope: `company/admin/modules/support-service/functions.php`
- Modify only if needed within scope: `company/admin/modules/support-service/actions.php`

**Interfaces:**
- Produces: a complete module-local workspace render consumed by the orchestrator-owned module registry.
- Consumes: shared route, modal, confirmation, Form Builder, export/print, and portal-shell contracts.

- [ ] **Step 1: Write failing workspace contract tests**

Assert all six feature groups resolve without placeholder text; desktop markup uses `12fr/8fr`; mobile order is main then utilities; every Add/New/Create/Insert trigger targets an accessible modal; every form has the shared confirmation hook; dialogs are body-owned siblings; modal Cancel/confirmation Cancel preserve values and focus; server errors reopen the modal; and Form Builder targets render in the right panel.

- [ ] **Step 2: Run and verify the missing-workspace failure**

Run: `php tests/support-service-workspace.php`

Expected: non-zero exit because the workspace view is absent.

- [ ] **Step 3: Implement the complete module-local workspace**

Use compact feature navigation, tables/timelines/reports in the main panel, actions/filters/Form Builder in the utility panel, stable responsive dimensions, Lucide/Material icons already supplied by the app, visible focus, accessible labels, and no nested cards or decorative placeholders. Do not add shared CSS or JavaScript.

- [ ] **Step 4: Request orchestration-owned route and portal wiring**

Report the module function file, workspace view, section provider, data provider, default section, portal service callbacks, and required owner adapters to orchestration. The Support task stops before editing registry/bootstrap/layout/shared files.

- [ ] **Step 5: Run static and live interaction checks after orchestration wires the route**

```bash
php tests/support-service-workspace.php
support_company_slug="$(php -r 'require "app/foundation.php"; echo (string) bx_db()->GetOne("SELECT company_slug FROM project_company WHERE company_status = ? ORDER BY x_id LIMIT 1", ["ACTIVE"]);')"
test -n "$support_company_slug"
curl -fsS "http://127.0.0.1/company/${support_company_slug}/admin/?view=support-service&section=issues" >/tmp/support-service-route.html
rg -n 'Support / Service|data-record-modal|data-confirm-submit' /tmp/support-service-route.html
```

Expected: test exits `0`, curl returns `200`, and all three required markers are present. Use the in-app browser/Playwright at desktop and mobile widths to verify focus order, modal/confirmation separation, no overlap, 12/8 desktop composition, and main-first stacking.

### Task 8: Close Ledger Rows Only With Fresh Evidence

**Files:**
- Modify: `docs/erpnext-parity/ledgers/support-service.json`
- Verify: all `company/admin/modules/support-service/*.php`
- Verify: all `company/admin/modules/support-service/views/*.php`
- Verify: all `tests/support-service-*.php`

- [ ] **Step 1: Run the full focused suite twice**

```bash
for run in 1 2; do
  for test_file in tests/support-service-*.php; do php "$test_file" || exit 1; done
done
```

Expected: every test exits `0` twice with fixture cleanup and no duplicate schema/data effects.

- [ ] **Step 2: Run syntax, whitespace, and shared-scope checks**

```bash
find company/admin/modules/support-service -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
git diff --name-only | rg -v '^(company/admin/modules/support-service/|tests/support-service-|docs/erpnext-parity/ledgers/support-service.json$|docs/superpowers/plans/2026-08-25-support-service-erpnext-parity.md$)'
```

Expected: syntax and whitespace checks exit `0`; the scope command prints nothing.

- [ ] **Step 3: Update row evidence package by package**

Change a row to `COMPLETE` only when its exact module file(s), exact passing test file/command, and live or interaction evidence are recorded. Leave any unsupported behavior `PARTIAL` or `MISSING`; do not close a JSON definition/code pair from one unverified label or route.

- [ ] **Step 4: Validate the ledger and run relevant integration gates**

```bash
php tools/erpnext-parity.php validate-ledger
php tests/erpnext-support-lifecycle-flow.php
php tests/company-admin-erp-contract.php
git diff --check
```

Expected: all available commands exit `0`. `tests/erpnext-support-lifecycle-flow.php` and shared route tests are orchestration-owned; if they are not yet present, report that integration gate as pending and do not claim end-to-end parity.

## Proposed Implementation Sequence

1. Wait for orchestration's shared registry/modal/Form Builder contracts and reviewed Sales/CRM customer plus Projects service-work APIs.
2. Build module schema, scope, settings, search-source validation, and Form Builder adapter.
3. Build Issue Priority/Type and the issue/communication/portal lifecycle.
4. Add the SLA engine and first-response tracking before reports.
5. Add warranty claims and the idempotent Projects service-work handoff.
6. Add all four reports using persisted issue/SLA metrics.
7. Add telephony settings and idempotent call-log ingestion after Operations/HR/Sales service contracts are available.
8. Assemble the full workspace, ask orchestration for shared route/portal wiring, run focused suites twice plus browser/live checks, then update ledger rows with exact evidence.

## Highest-Risk Gaps

- SLA timing is the largest business-logic risk: work windows, holidays, holds, reopenings, first responses, and resolution clocks must remain deterministic across timezones.
- Customer portal authorization is the largest data-isolation risk because a weak customer/raiser filter could expose another customer's issues.
- Sales/CRM and Projects currently require owner-provided service boundaries; direct table reads would violate ownership and make Support couple to unfinished modules.
- Warranty claims also depend on authoritative item/serial/asset warranty data and an idempotent Projects handoff; partial snapshots or duplicate handoffs would corrupt service history.
- Telephony is externally driven and retry-prone; provider event idempotency, phone normalization, recipient matching, and post-commit event publication need focused failure tests.
- The generated ledger lacks a separate web-form artifact for the customer portal, so orchestration must decide whether to extend discovery while this plan enforces portal behavior under the Issue/settings rows.

## Review Gate

Before implementation, orchestration reviews this plan for dependency interface naming, confirms shared Task 5 contracts are available, and approves the row grouping. Execution must use `executing-plans` or the orchestrator's subagent-driven workflow and stop whenever a required change falls outside the Support module/test scope.
