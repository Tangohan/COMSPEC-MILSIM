<?php

declare(strict_types=1);

namespace App\Support\Migrations;

use PDO;

/**
 * Contrôles de schéma des étapes de la file active (database/steps/registry.php).
 *
 * Chaque étape vérifie l'état réel de la base avant d'agir : information_schema sous MySQL,
 * sqlite_master / PRAGMA sous SQLite (tests). Une étape relancée ne refait donc ni ALTER ni
 * CREATE INDEX : elle constate que tout est en place et s'arrête.
 */
final class SchemaInspector
{
    public function __construct(private PDO $pdo)
    {
    }

    private function sqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    /** @param list<string> $params */
    private function exists(string $sql, array $params): bool
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $found = $st->fetchColumn() !== false;
        $st->closeCursor();

        return $found;
    }

    /** @return list<array<string, mixed>> */
    private function pragma(string $pragma, string $name): array
    {
        $st = $this->pdo->query('PRAGMA ' . $pragma . '(' . $this->pdo->quote($name) . ')');

        return $st !== false ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function tableExists(string $table): bool
    {
        if ($this->sqlite()) {
            return $this->exists("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
        }

        return $this->exists('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1', [$table]);
    }

    public function columnExists(string $table, string $column): bool
    {
        if ($this->sqlite()) {
            foreach ($this->pragma('table_info', $table) as $row) {
                if (strcasecmp((string) $row['name'], $column) === 0) {
                    return true;
                }
            }

            return false;
        }

        return $this->exists(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$table, $column]
        );
    }

    public function indexExists(string $table, string $index): bool
    {
        if ($this->sqlite()) {
            return $this->exists("SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?", [$table, $index]);
        }

        return $this->exists(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        );
    }

    /** Un index (quel que soit son nom) commence par cette colonne : une recherche sur elle est déjà indexée. */
    public function columnIsIndexed(string $table, string $column): bool
    {
        if ($this->sqlite()) {
            foreach ($this->pragma('index_list', $table) as $index) {
                foreach ($this->pragma('index_info', (string) $index['name']) as $part) {
                    if ((int) $part['seqno'] === 0 && strcasecmp((string) $part['name'], $column) === 0) {
                        return true;
                    }
                }
            }

            return false;
        }

        return $this->exists(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1 LIMIT 1',
            [$table, $column]
        );
    }

    /**
     * Ajoute la colonne si la table existe et que la colonne manque.
     *
     * @return bool true si la colonne vient d'être ajoutée
     */
    public function addColumnIfMissing(string $table, string $column, string $definition): bool
    {
        if (!$this->tableExists($table) || $this->columnExists($table, $column)) {
            return false;
        }
        $this->pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);

        return true;
    }

    /**
     * Ajoute l'index si la table et toutes ses colonnes existent et qu'aucun index de ce nom n'est posé.
     *
     * @param list<string> $columns
     * @return bool true si l'index vient d'être créé
     */
    public function addIndexIfMissing(string $table, string $index, array $columns): bool
    {
        if (!$this->tableExists($table) || $this->indexExists($table, $index)) {
            return false;
        }
        foreach ($columns as $column) {
            if (!$this->columnExists($table, $column)) {
                return false;
            }
        }
        $list = '`' . implode('`, `', $columns) . '`';
        $this->pdo->exec($this->sqlite()
            ? 'CREATE INDEX `' . $index . '` ON `' . $table . '` (' . $list . ')'
            : 'ALTER TABLE `' . $table . '` ADD INDEX `' . $index . '` (' . $list . ')');

        return true;
    }

    /**
     * Exécute un seul fichier SQL désigné explicitement (jamais de balayage de dossier).
     * Une erreur interrompt l'étape : elle reste alors en attente au journal.
     *
     * @return int nombre d'instructions exécutées
     */
    public function runSqlFile(string $path): int
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Fichier SQL absent : ' . basename($path));
        }
        if (!class_exists(\ComspecMigrationRunner::class, false)) {
            require_once dirname(__DIR__, 3) . '/bootstrap/migration_runner.php';
        }
        $count = 0;
        foreach (\ComspecMigrationRunner::splitStatements((string) file_get_contents($path)) as $statement) {
            \ComspecMigrationRunner::runStatement($this->pdo, $statement);
            $count++;
        }

        return $count;
    }
}
