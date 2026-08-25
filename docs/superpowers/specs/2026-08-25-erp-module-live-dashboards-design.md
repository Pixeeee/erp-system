# ERP Module Live Dashboards Design

**Date:** 2026-08-25
**Status:** Approved for implementation planning
**Reference:** ERPNext workspace behavior and information architecture, adapted without copying ERPNext source, text, or branding

## Objective

Give every browser-based ERP group a real operational dashboard and make that dashboard the group's default entry point. The dashboard must help an authorized company administrator understand current state, pending work, exceptions, and the next useful action without replacing the module's detailed record pages.

The 11 in-scope groups are HR Department, Accounting / Finance, Sales / CRM, Buying / Procurement, Inventory / Warehouse, Manufacturing, Projects, Support / Service, Assets / Maintenance, Operations, and Compliance / Localization. Mobile / Android remains deferred.

## Chosen Approach

Use live operational dashboards, not navigation-only hubs and not a shared generic analytics page.

Each module owns its dashboard data provider, dashboard view, tests, and module-specific language. The shared layer owns only the stable route contract and default-dashboard behavior. This preserves module boundaries while giving every group the same interaction and visual standards.

## Dashboard Composition

Every dashboard uses the established module shell:

- Main panel on the left uses 12 of 20 desktop grid units.
- Tools panel on the right uses 8 of 20 desktop grid units.
- Main panel renders before the tools panel in the DOM and stacks first on mobile.
- Panel headers remain stable while long panel bodies own scrolling.
- Page sections are unframed or separated by dividers; cards are reserved for repeated records or real tools and are never nested.

The main panel contains, in order:

1. Compact module header with company context and the primary module action.
2. Four to six live KPI summaries backed by module-owned queries or explicit owner-service contracts.
3. Action queue for pending approvals, overdue work, exceptions, or blocked records.
4. Recent activity with actor, action, record identity, status, and timestamp.
5. Module shortcuts to implemented operational destinations.
6. Reports and masters directory with unavailable destinations visibly disabled.

The right tools panel contains:

1. Setup progress and the next incomplete setup step.
2. Alerts and dependency health.
3. Quick actions that open record modals.
4. Form Builder access for the current module.
5. A manually reopenable guided tour with four to seven steps.

## Module Content

### HR Department

KPIs cover active employees, attendance exceptions, employees on leave, open onboarding or exit work, and overtime awaiting payroll ownership. The action queue prioritizes incomplete employee records, attendance corrections, onboarding, leave, and separation steps. Existing HR Form Builder behavior remains dashboard-owned and is preserved.

### Accounting / Finance

KPIs cover open receivables, open payables, cash and bank position, unposted or draft journals, and period-close readiness. The queue prioritizes approvals, reconciliation differences, overdue invoices, failed postings, and close blockers. Existing Finance dashboard and Form Builder functionality is extended without removing completed behavior.

### Sales / CRM

KPIs cover open leads, qualified opportunities when available, campaign activity, quotations, orders, and expected pipeline value. Unavailable downstream document totals render as dependency states until their owner packages exist. The queue prioritizes stale leads, follow-ups, expiring quotations, and campaign actions.

### Buying / Procurement

KPIs cover active suppliers, pending material requests, requests for quotation, purchase orders, receipts, and supplier score exceptions as each package becomes available. The queue prioritizes approvals, late receipts, unresolved scorecard issues, and supplier setup gaps.

### Inventory / Warehouse

KPIs cover active items, warehouses, low-stock or reorder recommendations, projected shortages, capacity exceptions, and stock value only after an authoritative valuation service exists. The queue prioritizes reorder actions, negative or blocked bins, putaway work, and inventory exceptions.

### Manufacturing

KPIs cover active BOMs, production plans, work orders, job cards, material shortages, and capacity exceptions as packages are implemented. Until those packages exist, the dashboard shows foundation readiness and explicit dependency blockers rather than fabricated zero values.

### Projects

KPIs cover active projects, overdue tasks, milestones at risk, unbilled work, utilization, and budget variance when their owner contracts are available. The queue prioritizes overdue tasks, blocked milestones, time-entry review, billing readiness, and project risks.

### Support / Service

KPIs cover open tickets, SLA risks, overdue tickets, assignment backlog, average response state, and service work when available. The queue prioritizes unassigned tickets, SLA breaches, customer follow-ups, escalations, and linked project work.

### Assets / Maintenance

KPIs cover active assets, maintenance due, overdue maintenance, open work orders, depreciation or finance posting readiness, and unavailable dependency states. The queue prioritizes inspections, maintenance schedules, asset movements, disposals, and posting blockers.

### Operations

KPIs cover queued jobs, running jobs, failed jobs, active alerts, unresolved conflicts, and release readiness. The queue prioritizes failed jobs, retries, conflicts, deletion approvals, and unacknowledged system alerts.

### Compliance / Localization

KPIs cover active rule sets, pending approvals, evidence nearing retention limits, legal holds, mapping gaps, and return or export readiness as packages exist. The queue prioritizes maker-checker approvals, invalid evidence, missing Finance mappings, legal holds, and filing blockers.

## Data And Ownership Contracts

Each module exposes a module-local dashboard provider returning a stable envelope:

```php
[
    'summary' => [],
    'queue' => [],
    'activity' => [],
    'setup' => [],
    'alerts' => [],
    'shortcuts' => [],
    'directories' => [],
    'dependencies' => [],
]
```

Every item carries a stable key and status. KPI values include an availability state: `AVAILABLE`, `UNAVAILABLE_DEPENDENCY`, `NOT_IMPLEMENTED`, or `ERROR`. A missing contract is never converted to a numeric zero.

Modules may query only their owned tables. Cross-module values must come from allow-listed, read-only owner callables with company scope in the request and response. Dashboard reads do not create schema or persist records.

## Actions And Forms

Every Add, New, Create, Insert, Retry, Approve, or other mutating dashboard action opens an accessible modal. Selecting Submit opens a separate sibling confirmation dialog. No database request or write begins before Confirm.

Confirmed writes must enforce authorization and CSRF protection, use parameterized SQL inside an explicit transaction, create an audit record, verify exact read-back, and roll back on any mismatch. Validation or dependency errors reopen the originating modal with server-backed values and clear feedback.

Each dashboard exposes the module's universal Form Builder. Published form versions remain immutable. Draft, publish, archive, version history, preview, stable field keys, protected system fields, and server rehydration follow the existing shared standard.

## Guided Tour

Each dashboard includes a four-to-seven-step tour targeting real interface regions: KPI summary, action queue, setup progress, shortcuts, reports or masters, alerts, and Form Builder. Escape closes the tour, focus is trapped while open, and focus returns to Show Tour. Completion or dismissal is stored per company and module, while Show Tour remains available.

## Empty, Loading, And Error States

- Empty module data explains the next valid setup action and does not look like a failure.
- Unavailable dependencies name the owning module and required contract.
- Query errors show a bounded dashboard error state without exposing SQL or secrets.
- One failed dashboard section does not prevent the remaining sections from rendering.
- Counts and activity are company-scoped and role-filtered.

## Shared Integration

The shared registry changes every web module's `default_section` to `dashboard`. Every module section registry must contain `dashboard`, and every workspace dispatcher must render a dashboard view for it. Unknown sections resolve to dashboard.

The orchestrator owns shared registry edits and shared contract tests. Module owners do not edit shared files.

## Validation

Every module dashboard package must prove:

- Dashboard is a registered section and the module's default entry point.
- Live KPIs use module-owned data or allow-listed owner services.
- Missing dependencies render explicit nonnumeric states.
- 12/8 desktop ratio and main-first mobile stacking.
- No nested cards, clipping, horizontal overflow, or overlapping text.
- Modal and sibling confirmation behavior, including zero requests before Confirm.
- Cancel and Escape retain values and restore focus.
- Server validation reopens and rehydrates the correct modal.
- Form Builder remains reachable and existing module workflows do not regress.
- Desktop and mobile authenticated browser checks pass.
- Focused PHP tests, shared contracts, ledger validation, PHP lint, ownership scan, and diff check pass.

## Delivery Sequence

1. Orchestrator adds shared dashboard route assertions only after module-local dashboard sections exist.
2. Each module owner implements its dashboard within its own directory and tests.
3. The orchestrator independently reruns focused and shared verification before accepting a dashboard.
4. After acceptance, that owner receives the next dependency-ready parity package from its existing plan.
5. Packages continue in dependency order until applicable ledger rows are complete or an explicit external owner contract blocks further work.
6. Android work is excluded from every wave.

## Completion Definition

The dashboard initiative is complete when all 11 web modules open on independently verified live operational dashboards and all shared route, interaction, browser, and ownership checks pass. Full ERPNext functional parity remains governed separately by the 1,371-row ledger and continues package by package after dashboard acceptance.
