<?php

declare(strict_types=1);

/**
 * LMS : statut d’inscription « withdrawn » (abandon volontaire du membre), distinct de « revoked » (retrait staff).
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_enrollments' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_enrollment_withdrawn : table training_enrollments absente — ignoré.\n";

        return;
    }

    $st = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_enrollments' AND COLUMN_NAME = 'status' LIMIT 1");
    $colType = $st ? (string) $st->fetchColumn() : '';
    if ($colType !== '' && str_contains($colType, 'withdrawn')) {
        return;
    }

    echo "training_enrollments : extension ENUM status (withdrawn)...\n";
    try {
        $pdo->exec(
            "ALTER TABLE training_enrollments MODIFY COLUMN status ENUM("
            . "'assigned','in_progress','completed','failed','expired','revoked','pending_approval','withdrawn'"
            . ") NOT NULL DEFAULT 'assigned'"
        );
    } catch (PDOException $e) {
        echo '  [ATTENTION] ' . $e->getMessage() . "\n";
    }
};
