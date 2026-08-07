<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Chiffre / déchiffre le secret TOTP au repos (AES-256-GCM via APP_KEY).
 */
final class TotpSecretCipher
{
    private const PREFIX = 'v1:';

    public function encrypt(string $plaintext): string
    {
        $plaintext = trim($plaintext);
        if ($plaintext === '') {
            throw new \InvalidArgumentException('Secret TOTP vide.');
        }
        $key = $this->keyBytes();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false || $tag === '') {
            throw new \RuntimeException('Impossible de chiffrer le secret TOTP.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $payload): ?string
    {
        $payload = trim($payload);
        if ($payload === '' || !str_starts_with($payload, self::PREFIX)) {
            // Compat : secret déjà en clair (ne devrait pas arriver en prod).
            if ($payload !== '' && preg_match('/^[A-Z2-7]+$/', strtoupper(str_replace(' ', '', $payload)))) {
                return strtoupper(str_replace(' ', '', $payload));
            }

            return null;
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->keyBytes(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false || $plain === '') {
            return null;
        }

        return $plain;
    }

    private function keyBytes(): string
    {
        $material = function_exists('env') ? (string) env('APP_KEY', '') : '';
        if ($material === '') {
            $material = 'athena-totp-dev-key';
        }

        return hash('sha256', $material, true);
    }
}
