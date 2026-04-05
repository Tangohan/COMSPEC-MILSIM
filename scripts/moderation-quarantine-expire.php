<?php

declare(strict_types=1);

/**
 * Expiration quarantaine modération : rejette les artefacts expirés, supprime les fichiers,
 * enregistre une décision avec le compte technique « Modération automatique ».
 *
 * Cron : php scripts/moderation-quarantine-expire.php
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
    }
}

require_once $root . '/bootstrap/autoload.php';

$dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbName = $_ENV['DB_NAME'] ?? '';
$dbUser = $_ENV['DB_USER'] ?? '';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

if ($dbName === '' || $dbUser === '') {
    fwrite(STDERR, "DB_NAME et DB_USER requis.\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$dbHost};dbname={$dbName};charset={$charset}",
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$stmt = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_artifacts' LIMIT 1");
if (!$stmt || !$stmt->fetchColumn()) {
    echo "Table moderation_artifacts absente — rien à faire.\n";
    exit(0);
}

$userRepo = new \App\Repositories\UserRepository();
$decisionRepo = new \App\Repositories\ModerationDecisionRepository();

$before = new DateTimeImmutable();
$sel = $pdo->prepare(
    "SELECT * FROM moderation_artifacts WHERE state IN ('quarantined','pending_scan') AND expires_at IS NOT NULL AND expires_at < ?"
);
$sel->execute([$before->format('Y-m-d H:i:s')]);
$rows = $sel->fetchAll(PDO::FETCH_ASSOC);
$n = 0;
foreach ($rows as $row) {
    $rel = (string) ($row['file_path'] ?? '');
    if ($rel !== '') {
        $full = str_starts_with($rel, 'public/')
            ? $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel)
            : $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($full)) {
            @unlink($full);
        }
    }
    $artifactId = (int) $row['id'];
    $tenantId = (int) ($row['tenant_id'] ?? 0);
    $upd = $pdo->prepare('UPDATE moderation_artifacts SET state = ? WHERE id = ?');
    $upd->execute(['rejected', $artifactId]);

    $actorId = $tenantId > 0 ? $userRepo->ensureSystemModeratorUser($tenantId) : null;
    if ($actorId !== null && $actorId > 0) {
        try {
            $decisionRepo->insert($artifactId, $actorId, 'reject', 'quarantine_expired', 'Expiration délai quarantaine (cron)');
        } catch (Throwable $e) {
            fwrite(STDERR, "Décision artefact #{$artifactId} : " . $e->getMessage() . "\n");
        }
    }
    $n++;
}
echo "Artefacts expirés traités : {$n}\n";
