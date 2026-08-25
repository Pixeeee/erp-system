# Development UI/UX Rules

## AI runtime boundary

- Phase Builder and Phase Manager must use the shared BuilderX AI Bridge with the active Codex AI Chat as their only approved AI transport.
- Keep exactly two application engines: one Phase Builder Planning Engine and one Phase Manager Coding Engine. Specialist labels and chunks are bounded stages executed by the applicable engine, not separately dispatched agents.
- Persist run, stage, chunk, event, source-hash, request, result, retry, and exact failure state in the current installation's database before advancing the workflow.
- Resolve the workspace from the current installed project root. A deployed copy must not read, write, link to, or fall back to the Developer source or another installation.
- Do not implement, invoke, document as a required step, or add a fallback to the Codex CLI terminal UI or a command-line Codex workflow.
- Do not add MCP, a BuilderX-maintained direct OpenAI API integration, a hidden provider, or an automatic provider fallback.
- Do not dispatch additional autonomous AI agents or child AI requests. Deterministic application code owns stage order, validation, retry, cancellation, and persistence.
- Sharingan is a surface feature, not a third engine or autonomous agent. It may be changed only to operate directly within the current User Portal, Administrator Portal, and Phases route through the same server-owned BuilderX AI Bridge adapter and active Codex AI Chat lifecycle.
- Sharingan capture and annotation must preserve the visible route, selected element, and installed-project identity. Its browser code must not call the loopback Bridge or another provider directly.
- User Portal exposure must not grant Administrator, Planning Engine, Coding Engine, source-write, Git, or product-mutation authority. Enforce Administrator-only and privileged operations at the backend with the established session, CSRF validation, saved scope, transaction, audit, and direct read-back.

## Layout width and containment

- Use the full available width for the primary workspace; do not add arbitrary max-width constraints.
- Do not place a nested bordered box or card inside another bordered box or card.
- When nested grouping is necessary, use spacing, a different surface/background color, or a separator without adding another border.
- Prefer the existing shadcn/ui surface tokens and layout primitives so hierarchy comes from spacing, typography, and contrast rather than stacked boxes.
- Follow the project-local `ui-ux-main` skill for the shared sidebar, header, full-width workspace, responsive columns, and sticky footer labels.
- All new persisted writes must follow the project-local `database-transaction` skill: ADODB, parameterized SQL, one complete create/update upsert, explicit transaction boundaries, write-result checks, audit logging, read-back verification, and server-backed UI rehydration.

## ERPNext workspace standard

- Treat ERPNext Home and module workspaces as the default design reference for BuilderX ERP development.
- Build ERP modules as quiet operational workspaces, not marketing pages: compact module headers, restrained typography, thin borders, neutral surfaces, and dense-but-readable spacing.
- Prefer an ERPNext-style setup card or workspace hub for module landing screens: setup steps, selected-step detail, a `Show Tour` action when helpful, shortcuts, and grouped reports/masters.
- Keep module navigation compact and functional. Use fit-content pills, chips, shortcuts, or grouped links; avoid oversized hero areas, decorative gradients, heavy shadows, and nested card stacks.
- For every add, edit, configure, or input action inside an ERP module, use a modal popup with native validation, confirmation before submit, and server-backed rehydration after save.
- Every ERP module form must be editable through a company-scoped form builder when the module has configurable records or report filters.
- Use the project-local `ui-ux-erpnext-tour` skill when creating or revising ERP module hubs, setup flows, shortcuts, reports/masters directories, or guided tours.

### ERPNext finance reference set

- For Accounting/Finance work, use these ERPNext workspaces as the fixed reference set:
  - Accounting: `https://erpnext-demo.frappe.cloud/app/accounting`
  - Payables: `https://erpnext-demo.frappe.cloud/app/payables`
  - Receivables: `https://erpnext-demo.frappe.cloud/app/receivables`
  - Financial Reports: `https://erpnext-demo.frappe.cloud/app/financial-reports`
- Treat these four workspaces as one Finance standard: Accounting owns setup and chart structure, Payables owns supplier/pay-out workflows, Receivables owns customer/collection workflows, and Financial Reports owns reporting and statement views.
- When the live demo is behind login, use the workspace names, ERPNext public workspace definitions, screenshots from the user, and the local `ui-ux-erpnext-tour` rules as the fallback reference.
- BuilderX Finance pages should preserve our established two-panel rule: main operational workspace on the left and contextual actions, form builder, tour, shortcuts, or reports/masters controls on the right.

## Tabs and controls

- Tab menus must fit their labels and button content; do not stretch tabs to fill the entire panel unless the layout explicitly requires equal-width controls.
- Add an appropriate existing icon to navigation items and action buttons whenever one is available.
- Place card-level tab menus in the card header, separate from the tab content area; when the content scrolls, keep the tab menu visible with a sticky header and an opaque surface background.
- When a tab card has a submit action, add a tab-scoped sticky footer with the tab context label on the left and a submit button on the right; keep it separate from the global main-layout footer.
- Follow the project-specific sticky-tab guidance in `docs/project/ui-ux-skills.md`.
- Use the project-local `ui-ux-modal` and `ui-ux-tabs` skills for modal and tab implementation/review.
- Use the project-local `ui-ux-form` skill for every form. After native validation succeeds, show a confirmation modal before any native navigation, React submit handler, or persisted action runs.
- After a confirmed form submission, show exactly one result: a dismissing success toast on success, or an accessible informational modal on failure. If a technical reason is available, keep it collapsed behind `View more`. Do not use a transient error toast for failed submissions.
- A persisted form is not complete until both create and update work, the committed row is read back, and the refreshed form displays the saved database values instead of fallback defaults.

## Phase Manager authentication

- Phase Manager and Phase Builder must reuse the Administrator Portal username/password through the shared `bx_login()` session; do not create a second credential store.
- Require an authenticated Administrator role before loading phase, task, or Phase Builder draft data, and enforce the same rule on every write action server-side.
- Provide sign-in and sign-out actions on the Phase Manager workspace while preserving the selected target or phase after authentication.

## Phase Builder data naming

- Phase Builder-generated table names must use the `phase_builder_` prefix followed by the normalized table name.

## Shared company-admin architecture

- Keep one shared company-admin application under `company/admin/`; never copy its business logic, views, CSS, or JavaScript into a company-specific directory.
- A company-specific admin entrypoint may only declare `BUILDERX_COMPANY_ADMIN_SLUG` and require the shared `company/admin/index.php` front controller.
- Keep `company/admin/index.php` thin. It must not contain module functions, SQL, module markup, embedded styles, or browser interaction code.
- Add company-admin behavior to the owning module under `company/admin/modules/`. Use the split HR module as the reference for navigation, schema, form-builder, data, record actions, and workspace views.
- Preserve existing company-admin route parameters, POST action names, redirects, stable record keys, company scoping, authorization, CSRF validation, audit events, ADODB transactions, and read-back verification during extraction or extension work.
- Store company-specific variation in company-scoped database configuration or an explicit allow-listed configuration file, not in copied module source.
- Run `php tests/company-admin-modular-architecture.php` after every company-admin architecture change.
