<?php

declare(strict_types=1);

/**
 * Colonnes vitrine dashboard (cartes + modale) sur training_courses — idempotent.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_showcase : table training_courses absente — ignoré.\n";

        return;
    }

    $cols = [
        'showcase_cycle_date' => "ADD COLUMN showcase_cycle_date DATE NULL DEFAULT NULL AFTER banner_path",
        'showcase_location' => "ADD COLUMN showcase_location VARCHAR(255) NULL DEFAULT NULL AFTER showcase_cycle_date",
        'showcase_badge' => "ADD COLUMN showcase_badge VARCHAR(32) NULL DEFAULT 'open' AFTER showcase_location",
        'showcase_card_style' => "ADD COLUMN showcase_card_style VARCHAR(32) NULL DEFAULT 'default' AFTER showcase_badge",
        'showcase_sort_order' => "ADD COLUMN showcase_sort_order INT UNSIGNED NULL DEFAULT NULL AFTER showcase_card_style",
    ];

    foreach ($cols as $name => $fragment) {
        $q = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' AND COLUMN_NAME = " . $pdo->quote($name) . " LIMIT 1");
        if ($q && $q->fetch()) {
            continue;
        }
        echo "training_courses : ajout colonne {$name}...\n";
        try {
            $pdo->exec('ALTER TABLE training_courses ' . $fragment);
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $e->getMessage() . "\n";
        }
    }
};
