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
    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /**
     * @param array<string, mixed> $raw     sortie de PublicCvDataLoader::load()
     * @param array<string, mixed> $settings ligne user_public_cv
     * @param array{page_url?: string, community_url?: ?string, badge_url?: callable(string): string, decoration_url?: callable(string): string, photo_url?: callable(string): ?string, locale?: string} $env
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
        if ($sections['unit']) {
            $g = is_array($raw['grade'] ?? null) ? $raw['grade'] : [];
            $grade = self::text($g['label_long'] ?? null) ?? self::text($g['name'] ?? null);
            $gradeShort = self::text($g['label_short'] ?? null) ?? self::text($g['short_name'] ?? null);
            $unit = self::text($raw['unit_name'] ?? null);
            $t = is_array($raw['tenant'] ?? null) ? $raw['tenant'] : [];
            $community = self::text($t['name'] ?? null);
            $communityUrl = $community !== null ? ($env['community_url'] ?? null) : null;
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
            $qualifications[] = [
                'title' => $title,
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
            $trainings[] = ['title' => $title, 'subtitle' => 'Formation', 'date' => self::monthYear($c['issued_at'] ?? ($c['completed_at'] ?? null))];
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
            ];
        }

        $ops = is_array($raw['operations'] ?? null) ? $raw['operations'] : [];
        $operations = [];
        foreach (is_array($ops['recent'] ?? null) ? $ops['recent'] : [] as $o) {
            $title = self::text($o['title'] ?? null);
            if ($title !== null) {
                $operations[] = ['title' => $title, 'date' => self::monthYear($o['starts_at'] ?? null)];
            }
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
        $counters = [
            ['key' => 'operations', 'label' => 'Opérations', 'value' => (string) max(0, (int) ($ops['count'] ?? 0))],
            ['key' => 'flight', 'label' => 'Heures de vol', 'value' => self::hours($flightSeconds)],
            ['key' => 'qualifications', 'label' => 'Qualifications', 'value' => (string) count($qualifications)],
            ['key' => 'trainings', 'label' => 'Formations', 'value' => (string) count($trainings)],
        ];

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
            'bio' => $bio,
            'sections' => $sections,
            'counters' => $sections['counters'] ? $counters : [],
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

        return self::MONTHS[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
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
