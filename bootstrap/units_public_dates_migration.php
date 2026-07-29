<?php

declare(strict_types=1);

/**
 * Dates affichables sur la fiche publique des unités (tenants).
 * - public_founded_on : date de création
 * - public_custom_date + public_custom_date_label : date complémentaire personnalisable
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $hasTable = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable($pdo, 'units')) {
        return;
    }

    $after = 'show_on_public_page';
    foreach (['public_accent_color', 'public_open_slots', 'public_capacity', 'show_on_public_page'] as $candidate) {
        if ($hasColumn($pdo, 'units', $candidate)) {
            $after = $candidate;
            break;
        }
    }

    if (!$hasColumn($pdo, 'units', 'public_founded_on')) {
        try {
            $pdo->exec(
                "ALTER TABLE units
                 ADD COLUMN public_founded_on DATE NULL
                 COMMENT 'Date de création affichée sur la fiche publique'
                 AFTER {$after}"
            );
            echo "  [OK] units.public_founded_on\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] units.public_founded_on : ' . $e->getMessage() . "\n";
        }
    }

    $afterFounded = $hasColumn($pdo, 'units', 'public_founded_on') ? 'public_founded_on' : $after;

    if (!$hasColumn($pdo, 'units', 'public_custom_date')) {
        try {
            $pdo->exec(
                "ALTER TABLE units
                 ADD COLUMN public_custom_date DATE NULL
                 COMMENT 'Date complémentaire personnalisable (fiche publique)'
                 AFTER {$afterFounded}"
            );
            echo "  [OK] units.public_custom_date\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] units.public_custom_date : ' . $e->getMessage() . "\n";
        }
    }

    $afterCustom = $hasColumn($pdo, 'units', 'public_custom_date')
        ? 'public_custom_date'
        : $afterFounded;

    if (!$hasColumn($pdo, 'units', 'public_custom_date_label')) {
        try {
            $pdo->exec(
                "ALTER TABLE units
                 ADD COLUMN public_custom_date_label VARCHAR(80) NULL
                 COMMENT 'Libellé de la date complémentaire (ex. Mise en service)'
                 AFTER {$afterCustom}"
            );
            echo "  [OK] units.public_custom_date_label\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] units.public_custom_date_label : ' . $e->getMessage() . "\n";
        }
    }
};
