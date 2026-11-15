<?php

declare(strict_types=1);

namespace App\Services\MemberIntegration;

use App\Repositories\EnlistmentRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelRoleplayTimelineRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Services\Personnel\QualificationAwardRuleService;
use App\Services\Recruitment\EnlistmentAcceptanceProvisioningService;
use App\Services\Training\TrainingAssignmentService;
use App\Support\GradeSelectOptions;
use Throwable;

/** Branchement réel du paquet d'intégration sur les services existants (qualifications, formations, intégration). */
final class LiveIntegrationPorts implements IntegrationPorts
{
    public function __construct(
        private EnlistmentAcceptanceProvisioningService $provisioning,
        private EnlistmentRepository $enlistments,
        private EnlistmentTimelineRepository $enlistmentTimeline,
        private PersonnelRoleplayTimelineRepository $memberTimeline,
        private QualificationAwardRuleService $qualifications,
        private QualificationAwardRepository $awards,
        private TrainingAssignmentService $training,
        private TrainingEnrollmentRepository $enrollments,
        private UserRepository $users,
        private TenantRepository $tenants,
        private GradeRepository $grades,
        private UnitRepository $units,
        private PersonnelJobRoleRepository $jobRoles,
        private RoleRepository $roles,
        private EmailService $email,
        private UserNotificationPreferencesRepository $prefs,
    ) {
    }

    public function ensureAccount(int $tenantId, int $enlistmentId, int $actorId): int
    {
        $row = $this->enlistments->findForTenant($tenantId, $enlistmentId);
        if (!$row) {
            return 0;
        }
        $linked = (int) ($row['submitter_user_id'] ?? 0);
        if ($linked < 1 || $this->users->findById($linked, $tenantId) === null) {
            $this->provisioning->provisionAfterAccept($tenantId, $enlistmentId, $actorId, null);
            $row = $this->enlistments->findForTenant($tenantId, $enlistmentId) ?? $row;
            $linked = (int) ($row['submitter_user_id'] ?? 0);
        }

        return $linked > 0 && $this->users->findById($linked, $tenantId) !== null ? $linked : 0;
    }

    public function applyBase(int $tenantId, int $enlistmentId, int $actorId, array $options): array
    {
        return $this->provisioning->completeAcceptanceOnboarding($tenantId, $enlistmentId, $actorId, $options);
    }

    public function qualificationBlocker(int $tenantId, int $definitionId): ?string
    {
        $settings = $this->qualifications->settings($tenantId, $definitionId);
        if (empty($settings['allow_manual'])) {
            return 'elle ne se délivre pas à la main (elle s’obtient automatiquement).';
        }

        return null;
    }

    public function awardQualification(int $tenantId, int $userId, int $definitionId, string $status, int $validMonths, int $actorId): string
    {
        // Les règles de la qualification s'appliquent (délivrance à la main, prérequis) : aucun forçage.
        $message = $this->qualifications->setCell(
            $tenantId,
            $definitionId,
            $userId,
            $status === 'in_training' ? 'train' : 'ok',
            '',
            'Paquet d’intégration',
            '',
            $actorId,
            false
        );
        if (str_starts_with($message, 'Le membre détient déjà') || str_starts_with($message, 'Le membre est déjà en cours')) {
            return 'unchanged';
        }
        if ($validMonths > 0 && $status !== 'in_training') {
            foreach ($this->awards->listForUser($userId, $tenantId) as $award) {
                if ((int) ($award['definition_id'] ?? 0) === $definitionId && trim((string) ($award['obtained_at'] ?? '')) !== '') {
                    $until = (new \DateTimeImmutable((string) $award['obtained_at']))->modify('+' . $validMonths . ' months')->format('Y-m-d');
                    $this->awards->update((int) $award['id'], ['expires_at' => $until], $actorId);
                    break;
                }
            }
        }

        return 'applied';
    }

    public function enrollCourse(int $tenantId, int $userId, int $courseId, int $actorId): string
    {
        $existing = $this->enrollments->findByCourseAndUser($courseId, $userId);
        $active = $existing !== null && !in_array((string) ($existing['status'] ?? ''), ['revoked', 'expired', 'withdrawn'], true);
        $this->training->assignUser($courseId, $userId, $tenantId, $actorId > 0 ? $actorId : null, 'manual', null, null, false);

        return $active ? 'unchanged' : 'applied';
    }

    public function baseLabels(int $tenantId, array $options): array
    {
        $out = ['grade' => '', 'unit' => '', 'job' => '', 'roles' => []];
        try {
            if ((int) ($options['grade_id'] ?? 0) > 0) {
                $g = $this->grades->findById((int) $options['grade_id'], $tenantId);
                $out['grade'] = $g ? GradeSelectOptions::label($g) : '';
            }
            if ((int) ($options['unit_id'] ?? 0) > 0) {
                $u = $this->units->findById((int) $options['unit_id'], $tenantId);
                $out['unit'] = trim((string) ($u['name'] ?? ''));
            }
            if ((int) ($options['personnel_job_role_id'] ?? 0) > 0) {
                $j = $this->jobRoles->findRoleById((int) $options['personnel_job_role_id'], $tenantId);
                $out['job'] = trim((string) ($j['name'] ?? ''));
            }
            foreach ((array) ($options['role_ids'] ?? []) as $rid) {
                $r = $this->roles->findById((int) $rid, $tenantId);
                if ($r && trim((string) ($r['name'] ?? '')) !== '') {
                    $out['roles'][] = trim((string) $r['name']);
                }
            }
        } catch (Throwable) {
        }

        return $out;
    }

    public function journal(int $tenantId, int $userId, int $enlistmentId, int $actorId, string $title, string $detail): void
    {
        $this->memberTimeline->addEvent($tenantId, $userId, 'integration_package', $title, $detail, date('Y-m-d'), null, 'completed', null, $actorId > 0 ? $actorId : null);
        $this->enlistmentTimeline->logAdhesionStep($tenantId, $enlistmentId, $actorId, $title, $detail);
    }

    public function sendWelcome(int $tenantId, int $userId, array $recap): bool
    {
        if (!$this->prefs->isEmailEventEnabled($userId, EmailEvents::MEMBER_INTEGRATION_STARTED)) {
            return true;
        }
        $user = $this->users->findById($userId, $tenantId);
        $to = trim((string) ($user['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $tenant = $this->tenants->findById($tenantId);

        return $this->email->sendMemberIntegrationPackageRecap(
            $to,
            (string) ($user['display_name'] ?? 'Membre'),
            (string) ($tenant['name'] ?? 'Communauté'),
            $recap,
            url('mon-integration'),
            $tenantId
        );
    }
}
