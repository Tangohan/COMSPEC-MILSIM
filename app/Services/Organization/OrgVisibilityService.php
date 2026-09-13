<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Support\OrgVisibilityCapabilities;
use App\Support\UnitAdminStatus;
use App\Support\VisibilityLevel;

/**
 * Règles métier de confidentialité et cohérence statut / visibilité.
 */
final class OrgVisibilityService
{
    /**
     * Applique la propagation parent → enfants (le plus restrictif gagne, sauf dérogation explicite).
     *
     * @param array<string, mixed> $node  Nœud ORBAT normalisé (visibilityLevel, visibilityPropagate, children)
     * @return array<string, mixed>|null
     */
    public static function applyEffectiveVisibility(
        array $node,
        OrgVisibilityCapabilities $caps,
        string $inheritedLevel = VisibilityLevel::NORMAL,
        bool $inheritedPropagate = true
    ): ?array {
        $own = VisibilityLevel::normalize((string) ($node['visibilityLevel'] ?? VisibilityLevel::NORMAL));
        $propagate = !array_key_exists('visibilityPropagate', $node)
            || (int) ($node['visibilityPropagate'] ?? 1) === 1;

        $effective = $own;
        if ($inheritedPropagate && VisibilityLevel::severity($inheritedLevel) > VisibilityLevel::severity($own)) {
            // Parent masqué force les enfants sauf dérogation explicite (propagate=0 sur l'enfant)
            if ($propagate || VisibilityLevel::normalize($inheritedLevel) === VisibilityLevel::HIDDEN) {
                $effective = VisibilityLevel::mostRestrictive($own, $inheritedLevel);
            }
        }

        $node['effectiveVisibility'] = $effective;
        $node['inheritedVisibility'] = $inheritedLevel;

        if (!empty($node['isOrbatPlaceholder'])) {
            return $node;
        }

        $isStaffViewer = $caps->bypassAll || $caps->viewHiddenUnits || $caps->viewRestrictedUnits;

        if ($effective === VisibilityLevel::HIDDEN && !$caps->canSeeUnitLevel(VisibilityLevel::HIDDEN)) {
            return null;
        }

        if (!$isStaffViewer) {
            if ($effective === VisibilityLevel::ANONYMIZED) {
                $node = self::anonymizeUnitNode($node);
            } elseif ($effective === VisibilityLevel::RESTRICTED) {
                $node = self::restrictUnitNode($node, $caps);
            }
        } else {
            $node['staffVisibilityActive'] = $effective !== VisibilityLevel::NORMAL;
            $node['visibilityHintLabel'] = $effective !== VisibilityLevel::NORMAL
                ? VisibilityLevel::label($effective)
                : '';
        }

        $childInherited = $effective;
        $childPropagate = $propagate;
        $childrenOut = [];
        foreach ($node['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $applied = self::applyEffectiveVisibility($child, $caps, $childInherited, $childPropagate);
            if ($applied !== null) {
                $childrenOut[] = $applied;
            }
        }
        $node['children'] = $childrenOut;

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    public static function anonymizeUnitNode(array $node): array
    {
        $node['label'] = 'Unité restreinte';
        $node['role'] = 'Structure anonymisée';
        $node['leader'] = '—';
        $node['mission'] = 'Les détails de cette structure sont masqués.';
        $node['orbatDetails'] = '';
        $node['chartIconUrl'] = null;
        $node['chartImageUrl'] = null;
        $node['commanderUserId'] = 0;
        $node['members'] = self::anonymizeMemberOccupation($node['members'] ?? []);
        $node['viewerNamesRedacted'] = true;
        $node['isAnonymizedUnit'] = true;
        $node['strength'] = self::strengthForDisplay($node);

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    public static function restrictUnitNode(array $node, OrgVisibilityCapabilities $caps): array
    {
        $node['members'] = self::anonymizeMemberOccupation($node['members'] ?? []);
        $node['leader'] = 'Restreint';
        $node['viewerNamesRedacted'] = true;
        $node['isRestrictedUnit'] = true;
        $node['strength'] = self::strengthForDisplay($node);
        if (!$caps->viewRestrictedUnits) {
            $node['orbatDetails'] = '';
            $mission = trim((string) ($node['mission'] ?? ''));
            if ($mission !== '' && $mission !== '—') {
                $node['mission'] = 'Informations limitées';
            }
        }

        return $node;
    }

    /**
     * @param list<array<string, mixed>>|mixed $members
     * @return list<array<string, mixed>>
     */
    public static function anonymizeMemberOccupation(mixed $members): array
    {
        if (!is_array($members)) {
            return [];
        }
        $out = [];
        foreach ($members as $m) {
            if (!is_array($m)) {
                continue;
            }
            $out[] = [
                'user_id' => 0,
                'label' => (string) ($m['anonymized_label'] ?? 'Personnel anonymisé'),
                'anonymized' => true,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $node
     */
    public static function strengthForDisplay(array $node): int|string|null
    {
        $mode = strtolower(trim((string) ($node['strengthDisplayMode'] ?? 'visible_only')));
        if ($mode === 'none' || $mode === 'hidden') {
            return null;
        }
        if ($mode === 'generic') {
            $n = (int) ($node['visibleStrength'] ?? $node['strength'] ?? 0);

            return $n > 0 ? 'Effectif restreint' : null;
        }

        return (int) ($node['visibleStrength'] ?? $node['strength'] ?? 0);
    }

    /**
     * Filtre un membre selon sa visibilité et celle de son unité d'affectation.
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>|null null = exclure complètement
     */
    public static function filterPersonnelRow(
        array $member,
        OrgVisibilityCapabilities $caps,
        string $unitVisibility = VisibilityLevel::NORMAL
    ): ?array {
        $pVis = VisibilityLevel::normalize((string) ($member['visibility_level'] ?? VisibilityLevel::NORMAL));
        $aVis = VisibilityLevel::normalize((string) ($member['assignment_visibility'] ?? VisibilityLevel::NORMAL));
        $uVis = VisibilityLevel::normalize($unitVisibility);

        if ($caps->shouldHidePersonnelCompletely($pVis)) {
            return null;
        }

        // Unité masquée : ne pas révéler l'affectation réelle
        if ($uVis === VisibilityLevel::HIDDEN && !$caps->canSeeUnitLevel(VisibilityLevel::HIDDEN)) {
            if ($pVis === VisibilityLevel::NORMAL || $pVis === VisibilityLevel::ANONYMIZED) {
                // Personnel connu mais affectation masquée
                $member['assignment_label'] = 'Restreinte';
                $member['unit_path'] = null;
                $member['unit_name'] = 'Restreinte';
                $member['assignment_redacted'] = true;
            } else {
                return null;
            }
        }

        if ($caps->shouldAnonymizePersonnel($pVis)) {
            $label = trim((string) ($member['anonymized_label'] ?? ''));
            $member['label'] = $label !== '' ? $label : 'Personnel anonymisé';
            $member['display_name'] = $member['label'];
            $member['callsign'] = null;
            $member['photo'] = null;
            $member['portrait'] = null;
            $member['user_id'] = 0;
            $member['athena_identifier'] = null;
            $member['anonymized'] = true;
            unset($member['first_name'], $member['last_name'], $member['character_name']);
        }

        if ($caps->shouldRedactAssignment($aVis, $uVis)) {
            $member['assignment_label'] = 'Restreinte';
            $member['unit_path'] = null;
            $member['unit_name'] = 'Restreinte';
            $member['assignment_redacted'] = true;
        }

        return $member;
    }

    /**
     * @param list<string>|null $allowedStatuses
     */
    public static function shouldShowUnitByAdminStatus(string $status, ?array $allowedStatuses = null): bool
    {
        $status = UnitAdminStatus::normalize($status);
        if ($allowedStatuses === null) {
            $allowedStatuses = UnitAdminStatus::DEFAULT_OPERATIONAL;
        }
        $allowed = array_map([UnitAdminStatus::class, 'normalize'], $allowedStatuses);

        return in_array($status, $allowed, true);
    }

    /**
     * Détecte les incohérences parent/enfant sans les corriger.
     *
     * @param array<string, mixed> $unit
     * @param array<string, mixed>|null $parent
     * @return list<string>
     */
    public static function detectStatusInconsistencies(array $unit, ?array $parent): array
    {
        $warnings = [];
        if ($parent === null) {
            return $warnings;
        }
        $child = UnitAdminStatus::normalize((string) ($unit['admin_status'] ?? UnitAdminStatus::ACTIVE));
        $parentStatus = UnitAdminStatus::normalize((string) ($parent['admin_status'] ?? UnitAdminStatus::ACTIVE));

        if ($parentStatus === UnitAdminStatus::ARCHIVED && $child !== UnitAdminStatus::ARCHIVED) {
            $warnings[] = 'Structure active ou non archivée rattachée à une structure archivée.';
        }
        if ($parentStatus === UnitAdminStatus::INACTIVE && $child === UnitAdminStatus::ACTIVE) {
            $warnings[] = 'Structure active rattachée à une structure inactive.';
        }

        return $warnings;
    }
}
