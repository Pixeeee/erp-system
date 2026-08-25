# HR / Frappe HR Parity Gap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the evidence-backed HR and Payroll gaps in `docs/erpnext-parity/ledgers/hr.json` against Frappe HR commit `f281e8b172ac8836ad89c59df65a922101103097` without rebuilding the completed HR Form Builder, employee organization masters, or transactional project-module projection.

**Architecture:** HR remains authoritative for employee lifecycle, organization structure, attendance, leave, recruitment, onboarding, performance, expenses, and payroll access. Each work package adds module-local schemas, services, and views behind company-scoped interfaces; cross-module effects call owner services supplied by the orchestration task. The completed database-driven Form Builder and projection pipeline are extended through adapters, not copied or replaced.

**Tech Stack:** PHP 8, ADODB/MySQL, server-rendered BuilderX views, shadcn-style controls, existing HR Form Builder schema/version/submission services, Material Symbols, focused PHP integration tests.

## Global Constraints

- Preserve `company/admin/modules/hr/forms.php`, its immutable versions/submissions, and immediate `project_module_form` projection behavior.
- Preserve current Employee Profiles, Departments, Job Positions, and Teams behavior unless a backward-compatible gap fix is required.
- Use the module page shell with a left main panel occupying 12/20 columns and a right action/tools panel occupying 8/20 columns; stack main-first on narrow screens.
- Every Add, New, Create, or Insert command opens an accessible modal.
- Submit opens a separate confirmation dialog. Persistence must not begin until Confirm; Cancel must preserve populated values.
- Public writes authorize company/admin scope, normalize server input, use parameterized SQL, acquire lifecycle locks, own one ADODB transaction, write audits inside it, directly read back critical state, and roll back on any failure.
- Validation failures rehydrate valid submitted values and expose actionable field/form feedback.
- Every new HR record type receives a module-local Form Builder adapter with protected workflow fields, stable keys, draft/publish/archive/version behavior, and version-bound submissions.
- Shared Form Builder, modal/confirmation, route/sidebar, attachment, notification, print/export, and cross-module service changes must be requested from the orchestration owner; this plan must not directly edit shared locked paths.
- Reproduce functional behavior and information architecture without copying GPL implementation source, visual assets, or ERPNext/Frappe branding.
- Android/mobile application work remains deferred. Responsive web behavior is still required.

---

## Audit Baseline

Audit commands completed on 2026-08-25:

```bash
php tests/hr-database-driven-forms.php
php tests/project-module-hierarchy.php
```

Both passed. Verified completed foundations include company-scoped employee assignments, typed custom values, stable Form Builder row/column keys, immutable form versions, version-bound submissions, rollback, registry ownership, idempotency, and immediate HR form projection.

Ledger status after audit:

| Status | Count | Meaning |
|---|---:|---|
| `COMPLETE` | 0 | No pinned Frappe HR row has exact end-to-end local implementation plus passing row-specific evidence. |
| `PARTIAL` | 6 | HR Setup, Employment Type, Employee Transfer, Employee Separation, Exit Interview, and Holiday List Assignment have local fragments but not complete source-equivalent workflows. |
| `MISSING` | 228 | No exact module-local implementation and passing HR test evidence. |
| `NOT_APPLICABLE` | 23 | Upstream `test_records.json` and `test_*.py` artifacts are verification inputs, not deployable capabilities. |
| `DEFERRED` | 0 | No Android-only row appears in this ledger. |

Every applicable row carries one of `HR-WP-01` through `HR-WP-12` in its `reason`. Retrieve exact row identities for any package with:

```bash
jq -r '.rows[] | select(.reason | startswith("[HR-WP-01]")) | [.source_id,.status,.source_path] | @tsv' docs/erpnext-parity/ledgers/hr.json
```

Replace `HR-WP-01` with the work package being executed. This query is the row-level acceptance list; do not close a package by checking only its summarized capability names.

## Planned Module Structure

The implementation phase may add these HR-owned files while retaining existing boundaries:

```text
company/admin/modules/hr/
  setup.php                    # HR settings and employee lifecycle masters
  attendance.php               # check-ins, attendance, shifts, overtime
  leave.php                    # leave setup, ledger, allocation, application
  recruitment.php              # requisitions, openings, applicants, interviews, offers
  onboarding.php               # boarding, separation, exit, document requirements
  performance.php              # appraisal, goals, skills, training
  expenses.php                 # advances, claims, travel, vehicle records
  communications.php           # daily summaries and module notification intents
  reports.php                  # HR report definitions and query services
  payroll.php                  # payroll setup and transaction services
  payroll-tax.php              # tax, benefit, and gratuity services
  payroll-reports.php          # payroll reports and print/export payloads
  views/<matching-feature>.php # 12/8 workspaces and body-owned modals
tests/
  hr-preservation-contract.php
  hr-setup-lifecycle.php
  hr-attendance-shifts.php
  hr-leave-management.php
  hr-recruitment.php
  hr-onboarding-separation.php
  hr-performance-training.php
  hr-expenses-travel.php
  hr-communications.php
  hr-reports.php
  hr-payroll.php
  hr-payroll-tax-benefits.php
  hr-payroll-outputs.php
  hr-modal-confirmation.php
  hr-responsive-layout.php
```

Do not split existing files merely for style. Add a file only when its package needs an independent schema/service boundary.

### Task 0: Freeze Completed HR Behavior And Obtain Shared Contracts

**Ledger package:** Preservation prerequisite; no ledger status changes.

**Files:**
- Create: `tests/hr-preservation-contract.php`
- Create: `tests/hr-modal-confirmation.php`
- Create: `tests/hr-responsive-layout.php`
- Verify only: `company/admin/modules/hr/forms.php`
- Verify only: `company/admin/modules/hr/views/dashboard.php`
- Verify only: `tests/hr-database-driven-forms.php`
- Verify only: `tests/project-module-hierarchy.php`

**Interfaces:**
- Consumes: existing `yovel_admin_persist_hr_builder_form(...)`, `yovel_admin_persist_hr_form_submission(...)`, and HR projection behavior.
- Produces: a regression gate and written orchestration requests for shared modal, confirmation, Form Builder adapter, notification, attachment, print/export, and cross-module owner interfaces.

- [ ] **Step 1: Write preservation assertions before feature work**

Assert that all 13 HR section slugs still resolve, employee/department/job-position/team modal triggers remain present, Form Builder target tabs remain available, and current saved-form/submission routes remain unchanged.

```php
hr_contract_assert(function_exists('yovel_admin_persist_hr_builder_form'), 'HR Form Builder persistence moved or disappeared.');
hr_contract_assert(function_exists('yovel_admin_persist_hr_form_submission'), 'HR form submissions moved or disappeared.');
hr_contract_assert(array_keys(yovel_admin_hr_sections()) === $expectedSections, 'HR navigation contract changed.');
```

- [ ] **Step 2: Run the preservation test red/green cycle**

```bash
php tests/hr-preservation-contract.php
php tests/hr-database-driven-forms.php
php tests/project-module-hierarchy.php
```

Expected: the new characterization test passes without runtime edits; both existing suites remain green.

- [ ] **Step 3: Send shared requirements to orchestration**

Request stable module APIs for `data-record-modal`, `data-confirm-dialog`, `data-confirm-submit`, module Form Builder adapter registration, attachments/comments, notification intents, print/export, and owner-service calls. Do not edit the shared locked files from the HR task.

- [ ] **Step 4: Add browser-level acceptance tests after orchestration supplies the harness**

Verify focus trap/restoration, Escape/Cancel behavior, no POST before Confirm, one POST after Confirm, retained data after validation errors, no horizontal scrolling, and 12/8-to-stacked behavior at desktop and mobile widths.

- [ ] **Step 5: Commit the preservation gate**

```bash
git add tests/hr-preservation-contract.php tests/hr-modal-confirmation.php tests/hr-responsive-layout.php
git commit -m "test: preserve completed HR workspace contracts"
```

### Task 1: HR Setup And Employee Lifecycle Foundations

**Ledger package:** `HR-WP-01` — 14 rows (`11 MISSING`, `3 PARTIAL`).

**Capabilities:** HR Settings, Employment Type, Employee Grade, employee transfer/promotion/property history, grievances, interests, health insurance, department approvers, appraisees, organization chart, and HR Setup workspace completion.

**Files:**
- Create: `company/admin/modules/hr/setup.php`
- Create: `company/admin/modules/hr/views/setup.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Modify: `company/admin/modules/hr/employees.php`
- Modify: `company/admin/modules/hr/views/employee-profiles.php`
- Create: `tests/hr-setup-lifecycle.php`

**Interfaces:**
- Produces: `yovel_admin_hr_setup_data(array $company): array`, `yovel_admin_persist_hr_setup_record(ADOConnection $db, array $company, array $admin, string $recordType, array $input): array`, and `yovel_admin_persist_employee_transfer(...): array`.
- Consumes: existing effective-dated assignment service and orchestration-owned confirmation/Form Builder adapters.

- [ ] **Step 1: Write failing setup/lifecycle tests**

Cover company isolation, unique active master codes, effective-dated transfer history, transfer rollback, approval/status transitions, organization hierarchy cycle rejection, audit rows, direct read-back, and no mutation before confirmation.

```php
$transfer = yovel_admin_persist_employee_transfer($db, $company, $admin, $payload);
hr_setup_assert($transfer['transfer_status'] === 'SUBMITTED', 'Transfer lifecycle was not persisted.');
hr_setup_assert($activePrimaryAssignments === 1, 'Transfer left multiple active assignments.');
```

- [ ] **Step 2: Run the focused test and confirm missing symbols fail**

```bash
php tests/hr-setup-lifecycle.php
```

Expected: FAIL at the first missing setup persistence interface.

- [ ] **Step 3: Add normalized module-local schemas and services**

Create company-scoped master/transaction tables with stable keys, explicit statuses, effective dates, actor keys, indexes, and immutable employee property/transfer history. Keep the existing assignment table authoritative for current placement and invoke it inside the transfer transaction.

- [ ] **Step 4: Build the 12/8 setup workspace and modal workflows**

Render masters/history in the left panel and record state, actions, hierarchy, and Form Builder tools in the right panel. Every create/edit/transfer/promotion command opens a modal and reaches persistence only through Confirm.

- [ ] **Step 5: Register module-local Form Builder targets**

Expose protected keys/status/effective-date fields for each new record type and verify that published versions remain immutable.

- [ ] **Step 6: Verify and update only evidenced ledger rows**

```bash
php tests/hr-setup-lifecycle.php
php tests/hr-database-driven-forms.php
php tests/project-module-hierarchy.php
```

Change a row to `COMPLETE` only after its exact workflow and test evidence pass. Keep residual behavior `PARTIAL`.

- [ ] **Step 7: Commit the package**

```bash
git add company/admin/modules/hr/setup.php company/admin/modules/hr/views/setup.php company/admin/modules/hr/schema.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php company/admin/modules/hr/employees.php company/admin/modules/hr/views/employee-profiles.php tests/hr-setup-lifecycle.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: complete HR setup and employee lifecycle gaps"
```

### Task 2: Attendance, Check-ins, Shifts, And Overtime

**Ledger package:** `HR-WP-02` — 16 `MISSING` rows.

**Files:**
- Create: `company/admin/modules/hr/attendance.php`
- Create: `company/admin/modules/hr/views/attendance.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Create: `tests/hr-attendance-shifts.php`

**Interfaces:**
- Produces: `yovel_admin_persist_employee_checkin(...)`, `yovel_admin_mark_attendance(...)`, `yovel_admin_persist_shift_assignment(...)`, and `yovel_admin_persist_overtime_slip(...)`.
- Consumes: employee/assignment data; requests an orchestration-owned Payroll calculation/posting boundary for overtime salary components.

- [ ] **Step 1: Write failing attendance lifecycle tests**

Assert duplicate check-in handling, shift windows/time zones, attendance uniqueness per employee/date, request approval/rejection, auto-attendance idempotency, overtime validation, transaction rollback, audit, and company isolation.

- [ ] **Step 2: Run the test and confirm the service boundary is absent**

```bash
php tests/hr-attendance-shifts.php
```

- [ ] **Step 3: Implement attendance/shift schemas and transaction-owned services**

Persist raw check-ins separately from derived attendance, lock employee/date records during mutation, and retain source references for deterministic recalculation. Do not write Payroll tables directly.

- [ ] **Step 4: Build list/calendar/tool modes in the 12/8 workspace**

Use modal creation for check-ins, requests, shifts, schedules, and overtime. Put filters, selected-day status, import tool, and actions in the right panel.

- [ ] **Step 5: Add Form Builder targets and confirmation coverage**

Protect employee/date/status/source keys; verify Cancel and validation failure retain values and Confirm is the only mutation boundary.

- [ ] **Step 6: Verify, record exact evidence, and commit**

```bash
php tests/hr-attendance-shifts.php
php tests/hr-preservation-contract.php
git add company/admin/modules/hr/attendance.php company/admin/modules/hr/views/attendance.php company/admin/modules/hr/schema.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php tests/hr-attendance-shifts.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add HR attendance and shift workflows"
```

### Task 3: Leave Setup, Ledger, Allocation, And Applications

**Ledger package:** `HR-WP-03` — 18 rows (`17 MISSING`, `1 PARTIAL`).

**Files:**
- Create: `company/admin/modules/hr/leave.php`
- Create: `company/admin/modules/hr/views/leave.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Create: `tests/hr-leave-management.php`

**Interfaces:**
- Produces: `yovel_admin_calculate_leave_balance(...)`, `yovel_admin_persist_leave_allocation(...)`, `yovel_admin_persist_leave_application(...)`, and `yovel_admin_transition_leave_application(...)`.
- Consumes: employee assignments, holiday/attendance services, and orchestration notification intents.

- [ ] **Step 1: Write failing leave calculation and lifecycle tests**

Cover effective periods, carry-forward, negative balance policy, half days, holidays, overlap rejection, allocation adjustments, compensatory leave, immutable ledger entries, approve/reject/cancel, concurrent balance locks, and rollback.

```php
$balance = yovel_admin_calculate_leave_balance($db, $companyHash, $employeeKey, $leaveTypeKey, $asOf);
hr_leave_assert($balance['available'] === '4.5000', 'Leave balance calculation changed.');
```

- [ ] **Step 2: Run the focused test and observe the missing service failure**

```bash
php tests/hr-leave-management.php
```

- [ ] **Step 3: Implement masters, effective assignments, and append-only leave ledger**

Use explicit debit/credit entries linked to allocation/application/encashment sources. A lifecycle transaction writes the application, ledger effect, audit, and notification intent together.

- [ ] **Step 4: Build request and approval workspaces**

The left panel holds applications/ledger/calendar; the right panel holds balances, approval state, policy details, and actions. All new/application/allocation commands use modals and every lifecycle action uses confirmation.

- [ ] **Step 5: Replace the free-text holiday fragment with validated assignments**

Migrate without deleting the compatibility field, add effective dates, and preserve existing employee profile behavior while the governed assignment becomes authoritative.

- [ ] **Step 6: Verify package rows and commit**

```bash
php tests/hr-leave-management.php
php tests/hr-database-driven-forms.php
git add company/admin/modules/hr/leave.php company/admin/modules/hr/views/leave.php company/admin/modules/hr/schema.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php tests/hr-leave-management.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add HR leave management workflows"
```

### Task 4: Recruitment, Interviews, Offers, And Staffing

**Ledger package:** `HR-WP-04` — 23 `MISSING` rows.

**Files:**
- Create: `company/admin/modules/hr/recruitment.php`
- Create: `company/admin/modules/hr/views/recruitment.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Create: `tests/hr-recruitment.php`

**Interfaces:**
- Produces: requisition/opening/applicant/interview/offer services and `yovel_admin_convert_accepted_offer_to_onboarding(...)`.
- Consumes: job positions, departments, employees/interviewers, orchestration attachment/notification/print services, and Task 5 onboarding service.

- [ ] **Step 1: Write failing recruitment pipeline tests**

Assert requisition-to-opening linkage, applicant source, duplicate applicant policy, interview scheduling and feedback, offer terms/templates, accepted/rejected/withdrawn transitions, staffing-plan limits, audit, rollback, and company isolation.

- [ ] **Step 2: Run the focused test red**

```bash
php tests/hr-recruitment.php
```

- [ ] **Step 3: Implement normalized pipeline records and locked transitions**

Keep interview details/feedback and offer terms as child rows replaced transactionally under their parent lock. Accepted offers are immutable except through explicit withdrawal/amendment transitions.

- [ ] **Step 4: Build pipeline/list/detail modes in the 12/8 workspace**

Create every record in an accessible modal. Put stage, interview schedule, offer actions, attachments, and related records in the right panel.

- [ ] **Step 5: Add Form Builder targets and print payload adapters**

Use the existing HR builder for applicant/interview feedback/offer fields. Request shared print rendering for appointment/offer output rather than embedding copied upstream templates.

- [ ] **Step 6: Verify package rows and commit**

```bash
php tests/hr-recruitment.php
php tests/hr-modal-confirmation.php
git add company/admin/modules/hr/recruitment.php company/admin/modules/hr/views/recruitment.php company/admin/modules/hr/schema.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php tests/hr-recruitment.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add HR recruitment workflows"
```

### Task 5: Onboarding, Separation, Exit, And Employee Documents

**Ledger package:** `HR-WP-05` — 11 rows (`9 MISSING`, `2 PARTIAL`).

**Files:**
- Create: `company/admin/modules/hr/onboarding.php`
- Create: `company/admin/modules/hr/views/onboarding.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Modify: `company/admin/modules/hr/views/employee-profiles.php`
- Create: `tests/hr-onboarding-separation.php`

**Interfaces:**
- Produces: template/activity assignment services, document requirement status, exit interview workflow, separation workflow, and full-and-final checklist state.
- Consumes: recruitment conversion, employee status, orchestration attachments/notifications, Assets clearance service, and Finance settlement service.

- [ ] **Step 1: Write failing boarding/separation tests**

Assert template cloning, activity ownership/dates, document completion, onboarding completion eligibility, exit scheduling, separation approval, asset/outstanding blockers, idempotent full-and-final checks, employee status synchronization, audit, and rollback.

- [ ] **Step 2: Run the focused test red**

```bash
php tests/hr-onboarding-separation.php
```

- [ ] **Step 3: Implement template and transaction records without rewriting employee profiles**

Use linked workflow tables for onboarding/separation/exit details; preserve compatibility exit fields and update them inside the owning workflow transaction.

- [ ] **Step 4: Build onboarding and employee-document workspaces**

The left panel shows hires/activities/requirements; the right panel shows progress, owners, blockers, links, and actions. All create and consequential actions use modal plus confirmation.

- [ ] **Step 5: Add Form Builder and cross-owner blocker adapters**

Keep protected employee/status/date keys; request Assets and Finance read-only clearance contracts instead of direct table access.

- [ ] **Step 6: Verify package rows and commit**

```bash
php tests/hr-onboarding-separation.php
php tests/hr-database-driven-forms.php
git add company/admin/modules/hr/onboarding.php company/admin/modules/hr/views/onboarding.php company/admin/modules/hr/schema.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php company/admin/modules/hr/views/employee-profiles.php tests/hr-onboarding-separation.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add HR onboarding and separation workflows"
```

### Task 6: Performance, Goals, Skills, And Training

**Ledger package:** `HR-WP-06` — 28 `MISSING` rows.

**Files:**
- Create: `company/admin/modules/hr/performance.php`
- Create: `company/admin/modules/hr/views/performance.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Create: `tests/hr-performance-training.php`

**Interfaces:**
- Produces: appraisal-cycle/template/goal/KRA services, feedback/rating services, skill maps/assessments, and training event/result services.
- Consumes: employee/job-position records and orchestration notification intents.

- [ ] **Step 1: Write failing performance tests**

Assert template cloning, unique cycle/appraisee pairing, weighted score totals, reviewer authorization, immutable submitted appraisal ratings, goal progress bounds, expected-versus-employee skill gaps, training attendance/results, notification intents, rollback, and read-back.

- [ ] **Step 2: Run the focused test red**

```bash
php tests/hr-performance-training.php
```

- [ ] **Step 3: Implement normalized performance and training records**

Store scoring snapshots with submitted appraisals so later template edits do not rewrite history. Preserve stable child keys for goals/KRAs/ratings.

- [ ] **Step 4: Build performance dashboard and modal workflows**

Use cycle/employee views on the left and score, reviewer, skill-gap, training, and actions on the right. New cycles/templates/events open modals; submit/finalize actions require confirmation.

- [ ] **Step 5: Add Form Builder targets and verify package rows**

```bash
php tests/hr-performance-training.php
php tests/hr-modal-confirmation.php
git add company/admin/modules/hr/performance.php company/admin/modules/hr/views/performance.php company/admin/modules/hr/schema.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php tests/hr-performance-training.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add HR performance and training workflows"
```

### Task 7: Employee Advances, Expenses, Travel, And Vehicles

**Ledger package:** `HR-WP-07` — 15 `MISSING` rows.

**Files:**
- Create: `company/admin/modules/hr/expenses.php`
- Create: `company/admin/modules/hr/views/expenses.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Create: `tests/hr-expenses-travel.php`

**Interfaces:**
- Produces: advance/claim/travel/vehicle workflow services and approved accounting payloads.
- Consumes: Finance account/tax/payment/posting services, Projects allocation service, and orchestration attachments.

- [ ] **Step 1: Write failing expense/travel tests**

Assert claim totals/taxes, advance allocation limits, receipt requirements, multi-step approval, travel costing/itinerary, vehicle service mileage chronology, cancellation/reversal, Finance payload idempotency, rollback, and company isolation.

- [ ] **Step 2: Run the focused test red**

```bash
php tests/hr-expenses-travel.php
```

- [ ] **Step 3: Implement HR-authoritative workflow data**

Do not create GL/payment rows in HR. Build immutable approved payloads keyed for Finance idempotency and retain Finance result keys.

- [ ] **Step 4: Build the 12/8 expense workspace and modal flows**

Put claims/travel/vehicles on the left and totals, receipts, approval, accounting linkage, and actions on the right. All create/import/submit/approve/cancel actions follow modal and confirmation contracts.

- [ ] **Step 5: Add Form Builder targets, verify, and commit**

```bash
php tests/hr-expenses-travel.php
php tests/hr-preservation-contract.php
git add company/admin/modules/hr/expenses.php company/admin/modules/hr/views/expenses.php company/admin/modules/hr/schema.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php tests/hr-expenses-travel.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add HR expense and travel workflows"
```

### Task 8: Daily Work Summaries And Team Communications

**Ledger package:** `HR-WP-08` — 5 `MISSING` rows.

**Files:**
- Create: `company/admin/modules/hr/communications.php`
- Create: `company/admin/modules/hr/views/communications.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Create: `tests/hr-communications.php`

**Interfaces:**
- Produces: work-summary group/member/response services and notification intents.
- Consumes: employee/team records and orchestration delivery/background-job services.

- [ ] **Step 1: Write failing communication tests**

Assert group membership scope, one summary per employee/date policy, due reminders, response capture, delivery intent idempotency, disabled-recipient behavior, audit, and rollback.

- [ ] **Step 2: Run the focused test red**

```bash
php tests/hr-communications.php
```

- [ ] **Step 3: Implement communication records and outbox intents**

Persist HR records and notification intents in one transaction; leave actual email/PWA delivery and retries to Operations.

- [ ] **Step 4: Build team update and summary workspaces**

Use the left panel for timeline/responses and the right panel for recipients, delivery state, schedule, and actions. Add/New opens a modal; send/close/resend requires confirmation.

- [ ] **Step 5: Verify package rows and commit**

```bash
php tests/hr-communications.php
git add company/admin/modules/hr/communications.php company/admin/modules/hr/views/communications.php company/admin/modules/hr/schema.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php tests/hr-communications.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add HR work summary communications"
```

### Task 9: HR Reports And Analytics

**Ledger package:** `HR-WP-09` — 35 `MISSING` rows.

**Files:**
- Create: `company/admin/modules/hr/reports.php`
- Create: `company/admin/modules/hr/views/reports.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Create: `tests/hr-reports.php`

**Interfaces:**
- Produces: `yovel_admin_hr_report_catalog(): array` and `yovel_admin_run_hr_report(ADOConnection $db, array $company, array $admin, string $reportCode, array $filters): array`.
- Consumes: completed HR domain services/read models; requests Projects/Finance-owned inputs for utilization, profitability, and unpaid claims.

- [ ] **Step 1: Write failing report contract tests**

For every report/report-code ledger row, assert a catalog entry, allowed filters, company-scoped parameterized query, stable columns, empty/populated results, totals, date boundaries, and export payload. Pair duplicate JSON/Python source rows to one BuilderX report capability while retaining evidence on both rows.

- [ ] **Step 2: Run the report test red**

```bash
php tests/hr-reports.php
```

- [ ] **Step 3: Implement the catalog and report query services**

Use structured definitions and parameterized queries; do not port upstream Python. Cross-owner reports consume orchestration-approved read services rather than direct foreign-table writes.

- [ ] **Step 4: Build report results in the 12/8 contract**

Results/charts occupy the left panel; filters, grouping, export, and report metadata occupy the right. Export actions require confirmation when they expose personal/payroll data.

- [ ] **Step 5: Verify every report row and commit**

```bash
php tests/hr-reports.php
php tests/hr-responsive-layout.php
git add company/admin/modules/hr/reports.php company/admin/modules/hr/views/reports.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php tests/hr-reports.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add HR reports and analytics"
```

### Task 10: Payroll Setup, Salary Structures, And Payroll Processing

**Ledger package:** `HR-WP-10` — 25 `MISSING` rows.

**Files:**
- Create: `company/admin/modules/hr/payroll.php`
- Create: `company/admin/modules/hr/views/payroll.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Create: `tests/hr-payroll.php`

**Interfaces:**
- Produces: effective payroll settings/period/components/structures/assignments, salary calculation snapshots, payroll entry/slip lifecycle, corrections, withholding, and Finance posting payloads.
- Consumes: attendance/leave/overtime, employee assignments, and Finance journal/payment owner services.

- [ ] **Step 1: Freeze localization and calculation policy before code**

Record currency, precision, rounding, proration, working-day, tax jurisdiction, correction, and posting policies. Legal/tax rules require authoritative verification and orchestration approval; do not invent defaults.

- [ ] **Step 2: Write failing payroll calculation/lifecycle tests**

Assert effective salary structure selection, formula dependency order/cycle rejection, attendance/leave proration, additional salary/arrears/incentives, withholding, bulk assignment, immutable submitted slips, correction/reversal, duplicate payroll prevention, posting idempotency, and rollback.

- [ ] **Step 3: Run the payroll test red**

```bash
php tests/hr-payroll.php
```

- [ ] **Step 4: Implement versioned setup and immutable calculation snapshots**

Each salary slip stores the component inputs, formulas/versions, attendance facts, precision, and totals used at submission. Corrections create explicit linked adjustments; they do not rewrite submitted slips.

- [ ] **Step 5: Build payroll workspaces with strict authorization**

Use the left panel for periods/entries/slips and the right panel for calculation state, exceptions, posting, access scope, and actions. Every create/submit/cancel/correct/post command uses modal plus confirmation and redacts payroll data without permission.

- [ ] **Step 6: Add Form Builder adapters without exposing protected calculations**

Allow safe supplemental fields while protecting component formulas, calculated totals, lifecycle state, source facts, and posting keys.

- [ ] **Step 7: Verify package rows and commit**

```bash
php tests/hr-payroll.php
php tests/hr-modal-confirmation.php
git add company/admin/modules/hr/payroll.php company/admin/modules/hr/views/payroll.php company/admin/modules/hr/schema.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php tests/hr-payroll.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add HR payroll setup and processing"
```

### Task 11: Payroll Tax, Benefits, And Gratuity

**Ledger package:** `HR-WP-11` — 18 `MISSING` rows.

**Files:**
- Create: `company/admin/modules/hr/payroll-tax.php`
- Create: `company/admin/modules/hr/views/payroll-tax.php`
- Modify: `company/admin/modules/hr/schema.php`
- Modify: `company/admin/modules/hr/functions.php`
- Create: `tests/hr-payroll-tax-benefits.php`

**Interfaces:**
- Produces: effective tax slabs, exemption declarations/proofs, benefit application/claim/ledger, gratuity rules/slabs, and calculation snapshots consumed by Task 10.
- Consumes: verified jurisdiction policy, attachments, employee/payroll facts, and Finance posting services.

- [ ] **Step 1: Write failing tax/benefit/gratuity tests from approved rules**

Cover effective dates, slab boundaries, taxable components, exemption caps/proofs, benefit claims/ledger, gratuity eligibility/slabs, precision/rounding, retroactive policy protection, audit, and rollback.

- [ ] **Step 2: Run the focused test red**

```bash
php tests/hr-payroll-tax-benefits.php
```

- [ ] **Step 3: Implement versioned policies and calculation services**

Never silently mutate historical tax/benefit/gratuity outcomes. New rules receive effective versions; submitted payroll records retain the policy keys used.

- [ ] **Step 4: Build protected 12/8 workspaces and modals**

The right panel must show effective policy, approval/proof state, calculation explanation, and audit links. Sensitive fields require explicit permissions.

- [ ] **Step 5: Verify package rows and commit**

```bash
php tests/hr-payroll-tax-benefits.php
php tests/hr-payroll.php
git add company/admin/modules/hr/payroll-tax.php company/admin/modules/hr/views/payroll-tax.php company/admin/modules/hr/schema.php company/admin/modules/hr/functions.php tests/hr-payroll-tax-benefits.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add payroll tax and benefit workflows"
```

### Task 12: Payroll Reports, Workspaces, Print, And Remittance Outputs

**Ledger package:** `HR-WP-12` — 26 `MISSING` rows.

**Files:**
- Create: `company/admin/modules/hr/payroll-reports.php`
- Create: `company/admin/modules/hr/views/payroll-reports.php`
- Modify: `company/admin/modules/hr/functions.php`
- Modify: `company/admin/modules/hr/navigation.php`
- Create: `tests/hr-payroll-outputs.php`

**Interfaces:**
- Produces: payroll report catalog/results, salary-slip print payloads, bank/ECS remittance payloads, and Payroll/Tax & Benefits workspace data.
- Consumes: submitted immutable payroll snapshots, orchestration print/export services, and Finance bank/payment services.

- [ ] **Step 1: Write failing output contract tests**

Assert report columns/totals, year-to-date boundaries, CTC breakup, tax deductions, provident/professional tax inputs, bank remittance control totals, salary register, print redaction, export authorization, and immutable source keys.

- [ ] **Step 2: Run the focused test red**

```bash
php tests/hr-payroll-outputs.php
```

- [ ] **Step 3: Implement report and output adapters**

Generate structured BuilderX payloads; do not copy upstream templates. Bank/export generation must be deterministic and auditable, and regeneration must preserve the source payroll version.

- [ ] **Step 4: Build Payroll and Tax & Benefits workspaces**

Use results/records in the left panel and filters, totals, print/export, remittance state, and actions in the right. Consequential export/remittance commands require confirmation.

- [ ] **Step 5: Verify package rows and commit**

```bash
php tests/hr-payroll-outputs.php
php tests/hr-payroll.php
php tests/hr-responsive-layout.php
git add company/admin/modules/hr/payroll-reports.php company/admin/modules/hr/views/payroll-reports.php company/admin/modules/hr/functions.php company/admin/modules/hr/navigation.php tests/hr-payroll-outputs.php docs/erpnext-parity/ledgers/hr.json
git commit -m "feat: add payroll reports and outputs"
```

### Task 13: Parity Closure And Evidence Review

**Ledger scope:** All 257 rows.

**Files:**
- Modify: `docs/erpnext-parity/ledgers/hr.json`
- Verify: `company/admin/modules/hr/`
- Verify: `tests/hr-*`
- Verify: `tests/project-module-hierarchy.php`

**Interfaces:**
- Consumes: all completed packages and orchestrator-owned shared integrations.
- Produces: a final evidence-backed ledger with no applicable `MISSING`/`PARTIAL` rows.

- [ ] **Step 1: Validate ledger integrity and unresolved rows**

```bash
jq -e '.rows | length == 257' docs/erpnext-parity/ledgers/hr.json
jq -r '.rows[] | select((.status == "COMPLETE" and (.evidence | length == 0)) or .reason == "") | .source_id' docs/erpnext-parity/ledgers/hr.json
jq -r '.rows[] | select(.status == "MISSING" or .status == "PARTIAL") | [.source_id,.status,.reason] | @tsv' docs/erpnext-parity/ledgers/hr.json
```

Expected: 257 rows; no evidence-less complete row; no applicable unresolved row before declaring parity.

- [ ] **Step 2: Run all focused and preservation suites**

```bash
php tests/hr-preservation-contract.php
php tests/hr-database-driven-forms.php
php tests/hr-setup-lifecycle.php
php tests/hr-attendance-shifts.php
php tests/hr-leave-management.php
php tests/hr-recruitment.php
php tests/hr-onboarding-separation.php
php tests/hr-performance-training.php
php tests/hr-expenses-travel.php
php tests/hr-communications.php
php tests/hr-reports.php
php tests/hr-payroll.php
php tests/hr-payroll-tax-benefits.php
php tests/hr-payroll-outputs.php
php tests/hr-modal-confirmation.php
php tests/hr-responsive-layout.php
php tests/project-module-hierarchy.php
```

- [ ] **Step 3: Run syntax, route, browser, and cleanup gates**

Lint every HR PHP file, verify all HR routes return successful authenticated responses, inspect desktop/mobile screenshots for the 12/8 contract, verify no test fixture remains, and run `git diff --check`.

- [ ] **Step 4: Review shared and cross-module acceptance**

Confirm Finance postings, Projects inputs, Assets clearances, Operations notifications/jobs, attachments, printing, export, modal confirmation, and Form Builder adapters use owner contracts and have integration evidence.

- [ ] **Step 5: Commit final parity evidence**

```bash
git add docs/erpnext-parity/ledgers/hr.json tests/hr-preservation-contract.php tests/hr-database-driven-forms.php tests/hr-setup-lifecycle.php tests/hr-attendance-shifts.php tests/hr-leave-management.php tests/hr-recruitment.php tests/hr-onboarding-separation.php tests/hr-performance-training.php tests/hr-expenses-travel.php tests/hr-communications.php tests/hr-reports.php tests/hr-payroll.php tests/hr-payroll-tax-benefits.php tests/hr-payroll-outputs.php tests/hr-modal-confirmation.php tests/hr-responsive-layout.php tests/project-module-hierarchy.php company/admin/modules/hr/setup.php company/admin/modules/hr/attendance.php company/admin/modules/hr/leave.php company/admin/modules/hr/recruitment.php company/admin/modules/hr/onboarding.php company/admin/modules/hr/performance.php company/admin/modules/hr/expenses.php company/admin/modules/hr/communications.php company/admin/modules/hr/reports.php company/admin/modules/hr/payroll.php company/admin/modules/hr/payroll-tax.php company/admin/modules/hr/payroll-reports.php
git commit -m "docs: close verified HR parity ledger"
```

## Risk-Ordered Implementation Sequence

1. Task 0 preservation/shared contracts.
2. Task 1 employee/setup masters needed by all later workflows.
3. Task 2 attendance and shifts.
4. Task 3 leave ledger and approvals.
5. Task 4 recruitment.
6. Task 5 onboarding/separation/documents.
7. Task 6 performance/training.
8. Task 7 expenses/travel/vehicles after Finance contracts are available.
9. Task 8 communications after Operations notification contracts are available.
10. Task 9 HR reports after domain read models stabilize.
11. Task 10 payroll setup/processing only after attendance, leave, overtime, Finance posting, and legal policy gates are approved.
12. Task 11 tax/benefits/gratuity only after authoritative jurisdiction rules are approved.
13. Task 12 payroll outputs after submitted payroll snapshots are stable.
14. Task 13 final evidence closure.

## Highest-Risk Gaps

- Payroll and tax calculations are legally and financially sensitive; no local payroll engine, versioned policy model, or pinned localization policy currently exists.
- Attendance/shift/overtime and leave balances require concurrency-safe effective-date calculations; the current attendance and leave routes are queued placeholders.
- Expense, payroll, project profitability, full-and-final asset clearance, notifications, attachments, printing, and exports depend on cross-module/shared owner services that HR must not bypass.
- The existing confirmation behavior lacks a dedicated HR interaction suite proving no write occurs before Confirm and that validation failures rehydrate modal state.
- The ledger is broad: 228 product rows are still missing, including 35 HR report rows and 69 payroll product/output rows.

## Shared Escalations Required

The HR implementation task must send these requests to the orchestration owner before the dependent package begins:

1. Shared accessible modal/confirmation controller and browser test harness.
2. Universal module Form Builder adapter registration for new HR targets.
3. Attachment/comment/timeline contracts for applicants, claims, onboarding, tax proofs, and employee documents.
4. Notification/outbox/background-job contracts for interviews, training, leave, exits, and daily summaries.
5. Print/export contracts for offers, appointment letters, salary slips, reports, and bank files.
6. Finance owner services for expense, payroll posting/payment, and settlement.
7. Projects read services for utilization/profitability and cost allocation.
8. Assets clearance service for full-and-final settlement.

No shared or foreign-module file is assigned to the HR task by this plan.
