<?php

declare(strict_types=1);

/**
 * LMS : sous-titre module, objectifs, durée estimée ; résumé leçon, objectifs, difficulté, notes formateur.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_modules' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_module_lesson_enrichment : table training_modules absente — ignoré.\n";

        return;
    }
    $col = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_modules' AND COLUMN_NAME = 'course_id' LIMIT 1");
    if (!$col || !$col->fetch()) {
        echo "[ATTENTION] training_module_lesson_enrichment : training_modules sans course_id (schéma legacy) — ignoré.\n";

        return;
    }

    $moduleCols = [
        'subtitle' => 'ADD COLUMN `subtitle` VARCHAR(255) NULL DEFAULT NULL AFTER `description`',
        'learning_objectives' => 'ADD COLUMN `learning_objectives` TEXT NULL DEFAULT NULL AFTER `subtitle`',
        'estimated_minutes' => 'ADD COLUMN `estimated_minutes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `learning_objectives`',
    ];

    foreach ($moduleCols as $name => $fragment) {
        $q = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_modules' AND COLUMN_NAME = " . $pdo->quote($name) . ' LIMIT 1');
        if ($q && $q->fetch()) {
            continue;
        }
        echo "training_modules : ajout colonne {$name}...\n";
        try {
            $pdo->exec('ALTER TABLE training_modules ' . $fragment);
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $e->getMessage() . "\n";
        }
    }

    $lessonCols = [
        'summary' => 'ADD COLUMN `summary` VARCHAR(500) NULL DEFAULT NULL AFTER `title`',
        'learning_objectives' => 'ADD COLUMN `learning_objectives` TEXT NULL DEFAULT NULL AFTER `summary`',
        'difficulty' => 'ADD COLUMN `difficulty` VARCHAR(20) NULL DEFAULT NULL AFTER `duration_minutes`',
        'instructor_notes' => 'ADD COLUMN `instructor_notes` TEXT NULL DEFAULT NULL AFTER `learning_objectives`',
    ];

    foreach ($lessonCols as $name => $fragment) {
        $q = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_lessons' AND COLUMN_NAME = " . $pdo->quote($name) . ' LIMIT 1');
        if ($q && $q->fetch()) {
            continue;
        }
        echo "training_lessons : ajout colonne {$name}...\n";
        try {
            $pdo->exec('ALTER TABLE training_lessons ' . $fragment);
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $e->getMessage() . "\n";
        }
    }
};
