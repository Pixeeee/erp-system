# Administrator Projects Branch Detail Design

## Goal

Update Administrator → Projects → active company views so an administrator can click `Yovel East`, see only the branches under that company, and then click a branch such as `Sariaya Branch` to review its project and admin context.

## Scope

- Applies to the Administrator page only.
- Applies to the existing Projects sidebar company route: `/erpsystem/administrator/?tab=project-company&company_key=<company_key>`.
- Adds optional branch selection to the URL with `branch_key=<branch_key>`.
- Does not create, update, or delete database records.

## Left Main Panel

When `Yovel East` is selected under Projects, the left main panel shows branches under that company with these columns:

- Branch
- URL Link
- Projects
- Status

The URL link points to the company admin login/dashboard route:

- `/erpsystem/company/yovel-east/admin/`

The Projects value uses singular/plural copy such as `1 Project` and `2 Projects`.

## Right Side Panel

The right side panel shows branch context. If no branch is selected, it asks the administrator to select a branch.

When `Sariaya Branch` is selected, the right side panel shows:

- Branch name
- Branch status
- Projects under that branch
- Company admin rows from `project_company_admin`
- Company users placeholder
- Company roles placeholder
- Note that the Administrator creates the company admin, then the company admin later creates company users, roles, and access inside the Company Dashboard.

## Sidebar Behavior

- Clicking a company such as `Yovel East` opens the company branch list.
- Clicking a branch under that company updates the same project-company view with the selected `branch_key`.
- Clicking a project under the branch can also select that branch for now.

## Data

- Existing payload: `companies`, `companyBranches`, and `companyProjects`.
- New read-only payload: `companyAdmins` from `project_company_admin`.
- Do not show `company_key`.
- Do not show password hashes.

## Validation

- Run `npm run build`.
- Run PHP lint for `administrator/index.php` and `app/foundation.php`.
- Route-check:
  - `?tab=project-company&company_key=JMyOyz1UVRwdpLC9j9db`
  - `?tab=project-company&company_key=JMyOyz1UVRwdpLC9j9db&branch_key=8a4a9105-0dd6-4391-accb-02d2f7893136`
- Confirm both routes return HTTP 200, load the latest bundle, and have no fatal/parse/warning/SQL markers.
