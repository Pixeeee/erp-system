<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/foundation.php';

$db = bx_db();
$requiredColumns = [
    'project_module_group' => [
        'module_group_key',
        'company_key_hash',
        'branch_key',
        'project_key',
        'source_company_module_key',
        'module_group_code',
        'module_group_name',
        'module_group_index',
        'module_group_status',
    ],
    'project_module' => [
        'module_index',
        'module_key',
        'company_key_hash',
        'branch_key',
        'project_key',
        'module_group_key',
        'module_code',
        'module_name',
        'module_table_name',
        'module_sort_order',
        'module_status',
    ],
    'project_module_form' => [
        'form_key',
        'company_key_hash',
        'branch_key',
        'project_key',
        'module_group_key',
        'module_key',
        'source_form_schema_key',
        'source_builder_form_key',
        'form_code',
        'form_name',
        'form_schema_json',
        'form_status',
        'form_sort_order',
    ],
];

foreach ($requiredColumns as $table => $columns) {
    $actual = $db->GetCol(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
        [BUILDERX_DB_NAME, $table]
    );
    if (!is_array($actual) || $actual === []) {
        throw new RuntimeException('Project module schema is missing table ' . $table . '.');
    }
    foreach ($columns as $column) {
        if (!in_array($column, $actual, true)) {
            throw new RuntimeException('Project module schema is missing ' . $table . '.' . $column . '.');
        }
    }
}

$requiredIndexes = [
    'project_module_group' => [
        'uq_project_module_group_project_code',
        'idx_project_module_group_scope',
        'idx_project_module_group_sort',
    ],
    'project_module' => [
        'uq_project_module_scope_code',
        'uq_project_module_table_name',
        'idx_project_module_scope',
        'idx_project_module_sort',
    ],
    'project_module_form' => [
        'uq_project_module_form_code',
        'idx_project_module_form_scope',
        'idx_project_module_form_sort',
    ],
];

foreach ($requiredIndexes as $table => $indexes) {
    $actual = $db->GetCol(
        'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [BUILDERX_DB_NAME, $table]
    );
    foreach ($indexes as $index) {
        if (!is_array($actual) || !in_array($index, $actual, true)) {
            throw new RuntimeException('Project module schema is missing index ' . $index . '.');
        }
    }
}

$activeProjectCount = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_project WHERE project_status <> 'DELETED'");
$groupCount = (int) $db->GetOne("SELECT COUNT(*) FROM project_module_group WHERE module_group_status <> 'DELETED'");
$moduleCount = (int) $db->GetOne("SELECT COUNT(*) FROM project_module WHERE module_status <> 'DELETED'");
if ($activeProjectCount > 0 && ($groupCount === 0 || $moduleCount === 0)) {
    throw new RuntimeException('Project module seed did not create groups and modules for active projects.');
}

$moduleRows = $db->GetAll('SELECT module_index, module_table_name FROM project_module ORDER BY module_index');
if (!is_array($moduleRows)) {
    throw new RuntimeException('Project module numbering read-back failed.');
}
foreach ($moduleRows as $row) {
    $moduleIndex = (int) ($row['module_index'] ?? 0);
    if ($moduleIndex < 100) {
        throw new RuntimeException('Project module numbering started below 100.');
    }
    if ((string) ($row['module_table_name'] ?? '') !== 'project_module_' . $moduleIndex) {
        throw new RuntimeException('Project module logical table name mismatch.');
    }
}

$brokenGroupOwnership = (int) $db->GetOne("
    SELECT COUNT(*)
    FROM project_module_group module_group
    LEFT JOIN project_company_project project_record ON project_record.project_key = module_group.project_key
    LEFT JOIN project_company_branch branch_record ON branch_record.branch_key = module_group.branch_key
    WHERE project_record.project_key IS NULL
       OR branch_record.branch_key IS NULL
       OR project_record.branch_key <> module_group.branch_key
       OR project_record.company_key_hash <> module_group.company_key_hash
       OR branch_record.company_key_hash <> module_group.company_key_hash
");
$brokenModuleOwnership = (int) $db->GetOne("
    SELECT COUNT(*)
    FROM project_module module_record
    LEFT JOIN project_module_group module_group ON module_group.module_group_key = module_record.module_group_key
    WHERE module_group.module_group_key IS NULL
       OR module_group.project_key <> module_record.project_key
       OR module_group.branch_key <> module_record.branch_key
       OR module_group.company_key_hash <> module_record.company_key_hash
");
$brokenFormOwnership = (int) $db->GetOne("
    SELECT COUNT(*)
    FROM project_module_form form_record
    LEFT JOIN project_module module_record ON module_record.module_key = form_record.module_key
    WHERE module_record.module_key IS NULL
       OR module_record.project_key <> form_record.project_key
       OR module_record.branch_key <> form_record.branch_key
       OR module_record.company_key_hash <> form_record.company_key_hash
       OR module_record.module_group_key <> form_record.module_group_key
");
if ($brokenGroupOwnership !== 0 || $brokenModuleOwnership !== 0 || $brokenFormOwnership !== 0) {
    throw new RuntimeException('Project module ownership verification failed.');
}

$beforeGroups = $db->GetAll('SELECT module_group_key, project_key, module_group_code, module_group_index FROM project_module_group ORDER BY module_group_key');
$beforeModules = $db->GetAll('SELECT module_key, module_index, project_key, module_group_key, module_code, module_table_name FROM project_module ORDER BY module_key');
$beforeForms = $db->GetAll('SELECT form_key, project_key, module_key, form_code FROM project_module_form ORDER BY form_key');
bx_seed_project_module_hierarchy();
$afterGroups = $db->GetAll('SELECT module_group_key, project_key, module_group_code, module_group_index FROM project_module_group ORDER BY module_group_key');
$afterModules = $db->GetAll('SELECT module_key, module_index, project_key, module_group_key, module_code, module_table_name FROM project_module ORDER BY module_key');
$afterForms = $db->GetAll('SELECT form_key, project_key, module_key, form_code FROM project_module_form ORDER BY form_key');
if ($beforeGroups !== $afterGroups || $beforeModules !== $afterModules || $beforeForms !== $afterForms) {
    throw new RuntimeException('Project module seed is not idempotent.');
}

$hrFormFieldTableExists = (int) $db->GetOne(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
    [BUILDERX_DB_NAME, 'project_company_hr_form_field']
) > 0;
$hrBuilderFormTableExists = (int) $db->GetOne(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
    [BUILDERX_DB_NAME, 'project_company_hr_builder_form']
) > 0;
$employeeModules = $db->GetAll("
    SELECT module_record.module_key, module_record.project_key, module_record.company_key_hash
    FROM project_module module_record
    INNER JOIN project_module_group module_group ON module_group.module_group_key = module_record.module_group_key
    WHERE module_group.module_group_code = 'HR_DEPARTMENT'
      AND module_record.module_code = 'EMPLOYEE_PROFILES'
      AND module_record.module_status <> 'DELETED'
");
foreach (is_array($employeeModules) ? $employeeModules : [] as $employeeModule) {
    $moduleKey = (string) $employeeModule['module_key'];
    $companyKeyHash = (string) $employeeModule['company_key_hash'];
    if ($hrFormFieldTableExists) {
        $expectedBuiltInForms = (int) $db->GetOne("
            SELECT COUNT(DISTINCT field_section)
            FROM project_company_hr_form_field
            WHERE company_key_hash = ? AND form_key = 'employee-profiles' AND field_status = 'ACTIVE'
        ", [$companyKeyHash]);
        $actualBuiltInForms = (int) $db->GetOne("
            SELECT COUNT(*)
            FROM project_module_form
            WHERE module_key = ? AND form_code LIKE 'BUILT_IN_%' AND form_status <> 'DELETED'
        ", [$moduleKey]);
        if ($actualBuiltInForms !== $expectedBuiltInForms) {
            throw new RuntimeException('Employee Profiles built-in form projection count mismatch.');
        }
    }
    if ($hrBuilderFormTableExists) {
        $sourceForms = $db->GetAll("
            SELECT builder_form_key, form_status
            FROM project_company_hr_builder_form
            WHERE company_key_hash = ? AND target_section = 'employee-profiles' AND form_status <> 'DELETED'
            ORDER BY builder_form_key
        ", [$companyKeyHash]);
        foreach (is_array($sourceForms) ? $sourceForms : [] as $sourceForm) {
            $projectedForm = $db->GetRow(
                'SELECT source_builder_form_key, form_status FROM project_module_form WHERE module_key = ? AND source_builder_form_key = ? LIMIT 1',
                [$moduleKey, (string) $sourceForm['builder_form_key']]
            );
            if (!is_array($projectedForm)
                || (string) ($projectedForm['source_builder_form_key'] ?? '') !== (string) $sourceForm['builder_form_key']
                || (string) ($projectedForm['form_status'] ?? '') !== (string) $sourceForm['form_status']) {
                throw new RuntimeException('Employee Profiles custom form projection or status preservation failed.');
            }
        }
    }
}

$administratorSource = (string) file_get_contents(dirname(__DIR__) . '/administrator/index.php');
$frontendSource = (string) file_get_contents(dirname(__DIR__) . '/frontend/src/App.tsx');
foreach (['projectModuleGroups', 'projectModuleForms', 'FROM project_module module_record'] as $marker) {
    if (!str_contains($administratorSource, $marker)) {
        throw new RuntimeException('Administrator payload is missing marker ' . $marker . '.');
    }
}
foreach (['<SidebarGroupLabel>Company</SidebarGroupLabel>', 'projectCompanyProjectKeyFromView', 'ProjectModuleWorkspace', 'project.project_key))'] as $marker) {
    if (!str_contains($frontendSource, $marker)) {
        throw new RuntimeException('Administrator frontend is missing marker ' . $marker . '.');
    }
}

echo json_encode([
    'schema_verified' => true,
    'numbering_verified' => true,
    'ownership_verified' => true,
    'direct_read_back_verified' => true,
    'idempotency_verified' => true,
    'built_in_form_projection_verified' => true,
    'custom_form_projection_verified' => true,
    'payload_contract_verified' => true,
    'sidebar_contract_verified' => true,
    'group_count' => $groupCount,
    'module_count' => $moduleCount,
    'form_count' => count($afterForms),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
