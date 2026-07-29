<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;
use App\Core\Session;
use App\Repositories\UnitRepository;

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
        array $unitRosterByUnit
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
                $children[] = self::normalizeNode($c, $unitMemberCounts, $unitCommanderLabels, $unitRosterByUnit);
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

        return [
            'id' => 'unit-' . $uid,
            'unitId' => $uid,
            'label' => $u['name'] ?? 'Unité',
            'role' => !empty($u['code']) ? (string) $u['code'] : 'Unité',
            'type' => $displayType,
            'structType' => $structType,
            'maskMode' => $maskMode,
            'status' => 'active',
            'strength' => (int) ($unitMemberCounts[$uid] ?? 0),
            'leader' => $unitCommanderLabels[$uid] ?? '—',
            'mission' => $mission,
            'orbatDetails' => $details,
            'chartIconUrl' => $iconPath !== null && $iconPath !== '' ? $iconPath : null,
            'chartImageUrl' => $imagePath !== null && $imagePath !== '' ? $imagePath : null,
            'commanderUserId' => (int) ($u['commander_user_id'] ?? 0),
            'showOnPublicPage' => !array_key_exists('show_on_public_page', $u)
                || (int) ($u['show_on_public_page'] ?? 0) === 1,
            'publicFoundedOn' => self::normalizePublicDate($u['public_founded_on'] ?? null),
            'publicCustomDate' => self::normalizePublicDate($u['public_custom_date'] ?? null),
            'publicCustomDateLabel' => trim((string) ($u['public_custom_date_label'] ?? '')),
            'members' => $unitRosterByUnit[$uid] ?? [],
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
     * @return array<string, mixed>|null
     */
    public static function buildForTenant(
        UnitRepository $unitRepository,
        int $tenantId,
        ?int $viewerUserId = null,
        ?bool $canBypassMasks = null
    ): ?array {
        if ($viewerUserId === null) {
            $viewerUserId = (int) Session::get('user_id');
        }
        if ($canBypassMasks === null) {
            $gate = Gate::getInstance();
            $canBypassMasks = $gate->allows('admin.organization') || $gate->allows('admin.access')
                || $gate->allows('organization.orbat.manage');
        }

        $tree = $unitRepository->getTree($tenantId);
        if ($tree === []) {
            return null;
        }
        $flat = UnitRepository::flattenTree($tree);
        $memberCounts = $unitRepository->countDistinctMembersByUnitForTenant($tenantId);
        $commanderLabels = $unitRepository->commanderLabelByUnitForTenant($tenantId, $flat);
        $rosterByUnit = $unitRepository->rosterMembersByUnitForTenant($tenantId);
        $allUserIds = [];
        foreach ($rosterByUnit as $rows) {
            foreach ($rows as $mem) {
                $mid = (int) ($mem['user_id'] ?? 0);
                if ($mid > 0) {
                    $allUserIds[$mid] = true;
                }
            }
        }
        $readinessByUser = $unitRepository->readinessByUsersForTenant($tenantId, array_map('intval', array_keys($allUserIds)));
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

        $viewerUnitIds = $viewerUserId > 0
            ? $unitRepository->unitIdsForUser($tenantId, $viewerUserId)
            : [];

        $root = null;
        if (count($tree) === 1) {
            $root = self::normalizeNode($tree[0], $memberCounts, $commanderLabels, $rosterByUnit);
        } else {
            $children = [];
            foreach ($tree as $r) {
                $children[] = self::normalizeNode($r, $memberCounts, $commanderLabels, $rosterByUnit);
            }
            $root = [
                'id' => 'command',
                'unitId' => 0,
                'label' => 'Command',
                'role' => 'Structure organique',
                'type' => 'command',
                'structType' => 'command',
                'maskMode' => OrbatMaskMode::NONE,
                'status' => 'active',
                'strength' => 0,
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

        $root = self::applyViewerPolicies($root, $viewerUnitIds, $canBypassMasks);
        if ($root !== null) {
            [$root] = self::annotateReadinessAggregation($root);
        }

        return $root;
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
        if (($node['status'] ?? 'active') === 'active' && $node['readinessState'] !== null) {
            $node['status'] = $node['readinessState'];
        }
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
            $node['viewerNamesRedacted'] = true;
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
            'label' => 'Structure non affichée',
            'role' => 'Selon votre périmètre',
            'type' => 'command',
            'structType' => 'placeholder',
            'maskMode' => OrbatMaskMode::NONE,
            'status' => 'inactive',
            'strength' => 0,
            'leader' => '—',
            'mission' => 'Cette portion de l’organigramme est réservée aux personnes concernées par l’affectation ou le niveau d’habilitation. Si vous devez la consulter, rapprochez votre encadrement ou le référent organisation.',
            'orbatDetails' => $scopeNote,
            'chartIconUrl' => null,
            'chartImageUrl' => null,
            'commanderUserId' => 0,
            'members' => [],
            'children' => [],
            'isOrbatPlaceholder' => true,
            'placeholderReason' => 'branch_hidden',
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
