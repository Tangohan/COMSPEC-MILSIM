<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakIntelEventRepository;
use App\Repositories\AtakUnitAssignmentRepository;
use App\Support\AtakCopTerrainSchema;

/**
 * Produit des événements analytiques à partir des remontées BFT (pas d’invention).
 */
final class AtakCommandIntelService
{
    private AtakIntelEventRepository $events;
    private AtakUnitAssignmentRepository $assignments;

    public function __construct(
        ?AtakIntelEventRepository $events = null,
        ?AtakUnitAssignmentRepository $assignments = null
    ) {
        AtakCopTerrainSchema::ensure();
        $this->events = $events ?? new AtakIntelEventRepository();
        $this->assignments = $assignments ?? new AtakUnitAssignmentRepository();
    }

    /**
     * @param array<string, mixed> $computed
     * @param array<string, mixed>|null $previous
     * @param array<string, mixed> $extra
     */
    public function observe(
        int $tenantId,
        int $mapId,
        string $unitKind,
        string $unitRef,
        float $x,
        float $y,
        array $computed,
        ?array $previous,
        array $extra
    ): void {
        $unitRef = trim($unitRef);
        if ($tenantId < 1 || $unitRef === '') {
            return;
        }
        $kind = $unitKind === 'air' ? 'air' : 'ground';
        $status = strtoupper((string) ($computed['motion_status'] ?? AtakMotionMath::STATUS_UNKNOWN));
        $prevStatus = strtoupper((string) ($previous['motion_status'] ?? ''));
        $trend = strtoupper((string) ($computed['trend'] ?? ''));
        $speed = (float) ($computed['speed_ms'] ?? $extra['speed'] ?? 0);

        if ($prevStatus !== '' && $prevStatus !== $status) {
            if ($status === AtakMotionMath::STATUS_STATIC && in_array($prevStatus, [AtakMotionMath::STATUS_MOVING, AtakMotionMath::STATUS_FAST, AtakMotionMath::STATUS_MANEUVERING], true)) {
                $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_STOPPED', $unitRef . ' s’arrête', 'info', 20);
            }
            if (in_array($status, [AtakMotionMath::STATUS_MOVING, AtakMotionMath::STATUS_FAST, AtakMotionMath::STATUS_MANEUVERING], true)
                && in_array($prevStatus, [AtakMotionMath::STATUS_STATIC, AtakMotionMath::STATUS_UNKNOWN, ''], true)) {
                $cap = $this->headingLabel($computed['movement_heading'] ?? $extra['movement_heading'] ?? $extra['heading_object'] ?? null);
                $msg = $cap !== '' ? $unitRef . ' prend cap ' . $cap : $unitRef . ' se met en mouvement';
                $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_MOVING', $msg, 'info', 15);
            }
        }

        if ($trend === 'ACCEL' && $speed > 1.0) {
            $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_ACCELERATING', $unitRef . ' accélère', 'info', 40);
        }

        foreach (AtakCombatEventPresenter::fromExtra($unitRef, $extra) as $combat) {
            $this->emit(
                $tenantId,
                $mapId,
                $kind,
                $unitRef,
                (string) $combat['type'],
                (string) $combat['message'],
                (string) $combat['severity'],
                (int) $combat['debounce'],
                'arma',
                is_array($combat['payload'] ?? null) ? $combat['payload'] : null
            );
        }

        $health = strtolower(trim((string) ($extra['health'] ?? '')));
        if (in_array($health, ['unconscious', 'cardiac_arrest', 'incapacitated', 'down'], true)) {
            $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_INCAPACITATED', $unitRef . ' — personnel hors combat', 'alert', 90, 'arma');
        } elseif (in_array($health, ['wounded', 'injured', 'critical'], true)) {
            $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_CASUALTY', $unitRef . ' — blessé signalé', 'warn', 60, 'arma');
        }

        $fuel = $this->num($extra['fuel'] ?? null);
        if ($fuel !== null && $fuel <= 15 && !empty($extra['in_vehicle'])) {
            $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_LOW_FUEL', $unitRef . ' — carburant bas (' . round($fuel) . ' %)', 'warn', 90, 'arma');
        }

        $inVeh = $extra['in_vehicle'] ?? null;
        $prevInVeh = $previous['motion_json']['in_vehicle'] ?? null;
        if (is_bool($inVeh) && is_bool($prevInVeh) && $inVeh !== $prevInVeh) {
            if ($inVeh) {
                $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_REMOUNTED', $unitRef . ' remonte à bord', 'info', 20);
            } else {
                $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_DISMOUNTED', $unitRef . ' débarque', 'info', 20);
            }
        }

        $asg = null;
        try {
            $asg = $this->assignments->findActiveForUnit($tenantId, $mapId, $kind, $unitRef);
        } catch (\Throwable) {
        }
        if (is_array($asg)) {
            $dx = $this->num($asg['destination_x'] ?? null);
            $dy = $this->num($asg['destination_y'] ?? null);
            $label = trim((string) ($asg['destination_label'] ?? 'objectif'));
            if ($dx !== null && $dy !== null) {
                $dist = hypot($dx - $x, $dy - $y);
                $bearing = AtakMotionMath::bearingTo($x, $y, $dx, $dy);
                $moveH = $this->num($computed['movement_heading'] ?? null);
                $course = AtakMotionMath::courseStatus($moveH, $bearing, $speed, $dist, (string) ($computed['category'] ?? AtakMotionMath::CAT_INFANTRY));
                if ($course === AtakMotionMath::COURSE_ARRIVED) {
                    $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_ARRIVED', $unitRef . ' arrive sur ' . $label, 'info', 30);
                } elseif ($course === AtakMotionMath::COURSE_DIVERGING) {
                    $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_DIVERGING', $unitRef . ' s’écarte de ' . $label, 'warn', 45);
                    $this->emit($tenantId, $mapId, $kind, $unitRef, 'UNIT_OFF_ROUTE', $unitRef . ' hors route vers ' . $label, 'warn', 60);
                } elseif ($dist < 500 && $speed > 0.4) {
                    $this->emit($tenantId, $mapId, $kind, $unitRef, 'OBJECTIVE_APPROACH', $unitRef . ' approche ' . $label, 'info', 40);
                }
                if ($kind === 'air' && $speed > 8 && $dist > 80) {
                    $this->emit($tenantId, $mapId, $kind, $unitRef, 'AIRCRAFT_INBOUND', $unitRef . ' passe en approche', 'info', 50);
                }
                if ($kind === 'air' && $dist < 400 && $speed < 15) {
                    $this->emit($tenantId, $mapId, $kind, $unitRef, 'AIRCRAFT_ON_STATION', $unitRef . ' sur station', 'info', 60);
                }
            }
        }
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function emit(
        int $tenantId,
        int $mapId,
        string $kind,
        string $unitRef,
        string $type,
        string $message,
        string $severity,
        int $debounceSec,
        string $source = 'athena',
        ?array $payload = null
    ): void {
        $last = $this->events->lastOfType($tenantId, $mapId, $kind, $unitRef, $type);
        if (is_array($last)) {
            $t = strtotime((string) ($last['created_at'] ?? '')) ?: 0;
            if ($t > 0 && (time() - $t) < $debounceSec) {
                return;
            }
        }
        $this->events->insert($tenantId, $mapId, $kind, $unitRef, $type, $message, $source, $severity, $payload);
    }

    private function headingLabel(mixed $h): string
    {
        $n = $this->num($h);
        if ($n === null) {
            return '';
        }

        return str_pad((string) (int) round(fmod($n + 360.0, 360.0)), 3, '0', STR_PAD_LEFT) . '°';
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '' || $v === false) {
            return null;
        }
        if (is_bool($v)) {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return is_finite($f) ? $f : null;
    }
}
