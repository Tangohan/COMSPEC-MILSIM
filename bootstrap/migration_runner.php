<?php

declare(strict_types=1);

/**
 * Moteur du pipeline de migrations (run-migrations.php / setup-database.php).
 *
 * - Journal en base `comspec_schema_migrations` : chaque fichier migrations/*.sql est appliqué
 *   une fois, puis ignoré tant que son contenu (SHA-256) ne change pas. Un fichier en échec est
 *   retenté au passage suivant ; un fichier modifié est rejoué.
 * - Découpage SQL fiable : chaînes, identifiants, commentaires, DELIMITER, blocs BEGIN…END.
 * - Les erreurs « déjà présent » (colonne, index, table, contrainte existants) ne sont pas des
 *   échecs : elles prouvent que l’instruction a déjà été appliquée.
 * - Chaque étape PHP est isolée : une exception est notée et le pipeline continue.
 * - Rapport final : appliqués / déjà à jour / en échec / exclus, et code de sortie 1 en cas d’échec.
 */
final class ComspecMigrationRunner
{
    public const LEDGER_TABLE = 'comspec_schema_migrations';

    /**
     * Fichiers jamais rejoués tels quels : leur étape PHP les applique déjà, en les adaptant.
     * Les rejouer bruts recréerait des objets volontairement écartés (triggers, vues, tables renommées).
     *
     * @var array<string, string>
     */
    public const OWNED_SQL_FILES = [
        'lms_training.sql' => 'bootstrap/lms_training_base_migration.php (RENAME legacy conditionnels)',
        'grade_referentiel.sql' => 'run-migrations.php (grades_referentiel est ensuite renommée en grades)',
        '2026_07_24_001_atak_tactical_reports.sql' => 'bootstrap/atak_modules_schema_migration.php',
        '2026_07_24_002_atak_poi_intelligence.sql' => 'bootstrap/atak_modules_schema_migration.php',
        '2026_07_24_003_atak_tactical_zones.sql' => 'bootstrap/atak_modules_schema_migration.php',
        '2026_07_24_004_atak_medevac_extended.sql' => 'bootstrap/atak_modules_schema_migration.php',
        '2026_07_24_005_atak_qrf_system.sql' => 'bootstrap/atak_modules_schema_migration.php',
        '2026_07_24_006_atak_vehicle_tracking.sql' => 'bootstrap/atak_modules_schema_migration.php',
        '2026_07_24_007_atak_intelligence_enhancements.sql' => 'bootstrap/atak_modules_schema_migration.php',
        '2026_07_27_001_atak_waypoints.sql' => 'bootstrap/atak_modules_schema_migration.php',
    ];

    /**
     * Codes MySQL/MariaDB signifiant « déjà appliqué » lors d’un rejeu.
     *
     * @var array<int, string>
     */
    private const ALREADY_APPLIED_CODES = [
        1050 => 'table déjà présente',
        1060 => 'colonne déjà présente',
        1061 => 'index déjà présent',
        1062 => 'ligne déjà présente',
        1068 => 'clé primaire déjà définie',
        1091 => 'élément déjà supprimé',
        1304 => 'routine déjà présente',
        1359 => 'trigger déjà présent',
        1826 => 'contrainte déjà présente',
    ];

    /** @var callable():PDO */
    private $pdo;

    /** @var callable():void */
    private $flush;

    /** @var callable():void */
    private $ensurePdo;

    private bool $ledgerReady = false;

    private ?string $currentStep = null;

    private bool $finished = false;

    private float $startedAt;

    /** @var list<array{name:string, status:string, detail:string}> */
    private array $phpFailures = [];

    private int $phpSteps = 0;

    /** @var array<string, list<array{name:string, detail:string}>> */
    private array $sql = ['applied' => [], 'up_to_date' => [], 'failed' => [], 'excluded' => []];

    /** @var list<string> */
    private array $replay = [];

    private bool $replayAll = false;

    /**
     * @param callable():PDO $pdo        renvoie la connexion courante (elle peut être recréée)
     * @param callable():void $flush
     * @param callable():void $ensurePdo reconnecte si la session MySQL est morte
     * @param list<string> $argv
     */
    public function __construct(callable $pdo, callable $flush, callable $ensurePdo, array $argv = [])
    {
        $this->pdo = $pdo;
        $this->flush = $flush;
        $this->ensurePdo = $ensurePdo;
        $this->startedAt = microtime(true);
        foreach ($argv as $arg) {
            if ($arg === '--replay-all') {
                $this->replayAll = true;
            } elseif (str_starts_with($arg, '--replay=')) {
                foreach (explode(',', substr($arg, 9)) as $name) {
                    $name = basename(trim($name));
                    if ($name !== '') {
                        $this->replay[] = str_ends_with($name, '.sql') ? $name : $name . '.sql';
                    }
                }
            }
        }
    }

    // ------------------------------------------------------------------ étapes PHP

    /**
     * Exécute une étape PHP isolée : une exception est notée, le pipeline continue.
     */
    public function step(string $name, callable $fn): void
    {
        $this->currentStep = $name;
        $this->phpSteps++;
        try {
            $fn();
        } catch (Throwable $e) {
            echo "  [ERREUR] Étape {$name} : " . $e->getMessage() . "\n";
            $this->recordPhpFailure($name, $e->getMessage());
            ($this->ensurePdo)();
        }
        $this->currentStep = null;
        ($this->flush)();
    }

    /**
     * Note l’échec d’une étape déjà protégée par un try/catch historique.
     */
    public function warn(string $name, Throwable $e): void
    {
        $this->recordPhpFailure($name, $e->getMessage());
    }

    private function recordPhpFailure(string $name, string $detail): void
    {
        $this->phpFailures[] = ['name' => $name, 'status' => 'failed', 'detail' => self::oneLine($detail)];
    }

    // ------------------------------------------------------------------ fichiers SQL

    /**
     * Applique migrations/schema.sql à chaque passage (CREATE TABLE IF NOT EXISTS).
     */
    public function runSchema(string $path): bool
    {
        $sql = @file_get_contents($path);
        if ($sql === false || $sql === '') {
            echo "[ERREUR] Impossible de lire le fichier schema.sql\n";

            return false;
        }
        $statements = self::splitStatements($sql);
        echo '  ' . count($statements) . " instructions à exécuter\n";
        ($this->flush)();
        $result = $this->executeStatements($statements);
        if ($result['errors'] !== []) {
            echo '[ERREUR] schema.sql : ' . count($result['errors']) . " instruction(s) en échec :\n";
            foreach (array_slice($result['errors'], 0, 10) as $err) {
                echo "  - {$err}\n";
            }
            $this->recordPhpFailure('schema.sql', $result['errors'][0]);
        }
        echo "Schéma OK. ({$result['ok']} exécutées, {$result['already']} déjà en place)\n";
        ($this->flush)();

        return true;
    }

    /**
     * Applique tous les fichiers migrations/*.sql en attente, par ordre de nom.
     */
    public function runPendingSqlFiles(string $dir): void
    {
        echo "\n=== Migrations SQL (migrations/*.sql) ===\n";
        if (!is_dir($dir)) {
            echo "[ERREUR] Dossier migrations/ introuvable.\n";
            $this->recordPhpFailure('migrations/', 'dossier introuvable');

            return;
        }
        $ledger = $this->loadLedger();
        $files = self::listSqlFiles($dir);
        echo count($files) . " fichier(s) SQL, journal : " . count($ledger) . " entrée(s).\n";
        ($this->flush)();

        foreach ($files as $path) {
            $name = basename($path);
            $excluded = self::exclusionReason($name);
            if ($excluded !== null) {
                $this->sql['excluded'][] = ['name' => $name, 'detail' => $excluded];
                continue;
            }
            $raw = @file_get_contents($path);
            if ($raw === false) {
                echo "→ {$name}\n  [ERREUR] Fichier illisible.\n";
                $this->sql['failed'][] = ['name' => $name, 'detail' => 'fichier illisible'];
                continue;
            }
            $checksum = hash('sha256', $raw);
            $entry = $ledger[$name] ?? null;
            $forced = $this->replayAll || in_array($name, $this->replay, true);
            if (!$forced && $entry !== null && $entry['status'] === 'applied' && $entry['checksum'] === $checksum) {
                $this->sql['up_to_date'][] = ['name' => $name, 'detail' => ''];
                continue;
            }

            $why = $forced ? 'rejeu demandé' : ($entry === null ? 'nouveau' : ($entry['status'] !== 'applied' ? 'nouvel essai après échec' : 'contenu modifié'));
            echo "→ {$name} ({$why})\n";
            ($this->flush)();
            $this->applySqlFile($name, $raw, $checksum);
        }

        $this->printSqlSection();
    }

    private function applySqlFile(string $name, string $raw, string $checksum): void
    {
        $started = microtime(true);
        $statements = array_values(array_filter(
            self::splitStatements($raw),
            static fn (string $s): bool => preg_match('/^SET\s+(NAMES|FOREIGN_KEY_CHECKS)\b/i', $s) !== 1
        ));
        $result = $this->executeStatements($statements);
        $ms = (int) round((microtime(true) - $started) * 1000);
        $failed = $result['errors'] !== [];

        foreach ($result['errors'] as $err) {
            echo "  [ERREUR] {$err}\n";
        }
        echo '  ' . ($failed ? 'ÉCHEC' : 'OK') . " — {$result['ok']} exécutée(s), {$result['already']} déjà en place, "
            . count($result['errors']) . " en échec ({$ms} ms)\n";
        ($this->flush)();

        $this->sql[$failed ? 'failed' : 'applied'][] = [
            'name' => $name,
            'detail' => $failed ? $result['errors'][0] : '',
        ];
        $this->saveLedger($name, $checksum, $failed ? 'failed' : 'applied', $result, $ms);
    }

    /**
     * @param list<string> $statements
     * @return array{ok:int, already:int, errors:list<string>}
     */
    private function executeStatements(array $statements): array
    {
        $ok = 0;
        $already = 0;
        $errors = [];
        foreach ($statements as $stmt) {
            try {
                self::runStatement(($this->pdo)(), $stmt);
                $ok++;
            } catch (PDOException $e) {
                if (self::isAlreadyApplied($e)) {
                    $already++;
                    continue;
                }
                $errors[] = self::oneLine($e->getMessage()) . ' — « ' . self::excerpt($stmt) . ' »';
                $msg = $e->getMessage();
                if (str_contains($msg, '2014') || migration_is_lost_connection($msg)) {
                    ($this->ensurePdo)();
                }
            }
        }

        return ['ok' => $ok, 'already' => $already, 'errors' => $errors];
    }

    /**
     * SELECT / SHOW / EXECUTE… passent par query() et le curseur est vidé, sinon PDO MySQL
     * lève 2014 sur l’instruction suivante.
     */
    public static function runStatement(PDO $pdo, string $stmt): void
    {
        if (preg_match('/^(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN|WITH|EXECUTE|CALL)\b/i', ltrim($stmt)) === 1) {
            $st = $pdo->query($stmt);
            if ($st !== false) {
                do {
                    try {
                        $st->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException) {
                        // EXECUTE d’un DDL : pas de jeu de résultats à lire.
                    }
                } while ($st->nextRowset());
                $st->closeCursor();
            }

            return;
        }
        $pdo->exec($stmt);
    }

    public static function isAlreadyApplied(PDOException $e): bool
    {
        $info = $e->errorInfo;
        $code = is_array($info) && isset($info[1]) ? (int) $info[1] : 0;
        if ($code === 0 && preg_match('/:\s*(\d{4})\s/', $e->getMessage(), $m) === 1) {
            $code = (int) $m[1];
        }
        if (isset(self::ALREADY_APPLIED_CODES[$code])) {
            return true;
        }
        // 1005 errno 121 : une contrainte du même nom existe déjà.
        if ($code === 1005 && str_contains($e->getMessage(), 'errno: 121')) {
            return true;
        }

        return false;
    }

    public static function exclusionReason(string $basename): ?string
    {
        if (strcasecmp($basename, 'schema.sql') === 0) {
            return 'schéma de base, appliqué en début de pipeline';
        }
        if (preg_match('/_manual\.sql$/i', $basename) === 1) {
            return 'script manuel (à lancer à la main en SSH)';
        }
        if (isset(self::OWNED_SQL_FILES[$basename])) {
            return 'appliqué par ' . self::OWNED_SQL_FILES[$basename];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function listSqlFiles(string $dir): array
    {
        $paths = glob(rtrim($dir, '/') . '/*.sql') ?: [];
        sort($paths, SORT_STRING);

        return array_values($paths);
    }

    // ------------------------------------------------------------------ découpage SQL

    /**
     * Découpe un script SQL en instructions exécutables une par une.
     * Gère chaînes ('…', "…", `…`), commentaires (--, #, /* *\/), DELIMITER, et les blocs
     * BEGIN…END des triggers / procédures écrits sans DELIMITER.
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $sql = str_replace("\r\n", "\n", $sql);
        if (str_starts_with($sql, "\xEF\xBB\xBF")) {
            $sql = substr($sql, 3);
        }
        $len = strlen($sql);
        $out = [];
        $buf = '';
        $delimiter = ';';
        $i = 0;
        $atLineStart = true;

        while ($i < $len) {
            $c = $sql[$i];

            if ($atLineStart && preg_match('/\G[ \t]*DELIMITER[ \t]+(\S+)[ \t]*(?:\n|$)/Ai', $sql, $m, 0, $i) === 1) {
                self::pushStatement($out, $buf);
                $buf = '';
                $delimiter = $m[1];
                $i += strlen($m[0]);
                continue;
            }

            if ($c === "'" || $c === '"' || $c === '`') {
                $j = $i + 1;
                while ($j < $len) {
                    if ($sql[$j] === '\\' && $c !== '`') {
                        $j += 2;
                        continue;
                    }
                    if ($sql[$j] === $c) {
                        if ($j + 1 < $len && $sql[$j + 1] === $c) {
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $j++;
                }
                $buf .= substr($sql, $i, $j - $i + 1);
                $i = $j + 1;
                $atLineStart = false;
                continue;
            }

            if (($c === '-' && substr($sql, $i, 2) === '--' && ($i + 2 >= $len || ctype_space($sql[$i + 2])))
                || $c === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                continue;
            }

            if ($c === '/' && substr($sql, $i, 2) === '/*') {
                $end = strpos($sql, '*/', $i + 2);
                $end = $end === false ? $len : $end + 2;
                if (substr($sql, $i, 3) === '/*!') {
                    $buf .= substr($sql, $i, $end - $i);
                } else {
                    $buf .= ' ';
                }
                $i = $end;
                continue;
            }

            if (substr($sql, $i, strlen($delimiter)) === $delimiter
                && ($delimiter !== ';' || self::blockDepth($buf) <= 0)) {
                self::pushStatement($out, $buf);
                $buf = '';
                $i += strlen($delimiter);
                continue;
            }

            $buf .= $c;
            $atLineStart = $c === "\n";
            $i++;
        }
        self::pushStatement($out, $buf);

        return $out;
    }

    /**
     * Profondeur BEGIN…END d’un CREATE TRIGGER / PROCEDURE / FUNCTION / EVENT en cours.
     * Hors de ces instructions, toujours 0 (un « ; » termine l’instruction).
     */
    private static function blockDepth(string $buf): int
    {
        $head = ltrim($buf);
        if (preg_match('/^CREATE\s+(?:OR\s+REPLACE\s+)?(?:DEFINER\s*=\s*\S+\s+)?(?:TRIGGER|PROCEDURE|FUNCTION|EVENT)\b/i', $head) !== 1) {
            return 0;
        }
        $plain = preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'|\"(?:[^\"\\\\]|\\\\.)*\"|`[^`]*`/s", "''", $head) ?? $head;
        preg_match_all('/\b(BEGIN|CASE|END(?:\s+(?:IF|LOOP|WHILE|REPEAT|CASE)\b)?)/i', $plain, $m);
        $depth = 0;
        foreach ($m[1] as $word) {
            $w = strtoupper(preg_replace('/\s+/', ' ', $word) ?? $word);
            if ($w === 'BEGIN' || $w === 'CASE') {
                $depth++;
            } elseif ($w === 'END' || $w === 'END CASE') {
                $depth--;
            }
        }

        return $depth;
    }

    /** @param list<string> $out */
    private static function pushStatement(array &$out, string $buf): void
    {
        $stmt = trim($buf);
        if ($stmt !== '') {
            $out[] = $stmt;
        }
    }

    // ------------------------------------------------------------------ journal en base

    private function ensureLedger(): bool
    {
        if ($this->ledgerReady) {
            return true;
        }
        try {
            ($this->pdo)()->exec('CREATE TABLE IF NOT EXISTS `' . self::LEDGER_TABLE . '` (
                `name` VARCHAR(191) NOT NULL,
                `checksum` CHAR(64) NOT NULL,
                `status` VARCHAR(16) NOT NULL,
                `statements_ok` INT UNSIGNED NOT NULL DEFAULT 0,
                `statements_already` INT UNSIGNED NOT NULL DEFAULT 0,
                `statements_failed` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_error` TEXT NULL,
                `first_applied_at` DATETIME NULL,
                `last_run_at` DATETIME NOT NULL,
                `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $this->ledgerReady = true;
        } catch (Throwable $e) {
            echo '[ERREUR] Journal des migrations impossible à créer : ' . $e->getMessage() . "\n";
        }

        return $this->ledgerReady;
    }

    /**
     * @return array<string, array{checksum:string, status:string}>
     */
    private function loadLedger(): array
    {
        if (!$this->ensureLedger()) {
            return [];
        }

        return self::readLedger(($this->pdo)());
    }

    /**
     * @return array<string, array{checksum:string, status:string, last_error:?string, last_run_at:?string}>
     */
    public static function readLedger(PDO $pdo): array
    {
        $rows = [];
        try {
            $st = $pdo->query('SELECT name, checksum, status, last_error, last_run_at FROM `' . self::LEDGER_TABLE . '`');
            foreach ($st ? $st->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                $rows[(string) $row['name']] = [
                    'checksum' => (string) $row['checksum'],
                    'status' => (string) $row['status'],
                    'last_error' => $row['last_error'] !== null ? (string) $row['last_error'] : null,
                    'last_run_at' => $row['last_run_at'] !== null ? (string) $row['last_run_at'] : null,
                ];
            }
        } catch (Throwable) {
            return [];
        }

        return $rows;
    }

    /**
     * @param array{ok:int, already:int, errors:list<string>} $result
     */
    private function saveLedger(string $name, string $checksum, string $status, array $result, int $ms): void
    {
        if (!$this->ensureLedger()) {
            return;
        }
        try {
            $st = ($this->pdo)()->prepare('INSERT INTO `' . self::LEDGER_TABLE . '`
                (name, checksum, status, statements_ok, statements_already, statements_failed, last_error, first_applied_at, last_run_at, duration_ms)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), status = VALUES(status),
                    statements_ok = VALUES(statements_ok), statements_already = VALUES(statements_already),
                    statements_failed = VALUES(statements_failed), last_error = VALUES(last_error),
                    first_applied_at = COALESCE(first_applied_at, VALUES(first_applied_at)),
                    last_run_at = VALUES(last_run_at), duration_ms = VALUES(duration_ms)');
            $st->execute([
                $name,
                $checksum,
                $status,
                $result['ok'],
                $result['already'],
                count($result['errors']),
                $result['errors'] === [] ? null : implode("\n", $result['errors']),
                $status === 'applied' ? date('Y-m-d H:i:s') : null,
                $ms,
            ]);
        } catch (Throwable $e) {
            echo "  [ERREUR] Journal non mis à jour pour {$name} : " . $e->getMessage() . "\n";
        }
    }

    /**
     * État des fichiers SQL sans rien exécuter (tableau de bord web, --status).
     *
     * @return array{pending:list<string>, failed:list<array{name:string, error:string}>, applied:int, excluded:int, total:int, ledger:bool}
     */
    public static function statusReport(PDO $pdo, string $dir): array
    {
        $ledger = self::readLedger($pdo);
        $report = ['pending' => [], 'failed' => [], 'applied' => 0, 'excluded' => 0, 'total' => 0, 'ledger' => $ledger !== []];
        foreach (self::listSqlFiles($dir) as $path) {
            $name = basename($path);
            $report['total']++;
            if (self::exclusionReason($name) !== null) {
                $report['excluded']++;
                continue;
            }
            $entry = $ledger[$name] ?? null;
            $checksum = hash_file('sha256', $path) ?: '';
            if ($entry !== null && $entry['status'] === 'applied' && $entry['checksum'] === $checksum) {
                $report['applied']++;
            } elseif ($entry !== null && $entry['status'] === 'failed' && $entry['checksum'] === $checksum) {
                $report['failed'][] = ['name' => $name, 'error' => self::oneLine((string) $entry['last_error'])];
            } else {
                $report['pending'][] = $name;
            }
        }

        return $report;
    }

    // ------------------------------------------------------------------ rapport

    private function printSqlSection(): void
    {
        $s = $this->sql;
        echo "\nSQL : " . count($s['applied']) . ' appliqué(s), ' . count($s['up_to_date']) . ' déjà à jour, '
            . count($s['failed']) . ' en échec, ' . count($s['excluded']) . " exclu(s).\n";
        ($this->flush)();
    }

    /**
     * Garde-fou : si le script s’arrête sur une erreur fatale, on dit où et on imprime le bilan.
     */
    public function registerShutdownReport(): void
    {
        register_shutdown_function(function (): void {
            if ($this->finished) {
                return;
            }
            $err = error_get_last();
            echo "\n[ERREUR] Pipeline interrompu" . ($this->currentStep !== null ? " pendant l’étape {$this->currentStep}" : '')
                . ($err !== null ? ' : ' . self::oneLine((string) $err['message']) : '') . "\n";
            $this->recordPhpFailure($this->currentStep ?? 'pipeline', 'interrompu');
            $this->printReport();
        });
    }

    /**
     * Bilan final ; renvoie true si rien n’a échoué.
     */
    public function printReport(): bool
    {
        $this->finished = true;
        $s = $this->sql;
        $duration = (int) round(microtime(true) - $this->startedAt);
        echo "\n=== Bilan des migrations ===\n";
        echo 'Étapes PHP : ' . $this->phpSteps . ' isolée(s), ' . count($this->phpFailures) . " en échec.\n";
        echo 'Fichiers SQL : ' . count($s['applied']) . ' appliqué(s) ce passage, ' . count($s['up_to_date'])
            . ' déjà à jour, ' . count($s['failed']) . ' en échec, ' . count($s['excluded']) . " exclu(s).\n";
        foreach ($s['applied'] as $row) {
            echo "  [APPLIQUÉ] {$row['name']}\n";
        }
        foreach ($s['excluded'] as $row) {
            echo "  [EXCLU] {$row['name']} — {$row['detail']}\n";
        }
        foreach ($s['failed'] as $row) {
            echo "  [ERREUR] SQL {$row['name']} — {$row['detail']}\n";
        }
        foreach ($this->phpFailures as $row) {
            echo "  [ERREUR] PHP {$row['name']} — {$row['detail']}\n";
        }
        $ok = $s['failed'] === [] && $this->phpFailures === [];
        echo ($ok ? '[OK] Toutes les migrations sont appliquées' : '[ERREUR] Des migrations ont échoué : corrigez puis relancez (seuls les fichiers en échec ou modifiés seront rejoués)')
            . " ({$duration} s).\n";
        ($this->flush)();

        return $ok;
    }

    public function hasFailures(): bool
    {
        return $this->sql['failed'] !== [] || $this->phpFailures !== [];
    }

    private static function oneLine(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? $s);
        $s = preg_replace('/^SQLSTATE\[[0-9A-Z]+\]:\s*/', '', $s) ?? $s;

        return mb_strlen($s) > 220 ? mb_substr($s, 0, 220) . '…' : $s;
    }

    private static function excerpt(string $stmt): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $stmt) ?? $stmt);

        return mb_strlen($s) > 90 ? mb_substr($s, 0, 90) . '…' : $s;
    }
}
