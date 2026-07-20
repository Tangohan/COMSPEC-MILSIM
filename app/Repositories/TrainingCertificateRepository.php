<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingCertificateRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT c.*, e.user_id, e.course_id, cr.title AS course_title, cr.slug AS course_slug
                FROM training_certificates c
                JOIN training_enrollments e ON e.id = c.enrollment_id
                JOIN training_courses cr ON cr.id = e.course_id
                WHERE c.id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND c.tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEnrollmentId(int $enrollmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, e.user_id, e.course_id
             FROM training_certificates c
             JOIN training_enrollments e ON e.id = c.enrollment_id
             WHERE c.enrollment_id = ? AND c.status = ? LIMIT 1'
        );
        $stmt->execute([$enrollmentId, 'valid']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCertificateNumber(string $number, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT c.*, e.user_id, e.course_id
                FROM training_certificates c
                JOIN training_enrollments e ON e.id = c.enrollment_id
                WHERE c.certificate_number = ?';
        $params = [$number];
        if ($tenantId !== null) {
            $sql .= ' AND c.tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function generateNextNumber(int $tenantId): string
    {
        $prefix = 'ATH-' . $tenantId . '-' . date('Y') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM training_certificates WHERE tenant_id = ? AND certificate_number LIKE ?'
        );
        $stmt->execute([$tenantId, $prefix . '%']);
        $seq = (int) $stmt->fetchColumn() + 1;
        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function numberExists(string $number, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM training_certificates WHERE certificate_number = ?';
        $params = [$number];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_certificates (tenant_id, enrollment_id, issued_by_user_id, certificate_number, issued_at, expires_at, final_score, pdf_path, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $data['enrollment_id'],
            $data['issued_by_user_id'] ?? null,
            $data['certificate_number'],
            $data['issued_at'] ?? date('Y-m-d H:i:s'),
            $data['expires_at'] ?? null,
            $data['final_score'],
            $data['pdf_path'] ?? null,
            $data['status'] ?? 'valid',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePdfPath(int $id, string $path): void
    {
        $stmt = $this->pdo->prepare('UPDATE training_certificates SET pdf_path = ? WHERE id = ?');
        $stmt->execute([$path, $id]);
    }

    public function revoke(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE training_certificates SET status = 'revoked' WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function markExpired(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE training_certificates SET status = 'expired' WHERE id = ? AND status = 'valid'");
        $stmt->execute([$id]);
    }

    /** Certificats expirés (expires_at < NOW()) à marquer expired. */
    public function listValidExpired(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM training_certificates WHERE status = 'valid' AND expires_at IS NOT NULL AND expires_at < NOW()"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<array<string, mixed>> */
    public function listForTenantAdmin(int $tenantId, int $limit = 200, ?string $search = null): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT c.*, cr.title AS course_title, cr.slug AS course_slug,
                       e.user_id AS learner_user_id,
                       lu.display_name AS learner_display_name, lu.email AS learner_email,
                       iu.display_name AS issued_by_display_name, iu.email AS issued_by_email
                FROM training_certificates c
                JOIN training_enrollments e ON e.id = c.enrollment_id
                JOIN training_courses cr ON cr.id = e.course_id
                LEFT JOIN users lu ON lu.id = e.user_id
                LEFT JOIN users iu ON iu.id = c.issued_by_user_id
                WHERE c.tenant_id = ?';
        $params = [$tenantId];
        $search = trim((string) $search);
        if ($search !== '') {
            $sql .= ' AND (cr.title LIKE ? OR lu.display_name LIKE ? OR lu.email LIKE ? OR c.certificate_number LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term);
        }
        $sql .= ' ORDER BY c.issued_at DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dernière formation certifiante attestée (titre court) pour affichage forum.
     *
     * @param list<int> $userIds
     * @return array<int, string> user_id => course title
     */
    public function latestValidCertifiedCourseTitlesForUsers(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds), fn ($id) => $id > 0));
        if ($userIds === []) {
            return [];
        }
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_certificates' LIMIT 1");
            if (!$stmt || !$stmt->fetchColumn()) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT e.user_id, cr.title AS course_title, tc.issued_at
                FROM training_certificates tc
                INNER JOIN training_enrollments e ON e.id = tc.enrollment_id
                INNER JOIN training_courses cr ON cr.id = e.course_id
                WHERE tc.tenant_id = ? AND tc.status = 'valid' AND cr.is_certifying = 1 AND e.user_id IN ($placeholders)
                ORDER BY e.user_id ASC, tc.issued_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $userIds));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0 && !isset($out[$uid])) {
                $t = trim((string) ($row['course_title'] ?? ''));
                if ($t !== '') {
                    $out[$uid] = $t;
                }
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function listByUserId(int $userId, ?int $tenantId = null): array
    {
        $sql = 'SELECT c.*, cr.title AS course_title, cr.slug AS course_slug, e.completed_at
                FROM training_certificates c
                JOIN training_enrollments e ON e.id = c.enrollment_id
                JOIN training_courses cr ON cr.id = e.course_id
                WHERE e.user_id = ?';
        $params = [$userId];
        if ($tenantId !== null) {
            $sql .= ' AND c.tenant_id = ?';
            $params[] = $tenantId;
        }
        $sql .= ' ORDER BY c.issued_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
