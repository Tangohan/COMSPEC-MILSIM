<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Repositories\GradeRepository;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\PersonnelCommandChainService;

/**
 * Charge l’organisation d’une communauté (unités, chefs, adjoints, membres, grades, qualifications, emplois) et la
 * passe au moteur OrgAdequacyEngine. Résultat mémorisé pour la requête : la chaîne, la fiche et les alertes RH
 * lisent la même évaluation.
 */
final class OrgAdequacyService
{
    /** @var array<int, array<string, mixed>> */
    private static array $memo = [];

    public function __construct(
        private ?UnitRepository $units = null,
        private ?UserRepository $users = null,
        private ?PersonnelAssignmentRepository $assignments = null,
        private ?OrbatBilletRepository $billets = null,
    ) {
        $this->units ??= new UnitRepository();
        $this->users ??= new UserRepository();
        $this->assignments ??= new PersonnelAssignmentRepository();
        $this->billets ??= new OrbatBilletRepository();
    }

    public static function forget(): void
    {
        self::$memo = [];
    }

    /**
     * @return array<string, mixed> résultat du moteur + labels (people_labels) + réglages
     */
    public function evaluate(int $tenantId): array
    {
        if ($tenantId < 1) {
            return OrgAdequacyEngine::evaluate(['settings' => ['enabled' => false]]);
        }
        if (isset(self::$memo[$tenantId])) {
            return self::$memo[$tenantId];
        }
        $settings = OrgAdequacySettings::forTenant($tenantId);
        $profile = OrgAdequacySettings::profileForTenant($tenantId, $settings);
        if (empty($settings['enabled'])) {
            $result = OrgAdequacyEngine::evaluate(['settings' => $settings, 'profile' => $profile]);
            $result['settings'] = $settings;

            return self::$memo[$tenantId] = $result;
        }
        $input = $this->loadInput($tenantId, $settings, $profile);
        $result = OrgAdequacyEngine::evaluate($input);
        $result['settings'] = $settings;
        $result['standards'] = $input['standards'];

        return self::$memo[$tenantId] = $result;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function loadInput(int $tenantId, array $settings, string $profile): array
    {
        $chain = (new PersonnelCommandChainService($this->units, $this->users, $this->assignments))->workspace($tenantId);
        $units = [];
        $membersByUnit = [];
        foreach ($chain['units'] as $u) {
            $id = (int) $u['id'];
            $units[] = [
                'id' => $id,
                'parent_id' => (int) ($u['parent_id'] ?? 0),
                'name' => (string) ($u['name'] ?? ''),
                'commander_user_id' => (int) ($u['commander_user_id'] ?? 0),
                'echelon' => (string) ($u['echelon'] ?? ''),
            ];
            $membersByUnit[$id] = array_values(array_map('intval', is_array($u['member_ids'] ?? null) ? $u['member_ids'] : []));
        }
        $primary = [];
        $roleNames = [];
        $people = [];
        foreach ($chain['members'] as $m) {
            $uid = (int) $m['user_id'];
            if ((int) ($m['unit_id'] ?? 0) > 0) {
                $primary[$uid] = (int) $m['unit_id'];
            }
            $roleNames[$uid] = (string) ($m['role_name'] ?? '');
            $people[$uid] = ['label' => (string) ($m['label'] ?? 'Membre'), 'grade_id' => 0];
        }
        try {
            $rows = $this->users->listForTenant($tenantId, null, 'active', null, null, null, true);
        } catch (\Throwable) {
            $rows = [];
        }
        foreach ($rows as $r) {
            $uid = (int) ($r['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $people[$uid] ??= ['label' => PersonnelCommandChainService::labelFromUserRow($r)];
            $people[$uid]['grade_id'] = (int) ($r['grade_id'] ?? 0);
        }

        [$grades, $labelsByLevel] = $this->gradeCatalog($tenantId);
        foreach ($people as $uid => $p) {
            $gid = (int) ($p['grade_id'] ?? 0);
            $g = $gid > 0 ? ($grades[$gid] ?? null) : null;
            $people[$uid]['has_grade'] = $g !== null;
            $people[$uid]['grade_label'] = $g !== null ? (string) $g['label'] : '';
            $people[$uid]['grade_level'] = $g !== null ? $g['level'] : null;
        }

        $standards = OrgAdequacySettings::effectiveStandards($settings, $profile);
        $qualIds = [];
        $jobIds = [];
        foreach ($standards as $s) {
            foreach ($s['qualification_ids'] as $q) {
                $qualIds[(int) $q] = true;
            }
            foreach ($s['job_role_ids'] as $j) {
                $jobIds[(int) $j] = true;
            }
        }
        [$quals, $qualLabels] = $this->qualifications($tenantId, array_keys($qualIds));
        [$jobs, $jobLabels] = $this->jobRoles($tenantId, array_keys($jobIds));
        try {
            $deputies = $this->billets->deputyHoldersByUnitForTenant($tenantId);
        } catch (\Throwable) {
            $deputies = [];
        }

        return [
            'settings' => $settings,
            'profile' => $profile,
            'standards' => $standards,
            'units' => $units,
            'members_by_unit' => $membersByUnit,
            'primary_unit' => $primary,
            'deputies' => $deputies,
            'people' => $people,
            'role_names' => $roleNames,
            'qualifications' => $quals,
            'qualification_labels' => $qualLabels,
            'job_roles' => $jobs,
            'job_role_labels' => $jobLabels,
            'grade_labels_by_level' => $labelsByLevel,
        ];
    }

    /**
     * Grades connus (id => libellé + niveau OTAN) et libellés du système de la communauté par niveau.
     *
     * @return array{0: array<int, array{label: string, level: ?int}>, 1: array<int, list<string>>}
     */
    public function gradeCatalog(int $tenantId): array
    {
        $byId = [];
        $byLevel = [];
        try {
            $repo = new GradeRepository();
            foreach ($repo->listActive($tenantId) as $g) {
                $byId[(int) $g['id']] = self::gradeRow($g);
            }
            foreach ($repo->listForTenant($tenantId) as $g) {
                $row = self::gradeRow($g);
                $byId[(int) $g['id']] = $row;
                if ($row['level'] !== null) {
                    $byLevel[$row['level']][] = $row['label'];
                }
            }
        } catch (\Throwable) {
        }
        foreach ($byLevel as $l => $labels) {
            $byLevel[$l] = array_values(array_unique($labels));
        }
        ksort($byLevel);

        return [$byId, $byLevel];
    }

    /** @param array<string, mixed> $g */
    private static function gradeRow(array $g): array
    {
        $long = trim((string) ($g['label_long'] ?? ''));
        $short = trim((string) ($g['label_short'] ?? ''));

        return [
            'label' => $long !== '' ? $long : ($short !== '' ? $short : trim((string) ($g['code'] ?? ''))),
            'level' => GradeLevel::fromCode((string) ($g['label_otan'] ?? '')),
        ];
    }

    /**
     * @param list<int> $ids
     * @return array{0: array<int, list<int>>, 1: array<int, string>}
     */
    private function qualifications(int $tenantId, array $ids): array
    {
        $byUser = [];
        $labels = [];
        if ($ids === []) {
            return [$byUser, $labels];
        }
        try {
            foreach ((new QualificationDefinitionRepository())->listForTenant($tenantId, true) as $d) {
                $labels[(int) $d['id']] = trim((string) ($d['name'] ?? ''));
            }
            $awards = new QualificationAwardRepository();
            foreach ($ids as $defId) {
                foreach ($awards->listHolders($tenantId, (int) $defId) as $row) {
                    $status = strtolower(trim((string) (($row['admin_status'] ?? '') !== '' && $row['admin_status'] !== null ? $row['admin_status'] : ($row['status'] ?? ''))));
                    if (!in_array($status, ['obtained', 'valid', 'expiring'], true)) {
                        continue;
                    }
                    $uid = (int) ($row['user_id'] ?? 0);
                    if ($uid > 0) {
                        $byUser[$uid][] = (int) $defId;
                    }
                }
            }
        } catch (\Throwable) {
        }

        return [$byUser, $labels];
    }

    /**
     * @param list<int> $ids
     * @return array{0: array<int, list<int>>, 1: array<int, string>}
     */
    private function jobRoles(int $tenantId, array $ids): array
    {
        $byUser = [];
        $labels = [];
        if ($ids === []) {
            return [$byUser, $labels];
        }
        try {
            $repo = new PersonnelJobRoleRepository();
            foreach ($repo->listRolesWithCategory($tenantId) as $r) {
                $labels[(int) $r['id']] = trim((string) ($r['name'] ?? ''));
            }
            foreach ($repo->listHoldersByJobRoleIds($tenantId, $ids) as $roleId => $holders) {
                foreach ($holders as $h) {
                    $byUser[(int) $h['user_id']][] = (int) $roleId;
                }
            }
        } catch (\Throwable) {
        }

        return [$byUser, $labels];
    }

    /**
     * Lien d’une action d’écart (bureau effectifs, ORBAT, avancement).
     *
     * @param array<string, mixed> $action
     * @return array{label: string, href: string}|null
     */
    public static function actionLink(array $action): ?array
    {
        $unitId = (int) ($action['unit_id'] ?? 0);
        $userId = (int) ($action['user_id'] ?? 0);

        return match ((string) ($action['key'] ?? '')) {
            'designate' => ['label' => 'Désigner le chef', 'href' => effectifs_workspace_url('chaine') . '#eff-chain-setup'],
            'advancement' => ['label' => 'Proposer à l’avancement', 'href' => url('back-office/rh/avancement')],
            'reassign' => ['label' => 'Réaffecter', 'href' => effectifs_workspace_url('affectations') . ($unitId > 0 ? '?unite=' . $unitId : '')],
            'deputy' => $unitId > 0 ? ['label' => 'Nommer un adjoint', 'href' => url('back-office/organisation/unites/' . $unitId)] : null,
            'structure' => $unitId > 0 ? ['label' => 'Ouvrir l’unité', 'href' => url('back-office/organisation/unites/' . $unitId)] : null,
            'assign' => $userId > 0 ? ['label' => 'Affecter', 'href' => effectifs_workspace_url('membres/' . $userId)] : null,
            'member' => $userId > 0 ? ['label' => 'Renseigner le grade', 'href' => effectifs_workspace_url('membres/' . $userId)] : null,
            'qualification' => $userId > 0 ? ['label' => 'Attribuer la qualification', 'href' => url('back-office/referentiels/qualifications/attribuer') . '?user_id=' . $userId] : null,
            'job' => $userId > 0 ? ['label' => 'Attribuer l’emploi', 'href' => effectifs_workspace_url('membres/' . $userId)] : null,
            'grades' => ['label' => 'Ouvrir le référentiel des grades', 'href' => url('back-office/referentiels/grades')],
            default => null,
        };
    }

    /**
     * Écarts d’une personne et d’une unité, prêts à afficher.
     *
     * @param array<string, mixed> $result
     * @return list<array<string, mixed>>
     */
    public static function issuesFor(array $result, string $kind, int $id): array
    {
        $bucket = $kind === 'unit' ? ($result['units'][$id] ?? null) : ($result['people'][$id] ?? null);
        if (!is_array($bucket)) {
            return [];
        }
        $out = [];
        foreach ($bucket['issues'] ?? [] as $i) {
            if (isset($result['issues'][$i])) {
                $out[] = $result['issues'][$i];
            }
        }

        return $out;
    }
}
