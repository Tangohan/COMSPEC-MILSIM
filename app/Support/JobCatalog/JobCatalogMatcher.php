<?php

declare(strict_types=1);

namespace App\Support\JobCatalog;

/**
 * Rapproche un emploi saisi par la communauté (nom, abrégé, code, unité) des emplois du référentiel (JobCatalog) : à la
 * création (« Hop hop ! Ça existe dans notre référentiel ») et sur les emplois déjà créés (note orange dans la liste).
 *
 * Détection large : toutes les langues du référentiel (fr, en, langue officielle et sa romanisation), sans accents ni
 * casse, au singulier, mots vides retirés, familles de synonymes (« médecin », « infirmier », « medic », « corpsman »,
 * « 18D » → même famille), mots proches (une ou deux lettres d'écart), abrégé et code (MOS, AFSC…) exacts. Le pays compte :
 * la nation lue dans le nom ou l'unité (« SAS », « KSK », « spetsnaz », « RPIMa », « Green Beret »…) puis le pays de la
 * communauté départagent les emplois proches, et les mots de l'unité liée rapprochent les emplois de la même formation.
 *
 * Score de 0 à 100 ; SUGGEST pour proposer à la création, NOTE pour la note des emplois existants (moins de bruit).
 */
final class JobCatalogMatcher
{
    public const SUGGEST = 58;
    public const NOTE = 70;

    /** Mots sans valeur pour reconnaître un emploi (le référentiel entier est « forces spéciales »). */
    private const STOP = [
        'de', 'du', 'des', 'la', 'le', 'les', 'l', 'd', 'un', 'une', 'et', 'en', 'au', 'aux', 'a', 'pour', 'par', 'sur', 'dans',
        'the', 'of', 'and', 'for', 'in', 'on', 'to', 'at', 'der', 'die', 'das', 'und', 'fur', 'fuer', 'im',
        'special', 'speciale', 'speciales', 'speciaux', 'force', 'forces', 'sof', 'spec', 'spezialkrafte', 'spezialkraefte',
        'officer', 'officier', 'sous', 'nco', 'man', 'person', 'personnel', 'membre', 'member', 'n',
    ];

    /**
     * Familles de synonymes (mots déjà normalisés, au singulier). Un mot de la famille vaut la famille.
     *
     * @var array<string, list<string>>
     */
    private const FAMILIES = [
        'med' => ['medic', 'medecin', 'infirmier', 'infirmiere', 'sanitaire', 'sante', 'corpsman', 'secouriste', 'paramedic', 'paramedical', 'surgeon', 'chirurgien', 'doc', 'doctor', 'docteur', 'medical', 'medicale', 'medizinisch', 'sanitaeter', 'sanitater', 'arzt', 'pj', 'pararescue', 'pararescueman', 'soignant', 'feldscher', 'vrach'],
        'sniper' => ['sniper', 'tireur', 'elite', 'marksman', 'precision', 'scharfschuetze', 'scharfschutze', 'snayper', 'tde', 'designated'],
        'comms' => ['radio', 'radiotelephoniste', 'transmission', 'transmetteur', 'transmissions', 'rto', 'communication', 'communications', 'comm', 'comms', 'com', 'telecom', 'signal', 'signaux', 'funker', 'fernmelder', 'svyaz', 'radist', 'sic', 'satcom'],
        'lead' => ['chef', 'leader', 'commandant', 'commander', 'cdr', 'cmdr', 'lead', 'responsable', 'head', 'kommandeur', 'fuehrer', 'fuhrer', 'komandir', 'director', 'directeur', 'cdt', 'cdg', 'cds', 'cde', 'chief', 'boss'],
        'deputy' => ['adjoint', 'second', 'assistant', 'deputy', '2ic', 'stellvertreter', 'zamestitel', 'xo', 'executive'],
        'team' => ['groupe', 'team', 'squad', 'patrouille', 'patrol', 'equipe', 'detachement', 'detachment', 'section', 'platoon', 'troop', 'cellule', 'element', 'binome', 'zug', 'trupp', 'gruppa', 'oda', 'odb', 'stick'],
        'ops' => ['operation', 'operations', 'ops', 's3', 'j3', 'g3', 'opex'],
        'intel' => ['renseignement', 'intelligence', 'intel', 's2', 'j2', 'g2', 'rens', 'analyste', 'analyst', 'humint', 'sigint', 'geoint', 'razvedka'],
        'recon' => ['reconnaissance', 'reco', 'recon', 'eclaireur', 'scout', 'pathfinder', 'aufklaerer', 'aufklarer', 'razvedchik', 'surveillance', 'lrrp'],
        'demo' => ['explosif', 'explosive', 'explosives', 'demolition', 'demolitions', 'demineur', 'deminage', 'eod', 'neb', 'genie', 'engineer', 'sapeur', 'sapper', 'mineur', 'pionier', 'breacher', 'breach', 'effraction', 'ied'],
        'weapons' => ['armement', 'arme', 'armes', 'weapon', 'weapons', 'armurier', 'armorer', 'armourer', 'mitrailleur', 'gunner', 'mitrailleuse', 'mortier', 'mortar', 'waffen'],
        'pilot' => ['pilote', 'pilot', 'aviateur', 'aviator', 'navigant', 'helicoptere', 'helicopter', 'helico', 'helo', 'heli', 'aviation', 'aircrew', 'flieger', 'lyotchik'],
        'jtac' => ['jtac', 'fac', 'controleur', 'controller', 'appui', 'cas', 'guidage', 'jfo', 'tacp', 'cct', 'feuerleit', 'navodchik', 'aerien'],
        'para' => ['parachutiste', 'chuteur', 'chute', 'haho', 'halo', 'freefall', 'mff', 'paratrooper', 'fallschirmjaeger', 'fallschirmjager', 'airborne', 'para', 'parachute', 'jumpmaster', 'desantnik'],
        'diver' => ['plongeur', 'nageur', 'diver', 'swimmer', 'kampfschwimmer', 'plovets', 'boevoy', 'cdo', 'subaquatique', 'underwater'],
        'operator' => ['operateur', 'operator', 'combattant', 'trooper', 'commando', 'equipier', 'soldat', 'soldier', 'kommando', 'kommandosoldat', 'boets', 'tirailleur', 'rifleman', 'grenadier'],
        'drone' => ['drone', 'drones', 'uav', 'uas', 'teleoperateur', 'rpas', 'bpla'],
        'log' => ['logistique', 'logistics', 'logisticien', 'supply', 'approvisionnement', 'intendance', 'quartermaster', 's4', 'j4', 'g4'],
        'ling' => ['interprete', 'linguiste', 'linguist', 'interpreter', 'traducteur', 'translator', 'dolmetscher', 'perevodchik'],
        'k9' => ['cynotechnique', 'cynophile', 'chien', 'k9', 'dog', 'maitre', 'handler', 'hundefuehrer'],
        'cyber' => ['cyber', 'informatique', 'it', 'numerique', 'reseau', 'network', 'computer', 'digital'],
        'psyop' => ['psyop', 'influence', 'psychologique', 'psychological', 'misop', 'miso'],
        'staff' => ['etat', 'major', 'staff', 'stab', 'shtab', 'em', 'pc', 'hq', 'qg'],
        'driver' => ['conducteur', 'chauffeur', 'driver', 'vehicule', 'vehicle', 'fahrer', 'voditel'],
        'train' => ['instructeur', 'instructor', 'formateur', 'trainer', 'moniteur', 'ausbilder', 'instruktor', 'entrainement', 'training'],
    ];

    /** Mots qui désignent une nation (dans le nom de l'emploi ou de l'unité). */
    private const NATIONS = [
        'FR' => ['francais', 'francaise', 'france', 'french', 'rpima', 'cpa', 'gign', 'cos', 'legion', 'legionnaire', 'crap', 'gcp', 'alat', 'drm', 'rdp', 'kieffer', 'jaubert', 'trepel', 'penfentenyo', 'montfort', 'hubert', 'ponchardier', 'gaulois'],
        'US' => ['us', 'usa', 'american', 'americain', 'americaine', 'green', 'beret', 'ranger', 'rangers', 'seal', 'seals', 'delta', 'marsoc', 'socom', 'devgru', 'cag', 'nightstalker', 'nightstalkers', 'pararescue', 'raider', 'raiders', 'usaf', 'usmc'],
        'GB' => ['sas', 'sbs', 'british', 'britannique', 'uk', 'royal', 'srr', 'ukfs', 'hereford', 'poole'],
        'DE' => ['ksk', 'ksm', 'bundeswehr', 'german', 'allemand', 'allemande', 'deutsch', 'deutsche', 'kampfschwimmer', 'fallschirmjaeger', 'fallschirmjager', 'gsg'],
        'RU' => ['spetsnaz', 'russe', 'russian', 'gru', 'vdv', 'vympel', 'sso', 'omon', 'fsb', 'russia', 'russie'],
        'CN' => ['pla', 'chinois', 'chinoise', 'chinese', 'apl', 'china', 'chine', 'jiaolong'],
        'MY' => ['ggk', 'paskal', 'pasukan', 'malaysian', 'malaisie', 'malaysia', 'malais', 'ssg', 'paskau', 'utk'],
        'NATO' => ['nato', 'otan', 'socc', 'sofle', 'nshq', 'combined', 'interallie', 'allied'],
    ];

    /** @var list<array<string, mixed>>|null */
    private static ?array $index = null;

    /** @var array<string, string>|null mot → famille */
    private static ?array $family = null;

    /**
     * Emplois du référentiel les plus proches, meilleur d'abord (au plus $limit, score >= $min).
     *
     * @param array{name?: string, abbreviation?: string, code?: string, unit?: string, country?: string} $job
     * @return list<array{id: string, score: int, reasons: list<string>, country: string}>
     */
    public static function match(array $job, int $limit = 3, int $min = self::SUGGEST): array
    {
        $name = trim((string) ($job['name'] ?? ''));
        $abbr = self::compact((string) ($job['abbreviation'] ?? ''));
        $code = self::compact((string) ($job['code'] ?? ''));
        if (mb_strlen($name) < 2 && $abbr === '' && $code === '') {
            return [];
        }
        $q = self::profile($name);
        $nameCompact = self::compact($name);
        $nation = self::nation($name . ' ' . (string) ($job['abbreviation'] ?? '') . ' ' . (string) ($job['unit'] ?? ''));
        $home = strtoupper(trim((string) ($job['country'] ?? '')));
        $unitWords = array_diff(self::words((string) ($job['unit'] ?? '')), self::STOP);

        $hits = [];
        foreach (self::index() as $e) {
            $score = 0.0;
            $why = [];
            if ($code !== '' && $e['code'] !== '' && $code === $e['code']) {
                $score = 96;
                $why[] = 'code';
            }
            if ($nameCompact !== '' && $e['code'] !== '' && $nameCompact === $e['code']) {
                $score = max($score, 96);
                $why[] = 'code';
            } elseif ($nameCompact !== '' && $e['abbr'] !== '' && mb_strlen($nameCompact) >= 3 && $nameCompact === $e['abbr']) {
                $score = max($score, 86);
                $why[] = 'abrégé';
            }
            foreach ($e['variants'] as $v) {
                if ($q['flat'] !== '' && $q['flat'] === $v['flat']) {
                    $score = max($score, 100);
                    $why[] = 'nom';
                    break;
                }
                $sim = self::similarity($q, $v);
                if ($sim > 0) {
                    $score = max($score, $sim);
                }
            }
            if ($score <= 0) {
                continue;
            }
            if ($abbr !== '' && $e['abbr'] !== '' && $abbr === $e['abbr']) {
                $score += 14;
                $why[] = 'abrégé';
            }
            if ($nation !== '' && $nation === $e['country']) {
                $score += 12;
                $why[] = 'nation';
            } elseif ($home !== '' && $home === $e['country']) {
                $score += 5;
                $why[] = 'pays';
            } elseif ($nation !== '' && $e['country'] !== 'NATO') {
                // Nation nommée ailleurs : l'emploi d'un autre pays recule.
                $score -= 8;
            }
            if ($unitWords !== []) {
                $common = count(array_intersect($unitWords, $e['unit_words']));
                if ($common > 0) {
                    $score += min(10, 5 * $common);
                    $why[] = 'unité';
                }
            }
            $score = (int) round(min(100, $score));
            if ($score >= $min) {
                $hits[] = ['id' => $e['id'], 'score' => $score, 'reasons' => array_values(array_unique($why)), 'country' => $e['country'], '_pos' => $e['pos']];
            }
        }
        usort($hits, static fn (array $a, array $b): int => [$b['score'], $a['_pos']] <=> [$a['score'], $b['_pos']]);

        return array_map(static function (array $h): array {
            unset($h['_pos']);

            return $h;
        }, array_slice($hits, 0, max(1, $limit)));
    }

    /** Nation reconnue dans un texte (« SAS » → GB), ou chaîne vide. */
    public static function nation(string $text): string
    {
        $words = self::words($text);
        $best = '';
        $bestCount = 0;
        foreach (self::NATIONS as $code => $keys) {
            $count = count(array_intersect($words, $keys));
            if ($count > $bestCount) {
                [$best, $bestCount] = [$code, $count];
            }
        }
        if ($best !== '') {
            return $best;
        }
        $flat = ' ' . implode(' ', $words) . ' ';
        foreach (['green beret' => 'US', 'royal marine' => 'GB', 'marine nationale' => 'FR', 'commando marine' => 'FR'] as $phrase => $code) {
            if (str_contains($flat, ' ' . $phrase . ' ')) {
                return $code;
            }
        }

        return '';
    }

    /** Mots normalisés d'un texte (sans accents ni casse, translittérés, au singulier). @return list<string> */
    public static function words(string $text): array
    {
        $text = JobCatalog::normalize(JobCatalog::transliterate($text));
        $out = [];
        foreach (preg_split('/\s+/', $text) ?: [] as $w) {
            if ($w === '') {
                continue;
            }
            // Pluriel simple (fr, en) : « transmissions » → « transmission », « medics » → « medic ».
            if (mb_strlen($w) > 4 && preg_match('/[a-z]{3}[sx]$/', $w) === 1 && !str_ends_with($w, 'ss')) {
                $w = mb_substr($w, 0, -1);
            }
            $out[] = $w;
        }

        return $out;
    }

    /**
     * Profil d'un nom : chaîne aplatie, et ensemble de « signes » (famille de synonymes, sinon le mot lui-même).
     *
     * @return array{flat: string, signs: array<string, string>, words: list<string>, wordset: array<string, true>}
     */
    private static function profile(string $name): array
    {
        $words = array_values(array_filter(self::words($name), static fn (string $w): bool => !in_array($w, self::STOP, true)));
        $signs = [];
        foreach ($words as $w) {
            $signs[self::sign($w)] ??= $w;
        }

        return ['flat' => implode(' ', $words), 'signs' => $signs, 'words' => $words, 'wordset' => array_fill_keys($words, true)];
    }

    private static function sign(string $word): string
    {
        if (self::$family === null) {
            self::$family = [];
            foreach (self::FAMILIES as $family => $members) {
                foreach ($members as $m) {
                    self::$family[$m] ??= '@' . $family;
                }
            }
        }

        return self::$family[$word] ?? $word;
    }

    /** Familles trop générales pour reconnaître un emploi à elles seules : elles comptent pour moitié. */
    private const GENERIC = ['@lead' => true, '@deputy' => true, '@team' => true, '@operator' => true, '@staff' => true];

    /**
     * Ressemblance de deux profils (0 à 92) : coefficient de Dice pondéré sur les signes. Un même mot compte plein, un
     * synonyme de la même famille un peu moins, un mot proche (une lettre d'écart, deux pour les mots longs) encore un peu
     * moins ; les familles générales (chef, adjoint, équipe…) comptent pour moitié. Un nom saisi dont tous les mots se
     * retrouvent tels quels dans l'emploi du référentiel est reconnu même si celui-ci est plus long.
     */
    private static function similarity(array $a, array $b): float
    {
        $weight = static fn (string $s): float => isset(self::GENERIC[$s]) ? 0.5 : 1.0;
        $total = 0.0;
        foreach ($b['signs'] as $t => $_) {
            $total += $weight((string) $t);
        }
        $num = 0.0;
        $literalAll = $a['words'] !== [];
        foreach ($a['signs'] as $s => $word) {
            $s = (string) $s;
            $w = $weight($s);
            $total += $w;
            $literal = isset($b['wordset'][(string) $word]);
            if (!$literal) {
                $literalAll = false;
            }
            if (isset($b['signs'][$s])) {
                $num += $w * ($literal || $s[0] !== '@' ? 1.0 : 0.85);
                continue;
            }
            if ($s[0] === '@' || mb_strlen($s) < 5) {
                continue;
            }
            foreach ($b['signs'] as $t => $__) {
                $t = (string) $t;
                if ($t[0] === '@' || $t[0] !== $s[0] || abs(strlen($t) - strlen($s)) > 2) {
                    continue;
                }
                if (levenshtein($s, $t) <= (strlen($s) >= 8 ? 2 : 1)) {
                    $num += 0.8 * $w;
                    break;
                }
            }
        }
        if ($num <= 0 || $total <= 0) {
            return 0;
        }
        $dice = min(1.0, 2 * $num / $total);
        $score = 92 * $dice;
        $score = $literalAll ? max($score, 78 + 14 * $dice) : $score;
        // Un nom fait seulement de mots généraux (« Chef », « Chef d'équipe ») ne désigne aucun emploi précis.
        foreach ($a['signs'] as $s => $_) {
            if (!isset(self::GENERIC[(string) $s])) {
                return $score;
            }
        }

        return min($score, 55);
    }

    private static function compact(string $raw): string
    {
        return str_replace(' ', '', JobCatalog::normalize($raw));
    }

    /** @return list<array<string, mixed>> */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }
        $out = [];
        foreach (JobCatalog::rawEntries() as $pos => $entry) {
            $names = is_array($entry['name'] ?? null) ? $entry['name'] : [];
            $variants = [];
            $seen = [];
            foreach ($names as $value) {
                $p = self::profile((string) $value);
                if ($p['flat'] !== '' && !isset($seen[$p['flat']])) {
                    $seen[$p['flat']] = true;
                    $variants[] = $p;
                }
            }
            $unitText = implode(' ', array_merge(
                is_array($entry['unit'] ?? null) ? array_map('strval', $entry['unit']) : [(string) ($entry['unit'] ?? '')],
                [(string) ($entry['formation'] ?? ''), (string) ($entry['command'] ?? '')]
            ));
            $out[] = [
                'id' => (string) ($entry['id'] ?? ''),
                'pos' => $pos,
                'country' => (string) ($entry['country'] ?? ''),
                'abbr' => self::compact((string) ($entry['abbreviation'] ?? '')),
                'code' => self::compact((string) ($entry['code'] ?? '')),
                'variants' => $variants,
                'unit_words' => array_values(array_diff(self::words($unitText), self::STOP)),
            ];
        }

        return self::$index = $out;
    }

    /** Tests : oublie l'index (après JobCatalog::useDirectory). */
    public static function reset(): void
    {
        self::$index = null;
    }
}
