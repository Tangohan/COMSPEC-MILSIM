<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\MemberDepartureRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\AdminAuditService;
use Throwable;

/**
 * Offboarding structuré : enregistre un départ (motif, date) et applique, si demandé,
 * la checklist de reprise d’accès (retrait des rôles organisation + habilitation).
 * La révocation d’accès n’est volontairement pas soumise au circuit d’élévation : il n’y a
 * rien à « approuver » dans le retrait des droits d’un membre qui part, et retarder la
 * révocation serait le vrai risque.
 */
class MemberOffboardingService
{
    public function __construct(
        private UserRepository $userRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private MemberDepartureRepository $departureRepository,
        private AdminAuditService $adminAuditService,
    ) {
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function recordDeparture(
        int $tenantId,
        int $targetUserId,
        int $actorUserId,
        string $reason,
        string $reasonNote,
        string $departedAt,
        bool $revokeAccess
    ): array {
        $user = $this->userRepository->findById($targetUserId, $tenantId);
        if (!$user) {
            return ['ok' => false, 'message' => 'Membre introuvable.'];
        }
        if (!in_array($reason, MemberDepartureRepository::REASONS, true)) {
            return ['ok' => false, 'message' => 'Motif de départ non reconnu.'];
        }
        $departedAtDt = \DateTimeImmutable::createFromFormat('Y-m-d', $departedAt);
        if ($departedAtDt === false) {
            $departedAt = date('Y-m-d');
        }

        try {
            $beforeStatus = (string) ($user['status'] ?? '');
            if ($beforeStatus !== 'inactive') {
                $this->userRepository->update($targetUserId, $tenantId, ['status' => 'inactive']);
                $this->adminAuditService->logUserDeactivated($tenantId, $actorUserId, $targetUserId);
            }

            $departureId = $this->departureRepository->create(
                $tenantId,
                $targetUserId,
                $actorUserId,
                $reason,
                $reasonNote,
                $departedAt
            );

            $accessRevoked = false;
            if ($revokeAccess) {
                $accessRevoked = $this->revokeAccess($tenantId, $targetUserId, $actorUserId);
                if ($accessRevoked) {
                    $this->departureRepository->markAccessRevoked($departureId, $tenantId);
                }
            }
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'L’enregistrement du départ a échoué. Réessayez.'];
        }

        return [
            'ok' => true,
            'message' => 'Départ enregistré' . ($revokeAccess
                ? ($accessRevoked ? ' — accès retirés.' : ' — le retrait des accès a échoué, à refaire manuellement.')
                : '.'),
        ];
    }

    /**
     * Archive le dossier RH lié au départ (marquage administratif, sans purge).
     *
     * @return array{ok:bool,message:string}
     */
    public function archiveDossier(int $tenantId, int $departureId, int $actorUserId): array
    {
        $row = $this->departureRepository->findByIdForTenant($departureId, $tenantId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Départ introuvable.'];
        }
        if (!empty($row['dossier_archived'])) {
            return ['ok' => true, 'message' => 'Dossier déjà archivé.'];
        }
        if (!$this->departureRepository->markDossierArchived($departureId, $tenantId)) {
            return ['ok' => false, 'message' => 'Archivage indisponible (migration requise) ou déjà effectué.'];
        }
        $this->adminAuditService->logUserUpdated(
            $tenantId,
            $actorUserId,
            (int) ($row['user_id'] ?? 0),
            'dossier_open',
            'dossier_archived'
        );

        return ['ok' => true, 'message' => 'Dossier RH archivé.'];
    }

    /**
     * Réintégration : réactive le compte et marque le départ comme réintégré.
     * Ne restaure pas automatiquement les rôles (à reposer via élévation / affectation).
     *
     * @return array{ok:bool,message:string}
     */
    public function reinstate(int $tenantId, int $departureId, int $actorUserId): array
    {
        $row = $this->departureRepository->findByIdForTenant($departureId, $tenantId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Départ introuvable.'];
        }
        if (!empty($row['reinstated_at'])) {
            return ['ok' => true, 'message' => 'Réintégration déjà enregistrée.'];
        }
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId < 1) {
            return ['ok' => false, 'message' => 'Membre introuvable.'];
        }
        $user = $this->userRepository->findById($userId, $tenantId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Compte introuvable.'];
        }
        try {
            if ((string) ($user['status'] ?? '') !== 'active') {
                $this->userRepository->update($userId, $tenantId, ['status' => 'active']);
            }
            if (!$this->departureRepository->markReinstated($departureId, $tenantId, $actorUserId)) {
                return ['ok' => false, 'message' => 'Réintégration indisponible (migration requise).'];
            }
            $this->adminAuditService->logUserUpdated(
                $tenantId,
                $actorUserId,
                $userId,
                'inactive_departed',
                'active_reinstated'
            );
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'La réintégration a échoué. Réessayez.'];
        }

        return [
            'ok' => true,
            'message' => 'Membre réintégré (compte réactivé). Repassez les rôles et habilitations si besoin.',
        ];
    }

    /**
     * Retire les rôles organisation et l’habilitation en cours. Best-effort par étape :
     * un échec sur l’un n’empêche pas de tenter l’autre.
     */
    private function revokeAccess(int $tenantId, int $targetUserId, int $actorUserId): bool
    {
        $ok = true;
        try {
            $this->userRepository->syncOrganizationRoles($targetUserId, $tenantId, [], $actorUserId, true);
        } catch (Throwable) {
            $ok = false;
        }
        try {
            $this->personnelProfileRepository->ensureRecord($targetUserId);
            $this->personnelProfileRepository->update($targetUserId, [
                'clearance_level' => null,
                'clearance_reviewed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            $ok = false;
        }

        return $ok;
    }
}
