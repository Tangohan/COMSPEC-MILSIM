<?php

declare(strict_types=1);

namespace App\Support\JobCatalog;

use App\Services\Personnel\JobRoleEnrichmentService;
use App\Support\JobRoleIconLibrary;

/**
 * Catalogue d’emplois de référence (inspiration à la création d’un emploi) : fichiers versionnés
 * config/job_catalog/<pack>.json, un par pays ou par armée. Chaque entrée est bilingue (fr / en) et servie dans la
 * langue du lecteur, sans passer par le dictionnaire du site. Ajouter un pack = déposer le fichier et l’inscrire
 * dans PACKS.
 *
 * Format d’un pack : { pack, country, label{fr,en}, compiled_at, sources_note{fr,en}, entries[] } ; une entrée :
 * id, branch, command, unit{fr,en}, type, code, code_system, name{fr,en}, abbreviation, description{fr,en},
 * qualifications{fr[],en[]}, billets{fr[],en[]}, icon, tags{fr[],en[]}, sources[{url,title}].
 */
final class JobCatalog
{
    /** Packs livrés (nom du fichier sans extension). */
    public const PACKS = ['us_sof'];

    public const TYPES = ['operator', 'command', 'support', 'aviation'];
    public const BRANCHES = ['army', 'navy', 'air_force', 'marine_corps', 'joint'];
    public const CODE_SYSTEMS = ['', 'MOS', 'WO_MOS', 'AOC', 'AFSC', 'NEC', 'RATING', 'DESIGNATOR'];

    /** Libellés des filtres, contenus du catalogue (langue du lecteur). */
    private const LABELS = [
        'type' => [
            'operator' => ['fr' => 'Opérateur', 'en' => 'Operator'],
            'command' => ['fr' => 'Commandement et encadrement', 'en' => 'Command and leadership'],
            'support' => ['fr' => 'Soutien', 'en' => 'Support'],
            'aviation' => ['fr' => 'Aviation', 'en' => 'Aviation'],
        ],
        'branch' => [
            'army' => ['fr' => 'US Army (armée de terre)', 'en' => 'US Army'],
            'navy' => ['fr' => 'US Navy (marine)', 'en' => 'US Navy'],
            'air_force' => ['fr' => 'US Air Force (armée de l’air)', 'en' => 'US Air Force'],
            'marine_corps' => ['fr' => 'US Marine Corps', 'en' => 'US Marine Corps'],
            'joint' => ['fr' => 'Interarmées', 'en' => 'Joint'],
        ],
        'code_system' => [
            '' => ['fr' => '', 'en' => ''],
            'MOS' => ['fr' => 'MOS (spécialité militaire)', 'en' => 'MOS'],
            'WO_MOS' => ['fr' => 'MOS warrant officer', 'en' => 'Warrant officer MOS'],
            'AOC' => ['fr' => 'AOC (spécialité d’officier)', 'en' => 'AOC'],
            'AFSC' => ['fr' => 'AFSC (spécialité Air Force)', 'en' => 'AFSC'],
            'NEC' => ['fr' => 'NEC (qualification Navy)', 'en' => 'NEC'],
            'RATING' => ['fr' => 'Rating (spécialité Navy)', 'en' => 'Rating'],
            'DESIGNATOR' => ['fr' => 'Désignateur d’officier Navy', 'en' => 'Navy officer designator'],
        ],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $packs = null;

    private static ?string $directory = null;

    /** Dossier des packs (tests : un dossier temporaire). */
    public static function useDirectory(?string $directory): void
    {
        self::$directory = $directory;
        self::$packs = null;
    }

    private static function directory(): string
    {
        return self::$directory ?? dirname(__DIR__, 3) . '/config/job_catalog';
    }

    /** @return array<string, array<string, mixed>> pack => contenu brut */
    public static function packs(): array
    {
        if (self::$packs !== null) {
            return self::$packs;
        }
        $out = [];
        foreach (self::PACKS as $pack) {
            $file = self::directory() . '/' . $pack . '.json';
            if (!is_file($file)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data) || !is_array($data['entries'] ?? null)) {
                error_log('Job catalog: pack illisible ' . $pack);
                continue;
            }
            $data['pack'] = $pack;
            $out[$pack] = $data;
        }

        return self::$packs = $out;
    }

    /** @return list<array<string, mixed>> entrées brutes bilingues, avec leur pack */
    public static function rawEntries(): array
    {
        $out = [];
        foreach (self::packs() as $pack => $data) {
            foreach ($data['entries'] as $entry) {
                if (is_array($entry)) {
                    $out[] = $entry + ['pack' => $pack, 'country' => (string) ($data['country'] ?? '')];
                }
            }
        }

        return $out;
    }

    public static function lang(string $lang): string
    {
        return str_starts_with(strtolower($lang), 'en') ? 'en' : 'fr';
    }

    /** @param mixed $pair {fr, en} */
    private static function pick(mixed $pair, string $lang): string
    {
        if (!is_array($pair)) {
            return is_scalar($pair) ? (string) $pair : '';
        }
        $value = trim((string) ($pair[$lang] ?? ''));

        return $value !== '' ? $value : trim((string) ($pair[$lang === 'en' ? 'fr' : 'en'] ?? ''));
    }

    /** @return list<string> */
    private static function pickList(mixed $pair, string $lang): array
    {
        if (!is_array($pair)) {
            return [];
        }
        $list = $pair[$lang] ?? [];
        if (!is_array($list) || $list === []) {
            $list = $pair[$lang === 'en' ? 'fr' : 'en'] ?? [];
        }

        return array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), is_array($list) ? $list : []), static fn (string $v): bool => $v !== ''));
    }

    public static function label(string $kind, string $key, string $lang): string
    {
        return self::LABELS[$kind][$key][self::lang($lang)] ?? $key;
    }

    /**
     * Entrée dans la langue du lecteur, prête pour l’affichage et la copie dans un emploi.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    public static function localize(array $entry, string $lang): array
    {
        $lang = self::lang($lang);
        $branch = (string) ($entry['branch'] ?? '');
        $type = (string) ($entry['type'] ?? '');
        $icon = (string) ($entry['icon'] ?? '');
        $sources = [];
        foreach (is_array($entry['sources'] ?? null) ? $entry['sources'] : [] as $source) {
            $url = is_array($source) ? (string) ($source['url'] ?? '') : (string) $source;
            if (preg_match('~^https://~', $url) === 1) {
                $sources[] = ['url' => $url, 'title' => is_array($source) ? (string) ($source['title'] ?? '') : ''];
            }
        }

        return [
            'id' => (string) ($entry['id'] ?? ''),
            'pack' => (string) ($entry['pack'] ?? ''),
            'country' => (string) ($entry['country'] ?? ''),
            'branch' => $branch,
            'branch_label' => self::label('branch', $branch, $lang),
            'command' => (string) ($entry['command'] ?? ''),
            'unit' => self::pick($entry['unit'] ?? '', $lang),
            'type' => $type,
            'type_label' => self::label('type', $type, $lang),
            'code' => (string) ($entry['code'] ?? ''),
            'code_system' => (string) ($entry['code_system'] ?? ''),
            'code_system_label' => self::label('code_system', (string) ($entry['code_system'] ?? ''), $lang),
            'name' => self::pick($entry['name'] ?? '', $lang),
            'official_name' => self::pick($entry['name'] ?? '', 'en'),
            'abbreviation' => (string) ($entry['abbreviation'] ?? ''),
            'description' => self::pick($entry['description'] ?? '', $lang),
            'qualifications' => self::pickList($entry['qualifications'] ?? [], $lang),
            'billets' => self::pickList($entry['billets'] ?? [], $lang),
            'icon' => JobRoleIconLibrary::has($icon) ? $icon : '',
            'tags' => array_slice(self::pickList($entry['tags'] ?? [], $lang), 0, JobRoleEnrichmentService::MAX_TAGS),
            'sources' => $sources,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function entries(string $lang): array
    {
        return array_map(static fn (array $e): array => self::localize($e, $lang), self::rawEntries());
    }

    /** @return array<string, mixed>|null */
    public static function find(string $id, string $lang): ?array
    {
        foreach (self::rawEntries() as $entry) {
            if ((string) ($entry['id'] ?? '') === $id) {
                return self::localize($entry, $lang);
            }
        }

        return null;
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $text = (string) preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($text, \Normalizer::FORM_D));
        } else {
            $text = strtr($text, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae']);
        }

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
    }

    /** Texte cherché d’une entrée : noms, abrégé, code, unité, commandement, branche et étiquettes dans les deux langues. */
    public static function haystack(array $entry): string
    {
        $parts = [(string) ($entry['id'] ?? ''), (string) ($entry['abbreviation'] ?? ''), (string) ($entry['code'] ?? ''), (string) ($entry['command'] ?? '')];
        foreach (['name', 'unit'] as $field) {
            foreach (['fr', 'en'] as $l) {
                $parts[] = self::pick($entry[$field] ?? '', $l);
            }
        }
        foreach (['tags'] as $field) {
            foreach (['fr', 'en'] as $l) {
                $parts[] = implode(' ', self::pickList($entry[$field] ?? [], $l));
            }
        }
        foreach (['fr', 'en'] as $l) {
            $parts[] = self::label('branch', (string) ($entry['branch'] ?? ''), $l);
            $parts[] = self::label('type', (string) ($entry['type'] ?? ''), $l);
        }
        $code = (string) ($entry['code'] ?? '');

        // « 18B » se trouve aussi en tapant « 18 b ».
        return ' ' . self::normalize(implode(' ', $parts)) . ' ' . strtolower(str_replace(' ', '', $code)) . ' ';
    }

    /**
     * Recherche plein texte (tous les mots, sans accents ni casse) et filtres exacts. Le nom et l’abrégé exacts
     * passent en tête.
     *
     * @param array{branch?: string, command?: string, type?: string, pack?: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function search(string $query, array $filters, string $lang): array
    {
        $words = array_values(array_filter(explode(' ', self::normalize($query)), static fn (string $w): bool => $w !== ''));
        $hits = [];
        foreach (self::rawEntries() as $position => $entry) {
            foreach (['branch', 'command', 'type', 'pack'] as $f) {
                $want = trim((string) ($filters[$f] ?? ''));
                if ($want !== '' && (string) ($entry[$f] ?? '') !== $want) {
                    continue 2;
                }
            }
            $hay = self::haystack($entry);
            $score = 0;
            foreach ($words as $word) {
                if (!str_contains($hay, $word)) {
                    continue 2;
                }
                $score += str_contains($hay, ' ' . $word . ' ') ? 2 : 1;
            }
            $abbr = strtolower((string) ($entry['abbreviation'] ?? ''));
            $code = strtolower((string) ($entry['code'] ?? ''));
            $q = strtolower(str_replace(' ', '', trim($query)));
            if ($q !== '' && ($q === $abbr || $q === $code)) {
                $score += 10;
            }
            $hits[] = [$score, $position, $entry];
        }
        usort($hits, static fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_map(static fn (array $h): array => self::localize($h[2], $lang), $hits);
    }

    /** @return array{branch: array<string, string>, command: array<string, string>, type: array<string, string>, pack: array<string, string>} */
    public static function facets(string $lang): array
    {
        $out = ['branch' => [], 'command' => [], 'type' => [], 'pack' => []];
        foreach (self::rawEntries() as $entry) {
            $branch = (string) ($entry['branch'] ?? '');
            $type = (string) ($entry['type'] ?? '');
            $command = (string) ($entry['command'] ?? '');
            if ($branch !== '') {
                $out['branch'][$branch] = self::label('branch', $branch, $lang);
            }
            if ($type !== '') {
                $out['type'][$type] = self::label('type', $type, $lang);
            }
            if ($command !== '') {
                $out['command'][$command] = $command;
            }
        }
        foreach (self::packs() as $pack => $data) {
            $out['pack'][$pack] = self::pick($data['label'] ?? $pack, self::lang($lang));
        }
        ksort($out['command']);
        $order = array_flip(array_merge(self::BRANCHES, self::TYPES));
        foreach (['branch', 'type'] as $k) {
            uksort($out[$k], static fn (string $a, string $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
        }

        return $out;
    }

    /** @return list<array{pack: string, label: string, country: string, compiled_at: string, sources_note: string, count: int}> */
    public static function packSummaries(string $lang): array
    {
        $out = [];
        foreach (self::packs() as $pack => $data) {
            $out[] = [
                'pack' => $pack,
                'label' => self::pick($data['label'] ?? $pack, self::lang($lang)),
                'country' => (string) ($data['country'] ?? ''),
                'compiled_at' => (string) ($data['compiled_at'] ?? ''),
                'sources_note' => self::pick($data['sources_note'] ?? '', self::lang($lang)),
                'count' => count($data['entries']),
            ];
        }

        return $out;
    }

    /**
     * Contrôle d’une entrée brute (tests et ajout d’un pack) : liste des problèmes, vide si l’entrée est valide.
     *
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    public static function problems(array $entry): array
    {
        $id = (string) ($entry['id'] ?? '');
        $p = [];
        if (preg_match('/^[a-z]{2,3}-[a-z0-9-]{2,60}$/', $id) !== 1) {
            $p[] = 'id invalide';
        }
        if (!in_array($entry['branch'] ?? null, self::BRANCHES, true)) {
            $p[] = 'branche inconnue';
        }
        if (!in_array($entry['type'] ?? null, self::TYPES, true)) {
            $p[] = 'type inconnu';
        }
        if (!in_array($entry['code_system'] ?? '', self::CODE_SYSTEMS, true)) {
            $p[] = 'système de code inconnu';
        }
        if (trim((string) ($entry['command'] ?? '')) === '') {
            $p[] = 'commandement manquant';
        }
        foreach (['name' => 120, 'description' => 500, 'unit' => 200] as $field => $max) {
            foreach (['fr', 'en'] as $l) {
                $v = trim((string) ($entry[$field][$l] ?? ''));
                if ($v === '' || mb_strlen($v) > $max) {
                    $p[] = $field . '.' . $l . ' vide ou trop long';
                }
            }
        }
        $abbr = (string) ($entry['abbreviation'] ?? '');
        if ($abbr !== '' && preg_match('/^[A-Z0-9]{2,8}$/', $abbr) !== 1) {
            $p[] = 'abrégé invalide';
        }
        $icon = (string) ($entry['icon'] ?? '');
        if ($icon !== '' && !JobRoleIconLibrary::has($icon)) {
            $p[] = 'icône inconnue';
        }
        foreach (['qualifications', 'billets', 'tags'] as $field) {
            $fr = $entry[$field]['fr'] ?? [];
            $en = $entry[$field]['en'] ?? [];
            if (!is_array($fr) || !is_array($en) || count($fr) !== count($en)) {
                $p[] = $field . ' : listes fr/en de longueurs différentes';
            }
        }
        foreach (['fr', 'en'] as $l) {
            $tags = $entry['tags'][$l] ?? [];
            if (is_array($tags) && (count($tags) > JobRoleEnrichmentService::MAX_TAGS || array_filter($tags, static fn ($t): bool => mb_strlen((string) $t) > JobRoleEnrichmentService::TAG_MAX_LENGTH) !== [])) {
                $p[] = 'étiquettes trop nombreuses ou trop longues';
            }
        }
        $sources = is_array($entry['sources'] ?? null) ? $entry['sources'] : [];
        if ($sources === []) {
            $p[] = 'aucune source';
        }
        foreach ($sources as $source) {
            if (preg_match('~^https://[a-z0-9.-]+\.[a-z]{2,}(/|$)~i', (string) ($source['url'] ?? '')) !== 1) {
                $p[] = 'source sans URL https';
            }
        }

        return $p;
    }
}
