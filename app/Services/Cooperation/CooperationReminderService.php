<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

use App\Repositories\InterteamMissionRepository;
use Throwable;

/**
 * Envois automatiques du module coopération (tâche planifiée « cooperation_reminders ») :
 *   1. signale les dates limites de réponse dépassées (proposal_deadline_notified_at) ;
 *   2. relance à J-2 les unités invitées sans réponse — une relance par unité et par 24 h,
 *      manuelle ou automatique confondues ;
 *   3. prévient les responsables dont l’autorisation de partage expire dans les 12 h —
 *      un avis par personne et par 24 h.
 * Chaque envoi est tracé dans le journal de la coopération.
 */
final class CooperationReminderService
{
    public function __construct(
        private InterteamMissionRepository $missions,
        private CooperationAnnouncementDispatcher $dispatcher
    ) {}

    /**
     * @return array{deadlines: int, reminders: int, consent_notices: int, errors: int}
     */
    public function run(?int $now = null): array
    {
        $now ??= time();
        $stats = ['deadlines' => 0, 'reminders' => 0, 'consent_notices' => 0, 'errors' => 0];

        foreach ($this->missions->listPendingDeadlineElapsedIds() as $missionId) {
            try {
                $this->missions->recordProposalDeadlineElapsedIfNeeded($missionId);
                $stats['deadlines']++;
            } catch (Throwable) {
                $stats['errors']++;
            }
        }

        $windowHours = (int) ceil(CooperationTransitionRules::AUTO_REMINDER_WINDOW_SECONDS / 3600);
        foreach ($this->missions->listPendingWithDeadlineWithin($windowHours) as $mission) {
            try {
                $stats['reminders'] += $this->remindPendingInvitations($mission, $now);
            } catch (Throwable) {
                $stats['errors']++;
            }
        }

        $noticeHours = (int) ceil(CooperationTransitionRules::CONSENT_EXPIRY_NOTICE_SECONDS / 3600);
        $lastByMission = [];
        foreach ($this->missions->listConsentsExpiringWithin($noticeHours) as $c) {
            try {
                $mid = $c['mission_id'];
                $lastByMission[$mid] ??= $this->missions->lastEventAtByPayloadKey($mid, 'consent_expiring', 'user_id', 48);
                $last = $lastByMission[$mid][$c['user_id']] ?? null;
                if (!CooperationTransitionRules::consentExpiryNoticeDue($c['consent_expires_at'], $last, $now)) {
                    continue;
                }
                $mission = $this->missions->findById($mid);
                if (!$mission) {
                    continue;
                }
                [$actorUid, $actorTid] = $this->systemActor($mission);
                $this->missions->logEvent($mid, $actorUid, $actorTid, 'consent_expiring', [
                    'user_id' => $c['user_id'],
                    'tenant_id' => $c['tenant_id'],
                    'expires_at' => $c['consent_expires_at'],
                ]);
                $this->dispatcher->dispatch(CooperationAnnouncementEvents::CONSENT_EXPIRING, $mid, $actorUid, $actorTid, [
                    'notify_user_id' => $c['user_id'],
                    'partner_tenant_id' => $c['tenant_id'],
                    'consent_until' => $c['consent_expires_at'],
                ]);
                $lastByMission[$mid][$c['user_id']] = date('Y-m-d H:i:s', $now);
                $stats['consent_notices']++;
            } catch (Throwable) {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Relance J-2 des unités invitées d’une proposition.
     *
     * @param array<string, mixed> $mission
     */
    private function remindPendingInvitations(array $mission, int $now): int
    {
        if (!CooperationTransitionRules::autoReminderDue($mission, $now)) {
            return 0;
        }
        $mid = (int) ($mission['id'] ?? 0);
        $participants = $this->missions->listParticipants($mid);
        $lastByTenant = $this->missions->lastInvitationReminderByTenant($mid, 48);
        [$actorUid, $actorTid] = $this->systemActor($mission);
        $sent = 0;
        foreach ($participants as $p) {
            $tid = (int) ($p['tenant_id'] ?? 0);
            $check = CooperationTransitionRules::canRemind($mission, $tid, $participants, $lastByTenant[$tid] ?? null, $now);
            if (!$check['allowed']) {
                continue;
            }
            $this->missions->logEvent($mid, $actorUid, $actorTid, 'invitation_reminder', ['partner_tenant_id' => $tid, 'manual' => false]);
            $this->dispatcher->dispatch(CooperationAnnouncementEvents::INVITATION_REMINDER, $mid, $actorUid, $actorTid, ['invited_tenant_id' => $tid]);
            $sent++;
        }

        return $sent;
    }

    /**
     * Les envois automatiques sont attribués à l’unité support (créatrice de la coopération).
     *
     * @param array<string, mixed> $mission
     * @return array{0: int, 1: int}
     */
    private function systemActor(array $mission): array
    {
        return [(int) ($mission['created_by_user_id'] ?? 0), (int) ($mission['created_by_tenant_id'] ?? 0)];
    }
}
