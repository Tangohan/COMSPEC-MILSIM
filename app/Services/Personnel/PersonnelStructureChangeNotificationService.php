<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * E-mails lors d’un changement de grade, d’affectation (unité) ou de fonction (rôle métier).
 * Un seul message combiné par sauvegarde réussie, pour le membre et éventuellement le staff RH.
 */
final class PersonnelStructureChangeNotificationService
{
    /**
     * Mêmes habilitations que les alertes d’élévation effectifs (RH / commandement communauté).
     *
     * @var list<string>
     */
    private const STAFF_PERMISSION_SLUGS = [
        'admin.access',
        'admin.organization',
        'admin.roles.manage',
        'personnel.grades.manage',
        'personnel.assignments.manage',
        'personnel.status.manage',
    ];

    public function __construct(
        private EmailService $emailService,
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private GradeRepository $gradeRepository,
        private UnitRepository $unitRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private ?PersonnelPromotionCelebrationService $promotionCelebrationService = null,
    ) {
        $this->promotionCelebrationService ??= new PersonnelPromotionCelebrationService(
            $this->gradeRepository,
            new \App\Repositories\TenantAlertRepository(),
            $this->userRepository,
        );
    }

    /**
     * État courant (identifiants + libellés métier) pour comparaison avant / après.
     *
     * @return array{
     *   grade_id: ?int,
     *   unit_id: ?int,
     *   job_role_id: ?int,
     *   grade_label: string,
     *   unit_label: string,
     *   job_role_label: string
     * }
     */
    public function snapshot(int $tenantId, int $userId): array
    {
        $user = $this->userRepository->findById($userId, $tenantId);
        $gradeId = $user && !empty($user['grade_id']) ? (int) $user['grade_id'] : null;
        if ($gradeId !== null && $gradeId < 1) {
            $gradeId = null;
        }

        $unitId = null;
        $unitLabel = '';
        $primary = $this->personnelAssignmentRepository->getPrimaryAssignment($userId);
        if ($primary) {
            $unitId = (int) ($primary['unit_id'] ?? 0) ?: null;
            $unitLabel = trim((string) ($primary['unit_name'] ?? ''));
        }
        if ($unitId === null) {
            $profile = $this->personnelProfileRepository->getByUserId($userId) ?? [];
            $fromProfile = (int) ($profile['primary_unit_id'] ?? 0);
            if ($fromProfile > 0) {
                $unitId = $fromProfile;
            }
        }
        if ($unitLabel === '' && $unitId !== null && $unitId > 0) {
            $u = $this->unitRepository->findById($unitId, $tenantId);
            $unitLabel = $u ? trim((string) ($u['name'] ?? '')) : '';
        }

        $jobRoleId = null;
        $jobRoleLabel = '';
        $profile = $this->personnelProfileRepository->getByUserId($userId) ?? [];
        if ($this->personnelJobRoleRepository->tablesExist()) {
            $jobRoleId = !empty($profile['personnel_job_role_id']) ? (int) $profile['personnel_job_role_id'] : null;
            if ($jobRoleId !== null && $jobRoleId < 1) {
                $jobRoleId = null;
            }
            if ($jobRoleId !== null) {
                $jr = $this->personnelJobRoleRepository->findRoleById($jobRoleId, $tenantId);
                $jobRoleLabel = $jr ? trim((string) ($jr['name'] ?? '')) : '';
            }
        }
        if ($jobRoleLabel === '') {
            $jobRoleLabel = trim((string) ($profile['primary_role'] ?? ''));
        }

        return [
            'grade_id' => $gradeId,
            'unit_id' => $unitId,
            'job_role_id' => $jobRoleId,
            'grade_label' => $this->gradeLabel($tenantId, $gradeId),
            'unit_label' => $unitLabel,
            'job_role_label' => $jobRoleLabel,
        ];
    }

    /**
     * Compare deux snapshots et envoie les e-mails si grade / affectation / fonction ont changé.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function notifyFromSnapshots(
        int $tenantId,
        int $targetUserId,
        ?int $actorUserId,
        array $before,
        array $after
    ): void {
        $changes = $this->diffSnapshots($before, $after);
        if ($changes === []) {
            return;
        }

        $this->notify($tenantId, $targetUserId, $actorUserId, $changes);

        $beforeGradeId = $before['grade_id'] ?? null;
        $afterGradeId = $after['grade_id'] ?? null;
        $this->promotionCelebrationService->celebrateIfPromotion(
            $tenantId,
            $targetUserId,
            $beforeGradeId !== null ? (int) $beforeGradeId : null,
            $afterGradeId !== null ? (int) $afterGradeId : null,
        );
    }

    /**
     * @param list<array{type: string, label: string, from: string, to: string}> $changes
     */
    public function notify(
        int $tenantId,
        int $targetUserId,
        ?int $actorUserId,
        array $changes
    ): void {
        if ($tenantId < 1 || $targetUserId < 1 || $changes === []) {
            return;
        }

        $target = $this->userRepository->findById($targetUserId, $tenantId);
        if (!$target) {
            return;
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = 'Communauté';
        if ($tenant) {
            $tenantName = function_exists('community_display_name')
                ? community_display_name($tenant)
                : trim((string) ($tenant['name'] ?? 'Communauté'));
        }
        if ($tenantName === '') {
            $tenantName = 'Communauté';
        }

        $targetName = trim((string) ($target['display_name'] ?? ''));
        if ($targetName === '') {
            $targetName = trim((string) ($target['callsign'] ?? ''));
        }
        if ($targetName === '') {
            $targetName = 'Membre';
        }

        $actorName = '';
        if ($actorUserId !== null && $actorUserId > 0) {
            $actor = $this->userRepository->findById($actorUserId, $tenantId);
            if ($actor) {
                $actorName = trim((string) ($actor['display_name'] ?? ''));
                if ($actorName === '') {
                    $actorName = trim((string) ($actor['callsign'] ?? ''));
                }
            }
        }

        $lines = [];
        foreach ($changes as $c) {
            $from = $c['from'] !== '' ? $c['from'] : 'Non renseigné';
            $to = $c['to'] !== '' ? $c['to'] : 'Non renseigné';
            $lines[] = $c['label'] . ' : ' . $from . ' → ' . $to;
        }

        $dossierUrl = function_exists('url')
            ? url('personnel/' . $this->personPathSegment($target))
            : '';

        $this->notifyMember(
            $tenantId,
            $targetUserId,
            $target,
            $targetName,
            $tenantName,
            $actorName,
            $lines,
            $dossierUrl
        );

        $this->notifyStaff(
            $tenantId,
            $targetUserId,
            $actorUserId,
            $targetName,
            $tenantName,
            $actorName,
            $lines,
            $dossierUrl
        );
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return list<array{type: string, label: string, from: string, to: string}>
     */
    private function diffSnapshots(array $before, array $after): array
    {
        $changes = [];

        $bg = (int) ($before['grade_id'] ?? 0);
        $ag = (int) ($after['grade_id'] ?? 0);
        if ($bg !== $ag) {
            $changes[] = [
                'type' => 'grade',
                'label' => 'Grade',
                'from' => trim((string) ($before['grade_label'] ?? '')),
                'to' => trim((string) ($after['grade_label'] ?? '')),
            ];
        }

        $bu = (int) ($before['unit_id'] ?? 0);
        $au = (int) ($after['unit_id'] ?? 0);
        $bul = trim((string) ($before['unit_label'] ?? ''));
        $aul = trim((string) ($after['unit_label'] ?? ''));
        if ($bu !== $au || ($bu === $au && $bu === 0 && $bul !== $aul)) {
            if ($bu !== $au || $bul !== $aul) {
                $changes[] = [
                    'type' => 'unit',
                    'label' => 'Affectation',
                    'from' => $bul,
                    'to' => $aul,
                ];
            }
        }

        $bj = (int) ($before['job_role_id'] ?? 0);
        $aj = (int) ($after['job_role_id'] ?? 0);
        $bjl = trim((string) ($before['job_role_label'] ?? ''));
        $ajl = trim((string) ($after['job_role_label'] ?? ''));
        if ($bj !== $aj || $bjl !== $ajl) {
            // Évite un faux positif si seuls des détails d’affichage bougent sans id ni libellé utile.
            if ($bj !== $aj || ($bjl !== '' || $ajl !== '')) {
                $changes[] = [
                    'type' => 'job_role',
                    'label' => 'Fonction',
                    'from' => $bjl,
                    'to' => $ajl,
                ];
            }
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string> $lines
     */
    private function notifyMember(
        int $tenantId,
        int $targetUserId,
        array $target,
        string $targetName,
        string $tenantName,
        string $actorName,
        array $lines,
        string $dossierUrl
    ): void {
        if (!$this->notificationPreferencesRepository->isEmailEventEnabled(
            $targetUserId,
            EmailEvents::PERSONNEL_STRUCTURE_CHANGED
        )) {
            return;
        }

        $email = strtolower(trim((string) ($target['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $this->emailService->sendPersonnelStructureChangedMember(
                $email,
                $targetName,
                $tenantName,
                $actorName,
                $lines,
                $dossierUrl,
                $tenantId
            );
        } catch (\Throwable) {
            // Notification optionnelle.
        }
    }

    /**
     * @param list<string> $lines
     */
    private function notifyStaff(
        int $tenantId,
        int $targetUserId,
        ?int $actorUserId,
        string $targetName,
        string $tenantName,
        string $actorName,
        array $lines,
        string $dossierUrl
    ): void {
        $exclude = array_filter([
            $targetUserId,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
        ], static fn ($id): bool => $id !== null && (int) $id > 0);

        $ids = $this->userRepository->listActiveUserIdsWithAnyPermissionSlug(
            $tenantId,
            self::STAFF_PERMISSION_SLUGS
        );
        if ($exclude !== []) {
            $excludeMap = array_fill_keys(array_map('intval', $exclude), true);
            $ids = array_values(array_filter(
                $ids,
                static fn (int $id): bool => !isset($excludeMap[$id])
            ));
        }
        if ($ids === []) {
            return;
        }

        $users = $this->userRepository->findByIdsForTenant($tenantId, $ids);
        $seenEmails = [];
        $memberUrl = function_exists('effectifs_workspace_url')
            ? effectifs_workspace_url('membres/' . $targetUserId)
            : $dossierUrl;

        foreach ($ids as $uid) {
            $user = $users[$uid] ?? null;
            if (!$user || (int) ($user['tenant_id'] ?? 0) !== $tenantId) {
                continue;
            }
            if (!$this->notificationPreferencesRepository->isEmailEventEnabled(
                $uid,
                EmailEvents::PERSONNEL_STRUCTURE_CHANGED_STAFF
            )) {
                continue;
            }
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seenEmails[$email])) {
                continue;
            }
            $seenEmails[$email] = true;
            $staffName = trim((string) ($user['display_name'] ?? ''));
            if ($staffName === '') {
                $staffName = trim((string) ($user['callsign'] ?? 'Responsable'));
            }

            try {
                $this->emailService->sendPersonnelStructureChangedStaff(
                    $email,
                    $staffName,
                    $targetName,
                    $tenantName,
                    $actorName,
                    $lines,
                    $memberUrl !== '' ? $memberUrl : $dossierUrl,
                    $tenantId
                );
            } catch (\Throwable) {
                // Continuer les autres destinataires.
            }
        }
    }

    private function gradeLabel(int $tenantId, ?int $gradeId): string
    {
        if ($gradeId === null || $gradeId < 1) {
            return '';
        }
        $g = $this->gradeRepository->findById($gradeId, $tenantId);
        if (!$g) {
            return '';
        }
        $short = trim((string) ($g['label_short'] ?? ''));
        $long = trim((string) ($g['label_long'] ?? ''));

        return $short !== '' ? $short : $long;
    }

    /** @param array<string, mixed> $userRow */
    private function personPathSegment(array $userRow): string
    {
        $slug = trim((string) ($userRow['profile_slug'] ?? ''));

        return $slug !== '' ? $slug : (string) ($userRow['id'] ?? '');
    }
}
