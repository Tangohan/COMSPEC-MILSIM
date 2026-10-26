<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Découpe une rétrospective en écrans (stories) : seuls les écrans qui ont des chiffres sont montrés.
 *
 * Les textes restent en français (traduits à l'affichage par HtmlTranslator) et ne mélangent jamais une valeur
 * dans une phrase : le nom, les nombres et les unités sont des champs à part, pour que la traduction tienne.
 *
 * Chaque écran : key, tone (couleur), kicker, title, big (chiffre principal), big_label, lead, stats (liste
 * [label, value]), bars (12 mois), boards (tableaux d'honneur), locked (invitation formule payante).
 */
final class YearReviewStory
{
    /**
     * @param array<string, mixed>|null $player fiche YearReviewStats (null : pas assez d'activité)
     * @param array<string, mixed>|null $community bilan YearReviewStats
     * @param array{tenant_name?: string, partial?: bool, until?: string, collective?: bool, upsell?: bool, community_only?: bool} $opts
     * @return list<array<string, mixed>>
     */
    public static function slides(int $year, ?array $player, ?array $community, array $opts = []): array
    {
        $s = [];
        $tenant = trim((string) ($opts['tenant_name'] ?? ''));
        $communityOnly = !empty($opts['community_only']);
        $hasCommunity = !empty($opts['collective']) && is_array($community) && (int) ($community['members_active'] ?? 0) > 0;

        if (!$communityOnly) {
            $s[] = [
                'key' => 'intro',
                'tone' => 'night',
                'kicker' => 'Rétrospective ' . $year,
                'name' => $player !== null ? (string) $player['name'] : '',
                'title' => $player !== null ? 'Voici ton année sur le terrain.' : 'Pas encore assez de terrain cette année.',
                'lead' => $player !== null
                    ? 'Heures de jeu, kilomètres, blessures, SSE, comptes rendus : tout ce que ton ATAK et le site ont retenu.'
                    : 'Ta rétrospective se remplit à chaque partie jouée avec l’ATAK et le lien du jeu.',
                'sub' => $tenant,
                'partial' => !empty($opts['partial']),
            ];
            if ($player !== null) {
                array_push($s, ...self::playerSlides($year, $player));
            }
        } else {
            $s[] = [
                'key' => 'intro',
                'tone' => 'night',
                'kicker' => 'Rétrospective ' . $year,
                'name' => $tenant,
                'title' => 'L’année de ton unité.',
                'lead' => 'Toutes les heures, tous les kilomètres et toutes les remontées de la communauté.',
                'sub' => '',
                'partial' => !empty($opts['partial']),
            ];
        }

        if ($hasCommunity) {
            array_push($s, ...self::communitySlides($year, $community, $tenant));
        } elseif (!empty($opts['upsell']) && !$communityOnly) {
            $s[] = [
                'key' => 'locked',
                'tone' => 'slate',
                'kicker' => 'Bilan de l’unité',
                'title' => 'La rétrospective de toute l’unité.',
                'lead' => 'Heures et kilomètres cumulés, missions, SSE et tableau d’honneur de la communauté : inclus dans les formules Standard et supérieures.',
                'locked' => true,
            ];
        }

        $summary = $communityOnly || $player === null ? self::communitySummary($community, $hasCommunity) : self::playerSummary($player);
        if ($summary !== []) {
            $s[] = [
                'key' => 'summary',
                'tone' => 'gold',
                'kicker' => 'Rétrospective ' . $year,
                'name' => $communityOnly || $player === null ? $tenant : (string) $player['name'],
                'title' => $communityOnly || $player === null ? 'Notre année en chiffres' : 'Mon année en chiffres',
                'persona' => !$communityOnly && $player !== null ? (string) ($player['persona']['title'] ?? '') : '',
                'stats' => $summary,
                'share' => true,
            ];
        }

        return $s;
    }

    /** @return list<array<string, mixed>> */
    private static function playerSlides(int $year, array $p): array
    {
        $s = [];
        $hours = YearReviewStats::hours(...);
        $km = YearReviewStats::km(...);
        $int = YearReviewStats::int(...);

        if ($p['play_s'] > 0 || $p['missions'] > 0) {
            $stats = [];
            if ($p['days'] > 0) {
                $stats[] = ['Jours sur le terrain', $int($p['days'])];
            }
            if ($p['missions'] > 0) {
                $stats[] = ['Missions jouées', $int($p['missions'])];
            }
            if (is_array($p['busiest_month'] ?? null)) {
                $stats[] = ['Mois le plus actif', (string) $p['busiest_month']['label']];
            }
            $s[] = [
                'key' => 'play', 'tone' => 'olive', 'kicker' => 'Temps de jeu',
                'big' => $hours($p['play_s']), 'big_label' => 'passées en jeu',
                'title' => 'Tu as répondu présent.',
                'stats' => $stats,
                'bars' => array_sum($p['months']) > 0 ? self::bars($p['months']) : [],
                'share' => true,
            ];
        }

        if ($p['total_m'] > 0) {
            $stats = [];
            foreach ([['foot_m', 'À pied'], ['land_m', 'En véhicule'], ['air_m', 'Dans les airs'], ['sea_m', 'Sur l’eau'], ['halo_m', 'En chute libre']] as [$f, $label]) {
                if ($p[$f] > 0) {
                    $stats[] = [$label, $km($p[$f])];
                }
            }
            if ($p['steps'] > 0) {
                $stats[] = ['Pas', $int($p['steps'])];
            }
            if ($p['halo_jumps'] > 0) {
                $stats[] = ['Sauts HALO', $int($p['halo_jumps'])];
            }
            $s[] = [
                'key' => 'distance', 'tone' => 'sand', 'kicker' => 'Kilomètres',
                'big' => $km($p['total_m']), 'big_label' => 'parcourus en opération',
                'title' => self::distanceLine($p['total_m']),
                'stats' => $stats,
                'share' => true,
            ];
        }

        if ($p['pilot_s'] > 0 || $p['air_s'] > 0) {
            $stats = [];
            if (is_array($p['top_aircraft'])) {
                $stats[] = ['Appareil préféré', (string) $p['top_aircraft']['label']];
            }
            if ($p['pilot_m'] > 0) {
                $stats[] = ['Distance aux commandes', $km($p['pilot_m'])];
            }
            if ($p['pilot_s'] > 0 && $p['air_s'] > 0) {
                $stats[] = ['Temps en l’air, passager compris', $hours($p['air_s'])];
            }
            $s[] = [
                'key' => 'flight', 'tone' => 'sky', 'kicker' => 'Heures de vol',
                'big' => $hours($p['pilot_s'] > 0 ? $p['pilot_s'] : $p['air_s']),
                'big_label' => $p['pilot_s'] > 0 ? 'aux commandes' : 'dans les airs',
                'title' => $p['pilot_s'] > 0 ? 'Le ciel était ton bureau.' : 'Tu as pris l’air.',
                'stats' => $stats,
                'share' => true,
            ];
        }

        if ($p['play_s'] > 0 || $p['wia'] + $p['kia'] + $p['medevac_patient'] > 0) {
            $hurt = $p['wia'] + $p['kia'];
            $s[] = [
                'key' => 'medical', 'tone' => 'red', 'kicker' => 'Blessures',
                'big' => $int($hurt), 'big_label' => $hurt > 1 ? 'fois touché' : ($hurt === 1 ? 'fois touché' : 'blessure enregistrée'),
                'title' => match (true) {
                    $hurt === 0 && $p['medevac_patient'] === 0 => 'Pas une égratignure. Intouchable.',
                    $p['kia'] >= 5 => 'Le médecin connaît ton groupe sanguin par cœur.',
                    default => 'Tu es toujours revenu au combat.',
                },
                'stats' => array_values(array_filter([
                    $p['wia'] > 0 ? ['Blessé au combat', $int($p['wia'])] : null,
                    $p['kia'] > 0 ? ['Tombé au combat', $int($p['kia'])] : null,
                    $p['medevac_patient'] > 0 ? ['Évacué en MEDEVAC', $int($p['medevac_patient'])] : null,
                ])),
                'share' => true,
            ];
        }

        if ($p['sse_total'] + $p['reports'] + $p['medevac_requests'] + $p['poi'] > 0) {
            $stats = array_values(array_filter([
                $p['sse_notes'] > 0 ? ['Fiches de renseignement SSE', $int($p['sse_notes'])] : null,
                $p['sse_photos'] > 0 ? ['Photos SSE', $int($p['sse_photos'])] : null,
                $p['sse_samples'] > 0 ? ['Prélèvements biométriques', $int($p['sse_samples'])] : null,
                $p['sse_custody'] > 0 ? ['Saisies sous scellés', $int($p['sse_custody'])] : null,
                $p['cr'] > 0 ? ['Comptes rendus tactiques', $int($p['cr'])] : null,
                $p['debriefs'] > 0 ? ['Debriefings remplis', $int($p['debriefs'])] : null,
                $p['medevac_requests'] > 0 ? ['Demandes MEDEVAC', $int($p['medevac_requests'])] : null,
                $p['poi'] > 0 ? ['Points d’intérêt signalés', $int($p['poi'])] : null,
            ]));
            $sse = $p['sse_total'] > 0;
            $s[] = [
                'key' => 'intel', 'tone' => 'teal', 'kicker' => $sse ? 'SSE et renseignement' : 'Comptes rendus',
                'big' => $int($sse ? $p['sse_total'] : ($p['reports'] > 0 ? $p['reports'] : $p['medevac_requests'] + $p['poi'])),
                'big_label' => $sse ? 'actions SSE' : ($p['reports'] > 0 ? 'comptes rendus' : 'remontées du terrain'),
                'title' => $sse ? 'Rien ne t’échappe sur un objectif.' : 'Tu as nourri le renseignement.',
                'stats' => $stats,
                'share' => true,
            ];
        }

        if ($p['screen_s'] > 0 || $p['events'] > 0) {
            $stats = [];
            if (is_array($p['top_app'])) {
                $stats[] = ['Appli préférée', (string) $p['top_app']['label']];
            }
            if (is_array($p['top_role'])) {
                $stats[] = ['Rôle le plus joué', (string) $p['top_role']['label']];
            }
            if ($p['events'] > 0) {
                $stats[] = ['Événements de l’unité', $int($p['events'])];
            }
            $s[] = [
                'key' => 'atak', 'tone' => 'violet', 'kicker' => 'Ton ATAK',
                'big' => $p['screen_s'] > 0 ? $hours($p['screen_s']) : $int($p['events']),
                'big_label' => $p['screen_s'] > 0 ? 'les yeux sur l’écran' : 'événements',
                'title' => 'Ton téléphone de terrain ne t’a pas quitté.',
                'stats' => $stats,
                'share' => true,
            ];
        }

        $ranks = [];
        foreach ((array) ($p['ranks'] ?? []) as $r) {
            $ranks[] = [(int) $r['rank'] <= 3 ? 'N°' . (int) $r['rank'] : 'Top ' . (int) $r['top_pct'] . ' %', (string) $r['label']];
        }
        $s[] = [
            'key' => 'persona', 'tone' => 'gold', 'kicker' => 'Ton profil ' . $year,
            'big' => (string) ($p['persona']['title'] ?? ''), 'big_label' => '',
            'title' => (string) ($p['persona']['line'] ?? ''),
            'ranks' => $ranks,
            'share' => true,
        ];

        return $s;
    }

    /** @return list<array<string, mixed>> */
    private static function communitySlides(int $year, array $c, string $tenant): array
    {
        $hours = YearReviewStats::hours(...);
        $km = YearReviewStats::km(...);
        $int = YearReviewStats::int(...);
        $stats = array_values(array_filter([
            $c['play_s'] > 0 ? ['Heures de jeu cumulées', $hours($c['play_s'])] : null,
            $c['total_m'] > 0 ? ['Kilomètres parcourus', $km($c['total_m'])] : null,
            $c['missions'] > 0 ? ['Missions jouées', $int($c['missions'])] : null,
            $c['pilot_s'] > 0 ? ['Heures de vol', $hours($c['pilot_s'])] : null,
            $c['sse_total'] > 0 ? ['Actions SSE', $int($c['sse_total'])] : null,
            $c['reports'] > 0 ? ['Comptes rendus', $int($c['reports'])] : null,
            $c['wia'] + $c['kia'] > 0 ? ['Blessés et tués', $int($c['wia'] + $c['kia'])] : null,
            $c['medevac_requests'] > 0 ? ['Demandes MEDEVAC', $int($c['medevac_requests'])] : null,
        ]));
        $out = [[
            'key' => 'community', 'tone' => 'olive', 'kicker' => 'Ton unité en ' . $year,
            'name' => $tenant,
            'big' => $int((int) $c['members_active']), 'big_label' => (int) $c['members_active'] > 1 ? 'joueurs sur le terrain' : 'joueur sur le terrain',
            'title' => 'Ensemble, cette année.',
            'stats' => array_slice($stats, 0, 5),
            'bars' => array_sum($c['months']) > 0 ? self::bars($c['months']) : [],
            'share' => true,
        ]];
        $boards = [];
        foreach ((array) ($c['boards'] ?? []) as $field => $b) {
            $rows = [];
            foreach ($b['top'] as $i => $t) {
                $value = match ($field) {
                    'play_s' => $hours((int) $t['value']),
                    'total_m' => $km((int) $t['value']),
                    default => $int((int) $t['value']),
                };
                $rows[] = ['rank' => $i + 1, 'name' => (string) $t['name'], 'value' => $value];
            }
            $boards[] = ['label' => (string) $b['label'], 'rows' => $rows];
        }
        if ($boards !== []) {
            $out[] = [
                'key' => 'boards', 'tone' => 'night', 'kicker' => 'Tableau d’honneur ' . $year,
                'name' => $tenant,
                'title' => 'Ils ont porté l’unité.',
                'boards' => $boards,
                'share' => true,
            ];
        }

        return $out;
    }

    /** @return list<array{0: string, 1: string}> */
    private static function playerSummary(array $p): array
    {
        $int = YearReviewStats::int(...);
        $out = [];
        if ($p['play_s'] > 0) {
            $out[] = ['Heures de jeu', YearReviewStats::hours($p['play_s'])];
        }
        if ($p['missions'] > 0) {
            $out[] = ['Missions', $int($p['missions'])];
        }
        if ($p['total_m'] > 0) {
            $out[] = ['Kilomètres', YearReviewStats::km($p['total_m'])];
        }
        if ($p['pilot_s'] > 0) {
            $out[] = ['Heures de vol', YearReviewStats::hours($p['pilot_s'])];
        }
        if ($p['sse_total'] > 0) {
            $out[] = ['Actions SSE', $int($p['sse_total'])];
        }
        if ($p['reports'] > 0) {
            $out[] = ['Comptes rendus', $int($p['reports'])];
        }
        $out[] = ['Blessures', $int($p['wia'] + $p['kia'])];

        return array_slice($out, 0, 6);
    }

    /** @return list<array{0: string, 1: string}> */
    private static function communitySummary(?array $c, bool $visible): array
    {
        if (!$visible || $c === null) {
            return [];
        }
        $int = YearReviewStats::int(...);

        return array_slice(array_values(array_filter([
            ['Joueurs actifs', $int((int) $c['members_active'])],
            $c['play_s'] > 0 ? ['Heures de jeu', YearReviewStats::hours($c['play_s'])] : null,
            $c['missions'] > 0 ? ['Missions', $int($c['missions'])] : null,
            $c['total_m'] > 0 ? ['Kilomètres', YearReviewStats::km($c['total_m'])] : null,
            $c['sse_total'] > 0 ? ['Actions SSE', $int($c['sse_total'])] : null,
            $c['reports'] > 0 ? ['Comptes rendus', $int($c['reports'])] : null,
        ])), 0, 6);
    }

    /**
     * Barres mensuelles (hauteur en % du mois le plus fort) ; mois à venir de l'année en cours marqués.
     *
     * @param list<int> $months
     * @return list<array{label: string, pct: int}>
     */
    private static function bars(array $months): array
    {
        $max = max(1, ...$months);
        $out = [];
        foreach ($months as $i => $sec) {
            $out[] = ['label' => mb_strtoupper(mb_substr(YearReviewStats::MONTHS[$i], 0, 1)), 'pct' => (int) round($sec * 100 / $max)];
        }

        return $out;
    }

    /** Comparaison parlante pour la distance. */
    private static function distanceLine(int $m): string
    {
        return match (true) {
            $m >= 400000 => 'Plus qu’un Paris – Lyon en opération.',
            $m >= 42195 => 'Plus qu’un marathon, chargé comme une mule.',
            $m >= 10000 => 'Des kilomètres de patrouille au compteur.',
            default => 'Chaque pas compte sur le terrain.',
        };
    }
}
