<?php

declare(strict_types=1);

/**
 * Étape « Backfill indicatif plateforme : users.callsign ← personnel_profiles / user_profiles (source unique) » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Backfill indicatif plateforme : users.callsign ← personnel_profiles / user_profiles (source unique)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'callsign'");
if ($stmt && $stmt->fetch()) {
    echo "Backfill users.callsign depuis dossier / profil (si vide)...\n";
    $pdo->exec(
        'UPDATE users u
         LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
         LEFT JOIN user_profiles up ON up.user_id = u.id
         SET u.callsign = COALESCE(
           NULLIF(TRIM(u.callsign), \'\'),
           NULLIF(TRIM(pp.callsign), \'\'),
           NULLIF(TRIM(up.arma_callsign), \'\')
         )
         WHERE (u.callsign IS NULL OR TRIM(u.callsign) = \'\')
           AND (
             (pp.callsign IS NOT NULL AND TRIM(pp.callsign) <> \'\')
             OR (up.arma_callsign IS NOT NULL AND TRIM(up.arma_callsign) <> \'\')
           )'
    );
}
