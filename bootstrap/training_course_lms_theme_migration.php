<?php

declare(strict_types=1);

/**
 * Colonnes LMS : code affichage, objectifs, thème JSON — idempotent.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_course_lms_theme : table training_courses absente — ignoré.\n";

        return;
    }

    $cols = [
        'course_code' => "ADD COLUMN course_code VARCHAR(32) NULL DEFAULT NULL AFTER slug",
        'learning_objectives' => "ADD COLUMN learning_objectives LONGTEXT NULL DEFAULT NULL AFTER description",
        'theme_json' => "ADD COLUMN theme_json LONGTEXT NULL DEFAULT NULL AFTER learning_objectives",
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
