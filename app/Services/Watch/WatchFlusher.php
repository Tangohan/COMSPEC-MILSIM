<?php

declare(strict_types=1);

namespace App\Services\Watch;

use App\Repositories\PlatformAbuseAlertRepository;
use App\Repositories\PlatformLoadRepository;
use PDO;

/**
 * Regroupe les tampons de surveillance : écrit les compteurs de charge en base par lots, analyse les signaux du mod,
 * calcule l'état de charge et le publie dans state.json. Un seul passage à la fois (verrou non bloquant).
 *
 * Lancé de deux façons : tâche automatique « platform_watch » (toutes les 5 minutes) et, entre deux passages, au plus
 * toutes les 30 secondes après l'envoi d'une réponse web (WatchRecorder). Le calcul de l'état de charge ne dépend ni de
 * la base ni du réseau : il ne lit que les fichiers du tampon et la charge du système.
 */
final class WatchFlusher
{
    /** Fenêtre de mesure de la charge (secondes). */
    public const WINDOW = 60;

    /** Intervalle minimal entre deux passages déclenchés par le trafic (secondes). */
    public const MIN_INTERVAL = 30;

    /** Nombre maximal de nouvelles alertes écrites par passage : un afflux de détections ne noie pas l'administration. */
    public const MAX_NEW_ALERTS = 25;

    /** Le réglage est relu en base au plus toutes les 60 s. */
    public const SETTINGS_TTL = 60;

    /** Pause (secondes) des passages déclenchés par le trafic après un échec de la base. */
    public const DB_PAUSE = 300;

    /** Purge de la base au plus une fois par 6 heures. */
    public const PURGE_EVERY = 21600;

    /**
     * @param callable(): PDO|null $pdoFactory connexion à la base, ouverte seulement si nécessaire (tests : base simulée)
     * @param array{0: float, 1: float, 2: float}|null|false $loadAvg charge système imposée (tests) ; false = sys_getloadavg()
     * @return array<string, mixed> résumé du passage ; 'ran' vaut false si un autre passage tournait
     */
    public static function run(int $now, ?callable $pdoFactory = null, array|null|false $loadAvg = false, ?int $cores = null, bool $force = false): array
    {
        $dir = WatchSpool::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ran' => false, 'reason' => 'no_dir'];
        }
        $lock = @fopen($dir . '/flush.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ran' => false, 'reason' => 'locked'];
        }
        try {
            WatchState::forget();
            $previous = WatchState::raw();
            if (!$force && $previous !== null && $now - (int) $previous['at'] < self::MIN_INTERVAL) {
                return ['ran' => false, 'reason' => 'fresh'];
            }
            // Filet : si state.json ne peut pas être écrit, un passage toutes les 30 s tout de même, pas un par requête.
            $stamp = $dir . '/flush.stamp';
            $stampAge = is_file($stamp) ? $now - (int) @filemtime($stamp) : PHP_INT_MAX;
            if (!$force && $stampAge >= 0 && $stampAge < self::MIN_INTERVAL) {
                return ['ran' => false, 'reason' => 'recent'];
            }
            @touch($stamp);
            $pdoFactory ??= static fn (): PDO => \App\Core\Database::getPdo();
            $summary = ['ran' => true];

            [$settings, $settingsAt] = self::settings($previous, $now, $pdoFactory, $force);
            $files = WatchSpool::files(WatchSpool::KIND_LOAD);
            $closed = intdiv($now, 60) * 60;

            // Fenêtre de mesure : les fichiers des deux dernières minutes (ouverts ou déjà traités), lus en flux.
            $signals = self::signals(
                self::windowRows($files, $closed, $now),
                $loadAvg === false ? (function_exists('sys_getloadavg') ? (sys_getloadavg() ?: null) : null) : $loadAvg,
                $cores ?? LoadLevel::cpuCores()
            );
            $eval = LoadLevel::evaluate($signals, $settings);
            $settled = LoadLevel::settle($eval['raw_level'], $eval['reliable'], ['level' => (int) ($previous['level'] ?? 0), 'up' => (int) ($previous['up'] ?? 0), 'down' => (int) ($previous['down'] ?? 0)]);
            $killed = strtolower(trim((string) (function_exists('env') ? env('LOAD_SHED', '') : ''))) === 'off';
            $state = [
                'v' => 1,
                'at' => $now,
                'shed_enabled' => !$killed && (int) $settings[WatchSettings::SHED_ENABLED] === 1,
                'shed_percent' => (int) $settings[WatchSettings::SHED_PERCENT],
                'detect_enabled' => (int) $settings[WatchSettings::DETECT_ENABLED] === 1,
                'kill_switch' => $killed,
                'level' => $settled['level'],
                'up' => $settled['up'],
                'down' => $settled['down'],
                'raw_level' => $eval['raw_level'],
                'ratio' => $eval['ratio'],
                'sensor' => [
                    'reliable' => $eval['reliable'],
                    'reason' => $eval['reason'],
                    'used' => $eval['used'],
                    'requests' => $signals['requests'],
                    'p95_ms' => $signals['p95_ms'],
                    'error_pct' => $signals['error_pct'],
                    'load_per_core' => $signals['load_per_core'],
                    'window_s' => self::WINDOW,
                ],
                'settings' => $settings,
                'settings_at' => $settingsAt,
            ];
            WatchState::write($state);
            $summary['level'] = $state['level'];

            // Base de données : tout ce qui suit peut échouer sans toucher à l'état publié ci-dessus. Après un échec (base
            // muette, étape de migration pas encore passée), les passages déclenchés par le trafic n'insistent pas pendant
            // 5 minutes ; la tâche automatique, elle, réessaie à chaque passage.
            $pause = $dir . '/db-pause.stamp';
            if (!$force && is_file($pause) && $now - (int) @filemtime($pause) >= 0 && $now - (int) @filemtime($pause) < self::DB_PAUSE) {
                $summary['db_paused'] = true;
                WatchSpool::sweep(WatchSpool::KIND_LOAD, $now);
                WatchSpool::sweep(WatchSpool::KIND_MOD, $now, 0);

                return $summary;
            }
            try {
                $pdo = $pdoFactory();
                $summary += self::flushLoad($pdo, $files, $closed);
                $summary += self::flushMod($pdo, $now, $settings);
                self::raiseLoadAlert($pdo, $state, $now);
                if ($state['shed_enabled'] && ((PlanRankIndex::ageSeconds($now) ?? PHP_INT_MAX) > 60)) {
                    PlanRankIndex::refresh($pdo, $now);
                }
                $summary += self::purge($pdo, $now);
                @unlink($pause);
            } catch (\Throwable $e) {
                $summary['db_error'] = true;
                @touch($pause);
            }
            WatchSpool::sweep(WatchSpool::KIND_LOAD, $now);
            WatchSpool::sweep(WatchSpool::KIND_MOD, $now, 0);

            return $summary;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Réglages en vigueur : ceux de l'état précédent s'ils ont moins de SETTINGS_TTL secondes, sinon relus en base ;
     * en cas d'échec, derniers réglages connus, à défaut les valeurs par défaut (ralentissement coupé).
     *
     * @param array<string, mixed>|null $previous
     * @return array{0: array<string, float|int>, 1: int} réglages et date de leur dernière lecture en base
     */
    private static function settings(?array $previous, int $now, callable $pdoFactory, bool $force = false): array
    {
        $known = is_array($previous['settings'] ?? null) ? WatchSettings::fromMap($previous['settings']) : null;
        $knownAt = (int) ($previous['settings_at'] ?? 0);
        if (!$force && $known !== null && $now - $knownAt < self::SETTINGS_TTL) {
            return [$known, $knownAt];
        }
        try {
            $pdo = $pdoFactory();
            $placeholders = implode(',', array_fill(0, count(WatchSettings::keys()), '?'));
            $st = $pdo->prepare('SELECT setting_key, value FROM platform_settings WHERE setting_key IN (' . $placeholders . ')');
            $st->execute(WatchSettings::keys());
            $raw = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $raw[(string) $row['setting_key']] = (string) $row['value'];
            }

            return [WatchSettings::fromMap($raw), $now];
        } catch (\Throwable) {
            // Base muette : derniers réglages connus (la prochaine tentative a lieu au passage suivant).
            return [$known ?? WatchSettings::defaults(), $knownAt];
        }
    }

    /**
     * Lignes L des dernières secondes (WINDOW), lues en flux dans les fichiers de la fenêtre.
     *
     * @param list<array{path: string, minute: int, ext: string}> $files
     * @return \Generator<int, list<string>>
     */
    private static function windowRows(array $files, int $closedMinute, int $now): \Generator
    {
        foreach ($files as $f) {
            if ($f['minute'] < $closedMinute - 120) {
                continue;
            }
            foreach (WatchSpool::lines($f['path']) as $row) {
                if (($row[0] ?? '') === 'L' && (int) ($row[1] ?? 0) >= $now - self::WINDOW) {
                    yield $row;
                }
            }
        }
    }

    /**
     * Signaux de la fenêtre : requêtes dynamiques hors ralenties, hors administration et tâches.
     *
     * @param iterable<list<string>> $rows lignes L de la fenêtre
     * @param array{0: float, 1: float, 2: float}|null $loadAvg
     * @return array{requests: int, p95_ms: ?int, error_pct: ?float, load_per_core: ?float}
     */
    public static function signals(iterable $rows, ?array $loadAvg, ?int $cores): array
    {
        $durations = [];
        $errors = 0;
        foreach ($rows as $r) {
            if ((int) ($r[6] ?? 0) === 1) {
                continue;
            }
            $route = (string) ($r[3] ?? '');
            if (str_contains($route, ' /admin') || str_contains($route, ' /cron/') || str_contains($route, ' /api/admin/')) {
                continue;
            }
            $durations[] = (int) ($r[5] ?? 0);
            if ((int) ($r[4] ?? 0) >= 500) {
                $errors++;
            }
        }
        $n = count($durations);

        return [
            'requests' => $n,
            'p95_ms' => LoadLevel::percentile($durations, 95),
            'error_pct' => $n > 0 ? round($errors * 100 / $n, 1) : null,
            'load_per_core' => LoadLevel::loadPerCore($loadAvg, $cores),
        ];
    }

    /**
     * Minutes closes → base (une transaction), puis fichiers marqués « .done ».
     *
     * @param list<array{path: string, minute: int, ext: string}> $files
     * @return array{load_files: int, load_rows: int}
     */
    private static function flushLoad(PDO $pdo, array $files, int $closedMinute): array
    {
        $minutes = [];
        $hours = [];
        $todo = [];
        foreach ($files as $f) {
            if ($f['ext'] !== 'log' || $f['minute'] >= $closedMinute) {
                continue;
            }
            $todo[] = $f['path'];
            foreach (WatchSpool::read($f['path']) as $r) {
                if (($r[0] ?? '') !== 'L') {
                    continue;
                }
                self::addLine($minutes, $hours, $r);
            }
        }
        if ($todo === []) {
            return ['load_files' => 0, 'load_rows' => 0];
        }
        $repo = new PlatformLoadRepository($pdo);
        $pdo->beginTransaction();
        try {
            $repo->addMinutes($minutes);
            $repo->addTenantHours($hours);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        foreach ($todo as $path) {
            @rename($path, preg_replace('/\.log$/', '.done', $path) ?? $path);
        }

        return ['load_files' => count($todo), 'load_rows' => count($minutes) + count($hours)];
    }

    /**
     * Ajoute une ligne L aux compteurs par minute et par heure.
     *
     * @param array<string, array<string, mixed>> $minutes
     * @param array<string, array<string, mixed>> $hours
     * @param list<string> $r
     */
    public static function addLine(array &$minutes, array &$hours, array $r): void
    {
        $ts = (int) ($r[1] ?? 0);
        if ($ts < 1) {
            return;
        }
        $tenant = (int) ($r[2] ?? 0);
        $route = substr((string) ($r[3] ?? '(inconnue)'), 0, 120);
        $status = (int) ($r[4] ?? 0);
        $ms = max(0, (int) ($r[5] ?? 0));
        $shed = (int) ($r[6] ?? 0) === 1;
        $err = !$shed && $status >= 500 ? 1 : 0;
        $mk = intdiv($ts, 60) * 60 . '|' . $route;
        $minutes[$mk] ??= ['requests' => 0, 'errors' => 0, 'shed' => 0, 'dur_sum' => 0, 'dur_max' => 0, 'b' => [0, 0, 0, 0, 0]];
        $minutes[$mk]['requests']++;
        $minutes[$mk]['errors'] += $err;
        if ($shed) {
            $minutes[$mk]['shed']++;
        } else {
            $minutes[$mk]['dur_sum'] += $ms;
            $minutes[$mk]['dur_max'] = max($minutes[$mk]['dur_max'], $ms);
            $minutes[$mk]['b'][PlatformLoadRepository::bucketOf($ms)]++;
        }
        if ($tenant > 0) {
            $hk = intdiv($ts, 3600) * 3600 . '|' . $tenant . '|' . $route;
            $hours[$hk] ??= ['requests' => 0, 'errors' => 0, 'shed' => 0, 'dur_sum' => 0];
            $hours[$hk]['requests']++;
            $hours[$hk]['errors'] += $err;
            $hours[$hk]['shed'] += $shed ? 1 : 0;
            $hours[$hk]['dur_sum'] += $shed ? 0 : $ms;
        }
    }

    /**
     * Signaux du mod des minutes closes → détections → alertes.
     *
     * @param array<string, float|int> $settings
     * @return array{mod_rows: int, alerts_created: int, alerts_merged: int}
     */
    private static function flushMod(PDO $pdo, int $now, array $settings): array
    {
        $closed = intdiv($now, 60) * 60;
        $paths = [];
        foreach (WatchSpool::files(WatchSpool::KIND_MOD) as $f) {
            if ($f['ext'] === 'log' && $f['minute'] < $closed) {
                $paths[] = $f['path'];
            }
        }
        $statePath = WatchSpool::dir() . '/mod-state.json';
        $saved = json_decode((string) @file_get_contents($statePath), true);
        $saved = is_array($saved) ? $saved : [];
        $pending = is_array($saved['pending'] ?? null) ? $saved['pending'] : [];
        unset($saved['pending']);
        if ($paths === [] && $pending === []) {
            return ['mod_rows' => 0, 'alerts_created' => 0, 'alerts_merged' => 0];
        }
        $rowCount = 0;
        $rows = (static function () use ($paths, &$rowCount): \Generator {
            foreach ($paths as $path) {
                foreach (WatchSpool::lines($path) as $row) {
                    $rowCount++;
                    yield $row;
                }
            }
        })();
        $result = (int) $settings[WatchSettings::DETECT_ENABLED] === 1
            ? ModAbuseDetector::analyse($rows, $saved, $settings, $now)
            : ['detections' => [], 'state' => ModAbuseDetector::emptyState()];
        $repo = new PlatformAbuseAlertRepository($pdo);
        $created = 0;
        $merged = 0;
        $retry = [];
        foreach (array_merge($pending, $result['detections']) as $d) {
            if ($created >= self::MAX_NEW_ALERTS) {
                continue;
            }
            $outcome = $repo->raise($d + ['kind' => PlatformAbuseAlertRepository::KIND_MOD], $now);
            if ($outcome === 'created') {
                $created++;
            } elseif ($outcome === 'merged') {
                $merged++;
            } elseif ($outcome === 'error' && count($retry) < 50) {
                $retry[] = $d;
            }
        }
        $newState = $result['state'];
        $newState['pending'] = $retry;
        @file_put_contents($statePath . '.tmp', json_encode($newState));
        @rename($statePath . '.tmp', $statePath);
        foreach ($paths as $path) {
            @unlink($path);
        }

        return ['mod_rows' => $rowCount, 'alerts_created' => $created, 'alerts_merged' => $merged];
    }

    /**
     * Charge élevée soutenue (palier retenu au moins 1) : une alerte regroupée, qu'un humain voit dans l'administration.
     *
     * @param array<string, mixed> $state
     */
    private static function raiseLoadAlert(PDO $pdo, array $state, int $now): void
    {
        if ((int) $state['level'] < 1) {
            return;
        }
        $repo = new PlatformAbuseAlertRepository($pdo);
        $repo->raise([
            'kind' => PlatformAbuseAlertRepository::KIND_LOAD,
            'rule' => 'charge_elevee',
            'key' => 'platform',
            'tenant_id' => null,
            'measured' => [
                'palier' => (int) $state['level'],
                'rapport_au_seuil' => $state['ratio'],
                'p95_ms' => $state['sensor']['p95_ms'],
                'erreurs_pct' => $state['sensor']['error_pct'],
                'charge_par_coeur' => $state['sensor']['load_per_core'],
                'requetes_fenetre' => $state['sensor']['requests'],
                'ralentissement_actif' => (bool) $state['shed_enabled'],
            ],
        ], $now);
    }

    /**
     * Purge bornée des anciennes données, au plus toutes les 6 heures.
     *
     * @return array{purged?: array<string, int>}
     */
    private static function purge(PDO $pdo, int $now): array
    {
        $stamp = WatchSpool::dir() . '/purge.stamp';
        if (is_file($stamp) && $now - (int) @filemtime($stamp) < self::PURGE_EVERY) {
            return [];
        }
        @touch($stamp);
        [$minutes, $hours] = (new PlatformLoadRepository($pdo))->purge(WatchSchema::RETENTION_MINUTE_DAYS, WatchSchema::RETENTION_HOUR_DAYS, $now);
        $alerts = (new PlatformAbuseAlertRepository($pdo))->purgeOlderThan(WatchSchema::RETENTION_ALERT_DAYS, $now);

        return ['purged' => ['minutes' => $minutes, 'hours' => $hours, 'alerts' => $alerts]];
    }
}
