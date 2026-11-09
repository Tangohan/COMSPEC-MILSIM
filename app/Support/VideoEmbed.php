<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Vidéo intégrée : seuls YouTube (domaine sans cookies) et Vimeo (mode « do not track »), en https, avec un
 * identifiant au format attendu. Toute autre adresse est refusée ; l'adresse d'intégration est reconstruite à partir
 * de l'identifiant, jamais recopiée.
 */
final class VideoEmbed
{
    private const YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];
    private const VIMEO_HOSTS = ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];

    /** @return array{provider: 'youtube'|'vimeo', id: string}|null */
    public static function parse(mixed $url): ?array
    {
        $s = is_scalar($url) ? trim((string) $url) : '';
        if ($s === '' || strlen($s) > 300 || preg_match('/[\s<>"\'`\\\\]/', $s) === 1) {
            return null;
        }
        $p = parse_url($s);
        if (!is_array($p) || strtolower((string) ($p['scheme'] ?? '')) !== 'https' || isset($p['user']) || isset($p['pass']) || isset($p['port'])) {
            return null;
        }
        $host = strtolower((string) ($p['host'] ?? ''));
        $path = (string) ($p['path'] ?? '');
        parse_str((string) ($p['query'] ?? ''), $q);

        if (in_array($host, self::YOUTUBE_HOSTS, true)) {
            $id = '';
            if ($host === 'youtu.be') {
                $id = ltrim($path, '/');
            } elseif (preg_match('#^/(?:embed|shorts|live|v)/([^/]+)$#', $path, $m) === 1) {
                $id = $m[1];
            } elseif ($path === '/watch' && is_string($q['v'] ?? null)) {
                $id = $q['v'];
            }

            return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? ['provider' => 'youtube', 'id' => $id] : null;
        }
        if (in_array($host, self::VIMEO_HOSTS, true)) {
            if (preg_match('#^/(?:video/)?(\d{6,12})$#', $path, $m) === 1) {
                return ['provider' => 'vimeo', 'id' => $m[1]];
            }

            return null;
        }

        return null;
    }

    /** Adresse de partage normalisée (celle que l'on enregistre), ou « » si la vidéo est refusée. */
    public static function canonical(mixed $url): string
    {
        $v = self::parse($url);
        if ($v === null) {
            return '';
        }

        return $v['provider'] === 'youtube' ? 'https://www.youtube.com/watch?v=' . $v['id'] : 'https://vimeo.com/' . $v['id'];
    }

    /** Adresse du cadre d'intégration, ou « ». */
    public static function embedUrl(mixed $url): string
    {
        $v = self::parse($url);
        if ($v === null) {
            return '';
        }

        return $v['provider'] === 'youtube'
            ? 'https://www.youtube-nocookie.com/embed/' . $v['id'] . '?rel=0'
            : 'https://player.vimeo.com/video/' . $v['id'] . '?dnt=1';
    }
}
