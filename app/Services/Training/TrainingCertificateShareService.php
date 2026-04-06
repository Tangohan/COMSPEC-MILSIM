<?php

declare(strict_types=1);

namespace App\Services\Training;

/**
 * Lien de consultation publique d’une attestation (signature + date limite).
 */
class TrainingCertificateShareService
{
    private const DEFAULT_TTL_SEC = 7776000; // 90 jours

    private function keyMaterial(): string
    {
        $k = function_exists('env') ? (string) env('APP_KEY', '') : '';

        return $k !== '' ? $k : 'training-cert-share';
    }

    /**
     * @return array{token: string, expires_at: int}
     */
    public function mint(int $certificateId, ?int $ttlSeconds = null): array
    {
        $ttl = $ttlSeconds ?? self::DEFAULT_TTL_SEC;
        $ttl = max(3600, min(86400 * 365, $ttl));
        $exp = time() + $ttl;
        $payload = $certificateId . '|' . $exp;
        $token = hash_hmac('sha256', $payload, $this->keyMaterial());

        return ['token' => $token, 'expires_at' => $exp];
    }

    public function verify(int $certificateId, ?string $token, ?int $expiresAt): bool
    {
        if ($token === null || $token === '' || $expiresAt === null || $expiresAt < time()) {
            return false;
        }
        $payload = $certificateId . '|' . $expiresAt;
        $expected = hash_hmac('sha256', $payload, $this->keyMaterial());

        return hash_equals($expected, $token);
    }

    public function buildConsultationUrl(int $certificateId, string $token, int $expiresAt): string
    {
        $base = url('formations/attestation/' . $certificateId);

        return $base . '?t=' . rawurlencode($token) . '&e=' . $expiresAt;
    }
}
