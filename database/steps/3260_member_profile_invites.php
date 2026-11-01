<?php

declare(strict_types=1);

/**
 * Étape « Liens pour compléter son profil » (Schéma) : table member_profile_invites.
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n'est créée que si elle
 * manque, depuis le seul fichier migrations/2026_11_01_010_member_profile_invites.sql (CREATE TABLE IF NOT EXISTS).
 * Aucune donnée existante n'est touchée.
 */

$memberProfileInvitesSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($memberProfileInvitesSchema->tableExists('member_profile_invites')) {
    echo "  [OK] Table member_profile_invites déjà présente.\n";
} else {
    $memberProfileInvitesSchema->runSqlFile($root . '/migrations/2026_11_01_010_member_profile_invites.sql');
    echo "  [OK] Table member_profile_invites créée.\n";
}
