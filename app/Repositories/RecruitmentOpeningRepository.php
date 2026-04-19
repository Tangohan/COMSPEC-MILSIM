<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Recruitment\RecruitmentOpeningReferenceService;
use App\Services\Recruitment\TenantRecruitmentSettings;
use PDO;
use Throwable;

class RecruitmentOpeningRepository
{
    private PDO $pdo;

    private static ?bool $tableExists = null;

    public function __construct(
        private RecruitmentOpeningReferenceService $referenceService = new RecruitmentOpeningReferenceService()
    ) {
        $this->pdo = Database::getPdo();
    }

    public function tablesExist(): bool
    {
        if (self::$tableExists === null) {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_openings' LIMIT 1");
            self::$tableExists = $st && (bool) $st->fetchColumn();
        }

        return self::$tableExists;
    }

    /**
     * Nettoie le segment d’URL « avis » (copier-coller depuis du HTML, encodage partiel, etc.).
     */
    public static function normalizePublicPageSlugFromRequest(string $raw): string
    {
        $s = trim(str_replace('+', ' ', rawurldecode($raw)));
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = trim($s);
        for ($i = 0; $i < 12; $i++) {
            $before = $s;
            $s = preg_replace('/&(quot|#0*34);+$/iu', '', $s) ?? '';
            $s = preg_replace('/&quot$/iu', '', $s) ?? '';
            $s = preg_replace('/%22+$/i', '', $s) ?? '';
            $s = rtrim($s, "\"'<>% \t\n\r");
            if ($s === $before) {
                break;
            }
        }

        return trim($s);
    }

    /** @return list<array<string, mixed>> */
    public function listPublishedForTenant(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $sql = 'SELECT ro.*, u.name AS unit_name, u.slug AS unit_slug, u.code AS unit_code
                FROM recruitment_openings ro
                INNER JOIN units u ON u.id = ro.unit_id AND u.tenant_id = ro.tenant_id
                WHERE ro.tenant_id = ? AND ro.status = \'published\'
                ORDER BY ro.published_at DESC, ro.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listForTenantAdmin(int $tenantId, ?string $statusFilter = null): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $sql = 'SELECT ro.*, u.name AS unit_name
                FROM recruitment_openings ro
                INNER JOIN units u ON u.id = ro.unit_id AND u.tenant_id = ro.tenant_id
                WHERE ro.tenant_id = ?';
        $params = [$tenantId];
        if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
            $sql .= ' AND ro.status = ?';
            $params[] = $statusFilter;
        }
        $sql .= ' ORDER BY ro.updated_at DESC, ro.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        if (!$this->tablesExist()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT ro.*, u.name AS unit_name, u.slug AS unit_slug, u.code AS unit_code
             FROM recruitment_openings ro
             INNER JOIN units u ON u.id = ro.unit_id AND u.tenant_id = ro.tenant_id
             WHERE ro.id = ? AND ro.tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPublishedByPublicSlug(int $tenantId, string $slug): ?array
    {
        $slug = self::normalizePublicPageSlugFromRequest($slug);
        if (!$this->tablesExist() || $slug === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT ro.*, u.name AS unit_name, u.slug AS unit_slug, u.code AS unit_code
             FROM recruitment_openings ro
             INNER JOIN units u ON u.id = ro.unit_id AND u.tenant_id = ro.tenant_id
             WHERE ro.tenant_id = ? AND ro.public_page_slug = ? AND ro.status = \'published\' LIMIT 1'
        );
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function publicSlugExists(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        if (!$this->tablesExist() || $slug === '') {
            return false;
        }
        $sql = 'SELECT 1 FROM recruitment_openings WHERE tenant_id = ? AND public_page_slug = ?';
        $params = [$tenantId, $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $tenant row
     * @param array<string, mixed> $tenantSettings decoded settings
     */
    /** Dernier numéro d’ordre enregistré pour l’année (0 si aucune ligne). Lecture seule, sans réserver le suivant. */
    public function currentLastSeq(int $tenantId, int $year): int
    {
        if (!$this->tablesExist()) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT last_seq FROM recruitment_opening_counters WHERE tenant_id = ? AND year = ? LIMIT 1');
        $stmt->execute([$tenantId, $year]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 0;
        }

        return max(0, (int) ($row['last_seq'] ?? 0));
    }

    public function allocateNextSeq(int $tenantId, int $year): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT last_seq FROM recruitment_opening_counters WHERE tenant_id = ? AND year = ? FOR UPDATE');
            $stmt->execute([$tenantId, $year]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $next = (int) $row['last_seq'] + 1;
                $this->pdo->prepare('UPDATE recruitment_opening_counters SET last_seq = ? WHERE tenant_id = ? AND year = ?')
                    ->execute([$next, $tenantId, $year]);
            } else {
                $next = 1;
                $this->pdo->prepare('INSERT INTO recruitment_opening_counters (tenant_id, year, last_seq) VALUES (?, ?, ?)')
                    ->execute([$tenantId, $year, $next]);
            }
            $this->pdo->commit();

            return $next;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recruitment_openings (
                tenant_id, unit_id, created_by_user_id, personnel_job_role_id, title, summary, description,
                requirements_json, employment_contract_label, employment_context_label,
                personnel_category, arm_domain, clearance_level,
                candidate_profile_items, technical_notice, mission_lead, responsibility_blocks,
                status, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'draft\', NOW(), NOW()
            )'
        );
        $stmt->execute([
            $tenantId,
            (int) $data['unit_id'],
            $userId,
            !empty($data['personnel_job_role_id']) ? (int) $data['personnel_job_role_id'] : null,
            (string) $data['title'],
            $data['summary'] !== null && $data['summary'] !== '' ? (string) $data['summary'] : null,
            $data['description'] !== null && $data['description'] !== '' ? (string) $data['description'] : null,
            $this->jsonOrNull($data['requirements_json'] ?? null),
            $data['employment_contract_label'] !== null && $data['employment_contract_label'] !== '' ? (string) $data['employment_contract_label'] : null,
            $data['employment_context_label'] !== null && $data['employment_context_label'] !== '' ? (string) $data['employment_context_label'] : null,
            (string) ($data['personnel_category'] ?? 'other'),
            isset($data['arm_domain']) && $data['arm_domain'] !== '' ? (string) $data['arm_domain'] : null,
            (string) ($data['clearance_level'] ?? 'none'),
            $this->jsonOrNull($data['candidate_profile_items'] ?? null),
            $data['technical_notice'] !== null && $data['technical_notice'] !== '' ? (string) $data['technical_notice'] : null,
            $data['mission_lead'] !== null && $data['mission_lead'] !== '' ? (string) $data['mission_lead'] : null,
            $this->jsonOrNull($data['responsibility_blocks'] ?? null),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE recruitment_openings SET
                unit_id = ?, personnel_job_role_id = ?, title = ?, summary = ?, description = ?,
                requirements_json = ?, employment_contract_label = ?, employment_context_label = ?,
                personnel_category = ?, arm_domain = ?, clearance_level = ?,
                candidate_profile_items = ?, technical_notice = ?, mission_lead = ?, responsibility_blocks = ?,
                updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = \'draft\''
        );
        $stmt->execute([
            (int) $data['unit_id'],
            !empty($data['personnel_job_role_id']) ? (int) $data['personnel_job_role_id'] : null,
            (string) $data['title'],
            $data['summary'] !== null && $data['summary'] !== '' ? (string) $data['summary'] : null,
            $data['description'] !== null && $data['description'] !== '' ? (string) $data['description'] : null,
            $this->jsonOrNull($data['requirements_json'] ?? null),
            $data['employment_contract_label'] !== null && $data['employment_contract_label'] !== '' ? (string) $data['employment_contract_label'] : null,
            $data['employment_context_label'] !== null && $data['employment_context_label'] !== '' ? (string) $data['employment_context_label'] : null,
            (string) ($data['personnel_category'] ?? 'other'),
            isset($data['arm_domain']) && $data['arm_domain'] !== '' ? (string) $data['arm_domain'] : null,
            (string) ($data['clearance_level'] ?? 'none'),
            $this->jsonOrNull($data['candidate_profile_items'] ?? null),
            $data['technical_notice'] !== null && $data['technical_notice'] !== '' ? (string) $data['technical_notice'] : null,
            $data['mission_lead'] !== null && $data['mission_lead'] !== '' ? (string) $data['mission_lead'] : null,
            $this->jsonOrNull($data['responsibility_blocks'] ?? null),
            $id,
            $tenantId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $tenant
     * @param array<string, mixed> $tenantSettings
     * @param array<string, mixed> $unit
     */
    public function publish(int $id, int $tenantId, array $tenant, array $tenantSettings, array $unit): bool
    {
        $row = $this->findByIdForTenant($id, $tenantId);
        if (!$row || ($row['status'] ?? '') !== 'draft') {
            return false;
        }
        $year = (int) date('Y');
        $format = TenantRecruitmentSettings::referenceFormatFromSettings($tenantSettings);
        $seq = $this->allocateNextSeq($tenantId, $year);
        $reference = $this->referenceService->buildReference($format, $tenant, $unit, $year, $seq, $row);
        $baseSlug = $this->referenceService->slugFromReference($reference);
        $slug = $baseSlug;
        $n = 2;
        while ($this->publicSlugExists($tenantId, $slug, $id)) {
            $slug = substr($baseSlug, 0, 90) . '-' . $n;
            $n++;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE recruitment_openings SET status = \'published\', reference_public = ?, public_page_slug = ?,
             published_at = NOW(), closed_at = NULL, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = \'draft\''
        );
        $stmt->execute([$reference, $slug, $id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function close(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE recruitment_openings SET status = \'closed\', closed_at = NOW(), updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = \'published\''
        );
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRelatedPublished(int $tenantId, array $current, int $limit = 5): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $id = (int) ($current['id'] ?? 0);
        $pjr = !empty($current['personnel_job_role_id']) ? (int) $current['personnel_job_role_id'] : 0;
        $arm = (string) ($current['arm_domain'] ?? '');
        $sql = 'SELECT ro.*, u.name AS unit_name, u.slug AS unit_slug, u.code AS unit_code
                FROM recruitment_openings ro
                INNER JOIN units u ON u.id = ro.unit_id AND u.tenant_id = ro.tenant_id
                WHERE ro.tenant_id = ? AND ro.status = \'published\' AND ro.id <> ?';
        $params = [$tenantId, $id];
        if ($pjr > 0) {
            $sql .= ' AND ro.personnel_job_role_id = ?';
            $params[] = $pjr;
        } elseif ($arm !== '') {
            $sql .= ' AND ro.arm_domain = ?';
            $params[] = $arm;
        }
        $sql .= ' ORDER BY ro.published_at DESC LIMIT ' . (int) max(1, min(20, $limit));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $out = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($out) >= $limit) {
            return array_slice($out, 0, $limit);
        }
        $need = $limit - count($out);
        $ids = array_column($out, 'id');
        $ids[] = $id;
        $place = implode(',', array_fill(0, count($ids), '?'));
        $sql2 = "SELECT ro.*, u.name AS unit_name, u.slug AS unit_slug, u.code AS unit_code
                 FROM recruitment_openings ro
                 INNER JOIN units u ON u.id = ro.unit_id AND u.tenant_id = ro.tenant_id
                 WHERE ro.tenant_id = ? AND ro.status = 'published' AND ro.id NOT IN ($place)
                 ORDER BY ro.published_at DESC LIMIT " . (int) $need;
        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute(array_merge([$tenantId], $ids));
        $extra = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_slice(array_merge($out, $extra), 0, $limit);
    }

    public function maxUpdatedAtPublished(int $tenantId): ?string
    {
        if (!$this->tablesExist()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT MAX(updated_at) FROM recruitment_openings WHERE tenant_id = ? AND status = \'published\''
        );
        $stmt->execute([$tenantId]);
        $v = $stmt->fetchColumn();

        return $v ? (string) $v : null;
    }

    public function recruitmentForumLinkColumnsExist(): bool
    {
        if (!$this->tablesExist()) {
            return false;
        }

        return $this->hasColumn('forum_topic_id_externe') && $this->hasColumn('forum_topic_id_interne');
    }

    public function setForumTopicExterne(int $id, int $tenantId, int $topicId): bool
    {
        if (!$this->hasColumn('forum_topic_id_externe')) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE recruitment_openings SET forum_topic_id_externe = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        );

        return $stmt->execute([$topicId, $id, $tenantId]) && $stmt->rowCount() > 0;
    }

    public function setForumTopicInterne(int $id, int $tenantId, int $topicId): bool
    {
        if (!$this->hasColumn('forum_topic_id_interne')) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE recruitment_openings SET forum_topic_id_interne = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        );

        return $stmt->execute([$topicId, $id, $tenantId]) && $stmt->rowCount() > 0;
    }

    private function hasColumn(string $name): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_openings' AND COLUMN_NAME = " . $this->pdo->quote($name) . ' LIMIT 1'
            );

            return $st && (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function jsonOrNull(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (is_string($v) && $v === '') {
            return null;
        }
        if (is_array($v)) {
            if ($v === []) {
                return null;
            }

            return json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        return null;
    }
}
