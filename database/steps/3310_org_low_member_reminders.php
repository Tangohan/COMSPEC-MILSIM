<?php

declare(strict_types=1);

/**
 * Étape « Communautés à un ou deux membres : rappels et liste à supprimer » (Schéma) : tables
 * org_low_member_reminders et org_low_member_watch.
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : les tables ne sont créées que si
 * elles manquent, depuis migrations/2026_11_01_030_org_low_member_reminders.sql (CREATE TABLE IF NOT EXISTS).
 * Aucune donnée existante n'est touchée.
 */

$orgLowMemberSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($orgLowMemberSchema->tableExists('org_low_member_reminders') && $orgLowMemberSchema->tableExists('org_low_member_watch')) {
    echo "  [OK] Tables org_low_member_reminders et org_low_member_watch déjà présentes.\n";
} else {
    $orgLowMemberSchema->runSqlFile($root . '/migrations/2026_11_01_030_org_low_member_reminders.sql');
    echo "  [OK] Tables org_low_member_reminders et org_low_member_watch créées.\n";
}
