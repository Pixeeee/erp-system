<?php
declare(strict_types=1);

function yovel_admin_buying_scorecard_tokens(string $expression): array
{
    $tokens = [];
    $length = strlen($expression);
    $offset = 0;
    while ($offset < $length) {
        if (preg_match('/\G\s+/A', $expression, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);
            continue;
        }
        if (preg_match('/\G(?:\d+(?:\.\d*)?|\.\d+)/A', $expression, $match, 0, $offset) === 1) {
            $value = (float) $match[0];
            if (!is_finite($value)) {
                throw new InvalidArgumentException('Scorecard expression contains a non-finite numeric literal.');
            }
            $tokens[] = ['type' => 'number', 'value' => $value];
            $offset += strlen($match[0]);
            continue;
        }
        if (preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/A', $expression, $match, 0, $offset) === 1) {
            $tokens[] = ['type' => 'variable', 'value' => strtoupper($match[0])];
            $offset += strlen($match[0]);
            continue;
        }
        $operator = $expression[$offset];
        if (str_contains('+-*/()', $operator)) {
            $tokens[] = ['type' => 'operator', 'value' => $operator];
            $offset++;
            continue;
        }
        throw new InvalidArgumentException('Scorecard expression contains a prohibited token.');
    }
    if ($tokens === []) {
        throw new InvalidArgumentException('Scorecard expression is required.');
    }
    return $tokens;
}

function yovel_admin_buying_scorecard_apply_operation(float $left, string $operator, float $right): float
{
    if ($operator === '/' && $right == 0.0) {
        throw new InvalidArgumentException('Scorecard expression cannot divide by zero.');
    }
    $result = match ($operator) {
        '+' => $left + $right,
        '-' => $left - $right,
        '*' => $left * $right,
        '/' => $left / $right,
        default => throw new InvalidArgumentException('Scorecard expression contains an invalid operator.'),
    };
    if (!is_finite($result)) {
        throw new InvalidArgumentException('Scorecard expression produced a non-finite result.');
    }
    return $result;
}

function yovel_admin_buying_scorecard_parse_factor(array $tokens, int &$index, array $variables): float
{
    $token = $tokens[$index] ?? null;
    if (!is_array($token)) {
        throw new InvalidArgumentException('Scorecard expression is incomplete.');
    }
    if ($token['type'] === 'operator' && in_array($token['value'], ['+', '-'], true)) {
        $index++;
        $value = yovel_admin_buying_scorecard_parse_factor($tokens, $index, $variables);
        $result = $token['value'] === '-' ? -$value : $value;
        if (!is_finite($result)) {
            throw new InvalidArgumentException('Scorecard expression produced a non-finite result.');
        }
        return $result;
    }
    if ($token['type'] === 'number') {
        $index++;
        return (float) $token['value'];
    }
    if ($token['type'] === 'variable') {
        $index++;
        $code = (string) $token['value'];
        if (!array_key_exists($code, $variables)) {
            throw new InvalidArgumentException('Scorecard expression uses a variable that is not registered.');
        }
        $value = $variables[$code];
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw new InvalidArgumentException('Scorecard expression variable values must be numeric.');
        }
        $numeric = (float) $value;
        if (!is_finite($numeric)) {
            throw new InvalidArgumentException('Scorecard expression variable values must be finite.');
        }
        return $numeric;
    }
    if ($token['type'] === 'operator' && $token['value'] === '(') {
        $index++;
        $result = yovel_admin_buying_scorecard_parse_expression($tokens, $index, $variables);
        $closing = $tokens[$index] ?? null;
        if (!is_array($closing) || $closing['type'] !== 'operator' || $closing['value'] !== ')') {
            throw new InvalidArgumentException('Scorecard expression has unbalanced parentheses.');
        }
        $index++;
        return $result;
    }
    throw new InvalidArgumentException('Scorecard expression has invalid syntax.');
}

function yovel_admin_buying_scorecard_parse_term(array $tokens, int &$index, array $variables): float
{
    $result = yovel_admin_buying_scorecard_parse_factor($tokens, $index, $variables);
    while (isset($tokens[$index])
        && $tokens[$index]['type'] === 'operator'
        && in_array($tokens[$index]['value'], ['*', '/'], true)) {
        $operator = (string) $tokens[$index]['value'];
        $index++;
        $result = yovel_admin_buying_scorecard_apply_operation(
            $result,
            $operator,
            yovel_admin_buying_scorecard_parse_factor($tokens, $index, $variables)
        );
    }
    return $result;
}

function yovel_admin_buying_scorecard_parse_expression(array $tokens, int &$index, array $variables): float
{
    $result = yovel_admin_buying_scorecard_parse_term($tokens, $index, $variables);
    while (isset($tokens[$index])
        && $tokens[$index]['type'] === 'operator'
        && in_array($tokens[$index]['value'], ['+', '-'], true)) {
        $operator = (string) $tokens[$index]['value'];
        $index++;
        $result = yovel_admin_buying_scorecard_apply_operation(
            $result,
            $operator,
            yovel_admin_buying_scorecard_parse_term($tokens, $index, $variables)
        );
    }
    return $result;
}

function yovel_admin_buying_scorecard_evaluate_expression(string $expression, array $variables): string
{
    $registered = [];
    foreach ($variables as $code => $value) {
        $normalizedCode = strtoupper(trim((string) $code));
        if (preg_match('/^[A-Z][A-Z0-9_]{0,79}$/', $normalizedCode) !== 1) {
            throw new InvalidArgumentException('Scorecard expression variable registration is invalid.');
        }
        $registered[$normalizedCode] = $value;
    }
    $tokens = yovel_admin_buying_scorecard_tokens(trim($expression));
    $index = 0;
    $result = yovel_admin_buying_scorecard_parse_expression($tokens, $index, $registered);
    if ($index !== count($tokens)) {
        throw new InvalidArgumentException('Scorecard expression contains trailing or prohibited syntax.');
    }
    if (!is_finite($result)) {
        throw new InvalidArgumentException('Scorecard expression produced a non-finite result.');
    }
    return number_format($result, 4, '.', '');
}

function yovel_admin_buying_scorecard_decimal(mixed $value, string $label, string $minimum, string $maximum): string
{
    $text = trim((string) $value);
    if ($text === '' || preg_match('/^\d{1,6}(?:\.\d{1,4})?$/', $text) !== 1) {
        throw new InvalidArgumentException($label . ' must be a finite non-negative number with up to four decimals.');
    }
    $normalized = number_format((float) $text, 4, '.', '');
    if (bccomp($normalized, $minimum, 4) < 0 || bccomp($normalized, $maximum, 4) > 0) {
        throw new InvalidArgumentException($label . ' must be between ' . $minimum . ' and ' . $maximum . '.');
    }
    return $normalized;
}

function yovel_admin_buying_scorecard_code(mixed $value, string $label): string
{
    $code = strtoupper(trim((string) $value));
    if (preg_match('/^[A-Z][A-Z0-9_]{0,79}$/', $code) !== 1) {
        throw new InvalidArgumentException($label . ' must begin with a letter and use only letters, numbers, and underscores.');
    }
    return $code;
}

function yovel_admin_buying_scorecard_rows(array $input, string $arrayKey, string $textKey, array $columns): array
{
    $rows = $input[$arrayKey] ?? [];
    if (!is_array($rows)) {
        throw new InvalidArgumentException('Scorecard ' . $arrayKey . ' rows are invalid.');
    }
    if ($rows === [] && trim((string) ($input[$textKey] ?? '')) !== '') {
        foreach (preg_split('/\R/', (string) $input[$textKey]) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) > count($columns)) {
                throw new InvalidArgumentException('Scorecard ' . $arrayKey . ' text contains too many columns.');
            }
            $rows[] = array_combine($columns, array_pad($parts, count($columns), ''));
        }
    }
    return array_values($rows);
}

function yovel_admin_buying_scorecard_normalize_definition(array $input): array
{
    $supplierKey = yovel_admin_buying_optional_uuid($input, 'supplier_key', 'Supplier');
    if ($supplierKey === null) {
        throw new InvalidArgumentException('Supplier is required for a scorecard.');
    }
    $scorecardKey = yovel_admin_buying_optional_uuid($input, 'scorecard_key', 'Scorecard');
    $periodType = strtoupper(trim((string) ($input['period_type'] ?? 'MONTH')));
    if (!in_array($periodType, ['WEEK', 'MONTH', 'YEAR'], true)) {
        throw new InvalidArgumentException('Scorecard period type is invalid.');
    }
    $status = strtoupper(trim((string) ($input['scorecard_status'] ?? 'ACTIVE')));
    if (!in_array($status, ['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED'], true)) {
        throw new InvalidArgumentException('Scorecard status is invalid.');
    }

    $variableRows = yovel_admin_buying_scorecard_rows(
        $input,
        'variables',
        'variables_text',
        ['variable_code', 'variable_label', 'metric_path', 'description']
    );
    if ($variableRows === [] || count($variableRows) > 50) {
        throw new InvalidArgumentException('A scorecard requires between 1 and 50 registered variables.');
    }
    $allowedMetricPaths = [
        'purchase_order_count',
        'purchase_order_value',
        'active_purchase_order_count',
        'supplier_quotation_count',
        'rfq_count',
        'delivered_order_count',
        'ordered_delivery_count',
        'quality_score',
    ];
    $variables = [];
    $variableCodes = [];
    foreach ($variableRows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Scorecard variable rows are invalid.');
        }
        $code = yovel_admin_buying_scorecard_code($row['variable_code'] ?? '', 'Variable code');
        if (isset($variableCodes[$code])) {
            throw new InvalidArgumentException('Scorecard variable codes must be unique.');
        }
        $label = trim((string) ($row['variable_label'] ?? ''));
        $metricPath = strtolower(trim((string) ($row['metric_path'] ?? '')));
        $description = trim((string) ($row['description'] ?? ''));
        if ($label === '' || strlen($label) > 160) {
            throw new InvalidArgumentException('Each scorecard variable requires a label of at most 160 characters.');
        }
        if (!in_array($metricPath, $allowedMetricPaths, true)) {
            throw new InvalidArgumentException('Scorecard metric path is not in the normalized metric registry.');
        }
        if (strlen($description) > 10000) {
            throw new InvalidArgumentException('Scorecard variable description cannot exceed 10000 characters.');
        }
        $variableCodes[$code] = 1;
        $variables[] = [
            'variable_code' => $code,
            'variable_label' => $label,
            'metric_path' => $metricPath,
            'description' => $description === '' ? null : $description,
        ];
    }

    $criteriaRows = yovel_admin_buying_scorecard_rows(
        $input,
        'criteria',
        'criteria_text',
        ['criteria_code', 'criteria_name', 'max_score', 'weight_percentage', 'formula_expression']
    );
    if ($criteriaRows === [] || count($criteriaRows) > 50) {
        throw new InvalidArgumentException('A scorecard requires between 1 and 50 criteria rows.');
    }
    $criteria = [];
    $criteriaCodes = [];
    $weightTotal = '0.0000';
    foreach ($criteriaRows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Scorecard criteria rows are invalid.');
        }
        $code = yovel_admin_buying_scorecard_code($row['criteria_code'] ?? '', 'Criteria code');
        if (isset($criteriaCodes[$code])) {
            throw new InvalidArgumentException('Scorecard criteria codes must be unique.');
        }
        $name = trim((string) ($row['criteria_name'] ?? ''));
        if ($name === '' || strlen($name) > 160) {
            throw new InvalidArgumentException('Each scorecard criterion requires a name of at most 160 characters.');
        }
        $maxScore = yovel_admin_buying_scorecard_decimal($row['max_score'] ?? '', 'Criteria maximum score', '0.0001', '100.0000');
        $weight = yovel_admin_buying_scorecard_decimal($row['weight_percentage'] ?? '', 'Criteria weight', '0.0001', '100.0000');
        $formula = trim((string) ($row['formula_expression'] ?? ''));
        if (strlen($formula) > 500) {
            throw new InvalidArgumentException('Scorecard criteria expression cannot exceed 500 characters.');
        }
        yovel_admin_buying_scorecard_evaluate_expression($formula, $variableCodes);
        $weightTotal = bcadd($weightTotal, $weight, 4);
        $criteriaCodes[$code] = true;
        $criteria[] = [
            'criteria_code' => $code,
            'criteria_name' => $name,
            'max_score' => $maxScore,
            'weight_percentage' => $weight,
            'formula_expression' => $formula,
        ];
    }
    if (bccomp($weightTotal, '100.0000', 4) !== 0) {
        throw new InvalidArgumentException('Scorecard criteria weights must total exactly 100.0000.');
    }

    $standingRows = yovel_admin_buying_scorecard_rows(
        $input,
        'standings',
        'standings_text',
        [
            'standing_code', 'standing_name', 'minimum_score', 'maximum_score', 'color_token',
            'warn_rfqs', 'prevent_rfqs', 'warn_purchase_orders', 'prevent_purchase_orders',
            'notify_supplier', 'notify_employee', 'employee_key',
        ]
    );
    if ($standingRows === [] || count($standingRows) > 25) {
        throw new InvalidArgumentException('A scorecard requires between 1 and 25 standing rows.');
    }
    $standings = [];
    $standingCodes = [];
    foreach ($standingRows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Scorecard standing rows are invalid.');
        }
        $code = yovel_admin_buying_scorecard_code($row['standing_code'] ?? '', 'Standing code');
        if (isset($standingCodes[$code])) {
            throw new InvalidArgumentException('Scorecard standing codes must be unique.');
        }
        $name = trim((string) ($row['standing_name'] ?? ''));
        $color = strtolower(trim((string) ($row['color_token'] ?? 'neutral')));
        if ($name === '' || strlen($name) > 160) {
            throw new InvalidArgumentException('Each scorecard standing requires a name of at most 160 characters.');
        }
        if (preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $color) !== 1) {
            throw new InvalidArgumentException('Scorecard standing color token is invalid.');
        }
        $minimum = yovel_admin_buying_scorecard_decimal($row['minimum_score'] ?? '', 'Standing minimum score', '0.0000', '100.0000');
        $maximum = yovel_admin_buying_scorecard_decimal($row['maximum_score'] ?? '', 'Standing maximum score', '0.0000', '100.0000');
        if (bccomp($minimum, $maximum, 4) >= 0) {
            throw new InvalidArgumentException('Each scorecard standing maximum must exceed its minimum.');
        }
        $preventRfqs = yovel_admin_buying_bool($row, 'prevent_rfqs');
        $preventOrders = yovel_admin_buying_bool($row, 'prevent_purchase_orders');
        $notifyEmployee = yovel_admin_buying_bool($row, 'notify_employee');
        $employeeKey = yovel_admin_buying_optional_uuid($row, 'employee_key', 'Standing employee');
        if ($notifyEmployee === 1 && $employeeKey === null) {
            throw new InvalidArgumentException('A scorecard standing employee is required when employee notification is enabled.');
        }
        $standingCodes[$code] = true;
        $standings[] = [
            'standing_code' => $code,
            'standing_name' => $name,
            'color_token' => $color,
            'minimum_score' => $minimum,
            'maximum_score' => $maximum,
            'warn_rfqs' => max(yovel_admin_buying_bool($row, 'warn_rfqs'), $preventRfqs),
            'warn_purchase_orders' => max(yovel_admin_buying_bool($row, 'warn_purchase_orders'), $preventOrders),
            'prevent_rfqs' => $preventRfqs,
            'prevent_purchase_orders' => $preventOrders,
            'notify_supplier' => yovel_admin_buying_bool($row, 'notify_supplier'),
            'notify_employee' => $notifyEmployee,
            'employee_key' => $employeeKey,
        ];
    }
    usort($standings, static fn (array $left, array $right): int => bccomp($left['minimum_score'], $right['minimum_score'], 4));
    if (bccomp($standings[0]['minimum_score'], '0.0000', 4) !== 0
        || bccomp($standings[count($standings) - 1]['maximum_score'], '100.0000', 4) !== 0) {
        throw new InvalidArgumentException('Scorecard standings must fully cover 0 through 100.');
    }
    for ($index = 1; $index < count($standings); $index++) {
        if (bccomp($standings[$index - 1]['maximum_score'], $standings[$index]['minimum_score'], 4) !== 0) {
            throw new InvalidArgumentException('Scorecard standing ranges must be contiguous and non-overlapping.');
        }
    }

    $weightingExpression = trim((string) ($input['weighting_expression'] ?? ''));
    if (strlen($weightingExpression) > 500) {
        throw new InvalidArgumentException('Scorecard weighting expression cannot exceed 500 characters.');
    }
    yovel_admin_buying_scorecard_evaluate_expression($weightingExpression, $variableCodes);

    return [
        'scorecard_key' => $scorecardKey,
        'supplier_key' => $supplierKey,
        'period_type' => $periodType,
        'weighting_expression' => $weightingExpression,
        'scorecard_status' => $status,
        'variables' => $variables,
        'criteria' => $criteria,
        'standings' => $standings,
    ];
}

function yovel_admin_buying_scorecard_read(ADOConnection $db, string $companyKeyHash, string $scorecardKey): ?array
{
    $definition = $db->GetRow(
        "SELECT * FROM project_company_buying_scorecard_definition
        WHERE company_key_hash = ? AND scorecard_key = ? AND scorecard_status <> 'DELETED'
        LIMIT 1",
        [$companyKeyHash, $scorecardKey]
    );
    if (!is_array($definition) || $definition === []) {
        return null;
    }
    $definition['variables'] = $db->GetAll(
        "SELECT * FROM project_company_buying_scorecard_variable
        WHERE company_key_hash = ? AND scorecard_key = ? AND row_status = 'ACTIVE'
        ORDER BY x_id",
        [$companyKeyHash, $scorecardKey]
    ) ?: [];
    $definition['criteria'] = $db->GetAll(
        "SELECT * FROM project_company_buying_scorecard_criteria
        WHERE company_key_hash = ? AND scorecard_key = ? AND row_status = 'ACTIVE'
        ORDER BY x_id",
        [$companyKeyHash, $scorecardKey]
    ) ?: [];
    $definition['standings'] = $db->GetAll(
        "SELECT * FROM project_company_buying_scorecard_standing
        WHERE company_key_hash = ? AND scorecard_key = ? AND row_status = 'ACTIVE'
        ORDER BY minimum_score, x_id",
        [$companyKeyHash, $scorecardKey]
    ) ?: [];
    return $definition;
}

function yovel_admin_buying_scorecard_definition(array $company, string $scorecardKey): ?array
{
    yovel_admin_buying_schema();
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    if (!yovel_admin_is_uuid($scorecardKey)) {
        throw new InvalidArgumentException('Scorecard key is invalid.');
    }
    return yovel_admin_buying_scorecard_read(bx_db(), $companyKeyHash, strtolower($scorecardKey));
}

function yovel_admin_buying_scorecards(array $company): array
{
    yovel_admin_buying_schema();
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    $keys = bx_db()->GetCol(
        "SELECT scorecard_key FROM project_company_buying_scorecard_definition
        WHERE company_key_hash = ? AND scorecard_status <> 'DELETED'
        ORDER BY updated_at DESC, x_id DESC",
        [$companyKeyHash]
    );
    $definitions = [];
    foreach (is_array($keys) ? $keys : [] as $key) {
        $definition = yovel_admin_buying_scorecard_read(bx_db(), $companyKeyHash, (string) $key);
        if ($definition !== null) {
            $definitions[] = $definition;
        }
    }
    return $definitions;
}

function yovel_admin_buying_scorecard_verify_definition(array $saved, array $expected): void
{
    foreach (['scorecard_key', 'supplier_key', 'period_type', 'weighting_expression', 'scorecard_status'] as $field) {
        $expectedValue = $field === 'scorecard_key' ? $saved[$field] : $expected[$field];
        if ((string) ($saved[$field] ?? '') !== (string) $expectedValue) {
            throw new RuntimeException('Scorecard definition read-back verification failed.');
        }
    }
    foreach (['variables', 'criteria', 'standings'] as $collection) {
        if (count($saved[$collection] ?? []) !== count($expected[$collection])) {
            throw new RuntimeException('Scorecard ' . $collection . ' read-back verification failed.');
        }
        foreach ($expected[$collection] as $index => $expectedRow) {
            $savedRow = $saved[$collection][$index] ?? [];
            foreach ($expectedRow as $field => $value) {
                if ((string) ($savedRow[$field] ?? '') !== (string) ($value ?? '')) {
                    throw new RuntimeException('Scorecard ' . $collection . ' read-back verification failed.');
                }
            }
        }
    }
}

function yovel_admin_buying_save_scorecard_definition(
    ADOConnection $db,
    array $company,
    array $admin,
    array $input
): array {
    yovel_admin_buying_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);
    $normalized = yovel_admin_buying_scorecard_normalize_definition($input);
    $supplier = yovel_admin_buying_supplier($company, $normalized['supplier_key']);
    if ($supplier === null || (string) $supplier['supplier_status'] !== 'ACTIVE') {
        throw new InvalidArgumentException('An active company-scoped supplier is required for a scorecard.');
    }

    return yovel_admin_buying_in_transaction($db, static function () use (
        $db,
        $companyKey,
        $companyKeyHash,
        $adminKey,
        $normalized
    ): array {
        $supplierLock = $db->GetRow(
            "SELECT supplier_key FROM project_company_buying_supplier
            WHERE company_key_hash = ? AND supplier_key = ? AND supplier_status = 'ACTIVE'
            FOR UPDATE",
            [$companyKeyHash, $normalized['supplier_key']]
        );
        if (!is_array($supplierLock) || $supplierLock === []) {
            throw new RuntimeException('The scorecard supplier is no longer available.');
        }
        $existing = $normalized['scorecard_key'] !== null
            ? $db->GetRow(
                "SELECT * FROM project_company_buying_scorecard_definition
                WHERE company_key_hash = ? AND scorecard_key = ? AND scorecard_status <> 'DELETED'
                FOR UPDATE",
                [$companyKeyHash, $normalized['scorecard_key']]
            )
            : $db->GetRow(
                "SELECT * FROM project_company_buying_scorecard_definition
                WHERE company_key_hash = ? AND supplier_key = ? AND scorecard_status <> 'DELETED'
                FOR UPDATE",
                [$companyKeyHash, $normalized['supplier_key']]
            );
        $isUpdate = is_array($existing) && $existing !== [];
        if ($normalized['scorecard_key'] !== null && !$isUpdate) {
            throw new RuntimeException('The scorecard definition was not found for this company.');
        }
        if ($isUpdate && (string) $existing['scorecard_status'] === 'ARCHIVED') {
            throw new RuntimeException('An archived scorecard definition cannot be changed.');
        }
        $scorecardKey = $isUpdate ? (string) $existing['scorecard_key'] : bx_uuid();
        if ($isUpdate) {
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_buying_scorecard_definition
                SET supplier_key = ?, period_type = ?, weighting_expression = ?, scorecard_status = ?, updated_by_admin_key = ?
                WHERE company_key_hash = ? AND scorecard_key = ?",
                [
                    $normalized['supplier_key'], $normalized['period_type'], $normalized['weighting_expression'],
                    $normalized['scorecard_status'], $adminKey, $companyKeyHash, $scorecardKey,
                ],
                'Buying scorecard definition update'
            );
        } else {
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_scorecard_definition (
                    scorecard_key, company_key, company_key_hash, supplier_key, period_type,
                    weighting_expression, scorecard_status, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $scorecardKey, $companyKey, $companyKeyHash, $normalized['supplier_key'],
                    $normalized['period_type'], $normalized['weighting_expression'], $normalized['scorecard_status'],
                    $adminKey, $adminKey,
                ],
                'Buying scorecard definition creation'
            );
        }

        $childKeyMap = [];
        foreach ([
            'criteria' => ['scorecard_criteria_key', 'criteria_code'],
            'variable' => ['scorecard_variable_key', 'variable_code'],
            'standing' => ['scorecard_standing_key', 'standing_code'],
        ] as $child => [$keyColumn, $codeColumn]) {
            $childKeyMap[$child] = [];
            $lockedChildren = $db->GetAll(
                'SELECT ' . $keyColumn . ', ' . $codeColumn . ' FROM project_company_buying_scorecard_' . $child
                    . ' WHERE company_key_hash = ? AND scorecard_key = ? FOR UPDATE',
                [$companyKeyHash, $scorecardKey]
            );
            foreach (is_array($lockedChildren) ? $lockedChildren : [] as $lockedChild) {
                $childKeyMap[$child][(string) $lockedChild[$codeColumn]] = (string) $lockedChild[$keyColumn];
            }
            yovel_admin_db_execute(
                $db,
                'DELETE FROM project_company_buying_scorecard_' . $child . ' WHERE company_key_hash = ? AND scorecard_key = ?',
                [$companyKeyHash, $scorecardKey],
                'Buying scorecard ' . $child . ' replacement'
            );
        }
        foreach ($normalized['variables'] as $index => $row) {
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_scorecard_variable (
                    scorecard_variable_key, company_key, company_key_hash, scorecard_key,
                    variable_code, variable_label, metric_path, description, row_status,
                    created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                [
                    $childKeyMap['variable'][$row['variable_code']] ?? bx_uuid(), $companyKey, $companyKeyHash, $scorecardKey, $row['variable_code'],
                    $row['variable_label'], $row['metric_path'], $row['description'], $adminKey, $adminKey,
                ],
                'Buying scorecard variable creation'
            );
            if (is_callable($GLOBALS['yovel_admin_buying_scorecard_child_write_hook'] ?? null)) {
                ($GLOBALS['yovel_admin_buying_scorecard_child_write_hook'])('variable', $index);
            }
        }
        foreach ($normalized['criteria'] as $index => $row) {
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_scorecard_criteria (
                    scorecard_criteria_key, company_key, company_key_hash, scorecard_key,
                    criteria_code, criteria_name, max_score, weight_percentage, formula_expression,
                    row_status, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                [
                    $childKeyMap['criteria'][$row['criteria_code']] ?? bx_uuid(), $companyKey, $companyKeyHash, $scorecardKey, $row['criteria_code'],
                    $row['criteria_name'], $row['max_score'], $row['weight_percentage'],
                    $row['formula_expression'], $adminKey, $adminKey,
                ],
                'Buying scorecard criteria creation'
            );
            if (is_callable($GLOBALS['yovel_admin_buying_scorecard_child_write_hook'] ?? null)) {
                ($GLOBALS['yovel_admin_buying_scorecard_child_write_hook'])('criteria', $index);
            }
        }
        foreach ($normalized['standings'] as $index => $row) {
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_scorecard_standing (
                    scorecard_standing_key, company_key, company_key_hash, scorecard_key,
                    standing_code, standing_name, color_token, minimum_score, maximum_score,
                    warn_rfqs, warn_purchase_orders, prevent_rfqs, prevent_purchase_orders,
                    notify_supplier, notify_employee, employee_key, row_status,
                    created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                [
                    $childKeyMap['standing'][$row['standing_code']] ?? bx_uuid(), $companyKey, $companyKeyHash, $scorecardKey, $row['standing_code'],
                    $row['standing_name'], $row['color_token'], $row['minimum_score'], $row['maximum_score'],
                    $row['warn_rfqs'], $row['warn_purchase_orders'], $row['prevent_rfqs'],
                    $row['prevent_purchase_orders'], $row['notify_supplier'], $row['notify_employee'],
                    $row['employee_key'], $adminKey, $adminKey,
                ],
                'Buying scorecard standing creation'
            );
            if (is_callable($GLOBALS['yovel_admin_buying_scorecard_child_write_hook'] ?? null)) {
                ($GLOBALS['yovel_admin_buying_scorecard_child_write_hook'])('standing', $index);
            }
        }

        $saved = yovel_admin_buying_scorecard_read($db, $companyKeyHash, $scorecardKey);
        if ($saved === null) {
            throw new RuntimeException('Scorecard definition read-back verification failed.');
        }
        yovel_admin_buying_scorecard_verify_definition($saved, $normalized);
        bx_audit($isUpdate ? 'UPDATE' : 'CREATE', 'project_company_buying_scorecard_definition', $scorecardKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'supplier_key' => $normalized['supplier_key'],
            'period_type' => $normalized['period_type'],
            'scorecard_status' => $normalized['scorecard_status'],
            'criteria_count' => count($normalized['criteria']),
            'variable_count' => count($normalized['variables']),
            'standing_count' => count($normalized['standings']),
            'admin_key' => $adminKey,
        ], 'Company administrator saved a supplier scorecard definition.');
        return $saved;
    });
}

function yovel_admin_buying_scorecard_date(string $value, string $label): string
{
    $value = trim($value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($label . ' must be a valid date.');
    }
    return $value;
}

function yovel_admin_buying_scorecard_metric_snapshot(
    ADOConnection $db,
    array $company,
    array $definition,
    string $periodStart,
    string $periodEnd
): array {
    $provider = $GLOBALS['yovel_admin_buying_scorecard_metric_provider'] ?? null;
    if (is_callable($provider)) {
        $provided = $provider($company, $definition, $periodStart, $periodEnd);
        if (!is_array($provided)) {
            throw new RuntimeException('The normalized scorecard metric provider returned an invalid snapshot.');
        }
    } else {
        $companyKeyHash = (string) $company['company_key_hash'];
        $supplierKey = (string) $definition['supplier_key'];
        $provided = [
            'purchase_order_count' => (int) $db->GetOne(
                "SELECT COUNT(*) FROM project_company_buying_purchase_order
                WHERE company_key_hash = ? AND supplier_key = ? AND transaction_date BETWEEN ? AND ?
                  AND document_status NOT IN ('CANCELLED','ARCHIVED')",
                [$companyKeyHash, $supplierKey, $periodStart, $periodEnd]
            ),
            'purchase_order_value' => (string) $db->GetOne(
                "SELECT COALESCE(SUM(grand_total), 0) FROM project_company_buying_purchase_order
                WHERE company_key_hash = ? AND supplier_key = ? AND transaction_date BETWEEN ? AND ?
                  AND document_status NOT IN ('CANCELLED','ARCHIVED')",
                [$companyKeyHash, $supplierKey, $periodStart, $periodEnd]
            ),
            'active_purchase_order_count' => (int) $db->GetOne(
                "SELECT COUNT(*) FROM project_company_buying_purchase_order
                WHERE company_key_hash = ? AND supplier_key = ? AND transaction_date BETWEEN ? AND ?
                  AND document_status NOT IN ('CANCELLED','ARCHIVED','COMPLETED','CLOSED')",
                [$companyKeyHash, $supplierKey, $periodStart, $periodEnd]
            ),
            'supplier_quotation_count' => (int) $db->GetOne(
                "SELECT COUNT(*) FROM project_company_buying_supplier_quotation
                WHERE company_key_hash = ? AND supplier_key = ? AND transaction_date BETWEEN ? AND ?
                  AND document_status NOT IN ('CANCELLED','ARCHIVED')",
                [$companyKeyHash, $supplierKey, $periodStart, $periodEnd]
            ),
            'rfq_count' => (int) $db->GetOne(
                "SELECT COUNT(*) FROM project_company_buying_rfq_supplier supplier_row
                INNER JOIN project_company_buying_rfq rfq ON rfq.company_key_hash = supplier_row.company_key_hash AND rfq.rfq_key = supplier_row.rfq_key
                WHERE supplier_row.company_key_hash = ? AND supplier_row.supplier_key = ?
                  AND rfq.transaction_date BETWEEN ? AND ? AND supplier_row.row_status = 'ACTIVE'
                  AND rfq.document_status NOT IN ('CANCELLED','ARCHIVED')",
                [$companyKeyHash, $supplierKey, $periodStart, $periodEnd]
            ),
        ];
    }

    $snapshot = [];
    foreach ($definition['variables'] as $variable) {
        $path = (string) $variable['metric_path'];
        if (!array_key_exists($path, $provided)) {
            throw new RuntimeException('Normalized scorecard metric is unavailable: ' . $path . '.');
        }
        $value = $provided[$path];
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw new RuntimeException('Normalized scorecard metrics must be numeric.');
        }
        $numeric = (float) $value;
        if (!is_finite($numeric)) {
            throw new RuntimeException('Normalized scorecard metrics must be finite.');
        }
        $snapshot[(string) $variable['variable_code']] = number_format($numeric, 4, '.', '');
    }
    ksort($snapshot);
    return $snapshot;
}

function yovel_admin_buying_scorecard_period_read(ADOConnection $db, string $companyKeyHash, string $periodKey): ?array
{
    $period = $db->GetRow(
        "SELECT * FROM project_company_buying_scorecard_period
        WHERE company_key_hash = ? AND scorecard_period_key = ? AND period_status <> 'CANCELLED'
        LIMIT 1",
        [$companyKeyHash, $periodKey]
    );
    if (!is_array($period) || $period === []) {
        return null;
    }
    $period['scores'] = $db->GetAll(
        "SELECT * FROM project_company_buying_scorecard_period_score
        WHERE company_key_hash = ? AND scorecard_period_key = ? AND row_status = 'ACTIVE'
        ORDER BY x_id",
        [$companyKeyHash, $periodKey]
    ) ?: [];
    $period['standing'] = $period['standing_key'] === null ? null : $db->GetRow(
        "SELECT * FROM project_company_buying_scorecard_standing
        WHERE company_key_hash = ? AND scorecard_standing_key = ? AND row_status = 'ACTIVE'
        LIMIT 1",
        [$companyKeyHash, $period['standing_key']]
    );
    return $period;
}

function yovel_admin_buying_scorecard_notification(array $company, array $period): array
{
    $standing = is_array($period['standing'] ?? null) ? $period['standing'] : [];
    $requested = (int) ($standing['notify_supplier'] ?? 0) === 1 || (int) ($standing['notify_employee'] ?? 0) === 1;
    $available = function_exists('yovel_admin_operations_create_notification_handoff');
    $state = [
        'available' => $available,
        'requested' => $requested,
        'delivered' => false,
        'blocking' => false,
        'message' => $available ? 'Operations notification contract available.' : 'Operations notification contract unavailable; the local score and restrictions remain active.',
    ];
    if (!$requested || !$available) {
        return $state;
    }
    try {
        $response = yovel_admin_operations_create_notification_handoff($company, [
            'source_module' => 'buying-procurement',
            'source_record_type' => 'supplier-scorecard-period',
            'source_record_key' => (string) $period['scorecard_period_key'],
            'supplier_key' => (string) $period['supplier_key'],
            'scorecard_key' => (string) $period['scorecard_key'],
            'period_start' => (string) $period['period_start'],
            'period_end' => (string) $period['period_end'],
            'total_score' => (string) $period['total_score'],
            'standing_code' => (string) ($standing['standing_code'] ?? ''),
            'notify_supplier' => (int) ($standing['notify_supplier'] ?? 0) === 1,
            'notify_employee' => (int) ($standing['notify_employee'] ?? 0) === 1,
            'employee_key' => $standing['employee_key'] ?? null,
        ]);
        if (!is_array($response)) {
            throw new RuntimeException('Operations returned an invalid notification response.');
        }
        $state['delivered'] = true;
        $state['message'] = 'Operations accepted the supplier scorecard notification.';
        $state['response'] = $response;
    } catch (Throwable $error) {
        $state['message'] = 'Operations notification failed without rolling back the local score: ' . $error->getMessage();
    }
    return $state;
}

function yovel_admin_buying_calculate_scorecard_period(
    ADOConnection $db,
    array $company,
    array $admin,
    string $scorecardKey,
    string $periodStart,
    string $periodEnd
): array {
    yovel_admin_buying_schema();
    [$companyKey, $companyKeyHash, $adminKey] = yovel_admin_buying_scope($company, $admin);
    if (!yovel_admin_is_uuid($scorecardKey)) {
        throw new InvalidArgumentException('Scorecard key is invalid.');
    }
    $scorecardKey = strtolower($scorecardKey);
    $periodStart = yovel_admin_buying_scorecard_date($periodStart, 'Scorecard period start');
    $periodEnd = yovel_admin_buying_scorecard_date($periodEnd, 'Scorecard period end');
    if ($periodStart > $periodEnd) {
        throw new InvalidArgumentException('Scorecard period end cannot precede its start.');
    }

    $period = yovel_admin_buying_in_transaction($db, static function () use (
        $db,
        $company,
        $companyKey,
        $companyKeyHash,
        $adminKey,
        $scorecardKey,
        $periodStart,
        $periodEnd
    ): array {
        $locked = $db->GetRow(
            "SELECT * FROM project_company_buying_scorecard_definition
            WHERE company_key_hash = ? AND scorecard_key = ? AND scorecard_status = 'ACTIVE'
            FOR UPDATE",
            [$companyKeyHash, $scorecardKey]
        );
        if (!is_array($locked) || $locked === []) {
            throw new RuntimeException('An active scorecard definition was not found for this company.');
        }
        $duplicate = $db->GetRow(
            "SELECT scorecard_period_key FROM project_company_buying_scorecard_period
            WHERE company_key_hash = ? AND scorecard_key = ? AND period_start = ? AND period_end = ?
            FOR UPDATE",
            [$companyKeyHash, $scorecardKey, $periodStart, $periodEnd]
        );
        if (is_array($duplicate) && $duplicate !== []) {
            throw new RuntimeException('This scorecard period already exists.');
        }
        $definition = yovel_admin_buying_scorecard_read($db, $companyKeyHash, $scorecardKey);
        if ($definition === null || $definition['criteria'] === [] || $definition['standings'] === []) {
            throw new RuntimeException('The scorecard definition is incomplete.');
        }
        $metrics = yovel_admin_buying_scorecard_metric_snapshot($db, $company, $definition, $periodStart, $periodEnd);
        $scores = [];
        $total = '0.0000';
        foreach ($definition['criteria'] as $criterion) {
            $score = yovel_admin_buying_scorecard_evaluate_expression((string) $criterion['formula_expression'], $metrics);
            if (bccomp($score, '0.0000', 4) < 0 || bccomp($score, (string) $criterion['max_score'], 4) > 0) {
                throw new RuntimeException('A scorecard criteria result is outside its configured range.');
            }
            $weighted = bcmul(
                bcdiv($score, (string) $criterion['max_score'], 8),
                (string) $criterion['weight_percentage'],
                4
            );
            $total = bcadd($total, $weighted, 4);
            $scores[] = ['criterion' => $criterion, 'criteria_score' => $score, 'weighted_score' => $weighted];
        }
        if (bccomp($total, '0.0000', 4) < 0 || bccomp($total, '100.0000', 4) > 0) {
            throw new RuntimeException('The scorecard total is outside 0 through 100.');
        }
        $standing = null;
        foreach ($definition['standings'] as $index => $candidate) {
            $isLast = $index === count($definition['standings']) - 1;
            if (bccomp($total, (string) $candidate['minimum_score'], 4) >= 0
                && ($isLast
                    ? bccomp($total, (string) $candidate['maximum_score'], 4) <= 0
                    : bccomp($total, (string) $candidate['maximum_score'], 4) < 0)) {
                $standing = $candidate;
                break;
            }
        }
        if (!is_array($standing)) {
            throw new RuntimeException('The scorecard total did not map to a configured standing.');
        }

        $periodKey = bx_uuid();
        $metricJson = json_encode($metrics, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_buying_scorecard_period (
                scorecard_period_key, company_key, company_key_hash, scorecard_key, supplier_key,
                period_start, period_end, total_score, standing_key, metric_snapshot_json,
                period_status, created_by_admin_key, updated_by_admin_key
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CALCULATED', ?, ?)",
            [
                $periodKey, $companyKey, $companyKeyHash, $scorecardKey, $definition['supplier_key'],
                $periodStart, $periodEnd, $total, $standing['scorecard_standing_key'], $metricJson,
                $adminKey, $adminKey,
            ],
            'Buying scorecard period creation'
        );
        foreach ($scores as $index => $scoreRow) {
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_buying_scorecard_period_score (
                    scorecard_period_score_key, company_key, company_key_hash, scorecard_period_key,
                    scorecard_criteria_key, criteria_score, weighted_score, variable_snapshot_json,
                    row_status, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)",
                [
                    bx_uuid(), $companyKey, $companyKeyHash, $periodKey,
                    $scoreRow['criterion']['scorecard_criteria_key'], $scoreRow['criteria_score'],
                    $scoreRow['weighted_score'], $metricJson, $adminKey, $adminKey,
                ],
                'Buying scorecard period-score creation'
            );
            if (is_callable($GLOBALS['yovel_admin_buying_scorecard_child_write_hook'] ?? null)) {
                ($GLOBALS['yovel_admin_buying_scorecard_child_write_hook'])('period_score', $index);
            }
        }
        $saved = yovel_admin_buying_scorecard_period_read($db, $companyKeyHash, $periodKey);
        if ($saved === null
            || (string) $saved['scorecard_key'] !== $scorecardKey
            || (string) $saved['supplier_key'] !== (string) $definition['supplier_key']
            || (string) $saved['period_start'] !== $periodStart
            || (string) $saved['period_end'] !== $periodEnd
            || (string) $saved['total_score'] !== $total
            || (string) $saved['standing_key'] !== (string) $standing['scorecard_standing_key']
            || (string) $saved['metric_snapshot_json'] !== $metricJson
            || (string) $saved['period_status'] !== 'CALCULATED'
            || count($saved['scores']) !== count($scores)) {
            throw new RuntimeException('Scorecard period read-back verification failed.');
        }
        foreach ($scores as $index => $expectedScore) {
            $savedScore = $saved['scores'][$index];
            if ((string) $savedScore['scorecard_criteria_key'] !== (string) $expectedScore['criterion']['scorecard_criteria_key']
                || (string) $savedScore['criteria_score'] !== $expectedScore['criteria_score']
                || (string) $savedScore['weighted_score'] !== $expectedScore['weighted_score']
                || (string) $savedScore['variable_snapshot_json'] !== $metricJson) {
                throw new RuntimeException('Scorecard period-score read-back verification failed.');
            }
        }
        bx_audit('CALCULATE', 'project_company_buying_scorecard_period', $periodKey, [
            'company_key' => $companyKey,
            'company_key_hash' => $companyKeyHash,
            'scorecard_key' => $scorecardKey,
            'supplier_key' => $definition['supplier_key'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_score' => $total,
            'standing_code' => $standing['standing_code'],
            'admin_key' => $adminKey,
        ], 'Company administrator calculated a supplier scorecard period.');
        return $saved;
    });

    $period['notification'] = yovel_admin_buying_scorecard_notification($company, $period);
    return $period;
}

function yovel_admin_buying_supplier_restrictions(array $company, string $supplierKey): array
{
    yovel_admin_buying_schema();
    [, $companyKeyHash] = yovel_admin_buying_read_scope($company);
    if (!yovel_admin_is_uuid($supplierKey)) {
        throw new InvalidArgumentException('Supplier key is invalid.');
    }
    $supplier = yovel_admin_buying_supplier($company, strtolower($supplierKey));
    if ($supplier === null) {
        throw new RuntimeException('The supplier was not found for this company.');
    }
    $latest = bx_db()->GetRow(
        "SELECT period.scorecard_period_key, period.total_score, period.period_start, period.period_end,
            standing.*
        FROM project_company_buying_scorecard_period period
        INNER JOIN project_company_buying_scorecard_standing standing
            ON standing.company_key_hash = period.company_key_hash
           AND standing.scorecard_standing_key = period.standing_key
           AND standing.row_status = 'ACTIVE'
        WHERE period.company_key_hash = ? AND period.supplier_key = ?
          AND period.period_status IN ('CALCULATED','PUBLISHED')
        ORDER BY period.period_end DESC, period.x_id DESC
        LIMIT 1",
        [$companyKeyHash, strtolower($supplierKey)]
    );
    $standing = is_array($latest) && $latest !== [] ? $latest : [];
    $preventRfqs = (int) ($supplier['prevent_rfqs'] ?? 0) === 1 || (int) ($standing['prevent_rfqs'] ?? 0) === 1;
    $preventOrders = (int) ($supplier['prevent_purchase_orders'] ?? 0) === 1 || (int) ($standing['prevent_purchase_orders'] ?? 0) === 1;
    return [
        'supplier_key' => strtolower($supplierKey),
        'scorecard_period_key' => $standing['scorecard_period_key'] ?? null,
        'standing_code' => $standing['standing_code'] ?? null,
        'standing_name' => $standing['standing_name'] ?? null,
        'total_score' => $standing['total_score'] ?? null,
        'warn_rfqs' => $preventRfqs || (int) ($supplier['warn_rfqs'] ?? 0) === 1 || (int) ($standing['warn_rfqs'] ?? 0) === 1,
        'prevent_rfqs' => $preventRfqs,
        'warn_purchase_orders' => $preventOrders || (int) ($supplier['warn_purchase_orders'] ?? 0) === 1 || (int) ($standing['warn_purchase_orders'] ?? 0) === 1,
        'prevent_purchase_orders' => $preventOrders,
    ];
}

function yovel_admin_buying_assert_supplier_purchase_allowed(array $company, string $supplierKey, string $documentType): array
{
    $documentType = strtoupper(trim($documentType));
    if (!in_array($documentType, ['RFQ', 'PURCHASE_ORDER'], true)) {
        throw new InvalidArgumentException('Buying supplier restriction document type is invalid.');
    }
    $restrictions = yovel_admin_buying_supplier_restrictions($company, $supplierKey);
    $preventKey = $documentType === 'RFQ' ? 'prevent_rfqs' : 'prevent_purchase_orders';
    $warnKey = $documentType === 'RFQ' ? 'warn_rfqs' : 'warn_purchase_orders';
    if ($restrictions[$preventKey]) {
        throw new RuntimeException(($documentType === 'RFQ' ? 'RFQ' : 'Purchase order') . ' creation is prevented by the supplier scorecard standing.');
    }
    return ['allowed' => true, 'warning' => $restrictions[$warnKey], 'restrictions' => $restrictions];
}
