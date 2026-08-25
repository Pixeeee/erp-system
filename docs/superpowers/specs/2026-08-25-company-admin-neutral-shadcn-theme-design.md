# Company Admin Neutral Shadcn Theme

## Goal

Return the shared company-admin interface to a restrained shadcn visual language in both light and dark themes. Dark mode should read as black and zinc rather than teal, while light mode should remain white and neutral gray.

## Visual Rules

- Use the existing semantic shadcn tokens for backgrounds, cards, borders, text, muted surfaces, controls, and focus rings.
- Remove decorative gradients, cyan/teal washes, colored icon glows, text shadows, and tinted panel borders.
- Keep panels flat, with one-pixel neutral borders and clear header/body separators.
- Use monochrome icons and neutral hover or selected states.
- Preserve color only when it communicates state: destructive actions, errors, warnings, and success feedback.
- Keep drag-and-drop and guided-tour indicators visible using high-contrast neutral outlines instead of colored glow.

## Scope

The shared theme applies to Dashboard, HR, Platform, Sales/CRM, Accounting/Finance, dialogs, guided tours, employee records, and the HR form builder. Layout, data, forms, modal behavior, and permissions are unchanged.

## Responsive Behavior

Existing 8/4 panel proportions, independent panel scrolling, sticky regions, mobile stacking, and accessible touch targets remain intact.

