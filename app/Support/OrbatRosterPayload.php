<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;
use App\Core\Session;
use App\Repositories\UnitRepository;
use App\Services\Effectifs\UnitCodeService;
use App\Services\Organization\OrgVisibilityService;

/**
 * Arbre ORBAT (format consommé par la vue / l’API) à partir des unités en base.
 * Les masques par unité sont appliqués côté serveur selon le lecteur.
 */
final class OrbatRosterPayload
{
    private const ORBAT_TYPES = ['command', 'alpha', 'bravo', 'support', 'special'];

    /**
     * @param array<int, int> $unitMemberCounts
     * @param array<int, string> $unitCommanderLabels
     * @param array<int, list<array{user_id: int, label: string, readiness?: int, readinessComponents?: array<string, mixed>}>> $unitRosterByUnit
     * @return array<string, mixed>
     */
    public static function normalizeNode(
        array $u,
        array $unitMemberCounts,
        array $unitCommanderLabels,
        array $unitRosterByUnit,
        array $unitCodes = []
    ): array {
        $rawType = (string) ($u['type'] ?? '');
        $structType = strtolower(trim($rawType));

        $displayType = 'command';
        if (!empty($u['orbat_display_type'])) {
            $d = strtolower(trim((string) $u['orbat_display_type']));
            $displayType = preg_replace('/[^a-z0-9_-]/', '', $d) ?: 'command';
        } else {
            $legacy = $rawType;
            $displayType = in_array($legacy, self::ORBAT_TYPES, true) ? $legacy : 'command';
        }

        $uid = (int) ($u['id'] ?? 0);
        $children = [];
        foreach ($u['children'] ?? [] as $c) {
            if (is_array($c)) {
                $children[] = self::normalizeNode($c, $unitMemberCounts, $unitCommanderLabels, $unitRosterByUnit, $unitCodes);
            }
        }
        $mission = '—';
        if (!empty(trim((string) ($u['public_blurb'] ?? '')))) {
            $mission = trim((string) $u['public_blurb']);
        }

        $details = '';
        if (!empty(trim((string) ($u['orbat_details'] ?? '')))) {
            $details = trim((string) $u['orbat_details']);
        }

        $iconPath = null;
        if (!empty($u['orbat_icon_path'])) {
            $iconPath = trim((string) $u['orbat_icon_path']);
        }
        $imagePath = null;
        if (!empty($u['orbat_image_path'])) {
            $imagePath = trim((string) $u['orbat_image_path']);
        }

        $maskMode = OrbatMaskMode::NONE;
        if (array_key_exists('orbat_mask_mode', $u)) {
            $maskMode = OrbatMaskMode::normalize((string) ($u['orbat_mask_mode'] ?? ''));
        }

        $visibilityLevel = VisibilityLevel::NORMAL;
        if (array_key_exists('visibility_level', $u)) {
            $visibilityLevel = VisibilityLevel::normalize((string) ($u['visibility_level'] ?? ''));
        } else {
            $visibilityLevel = VisibilityLevel::fromOrbatMaskMode($maskMode);
        }

        $adminStatus = UnitAdminStatus::ACTIVE;
        if (array_key_exists('admin_status', $u)) {
            $adminStatus = UnitAdminStatus::normalize((string) ($u['admin_status'] ?? ''));
        }

        $visibilityPropagate = true;
        if (array_key_exists('visibility_propagate', $u)) {
            $visibilityPropagate = (int) ($u['visibility_propagate'] ?? 1) === 1;
        }

        $strengthDisplayMode = 'visible_only';
        if (array_key_exists('strength_display_mode', $u)) {
            $strengthDisplayMode = strtolower(trim((string) ($u['strength_display_mode'] ?? 'visible_only')));
            if (!in_array($strengthDisplayMode, ['visible_only', 'generic', 'none', 'hidden'], true)) {
                $strengthDisplayMode = 'visible_only';
            }
        }

        $rawMembers = $unitRosterByUnit[$uid] ?? [];
        $visibleStrength = is_array($rawMembers) ? count($rawMembers) : 0;
        $symbol = UnitMilitarySymbol::resolve($u);

        return [
            'id' => 'unit-' . $uid,
            'unitId' => $uid,
            'label' => $u['name'] ?? 'Unité',
            'role' => !empty($u['code']) ? (string) $u['code'] : 'Unité',
            // Code unité hiérarchique (SOAR-B-A-1) et identifiant administratif fixe (0042-0137).
            'unitCode' => (string) ($unitCodes[$uid]['code'] ?? ''),
            'adminId' => (string) ($unitCodes[$uid]['admin_id'] ?? ''),
            // Symbole MIL-STD-2525 : base choisie (ou déduite), échelon et code final affiché par milsymbol.
            'sidc' => $symbol['sidc'],
            'sidcAuto' => $symbol['sidcAuto'],
            'echelon' => $symbol['echelon'],
            'echelonAuto' => $symbol['echelonAuto'],
            'symbolSidc' => $symbol['symbol'],
            'symbolFunction' => $symbol['functionKey'],
            'type' => $displayType,
            'structType' => $structType,
            'maskMode' => $maskMode,
            'visibilityLevel' => $visibilityLevel,
            'visibilityPropagate' => $visibilityPropagate,
            'strengthDisplayMode' => $strengthDisplayMode,
            'adminStatus' => $adminStatus,
            'adminStatusNote' => trim((string) ($u['admin_status_note'] ?? '')),
            'adminStatusLabel' => UnitAdminStatus::badgeLabel($adminStatus),
            'status' => $adminStatus === UnitAdminStatus::ACTIVE ? 'active' : (
                $adminStatus === UnitAdminStatus::PARTIALLY_ACTIVE ? 'partial' : (
                    in_array($adminStatus, [UnitAdminStatus::INACTIVE, UnitAdminStatus::ARCHIVED], true) ? 'inactive' : 'active'
                )
            ),
            'readinessState' => null,
            'strength' => (int) ($unitMemberCounts[$uid] ?? $visibleStrength),
            'visibleStrength' => $visibleStrength,
            'leader' => $unitCommanderLabels[$uid] ?? '—',
            'mission' => $mission,
            'orbatDetails' => $details,
            'chartIconUrl' => $iconPath !== null && $iconPath !== '' ? $iconPath : null,
            'chartImageUrl' => $imagePath !== null && $imagePath !== '' ? $imagePath : null,
            'commanderUserId' => (int) ($u['commander_user_id'] ?? 0),
            'motto' => trim((string) ($u['motto'] ?? '')),
            'descriptionLong' => trim((string) ($u['description_long'] ?? '')),
            'accentColor' => trim((string) ($u['accent_color'] ?? $u['public_accent_color'] ?? '')),
            'unitTypeId' => (int) ($u['unit_type_id'] ?? 0),
            'badgeMediaUrl' => (($bp = trim((string) ($u['badge_media_path'] ?? ''))) !== '') ? $bp : null,
            'showOnPublicPage' => !array_key_exists('show_on_public_page', $u)
                || (int) ($u['show_on_public_page'] ?? 0) === 1,
            'publicFoundedOn' => self::normalizePublicDate($u['public_founded_on'] ?? null),
            'publicCustomDate' => self::normalizePublicDate($u['public_custom_date'] ?? null),
            'publicCustomDateLabel' => trim((string) ($u['public_custom_date_label'] ?? '')),
            'members' => is_array($rawMembers) ? $rawMembers : [],
            'children' => $children,
        ];
    }

    private static function normalizePublicDate(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * @param list<int> $viewerUnitIds
     * @param list<string>|null $adminStatusFilter
     * @return array<string, mixed>|null
     */
    public static function buildForTenant(
        UnitRepository $unitRepository,
        int $tenantId,
        ?int $viewerUserId = null,
        ?bool $canBypassMasks = null,
        ?OrgVisibilityCapabilities $caps = null,
        ?array $adminStatusFilter = null,
        ?UnitCodeService $unitCodeService = null,
        int $maxMembersPerUnit = 40
    ): ?array {
        if ($viewerUserId === null) {
            $viewerUserId = (int) Session::get('user_id');
        }
        $caps ??= OrgVisibilityCapabilities::fromGate();
        if ($canBypassMasks === null) {
            $canBypassMasks = $caps->bypassAll
                || $caps->viewHiddenUnits
                || Gate::getInstance()->allows('organization.orbat.manage');
        }

        $tree = $unitRepository->getTree($tenantId);
        if ($tree === []) {
            return null;
        }
        $flat = UnitRepository::flattenTree($tree);
        // Tout ce qui suit l’arbre est de l’enrichissement : une colonne absente ou une donnée inattendue
        // dans une table annexe ne doit jamais faire tomber l’ORBAT entier (500 sur la page et l’API).
        $unitCodes = self::attempt('codes unités', static fn (): array => (array) (($unitCodeService ?? new UnitCodeService())->sync($tenantId)['units'] ?? []), []);
        $memberCounts = self::attempt('effectifs', static fn (): array => $unitRepository->countDistinctMembersByUnitForTenant($tenantId), []);
        $commanderLabels = self::attempt('chefs d’unité', static fn (): array => $unitRepository->commanderLabelByUnitForTenant($tenantId, $flat), []);
        $rosterByUnit = self::attempt('membres', static fn (): array => $unitRepository->rosterMembersByUnitForTenant($tenantId, $maxMembersPerUnit), []);
        $rosterUserIds = self::collectUserIdsFromRoster($rosterByUnit);
        $visibilityByUser = self::personnelVisibilityOrStrictest(
            static fn (): array => $unitRepository->personnelVisibilityByUserIdsForTenant($tenantId, $rosterUserIds),
            $rosterUserIds
        );
        $rosterByUnit = self::enrichAndFilterRosterMembers($rosterByUnit, $visibilityByUser, $caps, $flat);
        $allUserIds = [];
        foreach ($rosterByUnit as $rows) {
            foreach ($rows as $mem) {
                $mid = (int) ($mem['user_id'] ?? 0);
                if ($mid > 0) {
                    $allUserIds[$mid] = true;
                }
            }
        }
        $readinessByUser = self::attempt('disponibilité opérationnelle', static fn (): array => $unitRepository->readinessByUsersForTenant($tenantId, array_map('intval', array_keys($allUserIds))), []);
        foreach ($rosterByUnit as $unitId => $rows) {
            $enrichedRows = [];
            foreach ($rows as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                $entry = $row;
                if ($uid > 0 && isset($readinessByUser[$uid])) {
                    $entry['readiness'] = (int) ($readinessByUser[$uid]['readiness'] ?? 0);
                    $entry['readinessComponents'] = is_array($readinessByUser[$uid]['components'] ?? null)
                        ? $readinessByUser[$uid]['components']
                        : [];
                }
                $enrichedRows[] = $entry;
            }
            $rosterByUnit[(int) $unitId] = $enrichedRows;
        }

        // Recalculer les effectifs visibles après filtrage confidentialité
        $visibleCounts = [];
        foreach ($rosterByUnit as $unitId => $rows) {
            $visibleCounts[(int) $unitId] = count($rows);
        }

        $viewerUnitIds = $viewerUserId > 0
            ? self::attempt('unités du lecteur', static fn (): array => $unitRepository->unitIdsForUser($tenantId, (int) $viewerUserId), [])
            : [];

        $root = null;
        if (count($tree) === 1) {
            $root = self::normalizeNode($tree[0], $memberCounts, $commanderLabels, $rosterByUnit, $unitCodes);
        } else {
            $children = [];
            foreach ($tree as $r) {
                $children[] = self::normalizeNode($r, $memberCounts, $commanderLabels, $rosterByUnit, $unitCodes);
            }
            $root = [
                'id' => 'command',
                'unitId' => 0,
                'label' => 'Command',
                'role' => 'Structure organique',
                'type' => 'command',
                'structType' => 'command',
                'maskMode' => OrbatMaskMode::NONE,
                'visibilityLevel' => VisibilityLevel::NORMAL,
                'visibilityPropagate' => true,
                'strengthDisplayMode' => 'visible_only',
                'adminStatus' => UnitAdminStatus::ACTIVE,
                'adminStatusNote' => '',
                'adminStatusLabel' => UnitAdminStatus::badgeLabel(UnitAdminStatus::ACTIVE),
                'status' => 'active',
                'strength' => 0,
                'visibleStrength' => 0,
                'leader' => '—',
                'mission' => 'Direction des unités et coordination.',
                'orbatDetails' => '',
                'chartIconUrl' => null,
                'chartImageUrl' => null,
                'commanderUserId' => 0,
                'members' => [],
                'children' => $children,
            ];
        }

        // Appliquer effectifs visibles
        $root = self::applyVisibleStrengthCounts($root, $visibleCounts);

        $root = self::applyViewerPolicies($root, $viewerUnitIds, $canBypassMasks);
        if ($root !== null) {
            $root = OrgVisibilityService::applyEffectiveVisibility($root, $caps);
        }
        if ($root !== null && $adminStatusFilter !== null) {
            $root = self::filterByAdminStatus($root, $adminStatusFilter, $canBypassMasks);
        } elseif ($root !== null && !$canBypassMasks) {
            // Par défaut : masquer archivés (et optionnellement inactifs) pour lecteurs non autorisés
            $root = self::filterByAdminStatus($root, UnitAdminStatus::DEFAULT_OPERATIONAL, false);
        }
        if ($root !== null) {
            [$root] = self::annotateReadinessAggregation($root);
            $root = self::attachBilletManning($root, $tenantId);
            $personIds = self::collectPersonIds($root);
            $root = self::redactPeopleForViewer(
                $root,
                $caps,
                self::personnelVisibilityOrStrictest(
                    static fn (): array => $unitRepository->personnelVisibilityByUserIdsForTenant($tenantId, $personIds),
                    $personIds
                )
            );
            $root = self::attachGrades($root, self::attempt('grades', static fn (): array => $unitRepository->gradeShortNamesForUsers($tenantId, $personIds), []));
            // Trigrammes : mêmes garde-fous que les grades (jamais sur une unité aux noms masqués ni un membre anonymisé).
            $root = self::attachGrades($root, self::attempt('trigrammes', static fn (): array => \App\Services\Personnel\MemberNameSettings::forTenant($tenantId)['trigrams_enabled']
                ? (new \App\Services\Personnel\MemberNameService())->trigramsFor($tenantId, $personIds)
                : [], []), 'trigram');
            // Noms d’unités et postes générés par Athena dans la langue du lecteur (personnalisés par la communauté).
            $root = \App\Services\I18n\GeneratedLabels::localizeOrbat($root, $tenantId, array_column($flat, 'slug', 'id'));
        }

        return $root;
    }

    /**
     * Exécute une étape d’enrichissement ; en cas d’erreur, consigne et renvoie la valeur de repli.
     *
     * @template T
     * @param callable(): T $step
     * @param T $fallback
     * @return T
     */
    private static function attempt(string $label, callable $step, mixed $fallback): mixed
    {
        try {
            return $step();
        } catch (\Throwable $e) {
            error_log('[orbat] ' . $label . ' indisponible : ' . $e->getMessage());

            return $fallback;
        }
    }

    /**
     * Lecture des niveaux de confidentialité des personnes. Contrairement aux enrichissements, une erreur
     * ne doit pas ouvrir les données : chaque personne est alors traitée au niveau le plus strict (masquée),
     * les droits du lecteur décidant ensuite de ce qu’il peut voir.
     *
     * @param callable(): array<int, array<string, mixed>> $read
     * @param list<int> $userIds
     * @return array<int, array<string, mixed>>
     */
    private static function personnelVisibilityOrStrictest(callable $read, array $userIds): array
    {
        try {
            return $read();
        } catch (\Throwable $e) {
            error_log('[orbat] confidentialité indisponible, personnes masquées par défaut : ' . $e->getMessage());
            $strict = [];
            foreach ($userIds as $uid) {
                $uid = (int) $uid;
                if ($uid > 0) {
                    $strict[$uid] = [
                        'visibility_level' => VisibilityLevel::HIDDEN,
                        'assignment_visibility' => VisibilityLevel::HIDDEN,
                        'anonymized_label' => '',
                    ];
                }
            }

            return $strict;
        }
    }

    /**
     * Identifiants des personnes citées dans l’arbre (membres, titulaires de postes, chef et adjoint).
     *
     * @param array<string, mixed> $node
     * @return list<int>
     */
    private static function collectPersonIds(array $node): array
    {
        $ids = [];
        $walk = static function (array $n) use (&$walk, &$ids): void {
            if (empty($n['viewerNamesRedacted']) && empty($n['isOrbatPlaceholder'])) {
                $ids[] = (int) ($n['commanderUserId'] ?? 0);
                foreach (['commander', 'deputy'] as $k) {
                    if (is_array($n[$k] ?? null)) {
                        $ids[] = (int) ($n[$k]['userId'] ?? 0);
                    }
                }
                foreach (is_array($n['members'] ?? null) ? $n['members'] : [] as $m) {
                    $ids[] = (int) (is_array($m) ? ($m['user_id'] ?? 0) : 0);
                }
                foreach (is_array($n['billets'] ?? null) ? $n['billets'] : [] as $b) {
                    foreach (is_array($b['holders'] ?? null) ? $b['holders'] : [] as $h) {
                        $ids[] = (int) (is_array($h) ? ($h['user_id'] ?? 0) : 0);
                    }
                }
            }
            foreach (is_array($n['children'] ?? null) ? $n['children'] : [] as $c) {
                if (is_array($c)) {
                    $walk($c);
                }
            }
        };
        $walk($node);

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Ajoute l’abréviation du grade (ou le trigramme, $field = 'trigram') aux cadres et aux membres
     * (jamais sur une unité aux noms masqués ; un membre anonymisé n’a plus d’identifiant, rien ne s’y attache).
     *
     * @param array<string, mixed> $node
     * @param array<int, string> $grades
     * @return array<string, mixed>
     */
    private static function attachGrades(array $node, array $grades, string $field = 'grade'): array
    {
        if ($grades !== [] && empty($node['viewerNamesRedacted']) && empty($node['isOrbatPlaceholder'])) {
            $node['commander' . ucfirst($field)] = $grades[(int) ($node['commanderUserId'] ?? 0)] ?? '';
            foreach (['commander', 'deputy'] as $k) {
                if (is_array($node[$k] ?? null)) {
                    $node[$k][$field] = $grades[(int) ($node[$k]['userId'] ?? 0)] ?? '';
                }
            }
            if (is_array($node['members'] ?? null)) {
                foreach ($node['members'] as $i => $m) {
                    if (is_array($m)) {
                        $node['members'][$i][$field] = $grades[(int) ($m['user_id'] ?? 0)] ?? '';
                    }
                }
            }
            if (is_array($node['billets'] ?? null)) {
                foreach ($node['billets'] as $i => $b) {
                    foreach (is_array($b['holders'] ?? null) ? $b['holders'] : [] as $j => $h) {
                        $node['billets'][$i]['holders'][$j][$field] = $grades[(int) ($h['user_id'] ?? 0)] ?? '';
                    }
                }
            }
        }
        if (is_array($node['children'] ?? null)) {
            foreach ($node['children'] as $i => $c) {
                if (is_array($c)) {
                    $node['children'][$i] = self::attachGrades($c, $grades, $field);
                }
            }
        }

        return $node;
    }

    /**
     * Attache effectif théorique / pourvu / vacant issus des postes ORBAT.
     *
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function attachBilletManning(array $node, int $tenantId): array
    {
        static $manningMap = null;
        static $manningTenant = 0;
        if ($manningMap === null || $manningTenant !== $tenantId) {
            $manningTenant = $tenantId;
            $manningMap = [];
            try {
                $repo = new \App\Repositories\OrbatBilletRepository();
                if ($repo->schemaReady()) {
                    $manningMap = $repo->manningByUnitForTenant($tenantId);
                }
            } catch (\Throwable) {
                $manningMap = [];
            }
        }
        $uid = (int) ($node['unitId'] ?? 0);
        if ($uid > 0 && isset($manningMap[$uid])) {
            $m = $manningMap[$uid];
            $node['billetAuthorized'] = (int) ($m['authorized'] ?? 0);
            $node['billetFilled'] = (int) ($m['filled'] ?? 0);
            $node['billetVacant'] = (int) ($m['vacant'] ?? 0);
            $node['billetManningLabel'] = sprintf(
                '%d affectés — %d postes — %d vacants',
                (int) ($m['filled'] ?? 0),
                (int) ($m['authorized'] ?? 0),
                (int) ($m['vacant'] ?? 0)
            );
            // Liste des postes (y compris vacants) pour la fiche unité
            $node['billets'] = array_map(static function (array $b): array {
                $occLabels = [
                    'primary' => 'Titulaire',
                    'acting' => 'Intérim',
                    'deputy' => 'Adjoint',
                    'alternate' => 'Suppléant',
                ];
                $holdersOut = [];
                foreach ($b['holders'] ?? [] as $h) {
                    if (!is_array($h)) {
                        continue;
                    }
                    $occ = (string) ($h['occupancy_type'] ?? 'primary');
                    $holdersOut[] = [
                        'id' => (int) ($h['id'] ?? 0),
                        'user_id' => (int) ($h['user_id'] ?? 0),
                        'label' => (string) ($h['label'] ?? ''),
                        'occupancy_type' => $occ,
                        'occupancy_label' => $occLabels[$occ] ?? $occ,
                        'starts_at' => (string) ($h['starts_at'] ?? ''),
                        'ends_at' => $h['ends_at'] ?? null,
                    ];
                }
                $vacant = (int) ($b['vacant'] ?? 0);
                $filled = (int) ($b['filled'] ?? 0);
                $seatStatus = $vacant > 0 && $filled === 0
                    ? 'vacant'
                    : ($vacant > 0 ? 'partial' : 'filled');

                return [
                    'id' => (int) ($b['id'] ?? 0),
                    'code' => (string) ($b['code'] ?? ''),
                    'title' => (string) ($b['title'] ?? ''),
                    'org_callsign' => (string) ($b['org_callsign'] ?? ''),
                    'function_label' => (string) ($b['function_label'] ?? ''),
                    'authorized' => (int) ($b['authorized'] ?? 1),
                    'filled' => $filled,
                    'vacant' => $vacant,
                    'seat_status' => $seatStatus,
                    'is_key_post' => !empty($b['is_key_post']),
                    'is_critical' => !empty($b['is_critical']),
                    'key_post_kind' => (string) ($b['key_post_kind'] ?? ''),
                    'holders' => $holdersOut,
                ];
            }, is_array($m['billets'] ?? null) ? $m['billets'] : []);

            $derivedCommand = [];
            $capacitySignals = [];
            $keyDefined = false;
            $criticalVacant = 0;
            foreach ($node['billets'] as $b) {
                $isKey = !empty($b['is_key_post']) || !empty($b['is_critical']);
                if ($isKey) {
                    $keyDefined = true;
                }
                if ($isKey && (int) ($b['vacant'] ?? 0) > 0) {
                    ++$criticalVacant;
                }
                foreach ($b['holders'] as $h) {
                    if (!$isKey) {
                        continue;
                    }
                    if (!in_array((string) ($h['occupancy_type'] ?? ''), ['primary', 'acting'], true)) {
                        continue;
                    }
                    $derivedCommand[] = [
                        'billet_id' => (int) ($b['id'] ?? 0),
                        'title' => (string) ($b['title'] ?? ''),
                        'kind' => (string) (($b['key_post_kind'] ?? '') !== ''
                            ? $b['key_post_kind']
                            : (!empty($b['is_critical']) ? 'critical' : 'key')),
                        'user_id' => (int) ($h['user_id'] ?? 0),
                        'label' => (string) ($h['label'] ?? ''),
                        'occupancy_type' => (string) ($h['occupancy_type'] ?? 'primary'),
                        'occupancy_label' => (string) ($h['occupancy_label'] ?? ''),
                    ];
                }
            }
            if ((int) ($m['vacant'] ?? 0) > 0) {
                $capacitySignals[] = [
                    'code' => 'vacant_billets',
                    'severity' => 'medium',
                    'message' => (int) $m['vacant'] . ' poste(s) vacant(s) sur ' . (int) $m['authorized'],
                ];
            }
            if ($criticalVacant > 0) {
                $capacitySignals[] = [
                    'code' => 'critical_billet_vacant',
                    'severity' => 'high',
                    'message' => $criticalVacant . ' poste(s) clé/critique(s) vacant(s)',
                ];
            }
            if ($keyDefined && $derivedCommand === []) {
                $capacitySignals[] = [
                    'code' => 'no_derived_command',
                    'severity' => 'high',
                    'message' => 'Aucun responsable dérivé des postes clés actuellement pourvus',
                ];
            }
            $node['derivedCommand'] = $derivedCommand;
            $node['capacitySignals'] = $capacitySignals;

            $cmdNode = null;
            $deputyNode = null;
            $keyVacantKinds = [];
            foreach ($node['billets'] as $b) {
                $kind = strtolower((string) ($b['key_post_kind'] ?? ''));
                if ($kind === '' && !empty($b['is_key_post'])) {
                    $kind = 'key';
                }
                if ((int) ($b['vacant'] ?? 0) > 0 && in_array($kind, ['commander', 'deputy'], true)) {
                    $keyVacantKinds[$kind] = true;
                }
            }
            foreach ($derivedCommand as $dc) {
                $kind = strtolower((string) ($dc['kind'] ?? ''));
                $entry = [
                    'userId' => (int) ($dc['user_id'] ?? 0),
                    'label' => (string) ($dc['label'] ?? ''),
                    'billetId' => (int) ($dc['billet_id'] ?? 0),
                    'title' => (string) ($dc['title'] ?? ''),
                    'occupancyType' => (string) ($dc['occupancy_type'] ?? ''),
                ];
                if ($kind === 'commander' && $cmdNode === null) {
                    $cmdNode = $entry;
                }
                if ($kind === 'deputy' && $deputyNode === null) {
                    $deputyNode = $entry;
                }
            }
            $node['commander'] = $cmdNode;
            $node['deputy'] = $deputyNode;
            $node['keyPostsHealth'] = [
                'commanderVacant' => !empty($keyVacantKinds['commander']),
                'deputyVacant' => !empty($keyVacantKinds['deputy']),
                'underStrength' => (int) ($m['filled'] ?? 0) < (int) ($m['authorized'] ?? 0),
                'authorized' => (int) ($m['authorized'] ?? 0),
                'filled' => (int) ($m['filled'] ?? 0),
            ];
            if ($derivedCommand !== [] && trim((string) ($node['leader'] ?? '')) === '') {
                $node['leader'] = (string) ($derivedCommand[0]['label'] ?? '');
                $node['leaderFromBillet'] = true;
            }
            if ($cmdNode !== null && trim((string) ($cmdNode['label'] ?? '')) !== '') {
                $node['leader'] = (string) $cmdNode['label'];
            }

            // Remplacer le strength « membres » par le pourvu postes si des billets existent
            if ((int) ($m['authorized'] ?? 0) > 0) {
                $node['strengthTheoretical'] = (int) ($m['authorized'] ?? 0);
                $node['strengthFilled'] = (int) ($m['filled'] ?? 0);
                $node['strengthVacant'] = (int) ($m['vacant'] ?? 0);
            }
        }
        $children = [];
        foreach ($node['children'] ?? [] as $ch) {
            if (is_array($ch)) {
                $children[] = self::attachBilletManning($ch, $tenantId);
            }
        }
        $node['children'] = $children;

        return $node;
    }

    /**
     * @param array<int, list<array<string, mixed>>> $rosterByUnit
     * @return list<int>
     */
    private static function collectUserIdsFromRoster(array $rosterByUnit): array
    {
        $ids = [];
        foreach ($rosterByUnit as $rows) {
            foreach ($rows as $mem) {
                $mid = (int) ($mem['user_id'] ?? 0);
                if ($mid > 0) {
                    $ids[$mid] = true;
                }
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * @param array<int, list<array<string, mixed>>> $rosterByUnit
     * @param array<int, array<string, mixed>> $visibilityByUser
     * @param array<int, array<string, mixed>> $flatUnits
     * @return array<int, list<array<string, mixed>>>
     */
    private static function enrichAndFilterRosterMembers(
        array $rosterByUnit,
        array $visibilityByUser,
        OrgVisibilityCapabilities $caps,
        array $flatUnits
    ): array {
        $unitVis = [];
        foreach ($flatUnits as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            if (array_key_exists('visibility_level', $u)) {
                $unitVis[$id] = VisibilityLevel::normalize((string) ($u['visibility_level'] ?? ''));
            } else {
                $unitVis[$id] = VisibilityLevel::fromOrbatMaskMode((string) ($u['orbat_mask_mode'] ?? ''));
            }
        }

        $out = [];
        foreach ($rosterByUnit as $unitId => $rows) {
            $uVis = $unitVis[(int) $unitId] ?? VisibilityLevel::NORMAL;
            $list = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $uid = (int) ($row['user_id'] ?? 0);
                $meta = $visibilityByUser[$uid] ?? [];
                $row['visibility_level'] = VisibilityLevel::normalize((string) ($meta['visibility_level'] ?? VisibilityLevel::NORMAL));
                $row['assignment_visibility'] = VisibilityLevel::normalize((string) ($meta['assignment_visibility'] ?? VisibilityLevel::NORMAL));
                $row['anonymized_label'] = trim((string) ($meta['anonymized_label'] ?? ''));
                $filtered = OrgVisibilityService::filterPersonnelRow($row, $caps, $uVis);
                if ($filtered !== null) {
                    $list[] = $filtered;
                }
            }
            $out[(int) $unitId] = $list;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, int> $visibleCounts
     * @return array<string, mixed>
     */
    private static function applyVisibleStrengthCounts(array $node, array $visibleCounts): array
    {
        $uid = (int) ($node['unitId'] ?? 0);
        if ($uid > 0 && isset($visibleCounts[$uid])) {
            $node['visibleStrength'] = $visibleCounts[$uid];
            $mode = strtolower(trim((string) ($node['strengthDisplayMode'] ?? 'visible_only')));
            if ($mode === 'visible_only') {
                $node['strength'] = $visibleCounts[$uid];
            } elseif ($mode === 'none' || $mode === 'hidden') {
                $node['strength'] = 0;
                $node['strengthHidden'] = true;
            } elseif ($mode === 'generic') {
                $node['strengthLabel'] = $visibleCounts[$uid] > 0
                    ? ConfidentialDisplay::label(ConfidentialDisplay::STRENGTH_RESTRICTED)
                    : '—';
            }
        }
        $children = [];
        foreach ($node['children'] ?? [] as $ch) {
            if (is_array($ch)) {
                $children[] = self::applyVisibleStrengthCounts($ch, $visibleCounts);
            }
        }
        $node['children'] = $children;

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $allowed
     * @return array<string, mixed>|null
     */
    private static function filterByAdminStatus(array $node, array $allowed, bool $keepForStaff): ?array
    {
        $uid = (int) ($node['unitId'] ?? 0);
        $status = UnitAdminStatus::normalize((string) ($node['adminStatus'] ?? UnitAdminStatus::ACTIVE));
        if ($uid > 0 && !OrgVisibilityService::shouldShowUnitByAdminStatus($status, $allowed)) {
            if ($keepForStaff) {
                $node['adminStatusFiltered'] = true;
            } else {
                return null;
            }
        }
        $children = [];
        foreach ($node['children'] ?? [] as $ch) {
            if (!is_array($ch)) {
                continue;
            }
            $applied = self::filterByAdminStatus($ch, $allowed, $keepForStaff);
            if ($applied !== null) {
                $children[] = $applied;
            }
        }
        $node['children'] = $children;

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     * @return array{0: array<string, mixed>, 1: int, 2: int}
     */
    private static function annotateReadinessAggregation(array $node): array
    {
        $children = [];
        $subtreeScoreTotal = 0;
        $subtreeMemberCount = 0;
        foreach ($node['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            [$annotatedChild, $childReadiness, $childCount] = self::annotateReadinessAggregation($child);
            $children[] = $annotatedChild;
            $subtreeScoreTotal += $childReadiness;
            $subtreeMemberCount += $childCount;
        }
        $node['children'] = $children;

        $localScoreTotal = 0;
        $localCount = 0;
        foreach ($node['members'] ?? [] as $member) {
            if (!is_array($member)) {
                continue;
            }
            $score = (int) ($member['readiness'] ?? 0);
            if ($score <= 0) {
                continue;
            }
            $localScoreTotal += $score;
            ++$localCount;
        }

        $totalScore = $localScoreTotal + $subtreeScoreTotal;
        $totalCount = $localCount + $subtreeMemberCount;
        $node['localReadinessScore'] = $localCount > 0 ? (int) round($localScoreTotal / $localCount) : null;
        $node['readinessScore'] = $totalCount > 0 ? (int) round($totalScore / $totalCount) : null;
        $node['readinessPopulation'] = $totalCount;
        $node['readinessState'] = self::readinessStateFromScore($node['readinessScore']);
        // Ne plus écraser le statut administratif par la readiness : les deux coexistent.
        if (!isset($node['adminStatus'])) {
            $node['adminStatus'] = UnitAdminStatus::ACTIVE;
        }
        // Conservé pour compat UI legacy : pastille readiness si admin = actif
        if (($node['adminStatus'] ?? UnitAdminStatus::ACTIVE) === UnitAdminStatus::ACTIVE && $node['readinessState'] !== null) {
            $node['operationalDot'] = $node['readinessState'];
        } else {
            $node['operationalDot'] = match (UnitAdminStatus::normalize((string) ($node['adminStatus'] ?? ''))) {
                UnitAdminStatus::PARTIALLY_ACTIVE => 'partial',
                UnitAdminStatus::INACTIVE, UnitAdminStatus::ARCHIVED => 'inactive',
                default => 'active',
            };
        }
        $node['status'] = $node['operationalDot'];

        return [$node, $totalScore, $totalCount];
    }

    private static function readinessStateFromScore(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }
        if ($score >= 70) {
            return 'active';
        }
        if ($score >= 40) {
            return 'partial';
        }

        return 'inactive';
    }

    /**
     * @param array<string, mixed> $node
     * @param list<int> $viewerUnitIds
     * @return array<string, mixed>|null
     */
    private static function applyViewerPolicies(
        array $node,
        array $viewerUnitIds,
        bool $canBypassMasks
    ): ?array {
        if ($canBypassMasks) {
            return self::annotateStaffMaskHints($node);
        }

        $uid = (int) ($node['unitId'] ?? 0);
        $mask = OrbatMaskMode::normalize((string) ($node['maskMode'] ?? OrbatMaskMode::NONE));
        $subtreeIds = self::collectSubtreeUnitIds($node);
        $viewerInSubtree = $uid < 1 ? true : self::intersects($viewerUnitIds, $subtreeIds);

        if ($uid > 0 && $mask === OrbatMaskMode::HIDDEN_ALL && !$viewerInSubtree) {
            return null;
        }

        $childrenOut = [];
        foreach ($node['children'] ?? [] as $ch) {
            if (!is_array($ch)) {
                continue;
            }
            $applied = self::applyViewerPolicies($ch, $viewerUnitIds, false);
            if ($applied !== null) {
                $childrenOut[] = $applied;
            } elseif ((int) ($ch['unitId'] ?? 0) > 0) {
                $childrenOut[] = self::hiddenBranchPlaceholder($ch);
            }
        }
        $node['children'] = $childrenOut;

        if ($uid < 1) {
            return $node;
        }

        $needAnon = self::shouldAnonymizeMembers($mask, $viewerUnitIds, $subtreeIds);

        if ($needAnon) {
            $node['members'] = self::anonymizeMemberRows($node['members'] ?? []);
            $node['leader'] = self::anonymizeLeaderLabel((string) ($node['leader'] ?? '—'));
            $node['commanderUserId'] = 0;
            $node['viewerNamesRedacted'] = true;
            $node['namesAbbreviated'] = true;
            $node['confidential'] = ConfidentialDisplay::payload(ConfidentialDisplay::NAMES_ABBREVIATED);
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    /**
     * Carte synthétique lorsqu’une branche est entièrement masquée pour le lecteur (sans révéler le nom de l’unité).
     *
     * @param array<string, mixed> $removedSubtree
     *
     * @return array<string, mixed>
     */
    private static function hiddenBranchPlaceholder(array $removedSubtree): array
    {
        $subCount = count(self::collectSubtreeUnitIds($removedSubtree));
        $scopeNote = $subCount > 1
            ? sprintf('Cette branche regroupe %d unités qui ne sont pas affichées avec votre profil actuel.', $subCount)
            : 'Le détail de cette partie de l’organigramme n’est pas affiché.';

        $stableKey = (string) ($removedSubtree['id'] ?? '') . '|' . (string) (int) ($removedSubtree['unitId'] ?? 0);

        return [
            'id' => 'masked-' . substr(hash('sha256', 'orbat-masked|' . $stableKey), 0, 14),
            'unitId' => 0,
            'label' => ConfidentialDisplay::label(ConfidentialDisplay::UNIT_HIDDEN),
            'role' => 'Selon votre périmètre',
            'type' => 'command',
            'structType' => 'placeholder',
            'maskMode' => OrbatMaskMode::NONE,
            'status' => 'inactive',
            'strength' => 0,
            'leader' => '—',
            'mission' => ConfidentialDisplay::hint(ConfidentialDisplay::UNIT_HIDDEN),
            'orbatDetails' => $scopeNote,
            'chartIconUrl' => null,
            'chartImageUrl' => null,
            'commanderUserId' => 0,
            'members' => [],
            'children' => [],
            'isOrbatPlaceholder' => true,
            'placeholderReason' => 'branch_hidden',
            'confidential' => ConfidentialDisplay::payload(ConfidentialDisplay::UNIT_HIDDEN),
        ];
    }

    private static function staffMaskBadgeCaption(string $mask): string
    {
        return match ($mask) {
            OrbatMaskMode::HIDDEN_ALL => 'Masqué',
            OrbatMaskMode::SCOPE_SECTION => 'Section',
            OrbatMaskMode::SCOPE_TEAM => 'Équipe',
            OrbatMaskMode::SCOPE_ROLE => 'Rôles',
            OrbatMaskMode::ANONYMIZE => 'Noms abrégés',
            default => 'Confidentiel',
        };
    }

    private static function annotateStaffMaskHints(array $node): array
    {
        $mask = OrbatMaskMode::normalize((string) ($node['maskMode'] ?? OrbatMaskMode::NONE));
        $node['staffMaskActive'] = $mask !== OrbatMaskMode::NONE;
        $node['maskHintLabel'] = $mask !== OrbatMaskMode::NONE ? self::staffMaskBadgeCaption($mask) : '';
        $staff = $mask !== OrbatMaskMode::NONE
            ? ConfidentialDisplay::staffPayload(VisibilityLevel::fromOrbatMaskMode($mask), 'unit')
            : null;
        if ($staff !== null) {
            $staff['label'] = $node['maskHintLabel'];
            $node['confidential'] = $staff;
        }

        $children = [];
        foreach ($node['children'] ?? [] as $ch) {
            if (is_array($ch)) {
                $children[] = self::annotateStaffMaskHints($ch);
            }
        }
        $node['children'] = $children;

        return $node;
    }

    /**
     * @param list<int> $viewerUnitIds
     * @param list<int> $subtreeIds
     */
    private static function shouldAnonymizeMembers(
        string $mask,
        array $viewerUnitIds,
        array $subtreeIds
    ): bool {
        if ($mask === OrbatMaskMode::NONE || $mask === OrbatMaskMode::HIDDEN_ALL) {
            return false;
        }

        if (self::intersects($viewerUnitIds, $subtreeIds)) {
            return false;
        }

        if ($mask === OrbatMaskMode::ANONYMIZE) {
            return true;
        }

        if ($mask === OrbatMaskMode::SCOPE_ROLE) {
            return true;
        }

        if ($mask === OrbatMaskMode::SCOPE_SECTION || $mask === OrbatMaskMode::SCOPE_TEAM) {
            return true;
        }

        return false;
    }

    /**
     * @param list<int> $a
     * @param list<int> $b
     */
    private static function intersects(array $a, array $b): bool
    {
        if ($a === [] || $b === []) {
            return false;
        }
        $set = array_fill_keys($a, true);
        foreach ($b as $id) {
            if (isset($set[$id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $node
     * @return list<int>
     */
    private static function collectSubtreeUnitIds(array $node): array
    {
        $ids = [];
        $uid = (int) ($node['unitId'] ?? 0);
        if ($uid > 0) {
            $ids[] = $uid;
        }
        foreach ($node['children'] ?? [] as $ch) {
            if (is_array($ch)) {
                $ids = array_merge($ids, self::collectSubtreeUnitIds($ch));
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Retire les identités ajoutées APRÈS le filtrage de confidentialité (postes, chef, adjoint) :
     * unité aux noms masqués, ou personne anonymisée / masquée pour ce lecteur.
     *
     * @param array<string, mixed> $node
     * @param array<int, array<string, mixed>> $visByUser
     * @return array<string, mixed>
     */
    public static function redactPeopleForViewer(array $node, OrgVisibilityCapabilities $caps, array $visByUser): array
    {
        $children = [];
        foreach ($node['children'] ?? [] as $ch) {
            if (is_array($ch)) {
                $children[] = self::redactPeopleForViewer($ch, $caps, $visByUser);
            }
        }
        $node['children'] = $children;
        if (!empty($node['isOrbatPlaceholder'])) {
            return $node;
        }

        $unitRedacted = !empty($node['viewerNamesRedacted']);
        $abbrev = !empty($node['namesAbbreviated']);
        $person = static function (int $uid, string $label) use ($unitRedacted, $abbrev, $caps, $visByUser): array {
            $meta = $uid > 0 ? ($visByUser[$uid] ?? null) : null;
            $custom = is_array($meta) ? (string) ($meta['anonymized_label'] ?? '') : '';
            if ($unitRedacted) {
                if ($abbrev) {
                    $short = self::anonymizePersonLabel($label);

                    return [0, $short, ConfidentialDisplay::payload(ConfidentialDisplay::NAMES_ABBREVIATED, $short)];
                }
                $l = ConfidentialDisplay::personLabel($custom);

                return [0, $l, ConfidentialDisplay::payload(ConfidentialDisplay::PERSON_ANONYMIZED, $l)];
            }
            if (is_array($meta)) {
                $level = (string) ($meta['visibility_level'] ?? VisibilityLevel::NORMAL);
                if ($caps->shouldHidePersonnelCompletely($level) || $caps->shouldAnonymizePersonnel($level)) {
                    $l = ConfidentialDisplay::personLabel($custom);

                    return [0, $l, ConfidentialDisplay::payload(ConfidentialDisplay::PERSON_ANONYMIZED, $l)];
                }
            }

            return [$uid, $label, null];
        };

        if (is_array($node['billets'] ?? null)) {
            foreach ($node['billets'] as $bi => $b) {
                if (!is_array($b) || !is_array($b['holders'] ?? null)) {
                    continue;
                }
                foreach ($b['holders'] as $hi => $h) {
                    if (!is_array($h)) {
                        continue;
                    }
                    [$id, $label, $conf] = $person((int) ($h['user_id'] ?? 0), (string) ($h['label'] ?? ''));
                    if ($conf !== null) {
                        $node['billets'][$bi]['holders'][$hi]['user_id'] = $id;
                        $node['billets'][$bi]['holders'][$hi]['label'] = $label;
                        $node['billets'][$bi]['holders'][$hi]['confidential'] = $conf;
                    }
                }
            }
        }
        if (is_array($node['derivedCommand'] ?? null)) {
            foreach ($node['derivedCommand'] as $di => $dc) {
                if (!is_array($dc)) {
                    continue;
                }
                [$id, $label, $conf] = $person((int) ($dc['user_id'] ?? 0), (string) ($dc['label'] ?? ''));
                if ($conf !== null) {
                    $node['derivedCommand'][$di]['user_id'] = $id;
                    $node['derivedCommand'][$di]['label'] = $label;
                }
            }
        }
        foreach (['commander', 'deputy'] as $k) {
            if (!is_array($node[$k] ?? null)) {
                continue;
            }
            [$id, $label, $conf] = $person((int) ($node[$k]['userId'] ?? 0), (string) ($node[$k]['label'] ?? ''));
            if ($conf !== null) {
                $node[$k]['userId'] = $id;
                $node[$k]['label'] = $label;
                $node[$k]['confidential'] = $conf;
            }
        }

        if ($unitRedacted) {
            // Le chef issu des postes a pu réécrire « leader » avec un nom réel après le masquage.
            if (!empty($node['isAnonymizedUnit'])) {
                $node['leader'] = '—';
                $node['commander'] = null;
                $node['deputy'] = null;
            } elseif (is_array($node['commander'] ?? null)) {
                $node['leader'] = (string) $node['commander']['label'];
            } elseif (!empty($node['isRestrictedUnit'])) {
                $node['leader'] = ConfidentialDisplay::label(ConfidentialDisplay::IDENTITY_RESTRICTED);
            } elseif (!empty($node['leaderFromBillet'])) {
                $node['leader'] = self::anonymizeLeaderLabel((string) ($node['leader'] ?? '—'));
            }
            $node['commanderUserId'] = 0;
            $leader = trim((string) ($node['leader'] ?? ''));
            if ($leader !== '' && $leader !== '—') {
                $kind = ConfidentialDisplay::kindFromText($leader);
                $node['leaderConfidential'] = is_array($node['commander']['confidential'] ?? null)
                    ? $node['commander']['confidential']
                    : ($kind !== null
                        ? ConfidentialDisplay::payload($kind)
                        : ConfidentialDisplay::payload(ConfidentialDisplay::NAMES_ABBREVIATED, $leader));
            }
        } else {
            $cmdId = (int) ($node['commanderUserId'] ?? 0);
            if ($cmdId > 0) {
                [$id, $label, $conf] = $person($cmdId, (string) ($node['leader'] ?? ''));
                if ($conf !== null) {
                    $node['commanderUserId'] = $id;
                    $node['leader'] = $label;
                    $node['leaderConfidential'] = $conf;
                }
            }
            if (is_array($node['commander'] ?? null) && !empty($node['commander']['confidential'])) {
                $node['leader'] = (string) $node['commander']['label'];
            }
        }

        return $node;
    }

    /**
     * @param list<array{user_id: int, label: string}>|mixed $members
     * @return list<array{user_id: int, label: string}>
     */
    private static function anonymizeMemberRows(mixed $members): array
    {
        if (!is_array($members)) {
            return [];
        }
        $out = [];
        foreach ($members as $m) {
            if (!is_array($m)) {
                continue;
            }
            $label = (string) ($m['label'] ?? '');
            $out[] = [
                'user_id' => 0,
                'label' => self::anonymizePersonLabel($label),
                'anonymized' => true,
                'confidential' => ConfidentialDisplay::payload(
                    ConfidentialDisplay::NAMES_ABBREVIATED,
                    self::anonymizePersonLabel($label)
                ),
            ];
        }

        return $out;
    }

    private static function anonymizeLeaderLabel(string $leader): string
    {
        $t = trim($leader);
        if ($t === '' || $t === '—') {
            return '—';
        }

        return self::anonymizePersonLabel($t);
    }

    private static function anonymizePersonLabel(string $label): string
    {
        $t = trim($label);
        if ($t === '') {
            return 'Membre';
        }
        $parts = preg_split('/\s+/u', $t) ?: [];
        $ini = '';
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $ini .= mb_strtoupper(mb_substr($p, 0, 1));
            }
        }

        return $ini !== '' ? $ini . '.' : 'Membre';
    }
}
