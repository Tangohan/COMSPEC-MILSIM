<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Support\SseSignalSchema;
use App\Support\SseTriageSchema;
use PDO;

/**
 * Base sqlite en mémoire de « À trier » : les tables du moteur analytique (signaux d'avant la déduplication), des dossiers,
 * personnes et identités, puis, au choix, les tables et colonnes de l'étape 4690 créées par le VRAI fichier de l'étape (SQL de la
 * migration traduit par MysqlDdlSqlitePdo). Branchée sur Database le temps d'un test.
 */
final class SseTriageSqlite
{
    private static ?PDO $saved = null;

    public static function open(bool $withStep = true): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'Alpha', 'alpha'), (2, 'Bravo', 'bravo')");
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, steam_id TEXT, display_name TEXT)');
        $pdo->exec('CREATE TABLE sse_cases (id INTEGER PRIMARY KEY, tenant_id INTEGER, reference_code TEXT, title TEXT, classification TEXT, status TEXT, is_folder INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE sse_persons (id INTEGER PRIMARY KEY, tenant_id INTEGER, last_name TEXT, first_name TEXT, alias TEXT)');
        $pdo->exec('CREATE TABLE sse_case_persons (id INTEGER PRIMARY KEY, case_id INTEGER, person_id INTEGER, tenant_id INTEGER)');
        $pdo->exec('CREATE TABLE sse_identities (id INTEGER PRIMARY KEY, tenant_id INTEGER, person_id INTEGER, kind TEXT, user_id INTEGER, first_name TEXT, last_name TEXT)');
        // Signaux tels que créés par bootstrap/atak_sse_engine_migration.php (avant l'étape 4690).
        $pdo->exec("CREATE TABLE sse_engine_signals (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            signal_type VARCHAR(40) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'info',
            title VARCHAR(220) NOT NULL,
            detail MEDIUMTEXT NULL,
            payload_json MEDIUMTEXT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'open',
            rule_key VARCHAR(64) NOT NULL DEFAULT '',
            run_id VARCHAR(40) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_signal_case (tenant_id, case_id, status),
            KEY idx_sse_signal_type (tenant_id, signal_type, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $current = $prop->getValue();
        self::$saved = $current instanceof PDO ? $current : null;
        $prop->setValue(null, $pdo);
        SseSignalSchema::forget();
        SseTriageSchema::forget();
        if ($withStep) {
            self::runStep($pdo);
        }

        return $pdo;
    }

    /** Joue le vrai fichier database/steps/4690_sse_triage_automatisms.php ; retourne sa sortie. */
    public static function runStep(PDO $pdo): string
    {
        $run = static function () use ($pdo): string {
            $root = dirname(__DIR__, 2);
            ob_start();
            try {
                require $root . '/database/steps/4690_sse_triage_automatisms.php';
            } finally {
                $out = (string) ob_get_clean();
            }

            return $out;
        };
        $out = $run();
        SseSignalSchema::forget();
        SseTriageSchema::forget();

        return $out;
    }

    public static function close(): void
    {
        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $prop->setValue(null, self::$saved);
        self::$saved = null;
        SseSignalSchema::forget();
        SseTriageSchema::forget();
    }

    public static function addCase(PDO $pdo, int $id, string $ref, string $title, int $tenant = 1, string $classification = 'commandement', string $status = 'ouvert'): void
    {
        $pdo->prepare('INSERT INTO sse_cases (id, tenant_id, reference_code, title, classification, status, is_folder, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)')
            ->execute([$id, $tenant, $ref, $title, $classification, $status, '2026-10-01 08:00:00', '2026-10-01 08:00:00']);
    }

    /** Une personne rattachée à un dossier, avec (ou non) une identité de ce type (civil, cover) et un compte de membre. */
    public static function addPerson(PDO $pdo, int $caseId, int $personId, string $first, string $last, ?string $identityKind = null, int $memberUserId = 0, int $tenant = 1): void
    {
        $pdo->prepare('INSERT INTO sse_persons (id, tenant_id, last_name, first_name, alias) VALUES (?, ?, ?, ?, NULL)')->execute([$personId, $tenant, $last, $first]);
        $pdo->prepare('INSERT INTO sse_case_persons (case_id, person_id, tenant_id) VALUES (?, ?, ?)')->execute([$caseId, $personId, $tenant]);
        if ($identityKind !== null) {
            $pdo->prepare('INSERT INTO sse_identities (id, tenant_id, person_id, kind, user_id, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$personId + 1000, $tenant, $personId, $identityKind, $memberUserId ?: null, $first, $last]);
        }
    }
}
