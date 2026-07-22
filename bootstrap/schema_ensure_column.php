<?php

declare(strict_types=1);

/**
 * Utilitaire générique : combler une colonne absente sur une table existante.
 *
 * CREATE TABLE IF NOT EXISTS ne met jamais à jour une table déjà présente ;
 * cet helper vérifie INFORMATION_SCHEMA puis exécute ALTER TABLE … ADD COLUMN.
 */

if (!function_exists('schema_column_exists')) {
    function schema_column_exists(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    }
}

if (!function_exists('schema_table_exists')) {
    function schema_table_exists(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }
}

if (!function_exists('schema_ensure_column')) {
    /**
     * Ajoute la colonne si absente.
     *
     * @param string $definition DDL de colonne (sans « ADD COLUMN »), ex. :
     *                           "`military_id` varchar(32) DEFAULT NULL AFTER `call_sign`"
     *
     * @return bool true si la colonne vient d’être ajoutée, false si déjà présente ou table absente
     */
    function schema_ensure_column(PDO $pdo, string $table, string $column, string $definition): bool
    {
        if (!schema_table_exists($pdo, $table)) {
            echo "  [ATTENTION] Table absente — skip colonne {$table}.{$column}\n";

            return false;
        }

        if (schema_column_exists($pdo, $table, $column)) {
            return false;
        }

        $definition = trim($definition);
        if ($definition === '') {
            echo "  [ATTENTION] Définition vide pour {$table}.{$column}\n";

            return false;
        }

        try {
            $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN ' . $definition);
            echo "  [COMPLÉTÉ] Colonne ajoutée : {$table}.{$column}\n";

            return true;
        } catch (Throwable $e) {
            echo "  [ATTENTION] Impossible d’ajouter {$table}.{$column} : " . $e->getMessage() . "\n";

            return false;
        }
    }
}

if (!function_exists('schema_ensure_columns')) {
    /**
     * @param array<string, string> $columns map column_name => DDL definition (sans ADD COLUMN)
     *
     * @return int nombre de colonnes réellement ajoutées
     */
    function schema_ensure_columns(PDO $pdo, string $table, array $columns): int
    {
        $added = 0;
        foreach ($columns as $column => $definition) {
            if (schema_ensure_column($pdo, $table, (string) $column, (string) $definition)) {
                $added++;
            }
        }

        return $added;
    }
}
