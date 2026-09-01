<?php

declare(strict_types=1);

namespace App\Services\Steam;

use App\Support\SteamId;

/**
 * Liaison d’un compte déjà connecté au portail via Steam (OpenID 2.0).
 * Pas de clé serveur : Steam renvoie l’identité, on la vérifie auprès de Steam.
 */
final class SteamOpenIdService
{
    public const STEAM_LOGIN = 'https://steamcommunity.com/openid/login';
    private const OPENID_NS = 'http://specs.openid.net/auth/2.0';
    private const IDENTIFIER_SELECT = 'http://specs.openid.net/auth/2.0/identifier_select';

    public function callbackUrl(): string
    {
        return url('account/steam/callback');
    }

    public function realm(): string
    {
        $base = rtrim((string) env('APP_URL', ''), '/');
        if ($base === '') {
            return $this->callbackUrl();
        }

        return $base . '/';
    }

    public function authorizationUrl(string $state): string
    {
        $returnTo = $this->callbackUrl() . '?state=' . rawurlencode($state);
        $params = [
            'openid.ns' => self::OPENID_NS,
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => $returnTo,
            'openid.realm' => $this->realm(),
            'openid.identity' => self::IDENTIFIER_SELECT,
            'openid.claimed_id' => self::IDENTIFIER_SELECT,
        ];

        return self::STEAM_LOGIN . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function claimedSteamId(array $query): ?string
    {
        $claimed = trim((string) ($query['openid_claimed_id'] ?? $query['openid.claimed_id'] ?? ''));

        return SteamId::normalize($claimed);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, string>
     */
    public function verificationFields(array $query): array
    {
        $out = [];
        foreach ($query as $key => $value) {
            if (!is_string($key) || $value === null || is_array($value)) {
                continue;
            }
            $name = str_starts_with($key, 'openid_')
                ? 'openid.' . substr($key, 7)
                : $key;
            if (!str_starts_with($name, 'openid.')) {
                continue;
            }
            $out[$name] = (string) $value;
        }
        $out['openid.mode'] = 'check_authentication';

        return $out;
    }

    public function isPositiveAssertion(string $body): bool
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $flags = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $flags[strtolower(trim($k))] = strtolower(trim($v));
        }

        return ($flags['is_valid'] ?? '') === 'true';
    }

    /**
     * @param array<string, mixed> $query
     */
    public function verify(array $query): bool
    {
        $fields = $this->verificationFields($query);
        if (($fields['openid.claimed_id'] ?? '') === '' && ($query['openid.claimed_id'] ?? '') === '') {
            return false;
        }
        $body = $this->postForm(self::STEAM_LOGIN, $fields);
        if ($body === null || $body === '') {
            return false;
        }

        return $this->isPositiveAssertion($body);
    }

    /**
     * @param array<string, string> $fields
     */
    private function postForm(string $url, array $fields): ?string
    {
        $payload = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: text/plain\r\n",
                'content' => $payload,
                'timeout' => 12,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }
        if (!function_exists('curl_init')) {
            return is_string($raw) ? $raw : null;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: text/plain'],
        ]);
        $out = curl_exec($ch);
        curl_close($ch);

        return is_string($out) ? $out : null;
    }
}
