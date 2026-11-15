<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use PDO;

/**
 * Base sqlite en mémoire du laboratoire numérique : les vraies migrations (bootstrap/atak_sse_digital_lab_migration.php et
 * atak_sse_domex_packets_migration.php, écrites pour MySQL) rejouées sur sqlite par un PDO qui réécrit leurs
 * CREATE TABLE / ALTER TABLE (clés, moteur, UNSIGNED, AFTER) et répond aux contrôles information_schema par sqlite_master et pragma_table_info.
 * Branchée sur Database le temps d'un test.
 */
final class SseDigitalLabSqlite
{
    private static ?PDO $saved = null;

    public static function open(): PDO
    {
        $pdo = new class ('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]) extends PDO {
            public function exec(string $statement): int|false
            {
                return parent::exec(SseDigitalLabSqlite::toSqlite($statement));
            }

            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                if (str_contains($query, 'information_schema.COLUMNS')) {
                    return parent::prepare('SELECT 1 FROM pragma_table_info(?) WHERE name = ?');
                }
                if (str_contains($query, 'information_schema.TABLES')) {
                    return parent::prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
                }

                return parent::prepare($query, $options);
            }
        };
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'Alpha', 'alpha'), (2, 'Bravo', 'bravo')");
        $root = dirname(__DIR__, 2);
        // Les migrations écrivent leur journal : rien à afficher dans un test.
        ob_start();
        try {
            foreach (['atak_sse_digital_lab_migration.php', 'atak_sse_domex_packets_migration.php'] as $file) {
                (require $root . '/bootstrap/' . $file)($pdo);
            }
        } finally {
            ob_end_clean();
        }

        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $current = $prop->getValue();
        self::$saved = $current instanceof PDO ? $current : null;
        $prop->setValue(null, $pdo);

        return $pdo;
    }

    public static function close(): void
    {
        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $prop->setValue(null, self::$saved);
        self::$saved = null;
    }

    /** Réécriture d'une instruction MySQL des migrations du laboratoire en sqlite. */
    public static function toSqlite(string $sql): string
    {
        if (preg_match('/^\s*ALTER TABLE\s+`?(\w+)`?\s+ADD COLUMN\s+(.+)$/is', $sql, $m) === 1) {
            return 'ALTER TABLE ' . $m[1] . ' ADD COLUMN ' . trim((string) preg_replace('/\s+AFTER\s+`?\w+`?\s*$/i', '', $m[2]));
        }
        if (preg_match('/^\s*CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?\s*\((.*)\)\s*ENGINE=.*$/is', $sql, $m) !== 1) {
            return $sql;
        }
        $cols = [];
        foreach (preg_split('/\R/', $m[2]) ?: [] as $line) {
            $line = trim($line);
            $line = rtrim($line, ',');
            if ($line === '' || preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY|CONSTRAINT)\b/i', $line) === 1) {
                continue;
            }
            if (preg_match('/^`?id`?\s+INT\b.*AUTO_INCREMENT/i', $line) === 1) {
                $cols[] = 'id INTEGER PRIMARY KEY AUTOINCREMENT';
                continue;
            }
            $line = (string) preg_replace('/\s+UNSIGNED\b/i', '', $line);
            $line = (string) preg_replace('/\s+ON UPDATE CURRENT_TIMESTAMP/i', '', $line);
            $line = (string) preg_replace('/\s+COMMENT\s+\'[^\']*\'/i', '', $line);
            $cols[] = $line;
        }

        return 'CREATE TABLE IF NOT EXISTS ' . $m[1] . ' (' . implode(', ', $cols) . ')';
    }
}
