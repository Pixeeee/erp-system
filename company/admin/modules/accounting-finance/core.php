<?php
declare(strict_types=1);

require_once __DIR__ . '/foundation.php';

function yovel_admin_finance_big_strip(string $value): string
{
    $value = ltrim($value, '0');
    return $value === '' ? '0' : $value;
}

function yovel_admin_finance_big_compare(string $left, string $right): int
{
    $left = yovel_admin_finance_big_strip($left);
    $right = yovel_admin_finance_big_strip($right);
    return strlen($left) === strlen($right) ? ($left <=> $right) : (strlen($left) <=> strlen($right));
}

function yovel_admin_finance_big_add(string $left, string $right): string
{
    $left = strrev($left); $right = strrev($right); $carry = 0; $result = '';
    for ($index = 0, $length = max(strlen($left), strlen($right)); $index < $length; $index++) {
        $sum = (int) ($left[$index] ?? '0') + (int) ($right[$index] ?? '0') + $carry;
        $result .= (string) ($sum % 10); $carry = intdiv($sum, 10);
    }
    if ($carry > 0) $result .= (string) $carry;
    return yovel_admin_finance_big_strip(strrev($result));
}

function yovel_admin_finance_big_subtract(string $left, string $right): string
{
    $left = strrev($left); $right = strrev($right); $borrow = 0; $result = '';
    for ($index = 0, $length = strlen($left); $index < $length; $index++) {
        $digit = (int) $left[$index] - (int) ($right[$index] ?? '0') - $borrow;
        if ($digit < 0) { $digit += 10; $borrow = 1; } else { $borrow = 0; }
        $result .= (string) $digit;
    }
    return yovel_admin_finance_big_strip(strrev($result));
}

function yovel_admin_finance_big_multiply(string $left, string $right): string
{
    $left = yovel_admin_finance_big_strip($left); $right = yovel_admin_finance_big_strip($right);
    if ($left === '0' || $right === '0') return '0';
    $digits = array_fill(0, strlen($left) + strlen($right), 0);
    for ($i = strlen($left) - 1; $i >= 0; $i--) for ($j = strlen($right) - 1; $j >= 0; $j--) {
        $position = $i + $j + 1; $sum = $digits[$position] + ((int) $left[$i] * (int) $right[$j]);
        $digits[$position] = $sum % 10; $digits[$position - 1] += intdiv($sum, 10);
    }
    return yovel_admin_finance_big_strip(implode('', $digits));
}

function yovel_admin_finance_big_divide(string $numerator, string $denominator): string
{
    $numerator = yovel_admin_finance_big_strip($numerator); $denominator = yovel_admin_finance_big_strip($denominator);
    if ($denominator === '0') throw new DivisionByZeroError('Division by zero.');
    $result = ''; $remainder = '0';
    for ($index = 0, $length = strlen($numerator); $index < $length; $index++) {
        $remainder = yovel_admin_finance_big_strip($remainder . $numerator[$index]); $digit = 0;
        while (yovel_admin_finance_big_compare($remainder, $denominator) >= 0) { $remainder = yovel_admin_finance_big_subtract($remainder, $denominator); $digit++; }
        $result .= (string) $digit;
    }
    return yovel_admin_finance_big_strip($result);
}

function yovel_admin_finance_decimal_parts(string $value): array
{
    $value = trim($value); $negative = str_starts_with($value, '-'); $value = ltrim($value, '+-');
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    $whole = preg_replace('/\D/', '', $whole) ?: '0'; $fraction = preg_replace('/\D/', '', $fraction) ?: '';
    return [$negative, yovel_admin_finance_big_strip($whole . $fraction), strlen($fraction)];
}

function yovel_admin_finance_decimal_integer(string $value, int $scale): array
{
    [$negative, $digits, $sourceScale] = yovel_admin_finance_decimal_parts($value);
    if ($sourceScale < $scale) $digits .= str_repeat('0', $scale - $sourceScale);
    elseif ($sourceScale > $scale) { $cut = $sourceScale - $scale; $digits = strlen($digits) <= $cut ? '0' : substr($digits, 0, strlen($digits) - $cut); }
    $digits = yovel_admin_finance_big_strip($digits);
    return [$negative && $digits !== '0', $digits];
}

function yovel_admin_finance_decimal_format(bool $negative, string $digits, int $scale): string
{
    $digits = yovel_admin_finance_big_strip($digits);
    if ($scale > 0) { $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT); $digits = substr($digits, 0, -$scale) . '.' . substr($digits, -$scale); }
    return ($negative && yovel_admin_finance_big_strip(str_replace('.', '', $digits)) !== '0' ? '-' : '') . $digits;
}

if (!function_exists('bccomp')) {
    function bccomp(string $left, string $right, int $scale = 0): int { [$ln,$li]=yovel_admin_finance_decimal_integer($left,$scale);[$rn,$ri]=yovel_admin_finance_decimal_integer($right,$scale);if($ln!==$rn)return $ln?-1:1;$cmp=yovel_admin_finance_big_compare($li,$ri);return $ln?-$cmp:$cmp; }
    function bcadd(string $left, string $right, int $scale = 0): string { [$ln,$li]=yovel_admin_finance_decimal_integer($left,$scale);[$rn,$ri]=yovel_admin_finance_decimal_integer($right,$scale);if($ln===$rn)return yovel_admin_finance_decimal_format($ln,yovel_admin_finance_big_add($li,$ri),$scale);$cmp=yovel_admin_finance_big_compare($li,$ri);return $cmp>=0?yovel_admin_finance_decimal_format($ln,yovel_admin_finance_big_subtract($li,$ri),$scale):yovel_admin_finance_decimal_format($rn,yovel_admin_finance_big_subtract($ri,$li),$scale); }
    function bcsub(string $left, string $right, int $scale = 0): string { return bcadd($left, str_starts_with($right,'-')?substr($right,1):'-'.$right, $scale); }
    function bcmul(string $left, string $right, int $scale = 0): string { [$ln,$li,$ls]=yovel_admin_finance_decimal_parts($left);[$rn,$ri,$rs]=yovel_admin_finance_decimal_parts($right);$digits=yovel_admin_finance_big_multiply($li,$ri);$source=$ls+$rs;if($source<$scale)$digits.=str_repeat('0',$scale-$source);elseif($source>$scale){$cut=$source-$scale;$digits=strlen($digits)<=$cut?'0':substr($digits,0,strlen($digits)-$cut);}return yovel_admin_finance_decimal_format($ln!==$rn,$digits,$scale); }
    function bcdiv(string $left, string $right, int $scale = 0): string { [$ln,$li,$ls]=yovel_admin_finance_decimal_parts($left);[$rn,$ri,$rs]=yovel_admin_finance_decimal_parts($right);$numerator=$li.str_repeat('0',$rs+$scale);$denominator=$ri.str_repeat('0',$ls);return yovel_admin_finance_decimal_format($ln!==$rn,yovel_admin_finance_big_divide($numerator,$denominator),$scale); }
}

function yovel_admin_finance_core_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_finance_setting (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            finance_setting_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL UNIQUE,
            base_currency VARCHAR(20) NOT NULL DEFAULT 'PHP',
            currency_precision TINYINT UNSIGNED NOT NULL DEFAULT 2,
            fiscal_year_start_month TINYINT UNSIGNED NOT NULL DEFAULT 1,
            bir_registered_name VARCHAR(180) NULL,
            bir_tin VARCHAR(30) NULL,
            bir_branch_code VARCHAR(10) NULL,
            bir_rdo_code VARCHAR(10) NULL,
            vat_registration_type ENUM('VAT','NON_VAT') NOT NULL DEFAULT 'VAT',
            default_receivable_account_key CHAR(36) NULL,
            default_payable_account_key CHAR(36) NULL,
            default_income_account_key CHAR(36) NULL,
            default_expense_account_key CHAR(36) NULL,
            default_output_vat_account_key CHAR(36) NULL,
            default_input_vat_account_key CHAR(36) NULL,
            retained_earnings_account_key CHAR(36) NULL,
            round_off_account_key CHAR(36) NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_number_series (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            number_series_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            series_code VARCHAR(80) NOT NULL,
            fiscal_year SMALLINT UNSIGNED NOT NULL,
            prefix VARCHAR(40) NOT NULL,
            next_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
            padding TINYINT UNSIGNED NOT NULL DEFAULT 5,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_series (company_key_hash, series_code, fiscal_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_cost_center (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cost_center_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            cost_center_code VARCHAR(80) NOT NULL,
            cost_center_name VARCHAR(180) NOT NULL,
            parent_cost_center_key CHAR(36) NULL,
            is_group TINYINT(1) NOT NULL DEFAULT 0,
            manager_name VARCHAR(180) NULL,
            status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            notes TEXT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_cost_center (company_key_hash, cost_center_code),
            INDEX idx_project_company_finance_cost_parent (company_key_hash, parent_cost_center_key),
            INDEX idx_project_company_finance_cost_status (company_key_hash, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_dimension (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dimension_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            dimension_code VARCHAR(80) NOT NULL,
            dimension_name VARCHAR(180) NOT NULL,
            reference_type ENUM('CUSTOM','PROJECT','BRANCH','DEPARTMENT') NOT NULL DEFAULT 'CUSTOM',
            mandatory_for_profit_loss TINYINT(1) NOT NULL DEFAULT 0,
            mandatory_for_balance_sheet TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_dimension (company_key_hash, dimension_code),
            INDEX idx_project_company_finance_dimension_status (company_key_hash, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_dimension_value (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dimension_value_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            dimension_key CHAR(36) NOT NULL,
            value_code VARCHAR(80) NOT NULL,
            value_name VARCHAR(180) NOT NULL,
            status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_dimension_value (company_key_hash, dimension_key, value_code),
            INDEX idx_project_company_finance_dimension_value_status (company_key_hash, dimension_key, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_bank_account (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bank_account_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            bank_account_code VARCHAR(80) NOT NULL,
            bank_name VARCHAR(180) NOT NULL,
            account_name VARCHAR(180) NOT NULL,
            masked_account_number VARCHAR(80) NULL,
            ledger_account_key CHAR(36) NOT NULL,
            currency VARCHAR(20) NOT NULL DEFAULT 'PHP',
            opening_balance DECIMAL(20,6) NOT NULL DEFAULT 0,
            status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            notes TEXT NULL,
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_bank_account (company_key_hash, bank_account_code),
            INDEX idx_project_company_finance_bank_ledger (company_key_hash, ledger_account_key),
            INDEX idx_project_company_finance_bank_status (company_key_hash, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_tax_code (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tax_code_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            tax_code VARCHAR(80) NOT NULL,
            tax_name VARCHAR(180) NOT NULL,
            tax_kind ENUM('VAT','WITHHOLDING','OTHER') NOT NULL DEFAULT 'VAT',
            bir_classification ENUM('VATABLE','ZERO_RATED','EXEMPT','OUT_OF_SCOPE','GOVERNMENT','INPUT_SERVICE','INPUT_CAPITAL','INPUT_GOODS') NOT NULL DEFAULT 'VATABLE',
            rate DECIMAL(12,6) NOT NULL DEFAULT 0,
            price_inclusive TINYINT(1) NOT NULL DEFAULT 0,
            creditable TINYINT(1) NOT NULL DEFAULT 1,
            tax_account_key CHAR(36) NULL,
            effective_from DATE NOT NULL,
            effective_to DATE NULL,
            status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_tax_code (company_key_hash, tax_code),
            INDEX idx_project_company_finance_tax_effective (company_key_hash, status, effective_from, effective_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_period_lock (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            period_lock_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            lock_type ENUM('SOFT_CLOSE','HARD_CLOSE') NOT NULL DEFAULT 'HARD_CLOSE',
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            reason VARCHAR(500) NOT NULL,
            close_transaction_key CHAR(36) NULL,
            status ENUM('ACTIVE','REOPENED','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            reopened_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reopened_at TIMESTAMP NULL,
            UNIQUE KEY uq_project_company_finance_period_lock (company_key_hash, date_from, date_to, lock_type, status),
            INDEX idx_project_company_finance_period_date (company_key_hash, status, date_from, date_to)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Finance core schema update');
    }
}

function yovel_admin_finance_money(mixed $value, int $scale = 6): string
{
    $value = trim((string) $value);
    if ($value === '' || preg_match('/^-?(?:\d+)(?:\.\d+)?$/', $value) !== 1) {
        throw new InvalidArgumentException('Amount must be a number.');
    }
    if (bccomp($value, '99999999999999.999999', 6) === 1 || bccomp($value, '-99999999999999.999999', 6) === -1) {
        throw new InvalidArgumentException('Amount is outside the allowed range.');
    }
    $rounded = yovel_admin_finance_decimal_round($value, $scale);
    return bccomp($rounded, '0', $scale) === 0 ? bcadd('0', '0', $scale) : $rounded;
}

function yovel_admin_finance_abs(mixed $value, int $scale = 6): string
{
    $amount = yovel_admin_finance_money($value, $scale);
    return bccomp($amount, '0', $scale) === -1 ? bcmul($amount, '-1', $scale) : $amount;
}

function yovel_admin_finance_decimal_round(string $value, int $scale = 6): string
{
    $scale = max(0, min(8, $scale));
    $offset = $scale === 0 ? '0.5' : '0.' . str_repeat('0', $scale) . '5';
    $adjusted = str_starts_with(trim($value), '-') ? bcsub($value, $offset, $scale + 1) : bcadd($value, $offset, $scale + 1);
    return bcadd($adjusted, '0', $scale);
}

function yovel_admin_finance_scope(array $company, array $admin): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyKeyHash = trim((string) ($company['company_key_hash'] ?? ''));
    $adminKey = trim((string) ($admin['admin_key'] ?? ''));
    if ($companyKey === '' || strlen($companyKey) > 64 || $companyKeyHash === '' || !yovel_admin_is_uuid($adminKey)) {
        throw new InvalidArgumentException('Finance administrator scope is invalid.');
    }
    $authorized = (int) bx_db()->GetOne(
        "SELECT COUNT(*)
           FROM project_company c
           JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash
          WHERE c.company_key=? AND c.company_key_hash=? AND c.company_status<>'DELETED'
            AND a.admin_key=? AND a.company_key_hash=? AND a.admin_status='ACTIVE'",
        [$companyKey, $companyKeyHash, $adminKey, $companyKeyHash]
    );
    if ($authorized !== 1) {
        throw new InvalidArgumentException('Finance administrator is not authorized for this company.');
    }
    return [$companyKey, $companyKeyHash, $adminKey];
}

function yovel_admin_finance_settings(array $company, ?array $admin = null): array
{
    yovel_admin_finance_core_schema();
    $companyKeyHash = trim((string) ($company['company_key_hash'] ?? ''));
    $row = $companyKeyHash !== '' ? bx_db()->GetRow('SELECT * FROM project_company_finance_setting WHERE company_key_hash = ? LIMIT 1', [$companyKeyHash]) : null;
    return is_array($row) && $row !== [] ? $row : [
        'base_currency' => 'PHP',
        'currency_precision' => 2,
        'fiscal_year_start_month' => 1,
        'vat_registration_type' => 'VAT',
        'bir_registered_name' => (string) ($company['company_name'] ?? ''),
        'bir_tin' => '',
        'bir_branch_code' => '',
        'bir_rdo_code' => '',
    ];
}

function yovel_admin_finance_account_key(ADOConnection $db, string $companyKeyHash, mixed $value, string $label, array $rootTypes = [], array $accountTypes = []): ?string
{
    $key = trim((string) $value);
    if ($key === '') {
        return null;
    }
    if (!yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException($label . ' selection is invalid.');
    }
    $row = $db->GetRow("SELECT account_key, root_type, account_type, is_group, freeze_account, account_status FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key = ? AND account_status = 'ACTIVE' LIMIT 1", [$companyKeyHash, $key]);
    if (!is_array($row) || $row === [] || (int) $row['is_group'] === 1 || (int) $row['freeze_account'] === 1) {
        throw new InvalidArgumentException($label . ' must be an active posting account owned by this company.');
    }
    if ($accountTypes !== [] && !in_array(strtoupper((string) $row['account_type']), array_map('strtoupper', $accountTypes), true)) {
        throw new InvalidArgumentException($label . ' must use a ' . implode(' or ', $accountTypes) . ' account type.');
    }
    if ($rootTypes !== [] && !in_array((string) $row['root_type'], $rootTypes, true)) {
        throw new InvalidArgumentException($label . ' has an incompatible root type.');
    }
    return $key;
}

function yovel_admin_persist_finance_settings(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_finance_core_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $currency = strtoupper(trim((string) ($input['base_currency'] ?? 'PHP')));
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
        throw new InvalidArgumentException('Base currency must be a three-letter currency code.');
    }
    $precision = (int) ($input['currency_precision'] ?? 2);
    $fiscalMonth = (int) ($input['fiscal_year_start_month'] ?? 1);
    if ($precision < 0 || $precision > 6 || $fiscalMonth < 1 || $fiscalMonth > 12) {
        throw new InvalidArgumentException('Currency precision or fiscal year start month is invalid.');
    }
    $registeredName = trim((string) ($input['bir_registered_name'] ?? ''));
    $tin = trim((string) ($input['bir_tin'] ?? ''));
    $branch = trim((string) ($input['bir_branch_code'] ?? ''));
    $rdo = trim((string) ($input['bir_rdo_code'] ?? ''));
    if ($tin !== '' && preg_match('/^\d{3}-\d{3}-\d{3}(?:-\d{3,5})?$/', $tin) !== 1) {
        throw new InvalidArgumentException('BIR TIN must use the registered numeric format.');
    }
    if ($branch !== '' && preg_match('/^\d{3,5}$/', $branch) !== 1) {
        throw new InvalidArgumentException('BIR branch code must contain 3 to 5 digits.');
    }
    if ($rdo !== '' && preg_match('/^\d{3,5}$/', $rdo) !== 1) {
        throw new InvalidArgumentException('BIR RDO code must contain 3 to 5 digits.');
    }
    if (strlen($registeredName) > 180) {
        throw new InvalidArgumentException('BIR registered name must be 180 characters or fewer.');
    }
    $vatType = yovel_admin_status((string) ($input['vat_registration_type'] ?? 'VAT'), ['VAT', 'NON_VAT'], 'VAT');
    $accountRules = [
        'default_receivable_account_key' => [['ASSET'], ['Receivable']],
        'default_payable_account_key' => [['LIABILITY'], ['Payable']],
        'default_income_account_key' => [['INCOME'], []],
        'default_expense_account_key' => [['EXPENSE', 'ASSET'], []],
        'default_output_vat_account_key' => [['LIABILITY'], ['Tax']],
        'default_input_vat_account_key' => [['ASSET'], ['Tax']],
        'retained_earnings_account_key' => [['EQUITY'], []],
        'round_off_account_key' => [['INCOME', 'EXPENSE'], []],
    ];
    $accounts = [];
    foreach ($accountRules as $field => [$roots, $types]) {
        $accounts[$field] = yovel_admin_finance_account_key($db, $companyKeyHash, $input[$field] ?? '', ucwords(str_replace('_', ' ', $field)), $roots, $types);
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance settings transaction could not start.');
    }
    try {
        $existingKey = (string) $db->GetOne('SELECT finance_setting_key FROM project_company_finance_setting WHERE company_key_hash = ? FOR UPDATE', [$companyKeyHash]);
        $settingKey = yovel_admin_is_uuid($existingKey) ? $existingKey : bx_uuid();
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_setting (
            finance_setting_key, company_key, company_key_hash, base_currency, currency_precision, fiscal_year_start_month,
            bir_registered_name, bir_tin, bir_branch_code, bir_rdo_code, vat_registration_type,
            default_receivable_account_key, default_payable_account_key, default_income_account_key, default_expense_account_key,
            default_output_vat_account_key, default_input_vat_account_key, retained_earnings_account_key, round_off_account_key,
            created_by_admin_key, updated_by_admin_key
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE base_currency=VALUES(base_currency), currency_precision=VALUES(currency_precision), fiscal_year_start_month=VALUES(fiscal_year_start_month),
            bir_registered_name=VALUES(bir_registered_name), bir_tin=VALUES(bir_tin), bir_branch_code=VALUES(bir_branch_code), bir_rdo_code=VALUES(bir_rdo_code),
            vat_registration_type=VALUES(vat_registration_type), default_receivable_account_key=VALUES(default_receivable_account_key),
            default_payable_account_key=VALUES(default_payable_account_key), default_income_account_key=VALUES(default_income_account_key),
            default_expense_account_key=VALUES(default_expense_account_key), default_output_vat_account_key=VALUES(default_output_vat_account_key),
            default_input_vat_account_key=VALUES(default_input_vat_account_key), retained_earnings_account_key=VALUES(retained_earnings_account_key),
            round_off_account_key=VALUES(round_off_account_key), updated_by_admin_key=VALUES(updated_by_admin_key)", [
                $settingKey, $companyKey, $companyKeyHash, $currency, $precision, $fiscalMonth,
                $registeredName !== '' ? $registeredName : null, $tin !== '' ? $tin : null, $branch !== '' ? $branch : null, $rdo !== '' ? $rdo : null, $vatType,
                $accounts['default_receivable_account_key'], $accounts['default_payable_account_key'], $accounts['default_income_account_key'], $accounts['default_expense_account_key'],
                $accounts['default_output_vat_account_key'], $accounts['default_input_vat_account_key'], $accounts['retained_earnings_account_key'], $accounts['round_off_account_key'],
                $adminKey, $adminKey,
            ], 'Finance settings save');
        $saved = $db->GetRow('SELECT * FROM project_company_finance_setting WHERE company_key_hash = ? AND finance_setting_key = ? LIMIT 1', [$companyKeyHash, $settingKey]);
        if (!is_array($saved) || (string) $saved['base_currency'] !== $currency || (string) ($saved['bir_tin'] ?? '') !== $tin) {
            throw new RuntimeException('Finance settings read-back verification failed.');
        }
        bx_audit($existingKey !== '' ? 'UPDATE' : 'CREATE', 'project_company_finance_setting', $settingKey, ['company_key' => $companyKey, 'base_currency' => $currency, 'vat_registration_type' => $vatType, 'admin_key' => $adminKey], 'Company administrator saved Finance settings.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance settings transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_finance_master_type(string $type): string
{
    $type = yovel_admin_slug($type);
    if (!in_array($type, array_merge(['cost-center', 'accounting-dimension', 'bank-account', 'tax-code'], yovel_admin_finance_foundation_master_types()), true)) {
        throw new InvalidArgumentException('Finance master type is invalid.');
    }
    return $type;
}

function yovel_admin_persist_finance_master(ADOConnection $db, array $company, array $admin, string $type, array $input): array
{
    yovel_admin_finance_core_schema();
    $type = yovel_admin_finance_master_type($type);
    return match ($type) {
        'cost-center' => yovel_admin_persist_finance_cost_center($db, $company, $admin, $input),
        'accounting-dimension' => yovel_admin_persist_finance_dimension($db, $company, $admin, $input),
        'bank-account' => yovel_admin_persist_finance_bank_account($db, $company, $admin, $input),
        'tax-code' => yovel_admin_persist_finance_tax_code($db, $company, $admin, $input),
        default => yovel_admin_persist_finance_foundation_master($db, $company, $admin, $type, $input),
    };
}

function yovel_admin_finance_master_common(array $company, array $admin, array $input, string $keyField, string $codeField, string $nameField): array
{
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $key = trim((string) ($input[$keyField] ?? ''));
    if ($key !== '' && !yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Finance master key is invalid.');
    }
    $code = yovel_admin_code((string) ($input[$codeField] ?? ''));
    $name = trim((string) ($input[$nameField] ?? ''));
    if ($code === '' || $name === '') {
        throw new InvalidArgumentException('Finance master code and name are required.');
    }
    if (strlen($code) > 80 || strlen($name) > 180) {
        throw new InvalidArgumentException('Finance master code or name exceeds the allowed length.');
    }
    return [$companyKey, $companyKeyHash, $adminKey, $key, $code, $name, yovel_admin_status((string) ($input['status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED'], 'ACTIVE')];
}

function yovel_admin_persist_finance_cost_center(ADOConnection $db, array $company, array $admin, array $input): array
{
    [$companyKey, $hash, $adminKey, $key, $code, $name, $status] = yovel_admin_finance_master_common($company, $admin, $input, 'cost_center_key', 'cost_center_code', 'cost_center_name');
    $parentKey = trim((string) ($input['parent_cost_center_key'] ?? ''));
    if ($parentKey !== '') {
        if (!yovel_admin_is_uuid($parentKey) || $parentKey === $key) {
            throw new InvalidArgumentException('Parent Cost Center selection is invalid.');
        }
        $parent = $db->GetRow("SELECT is_group FROM project_company_finance_cost_center WHERE company_key_hash = ? AND cost_center_key = ? AND status = 'ACTIVE' LIMIT 1", [$hash, $parentKey]);
        if (!is_array($parent) || (int) ($parent['is_group'] ?? 0) !== 1) {
            throw new InvalidArgumentException('Parent Cost Center must be an active group owned by this company.');
        }
    }
    $manager = trim((string) ($input['manager_name'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));
    $isGroup = !empty($input['is_group']) ? 1 : 0;
    return yovel_admin_finance_master_transaction($db, $companyKey, $hash, $adminKey, 'project_company_finance_cost_center', 'cost_center_key', $key, $code, 'cost_center_code', static function (string $stableKey) use ($db, $companyKey, $hash, $adminKey, $code, $name, $parentKey, $isGroup, $manager, $status, $notes): void {
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_cost_center (cost_center_key, company_key, company_key_hash, cost_center_code, cost_center_name, parent_cost_center_key, is_group, manager_name, status, notes, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE cost_center_name=VALUES(cost_center_name), parent_cost_center_key=VALUES(parent_cost_center_key), is_group=VALUES(is_group), manager_name=VALUES(manager_name), status=VALUES(status), notes=VALUES(notes), updated_by_admin_key=VALUES(updated_by_admin_key)", [$stableKey, $companyKey, $hash, $code, $name, $parentKey !== '' ? $parentKey : null, $isGroup, $manager !== '' ? $manager : null, $status, $notes !== '' ? $notes : null, $adminKey, $adminKey], 'Cost Center save');
    });
}

function yovel_admin_persist_finance_dimension(ADOConnection $db, array $company, array $admin, array $input): array
{
    [$companyKey, $hash, $adminKey, $key, $code, $name, $status] = yovel_admin_finance_master_common($company, $admin, $input, 'dimension_key', 'dimension_code', 'dimension_name');
    $referenceType = yovel_admin_status((string) ($input['reference_type'] ?? 'CUSTOM'), ['CUSTOM', 'PROJECT', 'BRANCH', 'DEPARTMENT'], 'CUSTOM');
    $values = $input['values'] ?? [];
    if (!is_array($values) || count($values) > 500) {
        throw new InvalidArgumentException('Accounting Dimension values are invalid.');
    }
    $saved = yovel_admin_finance_master_transaction($db, $companyKey, $hash, $adminKey, 'project_company_finance_dimension', 'dimension_key', $key, $code, 'dimension_code', static function (string $stableKey) use ($db, $companyKey, $hash, $adminKey, $code, $name, $referenceType, $input, $status, $values): void {
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_dimension (dimension_key, company_key, company_key_hash, dimension_code, dimension_name, reference_type, mandatory_for_profit_loss, mandatory_for_balance_sheet, status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE dimension_name=VALUES(dimension_name), reference_type=VALUES(reference_type), mandatory_for_profit_loss=VALUES(mandatory_for_profit_loss), mandatory_for_balance_sheet=VALUES(mandatory_for_balance_sheet), status=VALUES(status), updated_by_admin_key=VALUES(updated_by_admin_key)", [$stableKey, $companyKey, $hash, $code, $name, $referenceType, !empty($input['mandatory_for_profit_loss']) ? 1 : 0, !empty($input['mandatory_for_balance_sheet']) ? 1 : 0, $status, $adminKey, $adminKey], 'Accounting Dimension save');
        $seen = [];
        foreach ($values as $value) {
            if (!is_array($value)) {
                throw new InvalidArgumentException('Accounting Dimension values must be structured rows.');
            }
            $valueCode = yovel_admin_code((string) ($value['value_code'] ?? ''));
            $valueName = trim((string) ($value['value_name'] ?? ''));
            if ($valueCode === '' || $valueName === '' || isset($seen[$valueCode])) {
                throw new InvalidArgumentException('Accounting Dimension value code and name must be unique and complete.');
            }
            $seen[$valueCode] = true;
            $valueKey = trim((string) ($value['dimension_value_key'] ?? ''));
            if ($valueKey === '') {
                $valueKey = bx_uuid();
            } elseif (!yovel_admin_is_uuid($valueKey)) {
                throw new InvalidArgumentException('Accounting Dimension value key is invalid.');
            }
            yovel_admin_db_execute($db, "INSERT INTO project_company_finance_dimension_value (dimension_value_key, company_key, company_key_hash, dimension_key, value_code, value_name, status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE value_name=VALUES(value_name), status=VALUES(status), updated_by_admin_key=VALUES(updated_by_admin_key)", [$valueKey, $companyKey, $hash, $stableKey, $valueCode, $valueName, yovel_admin_status((string) ($value['status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED'], 'ACTIVE'), $adminKey, $adminKey], 'Accounting Dimension value save');
        }
    });
    $saved['values'] = yovel_admin_finance_dimension_values($company, (string) $saved['dimension_key']);
    return $saved;
}

function yovel_admin_persist_finance_bank_account(ADOConnection $db, array $company, array $admin, array $input): array
{
    [$companyKey, $hash, $adminKey, $key, $code, $bankName, $status] = yovel_admin_finance_master_common($company, $admin, $input, 'bank_account_key', 'bank_account_code', 'bank_name');
    $accountName = trim((string) ($input['account_name'] ?? ''));
    if ($accountName === '' || strlen($accountName) > 180) {
        throw new InvalidArgumentException('Bank Account name is required.');
    }
    $ledgerKey = yovel_admin_finance_account_key($db, $hash, $input['ledger_account_key'] ?? '', 'Bank ledger account', ['ASSET'], ['Bank', 'Cash']);
    if ($ledgerKey === null) {
        throw new InvalidArgumentException('Bank Account requires a Bank or Cash ledger account.');
    }
    $currency = strtoupper(trim((string) ($input['currency'] ?? 'PHP')));
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
        throw new InvalidArgumentException('Bank Account currency is invalid.');
    }
    $masked = trim((string) ($input['masked_account_number'] ?? ''));
    $opening = yovel_admin_finance_money($input['opening_balance'] ?? '0');
    $notes = trim((string) ($input['notes'] ?? ''));
    return yovel_admin_finance_master_transaction($db, $companyKey, $hash, $adminKey, 'project_company_finance_bank_account', 'bank_account_key', $key, $code, 'bank_account_code', static function (string $stableKey) use ($db, $companyKey, $hash, $adminKey, $code, $bankName, $accountName, $masked, $ledgerKey, $currency, $opening, $status, $notes): void {
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_bank_account (bank_account_key, company_key, company_key_hash, bank_account_code, bank_name, account_name, masked_account_number, ledger_account_key, currency, opening_balance, status, notes, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE bank_name=VALUES(bank_name), account_name=VALUES(account_name), masked_account_number=VALUES(masked_account_number), ledger_account_key=VALUES(ledger_account_key), currency=VALUES(currency), opening_balance=VALUES(opening_balance), status=VALUES(status), notes=VALUES(notes), updated_by_admin_key=VALUES(updated_by_admin_key)", [$stableKey, $companyKey, $hash, $code, $bankName, $accountName, $masked !== '' ? $masked : null, $ledgerKey, $currency, $opening, $status, $notes !== '' ? $notes : null, $adminKey, $adminKey], 'Bank Account save');
    });
}

function yovel_admin_persist_finance_tax_code(ADOConnection $db, array $company, array $admin, array $input): array
{
    [$companyKey, $hash, $adminKey, $key, $code, $name, $status] = yovel_admin_finance_master_common($company, $admin, $input, 'tax_code_key', 'tax_code', 'tax_name');
    $kind = yovel_admin_status((string) ($input['tax_kind'] ?? 'VAT'), ['VAT', 'WITHHOLDING', 'OTHER'], 'VAT');
    $classification = yovel_admin_status((string) ($input['bir_classification'] ?? 'VATABLE'), ['VATABLE', 'ZERO_RATED', 'EXEMPT', 'OUT_OF_SCOPE', 'GOVERNMENT', 'INPUT_SERVICE', 'INPUT_CAPITAL', 'INPUT_GOODS'], 'VATABLE');
    $rate = yovel_admin_finance_money($input['rate'] ?? '0');
    if (bccomp($rate, '100', 6) === 1 || bccomp($rate, '0', 6) === -1) {
        throw new InvalidArgumentException('Tax Code rate must be between 0 and 100.');
    }
    $effectiveFrom = yovel_admin_optional_date((string) ($input['effective_from'] ?? ''), 'Tax Code effective from');
    $effectiveTo = yovel_admin_optional_date((string) ($input['effective_to'] ?? ''), 'Tax Code effective to');
    if ($effectiveFrom === '' || ($effectiveTo !== '' && $effectiveTo < $effectiveFrom)) {
        throw new InvalidArgumentException('Tax Code effective date range is invalid.');
    }
    $taxAccountKey = yovel_admin_finance_account_key($db, $hash, $input['tax_account_key'] ?? '', 'Tax account', ['ASSET', 'LIABILITY'], ['Tax']);
    return yovel_admin_finance_master_transaction($db, $companyKey, $hash, $adminKey, 'project_company_finance_tax_code', 'tax_code_key', $key, $code, 'tax_code', static function (string $stableKey) use ($db, $companyKey, $hash, $adminKey, $code, $name, $kind, $classification, $rate, $input, $taxAccountKey, $effectiveFrom, $effectiveTo, $status): void {
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_tax_code (tax_code_key, company_key, company_key_hash, tax_code, tax_name, tax_kind, bir_classification, rate, price_inclusive, creditable, tax_account_key, effective_from, effective_to, status, created_by_admin_key, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE tax_name=VALUES(tax_name), tax_kind=VALUES(tax_kind), bir_classification=VALUES(bir_classification), rate=VALUES(rate), price_inclusive=VALUES(price_inclusive), creditable=VALUES(creditable), tax_account_key=VALUES(tax_account_key), effective_from=VALUES(effective_from), effective_to=VALUES(effective_to), status=VALUES(status), updated_by_admin_key=VALUES(updated_by_admin_key)", [$stableKey, $companyKey, $hash, $code, $name, $kind, $classification, $rate, !empty($input['price_inclusive']) ? 1 : 0, !empty($input['creditable']) ? 1 : 0, $taxAccountKey, $effectiveFrom, $effectiveTo !== '' ? $effectiveTo : null, $status, $adminKey, $adminKey], 'Tax Code save');
    });
}

function yovel_admin_finance_master_transaction(ADOConnection $db, string $companyKey, string $hash, string $adminKey, string $table, string $keyColumn, string $key, string $code, string $codeColumn, callable $writer): array
{
    $allowed = [
        'project_company_finance_cost_center' => ['cost_center_key', 'cost_center_code'],
        'project_company_finance_dimension' => ['dimension_key', 'dimension_code'],
        'project_company_finance_bank_account' => ['bank_account_key', 'bank_account_code'],
        'project_company_finance_tax_code' => ['tax_code_key', 'tax_code'],
    ];
    if (($allowed[$table] ?? null) !== [$keyColumn, $codeColumn]) {
        throw new InvalidArgumentException('Finance master storage mapping is invalid.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance master transaction could not start.');
    }
    try {
        $existing = $key !== '' ? $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash = ? AND {$keyColumn} = ? FOR UPDATE", [$hash, $key]) : $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash = ? AND {$codeColumn} = ? FOR UPDATE", [$hash, $code]);
        if ($key !== '' && (!is_array($existing) || $existing === [])) {
            $foreignOwner = (int) $db->GetOne("SELECT COUNT(*) FROM {$table} WHERE {$keyColumn} = ?", [$key]);
            if ($foreignOwner > 0) {
                throw new InvalidArgumentException('Finance master record does not belong to this company.');
            }
        }
        $stableKey = is_array($existing) && $existing !== [] ? (string) $existing[$keyColumn] : ($key !== '' ? $key : bx_uuid());
        $writer($stableKey);
        $saved = $db->GetRow("SELECT * FROM {$table} WHERE company_key_hash = ? AND {$keyColumn} = ? LIMIT 1", [$hash, $stableKey]);
        if (!is_array($saved) || (string) $saved[$keyColumn] !== $stableKey || (string) $saved[$codeColumn] !== $code) {
            throw new RuntimeException('Finance master read-back verification failed.');
        }
        bx_audit(is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE', $table, $stableKey, ['company_key' => $companyKey, $codeColumn => $code, 'admin_key' => $adminKey], 'Company administrator saved a Finance master record.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance master transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_finance_dimension_values(array $company, string $dimensionKey): array
{
    if (!yovel_admin_is_uuid($dimensionKey)) {
        return [];
    }
    $rows = bx_db()->GetAll("SELECT * FROM project_company_finance_dimension_value WHERE company_key_hash = ? AND dimension_key = ? AND status <> 'DELETED' ORDER BY value_name, x_id", [(string) ($company['company_key_hash'] ?? ''), $dimensionKey]);
    return is_array($rows) ? $rows : [];
}

function yovel_admin_finance_masters(array $company, string $type): array
{
    yovel_admin_finance_core_schema();
    $type = yovel_admin_finance_master_type($type);
    [$table, $order] = match ($type) {
        'cost-center' => ['project_company_finance_cost_center', 'cost_center_name, x_id'],
        'accounting-dimension' => ['project_company_finance_dimension', 'dimension_name, x_id'],
        'bank-account' => ['project_company_finance_bank_account', 'bank_name, account_name, x_id'],
        'tax-code' => ['project_company_finance_tax_code', 'tax_code, effective_from DESC, x_id'],
    };
    $rows = bx_db()->GetAll("SELECT * FROM {$table} WHERE company_key_hash = ? AND status <> 'DELETED' ORDER BY {$order}", [(string) ($company['company_key_hash'] ?? '')]);
    $rows = is_array($rows) ? $rows : [];
    if ($type === 'accounting-dimension') {
        foreach ($rows as &$row) {
            $row['values'] = yovel_admin_finance_dimension_values($company, (string) $row['dimension_key']);
        }
        unset($row);
    }
    return $rows;
}

function yovel_admin_finance_next_number(ADOConnection $db, array $company, array $admin, string $seriesCode, string $prefix, int $fiscalYear): string
{
    yovel_admin_finance_core_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $seriesCode = yovel_admin_code($seriesCode);
    $prefix = strtoupper(trim($prefix));
    if ($seriesCode === '' || strlen($prefix) > 40 || $fiscalYear < 1900 || $fiscalYear > 2500) {
        throw new InvalidArgumentException('Finance number series is invalid.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance numbering transaction could not start.');
    }
    try {
        $row = $db->GetRow('SELECT * FROM project_company_finance_number_series WHERE company_key_hash = ? AND series_code = ? AND fiscal_year = ? FOR UPDATE', [$hash, $seriesCode, $fiscalYear]);
        $number = is_array($row) && $row !== [] ? (int) $row['next_number'] : 1;
        $padding = is_array($row) && $row !== [] ? (int) $row['padding'] : 5;
        $seriesKey = is_array($row) && $row !== [] ? (string) $row['number_series_key'] : bx_uuid();
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_number_series (number_series_key, company_key, company_key_hash, series_code, fiscal_year, prefix, next_number, padding, updated_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE prefix=VALUES(prefix), next_number=VALUES(next_number), padding=VALUES(padding), updated_by_admin_key=VALUES(updated_by_admin_key)", [$seriesKey, $companyKey, $hash, $seriesCode, $fiscalYear, $prefix, $number + 1, $padding, $adminKey], 'Finance number allocation');
        $savedNext = (int) $db->GetOne('SELECT next_number FROM project_company_finance_number_series WHERE company_key_hash = ? AND series_code = ? AND fiscal_year = ?', [$hash, $seriesCode, $fiscalYear]);
        if ($savedNext !== $number + 1) {
            throw new RuntimeException('Finance number allocation read-back verification failed.');
        }
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance numbering transaction could not commit.');
        }
        return $prefix . $fiscalYear . '-' . str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_persist_finance_period_lock(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_finance_core_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $key = trim((string) ($input['period_lock_key'] ?? '')) ?: bx_uuid();
    $dateFrom = yovel_admin_optional_date((string) ($input['date_from'] ?? ''), 'Period start');
    $dateTo = yovel_admin_optional_date((string) ($input['date_to'] ?? ''), 'Period end');
    $reason = trim((string) ($input['reason'] ?? ''));
    if (!yovel_admin_is_uuid($key) || $dateFrom === '' || $dateTo === '' || $dateTo < $dateFrom || $reason === '' || strlen($reason) > 500) {
        throw new InvalidArgumentException('Finance period lock dates and reason are required and must be valid.');
    }
    $lockType = yovel_admin_status((string) ($input['lock_type'] ?? 'HARD_CLOSE'), ['SOFT_CLOSE', 'HARD_CLOSE'], 'HARD_CLOSE');
    $status = yovel_admin_status((string) ($input['status'] ?? 'ACTIVE'), ['ACTIVE', 'REOPENED', 'DELETED'], 'ACTIVE');
    $transactionKey = trim((string) ($input['close_transaction_key'] ?? ''));
    if ($transactionKey !== '' && !yovel_admin_is_uuid($transactionKey)) {
        throw new InvalidArgumentException('Period close transaction key is invalid.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance period lock transaction could not start.');
    }
    try {
        $existing = $db->GetRow('SELECT period_lock_key FROM project_company_finance_period_lock WHERE company_key_hash = ? AND period_lock_key = ? FOR UPDATE', [$hash, $key]);
        yovel_admin_db_execute($db, "INSERT INTO project_company_finance_period_lock (period_lock_key, company_key, company_key_hash, lock_type, date_from, date_to, reason, close_transaction_key, status, created_by_admin_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE lock_type=VALUES(lock_type), date_from=VALUES(date_from), date_to=VALUES(date_to), reason=VALUES(reason), close_transaction_key=VALUES(close_transaction_key), status=VALUES(status)", [$key, $companyKey, $hash, $lockType, $dateFrom, $dateTo, $reason, $transactionKey !== '' ? $transactionKey : null, $status, $adminKey], 'Finance period lock save');
        $saved = $db->GetRow('SELECT * FROM project_company_finance_period_lock WHERE company_key_hash = ? AND period_lock_key = ? LIMIT 1', [$hash, $key]);
        if (!is_array($saved) || (string) $saved['date_from'] !== $dateFrom || (string) $saved['date_to'] !== $dateTo) {
            throw new RuntimeException('Finance period lock read-back verification failed.');
        }
        bx_audit(is_array($existing) && $existing !== [] ? 'UPDATE' : 'CREATE', 'project_company_finance_period_lock', $key, ['company_key' => $companyKey, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'lock_type' => $lockType, 'admin_key' => $adminKey], 'Company administrator saved a Finance period lock.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance period lock transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_finance_assert_open_period(array $company, string $postingDate, bool $ensureSchema = true): void
{
    if ($ensureSchema) {
        yovel_admin_finance_core_schema();
    }
    $postingDate = yovel_admin_optional_date($postingDate, 'Posting date');
    if ($postingDate === '') {
        throw new InvalidArgumentException('Posting date is required.');
    }
    $lock = bx_db()->GetRow("SELECT date_from, date_to, lock_type FROM project_company_finance_period_lock WHERE company_key_hash = ? AND status = 'ACTIVE' AND ? BETWEEN date_from AND date_to ORDER BY lock_type DESC, date_to DESC LIMIT 1", [(string) ($company['company_key_hash'] ?? ''), $postingDate]);
    if (is_array($lock) && $lock !== []) {
        throw new InvalidArgumentException('Posting date is inside a closed Finance period (' . (string) $lock['date_from'] . ' to ' . (string) $lock['date_to'] . ').');
    }
}

function yovel_admin_save_finance_settings(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'save_finance_settings', static function () use ($company, $admin): string {
        yovel_admin_persist_finance_settings(bx_db(), $company, $admin, $_POST);
        return 'Finance settings saved.';
    });
}

function yovel_admin_save_finance_master(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'save_finance_master', static function () use ($company, $admin): string {
        yovel_admin_persist_finance_master(bx_db(), $company, $admin, (string) ($_POST['master_type'] ?? ''), $_POST);
        return 'Finance master saved.';
    });
}

function yovel_admin_set_finance_master_status(array $company, array $admin): string
{
    yovel_admin_finance_core_schema();
    [$companyKey, $hash, $adminKey] = yovel_admin_finance_scope($company, $admin);
    $type = yovel_admin_finance_master_type((string) ($_POST['master_type'] ?? ''));
    $mapping = [
        'cost-center' => ['project_company_finance_cost_center', 'cost_center_key'],
        'accounting-dimension' => ['project_company_finance_dimension', 'dimension_key'],
        'bank-account' => ['project_company_finance_bank_account', 'bank_account_key'],
        'tax-code' => ['project_company_finance_tax_code', 'tax_code_key'],
    ];
    [$table, $keyColumn] = $mapping[$type];
    $key = trim((string) ($_POST['record_key'] ?? ''));
    $status = yovel_admin_status((string) ($_POST['status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'DELETED'], 'ACTIVE');
    if (!yovel_admin_is_uuid($key)) {
        throw new InvalidArgumentException('Finance master record key is invalid.');
    }
    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance master status transaction could not start.');
    }
    try {
        $existing = $db->GetRow("SELECT {$keyColumn} FROM {$table} WHERE company_key_hash = ? AND {$keyColumn} = ? FOR UPDATE", [$hash, $key]);
        if (!is_array($existing) || $existing === []) {
            throw new InvalidArgumentException('Finance master record was not found for this company.');
        }
        yovel_admin_db_execute($db, "UPDATE {$table} SET status = ?, updated_by_admin_key = ? WHERE company_key_hash = ? AND {$keyColumn} = ?", [$status, $adminKey, $hash, $key], 'Finance master status update');
        if ((string) $db->GetOne("SELECT status FROM {$table} WHERE company_key_hash = ? AND {$keyColumn} = ?", [$hash, $key]) !== $status) {
            throw new RuntimeException('Finance master status read-back verification failed.');
        }
        bx_audit('STATUS', $table, $key, ['company_key' => $companyKey, 'status' => $status, 'admin_key' => $adminKey], 'Company administrator changed a Finance master status.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance master status transaction could not commit.');
        }
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
    return 'Finance master status updated.';
}
