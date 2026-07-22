<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Réglages d’affichage camps (inspiré Athena Remastered sendSettings).
 * Stockage fichier par tenant/carte — pas de migration BDD requise pour le MVP.
 */
final class MissionDisplaySettingsService
{
    /**
     * @return array{show_east: bool, show_guer: bool, show_civ: bool, updated_at: string}
     */
    public function get(int $tenantId, int $mapId): array
    {
        $defaults = [
            'show_east' => true,
            'show_guer' => true,
            'show_civ' => true,
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

        return [
            'show_east' => (bool) ($data['show_east'] ?? true),
            'show_guer' => (bool) ($data['show_guer'] ?? true),
            'show_civ' => (bool) ($data['show_civ'] ?? true),
            'updated_at' => (string) ($data['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array{show_east?: bool, show_guer?: bool, show_civ?: bool} $settings
     * @return array{show_east: bool, show_guer: bool, show_civ: bool, updated_at: string}
     */
    public function put(int $tenantId, int $mapId, array $settings): array
    {
        $merged = $this->get($tenantId, $mapId);
        if (array_key_exists('show_east', $settings)) {
            $merged['show_east'] = (bool) $settings['show_east'];
        }
        if (array_key_exists('show_guer', $settings)) {
            $merged['show_guer'] = (bool) $settings['show_guer'];
        }
        if (array_key_exists('show_civ', $settings)) {
            $merged['show_civ'] = (bool) $settings['show_civ'];
        }
        $merged['updated_at'] = gmdate('c');

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
        return base_path('storage/cache/mission-settings/t' . $tenantId . '_m' . $mapId . '.json');
    }
}
