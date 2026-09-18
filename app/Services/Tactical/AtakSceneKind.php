<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Types de volumes du théâtre et niveaux de détail géographique.
 */
final class AtakSceneKind
{
    public const BUILDING = 'building';
    public const FOREST = 'forest';
    public const WALL = 'wall';
    public const FENCE = 'fence';
    public const POWER = 'power';
    public const BRIDGE = 'bridge';
    public const ROCK = 'rock';
    public const PYLON = 'pylon';

    public const LOD0 = '0';
    public const LOD1 = '1';
    public const LOD2 = '2';
    public const LOD3 = '3';

    public const QUALITY_COMPLETE = 'complete';
    public const QUALITY_APPROX = 'approx';
    public const QUALITY_POSITION = 'position';
    public const QUALITY_SUSPECT = 'suspect';

    /** @return list<string> */
    public static function obstacles(): array
    {
        return [self::WALL, self::FENCE, self::POWER, self::BRIDGE, self::ROCK, self::PYLON];
    }

    public static function normalize(mixed $raw): string
    {
        $kind = strtolower(trim((string) $raw));
        if ($kind === 'buildings' || $kind === 'house' || $kind === 'houses') {
            return self::BUILDING;
        }
        if ($kind === 'forests' || $kind === 'tree' || $kind === 'trees') {
            return self::FOREST;
        }
        if ($kind === 'walls' || $kind === 'mur') {
            return self::WALL;
        }
        if ($kind === 'fences' || $kind === 'cloture' || $kind === 'clôture') {
            return self::FENCE;
        }
        if ($kind === 'powerline' || $kind === 'power_lines' || $kind === 'wires') {
            return self::POWER;
        }
        if ($kind === 'bridges') {
            return self::BRIDGE;
        }
        if ($kind === 'rocks' || $kind === 'boulder') {
            return self::ROCK;
        }
        if ($kind === 'pylons' || $kind === 'transmitter' || $kind === 'stack') {
            return self::PYLON;
        }
        if (in_array($kind, array_merge([self::BUILDING, self::FOREST], self::obstacles()), true)) {
            return $kind;
        }

        return self::BUILDING;
    }

    public static function isObstacle(string $kind): bool
    {
        return in_array(self::normalize($kind), self::obstacles(), true);
    }

    public static function isLinear(string $kind): bool
    {
        $kind = self::normalize($kind);

        return $kind === self::WALL || $kind === self::FENCE || $kind === self::POWER || $kind === self::BRIDGE;
    }

    public static function label(string $kind): string
    {
        return match (self::normalize($kind)) {
            self::FOREST => 'Couvert',
            self::WALL => 'Mur',
            self::FENCE => 'Clôture',
            self::POWER => 'Ligne électrique',
            self::BRIDGE => 'Pont',
            self::ROCK => 'Rocher',
            self::PYLON => 'Pylône',
            default => 'Bâtiment',
        };
    }

    public static function losCause(string $kind): string
    {
        return match (self::normalize($kind)) {
            self::FOREST => 'Masqué par un couvert',
            self::WALL => 'Masqué par un mur',
            self::FENCE => 'Masqué par une clôture',
            self::POWER => 'Masqué par une ligne électrique',
            self::BRIDGE => 'Masqué par un pont',
            self::ROCK => 'Masqué par un rocher',
            self::PYLON => 'Masqué par un pylône',
            default => 'Masqué par un bâtiment',
        };
    }

    public static function normalizeLod(string $lod): string
    {
        $lod = strtolower(trim($lod));
        if ($lod === '0' || $lod === 'strategic' || $lod === 'far' || $lod === 'lod0') {
            return self::LOD0;
        }
        if ($lod === '1' || $lod === 'operational' || $lod === 'mid' || $lod === 'lod1') {
            return self::LOD1;
        }
        if ($lod === '3' || $lod === 'proximity' || $lod === 'lod3') {
            return self::LOD3;
        }

        return self::LOD2;
    }

    /**
     * @param array<string, mixed> $object
     */
    public static function quality(array $object, bool $clipped): string
    {
        if ($clipped) {
            return self::QUALITY_SUSPECT;
        }
        $model = trim((string) ($object['model'] ?? $object['model_class'] ?? ''));
        $w = (float) ($object['width'] ?? 0);
        $d = (float) ($object['depth'] ?? 0);
        $h = (float) ($object['height'] ?? 0);
        if ($w < 2.1 && $d < 2.1 && $h < 2.1) {
            return self::QUALITY_POSITION;
        }
        if ($model === '' || $model === 'forest') {
            return self::QUALITY_APPROX;
        }

        return self::QUALITY_COMPLETE;
    }

    public static function floors(float $heightM): int
    {
        return max(1, (int) round(max(2.0, $heightM) / 3.0));
    }

    /**
     * @return list<array{value:int,label:string}>
     */
    public static function floorLabels(int $floors): array
    {
        $floors = max(1, min(20, $floors));
        $out = [['value' => 0, 'label' => 'RDC']];
        for ($i = 1; $i < $floors; $i++) {
            $out[] = ['value' => $i, 'label' => 'N+' . $i];
        }
        $out[] = ['value' => $floors, 'label' => 'Toit'];

        return $out;
    }

    public static function humanName(string $model, string $kind, string $id = ''): string
    {
        $kind = self::normalize($kind);
        if ($kind !== self::BUILDING) {
            return self::label($kind);
        }
        $raw = preg_replace('/^(Land_|CUP_|CUPLand_)/i', '', $model) ?? $model;
        $n = 0;
        if (preg_match('/(\d{1,3})/', $raw, $m)) {
            $n = (int) $m[1];
        }
        $pad = $n > 0 ? str_pad((string) $n, 2, '0', STR_PAD_LEFT) : '';
        $lower = strtolower($raw);
        if (str_contains($lower, 'church') || str_contains($lower, 'chapel')) {
            return $pad !== '' ? 'Église ' . $pad : 'Église';
        }
        if (str_contains($lower, 'tower') || str_contains($lower, 'lighthouse')) {
            return $pad !== '' ? 'Tour ' . $pad : 'Tour';
        }
        if (str_contains($lower, 'warehouse') || str_contains($lower, 'shed') || str_contains($lower, 'hangar') || str_contains($lower, 'factory')) {
            return $pad !== '' ? 'Hangar ' . $pad : 'Hangar';
        }
        if (str_contains($lower, 'house') || str_contains($lower, 'home') || str_contains($lower, 'offices') || str_contains($lower, 'shop')) {
            return $pad !== '' ? 'Maison ' . $pad : 'Maison';
        }
        if ($pad !== '') {
            return 'Construction ' . $pad;
        }
        $tail = preg_replace('/[^a-zA-Z0-9]+/', '', substr($id, -3)) ?? '';

        return $tail !== '' ? 'Construction ' . $tail : 'Construction';
    }
}
