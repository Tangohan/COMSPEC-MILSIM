<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Métadonnées de point de mission encodées dans le payload d’un ordre MOVE.
 * Format : texte libre + suffixe @WP:pos_x|pos_y|GRID:…|ETA:…|DIST:…|SPD:…|LBL:…
 */
final class AtakOrderWaypoint
{
    private const META_PREFIX = '@WP:';

    /**
     * @return array{text: string, pos_x: float, pos_y: float, grid_reference: string, eta_min: ?int, distance_m: ?int, speed_kph: ?float, label: string}|null
     */
    public static function parse(string $payload): ?array
    {
        $idx = strpos($payload, self::META_PREFIX);
        if ($idx === false) {
            return null;
        }

        $text = trim(substr($payload, 0, $idx));
        $meta = substr($payload, $idx + strlen(self::META_PREFIX));
        $parts = explode('|', $meta);
        if (count($parts) < 2) {
            return null;
        }

        $posX = (float) $parts[0];
        $posY = (float) $parts[1];
        if ($posX === 0.0 && $posY === 0.0) {
            return null;
        }

        $result = [
            'text' => $text,
            'pos_x' => $posX,
            'pos_y' => $posY,
            'grid_reference' => '',
            'eta_min' => null,
            'distance_m' => null,
            'speed_kph' => null,
            'label' => '',
        ];

        for ($i = 2, $n = count($parts); $i < $n; $i++) {
            $p = $parts[$i];
            if (str_starts_with($p, 'GRID:')) {
                $result['grid_reference'] = substr($p, 5);
            } elseif (str_starts_with($p, 'ETA:')) {
                $result['eta_min'] = (int) substr($p, 4);
            } elseif (str_starts_with($p, 'DIST:')) {
                $result['distance_m'] = (int) substr($p, 5);
            } elseif (str_starts_with($p, 'SPD:')) {
                $result['speed_kph'] = (float) substr($p, 4);
            } elseif (str_starts_with($p, 'LBL:')) {
                $result['label'] = substr($p, 4);
            }
        }

        return $result;
    }

    /**
     * @param array{grid_reference?: string, eta_min?: int|null, distance_m?: int|null, speed_kph?: float|null, label?: string} $opts
     */
    public static function build(string $text, float $posX, float $posY, array $opts = []): string
    {
        $text = trim($text);
        $grid = trim((string) ($opts['grid_reference'] ?? ''));
        if ($grid === '') {
            $grid = (string) round($posX) . ' / ' . (string) round($posY);
        }

        $meta = sprintf('%s%.2f|%.2f|GRID:%s', self::META_PREFIX, $posX, $posY, $grid);

        if (isset($opts['eta_min']) && $opts['eta_min'] !== null) {
            $meta .= '|ETA:' . (int) $opts['eta_min'];
        }
        if (isset($opts['distance_m']) && $opts['distance_m'] !== null) {
            $meta .= '|DIST:' . (int) $opts['distance_m'];
        }
        if (isset($opts['speed_kph']) && $opts['speed_kph'] !== null) {
            $meta .= '|SPD:' . (float) $opts['speed_kph'];
        }

        $label = trim((string) ($opts['label'] ?? ''));
        if ($label !== '') {
            $meta .= '|LBL:' . $label;
        }

        return $text !== '' ? ($text . "\n" . $meta) : $meta;
    }

    public static function displayPayload(string $payload): string
    {
        $wp = self::parse($payload);
        if ($wp === null) {
            return $payload;
        }

        if ($wp['text'] !== '') {
            return $wp['text'];
        }
        if ($wp['label'] !== '') {
            return $wp['label'];
        }

        return 'Point de mission';
    }
}
