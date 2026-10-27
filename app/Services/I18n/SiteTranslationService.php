<?php

declare(strict_types=1);

namespace App\Services\I18n;

use App\Core\Gate;
use App\Repositories\SiteContributorRepository;

/**
 * Traduction communautaire du site (/traduire-site) : qui peut valider, enregistrement (validation directe ou
 * proposition), pages de textes pour l'espace de traduction, recompilation des dictionnaires (CommunityLanguages).
 *
 * Valident directement : l'administration du site et les traducteurs actifs de la langue (rôle de contributeur).
 * Tout autre membre connecté propose ; un traducteur de la langue accepte ou refuse.
 */
final class SiteTranslationService
{
    public const PER_PAGE = 40;
    public const FILTERS = ['all', 'todo', 'blank', 'done', 'suggested', 'mine'];

    public function __construct(private readonly SiteContributorRepository $repo = new SiteContributorRepository())
    {
    }

    public function repo(): SiteContributorRepository
    {
        return $this->repo;
    }

    public static function isPlatformAdmin(): bool
    {
        try {
            return Gate::getInstance()->isPlatformAdmin();
        } catch (\Throwable) {
            return false;
        }
    }

    public function canReview(int $userId, string $lang): bool
    {
        if ($userId < 1) {
            return false;
        }

        return self::isPlatformAdmin() || $this->repo->isTranslatorFor($userId, $lang);
    }

    /**
     * Langues ouvertes à la traduction : « en » (corrections de l'anglais) puis les langues créées par
     * l'administration, avec leur avancement.
     *
     * @return list<array{code: string, name: string, status: string, done: int, total: int, pending: int, blank: int}>
     */
    public function languages(): array
    {
        $total = count(CommunityLanguages::catalog());
        $blank = CommunityLanguages::missingEnglishCount();
        $counts = $this->repo->translatedCounts();
        $pending = $this->repo->pendingCounts();
        $out = [[
            'code' => CommunityLanguages::ENGLISH, 'name' => 'English', 'status' => 'corrections',
            'done' => $counts['en'] ?? 0, 'total' => $total, 'pending' => $pending['en'] ?? 0, 'blank' => $blank,
        ]];
        foreach ($this->repo->languages() as $row) {
            $code = (string) $row['code'];
            if ($code === CommunityLanguages::ENGLISH || !CommunityLanguages::validCode($code)) {
                continue;
            }
            $out[] = [
                'code' => $code, 'name' => (string) $row['name'], 'status' => (string) $row['status'],
                'done' => $counts[$code] ?? 0, 'total' => $total, 'pending' => $pending[$code] ?? 0, 'blank' => $blank,
            ];
        }

        return $out;
    }

    /** @return array{code: string, name: string, status: string, done: int, total: int, pending: int}|null */
    public function language(string $code): ?array
    {
        foreach ($this->languages() as $l) {
            if ($l['code'] === $code) {
                return $l;
            }
        }

        return null;
    }

    /**
     * Une page de textes de l'espace de traduction.
     *
     * @return array{total: int, page: int, pages: int, per_page: int, counts: array<string, int>, rows: list<array<string, mixed>>}
     */
    public function page(string $lang, string $query, string $filter, int $page, int $userId): array
    {
        $done = $this->repo->translations($lang);
        $sugg = $this->repo->pendingSuggestions($lang);
        $q = self::fold(trim($query));
        $filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
        $contexts = CommunityLanguages::contexts();
        $match = [];
        $first = [];
        // Compteurs de chaque filtre (avec la recherche en cours) pour les onglets de l'espace.
        $counts = array_fill_keys(self::FILTERS, 0);
        // Mémoire : même texte français déjà traduit ailleurs dans le site.
        $memory = [];
        foreach (CommunityLanguages::catalog() as [$h, $src, , $kind]) {
            if (isset($done[$h])) {
                $memory[$kind === 'key' ? CommunityLanguages::frenchOf($src) : $src] ??= (string) $done[$h];
            }
        }
        foreach (CommunityLanguages::catalog() as [$h, $src, $en, $kind]) {
            $hasTr = isset($done[$h]);
            $hasSugg = isset($sugg[$h]);
            $fr = $kind === 'key' ? CommunityLanguages::frenchOf($src) : $src;
            $bare = !CommunityLanguages::hasShippedEnglish($fr, $en);
            $ctx = $contexts[$h] ?? ['screen' => 'pages', 'label' => 'Pages du portail', 'context' => ''];
            if ($q !== '' && !str_contains(self::fold($fr . "\n" . $en . "\n" . ($done[$h] ?? '') . "\n" . $ctx['label'] . "\n" . $ctx['context']), $q) && $q !== $h) {
                continue;
            }
            $mine = $hasSugg && array_filter($sugg[$h], static fn (array $s): bool => $s['user_id'] === $userId) !== [];
            $counts['all']++;
            $counts[$hasTr ? 'done' : 'todo']++;
            $counts['blank'] += (!$hasTr && $bare) ? 1 : 0;
            $counts['suggested'] += $hasSugg ? 1 : 0;
            $counts['mine'] += $mine ? 1 : 0;
            $ok = match ($filter) {
                'todo' => !$hasTr,
                'blank' => !$hasTr && $bare,
                'done' => $hasTr,
                'suggested' => $hasSugg,
                'mine' => $mine,
                default => true,
            };
            if (!$ok) {
                continue;
            }
            $row = [$h, $fr, $en, $kind, $src, $bare, $ctx['screen'], $ctx['label'], $ctx['context']];
            if ($bare && in_array($filter, ['all', 'todo'], true)) {
                $first[] = $row;
            } else {
                $match[] = $row;
            }
        }
        $match = array_merge($first, $match);
        $total = count($match);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $pages));
        $rows = [];
        foreach (array_slice($match, ($page - 1) * self::PER_PAGE, self::PER_PAGE) as [$h, $fr, $en, $kind, $src, $bare, $screen, $screenLabel, $context]) {
            $rows[] = [
                'h' => $h,
                'fr' => $fr,
                'en' => $en,
                'bare' => $bare,
                'kind' => $kind,
                'key' => $kind === 'key' ? substr($src, 1) : '',
                'code' => CommunityLanguages::markup($fr) !== [],
                'tr' => $done[$h] ?? '',
                'memory' => !isset($done[$h]) && isset($memory[$fr]) ? $memory[$fr] : '',
                'screen' => $screen,
                'screen_label' => $screenLabel,
                'context' => $context,
                'sugg' => array_map(static fn (array $s): array => [
                    'id' => $s['id'], 'text' => $s['text'], 'author' => $s['author'], 'mine' => $s['user_id'] === $userId,
                ], $sugg[$h] ?? []),
            ];
        }

        return ['total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => self::PER_PAGE, 'counts' => $counts, 'rows' => $rows];
    }

    /**
     * Prochain texte encore seulement en français : d’abord le même écran, puis l’écran suivant.
     * La page renvoyée est celle du filtre « seulement en français », sans recherche.
     *
     * @return array{h: string, page: int}|null
     */
    public function nextBare(string $lang, string $hash): ?array
    {
        if (preg_match('/^[0-9a-f]{16}$/', $hash) !== 1) {
            return null;
        }
        $done = $this->repo->translations($lang);
        $contexts = CommunityLanguages::contexts();
        $screen = $contexts[$hash]['screen'] ?? '';
        $bare = [];
        $passed = false;
        $sameAfter = null;
        foreach (CommunityLanguages::catalog() as [$h, $src, $en, $kind]) {
            if ($h === $hash) {
                $passed = true;
                $screen = $contexts[$h]['screen'] ?? $screen;
            }
            $fr = $kind === 'key' ? CommunityLanguages::frenchOf($src) : $src;
            if (isset($done[$h]) || CommunityLanguages::hasShippedEnglish($fr, $en)) {
                continue;
            }
            $sc = $contexts[$h]['screen'] ?? 'pages';
            $bare[] = [$h, $sc];
            if ($passed && $h !== $hash && $sc === $screen && $sameAfter === null) {
                $sameAfter = count($bare) - 1;
            }
        }
        if ($bare === []) {
            return null;
        }
        $target = $sameAfter;
        if ($target === null) {
            $order = [];
            foreach ($bare as $i => [, $sc]) {
                if (!isset($order[$sc])) {
                    $order[$sc] = $i;
                }
            }
            $ids = array_keys($order);
            $pos = array_search($screen, $ids, true);
            if ($pos === false) {
                $target = 0;
            } else {
                $next = $ids[($pos + 1) % count($ids)];
                if ($next === $screen) {
                    return null;
                }
                $target = $order[$next];
            }
        }

        return ['h' => $bare[$target][0], 'page' => (int) floor($target / self::PER_PAGE) + 1];
    }

    /**
     * Enregistre un texte : validé (traducteur, administration) ou proposé (autre membre connecté).
     *
     * @return array{ok: bool, mode?: string, errors?: list<string>}
     */
    public function save(string $lang, string $hash, string $text, int $userId): array
    {
        $src = $this->sourceOf($hash);
        if ($src === null || $userId < 1) {
            return ['ok' => false, 'errors' => ['unknown']];
        }
        $text = str_replace("\r\n", "\n", $text);
        $errors = CommunityLanguages::check($src, $text);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        if ($this->canReview($userId, $lang)) {
            $this->repo->saveTranslation($lang, $hash, $text, $userId);
            $this->recompile($lang);

            return ['ok' => true, 'mode' => 'saved'];
        }
        $this->repo->suggest($lang, $hash, $text, $userId);

        return ['ok' => true, 'mode' => 'suggested'];
    }

    /** @return array{ok: bool, errors?: list<string>} */
    public function clear(string $lang, string $hash, int $userId): array
    {
        if (!$this->canReview($userId, $lang) || $this->sourceOf($hash) === null) {
            return ['ok' => false, 'errors' => ['forbidden']];
        }
        $this->repo->deleteTranslation($lang, $hash);
        $this->recompile($lang);

        return ['ok' => true];
    }

    /** @return array{ok: bool, errors?: list<string>} */
    public function decide(int $suggestionId, bool $accept, int $userId): array
    {
        $s = $this->repo->suggestion($suggestionId);
        if ($s === null || (string) $s['status'] !== 'pending') {
            return ['ok' => false, 'errors' => ['unknown']];
        }
        $lang = (string) $s['lang'];
        if (!$this->canReview($userId, $lang)) {
            return ['ok' => false, 'errors' => ['forbidden']];
        }
        if (!$accept) {
            $this->repo->rejectSuggestion($suggestionId, $userId);

            return ['ok' => true];
        }
        $src = $this->sourceOf((string) $s['src_hash']);
        if ($src === null || CommunityLanguages::check($src, (string) $s['text']) !== []) {
            return ['ok' => false, 'errors' => ['invalid']];
        }
        // L'auteur de la proposition reste l'auteur de la traduction validée.
        $this->repo->saveTranslation($lang, (string) $s['src_hash'], (string) $s['text'], (int) $s['user_id']);
        $this->recompile($lang);

        return ['ok' => true];
    }

    public function recompile(string $lang): int
    {
        try {
            return CommunityLanguages::compile($lang, $this->repo->translations($lang));
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Réécrit la liste des langues publiées lue par le sélecteur de langue. */
    public function syncPublished(): void
    {
        CommunityLanguages::writePublished($this->repo->publishedLanguages());
    }

    private function sourceOf(string $hash): ?string
    {
        if (preg_match('/^[0-9a-f]{16}$/', $hash) !== 1) {
            return null;
        }
        foreach (CommunityLanguages::catalog() as [$h, $src]) {
            if ($h === $hash) {
                return $src;
            }
        }

        return null;
    }

    private static function fold(string $s): string
    {
        $s = mb_strtolower($s);
        $t = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) : false;

        return is_string($t) && $t !== '' ? $t : $s;
    }
}
