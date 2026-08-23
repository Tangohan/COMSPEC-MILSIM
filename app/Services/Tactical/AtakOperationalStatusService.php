<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Sépare les grandeurs moteur (Arma) des inférences Athena, et range l’état opérationnel.
 */
final class AtakOperationalStatusService
{
    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $payload motion public
     * @return array<string, mixed>
     */
    public static function decorate(array $row, array $payload): array
    {
        $extra = self::extra($row);
        $motion = is_array($payload['motion'] ?? null) ? $payload['motion'] : [];
        $nav = is_array($row['navigation'] ?? $row['assignment'] ?? null)
            ? ($row['navigation'] ?? $row['assignment'])
            : null;

        $speedMs = self::num($payload['speed'] ?? $extra['speed'] ?? null);
        $heading = self::num($payload['heading_object'] ?? $row['heading'] ?? $extra['heading_object'] ?? null);
        $moveH = self::num($payload['movement_heading'] ?? $extra['movement_heading'] ?? null);
        $alt = self::num($payload['air']['altitude'] ?? $extra['asl_z'] ?? $row['pos_z'] ?? null);
        $fuel = self::num($extra['fuel'] ?? $row['fuel'] ?? null);
        $ammo = $extra['ammo'] ?? $row['ammo'] ?? null;
        $health = (string) ($extra['health'] ?? $row['health'] ?? '');
        $radioFreq = (string) ($extra['radio_freq'] ?? $row['radio_freq'] ?? '');
        $radioNet = (string) ($extra['radio_net'] ?? '');
        $inVeh = $extra['in_vehicle'] ?? null;
        $combatMode = (string) ($extra['combat_mode'] ?? '');
        $behaviour = (string) ($extra['behaviour'] ?? '');

        $row['source_arma'] = [
            'speed_ms' => $speedMs,
            'heading_deg' => $heading,
            'movement_heading_deg' => $moveH,
            'altitude_m' => $alt,
            'in_vehicle' => is_bool($inVeh) ? $inVeh : null,
            'vehicle' => $extra['vehicle'] ?? null,
            'fuel_pct' => $fuel,
            'ammo' => is_string($ammo) || is_numeric($ammo) ? $ammo : null,
            'health' => $health !== '' ? $health : null,
            'blood_pct' => self::num($extra['blood'] ?? null),
            'radio_freq' => $radioFreq !== '' && strtoupper($radioFreq) !== 'N/A' ? $radioFreq : null,
            'radio_net' => $radioNet !== '' ? $radioNet : null,
            'radio_lr' => $extra['radio_lr'] ?? $extra['lr_freq'] ?? null,
            'combat_mode' => $combatMode !== '' ? $combatMode : null,
            'behaviour' => $behaviour !== '' ? $behaviour : null,
            'formation' => $extra['formation'] ?? null,
            'group_count' => self::int($extra['group_count'] ?? $extra['crew_count'] ?? null),
            'leader' => $extra['leader'] ?? $extra['group_leader'] ?? null,
            'current_weapon' => $extra['current_weapon'] ?? null,
        ];

        $eta = is_array($nav) ? ($nav['eta'] ?? null) : null;
        $terrain = is_array($nav) ? ($nav['terrain'] ?? null) : null;
        $row['analysis_athena'] = [
            'motion_status' => $motion['status'] ?? null,
            'trend' => $motion['trend'] ?? null,
            'confidence' => $motion['confidence'] ?? null,
            'category' => $motion['category'] ?? null,
            'course_status' => is_array($nav) ? ($nav['course_status'] ?? null) : null,
            'destination' => is_array($nav) ? ($nav['destination_label'] ?? null) : null,
            'eta_kinematic_s' => is_array($eta) ? ($eta['seconds'] ?? null) : null,
            'eta_terrain_s' => is_array($terrain) ? ($terrain['eta_terrain_s'] ?? null) : null,
            'terrain_confidence' => is_array($terrain) ? ($terrain['confidence'] ?? null) : null,
        ];

        $contact = !empty($extra['radio_speaking'])
            || in_array(strtoupper((string) ($extra['behaviour'] ?? '')), ['COMBAT'], true)
            || in_array(strtoupper((string) ($extra['combat_mode'] ?? '')), ['YELLOW', 'RED', 'COMBAT'], true);
        $row['operational'] = [
            'unit' => [
                'call_sign' => $row['call_sign'] ?? $row['callsign'] ?? null,
                'role' => $row['role'] ?? $extra['role'] ?? null,
                'category' => $motion['category'] ?? null,
                'affiliation' => $extra['affiliation'] ?? $row['affiliation'] ?? null,
                'status' => $row['status'] ?? null,
            ],
            'task' => [
                'destination' => is_array($nav) ? ($nav['destination_label'] ?? null) : null,
                'course' => is_array($nav) ? ($nav['course_status'] ?? null) : null,
                'phase' => $extra['mission_phase'] ?? $extra['phase'] ?? null,
                'task' => $extra['mission_task'] ?? $extra['task'] ?? null,
            ],
            'combat' => [
                'contact' => $contact,
                'combat_mode' => $combatMode !== '' ? $combatMode : null,
                'behaviour' => $behaviour !== '' ? $behaviour : null,
                'weapon' => $extra['current_weapon'] ?? null,
            ],
            'logistics' => [
                'ammo' => is_string($ammo) || is_numeric($ammo) ? $ammo : null,
                'fuel_pct' => $fuel,
            ],
            'medical' => [
                'health' => $health !== '' ? $health : null,
                'blood_pct' => self::num($extra['blood'] ?? null),
                'unconscious' => in_array(strtolower($health), ['unconscious', 'cardiac_arrest', 'incapacitated'], true),
            ],
            'radio' => [
                'sr' => $row['source_arma']['radio_freq'],
                'lr' => $row['source_arma']['radio_lr'],
                'net' => $row['source_arma']['radio_net'],
                'speaking' => !empty($extra['radio_speaking']),
            ],
            'vehicle' => [
                'in_vehicle' => is_bool($inVeh) ? $inVeh : null,
                'class' => $extra['vehicle'] ?? null,
                'damage' => self::num($extra['vehicle_damage'] ?? $extra['damage'] ?? null),
                'fuel_pct' => $fuel,
            ],
            'air' => is_array($payload['air'] ?? null) ? $payload['air'] : null,
        ];

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function extra(array $row): array
    {
        $raw = $row['extra'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private static function num(mixed $v): ?float
    {
        if ($v === null || $v === '' || is_bool($v)) {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return is_finite($f) ? $f : null;
    }

    private static function int(mixed $v): ?int
    {
        $n = self::num($v);

        return $n === null ? null : (int) round($n);
    }
}
