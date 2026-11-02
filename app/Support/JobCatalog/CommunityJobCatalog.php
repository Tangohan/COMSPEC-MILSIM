<?php

declare(strict_types=1);

namespace App\Support\JobCatalog;

use App\Services\Personnel\JobRoleEnrichmentService;
use App\Support\JobRoleIconLibrary;
use InvalidArgumentException;

/**
 * Catalogues d’emplois créés par une communauté (univers SCP, Warhammer 40k, unité fictive, PMC…), montrés dans la
 * fenêtre « Chercher dans le catalogue » comme les pays : contrôle des champs saisis et mise au format des entrées
 * officielles (JobCatalog::localize) pour la recherche, les filtres et la copie. Un catalogue appartient à une
 * communauté ; partagé, il est visible en lecture seule par les autres, avec le nom de la communauté auteur.
 */
final class CommunityJobCatalog
{
    public const MAX_COLLECTIONS = 30;
    public const MAX_ENTRIES = 300;
    public const MAX_QUALIFICATIONS = 20;

    /** Code « pays » d’un catalogue de communauté dans la fenêtre (minuscule : jamais confondu avec un code ISO). */
    public static function code(int $collectionId): string
    {
        return 'c' . $collectionId;
    }

    private static function text(mixed $value, int $max, string $label, bool $required = false, int $min = 0): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', is_scalar($value) ? (string) $value : ''));
        if ($required && $value === '') {
            throw new InvalidArgumentException($label . ' est obligatoire.');
        }
        if ($value !== '' && mb_strlen($value) < $min) {
            throw new InvalidArgumentException(sprintf('%s : %d caractères minimum.', $label, $min));
        }
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(sprintf('%s : %d caractères maximum.', $label, $max));
        }

        return $value;
    }

    private static function longText(mixed $value, int $max, string $label): string
    {
        $value = trim(str_replace("\r\n", "\n", is_scalar($value) ? (string) $value : ''));
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(sprintf('%s : %d caractères maximum.', $label, $max));
        }

        return $value;
    }

    private static function icon(mixed $value): string
    {
        $value = trim(is_scalar($value) ? (string) $value : '');

        return JobRoleIconLibrary::has($value) ? $value : '';
    }

    /**
     * Catalogue saisi : nom (2 à 80), description, icône de la bibliothèque, couleur #rrggbb, partage public.
     *
     * @param array<string, mixed> $in
     * @return array{name: string, description: string, icon: string, color: string, is_public: int}
     */
    public static function normalizeCollection(array $in): array
    {
        $color = strtolower(trim(is_scalar($in['color'] ?? null) ? (string) $in['color'] : ''));

        return [
            'name' => self::text($in['name'] ?? '', 80, 'Le nom du catalogue', true, 2),
            'description' => self::longText($in['description'] ?? '', 1000, 'La description'),
            'icon' => self::icon($in['icon'] ?? ''),
            'color' => preg_match('/^#[0-9a-f]{6}$/', $color) === 1 ? $color : '',
            'is_public' => in_array($in['is_public'] ?? null, ['1', 1, true, 'on'], true) ? 1 : 0,
        ];
    }

    /**
     * Emploi saisi : mêmes champs que les catalogues officiels (nom, abrégé, catégorie libre, unité, type,
     * description, qualifications une par ligne, icône, étiquettes séparées par des virgules).
     *
     * @param array<string, mixed> $in
     * @return array{name: string, abbreviation: string, category: string, unit: string, type: string, description: string, qualifications: list<string>, icon: string, tags: list<string>}
     */
    public static function normalizeEntry(array $in): array
    {
        $abbreviation = JobRoleEnrichmentService::normalizeAbbreviation(is_scalar($in['abbreviation'] ?? null) ? (string) $in['abbreviation'] : '');
        $type = is_scalar($in['type'] ?? null) ? (string) $in['type'] : '';
        $lines = preg_split('/\R/u', is_scalar($in['qualifications'] ?? null) ? (string) $in['qualifications'] : '') ?: [];
        $qualifications = [];
        foreach ($lines as $line) {
            $line = trim($line, " \t-•*");
            if ($line !== '' && !in_array($line, $qualifications, true)) {
                $qualifications[] = mb_substr($line, 0, 160);
            }
        }
        if (count($qualifications) > self::MAX_QUALIFICATIONS) {
            throw new InvalidArgumentException(sprintf('%d qualifications maximum.', self::MAX_QUALIFICATIONS));
        }
        $tags = [];
        foreach (explode(',', is_scalar($in['tags'] ?? null) ? (string) $in['tags'] : '') as $tag) {
            $tag = trim($tag);
            if ($tag !== '' && !in_array($tag, $tags, true) && count($tags) < JobRoleEnrichmentService::MAX_TAGS) {
                $tags[] = mb_substr($tag, 0, JobRoleEnrichmentService::TAG_MAX_LENGTH);
            }
        }

        return [
            'name' => self::text($in['name'] ?? '', 120, 'Le nom de l’emploi', true, 2),
            'abbreviation' => (string) ($abbreviation ?? ''),
            'category' => self::text($in['category'] ?? '', 80, 'La catégorie'),
            'unit' => self::text($in['unit'] ?? '', 120, 'L’unité'),
            'type' => in_array($type, JobCatalog::TYPES, true) ? $type : 'operator',
            'description' => self::longText($in['description'] ?? '', 1500, 'La description'),
            'qualifications' => $qualifications,
            'icon' => self::icon($in['icon'] ?? ''),
            'tags' => $tags,
        ];
    }

    /** @return list<string> */
    private static function jsonList(mixed $raw): array
    {
        $list = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];

        return is_array($list) ? array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $list), static fn (string $v): bool => $v !== '')) : [];
    }

    /**
     * Catalogues et emplois visibles par une communauté, au format de la fenêtre : « pays » (barre), résumés (pied de
     * fenêtre) et entrées (comme JobCatalog::localize, plus image, catalogue modifiable ou non, communauté auteur).
     *
     * @param list<array<string, mixed>> $collections lignes job_catalog_collections (+ author_name)
     * @param list<array<string, mixed>> $entries lignes job_catalog_collection_entries
     * @param callable(string): string $mediaUrl chemin enregistré → URL publique
     * @return array{countries: list<array<string, mixed>>, packs: list<array<string, mixed>>, entries: list<array<string, mixed>>}
     */
    public static function toCatalog(array $collections, array $entries, int $tenantId, string $lang, callable $mediaUrl): array
    {
        $lang = JobCatalog::lang($lang);
        $byCollection = [];
        foreach ($entries as $row) {
            $byCollection[(int) $row['collection_id']][] = $row;
        }
        $out = ['countries' => [], 'packs' => [], 'entries' => []];
        foreach ($collections as $c) {
            $id = (int) $c['id'];
            $code = self::code($id);
            $own = (int) $c['tenant_id'] === $tenantId;
            $name = (string) $c['name'];
            $rows = $byCollection[$id] ?? [];
            $media = static fn (string $key): string => trim((string) ($c[$key] ?? '')) !== '' ? $mediaUrl((string) $c[$key]) : '';
            $out['countries'][] = [
                'code' => $code, 'label' => $name, 'count' => count($rows), 'community' => true, 'id' => $id,
                'editable' => $own, 'is_public' => (int) ($c['is_public'] ?? 0) === 1, 'author' => $own ? '' : (string) ($c['author_name'] ?? ''),
                'description' => (string) ($c['description'] ?? ''), 'icon' => (string) ($c['icon'] ?? ''), 'color' => (string) ($c['color'] ?? ''),
                'emblem_url' => $media('emblem_path'), 'banner_url' => $media('banner_path'),
            ];
            $out['packs'][] = [
                'pack' => $code, 'label' => $name, 'country' => $code, 'country_label' => $name,
                'compiled_at' => substr((string) ($c['updated_at'] ?? $c['created_at'] ?? ''), 0, 10),
                'sources_note' => (string) ($c['description'] ?? ''), 'count' => count($rows),
            ];
            foreach ($rows as $row) {
                $category = trim((string) ($row['category'] ?? ''));
                $type = (string) ($row['type'] ?? 'operator');
                $entryName = (string) $row['name'];
                $qualifications = self::jsonList($row['qualifications_json'] ?? '');
                $tags = self::jsonList($row['tags_json'] ?? '');
                $unit = trim((string) ($row['unit'] ?? ''));
                $out['entries'][] = [
                    'id' => $code . '-' . (int) $row['id'], 'entry_id' => (int) $row['id'], 'pack' => $code, 'country' => $code, 'country_label' => $name,
                    'community' => true, 'editable' => $own, 'author' => $own ? '' : (string) ($c['author_name'] ?? ''),
                    'branch' => $category !== '' ? 'k' . substr(md5(JobCatalog::normalize($category)), 0, 10) : 'general', 'branch_label' => $category !== '' ? $category : $name,
                    'category' => $category, 'command' => '', 'formation' => mb_substr($unit, 0, 40), 'unit' => $unit,
                    'type' => in_array($type, JobCatalog::TYPES, true) ? $type : 'operator', 'type_label' => JobCatalog::label('type', $type, $lang),
                    'code' => '', 'code_system' => '', 'code_system_label' => '',
                    // Langue du nom inconnue (saisie libre) : pas de liste de noms par langue.
                    'name' => $entryName, 'official_name' => $entryName, 'official_lang' => '', 'names' => [], 'alt' => '',
                    'abbreviation' => (string) ($row['abbreviation'] ?? ''), 'description' => (string) ($row['description'] ?? ''),
                    'qualifications' => $qualifications, 'billets' => [],
                    'icon' => JobRoleIconLibrary::has((string) ($row['icon'] ?? '')) ? (string) $row['icon'] : '',
                    'tags' => $tags, 'sources' => [],
                    'image_url' => trim((string) ($row['image_path'] ?? '')) !== '' ? $mediaUrl((string) $row['image_path']) : '',
                ];
            }
        }

        return $out;
    }
}
