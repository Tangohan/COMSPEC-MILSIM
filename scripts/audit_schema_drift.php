<?php

declare(strict_types=1);

/**
 * Audit de dérive : colonnes attendues dans migrations/schema.sql vs structure réelle (INFORMATION_SCHEMA).
 *
 * Usage (CLI, à la racine du projet) :
 *   php scripts/audit_schema_drift.php
 *   php scripts/audit_schema_drift.php --dump=u416380327_BDD_PROD.sql
 *
 * Sans --dump : connexion via .env (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD).
 * Avec --dump : compare schema.sql au fichier SQL de dump (hors ligne, sans MySQL).
 *
 * N’applique aucun ALTER — rapport uniquement + ALTER proposés.
 */

$root = dirname(__DIR__);
$dumpPath = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--dump=')) {
        $dumpPath = substr($arg, 7);
        if ($dumpPath !== '' && !str_contains($dumpPath, '/') && !str_contains($dumpPath, '\\')) {
            $dumpPath = $root . DIRECTORY_SEPARATOR . $dumpPath;
        }
    }
}

require_once $root . '/bootstrap/schema_ensure_column.php';

$schemaPath = $root . '/migrations/schema.sql';
if (!is_file($schemaPath)) {
    fwrite(STDERR, "[ERREUR] migrations/schema.sql introuvable\n");
    exit(1);
}

$expected = audit_parse_create_tables((string) file_get_contents($schemaPath));
if ($expected === []) {
    fwrite(STDERR, "[ERREUR] Aucune table CREATE TABLE trouvée dans schema.sql\n");
    exit(1);
}

$actual = [];
$sourceLabel = '';

if ($dumpPath !== null) {
    if (!is_file($dumpPath)) {
        fwrite(STDERR, "[ERREUR] Dump introuvable : {$dumpPath}\n");
        exit(1);
    }
    $actual = audit_parse_create_tables((string) file_get_contents($dumpPath));
    $sourceLabel = 'dump ' . basename($dumpPath);
} else {
    $envFile = $root . '/.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value, " \t\"'");
        }
    }
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $name = $_ENV['DB_NAME'] ?? '';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASSWORD'] ?? '';
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
    if ($name === '' || $user === '') {
        fwrite(STDERR, "[ERREUR] DB_NAME / DB_USER requis (.env) ou utilisez --dump=fichier.sql\n");
        exit(1);
    }
    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$name};charset={$charset}",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        fwrite(STDERR, 'Connexion impossible : ' . $e->getMessage() . "\n");
        exit(1);
    }
    $actual = audit_load_live_columns($pdo, array_keys($expected));
    $sourceLabel = "DB live {$name}@{$host}";
}

echo "=== Audit dérive schéma ===\n";
echo 'Attendu : migrations/schema.sql (' . count($expected) . " tables)\n";
echo "Réel    : {$sourceLabel} (" . count($actual) . " tables comparées)\n\n";

$drifts = [];
$missingTables = [];
foreach ($expected as $table => $cols) {
    if (!isset($actual[$table])) {
        $missingTables[] = $table;
        continue;
    }
    foreach ($cols as $colName => $expectedDef) {
        if (!isset($actual[$table][$colName])) {
            $drifts[] = [
                'kind' => 'missing_column',
                'table' => $table,
                'column' => $colName,
                'expected' => $expectedDef,
                'actual' => null,
            ];
            continue;
        }
        $expNorm = audit_normalize_type($expectedDef);
        $actNorm = audit_normalize_type($actual[$table][$colName]);
        if ($expNorm !== $actNorm) {
            $drifts[] = [
                'kind' => 'type_mismatch',
                'table' => $table,
                'column' => $colName,
                'expected' => $expectedDef,
                'actual' => $actual[$table][$colName],
                'expected_norm' => $expNorm,
                'actual_norm' => $actNorm,
            ];
        }
    }
}

if ($missingTables !== []) {
    echo "--- Tables absentes (" . count($missingTables) . ") ---\n";
    foreach ($missingTables as $t) {
        echo "  [TABLE MANQUANTE] {$t}\n";
    }
    echo "\n";
}

if ($drifts === []) {
    echo "Aucune dérive de colonne détectée sur les tables présentes.\n";
    exit(0);
}

echo '--- Dérives colonnes (' . count($drifts) . ") ---\n";
foreach ($drifts as $d) {
    if ($d['kind'] === 'missing_column') {
        echo "  [COLONNE MANQUANTE] {$d['table']}.{$d['column']}\n";
        echo "      attendu : {$d['expected']}\n";
        echo '      ALTER   : ALTER TABLE `' . $d['table'] . '` ADD COLUMN ' . $d['expected'] . ";\n";
    } else {
        echo "  [TYPE DIFFÉRENT] {$d['table']}.{$d['column']}\n";
        echo "      attendu : {$d['expected']}  (norm: {$d['expected_norm']})\n";
        echo "      réel    : {$d['actual']}  (norm: {$d['actual_norm']})\n";
        echo '      ALTER   : ALTER TABLE `' . $d['table'] . '` MODIFY COLUMN ' . $d['expected'] . ";\n";
        echo "      NOTE    : ne pas appliquer sans analyse (données / CHECK / moteur).\n";
    }
}

echo "\nAucun ALTER n’a été appliqué par ce script.\n";
exit(count($drifts) > 0 || $missingTables !== [] ? 2 : 0);

/**
 * @return array<string, array<string, string>> table => [column => raw definition]
 */
function audit_parse_create_tables(string $sql): array
{
    $sql = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
    $out = [];
    if (!preg_match_all(
        '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE/is',
        $sql,
        $matches,
        PREG_SET_ORDER
    )) {
        return [];
    }
    foreach ($matches as $m) {
        $table = $m[1];
        $body = $m[2];
        $cols = [];
        foreach (audit_split_sql_parts($body) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|INDEX|CONSTRAINT|FULLTEXT|SPATIAL)\b/i', $part)) {
                continue;
            }
            if (!preg_match('/^`?([a-zA-Z0-9_]+)`?\s+(.+)$/s', $part, $cm)) {
                continue;
            }
            $cols[$cm[1]] = trim($part);
        }
        if ($cols !== []) {
            $out[$table] = $cols;
        }
    }

    return $out;
}

/**
 * @return list<string>
 */
function audit_split_sql_parts(string $body): array
{
    $parts = [];
    $buf = '';
    $depth = 0;
    $len = strlen($body);
    $inStr = false;
    $strCh = '';
    for ($i = 0; $i < $len; $i++) {
        $ch = $body[$i];
        if ($inStr) {
            $buf .= $ch;
            if ($ch === $strCh && ($i === 0 || $body[$i - 1] !== '\\')) {
                $inStr = false;
            }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $inStr = true;
            $strCh = $ch;
            $buf .= $ch;
            continue;
        }
        if ($ch === '(') {
            $depth++;
            $buf .= $ch;
            continue;
        }
        if ($ch === ')') {
            $depth--;
            $buf .= $ch;
            continue;
        }
        if ($ch === ',' && $depth === 0) {
            $parts[] = $buf;
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    if (trim($buf) !== '') {
        $parts[] = $buf;
    }

    return $parts;
}

/**
 * @param list<string> $tables
 * @return array<string, array<string, string>>
 */
function audit_load_live_columns(PDO $pdo, array $tables): array
{
    $out = [];
    $st = $pdo->prepare(
        'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_TYPE AS ct,
                DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE,
                COLUMN_TYPE AS full_type
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION'
    );
    foreach ($tables as $table) {
        $st->execute([$table]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            continue;
        }
        $cols = [];
        foreach ($rows as $r) {
            $name = (string) $r['COLUMN_NAME'];
            $def = '`' . $name . '` ' . (string) $r['COLUMN_TYPE'];
            if (($r['IS_NULLABLE'] ?? '') === 'NO') {
                $def .= ' NOT NULL';
            } else {
                $def .= ' DEFAULT NULL';
            }
            $cols[$name] = $def;
        }
        $out[$table] = $cols;
    }

    return $out;
}

function audit_normalize_type(string $def): string
{
    $d = strtolower($def);
    $d = preg_replace('/`/', '', $d) ?? $d;
    // Retirer le nom de colonne
    $d = preg_replace('/^[a-z0-9_]+\s+/', '', trim($d)) ?? $d;
    // Variantes dump MariaDB / MySQL
    $d = preg_replace('/\sint\(\d+\)/', ' int', $d) ?? $d;
    $d = preg_replace('/\sbigint\(\d+\)/', ' bigint', $d) ?? $d;
    $d = preg_replace('/\stinyint\(\d+\)/', ' tinyint', $d) ?? $d;
    $d = preg_replace('/\ssmallint\(\d+\)/', ' smallint', $d) ?? $d;
    $d = preg_replace('/\smediumint\(\d+\)/', ' mediumint', $d) ?? $d;
    $d = preg_replace('/character set\s+\w+/', '', $d) ?? $d;
    $d = preg_replace('/collate\s+\w+/', '', $d) ?? $d;
    $d = preg_replace('/check\s*\([^)]*\)/', '', $d) ?? $d;
    // json stocké en longtext + check json_valid → signaler comme longtext (dérive vs json)
    if (preg_match('/\blongtext\b/', $d) && preg_match('/json_valid/', strtolower($def))) {
        $d = 'longtext_json_compat';
    }
    if (preg_match('/\bjson\b/', $d)) {
        // garder json distinct de longtext_json_compat
        $d = preg_replace('/\bjson\b.*/', 'json', $d) ?? $d;
    }
    $d = preg_replace('/\s+/', ' ', $d) ?? $d;
    // Comparer surtout le type de base + nullabilité approximative
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal\([^)]+\)|float|double|varchar\(\d+\)|char\(\d+\)|text|mediumtext|longtext|tinytext|json|longtext_json_compat|datetime|timestamp|date|time|blob|mediumblob|longblob|enum\([^)]+\)|set\([^)]+\))/', trim($d), $m)) {
        $base = $m[1];
        $nullable = !preg_match('/\bnot null\b/', $d);
        return $base . ($nullable ? '|null' : '|notnull');
    }

    return trim($d);
}
