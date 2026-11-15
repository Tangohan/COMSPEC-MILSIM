<?php

declare(strict_types=1);

/**
 * Étape « Boîte de contact de la plateforme : fils, messages, pièces jointes, état IMAP » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle
 * manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_15_010_contact_mailbox.sql). Aucune donnée n'est ajoutée
 * ni modifiée : sans CONTACT_MAIL_PASSWORD dans le .env, la boîte reste vide et ne se connecte à rien. Retour arrière :
 * migrations/rollback/contact_mailbox_down.sql.
 */

$contactMailSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$contactMailTables = ['contact_mail_threads', 'contact_mail_messages', 'contact_mail_attachments', 'contact_mail_sync_state'];
$contactMailMissing = array_values(array_filter($contactMailTables, static fn (string $t): bool => !$contactMailSchema->tableExists($t)));
if ($contactMailMissing === []) {
    echo "  [OK] Tables de la boîte de contact déjà présentes.\n";
} else {
    $contactMailSchema->runSqlFile($root . '/migrations/2026_11_15_010_contact_mailbox.sql');
    foreach ($contactMailTables as $contactMailTable) {
        if (!$contactMailSchema->tableExists($contactMailTable)) {
            throw new RuntimeException('Création de la table ' . $contactMailTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $contactMailMissing) . ".\n";
}
