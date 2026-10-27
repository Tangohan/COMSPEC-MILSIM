<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\JobRolePickerService;
use App\Services\Personnel\PersonnelCorrectionRequestService;
use App\Services\Personnel\PersonnelDutyPositionService;
use App\Services\Rbac\CommunityAccessProfiles;
use App\Support\CommunityLanguages;
use App\Support\GradeSelectOptions;
use Throwable;

/**
 * Listes et valeurs du parcours d’intégration (bureau recrutement), alignées sur la fiche Effectifs :
 * niveaux d’accès (Membre / RH / Gestionnaire + niveaux créés), grades de la communauté (y compris
 * les systèmes sur mesure), toutes les fonctions du référentiel et l’arborescence des unités.
 */
final class EnlistmentOnboardingFormService
{
    public const DUTY_TRAINING = 'training';
    public const DUTY_ACTIVE = 'active';

    /** Rôles techniques qui ne sont pas des niveaux d’accès (position de service). */
    private const NON_ACCESS_SLUGS = [
        PersonnelDutyPositionService::SLUG_TRAINING,
        PersonnelDutyPositionService::SLUG_ACTIVE,
    ];

    public function __construct(
        private RoleRepository $roles,
        private UnitRepository $units,
        private GradeRepository $grades,
        private PersonnelJobRoleRepository $jobRoles,
        private UserRepository $users,
        private PersonnelProfileRepository $profiles,
        private TenantRepository $tenants,
    ) {}

    /**
     * @param array<string, mixed> $enlistment
     * @param array<string, mixed>|null $linkedUser
     * @param list<int> $currentRoleIds
     * @return array{
     *   accessRoles: list<array<string, mixed>>,
     *   accessRoleId: int,
     *   gradeGroups: list<array{label: string, grades: list<array<string, mixed>>}>,
     *   gradeId: int,
     *   jobRoleGroups: list<array{label: string, hint: string, items: list<array{value: string, label: string, detail: string, checked: bool}>}>,
     *   units: list<array{id: int, label: string, depth: int}>,
     *   referents: list<array{id: int, label: string}>,
     *   civilOptions: array<string, list<string>>,
     *   profile: array<string, string>
     * }
     */
    public function build(int $tenantId, array $enlistment, ?array $linkedUser, array $currentRoleIds): array
    {
        $accessRoles = $this->accessRoles($tenantId);
        $accessRoleId = self::pickAccessRoleId($accessRoles, $currentRoleIds);

        $gradeRows = [];
        try {
            $gradeRows = $this->grades->listForTenant($tenantId);
        } catch (Throwable) {
            $gradeRows = [];
        }
        $settings = [];
        try {
            $settings = $this->tenants->getSettings($tenantId);
        } catch (Throwable) {
            $settings = [];
        }
        $gradeGroups = GradeSelectOptions::groups($gradeRows, CommunityLanguages::preferredGradeCountry($settings));

        $jobRoleGroups = [];
        try {
            $jobRoleGroups = (new JobRolePickerService($this->jobRoles))->groups($tenantId);
        } catch (Throwable $e) {
            error_log('Recruitment onboarding: job role picker failed: ' . $e->getMessage());
        }

        $userId = is_array($linkedUser) ? (int) ($linkedUser['id'] ?? 0) : 0;
        $pp = [];
        if ($userId > 0) {
            try {
                $pp = $this->profiles->getByUserId($userId) ?? [];
            } catch (Throwable) {
                $pp = [];
            }
        }
        $rp = is_array($enlistment['recruitment_rp_json'] ?? null) ? $enlistment['recruitment_rp_json'] : [];
        $pick = static function (string $key, array ...$sources): string {
            foreach ($sources as $src) {
                $v = trim((string) ($src[$key] ?? ''));
                if ($v !== '' && $v !== '—') {
                    return $v;
                }
            }

            return '';
        };
        $enlistDate = trim((string) ($pp['enlistment_date'] ?? ''));
        if ($enlistDate === '' || $enlistDate === '0000-00-00') {
            $enlistDate = self::ymd((string) ($enlistment['reviewed_at'] ?? '')) ?? date('Y-m-d');
        }

        return [
            'accessRoles' => $accessRoles,
            'accessRoleId' => $accessRoleId,
            'gradeGroups' => $gradeGroups,
            'gradeId' => is_array($linkedUser) ? (int) ($linkedUser['grade_id'] ?? 0) : 0,
            'jobRoleGroups' => $jobRoleGroups,
            'units' => $this->unitOptions($tenantId),
            'referents' => $this->referentOptions($tenantId),
            'civilOptions' => self::civilOptions(),
            'profile' => [
                'nickname_primary' => $pick('nickname_primary', $pp),
                'nationality' => $pick('nationality', $pp, $rp),
                'birth_place' => $pick('birth_place', $pp),
                'sex' => $pick('sex', $pp),
                'blood_type' => $pick('blood_type', $pp),
                'enlistment_date' => $enlistDate,
                'referent_user_id' => (string) ((int) ($pp['rp_tutor_user_id'] ?? 0) ?: (int) ($enlistment['reviewed_by'] ?? 0)),
                'duty' => self::DUTY_TRAINING,
            ],
        ];
    }

    /**
     * Niveaux d’accès proposés : les modèles et niveaux créés par la communauté (comme sur la fiche Effectifs).
     * Communauté pas encore convertie : rôles d’organisation, sans les positions de service.
     *
     * @return list<array<string, mixed>>
     */
    public function accessRoles(int $tenantId): array
    {
        $rows = [];
        try {
            $rows = $this->roles->forTenantAccessProfiles($tenantId);
        } catch (Throwable) {
            $rows = [];
        }
        if ($rows === []) {
            try {
                $rows = $this->roles->forTenantOrganization($tenantId);
            } catch (Throwable) {
                $rows = [];
            }
        }

        return self::filterAccessRoles($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function filterAccessRoles(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $slug = strtolower(trim((string) ($r['slug'] ?? '')));
            if ((int) ($r['id'] ?? 0) < 1 || in_array($slug, self::NON_ACCESS_SLUGS, true)) {
                continue;
            }
            $out[] = $r;
        }

        return $out;
    }

    /**
     * Niveau coché : celui que le compte a déjà, sinon « Membre », sinon le premier proposé.
     *
     * @param list<array<string, mixed>> $accessRoles
     * @param list<int> $currentRoleIds
     */
    public static function pickAccessRoleId(array $accessRoles, array $currentRoleIds): int
    {
        $current = array_map('intval', $currentRoleIds);
        $member = 0;
        foreach ($accessRoles as $r) {
            $rid = (int) ($r['id'] ?? 0);
            if (in_array($rid, $current, true)) {
                return $rid;
            }
            if ($member < 1 && (string) ($r['slug'] ?? '') === CommunityAccessProfiles::SLUG_MEMBER) {
                $member = $rid;
            }
        }
        if ($member > 0) {
            return $member;
        }

        return (int) ($accessRoles[0]['id'] ?? 0);
    }

    /**
     * Unités dans l’ordre de l’organigramme, avec leur profondeur (indentation) et le chemin complet.
     *
     * @return list<array{id: int, label: string, depth: int}>
     */
    public function unitOptions(int $tenantId): array
    {
        try {
            $rows = $this->units->allForTenant($tenantId);
        } catch (Throwable) {
            return [];
        }

        return self::orderUnits($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{id: int, label: string, depth: int}>
     */
    public static function orderUnits(array $rows): array
    {
        $byId = [];
        $children = [];
        foreach ($rows as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $byId[$id] = $u;
        }
        foreach ($byId as $id => $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            $children[isset($byId[$pid]) && $pid !== $id ? $pid : 0][] = $id;
        }
        $out = [];
        $seen = [];
        $walk = static function (int $parent, int $depth) use (&$walk, &$out, &$seen, $children, $byId): void {
            foreach ($children[$parent] ?? [] as $id) {
                if (isset($seen[$id]) || $depth > 12) {
                    continue;
                }
                $seen[$id] = true;
                $u = $byId[$id];
                $name = trim((string) ($u['name'] ?? '')) ?: ('Unité #' . $id);
                $code = trim((string) ($u['code'] ?? ''));
                $out[] = ['id' => $id, 'label' => $code !== '' ? $name . ' (' . $code . ')' : $name, 'depth' => $depth];
                $walk($id, $depth + 1);
            }
        };
        $walk(0, 0);
        // Boucle parent/enfant éventuelle : les unités non atteintes restent proposées.
        foreach ($byId as $id => $u) {
            if (!isset($seen[$id])) {
                $out[] = ['id' => $id, 'label' => trim((string) ($u['name'] ?? '')) ?: ('Unité #' . $id), 'depth' => 0];
            }
        }

        return $out;
    }

    /**
     * Membres actifs pouvant suivre le nouveau venu (référent d’intégration).
     *
     * @return list<array{id: int, label: string}>
     */
    public function referentOptions(int $tenantId): array
    {
        try {
            $rows = $this->users->allForTenant($tenantId);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1 || (string) ($u['status'] ?? 'active') !== 'active') {
                continue;
            }
            $name = trim((string) ($u['display_name'] ?? ''));
            $cs = trim((string) ($u['callsign'] ?? ''));
            $label = $name !== '' ? $name : ($cs !== '' ? $cs : 'Membre #' . $id);
            if ($cs !== '' && $name !== '' && mb_stripos($name, $cs) === false) {
                $label .= ' « ' . $cs . ' »';
            }
            $out[] = ['id' => $id, 'label' => $label];
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $out;
    }

    /** @return array<string, list<string>> */
    public static function civilOptions(): array
    {
        $out = [];
        foreach (PersonnelCorrectionRequestService::choiceCatalog() as $key => $pairs) {
            if (!in_array($key, ['sex', 'blood_type'], true)) {
                continue;
            }
            foreach ($pairs as $pair) {
                $v = trim((string) ($pair['value'] ?? ''));
                if ($v !== '') {
                    $out[$key][] = $v;
                }
            }
        }

        return $out;
    }

    /**
     * Valeurs postées du parcours → options de EnlistmentAcceptanceProvisioningService::completeAcceptanceOnboarding().
     * Les fonctions du référentiel (« cat:slug ») sont créées dans la communauté à ce moment-là.
     *
     * @param array<string, mixed> $input
     * @return array{options: array<string, mixed>, error: ?string}
     */
    public function normalizeSubmission(int $tenantId, array $input): array
    {
        $text = static function (mixed $raw, int $max): string {
            return mb_substr(trim((string) $raw), 0, $max, 'UTF-8');
        };

        $roleIds = [];
        $accessRoleId = (int) ($input['access_role_id'] ?? 0);
        if ($accessRoleId > 0) {
            $allowed = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $this->accessRoles($tenantId));
            if (!in_array($accessRoleId, $allowed, true)) {
                return ['options' => [], 'error' => 'Ce niveau d’accès n’est pas disponible pour la communauté.'];
            }
            $roleIds = [$accessRoleId];
        } elseif (is_array($input['role_ids'] ?? null)) {
            $roleIds = array_values(array_filter(array_map('intval', $input['role_ids']), static fn (int $v): bool => $v > 0));
        }

        $jobRaw = trim((string) ($input['personnel_job_role_id'] ?? ''));
        $jobRoleId = 0;
        if ($jobRaw !== '' && $jobRaw !== '0') {
            try {
                $ids = (new JobRolePickerService($this->jobRoles))->resolveValues($tenantId, [$jobRaw]);
                $jobRoleId = (int) ($ids[0] ?? 0);
            } catch (Throwable) {
                $jobRoleId = 0;
            }
            if ($jobRoleId < 1) {
                return ['options' => [], 'error' => 'L’emploi choisi est introuvable. Choisissez-en un autre ou nommez-le.'];
            }
        }

        $gradeId = (int) ($input['grade_id'] ?? 0);
        if ($gradeId > 0) {
            $known = false;
            try {
                foreach ($this->grades->listForTenant($tenantId) as $g) {
                    if ((int) ($g['id'] ?? 0) === $gradeId) {
                        $known = true;
                        break;
                    }
                }
            } catch (Throwable) {
                $known = false;
            }
            if (!$known) {
                return ['options' => [], 'error' => 'Ce grade n’appartient pas à la communauté.'];
            }
        }

        $civil = self::civilOptions();
        $choice = static function (string $key, mixed $raw) use ($civil): ?string {
            $v = trim((string) $raw);

            return $v !== '' && in_array($v, $civil[$key] ?? [], true) ? $v : null;
        };
        $enlistDate = self::ymd((string) ($input['enlistment_date'] ?? ''));

        $referentId = (int) ($input['referent_user_id'] ?? 0);
        if ($referentId > 0 && !$this->users->findById($referentId, $tenantId)) {
            $referentId = 0;
        }
        $duty = (string) ($input['duty'] ?? '') === self::DUTY_ACTIVE ? self::DUTY_ACTIVE : self::DUTY_TRAINING;

        return [
            'options' => [
                'first_name' => $text($input['first_name'] ?? '', 100),
                'last_name' => $text($input['last_name'] ?? '', 100),
                'callsign' => $text($input['callsign'] ?? '', 64),
                'steam_profile' => $text($input['steam_profile'] ?? '', 255),
                'unit_id' => (int) ($input['unit_id'] ?? 0),
                'personnel_job_role_id' => $jobRoleId,
                'role_ids' => $roleIds,
                'assignment_label' => $text($input['assignment_label'] ?? '', 120),
                'grade_id' => $gradeId,
                'duty' => $duty,
                'referent_user_id' => $referentId,
                'profile' => [
                    'nickname_primary' => $text($input['nickname_primary'] ?? '', 100),
                    'nationality' => $text($input['nationality'] ?? '', 100),
                    'birth_place' => $text($input['birth_place'] ?? '', 150),
                    'sex' => $choice('sex', $input['sex'] ?? ''),
                    'blood_type' => $choice('blood_type', $input['blood_type'] ?? ''),
                    'enlistment_date' => $enlistDate,
                ],
            ],
            'error' => null,
        ];
    }

    public static function ymd(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || str_starts_with($raw, '0000')) {
            return null;
        }
        try {
            $d = new \DateTimeImmutable($raw);
        } catch (Throwable) {
            return null;
        }
        $y = (int) $d->format('Y');

        return $y >= 1900 && $y <= 2200 ? $d->format('Y-m-d') : null;
    }
}
