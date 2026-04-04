<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class CasNineLineRepository
{
    private const VALID_STATUSES = [
        'DRAFT', 'SUBMITTED', 'ACKNOWLEDGED', 'CHECKING', 'TARGET_ACQUIRED',
        'INBOUND', 'CLEARED_HOT', 'ENGAGED', 'BDA_PENDING', 'COMPLETE', 'ABORTED',
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listCas(int $tenantId, int $mapId, ?string $assignedTo = null, ?string $status = null): array
    {
        $sql = 'SELECT * FROM atak_nine_line WHERE tenant_id = ? AND map_id = ?';
        $params = [$tenantId, $mapId];
        if ($assignedTo !== null && $assignedTo !== '') {
            $sql .= ' AND assigned_aircraft = ?';
            $params[] = $assignedTo;
        }
        if ($status !== null && $status !== '') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY updated_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'normalizeCasRow'], $rows);
    }

    public function getCas(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_nine_line WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeCasRow($row) : null;
    }

    public function createCas(int $tenantId, int $mapId, string $author, array $payload): array
    {
        $missionId = $payload['missionId'] ?? $payload['mission_id'] ?? null;
        $assignedAircraft = $payload['assigned_aircraft'] ?? $payload['assignedAircraft'] ?? null;
        $lines = $payload['lines'] ?? $payload;
        $line1 = $lines['line1'] ?? $payload['line1'] ?? '';
        $line2 = $lines['line2'] ?? $payload['line2'] ?? '';
        $line3 = $lines['line3'] ?? $payload['line3'] ?? '';
        $line4 = $lines['line4'] ?? $payload['line4'] ?? '';
        $line5 = $lines['line5'] ?? $payload['line5'] ?? '';
        $line6 = $lines['line6'] ?? $payload['line6'] ?? '';
        $line7 = $lines['line7'] ?? $payload['line7'] ?? '';
        $line8 = $lines['line8'] ?? $payload['line8'] ?? '';
        $line9 = $lines['line9'] ?? $payload['line9'] ?? '';
        $status = 'SUBMITTED';

        $stmt = $this->pdo->prepare(
            'INSERT INTO atak_nine_line (tenant_id, map_id, mission_id, author, assigned_aircraft, line1, line2, line3, line4, line5, line6, line7, line8, line9, lines_checked, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)'
        );
        $stmt->execute([
            $tenantId, $mapId, $missionId, $author, $assignedAircraft,
            $line1, $line2, $line3, $line4, $line5, $line6, $line7, $line8, $line9,
            $status,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->getCas($tenantId, $id);
        return $row ?? [];
    }

    public function updateCasStatus(int $tenantId, int $id, string $status): ?array
    {
        $status = strtoupper($status);
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM atak_nine_line WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        if (!$stmt->fetch()) {
            return null;
        }
        $this->pdo->prepare('UPDATE atak_nine_line SET status = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?')->execute([$status, $tenantId, $id]);
        return $this->getCas($tenantId, $id);
    }

    public function ackCas(int $tenantId, int $id): ?array
    {
        return $this->updateCasStatus($tenantId, $id, 'ACKNOWLEDGED');
    }

    public function updateLineChecked(int $tenantId, int $id, string $lineKey, bool $checked, string $checkedBy): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, lines_checked FROM atak_nine_line WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $linesChecked = $row['lines_checked'] ? json_decode($row['lines_checked'], true) : [];
        if (!is_array($linesChecked)) {
            $linesChecked = [];
        }
        $linesChecked[$lineKey] = [
            'checked' => $checked,
            'checkedBy' => $checkedBy,
            'checkedAt' => time(),
        ];
        $json = json_encode($linesChecked);
        $this->pdo->prepare('UPDATE atak_nine_line SET lines_checked = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?')->execute([$json, $tenantId, $id]);
        return $this->getCas($tenantId, $id);
    }

    public function assignAircraft(int $tenantId, int $id, string $callsign): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM atak_nine_line WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        if (!$stmt->fetch()) {
            return null;
        }
        $this->pdo->prepare('UPDATE atak_nine_line SET assigned_aircraft = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?')->execute([$callsign, $tenantId, $id]);
        return $this->getCas($tenantId, $id);
    }

    public function patchCas(int $tenantId, int $id, array $payload): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_nine_line WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (isset($payload['status'])) {
            $this->updateCasStatus($tenantId, $id, $payload['status']);
        }
        if (isset($payload['assigned_aircraft']) || isset($payload['assignedAircraft'])) {
            $this->assignAircraft($tenantId, $id, $payload['assigned_aircraft'] ?? $payload['assignedAircraft']);
        }
        if (isset($payload['lines_checked'])) {
            $json = is_string($payload['lines_checked']) ? $payload['lines_checked'] : json_encode($payload['lines_checked']);
            $this->pdo->prepare('UPDATE atak_nine_line SET lines_checked = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?')->execute([$json, $tenantId, $id]);
        }
        return $this->getCas($tenantId, $id);
    }

    private function normalizeCasRow(array $row): array
    {
        $linesChecked = $row['lines_checked'] ?? null;
        if (is_string($linesChecked)) {
            $decoded = json_decode($linesChecked, true);
            $row['lines_checked'] = is_array($decoded) ? $decoded : [];
        }
        $row['missionId'] = $row['mission_id'] ?? null;
        $row['assignedAircraft'] = $row['assigned_aircraft'] ?? null;
        return $row;
    }
}
