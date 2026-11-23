<?php

declare(strict_types=1);

/**
 * Étape « Comptes : région déclarée » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : CREATE TABLE IF NOT EXISTS.
 * Une ligne par compte qui a choisi une région : le code seulement (liste fermée du catalogue PHP) et sa date.
 * Aucune adresse IP, aucune donnée existante modifiée. Identifiant en INT UNSIGNED comme users.id.
 */

try {
    echo "Comptes : région déclarée…\n";
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_regions (
            user_id INT UNSIGNED NOT NULL,
            region_code VARCHAR(8) NOT NULL,
            source VARCHAR(12) NOT NULL DEFAULT \'declared\',
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_id),
            KEY idx_user_regions_code (region_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    echo '  [ATTENTION] user_regions : ' . $e->getMessage() . "\n";
    $migrationRunner?->warn('user_regions', $e);
}
$migrationEnsurePdo();
