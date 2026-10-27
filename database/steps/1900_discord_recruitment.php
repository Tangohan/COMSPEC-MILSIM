<?php

declare(strict_types=1);

/**
 * Étape « Migration discord_recruitment (recrutement via Discord) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// ----- Migrations annexes (toujours, même si le tenant default existait déjà) -----
// Avant : exit(0) dès que slug=default existait → discord_recruitment, ATAK, etc. ne tournaient jamais en prod.
$discordRecruitmentMigrate = require $root . '/bootstrap/discord_recruitment_migration.php';
try {
    echo "Migration discord_recruitment (recrutement via Discord)...\n";
    $discordRecruitmentMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] discord_recruitment : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('discord_recruitment', $e);
}
