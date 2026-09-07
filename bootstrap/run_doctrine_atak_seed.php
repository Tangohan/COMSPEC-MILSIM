<?php

declare(strict_types=1);

/**
 * Applique uniquement le seed doctrine ATAK (PDF officiel).
 * Usage : php bootstrap/run_doctrine_atak_seed.php
 */

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " \t\"'");
        putenv(trim($name) . '=' . trim($value, " \t\"'"));
    }
}

require_once $root . '/bootstrap/migration_pdo.php';
require_once $root . '/app/Support/SqlText.php';

$name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
$pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
if ($name === '' || $user === '') {
    fwrite(STDERR, "DB_NAME et DB_USER requis.\n");
    exit(1);
}

$pdo = new PDO(migration_mysql_dsn(), $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$seed = require $root . '/bootstrap/doctrine_atak_employment_seed.php';
if (!is_callable($seed)) {
    fwrite(STDERR, "Seed ATAK illisible.\n");
    exit(1);
}
$seed($pdo);

$st = $pdo->query(
    "SELECT d.tenant_id, d.id, dv.file_path, dv.mime_type, dv.original_name, dv.version_label
     FROM documents d
     INNER JOIN document_doctrines dd ON dd.document_id = d.id
     LEFT JOIN document_versions dv ON dv.document_id = d.id AND dv.is_current = 1
     WHERE dd.reference_code = 'SIC/ATAK/2026-001' OR d.slug = 'sic-atak-2026-001'"
);
$rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
echo 'Fiches mises à jour : ' . count($rows) . "\n";
foreach ($rows as $row) {
    echo sprintf(
        "  tenant=%s doc=%s mime=%s file=%s label=%s name=%s\n",
        $row['tenant_id'] ?? '',
        $row['id'] ?? '',
        $row['mime_type'] ?? '',
        $row['file_path'] ?? '',
        $row['version_label'] ?? '',
        $row['original_name'] ?? ''
    );
}
