<?php

declare(strict_types=1);

/**
 * Socle des rapports de cohérence du back-office (audit, lot 6) : tools/audit-report-*.php.
 *
 * Ces rapports LISENT la base et ne modifient rien :
 *  - une option d'écriture (--write, --apply, --fix…) est refusée ;
 *  - la session MySQL est ouverte en LECTURE SEULE (START TRANSACTION READ ONLY) : le serveur refuse toute écriture ;
 *  - toute requête passe par AuditReadOnlyDb::rows(), qui n'accepte qu'un SELECT (ou WITH … SELECT) en une instruction ;
 *  - exécution en ligne de commande uniquement.
 *
 * La connexion est celle de l'application (app/Config/database.local.php, sinon .env : DB_HOST, DB_NAME, DB_USER,
 * DB_PASSWORD). Le script ne lit donc que la base que cette configuration désigne : sur un poste de développement,
 * la base locale ; sur le serveur, sa propre base.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Accès à la base en lecture seule : une seule porte, qui refuse tout ce qui n'est pas une lecture. */
class AuditReadOnlyDb
{
    /** Mots qui n'ont pas leur place dans une lecture, même cités dans une requête. */
    private const FORBIDDEN = '/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE|CREATE|GRANT|REVOKE|CALL|LOCK|UNLOCK|RENAME|LOAD|HANDLER|SET|DO|PREPARE|EXECUTE|OUTFILE|DUMPFILE)\b/i';

    public function __construct(protected readonly PDO $pdo)
    {
    }

    /** Lève une exception si la requête n'est pas une lecture. */
    public static function assertReadOnlySql(string $sql): void
    {
        $clean = preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'/s', "''", $sql) ?? $sql;
        $clean = trim((string) preg_replace('/--[^\n]*|\/\*.*?\*\//s', ' ', $clean));
        if (preg_match('/^(SELECT|WITH)\b/i', $clean) !== 1) {
            throw new RuntimeException('Requête refusée (lecture seule) : ' . substr($clean, 0, 80));
        }
        if (str_contains(rtrim($clean, "; \t\n\r"), ';')) {
            throw new RuntimeException('Requête refusée : une seule instruction est permise.');
        }
        if (preg_match(self::FORBIDDEN, $clean) === 1) {
            throw new RuntimeException('Requête refusée (mot interdit en lecture seule) : ' . substr($clean, 0, 80));
        }
    }

    /**
     * @param list<scalar|null> $params paramètres positionnels (?)
     * @return list<array<string, mixed>>
     */
    public function rows(string $sql, array $params = []): array
    {
        self::assertReadOnlySql($sql);
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function hasTable(string $table): bool
    {
        return $this->rows(
            'SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
            [$table]
        ) !== [];
    }

    public function hasColumn(string $table, string $column): bool
    {
        return $this->rows(
            'SELECT 1 AS ok FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$table, $column]
        ) !== [];
    }
}

final class AuditReportKit
{
    /** @var array{tenant:int, format:string, limit:int, selftest:bool} */
    public array $options = ['tenant' => 0, 'format' => 'text', 'limit' => 200, 'selftest' => false];

    /** Tests : garde les sections au lieu de les afficher. @var list<array{title:string,total:int,rows:list<array<string,mixed>>}> */
    public array $captured = [];

    public bool $capture = false;

    public function __construct(private readonly string $name, private readonly string $purpose, private readonly string $reference)
    {
    }

    /**
     * Lit les options. Toute option d'écriture est refusée, toute option inconnue aussi.
     *
     * @param list<string> $argv
     */
    public function parse(array $argv): void
    {
        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $this->usage();
                exit(0);
            }
            if (preg_match('/^--(write|apply|fix|update|delete|execute|commit|force|repair|migrate|purge|sync)\b/i', $arg) === 1) {
                $this->fail('Ce rapport est en lecture seule : l’option ' . $arg . ' n’existe pas. Les corrections se font ailleurs, après relecture du rapport.');
            }
            if (preg_match('/^--tenant=(\d+)$/', $arg, $m) === 1) {
                $this->options['tenant'] = (int) $m[1];
            } elseif (preg_match('/^--format=(text|csv|json)$/', $arg, $m) === 1) {
                $this->options['format'] = $m[1];
            } elseif (preg_match('/^--limit=(\d+)$/', $arg, $m) === 1) {
                $this->options['limit'] = max(1, (int) $m[1]);
            } elseif ($arg === '--selftest') {
                $this->options['selftest'] = true;
            } else {
                $this->fail('Option inconnue : ' . $arg . ' (voir --help).');
            }
        }
    }

    /** Vérifie, sans base, que le garde-fou refuse bien les écritures. */
    public function selfTest(): never
    {
        $refused = [
            'UPDATE users SET grade_id = 1',
            'DELETE FROM users',
            'INSERT INTO users (id) VALUES (1)',
            'SELECT 1; DROP TABLE users',
            'SELECT * FROM users INTO OUTFILE \'/tmp/x\'',
            'SET SESSION sql_mode = \'\'',
            '/* x */ UPDATE users SET id = 1',
        ];
        foreach ($refused as $sql) {
            try {
                AuditReadOnlyDb::assertReadOnlySql($sql);
                fwrite(STDERR, 'ÉCHEC : requête acceptée à tort : ' . $sql . "\n");
                exit(1);
            } catch (RuntimeException) {
            }
        }
        AuditReadOnlyDb::assertReadOnlySql('SELECT u.id FROM users u WHERE u.display_name = \'update delete\' AND u.status = ?');
        echo $this->name . " : garde-fou lecture seule OK (" . count($refused) . " écritures refusées).\n";
        exit(0);
    }

    /** Ouvre la base de l'application en lecture seule. */
    public function connect(): AuditReadOnlyDb
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/bootstrap/autoload.php';
        $pdo = \App\Core\Database::getPdo();
        // Le serveur lui-même refuse toute écriture sur cette session.
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $pdo->exec('START TRANSACTION READ ONLY');

        return new AuditReadOnlyDb($pdo);
    }

    /**
     * Affiche un tableau (texte, CSV ou JSON). Retourne le nombre de lignes totales avant la limite.
     *
     * @param array<string, string> $columns clé de ligne => titre de colonne
     * @param list<array<string, mixed>> $rows
     */
    public function table(string $title, array $columns, array $rows, string $note = ''): int
    {
        $total = count($rows);
        $shown = array_slice($rows, 0, $this->options['limit']);
        if ($this->capture) {
            $this->captured[] = ['title' => $title, 'total' => $total, 'rows' => $rows];

            return $total;
        }
        if ($this->options['format'] === 'json') {
            echo json_encode(['section' => $title, 'total' => $total, 'rows' => $shown], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";

            return $total;
        }
        if ($this->options['format'] === 'csv') {
            echo '# ', $title, ' (', $total, ")\n";
            $out = fopen('php://output', 'wb');
            fputcsv($out, array_values($columns), ';', '"', '\\');
            foreach ($shown as $row) {
                fputcsv($out, array_map(static fn (string $k): string => (string) ($row[$k] ?? ''), array_keys($columns)), ';', '"', '\\');
            }
            fclose($out);

            return $total;
        }
        echo "\n== ", $title, ' : ', $total, " ligne(s)\n";
        if ($note !== '') {
            echo $note, "\n";
        }
        foreach ($shown as $row) {
            $cells = [];
            foreach ($columns as $key => $label) {
                $cells[] = $label . '=' . trim((string) ($row[$key] ?? ''));
            }
            echo '  - ', implode(' | ', $cells), "\n";
        }
        if ($total > count($shown)) {
            echo '  … ', $total - count($shown), " ligne(s) de plus (--limit=N pour en voir davantage).\n";
        }

        return $total;
    }

    public function say(string $line): void
    {
        if ($this->options['format'] === 'text') {
            echo $line, "\n";
        }
    }

    public function usage(): void
    {
        echo $this->name, ' — ', $this->purpose, "\n";
        echo 'Référence : ', $this->reference, "\n\n";
        echo "Rapport en LECTURE SEULE : aucune écriture, aucune correction.\n";
        echo "Usage : php tools/{$this->name}.php [--tenant=ID] [--format=text|csv|json] [--limit=N] [--selftest] [--help]\n";
        echo "  --tenant=ID   limite le rapport à une communauté (sinon toutes)\n";
        echo "  --format=…    text (défaut), csv (séparateur « ; ») ou json\n";
        echo "  --limit=N     nombre de lignes affichées par section (défaut 200)\n";
        echo "  --selftest    vérifie le garde-fou lecture seule, sans toucher à la base\n";
        echo "Connexion : celle de l'application (app/Config/database.local.php ou .env).\n";
    }

    private function fail(string $message): never
    {
        fwrite(STDERR, $this->name . ' : ' . $message . "\n");
        exit(2);
    }

    /** Point d'entrée commun : options, auto-test, puis connexion. */
    public function start(array $argv): AuditReadOnlyDb
    {
        $this->parse($argv);
        if ($this->options['selftest']) {
            $this->selfTest();
        }

        return $this->connect();
    }
}
