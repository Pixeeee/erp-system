<?php
declare(strict_types=1);

function yovel_admin_sales_campaign_decimal(mixed $value, string $label): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '0.00';
    }
    if (!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/', $value, $matches)) {
        throw new InvalidArgumentException($label . ' must be a non-negative amount with no more than two decimal places.');
    }

    return $matches[1] . '.' . str_pad((string) ($matches[2] ?? ''), 2, '0');
}

function yovel_admin_sales_campaign_date(mixed $value, string $label): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($label . ' must be a valid date.');
    }

    return $value;
}

function yovel_admin_sales_campaign_datetime(mixed $value): string
{
    $value = str_replace('T', ' ', trim((string) $value));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
        $value .= ':00';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
        throw new InvalidArgumentException('Scheduled time must use YYYY-MM-DD HH:MM:SS.');
    }

    return $value;
}

function yovel_admin_sales_campaign_decimal_readback(mixed $value): string
{
    $value = trim((string) $value);
    if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?$/', $value, $matches)) {
        throw new RuntimeException('Campaign decimal read-back is invalid.');
    }
    $whole = ltrim($matches[2], '0');
    $whole = $whole === '' ? '0' : $whole;
    return $matches[1] . $whole . '.' . str_pad(substr((string) ($matches[3] ?? ''), 0, 2), 2, '0');
}

function yovel_admin_sales_campaign_attribution_sources(): array
{
    return [
        'leads' => [
            'table' => 'project_company_sales_lead',
            'record_key' => 'lead_key',
            'join_key' => 'campaign_key',
            'status_column' => 'lead_status',
            'value_column' => 'estimated_value',
        ],
        'opportunities' => [
            'table' => 'project_company_sales_opportunity',
            'record_key' => 'opportunity_key',
            'join_key' => 'campaign_key',
            'status_column' => 'opportunity_status',
            'value_column' => 'estimated_value',
        ],
        'quotations' => [
            'table' => 'project_company_sales_quotation',
            'record_key' => 'quotation_key',
            'join_key' => 'campaign_key',
            'status_column' => 'quotation_status',
            'value_column' => 'grand_total',
        ],
        'sales_orders' => [
            'table' => 'project_company_sales_order',
            'record_key' => 'sales_order_key',
            'join_key' => 'campaign_key',
            'status_column' => 'sales_order_status',
            'value_column' => 'grand_total',
        ],
    ];
}

function yovel_admin_sales_campaign_source_available(array $source): bool
{
    $required = ['company_key_hash', (string) $source['record_key'], (string) $source['join_key'], (string) $source['status_column'], (string) $source['value_column']];
    $placeholders = implode(',', array_fill(0, count($required), '?'));
    $params = array_merge([BUILDERX_DB_NAME, (string) $source['table']], $required);
    return (int) bx_db()->GetOne(
        "SELECT COUNT(DISTINCT COLUMN_NAME) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN ({$placeholders})",
        $params
    ) === count($required);
}

function yovel_admin_sales_campaign_row(array $company, string $campaignKey, bool $withSchedules = true): array
{
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_key = ? LIMIT 1',
        [(string) $company['company_key_hash'], $campaignKey]
    );
    if (!is_array($row) || $row === []) {
        throw new InvalidArgumentException('Campaign was not found for this company.');
    }
    $row['campaign_version'] = (int) $row['campaign_version'];
    $row['budget'] = yovel_admin_sales_campaign_decimal_readback($row['budget']);
    $row['expected_revenue'] = yovel_admin_sales_campaign_decimal_readback($row['expected_revenue']);
    $row['email_schedules'] = $withSchedules ? yovel_admin_sales_campaign_schedules($company, $campaignKey) : [];

    return $row;
}

function yovel_admin_sales_campaign_schedules(array $company, string $campaignKey): array
{
    $rows = bx_db()->GetAll(
        'SELECT campaign_email_schedule_key, company_key, company_key_hash, campaign_key, schedule_code,
                subject, recipient_segment, scheduled_at, send_status, created_by_admin_key,
                updated_by_admin_key, created_at, updated_at
         FROM project_company_sales_campaign_email_schedule
         WHERE company_key_hash = ? AND campaign_key = ?
         ORDER BY scheduled_at, schedule_code',
        [(string) $company['company_key_hash'], $campaignKey]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_sales_campaign_validate_schedule(array $campaign, array $input): array
{
    $scheduleKey = trim((string) ($input['campaign_email_schedule_key'] ?? ''));
    $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
    if ($scheduleKey !== '' && !yovel_admin_is_uuid($scheduleKey)) {
        throw new InvalidArgumentException('Invalid Campaign Email Schedule key.');
    }
    if ($idempotencyKey !== '' && !yovel_admin_is_uuid($idempotencyKey)) {
        throw new InvalidArgumentException('Invalid Campaign Email Schedule idempotency key.');
    }
    $scheduleCode = yovel_admin_code((string) ($input['schedule_code'] ?? ''));
    $subject = trim((string) ($input['subject'] ?? ''));
    $recipientSegment = trim((string) ($input['recipient_segment'] ?? ''));
    $scheduledAt = yovel_admin_sales_campaign_datetime($input['scheduled_at'] ?? '');
    $sendStatus = yovel_admin_status((string) ($input['send_status'] ?? 'SCHEDULED'), ['DRAFT', 'SCHEDULED', 'SENT', 'CANCELLED'], '');
    if ($scheduleCode === '' || strlen($scheduleCode) > 80) {
        throw new InvalidArgumentException('Schedule code is required and must not exceed 80 characters.');
    }
    if ($subject === '' || strlen($subject) > 255) {
        throw new InvalidArgumentException('Email subject is required and must not exceed 255 characters.');
    }
    if (strlen($recipientSegment) > 180 || $sendStatus === '') {
        throw new InvalidArgumentException('Campaign Email Schedule values are invalid.');
    }
    $scheduledDate = substr($scheduledAt, 0, 10);
    if ((string) ($campaign['start_date'] ?? '') !== '' && $scheduledDate < (string) $campaign['start_date']) {
        throw new InvalidArgumentException('Scheduled time must not be before the Campaign start date.');
    }
    if ((string) ($campaign['end_date'] ?? '') !== '' && $scheduledDate > (string) $campaign['end_date']) {
        throw new InvalidArgumentException('Scheduled time must not be after the Campaign end date.');
    }

    return [
        'campaign_email_schedule_key' => $scheduleKey,
        'idempotency_key' => $idempotencyKey,
        'schedule_code' => $scheduleCode,
        'subject' => $subject,
        'recipient_segment' => $recipientSegment,
        'scheduled_at' => $scheduledAt,
        'send_status' => $sendStatus,
    ];
}

function yovel_admin_sales_campaign_schedule_write(
    ADOConnection $db,
    array $company,
    array $admin,
    array $campaign,
    array $input
): array {
    if ((string) $campaign['campaign_status'] === 'COMPLETED') {
        throw new RuntimeException('A completed Campaign is immutable.');
    }
    $values = yovel_admin_sales_campaign_validate_schedule($campaign, $input);
    $companyKeyHash = (string) $company['company_key_hash'];
    $scheduleKey = (string) $values['campaign_email_schedule_key'];
    $existing = null;
    if ($scheduleKey === '' && (string) $values['idempotency_key'] !== '') {
        $replay = $db->GetRow(
            'SELECT * FROM project_company_sales_campaign_email_schedule
             WHERE company_key_hash = ? AND idempotency_key = ? FOR UPDATE',
            [$companyKeyHash, (string) $values['idempotency_key']]
        );
        if (is_array($replay) && $replay !== []) {
            if (!hash_equals((string) $campaign['campaign_key'], (string) $replay['campaign_key'])) {
                throw new InvalidArgumentException('Campaign Email Schedule idempotency key belongs to another Campaign.');
            }
            return $replay;
        }
    }
    if ($scheduleKey !== '') {
        $existing = $db->GetRow(
            'SELECT * FROM project_company_sales_campaign_email_schedule
             WHERE company_key_hash = ? AND campaign_key = ? AND campaign_email_schedule_key = ? FOR UPDATE',
            [$companyKeyHash, (string) $campaign['campaign_key'], $scheduleKey]
        );
        if (!is_array($existing) || $existing === []) {
            throw new InvalidArgumentException('Campaign Email Schedule was not found for this Campaign.');
        }
        if ((string) $existing['send_status'] === 'SENT') {
            throw new RuntimeException('A sent Campaign Email Schedule is immutable.');
        }
        if ((string) $values['idempotency_key'] === '') {
            $values['idempotency_key'] = (string) ($existing['idempotency_key'] ?? '');
        }
    } else {
        $duplicate = $db->GetRow(
            'SELECT * FROM project_company_sales_campaign_email_schedule
             WHERE company_key_hash = ? AND campaign_key = ? AND schedule_code = ? FOR UPDATE',
            [$companyKeyHash, (string) $campaign['campaign_key'], (string) $values['schedule_code']]
        );
        if (is_array($duplicate) && $duplicate !== []) {
            throw new InvalidArgumentException('Schedule code already belongs to this Campaign.');
        }
        $scheduleKey = bx_uuid();
    }
    yovel_admin_db_execute(
        $db,
        'INSERT INTO project_company_sales_campaign_email_schedule (
            campaign_email_schedule_key, company_key, company_key_hash, campaign_key, idempotency_key,
            schedule_code, subject, recipient_segment, scheduled_at, send_status, created_by_admin_key, updated_by_admin_key
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE schedule_code = VALUES(schedule_code), subject = VALUES(subject),
            recipient_segment = VALUES(recipient_segment), scheduled_at = VALUES(scheduled_at),
            send_status = VALUES(send_status), updated_by_admin_key = VALUES(updated_by_admin_key)',
        [
            $scheduleKey, (string) $company['company_key'], $companyKeyHash, (string) $campaign['campaign_key'], $values['idempotency_key'] !== '' ? $values['idempotency_key'] : null,
            $values['schedule_code'], $values['subject'], $values['recipient_segment'], $values['scheduled_at'],
            $values['send_status'], (string) $admin['admin_key'], (string) $admin['admin_key'],
        ],
        'Sales/CRM Campaign Email Schedule save'
    );
    $saved = $db->GetRow(
        'SELECT campaign_email_schedule_key, company_key, company_key_hash, campaign_key, COALESCE(idempotency_key, \'\') AS idempotency_key, schedule_code,
                subject, recipient_segment, scheduled_at, send_status
         FROM project_company_sales_campaign_email_schedule
         WHERE company_key_hash = ? AND campaign_key = ? AND campaign_email_schedule_key = ? LIMIT 1',
        [$companyKeyHash, (string) $campaign['campaign_key'], $scheduleKey]
    );
    foreach (array_merge($values, [
        'campaign_email_schedule_key' => $scheduleKey,
        'company_key' => (string) $company['company_key'],
        'company_key_hash' => $companyKeyHash,
        'campaign_key' => (string) $campaign['campaign_key'],
    ]) as $column => $expected) {
        if (!is_array($saved) || (string) ($saved[$column] ?? '') !== (string) $expected) {
            throw new RuntimeException('Campaign Email Schedule read-back verification failed for ' . $column . '.');
        }
    }
    bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_sales_campaign_email_schedule', $scheduleKey, [
        'company_key' => (string) $company['company_key'],
        'company_name' => (string) $company['company_name'],
        'campaign_key' => (string) $campaign['campaign_key'],
        'schedule_code' => (string) $values['schedule_code'],
        'send_status' => (string) $values['send_status'],
        'admin_key' => (string) $admin['admin_key'],
    ], $existing ? 'Company administrator updated a Campaign email schedule.' : 'Company administrator created a Campaign email schedule.');

    return $saved;
}

function yovel_admin_sales_campaign_save(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $db = bx_db();
    $companyKey = (string) $company['company_key'];
    $companyKeyHash = (string) $company['company_key_hash'];
    $adminKey = (string) $admin['admin_key'];
    $campaignKey = trim((string) ($input['campaign_key'] ?? ''));
    $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
    if ($campaignKey !== '' && !yovel_admin_is_uuid($campaignKey)) {
        throw new InvalidArgumentException('Invalid Campaign key.');
    }
    if ($idempotencyKey !== '' && !yovel_admin_is_uuid($idempotencyKey)) {
        throw new InvalidArgumentException('Invalid Campaign idempotency key.');
    }

    $db->BeginTrans();
    try {
        if ($campaignKey === '' && $idempotencyKey !== '') {
            $replay = $db->GetRow(
                'SELECT campaign_key FROM project_company_sales_campaign
                 WHERE company_key_hash = ? AND idempotency_key = ? FOR UPDATE',
                [$companyKeyHash, $idempotencyKey]
            );
            if (is_array($replay) && $replay !== []) {
                $db->CommitTrans();
                return yovel_admin_sales_campaign_row($company, (string) $replay['campaign_key']);
            }
        }

        $existing = null;
        if ($campaignKey !== '') {
            $existing = $db->GetRow(
                'SELECT * FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_key = ? FOR UPDATE',
                [$companyKeyHash, $campaignKey]
            );
            if (!is_array($existing) || $existing === []) {
                throw new InvalidArgumentException('Campaign was not found for this company.');
            }
            if ((string) $existing['campaign_status'] === 'COMPLETED') {
                throw new RuntimeException('A completed Campaign is immutable.');
            }
            $expectedVersion = filter_var($input['expected_version'] ?? null, FILTER_VALIDATE_INT);
            if ($expectedVersion === false || $expectedVersion !== (int) $existing['campaign_version']) {
                throw new RuntimeException('Campaign changed since this form was opened. Refresh and try again.');
            }
        }

        $campaignCode = yovel_admin_code((string) ($input['campaign_code'] ?? ''));
        $campaignName = trim((string) ($input['campaign_name'] ?? ''));
        $campaignType = trim((string) ($input['campaign_type'] ?? ''));
        $requestedStatus = yovel_admin_status((string) ($input['campaign_status'] ?? 'DRAFT'), ['DRAFT', 'ACTIVE', 'COMPLETED', 'INACTIVE'], '');
        $campaignStatus = $existing ? (string) $existing['campaign_status'] : 'DRAFT';
        $startDate = yovel_admin_sales_campaign_date($input['start_date'] ?? '', 'Start date');
        $endDate = yovel_admin_sales_campaign_date($input['end_date'] ?? '', 'End date');
        $budget = yovel_admin_sales_campaign_decimal($input['budget'] ?? '', 'Budget');
        $expectedRevenue = yovel_admin_sales_campaign_decimal($input['expected_revenue'] ?? '', 'Expected revenue');
        $notes = trim((string) ($input['notes'] ?? ''));
        if ($campaignCode === '' || strlen($campaignCode) > 80) {
            throw new InvalidArgumentException('Campaign code is required and must not exceed 80 characters.');
        }
        if ($campaignName === '' || strlen($campaignName) > 180) {
            throw new InvalidArgumentException('Campaign name is required and must not exceed 180 characters.');
        }
        if (strlen($campaignType) > 120 || strlen($notes) > 10000) {
            throw new InvalidArgumentException('Campaign text exceeds the allowed length.');
        }
        if ($requestedStatus === '') {
            throw new InvalidArgumentException('Campaign status is invalid.');
        }
        if ($existing && $requestedStatus !== $campaignStatus) {
            throw new InvalidArgumentException('Use the Campaign lifecycle action to change status.');
        }
        if (!$existing && $requestedStatus !== 'DRAFT') {
            throw new InvalidArgumentException('A new Campaign must begin in DRAFT.');
        }
        if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) {
            throw new InvalidArgumentException('End date must be on or after the start date.');
        }
        $duplicate = (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_sales_campaign
             WHERE company_key_hash = ? AND campaign_code = ? AND campaign_key <> ?',
            [$companyKeyHash, $campaignCode, $campaignKey]
        );
        if ($duplicate > 0) {
            throw new InvalidArgumentException('Campaign code already belongs to another Campaign.');
        }
        if ($campaignKey === '') {
            $campaignKey = bx_uuid();
        }
        $nextVersion = $existing ? (int) $existing['campaign_version'] + 1 : 1;
        yovel_admin_db_execute(
            $db,
            'INSERT INTO project_company_sales_campaign (
                campaign_key, company_key, company_key_hash, campaign_code, campaign_name, campaign_status,
                campaign_version, campaign_type, start_date, end_date, budget, expected_revenue, notes,
                idempotency_key, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE campaign_code = VALUES(campaign_code), campaign_name = VALUES(campaign_name),
                campaign_status = VALUES(campaign_status), campaign_version = VALUES(campaign_version),
                campaign_type = VALUES(campaign_type), start_date = VALUES(start_date), end_date = VALUES(end_date),
                budget = VALUES(budget), expected_revenue = VALUES(expected_revenue), notes = VALUES(notes),
                updated_by_admin_key = VALUES(updated_by_admin_key)',
            [
                $campaignKey, $companyKey, $companyKeyHash, $campaignCode, $campaignName, $campaignStatus,
                $nextVersion, $campaignType, $startDate !== '' ? $startDate : null, $endDate !== '' ? $endDate : null,
                $budget, $expectedRevenue, $notes, $idempotencyKey !== '' ? $idempotencyKey : null, $adminKey, $adminKey,
            ],
            'Sales/CRM Campaign save'
        );
        $campaign = $db->GetRow(
            'SELECT * FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_key = ? LIMIT 1',
            [$companyKeyHash, $campaignKey]
        );
        $expectedHeader = [
            'campaign_key' => $campaignKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'campaign_code' => $campaignCode,
            'campaign_name' => $campaignName,
            'campaign_status' => $campaignStatus,
            'campaign_version' => (string) $nextVersion,
            'campaign_type' => $campaignType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'budget' => $budget,
            'expected_revenue' => $expectedRevenue,
            'notes' => $notes,
        ];
        foreach ($expectedHeader as $column => $expected) {
            if (!is_array($campaign) || (string) ($campaign[$column] ?? '') !== $expected) {
                throw new RuntimeException('Campaign read-back verification failed for ' . $column . '.');
            }
        }
        foreach (is_array($input['email_schedules'] ?? null) ? $input['email_schedules'] : [] as $scheduleInput) {
            if (!is_array($scheduleInput)) {
                throw new InvalidArgumentException('Campaign Email Schedule rows must be structured records.');
            }
            if (trim((string) ($scheduleInput['campaign_email_schedule_key'] ?? '')) === ''
                && trim((string) ($scheduleInput['schedule_code'] ?? '')) === ''
                && trim((string) ($scheduleInput['subject'] ?? '')) === ''
                && trim((string) ($scheduleInput['scheduled_at'] ?? '')) === '') {
                continue;
            }
            yovel_admin_sales_campaign_schedule_write($db, $company, $admin, $campaign, $scheduleInput);
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_sales_campaign', $campaignKey, [
            'company_key' => $companyKey,
            'company_name' => (string) $company['company_name'],
            'campaign_code' => $campaignCode,
            'campaign_name' => $campaignName,
            'campaign_status' => $campaignStatus,
            'campaign_version' => $nextVersion,
            'admin_key' => $adminKey,
        ], $existing ? 'Company administrator updated a Campaign.' : 'Company administrator created a Campaign.');
        if ($failureInjector !== null) {
            $failureInjector();
        }
        $saved = yovel_admin_sales_campaign_row($company, $campaignKey);
        if (count($saved['email_schedules']) !== count(yovel_admin_sales_campaign_schedules($company, $campaignKey))) {
            throw new RuntimeException('Campaign child-row read-back verification failed.');
        }
        $db->CommitTrans();
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_campaign_schedule_save(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $campaignKey = trim((string) ($input['campaign_key'] ?? ''));
    if (!yovel_admin_is_uuid($campaignKey)) {
        throw new InvalidArgumentException('Invalid Campaign key.');
    }
    $db = bx_db();
    $db->BeginTrans();
    try {
        $campaign = $db->GetRow(
            'SELECT * FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_key = ? FOR UPDATE',
            [(string) $company['company_key_hash'], $campaignKey]
        );
        if (!is_array($campaign) || $campaign === []) {
            throw new InvalidArgumentException('Campaign was not found for this company.');
        }
        $saved = yovel_admin_sales_campaign_schedule_write($db, $company, $admin, $campaign, $input);
        if ($failureInjector !== null) {
            $failureInjector();
        }
        $db->CommitTrans();
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_campaign_transition(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    $campaignKey = trim((string) ($input['campaign_key'] ?? ''));
    $target = yovel_admin_status((string) ($input['campaign_status'] ?? ''), ['DRAFT', 'ACTIVE', 'COMPLETED', 'INACTIVE'], '');
    if (!yovel_admin_is_uuid($campaignKey) || $target === '') {
        throw new InvalidArgumentException('Invalid Campaign lifecycle request.');
    }
    $db = bx_db();
    $db->BeginTrans();
    try {
        $campaign = $db->GetRow(
            'SELECT * FROM project_company_sales_campaign WHERE company_key_hash = ? AND campaign_key = ? FOR UPDATE',
            [(string) $company['company_key_hash'], $campaignKey]
        );
        if (!is_array($campaign) || $campaign === []) {
            throw new InvalidArgumentException('Campaign was not found for this company.');
        }
        $current = (string) $campaign['campaign_status'];
        if ($current === $target) {
            $db->CommitTrans();
            return yovel_admin_sales_campaign_row($company, $campaignKey);
        }
        if ($current === 'COMPLETED') {
            throw new RuntimeException('A completed Campaign is immutable.');
        }
        $transitions = [
            'DRAFT' => ['ACTIVE', 'INACTIVE'],
            'ACTIVE' => ['COMPLETED', 'INACTIVE'],
            'INACTIVE' => ['ACTIVE'],
        ];
        if (!in_array($target, $transitions[$current] ?? [], true)) {
            throw new InvalidArgumentException('The requested Campaign lifecycle transition is not allowed.');
        }
        $nextVersion = (int) $campaign['campaign_version'] + 1;
        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_sales_campaign
             SET campaign_status = ?, campaign_version = ?, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND campaign_key = ?',
            [$target, $nextVersion, (string) $admin['admin_key'], (string) $company['company_key_hash'], $campaignKey],
            'Sales/CRM Campaign lifecycle transition'
        );
        $saved = $db->GetRow(
            'SELECT campaign_status, campaign_version FROM project_company_sales_campaign
             WHERE company_key_hash = ? AND campaign_key = ? LIMIT 1',
            [(string) $company['company_key_hash'], $campaignKey]
        );
        if (!is_array($saved) || (string) $saved['campaign_status'] !== $target || (int) $saved['campaign_version'] !== $nextVersion) {
            throw new RuntimeException('Campaign lifecycle read-back verification failed.');
        }
        bx_audit('STATUS', 'project_company_sales_campaign', $campaignKey, [
            'company_key' => (string) $company['company_key'],
            'company_name' => (string) $company['company_name'],
            'campaign_code' => (string) $campaign['campaign_code'],
            'previous_status' => $current,
            'campaign_status' => $target,
            'campaign_version' => $nextVersion,
            'admin_key' => (string) $admin['admin_key'],
        ], 'Company administrator changed the Campaign lifecycle state.');
        if ($failureInjector !== null) {
            $failureInjector();
        }
        $result = yovel_admin_sales_campaign_row($company, $campaignKey);
        $db->CommitTrans();
        return $result;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_sales_campaign_money_to_cents(string $amount): int
{
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
    return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
}

function yovel_admin_sales_campaign_efficiency(array $company, array $admin, string $campaignKey): array
{
    yovel_admin_sales_crm_schema();
    yovel_admin_sales_crm_require_scope($company, $admin, 'crm');
    if (!yovel_admin_is_uuid($campaignKey)) {
        throw new InvalidArgumentException('Invalid Campaign key.');
    }
    $campaign = yovel_admin_sales_campaign_row($company, $campaignKey, false);
    $db = bx_db();
    $companyKeyHash = (string) $company['company_key_hash'];
    $result = [
        'campaign_key' => $campaignKey,
        'budget' => (string) $campaign['budget'],
        'expected_revenue' => (string) $campaign['expected_revenue'],
        'lead_count' => 0,
        'qualified_lead_count' => 0,
        'converted_lead_count' => 0,
        'lead_value' => '0.00',
        'opportunity_count' => 0,
        'opportunity_value' => '0.00',
        'quotation_count' => 0,
        'quotation_value' => '0.00',
        'sales_order_count' => 0,
        'sales_order_value' => '0.00',
        'attributed_revenue' => '0.00',
        'roi_percent' => '0.00',
        'source_states' => [],
    ];
    foreach (yovel_admin_sales_campaign_attribution_sources() as $name => $source) {
        $available = yovel_admin_sales_campaign_source_available($source);
        $result['source_states'][$name] = $available ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY';
        if (!$available) {
            continue;
        }
        $table = (string) $source['table'];
        $statusColumn = (string) $source['status_column'];
        $valueColumn = (string) $source['value_column'];
        $row = $db->GetRow(
            "SELECT COUNT(*) AS record_count, COALESCE(SUM({$valueColumn}), 0.00) AS record_value
             FROM {$table} WHERE company_key_hash = ? AND campaign_key = ?",
            [$companyKeyHash, $campaignKey]
        );
        $singular = $name === 'opportunities' ? 'opportunity' : ($name === 'sales_orders' ? 'sales_order' : rtrim($name, 's'));
        $result[$singular . '_count'] = (int) ($row['record_count'] ?? 0);
        $result[$singular . '_value'] = yovel_admin_sales_campaign_decimal_readback($row['record_value'] ?? '0');
        if ($name === 'leads') {
            $statusCounts = $db->GetRow(
                "SELECT
                    SUM(CASE WHEN {$statusColumn} = 'QUALIFIED' THEN 1 ELSE 0 END) AS qualified_count,
                    SUM(CASE WHEN {$statusColumn} = 'CONVERTED' THEN 1 ELSE 0 END) AS converted_count
                 FROM {$table} WHERE company_key_hash = ? AND campaign_key = ?",
                [$companyKeyHash, $campaignKey]
            );
            $result['qualified_lead_count'] = (int) ($statusCounts['qualified_count'] ?? 0);
            $result['converted_lead_count'] = (int) ($statusCounts['converted_count'] ?? 0);
        }
    }
    $result['attributed_revenue'] = $result['sales_order_value'];
    $budgetCents = yovel_admin_sales_campaign_money_to_cents((string) $result['budget']);
    $revenueCents = yovel_admin_sales_campaign_money_to_cents((string) $result['attributed_revenue']);
    if ($budgetCents > 0) {
        $basisPoints = intdiv(($revenueCents - $budgetCents) * 10000, $budgetCents);
        $result['roi_percent'] = number_format($basisPoints / 100, 2, '.', '');
    }

    return $result;
}
