<?php

declare(strict_types=1);

namespace App\Services\Deployment;

/**
 * Vérifie la signature HMAC-SHA256 d’un package (secret UPDATE_PACKAGE_HMAC_SECRET).
 */
final class PackageSignatureVerifier
{
    public function isEnforced(): bool
    {
        return $this->secret() !== '';
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function verifyManifest(array $manifest, string $payloadChecksum): bool
    {
        $secret = $this->secret();
        if ($secret === '') {
            return true;
        }

        $provided = trim((string) ($manifest['signature'] ?? ''));
        if ($provided === '') {
            return false;
        }

        $expected = $this->sign(
            (string) ($manifest['version'] ?? ''),
            (string) ($manifest['minimum_version'] ?? ''),
            $payloadChecksum
        );

        return hash_equals($expected, strtolower($provided));
    }

    public function sign(string $version, string $minimumVersion, string $payloadChecksum): string
    {
        $payload = $version . '|' . $minimumVersion . '|' . strtolower($payloadChecksum);

        return hash_hmac('sha256', $payload, $this->secret());
    }

    private function secret(): string
    {
        return trim((string) (function_exists('env') ? env('UPDATE_PACKAGE_HMAC_SECRET', '') : ''));
    }
}
