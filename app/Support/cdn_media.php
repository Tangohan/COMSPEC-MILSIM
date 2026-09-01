<?php

declare(strict_types=1);

/**
 * Résolution des packs CDN média (icônes, emoji, gif, drapeaux, animations…).
 */

if (!function_exists('cdn_libraries_config')) {
    /**
     * @return array{version?: string, defaults?: list<string>, defaults_forum?: list<string>, packs?: array<string, mixed>}
     */
    function cdn_libraries_config(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $path = base_path('config/cdn_libraries.php');
            $cfg = is_file($path) ? (require $path) : [];
            if (!is_array($cfg)) {
                $cfg = [];
            }
        }

        return $cfg;
    }
}

if (!function_exists('cdn_resolve_packs')) {
    /**
     * @param list<string>|string|bool|null $requested
     * @return list<string>
     */
    function cdn_resolve_packs(array|string|bool|null $requested = null, ?string $preset = null): array
    {
        $cfg = cdn_libraries_config();
        $packsDef = is_array($cfg['packs'] ?? null) ? $cfg['packs'] : [];

        if ($requested === false || $requested === 'none') {
            return [];
        }

        if ($requested === null || $requested === '') {
            if ($preset === 'forum') {
                $requested = $cfg['defaults_forum'] ?? ['emoji', 'gif', 'flags'];
            } else {
                $requested = $cfg['defaults'] ?? [];
            }
        }

        if (is_string($requested)) {
            $requested = preg_split('/[\s,|]+/', $requested, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if ($requested === []) {
            return [];
        }

        $requested = array_values(array_filter(array_map(
            static fn ($p) => strtolower(trim((string) $p)),
            $requested
        )));

        if (in_array('all', $requested, true)) {
            $requested = array_keys($packsDef);
        }

        $resolved = [];
        $visit = static function (string $name) use (&$visit, &$resolved, $packsDef): void {
            if ($name === '' || isset($resolved[$name]) || !isset($packsDef[$name])) {
                return;
            }
            $depends = $packsDef[$name]['depends'] ?? [];
            if (is_array($depends)) {
                foreach ($depends as $dep) {
                    $visit((string) $dep);
                }
            }
            $resolved[$name] = true;
        };

        foreach ($requested as $name) {
            $visit($name);
        }

        return array_keys($resolved);
    }
}

if (!function_exists('cdn_collect_assets')) {
    /**
     * Collecte les assets CSS/JS pour une phase (head|body).
     *
     * @param list<string> $packNames
     * @return list<array{type: string, href?: string, src?: string, attrs?: array<string, mixed>}>
     */
    function cdn_collect_assets(array $packNames, string $phase = 'head'): array
    {
        $cfg = cdn_libraries_config();
        $packsDef = is_array($cfg['packs'] ?? null) ? $cfg['packs'] : [];
        $out = [];
        $seen = [];

        foreach ($packNames as $name) {
            $assets = $packsDef[$name]['assets'] ?? [];
            if (!is_array($assets)) {
                continue;
            }
            foreach ($assets as $asset) {
                if (!is_array($asset) || ($asset['phase'] ?? 'head') !== $phase) {
                    continue;
                }
                $key = ($asset['type'] ?? '') . '|' . ($asset['href'] ?? $asset['src'] ?? '');
                if ($key === '|' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $asset;
            }
        }

        return $out;
    }
}

if (!function_exists('cdn_render_attr')) {
    /**
     * @param array<string, mixed> $attrs
     */
    function cdn_render_attr(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $k => $v) {
            if ($v === false || $v === null) {
                continue;
            }
            $name = htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8');
            if ($v === true) {
                $html .= ' ' . $name;
                continue;
            }
            $html .= ' ' . $name . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
        }

        return $html;
    }
}

if (!function_exists('athena_flag_html')) {
    /**
     * Drapeau HTML (flag-icons CSS ou image flagcdn).
     *
     * @param string $iso Code pays ISO alpha-2 (ex. fr)
     * @param array{mode?: string, class?: string, title?: string, label?: string, size?: string} $opts
     */
    function athena_flag_html(string $iso, array $opts = []): string
    {
        $iso = strtolower(trim($iso));
        if (!preg_match('/^[a-z]{2}$/', $iso)) {
            return '';
        }
        $mode = $opts['mode'] ?? 'css';
        $title = htmlspecialchars((string) ($opts['title'] ?? strtoupper($iso)), ENT_QUOTES, 'UTF-8');
        $class = htmlspecialchars(trim((string) ($opts['class'] ?? '')), ENT_QUOTES, 'UTF-8');

        if ($mode === 'img') {
            $size = preg_replace('/[^0-9x]/', '', (string) ($opts['size'] ?? '24x18')) ?: '24x18';
            $parts = explode('x', $size);
            $w = (int) ($parts[0] ?? 24);
            $h = (int) ($parts[1] ?? 18);
            $alt = htmlspecialchars((string) ($opts['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $cls = trim('athena-flag athena-flag--img ' . $class);

            return '<img class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" src="https://flagcdn.com/'
                . htmlspecialchars($size, ENT_QUOTES, 'UTF-8') . '/' . $iso . '.png" width="' . $w
                . '" height="' . $h . '" alt="' . $alt . '" title="' . $title . '" loading="lazy" decoding="async">';
        }

        $cls = trim('fi fi-' . $iso . ' athena-flag ' . $class);
        $label = isset($opts['label']) ? htmlspecialchars((string) $opts['label'], ENT_QUOTES, 'UTF-8') : '';
        if ($label !== '') {
            return '<span class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" role="img" aria-label="'
                . $label . '" title="' . $title . '"></span>';
        }

        return '<span class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" title="' . $title
            . '" aria-hidden="true"></span>';
    }
}

if (!function_exists('athena_icon_html')) {
    /**
     * Icône Iconify / Heroicons / Lucide (balise web component ou data-lucide).
     *
     * @param string $name Ex. "heroicons:outline:home", "mdi:shield", "lucide:map" (data-lucide=map)
     * @param array{size?: int|string, class?: string} $opts
     */
    function athena_icon_html(string $name, array $opts = []): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $size = $opts['size'] ?? 20;
        $class = htmlspecialchars(trim((string) ($opts['class'] ?? '')), ENT_QUOTES, 'UTF-8');

        // Préfixe lucide: → data-lucide
        if (str_starts_with($name, 'lucide:')) {
            $icon = substr($name, 7);
            $cls = trim('h-5 w-5 ' . $class);

            return '<i data-lucide="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" class="'
                . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>';
        }

        $w = is_numeric($size) ? (string) (int) $size : htmlspecialchars((string) $size, ENT_QUOTES, 'UTF-8');

        return '<iconify-icon icon="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" width="' . $w
            . '" height="' . $w . '"' . ($class !== '' ? ' class="' . $class . '"' : '')
            . ' aria-hidden="true"></iconify-icon>';
    }
}

if (!function_exists('athena_emoji_html')) {
    /** Enveloppe un emoji pour parsing Twemoji. */
    function athena_emoji_html(string $emoji, string $class = ''): string
    {
        $cls = trim('athena-emoji ' . $class);

        return '<span class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" data-athena-emoji>'
            . htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
