<?php

declare(strict_types=1);

/**
 * Formations « lien » et nominations pédagogiques par personne — idempotent.
 *
 * - training_courses.delivery_mode : parcours (défaut), url_redirect (simple redirection), url_viewer (visionneuse intégrée)
 * - training_courses.delivery_url : adresse ouverte par une formation « lien »
 * - training_pedagogy_designations : membre nommé à une étape de la chaîne pédagogique (sans passer par un rôle)
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if ($chk && $chk->fetch()) {
        $cols = [
            'delivery_mode' => "ADD COLUMN delivery_mode VARCHAR(20) NOT NULL DEFAULT 'parcours'",
            'delivery_url' => 'ADD COLUMN delivery_url VARCHAR(1000) NULL DEFAULT NULL',
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
    } else {
        echo "[ATTENTION] training_url_delivery : table training_courses absente — colonnes ignorées.\n";
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS training_pedagogy_designations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            pedagogy_kind VARCHAR(40) NOT NULL,
            designated_by_user_id INT UNSIGNED NULL DEFAULT NULL,
            note VARCHAR(255) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tpd_tenant_user_kind (tenant_id, user_id, pedagogy_kind),
            KEY idx_tpd_tenant_kind (tenant_id, pedagogy_kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
