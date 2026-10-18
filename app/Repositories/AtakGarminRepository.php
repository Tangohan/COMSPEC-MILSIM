<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * App Garmin de l'ATAK natif : la montre du joueur, mesurée sur son PC (fn_garminTick) et envoyée en entier toutes les
 * 5 min (COMSPEC Link « Garmin.Report », POST /api/atak/garmin) : état actuel, 14 derniers jours, 40 dernières activités.
 *
 * Un envoi est une photo : les jours sont remplacés en gardant le plus grand compteur (un profil effacé sur le PC ne
 * fait pas baisser une journée déjà reçue), les activités déjà reçues sont ignorées.
 */
class AtakGarminRepository
{
    use LazyDatabaseConnection;

    /** Compteurs d'une journée et plafond (au-delà : erreur ou triche, ramené au plafond). */
    public const DAY_COUNTERS = [
        'steps' => 200000,
        'foot_m' => 150000,
        'foot_s' => 86400,
        'hr_max' => 230,
        'bb_up' => 500,
        'bb_down' => 500,
        'sleep_s' => 86400,
        'run_n' => 200,
        'run_m' => 150000,
        'walk_n' => 200,
        'walk_m' => 150000,
        'act_n' => 200,
    ];

    /** Types d'activité de la montre : libellé et plafond de distance. */
    public const ACTIVITY_TYPES = [
        'RUN' => ['Course à pied', 150000],
        'WALK' => ['Marche', 150000],
        'DRIVE' => ['Trajet en véhicule', 3000000],
        'FLIGHT' => ['Vol', 15000000],
        'JUMP' => ['Saut', 300000],
        'BOAT' => ['Bateau', 1500000],
        'SWIM' => ['Nage', 50000],
    ];

    private const MAX_DAYS = 14;
    private const MAX_ACTIVITIES = 40;

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if (self::$ready === null) {
            try {
                $st = $this->pdo()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_garmin_status' LIMIT 1");
                self::$ready = $st !== false && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    private static function int(mixed $v, int $max, int $min = 0): int
    {
        return is_numeric($v) ? max($min, min($max, (int) round((float) $v))) : $min;
    }

    private static function text(mixed $v, int $max): string
    {
        $s = is_scalar($v) ? trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '') : '';

        return mb_substr($s, 0, $max);
    }

    /** Jour « AAAA-MM-JJ » plausible (30 jours en arrière au plus, demain au plus : fuseau du PC du joueur), sinon null. */
    private static function day(mixed $v, string $today): ?string
    {
        if (!is_string($v) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) !== 1 || !checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4))) {
            return null;
        }
        $t = strtotime($today . ' 00:00:00');
        $d = strtotime($v . ' 00:00:00');
        if ($t === false || $d === false || $d < $t - 30 * 86400 || $d > $t + 86400) {
            return null;
        }

        return $v;
    }

    /**
     * Envoi de la montre validé et plafonné.
     *
     * @return array{status: array{body_battery: int, heart_rate: int, stress: int, rest_hr: int, step_goal: int}, days: list<array<string, int|string|null>>, activities: list<array{day: string, started_at: string, type: string, place: string, world: string, meters: int, seconds: int}>}
     */
    public static function normalizeReport(mixed $body, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $body = is_array($body) ? $body : [];
        $s = is_array($body['status'] ?? null) ? $body['status'] : [];
        $status = [
            'body_battery' => self::int($s['bb'] ?? 0, 100),
            'heart_rate' => self::int($s['hr'] ?? 0, 230),
            'stress' => self::int($s['stress'] ?? 0, 100),
            'rest_hr' => self::int($s['rest_hr'] ?? 0, 120),
            'step_goal' => self::int($s['goal'] ?? 10000, 50000, 1000),
        ];

        $days = [];
        foreach (is_array($body['days'] ?? null) ? $body['days'] : [] as $d) {
            if (!is_array($d) || ($day = self::day($d['day'] ?? null, $today)) === null) {
                continue;
            }
            $row = ['day' => $day];
            foreach (self::DAY_COUNTERS as $k => $max) {
                $row[$k] = self::int($d[$k] ?? 0, $max);
            }
            $rest = is_numeric($d['hr_rest'] ?? null) ? (int) round((float) $d['hr_rest']) : 0;
            $row['hr_rest'] = $rest >= 30 && $rest <= 120 ? $rest : null;
            $row['stress_avg'] = self::int($d['stress_avg'] ?? 0, 100);
            $days[$day] = $row;
        }
        krsort($days);
        $days = array_values(array_slice($days, 0, self::MAX_DAYS, true));

        $acts = [];
        foreach (is_array($body['acts'] ?? null) ? $body['acts'] : [] as $a) {
            $type = is_array($a) ? strtoupper(self::text($a['type'] ?? '', 8)) : '';
            if (!isset(self::ACTIVITY_TYPES[$type]) || ($day = self::day($a['day'] ?? null, $today)) === null) {
                continue;
            }
            $at = self::text($a['at'] ?? '', 5);
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $at, $m) !== 1 || (int) $m[1] > 23 || (int) $m[2] > 59) {
                continue;
            }
            $acts[] = [
                'day' => $day,
                'started_at' => sprintf('%02d:%s', (int) $m[1], $m[2]),
                'type' => $type,
                'place' => self::text($a['place'] ?? '', 80),
                'world' => self::text($a['world'] ?? '', 60),
                'meters' => self::int($a['m'] ?? 0, self::ACTIVITY_TYPES[$type][1]),
                'seconds' => self::int($a['sec'] ?? 0, 43200),
            ];
        }
        $acts = array_slice($acts, -self::MAX_ACTIVITIES);

        return ['status' => $status, 'days' => $days, 'activities' => $acts];
    }

    /** @param array{status: array<string, int>, days: list<array<string, int|string|null>>, activities: list<array<string, int|string>>} $report */
    public function saveReport(int $tenantId, int $userId, array $report): bool
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return false;
        }
        $s = $report['status'];
        $this->pdo()->prepare(
            'INSERT INTO atak_garmin_status (tenant_id, user_id, body_battery, heart_rate, stress, rest_hr, step_goal, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE body_battery = VALUES(body_battery), heart_rate = VALUES(heart_rate), stress = VALUES(stress),
                rest_hr = VALUES(rest_hr), step_goal = VALUES(step_goal), synced_at = NOW()'
        )->execute([$tenantId, $userId, $s['body_battery'], $s['heart_rate'], $s['stress'], $s['rest_hr'], $s['step_goal']]);

        if ($report['days'] !== []) {
            $cols = array_keys(self::DAY_COUNTERS);
            $st = $this->pdo()->prepare(
                'INSERT INTO atak_garmin_days (tenant_id, user_id, day, ' . implode(', ', $cols) . ', hr_rest, stress_avg)
                 VALUES (?, ?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ', ?, ?)
                 ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn (string $k): string => "{$k} = GREATEST({$k}, VALUES({$k}))", $cols))
                . ', hr_rest = IF(hr_rest IS NULL, VALUES(hr_rest), IF(VALUES(hr_rest) IS NULL, hr_rest, LEAST(hr_rest, VALUES(hr_rest))))'
                . ', stress_avg = VALUES(stress_avg)'
            );
            foreach ($report['days'] as $d) {
                $st->execute(array_merge([$tenantId, $userId, $d['day']], array_map(static fn (string $k): int => (int) $d[$k], $cols), [$d['hr_rest'], $d['stress_avg']]));
            }
        }

        if ($report['activities'] !== []) {
            $st = $this->pdo()->prepare(
                'INSERT IGNORE INTO atak_garmin_activities (tenant_id, user_id, day, started_at, type, place, world, meters, seconds)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($report['activities'] as $a) {
                $st->execute([$tenantId, $userId, $a['day'], $a['started_at'], $a['type'], $a['place'], $a['world'], $a['meters'], $a['seconds']]);
            }
        }

        return true;
    }

    /**
     * Montre d'un membre (fiche du site) : dernier état, 7 derniers jours reçus, cumul de tous les jours et
     * 8 dernières activités. null si la montre n'a jamais rien envoyé.
     *
     * @return array<string, mixed>|null
     */
    public function summaryForUser(int $tenantId, int $userId): ?array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT body_battery, heart_rate, stress, rest_hr, step_goal, synced_at FROM atak_garmin_status WHERE tenant_id = ? AND user_id = ?');
        $st->execute([$tenantId, $userId]);
        $status = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($status)) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM atak_garmin_days WHERE tenant_id = ? AND user_id = ? ORDER BY day DESC LIMIT 7');
        $st->execute([$tenantId, $userId]);
        $week = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $st = $this->pdo()->prepare(
            'SELECT COUNT(*) AS days, COALESCE(SUM(steps), 0) AS steps, COALESCE(SUM(foot_m), 0) AS foot_m, COALESCE(SUM(run_n), 0) AS run_n,
                COALESCE(SUM(run_m), 0) AS run_m, COALESCE(SUM(sleep_s), 0) AS sleep_s FROM atak_garmin_days WHERE tenant_id = ? AND user_id = ?'
        );
        $st->execute([$tenantId, $userId]);
        $totals = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $st = $this->pdo()->prepare(
            'SELECT day, started_at, type, place, world, meters, seconds FROM atak_garmin_activities
             WHERE tenant_id = ? AND user_id = ? ORDER BY day DESC, started_at DESC LIMIT 8'
        );
        $st->execute([$tenantId, $userId]);

        return self::buildSummary($status, $week, $totals, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * @param array<string, mixed> $status
     * @param list<array<string, mixed>> $week jours du plus récent au plus ancien
     * @param array<string, mixed> $totals
     * @param list<array<string, mixed>> $acts
     * @return array<string, mixed>
     */
    public static function buildSummary(array $status, array $week, array $totals, array $acts): array
    {
        $n = max(1, count($week));
        $rest = array_values(array_filter(array_map(static fn (array $d): int => (int) ($d['hr_rest'] ?? 0), $week)));

        return [
            'body_battery' => (int) ($status['body_battery'] ?? 0),
            'heart_rate' => (int) ($status['heart_rate'] ?? 0),
            'stress' => (int) ($status['stress'] ?? 0),
            'rest_hr' => (int) ($status['rest_hr'] ?? 0),
            'step_goal' => (int) ($status['step_goal'] ?? 10000),
            'synced_at' => (string) ($status['synced_at'] ?? ''),
            'week' => array_map(static fn (array $d): array => [
                'day' => (string) $d['day'],
                'steps' => (int) $d['steps'],
                'foot_m' => (int) $d['foot_m'],
                'sleep_s' => (int) $d['sleep_s'],
                'stress_avg' => (int) $d['stress_avg'],
                'bb_up' => (int) $d['bb_up'],
                'bb_down' => (int) $d['bb_down'],
            ], $week),
            'avg_steps' => (int) round(array_sum(array_map(static fn (array $d): int => (int) $d['steps'], $week)) / $n),
            'avg_sleep_s' => (int) round(array_sum(array_map(static fn (array $d): int => (int) $d['sleep_s'], $week)) / $n),
            'avg_stress' => (int) round(array_sum(array_map(static fn (array $d): int => (int) $d['stress_avg'], $week)) / $n),
            'avg_rest_hr' => $rest === [] ? 0 : (int) round(array_sum($rest) / count($rest)),
            'totals' => [
                'days' => (int) ($totals['days'] ?? 0),
                'steps' => (int) ($totals['steps'] ?? 0),
                'foot_m' => (int) ($totals['foot_m'] ?? 0),
                'run_n' => (int) ($totals['run_n'] ?? 0),
                'run_m' => (int) ($totals['run_m'] ?? 0),
                'sleep_s' => (int) ($totals['sleep_s'] ?? 0),
            ],
            'activities' => array_map(static fn (array $a): array => [
                'day' => (string) $a['day'],
                'started_at' => (string) $a['started_at'],
                'type' => (string) $a['type'],
                'label' => self::ACTIVITY_TYPES[(string) $a['type']][0] ?? (string) $a['type'],
                'place' => (string) $a['place'],
                'meters' => (int) $a['meters'],
                'seconds' => (int) $a['seconds'],
            ], $acts),
        ];
    }
}
