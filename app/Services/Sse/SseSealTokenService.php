<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Jeton signé pour le QR « sceau poste de travail » d’une chemise de dossier.
 * Sans expiration : une chemise papier doit rester scannable longtemps.
 */
final class SseSealTokenService
{
    private const VERSION = 1;

    public function __construct(
        private string $appKey,
    ) {
    }

    public static function fromEnv(): self
    {
        $k = (string) (function_exists('env') ? env('APP_KEY', '') : '');

        return new self($k !== '' ? $k : 'dev-insecure-key');
    }

    public function mint(int $tenantId, int $caseId, string $sealId, string $fingerprint): string
    {
        $payload = json_encode([
            'v' => self::VERSION,
            't' => $tenantId,
            'c' => $caseId,
            's' => $sealId,
            'f' => $fingerprint,
        ], JSON_UNESCAPED_UNICODE);
        $b64 = self::b64urlEncode((string) $payload);
        $sig = self::b64urlEncode(hash_hmac('sha256', $b64, $this->appKey, true));

        return $b64 . '.' . $sig;
    }

    /**
     * @return array{tenant_id:int,case_id:int,seal_id:string,fingerprint:string}|null
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
        if (!is_array($data) || (int) ($data['v'] ?? 0) !== self::VERSION) {
            return null;
        }
        $tenantId = (int) ($data['t'] ?? 0);
        $caseId = (int) ($data['c'] ?? 0);
        $sealId = trim((string) ($data['s'] ?? ''));
        $fp = trim((string) ($data['f'] ?? ''));
        if ($tenantId < 1 || $caseId < 1 || $sealId === '' || $fp === '') {
            return null;
        }

        return [
            'tenant_id' => $tenantId,
            'case_id' => $caseId,
            'seal_id' => $sealId,
            'fingerprint' => $fp,
        ];
    }

    private static function b64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $b64): ?string
    {
        $pad = 4 - (strlen($b64) % 4);
        if ($pad < 4) {
            $b64 .= str_repeat('=', $pad);
        }
        $raw = base64_decode(strtr($b64, '-_', '+/'), true);

        return $raw === false ? null : $raw;
    }
}
