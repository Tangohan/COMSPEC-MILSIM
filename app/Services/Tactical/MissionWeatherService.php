<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Snapshot météo mission (inspiré ATAK Enhanced Weather) — fichier par tenant/carte.
 */
final class MissionWeatherService
{
    /**
     * @return array<string, mixed>
     */
    public function get(int $tenantId, int $mapId): array
    {
        $defaults = [
            'condition' => '',
            'temperature_c' => null,
            'wind_kph' => null,
            'wind_dir' => null,
            'cloud_pct' => null,
            'fog_pct' => null,
            'rain_pct' => null,
            'humidity_pct' => null,
            'call_sign' => '',
            'updated_at' => '',
        ];
        $path = $this->path($tenantId, $mapId);
        if (!is_file($path)) {
            return $defaults;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $defaults;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $defaults;
        }

        return array_merge($defaults, $data);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function put(int $tenantId, int $mapId, array $payload): array
    {
        $merged = [
            'condition' => trim((string) ($payload['condition'] ?? '')),
            'temperature_c' => isset($payload['temperature_c']) ? (int) $payload['temperature_c'] : null,
            'wind_kph' => isset($payload['wind_kph']) ? (int) $payload['wind_kph'] : null,
            'wind_dir' => isset($payload['wind_dir']) ? (int) $payload['wind_dir'] : null,
            'cloud_pct' => isset($payload['cloud_pct']) ? (int) $payload['cloud_pct'] : null,
            'fog_pct' => isset($payload['fog_pct']) ? (int) $payload['fog_pct'] : null,
            'rain_pct' => isset($payload['rain_pct']) ? (int) $payload['rain_pct'] : null,
            'humidity_pct' => isset($payload['humidity_pct']) ? (int) $payload['humidity_pct'] : null,
            'call_sign' => trim((string) ($payload['call_sign'] ?? '')),
            'updated_at' => gmdate('c'),
        ];
        $dir = dirname($this->path($tenantId, $mapId));
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            $this->path($tenantId, $mapId),
            json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        return $merged;
    }

    private function path(int $tenantId, int $mapId): string
    {
        return base_path('storage/cache/mission-weather/t' . $tenantId . '_m' . $mapId . '.json');
    }
}
