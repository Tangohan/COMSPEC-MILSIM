<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class IntelReportRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findCompatible(string $missionId, string $targetType, float $posX, float $posY, float $radiusMeters, int $windowSeconds): ?array
    {
        $posX = round($posX, 2);
        $posY = round($posY, 2);
        $stmt = $this->pdo->prepare(
            'SELECT * FROM intel_reports WHERE mission_id = ? AND target_type = ? AND status NOT IN (?, ?) AND last_seen_at >= DATE_SUB(NOW(), INTERVAL ? SECOND) ORDER BY last_seen_at DESC'
        );
        $stmt->execute([$missionId, $targetType, 'STALE', 'DISMISSED', $windowSeconds]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $radiusSq = $radiusMeters * $radiusMeters;
        foreach ($rows as $r) {
            $rx = round((float) $r['pos_x'], 2);
            $ry = round((float) $r['pos_y'], 2);
            $dx = $rx - $posX;
            $dy = $ry - $posY;
            if (($dx * $dx + $dy * $dy) <= $radiusSq) {
                return $r;
            }
        }
        return null;
    }

    public function create(string $missionId, array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO intel_reports (mission_id, source_callsign, report_type, target_type, pos_x, pos_y, pos_z, confidence_score, raw_payload_json, first_seen_at, last_seen_at, merged_count, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $rawJson = isset($data['raw_payload_json']) ? (is_string($data['raw_payload_json']) ? $data['raw_payload_json'] : json_encode($data['raw_payload_json'])) : null;
        $stmt->execute([
            $missionId,
            $data['source_callsign'] ?? null,
            $data['report_type'] ?? null,
            $data['target_type'] ?? 'UNKNOWN',
            (float) ($data['pos_x'] ?? 0),
            (float) ($data['pos_y'] ?? 0),
            (float) ($data['pos_z'] ?? 0),
            (int) ($data['confidence_score'] ?? 0),
            $rawJson,
            $now,
            $now,
            'TEMPORARY',
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->getById($id);
        return $row ?? [];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM intel_reports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['raw_payload_json'])) {
            $row['raw_payload_json'] = json_decode($row['raw_payload_json'], true);
        }
        return $row ?: null;
    }

    public function addEvent(int $intelReportId, ?string $sourceCallsign, $payloadJson): void
    {
        $payload = is_string($payloadJson) ? $payloadJson : json_encode($payloadJson);
        $stmt = $this->pdo->prepare('INSERT INTO intel_reports_events (intel_report_id, source_callsign, payload_json) VALUES (?, ?, ?)');
        $stmt->execute([$intelReportId, $sourceCallsign, $payload]);
    }

    public function mergeInto(int $id, string $sourceCallsign, $payloadJson): void
    {
        $this->pdo->prepare('UPDATE intel_reports SET last_seen_at = NOW(), merged_count = merged_count + 1, confidence_score = LEAST(100, confidence_score + 10), updated_at = NOW() WHERE id = ?')->execute([$id]);
        $this->addEvent($id, $sourceCallsign, $payloadJson);
        $row = $this->getById($id);
        if ($row) {
            $mergedCount = (int) $row['merged_count'];
            $status = $mergedCount >= 3 ? 'CONFIRMED' : ($mergedCount >= 2 ? 'CORROBORATED' : 'TEMPORARY');
            $this->pdo->prepare('UPDATE intel_reports SET status = ? WHERE id = ?')->execute([$status, $id]);
        }
    }

    public function listByMission(string $missionId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM intel_reports WHERE mission_id = ?';
        $params = [$missionId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY last_seen_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            if (!empty($r['raw_payload_json'])) {
                $r['raw_payload_json'] = json_decode($r['raw_payload_json'], true);
            }
        }
        return $rows;
    }

    public function markStale(int $id): void
    {
        $this->pdo->prepare('UPDATE intel_reports SET status = ? WHERE id = ?')->execute(['STALE', $id]);
    }
}
