# ERPNext Parity Orchestration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Coordinate the existing ERP module tasks to deliver verified BuilderX functional parity with the pinned ERPNext and Frappe HR web baseline while preserving completed HR and Accounting/Finance work.

**Architecture:** The orchestration task owns the versioned parity ledger, shared routing, universal modal/confirmation infrastructure, shared Form Builder contract, and integration gates. Existing module tasks own disjoint module folders, module-specific persistence, views, and tests; each receives its parity subset and cannot declare completion while an applicable ledger row is missing or partial.

**Tech Stack:** PHP 8.5 runtime with PHP 8.1 compatibility, ADODB/MySQL, server-rendered PHP, Tailwind 4 and shadcn tokens, Lucide/Material icons already present in the application, vanilla JavaScript, Playwright 1.62, PHP executable tests, curl route checks, Codex task coordination.

## Global Constraints

- Use ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325` and Frappe HR `version-16` commit `f281e8b172ac8836ad89c59df65a922101103097` as the initial parity baseline.
- Use `gpt-5.6-sol` with `high` reasoning for every dispatched module task.
- Preserve completed HR and Accounting/Finance behavior; implement only verified gaps or backward-compatible shared-contract improvements.
- Defer Mobile / Android Stockroom and all Android-specific work.
- Every ERP module exposes the shared Form Builder contract for its configurable record types.
- Every workspace uses the 12/20 left main panel and 8/20 right utility panel on desktop and stacks main-first on smaller screens.
- Every Add, New, Insert, or Create command opens an accessible modal.
- Submit opens a confirmation dialog; no mutation occurs until Confirm.
- Every persisted write uses authorization, company scope, server validation, parameterized SQL, one ADODB transaction, audit logging, direct read-back verification, rollback, and server rehydration.
- No placeholder, queued, decorative-only, or non-persisted feature can be marked complete.
- Module builders may implement module-local backward-compatible improvements immediately with proportionate tests; cross-module or shared-file improvements return to the orchestration task.
- Do not copy ERPNext GPL source or use ERPNext names, logos, or visual assets as BuilderX product branding.
- Never edit or revert unrelated working-tree changes.

## Task Ownership

| Owner task | Thread ID | ERPNext parity ownership | Allowed module write scope |
|---|---|---|---|
| HR Department | `01a03381-988b-7dd2-8498-043adc037cf5` | Frappe HR and Payroll | `company/admin/modules/hr/`, `tests/hr-*` |
| Accounting/Financing | `01a033ee-1c8b-7da2-98c5-98f65656f588` | Accounts | `company/admin/modules/accounting-finance/`, `tests/accounting-finance-*` |
| Sales/CRM | `01a033f2-d75e-7c13-ac7f-25128b0723fb` | CRM, Selling, sales-facing POS | `company/admin/modules/sales-crm/`, `tests/sales-crm-*` |
| Buying/Procurement | `01a037da-71f4-72d2-bce4-6ed2b5480789` | Buying and supplier-facing EDI workflows | `company/admin/modules/buying-procurement/`, `tests/buying-procurement-*` |
| Inventory/Warehouse | `01a037db-349d-7453-994f-583b757e2e1c` | Stock and inventory-facing POS | `company/admin/modules/inventory-warehouse/`, `tests/inventory-warehouse-*` |
| Manufacturing | `01a037db-aacf-7643-9656-2012cdb0f1a3` | Manufacturing, Quality Management, Subcontracting | `company/admin/modules/manufacturing/`, `tests/manufacturing-*` |
| Projects | `01a037db-cca6-7a03-a82b-31a34e6935f0` | Projects and Project Portal | `company/admin/modules/projects/`, `tests/projects-*` |
| Support/Service | `01a037db-fe95-7e80-bca6-55c0f2c30d6b` | Support, customer support portal, service Telephony | `company/admin/modules/support-service/`, `tests/support-service-*` |
| Assets/Maintenance | `01a037dc-3800-7850-918b-615d933611ec` | Assets and Maintenance | `company/admin/modules/assets-maintenance/`, `tests/assets-maintenance-*` |
| Operations | `01a037dc-5cbd-7510-b6fb-1c47fd3ce6ef` | Setup, Utilities, Integrations, Bulk Transaction, EDI transport, Communication infrastructure | `company/admin/modules/operations/`, `tests/operations-*` |
| Compliance/Localization | `01a037dc-891f-73a1-b881-0ffe921d6401` | Regional and localization | `company/admin/modules/compliance-localization/`, `tests/compliance-localization-*` |

Only the orchestration task edits `app/foundation.php`, `company/admin/bootstrap/`, `company/admin/core/`, `company/admin/views/layout.php`, shared partials/assets, shared Form Builder code, shared modal code, and cross-module integration tests unless a later checkpoint explicitly delegates one of those paths.

---

### Task 1: Pin And Validate The ERPNext Parity Baseline

**Files:**
- Create: `docs/erpnext-parity/baseline.json`
- Create: `docs/erpnext-parity/owners.json`
- Create: `tools/erpnext-parity.php`
- Create: `tests/erpnext-parity-ledger.php`

**Interfaces:**
- Consumes: official ERPNext and Frappe HR Git refs and the approved ownership table.
- Produces: `erpnext_parity_load_baseline(string): array`, `erpnext_parity_load_owners(string): array`, `erpnext_parity_validate_ledger(array,array): array`, and CLI commands `validate-baseline` and `validate-ledger`.

- [ ] **Step 1: Write the failing baseline test**

Create `tests/erpnext-parity-ledger.php` with assertions that both JSON files exist, decode through `JSON_THROW_ON_ERROR`, contain the exact baseline hashes from Global Constraints, list all eleven owner keys, reject unknown status values, reject `DEFERRED` rows outside Android without an approved exclusion, and reject duplicate `source_id` values.

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/tools/erpnext-parity.php';

$root = dirname(__DIR__);
$baseline = erpnext_parity_load_baseline($root . '/docs/erpnext-parity/baseline.json');
$owners = erpnext_parity_load_owners($root . '/docs/erpnext-parity/owners.json');

if (($baseline['erpnext']['commit'] ?? '') !== '11e0ba0a1c45f217e2e73e885f699102d06da325') {
    throw new RuntimeException('ERPNext baseline commit is not pinned.');
}
if (($baseline['hrms']['commit'] ?? '') !== 'f281e8b172ac8836ad89c59df65a922101103097') {
    throw new RuntimeException('Frappe HR baseline commit is not pinned.');
}
if (count($owners['owners'] ?? []) !== 11) {
    throw new RuntimeException('Parity ownership does not cover eleven module tasks.');
}
```

- [ ] **Step 2: Run the test and verify the missing-tool failure**

Run: `php tests/erpnext-parity-ledger.php`

Expected: non-zero exit because `tools/erpnext-parity.php` and the baseline files do not exist.

- [ ] **Step 3: Implement the baseline and owner manifests**

Use this baseline shape:

```json
{
  "schema_version": "builderx.erpnext-parity-baseline.v1",
  "captured_on": "2026-08-25",
  "erpnext": {"repository": "https://github.com/frappe/erpnext.git", "branch": "version-16", "commit": "11e0ba0a1c45f217e2e73e885f699102d06da325", "license": "GPL-3.0"},
  "hrms": {"repository": "https://github.com/frappe/hrms.git", "branch": "version-16", "commit": "f281e8b172ac8836ad89c59df65a922101103097", "license": "GPL-3.0"}
}
```

`owners.json` uses stable owner keys `hr`, `accounting-finance`, `sales-crm`, `buying-procurement`, `inventory-warehouse`, `manufacturing`, `projects`, `support-service`, `assets-maintenance`, `operations`, and `compliance-localization`, with the thread ID, source modules, and allowed write paths from Task Ownership.

- [ ] **Step 4: Implement strict structured validation**

The validator must use `json_decode(..., true, 512, JSON_THROW_ON_ERROR)`, allow only `MISSING`, `PARTIAL`, `COMPLETE`, `NOT_APPLICABLE`, and `DEFERRED`, and require these fields on every ledger row:

```php
['source_id', 'source_repository', 'source_commit', 'source_path', 'source_kind',
 'module_owner', 'builderx_target', 'dependencies', 'status', 'evidence', 'reason']
```

Return a list of complete error messages and exit non-zero from the CLI when any error exists.

- [ ] **Step 5: Run and commit the baseline slice**

```bash
php tests/erpnext-parity-ledger.php
php tools/erpnext-parity.php validate-baseline
php -l tools/erpnext-parity.php
git add docs/erpnext-parity/baseline.json docs/erpnext-parity/owners.json tools/erpnext-parity.php tests/erpnext-parity-ledger.php
git commit -m "Add ERPNext parity baseline ledger"
```

Expected: all commands exit `0`.

### Task 2: Generate Owner Ledgers And Module Plan Inputs

**Files:**
- Create: `docs/erpnext-parity/ledgers/hr.json`
- Create: `docs/erpnext-parity/ledgers/accounting-finance.json`
- Create: `docs/erpnext-parity/ledgers/sales-crm.json`
- Create: `docs/erpnext-parity/ledgers/buying-procurement.json`
- Create: `docs/erpnext-parity/ledgers/inventory-warehouse.json`
- Create: `docs/erpnext-parity/ledgers/manufacturing.json`
- Create: `docs/erpnext-parity/ledgers/projects.json`
- Create: `docs/erpnext-parity/ledgers/support-service.json`
- Create: `docs/erpnext-parity/ledgers/assets-maintenance.json`
- Create: `docs/erpnext-parity/ledgers/operations.json`
- Create: `docs/erpnext-parity/ledgers/compliance-localization.json`
- Modify: `tools/erpnext-parity.php`
- Modify: `tests/erpnext-parity-ledger.php`

**Interfaces:**
- Consumes: pinned source archives checked out into a temporary `mktemp -d` directory and Task 1 manifests.
- Produces: `erpnext_parity_discover(string,string,array): array`, `erpnext_parity_partition(array,array): array`, and eleven valid owner ledgers.

- [ ] **Step 1: Add failing discovery and partition tests**

Use a fixture directory created under `sys_get_temp_dir()` with representative workspace JSON, DocType JSON, report JSON/Python, page, print format, and settings files. Assert discovery produces deterministic `source_id` values in the form `<repository>:<relative-path>:<kind>:<name>` and assigns each row to exactly one owner.

- [ ] **Step 2: Run the test and verify the missing-discovery failure**

Run: `php tests/erpnext-parity-ledger.php`

Expected: non-zero exit because discovery and partition functions are absent.

- [ ] **Step 3: Implement source discovery without copying source code**

Inspect filenames and structured JSON metadata only. Record paths, names, module ownership, kinds, dependencies, and labels; do not copy Python, JavaScript, HTML, images, or ERPNext branding into the BuilderX repository. Include workspace, DocType, report, page, print, dashboard, notification, workflow, and settings metadata.

- [ ] **Step 4: Generate ledgers from exact pinned commits**

```bash
PARITY_TMP="$(mktemp -d)"
git clone --filter=blob:none --no-checkout https://github.com/frappe/erpnext.git "$PARITY_TMP/erpnext"
git -C "$PARITY_TMP/erpnext" checkout 11e0ba0a1c45f217e2e73e885f699102d06da325
git clone --filter=blob:none --no-checkout https://github.com/frappe/hrms.git "$PARITY_TMP/hrms"
git -C "$PARITY_TMP/hrms" checkout f281e8b172ac8836ad89c59df65a922101103097
php tools/erpnext-parity.php discover "$PARITY_TMP/erpnext" "$PARITY_TMP/hrms"
php tools/erpnext-parity.php validate-ledger
```

Expected: eleven ledger files are written with every row initially `MISSING`, except Android-only rows which are `DEFERRED` with reason `Approved Android exclusion`.

- [ ] **Step 5: Commit the generated planning inputs**

```bash
git add docs/erpnext-parity/ledgers tools/erpnext-parity.php tests/erpnext-parity-ledger.php
git commit -m "Catalog ERPNext parity by module owner"
```

### Task 3: Freeze Shared Write Ownership And Characterize Existing Work

**Files:**
- Create: `docs/erpnext-parity/shared-write-lock.json`
- Create: `tests/company-admin-erp-contract.php`
- Verify: `app/foundation.php`
- Verify: `company/admin/bootstrap/app.php`
- Verify: `company/admin/bootstrap/controller.php`
- Verify: `company/admin/core/functions.php`
- Verify: `company/admin/views/layout.php`
- Verify: `company/admin/assets/css/admin.css`

**Interfaces:**
- Consumes: current dirty worktree and module ownership manifests.
- Produces: a machine-readable lock defining orchestration-owned shared paths and a characterization suite for current routes and contracts.

- [ ] **Step 1: Write the characterization test before shared refactoring**

Assert the existing `dashboard`, `platform`, `hr`, `sales-crm`, and `accounting-finance` routes remain allowed; all current HR and Finance section slugs still resolve; module functions are loaded before controller dispatch; and layout selection renders the existing module workspaces.

- [ ] **Step 2: Capture the shared write lock**

Store exact shared path prefixes, orchestration owner, and prohibited concurrent editors. Validate the file with `json_decode(... JSON_THROW_ON_ERROR)` in `tests/company-admin-erp-contract.php`.

- [ ] **Step 3: Run characterization without changing runtime behavior**

```bash
php tests/company-admin-erp-contract.php
php tests/company-admin-modular-architecture.php
php tests/hr-database-driven-forms.php
php tests/accounting-finance-workspaces.php
```

Expected: all currently implemented contracts pass. If an active Accounting turn is still running, wait for that task to finish and rerun this step before committing.

- [ ] **Step 4: Commit only the lock and characterization files**

```bash
git add docs/erpnext-parity/shared-write-lock.json tests/company-admin-erp-contract.php
git commit -m "Characterize shared ERP integration contracts"
```

### Task 4: Dispatch Gap Audits To HR And Accounting/Finance

**Files:**
- Modify by HR task: `docs/erpnext-parity/ledgers/hr.json`
- Create by HR task: `docs/superpowers/plans/2026-08-25-hr-erpnext-parity.md`
- Modify by Finance task: `docs/erpnext-parity/ledgers/accounting-finance.json`
- Create by Finance task: `docs/superpowers/plans/2026-08-25-accounting-finance-erpnext-parity.md`

**Interfaces:**
- Consumes: Task 2 ledgers, existing HR/Finance code and tests, approved design spec.
- Produces: evidence-backed `COMPLETE`/`PARTIAL`/`MISSING` classifications and gap-only implementation plans.

- [ ] **Step 1: Wait for active Finance work to reach an idle or completed state**

Use `wait_threads` for the Finance thread. Do not interrupt its active implementation turn.

- [ ] **Step 2: Send the HR audit prompt with the required model settings**

```text
You own the HR/Frappe HR parity slice. Read the approved master design and orchestration plan, then audit only company/admin/modules/hr and tests/hr-* against docs/erpnext-parity/ledgers/hr.json. Preserve all completed behavior and active user changes. Mark rows COMPLETE only with file/test evidence; mark genuine gaps PARTIAL or MISSING. Write a gap-only implementation plan at docs/superpowers/plans/2026-08-25-hr-erpnext-parity.md. Do not edit shared files or implement yet. Include the universal Form Builder, 12/8 layout, modal creation, Submit->Confirm boundary, transaction, audit, read-back, and rehydration requirements.
```

Send with `model: gpt-5.6-sol` and `thinking: high`.

- [ ] **Step 3: Send the Finance audit prompt with the required model settings**

Use the same prompt structure with Finance paths and ledger. Require the task to incorporate its just-completed work before classifying gaps and prohibit rewrites of verified accounting behavior.

- [ ] **Step 4: Review both audits**

Reject ledger rows lacking evidence, plans that edit shared paths, destructive replacements of completed work, missing modal/Form Builder requirements, or unbounded claims of parity.

### Task 5: Implement Shared Routing, Modal Confirmation, And Form Builder Contracts

**Files:**
- Create: `company/admin/modules/shared/registry.php`
- Create: `company/admin/modules/shared/forms.php`
- Create: `company/admin/views/partials/record-modal.php`
- Create: `company/admin/views/partials/confirm-dialog.php`
- Create: `company/admin/assets/js/admin-modal.js`
- Modify: `app/foundation.php`
- Modify: `company/admin/bootstrap/app.php`
- Modify: `company/admin/bootstrap/controller.php`
- Modify: `company/admin/core/functions.php`
- Modify: `company/admin/views/layout.php`
- Modify: `company/admin/views/partials/scripts.php`
- Modify: `company/admin/assets/css/admin.css`
- Test: `tests/company-admin-erp-contract.php`
- Create: `tests/company-admin-modal-confirmation.php`
- Create: `tests/company-admin-shared-form-builder.php`

**Interfaces:**
- Consumes: current HR and Finance builder normalization/version contracts and Task 3 characterization.
- Produces: `yovel_admin_module_registry(): array`, `yovel_admin_module_route(string): ?array`, `yovel_admin_shared_form_adapter(string): array`, modal data attributes `data-record-modal`, `data-confirm-dialog`, `data-confirm-submit`, and one confirmation controller.

- [ ] **Step 1: Write failing shared contract tests**

Assert eleven web module routes resolve through a registry rather than nested label checks. Render a fixture create form and assert Add opens `data-record-modal`; Submit opens `data-confirm-dialog`; no POST occurs before Confirm; Cancel restores focus and values; Confirm submits exactly once. Assert a shared form adapter cannot remove protected system fields or change stable field keys during reorder.

- [ ] **Step 2: Run the tests and verify expected failures**

```bash
php tests/company-admin-erp-contract.php
php tests/company-admin-modal-confirmation.php
php tests/company-admin-shared-form-builder.php
```

Expected: the new modal and shared-form tests fail at missing interfaces.

- [ ] **Step 3: Implement the data-driven registry**

Each registry entry contains `view`, `label`, `function_file`, `workspace_file`, `sections_provider`, `data_provider`, `default_section`, and `owner`. Keep current route strings stable and add the new module route strings from Task Ownership.

- [ ] **Step 4: Implement one modal and confirmation controller**

The record modal owns fields and Submit. Submit serializes no data externally, makes the record modal inert and visually suspended, then opens a body-owned sibling confirmation dialog; dialogs are never nested in the DOM. Confirm closes the confirmation layer, restores the record form, and calls the original form's `requestSubmit()` once through a guarded `confirmed` state. Cancel closes confirmation, reactivates the populated record modal, clears the guard, and returns focus to Submit. Server errors reopen the record modal through server-rendered state, not client-only cached success.

- [ ] **Step 5: Extract only proven common Form Builder behavior**

Expose adapter metadata for target record types, protected fields, field types, row/column layout, normalization, version identity, and rendering. Keep HR and Finance persistence tables authoritative; adapters call their existing services until a separately tested migration is approved.

- [ ] **Step 6: Run regression and commit the shared slice**

```bash
php tests/company-admin-erp-contract.php
php tests/company-admin-modal-confirmation.php
php tests/company-admin-shared-form-builder.php
php tests/hr-database-driven-forms.php
php tests/accounting-finance-form-builder.php
php tests/company-admin-modular-architecture.php
git diff --check
git add app/foundation.php company/admin tests/company-admin-erp-contract.php tests/company-admin-modal-confirmation.php tests/company-admin-shared-form-builder.php
git commit -m "Add shared ERP module interaction contracts"
```

### Task 6: Dispatch Foundation Module Implementations

**Files:**
- Owned by Sales, Buying, Inventory, Operations, HR, and Finance tasks according to Task Ownership.
- Each owner creates or updates `docs/superpowers/plans/2026-08-25-<owner>-erpnext-parity.md` and its ledger.

**Interfaces:**
- Consumes: Tasks 1-5 and owner ledger rows.
- Produces: working masters and source-document lifecycles required by downstream modules.

- [ ] **Step 1: Dispatch Sales/CRM, Buying/Procurement, and Inventory/Warehouse concurrently**

Send each task an owner-specific prompt containing its exact ledger path and allowed write scope. Require it to execute its approved module plan with TDD, use shared registry/modal/Form Builder APIs, stop before editing shared files, and report requested integration changes separately.

- [ ] **Step 2: Dispatch Operations foundation after shared contracts are green**

Require Setup, Utilities, Integrations, Bulk Transaction, EDI transport, Communication infrastructure, scheduled jobs, retries, idempotency, and observability owned by its ledger. Operations may expose services but cannot directly mutate another module's tables.

- [ ] **Step 3: Dispatch HR and Finance gap-only implementation**

Require both tasks to execute only `PARTIAL` and `MISSING` rows from their reviewed gap plans. They must rerun all existing HR or Finance suites before claiming completion.

- [ ] **Step 4: Review each completed foundation task**

Require changed-file lists, exact test commands and outputs, ledger evidence, no shared-path changes, no placeholder screens, and no applicable `MISSING` rows in the delivered slice.

### Task 7: Dispatch Dependent Operational Modules

**Files:**
- Owned by Manufacturing, Projects, Support, and Assets tasks according to Task Ownership.

**Interfaces:**
- Consumes: verified Sales, Buying, Inventory, Operations, HR, and Finance service boundaries.
- Produces: production, quality, project delivery, support, asset, and maintenance workflows without duplicated authoritative data.

- [ ] **Step 1: Dispatch Manufacturing after Item, Warehouse, and Stock Entry services pass**

Require BOM, production planning, work orders, job cards, MRP, forecasting, quality inspection, subcontracting, stock consumption/receipt, and reports from its parity ledger.

- [ ] **Step 2: Dispatch Projects and Support when Customer and Employee services pass**

Projects consumes Customer and Employee keys for tasks, timesheets, billing, collaboration, and portal access. Support consumes Customer, Item/Asset warranty, communication, SLA, issue, portal, and telephony services.

- [ ] **Step 3: Dispatch Assets/Maintenance when Buying, Inventory, and Finance posting services pass**

Require acquisition, capitalization, locations, custody, movement, depreciation, maintenance, repair, inspection, sale/scrap, and accounting integration from the ledger.

- [ ] **Step 4: Review module-private verification**

Run each module's focused suite twice to expose non-idempotent schema or fixture behavior. Reject direct cross-module table writes and require service-based replacements.

### Task 8: Dispatch Compliance/Localization And Complete Peripheral Parity

**Files:**
- Owned by Compliance and Operations tasks according to Task Ownership.
- Integration tests: orchestration-owned `tests/erpnext-regional-integration.php` and `tests/erpnext-operations-integration.php`.

**Interfaces:**
- Consumes: posted Finance documents, current company region, source-document snapshots, and Operations job services.
- Produces: effective-dated regional rules, e-invoice/report exports, audit evidence, import/export jobs, notifications, portal infrastructure, and remaining applicable parity rows.

- [ ] **Step 1: Dispatch Compliance/Localization**

Require effective-dated tax/localization templates, VAT settings, e-invoice reports, regional reports, evidence, exports, and immutable historical document behavior.

- [ ] **Step 2: Dispatch the remaining Operations ledger rows**

Require setup/settings surfaces, import/export, integration jobs, communication infrastructure, system alerts, release checklist, retryable background work, and evidence for every applicable remaining row.

- [ ] **Step 3: Add cross-owner integration tests before shared wiring**

Write failing tests that call owner services and assert company isolation, stable source keys, idempotent retries, no historical tax mutation, and auditable export creation.

- [ ] **Step 4: Implement only orchestration-owned shared wiring and rerun tests**

Do not move business calculations into bootstrap or layout files.

### Task 9: Cross-Module Workflow Integration

**Files:**
- Create: `tests/erpnext-sales-fulfillment-flow.php`
- Create: `tests/erpnext-procure-to-pay-flow.php`
- Create: `tests/erpnext-manufacturing-flow.php`
- Create: `tests/erpnext-project-billing-flow.php`
- Create: `tests/erpnext-asset-lifecycle-flow.php`
- Create: `tests/erpnext-support-lifecycle-flow.php`
- Modify shared integration files only where required by failing tests.

**Interfaces:**
- Consumes: verified owner service APIs.
- Produces: transaction-safe workflow composition without cross-module record duplication.

- [ ] **Step 1: Write failing end-to-end service tests**

Cover quotation to sales order to reservation/procurement/production to delivery to invoice/payment; material request to RFQ to supplier quotation to purchase order/receipt/invoice/payment; BOM to work order to material issue/receipt; project/timesheet to billing; asset acquisition to depreciation/maintenance/disposal; and ticket to SLA/warranty/communication closure.

- [ ] **Step 2: Verify each failure occurs at a missing integration boundary**

Run each file individually and record the missing service call or projection. A failure caused by fixture leakage or schema errors must be fixed before implementation.

- [ ] **Step 3: Implement the smallest shared coordinators**

Each coordinator owns one public transaction, calls module services that can join an outer transaction, writes audit events, reads every required projection back, and rolls everything back on failure.

- [ ] **Step 4: Run all flow tests and commit by workflow**

```bash
php tests/erpnext-sales-fulfillment-flow.php
php tests/erpnext-procure-to-pay-flow.php
php tests/erpnext-manufacturing-flow.php
php tests/erpnext-project-billing-flow.php
php tests/erpnext-asset-lifecycle-flow.php
php tests/erpnext-support-lifecycle-flow.php
```

Commit each independently green workflow with only its coordinator, tests, and required module service extensions.

### Task 10: Parity Closure And Full Verification

**Files:**
- Modify: all `docs/erpnext-parity/ledgers/*.json`
- Create: `docs/erpnext-parity/final-report.md`
- Verify: all runtime and test files changed by the program.

**Interfaces:**
- Consumes: every module report, focused suite, integration flow, and browser check.
- Produces: a zero-gap parity report for all applicable baseline rows.

- [ ] **Step 1: Validate ledger closure**

```bash
php tools/erpnext-parity.php validate-ledger
```

Expected: no applicable `MISSING` or `PARTIAL` rows, no unsupported status, and evidence for every `COMPLETE` row.

- [ ] **Step 2: Run the complete PHP verification matrix**

```bash
find company/admin -name '*.php' -print0 | xargs -0 -n1 php -l
for test_file in tests/hr-*.php tests/accounting-finance-*.php tests/sales-crm-*.php tests/buying-procurement-*.php tests/inventory-warehouse-*.php tests/manufacturing-*.php tests/projects-*.php tests/support-service-*.php tests/assets-maintenance-*.php tests/operations-*.php tests/compliance-localization-*.php tests/erpnext-*.php tests/company-admin-*.php; do php "$test_file" || exit 1; done
git diff --check
```

Expected: every command exits `0` and test fixtures leave no persisted rows.

- [ ] **Step 3: Run live route and browser verification**

Request every registered module section under `company/yovel-east/admin/` with an authenticated session. Require non-500 responses and no PHP warning/fatal text. Use Playwright at desktop and mobile viewports to verify the 12/8 split, main-first stacking, modal focus, Submit-to-Confirm behavior, retained values on Cancel/error, no overlap, and no page-level horizontal scrolling.

- [ ] **Step 4: Write the final report from evidence**

List baseline commits, each owner task and ledger totals, implemented improvements, test commands, live-route results, browser viewport results, remaining `NOT_APPLICABLE` reasons, approved Android exclusions, and residual operational risks. Do not claim completion from task summaries alone.

- [ ] **Step 5: Commit parity closure**

```bash
git add docs/erpnext-parity tests company/admin app/foundation.php tools/erpnext-parity.php
git commit -m "Complete ERPNext web parity program"
```

Expected: the commit includes only reviewed program files and preserves unrelated user changes.
