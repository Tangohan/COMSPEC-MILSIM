<?php

declare(strict_types=1);

/**
 * Dossier médical du personnage : taille, intolérances médicamenteuses et allergies (le groupe sanguin et le poids
 * existent déjà). Affichés dans la vue « Médical » du tableur des effectifs. Idempotente, appelée par run-migrations.php.
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

    if (!$hasTable('personnel_profiles')) {
        return;
    }
    $columns = [
        'height_cm' => 'SMALLINT UNSIGNED NULL',
        'drug_intolerances' => 'VARCHAR(500) NULL',
        'allergies' => 'VARCHAR(500) NULL',
    ];
    foreach ($columns as $col => $def) {
        if ($hasCol('personnel_profiles', $col)) {
            continue;
        }
        $pdo->exec("ALTER TABLE personnel_profiles ADD COLUMN {$col} {$def}");
        echo "  [OK] personnel_profiles.{$col} ajoutée\n";
    }
};
