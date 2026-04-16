<?php

declare(strict_types=1);

namespace App\Services\Rbac;

/**
 * Référentiel unique : rôles militaires / organisationnels (FR + label_en), hiérarchie catégorie → sous-catégorie.
 * Alimente `roles` (intra) et `personnel_job_roles` via MilitaryRoleCatalogSyncService.
 *
 * Les entrées détaillées sont dans {@see MilitaryOperationalRoleCatalogData}.
 *
 * @phpstan-type CatalogEntry array{
 *   slug: string,
 *   name: string,
 *   label_en: string,
 *   category: string,
 *   subcategory: string,
 *   description: string,
 *   semantic_tier: 'authority'|'function'|'specialty'|'status'|'support'|'liaison',
 *   is_visual_only: int,
 *   display_group: int,
 *   display_weight: int,
 *   display_priority: int,
 *   permission_baseline: 'member'|'officer'|'instructor'|'medic'|'logistics'|'hr'|'rto'|'probation',
 *   mos_code?: string|null,
 *   mos_specialty_title?: string|null
 * }
 */
final class MilitaryOperationalRoleCatalog
{
    /** @return list<CatalogEntry> */
    public static function entries(): array
    {
        return MilitaryOperationalRoleCatalogData::entries();
    }

    /** @return array<string, true> */
    public static function catalogSlugSet(): array
    {
        $out = [];
        foreach (self::entries() as $e) {
            $out[$e['slug']] = true;
        }

        return $out;
    }
}
