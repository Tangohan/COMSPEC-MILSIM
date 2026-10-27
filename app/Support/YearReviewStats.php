<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Chiffres de la rétrospective annuelle, à partir des remontées déjà enregistrées (voir YearReviewService) :
 * une fiche par joueur, son profil (« persona »), son rang dans la communauté, et le bilan collectif.
 *
 * Les entrées sont déjà regroupées par clé joueur (IntelPlayerIdentity : « u12 » compte du site, « s7656… » Steam
 * seul, « c-indicatif » indicatif seul). Seuls les comptes (« u… ») ont une rétrospective individuelle ; les autres
 * comptent dans le bilan de la communauté. Une source absente vaut zéro : jamais d'estimation.
 */
final class YearReviewStats
{
    /** Champs numériques d'une fiche joueur (tous additionnés dans le bilan collectif). */
    public const SUM_FIELDS = [
        'play_s', 'screen_s', 'days', 'missions',
        'foot_m', 'land_m', 'sea_m', 'air_m', 'halo_m', 'halo_jumps', 'steps', 'total_m', 'air_s', 'pilot_s', 'pilot_m',
        'wia', 'kia', 'medevac_patient',
        'sse_notes', 'sse_photos', 'sse_samples', 'sse_custody', 'sse_total',
        'cr', 'debriefs', 'reports', 'medevac_requests', 'poi', 'sigint', 'intel_total', 'events',
    ];

    /** Classements « top X % » affichés au joueur : champ => libellé. */
    public const RANKED = [
        'play_s' => 'de la communauté en temps de jeu',
        'total_m' => 'de la communauté en distance parcourue',
        'sse_total' => 'de la communauté en actions SSE',
        'reports' => 'de la communauté en comptes rendus',
    ];

    /** Tableaux d'honneur du bilan collectif : champ => libellé. */
    public const BOARDS = [
        'play_s' => 'Le plus d’heures de jeu',
        'total_m' => 'Le plus de kilomètres',
        'sse_total' => 'Le plus d’actions SSE',
        'reports' => 'Le plus de comptes rendus',
    ];

    /** Profils possibles : clé => [titre, phrase]. */
    public const PERSONAS = [
        'pilier' => ['Le pilier', 'Toujours là quand l’unité part en opération.'],
        'fantassin' => ['Le fantassin infatigable', 'Tes rangers ont plus de kilomètres que certains véhicules.'],
        'pilote' => ['Le pilote', 'Plus souvent dans le ciel que les pieds sur terre.'],
        'exploitant' => ['L’exploitant SSE', 'Aucun document, aucun téléphone ne t’échappe sur un objectif.'],
        'plume' => ['La plume de l’unité', 'Tes comptes rendus font la mémoire de l’unité.'],
        'ange' => ['L’ange gardien', 'Quand ça tombe, c’est toi qui appelles l’évacuation.'],
        'casse_cou' => ['Le casse-cou', 'Le médecin connaît ton groupe sanguin par cœur.'],
        'recrue' => ['La recrue', 'Une première année sur le terrain : la suite s’annonce bien.'],
    ];

    public const MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    /** @return array<string, mixed> */
    public static function emptyPlayer(): array
    {
        $p = array_fill_keys(self::SUM_FIELDS, 0);
        $p['months'] = array_fill(0, 12, 0);
        $p['top_app'] = null;
        $p['top_role'] = null;
        $p['top_aircraft'] = null;
        $p['first_day'] = '';

        return $p;
    }

    /**
     * Assemble les remontées brutes.
     *
     * @param array<string, array<string, mixed>> $raw clé joueur => champs partiels (mêmes noms que SUM_FIELDS,
     *        plus months (index 0-11 => secondes), apps / roles / aircraft (libellé => secondes), first_day)
     * @param array<string, string> $names clé joueur => nom affiché (indicatif d'abord)
     * @param int $communityMissions sessions distinctes jouées par la communauté (pas la somme des joueurs)
     * @return array{players: array<string, array<string, mixed>>, community: array<string, mixed>}
     */
    public static function build(array $raw, array $names = [], int $communityMissions = 0): array
    {
        $players = [];
        foreach ($raw as $key => $src) {
            $p = self::emptyPlayer();
            foreach (self::SUM_FIELDS as $f) {
                $p[$f] = max(0, (int) ($src[$f] ?? 0));
            }
            foreach ((array) ($src['months'] ?? []) as $i => $sec) {
                if (is_int($i) && $i >= 0 && $i < 12) {
                    $p['months'][$i] = max(0, (int) $sec);
                }
            }
            $p['first_day'] = (string) ($src['first_day'] ?? '');
            $p['top_app'] = self::top((array) ($src['apps'] ?? []));
            $p['top_role'] = self::top((array) ($src['roles'] ?? []));
            $p['top_aircraft'] = self::top((array) ($src['aircraft'] ?? []));
            // Totaux dérivés, toujours recalculés ici.
            $p['total_m'] = $p['foot_m'] + $p['land_m'] + $p['sea_m'] + $p['air_m'] + $p['halo_m'];
            $p['sse_total'] = $p['sse_notes'] + $p['sse_photos'] + $p['sse_samples'] + $p['sse_custody'];
            $p['reports'] = $p['cr'] + $p['debriefs'];
            $p['key'] = (string) $key;
            $p['name'] = trim((string) ($names[$key] ?? '')) !== '' ? trim((string) $names[$key]) : self::fallbackName((string) $key);
            $p['active'] = self::isActive($p);
            $players[(string) $key] = $p;
        }

        $accounts = array_filter($players, static fn (array $p): bool => str_starts_with($p['key'], 'u') && $p['active']);
        foreach ($players as $key => &$p) {
            if (!str_starts_with($key, 'u')) {
                continue;
            }
            $p['ranks'] = self::ranks($p, $accounts);
            $p['persona'] = self::persona($p, $accounts);
            $p['busiest_month'] = self::busiestMonth($p['months']);
        }
        unset($p);

        return ['players' => $players, 'community' => self::community($players, $accounts, $communityMissions)];
    }

    /** Un joueur a-t-il assez joué pour qu'on lui montre une rétrospective ? */
    public static function isActive(array $p): bool
    {
        return (int) ($p['play_s'] ?? 0) >= 600
            || (int) ($p['total_m'] ?? 0) >= 500
            || (int) ($p['missions'] ?? 0) > 0
            || (int) ($p['sse_total'] ?? 0) + (int) ($p['reports'] ?? 0) + (int) ($p['intel_total'] ?? 0) > 0;
    }

    /**
     * @param array<string, int|string> $bySeconds libellé => secondes
     * @return array{label: string, seconds: int}|null
     */
    private static function top(array $bySeconds): ?array
    {
        $best = null;
        foreach ($bySeconds as $label => $sec) {
            $label = trim((string) $label);
            $sec = (int) $sec;
            if ($label === '' || $sec < 1) {
                continue;
            }
            if ($best === null || $sec > $best['seconds'] || ($sec === $best['seconds'] && strcmp($label, $best['label']) < 0)) {
                $best = ['label' => $label, 'seconds' => $sec];
            }
        }

        return $best;
    }

    /**
     * Rang du joueur parmi les comptes actifs qui ont une valeur : podium (N°1 à 3) ou « top 5 % ». Rien sous
     * 3 joueurs classés, ni au-delà de la première moitié hors podium (un « top 80 % » n'a rien de flatteur).
     *
     * @param array<string, array<string, mixed>> $accounts
     * @return array<string, array{label: string, rank: int, of: int, top_pct: int}>
     */
    public static function ranks(array $p, array $accounts): array
    {
        $out = [];
        foreach (self::RANKED as $field => $label) {
            $mine = (int) ($p[$field] ?? 0);
            if ($mine < 1) {
                continue;
            }
            $values = [];
            foreach ($accounts as $a) {
                if ((int) ($a[$field] ?? 0) > 0) {
                    $values[] = (int) $a[$field];
                }
            }
            $n = count($values);
            if ($n < 3) {
                continue;
            }
            $rank = 1 + count(array_filter($values, static fn (int $v): bool => $v > $mine));
            $pct = max(1, (int) ceil($rank * 100 / $n));
            if ($pct > 50 && $rank !== 1 && ($rank > 3 || $n < 6)) {
                continue;
            }
            $out[$field] = ['label' => $label, 'rank' => $rank, 'of' => $n, 'top_pct' => $pct];
        }

        return $out;
    }

    /**
     * Profil dominant : la catégorie où le joueur pèse le plus par rapport au meilleur de sa communauté.
     *
     * @param array<string, array<string, mixed>> $accounts
     * @return array{key: string, title: string, line: string}
     */
    public static function persona(array $p, array $accounts): array
    {
        // Ordre = priorité en cas d'égalité : le profil le plus parlant d'abord, « pilier » en dernier.
        $score = static fn (array $x): array => [
            'fantassin' => (int) ($x['foot_m'] ?? 0),
            'pilote' => (int) ($x['pilot_s'] ?? 0) + (int) ($x['air_s'] ?? 0),
            'exploitant' => (int) ($x['sse_total'] ?? 0),
            'plume' => (int) ($x['reports'] ?? 0),
            'ange' => (int) ($x['medevac_requests'] ?? 0),
            'casse_cou' => (int) ($x['wia'] ?? 0) + (int) ($x['kia'] ?? 0) + (int) ($x['medevac_patient'] ?? 0),
            'pilier' => (int) ($x['play_s'] ?? 0),
        ];
        $max = [];
        foreach ($accounts as $a) {
            foreach ($score($a) as $k => $v) {
                $max[$k] = max($max[$k] ?? 0, $v);
            }
        }
        // Un minimum par catégorie : 3 actions ne font pas un profil.
        $floor = ['pilier' => 3600, 'fantassin' => 5000, 'pilote' => 1800, 'exploitant' => 3, 'plume' => 3, 'ange' => 2, 'casse_cou' => 3];
        $best = 'recrue';
        $bestRatio = 0.0;
        foreach ($score($p) as $k => $v) {
            if ($v < $floor[$k] || ($max[$k] ?? 0) < 1) {
                continue;
            }
            $ratio = $v / $max[$k];
            if ($ratio > $bestRatio + 1e-9) {
                $best = $k;
                $bestRatio = $ratio;
            }
        }
        [$title, $line] = self::PERSONAS[$best];

        return ['key' => $best, 'title' => $title, 'line' => $line];
    }

    /**
     * @param list<int> $months
     * @return array{index: int, label: string, seconds: int}|null
     */
    public static function busiestMonth(array $months): ?array
    {
        $best = null;
        foreach (array_values($months) as $i => $sec) {
            if ($sec > 0 && ($best === null || $sec > $best['seconds'])) {
                $best = ['index' => $i, 'label' => self::MONTHS[$i] ?? '', 'seconds' => (int) $sec];
            }
        }

        return $best;
    }

    /**
     * @param array<string, array<string, mixed>> $players
     * @param array<string, array<string, mixed>> $accounts
     * @return array<string, mixed>
     */
    private static function community(array $players, array $accounts, int $communityMissions): array
    {
        $t = array_fill_keys(self::SUM_FIELDS, 0);
        $months = array_fill(0, 12, 0);
        $apps = [];
        foreach ($players as $p) {
            foreach (self::SUM_FIELDS as $f) {
                $t[$f] += (int) $p[$f];
            }
            foreach ($p['months'] as $i => $sec) {
                $months[$i] += (int) $sec;
            }
            if (is_array($p['top_app'])) {
                $apps[$p['top_app']['label']] = ($apps[$p['top_app']['label']] ?? 0) + 1;
            }
        }
        // Les sessions jouées se comptent une fois pour la communauté, pas une fois par joueur.
        $t['missions'] = $communityMissions > 0 ? $communityMissions : 0;
        unset($t['days']);
        arsort($apps);

        $boards = [];
        foreach (self::BOARDS as $field => $label) {
            $list = array_values(array_filter($accounts, static fn (array $a): bool => (int) $a[$field] > 0));
            usort($list, static fn (array $a, array $b): int => [(int) $b[$field], $a['name']] <=> [(int) $a[$field], $b['name']]);
            $top = array_map(static fn (array $a): array => ['key' => $a['key'], 'name' => $a['name'], 'value' => (int) $a[$field]], array_slice($list, 0, 3));
            if ($top !== []) {
                $boards[$field] = ['label' => $label, 'top' => $top];
            }
        }

        return $t + [
            'members_active' => count($accounts),
            'months' => $months,
            'busiest_month' => self::busiestMonth($months),
            'favorite_app' => $apps !== [] ? (string) array_key_first($apps) : '',
            'boards' => $boards,
        ];
    }

    private static function fallbackName(string $key): string
    {
        if (str_starts_with($key, 'u')) {
            return 'Membre #' . substr($key, 1);
        }
        if (str_starts_with($key, 'c-')) {
            return substr($key, 2);
        }

        return 'Joueur';
    }

    // ---------------------------------------------------------------- affichage

    /** « 128 h », « 45 min ». */
    public static function hours(int $sec): string
    {
        if ($sec < 3600) {
            return max(0, intdiv($sec, 60)) . ' min';
        }

        return number_format(intdiv($sec, 3600), 0, ',', ' ') . ' h';
    }

    /** « 1 284 km », « 12,4 km », « 850 m ». */
    public static function km(int $m): string
    {
        if ($m < 1000) {
            return $m . ' m';
        }

        return number_format($m / 1000, $m < 100000 ? 1 : 0, ',', ' ') . ' km';
    }

    public static function int(int $n): string
    {
        return number_format($n, 0, ',', ' ');
    }
}
