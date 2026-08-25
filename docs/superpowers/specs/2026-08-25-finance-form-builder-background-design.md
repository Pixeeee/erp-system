# Finance Form Builder Background Design

## Goal

Make the Finance Form Builder fully readable by preventing the Accounting/Finance page behind the modal from showing through its dialog surface.

## Approved Treatment

- Keep a dark, softened overlay behind the dialog to preserve modal focus.
- Give the dialog an opaque theme-matched background in both light and dark modes.
- Give the header, scrolling body, sticky feature tabs, workbench panes, and footer explicit opaque backgrounds.
- Preserve the existing dimensions, 3-column workbench, sticky regions, and responsive behavior.
- Do not add imagery, gradients, glass effects, or decorative surfaces.

## Implementation

Update only the Finance Form Builder CSS in `company/admin/assets/css/admin.css`. Use the project's color variables directly so the declarations remain valid with the current theme token format. Add isolation and background clipping where necessary to prevent backdrop content from compositing through the dialog.

## Verification

- Open the Finance Form Builder from the Finance Dashboard.
- Confirm that no dashboard text or controls are visible through the dialog.
- Confirm that header, body, tabs, workbench, and footer remain opaque while scrolling.
- Confirm visual contrast in both dark and light themes.
- Run Finance form-builder regression tests and CSS diff checks.
