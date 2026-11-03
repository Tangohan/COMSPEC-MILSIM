<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Détection de prérequis dans le texte libre d'une qualification (description, liste des prérequis du catalogue) :
 * motifs « nombre + unité + activité » en français, anglais et allemand (« 5 sauts en chute libre », « 200 flight
 * hours », « mindestens 150 Freifallsprünge », « two years of prior service »), grade minimum (codes OTAN OR-/OF-,
 * grades américains E-n, grades nommés), épreuves sans donnée mesurée (tir, tests physiques) et phrases qui citent une
 * autre qualification ou un cours (résolues ensuite par QualificationPrerequisiteResolver).
 *
 * Pur : ni base ni session. Rien n'est appliqué ici : chaque détection porte un score de confiance (0 à 1) et sa
 * phrase source ; l'écran les propose, une personne choisit. HIGH : seuil des ajouts pré-calculés dans les catalogues.
 *
 * Une détection : kind (metric | attestation | grade | reference), confidence, source (phrase), excerpt (passage
 * reconnu) et selon le type : metric, scope, min, window_days (metric) ; key, min, label (attestation) ; nato, rank
 * (grade) ; text (reference).
 */
final class QualificationPrerequisiteExtractor
{
    public const HIGH = 0.8;

    public const ORIGIN_PREREQUISITE = 'prerequisite';
    public const ORIGIN_DESCRIPTION = 'description';

    /** Nombres écrits en toutes lettres (sans accents, minuscules). */
    private const WORDS = [
        'a' => 1, 'an' => 1, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7,
        'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'fifteen' => 15, 'twenty' => 20,
        'thirty' => 30, 'forty' => 40, 'fifty' => 50, 'hundred' => 100,
        'un' => 1, 'une' => 1, 'deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'sept' => 7, 'huit' => 8,
        'neuf' => 9, 'dix' => 10, 'douze' => 12, 'quinze' => 15, 'vingt' => 20, 'trente' => 30, 'quarante' => 40,
        'cinquante' => 50, 'cent' => 100,
        'ein' => 1, 'eine' => 1, 'einem' => 1, 'einen' => 1, 'zwei' => 2, 'drei' => 3, 'vier' => 4, 'funf' => 5,
        'sechs' => 6, 'sieben' => 7, 'acht' => 8, 'neun' => 9, 'zehn' => 10, 'zwolf' => 12, 'zwanzig' => 20,
        'dreissig' => 30, 'funfzig' => 50, 'hundert' => 100,
    ];

    /**
     * Grades nommés (sans accents, minuscules) => code OTAN. Les plus longs d'abord à la recherche (« staff sergeant »
     * avant « sergeant »). « warrant officer » est reconnu pour ne pas être lu comme « officer ».
     */
    private const RANKS = [
        // Anglais (US / GB)
        'private' => 'OR-1', 'lance corporal' => 'OR-3', 'lance bombardier' => 'OR-3', 'corporal' => 'OR-4',
        'bombardier' => 'OR-4', 'sergeant' => 'OR-5', 'staff sergeant' => 'OR-6', 'sergeant first class' => 'OR-7',
        'colour sergeant' => 'OR-7', 'color sergeant' => 'OR-7', 'master sergeant' => 'OR-8', 'first sergeant' => 'OR-8',
        'sergeant major' => 'OR-9', 'petty officer' => 'OR-5', 'chief petty officer' => 'OR-7', 'warrant officer' => '',
        'second lieutenant' => 'OF-1', 'first lieutenant' => 'OF-1', 'lieutenant' => 'OF-1', 'captain' => 'OF-2',
        'major' => 'OF-3', 'lieutenant colonel' => 'OF-4', 'colonel' => 'OF-5',
        // Français
        'soldat' => 'OR-1', 'caporal' => 'OR-3', 'caporal suppleant' => 'OR-3', 'caporal-chef' => 'OR-4', 'caporal chef' => 'OR-4',
        'sergent' => 'OR-5', 'sergent-chef' => 'OR-6', 'sergent chef' => 'OR-6', 'adjudant' => 'OR-7', 'adjudant-chef' => 'OR-8',
        'sous-lieutenant' => 'OF-1', 'capitaine' => 'OF-2', 'commandant' => 'OF-3', 'lieutenant-colonel' => 'OF-4',
        // Allemand
        'gefreiter' => 'OR-2', 'obergefreiter' => 'OR-3', 'hauptgefreiter' => 'OR-4', 'unteroffizier' => 'OR-5',
        'stabsunteroffizier' => 'OR-5', 'feldwebel' => 'OR-6', 'oberfeldwebel' => 'OR-6', 'hauptfeldwebel' => 'OR-7',
        'stabsfeldwebel' => 'OR-8', 'oberstabsfeldwebel' => 'OR-9', 'leutnant' => 'OF-1', 'oberleutnant' => 'OF-1',
        'hauptmann' => 'OF-2', 'oberstleutnant' => 'OF-4', 'oberst' => 'OF-5',
    ];

    /** Grades dont le code OTAN change au Royaume-Uni (pack gb). */
    private const RANKS_GB = ['sergeant' => 'OR-6', 'staff sergeant' => 'OR-7', 'corporal' => 'OR-4', 'caporal' => 'OR-4', 'sergent' => 'OR-6'];

    /** Nombre de sauts, d’heures, de missions : chiffres ou nombre en lettres, sans article (« un saut de nuit »). */
    private const NUM_COUNT = '(\d{1,3}(?:[ \x{202F}\x{00A0},]\d{3})+|\d+(?:[.,]\d+)?|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|fifteen|twenty|thirty|forty|fifty|hundred|deux|trois|quatre|cinq|six|sept|huit|neuf|dix|douze|quinze|vingt|trente|quarante|cinquante|cent|zwei|drei|vier|funf|sechs|sieben|acht|neun|zehn|zwolf|zwanzig|dreissig|funfzig|hundert)';

    /** Durée : l’article vaut un (« a year of service », « un an d’expérience »). */
    private const NUM = '(\d{1,3}(?:[ \x{202F}\x{00A0},]\d{3})+|\d+(?:[.,]\d+)?|a|an|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|fifteen|twenty|thirty|forty|fifty|hundred|un|une|deux|trois|quatre|cinq|six|sept|huit|neuf|dix|douze|quinze|vingt|trente|quarante|cinquante|cent|ein|eine|einem|einen|zwei|drei|vier|funf|sechs|sieben|acht|neun|zehn|zwolf|zwanzig|dreissig|funfzig|hundert)';

    /**
     * Prérequis détectés dans un texte (une phrase par élément de liste, ou un paragraphe découpé en phrases).
     *
     * @param string|list<string> $text
     * @param string $origin ORIGIN_PREREQUISITE (liste des prérequis : confiance haute) ou ORIGIN_DESCRIPTION
     * @param string $pack pack du catalogue (us, gb…) pour les grades qui en dépendent ; vide sinon
     * @return list<array<string, mixed>>
     */
    public static function extract(string|array $text, string $origin = self::ORIGIN_DESCRIPTION, string $pack = ''): array
    {
        $sentences = is_array($text) ? array_values(array_filter(array_map('strval', $text), static fn (string $s): bool => trim($s) !== '')) : self::sentences($text);
        $out = [];
        foreach ($sentences as $sentence) {
            foreach (self::fromSentence(trim($sentence), $origin, $pack) as $d) {
                $out[] = $d;
            }
        }

        return self::dedupe($out);
    }

    /** @return list<string> */
    public static function sentences(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $parts = preg_split('/(?<=[.!?;])\s+(?=[\p{Lu}\d«"(])|\n+|\s+\|\s+/u', $text) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
    }

    /** Forme comparable : minuscules, sans accents ni ligatures, apostrophes et tirets typographiques unifiés. */
    public static function fold(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['’' => "'", '‘' => "'", '–' => '-', '—' => '-', '‑' => '-', 'ß' => 'ss', 'œ' => 'oe', 'æ' => 'ae', "\u{202F}" => ' ', "\u{00A0}" => ' ']);
        // Sans l'extension intl (Normalizer) : table des lettres accentuées des langues des catalogues.
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e',
            'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ó' => 'o', 'ò' => 'o',
            'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
        ]);
        if (class_exists(\Normalizer::class)) {
            $t = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if (is_string($t)) {
                $s = preg_replace('/\p{Mn}+/u', '', $t) ?? $s;
            }
        }

        return $s;
    }

    /** @return list<array<string, mixed>> */
    private static function fromSentence(string $sentence, string $origin, string $pack): array
    {
        $f = self::fold($sentence);
        $pre = $origin === self::ORIGIN_PREREQUISITE;
        $out = [];
        $n = self::NUM_COUNT;
        $filler = '(?:\s+[\p{L}\'-]+){0,3}?';
        $add = static function (array $d) use (&$out, $sentence): void {
            $d['source'] = $sentence;
            $d['confidence'] = round(max(0.05, min(0.95, (float) $d['confidence'])), 2);
            $out[] = $d;
        };

        // Sauts : « 5 sauts », « 150 sauts en chute libre », « 50 military free-fall jumps », « 5 Freifallsprünge ».
        if (preg_match_all('/(?<![\w,.])' . $n . '(' . $filler . ')\s+(jumps?|sauts?|[\p{L}]*sprunge?n?|[\p{L}]*sprung)\b/u', $f, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) {
                $value = self::number($hit[1][0]);
                // « trois semaines de saut », « 69 Komando free fallers jump » : durée ou nom, pas un nombre de sauts.
                if ($value === null || $value < 1 || $value > 5000 || self::isDuration($hit[2][0]) || ($value > 1 && preg_match('/^(?:jump|saut)$/u', $hit[3][0]) === 1)) {
                    continue;
                }
                $around = $hit[2][0] . ' ' . $hit[3][0] . ' ' . substr($f, $hit[0][1] + strlen($hit[0][0]), 40);
                $free = preg_match('/free[- ]?fall|chute libre|halo|haho|ouverture (?:retardee|commandee)|delayed[- ]opening|freifall/u', $around) === 1
                    || preg_match('/freifall/u', $hit[3][0]) === 1;
                $static = preg_match('/static[- ]line|ouverture automatique|automatic opening|automatik|s\.o\.a|\bsoa\b/u', $around) === 1;
                $window = preg_match('/^\W*(?:\w+\W+){0,4}?(?:a|per|each) year|par an|chaque annee|pro jahr|jahrlich/u', substr($f, $hit[0][1] + strlen($hit[0][0]), 40)) === 1 ? 365 : 0;
                $conf = self::confidence($f, $hit[0][1], strlen($hit[0][0]), $pre);
                if ($free) {
                    $add(['kind' => 'attestation', 'key' => 'freefall_jumps', 'min' => $value, 'label' => 'Sauts en chute libre', 'excerpt' => $hit[0][0], 'confidence' => $conf]);
                } else {
                    $add(['kind' => 'metric', 'metric' => 'halo_jumps', 'scope' => '', 'min' => $value, 'window_days' => $window, 'excerpt' => $hit[0][0], 'confidence' => $conf, 'static_line' => $static]);
                }
            }
        }
        // « à l'origine six sauts, aujourd'hui cinq suffisent » : le nombre actuel, sans le mot « sauts » répété.
        if (preg_match('/\b(?:jumps|sauts|sprunge)\b[^.;]{0,40}?\b(?:now|today|aujourd\'hui|desormais|heute)\s+(?:only\s+)?' . $n . '\s+(?:are enough|suffice|suffisent|suffit|genugen|reichen)/u', $f, $mm, PREG_OFFSET_CAPTURE)) {
            $value = self::number($mm[1][0]);
            if ($value !== null && $value >= 1) {
                $add(['kind' => 'metric', 'metric' => 'halo_jumps', 'scope' => '', 'min' => $value, 'window_days' => 0, 'excerpt' => $mm[0][0], 'confidence' => $pre ? 0.9 : 0.8, 'static_line' => false]);
            }
        }

        // Heures de vol : « 200 heures de vol », « 500 flight hours », « 300 Flugstunden », « 50 h of flight ».
        if (preg_match_all('/(?<![\w,.])' . $n . '\s*(?:(?:h|hours?|heures?|stunden)\s+(?:de\s+|of\s+)?(?:vol|flight|flying|flugzeit)\b|(?:flight|flying)\s+hours?\b|flugstunden\b)/u', $f, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) {
                $value = self::number($hit[1][0]);
                if ($value === null || $value <= 0 || $value > 20000) {
                    continue;
                }
                $after = substr($f, $hit[0][1], strlen($hit[0][0]) + 50);
                $scope = preg_match('/helicop|helico|hubschrauber|rotary|voilure tournante/u', $after) === 1 ? 'kind:heli'
                    : (preg_match('/\b(?:aircraft|airplane|aeroplane|avion|flugzeug|fixed[- ]wing|jet)\b/u', $after) === 1 ? 'kind:plane' : '');
                $add(['kind' => 'metric', 'metric' => 'flight_hours', 'scope' => $scope, 'min' => $value, 'window_days' => 0, 'excerpt' => $hit[0][0], 'confidence' => self::confidence($f, $hit[0][1], strlen($hit[0][0]), $pre)]);
            }
        }

        // Heures de plongée : non mesurées par le jeu, attestées.
        if (preg_match_all('/(?<![\w,.])' . $n . '\s*(?:(?:diving|dive)\s+hours?|(?:h|heures?)\s+de\s+plongee|hours?\s+(?:of\s+)?(?:diving|underwater)|tauchstunden)\b/u', $f, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) {
                $value = self::number($hit[1][0]);
                if ($value !== null && $value > 0 && $value <= 20000) {
                    $add(['kind' => 'attestation', 'key' => 'dive_hours', 'min' => $value, 'label' => 'Heures de plongée', 'excerpt' => $hit[0][0], 'confidence' => self::confidence($f, $hit[0][1], strlen($hit[0][0]), $pre)]);
                }
            }
        }

        // Heures de jeu.
        if (preg_match_all('/(?<![\w,.])' . $n . '\s*(?:h|hours?|heures?)\s+(?:de\s+(?:temps\s+de\s+)?jeu|of\s+(?:play|gameplay|playtime)|played|spielzeit)\b/u', $f, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) {
                $value = self::number($hit[1][0]);
                if ($value !== null && $value > 0) {
                    $add(['kind' => 'metric', 'metric' => 'playtime_hours', 'scope' => '', 'min' => $value, 'window_days' => 0, 'excerpt' => $hit[0][0], 'confidence' => self::confidence($f, $hit[0][1], strlen($hit[0][0]), $pre)]);
                }
            }
        }

        // Missions / opérations / sorties : parties relevées par le mod (une sortie aérienne compte comme un vol).
        if (preg_match_all('/(?<![\w,.])' . $n . '((?:\s+[\p{L}\'-]+){0,2}?)\s+(missions?|operations|operations? de combat|deployments?|deploiements?|einsatzen|einsatze|sorties?)\b/u', $f, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) {
                $value = self::number($hit[1][0]);
                if ($value === null || $value < 1 || $value > 2000 || self::isDuration($hit[2][0]) || preg_match('/\b(?:special|speciales?|spezial)\b/u', $hit[2][0]) === 1) {
                    continue;
                }
                $around = substr($f, max(0, $hit[0][1] - 40), strlen($hit[0][0]) + 80);
                $flight = str_starts_with($hit[3][0], 'sortie') && preg_match('/vol|flight|aircraft|aerien|air|pilot|helico/u', $around) === 1;
                $window = preg_match('/^\W*(?:a|per|each) year|^\W*par an|^\W*pro jahr/u', substr($f, $hit[0][1] + strlen($hit[0][0]), 20)) === 1 ? 365 : 0;
                $add(['kind' => 'metric', 'metric' => $flight ? 'flight_count' : 'missions_played', 'scope' => '', 'min' => $value, 'window_days' => $flight ? 0 : $window, 'excerpt' => $hit[0][0], 'confidence' => self::confidence($f, $hit[0][1], strlen($hit[0][0]), $pre) - 0.05]);
            }
        }

        // Ancienneté : « two years of service », « au moins deux ans de service », « zwei Jahre Dienstzeit ».
        if (preg_match_all('/(?<![\w,.])' . self::NUM . '\s+(years?|yrs|ans?|annees?|jahren?|months?|mois|monaten?)\b(?:\'|\s)*(?=(.{0,45}))/u', $f, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) {
                $value = self::number($hit[1][0]);
                $unit = $hit[2][0];
                $after = $hit[3][0];
                $before = substr($f, max(0, $hit[0][1] - 25), min(25, $hit[0][1]));
                if ($value === null || $value <= 0 || $value > 40) {
                    continue;
                }
                $service = preg_match('/^(?:of\s+|d\'|de\s+)?(?:\w+\s+){0,2}?(?:service|dienstzeit|dienst\b|anciennete|seniority|experience|erfahrung|on an? |as an? |comme |in the regiment|au sein|dans l\'unite)/u', $after) === 1;
                $age = preg_match('/^(?:\w+\s+)?(?:old|au plus|or under|maximum|or younger|alt\b)|^(?:\d+\s+)?(?:jours|days|mois|months)\s+(?:au plus|or under)/u', $after) === 1
                    || preg_match('/(?:aged?|age de|ages?|under|moins de|avant|before|alter|unter)\s*$/u', $before) === 1;
                $training = preg_match('/^(?:of\s+|de\s+)?(?:\w+\s+){0,2}?(?:training|formation|ausbildung|course|cours|studies|etudes|residence|lehrgang)/u', $after) === 1;
                if (!$service || $age || $training) {
                    continue;
                }
                $days = (int) round($value * (preg_match('/^(?:month|mois|monat)/u', $unit) === 1 ? 30 : 365));
                // « deux ans de service » est net ; « un an d'expérience en montagne » l'est moins (expérience précise).
                $generic = preg_match('/^(?:of\s+|d\'|de\s+)?(?:prior\s+|previous\s+|military\s+|militaire\s+|prealable\s+)?(?:service|dienstzeit|dienst\b|anciennete|seniority)/u', $after) === 1;
                $conf = self::confidence($f, $hit[0][1], strlen($hit[0][0]), $pre) - ($generic ? 0.0 : 0.25);
                $add(['kind' => 'metric', 'metric' => 'seniority_days', 'scope' => '', 'min' => $days, 'window_days' => 0, 'excerpt' => trim($hit[1][0] . ' ' . $unit . ' ' . mb_substr($after, 0, 30)), 'confidence' => $conf]);
            }
        }

        $grade = self::grade($f, $pre, $pack);
        if ($grade !== null) {
            $add($grade);
        }

        // Épreuves sans donnée mesurée : tir, tests physiques (dans la liste des prérequis : une description raconte
        // surtout le déroulé du stage).
        if ($pre && preg_match('/\b(?:marksman(?:ship)?|expert (?:rifle|marksman|shooter)|shooting (?:test|qualification|standard)|rifle qualification|weapon(?:s)? qualification|tir qualifie|qualification (?:de|au) tir|tireur qualifie|epreuve de tir|schiessleistung|schiessnachweis)\b/u', $f, $mm, PREG_OFFSET_CAPTURE)) {
            $add(['kind' => 'attestation', 'key' => 'marksmanship', 'min' => 0, 'label' => self::label($sentence), 'excerpt' => $mm[0][0], 'confidence' => $pre ? 0.75 : 0.55]);
        }
        if (preg_match('/\b(?:fitness test|physical (?:test|assessment|screening)|fitness assessment|swim(?:ming)? test|minimum standards|march with \d|km with \d|tests? physiques?|epreuves? physiques?|test sportif|test de natation|normes minimales|marche de \d|course de \d|sporttest|sportlicher test|basic fitness test|pst\b|pcpa\b|rmft\b|royal marines fitness test)/u', $f, $mm, PREG_OFFSET_CAPTURE)) {
            $add(['kind' => 'attestation', 'key' => 'fitness_test', 'min' => 0, 'label' => self::label($sentence), 'excerpt' => $mm[0][0], 'confidence' => $pre ? 0.7 : 0.45]);
        }

        // Autre qualification ou cours cité : seules les phrases de la liste des prérequis (une description cite
        // souvent des unités ou des écoles sans en faire un prérequis).
        if ($pre) {
            $add(['kind' => 'reference', 'text' => $sentence, 'excerpt' => $sentence, 'confidence' => 0.9]);
        }

        return $out;
    }

    /**
     * Grade minimum d'une phrase : codes OTAN (OR-4 à OR-8, OF-3 et plus), grades américains (E-6 and above), grades
     * nommés (« at least the rank of Feldwebel », « Corporal to Captain »). Le plus bas cité l'emporte.
     *
     * @return array<string, mixed>|null
     */
    private static function grade(string $f, bool $pre, string $pack): ?array
    {
        $found = [];
        if (preg_match_all('/\b(or|of)-?(\d{1,2})\b/u', $f, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $num = (int) $hit[2];
                if ($num >= 1 && $num <= 10) {
                    $found[] = [strtoupper($hit[1]) . '-' . $num, strtoupper($hit[0]), 0.85];
                }
            }
        }
        if (preg_match_all('/\b([eo])-([1-9])\b/u', $f, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $num = (int) $hit[2];
                $code = $hit[1] === 'e' ? 'OR-' . $num : 'OF-' . max(1, [1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8][$num]);
                $found[] = [$code, strtoupper($hit[0]), 0.85];
            }
        }
        $ranks = self::RANKS;
        if ($pack === 'gb' || $pack === 'my') {
            $ranks = self::RANKS_GB + $ranks;
        }
        $keys = array_keys($ranks);
        usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $alt = implode('|', array_map(static fn (string $k): string => preg_quote($k, '/'), $keys));
        $signal = preg_match('/rank of|grade de|grade minimum|au moins|at least|minimum|or above|and above|or higher|et plus|ou plus|au minimum|mindestens|jusqu|up to|dienstgrad/u', $f) === 1
            // Fourchette « Corporal to Captain », « caporal à sergent », « OR4 à OR6 ».
            || preg_match('/(?:' . $alt . '|\bor-?\d|\bof-?\d|\be-\d)\s+(?:to|a|bis|-)\s+(?:' . $alt . '|or-?\d|of-?\d|e-\d)/u', $f) === 1;
        if (preg_match_all('/(?<![\p{L}-])(' . $alt . ')(?![\p{L}-])/u', $f, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) {
                $code = $ranks[$hit[1][0]];
                if ($code === '') {
                    continue;
                }
                // « Officer Cadet », « commandant de bord », « Major General » : pas un grade minimum.
                $next = substr($f, $hit[1][1] + strlen($hit[1][0]), 12);
                if (preg_match('/^\s*(?:de bord|d\'unite|general|cadet|course|school|des? |du |d\')/u', $next) === 1) {
                    continue;
                }
                $found[] = [$code, $hit[1][0], $signal ? 0.85 : 0.7];
            }
        }
        $codes = $found !== [] && preg_match('/^(?:OR|OF|E|O)-?\d/', (string) $found[0][1]) === 1;
        // Catégorie (« officier », « senior NCO ») : seulement sans code chiffré, et toujours à confirmer.
        if (!$codes && $pre && preg_match('/\b(?:cours|course|formation|training|career|carriere|cadet|eleve|selection|commission|police|technical|technician)\b/u', $f) !== 1) {
            if (preg_match('/\b(?:senior nco|snco|senior staff nco|sous-officiers? superieurs?)\b/u', $f, $mm) === 1) {
                $found[] = ['OR-6', $mm[0], 0.5];
            } elseif (preg_match('/\b(?:nco|ncos|sous-officiers?|non-commissioned)\b/u', $f, $mm) === 1) {
                $found[] = ['OR-5', $mm[0], 0.45];
            }
            if (preg_match('/(?<!warrant )(?<!petty )\b(?:officer|officier|offizier)\b/u', $f, $mm) === 1) {
                $found[] = ['OF-1', $mm[0], 0.45];
            }
        }
        if ($found === []) {
            return null;
        }
        usort($found, static fn (array $a, array $b): int => self::natoOrder($a[0]) <=> self::natoOrder($b[0]));
        [$code, $rank, $conf] = $found[0];
        // OR-1, OR-2 : tout le monde y est, ce n'est pas un grade minimum.
        if (self::natoOrder($code) <= 2) {
            return null;
        }
        // « OF-1 à OF-4, ou sous-officier supérieur » : des sous-officiers sans code sont admis aussi.
        if ($codes && str_starts_with($code, 'OF') && preg_match('/\b(?:nco|ncos|snco|sous-officiers?|unteroffizier\w*|feldwebel)\b/u', $f) === 1) {
            $conf = min($conf, 0.55);
        }
        // Militaires du rang admis aussi (« Enlisted…; officers: Leutnant to Hauptmann ») : le grade cité ne vaut que
        // pour une partie des candidats.
        if (preg_match('/\b(?:enlisted|militaires du rang|mannschaften|rank and file|junior soldiers?)\b/u', $f) === 1) {
            $conf = min($conf, 0.5);
        }
        if (!$pre) {
            // Dans une description, un grade cité est rarement une condition : seulement « au moins », « et plus »…
            if (preg_match('/au moins|at least|minimum|or above|and above|or higher|et plus|ou plus|au minimum|mindestens|required|requis|exige/u', $f) !== 1) {
                return null;
            }
            $conf = min($conf, 0.65);
        }

        return ['kind' => 'grade', 'nato' => $code, 'rank' => $rank, 'excerpt' => $rank, 'confidence' => $conf];
    }

    /** Rang comparable d'un code OTAN : OR-1 … OR-9 puis OF-1 … OF-10. */
    public static function natoOrder(string $code): int
    {
        if (preg_match('/^(OR|OF)-(\d{1,2})$/', strtoupper($code), $m) !== 1) {
            return 0;
        }

        return ($m[1] === 'OF' ? 10 : 0) + (int) $m[2];
    }

    /** Confiance d'un motif chiffré selon la source et les mots autour (« au moins », « environ », « à l'origine »). */
    private static function confidence(string $f, int $offset, int $length, bool $pre): float
    {
        $before = substr($f, max(0, $offset - 30), min(30, $offset));
        $after = substr($f, $offset + $length, 30);
        $conf = $pre ? 0.85 : 0.6;
        if (preg_match('/(?:at least|au moins|minimum|mindestens|min\.|required|requires?|exige|necessaires?|after|apres|nach|plus de|more than|ueber|uber)\s*\S*\s*$/u', $before) === 1
            || preg_match('/^\W*(?:are |is |sont |sind )?(?:enough|suffisent|suffit|required|requis|exiges?|erforderlich|minimum|au minimum)/u', $after) === 1
            // « The badge requires … and 21 free fall jumps », « students make five jumps and receive the badge ».
            || preg_match('/\b(?:requires?|suppose|exige|impose|erfordert|setzt [^.;]* voraus)\b[^.;]*$/u', substr($f, 0, $offset)) === 1
            || preg_match('/^[^.;]{0,25}\b(?:and (?:receive|earn|are awarded)|to (?:earn|qualify|be awarded)|et (?:recoivent|obtiennent)|pour obtenir)\b/u', $after) === 1) {
            $conf += $pre ? 0.05 : 0.2;
        }
        if (preg_match('/(?:about|around|approximately|roughly|environ|pres de|nearly|almost|etwa|rund|ca\.|presque)\s*$/u', $before) === 1) {
            $conf -= 0.1;
        }
        if (preg_match('/(?:originally|initially|formerly|a l\'origine|initialement|autrefois|ursprunglich|fruher)\b[^.;]*$/u', $before) === 1) {
            $conf -= 0.3;
        }

        return $conf;
    }

    /** Mots entre le nombre et l'unité qui en font une durée (« 17 weeks of mission », « trois semaines de saut »). */
    private static function isDuration(string $filler): bool
    {
        return preg_match('/\b(?:weeks?|months?|days?|years?|hours?|minutes?|semaines?|mois|jours?|ans|annees?|heures?|wochen|monate|tage|jahre|stunden)\b/u', $filler) === 1;
    }

    private static function number(string $raw): ?float
    {
        $raw = trim($raw);
        if (isset(self::WORDS[$raw])) {
            return (float) self::WORDS[$raw];
        }
        if (preg_match('/^\d{1,3}(?:[ \x{202F}\x{00A0},]\d{3})+$/u', $raw) === 1) {
            return (float) preg_replace('/\D/', '', $raw);
        }
        if (preg_match('/^\d+(?:[.,]\d+)?$/', $raw) === 1) {
            return (float) str_replace(',', '.', $raw);
        }

        return null;
    }

    private static function label(string $sentence): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $sentence) ?? $sentence);

        return mb_strlen($s) > 160 ? rtrim(mb_substr($s, 0, 157)) . '…' : $s;
    }

    /** Signature d'une détection (deux détections de même signature : la plus sûre est gardée). @param array<string, mixed> $d */
    public static function signature(array $d): string
    {
        return match ((string) $d['kind']) {
            'metric' => 'metric:' . $d['metric'] . ':' . ($d['scope'] ?? '') . ':' . (int) ($d['window_days'] ?? 0),
            'attestation' => 'attestation:' . $d['key'] . (in_array($d['key'], ['freefall_jumps', 'static_line_jumps', 'dive_hours'], true) ? '' : ':' . substr(sha1(self::fold((string) ($d['label'] ?? ''))), 0, 8)),
            'grade' => 'grade',
            default => 'reference:' . substr(sha1(self::fold((string) ($d['text'] ?? ''))), 0, 10),
        };
    }

    /**
     * @param list<array<string, mixed>> $list
     * @return list<array<string, mixed>>
     */
    private static function dedupe(array $list): array
    {
        $best = [];
        foreach ($list as $d) {
            $sig = self::signature($d);
            $cur = $best[$sig] ?? null;
            // Même condition citée deux fois : la plus sûre, à égalité la plus basse pour un grade, la dernière citée sinon
            // (« à l'origine six sauts, aujourd'hui cinq »).
            if ($cur === null || $d['confidence'] > $cur['confidence']
                || ($d['confidence'] === $cur['confidence'] && ($sig === 'grade' ? self::natoOrder((string) $d['nato']) < self::natoOrder((string) $cur['nato']) : true))) {
                $best[$sig] = $d;
            }
        }

        return array_values($best);
    }
}
