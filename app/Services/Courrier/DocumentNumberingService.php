<?php

declare(strict_types=1);

namespace App\Services\Courrier;

use App\Repositories\Courrier\CourrierDocumentRepository;

/**
 * Génération du numéro de référence (CR-YYYY-NNNN).
 */
class DocumentNumberingService
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository
    ) {
    }

    public function generateNext(int $tenantId, string $prefix = 'CR', ?string $year = null): string
    {
        return $this->documentRepository->getNextReferenceNumber($tenantId, $prefix, $year ?? date('Y'));
    }
}
