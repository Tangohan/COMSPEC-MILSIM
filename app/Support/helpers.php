<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);
        return $root . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        $config = $GLOBALS['__app_config'] ?? null;
        if ($config === null) {
            $config = require base_path('app/Config/app.php');
            $config = ['app' => $config];
        }
        $keys = explode('.', $key);
        $value = $config;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }
}

if (!function_exists('email_config')) {
    /**
     * @return array<string, mixed>
     */
    function email_config(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $path = base_path('config/email.php');
            $cfg = is_file($path) ? require $path : [];
        }

        return $cfg;
    }
}

if (!function_exists('email_file_mailer_notice')) {
    /**
     * Texte d’avertissement si MAIL_MAILER=file (pas d’envoi Internet, fichiers .eml sur le serveur).
     */
    function email_file_mailer_notice(): string
    {
        $m = strtolower((string) (email_config()['default_mailer'] ?? ''));

        return $m === 'file'
            ? 'Le courrier est en mode « fichier » : les messages sont enregistrés dans storage/mail-outbox/ sur le serveur, ils ne partent pas sur Internet. Pour un envoi réel, configurez SMTP (MAIL_MAILER=smtp et MAIL_HOST, etc.) dans .env.'
            : '';
    }
}

if (!function_exists('email_brand_name')) {
    function email_brand_name(): string
    {
        $n = trim((string) env('APP_NAME', 'Athena'));

        return $n !== '' ? $n : 'Athena';
    }
}

if (!function_exists('email_html_accent_hex')) {
    function email_html_accent_hex(string $accent): string
    {
        return match ($accent) {
            'amber' => '#d97706',
            'emerald' => '#059669',
            'rose' => '#e11d48',
            'slate' => '#475569',
            'indigo' => '#4f46e5',
            default => '#2563eb',
        };
    }
}

if (!function_exists('email_html_button')) {
    /**
     * Bouton d’action principal (HTML e-mail, styles inline).
     */
    function email_html_button(string $href, string $label, string $accent = 'blue'): string
    {
        $h = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        $l = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $bg = email_html_accent_hex($accent);

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px;"><tr><td>'
            . '<a href="' . $h . '" style="display:inline-block;padding:14px 28px;background-color:' . $bg . ';color:#ffffff !important;text-decoration:none;border-radius:10px;font-weight:600;font-size:15px;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;line-height:1.2;">' . $l . '</a>'
            . '</td></tr></table>';
    }
}

if (!function_exists('email_html_url_fallback')) {
    /**
     * Lien brut pour clients qui ne rendent pas le bouton.
     */
    function email_html_url_fallback(string $url): string
    {
        $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        return '<p style="margin:20px 0 0;font-size:13px;color:#64748b;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>'
            . '<p style="margin:8px 0 0;padding:12px 14px;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;word-break:break-all;font-size:12px;color:#0f172a;font-family:Consolas,\'Courier New\',monospace;line-height:1.4;">' . $u . '</p>';
    }
}

if (!function_exists('email_html_callout')) {
    /**
     * Encadré informatif (alerte légère, détail).
     */
    function email_html_callout(string $innerHtml, string $style = 'info'): string
    {
        $border = match ($style) {
            'warning' => '#fcd34d',
            'danger' => '#fecaca',
            'success' => '#a7f3d0',
            default => '#bfdbfe',
        };
        $bg = match ($style) {
            'warning' => '#fffbeb',
            'danger' => '#fef2f2',
            'success' => '#ecfdf5',
            default => '#eff6ff',
        };

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0;"><tr><td style="padding:14px 16px;background-color:' . $bg . ';border-left:4px solid ' . $border . ';border-radius:0 8px 8px 0;">'
            . '<div style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.55;color:#334155;">' . $innerHtml . '</div>'
            . '</td></tr></table>';
    }
}

if (!function_exists('email_html_layout')) {
    /**
     * Enveloppe HTML transactionnelle (tables, styles inline, pré-en-tête).
     *
     * @param array{accent?: string, footer_note?: string} $options
     */
    function email_html_layout(string $preheader, string $heading, string $bodyHtml, array $options = []): string
    {
        $accent = (string) ($options['accent'] ?? 'blue');
        $accentHex = email_html_accent_hex($accent);
        $footerNote = isset($options['footer_note']) ? trim((string) $options['footer_note']) : '';
        $brand = htmlspecialchars(email_brand_name(), ENT_QUOTES, 'UTF-8');
        $pre = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');
        $h = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
        $footerExtra = $footerNote !== ''
            ? '<br><br>' . nl2br(htmlspecialchars($footerNote, ENT_QUOTES, 'UTF-8'))
            : '';

        $wrappedBody = '<div style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.65;color:#334155;">' . $bodyHtml . '</div>';

        return '<!DOCTYPE html><html lang="fr"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1.0">'
            . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light">'
            . '<title>' . $h . '</title>'
            . '<style type="text/css">p{margin:0 0 16px;}ul{margin:0 0 16px;padding-left:22px;}li{margin:6px 0;}a{color:#2563eb;}strong{color:#0f172a;}</style>'
            . '</head>'
            . '<body style="margin:0;padding:0;background-color:#f1f5f9;-webkit-font-smoothing:antialiased;">'
            . '<span style="display:none !important;visibility:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#fff;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . $pre . '</span>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f1f5f9;"><tr><td align="center" style="padding:28px 16px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px;">'
            . '<tr><td style="background-color:#ffffff;border-radius:14px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 6px -1px rgba(15,23,42,0.06);">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td style="height:4px;line-height:4px;background-color:' . $accentHex . ';font-size:0;">&nbsp;</td></tr></table>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">'
            . '<tr><td style="padding:28px 32px 12px 32px;">'
            . '<p style="margin:0 0 6px;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:11px;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#94a3b8;">' . $brand . '</p>'
            . '<h1 style="margin:0;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:22px;font-weight:700;line-height:1.3;color:#0f172a;letter-spacing:-0.02em;">' . $h . '</h1>'
            . '</td></tr>'
            . '<tr><td style="padding:8px 32px 28px 32px;">' . $wrappedBody . '</td></tr>'
            . '<tr><td style="padding:20px 32px;background-color:#f8fafc;border-top:1px solid #e2e8f0;">'
            . '<p style="margin:0;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.55;color:#64748b;">'
            . 'Message automatique — merci de ne pas répondre directement à cette adresse.'
            . $footerExtra
            . '</p></td></tr></table>'
            . '</td></tr></table>'
            . '</td></tr></table></body></html>';
    }
}

if (!function_exists('privacy_request_inbox_email')) {
    /**
     * Adresse qui reçoit les demandes « données personnelles » (formulaire public).
     * Priorité : PRIVACY_REQUEST_EMAIL, puis APP_PUBLISHER_CONTACT_EMAIL.
     */
    function privacy_request_inbox_email(): ?string
    {
        foreach (['PRIVACY_REQUEST_EMAIL', 'APP_PUBLISHER_CONTACT_EMAIL'] as $key) {
            $e = trim((string) env($key, ''));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                return $e;
            }
        }

        return null;
    }
}

if (!function_exists('legal_public_contact_email')) {
    /**
     * Adresse de contact affichée au public (liens mailto, mentions).
     * Priorité : APP_PUBLISHER_CONTACT_EMAIL, puis boîte des demandes données personnelles.
     */
    function legal_public_contact_email(): ?string
    {
        $e = trim((string) env('APP_PUBLISHER_CONTACT_EMAIL', ''));
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            return $e;
        }

        return privacy_request_inbox_email();
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(env('APP_URL', ''), '/');
        $prefix = rtrim((string) env('APP_BASE_PATH', ''), '/');
        // Si APP_BASE_PATH non défini (ex. .env non chargé), déduire /public depuis SCRIPT_NAME
        if ($prefix === '' && isset($_SERVER['SCRIPT_NAME']) && str_contains((string) $_SERVER['SCRIPT_NAME'], '/public/')) {
            $prefix = '/public';
        }
        $base = $base . $prefix;
        return $base . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('back_office_path_suffix')) {
    /**
     * Chemin HTTP normalisé après le préfixe d’application (ex. « back-office/users »), sans slash initial/final.
     */
    function back_office_path_suffix(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        $prefix = rtrim((string) env('APP_BASE_PATH', ''), '/');
        if ($prefix === '' && isset($_SERVER['SCRIPT_NAME']) && str_contains((string) $_SERVER['SCRIPT_NAME'], '/public/')) {
            $prefix = '/public';
        }
        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix)) ?: '/';
        }

        return trim($path, '/');
    }
}

if (!function_exists('is_back_office_request')) {
    function is_back_office_request(): bool
    {
        $p = back_office_path_suffix();

        return $p === 'back-office' || str_starts_with($p, 'back-office/');
    }
}

if (!function_exists('training_studio_path')) {
    /**
     * Chemin URL du Studio LMS (back-office ressources), sans slash initial.
     */
    function training_studio_path(): string
    {
        return 'back-office/ressources/training/studio';
    }
}

if (!function_exists('training_studio_url')) {
    /**
     * Lien vers le Studio LMS (création / édition des parcours) — toujours sous le back-office communauté.
     *
     * @param string|int $suffix ex. '', 'versions', 12, '12/fiche', '12/preview'
     */
    function training_studio_url(string|int $suffix = ''): string
    {
        $base = training_studio_path();
        if ($suffix === '') {
            return url($base);
        }
        $suffix = trim((string) $suffix, '/');

        return $suffix === '' ? url($base) : url($base . '/' . $suffix);
    }
}

if (!function_exists('training_lms_admin_path')) {
    /**
     * Chemin URL canonique de l’admin LMS communauté (hors Studio), sans slash initial.
     */
    function training_lms_admin_path(): string
    {
        return 'back-office/ressources/training';
    }
}

if (!function_exists('training_lms_admin_url')) {
    /**
     * Lien vers le tableau de bord LMS, catalogue, inscriptions, etc. (back-office ressources).
     *
     * @param string $suffix ex. '', 'courses', 'enrollments', 'courses/12/showcase'
     */
    function training_lms_admin_url(string $suffix = ''): string
    {
        $base = training_lms_admin_path();
        if ($suffix === '') {
            return url($base);
        }
        $suffix = trim($suffix, '/');

        return $suffix === '' ? url($base) : url($base . '/' . $suffix);
    }
}

if (!function_exists('training_lms_admin_redirect_from_legacy')) {
    /**
     * Redirige une ancienne URL /admin/training/... vers le back-office en conservant les paramètres de requête (ex. course_id).
     */
    function training_lms_admin_redirect_from_legacy(\App\Core\Request $request, string $suffix = ''): \App\Core\Response
    {
        $target = training_lms_admin_url($suffix);
        $params = $request->queryParams();
        $qs = http_build_query($params);
        if ($qs !== '') {
            $target .= (str_contains($target, '?') ? '&' : '?') . $qs;
        }

        return \App\Core\Response::redirect($target);
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        $gate = \App\Core\Gate::getInstance();
        return $gate->allows($permission);
    }
}

if (!function_exists('training_course_default_cover_url')) {
    /**
     * Visuel par défaut catalogue / fiche formation lorsqu’aucune miniature ni bannière n’est définie.
     */
    function training_course_default_cover_url(): string
    {
        return 'https://www.armytimes.com/resizer/v2/RAZQ3MLRIBFRLBIO4MWPXAB6XM.jpg?width=1200&auth=45ae6a1e3391a70c6e9e748d98ade72e1ed3f43ae5d0a5441a65e1d8a4a93e00';
    }
}

if (!function_exists('training_media_url')) {
    /**
     * URL absolue pour miniature / bannière formation (chemin relatif public ou URL externe).
     */
    function training_media_url(?string $path): string
    {
        if ($path === null || trim($path) === '') {
            return training_course_default_cover_url();
        }
        $path = trim($path);
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return rtrim(url(''), '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}

if (!function_exists('audit_action_label_fr')) {
    /**
     * Libellé français pour une action du journal audit_logs.
     */
    function audit_action_label_fr(string $action): string
    {
        return \App\Services\Audit\AuditActionLabel::toFrench($action);
    }
}

if (!function_exists('training_showcase_badge_meta')) {
    /**
     * @return array{label: string, classes: string}
     */
    function training_showcase_badge_meta(?string $badge): array
    {
        return match ($badge ?? 'open') {
            'full' => ['label' => 'Complet', 'classes' => 'bg-slate-600'],
            'coming_soon' => ['label' => 'Bientôt', 'classes' => 'bg-amber-500'],
            'closed' => ['label' => 'Fermé', 'classes' => 'bg-red-600'],
            default => ['label' => 'Ouvert', 'classes' => 'bg-emerald-500'],
        };
    }
}

if (!function_exists('detect_current_module')) {
    /**
     * Module métier pour les scopes `module:` (aligné sur routes/web.php).
     */
    function detect_current_module(string $path): ?string
    {
        if (preg_match('#^/c/[^/]+/forum#', $path) === 1) {
            return 'forum';
        }
        if (str_starts_with($path, '/forum') || str_starts_with($path, '/api/forum')) {
            return 'forum';
        }

        $atakPrefixes = [
            '/atak', '/api/atak', '/api/markers', '/api/units', '/api/chat', '/api/pings',
            '/api/nine-line', '/api/cas', '/api/recon', '/api/map-shapes', '/api/flight-manifest',
            '/api/intel', '/api/fire-support', '/api/danger-zones', '/api/logistics', '/api/replay', '/api/iff',
        ];
        foreach ($atakPrefixes as $pre) {
            if ($path === $pre || str_starts_with($path, $pre . '/')) {
                return 'atak';
            }
        }

        if (str_starts_with($path, '/documents')) {
            return 'documents';
        }
        if (str_starts_with($path, '/courrier')) {
            return 'courrier';
        }
        if (
            str_starts_with($path, '/formations')
            || str_starts_with($path, '/api/training')
            || str_starts_with($path, '/admin/training')
            || str_starts_with($path, '/back-office/ressources/training')
        ) {
            return 'training';
        }
        if (str_starts_with($path, '/admin')) {
            return 'admin';
        }

        return null;
    }
}

if (is_file(base_path('app/Support/navigation_menu.php'))) {
    require_once base_path('app/Support/navigation_menu.php');
}

if (!function_exists('community_display_name')) {
    /**
     * Libellé UI pour le tenant système (slug `default`) : jamais « Default Organisation ».
     *
     * @param array{name?: string, slug?: string} $tenantOrMembershipRow
     */
    function community_display_name(array $tenantOrMembershipRow): string
    {
        if (($tenantOrMembershipRow['slug'] ?? '') === 'default') {
            return 'Pas d\'organisation';
        }

        return (string) ($tenantOrMembershipRow['name'] ?? '');
    }
}

if (is_file(base_path('app/Support/portal_header.php'))) {
    require_once base_path('app/Support/portal_header.php');
}

if (is_file(base_path('app/Support/training_canvas.php'))) {
    require_once base_path('app/Support/training_canvas.php');
}

if (is_file(base_path('app/Support/training_lesson_payloads.php'))) {
    require_once base_path('app/Support/training_lesson_payloads.php');
}

if (is_file(base_path('app/Support/training_lms.php'))) {
    require_once base_path('app/Support/training_lms.php');
}

if (is_file(base_path('app/Support/lms_platform_version.php'))) {
    require_once base_path('app/Support/lms_platform_version.php');
}

if (!function_exists('training_legacy_enabled')) {
    /**
     * Modules legacy_training_* dans le catalogue et liaisons documentaires (désactivable en prod).
     */
    function training_legacy_enabled(): bool
    {
        $v = $_ENV['TRAINING_LEGACY_ENABLED'] ?? getenv('TRAINING_LEGACY_ENABLED');
        if ($v === false || $v === null || $v === '') {
            return true;
        }

        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }
}

require __DIR__ . '/forum_helpers.php';
