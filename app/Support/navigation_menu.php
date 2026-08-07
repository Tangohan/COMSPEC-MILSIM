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
 * Compteur de conversations internes non lues (messagerie portail), pour badge menu.
 */
function navigation_unread_internal_messages_count(): int
{
    if (!(\App\Core\Session::get('user_id'))) {
        return 0;
    }
    $tenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
    $userId = (int) (\App\Core\Session::get('user_id') ?? 0);
    if ($tenantId < 1 || $userId < 1) {
        return 0;
    }
    try {
        return \App\Core\Container::get(\App\Repositories\TenantMessageRepository::class)->unreadCountForUser($tenantId, $userId);
    } catch (\Throwable) {
        return 0;
    }
}

/**
 * @param array{label: string, href: string, path: string, active_match: string, description?: string|null, badge?: string} $resolved
 * @return array{label: string, href: string, path: string, active_match: string, description?: string|null, badge?: string}
 */
function navigation_apply_internal_messages_badge(array $resolved): array
{
    if (($resolved['path'] ?? '') !== '/messages') {
        return $resolved;
    }
    $n = navigation_unread_internal_messages_count();
    if ($n > 0) {
        $resolved['badge'] = (string) min(99, $n);
    }

    return $resolved;
}

/**
 * Total non lu agrégé (forum + courrier si habilitation + messagerie interne), pour pastille « Mon activité ».
 */
function navigation_aggregate_message_activity_unread_total(): int
{
    if (!(\App\Core\Session::get('user_id'))) {
        return 0;
    }
    $tenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
    $userId = (int) (\App\Core\Session::get('user_id') ?? 0);
    if ($tenantId < 1 || $userId < 1) {
        return 0;
    }
    try {
        $gate = \App\Core\Gate::getInstance();
        $n = \App\Core\Container::get(\App\Services\Notifications\PersonalMessageUnreadCounter::class)
            ->countsForUser($tenantId, $userId, $gate)['total'];

        return max(0, $n);
    } catch (\Throwable) {
        return 0;
    }
}

/**
 * @param array{label: string, href: string, path: string, active_match: string, description?: string|null, badge?: string} $resolved
 * @return array{label: string, href: string, path: string, active_match: string, description?: string|null, badge?: string}
 */
function navigation_apply_activity_hub_badge(array $resolved): array
{
    if (($resolved['path'] ?? '') !== '/activite') {
        return $resolved;
    }
    $n = navigation_aggregate_message_activity_unread_total();
    if ($n > 0) {
        $resolved['badge'] = (string) min(99, $n);
    }

    return $resolved;
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

    if ($loggedIn && !navigation_tenant_type_allows_item($item)) {
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
 * Type de communauté courant (session), normalisé.
 */
function navigation_current_tenant_type(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = \App\Services\Community\TenantTypeConfig::TYPE_FULL;
    $tenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
    if ($tenantId < 1) {
        return $cached;
    }
    try {
        $tenant = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->findById($tenantId);
        if ($tenant) {
            $cached = \App\Services\Community\TenantTypeConfig::normalizeType(
                (string) ($tenant['tenant_type'] ?? 'full')
            );
        }
    } catch (\Throwable) {
    }

    return $cached;
}

/**
 * @param array{module?: string, path?: string} $item
 */
function navigation_tenant_type_allows_item(array $item): bool
{
    $type = navigation_current_tenant_type();
    if ($type === \App\Services\Community\TenantTypeConfig::TYPE_FULL) {
        return true;
    }
    $module = trim((string) ($item['module'] ?? ''));
    if ($module === '' && !empty($item['path'])) {
        $module = (string) (\App\Services\Community\TenantTypeConfig::moduleForUri((string) $item['path']) ?? '');
    }
    if ($module === '') {
        return true;
    }

    return \App\Services\Community\TenantTypeConfig::moduleAllowed($type, $module);
}

/**
 * @param array{module?: string, path?: string} $link
 */
function navigation_tenant_type_allows_link(array $link): bool
{
    return navigation_tenant_type_allows_item($link);
}

/**
 * Résout un lien avec href + path canonique pour l’état actif.
 *
 * @param array{path: string, active_match?: string, label: string, description?: string} $link
 * @return array{label: string, href: string, path: string, active_match: string, description?: string|null, badge?: string}|null
 */
function navigation_resolve_link(array $link): ?array
{
    if (!navigation_item_allowed($link)) {
        return null;
    }
    if (!navigation_tenant_type_allows_link($link)) {
        return null;
    }
    $pathFragment = (string) ($link['path'] ?? '');
    $routePath = navigation_route_path($pathFragment);
    $match = $link['active_match'] ?? navigation_infer_active_match($routePath);

    $out = [
        'label' => (string) ($link['label'] ?? ''),
        'href' => url($pathFragment),
        'path' => $routePath,
        'active_match' => $match,
        'description' => isset($link['description']) ? (string) $link['description'] : null,
    ];

    return navigation_apply_activity_hub_badge(navigation_apply_internal_messages_badge($out));
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

function navigation_normalize_submenu_style(?string $style): string
{
    $allowed = ['standard', 'cards', 'minimal'];
    $v = strtolower(trim((string) ($style ?? '')));

    return in_array($v, $allowed, true) ? $v : 'standard';
}

/**
 * @return array<string, mixed>
 */
function navigation_tenant_portal_nav_overrides(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $tenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
    if ($tenantId < 1) {
        $cache = [];

        return $cache;
    }
    try {
        $repo = \App\Core\Container::get(\App\Repositories\TenantRepository::class);
        $settings = $repo->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $cache = is_array($community['portal_nav'] ?? null) ? $community['portal_nav'] : [];
        $tenant = $repo->findById($tenantId);
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        if ($slug !== '') {
            foreach (['operations', 'resources'] as $slot) {
                $imgRel = 'assets/img/communities/' . $slug . '-nav-' . $slot . '.jpg';
                if (navigation_image_file_exists($imgRel)) {
                    if (!isset($cache[$slot]) || !is_array($cache[$slot])) {
                        $cache[$slot] = [];
                    }
                    $cache[$slot]['image'] = $imgRel;
                }
            }
        }
    } catch (\Throwable) {
        $cache = [];
    }

    return $cache;
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

    $ctaPath = (string) $featured['cta_path'];
    if (!navigation_tenant_type_allows_link(['path' => $ctaPath, 'module' => $featured['cta_module'] ?? null])) {
        return $out;
    }

    $perm = $featured['cta_permission'] ?? null;
    if ($perm !== null && $perm !== '' && !\App\Core\Gate::getInstance()->allows((string) $perm)) {
        return $out;
    }

    $out['cta_href'] = url($ctaPath);
    $out['cta_path'] = navigation_route_path($ctaPath);

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

    return is_file(public_file_path($relativePath));
}

/**
 * Complète le méga-menu Opérations avec les rubriques et sous-rubriques forum visibles pour l’utilisateur.
 *
 * @param array<string, mixed> $megaItem
 */
function navigation_append_forum_rubric_links(array &$megaItem): void
{
    if (($megaItem['variant'] ?? '') !== 'operations') {
        return;
    }
    if (!navigation_tenant_type_allows_item(['module' => 'forum'])) {
        return;
    }
    if (!\App\Core\Gate::getInstance()->allows('forum.view')) {
        return;
    }
    $tenantId = (int) \App\Core\Session::get('tenant_id');
    $userId = (int) \App\Core\Session::get('user_id');
    if ($tenantId < 1 || $userId < 1) {
        return;
    }
    if (!function_exists('forum_filter_category_tree_for_user')) {
        return;
    }

    try {
        $repo = \App\Core\Container::get(\App\Repositories\ForumCategoryRepository::class);
        $tree = $repo->listForTenantWithChildren($tenantId);
        $tree = forum_filter_category_tree_for_user($tree, $userId);
    } catch (\Throwable) {
        return;
    }

    $resolved = [];
    $max = 28;
    $n = 0;
    foreach ($tree as $root) {
        if ($n >= $max) {
            break;
        }
        $slug = trim((string) ($root['slug'] ?? ''));
        if ($slug !== '') {
            $link = navigation_resolve_link([
                'label' => (string) ($root['name'] ?? 'Rubrique'),
                'path' => 'forum/category/' . $slug,
                'permission' => 'forum.view',
            ]);
            if ($link !== null) {
                $resolved[] = $link;
                $n++;
            }
        }
        $parentName = trim((string) ($root['name'] ?? ''));
        foreach ($root['children'] ?? [] as $ch) {
            if ($n >= $max) {
                break;
            }
            $cs = trim((string) ($ch['slug'] ?? ''));
            if ($cs === '') {
                continue;
            }
            $opts = [
                'label' => (string) ($ch['name'] ?? 'Sous-rubrique'),
                'path' => 'forum/category/' . $cs,
                'permission' => 'forum.view',
            ];
            if ($parentName !== '') {
                $opts['description'] = 'Rubrique « ' . $parentName . ' »';
            }
            $link = navigation_resolve_link($opts);
            if ($link !== null) {
                $resolved[] = $link;
                $n++;
            }
        }
    }

    if ($resolved === []) {
        return;
    }

    $megaItem['sections'][] = [
        'title' => 'Rubriques du forum',
        'slot' => 'secondary',
        'links' => $resolved,
    ];
}

/**
 * @return array{brand: array{name: string, subtitle: string, href: string}, search: array{enabled: bool, shortcut: bool, placeholder: string, action: string, method: string, param: string}, menu: list<array<string, mixed>>}
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
    $searchOn = !empty($searchRaw['enabled']) && $loggedIn;
    $builtSearch = [
        'enabled' => $searchOn,
        'shortcut' => $searchOn && !empty($searchRaw['shortcut']),
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
                    'description' => $item['description'] ?? null,
                ], static fn ($v) => $v !== null)
            ));
            if ($link === null) {
                continue;
            }
            $linkItem = [
                'type' => 'link',
                'label' => (string) ($item['label'] ?? ''),
                'href' => $link['href'],
                'path' => $link['path'],
                'active_match' => $link['active_match'],
                'id' => 'nav-top-' . $idx,
            ];
            if (!empty($link['description'])) {
                $linkItem['description'] = (string) $link['description'];
            }
            $badge = trim((string) ($item['badge'] ?? ''));
            if ($badge !== '') {
                $linkItem['badge'] = $badge;
            }
            $menuOut[] = $linkItem;
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

            $megaItem = [
                'type' => 'mega',
                'label' => (string) ($item['label'] ?? ''),
                'icon' => (string) ($item['icon'] ?? ''),
                'accent' => navigation_normalize_accent(isset($item['accent']) ? (string) $item['accent'] : null),
                'variant' => navigation_normalize_variant(isset($item['variant']) ? (string) $item['variant'] : null),
                'submenu_style' => navigation_normalize_submenu_style(isset($item['submenu_style']) ? (string) $item['submenu_style'] : null),
                'sections' => $sectionsOut,
                'live' => $liveBlocks,
                'featured' => $featured,
                'id' => 'nav-mega-' . $idx,
            ];
            $badge = trim((string) ($item['badge'] ?? ''));
            if ($badge !== '') {
                $megaItem['badge'] = $badge;
            }
            $menuOut[] = $megaItem;
        }
    }

    foreach ($menuOut as &$builtItem) {
        if (($builtItem['type'] ?? '') === 'mega') {
            $portalNav = navigation_tenant_portal_nav_overrides();
            $slot = ($builtItem['variant'] ?? '') === 'operations'
                ? 'operations'
                : (($builtItem['variant'] ?? '') === 'resources' ? 'resources' : '');
            if ($slot !== '' && is_array($portalNav[$slot] ?? null)) {
                $custom = $portalNav[$slot];
                if (!empty($custom['accent'])) {
                    $builtItem['accent'] = navigation_normalize_accent((string) $custom['accent']);
                }
                $builtItem['submenu_style'] = navigation_normalize_submenu_style((string) ($custom['submenu_style'] ?? 'standard'));
                if (is_array($builtItem['featured'] ?? null)) {
                    $img = trim((string) ($custom['image'] ?? ''));
                    if ($img !== '') {
                        $builtItem['featured']['image'] = ltrim($img, '/');
                    }
                    if (array_key_exists('image_enabled', $custom)) {
                        $builtItem['featured']['image_enabled'] = !empty($custom['image_enabled']);
                    }
                }
            }
        }
        if (($builtItem['type'] ?? '') === 'mega' && ($builtItem['variant'] ?? '') === 'operations') {
            navigation_append_forum_rubric_links($builtItem);
        }
    }
    unset($builtItem);

    return [
        'brand' => $builtBrand,
        'search' => $builtSearch,
        'menu' => $menuOut,
    ];
}

/**
 * Liens du menu portail effectivement autorisés pour l’utilisateur courant (Gate),
 * pour affichage « périmètre des accès » (tiroir accueil, synthèses, etc.).
 *
 * @return list<array{label: string, href: string, routePath: string, group: string}>
 */
function navigation_scope_drawer_entries(): array
{
    $nav = build_navigation_menu();
    $seen = [];
    $out = [];

    foreach ($nav['menu'] ?? [] as $item) {
        $type = (string) ($item['type'] ?? 'link');
        if ($type === 'link') {
            $rp = (string) ($item['path'] ?? '');
            if ($rp === '') {
                $rp = '/';
            }
            if (isset($seen[$rp])) {
                continue;
            }
            $seen[$rp] = true;
            $out[] = [
                'label' => (string) ($item['label'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'routePath' => $rp,
                'group' => 'Accès directs',
            ];

            continue;
        }

        if ($type !== 'mega') {
            continue;
        }

        $megaLabel = (string) ($item['label'] ?? '');
        foreach ($item['sections'] ?? [] as $section) {
            if (!is_array($section)) {
                continue;
            }
            $secTitle = (string) ($section['title'] ?? '');
            foreach ($section['links'] ?? [] as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $rp = (string) ($link['path'] ?? '');
                if ($rp === '') {
                    continue;
                }
                $dedupe = preg_replace('/#.*$/', '', $rp) ?: $rp;
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;
                $group = trim($megaLabel . ($secTitle !== '' ? ' · ' . $secTitle : ''));

                $out[] = [
                    'label' => (string) ($link['label'] ?? ''),
                    'href' => (string) ($link['href'] ?? ''),
                    'routePath' => $rp,
                    'group' => $group !== '' ? $group : $megaLabel,
                ];
            }
        }
    }

    return $out;
}

/**
 * @param list<array{label: string, href: string, routePath: string, group: string}> $entries
 * @return array<string, list<array{label: string, href: string, routePath: string, group: string}>>
 */
function navigation_scope_group_entries(array $entries): array
{
    $groups = [];
    foreach ($entries as $e) {
        $g = (string) ($e['group'] ?? 'Autres');
        if (!isset($groups[$g])) {
            $groups[$g] = [];
        }
        $groups[$g][] = $e;
    }

    return $groups;
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
