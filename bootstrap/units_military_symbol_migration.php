<?php

declare(strict_types=1);

/**
 * Symbole militaire des unités de l’ORBAT : code MIL-STD-2525 (SIDC) et échelon, NULL = déduits du nom et du type
 * (App\Support\UnitMilitarySymbol). Idempotente, appelée par run-migrations.php (étape units_military_symbol).
 */
return function (PDO $pdo): void {
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $hasCol = static function (string $table, string $col) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $st->execute([$table, $col]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('units')) {
        return;
    }
    $columns = [
        'sidc' => "VARCHAR(30) NULL DEFAULT NULL COMMENT 'Symbole MIL-STD-2525 ; NULL = déduit du nom'",
        'echelon' => "VARCHAR(20) NULL DEFAULT NULL COMMENT 'Échelon (equipe, groupe, section…) ; NULL = déduit'",
    ];
    foreach ($columns as $col => $def) {
        if ($hasCol('units', $col)) {
            continue;
        }
        $pdo->exec("ALTER TABLE units ADD COLUMN {$col} {$def}");
        echo "  [OK] units.{$col} ajoutée\n";
    }
};
