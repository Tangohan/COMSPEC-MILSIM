<?php

declare(strict_types=1);

/**
 * Étape « Messages de la plateforme aux back-offices des communautés » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : CREATE TABLE IF NOT EXISTS.
 * - platform_bo_messages : le message (FR/EN), son niveau, sa fenêtre de diffusion et son ciblage.
 * - platform_bo_message_tenants : une ligne par communauté (vu, cliqué, fermé, acquitté) : base des compteurs.
 * - platform_bo_message_users : une ligne par personne (fermé, vu, ouvert) : masquage individuel seulement.
 * Identifiants en INT UNSIGNED comme tenants.id et users.id.
 */

try {
    echo "Messages de la plateforme aux back-offices…\n";
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS platform_bo_messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(12) NOT NULL DEFAULT \'info\',
            title_fr VARCHAR(160) NOT NULL DEFAULT \'\',
            title_en VARCHAR(160) NOT NULL DEFAULT \'\',
            body_fr TEXT NULL,
            body_en TEXT NULL,
            cta_label_fr VARCHAR(80) NULL,
            cta_label_en VARCHAR(80) NULL,
            cta_url VARCHAR(500) NULL,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            target_mode VARCHAR(12) NOT NULL DEFAULT \'all\',
            target_json TEXT NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_pbm_live (is_active, starts_at, ends_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS platform_bo_message_tenants (
            message_id INT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            seen_at DATETIME NULL,
            clicked_at DATETIME NULL,
            dismissed_at DATETIME NULL,
            acked_at DATETIME NULL,
            acked_by INT UNSIGNED NULL,
            PRIMARY KEY (message_id, tenant_id),
            KEY idx_pbmt_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS platform_bo_message_users (
            message_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            seen_at DATETIME NULL,
            opened_at DATETIME NULL,
            dismissed_at DATETIME NULL,
            PRIMARY KEY (message_id, user_id),
            KEY idx_pbmu_tenant (tenant_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    echo '  [ATTENTION] platform_bo_messages : ' . $e->getMessage() . "\n";
    $migrationRunner?->warn('platform_bo_messages', $e);
}
$migrationEnsurePdo();
