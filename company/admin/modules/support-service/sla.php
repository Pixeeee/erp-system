<?php
declare(strict_types=1);

function yovel_admin_support_sla_utc(DateTimeImmutable $value): DateTimeImmutable
{
    return $value->setTimezone(new DateTimeZone('UTC'));
}

function yovel_admin_support_sla_now(?DateTimeImmutable $value = null): DateTimeImmutable
{
    return yovel_admin_support_sla_utc($value ?? new DateTimeImmutable('now', new DateTimeZone('UTC')));
}

function yovel_admin_support_sla_parse_date(mixed $value, string $label): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($label . ' date is invalid.');
    }

    return $value;
}

function yovel_admin_support_sla_parse_time(mixed $value, string $label): string
{
    $value = trim((string) $value);
    if (preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $value) !== 1) {
        throw new InvalidArgumentException($label . ' time is invalid.');
    }

    return strlen($value) === 5 ? $value . ':00' : $value;
}

function yovel_admin_support_sla_input_rows(array $input, string $key, string $jsonKey): array
{
    if (isset($input[$key])) {
        if (!is_array($input[$key])) {
            throw new InvalidArgumentException(str_replace('_', ' ', ucfirst($key)) . ' must be a list.');
        }

        return array_values($input[$key]);
    }
    $encoded = trim((string) ($input[$jsonKey] ?? ''));
    if ($encoded === '') {
        return [];
    }
    try {
        $rows = json_decode($encoded, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new InvalidArgumentException(str_replace('_', ' ', ucfirst($key)) . ' JSON is invalid.');
    }
    if (!is_array($rows) || !array_is_list($rows)) {
        throw new InvalidArgumentException(str_replace('_', ' ', ucfirst($key)) . ' JSON must contain a list.');
    }

    return $rows;
}

function yovel_admin_support_sla_input_statuses(array $input, string $key, string $csvKey): array
{
    $values = $input[$key] ?? null;
    if ($values === null) {
        $values = preg_split('/\s*,\s*/', trim((string) ($input[$csvKey] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
    }
    if (!is_array($values)) {
        throw new InvalidArgumentException(str_replace('_', ' ', ucfirst($key)) . ' must be a list.');
    }
    $allowed = ['OPEN', 'REPLIED', 'ON_HOLD', 'RESOLVED', 'CLOSED'];
    $normalized = [];
    foreach ($values as $value) {
        $status = strtoupper(trim((string) $value));
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('SLA issue status is invalid.');
        }
        if (isset($normalized[$status])) {
            throw new InvalidArgumentException('SLA issue status rows contain a duplicate.');
        }
        $normalized[$status] = $status;
    }
    ksort($normalized, SORT_STRING);

    return array_values($normalized);
}

function yovel_admin_support_sla_normalize_condition(mixed $value): string
{
    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        try {
            $value = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('SLA condition JSON is invalid.');
        }
    }
    if ($value === null || $value === []) {
        return '';
    }
    if (!is_array($value) || array_keys($value) !== ['all'] || !is_array($value['all']) || !array_is_list($value['all'])) {
        throw new InvalidArgumentException('SLA condition must use the allowlisted all-expression model.');
    }
    if (count($value['all']) > 12) {
        throw new InvalidArgumentException('SLA condition has too many clauses.');
    }
    $allowedFields = ['subject', 'description', 'issue_status', 'issue_priority_key', 'issue_type_key'];
    $allowedOperators = ['EQUALS', 'NOT_EQUALS', 'CONTAINS', 'STARTS_WITH'];
    $clauses = [];
    foreach ($value['all'] as $clause) {
        if (!is_array($clause)) {
            throw new InvalidArgumentException('SLA condition clause is invalid.');
        }
        $field = trim((string) ($clause['field'] ?? ''));
        $operator = strtoupper(trim((string) ($clause['operator'] ?? '')));
        $operand = trim((string) ($clause['value'] ?? ''));
        if (!in_array($field, $allowedFields, true)
            || !in_array($operator, $allowedOperators, true)
            || $operand === ''
            || strlen($operand) > 255) {
            throw new InvalidArgumentException('SLA condition contains a field, operator, or value outside the allowlist.');
        }
        $clauses[] = ['field' => $field, 'operator' => $operator, 'value' => $operand];
    }
    if ($clauses === []) {
        throw new InvalidArgumentException('SLA condition requires at least one clause.');
    }

    return json_encode(['all' => $clauses], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function yovel_admin_support_sla_validate_sales_entity(
    array $company,
    string $entityType,
    string $entityKey,
    array $input
): void {
    if ($entityType === 'NONE') {
        return;
    }
    if (!function_exists('yovel_admin_sales_crm_customer_segmentation')) {
        throw new YovelAdminSupportUnavailableDependency(
            'sales-crm.customer-segmentation.v1',
            'support-sla',
            $input
        );
    }
    try {
        $response = yovel_admin_sales_crm_customer_segmentation($company, [
            'entity_type' => $entityType,
            'entity_key' => $entityKey,
        ]);
    } catch (Throwable) {
        throw new YovelAdminSupportUnavailableDependency(
            'sales-crm.customer-segmentation.v1',
            'support-sla',
            $input
        );
    }
    if (($response['contract'] ?? '') !== 'sales-crm.customer-segmentation.v1'
        || !hash_equals(
            strtolower((string) ($company['company_key_hash'] ?? '')),
            strtolower((string) ($response['company_key_hash'] ?? ''))
        )
        || strtoupper((string) ($response['entity_type'] ?? '')) !== $entityType
        || (string) ($response['entity_key'] ?? '') !== $entityKey
        || ($response['valid'] ?? null) !== true) {
        throw new InvalidArgumentException('SLA applicability reference does not belong to this company.');
    }
}

function yovel_admin_support_sla_form_checksum(): string
{
    $schema = yovel_admin_support_service_default_form_schemas()['service-level-agreement'];
    $normalized = yovel_admin_support_service_normalize_form_schema('service-level-agreement', $schema);

    return yovel_admin_support_service_form_checksum($normalized);
}

function yovel_admin_support_sla_normalize_input(array $company, array $input): array
{
    $stableKey = yovel_admin_support_service_optional_uuid($input, 'service_level_agreement_key', 'Service Level Agreement');
    $expectedVersion = yovel_admin_support_service_required_version($input);
    $code = strtoupper(yovel_admin_support_service_text($input, 'sla_code', 120, true));
    if (preg_match('/^[A-Z0-9][A-Z0-9_.-]{0,119}$/D', $code) !== 1) {
        throw new InvalidArgumentException('SLA code is invalid.');
    }
    $name = yovel_admin_support_service_text($input, 'service_level_name', 160, true);
    $documentType = strtoupper(yovel_admin_support_service_text($input, 'document_type', 120, true));
    if ($documentType !== 'ISSUE') {
        throw new InvalidArgumentException('SLA document type must be ISSUE.');
    }
    $entityType = strtoupper(trim((string) ($input['entity_type'] ?? 'NONE')));
    if (!in_array($entityType, ['NONE', 'CUSTOMER', 'CUSTOMER_GROUP', 'TERRITORY'], true)) {
        throw new InvalidArgumentException('SLA entity type is invalid.');
    }
    $entityKey = trim((string) ($input['entity_key'] ?? ''));
    if (($entityType === 'NONE') !== ($entityKey === '')) {
        throw new InvalidArgumentException('SLA applicability requires an entity key only for a specialized entity type.');
    }
    if (strlen($entityKey) > 1500) {
        throw new InvalidArgumentException('SLA entity key exceeds the allowed length.');
    }
    $entityName = yovel_admin_support_service_text($input, 'entity_name_snapshot', 255, $entityType !== 'NONE');
    $startDate = yovel_admin_support_sla_parse_date($input['start_date'] ?? '', 'SLA start');
    $endDate = yovel_admin_support_sla_parse_date($input['end_date'] ?? '', 'SLA end');
    if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
        throw new InvalidArgumentException('SLA end date must be on or after the start date.');
    }
    $timezoneName = trim((string) ($input['timezone_name'] ?? 'UTC'));
    try {
        new DateTimeZone($timezoneName);
    } catch (Throwable) {
        throw new InvalidArgumentException('SLA timezone is invalid.');
    }
    if (strlen($timezoneName) > 64) {
        throw new InvalidArgumentException('SLA timezone exceeds the allowed length.');
    }
    $calendarKey = trim((string) ($input['calendar_key'] ?? ''));
    if (strlen($calendarKey) > 1500) {
        throw new InvalidArgumentException('SLA calendar key exceeds the allowed length.');
    }
    $isDefault = yovel_admin_support_service_input_bool($input, 'is_default');
    $enabled = yovel_admin_support_service_input_bool($input, 'enabled');
    $applyResolution = yovel_admin_support_service_input_bool($input, 'apply_for_resolution');
    $status = yovel_admin_support_service_status($input, 'sla_status', 'Service Level Agreement');
    $conditionJson = yovel_admin_support_sla_normalize_condition($input['condition_json'] ?? '');
    if ($isDefault === 1 && ($entityType !== 'NONE' || $conditionJson !== '')) {
        throw new InvalidArgumentException('A default SLA cannot have specialized applicability or a condition.');
    }
    yovel_admin_support_sla_validate_sales_entity($company, $entityType, $entityKey, $input);

    $days = [];
    foreach (yovel_admin_support_sla_input_rows($input, 'service_days', 'service_days_json') as $index => $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('SLA service day row is invalid.');
        }
        $weekday = filter_var($row['weekday'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
        if ($weekday === false) {
            throw new InvalidArgumentException('SLA service day weekday is invalid.');
        }
        if (isset($days[(int) $weekday])) {
            throw new InvalidArgumentException('SLA service day weekday must be unique.');
        }
        $startTime = yovel_admin_support_sla_parse_time($row['start_time'] ?? '', 'Service day start');
        $endTime = yovel_admin_support_sla_parse_time($row['end_time'] ?? '', 'Service day end');
        if ($startTime >= $endTime) {
            throw new InvalidArgumentException('Service day start must be before end.');
        }
        $sortOrder = filter_var($row['sort_order'] ?? (($index + 1) * 10), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 65535]]);
        if ($sortOrder === false) {
            throw new InvalidArgumentException('SLA service day sort order is invalid.');
        }
        $days[(int) $weekday] = [
            'sla_service_day_key' => yovel_admin_support_service_optional_uuid($row, 'sla_service_day_key', 'SLA Service Day'),
            'weekday' => (int) $weekday,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'sort_order' => (int) $sortOrder,
        ];
    }
    if ($days === []) {
        throw new InvalidArgumentException('SLA requires at least one service day.');
    }
    ksort($days, SORT_NUMERIC);

    $priorities = [];
    $defaultCount = 0;
    foreach (yovel_admin_support_sla_input_rows($input, 'priorities', 'priorities_json') as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('SLA priority row is invalid.');
        }
        $priorityKey = yovel_admin_support_service_optional_uuid($row, 'issue_priority_key', 'Issue Priority');
        if ($priorityKey === '') {
            throw new InvalidArgumentException('SLA priority requires an Issue Priority.');
        }
        if (isset($priorities[$priorityKey])) {
            throw new InvalidArgumentException('SLA priority rows contain a duplicate.');
        }
        $response = filter_var($row['response_seconds'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 315360000]]);
        $resolutionRaw = trim((string) ($row['resolution_seconds'] ?? ''));
        $resolution = $resolutionRaw === '' ? null : filter_var($resolutionRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 315360000]]);
        if ($response === false || $resolution === false) {
            throw new InvalidArgumentException('SLA priority response or resolution duration is invalid.');
        }
        if ($resolution !== null && $response > $resolution) {
            throw new InvalidArgumentException('SLA priority resolution duration must be at least the response duration.');
        }
        $default = filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $defaultCount += $default;
        $priorities[$priorityKey] = [
            'sla_priority_key' => yovel_admin_support_service_optional_uuid($row, 'sla_priority_key', 'SLA Priority'),
            'issue_priority_key' => $priorityKey,
            'response_seconds' => (int) $response,
            'resolution_seconds' => $resolution !== null ? (int) $resolution : null,
            'is_default' => $default,
        ];
    }
    if ($priorities === [] || $defaultCount !== 1) {
        throw new InvalidArgumentException('SLA priorities require exactly one default row.');
    }

    return [
        'service_level_agreement_key' => $stableKey,
        'expected_version' => $expectedVersion,
        'sla_code' => $code,
        'service_level_name' => $name,
        'document_type' => $documentType,
        'entity_type' => $entityType,
        'entity_key' => $entityKey,
        'entity_name_snapshot' => $entityName,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'timezone_name' => $timezoneName,
        'calendar_key' => $calendarKey,
        'is_default' => $isDefault,
        'enabled' => $enabled,
        'apply_for_resolution' => $applyResolution,
        'condition_json' => $conditionJson,
        'sla_status' => $status,
        'service_days' => array_values($days),
        'priorities' => array_values($priorities),
        'pause_statuses' => yovel_admin_support_sla_input_statuses($input, 'pause_statuses', 'pause_statuses_csv'),
        'fulfilled_statuses' => yovel_admin_support_sla_input_statuses($input, 'fulfilled_statuses', 'fulfilled_statuses_csv'),
        'form_schema_checksum' => yovel_admin_support_sla_form_checksum(),
    ];
}

function yovel_admin_support_slas(array $company, array $admin): array
{
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $rows = bx_db()->GetAll(
        'SELECT * FROM project_company_support_sla WHERE company_key_hash = ? ORDER BY service_level_name, sla_code LIMIT 300',
        [$companyHash]
    );

    return is_array($rows) ? $rows : [];
}

function yovel_admin_support_sla_read_aggregate(ADOConnection $db, string $companyHash, string $slaKey): array
{
    $header = $db->GetRow(
        'SELECT * FROM project_company_support_sla WHERE company_key_hash = ? AND service_level_agreement_key = ? LIMIT 1',
        [$companyHash, $slaKey]
    );
    if (!is_array($header) || $header === []) {
        return [];
    }
    $children = [
        'service_days' => ['project_company_support_sla_service_day', 'sort_order, weekday, sla_service_day_key'],
        'priorities' => ['project_company_support_sla_priority', 'is_default DESC, issue_priority_key, sla_priority_key'],
        'pause_statuses' => ['project_company_support_sla_pause_status', 'issue_status, sla_pause_status_key'],
        'fulfilled_statuses' => ['project_company_support_sla_fulfilled_status', 'issue_status, sla_fulfilled_status_key'],
    ];
    foreach ($children as $key => [$table, $order]) {
        $rows = $db->GetAll(
            "SELECT * FROM {$table} WHERE company_key_hash = ? AND service_level_agreement_key = ? ORDER BY {$order}",
            [$companyHash, $slaKey]
        );
        $header[$key] = is_array($rows) ? $rows : [];
    }

    return $header;
}

function yovel_admin_support_sla(array $company, array $admin, string $slaKey): array
{
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $slaKey = yovel_admin_support_service_optional_uuid(
        ['service_level_agreement_key' => $slaKey],
        'service_level_agreement_key',
        'Service Level Agreement'
    );
    $global = bx_db()->GetRow(
        'SELECT company_key_hash FROM project_company_support_sla WHERE service_level_agreement_key = ? LIMIT 1',
        [$slaKey]
    );
    if (!is_array($global) || $global === []) {
        throw new InvalidArgumentException('Service Level Agreement does not belong to this company.');
    }
    if (!hash_equals($companyHash, strtolower((string) $global['company_key_hash']))) {
        throw new InvalidArgumentException('Service Level Agreement belongs to another company.');
    }

    return yovel_admin_support_sla_read_aggregate(bx_db(), $companyHash, $slaKey);
}

function yovel_admin_save_support_sla(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    yovel_admin_support_service_assert_no_secrets($input);
    yovel_admin_support_service_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_support_service_scope($company, $admin);
    $values = yovel_admin_support_sla_normalize_input($company, $input);
    $requestedKey = (string) $values['service_level_agreement_key'];
    if ($requestedKey !== '') {
        $global = bx_db()->GetRow(
            'SELECT company_key_hash FROM project_company_support_sla WHERE service_level_agreement_key = ? LIMIT 1',
            [$requestedKey]
        );
        if (!is_array($global) || $global === []) {
            throw new InvalidArgumentException('Service Level Agreement does not belong to this company.');
        }
        if (!hash_equals($companyHash, strtolower((string) $global['company_key_hash']))) {
            throw new InvalidArgumentException('Service Level Agreement belongs to another company.');
        }
    }
    foreach ($values['priorities'] as $priority) {
        yovel_admin_support_service_active_master(
            'project_company_support_issue_priority',
            'issue_priority_key',
            'priority_status',
            (string) $priority['issue_priority_key'],
            $companyHash,
            'Issue Priority'
        );
    }

    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Support SLA transaction could not start.');
    }
    try {
        yovel_admin_support_service_lock_company($db, $companyKey, $companyHash);
        $current = $requestedKey !== ''
            ? $db->GetRow(
                'SELECT * FROM project_company_support_sla WHERE company_key_hash = ? AND service_level_agreement_key = ? FOR UPDATE',
                [$companyHash, $requestedKey]
            )
            : $db->GetRow(
                'SELECT * FROM project_company_support_sla WHERE company_key_hash = ? AND sla_code = ? FOR UPDATE',
                [$companyHash, $values['sla_code']]
            );
        $current = is_array($current) && $current !== [] ? $current : null;
        $currentVersion = (int) ($current['sla_version'] ?? 0);
        if ($currentVersion !== (int) $values['expected_version']) {
            throw new RuntimeException('Service Level Agreement changed since this form was opened. Reload and try again.');
        }
        $recordKey = $current !== null ? (string) $current['service_level_agreement_key'] : bx_uuid();
        $sameCode = $db->GetRow(
            'SELECT service_level_agreement_key FROM project_company_support_sla WHERE company_key_hash = ? AND sla_code = ? FOR UPDATE',
            [$companyHash, $values['sla_code']]
        );
        if (is_array($sameCode) && $sameCode !== [] && (string) $sameCode['service_level_agreement_key'] !== $recordKey) {
            throw new InvalidArgumentException('SLA code belongs to another record.');
        }
        if ((int) $values['is_default'] === 1 && (int) $values['enabled'] === 1 && $values['sla_status'] === 'ACTIVE') {
            $otherDefault = $db->GetOne(
                "SELECT service_level_agreement_key FROM project_company_support_sla
                 WHERE company_key_hash = ? AND document_type = ? AND is_default = 1
                   AND enabled = 1 AND sla_status = 'ACTIVE' AND service_level_agreement_key <> ?
                 LIMIT 1 FOR UPDATE",
                [$companyHash, $values['document_type'], $recordKey]
            );
            if (is_string($otherDefault) && $otherDefault !== '') {
                throw new RuntimeException('Only one active default SLA is allowed per company and document type.');
            }
        }
        $nextVersion = $currentVersion + 1;
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_support_sla (
                service_level_agreement_key, company_key, company_key_hash, sla_code,
                service_level_name, sla_version, form_schema_checksum, document_type,
                timezone_name, entity_type, entity_key, entity_key_hash, entity_name_snapshot,
                start_date, end_date, calendar_contract, calendar_key, is_default,
                enabled, apply_for_resolution, condition_json, sla_status,
                created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'operations.calendar-holiday-dates.v1', ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                sla_code = VALUES(sla_code), service_level_name = VALUES(service_level_name),
                sla_version = VALUES(sla_version), form_schema_checksum = VALUES(form_schema_checksum),
                document_type = VALUES(document_type), timezone_name = VALUES(timezone_name),
                entity_type = VALUES(entity_type), entity_key = VALUES(entity_key),
                entity_key_hash = VALUES(entity_key_hash), entity_name_snapshot = VALUES(entity_name_snapshot),
                start_date = VALUES(start_date), end_date = VALUES(end_date),
                calendar_contract = VALUES(calendar_contract), calendar_key = VALUES(calendar_key),
                is_default = VALUES(is_default), enabled = VALUES(enabled),
                apply_for_resolution = VALUES(apply_for_resolution), condition_json = VALUES(condition_json),
                sla_status = VALUES(sla_status), updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $recordKey, $companyKey, $companyHash, $values['sla_code'],
                $values['service_level_name'], $nextVersion, $values['form_schema_checksum'],
                $values['document_type'], $values['timezone_name'], $values['entity_type'],
                $values['entity_key'] !== '' ? $values['entity_key'] : null,
                $values['entity_key'] !== '' ? hash('sha256', $values['entity_key']) : null,
                $values['entity_name_snapshot'] !== '' ? $values['entity_name_snapshot'] : null,
                $values['start_date'], $values['end_date'],
                $values['calendar_key'] !== '' ? $values['calendar_key'] : null,
                $values['is_default'], $values['enabled'], $values['apply_for_resolution'],
                $values['condition_json'] !== '' ? $values['condition_json'] : null,
                $values['sla_status'], $adminKey, $adminKey,
            ],
            'Support SLA save'
        );

        $existingDays = $db->GetAll(
            'SELECT * FROM project_company_support_sla_service_day WHERE company_key_hash = ? AND service_level_agreement_key = ? FOR UPDATE',
            [$companyHash, $recordKey]
        );
        $existingPriorities = $db->GetAll(
            'SELECT * FROM project_company_support_sla_priority WHERE company_key_hash = ? AND service_level_agreement_key = ? FOR UPDATE',
            [$companyHash, $recordKey]
        );
        $existingPause = $db->GetAll(
            'SELECT * FROM project_company_support_sla_pause_status WHERE company_key_hash = ? AND service_level_agreement_key = ? FOR UPDATE',
            [$companyHash, $recordKey]
        );
        $existingFulfilled = $db->GetAll(
            'SELECT * FROM project_company_support_sla_fulfilled_status WHERE company_key_hash = ? AND service_level_agreement_key = ? FOR UPDATE',
            [$companyHash, $recordKey]
        );
        foreach ([
            'project_company_support_sla_service_day',
            'project_company_support_sla_priority',
            'project_company_support_sla_pause_status',
            'project_company_support_sla_fulfilled_status',
        ] as $table) {
            yovel_admin_db_execute(
                $db,
                "DELETE FROM {$table} WHERE company_key_hash = ? AND service_level_agreement_key = ?",
                [$companyHash, $recordKey],
                'Support SLA child replacement'
            );
        }
        $dayKeys = [];
        foreach (is_array($existingDays) ? $existingDays : [] as $row) {
            $dayKeys[(int) $row['weekday']] = (string) $row['sla_service_day_key'];
        }
        foreach ($values['service_days'] as $day) {
            $childKey = (string) $day['sla_service_day_key'];
            $childKey = $childKey !== '' ? $childKey : ($dayKeys[(int) $day['weekday']] ?? bx_uuid());
            yovel_admin_db_execute(
                $db,
                'INSERT INTO project_company_support_sla_service_day (
                    sla_service_day_key, service_level_agreement_key, company_key, company_key_hash,
                    weekday, start_time, end_time, sort_order, created_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$childKey, $recordKey, $companyKey, $companyHash, $day['weekday'], $day['start_time'], $day['end_time'], $day['sort_order'], $adminKey],
                'Support SLA service day save'
            );
        }
        $priorityKeys = [];
        foreach (is_array($existingPriorities) ? $existingPriorities : [] as $row) {
            $priorityKeys[(string) $row['issue_priority_key']] = (string) $row['sla_priority_key'];
        }
        foreach ($values['priorities'] as $priority) {
            $childKey = (string) $priority['sla_priority_key'];
            $childKey = $childKey !== '' ? $childKey : ($priorityKeys[(string) $priority['issue_priority_key']] ?? bx_uuid());
            yovel_admin_db_execute(
                $db,
                'INSERT INTO project_company_support_sla_priority (
                    sla_priority_key, service_level_agreement_key, company_key, company_key_hash,
                    issue_priority_key, response_seconds, resolution_seconds, is_default, created_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$childKey, $recordKey, $companyKey, $companyHash, $priority['issue_priority_key'], $priority['response_seconds'], $priority['resolution_seconds'], $priority['is_default'], $adminKey],
                'Support SLA priority save'
            );
        }
        $pauseKeys = [];
        foreach (is_array($existingPause) ? $existingPause : [] as $row) {
            $pauseKeys[(string) $row['issue_status']] = (string) $row['sla_pause_status_key'];
        }
        foreach ($values['pause_statuses'] as $statusValue) {
            yovel_admin_db_execute(
                $db,
                'INSERT INTO project_company_support_sla_pause_status (
                    sla_pause_status_key, service_level_agreement_key, company_key, company_key_hash,
                    issue_status, created_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, ?)',
                [$pauseKeys[$statusValue] ?? bx_uuid(), $recordKey, $companyKey, $companyHash, $statusValue, $adminKey],
                'Support SLA pause status save'
            );
        }
        $fulfilledKeys = [];
        foreach (is_array($existingFulfilled) ? $existingFulfilled : [] as $row) {
            $fulfilledKeys[(string) $row['issue_status']] = (string) $row['sla_fulfilled_status_key'];
        }
        foreach ($values['fulfilled_statuses'] as $statusValue) {
            yovel_admin_db_execute(
                $db,
                'INSERT INTO project_company_support_sla_fulfilled_status (
                    sla_fulfilled_status_key, service_level_agreement_key, company_key, company_key_hash,
                    issue_status, created_by_admin_key
                 ) VALUES (?, ?, ?, ?, ?, ?)',
                [$fulfilledKeys[$statusValue] ?? bx_uuid(), $recordKey, $companyKey, $companyHash, $statusValue, $adminKey],
                'Support SLA fulfilled status save'
            );
        }

        $saved = yovel_admin_support_sla_read_aggregate($db, $companyHash, $recordKey);
        yovel_admin_support_service_verify_read_back($saved, [
            'service_level_agreement_key' => $recordKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'sla_code' => $values['sla_code'],
            'service_level_name' => $values['service_level_name'],
            'sla_version' => $nextVersion,
            'form_schema_checksum' => $values['form_schema_checksum'],
            'document_type' => $values['document_type'],
            'timezone_name' => $values['timezone_name'],
            'entity_type' => $values['entity_type'],
            'entity_key' => $values['entity_key'],
            'start_date' => $values['start_date'],
            'end_date' => $values['end_date'],
            'is_default' => $values['is_default'],
            'enabled' => $values['enabled'],
            'apply_for_resolution' => $values['apply_for_resolution'],
            'condition_json' => $values['condition_json'],
            'sla_status' => $values['sla_status'],
            'created_by_admin_key' => (string) ($current['created_by_admin_key'] ?? $adminKey),
            'updated_by_admin_key' => $adminKey,
        ], 'Support SLA');
        if (count($saved['service_days'] ?? []) !== count($values['service_days'])
            || count($saved['priorities'] ?? []) !== count($values['priorities'])
            || array_column($saved['pause_statuses'] ?? [], 'issue_status') !== $values['pause_statuses']
            || array_column($saved['fulfilled_statuses'] ?? [], 'issue_status') !== $values['fulfilled_statuses']) {
            throw new RuntimeException('Support SLA aggregate child read-back verification failed.');
        }
        bx_audit($current === null ? 'CREATE' : 'UPDATE', 'project_company_support_sla', $recordKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'sla_code' => $values['sla_code'],
            'sla_version' => $nextVersion,
            'service_day_count' => count($values['service_days']),
            'priority_count' => count($values['priorities']),
            'admin_key' => $adminKey,
        ], 'Company administrator changed a Support SLA aggregate.');
        if ($failureInjector !== null) {
            $failureInjector();
        }
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Support SLA transaction could not commit.');
        }

        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_support_sla_expected_at(
    array $sla,
    DateTimeImmutable $start,
    int $businessSeconds,
    array $holidayDates = []
): DateTimeImmutable {
    if ($businessSeconds < 1) {
        throw new InvalidArgumentException('SLA business duration must be positive.');
    }
    $timezoneName = trim((string) ($sla['timezone_name'] ?? 'UTC'));
    try {
        $timezone = new DateTimeZone($timezoneName !== '' ? $timezoneName : 'UTC');
    } catch (Throwable) {
        throw new InvalidArgumentException('SLA timezone is invalid.');
    }
    $windows = [];
    foreach (($sla['service_days'] ?? []) as $day) {
        if (!is_array($day)) {
            continue;
        }
        $weekday = (int) ($day['weekday'] ?? 0);
        if ($weekday >= 1 && $weekday <= 7) {
            $windows[$weekday] = [
                yovel_admin_support_sla_parse_time($day['start_time'] ?? '', 'Service day start'),
                yovel_admin_support_sla_parse_time($day['end_time'] ?? '', 'Service day end'),
            ];
        }
    }
    if ($windows === []) {
        throw new RuntimeException('SLA has no service-day windows.');
    }
    $holidays = [];
    foreach ($holidayDates as $holiday) {
        $date = yovel_admin_support_sla_parse_date($holiday, 'Holiday');
        if ($date !== null) {
            $holidays[$date] = true;
        }
    }
    $cursor = $start->setTimezone($timezone);
    $remaining = $businessSeconds;
    for ($guard = 0; $guard < 3700; $guard++) {
        $date = $cursor->format('Y-m-d');
        $weekday = (int) $cursor->format('N');
        if (!isset($holidays[$date]) && isset($windows[$weekday])) {
            [$startTime, $endTime] = $windows[$weekday];
            $windowStart = new DateTimeImmutable($date . ' ' . $startTime, $timezone);
            $windowEnd = new DateTimeImmutable($date . ' ' . $endTime, $timezone);
            if ($cursor < $windowStart) {
                $cursor = $windowStart;
            }
            if ($cursor < $windowEnd) {
                $available = $windowEnd->getTimestamp() - $cursor->getTimestamp();
                if ($remaining <= $available) {
                    return yovel_admin_support_sla_utc($cursor->modify('+' . $remaining . ' seconds'));
                }
                $remaining -= $available;
            }
        }
        $cursor = (new DateTimeImmutable($date . ' 00:00:00', $timezone))->modify('+1 day');
    }

    throw new RuntimeException('SLA deadline exceeds the supported calculation range.');
}

function yovel_admin_support_sla_business_seconds_between(
    array $sla,
    DateTimeImmutable $start,
    DateTimeImmutable $end,
    array $holidayDates = []
): int {
    if ($end <= $start) {
        return 0;
    }
    $timezone = new DateTimeZone((string) ($sla['timezone_name'] ?? 'UTC'));
    $cursor = $start->setTimezone($timezone);
    $finish = $end->setTimezone($timezone);
    $windows = [];
    foreach (($sla['service_days'] ?? []) as $day) {
        if (is_array($day)) {
            $windows[(int) $day['weekday']] = [(string) $day['start_time'], (string) $day['end_time']];
        }
    }
    $holidays = array_fill_keys(array_map('strval', $holidayDates), true);
    $seconds = 0;
    for ($guard = 0; $guard < 3700 && $cursor < $finish; $guard++) {
        $date = $cursor->format('Y-m-d');
        $weekday = (int) $cursor->format('N');
        if (!isset($holidays[$date]) && isset($windows[$weekday])) {
            $windowStart = new DateTimeImmutable($date . ' ' . $windows[$weekday][0], $timezone);
            $windowEnd = new DateTimeImmutable($date . ' ' . $windows[$weekday][1], $timezone);
            $from = $cursor > $windowStart ? $cursor : $windowStart;
            $to = $finish < $windowEnd ? $finish : $windowEnd;
            if ($to > $from) {
                $seconds += $to->getTimestamp() - $from->getTimestamp();
            }
        }
        $cursor = (new DateTimeImmutable($date . ' 00:00:00', $timezone))->modify('+1 day');
    }

    return $seconds;
}

function yovel_admin_support_sla_holiday_dates(array $company, array $sla): array
{
    if (!function_exists('yovel_admin_operations_calendar_holiday_dates')) {
        return ['status' => 'UNAVAILABLE_DEPENDENCY', 'dates' => [], 'contract' => 'operations.calendar-holiday-dates.v1'];
    }
    try {
        $response = yovel_admin_operations_calendar_holiday_dates($company, [
            'calendar_key' => (string) ($sla['calendar_key'] ?? ''),
            'start_date' => (string) ($sla['start_date'] ?? ''),
            'end_date' => (string) ($sla['end_date'] ?? ''),
        ]);
        if (($response['contract'] ?? '') !== 'operations.calendar-holiday-dates.v1'
            || !hash_equals(
                strtolower((string) ($company['company_key_hash'] ?? '')),
                strtolower((string) ($response['company_key_hash'] ?? ''))
            )
            || !is_array($response['holiday_dates'] ?? null)) {
            throw new RuntimeException('Operations holiday response is invalid.');
        }
        $dates = [];
        foreach ($response['holiday_dates'] as $holiday) {
            $date = yovel_admin_support_sla_parse_date($holiday, 'Holiday');
            if ($date !== null) {
                $dates[$date] = $date;
            }
        }
        ksort($dates, SORT_STRING);

        return ['status' => 'AVAILABLE', 'dates' => array_values($dates), 'contract' => 'operations.calendar-holiday-dates.v1'];
    } catch (Throwable) {
        return ['status' => 'UNAVAILABLE_DEPENDENCY', 'dates' => [], 'contract' => 'operations.calendar-holiday-dates.v1'];
    }
}

function yovel_admin_support_sla_condition_matches(string $conditionJson, array $issue): bool
{
    if ($conditionJson === '') {
        return false;
    }
    $condition = json_decode($conditionJson, true, 32, JSON_THROW_ON_ERROR);
    foreach (($condition['all'] ?? []) as $clause) {
        $actual = (string) ($issue[$clause['field']] ?? '');
        $expected = (string) $clause['value'];
        $matches = match ($clause['operator']) {
            'EQUALS' => hash_equals($expected, $actual),
            'NOT_EQUALS' => !hash_equals($expected, $actual),
            'CONTAINS' => str_contains(mb_strtolower($actual), mb_strtolower($expected)),
            'STARTS_WITH' => str_starts_with(mb_strtolower($actual), mb_strtolower($expected)),
            default => false,
        };
        if (!$matches) {
            return false;
        }
    }

    return true;
}

function yovel_admin_support_sla_sales_segments(array $company, string $customerKey): array
{
    if ($customerKey === '' || !function_exists('yovel_admin_sales_crm_customer_segmentation')) {
        throw new YovelAdminSupportUnavailableDependency(
            'sales-crm.customer-segmentation.v1',
            'support-sla-selection',
            ['customer_key' => $customerKey]
        );
    }
    try {
        $response = yovel_admin_sales_crm_customer_segmentation($company, ['customer_key' => $customerKey]);
    } catch (Throwable) {
        throw new YovelAdminSupportUnavailableDependency(
            'sales-crm.customer-segmentation.v1',
            'support-sla-selection',
            ['customer_key' => $customerKey]
        );
    }
    if (($response['contract'] ?? '') !== 'sales-crm.customer-segmentation.v1'
        || !hash_equals(
            strtolower((string) ($company['company_key_hash'] ?? '')),
            strtolower((string) ($response['company_key_hash'] ?? ''))
        )
        || (string) ($response['customer_key'] ?? '') !== $customerKey) {
        throw new YovelAdminSupportUnavailableDependency(
            'sales-crm.customer-segmentation.v1',
            'support-sla-selection',
            ['customer_key' => $customerKey]
        );
    }

    return [
        'customer_key' => $customerKey,
        'customer_group_key' => trim((string) ($response['customer_group_key'] ?? '')),
        'territory_key' => trim((string) ($response['territory_key'] ?? '')),
    ];
}

function yovel_admin_support_sla_resolve(
    array $company,
    array $admin,
    array $issue,
    DateTimeImmutable $now
): ?array {
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    $date = yovel_admin_support_sla_utc($now)->format('Y-m-d');
    $headers = bx_db()->GetAll(
        "SELECT service_level_agreement_key FROM project_company_support_sla
         WHERE company_key_hash = ? AND document_type = 'ISSUE'
           AND enabled = 1 AND sla_status = 'ACTIVE'
           AND (start_date IS NULL OR start_date <= ?)
           AND (end_date IS NULL OR end_date >= ?)
         ORDER BY sla_code, service_level_agreement_key",
        [$companyHash, $date, $date]
    );
    $candidates = [];
    $hasSpecialized = false;
    foreach (is_array($headers) ? $headers : [] as $header) {
        $sla = yovel_admin_support_sla_read_aggregate(
            bx_db(),
            $companyHash,
            (string) $header['service_level_agreement_key']
        );
        $entityType = (string) $sla['entity_type'];
        $condition = trim((string) ($sla['condition_json'] ?? ''));
        if ($entityType !== 'NONE') {
            $hasSpecialized = true;
            $candidates[] = ['rank' => match ($entityType) {
                'CUSTOMER' => 40,
                'CUSTOMER_GROUP' => 30,
                'TERRITORY' => 20,
                default => 0,
            }, 'sla' => $sla, 'kind' => 'SPECIALIZED'];
        } elseif ($condition !== '' && yovel_admin_support_sla_condition_matches($condition, $issue)) {
            $candidates[] = ['rank' => 10, 'sla' => $sla, 'kind' => 'CONDITION'];
        } elseif ((int) $sla['is_default'] === 1) {
            $candidates[] = ['rank' => 1, 'sla' => $sla, 'kind' => 'DEFAULT'];
        }
    }
    $dependencies = [];
    $segments = null;
    if ($hasSpecialized && trim((string) ($issue['customer_key'] ?? '')) !== '') {
        try {
            $segments = yovel_admin_support_sla_sales_segments($company, trim((string) $issue['customer_key']));
            $dependencies['sales-crm.customer-segmentation.v1'] = 'AVAILABLE';
        } catch (YovelAdminSupportUnavailableDependency) {
            $dependencies['sales-crm.customer-segmentation.v1'] = 'UNAVAILABLE_DEPENDENCY';
        }
    }
    $matching = [];
    foreach ($candidates as $candidate) {
        if ($candidate['kind'] !== 'SPECIALIZED') {
            $matching[] = $candidate;
            continue;
        }
        if ($segments === null) {
            continue;
        }
        $sla = $candidate['sla'];
        $matches = match ((string) $sla['entity_type']) {
            'CUSTOMER' => hash_equals((string) $sla['entity_key'], $segments['customer_key']),
            'CUSTOMER_GROUP' => (string) $sla['entity_key'] !== '' && hash_equals((string) $sla['entity_key'], $segments['customer_group_key']),
            'TERRITORY' => (string) $sla['entity_key'] !== '' && hash_equals((string) $sla['entity_key'], $segments['territory_key']),
            default => false,
        };
        if ($matches) {
            $matching[] = $candidate;
        }
    }
    usort($matching, static function (array $left, array $right): int {
        return $right['rank'] <=> $left['rank']
            ?: strcmp((string) $left['sla']['sla_code'], (string) $right['sla']['sla_code']);
    });
    if ($matching === []) {
        if (($dependencies['sales-crm.customer-segmentation.v1'] ?? '') === 'UNAVAILABLE_DEPENDENCY') {
            throw new YovelAdminSupportUnavailableDependency(
                'sales-crm.customer-segmentation.v1',
                'support-sla-selection',
                $issue
            );
        }

        return null;
    }
    $selected = $matching[0]['sla'];
    $selected['selection_dependencies'] = $dependencies;

    return $selected;
}

function yovel_admin_support_sla_priority_rule(array $sla, string $priorityKey): array
{
    $default = null;
    foreach (($sla['priorities'] ?? []) as $rule) {
        if ((int) ($rule['is_default'] ?? 0) === 1) {
            $default = $rule;
        }
        if ($priorityKey !== '' && hash_equals((string) $rule['issue_priority_key'], $priorityKey)) {
            return $rule;
        }
    }
    if (!is_array($default)) {
        throw new RuntimeException('SLA has no default priority duration.');
    }

    return $default;
}

function yovel_admin_support_sla_statuses(array $sla, string $collection): array
{
    $statuses = [];
    foreach (($sla[$collection] ?? []) as $row) {
        $status = strtoupper(trim((string) ($row['issue_status'] ?? '')));
        if ($status !== '') {
            $statuses[$status] = true;
        }
    }

    return $statuses;
}

function yovel_admin_support_sla_load_for_issue(ADOConnection $db, string $companyHash, array $issue): ?array
{
    $slaKey = trim((string) ($issue['service_level_agreement_key'] ?? ''));
    if ($slaKey === '') {
        return null;
    }
    $sla = yovel_admin_support_sla_read_aggregate($db, $companyHash, $slaKey);
    if ($sla === [] || !hash_equals($companyHash, strtolower((string) ($sla['company_key_hash'] ?? '')))) {
        throw new RuntimeException('Issue SLA does not belong to this company.');
    }

    return $sla;
}

function yovel_admin_support_sla_write_event(
    ADOConnection $db,
    string $companyKey,
    string $companyHash,
    string $adminKey,
    string $issueKey,
    string $eventType,
    array $payload,
    DateTimeImmutable $now
): void {
    yovel_admin_support_service_write_issue_event(
        $db,
        $companyKey,
        $companyHash,
        $adminKey,
        $issueKey,
        $eventType,
        null,
        null,
        $payload,
        $now
    );
}

function yovel_admin_support_sla_apply_created_in_transaction(
    ADOConnection $db,
    array $company,
    array $admin,
    string $companyKey,
    string $companyHash,
    string $adminKey,
    array $issue,
    DateTimeImmutable $now
): array {
    $settings = $db->GetRow(
        'SELECT track_service_level_agreement FROM project_company_support_setting WHERE company_key_hash = ? LIMIT 1',
        [$companyHash]
    );
    if (!is_array($settings) || (int) ($settings['track_service_level_agreement'] ?? 0) !== 1) {
        return $issue;
    }
    $sla = yovel_admin_support_sla_resolve($company, $admin, $issue, $now);
    if ($sla === null) {
        return $issue;
    }
    $rule = yovel_admin_support_sla_priority_rule($sla, trim((string) ($issue['issue_priority_key'] ?? '')));
    $holidayState = yovel_admin_support_sla_holiday_dates($company, $sla);
    $holidays = $holidayState['dates'];
    $responseBy = yovel_admin_support_sla_expected_at($sla, $now, (int) $rule['response_seconds'], $holidays);
    $resolutionBy = (int) $sla['apply_for_resolution'] === 1
        ? yovel_admin_support_sla_expected_at($sla, $now, (int) $rule['resolution_seconds'], $holidays)
        : null;
    yovel_admin_db_execute(
        $db,
        'UPDATE project_company_support_issue
         SET service_level_agreement_key = ?, response_by = ?, first_responded_on = NULL,
             first_response_seconds = NULL, resolution_by = ?, resolution_date = NULL,
             on_hold_since = NULL, total_hold_seconds = 0,
             sla_resolution_remaining_seconds = NULL, agreement_status = ?, updated_by_admin_key = ?
         WHERE company_key_hash = ? AND issue_key = ?',
        [
            (string) $sla['service_level_agreement_key'],
            $responseBy->format('Y-m-d H:i:s'),
            $resolutionBy?->format('Y-m-d H:i:s'),
            'FIRST_RESPONSE_DUE',
            $adminKey,
            $companyHash,
            (string) $issue['issue_key'],
        ],
        'Support Issue SLA apply'
    );
    yovel_admin_support_sla_write_event(
        $db,
        $companyKey,
        $companyHash,
        $adminKey,
        (string) $issue['issue_key'],
        'SLA_APPLIED',
        [
            'service_level_agreement_key' => (string) $sla['service_level_agreement_key'],
            'response_by' => $responseBy->format('Y-m-d H:i:s'),
            'resolution_by' => $resolutionBy?->format('Y-m-d H:i:s'),
            'holiday_dependency' => (string) $holidayState['status'],
            'selection_dependencies' => $sla['selection_dependencies'] ?? [],
        ],
        $now
    );
    $saved = $db->GetRow(
        'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? LIMIT 1',
        [$companyHash, (string) $issue['issue_key']]
    );
    yovel_admin_support_service_verify_read_back($saved, [
        'service_level_agreement_key' => (string) $sla['service_level_agreement_key'],
        'response_by' => $responseBy->format('Y-m-d H:i:s'),
        'resolution_by' => $resolutionBy?->format('Y-m-d H:i:s'),
        'agreement_status' => 'FIRST_RESPONSE_DUE',
    ], 'Support Issue SLA apply');

    return is_array($saved) ? $saved : [];
}

function yovel_admin_support_sla_on_issue_created(
    array $company,
    array $admin,
    string $issueKey,
    ?DateTimeImmutable $now = null,
    ?callable $failureInjector = null
): array {
    yovel_admin_support_service_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_support_service_scope($company, $admin);
    $issueKey = yovel_admin_support_service_optional_uuid(['issue_key' => $issueKey], 'issue_key', 'Issue');
    yovel_admin_support_service_assert_owned_key(
        'project_company_support_issue', 'issue_key', $issueKey, $companyHash, 'Issue'
    );
    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Support Issue SLA transaction could not start.');
    }
    try {
        yovel_admin_support_service_lock_company($db, $companyKey, $companyHash);
        $issue = $db->GetRow(
            'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? FOR UPDATE',
            [$companyHash, $issueKey]
        );
        if (!is_array($issue) || $issue === []) {
            throw new InvalidArgumentException('Issue does not belong to this company.');
        }
        if ((string) ($issue['service_level_agreement_key'] ?? '') !== '') {
            if ($db->CommitTrans() === false) {
                throw new RuntimeException('Support Issue SLA transaction could not commit.');
            }

            return $issue;
        }
        $saved = yovel_admin_support_sla_apply_created_in_transaction(
            $db, $company, $admin, $companyKey, $companyHash, $adminKey, $issue, yovel_admin_support_sla_now($now)
        );
        if ((string) ($saved['service_level_agreement_key'] ?? '') !== '') {
            $appliedSlaKey = (string) $saved['service_level_agreement_key'];
            $nextVersion = (int) $issue['issue_version'] + 1;
            yovel_admin_db_execute(
                $db,
                'UPDATE project_company_support_issue
                 SET issue_version = ?, updated_by_admin_key = ?
                 WHERE company_key_hash = ? AND issue_key = ?',
                [$nextVersion, $adminKey, $companyHash, $issueKey],
                'Support Issue standalone SLA apply version'
            );
            $saved = $db->GetRow(
                'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? LIMIT 1',
                [$companyHash, $issueKey]
            );
            yovel_admin_support_service_verify_read_back($saved, [
                'issue_version' => $nextVersion,
                'service_level_agreement_key' => $appliedSlaKey,
                'agreement_status' => 'FIRST_RESPONSE_DUE',
                'updated_by_admin_key' => $adminKey,
            ], 'Support Issue standalone SLA apply');
            bx_audit('SLA_APPLY', 'project_company_support_issue', $issueKey, [
                'company_key' => $companyKey,
                'company_key_hash' => $companyHash,
                'issue_version' => $nextVersion,
                'service_level_agreement_key' => $appliedSlaKey,
                'admin_key' => $adminKey,
            ], 'Support Issue SLA was applied by the created callback.');
        }
        if ($failureInjector !== null) {
            $failureInjector();
        }
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Support Issue SLA transaction could not commit.');
        }

        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_support_sla_on_communication(
    array $company,
    array $admin,
    string $issueKey,
    array $communication,
    ?DateTimeImmutable $now = null,
    ?callable $failureInjector = null
): array {
    yovel_admin_support_service_assert_no_secrets($communication);
    yovel_admin_support_service_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_support_service_scope($company, $admin);
    $issueKey = yovel_admin_support_service_optional_uuid(['issue_key' => $issueKey], 'issue_key', 'Issue');
    $direction = strtoupper(trim((string) ($communication['direction'] ?? '')));
    $actorType = strtoupper(trim((string) ($communication['actor_type'] ?? '')));
    $communicationKey = trim((string) ($communication['communication_key'] ?? ''));
    if ($direction !== 'SENT' || !in_array($actorType, ['ADMIN', 'AGENT'], true)) {
        throw new InvalidArgumentException('First-response tracking requires a sent agent communication.');
    }
    if ($communicationKey === '' || strlen($communicationKey) > 190) {
        throw new InvalidArgumentException('Communication key is required and must be bounded.');
    }
    $clock = yovel_admin_support_sla_now($now);
    yovel_admin_support_service_assert_owned_key(
        'project_company_support_issue', 'issue_key', $issueKey, $companyHash, 'Issue'
    );
    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Support first-response transaction could not start.');
    }
    try {
        yovel_admin_support_service_lock_company($db, $companyKey, $companyHash);
        $issue = $db->GetRow(
            'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? FOR UPDATE',
            [$companyHash, $issueKey]
        );
        if (!is_array($issue) || $issue === []) {
            throw new InvalidArgumentException('Issue does not belong to this company.');
        }
        if ((string) ($issue['service_level_agreement_key'] ?? '') === '') {
            throw new RuntimeException('Issue has no service level agreement.');
        }
        if ((string) ($issue['first_responded_on'] ?? '') !== '') {
            if ($db->CommitTrans() === false) {
                throw new RuntimeException('Support first-response transaction could not commit.');
            }

            return $issue;
        }
        $sla = yovel_admin_support_sla_load_for_issue($db, $companyHash, $issue);
        $holidayState = yovel_admin_support_sla_holiday_dates($company, $sla ?? []);
        $openedAt = new DateTimeImmutable((string) $issue['opened_at'], new DateTimeZone('UTC'));
        $seconds = yovel_admin_support_sla_business_seconds_between($sla ?? [], $openedAt, $clock, $holidayState['dates']);
        $late = $clock > new DateTimeImmutable((string) $issue['response_by'], new DateTimeZone('UTC'));
        $agreementStatus = $late
            ? 'FAILED'
            : ((int) ($sla['apply_for_resolution'] ?? 0) === 1 ? 'RESOLUTION_DUE' : 'FULFILLED');
        $nextVersion = (int) $issue['issue_version'] + 1;
        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_support_issue
             SET issue_version = ?, first_responded_on = ?, first_response_seconds = ?,
                 agreement_status = ?, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND issue_key = ?',
            [$nextVersion, $clock->format('Y-m-d H:i:s'), $seconds, $agreementStatus, $adminKey, $companyHash, $issueKey],
            'Support Issue first response'
        );
        yovel_admin_support_sla_write_event(
            $db, $companyKey, $companyHash, $adminKey, $issueKey, 'FIRST_RESPONSE',
            ['communication_key' => $communicationKey, 'first_response_seconds' => $seconds, 'agreement_status' => $agreementStatus],
            $clock
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? LIMIT 1',
            [$companyHash, $issueKey]
        );
        yovel_admin_support_service_verify_read_back($saved, [
            'issue_version' => $nextVersion,
            'first_responded_on' => $clock->format('Y-m-d H:i:s'),
            'first_response_seconds' => $seconds,
            'agreement_status' => $agreementStatus,
            'updated_by_admin_key' => $adminKey,
        ], 'Support Issue first response');
        bx_audit('FIRST_RESPONSE', 'project_company_support_issue', $issueKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'issue_version' => $nextVersion,
            'communication_key' => $communicationKey,
            'admin_key' => $adminKey,
        ], 'Support Issue first response was recorded.');
        if ($failureInjector !== null) {
            $failureInjector();
        }
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Support first-response transaction could not commit.');
        }

        return is_array($saved) ? $saved : [];
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_support_sla_apply_status_in_transaction(
    ADOConnection $db,
    array $company,
    string $companyKey,
    string $companyHash,
    string $adminKey,
    array $before,
    string $nextStatus,
    DateTimeImmutable $now
): array {
    $sla = yovel_admin_support_sla_load_for_issue($db, $companyHash, $before);
    if ($sla === null) {
        return $before;
    }
    $pauseStatuses = yovel_admin_support_sla_statuses($sla, 'pause_statuses');
    $fulfilledStatuses = yovel_admin_support_sla_statuses($sla, 'fulfilled_statuses');
    $previousStatus = strtoupper((string) $before['issue_status']);
    $wasPaused = isset($pauseStatuses[$previousStatus]);
    $isPaused = isset($pauseStatuses[$nextStatus]);
    $wasFulfilled = isset($fulfilledStatuses[$previousStatus]);
    $isFulfilled = isset($fulfilledStatuses[$nextStatus]);
    $onHoldSince = (string) ($before['on_hold_since'] ?? '');
    $totalHold = (int) ($before['total_hold_seconds'] ?? 0);
    $resolutionBy = (string) ($before['resolution_by'] ?? '');
    $resolutionDate = (string) ($before['resolution_date'] ?? '');
    $remaining = (string) ($before['sla_resolution_remaining_seconds'] ?? '');
    $agreementStatus = (string) ($before['agreement_status'] ?? '');

    if (!$wasPaused && $isPaused) {
        $onHoldSince = $now->format('Y-m-d H:i:s');
    } elseif ($wasPaused && !$isPaused) {
        $holdStarted = new DateTimeImmutable($onHoldSince, new DateTimeZone('UTC'));
        $heldSeconds = max(0, $now->getTimestamp() - $holdStarted->getTimestamp());
        $totalHold += $heldSeconds;
        $onHoldSince = '';
        if ($resolutionBy !== '') {
            $resolutionBy = (new DateTimeImmutable($resolutionBy, new DateTimeZone('UTC')))
                ->modify('+' . $heldSeconds . ' seconds')->format('Y-m-d H:i:s');
        }
    }
    $holidayState = yovel_admin_support_sla_holiday_dates($company, $sla);
    if (!$wasFulfilled && $isFulfilled) {
        $resolutionDate = $now->format('Y-m-d H:i:s');
        $remainingSeconds = $resolutionBy !== ''
            ? yovel_admin_support_sla_business_seconds_between(
                $sla,
                $now,
                new DateTimeImmutable($resolutionBy, new DateTimeZone('UTC')),
                $holidayState['dates']
            )
            : 0;
        $remaining = (string) $remainingSeconds;
        $agreementStatus = $resolutionBy === '' || $now <= new DateTimeImmutable($resolutionBy, new DateTimeZone('UTC'))
            ? 'FULFILLED'
            : 'FAILED';
    } elseif ($wasFulfilled && !$isFulfilled) {
        $resolutionDate = '';
        $remainingSeconds = max(1, (int) $remaining);
        $resolutionBy = yovel_admin_support_sla_expected_at($sla, $now, $remainingSeconds, $holidayState['dates'])
            ->format('Y-m-d H:i:s');
        $agreementStatus = (string) ($before['first_responded_on'] ?? '') !== ''
            ? 'RESOLUTION_DUE'
            : 'FIRST_RESPONSE_DUE';
    }
    yovel_admin_db_execute(
        $db,
        'UPDATE project_company_support_issue
         SET on_hold_since = ?, total_hold_seconds = ?, resolution_by = ?, resolution_date = ?,
             sla_resolution_remaining_seconds = ?, agreement_status = ?, updated_by_admin_key = ?
         WHERE company_key_hash = ? AND issue_key = ?',
        [
            $onHoldSince !== '' ? $onHoldSince : null,
            $totalHold,
            $resolutionBy !== '' ? $resolutionBy : null,
            $resolutionDate !== '' ? $resolutionDate : null,
            $remaining !== '' ? (int) $remaining : null,
            $agreementStatus !== '' ? $agreementStatus : null,
            $adminKey,
            $companyHash,
            (string) $before['issue_key'],
        ],
        'Support Issue SLA status change'
    );
    yovel_admin_support_sla_write_event(
        $db, $companyKey, $companyHash, $adminKey, (string) $before['issue_key'], 'SLA_STATUS_CHANGED',
        [
            'previous_status' => $previousStatus,
            'next_status' => $nextStatus,
            'agreement_status' => $agreementStatus,
            'holiday_dependency' => (string) $holidayState['status'],
        ],
        $now
    );

    $saved = $db->GetRow(
        'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? LIMIT 1',
        [$companyHash, (string) $before['issue_key']]
    );
    yovel_admin_support_service_verify_read_back($saved, [
        'issue_key' => (string) $before['issue_key'],
        'company_key_hash' => $companyHash,
        'on_hold_since' => $onHoldSince,
        'total_hold_seconds' => $totalHold,
        'resolution_by' => $resolutionBy,
        'resolution_date' => $resolutionDate,
        'sla_resolution_remaining_seconds' => $remaining,
        'agreement_status' => $agreementStatus,
        'updated_by_admin_key' => $adminKey,
    ], 'Support Issue SLA status change');

    return is_array($saved) ? $saved : [];
}

function yovel_admin_support_sla_on_status_change(
    array $company,
    array $admin,
    string $issueKey,
    string $nextStatus,
    array $input,
    ?DateTimeImmutable $now = null,
    ?callable $failureInjector = null
): array {
    return yovel_admin_transition_support_issue(
        $company,
        $admin,
        $issueKey,
        $nextStatus,
        $input,
        $failureInjector,
        $now
    );
}

function yovel_admin_reset_support_sla(
    array $company,
    array $admin,
    string $issueKey,
    array $input,
    ?DateTimeImmutable $now = null,
    ?callable $failureInjector = null
): array {
    yovel_admin_support_service_assert_no_secrets($input);
    yovel_admin_support_service_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_support_service_scope($company, $admin);
    $issueKey = yovel_admin_support_service_optional_uuid(['issue_key' => $issueKey], 'issue_key', 'Issue');
    $expectedVersion = yovel_admin_support_service_required_version($input);
    $reason = yovel_admin_support_service_text($input, 'reason', 1000, true);
    $clock = yovel_admin_support_sla_now($now);
    yovel_admin_support_service_assert_owned_key(
        'project_company_support_issue',
        'issue_key',
        $issueKey,
        $companyHash,
        'Issue'
    );
    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Support SLA reset transaction could not start.');
    }
    try {
        yovel_admin_support_service_lock_company($db, $companyKey, $companyHash);
        $settings = $db->GetRow(
            'SELECT allow_resetting_service_level_agreement FROM project_company_support_setting WHERE company_key_hash = ? FOR UPDATE',
            [$companyHash]
        );
        if (!is_array($settings) || (int) ($settings['allow_resetting_service_level_agreement'] ?? 0) !== 1) {
            throw new RuntimeException('Support SLA resetting is not enabled.');
        }
        $issue = $db->GetRow(
            'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? FOR UPDATE',
            [$companyHash, $issueKey]
        );
        if (!is_array($issue) || $issue === []) {
            throw new InvalidArgumentException('Issue does not belong to this company.');
        }
        if ((int) $issue['issue_version'] !== $expectedVersion) {
            throw new RuntimeException('Issue changed since this form was opened. Reload and try again.');
        }
        $sla = yovel_admin_support_sla_load_for_issue($db, $companyHash, $issue);
        if ($sla === null) {
            throw new RuntimeException('Issue has no service level agreement.');
        }
        $rule = yovel_admin_support_sla_priority_rule($sla, trim((string) ($issue['issue_priority_key'] ?? '')));
        $holidayState = yovel_admin_support_sla_holiday_dates($company, $sla);
        $responseBy = yovel_admin_support_sla_expected_at($sla, $clock, (int) $rule['response_seconds'], $holidayState['dates']);
        $resolutionBy = (int) $sla['apply_for_resolution'] === 1
            ? yovel_admin_support_sla_expected_at($sla, $clock, (int) $rule['resolution_seconds'], $holidayState['dates'])
            : null;
        $nextVersion = (int) $issue['issue_version'] + 1;
        yovel_admin_db_execute(
            $db,
            'UPDATE project_company_support_issue
             SET issue_version = ?, response_by = ?, first_responded_on = NULL,
                 first_response_seconds = NULL, resolution_by = ?, resolution_date = NULL,
                 on_hold_since = NULL, total_hold_seconds = 0,
                 sla_resolution_remaining_seconds = NULL, agreement_status = ?, updated_by_admin_key = ?
             WHERE company_key_hash = ? AND issue_key = ?',
            [
                $nextVersion, $responseBy->format('Y-m-d H:i:s'), $resolutionBy?->format('Y-m-d H:i:s'),
                'FIRST_RESPONSE_DUE', $adminKey, $companyHash, $issueKey,
            ],
            'Support SLA reset'
        );
        yovel_admin_support_sla_write_event(
            $db, $companyKey, $companyHash, $adminKey, $issueKey, 'SLA_RESET',
            ['reason' => $reason, 'response_by' => $responseBy->format('Y-m-d H:i:s'), 'resolution_by' => $resolutionBy?->format('Y-m-d H:i:s')],
            $clock
        );
        $saved = $db->GetRow(
            'SELECT * FROM project_company_support_issue WHERE company_key_hash = ? AND issue_key = ? LIMIT 1',
            [$companyHash, $issueKey]
        );
        yovel_admin_support_service_verify_read_back($saved, [
            'issue_version' => $nextVersion,
            'response_by' => $responseBy->format('Y-m-d H:i:s'),
            'resolution_by' => $resolutionBy?->format('Y-m-d H:i:s'),
            'first_responded_on' => '',
            'resolution_date' => '',
            'on_hold_since' => '',
            'total_hold_seconds' => 0,
            'agreement_status' => 'FIRST_RESPONSE_DUE',
            'updated_by_admin_key' => $adminKey,
        ], 'Support SLA reset');
        bx_audit('RESET', 'project_company_support_issue', $issueKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'issue_version' => $nextVersion,
            'service_level_agreement_key' => (string) $sla['service_level_agreement_key'],
            'admin_key' => $adminKey,
        ], $reason);
        if ($failureInjector !== null) {
            $failureInjector();
        }
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Support SLA reset transaction could not commit.');
        }

        return is_array($saved) ? $saved : [];
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}
