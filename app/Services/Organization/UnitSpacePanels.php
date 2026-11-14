<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\PersonnelCommandChainService;
use App\Services\Personnel\QualificationAwardRuleService;
use App\Support\UnitSpaceTabs;

/**
 * Données des onglets de l'espace d'unité, calculées à la demande (un seul onglet par requête).
 *
 * Rien n'est recalculé ici : chaque onglet appelle le service ou le dépôt de la page Effectifs d'origine
 * (tableur, chaîne de commandement, matrice des qualifications), restreint à l'unité et à ses sous-unités,
 * exactement comme le filtre « unité » du tableur (`?unite=`).
 */
final class UnitSpacePanels
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $memberRowsCache = [];

    public function __construct(
        private ?UnitRepository $units = null,
        private ?UserRepository $users = null,
        private ?PersonnelCommandChainService $chain = null,
        private ?QualificationAwardRuleService $qualifications = null,
    ) {
        $this->units ??= new UnitRepository();
        $this->users ??= new UserRepository();
    }

    /**
     * L'unité et toutes ses sous-unités (même périmètre que le filtre « unité » du tableur des effectifs).
     *
     * @return list<int>
     */
    public function scopeUnitIds(int $tenantId, int $unitId): array
    {
        return $this->units->expandUnitIdsWithDescendants($tenantId, [$unitId]);
    }

    /**
     * Membres actifs de l'unité et de ses sous-unités, rangés par grade puis par nom, bornés à UnitSpaceTabs::MEMBER_CAP.
     *
     * @return list<array{id: int, label: string, name: string, grade: string, unit: string, role: string, grade_order: int}>
     */
    private function orderedMembers(int $tenantId, int $unitId): array
    {
        $key = $tenantId . ':' . $unitId;
        if (isset($this->memberRowsCache[$key])) {
            return $this->memberRowsCache[$key];
        }
        $ids = $this->units->listActiveUserIdsForUnits($tenantId, $this->scopeUnitIds($tenantId, $unitId));
        sort($ids);
        $ids = array_slice($ids, 0, UnitSpaceTabs::MEMBER_CAP);
        $rows = [];
        foreach ($this->users->listEffectifsRosterByIds($tenantId, $ids) as $r) {
            $callsign = trim((string) ($r['callsign'] ?? ''));
            $display = trim((string) ($r['display_name'] ?? ''));
            $label = $callsign !== '' ? $callsign : ($display !== '' ? $display : ('#' . (int) ($r['id'] ?? 0)));
            $role = trim((string) ($r['assignment_role_name'] ?? ''));
            if ($role === '') {
                $role = trim((string) ($r['job_role_display'] ?? ''));
            }
            $rows[] = [
                'id' => (int) ($r['id'] ?? 0),
                'label' => $label,
                'name' => $display !== '' && $display !== $label ? $display : '',
                'grade' => function_exists('personnel_assigned_grade_label') ? personnel_assigned_grade_label($r) : trim((string) ($r['grade_short'] ?? '')),
                'unit' => trim((string) ($r['unit_name'] ?? '')),
                'role' => $role,
                'grade_order' => (int) ($r['grade_sort_order'] ?? 999),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$a['grade_order'], mb_strtolower($a['label'], 'UTF-8')] <=> [$b['grade_order'], mb_strtolower($b['label'], 'UTF-8')]);

        return $this->memberRowsCache[$key] = $rows;
    }

    /**
     * Onglet Membres : une page de la liste.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, shown: int, truncated: bool, page: int, pages: int, scope_units: int}
     */
    public function members(int $tenantId, int $unitId, mixed $rawPage): array
    {
        $all = $this->orderedMembers($tenantId, $unitId);
        $scope = $this->scopeUnitIds($tenantId, $unitId);
        $total = count($all);
        $p = UnitSpaceTabs::paginate($rawPage, $total);

        return [
            'rows' => array_slice($all, $p['offset'], $p['per']),
            'total' => $total,
            'shown' => $total,
            'truncated' => $total >= UnitSpaceTabs::MEMBER_CAP,
            'page' => $p['page'],
            'pages' => $p['pages'],
            'scope_units' => count($scope),
        ];
    }

    /**
     * Onglet Chaîne de commandement : chaîne au-dessus de l'unité, chef de l'unité et de ses sous-unités, écarts d'adéquation.
     *
     * @return array{ancestors: list<array<string, mixed>>, rows: list<array<string, mixed>>, missing: int, unit_adequacy: array<string, mixed>|null, issue_counts: array<int, int>}
     */
    public function chain(int $tenantId, int $unitId): array
    {
        $this->chain ??= new PersonnelCommandChainService($this->units, $this->users);
        $workspace = $this->chain->workspace($tenantId);
        $byId = [];
        foreach ($workspace['units'] as $row) {
            $byId[(int) $row['id']] = $row;
        }
        $self = $byId[$unitId] ?? null;
        $scope = array_flip($this->scopeUnitIds($tenantId, $unitId));

        $ancestors = [];
        $seen = [$unitId => true];
        $cursor = (int) ($self['parent_id'] ?? 0);
        while ($cursor > 0 && isset($byId[$cursor]) && !isset($seen[$cursor]) && count($ancestors) < 50) {
            $seen[$cursor] = true;
            array_unshift($ancestors, $byId[$cursor]);
            $cursor = (int) ($byId[$cursor]['parent_id'] ?? 0);
        }

        $baseDepth = (int) ($self['depth'] ?? 0);
        $rows = [];
        $missing = 0;
        foreach ($workspace['units'] as $row) {
            $id = (int) $row['id'];
            if (!isset($scope[$id])) {
                continue;
            }
            $row['rel_depth'] = max(0, (int) $row['depth'] - $baseDepth);
            if ((int) ($row['commander_user_id'] ?? 0) < 1) {
                $missing++;
            }
            $rows[] = $row;
        }

        $unitAdequacy = null;
        $issueCounts = [];
        try {
            $result = (new OrgAdequacyService($this->units, $this->users))->evaluate($tenantId);
            $unitAdequacy = OrgAdequacyView::forUnit($result, $unitId);
            foreach ($rows as $row) {
                $issueCounts[(int) $row['id']] = count(OrgAdequacyService::issuesFor($result, 'unit', (int) $row['id']));
            }
        } catch (\Throwable) {
            $unitAdequacy = null;
            $issueCounts = [];
        }

        return [
            'ancestors' => $ancestors,
            'rows' => $rows,
            'missing' => $missing,
            'unit_adequacy' => $unitAdequacy,
            'issue_counts' => $issueCounts,
        ];
    }

    /**
     * Onglet Qualifications : la matrice des effectifs, calculée seulement pour la page de membres affichée.
     *
     * @return array{matrix: array<string, mixed>, total: int, truncated: bool, page: int, pages: int}
     */
    public function qualifications(int $tenantId, int $unitId, mixed $rawPage): array
    {
        $all = $this->orderedMembers($tenantId, $unitId);
        $total = count($all);
        $p = UnitSpaceTabs::paginate($rawPage, $total);
        $ids = array_map(static fn (array $m): int => (int) $m['id'], array_slice($all, $p['offset'], $p['per']));
        $service = $this->qualifications ?? \App\Core\Container::get(QualificationAwardRuleService::class);
        try {
            $matrix = $ids === [] ? $this->emptyMatrix() : $service->matrix($tenantId, $ids);
        } catch (\Throwable) {
            $matrix = $this->emptyMatrix();
        }

        return [
            'matrix' => $matrix,
            'total' => $total,
            'truncated' => $total >= UnitSpaceTabs::MEMBER_CAP,
            'page' => $p['page'],
            'pages' => $p['pages'],
        ];
    }

    /** @return array<string, mixed> */
    private function emptyMatrix(): array
    {
        return ['definitions' => [], 'members' => [], 'cells' => [], 'counts' => ['ok' => 0, 'soon' => 0, 'exp' => 0, 'train' => 0], 'units' => [], 'coverage' => [], 'totals' => [], 'unlinked_academy' => 0];
    }
}
