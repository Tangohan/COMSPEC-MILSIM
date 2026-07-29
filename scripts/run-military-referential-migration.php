<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/.env') && is_file(__DIR__ . '/.env')) {
    $root = __DIR__;
}

$envFile = $root . '/.env';
if (!is_file($envFile)) {
    fwrite(STDERR, "Missing .env\n");
    exit(1);
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v, " \t\"'");
    if ($k !== '') {
        putenv("{$k}={$v}");
        $_ENV[$k] = $v;
    }
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    getenv('DB_HOST') ?: '127.0.0.1',
    getenv('DB_PORT') ?: '3306',
    getenv('DB_NAME') ?: '',
    getenv('DB_CHARSET') ?: 'utf8mb4'
);

echo 'Connecting to ' . (getenv('DB_HOST') ?: '') . ' / ' . (getenv('DB_NAME') ?: '') . "...\n";

$pdo = new PDO($dsn, getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$migrate = require $root . '/bootstrap/military_referential_migration.php';
echo "Migration référentiel militaire SOF...\n";
$migrate($pdo);

$n = (int) $pdo->query('SELECT COUNT(*) FROM military_units')->fetchColumn();
$c = (int) $pdo->query('SELECT COUNT(*) FROM countries')->fetchColumn();
$a = (int) $pdo->query('SELECT COUNT(*) FROM tenant_military_unit_affiliations')->fetchColumn();
$aliases = (int) $pdo->query('SELECT COUNT(*) FROM military_unit_aliases')->fetchColumn();

echo "OK — countries={$c} military_units={$n} aliases={$aliases} affiliations={$a}\n";

// Quick integrity probes
$checks = [
    'fr-cdo-hubert' => 'SELECT code FROM military_units WHERE code = ?',
    'Hubert alias' => "SELECT a.alias FROM military_unit_aliases a INNER JOIN military_units u ON u.id = a.unit_id WHERE u.code = 'fr-cdo-hubert' AND a.alias = 'Hubert'",
    'us-5sfg parent' => "SELECT p.code FROM military_units u LEFT JOIN military_units p ON p.id = u.parent_id WHERE u.code = 'us-5sfg'",
];
foreach (['fr-cdo-hubert', 'fr-1rpima', 'us-usasoc', 'us-5sfg'] as $code) {
    $st = $pdo->prepare('SELECT 1 FROM military_units WHERE code = ?');
    $st->execute([$code]);
    echo $st->fetchColumn() ? "  [OK] {$code}\n" : "  [FAIL] {$code}\n";
}
$st = $pdo->query("SELECT p.code FROM military_units u INNER JOIN military_units p ON p.id = u.parent_id WHERE u.code = 'fr-cdo-hubert'");
echo '  parent Hubert = ' . (($st && ($v = $st->fetchColumn())) ? $v : 'NULL') . "\n";
$st = $pdo->query("SELECT p.code FROM military_units u INNER JOIN military_units p ON p.id = u.parent_id WHERE u.code = 'us-5sfg'");
echo '  parent 5SFG = ' . (($st && ($v = $st->fetchColumn())) ? $v : 'NULL') . "\n";
