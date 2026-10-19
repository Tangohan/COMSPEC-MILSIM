<?php

declare(strict_types=1);

namespace App\Services\Atak;

/**
 * Vérifie, à l'enregistrement du lien de briefing, que la tablette en jeu pourra lire la présentation.
 *
 * Le Link télécharge les diapositives via docs.google.com/presentation/d/{id}/export/png : un lien
 * « Publier sur le Web » (/d/e/2PACX…/pub) ne passe jamais par là, et une présentation non partagée
 * renvoie la page de connexion Google au lieu d'une image. Dans les deux cas, chaque joueur remontait
 * « google_deck_error — Présentation inaccessible ou non publique. » sans que l'admin le sache.
 */
final class GoogleSlidesAccessCheck
{
    public const OK = 'ok';
    public const PUBLISH_LINK = 'publish_link';
    public const PRIVATE = 'private';
    public const UNKNOWN = 'unknown';

    /** @var (callable(string): array{status:int, type:string})|null */
    private $fetch;

    /** @param (callable(string): array{status:int, type:string})|null $fetch injecté par les tests */
    public function __construct(?callable $fetch = null)
    {
        $this->fetch = $fetch;
    }

    public static function isPublishLink(string $url): bool
    {
        return (bool) preg_match('#^/presentation/d/e/#', (string) (parse_url($url, PHP_URL_PATH) ?? ''));
    }

    public static function deckId(string $url): ?string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

        return preg_match('#^/presentation/d/([a-zA-Z0-9_-]{20,})#', $path, $m) === 1 && $m[1] !== 'e' ? $m[1] : null;
    }

    /** @return self::OK|self::PUBLISH_LINK|self::PRIVATE|self::UNKNOWN */
    public function check(string $url): string
    {
        if (self::isPublishLink($url)) {
            return self::PUBLISH_LINK;
        }
        $id = self::deckId($url);
        if ($id === null) {
            return self::UNKNOWN;
        }
        try {
            $r = ($this->fetch ?? self::httpHead(...))('https://docs.google.com/presentation/d/' . $id . '/export/png?pageid=p');
        } catch (\Throwable) {
            return self::UNKNOWN;
        }
        $status = $r['status'];
        if ($status >= 200 && $status < 300) {
            // Présentation non partagée : Google renvoie sa page de connexion (HTML) au lieu d'une image.
            return str_starts_with(strtolower($r['type']), 'image/') ? self::OK : self::PRIVATE;
        }
        if (in_array($status, [401, 403, 404], true)) {
            return self::PRIVATE;
        }

        return self::UNKNOWN; // réseau du serveur, quota Google… : on ne bloque pas l'admin.
    }

    /** @return array{status:int, type:string} */
    private static function httpHead(string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => 0, 'type' => ''];
        }
        $ch = curl_init($url);
        // GET (l'export Google ne répond pas toujours à HEAD) coupé au premier kilo-octet : seuls le code et le type comptent.
        $read = 0;
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_WRITEFUNCTION => static function ($h, string $chunk) use (&$read): int {
                $read += strlen($chunk);

                return $read > 1024 ? -1 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return ['status' => $status, 'type' => $type];
    }
}
