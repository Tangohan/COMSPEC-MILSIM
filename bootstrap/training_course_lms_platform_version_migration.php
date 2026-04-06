<?php

declare(strict_types=1);

/**
 * Colonnes training_courses : suivi version Studio LMS — idempotent.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_course_lms_platform_version : table training_courses absente — ignoré.\n";

        return;
    }

    $cols = [
        'lms_created_with_version' => 'ADD COLUMN lms_created_with_version VARCHAR(32) NULL DEFAULT NULL',
        'lms_last_saved_with_version' => 'ADD COLUMN lms_last_saved_with_version VARCHAR(32) NULL DEFAULT NULL',
    ];

    foreach ($cols as $name => $fragment) {
        $q = $pdo->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $pdo->quote('training_courses') . ' AND COLUMN_NAME = ' . $pdo->quote($name) . ' LIMIT 1');
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
