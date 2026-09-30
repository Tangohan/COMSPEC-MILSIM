<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Support\MedicalAlertParser;

/**
 * Alertes médicales structurées (Phase B) — indépendantes du canal tchat.
 * Stockage fichier par théâtre ; fusionnées dans medical-alerts.
 */
final class AtakTelemetryMedicalStore
{
    private const MAX_ALERTS = 200;
    private const ACTIVE_WINDOW = MedicalAlertParser::ACTIVE_WINDOW_SECONDS;

    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (dirname(__DIR__, 3) . '/storage/cache/atak-telemetry-medical');
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function upsertFromEvent(int $tenantId, int $mapId, array $event): array
    {
        $kind = strtolower(trim((string) ($event['kind'] ?? $event['state'] ?? 'unconscious')));
        if (in_array($kind, ['death', 'dead', 'killed', 'mort'], true)) {
            $kind = 'kia';
        }
        $callSign = trim((string) ($event['call_sign'] ?? $event['cs'] ?? $event['unit'] ?? ''));
        if ($callSign === '') {
            $callSign = 'Operateur';
        }

        $label = match ($kind) {
            'cardiac_arrest' => 'Arrêt cardiaque',
            'kia' => 'Hors combat',
            'unconscious' => 'Au sol — inconscient',
            'wounded' => 'Blessé',
            'critical' => 'État critique',
            default => 'Assistance médicale',
        };
        $severity = match ($kind) {
            'kia', 'cardiac_arrest' => 'critical',
            'unconscious', 'critical' => 'critical',
            default => 'urgent',
        };

        $id = 'telmed_' . strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $callSign) ?? 'op')
            . '_' . substr(sha1($tenantId . '|' . $mapId . '|' . mb_strtoupper($callSign)), 0, 10);

        $alert = [
            'id' => $id,
            'is_medical' => true,
            'kind' => $kind === 'kia' ? 'kia' : ($kind === 'cardiac_arrest' ? 'cardiac_arrest' : 'unconscious'),
            'severity' => $severity,
            'call_sign' => $callSign,
            'label' => $label,
            'heart_rate' => isset($event['hr']) && is_numeric($event['hr']) ? (int) $event['hr'] : (isset($event['heart_rate']) && is_numeric($event['heart_rate']) ? (int) $event['heart_rate'] : null),
            'blood_pct' => isset($event['blood']) && is_numeric($event['blood']) ? (int) $event['blood'] : (isset($event['blood_pct']) && is_numeric($event['blood_pct']) ? (int) $event['blood_pct'] : null),
            'grid' => trim((string) ($event['grid'] ?? '')),
            'summary' => trim($callSign . ' — ' . $label),
            'author' => $callSign,
            'body' => '',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'map_id' => $mapId,
            'pos_x' => isset($event['x']) && is_numeric($event['x']) ? (float) $event['x'] : (isset($event['pos_x']) && is_numeric($event['pos_x']) ? (float) $event['pos_x'] : null),
            'pos_y' => isset($event['y']) && is_numeric($event['y']) ? (float) $event['y'] : (isset($event['pos_y']) && is_numeric($event['pos_y']) ? (float) $event['pos_y'] : null),
            'source' => 'telemetry',
            'telemetry' => true,
        ];

        $path = $this->path($tenantId, $mapId);
        $this->ensureDir();
        $lock = fopen($path . '.lock', 'c+');
        if ($lock === false) {
            return $alert;
        }
        try {
            flock($lock, LOCK_EX);
            $state = $this->readState($path);
            $alerts = is_array($state['alerts'] ?? null) ? $state['alerts'] : [];
            $replaced = false;
            foreach ($alerts as $i => $existing) {
                if (!is_array($existing)) {
                    continue;
                }
                if (strcasecmp((string) ($existing['call_sign'] ?? ''), $callSign) === 0
                    || (string) ($existing['id'] ?? '') === $id) {
                    // Conserver triage si déjà posé.
                    if (isset($existing['triage']) && is_array($existing['triage'])) {
                        $alert['triage'] = $existing['triage'];
                    }
                    $alerts[$i] = $alert;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) {
                $alerts[] = $alert;
            }
            $alerts = $this->prune($alerts);
            $this->writeState($path, ['alerts' => array_values($alerts)]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $alert;
    }

    public function clearForCallSign(int $tenantId, int $mapId, string $callSign, string $reason = 'annule'): bool
    {
        $callSign = trim($callSign);
        if ($callSign === '') {
            return false;
        }
        $path = $this->path($tenantId, $mapId);
        if (!is_file($path)) {
            return false;
        }
        $lock = fopen($path . '.lock', 'c+');
        if ($lock === false) {
            return false;
        }
        $changed = false;
        try {
            flock($lock, LOCK_EX);
            $state = $this->readState($path);
            $alerts = is_array($state['alerts'] ?? null) ? $state['alerts'] : [];
            foreach ($alerts as $i => $existing) {
                if (!is_array($existing)) {
                    continue;
                }
                if (strcasecmp((string) ($existing['call_sign'] ?? ''), $callSign) !== 0) {
                    continue;
                }
                $alerts[$i]['triage'] = [
                    'status' => $reason,
                    'is_resolved' => true,
                    'label' => MedicalAlertParser::triageLabelFr($reason),
                ];
                $alerts[$i]['triage_status'] = $reason;
                $changed = true;
            }
            if ($changed) {
                $this->writeState($path, ['alerts' => array_values($this->prune($alerts))]);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $changed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(int $tenantId, int $mapId, int $limit = 50): array
    {
        $state = $this->readState($this->path($tenantId, $mapId));
        $alerts = is_array($state['alerts'] ?? null) ? $state['alerts'] : [];
        $out = [];
        $now = time();
        foreach ($alerts as $a) {
            if (!is_array($a)) {
                continue;
            }
            $created = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            if ($created > 0 && ($now - $created) > self::ACTIVE_WINDOW) {
                continue;
            }
            $out[] = $a;
        }
        if (count($out) > $limit) {
            $out = array_slice($out, -$limit);
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $alerts
     * @return list<array<string, mixed>>
     */
    private function prune(array $alerts): array
    {
        $now = time();
        $kept = [];
        foreach ($alerts as $a) {
            if (!is_array($a)) {
                continue;
            }
            $created = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            if ($created > 0 && ($now - $created) > self::ACTIVE_WINDOW) {
                continue;
            }
            $kept[] = $a;
        }
        if (count($kept) > self::MAX_ALERTS) {
            $kept = array_slice($kept, -self::MAX_ALERTS);
        }

        return $kept;
    }

    private function path(int $tenantId, int $mapId): string
    {
        return $this->dir . '/t' . $tenantId . '_m' . $mapId . '.json';
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    /**
     * @return array{alerts?: list<array<string, mixed>>}
     */
    private function readState(string $path): array
    {
        if (!is_file($path)) {
            return ['alerts' => []];
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return ['alerts' => []];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['alerts' => []];
    }

    /**
     * @param array{alerts?: list<array<string, mixed>>} $state
     */
    private function writeState(string $path, array $state): void
    {
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json) && $json !== '') {
            file_put_contents($path, $json, LOCK_EX);
        }
    }
}
