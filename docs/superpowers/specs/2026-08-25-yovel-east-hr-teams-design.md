# Yovel East HR Teams Design

## Objective

Replace the queued Teams placeholder with a complete, company-scoped HR workspace that follows the established Employee Profiles, Departments, and Job Positions interaction standard.

## Scope

- Use the existing `project_company_hr_team` table and employee `team_key` relationship.
- Add transactional Team create, update, status, delete, audit, and read-back verification workflows.
- Add dedicated Team form metadata for customizable Overview and Details sections.
- Render an aligned responsive 8/4 workspace with a compact team directory and focused right panel.
- Use modal workflows for creating, viewing, and editing teams.
- Show assigned employee counts without duplicating assignment data.

## Workspace

The left panel contains Team metrics, search filters, the Add Team command, and a table with Team, Members, Status, and icon actions. Empty state content leads directly to Add Team.

The right panel contains only working Team workflows: Add Team, Manage Forms, Employee Profiles, and the real Overview and Details form sections. Form sections can be opened or reordered and can be dragged onto a Team row to open that record at the selected section.

At desktop sizes the panels use an 8/4 grid. They stack at smaller widths without page-level horizontal overflow. Both headers align and the right panel avoids unnecessary internal scrolling at 100% zoom.

## Team Modal

The modal has two switchable sections:

- Overview: Team code, Team name, and status.
- Details: Description, assigned-member summary, and custom fields.

Create and edit use the same server-backed form. Submission requires confirmation and returns clear flash feedback. Assigned employees remain owned by Employee Profiles; Teams displays their membership and links to the employee workspace rather than creating a second assignment mechanism.

## Data Flow

Team writes are company-scoped and parameterized. Each write runs inside an ADODB transaction, validates UUIDs and allowed statuses, checks code uniqueness, verifies the persisted row directly, saves typed custom values, writes an audit record, and commits only after verification. Status changes use the same transaction and read-back guarantees.

## Verification

- PHP syntax checks for all changed modules.
- HR database-driven forms and modular architecture tests.
- HTTP checks for the Teams route.
- Browser checks at desktop and compact widths for alignment, overflow, modal behavior, tab switching, and right-panel content.
