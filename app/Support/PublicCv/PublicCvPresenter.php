<?php

declare(strict_types=1);

namespace App\Support\PublicCv;

/**
 * Transforme les données brutes du joueur en modèle d’affichage de sa page publique.
 *
 * Liste blanche : seuls les champs nommés ici sortent (jamais l’e-mail, le nom affiché — souvent le nom civil —,
 * l’identifiant Steam ou un champ ajouté plus tard à une table). Une section masquée par le joueur ne produit
 * aucune donnée, elle n’est donc pas rendue du tout.
 */
final class PublicCvPresenter
{
    /** Type d’événement de la communauté => libellé affiché sur la ligne d’opération. */
    private const EVENT_TYPES = ['operation' => 'Opération', 'formation' => 'Formation', 'evenement' => 'Événement', 'autre' => 'Autre'];

    /** Mode de déplacement => [libellé, compteur en mètres d’atak_distance_stats]. */
    private const DISTANCE_MODES = [
        'foot' => ['À pied', 'foot_m'],
        'land' => ['En véhicule', 'land_m'],
        'air' => ['En vol', 'air_m'],
        'sea' => ['En mer', 'sea_m'],
        'halo' => ['En chute libre', 'halo_m'],
    ];

    /** Codes de rôle remontés par le jeu => libellé lisible (les autres gardent le nom envoyé par le jeu). */
    private const GAME_ROLE_LABELS = [
        'PIL' => 'Pilote', 'CPL' => 'Copilote', 'DRV' => 'Conducteur', 'CREW' => 'Équipage', 'MED' => 'Infirmier',
        'EOD' => 'Démineur', 'ENG' => 'Sapeur', 'DRN' => 'Télépilote', 'CDE' => 'Chef d’équipe', 'FUS' => 'Fusilier',
        'JTAC' => 'Guidage aérien (JTAC)',
    ];

    /**
     * @param array<string, mixed> $raw     sortie de PublicCvDataLoader::load()
     * @param array<string, mixed> $settings ligne user_public_cv
     * @param array{page_url?: string, community_url?: ?string, badge_url?: callable(string): string, decoration_url?: callable(string): string, photo_url?: callable(string): ?string, asset_url?: callable(string): string, locale?: string} $env
     * @return array<string, mixed>
     */
    public static function present(array $raw, array $settings, array $env = []): array
    {
        $sections = PublicCvSettings::decodeSections(isset($settings['sections_json']) ? (string) $settings['sections_json'] : null);
        $user = is_array($raw['user'] ?? null) ? $raw['user'] : [];
        $locale = (string) ($env['locale'] ?? 'fr');

        $callsign = self::text($user['callsign'] ?? null) ?? self::text($user['public_name'] ?? null) ?? 'Opérateur';

        $grade = null;
        $gradeShort = null;
        $unit = null;
        $community = null;
        $communityUrl = null;
        $banner = null;
        if ($sections['unit']) {
            $g = is_array($raw['grade'] ?? null) ? $raw['grade'] : [];
            $grade = self::text($g['label_long'] ?? null) ?? self::text($g['name'] ?? null);
            $gradeShort = self::text($g['label_short'] ?? null) ?? self::text($g['short_name'] ?? null);
            $post = is_array($raw['unit_post'] ?? null) ? $raw['unit_post'] : [];
            $unit = self::text($raw['unit_name'] ?? null) ?? self::text($post['unit_name'] ?? null);
            $t = is_array($raw['tenant'] ?? null) ? $raw['tenant'] : [];
            $community = self::text($t['name'] ?? null);
            $communityUrl = $community !== null ? ($env['community_url'] ?? null) : null;
            // Bannière déjà publiée par la communauté sur sa propre page : fond de l’en-tête.
            $banner = $community !== null ? self::imageUrl($raw['banner_url'] ?? null, $env['asset_url'] ?? null) : null;
        }
        // Bannière choisie par le joueur : prioritaire sur celle de la communauté (sinon trame).
        $banner = self::text($env['own_banner_url'] ?? null) ?? $banner;

        // Fonction (emploi) et poste dans l’unité, comme les colonnes du tableur Effectifs.
        $job = null;
        $jobBadge = null;
        $otherJobs = [];
        $post = null;
        if ($sections['roles']) {
            foreach (is_array($raw['jobs'] ?? null) ? $raw['jobs'] : [] as $j) {
                $name = self::text($j['name'] ?? null);
                if ($name === null) {
                    continue;
                }
                $detail = self::text($j['detail'] ?? null);
                $label = $detail !== null && mb_strtolower($detail) !== mb_strtolower($name) ? $name . ' — ' . $detail : $name;
                if ($job === null) {
                    $job = $label;
                    $jobBadge = ['icon' => self::text($j['icon'] ?? null), 'abbreviation' => self::text($j['abbreviation'] ?? null)];
                } elseif ($label !== $job && !in_array($label, $otherJobs, true)) {
                    $otherJobs[] = $label;
                }
            }
            $up = is_array($raw['unit_post'] ?? null) ? $raw['unit_post'] : [];
            $post = self::text($up['role_name'] ?? null);
            if ($post !== null && $job !== null && mb_strtolower($post) === mb_strtolower($job)) {
                $post = null;
            }
        }

        $since = $sections['since'] ? self::monthYear($raw['member_since'] ?? null) : null;

        $career = [];
        if ($sections['career']) {
            foreach (is_array($raw['promotions'] ?? null) ? $raw['promotions'] : [] as $pr) {
                $parts = self::structuredParts((string) ($pr['description'] ?? ''));
                if (!isset($parts['Grade'])) {
                    continue;
                }
                $sub = implode(' · ', array_values(array_filter([$parts['Fonction'] ?? null, $parts['Unité'] ?? null])));
                $career[] = [
                    'title' => $parts['Grade'],
                    'subtitle' => $sub !== '' ? $sub : null,
                    'date' => self::monthYear($pr['event_date'] ?? null),
                ];
            }
        }

        $evaluations = ['average' => null, 'items' => []];
        if ($sections['evaluations']) {
            $ev = is_array($raw['evaluations'] ?? null) ? $raw['evaluations'] : [];
            foreach (is_array($ev['items'] ?? null) ? $ev['items'] : [] as $it) {
                $evaluations['items'][] = [
                    'date' => self::monthYear($it['date'] ?? null),
                    'stage' => self::text($it['stage'] ?? null),
                    'title' => self::text($it['title'] ?? null),
                    'rating' => isset($it['rating']) ? (int) $it['rating'] : null,
                    'comment' => self::text($it['comment'] ?? null),
                ];
            }
            $evaluations['average'] = isset($ev['average']) ? (float) $ev['average'] : null;
        }
        $mutations = [];
        if ($sections['mutations']) {
            foreach (is_array($raw['mutations'] ?? null) ? $raw['mutations'] : [] as $mu) {
                $text = self::text($mu['text'] ?? null);
                if ($text !== null) {
                    $mutations[] = ['date' => self::monthYear($mu['date'] ?? null), 'text' => $text];
                }
            }
        }
        $discord = null;
        if ($sections['discord'] && is_array($raw['discord'] ?? null) && !empty($raw['discord']['url'])) {
            $discord = ['name' => (string) ($raw['discord']['name'] ?? 'Discord'), 'url' => (string) $raw['discord']['url']];
        }

        $badge = $env['badge_url'] ?? null;
        $decoUrl = $env['decoration_url'] ?? null;
        $photoUrl = $env['photo_url'] ?? null;

        $decorations = [];
        foreach (is_array($raw['decorations'] ?? null) ? $raw['decorations'] : [] as $d) {
            $title = self::text($d['definition_name'] ?? null);
            if ($title === null) {
                continue;
            }
            $img = self::text($d['image_path'] ?? null);
            $decorations[] = [
                'title' => $title,
                'subtitle' => self::text($d['decoration_grade'] ?? null),
                'date' => self::monthYear($d['awarded_at'] ?? null),
                'image' => $img !== null && is_callable($decoUrl) ? (self::text($decoUrl($img)) ?? null) : null,
            ];
        }

        $qualifications = [];
        foreach (is_array($raw['qualifications'] ?? null) ? $raw['qualifications'] : [] as $q) {
            $title = self::text($q['definition_name'] ?? null) ?? self::text($q['qualification_name'] ?? null);
            if ($title === null) {
                continue;
            }
            $img = self::text($q['level_badge_path'] ?? null) ?? self::text($q['definition_badge_path'] ?? null);
            $code = self::text($q['definition_short_name'] ?? null);
            $qualifications[] = [
                'title' => $title,
                'code' => $code !== null && mb_strlen($code) <= 6 && mb_strtolower($code) !== mb_strtolower($title) ? $code : null,
                'subtitle' => self::text($q['level_name'] ?? null) ?? self::text($q['level'] ?? null),
                'date' => self::monthYear($q['obtained_at'] ?? null),
                'image' => $img !== null && is_callable($badge) ? (self::text($badge($img)) ?? null) : null,
            ];
        }

        $trainings = [];
        $seen = [];
        foreach (is_array($raw['certificates'] ?? null) ? $raw['certificates'] : [] as $c) {
            if (strtolower((string) ($c['status'] ?? 'valid')) !== 'valid') {
                continue;
            }
            $title = self::text($c['course_title'] ?? null);
            if ($title === null || isset($seen[mb_strtolower($title)])) {
                continue;
            }
            $seen[mb_strtolower($title)] = true;
            $when = $c['issued_at'] ?? ($c['completed_at'] ?? null);
            $trainings[] = ['title' => $title, 'subtitle' => 'Formation', 'date' => self::monthYear($when), 'ts' => self::timestamp($when)];
        }
        foreach (is_array($raw['academy'] ?? null) ? $raw['academy'] : [] as $key => $state) {
            if (!is_array($state) || str_starts_with((string) $key, '__') || empty($state['completed'])) {
                continue;
            }
            $snap = is_array($state['snapshot'] ?? null) ? $state['snapshot'] : [];
            $title = self::localized($snap['title'] ?? null, $locale);
            if ($title === null || isset($seen[mb_strtolower($title)])) {
                continue;
            }
            $seen[mb_strtolower($title)] = true;
            $updated = (int) ($state['updated'] ?? 0);
            $trainings[] = [
                'title' => $title,
                'subtitle' => !empty($state['license']) ? 'Licence Academy' : 'Cursus Academy',
                'date' => $updated > 0 ? self::monthYear(date('Y-m-d', $updated)) : null,
                'ts' => max(0, $updated),
            ];
        }
        // Frise : la plus récente en tête, puis sans date.
        usort($trainings, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);
        $trainings = array_map(static function (array $t): array {
            unset($t['ts']);

            return $t;
        }, $trainings);

        $ops = is_array($raw['operations'] ?? null) ? $raw['operations'] : [];
        $operations = [];
        foreach (is_array($ops['recent'] ?? null) ? $ops['recent'] : [] as $o) {
            $title = self::text($o['title'] ?? null);
            if ($title === null) {
                continue;
            }
            $ts = self::timestamp($o['starts_at'] ?? null);
            $operations[] = [
                'title' => $title,
                'date' => self::monthYear($o['starts_at'] ?? null),
                'day' => $ts > 0 ? date('d.m.y', $ts) : null,
                'iso' => $ts > 0 ? date('Y-m-d', $ts) : null,
                'type' => self::EVENT_TYPES[strtolower(trim((string) ($o['event_type'] ?? '')))] ?? null,
            ];
        }

        $photos = [];
        foreach (is_array($raw['photos'] ?? null) ? $raw['photos'] : [] as $p) {
            $path = self::text($p['storage_path'] ?? null);
            $u = $path !== null && is_callable($photoUrl) ? self::text($photoUrl($path)) : null;
            if ($u !== null) {
                $photos[] = ['url' => $u, 'title' => self::text($p['title'] ?? null) ?? ''];
            }
        }

        $flightSeconds = max(0, (int) ($raw['flight_seconds'] ?? 0));
        $flight = self::hours($flightSeconds);
        [$flightNumber, $flightUnit] = array_pad(explode(' ', $flight, 2), 2, null);
        $operationsCount = (string) max(0, (int) ($ops['count'] ?? 0));
        // value : texte complet ; number + unit : le même chiffre et son unité, mis en forme séparément.
        $activityCounters = [];
        $distanceModes = [];
        $gameRoles = [];
        if ($sections['activity']) {
            $play = is_array($raw['play'] ?? null) ? $raw['play'] : [];
            $playSeconds = max(0, (int) ($play['total_seconds'] ?? 0));
            if ($playSeconds >= 60) {
                $p = self::hours($playSeconds);
                [$pn, $pu] = array_pad(explode(' ', $p, 2), 2, null);
                $sessions = max(0, (int) ($play['sessions_total'] ?? 0));
                $activityCounters[] = [
                    'key' => 'play', 'label' => 'Heures de jeu', 'value' => $p, 'number' => (string) $pn, 'unit' => $pu,
                    'hint' => $sessions > 0 ? 'sur ' . $sessions . ' partie' . ($sessions > 1 ? 's' : '') : 'en mission',
                ];
            }
            $dist = is_array($raw['distances'] ?? null) ? $raw['distances'] : [];
            $totals = is_array($dist['totals'] ?? null) ? $dist['totals'] : [];
            $totalM = 0;
            foreach (self::DISTANCE_MODES as $mode => [$label, $col]) {
                $m = max(0, (int) ($totals[$col] ?? 0));
                $totalM += $m;
                if ($m >= 100) {
                    $jumps = $mode === 'halo' ? max(0, (int) ($totals['halo_jumps'] ?? 0)) : 0;
                    $distanceModes[] = [
                        'key' => $mode, 'label' => $label, 'meters' => $m, 'km' => self::km($m),
                        'note' => $jumps > 0 ? $jumps . ' saut' . ($jumps > 1 ? 's' : '') : null,
                    ];
                }
            }
            if ($totalM >= 100) {
                $activityCounters[] = [
                    'key' => 'distance', 'label' => 'Kilomètres', 'value' => self::km($totalM) . ' km', 'number' => self::km($totalM),
                    'unit' => 'km', 'hint' => 'parcourus en jeu',
                ];
            }
            usort($distanceModes, static fn (array $a, array $b): int => $b['meters'] <=> $a['meters']);
            $maxM = $distanceModes !== [] ? max(1, $distanceModes[0]['meters']) : 1;
            foreach ($distanceModes as $i => $d) {
                $distanceModes[$i]['share'] = (int) max(2, round($d['meters'] * 100 / $maxM));
            }
            foreach (array_slice(is_array($raw['game_roles'] ?? null) ? $raw['game_roles'] : [], 0, 5) as $r) {
                $sec = max(0, (int) ($r['seconds'] ?? 0));
                $label = self::roleLabel((string) ($r['key'] ?? ''), self::text($r['label'] ?? null));
                if ($sec >= 600 && $label !== null) {
                    $gameRoles[] = ['label' => $label, 'seconds' => $sec, 'hours' => self::hours($sec)];
                }
            }
            $maxR = $gameRoles !== [] ? max(1, $gameRoles[0]['seconds']) : 1;
            foreach ($gameRoles as $i => $r) {
                $gameRoles[$i]['share'] = (int) max(2, round($r['seconds'] * 100 / $maxR));
            }
        }
        // Ordre du bandeau : opérations, heures de jeu, kilomètres, heures de vol, qualifications, formations.
        $counters = array_merge($sections['counters'] ? [
            ['key' => 'operations', 'label' => 'Opérations', 'value' => $operationsCount, 'number' => $operationsCount, 'unit' => null, 'hint' => 'jouées avec la communauté'],
        ] : [], $activityCounters, !$sections['counters'] ? [] : [
            ['key' => 'flight', 'label' => 'Heures de vol', 'value' => $flight, 'number' => (string) $flightNumber, 'unit' => $flightUnit, 'hint' => 'inscrites au carnet de vol'],
            ['key' => 'qualifications', 'label' => 'Qualifications', 'value' => (string) count($qualifications), 'number' => (string) count($qualifications), 'unit' => null, 'hint' => 'en cours de validité'],
            ['key' => 'trainings', 'label' => 'Formations', 'value' => (string) count($trainings), 'number' => (string) count($trainings), 'unit' => null, 'hint' => 'validées, cursus Academy compris'],
        ]);

        $bio = $sections['bio'] ? self::text(PublicCvSettings::normalizeBio((string) ($settings['bio'] ?? ''))) : null;

        $headline = implode(' · ', array_values(array_filter([$grade, $unit, $community], static fn ($v): bool => $v !== null)));
        $ogDescription = $bio !== null
            ? mb_substr(str_replace("\n", ' ', $bio), 0, 200)
            : 'Parcours de ' . $callsign . ($headline !== '' ? ' — ' . $headline : '') . ' sur Athena.';

        $avatar = self::text($raw['avatar_url'] ?? null);

        return [
            'slug' => (string) ($settings['slug'] ?? ''),
            'page_url' => (string) ($env['page_url'] ?? ''),
            'callsign' => $callsign,
            'initials' => mb_strtoupper(mb_substr($callsign, 0, 2)),
            'grade' => $grade,
            'grade_short' => $gradeShort,
            'unit' => $unit,
            'community' => $community,
            'community_url' => $communityUrl,
            'headline' => $headline,
            'avatar_url' => $avatar,
            'banner_url' => $banner,
            'bio' => $bio,
            'sections' => $sections,
            'job' => $job,
            'job_badge' => $jobBadge,
            'other_jobs' => $otherJobs,
            'post' => $post,
            'member_since' => $since,
            'career' => $career,
            'evaluations' => $evaluations,
            'mutations' => $mutations,
            'discord' => $discord,
            'counters' => $counters,
            'distance_modes' => $distanceModes,
            'game_roles' => $gameRoles,
            'decorations' => $sections['decorations'] ? $decorations : [],
            'qualifications' => $sections['qualifications'] ? $qualifications : [],
            'trainings' => $sections['trainings'] ? $trainings : [],
            'operations' => $sections['operations'] ? $operations : [],
            'photos' => $sections['photos'] ? $photos : [],
            'og' => [
                'title' => $callsign . ($grade !== null ? ' — ' . $grade : '') . ' | Athena',
                'description' => $ogDescription,
                'image' => $avatar,
            ],
        ];
    }

    private static function text(mixed $v): ?string
    {
        if (!is_scalar($v)) {
            return null;
        }
        $s = trim((string) $v);

        return $s !== '' ? $s : null;
    }

    /** Distance lisible : une décimale sous 10 km, entier au-delà. */
    public static function km(int $meters): string
    {
        $km = max(0, $meters) / 1000;
        if ($km < 10) {
            return number_format($km, 1, ',', '');
        }

        return number_format((int) round($km), 0, ',', "\u{202F}");
    }

    /**
     * Segments « Clé : valeur » d’un texte de promotion (« Grade : X · Fonction : Y · Unité : Z · Accès : … »).
     * Seuls Grade, Fonction et Unité sont gardés ; le reste (accès, droits, motifs) n’est jamais repris.
     *
     * @return array<string, string>
     */
    private static function structuredParts(string $text): array
    {
        $out = [];
        foreach (explode('·', $text) as $seg) {
            if (preg_match('/^\s*(Grade|Fonction|Unité)\s*:\s*(.+?)\s*$/u', $seg, $m) === 1 && !isset($out[$m[1]])) {
                $v = mb_substr(trim($m[2]), 0, 80);
                if ($v !== '') {
                    $out[$m[1]] = $v;
                }
            }
        }

        return $out;
    }

    private static function roleLabel(string $key, ?string $label): ?string
    {
        $k = strtoupper(trim($key));
        if (isset(self::GAME_ROLE_LABELS[$k])) {
            return self::GAME_ROLE_LABELS[$k];
        }
        if (str_starts_with($k, 'SLOT:') && ($label === null || strtoupper($label) === $k)) {
            $label = trim(substr($key, 5));
        }
        $label = $label !== null ? trim(str_replace('_', ' ', $label)) : '';

        return $label !== '' ? mb_substr($label, 0, 40) : null;
    }

    private static function timestamp(mixed $raw): int
    {
        $s = self::text($raw);
        if ($s === null || str_starts_with($s, '0000')) {
            return 0;
        }
        $ts = strtotime($s);

        return $ts === false ? 0 : max(0, $ts);
    }

    /**
     * Adresse d’image sûre : http(s) absolue, ou chemin du site passé par $asset. Tout autre schéma est écarté.
     */
    private static function imageUrl(mixed $raw, mixed $asset): ?string
    {
        $u = self::text($raw);
        if ($u === null) {
            return null;
        }
        if (preg_match('#[\s"\'<>()\\\\]#', $u) === 1) {
            return null;
        }
        if (preg_match('#^https?://#i', $u) === 1) {
            return $u;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $u) === 1 || str_starts_with($u, '//')) {
            return null;
        }
        $path = ltrim($u, '/');

        return is_callable($asset) ? self::text($asset($path)) : '/' . $path;
    }

    private static function localized(mixed $v, string $locale): ?string
    {
        if (is_array($v)) {
            return self::text($v[$locale] ?? null) ?? self::text($v['fr'] ?? null) ?? self::text(reset($v));
        }

        return self::text($v);
    }

    public static function monthYear(mixed $raw): ?string
    {
        $s = self::text($raw);
        if ($s === null || str_starts_with($s, '0000')) {
            return null;
        }
        $ts = strtotime($s);
        if ($ts === false) {
            return null;
        }

        return \App\Support\LocalizedCalendar::month((int) date('n', $ts)) . ' ' . date('Y', $ts);
    }

    public static function hours(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 h';
        }
        $h = intdiv($seconds, 3600);
        if ($h === 0) {
            return intdiv($seconds, 60) . ' min';
        }

        return number_format($h, 0, ',', "\u{202F}") . ' h';
    }
}
