<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingEnrollmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listByUserId(int $userId, ?int $tenantId = null): array
    {
        $sql = 'SELECT e.*, c.title AS course_title, c.slug AS course_slug, c.estimated_minutes, c.banner_path,
                       c.short_description, c.category, c.level, c.is_certifying, c.is_mandatory, c.thumbnail_path,
                       c.lms_scope AS course_lms_scope,
                       (SELECT id FROM training_certificates WHERE enrollment_id = e.id AND status = \'valid\' ORDER BY id DESC LIMIT 1) AS certificate_id
                FROM training_enrollments e
                JOIN training_courses c ON c.id = e.course_id
                WHERE e.user_id = ?';
        $params = [$userId];
        if ($tenantId !== null) {
            $sql .= ' AND (e.tenant_id = ? OR c.lms_scope = \'platform\')';
            $params[] = $tenantId;
        }
        $sql .= ' ORDER BY e.assigned_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Comptes agrégés (inscriptions totales / terminées) pour toutes les formations d’un tenant,
     * en une seule requête (tableau de bord admin, évite une requête par formation).
     *
     * @return array{total: int, completed: int}
     */
    public function countAndCompletedForTenantCourses(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total, SUM(CASE WHEN e.status = ? THEN 1 ELSE 0 END) AS completed
             FROM training_enrollments e
             INNER JOIN training_courses c ON c.id = e.course_id
             WHERE c.tenant_id = ?'
        );
        $stmt->execute(['completed', $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listByCourseId(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, u.email, u.display_name
             FROM training_enrollments e
             JOIN users u ON u.id = e.user_id
             WHERE e.course_id = ?
             ORDER BY e.assigned_at DESC'
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Apprenants dont l’inscription est encore active sur le parcours (pas terminée / retirée / expirée).
     * Une ligne par utilisateur (DISTINCT).
     *
     * @return list<array<string, mixed>>
     */
    public function listIncompleteLearnersForCourseSessionNotify(int $tenantId, int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT e.user_id, u.email, u.display_name, u.callsign
             FROM training_enrollments e
             INNER JOIN users u ON u.id = e.user_id
             WHERE e.course_id = ?
               AND e.tenant_id = ?
               AND e.status IN (\'assigned\', \'in_progress\', \'pending_approval\', \'failed\')
             ORDER BY e.user_id ASC'
        );
        $stmt->execute([$courseId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT e.*, c.title AS course_title, c.slug AS course_slug, c.estimated_minutes, c.passing_score, c.is_certifying, c.validity_days
                FROM training_enrollments e
                JOIN training_courses c ON c.id = e.course_id
                WHERE e.id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND (e.tenant_id = ? OR c.lms_scope = \'platform\')';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>> id => row
     */
    public function findByIdsForTenant(array $ids, int $tenantId): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn ($v) => (int) $v, $ids), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT e.*, c.title AS course_title, c.slug AS course_slug, c.estimated_minutes, c.passing_score, c.is_certifying, c.validity_days, c.lms_scope
                FROM training_enrollments e
                JOIN training_courses c ON c.id = e.course_id
                WHERE e.id IN (' . $placeholders . ')
                  AND (e.tenant_id = ? OR c.lms_scope = \'platform\')';
        $params = [...$ids, $tenantId];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $eid = (int) ($row['id'] ?? 0);
            if ($eid > 0) {
                $out[$eid] = $row;
            }
        }

        return $out;
    }

    public function findByCourseAndUser(int $courseId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_enrollments WHERE course_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$courseId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function userHasCompletedCourse(int $userId, int $courseId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM training_enrollments WHERE user_id = ? AND course_id = ? AND status = ? LIMIT 1'
        );
        $stmt->execute([$userId, $courseId, 'completed']);

        return (bool) $stmt->fetchColumn();
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_enrollments (tenant_id, course_id, user_id, assigned_by, assignment_type, status, expires_at, motivation_text)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $data['course_id'],
            $data['user_id'],
            $data['assigned_by'] ?? null,
            $data['assignment_type'] ?? 'manual',
            $data['status'] ?? 'assigned',
            $data['expires_at'] ?? null,
            $data['motivation_text'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['status', 'started_at', 'completed_at', 'expires_at', 'motivation_text'];
        $fields = [];
        $params = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $fields[] = "`$k` = ?";
                $params[] = $data[$k];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE training_enrollments SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function revoke(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE training_enrollments SET status = 'revoked' WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Abandon volontaire : le membre annule son inscription (assigned, in_progress, pending_approval, failed).
     * Vérifie que l’inscription appartient au membre et est visible depuis le tenant (parcours communauté ou plateforme).
     */
    public function withdrawByLearner(int $enrollmentId, int $userId, int $tenantId): bool
    {
        $sql = 'UPDATE training_enrollments e
                INNER JOIN training_courses c ON c.id = e.course_id
                SET e.status = \'withdrawn\'
                WHERE e.id = ?
                  AND e.user_id = ?
                  AND (e.tenant_id = ? OR c.lms_scope = \'platform\')
                  AND e.status IN (\'assigned\', \'in_progress\', \'pending_approval\', \'failed\')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$enrollmentId, $userId, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Statistiques de conformité par formation (rapport admin) : une seule requête agrégée
     * pour tout le tenant au lieu d'une requête par formation.
     *
     * @return array<int, array{total: int, completed: int, active: int, revoked: int, avg_completion_seconds: ?float}> clé = course_id
     */
    public function complianceStatsByCourseForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.course_id,
                    COUNT(*) AS total,
                    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN e.status IN ('assigned', 'in_progress') THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN e.status = 'revoked' THEN 1 ELSE 0 END) AS revoked,
                    AVG(CASE WHEN e.status = 'completed' AND e.completed_at IS NOT NULL
                             THEN TIMESTAMPDIFF(SECOND, COALESCE(e.started_at, e.assigned_at), e.completed_at) END) AS avg_completion_seconds
             FROM training_enrollments e
             INNER JOIN training_courses c ON c.id = e.course_id
             WHERE c.tenant_id = ?
             GROUP BY e.course_id"
        );
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $courseId = (int) ($row['course_id'] ?? 0);
            if ($courseId < 1) {
                continue;
            }
            $avgSeconds = $row['avg_completion_seconds'] ?? null;
            $out[$courseId] = [
                'total' => (int) ($row['total'] ?? 0),
                'completed' => (int) ($row['completed'] ?? 0),
                'active' => (int) ($row['active'] ?? 0),
                'revoked' => (int) ($row['revoked'] ?? 0),
                'avg_completion_seconds' => $avgSeconds !== null ? (float) $avgSeconds : null,
            ];
        }

        return $out;
    }

    /** Enrollments expirant ou expirés pour un tenant. */
    public function listExpiringOrExpired(int $tenantId, ?int $daysAhead = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, c.title AS course_title, u.email, u.display_name
             FROM training_enrollments e
             JOIN training_courses c ON c.id = e.course_id
             JOIN users u ON u.id = e.user_id
             WHERE e.tenant_id = ? AND e.expires_at IS NOT NULL AND e.expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY) AND e.status IN (\'assigned\', \'in_progress\')
             ORDER BY e.expires_at ASC'
        );
        $stmt->execute([$tenantId, $daysAhead]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Demandes d’inscription en attente de validation (toutes formations du tenant).
     *
     * @return list<array<string, mixed>>
     */
    public function listPendingApproval(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, c.title AS course_title, u.email, u.display_name
             FROM training_enrollments e
             JOIN training_courses c ON c.id = e.course_id
             JOIN users u ON u.id = e.user_id
             WHERE e.tenant_id = ? AND e.status = \'pending_approval\'
             ORDER BY e.assigned_at DESC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Nombre d’inscriptions rattachées à une communauté.
     */
    public function countForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM training_enrollments WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Taux de réussite pédagogique.
     *
     * Réussites = inscriptions au statut « terminé ».
     * Base = inscriptions engagées : en cours, terminées ou non validées
     * (exclut non démarrées, en attente, annulées, révoquées, expirées).
     *
     * @return array{
     *   completed: int,
     *   failed: int,
     *   in_progress: int,
     *   engaged: int,
     *   rate_percent: float|null
     * }
     */
    public function aggregateSuccessRate(?int $tenantId = null): array
    {
        $sql = 'SELECT
                    COALESCE(SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END), 0) AS completed_count,
                    COALESCE(SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END), 0) AS failed_count,
                    COALESCE(SUM(CASE WHEN status = \'in_progress\' THEN 1 ELSE 0 END), 0) AS in_progress_count,
                    COALESCE(SUM(CASE WHEN status IN (\'in_progress\', \'completed\', \'failed\') THEN 1 ELSE 0 END), 0) AS engaged_count
                FROM training_enrollments';
        $params = [];
        if ($tenantId !== null && $tenantId > 0) {
            $sql .= ' WHERE tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $completed = (int) ($row['completed_count'] ?? 0);
        $failed = (int) ($row['failed_count'] ?? 0);
        $inProgress = (int) ($row['in_progress_count'] ?? 0);
        $engaged = (int) ($row['engaged_count'] ?? 0);

        return [
            'completed' => $completed,
            'failed' => $failed,
            'in_progress' => $inProgress,
            'engaged' => $engaged,
            'rate_percent' => $engaged > 0 ? round(100.0 * $completed / $engaged, 1) : null,
        ];
    }

    /**
     * Inscriptions terminées pour export conformité (jointure certificat si présent).
     *
     * @return list<array<string, mixed>>
     */
    public function listCompletedForComplianceExport(int $tenantId, int $limit = 2000): array
    {
        $lim = max(1, min(5000, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT e.id AS enrollment_id, e.user_id, e.completed_at, e.course_id,
                    c.title AS course_title,
                    u.display_name, u.email,
                    cert.certificate_number, cert.id AS certificate_id, cert.pdf_path
             FROM training_enrollments e
             INNER JOIN training_courses c ON c.id = e.course_id AND c.tenant_id = e.tenant_id
             INNER JOIN users u ON u.id = e.user_id
             LEFT JOIN training_certificates cert ON cert.enrollment_id = e.id AND cert.tenant_id = e.tenant_id AND cert.status = 'valid'
             WHERE e.tenant_id = ? AND e.status = 'completed'
             ORDER BY e.completed_at DESC
             LIMIT {$lim}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
