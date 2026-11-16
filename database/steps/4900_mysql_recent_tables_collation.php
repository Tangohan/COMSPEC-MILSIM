<?php

declare(strict_types=1);

/**
 * Étape « Collation des tables récentes » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. MySQL / MariaDB seulement : sans effet sous SQLite.
 *  1. Crée user_community_membership_periods si elle manque (même définition que migrations/20261114000001, sans
 *     reprise de données) : l'inscription ne la crée plus à la volée, ce CREATE TABLE validait sa transaction.
 *  2. Remet en utf8mb4_unicode_ci les tables récentes listées dans TableCollationAligner::RECENT_TABLES dont la collation
 *     diffère, si elles restent petites (voir docs/dev/COLLATIONS-MYSQL.md). Une table déjà en utf8mb4_unicode_ci n'est
 *     pas touchée : relancer l'étape ne fait rien.
 *  3. Liste, sans rien modifier, les autres tables à une autre collation (tables historiques créées sans COLLATE).
 * Retour arrière : aucun (migrations/rollback/mysql_recent_tables_collation_down.sql l'explique).
 */

$collationAligner = new \App\Support\Migrations\TableCollationAligner($pdo);
if (!$collationAligner->supported()) {
    echo "  [OK] Base non MySQL : rien à faire.\n";
} else {
    $collationSchema = new \App\Support\Migrations\SchemaInspector($pdo);
    if ($collationSchema->tableExists('user_community_membership_periods')) {
        echo "  [OK] Table user_community_membership_periods déjà présente.\n";
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `user_community_membership_periods` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `user_id` int unsigned NOT NULL,
              `tenant_id` int unsigned NOT NULL,
              `joined_at` datetime NOT NULL,
              `left_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_ucmp_user_tenant` (`user_id`,`tenant_id`,`joined_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "  [OK] Table user_community_membership_periods créée.\n";
    }

    $collationResult = $collationAligner->align(\App\Support\Migrations\TableCollationAligner::RECENT_TABLES);
    echo '  [OK] Collation des tables récentes : ' . count($collationResult['converted']) . ' convertie(s), '
        . count($collationResult['already']) . ' déjà en utf8mb4_unicode_ci, ' . count($collationResult['missing']) . ' absente(s), '
        . count($collationResult['skipped']) . " laissée(s) telle(s) quelle(s).\n";

    $collationOthers = $collationAligner->otherTablesOffTarget(\App\Support\Migrations\TableCollationAligner::RECENT_TABLES);
    if ($collationOthers !== []) {
        $collationByName = [];
        foreach ($collationOthers as $collationTable => $collationName) {
            $collationByName[$collationName][] = $collationTable;
        }
        foreach ($collationByName as $collationName => $collationTables) {
            echo '  [INFO] ' . count($collationTables) . ' table(s) historique(s) en ' . $collationName . ', non modifiée(s) : '
                . implode(', ', array_slice($collationTables, 0, 25)) . (count($collationTables) > 25 ? ', …' : '') . "\n";
        }
    }
}
