<?php
declare(strict_types=1);

final class YovelAdminSupportUnavailableDependency extends RuntimeException
{
    public function __construct(
        private readonly string $contract,
        private readonly string $formId,
        private readonly array $submittedValues
    ) {
        parent::__construct('UNAVAILABLE_DEPENDENCY: ' . $contract . ' is unavailable.');
    }

    public function rehydration(): array
    {
        return yovel_admin_support_service_rehydration($this->formId, $this->submittedValues);
    }
}

function yovel_admin_support_service_lock_company(
    ADOConnection $db,
    string $companyKey,
    string $companyHash
): void {
    $locked = $db->GetRow(
        "SELECT company_key, company_key_hash FROM project_company
         WHERE company_key = ? AND company_key_hash = ? AND company_status = 'ACTIVE'
         FOR UPDATE",
        [$companyKey, $companyHash]
    );
    yovel_admin_support_service_verify_read_back(
        is_array($locked) ? $locked : null,
        ['company_key' => $companyKey, 'company_key_hash' => $companyHash],
        'Support company lock'
    );
}

function yovel_admin_support_service_optional_uuid(array $input, string $key, string $label): string
{
    $value = trim((string) ($input[$key] ?? ''));
    if ($value !== '' && !yovel_admin_is_uuid($value)) {
        throw new InvalidArgumentException($label . ' key is invalid.');
    }

    return $value;
}

function yovel_admin_support_service_status(array $input, string $key, string $label): string
{
    $status = strtoupper(trim((string) ($input[$key] ?? 'ACTIVE')));
    if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ARCHIVED'], true)) {
        throw new InvalidArgumentException($label . ' status is invalid.');
    }

    return $status;
}

function yovel_admin_support_service_assert_owned_key(
    string $table,
    string $keyColumn,
    string $recordKey,
    string $companyHash,
    string $label,
    bool $activeOnly = false
): array {
    if ($recordKey === '') {
        return [];
    }
    $allowed = [
        'project_company_support_issue_priority' => ['issue_priority_key', 'priority_status'],
        'project_company_support_issue_type' => ['issue_type_key', 'issue_type_status'],
        'project_company_support_issue' => ['issue_key', 'issue_status'],
    ];
    if (!isset($allowed[$table]) || $allowed[$table][0] !== $keyColumn) {
        throw new LogicException('Support ownership validation target is not registered.');
    }
    $row = bx_db()->GetRow("SELECT * FROM {$table} WHERE {$keyColumn} = ? LIMIT 1", [$recordKey]);
    if (!is_array($row) || $row === []) {
        throw new InvalidArgumentException($label . ' does not belong to this company.');
    }
    if (!hash_equals($companyHash, strtolower((string) ($row['company_key_hash'] ?? '')))) {
        throw new InvalidArgumentException($label . ' belongs to another company.');
    }
    if ($activeOnly && (string) ($row[$allowed[$table][1]] ?? '') !== 'ACTIVE') {
        throw new InvalidArgumentException($label . ' is inactive.');
    }

    return $row;
}

function yovel_admin_support_issue_priorities(array $company, array $admin): array
{
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $rows = bx_db()->GetAll(
        'SELECT * FROM project_company_support_issue_priority
         WHERE company_key_hash = ? ORDER BY priority_name, issue_priority_key LIMIT 300',
        [$companyHash]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_support_issue_priority(
    array $company,
    array $admin,
    string $priorityKey
): array {
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $priorityKey = yovel_admin_support_service_optional_uuid(
        ['issue_priority_key' => $priorityKey],
        'issue_priority_key',
        'Issue Priority'
    );

    return yovel_admin_support_service_assert_owned_key(
        'project_company_support_issue_priority',
        'issue_priority_key',
        $priorityKey,
        $companyHash,
        'Issue Priority'
    );
}

function yovel_admin_support_issue_types(array $company, array $admin): array
{
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $rows = bx_db()->GetAll(
        'SELECT * FROM project_company_support_issue_type
         WHERE company_key_hash = ? ORDER BY issue_type_name, issue_type_key LIMIT 300',
        [$companyHash]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_support_issue_type(array $company, array $admin, string $issueTypeKey): array
{
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $issueTypeKey = yovel_admin_support_service_optional_uuid(
        ['issue_type_key' => $issueTypeKey],
        'issue_type_key',
        'Issue Type'
    );

    return yovel_admin_support_service_assert_owned_key(
        'project_company_support_issue_type',
        'issue_type_key',
        $issueTypeKey,
        $companyHash,
        'Issue Type'
    );
}

function yovel_admin_save_support_issue_master(
    array $company,
    array $admin,
    array $input,
    string $kind,
    ?callable $failureInjector = null
): array {
    yovel_admin_support_service_assert_no_secrets($input);
    yovel_admin_support_service_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_support_service_scope($company, $admin);
    $definitions = [
        'priority' => [
            'table' => 'project_company_support_issue_priority',
            'key' => 'issue_priority_key',
            'version' => 'priority_version',
            'name' => 'priority_name',
            'description' => 'priority_description',
            'status' => 'priority_status',
            'label' => 'Issue Priority',
        ],
        'type' => [
            'table' => 'project_company_support_issue_type',
            'key' => 'issue_type_key',
            'version' => 'issue_type_version',
            'name' => 'issue_type_name',
            'description' => 'issue_type_description',
            'status' => 'issue_type_status',
            'label' => 'Issue Type',
        ],
    ];
    if (!isset($definitions[$kind])) {
        throw new LogicException('Support Issue master type is not registered.');
    }
    $definition = $definitions[$kind];
    $stableKey = yovel_admin_support_service_optional_uuid($input, $definition['key'], $definition['label']);
    $expectedVersion = yovel_admin_support_service_required_version($input);
    $name = yovel_admin_support_service_text($input, $definition['name'], 120, true);
    $description = yovel_admin_support_service_text($input, $definition['description'], 500);
    $status = yovel_admin_support_service_status($input, $definition['status'], $definition['label']);
    if ($stableKey !== '') {
        yovel_admin_support_service_assert_owned_key(
            $definition['table'],
            $definition['key'],
            $stableKey,
            $companyHash,
            $definition['label']
        );
    }

    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException($definition['label'] . ' transaction could not start.');
    }
    try {
        yovel_admin_support_service_lock_company($db, $companyKey, $companyHash);
        $current = $stableKey !== ''
            ? $db->GetRow(
                "SELECT * FROM {$definition['table']} WHERE company_key_hash = ? AND {$definition['key']} = ? FOR UPDATE",
                [$companyHash, $stableKey]
            )
            : $db->GetRow(
                "SELECT * FROM {$definition['table']} WHERE company_key_hash = ? AND {$definition['name']} = ? FOR UPDATE",
                [$companyHash, $name]
            );
        $current = is_array($current) && $current !== [] ? $current : null;
        if ($stableKey !== '' && $current === null) {
            throw new InvalidArgumentException($definition['label'] . ' does not belong to this company.');
        }
        $sameName = $db->GetRow(
            "SELECT {$definition['key']} FROM {$definition['table']} WHERE company_key_hash = ? AND {$definition['name']} = ? FOR UPDATE",
            [$companyHash, $name]
        );
        if (is_array($sameName) && $sameName !== [] && $current !== null
            && (string) $sameName[$definition['key']] !== (string) $current[$definition['key']]) {
            throw new InvalidArgumentException($definition['label'] . ' name belongs to another record.');
        }
        $currentVersion = (int) ($current[$definition['version']] ?? 0);
        if ($currentVersion !== $expectedVersion) {
            throw new RuntimeException($definition['label'] . ' changed since this form was opened. Reload and try again.');
        }
        $recordKey = $current !== null ? (string) $current[$definition['key']] : bx_uuid();
        $nextVersion = $currentVersion + 1;
        yovel_admin_db_execute(
            $db,
            "INSERT INTO {$definition['table']} (
                {$definition['key']}, company_key, company_key_hash, {$definition['version']},
                {$definition['name']}, {$definition['description']}, {$definition['status']},
                created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                {$definition['version']} = VALUES({$definition['version']}),
                {$definition['name']} = VALUES({$definition['name']}),
                {$definition['description']} = VALUES({$definition['description']}),
                {$definition['status']} = VALUES({$definition['status']}),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $recordKey, $companyKey, $companyHash, $nextVersion, $name,
                $description !== '' ? $description : null, $status, $adminKey, $adminKey,
            ],
            $definition['label'] . ' save'
        );
        $saved = $db->GetRow(
            "SELECT * FROM {$definition['table']} WHERE company_key_hash = ? AND {$definition['key']} = ? LIMIT 1",
            [$companyHash, $recordKey]
        );
        yovel_admin_support_service_verify_read_back($saved, [
            $definition['key'] => $recordKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            $definition['version'] => $nextVersion,
            $definition['name'] => $name,
            $definition['description'] => $description,
            $definition['status'] => $status,
            'created_by_admin_key' => (string) ($current['created_by_admin_key'] ?? $adminKey),
            'updated_by_admin_key' => $adminKey,
        ], $definition['label']);
        bx_audit($current === null ? 'CREATE' : 'UPDATE', $definition['table'], $recordKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            $definition['version'] => $nextVersion,
            $definition['name'] => $name,
            $definition['status'] => $status,
            'admin_key' => $adminKey,
        ], 'Company administrator changed a Support / Service Issue master.');
        if ($failureInjector !== null) {
            $failureInjector();
        }
        if ($db->CommitTrans() === false) {
            throw new RuntimeException($definition['label'] . ' transaction could not commit.');
        }

        return is_array($saved) ? $saved : [];
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_save_support_issue_priority(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    return yovel_admin_save_support_issue_master($company, $admin, $input, 'priority', $failureInjector);
}

function yovel_admin_save_support_issue_type(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    return yovel_admin_save_support_issue_master($company, $admin, $input, 'type', $failureInjector);
}

function yovel_admin_support_service_assert_blank_owner_references(array $input, string $formId): void
{
    $contracts = [
        'customer_key' => 'sales-crm.customer-reference.v1',
        'contact_key' => 'sales-crm.contact-reference.v1',
        'project_key' => 'projects.service-work-reference.v1',
        'assigned_admin_key' => 'operations.support-assignment.v1',
        'communication_key' => 'operations.append-communication.v1',
        'portal_owner_key' => 'sales-crm.support-portal-owner.v1',
        'raised_by' => 'sales-crm.support-portal-owner.v1',
    ];
    foreach ($contracts as $field => $contract) {
        if (trim((string) ($input[$field] ?? '')) !== '') {
            throw new YovelAdminSupportUnavailableDependency($contract, $formId, $input);
        }
    }
    if (yovel_admin_support_service_input_bool($input, 'via_customer_portal')) {
        throw new YovelAdminSupportUnavailableDependency(
            'sales-crm.support-portal-owner.v1',
            $formId,
            $input
        );
    }
}

function yovel_admin_support_service_active_master(
    string $table,
    string $keyColumn,
    string $statusColumn,
    string $recordKey,
    string $companyHash,
    string $label
): void {
    if ($recordKey === '') {
        return;
    }
    $row = yovel_admin_support_service_assert_owned_key(
        $table,
        $keyColumn,
        $recordKey,
        $companyHash,
        $label
    );
    if ((string) ($row[$statusColumn] ?? '') !== 'ACTIVE') {
        throw new InvalidArgumentException($label . ' is inactive.');
    }
}

function yovel_admin_support_service_issue_checksum(): string
{
    $schema = yovel_admin_support_service_default_form_schemas()['issue'];
    $normalized = yovel_admin_support_service_normalize_form_schema('issue', $schema);

    return yovel_admin_support_service_form_checksum($normalized);
}

function yovel_admin_support_issues(array $company, array $admin): array
{
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $rows = bx_db()->GetAll(
        'SELECT issue.*, priority.priority_name, issue_type.issue_type_name
         FROM project_company_support_issue issue
         LEFT JOIN project_company_support_issue_priority priority
           ON priority.company_key_hash = issue.company_key_hash
          AND priority.issue_priority_key = issue.issue_priority_key
         LEFT JOIN project_company_support_issue_type issue_type
           ON issue_type.company_key_hash = issue.company_key_hash
          AND issue_type.issue_type_key = issue.issue_type_key
         WHERE issue.company_key_hash = ?
         ORDER BY issue.opened_at DESC, issue.issue_key LIMIT 500',
        [$companyHash]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_support_issue(array $company, array $admin, string $issueKey): array
{
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $issueKey = yovel_admin_support_service_optional_uuid(['issue_key' => $issueKey], 'issue_key', 'Issue');

    return yovel_admin_support_service_assert_owned_key(
        'project_company_support_issue',
        'issue_key',
        $issueKey,
        $companyHash,
        'Issue'
    );
}

function yovel_admin_support_issue_events(
    array $company,
    array $admin,
    string $issueKey
): array {
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $issueKey = yovel_admin_support_service_optional_uuid(['issue_key' => $issueKey], 'issue_key', 'Issue');
    yovel_admin_support_service_assert_owned_key(
        'project_company_support_issue',
        'issue_key',
        $issueKey,
        $companyHash,
        'Issue'
    );
    $rows = bx_db()->GetAll(
        'SELECT * FROM project_company_support_issue_event
         WHERE company_key_hash = ? AND issue_key = ?
         ORDER BY occurred_at, x_id',
        [$companyHash, $issueKey]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_support_service_write_issue_event(
    ADOConnection $db,
    string $companyKey,
    string $companyHash,
    string $adminKey,
    string $issueKey,
    string $eventType,
    ?string $previousStatus,
    ?string $nextStatus,
    array $payload,
    ?DateTimeImmutable $occurredAt = null
): void {
    $occurredAt = $occurredAt !== null
        ? yovel_admin_support_sla_utc($occurredAt)->format('Y-m-d H:i:s')
        : date('Y-m-d H:i:s');
    yovel_admin_db_execute(
        $db,
        'INSERT INTO project_company_support_issue_event (
            issue_event_key, issue_key, company_key, company_key_hash, event_type,
            previous_status, next_status, event_payload, occurred_at, created_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            bx_uuid(), $issueKey, $companyKey, $companyHash, $eventType,
            $previousStatus, $nextStatus,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $occurredAt,
            $adminKey,
        ],
        'Support Issue event save'
    );
}

function yovel_admin_save_support_issue(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null,
    ?DateTimeImmutable $now = null
): array {
    yovel_admin_support_service_assert_no_secrets($input);
    yovel_admin_support_service_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_support_service_scope($company, $admin);
    $issueKey = yovel_admin_support_service_optional_uuid($input, 'issue_key', 'Issue');
    $expectedVersion = yovel_admin_support_service_required_version($input);
    $subject = yovel_admin_support_service_text($input, 'subject', 255, true);
    $description = yovel_admin_support_service_text($input, 'description', 20000);
    $priorityKey = yovel_admin_support_service_optional_uuid($input, 'issue_priority_key', 'Issue Priority');
    $issueTypeKey = yovel_admin_support_service_optional_uuid($input, 'issue_type_key', 'Issue Type');
    yovel_admin_support_service_assert_blank_owner_references($input, 'support-issue');
    yovel_admin_support_service_active_master(
        'project_company_support_issue_priority', 'issue_priority_key', 'priority_status',
        $priorityKey, $companyHash, 'Issue Priority'
    );
    yovel_admin_support_service_active_master(
        'project_company_support_issue_type', 'issue_type_key', 'issue_type_status',
        $issueTypeKey, $companyHash, 'Issue Type'
    );
    $preflight = $issueKey !== ''
        ? yovel_admin_support_service_assert_owned_key(
            'project_company_support_issue', 'issue_key', $issueKey, $companyHash, 'Issue'
        )
        : [];
    if ($preflight !== [] && (string) ($preflight['archived_at'] ?? '') !== '') {
        throw new RuntimeException('This Issue is archived and cannot be edited. Restore it first.');
    }
    $requestedStatus = strtoupper(trim((string) ($input['issue_status'] ?? ($preflight['issue_status'] ?? 'OPEN'))));
    if ($requestedStatus !== (string) ($preflight['issue_status'] ?? 'OPEN')) {
        throw new InvalidArgumentException('Issue status changes require the lifecycle action.');
    }
    $checksum = yovel_admin_support_service_issue_checksum();
    $submittedChecksum = trim((string) ($input['form_schema_checksum'] ?? ''));
    if ($submittedChecksum !== '' && !hash_equals($checksum, $submittedChecksum)) {
        throw new RuntimeException('Issue Form Builder schema changed since this form was opened. Reload and try again.');
    }
    $clock = yovel_admin_support_sla_now($now);

    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Support Issue transaction could not start.');
    }
    try {
        yovel_admin_support_service_lock_company($db, $companyKey, $companyHash);
        $current = $issueKey !== ''
            ? $db->GetRow(
                'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? FOR UPDATE',
                [$companyHash, $issueKey]
            )
            : null;
        $current = is_array($current) && $current !== [] ? $current : null;
        if ($issueKey !== '' && $current === null) {
            throw new InvalidArgumentException('Issue does not belong to this company.');
        }
        if ($current !== null && (string) ($current['archived_at'] ?? '') !== '') {
            throw new RuntimeException('This Issue is archived and cannot be edited. Restore it first.');
        }
        $currentVersion = (int) ($current['issue_version'] ?? 0);
        if ($currentVersion !== $expectedVersion) {
            throw new RuntimeException('Issue changed since this form was opened. Reload and try again.');
        }
        if ($current !== null) {
            yovel_admin_support_service_assert_blank_owner_references($current, 'support-issue');
        }
        $recordKey = $current !== null ? (string) $current['issue_key'] : bx_uuid();
        $issueCode = $current !== null
            ? (string) $current['issue_code']
            : 'ISS-' . $clock->format('Ymd') . '-' . strtoupper(substr(str_replace('-', '', bx_uuid()), 0, 8));
        $openedAt = $current !== null ? (string) $current['opened_at'] : $clock->format('Y-m-d H:i:s');
        $status = (string) ($current['issue_status'] ?? 'OPEN');
        $nextVersion = $currentVersion + 1;
        yovel_admin_db_execute(
            $db,
            'INSERT INTO project_company_support_issue (
                issue_key, company_key, company_key_hash, issue_code, issue_version,
                form_schema_checksum, subject, description, issue_status,
                issue_priority_key, issue_type_key, customer_key, customer_key_hash,
                project_key, project_key_hash, raised_by, assigned_admin_key,
                via_customer_portal, opened_at, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL, 0, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                issue_version = VALUES(issue_version),
                form_schema_checksum = VALUES(form_schema_checksum),
                subject = VALUES(subject), description = VALUES(description),
                issue_priority_key = VALUES(issue_priority_key), issue_type_key = VALUES(issue_type_key),
                updated_by_admin_key = VALUES(updated_by_admin_key)',
            [
                $recordKey, $companyKey, $companyHash, $issueCode, $nextVersion,
                $checksum, $subject, $description !== '' ? $description : null, $status,
                $priorityKey !== '' ? $priorityKey : null,
                $issueTypeKey !== '' ? $issueTypeKey : null,
                $openedAt, $adminKey, $adminKey,
            ],
            'Support Issue save'
        );
        if ($current === null) {
            yovel_admin_support_service_write_issue_event(
                $db, $companyKey, $companyHash, $adminKey, $recordKey,
                'CREATED', null, 'OPEN', ['subject' => $subject], $clock
            );
        }
        $saved = $db->GetRow(
            'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? LIMIT 1',
            [$companyHash, $recordKey]
        );
        yovel_admin_support_service_verify_read_back($saved, [
            'issue_key' => $recordKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'issue_code' => $issueCode,
            'issue_version' => $nextVersion,
            'form_schema_checksum' => $checksum,
            'subject' => $subject,
            'description' => $description,
            'issue_status' => $status,
            'issue_priority_key' => $priorityKey,
            'issue_type_key' => $issueTypeKey,
            'customer_key' => '',
            'project_key' => '',
            'opened_at' => $openedAt,
            'created_by_admin_key' => (string) ($current['created_by_admin_key'] ?? $adminKey),
            'updated_by_admin_key' => $adminKey,
        ], 'Support Issue');
        if ($current === null && function_exists('yovel_admin_support_sla_apply_created_in_transaction')) {
            $saved = yovel_admin_support_sla_apply_created_in_transaction(
                $db,
                $company,
                $admin,
                $companyKey,
                $companyHash,
                $adminKey,
                is_array($saved) ? $saved : [],
                $clock
            );
        }
        bx_audit($current === null ? 'CREATE' : 'UPDATE', 'project_company_support_issue', $recordKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'issue_code' => $issueCode,
            'issue_version' => $nextVersion,
            'issue_status' => $status,
            'admin_key' => $adminKey,
        ], 'Company administrator saved a dependency-safe Support Issue.');
        if ($failureInjector !== null) {
            $failureInjector();
        }
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Support Issue transaction could not commit.');
        }

        return is_array($saved) ? $saved : [];
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_transition_support_issue(
    array $company,
    array $admin,
    string $issueKey,
    string $nextStatus,
    array $input = [],
    ?callable $failureInjector = null,
    ?DateTimeImmutable $now = null
): array {
    yovel_admin_support_service_assert_no_secrets($input);
    yovel_admin_support_service_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_support_service_scope($company, $admin);
    $issueKey = yovel_admin_support_service_optional_uuid(['issue_key' => $issueKey], 'issue_key', 'Issue');
    $expectedVersion = yovel_admin_support_service_required_version($input);
    $reason = yovel_admin_support_service_text($input, 'reason', 1000, true);
    $nextStatus = strtoupper(trim($nextStatus));
    if (!in_array($nextStatus, ['OPEN', 'REPLIED', 'ON_HOLD', 'RESOLVED', 'CLOSED', 'ARCHIVED', 'RESTORED'], true)) {
        throw new InvalidArgumentException('Issue status is invalid.');
    }
    $preflight = yovel_admin_support_service_assert_owned_key(
        'project_company_support_issue', 'issue_key', $issueKey, $companyHash, 'Issue'
    );
    yovel_admin_support_service_assert_blank_owner_references($preflight, 'support-issue-transition');
    $clock = yovel_admin_support_sla_now($now);

    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Support Issue lifecycle transaction could not start.');
    }
    try {
        yovel_admin_support_service_lock_company($db, $companyKey, $companyHash);
        $current = $db->GetRow(
            'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? FOR UPDATE',
            [$companyHash, $issueKey]
        );
        if (!is_array($current) || $current === []) {
            throw new InvalidArgumentException('Issue does not belong to this company.');
        }
        if ((int) $current['issue_version'] !== $expectedVersion) {
            throw new RuntimeException('Issue changed since this form was opened. Reload and try again.');
        }
        $previousStatus = (string) $current['issue_status'];
        $archived = (string) ($current['archived_at'] ?? '') !== '';
        if ($nextStatus === 'ARCHIVED') {
            if ($archived) {
                throw new InvalidArgumentException('Issue is already archived.');
            }
            $storedStatus = $previousStatus;
            $archivedAt = $clock->format('Y-m-d H:i:s');
            $eventType = 'ARCHIVED';
        } elseif ($nextStatus === 'RESTORED') {
            if (!$archived) {
                throw new InvalidArgumentException('Issue is not archived.');
            }
            $storedStatus = $previousStatus;
            $archivedAt = null;
            $eventType = 'RESTORED';
        } else {
            if ($archived) {
                throw new RuntimeException('Archived Issues must be restored before changing status.');
            }
            $allowed = [
                'OPEN' => ['REPLIED', 'ON_HOLD', 'RESOLVED', 'CLOSED'],
                'REPLIED' => ['OPEN', 'ON_HOLD', 'RESOLVED', 'CLOSED'],
                'ON_HOLD' => ['OPEN', 'REPLIED', 'RESOLVED', 'CLOSED'],
                'RESOLVED' => ['OPEN', 'CLOSED'],
                'CLOSED' => ['OPEN'],
            ];
            if (!in_array($nextStatus, $allowed[$previousStatus] ?? [], true)) {
                throw new InvalidArgumentException('Issue status transition is not allowed.');
            }
            $storedStatus = $nextStatus;
            $archivedAt = null;
            $eventType = $nextStatus;
        }
        $onHoldSince = $storedStatus === 'ON_HOLD'
            ? $clock->format('Y-m-d H:i:s')
            : null;
        $resolutionDate = in_array($storedStatus, ['RESOLVED', 'CLOSED'], true)
            ? ((string) ($current['resolution_date'] ?? '') !== ''
                ? (string) $current['resolution_date']
                : $clock->format('Y-m-d H:i:s'))
            : null;
        $nextVersion = (int) $current['issue_version'] + 1;
        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_support_issue
             SET issue_version = ?, issue_status = ?, on_hold_since = ?, resolution_date = ?,
                 archived_at = ?, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND issue_key = ?',
            [
                $nextVersion, $storedStatus, $onHoldSince, $resolutionDate,
                $archivedAt, $adminKey, $companyHash, $issueKey,
            ],
            'Support Issue lifecycle update'
        );
        yovel_admin_support_service_write_issue_event(
            $db, $companyKey, $companyHash, $adminKey, $issueKey, $eventType,
            $previousStatus, $nextStatus, ['reason' => $reason, 'issue_version' => $nextVersion], $clock
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? LIMIT 1',
            [$companyHash, $issueKey]
        );
        yovel_admin_support_service_verify_read_back($saved, [
            'issue_key' => $issueKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'issue_version' => $nextVersion,
            'issue_status' => $storedStatus,
            'on_hold_since' => $onHoldSince,
            'resolution_date' => $resolutionDate,
            'archived_at' => $archivedAt,
            'updated_by_admin_key' => $adminKey,
        ], 'Support Issue lifecycle');
        if ($nextStatus !== 'ARCHIVED'
            && $nextStatus !== 'RESTORED'
            && (string) ($current['service_level_agreement_key'] ?? '') !== ''
            && function_exists('yovel_admin_support_sla_apply_status_in_transaction')) {
            $saved = yovel_admin_support_sla_apply_status_in_transaction(
                $db,
                $company,
                $companyKey,
                $companyHash,
                $adminKey,
                $current,
                $storedStatus,
                $clock
            );
        }
        bx_audit($eventType, 'project_company_support_issue', $issueKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'issue_version' => $nextVersion,
            'previous_status' => $previousStatus,
            'next_status' => $nextStatus,
            'admin_key' => $adminKey,
        ], $reason);
        if ($failureInjector !== null) {
            $failureInjector();
        }
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Support Issue lifecycle transaction could not commit.');
        }

        return is_array($saved) ? $saved : [];
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_support_issue_record_communication(
    array $company,
    array $admin,
    string $issueKey,
    array $input
): array {
    yovel_admin_support_service_scope($company, $admin);
    throw new YovelAdminSupportUnavailableDependency(
        'operations.append-communication.v1',
        'support-issue-communication',
        $input + ['issue_key' => $issueKey]
    );
}

function yovel_admin_support_issue_split(
    array $company,
    array $admin,
    string $issueKey,
    array $input
): array {
    yovel_admin_support_service_scope($company, $admin);
    throw new YovelAdminSupportUnavailableDependency(
        'operations.support-communication-split.v1',
        'support-issue-split',
        $input + ['issue_key' => $issueKey]
    );
}

function yovel_admin_support_portal_issues(array $company, array $portalOwner, array $filters = []): array
{
    throw new YovelAdminSupportUnavailableDependency(
        'sales-crm.support-portal-owner.v1',
        'support-portal-issues',
        $filters + $portalOwner
    );
}

function yovel_admin_support_portal_issue(array $company, array $portalOwner, string $issueKey): array
{
    throw new YovelAdminSupportUnavailableDependency(
        'sales-crm.support-portal-owner.v1',
        'support-portal-issue',
        $portalOwner + ['issue_key' => $issueKey]
    );
}
