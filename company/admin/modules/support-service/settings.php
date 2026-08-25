<?php
declare(strict_types=1);

function yovel_admin_support_service_assert_no_secrets(array $input, int $depth = 0): void
{
    if ($depth > 4) {
        throw new InvalidArgumentException('Support input nesting exceeds the safe limit.');
    }
    foreach ($input as $key => $value) {
        $key = strtolower(trim((string) $key));
        if ($key !== 'csrf' && preg_match('/(?:password|secret|api[_-]?key|token|auth(?:orization)?|credential|private[_-]?key)/i', $key) === 1) {
            throw new InvalidArgumentException('Support configuration must not contain secrets or credentials.');
        }
        if (is_array($value)) {
            yovel_admin_support_service_assert_no_secrets($value, $depth + 1);
        }
    }
}

function yovel_admin_support_service_input_bool(array $input, string $key): int
{
    return filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
}

function yovel_admin_support_service_required_version(array $input): int
{
    $version = filter_var(
        $input['expected_version'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
    );
    if ($version === false) {
        throw new InvalidArgumentException('A valid non-negative Support record version is required.');
    }

    return (int) $version;
}

function yovel_admin_support_service_text(array $input, string $key, int $maxLength, bool $required = false): string
{
    $value = trim((string) ($input[$key] ?? ''));
    if ($required && $value === '') {
        throw new InvalidArgumentException(str_replace('_', ' ', ucfirst($key)) . ' is required.');
    }
    if (strlen($value) > $maxLength) {
        throw new InvalidArgumentException(str_replace('_', ' ', ucfirst($key)) . ' exceeds the allowed length.');
    }

    return $value;
}

function yovel_admin_support_settings_defaults(): array
{
    return [
        'support_setting_key' => '',
        'company_key' => '',
        'company_key_hash' => '',
        'setting_version' => 0,
        'close_issue_after_days' => 7,
        'portal_enabled' => 1,
        'track_service_level_agreement' => 0,
        'allow_resetting_service_level_agreement' => 0,
        'greeting_title' => 'We are here to help',
        'greeting_subtitle' => 'Browse help topics or open a request',
        'operations_search_contract' => 'operations.support-search.v1',
        'operations_job_contract' => 'operations.enqueue-job.v1',
    ];
}

function yovel_admin_support_settings(array $company, ?array $admin = null): array
{
    yovel_admin_support_service_schema();
    if ($admin !== null) {
        yovel_admin_support_service_scope($company, $admin);
    }
    $companyHash = strtolower((string) ($company['company_key_hash'] ?? ''));
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_support_setting WHERE company_key_hash = ? LIMIT 1',
        [$companyHash]
    );

    return is_array($row) && $row !== []
        ? array_merge(yovel_admin_support_settings_defaults(), $row)
        : yovel_admin_support_settings_defaults();
}

function yovel_admin_save_support_settings(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    yovel_admin_support_service_assert_no_secrets($input);
    yovel_admin_support_service_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_support_service_scope($company, $admin);
    $expectedVersion = yovel_admin_support_service_required_version($input);
    $days = filter_var(
        $input['close_issue_after_days'] ?? 7,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 3650]]
    );
    if ($days === false) {
        throw new InvalidArgumentException('Close issue after days must be between 0 and 3650.');
    }
    $portalEnabled = yovel_admin_support_service_input_bool($input, 'portal_enabled');
    $trackSla = yovel_admin_support_service_input_bool($input, 'track_service_level_agreement');
    $allowReset = yovel_admin_support_service_input_bool($input, 'allow_resetting_service_level_agreement');
    $greetingTitle = yovel_admin_support_service_text($input, 'greeting_title', 160, true);
    $greetingSubtitle = yovel_admin_support_service_text($input, 'greeting_subtitle', 255, true);
    $db = bx_db();
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Support settings transaction could not start.');
    }

    try {
        $current = $db->GetRow(
            'SELECT * FROM project_company_support_setting WHERE company_key_hash = ? FOR UPDATE',
            [$companyHash]
        );
        $current = is_array($current) && $current !== [] ? $current : null;
        $currentVersion = (int) ($current['setting_version'] ?? 0);
        if ($currentVersion !== $expectedVersion) {
            throw new RuntimeException('Support settings changed since this form was opened. Reload and try again.');
        }
        $settingsKey = $current ? (string) $current['support_setting_key'] : bx_uuid();
        $nextVersion = $currentVersion + 1;
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_support_setting (
                support_setting_key, company_key, company_key_hash, setting_version,
                close_issue_after_days, portal_enabled, track_service_level_agreement,
                allow_resetting_service_level_agreement, greeting_title, greeting_subtitle,
                operations_search_contract, operations_job_contract,
                created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'operations.support-search.v1', 'operations.enqueue-job.v1', ?, ?)
             ON DUPLICATE KEY UPDATE
                setting_version = VALUES(setting_version),
                close_issue_after_days = VALUES(close_issue_after_days),
                portal_enabled = VALUES(portal_enabled),
                track_service_level_agreement = VALUES(track_service_level_agreement),
                allow_resetting_service_level_agreement = VALUES(allow_resetting_service_level_agreement),
                greeting_title = VALUES(greeting_title),
                greeting_subtitle = VALUES(greeting_subtitle),
                operations_search_contract = VALUES(operations_search_contract),
                operations_job_contract = VALUES(operations_job_contract),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $settingsKey,
                $companyKey,
                $companyHash,
                $nextVersion,
                (int) $days,
                $portalEnabled,
                $trackSla,
                $allowReset,
                $greetingTitle,
                $greetingSubtitle,
                $adminKey,
                $adminKey,
            ],
            'Support settings save'
        );
        if ($failureInjector !== null) {
            $failureInjector();
        }
        $saved = $db->GetRow(
            "SELECT support_setting_key, company_key, company_key_hash, setting_version,
                    close_issue_after_days, portal_enabled, track_service_level_agreement,
                    allow_resetting_service_level_agreement, greeting_title, greeting_subtitle,
                    operations_search_contract, operations_job_contract,
                    created_by_admin_key, updated_by_admin_key
             FROM project_company_support_setting
             WHERE company_key_hash = ? AND support_setting_key = ? LIMIT 1",
            [$companyHash, $settingsKey]
        );
        $expected = [
            'support_setting_key' => $settingsKey,
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'setting_version' => $nextVersion,
            'close_issue_after_days' => (int) $days,
            'portal_enabled' => $portalEnabled,
            'track_service_level_agreement' => $trackSla,
            'allow_resetting_service_level_agreement' => $allowReset,
            'greeting_title' => $greetingTitle,
            'greeting_subtitle' => $greetingSubtitle,
            'operations_search_contract' => 'operations.support-search.v1',
            'operations_job_contract' => 'operations.enqueue-job.v1',
            'created_by_admin_key' => (string) ($current['created_by_admin_key'] ?? $adminKey),
            'updated_by_admin_key' => $adminKey,
        ];
        yovel_admin_support_service_verify_read_back($saved, $expected, 'Support settings');
        bx_audit($current ? 'UPDATE' : 'CREATE', 'project_company_support_setting', $settingsKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'setting_version' => $nextVersion,
            'close_issue_after_days' => (int) $days,
            'portal_enabled' => $portalEnabled,
            'track_service_level_agreement' => $trackSla,
            'allow_resetting_service_level_agreement' => $allowReset,
            'admin_key' => $adminKey,
        ], 'Company administrator changed Support / Service settings.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Support settings transaction could not commit.');
        }

        return is_array($saved) ? array_merge(yovel_admin_support_settings_defaults(), $saved) : [];
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_support_service_safe_route(string $route, string $label, bool $required = false): string
{
    $route = trim($route);
    if ($route === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' requires a safe relative route.');
        }
        return '';
    }
    $decoded = $route;
    for ($pass = 0; $pass < 3; $pass++) {
        $next = rawurldecode($decoded);
        if ($next === $decoded) {
            break;
        }
        $decoded = $next;
    }
    $safe = preg_match('/^[A-Za-z0-9._~{}%\/-]{1,255}$/D', $route) === 1
        && preg_match('/%(?![0-9A-Fa-f]{2})/', $route) !== 1
        && !str_starts_with($route, '/')
        && !str_starts_with($decoded, '/')
        && !str_contains($decoded, '..')
        && !str_contains($decoded, '//')
        && !str_contains($decoded, '\\')
        && preg_match('/[\x00-\x1F\x7F]/', $decoded) !== 1;
    if (!$safe) {
        throw new InvalidArgumentException($label . ' must be a safe relative route.');
    }

    return $route;
}

function yovel_admin_support_service_safe_base_url(string $url): string
{
    $url = rtrim(trim($url), '/');
    $parts = $url !== '' ? parse_url($url) : false;
    $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
    $port = is_array($parts) ? ($parts['port'] ?? null) : null;
    $unsafeHost = $host === ''
        || $host === 'localhost'
        || str_ends_with($host, '.localhost')
        || str_ends_with($host, '.local')
        || (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);
    $safe = is_array($parts)
        && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
        && !$unsafeHost
        && !array_key_exists('user', $parts)
        && !array_key_exists('pass', $parts)
        && !array_key_exists('query', $parts)
        && !array_key_exists('fragment', $parts)
        && ($port === null || (int) $port === 443);
    if (!$safe) {
        throw new InvalidArgumentException('API search source requires a safe public HTTPS base URL without credentials, query strings, or fragments.');
    }
    $path = (string) ($parts['path'] ?? '');
    if ($path !== '') {
        yovel_admin_support_service_safe_route(ltrim($path, '/'), 'API base URL path');
    }

    return $url;
}

function yovel_admin_support_service_safe_identifier(string $value, string $label, bool $required = false): string
{
    $value = trim($value);
    if ($value === '' && !$required) {
        return '';
    }
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_ ]{0,159}$/D', $value) !== 1) {
        throw new InvalidArgumentException($label . ' must use a safe identifier.');
    }

    return $value;
}

function yovel_admin_support_service_safe_key_path(string $value, string $label, bool $required = false): string
{
    $value = trim($value);
    if ($value === '' && !$required) {
        return '';
    }
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/D', $value) !== 1 || strlen($value) > 255) {
        throw new InvalidArgumentException($label . ' must use a safe dotted key path.');
    }

    return $value;
}

function yovel_admin_support_service_route_key_list(mixed $input): string
{
    $values = is_array($input) ? $input : explode(',', (string) $input);
    $keys = [];
    foreach ($values as $value) {
        $key = trim((string) $value);
        if ($key === '') {
            continue;
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,79}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Post route key list contains an unsafe key.');
        }
        $keys[$key] = true;
    }
    $keys = array_keys($keys);
    sort($keys, SORT_STRING);

    return implode(',', $keys);
}

function yovel_admin_support_search_sources(array $company, ?array $admin = null): array
{
    yovel_admin_support_service_schema();
    if ($admin !== null) {
        yovel_admin_support_service_scope($company, $admin);
    }
    $rows = bx_db()->GetAll(
        "SELECT * FROM project_company_support_search_source
         WHERE company_key_hash = ? AND source_status <> 'ARCHIVED'
         ORDER BY source_name, x_id",
        [strtolower((string) ($company['company_key_hash'] ?? ''))]
    );

    return array_map('yovel_admin_support_service_normalize_search_row', is_array($rows) ? $rows : []);
}

function yovel_admin_support_search_source(array $company, array $admin, string $searchSourceKey): array
{
    yovel_admin_support_service_schema();
    [, $companyHash] = yovel_admin_support_service_scope($company, $admin);
    if (!yovel_admin_is_uuid($searchSourceKey)) {
        return [];
    }
    $row = bx_db()->GetRow(
        'SELECT * FROM project_company_support_search_source WHERE company_key_hash = ? AND search_source_key = ? LIMIT 1',
        [$companyHash, $searchSourceKey]
    );

    return is_array($row) && $row !== [] ? yovel_admin_support_service_normalize_search_row($row) : [];
}

function yovel_admin_support_service_normalize_search_row(array $row): array
{
    foreach ([
        'base_url', 'query_route', 'search_term_param_name', 'response_result_key_path',
        'post_route', 'post_route_key_list', 'post_title_key', 'post_description_key',
        'source_doctype', 'result_title_field', 'result_preview_field', 'result_route_field',
    ] as $column) {
        $row[$column] = (string) ($row[$column] ?? '');
    }

    return $row;
}

function yovel_admin_save_support_search_source(
    array $company,
    array $admin,
    array $input,
    ?callable $failureInjector = null
): array {
    yovel_admin_support_service_assert_no_secrets($input);
    yovel_admin_support_service_schema();
    [$companyKey, $companyHash, $adminKey] = yovel_admin_support_service_scope($company, $admin);
    $expectedVersion = yovel_admin_support_service_required_version($input);
    $searchSourceKey = trim((string) ($input['search_source_key'] ?? ''));
    if ($searchSourceKey !== '' && !yovel_admin_is_uuid($searchSourceKey)) {
        throw new InvalidArgumentException('Support search source key is invalid.');
    }
    $db = bx_db();
    if ($searchSourceKey !== '') {
        $ownerHash = $db->GetOne(
            'SELECT company_key_hash FROM project_company_support_search_source WHERE search_source_key = ? LIMIT 1',
            [$searchSourceKey]
        );
        if (!is_string($ownerHash) || $ownerHash === '') {
            throw new InvalidArgumentException('Support search source was not found.');
        }
        if (!hash_equals($companyHash, strtolower($ownerHash))) {
            throw new InvalidArgumentException('Support search source belongs to another company.');
        }
    }
    $settings = yovel_admin_support_settings($company, $admin);
    if ((string) $settings['support_setting_key'] === '') {
        throw new InvalidArgumentException('Save Support settings before adding search sources.');
    }
    $sourceName = yovel_admin_support_service_text($input, 'source_name', 160, true);
    $sourceType = strtoupper(trim((string) ($input['source_type'] ?? '')));
    if (!in_array($sourceType, ['API', 'LINK'], true)) {
        throw new InvalidArgumentException('Support search source type must be API or Link.');
    }
    $sourceStatus = strtoupper(trim((string) ($input['source_status'] ?? 'ACTIVE')));
    if (!in_array($sourceStatus, ['ACTIVE', 'INACTIVE', 'ARCHIVED'], true)) {
        throw new InvalidArgumentException('Support search source status is invalid.');
    }
    $values = [
        'base_url' => '',
        'query_route' => '',
        'search_term_param_name' => '',
        'response_result_key_path' => '',
        'post_route' => '',
        'post_route_key_list' => '',
        'post_title_key' => '',
        'post_description_key' => '',
        'source_doctype' => '',
        'result_title_field' => '',
        'result_preview_field' => '',
        'result_route_field' => '',
    ];
    if ($sourceType === 'API') {
        $values['base_url'] = yovel_admin_support_service_safe_base_url((string) ($input['base_url'] ?? ''));
        $values['query_route'] = yovel_admin_support_service_safe_route((string) ($input['query_route'] ?? ''), 'API query route', true);
        $values['search_term_param_name'] = yovel_admin_support_service_safe_key_path((string) ($input['search_term_param_name'] ?? ''), 'Search term parameter', true);
        $values['response_result_key_path'] = yovel_admin_support_service_safe_key_path((string) ($input['response_result_key_path'] ?? ''), 'Response result key path', true);
        $values['post_route'] = yovel_admin_support_service_safe_route((string) ($input['post_route'] ?? ''), 'API post route');
        $values['post_route_key_list'] = yovel_admin_support_service_route_key_list($input['post_route_key_list'] ?? '');
        $values['post_title_key'] = yovel_admin_support_service_safe_key_path((string) ($input['post_title_key'] ?? ''), 'Post title key', true);
        $values['post_description_key'] = yovel_admin_support_service_safe_key_path((string) ($input['post_description_key'] ?? ''), 'Post description key');
    } else {
        $values['source_doctype'] = yovel_admin_support_service_safe_identifier((string) ($input['source_doctype'] ?? ''), 'Source record type', true);
        $values['result_title_field'] = yovel_admin_support_service_safe_key_path((string) ($input['result_title_field'] ?? ''), 'Result title field', true);
        $values['result_preview_field'] = yovel_admin_support_service_safe_key_path((string) ($input['result_preview_field'] ?? ''), 'Result preview field');
        $values['result_route_field'] = yovel_admin_support_service_safe_key_path((string) ($input['result_route_field'] ?? ''), 'Result route field', true);
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Support search-source transaction could not start.');
    }

    try {
        $current = $searchSourceKey !== ''
            ? $db->GetRow(
                'SELECT * FROM project_company_support_search_source WHERE company_key_hash = ? AND search_source_key = ? FOR UPDATE',
                [$companyHash, $searchSourceKey]
            )
            : $db->GetRow(
                'SELECT * FROM project_company_support_search_source WHERE company_key_hash = ? AND source_name = ? FOR UPDATE',
                [$companyHash, $sourceName]
            );
        $current = is_array($current) && $current !== [] ? $current : null;
        $currentVersion = (int) ($current['source_version'] ?? 0);
        if ($currentVersion !== $expectedVersion) {
            throw new RuntimeException('Support search source changed since this form was opened. Reload and try again.');
        }
        $stableKey = $current ? (string) $current['search_source_key'] : bx_uuid();
        $nextVersion = $currentVersion + 1;
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_support_search_source (
                search_source_key, support_setting_key, company_key, company_key_hash,
                source_version, source_name, source_type, base_url, query_route,
                search_term_param_name, response_result_key_path, post_route,
                post_route_key_list, post_title_key, post_description_key, source_doctype,
                result_title_field, result_preview_field, result_route_field, source_status,
                created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                source_version = VALUES(source_version), source_name = VALUES(source_name),
                source_type = VALUES(source_type), base_url = VALUES(base_url),
                query_route = VALUES(query_route), search_term_param_name = VALUES(search_term_param_name),
                response_result_key_path = VALUES(response_result_key_path), post_route = VALUES(post_route),
                post_route_key_list = VALUES(post_route_key_list), post_title_key = VALUES(post_title_key),
                post_description_key = VALUES(post_description_key), source_doctype = VALUES(source_doctype),
                result_title_field = VALUES(result_title_field), result_preview_field = VALUES(result_preview_field),
                result_route_field = VALUES(result_route_field), source_status = VALUES(source_status),
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [
                $stableKey,
                (string) $settings['support_setting_key'],
                $companyKey,
                $companyHash,
                $nextVersion,
                $sourceName,
                $sourceType,
                $values['base_url'] !== '' ? $values['base_url'] : null,
                $values['query_route'] !== '' ? $values['query_route'] : null,
                $values['search_term_param_name'] !== '' ? $values['search_term_param_name'] : null,
                $values['response_result_key_path'] !== '' ? $values['response_result_key_path'] : null,
                $values['post_route'] !== '' ? $values['post_route'] : null,
                $values['post_route_key_list'] !== '' ? $values['post_route_key_list'] : null,
                $values['post_title_key'] !== '' ? $values['post_title_key'] : null,
                $values['post_description_key'] !== '' ? $values['post_description_key'] : null,
                $values['source_doctype'] !== '' ? $values['source_doctype'] : null,
                $values['result_title_field'] !== '' ? $values['result_title_field'] : null,
                $values['result_preview_field'] !== '' ? $values['result_preview_field'] : null,
                $values['result_route_field'] !== '' ? $values['result_route_field'] : null,
                $sourceStatus,
                $adminKey,
                $adminKey,
            ],
            'Support search source save'
        );
        if ($failureInjector !== null) {
            $failureInjector();
        }
        $saved = $db->GetRow(
            'SELECT * FROM project_company_support_search_source WHERE company_key_hash = ? AND search_source_key = ? LIMIT 1',
            [$companyHash, $stableKey]
        );
        $expected = array_merge($values, [
            'search_source_key' => $stableKey,
            'support_setting_key' => (string) $settings['support_setting_key'],
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'source_version' => $nextVersion,
            'source_name' => $sourceName,
            'source_type' => $sourceType,
            'source_status' => $sourceStatus,
            'created_by_admin_key' => (string) ($current['created_by_admin_key'] ?? $adminKey),
            'updated_by_admin_key' => $adminKey,
        ]);
        $normalizedSaved = is_array($saved) ? yovel_admin_support_service_normalize_search_row($saved) : [];
        yovel_admin_support_service_verify_read_back($normalizedSaved, $expected, 'Support search source');
        bx_audit($current ? 'UPDATE' : 'CREATE', 'project_company_support_search_source', $stableKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyHash,
            'source_version' => $nextVersion,
            'source_name' => $sourceName,
            'source_type' => $sourceType,
            'source_status' => $sourceStatus,
            'admin_key' => $adminKey,
        ], 'Company administrator changed a Support portal search source.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Support search-source transaction could not commit.');
        }

        return $normalizedSaved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}
