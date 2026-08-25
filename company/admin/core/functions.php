<?php
declare(strict_types=1);

function yovel_admin_requested_slug(): string
{
    if (defined('BUILDERX_COMPANY_ADMIN_SLUG')) {
        return bx_project_company_slug_candidate((string) BUILDERX_COMPANY_ADMIN_SLUG, 'company');
    }

    $requested = trim((string) ($_GET['company_slug'] ?? ''));
    if ($requested !== '') {
        return bx_project_company_slug_candidate($requested, 'company');
    }

    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $path = is_string($path) ? trim($path, '/') : '';
    if (preg_match('#(?:^|/)company/([^/]+)/admin/?$#', $path, $matches) === 1) {
        return bx_project_company_slug_candidate(rawurldecode($matches[1]), 'company');
    }

    return '';
}

function yovel_admin_redirect(): void
{
    header('Location: ./');
    exit;
}

function yovel_admin_redirect_to(string $query): void
{
    header('Location: ./' . ($query !== '' ? '?' . ltrim($query, '?') : ''));
    exit;
}

function yovel_admin_company(): ?array
{
    $slug = yovel_admin_requested_slug();
    if ($slug === '') {
        return null;
    }

    $company = bx_db()->GetRow(
        "SELECT company_key, company_key_hash, company_code, company_slug, company_name, company_status, company_email, company_phone, company_description
        FROM project_company
        WHERE company_slug = ? AND company_status = 'ACTIVE'
        LIMIT 1",
        [$slug]
    );

    return $company ?: null;
}

function yovel_admin_current(?array $company): ?array
{
    if (!$company || empty($_SESSION['builderx_company_admin_key'])) {
        return null;
    }

    $admin = bx_db()->GetRow(
        "SELECT admin_key, company_key_hash, admin_login, admin_name, admin_email, admin_status, admin_last_login_at
        FROM project_company_admin
        WHERE admin_key = ? AND company_key_hash = ? AND admin_status = 'ACTIVE'
        LIMIT 1",
        [$_SESSION['builderx_company_admin_key'], $company['company_key_hash']]
    );

    return $admin ?: null;
}

function yovel_admin_login(array $company, string $login, string $password): bool
{
    $db = bx_db();
    $identity = trim($login);
    if ($identity === '' || $password === '') {
        return false;
    }

    $admin = $db->GetRow(
        "SELECT *
        FROM project_company_admin
        WHERE company_key_hash = ? AND admin_login = ? AND admin_status IN ('ACTIVE','LOCKED')
        LIMIT 1",
        [$company['company_key_hash'], $identity]
    );
    if (!$admin || (string) $admin['admin_status'] === 'LOCKED') {
        return false;
    }

    if (!password_verify($password, (string) $admin['admin_password_hash'])) {
        $failed = (int) $admin['admin_failed_login_count'] + 1;
        $status = $failed >= 5 ? 'LOCKED' : 'ACTIVE';
        $db->BeginTrans();
        try {
            $updated = $db->Execute(
                'UPDATE project_company_admin SET admin_failed_login_count = ?, admin_status = ? WHERE admin_key = ? AND company_key_hash = ?',
                [$failed, $status, $admin['admin_key'], $company['company_key_hash']]
            );
            if ($updated === false) {
                $databaseError = trim((string) $db->ErrorMsg());
                throw new RuntimeException('Company admin failed-login update failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
            }
            $db->CommitTrans();
        } catch (Throwable $error) {
            $db->RollbackTrans();
            throw $error;
        }
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['builderx_company_admin_key'] = $admin['admin_key'];
    $_SESSION['builderx_company_admin_company_hash'] = $company['company_key_hash'];

    $db->BeginTrans();
    try {
        $updated = $db->Execute(
            'UPDATE project_company_admin SET admin_failed_login_count = 0, admin_last_login_at = CURRENT_TIMESTAMP WHERE admin_key = ? AND company_key_hash = ?',
            [$admin['admin_key'], $company['company_key_hash']]
        );
        if ($updated === false) {
            $databaseError = trim((string) $db->ErrorMsg());
            throw new RuntimeException('Company admin login update failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
        }
        bx_audit('LOGIN', 'project_company_admin', (string) $admin['admin_key'], [
            'company_code' => (string) $company['company_code'],
            'company_name' => (string) $company['company_name'],
            'admin_login' => (string) $admin['admin_login'],
        ], (string) $company['company_name'] . ' company administrator signed in.');
        $db->CommitTrans();
    } catch (Throwable $error) {
        $db->RollbackTrans();
        unset($_SESSION['builderx_company_admin_key'], $_SESSION['builderx_company_admin_company_hash']);
        throw $error;
    }

    return true;
}

function yovel_admin_logout(): void
{
    unset($_SESSION['builderx_company_admin_key'], $_SESSION['builderx_company_admin_company_hash']);
}

function yovel_admin_asset_entry(): array
{
    $manifestPath = dirname(__DIR__, 3) . '/frontend/dist/.vite/manifest.json';
    $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : [];
    $entry = is_array($manifest) ? ($manifest['index.html'] ?? []) : [];
    $css = is_array($entry) && isset($entry['css']) && is_array($entry['css']) ? $entry['css'] : [];

    return [
        'css' => $css,
        'base' => bx_project_base_path() . 'frontend/dist/',
    ];
}

function yovel_admin_view(): string
{
    $view = strtolower(trim((string) ($_GET['view'] ?? 'dashboard')));
    if (in_array($view, ['dashboard', 'platform'], true) || yovel_admin_module_route($view) !== null) {
        return $view;
    }

    return 'dashboard';
}

function yovel_admin_slug(string $value): string
{
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    return $slug !== '' ? $slug : 'item';
}

function yovel_admin_feature_anchor(array $group, string $feature): string
{
    return 'erp-' . yovel_admin_slug((string) $group['label']) . '-feature-' . yovel_admin_slug($feature);
}

function yovel_admin_erp_feature_href(array $group, string $feature): string
{
    $route = yovel_admin_module_route_by_label((string) ($group['label'] ?? ''));
    if ($route) {
        $featureSlug = yovel_admin_module_feature_section($route, $feature);
        return './?view=' . rawurlencode((string) $route['view']) . '&section=' . rawurlencode($featureSlug);
    }

    return './?view=dashboard#' . yovel_admin_feature_anchor($group, $feature);
}

function yovel_admin_erp_groups(): array
{
    return bx_project_erp_groups();
}

function yovel_admin_db_execute(ADOConnection $db, string $sql, array $params, string $operation): void
{
    $result = $db->Execute($sql, $params);
    if ($result === false) {
        $databaseError = trim((string) $db->ErrorMsg());
        throw new RuntimeException($operation . ' failed' . ($databaseError !== '' ? ': ' . $databaseError : '.'));
    }
}

function yovel_admin_code(string $value): string
{
    $code = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9_.-]+/', '_', $value), '_-.'));
    return $code;
}

function yovel_admin_post_array(string $name): array
{
    $value = $_POST[$name] ?? [];
    if (!is_array($value)) {
        return [];
    }

    $values = [];
    foreach ($value as $item) {
        $item = trim((string) $item);
        if ($item !== '') {
            $values[$item] = $item;
        }
    }

    return array_values($values);
}

function yovel_admin_status(string $value, array $allowed, string $default = 'ACTIVE'): string
{
    $status = strtoupper(trim($value));
    return in_array($status, $allowed, true) ? $status : $default;
}

function yovel_admin_is_uuid(string $value): bool
{
    return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $value) === 1;
}

function yovel_admin_existing_key(ADOConnection $db, string $table, string $keyColumn, string $companyKeyHash, string $key): bool
{
    if ($key === '') {
        return false;
    }

    return (int) $db->GetOne(
        "SELECT COUNT(*) FROM {$table} WHERE company_key_hash = ? AND {$keyColumn} = ? LIMIT 1",
        [$companyKeyHash, $key]
    ) > 0;
}

function yovel_admin_valid_keys(ADOConnection $db, string $table, string $keyColumn, string $companyKeyHash, array $keys): array
{
    $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
    if (!$keys) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $rows = $db->GetCol(
        "SELECT {$keyColumn} FROM {$table} WHERE company_key_hash = ? AND {$keyColumn} IN ({$placeholders})",
        array_merge([$companyKeyHash], $keys)
    );
    $rows = is_array($rows) ? array_map('strval', $rows) : [];
    sort($keys);
    sort($rows);
    if ($keys !== $rows) {
        throw new InvalidArgumentException('One or more selected access records do not belong to this company.');
    }

    return $rows;
}

function yovel_admin_optional_company_key(ADOConnection $db, string $table, string $keyColumn, string $companyKeyHash, string $key, string $label): string
{
    $key = trim($key);
    if ($key === '') {
        return '';
    }
    if (!yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Invalid ' . $label . ' selection.');
    }
    if (!yovel_admin_existing_key($db, $table, $keyColumn, $companyKeyHash, $key)) {
        throw new InvalidArgumentException('Selected ' . $label . ' does not belong to this company.');
    }

    return $key;
}

function yovel_admin_optional_date(string $value, string $label): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($label . ' must be a valid date.');
    }

    return $value;
}

function yovel_admin_replace_links(ADOConnection $db, string $table, string $companyKeyHash, string $sourceColumn, string $sourceKey, string $targetColumn, array $targetKeys): void
{
    yovel_admin_db_execute(
        $db,
        "DELETE FROM {$table} WHERE company_key_hash = ? AND {$sourceColumn} = ?",
        [$companyKeyHash, $sourceKey],
        'Access assignment reset'
    );

    foreach ($targetKeys as $targetKey) {
        yovel_admin_db_execute(
            $db,
            "INSERT IGNORE INTO {$table} (company_key_hash, {$sourceColumn}, {$targetColumn}) VALUES (?, ?, ?)",
            [$companyKeyHash, $sourceKey, $targetKey],
            'Access assignment save'
        );
    }
}

function yovel_admin_link_map(string $table, string $sourceColumn, string $targetColumn, string $companyKeyHash): array
{
    $rows = bx_db()->GetAll(
        "SELECT {$sourceColumn} AS source_key, {$targetColumn} AS target_key FROM {$table} WHERE company_key_hash = ?",
        [$companyKeyHash]
    );
    $map = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $source = (string) ($row['source_key'] ?? '');
        $target = (string) ($row['target_key'] ?? '');
        if ($source !== '' && $target !== '') {
            $map[$source][] = $target;
        }
    }

    return $map;
}

function yovel_admin_find_record(array $rows, string $keyColumn, string $key): ?array
{
    foreach ($rows as $row) {
        if ((string) ($row[$keyColumn] ?? '') === $key) {
            return $row;
        }
    }

    return null;
}

function yovel_admin_optional_decimal(string $value, string $label): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (!is_numeric($value)) {
        throw new InvalidArgumentException($label . ' must be a number.');
    }
    $number = (float) $value;
    if ($number < 0 || $number > 999999999999.99) {
        throw new InvalidArgumentException($label . ' is outside the allowed range.');
    }

    return number_format($number, 2, '.', '');
}
