<?php

declare(strict_types=1);

namespace App\Repositories\Courrier;

use App\Core\Database;
use PDO;

class CourrierDocumentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(
        int $tenantId,
        ?string $status = null,
        ?int $templateId = null,
        ?int $createdBy = null,
        ?string $search = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $sql = 'SELECT d.*, t.name AS template_name, t.slug AS template_slug, p.name AS preset_name
                FROM courrier_documents d
                LEFT JOIN document_templates t ON t.id = d.template_id
                LEFT JOIN document_presets p ON p.id = d.preset_id
                WHERE d.tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND d.status = ?';
            $params[] = $status;
        }
        if ($templateId !== null) {
            $sql .= ' AND d.template_id = ?';
            $params[] = $templateId;
        }
        if ($createdBy !== null) {
            $sql .= ' AND d.created_by = ?';
            $params[] = $createdBy;
        }
        if ($search !== null && $search !== '') {
            $sql .= ' AND (d.title LIKE ? OR d.reference_number LIKE ? OR d.subject LIKE ?)';
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        $sql .= ' ORDER BY d.updated_at DESC, d.created_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByStatus(int $tenantId, ?string $status = null): int
    {
        $sql = 'SELECT COUNT(*) FROM courrier_documents WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countCreatedToday(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM courrier_documents WHERE tenant_id = ? AND DATE(created_at) = CURDATE()');
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT d.*, t.name AS template_name, t.slug AS template_slug, t.body_template AS template_body, p.name AS preset_name, p.code AS preset_code
                FROM courrier_documents d
                LEFT JOIN document_templates t ON t.id = d.template_id
                LEFT JOIN document_presets p ON p.id = d.preset_id
                WHERE d.id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND d.tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUuid(string $uuid, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM courrier_documents WHERE uuid = ?';
        $params = [$uuid];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $uuid = $data['uuid'] ?? null;
        if ($uuid === null) {
            $stmt = $this->pdo->query('SELECT LOWER(UUID())');
            $uuid = $stmt->fetchColumn();
        }
        $sql = 'INSERT INTO courrier_documents (uuid, tenant_id, template_id, preset_id, type, status, title, reference_number, subject,
                destination_label, issuer_label, body_rendered, variables_json, metadata_json, attachments_json, classification_level,
                moderation_state, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $uuid,
            $data['tenant_id'],
            $data['template_id'] ?? null,
            $data['preset_id'] ?? null,
            $data['type'] ?? null,
            $data['status'] ?? 'draft',
            $data['title'] ?? null,
            $data['reference_number'] ?? null,
            $data['subject'] ?? null,
            $data['destination_label'] ?? null,
            $data['issuer_label'] ?? null,
            $data['body_rendered'] ?? null,
            isset($data['variables_json']) ? (is_string($data['variables_json']) ? $data['variables_json'] : json_encode($data['variables_json'])) : null,
            isset($data['metadata_json']) ? (is_string($data['metadata_json']) ? $data['metadata_json'] : json_encode($data['metadata_json'])) : null,
            isset($data['attachments_json']) ? (is_string($data['attachments_json']) ? $data['attachments_json'] : json_encode($data['attachments_json'])) : null,
            $data['classification_level'] ?? 'interne',
            $data['moderation_state'] ?? null,
            $data['created_by'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $allowed = ['template_id', 'preset_id', 'type', 'status', 'title', 'reference_number', 'subject', 'destination_label', 'issuer_label',
            'body_rendered', 'variables_json', 'metadata_json', 'attachments_json', 'classification_level', 'moderation_state', 'validated_by', 'signed_by',
            'signed_at', 'signature_data_json', 'content_hash', 'sent_at', 'archived_at', 'updated_at'];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $fields[] = "`$k` = ?";
            $val = $data[$k];
            if (in_array($k, ['variables_json', 'metadata_json', 'attachments_json', 'signature_data_json'], true) && is_array($val)) {
                $val = json_encode($val);
            }
            $params[] = $val;
        }
        if (empty($fields)) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE courrier_documents SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    public function createVersion(int $documentId, array $snapshot, ?int $createdBy): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM courrier_document_versions WHERE document_id = ?');
        $stmt->execute([$documentId]);
        $versionNumber = (int) $stmt->fetchColumn();
        $stmt = $this->pdo->prepare('INSERT INTO courrier_document_versions (document_id, version_number, snapshot_json, created_by, created_at)
                VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$documentId, $versionNumber, json_encode($snapshot), $createdBy]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getVersions(int $documentId): array
    {
        $stmt = $this->pdo->prepare('SELECT v.*, u.display_name AS created_by_name
                FROM courrier_document_versions v
                LEFT JOIN users u ON u.id = v.created_by
                WHERE v.document_id = ?
                ORDER BY v.version_number DESC');
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNextReferenceNumber(int $tenantId, string $prefix = 'CR', ?string $year = null): string
    {
        $year = $year ?? date('Y');
        $stmt = $this->pdo->prepare('SELECT reference_number FROM courrier_documents WHERE tenant_id = ? AND reference_number LIKE ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$tenantId, $prefix . '-' . $year . '-%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $prefix . '-' . $year . '-0001';
        }
        $ref = $row['reference_number'];
        if (preg_match('/-(\d+)$/', $ref, $m)) {
            $num = (int) $m[1] + 1;
            return $prefix . '-' . $year . '-' . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
        }
        return $prefix . '-' . $year . '-0001';
    }

    /**
     * Documents du tenant où l’utilisateur est impliqué (rédacteur, validateur ou signataire).
     *
     * @return list<array<string,mixed>>
     */
    public function listForUserInvolvement(
        int $tenantId,
        int $userId,
        ?string $status = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $sql = 'SELECT d.*, t.name AS template_name, t.slug AS template_slug, p.name AS preset_name
                FROM courrier_documents d
                LEFT JOIN document_templates t ON t.id = d.template_id
                LEFT JOIN document_presets p ON p.id = d.preset_id
                WHERE d.tenant_id = ?
                  AND (d.created_by = ? OR d.signed_by = ? OR d.validated_by = ?)';
        $params = [$tenantId, $userId, $userId, $userId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND d.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY d.updated_at DESC, d.created_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
