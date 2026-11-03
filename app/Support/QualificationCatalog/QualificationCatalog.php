<?php

declare(strict_types=1);

namespace App\Support\QualificationCatalog;

/**
 * Catalogue de qualifications de référence que chaque communauté peut importer dans son référentiel :
 * fichiers versionnés config/qualification_catalog/<pack>.json, un par pays (us, fr, de : brevets et
 * qualifications réellement cités par les packs d’emplois du même pays) et un pour l’OTAN (standards et cours
 * OTAN publics). Chaque entrée est bilingue (fr / en) et servie dans la langue du lecteur ; l’intitulé officiel
 * reste dans la langue du pays. Ce sont des qualifications de jeu : l’import ajoute toujours la mention du pack
 * (« qualification de jeu inspirée de …, sans valeur réelle »).
 *
 * Format d’un pack : { pack, country, official_lang, label{fr,en}, disclaimer{fr,en}, issuer{name, short_name,
 * description{fr,en}}, compiled_at, sources_note{fr,en}, entries[] } ; une entrée : id, code, category, name{fr,en},
 * official_name (intitulé officiel, langue du pays, facultatif), short_name, description{fr,en}, body{fr,en}
 * (organisme de référence), issuer{name, short_name} (école ou organisme émetteur réel, facultatif : sinon
 * l’émetteur du pack), reference{fr,en}, prerequisites{fr[],en[]}, validity{fr,en} (vide si aucune source ouverte
 * ne la précise), validity_months (entier ou null), sources[{url,title}], issuer_key (facultatif : émetteur réel du
 * catalogue config/qualification_issuers, prioritaire sur issuer), prerequisites_detected (facultatif : prérequis
 * structurés de confiance haute pré-calculés depuis le texte, kind metric | attestation | grade | qualification ;
 * proposés à l'import, jamais appliqués seuls).
 * Les emplois du catalogue d’emplois désignent ces entrées par leur id (champ qualification_refs).
 */
final class QualificationCatalog
{
    /** Packs livrés (nom du fichier sans extension). */
    public const PACKS = ['us', 'fr', 'de', 'cn', 'ru', 'gb', 'my', 'nato'];

    /** Pays des packs, dans l’ordre des onglets (mêmes codes que le catalogue d’emplois). */
    public const COUNTRIES = [
        'US' => ['fr' => 'États-Unis', 'en' => 'United States'],
        'FR' => ['fr' => 'France', 'en' => 'France'],
        'DE' => ['fr' => 'Allemagne', 'en' => 'Germany'],
        'CN' => ['fr' => 'Chine', 'en' => 'China'],
        'RU' => ['fr' => 'Russie', 'en' => 'Russia'],
        'GB' => ['fr' => 'Royaume-Uni', 'en' => 'United Kingdom'],
        'MY' => ['fr' => 'Malaisie', 'en' => 'Malaysia'],
        'NATO' => ['fr' => 'OTAN', 'en' => 'NATO'],
    ];

    /** Catégories du catalogue (créées dans le référentiel de la communauté à l’import, si absentes). */
    public const CATEGORIES = [
        'air_support' => ['fr' => 'Appui aérien', 'en' => 'Air support'],
        'fires' => ['fr' => 'Appui-feux', 'en' => 'Fire support'],
        'sof' => ['fr' => 'Opérations spéciales', 'en' => 'Special operations'],
        'medical' => ['fr' => 'Santé et évacuation', 'en' => 'Medical and evacuation'],
        'personnel_recovery' => ['fr' => 'Récupération de personnel', 'en' => 'Personnel recovery'],
        'cbrn_eod' => ['fr' => 'NRBC, EOD et lutte contre les engins explosifs', 'en' => 'CBRN, EOD and counter-IED'],
        'c2_staff' => ['fr' => 'Commandement et état-major', 'en' => 'Command and staff'],
        'communications' => ['fr' => 'Transmissions et systèmes d’information', 'en' => 'Communications and information systems'],
        'intelligence' => ['fr' => 'Renseignement et ciblage', 'en' => 'Intelligence and targeting'],
        'influence' => ['fr' => 'CIMIC et influence', 'en' => 'CIMIC and influence'],
        'selection' => ['fr' => 'Sélection et cursus forces spéciales', 'en' => 'Special forces selection and courses'],
        'airborne' => ['fr' => 'Parachutisme et mise à terre', 'en' => 'Airborne and insertion'],
        'maritime' => ['fr' => 'Plongée et opérations maritimes', 'en' => 'Diving and maritime operations'],
        'aviation' => ['fr' => 'Aviation', 'en' => 'Aviation'],
        'marksmanship' => ['fr' => 'Tir et tireur d’élite', 'en' => 'Marksmanship and sniping'],
        'environment' => ['fr' => 'Montagne, grand froid et milieux spécifiques', 'en' => 'Mountain, arctic and specialised environments'],
        'close_combat' => ['fr' => 'Combat rapproché et corps à corps', 'en' => 'Close combat and combatives'],
    ];

    public const DISCLAIMER = [
        'fr' => 'Qualification de jeu inspirée du standard OTAN, sans valeur réelle.',
        'en' => 'In-game qualification inspired by the NATO standard, with no real-world value.',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $packs = null;

    private static ?string $directory = null;

    /** Dossier des packs (tests : un dossier temporaire). */
    public static function useDirectory(?string $directory): void
    {
        self::$directory = $directory;
        self::$packs = null;
        \App\Support\QualificationCatalog\QualificationCatalogExplorer::reset();
    }

    private static function directory(): string
    {
        return self::$directory ?? dirname(__DIR__, 3) . '/config/qualification_catalog';
    }

    public static function lang(string $lang): string
    {
        return str_starts_with(strtolower($lang), 'en') ? 'en' : 'fr';
    }

    /** @return array<string, array<string, mixed>> pack => contenu brut */
    public static function packs(): array
    {
        if (self::$packs !== null) {
            return self::$packs;
        }
        $out = [];
        // Packs inscrits, puis tout pack déposé ensuite dans le dossier (nouveau pays servi sans autre changement).
        $extra = array_map(static fn (string $f): string => basename($f, '.json'), glob(self::directory() . '/*.json') ?: []);
        sort($extra);
        foreach (array_values(array_unique(array_merge(self::PACKS, array_filter($extra, static fn (string $p): bool => preg_match('/^[a-z]{2,8}$/', $p) === 1)))) as $pack) {
            $file = self::directory() . '/' . $pack . '.json';
            if (!is_file($file)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data) || !is_array($data['entries'] ?? null)) {
                error_log('Qualification catalog: pack illisible ' . $pack);
                continue;
            }
            $data['pack'] = $pack;
            $out[$pack] = $data;
        }

        return self::$packs = $out;
    }

    /** @return array<string, mixed>|null */
    public static function pack(string $pack): ?array
    {
        return self::packs()[$pack] ?? null;
    }

    /** @param mixed $pair {fr, en} */
    public static function pick(mixed $pair, string $lang): string
    {
        if (!is_array($pair)) {
            return is_scalar($pair) ? trim((string) $pair) : '';
        }
        $lang = self::lang($lang);
        $value = trim((string) ($pair[$lang] ?? ''));

        return $value !== '' ? $value : trim((string) ($pair[$lang === 'en' ? 'fr' : 'en'] ?? ''));
    }

    /** @return list<string> */
    private static function pickList(mixed $pair, string $lang): array
    {
        if (!is_array($pair)) {
            return [];
        }
        $lang = self::lang($lang);
        $list = $pair[$lang] ?? [];
        if (!is_array($list) || $list === []) {
            $list = $pair[$lang === 'en' ? 'fr' : 'en'] ?? [];
        }

        return array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), is_array($list) ? $list : []), static fn (string $v): bool => $v !== ''));
    }

    public static function categoryLabel(string $category, string $lang): string
    {
        return self::CATEGORIES[$category][self::lang($lang)] ?? $category;
    }

    /** Mention de jeu d’un pack (« qualification de jeu inspirée de …, sans valeur réelle »). */
    public static function disclaimer(string $lang, ?string $pack = null): string
    {
        $own = $pack !== null ? self::pick(self::pack($pack)['disclaimer'] ?? '', $lang) : '';

        return $own !== '' ? $own : self::DISCLAIMER[self::lang($lang)];
    }

    public static function countryLabel(string $country, string $lang): string
    {
        if (isset(self::COUNTRIES[$country])) {
            return self::COUNTRIES[$country][self::lang($lang)];
        }
        // Pays d’un pack ajouté sans libellé ici : nom du pack.
        foreach (self::packs() as $data) {
            if ((string) ($data['country'] ?? '') === $country) {
                return self::pick($data['label'] ?? '', $lang) ?: $country;
            }
        }

        return $country;
    }

    /**
     * Émetteur d’une entrée : l’école ou l’organisme réel de l’entrée, sinon l’émetteur de référence du pack.
     *
     * @param array<string, mixed> $entry entrée brute (avec son pack)
     * Avec une clé issuer_key (catalogue des émetteurs réels, QualificationIssuerCatalog), c’est cet émetteur réel.
     *
     * @return array{name: string, short_name: string, description: string, kind: string, key?: string}
     */
    public static function issuerOf(array $entry, string $lang): array
    {
        $real = is_string($entry['issuer_key'] ?? null) ? QualificationIssuerCatalog::find($entry['issuer_key']) : null;
        if ($real !== null) {
            $l = QualificationIssuerCatalog::localize($real, $lang);

            return [
                'name' => mb_substr($l['name'], 0, 200),
                'short_name' => mb_substr($l['short_name'], 0, 80),
                'description' => QualificationIssuerCatalog::description($real, $lang),
                'kind' => $l['kind'],
                'key' => $l['key'],
            ];
        }
        $pack = self::pack((string) ($entry['pack'] ?? '')) ?? [];
        $own = is_array($entry['issuer'] ?? null) ? $entry['issuer'] : [];
        $packIssuer = is_array($pack['issuer'] ?? null) ? $pack['issuer'] : [];
        $name = trim((string) ($own['name'] ?? ''));
        if ($name !== '') {
            return [
                'name' => mb_substr($name, 0, 200),
                'short_name' => mb_substr(trim((string) ($own['short_name'] ?? '')), 0, 80),
                'description' => self::pick($entry['body'] ?? '', $lang),
                'kind' => 'school',
            ];
        }

        return [
            'name' => trim((string) ($packIssuer['name'] ?? 'OTAN (référence)')),
            'short_name' => trim((string) ($packIssuer['short_name'] ?? '')),
            'description' => self::pick($packIssuer['description'] ?? '', $lang),
            'kind' => 'external',
        ];
    }

    /**
     * Entrée dans la langue du lecteur.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    public static function localize(array $entry, string $lang): array
    {
        $sources = [];
        foreach (is_array($entry['sources'] ?? null) ? $entry['sources'] : [] as $source) {
            $url = is_array($source) ? (string) ($source['url'] ?? '') : '';
            if (preg_match('~^https://~', $url) === 1) {
                $sources[] = ['url' => $url, 'title' => (string) ($source['title'] ?? '')];
            }
        }
        $months = $entry['validity_months'] ?? null;
        $category = (string) ($entry['category'] ?? '');

        $country = (string) (self::pack((string) ($entry['pack'] ?? ''))['country'] ?? '');

        return [
            'id' => (string) ($entry['id'] ?? ''),
            'pack' => (string) ($entry['pack'] ?? ''),
            'country' => $country,
            'country_label' => self::countryLabel($country, $lang),
            'code' => (string) ($entry['code'] ?? ''),
            'official_name' => trim((string) ($entry['official_name'] ?? '')),
            'issuer' => self::issuerOf($entry, $lang)['name'],
            'issuer_key' => is_string($entry['issuer_key'] ?? null) && QualificationIssuerCatalog::find($entry['issuer_key']) !== null ? $entry['issuer_key'] : '',
            'category' => $category,
            'category_label' => self::categoryLabel($category, $lang),
            'name' => self::pick($entry['name'] ?? '', $lang),
            'short_name' => trim((string) ($entry['short_name'] ?? '')),
            'description' => self::pick($entry['description'] ?? '', $lang),
            'body' => self::pick($entry['body'] ?? '', $lang),
            'reference' => self::pick($entry['reference'] ?? '', $lang),
            'prerequisites' => self::pickList($entry['prerequisites'] ?? [], $lang),
            'validity' => self::pick($entry['validity'] ?? '', $lang),
            'validity_months' => is_int($months) && $months > 0 ? $months : null,
            'sources' => $sources,
        ];
    }

    /** @return list<array<string, mixed>> entrées brutes, avec leur pack */
    public static function rawEntries(?string $pack = null): array
    {
        $out = [];
        foreach (self::packs() as $name => $data) {
            if ($pack !== null && $pack !== $name) {
                continue;
            }
            foreach ($data['entries'] as $entry) {
                if (is_array($entry)) {
                    $out[] = $entry + ['pack' => $name];
                }
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function entries(string $lang, ?string $pack = null): array
    {
        return array_map(static fn (array $e): array => self::localize($e, $lang), self::rawEntries($pack));
    }

    /** Entrée brute d'après le code d'une fiche importée (les fiches gardent le code du catalogue). @return array<string, mixed>|null */
    public static function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        foreach (self::rawEntries() as $entry) {
            if (strtoupper((string) ($entry['code'] ?? '')) === $code) {
                return $entry;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null entrée brute */
    public static function findRaw(string $id): ?array
    {
        foreach (self::rawEntries() as $entry) {
            if ((string) ($entry['id'] ?? '') === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Texte de la fiche créée dans le référentiel (langue de la communauté) : description, mention de jeu,
     * organisme et standard de référence, prérequis, validité, sources.
     *
     * @param array<string, mixed> $entry entrée brute
     */
    public static function definitionDescription(array $entry, string $lang): string
    {
        $l = self::localize($entry, $lang);
        $en = self::lang($lang) === 'en';
        $lines = [$l['description'], '', self::disclaimer($lang, (string) ($entry['pack'] ?? ''))];
        if ($l['official_name'] !== '' && $l['official_name'] !== $l['name']) {
            $lines[] = ($en ? 'Official title: ' : 'Intitulé officiel : ') . $l['official_name'];
        }
        if ($l['body'] !== '') {
            $lines[] = ($en ? 'Reference body: ' : 'Organisme de référence : ') . $l['body'];
        }
        if ($l['reference'] !== '') {
            $lines[] = ($en ? 'Public reference: ' : 'Référence publique : ') . $l['reference'];
        }
        if ($l['prerequisites'] !== []) {
            $lines[] = ($en ? 'Prerequisites: ' : 'Prérequis : ') . implode(' ; ', $l['prerequisites']);
        }
        if ($l['validity'] !== '') {
            $lines[] = ($en ? 'Validity: ' : 'Validité : ') . $l['validity'];
        }
        if ($l['sources'] !== []) {
            $lines[] = ($en ? 'Sources: ' : 'Sources : ') . implode(' ; ', array_column($l['sources'], 'url'));
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Contrôle d’une entrée brute (tests et ajout d’un pack) : liste des problèmes, vide si l’entrée est valide.
     *
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    public static function problems(array $entry): array
    {
        $p = [];
        if (preg_match('/^[a-z]{2,4}-[a-z0-9-]{2,60}$/', (string) ($entry['id'] ?? '')) !== 1) {
            $p[] = 'id invalide';
        }
        if (preg_match('/^[A-Z]{2,5}-[A-Z0-9-]{2,30}$/', (string) ($entry['code'] ?? '')) !== 1) {
            $p[] = 'code invalide';
        }
        if (!isset(self::CATEGORIES[(string) ($entry['category'] ?? '')])) {
            $p[] = 'catégorie inconnue';
        }
        if (mb_strlen(trim((string) ($entry['official_name'] ?? ''))) > 200) {
            $p[] = 'intitulé officiel trop long';
        }
        if (isset($entry['issuer']) && (!is_array($entry['issuer']) || trim((string) ($entry['issuer']['name'] ?? '')) === ''
            || mb_strlen((string) $entry['issuer']['name']) > 200 || mb_strlen((string) ($entry['issuer']['short_name'] ?? '')) > 80)) {
            $p[] = 'émetteur invalide';
        }
        $short = trim((string) ($entry['short_name'] ?? ''));
        if ($short === '' || mb_strlen($short) > 20) {
            $p[] = 'abrégé vide ou trop long';
        }
        foreach (['name' => 150, 'description' => 700, 'body' => 200, 'reference' => 300] as $field => $max) {
            foreach (['fr', 'en'] as $l) {
                $v = trim((string) ($entry[$field][$l] ?? ''));
                if ($v === '' || mb_strlen($v) > $max) {
                    $p[] = $field . '.' . $l . ' vide ou trop long';
                }
            }
        }
        foreach (['fr', 'en'] as $l) {
            if (mb_strlen(trim((string) ($entry['validity'][$l] ?? ''))) > 300) {
                $p[] = 'validity.' . $l . ' trop long';
            }
        }
        $fr = $entry['prerequisites']['fr'] ?? null;
        $en = $entry['prerequisites']['en'] ?? null;
        if (!is_array($fr) || !is_array($en) || count($fr) !== count($en)) {
            $p[] = 'prérequis : listes fr/en de longueurs différentes';
        }
        // Prérequis structurés pré-calculés (tools/qualifications/detect-prerequisites.php), relus dans
        // docs/qualifications/prerequis-detectes.md.
        foreach (is_array($entry['prerequisites_detected'] ?? null) ? $entry['prerequisites_detected'] : (isset($entry['prerequisites_detected']) ? [null] : []) as $d) {
            $kind = is_array($d) ? (string) ($d['kind'] ?? '') : '';
            $ok = match ($kind) {
                'metric' => \App\Support\QualificationCriteriaCatalog::isMetric((string) ($d['metric'] ?? '')) && (float) ($d['min'] ?? 0) > 0,
                'attestation' => \App\Support\QualificationCriteriaCatalog::isAttestation((string) ($d['key'] ?? '')),
                'grade' => preg_match('/^O[RF]-\d{1,2}$/', (string) ($d['nato'] ?? '')) === 1,
                'qualification' => preg_match('/^[a-z]{2,4}-[a-z0-9-]{2,60}$/', (string) ($d['ref'] ?? '')) === 1 && ($d['ref'] ?? '') !== ($entry['id'] ?? ''),
                default => false,
            };
            if (!$ok) {
                $p[] = 'prérequis détecté invalide';
            }
        }
        $months = $entry['validity_months'] ?? null;
        if ($months !== null && (!is_int($months) || $months < 1 || $months > 120)) {
            $p[] = 'validity_months invalide';
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
