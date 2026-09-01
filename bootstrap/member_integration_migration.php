<?php

declare(strict_types=1);

/**
 * Parcours d’intégration des nouveaux membres. Idempotent.
 */
function run_member_integration_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $sqlFile = dirname(__DIR__) . '/migrations/20260901000001_member_integration.sql';
    if (!is_file($sqlFile)) {
        echo "  [ATTENTION] member_integration : fichier SQL introuvable\n";

        return;
    }

    $sql = (string) file_get_contents($sqlFile);
    $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '' || !preg_match('/CREATE TABLE/i', $stmt)) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            echo '  [ATTENTION] member_integration : ' . $e->getMessage() . "\n";
        }
    }

    if ($hasTable('member_integrations')) {
        echo "  [OK] member_integration\n";
    }
}
