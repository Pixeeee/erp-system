# Company Management Administrator Design

## Scope

Add Company Management under the Administrator Platform menu at `http://localhost/erpsystem/administrator/`.

## Approved Behavior

- Add a Platform menu group named `Company Management`.
- Add a child menu item named `Companies`.
- Add a new admin tab key named `companies`.
- Keep Phase Builder visible and unchanged.
- Build a CRUD screen for companies.
- Use a 12-column workspace layout: `col-8` for the company list and `col-4` for the create/edit form.
- Store company records locally in MySQL.
- Treat `company_key` as the Firebase Firestore document ID.
- Validate `company_key` before any write using Firestore document ID constraints: valid UTF-8, no more than 1,500 bytes, no `/`, not `.` or `..`, and not matching `__.*__`.
- Do not add Firebase credentials or direct Firebase writes in this change.

## Company Fields

- `company_key`: required, manually entered, unique, Firestore-compatible document ID.
- `company_key_hash`: internal SHA-256 hash used to enforce full-key uniqueness in MySQL.
- `company_code`: required, unique, 2-40 uppercase letters, numbers, underscores, or hyphens.
- `company_name`: required, max 160 characters.
- `company_status`: `DRAFT`, `ACTIVE`, `INACTIVE`, `ARCHIVED`, or `DELETED`.
- `company_email`: optional, max 190 characters, valid email when present.
- `company_phone`: optional, max 40 characters.
- `company_address`: optional text.
- `company_description`: optional text.

## Architecture

`app/foundation.php` creates the `builder_company` table during foundation schema setup. `administrator/index.php` validates and persists company create/update/status actions with parameterized SQL, audit logging, SHA-256 full-key uniqueness, and direct read-back before success feedback. `frontend/src/App.tsx` adds the menu item, admin tab, payload type, Company CRUD view, and a `col-8` list plus `col-4` form layout that follows existing Administrator CRUD patterns.

## Validation

- `php -l app/foundation.php`
- `php -l administrator/index.php`
- `npm run build` from `frontend/`
- Static search confirms `companies` is a valid admin tab and `Phase Builder` remains present.
- Browser validation confirms the Companies tab renders with Company Management under Platform.

## References

- Firebase Firestore document ID constraints: https://firebase.google.com/docs/firestore/quotas#limits

## Self-Review

- No placeholders remain.
- Scope is limited to the administrator page.
- The approved `col-8` and `col-4` layout is explicit.
- Firestore is represented as local validation for `company_key`, not as direct Firebase integration.
