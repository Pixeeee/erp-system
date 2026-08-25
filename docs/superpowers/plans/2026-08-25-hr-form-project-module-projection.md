# HR Form Project Module Projection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Synchronize every saved company HR builder form into all matching project module form registries before the Save Form transaction commits.

**Architecture:** Add a targeted projector to the project module foundation layer because that layer owns project, branch, module group, module, and `project_module_form` relationships. Refactor HR Form Builder persistence into a testable service that retains the existing ADODB transaction, saves the company master and immutable version, invokes the projector using the same connection, verifies all records, and commits only when the complete write succeeds.

**Tech Stack:** PHP 8, ADODB, MySQL/MariaDB, existing BuilderX UUID/audit helpers, transaction-backed PHP integration tests.

## Global Constraints

- `project_company_hr_builder_form` remains the authoritative mutable form definition.
- `project_company_hr_builder_form_version` remains the immutable version history.
- `project_module_form` remains a project-scoped registry projection and never owns submissions or answers.
- One company form may create zero, one, or many project registry rows.
- `source_builder_form_key` must equal the source `builder_form_key` in every projection.
- All source, version, registry, retirement, and audit writes occur in one caller-owned transaction.
- Missing project feature modules are skipped because they are not valid projection targets.
- Target changes and source deletion mark obsolete registry rows `DELETED`; they are not physically removed.
- Every write uses parameterized ADODB SQL, checks failure immediately, and performs direct read-back before commit.
- Do not modify or unstage unrelated dirty worktree files. Use path-limited commits.

---

### Task 1: Targeted Project Module Form Synchronizer

**Files:**
- Modify: `app/foundation.php:2335-2435`
- Test: `tests/project-module-hierarchy.php:145-225`

**Interfaces:**
- Consumes: `bx_project_module_code(string): string`, `bx_project_module_find_feature(ADOConnection, string, string, string): ?array`, `bx_project_module_upsert_form(...)`, a validated company array, and a saved HR builder form row.
- Produces: `bx_project_module_sync_hr_builder_form(ADOConnection $db, array $company, array $builderForm): array{projected:list<array>,retired:list<array>}`.
- Changes: `bx_project_module_upsert_form(...): array` returns its already-verified `project_module_form` read-back row; existing callers may ignore the result.

- [ ] **Step 1: Add the failing synchronizer contract and fixture assertions**

Extend `tests/project-module-hierarchy.php` after the existing global seed/idempotency assertions. Use the first active company with at least one active HR Employee Profiles module, create a unique source form inside a test-owned transaction, and exercise create, unchanged update, retarget, archive, and delete.

```php
if (!function_exists('bx_project_module_sync_hr_builder_form')) {
    throw new RuntimeException('Immediate HR builder form projection is not implemented.');
}

$projectionFixture = $db->GetRow("
    SELECT company_record.company_key, company_record.company_key_hash
    FROM project_company company_record
    INNER JOIN project_company_project project_record
        ON project_record.company_key_hash = company_record.company_key_hash
       AND project_record.project_status <> 'DELETED'
    INNER JOIN project_module_group module_group
        ON module_group.project_key = project_record.project_key
       AND module_group.module_group_code = 'HR_DEPARTMENT'
       AND module_group.module_group_status <> 'DELETED'
    INNER JOIN project_module module_record
        ON module_record.module_group_key = module_group.module_group_key
       AND module_record.module_code = 'EMPLOYEE_PROFILES'
       AND module_record.module_status <> 'DELETED'
    WHERE company_record.company_status <> 'DELETED'
    ORDER BY company_record.x_id
    LIMIT 1
");
if (!is_array($projectionFixture) || $projectionFixture === []) {
    throw new RuntimeException('An HR project module projection fixture is required.');
}

$projectionFormKey = bx_uuid();
$projectionForm = [
    'builder_form_key' => $projectionFormKey,
    'company_key' => (string) $projectionFixture['company_key'],
    'company_key_hash' => (string) $projectionFixture['company_key_hash'],
    'target_section' => 'employee-profiles',
    'form_title' => 'Immediate Projection Fixture',
    'form_description' => 'Project module synchronization fixture.',
    'form_status' => 'ACTIVE',
    'schema_json' => '{"version":2,"questions":[],"rows":[]}',
];

if ($db->BeginTrans() === false) {
    throw new RuntimeException('Projection fixture transaction could not start.');
}
try {
    $firstProjection = bx_project_module_sync_hr_builder_form($db, [
        'company_key' => (string) $projectionFixture['company_key'],
        'company_key_hash' => (string) $projectionFixture['company_key_hash'],
    ], $projectionForm);
    $firstKeys = array_column($firstProjection['projected'], 'form_key');
    if ($firstKeys === []) {
        throw new RuntimeException('Immediate HR form projection created no registry rows.');
    }

    $sameProjection = bx_project_module_sync_hr_builder_form($db, [
        'company_key' => (string) $projectionFixture['company_key'],
        'company_key_hash' => (string) $projectionFixture['company_key_hash'],
    ], $projectionForm);
    if ($firstKeys !== array_column($sameProjection['projected'], 'form_key')) {
        throw new RuntimeException('Unchanged projection replaced stable registry keys.');
    }

    $projectionForm['target_section'] = 'departments';
    $retargeted = bx_project_module_sync_hr_builder_form($db, $projectionFixture, $projectionForm);
    if ($retargeted['retired'] === []) {
        throw new RuntimeException('Retargeting did not retire old registry mappings.');
    }

    $projectionForm['form_status'] = 'ARCHIVED';
    $archived = bx_project_module_sync_hr_builder_form($db, $projectionFixture, $projectionForm);
    foreach ($archived['projected'] as $row) {
        if ((string) $row['form_status'] !== 'ARCHIVED') {
            throw new RuntimeException('Archived source status was not projected.');
        }
    }

    $projectionForm['form_status'] = 'DELETED';
    $deleted = bx_project_module_sync_hr_builder_form($db, $projectionFixture, $projectionForm);
    if ($deleted['projected'] !== []) {
        throw new RuntimeException('Deleted source retained active projections.');
    }
    $remaining = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_module_form WHERE source_builder_form_key = ? AND form_status <> 'DELETED'",
        [$projectionFormKey]
    );
    if ($remaining !== 0) {
        throw new RuntimeException('Deleted source left a live registry mapping.');
    }
} finally {
    $db->RollbackTrans();
}
```

- [ ] **Step 2: Run the project hierarchy test and verify the new contract fails**

Run:

```bash
php tests/project-module-hierarchy.php
```

Expected: FAIL with `Immediate HR builder form projection is not implemented.`

- [ ] **Step 3: Return verified rows from the existing registry upsert**

Change the return type of `bx_project_module_upsert_form()` from `void` to `array`. Keep its current create/update comparison, parameterized SQL, audit, and field-by-field read-back. Return `$readBack` only after every expected field matches.

```php
function bx_project_module_upsert_form(
    ADOConnection $db,
    array $project,
    array $moduleGroup,
    array $module,
    string $formCode,
    string $formName,
    string $formDescription,
    string $formSchemaJson,
    ?string $sourceFormSchemaKey,
    ?string $sourceBuilderFormKey,
    string $formStatus,
    int $formSortOrder
): array {
    foreach ($expected as $field => $value) {
        if ((string) ($readBack[$field] ?? '') !== (string) ($value ?? '')) {
            throw new RuntimeException('Project module form direct read-back mismatch for ' . $field . '.');
        }
    }

    return $readBack;
}
```

- [ ] **Step 4: Implement the focused synchronizer in the project module layer**

Add `bx_project_module_sync_hr_builder_form()` immediately after `bx_project_module_upsert_form()`. Validate UUID/company ownership fields, target section, source status, title/description/schema lengths, and then lock all existing rows for the source key.

Load projection targets with fixed SQL:

```php
$projects = $db->GetAll("
    SELECT project_record.project_key, project_record.company_key, project_record.company_key_hash,
           project_record.branch_key, project_record.project_name
    FROM project_company_project project_record
    INNER JOIN project_company_branch branch_record
        ON branch_record.branch_key = project_record.branch_key
       AND branch_record.company_key_hash = project_record.company_key_hash
       AND branch_record.branch_status <> 'DELETED'
    WHERE project_record.company_key_hash = ?
      AND project_record.project_status <> 'DELETED'
    ORDER BY project_record.x_id
", [$companyKeyHash]);
```

For each project, load its active `HR_DEPARTMENT` group, resolve the target feature with `bx_project_module_find_feature()`, preserve the existing mapping's sort order or use the next module sort increment, and call `bx_project_module_upsert_form()` with:

```php
$savedProjection = bx_project_module_upsert_form(
    $db,
    $project,
    $moduleGroup,
    $module,
    'HR_BUILDER_' . bx_project_module_code($builderFormKey),
    $formTitle,
    $formDescription,
    $schemaJson,
    null,
    $builderFormKey,
    $formStatus,
    $sortOrder
);
$projected[] = $savedProjection;
$expectedFormKeys[(string) $savedProjection['form_key']] = true;
```

When the source status is `DELETED`, skip target creation. For every locked existing source row absent from `$expectedFormKeys`, execute a parameterized status update to `DELETED`, check the write result, audit the change, and directly read back `form_key`, `source_builder_form_key`, and `form_status`. Return:

```php
return [
    'projected' => $projected,
    'retired' => $retired,
];
```

Do not call `BeginTrans()`, `CommitTrans()`, or `RollbackTrans()` in this function.

- [ ] **Step 5: Run the focused test and verify create, idempotency, retarget, archive, and delete pass**

Run:

```bash
php tests/project-module-hierarchy.php
```

Expected: PASS with `custom_form_projection_verified` still true and no ownership or idempotency failures.

- [ ] **Step 6: Commit the synchronizer and focused test**

```bash
git add app/foundation.php tests/project-module-hierarchy.php
git commit --only app/foundation.php tests/project-module-hierarchy.php -m "Sync HR forms into project modules"
```

### Task 2: Transactional HR Form Persistence Integration

**Files:**
- Modify: `company/admin/modules/hr/forms.php:1181-1304`
- Modify: `company/admin/bootstrap/controller.php:109-116`
- Test: `tests/hr-database-driven-forms.php:425-490`

**Interfaces:**
- Consumes: `bx_project_module_sync_hr_builder_form(ADOConnection, array, array): array`, `yovel_admin_upsert_hr_builder_form_version(...)`, and normalized HR schema JSON.
- Produces: `yovel_admin_persist_hr_builder_form(ADOConnection $db, array $company, array $admin, array $input, ?callable $projector = null): array`.
- Preserves: `yovel_admin_save_hr_builder_form(array $company, array $admin): string` as the controller-facing action.

- [ ] **Step 1: Add failing create/update projection and rollback tests**

In `tests/hr-database-driven-forms.php`, add a committed fixture after the immutable-version test. Use the existing `$fixtureCompany`, `$fixtureAdmin`, and `$stableSchema` values. Track the generated form keys and remove registry rows, versions, masters, and matching audit records in `finally`.

```php
hr_database_assert(
    function_exists('yovel_admin_persist_hr_builder_form'),
    'Transactional HR builder form persistence is not implemented.'
);

$projectionPersistenceKey = bx_uuid();
$projectionFailureKey = bx_uuid();
$stableSchemaJson = json_encode($stableSchema, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
try {
    $createdForm = yovel_admin_persist_hr_builder_form($db, $fixtureCompany, $fixtureAdmin, [
        'builder_form_key' => $projectionPersistenceKey,
        'target_section' => 'employee-profiles',
        'form_title' => 'Immediate HR Projection',
        'form_description' => 'Create path.',
        'form_status' => 'DRAFT',
        'schema_json' => $stableSchemaJson,
    ]);
    $createdRegistryKeys = array_column($createdForm['projection']['projected'], 'form_key');
    hr_database_assert($createdRegistryKeys !== [], 'Committed HR form save created no project registry rows.');

    $updatedForm = yovel_admin_persist_hr_builder_form($db, $fixtureCompany, $fixtureAdmin, [
        'builder_form_key' => $projectionPersistenceKey,
        'target_section' => 'employee-profiles',
        'form_title' => 'Immediate HR Projection Updated',
        'form_description' => 'Update path.',
        'form_status' => 'ACTIVE',
        'schema_json' => $stableSchemaJson,
    ]);
    hr_database_assert(
        $createdRegistryKeys === array_column($updatedForm['projection']['projected'], 'form_key'),
        'HR form update replaced stable project registry keys.'
    );
    foreach ($updatedForm['projection']['projected'] as $registryRow) {
        hr_database_assert((string) $registryRow['form_name'] === 'Immediate HR Projection Updated', 'Registry title did not update.');
        hr_database_assert((string) $registryRow['form_status'] === 'ACTIVE', 'Registry status did not update.');
    }

    $projectionFailureRaised = false;
    try {
        yovel_admin_persist_hr_builder_form(
            $db,
            $fixtureCompany,
            $fixtureAdmin,
            [
                'builder_form_key' => $projectionFailureKey,
                'target_section' => 'employee-profiles',
                'form_title' => 'Projection Rollback Fixture',
                'form_status' => 'ACTIVE',
                'schema_json' => $stableSchemaJson,
            ],
            static function (ADOConnection $connection, array $scope, array $form): array {
                throw new RuntimeException('Forced project projection failure.');
            }
        );
    } catch (RuntimeException $error) {
        $projectionFailureRaised = $error->getMessage() === 'Forced project projection failure.';
    }
    hr_database_assert($projectionFailureRaised, 'Forced projection failure did not reach the transaction boundary.');
    hr_database_assert(
        (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_builder_form WHERE builder_form_key = ?', [$projectionFailureKey]) === 0,
        'Projection failure committed the HR form master.'
    );
    hr_database_assert(
        (int) $db->GetOne('SELECT COUNT(*) FROM project_company_hr_builder_form_version WHERE builder_form_key = ?', [$projectionFailureKey]) === 0,
        'Projection failure committed an immutable form version.'
    );
} finally {
    foreach ([$projectionPersistenceKey, $projectionFailureKey] as $cleanupKey) {
        $registryAuditKeys = $db->GetCol(
            'SELECT form_key FROM project_module_form WHERE source_builder_form_key = ?',
            [$cleanupKey]
        );
        foreach (is_array($registryAuditKeys) ? $registryAuditKeys : [] as $registryAuditKey) {
            $db->Execute(
                "DELETE FROM builder_audit_log WHERE record_key = ? AND module = 'project_module_form'",
                [(string) $registryAuditKey]
            );
        }
        $db->Execute('DELETE FROM project_module_form WHERE source_builder_form_key = ?', [$cleanupKey]);
        $db->Execute('DELETE FROM project_company_hr_builder_form_version WHERE builder_form_key = ?', [$cleanupKey]);
        $db->Execute('DELETE FROM project_company_hr_builder_form WHERE builder_form_key = ?', [$cleanupKey]);
        $db->Execute(
            "DELETE FROM builder_audit_log WHERE record_key = ? AND module IN ('project_company_hr_builder_form', 'project_company_hr_builder_form_version')",
            [$cleanupKey]
        );
    }
}
```

- [ ] **Step 2: Run the HR database test and verify the persistence contract fails**

Run:

```bash
php tests/hr-database-driven-forms.php
```

Expected: FAIL with `Transactional HR builder form persistence is not implemented.`

- [ ] **Step 3: Extract a testable HR builder persistence service**

Move validation and transaction logic from `yovel_admin_save_hr_builder_form()` into:

```php
function yovel_admin_persist_hr_builder_form(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input,
    ?callable $projector = null
): array
```

The service must:

1. Validate company/admin scope and normalize the input.
2. Begin the transaction and lock an existing source row when a key is supplied.
3. Upsert the company master and immutable version using the existing logic.
4. Read the saved master back before projection.
5. Resolve `$projector` to `bx_project_module_sync_hr_builder_form(...)` when no callable is injected.
6. Invoke it with the same `$db`, `$company`, and saved master row before source audit and commit.
7. Include projected and retired counts in the source audit event.
8. Verify `CommitTrans()` succeeds.
9. Return the saved master with `current_form_version_key`, `current_version_number`, and `projection`.
10. Roll back and rethrow every exception.

```php
$projector ??= static fn (ADOConnection $connection, array $scope, array $form): array =>
    bx_project_module_sync_hr_builder_form($connection, $scope, $form);
$projection = $projector($db, $company, $saved);

$saved['current_form_version_key'] = (string) ($version['form_version_key'] ?? '');
$saved['current_version_number'] = (int) ($version['version_number'] ?? 0);
$saved['projection'] = $projection;
return $saved;
```

The optional callable is an internal dependency boundary for deterministic rollback testing; no request value may control it.

- [ ] **Step 4: Keep the POST action thin and preserve the generated key for redirect**

Replace the controller-facing wrapper body with a call to the persistence service:

```php
function yovel_admin_save_hr_builder_form(array $company, array $admin): string
{
    $saved = yovel_admin_persist_hr_builder_form(bx_db(), $company, $admin, [
        'builder_form_key' => (string) ($_POST['builder_form_key'] ?? ''),
        'target_section' => (string) ($_POST['target_section'] ?? 'employee-profiles'),
        'form_title' => (string) ($_POST['form_title'] ?? ''),
        'form_description' => (string) ($_POST['form_description'] ?? ''),
        'form_status' => (string) ($_POST['form_status'] ?? 'DRAFT'),
        'schema_json' => (string) ($_POST['schema_json'] ?? '{}'),
    ]);
    $GLOBALS['yovel_admin_saved_hr_builder_form_key'] = (string) $saved['builder_form_key'];

    return 'HR builder form saved.';
}
```

In `company/admin/bootstrap/controller.php`, prefer the saved global key so a newly created form redirects directly into Existing Form mode:

```php
$returnBuilderFormKey = trim((string) (
    $GLOBALS['yovel_admin_saved_hr_builder_form_key']
    ?? $_POST['builder_form_key']
    ?? ''
));
```

- [ ] **Step 5: Run focused persistence and hierarchy tests**

Run:

```bash
php tests/hr-database-driven-forms.php
php tests/project-module-hierarchy.php
```

Expected: both PASS. The HR result must retain existing schema/version/submission flags and include a new immediate projection verification flag.

- [ ] **Step 6: Run syntax and route checks**

Run:

```bash
php -l app/foundation.php
php -l company/admin/modules/hr/forms.php
php -l company/admin/bootstrap/controller.php
php -l company/yovel-east/admin/index.php
curl -fsS -o /tmp/hr-form-project-projection.html -w '%{http_code}\n' 'http://localhost/erpsystem/company/yovel-east/admin/?view=hr&section=dashboard&builder=1&builder_mode=new&builder_target=employee-profiles'
git diff --check
```

Expected: every lint command reports no syntax errors, the route returns `200`, and the diff check prints no errors.

- [ ] **Step 7: Commit the transactional integration**

```bash
git add company/admin/modules/hr/forms.php company/admin/bootstrap/controller.php tests/hr-database-driven-forms.php
git commit --only company/admin/modules/hr/forms.php company/admin/bootstrap/controller.php tests/hr-database-driven-forms.php -m "Project saved HR forms immediately"
```

## Final Verification

Run the complete focused suite from `/var/www/html/erpsystem`:

```bash
php tests/hr-database-driven-forms.php
php tests/project-module-hierarchy.php
php tests/company-admin-modular-architecture.php
php -l app/foundation.php
php -l company/admin/modules/hr/forms.php
php -l company/admin/modules/hr/submissions.php
php -l company/admin/bootstrap/controller.php
php -l company/yovel-east/admin/index.php
curl -fsS -o /tmp/hr-form-project-projection-final.html -w '%{http_code}\n' 'http://localhost/erpsystem/company/yovel-east/admin/?view=hr&section=dashboard&builder=1&builder_mode=new&builder_target=employee-profiles'
git diff --check
```

Expected: all three tests pass, all PHP files report no syntax errors, the route returns `200`, and the worktree diff contains no whitespace errors.
