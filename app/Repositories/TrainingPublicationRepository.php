<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingPublicationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_document_publications (
                tenant_id, course_id, document_id, courrier_template_id, status, cover_asset_id,
                overlay_payload_json, watermark_payload_json, qr_payload_json, security_level, version_label,
                hash_integrity, is_revoked, access_policy_json, validation_chain_json, publication_targets_json,
                format_payload_json, institutional_signature_json, diffusion_classification, expires_at,
                compliance_score, created_by, updated_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );

        $stmt->execute([
            $data['tenant_id'],
            $data['course_id'],
            $data['document_id'] ?? null,
            $data['courrier_template_id'] ?? null,
            $data['status'] ?? 'draft',
            $data['cover_asset_id'] ?? null,
            $this->encode($data['overlay_payload_json'] ?? []),
            $this->encode($data['watermark_payload_json'] ?? []),
            $this->encode($data['qr_payload_json'] ?? []),
            $data['security_level'] ?? 'interne',
            $data['version_label'] ?? 'v1',
            $data['hash_integrity'] ?? null,
            (int) ($data['is_revoked'] ?? 0),
            $this->encode($data['access_policy_json'] ?? []),
            $this->encode($data['validation_chain_json'] ?? []),
            $this->encode($data['publication_targets_json'] ?? []),
            $this->encode($data['format_payload_json'] ?? []),
            $this->encode($data['institutional_signature_json'] ?? []),
            $data['diffusion_classification'] ?? 'interne',
            $data['expires_at'] ?? null,
            (int) ($data['compliance_score'] ?? 0),
            $data['created_by'] ?? null,
            $data['updated_by'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }


    public function listByTenant(int $tenantId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, c.title AS course_title, c.slug AS course_slug
            FROM training_document_publications p
            LEFT JOIN training_courses c ON c.id = p.course_id AND c.tenant_id = p.tenant_id
            WHERE p.tenant_id = ?
            ORDER BY p.updated_at DESC
            LIMIT ' . (int) $limit
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, c.title AS course_title, c.slug AS course_slug
            FROM training_document_publications p
            LEFT JOIN training_courses c ON c.id = p.course_id AND c.tenant_id = p.tenant_id
            WHERE p.id = ? AND p.tenant_id = ?
            LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $allowed = [
            'status', 'document_id', 'courrier_template_id', 'cover_asset_id',
            'overlay_payload_json', 'watermark_payload_json', 'qr_payload_json',
            'security_level', 'version_label', 'published_at', 'archived_at',
            'hash_integrity', 'is_revoked', 'access_policy_json', 'updated_by',
            'validation_chain_json', 'publication_targets_json', 'format_payload_json',
            'institutional_signature_json', 'expires_at', 'obsolete_at', 'replacement_publication_id',
            'compliance_score', 'diffusion_classification',
        ];
        $jsonColumns = [
            'overlay_payload_json', 'watermark_payload_json', 'qr_payload_json', 'access_policy_json',
            'validation_chain_json', 'publication_targets_json', 'format_payload_json', 'institutional_signature_json',
        ];

        $fields = [];
        $params = [];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $fields[] = $column . ' = ?';
            $value = $data[$column];
            if (in_array($column, $jsonColumns, true)) {
                $value = $this->encode($value);
            }
            $params[] = $value;
        }

        if ($fields === []) {
            return true;
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare('UPDATE training_document_publications SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?');

        return $stmt->execute($params);
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
