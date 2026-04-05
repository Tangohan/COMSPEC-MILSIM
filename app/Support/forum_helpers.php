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
     */
    function forum_can_read(?int $userId, array $category): bool
    {
        if ($userId === null) {
            return false;
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
            $href = $sanitized;
            $class = 'text-orange-400 hover:text-orange-300 underline';
            if (!$svc->isInternalUrl($sanitized, $extraHosts)) {
                $leave = $svc->buildSignedLeaveUrl($sanitized);
                if ($leave !== null) {
                    $href = $leave;
                }
            }
            $safeHref = htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return '<a href="' . $safeHref . '" rel="noopener noreferrer" class="' . $class . '">' . $label . '</a>';
        }, $content);
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
