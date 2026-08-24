<?php

declare(strict_types=1);

namespace App\Services\MissionPlanning;

use App\Repositories\MissionPlanRepository;
use Throwable;

/**
 * Rapprochement UID Arma ↔ poste prévu, pendant la session.
 */
final class MissionPlanningLiveService
{
    public function __construct(
        private MissionPlanRepository $plans,
    ) {
    }

    /**
     * @param array<string,mixed> $user
     * @return array{matched:bool,mismatch:bool,callsign:?string,slot_id:?int,status:string}
     */
    public function onPlayerConnected(int $tenantId, array $user, string $steamUid, string $gameCallsign = ''): array
    {
        $empty = [
            'matched' => false,
            'mismatch' => false,
            'callsign' => null,
            'slot_id' => null,
            'status' => 'none',
        ];
        try {
            if (!$this->plans->tablesReady()) {
                return $empty;
            }
            $plan = $this->plans->findLiveForTenant($tenantId);
            if ($plan === null) {
                return $empty;
            }
            $planId = (int) ($plan['id'] ?? 0);
            $userId = (int) ($user['id'] ?? 0);
            if ($planId < 1 || $userId < 1) {
                return $empty;
            }

            $alreadyCurrent = $this->plans->findAssignmentByCurrentUser($planId, $userId);
            if ($alreadyCurrent !== null) {
                $slotId = (int) ($alreadyCurrent['slot_id'] ?? 0);
                $wasPresent = (string) ($alreadyCurrent['presence_status'] ?? '') === 'present';
                $this->markPresent($slotId, $alreadyCurrent, $steamUid, 'detected');
                $callsign = (string) ($alreadyCurrent['callsign'] ?? '');
                if (!$wasPresent) {
                    $this->plans->addLog($planId, $callsign . ' — présence reconnue à la connexion.', null);
                    $this->appendArmaTimeline($planId, $callsign . ' en liaison', 'CONN');
                }

                return [
                    'matched' => true,
                    'mismatch' => false,
                    'callsign' => $callsign !== '' ? $callsign : null,
                    'slot_id' => $slotId > 0 ? $slotId : null,
                    'status' => 'matched',
                ];
            }

            $planned = $this->plans->findAssignmentByPlannedUser($planId, $userId);
            if ($planned !== null) {
                $slotId = (int) ($planned['slot_id'] ?? 0);
                $currentOther = (int) ($planned['current_user_id'] ?? 0);
                if ($currentOther > 0 && $currentOther !== $userId) {
                    return $this->flagMismatch($planId, $slotId, $planned, $userId, $steamUid, false);
                }
                $this->plans->updateAssignment($slotId, [
                    'planned_user_id' => $userId,
                    'current_user_id' => $userId,
                    'detected_user_id' => $userId,
                    'assignment_mode' => 'detected',
                    'presence_status' => 'present',
                    'arma_uid' => $steamUid,
                    'notes' => '',
                ]);
                $callsign = (string) ($planned['callsign'] ?? '');
                $this->plans->addLog($planId, $callsign . ' — joueur prévu reconnu à la connexion.', null);
                $this->appendArmaTimeline($planId, $callsign . ' en liaison (poste prévu)', 'CONN');

                return [
                    'matched' => true,
                    'mismatch' => false,
                    'callsign' => $callsign !== '' ? $callsign : null,
                    'slot_id' => $slotId > 0 ? $slotId : null,
                    'status' => 'matched',
                ];
            }

            $byCallsign = $gameCallsign !== '' ? $this->plans->findSlotByCallsign($planId, $gameCallsign) : null;
            if ($byCallsign !== null) {
                $slotPlanned = (int) ($byCallsign['planned_user_id'] ?? 0);
                $slotId = (int) ($byCallsign['id'] ?? 0);
                if ($slotPlanned > 0 && $slotPlanned !== $userId) {
                    return $this->flagMismatch($planId, $slotId, $byCallsign, $userId, $steamUid, true);
                }
                $this->plans->updateAssignment($slotId, [
                    'planned_user_id' => $slotPlanned > 0 ? $slotPlanned : $userId,
                    'current_user_id' => $userId,
                    'detected_user_id' => $userId,
                    'assignment_mode' => $slotPlanned === $userId ? 'detected' : 'live',
                    'presence_status' => 'present',
                    'arma_uid' => $steamUid,
                    'notes' => '',
                ]);
                $cs = (string) ($byCallsign['callsign'] ?? $gameCallsign);
                $this->plans->addLog($planId, $cs . ' — présence rattachée à la connexion.', null);
                $this->appendArmaTimeline($planId, $cs . ' en liaison', 'CONN');

                return [
                    'matched' => true,
                    'mismatch' => false,
                    'callsign' => $cs !== '' ? $cs : null,
                    'slot_id' => $slotId > 0 ? $slotId : null,
                    'status' => 'matched',
                ];
            }

            return $empty;
        } catch (Throwable) {
            return $empty;
        }
    }

    public function onPlayerDisconnected(int $tenantId, string $callsign): void
    {
        try {
            if (!$this->plans->tablesReady()) {
                return;
            }
            $plan = $this->plans->findLiveForTenant($tenantId);
            if ($plan === null) {
                return;
            }
            $planId = (int) ($plan['id'] ?? 0);
            $cs = trim($callsign);
            if ($planId < 1 || $cs === '') {
                return;
            }
            $slot = $this->plans->findSlotByCallsign($planId, $cs);
            if ($slot === null) {
                return;
            }
            $presence = (string) ($slot['presence_status'] ?? '');
            if (!in_array($presence, ['present', 'temporary', 'mismatch'], true)) {
                return;
            }
            $slotId = (int) ($slot['id'] ?? 0);
            if ($slotId < 1) {
                return;
            }
            $this->plans->updateAssignment($slotId, [
                'planned_user_id' => $slot['planned_user_id'] ?? null,
                'current_user_id' => $slot['current_user_id'] ?? null,
                'detected_user_id' => $slot['detected_user_id'] ?? null,
                'assignment_mode' => (string) ($slot['assignment_mode'] ?? 'detected'),
                'presence_status' => 'absent',
                'arma_uid' => (string) ($slot['arma_uid'] ?? ''),
                'notes' => (string) ($slot['notes'] ?? ''),
            ]);
            $this->plans->addLog($planId, $cs . ' — hors liaison.', null);
            $this->appendArmaTimeline($planId, $cs . ' hors liaison', 'DISC');
        } catch (Throwable) {
        }
    }

    private function appendArmaTimeline(int $planId, string $label, string $code): void
    {
        if (!$this->plans->graphicsReady()) {
            return;
        }
        $this->plans->insertTimeline($planId, 'arma', $code, $label, null, date('Y-m-d H:i:s'));
    }

    /**
     * Un autre joueur occupe (ou devrait occuper) le poste : signaler l’écart.
     *
     * @param array<string,mixed> $assignment
     * @return array{matched:bool,mismatch:bool,callsign:?string,slot_id:?int,status:string}
     */
    public function recordUnexpectedOnSlot(int $planId, int $slotId, array $assignment, int $detectedUserId, string $steamUid): array
    {
        return $this->flagMismatch($planId, $slotId, $assignment, $detectedUserId, $steamUid, true);
    }

    /**
     * @param array<string,mixed> $assignment
     */
    private function markPresent(int $slotId, array $assignment, string $steamUid, string $mode): void
    {
        if ($slotId < 1) {
            return;
        }
        $this->plans->updateAssignment($slotId, [
            'planned_user_id' => $assignment['planned_user_id'] ?? null,
            'current_user_id' => $assignment['current_user_id'] ?? $assignment['planned_user_id'] ?? null,
            'detected_user_id' => $assignment['current_user_id'] ?? $assignment['planned_user_id'] ?? null,
            'assignment_mode' => $mode,
            'presence_status' => 'present',
            'arma_uid' => $steamUid,
            'notes' => (string) ($assignment['notes'] ?? ''),
        ]);
    }

    /**
     * @param array<string,mixed> $assignment
     * @return array{matched:bool,mismatch:bool,callsign:?string,slot_id:?int,status:string}
     */
    private function flagMismatch(
        int $planId,
        int $slotId,
        array $assignment,
        int $detectedUserId,
        string $steamUid,
        bool $log,
    ): array {
        $planned = (int) ($assignment['planned_user_id'] ?? 0);
        $this->plans->updateAssignment($slotId, [
            'planned_user_id' => $planned > 0 ? $planned : null,
            'current_user_id' => $assignment['current_user_id'] ?? ($planned > 0 ? $planned : null),
            'detected_user_id' => $detectedUserId,
            'assignment_mode' => 'detected',
            'presence_status' => 'mismatch',
            'arma_uid' => $steamUid,
            'notes' => 'Remplaçant détecté — décision commandement requise',
        ]);
        $callsign = (string) ($assignment['callsign'] ?? '');
        if ($log) {
            $this->plans->addLog($planId, $callsign . ' — remplaçant détecté, en attente de rapprochement.', null);
        } else {
            $this->plans->addLog($planId, $callsign . ' — un autre joueur est déjà sur le poste, écart signalé.', null);
        }

        return [
            'matched' => false,
            'mismatch' => true,
            'callsign' => $callsign !== '' ? $callsign : null,
            'slot_id' => $slotId > 0 ? $slotId : null,
            'status' => 'mismatch',
        ];
    }
}
