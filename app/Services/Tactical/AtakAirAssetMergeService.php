<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakDataRepository;

/**
 * Fusionne manifeste de vol, occupation moteur et unités déjà sur ATAK
 * pour n’afficher qu’un aéronef par cellule.
 */
final class AtakAirAssetMergeService
{
    public const SOURCE_MANIFEST = 'manifest';
    public const SOURCE_OCCUPANCY = 'occupancy';
    public const SOURCE_UNIT = 'unit';

    public const MERGE_RADIUS_M = 35.0;

    public static function isOccupancyPayload(array $body): bool
    {
        $source = strtolower(trim((string) ($body['source'] ?? $body['origin'] ?? '')));
        if (in_array($source, ['occupancy', 'inferred', 'vehicle', 'auto', 'engine'], true)) {
            return true;
        }
        $flag = $body['inferred'] ?? $body['from_occupancy'] ?? false;

        return $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
    }

    public static function isAirCategory(string $category): bool
    {
        $c = strtoupper(trim($category));

        return in_array($c, [
            AtakMotionMath::CAT_HELICOPTER,
            AtakMotionMath::CAT_FIXED_WING,
            AtakMotionMath::CAT_UAV,
        ], true);
    }

    public static function aircraftTypeFromCategory(string $category): string
    {
        return match (strtoupper(trim($category))) {
            AtakMotionMath::CAT_HELICOPTER => 'helicopter',
            AtakMotionMath::CAT_UAV => 'uav',
            default => 'plane',
        };
    }

    /**
     * @param list<array<string, mixed>> $stored
     * @param list<array<string, mixed>> $units
     * @return list<array<string, mixed>>
     */
    public static function merge(array $stored, array $units): array
    {
        $out = [];
        foreach ($stored as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = self::normalizeStored($row);
        }
        foreach (self::unitsToAirCandidates($units) as $candidate) {
            $out[] = $candidate;
        }

        return array_values(self::dedupe($out));
    }

    /**
     * @param list<array<string, mixed>> $units
     * @return list<array<string, mixed>>
     */
    public static function unitsToAirCandidates(array $units): array
    {
        $clusters = [];
        foreach ($units as $unit) {
            if (!is_array($unit)) {
                continue;
            }
            $status = strtolower(trim((string) ($unit['status'] ?? '')));
            if (in_array($status, ['offline', 'dead'], true)) {
                continue;
            }
            $extra = AtakDataRepository::decodeExtra($unit['extra'] ?? null);
            if (AtakDataRepository::isProxyContactExtra($extra) && empty($extra['ally_ai']) && empty($extra['is_ai'])) {
                // Téléphone / balise : pas un aéronef. Les IA alliées à bord passent.
                if (!empty($extra['phone_geoloc']) || ($extra['source'] ?? '') === 'phone') {
                    continue;
                }
            }
            $cat = AtakMotionMath::classifyCategory($extra);
            $vehName = trim((string) ($extra['vehicle_name'] ?? $extra['vehicle_label'] ?? ''));
            if (!self::isAirCategory($cat)) {
                continue;
            }
            $x = self::num($unit['pos_x'] ?? $extra['pos_x'] ?? null);
            $y = self::num($unit['pos_y'] ?? $extra['pos_y'] ?? null);
            if ($x === null || $y === null) {
                continue;
            }
            $vehClass = strtolower(trim((string) ($extra['vehicle'] ?? '')));
            $group = trim((string) ($extra['group_name'] ?? $extra['group'] ?? $unit['group_name'] ?? ''));
            $cellX = (int) round($x / 15);
            $cellY = (int) round($y / 15);
            $key = ($vehClass !== '' ? $vehClass : strtolower($cat)) . ':' . $cellX . ':' . $cellY;
            if (!isset($clusters[$key])) {
                $callsign = $group !== '' ? $group : trim((string) ($unit['call_sign'] ?? $unit['callsign'] ?? ''));
                $model = $vehName !== '' ? $vehName : trim((string) ($extra['vehicle'] ?? ''));
                $clusters[$key] = [
                    'callsign' => $callsign !== '' ? $callsign : $model,
                    'model' => $model !== '' ? $model : $callsign,
                    'aircraft_type' => self::aircraftTypeFromCategory($cat),
                    'freq' => null,
                    'laser' => null,
                    'auth' => null,
                    'pos_x' => $x,
                    'pos_y' => $y,
                    'alt' => self::num($extra['asl_z'] ?? $extra['pos_z'] ?? $unit['pos_z'] ?? null),
                    'heading' => self::num($unit['heading'] ?? $extra['heading_object'] ?? $extra['heading'] ?? null),
                    'side' => strtoupper(trim((string) ($extra['side'] ?? $unit['side'] ?? 'WEST'))) ?: 'WEST',
                    'status' => 'IN-FLIGHT',
                    'pilot_status' => null,
                    'aircraft_count' => 1,
                    'updated_at' => $unit['updated_at'] ?? null,
                    'source' => self::SOURCE_UNIT,
                    'vehicle_id' => '',
                    'occupants' => [],
                    'crew' => [],
                    'crew_count' => 0,
                ];
            }
            $clusters[$key]['aircraft_count'] = 1;
            if ($vehName !== '' && trim((string) ($clusters[$key]['model'] ?? '')) === '') {
                $clusters[$key]['model'] = $vehName;
            }
            if ($group !== '' && trim((string) ($clusters[$key]['callsign'] ?? '')) === '') {
                $clusters[$key]['callsign'] = $group;
            }
            $fromUnit = self::occupantsOf($extra);
            $inVeh = !empty($extra['in_vehicle']) || $fromUnit !== [];
            if ($fromUnit === [] && $inVeh) {
                $name = trim((string) ($unit['call_sign'] ?? $unit['callsign'] ?? ''));
                if ($name !== '') {
                    $fromUnit = [['name' => $name, 'seat' => 'cargo']];
                }
            }
            if ($fromUnit !== []) {
                $mergedOcc = self::mergeOccupants(
                    is_array($clusters[$key]['occupants'] ?? null) ? $clusters[$key]['occupants'] : [],
                    $fromUnit
                );
                $clusters[$key]['occupants'] = $mergedOcc;
                $clusters[$key]['crew'] = $mergedOcc;
                $clusters[$key]['crew_count'] = count($mergedOcc);
            }
        }

        $out = [];
        foreach ($clusters as $row) {
            if ((int) ($row['aircraft_count'] ?? 0) < 1) {
                $row['aircraft_count'] = 1;
            }
            $cs = trim((string) ($row['callsign'] ?? ''));
            if ($cs === '') {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $assets
     * @return list<array<string, mixed>>
     */
    public static function dedupe(array $assets): array
    {
        $out = [];
        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            $mergedInto = false;
            foreach ($out as $i => $existing) {
                if (!self::sameAirframe($existing, $asset)) {
                    continue;
                }
                $out[$i] = self::preferRicher($existing, $asset);
                $mergedInto = true;
                break;
            }
            if (!$mergedInto) {
                $out[] = $asset;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    public static function sameAirframe(array $a, array $b): bool
    {
        $idA = strtolower(trim((string) ($a['vehicle_id'] ?? '')));
        $idB = strtolower(trim((string) ($b['vehicle_id'] ?? '')));
        if ($idA !== '' && $idB !== '') {
            return $idA === $idB;
        }

        $csA = self::normCallsign((string) ($a['callsign'] ?? $a['call_sign'] ?? ''));
        $csB = self::normCallsign((string) ($b['callsign'] ?? $b['call_sign'] ?? ''));
        if ($csA !== '' && $csA === $csB) {
            return true;
        }

        $xA = self::num($a['pos_x'] ?? null);
        $yA = self::num($a['pos_y'] ?? null);
        $xB = self::num($b['pos_x'] ?? null);
        $yB = self::num($b['pos_y'] ?? null);
        if ($xA === null || $yA === null || $xB === null || $yB === null) {
            return false;
        }
        $dx = $xA - $xB;
        $dy = $yA - $yB;
        if (sqrt(($dx * $dx) + ($dy * $dy)) > self::MERGE_RADIUS_M) {
            return false;
        }

        $typeA = self::normAirType((string) ($a['aircraft_type'] ?? ''));
        $typeB = self::normAirType((string) ($b['aircraft_type'] ?? ''));
        if ($typeA !== '' && $typeB !== '' && $typeA !== $typeB) {
            return false;
        }

        $modelA = self::normCallsign((string) ($a['model'] ?? ''));
        $modelB = self::normCallsign((string) ($b['model'] ?? ''));
        if ($modelA !== '' && $modelB !== '' && $modelA === $modelB) {
            return true;
        }

        return $idA === '' || $idB === '';
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    public static function preferRicher(array $a, array $b): array
    {
        $rankA = self::sourceRank((string) ($a['source'] ?? ''));
        $rankB = self::sourceRank((string) ($b['source'] ?? ''));
        $primary = $rankA >= $rankB ? $a : $b;
        $secondary = $rankA >= $rankB ? $b : $a;
        $out = $primary;
        foreach (['model', 'aircraft_type', 'freq', 'laser', 'auth', 'pilot', 'pilot_status', 'side'] as $field) {
            $cur = trim((string) ($out[$field] ?? ''));
            $add = trim((string) ($secondary[$field] ?? ''));
            if ($cur === '' && $add !== '') {
                $out[$field] = $secondary[$field];
            }
        }
        $countP = (int) ($primary['aircraft_count'] ?? 1);
        $countS = (int) ($secondary['aircraft_count'] ?? 1);
        $out['aircraft_count'] = max($countP, $countS, 1);
        if (self::num($out['pos_x'] ?? null) === null && self::num($secondary['pos_x'] ?? null) !== null) {
            $out['pos_x'] = $secondary['pos_x'];
            $out['pos_y'] = $secondary['pos_y'];
        }
        if (self::sourceRank((string) ($secondary['source'] ?? '')) === 2
            && self::num($secondary['pos_x'] ?? null) !== null
        ) {
            // L’occupation moteur a la pose la plus fraîche.
            $out['pos_x'] = $secondary['pos_x'];
            $out['pos_y'] = $secondary['pos_y'];
            if (self::num($secondary['alt'] ?? null) !== null) {
                $out['alt'] = $secondary['alt'];
            }
            if (self::num($secondary['heading'] ?? null) !== null) {
                $out['heading'] = $secondary['heading'];
            }
            $vid = trim((string) ($secondary['vehicle_id'] ?? ''));
            if ($vid !== '') {
                $out['vehicle_id'] = $vid;
            }
        }
        $mergedOcc = self::mergeOccupants(self::occupantsOf($primary), self::occupantsOf($secondary));
        if ($mergedOcc !== []) {
            $out['occupants'] = $mergedOcc;
            $out['crew'] = $mergedOcc;
            $out['crew_count'] = count($mergedOcc);
        }
        if (self::sourceRank((string) ($primary['source'] ?? '')) >= 2) {
            $out['source'] = $primary['source'];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    private static function occupantsOf(array $row): array
    {
        $raw = $row['occupants'] ?? $row['crew'] ?? $row['passengers_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? $item['callsign'] ?? $item['call_sign'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $a
     * @param list<array<string, mixed>> $b
     * @return list<array<string, mixed>>
     */
    private static function mergeOccupants(array $a, array $b): array
    {
        $seen = [];
        $out = [];
        foreach (array_merge($a, $b) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = mb_strtolower(trim((string) ($item['name'] ?? $item['callsign'] ?? $item['call_sign'] ?? '')));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeStored(array $row): array
    {
        $source = strtolower(trim((string) ($row['source'] ?? '')));
        if ($source === '') {
            $hasRich = trim((string) ($row['auth'] ?? $row['freq'] ?? '')) !== ''
                || (trim((string) ($row['laser'] ?? '')) !== '' && trim((string) ($row['laser'] ?? '')) !== '1688');
            $source = $hasRich ? self::SOURCE_MANIFEST : self::SOURCE_OCCUPANCY;
        }
        $row['source'] = $source;
        $row['callsign'] = trim((string) ($row['callsign'] ?? $row['call_sign'] ?? ''));
        $row['vehicle_id'] = trim((string) ($row['vehicle_id'] ?? ''));

        return $row;
    }

    private static function sourceRank(string $source): int
    {
        return match (strtolower(trim($source))) {
            self::SOURCE_MANIFEST => 3,
            self::SOURCE_OCCUPANCY => 2,
            self::SOURCE_UNIT => 1,
            default => 0,
        };
    }

    private static function normCallsign(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return $s;
    }

    private static function normAirType(string $raw): string
    {
        $s = strtolower(trim($raw));
        if (in_array($s, ['heli', 'helicopter', 'helico'], true)) {
            return 'helicopter';
        }
        if (in_array($s, ['uav', 'drone'], true)) {
            return 'uav';
        }
        if (in_array($s, ['plane', 'fixed_wing', 'fixed-wing', 'jet'], true)) {
            return 'plane';
        }

        return $s;
    }

    private static function num(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }

        return null;
    }
}
