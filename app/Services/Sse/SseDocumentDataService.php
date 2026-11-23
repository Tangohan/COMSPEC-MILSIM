<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCaseRepository;
use App\Services\Organization\OrgVisibilityService;
use App\Support\OrgVisibilityCapabilities;
use App\Support\VisibilityLevel;

/**
 * Données de la communauté insérables dans un document SSE : extrait d'ORBAT d'une unité (arbre d'unités et affectations),
 * fiche d'un membre, tableau des membres d'une unité.
 *
 * Aucun accès propre : tout passe par les règles existantes de l'ORBAT (OrgVisibilityService et OrgVisibilityCapabilities :
 * niveau effectif des unités avec propagation, personnel masqué ou anonymisé, affectations restreintes, qualifications
 * filtrées) et par le cloisonnement par communauté (toutes les sources sont déjà celles de la communauté du rédacteur). Une
 * unité hors périmètre est refusée ; un membre masqué ou anonymisé n'est jamais nommé. Aucune donnée d'état civil : seulement
 * nom d'affichage, grade, indicatif, unité, emploi et qualifications visibles.
 *
 * Le résultat est un bloc STATIQUE, horodaté (« extrait au … »), qui rappelle sa source et porte data-src (type et numéro) et
 * data-lock : le texte n'est plus relié aux données. La classification minimale du bloc se déduit du niveau de visibilité des
 * unités et des personnes concernées ({@see self::LEVEL_CLASSIFICATION}) ; à l'enregistrement, le serveur la recalcule depuis
 * data-src (jamais depuis ce que dit le client) et relève la classification du document si besoin.
 */
final class SseDocumentDataService
{
    /** Niveau de visibilité ORBAT => classification minimale du document. */
    public const LEVEL_CLASSIFICATION = [
        VisibilityLevel::NORMAL => SseCaseRepository::CLASS_INTERNAL,
        VisibilityLevel::RESTRICTED => SseCaseRepository::CLASS_COMMAND,
        VisibilityLevel::ANONYMIZED => SseCaseRepository::CLASS_CONFIDENTIAL,
        VisibilityLevel::HIDDEN => SseCaseRepository::CLASS_RESTRICTED,
    ];

    private const RANK = [
        SseCaseRepository::CLASS_INTERNAL => 1,
        SseCaseRepository::CLASS_COMMAND => 2,
        SseCaseRepository::CLASS_CONFIDENTIAL => 3,
        SseCaseRepository::CLASS_RESTRICTED => 4,
    ];

    private const MAX_UNITS = 60;

    /** @var array<int, array<string,mixed>> */
    private array $unitsById = [];

    /** @var array<int, string> */
    private array $levels = [];

    /** @var callable(): string */
    private $clock;

    /**
     * @param list<array<string,mixed>> $units lignes units de la communauté (id, parent_id, name, visibility_level…)
     * @param array<int, list<array{user_id:int,role_name:string,callsign:string,display_name:string}>> $membersByUnit
     * @param array<int, array<string,mixed>> $visByUser user_id => visibility_level, assignment_visibility, anonymized_label
     * @param callable(int): string $gradeOf libellé de grade d'un membre
     * @param callable(int): list<array<string,mixed>> $qualificationsOf qualifications déjà filtrées pour le lecteur
     * @param (callable(): string)|null $clock date et heure affichées (tests)
     */
    public function __construct(
        private OrgVisibilityCapabilities $caps,
        array $units,
        private array $membersByUnit,
        private array $visByUser,
        private $gradeOf,
        private $qualificationsOf,
        ?callable $clock = null
    ) {
        foreach ($units as $u) {
            if ((int) ($u['id'] ?? 0) > 0) {
                $this->unitsById[(int) $u['id']] = $u;
            }
        }
        $this->levels = OrgVisibilityService::effectiveUnitLevels(array_values($this->unitsById));
        $this->clock = $clock ?? static fn (): string => date('d/m/Y à H:i');
    }

    /** Service du rédacteur courant : sources et capacités de l'ORBAT, pour la communauté donnée. */
    public static function forSession(int $tenantId): self
    {
        $caps = OrgVisibilityCapabilities::fromGate();
        $unitsRepo = new \App\Repositories\UnitRepository();
        $units = $unitsRepo->allForTenant($tenantId);
        $members = (new \App\Repositories\PersonnelAssignmentRepository())->listActiveMembersByUnitForTenant($tenantId);
        $ids = [];
        foreach ($members as $rows) {
            foreach ($rows as $r) {
                $ids[] = (int) $r['user_id'];
            }
        }
        $vis = $unitsRepo->personnelVisibilityByUserIdsForTenant($tenantId, $ids);
        $grades = [];
        $gradeOf = static function (int $uid) use ($tenantId, &$grades): string {
            if (isset($grades[$uid])) {
                return $grades[$uid];
            }
            $label = '';
            try {
                $row = (new \App\Repositories\UserRepository())->findById($uid, $tenantId);
                $gid = (int) ($row['grade_id'] ?? 0);
                $g = $gid > 0 ? (new \App\Repositories\GradeRepository())->findById($gid, $tenantId) : null;
                $label = is_array($g) ? (string) \App\Support\GradeSelectOptions::label($g) : '';
            } catch (\Throwable) {
                $label = '';
            }

            return $grades[$uid] = $label;
        };
        $qualRepo = new \App\Repositories\PersonnelQualificationRepository();
        $qualsOf = static function (int $uid) use ($qualRepo, $caps, $tenantId): array {
            try {
                return $qualRepo->filterForViewer($qualRepo->listForUser($uid, $tenantId), $caps);
            } catch (\Throwable) {
                return [];
            }
        };

        return new self($caps, $units, $members, $vis, $gradeOf, $qualsOf);
    }

    public static function rank(string $classification): int
    {
        return self::RANK[SseCaseRepository::normalizeClassification($classification)] ?? 2;
    }

    public static function maxClassification(string $a, string $b): string
    {
        return self::rank($a) >= self::rank($b) ? SseCaseRepository::normalizeClassification($a) : SseCaseRepository::normalizeClassification($b);
    }

    /** L'unité est-elle dans le périmètre de lecture du rédacteur ? */
    public function canSeeUnit(int $unitId): bool
    {
        if (!isset($this->unitsById[$unitId])) {
            return false;
        }
        $level = $this->levels[$unitId] ?? VisibilityLevel::NORMAL;
        if ($level === VisibilityLevel::NORMAL) {
            return true;
        }
        if ($this->caps->bypassAll) {
            return true;
        }
        // Restreinte, anonymisée ou masquée : seuls les lecteurs habilités à voir ce niveau d'unité (un nom anonymisé n'a aucun intérêt dans un document).
        return $level === VisibilityLevel::HIDDEN ? $this->caps->viewHiddenUnits : ($this->caps->viewRestrictedUnits || $this->caps->viewHiddenUnits);
    }

    /** @return list<array{id:int,name:string,depth:int,level:string}> unités proposées, en arbre aplati */
    public function selectableUnits(): array
    {
        $out = [];
        $children = [];
        foreach ($this->unitsById as $id => $u) {
            $children[(int) ($u['parent_id'] ?? 0)][] = $id;
        }
        $walk = function (int $parent, int $depth, array $seen) use (&$walk, &$out, $children): void {
            foreach ($children[$parent] ?? [] as $id) {
                if (isset($seen[$id]) || count($out) >= self::MAX_UNITS * 3) {
                    continue;
                }
                if ($this->canSeeUnit($id)) {
                    $out[] = ['id' => $id, 'name' => $this->unitName($id), 'depth' => $depth, 'level' => $this->levels[$id] ?? VisibilityLevel::NORMAL];
                    $walk($id, $depth + 1, $seen + [$id => true]);
                }
            }
        };
        $roots = array_filter(array_keys($this->unitsById), fn (int $id): bool => !isset($this->unitsById[(int) ($this->unitsById[$id]['parent_id'] ?? 0)]));
        foreach ($roots as $id) {
            if ($this->canSeeUnit($id)) {
                $out[] = ['id' => $id, 'name' => $this->unitName($id), 'depth' => 0, 'level' => $this->levels[$id] ?? VisibilityLevel::NORMAL];
                $walk($id, 1, [$id => true]);
            }
        }

        return $out;
    }

    /**
     * Membres nommables d'une unité visible, après les règles de confidentialité (masqués retirés, anonymisés sans nom).
     *
     * @return list<array<string,mixed>>
     */
    public function rosterOf(int $unitId): array
    {
        if (!$this->canSeeUnit($unitId)) {
            return [];
        }
        $rows = [];
        foreach ($this->membersByUnit[$unitId] ?? [] as $m) {
            $rows[] = ['user_id' => (int) $m['user_id'], 'label' => (string) ($m['display_name'] !== '' ? $m['display_name'] : $m['callsign']),
                'callsign' => (string) $m['callsign'], 'role_name' => (string) $m['role_name']];
        }

        return OrgVisibilityService::rosterForViewer($rows, $this->caps, $this->levels[$unitId] ?? VisibilityLevel::NORMAL, $this->visByUser);
    }

    /** @return list<array{id:int,label:string,unit:string}> membres nommables, pour le menu */
    public function selectableMembers(): array
    {
        $out = [];
        $seen = [];
        foreach ($this->selectableUnits() as $u) {
            foreach ($this->rosterOf($u['id']) as $m) {
                $uid = (int) ($m['user_id'] ?? 0);
                if ($uid < 1 || isset($seen[$uid]) || !empty($m['anonymized']) || !empty($m['assignment_redacted'])) {
                    continue;
                }
                $seen[$uid] = true;
                $out[] = ['id' => $uid, 'label' => (string) ($m['label'] ?? ''), 'unit' => $u['name']];
            }
        }

        return $out;
    }

    /**
     * Bloc « extrait d'ORBAT » : l'unité et ses sous-unités visibles, avec leurs affectations.
     *
     * @return array{ok:bool,html?:string,classification?:string,label?:string,error?:string}
     */
    public function orbatExtract(int $unitId): array
    {
        if (!$this->canSeeUnit($unitId)) {
            return ['ok' => false, 'error' => 'Unité hors de votre périmètre.'];
        }
        $name = $this->unitName($unitId);
        $min = $this->levelClass($unitId);
        $html = '';
        $count = 0;
        $walk = function (int $id, int $depth, array $seen) use (&$walk, &$html, &$min, &$count): void {
            if ($count >= self::MAX_UNITS || isset($seen[$id]) || !$this->canSeeUnit($id)) {
                return;
            }
            ++$count;
            $min = self::maxClassification($min, $this->levelClass($id));
            $html .= '<li><strong>' . $this->e($this->unitName($id)) . '</strong>';
            $people = [];
            foreach ($this->rosterOf($id) as $m) {
                $min = self::maxClassification($min, $this->personClass((int) ($m['user_id'] ?? 0)));
                $role = trim((string) ($m['role_name'] ?? ''));
                $people[] = '<li>' . $this->e((string) ($m['label'] ?? '')) . ($role !== '' ? ' — ' . $this->e($role) : '') . '</li>';
            }
            $kids = [];
            foreach ($this->unitsById as $cid => $cu) {
                if ((int) ($cu['parent_id'] ?? 0) === $id) {
                    $kids[] = $cid;
                }
            }
            $inner = implode('', $people);
            if ($inner !== '') {
                $html .= '<ul>' . $inner . '</ul>';
            }
            if ($kids !== []) {
                $before = strlen($html);
                $html .= '<ul>';
                foreach ($kids as $cid) {
                    $walk($cid, $depth + 1, $seen + [$id => true]);
                }
                $html .= '</ul>';
                if (str_ends_with($html, '<ul></ul>')) {
                    $html = substr($html, 0, $before);
                }
            }
            $html .= '</li>';
        };
        $walk($unitId, 0, []);

        return $this->block('unit:' . $unitId, 'Extrait d’ORBAT — ' . $name, 'ORBAT de la communauté, unité « ' . $name . ' »', '<ul>' . $html . '</ul>', $min);
    }

    /**
     * Fiche d'un membre visible : nom, grade, indicatif, unité, emploi, qualifications visibles. Jamais d'état civil.
     *
     * @return array{ok:bool,html?:string,classification?:string,label?:string,error?:string}
     */
    public function memberCard(int $userId): array
    {
        foreach ($this->selectableUnits() as $u) {
            foreach ($this->rosterOf($u['id']) as $m) {
                if ((int) ($m['user_id'] ?? 0) !== $userId || $userId < 1 || !empty($m['anonymized']) || !empty($m['assignment_redacted'])) {
                    continue;
                }
                $quals = [];
                foreach (($this->qualificationsOf)($userId) as $q) {
                    if (!empty($q['visibility_redacted'])) {
                        continue;
                    }
                    $qn = trim((string) ($q['qualification_name'] ?? ''));
                    if ($qn !== '') {
                        $quals[] = $this->e($qn);
                    }
                }
                $callsign = trim((string) ($m['callsign'] ?? ''));
                $rows = [
                    ['Nom', (string) ($m['label'] ?? '')],
                    ['Grade', ($this->gradeOf)($userId)],
                    ['Indicatif', $callsign],
                    ['Unité', $u['name']],
                    ['Emploi', (string) ($m['role_name'] ?? '')],
                ];
                $body = '<table><tbody>';
                foreach ($rows as [$k, $v]) {
                    $body .= '<tr><td style="background-color:#e2e8f0;"><strong>' . $k . '</strong></td><td>' . $this->e($v !== '' ? $v : '—') . '</td></tr>';
                }
                $body .= '<tr><td style="background-color:#e2e8f0;"><strong>Qualifications</strong></td><td>' . ($quals !== [] ? implode(' ; ', $quals) : '—') . '</td></tr></tbody></table>';
                $min = self::maxClassification($this->levelClass($u['id']), $this->personClass($userId));
                $label = (string) ($m['label'] ?? '');

                return $this->block('member:' . $userId, 'Fiche membre — ' . $label, 'fiche membre de la communauté, unité « ' . $u['name'] . ' »', $body, $min);
            }
        }

        return ['ok' => false, 'error' => 'Membre hors de votre périmètre.'];
    }

    /**
     * Tableau des membres d'une unité visible.
     *
     * @return array{ok:bool,html?:string,classification?:string,label?:string,error?:string}
     */
    public function unitRosterTable(int $unitId): array
    {
        if (!$this->canSeeUnit($unitId)) {
            return ['ok' => false, 'error' => 'Unité hors de votre périmètre.'];
        }
        $name = $this->unitName($unitId);
        $min = $this->levelClass($unitId);
        $body = '<table><tbody><tr><th>Nom</th><th>Grade</th><th>Indicatif</th><th>Emploi</th></tr>';
        $n = 0;
        foreach ($this->rosterOf($unitId) as $m) {
            $uid = (int) ($m['user_id'] ?? 0);
            $anon = $uid < 1 || !empty($m['anonymized']);
            if (!$anon) {
                $min = self::maxClassification($min, $this->personClass($uid));
            }
            $body .= '<tr><td>' . $this->e((string) ($m['label'] ?? '')) . '</td><td>' . $this->e($anon ? '—' : ($this->gradeOf)($uid)) . '</td><td>'
                . $this->e($anon ? '—' : (string) ($m['callsign'] ?? '')) . '</td><td>' . $this->e($anon ? '—' : (string) ($m['role_name'] ?? '')) . '</td></tr>';
            ++$n;
        }
        $body .= '</tbody></table>';
        if ($n === 0) {
            $body = '<p>Aucun membre visible dans cette unité.</p>';
        }

        return $this->block('roster:' . $unitId, 'Effectif — ' . $name, 'effectif de l’unité « ' . $name . ' »', $body, $min);
    }

    /**
     * Classification minimale des blocs de données d'un corps (calculée par le serveur depuis data-src, indépendante du lecteur).
     *
     * @param string $html corps assaini
     */
    public function minimumFor(string $html): string
    {
        $min = SseCaseRepository::CLASS_INTERNAL;
        if (!str_contains($html, 'data-src="')) {
            return $min;
        }
        preg_match_all('~data-src="(unit|roster|member):([0-9]{1,10})"~', $html, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            $id = (int) $hit[2];
            if ($hit[1] === 'member') {
                $min = self::maxClassification($min, $this->personClass($id));
                foreach ($this->membersByUnit as $unit => $rows) {
                    foreach ($rows as $r) {
                        if ((int) $r['user_id'] === $id) {
                            $min = self::maxClassification($min, $this->levelClass((int) $unit));
                        }
                    }
                }
            } else {
                // Tout l'arbre sous l'unité : le niveau le plus élevé rencontré.
                // Le niveau de l'unité visée compte même pour un lecteur qui ne la voit pas (bloc inséré par un autre rédacteur).
                $min = self::maxClassification($min, self::maxClassification($this->levelClass($id), $this->subtreeClass($id, 0)));
            }
        }

        return $min;
    }

    /** Niveau le plus élevé de ce que le lecteur voit sous l'unité (unités visibles et personnes nommables). */
    private function subtreeClass(int $id, int $depth): string
    {
        if (!$this->canSeeUnit($id)) {
            return SseCaseRepository::CLASS_INTERNAL;
        }
        $min = $this->levelClass($id);
        if ($depth > 12) {
            return $min;
        }
        foreach ($this->unitsById as $cid => $cu) {
            if ((int) ($cu['parent_id'] ?? 0) === $id && $cid !== $id) {
                $min = self::maxClassification($min, $this->subtreeClass($cid, $depth + 1));
            }
        }
        foreach ($this->rosterOf($id) as $r) {
            if ((int) ($r['user_id'] ?? 0) > 0 && empty($r['anonymized'])) {
                $min = self::maxClassification($min, $this->personClass((int) $r['user_id']));
            }
        }

        return $min;
    }

    private function levelClass(int $unitId): string
    {
        return self::LEVEL_CLASSIFICATION[$this->levels[$unitId] ?? VisibilityLevel::NORMAL];
    }

    private function personClass(int $userId): string
    {
        $level = VisibilityLevel::normalize((string) ($this->visByUser[$userId]['visibility_level'] ?? VisibilityLevel::NORMAL));

        return self::LEVEL_CLASSIFICATION[$level];
    }

    private function unitName(int $id): string
    {
        return trim((string) ($this->unitsById[$id]['name'] ?? '')) !== '' ? (string) $this->unitsById[$id]['name'] : 'Unité ' . $id;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @return array{ok:bool,html:string,classification:string,label:string} */
    private function block(string $src, string $title, string $sourceText, string $inner, string $min): array
    {
        $label = SseCaseRepository::classificationLabel($min);
        $html = '<div class="sse-data" data-src="' . $this->e($src) . '" data-lock="1"><p><strong>' . $this->e($title) . '</strong></p>'
            . '<p style="color:#475569;">Extrait au ' . $this->e(($this->clock)()) . ' · Source : ' . $this->e($sourceText) . ' · Classification minimale : ' . $this->e($label) . '</p>'
            . $inner . '</div>';

        return ['ok' => true, 'html' => $html, 'classification' => $min, 'label' => $title];
    }
}
