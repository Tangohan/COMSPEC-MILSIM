<?php

declare(strict_types=1);

/**
 * Abrégé d'unité saisi à la main (units.short_label). Vide : abrégé automatique
 * (App\Support\UnitAbbreviation), utilisé par le back-office et l'ATAK.
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $st = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN (?, ?)'
    );
    $st->execute(['units', 'name', 'short_label']);
    $cols = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if (!in_array('name', $cols, true) || in_array('short_label', $cols, true)) {
        return;
    }
    try {
        $pdo->exec(
            "ALTER TABLE units
             ADD COLUMN short_label VARCHAR(40) NULL
             COMMENT 'Abrégé affiché (site, ATAK) ; vide = automatique'
             AFTER name"
        );
        echo "  [OK] units.short_label\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] units.short_label : ' . $e->getMessage() . "\n";
    }
};
