<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseKitCatalog;

/**
 * Générateur de contenu SSE par kits : un RÉSEAU cohérent de personnes fictives (familles qui partagent nom et adresse,
 * organisations inventées avec leur hiérarchie, véhicules rattachés, papiers concordants ou volontairement discordants
 * pour les faux), et non des fiches tirées une à une.
 *
 * Pur et déterministe : mêmes paramètres, même graine, même date et mêmes valeurs déjà prises = même résultat. Rien n'est
 * écrit en base ici (SseKitContentService s'en charge). Tout est fictif : viviers de config/sse_kits, homonymes de
 * personnalités écartés (SseKitCatalog::isBlocked), téléphones à bloc « 000 », IMSI en 999 (plage hors réseau public).
 */
final class SseKitContentGenerator
{
    public const MAX_PEOPLE = 200;

    /** Âge minimal d'une personne générée (porteuse d'au moins une pièce d'identité). */
    public const MIN_AGE = 16;

    private const HOUSEHOLD_WEIGHTS = [1 => 18, 2 => 22, 3 => 22, 4 => 18, 5 => 13, 6 => 7];

    private const FORGERY = [
        'dates' => 'data_alteration',
        'number' => 'counterfeit',
        'authority' => 'counterfeit',
        'name' => 'stolen_blank',
        'photo' => 'photo_swap',
    ];

    /** Types de papiers réservés aux majeurs. */
    private const ADULT_DOCS = ['driving_license', 'vehicle_registration', 'military_id'];

    private const RELATIVES = [
        'spouse' => ['fr' => ['M' => 'son mari', 'F' => 'sa femme'], 'en' => ['M' => 'husband', 'F' => 'wife']],
        'child' => ['fr' => ['M' => 'son fils', 'F' => 'sa fille'], 'en' => ['M' => 'son', 'F' => 'daughter']],
        'parent' => ['fr' => ['M' => 'son père', 'F' => 'sa mère'], 'en' => ['M' => 'father', 'F' => 'mother']],
        'sibling' => ['fr' => ['M' => 'son frère', 'F' => 'sa sœur'], 'en' => ['M' => 'brother', 'F' => 'sister']],
    ];

    /** @var array<string, bool> */
    private array $usedNames = [];

    /** @var array<string, bool> */
    private array $usedPhones = [];

    /** @var array<string, bool> */
    private array $usedPlates = [];

    /** @var array<string, bool> */
    private array $usedNumbers = [];

    /** @var array<string, bool> */
    private array $usedStories = [];

    /**
     * @param array{kits: list<string>, themes: list<string>, people: int, civil_pct: int, link_density: int, forged_pct: int,
     *     alias_pct: int, wanted_pct: int, vehicles_pp: int, docs_pp: int, lang: string} $params paramètres normalisés
     * @param array{names?: list<string>, phones?: list<string>, plates?: list<string>} $taken valeurs déjà au registre
     *     (noms « prénom nom » en minuscules, téléphones et plaques compactés) : jamais retirées
     * @param array<string, array{number_format?: string, doc_types?: list<string>}> $formats par code pays : format de
     *     numéro et types délivrés dans la communauté (défaut : ISO2 + une lettre + six chiffres, types du kit)
     * @return array{seed: int, today: string, params: array<string, mixed>, people: list<array<string, mixed>>,
     *     links: list<array<string, mixed>>, orgs: list<array<string, mixed>>, households: int, summary: array<string, mixed>}
     */
    public function generate(array $params, int $seed, string $today, array $taken = [], array $formats = []): array
    {
        $this->usedNames = array_fill_keys(array_map('mb_strtolower', $taken['names'] ?? []), true);
        $this->usedPhones = array_fill_keys(array_map([self::class, 'compact'], $taken['phones'] ?? []), true);
        $this->usedPlates = array_fill_keys(array_map([self::class, 'compact'], $taken['plates'] ?? []), true);
        $this->usedNumbers = [];
        $this->usedStories = [];

        $lang = $params['lang'] === 'en' ? 'en' : 'fr';
        $kits = array_values(array_filter($params['kits'], static fn (string $c): bool => SseKitCatalog::kit($c) !== null));
        if ($kits === []) {
            return ['seed' => $seed, 'today' => $today, 'params' => $params, 'people' => [], 'links' => [], 'orgs' => [], 'households' => 0,
                'summary' => self::summary([], [], [], 0)];
        }
        $themes = array_values(array_filter($params['themes'], static fn (string $t): bool => $t !== SseKitCatalog::CIVIL_THEME && SseKitCatalog::theme($t) !== null));
        $total = max(1, min(self::MAX_PEOPLE, (int) $params['people']));
        $civil = $themes === [] ? $total : (int) round($total * max(0, min(100, (int) $params['civil_pct'])) / 100);
        $civil = max(0, min($total, $civil));
        $network = $total - $civil;

        $rng = new SseKitRng($seed, 'plan');
        $places = $this->places($kits, $total, $seed);

        // ── Organisations et leurs membres ───────────────────────────────────────────────────────────────────────────
        $people = [];
        $orgs = [];
        $links = [];
        if ($network > 0) {
            $perTheme = intdiv($network, count($themes));
            $extra = $network % count($themes);
            foreach ($themes as $ti => $themeKey) {
                $share = $perTheme + ($ti < $extra ? 1 : 0);
                if ($share <= 0) {
                    continue;
                }
                $theme = SseKitCatalog::theme($themeKey);
                $nOrgs = max(1, (int) round($share / 10));
                $prefer = array_values(array_intersect($kits, SseKitCatalog::kitsForTheme($themeKey)));
                for ($o = 0; $o < $nOrgs; $o++) {
                    $size = intdiv($share, $nOrgs) + ($o < $share % $nOrgs ? 1 : 0);
                    if ($size <= 0) {
                        continue;
                    }
                    $kitCode = $rng->pick($prefer !== [] ? $prefer : $kits);
                    $place = $rng->pick($places[$kitCode]);
                    [$orgName, $orgArticle] = $this->orgName($theme, SseKitCatalog::kit($kitCode), $place, $lang, $rng, array_column($orgs, 'name'));
                    $org = ['index' => count($orgs), 'theme' => $themeKey, 'kit' => $kitCode, 'place' => $place, 'name' => $orgName,
                        'article' => $orgArticle, 'members' => $size, 'leader' => null];
                    $roles = $theme['roles'];
                    $leaderRole = null;
                    $others = [];
                    foreach ($roles as $r) {
                        if (!empty($r['leader']) && $leaderRole === null) {
                            $leaderRole = $r;
                        } else {
                            $others[] = $r;
                        }
                    }
                    $leaderRole ??= $roles[0];
                    $others = $others !== [] ? $others : $roles;
                    $weights = array_map(static fn (array $r): int => max(1, (int) $r['weight']), $others);
                    for ($m = 0; $m < $size; $m++) {
                        $role = $m === 0 ? $leaderRole : $others[$rng->weighted($weights)];
                        $idx = count($people);
                        $people[] = ['index' => $idx, 'kit' => $kitCode, 'theme' => $themeKey, 'role' => $role, 'org' => $org['index'], 'place' => $place,
                            'household' => null, 'relation' => null, 'network' => true];
                        if ($m === 0) {
                            $org['leader'] = $idx;
                        }
                    }
                    $orgs[] = $org;
                }
            }
        }

        // ── Foyers civils ────────────────────────────────────────────────────────────────────────────────────────────
        $civilTheme = SseKitCatalog::theme(SseKitCatalog::CIVIL_THEME);
        $civilRole = $civilTheme['roles'][0];
        $households = [];
        $left = $civil;
        $sizes = array_keys(self::HOUSEHOLD_WEIGHTS);
        $sizeWeights = array_values(self::HOUSEHOLD_WEIGHTS);
        while ($left > 0) {
            $size = min($left, $sizes[$rng->weighted($sizeWeights)]);
            $kitCode = $kits[count($households) % count($kits)];
            $h = ['index' => count($households), 'kit' => $kitCode, 'place' => $rng->pick($places[$kitCode]), 'members' => []];
            for ($m = 0; $m < $size; $m++) {
                $idx = count($people);
                $people[] = ['index' => $idx, 'kit' => $kitCode, 'theme' => SseKitCatalog::CIVIL_THEME, 'role' => $civilRole, 'org' => null, 'place' => $h['place'],
                    'household' => $h['index'], 'relation' => $m === 0 ? 'head' : null, 'network' => false];
                $h['members'][] = $idx;
            }
            $households[] = $h;
            $left -= $size;
        }

        // Parenté entre le réseau et la population : une part des membres vit dans un foyer civil du même kit.
        $kin = (int) round($network * max(0, min(100, (int) $params['link_density'])) / 100 * 0.4);
        if ($kin > 0 && $households !== []) {
            $candidates = $rng->shuffle(array_values(array_filter(array_keys($people), static fn (int $i): bool => $people[$i]['network'])));
            foreach (array_slice($candidates, 0, $kin) as $i) {
                $same = array_values(array_filter($households, static fn (array $h): bool => $h['kit'] === $people[$i]['kit'] && count($h['members']) < 7));
                if ($same === []) {
                    continue;
                }
                $h = $rng->pick($same);
                $people[$i]['household'] = $h['index'];
                $people[$i]['relation'] = 'kin';
                $people[$i]['place'] = $h['place'];
                $households[$h['index']]['members'][] = $i;
            }
        }
        // Les autres membres du réseau vivent seuls, à leur propre adresse.
        foreach ($people as $i => $p) {
            if ($p['household'] === null) {
                $h = ['index' => count($households), 'kit' => $p['kit'], 'place' => $p['place'], 'members' => [$i]];
                $people[$i]['household'] = $h['index'];
                $people[$i]['relation'] = 'head';
                $households[] = $h;
            }
        }

        // ── Identités, foyer par foyer (noms et adresses partagés) ───────────────────────────────────────────────────
        foreach ($households as $h) {
            $this->household($people, $links, $h, $seed, $today, $lang);
        }

        // ── Alias, faux papiers, recherchés (membres de réseau seulement) ────────────────────────────────────────────
        $members = array_values(array_filter(array_keys($people), static fn (int $i): bool => $people[$i]['network']));
        $flags = new SseKitRng($seed, 'flags');
        $aliasCount = (int) round(count($members) * max(0, min(100, (int) $params['alias_pct'])) / 100);
        $forgedCount = (int) round(count($members) * max(0, min(100, (int) $params['forged_pct'])) / 100);
        $wantedCount = (int) round(count($members) * max(0, min(100, (int) $params['wanted_pct'])) / 100);
        $byThreat = $flags->shuffle($members);
        usort($byThreat, static fn (int $a, int $b): int => ((int) $people[$b]['role']['threat']) <=> ((int) $people[$a]['role']['threat']) ?: $a <=> $b);
        foreach (array_slice($byThreat, 0, $aliasCount) as $i) {
            $nicks = SseKitCatalog::kit($people[$i]['kit'])['nicknames'];
            $usedAliases = array_column($people, 'alias');
            for ($t = 0; $t < 12; $t++) {
                $nick = $flags->pick($nicks);
                if (!in_array($nick, $usedAliases, true)) {
                    $people[$i]['alias'] = $nick;
                    break;
                }
            }
        }
        foreach (array_slice($flags->shuffle($members), 0, $forgedCount) as $i) {
            $people[$i]['forged'] = $flags->pick(array_keys(self::FORGERY));
            // Faux nom : celui du papier discordant « name », repris par le récit.
            $fk = SseKitCatalog::kit($people[$i]['kit']);
            $fr = new SseKitRng($seed, 'false' . $i);
            $falseLast = self::lastForm($fk, (string) $fr->pick($fk['last']), $people[$i]['sex']);
            $people[$i]['false_name'] = $this->firstName($fk, $people[$i]['sex'], $falseLast, $fr) . ' ' . $falseLast;
        }
        foreach (array_slice($byThreat, 0, $wantedCount) as $n => $i) {
            $threat = (int) $people[$i]['role']['threat'];
            $people[$i]['wanted_level'] = !empty($people[$i]['role']['leader']) || $people[$i]['role']['category'] === 'hvt' ? 'hvt' : ($threat >= 3 ? 'recherche' : 'surveillance');
        }

        // ── Téléphones, véhicules, papiers ───────────────────────────────────────────────────────────────────────────
        foreach ($people as $i => $p) {
            $r = new SseKitRng($seed, 'phone' . $i);
            $kit = SseKitCatalog::kit($p['kit']);
            $n = $p['network'] ? $r->int(1, 2) : ($p['age'] >= 17 && $r->chance(85) ? 1 : 0);
            $people[$i]['phones'] = [];
            for ($k = 0; $k < $n; $k++) {
                $people[$i]['phones'][] = $this->phone($kit, $r);
            }
        }
        $this->vehicles($people, $households, (int) round($total * max(0, min(20, (int) $params['vehicles_pp'])) / 10), $seed, $lang);
        foreach ($people as $i => $p) {
            $people[$i]['documents'] = $this->documents($people[$i], $params, $formats[$p['kit']] ?? [], $seed, $today, $kits);
        }

        // ── Liens du réseau : hiérarchie, associés, contacts entre organisations ─────────────────────────────────────
        $net = new SseKitRng($seed, 'links');
        $density = max(0, min(100, (int) $params['link_density']));
        foreach ($orgs as $org) {
            $orgMembers = array_values(array_filter($members, static fn (int $i): bool => $people[$i]['org'] === $org['index']));
            $employer = in_array($org['theme'], ['pmc', 'front_ngo', 'corrupt_administration'], true);
            foreach ($orgMembers as $i) {
                if ($i !== $org['leader'] && $org['leader'] !== null) {
                    $links[] = ['from' => $i, 'to' => $org['leader'], 'relation' => $employer ? 'employer' : 'cell',
                        'label' => $lang === 'en' ? 'reports to' : 'rend compte à'];
                }
            }
            $extra = (int) round(count($orgMembers) * $density / 100 * 1.2);
            for ($k = 0; $k < $extra && count($orgMembers) > 2; $k++) {
                $a = $net->pick($orgMembers);
                $b = $net->pick($orgMembers);
                if ($a !== $b && $a !== $org['leader'] && $b !== $org['leader'] && !self::linked($links, $a, $b)) {
                    $links[] = ['from' => $a, 'to' => $b, 'relation' => 'associate', 'label' => $lang === 'en' ? 'associate' : 'associé'];
                }
            }
        }
        $cross = count($orgs) > 1 ? (int) round(count($orgs) * $density / 100 * 1.5) : 0;
        for ($k = 0; $k < $cross; $k++) {
            $a = $net->pick($orgs);
            $b = $net->pick($orgs);
            if ($a['index'] !== $b['index'] && $a['leader'] !== null && $b['leader'] !== null && !self::linked($links, $a['leader'], $b['leader'])) {
                $links[] = ['from' => $a['leader'], 'to' => $b['leader'], 'relation' => 'contact', 'label' => $lang === 'en' ? 'contact' : 'contact'];
            }
        }

        // ── Récits ───────────────────────────────────────────────────────────────────────────────────────────────────
        foreach ($people as $i => $p) {
            $people[$i]['story'] = $this->story($people, $links, $orgs, $i, $places, $seed, $today, $lang);
            $people[$i]['pocket'] = $this->pocket($people[$i], $seed, $lang);
        }

        // ── Forme de sortie ──────────────────────────────────────────────────────────────────────────────────────────
        $out = [];
        foreach ($people as $p) {
            $role = $p['role'];
            $org = $p['org'] !== null ? $orgs[$p['org']] : null;
            $themeName = SseKitCatalog::text(SseKitCatalog::theme($p['theme'])['name'], $lang);
            $roleLabel = $lang === 'en' ? (string) $role['label']['en'] : (string) ($p['sex'] === 'F' ? $role['label']['frF'] : $role['label']['fr']);
            $out[] = [
                'index' => $p['index'], 'kit' => $p['kit'], 'theme' => $p['theme'], 'theme_label' => $themeName,
                'role' => (string) $role['key'], 'role_label' => $roleLabel, 'category' => (string) $role['category'],
                'side' => (string) $role['side'], 'threat' => (int) $role['threat'],
                'org' => $org !== null ? (string) $org['name'] : '', 'org_index' => $p['org'], 'leader' => $org !== null && $org['leader'] === $p['index'],
                'household' => (int) $p['household'], 'seed' => $p['seed'],
                'last_name' => $p['last_name'], 'first_name' => $p['first_name'], 'sex' => $p['sex'], 'birth_date' => $p['birth_date'], 'age' => $p['age'],
                'birth_place' => $p['birth_place'], 'nationality' => $p['kit'], 'profession' => $p['profession'],
                'language' => (string) (SseKitCatalog::kit($p['kit'])['languages'][0] ?? ''),
                'alias' => (string) ($p['alias'] ?? ''), 'false_name' => (string) ($p['false_name'] ?? ''),
                'height_cm' => $p['height_cm'], 'weight_kg' => $p['weight_kg'], 'build' => $p['build'], 'hair' => $p['hair'], 'eyes' => $p['eyes'],
                'marks' => $p['marks'], 'cautions' => array_values($role['cautions']),
                'address' => $p['address'], 'zone' => $p['place'], 'phones' => $p['phones'], 'vehicles' => $p['vehicles'] ?? [],
                'documents' => $p['documents'], 'pocket' => $p['pocket'], 'wanted_level' => $p['wanted_level'] ?? null,
                'forged' => isset($p['forged']), 'story' => $p['story'], 'fictional' => true,
            ];
        }
        $orgsOut = array_map(static fn (array $o): array => ['name' => $o['name'], 'theme' => $o['theme'], 'kit' => $o['kit'], 'place' => $o['place'],
            'members' => $o['members'], 'leader' => $o['leader']], $orgs);

        return ['seed' => $seed, 'today' => $today, 'params' => $params, 'people' => $out, 'links' => $links, 'orgs' => $orgsOut,
            'households' => count($households), 'summary' => self::summary($out, $links, $orgsOut, count($households))];
    }

    /**
     * Compteurs d'un jeu généré (aperçu, journal).
     *
     * @param list<array<string, mixed>> $people
     * @param list<array<string, mixed>> $links
     * @param list<array<string, mixed>> $orgs
     * @return array<string, mixed>
     */
    public static function summary(array $people, array $links, array $orgs, int $households): array
    {
        $s = ['people' => count($people), 'households' => $households, 'orgs' => count($orgs), 'documents' => 0, 'vehicles' => 0, 'phones' => 0,
            'links' => count($links), 'aliases' => 0, 'wanted' => 0, 'forged_people' => 0, 'by_kit' => [], 'by_theme' => [], 'by_category' => [],
            'by_flag' => ['genuine' => 0, 'forged' => 0, 'expired' => 0, 'stolen' => 0], 'by_relation' => [], 'by_doc_type' => []];
        foreach ($people as $p) {
            $s['by_kit'][$p['kit']] = ($s['by_kit'][$p['kit']] ?? 0) + 1;
            $s['by_theme'][$p['theme']] = ($s['by_theme'][$p['theme']] ?? 0) + 1;
            $s['by_category'][$p['category']] = ($s['by_category'][$p['category']] ?? 0) + 1;
            $s['vehicles'] += count($p['vehicles']);
            $s['phones'] += count($p['phones']);
            $s['aliases'] += $p['alias'] !== '' ? 1 : 0;
            $s['wanted'] += $p['wanted_level'] !== null ? 1 : 0;
            $s['forged_people'] += $p['forged'] ? 1 : 0;
            foreach ($p['documents'] as $d) {
                $s['documents']++;
                $s['by_flag'][$d['flag']]++;
                $s['by_doc_type'][$d['doc_type']] = ($s['by_doc_type'][$d['doc_type']] ?? 0) + 1;
            }
        }
        foreach ($links as $l) {
            $s['by_relation'][$l['relation']] = ($s['by_relation'][$l['relation']] ?? 0) + 1;
        }

        return $s;
    }

    // ───────────────────────────────────────────────────────────────── Lieux et organisations

    /**
     * Localités fictives du lot, par kit (les personnes se regroupent dans quelques lieux).
     *
     * @param list<string> $kits
     * @return array<string, list<string>>
     */
    private function places(array $kits, int $total, int $seed): array
    {
        $out = [];
        $n = max(2, min(6, (int) ceil($total / 40) + 1));
        foreach ($kits as $code) {
            $kit = SseKitCatalog::kit($code);
            $r = new SseKitRng($seed, 'places' . $code);
            $out[$code] = [];
            for ($t = 0; count($out[$code]) < $n && $t < 60; $t++) {
                $name = self::placeName($kit, $r);
                if (!in_array($name, $out[$code], true)) {
                    $out[$code][] = $name;
                }
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $kit */
    private static function placeName(array $kit, SseKitRng $r): string
    {
        return strtr((string) $kit['placeFormat'], ['{a}' => $r->pick($kit['placeA']), '{b}' => $r->pick($kit['placeB'])]);
    }

    /**
     * @param array<string, mixed> $theme
     * @param array<string, mixed> $kit
     * @param list<string> $existing
     * @return array{0: string, 1: string} nom inventé et son article (« le », « la », « l' », « the » ou vide)
     */
    private function orgName(array $theme, array $kit, string $place, string $lang, SseKitRng $r, array $existing): array
    {
        $patterns = $theme['orgPatterns'][$lang] ?? $theme['orgPatterns']['fr'];
        $name = '';
        $article = '';
        for ($t = 0; $t < 30; $t++) {
            $pattern = $r->pick($patterns);
            $article = (string) $pattern['art'];
            $name = strtr((string) $pattern['name'], ['{w}' => (string) $r->pick($kit['orgWords']), '{place}' => $place]);
            if (!in_array($name, $existing, true)) {
                break;
            }
        }

        return [$name, $article];
    }

    /**
     * Nom d'une organisation avec son article, dans une phrase : « le Front X », « du Front X », « au Front X ».
     *
     * @param array<string, mixed>|null $org
     * @return array{org: string, de: string, a: string}
     */
    private static function orgForms(?array $org, string $lang): array
    {
        if ($org === null) {
            return ['org' => '', 'de' => '', 'a' => ''];
        }
        $name = (string) $org['name'];
        $art = (string) $org['article'];
        if ($lang === 'en') {
            $full = $art !== '' ? $art . ' ' . $name : $name;

            return ['org' => $full, 'de' => 'of ' . $full, 'a' => 'at ' . $full];
        }

        return match ($art) {
            'le' => ['org' => 'le ' . $name, 'de' => 'du ' . $name, 'a' => 'au ' . $name],
            'la' => ['org' => 'la ' . $name, 'de' => 'de la ' . $name, 'a' => 'à la ' . $name],
            "l'" => ['org' => 'l’' . $name, 'de' => 'de l’' . $name, 'a' => 'à l’' . $name],
            'les' => ['org' => 'les ' . $name, 'de' => 'des ' . $name, 'a' => 'aux ' . $name],
            default => ['org' => $name, 'de' => 'de ' . $name, 'a' => 'à ' . $name],
        };
    }

    // ───────────────────────────────────────────────────────────────── Foyers et identités

    /**
     * Remplit les identités d'un foyer : nom de famille et adresse partagés, âges cohérents, liens de parenté.
     *
     * @param list<array<string, mixed>> $people
     * @param list<array<string, mixed>> $links
     * @param array{index: int, kit: string, place: string, members: list<int>} $h
     */
    private function household(array &$people, array &$links, array $h, int $seed, string $today, string $lang): void
    {
        $kit = SseKitCatalog::kit($h['kit']);
        $r = new SseKitRng($seed, 'house' . $h['index']);
        $address = $this->address($kit, $h['place'], $r);
        $family = $this->familyName($kit, $r);
        $spouseFamily = $this->familyName($kit, $r);
        $members = $h['members'];
        $head = $members[0];

        // Rôles dans le foyer.
        $headRole = $people[$head]['role'];
        $headSex = $people[$head]['network'] ? ($r->chance((int) $headRole['femalePct']) ? 'F' : 'M') : ($r->chance(24) ? 'F' : 'M');
        $headAge = $people[$head]['network']
            ? $r->int(max(self::MIN_AGE, (int) $headRole['ageMin']), (int) $headRole['ageMax'])
            : $r->int(count($members) > 2 ? 30 : 22, 72);
        $rel = [$head => 'head'];
        $age = [$head => $headAge];
        $sex = [$head => $headSex];
        $spouse = null;
        foreach (array_slice($members, 1) as $i) {
            $p = $people[$i];
            if ($p['network']) {
                // Membre du réseau hébergé par la famille : son âge suit son rôle, sa place dans la famille s'en déduit.
                $a = $r->int(max(self::MIN_AGE, (int) $p['role']['ageMin']), (int) $p['role']['ageMax']);
                $s = $r->chance((int) $p['role']['femalePct']) ? 'F' : 'M';
                $diff = $headAge - $a;
                $rel[$i] = $diff >= 18 ? 'child' : ($diff <= -18 ? 'parent' : 'sibling');
                $age[$i] = $a;
                $sex[$i] = $s;

                continue;
            }
            if ($spouse === null && $headAge >= 20 && $r->chance(78)) {
                $spouse = $i;
                $rel[$i] = 'spouse';
                $sex[$i] = $headSex === 'M' ? 'F' : 'M';
                $age[$i] = max(18, min(84, $headAge + $r->int(-8, 6)));

                continue;
            }
            $youngest = min($headAge, $spouse !== null ? $age[$spouse] : $headAge);
            if ($youngest - 18 >= self::MIN_AGE && $r->chance(75)) {
                $rel[$i] = 'child';
                $age[$i] = $r->int(self::MIN_AGE, min(45, $youngest - 18));
            } elseif ($headAge <= 62 && $r->chance(45)) {
                $rel[$i] = 'parent';
                $age[$i] = min(90, $headAge + $r->int(19, 30));
            } else {
                $rel[$i] = 'sibling';
                $age[$i] = max(self::MIN_AGE, min(88, $headAge + $r->int(-12, 12)));
            }
            $sex[$i] = $r->chance(50) ? 'F' : 'M';
        }

        $headIsFather = $headSex === 'M';
        foreach ($members as $i) {
            $pr = new SseKitRng($seed, 'person' . $i);
            $s = $sex[$i];
            $relation = $rel[$i];
            // Nom : la lignée du chef de foyer, sauf conjoint qui garde le sien (selon l'usage du pays).
            $own = match (true) {
                $relation === 'spouse' && !empty($kit['spouseKeepsName']) => $spouseFamily,
                $relation === 'parent' && $s === 'F' && !empty($kit['spouseKeepsName']) => $this->familyName($kit, $pr),
                default => $family,
            };
            if (!empty($kit['doubleLast'])) {
                $second = match (true) {
                    $relation === 'child' => $spouse !== null ? $spouseFamily : $this->familyName($kit, new SseKitRng($seed, 'mother' . $h['index'])),
                    $relation === 'sibling' => $this->familyName($kit, new SseKitRng($seed, 'grandmother' . $h['index'])),
                    default => $this->familyName($kit, $pr),
                };
                if ($relation === 'child' && !$headIsFather && $spouse !== null) {
                    [$own, $second] = [$spouseFamily, $family];
                }
                $last = self::lastForm($kit, $own, 'M') . ' ' . self::lastForm($kit, $second, 'M');
            } else {
                $last = self::lastForm($kit, $own, $s);
            }
            $first = $this->firstName($kit, $s, $last, $pr);

            $a = $age[$i];
            $birth = (new \DateTimeImmutable($today, new \DateTimeZone('UTC')))->modify('-' . $a . ' years')->modify('-' . $pr->int(1, 360) . ' days');
            $job = $this->profession($people[$i], $a, $s, $pr, $lang);
            $height = $s === 'M' ? 160 + $pr->int(0, 15) + $pr->int(0, 15) : 150 + $pr->int(0, 13) + $pr->int(0, 13);
            $bmi = ($s === 'M' ? 19.0 : 18.0) + ($pr->int(0, 50) + $pr->int(0, 50)) / 10;
            $weight = max(40, min(160, (int) round($bmi * ($height / 100) ** 2)));
            $build = match (true) {
                $bmi < 21 => 'slim',
                $bmi < 24.5 => $pr->pick(['medium', 'athletic']),
                $bmi < 27 => $s === 'M' ? $pr->pick(['athletic', 'muscular', 'medium']) : 'medium',
                default => $pr->pick(['heavy', 'heavy', 'muscular']),
            };
            [$hair, $eyes] = self::features($kit, $s, $a, $pr);
            $marks = '';
            if ($pr->chance($people[$i]['network'] ? 55 : 22)) {
                $mark = $pr->pick(SseKitCatalog::catalog()['marks']);
                // Barbe, moustache et crâne rasé : signes d'homme seulement.
                if ($s === 'M' || preg_match('/barbe|moustache|crâne/u', (string) $mark['fr']) !== 1) {
                    $marks = (string) $mark[$lang];
                }
            }

            $people[$i] += [
                'seed' => $pr->int(1, SseIdentityGeneratorService::MAX_SEED),
                'last_name' => $last, 'first_name' => $first, 'sex' => $s, 'age' => $a, 'birth_date' => $birth->format('Y-m-d'),
                'birth_place' => $pr->chance(55) ? $h['place'] : ($pr->chance(60) ? (string) $pr->pick($kit['cities']) : self::placeName($kit, $pr)),
                'profession' => $job, 'height_cm' => $height, 'weight_kg' => $weight, 'build' => $build, 'hair' => $hair, 'eyes' => $eyes, 'marks' => $marks,
                'address' => $address, 'family_relation' => $relation, 'quarter' => self::quarterOf($address, $kit),
            ];
        }

        // Liens de parenté : chacun vers le chef de foyer, et les conjoints entre eux.
        foreach (array_slice($members, 1) as $i) {
            $relation = $rel[$i];
            $links[] = ['from' => $i, 'to' => $head, 'relation' => $relation === 'spouse' ? 'spouse' : 'family', 'kin' => $relation,
                'label' => self::kinLabel($relation, $sex[$i], $lang)];
        }
    }

    /** @param array<string, mixed> $kit */
    private function familyName(array $kit, SseKitRng $r): string
    {
        return (string) $r->pick($kit['last']);
    }

    /** Forme du nom selon le sexe (nom au féminin aligné : Ivanov / Ivanova). @param array<string, mixed> $kit */
    private static function lastForm(array $kit, string $last, string $sex): string
    {
        if ($sex !== 'F' || empty($kit['lastF'])) {
            return $last;
        }
        $i = array_search($last, $kit['last'], true);

        return $i !== false && isset($kit['lastF'][$i]) ? (string) $kit['lastF'][$i] : $last;
    }

    /** Prénom sans homonyme dans le lot, au registre, ni parmi les personnalités écartées. @param array<string, mixed> $kit */
    private function firstName(array $kit, string $sex, string $last, SseKitRng $r): string
    {
        $pool = $sex === 'F' ? $kit['female'] : $kit['male'];
        $first = (string) $r->pick($pool);
        for ($t = 0; $t < 40; $t++) {
            $key = mb_strtolower($first . ' ' . $last);
            if (!isset($this->usedNames[$key]) && !SseKitCatalog::isBlocked($first, $last)) {
                break;
            }
            $first = (string) $r->pick($pool);
        }
        $this->usedNames[mb_strtolower($first . ' ' . $last)] = true;

        return $first;
    }

    /**
     * @param array<string, mixed> $p
     */
    private function profession(array $p, int $age, string $sex, SseKitRng $r, string $lang): string
    {
        $key = $lang === 'en' ? 'en' : ($sex === 'F' ? 'frF' : 'fr');
        $cover = $p['role']['cover'] ?? [];
        if ($cover !== []) {
            return (string) $r->pick($cover)[$key];
        }
        $all = SseKitCatalog::catalog()['professions'];
        if ($age <= 19 && $r->chance(70)) {
            return $lang === 'en' ? 'Student' : ($sex === 'F' ? 'Étudiante' : 'Étudiant');
        }
        if ($age >= 66 && $r->chance(75)) {
            return $lang === 'en' ? 'Retired' : ($sex === 'F' ? 'Retraitée' : 'Retraité');
        }
        for ($t = 0; $t < 8; $t++) {
            $job = $r->pick($all);
            $fr = (string) $job['fr'];
            $female = in_array($fr, ['Mère au foyer', 'Couturière', 'Institutrice', 'Vendeuse au marché', 'Sage-femme'], true);
            $young = in_array($fr, ['Étudiant', 'Lycéen'], true);
            $old = $fr === 'Retraité';
            if (($female && $sex !== 'F') || ($young && $age > 24) || ($old && $age < 60)) {
                continue;
            }

            return (string) $job[$key];
        }

        return $lang === 'en' ? 'Shopkeeper' : ($sex === 'F' ? 'Commerçante' : 'Commerçant');
    }

    /**
     * @param array<string, mixed> $kit
     * @return array{0: string, 1: string}
     */
    private static function features(array $kit, string $sex, int $age, SseKitRng $r): array
    {
        $dark = ($kit['features'] ?? 'dark') === 'dark';
        $hair = $dark ? $r->pick(['black', 'black', 'black', 'brown']) : $r->pick(['brown', 'brown', 'blond', 'black', 'red']);
        if ($age >= 68) {
            $hair = $r->pick(['white', 'gray', 'gray']);
        } elseif ($age >= 52 && $r->chance(55)) {
            $hair = 'gray';
        }
        if ($sex === 'M' && $age >= 28 && $r->chance(9)) {
            $hair = 'bald';
        }
        $eyes = $dark ? $r->pick(['brown', 'brown', 'brown', 'black', 'hazel']) : $r->pick(['brown', 'blue', 'gray', 'green', 'hazel', 'blue']);

        return [(string) $hair, (string) $eyes];
    }

    private static function kinLabel(string $relation, string $sex, string $lang): string
    {
        $map = [
            'spouse' => ['fr' => ['M' => 'mari', 'F' => 'femme'], 'en' => ['M' => 'husband', 'F' => 'wife']],
            'child' => ['fr' => ['M' => 'fils', 'F' => 'fille'], 'en' => ['M' => 'son', 'F' => 'daughter']],
            'parent' => ['fr' => ['M' => 'père', 'F' => 'mère'], 'en' => ['M' => 'father', 'F' => 'mother']],
            'sibling' => ['fr' => ['M' => 'frère', 'F' => 'sœur'], 'en' => ['M' => 'brother', 'F' => 'sister']],
        ];

        return $map[$relation][$lang][$sex] ?? ($lang === 'en' ? 'relative' : 'parent');
    }

    // ───────────────────────────────────────────────────────────────── Adresses, téléphones, véhicules

    /** @param array<string, mixed> $kit */
    private function address(array $kit, string $place, SseKitRng $r): string
    {
        $streetB = str_replace('##', (string) $r->int(2, 98), (string) $r->pick($kit['streetB']));

        return strtr((string) $kit['address'], [
            '{street}' => $r->pick($kit['streetA']) . ' ' . $streetB,
            '{no}' => (string) $r->int(1, 240),
            '{quarter}' => (string) $r->pick($kit['quarters']),
            '{place}' => $place,
        ]);
    }

    /** @param array<string, mixed> $kit */
    private static function quarterOf(string $address, array $kit): string
    {
        foreach ($kit['quarters'] as $q) {
            if (str_contains($address, (string) $q)) {
                return (string) $q;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $kit
     * @return array{number: string, imsi: string}
     */
    private function phone(array $kit, SseKitRng $r): array
    {
        $number = '';
        for ($t = 0; $t < 40; $t++) {
            $number = $r->pattern((string) $r->pick($kit['phones']));
            if (!isset($this->usedPhones[self::compact($number)])) {
                break;
            }
        }
        $this->usedPhones[self::compact($number)] = true;

        // IMSI fictif de quinze chiffres : MCC 999 (hors réseaux publics), puis l'opérateur du kit.
        $mnc = str_pad((string) $r->pick($kit['mnc']), 2, '0', STR_PAD_LEFT);

        return ['number' => $number, 'imsi' => '999' . $mnc . $r->pattern(str_repeat('#', 12 - strlen($mnc)))];
    }

    /** @param array<string, mixed> $kit */
    private function plate(array $kit, SseKitRng $r): string
    {
        $plate = '';
        for ($t = 0; $t < 40; $t++) {
            $pattern = (string) $r->pick($kit['plates']);
            if (str_contains($pattern, '{r}')) {
                $pattern = str_replace('{r}', $kit['plateRegions'] !== [] ? (string) $r->pick($kit['plateRegions']) : '', $pattern);
            }
            $plate = trim($r->pattern($pattern, (string) $kit['plateLetters']));
            if (!isset($this->usedPlates[self::compact($plate)])) {
                break;
            }
        }
        $this->usedPlates[self::compact($plate)] = true;

        return $plate;
    }

    /**
     * Répartit les véhicules : d'abord un par foyer (au chef de foyer majeur), puis aux membres de réseau, puis un second.
     *
     * @param list<array<string, mixed>> $people
     * @param list<array{index: int, kit: string, place: string, members: list<int>}> $households
     */
    private function vehicles(array &$people, array $households, int $count, int $seed, string $lang): void
    {
        $r = new SseKitRng($seed, 'vehicles');
        $owners = [];
        foreach ($r->shuffle($households) as $h) {
            foreach ($h['members'] as $i) {
                if ($people[$i]['age'] >= 18) {
                    $owners[] = $i;
                    break;
                }
            }
        }
        $second = $r->shuffle(array_values(array_filter(array_keys($people), static fn (int $i): bool => $people[$i]['age'] >= 18 && !in_array($i, $owners, true))));
        $queue = array_merge($owners, $second, $owners);
        $colors = SseKitCatalog::catalog()['vehicleColors'];
        foreach (array_slice($queue, 0, max(0, $count)) as $i) {
            $kit = SseKitCatalog::kit($people[$i]['kit']);
            $v = $r->pick($kit['vehicles']);
            $flag = null;
            if (($people[$i]['wanted_level'] ?? null) !== null && $r->chance(35)) {
                $flag = $r->pick(['wanted', 'flagged']);
            } elseif ($r->chance(2)) {
                $flag = 'stolen';
            }
            $people[$i]['vehicles'][] = [
                'plate' => $this->plate($kit, $r), 'make' => (string) $v['make'], 'model' => (string) $v['model'], 'kind' => (string) $v['kind'],
                'color' => (string) $r->pick($colors)[$lang], 'flag' => $flag,
            ];
        }
        foreach ($people as $i => $p) {
            $people[$i]['vehicles'] ??= [];
        }
    }

    // ───────────────────────────────────────────────────────────────── Papiers

    /**
     * @param array<string, mixed> $p
     * @param array<string, mixed> $params
     * @param array{number_format?: string, doc_types?: list<string>} $format
     * @param list<string> $kits
     * @return list<array<string, mixed>>
     */
    private function documents(array $p, array $params, array $format, int $seed, string $today, array $kits): array
    {
        $kit = SseKitCatalog::kit($p['kit']);
        $r = new SseKitRng($seed, 'docs' . $p['index']);
        $allowed = array_values(array_intersect($kit['docTypes'], $format['doc_types'] ?? $kit['docTypes']));
        if ($allowed === []) {
            $allowed = ['id_card'];
        }
        $adult = $p['age'] >= 18;
        $wanted = [in_array('id_card', $allowed, true) ? 'id_card' : $allowed[0]];
        if ($p['role']['category'] === 'military') {
            $wanted[] = 'military_id';
        }
        if (($p['vehicles'] ?? []) !== []) {
            $wanted[] = 'driving_license';
            $wanted[] = 'vehicle_registration';
        }
        $wanted[] = $r->chance(55) ? 'passport' : 'driving_license';
        $wanted[] = 'passport';
        $wanted[] = 'driving_license';
        $types = [];
        foreach ($wanted as $t) {
            if (in_array($t, $allowed, true) && !in_array($t, $types, true) && ($adult || !in_array($t, self::ADULT_DOCS, true))) {
                $types[] = $t;
            }
        }
        $types = array_slice($types, 0, max(1, min(4, (int) $params['docs_pp'])));

        $numberFormat = (string) ($format['number_format'] ?? '');
        if ($numberFormat === '') {
            $numberFormat = $kit['iso2'] . '@######';
        }
        $base = new \DateTimeImmutable($today, new \DateTimeZone('UTC'));
        $birth = new \DateTimeImmutable((string) $p['birth_date'], new \DateTimeZone('UTC'));
        $city = (string) $r->pick($kit['cities']);
        $forgedKind = $p['forged'] ?? null;
        $en = ($params['lang'] ?? 'fr') === 'en';
        $forgedAt = $forgedKind !== null ? $r->int(0, count($types) - 1) : -1;
        $docs = [];
        foreach ($types as $n => $type) {
            $years = (int) ($kit['validity'][$type] ?? 10);
            $span = $years > 0 ? $years : 10;
            $minAge = in_array($type, self::ADULT_DOCS, true) ? 18 : self::MIN_AGE;
            $earliest = max($birth->modify('+' . $minAge . ' years'), $base->modify('-' . $span . ' years')->modify('+20 days'));
            $latest = $base->modify('-15 days');
            if ($earliest > $latest) {
                $earliest = max($birth->modify('+' . $minAge . ' years'), $latest->modify('-30 days'));
            }
            $window = max(0, (int) $earliest->diff($latest)->days);
            $issued = $earliest->modify('+' . $r->int(0, $window) . ' days');
            $flag = 'genuine';
            if ($years > 0 && $r->chance(6) && $birth->modify('+' . $minAge . ' years') < $base->modify('-' . $years . ' years')->modify('-40 days')) {
                $flag = 'expired';
                $limit = $base->modify('-' . $years . ' years')->modify('-30 days');
                $from = max($birth->modify('+' . $minAge . ' years'), $limit->modify('-600 days'));
                $issued = $from->modify('+' . $r->int(0, max(0, (int) $from->diff($limit)->days)) . ' days');
            } elseif ($r->chance(2)) {
                $flag = 'stolen';
            }
            $number = $this->number($numberFormat, $p['kit'], $type, $r);
            $authority = strtr((string) ($kit['authorities'][$type] ?? ''), ['{city}' => $city, '{region}' => $city]);
            $note = '';
            $forgery = null;
            if ($n === $forgedAt && $forgedKind !== null) {
                $flag = 'forged';
                $forgery = self::FORGERY[$forgedKind];
                switch ($forgedKind) {
                    case 'dates':
                        // Délivré avant l'âge légal : la date ne peut pas être vraie.
                        $issued = $birth->modify('+' . $r->int(4, $minAge - 3) . ' years')->modify('+' . $r->int(1, 300) . ' days');
                        $note = $en ? 'Deliberate inconsistency: issue date earlier than the holder’s legal age.' : 'Discordance voulue : date de délivrance antérieure à l’âge légal du titulaire.';
                        break;
                    case 'number':
                        $number = 'XX' . $r->pattern('#####');
                        $note = $en ? 'Deliberate inconsistency: number outside the format of the country.' : 'Discordance voulue : numéro qui ne suit pas le format du pays.';
                        break;
                    case 'authority':
                        $others = array_values(array_diff(SseKitCatalog::codes(), [$p['kit']]));
                        $other = SseKitCatalog::kit((string) $r->pick($others));
                        $authority = strtr((string) ($other['authorities'][$type] ?? $other['authorities']['id_card'] ?? ''), ['{city}' => (string) $r->pick($other['cities'])]);
                        $note = $en ? 'Deliberate inconsistency: issuing authority of another country.' : 'Discordance voulue : autorité de délivrance d’un autre pays.';
                        break;
                    case 'name':
                        $note = ($en ? 'Deliberate inconsistency: issued in the name of ' : 'Discordance voulue : établi au nom de ') . ($p['false_name'] ?? '') . '.';
                        break;
                    default:
                        $note = $en ? 'Deliberate inconsistency: photo replaced on a genuine document.' : 'Discordance voulue : photo remplacée sur un titre authentique.';
                }
            }
            $expires = $years > 0 ? $issued->modify('+' . $years . ' years')->format('Y-m-d') : null;
            $docs[] = [
                'doc_type' => $type, 'country_code' => $p['kit'], 'doc_number' => $number, 'issued_on' => $issued->format('Y-m-d'), 'expires_on' => $expires,
                'authority' => $authority, 'flag' => $flag,
                'st_origin' => $flag === 'forged' ? 'forged' : 'generated',
                'st_validity' => $flag === 'expired' || ($expires !== null && $expires < $today) ? 'expired' : 'valid',
                'st_report' => $flag === 'stolen' ? 'stolen' : 'none',
                'forgery_kind' => $forgery, 'forgery_quality' => $forgery !== null ? $r->int(30, 90) : null,
                'chip_status' => $forgery !== null ? ($r->chance(50) ? 'absent' : 'mismatch') : 'ok',
                'note' => $note,
            ];
        }

        return $docs;
    }

    private function number(string $format, string $country, string $type, SseKitRng $r): string
    {
        $number = '';
        for ($t = 0; $t < 60; $t++) {
            $number = $r->pattern($format);
            if (!isset($this->usedNumbers[$country . ':' . $type . ':' . $number])) {
                break;
            }
        }
        $this->usedNumbers[$country . ':' . $type . ':' . $number] = true;

        return $number;
    }

    // ───────────────────────────────────────────────────────────────── Récits et pièces annexes

    /**
     * Récit de contexte : deux ou trois phrases assemblées (ouverture du thème, détail de la fiche, conclusion), jamais
     * deux fois le même assemblage dans un lot.
     *
     * @param list<array<string, mixed>> $people
     * @param list<array<string, mixed>> $links
     * @param list<array<string, mixed>> $orgs
     * @param array<string, list<string>> $places
     */
    private function story(array $people, array $links, array $orgs, int $i, array $places, int $seed, string $today, string $lang): string
    {
        $p = $people[$i];
        $r = new SseKitRng($seed, 'story' . $i);
        $theme = SseKitCatalog::theme($p['theme']);
        $shared = SseKitCatalog::catalog()['shared'];
        $kit = SseKitCatalog::kit($p['kit']);
        $org = $p['org'] !== null ? $orgs[$p['org']] : null;
        $isLeader = $org !== null && $org['leader'] === $i;
        $chief = $org !== null && $org['leader'] !== null && !$isLeader ? $people[$org['leader']]['first_name'] . ' ' . $people[$org['leader']]['last_name'] : '';

        $relative = '';
        foreach ($links as $l) {
            if (!isset($l['kin'])) {
                continue;
            }
            $other = $l['from'] === $i ? $l['to'] : ($l['to'] === $i ? $l['from'] : null);
            if ($other === null) {
                continue;
            }
            // Le lien est écrit du point de vue de « from » : vu de l'autre côté, la parenté s'inverse.
            $kin = (string) $l['kin'];
            if ($l['from'] === $i) {
                $kin = match ($kin) {
                    'child' => 'parent',
                    'parent' => 'child',
                    default => $kin,
                };
            }
            $o = $people[$other];
            $word = self::RELATIVES[$kin][$lang][$o['sex']] ?? '';
            if ($lang === 'en') {
                $word = ($p['sex'] === 'F' ? 'her ' : 'his ') . $word;
            }
            $relative = trim($word . ' ' . $o['first_name'] . ' ' . $o['last_name']);
            break;
        }
        $vehicle = $p['vehicles'][0] ?? null;
        $forms = self::orgForms($org, $lang);
        $others = array_values(array_diff($places[$p['kit']], [$p['place']]));
        $year = (int) substr($today, 0, 4) - $r->int(1, max(1, min(12, $p['age'] - 17)));
        $vars = [
            '{first}' => $p['first_name'], '{last}' => $p['last_name'], '{age}' => (string) $p['age'], '{job}' => self::lower($p['profession']),
            '{place}' => $p['place'], '{place2}' => $others !== [] ? (string) $r->pick($others) : self::placeName($kit, $r),
            '{birth}' => $p['birth_place'], '{quarter}' => $p['quarter'] !== '' ? $p['quarter'] : $p['place'], '{year}' => (string) $year,
            '{role}' => self::lower($lang === 'en' ? (string) $p['role']['label']['en'] : (string) ($p['sex'] === 'F' ? $p['role']['label']['frF'] : $p['role']['label']['fr'])),
            '{org}' => $forms['org'], '{deOrg}' => $forms['de'], '{aOrg}' => $forms['a'], '{chief}' => $chief, '{alias}' => (string) ($p['alias'] ?? ''),
            '{relative}' => $relative, '{falseName}' => (string) ($p['false_name'] ?? ''),
            '{vehicle}' => $vehicle !== null ? $vehicle['make'] . ' ' . $vehicle['model'] : '', '{color}' => $vehicle !== null ? mb_strtolower((string) $vehicle['color']) : '',
            '{plate}' => $vehicle !== null ? (string) $vehicle['plate'] : '',
        ];
        $sameBirth = $p['birth_place'] === $p['place'];
        $usable = static function (array $lines) use ($vars, $lang, $sameBirth): array {
            $ok = [];
            foreach ($lines as $n => $line) {
                $text = (string) $line[$lang];
                // « Né à X, installé à X » : la phrase n'a de sens que si les deux lieux diffèrent.
                $fine = !($sameBirth && str_contains($text, '{birth}') && str_contains($text, '{place}'));
                foreach ($vars as $k => $v) {
                    if ($v === '' && str_contains($text, $k)) {
                        $fine = false;
                        break;
                    }
                }
                if ($fine) {
                    $ok[$n] = $text;
                }
            }

            return $ok;
        };
        $open = $usable($theme['narrative']['open']);
        $mid = $usable($theme['narrative']['mid']);
        $close = $usable($theme['narrative']['close']);
        $special = [];
        foreach (['alias' => ($p['alias'] ?? '') !== '', 'vehicle' => $vehicle !== null, 'family' => $relative !== '', 'forged' => isset($p['forged'])] as $part => $on) {
            if ($on) {
                foreach ($usable($shared[$part]) as $n => $text) {
                    $special[$part . $n] = $text;
                }
            }
        }
        $wanted = ($p['wanted_level'] ?? null) !== null ? $usable($shared['wanted']) : [];

        $ok = array_keys($open);
        $mk = array_keys($special !== [] && ($mid === [] || $r->chance(60)) ? $special : $mid);
        $ck = array_keys($wanted !== [] ? $wanted : $close);
        $a = $r->int(0, count($ok) - 1);
        $b = $mk !== [] ? $r->int(0, count($mk) - 1) : 0;
        $c = $ck !== [] ? $r->int(0, count($ck) - 1) : 0;
        for ($t = 0; $t < 400; $t++) {
            $key = $p['theme'] . '|' . $ok[$a] . '|' . ($mk[$b] ?? '') . '|' . ($ck[$c] ?? '');
            if (!isset($this->usedStories[$key])) {
                break;
            }
            $c++;
            if ($c >= max(1, count($ck))) {
                $c = 0;
                $b++;
                if ($b >= max(1, count($mk))) {
                    $b = 0;
                    $a = ($a + 1) % count($ok);
                }
            }
        }
        $this->usedStories[$p['theme'] . '|' . $ok[$a] . '|' . ($mk[$b] ?? '') . '|' . ($ck[$c] ?? '')] = true;
        $middle = $mk !== [] ? (is_string($mk[$b]) ? $special[$mk[$b]] : $mid[$mk[$b]]) : '';
        $end = $ck !== [] ? ($wanted !== [] ? $wanted[$ck[$c]] : $close[$ck[$c]]) : '';
        $sentences = array_filter([$open[$ok[$a]], $middle, $end], static fn (string $s): bool => $s !== '');

        $text = implode(' ', array_map(static fn (string $s): string => self::ucfirst(self::gender(strtr($s, $vars), $p['sex'])), $sentences));

        // Anglais : « a electrician » -> « an electrician ».
        return $lang === 'en' ? (string) preg_replace('/\b([Aa]) (?=[aeiouAEIOU])/', '$1n ', $text) : $text;
    }

    /**
     * Pièces annexes trouvées sur la personne (texte de la fiche : jamais un papier d'identité du registre).
     *
     * @param array<string, mixed> $p
     * @return list<string>
     */
    private function pocket(array $p, int $seed, string $lang): array
    {
        $r = new SseKitRng($seed, 'pocket' . $p['index']);
        $all = SseKitCatalog::catalog()['pocket'];
        $n = $p['network'] ? $r->int(1, 3) : $r->int(0, 2);
        $out = [];
        foreach (array_slice($r->shuffle($all), 0, $n) as $item) {
            $label = (string) $item[$lang];
            if (str_contains((string) $item['fr'], 'véhicule') && ($p['vehicles'] ?? []) === []) {
                continue;
            }
            if (str_contains((string) $item['fr'], 'étudiant') && $p['age'] > 28) {
                continue;
            }
            $out[] = $label . ' n° ' . $r->pattern((string) $item['format']);
        }

        return $out;
    }

    // ───────────────────────────────────────────────────────────────── Outils

    /** Résout les accords « [masculin|féminin] ». */
    public static function gender(string $text, string $sex): string
    {
        return (string) preg_replace_callback('/\[([^|\]]*)\|([^\]]*)\]/u', static fn (array $m): string => $sex === 'F' ? $m[2] : $m[1], $text);
    }

    private static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    private static function lower(string $s): string
    {
        return mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    public static function compact(string $s): string
    {
        return mb_strtoupper((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $s));
    }

    /** @param list<array<string, mixed>> $links */
    private static function linked(array $links, int $a, int $b): bool
    {
        foreach ($links as $l) {
            if (($l['from'] === $a && $l['to'] === $b) || ($l['from'] === $b && $l['to'] === $a)) {
                return true;
            }
        }

        return false;
    }
}
