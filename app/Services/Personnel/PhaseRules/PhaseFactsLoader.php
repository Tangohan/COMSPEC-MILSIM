<?php

declare(strict_types=1);

namespace App\Services\Personnel\PhaseRules;

use App\Repositories\ArmaPlaytimeRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\PersonnelPhaseRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PersonnelCompletenessService;
use App\Services\Personnel\SeniorityEngine;

final class PhaseFactsLoader
{
    public function __construct(
        private UserRepository $users,
        private PersonnelProfileRepository $profiles,
        private PersonnelQualificationRepository $qualifications,
        private TrainingEnrollmentRepository $enrollments,
        private ArmaPlaytimeRepository $playtime,
        private RoleplayGameSessionRepository $sessions,
        private PersonnelCompletenessService $completeness,
        private SeniorityEngine $seniority,
        private ModerationRepository $moderation,
        private PersonnelPhaseRepository $phases,
    ) {}

    /**
     * @param array<string, mixed> $condition
     */
    public function load(int $tenantId, int $userId, array $condition = []): PhaseEvaluationContext
    {
        $user = $this->users->findById($userId, $tenantId) ?? [];
        $profile = $this->profiles->getByUserId($userId, $tenantId) ?? [];
        $archived = strtolower(trim((string) ($user['status'] ?? ''))) !== 'active';
        $rawSeconds = 0;
        try {
            if ($this->playtime->schemaReady()) {
                $sum = $this->playtime->getSummaryForUser($tenantId, $userId);
                $rawSeconds = (int) ($sum['total_seconds'] ?? 0);
            }
        } catch (\Throwable) {
            $rawSeconds = 0;
        }
        $validatedSeconds = 0;
        $categorySeconds = 0;
        $validatedSessions = 0;
        $kindSessions = 0;
        $checkedIn = 0;
        $rate = 0.0;
        try {
            if ($this->sessions->schemaReady()) {
                $validatedSeconds = $this->sessions->sumValidatedSeconds($tenantId, $userId, null, null, null);
                $cat = trim((string) ($condition['hour_category'] ?? ''));
                if ($cat !== '') {
                    $categorySeconds = $this->sessions->sumValidatedSeconds($tenantId, $userId, $cat, null, null);
                }
                $window = (int) ($condition['window_days'] ?? 0) ?: null;
                $kind = trim((string) ($condition['session_kind'] ?? ''));
                $validatedSessions = $this->sessions->countValidatedSessions($tenantId, $userId, null, $window);
                $kindSessions = $kind !== '' ? $this->sessions->countValidatedSessions($tenantId, $userId, $kind, $window) : 0;
                $checkedIn = $this->sessions->countCheckedIn($tenantId, $userId, $window);
                $rateRow = $this->sessions->attendanceRate($tenantId, $userId, $window ?: 90);
                $rate = (float) ($rateRow['rate'] ?? 0);
            }
        } catch (\Throwable) {
        }

        $qualId = (int) ($condition['qualification_id'] ?? 0);
        $heldId = 0;
        $qualValid = false;
        $qualLabel = 'la qualification demandée';
        if ($qualId > 0) {
            foreach ($this->qualifications->listForUser($userId) as $q) {
                if ((int) ($q['tenant_id'] ?? $tenantId) !== $tenantId && (int) ($q['tenant_id'] ?? 0) > 0) {
                    continue;
                }
                $defId = (int) ($q['definition_id'] ?? 0);
                $rowId = (int) ($q['id'] ?? 0);
                if ($defId !== $qualId && $rowId !== $qualId) {
                    continue;
                }
                $heldId = $qualId;
                $qualLabel = trim((string) ($q['qualification_name'] ?? '')) ?: $qualLabel;
                $status = strtolower(trim((string) ($q['status'] ?? 'valid')));
                $expires = trim((string) ($q['expires_at'] ?? ''));
                $expired = $expires !== '' && strtotime($expires) !== false && strtotime($expires) < time();
                $qualValid = in_array($status, ['valid', 'expiring'], true) && !$expired;
                break;
            }
        }

        $lmsId = (int) ($condition['training_module_id'] ?? 0);
        $lmsDone = false;
        $lmsLabel = 'le module demandé';
        if ($lmsId > 0) {
            foreach ($this->enrollments->listByUserId($userId, $tenantId) as $enr) {
                $courseId = (int) ($enr['course_id'] ?? 0);
                $moduleId = (int) ($enr['module_id'] ?? 0);
                if ($courseId !== $lmsId && $moduleId !== $lmsId) {
                    continue;
                }
                $lmsLabel = trim((string) ($enr['course_title'] ?? '')) ?: $lmsLabel;
                $lmsDone = strtolower(trim((string) ($enr['status'] ?? ''))) === 'completed';
                if ($lmsDone) {
                    break;
                }
            }
        }

        $seniorityDays = 0;
        try {
            $joined = trim((string) ($user['created_at'] ?? ''));
            if ($joined !== '') {
                $start = new \DateTimeImmutable($joined);
                $seniorityDays = (int) $start->diff(new \DateTimeImmutable('today'))->days;
            }
        } catch (\Throwable) {
            $seniorityDays = 0;
        }
        try {
            $computed = $this->seniority->compute(['calc_mode' => 'from_start'], [
                ['start_date' => substr((string) ($user['created_at'] ?? ''), 0, 10), 'status' => 'active'],
            ]);
            if ((int) ($computed['days'] ?? 0) > 0) {
                $seniorityDays = (int) $computed['days'];
            }
        } catch (\Throwable) {
        }

        $completeness = 0;
        try {
            $score = $this->completeness->getScore($userId, $user, null, null, $tenantId);
            $completeness = (int) ($score['score'] ?? 0);
        } catch (\Throwable) {
        }

        $hasSanction = false;
        try {
            $hasSanction = $this->moderation->listActiveActionsWithRestrictions($tenantId, $userId) !== [];
        } catch (\Throwable) {
        }

        return new PhaseEvaluationContext(
            tenantId: $tenantId,
            userId: $userId,
            archived: $archived,
            tenantActive: true,
            currentPhaseId: isset($profile['current_phase_id']) ? ((int) $profile['current_phase_id'] ?: null) : null,
            qualificationHeldId: $heldId > 0 ? $heldId : null,
            qualificationValid: $qualValid,
            lmsCompleted: $lmsDone,
            rawHours: round($rawSeconds / 3600, 2),
            validatedHours: round($validatedSeconds / 3600, 2),
            categoryHours: round($categorySeconds / 3600, 2),
            validatedSessions: $validatedSessions,
            kindSessions: $kindSessions,
            checkedInCount: $checkedIn,
            attendanceRate: $rate,
            seniorityDays: $seniorityDays,
            completenessPercent: $completeness,
            tutorAssigned: (int) ($profile['rp_tutor_user_id'] ?? 0) > 0,
            hasActiveSanction: $hasSanction,
            qualificationLabel: $qualLabel,
            lmsLabel: $lmsLabel,
            categoryLabel: trim((string) ($condition['hour_category'] ?? '')) ?: 'cette catégorie',
            kindLabel: trim((string) ($condition['session_kind'] ?? '')) ?: 'ce type de session',
        );
    }
}
