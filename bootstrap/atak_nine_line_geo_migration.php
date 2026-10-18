<?php

declare(strict_types=1);

/**
 * Colonne geo_json sur atak_nine_line : géométrie de l'app JTAC du téléphone ATAK natif (cible, IP, cône FAH,
 * distance de risque, JTAC, ami le plus proche, appareil), redessinée sur la carte du poste Overwatch.
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute(['atak_nine_line']);
    if (!$st->fetchColumn()) {
        echo "  [ATTENTION] atak_nine_line absente\n";

        return;
    }

    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute(['atak_nine_line', 'geo_json']);
    if (!$st->fetchColumn()) {
        $pdo->exec('ALTER TABLE atak_nine_line ADD COLUMN geo_json TEXT NULL AFTER lines_checked');
        echo "  OK atak_nine_line.geo_json\n";
    }
};
