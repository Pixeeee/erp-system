# Company Admin Auto Provision Design

## Goal

When Administrator creates a company, the system also prepares the company admin portal behavior used by Yovel East: a dedicated URL and a seeded company admin login.

## Scope

- Applies to Administrator → Company Management → Companies.
- Applies to the reusable company admin portal under `/company/<company-slug>/admin/`.
- Does not replace the current Yovel East example.
- Does not expose internal keys or password hashes in Administrator payloads.

## Company Slug

- Add `company_slug` to `project_company`.
- Slugs are lowercase URL-safe values derived from company name, falling back to company code.
- Existing Yovel East keeps slug `yovel-east`.
- Slugs are unique and stable once created.

## Provisioning

When a new company is created:

- Save the company record.
- Create an active `project_company_admin` row for that company if one does not exist.
- Default login is `admin`.
- Temporary build password is `admin12345`.
- Store only password hashes.
- Audit company creation and company admin provisioning in the same transaction.
- Read back both company and admin rows before commit.

When an existing company is updated:

- Preserve the existing slug.
- Ensure the company admin row exists.
- Do not reset an existing company admin password.

## Reusable Portal

- Add a shared company admin portal file.
- Resolve the company by slug.
- Keep `/company/yovel-east/admin/` working by routing it through the shared portal.
- Add rewrite support so future `/company/<slug>/admin/` URLs work without creating folders.

## Administrator UI

- Administrator payload includes `company_slug`.
- Company Management shows the company admin URL as a visible link.
- Project company views build Company Admin links from `company_slug`.
