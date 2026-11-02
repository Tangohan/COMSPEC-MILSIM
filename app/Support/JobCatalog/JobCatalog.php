<?php

declare(strict_types=1);

namespace App\Support\JobCatalog;

use App\Services\Personnel\JobRoleEnrichmentService;
use App\Support\JobRoleIconLibrary;

/**
 * Catalogue d’emplois de référence (inspiration à la création d’un emploi) : fichiers versionnés
 * config/job_catalog/<pack>.json, un par pays ou par armée. Chaque entrée est bilingue (fr / en) et servie dans la
 * langue du lecteur, sans passer par le dictionnaire du site. Ajouter un pays = déposer le fichier, l’inscrire dans
 * PACKS et donner son nom dans COUNTRIES (et, si besoin, les libellés de ses armées dans BRANCH_LABELS).
 *
 * Format d’un pack : { pack, country, official_lang, label{fr,en}, compiled_at, sources_note{fr,en}, entries[] } ;
 * une entrée : id, branch, command, formation, unit{fr,en}, type, code, code_system, name{fr,en}, abbreviation,
 * description{fr,en}, qualifications{fr[],en[]}, billets{fr[],en[]}, icon, tags{fr[],en[]}, sources[{url,title}],
 * qualification_refs[] (facultatif : ids du catalogue de qualifications, App\Support\QualificationCatalog, des brevets
 * cités dans qualifications), reference_keys[] (alignée sur qualifications : pour chaque brevet, formation ou doctrine
 * citée, la clé stable de l’élément de référence qu’elle désigne, « qualification:<id> » du catalogue de qualifications
 * ou « doctrine:<clé> » du référentiel doctrinal, App\Services\Doctrine\DoctrineLibraryCatalog).
 * official_lang : langue des appellations officielles du pays (« en » par défaut ; « de » : name porte aussi une clé de ;
 * « zh » : clé zh en caractères simplifiés ; « ru » : clé ru en cyrillique) ; un nom officiel en alphabet non latin a sa
 * romanisation sous la clé <langue>-Latn (« zh-Latn » : pinyin avec tons, « ru-Latn » : translittération usuelle), cherchée
 * aussi sans accents ni tons et d’un seul tenant (latinForms) ; formation : unité courte
 * (« 1er RPIMa », « CPA 10 »), facultative, proposée comme filtre sous le commandement.
 */
final class JobCatalog
{
    /** Packs livrés (nom du fichier sans extension). */
    public const PACKS = ['us_sof', 'fr_sof', 'de_sof', 'cn_sof', 'ru_sof', 'my_sof', 'nato'];

    public const TYPES = ['operator', 'command', 'support', 'aviation'];
    public const BRANCHES = ['army', 'navy', 'air_force', 'marine_corps', 'gendarmerie', 'joint'];
    public const CODE_SYSTEMS = ['', 'MOS', 'WO_MOS', 'AOC', 'AFSC', 'NEC', 'RATING', 'DESIGNATOR', 'SPECIALITE'];

    /** Filtres exacts de la recherche, dans l’ordre de la fenêtre (pays → armée → commandement → unité → type). */
    public const FILTERS = ['country', 'branch', 'command', 'formation', 'type', 'pack'];

    /** Pays des packs, dans l’ordre d’affichage des catégories (code ISO 3166-1 alpha-2 ; « NATO » pour l’OTAN). */
    public const COUNTRIES = [
        'US' => ['fr' => 'États-Unis', 'en' => 'United States'],
        'FR' => ['fr' => 'France', 'en' => 'France'],
        'DE' => ['fr' => 'Allemagne', 'en' => 'Germany'],
        'CN' => ['fr' => 'Chine', 'en' => 'China'],
        'RU' => ['fr' => 'Russie', 'en' => 'Russia'],
        'MY' => ['fr' => 'Malaisie', 'en' => 'Malaysia'],
        // Fonctions interalliées standardisées (pas un pays) : code « NATO », emblème dessiné en CSS.
        'NATO' => ['fr' => 'OTAN', 'en' => 'NATO'],
    ];

    /** Armées d’un pays ; une armée absente reprend le libellé générique de LABELS['branch']. */
    private const BRANCH_LABELS = [
        'US' => [
            'army' => ['fr' => 'US Army (armée de terre)', 'en' => 'US Army'],
            'navy' => ['fr' => 'US Navy (marine)', 'en' => 'US Navy'],
            'air_force' => ['fr' => 'US Air Force (armée de l’air)', 'en' => 'US Air Force'],
            'marine_corps' => ['fr' => 'US Marine Corps', 'en' => 'US Marine Corps'],
            'joint' => ['fr' => 'Interarmées (USSOCOM)', 'en' => 'Joint (USSOCOM)'],
        ],
        'FR' => [
            'army' => ['fr' => 'Armée de Terre', 'en' => 'French Army'],
            'navy' => ['fr' => 'Marine nationale', 'en' => 'French Navy'],
            'air_force' => ['fr' => 'Armée de l’Air et de l’Espace', 'en' => 'French Air and Space Force'],
            'gendarmerie' => ['fr' => 'Gendarmerie nationale', 'en' => 'French Gendarmerie'],
            'joint' => ['fr' => 'Interarmées (COS)', 'en' => 'Joint (COS)'],
        ],
        // Nom allemand officiel de l’armée gardé dans les deux langues.
        'DE' => [
            'army' => ['fr' => 'Heer (armée de terre)', 'en' => 'German Army (Heer)'],
            'navy' => ['fr' => 'Deutsche Marine (marine)', 'en' => 'German Navy (Deutsche Marine)'],
            'air_force' => ['fr' => 'Luftwaffe (armée de l’air)', 'en' => 'German Air Force (Luftwaffe)'],
            'joint' => ['fr' => 'Interarmées (Bundeswehr)', 'en' => 'Joint (Bundeswehr)'],
        ],
        // Nom chinois officiel de l’armée gardé dans les deux langues ; la Police armée du peuple est une force à part.
        'CN' => [
            'army' => ['fr' => 'Armée de terre de l’APL (解放军陆军)', 'en' => 'PLA Army (解放军陆军)'],
            'navy' => ['fr' => 'Marine de l’APL (解放军海军)', 'en' => 'PLA Navy (解放军海军)'],
            'marine_corps' => ['fr' => 'Corps des Marines de l’APL (海军陆战队)', 'en' => 'PLA Navy Marine Corps (海军陆战队)'],
            'air_force' => ['fr' => 'Force aérienne de l’APL (解放军空军)', 'en' => 'PLA Air Force (解放军空军)'],
            'gendarmerie' => ['fr' => 'Police armée du peuple (武警部队)', 'en' => 'People’s Armed Police (武警部队)'],
        ],
        // Sigles russes translittérés ; la sécurité intérieure (Rosgvardia, FSB) est hors des forces armées.
        'RU' => [
            'army' => ['fr' => 'Forces terrestres et VDV (troupes aéroportées)', 'en' => 'Ground Forces and VDV (Airborne Forces)'],
            'navy' => ['fr' => 'VMF (marine)', 'en' => 'Russian Navy (VMF)'],
            'air_force' => ['fr' => 'VKS (forces aérospatiales)', 'en' => 'Aerospace Forces (VKS)'],
            'marine_corps' => ['fr' => 'Morskaya pekhota (infanterie de marine)', 'en' => 'Naval Infantry (Morskaya pekhota)'],
            'gendarmerie' => ['fr' => 'Sécurité intérieure (Rosgvardia, FSB), hors armées', 'en' => 'Internal security (Rosgvardia, FSB), not armed forces'],
            'joint' => ['fr' => 'Interarmées (KSSO, état-major général)', 'en' => 'Joint (KSSO, General Staff)'],
        ],
        // Nom malais officiel de l’armée gardé dans les deux langues ; la police (PDRM) est hors des forces armées.
        'MY' => [
            'army' => ['fr' => 'Tentera Darat Malaysia (armée de terre)', 'en' => 'Malaysian Army (Tentera Darat Malaysia)'],
            'navy' => ['fr' => 'Tentera Laut Diraja Malaysia (marine, TLDM)', 'en' => 'Royal Malaysian Navy (Tentera Laut Diraja Malaysia)'],
            'air_force' => ['fr' => 'Tentera Udara Diraja Malaysia (armée de l’air, TUDM)', 'en' => 'Royal Malaysian Air Force (Tentera Udara Diraja Malaysia)'],
            'gendarmerie' => ['fr' => 'Polis Diraja Malaysia (PDRM), police, hors armées', 'en' => 'Royal Malaysia Police (Polis Diraja Malaysia), not armed forces'],
            'joint' => ['fr' => 'Interarmées (Angkatan Tentera Malaysia)', 'en' => 'Joint (Malaysian Armed Forces)'],
        ],
        'NATO' => [
            'army' => ['fr' => 'Composante terrestre (LCC)', 'en' => 'Land component (LCC)'],
            'navy' => ['fr' => 'Composante maritime (MCC)', 'en' => 'Maritime component (MCC)'],
            'air_force' => ['fr' => 'Composante aérienne (ACC)', 'en' => 'Air component (ACC)'],
            'joint' => ['fr' => 'Interarmées et interalliés', 'en' => 'Joint and combined'],
        ],
    ];

    /** Translittération latine usuelle du russe (recherche ; ё et е s’écrivent « e »). */
    private const CYRILLIC = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y',
        'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
        'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    /** Libellés des filtres, contenus du catalogue (langue du lecteur). */
    private const LABELS = [
        'type' => [
            'operator' => ['fr' => 'Opérateur', 'en' => 'Operator'],
            'command' => ['fr' => 'Commandement et encadrement', 'en' => 'Command and leadership'],
            'support' => ['fr' => 'Soutien', 'en' => 'Support'],
            'aviation' => ['fr' => 'Aviation', 'en' => 'Aviation'],
        ],
        'branch' => [
            'army' => ['fr' => 'Armées de terre', 'en' => 'Armies'],
            'navy' => ['fr' => 'Marines', 'en' => 'Navies'],
            'air_force' => ['fr' => 'Armées de l’air', 'en' => 'Air forces'],
            'marine_corps' => ['fr' => 'Infanterie de marine (Marine Corps)', 'en' => 'Marine Corps'],
            'gendarmerie' => ['fr' => 'Gendarmerie', 'en' => 'Gendarmerie'],
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
            'SPECIALITE' => ['fr' => 'Spécialité', 'en' => 'French specialty'],
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
        \App\Support\QualificationCatalog\QualificationCatalogExplorer::reset();
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
                    $out[] = $entry + ['pack' => $pack, 'country' => (string) ($data['country'] ?? ''), 'official_lang' => self::officialLang((string) ($data['official_lang'] ?? 'en'))];
                }
            }
        }

        return $out;
    }

    public static function lang(string $lang): string
    {
        return str_starts_with(strtolower($lang), 'en') ? 'en' : 'fr';
    }

    /** Langue des appellations officielles d’un pack (code à deux lettres : fr, en, de…). */
    public static function officialLang(string $lang): string
    {
        $lang = strtolower(substr(trim($lang), 0, 2));

        return preg_match('/^[a-z]{2}$/', $lang) === 1 ? $lang : 'en';
    }

    /** @param mixed $pair {fr, en} (et, pour le nom officiel, la langue du pays) */
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

    /** Armée d’un pays (« Marine nationale », « US Navy ») ; libellé générique pour un pays sans libellé propre. */
    public static function branchLabel(string $country, string $branch, string $lang): string
    {
        return self::BRANCH_LABELS[$country][$branch][self::lang($lang)] ?? self::label('branch', $branch, $lang);
    }

    public static function countryLabel(string $country, string $lang): string
    {
        return self::COUNTRIES[$country][self::lang($lang)] ?? $country;
    }

    /** @return list<array{code: string, label: string, count: int}> pays livrés, dans l’ordre de COUNTRIES */
    public static function countries(string $lang): array
    {
        $counts = [];
        foreach (self::rawEntries() as $entry) {
            $c = (string) ($entry['country'] ?? '');
            $counts[$c] = ($counts[$c] ?? 0) + 1;
        }
        $order = array_flip(array_keys(self::COUNTRIES));
        uksort($counts, static fn (string $a, string $b): int => [$order[$a] ?? 99, $a] <=> [$order[$b] ?? 99, $b]);
        $out = [];
        foreach ($counts as $code => $count) {
            if ($code !== '') {
                $out[] = ['code' => (string) $code, 'label' => self::countryLabel((string) $code, $lang), 'count' => $count];
            }
        }

        return $out;
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
        $other = $lang === 'en' ? 'fr' : 'en';
        $country = (string) ($entry['country'] ?? '');
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
            'country' => $country,
            'country_label' => self::countryLabel($country, $lang),
            'branch' => $branch,
            'branch_label' => self::branchLabel($country, $branch, $lang),
            'command' => (string) ($entry['command'] ?? ''),
            'formation' => trim((string) ($entry['formation'] ?? '')),
            'unit' => self::pick($entry['unit'] ?? '', $lang),
            'type' => $type,
            'type_label' => self::label('type', $type, $lang),
            'code' => (string) ($entry['code'] ?? ''),
            'code_system' => (string) ($entry['code_system'] ?? ''),
            'code_system_label' => self::label('code_system', (string) ($entry['code_system'] ?? ''), $lang),
            'name' => self::pick($entry['name'] ?? '', $lang),
            'official_name' => self::pick($entry['name'] ?? '', (string) ($entry['official_lang'] ?? 'en')),
            'official_lang' => (string) ($entry['official_lang'] ?? 'en'),
            'names' => self::names($entry, $lang),
            // Recherche seulement (pas affiché) : nom, unité, brevets et étiquettes dans l’autre langue.
            'alt' => implode(' ', array_merge([self::pick($entry['name'] ?? '', $other), self::pick($entry['unit'] ?? '', $other)], self::pickList($entry['qualifications'] ?? [], $other), self::pickList($entry['tags'] ?? [], $other))),
            'abbreviation' => (string) ($entry['abbreviation'] ?? ''),
            'description' => self::pick($entry['description'] ?? '', $lang),
            'qualifications' => self::pickList($entry['qualifications'] ?? [], $lang),
            'billets' => self::pickList($entry['billets'] ?? [], $lang),
            'icon' => JobRoleIconLibrary::has($icon) ? $icon : '',
            'tags' => array_slice(self::pickList($entry['tags'] ?? [], $lang), 0, JobRoleEnrichmentService::MAX_TAGS),
            'sources' => $sources,
            'qualification_refs' => self::qualificationRefs($entry),
            'qualification_links' => self::qualificationLinks($entry, $lang),
            'linked_qualifications' => self::linkedQualifications($entry, $lang),
            'references' => self::references($entry, $lang),
        ];
    }

    /**
     * Noms de l’emploi par langue, nom officiel (langue du pays) en premier, puis sa translittération, puis la langue du lecteur, puis
     * l’anglais, puis les autres langues livrées ; un nom identique à un précédent n’est pas répété.
     *
     * @param array<string, mixed> $entry
     * @return array<string, string> langue => nom
     */
    public static function names(array $entry, string $lang): array
    {
        $name = is_array($entry['name'] ?? null) ? $entry['name'] : [];
        // La romanisation (« zh-Latn », « ru-Latn ») suit le nom officiel qu’elle transcrit.
        $order = array_unique(array_merge([(string) ($entry['official_lang'] ?? 'en'), self::latinKey($entry), self::lang($lang), 'en'], array_map('strval', array_keys($name))));
        $out = [];
        foreach ($order as $l) {
            $value = trim((string) ($name[$l] ?? ''));
            if ($value !== '' && !in_array($value, $out, true)) {
                $out[$l] = $value;
            }
        }

        return $out;
    }

    /**
     * Ids du catalogue de qualifications cités par l’entrée : qualification_refs, puis les qualifications désignées par
     * reference_keys.
     *
     * @return list<string>
     */
    public static function qualificationRefs(array $entry): array
    {
        $refs = is_array($entry['qualification_refs'] ?? null) ? $entry['qualification_refs'] : [];
        foreach (self::referenceKeys($entry) as $key) {
            if (str_starts_with($key, 'qualification:')) {
                $refs[] = substr($key, 14);
            }
        }

        return array_values(array_unique(array_filter(array_map(static fn ($r): string => is_string($r) ? trim($r) : '', $refs), static fn (string $r): bool => $r !== '')));
    }

    /**
     * Clés des éléments de référence désignés par les brevets cités (alignées sur qualifications, '' si absente).
     *
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    public static function referenceKeys(array $entry): array
    {
        $keys = is_array($entry['reference_keys'] ?? null) ? array_values($entry['reference_keys']) : [];

        return array_map(static fn ($k): string => is_string($k) && preg_match(self::REFERENCE_KEY, $k) === 1 ? $k : '', $keys);
    }

    /** Clé stable d’un élément de référence : qualification du catalogue ou publication du référentiel doctrinal. */
    public const REFERENCE_KEY = '/^(qualification:[a-z]{2,4}-[a-z0-9-]{2,60}|doctrine:[a-z]{2,4}-[a-z0-9-]{2,80})$/';

    /**
     * Élément de référence désigné par une clé, dans la langue du lecteur, ou null s’il n’existe pas.
     *
     * @return array<string, mixed>|null
     */
    public static function resolveReference(string $key, string $lang): ?array
    {
        if (str_starts_with($key, 'qualification:')) {
            $raw = \App\Support\QualificationCatalog\QualificationCatalog::findRaw(substr($key, 14));
            if ($raw === null) {
                return null;
            }
            $q = \App\Support\QualificationCatalog\QualificationCatalog::localize($raw, $lang);

            return ['key' => $key, 'kind' => 'qualification', 'id' => $q['id'], 'code' => $q['code'], 'name' => $q['name'], 'official_name' => $q['official_name'],
                'issuer' => $q['issuer'], 'academy' => \App\Support\QualificationCatalog\QualificationCatalogExplorer::academyCourse($q['id'], $lang)];
        }
        if (str_starts_with($key, 'doctrine:')) {
            $d = \App\Services\Doctrine\DoctrineLibraryCatalog::find(substr($key, 9));
            if ($d === null) {
                return null;
            }

            return ['key' => $key, 'kind' => 'doctrine', 'id' => (string) $d['key'], 'code' => (string) $d['code'], 'name' => self::lang($lang) === 'en' ? (string) $d['title_en'] : (string) $d['title_fr'],
                'official_name' => (string) $d['title'], 'issuer' => (string) $d['issuer'], 'academy' => null];
        }

        return null;
    }

    /**
     * Pour chaque brevet cité (langue du lecteur), l’élément de référence qu’il désigne, ou null.
     *
     * @param array<string, mixed> $entry
     * @return list<array<string, mixed>|null>
     */
    public static function references(array $entry, string $lang): array
    {
        $keys = self::referenceKeys($entry);
        $out = [];
        foreach (self::pickList($entry['qualifications'] ?? [], $lang) as $i => $text) {
            $out[] = ($keys[$i] ?? '') !== '' ? self::resolveReference($keys[$i], $lang) : null;
        }

        return $out;
    }

    /**
     * Pour chaque brevet cité en texte (qualifications, langue du lecteur), la qualification liée du catalogue qu’il
     * désigne (id) ou '' : rapprochement par abrégé, intitulé officiel ou nom (QualificationCatalogExplorer::matchText),
     * limité aux qualification_refs de l’emploi.
     *
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    public static function qualificationLinks(array $entry, string $lang): array
    {
        $shown = self::pickList($entry['qualifications'] ?? [], $lang);
        // Clé stable de l’élément désigné : prioritaire sur le rapprochement par le texte.
        $keys = self::referenceKeys($entry);
        if ($keys !== []) {
            return array_map(static fn (int $i): string => str_starts_with($keys[$i] ?? '', 'qualification:') ? substr($keys[$i], 14) : '', array_keys($shown));
        }
        $fr = array_values(is_array($entry['qualifications']['fr'] ?? null) ? $entry['qualifications']['fr'] : []);
        $en = array_values(is_array($entry['qualifications']['en'] ?? null) ? $entry['qualifications']['en'] : []);
        $candidates = array_values(array_filter(array_map(static fn (string $ref): ?array => \App\Support\QualificationCatalog\QualificationCatalog::findRaw($ref), self::qualificationRefs($entry))));
        if ($candidates === []) {
            return array_fill(0, count($shown), '');
        }
        $aligned = count($fr) === count($en);
        $out = [];
        foreach ($shown as $i => $text) {
            $both = $aligned ? trim((string) ($fr[$i] ?? '')) . ' | ' . trim((string) ($en[$i] ?? '')) : $text;
            $out[] = \App\Support\QualificationCatalog\QualificationCatalogExplorer::matchText($both, $candidates)[0] ?? '';
        }

        return $out;
    }

    /**
     * Qualifications du catalogue liées à un emploi, dans la langue du lecteur (nom, intitulé officiel, émetteur,
     * niveau, cours Academy correspondant).
     *
     * @param array<string, mixed> $entry
     * @return list<array{id: string, code: string, name: string, official_name: string, issuer: string, country: string, level: string, academy: array<string, string>|null}>
     */
    public static function linkedQualifications(array $entry, string $lang): array
    {
        $out = [];
        foreach (self::qualificationRefs($entry) as $ref) {
            $raw = \App\Support\QualificationCatalog\QualificationCatalog::findRaw($ref);
            if ($raw === null) {
                continue;
            }
            $q = \App\Support\QualificationCatalog\QualificationCatalog::localize($raw, $lang);
            $level = \App\Support\QualificationCatalog\QualificationCatalogExplorer::level($raw);
            $out[] = ['id' => $q['id'], 'code' => $q['code'], 'name' => $q['name'], 'official_name' => $q['official_name'], 'issuer' => $q['issuer'], 'country' => $q['country'],
                'level' => \App\Support\QualificationCatalog\QualificationCatalogExplorer::levelLabel($level['key'], $lang),
                'academy' => \App\Support\QualificationCatalog\QualificationCatalogExplorer::academyCourse($q['id'], $lang)];
        }

        return $out;
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
            $text = strtr($text, ['ß' => 'ss', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
                // Tons du pinyin (« jūjīshǒu » → « jujishou »).
                'ā' => 'a', 'ǎ' => 'a', 'ē' => 'e', 'ě' => 'e', 'ī' => 'i', 'ǐ' => 'i', 'ì' => 'i', 'ō' => 'o', 'ǒ' => 'o', 'ò' => 'o', 'ū' => 'u', 'ǔ' => 'u', 'ǖ' => 'u', 'ǘ' => 'u', 'ǚ' => 'u', 'ǜ' => 'u',
                // Cyrillique : comme la décomposition Unicode (ё → е, й → и).
                'ё' => 'е', 'й' => 'и']);
        }

        // « ß » n’a pas de forme décomposée : « Straße » se cherche « strasse ».
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', str_replace('ß', 'ss', $text));
    }

    /** Graphie allemande sans tréma (« Fallschirmjäger » → « Fallschirmjaeger ») et russe en latin (« Спецназ » → « spetsnaz »), pour la recherche. */
    public static function transliterate(string $text): string
    {
        $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue']);

        return preg_match('/\p{Cyrillic}/u', $text) === 1 ? strtr(mb_strtolower($text, 'UTF-8'), self::CYRILLIC) : $text;
    }

    /** Texte cherché d’une entrée : noms, abrégé, code, unité, commandement, branche et étiquettes dans les deux langues. */
    public static function haystack(array $entry): string
    {
        $parts = [(string) ($entry['id'] ?? ''), (string) ($entry['abbreviation'] ?? ''), (string) ($entry['code'] ?? ''), (string) ($entry['command'] ?? ''), (string) ($entry['formation'] ?? '')];
        foreach (['name', 'unit'] as $field) {
            // Toutes les langues livrées, dont le nom officiel allemand.
            foreach (array_unique(array_merge(['fr', 'en'], is_array($entry[$field] ?? null) ? array_keys($entry[$field]) : [])) as $l) {
                $parts[] = self::pick($entry[$field] ?? '', (string) $l);
            }
        }
        foreach (['tags', 'qualifications'] as $field) {
            foreach (['fr', 'en'] as $l) {
                $parts[] = implode(' ', self::pickList($entry[$field] ?? [], $l));
            }
        }
        foreach (['fr', 'en'] as $l) {
            $parts[] = self::branchLabel((string) ($entry['country'] ?? ''), (string) ($entry['branch'] ?? ''), $l);
            $parts[] = self::label('type', (string) ($entry['type'] ?? ''), $l);
            $parts[] = self::countryLabel((string) ($entry['country'] ?? ''), $l);
        }
        $code = (string) ($entry['code'] ?? '');
        $text = implode(' ', $parts);
        $german = self::transliterate($text);

        // « 18B » se trouve aussi en tapant « 18 b » ; « Fallschirmjäger » en tapant « fallschirmjager » ou « fallschirmjaeger ».
        return ' ' . self::normalize($text) . ' ' . ($german !== $text ? self::normalize($german) . ' ' : '') . self::latinForms((string) ($entry['name'][self::latinKey($entry)] ?? '')) . strtolower(str_replace(' ', '', $code)) . ' ';
    }

    /**
     * Clé de la romanisation du nom officiel : « <langue>-Latn » (« zh-Latn » pour le pinyin, « ru-Latn » pour le russe).
     *
     * @param array<string, mixed> $entry
     */
    public static function latinKey(array $entry): string
    {
        return (string) ($entry['official_lang'] ?? 'en') . '-Latn';
    }

    /**
     * Romanisation (« zh-Latn », « ru-Latn ») cherchée comme on la tape au clavier : sans accents ni tons (la normalisation
     * les retire), aussi d’un seul tenant (« tezhongzuozhan », « boevoyplovets ») et avec « v » pour « ü » du pinyin (« lvzhang »).
     */
    public static function latinForms(string $latin): string
    {
        if (trim($latin) === '') {
            return '';
        }
        $flat = self::normalize($latin);
        $forms = [str_replace(' ', '', $flat)];
        if (preg_match('/[üǖǘǚǜÜǕǗǙǛ]/u', $latin) === 1) {
            $v = self::normalize((string) preg_replace('/[üǖǘǚǜÜǕǗǙǛ]/u', 'v', $latin));
            $forms[] = $v;
            $forms[] = str_replace(' ', '', $v);
        }

        return implode(' ', array_unique($forms)) . ' ';
    }

    /**
     * Recherche plein texte tous pays (tous les mots, sans accents ni casse ; noms, unités, brevets et étiquettes dans
     * les deux langues) et filtres exacts combinables. Le nom et l’abrégé exacts passent en tête.
     *
     * @param array{country?: string, branch?: string, command?: string, formation?: string, type?: string, pack?: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function search(string $query, array $filters, string $lang): array
    {
        $words = array_values(array_filter(explode(' ', self::normalize($query)), static fn (string $w): bool => $w !== ''));
        $hits = [];
        foreach (self::rawEntries() as $position => $entry) {
            foreach (self::FILTERS as $f) {
                $want = trim((string) ($filters[$f] ?? ''));
                if ($want !== '' && (string) ($entry[$f] ?? '') !== $want) {
                    continue 2;
                }
            }
            $hay = self::haystack($entry);
            $name = ' ' . self::normalize(self::pick($entry['name'] ?? '', self::lang($lang))) . ' ';
            $score = 0;
            foreach ($words as $typed) {
                // Mot tapé en cyrillique : sa translittération compte aussi (« спецназ » trouve l’étiquette « Spetsnaz »).
                $word = str_contains($hay, $typed) ? $typed : trim(self::normalize(self::transliterate($typed)));
                if ($word === '' || !str_contains($hay, $word)) {
                    continue 2;
                }
                $score += (str_contains($hay, ' ' . $word . ' ') ? 2 : 1) + (str_contains($name, ' ' . $word) ? 1 : 0);
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

    /**
     * Valeurs des filtres. Une armée présente dans un seul pays prend le libellé de ce pays (« Marine nationale »),
     * sinon le libellé générique (« Marines »).
     *
     * @param array<string, string> $filters filtres déjà choisis (ex. ['country' => 'FR']) : seules les valeurs encore possibles
     * @return array{country: array<string, string>, branch: array<string, string>, command: array<string, string>, formation: array<string, string>, type: array<string, string>, pack: array<string, string>}
     */
    public static function facets(string $lang, array $filters = []): array
    {
        $out = ['country' => [], 'branch' => [], 'command' => [], 'formation' => [], 'type' => [], 'pack' => []];
        $branchCountries = [];
        foreach (self::rawEntries() as $entry) {
            foreach (self::FILTERS as $f) {
                $want = trim((string) ($filters[$f] ?? ''));
                if ($want !== '' && (string) ($entry[$f] ?? '') !== $want) {
                    continue 2;
                }
            }
            $country = (string) ($entry['country'] ?? '');
            $branch = (string) ($entry['branch'] ?? '');
            $type = (string) ($entry['type'] ?? '');
            $command = (string) ($entry['command'] ?? '');
            $formation = trim((string) ($entry['formation'] ?? ''));
            if ($country !== '') {
                $out['country'][$country] = self::countryLabel($country, $lang);
            }
            if ($branch !== '') {
                $branchCountries[$branch][$country] = true;
            }
            if ($type !== '') {
                $out['type'][$type] = self::label('type', $type, $lang);
            }
            if ($command !== '') {
                $out['command'][$command] = $command;
            }
            if ($formation !== '') {
                $out['formation'][$formation] = $formation;
            }
        }
        foreach ($branchCountries as $branch => $countries) {
            $out['branch'][$branch] = count($countries) === 1 ? self::branchLabel((string) array_key_first($countries), $branch, $lang) : self::label('branch', $branch, $lang);
        }
        foreach (self::packs() as $pack => $data) {
            $out['pack'][$pack] = self::pick($data['label'] ?? $pack, self::lang($lang));
        }
        $countryOrder = array_flip(array_keys(self::COUNTRIES));
        uksort($out['country'], static fn (string $a, string $b): int => ($countryOrder[$a] ?? 99) <=> ($countryOrder[$b] ?? 99));
        ksort($out['command']);
        ksort($out['formation'], SORT_NATURAL | SORT_FLAG_CASE);
        $order = array_flip(array_merge(self::BRANCHES, self::TYPES));
        foreach (['branch', 'type'] as $k) {
            uksort($out[$k], static fn (string $a, string $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
        }

        return $out;
    }

    /** @return list<array{pack: string, label: string, country: string, country_label: string, compiled_at: string, sources_note: string, count: int}> */
    public static function packSummaries(string $lang): array
    {
        $out = [];
        foreach (self::packs() as $pack => $data) {
            $out[] = [
                'pack' => $pack,
                'label' => self::pick($data['label'] ?? $pack, self::lang($lang)),
                'country' => (string) ($data['country'] ?? ''),
                'country_label' => self::countryLabel((string) ($data['country'] ?? ''), $lang),
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
        // Préfixe : code pays en minuscules (« us- », « fr- ») ou « nato- » pour les fonctions interalliées.
        if (preg_match('/^[a-z]{2,4}-[a-z0-9-]{2,60}$/', $id) !== 1) {
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
        if (mb_strlen(trim((string) ($entry['formation'] ?? ''))) > 40) {
            $p[] = 'formation trop longue';
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
        $keys = $entry['reference_keys'] ?? [];
        if (!is_array($keys) || !array_is_list($keys) || count($keys) !== count(is_array($entry['qualifications']['fr'] ?? null) ? $entry['qualifications']['fr'] : [])
            || array_filter($keys, static fn ($k): bool => !is_string($k) || preg_match(self::REFERENCE_KEY, $k) !== 1) !== []) {
            $p[] = 'reference_keys invalides';
        }
        $refs = $entry['qualification_refs'] ?? [];
        if (!is_array($refs) || !array_is_list($refs) || count($refs) > 12
            || array_filter($refs, static fn ($r): bool => !is_string($r) || preg_match('/^[a-z]{2,4}-[a-z0-9-]{2,60}$/', $r) !== 1) !== []) {
            $p[] = 'qualification_refs invalides';
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
