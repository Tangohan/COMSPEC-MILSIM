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
        $gate = \App\Core\Gate::getInstance();
        if ($gate->allows('admin.system')) {
            return true;
        }
        if (!function_exists('can')) {
            return false;
        }
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

if (!function_exists('forum_report_resolve_target_user_id')) {
    /**
     * Membre visé par un signalement hors fil (fiche, photo, dossier opérateur) quand l’identifiant
     * n’est pas stocké en colonne dédiée — dérivé du texte du signalement.
     */
    function forum_report_resolve_target_user_id(array $report): ?int
    {
        if (!empty($report['reported_user_id'])) {
            $u = (int) $report['reported_user_id'];

            return $u > 0 ? $u : null;
        }
        $reporter = (int) ($report['reporter_id'] ?? 0);
        if (!empty($report['post_author_id'])) {
            $u = (int) $report['post_author_id'];
            if ($u > 0 && $u !== $reporter) {
                return $u;
            }
        }
        $reason = (string) ($report['reason'] ?? '');
        $kind = (string) ($report['content_kind'] ?? '');
        if ($reason === '') {
            return null;
        }
        if (preg_match('/compte\s+n°\s*(\d+)/iu', $reason, $m)) {
            return (int) $m[1];
        }
        if (in_array($kind, ['member_profile', 'profile_picture', 'operator_visual'], true)
            && preg_match('/\(\s*n°\s*(\d+)\s*\)/u', $reason, $m)) {
            return (int) $m[1];
        }

        return null;
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

if (!function_exists('forum_truthy')) {
    /**
     * Interprète une valeur issue des réglages site (0/1, on/off, etc.).
     */
    function forum_truthy(mixed $raw, bool $default = false): bool
    {
        if ($raw === null || $raw === '') {
            return $default;
        }
        $v = strtolower(trim((string) $raw));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('forum_get_setting')) {
    /**
     * Valeur de config forum : d’abord fusion tenant (site_settings forum_*), puis config/forum.php.
     */
    function forum_get_setting(string $key, mixed $default = null): mixed
    {
        $tid = \App\Core\Session::get('tenant_id');
        if ($tid) {
            $merged = forum_config_for_tenant((int) $tid);
            if (array_key_exists($key, $merged) && $merged[$key] !== null && $merged[$key] !== '') {
                return $merged[$key];
            }
        }
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
        $merged['community_section_enabled'] = forum_truthy($merged['forum_community_section_enabled'] ?? null, true);
        $merged['community_section_notice'] = trim((string) ($merged['forum_community_section_notice'] ?? ''));
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

if (!function_exists('brief_is_open_for_members_globally')) {
    /**
     * Interrupteur plateforme : accès au brief pour les membres (toutes communautés).
     */
    function brief_is_open_for_members_globally(): bool
    {
        try {
            $repo = new \App\Repositories\PlatformSettingsRepository();
            if (!$repo->tableExists()) {
                return true;
            }

            return $repo->getBool('brief_member_access', true);
        } catch (\Throwable) {
            return true;
        }
    }
}

if (!function_exists('brief_platform_closed_message_text')) {
    function brief_platform_closed_message_text(): string
    {
        try {
            $repo = new \App\Repositories\PlatformSettingsRepository();
            if (!$repo->tableExists()) {
                return '';
            }

            return trim($repo->get('brief_member_closed_message', ''));
        } catch (\Throwable) {
            return '';
        }
    }
}

if (!function_exists('forum_community_section_open_for_current_viewer')) {
    /**
     * Section « organisation » du brief : visible / utilisable pour le visiteur courant.
     * Les modérateurs et administrateurs du forum passent toujours.
     */
    function forum_community_section_open_for_current_viewer(int $tenantId): bool
    {
        $gate = \App\Core\Gate::getInstance();
        if ($gate->allows('admin.system') || $gate->allows('admin.access') || $gate->allows('admin.organization')) {
            return true;
        }
        if (function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator()) {
            return true;
        }
        $cfg = forum_config_for_tenant($tenantId);

        return !empty($cfg['community_section_enabled']);
    }
}

if (!function_exists('forum_organization_scope_accessible_for_current_viewer')) {
    function forum_organization_scope_accessible_for_current_viewer(int $tenantId, string $scope): bool
    {
        if ($scope !== 'organization') {
            return true;
        }

        return forum_community_section_open_for_current_viewer($tenantId);
    }
}

if (!function_exists('forum_is_enabled')) {
    function forum_is_enabled(): bool
    {
        return brief_is_open_for_members_globally();
    }
}

if (!function_exists('forum_forum_resolve_href_for_http_url')) {
    /**
     * URL http(s) déjà validée : lien interne direct ou interstitiel /leave signé si externe
     * (selon forum_url_gate_enabled pour le tenant en session).
     *
     * @param list<string> $extraInternalHosts
     */
    function forum_forum_resolve_href_for_http_url(string $sanitizedUrl, array $extraInternalHosts = []): string
    {
        $svc = new \App\Services\Forum\ExternalLeaveService();
        $href = $sanitizedUrl;
        if (!$svc->isInternalUrl($sanitizedUrl, $extraInternalHosts)) {
            $tid = \App\Core\Session::get('tenant_id');
            $gate = true;
            if ($tid) {
                $cfg = forum_config_for_tenant((int) $tid);
                /* Absence de clé = comportement historique (page d’avertissement) */
                $gate = forum_truthy($cfg['forum_url_gate_enabled'] ?? null, true);
            }
            if ($gate) {
                $leave = $svc->buildSignedLeaveUrl($sanitizedUrl);
                if ($leave !== null) {
                    $href = $leave;
                }
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
     * Libellé carte auteur forum : intitulés courts alignés sur le sélecteur « rôle affiché » (slug / couche rôle).
     *
     * @param string|null $roleLayer Valeur roles.role_layer si disponible (ex. site, intra, community).
     */
    function forum_forum_role_display(?string $name, ?string $slug, ?string $roleLayer = null): string
    {
        if (function_exists('forum_visible_role_choice_label')) {
            return forum_visible_role_choice_label([
                'slug' => (string) ($slug ?? ''),
                'name' => (string) ($name ?? ''),
                'role_layer' => (string) ($roleLayer ?? ''),
            ]);
        }
        $n = trim((string) $name);
        if ($n !== '') {
            return $n;
        }
        $slugNorm = $slug !== null && trim((string) $slug) !== '' ? strtolower(trim((string) $slug)) : '';
        if ($slugNorm === '') {
            return '';
        }
        $fallback = [
            'guest' => 'Invité',
            'invite' => 'Visiteur',
            'member' => 'Membre',
            'administrator' => 'Administrateur',
        ];

        return $fallback[$slugNorm] ?? 'Membre';
    }
}

if (!function_exists('forum_visible_role_choice_label')) {
    /**
     * Libellé court « rôle du site » pour le sélecteur carte auteur (évite les intitulés métiers longs en base).
     *
     * @param array<string, mixed> $r Ligne roles (slug, name, role_layer).
     */
    function forum_visible_role_choice_label(array $r): string
    {
        $slug = strtolower(trim((string) ($r['slug'] ?? '')));
        $layer = (string) ($r['role_layer'] ?? '');

        $siteLabels = [
            'site_super_admin' => 'Gestionnaire de plateforme',
            'platform_admin' => 'Gestionnaire de plateforme',
            'site_support' => 'Équipe assistance',
            'site_moderator' => 'Modérateur plateforme',
            'site_senior_moderator' => 'Modérateur senior plateforme',
        ];
        if ($layer === 'site') {
            if ($slug !== '' && isset($siteLabels[$slug])) {
                return $siteLabels[$slug];
            }
            $n = trim((string) ($r['name'] ?? ''));

            return $n !== '' ? $n : 'Rôle plateforme';
        }

        $tenantLabels = [
            'forum_moderator' => 'Modérateur',
            'community_owner' => 'Gestionnaire d’organisation',
            'tenant_admin' => 'Administrateur organisation',
            'member' => 'Membre',
            'officer' => 'Cadre',
            'hr' => 'Ressources humaines',
            'recruiter' => 'Recruteur',
            'invite' => 'Visiteur',
            'instructor' => 'Instructeur',
            'medic' => 'OPSAN',
            'logistics' => 'Logistique',
            'rto' => 'R2 (transmissions)',
            'probation' => 'Période d’essai',
            'administrator' => 'Administrateur',
        ];
        if ($slug !== '' && isset($tenantLabels[$slug])) {
            return $tenantLabels[$slug];
        }
        $n = trim((string) ($r['name'] ?? ''));

        return $n !== '' ? $n : 'Rôle';
    }
}

if (!function_exists('forum_build_visible_role_choices')) {
    /**
     * Rôles autorisés pour la préférence « carte auteur » : rôles communauté du membre + rôles plateforme (site) liés à son e-mail.
     *
     * @return list<array{id: int, name: string}>
     */
    function forum_build_visible_role_choices(
        int $userId,
        int $tenantId,
        string $userEmail,
        \App\Repositories\UserRepository $userRepository,
        \App\Repositories\RoleRepository $roleRepository,
        \App\Repositories\SiteRoleAssignmentRepository $siteRoleAssignmentRepository
    ): array {
        $seen = [];
        $out = [];
        foreach ($userRepository->listOrganizationRoleIdsForUser($userId) as $rid) {
            $rid = (int) $rid;
            if ($rid < 1 || isset($seen[$rid])) {
                continue;
            }
            $row = $roleRepository->findById($rid, $tenantId);
            if ($row === null) {
                continue;
            }
            $seen[$rid] = true;
            $out[] = [
                'id' => $rid,
                'name' => forum_visible_role_choice_label($row),
            ];
        }
        $email = strtolower(trim($userEmail));
        if ($email !== '') {
            foreach ($siteRoleAssignmentRepository->activeRoleIdsForEmail($email) as $srid) {
                $srid = (int) $srid;
                if ($srid < 1 || isset($seen[$srid])) {
                    continue;
                }
                $row = $roleRepository->findById($srid, null);
                if ($row === null || (string) ($row['role_layer'] ?? '') !== 'site') {
                    continue;
                }
                $seen[$srid] = true;
                $out[] = [
                    'id' => $srid,
                    'name' => forum_visible_role_choice_label($row),
                ];
            }
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $out;
    }
}

if (!function_exists('forum_user_may_set_visible_role_id')) {
    function forum_user_may_set_visible_role_id(
        int $userId,
        string $userEmail,
        int $roleId,
        \App\Repositories\UserRepository $userRepository,
        \App\Repositories\SiteRoleAssignmentRepository $siteRoleAssignmentRepository
    ): bool {
        if ($roleId < 1) {
            return false;
        }
        if ($userRepository->userHasTenantRole($userId, $roleId)) {
            return true;
        }
        $email = strtolower(trim($userEmail));
        if ($email === '') {
            return false;
        }
        foreach ($siteRoleAssignmentRepository->activeRoleIdsForEmail($email) as $rid) {
            if ((int) $rid === $roleId) {
                return true;
            }
        }

        return false;
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

if (!function_exists('forum_allowed_upload_extensions')) {
    /**
     * @return list<string>
     */
    function forum_allowed_upload_extensions(int $tenantId): array
    {
        $fc = forum_config_for_tenant($tenantId);
        $raw = strtolower(trim((string) ($fc['forum_attachments_allowed_ext'] ?? '')));
        $safe = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        if ($raw === '') {
            return $safe;
        }
        $out = [];
        foreach (preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $p) {
            $p = preg_replace('/[^a-z0-9]/', '', $p);
            if (in_array($p, $safe, true)) {
                $out[] = $p;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : $safe;
    }
}

if (!function_exists('forum_upload_max_bytes')) {
    function forum_upload_max_bytes(int $tenantId): int
    {
        $fc = forum_config_for_tenant($tenantId);
        $v = (int) ($fc['forum_attachments_max_size'] ?? 0);
        if ($v <= 0) {
            return 5 * 1024 * 1024;
        }

        return max(1024, min(52_428_800, $v));
    }
}

if (!function_exists('forum_upload_allowed_mimes')) {
    /**
     * @return list<string>
     */
    function forum_upload_allowed_mimes(int $tenantId): array
    {
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
        ];
        $mimes = [];
        foreach (forum_allowed_upload_extensions($tenantId) as $e) {
            if (isset($map[$e])) {
                $mimes[] = $map[$e];
            }
        }

        return $mimes !== [] ? array_values(array_unique($mimes)) : array_values($map);
    }
}

if (!function_exists('forum_pagination_limit')) {
    function forum_pagination_limit(array $cfg, string $key, int $fallback, int $min = 1, int $max = 200): int
    {
        $v = isset($cfg[$key]) ? (int) $cfg[$key] : 0;
        if ($v < 1) {
            $v = $fallback;
        }

        return max($min, min($max, $v));
    }
}

if (!function_exists('forum_cooldown_remaining_seconds')) {
    function forum_cooldown_remaining_seconds(int $tenantId, int $userId): int
    {
        $cfg = forum_config_for_tenant($tenantId);
        $cool = (int) ($cfg['forum_cooldown_seconds'] ?? 0);
        if ($cool <= 0) {
            return 0;
        }
        try {
            $pr = \App\Core\Container::get(\App\Repositories\ForumPostRepository::class);
            $last = $pr->latestPostCreatedAtForUser($tenantId, $userId);
            if ($last === null || $last === '') {
                return 0;
            }
            $ts = strtotime($last);
            if ($ts === false) {
                return 0;
            }
            $elapsed = time() - $ts;

            return max(0, $cool - $elapsed);
        } catch (\Throwable) {
            return 0;
        }
    }
}

if (!function_exists('forum_validate_post_text_limits')) {
    /**
     * @return string|null message d’erreur affichable, ou null si OK
     */
    function forum_validate_post_text_limits(int $tenantId, string $bodyTrimmed, bool $hasAttachments, int $maxLen): ?string
    {
        if (strlen($bodyTrimmed) > $maxLen) {
            return 'Le message dépasse la longueur maximale autorisée.';
        }
        $cfg = forum_config_for_tenant($tenantId);
        if (!forum_truthy($cfg['forum_antispam_enabled'] ?? null, false)) {
            return null;
        }
        $min = (int) ($cfg['forum_antispam_min_length'] ?? 20);
        $min = max(1, min(5000, $min));
        if ($hasAttachments && $bodyTrimmed === '') {
            return null;
        }
        if ($bodyTrimmed !== '' && strlen($bodyTrimmed) < $min) {
            return 'Le message est trop court pour être accepté (filtre anti-spam de l’unité).';
        }

        return null;
    }
}

if (!function_exists('forum_after_post_moderation')) {
    /**
     * Heuristiques, file d’attente masquée, notifications modérateurs.
     */
    function forum_after_post_moderation(int $tenantId, int $userId, int $postId, string $body): void
    {
        try {
            $engine = \App\Core\Container::get(\App\Services\Forum\ForumModerationEngine::class);
            $result = $engine->analyze($tenantId, $userId, $postId, $body);
            $cfg = forum_config_for_tenant($tenantId);
            $action = (string) ($result['action'] ?? 'allow');
            if ($action === 'flag' && forum_truthy($cfg['forum_sandbox_enabled'] ?? null, false)) {
                $pr = \App\Core\Container::get(\App\Repositories\ForumPostRepository::class);
                $pr->setHidden($postId, $tenantId, true);
            }
            if ($action === 'flag' && forum_truthy($cfg['forum_notify_moderators'] ?? null, false)) {
                forum_send_moderation_alert_notifications($tenantId, $postId, $result);
            }
        } catch (\Throwable) {
            // ne pas bloquer la publication
        }
    }
}

if (!function_exists('forum_api_disabled_response')) {
    function forum_api_disabled_response(int $tenantId): ?\App\Core\Response
    {
        if (function_exists('brief_is_open_for_members_globally') && brief_is_open_for_members_globally()) {
            return null;
        }
        if (function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator()) {
            return null;
        }

        return \App\Core\Response::json(['success' => false, 'error' => 'Le brief est temporairement indisponible pour les membres.'], 403);
    }
}

if (!function_exists('forum_disabled_for_member_response')) {
    /**
     * Brief fermé au niveau plateforme pour les membres (les modérateurs passent).
     */
    function forum_disabled_for_member_response(int $tenantId): ?\App\Core\Response
    {
        $cfg = forum_config_for_tenant($tenantId);
        if (function_exists('brief_is_open_for_members_globally') && brief_is_open_for_members_globally()) {
            return null;
        }
        if (function_exists('forum_viewer_is_moderator') && forum_viewer_is_moderator()) {
            return null;
        }

        return \App\Core\Response::view('layout.forum', [
            'content' => 'forum.disabled',
            'title' => 'Brief indisponible',
            'forumConfig' => $cfg,
            'briefClosureLevel' => 'platform',
            'briefClosedMessageText' => function_exists('brief_platform_closed_message_text') ? brief_platform_closed_message_text() : '',
        ]);
    }
}

if (!function_exists('forum_send_moderation_alert_notifications')) {
    /**
     * @param array{action?: string, score?: float, reasons?: list<string>} $result
     */
    function forum_send_moderation_alert_notifications(int $tenantId, int $postId, array $result): void
    {
        try {
            $notif = \App\Core\Container::get(\App\Repositories\ForumNotificationRepository::class);
            if (!$notif->tableExists()) {
                return;
            }
            $users = \App\Core\Container::get(\App\Repositories\UserRepository::class);
            $ids = $users->listForumAlertRecipientUserIds($tenantId);
            $reasons = $result['reasons'] ?? [];
            $snippet = implode(', ', array_slice($reasons, 0, 4));
            foreach ($ids as $uid) {
                if ($uid <= 0) {
                    continue;
                }
                $notif->create($tenantId, $uid, 'moderation_alert', [
                    'post_id' => $postId,
                    'reasons' => $reasons,
                    'summary' => $snippet,
                ]);
            }
        } catch (\Throwable) {
        }
    }
}
