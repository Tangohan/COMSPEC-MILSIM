<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier;

/**
 * Ce que le suivi de dossier demande aux services qui portent déjà les étapes. L'implémentation réelle
 * (LiveDossierPorts) passe par ces services : aucune écriture directe qui contournerait leurs contrôles.
 */
interface DossierPorts
{
    /**
     * Valide une étape du parcours d'intégration (MemberIntegrationService::completeStep). Une validation forcée
     * exige un motif et laisse sa trace dans le journal d'audit du service.
     *
     * @return array{ok: bool, message?: string}
     */
    public function completeIntegrationStep(int $tenantId, int $integrationId, int $stepId, int $actorId, bool $force, string $reason): array;

    /**
     * Change l'échéance, le responsable ou l'état d'une étape d'intégration, avec événement au journal du parcours
     * et recalcul de la progression du parcours.
     *
     * @param array<string, mixed> $fields due_at, responsible_user_id, status, completed_at, validated_by, force_reason
     */
    public function updateIntegrationStep(int $tenantId, int $integrationId, int $stepId, int $actorId, array $fields, string $eventType, string $message): bool;

    /** Note interne au journal du parcours d'intégration. */
    public function addIntegrationNote(int $tenantId, int $integrationId, int $actorId, string $message): bool;

    /** Notification au membre (boîte de réception). Faux si elle ne peut pas être déposée. */
    public function notifyMember(int $tenantId, int $userId, string $stepTitle, ?string $dueOn, string $path): bool;

    /**
     * Relance de la campagne de fiabilisation (DataCampaignService::remind : délai entre deux relances respecté).
     *
     * @return array{ok: bool, message: string, sent: int}
     */
    public function remindCampaign(int $tenantId, int $actorId, int $campaignId, int $userId): array;

    /** Journal d'audit de la plateforme. */
    public function audit(int $tenantId, int $actorId, int $userId, string $stepKey, string $action, string $detailJson): void;

    /** Le compte appartient-il à cette communauté (responsable désigné) ? */
    public function isTenantUser(int $tenantId, int $userId): bool;
}
