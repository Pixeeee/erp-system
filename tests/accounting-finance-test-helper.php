<?php
declare(strict_types=1);

function finance_test_snapshot_rows(ADOConnection $db, string $table, string $where, array $params): array
{
    if (preg_match('/^project_company_[a-z0-9_]+$/', $table) !== 1) throw new InvalidArgumentException('Unsafe Finance test table name.');
    $rows = $db->GetAll("SELECT * FROM {$table} WHERE {$where}", $params);
    return is_array($rows) ? $rows : [];
}

function finance_test_restore_rows(ADOConnection $db, string $table, string $where, array $params, array $rows): void
{
    if (preg_match('/^project_company_[a-z0-9_]+$/', $table) !== 1) throw new InvalidArgumentException('Unsafe Finance test table name.');
    $db->Execute("DELETE FROM {$table} WHERE {$where}", $params);
    foreach ($rows as $row) {
        $columns = array_keys($row);
        $quoted = implode(',', array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns));
        $marks = implode(',', array_fill(0, count($columns), '?'));
        $db->Execute("INSERT INTO {$table} ({$quoted}) VALUES ({$marks})", array_values($row));
    }
}

function finance_test_preserve_rows(ADOConnection $db, string $table, string $where, array $params): void
{
    $rows = finance_test_snapshot_rows($db, $table, $where, $params);
    register_shutdown_function(static function () use ($db, $table, $where, $params, $rows): void {
        finance_test_restore_rows($db, $table, $where, $params, $rows);
    });
}
