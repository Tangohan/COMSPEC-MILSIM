<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\CompetencyModuleRepository;
use App\Repositories\GradeRepository;
use App\Repositories\QualificationAwardRuleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Organization\OrgVisibilityService;
use App\Support\ConfidentialDisplay;
use App\Support\OrgVisibilityCapabilities;
use App\Support\VisibilityLevel;

/**
 * Vue commandement des compétences : où en est chaque membre, chaque unité et chaque emploi sur les modules
 * ALPHA → DELTA actifs de la communauté (statuts réels de user_progress, rien d'estimé).
 *
 * « Modules de référence » = modules actifs marqués obligatoires ; s'il n'y en a aucun, tous les modules actifs.
 * Écart = module de référence non validé. Écart critique = module de référence à renouveler (échéance dépassée)
 * ou non validé (échec), ou module « critique » pas encore validé.
 *
 * Le calcul (compute) est pur et testable sans base ; build() lit les données.
 */
final class CompetencyCommandBoard
{
    public const STATUSES = ['COMPLETED', 'IN_PROGRESS', 'NOT_STARTED', 'FAILED', 'EXPIRED'];

    /** Fenêtre « à renouveler bientôt » (jours). */
    public const SOON_DAYS = 30;

    public function __construct(
        private ?CompetencyModuleRepository $modules = null,
        private ?UserRepository $users = null,
        private ?QualificationAwardRuleRepository $memberData = null,
        private ?GradeRepository $grades = null,
        private ?UnitRepository $units = null,
        private ?CompetencyCatalogService $catalog = null,
    ) {
        $this->modules ??= new CompetencyModuleRepository();
        $this->users ??= \App\Core\Container::get(UserRepository::class);
        $this->memberData ??= new QualificationAwardRuleRepository();
        $this->grades ??= new GradeRepository();
        $this->units ??= new UnitRepository();
        $this->catalog ??= \App\Core\Container::get(CompetencyCatalogService::class);
    }

    /**
     * @param array<int, list<int>> $groupsByUser user_id => groupes de suivi
     * @return array<string, mixed>
     */
    public function build(int $tenantId, OrgVisibilityCapabilities $caps, array $groupsByUser = []): array
    {
        if (!$this->modules->schemaReady()) {
            return self::compute([], [], [], [], [], [], [], $groupsByUser, time()) + ['schema_ready' => false];
        }
        $modules = array_values(array_filter($this->modules->listForTenant($tenantId), static fn (array $m): bool => !empty($m['is_active'])));
        $ids = array_map(static fn (array $m): int => (int) $m['id'], $modules);
        $progress = $this->modules->progressMatrix($tenantId, $ids);

        $members = [];
        try {
            $rows = $this->users->listForTenant($tenantId);
        } catch (\Throwable) {
            $rows = [];
        }
        foreach ($rows as $u) {
            $status = strtolower((string) ($u['status'] ?? 'active'));
            if (in_array($status, ['inactive', 'banned', 'deleted'], true) || (int) ($u['id'] ?? 0) < 1) {
                continue;
            }
            $members[(int) $u['id']] = [
                'id' => (int) $u['id'],
                'display_name' => trim((string) ($u['display_name'] ?? '')),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
            ];
        }
        $details = $members !== [] ? $this->memberData->memberDetails($tenantId, array_keys($members)) : [];
        $jobsByUser = $this->memberData->jobRolesByUser($tenantId);
        foreach ($members as $uid => $m) {
            $d = $details[$uid] ?? [];
            $members[$uid] += [
                'unit_id' => (int) ($d['unit_id'] ?? 0),
                'grade_id' => (int) ($d['grade_id'] ?? 0),
                'job_ids' => array_values(array_unique($jobsByUser[$uid] ?? [])),
            ];
        }

        $grades = [];
        try {
            foreach ($this->grades->listForTenant($tenantId) as $g) {
                $grades[(int) $g['id']] = [
                    'label' => trim((string) ($g['label_short'] ?? '')) ?: trim((string) ($g['label_long'] ?? '')),
                    'order' => (int) ($g['sort_order'] ?? 0),
                ];
            }
        } catch (\Throwable) {
        }

        $unitRows = [];
        try {
            $unitRows = $this->units->listFlatForStructure($tenantId, true);
        } catch (\Throwable) {
        }
        $units = self::unitDisplay($unitRows, $caps);

        $catalog = [];
        try {
            $catalog = $this->catalog->build($tenantId, false);
        } catch (\Throwable) {
        }

        return self::compute($modules, $progress, $members, $grades, $units, $catalog['job_roles'] ?? [], $catalog, $groupsByUser, time())
            + ['schema_ready' => true];
    }

    /**
     * Libellé affichable de chaque unité selon la confidentialité (mêmes règles que l'ORBAT et la disponibilité des unités).
     *
     * @param list<array<string, mixed>> $unitRows
     * @return array<int, array{key: string, name: string, confidential: ?array<string, string>}>
     */
    public static function unitDisplay(array $unitRows, OrgVisibilityCapabilities $caps): array
    {
        $levels = OrgVisibilityService::effectiveUnitLevels($unitRows);
        $staff = $caps->bypassAll || $caps->viewHiddenUnits || $caps->viewRestrictedUnits;
        $out = [];
        foreach ($unitRows as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $level = $levels[$id] ?? VisibilityLevel::NORMAL;
            $name = trim((string) ($u['name'] ?? ''));
            if ($level === VisibilityLevel::HIDDEN && !$caps->canSeeUnitLevel(VisibilityLevel::HIDDEN)) {
                $out[$id] = ['key' => 'c-hidden', 'name' => ConfidentialDisplay::label(ConfidentialDisplay::UNIT_HIDDEN), 'confidential' => ConfidentialDisplay::payload(ConfidentialDisplay::UNIT_HIDDEN)];
            } elseif ($level === VisibilityLevel::ANONYMIZED && !$staff) {
                $out[$id] = ['key' => 'c-anon', 'name' => ConfidentialDisplay::label(ConfidentialDisplay::UNIT_ANONYMIZED), 'confidential' => ConfidentialDisplay::payload(ConfidentialDisplay::UNIT_ANONYMIZED)];
            } else {
                $kind = ConfidentialDisplay::kindForLevel($level, 'unit');
                $out[$id] = [
                    'key' => 'u' . $id,
                    'name' => $name !== '' ? $name : 'Unité',
                    'confidential' => $level === VisibilityLevel::NORMAL ? null : ($staff ? ConfidentialDisplay::staffPayload($level, 'unit') : ($kind !== null ? ConfidentialDisplay::payload($kind) : null)),
                ];
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $modules modules actifs (CompetencyModuleRepository::listForTenant)
     * @param array<int, array<int, array{status: string, expires_at: ?string}>> $progress
     * @param array<int, array<string, mixed>> $members id => {id, display_name, callsign, unit_id, grade_id, job_ids}
     * @param array<int, array{label: string, order: int}> $grades
     * @param array<int, array{key: string, name: string, confidential: ?array<string, string>}> $units
     * @param list<array<string, mixed>> $jobRoles {ref, title}
     * @param array<string, mixed> $catalog CompetencyCatalogService::build (compétences et leurs liens, cours)
     * @param array<int, list<int>> $groupsByUser
     * @return array<string, mixed>
     */
    public static function compute(array $modules, array $progress, array $members, array $grades, array $units, array $jobRoles, array $catalog, array $groupsByUser, int $now): array
    {
        $mandatoryIds = [];
        foreach ($modules as $m) {
            if (!empty($m['is_mandatory'])) {
                $mandatoryIds[(int) $m['id']] = true;
            }
        }
        $referenceAll = $mandatoryIds === [];
        $isReference = static fn (array $m): bool => $referenceAll || isset($mandatoryIds[(int) $m['id']]);
        $soonLimit = $now + self::SOON_DAYS * 86400;
        $links = self::moduleLinks($catalog);

        $jobNames = [];
        foreach ($jobRoles as $j) {
            $jobNames[(int) ($j['ref'] ?? 0)] = (string) ($j['title'] ?? '');
        }

        $moduleOut = [];
        foreach ($modules as $m) {
            $id = (int) $m['id'];
            $moduleOut[$id] = [
                'id' => $id,
                'code' => (string) ($m['code'] ?? ''),
                'name' => (string) ($m['name'] ?? ''),
                'phase' => (string) ($m['module_type'] ?? 'ALPHA'),
                'mandatory' => isset($mandatoryIds[$id]),
                'reference' => $isReference($m),
                'critical' => (string) ($m['delivery_mode'] ?? '') === 'CRITIQUE',
                'recurrence_days' => $m['recurrence_days'] ?? null,
                'counts' => array_fill_keys(self::STATUSES, 0),
                'soon' => 0,
                'critical_gaps' => 0,
                'pct' => 0,
                'courses' => $links[$id]['courses'] ?? [],
                'qualifications' => $links[$id]['qualifications'] ?? [],
                'competency_id' => $links[$id]['competency_id'] ?? 0,
            ];
        }

        $memberOut = [];
        $kpi = ['ref_done' => 0, 'ref_total' => 0, 'critical' => 0, 'members_critical' => 0, 'members_progress' => 0, 'soon' => 0, 'members_complete' => 0];
        foreach ($members as $uid => $u) {
            $callsign = trim((string) ($u['callsign'] ?? ''));
            $display = trim((string) ($u['display_name'] ?? ''));
            $gradeId = (int) ($u['grade_id'] ?? 0);
            $unitId = (int) ($u['unit_id'] ?? 0);
            $unit = $unitId > 0 && isset($units[$unitId]) ? $units[$unitId] : ['key' => 'u0', 'name' => '', 'confidential' => null];
            $row = [
                'id' => (int) $uid,
                'name' => $callsign !== '' ? $callsign : ($display !== '' ? $display : 'Membre #' . $uid),
                'sub' => $callsign !== '' && $display !== '' && strcasecmp($callsign, $display) !== 0 ? $display : '',
                'grade_id' => $gradeId,
                'grade_label' => (string) ($grades[$gradeId]['label'] ?? ''),
                'grade_order' => (int) ($grades[$gradeId]['order'] ?? 0),
                'unit_key' => $unit['key'],
                'job_ids' => array_values(array_filter(array_map('intval', (array) ($u['job_ids'] ?? [])), static fn (int $j): bool => isset($jobNames[$j]))),
                'group_ids' => array_values(array_map('intval', $groupsByUser[(int) $uid] ?? [])),
                'cells' => [],
                'ref_done' => 0,
                'ref_total' => 0,
                'critical' => 0,
                'in_progress' => 0,
                'soon' => [],
            ];
            foreach ($moduleOut as $mid => $mod) {
                $cell = $progress[(int) $uid][$mid] ?? null;
                $status = $cell['status'] ?? 'NOT_STARTED';
                if (!in_array($status, self::STATUSES, true)) {
                    $status = 'NOT_STARTED';
                }
                $row['cells'][$mid] = $status;
                $moduleOut[$mid]['counts'][$status]++;
                if ($status === 'IN_PROGRESS') {
                    $row['in_progress']++;
                }
                $isCritical = ($mod['reference'] && in_array($status, ['EXPIRED', 'FAILED'], true)) || ($mod['critical'] && $status !== 'COMPLETED');
                if ($isCritical) {
                    $row['critical']++;
                    $moduleOut[$mid]['critical_gaps']++;
                }
                if ($mod['reference']) {
                    $row['ref_total']++;
                    if ($status === 'COMPLETED') {
                        $row['ref_done']++;
                    }
                }
                $exp = $cell['expires_at'] ?? null;
                if ($status === 'COMPLETED' && $exp !== null && ($ts = strtotime($exp)) !== false && $ts >= $now && $ts <= $soonLimit) {
                    $row['soon'][] = ['module_id' => $mid, 'date' => date('Y-m-d', $ts)];
                    $moduleOut[$mid]['soon']++;
                }
            }
            $row['pct'] = $row['ref_total'] > 0 ? (int) floor($row['ref_done'] / $row['ref_total'] * 100) : 0;
            $kpi['ref_done'] += $row['ref_done'];
            $kpi['ref_total'] += $row['ref_total'];
            $kpi['critical'] += $row['critical'];
            $kpi['soon'] += count($row['soon']);
            if ($row['critical'] > 0) {
                $kpi['members_critical']++;
            }
            if ($row['in_progress'] > 0) {
                $kpi['members_progress']++;
            }
            if ($row['ref_total'] > 0 && $row['ref_done'] === $row['ref_total']) {
                $kpi['members_complete']++;
            }
            $memberOut[] = $row;
        }
        $memberCount = count($memberOut);
        foreach ($moduleOut as $mid => $mod) {
            $moduleOut[$mid]['pct'] = $memberCount > 0 ? (int) floor($mod['counts']['COMPLETED'] / $memberCount * 100) : 0;
        }
        // Les plus en retard d'abord : écarts critiques, puis couverture la plus faible, puis le nom.
        usort($memberOut, static fn (array $a, array $b): int => [$b['critical'], $a['pct'], -$b['grade_order'], mb_strtolower($a['name'])] <=> [$a['critical'], $b['pct'], -$a['grade_order'], mb_strtolower($b['name'])]);

        $unitOut = self::aggregate($memberOut, static fn (array $r): array => [$r['unit_key']]);
        foreach ($unitOut as $key => $agg) {
            $unitId = 0;
            foreach ($units as $id => $u) {
                if ($u['key'] === $key) {
                    $unitId = $id;
                    break;
                }
            }
            $unitOut[$key]['key'] = $key;
            $unitOut[$key]['name'] = $key === 'u0' ? '' : (string) ($units[$unitId]['name'] ?? '');
            $unitOut[$key]['confidential'] = $key === 'u0' ? null : ($units[$unitId]['confidential'] ?? null);
        }
        uasort($unitOut, static fn (array $a, array $b): int => [$a['key'] === 'u0', str_starts_with((string) $a['key'], 'c-'), mb_strtolower((string) $a['name'])] <=> [$b['key'] === 'u0', str_starts_with((string) $b['key'], 'c-'), mb_strtolower((string) $b['name'])]);

        $jobOut = self::aggregate($memberOut, static fn (array $r): array => array_map(static fn (int $j): string => (string) $j, $r['job_ids']));
        foreach ($jobOut as $key => $agg) {
            $jobOut[$key]['key'] = (string) $key;
            $jobOut[$key]['name'] = $jobNames[(int) $key] ?? '';
        }
        uasort($jobOut, static fn (array $a, array $b): int => [$a['pct'], mb_strtolower((string) $a['name'])] <=> [$b['pct'], mb_strtolower((string) $b['name'])]);

        $gradeOut = [];
        foreach ($memberOut as $r) {
            if ($r['grade_id'] > 0 && $r['grade_label'] !== '') {
                $gradeOut[$r['grade_id']] = ['id' => $r['grade_id'], 'label' => $r['grade_label'], 'order' => $r['grade_order']];
            }
        }
        uasort($gradeOut, static fn (array $a, array $b): int => $b['order'] <=> $a['order']);

        $moduleList = array_values($moduleOut);
        // Écarts à combler : modules de référence d'abord, écarts critiques, puis couverture la plus faible.
        $gaps = $moduleList;
        usort($gaps, static fn (array $a, array $b): int => [!$a['reference'], -$a['critical_gaps'], $a['pct']] <=> [!$b['reference'], -$b['critical_gaps'], $b['pct']]);

        return [
            'reference_all' => $referenceAll,
            'modules' => $moduleList,
            'gaps' => $gaps,
            'members' => $memberOut,
            'units' => array_values($unitOut),
            'jobs' => array_values($jobOut),
            'grades' => array_values($gradeOut),
            'kpi' => $kpi + [
                'pct' => $kpi['ref_total'] > 0 ? (int) floor($kpi['ref_done'] / $kpi['ref_total'] * 100) : 0,
                'members' => $memberCount,
                'modules' => count($moduleList),
                'reference_modules' => count(array_filter($moduleList, static fn (array $m): bool => $m['reference'])),
            ],
        ];
    }

    /**
     * Totaux par unité ou par emploi.
     *
     * @param list<array<string, mixed>> $members
     * @param callable(array<string, mixed>): list<string> $keys
     * @return array<string, array<string, int>>
     */
    private static function aggregate(array $members, callable $keys): array
    {
        $out = [];
        foreach ($members as $r) {
            foreach ($keys($r) as $k) {
                $out[$k] ??= ['members' => 0, 'ref_done' => 0, 'ref_total' => 0, 'critical' => 0, 'members_critical' => 0, 'members_progress' => 0, 'pct' => 0];
                $out[$k]['members']++;
                $out[$k]['ref_done'] += $r['ref_done'];
                $out[$k]['ref_total'] += $r['ref_total'];
                $out[$k]['critical'] += $r['critical'];
                $out[$k]['members_critical'] += $r['critical'] > 0 ? 1 : 0;
                $out[$k]['members_progress'] += $r['in_progress'] > 0 ? 1 : 0;
            }
        }
        foreach ($out as $k => $a) {
            $out[$k]['pct'] = $a['ref_total'] > 0 ? (int) floor($a['ref_done'] / $a['ref_total'] * 100) : 0;
        }

        return $out;
    }

    /**
     * Pour chaque module : cours Academy / formations et qualifications reliés par le référentiel des compétences
     * (compétence liée au module ET à ces éléments), et la première compétence liée (générateur de parcours).
     *
     * @param array<string, mixed> $catalog
     * @return array<int, array{courses: list<array{title: string, url: string, kind: string}>, qualifications: list<string>, competency_id: int}>
     */
    public static function moduleLinks(array $catalog): array
    {
        $byRef = [];
        foreach (['academy_course' => 'academy_courses', 'training_course' => 'training_courses', 'qualification' => 'qualifications'] as $type => $key) {
            foreach ((array) ($catalog[$key] ?? []) as $item) {
                $byRef[$type][(string) ($item['ref'] ?? '')] = $item;
            }
        }
        $out = [];
        foreach ((array) ($catalog['competencies'] ?? []) as $c) {
            $moduleIds = [];
            foreach ((array) ($c['links'] ?? []) as $l) {
                if (($l['type'] ?? '') === 'module' && (int) ($l['ref'] ?? 0) > 0) {
                    $moduleIds[] = (int) $l['ref'];
                }
            }
            if ($moduleIds === []) {
                continue;
            }
            foreach ($moduleIds as $mid) {
                $out[$mid] ??= ['courses' => [], 'qualifications' => [], 'competency_id' => (int) ($c['id'] ?? 0)];
                foreach ((array) ($c['links'] ?? []) as $l) {
                    $type = (string) ($l['type'] ?? '');
                    $item = $byRef[$type][(string) ($l['ref'] ?? '')] ?? null;
                    if ($item === null) {
                        continue;
                    }
                    if ($type === 'qualification') {
                        $out[$mid]['qualifications'][(string) $item['ref']] = (string) ($item['title'] ?? '');
                    } elseif (($item['url'] ?? '') !== '') {
                        $out[$mid]['courses'][$type . ':' . $item['ref']] = ['title' => (string) ($item['title'] ?? ''), 'url' => (string) $item['url'], 'kind' => $type];
                    }
                }
            }
        }
        foreach ($out as $mid => $o) {
            $out[$mid]['courses'] = array_values($o['courses']);
            $out[$mid]['qualifications'] = array_values($o['qualifications']);
        }

        return $out;
    }
}
