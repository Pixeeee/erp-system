# ERP Module Orchestration Design

**Date:** 2026-08-25  
**Status:** Approved  
**Reference:** ERPNext workspace and workflow conventions, adapted to the existing BuilderX PHP ERP architecture

## Objective

Complete and connect every web ERP module registered in `bx_project_erp_groups()` while preserving verified work already present in HR Department and Accounting/Finance. The Mobile / Android Stockroom module is explicitly deferred.

The orchestration task owns cross-module coordination, shared integration, acceptance gates, and task monitoring. Existing module tasks own their module-specific implementation. All dispatched implementation work must use `gpt-5.6-sol` with `high` reasoning.

## Scope

The program covers these module groups and all registered features:

1. **HR Department:** Dashboard, Employee profiles, Departments, Job positions, Teams, Attendance, Leave requests, Leave approvals, Payroll access, Recruitment, Onboarding, Employee documents, HR reports.
2. **Accounting / Finance:** Finance Dashboard, Chart of accounts, Cost centers, Accounting dimensions, Sales invoices, Purchase invoices, Journal entries, Payment entries, Bank accounts, Bank reconciliation, Budgets, Period closing, General ledger, Profit/loss, Balance sheet, Cash flow, Tax reports.
3. **Sales / CRM:** Leads, Opportunities, Campaigns, Customers, Quotations, Sales orders, Customer credit limits, Sales analytics, Salesperson performance, Territory performance.
4. **Buying / Procurement:** Suppliers, Material requests, Request for quotation, Supplier quotations, Purchase orders, Purchase receipts, Purchase analytics, Supplier material handoff.
5. **Inventory / Warehouse:** Items, Warehouses, Stock entries, Stock ledger, Stock reconciliation, Batch numbers, Serial numbers, Barcode records, Reorder levels, Putaway, Picking, Packing, Shipment, Stock balance reports, Traceability reports.
6. **Manufacturing:** BOM, Production plan, Work orders, Job cards, Material requirements planning, Production forecasting, Quality inspection handoff, Work order reports.
7. **Projects:** Projects, Project tasks, Timesheets, Project collaboration, Project summaries, Delayed task reports, Customer portal access, Project portal access.
8. **Support / Service:** Issues/tickets, SLA rules, Warranty claims, First response tracking, Issue summaries, Customer support portal.
9. **Assets / Maintenance:** Asset records, Asset depreciation schedule, Fixed asset register, Maintenance schedules, Quality inspection, Maintenance reports.
10. **Operations:** Scheduled jobs, Notifications, Background workers, Sync conflict dashboard, Import/export jobs, System alerts, Release checklist.
11. **Compliance / Localization:** Tax templates, VAT settings, E-invoice reports, Regional compliance reports, Audit evidence, Regulatory exports.

`Mobile / Android Stockroom` and all Android application work are excluded from this program.

## Preservation And Gap-Only Rule

HR Department and Accounting/Finance are active reference implementations, not rebuild targets.

- Inventory their routes, behaviors, persistence services, tests, and active uncommitted work before editing.
- Classify each registered feature as complete, incomplete, queued, missing, or failing.
- Preserve complete behavior and files unless a backward-compatible change is required by the approved shared contract.
- Implement only incomplete, queued, missing, or failing behavior.
- Do not replace existing module architecture merely to make it resemble a new module.
- Run existing HR and Finance tests before and after gap work.

## Architecture And Ownership

Each module owns a dedicated folder beneath `company/admin/modules/`, its company-scoped tables, module-specific services, views, workflows, and focused tests. A module exposes stable service boundaries for other modules instead of allowing direct writes to its authoritative tables.

The orchestration task controls shared integration surfaces:

- `app/foundation.php` module registry and shared contracts
- application bootstrap and controller routing
- sidebar and module navigation
- shared permissions and authorization rules
- shared styles and JavaScript behavior
- universal Form Builder components and schema contracts
- cross-module projections and integration tests

Module builders may describe required shared changes but must not concurrently edit shared integration files unless the orchestration plan assigns them that write scope.

## Connected Business Flows

- Sales quotations and orders may initiate procurement, inventory reservation, manufacturing, project delivery, invoicing, and later support history.
- Buying receipts update Inventory. Supplier invoices and payments are owned by Accounting/Finance.
- Inventory is authoritative for stock quantities, valuation movements, locations, batches, serial numbers, and traceability.
- Manufacturing consumes and produces stock through Inventory services using BOMs, production plans, work orders, and job cards.
- Projects connect customers, tasks, timesheets, delivery milestones, expenses, and billing without duplicating authoritative Sales or Finance records.
- Assets connect purchasing, capitalization, depreciation, inspections, maintenance, and Finance postings.
- HR is authoritative for employees, organization structure, attendance, leave, recruitment, onboarding, and payroll access.
- Accounting/Finance is authoritative for general-ledger postings, taxes, receivables, payables, cash, budgets, closing, and financial statements.
- Operations owns background execution, notifications, imports, synchronization, system alerts, and release readiness.
- Compliance/Localization supplies effective-dated tax and localization rules, e-invoice reporting, audit evidence, and regulatory exports. It does not rewrite historical posted documents.

Cross-module links use stable keys, company scoping, explicit statuses, and documented lifecycle transitions. Authoritative records remain in their owning module.

## Universal Form Builder Standard

Every ERP module includes a Form Builder based on the proven HR and Accounting/Finance implementations. It is a shared platform capability with module-specific adapters rather than separate copied implementations.

Each module builder provides:

- a target selector for module features and record types;
- drag-and-drop rows, columns, sections, and fields;
- field labels, supported types, options, required state, visibility, width, defaults, and validation;
- protected system fields required by business workflows;
- stable field keys that survive reordering and layout changes;
- preview, draft, publish, archive, and version-history workflows;
- immutable published versions and version-bound submitted records;
- company scoping, authorization, audit history, transaction ownership, and direct read-back verification;
- server rehydration after validation failures and clear success or failure feedback;
- module adapters that define valid targets, required fields, persistence mappings, and workflow constraints.

HR and Accounting/Finance are audited against this contract. Existing capabilities remain intact, and only missing capabilities or safe compatibility improvements are added.

## Two-Panel Interface Contract

Every ERP module workspace and feature page uses a 20-column desktop composition:

- **Left main panel: 12 columns.** Primary records, tables, forms, dashboards, reports, timelines, layout canvas, previews, and workflow content.
- **Right utility panel: 8 columns.** Feature navigation, filters, summaries, state, shortcuts, Form Builder tools, field settings, linked records, activity, and context-sensitive actions.

The utility panel may remain sticky and scroll independently where appropriate. On narrower screens the panels stack, with the main panel first. Interfaces must not create page-level horizontal scrolling, clipped text, overlapping controls, or unstable dimensions.

Context determines panel content:

- Form Builder: layout canvas and preview on the left; toolbox and selected-field settings on the right.
- Record workflows: list or detail on the left; state, actions, links, and activity on the right.
- Reports: results and charts on the left; filters, export, and report settings on the right.

Existing HR and Accounting/Finance screens are preserved and aligned only where they do not already satisfy the contract.

## Continuous Improvement Authority

Builders may immediately implement a better idea discovered during development when all of these conditions hold:

- it remains inside the assigned module's business scope;
- it is backward-compatible with completed behavior and persisted data;
- it clearly improves correctness, usability, accessibility, integration, security, performance, or maintainability;
- it includes proportionate tests and verification;
- it does not take ownership of another module's authoritative data;
- it does not introduce an unrelated platform or speculative feature.

Improvements affecting shared architecture or another module are routed through the orchestration task and implemented in the correct integration stage. Destructive migrations, security-policy changes, and accounting, tax, payroll, or legal rule changes require authoritative verification and explicit coordination before adoption.

## Persistence And Error Handling

Every persisted create, update, submit, cancel, archive, import, and cross-module projection must:

1. authorize the administrator and company scope;
2. validate and normalize server-side input;
3. use parameterized SQL and schema-safe identifiers;
4. obtain lifecycle locks where concurrent mutation could conflict;
5. begin one ADODB transaction owned by the public workflow boundary;
6. write the authoritative record, related rows, cross-module projections, and audit events inside that transaction;
7. directly read back critical saved state before commit;
8. commit only after every required write and verification succeeds;
9. roll back the complete operation on failure;
10. return actionable feedback and rehydrate valid submitted values.

Submitted accounting, stock, tax, and other controlled records are not silently rewritten. Corrections use explicit reversal, cancellation, amendment, or versioned replacement behavior.

## Definition Of Done

A registered feature is complete only when:

- its sidebar link and route resolve to a real workspace;
- expected empty, loading, populated, validation, success, failure, unauthorized, and archived states work;
- create, read, update, lifecycle, filtering, and reporting behaviors appropriate to the feature are functional;
- persisted workflows satisfy the transaction and read-back contract;
- authorization and company isolation are enforced on reads and writes;
- confirmation occurs before each form submission or consequential lifecycle action;
- audit history identifies the actor, company, action, and record;
- the universal Form Builder supports the feature's configurable record types;
- the 12/8 panel contract works on desktop and stacks correctly on mobile;
- cross-module dependencies use owning-module services and stable keys;
- focused unit or service tests, integration tests, syntax checks, and live route checks pass;
- no placeholder, queued, decorative-only, or non-persisted screen remains for that feature.

## Test Strategy

Each module supplies focused tests for schema idempotency, company isolation, authorization, validation, lifecycle transitions, rollback, audit logging, read-back verification, Form Builder versioning, and its core business calculations.

Integration tests cover the principal cross-module flows:

- Sales order to procurement, reservation, fulfillment, invoice, and project delivery;
- Purchase receipt to stock and supplier invoice matching;
- Manufacturing material issue and finished-goods receipt;
- Asset acquisition, capitalization, depreciation, and maintenance;
- Timesheet or project milestone to billable Finance records;
- Compliance rules to current transactions without historical mutation;
- Operations jobs to idempotent, retryable module services.

The final gate includes relevant existing regression suites, PHP syntax checks, `git diff --check`, live route responses, browser checks of the two-panel layout at desktop and mobile sizes, and database cleanup assertions for test fixtures.

## Rollout And Coordination

Implementation proceeds in dependency-aware waves:

1. Allow the active Accounting/Finance task to complete its current approved plan; audit HR and Finance gaps.
2. Establish or extract the shared Form Builder contract and shared module integration boundaries without regressing the reference modules.
3. Build the Sales/CRM, Buying/Procurement, and Inventory/Warehouse foundations.
4. Connect Manufacturing, Projects, Support/Service, and Assets/Maintenance.
5. Complete Operations and Compliance/Localization.
6. Finish HR and Finance gaps, cross-module workflows, responsive interface checks, regression suites, and live verification.

Module-private work may proceed concurrently when write scopes are disjoint. Shared-file changes are sequenced by the orchestration task. Each module task reports completed features, improvements implemented, files changed, tests run, outstanding risks, and requested shared integration changes.

The orchestration task monitors progress, reviews outcomes, redirects incomplete work, coordinates shared changes, and does not accept self-reported completion without verification evidence.

## Reference Policy

ERPNext is a workflow and information-architecture reference, not a source to copy blindly. Builders should adopt relevant workspace organization, lifecycle concepts, and domain expectations while following the existing BuilderX architecture, ADODB persistence conventions, shadcn-style controls, permissions, and visual language.

The supplied ERPNext demo currently requires authentication. Where the demo cannot be inspected, builders use official ERPNext documentation or source and the existing HR and Accounting/Finance implementations as the enforceable local standard.

## Explicit Exclusions

- Android application screens, APIs dedicated only to Android, offline mobile synchronization, and scanner workflows
- replacement of completed HR or Accounting/Finance behavior without a verified need
- unrelated platform refactors
- decorative placeholders presented as completed features
- direct cross-module table writes that bypass the owning module's service contract
