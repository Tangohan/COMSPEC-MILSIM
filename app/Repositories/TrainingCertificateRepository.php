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
            'INSERT INTO training_certificates (tenant_id, enrollment_id, certificate_number, issued_at, expires_at, final_score, pdf_path, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $data['enrollment_id'],
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
