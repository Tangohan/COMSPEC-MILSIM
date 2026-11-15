<?php

declare(strict_types=1);

namespace App\Services\PhotoLibrary;

/**
 * Ce que le lecteur de la photothèque a le droit de faire, lu une fois par requête (compte, communauté, gestion des
 * photos, portes du bureau SSE). Valeur pure : les règles qui s'en servent sont dans PhotoPolicy.
 *
 * Portes SSE, comme JNET (JnetDashboardService::mayReadIntelligence) : le droit d'entrer au bureau SSE (membre habilité,
 * encadrement) puis l'engagement de confidentialité de la session, ou une session ouverte par code d'accès de la même
 * communauté. « clearConfidential » : habilitation « confidentiel » des clichés confidentiels (SseClearanceService).
 */
final class PhotoViewer
{
    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
        public readonly bool $manager = false,
        public readonly bool $sseDoor = false,
        public readonly bool $sseManage = false,
        public readonly bool $clearConfidential = false,
    ) {
    }

    /** Un compte de la communauté (jamais une session invitée par code, ni sans communauté). */
    public function isMember(): bool
    {
        return $this->userId > 0 && $this->tenantId > 0;
    }
}
