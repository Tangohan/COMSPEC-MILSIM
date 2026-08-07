<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * TOTP (RFC 6238) pour applications d’authentification (Google Authenticator, etc.).
 */
final class TotpService
{
    public const PERIOD_SECONDS = 30;

    public const DIGITS = 6;

    public const SECRET_BYTES = 20;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(self::SECRET_BYTES));
    }

    public function verify(string $secretBase32, string $code, int $window = 1, ?int $atTimestamp = null): bool
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        $secret = $this->base32Decode($secretBase32);
        if ($secret === null || $secret === '') {
            return false;
        }
        $ts = $atTimestamp ?? time();
        $counter = intdiv($ts, self::PERIOD_SECONDS);
        $window = max(0, min(2, $window));
        for ($i = -$window; $i <= $window; $i++) {
            $expected = $this->hotp($secret, $counter + $i);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    public function currentCode(string $secretBase32, ?int $atTimestamp = null): string
    {
        $secret = $this->base32Decode($secretBase32);
        if ($secret === null || $secret === '') {
            return '';
        }
        $ts = $atTimestamp ?? time();
        $counter = intdiv($ts, self::PERIOD_SECONDS);

        return $this->hotp($secret, $counter);
    }

    public function provisioningUri(string $secretBase32, string $accountName, string $issuer): string
    {
        $issuer = trim($issuer) !== '' ? trim($issuer) : 'Athena';
        $accountName = trim($accountName) !== '' ? trim($accountName) : 'compte';
        $label = rawurlencode($issuer . ':' . $accountName);

        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secretBase32)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1'
            . '&digits=' . self::DIGITS
            . '&period=' . self::PERIOD_SECONDS;
    }

    public function formatSecretForDisplay(string $secretBase32): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secretBase32) ?? '');
        $chunks = str_split($clean, 4);

        return implode(' ', $chunks);
    }

    private function hotp(string $secretBinary, int $counter): string
    {
        $binCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binCounter, $secretBinary, true);
        $offset = ord($hash[19]) & 0x0f;
        $truncated = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        );
        $otp = $truncated % (10 ** self::DIGITS);

        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $chunks = str_split($binary, 5);
        $out = '';
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $out .= $alphabet[bindec($chunk)];
        }

        return $out;
    }

    private function base32Decode(string $secret): ?string
    {
        $secret = strtoupper(preg_replace('/[\s=]+/', '', $secret) ?? '');
        if ($secret === '' || preg_match('/[^A-Z2-7]/', $secret)) {
            return null;
        }
        $map = array_flip(str_split(self::BASE32_ALPHABET));
        $binary = '';
        foreach (str_split($secret) as $char) {
            if (!isset($map[$char])) {
                return null;
            }
            $binary .= str_pad(decbin($map[$char]), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        return $bytes;
    }
}
