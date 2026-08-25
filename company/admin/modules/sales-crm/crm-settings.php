<?php
declare(strict_types=1);

function yovel_admin_sales_crm_authorized_admin(array $company, array $admin): array
{
    $row = bx_db()->GetRow(
        "SELECT admin_key, company_key_hash, admin_login, admin_name, admin_email, admin_status
         FROM project_company_admin
         WHERE admin_key = ? AND company_key_hash = ? AND admin_status = 'ACTIVE'
         LIMIT 1",
        [(string) ($admin['admin_key'] ?? ''), (string) ($company['company_key_hash'] ?? '')]
    );
    if (!is_array($row) || $row === []) {
        throw new InvalidArgumentException('An authorized active company administrator is required.');
    }

    return $row;
}

function yovel_admin_sales_crm_settings_defaults(): array
{
    return [
        'settings_key' => '',
        'settings_version' => 0,
        'crm_enabled' => 1,
        'selling_enabled' => 1,
        'restrict_to_allowed_users' => 0,
        'default_lead_status' => 'OPEN',
        'default_opportunity_stage' => 'Qualification',
        'default_customer_group' => '',
        'default_price_list' => '',
        'allow_duplicate_lead_email' => 0,
        'validate_selling_price' => 0,
        'allowed_users' => [],
    ];
}

function yovel_admin_sales_crm_admin_directory(array $company): array
{
    yovel_admin_sales_crm_schema();
    $rows = bx_db()->GetAll(
        "SELECT admin_key, admin_login, admin_name, admin_email
         FROM project_company_admin
         WHERE company_key_hash = ? AND admin_status = 'ACTIVE'
         ORDER BY admin_name, admin_login",
        [(string) $company['company_key_hash']]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_sales_crm_settings(array $company, ?array $admin = null): array
{
    yovel_admin_sales_crm_schema();
    if ($admin !== null) {
        yovel_admin_sales_crm_authorized_admin($company, $admin);
    }
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_sales_crm_settings WHERE company_key_hash = ? LIMIT 1',
        [(string) $company['company_key_hash']]
    );
    if (!is_array($row) || $row === []) {
        return yovel_admin_sales_crm_settings_defaults();
    }
    $allowed = bx_db()->GetAll(
        "SELECT allowed.allowed_user_key, allowed.admin_key, allowed.allow_crm, allowed.allow_selling,
                allowed.manage_settings, admins.admin_login, admins.admin_name, admins.admin_email
         FROM project_company_sales_crm_allowed_user allowed
         INNER JOIN project_company_admin admins
            ON admins.admin_key = allowed.admin_key
           AND admins.company_key_hash = allowed.company_key_hash
           AND admins.admin_status = 'ACTIVE'
         WHERE allowed.company_key_hash = ? AND allowed.settings_key = ?
         ORDER BY admins.admin_name, admins.admin_login",
        [(string) $company['company_key_hash'], (string) $row['settings_key']]
    );
    $row['allowed_users'] = is_array($allowed) ? $allowed : [];

    return array_merge(yovel_admin_sales_crm_settings_defaults(), $row);
}

function yovel_admin_sales_crm_access(array $company, array $admin, ?array $settings = null): array
{
    $authorized = yovel_admin_sales_crm_authorized_admin($company, $admin);
    $settings ??= yovel_admin_sales_crm_settings($company);
    if ((int) $settings['restrict_to_allowed_users'] !== 1) {
        return [
            'crm' => (int) $settings['crm_enabled'] === 1,
            'selling' => (int) $settings['selling_enabled'] === 1,
            'manage_settings' => true,
        ];
    }
    foreach ($settings['allowed_users'] as $allowed) {
        if (hash_equals((string) $authorized['admin_key'], (string) $allowed['admin_key'])) {
            return [
                'crm' => (int) $settings['crm_enabled'] === 1 && (int) $allowed['allow_crm'] === 1,
                'selling' => (int) $settings['selling_enabled'] === 1 && (int) $allowed['allow_selling'] === 1,
                'manage_settings' => (int) $allowed['manage_settings'] === 1,
            ];
        }
    }

    return ['crm' => false, 'selling' => false, 'manage_settings' => false];
}

function yovel_admin_sales_crm_require_scope(array $company, array $admin, string $scope, bool $manageSettings = false): array
{
    $settings = yovel_admin_sales_crm_settings($company, $admin);
    $access = yovel_admin_sales_crm_access($company, $admin, $settings);
    $allowed = $manageSettings ? !empty($access['manage_settings']) : !empty($access[$scope]);
    if (!$allowed) {
        throw new InvalidArgumentException($manageSettings
            ? 'This administrator cannot manage Sales / CRM settings.'
            : 'This administrator does not have access to the requested Sales / CRM workspace.');
    }

    return $access;
}

function yovel_admin_sales_crm_input_keys(array $input, string $name): array
{
    $values = is_array($input[$name] ?? null) ? $input[$name] : [];
    $keys = [];
    foreach ($values as $value) {
        $key = trim((string) $value);
        if ($key === '') {
            continue;
        }
        if (!yovel_admin_is_uuid($key)) {
            throw new InvalidArgumentException('Sales / CRM allowed-user selections contain an invalid administrator key.');
        }
        $keys[$key] = true;
    }

    return array_keys($keys);
}

function yovel_admin_sales_crm_input_bool(array $input, string $name): int
{
    return filter_var($input[$name] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
}

function yovel_admin_save_sales_crm_settings(array $company, array $admin, array $input, ?callable $failureInjector = null): string
{
    yovel_admin_sales_crm_schema();
    $authorized = yovel_admin_sales_crm_authorized_admin($company, $admin);
    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $authorized['admin_key'];
    $expectedVersion = filter_var($input['expected_version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($expectedVersion === false) {
        throw new InvalidArgumentException('A valid Sales / CRM settings version is required.');
    }
    $crmEnabled = yovel_admin_sales_crm_input_bool($input, 'crm_enabled');
    $sellingEnabled = yovel_admin_sales_crm_input_bool($input, 'selling_enabled');
    $restricted = yovel_admin_sales_crm_input_bool($input, 'restrict_to_allowed_users');
    $leadStatus = yovel_admin_status((string) ($input['default_lead_status'] ?? 'OPEN'), ['DRAFT', 'OPEN', 'QUALIFIED'], '');
    $opportunityStage = trim((string) ($input['default_opportunity_stage'] ?? 'Qualification'));
    $customerGroup = trim((string) ($input['default_customer_group'] ?? ''));
    $priceList = trim((string) ($input['default_price_list'] ?? ''));
    $allowDuplicateEmail = yovel_admin_sales_crm_input_bool($input, 'allow_duplicate_lead_email');
    $validateSellingPrice = yovel_admin_sales_crm_input_bool($input, 'validate_selling_price');
    if ($leadStatus === '') {
        throw new InvalidArgumentException('Select a valid default Lead status.');
    }
    foreach (['Opportunity stage' => [$opportunityStage, 120], 'Customer group' => [$customerGroup, 120], 'Price list' => [$priceList, 120]] as $label => [$value, $max]) {
        if ($value === '' && $label === 'Opportunity stage') {
            throw new InvalidArgumentException('Default opportunity stage is required.');
        }
        if (strlen($value) > $max) {
            throw new InvalidArgumentException($label . ' exceeds the allowed length.');
        }
    }
    $crmKeys = yovel_admin_sales_crm_input_keys($input, 'crm_user_keys');
    $sellingKeys = yovel_admin_sales_crm_input_keys($input, 'selling_user_keys');
    $managerKeys = yovel_admin_sales_crm_input_keys($input, 'settings_manager_keys');
    $selectedKeys = array_values(array_unique(array_merge($crmKeys, $sellingKeys, $managerKeys)));
    if ($restricted && $managerKeys === []) {
        throw new InvalidArgumentException('Restricted access requires at least one settings manager.');
    }

    $db->BeginTrans();
    try {
        $current = $db->GetRow(
            'SELECT * FROM project_company_sales_crm_settings WHERE company_key_hash = ? FOR UPDATE',
            [$companyHash]
        );
        $currentVersion = (int) ($current['settings_version'] ?? 0);
        if ($currentVersion !== (int) $expectedVersion) {
            throw new RuntimeException('Sales / CRM settings changed since this form was opened. Reload and try again.');
        }
        if ($current && (int) $current['restrict_to_allowed_users'] === 1) {
            $manager = (int) $db->GetOne(
                'SELECT COUNT(*) FROM project_company_sales_crm_allowed_user WHERE company_key_hash = ? AND settings_key = ? AND admin_key = ? AND manage_settings = 1',
                [$companyHash, (string) $current['settings_key'], $adminKey]
            );
            if ($manager !== 1) {
                throw new InvalidArgumentException('This administrator cannot manage Sales / CRM settings.');
            }
        }
        if ($selectedKeys !== []) {
            $placeholders = implode(',', array_fill(0, count($selectedKeys), '?'));
            $params = array_merge([$companyHash], $selectedKeys);
            $activeKeys = $db->GetCol(
                "SELECT admin_key FROM project_company_admin WHERE company_key_hash = ? AND admin_status = 'ACTIVE' AND admin_key IN ($placeholders)",
                $params
            );
            sort($selectedKeys);
            $activeKeys = is_array($activeKeys) ? array_map('strval', $activeKeys) : [];
            sort($activeKeys);
            if ($activeKeys !== $selectedKeys) {
                throw new InvalidArgumentException('Every allowed Sales / CRM user must be an active administrator in this company.');
            }
        }

        $settingsKey = $current ? (string) $current['settings_key'] : bx_uuid();
        $nextVersion = $currentVersion + 1;
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_sales_crm_settings (
                settings_key, company_key, company_key_hash, settings_version, crm_enabled, selling_enabled,
                restrict_to_allowed_users, default_lead_status, default_opportunity_stage,
                default_customer_group, default_price_list, allow_duplicate_lead_email,
                validate_selling_price, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                settings_version = VALUES(settings_version), crm_enabled = VALUES(crm_enabled),
                selling_enabled = VALUES(selling_enabled), restrict_to_allowed_users = VALUES(restrict_to_allowed_users),
                default_lead_status = VALUES(default_lead_status), default_opportunity_stage = VALUES(default_opportunity_stage),
                default_customer_group = VALUES(default_customer_group), default_price_list = VALUES(default_price_list),
                allow_duplicate_lead_email = VALUES(allow_duplicate_lead_email),
                validate_selling_price = VALUES(validate_selling_price), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $settingsKey, $companyKey, $companyHash, $nextVersion, $crmEnabled, $sellingEnabled,
                $restricted, $leadStatus, $opportunityStage, $customerGroup, $priceList,
                $allowDuplicateEmail, $validateSellingPrice, $adminKey, $adminKey,
            ],
            'Sales / CRM settings save'
        );
        yovel_admin_db_execute(
            $db,
            'DELETE FROM project_company_sales_crm_allowed_user WHERE company_key_hash = ? AND settings_key = ?',
            [$companyHash, $settingsKey],
            'Sales / CRM allowed-user replace'
        );
        foreach ($selectedKeys as $allowedAdminKey) {
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_sales_crm_allowed_user (
                    allowed_user_key, settings_key, company_key_hash, admin_key, allow_crm, allow_selling, manage_settings
                 ) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    bx_uuid(), $settingsKey, $companyHash, $allowedAdminKey,
                    in_array($allowedAdminKey, $crmKeys, true) ? 1 : 0,
                    in_array($allowedAdminKey, $sellingKeys, true) ? 1 : 0,
                    in_array($allowedAdminKey, $managerKeys, true) ? 1 : 0,
                ],
                'Sales / CRM allowed-user save'
            );
        }
        if ($failureInjector !== null) {
            $failureInjector();
        }
        $saved = $db->GetRow(
            'SELECT settings_key, settings_version, crm_enabled, selling_enabled, restrict_to_allowed_users, default_lead_status, default_opportunity_stage, default_customer_group, default_price_list, allow_duplicate_lead_email, validate_selling_price, updated_by_admin_key FROM project_company_sales_crm_settings WHERE company_key_hash = ? AND settings_key = ? LIMIT 1',
            [$companyHash, $settingsKey]
        );
        $expected = [
            'settings_key' => $settingsKey,
            'settings_version' => $nextVersion,
            'crm_enabled' => $crmEnabled,
            'selling_enabled' => $sellingEnabled,
            'restrict_to_allowed_users' => $restricted,
            'default_lead_status' => $leadStatus,
            'default_opportunity_stage' => $opportunityStage,
            'default_customer_group' => $customerGroup,
            'default_price_list' => $priceList,
            'allow_duplicate_lead_email' => $allowDuplicateEmail,
            'validate_selling_price' => $validateSellingPrice,
            'updated_by_admin_key' => $adminKey,
        ];
        foreach ($expected as $column => $value) {
            if (!is_array($saved) || (string) ($saved[$column] ?? '') !== (string) $value) {
                throw new RuntimeException('Sales / CRM settings read-back verification failed for ' . $column . '.');
            }
        }
        $allowedReadBack = $db->GetAll(
            'SELECT admin_key, allow_crm, allow_selling, manage_settings FROM project_company_sales_crm_allowed_user WHERE company_key_hash = ? AND settings_key = ? ORDER BY admin_key',
            [$companyHash, $settingsKey]
        );
        $expectedAllowed = [];
        foreach ($selectedKeys as $allowedAdminKey) {
            $expectedAllowed[] = [
                'admin_key' => $allowedAdminKey,
                'allow_crm' => in_array($allowedAdminKey, $crmKeys, true) ? '1' : '0',
                'allow_selling' => in_array($allowedAdminKey, $sellingKeys, true) ? '1' : '0',
                'manage_settings' => in_array($allowedAdminKey, $managerKeys, true) ? '1' : '0',
            ];
        }
        usort($expectedAllowed, static fn (array $a, array $b): int => strcmp($a['admin_key'], $b['admin_key']));
        $allowedReadBack = array_map(static fn (array $row): array => array_map('strval', $row), is_array($allowedReadBack) ? $allowedReadBack : []);
        if ($allowedReadBack !== $expectedAllowed) {
            throw new RuntimeException('Sales / CRM allowed-user read-back verification failed.');
        }
        bx_audit($current ? 'UPDATE' : 'CREATE', 'project_company_sales_crm_settings', $settingsKey, [
            'company_key' => $companyKey,
            'company_name' => (string) $company['company_name'],
            'settings_version' => $nextVersion,
            'crm_enabled' => $crmEnabled,
            'selling_enabled' => $sellingEnabled,
            'restricted' => $restricted,
            'allowed_user_count' => count($selectedKeys),
            'admin_key' => $adminKey,
        ], 'Company administrator changed Sales / CRM workspace settings.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Sales / CRM settings saved.';
}

function yovel_admin_sales_crm_preference(array $company, array $admin): array
{
    yovel_admin_sales_crm_schema();
    $authorized = yovel_admin_sales_crm_authorized_admin($company, $admin);
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_sales_crm_preference WHERE company_key_hash = ? AND admin_key = ? LIMIT 1',
        [(string) $company['company_key_hash'], (string) $authorized['admin_key']]
    );

    return is_array($row) && $row !== [] ? $row : [
        'preference_key' => '',
        'preference_version' => 0,
        'setup_dismissed' => 0,
        'tour_status' => 'NEW',
    ];
}

function yovel_admin_save_sales_crm_preference(array $company, array $admin, array $input): string
{
    yovel_admin_sales_crm_schema();
    $authorized = yovel_admin_sales_crm_authorized_admin($company, $admin);
    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $authorized['admin_key'];
    $expectedVersion = filter_var($input['expected_version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $tourStatus = yovel_admin_status((string) ($input['tour_status'] ?? 'NEW'), ['NEW', 'DISMISSED', 'COMPLETED'], '');
    $setupDismissed = yovel_admin_sales_crm_input_bool($input, 'setup_dismissed');
    if ($expectedVersion === false || $tourStatus === '') {
        throw new InvalidArgumentException('A valid Sales / CRM workspace preference is required.');
    }

    $db->BeginTrans();
    try {
        $current = $db->GetRow(
            'SELECT * FROM project_company_sales_crm_preference WHERE company_key_hash = ? AND admin_key = ? FOR UPDATE',
            [$companyHash, $adminKey]
        );
        $currentVersion = (int) ($current['preference_version'] ?? 0);
        if ($currentVersion !== (int) $expectedVersion) {
            throw new RuntimeException('Sales / CRM workspace preference changed. Reload and try again.');
        }
        $preferenceKey = $current ? (string) $current['preference_key'] : bx_uuid();
        $nextVersion = $currentVersion + 1;
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_sales_crm_preference (
                preference_key, company_key, company_key_hash, admin_key, preference_version, setup_dismissed, tour_status
             ) VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE preference_version = VALUES(preference_version),
                setup_dismissed = VALUES(setup_dismissed), tour_status = VALUES(tour_status)",
            [$preferenceKey, $companyKey, $companyHash, $adminKey, $nextVersion, $setupDismissed, $tourStatus],
            'Sales / CRM workspace preference save'
        );
        $saved = $db->GetRow(
            'SELECT preference_key, preference_version, setup_dismissed, tour_status FROM project_company_sales_crm_preference WHERE company_key_hash = ? AND admin_key = ? LIMIT 1',
            [$companyHash, $adminKey]
        );
        foreach (['preference_key' => $preferenceKey, 'preference_version' => $nextVersion, 'setup_dismissed' => $setupDismissed, 'tour_status' => $tourStatus] as $column => $value) {
            if (!is_array($saved) || (string) ($saved[$column] ?? '') !== (string) $value) {
                throw new RuntimeException('Sales / CRM preference read-back verification failed for ' . $column . '.');
            }
        }
        bx_audit($current ? 'UPDATE' : 'CREATE', 'project_company_sales_crm_preference', $preferenceKey, [
            'company_key' => $companyKey,
            'company_name' => (string) $company['company_name'],
            'admin_key' => $adminKey,
            'preference_version' => $nextVersion,
            'setup_dismissed' => $setupDismissed,
            'tour_status' => $tourStatus,
        ], 'Company administrator changed Sales / CRM workspace preferences.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return 'Sales / CRM workspace preference saved.';
}

function yovel_admin_sales_crm_workspace(array $company, array $admin): array
{
    $settings = yovel_admin_sales_crm_settings($company, $admin);
    $access = yovel_admin_sales_crm_access($company, $admin, $settings);
    $preference = yovel_admin_sales_crm_preference($company, $admin);
    $definitions = [
        ['section' => 'leads', 'label' => 'Leads', 'scope' => 'crm', 'available' => true, 'dependency_state' => ''],
        ['section' => 'prospects', 'label' => 'Prospects', 'scope' => 'crm', 'available' => true, 'dependency_state' => ''],
        ['section' => 'appointments', 'label' => 'Appointments', 'scope' => 'crm', 'available' => true, 'dependency_state' => ''],
        ['section' => 'opportunities', 'label' => 'Opportunities', 'scope' => 'crm', 'available' => false, 'dependency_state' => 'Available after SC-04'],
        ['section' => 'campaigns', 'label' => 'Campaigns', 'scope' => 'crm', 'available' => true, 'dependency_state' => ''],
        ['section' => 'customers', 'label' => 'Customers', 'scope' => 'selling', 'available' => false, 'dependency_state' => 'Available after SC-06'],
        ['section' => 'quotations', 'label' => 'Quotations', 'scope' => 'selling', 'available' => false, 'dependency_state' => 'Available after SC-07'],
        ['section' => 'sales-orders', 'label' => 'Sales orders', 'scope' => 'selling', 'available' => false, 'dependency_state' => 'Available after SC-08'],
        ['section' => 'customer-credit-limits', 'label' => 'Credit limits', 'scope' => 'selling', 'available' => false, 'dependency_state' => 'Requires SC-06 and Finance read contracts'],
    ];
    $shortcuts = [];
    foreach ($definitions as $definition) {
        if (empty($access[$definition['scope']])) {
            continue;
        }
        $definition['href'] = $definition['available'] ? './?view=sales-crm&section=' . rawurlencode($definition['section']) : '';
        $shortcuts[] = $definition;
    }
    $directory = [
        'CRM Masters' => array_values(array_filter($shortcuts, static fn (array $item): bool => $item['scope'] === 'crm')),
        'Selling Masters' => array_values(array_filter($shortcuts, static fn (array $item): bool => $item['scope'] === 'selling')),
        'Key Reports' => [],
    ];
    if ($access['crm']) {
        $directory['Key Reports'][] = ['section' => 'leads', 'label' => 'Lead details', 'scope' => 'crm', 'available' => true, 'href' => './?view=sales-crm&section=leads&report=lead-details', 'dependency_state' => ''];
        $directory['Key Reports'][] = ['section' => 'leads', 'label' => 'Lead conversion time', 'scope' => 'crm', 'available' => true, 'href' => './?view=sales-crm&section=leads&report=lead-conversion-time', 'dependency_state' => ''];
        $directory['Key Reports'][] = ['section' => 'leads', 'label' => 'Lead owner efficiency', 'scope' => 'crm', 'available' => true, 'href' => './?view=sales-crm&section=leads&report=lead-owner-efficiency', 'dependency_state' => ''];
        $directory['Key Reports'][] = ['section' => 'prospects', 'label' => 'Prospects engaged but not converted', 'scope' => 'crm', 'available' => true, 'href' => './?view=sales-crm&section=prospects&report=prospects-engaged', 'dependency_state' => ''];
        $directory['Key Reports'][] = ['section' => 'leads', 'label' => 'Address and contacts', 'scope' => 'crm', 'available' => true, 'href' => './?view=sales-crm&section=leads&report=address-contacts', 'dependency_state' => ''];
    }
    if ($access['selling']) {
        $directory['Key Reports'][] = ['section' => 'sales-analytics', 'label' => 'Sales analytics', 'scope' => 'selling', 'available' => false, 'href' => '', 'dependency_state' => 'Available after SC-10'];
        $directory['Key Reports'][] = ['section' => 'territory-performance', 'label' => 'Territory performance', 'scope' => 'selling', 'available' => false, 'href' => '', 'dependency_state' => 'Available after SC-10'];
    }

    $adminDirectory = $access['manage_settings'] ? yovel_admin_sales_crm_admin_directory($company) : [];
    if (!$access['manage_settings']) {
        $settings['allowed_users'] = [];
    }

    return [
        'settings' => $settings,
        'access' => $access,
        'preference' => $preference,
        'admin_directory' => $adminDirectory,
        'setup_steps' => [
            ['key' => 'access', 'label' => 'Set workspace access', 'complete' => (int) $settings['settings_version'] > 0, 'detail' => 'Choose which administrators can use CRM, Selling, and settings.'],
            ['key' => 'lead', 'label' => 'Capture the first Lead', 'complete' => false, 'detail' => 'Use the Lead form to establish a qualified pipeline record.'],
            ['key' => 'forms', 'label' => 'Review form layouts', 'complete' => true, 'detail' => 'Keep required workflow fields protected while arranging visible fields.'],
            ['key' => 'selling', 'label' => 'Prepare Selling defaults', 'complete' => (int) $settings['settings_version'] > 0 && (string) $settings['default_price_list'] !== '', 'detail' => 'Set the default customer group, price list, and validation policy.'],
        ],
        'shortcuts' => $shortcuts,
        'directory' => $directory,
    ];
}

function yovel_admin_sales_crm_handle_post(array $company, array $admin, string $action, array $input): array
{
    return match ($action) {
        'sales_crm_save_settings' => [
            'message' => yovel_admin_save_sales_crm_settings($company, $admin, $input),
            'section' => (string) ($input['section'] ?? 'leads'),
        ],
        'sales_crm_save_preference' => [
            'message' => yovel_admin_save_sales_crm_preference($company, $admin, $input),
            'section' => (string) ($input['section'] ?? 'leads'),
        ],
        'sales_crm_dashboard_save_lead' => [
            'message' => (static function () use ($company, $admin): string {
                try {
                    return yovel_admin_save_sales_lead($company, $admin);
                } catch (Throwable $error) {
                    yovel_admin_sales_crm_clear_rehydration('lead');
                    throw $error;
                }
            })(),
            'section' => 'dashboard',
        ],
        'sales_crm_save_prospect' => [
            'message' => (static function () use ($company, $admin, $input): string {
                $editing = trim((string) ($input['prospect_key'] ?? '')) !== '';
                yovel_admin_sales_prospect_save($company, $admin, $input);
                return $editing ? 'Prospect updated.' : 'Prospect created.';
            })(),
            'section' => 'prospects',
        ],
        'sales_crm_save_appointment' => [
            'message' => (static function () use ($company, $admin, $input): string {
                $editing = trim((string) ($input['appointment_key'] ?? '')) !== '';
                yovel_admin_sales_appointment_save($company, $admin, $input);
                return $editing ? 'Appointment updated.' : 'Appointment created.';
            })(),
            'section' => 'appointments',
        ],
        'sales_crm_save_appointment_settings' => [
            'message' => (static function () use ($company, $admin, $input): string {
                yovel_admin_sales_appointment_settings_save($company, $admin, $input);
                return 'Appointment settings saved.';
            })(),
            'section' => 'appointments',
        ],
        'sales_crm_save_appointment_slot' => [
            'message' => (static function () use ($company, $admin, $input): string {
                yovel_admin_sales_appointment_slot_save($company, $admin, $input);
                return 'Appointment slot saved.';
            })(),
            'section' => 'appointments',
        ],
        'sales_crm_save_note' => [
            'message' => (static function () use ($company, $admin, $input): string {
                yovel_admin_sales_crm_note_save($company, $admin, $input);
                return 'CRM Note created.';
            })(),
            'section' => (string) ($input['section'] ?? 'leads'),
        ],
        'sales_crm_save_reference' => [
            'message' => (static function () use ($company, $admin, $input): string {
                yovel_admin_sales_reference_save($company, $admin, (string) ($input['reference_type'] ?? ''), $input);
                return 'Sales reference saved.';
            })(),
            'section' => 'prospects',
        ],
        'sales_crm_assign_lead' => [
            'message' => (static function () use ($company, $admin, $input): string {
                yovel_admin_sales_lead_assign($company, $admin, $input);
                return 'Lead assignment saved.';
            })(),
            'section' => 'leads',
        ],
        'sales_crm_request_lead_conversion' => [
            'message' => (static function () use ($company, $admin, $input): string {
                $saved = yovel_admin_sales_lead_conversion_request($company, $admin, $input);
                return (string) ($saved['dependency_status'] ?? '') === 'AVAILABLE'
                    ? 'Lead conversion request queued.'
                    : 'Lead conversion request queued; required owner services are unavailable.';
            })(),
            'section' => 'leads',
        ],
        'sales_crm_import_leads' => [
            'message' => (static function () use ($company, $admin, $input): string {
                $result = yovel_admin_sales_lead_import($company, $admin, (string) ($input['csv_data'] ?? ''));
                return 'Lead import completed: ' . (int) $result['created'] . ' created, ' . (int) $result['updated'] . ' updated.';
            })(),
            'section' => 'leads',
        ],
        'sales_crm_save_campaign' => [
            'message' => (static function () use ($company, $admin, $input): string {
                $editing = trim((string) ($input['campaign_key'] ?? '')) !== '';
                yovel_admin_sales_campaign_save($company, $admin, $input);
                return $editing ? 'Campaign updated.' : 'Campaign created.';
            })(),
            'section' => 'campaigns',
        ],
        'sales_crm_save_campaign_schedule' => [
            'message' => (static function () use ($company, $admin, $input): string {
                yovel_admin_sales_campaign_schedule_save($company, $admin, $input);
                return 'Campaign email schedule saved.';
            })(),
            'section' => 'campaigns',
        ],
        'sales_crm_transition_campaign' => [
            'message' => (static function () use ($company, $admin, $input): string {
                $saved = yovel_admin_sales_campaign_transition($company, $admin, $input);
                return 'Campaign moved to ' . (string) $saved['campaign_status'] . '.';
            })(),
            'section' => 'campaigns',
        ],
        default => throw new InvalidArgumentException('Unknown Sales / CRM module action.'),
    };
}
