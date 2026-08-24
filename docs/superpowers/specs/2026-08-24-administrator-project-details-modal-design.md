# Administrator Project Details Modal Design

## Goal

Allow an administrator to click a project inside a selected company branch and inspect the project details in a popup modal.

## Scope

- Applies only to Administrator → Projects → company → branch.
- Uses existing `companyProjects`, `companyBranches`, and `companyAdmins` payload data.
- Does not create, update, or delete database records.
- Does not show `company_key`, `branch_key`, `project_key`, hash fields, or password fields.

## Interaction

- The left panel shows projects when a branch is selected.
- Clicking a project name opens a read-only details modal.
- The modal can be closed with the header `X`.
- No footer Cancel button is shown.

## Modal Content

The modal shows:

- Project name
- Project code
- Company name
- Branch name
- Project status
- Project description
- Created date
- Updated date
- Company admin summary
- Future users and roles notes

## UI Rules

- Use the existing shadcn `Dialog` primitives.
- Dialog structure is header, independently scrollable body, and footer.
- The footer is informational only because the close action is the `X`.
- Do not introduce nested bordered cards.
- Keep modal width and height responsive.
- Keep body padding equal on all sides.
