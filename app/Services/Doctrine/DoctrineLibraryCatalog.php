<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

/**
 * Catalogue des publications doctrinales de référence fourni par le site (Doctrine › Référentiel) : fichiers versionnés
 * config/doctrine_library/<pack>.json, un par pays ou organisation. Uniquement des textes publiés en accès libre
 * (rien de classifié ni en diffusion restreinte), chacun avec son code officiel, son titre dans la langue d’origine,
 * une traduction française et anglaise, son domaine, son niveau et sa source https.
 *
 * Format d’un pack : { pack, country, official_lang, label{fr,en}, compiled_at, sources_note{fr,en}, entries[] } ;
 * une entrée : key (préfixée par le pack), code, series, issuer, issuer_short, type, domain, level, lang, title,
 * title_fr, title_en, summary_fr, summary_en, edition, distribution (« public »), source_url, source_label, verified_at.
 * Les libellés (domaines, types, niveaux, pays) sont bilingues ici et servis dans la langue du lecteur.
 * Ajouter un pays = déposer le fichier et l’inscrire dans PACKS.
 */
final class DoctrineLibraryCatalog
{
    /** Packs livrés, dans l’ordre d’affichage du rail. */
    public const PACKS = ['fr', 'us', 'nato', 'de', 'gb'];

    /** Préfixe réservé aux références ajoutées par une communauté (clé « custom-<id> »). */
    public const CUSTOM_PREFIX = 'custom-';

    public const LANGS = ['fr', 'en', 'de'];

    /** @var array<string, array{fr: string, en: string}> */
    public const DOMAINS = [
        'command' => ['fr' => 'Commandement, éthique et leadership', 'en' => 'Command, ethics and leadership'],
        'joint' => ['fr' => 'Opérations interarmées', 'en' => 'Joint operations'],
        'planning' => ['fr' => 'Planification et état-major', 'en' => 'Planning and staff work'],
        'land' => ['fr' => 'Combat terrestre', 'en' => 'Land operations'],
        'air' => ['fr' => 'Opérations aériennes et spatiales', 'en' => 'Air and space operations'],
        'maritime' => ['fr' => 'Opérations maritimes', 'en' => 'Maritime operations'],
        'special_operations' => ['fr' => 'Opérations spéciales', 'en' => 'Special operations'],
        'intelligence' => ['fr' => 'Renseignement', 'en' => 'Intelligence'],
        'fires' => ['fr' => 'Appuis feux et ciblage', 'en' => 'Fires and targeting'],
        'logistics' => ['fr' => 'Soutien et logistique', 'en' => 'Sustainment and logistics'],
        'medical' => ['fr' => 'Soutien santé', 'en' => 'Medical support'],
        'cis_cyber' => ['fr' => 'Transmissions, cyber et guerre électronique', 'en' => 'Communications, cyber and electronic warfare'],
        'cbrn' => ['fr' => 'NRBC', 'en' => 'CBRN'],
        'civil_military' => ['fr' => 'Coopération civilo-militaire et influence', 'en' => 'Civil-military cooperation and influence'],
        'law' => ['fr' => 'Droit et cadre juridique', 'en' => 'Law and legal framework'],
        'training' => ['fr' => 'Formation et entraînement', 'en' => 'Education and training'],
    ];

    /** @var array<string, array{fr: string, en: string}> */
    public const TYPES = [
        'doctrine' => ['fr' => 'Doctrine', 'en' => 'Doctrine'],
        'tactics' => ['fr' => 'Manuel tactique', 'en' => 'Tactics manual'],
        'regulation' => ['fr' => 'Règlement', 'en' => 'Regulation'],
        'concept' => ['fr' => 'Concept ou stratégie', 'en' => 'Concept or strategy'],
        'law' => ['fr' => 'Loi ou traité', 'en' => 'Law or treaty'],
        'parliamentary' => ['fr' => 'Document parlementaire', 'en' => 'Parliamentary paper'],
        'standard' => ['fr' => 'Norme ou terminologie', 'en' => 'Standard or terminology'],
    ];

    /** @var array<string, array{fr: string, en: string}> */
    public const LEVELS = [
        'strategic' => ['fr' => 'Stratégique', 'en' => 'Strategic'],
        'operational' => ['fr' => 'Opératif', 'en' => 'Operational'],
        'tactical' => ['fr' => 'Tactique', 'en' => 'Tactical'],
        'institutional' => ['fr' => 'Institutionnel', 'en' => 'Institutional'],
    ];

    /** @var array<string, array{fr: string, en: string}> */
    public const LANG_LABELS = [
        'fr' => ['fr' => 'français', 'en' => 'French'],
        'en' => ['fr' => 'anglais', 'en' => 'English'],
        'de' => ['fr' => 'allemand', 'en' => 'German'],
    ];

    private const ENTRY_FIELDS = ['key', 'code', 'series', 'issuer', 'issuer_short', 'type', 'domain', 'level', 'lang', 'title',
        'title_fr', 'title_en', 'summary_fr', 'summary_en', 'edition', 'distribution', 'source_url', 'source_label', 'verified_at'];

    private static ?string $directory = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** Dossier des packs (tests : dossier temporaire ; null = config/doctrine_library). */
    public static function useDirectory(?string $directory): void
    {
        self::$directory = $directory;
        self::$cache = null;
    }

    private static function directory(): string
    {
        return self::$directory ?? dirname(__DIR__, 3) . '/config/doctrine_library';
    }

    public static function lang(?string $locale = null): string
    {
        $locale ??= function_exists('locale') ? locale() : 'fr';

        return str_starts_with(strtolower($locale), 'en') ? 'en' : 'fr';
    }

    /** @param array{fr: string, en: string} $pair */
    public static function pick(array $pair, ?string $locale = null): string
    {
        return (string) ($pair[self::lang($locale)] ?? $pair['fr'] ?? '');
    }

    public static function domainLabel(string $key, ?string $locale = null): string
    {
        return isset(self::DOMAINS[$key]) ? self::pick(self::DOMAINS[$key], $locale) : '';
    }

    public static function typeLabel(string $key, ?string $locale = null): string
    {
        return isset(self::TYPES[$key]) ? self::pick(self::TYPES[$key], $locale) : '';
    }

    public static function levelLabel(string $key, ?string $locale = null): string
    {
        return isset(self::LEVELS[$key]) ? self::pick(self::LEVELS[$key], $locale) : '';
    }

    public static function langLabel(string $key, ?string $locale = null): string
    {
        return isset(self::LANG_LABELS[$key]) ? self::pick(self::LANG_LABELS[$key], $locale) : $key;
    }

    /**
     * Packs lus et contrôlés (un pack illisible ou une entrée invalide est écarté sans casser la page).
     *
     * @return array<string, array<string, mixed>> pack => {pack, country, label, sources_note, compiled_at, entries}
     */
    public static function packs(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = [];
        foreach (self::PACKS as $pack) {
            $data = self::readPack($pack);
            if ($data === null) {
                continue;
            }
            $data['entries'] = array_values(array_filter(
                is_array($data['entries'] ?? null) ? $data['entries'] : [],
                static fn (mixed $e): bool => is_array($e) && self::entryErrors($e, $pack) === []
            ));
            $out[$pack] = $data;
        }

        return self::$cache = $out;
    }

    /** @return array<string, mixed>|null */
    private static function readPack(string $pack): ?array
    {
        $file = self::directory() . '/' . $pack . '.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    /** @return list<array<string, mixed>> toutes les entrées valides, avec leur pack */
    public static function all(): array
    {
        $out = [];
        foreach (self::packs() as $pack => $data) {
            foreach ($data['entries'] as $entry) {
                $entry['pack'] = $pack;
                $out[] = $entry;
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $entry) {
            if ($entry['key'] === $key) {
                return $entry;
            }
        }

        return null;
    }

    public static function packLabel(string $pack, ?string $locale = null): string
    {
        $label = self::packs()[$pack]['label'] ?? null;

        return is_array($label) ? self::pick(['fr' => (string) ($label['fr'] ?? $pack), 'en' => (string) ($label['en'] ?? $pack)], $locale) : strtoupper($pack);
    }

    public static function packCountry(string $pack): string
    {
        return (string) (self::packs()[$pack]['country'] ?? strtoupper($pack));
    }

    public static function sourcesNote(string $pack, ?string $locale = null): string
    {
        $note = self::packs()[$pack]['sources_note'] ?? null;

        return is_array($note) ? self::pick(['fr' => (string) ($note['fr'] ?? ''), 'en' => (string) ($note['en'] ?? '')], $locale) : '';
    }

    /**
     * Défauts d’une entrée de pack (vide = valide) : champs présents, clé préfixée, valeurs dans les listes, diffusion
     * publique, source https.
     *
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    public static function entryErrors(array $entry, string $pack): array
    {
        $errors = [];
        foreach (self::ENTRY_FIELDS as $field) {
            if (!array_key_exists($field, $entry) || !is_string($entry[$field])) {
                $errors[] = 'champ ' . $field;
            }
        }
        if ($errors !== []) {
            return $errors;
        }
        foreach (['key', 'code', 'issuer', 'title', 'title_fr', 'title_en', 'summary_fr', 'summary_en', 'source_url'] as $required) {
            if (trim($entry[$required]) === '') {
                $errors[] = 'vide : ' . $required;
            }
        }
        if (!preg_match('/^' . preg_quote($pack, '/') . '-[a-z0-9-]+$/', $entry['key']) || str_starts_with($entry['key'], self::CUSTOM_PREFIX)) {
            $errors[] = 'clé ' . $entry['key'];
        }
        if (!isset(self::TYPES[$entry['type']])) {
            $errors[] = 'type ' . $entry['type'];
        }
        if (!isset(self::DOMAINS[$entry['domain']])) {
            $errors[] = 'domaine ' . $entry['domain'];
        }
        if (!isset(self::LEVELS[$entry['level']])) {
            $errors[] = 'niveau ' . $entry['level'];
        }
        if (!in_array($entry['lang'], self::LANGS, true)) {
            $errors[] = 'langue ' . $entry['lang'];
        }
        if ($entry['distribution'] !== 'public') {
            $errors[] = 'diffusion ' . $entry['distribution'];
        }
        if (!self::isHttpsUrl($entry['source_url'])) {
            $errors[] = 'source ' . $entry['source_url'];
        }

        return $errors;
    }

    public static function isHttpsUrl(string $url): bool
    {
        $url = trim($url);
        if (!str_starts_with(strtolower($url), 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host !== '' && str_contains($host, '.');
    }

    /**
     * Contrôle complet des fichiers livrés (tests) : fichiers lisibles, en-têtes, entrées valides, clés uniques sur
     * l’ensemble des packs, codes uniques dans chaque pack.
     *
     * @return list<string>
     */
    public static function validate(): array
    {
        $errors = [];
        $keys = [];
        foreach (self::PACKS as $pack) {
            $data = self::readPack($pack);
            if ($data === null) {
                $errors[] = $pack . ' : fichier absent ou illisible';
                continue;
            }
            if (($data['pack'] ?? null) !== $pack) {
                $errors[] = $pack . ' : champ pack';
            }
            foreach (['label', 'sources_note'] as $pair) {
                if (!is_array($data[$pair] ?? null) || trim((string) ($data[$pair]['fr'] ?? '')) === '' || trim((string) ($data[$pair]['en'] ?? '')) === '') {
                    $errors[] = $pack . ' : ' . $pair . ' fr/en';
                }
            }
            $codes = [];
            foreach (is_array($data['entries'] ?? null) ? $data['entries'] : [] as $i => $entry) {
                if (!is_array($entry)) {
                    $errors[] = $pack . ' #' . $i . ' : entrée invalide';
                    continue;
                }
                foreach (self::entryErrors($entry, $pack) as $e) {
                    $errors[] = $pack . ' ' . (string) ($entry['key'] ?? '#' . $i) . ' : ' . $e;
                }
                $key = (string) ($entry['key'] ?? '');
                if (isset($keys[$key])) {
                    $errors[] = $pack . ' : clé en double ' . $key;
                }
                $keys[$key] = true;
                $code = mb_strtolower(trim((string) ($entry['code'] ?? '')));
                if (isset($codes[$code])) {
                    $errors[] = $pack . ' : code en double ' . $code;
                }
                $codes[$code] = true;
            }
        }

        return $errors;
    }
}
