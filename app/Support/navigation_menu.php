<?php

declare(strict_types=1);

/**
 * Construction du menu portail : permissions Gate, url(), état actif.
 */
function navigation_raw_config(): array
{
    $path = base_path('config/navigation.php');
    if (!is_file($path)) {
        return ['brand' => [], 'search' => [], 'menu' => []];
    }

    return require $path;
}

function navigation_route_path(string $pathFragment): string
{
    $pathFragment = trim($pathFragment, '/');
    if ($pathFragment === '') {
        return '/';
    }

    return '/' . $pathFragment;
}

function navigation_infer_active_match(string $routePath): string
{
    if (in_array($routePath, ['/admin', '/back-office', '/'], true)) {
        return 'exact';
    }

    return 'prefix';
}

/**
 * @param array{permission?: string, any_permissions?: string[], all_permissions?: string[]} $item
 */
function navigation_item_allowed(array $item): bool
{
    $gate = \App\Core\Gate::getInstance();
    if (!empty($item['permission'])) {
        return $gate->allows((string) $item['permission']);
    }
    if (!empty($item['any_permissions']) && is_array($item['any_permissions'])) {
        foreach ($item['any_permissions'] as $p) {
            if ($gate->allows((string) $p)) {
                return true;
            }
        }

        return false;
    }
    if (!empty($item['all_permissions']) && is_array($item['all_permissions'])) {
        foreach ($item['all_permissions'] as $p) {
            if (!$gate->allows((string) $p)) {
                return false;
            }
        }

        return true;
    }

    return true;
}

function navigation_menu_item_visible(array $item, bool $loggedIn): bool
{
    if (!empty($item['guest_only']) && $loggedIn) {
        return false;
    }
    if (!empty($item['auth_only']) && !$loggedIn) {
        return false;
    }
    if (!empty($item['any_permissions']) && is_array($item['any_permissions'])) {
        if (!navigation_item_allowed($item)) {
            return false;
        }
    }

    return true;
}

/**
 * Résout un lien avec href + path canonique pour l’état actif.
 *
 * @param array{path: string, active_match?: string, label: string, description?: string} $link
 * @return array{label: string, href: string, path: string, active_match: string, description?: string}|null
 */
function navigation_resolve_link(array $link): ?array
{
    if (!navigation_item_allowed($link)) {
        return null;
    }
    $pathFragment = (string) ($link['path'] ?? '');
    $routePath = navigation_route_path($pathFragment);
    $match = $link['active_match'] ?? navigation_infer_active_match($routePath);

    return [
        'label' => (string) ($link['label'] ?? ''),
        'href' => url($pathFragment),
        'path' => $routePath,
        'active_match' => $match,
        'description' => isset($link['description']) ? (string) $link['description'] : null,
    ];
}

function navigation_normalize_accent(?string $accent): string
{
    $allowed = ['sky', 'amber', 'emerald', 'violet', 'rose', 'slate'];
    $a = strtolower(trim((string) ($accent ?? '')));

    return in_array($a, $allowed, true) ? $a : 'slate';
}

function navigation_normalize_variant(?string $variant): string
{
    $allowed = ['operations', 'resources', 'personnel', 'training', 'admin'];
    $v = strtolower(trim((string) ($variant ?? '')));

    return in_array($v, $allowed, true) ? $v : 'operations';
}

/**
 * Classes CSS pour le CTA du panneau latéral (définies dans portal-nav.css).
 */
function navigation_cta_intent_class(string $intent): string
{
    return match ($intent) {
        'monitor' => 'nav-cta nav-cta--monitor',
        'resume' => 'nav-cta nav-cta--resume',
        'administer' => 'nav-cta nav-cta--administer',
        'view_activity' => 'nav-cta nav-cta--view_activity',
        'access' => 'nav-cta nav-cta--access',
        default => 'nav-cta nav-cta--open',
    };
}

/**
 * @param array<string, mixed> $featured
 * @return array<string, mixed>|null
 */
function navigation_resolve_featured(array $featured): ?array
{
    if ($featured === []) {
        return null;
    }

    $intent = strtolower(trim((string) ($featured['cta_intent'] ?? 'open')));
    $out = [
        'eyebrow' => isset($featured['eyebrow']) ? (string) $featured['eyebrow'] : '',
        'title' => (string) ($featured['title'] ?? ''),
        'description' => (string) ($featured['description'] ?? ''),
        'image' => isset($featured['image']) ? (string) $featured['image'] : '',
        'image_enabled' => !empty($featured['image_enabled']),
        'image_position' => (string) ($featured['image_position'] ?? 'center'),
        'overlay' => (string) ($featured['overlay'] ?? 'dark'),
        'cta_label' => isset($featured['cta_label']) ? (string) $featured['cta_label'] : '',
        'cta_intent' => $intent,
        'cta_classes' => navigation_cta_intent_class($intent),
        'cta_href' => null,
        'cta_path' => null,
    ];

    if (empty($featured['cta_path'])) {
        return $out;
    }

    $perm = $featured['cta_permission'] ?? null;
    if ($perm !== null && $perm !== '' && !\App\Core\Gate::getInstance()->allows((string) $perm)) {
        return $out;
    }

    $out['cta_href'] = url((string) $featured['cta_path']);
    $out['cta_path'] = navigation_route_path((string) $featured['cta_path']);

    return $out;
}

/**
 * @param list<mixed> $liveRaw
 * @return list<array{id: string, type: string, enabled: bool, title: string, empty_message: string}>
 */
function navigation_normalize_live_blocks(array $liveRaw): array
{
    $out = [];
    foreach ($liveRaw as $block) {
        if (!is_array($block)) {
            continue;
        }
        $id = trim((string) ($block['id'] ?? ''));
        if ($id === '') {
            $id = 'live_' . count($out);
        }
        $out[] = [
            'id' => $id,
            'type' => (string) ($block['type'] ?? 'placeholder'),
            'enabled' => !empty($block['enabled']),
            'title' => (string) ($block['title'] ?? ''),
            'empty_message' => (string) ($block['empty_message'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @param list<array{title: string, slot: string, links: list<mixed>}> $sections
 * @return array{primary: list<array>, center: list<array>, secondary: list<array>}
 */
function navigation_group_sections_by_slot(array $sections): array
{
    $g = ['primary' => [], 'center' => [], 'secondary' => []];
    foreach ($sections as $sec) {
        if (!is_array($sec)) {
            continue;
        }
        $slot = strtolower((string) ($sec['slot'] ?? 'primary'));
        if (!isset($g[$slot])) {
            $slot = 'primary';
        }
        $g[$slot][] = $sec;
    }

    return $g;
}

function navigation_public_image_url(string $relativePath): string
{
    $relativePath = ltrim($relativePath, '/');

    return url($relativePath);
}

function navigation_image_file_exists(string $relativePath): bool
{
    $relativePath = ltrim($relativePath, '/');

    return is_file(base_path('public/' . $relativePath));
}

/**
 * @return array{brand: array{name: string, subtitle: string, href: string}, search: array{enabled: bool, placeholder: string, action: string, method: string, param: string}, menu: list<array<string, mixed>>}
 */
function build_navigation_menu(): array
{
    $raw = navigation_raw_config();
    $loggedIn = (bool) \App\Core\Session::get('user_id');

    $brand = $raw['brand'] ?? [];
    $brandPath = (string) ($brand['path'] ?? '');
    $builtBrand = [
        'name' => (string) ($brand['name'] ?? 'Athena'),
        'subtitle' => (string) ($brand['subtitle'] ?? ''),
        'tagline' => (string) ($brand['tagline'] ?? ''),
        'href' => url($brandPath),
    ];

    $searchRaw = $raw['search'] ?? [];
    $searchPath = (string) ($searchRaw['path'] ?? 'search');
    $builtSearch = [
        'enabled' => !empty($searchRaw['enabled']) && $loggedIn,
        'placeholder' => (string) ($searchRaw['placeholder'] ?? ''),
        'action' => url($searchPath),
        'method' => strtolower((string) ($searchRaw['method'] ?? 'get')),
        'param' => (string) ($searchRaw['param'] ?? 'q'),
    ];

    $menuOut = [];
    foreach ($raw['menu'] ?? [] as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!navigation_menu_item_visible($item, $loggedIn)) {
            continue;
        }

        $type = (string) ($item['type'] ?? 'link');
        if ($type === 'link') {
            $link = navigation_resolve_link(array_merge(
                [
                    'path' => (string) ($item['path'] ?? ''),
                    'label' => (string) ($item['label'] ?? ''),
                ],
                array_filter([
                    'active_match' => $item['active_match'] ?? null,
                    'permission' => $item['permission'] ?? null,
                    'any_permissions' => $item['any_permissions'] ?? null,
                ], static fn ($v) => $v !== null)
            ));
            if ($link === null) {
                continue;
            }
            $menuOut[] = [
                'type' => 'link',
                'label' => (string) ($item['label'] ?? ''),
                'href' => $link['href'],
                'path' => $link['path'],
                'active_match' => $link['active_match'],
                'id' => 'nav-top-' . $idx,
            ];
            continue;
        }

        if ($type === 'mega') {
            $sectionsOut = [];
            foreach ($item['sections'] ?? [] as $section) {
                if (!is_array($section)) {
                    continue;
                }
                $linksOut = [];
                foreach ($section['links'] ?? [] as $link) {
                    if (!is_array($link)) {
                        continue;
                    }
                    $resolved = navigation_resolve_link($link);
                    if ($resolved !== null) {
                        $linksOut[] = $resolved;
                    }
                }
                if ($linksOut !== []) {
                    $slot = strtolower(trim((string) ($section['slot'] ?? 'primary')));
                    if (!in_array($slot, ['primary', 'center', 'secondary'], true)) {
                        $slot = 'primary';
                    }
                    $sectionsOut[] = [
                        'title' => (string) ($section['title'] ?? ''),
                        'slot' => $slot,
                        'links' => $linksOut,
                    ];
                }
            }

            $featured = null;
            if (!empty($item['featured']) && is_array($item['featured'])) {
                $featured = navigation_resolve_featured($item['featured']);
            }

            $liveBlocks = [];
            if (!empty($item['live']) && is_array($item['live'])) {
                $liveBlocks = navigation_normalize_live_blocks($item['live']);
            }

            $hasContent = $sectionsOut !== [] || $featured !== null;
            if (!$hasContent) {
                continue;
            }

            $menuOut[] = [
                'type' => 'mega',
                'label' => (string) ($item['label'] ?? ''),
                'icon' => (string) ($item['icon'] ?? ''),
                'accent' => navigation_normalize_accent(isset($item['accent']) ? (string) $item['accent'] : null),
                'variant' => navigation_normalize_variant(isset($item['variant']) ? (string) $item['variant'] : null),
                'sections' => $sectionsOut,
                'live' => $liveBlocks,
                'featured' => $featured,
                'id' => 'nav-mega-' . $idx,
            ];
        }
    }

    return [
        'brand' => $builtBrand,
        'search' => $builtSearch,
        'menu' => $menuOut,
    ];
}

/**
 * @param array{path?: string, active_match?: string} $link
 */
function nav_link_is_active(array $link, string $currentPath): bool
{
    $rp = (string) ($link['path'] ?? '');
    $match = (string) ($link['active_match'] ?? navigation_infer_active_match($rp));

    return nav_path_matches($rp, $currentPath, $match);
}

function nav_path_matches(string $routePath, string $currentPath, string $match): bool
{
    if ($routePath === '/' || $routePath === '') {
        return $currentPath === '/' || $currentPath === '';
    }
    if ($match === 'exact') {
        return $currentPath === $routePath;
    }
    if ($currentPath === $routePath) {
        return true;
    }

    return str_starts_with($currentPath, $routePath . '/');
}

/**
 * @param array<string, mixed> $item
 */
function nav_item_is_active(array $item, string $currentPath): bool
{
    $type = (string) ($item['type'] ?? 'link');
    if ($type === 'link') {
        $path = (string) ($item['path'] ?? '');
        $routePath = navigation_route_path($path);
        $match = (string) ($item['active_match'] ?? navigation_infer_active_match($routePath));

        return nav_path_matches($routePath, $currentPath, $match);
    }

    if ($type === 'mega') {
        foreach ($item['sections'] ?? [] as $section) {
            foreach ($section['links'] ?? [] as $link) {
                if (!is_array($link)) {
                    continue;
                }
                if (nav_link_is_active($link, $currentPath)) {
                    return true;
                }
            }
        }
        $feat = $item['featured'] ?? null;
        if (is_array($feat) && !empty($feat['cta_path'])) {
            $ctaPath = (string) $feat['cta_path'];
            if ($ctaPath !== '' && nav_path_matches($ctaPath, $currentPath, navigation_infer_active_match($ctaPath))) {
                return true;
            }
        }
    }

    return false;
}

function navigation_current_path(): string
{
    return \App\Core\Request::normalizePathFromServer($_SERVER);
}
