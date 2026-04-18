<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ReplayRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function insertLog(string $missionId, string $unitId, string $callsign, ?string $unitType, ?string $side, float $posX, float $posY, ?float $posZ, ?float $heading, ?float $speed, ?string $stateJson): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO logs_positions (mission_id, unit_id, callsign, unit_type, side, pos_x, pos_y, pos_z, heading, speed, state_json, logged_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $missionId,
            $unitId,
            $callsign,
            $unitType,
            $side,
            $posX,
            $posY,
            $posZ ?? 0,
            $heading,
            $speed,
            $stateJson,
        ]);
    }

    /**
     * Get timeline of positions for mission between from and to (datetime strings or null for unbounded).
     */
    public function getTimeline(string $missionId, ?string $from, ?string $to, int $limit = 5000): array
    {
        $sql = 'SELECT id, unit_id, callsign, unit_type, side, pos_x, pos_y, pos_z, heading, speed, state_json, logged_at FROM logs_positions WHERE mission_id = ?';
        $params = [$missionId];
        if ($from !== null && $from !== '') {
            $sql .= ' AND logged_at >= ?';
            $params[] = $from;
        }
        if ($to !== null && $to !== '') {
            $sql .= ' AND logged_at <= ?';
            $params[] = $to;
        }
        $sql .= ' ORDER BY logged_at ASC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getIntelEvents(string $missionId, ?string $from, ?string $to, int $limit = 1000): array
    {
        $sql = 'SELECT id, source_callsign, report_type, target_type, pos_x, pos_y, pos_z, confidence_score, status, first_seen_at, last_seen_at, merged_count
                FROM intel_reports
                WHERE mission_id = ?';
        $params = [$missionId];
        if ($from !== null && $from !== '') {
            $sql .= ' AND last_seen_at >= ?';
            $params[] = $from;
        }
        if ($to !== null && $to !== '') {
            $sql .= ' AND first_seen_at <= ?';
            $params[] = $to;
        }
        $sql .= ' ORDER BY last_seen_at ASC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
