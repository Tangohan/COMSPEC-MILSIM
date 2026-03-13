<?php

declare(strict_types=1);

/**
 * Configuration du mode maintenance.
 * Peut être surchargé par un fichier storage/maintenance.json (prioritaire sur .env).
 * Format JSON : {"enabled": true, "message": "..."}
 */

$config = config('app.maintenance', []);

$overrideFile = base_path('storage/maintenance.json');
if (is_file($overrideFile)) {
    $json = @file_get_contents($overrideFile);
    if ($json !== false) {
        $data = @json_decode($json, true);
        if (is_array($data)) {
            if (isset($data['enabled'])) {
                $config['enabled'] = (bool) $data['enabled'];
            }
            if (isset($data['message']) && is_string($data['message'])) {
                $config['message'] = $data['message'];
            }
            if (isset($data['allowed_ips']) && is_array($data['allowed_ips'])) {
                $config['allowed_ips'] = $data['allowed_ips'];
            }
        }
    }
}

return $config;
