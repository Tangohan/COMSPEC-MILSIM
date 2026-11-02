<?php

declare(strict_types=1);

namespace App\Support\QualificationCatalog;

use App\Support\JobCatalog\JobCatalog;

/**
 * Catalogue versionné des émetteurs réels (écoles, centres d’excellence, commandements, unités formatrices) :
 * fichiers config/qualification_issuers/<pack>.json, un par pays (us, fr, de, cn, ru) et un pour l’OTAN. Une
 * communauté y choisit les émetteurs à créer dans son référentiel (page Émetteurs) ; l’import d’une qualification du
 * catalogue de référence crée aussi son émetteur réel (clé issuer_key de l’entrée). Ce sont des émetteurs de jeu : la
 * fiche créée le rappelle.
 *
 * Format d’un pack : { pack, country, compiled_at, sources_note{fr,en}, issuers[] } ; un émetteur : key, name (nom
 * officiel, langue du pays), transliteration (cn / ru), short_name (sigle), type (TYPES), location, location_en,
 * website (https ou vide), description{fr,en}, aliases[] (libellés cités par les qualifications du catalogue et
 * anciens noms), sources[{url,title}], uncertain{fr,en} (facultatif : ce qui reste à confirmer).
 *
 * Rapprochement avec les émetteurs d’une communauté : même nom, même alias ou même sigle (casse, accents et
 * ponctuation ignorés). Un émetteur déjà présent n’est jamais modifié.
 */
final class QualificationIssuerCatalog
{
    /** Types d’émetteur du catalogue => type d’émetteur du référentiel (QualificationIssuerRepository::KINDS). */
    public const TYPES = [
        'school' => ['kind' => 'school', 'fr' => 'École', 'en' => 'School'],
        'academy' => ['kind' => 'school', 'fr' => 'Académie ou université', 'en' => 'Academy or university'],
        'coe' => ['kind' => 'training_center', 'fr' => 'Centre d’excellence', 'en' => 'Centre of excellence'],
        'training_center' => ['kind' => 'training_center', 'fr' => 'Centre d’entraînement', 'en' => 'Training centre'],
        'command' => ['kind' => 'external', 'fr' => 'Commandement', 'en' => 'Command'],
        'unit' => ['kind' => 'unit', 'fr' => 'Unité', 'en' => 'Unit'],
        'agency' => ['kind' => 'external', 'fr' => 'Organisme', 'en' => 'Agency'],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $packs = null;

    /** @var array<string, array<string, mixed>>|null clé => émetteur brut (avec pack et pays) */
    private static ?array $byKey = null;

    private static ?string $directory = null;

    public static function useDirectory(?string $directory): void
    {
        self::$directory = $directory;
        self::$packs = null;
        self::$byKey = null;
    }

    private static function directory(): string
    {
        return self::$directory ?? dirname(__DIR__, 3) . '/config/qualification_issuers';
    }

    /** @return array<string, array<string, mixed>> pack => contenu brut, dans l’ordre des pays du catalogue */
    public static function packs(): array
    {
        if (self::$packs !== null) {
            return self::$packs;
        }
        $files = array_map(static fn (string $f): string => basename($f, '.json'), glob(self::directory() . '/*.json') ?: []);
        $order = array_flip(QualificationCatalog::PACKS);
        usort($files, static fn (string $a, string $b): int => [($order[$a] ?? 99), $a] <=> [($order[$b] ?? 99), $b]);
        $out = [];
        foreach ($files as $pack) {
            if (preg_match('/^[a-z]{2,8}$/', $pack) !== 1) {
                continue;
            }
            $data = json_decode((string) file_get_contents(self::directory() . '/' . $pack . '.json'), true);
            if (!is_array($data) || !is_array($data['issuers'] ?? null)) {
                error_log('Qualification issuer catalog: pack illisible ' . $pack);
                continue;
            }
            $data['pack'] = $pack;
            $out[$pack] = $data;
        }

        return self::$packs = $out;
    }

    /** @return array<string, array<string, mixed>> clé => émetteur brut */
    public static function all(): array
    {
        if (self::$byKey !== null) {
            return self::$byKey;
        }
        $out = [];
        foreach (self::packs() as $pack => $data) {
            foreach ($data['issuers'] as $issuer) {
                if (is_array($issuer) && is_string($issuer['key'] ?? null) && !isset($out[$issuer['key']])) {
                    $out[$issuer['key']] = $issuer + ['pack' => $pack, 'country' => (string) ($data['country'] ?? '')];
                }
            }
        }

        return self::$byKey = $out;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function typeLabel(string $type, string $lang): string
    {
        return self::TYPES[$type][QualificationCatalog::lang($lang)] ?? self::TYPES['agency'][QualificationCatalog::lang($lang)];
    }

    /** Type d’émetteur du référentiel de la communauté. */
    public static function kindOf(array $issuer): string
    {
        return self::TYPES[(string) ($issuer['type'] ?? '')]['kind'] ?? 'external';
    }

    /**
     * Émetteur dans la langue du lecteur.
     *
     * @param array<string, mixed> $issuer
     * @return array<string, mixed>
     */
    public static function localize(array $issuer, string $lang): array
    {
        $lang = QualificationCatalog::lang($lang);
        $website = trim((string) ($issuer['website'] ?? ''));
        $sources = [];
        foreach (is_array($issuer['sources'] ?? null) ? $issuer['sources'] : [] as $s) {
            if (is_array($s) && preg_match('~^https://~', (string) ($s['url'] ?? '')) === 1) {
                $sources[] = ['url' => (string) $s['url'], 'title' => (string) ($s['title'] ?? '')];
            }
        }
        $country = (string) ($issuer['country'] ?? '');
        $location = trim((string) ($lang === 'en' ? ($issuer['location_en'] ?? '') : ($issuer['location'] ?? '')));

        return [
            'key' => (string) ($issuer['key'] ?? ''),
            'pack' => (string) ($issuer['pack'] ?? ''),
            'country' => $country,
            'country_label' => QualificationCatalog::countryLabel($country, $lang),
            'name' => trim((string) ($issuer['name'] ?? '')),
            'transliteration' => trim((string) ($issuer['transliteration'] ?? '')),
            'short_name' => trim((string) ($issuer['short_name'] ?? '')),
            'type' => (string) ($issuer['type'] ?? ''),
            'type_label' => self::typeLabel((string) ($issuer['type'] ?? ''), $lang),
            'kind' => self::kindOf($issuer),
            'location' => $location !== '' ? $location : trim((string) ($issuer['location'] ?? '')),
            'website' => preg_match('~^https://~', $website) === 1 ? $website : '',
            'description' => QualificationCatalog::pick($issuer['description'] ?? '', $lang),
            'uncertain' => QualificationCatalog::pick($issuer['uncertain'] ?? '', $lang),
            'sources' => $sources,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function entries(string $lang, ?string $pack = null): array
    {
        $out = [];
        foreach (self::all() as $issuer) {
            if ($pack === null || $issuer['pack'] === $pack) {
                $out[] = self::localize($issuer, $lang);
            }
        }

        return $out;
    }

    /** Clé de rapprochement : minuscules, sans accents ni ponctuation. */
    public static function norm(string $text): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', JobCatalog::normalize($text)));
    }

    /**
     * Formes reconnues d’un émetteur du catalogue : [nom, alias, transcription] puis sigle à part.
     *
     * @param array<string, mixed> $issuer
     * @return array{names: list<string>, short: string}
     */
    public static function forms(array $issuer): array
    {
        $names = [(string) ($issuer['name'] ?? ''), (string) ($issuer['transliteration'] ?? '')];
        foreach (is_array($issuer['aliases'] ?? null) ? $issuer['aliases'] : [] as $alias) {
            $names[] = (string) $alias;
        }
        $names = array_values(array_unique(array_filter(array_map([self::class, 'norm'], $names), static fn (string $n): bool => $n !== '')));

        return ['names' => $names, 'short' => self::norm((string) ($issuer['short_name'] ?? ''))];
    }

    /**
     * Émetteur de la communauté qui correspond à un émetteur du catalogue (même nom, alias, transcription, ou même
     * sigle), ou 0.
     *
     * @param array<string, mixed> $issuer émetteur brut du catalogue
     * @param list<array<string, mixed>> $tenantIssuers lignes qualification_issuers (id, name, short_name)
     */
    public static function matchTenant(array $issuer, array $tenantIssuers): int
    {
        $forms = self::forms($issuer);
        $byShort = 0;
        foreach ($tenantIssuers as $row) {
            $name = self::norm((string) ($row['name'] ?? ''));
            if ($name !== '' && in_array($name, $forms['names'], true)) {
                return (int) $row['id'];
            }
            $short = self::norm((string) ($row['short_name'] ?? ''));
            if ($byShort === 0 && $forms['short'] !== '' && ($short === $forms['short'] || $name === $forms['short'])) {
                $byShort = (int) $row['id'];
            }
        }

        return $byShort;
    }

    /**
     * Émetteur du catalogue qui correspond à un émetteur de la communauté (fiche Academy : localisation, site…).
     *
     * @param array<string, mixed> $row ligne qualification_issuers
     * @return array<string, mixed>|null émetteur brut
     */
    public static function matchCatalog(array $row): ?array
    {
        $name = self::norm((string) ($row['name'] ?? ''));
        $short = self::norm((string) ($row['short_name'] ?? ''));
        $byShort = null;
        foreach (self::all() as $issuer) {
            $forms = self::forms($issuer);
            if ($name !== '' && in_array($name, $forms['names'], true)) {
                return $issuer;
            }
            if ($byShort === null && $short !== '' && $short === $forms['short'] && mb_strlen($short) >= 3) {
                $byShort = $issuer;
            }
        }

        return $byShort;
    }

    /**
     * Présentation enregistrée dans la fiche de l’émetteur créé (langue de la communauté) : description, mention de
     * jeu, nom d’origine, type, localisation, site officiel, sources.
     *
     * @param array<string, mixed> $issuer émetteur brut
     * @param bool $details lignes localisation et site officiel (schéma sans les colonnes de l’étape 3680)
     */
    public static function description(array $issuer, string $lang, bool $details = true): string
    {
        $l = self::localize($issuer, $lang);
        $en = QualificationCatalog::lang($lang) === 'en';
        $lines = [$l['description'], '', $en
            ? 'In-game issuer inspired by a real ' . mb_strtolower($l['type_label']) . ' (' . $l['country_label'] . '), with no real-world value.'
            : 'Émetteur de jeu inspiré d’un organisme réel (' . mb_strtolower($l['type_label']) . ', ' . $l['country_label'] . '), sans valeur réelle.'];
        if ($l['transliteration'] !== '') {
            $lines[] = ($en ? 'Transliteration: ' : 'Transcription : ') . $l['transliteration'];
        }
        if ($details && $l['location'] !== '') {
            $lines[] = ($en ? 'Location: ' : 'Localisation : ') . $l['location'];
        }
        if ($details && $l['website'] !== '') {
            $lines[] = ($en ? 'Official website: ' : 'Site officiel : ') . $l['website'];
        }
        if ($l['sources'] !== []) {
            $lines[] = ($en ? 'Sources: ' : 'Sources : ') . implode(' ; ', array_column($l['sources'], 'url'));
        }

        return mb_substr(trim(implode("\n", $lines)), 0, 2000);
    }

    /**
     * Contrôle d’un émetteur brut (tests) : liste des problèmes, vide si valide.
     *
     * @param array<string, mixed> $issuer
     * @return list<string>
     */
    public static function problems(array $issuer): array
    {
        $p = [];
        if (preg_match('/^[a-z]{2,4}-[a-z0-9-]{2,60}$/', (string) ($issuer['key'] ?? '')) !== 1) {
            $p[] = 'clé invalide';
        }
        $name = trim((string) ($issuer['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 200) {
            $p[] = 'nom vide ou trop long';
        }
        $short = trim((string) ($issuer['short_name'] ?? ''));
        if ($short === '' || mb_strlen($short) > 80) {
            $p[] = 'sigle vide ou trop long';
        }
        if (!isset(self::TYPES[(string) ($issuer['type'] ?? '')])) {
            $p[] = 'type inconnu';
        }
        foreach (['fr', 'en'] as $l) {
            $v = trim((string) ($issuer['description'][$l] ?? ''));
            if ($v === '' || mb_strlen($v) > 700) {
                $p[] = 'description.' . $l . ' vide ou trop longue';
            }
        }
        $website = trim((string) ($issuer['website'] ?? ''));
        if ($website !== '' && preg_match('~^https://[a-z0-9.-]+\.[a-z]{2,}(/|$)~i', $website) !== 1) {
            $p[] = 'site officiel sans https';
        }
        $sources = is_array($issuer['sources'] ?? null) ? $issuer['sources'] : [];
        if ($sources === []) {
            $p[] = 'aucune source';
        }
        foreach ($sources as $s) {
            if (preg_match('~^https://[a-z0-9.-]+\.[a-z]{2,}(/|$)~i', (string) ($s['url'] ?? '')) !== 1) {
                $p[] = 'source sans URL https';
            }
        }

        return $p;
    }
}
