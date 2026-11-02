<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Repositories\TenantAdminSettingsRepository;
use App\Repositories\TenantRepository;
use App\Services\Doctrine\DoctrineReferential;

/**
 * Réglages de l’adéquation de l’organisation, par communauté (admin_runtime.org_adequacy des paramètres).
 *
 * - enabled : contrôle actif ou non ;
 * - profile : référentiel des attentes (auto = doctrine de la communauté ou système de grades, sinon fr / us / gb) ;
 * - overqualification_margin : écart de niveaux de grade (OTAN) au-dessus de la plage attendue à partir duquel un
 *   grade est signalé « nettement au-dessus » ;
 * - rules : chaque famille de contrôle s’active ou se désactive ;
 * - echelons : surcharges par échelon (activé, effectif, plages de grades, adjoint, sous-unités, qualifications de
 *   commandement et emplois attendus). Une valeur absente reprend celle du référentiel (EchelonStandards).
 */
final class OrgAdequacySettings
{
    public const PROFILE_AUTO = 'auto';

    public const RULES = [
        'vacancy' => 'Poste de chef à pourvoir',
        'deputy' => 'Adjoint attendu',
        'grade_below' => 'Sous-capacité de commandement (grade)',
        'grade_above' => 'Surqualification (grade ou qualifications)',
        'qualifications' => 'Qualifications de commandement attendues',
        'functions' => 'Emploi attendu du chef',
        'strength' => 'Effectif hors plage',
        'subunits' => 'Sous-unités attendues',
        'placement' => 'Personnes sans place ou sans chef',
        'echelon_unknown' => 'Unités sans échelon renseigné',
    ];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'profile' => self::PROFILE_AUTO,
            'overqualification_margin' => 2,
            'rules' => array_fill_keys(array_keys(self::RULES), true),
            'echelons' => [],
        ];
    }

    /** @return array<string, mixed> */
    public static function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $defaults = self::defaults();
        $profile = strtolower(trim((string) ($input['profile'] ?? self::PROFILE_AUTO)));
        if ($profile !== self::PROFILE_AUTO && !in_array($profile, EchelonStandards::profiles(), true)) {
            $profile = self::PROFILE_AUTO;
        }
        $rulesIn = is_array($input['rules'] ?? null) ? $input['rules'] : [];
        $rules = [];
        foreach (array_keys(self::RULES) as $rule) {
            $rules[$rule] = array_key_exists($rule, $rulesIn) ? self::bool($rulesIn[$rule]) : true;
        }
        $echelons = [];
        $echelonsIn = is_array($input['echelons'] ?? null) ? $input['echelons'] : [];
        foreach (EchelonStandards::keys() as $key) {
            if (!is_array($echelonsIn[$key] ?? null)) {
                continue;
            }
            $clean = self::sanitizeEchelon($echelonsIn[$key]);
            if ($clean !== []) {
                $echelons[$key] = $clean;
            }
        }

        return [
            'enabled' => array_key_exists('enabled', $input) ? self::bool($input['enabled']) : $defaults['enabled'],
            'profile' => $profile,
            'overqualification_margin' => max(1, min(6, (int) ($input['overqualification_margin'] ?? $defaults['overqualification_margin']))),
            'rules' => $rules,
            'echelons' => $echelons,
        ];
    }

    /**
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    private static function sanitizeEchelon(array $in): array
    {
        $out = [];
        // Seule la désactivation est retenue : un échelon actif reprend le comportement par défaut.
        if (array_key_exists('enabled', $in) && !self::bool($in['enabled'])) {
            $out['enabled'] = false;
        }
        foreach (['strength_min', 'strength_max', 'subunits_min', 'subunits_max'] as $f) {
            $v = trim((string) ($in[$f] ?? ''));
            if ($v !== '' && preg_match('/^\d{1,5}$/', $v) === 1) {
                $out[$f] = min(20000, (int) $v);
            }
        }
        foreach (['leader_grade_min', 'leader_grade_max', 'deputy_grade_min', 'deputy_grade_max'] as $f) {
            $level = GradeLevel::fromCode((string) ($in[$f] ?? ''));
            if ($level !== null) {
                $out[$f] = GradeLevel::code($level);
            }
        }
        $dep = strtolower(trim((string) ($in['deputy_required'] ?? '')));
        if (in_array($dep, ['1', '0', 'yes', 'no', 'true', 'false'], true)) {
            $out['deputy_required'] = in_array($dep, ['1', 'yes', 'true'], true);
        } elseif (is_bool($in['deputy_required'] ?? null)) {
            $out['deputy_required'] = $in['deputy_required'];
        }
        foreach (['qualification_ids', 'job_role_ids'] as $f) {
            $ids = is_array($in[$f] ?? null) ? $in[$f] : [];
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
            if ($ids !== []) {
                $out[$f] = array_slice($ids, 0, 20);
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function forTenant(int $tenantId, ?TenantAdminSettingsRepository $repo = null): array
    {
        if ($tenantId < 1) {
            return self::defaults();
        }
        try {
            $all = ($repo ?? new TenantAdminSettingsRepository())->getForTenant($tenantId);

            return self::sanitize($all['org_adequacy'] ?? []);
        } catch (\Throwable) {
            return self::defaults();
        }
    }

    /**
     * Référentiel retenu : réglage explicite, sinon doctrine de la communauté (les deux : d’après le système de grades).
     */
    public static function resolveProfile(array $settings, string $doctrineReferential, string $gradeSystemCode = ''): string
    {
        $profile = (string) ($settings['profile'] ?? self::PROFILE_AUTO);
        if (in_array($profile, EchelonStandards::profiles(), true)) {
            return $profile;
        }
        // Grades britanniques : référentiel des échelons britannique, quelle que soit la doctrine.
        if (str_starts_with(strtoupper(trim($gradeSystemCode)), 'GB')) {
            return EchelonStandards::PROFILE_GB;
        }
        $ref = DoctrineReferential::sanitize($doctrineReferential);
        if ($ref === DoctrineReferential::US) {
            return EchelonStandards::PROFILE_US;
        }
        if ($ref === DoctrineReferential::BOTH && str_starts_with(strtoupper(trim($gradeSystemCode)), 'US')) {
            return EchelonStandards::PROFILE_US;
        }

        return EchelonStandards::PROFILE_FR;
    }

    public static function profileForTenant(int $tenantId, array $settings): string
    {
        $referential = DoctrineReferential::DEFAULT;
        $gradeSystem = '';
        try {
            $all = (new TenantAdminSettingsRepository())->getForTenant($tenantId);
            $referential = (string) ($all['doctrine']['referential'] ?? DoctrineReferential::DEFAULT);
            $tenantSettings = (new TenantRepository())->getSettings($tenantId);
            $gradeSystem = (string) ($tenantSettings['grade_system_code'] ?? '');
        } catch (\Throwable) {
        }

        return self::resolveProfile($settings, $referential, $gradeSystem);
    }

    /**
     * Référentiel effectif : valeurs par défaut du profil + surcharges de la communauté.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function effectiveStandards(array $settings, string $profile): array
    {
        $out = [];
        $overrides = is_array($settings['echelons'] ?? null) ? $settings['echelons'] : [];
        foreach (EchelonStandards::all($profile) as $key => $def) {
            $o = is_array($overrides[$key] ?? null) ? $overrides[$key] : [];
            $def['default'] = $def;
            $def['enabled'] = array_key_exists('enabled', $o) ? (bool) $o['enabled'] : true;
            $def['strength'] = self::range($def['strength'], $o['strength_min'] ?? null, $o['strength_max'] ?? null);
            $def['subunits'] = self::range($def['subunits'], $o['subunits_min'] ?? null, $o['subunits_max'] ?? null);
            $def['leader_grade'] = self::gradeRange($def['leader_grade'], $o['leader_grade_min'] ?? null, $o['leader_grade_max'] ?? null);
            if (array_key_exists('deputy_required', $o)) {
                $def['deputy_required'] = (bool) $o['deputy_required'];
            }
            $baseDeputy = is_array($def['deputy_grade']) ? $def['deputy_grade'] : null;
            if ($baseDeputy !== null || isset($o['deputy_grade_min']) || isset($o['deputy_grade_max'])) {
                $baseDeputy ??= $def['leader_grade'];
                $def['deputy_grade'] = self::gradeRange($baseDeputy, $o['deputy_grade_min'] ?? null, $o['deputy_grade_max'] ?? null);
            }
            $def['qualification_ids'] = array_values(array_map('intval', is_array($o['qualification_ids'] ?? null) ? $o['qualification_ids'] : []));
            $def['job_role_ids'] = array_values(array_map('intval', is_array($o['job_role_ids'] ?? null) ? $o['job_role_ids'] : []));
            $def['overridden'] = $o !== [];
            $out[$key] = $def;
        }

        return $out;
    }

    /**
     * @param array{0:int,1:int} $base
     * @return array{0:int,1:int}
     */
    private static function range(array $base, mixed $min, mixed $max): array
    {
        $lo = $min !== null ? (int) $min : (int) $base[0];
        $hi = $max !== null ? (int) $max : (int) $base[1];

        return $hi < $lo ? [$hi, $lo] : [$lo, $hi];
    }

    /**
     * @param array{0:int,1:int} $base
     * @return array{0:int,1:int}
     */
    private static function gradeRange(array $base, mixed $min, mixed $max): array
    {
        $lo = $min !== null ? (GradeLevel::fromCode((string) $min) ?? (int) $base[0]) : (int) $base[0];
        $hi = $max !== null ? (GradeLevel::fromCode((string) $max) ?? (int) $base[1]) : (int) $base[1];

        return $hi < $lo ? [$hi, $lo] : [$lo, $hi];
    }

    private static function bool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
    }
}
