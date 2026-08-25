<?php
declare(strict_types=1);

function yovel_admin_operations_projection_has_stable_key(array $record): bool
{
    foreach ($record as $key => $value) {
        if (preg_match('/_key$/', (string) $key) === 1) {
            $value = trim((string) $value);
            if ($value !== '' && strlen($value) <= 160) {
                return true;
            }
        }
    }
    return false;
}

function yovel_admin_operations_projection_state(string $contractId, array $company, array $providers): array
{
    if (!is_callable($providers[$contractId] ?? null)) {
        return ['contract_id' => $contractId, 'status' => 'UNAVAILABLE', 'blocking' => false, 'records' => [], 'message' => 'Owner provider unavailable.'];
    }
    try {
        $records = yovel_admin_operations_call_read_contract($contractId, $company, ['projection' => 'operations-setup'], $providers)['records'];
        foreach ($records as $record) {
            if (!is_array($record) || !yovel_admin_operations_projection_has_stable_key($record)) {
                throw new RuntimeException('Owner projection record has no stable reference key.');
            }
        }
        return [
            'contract_id' => $contractId,
            'status' => 'AVAILABLE',
            'blocking' => false,
            'records' => yovel_admin_operations_sanitize_evidence($records),
            'message' => '',
        ];
    } catch (Throwable) {
        return ['contract_id' => $contractId, 'status' => 'UNAVAILABLE', 'blocking' => false, 'records' => [], 'message' => 'Owner response unavailable or invalid.'];
    }
}

function yovel_admin_operations_owner_actions(): array
{
    return [
        ['owner' => 'Platform', 'label' => 'Manage companies and branches', 'href' => '?view=platform'],
        ['owner' => 'Identity', 'label' => 'Manage users and roles', 'href' => '?view=platform&section=users'],
        ['owner' => 'HR', 'label' => 'Manage workforce approvers', 'href' => '?view=hr'],
        ['owner' => 'Finance', 'label' => 'Manage finance defaults', 'href' => '?view=accounting-finance'],
        ['owner' => 'Inventory', 'label' => 'Manage warehouse defaults', 'href' => '?view=inventory-warehouse'],
        ['owner' => 'Assets', 'label' => 'Manage asset defaults', 'href' => '?view=assets-maintenance'],
    ];
}

function yovel_admin_operations_authorization_projection(array $company, array $providers = []): array
{
    yovel_admin_operations_contract_scope($company);
    $contracts = [];
    foreach ([
        'shared.identity-permission-directory.v1',
        'platform.company-scope.v1',
        'owners.document-lifecycle-catalog.v1',
        'hr.workforce-directory.v1',
    ] as $contractId) {
        $contracts[$contractId] = yovel_admin_operations_projection_state($contractId, $company, $providers);
    }
    return ['contracts' => $contracts, 'owner_actions' => array_slice(yovel_admin_operations_owner_actions(), 0, 3)];
}

function yovel_admin_operations_company_defaults_projection(array $company, array $providers = []): array
{
    yovel_admin_operations_contract_scope($company);
    $contracts = [];
    foreach ([
        'platform.company-directory.v1',
        'platform.company-branch-directory.v1',
        'accounting-finance.company-defaults.v1',
        'inventory-warehouse.company-defaults.v1',
        'assets-maintenance.company-defaults.v1',
        'accounting-finance.currency-directory.v1',
        'inventory-warehouse.unit-directory.v1',
    ] as $contractId) {
        $contracts[$contractId] = yovel_admin_operations_projection_state($contractId, $company, $providers);
    }
    return ['contracts' => $contracts, 'owner_actions' => yovel_admin_operations_owner_actions()];
}

function yovel_admin_operations_authorization_policies(array $company): array
{
    yovel_admin_operations_schema();
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    $rows = bx_db()->GetAll(
        'SELECT * FROM project_company_operations_authorization_policy WHERE company_key_hash=? ORDER BY policy_name,policy_key',
        [$companyKeyHash]
    ) ?: [];
    foreach ($rows as &$row) {
        $row['threshold_amount'] = yovel_admin_operations_policy_decimal((string) $row['threshold_amount']);
    }
    unset($row);
    return $rows;
}

function yovel_admin_operations_policy_decimal(string $value): string
{
    $parts = explode('.', trim($value), 2);
    $whole = ltrim($parts[0], '0');
    $whole = $whole === '' ? '0' : $whole;
    return $whole . '.' . str_pad(substr($parts[1] ?? '', 0, 6), 6, '0');
}

function yovel_admin_operations_policy_reference_exists(array $projection, string $contractId, string $field, string $value): bool
{
    if ($value === '') {
        return true;
    }
    foreach (($projection['contracts'][$contractId]['records'] ?? []) as $record) {
        if (is_array($record) && hash_equals((string) ($record[$field] ?? ''), $value)) {
            return true;
        }
    }
    return false;
}

function yovel_admin_operations_persist_authorization_policy(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input,
    array $providers = []
): array {
    yovel_admin_operations_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_operations_scope($company, $admin);
    $policyKey = trim((string) ($input['policy_key'] ?? ''));
    if ($policyKey !== '' && !yovel_admin_is_uuid($policyKey)) {
        throw new InvalidArgumentException('Operations authorization policy key is invalid.');
    }
    $policyName = yovel_admin_operations_bounded_text($input['policy_name'] ?? '', 'Policy name', 180);
    $lifecycleKey = yovel_admin_operations_bounded_text($input['lifecycle_key'] ?? '', 'Lifecycle key', 160);
    $documentType = yovel_admin_operations_bounded_text($input['document_type'] ?? '', 'Document type', 160);
    $actionCode = strtoupper(yovel_admin_operations_bounded_text($input['action_code'] ?? '', 'Action', 80));
    $currencyKey = yovel_admin_operations_bounded_text($input['currency_key'] ?? '', 'Currency key', 160, false);
    $approverUserKey = yovel_admin_operations_bounded_text($input['approver_user_key'] ?? '', 'Approver user key', 160);
    $approverRoleKey = yovel_admin_operations_bounded_text($input['approver_role_key'] ?? '', 'Approver role key', 160, false);
    $approverEmployeeKey = yovel_admin_operations_bounded_text($input['approver_employee_key'] ?? '', 'Approver employee key', 160, false);
    $status = strtoupper(trim((string) ($input['policy_status'] ?? 'ACTIVE')));
    $threshold = trim((string) ($input['threshold_amount'] ?? '0'));
    if (preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?$/', $threshold) !== 1) {
        throw new InvalidArgumentException('Authorization threshold must be a non-negative amount with up to six decimals.');
    }
    $threshold = yovel_admin_operations_policy_decimal($threshold);
    if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ARCHIVED'], true)) {
        throw new InvalidArgumentException('Authorization policy status is invalid.');
    }

    $projection = yovel_admin_operations_authorization_projection($company, $providers);
    if (!yovel_admin_operations_policy_reference_exists($projection, 'owners.document-lifecycle-catalog.v1', 'lifecycle_key', $lifecycleKey)) {
        throw new InvalidArgumentException('Lifecycle reference is unavailable for this company.');
    }
    if (!yovel_admin_operations_policy_reference_exists($projection, 'shared.identity-permission-directory.v1', 'user_key', $approverUserKey)
        || !yovel_admin_operations_policy_reference_exists($projection, 'shared.identity-permission-directory.v1', 'role_key', $approverRoleKey)
        || !yovel_admin_operations_policy_reference_exists($projection, 'hr.workforce-directory.v1', 'employee_key', $approverEmployeeKey)) {
        throw new InvalidArgumentException('Approver references are unavailable for this company.');
    }

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Operations authorization policy transaction could not start.');
    }
    try {
        $existing = $policyKey !== '' ? $db->GetRow(
            'SELECT * FROM project_company_operations_authorization_policy WHERE company_key_hash=? AND policy_key=? FOR UPDATE',
            [$companyKeyHash, $policyKey]
        ) : false;
        if ($policyKey !== '' && !$existing) {
            throw new InvalidArgumentException('Authorization policy was not found for this company.');
        }
        $policyKey = $existing ? (string) $existing['policy_key'] : bx_uuid();
        $values = [$policyName, $lifecycleKey, $documentType, $actionCode, $threshold, $currencyKey, $approverUserKey, $approverRoleKey, $approverEmployeeKey, $status];
        if ($existing) {
            yovel_admin_operations_db_execute($db,
                'UPDATE project_company_operations_authorization_policy SET policy_name=?,lifecycle_key=?,document_type=?,action_code=?,threshold_amount=?,currency_key=?,approver_user_key=?,approver_role_key=?,approver_employee_key=?,policy_status=?,updated_by_admin_key=? WHERE company_key_hash=? AND policy_key=?',
                [...$values, $adminKey, $companyKeyHash, $policyKey], 'Operations authorization policy update');
        } else {
            yovel_admin_operations_db_execute($db,
                'INSERT INTO project_company_operations_authorization_policy (policy_key,company_key,company_key_hash,policy_name,lifecycle_key,document_type,action_code,threshold_amount,currency_key,approver_user_key,approver_role_key,approver_employee_key,policy_status,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$policyKey, $companyKey, $companyKeyHash, ...$values, $adminKey, $adminKey], 'Operations authorization policy persistence');
        }
        $saved = $db->GetRow(
            'SELECT * FROM project_company_operations_authorization_policy WHERE company_key_hash=? AND policy_key=?',
            [$companyKeyHash, $policyKey]
        );
        if (!$saved
            || (string) $saved['company_key'] !== $companyKey
            || (string) $saved['policy_name'] !== $policyName
            || (string) $saved['lifecycle_key'] !== $lifecycleKey
            || (string) $saved['document_type'] !== $documentType
            || (string) $saved['action_code'] !== $actionCode
            || yovel_admin_operations_policy_decimal((string) $saved['threshold_amount']) !== $threshold
            || (string) $saved['currency_key'] !== $currencyKey
            || (string) $saved['approver_user_key'] !== $approverUserKey
            || (string) $saved['approver_role_key'] !== $approverRoleKey
            || (string) $saved['approver_employee_key'] !== $approverEmployeeKey
            || (string) $saved['policy_status'] !== $status) {
            throw new RuntimeException('Operations authorization policy read-back verification failed.');
        }
        yovel_admin_operations_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_operations_authorization_policy', $policyKey, $companyKeyHash, (string) $adminKey, [
            'policy_name' => $policyName, 'lifecycle_key' => $lifecycleKey, 'document_type' => $documentType,
            'action_code' => $actionCode, 'threshold_amount' => $threshold, 'currency_key' => $currencyKey,
            'approver_user_key' => $approverUserKey, 'approver_role_key' => $approverRoleKey,
            'approver_employee_key' => $approverEmployeeKey, 'policy_status' => $status,
        ], 'Company administrator saved an Operations authorization policy overlay.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Operations authorization policy transaction could not commit.');
        }
        $saved['threshold_amount'] = $threshold;
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}
