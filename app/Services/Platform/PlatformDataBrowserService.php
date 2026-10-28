<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Database;
use App\Support\PlatformStorageCatalog;
use PDO;
use Throwable;

/**
 * Explorateur de la base pour l’administration du site : lister les tables, chercher et lire des lignes,
 * modifier ou supprimer une ligne, vider une table.
 *
 * Garde-fous :
 *  - un nom de table ou de colonne n’est accepté que s’il existe dans information_schema (jamais interpolé brut) ;
 *  - colonnes sensibles (mots de passe, jetons, secrets…) masquées à l’affichage, ni filtrables ni modifiables ;
 *  - tables en lecture seule (journal d’audit, journal des migrations) ;
 *  - tables protégées (comptes, communautés, droits…) : lignes modifiables, mais jamais vidées en bloc ;
 *  - modification et suppression d’une ligne uniquement par clé primaire complète, une ligne à la fois.
 */
final class PlatformDataBrowserService
{
    public const PER_PAGE = 50;

    public const EXPORT_LIMIT = 5000;

    /** Au-delà, le total exact d’une table sans filtre n’est pas recompté (estimation information_schema). */
    private const EXACT_COUNT_MAX = 200000;

    /** Opérateurs de filtre proposés (clé => libellé). */
    public const OPERATORS = [
        'contains' => 'contient',
        'eq' => 'égal à',
        'neq' => 'différent de',
        'starts' => 'commence par',
        'gt' => 'supérieur à',
        'lt' => 'inférieur à',
        'null' => 'est vide (NULL)',
        'notnull' => 'n’est pas vide',
    ];

    /** @var array<string, list<array<string, mixed>>> */
    private array $columnsCache = [];

    /** @var array<string, bool>|null */
    private ?array $tableNames = null;

    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::getPdo();
    }

    /** Tables jamais modifiables (preuves et suivi technique). */
    public static function readOnlyTables(): array
    {
        return ['audit_logs', 'comspec_schema_migrations', 'athena_once_migrations'];
    }

    public static function isReadOnly(string $table): bool
    {
        return in_array($table, self::readOnlyTables(), true);
    }

    /** Tables qu’on ne vide jamais en bloc (comptes, communautés, droits, preuves). */
    public static function isProtected(string $table): bool
    {
        return self::isReadOnly($table) || in_array($table, PlatformStorageCatalog::protectedTables(), true);
    }

    /** Colonne dont la valeur ne doit ni s’afficher ni se modifier ici. */
    public static function isSensitiveColumn(string $column): bool
    {
        return preg_match('/(pass(word|wd)?|secret|token|api_?key|private_?key|totp|otp_|mfa|2fa|hash|salt|session_?id|refresh|signing|cipher|encrypted)/i', $column) === 1;
    }

    /**
     * @return list<array{name: string, rows: int, bytes: int, engine: string, comment: string, protected: bool, read_only: bool}>
     */
    public function listTables(string $search = ''): array
    {
        $search = trim($search);
        $sql = 'SELECT TABLE_NAME AS name, ENGINE AS engine, COALESCE(TABLE_ROWS, 0) AS row_est,
                       COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS bytes, COALESCE(TABLE_COMMENT, \'\') AS comment
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'';
        $args = [];
        if ($search !== '') {
            $sql .= ' AND TABLE_NAME LIKE ?';
            $args[] = '%' . self::likeEscape($search) . '%';
        }
        $sql .= ' ORDER BY TABLE_NAME ASC';
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $name = (string) ($r['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'rows' => (int) ($r['row_est'] ?? 0),
                'bytes' => (int) ($r['bytes'] ?? 0),
                'engine' => (string) ($r['engine'] ?? ''),
                'comment' => (string) ($r['comment'] ?? ''),
                'protected' => self::isProtected($name),
                'read_only' => self::isReadOnly($name),
            ];
        }

        return $out;
    }

    /** Nom de table validé contre information_schema (sinon null). */
    public function resolveTable(string $table): ?string
    {
        if ($this->tableNames === null) {
            $this->tableNames = [];
            try {
                $st = $this->pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
                foreach (($st ? $st->fetchAll(PDO::FETCH_COLUMN) : []) as $n) {
                    $this->tableNames[(string) $n] = true;
                }
            } catch (Throwable) {
                $this->tableNames = [];
            }
        }

        return isset($this->tableNames[$table]) ? $table : null;
    }

    /**
     * @return list<array{name: string, type: string, data_type: string, nullable: bool, primary: bool, auto: bool, default: ?string, max_length: ?int, sensitive: bool, comment: string}>
     */
    public function columns(string $table): array
    {
        if (isset($this->columnsCache[$table])) {
            return $this->columnsCache[$table];
        }
        $st = $this->pdo->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH, COLUMN_COMMENT
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
        );
        $st->execute([$table]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $c) {
            $name = (string) $c['COLUMN_NAME'];
            $out[] = [
                'name' => $name,
                'type' => (string) $c['COLUMN_TYPE'],
                'data_type' => strtolower((string) $c['DATA_TYPE']),
                'nullable' => strtoupper((string) $c['IS_NULLABLE']) === 'YES',
                'primary' => strtoupper((string) $c['COLUMN_KEY']) === 'PRI',
                'auto' => str_contains(strtolower((string) $c['EXTRA']), 'auto_increment'),
                'default' => $c['COLUMN_DEFAULT'] !== null ? (string) $c['COLUMN_DEFAULT'] : null,
                'max_length' => $c['CHARACTER_MAXIMUM_LENGTH'] !== null ? (int) $c['CHARACTER_MAXIMUM_LENGTH'] : null,
                'sensitive' => self::isSensitiveColumn($name),
                'comment' => (string) ($c['COLUMN_COMMENT'] ?? ''),
            ];
        }

        return $this->columnsCache[$table] = $out;
    }

    /** @return list<string> */
    public function primaryKey(string $table): array
    {
        return array_values(array_map(
            static fn (array $c): string => $c['name'],
            array_filter($this->columns($table), static fn (array $c): bool => $c['primary'])
        ));
    }

    /**
     * Lignes d’une table, avec recherche plein texte, filtre sur une colonne, tri et pagination.
     *
     * @param array{q?: string, col?: string, op?: string, val?: string, sort?: string, dir?: string, page?: int} $opts
     * @return array{rows: list<array<string, mixed>>, total: int, total_exact: bool, page: int, pages: int, sort: string, dir: string, error: ?string}
     */
    public function browse(string $table, array $opts, int $perPage = self::PER_PAGE): array
    {
        $cols = $this->columns($table);
        $names = array_column($cols, 'name');
        [$where, $args] = $this->whereClause($cols, $opts);

        $sort = (string) ($opts['sort'] ?? '');
        if (!in_array($sort, $names, true)) {
            $pk = $this->primaryKey($table);
            $sort = $pk[0] ?? ($names[0] ?? '');
        }
        $dir = strtolower((string) ($opts['dir'] ?? '')) === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) ($opts['page'] ?? 1));
        $perPage = max(1, min(self::EXPORT_LIMIT, $perPage));
        $q = self::quote($table);

        $total = 0;
        $exact = true;
        try {
            if ($where === '') {
                $est = 0;
                foreach ($this->listTables($table) as $t) {
                    if ($t['name'] === $table) {
                        $est = $t['rows'];
                    }
                }
                if ($est > self::EXACT_COUNT_MAX) {
                    $total = $est;
                    $exact = false;
                }
            }
            if ($exact) {
                $st = $this->pdo->prepare('SELECT /*+ MAX_EXECUTION_TIME(8000) */ COUNT(*) FROM ' . $q . $where);
                $st->execute($args);
                $total = (int) $st->fetchColumn();
            }
            $pages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $pages);
            $order = $sort !== '' ? ' ORDER BY ' . self::quote($sort) . ' ' . strtoupper($dir) : '';
            $st = $this->pdo->prepare(
                'SELECT /*+ MAX_EXECUTION_TIME(8000) */ * FROM ' . $q . $where . $order
                . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
            );
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return ['rows' => [], 'total' => 0, 'total_exact' => true, 'page' => 1, 'pages' => 1, 'sort' => $sort, 'dir' => $dir, 'error' => self::friendlyError($e)];
        }

        return ['rows' => $rows, 'total' => $total, 'total_exact' => $exact, 'page' => $page, 'pages' => $pages, 'sort' => $sort, 'dir' => $dir, 'error' => null];
    }

    /**
     * Une ligne par clé primaire complète, ou null.
     *
     * @param array<string, mixed> $pkValues
     * @return array<string, mixed>|null
     */
    public function findRow(string $table, array $pkValues): ?array
    {
        [$where, $args] = $this->pkWhere($table, $pkValues);
        if ($where === null) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM ' . self::quote($table) . $where . ' LIMIT 2');
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return count($rows) === 1 ? $rows[0] : null;
    }

    /**
     * Modifie une ligne. $values : colonne => valeur saisie ; $nulls : colonnes à passer à NULL.
     * Ignore la clé primaire, les colonnes sensibles et les valeurs inchangées.
     *
     * @param array<string, mixed> $pkValues
     * @param array<string, mixed> $values
     * @param list<string> $nulls
     * @return array{ok: bool, error?: string, changes?: array<string, array{0: mixed, 1: mixed}>}
     */
    public function updateRow(string $table, array $pkValues, array $values, array $nulls = []): array
    {
        if (self::isReadOnly($table)) {
            return ['ok' => false, 'error' => 'Cette table est en lecture seule.'];
        }
        $row = $this->findRow($table, $pkValues);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Ligne introuvable (elle a peut-être été supprimée entre-temps).'];
        }
        $sets = [];
        $args = [];
        $changes = [];
        foreach ($this->columns($table) as $c) {
            $name = $c['name'];
            if ($c['primary'] || $c['sensitive'] || !array_key_exists($name, $row) || self::isBinaryColumn($c, $row[$name])) {
                continue;
            }
            $toNull = in_array($name, $nulls, true) && $c['nullable'];
            if (!$toNull && !array_key_exists($name, $values)) {
                continue;
            }
            $new = $toNull ? null : (string) $values[$name];
            $old = $row[$name];
            // Les navigateurs renvoient les retours à la ligne en \r\n : ne pas y voir une modification.
            if ($new !== null && !str_contains((string) $old, "\r")) {
                $new = str_replace("\r\n", "\n", $new);
            }
            if ($new === ($old === null ? null : (string) $old)) {
                continue;
            }
            if ($new === '' && !$c['nullable'] && in_array($c['data_type'], ['int', 'bigint', 'smallint', 'tinyint', 'mediumint', 'decimal', 'float', 'double', 'date', 'datetime', 'timestamp', 'time'], true)) {
                return ['ok' => false, 'error' => 'La colonne « ' . $name . ' » ne peut pas être vide.'];
            }
            $sets[] = self::quote($name) . ' = ?';
            $args[] = $new === '' && $c['nullable'] && !in_array($c['data_type'], ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'json', 'enum', 'set'], true) ? null : $new;
            $changes[$name] = [$old, $new];
        }
        if ($sets === []) {
            return ['ok' => true, 'changes' => []];
        }
        [$where, $wArgs] = $this->pkWhere($table, $pkValues);
        try {
            $st = $this->pdo->prepare('UPDATE ' . self::quote($table) . ' SET ' . implode(', ', $sets) . $where . ' LIMIT 1');
            $st->execute(array_merge($args, $wArgs));
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => self::friendlyError($e)];
        }

        return ['ok' => true, 'changes' => $changes];
    }

    /**
     * @param array<string, mixed> $pkValues
     * @return array{ok: bool, error?: string, row?: array<string, mixed>}
     */
    public function deleteRow(string $table, array $pkValues): array
    {
        if (self::isReadOnly($table)) {
            return ['ok' => false, 'error' => 'Cette table est en lecture seule.'];
        }
        $row = $this->findRow($table, $pkValues);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Ligne introuvable.'];
        }
        [$where, $args] = $this->pkWhere($table, $pkValues);
        try {
            $st = $this->pdo->prepare('DELETE FROM ' . self::quote($table) . $where . ' LIMIT 1');
            $st->execute($args);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => self::friendlyError($e)];
        }

        return ['ok' => true, 'row' => $row];
    }

    /**
     * Vide une table (TRUNCATE, sinon DELETE). Refusé pour les tables protégées.
     *
     * @return array{ok: bool, error?: string, rows_before?: int}
     */
    public function emptyTable(string $table): array
    {
        if (self::isProtected($table)) {
            return ['ok' => false, 'error' => 'Cette table est protégée : elle ne peut pas être vidée d’ici.'];
        }
        $q = self::quote($table);
        $before = 0;
        try {
            $before = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $q)->fetchColumn();
        } catch (Throwable) {
        }
        try {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            try {
                $this->pdo->exec('TRUNCATE TABLE ' . $q);
            } catch (Throwable) {
                $this->pdo->exec('DELETE FROM ' . $q);
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => self::friendlyError($e)];
        } finally {
            try {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable) {
            }
        }

        return ['ok' => true, 'rows_before' => $before];
    }

    /** @param array<string, mixed> $column */
    public static function isBinaryColumn(array $column, mixed $value = null): bool
    {
        return in_array($column['data_type'] ?? '', ['blob', 'mediumblob', 'longblob', 'tinyblob', 'binary', 'varbinary', 'geometry', 'point'], true)
            || (is_string($value) && !mb_check_encoding($value, 'UTF-8'));
    }

    /** Valeur affichable d’une cellule (sensible masquée, texte long tronqué si $max > 0). */
    public static function displayValue(mixed $value, bool $sensitive, int $max = 120): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if ($sensitive) {
            return $value === '' ? '' : '•••••• (masqué)';
        }
        $s = (string) $value;
        if (!mb_check_encoding($s, 'UTF-8')) {
            return '(binaire, ' . strlen($s) . ' octets)';
        }
        if ($max > 0 && mb_strlen($s) > $max) {
            return mb_substr($s, 0, $max) . '…';
        }

        return $s;
    }

    /**
     * Clé primaire d’une ligne, prête pour une URL (pk[col]=val).
     *
     * @param array<string, mixed> $row
     * @return array<string, string>|null
     */
    public function pkOf(string $table, array $row): ?array
    {
        $pk = $this->primaryKey($table);
        if ($pk === []) {
            return null;
        }
        $out = [];
        foreach ($pk as $col) {
            if (!array_key_exists($col, $row) || $row[$col] === null) {
                return null;
            }
            $out[$col] = (string) $row[$col];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $cols
     * @param array<string, mixed> $opts
     * @return array{0: string, 1: list<mixed>}
     */
    private function whereClause(array $cols, array $opts): array
    {
        $parts = [];
        $args = [];
        $q = trim((string) ($opts['q'] ?? ''));
        if ($q !== '') {
            $or = [];
            foreach ($cols as $c) {
                if ($c['sensitive'] || in_array($c['data_type'], ['blob', 'longblob', 'mediumblob', 'tinyblob', 'binary', 'varbinary', 'geometry', 'point'], true)) {
                    continue;
                }
                $or[] = 'CAST(' . self::quote($c['name']) . ' AS CHAR) LIKE ?';
                $args[] = '%' . self::likeEscape($q) . '%';
                if (count($or) >= 40) {
                    break;
                }
            }
            if ($or !== []) {
                $parts[] = '(' . implode(' OR ', $or) . ')';
            }
        }
        $col = (string) ($opts['col'] ?? '');
        $op = (string) ($opts['op'] ?? 'contains');
        $val = (string) ($opts['val'] ?? '');
        $colDef = null;
        foreach ($cols as $c) {
            if ($c['name'] === $col && !$c['sensitive']) {
                $colDef = $c;
            }
        }
        if ($colDef !== null && isset(self::OPERATORS[$op]) && ($val !== '' || in_array($op, ['null', 'notnull'], true))) {
            $qc = self::quote($col);
            switch ($op) {
                case 'eq': $parts[] = $qc . ' = ?'; $args[] = $val; break;
                case 'neq': $parts[] = '(' . $qc . ' <> ? OR ' . $qc . ' IS NULL)'; $args[] = $val; break;
                case 'starts': $parts[] = 'CAST(' . $qc . ' AS CHAR) LIKE ?'; $args[] = self::likeEscape($val) . '%'; break;
                case 'gt': $parts[] = $qc . ' > ?'; $args[] = $val; break;
                case 'lt': $parts[] = $qc . ' < ?'; $args[] = $val; break;
                case 'null': $parts[] = $qc . ' IS NULL'; break;
                case 'notnull': $parts[] = $qc . ' IS NOT NULL'; break;
                default: $parts[] = 'CAST(' . $qc . ' AS CHAR) LIKE ?'; $args[] = '%' . self::likeEscape($val) . '%';
            }
        }

        return [$parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts), $args];
    }

    /**
     * @param array<string, mixed> $pkValues
     * @return array{0: ?string, 1: list<mixed>}
     */
    private function pkWhere(string $table, array $pkValues): array
    {
        $pk = $this->primaryKey($table);
        if ($pk === []) {
            return [null, []];
        }
        $parts = [];
        $args = [];
        foreach ($pk as $col) {
            if (!array_key_exists($col, $pkValues) || !is_scalar($pkValues[$col])) {
                return [null, []];
            }
            $parts[] = self::quote($col) . ' = ?';
            $args[] = (string) $pkValues[$col];
        }

        return [' WHERE ' . implode(' AND ', $parts), $args];
    }

    private static function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function likeEscape(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    private static function friendlyError(Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, '1451') || str_contains($msg, 'foreign key constraint fails')) {
            return 'Opération refusée : d’autres données dépendent de cette ligne (clé étrangère).';
        }
        if (str_contains($msg, '1062') || str_contains($msg, 'Duplicate entry')) {
            return 'Opération refusée : une autre ligne a déjà cette valeur (doublon sur une clé unique).';
        }
        if (str_contains($msg, '3024') || str_contains($msg, 'maximum statement execution time')) {
            return 'Recherche trop longue sur une table volumineuse : précisez un filtre sur une colonne indexée.';
        }
        if (preg_match('/SQLSTATE\[\w+\]:? (.*)$/s', $msg, $m) === 1) {
            return 'Erreur de la base : ' . mb_substr(trim($m[1]), 0, 240);
        }

        return 'Erreur de la base.';
    }
}
