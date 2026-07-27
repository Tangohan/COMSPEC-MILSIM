<?php

declare(strict_types=1);

/**
 * Vitrine publique communauté : capacité / places ouvertes par unité,
 * événements listés sur l’agenda public.
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

    if ($hasTable($pdo, 'units')) {
        if (!$hasColumn($pdo, 'units', 'public_capacity')) {
            try {
                $pdo->exec(
                    'ALTER TABLE units
                     ADD COLUMN public_capacity INT UNSIGNED NULL
                     COMMENT \'Effectif max affiché sur la vitrine\' AFTER show_on_public_page'
                );
                echo "  [OK] units.public_capacity\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] units.public_capacity : ' . $e->getMessage() . "\n";
            }
        }
        if (!$hasColumn($pdo, 'units', 'public_open_slots')) {
            try {
                $after = $hasColumn($pdo, 'units', 'public_capacity') ? 'public_capacity' : 'show_on_public_page';
                $pdo->exec(
                    "ALTER TABLE units
                     ADD COLUMN public_open_slots INT NULL
                     COMMENT 'Places ouvertes (NULL = non affiché ; -1 = ouvert sans plafond)' AFTER {$after}"
                );
                echo "  [OK] units.public_open_slots\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] units.public_open_slots : ' . $e->getMessage() . "\n";
            }
        }
        if (!$hasColumn($pdo, 'units', 'public_accent_color')) {
            try {
                $after = $hasColumn($pdo, 'units', 'public_open_slots')
                    ? 'public_open_slots'
                    : ($hasColumn($pdo, 'units', 'public_capacity') ? 'public_capacity' : 'show_on_public_page');
                $pdo->exec(
                    "ALTER TABLE units
                     ADD COLUMN public_accent_color VARCHAR(7) NULL
                     COMMENT 'Couleur de bandeau section (#RRGGBB)' AFTER {$after}"
                );
                echo "  [OK] units.public_accent_color\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] units.public_accent_color : ' . $e->getMessage() . "\n";
            }
        }
    }

    if ($hasTable($pdo, 'community_events') && !$hasColumn($pdo, 'community_events', 'show_on_public_page')) {
        try {
            $pdo->exec(
                'ALTER TABLE community_events
                 ADD COLUMN show_on_public_page TINYINT(1) NOT NULL DEFAULT 0
                 COMMENT \'Visible sur l’agenda de la vitrine publique\' AFTER cancelled_reason'
            );
            echo "  [OK] community_events.show_on_public_page\n";
        } catch (Throwable $e) {
            // Fallback if cancelled_reason absent
            try {
                $pdo->exec(
                    'ALTER TABLE community_events
                     ADD COLUMN show_on_public_page TINYINT(1) NOT NULL DEFAULT 0'
                );
                echo "  [OK] community_events.show_on_public_page\n";
            } catch (Throwable $e2) {
                echo '  [ATTENTION] community_events.show_on_public_page : ' . $e2->getMessage() . "\n";
            }
        }
    }
};
