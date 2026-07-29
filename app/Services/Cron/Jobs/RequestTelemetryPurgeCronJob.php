<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use PDO;

/**
 * Conserve la télémétrie HTTP récente pour le pilotage, purge le reste et
 * compacte le tablespace InnoDB quand il reste de l’espace libre après DELETE.
 */
final class RequestTelemetryPurgeCronJob implements CronJobInterface
{
    /** Couvre le tableau de bord mensuel (mois courant + précédent) avec marge. */
    public const RETENTION_DAYS = 90;

    private const BATCH_SIZE = 5000;

    /** Seuil d’espace libre avant reconstruction de la table (octets). */
    private const OPTIMIZE_FREE_BYTES = 1_048_576;

    public function __construct(private PDO $pdo) {}

    public function key(): string
    {
        return 'request_telemetry_purge';
    }

    public function label(): string
    {
        return 'Nettoyage du journal de performance';
    }

    public function description(): string
    {
        return 'Supprime les mesures de latence et de statut des pages au-delà de '
            . self::RETENTION_DAYS
            . ' jours, puis récupère l’espace disque inutile si besoin.';
    }

    public function run(): array
    {
        $chk = $this->pdo->query(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_telemetry' LIMIT 1"
        );
        if (!$chk || !$chk->fetchColumn()) {
            return [
                'ok' => true,
                'summary' => 'Table absente — rien à faire.',
                'details' => ['deleted' => 0, 'optimized' => false, 'skipped' => true],
            ];
        }

        $deleted = $this->purgeExpired();
        $stats = $this->tableStats();
        $optimized = false;
        if ($this->shouldOptimize($deleted, $stats)) {
            $optimized = $this->optimizeTable();
        }

        $parts = ["Lignes supprimées : {$deleted}"];
        $parts[] = $optimized ? 'Espace disque récupéré.' : 'Pas de compactage nécessaire.';

        return [
            'ok' => true,
            'summary' => implode(' · ', $parts),
            'details' => [
                'deleted' => $deleted,
                'retention_days' => self::RETENTION_DAYS,
                'optimized' => $optimized,
                'size_bytes_before' => $stats['size_bytes'],
                'approx_rows' => $stats['approx_rows'],
                'data_free_bytes' => $stats['data_free'],
            ],
        ];
    }

    private function purgeExpired(): int
    {
        $total = 0;
        $stmt = $this->pdo->prepare(
            'DELETE FROM request_telemetry
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY created_at ASC
             LIMIT ' . self::BATCH_SIZE
        );

        // Plafond de lots pour éviter un run CLI infini si la table est énorme.
        for ($i = 0; $i < 200; $i++) {
            $stmt->execute([self::RETENTION_DAYS]);
            $n = $stmt->rowCount();
            $total += $n;
            if ($n < self::BATCH_SIZE) {
                break;
            }
        }

        return $total;
    }

    /**
     * @return array{size_bytes: int, approx_rows: int, data_free: int}
     */
    private function tableStats(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS size_bytes,
                COALESCE(TABLE_ROWS, 0) AS approx_rows,
                COALESCE(DATA_FREE, 0) AS data_free
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_telemetry'
             LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!is_array($row)) {
            return ['size_bytes' => 0, 'approx_rows' => 0, 'data_free' => 0];
        }

        return [
            'size_bytes' => (int) ($row['size_bytes'] ?? 0),
            'approx_rows' => (int) ($row['approx_rows'] ?? 0),
            'data_free' => (int) ($row['data_free'] ?? 0),
        ];
    }

    /**
     * @param array{size_bytes: int, approx_rows: int, data_free: int} $stats
     */
    private function shouldOptimize(int $deleted, array $stats): bool
    {
        if ($stats['data_free'] >= self::OPTIMIZE_FREE_BYTES) {
            return true;
        }

        // Schéma ~300 o/ligne : > 8 Kio/ligne + table > 1 Mio = tablespace non rendu après DELETE.
        $rows = max(1, $stats['approx_rows']);
        if ($stats['size_bytes'] >= self::OPTIMIZE_FREE_BYTES && ($stats['size_bytes'] / $rows) > 8192) {
            return true;
        }

        // Gros lot purgé : forcer un rebuild même si information_schema n’a pas encore rafraîchi.
        return $deleted >= self::BATCH_SIZE;
    }

    private function optimizeTable(): bool
    {
        $result = $this->pdo->query('OPTIMIZE TABLE request_telemetry');
        if ($result) {
            $result->fetchAll(PDO::FETCH_ASSOC);
            $result->closeCursor();
        }

        return true;
    }
}
