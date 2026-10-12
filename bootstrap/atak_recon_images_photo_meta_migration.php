<?php

declare(strict_types=1);

/**
 * Fiche des clichés de reconnaissance (drone de l'ATAK natif, commande NativePhoto de la DLL native) :
 * titre, description, SSE (+ référence du dossier), confidentiel, source et nom du drone.
 * Avant, ces champs étaient tassés dans la légende. Lancée à la demande par ReconImageRepository
 * et par run-migrations.php ; sans effet si les colonnes existent déjà.
 */
return static function (PDO $pdo): void {
    $table = 'recon_images';
    $exists = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table) . " LIMIT 1"
    );
    if (!$exists || !$exists->fetchColumn()) {
        return;
    }
    $columns = [
        'title' => "ALTER TABLE recon_images ADD COLUMN title varchar(120) DEFAULT NULL AFTER caption",
        'description' => "ALTER TABLE recon_images ADD COLUMN description text DEFAULT NULL AFTER title",
        'is_sse' => "ALTER TABLE recon_images ADD COLUMN is_sse tinyint(1) NOT NULL DEFAULT 0 AFTER description",
        'sse_case_ref' => "ALTER TABLE recon_images ADD COLUMN sse_case_ref varchar(64) DEFAULT NULL AFTER is_sse",
        'is_confidential' => "ALTER TABLE recon_images ADD COLUMN is_confidential tinyint(1) NOT NULL DEFAULT 0 AFTER sse_case_ref",
        'photo_source' => "ALTER TABLE recon_images ADD COLUMN photo_source varchar(16) DEFAULT NULL AFTER is_confidential",
        'drone_name' => "ALTER TABLE recon_images ADD COLUMN drone_name varchar(120) DEFAULT NULL AFTER photo_source",
    ];

    foreach ($columns as $column => $sql) {
        $stmt = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table) . "
               AND COLUMN_NAME = " . $pdo->quote($column)
        );
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec($sql);
        }
    }
};
