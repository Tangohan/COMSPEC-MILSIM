<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Organization\OrgVisibilityService;
use App\Support\ConfidentialDisplay;
use App\Support\OrgVisibilityCapabilities;
use App\Support\VisibilityLevel;
use PDO;

/**
 * Charge les emplois et les membres du tableau « Qui tient quel emploi » et y applique la confidentialité
 * de l’ORBAT (OrgVisibilityService) avant tout calcul : un membre masqué n’apparaît pas, même dans les
 * compteurs ; un membre anonymisé garde sa place sans identité ni lien ; une affectation restreinte est
 * remplacée par sa mention commune et ne peut pas servir de filtre.
 */
final class JobAssignmentBoardLoader
{
    /** Garde-fou : au-delà, la page resterait lisible mais trop lourde. */
    public const MAX_MEMBERS = 2000;

    private PDO $pdo;

    public function __construct(
        private PersonnelJobRoleRepository $jobRoles,
        private UserRepository $users,
        private UnitRepository $units,
        private ?OrbatBilletRepository $billets = null,
        ?PDO $pdo = null,
    ) {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public static function accountStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Actif',
            'inactive' => 'Inactif',
            'pending_verification' => 'En attente',
            'suspended' => 'Suspendu',
            default => $status !== '' ? $status : '—',
        };
    }

    /**
     * @return array{ready: bool, jobs: list<array<string,mixed>>, members: list<array<string,mixed>>, categories: list<array<string,mixed>>,
     *   tags: list<array<string,mixed>>, units: list<array<string,mixed>>, truncated: bool}
     */
    public function load(int $tenantId, OrgVisibilityCapabilities $caps): array
    {
        $empty = ['ready' => false, 'jobs' => [], 'members' => [], 'categories' => [], 'tags' => [], 'units' => [], 'truncated' => false];
        if ($tenantId < 1 || !$this->jobRoles->tablesExist() || !$this->jobRoles->personnelProfilesHaveJobRoleColumns()) {
            return $empty;
        }
        $staff = $caps->bypassAll || $caps->viewHiddenUnits || $caps->viewRestrictedUnits;

        // Unités : niveau effectif (propagation parent → enfants), chaîne d’ancêtres, libellé affichable.
        $unitRows = $this->units->listFlatForStructure($tenantId, true);
        $levels = OrgVisibilityService::effectiveUnitLevels($unitRows);
        $unitById = [];
        foreach ($unitRows as $u) {
            $unitById[(int) $u['id']] = $u;
        }
        $chainOf = static function (int $id) use ($unitById): array {
            $chain = [];
            $guard = 0;
            while ($id > 0 && isset($unitById[$id]) && !in_array($id, $chain, true) && $guard++ < 40) {
                array_unshift($chain, $id);
                $id = (int) ($unitById[$id]['parent_id'] ?? 0);
            }

            return $chain;
        };
        $unitView = function (int $id) use ($unitById, $levels, $caps, $staff, $chainOf): ?array {
            if ($id < 1 || !isset($unitById[$id])) {
                return null;
            }
            $level = $levels[$id] ?? VisibilityLevel::NORMAL;
            if ($level === VisibilityLevel::HIDDEN && !$caps->canSeeUnitLevel(VisibilityLevel::HIDDEN)) {
                return ['id' => 0, 'label' => ConfidentialDisplay::label(ConfidentialDisplay::UNIT_HIDDEN), 'path' => '', 'chain' => [],
                    'badge' => ConfidentialDisplay::badge(ConfidentialDisplay::UNIT_HIDDEN, null, true), 'linkable' => false];
            }
            if ($level === VisibilityLevel::ANONYMIZED && !$staff) {
                return ['id' => 0, 'label' => ConfidentialDisplay::label(ConfidentialDisplay::UNIT_ANONYMIZED), 'path' => '', 'chain' => [],
                    'badge' => ConfidentialDisplay::badge(ConfidentialDisplay::UNIT_ANONYMIZED, null, true), 'linkable' => false];
            }
            $chain = $chainOf($id);
            $names = [];
            foreach ($chain as $cid) {
                $cl = $levels[$cid] ?? VisibilityLevel::NORMAL;
                if (($cl === VisibilityLevel::HIDDEN && !$caps->canSeeUnitLevel(VisibilityLevel::HIDDEN)) || ($cl === VisibilityLevel::ANONYMIZED && !$staff)) {
                    continue;
                }
                $names[] = (string) $unitById[$cid]['name'];
            }

            return ['id' => $id, 'label' => (string) $unitById[$id]['name'], 'path' => implode(' / ', $names), 'chain' => $chain,
                'badge' => $staff ? ConfidentialDisplay::levelBadge($level, 'unit', true) : '', 'linkable' => true];
        };
        $unitOptions = [];
        foreach ($unitRows as $u) {
            $view = $unitView((int) $u['id']);
            if ($view !== null && $view['id'] > 0) {
                $unitOptions[] = ['id' => $view['id'], 'label' => $view['label'], 'path' => $view['path'], 'depth' => count($view['chain']) - 1];
            }
        }
        usort($unitOptions, static fn (array $a, array $b): int => strnatcasecmp($a['path'], $b['path']));

        // Membres de la communauté (comptes de service exclus par la requête existante), par lots.
        $total = $this->jobRoles->countUsersForJobRoleAssignments($tenantId, null, null, false);
        $base = [];
        for ($offset = 0; $offset < min($total, self::MAX_MEMBERS); $offset += 200) {
            foreach ($this->jobRoles->listUsersForJobRoleAssignments($tenantId, null, null, false, 200, $offset) as $row) {
                $base[(int) $row['id']] = $row;
            }
        }
        $ids = array_keys($base);
        $rich = [];
        foreach ($ids === [] ? [] : $this->users->listEffectifsRosterByIds($tenantId, $ids) as $r) {
            $rich[(int) ($r['id'] ?? 0)] = $r;
        }
        $visibility = $ids === [] ? [] : $this->units->personnelVisibilityByUserIdsForTenant($tenantId, $ids);
        $situations = $this->situations($tenantId, $ids);
        $pivot = $this->jobRoles->pivotTableExists() ? $this->jobRoles->listPivotAssignmentsForUsers($tenantId, $ids) : [];

        $members = [];
        $holdersByJob = [];
        $anon = 0;
        foreach ($base as $uid => $row) {
            $r = $rich[$uid] ?? [];
            $unitId = (int) ($r['unit_id'] ?? 0);
            $vis = $visibility[$uid] ?? ['visibility_level' => VisibilityLevel::NORMAL, 'assignment_visibility' => VisibilityLevel::NORMAL, 'anonymized_label' => ''];
            $filtered = OrgVisibilityService::filterPersonnelRow([
                'user_id' => $uid,
                'visibility_level' => $vis['visibility_level'],
                'assignment_visibility' => $vis['assignment_visibility'],
                'anonymized_label' => $vis['anonymized_label'],
                'display_name' => (string) ($row['display_name'] ?? ''),
                'callsign' => $row['callsign'] ?? null,
                'unit_name' => $r['unit_name'] ?? null,
            ], $caps, $unitId > 0 ? ($levels[$unitId] ?? VisibilityLevel::NORMAL) : VisibilityLevel::NORMAL);
            if ($filtered === null) {
                continue;
            }
            $anonymized = !empty($filtered['anonymized']);
            $key = $anonymized ? 'a' . (++$anon) : 'u' . $uid;
            if (!empty($filtered['assignment_redacted'])) {
                $unit = ['id' => 0, 'label' => ConfidentialDisplay::label(ConfidentialDisplay::ASSIGNMENT_RESTRICTED), 'path' => '', 'chain' => [],
                    'badge' => ConfidentialDisplay::badge(ConfidentialDisplay::ASSIGNMENT_RESTRICTED, null, true), 'linkable' => false];
            } else {
                $unit = $unitView($unitId) ?? ['id' => 0, 'label' => '', 'path' => '', 'chain' => [], 'badge' => '', 'linkable' => false];
            }
            $jobs = [];
            $primary = 0;
            foreach ($pivot[$uid] ?? [] as $p) {
                $rid = (int) ($p['personnel_job_role_id'] ?? 0);
                if ($rid < 1 || in_array($rid, $jobs, true)) {
                    continue;
                }
                $jobs[] = $rid;
                if (!empty($p['is_primary']) && $primary === 0) {
                    $primary = $rid;
                }
                $holdersByJob[$rid][] = $key;
            }
            $name = $anonymized ? (string) $filtered['display_name'] : trim((string) ($row['display_name'] ?? ''));
            $avatar = '';
            if (!$anonymized) {
                $avatar = function_exists('personnel_operator_portrait_url') ? (string) (personnel_operator_portrait_url($r) ?? '') : '';
                if ($avatar === '' && trim((string) ($r['avatar_url'] ?? '')) !== '' && function_exists('user_media_public_url')) {
                    $avatar = (string) (user_media_public_url((string) $r['avatar_url']) ?? '');
                }
                if (str_contains($avatar, 'inconnu.svg')) {
                    $avatar = '';
                }
            }
            $status = (string) ($row['status'] ?? '');
            $grade = $anonymized ? '' : trim((string) ($r['grade_short'] ?? ''));
            $members[] = [
                'key' => $key,
                'id' => $anonymized ? 0 : $uid,
                'name' => $name !== '' ? $name : '—',
                'callsign' => $anonymized ? '' : trim((string) ($row['callsign'] ?? '')),
                'avatar' => $avatar,
                'grade' => $grade !== '' ? $grade : ($anonymized ? '' : trim((string) ($r['grade_long'] ?? ''))),
                'grade_long' => $anonymized ? '' : trim((string) ($r['grade_long'] ?? '')),
                'grade_order' => $anonymized ? 999 : (int) ($r['grade_sort_order'] ?? 999),
                'unit_id' => (int) $unit['id'],
                'unit_label' => (string) $unit['label'],
                'unit_path' => (string) $unit['path'],
                'unit_chain' => $unit['chain'],
                'unit_badge' => (string) $unit['badge'],
                'unit_linkable' => (bool) $unit['linkable'],
                'account_status' => $status,
                'status_label' => self::accountStatusLabel($status),
                'situation' => $anonymized ? '' : (string) ($situations[$uid] ?? ''),
                'jobs' => $jobs,
                'primary_job' => $primary,
                'anonymized' => $anonymized,
                'selectable' => !$anonymized,
                'person_badge' => $anonymized
                    ? ConfidentialDisplay::renderPayload((array) $filtered['confidential'], true)
                    : ConfidentialDisplay::levelBadge((string) $vis['visibility_level'], 'personnel', true),
            ];
        }

        // Emplois : habillage, structure (unité de rattachement), postes ORBAT liés et leurs places.
        $categories = $this->jobRoles->listCategories($tenantId);
        $categoryById = [];
        foreach ($categories as $c) {
            $categoryById[(int) $c['id']] = $c;
        }
        $categoryPath = static function (int $id) use ($categoryById): string {
            $parts = [];
            $seen = [];
            while ($id > 0 && isset($categoryById[$id]) && !isset($seen[$id])) {
                $seen[$id] = true;
                array_unshift($parts, (string) $categoryById[$id]['name']);
                $id = (int) ($categoryById[$id]['parent_id'] ?? 0);
            }

            return implode(' / ', $parts);
        };
        $enrichment = new JobRoleEnrichmentService($this->pdo);
        $decorations = $enrichment->decorations($tenantId);
        $structure = (new PersonnelJobRoleStructureService($this->pdo))->listMetadata($tenantId);
        $billetsByJob = $this->billetsByJob($tenantId);
        $jobs = [];
        foreach ($this->jobRoles->listRolesWithCategory($tenantId) as $role) {
            $id = (int) $role['id'];
            $deco = $decorations[$id] ?? ['abbreviation' => null, 'icon' => null, 'icon_is_default' => false, 'description' => '', 'tags' => [],
                'origin' => JobRoleEnrichmentService::origin($role), 'hidden' => false];
            $meta = $structure[$id] ?? [];
            $linkedUnit = $unitView((int) ($meta['linked_unit_id'] ?? 0));
            $linkedIds = [];
            $linkedChain = [];
            if ($linkedUnit !== null && $linkedUnit['id'] > 0) {
                $linkedIds[] = $linkedUnit['id'];
                $linkedChain = $linkedUnit['chain'];
            }
            $places = 0;
            $billets = [];
            foreach ($billetsByJob[$id] ?? [] as $b) {
                $bu = $unitView((int) $b['unit_id']);
                $places += (int) $b['slots'];
                $billets[] = $b + ['unit' => $bu];
                if ($bu !== null && $bu['id'] > 0) {
                    $linkedIds[] = $bu['id'];
                    $linkedChain = array_merge($linkedChain, $bu['chain']);
                }
            }
            $jobs[] = [
                'id' => $id,
                'name' => (string) $role['name'],
                'code' => (string) ($meta['code'] ?? ''),
                'kind' => (string) ($meta['kind'] ?? 'employment'),
                'category_id' => (int) $role['category_id'],
                'category' => $categoryPath((int) $role['category_id']),
                'abbreviation' => $deco['abbreviation'] ?? null,
                'decoration' => $deco,
                'tags' => $deco['tags'] ?? [],
                'origin' => (string) ($deco['origin'] ?? JobRoleEnrichmentService::ORIGIN_COMMUNITY),
                'hidden' => !empty($deco['hidden']),
                'linked_unit' => $linkedUnit,
                'linked_unit_ids' => array_values(array_unique($linkedIds)),
                'linked_unit_chain' => array_values(array_unique($linkedChain)),
                'billets' => $billets,
                'places' => $places,
                'holder_keys' => $holdersByJob[$id] ?? [],
            ];
        }

        return [
            'ready' => true,
            'jobs' => $jobs,
            'members' => $members,
            'categories' => $categories,
            'tags' => $enrichment->tagsReady() ? $enrichment->tags($tenantId) : [],
            'units' => $unitOptions,
            'truncated' => $total > self::MAX_MEMBERS,
        ];
    }

    /**
     * Situation de service saisie au dossier (« actif », « réserve »…), quand la colonne existe.
     *
     * @param list<int> $ids
     * @return array<int, string>
     */
    private function situations(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $st = $this->pdo->prepare('SELECT pp.user_id, pp.service_status FROM personnel_profiles pp
                INNER JOIN users u ON u.id = pp.user_id AND u.tenant_id = ? WHERE pp.user_id IN (' . $ph . ')');
            $st->execute(array_merge([$tenantId], $ids));
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $value = trim((string) ($row['service_status'] ?? ''));
                if ($value !== '') {
                    $out[(int) $row['user_id']] = $value;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Postes ORBAT actifs rattachés à un emploi : places autorisées et places tenues aujourd’hui.
     *
     * @return array<int, list<array{id:int,title:string,code:string,unit_id:int,slots:int,filled:int}>>
     */
    private function billetsByJob(int $tenantId): array
    {
        $repo = $this->billets;
        if ($repo === null || !$repo->schemaReady()) {
            return [];
        }
        $rows = [];
        foreach ([
            "SELECT id, title, code, unit_id, authorized_slots, job_role_id FROM orbat_billets WHERE tenant_id = ? AND job_role_id IS NOT NULL AND COALESCE(status, 'active') = 'active' AND archived_at IS NULL",
            'SELECT id, title, code, unit_id, authorized_slots, job_role_id FROM orbat_billets WHERE tenant_id = ? AND job_role_id IS NOT NULL AND is_active = 1',
        ] as $sql) {
            try {
                $st = $this->pdo->prepare($sql);
                $st->execute([$tenantId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                break;
            } catch (\Throwable) {
                continue;
            }
        }
        if ($rows === []) {
            return [];
        }
        try {
            $filled = $repo->filledSeatCountsForTenant($tenantId);
        } catch (\Throwable) {
            $filled = [];
        }
        $out = [];
        foreach ($rows as $b) {
            $out[(int) $b['job_role_id']][] = [
                'id' => (int) $b['id'],
                'title' => (string) ($b['title'] ?? ''),
                'code' => (string) ($b['code'] ?? ''),
                'unit_id' => (int) ($b['unit_id'] ?? 0),
                'slots' => max(1, (int) ($b['authorized_slots'] ?? 1)),
                'filled' => (int) ($filled[(int) $b['id']] ?? 0),
            ];
        }

        return $out;
    }
}
