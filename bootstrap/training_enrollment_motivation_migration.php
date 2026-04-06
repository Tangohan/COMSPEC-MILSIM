<?php

declare(strict_types=1);

/**
 * Texte de motivation optionnel à l’auto-inscription (training_enrollments.motivation_text).
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_enrollments' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_enrollment_motivation : table training_enrollments absente — ignoré.\n";

        return;
    }
    $q = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_enrollments' AND COLUMN_NAME = 'motivation_text' LIMIT 1");
    if ($q && $q->fetch()) {
        return;
    }
    echo "training_enrollments : ajout colonne motivation_text...\n";
    try {
        $pdo->exec('ALTER TABLE training_enrollments ADD COLUMN motivation_text TEXT NULL DEFAULT NULL AFTER expires_at');
    } catch (PDOException $e) {
        echo '  [ATTENTION] ' . $e->getMessage() . "\n";
    }
};
