<?php
declare(strict_types=1);

/**
 * MySQL 建表脚本执行器：解析根目录 schema.sql 并逐条执行（幂等）。
 * 供 bootstrap.php（自动建表）与 install.php（安装向导）共用。
 */
function schema_sql_path(): string
{
    return __DIR__ . '/../schema.sql';
}

function schema_statements(): array
{
    $raw = (string) file_get_contents(schema_sql_path());
    $lines = preg_split('/\r?\n/', $raw) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $clean[] = $line;
    }
    $sql = implode("\n", $clean);
    $parts = preg_split('/;\s*\n/', $sql) ?: [];
    $stmts = [];
    foreach ($parts as $part) {
        $stmt = trim((string) $part);
        if ($stmt !== '') {
            $stmts[] = $stmt;
        }
    }
    return $stmts;
}

function run_schema(PDO $pdo): int
{
    $count = 0;
    foreach (schema_statements() as $stmt) {
        $pdo->exec($stmt);
        $count++;
    }
    return $count;
}