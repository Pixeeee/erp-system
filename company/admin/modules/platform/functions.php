<?php
declare(strict_types=1);

function yovel_admin_platform_nav(): array
{
    return [
        'modules' => ['label' => 'Modules', 'icon' => '▥', 'description' => 'Enable, describe, and organize the ERP workspaces available to this company.'],
        'departments' => ['label' => 'Departments', 'icon' => '▧', 'description' => 'Create branch departments from fixed ERP standards or custom company structures.'],
        'users' => ['label' => 'Users', 'icon' => '☷', 'description' => 'Create company users and assign their roles, groups, and direct permission grants.'],
        'roles' => ['label' => 'Roles', 'icon' => '◇', 'description' => 'Define reusable job-based access sets for department and management work.'],
        'permissions' => ['label' => 'Permissions', 'icon' => '◈', 'description' => 'Name the exact actions or reports that can be granted across modules.'],
        'groups' => ['label' => 'Groups', 'icon' => '⌘', 'description' => 'Bundle users and permission grants for teams, committees, or temporary access pools.'],
    ];
}

function yovel_admin_platform_section(): string
{
    $section = strtolower(trim((string) ($_GET['section'] ?? 'departments')));
    return array_key_exists($section, yovel_admin_platform_nav()) ? $section : 'departments';
}

function yovel_admin_platform_sections(string $companyName): array
{
    return [
        ['label' => 'User accounts', 'description' => 'Create company users, activate accounts, and keep profile ownership inside ' . $companyName . '.'],
        ['label' => 'Roles', 'description' => 'Build company roles for department workspaces, management scopes, and reviewer access.'],
        ['label' => 'Permissions / RBAC', 'description' => 'Assign permissions by role so department users start with only their default workspace.'],
        ['label' => 'Cross-department access grants', 'description' => 'Grant selected reports or modules across departments without opening full Finance or Admin access.'],
        ['label' => 'Approval rules', 'description' => 'Define who can submit, review, approve, reject, and delegate operational requests.'],
        ['label' => 'Delegated authority', 'description' => 'Temporarily pass approval authority while preserving audit visibility.'],
        ['label' => 'Audit logs', 'description' => 'Review access changes, login events, setup changes, and sensitive platform activity.'],
        ['label' => 'Security and integrations', 'description' => 'Prepare API access, security settings, and company integration controls.'],
    ];
}

function yovel_admin_dashboard_metrics(array $company, array $admin): array
{
    $branchCount = bx_count(
        'project_company_branch',
        "company_key_hash = " . bx_db()->qstr((string) $company['company_key_hash']) . " AND branch_status <> 'DELETED'"
    );
    $erpGroupCount = count(yovel_admin_erp_groups());

    return [
        ['label' => 'Company Scope', 'value' => (string) $company['company_name'], 'description' => 'Signed in to company administration only.'],
        ['label' => 'Branches', 'value' => (string) $branchCount, 'description' => 'Active company branch records.'],
        ['label' => 'Admin Account', 'value' => (string) $admin['admin_status'], 'description' => (string) $admin['admin_login']],
        ['label' => 'ERP Workspaces', 'value' => (string) $erpGroupCount, 'description' => 'Department groups ready for module setup.'],
    ];
}

function yovel_admin_platform_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_module (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            module_code VARCHAR(80) NOT NULL,
            module_name VARCHAR(160) NOT NULL,
            module_icon VARCHAR(20) NULL,
            module_description TEXT NULL,
            module_status ENUM('DRAFT','ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            module_sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_module_code (company_key_hash, module_code),
            INDEX idx_project_company_module_company (company_key_hash),
            INDEX idx_project_company_module_status (module_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_user (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            user_login VARCHAR(80) NOT NULL,
            user_password_hash VARCHAR(255) NULL,
            user_name VARCHAR(160) NOT NULL,
            user_email VARCHAR(190) NOT NULL,
            user_department VARCHAR(120) NULL,
            user_status ENUM('DRAFT','ACTIVE','INACTIVE','LOCKED','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_user_login (company_key_hash, user_login),
            UNIQUE KEY uq_project_company_user_email (company_key_hash, user_email),
            INDEX idx_project_company_user_company (company_key_hash),
            INDEX idx_project_company_user_status (user_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_role (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            role_code VARCHAR(80) NOT NULL,
            role_name VARCHAR(160) NOT NULL,
            role_description TEXT NULL,
            role_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_role_code (company_key_hash, role_code),
            INDEX idx_project_company_role_company (company_key_hash),
            INDEX idx_project_company_role_status (role_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_permission (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            permission_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            module_key CHAR(36) NULL,
            permission_code VARCHAR(120) NOT NULL,
            permission_name VARCHAR(160) NOT NULL,
            permission_scope VARCHAR(80) NOT NULL DEFAULT 'module',
            permission_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_permission_code (company_key_hash, permission_code),
            INDEX idx_project_company_permission_company (company_key_hash),
            INDEX idx_project_company_permission_module (module_key),
            INDEX idx_project_company_permission_status (permission_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_group (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            group_key CHAR(36) NOT NULL UNIQUE,
            company_key VARCHAR(1500) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            group_code VARCHAR(80) NOT NULL,
            group_name VARCHAR(160) NOT NULL,
            group_description TEXT NULL,
            group_status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_group_code (company_key_hash, group_code),
            INDEX idx_project_company_group_company (company_key_hash),
            INDEX idx_project_company_group_status (group_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_user_role (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_key_hash CHAR(64) NOT NULL,
            user_key CHAR(36) NOT NULL,
            role_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_user_role (company_key_hash, user_key, role_key),
            INDEX idx_project_company_user_role_user (company_key_hash, user_key),
            INDEX idx_project_company_user_role_role (company_key_hash, role_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_user_group (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_key_hash CHAR(64) NOT NULL,
            user_key CHAR(36) NOT NULL,
            group_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_user_group (company_key_hash, user_key, group_key),
            INDEX idx_project_company_user_group_user (company_key_hash, user_key),
            INDEX idx_project_company_user_group_group (company_key_hash, group_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_user_permission (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_key_hash CHAR(64) NOT NULL,
            user_key CHAR(36) NOT NULL,
            permission_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_user_permission (company_key_hash, user_key, permission_key),
            INDEX idx_project_company_user_permission_user (company_key_hash, user_key),
            INDEX idx_project_company_user_permission_permission (company_key_hash, permission_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_role_permission (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_key_hash CHAR(64) NOT NULL,
            role_key CHAR(36) NOT NULL,
            permission_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_role_permission (company_key_hash, role_key, permission_key),
            INDEX idx_project_company_role_permission_role (company_key_hash, role_key),
            INDEX idx_project_company_role_permission_permission (company_key_hash, permission_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_group_permission (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_key_hash CHAR(64) NOT NULL,
            group_key CHAR(36) NOT NULL,
            permission_key CHAR(36) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_group_permission (company_key_hash, group_key, permission_key),
            INDEX idx_project_company_group_permission_group (company_key_hash, group_key),
            INDEX idx_project_company_group_permission_permission (company_key_hash, permission_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Platform schema update');
    }

    bx_add_column_if_missing('project_company_role', 'is_system', "TINYINT(1) NOT NULL DEFAULT 0 AFTER role_status");
    bx_add_column_if_missing('project_company_permission', 'is_system', "TINYINT(1) NOT NULL DEFAULT 0 AFTER permission_status");
    bx_add_column_if_missing('project_company_role_permission', 'company_key_hash', "CHAR(64) NOT NULL DEFAULT '' FIRST");
}

function yovel_admin_seed_company_modules(array $company, array $admin): void
{
    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $sortOrder = 10;

    foreach (yovel_admin_erp_groups() as $group) {
        $code = yovel_admin_code((string) $group['label']);
        if ($code === '') {
            continue;
        }
        $exists = (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_module WHERE company_key_hash = ? AND module_code = ?',
            [$companyKeyHash, $code]
        );
        if ($exists > 0) {
            $sortOrder += 10;
            continue;
        }

        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_module (
                module_key, company_key, company_key_hash, module_code, module_name, module_icon,
                module_description, module_status, module_sort_order, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?)",
            [
                bx_uuid(),
                $companyKey,
                $companyKeyHash,
                $code,
                (string) $group['label'],
                (string) $group['icon'],
                (string) $group['description'],
                $sortOrder,
                $adminKey,
                $adminKey,
            ],
            'ERP module seed'
        );
        $sortOrder += 10;
    }
}

function yovel_admin_platform_data(array $company, array $admin): array
{
    yovel_admin_platform_schema();
    yovel_admin_seed_company_modules($company, $admin);

    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $modules = $db->GetAll("SELECT * FROM project_company_module WHERE company_key_hash = ? AND module_status <> 'DELETED' ORDER BY module_sort_order ASC, module_name ASC", [$companyKeyHash]);
    $users = $db->GetAll("SELECT * FROM project_company_user WHERE company_key_hash = ? AND user_status <> 'DELETED' ORDER BY user_name ASC, user_login ASC", [$companyKeyHash]);
    $roles = $db->GetAll("SELECT * FROM project_company_role WHERE company_key_hash = ? AND role_status <> 'DELETED' ORDER BY role_name ASC", [$companyKeyHash]);
    $permissions = $db->GetAll("SELECT p.*, m.module_name FROM project_company_permission p LEFT JOIN project_company_module m ON m.module_key = p.module_key AND m.company_key_hash = p.company_key_hash WHERE p.company_key_hash = ? AND p.permission_status <> 'DELETED' ORDER BY p.permission_code ASC", [$companyKeyHash]);
    $groups = $db->GetAll("SELECT * FROM project_company_group WHERE company_key_hash = ? AND group_status <> 'DELETED' ORDER BY group_name ASC", [$companyKeyHash]);
    $departmentMasters = $db->GetAll("
        SELECT department_master_key, department_code, department_name, department_type, department_description, department_status, is_fixed
        FROM project_company_department_master
        WHERE department_status <> 'DELETED'
        ORDER BY is_fixed DESC, department_name ASC
    ");
    $branches = $db->GetAll("
        SELECT branch_key, branch_code, branch_name, branch_status
        FROM project_company_branch
        WHERE company_key_hash = ? AND branch_status <> 'DELETED'
        ORDER BY branch_name ASC
    ", [$companyKeyHash]);
    $departments = $db->GetAll("
        SELECT
            d.department_key,
            d.company_key,
            d.company_key_hash,
            d.branch_key,
            d.department_code,
            d.department_name,
            d.department_status,
            d.department_type,
            d.department_source,
            d.default_department_key,
            d.department_description,
            d.is_default,
            d.created_at,
            d.updated_at,
            COALESCE(b.branch_code, '') AS branch_code,
            COALESCE(b.branch_name, '') AS branch_name,
            COALESCE(b.branch_status, '') AS branch_status
        FROM project_company_department d
        LEFT JOIN project_company_branch b ON b.branch_key = d.branch_key
        WHERE d.company_key_hash = ? AND d.department_status <> 'DELETED'
        ORDER BY b.branch_name ASC, d.department_name ASC
    ", [$companyKeyHash]);

    return [
        'modules' => is_array($modules) ? $modules : [],
        'departmentMasters' => is_array($departmentMasters) ? $departmentMasters : [],
        'branches' => is_array($branches) ? $branches : [],
        'departments' => is_array($departments) ? $departments : [],
        'users' => is_array($users) ? $users : [],
        'roles' => is_array($roles) ? $roles : [],
        'permissions' => is_array($permissions) ? $permissions : [],
        'groups' => is_array($groups) ? $groups : [],
        'userRoles' => yovel_admin_link_map('project_company_user_role', 'user_key', 'role_key', $companyKeyHash),
        'userGroups' => yovel_admin_link_map('project_company_user_group', 'user_key', 'group_key', $companyKeyHash),
        'userPermissions' => yovel_admin_link_map('project_company_user_permission', 'user_key', 'permission_key', $companyKeyHash),
        'rolePermissions' => yovel_admin_link_map('project_company_role_permission', 'role_key', 'permission_key', $companyKeyHash),
        'groupPermissions' => yovel_admin_link_map('project_company_group_permission', 'group_key', 'permission_key', $companyKeyHash),
    ];
}

function yovel_admin_save_module(array $company, array $admin): string
{
    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $moduleKey = trim((string) ($_POST['module_key'] ?? ''));
    $code = yovel_admin_code((string) ($_POST['module_code'] ?? ''));
    $name = trim((string) ($_POST['module_name'] ?? ''));
    $icon = trim((string) ($_POST['module_icon'] ?? ''));
    $description = trim((string) ($_POST['module_description'] ?? ''));
    $status = yovel_admin_status((string) ($_POST['module_status'] ?? 'ACTIVE'), ['DRAFT', 'ACTIVE', 'INACTIVE', 'DELETED']);
    $sortOrder = max(0, (int) ($_POST['module_sort_order'] ?? 0));

    if ($code === '' || $name === '') {
        throw new InvalidArgumentException('Module code and name are required.');
    }

    $db->BeginTrans();
    try {
        if ($moduleKey === '') {
            $moduleKey = (string) $db->GetOne(
                'SELECT module_key FROM project_company_module WHERE company_key_hash = ? AND module_code = ? LIMIT 1',
                [$companyKeyHash, $code]
            );
        }

        if ($moduleKey !== '') {
            if (!yovel_admin_existing_key($db, 'project_company_module', 'module_key', $companyKeyHash, $moduleKey)) {
                throw new InvalidArgumentException('Module record was not found for this company.');
            }
            yovel_admin_db_execute(
                $db,
                'UPDATE project_company_module SET module_code = ?, module_name = ?, module_icon = ?, module_description = ?, module_status = ?, module_sort_order = ?, updated_by_admin_key = ? WHERE module_key = ? AND company_key_hash = ?',
                [$code, $name, $icon, $description, $status, $sortOrder, $adminKey, $moduleKey, $companyKeyHash],
                'Module update'
            );
            $action = 'UPDATE';
        } else {
            $moduleKey = bx_uuid();
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_module (
                    module_key, company_key, company_key_hash, module_code, module_name, module_icon,
                    module_description, module_status, module_sort_order, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$moduleKey, $companyKey, $companyKeyHash, $code, $name, $icon, $description, $status, $sortOrder, $adminKey, $adminKey],
                'Module create'
            );
            $action = 'CREATE';
        }

        $readBack = $db->GetRow('SELECT module_key, module_code, module_name FROM project_company_module WHERE module_key = ? AND company_key_hash = ? LIMIT 1', [$moduleKey, $companyKeyHash]);
        if (!$readBack || (string) $readBack['module_code'] !== $code || (string) $readBack['module_name'] !== $name) {
            throw new RuntimeException('Module read-back verification failed.');
        }
        bx_audit($action, 'project_company_module', $moduleKey, ['module_code' => $code, 'module_name' => $name, 'company' => $company['company_name']], 'Company platform module saved.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Module saved.';
}

function yovel_admin_save_permission(array $company, array $admin): string
{
    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $permissionKey = trim((string) ($_POST['permission_key'] ?? ''));
    $moduleKey = trim((string) ($_POST['module_key'] ?? ''));
    $code = strtolower(yovel_admin_code((string) ($_POST['permission_code'] ?? '')));
    $name = trim((string) ($_POST['permission_name'] ?? ''));
    $scope = trim((string) ($_POST['permission_scope'] ?? 'module'));
    $status = yovel_admin_status((string) ($_POST['permission_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED']);

    if ($code === '' || $name === '') {
        throw new InvalidArgumentException('Permission code and name are required.');
    }
    if ($moduleKey !== '' && !yovel_admin_existing_key($db, 'project_company_module', 'module_key', $companyKeyHash, $moduleKey)) {
        throw new InvalidArgumentException('Selected module does not belong to this company.');
    }

    $db->BeginTrans();
    try {
        if ($permissionKey === '') {
            $permissionKey = (string) $db->GetOne('SELECT permission_key FROM project_company_permission WHERE company_key_hash = ? AND permission_code = ? LIMIT 1', [$companyKeyHash, $code]);
        }
        if ($permissionKey !== '') {
            if (!yovel_admin_existing_key($db, 'project_company_permission', 'permission_key', $companyKeyHash, $permissionKey)) {
                throw new InvalidArgumentException('Permission record was not found for this company.');
            }
            yovel_admin_db_execute($db, 'UPDATE project_company_permission SET module_key = ?, permission_code = ?, permission_name = ?, permission_scope = ?, permission_status = ?, updated_by_admin_key = ? WHERE permission_key = ? AND company_key_hash = ?', [$moduleKey !== '' ? $moduleKey : null, $code, $name, $scope, $status, $adminKey, $permissionKey, $companyKeyHash], 'Permission update');
            $action = 'UPDATE';
        } else {
            $permissionKey = bx_uuid();
            yovel_admin_db_execute($db, 'INSERT INTO project_company_permission (permission_key, company_key, company_key_hash, module_key, permission_code, permission_name, permission_scope, permission_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$permissionKey, $companyKey, $companyKeyHash, $moduleKey !== '' ? $moduleKey : null, $code, $name, $scope, $status, $adminKey, $adminKey], 'Permission create');
            $action = 'CREATE';
        }

        $readBack = $db->GetRow('SELECT permission_key, permission_code, permission_name FROM project_company_permission WHERE permission_key = ? AND company_key_hash = ? LIMIT 1', [$permissionKey, $companyKeyHash]);
        if (!$readBack || (string) $readBack['permission_code'] !== $code || (string) $readBack['permission_name'] !== $name) {
            throw new RuntimeException('Permission read-back verification failed.');
        }
        bx_audit($action, 'project_company_permission', $permissionKey, ['permission_code' => $code, 'permission_name' => $name, 'company' => $company['company_name']], 'Company platform permission saved.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Permission saved.';
}

function yovel_admin_save_role(array $company, array $admin): string
{
    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $roleKey = trim((string) ($_POST['role_key'] ?? ''));
    $code = yovel_admin_code((string) ($_POST['role_code'] ?? ''));
    $name = trim((string) ($_POST['role_name'] ?? ''));
    $description = trim((string) ($_POST['role_description'] ?? ''));
    $status = yovel_admin_status((string) ($_POST['role_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED']);
    $permissionKeys = yovel_admin_valid_keys($db, 'project_company_permission', 'permission_key', $companyKeyHash, yovel_admin_post_array('permission_keys'));

    if ($code === '' || $name === '') {
        throw new InvalidArgumentException('Role code and name are required.');
    }

    $db->BeginTrans();
    try {
        if ($roleKey === '') {
            $roleKey = (string) $db->GetOne('SELECT role_key FROM project_company_role WHERE company_key_hash = ? AND role_code = ? LIMIT 1', [$companyKeyHash, $code]);
        }
        if ($roleKey !== '') {
            if (!yovel_admin_existing_key($db, 'project_company_role', 'role_key', $companyKeyHash, $roleKey)) {
                throw new InvalidArgumentException('Role record was not found for this company.');
            }
            yovel_admin_db_execute($db, 'UPDATE project_company_role SET role_code = ?, role_name = ?, role_description = ?, role_status = ?, updated_by_admin_key = ? WHERE role_key = ? AND company_key_hash = ?', [$code, $name, $description, $status, $adminKey, $roleKey, $companyKeyHash], 'Role update');
            $action = 'UPDATE';
        } else {
            $roleKey = bx_uuid();
            yovel_admin_db_execute($db, 'INSERT INTO project_company_role (role_key, company_key, company_key_hash, role_code, role_name, role_description, role_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$roleKey, $companyKey, $companyKeyHash, $code, $name, $description, $status, $adminKey, $adminKey], 'Role create');
            $action = 'CREATE';
        }

        yovel_admin_replace_links($db, 'project_company_role_permission', $companyKeyHash, 'role_key', $roleKey, 'permission_key', $permissionKeys);
        $readBack = $db->GetRow('SELECT role_key, role_code, role_name FROM project_company_role WHERE role_key = ? AND company_key_hash = ? LIMIT 1', [$roleKey, $companyKeyHash]);
        if (!$readBack || (string) $readBack['role_code'] !== $code || (string) $readBack['role_name'] !== $name) {
            throw new RuntimeException('Role read-back verification failed.');
        }
        bx_audit($action, 'project_company_role', $roleKey, ['role_code' => $code, 'role_name' => $name, 'permissions' => $permissionKeys, 'company' => $company['company_name']], 'Company platform role saved.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Role saved.';
}

function yovel_admin_save_group(array $company, array $admin): string
{
    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $groupKey = trim((string) ($_POST['group_key'] ?? ''));
    $code = yovel_admin_code((string) ($_POST['group_code'] ?? ''));
    $name = trim((string) ($_POST['group_name'] ?? ''));
    $description = trim((string) ($_POST['group_description'] ?? ''));
    $status = yovel_admin_status((string) ($_POST['group_status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED']);
    $permissionKeys = yovel_admin_valid_keys($db, 'project_company_permission', 'permission_key', $companyKeyHash, yovel_admin_post_array('permission_keys'));
    $userKeys = yovel_admin_valid_keys($db, 'project_company_user', 'user_key', $companyKeyHash, yovel_admin_post_array('user_keys'));

    if ($code === '' || $name === '') {
        throw new InvalidArgumentException('Group code and name are required.');
    }

    $db->BeginTrans();
    try {
        if ($groupKey === '') {
            $groupKey = (string) $db->GetOne('SELECT group_key FROM project_company_group WHERE company_key_hash = ? AND group_code = ? LIMIT 1', [$companyKeyHash, $code]);
        }
        if ($groupKey !== '') {
            if (!yovel_admin_existing_key($db, 'project_company_group', 'group_key', $companyKeyHash, $groupKey)) {
                throw new InvalidArgumentException('Group record was not found for this company.');
            }
            yovel_admin_db_execute($db, 'UPDATE project_company_group SET group_code = ?, group_name = ?, group_description = ?, group_status = ?, updated_by_admin_key = ? WHERE group_key = ? AND company_key_hash = ?', [$code, $name, $description, $status, $adminKey, $groupKey, $companyKeyHash], 'Group update');
            $action = 'UPDATE';
        } else {
            $groupKey = bx_uuid();
            yovel_admin_db_execute($db, 'INSERT INTO project_company_group (group_key, company_key, company_key_hash, group_code, group_name, group_description, group_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$groupKey, $companyKey, $companyKeyHash, $code, $name, $description, $status, $adminKey, $adminKey], 'Group create');
            $action = 'CREATE';
        }

        yovel_admin_replace_links($db, 'project_company_group_permission', $companyKeyHash, 'group_key', $groupKey, 'permission_key', $permissionKeys);
        yovel_admin_replace_links($db, 'project_company_user_group', $companyKeyHash, 'group_key', $groupKey, 'user_key', $userKeys);
        $readBack = $db->GetRow('SELECT group_key, group_code, group_name FROM project_company_group WHERE group_key = ? AND company_key_hash = ? LIMIT 1', [$groupKey, $companyKeyHash]);
        if (!$readBack || (string) $readBack['group_code'] !== $code || (string) $readBack['group_name'] !== $name) {
            throw new RuntimeException('Group read-back verification failed.');
        }
        bx_audit($action, 'project_company_group', $groupKey, ['group_code' => $code, 'group_name' => $name, 'permissions' => $permissionKeys, 'users' => $userKeys, 'company' => $company['company_name']], 'Company platform group saved.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Group saved.';
}

function yovel_admin_save_user(array $company, array $admin): string
{
    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $userKey = trim((string) ($_POST['user_key'] ?? ''));
    $login = trim((string) ($_POST['user_login'] ?? ''));
    $name = trim((string) ($_POST['user_name'] ?? ''));
    $email = trim((string) ($_POST['user_email'] ?? ''));
    $department = trim((string) ($_POST['user_department'] ?? ''));
    $password = (string) ($_POST['user_password'] ?? '');
    $status = yovel_admin_status((string) ($_POST['user_status'] ?? 'ACTIVE'), ['DRAFT', 'ACTIVE', 'INACTIVE', 'LOCKED', 'DELETED']);
    $roleKeys = yovel_admin_valid_keys($db, 'project_company_role', 'role_key', $companyKeyHash, yovel_admin_post_array('role_keys'));
    $groupKeys = yovel_admin_valid_keys($db, 'project_company_group', 'group_key', $companyKeyHash, yovel_admin_post_array('group_keys'));
    $permissionKeys = yovel_admin_valid_keys($db, 'project_company_permission', 'permission_key', $companyKeyHash, yovel_admin_post_array('permission_keys'));

    if ($login === '' || $name === '' || $email === '') {
        throw new InvalidArgumentException('User login, name, and email are required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }

    $db->BeginTrans();
    try {
        if ($userKey === '') {
            $userKey = (string) $db->GetOne('SELECT user_key FROM project_company_user WHERE company_key_hash = ? AND user_login = ? LIMIT 1', [$companyKeyHash, $login]);
        }
        if ($userKey !== '') {
            if (!yovel_admin_existing_key($db, 'project_company_user', 'user_key', $companyKeyHash, $userKey)) {
                throw new InvalidArgumentException('User record was not found for this company.');
            }
            $params = [$login, $name, $email, $department, $status, $adminKey];
            $passwordSql = '';
            if ($password !== '') {
                $passwordSql = ', user_password_hash = ?';
                $params[] = bx_password_hash($password);
            }
            $params[] = $userKey;
            $params[] = $companyKeyHash;
            yovel_admin_db_execute($db, 'UPDATE project_company_user SET user_login = ?, user_name = ?, user_email = ?, user_department = ?, user_status = ?, updated_by_admin_key = ?' . $passwordSql . ' WHERE user_key = ? AND company_key_hash = ?', $params, 'User update');
            $action = 'UPDATE';
        } else {
            $userKey = bx_uuid();
            yovel_admin_db_execute($db, 'INSERT INTO project_company_user (user_key, company_key, company_key_hash, user_login, user_password_hash, user_name, user_email, user_department, user_status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$userKey, $companyKey, $companyKeyHash, $login, $password !== '' ? bx_password_hash($password) : null, $name, $email, $department, $status, $adminKey, $adminKey], 'User create');
            $action = 'CREATE';
        }

        yovel_admin_replace_links($db, 'project_company_user_role', $companyKeyHash, 'user_key', $userKey, 'role_key', $roleKeys);
        yovel_admin_replace_links($db, 'project_company_user_group', $companyKeyHash, 'user_key', $userKey, 'group_key', $groupKeys);
        yovel_admin_replace_links($db, 'project_company_user_permission', $companyKeyHash, 'user_key', $userKey, 'permission_key', $permissionKeys);
        $readBack = $db->GetRow('SELECT user_key, user_login, user_name, user_email FROM project_company_user WHERE user_key = ? AND company_key_hash = ? LIMIT 1', [$userKey, $companyKeyHash]);
        if (!$readBack || (string) $readBack['user_login'] !== $login || (string) $readBack['user_name'] !== $name || (string) $readBack['user_email'] !== $email) {
            throw new RuntimeException('User read-back verification failed.');
        }
        bx_audit($action, 'project_company_user', $userKey, ['user_login' => $login, 'roles' => $roleKeys, 'groups' => $groupKeys, 'permissions' => $permissionKeys, 'company' => $company['company_name']], 'Company platform user saved.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'User saved.';
}
