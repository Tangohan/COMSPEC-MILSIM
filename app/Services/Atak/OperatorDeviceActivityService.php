<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Controllers\Api\AtakOverwatchIntelController;
use App\Repositories\AtakDeviceLogRepository;
use App\Repositories\OverwatchIntelRepository;
use App\Support\AtakDeviceLog;
use App\Support\AtakDevicePresenter;

/**
 * Activité d'un téléphone ATAK, lue comme sur un vrai terminal : applications qui transmettent,
 * synchronisations avec Athena et journal d'erreurs. Lecture seule.
 */
final class OperatorDeviceActivityService
{
    /** Une app est « active » si elle a transmis dans ce délai (même seuil que le poste Overwatch). */
    public const ACTIVE_SEC = 15 * 60;

    public function __construct(
        private ?OverwatchIntelRepository $intel = null,
        private ?AtakDeviceLogRepository $logs = null,
    ) {
    }

    /**
     * Applications du téléphone avec les données transmises par cet opérateur (toutes cartes confondues).
     *
     * @return list<array{app: string, module: string, data: string, today: int, day: int, age_sec: ?int, status: string}>
     */
    public function appActivity(int $tenantId, string $callsign): array
    {
        $callsign = trim($callsign);
        if ($tenantId < 1 || $callsign === '') {
            return [];
        }
        try {
            $this->intel ??= new OverwatchIntelRepository();
        } catch (\Throwable) {
            return [];
        }

        $rows = [];
        foreach (AtakOverwatchIntelController::appCatalog() as $def) {
            $specs = array_values(array_filter($def['sql'] ?? [], static fn (array $s): bool => !empty($s['author'])));
            if ($specs === []) {
                continue;
            }
            $today = 0;
            $day = 0;
            $age = null;
            $available = false;
            foreach ($specs as $spec) {
                $spec['author_value'] = $callsign;
                try {
                    $st = $this->intel->sourceStats($spec, $tenantId, 0);
                } catch (\Throwable) {
                    continue;
                }
                if (!$st['available']) {
                    continue;
                }
                $available = true;
                $today += $st['today'];
                $day += $st['total_24h'];
                if ($st['age_sec'] !== null && ($age === null || $st['age_sec'] < $age)) {
                    $age = $st['age_sec'];
                }
            }
            if (!$available) {
                continue;
            }
            $rows[] = [
                'app' => (string) $def['app'],
                'module' => (string) $def['module'],
                'data' => (string) $def['data'],
                'today' => $today,
                'day' => $day,
                'age_sec' => $age,
                'status' => $age === null ? 'jamais' : ($age <= self::ACTIVE_SEC ? 'actif' : 'inactif'),
            ];
        }
        // Actives d'abord, puis la plus récente.
        usort($rows, static fn (array $a, array $b): int => [$a['age_sec'] === null, $a['age_sec'] ?? 0] <=> [$b['age_sec'] === null, $b['age_sec'] ?? 0]);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function recentLogs(int $tenantId, string $terminalUid, int $limit = 300): array
    {
        if ($tenantId < 1 || trim($terminalUid) === '') {
            return [];
        }
        try {
            $this->logs ??= new AtakDeviceLogRepository();

            return $this->logs->listForTerminal($tenantId, $terminalUid, $limit);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Journal regroupé : un module par canal (volume, erreurs, dernier message) et les dernières erreurs.
     *
     * @param list<array<string, mixed>> $logs lignes atak_device_logs, plus récentes d'abord
     * @return array{modules: list<array<string, mixed>>, errors: list<array<string, mixed>>, errors_24h: int, warnings_24h: int, last_at: ?int}
     */
    public static function digestLogs(array $logs, ?int $now = null): array
    {
        $now ??= time();
        $modules = [];
        $errors = [];
        $errors24 = 0;
        $warn24 = 0;
        $lastAt = null;
        foreach ($logs as $row) {
            if (!is_array($row)) {
                continue;
            }
            $level = AtakDeviceLog::normalizeLevel((string) ($row['level'] ?? ''));
            $at = AtakDevicePresenter::utcTimestamp((string) ($row['logged_at'] ?? $row['created_at'] ?? ''));
            $lastAt = $lastAt === null || ($at !== null && $at > $lastAt) ? ($at ?? $lastAt) : $lastAt;
            $recent = $at !== null && $now - $at <= 86400;
            $key = strtolower(trim((string) ($row['channel'] ?? 'core'))) ?: 'core';
            $label = AtakDeviceLog::channelLabel($key);
            $m = $modules[$label] ?? ['module' => $label, 'channel' => $key, 'entries' => 0, 'errors' => 0, 'warnings' => 0, 'last_at' => null, 'last_message' => '', 'last_level' => ''];
            $m['entries']++;
            if ($level === AtakDeviceLog::LEVEL_ERROR) {
                $m['errors']++;
                $errors24 += $recent ? 1 : 0;
            } elseif ($level === AtakDeviceLog::LEVEL_WARN) {
                $m['warnings']++;
                $warn24 += $recent ? 1 : 0;
            }
            if ($m['last_at'] === null || ($at !== null && $at > $m['last_at'])) {
                $m['last_at'] = $at;
                $m['last_message'] = (string) ($row['message'] ?? '');
                $m['last_level'] = $level;
            }
            $modules[$label] = $m;

            if (($level === AtakDeviceLog::LEVEL_ERROR || $level === AtakDeviceLog::LEVEL_WARN) && count($errors) < 25) {
                $errors[] = [
                    'at' => $at,
                    'level' => $level,
                    'module' => $label,
                    'message' => (string) ($row['message'] ?? ''),
                    'detail' => trim((string) ($row['detail_text'] ?? '')),
                ];
            }
        }
        foreach ($modules as &$m) {
            $m['state'] = $m['last_level'] === AtakDeviceLog::LEVEL_ERROR ? 'bad' : ($m['errors'] > 0 || $m['warnings'] > 0 ? 'warn' : 'ok');
        }
        unset($m);
        $list = array_values($modules);
        usort($list, static fn (array $a, array $b): int => ($b['last_at'] ?? 0) <=> ($a['last_at'] ?? 0));

        return ['modules' => $list, 'errors' => $errors, 'errors_24h' => $errors24, 'warnings_24h' => $warn24, 'last_at' => $lastAt];
    }

    /**
     * État des synchronisations entre le téléphone et Athena, comme l'écran « Comptes et synchro » d'Android.
     *
     * @param array<string, mixed> $terminal
     * @param array<string, mixed> $phone
     * @param array{last_at: ?int, modules: list<array<string, mixed>>} $digest
     * @param list<array<string, mixed>> $apps
     * @return list<array{item: string, direction: string, detail: string, at: ?int, state: string}>
     */
    public static function syncTable(array $terminal, array $phone, array $digest, array $apps, ?int $now = null): array
    {
        $now ??= time();
        $ts = static fn (mixed $raw): ?int => is_string($raw) ? AtakDevicePresenter::utcTimestamp($raw) : null;
        $state = static function (?int $at, int $okSec, int $lateSec) use ($now): string {
            if ($at === null) {
                return 'never';
            }
            $age = $now - $at;

            return $age <= $okSec ? 'ok' : ($age <= $lateSec ? 'late' : 'stale');
        };
        $rows = [];

        if ($phone !== []) {
            $rev = (int) ($phone['identity_revision'] ?? 0);
            $rows[] = [
                'item' => 'Profil du téléphone',
                'direction' => 'Athena → téléphone',
                'detail' => 'Numéro, IMEI, adresse MAC, plan de numérotation',
                'at' => $rev > 0 ? $rev : null,
                'state' => $rev > 0 ? 'ok' : 'never',
            ];
            $seen = $ts($phone['device_seen_at'] ?? null);
            $rows[] = [
                'item' => 'État matériel',
                'direction' => 'Téléphone → Athena',
                'detail' => 'Batterie, réception, état de l’écran, modèle',
                'at' => $seen,
                'state' => $state($seen, 180, 3600),
            ];
        }
        if ($terminal !== []) {
            $seen = $ts($terminal['last_seen_at'] ?? null);
            $rows[] = [
                'item' => 'Enregistrement du terminal',
                'direction' => 'Téléphone → Athena',
                'detail' => 'Identifiant, versions, adresse IP, signature du serveur',
                'at' => $seen,
                'state' => $state($seen, 180, 3600),
            ];
            $issued = AtakDevicePresenter::utcTimestamp((string) ($terminal['certificate_issued_at'] ?? $terminal['certificate_valid_from'] ?? ''));
            $life = AtakDevicePresenter::certificateLifetime(AtakDevicePresenter::certificateOfTerminal($terminal), $now);
            $rows[] = [
                'item' => 'Certificat client',
                'direction' => 'Athena → téléphone',
                'detail' => trim((string) ($terminal['certificate_ref'] ?? '')) !== '' ? 'Référence ' . $terminal['certificate_ref'] : 'Aucun certificat émis',
                'at' => $issued,
                'state' => match ($life['state']) {
                    'valid', 'pending' => 'ok',
                    'expiring' => 'late',
                    'none' => 'never',
                    default => 'stale',
                },
            ];
        }
        $rows[] = [
            'item' => 'Journal de l’appareil',
            'direction' => 'Téléphone → Athena',
            'detail' => count($digest['modules']) . ' module' . (count($digest['modules']) > 1 ? 's' : '') . ' dans le journal (14 jours)',
            'at' => $digest['last_at'],
            'state' => $state($digest['last_at'], 3600, 86400),
        ];
        $lastApp = null;
        $activeApps = 0;
        foreach ($apps as $a) {
            if ($a['age_sec'] !== null) {
                $at = $now - (int) $a['age_sec'];
                $lastApp = $lastApp === null ? $at : max($lastApp, $at);
            }
            $activeApps += $a['status'] === 'actif' ? 1 : 0;
        }
        $rows[] = [
            'item' => 'Données tactiques',
            'direction' => 'Téléphone → Athena',
            'detail' => $activeApps . ' app' . ($activeApps > 1 ? 's' : '') . ' active' . ($activeApps > 1 ? 's' : '') . ' sur ' . count($apps),
            'at' => $lastApp,
            'state' => $state($lastApp, self::ACTIVE_SEC, 86400),
        ];

        return $rows;
    }
}
