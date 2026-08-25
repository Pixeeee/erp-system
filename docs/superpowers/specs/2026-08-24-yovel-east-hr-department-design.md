# Yovel East HR Department Design

## Goal

Develop the Yovel East ERP System HR Department one feature at a time. The existing sidebar dropdown already lists HR features, but each item currently points to a dashboard anchor. This work turns those HR dropdown items into real company-admin HR workspace sections while leaving the other ERP departments unchanged.

## References

- ERP base: `https://github.com/frappe/erpnext`
- Modern HR reference: `https://github.com/frappe/hrms`
- Current local route: `/erpsystem/company/yovel-east/admin/`
- Current HR sidebar source: `company/admin/index.php`

ERPNext still provides core setup objects such as Employee, Department, Designation, Branch, and Company. Current HR and Payroll objects live in Frappe HR/HRMS, which depends on ERPNext. The BuilderX implementation should follow the same dependency shape without copying Frappe internals directly.

## Scope

Build only the HR Department area in this cycle. The sidebar groups for Accounting, Sales, Procurement, Inventory, Manufacturing, Projects, Support, Assets, Operations, Compliance, and Mobile Stockroom remain as dashboard feature anchors until their own development cycles.

The HR dropdown items become sections under a new HR view:

- `?view=hr&section=employee-profiles`
- `?view=hr&section=departments`
- `?view=hr&section=job-positions`
- `?view=hr&section=teams`
- `?view=hr&section=attendance`
- `?view=hr&section=leave-requests`
- `?view=hr&section=leave-approvals`
- `?view=hr&section=payroll-access`
- `?view=hr&section=recruitment`
- `?view=hr&section=onboarding`
- `?view=hr&section=employee-documents`
- `?view=hr&section=hr-reports`

Unknown HR sections fall back to `employee-profiles`.

## Build Order

1. HR foundation: Employee profiles, Departments, Job positions, Teams.
2. Time: Attendance.
3. Leave: Leave requests and Leave approvals.
4. Payroll gate: Payroll access only, not full payroll processing.
5. Hiring: Recruitment and Onboarding.
6. Records: Employee documents.
7. Reporting: HR reports.

This order is deliberate. Employees need branch, department, job position, team, and reporting-manager references before attendance or leave can produce reliable data. Payroll access and reports depend on clean employee, attendance, and leave records.

## Phase Manager Alignment

Create or use a Phase Manager phase for Yovel East HR Department development. Track each dropdown item as a task in dependency order, starting with Employee profiles. Each task should remain small enough to implement, verify, and review independently before moving to the next HR feature.

The first Phase Manager task should be Employee profiles. Its implementation may create the minimal shared HR foundation schema needed for stable employee references, but the only completed user-facing HR section in that task is Employee profiles.

## First Slice: Employee Profiles

Employee profiles are the first real HR section. The section should support a company-admin workflow for listing, creating, editing, and status-managing Yovel East employees.

Initial employee fields:

- Employee code
- First name
- Middle name
- Last name
- Full name
- Employment status
- Branch
- Department
- Job position
- Team
- Reports to
- Date of birth
- Date of joining
- Employment type
- Mobile number
- Company email
- Personal email
- Notes

The field set follows ERPNext's Employee shape for the basics: employee ID, full name, status, company, branch, department, designation, reporting manager, joining date, mobile, company email, and personal email. BuilderX should store only the fields needed for this ERP system now.

## Architecture

Extend the shared company-admin PHP route because `company/yovel-east/admin/index.php` delegates to `company/admin/index.php`.

Add an HR view beside the existing dashboard and platform views:

- `dashboard`
- `platform`
- `hr`

Add HR section metadata in PHP so the sidebar, mobile navigation, title, section descriptions, and route validation share one source of truth.

Use server-rendered pages and native POST forms, matching the current Company Platform implementation. Do not introduce a new JavaScript application for HR in this slice.

## Data Model

All HR tables must be company scoped by the active Yovel East company record. Persist both `company_key` and `company_key_hash` to match the current company-admin platform tables.

### `project_company_hr_employee`

Stores employee profile records.

Fields:

- `employee_key`
- `company_key`
- `company_key_hash`
- `employee_code`
- `first_name`
- `middle_name`
- `last_name`
- `employee_name`
- `employee_status`
- `branch_key`
- `department_key`
- `job_position_key`
- `team_key`
- `reports_to_employee_key`
- `date_of_birth`
- `date_of_joining`
- `employment_type`
- `mobile_number`
- `company_email`
- `personal_email`
- `employee_notes`
- `created_by_admin_key`
- `updated_by_admin_key`
- timestamps

Indexes and uniqueness:

- Unique `(company_key_hash, employee_code)`
- Index `(company_key_hash, employee_status)`
- Index `(company_key_hash, branch_key)`
- Index `(company_key_hash, department_key)`
- Index `(company_key_hash, reports_to_employee_key)`

Statuses:

- `DRAFT`
- `ACTIVE`
- `INACTIVE`
- `ON_LEAVE`
- `SEPARATED`
- `DELETED`

### Existing Department Integration

Reuse the existing `project_company_department` records for the HR Departments section. The HR Departments section should point to and reuse the already implemented company-scoped department data rather than creating a duplicate department table.

### HR Foundation Reference Tables

Create these with the Employee profiles slice so employee references are stable from the start:

- `project_company_hr_job_position`
- `project_company_hr_team`

The first Employee profiles UI may show empty job position and team selectors until the Job positions and Teams sections are implemented. The later sections add their own list, create, edit, status, and audit workflows without changing the employee table shape.

### Later HR Tables

Add these only when their sections are implemented:

- `project_company_hr_attendance`
- `project_company_hr_leave_request`
- `project_company_hr_payroll_access`
- `project_company_hr_job_opening`
- `project_company_hr_onboarding`
- `project_company_hr_employee_document`

## First Slice UI

The HR Employee profiles screen uses the same shell style as Platform:

- Left side: employee table with code, name, department, job position, branch, status, and edit action.
- Right side: create/edit employee form.
- Top summary: HR Department title, active section, employee count, active employee count, and incomplete profile count.
- Empty state: prompts the admin to create the first employee.

The form should use compact, accessible controls and existing confirmation modal behavior before saving. Keep form text operational and avoid tutorial copy.

## Data Flow

1. Company admin opens an HR route.
2. Server validates the company and current company-admin session.
3. HR schema is ensured.
4. HR data is loaded from company-scoped tables.
5. A POST save validates CSRF and company-admin access.
6. The save runs inside an ADODB transaction.
7. Inputs are validated and related branch, department, job position, team, and manager keys are checked against the same company hash.
8. The employee row is created or updated with parameterized SQL.
9. The exact saved row is read back and compared.
10. An audit record is written.
11. The request redirects back to the HR section and rehydrates from the database.

## Validation Rules

- Employee code is required and must use uppercase letters, numbers, underscores, or hyphens.
- First name and full employee name are required.
- Email fields must be valid email addresses when present.
- Branch, department, job position, team, and reporting manager references must belong to Yovel East.
- An employee cannot report to themselves.
- Date of birth and date of joining must be valid dates when present.
- Soft deletes set status to `DELETED`; no physical delete through the normal UI.

## Error Handling

Validation failures show the existing flash error and return to the HR section. Database failures roll back the transaction and show a concise error. Read-back mismatches are treated as failures and roll back before the user sees success.

## Testing

First slice verification:

- PHP lint for `company/admin/index.php` and `company/yovel-east/admin/index.php`.
- Authenticated HTTP check that `?view=hr&section=employee-profiles` renders.
- Authenticated HTTP check that the HR sidebar link points to the HR route, not the dashboard anchor.
- Create, update, and soft-delete a temporary employee record, then verify direct database read-back.
- Desktop and mobile browser checks for the HR employee list and form.

Later slices repeat the same pattern with section-specific create, update, status, and report checks.

## Non-Goals

- Do not build full payroll processing in this HR cycle.
- Do not convert the whole ERP System sidebar into real modules yet.
- Do not replace the current company-admin PHP route with a frontend SPA.
- Do not duplicate existing company department records into a second HR-only department table.
