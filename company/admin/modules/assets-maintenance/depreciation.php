<?php
declare(strict_types=1);

function yovel_admin_assets_money_minor(mixed $value, string $label, bool $positive = false): int
{
    $text = trim((string) $value);
    if ($text === '' || preg_match('/^\d+(?:\.\d{1,6})?$/', $text) !== 1) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
    $fraction = str_pad($fraction, 3, '0');
    $minor = ((int) $whole * 100) + (int) substr($fraction, 0, 2);
    if ((int) $fraction[2] >= 5) {
        $minor++;
    }
    if ($minor < 0 || ($positive && $minor < 1)) {
        throw new InvalidArgumentException($label . ($positive ? ' must be greater than zero.' : ' cannot be negative.'));
    }
    return $minor;
}

function yovel_admin_assets_minor_money(int|string $minor, int $scale = 2): string
{
    return number_format(((int) $minor) / 100, $scale, '.', '');
}

function yovel_admin_assets_depreciation_method(mixed $value): string
{
    $method = strtoupper(trim((string) $value));
    if (!in_array($method, ['STRAIGHT_LINE', 'DOUBLE_DECLINING', 'WRITTEN_DOWN_VALUE', 'MANUAL'], true)) {
        throw new InvalidArgumentException('Depreciation method is invalid.');
    }
    return $method;
}

function yovel_admin_assets_month_date(string $startDate, int $months): string
{
    $start = new DateTimeImmutable($startDate);
    $day = (int) $start->format('d');
    $monthStart = $start->modify('first day of this month')->modify('+' . $months . ' months');
    $lastDay = (int) $monthStart->format('t');
    return $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), min($day, $lastDay))->format('Y-m-d');
}

function yovel_admin_assets_calculate_schedule(array $terms): array
{
    $method = yovel_admin_assets_depreciation_method($terms['method'] ?? '');
    $gross = yovel_admin_assets_money_minor($terms['gross_amount'] ?? '', 'Gross amount', true);
    $salvage = yovel_admin_assets_money_minor($terms['salvage_value'] ?? '0', 'Salvage value');
    $openingDepreciation = yovel_admin_assets_money_minor($terms['opening_depreciation'] ?? '0', 'Opening depreciation');
    $periodCount = filter_var($terms['period_count'] ?? null, FILTER_VALIDATE_INT);
    $frequencyMonths = filter_var($terms['frequency_months'] ?? null, FILTER_VALIDATE_INT);
    if ($periodCount === false || $periodCount < 1 || $periodCount > 1000) {
        throw new InvalidArgumentException('Depreciation period count must be between 1 and 1000.');
    }
    if ($frequencyMonths === false || $frequencyMonths < 1 || $frequencyMonths > 120) {
        throw new InvalidArgumentException('Depreciation frequency must be between 1 and 120 months.');
    }
    $startDate = yovel_admin_assets_date($terms['start_date'] ?? '', 'Depreciation start date');
    if ($salvage >= $gross || $openingDepreciation > ($gross - $salvage)) {
        throw new InvalidArgumentException('Salvage and opening depreciation exceed the depreciable amount.');
    }
    $depreciable = $gross - $salvage - $openingDepreciation;
    $dailyProrata = !empty($terms['daily_prorata']);
    $rateBasisPoints = (int) ($terms['rate_basis_points'] ?? 0);
    if ($method === 'WRITTEN_DOWN_VALUE' && ($rateBasisPoints < 1 || $rateBasisPoints > 9999)) {
        throw new InvalidArgumentException('Written-down-value rate must be between 1 and 9999 basis points.');
    }

    $amounts = [];
    $postingDates = [];
    if ($method === 'MANUAL') {
        $manualLines = is_array($terms['manual_lines'] ?? null) ? array_values($terms['manual_lines']) : [];
        if (count($manualLines) !== $periodCount) {
            throw new InvalidArgumentException('Manual depreciation rows must match the period count.');
        }
        $previousDate = '';
        foreach ($manualLines as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Manual depreciation row is invalid.');
            }
            $date = yovel_admin_assets_date($row['posting_date'] ?? '', 'Manual depreciation posting date');
            if ($previousDate !== '' && $date <= $previousDate) {
                throw new InvalidArgumentException('Manual depreciation dates must be strictly chronological.');
            }
            $postingDates[] = $date;
            $amounts[] = yovel_admin_assets_money_minor($row['amount'] ?? '', 'Manual depreciation amount', true);
            $previousDate = $date;
        }
        if (array_sum($amounts) !== $depreciable) {
            throw new InvalidArgumentException('Manual depreciation must equal the exact depreciable amount.');
        }
    } else {
        for ($index = 0; $index < $periodCount; $index++) {
            $postingDates[] = yovel_admin_assets_month_date($startDate, $index * $frequencyMonths);
        }
        if ($method === 'STRAIGHT_LINE') {
            if ($dailyProrata && $periodCount > 1) {
                $start = new DateTimeImmutable($startDate);
                $daysInMonth = (int) $start->format('t');
                $activeDays = $daysInMonth - (int) $start->format('d') + 1;
                $normal = intdiv($depreciable, $periodCount);
                $first = max(1, (int) round($normal * ($activeDays / $daysInMonth), 0, PHP_ROUND_HALF_UP));
                $amounts[] = min($first, $depreciable);
                $remaining = $depreciable - $amounts[0];
                $base = intdiv($remaining, $periodCount - 1);
                $remainder = $remaining % ($periodCount - 1);
                for ($index = 1; $index < $periodCount; $index++) {
                    $amounts[] = $base + (($index - 1) < $remainder ? 1 : 0);
                }
            } else {
                $base = intdiv($depreciable, $periodCount);
                $remainder = $depreciable % $periodCount;
                for ($index = 0; $index < $periodCount; $index++) {
                    $amounts[] = $base + ($index < $remainder ? 1 : 0);
                }
            }
        } else {
            $remaining = $depreciable;
            $bookValue = $gross - $openingDepreciation;
            for ($index = 0; $index < $periodCount; $index++) {
                if ($index === $periodCount - 1) {
                    $amount = $remaining;
                } elseif ($method === 'DOUBLE_DECLINING') {
                    $amount = (int) round($bookValue * (2 / $periodCount), 0, PHP_ROUND_HALF_UP);
                } else {
                    $amount = (int) round($bookValue * ($rateBasisPoints / 10000), 0, PHP_ROUND_HALF_UP);
                }
                $amount = max(0, min($amount, $remaining));
                $amounts[] = $amount;
                $remaining -= $amount;
                $bookValue -= $amount;
            }
        }
    }

    $lines = [];
    $accumulated = $openingDepreciation;
    $openingValue = $gross - $openingDepreciation;
    foreach ($amounts as $index => $amount) {
        $closingValue = $openingValue - $amount;
        if ($closingValue < $salvage) {
            throw new RuntimeException('Depreciation calculation crossed the salvage floor.');
        }
        $accumulated += $amount;
        $lines[] = [
            'line_no' => $index + 1,
            'posting_date' => $postingDates[$index],
            'opening_value_minor' => $openingValue,
            'opening_value' => yovel_admin_assets_minor_money($openingValue),
            'depreciation_amount_minor' => $amount,
            'depreciation_amount' => yovel_admin_assets_minor_money($amount),
            'accumulated_depreciation_minor' => $accumulated,
            'accumulated_depreciation' => yovel_admin_assets_minor_money($accumulated),
            'closing_value_minor' => $closingValue,
            'closing_value' => yovel_admin_assets_minor_money($closingValue),
        ];
        $openingValue = $closingValue;
    }
    if ($openingValue !== $salvage || array_sum($amounts) !== $depreciable) {
        throw new RuntimeException('Depreciation calculation did not reach the exact salvage value.');
    }
    $checksum = hash('sha256', json_encode([
        $method, $gross, $salvage, $openingDepreciation, $periodCount, $frequencyMonths,
        $dailyProrata, $rateBasisPoints, $lines,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    return [
        'method' => $method,
        'gross_amount_minor' => $gross,
        'gross_amount' => yovel_admin_assets_minor_money($gross),
        'salvage_value_minor' => $salvage,
        'salvage_value' => yovel_admin_assets_minor_money($salvage),
        'opening_depreciation_minor' => $openingDepreciation,
        'opening_depreciation' => yovel_admin_assets_minor_money($openingDepreciation),
        'total_depreciation_minor' => $depreciable,
        'total_depreciation' => yovel_admin_assets_minor_money($depreciable),
        'closing_value_minor' => $openingValue,
        'closing_value' => yovel_admin_assets_minor_money($openingValue),
        'period_count' => $periodCount,
        'frequency_months' => $frequencyMonths,
        'daily_prorata' => $dailyProrata,
        'rate_basis_points' => $rateBasisPoints,
        'calculation_checksum' => $checksum,
        'lines' => $lines,
    ];
}

function yovel_admin_assets_finance_audit(array $scope, string $action, string $module, string $recordKey, array $details): void
{
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    bx_audit($action, $module, $recordKey, [
        'company_key' => $companyKey,
        'company_key_hash' => $companyKeyHash,
        'admin_key' => $adminKey,
    ] + $details, 'Company administrator changed an Assets depreciation record.');
}

function yovel_admin_assets_finance_book(array $company, string $financeBookKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($financeBookKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_asset_finance_book WHERE company_key=? AND company_key_hash=? AND finance_book_key=? LIMIT 1', [$companyKey, $companyKeyHash, $financeBookKey]);
    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_assets_finance_books(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $rows = bx_db()->GetAll('SELECT book.*,asset.asset_code,asset.asset_name FROM project_company_asset_finance_book book INNER JOIN project_company_asset asset ON asset.company_key_hash=book.company_key_hash AND asset.asset_key=book.asset_key WHERE book.company_key=? AND book.company_key_hash=? ORDER BY asset.asset_code,book.book_code', [$companyKey, $companyKeyHash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_save_finance_book(array $company, array $admin, array $input, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    yovel_admin_assets_dependency_services($services);
    $requestedKey = yovel_admin_assets_local_key($input['finance_book_key'] ?? '', 'Finance Book key', false);
    $assetKey = yovel_admin_assets_local_key($input['asset_key'] ?? '', 'Asset key');
    $bookCode = yovel_admin_assets_code($input['book_code'] ?? '', 'Finance Book code', 80);
    $bookName = yovel_admin_assets_text($input['book_name'] ?? '', 'Finance Book name', 180, true);
    $method = yovel_admin_assets_depreciation_method($input['method'] ?? '');
    $gross = yovel_admin_assets_money_minor($input['gross_amount'] ?? '', 'Gross amount', true);
    $salvage = yovel_admin_assets_money_minor($input['salvage_value'] ?? '0', 'Salvage value');
    $opening = yovel_admin_assets_money_minor($input['opening_depreciation'] ?? '0', 'Opening depreciation');
    $periods = filter_var($input['period_count'] ?? null, FILTER_VALIDATE_INT);
    $frequency = filter_var($input['frequency_months'] ?? null, FILTER_VALIDATE_INT);
    if ($periods === false || $periods < 1 || $periods > 1000 || $frequency === false || $frequency < 1 || $frequency > 120) {
        throw new InvalidArgumentException('Finance Book period terms are invalid.');
    }
    if ($salvage >= $gross || $opening > ($gross - $salvage)) {
        throw new InvalidArgumentException('Finance Book salvage and opening depreciation are invalid.');
    }
    $daily = !empty($input['daily_prorata']) ? 1 : 0;
    $rate = (int) ($input['rate_basis_points'] ?? 0);
    if ($method === 'WRITTEN_DOWN_VALUE' && ($rate < 1 || $rate > 9999)) {
        throw new InvalidArgumentException('Finance Book written-down-value rate is invalid.');
    }
    $status = strtoupper(trim((string) ($input['book_status'] ?? 'ACTIVE')));
    if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
        throw new InvalidArgumentException('Finance Book status is invalid.');
    }
    $checksum = hash('sha256', implode('|', [$companyKeyHash, $assetKey, $bookCode, $method, $gross, $salvage, $opening, $periods, $frequency, $daily, $rate]));

    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $requestedKey, $assetKey, $bookCode, $bookName, $method, $gross, $salvage, $opening, $periods, $frequency, $daily, $rate, $status, $checksum): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $asset = $db->GetRow('SELECT asset_key,gross_purchase_amount,document_status FROM project_company_asset WHERE company_key_hash=? AND asset_key=? FOR UPDATE', [$companyKeyHash, $assetKey]);
        if (!is_array($asset) || $asset === [] || (string) $asset['document_status'] !== 'SUBMITTED') {
            throw new InvalidArgumentException('Finance Book requires a submitted company Asset.');
        }
        $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_asset_finance_book WHERE company_key_hash=? AND finance_book_key=? FOR UPDATE', [$companyKeyHash, $requestedKey]) : [];
        if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Finance Book was not found for this company.');
        }
        if (is_array($existing) && $existing !== [] && (int) $db->GetOne("SELECT COUNT(*) FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND finance_book_key=? AND document_status IN ('DRAFT','SUBMITTED')", [$companyKeyHash, $requestedKey]) > 0) {
            throw new InvalidArgumentException('Finance Book terms are immutable while an active schedule exists.');
        }
        $financeBookKey = is_array($existing) && $existing !== [] ? (string) $existing['finance_book_key'] : bx_uuid();
        if (is_array($existing) && $existing !== []) {
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset_finance_book SET book_code=?,book_name=?,depreciation_method=?,gross_amount_minor=?,salvage_value_minor=?,opening_depreciation_minor=?,period_count=?,frequency_months=?,daily_prorata=?,rate_basis_points=?,book_status=?,immutable_checksum=?,updated_by_admin_key=? WHERE company_key_hash=? AND finance_book_key=?', [$bookCode, $bookName, $method, $gross, $salvage, $opening, $periods, $frequency, $daily, $rate, $status, $checksum, $adminKey, $companyKeyHash, $financeBookKey], 'Asset Finance Book update');
            $action = 'UPDATE';
        } else {
            yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_finance_book (finance_book_key,company_key,company_key_hash,asset_key,book_code,book_name,depreciation_method,gross_amount_minor,salvage_value_minor,opening_depreciation_minor,period_count,frequency_months,daily_prorata,rate_basis_points,book_status,immutable_checksum,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$financeBookKey, $companyKey, $companyKeyHash, $assetKey, $bookCode, $bookName, $method, $gross, $salvage, $opening, $periods, $frequency, $daily, $rate, $status, $checksum, $adminKey, $adminKey], 'Asset Finance Book create');
            $action = 'CREATE';
        }
        yovel_admin_assets_write_activity($db, $scope, $assetKey, 'FINANCE_BOOK_' . $action, date('Y-m-d H:i:s'), '', [], [], ['finance_book_key' => $financeBookKey, 'book_code' => $bookCode]);
        yovel_admin_assets_finance_audit($scope, $action, 'project_company_asset_finance_book', $financeBookKey, ['asset_key' => $assetKey, 'book_code' => $bookCode]);
        $saved = yovel_admin_assets_finance_book($company, $financeBookKey);
        if (!is_array($saved)) {
            throw new RuntimeException('Asset Finance Book read-back failed.');
        }
        yovel_admin_assets_assert_readback(['finance_book_key' => $financeBookKey, 'company_key' => $companyKey, 'company_key_hash' => $companyKeyHash, 'asset_key' => $assetKey, 'book_code' => $bookCode, 'book_name' => $bookName, 'depreciation_method' => $method, 'gross_amount_minor' => $gross, 'salvage_value_minor' => $salvage, 'opening_depreciation_minor' => $opening, 'period_count' => $periods, 'frequency_months' => $frequency, 'daily_prorata' => $daily, 'rate_basis_points' => $rate, 'book_status' => $status, 'immutable_checksum' => $checksum, 'updated_by_admin_key' => $adminKey], $saved, ['finance_book_key','company_key','company_key_hash','asset_key','book_code','book_name','depreciation_method','gross_amount_minor','salvage_value_minor','opening_depreciation_minor','period_count','frequency_months','daily_prorata','rate_basis_points','book_status','immutable_checksum','updated_by_admin_key'], 'Asset Finance Book');
        return $saved;
    });
}

function yovel_admin_assets_depreciation_schedule(array $company, string $scheduleKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($scheduleKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_asset_depreciation_schedule WHERE company_key=? AND company_key_hash=? AND schedule_key=? LIMIT 1', [$companyKey, $companyKeyHash, $scheduleKey]);
    if (!is_array($row) || $row === []) {
        return null;
    }
    $lines = bx_db()->GetAll('SELECT * FROM project_company_asset_depreciation_line WHERE company_key=? AND company_key_hash=? AND schedule_key=? ORDER BY line_no', [$companyKey, $companyKeyHash, $scheduleKey]);
    return $row + ['lines' => is_array($lines) ? $lines : []];
}

function yovel_admin_assets_depreciation_schedules(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $rows = bx_db()->GetAll('SELECT schedule.*,asset.asset_code,book.book_code FROM project_company_asset_depreciation_schedule schedule INNER JOIN project_company_asset asset ON asset.company_key_hash=schedule.company_key_hash AND asset.asset_key=schedule.asset_key INNER JOIN project_company_asset_finance_book book ON book.company_key_hash=schedule.company_key_hash AND book.finance_book_key=schedule.finance_book_key WHERE schedule.company_key=? AND schedule.company_key_hash=? ORDER BY schedule.x_id DESC', [$companyKey, $companyKeyHash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_insert_depreciation_schedule(ADOConnection $db, array $scope, array $header, array $calculation): string
{
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    $scheduleKey = (string) ($header['schedule_key'] ?? bx_uuid());
    yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_depreciation_schedule (schedule_key,company_key,company_key_hash,asset_key,finance_book_key,schedule_version,depreciation_method,start_date,posting_through_date,period_count,frequency_months,daily_prorata,rate_basis_points,gross_amount_minor,salvage_value_minor,opening_depreciation_minor,total_depreciation_minor,calculation_checksum,document_status,lifecycle_status,replacement_of_schedule_key,notes,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'DRAFT\',\'ACTIVE\',?,?,?,?)', [$scheduleKey, $companyKey, $companyKeyHash, $header['asset_key'], $header['finance_book_key'], $header['schedule_version'], $calculation['method'], $header['start_date'], $header['posting_through_date'], $calculation['period_count'], $calculation['frequency_months'], $calculation['daily_prorata'] ? 1 : 0, $calculation['rate_basis_points'], $calculation['gross_amount_minor'], $calculation['salvage_value_minor'], $calculation['opening_depreciation_minor'], $calculation['total_depreciation_minor'], $calculation['calculation_checksum'], ($header['replacement_of_schedule_key'] ?? '') !== '' ? $header['replacement_of_schedule_key'] : null, $header['notes'] ?? '', $adminKey, $adminKey], 'Asset Depreciation Schedule create');
    foreach ($calculation['lines'] as $line) {
        $lineKey = bx_uuid();
        $checksum = hash('sha256', implode('|', [$companyKeyHash, $scheduleKey, $line['line_no'], $line['posting_date'], $line['opening_value_minor'], $line['depreciation_amount_minor'], $line['accumulated_depreciation_minor'], $line['closing_value_minor']]));
        yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_depreciation_line (depreciation_line_key,company_key,company_key_hash,schedule_key,line_no,posting_date,opening_value_minor,depreciation_amount_minor,accumulated_depreciation_minor,closing_value_minor,posting_status,immutable_checksum,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?,\'PENDING\',?,?,?)', [$lineKey, $companyKey, $companyKeyHash, $scheduleKey, $line['line_no'], $line['posting_date'], $line['opening_value_minor'], $line['depreciation_amount_minor'], $line['accumulated_depreciation_minor'], $line['closing_value_minor'], $checksum, $adminKey, $adminKey], 'Asset Depreciation line create');
    }
    return $scheduleKey;
}

function yovel_admin_assets_save_depreciation_schedule(array $company, array $admin, array $input, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    yovel_admin_assets_dependency_services($services);
    $requestedKey = yovel_admin_assets_local_key($input['schedule_key'] ?? '', 'Depreciation Schedule key', false);
    $financeBookKey = yovel_admin_assets_local_key($input['finance_book_key'] ?? '', 'Finance Book key');
    $startDate = yovel_admin_assets_date($input['start_date'] ?? '', 'Depreciation start date');
    $throughDate = yovel_admin_assets_date($input['posting_through_date'] ?? $startDate, 'Posting through date');
    $replacementKey = yovel_admin_assets_local_key($input['replacement_of_schedule_key'] ?? '', 'Replacement Schedule key', false);
    $notes = yovel_admin_assets_text($input['notes'] ?? '', 'Depreciation notes', 2000);
    $book = yovel_admin_assets_finance_book($company, $financeBookKey);
    if (!is_array($book) || (string) $book['book_status'] !== 'ACTIVE') {
        throw new InvalidArgumentException('Active Finance Book was not found for this company.');
    }
    $manualLines = $input['manual_lines'] ?? [];
    if (trim((string) ($input['manual_lines_json'] ?? '')) !== '') {
        try {
            $decodedManualLines = json_decode((string) $input['manual_lines_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Manual depreciation rows must be valid JSON.');
        }
        if (!is_array($decodedManualLines)) {
            throw new InvalidArgumentException('Manual depreciation rows must be a JSON array.');
        }
        $manualLines = $decodedManualLines;
    }
    $terms = [
        'method' => $input['method'] ?? $book['depreciation_method'],
        'gross_amount' => $input['gross_amount'] ?? yovel_admin_assets_minor_money($book['gross_amount_minor']),
        'salvage_value' => $input['salvage_value'] ?? yovel_admin_assets_minor_money($book['salvage_value_minor']),
        'opening_depreciation' => $input['opening_depreciation'] ?? yovel_admin_assets_minor_money($book['opening_depreciation_minor']),
        'period_count' => $input['period_count'] ?? $book['period_count'],
        'frequency_months' => $input['frequency_months'] ?? $book['frequency_months'],
        'daily_prorata' => $input['daily_prorata'] ?? !empty($book['daily_prorata']),
        'rate_basis_points' => $input['rate_basis_points'] ?? $book['rate_basis_points'],
        'start_date' => $startDate,
        'manual_lines' => $manualLines,
    ];
    $calculation = yovel_admin_assets_calculate_schedule($terms);

    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $requestedKey, $financeBookKey, $book, $startDate, $throughDate, $replacementKey, $notes, $calculation): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $lockedBook = $db->GetRow('SELECT * FROM project_company_asset_finance_book WHERE company_key_hash=? AND finance_book_key=? FOR UPDATE', [$companyKeyHash, $financeBookKey]);
        if (!is_array($lockedBook) || $lockedBook === [] || (string) $lockedBook['book_status'] !== 'ACTIVE') {
            throw new InvalidArgumentException('Finance Book is no longer active.');
        }
        $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND schedule_key=? FOR UPDATE', [$companyKeyHash, $requestedKey]) : [];
        if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Depreciation Schedule was not found for this company.');
        }
        if (is_array($existing) && $existing !== [] && (string) $existing['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Submitted Depreciation Schedules are immutable.');
        }
        $replacement = [];
        if ($replacementKey !== '') {
            $replacement = $db->GetRow('SELECT * FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND schedule_key=? FOR UPDATE', [$companyKeyHash, $replacementKey]);
            if (!is_array($replacement) || $replacement === [] || (string) $replacement['finance_book_key'] !== $financeBookKey || !in_array((string) $replacement['document_status'], ['CANCELLED','SUBMITTED'], true)) {
                throw new InvalidArgumentException('Replacement source Schedule is invalid.');
            }
        }
        $conflict = (int) $db->GetOne("SELECT COUNT(*) FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND finance_book_key=? AND document_status IN ('DRAFT','SUBMITTED') AND lifecycle_status='ACTIVE' AND schedule_key<>?", [$companyKeyHash, $financeBookKey, $requestedKey !== '' ? $requestedKey : ($replacementKey !== '' ? $replacementKey : '')]);
        if ($conflict > 0) {
            throw new InvalidArgumentException('Finance Book already has an active schedule.');
        }
        if (is_array($existing) && $existing !== []) {
            $scheduleKey = (string) $existing['schedule_key'];
            yovel_admin_assets_execute($db, 'DELETE FROM project_company_asset_depreciation_line WHERE company_key_hash=? AND schedule_key=?', [$companyKeyHash, $scheduleKey], 'Asset Depreciation line replacement');
            yovel_admin_assets_execute($db, 'DELETE FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND schedule_key=?', [$companyKeyHash, $scheduleKey], 'Asset Depreciation Schedule draft replacement');
            $version = (int) $existing['schedule_version'];
            $action = 'UPDATE';
        } else {
            $scheduleKey = bx_uuid();
            $version = (int) $db->GetOne('SELECT COALESCE(MAX(schedule_version),0)+1 FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND finance_book_key=?', [$companyKeyHash, $financeBookKey]);
            $action = $replacementKey !== '' ? 'AMEND' : 'CREATE';
        }
        yovel_admin_assets_insert_depreciation_schedule($db, $scope, [
            'schedule_key' => $scheduleKey, 'asset_key' => (string) $book['asset_key'], 'finance_book_key' => $financeBookKey,
            'schedule_version' => $version, 'start_date' => $startDate, 'posting_through_date' => $throughDate,
            'replacement_of_schedule_key' => $replacementKey, 'notes' => $notes,
        ], $calculation);
        if ($replacementKey !== '') {
            yovel_admin_assets_execute($db, "UPDATE project_company_asset_depreciation_schedule SET lifecycle_status='SUPERSEDED',replaced_by_schedule_key=?,updated_by_admin_key=? WHERE company_key_hash=? AND schedule_key=?", [$scheduleKey, $adminKey, $companyKeyHash, $replacementKey], 'Asset Depreciation replacement link');
        }
        yovel_admin_assets_write_activity($db, $scope, (string) $book['asset_key'], 'DEPRECIATION_' . $action, date('Y-m-d H:i:s'), '', [], [], ['schedule_key' => $scheduleKey, 'finance_book_key' => $financeBookKey, 'version' => $version]);
        yovel_admin_assets_finance_audit($scope, $action, 'project_company_asset_depreciation_schedule', $scheduleKey, ['asset_key' => (string) $book['asset_key'], 'finance_book_key' => $financeBookKey, 'version' => $version]);
        $saved = yovel_admin_assets_depreciation_schedule($company, $scheduleKey);
        if (!is_array($saved) || count($saved['lines']) !== (int) $calculation['period_count'] || (string) $saved['calculation_checksum'] !== (string) $calculation['calculation_checksum']) {
            throw new RuntimeException('Asset Depreciation Schedule read-back failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_category_posting_accounts(ADOConnection $db, string $companyKeyHash, string $assetKey): array
{
    $rows = $db->GetAll('SELECT account.account_role,account.account_owner_key FROM project_company_asset asset INNER JOIN project_company_asset_category_account account ON account.company_key_hash=asset.company_key_hash AND account.category_key=asset.category_key WHERE asset.company_key_hash=? AND asset.asset_key=?', [$companyKeyHash, $assetKey]);
    $accounts = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $accounts[(string) $row['account_role']] = (string) $row['account_owner_key'];
    }
    return $accounts;
}

function yovel_admin_assets_finance_command(ADOConnection $db, array $company, array $admin, array $services, array $request): array
{
    $operation = strtoupper((string) ($request['operation'] ?? ''));
    try {
        $result = $services['finance.asset-posting.command']($db, $company, $admin, $request);
    } catch (Throwable $error) {
        throw $error;
    }
    if (!is_array($result)
        || (string) ($result['contract'] ?? '') !== 'accounting-finance.asset-posting.v1'
        || (string) ($result['operation'] ?? '') !== $operation
        || !hash_equals((string) $company['company_key_hash'], (string) ($result['company_key_hash'] ?? ''))) {
        throw new RuntimeException('Finance Asset posting command returned an invalid response.');
    }
    if (in_array($operation, ['POST','REVERSE'], true) && (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid((string) ($result['transaction_key'] ?? '')))) {
        throw new RuntimeException('Finance Asset posting command did not return a valid transaction key.');
    }
    return $result;
}

function yovel_admin_assets_post_schedule_lines(
    ADOConnection $db,
    array $company,
    array $admin,
    array $scope,
    array $schedule,
    string $throughDate,
    array $services
): void {
    [, $companyKeyHash, $adminKey] = $scope;
    $accounts = yovel_admin_assets_category_posting_accounts($db, $companyKeyHash, (string) $schedule['asset_key']);
    foreach (['DEPRECIATION_EXPENSE','ACCUMULATED_DEPRECIATION'] as $role) {
        if (($accounts[$role] ?? '') === '') {
            throw new InvalidArgumentException('Asset Category requires depreciation expense and accumulated depreciation accounts.');
        }
    }
    $lines = $db->GetAll("SELECT * FROM project_company_asset_depreciation_line WHERE company_key_hash=? AND schedule_key=? AND posting_status='PENDING' AND posting_date<=? ORDER BY line_no FOR UPDATE", [$companyKeyHash, $schedule['schedule_key'], $throughDate]);
    foreach (is_array($lines) ? $lines : [] as $line) {
        $amount = yovel_admin_assets_minor_money($line['depreciation_amount_minor'], 6);
        $base = [
            'posting_date' => (string) $line['posting_date'],
            'voucher_type' => 'ASSET_DEPRECIATION',
            'voucher_no' => 'DEP-' . substr((string) $schedule['schedule_key'], 0, 8) . '-' . (string) $line['line_no'],
            'source_record_key' => (string) $line['depreciation_line_key'],
            'remarks' => 'Asset depreciation schedule version ' . (string) $schedule['schedule_version'],
            'entries' => [
                ['account_key' => $accounts['DEPRECIATION_EXPENSE'], 'debit' => $amount, 'credit' => '0'],
                ['account_key' => $accounts['ACCUMULATED_DEPRECIATION'], 'debit' => '0', 'credit' => $amount],
            ],
        ];
        $draft = yovel_admin_assets_finance_command($db, $company, $admin, $services, ['operation' => 'DRAFT'] + $base);
        if ((int) ($draft['entry_count'] ?? 0) !== 2
            || (string) ($draft['total_debit'] ?? '') !== $amount
            || (string) ($draft['total_credit'] ?? '') !== $amount
            || preg_match('/^[a-f0-9]{64}$/', (string) ($draft['calculation_hash'] ?? '')) !== 1) {
            throw new RuntimeException('Finance depreciation draft verification failed.');
        }
        $transactionKey = bx_uuid();
        $posted = yovel_admin_assets_finance_command($db, $company, $admin, $services, [
            'operation' => 'POST',
            'transaction_key' => $transactionKey,
            'idempotency_key' => 'assets:depreciation:post:' . (string) $line['depreciation_line_key'],
        ] + $base);
        yovel_admin_assets_fault('depreciation_after_finance_post');
        yovel_admin_assets_execute($db, "UPDATE project_company_asset_depreciation_line SET posting_status='POSTED',finance_transaction_key=?,finance_calculation_hash=?,updated_by_admin_key=?,posted_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND depreciation_line_key=? AND posting_status='PENDING'", [(string) $posted['transaction_key'], (string) ($posted['calculation_hash'] ?? $draft['calculation_hash']), $adminKey, $companyKeyHash, $line['depreciation_line_key']], 'Asset Depreciation line post');
        $saved = $db->GetRow('SELECT posting_status,finance_transaction_key,finance_calculation_hash FROM project_company_asset_depreciation_line WHERE company_key_hash=? AND depreciation_line_key=?', [$companyKeyHash, $line['depreciation_line_key']]);
        if (!is_array($saved) || (string) $saved['posting_status'] !== 'POSTED' || (string) $saved['finance_transaction_key'] !== (string) $posted['transaction_key'] || preg_match('/^[a-f0-9]{64}$/', (string) $saved['finance_calculation_hash']) !== 1) {
            throw new RuntimeException('Asset Depreciation posting read-back failed.');
        }
        yovel_admin_assets_write_activity($db, $scope, (string) $schedule['asset_key'], 'DEPRECIATION_POST', (string) $line['posting_date'] . ' 00:00:00', '', [], [], ['schedule_key' => (string) $schedule['schedule_key'], 'line_key' => (string) $line['depreciation_line_key'], 'finance_transaction_key' => (string) $posted['transaction_key']]);
        yovel_admin_assets_finance_audit($scope, 'POST', 'project_company_asset_depreciation_line', (string) $line['depreciation_line_key'], ['schedule_key' => (string) $schedule['schedule_key'], 'finance_transaction_key' => (string) $posted['transaction_key']]);
    }
}

function yovel_admin_assets_submit_depreciation_schedule(array $company, array $admin, string $scheduleKey, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $scheduleKey = yovel_admin_assets_local_key($scheduleKey, 'Depreciation Schedule key');
    $services = yovel_admin_assets_dependency_services($services);
    yovel_admin_assets_owner_posting_readiness($company, $services);
    $current = yovel_admin_assets_depreciation_schedule($company, $scheduleKey);
    if (is_array($current) && (string) $current['document_status'] === 'SUBMITTED') {
        return $current;
    }
    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $admin, $scope, $companyKey, $companyKeyHash, $adminKey, $scheduleKey, $services): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $schedule = $db->GetRow('SELECT * FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND schedule_key=? FOR UPDATE', [$companyKeyHash, $scheduleKey]);
        if (!is_array($schedule) || $schedule === [] || (string) $schedule['document_status'] !== 'DRAFT' || (string) $schedule['lifecycle_status'] !== 'ACTIVE') {
            throw new InvalidArgumentException('Only an active draft Depreciation Schedule can be submitted.');
        }
        yovel_admin_assets_post_schedule_lines($db, $company, $admin, $scope, $schedule, (string) $schedule['posting_through_date'], $services);
        yovel_admin_assets_execute($db, "UPDATE project_company_asset_depreciation_schedule SET document_status='SUBMITTED',submitted_by_admin_key=?,submitted_at=CURRENT_TIMESTAMP,updated_by_admin_key=? WHERE company_key_hash=? AND schedule_key=?", [$adminKey, $adminKey, $companyKeyHash, $scheduleKey], 'Asset Depreciation Schedule submit');
        yovel_admin_assets_write_activity($db, $scope, (string) $schedule['asset_key'], 'DEPRECIATION_SUBMIT', date('Y-m-d H:i:s'), '', [], [], ['schedule_key' => $scheduleKey, 'version' => (int) $schedule['schedule_version']]);
        yovel_admin_assets_finance_audit($scope, 'SUBMIT', 'project_company_asset_depreciation_schedule', $scheduleKey, ['asset_key' => (string) $schedule['asset_key']]);
        $saved = yovel_admin_assets_depreciation_schedule($company, $scheduleKey);
        if (!is_array($saved) || (string) $saved['document_status'] !== 'SUBMITTED' || (string) $saved['submitted_by_admin_key'] !== $adminKey || empty($saved['submitted_at'])) {
            throw new RuntimeException('Asset Depreciation Schedule submit read-back failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_post_depreciation_catchup(array $company, array $admin, string $scheduleKey, string $throughDate, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $scheduleKey = yovel_admin_assets_local_key($scheduleKey, 'Depreciation Schedule key');
    $throughDate = yovel_admin_assets_date($throughDate, 'Catch-up through date');
    $services = yovel_admin_assets_dependency_services($services);
    yovel_admin_assets_owner_posting_readiness($company, $services);
    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $admin, $scope, $companyKey, $companyKeyHash, $adminKey, $scheduleKey, $throughDate, $services): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $schedule = $db->GetRow('SELECT * FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND schedule_key=? FOR UPDATE', [$companyKeyHash, $scheduleKey]);
        if (!is_array($schedule) || $schedule === [] || (string) $schedule['document_status'] !== 'SUBMITTED' || (string) $schedule['lifecycle_status'] !== 'ACTIVE') {
            throw new InvalidArgumentException('Catch-up requires an active submitted Depreciation Schedule.');
        }
        if ($throughDate < (string) $schedule['start_date']) {
            throw new InvalidArgumentException('Catch-up date cannot precede the Schedule start date.');
        }
        yovel_admin_assets_post_schedule_lines($db, $company, $admin, $scope, $schedule, $throughDate, $services);
        if ($throughDate > (string) $schedule['posting_through_date']) {
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset_depreciation_schedule SET posting_through_date=?,updated_by_admin_key=? WHERE company_key_hash=? AND schedule_key=?', [$throughDate, $adminKey, $companyKeyHash, $scheduleKey], 'Asset Depreciation catch-up date');
        }
        $saved = yovel_admin_assets_depreciation_schedule($company, $scheduleKey);
        if (!is_array($saved)) {
            throw new RuntimeException('Asset Depreciation catch-up read-back failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_cancel_depreciation_schedule(array $company, array $admin, string $scheduleKey, string $reason, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $scheduleKey = yovel_admin_assets_local_key($scheduleKey, 'Depreciation Schedule key');
    $reason = yovel_admin_assets_text($reason, 'Cancellation reason', 500, true);
    $services = yovel_admin_assets_dependency_services($services);
    yovel_admin_assets_owner_posting_readiness($company, $services);
    $current = yovel_admin_assets_depreciation_schedule($company, $scheduleKey);
    if (is_array($current) && (string) $current['document_status'] === 'CANCELLED') {
        if ((string) $current['cancellation_reason'] !== $reason) {
            throw new InvalidArgumentException('Cancelled Schedule reason is immutable.');
        }
        return $current;
    }
    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $admin, $scope, $companyKey, $companyKeyHash, $adminKey, $scheduleKey, $reason, $services): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $schedule = $db->GetRow('SELECT * FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND schedule_key=? FOR UPDATE', [$companyKeyHash, $scheduleKey]);
        if (!is_array($schedule) || $schedule === [] || (string) $schedule['document_status'] !== 'SUBMITTED') {
            throw new InvalidArgumentException('Only a submitted Depreciation Schedule can be cancelled.');
        }
        $lines = $db->GetAll("SELECT * FROM project_company_asset_depreciation_line WHERE company_key_hash=? AND schedule_key=? AND posting_status='POSTED' ORDER BY line_no FOR UPDATE", [$companyKeyHash, $scheduleKey]);
        foreach (is_array($lines) ? $lines : [] as $line) {
            $reversal = yovel_admin_assets_finance_command($db, $company, $admin, $services, [
                'operation' => 'REVERSE', 'transaction_key' => (string) $line['finance_transaction_key'],
                'posting_date' => date('Y-m-d'), 'reason' => $reason,
                'idempotency_key' => 'assets:depreciation:reverse:' . (string) $line['depreciation_line_key'],
            ]);
            yovel_admin_assets_execute($db, "UPDATE project_company_asset_depreciation_line SET posting_status='REVERSED',finance_reversal_transaction_key=?,updated_by_admin_key=?,reversed_at=CURRENT_TIMESTAMP WHERE company_key_hash=? AND depreciation_line_key=? AND posting_status='POSTED'", [(string) $reversal['transaction_key'], $adminKey, $companyKeyHash, $line['depreciation_line_key']], 'Asset Depreciation line reversal');
            yovel_admin_assets_finance_audit($scope, 'REVERSE', 'project_company_asset_depreciation_line', (string) $line['depreciation_line_key'], ['finance_transaction_key' => (string) $line['finance_transaction_key'], 'finance_reversal_transaction_key' => (string) $reversal['transaction_key']]);
        }
        yovel_admin_assets_execute($db, "UPDATE project_company_asset_depreciation_schedule SET document_status='CANCELLED',cancellation_reason=?,cancelled_by_admin_key=?,cancelled_at=CURRENT_TIMESTAMP,updated_by_admin_key=? WHERE company_key_hash=? AND schedule_key=?", [$reason, $adminKey, $adminKey, $companyKeyHash, $scheduleKey], 'Asset Depreciation Schedule cancel');
        yovel_admin_assets_write_activity($db, $scope, (string) $schedule['asset_key'], 'DEPRECIATION_CANCEL', date('Y-m-d H:i:s'), '', [], [], ['schedule_key' => $scheduleKey, 'reason' => $reason]);
        yovel_admin_assets_finance_audit($scope, 'CANCEL', 'project_company_asset_depreciation_schedule', $scheduleKey, ['asset_key' => (string) $schedule['asset_key'], 'reason' => $reason]);
        $saved = yovel_admin_assets_depreciation_schedule($company, $scheduleKey);
        if (!is_array($saved) || (string) $saved['document_status'] !== 'CANCELLED' || (string) $saved['cancellation_reason'] !== $reason) {
            throw new RuntimeException('Asset Depreciation Schedule cancellation read-back failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_shift_factor(array $company, string $factorKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($factorKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_asset_shift_factor WHERE company_key=? AND company_key_hash=? AND shift_factor_key=? LIMIT 1', [$companyKey, $companyKeyHash, $factorKey]);
    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_assets_shift_factors(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $rows = bx_db()->GetAll('SELECT * FROM project_company_asset_shift_factor WHERE company_key=? AND company_key_hash=? ORDER BY factor_code', [$companyKey, $companyKeyHash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_save_shift_factor(array $company, array $admin, array $input): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $requestedKey = yovel_admin_assets_local_key($input['shift_factor_key'] ?? '', 'Shift Factor key', false);
    $code = yovel_admin_assets_code($input['factor_code'] ?? '', 'Shift Factor code', 80);
    $name = yovel_admin_assets_text($input['factor_name'] ?? '', 'Shift Factor name', 180, true);
    $multiplierText = trim((string) ($input['multiplier'] ?? ''));
    if (!is_numeric($multiplierText)) {
        throw new InvalidArgumentException('Shift Factor multiplier is invalid.');
    }
    $basisPoints = (int) round((float) $multiplierText * 10000, 0, PHP_ROUND_HALF_UP);
    if ($basisPoints < 1000 || $basisPoints > 100000) {
        throw new InvalidArgumentException('Shift Factor multiplier must be between 0.1 and 10.0.');
    }
    $status = strtoupper(trim((string) ($input['factor_status'] ?? 'ACTIVE')));
    if (!in_array($status, ['ACTIVE','INACTIVE'], true)) {
        throw new InvalidArgumentException('Shift Factor status is invalid.');
    }
    $checksum = hash('sha256', implode('|', [$companyKeyHash, $code, $basisPoints, $status]));
    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $requestedKey, $code, $name, $basisPoints, $status, $checksum): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_asset_shift_factor WHERE company_key_hash=? AND shift_factor_key=? FOR UPDATE', [$companyKeyHash, $requestedKey]) : [];
        if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Shift Factor was not found for this company.');
        }
        $factorKey = is_array($existing) && $existing !== [] ? (string) $existing['shift_factor_key'] : bx_uuid();
        if (is_array($existing) && $existing !== []) {
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset_shift_factor SET factor_code=?,factor_name=?,multiplier_basis_points=?,factor_status=?,immutable_checksum=?,updated_by_admin_key=? WHERE company_key_hash=? AND shift_factor_key=?', [$code, $name, $basisPoints, $status, $checksum, $adminKey, $companyKeyHash, $factorKey], 'Asset Shift Factor update');
            $action = 'UPDATE';
        } else {
            yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_shift_factor (shift_factor_key,company_key,company_key_hash,factor_code,factor_name,multiplier_basis_points,factor_status,immutable_checksum,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,?)', [$factorKey, $companyKey, $companyKeyHash, $code, $name, $basisPoints, $status, $checksum, $adminKey, $adminKey], 'Asset Shift Factor create');
            $action = 'CREATE';
        }
        yovel_admin_assets_finance_audit($scope, $action, 'project_company_asset_shift_factor', $factorKey, ['factor_code' => $code, 'multiplier_basis_points' => $basisPoints]);
        $saved = yovel_admin_assets_shift_factor($company, $factorKey);
        if (!is_array($saved) || (string) $saved['immutable_checksum'] !== $checksum || (string) $saved['updated_by_admin_key'] !== $adminKey) {
            throw new RuntimeException('Asset Shift Factor read-back failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_shift_allocation(array $company, string $allocationKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($allocationKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_asset_shift_allocation WHERE company_key=? AND company_key_hash=? AND shift_allocation_key=? LIMIT 1', [$companyKey, $companyKeyHash, $allocationKey]);
    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_assets_shift_allocations(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $rows = bx_db()->GetAll('SELECT allocation.*,factor.factor_code,asset.asset_code FROM project_company_asset_shift_allocation allocation INNER JOIN project_company_asset_shift_factor factor ON factor.company_key_hash=allocation.company_key_hash AND factor.shift_factor_key=allocation.shift_factor_key INNER JOIN project_company_asset asset ON asset.company_key_hash=allocation.company_key_hash AND asset.asset_key=allocation.asset_key WHERE allocation.company_key=? AND allocation.company_key_hash=? ORDER BY allocation.effective_date DESC', [$companyKey, $companyKeyHash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_save_shift_allocation(array $company, array $admin, array $input, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    yovel_admin_assets_dependency_services($services);
    $requestedKey = yovel_admin_assets_local_key($input['shift_allocation_key'] ?? '', 'Shift Allocation key', false);
    $assetKey = yovel_admin_assets_local_key($input['asset_key'] ?? '', 'Asset key');
    $bookKey = yovel_admin_assets_local_key($input['finance_book_key'] ?? '', 'Finance Book key');
    $scheduleKey = yovel_admin_assets_local_key($input['schedule_key'] ?? '', 'Depreciation Schedule key');
    $factorKey = yovel_admin_assets_local_key($input['shift_factor_key'] ?? '', 'Shift Factor key');
    $effectiveDate = yovel_admin_assets_date($input['effective_date'] ?? '', 'Shift effective date');
    $reason = yovel_admin_assets_text($input['reason'] ?? '', 'Shift Allocation reason', 500, true);
    $checksum = hash('sha256', implode('|', [$companyKeyHash, $assetKey, $bookKey, $scheduleKey, $factorKey, $effectiveDate, $reason]));
    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $requestedKey, $assetKey, $bookKey, $scheduleKey, $factorKey, $effectiveDate, $reason, $checksum): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $schedule = $db->GetRow('SELECT * FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND schedule_key=? AND asset_key=? AND finance_book_key=? FOR UPDATE', [$companyKeyHash, $scheduleKey, $assetKey, $bookKey]);
        $factor = $db->GetRow("SELECT * FROM project_company_asset_shift_factor WHERE company_key_hash=? AND shift_factor_key=? AND factor_status='ACTIVE' FOR UPDATE", [$companyKeyHash, $factorKey]);
        if (!is_array($schedule) || $schedule === [] || !is_array($factor) || $factor === [] || (string) $schedule['document_status'] !== 'SUBMITTED' || (string) $schedule['lifecycle_status'] !== 'ACTIVE') {
            throw new InvalidArgumentException('Shift Allocation requires an active submitted Schedule and Shift Factor.');
        }
        $latestPosted = (string) $db->GetOne("SELECT COALESCE(MAX(posting_date),'') FROM project_company_asset_depreciation_line WHERE company_key_hash=? AND schedule_key=? AND posting_status='POSTED'", [$companyKeyHash, $scheduleKey]);
        if ($latestPosted !== '' && $effectiveDate <= $latestPosted) {
            throw new InvalidArgumentException('Shift effective date must follow the latest posted depreciation.');
        }
        $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_asset_shift_allocation WHERE company_key_hash=? AND shift_allocation_key=? FOR UPDATE', [$companyKeyHash, $requestedKey]) : [];
        if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Shift Allocation was not found for this company.');
        }
        if (is_array($existing) && $existing !== [] && (string) $existing['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Submitted Shift Allocations are immutable.');
        }
        $allocationKey = is_array($existing) && $existing !== [] ? (string) $existing['shift_allocation_key'] : bx_uuid();
        if (is_array($existing) && $existing !== []) {
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset_shift_allocation SET asset_key=?,finance_book_key=?,schedule_key=?,shift_factor_key=?,effective_date=?,reason=?,immutable_checksum=?,updated_by_admin_key=? WHERE company_key_hash=? AND shift_allocation_key=?', [$assetKey, $bookKey, $scheduleKey, $factorKey, $effectiveDate, $reason, $checksum, $adminKey, $companyKeyHash, $allocationKey], 'Asset Shift Allocation update');
            $action = 'UPDATE';
        } else {
            yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_shift_allocation (shift_allocation_key,company_key,company_key_hash,asset_key,finance_book_key,schedule_key,shift_factor_key,effective_date,reason,document_status,immutable_checksum,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,?,\'DRAFT\',?,?,?)', [$allocationKey, $companyKey, $companyKeyHash, $assetKey, $bookKey, $scheduleKey, $factorKey, $effectiveDate, $reason, $checksum, $adminKey, $adminKey], 'Asset Shift Allocation create');
            $action = 'CREATE';
        }
        yovel_admin_assets_finance_audit($scope, $action, 'project_company_asset_shift_allocation', $allocationKey, ['asset_key' => $assetKey, 'schedule_key' => $scheduleKey]);
        $saved = yovel_admin_assets_shift_allocation($company, $allocationKey);
        if (!is_array($saved) || (string) $saved['immutable_checksum'] !== $checksum) {
            throw new RuntimeException('Asset Shift Allocation read-back failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_submit_shift_allocation(array $company, array $admin, string $allocationKey, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $allocationKey = yovel_admin_assets_local_key($allocationKey, 'Shift Allocation key');
    yovel_admin_assets_dependency_services($services);
    $current = yovel_admin_assets_shift_allocation($company, $allocationKey);
    if (is_array($current) && (string) $current['document_status'] === 'SUBMITTED') {
        return $current;
    }
    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $allocationKey): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $allocation = $db->GetRow('SELECT * FROM project_company_asset_shift_allocation WHERE company_key_hash=? AND shift_allocation_key=? FOR UPDATE', [$companyKeyHash, $allocationKey]);
        if (!is_array($allocation) || $allocation === [] || (string) $allocation['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Only a draft Shift Allocation can be submitted.');
        }
        $schedule = $db->GetRow('SELECT * FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND schedule_key=? FOR UPDATE', [$companyKeyHash, $allocation['schedule_key']]);
        $factor = $db->GetRow("SELECT * FROM project_company_asset_shift_factor WHERE company_key_hash=? AND shift_factor_key=? AND factor_status='ACTIVE' FOR UPDATE", [$companyKeyHash, $allocation['shift_factor_key']]);
        if (!is_array($schedule) || $schedule === [] || (string) $schedule['document_status'] !== 'SUBMITTED' || (string) $schedule['lifecycle_status'] !== 'ACTIVE' || !is_array($factor) || $factor === []) {
            throw new InvalidArgumentException('Shift Allocation source changed before submission.');
        }
        $pending = $db->GetAll("SELECT * FROM project_company_asset_depreciation_line WHERE company_key_hash=? AND schedule_key=? AND posting_status='PENDING' AND posting_date>=? ORDER BY line_no FOR UPDATE", [$companyKeyHash, $schedule['schedule_key'], $allocation['effective_date']]);
        if (!is_array($pending) || $pending === []) {
            throw new InvalidArgumentException('Shift Allocation has no unposted depreciation to replace.');
        }
        $remaining = array_sum(array_map(static fn (array $line): int => (int) $line['depreciation_amount_minor'], $pending));
        $newPeriods = max(1, (int) ceil(count($pending) * 10000 / (int) $factor['multiplier_basis_points']));
        $opening = (int) $schedule['gross_amount_minor'] - (int) $schedule['salvage_value_minor'] - $remaining;
        $calculation = yovel_admin_assets_calculate_schedule([
            'method' => (string) $schedule['depreciation_method'],
            'gross_amount' => yovel_admin_assets_minor_money($schedule['gross_amount_minor']),
            'salvage_value' => yovel_admin_assets_minor_money($schedule['salvage_value_minor']),
            'opening_depreciation' => yovel_admin_assets_minor_money($opening),
            'period_count' => $newPeriods,
            'frequency_months' => (int) $schedule['frequency_months'],
            'daily_prorata' => false,
            'rate_basis_points' => (int) $schedule['rate_basis_points'],
            'start_date' => (string) $allocation['effective_date'],
        ]);
        $replacementKey = bx_uuid();
        $version = (int) $db->GetOne('SELECT COALESCE(MAX(schedule_version),0)+1 FROM project_company_asset_depreciation_schedule WHERE company_key_hash=? AND finance_book_key=?', [$companyKeyHash, $schedule['finance_book_key']]);
        yovel_admin_assets_insert_depreciation_schedule($db, $scope, [
            'schedule_key' => $replacementKey, 'asset_key' => (string) $schedule['asset_key'], 'finance_book_key' => (string) $schedule['finance_book_key'],
            'schedule_version' => $version, 'start_date' => (string) $allocation['effective_date'], 'posting_through_date' => (string) $allocation['effective_date'],
            'replacement_of_schedule_key' => (string) $schedule['schedule_key'], 'notes' => 'Shift replacement: ' . (string) $allocation['reason'],
        ], $calculation);
        yovel_admin_assets_execute($db, "UPDATE project_company_asset_depreciation_schedule SET lifecycle_status='SUPERSEDED',replaced_by_schedule_key=?,updated_by_admin_key=? WHERE company_key_hash=? AND schedule_key=?", [$replacementKey, $adminKey, $companyKeyHash, $schedule['schedule_key']], 'Asset Shift Schedule supersede');
        yovel_admin_assets_execute($db, "UPDATE project_company_asset_shift_allocation SET document_status='SUBMITTED',replacement_schedule_key=?,submitted_by_admin_key=?,submitted_at=CURRENT_TIMESTAMP,updated_by_admin_key=? WHERE company_key_hash=? AND shift_allocation_key=?", [$replacementKey, $adminKey, $adminKey, $companyKeyHash, $allocationKey], 'Asset Shift Allocation submit');
        yovel_admin_assets_write_activity($db, $scope, (string) $allocation['asset_key'], 'SHIFT_ALLOCATE', date('Y-m-d H:i:s'), '', [], [], ['allocation_key' => $allocationKey, 'source_schedule_key' => (string) $schedule['schedule_key'], 'replacement_schedule_key' => $replacementKey]);
        yovel_admin_assets_finance_audit($scope, 'SUBMIT', 'project_company_asset_shift_allocation', $allocationKey, ['replacement_schedule_key' => $replacementKey]);
        $saved = yovel_admin_assets_shift_allocation($company, $allocationKey);
        $replacement = yovel_admin_assets_depreciation_schedule($company, $replacementKey);
        if (!is_array($saved) || !is_array($replacement) || (string) $saved['document_status'] !== 'SUBMITTED' || (string) $saved['replacement_schedule_key'] !== $replacementKey || count($replacement['lines']) !== $newPeriods) {
            throw new RuntimeException('Asset Shift Allocation submit read-back failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_value_adjustment(array $company, string $adjustmentKey): ?array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($adjustmentKey)) {
        return null;
    }
    $row = bx_db()->GetRow('SELECT * FROM project_company_asset_value_adjustment WHERE company_key=? AND company_key_hash=? AND adjustment_key=? LIMIT 1', [$companyKey, $companyKeyHash, $adjustmentKey]);
    return is_array($row) && $row !== [] ? $row : null;
}

function yovel_admin_assets_value_adjustments(array $company): array
{
    [$companyKey, $companyKeyHash] = yovel_admin_assets_read_company_scope($company);
    $rows = bx_db()->GetAll('SELECT adjustment.*,asset.asset_code FROM project_company_asset_value_adjustment adjustment INNER JOIN project_company_asset asset ON asset.company_key_hash=adjustment.company_key_hash AND asset.asset_key=adjustment.asset_key WHERE adjustment.company_key=? AND adjustment.company_key_hash=? ORDER BY adjustment.posting_date DESC,adjustment.x_id DESC', [$companyKey, $companyKeyHash]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_assets_save_value_adjustment(array $company, array $admin, array $input, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    yovel_admin_assets_dependency_services($services);
    $requestedKey = yovel_admin_assets_local_key($input['adjustment_key'] ?? '', 'Value Adjustment key', false);
    $assetKey = yovel_admin_assets_local_key($input['asset_key'] ?? '', 'Asset key');
    $type = strtoupper(trim((string) ($input['adjustment_type'] ?? '')));
    if (!in_array($type, ['INCREASE','DECREASE'], true)) {
        throw new InvalidArgumentException('Value Adjustment type is invalid.');
    }
    $postingDate = yovel_admin_assets_date($input['posting_date'] ?? '', 'Value Adjustment posting date');
    $amount = yovel_admin_assets_money_minor($input['amount'] ?? '', 'Value Adjustment amount', true);
    $reason = yovel_admin_assets_text($input['reason'] ?? '', 'Value Adjustment reason', 500, true);
    $checksum = hash('sha256', implode('|', [$companyKeyHash, $assetKey, $type, $postingDate, $amount, $reason]));
    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $scope, $companyKey, $companyKeyHash, $adminKey, $requestedKey, $assetKey, $type, $postingDate, $amount, $reason, $checksum): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $asset = $db->GetRow('SELECT * FROM project_company_asset WHERE company_key_hash=? AND asset_key=? FOR UPDATE', [$companyKeyHash, $assetKey]);
        if (!is_array($asset) || $asset === [] || (string) $asset['document_status'] !== 'SUBMITTED') {
            throw new InvalidArgumentException('Value Adjustment requires a submitted company Asset.');
        }
        if ($postingDate < (string) $asset['acquisition_date']) {
            throw new InvalidArgumentException('Value Adjustment cannot precede the Asset acquisition date.');
        }
        $existing = $requestedKey !== '' ? $db->GetRow('SELECT * FROM project_company_asset_value_adjustment WHERE company_key_hash=? AND adjustment_key=? FOR UPDATE', [$companyKeyHash, $requestedKey]) : [];
        if ($requestedKey !== '' && (!is_array($existing) || $existing === [])) {
            throw new InvalidArgumentException('Value Adjustment was not found for this company.');
        }
        if (is_array($existing) && $existing !== [] && (string) $existing['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Submitted Value Adjustments are immutable.');
        }
        $adjustmentKey = is_array($existing) && $existing !== [] ? (string) $existing['adjustment_key'] : bx_uuid();
        if (is_array($existing) && $existing !== []) {
            yovel_admin_assets_execute($db, 'UPDATE project_company_asset_value_adjustment SET asset_key=?,adjustment_type=?,posting_date=?,amount_minor=?,reason=?,immutable_checksum=?,updated_by_admin_key=? WHERE company_key_hash=? AND adjustment_key=?', [$assetKey, $type, $postingDate, $amount, $reason, $checksum, $adminKey, $companyKeyHash, $adjustmentKey], 'Asset Value Adjustment update');
            $action = 'UPDATE';
        } else {
            yovel_admin_assets_execute($db, 'INSERT INTO project_company_asset_value_adjustment (adjustment_key,company_key,company_key_hash,asset_key,adjustment_type,posting_date,amount_minor,reason,document_status,immutable_checksum,created_by_admin_key,updated_by_admin_key) VALUES (?,?,?,?,?,?,?,?,\'DRAFT\',?,?,?)', [$adjustmentKey, $companyKey, $companyKeyHash, $assetKey, $type, $postingDate, $amount, $reason, $checksum, $adminKey, $adminKey], 'Asset Value Adjustment create');
            $action = 'CREATE';
        }
        yovel_admin_assets_finance_audit($scope, $action, 'project_company_asset_value_adjustment', $adjustmentKey, ['asset_key' => $assetKey, 'amount_minor' => $amount]);
        $saved = yovel_admin_assets_value_adjustment($company, $adjustmentKey);
        if (!is_array($saved) || (string) $saved['immutable_checksum'] !== $checksum || (string) $saved['updated_by_admin_key'] !== $adminKey) {
            throw new RuntimeException('Asset Value Adjustment read-back failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_submit_value_adjustment(array $company, array $admin, string $adjustmentKey, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $adjustmentKey = yovel_admin_assets_local_key($adjustmentKey, 'Value Adjustment key');
    $services = yovel_admin_assets_dependency_services($services);
    yovel_admin_assets_owner_posting_readiness($company, $services);
    $current = yovel_admin_assets_value_adjustment($company, $adjustmentKey);
    if (is_array($current) && (string) $current['document_status'] === 'SUBMITTED') {
        return $current;
    }
    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $admin, $scope, $companyKey, $companyKeyHash, $adminKey, $adjustmentKey, $services): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $adjustment = $db->GetRow('SELECT * FROM project_company_asset_value_adjustment WHERE company_key_hash=? AND adjustment_key=? FOR UPDATE', [$companyKeyHash, $adjustmentKey]);
        if (!is_array($adjustment) || $adjustment === [] || (string) $adjustment['document_status'] !== 'DRAFT') {
            throw new InvalidArgumentException('Only a draft Value Adjustment can be submitted.');
        }
        $accounts = yovel_admin_assets_category_posting_accounts($db, $companyKeyHash, (string) $adjustment['asset_key']);
        foreach (['ASSET','GAIN_LOSS'] as $role) {
            if (($accounts[$role] ?? '') === '') {
                throw new InvalidArgumentException('Asset Category requires Asset and gain/loss accounts for adjustments.');
            }
        }
        $amount = yovel_admin_assets_minor_money($adjustment['amount_minor'], 6);
        $increase = (string) $adjustment['adjustment_type'] === 'INCREASE';
        $base = [
            'posting_date' => (string) $adjustment['posting_date'], 'voucher_type' => 'ASSET_ADJUSTMENT',
            'voucher_no' => 'ADJ-' . substr($adjustmentKey, 0, 12), 'source_record_key' => $adjustmentKey,
            'remarks' => (string) $adjustment['reason'],
            'entries' => [
                ['account_key' => $increase ? $accounts['ASSET'] : $accounts['GAIN_LOSS'], 'debit' => $amount, 'credit' => '0'],
                ['account_key' => $increase ? $accounts['GAIN_LOSS'] : $accounts['ASSET'], 'debit' => '0', 'credit' => $amount],
            ],
        ];
        $draft = yovel_admin_assets_finance_command($db, $company, $admin, $services, ['operation' => 'DRAFT'] + $base);
        if ((string) ($draft['total_debit'] ?? '') !== $amount || (string) ($draft['total_credit'] ?? '') !== $amount) {
            throw new RuntimeException('Finance Value Adjustment draft verification failed.');
        }
        $posted = yovel_admin_assets_finance_command($db, $company, $admin, $services, ['operation' => 'POST', 'transaction_key' => bx_uuid(), 'idempotency_key' => 'assets:value-adjustment:post:' . $adjustmentKey] + $base);
        yovel_admin_assets_fault('value_adjustment_after_finance_post');
        yovel_admin_assets_execute($db, "UPDATE project_company_asset_value_adjustment SET document_status='SUBMITTED',finance_transaction_key=?,finance_calculation_hash=?,submitted_by_admin_key=?,submitted_at=CURRENT_TIMESTAMP,updated_by_admin_key=? WHERE company_key_hash=? AND adjustment_key=?", [(string) $posted['transaction_key'], (string) ($posted['calculation_hash'] ?? $draft['calculation_hash']), $adminKey, $adminKey, $companyKeyHash, $adjustmentKey], 'Asset Value Adjustment submit');
        yovel_admin_assets_write_activity($db, $scope, (string) $adjustment['asset_key'], 'VALUE_ADJUST', (string) $adjustment['posting_date'] . ' 00:00:00', '', [], [], ['adjustment_key' => $adjustmentKey, 'adjustment_type' => (string) $adjustment['adjustment_type'], 'finance_transaction_key' => (string) $posted['transaction_key']]);
        yovel_admin_assets_finance_audit($scope, 'SUBMIT', 'project_company_asset_value_adjustment', $adjustmentKey, ['finance_transaction_key' => (string) $posted['transaction_key']]);
        $saved = yovel_admin_assets_value_adjustment($company, $adjustmentKey);
        if (!is_array($saved) || (string) $saved['document_status'] !== 'SUBMITTED' || (string) $saved['finance_transaction_key'] !== (string) $posted['transaction_key']) {
            throw new RuntimeException('Asset Value Adjustment submit read-back failed.');
        }
        return $saved;
    });
}

function yovel_admin_assets_cancel_value_adjustment(array $company, array $admin, string $adjustmentKey, string $reason, ?array $services = null): array
{
    $scope = yovel_admin_assets_scope($company, $admin);
    [$companyKey, $companyKeyHash, $adminKey] = $scope;
    yovel_admin_assets_maintenance_schema();
    $adjustmentKey = yovel_admin_assets_local_key($adjustmentKey, 'Value Adjustment key');
    $reason = yovel_admin_assets_text($reason, 'Cancellation reason', 500, true);
    $services = yovel_admin_assets_dependency_services($services);
    yovel_admin_assets_owner_posting_readiness($company, $services);
    $current = yovel_admin_assets_value_adjustment($company, $adjustmentKey);
    if (is_array($current) && (string) $current['document_status'] === 'CANCELLED') {
        if ((string) $current['cancellation_reason'] !== $reason) {
            throw new InvalidArgumentException('Cancelled Value Adjustment reason is immutable.');
        }
        return $current;
    }
    return yovel_admin_assets_in_transaction(static function (ADOConnection $db) use ($company, $admin, $scope, $companyKey, $companyKeyHash, $adminKey, $adjustmentKey, $reason, $services): array {
        yovel_admin_assets_lock_company($db, $companyKey, $companyKeyHash);
        $adjustment = $db->GetRow('SELECT * FROM project_company_asset_value_adjustment WHERE company_key_hash=? AND adjustment_key=? FOR UPDATE', [$companyKeyHash, $adjustmentKey]);
        if (!is_array($adjustment) || $adjustment === [] || (string) $adjustment['document_status'] !== 'SUBMITTED') {
            throw new InvalidArgumentException('Only a submitted Value Adjustment can be cancelled.');
        }
        $reversal = yovel_admin_assets_finance_command($db, $company, $admin, $services, [
            'operation' => 'REVERSE', 'transaction_key' => (string) $adjustment['finance_transaction_key'],
            'posting_date' => date('Y-m-d'), 'reason' => $reason,
            'idempotency_key' => 'assets:value-adjustment:reverse:' . $adjustmentKey,
        ]);
        yovel_admin_assets_execute($db, "UPDATE project_company_asset_value_adjustment SET document_status='CANCELLED',finance_reversal_transaction_key=?,cancellation_reason=?,cancelled_by_admin_key=?,cancelled_at=CURRENT_TIMESTAMP,updated_by_admin_key=? WHERE company_key_hash=? AND adjustment_key=?", [(string) $reversal['transaction_key'], $reason, $adminKey, $adminKey, $companyKeyHash, $adjustmentKey], 'Asset Value Adjustment cancel');
        yovel_admin_assets_write_activity($db, $scope, (string) $adjustment['asset_key'], 'VALUE_ADJUST_CANCEL', date('Y-m-d H:i:s'), '', [], [], ['adjustment_key' => $adjustmentKey, 'finance_reversal_transaction_key' => (string) $reversal['transaction_key']]);
        yovel_admin_assets_finance_audit($scope, 'CANCEL', 'project_company_asset_value_adjustment', $adjustmentKey, ['finance_reversal_transaction_key' => (string) $reversal['transaction_key']]);
        $saved = yovel_admin_assets_value_adjustment($company, $adjustmentKey);
        if (!is_array($saved) || (string) $saved['document_status'] !== 'CANCELLED' || (string) $saved['finance_reversal_transaction_key'] !== (string) $reversal['transaction_key']) {
            throw new RuntimeException('Asset Value Adjustment cancellation read-back failed.');
        }
        return $saved;
    });
}
