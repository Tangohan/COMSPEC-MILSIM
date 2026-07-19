<?php

declare(strict_types=1);

/**
 * Épinglage de sujets forum sur le tableau de bord (communication communauté).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'forum_topics'
           AND COLUMN_NAME = 'pin_on_dashboard'
         LIMIT 1"
    );
    if ($chk && $chk->fetch()) {
        return;
    }

    $pdo->exec(
        "ALTER TABLE forum_topics
         ADD COLUMN pin_on_dashboard TINYINT(1) NOT NULL DEFAULT 0
           COMMENT '1 = visible sur le tableau de bord de la communauté'
           AFTER is_pinned,
         ADD KEY idx_ft_tenant_dash_pin (tenant_id, pin_on_dashboard, updated_at)"
    );

    echo "Colonne forum_topics.pin_on_dashboard ajoutée.\n";
};
