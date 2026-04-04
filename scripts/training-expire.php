#!/usr/bin/env php
<?php

/**
 * Script d’expiration des formations et certificats.
 * À exécuter en cron (ex. quotidien) :
 * php scripts/training-expire.php
 *
 * - Marque les enrollments expirés (expires_at < NOW()) en status = expired
 * - Marque les certificats expirés (expires_at < NOW()) en status = expired
 */

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Autoload not found. Run composer install.\n");
    exit(1);
}
require $autoload;

$configPath = dirname(__DIR__) . '/app/Config/database.local.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "database.local.php not found.\n");
    exit(1);
}
$cfg = require $configPath;
if (empty($cfg['username']) || empty($cfg['password'])) {
    fwrite(STDERR, "Database config incomplete.\n");
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $cfg['host'] ?? 'localhost',
    $cfg['port'] ?? 3306,
    $cfg['database'] ?? '',
    $cfg['charset'] ?? 'utf8mb4'
);
try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$countEnrollments = 0;
$stmt = $pdo->prepare("UPDATE training_enrollments SET status = 'expired' WHERE status IN ('assigned','in_progress') AND expires_at IS NOT NULL AND expires_at < NOW()");
$stmt->execute();
$countEnrollments = $stmt->rowCount();

$countCertificates = 0;
$stmt = $pdo->prepare("UPDATE training_certificates SET status = 'expired' WHERE status = 'valid' AND expires_at IS NOT NULL AND expires_at < NOW()");
$stmt->execute();
$countCertificates = $stmt->rowCount();

echo date('Y-m-d H:i:s') . " — Enrollments expirés : $countEnrollments — Certificats expirés : $countCertificates\n");
exit(0);
