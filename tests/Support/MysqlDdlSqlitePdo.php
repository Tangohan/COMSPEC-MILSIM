<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Base SQLite en mémoire qui accepte le DDL MySQL des étapes de la file active (tests seulement).
 *
 * CREATE TABLE devient une table SQLite aux mêmes colonnes et index ; ALTER TABLE … ADD COLUMN / ADD INDEX
 * deviennent leurs équivalents SQLite et échouent comme MySQL si la colonne ou l'index existe déjà.
 * $changes compte les modifications réelles du schéma : une étape relancée doit le laisser à zéro.
 */
final class MysqlDdlSqlitePdo extends PDO
{
    public int $changes = 0;

    /** @var list<string> */
    public array $ddl = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function exec(string $statement): int|false
    {
        $sql = trim($statement);
        if (preg_match('/^SET\s+NAMES\b/i', $sql) === 1) {
            return 0;
        }
        if (preg_match('/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\((.*)\)[^)]*$/is', $sql, $m) === 1) {
            return $this->createTable($m[2], $m[1] !== '', $m[3]);
        }
        if (preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+COLUMN\s+`?(\w+)`?/i', $sql, $m) === 1) {
            return $this->change('ALTER TABLE "' . $m[1] . '" ADD COLUMN "' . $m[2] . '"');
        }
        if (preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+(?:INDEX|KEY)\s+`?(\w+)`?\s*\(([^)]*)\)$/i', $sql, $m) === 1) {
            return $this->change('CREATE INDEX "' . $m[2] . '" ON "' . $m[1] . '" (' . str_replace('`', '"', $m[3]) . ')');
        }
        if (preg_match('/^(CREATE\s+(UNIQUE\s+)?INDEX|ALTER\s+TABLE|DROP\s+)/i', $sql) === 1) {
            $this->changes++;
            $this->ddl[] = $sql;
        }

        return parent::exec($statement);
    }

    private function change(string $sqlite): int|false
    {
        $result = parent::exec($sqlite);
        $this->changes++;
        $this->ddl[] = $sqlite;

        return $result;
    }

    private function createTable(string $table, bool $ifNotExists, string $body): int|false
    {
        $exists = (bool) $this->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = " . $this->quote($table))->fetchColumn();
        if ($exists) {
            if ($ifNotExists) {
                return 0;
            }
            // Même refus que MySQL (1050) pour une table déjà présente.
            return parent::exec('CREATE TABLE "' . $table . '" (x)');
        }
        $columns = [];
        $constraints = [];
        $indexes = [];
        foreach (self::topLevelParts($body) as $part) {
            if (preg_match('/^PRIMARY\s+KEY\s*\(([^)]*)\)/i', $part, $m) === 1) {
                $constraints[] = 'PRIMARY KEY (' . str_replace('`', '"', $m[1]) . ')';
            } elseif (preg_match('/^UNIQUE\s+(?:KEY|INDEX)?\s*`?\w*`?\s*\(([^)]*)\)/i', $part, $m) === 1 || preg_match('/^UNIQUE\s*\(([^)]*)\)/i', $part, $m) === 1) {
                $constraints[] = 'UNIQUE (' . str_replace('`', '"', $m[1]) . ')';
            } elseif (preg_match('/^(?:KEY|INDEX)\s+`?(\w+)`?\s*\(([^)]*)\)/i', $part, $m) === 1) {
                $indexes[] = 'CREATE INDEX "' . $m[1] . '" ON "' . $table . '" (' . str_replace('`', '"', $m[2]) . ')';
            } elseif (preg_match('/^(CONSTRAINT|FOREIGN\s+KEY)\b/i', $part) === 1) {
                continue;
            } elseif (preg_match('/^`?(\w+)`?\s*(.*)$/s', $part, $m) === 1) {
                $definition = '"' . $m[1] . '"';
                if (preg_match('/\bAUTO_?INCREMENT\b/i', $m[2]) === 1 && preg_match('/\bPRIMARY\s+KEY\b/i', $m[2]) === 1) {
                    $definition .= ' INTEGER PRIMARY KEY AUTOINCREMENT';
                } else {
                    $definition .= preg_match('/^(TINY|SMALL|MEDIUM|BIG)?INT(EGER)?\b/i', $m[2]) === 1 ? ' INTEGER' : '';
                    $definition .= preg_match('/\bPRIMARY\s+KEY\b/i', $m[2]) === 1 ? ' PRIMARY KEY' : '';
                }
                $columns[] = $definition;
            }
        }
        $result = parent::exec('CREATE TABLE "' . $table . '" (' . implode(', ', [...$columns, ...$constraints]) . ')');
        foreach ($indexes as $index) {
            parent::exec($index);
        }
        $this->changes++;
        $this->ddl[] = 'CREATE TABLE ' . $table;

        return $result;
    }

    /** @return list<string> parties séparées par les virgules de premier niveau (hors parenthèses et chaînes) */
    private static function topLevelParts(string $body): array
    {
        $parts = [];
        $buf = '';
        $depth = 0;
        $quote = '';
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $c = $body[$i];
            if ($quote !== '') {
                $buf .= $c;
                if ($c === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $parts[] = trim($buf);
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') {
            $parts[] = trim($buf);
        }

        return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    }
}
