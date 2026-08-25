# Yovel East Employee Profile Enhancement Design

## Goal

Make Employee Profiles a record-centered HR workspace inspired by ERPNext while preserving BuilderX's existing two-panel layout, company scope, native PHP forms, confirmation flow, and HR form schema.

## Approved Direction

- Keep the employee list as the dominant eight-column panel.
- Use the four-column panel for contextual navigation and employee information rather than permanent layout-editing controls.
- Preserve clicking and dragging a feature onto an employee as shortcuts, but keep ordinary click navigation discoverable and keyboard accessible.
- Keep Add Employee and Edit Employee in the existing responsive modal.
- Keep Form Builder and Customize Form owned by the HR Dashboard rather than the Employee Profiles page.

## Employee List

The list header contains the title, scoped description, metrics, and Add Employee. The filter area provides employee ID, name, department, status, and branch controls, a clear action, and visible result count. Table rows remain semantic HTML and expose name, department/contact context, status, designation, employee code, and actions.

Selecting a row establishes the active employee context. The selected row has an explicit visual state, and the right panel updates without requiring navigation. Clicking the employee name or the View icon opens the record. Status transitions move into one compact menu to reduce row clutter; status writes retain the existing confirmation and server transaction flow.

## Context Panel

The right panel uses three views:

- `Sections`: Overview, Joining, Address & Contacts, Attendance & Leaves, Salary, Personal, Profile, Exit, and Connections.
- `Related`: Attendance, leave, payroll, documents, and onboarding destinations with availability states.
- `Insights`: profile completeness, missing assignment data, contact readiness, and lifecycle reminders derived from loaded employee data.

Sections are clickable. Existing drag-and-drop remains an optional accelerator and shows the destination employee while dragging. Reordering controls are removed from the daily record workspace because form structure is managed from the HR Dashboard.

## Employee Modal

The modal has three regions: opaque header, independently scrolling body, and opaque footer. Its header shows initials, employee name, code, status, designation, department, and an unsaved-state label. The tab menu is the first body element and remains sticky while content scrolls.

The existing eight ERP sections remain. `Connections` is added as a read-only overview of related HR destinations. Conditional sections reveal exit fields only for separated employees. The footer remains informational with Close or Cancel; create submission stays in the form body and edit submission stays in the document header.

## Accessibility And Responsive Behavior

- Use semantic table, tab, menu, and dialog roles.
- Preserve visible focus states and descriptive labels on icon controls.
- Keep click/keyboard alternatives for every drag interaction.
- Stack the two panels below the desktop breakpoint.
- Prevent page scrolling while the employee modal is open.
- Keep modal header, tabs, and footer opaque during internal scrolling.

## Persistence Boundary

This enhancement does not add or change database tables. Employee create, update, and status writes continue through the current parameterized, company-scoped ADODB transactions and confirmation guard. Insights are derived from already-loaded employee values. Related destinations are links or clearly disabled placeholders until their HR sections are implemented.

## Verification

- PHP lint both company-admin entrypoints.
- Confirm authenticated HR routes return successfully when a session is available.
- Inspect rendered markup for semantic tabs, menu controls, filter labels, and modal regions.
- Verify filtering, row selection, context switching, feature click/drag, modal tabs, and conditional exit behavior in a browser session.

