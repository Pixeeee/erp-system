# Yovel East ERP Sidebar Dropdowns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add icons to every Yovel East ERP sidebar group and make each group expand into clickable feature/function links.

**Architecture:** Keep the change inside `company/yovel-east/admin/index.php`. Extend existing ERP group metadata with icons, then render native `<details>/<summary>` dropdowns in the sidebar that link to dashboard feature anchors.

**Tech Stack:** PHP, existing shadcn/Tailwind CSS output, native HTML disclosure elements, curl and Playwright verification.

## Global Constraints

- Keep the existing Yovel East login, dashboard, Platform, and logout behavior unchanged.
- Add icons to every ERP System group in the sidebar.
- Make each ERP System group a clickable dropdown for its related functions/features.
- Feature links point to existing dashboard anchors for now because ERP CRUD screens are not implemented in this step.
- No new persisted writes are required.

---

### Task 1: Add ERP Icon Metadata

**Files:**
- Modify: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: `yovel_admin_erp_groups(): array`
- Produces: ERP group rows with `icon`, `label`, `description`, and `features`

- [ ] Add an `icon` string to every group in `yovel_admin_erp_groups()`.
- [ ] Run `php -l company/yovel-east/admin/index.php`.

### Task 2: Render Sidebar Dropdowns

**Files:**
- Modify: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: ERP group `icon`, `label`, and `features`
- Consumes: `yovel_admin_slug(string $value): string`

- [ ] Replace the ERP sidebar group links with native `<details>` elements.
- [ ] Render each group summary with icon, label, feature count, and chevron.
- [ ] Render each related feature as a clickable link to `./?view=dashboard#erp-<group-slug>-feature-<feature-slug>`.
- [ ] Add matching feature anchors to the dashboard feature chips.

### Task 3: Verify

**Files:**
- Test: `company/yovel-east/admin/index.php`

**Interfaces:**
- Consumes: completed sidebar dropdowns

- [ ] Run PHP lint.
- [ ] Login with `admin / admin12345` and confirm the authenticated dashboard includes `<details>`, `Mobile receiving`, and feature anchor links.
- [ ] Use Playwright at desktop width to confirm at least one ERP dropdown is visible, expandable, and contains feature links.
