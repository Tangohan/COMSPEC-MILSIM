<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu;

use App\Repositories\BackOfficeNavPrefsRepository;

/**
 * État du menu back-office pour la personne connectée : pastilles (cache court), pages nouvelles pas encore vues,
 * favoris encore autorisés. Une lecture de bo_user_nav_prefs par page ; écriture seulement à la première visite
 * d'une page nouvelle.
 */
final class BackOfficeMenuState
{
    public function __construct(
        private BackOfficeNavPrefsRepository $prefs,
        private BackOfficeMenuBadges $badges,
    ) {
    }

    /** Titre d'une page déclarée dans config/back_office_pages.php (correspondance exacte), '' sinon. */
    public static function pageTitle(string $path): string
    {
        static $titles = null;
        if ($titles === null) {
            $titles = [];
            $file = dirname(__DIR__, 4) . '/config/back_office_pages.php';
            $cfg = is_file($file) ? require $file : [];
            foreach ((is_array($cfg['pages'] ?? null) ? $cfg['pages'] : []) as $page) {
                if (is_array($page) && isset($page['path'], $page['title'])) {
                    $titles[trim((string) $page['path'], '/')] ??= (string) $page['title'];
                }
            }
        }

        return $titles[trim($path, '/')] ?? '';
    }

    public function badges(): BackOfficeMenuBadges
    {
        return $this->badges;
    }

    public function favoritesMax(): int
    {
        return max(1, min(50, (int) ($this->badges->config()['favorites_max'] ?? FavoritesList::DEFAULT_MAX)));
    }

    /** @return list<array{path: string, label: string}> */
    public function favorites(int $tenantId, int $userId): array
    {
        return FavoritesList::sanitize($this->prefs->get($tenantId, $userId)[BackOfficeNavPrefsRepository::COL_FAVORITES], $this->favoritesMax());
    }

    /** @param list<array{path: string, label: string}> $list */
    public function saveFavorites(int $tenantId, int $userId, array $list): bool
    {
        return $this->prefs->put($tenantId, $userId, BackOfficeNavPrefsRepository::COL_FAVORITES, array_values($list));
    }

    /**
     * Pages « Nouveau » à afficher ; la page courante, si elle en fait partie, est notée comme vue.
     *
     * @return list<string>
     */
    public function newPaths(int $tenantId, int $userId, string $currentPath, ?int $now = null): array
    {
        $cfg = $this->badges->config();
        $declared = is_array($cfg['new'] ?? null) ? $cfg['new'] : [];
        if ($declared === [] || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $now ??= time();
        $days = (int) ($cfg['new_days'] ?? 30);
        $rawSeen = $this->prefs->get($tenantId, $userId)[BackOfficeNavPrefsRepository::COL_SEEN];
        $seen = is_array($rawSeen) ? array_values(array_filter($rawSeen, 'is_string')) : [];
        $active = MenuDecorator::activeNew($declared, $days, $seen, $now);
        $visited = MenuDecorator::visitedNew($active, $currentPath);
        if ($visited !== null) {
            // Ne garder que les pages encore déclarées : la liste reste courte.
            $declaredPaths = array_map(static fn (array $r): string => trim((string) ($r['path'] ?? ''), '/'), $declared);
            $seen = array_values(array_intersect(array_unique(array_merge($seen, [$visited])), $declaredPaths));
            $this->prefs->put($tenantId, $userId, BackOfficeNavPrefsRepository::COL_SEEN, $seen);
            $active = array_values(array_diff($active, [$visited]));
        }

        return $active;
    }
}
