<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Secret d'une communauté chiffré au repos (mot de passe SMTP, clé d'API du fournisseur d'e-mails) :
 * AES-256-GCM, clé dérivée d'APP_KEY. Le texte chiffré commence par « sc1: ».
 */
final class SecretCipher
{
    private const PREFIX = 'sc1:';

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'athena-secret', 16);
        if ($cipher === false || $tag === '') {
            throw new \RuntimeException('Impossible de chiffrer le secret.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /** Texte en clair, ou null si le secret est absent, abîmé ou chiffré avec une autre APP_KEY. */
    public static function decrypt(string $payload): ?string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            return null;
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'athena-secret');

        return $plain === false ? null : $plain;
    }

    private static function key(): string
    {
        $material = function_exists('env') ? (string) env('APP_KEY', '') : '';

        return hash('sha256', 'athena-secret|' . ($material !== '' ? $material : 'athena-dev-key'), true);
    }
}
