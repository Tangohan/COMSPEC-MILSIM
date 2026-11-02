<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Support\ConfidentialDisplay;
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
            $staff = ConfidentialDisplay::staffPayload($effective, 'unit');
            if ($staff !== null) {
                $node['confidential'] = $staff;
            }
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
        $node['label'] = ConfidentialDisplay::label(ConfidentialDisplay::UNIT_ANONYMIZED);
        $node['role'] = '';
        $node['leader'] = '—';
        $node['mission'] = ConfidentialDisplay::hint(ConfidentialDisplay::UNIT_ANONYMIZED);
        $node['orbatDetails'] = '';
        $node['chartIconUrl'] = null;
        $node['chartImageUrl'] = null;
        $node['commanderUserId'] = 0;
        // Tout ce qui permettrait de reconnaître l’unité (codes, devise, insigne, textes publics).
        foreach (['unitCode', 'adminId', 'motto', 'descriptionLong', 'adminStatusNote', 'publicCustomDateLabel'] as $k) {
            if (array_key_exists($k, $node)) {
                $node[$k] = '';
            }
        }
        foreach (['badgeMediaUrl', 'commander', 'deputy'] as $k) {
            if (array_key_exists($k, $node)) {
                $node[$k] = null;
            }
        }
        $node['members'] = self::anonymizeMemberOccupation($node['members'] ?? []);
        $node['viewerNamesRedacted'] = true;
        $node['isAnonymizedUnit'] = true;
        $node['confidential'] = ConfidentialDisplay::payload(ConfidentialDisplay::UNIT_ANONYMIZED);
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
        $node['leader'] = ConfidentialDisplay::label(ConfidentialDisplay::IDENTITY_RESTRICTED);
        // Sans cet identifiant, aucun portrait ni lien vers la fiche du chef n’est reconstruit en aval.
        $node['commanderUserId'] = 0;
        $node['viewerNamesRedacted'] = true;
        $node['isRestrictedUnit'] = true;
        $node['confidential'] = ConfidentialDisplay::payload(ConfidentialDisplay::UNIT_RESTRICTED);
        $node['strength'] = self::strengthForDisplay($node);
        if (!$caps->viewRestrictedUnits) {
            $node['orbatDetails'] = '';
            $mission = trim((string) ($node['mission'] ?? ''));
            if ($mission !== '' && $mission !== '—') {
                $node['mission'] = ConfidentialDisplay::label(ConfidentialDisplay::UNIT_RESTRICTED);
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
                'label' => ConfidentialDisplay::personLabel((string) ($m['anonymized_label'] ?? '')),
                'anonymized' => true,
                'confidential' => ConfidentialDisplay::payload(
                    ConfidentialDisplay::PERSON_ANONYMIZED,
                    ConfidentialDisplay::personLabel((string) ($m['anonymized_label'] ?? ''))
                ),
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

            return $n > 0 ? ConfidentialDisplay::label(ConfidentialDisplay::STRENGTH_RESTRICTED) : null;
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
                $member = self::redactAssignment($member);
            } else {
                return null;
            }
        }

        if ($caps->shouldAnonymizePersonnel($pVis)) {
            $member['label'] = ConfidentialDisplay::personLabel((string) ($member['anonymized_label'] ?? ''));
            $member['display_name'] = $member['label'];
            $member['callsign'] = null;
            $member['photo'] = null;
            $member['portrait'] = null;
            $member['user_id'] = 0;
            $member['athena_identifier'] = null;
            $member['anonymized'] = true;
            $member['confidential'] = ConfidentialDisplay::payload(ConfidentialDisplay::PERSON_ANONYMIZED, $member['label']);
            unset($member['first_name'], $member['last_name'], $member['character_name'], $member['trigram']);
        }

        if ($caps->shouldRedactAssignment($aVis, $uVis)) {
            $member = self::redactAssignment($member);
        }

        return $member;
    }

    /**
     * Remplace l’affectation d’un membre par le libellé commun « Affectation restreinte ».
     *
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    public static function redactAssignment(array $member): array
    {
        $label = ConfidentialDisplay::label(ConfidentialDisplay::ASSIGNMENT_RESTRICTED);
        $member['assignment_label'] = $label;
        $member['unit_path'] = null;
        $member['unit_name'] = $label;
        $member['assignment_redacted'] = true;

        return $member;
    }

    /**
     * Niveau effectif de chaque unité (ligne brute `units`), avec la même propagation parent → enfants
     * que l’ORBAT : le plus restrictif gagne, sauf dérogation explicite ; un parent masqué s’impose toujours.
     *
     * @param list<array<string, mixed>> $units
     * @return array<int, string> unit_id => niveau
     */
    public static function effectiveUnitLevels(array $units): array
    {
        $byId = [];
        foreach ($units as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id > 0) {
                $byId[$id] = $u;
            }
        }
        $own = static function (array $u): string {
            return array_key_exists('visibility_level', $u)
                ? VisibilityLevel::normalize((string) ($u['visibility_level'] ?? ''))
                : VisibilityLevel::fromOrbatMaskMode((string) ($u['orbat_mask_mode'] ?? ''));
        };
        $memo = [];
        $resolve = static function (int $id, array $seen = []) use (&$resolve, &$memo, $byId, $own): string {
            if (isset($memo[$id])) {
                return $memo[$id];
            }
            $u = $byId[$id] ?? null;
            if ($u === null) {
                return VisibilityLevel::NORMAL;
            }
            $level = $own($u);
            $parentId = (int) ($u['parent_id'] ?? 0);
            if ($parentId > 0 && !isset($seen[$parentId]) && isset($byId[$parentId])) {
                $seen[$id] = true;
                $parentLevel = $resolve($parentId, $seen);
                $propagate = !array_key_exists('visibility_propagate', $u) || (int) ($u['visibility_propagate'] ?? 1) === 1;
                if (VisibilityLevel::severity($parentLevel) > VisibilityLevel::severity($level)
                    && ($propagate || $parentLevel === VisibilityLevel::HIDDEN)) {
                    $level = VisibilityLevel::mostRestrictive($level, $parentLevel);
                }
            }

            return $memo[$id] = $level;
        };
        $out = [];
        foreach (array_keys($byId) as $id) {
            $out[$id] = $resolve($id);
        }

        return $out;
    }

    /**
     * Unité vue par un visiteur sans droit (pages publiques) : null = ne pas l’afficher.
     *
     * @param array<string, mixed> $unit Ligne `units`
     * @return array<string, mixed>|null
     */
    public static function publicUnitRow(array $unit, string $level): ?array
    {
        $level = VisibilityLevel::normalize($level);
        if ($level === VisibilityLevel::HIDDEN) {
            return null;
        }
        if ($level === VisibilityLevel::ANONYMIZED) {
            foreach (['code', 'slug', 'public_blurb', 'motto', 'description_long', 'orbat_details', 'public_custom_date_label', 'type'] as $k) {
                if (array_key_exists($k, $unit)) {
                    $unit[$k] = '';
                }
            }
            foreach (['badge_media_path', 'orbat_icon_path', 'orbat_image_path'] as $k) {
                if (array_key_exists($k, $unit)) {
                    $unit[$k] = null;
                }
            }
            $unit['name'] = ConfidentialDisplay::label(ConfidentialDisplay::UNIT_ANONYMIZED);
            $unit['commander_user_id'] = 0;
            $unit['confidential'] = ConfidentialDisplay::payload(ConfidentialDisplay::UNIT_ANONYMIZED);
        } elseif ($level === VisibilityLevel::RESTRICTED) {
            $unit['commander_user_id'] = 0;
            $unit['confidential'] = ConfidentialDisplay::payload(ConfidentialDisplay::UNIT_RESTRICTED);
        }
        $unit['effective_visibility'] = $level;

        return $unit;
    }

    /**
     * Liste nominative d’une unité pour un lecteur : membres masqués retirés, anonymisés remplacés ;
     * dans une unité anonymisée ou restreinte, aucun nom n’est affiché.
     *
     * @param list<array<string, mixed>> $rows Lignes {user_id, label}
     * @param array<int, array<string, mixed>> $visByUser user_id => {visibility_level, anonymized_label…}
     * @return list<array<string, mixed>>
     */
    public static function rosterForViewer(
        array $rows,
        OrgVisibilityCapabilities $caps,
        string $unitLevel,
        array $visByUser
    ): array {
        $unitLevel = VisibilityLevel::normalize($unitLevel);
        $unitRedacted = !$caps->bypassAll && !$caps->viewRestrictedUnits && !$caps->viewHiddenUnits
            && in_array($unitLevel, [VisibilityLevel::ANONYMIZED, VisibilityLevel::RESTRICTED], true);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $uid = (int) ($row['user_id'] ?? 0);
            $meta = $visByUser[$uid] ?? [];
            $row['visibility_level'] = VisibilityLevel::normalize((string) ($meta['visibility_level'] ?? VisibilityLevel::NORMAL));
            $row['assignment_visibility'] = VisibilityLevel::normalize((string) ($meta['assignment_visibility'] ?? VisibilityLevel::NORMAL));
            $row['anonymized_label'] = trim((string) ($meta['anonymized_label'] ?? ''));
            $filtered = self::filterPersonnelRow($row, $caps, $unitLevel);
            if ($filtered === null) {
                continue;
            }
            if ($unitRedacted && empty($filtered['anonymized'])) {
                $filtered = self::anonymizeMemberOccupation([$filtered])[0];
            }
            $out[] = $filtered;
        }

        return $out;
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
