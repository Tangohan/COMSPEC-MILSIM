<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOStatement;

/**
 * Base MySQL simulée pour la logique conditionnelle des étapes (tests seulement).
 *
 * Se présente comme le pilote « mysql » : les lectures d'information_schema faites par SchemaInspector sont
 * servies par une table d'état SQLite, et les ALTER TABLE … ADD INDEX / ADD COLUMN mettent cet état à jour.
 * $ddl liste chaque modification réellement demandée.
 */
final class MysqlInformationSchemaPdo extends PDO
{
    /** @var list<string> */
    public array $ddl = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        parent::exec('CREATE TABLE _schema (kind TEXT NOT NULL, t TEXT NOT NULL, c TEXT NOT NULL DEFAULT \'\')');
    }

    public function getAttribute(int $attribute): mixed
    {
        return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : parent::getAttribute($attribute);
    }

    /** @param list<string> $columns */
    public function givenTable(string $table, array $columns): self
    {
        $this->state('table', $table);
        foreach ($columns as $column) {
            $this->state('column', $table, $column);
        }

        return $this;
    }

    /** Index existant (nom) dont la première colonne est $leadColumn. */
    public function givenIndex(string $table, string $index, string $leadColumn): self
    {
        $this->state('index', $table, $index);
        $this->state('lead', $table, $leadColumn);

        return $this;
    }

    private function state(string $kind, string $table, string $value = ''): void
    {
        parent::prepare('INSERT INTO _schema (kind, t, c) VALUES (?, ?, ?)')->execute([$kind, $table, $value]);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (stripos($query, 'information_schema.TABLES') !== false) {
            return parent::prepare("SELECT 1 FROM _schema WHERE kind = 'table' AND t = ?");
        }
        if (stripos($query, 'information_schema.COLUMNS') !== false) {
            return parent::prepare("SELECT 1 FROM _schema WHERE kind = 'column' AND t = ? AND c = ?");
        }
        if (stripos($query, 'information_schema.STATISTICS') !== false) {
            return parent::prepare(stripos($query, 'SEQ_IN_INDEX') !== false
                ? "SELECT 1 FROM _schema WHERE kind = 'lead' AND t = ? AND c = ?"
                : "SELECT 1 FROM _schema WHERE kind = 'index' AND t = ? AND c = ?");
        }

        return parent::prepare($query, $options);
    }

    public function exec(string $statement): int|false
    {
        $this->ddl[] = $statement;
        if (preg_match('/^ALTER TABLE `(\w+)` ADD INDEX `(\w+)` \(`(\w+)`/', $statement, $m) === 1) {
            $this->state('index', $m[1], $m[2]);
            $this->state('lead', $m[1], $m[3]);
        } elseif (preg_match('/^ALTER TABLE `(\w+)` ADD COLUMN `(\w+)`/', $statement, $m) === 1) {
            $this->state('column', $m[1], $m[2]);
        } else {
            throw new \LogicException('Instruction inattendue dans la base simulée : ' . $statement);
        }

        return 0;
    }
}
