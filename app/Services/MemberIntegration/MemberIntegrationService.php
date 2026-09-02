<?php

declare(strict_types=1);

namespace App\Services\MemberIntegration;

use App\Repositories\MemberIntegrationAppointmentRepository;
use App\Repositories\MemberIntegrationRepository;
use App\Repositories\MemberIntegrationTemplateRepository;
use App\Repositories\EnlistmentRecruitmentEngagementRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\PersonnelStageBilanRepository;
use App\Repositories\TrainingCompetencyRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Personnel\PersonnelCompletenessService;
use App\Services\Training\TrainingAssignmentService;
use App\Support\MemberIntegrationCatalog;
use DateTimeImmutable;
use Throwable;

final class MemberIntegrationService
{
    public function __construct(
        private MemberIntegrationRepository $integrations,
        private MemberIntegrationTemplateRepository $templates,
        private MemberIntegrationAppointmentRepository $appointments,
        private MemberIntegrationProgressService $progress,
        private UserRepository $users,
        private UserProfileRepository $userProfiles,
        private PersonnelCompletenessService $completeness,
        private PersonnelExtrasRepository $extras,
        private PersonnelStageBilanRepository $bilans,
        private EnlistmentRecruitmentEngagementRepository $recruitmentBilans,
        private TrainingCompetencyRepository $matrices,
        private TrainingEnrollmentRepository $enrollments,
        private TrainingAssignmentService $assignmentService,
        private AuditService $audit,
    ) {}

    /**
     * @param array{role_ids?: list<int>, unit_ids?: list<int>} $context
     * @return array{ok: bool, created: bool, integration_id?: int, message?: string}
     */
    public function instantiateForUser(
        int $tenantId,
        int $userId,
        int $actorUserId,
        string $source,
        ?int $templateId = null,
        array $context = []
    ): array {
        if (!$this->integrations->tablesExist()) {
            return ['ok' => false, 'created' => false, 'message' => 'Le suivi d’intégration n’est pas encore disponible.'];
        }
        $existing = $this->integrations->findActiveForUser($tenantId, $userId);
        if ($existing) {
            return ['ok' => true, 'created' => false, 'integration_id' => (int) $existing['id']];
        }
        $user = $this->users->findById($userId, $tenantId);
        if (!$user) {
            return ['ok' => false, 'created' => false, 'message' => 'Membre introuvable dans cette communauté.'];
        }
        $template = null;
        if ($templateId !== null && $templateId > 0) {
            $template = $this->templates->findForTenant($tenantId, $templateId);
        }
        if ($template === null) {
            $template = $this->templates->matchTemplate($tenantId, array_merge($context, ['source' => $source]));
        }
        if ($template === null) {
            return ['ok' => false, 'created' => false, 'message' => 'Aucun parcours d’intégration n’est configuré.'];
        }
        $stepsTpl = $this->templates->listSteps($tenantId, (int) $template['id']);
        $duration = (int) ($template['duration_days'] ?? 0);
        $target = $duration > 0 ? (new DateTimeImmutable('+' . $duration . ' days'))->format('Y-m-d H:i:s') : null;
        $id = $this->integrations->create($tenantId, [
            'user_id' => $userId,
            'template_id' => (int) $template['id'],
            'template_version' => (int) ($template['version'] ?? 1),
            'status' => MemberIntegrationCatalog::STATUS_TO_START,
            'source' => $source,
            'started_at' => date('Y-m-d H:i:s'),
            'target_completion_at' => $target,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'primary_referent_user_id' => !empty($template['default_referent_user_id'])
                ? (int) $template['default_referent_user_id']
                : null,
        ]);
        if ($id < 1) {
            return ['ok' => false, 'created' => false, 'message' => 'Le suivi n’a pas pu être créé.'];
        }
        $started = date('Y-m-d H:i:s');
        foreach ($stepsTpl as $tplStep) {
            $dueDays = isset($tplStep['due_after_days']) ? (int) $tplStep['due_after_days'] : 0;
            $dueAt = $dueDays > 0
                ? (new DateTimeImmutable('+' . $dueDays . ' days'))->format('Y-m-d H:i:s')
                : null;
            $this->integrations->createStep($tenantId, $id, [
                'template_step_id' => (int) $tplStep['id'],
                'step_key' => (string) $tplStep['step_key'],
                'position' => (int) $tplStep['position'],
                'title' => (string) $tplStep['title'],
                'description' => $tplStep['description'] ?? null,
                'step_type' => (string) $tplStep['step_type'],
                'responsible_kind' => (string) $tplStep['responsible_kind'],
                'due_at' => $dueAt,
                'is_required' => !empty($tplStep['is_required']),
                'is_member_visible' => !empty($tplStep['is_member_visible']),
                'linked_matrix_id' => $tplStep['linked_matrix_id'] ?? null,
                'linked_course_id' => $tplStep['linked_course_id'] ?? null,
                'linked_document_id' => $tplStep['linked_document_id'] ?? null,
                'configuration_json' => $tplStep['configuration_json'] ?? null,
            ]);
        }
        $primary = (int) ($template['default_referent_user_id'] ?? 0);
        if ($primary > 0) {
            $this->integrations->setReferents($tenantId, $id, $primary, []);
        }
        $this->integrations->addEvent(
            $tenantId,
            $id,
            'created',
            MemberIntegrationCatalog::VISIBILITY_MEMBER,
            'Le parcours d’intégration a été ouvert.',
            $actorUserId > 0 ? $actorUserId : null,
            null,
            ['source' => $source, 'template_id' => (int) $template['id'], 'template_version' => (int) ($template['version'] ?? 1)]
        );
        if ($primary > 0) {
            $this->integrations->addEvent(
                $tenantId,
                $id,
                'referent_assigned',
                MemberIntegrationCatalog::VISIBILITY_MEMBER,
                'Un référent a été désigné.',
                $actorUserId > 0 ? $actorUserId : null
            );
        }
        $this->applyTemplateMatrices($tenantId, $userId, $stepsTpl, $actorUserId, $id);
        $this->applyOptionalLms($tenantId, $userId, $stepsTpl, $actorUserId, $id);
        $this->refresh($tenantId, $id, $actorUserId);
        try {
            $this->audit->log(
                AuditAction::MEMBER_INTEGRATION_CREATED,
                $tenantId,
                $actorUserId > 0 ? $actorUserId : null,
                'member_integration',
                $id,
                null,
                json_encode(['user_id' => $userId, 'source' => $source], JSON_UNESCAPED_UNICODE)
            );
        } catch (Throwable) {
        }

        return ['ok' => true, 'created' => true, 'integration_id' => $id, 'started_at' => $started];
    }

    public function refresh(int $tenantId, int $integrationId, ?int $actorUserId = null): ?array
    {
        $row = $this->integrations->findForTenant($tenantId, $integrationId);
        if (!$row) {
            return null;
        }
        if (MemberIntegrationCatalog::isTerminalStatus((string) $row['status'])) {
            return $row;
        }
        $steps = $this->integrations->listSteps($tenantId, $integrationId);
        $userId = (int) $row['user_id'];
        $user = $this->users->findById($userId, $tenantId) ?? [];
        $dossierComplete = $this->isDossierComplete($userId, $user, $tenantId);
        foreach ($steps as $step) {
            $this->autoAdvanceStep($tenantId, $row, $step, $user, $dossierComplete, $actorUserId);
        }
        $steps = $this->integrations->listSteps($tenantId, $integrationId);
        $computed = $this->progress->compute($steps);
        $nextAppt = $this->appointments->nextUpcomingForIntegration($tenantId, $integrationId);
        $newStatus = $computed['status'];
        if ($computed['can_complete']) {
            $newStatus = MemberIntegrationCatalog::STATUS_COMPLETED;
        }
        $currentId = isset($computed['current_step']['id']) ? (int) $computed['current_step']['id'] : null;
        $update = [
            'progress_percent' => $computed['progress_percent'],
            'overdue_count' => $computed['overdue_count'],
            'dossier_complete' => $dossierComplete ? 1 : 0,
            'current_step_id' => $currentId,
            'next_appointment_at' => $nextAppt['starts_at'] ?? null,
        ];
        if ($newStatus === MemberIntegrationCatalog::STATUS_COMPLETED && (string) $row['status'] !== MemberIntegrationCatalog::STATUS_COMPLETED) {
            $update['status'] = MemberIntegrationCatalog::STATUS_COMPLETED;
            $update['completed_at'] = date('Y-m-d H:i:s');
            $this->integrations->addEvent(
                $tenantId,
                $integrationId,
                'closed',
                MemberIntegrationCatalog::VISIBILITY_MEMBER,
                'L’intégration est terminée : toutes les étapes obligatoires sont validées.',
                $actorUserId
            );
        } elseif ($newStatus !== MemberIntegrationCatalog::STATUS_COMPLETED) {
            $update['status'] = $newStatus;
        }
        if ($dossierComplete && empty($row['dossier_complete'])) {
            $this->integrations->addEvent(
                $tenantId,
                $integrationId,
                'dossier_complete',
                MemberIntegrationCatalog::VISIBILITY_STAFF,
                'Le dossier personnel est désormais complet.',
                $actorUserId
            );
        }
        $this->integrations->update($tenantId, $integrationId, $update);

        if ($newStatus === MemberIntegrationCatalog::STATUS_COMPLETED && (string) $row['status'] !== MemberIntegrationCatalog::STATUS_COMPLETED) {
            try {
                $duty = \App\Core\Container::get(\App\Services\Personnel\PersonnelDutyPositionService::class);
                $duty->applyActiveDuty($tenantId, $userId, $actorUserId ?? 0);
            } catch (Throwable) {
            }
        }

        return $this->integrations->findForTenant($tenantId, $integrationId);
    }

    /**
     * @param array<string, mixed> $integration
     * @param array<string, mixed> $step
     * @param array<string, mixed> $user
     */
    private function autoAdvanceStep(
        int $tenantId,
        array $integration,
        array $step,
        array $user,
        bool $dossierComplete,
        ?int $actorUserId
    ): void {
        $status = (string) ($step['status'] ?? '');
        if (MemberIntegrationCatalog::isStepDone($status) || $status === MemberIntegrationCatalog::STEP_CANCELLED) {
            return;
        }
        $type = (string) ($step['step_type'] ?? '');
        $userId = (int) $integration['user_id'];
        $done = false;
        if ($type === MemberIntegrationCatalog::TYPE_PERSONNEL_DOSSIER) {
            $done = $dossierComplete;
        } elseif ($type === MemberIntegrationCatalog::TYPE_MATRIX_ASSIGN) {
            $mid = (int) ($step['linked_matrix_id'] ?? 0);
            if ($mid > 0) {
                foreach ($this->matrices->listAssignmentsForUser($tenantId, $userId) as $as) {
                    if ((int) ($as['matrix_id'] ?? 0) === $mid) {
                        $done = true;
                        break;
                    }
                }
            }
        } elseif ($type === MemberIntegrationCatalog::TYPE_LMS_OPTIONAL) {
            $courseId = (int) ($step['linked_course_id'] ?? 0);
            if ($courseId > 0) {
                $enr = $this->enrollments->findByCourseAndUser($courseId, $userId);
                $cfg = json_decode((string) ($step['configuration_json'] ?? ''), true);
                $needComplete = is_array($cfg) && !empty($cfg['require_completed']);
                if ($enr) {
                    $st = (string) ($enr['status'] ?? '');
                    $done = $needComplete ? $st === 'completed' : !in_array($st, ['revoked', 'expired', 'withdrawn'], true);
                }
            }
        } elseif ($type === MemberIntegrationCatalog::TYPE_STAGE_BILAN) {
            if (!empty($step['linked_personnel_bilan_id'])) {
                $done = true;
            } else {
                $label = trim((string) ($step['title'] ?? ''));
                foreach ($this->bilans->listForUser($tenantId, $userId, 20) as $b) {
                    if ($label !== '' && strcasecmp((string) ($b['stage_label'] ?? ''), $label) === 0) {
                        $done = true;
                        break;
                    }
                }
            }
        } elseif ($type === MemberIntegrationCatalog::TYPE_EVENT_INVITE || $type === MemberIntegrationCatalog::TYPE_APPOINTMENT) {
            $appts = $this->appointments->listForIntegration($tenantId, (int) $integration['id']);
            foreach ($appts as $appt) {
                if ((int) ($appt['step_id'] ?? 0) !== (int) $step['id']) {
                    continue;
                }
                $inv = $this->appointments->findInvitationForAppointmentUser($tenantId, (int) $appt['id'], $userId);
                if ($inv && in_array((string) ($inv['status'] ?? ''), [
                    MemberIntegrationCatalog::RSVP_ACCEPTED,
                    MemberIntegrationCatalog::RSVP_TENTATIVE,
                ], true)) {
                    $done = true;
                    break;
                }
            }
        }
        if ($done) {
            $this->integrations->updateStep($tenantId, (int) $step['id'], [
                'status' => MemberIntegrationCatalog::STEP_COMPLETED,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            $this->integrations->addEvent(
                $tenantId,
                (int) $integration['id'],
                'step_completed',
                MemberIntegrationCatalog::VISIBILITY_MEMBER,
                'Étape terminée : ' . (string) ($step['title'] ?? 'étape'),
                $actorUserId,
                (int) $step['id']
            );
        }
    }

    public function isDossierComplete(int $userId, array $user, int $tenantId): bool
    {
        $profile = $this->userProfiles->getByUserId($userId) ?? [];
        $extras = [];
        try {
            $extras = $this->extras->getByUserId($userId) ?? [];
        } catch (Throwable) {
            $extras = [];
        }
        $score = $this->completeness->getScoreWithMissingLabels($userId, $user, $profile, $extras, $tenantId);

        return ($score['sections_critiques'] ?? []) === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function dossierSnapshot(int $userId, array $user, int $tenantId): array
    {
        $profile = $this->userProfiles->getByUserId($userId) ?? [];
        $extras = [];
        try {
            $extras = $this->extras->getByUserId($userId) ?? [];
        } catch (Throwable) {
            $extras = [];
        }
        $score = $this->completeness->getScoreWithMissingLabels($userId, $user, $profile, $extras, $tenantId);
        $onboarding = null;
        try {
            $onboarding = $this->userProfiles->getOnboardingState($userId);
        } catch (Throwable) {
            $onboarding = null;
        }

        $recruitment = [];
        try {
            $recruitment = $this->recruitmentBilans->listRetrosForSubmitter($tenantId, $userId, 20);
        } catch (Throwable) {
            $recruitment = [];
        }

        return [
            'score' => $score,
            'portal_onboarding' => $onboarding,
            'bilans' => $this->bilans->listForUser($tenantId, $userId, 20),
            'recruitment_bilans' => $recruitment,
        ];
    }

    public function completeStep(
        int $tenantId,
        int $integrationId,
        int $stepId,
        int $actorUserId,
        bool $force = false,
        ?string $reason = null
    ): array {
        $row = $this->integrations->findForTenant($tenantId, $integrationId);
        $step = $this->integrations->findStep($tenantId, $stepId);
        if (!$row || !$step || (int) $step['integration_id'] !== $integrationId) {
            return ['ok' => false, 'message' => 'Étape introuvable.'];
        }
        if ($force && trim((string) $reason) === '') {
            return ['ok' => false, 'message' => 'Indiquez un motif pour valider cette étape manuellement.'];
        }
        $fields = [
            'status' => MemberIntegrationCatalog::STEP_COMPLETED,
            'completed_at' => date('Y-m-d H:i:s'),
            'validated_by' => $actorUserId,
        ];
        if ($force) {
            $fields['force_reason'] = mb_substr(trim((string) $reason), 0, 500);
        }
        $this->integrations->updateStep($tenantId, $stepId, $fields);
        $this->integrations->addEvent(
            $tenantId,
            $integrationId,
            $force ? 'step_forced' : 'step_completed',
            MemberIntegrationCatalog::VISIBILITY_STAFF,
            ($force ? 'Validation forcée : ' : 'Étape validée : ') . (string) ($step['title'] ?? ''),
            $actorUserId,
            $stepId,
            $force ? ['reason' => $reason] : []
        );
        if ($force) {
            try {
                $this->audit->log(
                    AuditAction::MEMBER_INTEGRATION_STEP_FORCED,
                    $tenantId,
                    $actorUserId,
                    'member_integration_step',
                    $stepId,
                    null,
                    json_encode(['reason' => $reason, 'integration_id' => $integrationId], JSON_UNESCAPED_UNICODE)
                );
            } catch (Throwable) {
            }
        }
        $this->refresh($tenantId, $integrationId, $actorUserId);

        return ['ok' => true];
    }

    public function addNote(int $tenantId, int $integrationId, int $actorUserId, string $message, bool $visibleToMember): bool
    {
        $message = trim($message);
        if ($message === '' || !$this->integrations->findForTenant($tenantId, $integrationId)) {
            return false;
        }
        $this->integrations->addEvent(
            $tenantId,
            $integrationId,
            'note',
            $visibleToMember ? MemberIntegrationCatalog::VISIBILITY_MEMBER : MemberIntegrationCatalog::VISIBILITY_STAFF,
            mb_substr($message, 0, 4000),
            $actorUserId
        );

        return true;
    }

    public function assignReferents(int $tenantId, int $integrationId, int $primaryUserId, array $secondary, int $actorUserId): bool
    {
        if (!$this->integrations->findForTenant($tenantId, $integrationId)) {
            return false;
        }
        $this->integrations->setReferents($tenantId, $integrationId, $primaryUserId, $secondary);
        $this->integrations->update($tenantId, $integrationId, [
            'primary_referent_user_id' => $primaryUserId > 0 ? $primaryUserId : null,
        ]);
        $this->integrations->addEvent(
            $tenantId,
            $integrationId,
            'referent_assigned',
            MemberIntegrationCatalog::VISIBILITY_MEMBER,
            'Le référent a été mis à jour.',
            $actorUserId
        );

        return true;
    }

    public function assignMatrix(int $tenantId, int $userId, int $matrixId, int $actorUserId, int $integrationId): int
    {
        $n = $this->matrices->assignMatrixToUsers($tenantId, $matrixId, $actorUserId, [$userId], 'manual');
        if ($n > 0) {
            $this->integrations->addEvent(
                $tenantId,
                $integrationId,
                'matrix_added',
                MemberIntegrationCatalog::VISIBILITY_STAFF,
                'Le membre a été placé dans un groupe de suivi.',
                $actorUserId,
                null,
                ['matrix_id' => $matrixId]
            );
        }
        $this->refresh($tenantId, $integrationId, $actorUserId);

        return $n;
    }

    public function unassignMatrix(int $tenantId, int $userId, int $matrixId, int $actorUserId, int $integrationId): bool
    {
        $ok = $this->matrices->unassignUserFromMatrix($tenantId, $matrixId, $userId);
        if ($ok) {
            $this->integrations->addEvent(
                $tenantId,
                $integrationId,
                'matrix_removed',
                MemberIntegrationCatalog::VISIBILITY_STAFF,
                'Le membre a été retiré d’un groupe de suivi.',
                $actorUserId,
                null,
                ['matrix_id' => $matrixId]
            );
        }

        return $ok;
    }

    public function autoDetectMatrices(int $tenantId, int $matrixId, int $actorUserId): int
    {
        $matrix = $this->matrices->findMatrix($tenantId, $matrixId);
        if (!$matrix) {
            return 0;
        }
        $rules = json_decode((string) ($matrix['auto_detect_rules_json'] ?? ''), true);
        if (!is_array($rules)) {
            $rules = [];
        }
        $ids = $this->matrices->autoDetectCandidateUserIds($tenantId, $rules);

        return $this->matrices->assignMatrixToUsers($tenantId, $matrixId, $actorUserId, $ids, 'auto_detect');
    }

    public function cancel(int $tenantId, int $integrationId, int $actorUserId, string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            return false;
        }
        $this->integrations->update($tenantId, $integrationId, [
            'status' => MemberIntegrationCatalog::STATUS_CANCELLED,
            'cancelled_at' => date('Y-m-d H:i:s'),
        ]);
        $this->integrations->addEvent(
            $tenantId,
            $integrationId,
            'cancelled',
            MemberIntegrationCatalog::VISIBILITY_STAFF,
            'Parcours annulé : ' . mb_substr($reason, 0, 400),
            $actorUserId
        );

        return true;
    }

    public function reopen(int $tenantId, int $integrationId, int $actorUserId): bool
    {
        $row = $this->integrations->findForTenant($tenantId, $integrationId);
        if (!$row) {
            return false;
        }
        $active = $this->integrations->findActiveForUser($tenantId, (int) $row['user_id']);
        if ($active && (int) $active['id'] !== $integrationId) {
            return false;
        }
        $this->integrations->update($tenantId, $integrationId, [
            'status' => MemberIntegrationCatalog::STATUS_IN_PROGRESS,
            'cancelled_at' => null,
            'completed_at' => null,
        ]);
        $this->integrations->addEvent(
            $tenantId,
            $integrationId,
            'reopened',
            MemberIntegrationCatalog::VISIBILITY_STAFF,
            'Le parcours d’intégration a été rouvert.',
            $actorUserId
        );
        $this->refresh($tenantId, $integrationId, $actorUserId);

        return true;
    }

    /**
     * @param list<array<string, mixed>> $stepsTpl
     */
    private function applyTemplateMatrices(int $tenantId, int $userId, array $stepsTpl, int $actorUserId, int $integrationId): void
    {
        foreach ($stepsTpl as $step) {
            $mid = (int) ($step['linked_matrix_id'] ?? 0);
            if ($mid < 1) {
                continue;
            }
            $n = $this->matrices->assignMatrixToUsers($tenantId, $mid, $actorUserId, [$userId], 'manual');
            if ($n > 0) {
                $this->integrations->addEvent(
                    $tenantId,
                    $integrationId,
                    'matrix_added',
                    MemberIntegrationCatalog::VISIBILITY_STAFF,
                    'Groupe de suivi appliqué à l’ouverture du parcours.',
                    $actorUserId,
                    null,
                    ['matrix_id' => $mid]
                );
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $stepsTpl
     */
    private function applyOptionalLms(int $tenantId, int $userId, array $stepsTpl, int $actorUserId, int $integrationId): void
    {
        foreach ($stepsTpl as $step) {
            if ((string) ($step['step_type'] ?? '') !== MemberIntegrationCatalog::TYPE_LMS_OPTIONAL) {
                continue;
            }
            $courseId = (int) ($step['linked_course_id'] ?? 0);
            if ($courseId < 1) {
                continue;
            }
            try {
                $this->assignmentService->assignUser($courseId, $userId, $tenantId, $actorUserId > 0 ? $actorUserId : null, 'manual');
                $this->integrations->addEvent(
                    $tenantId,
                    $integrationId,
                    'lms_assigned',
                    MemberIntegrationCatalog::VISIBILITY_MEMBER,
                    'Une formation facultative a été proposée.',
                    $actorUserId,
                    null,
                    ['course_id' => $courseId]
                );
            } catch (Throwable) {
                // Politique d’inscription ou formation absente : non bloquant.
            }
        }
    }
}
