# Project Module Registry Group Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the long project module registry table with ERP module group buttons that open module-specific detail modals.

**Architecture:** This is a frontend-only presentation change inside `ProjectModuleWorkspace`. Existing `data.projectModuleGroups`, `data.projectModules`, and `data.projectModuleForms` remain the source of truth.

**Tech Stack:** React, TypeScript, BuilderX shadcn-style `Dialog`, existing table/badge components, Vite build.

## Global Constraints

- No database schema changes.
- No nested bordered cards inside panels or dialogs.
- Modal must have header, scrollable body, and footer.
- Page behind the modal must not become the modal scroll container.
- Existing project-company route and query parameters must continue to work.

---

### Task 1: Registry Group Buttons And Detail Modal

**Files:**
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: `data.projectModuleGroups`, `data.projectModules`, `data.projectModuleForms`
- Produces: `ProjectModuleWorkspace` rendering group buttons and a selected group dialog

- [ ] **Step 1: Add selected group state**

Inside `ProjectModuleWorkspace`, add:

```tsx
const [selectedModuleGroupKey, setSelectedModuleGroupKey] = useState<string | null>(null)
const selectedModuleGroup = selectedModuleGroupKey ? moduleGroups.find((group) => group.module_group_key === selectedModuleGroupKey) : null
const selectedGroupModules = selectedModuleGroup ? modulesByGroup[selectedModuleGroup.module_group_key] || [] : []
```

- [ ] **Step 2: Replace inline group sections with buttons**

Render a responsive grid of `button` elements inside the `DashboardPanel` body. Each button calls `setSelectedModuleGroupKey(group.module_group_key)`.

- [ ] **Step 3: Add the module group dialog**

Render a `Dialog` controlled by `selectedModuleGroup`, with a sticky header, scrollable body, and footer close action. Use `FoundationTable` inside the scrollable body for the selected group modules.

- [ ] **Step 4: Verify**

Run:

```bash
npm run build
php -l administrator/index.php
curl -sS -o /tmp/project-company-route.html -w '%{http_code}\n' 'http://localhost/erpsystem/administrator/?tab=project-company&company_key=JMyOyz1UVRwdpLC9j9db&branch_key=8a4a9105-0dd6-4391-accb-02d2f7893136&project_key=e8bef804-9ce6-480d-af5f-226878171fc8'
```

Expected:

- frontend build exits 0.
- PHP lint reports no syntax errors.
- route returns HTTP 200.
