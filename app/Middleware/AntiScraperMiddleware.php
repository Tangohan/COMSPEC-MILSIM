<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Security\FileRateLimiter;

/**
 * Freine les miroirs / suckers (HTTrack, etc.) : User-Agent connus + piège optionnel.
 * Les robots de recherche légitimes (Google, Bing…) ne sont pas bloqués.
 *
 * Limite : un outil peut spoofter le User-Agent — cette couche reste un frein, pas un DRM.
 */
final class AntiScraperMiddleware
{
    /** Chemin honeypot (lien invisible dans le layout) — visite = signal scraper. */
    public const TRAP_PATH = '/offline-archive/site-index';

    private const TRAP_WINDOW_SECONDS = 3600;

    /**
     * Sous-chaînes User-Agent typiques des outils de mirroring / scraping abusif.
     * Comparaison insensible à la casse.
     *
     * @var list<string>
     */
    private const BLOCKED_UA_NEEDLES = [
        'HTTrack',
        'Offline Explorer',
        'OfflineExplorer',
        'SiteSucker',
        'WebCopier',
        'WebStripper',
        'WebZIP',
        'WebReaper',
        'WebWhacker',
        'WebSauger',
        'Teleport Pro',
        'TeleportPro',
        'Xaldon',
        'EmailWolf',
        'WWWOFFLE',
        'GetRight',
        'Go!Zilla',
        'GoZilla',
        'Download Ninja',
        'Mass Downloader',
        'Web Image Collector',
        'WebSucker',
        'WebAuto',
        'pavuk',
        'BlackWidow',
        'SuperBot',
        'SuperHTTP',
        'Harvest',
        'ExtractorPro',
        'FlashGet',
        'NetAnt',
        'NetSpider',
        'PageGrabber',
        'lwp-download',
        'libwww-perl',
        'Wget/',
        'wget/',
    ];

    /**
     * UA à ne jamais traiter comme scraper (SEO / aperçus sociaux).
     *
     * @var list<string>
     */
    private const ALLOWED_UA_NEEDLES = [
        'Googlebot',
        'Google-InspectionTool',
        'bingbot',
        'BingPreview',
        'Slurp',
        'DuckDuckBot',
        'Baiduspider',
        'YandexBot',
        'Applebot',
        'facebookexternalhit',
        'Facebot',
        'Twitterbot',
        'LinkedInBot',
        'Discordbot',
        'Slackbot',
        'WhatsApp',
        'TelegramBot',
    ];

    public function __construct(
        private FileRateLimiter $limiter = new FileRateLimiter()
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $path = $request->path();
        $ua = $request->userAgent();
        $ip = $this->clientIp();

        if ($this->isAllowedBot($ua)) {
            return $next($request);
        }

        if ($path === self::TRAP_PATH || str_starts_with($path, self::TRAP_PATH . '/')) {
            $this->limiter->hit('rl:mirror_trap:' . $ip, self::TRAP_WINDOW_SECONDS);

            return $this->forbidden($request);
        }

        if ($this->limiter->attempts('rl:mirror_trap:' . $ip, self::TRAP_WINDOW_SECONDS) > 0) {
            return $this->forbidden($request);
        }

        if ($this->isBlockedUa($ua)) {
            return $this->forbidden($request);
        }

        return $next($request);
    }

    private function isAllowedBot(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }
        foreach (self::ALLOWED_UA_NEEDLES as $needle) {
            if (stripos($ua, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isBlockedUa(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }
        foreach (self::BLOCKED_UA_NEEDLES as $needle) {
            if (stripos($ua, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function forbidden(Request $request): Response
    {
        if (str_starts_with($request->path(), '/api/')) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Accès refusé.',
            ], 403)->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return Response::view('errors.403', [
            'title' => 'Accès refusé',
        ])->setStatusCode(403)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }

    private function clientIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0';
        if (is_string($ip) && str_contains($ip, ',')) {
            return trim(explode(',', $ip)[0]);
        }

        return is_string($ip) ? $ip : '0';
    }
}
