# HR Form Builder Row and Column Layout Design

## Goal

Extend the HR Dashboard Form Builder with approachable row and column controls so non-technical HR administrators can place related fields beside one another, such as First Name and Last Name, without learning a technical grid system.

The saved layout must render consistently in Build mode, Preview mode, saved form versions, and employee-linked submissions. Existing forms must remain usable without manual migration.

## Chosen Interaction Model

Use guided rows with one, two, or three equal-width columns.

- One column is the default and suits long text, addresses, notes, and uploads.
- Two columns suit paired data such as First Name and Last Name or Email and Phone.
- Three columns suit compact values such as codes, dates, status, and employment details.
- Below the tablet breakpoint, every multi-column row stacks into one column in reading order.
- Section headings always span the full form width and occupy their own row.

This is intentionally simpler than a free 12-column grid. It provides the useful HR layouts while avoiding technical width calculations, fragile resizing, and confusing drag targets.

## Builder Workspace

The existing HR Dashboard Form Builder remains the only entry point. Its three-panel workspace keeps the current Question Toolbox, central form canvas, and Form Settings panel.

The central canvas gains explicit layout structure:

1. `Add Row` creates an empty one-column row.
2. Each row has a compact `1 / 2 / 3` segmented column selector.
3. Each column is a visible drop zone with an empty-state instruction.
4. Field types can be dragged from the Question Toolbox into any visible column to create a new question at that location.
5. Questions can be dragged from one row or column into another.
6. Questions within a column can be reordered by dragging or with accessible move buttons.
7. Rows can be reordered with a row drag handle or row move buttons.
8. Empty rows can be deleted with a row-level delete action.
9. Changing a row to fewer columns redistributes displaced questions from left to right while preserving their relative order.

Clicking a question in the toolbox places it into the currently selected column. If no column is selected, it goes into the final column of the final row. If the form has no rows, the builder creates a one-column row automatically.

Dragging a toolbox field follows these rules:

- Pointer movement must cross a small threshold before the interaction becomes a drag, preserving ordinary click behavior.
- The dragged field displays a compact floating label so the admin knows which field type is being placed.
- Valid columns display a drop-ready state; the column currently under the pointer receives the active destination state.
- The new question is inserted at the pointer's vertical position among existing questions in that column.
- Dropping a section field creates a dedicated full-width row immediately after the targeted row.
- Dropping outside a valid column cancels without changing the form.
- A completed drag suppresses the subsequent click event so only one question is created.
- Mouse, touch, and stylus use the same pointer-based path. Click-to-add and move buttons remain available as keyboard and accessibility fallbacks.

The active row, active column, dragged toolbox field, dragged question, and valid drop destination receive clear but restrained shadcn-style indicators. Drop zones remain visible during dragging so admins can predict exactly where a field will land.

## Schema Version 2

Questions remain the canonical records so existing submission keys and answer persistence do not change. Layout is stored separately as ordered rows referencing question keys.

```json
{
  "version": 2,
  "questions": [
    {
      "key": "first_name",
      "label": "First Name",
      "type": "SHORT_TEXT",
      "required": true,
      "options": [],
      "order": 10
    },
    {
      "key": "last_name",
      "label": "Last Name",
      "type": "SHORT_TEXT",
      "required": true,
      "options": [],
      "order": 20
    }
  ],
  "rows": [
    {
      "key": "row-contact-name",
      "columns": [
        {"key": "column-first", "question_keys": ["first_name"]},
        {"key": "column-last", "question_keys": ["last_name"]}
      ]
    }
  ]
}
```

Row and column keys use the same restricted identifier rules as question keys. A form supports at most 60 questions, 60 rows, and three columns per row.

Server normalization enforces these rules:

- Every referenced question key must exist and may appear only once in the layout.
- Unreferenced questions are appended as one-column rows instead of being discarded.
- Unknown row or column properties are ignored.
- Empty rows are valid while editing and saving.
- A section question is moved into its own one-column row during normalization.
- Question order is recalculated from visual reading order: top to bottom, then left to right, then within each column.

## Backward Compatibility

Version 1 schemas contain only a flat `questions` array. When read, each existing question is placed in its own one-column row in its current order. No database migration or destructive rewrite is required.

The first subsequent save writes the normalized version 2 schema. Existing form versions remain immutable and continue to be valid because submission validation still uses their question list.

## Rendering Rules

Build mode, Preview mode, and the saved submission form share the same row interpretation:

- Desktop and tablet render the selected one, two, or three equal columns.
- Mobile renders all columns as a single vertical sequence.
- Multiple questions in one column stack vertically with consistent spacing.
- Section rows span the complete canvas width.
- Empty layout rows are shown only in Build mode and are omitted from Preview and submissions.
- Validation, labels, help text, options, required state, and field types remain unchanged.

The rendered field DOM order follows the visual reading order so keyboard navigation and screen-reader flow remain predictable.

## Persistence and Safety

The existing `save_hr_builder_form` transaction remains the write boundary. The server normalizes the version 2 JSON before saving the company-scoped form and immutable version record. Existing audit logging, confirmation, direct read-back, and question-count validation stay in place.

Malformed layouts fail with a clear validation message. The server never trusts client-provided row, column, or question identifiers without normalization.

## Verification

Automated coverage will verify:

- Version 1 forms normalize into ordered one-column rows.
- One-, two-, and three-column layouts survive save and read-back.
- Duplicate, missing, and unknown question references are handled safely.
- Section questions normalize into full-width rows.
- Question order follows row and column reading order.
- Submission persistence remains keyed by question and unaffected by layout metadata.

Browser verification will cover:

- Creating rows and switching between one, two, and three columns.
- Dragging Short Answer from the toolbox into a selected column creates exactly one new question at the indicated position.
- Dragging a Section toolbox item creates a dedicated full-width row.
- Dropping a toolbox item outside a valid column leaves the form unchanged.
- Dragging First Name and Last Name into the same two-column row.
- Reordering rows and questions with drag/drop and buttons.
- Clear drop indicators and empty-column targets.
- Matching Build, Preview, and submission layouts.
- Responsive stacking at desktop, tablet, and mobile widths and at common browser zoom levels.

## Scope Boundaries

This change adds spatial form layout only. It does not add conditional branching, page-by-page surveys, formulas, approval workflows, arbitrary column widths, or nested grids. Those can be evaluated separately after the row and column foundation is stable.
