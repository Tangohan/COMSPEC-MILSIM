<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\TenantRepository;
use App\Support\RoleplayBilanPolicy;

/**
 * Cadences et listes du suivi d’immersion, par communauté.
 * Clé absente = comportement historique. Les clés d’autres modules sont conservées.
 */
final class RoleplayFollowupSettings
{
    public const DEFAULT_STAGES = ['Pré-qualification', 'Tutorat', 'Validation', 'Intégration active'];

    public const DEFAULT_TRACKS = ['Infanterie', 'Support', 'Commandement'];

    public const DEFAULT_STAGE_BILAN_TYPES = [
        ['label' => 'Suivi périodique', 'active' => true, 'position' => 1],
        ['label' => 'Fin de période d’essai', 'active' => true, 'position' => 2],
        ['label' => 'Bilan annuel', 'active' => true, 'position' => 3],
        ['label' => 'Autre', 'active' => true, 'position' => 4],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'optional' => false,
            'cadence_reviewed' => false,
            'stages' => self::DEFAULT_STAGES,
            'recruitment_tracks' => [],
            'eligibility' => [
                'min_completeness' => 50,
                'min_readiness' => 30,
                'require_unit' => false,
                'require_callsign' => false,
                'require_tutor' => false,
            ],
            'bilans' => [
                'enabled' => true,
                'first_year_days' => RoleplayBilanPolicy::FIRST_YEAR_INTERVAL_DAYS,
                'second_year_days' => RoleplayBilanPolicy::SECOND_YEAR_INTERVAL_DAYS,
                'ongoing_days' => RoleplayBilanPolicy::ONGOING_INTERVAL_DAYS,
                'grace_days' => RoleplayBilanPolicy::OVERDUE_GRACE_DAYS,
            ],
            'interview' => ['visible' => true, 'next_after_days' => 0],
            'medical' => ['visible' => true, 'next_after_days' => 0],
            'rotation' => ['visible' => true, 'next_after_days' => 0, 'require_interview' => true],
            'probation' => [
                'duration_days' => 60,
                'alert_enabled' => true,
                'alert_after_days' => 60,
                'bilan_label' => 'Fin de période d’essai',
            ],
            'notifications' => [
                'email_reminders' => true,
                'calendar_horizon_days' => 14,
                'remind_before_days' => 0,
            ],
            'stage_bilan_types' => self::DEFAULT_STAGE_BILAN_TYPES,
        ];
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public static function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $d = self::defaults();

        $eligIn = is_array($input['eligibility'] ?? null) ? $input['eligibility'] : [];
        $bilansIn = is_array($input['bilans'] ?? null) ? $input['bilans'] : [];
        $interviewIn = is_array($input['interview'] ?? null) ? $input['interview'] : [];
        $medicalIn = is_array($input['medical'] ?? null) ? $input['medical'] : [];
        $rotationIn = is_array($input['rotation'] ?? null) ? $input['rotation'] : [];
        $probationIn = is_array($input['probation'] ?? null) ? $input['probation'] : [];
        $notifIn = is_array($input['notifications'] ?? null) ? $input['notifications'] : [];

        return [
            'enabled' => self::bool($input['enabled'] ?? $d['enabled']),
            'optional' => self::bool($input['optional'] ?? $d['optional']),
            'cadence_reviewed' => self::bool($input['cadence_reviewed'] ?? false),
            'stages' => self::stringList($input['stages'] ?? null, $d['stages']),
            'recruitment_tracks' => self::stringList($input['recruitment_tracks'] ?? null, []),
            'eligibility' => [
                'min_completeness' => self::intRange($eligIn['min_completeness'] ?? $d['eligibility']['min_completeness'], 0, 100),
                'min_readiness' => self::intRange($eligIn['min_readiness'] ?? $d['eligibility']['min_readiness'], 0, 100),
                'require_unit' => self::bool($eligIn['require_unit'] ?? false),
                'require_callsign' => self::bool($eligIn['require_callsign'] ?? false),
                'require_tutor' => self::bool($eligIn['require_tutor'] ?? false),
            ],
            'bilans' => [
                'enabled' => self::bool($bilansIn['enabled'] ?? true),
                'first_year_days' => self::intRange($bilansIn['first_year_days'] ?? $d['bilans']['first_year_days'], 30, 730),
                'second_year_days' => self::intRange($bilansIn['second_year_days'] ?? $d['bilans']['second_year_days'], 30, 730),
                'ongoing_days' => self::intRange($bilansIn['ongoing_days'] ?? $d['bilans']['ongoing_days'], 30, 730),
                'grace_days' => self::intRange($bilansIn['grace_days'] ?? $d['bilans']['grace_days'], 0, 60),
            ],
            'interview' => [
                'visible' => self::bool($interviewIn['visible'] ?? true),
                'next_after_days' => self::intRange($interviewIn['next_after_days'] ?? 0, 0, 730),
            ],
            'medical' => [
                'visible' => self::bool($medicalIn['visible'] ?? true),
                'next_after_days' => self::intRange($medicalIn['next_after_days'] ?? 0, 0, 730),
            ],
            'rotation' => [
                'visible' => self::bool($rotationIn['visible'] ?? true),
                'next_after_days' => self::intRange($rotationIn['next_after_days'] ?? 0, 0, 730),
                'require_interview' => self::bool($rotationIn['require_interview'] ?? true),
            ],
            'probation' => [
                'duration_days' => self::intRange($probationIn['duration_days'] ?? 60, 14, 365),
                'alert_enabled' => self::bool($probationIn['alert_enabled'] ?? true),
                'alert_after_days' => self::intRange($probationIn['alert_after_days'] ?? 60, 14, 365),
                'bilan_label' => self::clip((string) ($probationIn['bilan_label'] ?? $d['probation']['bilan_label']), 80)
                    ?: (string) $d['probation']['bilan_label'],
            ],
            'notifications' => [
                'email_reminders' => self::bool($notifIn['email_reminders'] ?? true),
                'calendar_horizon_days' => self::intRange($notifIn['calendar_horizon_days'] ?? 14, 1, 90),
                'remind_before_days' => self::intRange($notifIn['remind_before_days'] ?? 0, 0, 30),
            ],
            'stage_bilan_types' => self::sanitizeStageBilanTypes($input['stage_bilan_types'] ?? null),
        ];
    }

    /**
     * Fusionne le JSON stocké avec un correctif (POST) sans perdre les clés inconnues.
     *
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    public static function merge(array $stored, array $patch): array
    {
        $known = array_keys(self::defaults());
        $unknown = [];
        foreach ($stored as $key => $value) {
            if (!in_array((string) $key, $known, true)) {
                $unknown[(string) $key] = $value;
            }
        }
        $combined = array_replace_recursive($stored, $patch);
        $sanitized = self::sanitize($combined);

        return $unknown + $sanitized;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTenant(int $tenantId, ?TenantRepository $tenants = null): array
    {
        if ($tenantId < 1) {
            return self::defaults();
        }
        $tenants ??= new TenantRepository();
        try {
            $settings = $tenants->getSettings($tenantId);
        } catch (\Throwable) {
            return self::defaults();
        }
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $stored = is_array($community['roleplay_followup'] ?? null) ? $community['roleplay_followup'] : [];

        return self::merge($stored, []);
    }

    public static function isCadenceReviewed(int $tenantId): bool
    {
        return !empty(self::forTenant($tenantId)['cadence_reviewed']);
    }

    /**
     * @param array<string, mixed> $patch
     */
    public static function saveForTenant(int $tenantId, array $patch, ?TenantRepository $tenants = null): array
    {
        $tenants ??= new TenantRepository();
        $all = $tenants->getSettings($tenantId);
        $community = is_array($all['community'] ?? null) ? $all['community'] : [];
        $stored = is_array($community['roleplay_followup'] ?? null) ? $community['roleplay_followup'] : [];
        $merged = self::merge($stored, $patch);
        $community['roleplay_followup'] = $merged;
        $all['community'] = $community;
        $tenants->replaceSettings($tenantId, $all);

        return $merged;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function patchFromImmersionPost(array $post): array
    {
        $parseLines = static function (string $raw): array {
            $out = [];
            foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
                $v = trim((string) $line);
                if ($v !== '') {
                    $out[] = $v;
                }
            }

            return array_values(array_unique($out));
        };

        $bilanTypes = [];
        $rawTypes = $post['rp_stage_bilan_types'] ?? [];
        if (is_string($rawTypes)) {
            foreach ($parseLines($rawTypes) as $i => $label) {
                $bilanTypes[] = ['label' => $label, 'active' => true, 'position' => $i + 1];
            }
        } elseif (is_array($rawTypes)) {
            $pos = 1;
            foreach ($rawTypes as $row) {
                if (is_string($row)) {
                    $bilanTypes[] = ['label' => $row, 'active' => true, 'position' => $pos++];
                    continue;
                }
                if (!is_array($row)) {
                    continue;
                }
                $bilanTypes[] = [
                    'label' => (string) ($row['label'] ?? ''),
                    'active' => !empty($row['active']) || (string) ($row['active'] ?? '') === '1' || !array_key_exists('active', $row),
                    'position' => $pos++,
                ];
            }
        }

        return [
            'enabled' => !empty($post['rp_followup_enabled']),
            'optional' => !empty($post['rp_followup_optional']),
            'cadence_reviewed' => true,
            'stages' => $parseLines((string) ($post['rp_followup_stages'] ?? '')),
            'recruitment_tracks' => $parseLines((string) ($post['rp_followup_tracks'] ?? '')),
            'eligibility' => [
                'min_completeness' => (int) ($post['rp_eligibility_min_completeness'] ?? 50),
                'min_readiness' => (int) ($post['rp_eligibility_min_readiness'] ?? 30),
                'require_unit' => !empty($post['rp_eligibility_require_unit']),
                'require_callsign' => !empty($post['rp_eligibility_require_callsign']),
                'require_tutor' => !empty($post['rp_eligibility_require_tutor']),
            ],
            'bilans' => [
                'enabled' => !empty($post['rp_bilans_enabled']),
                'first_year_days' => (int) ($post['rp_bilans_first_year_days'] ?? RoleplayBilanPolicy::FIRST_YEAR_INTERVAL_DAYS),
                'second_year_days' => (int) ($post['rp_bilans_second_year_days'] ?? RoleplayBilanPolicy::SECOND_YEAR_INTERVAL_DAYS),
                'ongoing_days' => (int) ($post['rp_bilans_ongoing_days'] ?? RoleplayBilanPolicy::ONGOING_INTERVAL_DAYS),
                'grace_days' => (int) ($post['rp_bilans_grace_days'] ?? RoleplayBilanPolicy::OVERDUE_GRACE_DAYS),
            ],
            'interview' => [
                'visible' => !empty($post['rp_interview_visible']),
                'next_after_days' => (int) ($post['rp_interview_next_after_days'] ?? 0),
            ],
            'medical' => [
                'visible' => !empty($post['rp_medical_visible']),
                'next_after_days' => (int) ($post['rp_medical_next_after_days'] ?? 0),
            ],
            'rotation' => [
                'visible' => !empty($post['rp_rotation_visible']),
                'next_after_days' => (int) ($post['rp_rotation_next_after_days'] ?? 0),
                'require_interview' => !empty($post['rp_rotation_require_interview']),
            ],
            'probation' => [
                'duration_days' => (int) ($post['rp_probation_duration_days'] ?? 60),
                'alert_enabled' => !empty($post['rp_probation_alert_enabled']),
                'alert_after_days' => (int) ($post['rp_probation_alert_after_days'] ?? 60),
                'bilan_label' => (string) ($post['rp_probation_bilan_label'] ?? 'Fin de période d’essai'),
            ],
            'notifications' => [
                'email_reminders' => !empty($post['rp_notif_email_reminders']),
                'calendar_horizon_days' => (int) ($post['rp_notif_calendar_horizon_days'] ?? 14),
                'remind_before_days' => (int) ($post['rp_notif_remind_before_days'] ?? 0),
            ],
            'stage_bilan_types' => $bilanTypes,
        ];
    }

    /**
     * @return array{first_year_days: int, second_year_days: int, ongoing_days: int, grace_days: int, enabled: bool}
     */
    public static function bilanCadence(array $cfg): array
    {
        $bilans = is_array($cfg['bilans'] ?? null) ? $cfg['bilans'] : [];
        $d = self::defaults()['bilans'];

        return [
            'enabled' => self::bool($bilans['enabled'] ?? true),
            'first_year_days' => self::intRange($bilans['first_year_days'] ?? $d['first_year_days'], 30, 730),
            'second_year_days' => self::intRange($bilans['second_year_days'] ?? $d['second_year_days'], 30, 730),
            'ongoing_days' => self::intRange($bilans['ongoing_days'] ?? $d['ongoing_days'], 30, 730),
            'grace_days' => self::intRange($bilans['grace_days'] ?? $d['grace_days'], 0, 60),
        ];
    }

    /**
     * @return list<string>
     */
    public static function activeStageBilanLabels(array $cfg): array
    {
        $out = [];
        $types = is_array($cfg['stage_bilan_types'] ?? null) ? $cfg['stage_bilan_types'] : self::DEFAULT_STAGE_BILAN_TYPES;
        usort($types, static fn (array $a, array $b): int => ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0)));
        foreach ($types as $row) {
            if (!is_array($row) || empty($row['active'])) {
                continue;
            }
            $label = self::clip((string) ($row['label'] ?? ''), 80);
            if ($label !== '') {
                $out[] = $label;
            }
        }

        return $out !== [] ? $out : array_map(
            static fn (array $r): string => (string) $r['label'],
            self::DEFAULT_STAGE_BILAN_TYPES
        );
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array{cadence: array<string, mixed>, show_interview: bool, show_medical: bool, show_rotation: bool, show_bilan: bool, horizon: int}
     */
    public static function dueListOptions(array $cfg): array
    {
        $bilans = self::bilanCadence($cfg);
        $interview = is_array($cfg['interview'] ?? null) ? $cfg['interview'] : [];
        $medical = is_array($cfg['medical'] ?? null) ? $cfg['medical'] : [];
        $rotation = is_array($cfg['rotation'] ?? null) ? $cfg['rotation'] : [];
        $notif = is_array($cfg['notifications'] ?? null) ? $cfg['notifications'] : [];

        return [
            'cadence' => $bilans,
            'show_interview' => array_key_exists('visible', $interview) ? self::bool($interview['visible']) : true,
            'show_medical' => array_key_exists('visible', $medical) ? self::bool($medical['visible']) : true,
            'show_rotation' => array_key_exists('visible', $rotation) ? self::bool($rotation['visible']) : true,
            'show_bilan' => !empty($bilans['enabled']),
            'horizon' => self::intRange($notif['calendar_horizon_days'] ?? 14, 1, 90),
        ];
    }

    /**
     * @return array{cadence: array<string, mixed>, show_interview: bool, show_medical: bool, show_rotation: bool, show_bilan: bool, horizon: int}
     */
    public static function dueListOptionsForTenant(int $tenantId, ?TenantRepository $tenants = null): array
    {
        return self::dueListOptions(self::forTenant($tenantId, $tenants));
    }

    /**
     * @param mixed $raw
     * @return list<array{label: string, active: bool, position: int}>
     */
    private static function sanitizeStageBilanTypes(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return self::DEFAULT_STAGE_BILAN_TYPES;
        }
        $out = [];
        $pos = 1;
        foreach ($raw as $row) {
            if (is_string($row)) {
                $label = self::clip($row, 80);
                if ($label === '') {
                    continue;
                }
                $out[] = ['label' => $label, 'active' => true, 'position' => $pos++];
                continue;
            }
            if (!is_array($row)) {
                continue;
            }
            $label = self::clip((string) ($row['label'] ?? ''), 80);
            if ($label === '') {
                continue;
            }
            $out[] = [
                'label' => $label,
                'active' => array_key_exists('active', $row) ? self::bool($row['active']) : true,
                'position' => self::intRange($row['position'] ?? $pos, 1, 50),
            ];
            $pos++;
        }

        return $out !== [] ? $out : self::DEFAULT_STAGE_BILAN_TYPES;
    }

    /**
     * @param mixed $raw
     * @param list<string> $fallback
     * @return list<string>
     */
    private static function stringList(mixed $raw, array $fallback): array
    {
        if (!is_array($raw)) {
            return $fallback;
        }
        $out = [];
        foreach ($raw as $item) {
            $v = self::clip((string) $item, 80);
            if ($v !== '' && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }

        return $out !== [] ? $out : $fallback;
    }

    private static function intRange(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function clip(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }
}
