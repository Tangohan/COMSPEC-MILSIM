<?php

declare(strict_types=1);

/**
 * Étape « Bot Discord Athena : réglages par communauté, rappels, audit » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_17_100_discord_bot.sql). Aucune donnée n'est ajoutée ni modifiée : sans
 * cette étape le bot garde son fonctionnement actuel (événements) et les nouvelles commandes répondent « non configuré ».
 * Retour arrière : migrations/rollback/discord_bot_down.sql.
 */

$discordBotSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$discordBotTables = ['discord_bot_settings', 'discord_bot_reminders', 'discord_bot_audit', 'discord_bot_dismissals'];
$discordBotMissing = array_values(array_filter($discordBotTables, static fn (string $t): bool => !$discordBotSchema->tableExists($t)));
if ($discordBotMissing === []) {
    echo "  [OK] Tables du bot Discord déjà présentes.\n";
} else {
    $discordBotSchema->runSqlFile($root . '/migrations/2026_11_17_100_discord_bot.sql');
    foreach ($discordBotTables as $discordBotTable) {
        if (!$discordBotSchema->tableExists($discordBotTable)) {
            throw new RuntimeException('Création de la table ' . $discordBotTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $discordBotMissing) . ".\n";
}
