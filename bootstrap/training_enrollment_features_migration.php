<?php

declare(strict_types=1);

/**
 * LMS : statut d’inscription « en attente de validation », code de partage formation (unique global).
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_enrollments' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_enrollment_features : table training_enrollments absente — ignoré.\n";

        return;
    }

    $st = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_enrollments' AND COLUMN_NAME = 'status' LIMIT 1");
    $colType = $st ? (string) $st->fetchColumn() : '';
    if ($colType !== '' && !str_contains($colType, 'pending_approval')) {
        echo "training_enrollments : extension ENUM status (pending_approval)...\n";
        try {
            $pdo->exec(
                "ALTER TABLE training_enrollments MODIFY COLUMN status ENUM("
                . "'assigned','in_progress','completed','failed','expired','revoked','pending_approval'"
                . ") NOT NULL DEFAULT 'assigned'"
            );
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $e->getMessage() . "\n";
        }
    }

    $chkC = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' AND COLUMN_NAME = 'enrollment_share_code' LIMIT 1");
    if (!$chkC || !$chkC->fetch()) {
        echo "training_courses : colonne enrollment_share_code...\n";
        try {
            $pdo->exec(
                'ALTER TABLE training_courses ADD COLUMN enrollment_share_code VARCHAR(20) NULL DEFAULT NULL'
            );
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $e->getMessage() . "\n";
        }
    }

    $idx = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' AND INDEX_NAME = 'uk_training_courses_enrollment_share_code' LIMIT 1");
    if (!$idx || !$idx->fetch()) {
        echo "training_courses : index unique enrollment_share_code...\n";
        try {
            $pdo->exec('CREATE UNIQUE INDEX uk_training_courses_enrollment_share_code ON training_courses (enrollment_share_code)');
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $e->getMessage() . "\n";
        }
    }
};
