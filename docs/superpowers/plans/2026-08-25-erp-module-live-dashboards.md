# ERP Module Live Dashboards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every browser ERP group open on a live operational dashboard while preserving the completed HR and Accounting / Finance dashboards.

**Architecture:** Nine module owners implement module-local dashboard providers, views, focused tests, and browser evidence without editing shared files or foreign modules. HR and Accounting / Finance are regression references only. The orchestrator changes shared default routes to `dashboard` after every module-local dashboard contract is green, then independently validates the combined application.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, server-rendered HTML, Tailwind utility classes, Material Symbols, shared BuilderX modal/confirmation JavaScript, Playwright, JSON parity ledgers.

## Global Constraints

- Do not modify HR or Accounting / Finance module runtime files; validate their existing dashboards as references.
- Android / Mobile remains deferred and must not be edited.
- Use the existing 12/20 main panel and 8/20 right tools panel with main-first mobile stacking.
- Every mutating action opens a record modal, then a separate sibling confirmation dialog; zero writes occur before Confirm.
- Dashboard reads query only module-owned tables or allow-listed owner services.
- Missing dependencies use `UNAVAILABLE_DEPENDENCY`, never a fabricated numeric zero.
- Confirmed writes require authorization, CSRF, parameterized SQL, an explicit transaction, audit, exact read-back, rollback, and server rehydration.
- Keep published Form Builder versions immutable and preserve every existing workflow.
- Owner tasks may modify only their module directory, focused tests, and evidence rows in their own parity ledger.
- Shared registry, shared assets, shared partials, bootstrap, controller, layout, and shared tests are orchestrator-owned.

## Shared Dashboard Envelope

Every module dashboard provider returns:

```php
[
    'summary' => [['key' => 'stable-key', 'label' => 'Label', 'value' => 0, 'availability' => 'AVAILABLE', 'href' => '...']],
    'queue' => [['key' => 'stable-key', 'label' => 'Label', 'status' => 'OPEN', 'href' => '...']],
    'activity' => [['key' => 'stable-key', 'action' => 'CREATE', 'record_label' => 'Record', 'actor_label' => 'Admin', 'occurred_at' => '2026-08-25 00:00:00']],
    'setup' => [['key' => 'stable-key', 'label' => 'Step', 'complete' => false, 'href' => '...']],
    'alerts' => [['key' => 'stable-key', 'severity' => 'WARNING', 'label' => 'Alert', 'href' => '...']],
    'shortcuts' => [['key' => 'stable-key', 'label' => 'Destination', 'href' => '...', 'available' => true]],
    'directories' => [['group' => 'Reports', 'items' => []]],
    'dependencies' => [['contract' => 'owner.contract.v1', 'status' => 'AVAILABLE']],
]
```

Allowed availability values are `AVAILABLE`, `UNAVAILABLE_DEPENDENCY`, `NOT_IMPLEMENTED`, and `ERROR`.

---

### Task 1: Preserve HR And Finance Dashboard References

**Files:**
- Test: `tests/hr-dashboard-reference.php`
- Test: `tests/accounting-finance-dashboard-reference.php`
- Do not modify: `company/admin/modules/hr/`
- Do not modify: `company/admin/modules/accounting-finance/`

**Interfaces:**
- Consumes: existing `yovel_admin_hr_data(...)`, `yovel_admin_accounting_finance_data(...)`, dashboard views, and shared modal contract.
- Produces: regression evidence proving both completed dashboards remain operational.

- [ ] **Step 1: Write focused reference tests.** Assert registered `dashboard` sections, 12/8 hooks, setup, shortcuts, reports or masters, Form Builder access, modal confirmation markers, and no broken destinations.
- [ ] **Step 2: Run the reference tests.** Run `php tests/hr-dashboard-reference.php` and `php tests/accounting-finance-dashboard-reference.php`; both must fail only for missing assertions or fixtures, never by changing module runtime.
- [ ] **Step 3: Correct tests to consume the existing public contracts.** Do not weaken assertions and do not edit either module.
- [ ] **Step 4: Run all HR and Finance suites.** Run every `tests/hr-*.php` and `tests/accounting-finance-*.php` except helper files; require zero failures.
- [ ] **Step 5: Commit only the two reference tests.** Use `git add tests/hr-dashboard-reference.php tests/accounting-finance-dashboard-reference.php && git commit -m "Test completed HR and Finance dashboards"`.

### Task 2: Sales / CRM Live Dashboard

**Files:**
- Create: `company/admin/modules/sales-crm/dashboard.php`
- Create: `company/admin/modules/sales-crm/views/dashboard.php`
- Modify: `company/admin/modules/sales-crm/functions.php`
- Modify: `company/admin/modules/sales-crm/views/workspace.php`
- Create: `tests/sales-crm-dashboard.php`
- Create: `tests/sales-crm-dashboard-browser.mjs`

**Interfaces:**
- Produces: `yovel_admin_sales_crm_dashboard_data(array $company, array $admin): array` using the shared envelope.
- Consumes: Sales-owned lead, campaign, setting, and audit data; future opportunity, quotation, and order contracts render unavailable until registered.

- [ ] **Step 1: Write failing provider and workspace tests.** Assert dashboard registration, company isolation, lead and campaign KPIs, stale-lead queue, recent audit activity, dependency states, Form Builder shortcut, and 12/8 layout.
- [ ] **Step 2: Run `php tests/sales-crm-dashboard.php`.** Confirm failure because the provider and view do not exist.
- [ ] **Step 3: Implement the provider with parameterized Sales-owned reads.** Return bounded result sets, stable keys, explicit availability, implemented destinations, and role-filtered company data.
- [ ] **Step 4: Implement the dashboard view and workspace dispatch.** Render KPI summaries, queues, activity, setup, alerts, shortcuts, directories, guided tour, and Form Builder access without nested cards.
- [ ] **Step 5: Add browser coverage.** Prove desktop ratio, mobile stacking, guided-tour focus, modal confirmation, no pre-confirm request, and rehydration.
- [ ] **Step 6: Run all Sales and shared suites plus browser tests.** Require zero failures, PHP lint, ledger validation, ownership scan, and diff check.
- [ ] **Step 7: Commit the Sales-owned package.** Commit only Sales files, tests, and verified Sales ledger evidence.

### Task 3: Buying / Procurement Live Dashboard

**Files:**
- Create: `company/admin/modules/buying-procurement/dashboard.php`
- Modify: `company/admin/modules/buying-procurement/views/dashboard.php`
- Modify: `company/admin/modules/buying-procurement/functions.php`
- Modify: `company/admin/modules/buying-procurement/views/workspace.php`
- Create: `tests/buying-procurement-dashboard.php`

**Interfaces:**
- Produces: `yovel_admin_buying_procurement_dashboard_data(array $company, array $admin): array`.
- Consumes: Buying-owned supplier, settings, scorecard, and audit data; unavailable request, RFQ, order, and receipt packages remain explicit dependency or implementation states.

- [ ] **Step 1: Write failing tests for live supplier totals, score exceptions, setup progress, recent activity, and unavailable document states.**
- [ ] **Step 2: Run `php tests/buying-procurement-dashboard.php` and confirm the live provider assertions fail.**
- [ ] **Step 3: Add the provider and deepen the existing dashboard view.** Keep its current tour and shortcuts while adding real KPIs, queue, activity, alerts, reports, and Form Builder access.
- [ ] **Step 4: Run every Buying and shared test plus lint, ownership, ledger, and diff checks.**
- [ ] **Step 5: Commit only Buying-owned files and verified ledger evidence.**

### Task 4: Inventory / Warehouse Live Dashboard

**Files:**
- Create: `company/admin/modules/inventory-warehouse/dashboard.php`
- Create: `company/admin/modules/inventory-warehouse/views/dashboard.php`
- Modify: `company/admin/modules/inventory-warehouse/functions.php`
- Modify: `company/admin/modules/inventory-warehouse/views/workspace.php`
- Create: `tests/inventory-warehouse-dashboard.php`
- Extend: `tests/inventory-warehouse-ui.spec.js`

**Interfaces:**
- Produces: `yovel_admin_inventory_warehouse_dashboard_data(array $company, array $admin): array`.
- Consumes: Inventory-owned item, warehouse, bin, reorder, projected quantity, putaway, capacity, and audit services. Stock value remains `UNAVAILABLE_DEPENDENCY` until an authoritative valuation service exists.

- [ ] **Step 1: Write failing dashboard tests.** Assert live item and warehouse totals, reorder and projected-shortage queues, capacity alerts, putaway links, valuation unavailability, and company isolation.
- [ ] **Step 2: Run the focused PHP test and targeted Playwright test to establish red evidence.**
- [ ] **Step 3: Implement the provider and dashboard view.** Use existing Inventory owner functions rather than duplicate SQL where a public function exists.
- [ ] **Step 4: Extend Playwright coverage for tour, layout, Form Builder, and modal confirmation behavior.**
- [ ] **Step 5: Run all Inventory suites twice, the 10 existing UI tests plus new dashboard tests, and shared verification.**
- [ ] **Step 6: Commit only Inventory-owned files and verified ledger evidence.**

### Task 5: Manufacturing Live Dashboard

**Files:**
- Create: `company/admin/modules/manufacturing/dashboard.php`
- Modify: `company/admin/modules/manufacturing/views/dashboard.php`
- Modify: `company/admin/modules/manufacturing/functions.php`
- Create: `tests/manufacturing-dashboard.php`

**Interfaces:**
- Produces: `yovel_admin_manufacturing_dashboard_data(array $company, array $admin): array`.
- Consumes: Manufacturing settings, Form Builder, dependency gateway, and audit data. BOM, plan, work order, job card, shortage, and capacity metrics remain explicit unavailable states until their packages are implemented.

- [ ] **Step 1: Write failing tests for foundation readiness, dependency health, setup progress, activity, explicit unavailable production KPIs, and dashboard tour.**
- [ ] **Step 2: Run `php tests/manufacturing-dashboard.php` and confirm missing provider behavior.**
- [ ] **Step 3: Implement bounded Manufacturing-owned reads and upgrade the existing dashboard without inventing production records.**
- [ ] **Step 4: Run all Manufacturing suites twice, dependency-owner regressions, shared suites, lint, ledger, ownership, and diff checks.**
- [ ] **Step 5: Commit only Manufacturing-owned files and verified ledger evidence.**

### Task 6: Projects Live Dashboard

**Files:**
- Create: `company/admin/modules/projects/dashboard.php`
- Create: `company/admin/modules/projects/views/dashboard.php`
- Modify: `company/admin/modules/projects/functions.php`
- Modify: `company/admin/modules/projects/views/workspace.php`
- Create: `tests/projects-dashboard.php`

**Interfaces:**
- Produces: `yovel_admin_projects_dashboard_data(array $company, array $admin): array`.
- Consumes: Projects-owned settings, form, and audit data. Customer, employee, billing, task, milestone, time, utilization, and budget metrics use explicit availability states until owner packages exist.

- [ ] **Step 1: Write failing tests for the dashboard section, foundation status, dependency health, setup queue, Form Builder, and nonnumeric unavailable KPIs.**
- [ ] **Step 2: Run `php tests/projects-dashboard.php` and confirm failure at the absent provider/view.**
- [ ] **Step 3: Implement the provider, view, tour, and workspace dispatch using Projects-owned data only.**
- [ ] **Step 4: Run all Projects and shared suites, PHP lint, ownership scan, ledger validation, and diff check.**
- [ ] **Step 5: Commit only Projects-owned files and verified ledger evidence.**

### Task 7: Support / Service Live Dashboard

**Files:**
- Create: `company/admin/modules/support-service/dashboard.php`
- Create: `company/admin/modules/support-service/views/dashboard.php`
- Modify: `company/admin/modules/support-service/functions.php`
- Modify: `company/admin/modules/support-service/views/workspace.php`
- Create: `tests/support-service-dashboard.php`

**Interfaces:**
- Produces: `yovel_admin_support_service_dashboard_data(array $company, array $admin): array`.
- Consumes: Support settings, search source, form, and audit data. Ticket, SLA, customer, project-service, assignment, and communication metrics remain explicit unavailable states until their owner contracts exist.

- [ ] **Step 1: Write failing tests for foundation readiness, dependency states, setup actions, activity, Form Builder, and dashboard layout.**
- [ ] **Step 2: Run `php tests/support-service-dashboard.php` and confirm failure at the absent dashboard provider.**
- [ ] **Step 3: Implement the provider, view, tour, and workspace dispatch with no Sales, Projects, or Operations table access.**
- [ ] **Step 4: Run all Support, shared, and dependency-contract tests plus lint, ledger, ownership, and diff checks.**
- [ ] **Step 5: Commit only Support-owned files and verified ledger evidence.**

### Task 8: Assets / Maintenance Live Dashboard

**Files:**
- Create: `company/admin/modules/assets-maintenance/dashboard.php`
- Modify: `company/admin/modules/assets-maintenance/views/dashboard.php`
- Modify: `company/admin/modules/assets-maintenance/functions.php`
- Create: `tests/assets-maintenance-dashboard.php`

**Interfaces:**
- Produces: `yovel_admin_assets_maintenance_dashboard_data(array $company, array $admin): array`.
- Consumes: Assets-owned form and audit data plus allow-listed Inventory and HR reads. Asset records, schedules, work orders, and Finance posting metrics remain explicit unavailable states until those packages exist.

- [ ] **Step 1: Write failing tests for foundation readiness, Inventory and HR dependency health, Finance posting unavailability, setup progress, activity, and Form Builder.**
- [ ] **Step 2: Run `php tests/assets-maintenance-dashboard.php` and confirm missing live provider behavior.**
- [ ] **Step 3: Implement module-owned reads and deepen the existing dashboard without direct Inventory, HR, or Finance SQL.**
- [ ] **Step 4: Run all Assets, shared, and dependency-owner tests plus lint, ledger, ownership, and diff checks.**
- [ ] **Step 5: Commit only Assets-owned files and verified ledger evidence.**

### Task 9: Operations Live Dashboard

**Files:**
- Create: `company/admin/modules/operations/dashboard.php`
- Create: `company/admin/modules/operations/views/dashboard.php`
- Modify: `company/admin/modules/operations/functions.php`
- Modify: `company/admin/modules/operations/views/workspace.php`
- Create: `tests/operations-dashboard.php`

**Interfaces:**
- Produces: `yovel_admin_operations_dashboard_data(array $company, array $admin): array`.
- Consumes: Operations-owned jobs, attempts, workers, schedules, conflicts, alerts, deletion requests, releases, Form Builder, and audit functions.

- [ ] **Step 1: Write failing tests for queued/running/failed jobs, active alerts, unresolved conflicts, release readiness, action queue, and activity.**
- [ ] **Step 2: Run `php tests/operations-dashboard.php` and confirm the dashboard provider/view are absent.**
- [ ] **Step 3: Implement the provider and view using existing Operations services and bounded queries.**
- [ ] **Step 4: Add confirmed modal actions for retry, acknowledge, conflict resolution, and deletion approval only where existing handlers already support them.**
- [ ] **Step 5: Run all Operations suites twice, notification handoff tests, shared suites, lint, ledger, ownership, and diff checks.**
- [ ] **Step 6: Commit only Operations-owned files and verified ledger evidence.**

### Task 10: Compliance / Localization Live Dashboard

**Files:**
- Create: `company/admin/modules/compliance-localization/dashboard.php`
- Modify: `company/admin/modules/compliance-localization/views/dashboard.php`
- Modify: `company/admin/modules/compliance-localization/functions.php`
- Create: `tests/compliance-localization-dashboard.php`

**Interfaces:**
- Produces: `yovel_admin_compliance_localization_dashboard_data(array $company, array $admin): array`.
- Consumes: Compliance-owned rule sets, approvals, evidence, holds, mappings, audit, and dependency-provider state.

- [ ] **Step 1: Write failing tests for active rules, pending approvals, invalid evidence, legal holds, mapping gaps, setup progress, action queue, and recent activity.**
- [ ] **Step 2: Run `php tests/compliance-localization-dashboard.php` and confirm missing live provider behavior.**
- [ ] **Step 3: Implement the provider and deepen the existing dashboard with real WP-01/WP-02 data.**
- [ ] **Step 4: Preserve maker-checker, evidence cleanup, legal-hold, modal confirmation, and Finance contract behavior.**
- [ ] **Step 5: Run all Compliance suites twice, Finance contract tests, shared suites, lint, ledger, ownership, and diff checks.**
- [ ] **Step 6: Commit only Compliance-owned files and verified ledger evidence.**

### Task 11: Shared Default Dashboard Integration

**Files:**
- Modify: `company/admin/modules/shared/registry.php`
- Modify: `tests/company-admin-erp-contract.php`
- Create: `tests/company-admin-module-dashboards.php`

**Interfaces:**
- Consumes: all 11 module section providers and dashboard views.
- Produces: every module route defaulting to `dashboard`; unknown sections resolve to `dashboard`.

- [ ] **Step 1: Write failing shared tests.** Assert all registry defaults are `dashboard`, every section provider contains dashboard, every workspace view exists, and Android is absent.
- [ ] **Step 2: Run the shared tests and record current non-dashboard defaults as expected red evidence.**
- [ ] **Step 3: Change only the shared registry defaults.** Set HR, Sales, Buying, Inventory, Projects, Support, Assets, and Operations to `dashboard`; Finance, Manufacturing, and Compliance remain `dashboard`.
- [ ] **Step 4: Add unknown-section fallback assertions for all 11 routes.**
- [ ] **Step 5: Run shared contracts and all focused dashboard tests.** Require zero failures.
- [ ] **Step 6: Commit the shared registry and shared tests as one orchestrator-owned change.**

### Task 12: Combined Browser And Regression Verification

**Files:**
- Create: `tests/company-admin-module-dashboards-browser.mjs`
- Modify ledgers only for row-specific evidence proven by passing focused tests.

**Interfaces:**
- Consumes: authenticated routes for all 11 module dashboards.
- Produces: final desktop/mobile integration evidence and a dashboard acceptance matrix.

- [ ] **Step 1: Add an authenticated Playwright route matrix.** Visit all 11 module routes without a section and assert dashboard selection, HTTP success, no PHP fatal output, one 12/8 workspace, main-first DOM order, no horizontal overflow, and Form Builder access.
- [ ] **Step 2: Add desktop and 390x844 mobile checks.** Verify panel ratio, stacking, tour keyboard behavior, modal confirmation, Cancel/Escape retention, focus restoration, and server rehydration on representative mutating dashboards.
- [ ] **Step 3: Run every ERP PHP suite, all dashboard browser suites, PHP lint for changed files, full ledger validation, ownership scans, and `git diff --check`.**
- [ ] **Step 4: Re-run HR and Finance reference suites.** Confirm their completed dashboards and workflows did not change.
- [ ] **Step 5: Publish the acceptance matrix.** Record each module's route, provider, KPI evidence, unavailable dependencies, focused tests, browser result, and ledger counts.
- [ ] **Step 6: Dispatch the next dependency-ready parity package to each idle owner only after its dashboard package passes independent orchestrator verification.**
