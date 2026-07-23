<?php

declare(strict_types=1);

/**
 * Catégorie de poste (opérationnel / état-major / administratif)
 * + pack d’habilitations optionnel associé au poste.
 * Idempotent.
 */
function run_positions_admin_category_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('positions')) {
        return;
    }

    if (!$hasColumn('positions', 'category')) {
        $pdo->exec(
            "ALTER TABLE positions
             ADD COLUMN category ENUM('operational','staff','administrative') NOT NULL DEFAULT 'operational'
             AFTER description"
        );
        echo "  positions.category : colonne ajoutée.\n";
    }

    if (!$hasColumn('positions', 'default_role_set_id')) {
        $pdo->exec(
            'ALTER TABLE positions
             ADD COLUMN default_role_set_id INT UNSIGNED NULL DEFAULT NULL
             AFTER is_temporary'
        );
        echo "  positions.default_role_set_id : colonne ajoutée.\n";
    }

    if ($hasTable('role_sets') && $hasColumn('positions', 'default_role_set_id')) {
        $fkCheck = $pdo->query(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'positions'
               AND CONSTRAINT_NAME = 'fk_positions_default_role_set'
             LIMIT 1"
        );
        if ($fkCheck && !$fkCheck->fetchColumn()) {
            try {
                $pdo->exec(
                    'ALTER TABLE positions
                     ADD CONSTRAINT fk_positions_default_role_set
                     FOREIGN KEY (default_role_set_id) REFERENCES role_sets (id)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
                echo "  positions.default_role_set_id : clé étrangère ajoutée.\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] fk_positions_default_role_set : ' . $e->getMessage() . "\n";
            }
        }
    }
}
