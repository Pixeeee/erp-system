# Operations ERPNext Parity Gap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the missing BuilderX Operations module capabilities represented by the pinned Operations parity ledger, without duplicating or directly mutating data owned by another module.

**Architecture:** Operations owns module-local workspaces, configuration records, job execution, notifications, bulk/import/export orchestration, sync conflicts, alerts, and release readiness. Setup rows whose authoritative records belong to Platform, HR, Sales, Buying, Inventory, Finance, Assets, or shared infrastructure are rendered through documented owner contracts; Operations never writes another owner's tables. The module consumes orchestration-owned routing, modal/confirmation, and universal Form Builder contracts after those shared contracts are available.

**Tech Stack:** PHP 8.5 runtime with PHP 8.1 compatibility, ADODB/MySQL, server-rendered PHP, Tailwind 4 and existing shadcn tokens, vanilla JavaScript through orchestration-owned shared modal/Form Builder behavior, PHP executable tests, and authenticated route/browser checks.

## Global Constraints

- Baseline: ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325`.
- Operations may modify only `company/admin/modules/operations/`, `tests/operations-*`, its ledger, and this plan unless the orchestrator later grants an exact path-limited delegation.
- Preserve every existing user and other-task change. Do not replace completed behavior.
- Use the universal module-local Form Builder adapter with target selection, rows, columns, sections, fields, preview, draft, publish, archive, immutable versions, version-bound submissions, company scope, authorization, audit, read-back verification, and server rehydration.
- Every workspace and feature page uses a 20-column desktop composition: left main panel `12/20`, right actions/tools panel `8/20`; stack main-first on smaller screens.
- Every Add, New, Create, or Insert command opens an accessible body-owned modal.
- Submit opens a separate confirmation dialog. No request, job enqueue, or database mutation may begin until Confirm.
- Every write must enforce authorization and company scope, validate server-side input, use parameterized SQL, own one ADODB transaction at the public workflow boundary, write an audit event, directly read back exact saved state before commit, roll back on any mismatch, and rehydrate from server state.
- Module-local backward-compatible improvements may be implemented with proportionate tests. Shared or cross-module improvements must be sent to the orchestrator.
- Implement functional parity without copying ERPNext GPL source, text, branding, logos, or assets.
- Android and mobile Stockroom work remains deferred and is outside this plan.

---

## Audit Snapshot

The 2026-08-25 audit found no `company/admin/modules/operations/` directory and no `tests/operations-*` files. The shared module catalog has an Operations label and feature list, but a label is not a module implementation and has no focused passing evidence.

| Status | Count | Audit conclusion |
|---|---:|---|
| `COMPLETE` | 0 | No row has both exact local implementation files and passing Operations tests. |
| `PARTIAL` | 0 | No module-local implementation exists to classify as partial. |
| `MISSING` | 57 | Every applicable pinned capability is absent. |
| `NOT_APPLICABLE` | 3 | Three `test_records.json` paths are upstream fixture arrays, not runtime capabilities. |
| `DEFERRED` | 0 | No Android row is present in the Operations ledger. |

The three `NOT_APPLICABLE` rows are the Company, Currency Exchange, and Item Group `test_records.json` fixture files. Their corresponding runtime DocType rows remain `MISSING`.

## Planned File Map

- Create `company/admin/modules/operations/functions.php`: sections, feature metadata, action dispatch boundary, and workspace data composition.
- Create `company/admin/modules/operations/contracts.php`: allow-listed dependency contract catalog, read adapters, joinable command adapters, and fail-closed contract errors.
- Create `company/admin/modules/operations/schema.php`: idempotent Operations-owned tables, keys, indexes, ownership, statuses, and timestamps.
- Create `company/admin/modules/operations/forms.php`: Operations universal Form Builder adapter, schema normalization, immutable versions, publish/archive lifecycle, submissions, and read-back.
- Create `company/admin/modules/operations/jobs.php`: scheduled jobs, worker claims, idempotency, retries, leases, progress, cancellation, alerts, and audit events.
- Create `company/admin/modules/operations/bulk.php`: bulk/import/export logs and joinable owner-command orchestration.
- Create `company/admin/modules/operations/deletion.php`: dry-run, approval, submit, cancel, and owner-mediated governed deletion.
- Create `company/admin/modules/operations/setup.php`: Operations-owned setup records and read-only owner-backed setup projections.
- Create `company/admin/modules/operations/communication.php`: communication media, timeslots, notification/digest definitions, recipients, and schedules.
- Create `company/admin/modules/operations/integrations.php`: EDI code lists, secret-reference settings, bank-sync job handoff, and transport status.
- Create `company/admin/modules/operations/utilities.php`: portal publication settings, owner-mediated rename, video metadata/settings, and interaction reporting.
- Create `company/admin/modules/operations/views/workspace.php`: ERP workspace shell and section dispatcher.
- Create `company/admin/modules/operations/views/record-modal.php`: module-local record modal body that consumes the shared modal contract.
- Create `company/admin/modules/operations/views/sections/dashboard.php`, `setup.php`, `jobs.php`, `sync.php`, `import-export.php`, `alerts.php`, `release.php`, `bulk.php`, `deletion.php`, `authorization.php`, `company-defaults.php`, `workforce.php`, `calendars.php`, `commercial.php`, `catalog.php`, `fleet.php`, `notifications.php`, `integrations.php`, and `utilities.php`: feature-specific 12/8 panels.
- Create `tests/operations-test-helper.php`: isolated company/admin fixtures, dependency-provider stubs, assertion helpers, and cleanup.
- Create `tests/operations-contracts.php`, `operations-workspace.php`, `operations-form-builder.php`, `operations-jobs.php`, `operations-registered-features.php`, `operations-bulk.php`, `operations-governed-deletion.php`, `operations-authorization-setup.php`, `operations-company-defaults.php`, `operations-workforce-calendar.php`, `operations-commercial-masters.php`, `operations-catalog-units.php`, `operations-fleet.php`, `operations-communication.php`, `operations-email-digests.php`, `operations-edi.php`, `operations-bank-integration.php`, `operations-portal-utilities.php`, `operations-rename.php`, and `operations-video.php`: focused contract, persistence, lifecycle, and UI-source verification.

No task in this plan may edit a shared lock path. The orchestrator must separately wire module loading, route allow-listing, controller data/action dispatch, layout inclusion, shared modal behavior, shared Form Builder behavior, and navigation.

## Dependency Contract Rules

All dependencies listed in the ledger are contracts, not permission to query or update an owner's tables.

### Common Envelopes

Read-only directory contracts use:

```php
callable(array $company, array $query): array
// Returns: ['ok' => bool, 'company_key_hash' => string, 'records' => array, 'errors' => array]
```

Owner command contracts use the Operations-owned outer transaction and never commit independently:

```php
callable(ADOConnection $db, array $company, array $admin, array $command): array
// Returns: ['ok' => bool, 'owner_record_key' => string, 'status' => string,
//           'idempotency_key' => string, 'read_back' => array, 'audit_key' => string,
//           'errors' => array]
```

Operations resolves only allow-listed contracts through:

```php
function yovel_admin_operations_dependency_contracts(): array;
function yovel_admin_operations_call_read_contract(
    string $contractId,
    array $company,
    array $query,
    array $providers = []
): array;
function yovel_admin_operations_call_command_contract(
    string $contractId,
    ADOConnection $db,
    array $company,
    array $admin,
    array $command,
    array $providers = []
): array;
```

An absent provider, wrong company hash, unstable/missing record key, failed read-back, or mismatched idempotency key is a hard failure. Operations rolls back and reports the owning contract ID; it never falls back to direct SQL.

### Contract Catalog

| Owner | Contract IDs | Required behavior |
|---|---|---|
| Orchestrator/shared UI | `shared.module-routing.v1`, `shared.modal-confirmation.v1`, `shared.form-builder.v1` | Load the Operations route and data/actions, render accessible sibling dialogs, preserve submitter and form values, prevent writes before Confirm, and expose the universal Form Builder primitives. |
| Shared identity/security | `shared.identity-directory.v1`, `shared.identity-address-directory.v1`, `shared.identity-permission-directory.v1`, `shared.record-type-directory.v1` | Company-safe users, roles, permissions, addresses, and record-type metadata with stable keys; no secrets in UI payloads. |
| Shared platform services | `shared.attachment-service.v1`, `shared.audit-log.v1`, `shared.portal-publication.v1`, `shared.secret-store.v1`, `shared.external-provider-secrets.v1` | Attachment references, transaction-joined audit events, portal publication status, and opaque secret references. Raw secrets never enter views, logs, or ledger evidence. |
| Platform | `platform.company-scope.v1`, `platform.company-directory.v1`, `platform.company-branch-directory.v1` | Resolve current company/branch records and validate stable company scope. Company and branch mutations stay with Platform/orchestration. |
| HR | `hr.workforce-directory.v1`, `hr.calendar-directory.v1`, `hr.employee-group-directory.v1` | Read employees, departments, designations, histories, groups, holidays, and holiday lists by stable company-scoped keys. HR remains authoritative. |
| Accounting/Finance | `accounting-finance.company-defaults.v1`, `accounting-finance.currency-directory.v1`, `accounting-finance.currency-exchange.v1`, `accounting-finance.party-account-directory.v1`, `accounting-finance.planning-directory.v1`, `accounting-finance.tax-directory.v1`, `accounting-finance.bank-sync.v1` | Read finance references/defaults and invoke a transaction-joinable bank-sync command. Operations stores only its own job/configuration state. |
| Sales/CRM | `sales-crm.commercial-masters.v1`, `sales-crm.trade-terms.v1` | Read customer groups, territories, sales people/partners, quotation reasons, targets, and selling trade terms by stable keys. |
| Buying/Procurement | `buying-procurement.supplier-directory.v1`, `buying-procurement.supplier-masters.v1`, `buying-procurement.trade-terms.v1` | Read suppliers, supplier groups, and buying trade terms. Supplier records remain Buying-owned. |
| Inventory/Warehouse | `inventory-warehouse.catalog-directory.v1`, `inventory-warehouse.company-defaults.v1`, `inventory-warehouse.unit-directory.v1` | Read item groups, brands, item attributes/defaults, warehouses, and units. Inventory remains authoritative. |
| Assets/Maintenance | `assets-maintenance.company-defaults.v1`, `assets-maintenance.fleet-directory.v1` | Read asset-account defaults, vehicles, drivers, and license categories; fleet mutation remains Assets-owned. |
| All module owners | `owners.workspace-directory.v1`, `owners.document-lifecycle-catalog.v1`, `owners.digest-metrics.v1` | Provide real route metadata, allowed lifecycle actions, and company-scoped digest snapshots without placeholder links or cross-table reads. |
| All module owners | `owners.bulk-command.v1`, `owners.governed-delete.v1`, `owners.governed-rename.v1` | Join the Operations transaction; validate authorization/locks; apply an idempotent owner-side command; audit and read back exact state; never commit independently. |
| Operations internal | `operations.job-runner.v1`, `operations.edi-code-directory.v1` | Queue/claim/retry/cancel jobs and resolve Operations-owned EDI code lists with company isolation and immutable attempt history. |

The three ledger dependencies that are ERPNext source paths, rather than contract IDs, connect each fixture-only row to its applicable parent runtime row.

## Ledger Traceability

Every applicable row is assigned to exactly one work package. The commit is pinned globally above, so these exact source IDs are sufficient to locate each audited identity.

### OP-01: Workspace Shell And Form Builder

- `erpnext:erpnext/setup/workspace/erpnext_settings/erpnext_settings.json:workspace:ERPNext Settings`
- `erpnext:erpnext/setup/workspace/home/home.json:workspace:Home`

### OP-02: Authorization, Company, And Defaults

- `erpnext:erpnext/setup/doctype/authorization_control/authorization_control.json:doctype:Authorization Control`
- `erpnext:erpnext/setup/doctype/authorization_rule/authorization_rule.json:doctype:Authorization Rule`
- `erpnext:erpnext/setup/doctype/branch/branch.json:doctype:Branch`
- `erpnext:erpnext/setup/doctype/company/company.json:doctype:Company`
- `erpnext:erpnext/setup/doctype/global_defaults/global_defaults.json:doctype:Global Defaults`

### OP-03: Workforce And Calendars

- `erpnext:erpnext/setup/doctype/department/department.json:doctype:Department`
- `erpnext:erpnext/setup/doctype/designation/designation.json:doctype:Designation`
- `erpnext:erpnext/setup/doctype/employee/employee.json:doctype:Employee`
- `erpnext:erpnext/setup/doctype/employee_education/employee_education.json:doctype:Employee Education`
- `erpnext:erpnext/setup/doctype/employee_external_work_history/employee_external_work_history.json:doctype:Employee External Work History`
- `erpnext:erpnext/setup/doctype/employee_group/employee_group.json:doctype:Employee Group`
- `erpnext:erpnext/setup/doctype/employee_group_table/employee_group_table.json:doctype:Employee Group Table`
- `erpnext:erpnext/setup/doctype/employee_internal_work_history/employee_internal_work_history.json:doctype:Employee Internal Work History`
- `erpnext:erpnext/setup/doctype/holiday/holiday.json:doctype:Holiday`
- `erpnext:erpnext/setup/doctype/holiday_list/holiday_list.json:doctype:Holiday List`

### OP-04: Commercial And Trading Masters

- `erpnext:erpnext/setup/doctype/currency_exchange/currency_exchange.json:doctype:Currency Exchange`
- `erpnext:erpnext/setup/doctype/customer_group/customer_group.json:doctype:Customer Group`
- `erpnext:erpnext/setup/doctype/incoterm/incoterm.json:doctype:Incoterm`
- `erpnext:erpnext/setup/doctype/party_type/party_type.json:doctype:Party Type`
- `erpnext:erpnext/setup/doctype/quotation_lost_reason/quotation_lost_reason.json:doctype:Quotation Lost Reason`
- `erpnext:erpnext/setup/doctype/quotation_lost_reason_detail/quotation_lost_reason_detail.json:doctype:Quotation Lost Reason Detail`
- `erpnext:erpnext/setup/doctype/sales_partner/sales_partner.json:doctype:Sales Partner`
- `erpnext:erpnext/setup/doctype/sales_person/sales_person.json:doctype:Sales Person`
- `erpnext:erpnext/setup/doctype/supplier_group/supplier_group.json:doctype:Supplier Group`
- `erpnext:erpnext/setup/doctype/target_detail/target_detail.json:doctype:Target Detail`
- `erpnext:erpnext/setup/doctype/terms_and_conditions/terms_and_conditions.json:doctype:Terms and Conditions`
- `erpnext:erpnext/setup/doctype/territory/territory.json:doctype:Territory`

### OP-05: Catalog And Units

- `erpnext:erpnext/setup/doctype/brand/brand.json:doctype:Brand`
- `erpnext:erpnext/setup/doctype/item_group/item_group.json:doctype:Item Group`
- `erpnext:erpnext/setup/doctype/uom/uom.json:doctype:UOM`
- `erpnext:erpnext/setup/doctype/uom_conversion_factor/uom_conversion_factor.json:doctype:UOM Conversion Factor`

### OP-06: Fleet And Delivery

- `erpnext:erpnext/setup/doctype/driver/driver.json:doctype:Driver`
- `erpnext:erpnext/setup/doctype/driving_license_category/driving_license_category.json:doctype:Driving License Category`
- `erpnext:erpnext/setup/doctype/vehicle/vehicle.json:doctype:Vehicle`

### OP-07: Communication And Digests

- `erpnext:erpnext/communication/doctype/communication_medium/communication_medium.json:doctype:Communication Medium`
- `erpnext:erpnext/communication/doctype/communication_medium_timeslot/communication_medium_timeslot.json:doctype:Communication Medium Timeslot`
- `erpnext:erpnext/setup/doctype/email_digest/email_digest.json:doctype:Email Digest`
- `erpnext:erpnext/setup/doctype/email_digest_recipient/email_digest_recipient.json:doctype:Email Digest Recipient`

### OP-08: Jobs, Bulk Processing, And Governed Deletion

- `erpnext:erpnext/bulk_transaction/doctype/bulk_transaction_log/bulk_transaction_log.json:doctype:Bulk Transaction Log`
- `erpnext:erpnext/bulk_transaction/doctype/bulk_transaction_log_detail/bulk_transaction_log_detail.json:doctype:Bulk Transaction Log Detail`
- `erpnext:erpnext/setup/doctype/transaction_deletion_record/transaction_deletion_record.json:doctype:Transaction Deletion Record`
- `erpnext:erpnext/setup/doctype/transaction_deletion_record_item/transaction_deletion_record_item.json:doctype:Transaction Deletion Record Item`
- `erpnext:erpnext/setup/doctype/transaction_deletion_record_to_delete/transaction_deletion_record_to_delete.json:doctype:Transaction Deletion Record To Delete`

### OP-09: EDI And Bank Integration Settings

- `erpnext:erpnext/edi/doctype/code_list/code_list.json:doctype:Code List`
- `erpnext:erpnext/edi/doctype/common_code/common_code.json:doctype:Common Code`
- `erpnext:erpnext/erpnext_integrations/doctype/plaid_settings/plaid_settings.json:doctype:Plaid Settings`

### OP-10: Portal And Media Utilities

- `erpnext:erpnext/portal/doctype/website_attribute/website_attribute.json:doctype:Website Attribute`
- `erpnext:erpnext/portal/doctype/website_filter_field/website_filter_field.json:doctype:Website Filter Field`
- `erpnext:erpnext/setup/doctype/website_item_group/website_item_group.json:doctype:Website Item Group`
- `erpnext:erpnext/utilities/doctype/portal_user/portal_user.json:doctype:Portal User`
- `erpnext:erpnext/utilities/doctype/rename_tool/rename_tool.json:doctype:Rename Tool`
- `erpnext:erpnext/utilities/doctype/video/video.json:doctype:Video`
- `erpnext:erpnext/utilities/doctype/video_settings/video_settings.json:doctype:Video Settings`
- `erpnext:erpnext/utilities/report/youtube_interactions/youtube_interactions.json:report:YouTube Interactions`
- `erpnext:erpnext/utilities/report/youtube_interactions/youtube_interactions.py:report_code:Youtube Interactions`

### Fixture-Only Rows

- `erpnext:erpnext/setup/doctype/company/test_records.json:doctype:Test Records`
- `erpnext:erpnext/setup/doctype/currency_exchange/test_records.json:doctype:Test Records`
- `erpnext:erpnext/setup/doctype/item_group/test_records.json:doctype:Test Records`

---

### Task 1: OP-01 Module Foundation, Workspace, And Universal Form Builder

**Files:**
- Create: `company/admin/modules/operations/functions.php`
- Create: `company/admin/modules/operations/contracts.php`
- Create: `company/admin/modules/operations/schema.php`
- Create: `company/admin/modules/operations/forms.php`
- Create: `company/admin/modules/operations/views/workspace.php`
- Create: `company/admin/modules/operations/views/record-modal.php`
- Create: `company/admin/modules/operations/views/sections/dashboard.php`
- Create: `company/admin/modules/operations/views/sections/setup.php`
- Create: `tests/operations-test-helper.php`
- Create: `tests/operations-contracts.php`
- Create: `tests/operations-workspace.php`
- Create: `tests/operations-form-builder.php`

**Interfaces:**
- Produces: `yovel_admin_operations_sections(): array`, `yovel_admin_operations_section(): string`, `yovel_admin_operations_data(array $company, array $admin, array $providers = []): array`.
- Produces: the three dependency-call functions defined under Common Envelopes.
- Produces: `yovel_admin_operations_builder_targets(): array`, `yovel_admin_normalize_operations_builder_schema(string $target, array $schema): array`, `yovel_admin_persist_operations_builder_form(ADOConnection $db, array $company, array $admin, array $input): array`.
- Consumes: `shared.module-routing.v1`, `shared.modal-confirmation.v1`, `shared.form-builder.v1`, `platform.company-scope.v1`, `owners.workspace-directory.v1`.

- [ ] **Step 1: Write failing contract, workspace, and Form Builder tests**

Assert the contract allow-list contains every ledger contract ID and rejects unknown IDs. Assert the workspace exposes the registered Operations sections, a setup/tour area, real shortcuts only, and a `grid-cols-20` composition with left `col-span-12` and right `col-span-8`, stacking main-first at narrow widths. Assert every create trigger targets a body-owned record modal and Submit targets a sibling confirmation dialog. Assert Form Builder protects system fields, preserves stable row/column/field keys across reorder, supports draft/publish/archive, writes immutable versions, pins submissions to a version, verifies read-back, and rehydrates saved values.

- [ ] **Step 2: Run tests and confirm missing-file failures**

```bash
php tests/operations-contracts.php
php tests/operations-workspace.php
php tests/operations-form-builder.php
```

Expected: each exits non-zero because the Operations module does not exist.

- [ ] **Step 3: Implement module-local schema, contract client, and Form Builder adapter**

Create only `project_company_operations_*` tables. Use fixed identifiers, explicit unique keys, company hash ownership, stable UUID business keys, status/timestamps, ADODB parameterized SQL, and idempotent schema creation. Every public Form Builder write owns one transaction, writes audit inside it, reads back every persisted field/version key, commits only on an exact match, and returns a server payload used to rehydrate the form.

- [ ] **Step 4: Implement the workspace and modal bodies**

Render the main workspace and all feature records in the left 12 columns. Render feature navigation, actions, filters, summaries, Form Builder tools, and selected-field settings in the right 8 columns. Do not nest bordered cards. Add/New/Create/Insert buttons open the record modal; shared Submit-to-Confirm behavior is consumed, not copied.

- [ ] **Step 5: Verify OP-01 and record the shared integration request**

```bash
php tests/operations-contracts.php
php tests/operations-workspace.php
php tests/operations-form-builder.php
find company/admin/modules/operations -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check -- company/admin/modules/operations tests/operations-*
```

Expected: all module-local checks pass. Send the orchestrator the exact load/route/controller/layout and shared modal/Form Builder wiring request; do not edit the shared paths.

### Task 2: Operations Job Runner And Registered Operational Surfaces

**Files:**
- Create: `company/admin/modules/operations/jobs.php`
- Create: `company/admin/modules/operations/views/sections/jobs.php`
- Create: `company/admin/modules/operations/views/sections/sync.php`
- Create: `company/admin/modules/operations/views/sections/import-export.php`
- Create: `company/admin/modules/operations/views/sections/alerts.php`
- Create: `company/admin/modules/operations/views/sections/release.php`
- Create: `tests/operations-jobs.php`
- Create: `tests/operations-registered-features.php`

**Interfaces:**
- Produces: `yovel_admin_operations_enqueue_job(ADOConnection $db, array $company, array $admin, array $input): array`.
- Produces: `yovel_admin_operations_claim_job(ADOConnection $db, array $company, string $workerKey, int $leaseSeconds): ?array`.
- Produces: `yovel_admin_operations_complete_job(ADOConnection $db, array $company, array $admin, string $jobKey, string $workerKey, array $result): array`.
- Produces: `yovel_admin_operations_fail_job(ADOConnection $db, array $company, array $admin, string $jobKey, string $workerKey, string $errorSummary, bool $retryable): array`.
- Produces: `yovel_admin_operations_cancel_job(ADOConnection $db, array $company, array $admin, string $jobKey, string $reason): array`.
- Produces: `operations.job-runner.v1` for later packages.

- [ ] **Step 1: Write failing job lifecycle and feature-surface tests**

Cover scheduled jobs, notifications handoff, worker leases, lease expiry, idempotent enqueue, bounded retry/backoff, cancellation, sync conflicts, import/export jobs, system alerts, and release checklist state. Assert unauthorized/cross-company access fails, attempt history is immutable, and no worker can claim the same job twice.

- [ ] **Step 2: Run the focused tests and verify missing interfaces**

```bash
php tests/operations-jobs.php
php tests/operations-registered-features.php
```

- [ ] **Step 3: Implement transaction-owned queue, attempts, alerts, conflicts, and release checks**

Use lifecycle states `QUEUED`, `RUNNING`, `SUCCEEDED`, `FAILED`, `CANCELLED`; stable idempotency keys; compare-and-set worker claims; explicit lease timestamps; immutable attempt rows; and audit/read-back before commit. Release checks are persisted check results with actor, company, timestamp, evidence summary, and status, not decorative checklist text.

- [ ] **Step 4: Implement 12/8 views and modal-confirmed commands**

Keep job lists, conflict tables, import/export history, and release results in the main panel. Put filters, run/retry/cancel actions, worker health, and contextual tools in the utility panel. Every run/retry/cancel/import/export action opens confirmation and starts no mutation before Confirm.

- [ ] **Step 5: Verify job idempotency twice**

```bash
php tests/operations-jobs.php
php tests/operations-jobs.php
php tests/operations-registered-features.php
```

Expected: both job runs pass and leave no fixture rows.

### Task 3: OP-08 Bulk Processing And Governed Deletion

**Files:**
- Create: `company/admin/modules/operations/bulk.php`
- Create: `company/admin/modules/operations/deletion.php`
- Create: `company/admin/modules/operations/views/sections/bulk.php`
- Create: `company/admin/modules/operations/views/sections/deletion.php`
- Create: `tests/operations-bulk.php`
- Create: `tests/operations-governed-deletion.php`

**Interfaces:**
- Produces: `yovel_admin_operations_submit_bulk_job(ADOConnection $db, array $company, array $admin, array $input, array $providers = []): array`.
- Produces: `yovel_admin_operations_bulk_log(array $company, string $logKey): ?array`.
- Produces: `yovel_admin_operations_plan_deletion(array $company, array $admin, array $input, array $providers = []): array`.
- Produces: `yovel_admin_operations_submit_deletion(ADOConnection $db, array $company, array $admin, array $input, array $providers = []): array`.
- Produces: `yovel_admin_operations_cancel_deletion(ADOConnection $db, array $company, array $admin, string $requestKey, string $reason): array`.
- Consumes: `shared.record-type-directory.v1`, `owners.bulk-command.v1`, `owners.governed-delete.v1`, `operations.job-runner.v1`, `shared.audit-log.v1`.

- [ ] **Step 1: Write failing bulk and deletion transaction tests**

Assert dry-run plans enumerate owner contracts without touching owner data; Confirm enqueues exactly once; each owner command joins the outer transaction; any owner failure rolls back the Operations log and every joined write; retry uses the same idempotency key; cancellation is explicit; and completed logs expose per-record status without raw SQL or secret details.

- [ ] **Step 2: Run the tests and confirm the OP-08 gap**

```bash
php tests/operations-bulk.php
php tests/operations-governed-deletion.php
```

- [ ] **Step 3: Implement logs, details, dry-run plans, and status transitions**

Store only Operations-owned job/log/request/detail rows. Never issue `DELETE`, `UPDATE`, or `INSERT` against another module's table. A deletion request becomes `QUEUED`, `RUNNING`, `FAILED`, `COMPLETED`, or `CANCELLED`; its child rows record owner contract, stable target key, action result, and read-back evidence.

- [ ] **Step 4: Implement accessible modal and confirmation flows**

Keep the populated bulk/deletion form open after Cancel or validation failure. Destructive confirmation names the company, record type, count, and consequence. Success refreshes from server-backed logs and restores focus.

- [ ] **Step 5: Verify rollback and idempotent retry**

```bash
php tests/operations-bulk.php
php tests/operations-governed-deletion.php
php tests/operations-jobs.php
```

### Task 4: OP-02 Authorization, Company, And Global Defaults Projections

**Files:**
- Create: `company/admin/modules/operations/setup.php`
- Create: `company/admin/modules/operations/views/sections/authorization.php`
- Create: `company/admin/modules/operations/views/sections/company-defaults.php`
- Create: `tests/operations-authorization-setup.php`
- Create: `tests/operations-company-defaults.php`

**Interfaces:**
- Consumes all OP-02 ledger contracts.
- Produces read-only Operations projections and owner-action links; no authoritative company, branch, finance, inventory, asset, user, role, employee, or designation row.

- [ ] **Step 1: Write failing projection and authorization tests**

Cover company isolation, stable reference keys, authorization-rule thresholds/approvers, unavailable owner providers, and mismatch rejection. Assert Operations SQL contains no Platform, HR, Finance, Inventory, or Assets table writes.

- [ ] **Step 2: Run tests and verify missing projections**

```bash
php tests/operations-authorization-setup.php
php tests/operations-company-defaults.php
```

- [ ] **Step 3: Implement contract-backed projections and Operations-owned policy overlays**

Authorization policy records may be Operations-owned only when they reference owner keys and do not replace owner authorization. Company/Branch/Global Defaults surfaces are read-through summaries with links or contract commands routed to the owner.

- [ ] **Step 4: Implement modal-confirmed Operations-owned policy writes**

Apply the universal write contract to policy overlays. Owner-owned mutations remain disabled or owner-routed until their command contract exists.

- [ ] **Step 5: Verify OP-02**

```bash
php tests/operations-authorization-setup.php
php tests/operations-company-defaults.php
php tests/operations-contracts.php
```

### Task 5: OP-03 Workforce And Calendar Projections

**Files:**
- Create: `company/admin/modules/operations/views/sections/workforce.php`
- Create: `company/admin/modules/operations/views/sections/calendars.php`
- Create: `tests/operations-workforce-calendar.php`

**Interfaces:**
- Consumes: `hr.workforce-directory.v1`, `hr.calendar-directory.v1`, `hr.employee-group-directory.v1`, `platform.company-scope.v1`.
- Produces: read-only setup directories and owner-routed actions.

- [ ] **Step 1: Write failing tests for all ten OP-03 rows**

Assert Department, Designation, Employee, Education, External/Internal History, Employee Group/Table, Holiday, and Holiday List are loaded only from HR contracts and retain stable HR keys. Assert an absent HR provider shows an actionable unavailable state instead of an empty-success screen.

- [ ] **Step 2: Run and verify the missing adapter failure**

```bash
php tests/operations-workforce-calendar.php
```

- [ ] **Step 3: Implement the workforce/calendar projections**

Render directory data, filters, hierarchy, dates, and statuses without copying HR records into Operations tables. Create/edit actions navigate or call an HR owner contract; Operations does not duplicate an HR form.

- [ ] **Step 4: Verify narrow and desktop composition in source assertions**

Assert main lists remain in 12 columns, actions/tools remain in 8 columns, and stacked order is main-first with no nested bordered card.

- [ ] **Step 5: Verify OP-03**

```bash
php tests/operations-workforce-calendar.php
php tests/operations-workspace.php
```

### Task 6: OP-04 Commercial And Trading Master Projections

**Files:**
- Create: `company/admin/modules/operations/views/sections/commercial.php`
- Create: `tests/operations-commercial-masters.php`

**Interfaces:**
- Consumes all Sales, Buying, Finance, Inventory, HR, and shared contracts listed on OP-04 rows.
- Produces read-only setup directories and owner-routed commands.

- [ ] **Step 1: Write failing tests for all twelve OP-04 rows**

Cover stable keys, parent/group hierarchy, active/disabled state, company scope, target periods, trade-term applicability, and owner routing. Explicitly assert no direct Sales, Buying, Finance, Inventory, or HR table writes.

- [ ] **Step 2: Run and verify missing adapter failures**

```bash
php tests/operations-commercial-masters.php
```

- [ ] **Step 3: Implement contract-backed directories**

Group the UI by Finance, Sales, Buying, and shared trade terms. Operations-owned filters and saved views may persist locally; authoritative master edits remain owner-mediated.

- [ ] **Step 4: Verify modal behavior for Operations-owned saved views**

New saved view opens the record modal, Submit opens confirmation, Cancel retains values, and Confirm writes exactly once with audit/read-back/rehydration.

- [ ] **Step 5: Verify OP-04**

```bash
php tests/operations-commercial-masters.php
php tests/operations-workspace.php
```

### Task 7: OP-05 Catalog And Unit Projections

**Files:**
- Create: `company/admin/modules/operations/views/sections/catalog.php`
- Create: `tests/operations-catalog-units.php`

**Interfaces:**
- Consumes: `inventory-warehouse.catalog-directory.v1`, `inventory-warehouse.unit-directory.v1`, `accounting-finance.tax-directory.v1`.

- [ ] **Step 1: Write failing Brand, Item Group, UOM, and conversion tests**

Assert tree parents, group flags, enabled state, whole-number constraints, conversion direction/category, and stable inventory keys. Reject invalid cross-company references and duplicate conversion pairs.

- [ ] **Step 2: Run the test and verify missing views/contracts**

```bash
php tests/operations-catalog-units.php
```

- [ ] **Step 3: Implement read-through catalog/unit setup surfaces**

Keep authoritative create/update in Inventory. Operations may persist only module-local filters, alerts, or release checks referencing stable owner keys.

- [ ] **Step 4: Verify layout and inaccessible owner behavior**

Render an explicit provider-unavailable state and never convert a missing owner response into an empty completed directory.

- [ ] **Step 5: Verify OP-05**

```bash
php tests/operations-catalog-units.php
php tests/operations-contracts.php
```

### Task 8: OP-06 Fleet And Delivery Projections

**Files:**
- Create: `company/admin/modules/operations/views/sections/fleet.php`
- Create: `tests/operations-fleet.php`

**Interfaces:**
- Consumes: `assets-maintenance.fleet-directory.v1`, `hr.workforce-directory.v1`, `buying-procurement.supplier-directory.v1`, `shared.identity-address-directory.v1`, `inventory-warehouse.unit-directory.v1`, `platform.company-scope.v1`.

- [ ] **Step 1: Write failing Driver, License Category, and Vehicle tests**

Cover driver status, employee/supplier references, license issue/expiry dates, vehicle company, unit and fuel type, and expired-license alerts. Verify owner keys and company isolation.

- [ ] **Step 2: Run and confirm missing fleet adapter failure**

```bash
php tests/operations-fleet.php
```

- [ ] **Step 3: Implement fleet projections and Operations alerts**

Assets remains authoritative for fleet records. Operations may create its own expiring-license/system alerts through the job runner, keyed to the Assets record and verified against current owner state.

- [ ] **Step 4: Verify alert idempotency and stale-reference behavior**

Repeated checks create one active alert per stable record/date window; deleted or inaccessible owner records close or suppress the alert without mutating Assets.

- [ ] **Step 5: Verify OP-06**

```bash
php tests/operations-fleet.php
php tests/operations-jobs.php
```

### Task 9: OP-07 Communication Media And Email Digests

**Files:**
- Create: `company/admin/modules/operations/communication.php`
- Create: `company/admin/modules/operations/views/sections/notifications.php`
- Create: `tests/operations-communication.php`
- Create: `tests/operations-email-digests.php`

**Interfaces:**
- Produces communication media/timeslot and digest/recipient persistence services.
- Consumes: `hr.employee-group-directory.v1`, `buying-procurement.supplier-directory.v1`, `shared.identity-directory.v1`, `owners.digest-metrics.v1`, `operations.job-runner.v1`.

- [ ] **Step 1: Write failing communication and digest tests**

Cover medium type, provider reference, disabled state, non-overlapping day/time slots, employee-group routing, company-scoped recipients, daily/weekly/monthly schedules, metric snapshots, retry/idempotency, and failure alerts.

- [ ] **Step 2: Run and verify missing services**

```bash
php tests/operations-communication.php
php tests/operations-email-digests.php
```

- [ ] **Step 3: Implement transaction-safe definitions and schedules**

Store definitions, timeslots, digests, recipients, and job references in Operations tables. Validate every owner reference before write, audit and read back parent/child rows before commit, and never store provider credentials directly.

- [ ] **Step 4: Implement modal-confirmed create/update/enable/disable flows**

All actions use accessible modals and separate confirmation. A failed provider or metric contract leaves the definition intact, records a failed attempt, and shows an actionable error.

- [ ] **Step 5: Verify OP-07 twice for schedule idempotency**

```bash
php tests/operations-communication.php
php tests/operations-email-digests.php
php tests/operations-email-digests.php
```

### Task 10: OP-09 EDI Code Lists And Bank Integration Settings

**Files:**
- Create: `company/admin/modules/operations/integrations.php`
- Create: `company/admin/modules/operations/views/sections/integrations.php`
- Create: `tests/operations-edi.php`
- Create: `tests/operations-bank-integration.php`

**Interfaces:**
- Produces: `operations.edi-code-directory.v1`, EDI list/common-code CRUD, and opaque bank-integration setting references.
- Consumes: `shared.secret-store.v1`, `accounting-finance.bank-sync.v1`, `operations.job-runner.v1`.

- [ ] **Step 1: Write failing EDI and bank-integration tests**

Cover unique code-list names, common-code membership/defaults, applicability links, disabled state, environment allow-list, automatic-sync schedule, opaque secret references, unauthorized access, and transaction-joinable Finance sync. Assert secret values never appear in reads, views, audit payloads, or errors.

- [ ] **Step 2: Run and verify missing OP-09 services**

```bash
php tests/operations-edi.php
php tests/operations-bank-integration.php
```

- [ ] **Step 3: Implement EDI records and secret-reference settings**

Operations owns code lists and integration scheduling metadata. The shared secret store owns credentials; Finance owns bank transaction synchronization. Confirm queues a Finance contract job only after the Operations settings transaction commits and read-back matches.

- [ ] **Step 4: Implement modal-confirmed settings and manual sync**

Environment changes, enable/disable, and manual sync all require confirmation. Production selection includes clear consequence text. Errors retain non-secret form values and never echo credentials.

- [ ] **Step 5: Verify OP-09**

```bash
php tests/operations-edi.php
php tests/operations-bank-integration.php
php tests/operations-jobs.php
```

### Task 11: OP-10 Portal, Rename, And Media Utilities

**Files:**
- Create: `company/admin/modules/operations/utilities.php`
- Create: `company/admin/modules/operations/views/sections/utilities.php`
- Create: `tests/operations-portal-utilities.php`
- Create: `tests/operations-rename.php`
- Create: `tests/operations-video.php`

**Interfaces:**
- Consumes all OP-10 ledger contracts.
- Produces Operations-owned publication settings, rename requests/logs, video metadata/settings, interaction snapshots, and report filters.

- [ ] **Step 1: Write failing portal, rename, video, and report tests**

Cover website attributes/filter fields/item groups, portal users, owner-mediated rename dry-run/confirm/rollback, video provider/URL/title/publish/image metadata, tracking frequency, idempotent interaction import, date/filter report output, unauthorized access, and provider failure.

- [ ] **Step 2: Run and verify missing utilities**

```bash
php tests/operations-portal-utilities.php
php tests/operations-rename.php
php tests/operations-video.php
```

- [ ] **Step 3: Implement portal/media state and owner-mediated rename**

Inventory and shared portal/identity services remain authoritative. Rename requests call only `owners.governed-rename.v1` inside the Operations transaction and preserve immutable old/new key evidence. Implement report behavior from the pinned metadata identity without copying the upstream Python source.

- [ ] **Step 4: Implement accessible modal and report layouts**

All Add/New/Create/Insert flows use the record modal; rename/publish/import actions require separate confirmation. Reports keep results left and filters/export/settings right. Long modal bodies scroll independently with visible header/footer and no nested bordered card.

- [ ] **Step 5: Verify OP-10**

```bash
php tests/operations-portal-utilities.php
php tests/operations-rename.php
php tests/operations-video.php
```

### Task 12: Orchestrator Integration Request, Browser Gate, And Ledger Closure

**Files:**
- Modify after evidence exists: `docs/erpnext-parity/ledgers/operations.json`
- Verify only: `company/admin/modules/operations/`, `tests/operations-*`
- Orchestrator-owned changes requested separately: `app/foundation.php`, `company/admin/bootstrap/`, `company/admin/core/`, `company/admin/views/layout.php`, `company/admin/views/partials/`, `company/admin/assets/`, `company/admin/modules/shared/`, and `tests/erpnext-*`.

**Interfaces:**
- Consumes every completed task and the orchestrator's shared wiring.
- Produces evidence-backed ledger status updates and an Operations completion report.

- [ ] **Step 1: Send the exact shared integration contract to the orchestrator**

Request route `operations`, section resolution, module file loading, controller action/data dispatch, layout inclusion, sidebar state, shared modal/confirmation wiring, shared Form Builder provider registration, and cross-module provider registration. Include exact Operations function names from Task 1. Do not edit any requested shared path.

- [ ] **Step 2: Run the full Operations suite twice**

```bash
for test_file in tests/operations-*.php; do php "$test_file" || exit 1; done
for test_file in tests/operations-*.php; do php "$test_file" || exit 1; done
find company/admin/modules/operations -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check -- company/admin/modules/operations tests/operations-* docs/erpnext-parity/ledgers/operations.json
```

Expected: all commands exit `0`; the second run proves idempotent schema/fixture cleanup.

- [ ] **Step 3: Run authenticated route and browser checks after orchestration wiring**

Verify every Operations section returns non-500 output with no PHP warning/fatal text. At desktop and narrow viewports, verify 12/8 composition, main-first stacking, no horizontal scroll/overlap, modal focus trapping/return, Cancel value retention, validation rehydration, no write before Confirm, exactly one write after Confirm, and server-backed refresh.

- [ ] **Step 4: Update each ledger row only from direct evidence**

For each applicable source ID, add exact module file paths and passing focused test command/output. Mark `COMPLETE` only when the full row behavior and global contracts pass. Leave a row `PARTIAL` or `MISSING` when any required lifecycle, dependency, UI, persistence, authorization, audit, read-back, rehydration, route, or test evidence is absent.

- [ ] **Step 5: Run final ledger validation and report residual risks**

```bash
php tools/erpnext-parity.php validate-ledger
jq -e '
  . as $ledger
  | ["MISSING", "PARTIAL", "COMPLETE", "NOT_APPLICABLE", "DEFERRED"] as $statuses
  | ($ledger.audit.status_counts | to_entries | sort_by(.key))
    == ([$statuses[] as $status
         | {key: $status, value: ([$ledger.rows[].status | select(. == $status)] | length)}]
        | sort_by(.key))
' docs/erpnext-parity/ledgers/operations.json
```

Expected: the ledger validates, top-level counts match rows, all three fixture rows retain their reasons, and every `COMPLETE` row has exact implementation and passing test evidence.

## Highest-Risk Gaps

1. **Governed deletion and bulk commands:** cross-owner mutation, rollback, locks, and retry idempotency can corrupt multiple modules if any owner contract commits independently.
2. **Company/global defaults:** the upstream Company shape spans Finance, Inventory, Assets, HR, Sales, Buying, and shared settings; a duplicate Operations-owned Company model would violate authoritative ownership.
3. **Authorization rules:** transaction thresholds reference users, roles, employees, designations, companies, and multiple document lifecycles. A label-only or client-only rule is unsafe.
4. **Job runner concurrency:** duplicate claims, stale leases, non-idempotent retry, and partial audit/log commits can execute imports, notifications, deletion, or sync twice.
5. **Secrets and bank sync:** integration credentials must remain in the secret owner and Finance must remain authoritative for bank data.
6. **Generic rename:** stable keys are cross-module contracts; direct table renaming would break references and audit history.
7. **Shared integration availability:** Operations cannot be reachable or satisfy modal/Form Builder contracts until the orchestrator lands the shared route, dialog, and provider wiring.

## Proposed Implementation Sequence

1. Orchestrator confirms `shared.module-routing.v1`, `shared.modal-confirmation.v1`, `shared.form-builder.v1`, and provider registration shapes.
2. Task 1 establishes the module shell, allow-listed contract client, schema, 12/8 workspace, and universal Form Builder.
3. Task 2 establishes the idempotent job runner and registered Operations feature surfaces.
4. Task 3 implements the highest-risk bulk/deletion workflows against transaction-joinable owner stubs, then real owner contracts.
5. Tasks 4 and 5 add Platform/authorization and HR/calendar projections.
6. Tasks 6, 7, and 8 add commercial, catalog/unit, and fleet projections after their owners expose stable read contracts.
7. Task 9 builds notification/digest scheduling on the proven job runner.
8. Task 10 adds EDI and secret-safe bank integration handoff.
9. Task 11 adds portal/media utilities and governed rename last, after portal, record-type, and rename contracts are available.
10. Task 12 requests shared wiring, runs route/browser and full regression gates, and updates ledger statuses only from direct evidence.
