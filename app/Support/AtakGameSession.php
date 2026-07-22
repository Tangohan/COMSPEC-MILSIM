<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Session courte pour clients Arma après client-init.
 * Lie tenant + Steam + empreinte de clé : le serveur fait foi (client hostile).
 */
final class AtakGameSession
{
    public const TTL_SECONDS = 14400; // 4 h

    public const HEADER = 'HTTP_X_COMSPEC_SESSION';

    /**
     * Émet un jeton opaque stocké hors session PHP (fichier).
     *
     * @return array{token: string, expires_in: int}
     */
    public static function issue(int $tenantId, string $steamUid, string $apiKeyPresented): array
    {
        $steam = SteamId::normalize($steamUid);
        if ($tenantId < 1 || $steam === null) {
            return ['token' => '', 'expires_in' => 0];
        }

        $token = bin2hex(random_bytes(24));
        $expiresAt = time() + self::TTL_SECONDS;
        $payload = [
            'tenant_id' => $tenantId,
            'steam_uid' => $steam,
            'key_fp' => self::keyFingerprint($apiKeyPresented),
            'expires_at' => $expiresAt,
            'issued_at' => time(),
        ];
        self::write($token, $payload);

        return ['token' => $token, 'expires_in' => self::TTL_SECONDS];
    }

    /**
     * @return array{tenant_id: int, steam_uid: string, key_fp: string, expires_at: int}|null
     */
    public static function validate(string $token, int $tenantId, string $apiKeyPresented): ?array
    {
        $token = trim($token);
        if ($token === '' || !preg_match('/^[a-f0-9]{32,64}$/i', $token)) {
            return null;
        }
        $data = self::read($token);
        if ($data === null) {
            return null;
        }
        $expiresAt = (int) ($data['expires_at'] ?? 0);
        if ($expiresAt < time()) {
            self::forget($token);

            return null;
        }
        if ((int) ($data['tenant_id'] ?? 0) !== $tenantId) {
            return null;
        }
        $fp = (string) ($data['key_fp'] ?? '');
        $expected = self::keyFingerprint($apiKeyPresented);
        if ($fp === '' || $expected === '' || !hash_equals($fp, $expected)) {
            return null;
        }
        $steam = SteamId::normalize((string) ($data['steam_uid'] ?? ''));
        if ($steam === null) {
            return null;
        }

        return [
            'tenant_id' => $tenantId,
            'steam_uid' => $steam,
            'key_fp' => $fp,
            'expires_at' => $expiresAt,
        ];
    }

    public static function extractPresentedToken(): string
    {
        $h = $_SERVER[self::HEADER] ?? $_SERVER['HTTP_X_ATAK_SESSION'] ?? null;
        if (is_string($h) && trim($h) !== '') {
            return trim($h);
        }

        return '';
    }

    public static function keyFingerprint(string $apiKey): string
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return '';
        }

        return hash('sha256', 'atak-sess-key:' . $apiKey);
    }

    /** @param array<string, mixed> $payload */
    private static function write(string $token, array $payload): void
    {
        $dir = self::dir();
        if ($dir === null) {
            return;
        }
        $path = $dir . '/' . hash('sha256', $token) . '.json';
        try {
            file_put_contents(
                $path,
                json_encode($payload, JSON_THROW_ON_ERROR),
                LOCK_EX
            );
        } catch (\Throwable) {
            // Best-effort.
        }
    }

    /** @return array<string, mixed>|null */
    private static function read(string $token): ?array
    {
        $dir = self::dir();
        if ($dir === null) {
            return null;
        }
        $path = $dir . '/' . hash('sha256', $token) . '.json';
        if (!is_file($path)) {
            return null;
        }
        try {
            $raw = file_get_contents($path);
            if ($raw === false || $raw === '') {
                return null;
            }
            $data = json_decode($raw, true);

            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function forget(string $token): void
    {
        $dir = self::dir();
        if ($dir === null) {
            return;
        }
        $path = $dir . '/' . hash('sha256', $token) . '.json';
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function dir(): ?string
    {
        $root = dirname(__DIR__, 2);
        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'atak_sessions';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        return $dir;
    }
}
