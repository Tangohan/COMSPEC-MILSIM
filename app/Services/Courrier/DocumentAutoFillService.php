<?php

declare(strict_types=1);

namespace App\Services\Courrier;

/**
 * Pré-remplissage : signataire, destinataire, unité, date, numéro, à partir du contexte utilisateur.
 */
class DocumentAutoFillService
{
    public function __construct(
        private TemplateVariableService $variableService,
        private DocumentNumberingService $numberingService
    ) {
    }

    /**
     * Retourne les valeurs par défaut pour un nouveau document (issuer_label, reference_number, etc.).
     * @param array{user_id: int, tenant_id: int, unit_id?: int} $context
     */
    public function getDefaults(array $context): array
    {
        $resolved = $this->variableService->buildContext($context);
        $tenantId = $context['tenant_id'] ?? null;
        $ref = $tenantId ? $this->numberingService->generateNext($tenantId) : '';

        return [
            'issuer_label' => trim(($resolved['user.rank_label'] ?? '') . ' ' . ($resolved['user.full_name'] ?? '')),
            'reference_number' => $ref,
            'variables' => $resolved,
        ];
    }
}
