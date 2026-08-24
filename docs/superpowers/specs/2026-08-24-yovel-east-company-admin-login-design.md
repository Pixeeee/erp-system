# Yovel East Company Admin Login Design

## Goal

Create a dedicated Yovel East Company Admin login URL with a seeded company-scoped admin account for preview use.

## URL

- Public login route: `/erpsystem/company/yovel-east/admin/`
- The route is dedicated to the active `Yovel East` company row in `project_company`.
- The route must not expose the Firebase-style `company_key` in visible UI.

## Account

- Seed one company-scoped admin:
  - Username: `admin`
  - Password: `admin12345`
  - Company: `Yovel East`
- Store the password with the existing `bx_password_hash()` helper.
- Do not create another global `builder_user` named `admin`, because that username already exists as the system administrator.

## Database

- Add an idempotent project-layer table named `project_company_admin`.
- Required fields:
  - `admin_key`
  - `company_key`
  - `company_key_hash`
  - `admin_login`
  - `admin_password_hash`
  - `admin_name`
  - `admin_email`
  - `admin_status`
  - failed-login and login timestamp fields
  - created/updated timestamps
- Use a unique index on `(company_key_hash, admin_login)` so every company can have its own `admin` username.
- Seed the Yovel East admin only when the Yovel East company row exists.

## Login UI

- Match the existing BuilderX administrator login layout:
  - top logo/header row
  - left overview card
  - right login card
  - theme toggle
  - small footer links
- Replace BuilderX copy with Yovel East company admin copy:
  - Header title: `Yovel East`
  - Header subtitle: `Company Admin Portal`
  - Badge: `Company Admin Portal`
  - Main title: `Yovel East`
  - Login title: `Yovel East Admin Login`
- Keep the form simple for now:
  - Username
  - Password
  - Login button

## Auth Behavior

- Validate login against `project_company_admin`, scoped to the Yovel East company.
- On success, store a company-admin session key in PHP session and show a simple company admin dashboard placeholder.
- On failure, show a safe error message and keep SQL/internal details hidden.
- No Platform role or permission verification is required in this step.

## Validation

- Confirm `project_company_admin` exists.
- Confirm the seeded `admin` row exists for Yovel East and has a password hash, not plain text.
- Confirm the login route returns HTTP 200.
- Confirm invalid credentials do not sign in.
- Confirm `admin / admin12345` signs in.
- Run PHP lint and focused route checks.
