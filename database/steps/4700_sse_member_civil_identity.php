<?php

declare(strict_types=1);

/**
 * Étape « Identité officielle d'un membre = son état civil » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/sse_member_civil_identity_migration.php (trois colonnes, aucune donnée modifiée, rien d'aligné ici : l'alignement
 * se fait par le bouton du registre ou par tools/sse-sync-member-identities.php, en simulation par défaut).
 * Retour arrière : migrations/rollback/sse_member_civil_identity_down.sql.
 */

$sseMemberCivilMigrate = require $root . '/bootstrap/sse_member_civil_identity_migration.php';
try {
    echo "Migration sse_member_civil_identity (nom volontairement différent, date d'alignement, date de naissance du personnage)…\n";
    $sseMemberCivilMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_member_civil_identity : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_member_civil_identity', $e);
}
$migrationEnsurePdo();
