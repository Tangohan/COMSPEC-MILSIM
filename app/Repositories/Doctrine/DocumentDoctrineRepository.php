<?php

declare(strict_types=1);

namespace App\Repositories\Doctrine;

use App\Core\Database;
use PDO;

final class DocumentDoctrineRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM document_doctrines LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function findByDocumentId(int $documentId, ?int $tenantId = null): ?array
    {
        if (!$this->tableExists() || $documentId < 1) {
            return null;
        }
        $sql = 'SELECT dd.*,
                       drd.label AS domain_label, drd.doc_prefix AS domain_prefix,
                       drs.label AS subdomain_label, drs.code AS subdomain_code,
                       ddl.label AS diffusion_label, ddl.code AS diffusion_code
                FROM document_doctrines dd
                LEFT JOIN document_reference_domains drd ON drd.id = dd.domain_id
                LEFT JOIN document_reference_subdomains drs ON drs.id = dd.subdomain_id
                LEFT JOIN document_diffusion_levels ddl ON ddl.id = dd.diffusion_level_id
                WHERE dd.document_id = ?';
        $params = [$documentId];
        if ($tenantId !== null) {
            $sql .= ' AND (dd.tenant_id = ? OR dd.scope = ?)';
            $params[] = $tenantId;
            $params[] = 'platform';
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        if (!$this->tableExists() || $id < 1) {
            return null;
        }
        $sql = 'SELECT dd.* FROM document_doctrines dd WHERE dd.id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND (dd.tenant_id = ? OR dd.scope = ?)';
            $params[] = $tenantId;
            $params[] = 'platform';
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Liste des doctrines publiées pour un tenant (tenant + plateforme).
     *
     * @return list<array<string, mixed>>
     */
    public function listPublishedForTenant(int $tenantId, ?string $quickFilter = null, ?string $search = null): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $sql = 'SELECT dd.*, d.title, d.slug, d.status AS document_status, d.updated_at AS document_updated_at,
                       d.scope AS document_scope, dc.slug AS category_slug,
                       dv.id AS version_id, dv.version_major, dv.version_minor, dv.version_label, dv.published_at AS version_published_at,
                       drd.label AS domain_label, drd.doc_prefix AS domain_prefix,
                       drs.code AS subdomain_code, drs.label AS subdomain_label,
                       ddl.label AS diffusion_label
                FROM document_doctrines dd
                INNER JOIN documents d ON d.id = dd.document_id
                LEFT JOIN document_categories dc ON dc.id = d.document_category_id
                LEFT JOIN document_versions dv ON dv.document_id = d.id AND dv.is_current = 1
                LEFT JOIN document_reference_domains drd ON drd.id = dd.domain_id
                LEFT JOIN document_reference_subdomains drs ON drs.id = dd.subdomain_id
                LEFT JOIN document_diffusion_levels ddl ON ddl.id = dd.diffusion_level_id
                WHERE dd.doctrine_status = ?
                  AND (dd.tenant_id = ? OR dd.scope = ?)';
        $params = ['published', $tenantId, 'platform'];

        if ($search !== null && trim($search) !== '') {
            $sql .= ' AND (dd.reference_code LIKE ? OR d.title LIKE ? OR dd.summary LIKE ? OR drd.label LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if ($quickFilter === 'archived') {
            $sql .= ' AND dd.doctrine_status IN (?, ?)';
            $params[] = 'archived';
            $params[] = 'obsolete';
        } elseif ($quickFilter === 'archived_only') {
            $sql .= ' AND dd.doctrine_status = ?';
            $params[] = 'archived';
        }

        $sql .= ' ORDER BY dd.reference_code ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function referenceExists(int $tenantId, string $reference, ?int $excludeDocumentId = null): bool
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return false;
        }
        $sql = 'SELECT 1 FROM document_doctrines WHERE tenant_id = ? AND reference_code = ?';
        $params = [$tenantId, strtoupper(trim($reference))];
        if ($excludeDocumentId !== null && $excludeDocumentId > 0) {
            $sql .= ' AND document_id != ?';
            $params[] = $excludeDocumentId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function nextSequenceNumber(int $tenantId, string $servicePrefix, string $domainCode, int $year): int
    {
        $servicePrefix = strtoupper(trim($servicePrefix));
        $domainCode = strtoupper(trim($domainCode));
        $this->pdo->beginTransaction();
        try {
            $sel = $this->pdo->prepare(
                'SELECT id, last_number FROM document_reference_sequences
                 WHERE tenant_id = ? AND service_prefix = ? AND domain_code = ? AND year = ?
                 FOR UPDATE'
            );
            $sel->execute([$tenantId, $servicePrefix, $domainCode, $year]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $next = (int) $row['last_number'] + 1;
                $upd = $this->pdo->prepare('UPDATE document_reference_sequences SET last_number = ? WHERE id = ?');
                $upd->execute([$next, (int) $row['id']]);
            } else {
                $next = 1;
                $ins = $this->pdo->prepare(
                    'INSERT INTO document_reference_sequences (tenant_id, service_prefix, domain_code, year, last_number)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $ins->execute([$tenantId, $servicePrefix, $domainCode, $year, $next]);
            }
            $this->pdo->commit();

            return $next;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_doctrines (
                document_id, tenant_id, scope, reference_code, service_prefix, domain_id, subdomain_id,
                domain_code, seq_year, seq_number, short_title, summary, doctrine_status, requirement_level,
                diffusion_level_id, issuing_authority_type, issuing_unit_id, issuing_job_role_id,
                issuing_user_id, issuing_label, approver_user_id, effective_at, expires_at,
                acknowledgment_required, acknowledgment_deadline_at, reading_required, include_future_members,
                replaced_by_document_id, replaces_document_id, keywords_json
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            (int) ($data['document_id'] ?? 0),
            isset($data['tenant_id']) ? (int) $data['tenant_id'] : null,
            (string) ($data['scope'] ?? 'tenant'),
            strtoupper(trim((string) ($data['reference_code'] ?? ''))),
            strtoupper(trim((string) ($data['service_prefix'] ?? ''))),
            isset($data['domain_id']) ? (int) $data['domain_id'] : null,
            isset($data['subdomain_id']) ? (int) $data['subdomain_id'] : null,
            isset($data['domain_code']) ? strtoupper(trim((string) $data['domain_code'])) : null,
            isset($data['seq_year']) ? (int) $data['seq_year'] : null,
            isset($data['seq_number']) ? (int) $data['seq_number'] : null,
            $data['short_title'] ?? null,
            $data['summary'] ?? null,
            (string) ($data['doctrine_status'] ?? 'draft'),
            (string) ($data['requirement_level'] ?? 'informative'),
            isset($data['diffusion_level_id']) ? (int) $data['diffusion_level_id'] : null,
            (string) ($data['issuing_authority_type'] ?? 'tenant'),
            isset($data['issuing_unit_id']) ? (int) $data['issuing_unit_id'] : null,
            isset($data['issuing_job_role_id']) ? (int) $data['issuing_job_role_id'] : null,
            isset($data['issuing_user_id']) ? (int) $data['issuing_user_id'] : null,
            $data['issuing_label'] ?? null,
            isset($data['approver_user_id']) ? (int) $data['approver_user_id'] : null,
            $data['effective_at'] ?? null,
            $data['expires_at'] ?? null,
            !empty($data['acknowledgment_required']) ? 1 : 0,
            $data['acknowledgment_deadline_at'] ?? null,
            !empty($data['reading_required']) ? 1 : 0,
            !isset($data['include_future_members']) || !empty($data['include_future_members']) ? 1 : 0,
            isset($data['replaced_by_document_id']) ? (int) $data['replaced_by_document_id'] : null,
            isset($data['replaces_document_id']) ? (int) $data['replaces_document_id'] : null,
            isset($data['keywords_json']) ? (is_string($data['keywords_json']) ? $data['keywords_json'] : json_encode($data['keywords_json'])) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $fields */
    public function updateByDocumentId(int $documentId, ?int $tenantId, array $fields): bool
    {
        $allowed = [
            'reference_code', 'service_prefix', 'domain_id', 'subdomain_id', 'domain_code', 'seq_year', 'seq_number',
            'short_title', 'summary', 'doctrine_status', 'requirement_level', 'diffusion_level_id',
            'issuing_authority_type', 'issuing_unit_id', 'issuing_job_role_id', 'issuing_user_id', 'issuing_label',
            'approver_user_id', 'effective_at', 'expires_at', 'acknowledgment_required', 'acknowledgment_deadline_at',
            'reading_required', 'include_future_members', 'replaced_by_document_id', 'replaces_document_id',
            'keywords_json', 'published_at',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $sets[] = $key . ' = ?';
                $params[] = $fields[$key];
            }
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $sql = 'UPDATE document_doctrines SET ' . implode(', ', $sets) . ' WHERE document_id = ?';
        $params[] = $documentId;
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }
}
