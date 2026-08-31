<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserProfileDisplaySettingsRepository;
use App\Support\DevDispatchCatalog;

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
    ) {}

    /**
     * @param array<string, mixed> $user
     * @return array{
     *   display_name: string,
     *   grade_label: string,
     *   unit_label: string,
     *   avatar_url: ?string,
     *   initials: string,
     *   changes: list<array{label: string, when: string}>
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
        $unitLabel = $this->resolveUnitLabel($userId, is_array($profile) ? $profile : null);

        return [
            'display_name' => $displayName,
            'grade_label' => $gradeLabel,
            'unit_label' => $unitLabel,
            'avatar_url' => $avatarUrl,
            'initials' => function_exists('user_display_initials')
                ? user_display_initials($displayName, 2)
                : mb_strtoupper(mb_substr($displayName, 0, 2)),
            'changes' => $this->recentChanges(),
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
     */
    private function resolveUnitLabel(int $userId, ?array $profile): string
    {
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
        if (is_array($profile)) {
            $role = trim((string) ($profile['primary_role'] ?? ''));
            if ($role !== '') {
                return $role;
            }
        }

        return '';
    }

    /**
     * @return list<array{label: string, when: string}>
     */
    private function recentChanges(): array
    {
        $out = [];
        try {
            $rows = array_slice(DevDispatchCatalog::all(), 0, 3);
        } catch (\Throwable) {
            return [];
        }
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? $row['activity'] ?? ''));
            if ($title === '') {
                continue;
            }
            $date = trim((string) ($row['date'] ?? ''));
            $when = $this->formatWhen($date, $today, $yesterday);
            $out[] = [
                'label' => $title,
                'when' => $when,
            ];
        }

        return $out;
    }

    private function formatWhen(string $date, string $today, string $yesterday): string
    {
        if ($date === $today) {
            return 'Aujourd’hui';
        }
        if ($date === $yesterday) {
            return 'Hier';
        }
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($date);

            return $dt->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
