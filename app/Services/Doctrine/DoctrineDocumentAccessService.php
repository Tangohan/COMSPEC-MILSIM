<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Services\Documents\DocumentAccessService;
use App\Support\Doctrine\DoctrineWorkflowStatus;

/**
 * Accès membre aux doctrines publiées : audience ORBAT + habilitation,
 * en complément des règles classiques du module Documents (visibilité, rôles, etc.).
 */
final class DoctrineDocumentAccessService
{
    public function __construct(
        private DocumentAccessService $documentAccessService,
        private DocumentAudienceResolver $audienceResolver,
    ) {}

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $doctrine
     */
    public function canMemberView(int $tenantId, int $userId, array $document, array $doctrine): bool
    {
        if ($this->documentAccessService->canRead($document, $userId, $tenantId)) {
            return true;
        }

        if ((string) ($doctrine['doctrine_status'] ?? '') !== DoctrineWorkflowStatus::PUBLISHED) {
            return false;
        }

        $documentId = (int) ($document['id'] ?? 0);
        if ($documentId < 1) {
            return false;
        }

        if (!$this->audienceResolver->isUserInAudience($tenantId, $userId, $documentId, $doctrine)) {
            return false;
        }

        return $this->documentAccessService->passesClassification($document, $userId);
    }
}
