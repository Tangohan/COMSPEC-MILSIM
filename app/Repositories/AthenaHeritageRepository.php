<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AthenaHeritageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function hasTable(string $table): bool
    {
        $st = $this->pdo->query(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '
            . $this->pdo->quote($table) . ' LIMIT 1'
        );

        return $st && (bool) $st->fetchColumn();
    }

    public function hasColumn(string $table, string $column): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    }

    public function ready(): bool
    {
        return $this->hasTable('athena_heritage_snapshots')
            && $this->hasTable('athena_heritage_milestones')
            && $this->hasTable('athena_heritage_version_points');
    }

    /**
     * @param array<string, mixed> $row
     */
    public function upsertSnapshot(array $row): void
    {
        if (!$this->hasTable('athena_heritage_snapshots')) {
            return;
        }
        $sql = 'INSERT INTO athena_heritage_snapshots (
                    snapshot_date, operators_total, organizations_total, sessions_total,
                    distance_km, playtime_hours, version_label, version_adoption_pct,
                    metrics_json, source
                ) VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    operators_total = VALUES(operators_total),
                    organizations_total = VALUES(organizations_total),
                    sessions_total = VALUES(sessions_total),
                    distance_km = VALUES(distance_km),
                    playtime_hours = VALUES(playtime_hours),
                    version_label = VALUES(version_label),
                    version_adoption_pct = VALUES(version_adoption_pct),
                    metrics_json = VALUES(metrics_json),
                    source = VALUES(source),
                    updated_at = CURRENT_TIMESTAMP';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            (string) $row['snapshot_date'],
            (int) ($row['operators_total'] ?? 0),
            (int) ($row['organizations_total'] ?? 0),
            (int) ($row['sessions_total'] ?? 0),
            (float) ($row['distance_km'] ?? 0),
            (float) ($row['playtime_hours'] ?? 0),
            $row['version_label'] ?? null,
            $row['version_adoption_pct'] ?? null,
            isset($row['metrics_json']) ? json_encode($row['metrics_json'], JSON_UNESCAPED_UNICODE) : null,
            (string) ($row['source'] ?? 'live'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSnapshots(?string $fromDate = null, ?string $toDate = null): array
    {
        if (!$this->hasTable('athena_heritage_snapshots')) {
            return [];
        }
        $sql = 'SELECT * FROM athena_heritage_snapshots WHERE 1=1';
        $params = [];
        if ($fromDate !== null && $fromDate !== '') {
            $sql .= ' AND snapshot_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate !== null && $toDate !== '') {
            $sql .= ' AND snapshot_date <= ?';
            $params[] = $toDate;
        }
        $sql .= ' ORDER BY snapshot_date ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function latestSnapshot(): ?array
    {
        if (!$this->hasTable('athena_heritage_snapshots')) {
            return null;
        }
        $st = $this->pdo->query(
            'SELECT * FROM athena_heritage_snapshots ORDER BY snapshot_date DESC LIMIT 1'
        );
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;

        return is_array($row) ? $row : null;
    }

    public function milestoneExists(string $key): bool
    {
        if (!$this->hasTable('athena_heritage_milestones')) {
            return false;
        }
        $st = $this->pdo->prepare('SELECT 1 FROM athena_heritage_milestones WHERE milestone_key = ? LIMIT 1');
        $st->execute([$key]);

        return (bool) $st->fetchColumn();
    }

    /**
     * Insert-once : ignore si la clé existe déjà.
     *
     * @param array<string, mixed> $row
     */
    public function insertMilestoneOnce(array $row): bool
    {
        if (!$this->hasTable('athena_heritage_milestones')) {
            return false;
        }
        $key = (string) ($row['milestone_key'] ?? '');
        if ($key === '' || $this->milestoneExists($key)) {
            return false;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO athena_heritage_milestones (
                milestone_key, category, occurred_at, date_precision,
                title, narrative, value_numeric, value_label, context_json
             ) VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $key,
            (string) ($row['category'] ?? 'threshold'),
            $row['occurred_at'] ?? null,
            (string) ($row['date_precision'] ?? 'exact'),
            (string) ($row['title'] ?? $key),
            (string) ($row['narrative'] ?? ''),
            $row['value_numeric'] ?? null,
            $row['value_label'] ?? null,
            isset($row['context_json']) ? json_encode($row['context_json'], JSON_UNESCAPED_UNICODE) : null,
        ]);

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMilestones(): array
    {
        if (!$this->hasTable('athena_heritage_milestones')) {
            return [];
        }
        $st = $this->pdo->query(
            "SELECT * FROM athena_heritage_milestones
             ORDER BY
               CASE WHEN occurred_at IS NULL THEN 1 ELSE 0 END,
               occurred_at ASC,
               id ASC"
        );
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function upsertVersionPoint(array $row): void
    {
        if (!$this->hasTable('athena_heritage_version_points')) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO athena_heritage_version_points (
                version, released_at, date_precision, title, summary, stats_json, changelog_ref
             ) VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                released_at = COALESCE(VALUES(released_at), released_at),
                date_precision = IF(
                    date_precision = \'unknown\' AND VALUES(date_precision) <> \'unknown\',
                    VALUES(date_precision),
                    date_precision
                ),
                title = VALUES(title),
                summary = VALUES(summary),
                stats_json = COALESCE(VALUES(stats_json), stats_json),
                changelog_ref = COALESCE(VALUES(changelog_ref), changelog_ref),
                updated_at = CURRENT_TIMESTAMP'
        );
        $st->execute([
            (string) $row['version'],
            $row['released_at'] ?? null,
            (string) ($row['date_precision'] ?? 'unknown'),
            (string) ($row['title'] ?? $row['version']),
            $row['summary'] ?? null,
            isset($row['stats_json']) ? json_encode($row['stats_json'], JSON_UNESCAPED_UNICODE) : null,
            $row['changelog_ref'] ?? null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listVersionPoints(): array
    {
        if (!$this->hasTable('athena_heritage_version_points')) {
            return [];
        }
        $st = $this->pdo->query(
            "SELECT * FROM athena_heritage_version_points
             ORDER BY
               CASE WHEN released_at IS NULL THEN 1 ELSE 0 END,
               released_at ASC,
               version ASC"
        );
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    public function countActiveOperators(): int
    {
        if (!$this->hasTable('users')) {
            return 0;
        }
        $svc = $this->hasColumn('users', 'is_service_account');
        $sql = "SELECT COUNT(*) FROM users u WHERE u.status = 'active'";
        if ($svc) {
            $sql .= ' AND (u.is_service_account IS NULL OR u.is_service_account = 0)';
        }
        $st = $this->pdo->query($sql);

        return $st ? (int) $st->fetchColumn() : 0;
    }

    public function countOrganizations(): int
    {
        if (!$this->hasTable('tenants')) {
            return 0;
        }
        $st = $this->pdo->query('SELECT COUNT(*) FROM tenants');

        return $st ? (int) $st->fetchColumn() : 0;
    }

    public function countSessions(): int
    {
        if ($this->hasTable('community_events')) {
            $st = $this->pdo->query(
                'SELECT COUNT(*) FROM community_events WHERE cancelled_at IS NULL'
            );
            if ($st) {
                return (int) $st->fetchColumn();
            }
        }

        return 0;
    }

    public function sumDistanceKm(): float
    {
        if ($this->hasTable('atak_vehicles') && $this->hasColumn('atak_vehicles', 'total_distance_traveled')) {
            $st = $this->pdo->query(
                'SELECT COALESCE(SUM(total_distance_traveled), 0) FROM atak_vehicles'
            );
            if ($st) {
                return round((float) $st->fetchColumn(), 2);
            }
        }
        if ($this->hasTable('atak_waypoint_routes') && $this->hasColumn('atak_waypoint_routes', 'total_distance_m')) {
            $st = $this->pdo->query(
                'SELECT COALESCE(SUM(total_distance_m), 0) / 1000 FROM atak_waypoint_routes'
            );
            if ($st) {
                return round((float) $st->fetchColumn(), 2);
            }
        }

        return 0.0;
    }

    public function sumPlaytimeHours(): float
    {
        if (!$this->hasTable('user_arma_playtime')) {
            return 0.0;
        }
        $st = $this->pdo->query(
            'SELECT COALESCE(SUM(total_seconds), 0) / 3600 FROM user_arma_playtime'
        );

        return $st ? round((float) $st->fetchColumn(), 2) : 0.0;
    }

    /**
     * Date exacte du N-ième opérateur (1-indexé) via created_at.
     */
    public function nthOperatorCreatedAt(int $n): ?string
    {
        if ($n < 1 || !$this->hasTable('users')) {
            return null;
        }
        $svc = $this->hasColumn('users', 'is_service_account');
        $sql = 'SELECT created_at FROM users u WHERE 1=1';
        if ($this->hasColumn('users', 'status')) {
            $sql .= " AND u.status = 'active'";
        }
        if ($svc) {
            $sql .= ' AND (u.is_service_account IS NULL OR u.is_service_account = 0)';
        }
        $sql .= ' ORDER BY u.created_at ASC, u.id ASC LIMIT 1 OFFSET ' . max(0, $n - 1);
        $st = $this->pdo->query($sql);
        $val = $st ? $st->fetchColumn() : false;

        return $val !== false && $val !== null && $val !== '' ? (string) $val : null;
    }

    public function firstRowDate(string $table, string $dateColumn = 'created_at', ?string $whereSql = null): ?string
    {
        if (!$this->hasTable($table) || !$this->hasColumn($table, $dateColumn)) {
            return null;
        }
        $sql = 'SELECT MIN(' . $dateColumn . ') FROM ' . $table;
        if ($whereSql !== null && $whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }
        $st = $this->pdo->query($sql);
        $val = $st ? $st->fetchColumn() : false;

        return $val !== false && $val !== null && $val !== '' ? (string) $val : null;
    }

    /**
     * Première organisation hors pays de référence (international).
     *
     * @return array{occurred_at: ?string, country_code: ?string}|null
     */
    public function firstForeignOrganization(string $homeCountry = 'FR'): ?array
    {
        if (!$this->hasTable('tenants') || !$this->hasColumn('tenants', 'country_code')) {
            return null;
        }
        $st = $this->pdo->prepare(
            "SELECT created_at, country_code FROM tenants
             WHERE country_code IS NOT NULL
               AND country_code <> ''
               AND UPPER(country_code) <> ?
             ORDER BY created_at ASC, id ASC
             LIMIT 1"
        );
        $st->execute([strtoupper($homeCountry)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'occurred_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'country_code' => isset($row['country_code']) ? (string) $row['country_code'] : null,
        ];
    }

    /**
     * Première opération réunissant au moins $minPlayers participants (RSVP yes / check-in).
     *
     * @return array{occurred_at: ?string, event_id: int, players: int}|null
     */
    public function firstLargeOperation(int $minPlayers = 50): ?array
    {
        if (!$this->hasTable('community_events') || !$this->hasTable('community_event_rsvps')) {
            return null;
        }
        $st = $this->pdo->prepare(
            "SELECT e.id, e.starts_at, e.created_at,
                    COUNT(r.id) AS players
             FROM community_events e
             INNER JOIN community_event_rsvps r ON r.event_id = e.id
             WHERE e.cancelled_at IS NULL
               AND r.status = 'yes'
             GROUP BY e.id, e.starts_at, e.created_at
             HAVING players >= ?
             ORDER BY COALESCE(e.starts_at, e.created_at) ASC, e.id ASC
             LIMIT 1"
        );
        $st->execute([$minPlayers]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $when = $row['starts_at'] ?? $row['created_at'] ?? null;

        return [
            'occurred_at' => $when !== null && $when !== '' ? (string) $when : null,
            'event_id' => (int) ($row['id'] ?? 0),
            'players' => (int) ($row['players'] ?? 0),
        ];
    }

    /**
     * Taux d’adoption approximatif de la version majeure courante (devices de confiance).
     */
    public function versionAdoptionPct(string $majorPrefix): ?float
    {
        if (!$this->hasTable('atak_trusted_devices') || !$this->hasColumn('atak_trusted_devices', 'last_mod_version')) {
            return null;
        }
        $st = $this->pdo->query(
            "SELECT COUNT(*) FROM atak_trusted_devices WHERE enrollment_status = 'enrolled' AND revoked_at IS NULL"
        );
        $total = $st ? (int) $st->fetchColumn() : 0;
        if ($total < 1) {
            return null;
        }
        $like = $this->pdo->quote($majorPrefix . '%');
        $st = $this->pdo->query(
            "SELECT COUNT(*) FROM atak_trusted_devices
             WHERE enrollment_status = 'enrolled' AND revoked_at IS NULL
               AND last_mod_version IS NOT NULL AND last_mod_version LIKE {$like}"
        );
        $matched = $st ? (int) $st->fetchColumn() : 0;

        return round(($matched / $total) * 100, 2);
    }

    /**
     * Première suggestion / pack de traduction publié si la table existe.
     */
    public function firstTranslationPackDate(): ?string
    {
        if ($this->hasTable('translation_suggestions')) {
            $date = $this->firstRowDate('translation_suggestions', 'created_at');
            if ($date !== null) {
                return $date;
            }
        }
        if ($this->hasTable('platform_i18n_packs')) {
            return $this->firstRowDate('platform_i18n_packs', 'created_at');
        }

        return null;
    }
}
