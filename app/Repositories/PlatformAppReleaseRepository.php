<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PlatformAppReleaseRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_app_releases' LIMIT 1"
            );

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 30): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM platform_app_releases ORDER BY created_at DESC, id DESC LIMIT :lim'
        );
        $st->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM platform_app_releases WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByVersion(string $version): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM platform_app_releases WHERE version = ? LIMIT 1');
        $st->execute([$version]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO platform_app_releases
            (version, minimum_version, status, package_path, extract_path, payload_checksum, manifest_json, maintenance_required, uploaded_by, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $data['version'],
            $data['minimum_version'] ?? null,
            $data['status'] ?? 'uploaded',
            $data['package_path'],
            $data['extract_path'] ?? null,
            $data['payload_checksum'] ?? null,
            isset($data['manifest_json']) ? (is_string($data['manifest_json']) ? $data['manifest_json'] : json_encode($data['manifest_json'], JSON_UNESCAPED_UNICODE)) : null,
            (int) ($data['maintenance_required'] ?? 1),
            $data['uploaded_by'] ?? null,
            $data['error_message'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function update(int $id, array $fields): void
    {
        $allowed = [
            'status', 'extract_path', 'payload_checksum', 'manifest_json', 'maintenance_required',
            'deployed_by', 'deployed_at', 'rolled_back_at', 'error_message', 'minimum_version',
        ];
        $sets = [];
        $vals = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }
            $sets[] = $key . ' = ?';
            $val = $fields[$key];
            if ($key === 'manifest_json' && is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
            $vals[] = $val;
        }
        if ($sets === []) {
            return;
        }
        $vals[] = $id;
        $this->pdo->prepare('UPDATE platform_app_releases SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    public function deleteFilesForRelease(int $releaseId): void
    {
        $this->pdo->prepare('DELETE FROM platform_app_release_files WHERE release_id = ?')->execute([$releaseId]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function insertFiles(int $releaseId, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO platform_app_release_files
            (release_id, relative_path, action, source_checksum, target_checksum, conflict)
            VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($rows as $row) {
            $st->execute([
                $releaseId,
                $row['relative_path'],
                $row['action'],
                $row['source_checksum'] ?? null,
                $row['target_checksum'] ?? null,
                (int) ($row['conflict'] ?? 0),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listFiles(int $releaseId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM platform_app_release_files WHERE release_id = ? ORDER BY relative_path ASC'
        );
        $st->execute([$releaseId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed>|null $context
     */
    public function log(
        ?int $releaseId,
        string $action,
        string $message,
        string $level = 'info',
        ?array $context = null,
        ?int $actorUserId = null
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO platform_app_deployment_logs
            (release_id, action, level, message, context_json, actor_user_id)
            VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $releaseId,
            $action,
            $level,
            $message,
            $context !== null ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            $actorUserId,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLogs(?int $releaseId = null, int $limit = 100): array
    {
        if ($releaseId !== null) {
            $st = $this->pdo->prepare(
                'SELECT * FROM platform_app_deployment_logs WHERE release_id = ? ORDER BY id DESC LIMIT ?'
            );
            $st->bindValue(1, $releaseId, PDO::PARAM_INT);
            $st->bindValue(2, max(1, $limit), PDO::PARAM_INT);
            $st->execute();
        } else {
            $st = $this->pdo->prepare(
                'SELECT * FROM platform_app_deployment_logs ORDER BY id DESC LIMIT ?'
            );
            $st->bindValue(1, max(1, $limit), PDO::PARAM_INT);
            $st->execute();
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertBackup(int $releaseId, string $backupPath, ?string $previousVersion, int $fileCount): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO platform_app_deployment_backups (release_id, backup_path, previous_version, file_count)
             VALUES (?, ?, ?, ?)'
        );
        $st->execute([$releaseId, $backupPath, $previousVersion, $fileCount]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findLatestBackupForRelease(int $releaseId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM platform_app_deployment_backups WHERE release_id = ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$releaseId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function tryAcquireLock(?int $userId, ?int $releaseId, int $ttlSeconds = 1800): bool
    {
        $now = new \DateTimeImmutable('now');
        $expires = $now->modify('+' . max(60, $ttlSeconds) . ' seconds')->format('Y-m-d H:i:s');
        $nowStr = $now->format('Y-m-d H:i:s');

        $st = $this->pdo->prepare(
            'UPDATE platform_app_deployment_locks
             SET locked_by = ?, release_id = ?, locked_at = ?, expires_at = ?
             WHERE id = 1
               AND (locked_at IS NULL OR expires_at IS NULL OR expires_at < ?)'
        );
        $st->execute([$userId, $releaseId, $nowStr, $expires, $nowStr]);

        return $st->rowCount() > 0;
    }

    public function releaseLock(): void
    {
        $this->pdo->exec(
            'UPDATE platform_app_deployment_locks
             SET locked_by = NULL, release_id = NULL, locked_at = NULL, expires_at = NULL
             WHERE id = 1'
        );
    }

    public function lockStatus(): ?array
    {
        $st = $this->pdo->query('SELECT * FROM platform_app_deployment_locks WHERE id = 1 LIMIT 1');
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;

        return $row ?: null;
    }
}
