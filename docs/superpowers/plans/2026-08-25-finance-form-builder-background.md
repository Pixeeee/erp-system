# Finance Form Builder Background Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the Finance Form Builder a fully opaque, theme-matched dialog surface so the Accounting/Finance page cannot show through it.

**Architecture:** Keep the existing modal markup and layout unchanged. Correct only the Finance Form Builder surface declarations in the shared admin stylesheet, and protect the behavior with focused static assertions in the existing Finance form-builder test.

**Tech Stack:** PHP 8, CSS custom properties, BuilderX admin theme, browser visual verification

## Global Constraints

- Preserve the existing modal dimensions, workbench layout, sticky regions, and responsive behavior.
- Use existing theme tokens without adding dependencies or decorative effects.
- Keep the overlay dark while making every dialog region opaque in light and dark themes.

---

### Task 1: Opaque Finance Form Builder Surface

**Files:**
- Modify: `tests/accounting-finance-form-builder.php`
- Modify: `company/admin/assets/css/admin.css`

**Interfaces:**
- Consumes: Existing `.yovel-finance-builder-*` modal classes and CSS theme variables.
- Produces: Opaque dialog, header, body, tabs, workbench panes, and footer surfaces.

- [ ] **Step 1: Add failing CSS regression assertions**

Read `company/admin/assets/css/admin.css` in the Finance form-builder test and assert that the dialog uses `background: var(--card)`, the body and tabs use `background: var(--background)`, and the header/footer use `background: var(--popover)`.

```php
$adminCss = file_get_contents($root . '/company/admin/assets/css/admin.css');
finance_builder_assert(is_string($adminCss), 'Unable to read the admin stylesheet.');
finance_builder_assert(str_contains($adminCss, ".yovel-finance-builder-dialog {\n            background: var(--card);"), 'Finance builder dialog must have an opaque card background.');
finance_builder_assert(str_contains($adminCss, ".yovel-finance-builder-body {\n            background: var(--background);"), 'Finance builder body must have an opaque background.');
finance_builder_assert(str_contains($adminCss, ".yovel-finance-builder-tabs {\n            background: var(--background);"), 'Finance builder tabs must have an opaque background.');
```

- [ ] **Step 2: Run the focused test and verify failure**

Run: `php tests/accounting-finance-form-builder.php`

Expected: FAIL with `Finance builder dialog must have an opaque card background.`

- [ ] **Step 3: Correct the Finance modal surface declarations**

In `company/admin/assets/css/admin.css`, replace the incompatible `hsl(var(--...))` wrappers in Finance Form Builder rules with direct theme variables. Give each workbench pane an explicit background and retain isolation.

```css
.yovel-finance-builder-dialog {
    background: var(--card);
    background-clip: padding-box;
    isolation: isolate;
}
.yovel-finance-builder-dialog > header,
.yovel-finance-builder-dialog > footer {
    background: var(--popover);
}
.yovel-finance-builder-body,
.yovel-finance-builder-tabs {
    background: var(--background);
}
.yovel-finance-builder-pane {
    background: var(--background);
}
```

- [ ] **Step 4: Run focused and structural verification**

Run: `php tests/accounting-finance-form-builder.php`

Expected: `Accounting/Finance form builder checks passed.`

Run: `git diff --check`

Expected: no output and exit status 0.

- [ ] **Step 5: Verify the live modal in both themes**

Open `http://localhost/erpsystem/company/yovel-east/admin/?view=accounting-finance&section=dashboard&finance_builder=1&builder_mode=new&builder_target=chart-of-accounts`. Confirm that the dialog is opaque in dark mode, switch to light mode and confirm the same, then leave the page in its original theme.

- [ ] **Step 6: Commit only the focused implementation files if the worktree permits**

```bash
git add tests/accounting-finance-form-builder.php company/admin/assets/css/admin.css
git commit -m "Fix Finance form builder background"
```
