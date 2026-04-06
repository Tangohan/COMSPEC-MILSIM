<?php

declare(strict_types=1);

namespace App\Services\Calendar;

/**
 * Jeton signé (lecture seule) pour abonnement calendrier des événements communautaires.
 */
final class CommunityCalendarFeedTokenService
{
    private const VERSION = 1;

    public function __construct(
        private string $appKey,
    ) {}

    public static function fromEnv(): self
    {
        $k = (string) (function_exists('env') ? env('APP_KEY', '') : '');

        return new self($k !== '' ? $k : 'dev-insecure-key');
    }

    public function mint(int $userId, int $tenantId, int $ttlSeconds = 31536000): string
    {
        $exp = time() + max(3600, $ttlSeconds);
        $payload = json_encode([
            'v' => self::VERSION,
            'u' => $userId,
            't' => $tenantId,
            'exp' => $exp,
        ], JSON_UNESCAPED_UNICODE);
        $b64 = self::b64urlEncode($payload);
        $sig = self::b64urlEncode(hash_hmac('sha256', $b64, $this->appKey, true));

        return $b64 . '.' . $sig;
    }

    /**
     * @return array{user_id: int, tenant_id: int}|null
     */
    public function parse(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || !str_contains($token, '.')) {
            return null;
        }
        [$b64, $sig] = explode('.', $token, 2);
        $expected = self::b64urlEncode(hash_hmac('sha256', $b64, $this->appKey, true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $json = self::b64urlDecode($b64);
        if ($json === null) {
            return null;
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($data)) {
            return null;
        }
        if ((int) ($data['v'] ?? 0) !== self::VERSION) {
            return null;
        }
        $exp = (int) ($data['exp'] ?? 0);
        if ($exp < time()) {
            return null;
        }
        $u = (int) ($data['u'] ?? 0);
        $t = (int) ($data['t'] ?? 0);
        if ($u < 1 || $t < 1) {
            return null;
        }

        return ['user_id' => $u, 'tenant_id' => $t];
    }

    private static function b64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $b64): ?string
    {
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $bin = base64_decode(strtr($b64, '-_', '+/'), true);

        return $bin === false ? null : $bin;
    }
}
