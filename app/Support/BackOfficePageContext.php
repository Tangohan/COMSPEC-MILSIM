<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Résout automatiquement le contexte ATHENA (fil d'Ariane, en-tête, CSS) pour le back-office.
 */
final class BackOfficePageContext
{
    /**
     * Chemins qui utilisent la coque ATHENA (sidebar + topbar + en-tête).
     *
     * @return list<string>
     */
    public static function chromePathPrefixes(): array
    {
        return [
            'back-office',
            'formation',
            'documents/gestion',
            'admin/atak-config',
            'admin/modpacks',
            'admin/forum-config',
            'admin/content-moderation',
            'admin/atak-mod',
            'admin/atak-beta',
            'tableau-operationnel',
            'jnet',
        ];
    }

    public static function usesAthenaChrome(): bool
    {
        if (function_exists('is_back_office_request') && is_back_office_request()) {
            return true;
        }
        if (function_exists('is_formation_workspace_request') && is_formation_workspace_request()) {
            return true;
        }
        $path = self::currentPath();
        foreach (self::chromePathPrefixes() as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $vars Variables de la vue layout (titre, boPage*, etc.)
     * @return array<string, mixed>
     */
    public static function apply(array $vars): array
    {
        if (!self::usesAthenaChrome()) {
            return $vars;
        }

        $path = self::currentPath();
        $match = self::matchPage($path);
        $title = trim((string) ($vars['title'] ?? ''));

        if (empty($vars['boPageGroup']) && !empty($match['group'])) {
            $vars['boPageGroup'] = (string) $match['group'];
        }
        if (empty($vars['boPageTitle'])) {
            $vars['boPageTitle'] = !empty($match['title']) ? (string) $match['title'] : ($title !== '' ? $title : 'Administration');
        }
        if (empty($vars['boPageKicker']) && !empty($match['kicker'])) {
            $vars['boPageKicker'] = (string) $match['kicker'];
        }
        if (empty($vars['boPageSubtitle']) && !empty($match['subtitle'])) {
            $vars['boPageSubtitle'] = (string) $match['subtitle'];
        }
        if (empty($vars['boPageQuick']) && !empty($match['quick']) && is_array($match['quick'])) {
            $vars['boPageQuick'] = self::resolveQuickLinks($match['quick']);
        }

        foreach ($match['flags'] ?? [] as $flag => $value) {
            if (empty($vars[$flag])) {
                $vars[$flag] = $value;
            }
        }

        $css = is_array($vars['backOfficePageCss'] ?? null) ? $vars['backOfficePageCss'] : [];
        if ($path === 'back-office' || $path === 'public/back-office') {
            $config = self::config();
            foreach ($config['dashboard_css'] ?? [] as $file) {
                $css[] = $file;
            }
        }
        foreach ($match['css'] ?? [] as $file) {
            $css[] = $file;
        }
        if ($css !== []) {
            $vars['backOfficePageCss'] = array_values(array_unique($css));
        }

        if (empty($vars['boSkipPageHead']) && self::shouldSkipPageHead($path)) {
            $vars['boSkipPageHead'] = true;
        }

        $vars['isBackOfficeShell'] = true;

        return $vars;
    }

    private static function currentPath(): string
    {
        return function_exists('back_office_path_suffix')
            ? trim((string) back_office_path_suffix(), '/')
            : '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function matchPage(string $path): array
    {
        $config = self::config();
        $best = [];
        $bestLen = -1;
        foreach ($config['pages'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rulePath = trim((string) ($row['path'] ?? ''), '/');
            if ($rulePath === '') {
                continue;
            }
            if ($path === $rulePath || str_starts_with($path, $rulePath . '/')) {
                $len = strlen($rulePath);
                if ($len > $bestLen) {
                    $bestLen = $len;
                    $best = $row;
                }
            }
        }
        if ($best !== []) {
            return $best;
        }

        return self::fallbackFromPath($path);
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallbackFromPath(string $path): array
    {
        $group = 'Administration';
        $kicker = 'ADMINISTRATION';
        if (str_starts_with($path, 'back-office/atak/') || str_contains($path, '/events') || str_starts_with($path, 'jnet')) {
            $group = 'Opérations';
            $kicker = str_starts_with($path, 'jnet') ? 'UNITÉ · EXTRANET' : 'OPÉRATIONS';
            if (str_starts_with($path, 'jnet')) {
                $group = 'Unité';
            }
        } elseif (str_starts_with($path, 'back-office/users') || str_starts_with($path, 'formation/')) {
            $group = 'Personnel';
            $kicker = 'PERSONNEL';
        } elseif (str_starts_with($path, 'back-office/audit') || str_contains($path, 'roles') || str_contains($path, 'moderation')) {
            $group = 'Système';
            $kicker = 'SYSTÈME';
        } elseif (str_starts_with($path, 'back-office/ressources/') || str_starts_with($path, 'documents/')) {
            $group = 'Ressources';
            $kicker = 'RESSOURCES';
        } elseif (str_starts_with($path, 'back-office/community') || str_starts_with($path, 'back-office/organisation/parametres')) {
            $group = 'Communauté';
            $kicker = 'COMMUNAUTÉ';
        }

        return ['group' => $group, 'kicker' => $kicker];
    }

    private static function shouldSkipPageHead(string $path): bool
    {
        $config = self::config();
        foreach ($config['skip_page_head_paths'] ?? [] as $prefix) {
            $prefix = trim((string) $prefix, '/');
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                if (preg_match('#/comptes-rendus/\d+$#', $path) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array{label:string,href:string}> $quick
     * @return list<array{label:string,href:string}>
     */
    private static function resolveQuickLinks(array $quick): array
    {
        $out = [];
        foreach ($quick as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $href = trim((string) ($item['href'] ?? ''));
            if ($label === '' || $href === '') {
                continue;
            }
            $out[] = [
                'label' => $label,
                'href' => function_exists('url') ? url($href) : '/' . ltrim($href, '/'),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $file = dirname(__DIR__, 2) . '/config/back_office_pages.php';
        $cache = is_file($file) ? (require $file) : [];

        return is_array($cache) ? $cache : [];
    }
}
