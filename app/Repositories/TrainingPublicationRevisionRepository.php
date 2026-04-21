<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingPublicationRevisionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(int $publicationId, int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(revision_number), 0) + 1 FROM training_document_publication_revisions WHERE publication_id = ? AND tenant_id = ?');
        $stmt->execute([$publicationId, $tenantId]);
        $revision = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO training_document_publication_revisions (
                publication_id, tenant_id, revision_number, change_summary, diff_payload_json,
                pdf_snapshot_path, compiled_payload_json, qr_hash, watermark_hash, integrity_check_passed,
                created_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)'
        );

        $stmt->execute([
            $publicationId,
            $tenantId,
            $revision,
            $data['change_summary'] ?? null,
            $this->encode($data['diff_payload_json'] ?? []),
            $data['pdf_snapshot_path'] ?? null,
            $this->encode($data['compiled_payload_json'] ?? []),
            $data['qr_hash'] ?? null,
            $data['watermark_hash'] ?? null,
            (int) ($data['integrity_check_passed'] ?? 1),
            $data['created_by'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function latestForPublication(int $publicationId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM training_document_publication_revisions
             WHERE publication_id = ? AND tenant_id = ?
             ORDER BY revision_number DESC LIMIT 1'
        );
        $stmt->execute([$publicationId, $tenantId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function previousForPublication(int $publicationId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM training_document_publication_revisions
             WHERE publication_id = ? AND tenant_id = ?
             ORDER BY revision_number DESC LIMIT 1 OFFSET 1'
        );
        $stmt->execute([$publicationId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listForPublication(int $publicationId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM training_document_publication_revisions
             WHERE publication_id = ? AND tenant_id = ?
             ORDER BY revision_number DESC'
        );
        $stmt->execute([$publicationId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
