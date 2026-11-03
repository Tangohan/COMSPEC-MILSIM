<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC IDENTITY : papiers des joueurs dans la fiche membre, kit de mission, pays émetteurs » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_identity_member_papers_migration.php (colonnes et table ajoutées seulement si elles manquent),
 * après le registre (3730) qu'elle rejoue sans effet s'il est déjà en place.
 */

$atakSseIdentityMigrate = require $root . '/bootstrap/atak_sse_identity_migration.php';
$atakSseIdentityMemberPapersMigrate = require $root . '/bootstrap/atak_sse_identity_member_papers_migration.php';
try {
    echo "Migration atak_sse_identity_member_papers (papiers des membres, kits, pays émetteurs)…\n";
    $atakSseIdentityMigrate($pdo, $sseCliLog);
    $atakSseIdentityMemberPapersMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_identity_member_papers : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_identity_member_papers', $e);
}
$migrationEnsurePdo();
