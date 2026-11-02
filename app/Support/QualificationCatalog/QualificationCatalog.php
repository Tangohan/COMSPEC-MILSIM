<?php

declare(strict_types=1);

namespace App\Support\QualificationCatalog;

/**
 * Catalogue de qualifications de référence que chaque communauté peut importer dans son référentiel :
 * fichiers versionnés config/qualification_catalog/<pack>.json (aujourd’hui « nato » : qualifications inspirées
 * des standards et cours OTAN publics). Chaque entrée est bilingue (fr / en) et servie dans la langue du lecteur.
 * Ce sont des qualifications de jeu : l’import ajoute toujours la mention « qualification de jeu inspirée du
 * standard OTAN, sans valeur réelle » (DISCLAIMER).
 *
 * Format d’un pack : { pack, label{fr,en}, issuer{name, short_name, description{fr,en}}, compiled_at,
 * sources_note{fr,en}, entries[] } ; une entrée : id, code, category, name{fr,en}, short_name, description{fr,en},
 * body{fr,en} (organisme de référence), reference{fr,en} (standard ou cours public), prerequisites{fr[],en[]},
 * validity{fr,en} (texte, vide si aucune source ouverte ne la précise), validity_months (entier ou null),
 * sources[{url,title}].
 */
final class QualificationCatalog
{
    /** Packs livrés (nom du fichier sans extension). */
    public const PACKS = ['nato'];

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
        foreach (self::PACKS as $pack) {
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

    public static function disclaimer(string $lang): string
    {
        return self::DISCLAIMER[self::lang($lang)];
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

        return [
            'id' => (string) ($entry['id'] ?? ''),
            'pack' => (string) ($entry['pack'] ?? ''),
            'code' => (string) ($entry['code'] ?? ''),
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
        $lines = [$l['description'], '', self::disclaimer($lang)];
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
