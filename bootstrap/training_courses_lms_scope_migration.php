<?php

declare(strict_types=1);

/**
 * training_courses.lms_scope : formations « communauté » vs « plateforme Athena » (visibles par tous les tenants).
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_courses_lms_scope : table training_courses absente — ignoré.\n";

        return;
    }

    $q = $pdo->query(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '
        . $pdo->quote('training_courses') . " AND COLUMN_NAME = 'lms_scope' LIMIT 1"
    );
    if ($q && $q->fetch()) {
        return;
    }

    echo "training_courses : ajout colonne lms_scope (tenant | platform)...\n";
    try {
        $pdo->exec(
            "ALTER TABLE training_courses ADD COLUMN lms_scope ENUM('tenant','platform') NOT NULL DEFAULT 'tenant' AFTER tenant_id"
        );
    } catch (PDOException $e) {
        echo '  [ATTENTION] ' . $e->getMessage() . "\n";
    }
};
