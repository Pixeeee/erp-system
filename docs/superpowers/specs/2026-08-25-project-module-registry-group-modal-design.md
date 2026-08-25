# Project Module Registry Group Modal Design

## Goal

Make the project module registry easier to scan by showing ERP module groups as clickable department buttons, then opening a modal with the modules specific to the selected group.

## Scope

- Change only the Administrator project-company workspace registry presentation.
- Keep the existing project module group and project module payloads.
- Do not add database tables, routes, or persistence changes.
- Preserve the current project header, module counts, forms count, and project status.

## UI Behavior

- The registry panel shows a responsive grid of module group buttons.
- Each button displays the group name, short description, module count, group code, and status.
- Clicking a group opens a dialog titled with the group name.
- The dialog body lists only modules under that group, with module code, logical table name, form count, status, and description when available.
- The dialog uses BuilderX modal rules: header, independently scrollable body, footer, equal padding, and no nested bordered cards.

## Verification

- Build the frontend.
- Check the project-company route returns HTTP 200.
- Inspect the page with browser automation and confirm a group button opens a modal containing module-specific rows.
