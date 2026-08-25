<?php
declare(strict_types=1);

require_once __DIR__ . '/foundation.php';

function yovel_admin_finance_grid_schema(): void
{
    $db = bx_db();
    $statements = [
        "CREATE TABLE IF NOT EXISTS project_company_finance_grid_view (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            grid_view_key CHAR(36) NOT NULL UNIQUE,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            section_key VARCHAR(80) NOT NULL,
            view_title VARCHAR(160) NOT NULL,
            search_text VARCHAR(240) NULL,
            sort_json TEXT NULL,
            filter_json TEXT NULL,
            group_field VARCHAR(80) NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            view_status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_grid_view_title (company_key_hash, section_key, view_title),
            INDEX idx_project_company_finance_grid_view_section (company_key_hash, section_key, view_status, is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_grid_column (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            grid_column_key CHAR(36) NOT NULL UNIQUE,
            grid_view_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            column_key VARCHAR(80) NOT NULL,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            column_width INT UNSIGNED NOT NULL DEFAULT 160,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_grid_column (grid_view_key, column_key),
            INDEX idx_project_company_finance_grid_column_order (company_key_hash, grid_view_key, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_company_finance_grid_formula (
            x_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            grid_formula_key CHAR(36) NOT NULL UNIQUE,
            grid_view_key CHAR(36) NULL,
            company_key CHAR(36) NOT NULL,
            company_key_hash CHAR(64) NOT NULL,
            section_key VARCHAR(80) NOT NULL,
            formula_code VARCHAR(80) NOT NULL,
            formula_label VARCHAR(160) NOT NULL,
            formula_expression VARCHAR(500) NOT NULL,
            output_format ENUM('NUMBER','CURRENCY','PERCENT') NOT NULL DEFAULT 'NUMBER',
            decimal_precision TINYINT UNSIGNED NOT NULL DEFAULT 2,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            formula_status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
            created_by_admin_key CHAR(36) NULL,
            updated_by_admin_key CHAR(36) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_company_finance_grid_formula_code (company_key_hash, section_key, formula_code),
            INDEX idx_project_company_finance_grid_formula_section (company_key_hash, section_key, formula_status, sort_order),
            INDEX idx_project_company_finance_grid_formula_view (company_key_hash, grid_view_key, formula_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        yovel_admin_db_execute($db, $statement, [], 'Finance grid schema update');
    }
}

function yovel_admin_finance_formula_tokens(string $expression): array
{
    $expression = trim($expression);
    if ($expression === '' || strlen($expression) > 500) {
        throw new InvalidArgumentException('INVALID_EXPRESSION');
    }

    $tokens = [];
    $length = strlen($expression);
    $offset = 0;
    while ($offset < $length) {
        $character = $expression[$offset];
        if (ctype_space($character)) {
            $offset++;
            continue;
        }
        $remaining = substr($expression, $offset);
        if (preg_match('/^(?:\d+(?:\.\d*)?|\.\d+)/', $remaining, $match) === 1) {
            $tokens[] = ['type' => 'NUMBER', 'value' => $match[0]];
            $offset += strlen($match[0]);
            continue;
        }
        if ($character === '[') {
            $end = strpos($expression, ']', $offset + 1);
            if ($end === false) {
                throw new InvalidArgumentException('INVALID_FIELD_REFERENCE');
            }
            $field = substr($expression, $offset + 1, $end - $offset - 1);
            if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,79}$/', $field) !== 1) {
                throw new InvalidArgumentException('INVALID_FIELD_REFERENCE');
            }
            $tokens[] = ['type' => 'FIELD', 'value' => $field];
            $offset = $end + 1;
            continue;
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*/', $remaining, $match) === 1) {
            $tokens[] = ['type' => 'IDENT', 'value' => strtoupper($match[0])];
            $offset += strlen($match[0]);
            continue;
        }
        $pair = substr($expression, $offset, 2);
        if (in_array($pair, ['>=', '<=', '!='], true)) {
            $tokens[] = ['type' => 'OP', 'value' => $pair];
            $offset += 2;
            continue;
        }
        if (in_array($character, ['+', '-', '*', '/', '>', '<', '='], true)) {
            $tokens[] = ['type' => 'OP', 'value' => $character];
            $offset++;
            continue;
        }
        if ($character === '(') {
            $tokens[] = ['type' => 'LPAREN', 'value' => '('];
            $offset++;
            continue;
        }
        if ($character === ')') {
            $tokens[] = ['type' => 'RPAREN', 'value' => ')'];
            $offset++;
            continue;
        }
        if ($character === ',') {
            $tokens[] = ['type' => 'COMMA', 'value' => ','];
            $offset++;
            continue;
        }

        throw new InvalidArgumentException('INVALID_TOKEN');
    }
    $tokens[] = ['type' => 'EOF', 'value' => ''];

    return $tokens;
}

final class YovelFinanceFormulaParser
{
    private array $tokens;
    private int $position = 0;
    private array $allowedFields;

    public function __construct(array $tokens, array $allowedFields)
    {
        $this->tokens = $tokens;
        $this->allowedFields = array_fill_keys(array_map('strval', $allowedFields), true);
    }

    public function parse(): array
    {
        $node = $this->parseComparison();
        if ($this->current()['type'] !== 'EOF') {
            throw new InvalidArgumentException('INVALID_EXPRESSION');
        }

        return $node;
    }

    private function current(): array
    {
        return $this->tokens[$this->position] ?? ['type' => 'EOF', 'value' => ''];
    }

    private function consume(string $type, ?string $value = null): array
    {
        $token = $this->current();
        if ($token['type'] !== $type || ($value !== null && $token['value'] !== $value)) {
            throw new InvalidArgumentException('INVALID_EXPRESSION');
        }
        $this->position++;

        return $token;
    }

    private function parseComparison(): array
    {
        $left = $this->parseAdditive();
        $token = $this->current();
        if ($token['type'] === 'OP' && in_array($token['value'], ['=', '!=', '>', '>=', '<', '<='], true)) {
            $this->position++;
            return ['type' => 'comparison', 'operator' => $token['value'], 'left' => $left, 'right' => $this->parseAdditive()];
        }

        return $left;
    }

    private function parseAdditive(): array
    {
        $node = $this->parseMultiplicative();
        while ($this->current()['type'] === 'OP' && in_array($this->current()['value'], ['+', '-'], true)) {
            $operator = $this->current()['value'];
            $this->position++;
            $node = ['type' => 'binary', 'operator' => $operator, 'left' => $node, 'right' => $this->parseMultiplicative()];
        }

        return $node;
    }

    private function parseMultiplicative(): array
    {
        $node = $this->parseUnary();
        while ($this->current()['type'] === 'OP' && in_array($this->current()['value'], ['*', '/'], true)) {
            $operator = $this->current()['value'];
            $this->position++;
            $node = ['type' => 'binary', 'operator' => $operator, 'left' => $node, 'right' => $this->parseUnary()];
        }

        return $node;
    }

    private function parseUnary(): array
    {
        if ($this->current()['type'] === 'OP' && in_array($this->current()['value'], ['+', '-'], true)) {
            $operator = $this->current()['value'];
            $this->position++;
            return ['type' => 'unary', 'operator' => $operator, 'value' => $this->parseUnary()];
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): array
    {
        $token = $this->current();
        if ($token['type'] === 'NUMBER') {
            $this->position++;
            return ['type' => 'number', 'value' => (float) $token['value']];
        }
        if ($token['type'] === 'FIELD') {
            $this->position++;
            if (!isset($this->allowedFields[$token['value']])) {
                throw new InvalidArgumentException('UNKNOWN_FIELD');
            }
            return ['type' => 'field', 'name' => $token['value']];
        }
        if ($token['type'] === 'LPAREN') {
            $this->position++;
            $node = $this->parseComparison();
            $this->consume('RPAREN');
            return $node;
        }
        if ($token['type'] === 'IDENT') {
            $name = $token['value'];
            if (!in_array($name, ['SUM', 'AVERAGE', 'MIN', 'MAX', 'COUNT', 'IF'], true)) {
                throw new InvalidArgumentException('UNKNOWN_FUNCTION');
            }
            $this->position++;
            $this->consume('LPAREN');
            $arguments = [];
            if ($this->current()['type'] !== 'RPAREN') {
                $arguments[] = $this->parseComparison();
                while ($this->current()['type'] === 'COMMA') {
                    $this->position++;
                    $arguments[] = $this->parseComparison();
                }
            }
            $this->consume('RPAREN');
            $requiredCount = $name === 'IF' ? 3 : 1;
            if (count($arguments) !== $requiredCount) {
                throw new InvalidArgumentException('INVALID_ARGUMENT_COUNT');
            }
            return ['type' => 'call', 'name' => $name, 'arguments' => $arguments];
        }

        throw new InvalidArgumentException('INVALID_EXPRESSION');
    }
}

function yovel_admin_finance_formula_parse(string $expression, array $allowedFields): array
{
    $parser = new YovelFinanceFormulaParser(yovel_admin_finance_formula_tokens($expression), $allowedFields);
    return $parser->parse();
}

function yovel_admin_finance_formula_numeric(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    if (!is_numeric($value)) {
        throw new RuntimeException('NON_NUMERIC_VALUE');
    }

    return (float) $value;
}

function yovel_admin_finance_formula_node_value(array $node, array $row, array $rows): float|bool
{
    $type = (string) ($node['type'] ?? '');
    if ($type === 'number') {
        return (float) $node['value'];
    }
    if ($type === 'field') {
        return yovel_admin_finance_formula_numeric($row[(string) $node['name']] ?? null);
    }
    if ($type === 'unary') {
        $value = yovel_admin_finance_formula_numeric(yovel_admin_finance_formula_node_value($node['value'], $row, $rows));
        return $node['operator'] === '-' ? -$value : $value;
    }
    if ($type === 'binary') {
        $left = yovel_admin_finance_formula_numeric(yovel_admin_finance_formula_node_value($node['left'], $row, $rows));
        $right = yovel_admin_finance_formula_numeric(yovel_admin_finance_formula_node_value($node['right'], $row, $rows));
        return match ($node['operator']) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => abs($right) < 0.000000000001 ? throw new RuntimeException('DIVIDE_BY_ZERO') : $left / $right,
            default => throw new RuntimeException('INVALID_OPERATOR'),
        };
    }
    if ($type === 'comparison') {
        $left = yovel_admin_finance_formula_node_value($node['left'], $row, $rows);
        $right = yovel_admin_finance_formula_node_value($node['right'], $row, $rows);
        return match ($node['operator']) {
            '=' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            default => throw new RuntimeException('INVALID_COMPARISON'),
        };
    }
    if ($type === 'call') {
        $name = (string) $node['name'];
        if ($name === 'IF') {
            $condition = (bool) yovel_admin_finance_formula_node_value($node['arguments'][0], $row, $rows);
            return yovel_admin_finance_formula_node_value($node['arguments'][$condition ? 1 : 2], $row, $rows);
        }
        $sourceRows = $rows ?: [$row];
        $values = array_map(
            static fn (array $sourceRow): float => yovel_admin_finance_formula_numeric(yovel_admin_finance_formula_node_value($node['arguments'][0], $sourceRow, $sourceRows)),
            $sourceRows
        );
        return match ($name) {
            'SUM' => array_sum($values),
            'AVERAGE' => $values ? array_sum($values) / count($values) : 0.0,
            'MIN' => $values ? min($values) : 0.0,
            'MAX' => $values ? max($values) : 0.0,
            'COUNT' => (float) count($values),
            default => throw new RuntimeException('UNKNOWN_FUNCTION'),
        };
    }

    throw new RuntimeException('INVALID_AST');
}

function yovel_admin_finance_formula_evaluate(array $ast, array $row, array $rows = []): array
{
    try {
        return ['ok' => true, 'value' => yovel_admin_finance_formula_node_value($ast, $row, $rows), 'error' => null];
    } catch (Throwable $error) {
        return ['ok' => false, 'value' => null, 'error' => $error->getMessage() !== '' ? $error->getMessage() : 'FORMULA_ERROR'];
    }
}

function yovel_admin_finance_grid_allowed_columns(array $input): array
{
    $columns = $input['allowed_columns'] ?? [];
    if (!is_array($columns)) {
        return [];
    }
    return array_values(array_unique(array_filter(array_map('strval', $columns), static fn (string $column): bool => preg_match('/^[a-z][a-z0-9_]{1,79}$/', $column) === 1)));
}

function yovel_admin_finance_grid_normalize_columns(array $columns, array $allowedColumns): array
{
    $allowed = array_fill_keys($allowedColumns, true);
    $normalized = [];
    foreach (array_values($columns) as $index => $column) {
        if (!is_array($column)) {
            continue;
        }
        $key = (string) ($column['key'] ?? '');
        if (!isset($allowed[$key]) || isset($normalized[$key])) {
            continue;
        }
        $normalized[$key] = [
            'key' => $key,
            'visible' => !empty($column['visible']),
            'width' => max(80, min(640, (int) ($column['width'] ?? 160))),
            'order' => ($index + 1) * 10,
        ];
    }
    foreach ($allowedColumns as $key) {
        if (!isset($normalized[$key])) {
            $normalized[$key] = ['key' => $key, 'visible' => true, 'width' => 160, 'order' => (count($normalized) + 1) * 10];
        }
    }

    return array_values($normalized);
}

function yovel_admin_finance_grid_normalize_sort(mixed $sort, array $allowedColumns): array
{
    if (!is_array($sort)) {
        return [];
    }
    $allowed = array_fill_keys($allowedColumns, true);
    $normalized = [];
    foreach (array_slice(array_values($sort), 0, 3) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $column = (string) ($item['column'] ?? '');
        $direction = strtoupper((string) ($item['direction'] ?? 'ASC'));
        if (isset($allowed[$column]) && in_array($direction, ['ASC', 'DESC'], true)) {
            $normalized[] = ['column' => $column, 'direction' => $direction];
        }
    }
    return $normalized;
}

function yovel_admin_finance_grid_normalize_filters(mixed $filters, array $allowedColumns): array
{
    if (!is_array($filters)) {
        return [];
    }
    $allowed = array_fill_keys($allowedColumns, true);
    $normalized = [];
    foreach ($filters as $column => $value) {
        $column = (string) $column;
        $value = trim((string) $value);
        if (isset($allowed[$column]) && $value !== '') {
            $normalized[$column] = substr($value, 0, 240);
        }
        if (count($normalized) >= 20) {
            break;
        }
    }
    return $normalized;
}

function yovel_admin_finance_grid_view_with_columns(ADOConnection $db, string $companyKeyHash, string $viewKey): ?array
{
    $view = $db->GetRow(
        "SELECT * FROM project_company_finance_grid_view WHERE company_key_hash = ? AND grid_view_key = ? AND view_status = 'ACTIVE' LIMIT 1",
        [$companyKeyHash, $viewKey]
    );
    if (!is_array($view) || $view === []) {
        return null;
    }
    $columns = $db->GetAll(
        'SELECT column_key, is_visible, column_width, sort_order FROM project_company_finance_grid_column WHERE company_key_hash = ? AND grid_view_key = ? ORDER BY sort_order, x_id',
        [$companyKeyHash, $viewKey]
    );
    $view['columns'] = array_map(static fn (array $column): array => [
        'key' => (string) $column['column_key'],
        'visible' => (int) $column['is_visible'] === 1,
        'width' => (int) $column['column_width'],
        'order' => (int) $column['sort_order'],
    ], is_array($columns) ? $columns : []);
    $view['sort'] = json_decode((string) ($view['sort_json'] ?? '[]'), true) ?: [];
    $view['filters'] = json_decode((string) ($view['filter_json'] ?? '{}'), true) ?: [];

    return $view;
}

function yovel_admin_finance_grid_views(array $company, string $section): array
{
    yovel_admin_finance_grid_schema();
    $section = yovel_admin_finance_builder_target_section($section);
    $rows = bx_db()->GetAll(
        "SELECT grid_view_key FROM project_company_finance_grid_view WHERE company_key_hash = ? AND section_key = ? AND view_status = 'ACTIVE' ORDER BY is_default DESC, view_title",
        [(string) $company['company_key_hash'], $section]
    );
    $views = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $view = yovel_admin_finance_grid_view_with_columns(bx_db(), (string) $company['company_key_hash'], (string) $row['grid_view_key']);
        if ($view) {
            $views[] = $view;
        }
    }
    return $views;
}

function yovel_admin_persist_finance_grid_view(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_finance_grid_schema();
    $companyKey = (string) ($company['company_key'] ?? '');
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $adminKey = (string) ($admin['admin_key'] ?? '');
    if ($companyKey === '' || $companyKeyHash === '' || $adminKey === '') {
        throw new InvalidArgumentException('Finance grid administrator scope is invalid.');
    }
    $viewKey = trim((string) ($input['grid_view_key'] ?? ''));
    if ($viewKey !== '' && !yovel_admin_is_uuid($viewKey)) {
        throw new InvalidArgumentException('Finance grid view key is invalid.');
    }
    if ($viewKey === '') {
        $viewKey = bx_uuid();
    }
    $section = yovel_admin_slug((string) ($input['section_key'] ?? ''));
    if (!array_key_exists($section, yovel_admin_accounting_finance_sections())) {
        throw new InvalidArgumentException('Finance grid section is invalid.');
    }
    $title = trim((string) ($input['view_title'] ?? ''));
    if ($title === '' || strlen($title) > 160) {
        throw new InvalidArgumentException('Finance grid view title is required and must be 160 characters or fewer.');
    }
    $search = substr(trim((string) ($input['search_text'] ?? '')), 0, 240);
    $allowedColumns = yovel_admin_finance_grid_allowed_columns($input);
    if (!$allowedColumns) {
        throw new InvalidArgumentException('Finance grid view has no allowed columns.');
    }
    $columns = yovel_admin_finance_grid_normalize_columns(is_array($input['columns'] ?? null) ? $input['columns'] : [], $allowedColumns);
    $sort = yovel_admin_finance_grid_normalize_sort($input['sort'] ?? [], $allowedColumns);
    $filters = yovel_admin_finance_grid_normalize_filters($input['filters'] ?? [], $allowedColumns);
    $groupField = (string) ($input['group_field'] ?? '');
    if ($groupField !== '' && !in_array($groupField, $allowedColumns, true)) {
        throw new InvalidArgumentException('Finance grid group field is invalid.');
    }
    $isDefault = !empty($input['is_default']) ? 1 : 0;
    $sortJson = json_encode($sort, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $filterJson = json_encode($filters, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance grid view transaction could not start.');
    }
    try {
        $existing = $db->GetRow(
            'SELECT * FROM project_company_finance_grid_view WHERE company_key_hash = ? AND grid_view_key = ? FOR UPDATE',
            [$companyKeyHash, $viewKey]
        );
        if ($isDefault === 1) {
            yovel_admin_db_execute(
                $db,
                "UPDATE project_company_finance_grid_view SET is_default = 0, updated_by_admin_key = ? WHERE company_key_hash = ? AND section_key = ? AND view_status = 'ACTIVE'",
                [$adminKey, $companyKeyHash, $section],
                'Finance grid default view clear'
            );
        }
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_finance_grid_view (
                grid_view_key, company_key, company_key_hash, section_key, view_title,
                search_text, sort_json, filter_json, group_field, is_default,
                created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                section_key = VALUES(section_key), view_title = VALUES(view_title),
                search_text = VALUES(search_text), sort_json = VALUES(sort_json),
                filter_json = VALUES(filter_json), group_field = VALUES(group_field),
                is_default = VALUES(is_default), view_status = 'ACTIVE',
                updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$viewKey, $companyKey, $companyKeyHash, $section, $title, $search, $sortJson, $filterJson, $groupField !== '' ? $groupField : null, $isDefault, $adminKey, $adminKey],
            'Finance grid view save'
        );
        yovel_admin_db_execute($db, 'DELETE FROM project_company_finance_grid_column WHERE company_key_hash = ? AND grid_view_key = ?', [$companyKeyHash, $viewKey], 'Finance grid column replace');
        foreach ($columns as $column) {
            yovel_admin_db_execute(
                $db,
                'INSERT INTO project_company_finance_grid_column (grid_column_key, grid_view_key, company_key_hash, column_key, is_visible, column_width, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [bx_uuid(), $viewKey, $companyKeyHash, $column['key'], $column['visible'] ? 1 : 0, $column['width'], $column['order']],
                'Finance grid column save'
            );
        }

        $saved = yovel_admin_finance_grid_view_with_columns($db, $companyKeyHash, $viewKey);
        if (!$saved || (string) $saved['view_title'] !== $title || (string) ($saved['search_text'] ?? '') !== $search || count($saved['columns']) !== count($columns)) {
            throw new RuntimeException('Finance grid view read-back verification failed.');
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_finance_grid_view', $viewKey, [
            'company_key' => $companyKey,
            'section_key' => $section,
            'view_title' => $title,
            'column_count' => count($columns),
            'admin_key' => $adminKey,
        ], $existing ? 'Company admin updated a Finance grid view.' : 'Company admin created a Finance grid view.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance grid view transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_finance_grid_formulas(array $company, string $section): array
{
    yovel_admin_finance_grid_schema();
    $rows = bx_db()->GetAll(
        "SELECT * FROM project_company_finance_grid_formula WHERE company_key_hash = ? AND section_key = ? AND formula_status = 'ACTIVE' ORDER BY sort_order, formula_label",
        [(string) $company['company_key_hash'], yovel_admin_finance_builder_target_section($section)]
    );
    return is_array($rows) ? $rows : [];
}

function yovel_admin_persist_finance_grid_formula(ADOConnection $db, array $company, array $admin, array $input): array
{
    yovel_admin_finance_grid_schema();
    $companyKey = (string) ($company['company_key'] ?? '');
    $companyKeyHash = (string) ($company['company_key_hash'] ?? '');
    $adminKey = (string) ($admin['admin_key'] ?? '');
    $formulaKey = trim((string) ($input['grid_formula_key'] ?? ''));
    if ($formulaKey !== '' && !yovel_admin_is_uuid($formulaKey)) {
        throw new InvalidArgumentException('Finance formula key is invalid.');
    }
    if ($formulaKey === '') {
        $formulaKey = bx_uuid();
    }
    $section = yovel_admin_slug((string) ($input['section_key'] ?? ''));
    if (!array_key_exists($section, yovel_admin_accounting_finance_sections())) {
        throw new InvalidArgumentException('Finance formula section is invalid.');
    }
    $viewKey = trim((string) ($input['grid_view_key'] ?? ''));
    if ($viewKey !== '' && !yovel_admin_is_uuid($viewKey)) {
        throw new InvalidArgumentException('Finance formula view key is invalid.');
    }
    $code = strtolower(trim((string) preg_replace('/[^A-Za-z0-9_]+/', '_', (string) ($input['formula_code'] ?? '')), '_'));
    if (preg_match('/^[a-z][a-z0-9_]{1,79}$/', $code) !== 1) {
        throw new InvalidArgumentException('Finance formula code is invalid.');
    }
    $label = trim((string) ($input['formula_label'] ?? ''));
    if ($label === '' || strlen($label) > 160) {
        throw new InvalidArgumentException('Finance formula label is required and must be 160 characters or fewer.');
    }
    $expression = trim((string) ($input['formula_expression'] ?? ''));
    $allowedFields = is_array($input['allowed_fields'] ?? null) ? array_values(array_map('strval', $input['allowed_fields'])) : [];
    yovel_admin_finance_formula_parse($expression, $allowedFields);
    $format = strtoupper((string) ($input['output_format'] ?? 'NUMBER'));
    if (!in_array($format, ['NUMBER', 'CURRENCY', 'PERCENT'], true)) {
        throw new InvalidArgumentException('Finance formula output format is invalid.');
    }
    $precision = max(0, min(6, (int) ($input['decimal_precision'] ?? 2)));
    $sortOrder = max(0, (int) ($input['sort_order'] ?? 0));

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance formula transaction could not start.');
    }
    try {
        $existing = $db->GetRow(
            'SELECT * FROM project_company_finance_grid_formula WHERE company_key_hash = ? AND grid_formula_key = ? FOR UPDATE',
            [$companyKeyHash, $formulaKey]
        );
        yovel_admin_db_execute(
            $db,
            "INSERT INTO project_company_finance_grid_formula (
                grid_formula_key, grid_view_key, company_key, company_key_hash, section_key,
                formula_code, formula_label, formula_expression, output_format, decimal_precision,
                sort_order, created_by_admin_key, updated_by_admin_key
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                grid_view_key = VALUES(grid_view_key), formula_label = VALUES(formula_label),
                formula_expression = VALUES(formula_expression), output_format = VALUES(output_format),
                decimal_precision = VALUES(decimal_precision), sort_order = VALUES(sort_order),
                formula_status = 'ACTIVE', updated_by_admin_key = VALUES(updated_by_admin_key)",
            [$formulaKey, $viewKey !== '' ? $viewKey : null, $companyKey, $companyKeyHash, $section, $code, $label, $expression, $format, $precision, $sortOrder, $adminKey, $adminKey],
            'Finance grid formula save'
        );
        $saved = $db->GetRow(
            "SELECT * FROM project_company_finance_grid_formula WHERE company_key_hash = ? AND grid_formula_key = ? AND formula_status = 'ACTIVE' LIMIT 1",
            [$companyKeyHash, $formulaKey]
        );
        if (!is_array($saved) || (string) $saved['formula_expression'] !== $expression || (string) $saved['formula_label'] !== $label) {
            throw new RuntimeException('Finance formula read-back verification failed.');
        }
        bx_audit($existing ? 'UPDATE' : 'CREATE', 'project_company_finance_grid_formula', $formulaKey, [
            'company_key' => $companyKey,
            'section_key' => $section,
            'formula_code' => $code,
            'formula_expression' => $expression,
            'admin_key' => $adminKey,
        ], $existing ? 'Company admin updated a Finance calculated column.' : 'Company admin created a Finance calculated column.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance formula transaction could not commit.');
        }
        return $saved;
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }
}

function yovel_admin_finance_grid_column_registry(array $schema): array
{
    $registry = [];
    foreach (($schema['fields'] ?? []) as $field) {
        if (!is_array($field) || !filter_var($field['visible'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            continue;
        }
        $key = (string) ($field['key'] ?? '');
        if ($key === '' || preg_match('/^[a-z][a-z0-9_]{1,79}$/', $key) !== 1) {
            continue;
        }
        $registry[$key] = [
            'key' => $key,
            'label' => (string) ($field['label'] ?? $key),
            'type' => (string) ($field['type'] ?? 'text'),
        ];
    }
    return $registry;
}

function yovel_admin_finance_grid_numeric_fields(array $schema): array
{
    return array_keys(array_filter(
        yovel_admin_finance_grid_column_registry($schema),
        static fn (array $column): bool => in_array($column['type'], ['number', 'checkbox'], true)
    ));
}

function yovel_admin_finance_grid_post_json(string $name, array $fallback): array
{
    $value = (string) ($_POST[$name] ?? '');
    if ($value === '') {
        return $fallback;
    }
    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Finance grid configuration must be valid JSON.');
    }
    return $decoded;
}

function yovel_admin_save_finance_grid_view_operation(array $company, array $admin): string
{
    $section = yovel_admin_slug((string) ($_POST['section'] ?? ''));
    $sections = yovel_admin_accounting_finance_sections();
    if (!isset($sections[$section])) {
        throw new InvalidArgumentException('Finance grid section is invalid.');
    }
    $recordType = (string) ($sections[$section]['record_type'] ?? 'account');
    $schema = yovel_admin_accounting_finance_active_schema($company, $recordType, $admin);
    $registry = yovel_admin_finance_grid_column_registry($schema);
    $formulas = yovel_admin_finance_grid_formulas($company, $section);
    foreach ($formulas as $formula) {
        $key = 'formula_' . (string) $formula['formula_code'];
        $registry[$key] = ['key' => $key, 'label' => (string) $formula['formula_label'], 'type' => 'number'];
    }
    $columns = [];
    foreach (yovel_admin_finance_grid_post_json('columns_json', []) as $column) {
        if (is_array($column)) {
            $columns[] = $column;
        }
    }
    $saved = yovel_admin_persist_finance_grid_view(bx_db(), $company, $admin, [
        'grid_view_key' => (string) ($_POST['grid_view_key'] ?? ''),
        'section_key' => $section,
        'view_title' => (string) ($_POST['view_title'] ?? ''),
        'search_text' => (string) ($_POST['search_text'] ?? ''),
        'sort' => yovel_admin_finance_grid_post_json('sort_json', []),
        'filters' => yovel_admin_finance_grid_post_json('filter_json', []),
        'group_field' => (string) ($_POST['group_field'] ?? ''),
        'is_default' => isset($_POST['is_default']),
        'columns' => $columns,
        'allowed_columns' => array_keys($registry),
    ]);
    $GLOBALS['yovel_admin_saved_finance_grid_view_key'] = (string) $saved['grid_view_key'];

    return 'Finance grid view saved.';
}

function yovel_admin_save_finance_grid_view(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'save_finance_grid_view', static fn (): string => yovel_admin_save_finance_grid_view_operation($company, $admin));
}

function yovel_admin_save_finance_grid_formula_operation(array $company, array $admin): string
{
    $section = yovel_admin_slug((string) ($_POST['section'] ?? ''));
    $sections = yovel_admin_accounting_finance_sections();
    if (!isset($sections[$section])) {
        throw new InvalidArgumentException('Finance formula section is invalid.');
    }
    $recordType = (string) ($sections[$section]['record_type'] ?? 'account');
    $schema = yovel_admin_accounting_finance_active_schema($company, $recordType, $admin);
    $saved = yovel_admin_persist_finance_grid_formula(bx_db(), $company, $admin, [
        'grid_formula_key' => (string) ($_POST['grid_formula_key'] ?? ''),
        'grid_view_key' => (string) ($_POST['grid_view_key'] ?? ''),
        'section_key' => $section,
        'formula_code' => (string) ($_POST['formula_code'] ?? ''),
        'formula_label' => (string) ($_POST['formula_label'] ?? ''),
        'formula_expression' => (string) ($_POST['formula_expression'] ?? ''),
        'output_format' => (string) ($_POST['output_format'] ?? 'NUMBER'),
        'decimal_precision' => (int) ($_POST['decimal_precision'] ?? 2),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'allowed_fields' => yovel_admin_finance_grid_numeric_fields($schema),
    ]);
    $GLOBALS['yovel_admin_saved_finance_grid_formula_key'] = (string) $saved['grid_formula_key'];

    return 'Finance calculated column saved.';
}

function yovel_admin_save_finance_grid_formula(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'save_finance_grid_formula', static fn (): string => yovel_admin_save_finance_grid_formula_operation($company, $admin));
}

function yovel_admin_finance_formula_format(array $formula, array $result): string
{
    if (!($result['ok'] ?? false)) {
        return '#' . (string) ($result['error'] ?? 'ERROR');
    }
    $precision = max(0, min(6, (int) ($formula['decimal_precision'] ?? 2)));
    $value = (float) ($result['value'] ?? 0);
    return match ((string) ($formula['output_format'] ?? 'NUMBER')) {
        'CURRENCY' => number_format($value, $precision, '.', ','),
        'PERCENT' => number_format($value, $precision, '.', ',') . '%',
        default => number_format($value, $precision, '.', ','),
    };
}

function yovel_admin_finance_import_boolean(mixed $value): int
{
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'on'], true) ? 1 : 0;
}

function yovel_admin_import_finance_accounts_operation(array $company, array $admin): string
{
    yovel_admin_finance_foundation_schema();
    $decoded = json_decode((string) ($_POST['rows_json'] ?? ''), true);
    if (!is_array($decoded) || $decoded === [] || count($decoded) > 500) {
        throw new InvalidArgumentException('Finance import must contain between 1 and 500 rows.');
    }

    $db = bx_db();
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyKeyHash = trim((string) ($company['company_key_hash'] ?? ''));
    $adminKey = trim((string) ($admin['admin_key'] ?? ''));
    if ($companyKey === '' || strlen($companyKey) > 64 || !yovel_admin_is_uuid($adminKey) || preg_match('/^[a-f0-9]{64}$/', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('Finance import administrator scope is invalid.');
    }
    $authorized = (int) $db->GetOne(
        "SELECT COUNT(*) FROM project_company c JOIN project_company_admin a ON a.company_key_hash=c.company_key_hash WHERE c.company_key=? AND c.company_key_hash=? AND c.company_status<>'DELETED' AND a.admin_key=? AND a.admin_status='ACTIVE'",
        [$companyKey,$companyKeyHash,$adminKey]
    );
    if ($authorized !== 1) throw new InvalidArgumentException('Finance import administrator is not authorized for this company.');

    $existingRows = $db->GetAll(
        "SELECT * FROM project_company_accounting_account WHERE company_key_hash = ? AND account_status <> 'DELETED'",
        [$companyKeyHash]
    );
    $existingByCode = [];
    $allByKey = [];
    foreach (is_array($existingRows) ? $existingRows : [] as $existing) {
        $existingByCode[(string) $existing['account_code']] = $existing;
        $allByKey[(string) $existing['account_key']] = $existing;
    }

    $rows = [];
    $seenCodes = [];
    foreach (array_values($decoded) as $index => $input) {
        if (!is_array($input)) {
            throw new InvalidArgumentException('Finance import row ' . ($index + 2) . ' is invalid.');
        }
        $code = yovel_admin_code((string) ($input['account_code'] ?? ''));
        $name = trim((string) ($input['account_name'] ?? ''));
        $root = strtoupper(trim((string) ($input['root_type'] ?? '')));
        if ($code === '' || $name === '' || !in_array($root, ['ASSET', 'LIABILITY', 'INCOME', 'EXPENSE', 'EQUITY'], true)) {
            throw new InvalidArgumentException('Finance import row ' . ($index + 2) . ' requires account_code, account_name, and a valid root_type.');
        }
        if (isset($seenCodes[$code])) {
            throw new InvalidArgumentException('Finance import contains duplicate account code ' . $code . '.');
        }
        $seenCodes[$code] = true;
        $number = trim((string) ($input['account_number'] ?? ''));
        $type = trim((string) ($input['account_type'] ?? ''));
        $currency = strtoupper(trim((string) ($input['account_currency'] ?? '')));
        $notes = trim((string) ($input['account_notes'] ?? ''));
        if (strlen($name) > 180 || strlen($number) > 80 || strlen($type) > 80 || strlen($notes) > 5000) {
            throw new InvalidArgumentException('Finance import row ' . ($index + 2) . ' exceeds an allowed field length.');
        }
        if ($currency !== '' && preg_match('/^[A-Z]{3,20}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Finance import row ' . ($index + 2) . ' has an invalid currency.');
        }
        $taxRate = yovel_admin_optional_decimal((string) ($input['tax_rate'] ?? ''), 'Tax rate on import row ' . ($index + 2));
        $status = strtoupper(trim((string) ($input['account_status'] ?? 'ACTIVE')));
        if (!in_array($status, ['DRAFT', 'ACTIVE', 'FROZEN', 'INACTIVE'], true)) {
            throw new InvalidArgumentException('Finance import row ' . ($index + 2) . ' has an invalid account status.');
        }
        $balance = strtoupper(trim((string) ($input['balance_must_be'] ?? 'EITHER')));
        if (!in_array($balance, ['DEBIT', 'CREDIT', 'EITHER'], true)) {
            throw new InvalidArgumentException('Finance import row ' . ($index + 2) . ' has an invalid balance rule.');
        }
        $rows[$code] = [
            'account_key' => (string) ($existingByCode[$code]['account_key'] ?? bx_uuid()),
            'account_code' => $code,
            'account_number' => $number !== '' ? $number : null,
            'account_name' => $name,
            'parent_account_code' => yovel_admin_code((string) ($input['parent_account_code'] ?? '')),
            'root_type' => $root,
            'report_type' => yovel_admin_accounting_report_type_for_root($root),
            'account_type' => $type !== '' ? $type : null,
            'account_currency' => $currency !== '' ? $currency : null,
            'is_group' => yovel_admin_finance_import_boolean($input['is_group'] ?? 0),
            'tax_rate' => $taxRate !== '' ? $taxRate : null,
            'balance_must_be' => $balance,
            'freeze_account' => yovel_admin_finance_import_boolean($input['freeze_account'] ?? 0),
            'include_in_gross' => yovel_admin_finance_import_boolean($input['include_in_gross'] ?? 0),
            'account_status' => $status,
            'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
            'account_notes' => $notes,
        ];
    }

    foreach ($rows as $code => &$row) {
        $parentCode = (string) $row['parent_account_code'];
        if ($parentCode === '') {
            $row['parent_account_key'] = null;
            continue;
        }
        $parent = $rows[$parentCode] ?? $existingByCode[$parentCode] ?? null;
        if (!$parent || (int) ($parent['is_group'] ?? 0) !== 1 || (string) ($parent['root_type'] ?? '') !== (string) $row['root_type']) {
            throw new InvalidArgumentException('Parent account ' . $parentCode . ' for ' . $code . ' must exist, be a group, and use the same root type.');
        }
        $row['parent_account_key'] = (string) $parent['account_key'];
    }
    unset($row);

    $parentByKey = [];
    foreach ($allByKey as $key => $existing) {
        $parentByKey[$key] = (string) ($existing['parent_account_key'] ?? '');
    }
    foreach ($rows as $row) {
        $parentByKey[(string) $row['account_key']] = (string) ($row['parent_account_key'] ?? '');
    }
    foreach (array_keys($parentByKey) as $startKey) {
        $visited = [];
        $key = $startKey;
        while ($key !== '') {
            if (isset($visited[$key])) {
                throw new InvalidArgumentException('Finance import would create an account hierarchy cycle.');
            }
            $visited[$key] = true;
            $key = (string) ($parentByKey[$key] ?? '');
        }
    }

    if ($db->BeginTrans() === false) {
        throw new RuntimeException('Finance import transaction could not start.');
    }
    try {
        $db->GetAll('SELECT account_key FROM project_company_accounting_account WHERE company_key_hash = ? FOR UPDATE', [$companyKeyHash]);
        foreach ($rows as $row) {
            $lockedExisting = $db->GetRow('SELECT * FROM project_company_accounting_account WHERE company_key_hash = ? AND account_key = ? LIMIT 1', [$companyKeyHash, $row['account_key']]);
            if (is_array($lockedExisting) && $lockedExisting !== []) {
                yovel_admin_finance_assert_account_update_allowed($db, $companyKeyHash, $lockedExisting, $row);
            }
            yovel_admin_db_execute(
                $db,
                "INSERT INTO project_company_accounting_account (
                    account_key, company_key, company_key_hash, account_code, account_number, account_name,
                    parent_account_key, root_type, report_type, account_type, account_currency, is_group,
                    tax_rate, balance_must_be, freeze_account, include_in_gross, account_status,
                    sort_order, account_notes, created_by_admin_key, updated_by_admin_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE account_number = VALUES(account_number), account_name = VALUES(account_name),
                    parent_account_key = VALUES(parent_account_key), root_type = VALUES(root_type), report_type = VALUES(report_type),
                    account_type = VALUES(account_type), account_currency = VALUES(account_currency), is_group = VALUES(is_group),
                    tax_rate = VALUES(tax_rate), balance_must_be = VALUES(balance_must_be), freeze_account = VALUES(freeze_account),
                    include_in_gross = VALUES(include_in_gross), account_status = VALUES(account_status), sort_order = VALUES(sort_order),
                    account_notes = VALUES(account_notes), updated_by_admin_key = VALUES(updated_by_admin_key)",
                [
                    $row['account_key'], $companyKey, $companyKeyHash, $row['account_code'], $row['account_number'], $row['account_name'],
                    $row['parent_account_key'], $row['root_type'], $row['report_type'], $row['account_type'], $row['account_currency'], $row['is_group'],
                    $row['tax_rate'], $row['balance_must_be'], $row['freeze_account'], $row['include_in_gross'], $row['account_status'],
                    $row['sort_order'], $row['account_notes'], $adminKey, $adminKey,
                ],
                'Finance account CSV import'
            );
        }
        $placeholders = implode(',', array_fill(0, count($rows), '?'));
        $readBackCount = (int) $db->GetOne(
            'SELECT COUNT(*) FROM project_company_accounting_account WHERE company_key_hash = ? AND account_code IN (' . $placeholders . ')',
            array_merge([$companyKeyHash], array_keys($rows))
        );
        if ($readBackCount !== count($rows)) {
            throw new RuntimeException('Finance import read-back verification failed.');
        }
        bx_audit('IMPORT', 'project_company_accounting_account', $companyKey, [
            'company_key' => $companyKey,
            'row_count' => count($rows),
            'account_codes' => array_keys($rows),
            'admin_key' => $adminKey,
        ], 'Company admin imported Chart of Accounts records.');
        if ($db->CommitTrans() === false) {
            throw new RuntimeException('Finance import transaction could not commit.');
        }
    } catch (Throwable $error) {
        $db->RollbackTrans();
        throw $error;
    }

    return count($rows) . ' Finance accounts imported.';
}

function yovel_admin_import_finance_accounts(array $company, array $admin): string
{
    return yovel_admin_finance_run_form_action($company, 'import_finance_accounts', static fn (): string => yovel_admin_import_finance_accounts_operation($company, $admin));
}
