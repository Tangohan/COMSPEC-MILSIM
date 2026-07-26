<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\C2PillarsSchema;
use PDO;

class IffAssetStatusRepository
{
    private PDO $pdo;

    public function __construct()
    {
        C2PillarsSchema::ensure();
        $this->pdo = Database::getPdo();
    }

    public function listByMission(string $missionId): array
    {
        $stmt = $this->pdo->prepare('SELECT a.*, c.code as challenge_code FROM iff_asset_status a LEFT JOIN iff_challenges c ON a.current_challenge_id = c.id WHERE a.mission_id = ? ORDER BY a.callsign');
        $stmt->execute([$missionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByAsset(string $missionId, string $assetId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT a.*, c.code as challenge_code, c.valid_until FROM iff_asset_status a LEFT JOIN iff_challenges c ON a.current_challenge_id = c.id WHERE a.mission_id = ? AND a.asset_id = ?');
        $stmt->execute([$missionId, $assetId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function upsert(string $missionId, string $assetId, string $callsign, ?string $platformType = null, ?int $challengeId = null): array
    {
        $stmt = $this->pdo->prepare('SELECT id, current_challenge_id FROM iff_asset_status WHERE mission_id = ? AND asset_id = ?');
        $stmt->execute([$missionId, $assetId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $prevChallenge = isset($existing['current_challenge_id']) ? (int) $existing['current_challenge_id'] : 0;
            $nextChallenge = $challengeId !== null ? (int) $challengeId : 0;
            if ($nextChallenge > 0 && $nextChallenge !== $prevChallenge) {
                $this->pdo->prepare('UPDATE iff_asset_status SET callsign = ?, platform_type = ?, current_challenge_id = ?, response_status = ?, response_code = NULL, responded_at = NULL, updated_at = NOW() WHERE id = ?')
                    ->execute([$callsign, $platformType, $challengeId, 'PENDING', $existing['id']]);
            } else {
                $this->pdo->prepare('UPDATE iff_asset_status SET callsign = ?, platform_type = ?, current_challenge_id = COALESCE(?, current_challenge_id), updated_at = NOW() WHERE id = ?')
                    ->execute([$callsign, $platformType, $challengeId, $existing['id']]);
            }
        } else {
            $this->pdo->prepare('INSERT INTO iff_asset_status (mission_id, asset_id, callsign, platform_type, current_challenge_id, response_status) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$missionId, $assetId, $callsign, $platformType, $challengeId, 'PENDING']);
        }
        $row = $this->getByAsset($missionId, $assetId);
        return $row ?? [];
    }

    public function setResponse(string $missionId, string $assetId, string $responseCode, string $responseStatus): bool
    {
        $stmt = $this->pdo->prepare('UPDATE iff_asset_status SET response_code = ?, response_status = ?, responded_at = NOW(), updated_at = NOW() WHERE mission_id = ? AND asset_id = ?');
        $stmt->execute([$responseCode, $responseStatus, $missionId, $assetId]);
        return $stmt->rowCount() > 0;
    }
}
