<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Moderation\ModerationSourceType;
use PDO;

class ModerationArtifactRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** Mémoïsé pour la durée de la requête (barre latérale et bandeau de modération). */
    private static ?bool $tableExistsCache = null;

    public function tableExists(): bool
    {
        if (self::$tableExistsCache === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_artifacts' LIMIT 1");
            self::$tableExistsCache = (bool) $stmt?->fetchColumn();
        }

        return self::$tableExistsCache;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO moderation_artifacts (tenant_id, user_id, source_type, source_id, source_key, file_path, original_name, mime, sha256,
                state, risk_score, reason_codes, scan_log, ruleset_version, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $reasonCodes = $data['reason_codes'] ?? [];
        $scanLog = $data['scan_log'] ?? [];
        $stmt->execute([
            $tenantId,
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            $data['source_type'],
            (int) ($data['source_id'] ?? 0),
            $data['source_key'] ?? null,
            $data['file_path'] ?? null,
            $data['original_name'] ?? null,
            $data['mime'] ?? null,
            $data['sha256'] ?? null,
            $data['state'],
            (int) ($data['risk_score'] ?? 0),
            is_string($reasonCodes) ? $reasonCodes : json_encode($reasonCodes, JSON_UNESCAPED_UNICODE),
            is_string($scanLog) ? $scanLog : json_encode($scanLog, JSON_UNESCAPED_UNICODE),
            $data['ruleset_version'] ?? null,
            $data['expires_at'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteBySource(int $tenantId, string $sourceType, int $sourceId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM moderation_artifacts WHERE tenant_id = ? AND source_type = ? AND source_id = ?');
        $stmt->execute([$tenantId, $sourceType, $sourceId]);
    }

    public function markApprovedOverride(int $id, int $tenantId, int $sourceId, string $newFilePath, string $state = 'approved_override'): bool
    {
        $stmt = $this->pdo->prepare('UPDATE moderation_artifacts SET state = ?, source_id = ?, file_path = ? WHERE id = ? AND tenant_id = ?');

        return $stmt->execute([$state, $sourceId, $newFilePath, $id, $tenantId]) && $stmt->rowCount() > 0;
    }

    public function updateState(int $id, int $tenantId, string $state, ?int $riskScore = null): bool
    {
        $sql = 'UPDATE moderation_artifacts SET state = ?';
        $params = [$state];
        if ($riskScore !== null) {
            $sql .= ', risk_score = ?';
            $params[] = $riskScore;
        }
        $sql .= ' WHERE id = ? AND tenant_id = ?';
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params) && $stmt->rowCount() > 0;
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM moderation_artifacts WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByDocumentVersionId(int $versionId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM moderation_artifacts WHERE source_type = 'document_version' AND source_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$versionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCourrierDocumentId(int $courrierDocId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM moderation_artifacts WHERE source_type = 'courrier_document' AND source_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$courrierDocId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listQueue(int $tenantId, ?string $state, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $params = [$tenantId];
        $sql = 'SELECT * FROM moderation_artifacts WHERE tenant_id = ?';
        if ($state !== null && $state !== '') {
            $sql .= ' AND state = ?';
            $params[] = $state;
        } else {
            $sql .= " AND state IN ('quarantined','pending_scan')";
        }
        $sql .= ' ORDER BY risk_score DESC, created_at ASC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countQueue(int $tenantId, ?string $state): int
    {
        $params = [$tenantId];
        $sql = 'SELECT COUNT(*) FROM moderation_artifacts WHERE tenant_id = ?';
        if ($state !== null && $state !== '') {
            $sql .= ' AND state = ?';
            $params[] = $state;
        } else {
            $sql .= " AND state IN ('quarantined','pending_scan')";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Fichiers / pièces en attente de décision, toutes communautés. */
    public function countPendingQueueAllTenants(): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM moderation_artifacts WHERE state IN ('quarantined','pending_scan')"
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{tenant_id: int, pending: int, tenant_name: string|null}>
     */
    public function pendingQueueTopTenants(int $limit): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $limit = max(1, min(25, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT ma.tenant_id AS tenant_id, COUNT(*) AS pending, MAX(t.name) AS tenant_name
             FROM moderation_artifacts ma
             LEFT JOIN tenants t ON t.id = ma.tenant_id
             WHERE ma.state IN (\'quarantined\', \'pending_scan\')
             GROUP BY ma.tenant_id
             ORDER BY pending DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $r): array {
            return [
                'tenant_id' => (int) ($r['tenant_id'] ?? 0),
                'pending' => (int) ($r['pending'] ?? 0),
                'tenant_name' => isset($r['tenant_name']) && $r['tenant_name'] !== '' && $r['tenant_name'] !== null
                    ? (string) $r['tenant_name']
                    : null,
            ];
        }, $rows);
    }

    /**
     * Artefacts expirés (quarantaine TTL).
     *
     * @return list<array<string, mixed>>
     */
    public function findExpiredPending(\DateTimeInterface $before): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM moderation_artifacts WHERE state IN ('quarantined','pending_scan') AND expires_at IS NOT NULL AND expires_at < ?"
        );
        $stmt->execute([$before->format('Y-m-d H:i:s')]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
