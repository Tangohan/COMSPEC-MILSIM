<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingCertificateRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingCourseRepository;
use App\Services\Training\TrainingProgressService;

class TrainingCertificateService
{
    public function __construct(
        private TrainingCertificateRepository $certificateRepository,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingCourseRepository $courseRepository,
        private TrainingProgressService $progressService,
        private TrainingAuditService $auditService
    ) {}

    /** Émet un certificat si les conditions sont remplies (formation complétée, score, etc.). */
    public function issueCertificate(int $enrollmentId, int $tenantId, int $userId): ?array
    {
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            return null;
        }
        if ($enrollment['status'] !== 'completed') {
            return null;
        }
        $course = $this->courseRepository->findById((int) $enrollment['course_id'], $tenantId);
        if (!$course || (int) ($course['is_certifying'] ?? 0) !== 1) {
            return null;
        }
        $existing = $this->certificateRepository->findByEnrollmentId($enrollmentId);
        if ($existing && ($existing['status'] ?? '') === 'valid') {
            return $existing;
        }

        $progress = $this->progressService->computeCourseProgress($enrollmentId);
        if (!$progress['completed']) {
            return null;
        }

        $certificateNumber = $this->generateCertificateNumber($tenantId);
        $issuedAt = date('Y-m-d H:i:s');
        $validityDays = isset($course['validity_days']) ? (int) $course['validity_days'] : null;
        $expiresAt = $validityDays ? date('Y-m-d H:i:s', strtotime('+' . $validityDays . ' days')) : null;
        $finalScore = $progress['percent'];

        $id = $this->certificateRepository->create($tenantId, [
            'enrollment_id' => $enrollmentId,
            'certificate_number' => $certificateNumber,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'final_score' => $finalScore,
            'status' => 'valid',
        ]);
        $cert = $this->certificateRepository->findById($id, $tenantId);
        $this->auditService->logCertificateIssued($tenantId, $userId, $id, [
            'certificate_number' => $certificateNumber,
            'enrollment_id' => $enrollmentId,
        ]);
        return $cert;
    }

    public function generateCertificateNumber(int $tenantId): string
    {
        $number = $this->certificateRepository->generateNextNumber($tenantId);
        return $number;
    }

    public function storePdfPath(int $certificateId, string $path, int $tenantId): void
    {
        $cert = $this->certificateRepository->findById($certificateId, $tenantId);
        if ($cert) {
            $this->certificateRepository->updatePdfPath($certificateId, $path);
        }
    }

    public function revoke(int $certificateId, int $tenantId, int $userId): void
    {
        $cert = $this->certificateRepository->findById($certificateId, $tenantId);
        if (!$cert) {
            throw new \InvalidArgumentException('Certificate not found.');
        }
        $this->certificateRepository->revoke($certificateId);
        $this->auditService->logCertificateRevoked($tenantId, $userId, $certificateId);
    }

    public function getByEnrollment(int $enrollmentId): ?array
    {
        return $this->certificateRepository->findByEnrollmentId($enrollmentId);
    }

    public function getById(int $id, ?int $tenantId = null): ?array
    {
        return $this->certificateRepository->findById($id, $tenantId);
    }
}
