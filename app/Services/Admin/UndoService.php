<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\AdminActionRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\SiteRoleAssignmentRepository;
use App\Services\Moderation\ModerationService;

final class UndoService
{
    public function __construct(
        private ?AdminActionRepository $adminActions = null,
        private ?SiteRoleAssignmentRepository $siteRoles = null,
        private ?ModerationRepository $moderationRepository = null,
        private ?ModerationService $moderationService = null,
    ) {
        $this->adminActions ??= new AdminActionRepository();
        $this->siteRoles ??= new SiteRoleAssignmentRepository();
        $this->moderationRepository ??= new ModerationRepository();
    }

    /** @return array{ok: bool, message: string} */
    public function undo(int $adminActionId, int $actorUserId, string $reason): array
    {
        $action = $this->adminActions->findById($adminActionId);
        if ($action === null) {
            return ['ok' => false, 'message' => 'Action introuvable.'];
        }
        if ((int) ($action['is_undoable'] ?? 0) !== 1 || (string) ($action['status'] ?? '') !== 'applied') {
            return ['ok' => false, 'message' => 'Cette action n’est plus annulable.'];
        }

        $undoId = $this->adminActions->createUndoRequest($adminActionId, $actorUserId, $reason);

        $result = match ((string) ($action['action_type'] ?? '')) {
            'site_role.assigned' => $this->undoSiteRoleAssignment($action),
            'moderation.action_applied' => $this->undoModerationApplied($action, $actorUserId),
            default => ['ok' => false, 'message' => 'Annulation technique non disponible pour ce type.'],
        };

        if ($result['ok']) {
            $this->adminActions->markActionUndone($adminActionId);
        }
        $this->adminActions->markUndoResult($undoId, $result['ok'], $result['message']);

        if (!$result['ok'] && (int) ($action['is_compensable'] ?? 0) === 1) {
            $this->adminActions->createCompensation($adminActionId, $actorUserId, 'manual_followup', [
                'reason' => $reason,
                'note' => 'Action non annulable techniquement, compensation manuelle requise.',
            ]);
        }

        return $result;
    }

    /** @param array<string,mixed> $action */
    private function undoSiteRoleAssignment(array $action): array
    {
        $after = $this->decodeJson($action['after_json'] ?? null);
        $assignmentId = (int) ($after['assignment_id'] ?? 0);
        if ($assignmentId < 1) {
            return ['ok' => false, 'message' => 'Affectation cible absente de la trace.'];
        }

        return $this->siteRoles->revoke($assignmentId)
            ? ['ok' => true, 'message' => 'Affectation de rôle site révoquée (annulation réussie).']
            : ['ok' => false, 'message' => 'Affectation déjà révoquée ou introuvable.'];
    }

    /** @param array<string,mixed> $action */
    private function undoModerationApplied(array $action, int $actorUserId): array
    {
        $after = $this->decodeJson($action['after_json'] ?? null);
        $tenantId = (int) ($action['tenant_id'] ?? 0);
        $moderationActionId = (int) ($after['moderation_action_id'] ?? 0);
        if ($tenantId < 1 || $moderationActionId < 1) {
            return ['ok' => false, 'message' => 'Référence de sanction manquante pour annulation.'];
        }

        $ok = $this->moderationRepository->revokeActionForScope($tenantId, $moderationActionId, $actorUserId, 'platform');

        return $ok
            ? ['ok' => true, 'message' => 'Sanction plateforme levée par annulation.']
            : ['ok' => false, 'message' => 'Sanction non annulable (déjà levée, périmètre, ou expirée).'];
    }

    /** @return array<string,mixed> */
    private function decodeJson(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
