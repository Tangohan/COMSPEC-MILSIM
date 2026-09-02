<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SqlText;
use PDO;

class DocumentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** Liste des documents pour un tenant (côté utilisateur : status = published uniquement). */
    public function listForTenant(
        int $tenantId,
        ?int $categoryId = null,
        ?string $status = 'published',
        ?string $search = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $documentType = null,
        ?string $classificationLevel = null,
        ?string $sort = null
    ): array {
        $sql = 'SELECT d.*, dc.name AS category_name, dc.slug AS category_slug, dc.color AS category_color, dv.id AS version_id, dv.file_path, dv.mime_type, dv.size, dv.version_number
                FROM documents d
                LEFT JOIN document_categories dc ON dc.id = d.document_category_id
                LEFT JOIN document_versions dv ON dv.document_id = d.id AND dv.is_current = 1
                WHERE d.tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null) {
            $sql .= ' AND d.status = ?';
            $params[] = $status;
        }
        if ($categoryId !== null) {
            $sql .= ' AND d.document_category_id = ?';
            $params[] = $categoryId;
        }
        if ($search !== null && $search !== '') {
            $sql .= ' AND (d.title LIKE ? OR d.description LIKE ? OR d.short_description LIKE ?)';
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if ($entityType !== null && $entityId !== null) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM document_links dl WHERE dl.document_id = d.id AND dl.entity_type = ? AND dl.entity_id = ?
            )';
            $params[] = $entityType;
            $params[] = $entityId;
        }
        if ($documentType !== null && $documentType !== '') {
            $sql .= ' AND d.document_type = ?';
            $params[] = $documentType;
        }
        if ($classificationLevel !== null && $classificationLevel !== '') {
            $sql .= ' AND d.classification_level = ?';
            $params[] = $classificationLevel;
        }
        $order = match ($sort) {
            'title_desc' => 'd.title DESC',
            'updated_desc' => 'COALESCE(d.updated_at, d.created_at) DESC, d.title ASC',
            'updated_asc' => 'COALESCE(d.updated_at, d.created_at) ASC, d.title ASC',
            default => 'd.title ASC',
        };
        $sql .= ' ORDER BY ' . $order;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT d.*, dv.id AS version_id, dv.version_number, dv.file_path, dv.original_name, dv.mime_type, dv.size, dv.checksum
                FROM documents d
                LEFT JOIN document_versions dv ON dv.document_id = d.id AND dv.is_current = 1
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

    public function findBySlug(string $slug, int $tenantId): ?array
    {
        $slugEq = SqlText::equals($this->pdo, 'd.slug');
        $sql = 'SELECT d.*, dc.name AS category_name, dc.color AS category_color, dv.id AS version_id, dv.version_number, dv.file_path, dv.mime_type, dv.size, dv.checksum
                FROM documents d
                LEFT JOIN document_categories dc ON dc.id = d.document_category_id
                LEFT JOIN document_versions dv ON dv.document_id = d.id AND dv.is_current = 1
                WHERE d.tenant_id = ? AND ' . $slugEq;
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function slugExists(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        $slugEq = SqlText::equals($this->pdo, 'slug');
        $sql = 'SELECT 1 FROM documents WHERE tenant_id = ? AND ' . $slugEq;
        $params = [$tenantId, $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function slugify(string $title): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($title));
        return strtolower(trim($slug, '-') ?: 'document');
    }

    public function create(array $data): int
    {
        $uuid = $data['uuid'] ?? null;
        if ($uuid === null || $uuid === '') {
            $uuid = $this->pdo->query('SELECT LOWER(UUID()) as u')->fetch(PDO::FETCH_ASSOC)['u'] ?? null;
        }
        // Ordre des colonnes aligné sur la table `documents` (migration documentaire) ; created_at/updated_at via DEFAULT.
        $stmt = $this->pdo->prepare(
            'INSERT INTO documents (
                tenant_id, uuid, title, slug, short_description, document_type, description, document_category_id,
                classification_level, visibility_scope, owner_user_id, author_user_id, parent_document_id,
                relation_type, version_label, sort_order, current_file_id, formation_id, equipment_class_id, unit_id,
                operator_id, mission_id, effective_at, review_due_at, expires_at, download_allowed, print_allowed,
                locked, tags, inherit_parent_security, require_access_code, access_code_hash, require_account_signature, signature_mandatory_before_download, status, created_by
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?
            )'
        );
        $stmt->execute([
            (int) $data['tenant_id'],
            $uuid,
            $data['title'] ?? '',
            $data['slug'] ?? '',
            $data['short_description'] ?? null,
            $data['document_type'] ?? null,
            $data['description'] ?? null,
            isset($data['document_category_id']) && $data['document_category_id'] !== '' ? (int) $data['document_category_id'] : null,
            $data['classification_level'] ?? 'interne',
            $data['visibility_scope'] ?? 'private',
            isset($data['owner_user_id']) && $data['owner_user_id'] !== '' ? (int) $data['owner_user_id'] : null,
            isset($data['author_user_id']) && $data['author_user_id'] !== '' ? (int) $data['author_user_id'] : null,
            isset($data['parent_document_id']) && $data['parent_document_id'] !== '' ? (int) $data['parent_document_id'] : null,
            $data['relation_type'] ?? null,
            $data['version_label'] ?? null,
            isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            isset($data['current_file_id']) && $data['current_file_id'] !== '' ? (int) $data['current_file_id'] : null,
            isset($data['formation_id']) && $data['formation_id'] !== '' ? (int) $data['formation_id'] : null,
            isset($data['equipment_class_id']) && $data['equipment_class_id'] !== '' ? (int) $data['equipment_class_id'] : null,
            isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null,
            isset($data['operator_id']) && $data['operator_id'] !== '' ? (int) $data['operator_id'] : null,
            isset($data['mission_id']) ? (string) $data['mission_id'] : null,
            isset($data['effective_at']) && $data['effective_at'] !== '' ? $data['effective_at'] : null,
            isset($data['review_due_at']) && $data['review_due_at'] !== '' ? $data['review_due_at'] : null,
            isset($data['expires_at']) && $data['expires_at'] !== '' ? $data['expires_at'] : null,
            isset($data['download_allowed']) ? (int) (bool) $data['download_allowed'] : 1,
            isset($data['print_allowed']) ? (int) (bool) $data['print_allowed'] : 1,
            isset($data['locked']) ? (int) (bool) $data['locked'] : 0,
            isset($data['tags']) ? (is_string($data['tags']) ? $data['tags'] : json_encode($data['tags'])) : null,
            isset($data['inherit_parent_security']) ? (int) (bool) $data['inherit_parent_security'] : 0,
            isset($data['require_access_code']) ? (int) (bool) $data['require_access_code'] : 0,
            isset($data['access_code_hash']) && $data['access_code_hash'] !== '' ? (string) $data['access_code_hash'] : null,
            isset($data['require_account_signature']) ? (int) (bool) $data['require_account_signature'] : 0,
            isset($data['signature_mandatory_before_download']) ? (int) (bool) $data['signature_mandatory_before_download'] : 1,
            $data['status'] ?? 'draft',
            isset($data['created_by']) ? (int) $data['created_by'] : null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if (array_key_exists('origin', $data) || array_key_exists('authored_json', $data)) {
            $this->persistManuscript($id, (int) $data['tenant_id'], $data);
        }

        return $id;
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $allowed = [
            'title', 'slug', 'short_description', 'description', 'document_type', 'document_category_id',
            'classification_level', 'visibility_scope', 'status', 'owner_user_id', 'author_user_id',
            'parent_document_id', 'relation_type', 'version_label', 'sort_order', 'current_file_id',
            'formation_id', 'equipment_class_id', 'unit_id', 'operator_id', 'mission_id',
            'effective_at', 'review_due_at', 'expires_at', 'download_allowed', 'print_allowed',
            'locked', 'tags', 'inherit_parent_security', 'require_access_code', 'access_code_hash', 'require_account_signature', 'signature_mandatory_before_download', 'origin', 'authored_json', 'updated_at',
        ];
        $fields = [];
        $params = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $fields[] = $key . ' = ?';
            if (in_array($key, ['document_category_id', 'owner_user_id', 'author_user_id', 'parent_document_id', 'current_file_id', 'formation_id', 'equipment_class_id', 'unit_id', 'operator_id'], true)) {
                $params[] = $data[$key] !== null && $data[$key] !== '' ? (int) $data[$key] : null;
            } elseif (in_array($key, ['download_allowed', 'print_allowed', 'locked', 'inherit_parent_security', 'require_access_code', 'require_account_signature', 'signature_mandatory_before_download'], true)) {
                $params[] = (int) (bool) $data[$key];
            } elseif ($key === 'tags') {
                $params[] = is_array($data[$key]) ? json_encode($data[$key]) : (isset($data[$key]) && $data[$key] !== '' ? (string) $data[$key] : null);
            } else {
                $params[] = $data[$key];
            }
        }
        if (empty($fields)) {
            return true;
        }
        if (!in_array('updated_at', array_keys($data), true)) {
            $fields[] = 'updated_at = NOW()';
        }
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare('UPDATE documents SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function persistManuscript(int $id, int $tenantId, array $data): void
    {
        $fields = [];
        $params = [];
        if (array_key_exists('origin', $data)) {
            $fields[] = 'origin = ?';
            $params[] = (string) $data['origin'] !== '' ? (string) $data['origin'] : 'upload';
        }
        if (array_key_exists('authored_json', $data)) {
            $fields[] = 'authored_json = ?';
            $json = $data['authored_json'];
            $params[] = $json === null || $json === '' ? null : (is_string($json) ? $json : json_encode($json));
        }
        if ($fields === []) {
            return;
        }
        $params[] = $id;
        $params[] = $tenantId;
        try {
            $stmt = $this->pdo->prepare('UPDATE documents SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ? AND tenant_id = ?');
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if (($data['origin'] ?? '') === 'authored') {
                throw $e;
            }
        }
    }

    public function getVersions(int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_versions WHERE document_id = ? ORDER BY version_number DESC'
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCurrentVersion(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_versions WHERE document_id = ? AND is_current = 1 LIMIT 1'
        );
        $stmt->execute([$documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param int[] $ids
     * @return list<array{id: int, title: string, slug: string}> */
    public function findPublishedByIds(array $ids, int $tenantId): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$tenantId, 'published'], $ids);
        $stmt = $this->pdo->prepare(
            "SELECT id, title, slug FROM documents WHERE tenant_id = ? AND status = ? AND id IN ($placeholders) ORDER BY title ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array> Enfants directs (via document_relations) avec infos document. */
    public function listChildren(int $documentId, ?int $tenantId = null): array
    {
        $sql = 'SELECT d.*, dr.relation_type, dr.sort_order
                FROM document_relations dr
                JOIN documents d ON d.id = dr.child_document_id
                WHERE dr.parent_document_id = ?';
        $params = [$documentId];
        if ($tenantId !== null) {
            $sql .= ' AND d.tenant_id = ?';
            $params[] = $tenantId;
        }
        $sql .= ' ORDER BY dr.sort_order ASC, d.title ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array|null Parent direct (via document_relations ou parent_document_id). */
    public function getParent(int $documentId, ?int $tenantId = null): ?array
    {
        $doc = $this->findById($documentId, $tenantId);
        if ($doc && !empty($doc['parent_document_id'])) {
            return $this->findById((int) $doc['parent_document_id'], $tenantId);
        }
        $stmt = $this->pdo->prepare('SELECT parent_document_id FROM document_relations WHERE child_document_id = ? LIMIT 1');
        $stmt->execute([$documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['parent_document_id'])) {
            return $this->findById((int) $row['parent_document_id'], $tenantId);
        }
        return null;
    }

    /** Liste des documents pour sélection parent (recherche par titre). */
    public function searchForParent(int $tenantId, string $search = '', ?int $excludeId = null, int $limit = 50): array
    {
        $sql = 'SELECT id, title, slug FROM documents WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($search !== '') {
            $sql .= ' AND (' . SqlText::like($this->pdo, 'title') . ' OR ' . SqlText::like($this->pdo, 'slug') . ')';
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
        }
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $sql .= ' ORDER BY title ASC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Suppression stricte (cascade document_versions). Usage : annulation création document après échec upload. */
    public function deleteHard(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM documents WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function countPublishedForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM documents WHERE tenant_id = ? AND status = ?');
        $stmt->execute([$tenantId, 'published']);

        return (int) $stmt->fetchColumn();
    }

    /** Dernière activité sur le registre (création ou mise à jour). */
    public function latestActivityAtForTenant(int $tenantId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT MAX(COALESCE(updated_at, created_at)) AS m FROM documents WHERE tenant_id = ?'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row['m']) ? (string) $row['m'] : null;
    }
}
