<?php

declare(strict_types=1);

namespace App\Services\I18n;

use App\Core\Session;
use App\Repositories\UserProfileRepository;

/**
 * Résolution et persistance de la locale UI (fr|en).
 *
 * Ordre : cookie explicite → session → profil connecté → Accept-Language → APP_LOCALE.
 */
final class LocaleService
{
    public const COOKIE = 'athena_locale';
    public const SESSION_KEY = 'locale';
    public const SUPPORTED = ['fr', 'en'];

    public function __construct(
        private readonly Translator $translator = new Translator(),
        private readonly ?UserProfileRepository $profiles = null,
    ) {
    }

    public function translator(): Translator
    {
        return $this->translator;
    }

    public static function normalize(string $locale): string
    {
        $locale = strtolower(trim($locale));
        if ($locale === 'fr-fr' || str_starts_with($locale, 'fr')) {
            return 'fr';
        }
        if ($locale === 'en-us' || $locale === 'en-gb' || str_starts_with($locale, 'en')) {
            return 'en';
        }

        return in_array($locale, self::SUPPORTED, true) ? $locale : 'fr';
    }

    public static function isSupported(string $locale): bool
    {
        return in_array(self::normalize($locale), self::SUPPORTED, true);
    }

    public function current(): string
    {
        return $this->translator->getLocale();
    }

    public function htmlLang(): string
    {
        return $this->current() === 'en' ? 'en' : 'fr';
    }

    public function resolve(): string
    {
        $fromCookie = $this->readCookie();
        if ($fromCookie !== null) {
            return $fromCookie;
        }

        $fromSession = Session::get(self::SESSION_KEY);
        if (is_string($fromSession) && self::isSupported($fromSession)) {
            return self::normalize($fromSession);
        }

        $uid = (int) (Session::get('user_id') ?? 0);
        if ($uid > 0) {
            $fromProfile = $this->profileLanguage($uid);
            if ($fromProfile !== null) {
                return $fromProfile;
            }
        }

        $fromBrowser = $this->fromAcceptLanguage();
        if ($fromBrowser !== null) {
            return $fromBrowser;
        }

        return self::normalize((string) config('app.locale', 'fr'));
    }

    public function apply(string $locale): void
    {
        $locale = self::normalize($locale);
        $this->translator->setLocale($locale);
        Session::set(self::SESSION_KEY, $locale);
        $GLOBALS['__app_locale'] = $locale;
        $GLOBALS['__app_translator'] = $this->translator;
    }

    /**
     * Persiste le choix (cookie + session + profil si connecté).
     */
    public function setUserLocale(string $locale, bool $persistProfile = true): void
    {
        $locale = self::normalize($locale);
        $this->apply($locale);
        $this->writeCookie($locale);

        if (!$persistProfile) {
            return;
        }

        $uid = (int) (Session::get('user_id') ?? 0);
        if ($uid < 1) {
            return;
        }

        try {
            $repo = $this->profiles ?? new UserProfileRepository();
            $repo->ensureRow($uid);
            $repo->upsert($uid, ['language' => $locale]);
        } catch (\Throwable) {
            // Ne bloque pas le changement de langue UI.
        }
    }

    public function boot(): void
    {
        $this->apply($this->resolve());
    }

    private function readCookie(): ?string
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $norm = self::normalize($raw);

        return self::isSupported($norm) ? $norm : null;
    }

    private function writeCookie(string $locale): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (bool) (config('auth.session_secure_cookie', false));

        setcookie(self::COOKIE, $locale, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $locale;
    }

    private function profileLanguage(int $userId): ?string
    {
        try {
            $repo = $this->profiles ?? new UserProfileRepository();
            $row = $repo->getByUserId($userId);
            $lang = trim((string) ($row['language'] ?? ''));
            if ($lang === '' || !self::isSupported($lang)) {
                return null;
            }

            return self::normalize($lang);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fromAcceptLanguage(): ?string
    {
        $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($header === '') {
            return null;
        }

        $parts = preg_split('/\s*,\s*/', $header) ?: [];
        foreach ($parts as $part) {
            $tag = strtolower(trim(explode(';', $part)[0] ?? ''));
            if ($tag === '') {
                continue;
            }
            if (str_starts_with($tag, 'fr')) {
                return 'fr';
            }
            if (str_starts_with($tag, 'en')) {
                return 'en';
            }
        }

        return null;
    }
}
