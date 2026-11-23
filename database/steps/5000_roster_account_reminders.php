<?php

declare(strict_types=1);

/**
 * Étape « Effectifs : compte obligatoire des personnes sans compte » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : CREATE TABLE IF NOT EXISTS.
 * Une ligne par personne relancée (dernier rappel et nombre de rappels). Le réglage de la communauté (obligation,
 * délai, restriction) vit dans tenants.settings : aucune colonne ajoutée, aucune donnée existante modifiée.
 * Identifiants en INT UNSIGNED comme tenants.id et users.id ; toute lecture filtre sur tenant_id.
 */

try {
    echo "Effectifs : rappels de création de compte…\n";
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS roster_account_reminders (
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            last_reminded_at DATETIME NOT NULL,
            reminder_count INT UNSIGNED NOT NULL DEFAULT 1,
            last_channel VARCHAR(16) NOT NULL DEFAULT \'\',
            last_actor_user_id INT UNSIGNED NULL,
            PRIMARY KEY (tenant_id, user_id),
            KEY idx_rar_tenant (tenant_id, last_reminded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    echo '  [ATTENTION] roster_account_reminders : ' . $e->getMessage() . "\n";
    $migrationRunner?->warn('roster_account_reminders', $e);
}
$migrationEnsurePdo();
