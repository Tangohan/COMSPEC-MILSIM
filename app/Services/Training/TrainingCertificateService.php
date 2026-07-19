<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TenantRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Émission et consultation des attestations de formation.
 */
class TrainingCertificateService
{
    /** Anti-spam : ne pas renvoyer le même avis d’attestation trop tôt. */
    private const AVAILABILITY_EMAIL_COOLDOWN_SECONDS = 7200;

    public function __construct(
        private TrainingCertificateRepository $certificateRepository,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingCourseRepository $courseRepository,
        private TrainingProgressService $progressService,
        private TrainingAuditService $auditService,
        private TrainingCertificatePdfService $pdfService,
        private EmailService $emailService,
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
    ) {}

    /**
     * Émet un certificat si les conditions sont remplies (formation complétée, score, etc.).
     *
     * @param int      $userId              utilisateur concerné (souvent l’apprenant)
     * @param int|null $issuedByStaffUserId si une action staff déclenche l’émission ; null pour une complétion par l’apprenant
     */
    public function issueCertificate(int $enrollmentId, int $tenantId, int $userId, ?int $issuedByStaffUserId = null): ?array
    {
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment || (int) $enrollment['user_id'] !== $userId) {
            return null;
        }
        if ($enrollment['status'] !== 'completed') {
            return null;
        }
        $course = $this->courseRepository->findByIdForViewer((int) $enrollment['course_id'], $tenantId);
        if (!$course || (int) ($course['is_certifying'] ?? 0) !== 1) {
            return null;
        }
        $existing = $this->certificateRepository->findByEnrollmentId($enrollmentId);
        if ($existing && ($existing['status'] ?? '') === 'valid') {
            $eid = (int) ($existing['id'] ?? 0);
            if ($eid > 0) {
                $rel = trim((string) ($existing['pdf_path'] ?? ''));
                if ($rel === '' || !is_file(base_path($rel))) {
                    $this->generatePdfOrLogFailure($eid, $tenantId);

                    return $this->certificateRepository->findById($eid, $tenantId);
                }
            }

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
            'issued_by_user_id' => $issuedByStaffUserId,
            'certificate_number' => $certificateNumber,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'final_score' => $finalScore,
            'status' => 'valid',
        ]);
        $this->generatePdfOrLogFailure($id, $tenantId);
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

    /**
     * Tente de produire le PDF pour une attestation valide dont le fichier manque ou est absent du disque.
     */
    public function ensurePdfForCertificate(int $certificateId, int $tenantId): ?array
    {
        $cert = $this->certificateRepository->findById($certificateId, $tenantId);
        if (!$cert || ($cert['status'] ?? '') !== 'valid') {
            return null;
        }
        $rel = trim((string) ($cert['pdf_path'] ?? ''));
        if ($rel !== '' && is_file(base_path($rel))) {
            return $cert;
        }
        $this->generatePdfOrLogFailure($certificateId, $tenantId);

        return $this->certificateRepository->findById($certificateId, $tenantId);
    }

    /**
     * Génère le document PDF et, en cas de succès, prévient l’apprenant par e-mail.
     * Point d’entrée unique pour les actions staff (génération unitaire ou en masse).
     */
    public function generatePdfDocument(int $certificateId, int $tenantId): ?string
    {
        if (!TrainingCertificatePdfEngine::isAvailable()) {
            error_log('[training_certificate] Moteur PDF indisponible : impossible de générer le PDF (certificat id=' . $certificateId . ', tenant=' . $tenantId . ').');

            return null;
        }
        $path = $this->pdfService->generateAndStore($certificateId, $tenantId);
        if ($path === null) {
            error_log('[training_certificate] Échec génération PDF (certificat id=' . $certificateId . ', tenant=' . $tenantId . ').');

            return null;
        }
        $this->notifyCertificateDocumentAvailable($certificateId, $tenantId);

        return $path;
    }

    /**
     * Compte les attestations valides sans document PDF disponible sur le disque, pour un tenant.
     */
    public function countPendingPdfDocuments(int $tenantId, int $scanLimit = 300): int
    {
        $pending = 0;
        foreach ($this->certificateRepository->listForTenantAdmin($tenantId, $scanLimit) as $row) {
            if (($row['status'] ?? '') !== 'valid') {
                continue;
            }
            $rel = trim((string) ($row['pdf_path'] ?? ''));
            if ($rel === '' || !is_file(base_path($rel))) {
                $pending++;
            }
        }

        return $pending;
    }

    /**
     * Régénère les PDF manquants pour le tenant (ordre chronologique, plafonné).
     * Ne s’arrête jamais silencieusement sur un échec isolé : chaque échec est journalisé
     * et compté, pour un retour précis côté interface (succès / échecs).
     *
     * @return array{generated: int, failed: int, remaining: int, failed_ids: list<int>}
     */
    public function backfillPendingPdfDocuments(int $tenantId, int $max = 50): array
    {
        $result = ['generated' => 0, 'failed' => 0, 'remaining' => 0, 'failed_ids' => []];
        if (!TrainingCertificatePdfEngine::isAvailable()) {
            $result['remaining'] = $this->countPendingPdfDocuments($tenantId);

            return $result;
        }
        $max = max(1, min(100, $max));
        $rows = $this->certificateRepository->listForTenantAdmin($tenantId, 300);
        $processed = 0;
        foreach ($rows as $row) {
            if (($row['status'] ?? '') !== 'valid') {
                continue;
            }
            $rel = trim((string) ($row['pdf_path'] ?? ''));
            if ($rel !== '' && is_file(base_path($rel))) {
                continue;
            }
            if ($processed >= $max) {
                $result['remaining']++;
                continue;
            }
            $processed++;
            $certId = (int) $row['id'];
            if ($this->generatePdfDocument($certId, $tenantId) !== null) {
                $result['generated']++;
            } else {
                $result['failed']++;
                $result['failed_ids'][] = $certId;
            }
        }

        return $result;
    }

    private function generatePdfOrLogFailure(int $certificateId, int $tenantId): void
    {
        $this->generatePdfDocument($certificateId, $tenantId);
    }

    /**
     * E-mail transactionnel après création réussie du document (indépendant du moteur PDF).
     */
    private function notifyCertificateDocumentAvailable(int $certificateId, int $tenantId): void
    {
        try {
            if ($this->isCertificateAvailabilityEmailThrottled($certificateId)) {
                return;
            }
            $cert = $this->certificateRepository->findById($certificateId, $tenantId);
            if (!$cert || ($cert['status'] ?? '') !== 'valid') {
                return;
            }
            $userId = (int) ($cert['user_id'] ?? 0);
            if ($userId < 1) {
                return;
            }
            if (!$this->notificationPreferencesRepository->isEmailEventEnabled($userId, EmailEvents::TRAINING_CERTIFICATE_AVAILABLE)) {
                return;
            }
            $user = $this->userRepository->findById($userId, $tenantId);
            if (!$user) {
                return;
            }
            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $tenant = $this->tenantRepository->findById($tenantId);
            $tenantName = 'Communauté';
            if ($tenant) {
                $tenantName = function_exists('community_display_name')
                    ? community_display_name($tenant)
                    : (string) ($tenant['name'] ?? 'Communauté');
            }
            $display = trim((string) ($user['display_name'] ?? ''));
            if ($display === '') {
                $display = trim((string) ($user['callsign'] ?? ''));
            }
            if ($display === '') {
                $display = $email;
            }
            $courseTitle = trim((string) ($cert['course_title'] ?? ''));
            if ($courseTitle === '') {
                $courseTitle = 'Formation';
            }
            $certificateUrl = \url('formations/certificate/' . $certificateId);
            $myTrainingUrl = \url('formations/mes-formations');

            $sent = $this->emailService->sendTrainingCertificateAvailable(
                $email,
                $display,
                $tenantName,
                $courseTitle,
                $certificateUrl,
                $myTrainingUrl,
                $tenantId,
                $certificateId
            );
            if ($sent) {
                $this->markCertificateAvailabilityEmailSent($certificateId);
            }
        } catch (\Throwable) {
        }
    }

    private function isCertificateAvailabilityEmailThrottled(int $certificateId): bool
    {
        $path = $this->certificateAvailabilityThrottlePath($certificateId);
        if ($path === null || !is_file($path)) {
            return false;
        }
        $raw = @file_get_contents($path);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $lastSent = is_array($data) ? (int) ($data['last_sent'] ?? 0) : 0;
        if ($lastSent < 1) {
            return false;
        }

        return (time() - $lastSent) < self::AVAILABILITY_EMAIL_COOLDOWN_SECONDS;
    }

    private function markCertificateAvailabilityEmailSent(int $certificateId): void
    {
        $path = $this->certificateAvailabilityThrottlePath($certificateId);
        if ($path === null) {
            return;
        }
        @file_put_contents($path, json_encode(['last_sent' => time()], JSON_THROW_ON_ERROR), LOCK_EX);
    }

    private function certificateAvailabilityThrottlePath(int $certificateId): ?string
    {
        if ($certificateId < 1) {
            return null;
        }
        $dir = base_path('storage/cache/training-certificate-emails');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        return $dir . '/' . $certificateId . '.json';
    }
}
