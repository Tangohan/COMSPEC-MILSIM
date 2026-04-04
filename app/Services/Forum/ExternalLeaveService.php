<?php

declare(strict_types=1);

namespace App\Services\Forum;

/**
 * Liens de sortie signés (HMAC) vers la page interstitielle /leave.
 */
final class ExternalLeaveService
{
    private const TTL_SECONDS = 600;

    public function signingKey(): string
    {
        $key = (string) (env('APP_KEY') ?: '');
        if ($key !== '') {
            return $key;
        }
        $base = (string) config('app.url', '');
        if ($base === '') {
            $base = 'comspec-forum-leave';
        }

        return hash('sha256', $base . '|external_leave', true);
    }

    public function buildSignedLeaveUrl(string $targetUrl): ?string
    {
        $clean = $this->sanitizeHttpUrl($targetUrl);
        if ($clean === null) {
            return null;
        }
        $exp = time() + self::TTL_SECONDS;
        $payload = $exp . '|' . $clean;
        $sig = hash_hmac('sha256', $payload, $this->signingKey());

        return url('leave') . '?' . http_build_query([
            'u' => $this->base64UrlEncode($clean),
            'exp' => (string) $exp,
            'sig' => $sig,
        ]);
    }

    /**
     * @return array{url: string, exp: int}|null
     */
    public function verifySignedRequest(string $uB64, string $expRaw, string $sig): ?array
    {
        $url = $this->base64UrlDecode($uB64);
        if ($url === null || $url === '') {
            return null;
        }
        $clean = $this->sanitizeHttpUrl($url);
        if ($clean === null) {
            return null;
        }
        $exp = (int) $expRaw;
        if ($exp < time()) {
            return null;
        }
        $payload = $exp . '|' . $clean;
        $expected = hash_hmac('sha256', $payload, $this->signingKey());
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        return ['url' => $clean, 'exp' => $exp];
    }

    public function sanitizeHttpUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    /**
     * @param list<string> $extraInternalHosts
     */
    public function isInternalUrl(string $url, array $extraInternalHosts = []): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return true;
        }
        $host = strtolower((string) $parts['host']);
        $appHost = $this->appHost();
        if ($appHost !== '' && $host === $appHost) {
            return true;
        }
        foreach ($extraInternalHosts as $h) {
            $h = strtolower(trim((string) $h));
            if ($h !== '' && $host === $h) {
                return true;
            }
        }

        return false;
    }

    private function appHost(): string
    {
        $base = (string) config('app.url', '');
        if ($base === '') {
            return '';
        }
        $h = parse_url($base, PHP_URL_HOST);

        return is_string($h) ? strtolower($h) : '';
    }

    private function base64UrlEncode(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $s): ?string
    {
        $b64 = strtr($s, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($b64, true);

        return $raw === false ? null : $raw;
    }
}
