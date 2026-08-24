# Administrator Projects Company Table Label Design

## Goal

When an administrator clicks a company under Projects, show a company-level table instead of a branch-labeled table.

## Scope

- Applies only to Administrator → Projects → company view.
- Does not change branch click behavior.
- Does not change project details modal behavior.
- Does not create, update, or delete database records.
- Does not show internal keys.

## Company-Level Table

The left panel title is `Company`.

The table columns are:

- Company
- URL Link
- Branch
- Status

Each row represents a branch connection under the selected company:

- Company shows the selected company name.
- URL Link shows the existing Company Admin link.
- Branch shows the clickable branch name.
- Status shows the branch status.

## Branch-Level Table

When a branch is clicked, the left panel continues to show the selected branch projects and project details modal behavior remains unchanged.
