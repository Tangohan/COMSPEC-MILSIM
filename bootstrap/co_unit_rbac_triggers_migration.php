<?php

declare(strict_types=1);

/**
 * Colonnes co_unit_id / co_unit_scope : version physique + triggers (compatible MariaDB / hébergeurs
 * où GENERATED … IFNULL(org_unit_id,0) provoque l’erreur 1901).
 * Idempotent : relancer recrée les triggers et réaligne les valeurs.
 */
function run_co_unit_rbac_triggers_migration(PDO $pdo): void
{
    echo "RBAC co_unit (triggers + colonnes physiques, compat MariaDB)...\n";

    co_unit_rbac_ensure_table($pdo, 'tenant_user_roles', 'co_unit_id', 'tur_co_unit_bi', 'tur_co_unit_bu');
    co_unit_rbac_ensure_table($pdo, 'user_permission_overrides', 'co_unit_scope', 'upo_co_scope_bi', 'upo_co_scope_bu');

    echo "RBAC co_unit : OK.\n";
}

function co_unit_rbac_ensure_table(
    PDO $pdo,
    string $table,
    string $denormCol,
    string $trigInsert,
    string $trigUpdate
): void {
    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table) . ' LIMIT 1');
    if (!$chk || !$chk->fetch()) {
        echo "  [SKIP] Table {$table} absente.\n";

        return;
    }

    co_unit_rbac_ensure_physical_column($pdo, $table, $denormCol);

    try {
        $pdo->exec("UPDATE `{$table}` SET `{$denormCol}` = IFNULL(org_unit_id, 0)");
    } catch (PDOException $e) {
        echo '  [ATTENTION] UPDATE ' . $table . ' : ' . $e->getMessage() . "\n";
    }

    foreach ([$trigInsert, $trigUpdate] as $tn) {
        try {
            $pdo->exec('DROP TRIGGER IF EXISTS `' . str_replace('`', '``', $tn) . '`');
        } catch (PDOException) {
        }
    }

    $setExpr = 'NEW.' . $denormCol . ' = IFNULL(NEW.org_unit_id, 0)';
    try {
        $pdo->exec(
            'CREATE TRIGGER `' . $trigInsert . '` BEFORE INSERT ON `' . $table . '` FOR EACH ROW SET ' . $setExpr
        );
    } catch (PDOException $e) {
        echo '  [ATTENTION] TRIGGER ' . $trigInsert . ' : ' . $e->getMessage() . "\n";

        return;
    }
    try {
        $pdo->exec(
            'CREATE TRIGGER `' . $trigUpdate . '` BEFORE UPDATE ON `' . $table . '` FOR EACH ROW SET ' . $setExpr
        );
    } catch (PDOException $e) {
        echo '  [ATTENTION] TRIGGER ' . $trigUpdate . ' : ' . $e->getMessage() . "\n";
    }
}

function co_unit_rbac_ensure_physical_column(PDO $pdo, string $table, string $col): void
{
    $st = $pdo->prepare(
        'SELECT COLUMN_TYPE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` bigint unsigned NOT NULL DEFAULT 0");
            echo "  {$table}.{$col} : colonne ajoutée.\n";
        } catch (PDOException $e) {
            echo '  [ATTENTION] ADD ' . $table . '.' . $col . ' : ' . $e->getMessage() . "\n";
        }

        return;
    }
    $extra = (string) ($row['EXTRA'] ?? '');
    if (stripos($extra, 'GENERATED') === false) {
        return;
    }
    try {
        $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN `{$col}` bigint unsigned NOT NULL DEFAULT 0");
        echo "  {$table}.{$col} : colonne générée convertie en physique.\n";
    } catch (PDOException $e) {
        echo '  [ATTENTION] MODIFY ' . $table . '.' . $col . ' : ' . $e->getMessage() . "\n";
    }
}
