<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOStatement;

/**
 * Base sqlite en mémoire qui compte les requêtes qu'elle reçoit (prepare, query, exec) : pour borner le coût d'une page.
 * FIELD() et DATABASE() de MySQL y sont définis, et information_schema (TABLES, COLUMNS) y est une base attachée que
 * syncInformationSchema() remplit depuis le schéma réel : les dépôts qui sondent une table ou une colonne répondent comme en production.
 */
final class CountingSqlitePdo extends PDO
{
    public int $queries = 0;

    /** @var list<string> */
    public array $log = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->sqliteCreateFunction('FIELD', static function (mixed $needle, mixed ...$values): int {
            $pos = array_search($needle, $values, false);

            return $pos === false ? 0 : (int) $pos + 1;
        });
        $this->sqliteCreateFunction('DATABASE', static fn (): string => 'test');
        parent::exec("ATTACH DATABASE ':memory:' AS information_schema");
        parent::exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        parent::exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
    }

    /** À rappeler après toute création de table ou de colonne. */
    public function syncInformationSchema(): void
    {
        parent::exec('DELETE FROM information_schema.TABLES');
        parent::exec('DELETE FROM information_schema.COLUMNS');
        $tables = parent::query("SELECT name FROM main.sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        $addTable = parent::prepare("INSERT INTO information_schema.TABLES VALUES ('test', ?)");
        $addColumn = parent::prepare("INSERT INTO information_schema.COLUMNS VALUES ('test', ?, ?)");
        foreach ($tables as $table) {
            $addTable->execute([$table]);
            foreach (parent::query('PRAGMA main.table_info("' . $table . '")')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $addColumn->execute([$table, (string) $column['name']]);
            }
        }
    }

    public function reset(): void
    {
        $this->queries = 0;
        $this->log = [];
    }

    private function count(string $sql): void
    {
        $this->queries++;
        $this->log[] = (string) preg_replace('/\s+/', ' ', trim($sql));
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->count($query);

        return parent::prepare($query, $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->count($query);

        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        $this->count($statement);

        return parent::exec($statement);
    }
}
