# Company Project Module Hierarchy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Company -> Branch -> Project navigation and a normalized project module-group, project-module, and form registry with stable logical table numbering from `project_module_100`.

**Architecture:** Extend the existing additive foundation schema and keep `project_company`, `project_company_branch`, `project_company_project`, and `project_company_module` intact. Seed project-owned registries transactionally from one canonical ERP catalog, expose them in the administrator payload, and extend the existing project-company route to carry an optional project key.

**Tech Stack:** PHP 8, ADODB, MariaDB/MySQL, React, TypeScript, Vite, shadcn/ui Sidebar and Collapsible primitives.

## Global Constraints

- Preserve all existing company, branch, project, company-module, permission, specialized business, and form-schema records.
- Use only `bx_db()` and parameterized ADODB operations for data writes.
- Use fixed table names; never interpolate request data into SQL identifiers.
- Wrap hierarchy seed writes and audit entries in one transaction and verify direct read-back before commit.
- Start `project_module.module_index` at `100` and keep `module_table_name` equal to `project_module_<module_index>`.
- Store logical table names only; do not create numbered physical tables.
- Keep the existing company and branch overview routes usable.
- Preserve unrelated uncommitted work in every modified file.

---

### Task 1: Add Schema, Catalog, and Transactional Seed

**Files:**
- Modify: `app/foundation.php`
- Modify: `company/admin/index.php`
- Create: `tests/project-module-hierarchy.php`

**Interfaces:**
- Produces: `bx_project_erp_groups(): array`
- Produces: `bx_seed_project_module_hierarchy(): void`
- Produces tables: `project_company_module`, `project_module_group`, `project_module`, `project_module_form`
- Consumes: `bx_uuid()`, `bx_db()`, `bx_audit()`, `project_company_project`

- [ ] **Step 1: Write a failing schema and idempotency test**

Create `tests/project-module-hierarchy.php` to require `app/foundation.php`, assert the exact required columns and named indexes, verify ownership joins, assert every logical name with:

```php
if ((string) $row['module_table_name'] !== 'project_module_' . (int) $row['module_index']) {
    throw new RuntimeException('Project module logical table name mismatch.');
}
```

Capture group/module keys and counts, call `bx_seed_project_module_hierarchy()`, and assert the same keys and counts remain.

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php tests/project-module-hierarchy.php`

Expected: failure because `project_module_group` does not exist or `bx_seed_project_module_hierarchy()` is undefined.

- [ ] **Step 3: Define one canonical ERP catalog**

Add `bx_project_erp_groups()` to `app/foundation.php` with the current 12 group labels, icons, descriptions, and feature arrays. Replace the body of `yovel_admin_erp_groups()` with:

```php
function yovel_admin_erp_groups(): array
{
    return bx_project_erp_groups();
}
```

- [ ] **Step 4: Create idempotent registry schemas**

Inside `bx_schema()`, create the four fixed tables and their approved unique/index contracts. Define `project_module` with:

```sql
module_index BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
module_key CHAR(36) NOT NULL UNIQUE,
module_table_name VARCHAR(80) NOT NULL UNIQUE
```

End its DDL with `AUTO_INCREMENT=100` so the first generated index is at least 100.

- [ ] **Step 5: Implement transactional seed and read-back**

For each active project, validate its company/branch ownership, upsert groups by `(project_key, module_group_code)`, and modules by `(project_key, module_group_key, module_code)`. Insert a new module with a trusted pending logical name, obtain `Insert_ID()`, update it to `project_module_<index>`, audit it, and compare every saved ownership/name/index field before commit.

Map active `project_company_form_schema` and `project_company_hr_builder_form` records into `project_module_form` only when their normalized feature module exists. Preserve their source keys and schema JSON; do not move or delete source records.

- [ ] **Step 6: Run the focused test**

Run: `php tests/project-module-hierarchy.php`

Expected: one JSON line with schema, numbering, ownership, forms, direct read-back, and idempotency flags set to `true`.

### Task 2: Expose Project Registries and Extend Route Identity

**Files:**
- Modify: `administrator/index.php`
- Modify: `frontend/src/App.tsx`
- Modify: `tests/project-module-hierarchy.php`

**Interfaces:**
- Produces payload fields: `projectModuleGroups`, `projectModules`, `projectModuleForms`
- Updates: `projectCompanyViewKey(companyKey, branchKey, projectKey): string`
- Produces: `projectCompanyProjectKeyFromView(view): string`

- [ ] **Step 1: Extend the static test with payload and route markers**

Assert `administrator/index.php` selects all three registry tables and `frontend/src/App.tsx` contains `project_key`, `projectCompanyProjectKeyFromView`, and `projectModuleGroups`.

- [ ] **Step 2: Add scoped payload queries**

Add administrator payload arrays using fixed SQL joins and stable ordering. Include company name, branch name, project name, group name, module name, logical table name, and per-module form counts without exposing schema JSON in list data.

- [ ] **Step 3: Extend TypeScript payload and route helpers**

Add the three arrays to `AdminPayload`. Extend the view identity to three segments:

```ts
projectCompanyViewKey(companyKey, branchKey, projectKey)
// project-company:<company>::<branch>::<project>
```

Read and write `project_key` in `AdminApp`, browser history, and `popstate`; clear it when a company or branch is selected.

- [ ] **Step 4: Run focused validation**

Run: `php tests/project-module-hierarchy.php && php -l administrator/index.php`

Expected: test JSON and `No syntax errors detected`.

### Task 3: Render Company, Branch, and Project Sidebar Dropdowns

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `data.companyBranches`, `data.companyProjects`
- Consumes: extended route helper and company/branch/project parsers
- Produces: nested accessible company and branch disclosures with selectable project leaves

- [ ] **Step 1: Rename the group label**

Change `<SidebarGroupLabel>Projects</SidebarGroupLabel>` to `<SidebarGroupLabel>Company</SidebarGroupLabel>`.

- [ ] **Step 2: Group projects by branch**

Build `projectsByBranch` from non-deleted company projects and sort company, branch, and project lists by their display names.

- [ ] **Step 3: Render nested branch disclosures**

Keep company `Collapsible` rows. Render each branch as its own `Collapsible` with `Frame`, a chevron, and a nested list of project buttons using `FolderKanban`. Selecting a project calls:

```ts
onViewChange(projectCompanyViewKey(company.company_key, branch.branch_key, project.project_key))
```

Keep the active company and active branch open and mark the selected project active. Render disabled `No branches` and `No projects` states.

- [ ] **Step 4: Compile the frontend**

Run: `npm run build --prefix frontend`

Expected: TypeScript and Vite build exit code 0.

### Task 4: Add the Selected Project Module Workspace

**Files:**
- Modify: `frontend/src/App.tsx`
- Modify: `tests/project-module-hierarchy.php`

**Interfaces:**
- Produces: `ProjectModuleWorkspace`
- Consumes: selected company, branch, project, `projectModuleGroups`, `projectModules`, `projectModuleForms`

- [ ] **Step 1: Add selected-project state to the overview**

Pass `selectedProjectKey` and `onProjectSelect` into `ProjectCompanyOverviewView`. Validate that the selected project belongs to the selected company and branch; otherwise render the existing unavailable state without changing data.

- [ ] **Step 2: Render project identity and module counts**

When a valid project is selected, show company, branch, project code/name/status, active group count, active module count, and form count in the existing full-width workspace style.

- [ ] **Step 3: Render module groups and modules**

Render each module group as an unframed section with its modules in a compact table containing module name, logical table name, status, and form count. Do not nest cards. Show an empty state for groups or modules with no rows.

- [ ] **Step 4: Verify static UI requirements and build**

Extend the focused test to assert the `Company` label, nested project route call, `ProjectModuleWorkspace`, and logical table column. Run:

```bash
php tests/project-module-hierarchy.php
npm run build --prefix frontend
```

Expected: test JSON and build exit code 0.

### Task 5: Full Verification and Browser Read-Back

**Files:**
- Verify: `app/foundation.php`
- Verify: `administrator/index.php`
- Verify: `company/admin/index.php`
- Verify: `frontend/src/App.tsx`
- Verify: `tests/project-module-hierarchy.php`

**Interfaces:**
- Consumes the completed schema, payload, route, sidebar, and workspace.

- [ ] **Step 1: Run PHP checks**

Run:

```bash
php -l app/foundation.php
php -l administrator/index.php
php -l company/admin/index.php
php tests/project-module-hierarchy.php
```

- [ ] **Step 2: Run frontend checks**

Run: `npm run build --prefix frontend`

- [ ] **Step 3: Inspect committed data directly**

Use `bx_db()` to print group/module/form counts, the minimum and maximum module index, mismatched logical-name count, broken ownership count, and duplicate business-key count. All mismatch/broken/duplicate counts must be zero.

- [ ] **Step 4: Verify in the browser**

Open the signed-in administrator route. Confirm Company -> Yovel East -> Sariaya Branch -> Agri Financing Officer expands, selection adds `project_key`, reload preserves it, and the project module workspace renders non-empty groups/modules at desktop and mobile widths.

- [ ] **Step 5: Review the final diff**

Run `git diff --check` and inspect only the five planned files plus the plan/test additions. Do not stage or revert unrelated worktree changes.
