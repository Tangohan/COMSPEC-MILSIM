<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/error_hint.php';

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

if (!function_exists('platform_app_version')) {
    /**
     * Version applicative courante (fichier storage/app_version.json, sinon APP_VERSION / 1.0.0).
     */
    function platform_app_version(): string
    {
        $path = base_path('storage/app_version.json');
        if (is_file($path)) {
            $raw = json_decode((string) file_get_contents($path), true);
            $v = is_array($raw) ? trim((string) ($raw['version'] ?? '')) : '';
            if ($v !== '' && preg_match('/^\d+\.\d+\.\d+/', $v)) {
                return $v;
            }
        }

        $fromEnv = trim((string) (function_exists('env') ? env('APP_VERSION', '') : ''));

        return $fromEnv !== '' ? $fromEnv : '1.0.0';
    }
}

if (!function_exists('asset_url')) {
    /**
     * URL d’asset avec cache-busting ?v=version applicative.
     */
    function asset_url(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $base = function_exists('url') ? rtrim(url(''), '/') : '';
        $sep = str_contains($path, '?') ? '&' : '?';

        return $base . $path . $sep . 'v=' . rawurlencode(platform_app_version());
    }
}

if (!function_exists('user_media_public_url')) {
    /**
     * Résout une photo / bannière utilisateur (chemin relatif uploads/… ou URL absolue http(s)).
     * Sur Athena (APP_BASE_PATH=/public), force le préfixe /public devant /uploads/.
     */
    function user_media_public_url(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        $path = str_replace('\\', '/', $path);
        // Chemin absolu serveur → segment uploads/…
        if (preg_match('#(?:^|/)(uploads/.+)$#i', $path, $m)
            && (preg_match('#^[a-z]:/#i', $path) === 1 || str_contains($path, '/public/') || str_starts_with($path, '/home/'))
        ) {
            $path = $m[1];
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            return normalize_public_uploads_url($path);
        }
        $base = function_exists('url') ? rtrim(url(''), '/') : '';
        $rel = ltrim($path, '/');
        // Évite /public/public/uploads
        $prefix = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
        if ($prefix !== '' && str_starts_with('/' . $rel, $prefix . '/')) {
            $rel = ltrim(substr('/' . $rel, strlen($prefix)), '/');
        }

        return normalize_public_uploads_url($base . '/' . $rel);
    }
}

if (!function_exists('normalize_public_uploads_url')) {
    /**
     * Corrige les URLs du type https://host/uploads/… (404) → …/public/uploads/….
     */
    function normalize_public_uploads_url(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        $prefix = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
        if ($prefix === '' && isset($_SERVER['SCRIPT_NAME']) && str_contains((string) $_SERVER['SCRIPT_NAME'], '/public/')) {
            $prefix = '/public';
        }
        if ($prefix === '') {
            $prefix = '/public';
        }
        // Déjà préfixé
        if (preg_match('#' . preg_quote($prefix, '#') . '/uploads/#i', $url) === 1) {
            return $url;
        }
        if (preg_match('#^(https?://[^/]+)/uploads/#i', $url) === 1) {
            return (string) preg_replace('#^(https?://[^/]+)/uploads#i', '$1' . $prefix . '/uploads', $url, 1);
        }
        if (str_starts_with($url, '/uploads/')) {
            return $prefix . $url;
        }

        return $url;
    }
}

if (!function_exists('user_display_initials')) {
    /**
     * Initiale(s) de repli pour l’avatar (1 caractère par défaut).
     */
    function user_display_initials(string $displayName, int $length = 1): string
    {
        $clean = preg_replace('/\s+/u', '', $displayName) ?: 'A';
        $len = max(1, min(3, $length));

        return mb_strtoupper(mb_substr($clean, 0, $len));
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

if (!function_exists('locale')) {
    /** Locale UI courante (`fr` | `en`). */
    function locale(): string
    {
        $cached = $GLOBALS['__app_locale'] ?? null;
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return \App\Services\I18n\LocaleService::normalize((string) config('app.locale', 'fr'));
    }
}

if (!function_exists('html_lang')) {
    /** Valeur de l’attribut HTML `lang`. */
    function html_lang(): string
    {
        return locale() === 'en' ? 'en' : 'fr';
    }
}

if (!function_exists('__')) {
    /**
     * Traduction UI (catalogues lang/{locale}/*.php).
     *
     * @param array<string, scalar|null> $replace Placeholders `:name`
     */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        $translator = $GLOBALS['__app_translator'] ?? null;
        if (!$translator instanceof \App\Services\I18n\Translator) {
            $translator = new \App\Services\I18n\Translator();
            $translator->setLocale(locale());
            $GLOBALS['__app_translator'] = $translator;
        }

        return $translator->get($key, $replace, $locale);
    }
}

if (!function_exists('locale_switch_url')) {
    /** URL pour basculer la langue puis revenir à la page courante (ou un chemin donné). */
    function locale_switch_url(string $locale, ?string $redirectPath = null): string
    {
        $locale = \App\Services\I18n\LocaleService::normalize($locale);
        if ($redirectPath === null) {
            $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
            $prefix = rtrim((string) env('APP_BASE_PATH', ''), '/');
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix)) ?: '/';
            }
            $qs = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY) ?: '');
            $redirectPath = $qs !== '' ? ($path . '?' . $qs) : $path;
        }

        return url('locale/' . rawurlencode($locale)) . '?redirect=' . rawurlencode($redirectPath);
    }
}

if (!function_exists('app_environment_label_fr')) {
    /**
     * Libellé français pour APP_ENV (cohérent admin système / paramètres).
     */
    function app_environment_label_fr(?string $appEnvRaw = null): string
    {
        $raw = $appEnvRaw ?? (function_exists('env') ? (string) env('APP_ENV', 'local') : 'local');

        return match ($raw) {
            'production' => 'Production',
            'staging' => 'Préproduction',
            default => 'Développement / local',
        };
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

if (!function_exists('demo_feedback_inbox_email')) {
    /**
     * Adresse qui reçoit le questionnaire de retour après une démonstration.
     * Priorité : DEMO_NDA_FEEDBACK_EMAIL, APP_PUBLISHER_CONTACT_EMAIL, PRIVACY_REQUEST_EMAIL.
     */
    function demo_feedback_inbox_email(): ?string
    {
        foreach (['DEMO_NDA_FEEDBACK_EMAIL', 'APP_PUBLISHER_CONTACT_EMAIL', 'PRIVACY_REQUEST_EMAIL'] as $key) {
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
        // Évite /public/public si APP_URL contient déjà le préfixe (erreur de config fréquente).
        if ($prefix !== '' && ($base === $prefix || str_ends_with($base, $prefix))) {
            // préfixe déjà présent dans APP_URL
        } else {
            $base .= $prefix;
        }

        return $base . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('atak_tile_cdn_base')) {
    /**
     * Base CDN des tuiles Arma (sans slash final).
     * Défaut : GitHub Pages jetelain/Arma3Map (CORS OK).
     * OVH plan-ops : ATAK_MAP_TILES_CDN=https://mapsdata.plan-ops.fr
     */
    function atak_tile_cdn_base(): string
    {
        $raw = trim((string) env('ATAK_MAP_TILES_CDN', 'https://jetelain.github.io/Arma3Map'));

        return rtrim($raw !== '' ? $raw : 'https://jetelain.github.io/Arma3Map', '/');
    }
}

if (!function_exists('atak_marker_icons_cdn_base')) {
    /**
     * Base CDN des icônes marqueurs Arma (PAA→PNG), sans slash final.
     * Env : ATAK_MARKER_ICONS_CDN — défaut local /assets/markers/arma
     */
    function atak_marker_icons_cdn_base(): string
    {
        $raw = trim((string) env('ATAK_MARKER_ICONS_CDN', ''));
        if ($raw !== '') {
            return rtrim($raw, '/');
        }

        return rtrim(url('assets/markers/arma'), '/');
    }
}

if (!function_exists('atak_marker_icon_rewrite_addon_prefix')) {
    /**
     * Aligne les préfixes PBO jeu / forks (Iceman, NLN cTab, MarkersPlus) sur l’arborescence PNG du portail.
     */
    function atak_marker_icon_rewrite_addon_prefix(string $rel): string
    {
        $map = [
            'nln_ctab_core/' => 'ctab/',
            'nln_ctab/' => 'ctab/',
            'ctab_core/' => 'ctab/',
            'ctab_rev/' => 'ctab/',
            'ctab_enhanced/' => 'ctab/',
            'iceman_atak/' => 'ctab/',
            'iceman/' => 'ctab/',
            'markers_plus/' => 'markersplus/',
            'plp_markersplus/' => 'markersplus/',
        ];
        foreach ($map as $from => $to) {
            if (str_starts_with($rel, $from)) {
                return $to . substr($rel, strlen($from));
            }
        }

        return $rel;
    }
}

if (!function_exists('atak_marker_icon_relpath')) {
    /**
     * Normalise un chemin texture Arma (.paa) vers un chemin relatif PNG minuscule.
     * Ex. \A3\ui_f\data\map\markers\military\warning_CA.paa
     *   → a3/ui_f/data/map/markers/military/warning_ca.png
     */
    function atak_marker_icon_relpath(?string $texturePath): ?string
    {
        $raw = trim((string) $texturePath);
        if ($raw === '' || str_starts_with($raw, '#')) {
            return null;
        }
        $normalized = str_replace('\\', '/', $raw);
        $normalized = ltrim($normalized, '/');
        if ($normalized === '') {
            return null;
        }
        // Retirer un éventuel préfixe drive Windows (rare hors jeu)
        $normalized = (string) preg_replace('#^[a-z]:/#i', '', $normalized);
        if (preg_match('/\.paa$/i', $normalized) === 1) {
            $normalized = (string) preg_replace('/\.paa$/i', '.png', $normalized);
        } elseif (preg_match('/\.(png|jpg|jpeg|webp|svg)$/i', $normalized) !== 1) {
            $normalized .= '.png';
        }
        $normalized = strtolower($normalized);
        // Un identifiant numérique n’est pas une texture jeu (évite 1.png, 2.png… en relatif).
        if (preg_match('/^\d+\.(png|jpg|jpeg|webp|svg)$/', $normalized) === 1) {
            return null;
        }
        // Segments sûrs uniquement
        $parts = array_values(array_filter(explode('/', $normalized), static fn ($p) => $p !== '' && $p !== '.' && $p !== '..'));
        if ($parts === []) {
            return null;
        }
        if (count($parts) === 1 && preg_match('/^\d+\.(png|jpg|jpeg|webp|svg)$/', $parts[0]) === 1) {
            return null;
        }

        return atak_marker_icon_rewrite_addon_prefix(implode('/', $parts));
    }
}

if (!function_exists('atak_marker_icon_relpath_from_type')) {
    /**
     * Dérive un PNG du classname CfgMarkers quand le jeu n’a pas envoyé de texture.
     */
    function atak_marker_icon_relpath_from_type(?string $type): ?string
    {
        $key = strtolower(trim(str_replace([' ', '-'], '_', (string) $type)));
        if ($key === '') {
            return null;
        }
        if (str_starts_with($key, 'mplus_')) {
            $rest = substr($key, 6);

            return $rest !== '' ? 'markersplus/data/img/' . $rest . '.png' : null;
        }
        if (preg_match('/^mts_(blu|red|neu|unk|com|bludash|reddash)_mod_(.+)$/', $key, $m) === 1) {
            $aff = $m[1];
            $role = $m[2];

            return 'z/mts/addons/markers/data/' . $aff . '/mod/mts_markers_' . $aff . '_mod_' . $role . '.png';
        }
        if (str_starts_with($key, 'mil_')) {
            $rest = substr($key, 4);

            return $rest !== '' ? 'a3/ui_f/data/map/markers/military/' . $rest . '_ca.png' : null;
        }
        if (str_starts_with($key, 'hd_')) {
            $rest = substr($key, 3);

            return $rest !== '' ? 'a3/ui_f/data/map/markers/handdrawn/' . $rest . '_ca.png' : null;
        }
        if (preg_match('/^[boncu]_[a-z0-9_]+$/', $key) === 1) {
            return 'a3/ui_f/data/map/markers/nato/' . $key . '.png';
        }

        return null;
    }
}

if (!function_exists('atak_marker_icon_url')) {
    /**
     * URL absolue (ou sous APP_URL) d’une icône marqueur PNG dérivée d’un chemin PAA Arma.
     */
    function atak_marker_icon_url(?string $texturePath): ?string
    {
        $rel = atak_marker_icon_relpath($texturePath);
        if ($rel === null || $rel === '') {
            return null;
        }
        $segments = array_map('rawurlencode', explode('/', $rel));

        return atak_marker_icons_cdn_base() . '/' . implode('/', $segments);
    }
}

if (!function_exists('atak_resolve_tile_pattern')) {
    /**
     * Résout un motif de tuiles Leaflet ({z}/{x}/{y}) en URL absolue.
     * - http(s) : inchangé
     * - /assets/maps/{slug}/… ou maps/{slug}/… : CDN (évite le 404 sous /public)
     * - autre chemin relatif : préfixé avec APP_URL + APP_BASE_PATH
     */
    function atak_resolve_tile_pattern(?string $pattern, ?string $mapSlug = null): string
    {
        $pattern = trim((string) $pattern);
        $slug = strtolower(trim((string) ($mapSlug ?? '')));
        if ($slug === '') {
            $slug = 'altis';
        }

        if ($pattern === '') {
            return atak_tile_cdn_base() . '/maps/' . rawurlencode($slug) . '/{z}/{x}/{y}.png';
        }

        if (preg_match('#^https?://#i', $pattern) === 1) {
            return $pattern;
        }

        $normalized = str_replace('\\', '/', $pattern);
        // Anciens motifs locaux → CDN
        if (
            preg_match('#^(?:/)?(?:assets/)?maps/([a-z0-9_-]+)/\{z\}/\{x\}/\{y\}\.png$#i', $normalized, $m) === 1
            || preg_match('#ressources/MapViewers/maps/([a-z0-9_-]+)/\{z\}/\{x\}/\{y\}\.png$#i', $normalized, $m) === 1
        ) {
            $fromPath = strtolower((string) ($m[1] ?? $slug));

            return atak_tile_cdn_base() . '/maps/' . rawurlencode($fromPath !== '' ? $fromPath : $slug) . '/{z}/{x}/{y}.png';
        }

        $base = rtrim(url(''), '/');
        if (str_starts_with($normalized, '/')) {
            return $base . $normalized;
        }

        return $base . '/' . ltrim($normalized, '/');
    }
}

if (!function_exists('atak_client_base_url')) {
    /**
     * Adresse de base à communiquer au mod Arma / aux écrans ATAK : portail courant,
     * ou URL configurée pour l’équipe, en corrigeant l’oubli du sous-chemin d’appli
     * (ex. /public) lorsque l’origine (hôte + schéma) est la même que le portail.
     */
    function atak_client_base_url(?array $tenantAtakConfig): string
    {
        $portal = rtrim(url(''), '/');
        $fromDb = trim((string) ($tenantAtakConfig['node_url'] ?? ''));
        $raw = $fromDb !== '' ? $fromDb : trim((string) env('NODE_ATAK_URL', ''));
        if ($raw === '') {
            return $portal;
        }

        $nodeBase = rtrim($raw, '/');
        $pn = parse_url($portal) ?: [];
        $nn = parse_url($nodeBase) ?: [];
        if (($nn['host'] ?? '') === '' || ($pn['host'] ?? '') === '') {
            return $nodeBase;
        }
        if (strcasecmp((string) $nn['host'], (string) $pn['host']) !== 0) {
            return $nodeBase;
        }
        $pscheme = strtolower((string) ($pn['scheme'] ?? 'https'));
        $nscheme = strtolower((string) ($nn['scheme'] ?? 'https'));
        if ($pscheme !== $nscheme) {
            return $nodeBase;
        }
        $ppath = rtrim((string) ($pn['path'] ?? ''), '/');
        $npath = rtrim((string) ($nn['path'] ?? ''), '/');
        if ($ppath !== '' && ($npath === '' || $npath === '/')) {
            return $portal;
        }

        return $nodeBase;
    }
}

if (!function_exists('back_office_path_suffix')) {
    /**
     * Chemin HTTP normalisé après le préfixe d’application (ex. « back-office/users »), sans slash initial/final.
     * Aligné sur {@see \App\Core\Request::normalizePathFromServer()} (docroot « public » + segment /public/ dans l’URI).
     */
    function back_office_path_suffix(): string
    {
        return trim(\App\Core\Request::normalizePathFromServer(), '/');
    }
}

if (!function_exists('back_office_nav_href_to_path')) {
    /**
     * Extrait le chemin applicatif normalisé d’un href de menu back-office.
     */
    function back_office_nav_href_to_path(string $href): string
    {
        $path = $href;
        if (preg_match('#^https?://#i', $href)) {
            $parsed = parse_url($href, PHP_URL_PATH);
            $path = is_string($parsed) ? $parsed : '';
        }
        $path = trim((string) $path, '/');
        foreach (['public/', 'index.php/'] as $strip) {
            if (str_starts_with($path, $strip)) {
                $path = substr($path, strlen($strip));
            }
        }

        return $path;
    }
}

if (!function_exists('back_office_nav_path_match_score')) {
    /**
     * Score de correspondance chemin courant ↔ entrée de menu (exact > préfixe le plus long).
     */
    function back_office_nav_path_match_score(string $itemPath, string $currentPath): int
    {
        $itemPath = trim($itemPath, '/');
        $currentPath = trim($currentPath, '/');
        if ($itemPath === '' && $currentPath === '') {
            return 10000;
        }
        if ($itemPath === '' || $currentPath === '') {
            return 0;
        }
        if ($currentPath === $itemPath) {
            return 1000 + strlen($itemPath);
        }
        if (str_starts_with($currentPath, $itemPath . '/')) {
            return strlen($itemPath);
        }

        return 0;
    }
}

if (!function_exists('back_office_nav_resolve_sibling_active')) {
    /**
     * Parmi des entrées sœurs, ne conserve l’état actif que sur la correspondance la plus spécifique.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    function back_office_nav_resolve_sibling_active(array $items, string $currentPath): array
    {
        if ($items === []) {
            return $items;
        }

        $candidates = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $hrefPath = back_office_nav_href_to_path((string) ($item['href'] ?? ''));
            $pathScore = back_office_nav_path_match_score($hrefPath, $currentPath);
            $explicit = !empty($item['active']);
            if ($pathScore > 0 || $explicit) {
                $candidates[] = [
                    'index' => $index,
                    'path' => $hrefPath,
                    'pathScore' => $pathScore,
                ];
            }
        }

        if ($candidates === []) {
            foreach ($items as &$item) {
                if (is_array($item)) {
                    $item['active'] = false;
                }
            }
            unset($item);

            return $items;
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['pathScore'] !== $b['pathScore']) {
                return $b['pathScore'] <=> $a['pathScore'];
            }
            if ($a['pathScore'] > 0 && $b['pathScore'] === 0) {
                return -1;
            }
            if ($b['pathScore'] > 0 && $a['pathScore'] === 0) {
                return 1;
            }

            return $a['index'] <=> $b['index'];
        });

        $winnerIndex = $candidates[0]['index'];
        foreach ($items as $index => &$item) {
            if (!is_array($item)) {
                continue;
            }
            $item['active'] = $index === $winnerIndex;
        }
        unset($item);

        return $items;
    }
}

if (!function_exists('is_back_office_request')) {
    function is_back_office_request(): bool
    {
        $p = back_office_path_suffix();
        if ($p === 'back-office' || str_starts_with($p, 'back-office/')) {
            return true;
        }

        // Filet : préfixe /public/ non retiré (APP_BASE_PATH incohérent).
        return $p === 'public/back-office' || str_starts_with($p, 'public/back-office/');
    }
}

if (!function_exists('is_formation_workspace_request')) {
    /**
     * Pilotage LMS communauté : chemins /formation et /formation/*.
     * La racine /formation s’affiche dans la coque catalogue (même bandeau latéral que /formations, sans navbar portail).
     * Les sous-pages /formation/… conservent la coque portail + barre latérale back-office.
     * Ne pas confondre avec le catalogue apprenant /formations.
     */
    function is_formation_workspace_request(): bool
    {
        $p = back_office_path_suffix();

        return $p === 'formation' || str_starts_with($p, 'formation/');
    }
}

if (!function_exists('is_platform_site_admin_shell_request')) {
    /**
     * Pages d’administration « site entier » (pilotage / infra) avec barre latérale dédiée.
     * Exclut les écrans rattachés à la communauté active (/admin/modpacks, /admin/atak-config, etc.).
     */
    function is_platform_site_admin_shell_request(): bool
    {
        $p = back_office_path_suffix();
        if ($p === 'admin') {
            return true;
        }
        if (!str_starts_with($p, 'admin/')) {
            return false;
        }
        $rest = substr($p, strlen('admin/'));
        $prefixes = ['ops-center', 'audit', 'analytics', 'newsletter', 'content-moderation', 'maintenance', 'roles', 'settings', 'site-roles', 'tenants'];
        foreach ($prefixes as $prefix) {
            if ($rest === $prefix || str_starts_with($rest, $prefix . '/')) {
                return true;
            }
        }
        if ($rest === 'system' || str_starts_with($rest, 'system/')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('interteam_mission_status_label')) {
    /**
     * Libellé métier à partir du seul champ status (sans ligne mission complète).
     *
     * @deprecated Préférer {@see cooperation_mission_display_label()} lorsque la ligne mission est disponible.
     */
    function interteam_mission_status_label(string $status): string
    {
        return \App\Support\CooperationDictionary::labelFromLegacyStatus($status);
    }
}

if (!function_exists('cooperation_mission_display_label')) {
    /**
     * Libellé d’état à afficher (phase métier ou repli sur status).
     *
     * @param array<string, mixed> $mission
     */
    function cooperation_mission_display_label(array $mission): string
    {
        return \App\Support\CooperationDictionary::phaseLabel(
            \App\Support\CooperationDictionary::effectivePhase($mission)
        );
    }
}

if (!function_exists('cooperation_mission_index_url')) {
    function cooperation_mission_index_url(): string
    {
        return url('back-office/cooperation/missions');
    }
}

if (!function_exists('cooperation_mission_create_url')) {
    function cooperation_mission_create_url(): string
    {
        return url('back-office/cooperation/missions/create');
    }
}

if (!function_exists('cooperation_mission_show_url')) {
    function cooperation_mission_show_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id);
    }
}

if (!function_exists('cooperation_mission_edit_url')) {
    function cooperation_mission_edit_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id . '/edit');
    }
}

if (!function_exists('cooperation_mission_exchange_url')) {
    function cooperation_mission_exchange_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id . '/exchange');
    }
}

if (!function_exists('cooperation_mission_consent_url')) {
    function cooperation_mission_consent_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id . '/consent');
    }
}

if (!function_exists('cooperation_mission_timeline_url')) {
    function cooperation_mission_timeline_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id . '/timeline');
    }
}

if (!function_exists('cooperation_mission_timeline_export_url')) {
    function cooperation_mission_timeline_export_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id . '/timeline-export');
    }
}

if (!function_exists('cooperation_mission_meeting_url')) {
    function cooperation_mission_meeting_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id . '/meeting');
    }
}

if (!function_exists('cooperation_mission_orbat_url')) {
    function cooperation_mission_orbat_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id . '/orbat');
    }
}

if (!function_exists('cooperation_mission_archive_url')) {
    function cooperation_mission_archive_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id . '/archive');
    }
}

if (!function_exists('cooperation_mission_negotiate_url')) {
    function cooperation_mission_negotiate_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id . '/negotiate');
    }
}

if (!function_exists('cooperation_mission_rex_url')) {
    function cooperation_mission_rex_url(string|int $id): string
    {
        return url('back-office/cooperation/missions/' . (int) $id . '/rex');
    }
}

if (!function_exists('cooperation_missions_url')) {
    /**
     * URL du module (suffixe optionnel : « create », « 12/consent », etc.).
     * Préférer les helpers {@see cooperation_mission_show_url()} etc. pour éviter les chemins en dur.
     */
    function cooperation_missions_url(string $suffix = ''): string
    {
        if ($suffix === '') {
            return cooperation_mission_index_url();
        }

        return url('back-office/cooperation/missions/' . ltrim($suffix, '/'));
    }
}

if (!function_exists('training_studio_path')) {
    /**
     * Chemin URL du Studio LMS, sans slash initial.
     */
    function training_studio_path(): string
    {
        return 'formation/studio';
    }
}

if (!function_exists('training_studio_url')) {
    /**
     * Lien vers le Studio LMS (création / édition des parcours).
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
        return 'formation';
    }
}

if (!function_exists('training_lms_admin_url')) {
    /**
     * Lien vers le tableau de bord LMS, catalogue, inscriptions, etc.
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

if (!function_exists('training_lms_legacy_bo_training_path_prefix')) {
    /**
     * Ancien préfixe URL du pilotage LMS (redirections de compatibilité).
     */
    function training_lms_legacy_bo_training_path_prefix(): string
    {
        return 'back-office/ressources/training';
    }
}

if (!function_exists('training_lms_redirect_legacy_bo_training_to_formation')) {
    /**
     * Redirige une URL historique /back-office/ressources/training/… vers /formation/… (query conservée).
     * POST → 307 pour conserver la méthode et le corps selon les clients HTTP.
     */
    function training_lms_redirect_legacy_bo_training_to_formation(\App\Core\Request $request): \App\Core\Response
    {
        $p = back_office_path_suffix();
        $legacy = training_lms_legacy_bo_training_path_prefix();
        if ($p === $legacy) {
            $rel = '';
        } elseif (str_starts_with($p, $legacy . '/')) {
            $rel = substr($p, strlen($legacy) + 1);
        } else {
            $rel = '';
        }
        $target = $rel === '' ? url('formation') : url('formation/' . $rel);
        $params = $request->queryParams();
        $qs = http_build_query($params);
        if ($qs !== '') {
            $target .= (str_contains($target, '?') ? '&' : '?') . $qs;
        }
        $code = strtoupper($request->method()) === 'POST' ? 307 : 302;

        return \App\Core\Response::redirect($target, $code);
    }
}

if (!function_exists('training_lms_admin_redirect_from_legacy')) {
    /**
     * Redirige une ancienne URL /admin/training/... vers l’espace /formation en conservant les paramètres de requête (ex. course_id).
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

if (!function_exists('recruitment_workspace_path')) {
    /**
     * Chemin URL canonique du bureau recrutement (pilotage type LMS), sans slash initial.
     */
    function recruitment_workspace_path(): string
    {
        return 'back-office/ressources/recrutement';
    }
}

if (!function_exists('recruitment_workspace_url')) {
    /**
     * Lien vers le tableau de bord recrutement ou une sous-section (ex. « analyses »).
     *
     * @param string $suffix ex. '', 'analyses'
     */
    function recruitment_workspace_url(string $suffix = ''): string
    {
        $base = recruitment_workspace_path();
        if ($suffix === '') {
            return url($base);
        }
        $suffix = trim($suffix, '/');

        return $suffix === '' ? url($base) : url($base . '/' . $suffix);
    }
}

if (!function_exists('effectifs_workspace_path')) {
    /**
     * Chemin URL canonique du bureau LMS effectifs (outil RH), sans slash initial.
     */
    function effectifs_workspace_path(): string
    {
        return 'back-office/ressources/effectifs';
    }
}

if (!function_exists('effectifs_workspace_url')) {
    /**
     * Lien vers le bureau effectifs ou une sous-section (ex. « roles », « membres/12 »).
     *
     * @param string $suffix ex. '', 'roles', 'membres/12'
     */
    function effectifs_workspace_url(string $suffix = ''): string
    {
        $base = effectifs_workspace_path();
        if ($suffix === '') {
            return url($base);
        }
        $suffix = trim($suffix, '/');

        return $suffix === '' ? url($base) : url($base . '/' . $suffix);
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        $gate = \App\Core\Gate::getInstance();
        return $gate->allows($permission);
    }
}

if (!function_exists('user_advanced_fiche_edit_grant')) {
    /**
     * Grant actif d’édition avancée de fiche (24 h), ou null.
     *
     * @return array<string, mixed>|null
     */
    function user_advanced_fiche_edit_grant(?int $userId = null): ?array
    {
        $uid = $userId ?? (int) \App\Core\Session::get('user_id');
        $tenantId = (int) \App\Core\Session::get('tenant_id');
        if ($uid < 1 || $tenantId < 1) {
            return null;
        }
        try {
            /** @var \App\Repositories\UserAdvancedEditGrantRepository $repo */
            $repo = \App\Core\Container::get(\App\Repositories\UserAdvancedEditGrantRepository::class);

            return $repo->findActiveForUser($tenantId, $uid);
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('user_has_advanced_fiche_edit')) {
    /** True si l’utilisateur a un mode édition avancée de fiche actif. */
    function user_has_advanced_fiche_edit(?int $userId = null): bool
    {
        return user_advanced_fiche_edit_grant($userId) !== null;
    }
}

if (!function_exists('training_course_default_cover_url')) {
    /**
     * Visuel par défaut catalogue / fiche formation lorsqu’aucune miniature ni bannière n’est définie.
     */
    function training_course_default_cover_url(): string
    {
        return rtrim(url(''), '/') . '/assets/images/formation-de-specialite.jpg';
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

        // Aligné sur TenantTypeConfig::moduleForUri — les règles plateforme « module carte » couvrent toute la surface ATAK.
        $atakPrefixes = [
            '/atak', '/tacmap', '/overwatch', '/c2',
            '/admin/atak-config', '/admin/atak-mod', '/admin/atak-mod-blocks', '/admin/atak-beta', '/admin/atak',
            '/back-office/atak', '/back-office/ressources/atak-config', '/back-office/ressources/atak-mod',
            '/back-office/ressources/atak-mod-blocks', '/back-office/ressources/atak-beta',
            '/api/atak', '/api/markers', '/api/units', '/api/chat', '/api/pings',
            '/api/nine-line', '/api/cas', '/api/recon', '/api/map-shapes', '/api/flight-manifest',
            '/api/intel', '/api/fire-support', '/api/danger-zones', '/api/logistics', '/api/replay', '/api/iff',
            '/api/tacmap', '/api/overwatch',
        ];
        // Plus long préfixe d’abord (ex. /admin/atak-config avant /admin).
        usort($atakPrefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
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
            || ($path === '/formation' || str_starts_with($path, '/formation/'))
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

if (!function_exists('email_community_label')) {
    /**
     * Libellé pour e-mails transactionnels : le tenant système (slug default) et les
     * libellés techniques (« Aucune organisation », etc.) sont remplacés par la marque.
     *
     * @param array{name?: string, slug?: string}|null $tenant
     */
    function email_community_label(?array $tenant, ?string $fallbackName = null): string
    {
        $brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        $name = trim((string) ($tenant['name'] ?? ($fallbackName ?? '')));
        if ($slug === 'default') {
            return $brand;
        }
        $normalized = mb_strtolower(str_replace(["'", '’'], "'", $name));
        $placeholders = [
            '',
            'aucune organisation',
            "pas d'organisation",
            'default organisation',
            'default',
            'communauté',
        ];
        if (in_array($normalized, $placeholders, true)) {
            return $brand;
        }

        return $name !== '' ? $name : $brand;
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

if (is_file(base_path('app/Support/cdn_media.php'))) {
    require_once base_path('app/Support/cdn_media.php');
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

if (!function_exists('bo_select_class')) {
    /**
     * Classes pour les listes déroulantes du back-office (styles natifs renforcés via `select.bo-select` dans le layout).
     *
     * @param string $extra Classes Tailwind supplémentaires (ex. `mt-1`, `min-w-[14rem]`).
     */
    function bo_select_class(string $extra = ''): string
    {
        $base = 'bo-select w-full min-h-[2.75rem] rounded-lg border border-slate-300 bg-white py-2.5 pl-3 pr-10 text-sm font-medium text-slate-900 shadow-sm transition '
            . 'hover:border-slate-400 '
            . 'focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/25 '
            . 'disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 disabled:border-slate-200 disabled:shadow-none';
        $extra = trim($extra);

        return $extra !== '' ? $base . ' ' . $extra : $base;
    }
}

if (!function_exists('format_arma_playtime_french')) {
    /**
     * Libellé lisible du temps de mission transmis par ATAK (secondes cumulées).
     */
    function format_arma_playtime_french(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'Pas encore enregistré';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0) {
            return $m > 0 ? "{$h} h {$m} min" : "{$h} h";
        }
        if ($m > 0) {
            return "{$m} min";
        }

        return 'Moins d’une minute';
    }
}

if (!function_exists('sse_ui_theme_options')) {
    /**
     * Apparence unique du portail SSE (bureau type LMS Effectifs / tableau de bord).
     *
     * @return array<string, array{label:string,hint:string}>
     */
    function sse_ui_theme_options(): array
    {
        return [
            'bureau' => [
                'label' => 'Bureau SSE',
                'hint' => 'Espace de travail dense, typographie Inter, accents slate et émeraude.',
            ],
        ];
    }
}

if (!function_exists('sse_ui_theme_normalize')) {
    function sse_ui_theme_normalize(?string $theme): string
    {
        $theme = strtolower(trim((string) $theme));
        // Anciens cookies (console / confidentiel / archive) → apparence unique.
        if (in_array($theme, ['console', 'confidentiel', 'archive', 'control', 'athena'], true)) {
            return 'bureau';
        }

        return array_key_exists($theme, sse_ui_theme_options()) ? $theme : 'bureau';
    }
}

if (!function_exists('sse_ui_theme')) {
    /** Apparence SSE courante (cookie, défaut : Bureau SSE). */
    function sse_ui_theme(): string
    {
        return sse_ui_theme_normalize($_COOKIE['sse_ui_theme'] ?? null);
    }
}

if (!function_exists('sse_ui_theme_persist')) {
    function sse_ui_theme_persist(string $theme): string
    {
        $theme = sse_ui_theme_normalize($theme);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        setcookie('sse_ui_theme', $theme, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['sse_ui_theme'] = $theme;

        return $theme;
    }
}

if (!function_exists('sse_cookie_secure')) {
    function sse_cookie_secure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    }
}

if (!function_exists('sse_ui_mission_id')) {
    /** Identifiant de mission active dans le portail SSE (cookie, 0 = aucune). */
    function sse_ui_mission_id(): int
    {
        return max(0, (int) ($_COOKIE['sse_mission_id'] ?? 0));
    }
}

if (!function_exists('sse_ui_mission_persist')) {
    function sse_ui_mission_persist(int $missionId): int
    {
        $missionId = max(0, $missionId);
        setcookie('sse_mission_id', (string) $missionId, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => sse_cookie_secure(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['sse_mission_id'] = (string) $missionId;

        return $missionId;
    }
}

if (!function_exists('sse_ui_classification_options')) {
    /**
     * Niveaux de diffusion affichés dans la barre de contexte SSE.
     *
     * @return array<string, string>
     */
    function sse_ui_classification_options(): array
    {
        if (class_exists(\App\Repositories\SseCaseRepository::class)) {
            return \App\Repositories\SseCaseRepository::CLASSIFICATION_LABELS;
        }

        return [
            'encadrement' => 'Encadrement',
            'confidentiel' => 'Confidentiel',
            'tres_restreint' => 'Diffusion très restreinte',
            'interne' => 'Diffusion interne',
        ];
    }
}

if (!function_exists('sse_ui_classification_normalize')) {
    function sse_ui_classification_normalize(?string $code): string
    {
        $code = strtolower(trim((string) $code));
        $opts = sse_ui_classification_options();

        return array_key_exists($code, $opts) ? $code : 'confidentiel';
    }
}

if (!function_exists('sse_ui_classification')) {
    function sse_ui_classification(): string
    {
        return sse_ui_classification_normalize($_COOKIE['sse_ui_classification'] ?? null);
    }
}

if (!function_exists('sse_ui_classification_persist')) {
    function sse_ui_classification_persist(string $code): string
    {
        $code = sse_ui_classification_normalize($code);
        setcookie('sse_ui_classification', $code, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => sse_cookie_secure(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['sse_ui_classification'] = $code;

        return $code;
    }
}

if (!function_exists('sse_ui_classification_label')) {
    function sse_ui_classification_label(?string $code = null): string
    {
        $code = sse_ui_classification_normalize($code ?? sse_ui_classification());
        $opts = sse_ui_classification_options();

        return $opts[$code] ?? 'Confidentiel';
    }
}

if (!function_exists('sse_normalize_ref_display')) {
    /**
     * Corrige les références terrain affichées en notation scientifique (ex. SSE-WL-1.11e+09).
     */
    function sse_normalize_ref_display(mixed $value): string
    {
        $s = (string) $value;
        if ($s === '' || !preg_match('/\d+\.\d+[eE][+\-]?\d+/', $s)) {
            return $s;
        }

        return (string) preg_replace_callback(
            '/\d+\.\d+[eE][+\-]?\d+/',
            static function (array $m): string {
                return sprintf('%.0f', (float) $m[0]);
            },
            $s
        );
    }
}

if (!function_exists('mask_email_for_display')) {
    /**
     * Masque la partie locale d’un e-mail (ex. jean.dupont@ex.fr → je***@ex.fr).
     * L’adresse complète reste disponible côté serveur / staff.
     */
    function mask_email_for_display(string $email): string
    {
        $email = trim($email);
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return $email === '' ? '—' : $email;
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $n = strlen($local);
        $keep = min(2, $n);
        $prefix = $keep > 0 ? substr($local, 0, $keep) : '';

        return $prefix . '***@' . $domain;
    }
}

require __DIR__ . '/forum_helpers.php';
