<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserProfileDisplaySettingsRepository;

/**
 * Données affichées sur l’écran d’accueil post-connexion (lockscreen).
 */
final class LoginWelcomeProfileService
{
    public function __construct(
        private PersonnelProfileRepository $personnelProfiles,
        private PersonnelAssignmentRepository $assignments,
        private GradeRepository $grades,
        private UserProfileDisplaySettingsRepository $displaySettings,
        private PersonnelJobRoleRepository $jobRoles,
        private UnitRepository $units,
    ) {}

    /**
     * @param array<string, mixed> $user
     * @return array{
     *   display_name: string,
     *   grade_label: string,
     *   avatar_url: ?string,
     *   initials: string,
     *   account_facts: list<array{label: string, value: string}>
     * }
     */
    public function build(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $displayName = trim((string) ($user['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($user['callsign'] ?? ''));
        }
        if ($displayName === '') {
            $local = explode('@', (string) ($user['email'] ?? ''), 2)[0] ?? '';
            $displayName = $local !== '' ? $local : 'Opérateur';
        }

        $profile = $userId > 0 ? $this->personnelProfiles->getByUserId($userId) : null;
        $displayPrefs = null;
        try {
            $displayPrefs = $userId > 0 ? $this->displaySettings->getByUserId($userId) : null;
        } catch (\Throwable) {
            $displayPrefs = null;
        }

        $avatarUrl = null;
        if (function_exists('user_site_avatar_url')) {
            $avatarUrl = user_site_avatar_url(
                $user,
                is_array($profile) ? $profile : null,
                is_array($displayPrefs) ? $displayPrefs : null
            );
        }

        $gradeLabel = $this->resolveGradeLabel($tenantId, $user);

        return [
            'display_name' => $displayName,
            'grade_label' => $gradeLabel,
            'avatar_url' => $avatarUrl,
            'initials' => function_exists('user_display_initials')
                ? user_display_initials($displayName, 2)
                : mb_strtoupper(mb_substr($displayName, 0, 2)),
            'account_facts' => $this->accountFacts(
                $userId,
                $tenantId,
                is_array($profile) ? $profile : null,
                $user
            ),
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function resolveGradeLabel(int $tenantId, array $user): string
    {
        $gradeId = !empty($user['grade_id']) ? (int) $user['grade_id'] : 0;
        if ($gradeId < 1) {
            return '';
        }
        try {
            $grade = $this->grades->findById($gradeId, $tenantId > 0 ? $tenantId : null);
        } catch (\Throwable) {
            return '';
        }
        if (!is_array($grade)) {
            return '';
        }
        $long = trim((string) ($grade['label_long'] ?? ''));
        if ($long !== '') {
            return $long;
        }

        return trim((string) ($grade['label_short'] ?? $grade['label_otan'] ?? ''));
    }

    /**
     * @param array<string, mixed>|null $profile
     * @param array<string, mixed> $user
     * @return list<array{label: string, value: string}>
     */
    private function accountFacts(int $userId, int $tenantId, ?array $profile, array $user): array
    {
        return [
            ['label' => 'Ancienneté', 'value' => $this->resolveSeniorityLabel($profile, $user)],
            ['label' => 'Rôle / Fonction', 'value' => $this->resolveFunctionLabel($tenantId, $profile)],
            ['label' => 'Affectation', 'value' => $this->resolveAssignmentLabel($userId, $tenantId, $profile)],
        ];
    }

    /**
     * @param array<string, mixed>|null $profile
     * @param array<string, mixed> $user
     */
    private function resolveSeniorityLabel(?array $profile, array $user): string
    {
        $raw = is_array($profile) ? ($profile['enlistment_date'] ?? $profile['date_of_enlistment'] ?? null) : null;
        if (!$raw) {
            $raw = $user['created_at'] ?? null;
        }
        if (!$raw) {
            return 'Non renseignée';
        }
        $ts = strtotime((string) $raw);
        if ($ts === false) {
            return 'Non renseignée';
        }
        $daysSince = max(0, (int) floor((time() - $ts) / 86400));
        $yearsSince = intdiv($daysSince, 365);
        $monthsSince = intdiv($daysSince % 365, 30);
        if ($yearsSince > 0) {
            $label = $yearsSince . ' an' . ($yearsSince > 1 ? 's' : '');
            if ($monthsSince > 0) {
                $label .= ' et ' . $monthsSince . ' mois';
            }

            return $label;
        }
        if ($monthsSince > 0) {
            return $monthsSince . ' mois';
        }
        if ($daysSince > 0) {
            return $daysSince . ' jour' . ($daysSince > 1 ? 's' : '');
        }

        return 'Moins d’un jour';
    }

    /**
     * @param array<string, mixed>|null $profile
     */
    private function resolveFunctionLabel(int $tenantId, ?array $profile): string
    {
        if (!is_array($profile)) {
            return 'Non renseignée';
        }

        $jobRoleId = (int) ($profile['personnel_job_role_id'] ?? 0);
        if ($jobRoleId > 0 && $tenantId > 0) {
            try {
                $jobRole = $this->jobRoles->findRoleById($jobRoleId, $tenantId);
                if (is_array($jobRole)) {
                    $label = trim((string) ($jobRole['name'] ?? ''));
                    $subLabel = trim((string) ($profile['role_sub_label'] ?? ''));
                    if ($label !== '') {
                        return $subLabel !== '' ? $label . ' — ' . $subLabel : $label;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $primaryRole = trim((string) ($profile['primary_role'] ?? ''));
        if ($primaryRole !== '') {
            return $primaryRole;
        }

        $rpFunction = trim((string) ($profile['rp_operational_function'] ?? ''));
        if ($rpFunction !== '') {
            return $rpFunction;
        }

        return 'Non renseignée';
    }

    /**
     * @param array<string, mixed>|null $profile
     */
    private function resolveAssignmentLabel(int $userId, int $tenantId, ?array $profile): string
    {
        if (is_array($profile) && !empty($profile['primary_unit_id']) && $tenantId > 0) {
            try {
                $unitRow = $this->units->findById((int) $profile['primary_unit_id'], $tenantId);
                if (is_array($unitRow)) {
                    $name = trim((string) ($unitRow['name'] ?? ''));
                    if ($name !== '') {
                        return $name;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($userId > 0) {
            try {
                $primary = $this->assignments->getPrimaryAssignment($userId);
                if (is_array($primary)) {
                    $name = trim((string) ($primary['unit_name'] ?? ''));
                    if ($name !== '') {
                        return $name;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return 'Non renseignée';
    }
}
