<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Core\Session;

class AtakTokenService
{
    private const LIFETIME_SECONDS = 3600;

    /** @param string|null $jwtSecretOverride Secret JWT de l'équipe (tenant), ou null pour utiliser env */
    public function generate(?string $jwtSecretOverride = null): string
    {
        $secret = $jwtSecretOverride !== null && $jwtSecretOverride !== ''
            ? $jwtSecretOverride
            : (string) env('JWT_SECRET', 'athena-secret-change-me');
        $payload = [
            'sub' => Session::get('user_id'),
            'tenant_id' => Session::get('tenant_id'),
            'display_name' => Session::get('display_name'),
            'callsign' => Session::get('callsign'),
            'iat' => time(),
            'exp' => time() + self::LIFETIME_SECONDS,
        ];
        $payloadB64 = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $payloadB64, $secret, true);
        $sigB64 = strtr(base64_encode($signature), '+/', '-_');
        return $payloadB64 . '.' . $sigB64;
    }
}
