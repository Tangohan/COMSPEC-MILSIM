<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CronJobRunRepository
{
    private PDO $pdo;

    private static ?bool $runsTable = null;

    private static ?bool $notifTable = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function runsTableExists(): bool
    {
        if (self::$runsTable === null) {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cron_job_runs' LIMIT 1");
            self::$runsTable = $st && (bool) $st->fetchColumn();
        }

        return self::$runsTable;
    }

    public function notificationLogExists(): bool
    {
        if (self::$notifTable === null) {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cron_notification_log' LIMIT 1");
            self::$notifTable = $st && (bool) $st->fetchColumn();
        }

        return self::$notifTable;
    }

    public function beginRun(string $jobKey, string $triggerSource): ?int
    {
        if (!$this->runsTableExists() || $jobKey === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO cron_job_runs (job_key, started_at, status, trigger_source) VALUES (?, NOW(), \'running\', ?)'
        );
        $stmt->execute([$jobKey, mb_substr($triggerSource, 0, 32)]);

        return (int) $this->pdo->lastInsertId();
    }

    public function finishRun(?int $runId, string $status, string $summary, ?array $details = null): void
    {
        if ($runId === null || $runId < 1 || !$this->runsTableExists()) {
            return;
        }
        $status = in_array($status, ['ok', 'error'], true) ? $status : 'error';
        $json = $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $this->pdo->prepare(
            'UPDATE cron_job_runs SET finished_at = NOW(), status = ?, summary = ?, details_json = ? WHERE id = ?'
        );
        $stmt->execute([$status, mb_substr($summary, 0, 512), $json, $runId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 40): array
    {
        if (!$this->runsTableExists()) {
            return [];
        }
        $lim = max(1, min(100, $limit));
        $stmt = $this->pdo->query(
            "SELECT * FROM cron_job_runs ORDER BY id DESC LIMIT {$lim}"
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function latestByJobKey(): array
    {
        if (!$this->runsTableExists()) {
            return [];
        }
        $stmt = $this->pdo->query(
            'SELECT r.* FROM cron_job_runs r
             INNER JOIN (
                SELECT job_key, MAX(id) AS max_id FROM cron_job_runs GROUP BY job_key
             ) t ON t.max_id = r.id'
        );
        $out = [];
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $out[(string) ($row['job_key'] ?? '')] = $row;
            }
        }

        return $out;
    }

    public function wasNotified(string $jobKey, string $subjectType, string $subjectId, string $channel = 'email'): bool
    {
        if (!$this->notificationLogExists()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM cron_notification_log
             WHERE job_key = ? AND subject_type = ? AND subject_id = ? AND channel = ? LIMIT 1'
        );
        $stmt->execute([$jobKey, $subjectType, $subjectId, $channel]);

        return (bool) $stmt->fetchColumn();
    }

    public function markNotified(string $jobKey, string $subjectType, string $subjectId, string $channel = 'email', ?string $recipient = null): bool
    {
        if (!$this->notificationLogExists()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO cron_notification_log (job_key, subject_type, subject_id, channel, recipient, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$jobKey, $subjectType, $subjectId, $channel, $recipient]);

        return $stmt->rowCount() > 0;
    }
}
