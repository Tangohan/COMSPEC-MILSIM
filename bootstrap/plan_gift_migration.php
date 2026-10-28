<?php

declare(strict_types=1);

/**
 * Formules offertes par l'administration de la plateforme à une communauté.
 *
 * - plan_gifts : une offre (communauté, formule, fin ou permanente, message FR/EN, auteur, révocation).
 *   La formule offerte en cours est recopiée dans tenants.settings (clé plan_gift) et lue par FeatureGateService.
 * - plan_gift_notices : une ligne par destinataire (gestionnaires de la communauté) : e-mail envoyé, fenêtre vue.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('plan_gifts')) {
        $pdo->exec(
            "CREATE TABLE plan_gifts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                plan_slug VARCHAR(64) NOT NULL,
                plan_name VARCHAR(120) NOT NULL DEFAULT '',
                starts_at DATETIME NOT NULL,
                ends_at DATETIME DEFAULT NULL,
                message_fr TEXT DEFAULT NULL,
                message_en TEXT DEFAULT NULL,
                granted_by_user_id INT UNSIGNED DEFAULT NULL,
                revoked_at DATETIME DEFAULT NULL,
                revoked_by_user_id INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_plan_gifts_tenant (tenant_id, revoked_at),
                KEY idx_plan_gifts_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('plan_gift_notices')) {
        $pdo->exec(
            "CREATE TABLE plan_gift_notices (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                gift_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                email VARCHAR(255) NOT NULL DEFAULT '',
                email_sent_at DATETIME DEFAULT NULL,
                dismissed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_plan_gift_notice (gift_id, user_id),
                KEY idx_plan_gift_notice_user (user_id, dismissed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
};
