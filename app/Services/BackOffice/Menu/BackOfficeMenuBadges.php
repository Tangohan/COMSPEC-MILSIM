<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu;

use App\Support\ShortCache;

/**
 * Pastilles du menu back-office : lit config/back_office_menu.php, appelle un fournisseur par clé et met le
 * résultat en cache court, par communauté et par personne.
 *
 * Une pastille n'est calculée que si la personne a l'un des droits déclarés ET voit la page dans son menu : un
 * compteur ne révèle jamais une file qu'elle ne peut pas ouvrir. Un fournisseur en erreur donne 0, jamais une page
 * cassée.
 */
final class BackOfficeMenuBadges
{
    public const TONE_NOTIF = 'notif';
    public const TONE_IMPORTANT = 'important';

    /** @var array<string, mixed> */
    private array $config;

    /** @var array<class-string, BadgeProvider> */
    private array $providers = [];

    /**
     * @param array<string, mixed>|null $config contenu de config/back_office_menu.php (tests : tableau en dur)
     * @param array<class-string, BadgeProvider> $providers fournisseurs déjà construits (tests)
     */
    public function __construct(?array $config = null, array $providers = [])
    {
        $this->config = $config ?? self::loadConfig();
        $this->providers = $providers;
    }

    /** @return array<string, mixed> */
    public static function loadConfig(): array
    {
        $file = dirname(__DIR__, 4) . '/config/back_office_menu.php';
        $cfg = is_file($file) ? require $file : [];

        return is_array($cfg) ? $cfg : [];
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->config;
    }

    public function pollSeconds(): int
    {
        return max(0, (int) ($this->config['poll_seconds'] ?? 0));
    }

    /**
     * Clés de pastilles que cette personne a le droit de voir.
     *
     * @param callable(string): bool $allows droit (slug) de la personne dans la communauté courante
     * @param callable(string): bool $pathVisible la page est-elle dans son menu
     * @return list<string>
     */
    public function visibleKeys(callable $allows, callable $pathVisible): array
    {
        $keys = [];
        foreach ($this->declared() as $key => $def) {
            $perms = $def['any_permissions'];
            $ok = $perms === [];
            foreach ($perms as $perm) {
                if ($allows($perm)) {
                    $ok = true;
                    break;
                }
            }
            if ($ok && $pathVisible($def['path'])) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param callable(string): bool $allows
     * @param callable(string): bool $pathVisible
     * @return array<string, array{count: int, important: bool, title: string, path: string}>
     */
    public function compute(int $tenantId, int $userId, callable $allows, callable $pathVisible): array
    {
        if ($tenantId < 1 || $userId < 1) {
            return [];
        }
        $keys = $this->visibleKeys($allows, $pathVisible);
        if ($keys === []) {
            return [];
        }
        $ttl = max(0, (int) ($this->config['cache_seconds'] ?? 45));
        $cacheKey = 'bo_menu_badges:' . $tenantId . ':' . $userId . ':' . substr(md5(implode(',', $keys)), 0, 12);

        return ShortCache::remember($cacheKey, $ttl, fn (): array => $this->computeKeys($tenantId, $userId, $keys));
    }

    /**
     * @param list<string> $keys
     * @return array<string, array{count: int, important: bool, title: string, path: string}>
     */
    public function computeKeys(int $tenantId, int $userId, array $keys): array
    {
        $declared = $this->declared();
        $out = [];
        foreach ($keys as $key) {
            $def = $declared[$key] ?? null;
            if ($def === null) {
                continue;
            }
            $count = 0;
            $important = $def['tone'] === self::TONE_IMPORTANT;
            try {
                $res = $this->provider($def['provider'])?->badge($tenantId, $userId);
                if (is_array($res)) {
                    $count = max(0, (int) ($res['count'] ?? 0));
                    if (array_key_exists('important', $res)) {
                        $important = (bool) $res['important'];
                    }
                }
            } catch (\Throwable $e) {
                error_log('[bo-menu] pastille ' . $key . ' : ' . $e->getMessage());
                $count = 0;
            }
            $out[$key] = [
                'count' => $count,
                'important' => $count > 0 && $important,
                'title' => self::title($def['title'], $count),
                'path' => $def['path'],
            ];
        }

        return $out;
    }

    /** Texte d'une pastille : 1 à 9, puis « 9+ ». */
    public static function format(int $n): string
    {
        if ($n < 1) {
            return '';
        }

        return $n > 9 ? '9+' : (string) $n;
    }

    public static function title(string $pattern, int $n): string
    {
        return str_contains($pattern, '%d') ? str_replace('%d', (string) $n, $pattern) : $pattern;
    }

    /**
     * Déclarations valides (chemin, fournisseur, droits, ton, titre).
     *
     * @return array<string, array{path: string, provider: string, any_permissions: list<string>, tone: string, title: string}>
     */
    public function declared(): array
    {
        $out = [];
        $badges = is_array($this->config['badges'] ?? null) ? $this->config['badges'] : [];
        foreach ($badges as $key => $def) {
            if (!is_string($key) || !is_array($def)) {
                continue;
            }
            $path = trim((string) ($def['path'] ?? ''), '/');
            $provider = (string) ($def['provider'] ?? '');
            if ($path === '' || $provider === '') {
                continue;
            }
            $perms = is_array($def['any_permissions'] ?? null) ? array_values(array_map('strval', $def['any_permissions'])) : [];
            $tone = (string) ($def['tone'] ?? self::TONE_NOTIF) === self::TONE_IMPORTANT ? self::TONE_IMPORTANT : self::TONE_NOTIF;
            $out[$key] = [
                'path' => $path,
                'provider' => $provider,
                'any_permissions' => $perms,
                'tone' => $tone,
                'title' => (string) ($def['title'] ?? ''),
            ];
        }

        return $out;
    }

    private function provider(string $class): ?BadgeProvider
    {
        if (!isset($this->providers[$class])) {
            if (!class_exists($class)) {
                return null;
            }
            $instance = new $class();
            if (!$instance instanceof BadgeProvider) {
                return null;
            }
            $this->providers[$class] = $instance;
        }

        return $this->providers[$class];
    }
}
