<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier;

use App\Core\Container;
use App\Core\Database;
use App\Repositories\MemberIntegrationRepository;
use App\Repositories\MemberNotificationRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\DataCampaign\DataCampaignService;
use App\Services\MemberIntegration\MemberIntegrationService;
use App\Support\MemberIntegrationCatalog;
use Throwable;

/** Implémentation réelle des ports du suivi de dossier : services d'intégration, de campagne et de notification existants. */
final class LiveDossierPorts implements DossierPorts
{
    public function completeIntegrationStep(int $tenantId, int $integrationId, int $stepId, int $actorId, bool $force, string $reason): array
    {
        return Container::get(MemberIntegrationService::class)->completeStep($tenantId, $integrationId, $stepId, $actorId, $force, $force ? $reason : null);
    }

    public function updateIntegrationStep(int $tenantId, int $integrationId, int $stepId, int $actorId, array $fields, string $eventType, string $message): bool
    {
        $repo = Container::get(MemberIntegrationRepository::class);
        $step = $repo->findStep($tenantId, $stepId);
        if ($step === null || (int) $step['integration_id'] !== $integrationId) {
            return false;
        }
        $repo->updateStep($tenantId, $stepId, $fields);
        $repo->addEvent($tenantId, $integrationId, $eventType, MemberIntegrationCatalog::VISIBILITY_STAFF, $message, $actorId, $stepId);
        Container::get(MemberIntegrationService::class)->refresh($tenantId, $integrationId, $actorId);

        return true;
    }

    public function addIntegrationNote(int $tenantId, int $integrationId, int $actorId, string $message): bool
    {
        return Container::get(MemberIntegrationService::class)->addNote($tenantId, $integrationId, $actorId, $message, false);
    }

    public function notifyMember(int $tenantId, int $userId, string $stepTitle, ?string $dueOn, string $path): bool
    {
        $id = Container::get(MemberNotificationRepository::class)->create($tenantId, $userId, 'dossier_step_reminder', [
            'step' => mb_substr($stepTitle, 0, 180),
            'due_on' => $dueOn,
            'path' => $path,
        ]);

        return $id !== null && $id > 0;
    }

    public function remindCampaign(int $tenantId, int $actorId, int $campaignId, int $userId): array
    {
        $r = Container::get(DataCampaignService::class)->remind($tenantId, $actorId, $campaignId, [$userId], null);

        return ['ok' => (bool) $r['ok'], 'message' => (string) $r['message'], 'sent' => (int) $r['sent']];
    }

    public function audit(int $tenantId, int $actorId, int $userId, string $stepKey, string $action, string $detailJson): void
    {
        try {
            Container::get(AuditService::class)->log(
                $action === 'remind' ? AuditAction::MEMBER_DOSSIER_REMIND : AuditAction::MEMBER_DOSSIER_STEP,
                $tenantId,
                $actorId,
                'member_dossier',
                $userId,
                $stepKey,
                $detailJson
            );
        } catch (Throwable $e) {
            error_log('[dossier] audit : ' . $e->getMessage());
        }
    }

    public function isTenantUser(int $tenantId, int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        try {
            $pdo = Database::getPdo();
            $st = $pdo->prepare(
                "SELECT 1 FROM users u WHERE u.id = ? AND (u.tenant_id = ? OR EXISTS (SELECT 1 FROM user_community_memberships m WHERE m.user_id = u.id AND m.tenant_id = ? AND m.status = 'active')) LIMIT 1"
            );
            $st->execute([$userId, $tenantId, $tenantId]);

            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
