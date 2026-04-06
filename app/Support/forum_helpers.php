<?php

declare(strict_types=1);

if (!function_exists('forum_notification_unread_count')) {
    function forum_notification_unread_count(): int
    {
        if (!\App\Core\Session::get('user_id')) {
            return 0;
        }
        try {
            $r = \App\Core\Container::get(\App\Repositories\ForumNotificationRepository::class);

            return $r->unreadCount((int) \App\Core\Session::get('tenant_id'), (int) \App\Core\Session::get('user_id'));
        } catch (\Throwable) {
            return 0;
        }
    }
}

if (!function_exists('forum_user_can_moderate')) {
    /**
     * Droits de modération forum (agrégats historiques + permissions granulaires).
     */
    function forum_user_can_moderate(): bool
    {
        if (!function_exists('can')) {
            return false;
        }
        $gate = \App\Core\Gate::getInstance();
        if (can('forum.moderate') || can('forum.moderate_organization')) {
            return true;
        }
        if ($gate->allows('admin.organization') || $gate->allows('admin.access')) {
            return true;
        }
        foreach (\App\Authorization\TenantPermissionCatalog::forumModerateGranularSlugs() as $slug) {
            if (can($slug)) {
                return true;
            }
        }
        if (can('forum.categories.manage') || can('forum.manage_categories')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('forum_viewer_is_moderator')) {
    /**
     * Aligné sur ForumModerationConsoleMiddleware : accès outils / identité renforcée modération.
     */
    function forum_viewer_is_moderator(): bool
    {
        return forum_user_can_moderate();
    }
}

if (!function_exists('forum_can_read')) {
    /**
     * Vérifie si l'utilisateur peut lire la catégorie (tenant + min_role_id si défini).
     * scope = moderation : réservé aux membres avec pouvoirs de modération forum.
     */
    function forum_can_read(?int $userId, array $category): bool
    {
        if ($userId === null) {
            return false;
        }
        $scope = $category['scope'] ?? 'general';
        if ($scope === 'moderation') {
            return function_exists('forum_user_can_moderate') && forum_user_can_moderate();
        }
        $minRoleId = $category['min_role_id'] ?? null;
        if ($minRoleId === null) {
            return true;
        }
        if (!function_exists('can')) {
            return true;
        }
        return can('forum.view');
    }
}

if (!function_exists('forum_get_setting')) {
    function forum_get_setting(string $key, mixed $default = null): mixed
    {
        $forum = config('forum');
        if (!is_array($forum)) {
            return $default;
        }
        return $forum[$key] ?? $default;
    }
}

if (!function_exists('forum_config_for_tenant')) {
    /**
     * Fusionne config/forum.php et les clés site_settings forum_* du tenant.
     */
    function forum_config_for_tenant(?int $tenantId): array
    {
        $base = config('forum');
        if (!is_array($base)) {
            $base = [];
        }
        if ($tenantId === null || $tenantId <= 0) {
            return $base;
        }
        try {
            $repo = new \App\Repositories\SiteSettingsRepository();
            $site = $repo->getForumSettings($tenantId);
        } catch (\Throwable) {
            return $base;
        }
        $merged = array_merge($base, $site);
        if (array_key_exists('forum_enabled', $merged)) {
            $v = strtolower(trim((string) $merged['forum_enabled']));
            $merged['enabled'] = in_array($v, ['1', 'true', 'yes', 'on'], true);
        }
        $shortFromForum = [
            'name' => 'forum_name',
            'subtitle' => 'forum_subtitle',
            'tagline' => 'forum_tagline',
            'context' => 'forum_context',
        ];
        foreach ($shortFromForum as $short => $long) {
            if (isset($merged[$long]) && trim((string) $merged[$long]) !== '') {
                $merged[$short] = $merged[$long];
            }
        }
        if (isset($merged['forum_moderation_tutorial_html']) && trim((string) $merged['forum_moderation_tutorial_html']) !== '') {
            $merged['moderation_tutorial_html'] = $merged['forum_moderation_tutorial_html'];
        }

        return $merged;
    }
}

if (!function_exists('forum_is_enabled')) {
    function forum_is_enabled(): bool
    {
        $tid = \App\Core\Session::get('tenant_id');
        if ($tid) {
            $c = forum_config_for_tenant((int) $tid);
            if (array_key_exists('enabled', $c)) {
                return (bool) $c['enabled'];
            }
        }

        return (bool) forum_get_setting('enabled', true);
    }
}

if (!function_exists('forum_forum_resolve_href_for_http_url')) {
    /**
     * URL http(s) déjà validée : lien interne direct ou interstitiel /leave signé si externe.
     *
     * @param list<string> $extraInternalHosts
     */
    function forum_forum_resolve_href_for_http_url(string $sanitizedUrl, array $extraInternalHosts = []): string
    {
        $svc = new \App\Services\Forum\ExternalLeaveService();
        $href = $sanitizedUrl;
        if (!$svc->isInternalUrl($sanitizedUrl, $extraInternalHosts)) {
            $leave = $svc->buildSignedLeaveUrl($sanitizedUrl);
            if ($leave !== null) {
                $href = $leave;
            }
        }

        return $href;
    }
}

if (!function_exists('forum_linkify_plain_http_urls')) {
    /**
     * Transforme les URL http(s) saisies en texte brut en liens (hors &lt;pre&gt;, &lt;code&gt;, &lt;a&gt;).
     * Réutilise le même interstitiel /leave que le Markdown [libellé](url).
     */
    function forum_linkify_plain_http_urls(string $html): string
    {
        $placeholders = [];
        $n = 0;
        $stash = static function (array $m) use (&$placeholders, &$n): string {
            $chunk = $m[0] ?? '';
            $key = '@@FURLP' . ($n++) . '@@';
            $placeholders[$key] = $chunk;

            return $key;
        };

        $html = preg_replace_callback('#<pre\b[^>]*>.*?</pre>#is', $stash, $html) ?? $html;
        $html = preg_replace_callback('#<a\b[^>]*>.*?</a>#is', $stash, $html) ?? $html;
        $html = preg_replace_callback('#<code\b[^>]*>.*?</code>#is', $stash, $html) ?? $html;

        $svc = new \App\Services\Forum\ExternalLeaveService();
        $extra = forum_get_setting('internal_link_hosts', []);
        $extraHosts = is_array($extra) ? $extra : [];

        $html = preg_replace_callback(
            '#\bhttps?://[^\s<>"\'\[\]]+#iu',
            static function (array $m) use ($svc, $extraHosts): string {
                $matched = $m[0];
                $trail = '';
                if (preg_match('#([.,;:!?]+)$#u', $matched, $tm)) {
                    $trail = $tm[1];
                    $matched = substr($matched, 0, -strlen($trail));
                }
                $decoded = html_entity_decode($matched, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $sanitized = $svc->sanitizeHttpUrl($decoded);
                if ($sanitized === null) {
                    return $m[0];
                }
                $href = forum_forum_resolve_href_for_http_url($sanitized, $extraHosts);
                $safeHref = htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $safeLabel = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $class = 'text-orange-400 hover:text-orange-300 underline break-all';

                return '<a href="' . $safeHref . '" rel="noopener noreferrer" class="' . $class . '">' . $safeLabel . '</a>' . $trail;
            },
            $html
        );

        foreach ($placeholders as $key => $chunk) {
            $html = str_replace($key, $chunk, $html);
        }

        return $html;
    }
}

if (!function_exists('forum_markdown_to_html')) {
    /**
     * Convertit du Markdown simple en HTML (sécurisé).
     * Supporte : **gras**, *italique*, ~~barré~~, `code`, ```bloc```, [texte](url), > citation, - liste, 1. liste.
     */
    function forum_markdown_to_html(string $content): string
    {
        $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Code blocks ``` avant le reste
        $content = preg_replace_callback('/```(\w*)\s*([\s\S]*?)```/', function ($m) {
            return '<pre class="my-2 p-3 bg-black/30 border border-white/10 rounded text-sm overflow-x-auto"><code>' . $m[2] . '</code></pre>';
        }, $content);
        // Blockquote et listes (ligne par ligne), puis inlines sur tout le texte
        $out = [];
        $inBlockquote = false;
        $inUl = false;
        $inOl = false;
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (preg_match('/^&gt;\s?(.*)$/', $trimmed, $qm)) {
                if ($inUl) { $out[] = '</ul>'; $inUl = false; }
                if ($inOl) { $out[] = '</ol>'; $inOl = false; }
                if (!$inBlockquote) { $out[] = '<blockquote class="border-l-2 border-orange-500/40 pl-4 my-1.5 text-neutral-400">'; $inBlockquote = true; }
                $out[] = $qm[1] . '<br>';
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $um)) {
                if ($inBlockquote) { $out[] = '</blockquote>'; $inBlockquote = false; }
                if ($inOl) { $out[] = '</ol>'; $inOl = false; }
                if (!$inUl) { $out[] = '<ul class="list-disc list-inside space-y-0.5 my-2 text-neutral-300 pl-2">'; $inUl = true; }
                $out[] = '<li>' . $um[1] . '</li>';
                continue;
            }
            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $om)) {
                if ($inBlockquote) { $out[] = '</blockquote>'; $inBlockquote = false; }
                if ($inUl) { $out[] = '</ul>'; $inUl = false; }
                if (!$inOl) { $out[] = '<ol class="list-decimal list-inside space-y-0.5 my-2 text-neutral-300 pl-2">'; $inOl = true; }
                $out[] = '<li>' . $om[1] . '</li>';
                continue;
            }
            if ($inBlockquote) { $out[] = '</blockquote>'; $inBlockquote = false; }
            if ($inUl) { $out[] = '</ul>'; $inUl = false; }
            if ($inOl) { $out[] = '</ol>'; $inOl = false; }
            $out[] = $line . "\n";
        }
        if ($inBlockquote) $out[] = '</blockquote>';
        if ($inUl) $out[] = '</ul>';
        if ($inOl) $out[] = '</ol>';
        $content = implode('', $out);
        // Inline (après blocs pour que ** etc. dans blockquote/li soient rendus)
        $content = preg_replace('/`([^`\n]+)`/', '<code class="px-1 py-0.5 bg-white/10 rounded text-xs">$1</code>', $content);
        $content = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $content);
        $content = preg_replace('/_([^_]+)_/', '<em>$1</em>', $content);
        $content = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $content);
        $content = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', static function (array $m): string {
            $label = $m[1];
            $rawUrl = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $svc = new \App\Services\Forum\ExternalLeaveService();
            $sanitized = $svc->sanitizeHttpUrl($rawUrl);
            if ($sanitized === null) {
                return $label;
            }
            $extra = forum_get_setting('internal_link_hosts', []);
            $extraHosts = is_array($extra) ? $extra : [];
            $href = forum_forum_resolve_href_for_http_url($sanitized, $extraHosts);
            $safeHref = htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $class = 'text-orange-400 hover:text-orange-300 underline';

            return '<a href="' . $safeHref . '" rel="noopener noreferrer" class="' . $class . '">' . $label . '</a>';
        }, $content);
        $content = forum_linkify_plain_http_urls($content);

        return nl2br($content);
    }
}

if (!function_exists('forum_render_content')) {
    /**
     * Rendu sécurisé du contenu forum (Markdown simple → HTML).
     */
    function forum_render_content(string $content): string
    {
        return forum_markdown_to_html($content);
    }
}

if (!function_exists('forum_forum_role_display')) {
    /**
     * Libellé carte auteur forum à partir du rôle communauté (nom + slug).
     */
    function forum_forum_role_display(?string $name, ?string $slug): string
    {
        $slugNorm = $slug !== null && trim((string) $slug) !== '' ? strtolower(trim((string) $slug)) : '';
        $bySlug = [
            'member' => 'Opérateur',
            'forum_moderator' => 'Modérateur forum',
            'tenant_admin' => 'État-major',
            'community_owner' => 'Fondateur',
            'officer' => 'Cadre',
            'administrator' => 'Administrateur',
            'site_admin' => 'Admin plateforme',
            'recruiter' => 'Recruteur',
            'guest' => 'Invité',
            'hr' => 'RH (S1)',
            'invite' => 'Visiteur',
            'instructor' => 'Instructeur',
            'medic' => 'OPSAN',
            'logistics' => 'Logistique',
            'rto' => 'R2',
            'probation' => 'Période d’essai',
        ];
        if ($slugNorm !== '' && isset($bySlug[$slugNorm])) {
            return $bySlug[$slugNorm];
        }
        $n = trim((string) $name);
        if ($n === '') {
            return '';
        }
        $lower = strtolower($n);
        if ($lower === 'administrator') {
            return 'Administrateur';
        }
        if ($lower === 'member') {
            return 'Membre';
        }

        return $n;
    }
}

if (!function_exists('forum_time_ago')) {
    function forum_time_ago(string $datetime): string
    {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'à l\'instant';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . ' min';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . ' h';
        }
        if ($diff < 604800) {
            $d = (int) floor($diff / 86400);
            return $d . ' j';
        }
        return date('d/m/Y H:i', $ts);
    }
}

if (!function_exists('forum_build_category_url')) {
    /**
     * Construit l'URL de la catégorie avec query string (page, sort, filter, q).
     */
    function forum_build_category_url(string $slug, array $overrides = []): string
    {
        $base = url('forum/category/' . $slug);
        $params = array_filter([
            'page' => $overrides['page'] ?? null,
            'sort' => $overrides['sort'] ?? null,
            'filter' => $overrides['filter'] ?? null,
            'q' => $overrides['q'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        if (empty($params)) {
            return $base;
        }
        return $base . '?' . http_build_query($params);
    }
}

if (!function_exists('forum_user_can_moderate_for_user_id')) {
    /**
     * Indique si un autre membre (ex. auteur du sujet) dispose des mêmes pouvoirs « modération forum »
     * que forum_user_can_moderate() pour la session courante (badges staff).
     */
    function forum_user_can_moderate_for_user_id(int $userId, int $tenantId): bool
    {
        static $cache = [];
        $k = $userId . ':' . $tenantId;
        if (array_key_exists($k, $cache)) {
            return $cache[$k];
        }
        try {
            $users = \App\Core\Container::get(\App\Repositories\UserRepository::class);
            $rbac = \App\Core\Container::get(\App\Services\Rbac\RbacService::class);
            $user = $users->findById($userId, $tenantId);
            if (!$user || (int) ($user['tenant_id'] ?? 0) !== $tenantId) {
                return $cache[$k] = false;
            }
            $legacy = isset($user['role_id']) && $user['role_id'] !== null && $user['role_id'] !== ''
                ? (int) $user['role_id'] : null;
            $ids = $users->tenantRoleIdsForRbac($userId, $legacy);
            $tenantPerms = $rbac->loadPermissionsForRoles($ids);
            $sitePerms = $rbac->loadSitePermissionsForEmail((string) ($user['email'] ?? ''));
            $granted = array_values(array_unique([...$tenantPerms, ...$sitePerms]));
            $pi = \App\Authorization\PermissionImplication::class;
            if ($pi::isGranted($granted, 'forum.moderate') || $pi::isGranted($granted, 'forum.moderate_organization')) {
                return $cache[$k] = true;
            }
            if ($pi::isGranted($granted, 'admin.organization') || $pi::isGranted($granted, 'admin.access')) {
                return $cache[$k] = true;
            }
            foreach (\App\Authorization\TenantPermissionCatalog::forumModerateGranularSlugs() as $slug) {
                if ($pi::isGranted($granted, $slug)) {
                    return $cache[$k] = true;
                }
            }
            if ($pi::isGranted($granted, 'forum.categories.manage') || $pi::isGranted($granted, 'forum.manage_categories')) {
                return $cache[$k] = true;
            }

            return $cache[$k] = false;
        } catch (\Throwable) {
            return $cache[$k] = false;
        }
    }
}

if (!function_exists('forum_filter_category_tree_for_user')) {
    /**
     * Filtre l’arbre catégories (racines + enfants) selon forum_can_read().
     *
     * @param list<array<string,mixed>> $tree
     * @return list<array<string,mixed>>
     */
    function forum_filter_category_tree_for_user(array $tree, int $userId): array
    {
        $out = [];
        foreach ($tree as $root) {
            if (!function_exists('forum_can_read') || !forum_can_read($userId, $root)) {
                continue;
            }
            $kids = [];
            foreach ($root['children'] ?? [] as $ch) {
                if (forum_can_read($userId, $ch)) {
                    $kids[] = $ch;
                }
            }
            $root['children'] = $kids;
            $out[] = $root;
        }

        return $out;
    }
}

if (!function_exists('forum_topic_trend_level')) {
    /**
     * @return 'hot'|'active'|null
     */
    function forum_topic_trend_level(array $topicRow): ?string
    {
        $posts7d = (int) ($topicRow['posts_7d'] ?? 0);
        $pc = (int) ($topicRow['post_count'] ?? 0);
        $vc = (int) ($topicRow['view_count'] ?? 0);
        if ($posts7d >= 8 || ($posts7d >= 4 && $vc >= 120)) {
            return 'hot';
        }
        if ($posts7d >= 3 || $vc >= 200 || $pc >= 25) {
            return 'active';
        }

        return null;
    }
}
