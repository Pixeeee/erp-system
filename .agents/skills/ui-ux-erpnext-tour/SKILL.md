---
name: ui-ux-erpnext-tour
description: Design and implement ERPNext-inspired module workspaces in BuilderX, including minimalist setup cards, guided tours, shortcuts, reports/masters grids, and clean module navigation. Use when applying an ERPNext-style onboarding or workspace hub pattern to any ERP module.
---

# UI/UX ERPNext Tour Workspace

Use this skill when the user asks for an ERPNext-style module workspace, setup guide, onboarding tour, workspace hub, shortcuts section, reports/masters directory, or a minimalist module page similar to the ERPNext HR workspace.

The reference pattern comes from the ERPNext HR workspace: a quiet light surface, a module title, a setup checklist card with a selected step, explanatory copy, a `Show Tour` action, then compact shortcut links and grouped reports/masters below. If the live ERPNext demo is unavailable or behind login, use the provided screenshots or current user reference as the visual source.

## Design Goals

- Make the first screen teach the admin what to do next without turning the page into a marketing landing page.
- Keep the style minimal, spacious, and operational: thin borders, soft neutral surfaces, restrained type, compact buttons, and clear grouping.
- Preserve the existing BuilderX shell, sidebar, header, footer, dark-mode support, role scope, forms, CRUD actions, and confirmation behavior.
- Apply the pattern per module. HR content must stay HR-specific, Accounting content accounting-specific, Sales content sales-specific, and so on.

## Workspace Pattern

Use this structure for a module hub or feature overview:

- Header: module name and short breadcrumb/context only. Avoid large hero sections.
- Setup card: one bordered surface with a title, subtitle, dismiss action, setup step list on the left, and selected-step explanation on the right.
- Step list: compact rows with status icons, active row highlight, optional `Skip`, and labels written as tasks.
- Tour trigger: a `Show Tour` button in the selected-step explanation area.
- Shortcuts: a low-friction grid of common module destinations with external/open affordances and status badges only when useful.
- Reports & Masters: grouped link columns using clear headings such as Setup, Employee, Leaves, Attendance, Settings, Key Reports, or module-appropriate equivalents.
- Scroll behavior: the workspace can scroll, but persistent shell header/footer should remain stable. Internal long panels should own their scroll region.

## Guided Tour Behavior

Implement a guided tour when the user asks for a tour or when adding this workspace pattern:

- Use a small overlay/popover tour that points to real UI targets, not a generic full-screen tutorial.
- Include 4-7 steps maximum for a module overview: setup card, checklist, selected detail, shortcuts, reports/masters, and primary create/customize action when present.
- Each step needs a short title, one-sentence body, `Next`, `Back`, `Finish`, and `Skip` or close controls.
- Highlight the active target with a subtle ring or glow. Dim the rest of the page lightly; do not obscure the target.
- Store dismissed/completed tour state per company and module in local storage unless the project already has a server preference store.
- Keep keyboard support: Escape closes, Tab remains trapped in the tour controls while the overlay is active, and focus returns to the `Show Tour` trigger.
- Do not auto-start repeatedly after dismissal. A visible `Show Tour` action must allow reopening.

## Visual Style

- Prefer a light ERPNext-inspired workspace when the page or module requests this style, while preserving dark-mode readability if the shell is dark.
- Use neutral backgrounds, thin borders, small-radius surfaces, and restrained accent colors.
- Avoid decorative gradients, oversized hero text, nested cards, heavy shadows, and bright multi-color dashboards.
- Icons should clarify state or destination. Use a consistent icon family and accessible labels for icon-only controls.
- Buttons should be compact and familiar: `Dismiss`, `Show Tour`, `Skip`, `Next`, `Finish`, and direct module actions.
- Keep text concise and functional. Do not add instructional paragraphs outside the setup/tour context.

## Content Rules

- Rename ERPNext examples to the current module language. For Job Positions, use position, designation, assignment, responsibilities, and status. For Departments, use branch, department, assignment scope, and access scope. For Employee Profiles, use employee master data, joining, contacts, salary, and exit.
- Do not copy ERPNext text verbatim except for very short UI labels such as `Show Tour` or `Dismiss`.
- Do not link to ERPNext/Frappe pages from the user’s ERP unless explicitly requested; treat the demo as design inspiration, not product navigation.
- Keep shortcuts and reports realistic for the current module. If a destination does not exist yet, render it as disabled or omit it rather than creating a broken link.

## HR Dashboard Form Builder

When building the HR Department dashboard, include the Form Builder as a dashboard-level workspace rather than burying it inside one HR feature:

- Keep the dashboard recognizable as an ERPNext-inspired HR module hub. It should retain setup guidance, shortcuts, operational destinations, and reports/tools; Form Builder is one feature card or tool launched from this hub.
- Dashboard ownership: HR Dashboard owns `Form Builder`, `New Form`, and `Existing Form`. Feature pages such as Employee Profile, Departments, and Job Positions should focus on records and should not duplicate custom-form builder buttons in their panel headers.
- Place HR feature buttons/tabs inside the Form Builder header, then place `New Form` and `Existing Form` beside them so admins first choose the feature and then choose whether they are creating or editing.
- In `New Form` mode, show a blank Google Forms/Odoo-style builder canvas for the selected feature. In `Existing Form` mode, show both live built-in ERP section forms and saved custom forms, then load the chosen form into the appropriate editor.
- For Employee Profiles, list the eight built-in section forms: Overview, Joining, Address & Contacts, Attendance & Leaves, Salary, Personal, Profile, and Exit. Show live field counts and keep this list distinct from admin-created custom forms.
- Provide an HR feature selector in the dashboard header or builder toolbar with entries such as Employee Profile, Departments, Job Positions, Teams, Attendance, Leave Requests, Onboarding, Employee Documents, and HR Reports.
- Show existing custom forms for the selected HR feature in a compact list. Clicking a form should load that form into the builder for editing.
- Keep `Customize Form` separate from dashboard Form Builder. `Customize Form` edits the built-in ERP master form; dashboard Form Builder creates and edits separate admin-created forms.
- Use the ERPNext workspace pattern around the builder: setup card or short orientation, shortcuts, reports/masters, and compact operational panels.
- Preserve dark-mode readability, company scoping, and the HR-only language in every form builder control.

## Implementation Checks

- Confirm the setup card, checklist active state, selected detail, tour trigger, shortcuts, and reports/masters render at desktop and narrow widths.
- Verify the tour opens from `Show Tour`, advances backward and forward, skips/finishes cleanly, and returns focus.
- Confirm dismissed tour state does not block manually reopening the tour.
- Check that route/query behavior, existing CRUD forms, confirmation dialogs, and modals still work.
- Run PHP lint or the relevant frontend build, plus a focused route check for the module being changed.
