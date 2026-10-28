<?php

declare(strict_types=1);

namespace App\Support\Migrations;

use PDO;
use Throwable;

/**
 * Journal des passages du pipeline : une ligne par étape exécutée (table schema_migration_runs).
 * Sert au tableau de bord web (dernier résultat de chaque étape, bilan du dernier passage).
 * Toutes les méthodes sont tolérantes : le journal ne doit jamais faire échouer une migration.
 */
final class MigrationRunLog
{
    public const TABLE = 'schema_migration_runs';

    public static function ensure(PDO $pdo): bool
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    run_id CHAR(32) NOT NULL,
                    mode VARCHAR(12) NOT NULL DEFAULT \'cli\',
                    step_id VARCHAR(120) NOT NULL,
                    status VARCHAR(10) NOT NULL,
                    duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
                    message VARCHAR(500) NOT NULL DEFAULT \'\',
                    output MEDIUMTEXT NULL,
                    ran_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_smr_step (step_id, id),
                    KEY idx_smr_run (run_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function newRunId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function record(PDO $pdo, string $runId, string $mode, string $stepId, string $status, int $durationMs, string $message, string $output): void
    {
        try {
            $st = $pdo->prepare(
                'INSERT INTO ' . self::TABLE . ' (run_id, mode, step_id, status, duration_ms, message, output, ran_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $runId,
                substr($mode, 0, 12),
                substr($stepId, 0, 120),
                substr($status, 0, 10),
                max(0, $durationMs),
                mb_substr($message, 0, 500),
                mb_substr($output, 0, 20000),
                gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
        }
    }

    /**
     * Dernier résultat connu de chaque étape.
     *
     * @return array<string, array{status: string, duration_ms: int, message: string, ran_at: string, run_id: string, mode: string}>
     */
    public static function latestByStep(PDO $pdo): array
    {
        try {
            $rows = $pdo->query(
                'SELECT r.step_id, r.status, r.duration_ms, r.message, r.ran_at, r.run_id, r.mode FROM ' . self::TABLE . ' r
                 JOIN (SELECT step_id, MAX(id) AS id FROM ' . self::TABLE . ' GROUP BY step_id) m ON m.id = r.id'
            );
            $out = [];
            foreach ($rows !== false ? ($rows->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $r) {
                $out[(string) $r['step_id']] = [
                    'status' => (string) $r['status'],
                    'duration_ms' => (int) $r['duration_ms'],
                    'message' => (string) $r['message'],
                    'ran_at' => (string) $r['ran_at'],
                    'run_id' => (string) $r['run_id'],
                    'mode' => (string) $r['mode'],
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Bilan du dernier passage (complet ou étape seule).
     *
     * @return array{run_id: string, mode: string, started_at: string, finished_at: string, steps: int, ok: int, warn: int, error: int, duration_ms: int}|null
     */
    public static function lastRun(PDO $pdo): ?array
    {
        try {
            $last = $pdo->query('SELECT run_id FROM ' . self::TABLE . ' ORDER BY id DESC LIMIT 1');
            $runId = $last !== false ? (string) ($last->fetchColumn() ?: '') : '';
            if ($runId === '') {
                return null;
            }
            $st = $pdo->prepare(
                'SELECT MIN(ran_at) AS started_at, MAX(ran_at) AS finished_at, COUNT(*) AS steps, MAX(mode) AS mode,
                        SUM(status = \'ok\') AS ok, SUM(status = \'warn\') AS warn, SUM(status = \'error\') AS error,
                        SUM(duration_ms) AS duration_ms
                 FROM ' . self::TABLE . ' WHERE run_id = ?'
            );
            $st->execute([$runId]);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'run_id' => $runId,
                'mode' => (string) ($r['mode'] ?? ''),
                'started_at' => (string) ($r['started_at'] ?? ''),
                'finished_at' => (string) ($r['finished_at'] ?? ''),
                'steps' => (int) ($r['steps'] ?? 0),
                'ok' => (int) ($r['ok'] ?? 0),
                'warn' => (int) ($r['warn'] ?? 0),
                'error' => (int) ($r['error'] ?? 0),
                'duration_ms' => (int) ($r['duration_ms'] ?? 0),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /** Sortie enregistrée de la dernière exécution d'une étape (consultation dans l'interface). */
    public static function lastOutput(PDO $pdo, string $stepId): ?string
    {
        try {
            $st = $pdo->prepare('SELECT output FROM ' . self::TABLE . ' WHERE step_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$stepId]);
            $v = $st->fetchColumn();

            return is_string($v) ? $v : null;
        } catch (Throwable) {
            return null;
        }
    }
}
