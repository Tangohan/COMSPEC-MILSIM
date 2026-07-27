<?php

declare(strict_types=1);

/**
 * Bascule progressive du lecteur de leçon, formation par formation.
 *
 * `legacy` (défaut) conserve le lecteur Swiper historique ; `stage` active le lecteur
 * « scène 16:9 ». Le défaut vaut `legacy` pour qu'aucune formation existante ne change
 * d'apparence sans décision explicite.
 *
 * Idempotent : rejouable sans effet de bord.
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    require_once __DIR__ . '/schema_ensure_column.php';

    if (!schema_table_exists($pdo, 'training_courses')) {
        echo "  [ATTENTION] training_courses absente — skip lesson_player_mode\n";

        return;
    }

    schema_ensure_column(
        $pdo,
        'training_courses',
        'lesson_player_mode',
        "`lesson_player_mode` varchar(16) NOT NULL DEFAULT 'legacy' AFTER `visibility`"
    );
};
