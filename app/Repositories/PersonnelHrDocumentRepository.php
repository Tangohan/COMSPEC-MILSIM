<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Documents RH typés rattachés au dossier individuel.
 */
final class PersonnelHrDocumentRepository
{
    /** @var list<string> */
    public const DOC_TYPES = [
        'candidature',
        'charte',
        'reglement',
        'certificat',
        'qualification',
        'affectation',
        'evaluation',
        'autre',
    ];

    /** @var array<string, string> */
    public const DOC_TYPE_LABELS = [
        'candidature' => 'Dossier de candidature',
        'charte' => 'Charte signée',
        'reglement' => 'Règlement',
        'certificat' => 'Certificat interne',
        'qualification' => 'Qualification',
        'affectation' => 'Décision d’affectation',
        'evaluation' => 'Évaluation',
        'autre' => 'Autre document',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tableExists(): bool
    {
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_hr_documents' LIMIT 1"
        );

        return (bool) ($st && $st->fetchColumn());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId, bool $includeArchived = false, bool $memberVisibleOnly = false): array
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $where = 'tenant_id = ? AND user_id = ?';
        $params = [$tenantId, $userId];
        if (!$includeArchived) {
            $where .= ' AND archived_at IS NULL';
        }
        if ($memberVisibleOnly) {
            $where .= " AND visibility = 'MEMBER'";
        }
        $st = $this->pdo->prepare(
            "SELECT * FROM personnel_hr_documents WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT 200"
        );
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecentForTenant(int $tenantId, int $limit = 40): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $st = $this->pdo->prepare(
            "SELECT d.*, u.display_name AS user_display_name, u.email AS user_email
             FROM personnel_hr_documents d
             LEFT JOIN users u ON u.id = d.user_id
             WHERE d.tenant_id = ? AND d.archived_at IS NULL
             ORDER BY d.created_at DESC, d.id DESC
             LIMIT {$limit}"
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countForTenant(int $tenantId): int
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM personnel_hr_documents WHERE tenant_id = ? AND archived_at IS NULL'
        );
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, int $tenantId): ?array
    {
        if (!$this->tableExists() || $id < 1 || $tenantId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM personnel_hr_documents WHERE id = ? AND tenant_id = ? AND archived_at IS NULL LIMIT 1'
        );
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(
        int $tenantId,
        int $userId,
        string $docType,
        string $title,
        ?string $description,
        ?string $filePath,
        ?string $originalName,
        string $visibility,
        ?int $uploadedBy
    ): int {
        if (!$this->tableExists()) {
            return 0;
        }
        $docType = in_array($docType, self::DOC_TYPES, true) ? $docType : 'autre';
        $visibility = $visibility === 'MEMBER' ? 'MEMBER' : 'STAFF';
        $title = trim($title);
        if ($title === '') {
            $title = self::DOC_TYPE_LABELS[$docType] ?? 'Document RH';
        }
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_hr_documents
             (tenant_id, user_id, doc_type, title, description, file_path, original_name, visibility, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $tenantId,
            $userId,
            $docType,
            mb_substr($title, 0, 200),
            $description !== null && trim($description) !== '' ? trim($description) : null,
            $filePath,
            $originalName,
            $visibility,
            $uploadedBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function archive(int $id, int $tenantId): bool
    {
        if (!$this->tableExists() || $id < 1) {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE personnel_hr_documents SET archived_at = NOW()
             WHERE id = ? AND tenant_id = ? AND archived_at IS NULL'
        );
        $st->execute([$id, $tenantId]);

        return $st->rowCount() > 0;
    }
}
