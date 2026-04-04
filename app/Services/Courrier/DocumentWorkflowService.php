<?php

declare(strict_types=1);

namespace App\Services\Courrier;

use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Repositories\Courrier\DocumentWorkflowRepository;

/**
 * Transitions de statut : brouillon → prêt à validation → validé → signé → envoyé → archivé ; refus.
 */
class DocumentWorkflowService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_SENT = 'sent';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

    private const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PENDING_VALIDATION],
        self::STATUS_PENDING_VALIDATION => [self::STATUS_VALIDATED, self::STATUS_REJECTED],
        self::STATUS_VALIDATED => [self::STATUS_SIGNED],
        self::STATUS_SIGNED => [self::STATUS_SENT],
        self::STATUS_SENT => [self::STATUS_ARCHIVED],
        self::STATUS_REJECTED => [self::STATUS_DRAFT],
        self::STATUS_ARCHIVED => [],
    ];

    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private DocumentWorkflowRepository $workflowRepository,
        private DocumentValidationService $validationService
    ) {
    }

    public function canTransition(string $from, string $to): bool
    {
        $allowed = self::TRANSITIONS[$from] ?? [];
        return in_array($to, $allowed, true);
    }

    /**
     * Effectue la transition et enregistre dans document_workflows.
     */
    public function transition(int $documentId, string $toStatus, ?int $actedBy = null, ?string $comment = null, ?int $tenantId = null): bool
    {
        $doc = $this->documentRepository->findById($documentId, $tenantId);
        if (!$doc) {
            return false;
        }
        $from = $doc['status'];
        if (!$this->canTransition($from, $toStatus)) {
            return false;
        }
        if ($toStatus === self::STATUS_SENT && $actedBy !== null) {
            $alerts = $this->validationService->validate($doc, ['user_id' => $actedBy, 'tenant_id' => $tenantId], []);
            if (!$this->validationService->canSendOrExport($alerts)) {
                return false;
            }
        }

        $updates = ['status' => $toStatus];
        if ($toStatus === self::STATUS_VALIDATED) {
            $updates['validated_by'] = $actedBy;
        }
        if ($toStatus === self::STATUS_SIGNED) {
            $updates['signed_by'] = $actedBy;
        }
        if ($toStatus === self::STATUS_SENT) {
            $updates['sent_at'] = date('Y-m-d H:i:s');
        }
        if ($toStatus === self::STATUS_ARCHIVED) {
            $updates['archived_at'] = date('Y-m-d H:i:s');
        }

        $this->documentRepository->update($documentId, $updates);
        $actionLabel = $this->getActionLabel($from, $toStatus);
        $this->workflowRepository->log($documentId, $from, $toStatus, $actionLabel, $comment, $actedBy);
        return true;
    }

    public function getActionLabel(string $from, string $to): string
    {
        return match ($to) {
            self::STATUS_PENDING_VALIDATION => 'Soumis à validation',
            self::STATUS_VALIDATED => 'Validé',
            self::STATUS_SIGNED => 'Signé',
            self::STATUS_SENT => 'Envoyé',
            self::STATUS_REJECTED => 'Refusé',
            self::STATUS_ARCHIVED => 'Archivé',
            default => 'Changement de statut',
        };
    }

    public function getHistory(int $documentId): array
    {
        return $this->workflowRepository->getHistory($documentId);
    }
}
