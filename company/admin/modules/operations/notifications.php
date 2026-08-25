<?php
declare(strict_types=1);

function yovel_admin_operations_notification_actor(array $company, mixed $actorAdminKey): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    if (!is_string($actorAdminKey)) {
        throw new InvalidArgumentException('An authorized active company administrator is required for notification handoff.');
    }
    $actorAdminKey = strtolower(trim((string) $actorAdminKey));
    if (!yovel_admin_is_uuid($actorAdminKey)) {
        throw new InvalidArgumentException('An authorized active company administrator is required for notification handoff.');
    }
    $actor = bx_db()->GetRow(
        "SELECT admin_record.admin_key,admin_record.admin_status
        FROM project_company_admin admin_record
        INNER JOIN project_company company_record
            ON company_record.company_key_hash=admin_record.company_key_hash
           AND company_record.company_key=admin_record.company_key
           AND company_record.company_status='ACTIVE'
        WHERE admin_record.admin_key=?
          AND admin_record.company_key_hash=?
          AND admin_record.company_key=?
          AND admin_record.admin_status='ACTIVE'
        LIMIT 1",
        [$actorAdminKey, $companyKeyHash, $companyKey]
    );
    if (!is_array($actor) || $actor === []) {
        throw new RuntimeException('An authorized active company administrator is required for notification handoff.');
    }
    return ['admin_key' => $actorAdminKey, 'admin_status' => 'ACTIVE'];
}

function yovel_admin_operations_notification_boolean(mixed $value, string $label): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
        return (bool) (int) $value;
    }
    throw new InvalidArgumentException($label . ' must be a boolean.');
}

function yovel_admin_operations_notification_uuid(mixed $value, string $label, bool $required = true): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException($label . ' recipient or source reference is invalid.');
    }
    $value = strtolower(trim((string) $value));
    if ($value === '' && !$required) {
        return '';
    }
    if (!yovel_admin_is_uuid($value)) {
        throw new InvalidArgumentException($label . ' recipient or source reference is invalid.');
    }
    return $value;
}

function yovel_admin_operations_notification_date(mixed $value, string $label): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    $value = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function yovel_admin_operations_notification_score(mixed $value): string
{
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        throw new InvalidArgumentException('Notification total score is invalid.');
    }
    $value = trim((string) $value);
    if (preg_match('/^(?:0|[1-9][0-9]?|100)(?:\.[0-9]{1,4})?$/', $value) !== 1 || (float) $value > 100) {
        throw new InvalidArgumentException('Notification total score must be between 0 and 100 with up to four decimals.');
    }
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    return ltrim($whole, '0') === '' ? '0.' . str_pad($fraction, 4, '0') : ltrim($whole, '0') . '.' . str_pad($fraction, 4, '0');
}

function yovel_admin_operations_notification_reject_secrets(mixed $value): void
{
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $key => $item) {
        if (preg_match('/(?:password|passwd|secret|token|credential|api[_-]?key)/i', (string) $key) === 1) {
            throw new InvalidArgumentException('Raw secret values are not accepted by Operations notification handoff.');
        }
        yovel_admin_operations_notification_reject_secrets($item);
    }
}

function yovel_admin_operations_notification_text(mixed $value, string $label): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return trim($value);
}

function yovel_admin_operations_normalize_notification_handoff(array $input): array
{
    yovel_admin_operations_notification_reject_secrets($input);
    $allowed = [
        'actor_admin_key', 'source_module', 'source_record_type', 'source_record_key',
        'supplier_key', 'scorecard_key', 'period_start', 'period_end', 'total_score',
        'standing_code', 'notify_supplier', 'notify_employee', 'employee_key',
    ];
    $unknown = array_diff(array_keys($input), $allowed);
    if ($unknown !== []) {
        throw new InvalidArgumentException('Operations notification handoff contains an unknown field.');
    }
    $encodedInput = json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($encodedInput) > 8192) {
        throw new InvalidArgumentException('Operations notification handoff payload is too large.');
    }

    $sourceModule = strtolower(yovel_admin_operations_notification_text($input['source_module'] ?? null, 'Notification source module'));
    $sourceType = strtolower(yovel_admin_operations_notification_text($input['source_record_type'] ?? null, 'Notification source record type'));
    if ($sourceModule !== 'buying-procurement' || $sourceType !== 'supplier-scorecard-period') {
        throw new InvalidArgumentException('Operations notification source is not allow-listed.');
    }
    $sourceKey = yovel_admin_operations_notification_uuid($input['source_record_key'] ?? '', 'Notification source');
    $supplierKey = yovel_admin_operations_notification_uuid($input['supplier_key'] ?? '', 'Supplier');
    $scorecardKey = yovel_admin_operations_notification_uuid($input['scorecard_key'] ?? '', 'Scorecard source');
    $employeeKey = yovel_admin_operations_notification_uuid($input['employee_key'] ?? '', 'Employee', false);
    $notifySupplier = yovel_admin_operations_notification_boolean($input['notify_supplier'] ?? null, 'Notify supplier');
    $notifyEmployee = yovel_admin_operations_notification_boolean($input['notify_employee'] ?? null, 'Notify employee');
    if (!$notifySupplier && !$notifyEmployee) {
        throw new InvalidArgumentException('Notification handoff requires at least one intended recipient.');
    }
    if ($notifyEmployee && $employeeKey === '') {
        throw new InvalidArgumentException('Employee recipient reference is required.');
    }
    $periodStart = yovel_admin_operations_notification_date($input['period_start'] ?? '', 'Notification period start');
    $periodEnd = yovel_admin_operations_notification_date($input['period_end'] ?? '', 'Notification period end');
    if ($periodStart > $periodEnd) {
        throw new InvalidArgumentException('Notification period end cannot precede its start.');
    }
    $standingCode = strtoupper(yovel_admin_operations_notification_text($input['standing_code'] ?? null, 'Notification standing code'));
    if (preg_match('/^[A-Z0-9][A-Z0-9_-]{0,79}$/', $standingCode) !== 1) {
        throw new InvalidArgumentException('Notification standing code is invalid.');
    }

    $recipients = [];
    if ($notifySupplier) {
        $recipients[] = ['recipient_type' => 'supplier', 'recipient_key' => $supplierKey];
    }
    if ($notifyEmployee) {
        $recipients[] = ['recipient_type' => 'employee', 'recipient_key' => $employeeKey];
    }
    usort($recipients, static fn (array $left, array $right): int => strcmp(
        $left['recipient_type'] . ':' . $left['recipient_key'],
        $right['recipient_type'] . ':' . $right['recipient_key']
    ));

    return [
        'contract' => 'operations.notification-handoff.v1',
        'event' => 'supplier-scorecard-period-calculated',
        'source' => ['module' => $sourceModule, 'record_type' => $sourceType, 'record_key' => $sourceKey],
        'recipients' => $recipients,
        'notification' => [
            'supplier_key' => $supplierKey,
            'scorecard_key' => $scorecardKey,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_score' => yovel_admin_operations_notification_score($input['total_score'] ?? ''),
            'standing_code' => $standingCode,
        ],
    ];
}

function yovel_admin_operations_notification_idempotency(array $company, array $payload): string
{
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    $identity = [
        'company_key_hash' => $companyKeyHash,
        'source' => $payload['source'] ?? [],
        'event' => $payload['event'] ?? '',
        'recipients' => $payload['recipients'] ?? [],
    ];
    return 'notification-handoff:' . hash('sha256', yovel_admin_operations_json($identity));
}

function yovel_admin_operations_create_notification_handoff(array $company, array $input): array
{
    $actor = yovel_admin_operations_notification_actor($company, $input['actor_admin_key'] ?? null);
    $payload = yovel_admin_operations_normalize_notification_handoff($input);
    $payloadJson = yovel_admin_operations_json($payload);
    $idempotencyKey = yovel_admin_operations_notification_idempotency($company, $payload);
    $checksum = hash('sha256', $payloadJson);
    $job = yovel_admin_operations_enqueue_job(bx_db(), $company, $actor, [
        'idempotency_key' => $idempotencyKey,
        'job_type' => 'NOTIFICATION',
        'source_section' => 'notifications',
        'payload' => $payload,
        'priority' => 50,
        'max_attempts' => 3,
    ]);
    $readBack = yovel_admin_operations_job($company, (string) ($job['job_key'] ?? ''));
    if (!$readBack
        || (string) $readBack['idempotency_key'] !== $idempotencyKey
        || (string) $readBack['job_type'] !== 'NOTIFICATION'
        || (string) $readBack['source_section'] !== 'notifications'
        || yovel_admin_operations_json($readBack['payload']) !== $payloadJson
        || hash('sha256', yovel_admin_operations_json($readBack['payload'])) !== $checksum) {
        throw new RuntimeException('Operations notification handoff read-back verification failed.');
    }
    return [
        'job_key' => (string) $readBack['job_key'],
        'idempotency_key' => $idempotencyKey,
        'status' => (string) $readBack['status'],
        'checksum' => $checksum,
    ];
}
