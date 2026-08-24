# Administrator Projects Company Branch Navigation Design

## Goal

Update Administrator → Projects so company-level clicks show company administrator context, while branch-level clicks show the projects inside that branch.

## Scope

- Applies only to the Administrator page.
- Uses the existing project-company route with optional `branch_key`.
- Does not create, update, or delete database records.
- Does not show `company_key` or password/hash fields.

## Sidebar

- The Projects sidebar shows active companies.
- Each company expands to show only its branches.
- Branches are not dropdowns.
- Projects are not listed in the sidebar.

## Company Click Behavior

When an administrator clicks a company such as `Yovel East`:

- The left main panel shows all branches under the company.
- The right side panel shows company overview counts and the company admin.
- The company admin block includes login, name, email, status, and a Company Dashboard link.

## Branch Click Behavior

When an administrator clicks a branch such as `Sariaya Branch`:

- The left main panel changes to the projects inside that branch.
- The right side panel shows branch status, project count, company admin, future company users, future company roles, and admin flow.

## UI Rules

- Keep the existing Company Management-style two-panel layout.
- Left panel is the main panel.
- Right panel is the side panel.
- Panel headers keep bottom borders through the shared `DashboardPanel`.
- Scrolling stays inside panel bodies.
- Do not introduce nested bordered cards.
