<?php

declare(strict_types=1);

namespace App\Services\Rbac;

/**
 * Référentiel unique : emplois militaires / organisationnels (FR + label_en), hiérarchie catégorie → sous-catégorie.
 * Conservé comme documentation de métier. Il n’est plus recopié automatiquement dans les dossiers :
 * les emplois naissent des unités de l’ORBAT.
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
 *   permission_baseline: 'member'|'officer'|'instructor'|'medic'|'logistics'|'hr'|'rto'|'probation'|'all',
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

    /** @return array<string, true> */
    public static function catalogCategoryNameSet(): array
    {
        $out = [];
        foreach (self::entries() as $e) {
            $name = trim((string) ($e['category'] ?? ''));
            if ($name !== '') {
                $out[$name] = true;
            }
        }

        return $out;
    }

    /**
     * Liste dossier : unités / emplois communautaires, plus ceux déjà posés sur la fiche.
     * Le catalogue militaire n’apparaît pas comme menu.
     *
     * @param list<array<string, mixed>> $options
     * @param list<int> $keepRoleIds
     * @return list<array<string, mixed>>
     */
    public static function filterOptionsForMemberDossier(array $options, array $keepRoleIds = []): array
    {
        $keep = [];
        foreach ($keepRoleIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $keep[$id] = true;
            }
        }
        $slugs = self::catalogSlugSet();
        $categories = self::catalogCategoryNameSet();
        $out = [];
        foreach ($options as $opt) {
            if (!is_array($opt)) {
                continue;
            }
            $id = (int) ($opt['id'] ?? 0);
            $slug = trim((string) ($opt['slug'] ?? ''));
            if ($id > 0 && isset($keep[$id])) {
                $out[] = $opt;
                continue;
            }
            if (str_starts_with($slug, 'unit-')) {
                $out[] = $opt;
                continue;
            }
            if ($slug !== '' && isset($slugs[$slug])) {
                continue;
            }
            $label = trim((string) ($opt['label'] ?? $opt['name'] ?? ''));
            $root = $label;
            if (str_contains($label, ' › ')) {
                $root = explode(' › ', $label, 2)[0];
            } elseif (str_contains($label, ' > ')) {
                $root = explode(' > ', $label, 2)[0];
            }
            if ($root !== '' && isset($categories[$root])) {
                continue;
            }
            $out[] = $opt;
        }

        return $out;
    }
}
