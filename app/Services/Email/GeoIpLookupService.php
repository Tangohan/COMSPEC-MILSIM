<?php

declare(strict_types=1);

namespace App\Services\Email;

/**
 * Géolocalisation indicative (pays) via API publique — désactivable via config.
 */
final class GeoIpLookupService
{
    /** @var array<string, string> */
    private static array $cache = [];

    public function countryForIp(string $ip): ?string
    {
        $ip = trim($ip);
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }
        if (isset(self::$cache[$ip])) {
            return self::$cache[$ip] !== '' ? self::$cache[$ip] : null;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            self::$cache[$ip] = '';

            return null;
        }
        $ec = email_config();
        if (!filter_var($ec['geoip_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            self::$cache[$ip] = '';

            return null;
        }
        $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode';
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            self::$cache[$ip] = '';

            return null;
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            self::$cache[$ip] = '';

            return null;
        }
        if (($data['status'] ?? '') !== 'success') {
            self::$cache[$ip] = '';

            return null;
        }
        $code = isset($data['countryCode']) ? strtoupper((string) $data['countryCode']) : '';
        self::$cache[$ip] = $code;

        return $code !== '' ? $code : null;
    }
}
