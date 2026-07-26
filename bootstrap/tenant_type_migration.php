<?php

declare(strict_types=1);

/**
 * Colonne tenants.tenant_type (profil communauté : full / effectifs / atak).
 * Idempotent — appelée depuis run-migrations.php.
 *
 * Complète migrations/20260724000001_tenant_type.sql (non exécuté par le runner PHP).
 */
return static function (PDO $pdo): void {
    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $hasIndex = static function (PDO $pdo, string $table, string $indexName): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $indexName]);

        return (bool) $st->fetchColumn();
    };

    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'tenants')) {
        echo "  [ATTENTION] tenants absente — tenant_type non ajouté\n";

        return;
    }

    if (!$hasColumn($pdo, 'tenants', 'tenant_type')) {
        try {
            // AFTER slug si possible ; sinon ajout en fin de table.
            if ($hasColumn($pdo, 'tenants', 'slug')) {
                $pdo->exec(
                    "ALTER TABLE tenants
                     ADD COLUMN tenant_type VARCHAR(32) NOT NULL DEFAULT 'full'
                     COMMENT 'Profil communauté : full | effectifs | atak'
                     AFTER slug"
                );
            } else {
                $pdo->exec(
                    "ALTER TABLE tenants
                     ADD COLUMN tenant_type VARCHAR(32) NOT NULL DEFAULT 'full'
                     COMMENT 'Profil communauté : full | effectifs | atak'"
                );
            }
            echo "  [OK] tenants.tenant_type ajouté\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] tenants.tenant_type : ' . $e->getMessage() . "\n";

            return;
        }
    } else {
        echo "  [SKIP] tenants.tenant_type déjà présent\n";
    }

    if (!$hasIndex($pdo, 'tenants', 'idx_tenants_type')) {
        try {
            $pdo->exec('ALTER TABLE tenants ADD INDEX idx_tenants_type (tenant_type)');
            echo "  [OK] index idx_tenants_type\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] idx_tenants_type : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [SKIP] index idx_tenants_type déjà présent\n";
    }

    try {
        $pdo->exec("UPDATE tenants SET tenant_type = 'full' WHERE tenant_type IS NULL OR tenant_type = ''");
        echo "  [OK] tenant_type backfill (valeurs vides → full)\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] tenants.tenant_type backfill : ' . $e->getMessage() . "\n";
    }
};
