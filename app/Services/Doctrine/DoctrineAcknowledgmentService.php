<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Repositories\Doctrine\DocumentAcknowledgmentRepository;
use App\Repositories\DocumentAuditRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;

final class DoctrineAcknowledgmentService
{
    public function __construct(
        private DocumentAcknowledgmentRepository $acknowledgmentRepository,
        private DocumentComplianceService $complianceService,
        private DocumentAudienceResolver $audienceResolver,
        private DoctrineReferenceService $referenceService,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private UserRepository $userRepository,
        private AuditService $auditService,
        private DocumentAuditRepository $documentAuditRepository,
    ) {}

    /**
     * @param array<string, mixed> $doctrine
     * @param array<string, mixed> $version
     * @return array{ok: true, acknowledgment_id: int}|array{ok: false, error: string}
     */
    public function sign(
        int $tenantId,
        int $userId,
        array $doctrine,
        array $version,
        string $ipAddress,
        string $userAgent,
    ): array {
        $documentId = (int) ($doctrine['document_id'] ?? 0);
        $versionId = (int) ($version['id'] ?? 0);
        if ($documentId < 1 || $versionId < 1 || $tenantId < 1 || $userId < 1) {
            return ['ok' => false, 'error' => 'Référence documentaire invalide.'];
        }

        if (!$this->audienceResolver->isUserInAudience($tenantId, $userId, $documentId, $doctrine)) {
            return ['ok' => false, 'error' => 'Vous n’êtes pas dans le public visé par cette doctrine.'];
        }

        $status = $this->complianceService->statusForUser($tenantId, $userId, $doctrine, $versionId);
        if ($status === \App\Support\Doctrine\DoctrineComplianceStatus::ACKNOWLEDGED) {
            return ['ok' => false, 'error' => 'Vous avez déjà pris connaissance de cette version.'];
        }
        if ($status === \App\Support\Doctrine\DoctrineComplianceStatus::NOT_APPLICABLE) {
            return ['ok' => false, 'error' => 'Cette doctrine ne vous est pas applicable.'];
        }

        $user = $this->userRepository->findById($userId, $tenantId);
        if ($user === null) {
            return ['ok' => false, 'error' => 'Compte introuvable.'];
        }

        $profile = $this->personnelProfileRepository->getByUserId($userId);
        $assignment = $this->personnelAssignmentRepository->getPrimaryAssignment($userId);
        $displayName = trim((string) ($user['display_name'] ?? ''));
        $parts = preg_split('/\s+/u', $displayName, 2) ?: [];
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';
        $rank = is_array($profile) ? trim((string) ($profile['grade_label'] ?? $profile['rank'] ?? '')) : '';
        $unit = is_array($assignment) ? trim((string) ($assignment['unit_name'] ?? '')) : '';
        $reference = (string) ($doctrine['reference_code'] ?? '');
        $versionLabel = $this->referenceService->formatVersionLabel(
            isset($version['version_major']) ? (int) $version['version_major'] : null,
            isset($version['version_minor']) ? (int) $version['version_minor'] : null,
            isset($version['version_label']) ? (string) $version['version_label'] : null
        );
        $integrity = hash('sha256', implode('|', [
            $documentId,
            $versionId,
            (string) ($version['checksum'] ?? ''),
            $reference,
            $versionLabel,
        ]));

        if ($this->acknowledgmentRepository->findForUserVersion($tenantId, $userId, $versionId) !== null) {
            return ['ok' => false, 'error' => 'Prise en compte déjà enregistrée pour cette version.'];
        }

        $ackId = $this->acknowledgmentRepository->create([
            'tenant_id' => $tenantId,
            'document_id' => $documentId,
            'version_id' => $versionId,
            'user_id' => $userId,
            'signed_at' => date('Y-m-d H:i:s'),
            'snapshot_first_name' => $firstName,
            'snapshot_last_name' => $lastName,
            'snapshot_display_name' => $displayName,
            'snapshot_rank' => $rank,
            'snapshot_unit' => $unit,
            'snapshot_reference' => $reference,
            'snapshot_version_label' => $versionLabel,
            'integrity_hash' => $integrity,
            'ip_address' => $ipAddress,
            'user_agent' => mb_substr($userAgent, 0, 512),
        ]);

        try {
            $this->documentAuditRepository->log($documentId, $userId, 'doctrine_acknowledged', null, [
                'version_id' => $versionId,
                'reference' => $reference,
                'version' => $versionLabel,
            ]);
        } catch (\Throwable) {
        }

        try {
            $this->auditService->logChange(
                'doctrine.acknowledged',
                $tenantId,
                $userId,
                'document_doctrine',
                $documentId,
                [],
                ['version_id' => $versionId, 'reference' => $reference]
            );
        } catch (\Throwable) {
        }

        return ['ok' => true, 'acknowledgment_id' => $ackId];
    }
}
