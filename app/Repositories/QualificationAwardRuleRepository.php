<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Réglages d'obtention et d'affichage d'une qualification (qualification_award_rules : délivrance manuelle,
 * règle automatique, prérequis de grade et de fonction, visibilité, besoin par unité), et lectures des données
 * mesurées que la règle automatique compare à ses seuils (une requête groupée par membre pour toute la communauté).
 */
final class QualificationAwardRuleRepository
{
    private ?PDO $pdo;
    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public static function resetCache(): void
    {
        self::$ready = null;
    }

    public function schemaReady(): bool
    {
        if (self::$ready === null) {
            try {
                $this->pdo()->query('SELECT 1 FROM qualification_award_rules LIMIT 1');
                self::$ready = true;
            } catch (Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $definitionId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM qualification_award_rules WHERE tenant_id = ? AND definition_id = ? LIMIT 1');
        $st->execute([$tenantId, $definitionId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> qualification => réglages */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT * FROM qualification_award_rules WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['definition_id']] = $row;
        }

        return $out;
    }

    /** @param array<string, mixed> $data colonnes de qualification_award_rules */
    public function save(int $tenantId, int $definitionId, array $data, ?int $actorId): void
    {
        $cols = ['allow_manual', 'auto_enabled', 'auto_mode', 'rule_json', 'prereq_grade_id', 'prereq_job_role_id', 'show_on_profile', 'show_on_orbat', 'min_holders_per_unit'];
        $vals = [];
        foreach ($cols as $c) {
            $vals[] = $data[$c] ?? null;
        }
        $now = date('Y-m-d H:i:s');
        if ($this->find($tenantId, $definitionId) === null) {
            $st = $this->pdo()->prepare(
                'INSERT INTO qualification_award_rules (tenant_id, definition_id, ' . implode(', ', $cols) . ', created_by, updated_by, created_at, updated_at)
                 VALUES (?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ', ?, ?, ?, ?)'
            );
            $st->execute(array_merge([$tenantId, $definitionId], $vals, [$actorId, $actorId, $now, $now]));

            return;
        }
        $st = $this->pdo()->prepare(
            'UPDATE qualification_award_rules SET ' . implode(', ', array_map(static fn (string $c): string => $c . ' = ?', $cols)) . ', updated_by = ?, updated_at = ?
             WHERE tenant_id = ? AND definition_id = ?'
        );
        $st->execute(array_merge($vals, [$actorId, $now, $tenantId, $definitionId]));
    }

    public function touchEvaluated(int $tenantId, int $definitionId): void
    {
        try {
            $this->pdo()->prepare('UPDATE qualification_award_rules SET last_evaluated_at = ? WHERE tenant_id = ? AND definition_id = ?')
                ->execute([date('Y-m-d H:i:s'), $tenantId, $definitionId]);
        } catch (Throwable) {
        }
    }

    /** Communautés ayant au moins une règle automatique active. @return list<int> */
    public function tenantsWithAutoRules(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->query('SELECT DISTINCT tenant_id FROM qualification_award_rules WHERE auto_enabled = 1');

        return $st ? array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
    }

    /**
     * Unité principale, grade et Steam des membres donnés.
     *
     * @param list<int> $userIds
     * @return array<int, array{unit_id: int, unit_name: string, unit_short: string, grade_id: int, steam_id: string}>
     */
    public function memberDetails(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $i): bool => $i > 0)));
        if ($userIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $rows = [];
        foreach (['un.short_label', "''"] as $shortCol) {
            try {
                $st = $this->pdo()->prepare(
                    "SELECT u.id, u.grade_id, u.steam_id, pp.primary_unit_id, un.name AS unit_name, {$shortCol} AS unit_short
                     FROM users u
                     LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
                     LEFT JOIN units un ON un.id = pp.primary_unit_id AND un.tenant_id = ?
                     WHERE u.id IN ({$in})"
                );
                $st->execute(array_merge([$tenantId], $userIds));
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                break;
            } catch (Throwable) {
                $rows = [];
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = [
                'unit_id' => (int) ($r['primary_unit_id'] ?? 0),
                'unit_name' => trim((string) ($r['unit_name'] ?? '')),
                'unit_short' => trim((string) ($r['unit_short'] ?? '')),
                'grade_id' => (int) ($r['grade_id'] ?? 0),
                'steam_id' => trim((string) ($r['steam_id'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Toutes les attributions rattachées à une qualification de la communauté, par membre.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function awardsByUser(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT pq.* FROM personnel_qualifications pq
                 JOIN personnel_qualification_definitions d ON d.id = pq.definition_id AND d.tenant_id = ?
                 WHERE (pq.tenant_id = ? OR pq.tenant_id IS NULL)'
            );
            $st->execute([$tenantId, $tenantId]);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['user_id']][] = $row;
        }

        return $out;
    }

    /**
     * Attestations Academy en cours de validité, par membre : membre => formation => true.
     *
     * @return array<int, array<int, bool>>
     */
    public function validAcademyCourses(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare(
                "SELECT user_id, training_course_id FROM personnel_qualifications
                 WHERE tenant_id = ? AND source = 'training' AND training_course_id IS NOT NULL
                   AND status IN ('valid', 'expiring') AND (expires_at IS NULL OR expires_at >= ?)"
            );
            $st->execute([$tenantId, date('Y-m-d')]);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['user_id']][(int) $row['training_course_id']] = true;
        }

        return $out;
    }

    /**
     * Cours intégrés de l’Academy terminés (JTAC, cours inspirés des standards OTAN), par membre : membre => clé => true.
     *
     * @return array<int, array<string, bool>>
     */
    public function builtInCourseCompletions(int $tenantId): array
    {
        try {
            return \App\Services\Academy\AcademyBuiltInQualifications::forPdo($this->pdo())->completions($tenantId);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Rattache à une qualification les attestations Academy de ces formations qui ne le sont pas encore.
     *
     * @param list<int> $courseIds
     */
    public function attachAcademyAwards(int $tenantId, int $definitionId, string $definitionName, array $courseIds): int
    {
        $courseIds = array_values(array_filter(array_map('intval', $courseIds), static fn (int $i): bool => $i > 0));
        if ($courseIds === [] || $definitionId < 1) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($courseIds), '?'));
        try {
            $st = $this->pdo()->prepare(
                "UPDATE personnel_qualifications SET definition_id = ?, updated_at = ?
                 WHERE tenant_id = ? AND source = 'training' AND definition_id IS NULL AND training_course_id IN ({$in})"
            );
            $st->execute(array_merge([$definitionId, date('Y-m-d H:i:s'), $tenantId], $courseIds));

            return $st->rowCount();
        } catch (Throwable) {
            return 0;
        }
    }

    /** Attestations Academy rattachées à aucune qualification. */
    public function countUnlinkedAcademyAwards(int $tenantId): int
    {
        try {
            $st = $this->pdo()->prepare(
                "SELECT COUNT(*) FROM personnel_qualifications WHERE tenant_id = ? AND source = 'training' AND definition_id IS NULL AND training_course_id IS NOT NULL"
            );
            $st->execute([$tenantId]);

            return (int) $st->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /** Prérequis « obtention » de chaque qualification : qualification => liste des qualifications requises. @return array<int, list<int>> */
    public function prerequisitesByDefinition(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare(
                "SELECT qualification_id, required_qualification_id FROM qualification_prerequisites WHERE tenant_id = ? AND requirement_type = 'obtention'"
            );
            $st->execute([$tenantId]);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['qualification_id']][] = (int) $row['required_qualification_id'];
        }

        return $out;
    }

    /** Fonctions (emplois) tenues par chaque membre : membre => liste d'emplois. @return array<int, list<int>> */
    public function jobRolesByUser(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare('SELECT user_id, personnel_job_role_id FROM personnel_profile_job_roles WHERE tenant_id = ?');
            $st->execute([$tenantId]);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['user_id']][] = (int) $row['personnel_job_role_id'];
        }

        return $out;
    }

    /**
     * Valeur d'un critère mesuré pour chaque membre de la communauté (hors heures de vol, calculées à part).
     *
     * @return array<int, float> membre => valeur (heures, nombre, km…)
     */
    public function metricValues(int $tenantId, string $metric, string $scope, int $windowDays): array
    {
        $since = $windowDays > 0 ? date('Y-m-d H:i:s', time() - $windowDays * 86400) : null;
        $sinceDay = $since !== null ? substr($since, 0, 10) : null;
        $params = [$tenantId];
        $sql = '';
        $divide = 1.0;
        switch ($metric) {
            case 'playtime_hours':
                $col = match ($scope) {
                    'server' => 'server_seconds',
                    'zeus' => 'zeus_seconds',
                    default => 'total_seconds',
                };
                $sql = "SELECT user_id, {$col} FROM user_arma_playtime WHERE tenant_id = ?";
                $divide = 3600.0;
                break;
            case 'session_hours':
            case 'sessions_count':
                $agg = $metric === 'session_hours' ? 'SUM(m.validated_seconds)' : 'COUNT(*)';
                $sql = "SELECT m.user_id, {$agg} FROM roleplay_game_session_members m
                        JOIN roleplay_game_sessions s ON s.id = m.session_id AND s.tenant_id = m.tenant_id
                        WHERE m.tenant_id = ? AND m.rh_excluded = 0 AND m.attendance_valid = 1";
                if ($metric === 'session_hours' && $scope !== '') {
                    $sql .= ' AND s.hour_category = ?';
                    $params[] = $scope;
                }
                if ($since !== null) {
                    $sql .= ' AND COALESCE(s.started_at, s.created_at) >= ?';
                    $params[] = $since;
                }
                $sql .= ' GROUP BY m.user_id';
                $divide = $metric === 'session_hours' ? 3600.0 : 1.0;
                break;
            case 'missions_played':
            case 'halo_jumps':
            case 'foot_km':
            case 'ground_vehicle_hours':
            case 'boat_hours':
            case 'air_hours':
                [$agg, $divide] = match ($metric) {
                    'missions_played' => ['COUNT(*)', 1.0],
                    'halo_jumps' => ['SUM(halo_jumps)', 1.0],
                    'foot_km' => ['SUM(foot_m)', 1000.0],
                    'ground_vehicle_hours' => ['SUM(land_s)', 3600.0],
                    'boat_hours' => ['SUM(sea_s)', 3600.0],
                    default => ['SUM(air_s)', 3600.0],
                };
                $sql = "SELECT user_id, {$agg} FROM atak_distance_stats WHERE tenant_id = ?";
                if ($sinceDay !== null) {
                    $sql .= ' AND day >= ?';
                    $params[] = $sinceDay;
                }
                $sql .= ' GROUP BY user_id';
                break;
            case 'events_attended':
                $sql = 'SELECT r.user_id, COUNT(*) FROM community_event_rsvps r
                        JOIN community_events e ON e.id = r.event_id
                        WHERE e.tenant_id = ? AND r.checked_in_at IS NOT NULL AND e.cancelled_at IS NULL';
                if ($scope !== '') {
                    $sql .= ' AND e.event_type = ?';
                    $params[] = $scope;
                }
                if ($since !== null) {
                    $sql .= ' AND r.checked_in_at >= ?';
                    $params[] = $since;
                }
                $sql .= ' GROUP BY r.user_id';
                break;
            case 'aar_written':
                $sql = "SELECT author_user_id, COUNT(*) FROM aar_reports
                        WHERE tenant_id = ? AND status = 'validated' AND author_user_id IS NOT NULL";
                if ($since !== null) {
                    $sql .= ' AND COALESCE(validated_at, reported_at, created_at) >= ?';
                    $params[] = $since;
                }
                $sql .= ' GROUP BY author_user_id';
                break;
            case 'seniority_days':
                return $this->seniorityDays($tenantId);
            case 'item_days':
                if ($scope === '') {
                    return [];
                }
                $sql = 'SELECT u.id, COUNT(DISTINCT g.usage_day) FROM game_item_usage g
                        JOIN users u ON u.steam_id = g.steam_id
                        WHERE g.tenant_id = ?';
                if (str_starts_with($scope, 'kind:')) {
                    $sql .= ' AND g.kind = ?';
                    $params[] = substr($scope, 5);
                } else {
                    $sql .= ' AND g.class_name = ?';
                    $params[] = substr($scope, 6);
                }
                if ($sinceDay !== null) {
                    $sql .= ' AND g.usage_day >= ?';
                    $params[] = $sinceDay;
                }
                $sql .= ' GROUP BY u.id';
                break;
            default:
                return [];
        }
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_NUM) ?: [];
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r[0]] = round(((float) $r[1]) / $divide, 2);
        }

        return $out;
    }

    /**
     * Ancienneté de chaque membre en jours : depuis la date d’engagement de son dossier, sinon depuis son arrivée dans
     * la communauté (adhésion d’un membre venu d’une autre communauté, sinon création du compte).
     *
     * @return array<int, float>
     */
    public function seniorityDays(int $tenantId, ?int $now = null): array
    {
        $now ??= time();
        $queries = [
            ['SELECT u.id, pp.enlistment_date, m.joined_at, u.created_at FROM users u
             LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
             LEFT JOIN user_community_memberships m ON m.user_id = u.id AND m.tenant_id = ?
             WHERE u.tenant_id = ? OR m.tenant_id IS NOT NULL', [$tenantId, $tenantId]],
            ['SELECT u.id, pp.enlistment_date, NULL, u.created_at FROM users u
             LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
             WHERE u.tenant_id = ?', [$tenantId]],
        ];
        $rows = null;
        foreach ($queries as [$sql, $params]) {
            try {
                $st = $this->pdo()->prepare($sql);
                $st->execute($params);
                $rows = $st->fetchAll(PDO::FETCH_NUM) ?: [];
                break;
            } catch (Throwable) {
            }
        }
        $out = [];
        foreach ($rows ?? [] as $r) {
            $days = self::daysSince([$r[1] ?? null, $r[2] ?? null, $r[3] ?? null], $now);
            if ($days !== null) {
                $out[(int) $r[0]] = (float) $days;
            }
        }

        return $out;
    }

    /** Jours écoulés depuis la première date valide de la liste (null : aucune). @param list<mixed> $dates */
    public static function daysSince(array $dates, int $now): ?int
    {
        foreach ($dates as $d) {
            $d = trim((string) $d);
            if ($d === '' || str_starts_with($d, '0000')) {
                continue;
            }
            $ts = strtotime(substr($d, 0, 10));
            if ($ts !== false && $ts <= $now) {
                return (int) floor(($now - $ts) / 86400);
            }
        }

        return null;
    }

    /* ——————————————————— Conditions attestées par un instructeur ——————————————————— */

    public function attestationsReady(): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM qualification_attestations LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Attestations de la communauté : membre => clé (QualificationCriteriaCatalog::attestationStoreKey) => attestation.
     *
     * @return array<int, array<string, array{value: float, note: string, attested_by: int, attested_at: string}>>
     */
    public function attestationsByUser(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare('SELECT user_id, attestation_key, value, note, attested_by, attested_at FROM qualification_attestations WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['user_id']][(string) $r['attestation_key']] = [
                'value' => (float) $r['value'],
                'note' => (string) ($r['note'] ?? ''),
                'attested_by' => (int) ($r['attested_by'] ?? 0),
                'attested_at' => (string) ($r['attested_at'] ?? ''),
            ];
        }

        return $out;
    }

    /** Enregistre (ou remplace) l’attestation d’un membre. */
    public function saveAttestation(int $tenantId, int $userId, string $key, float $value, string $note, ?int $actorId): void
    {
        $params = [$tenantId, $userId, mb_substr($key, 0, 120), round(max(0.0, $value), 2), mb_substr(trim($note), 0, 255), $actorId, date('Y-m-d H:i:s')];
        $this->pdo()->prepare('DELETE FROM qualification_attestations WHERE tenant_id = ? AND user_id = ? AND attestation_key = ?')->execute(array_slice($params, 0, 3));
        $this->pdo()->prepare('INSERT INTO qualification_attestations (tenant_id, user_id, attestation_key, value, note, attested_by, attested_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute($params);
    }

    public function deleteAttestation(int $tenantId, int $userId, string $key): void
    {
        $this->pdo()->prepare('DELETE FROM qualification_attestations WHERE tenant_id = ? AND user_id = ? AND attestation_key = ?')->execute([$tenantId, $userId, $key]);
    }

    /** Formations de la communauté (pour le choix et les libellés). @return array<int, string> */
    public function courseTitles(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare('SELECT id, title FROM training_courses WHERE tenant_id = ? ORDER BY title ASC');
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['id']] = trim((string) $r['title']) ?: ('#' . (int) $r['id']);
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /** Appareils pilotés dans la communauté (classe, nom, type). @return list<array{vehicle_class: string, vehicle_label: string, vehicle_kind: string, seconds: int}> */
    public function aircraftSeen(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT vehicle_class, MAX(vehicle_label) AS vehicle_label, MAX(vehicle_kind) AS vehicle_kind, SUM(seconds) AS seconds
                 FROM atak_pilot_time WHERE tenant_id = ? GROUP BY vehicle_class ORDER BY seconds DESC LIMIT 300'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** Objets vus en jeu dans la communauté, les plus récents d'abord. @return list<array{class_name: string, name: string, kind: string}> */
    public function itemsSeen(int $tenantId, int $limit = 600): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT class_name, name, kind FROM game_item_catalog WHERE tenant_id = ? ORDER BY last_seen DESC LIMIT ' . max(1, min(2000, $limit))
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** Catégories d'heures des sessions de jeu. @return list<string> */
    public function sessionCategories(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare(
                "SELECT DISTINCT hour_category FROM roleplay_game_sessions WHERE tenant_id = ? AND hour_category IS NOT NULL AND hour_category <> '' ORDER BY hour_category"
            );
            $st->execute([$tenantId]);

            return array_values(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Historique des attributions d'une qualification, le plus récent d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function historyForDefinition(int $tenantId, int $definitionId, int $limit = 60): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT h.*, pq.user_id, u.display_name, u.callsign, p.display_name AS performer_name
                 FROM qualification_history h
                 JOIN personnel_qualifications pq ON pq.id = h.personnel_qualification_id
                 LEFT JOIN users u ON u.id = pq.user_id
                 LEFT JOIN users p ON p.id = h.performed_by
                 WHERE h.tenant_id = ? AND pq.definition_id = ?
                 ORDER BY h.event_date DESC, h.id DESC LIMIT ' . max(1, min(500, $limit))
            );
            $st->execute([$tenantId, $definitionId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** Qualifications dont l'affichage est coupé sur la page publique ou la fiche opérateur. @return list<int> */
    public function hiddenDefinitionIds(int $tenantId, string $column): array
    {
        if (!in_array($column, ['show_on_profile', 'show_on_orbat'], true) || !$this->schemaReady()) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare("SELECT definition_id FROM qualification_award_rules WHERE tenant_id = ? AND {$column} = 0");
            $st->execute([$tenantId]);

            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable) {
            return [];
        }
    }
}
