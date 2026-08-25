# Compliance / Localization ERPNext Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the missing Philippine Compliance / Localization capability surface over immutable Accounting/Finance tax snapshots, with effective-dated controls, approvals, retained evidence, and auditable regulatory outputs.

**Architecture:** Compliance owns versioned localization rules, account mappings, document-output profiles, import staging, approvals, evidence, retention, return packages, and export manifests. Accounting/Finance remains authoritative for posted invoices, payments, tax calculations, and ledger records; Operations remains authoritative for background transport and retry execution. Module services never write another module's tables and never rewrite a historical posted document.

**Tech Stack:** PHP 8.5 runtime with PHP 8.1 compatibility, ADODB/MySQL, server-rendered PHP, Tailwind 4 and existing shadcn tokens, shared BuilderX modal/confirmation and Form Builder contracts, vanilla JavaScript, PHP executable tests, and authenticated browser verification.

## Global Constraints

- Use ERPNext `version-16` commit `11e0ba0a1c45f217e2e73e885f699102d06da325` as the exact functional source baseline for every ledger row.
- Preserve all current Accounting/Finance behavior and consume it only through orchestrator-approved service boundaries.
- Implement only rows currently marked `MISSING`; this audit found no local `COMPLETE` or `PARTIAL` Compliance behavior to replace.
- Every Compliance workspace uses a 12/20 left main panel and an 8/20 right utility panel on desktop, stacking main-first on smaller screens.
- Every Add, New, Create, or Insert command opens an accessible modal.
- Native validation runs before Submit opens a separate confirmation dialog; no request or mutation occurs until Confirm.
- Every persisted write authorizes company administration, validates CSRF and company scope, normalizes server-side input, uses parameterized SQL, owns one ADODB transaction, writes an audit event, directly reads back critical fields, commits only after exact verification, rolls back on failure, and rehydrates valid submitted values.
- Published rules, templates, issued certificates, approved return packages, export manifests, and submitted source-document snapshots are immutable. Corrections use superseding versions, cancellation, amendment, or linked replacement records.
- The universal Form Builder is shared infrastructure with a module-local Compliance adapter; do not copy HR or Finance Form Builder implementation.
- Module-local backward-compatible improvements are allowed with tests. Shared routing, shared assets, shared modal/Form Builder code, Finance APIs, Operations jobs, and cross-module tests are escalated to orchestration.
- Functional behavior may be adapted from upstream metadata, but ERPNext GPL source, text, templates, branding, logos, and visual assets must not be copied.
- Mobile web must remain responsive; Android-specific application work remains deferred.
- Never edit or revert unrelated working-tree changes.

## Audit Snapshot

- Local module path: `company/admin/modules/compliance-localization/` does not exist.
- Focused tests: no file matches `tests/compliance-localization-*`.
- Ledger totals: `COMPLETE 0`, `PARTIAL 0`, `MISSING 20`, `NOT_APPLICABLE 0`, `DEFERRED 0`.
- Existing Finance functions such as `yovel_admin_ph_vat_effective_code()`, `yovel_admin_ph_vat_calculate_document()`, `yovel_admin_ph_vat_export_rows()`, and the BIR 2550Q review worksheet are dependencies, not Compliance completion evidence.
- Foreign regional rows remain applicable as functional analogues. They are re-expressed as Philippine controls after authoritative legal review; their country labels and source code are not copied.

## Philippine Authority Gate

Before publishing any built-in rule or output version, record the authority URL, issuance identifier, effective date, retrieved date, checksum of the retained source, reviewer, and approval event. Initial implementation references must include:

- [BIR RR No. 11-2025](https://bir-cdn.bir.gov.ph/BIR/pdf/RR%20No.%2011-2025.pdf) for structured electronic invoices and electronic sales reporting.
- [BIR RR No. 26-2025](https://bir-cdn.bir.gov.ph/BIR/pdf/RR%20No.%2026-2025.pdf) for subsequent electronic-invoice and sales-reporting coverage/transition changes.
- [BIR RMC No. 29-2021](https://bir-cdn.bir.gov.ph/local/pdf/RMC%20No.%2029-2021%20%281%29.pdf) for electronically signed withholding certificates, latest-form replication, single issuance, and controlled reprints.
- [BIR RR No. 7-2024](https://bir-cdn.bir.gov.ph/BIR/pdf/RR%20No.%207-%202024.pdf) for current books, invoice, and accounting-record preservation rules, including extended preservation for unresolved audits, protests, or refund claims.
- The current official [BIR/eFPS Form 2550Q](https://efps.bir.gov.ph/efps-war/EFPSWeb_war/forms/2550Q/TaxReturnSearch.xhtml) revision at implementation time.

No authority-backed seed is activated automatically. Seeded profiles start as `DRAFT`, and activation requires a different authorized reviewer to confirm the retained source evidence.

## Cross-Owner Gates

Implementation may start with module-private schema and pure services, but integration cannot be called complete until orchestration supplies these boundaries:

1. Shared registry route `view=compliance-localization`, section dispatch, sidebar links, action dispatch, and module adapter registration.
2. Finance read APIs for immutable submitted invoice/tax snapshots, submitted supplier-payment/withholding snapshots, and stable account references. The returned data must include company hash, stable source key, source status, source version/checksum, posting date, party identity/TIN, currency, tax buckets, and line tax snapshots.
3. A Finance-owned purchase-invoice projection API that can accept an approved, idempotency-keyed import payload under an orchestrator-owned transaction boundary. Compliance must not insert into Finance tables.
4. Operations job APIs for idempotent export/transmission execution, retry policy, acknowledgement capture, and terminal failure. Compliance owns payload and evidence state; Operations owns execution.
5. Shared modal/confirmation and Form Builder APIs from orchestration Task 5. Module markup consumes those contracts without adding a second controller.
6. Orchestration-owned `tests/erpnext-regional-integration.php` proving company isolation, stable keys, retry idempotency, no historical tax mutation, and auditable export creation.

## File Map

Module-private implementation files:

- `company/admin/modules/compliance-localization/functions.php`: sections, active section, workspace data composition, and action allow-list.
- `company/admin/modules/compliance-localization/schema.php`: idempotent Compliance tables, keys, indexes, statuses, and schema checks.
- `company/admin/modules/compliance-localization/core.php`: authorization scope, normalization, transaction helpers, checksums, and immutable-version guards.
- `company/admin/modules/compliance-localization/rules.php`: effective-dated rule sets, VAT settings, account mappings, and approval lifecycle.
- `company/admin/modules/compliance-localization/evidence.php`: evidence storage metadata, checksum verification, retention calculation, and legal holds.
- `company/admin/modules/compliance-localization/templates.php`: tax-document output profiles, immutable template versions, render snapshots, and e-invoice register services.
- `company/admin/modules/compliance-localization/imports.php`: supplier e-invoice batch staging, schema validation, duplicate detection, approvals, and projection requests.
- `company/admin/modules/compliance-localization/withholding.php`: reduced-rate authorities, payment aggregation, certificate issuance, cancellation, and controlled reprint.
- `company/admin/modules/compliance-localization/reports.php`: VAT return packages, VAT audit reconciliation, exceptions, amendments, and deterministic export rows.
- `company/admin/modules/compliance-localization/exports.php`: export manifests, payload checksums, approval, Operations handoff state, acknowledgements, and retries.
- `company/admin/modules/compliance-localization/forms.php`: module-local shared Form Builder adapter, protected fields, targets, and persistence mappings.
- `company/admin/modules/compliance-localization/views/*.php`: workspace, dashboard, feature pages, utility panels, and module-local modal bodies.

Focused tests:

- `tests/compliance-localization-test-helper.php`
- `tests/compliance-localization-schema.php`
- `tests/compliance-localization-rules.php`
- `tests/compliance-localization-evidence.php`
- `tests/compliance-localization-templates-einvoice.php`
- `tests/compliance-localization-imports.php`
- `tests/compliance-localization-withholding.php`
- `tests/compliance-localization-vat-reports.php`
- `tests/compliance-localization-exports.php`
- `tests/compliance-localization-form-builder.php`
- `tests/compliance-localization-workspace.php`
- `tests/compliance-localization-modal-confirmation.php`

## Row Traceability

| Audit ID | Exact pinned source identity | Work package | Philippine target |
|---|---|---|---|
| CL-001 | `erpnext:erpnext/regional/doctype/import_supplier_invoice/import_supplier_invoice.json:doctype:Import Supplier Invoice` | WP-04 | Supplier e-invoice batch import |
| CL-002 | `erpnext:erpnext/regional/doctype/lower_deduction_certificate/lower_deduction_certificate.json:doctype:Lower Deduction Certificate` | WP-05 | Effective-dated reduced-withholding authority |
| CL-003 | `erpnext:erpnext/regional/doctype/south_africa_vat_settings/south_africa_vat_settings.json:doctype:South Africa VAT Settings` | WP-02 | Philippine VAT settings aggregate |
| CL-004 | `erpnext:erpnext/regional/doctype/uae_vat_account/uae_vat_account.json:doctype:UAE VAT Account` | WP-02 | VAT/withholding Finance account mappings |
| CL-005 | `erpnext:erpnext/regional/doctype/uae_vat_settings/uae_vat_settings.json:doctype:UAE VAT Settings` | WP-02 | Approved effective-dated VAT versions |
| CL-006 | `erpnext:erpnext/regional/print_format/detailed_tax_invoice/detailed_tax_invoice.json:print_format:Detailed Tax Invoice` | WP-03 | Detailed Philippine invoice output profile |
| CL-007 | `erpnext:erpnext/regional/print_format/irs_1099_form/irs_1099_form.json:print_format:IRS 1099 Form` | WP-05 | Controlled supplier withholding certificate output |
| CL-008 | `erpnext:erpnext/regional/print_format/purchase_einvoice/purchase_einvoice.json:print_format:Purchase eInvoice` | WP-03 | Purchase e-invoice representation and evidence |
| CL-009 | `erpnext:erpnext/regional/print_format/simplified_tax_invoice/simplified_tax_invoice.json:print_format:Simplified Tax Invoice` | WP-03 | Rule-selected simplified invoice profile |
| CL-010 | `erpnext:erpnext/regional/print_format/tax_invoice/tax_invoice.json:print_format:Tax Invoice` | WP-03 | Standard Philippine invoice profile |
| CL-011 | `erpnext:erpnext/regional/report/electronic_invoice_register/electronic_invoice_register.json:report:Electronic Invoice Register` | WP-03 | E-invoice register workspace |
| CL-012 | `erpnext:erpnext/regional/report/electronic_invoice_register/electronic_invoice_register.py:report_code:Electronic Invoice Register` | WP-03 | E-invoice register query/service |
| CL-013 | `erpnext:erpnext/regional/report/irs_1099/irs_1099.json:report:IRS 1099` | WP-05 | Withholding register workspace |
| CL-014 | `erpnext:erpnext/regional/report/irs_1099/irs_1099.py:report_code:Irs 1099` | WP-05 | Withholding aggregation and issuance service |
| CL-015 | `erpnext:erpnext/regional/report/uae_vat_201/test_uae_vat_201.py:report_code:Test Uae Vat 201` | WP-06 | Philippine VAT return tests |
| CL-016 | `erpnext:erpnext/regional/report/uae_vat_201/uae_vat_201.json:report:UAE VAT 201` | WP-06 | BIR 2550Q review-package workspace |
| CL-017 | `erpnext:erpnext/regional/report/uae_vat_201/uae_vat_201.py:report_code:Uae Vat 201` | WP-06 | VAT return aggregation/reconciliation service |
| CL-018 | `erpnext:erpnext/regional/report/vat_audit_report/test_vat_audit_report.py:report_code:Test Vat Audit Report` | WP-06 | Philippine VAT audit tests |
| CL-019 | `erpnext:erpnext/regional/report/vat_audit_report/vat_audit_report.json:report:VAT Audit Report` | WP-06 | VAT audit workspace and exception queue |
| CL-020 | `erpnext:erpnext/regional/report/vat_audit_report/vat_audit_report.py:report_code:Vat Audit Report` | WP-06 | VAT audit aggregation/reconciliation service |

---

### Task 1: WP-01 Module Foundation, Schema, And Scope

**Files:**
- Create: `company/admin/modules/compliance-localization/functions.php`
- Create: `company/admin/modules/compliance-localization/schema.php`
- Create: `company/admin/modules/compliance-localization/core.php`
- Create: `company/admin/modules/compliance-localization/views/workspace.php`
- Create: `company/admin/modules/compliance-localization/views/dashboard.php`
- Create: `tests/compliance-localization-test-helper.php`
- Create: `tests/compliance-localization-schema.php`
- Create: `tests/compliance-localization-workspace.php`

**Interfaces:**
- Produces: `yovel_admin_compliance_sections(): array`, `yovel_admin_compliance_section(): string`, `yovel_admin_compliance_schema(): void`, `yovel_admin_compliance_scope(array $company, array $admin): array`, `yovel_admin_compliance_data(array $company, array $admin, string $section): array`, and `yovel_admin_compliance_checksum(array $payload): string`.
- Consumes later: orchestrator route registry and shared interaction contracts.

- [ ] **Step 1: Write failing schema and workspace characterization tests**

Assert the exact section slugs `dashboard`, `tax-rules`, `vat-settings`, `supplier-einvoice-imports`, `tax-document-templates`, `einvoice-register`, `withholding-certificates`, `vat-returns`, `vat-audit`, `audit-evidence`, `regulatory-exports`, and `form-builder`. Assert all fourteen tables below exist after two schema calls and every company-owned table has a `company_key_hash` index.

```php
$expectedTables = [
    'project_company_compliance_rule_set',
    'project_company_compliance_tax_account_map',
    'project_company_compliance_template',
    'project_company_compliance_template_version',
    'project_company_compliance_einvoice_record',
    'project_company_compliance_import_batch',
    'project_company_compliance_import_item',
    'project_company_compliance_withholding_authority',
    'project_company_compliance_withholding_certificate',
    'project_company_compliance_return_package',
    'project_company_compliance_evidence',
    'project_company_compliance_approval',
    'project_company_compliance_export',
    'project_company_compliance_retention_hold',
];
```

- [ ] **Step 2: Run the tests and verify the missing-module failure**

```bash
php tests/compliance-localization-schema.php
php tests/compliance-localization-workspace.php
```

Expected: non-zero exit because the module files and functions do not exist.

- [ ] **Step 3: Implement idempotent schema and immutable status contracts**

Use fixed `project_company_compliance_*` identifiers, UUID keys, `company_key`, `company_key_hash`, actor keys, timestamps, stable source keys, and narrow enums. Required lifecycles are:

```php
$statuses = [
    'version' => ['DRAFT', 'APPROVED', 'SUPERSEDED', 'ARCHIVED'],
    'batch' => ['UPLOADED', 'VALIDATED', 'REJECTED', 'APPROVED', 'PROJECTING', 'PROJECTED', 'FAILED'],
    'certificate' => ['DRAFT', 'ISSUED', 'CANCELLED'],
    'return' => ['DRAFT', 'REVIEWED', 'APPROVED', 'SUPERSEDED'],
    'export' => ['DRAFT', 'APPROVED', 'QUEUED', 'PROCESSING', 'SUCCEEDED', 'FAILED', 'CANCELLED'],
];
```

Enforce unique company-scoped business keys, source fingerprints, template version numbers, issued certificate numbers, and idempotency keys. Store JSON as canonical text plus SHA-256; never use a user-provided identifier in SQL.

- [ ] **Step 4: Implement company/admin scope and deterministic checksums**

`yovel_admin_compliance_scope()` must reject missing company UUID/hash, unauthenticated admin, and non-company-admin scope before a transaction begins. `yovel_admin_compliance_checksum()` recursively sorts object keys, preserves list order, encodes with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES`, and returns lowercase SHA-256.

- [ ] **Step 5: Render the module-private workspace contract**

Render a compact operational dashboard and section navigation. Every feature page must use:

```html
<div class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]">
  <main data-compliance-main-panel></main>
  <aside data-compliance-tools-panel></aside>
</div>
```

Tests render the file directly with fixture variables; shared route wiring remains an orchestration gate.

- [ ] **Step 6: Run foundation verification twice and commit**

```bash
php tests/compliance-localization-schema.php
php tests/compliance-localization-schema.php
php tests/compliance-localization-workspace.php
find company/admin/modules/compliance-localization -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
git add company/admin/modules/compliance-localization tests/compliance-localization-test-helper.php tests/compliance-localization-schema.php tests/compliance-localization-workspace.php
git commit -m "Add Compliance localization foundation"
```

### Task 2: WP-02 Effective Rules, VAT Settings, Evidence, And Retention

**Ledger rows:** CL-003, CL-004, CL-005.

**Files:**
- Create: `company/admin/modules/compliance-localization/rules.php`
- Create: `company/admin/modules/compliance-localization/evidence.php`
- Create: `company/admin/modules/compliance-localization/views/rules.php`
- Create: `company/admin/modules/compliance-localization/views/evidence.php`
- Create: `tests/compliance-localization-rules.php`
- Create: `tests/compliance-localization-evidence.php`

**Interfaces:**
- Produces: `yovel_admin_compliance_save_rule_set()`, `yovel_admin_compliance_approve_rule_set()`, `yovel_admin_compliance_supersede_rule_set()`, `yovel_admin_compliance_effective_rule_set()`, `yovel_admin_compliance_save_evidence()`, `yovel_admin_compliance_verify_evidence()`, `yovel_admin_compliance_place_hold()`, and `yovel_admin_compliance_release_hold()`.
- Consumes: Finance account-reference API and module foundation.

- [ ] **Step 1: Write failing rule lifecycle and isolation tests**

Create two company fixtures. Assert a draft may be updated under the same stable key; overlapping approved periods for the same jurisdiction/rule type are rejected; creator and approver differ; approval snapshots account mappings and source authority; approved rows cannot be edited; a superseding version does not alter an invoice snapshot dated under the old version; cross-company reads and approvals return no record.

- [ ] **Step 2: Write failing evidence and retention tests**

Use a temporary fixture file and assert MIME/size allow-listing, SHA-256 read-back, safe generated storage path, duplicate-content detection, rollback cleanup, retention calculation from the approved rule version, hold prevention of archive/removal, hold release audit, and extended retention while a case remains open.

- [ ] **Step 3: Run the focused tests and verify missing-service failures**

```bash
php tests/compliance-localization-rules.php
php tests/compliance-localization-evidence.php
```

- [ ] **Step 4: Implement transactional draft, approval, supersede, and archive boundaries**

Each public mutation follows this shape and compares all critical read-back fields before commit:

```php
$db = bx_db();
[$companyKey, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
if ($db->BeginTrans() === false) {
    throw new RuntimeException('Compliance transaction could not start.');
}
try {
    // Lock current version, execute parameterized writes, add approval/evidence rows,
    // call bx_audit(), and SELECT the saved version FOR UPDATE for exact comparison.
    if ($db->CommitTrans() === false) {
        throw new RuntimeException('Compliance transaction could not commit.');
    }
} catch (Throwable $error) {
    $db->RollbackTrans();
    throw $error;
}
```

The approved rule payload includes jurisdiction `PH`, rule type, legal source metadata, effective dates, VAT registration class, tax-code policy references, retention policy, invoice-profile keys, and Finance account mappings. Rate changes always create a new version.

- [ ] **Step 5: Implement evidence storage without orphan files**

Validate upload metadata before `BeginTrans()`. Write to a generated temporary path beneath an allow-listed module evidence directory, calculate SHA-256, begin the metadata transaction, move to the final generated path, verify file hash and metadata read-back, then commit. On any failure, roll back and remove only the generated temporary/final path for that attempted evidence key.

- [ ] **Step 6: Build 12/8 rules and evidence pages with modal creation**

The left panel lists versions/evidence and timelines; the right panel holds filters, source status, approval actions, retention state, and links. `New rule`, `Add mapping`, `Add evidence`, `Approve`, `Supersede`, `Archive`, `Place hold`, and `Release hold` use accessible record modals and action-specific confirmation.

- [ ] **Step 7: Verify and commit**

```bash
php tests/compliance-localization-rules.php
php tests/compliance-localization-evidence.php
php tests/compliance-localization-schema.php
git diff --check
git add company/admin/modules/compliance-localization/rules.php company/admin/modules/compliance-localization/evidence.php company/admin/modules/compliance-localization/views/rules.php company/admin/modules/compliance-localization/views/evidence.php tests/compliance-localization-rules.php tests/compliance-localization-evidence.php
git commit -m "Add effective Compliance rules and evidence controls"
```

### Task 3: WP-03 Tax Document Profiles And E-Invoice Register

**Ledger rows:** CL-006, CL-008, CL-009, CL-010, CL-011, CL-012.

**Files:**
- Create: `company/admin/modules/compliance-localization/templates.php`
- Create: `company/admin/modules/compliance-localization/views/templates.php`
- Create: `company/admin/modules/compliance-localization/views/einvoice-register.php`
- Create: `tests/compliance-localization-templates-einvoice.php`

**Interfaces:**
- Produces: `yovel_admin_compliance_save_template()`, `yovel_admin_compliance_publish_template()`, `yovel_admin_compliance_render_invoice_snapshot()`, `yovel_admin_compliance_register_einvoice()`, `yovel_admin_compliance_update_transmission()`, and `yovel_admin_compliance_einvoice_register()`.
- Consumes: approved rules, Finance immutable invoice snapshots, and Operations transmission acknowledgements.

- [ ] **Step 1: Write failing template-version tests**

Assert protected keys cannot be removed or renamed; draft update preserves template key; publish creates an immutable version/checksum; rendering binds source invoice key/checksum, rule version, and template version; identical input renders the same canonical payload checksum; amendments link to the prior record; old renders do not change after a new template is published.

- [ ] **Step 2: Write failing e-invoice register tests**

Assert company/date/status filters are parameterized, only submitted Finance snapshots appear, duplicate source checksum plus schema version is idempotent, correction lineage is visible, status transitions are allow-listed, acknowledgement fields are read back exactly, and another company cannot read or mutate a register row.

- [ ] **Step 3: Run the test and verify missing interfaces**

```bash
php tests/compliance-localization-templates-einvoice.php
```

- [ ] **Step 4: Implement protected Philippine output profiles**

Define built-in protected field keys for seller registration, branch/RDO, invoice identity and serial, issue date, buyer identity/TIN, line description/quantity/amount, VAT class/rate/base/amount, discounts, totals, currency, original/amendment reference, and source checksum. Profiles select detailed, simplified, purchase, or standard output through approved effective rules, never through client input alone.

- [ ] **Step 5: Implement immutable render and register services**

Canonical render payloads contain data only. HTML/PDF presentation is produced from BuilderX-owned templates; no upstream markup is copied. The register joins Finance snapshots through the approved read API and Compliance-owned records by stable source key, never by direct cross-module writes.

- [ ] **Step 6: Build the profile and register pages**

Use left-panel template versions/render preview or register results and right-panel filters, source/version details, validation, actions, and activity. New profile and correction actions open modals; publish, archive, queue transmission, retry, and cancel require confirmation.

- [ ] **Step 7: Verify and commit**

```bash
php tests/compliance-localization-templates-einvoice.php
php tests/compliance-localization-rules.php
git diff --check
git add company/admin/modules/compliance-localization/templates.php company/admin/modules/compliance-localization/views/templates.php company/admin/modules/compliance-localization/views/einvoice-register.php tests/compliance-localization-templates-einvoice.php
git commit -m "Add Philippine tax document profiles and e-invoice register"
```

### Task 4: WP-04 Supplier E-Invoice Import And Projection Requests

**Ledger row:** CL-001.

**Files:**
- Create: `company/admin/modules/compliance-localization/imports.php`
- Create: `company/admin/modules/compliance-localization/views/imports.php`
- Create: `tests/compliance-localization-imports.php`

**Interfaces:**
- Produces: `yovel_admin_compliance_create_import_batch()`, `yovel_admin_compliance_validate_import_batch()`, `yovel_admin_compliance_approve_import_batch()`, `yovel_admin_compliance_import_projection_payload()`, and `yovel_admin_compliance_record_import_projection()`.
- Consumes: approved supplier e-invoice schema profile, Buying supplier reference, Finance purchase-invoice projection API, and Operations idempotent job API.

- [ ] **Step 1: Write failing upload and parser security tests**

Cover JSON and XML allow-listed MIME/types, configured byte limit, empty/oversized files, malformed documents, unknown schema version, XML DTD/entity rejection, path traversal names, canonical SHA-256, duplicate batch fingerprint, line/tax total reconciliation, and retained validation messages.

- [ ] **Step 2: Write failing lifecycle and projection tests**

Assert `UPLOADED -> VALIDATED -> APPROVED -> PROJECTING -> PROJECTED` and rejection/failure transitions; no projection request before Confirm; approval requires a different reviewer; each item has a stable fingerprint; retries reuse the idempotency key; Finance failure rolls back the orchestrator-owned projection transaction while preserving staged input and actionable errors.

- [ ] **Step 3: Run the test and verify missing interfaces**

```bash
php tests/compliance-localization-imports.php
```

- [ ] **Step 4: Implement safe staged parsing and reconciliation**

Use `json_decode(..., true, 512, JSON_THROW_ON_ERROR)` for JSON. For XML, reject `<!DOCTYPE` and `<!ENTITY`, parse with network access disabled, and map only fields allowed by the approved schema profile. Normalize supplier identity, invoice identity/date/currency, lines, tax classes, and totals; calculate a canonical item fingerprint from company, supplier TIN, invoice number/date, currency, amount, and source checksum.

- [ ] **Step 5: Implement approval and cross-owner projection request**

Compliance approval writes the immutable approved payload and projection idempotency key in its transaction. The orchestrator coordinator calls the Finance-owned projection API and records the returned Finance key/checksum through `yovel_admin_compliance_record_import_projection()`. No Compliance query inserts or updates Finance tables.

- [ ] **Step 6: Build the import workspace**

The left panel shows batches, staged invoices, validation, and projection result. The right panel shows schema version, duplicate checks, totals, evidence, approval, and retry actions. `New import` opens a modal; Validate, Approve, Reject, Project, and Retry each use a separate confirmation dialog.

- [ ] **Step 7: Verify and commit**

```bash
php tests/compliance-localization-imports.php
php tests/compliance-localization-evidence.php
git diff --check
git add company/admin/modules/compliance-localization/imports.php company/admin/modules/compliance-localization/views/imports.php tests/compliance-localization-imports.php
git commit -m "Add supplier e-invoice import controls"
```

### Task 5: WP-05 Withholding Authorities, Registers, And Certificates

**Ledger rows:** CL-002, CL-007, CL-013, CL-014.

**Files:**
- Create: `company/admin/modules/compliance-localization/withholding.php`
- Create: `company/admin/modules/compliance-localization/views/withholding.php`
- Create: `tests/compliance-localization-withholding.php`

**Interfaces:**
- Produces: `yovel_admin_compliance_save_withholding_authority()`, `yovel_admin_compliance_approve_withholding_authority()`, `yovel_admin_compliance_effective_withholding_authority()`, `yovel_admin_compliance_withholding_register()`, `yovel_admin_compliance_issue_withholding_certificate()`, `yovel_admin_compliance_reprint_withholding_certificate()`, and `yovel_admin_compliance_cancel_withholding_certificate()`.
- Consumes: Finance submitted supplier-payment/withholding snapshots and approved official certificate-template evidence.

- [ ] **Step 1: Write failing authority tests**

Assert supplier/TIN, tax category, rate, amount ceiling, validity, source authority, evidence, and company scope; reject invalid rates/dates and overlapping approved authorities; lock ceiling consumption; preserve historical application after supersession; prevent creator self-approval; deny cross-company access.

- [ ] **Step 2: Write failing register and certificate tests**

Aggregate decimal-safe submitted Finance payment snapshots by supplier/category/period. Assert unique original issuance, immutable issued payload/version/checksum, signer approval, exact certificate number, cancellation lineage, reprint count, `RE-PRINT` state, evidence hash, and no duplicate tax-credit amount caused by reprint.

- [ ] **Step 3: Run the test and verify missing interfaces**

```bash
php tests/compliance-localization-withholding.php
```

- [ ] **Step 4: Implement effective authority and locked consumption**

Resolve the approved authority on payment date and lock it before consuming its ceiling. If the Finance snapshot already carries an immutable withholding decision, record a reference to that decision instead of recalculating or rewriting it. Any legal-rate change creates a new approved authority version.

- [ ] **Step 5: Implement controlled certificate issuance**

The issued record stores the official form revision/source evidence key, source payment keys/checksums, payor/payee identities, tax rows, period, totals, signer identity, issued timestamp, payload checksum, and output evidence key. Reprint produces a linked rendition with reprint watermark state and audit event; it does not create a second creditable-tax record.

- [ ] **Step 6: Build the withholding workspace and verify**

```bash
php tests/compliance-localization-withholding.php
php tests/compliance-localization-rules.php
php tests/compliance-localization-evidence.php
git diff --check
git add company/admin/modules/compliance-localization/withholding.php company/admin/modules/compliance-localization/views/withholding.php tests/compliance-localization-withholding.php
git commit -m "Add Philippine withholding compliance controls"
```

### Task 6: WP-06 VAT Return Packages, VAT Audit, And Exceptions

**Ledger rows:** CL-015, CL-016, CL-017, CL-018, CL-019, CL-020.

**Files:**
- Create: `company/admin/modules/compliance-localization/reports.php`
- Create: `company/admin/modules/compliance-localization/views/vat-reports.php`
- Create: `company/admin/modules/compliance-localization/views/vat-audit.php`
- Create: `tests/compliance-localization-vat-reports.php`

**Interfaces:**
- Produces: `yovel_admin_compliance_build_vat_return()`, `yovel_admin_compliance_save_vat_adjustment()`, `yovel_admin_compliance_review_vat_return()`, `yovel_admin_compliance_approve_vat_return()`, `yovel_admin_compliance_amend_vat_return()`, `yovel_admin_compliance_vat_audit()`, and `yovel_admin_compliance_vat_exceptions()`.
- Consumes: Finance submitted invoice/tax snapshots, approved Compliance account maps/rules, and official current BIR 2550Q revision metadata.

- [ ] **Step 1: Write failing VAT return tests**

Cover quarter boundaries, sales/purchase classification, output/input/non-creditable VAT, government withholding, zero-rated/exempt buckets, source totals, explicit adjustments, decimal precision, current form revision, company isolation, reviewer/approver separation, immutable approval, amendment lineage, and deterministic package checksum.

- [ ] **Step 2: Write failing VAT audit and exception tests**

Assert grouping by rate/classification and sales/purchase, mapped-account validation, invoice-tax-to-Finance-total reconciliation, source-document drilldown, missing TIN/profile/evidence exceptions, duplicate serial/source checksum detection, retained exception resolution history, and no update to Finance source rows.

- [ ] **Step 3: Run the test and verify missing interfaces**

```bash
php tests/compliance-localization-vat-reports.php
```

- [ ] **Step 4: Implement return packages over immutable Finance snapshots**

Use Finance service payloads as the only calculation source. Store source keys/checksums and reconciliation totals in the draft package. Manual adjustments require category, amount, reason, evidence, preparer, and reviewer; they never alter Finance. Approval locks the package; amendment creates a new package linked to the approved original.

- [ ] **Step 5: Implement audit reconciliation and exception queues**

Queries are company-scoped and parameterized. Each report row includes posting date, source type/key/number, party/TIN, VAT class/rate, net/tax/gross totals, Finance checksum, rule/account-map version, evidence completeness, and exception state. Resolution appends an auditable event; it does not erase the exception history.

- [ ] **Step 6: Build report pages and verify**

Results, charts, drilldown, and exceptions occupy the left 12/20 panel. Period filters, form revision, reconciliation summary, evidence state, approvals, amendment, and export actions occupy the right 8/20 panel.

```bash
php tests/compliance-localization-vat-reports.php
php tests/accounting-finance-ph-vat.php
php tests/accounting-finance-reports.php
git diff --check
git add company/admin/modules/compliance-localization/reports.php company/admin/modules/compliance-localization/views/vat-reports.php company/admin/modules/compliance-localization/views/vat-audit.php tests/compliance-localization-vat-reports.php
git commit -m "Add VAT return and audit compliance packages"
```

### Task 7: WP-07 Regulatory Exports, Approvals, And Operations Handoff

**Files:**
- Create: `company/admin/modules/compliance-localization/exports.php`
- Create: `company/admin/modules/compliance-localization/views/exports.php`
- Create: `tests/compliance-localization-exports.php`

**Interfaces:**
- Produces: `yovel_admin_compliance_create_export()`, `yovel_admin_compliance_approve_export()`, `yovel_admin_compliance_export_payload()`, `yovel_admin_compliance_queue_export()`, `yovel_admin_compliance_record_export_result()`, and `yovel_admin_compliance_retry_export()`.
- Consumes: approved e-invoice records, withholding certificates, VAT packages/audits, evidence, and Operations job API.

- [ ] **Step 1: Write failing export lifecycle tests**

Assert only approved source snapshots can enter an export; payload schema version and row count are fixed; canonical bytes and SHA-256 are deterministic; creator and approver differ; no queue call occurs before Confirm; idempotent retries reuse export/idempotency keys; acknowledgements and errors are retained; a successful export is immutable; cancellation/replacement is linked and audited.

- [ ] **Step 2: Run the test and verify missing interfaces**

```bash
php tests/compliance-localization-exports.php
```

- [ ] **Step 3: Implement manifest-first export generation**

The export transaction stores source type/key/checksum list, jurisdiction, filing/report period, schema version, payload MIME/name, byte size, SHA-256, row count, approval state, retention deadline, and evidence key. Payload generation reads only the approved manifest and fails on any source checksum mismatch.

- [ ] **Step 4: Implement Operations handoff without transport logic**

Queueing writes an idempotency-keyed handoff state and calls the orchestrator-approved Operations API. `yovel_admin_compliance_record_export_result()` accepts only allow-listed transitions and verifies Operations job key, attempt, acknowledgement/error payload checksum, and terminal state before commit.

- [ ] **Step 5: Build the export workspace and verify**

```bash
php tests/compliance-localization-exports.php
php tests/compliance-localization-evidence.php
git diff --check
git add company/admin/modules/compliance-localization/exports.php company/admin/modules/compliance-localization/views/exports.php tests/compliance-localization-exports.php
git commit -m "Add auditable regulatory export workflow"
```

### Task 8: WP-08 Universal Form Builder, Modal Contract, And Closure

**Files:**
- Create: `company/admin/modules/compliance-localization/forms.php`
- Create: `company/admin/modules/compliance-localization/views/form-builder.php`
- Modify: `company/admin/modules/compliance-localization/functions.php`
- Modify: `company/admin/modules/compliance-localization/views/workspace.php`
- Modify: `company/admin/modules/compliance-localization/views/rules.php`
- Modify: `company/admin/modules/compliance-localization/views/evidence.php`
- Modify: `company/admin/modules/compliance-localization/views/templates.php`
- Modify: `company/admin/modules/compliance-localization/views/einvoice-register.php`
- Modify: `company/admin/modules/compliance-localization/views/imports.php`
- Modify: `company/admin/modules/compliance-localization/views/withholding.php`
- Modify: `company/admin/modules/compliance-localization/views/vat-reports.php`
- Modify: `company/admin/modules/compliance-localization/views/vat-audit.php`
- Modify: `company/admin/modules/compliance-localization/views/exports.php`
- Create: `tests/compliance-localization-form-builder.php`
- Create: `tests/compliance-localization-modal-confirmation.php`
- Modify: `docs/erpnext-parity/ledgers/compliance-localization.json` after verification

**Interfaces:**
- Produces: `yovel_admin_compliance_form_adapter(): array` and module-local target/persistence mappings consumed by the shared Form Builder.
- Consumes: orchestrator shared Form Builder, record-modal, confirmation-dialog, and route contracts.

- [ ] **Step 1: Write failing adapter tests**

Targets must include rule set, VAT account map, document profile, import batch, withholding authority, withholding certificate, VAT return, evidence, and export. Assert stable field keys survive reorder, protected fields cannot be hidden/deleted/type-changed, drafts can change, published versions are immutable, archived versions are read-only, and submitted records retain the exact published version key/checksum.

- [ ] **Step 2: Write failing interaction contract tests**

Render every feature view and assert each Add/New/Create/Insert trigger references a body-owned accessible modal; forms include native constraints and CSRF; valid Submit is guarded by `data-confirm-submit`; confirmation is a sibling rather than nested dialog; Cancel preserves values; server error state rehydrates the same modal; no feature has an unconfirmed destructive or export action.

- [ ] **Step 3: Run the tests and verify missing adapter/shared-contract failures**

```bash
php tests/compliance-localization-form-builder.php
php tests/compliance-localization-modal-confirmation.php
```

- [ ] **Step 4: Implement the module-local Form Builder adapter**

Return target code, label, protected fields, allowed field types, validation limits, active schema/version lookup, record-version binding, persistence mapping, and lifecycle constraints. Reuse shared normalization/version APIs and keep Compliance business validation in module services.

- [ ] **Step 5: Align every workspace and modal**

Use the 12/8 page contract, main-first mobile stacking, compact operational navigation, and existing icon family. Modals have one header, independently scrollable body, footer, title/description, Escape support, focus return, and no nested cards/dialogs. Confirmation owns Confirm/Cancel and replays the original submission exactly once with `requestSubmit()`.

- [ ] **Step 6: Run the full module and dependency verification matrix**

```bash
for test_file in tests/compliance-localization-*.php; do php "$test_file" || exit 1; done
php tests/accounting-finance-ph-vat.php
php tests/accounting-finance-invoices.php
php tests/accounting-finance-reports.php
php tests/company-admin-erp-contract.php
php tests/company-admin-modal-confirmation.php
php tests/company-admin-shared-form-builder.php
find company/admin/modules/compliance-localization -name '*.php' -print0 | xargs -0 -n1 php -l
php tools/erpnext-parity.php validate-ledger
git diff --check
```

Expected: every command exits `0`, fixtures restore prior database rows, and no source invoice/payment checksum changes.

- [ ] **Step 7: Perform authenticated desktop and mobile browser verification**

At 1440x900 and 390x844, open every section and verify non-500 responses, no warning/fatal text, 12/8 desktop composition, main-first stacking, no horizontal page scroll, modal focus trap/return, Escape, native validation before confirmation, zero requests before Confirm, exactly one request after Confirm, retained values on Cancel/error, one success message after committed read-back, and persistent failure dialog on errors.

- [ ] **Step 8: Close ledger rows only from exact evidence**

For each CL row, change `MISSING` to `COMPLETE` only when its listed module file exists and its focused test passes in the final run. Populate `evidence` with exact file paths, test command, and observed pass message. Leave a row `PARTIAL` when any lifecycle, permission, company-isolation, modal, Form Builder, transaction, audit, read-back, rehydration, retention, or browser requirement is not evidenced.

- [ ] **Step 9: Commit module closure**

```bash
git add company/admin/modules/compliance-localization tests/compliance-localization-* docs/erpnext-parity/ledgers/compliance-localization.json
git commit -m "Complete Philippine Compliance localization parity"
```

## Proposed Implementation Sequence

1. Orchestration confirms shared route/modal/Form Builder contracts and Finance/Operations service signatures.
2. WP-01 establishes module schema, authorization, checksums, and directly renderable workspace.
3. WP-02 establishes effective rules, approvals, evidence, retention, and legal holds used by every later package.
4. WP-03 creates immutable output profiles and the e-invoice register over Finance snapshots.
5. WP-04 adds secure supplier e-invoice import staging and idempotent projection requests.
6. WP-05 adds reduced-withholding authorities, supplier registers, issuance, cancellation, and controlled reprints.
7. WP-06 adds VAT return packages, audit reconciliation, and exception resolution.
8. WP-07 adds approved deterministic exports and Operations handoff.
9. WP-08 connects the universal Form Builder and shared interaction contracts, then runs module, dependency, route, and browser closure gates.

## Highest-Risk Gaps

- Philippine electronic invoice and sales-reporting obligations are changing; schema version, applicability, deadlines, and transmission behavior must remain effective-dated and authority-backed.
- Finance APIs are not yet an approved stable boundary. Direct Finance table reads/writes would create coupling, bypass ownership, and risk historical mutation.
- Evidence upload and retention combine filesystem and database state; rollback cleanup, checksum verification, legal holds, and path allow-listing are mandatory.
- Cross-module import projection and export transmission need idempotent orchestration. A retry must not duplicate a purchase invoice, certificate, filing package, or transmission.
- Compliance approvals require separation of duties and immutable snapshots. A generic save action cannot stand in for approve, issue, publish, amend, archive, cancel, or reprint.
- Exact BIR form revisions and retention rules can change. The implementation must not silently treat a stale built-in template or period as current legal advice.
