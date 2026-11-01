<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\AtakTranslationPackRepository;
use App\Repositories\SiteContributorRepository;
use App\Services\Billing\DonorService;

/**
 * Contenu public de la page /contribuer : mur des contributeurs, nouveaux venus, plus actifs, donateurs,
 * packs de langue ATAK et chiffres.
 *
 * Uniquement ce que chacun a accepté de rendre public : rôles actifs cochés « citer mon nom », mur des
 * donateurs (déjà filtré par DonorService : masqués, anonymes et dons non publics exclus), packs de langue
 * publiés au Workshop (nom d'auteur choisi pour le Workshop). Nom public seulement (pseudo, sinon indicatif) :
 * jamais d'e-mail, de prénom, de nom ni de montant.
 */
final class ContributorShowcase
{
    /** Arrivé depuis moins de N jours : « nouveau ». */
    public const NEW_DAYS = 90;

    /**
     * Assemble la page (pur : testable sans base).
     *
     * @param list<array<string, mixed>> $wall SiteContributorRepository::publicWall()
     * @param list<array<string, mixed>> $leaders SiteContributorRepository::translationLeaders()
     * @param list<array<string, mixed>> $donors DonorService::board()
     * @param list<array<string, mixed>> $packs AtakTranslationPackRepository::catalog()
     * @param array{contributors?: int, texts?: int, languages?: int, communities?: int} $counts
     * @return array{people: list<array<string, mixed>>, newcomers: list<array<string, mixed>>, active: list<array<string, mixed>>, donors: list<array<string, mixed>>, packs: list<array<string, mixed>>, stats: array<string, int>, roles: array<string, int>}
     */
    public static function build(array $wall, array $leaders, array $donors, array $packs, array $counts = [], ?int $now = null): array
    {
        $now ??= time();
        $texts = [];
        $active = [];
        foreach ($leaders as $leader) {
            if (empty($leader['public_credit'])) {
                continue;
            }
            $uid = (int) ($leader['user_id'] ?? 0);
            $name = self::cleanName((string) ($leader['name'] ?? ''));
            if ($uid < 1 || $name === '' || (int) ($leader['validated'] ?? 0) < 1) {
                continue;
            }
            $texts[$uid] = (int) $leader['validated'];
            if (count($active) < 3) {
                $active[] = [
                    'user_id' => $uid,
                    'name' => $name,
                    'texts' => (int) $leader['validated'],
                    'roles' => array_values(array_filter((array) ($leader['public_roles'] ?? []), 'is_string')),
                    'monogram' => self::monogram($name),
                    'hue' => self::hue($name),
                ];
            }
        }

        $people = [];
        $roleCounts = array_fill_keys(SiteContributorRepository::ROLES, 0);
        foreach ($wall as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $name = self::cleanName((string) ($row['name'] ?? ''));
            $roles = array_values(array_filter(
                (array) ($row['roles'] ?? []),
                static fn ($r): bool => is_string($r) && in_array($r, SiteContributorRepository::ROLES, true)
            ));
            if ($uid < 1 || $name === '' || $roles === []) {
                continue;
            }
            foreach ($roles as $role) {
                $roleCounts[$role]++;
            }
            $since = isset($row['since']) && is_string($row['since']) && $row['since'] !== '' ? $row['since'] : null;
            $sinceTs = $since !== null ? strtotime($since) : false;
            $langs = array_values(array_filter(array_map(
                static fn (string $c): string => strtoupper(trim($c)),
                explode(',', (string) ($row['languages'] ?? ''))
            ), static fn (string $c): bool => preg_match('/^[A-Z]{2,3}(-[A-Z]{2})?$/', $c) === 1));
            $people[] = [
                'user_id' => $uid,
                'name' => $name,
                'roles' => $roles,
                'languages' => $langs,
                'since' => $since,
                'is_new' => $sinceTs !== false && $sinceTs >= $now - self::NEW_DAYS * 86400,
                'texts' => $texts[$uid] ?? 0,
                'monogram' => self::monogram($name),
                'hue' => self::hue($name),
            ];
        }
        // Plus actifs d'abord (textes validés), puis ancienneté, puis nom.
        usort($people, static fn (array $a, array $b): int => [$b['texts'], $a['since'] ?? '9', mb_strtolower($a['name'])] <=> [$a['texts'], $b['since'] ?? '9', mb_strtolower($b['name'])]);

        $newcomers = array_values(array_filter($people, static fn (array $p): bool => $p['is_new']));
        usort($newcomers, static fn (array $a, array $b): int => strcmp((string) $b['since'], (string) $a['since']));
        $newcomers = array_slice($newcomers, 0, 4);

        $donorRows = [];
        foreach ($donors as $d) {
            $name = self::cleanName((string) ($d['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $donorRows[] = [
                'name' => $name,
                'tier' => (string) ($d['tier'] ?? ''),
                'tier_label' => DonorService::tierLabel(isset($d['tier']) ? (string) $d['tier'] : null),
                'community' => mb_substr(trim((string) ($d['community'] ?? '')), 0, 60),
                'community_slug' => (string) ($d['community_slug'] ?? ''),
                'monogram' => self::monogram($name),
                'hue' => self::hue($name),
            ];
        }

        $packRows = [];
        foreach ($packs as $p) {
            $author = self::cleanName((string) ($p['author_label'] ?? ''));
            $lang = trim((string) ($p['lang_name'] ?? ''));
            if ($lang === '') {
                continue;
            }
            $packRows[] = [
                'id' => (string) ($p['public_id'] ?? ''),
                'lang' => mb_substr($lang, 0, 40),
                'author' => $author,
                'downloads' => (int) ($p['downloads'] ?? 0),
            ];
        }
        usort($packRows, static fn (array $a, array $b): int => $b['downloads'] <=> $a['downloads']);

        return [
            'people' => $people,
            'newcomers' => $newcomers,
            'active' => $active,
            'donors' => array_slice($donorRows, 0, 30),
            'packs' => array_slice($packRows, 0, 8),
            'roles' => $roleCounts,
            'stats' => [
                'contributors' => max((int) ($counts['contributors'] ?? 0), count($people)),
                'texts' => (int) ($counts['texts'] ?? 0),
                'languages' => (int) ($counts['languages'] ?? 0) + count($packRows),
                'donors' => count($donorRows),
                'communities' => (int) ($counts['communities'] ?? 0),
            ],
        ];
    }

    /** Lit les sources (chaque source absente est simplement vide). */
    public static function load(?SiteContributorRepository $repo = null, ?AtakTranslationPackRepository $packsRepo = null): array
    {
        $wall = [];
        $leaders = [];
        $counts = [];
        try {
            $repo ??= new SiteContributorRepository();
            if ($repo->schemaReady()) {
                $wall = $repo->publicWall();
                $leaders = $repo->translationLeaders(12);
                $counts['contributors'] = $repo->activeContributorCount();
                $counts['texts'] = array_sum($repo->translatedCounts());
                $counts['languages'] = count($repo->publishedLanguages());
            }
        } catch (\Throwable) {
        }
        $donors = [];
        try {
            $donors = DonorService::board();
            $counts['communities'] = count(DonorService::cached()['tenants']);
        } catch (\Throwable) {
        }
        $packs = [];
        try {
            $packsRepo ??= new AtakTranslationPackRepository();
            if ($packsRepo->workshopReady()) {
                $packs = $packsRepo->catalog();
            }
        } catch (\Throwable) {
        }

        return self::build($wall, $leaders, $donors, $packs, $counts);
    }

    /** Nom public prudent : une ligne, 40 caractères, jamais une adresse e-mail. */
    public static function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', strip_tags($name)));
        if ($name === '' || str_contains($name, '@') || $name === 'Membre') {
            return '';
        }

        return mb_substr($name, 0, 40);
    }

    /** Deux lettres de l'écusson (« Ghost Rider » → GR, « viper » → VI). */
    public static function monogram(string $name): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return '?';
        }
        $mono = count($words) > 1
            ? mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1)
            : mb_substr($words[0], 0, 2);

        return mb_strtoupper($mono);
    }

    /** Teinte stable de l'écusson, tirée du nom (0-359). */
    public static function hue(string $name): int
    {
        return (int) (crc32(mb_strtolower($name)) % 360);
    }
}
