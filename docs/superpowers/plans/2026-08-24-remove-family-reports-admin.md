# Remove Family Reports From Administrator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the Family Reports administrator surface while preserving Phase Builder and user-portal family member functionality.

**Architecture:** `frontend/src/App.tsx` owns the administrator sidebar, view routing, titles, and report component. `administrator/index.php` owns the Family Reports filters, query payload, and CSV export response. Removing both boundaries prevents visible navigation and direct `?tab=family-reports` access from rendering report data.

**Tech Stack:** PHP 8, React 19, TypeScript, Vite, shadcn/ui components, Lucide icons.

## Global Constraints

- Only `http://localhost/erpsystem/administrator/` is in scope.
- Keep Phase Builder visible and working.
- Remove Family Reports from the administrator surface entirely.
- Do not delete family-member data tables.
- Do not remove user-portal family member features.
- Existing unrelated working tree changes must be preserved.

---

### Task 1: Remove Administrator Family Reports Frontend Surface

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: Existing administrator `data` payload and `activeView` state.
- Produces: An administrator UI where `family-reports` is no longer a valid view key, sidebar item, title branch, build target branch, or render branch.

- [ ] **Step 1: Remove `family-reports` from valid administrator views**

Delete the `family-reports` item from `adminViewKeys`:

```ts
const adminViewKeys = ['dashboard', 'users', 'groups', 'roles', 'permissions', 'branches', 'projects', 'settings', 'audit', 'health', 'template']
```

- [ ] **Step 2: Remove the sidebar entry**

Delete this item from `sidebarSections`:

```ts
{ key: 'family-reports', label: 'Family Reports', icon: FileBarChart },
```

- [ ] **Step 3: Delete the `FamilyReportsView` component**

Remove the complete `function FamilyReportsView() { ... }` block.

- [ ] **Step 4: Remove title, build target, and render branches**

Remove the `activeView === 'family-reports'` title branch, remove the `P1-T4` build-target branch, and remove the `<FamilyReportsView />` render branch. Dashboard should become the fallback for unsupported values.

- [ ] **Step 5: Run frontend validation**

Run:

```bash
cd frontend && npm run build
```

Expected: PASS.

### Task 2: Remove Administrator Family Reports Backend Payload And Export

**Files:**
- Modify: `administrator/index.php`

**Interfaces:**
- Consumes: Existing administrator authentication and payload assembly.
- Produces: No Family Reports filters, data query, CSV export, or `familyReport` payload field from the administrator page.

- [ ] **Step 1: Delete report helper functions**

Remove these functions:

```php
function bx_family_report_filters_from_request(): array
function bx_family_report_csv(array $rows): void
function bx_family_report_data(?array $user, array $filters, bool $allowed): array
```

- [ ] **Step 2: Delete report preparation and export wiring**

Remove these statements from payload setup:

```php
$familyReportFilters = bx_family_report_filters_from_request();
$familyReportAllowed = $isAdmin && (bx_user_has_permission($user, 'family_members.report') || bx_user_has_permission($user, 'reports.manage'));
$familyReport = bx_family_report_data($user, $familyReportFilters, $familyReportAllowed);

if ($familyReportAllowed && (string) ($_GET['family_report_export'] ?? '') === 'csv') {
    bx_family_report_csv($familyReport['rows']);
}
```

- [ ] **Step 3: Remove payload field**

Remove:

```php
'familyReport' => $familyReport,
```

- [ ] **Step 4: Run PHP validation**

Run:

```bash
php -l administrator/index.php
```

Expected: PASS.

### Task 3: Verify Direct URL Fallback And Residual References

**Files:**
- Test only.

**Interfaces:**
- Consumes: The modified frontend and backend.
- Produces: Evidence that the administrator Family Reports surface is gone and Phase Builder references remain.

- [ ] **Step 1: Search for remaining administrator report routing**

Run:

```bash
rg -n "family-reports|FamilyReportsView|familyReport|family_report_export|bx_family_report" frontend/src/App.tsx administrator/index.php
```

Expected: no matches in `frontend/src/App.tsx` or `administrator/index.php`.

- [ ] **Step 2: Confirm Phase Builder is still present**

Run:

```bash
rg -n "Phase Builder" frontend/src/App.tsx
```

Expected: matches remain.

- [ ] **Step 3: Confirm user portal family member features are still present**

Run:

```bash
rg -n "FamilyMemberEditor|Family Member User Portal|builder_family_member" frontend/src/App.tsx administrator/index.php app/foundation.php
```

Expected: user-portal and schema references remain.

- [ ] **Step 4: Browser validation**

Open:

```text
http://localhost/erpsystem/administrator/?tab=family-reports
```

Expected: administrator page loads without a Family Reports sidebar item or Family Reports content. Unsupported tab state falls back to Dashboard UI.

## Self-Review

- Spec coverage: all approved behavior is covered by Tasks 1-3.
- Placeholder scan: no `TBD`, `TODO`, or vague implementation steps remain.
- Type consistency: `family-reports`, `FamilyReportsView`, `familyReport`, and `bx_family_report_*` are named consistently across tasks.
