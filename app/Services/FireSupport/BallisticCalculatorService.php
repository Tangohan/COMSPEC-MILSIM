<?php

declare(strict_types=1);

namespace App\Services\FireSupport;

use App\Repositories\FireTableRepository;

class BallisticCalculatorService
{
    public function __construct(
        private FireTableRepository $fireTableRepository
    ) {
    }

    public function calculateDistance(float $x1, float $y1, float $x2, float $y2): float
    {
        return (float) sqrt((($x2 - $x1) ** 2) + (($y2 - $y1) ** 2));
    }

    public function calculateAzimuth(float $x1, float $y1, float $x2, float $y2): float
    {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $deg = rad2deg(atan2($dx, $dy));
        if ($deg < 0) {
            $deg += 360.0;
        }
        return (float) $deg;
    }

    public function convertDegreesToMils(float $deg): float
    {
        return $deg * (6400.0 / 360.0);
    }

    /**
     * Resolve firing solution from ballistic table (interpolation, optional deltaZ).
     * Returns array with keys: distance, azimuth_deg, azimuth_mils, elevation_mils, charge, tof
     */
    public function resolveFiringSolution(
        string $weaponSystem,
        string $ammoType,
        float $distance,
        float $deltaZ = 0.0
    ): array {
        $table = $this->fireTableRepository->getTable($weaponSystem, $ammoType);
        if ($table === null || $table === []) {
            return [
                'distance' => $distance,
                'azimuth_deg' => 0.0,
                'azimuth_mils' => 0.0,
                'elevation_mils' => 0,
                'charge' => 0,
                'tof' => 0.0,
                'error' => 'No fire table for ' . $weaponSystem . '/' . $ammoType,
            ];
        }

        $ranges = array_column($table, 'range');
        $elevations = array_column($table, 'elevation_mils');
        $charges = array_column($table, 'charge');
        $tofs = array_column($table, 'tof');

        if ($ranges === [] || $elevations === []) {
            return [
                'distance' => $distance,
                'azimuth_deg' => 0.0,
                'azimuth_mils' => 0.0,
                'elevation_mils' => 0,
                'charge' => 0,
                'tof' => 0.0,
                'error' => 'Invalid table format',
            ];
        }

        $dist = (int) round($distance);
        $low = null;
        $high = null;
        $lowIdx = null;
        $highIdx = null;
        foreach ($ranges as $i => $r) {
            $rr = is_numeric($r) ? (float) $r : (int) $r;
            if ($rr <= $dist) {
                $low = $rr;
                $lowIdx = $i;
            }
            if ($rr >= $dist && $high === null) {
                $high = $rr;
                $highIdx = $i;
                break;
            }
        }

        if ($lowIdx === null) {
            $lowIdx = 0;
            $low = $ranges[0];
        }
        if ($highIdx === null) {
            $highIdx = $lowIdx;
            $high = $low;
        }

        $elevLow = isset($elevations[$lowIdx]) ? (float) $elevations[$lowIdx] : 0.0;
        $elevHigh = isset($elevations[$highIdx]) ? (float) $elevations[$highIdx] : $elevLow;
        $chargeLow = isset($charges[$lowIdx]) ? (int) $charges[$lowIdx] : 0;
        $chargeHigh = isset($charges[$highIdx]) ? (int) $charges[$highIdx] : $chargeLow;
        $tofLow = isset($tofs[$lowIdx]) ? (float) $tofs[$lowIdx] : 0.0;
        $tofHigh = isset($tofs[$highIdx]) ? (float) $tofs[$highIdx] : $tofLow;

        $rangeSpan = $high - $low;
        $ratio = $rangeSpan > 0 ? ($distance - $low) / $rangeSpan : 1.0;
        $ratio = max(0.0, min(1.0, $ratio));

        $elevationMils = $elevLow + (($elevHigh - $elevLow) * $ratio);
        $charge = $ratio >= 0.5 ? $chargeHigh : $chargeLow;
        $tof = $tofLow + (($tofHigh - $tofLow) * $ratio);

        if ($deltaZ != 0.0) {
            $elevationMils = $elevationMils + ($deltaZ * 0.1);
        }

        return [
            'distance' => $distance,
            'azimuth_deg' => 0.0,
            'azimuth_mils' => 0.0,
            'elevation_mils' => (int) round($elevationMils),
            'charge' => $charge,
            'tof' => round($tof, 1),
        ];
    }
}
