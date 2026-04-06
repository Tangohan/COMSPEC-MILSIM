<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AsyncJobRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'async_jobs' LIMIT 1"
            );

            return (bool) $stmt?->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function enqueue(string $jobType, string $payloadJson, ?int $tenantId = null, ?\DateTimeInterface $availableAt = null): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $when = $availableAt?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO async_jobs (tenant_id, job_type, payload_json, available_at, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $jobType, $payloadJson, $when]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claimNext(): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->query(
                "SELECT * FROM async_jobs WHERE reserved_at IS NULL AND available_at <= NOW() AND attempts < 8 ORDER BY id ASC LIMIT 1 FOR UPDATE"
            );
            $row = $stmt?->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->pdo->commit();

                return null;
            }
            $id = (int) $row['id'];
            $this->pdo->prepare(
                'UPDATE async_jobs SET reserved_at = NOW(), attempts = attempts + 1 WHERE id = ?'
            )->execute([$id]);
            $this->pdo->commit();

            return $row;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $this->pdo->prepare('DELETE FROM async_jobs WHERE id = ?')->execute([$id]);
    }

    public function release(int $id, string $error, int $delaySeconds = 60): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $when = date('Y-m-d H:i:s', time() + max(10, $delaySeconds));
        $stmt = $this->pdo->prepare(
            'UPDATE async_jobs SET reserved_at = NULL, available_at = ?, last_error = ? WHERE id = ?'
        );
        $stmt->execute([$when, $error, $id]);
    }
}
