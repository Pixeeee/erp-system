# Remove Family Reports From Administrator Design

## Scope

Remove the Family Reports administrator surface from `http://localhost/erpsystem/administrator/`.

## Approved Behavior

- Keep Phase Builder visible and working because it is the build workspace.
- Remove the Family Reports entry from the administrator sidebar.
- Remove `family-reports` as a valid administrator tab.
- Remove the Family Reports React view and its render branch.
- Remove administrator backend report payload, filters, report queries, and CSV export wiring.
- If someone opens `?tab=family-reports` directly, the administrator page falls back to the dashboard.
- Do not delete family-member data tables.
- Do not remove user-portal family member features.

## Architecture

The administrator UI is rendered from `frontend/src/App.tsx`, while `administrator/index.php` prepares the page payload and export responses. The cleanup should remove the report surface at those two boundaries without changing shared authentication, Phase Builder, or user-portal family management.

## Validation

- `npm run build` from `frontend/`
- `php -l administrator/index.php`
- Search validation that no administrator Family Reports route or view remains.

## Self-Review

- No placeholders remain.
- Scope is limited to the administrator Family Reports surface.
- Phase Builder is explicitly preserved.
- User-portal family member features are explicitly out of scope.
