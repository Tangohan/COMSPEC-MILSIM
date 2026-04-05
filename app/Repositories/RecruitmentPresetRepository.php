<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RecruitmentPresetRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, label, payload, created_at, updated_at FROM recruitment_presets WHERE user_id = ? ORDER BY updated_at DESC, id DESC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['payload'] = $this->decodePayload($r['payload'] ?? null);
        }

        return $rows;
    }

    public function findForUser(int $presetId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, label, payload, created_at, updated_at FROM recruitment_presets WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$presetId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['payload'] = $this->decodePayload($row['payload'] ?? null);

        return $row;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function create(int $userId, string $label, array $payload): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recruitment_presets (user_id, label, payload, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$userId, $label, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function update(int $presetId, int $userId, string $label, array $payload): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE recruitment_presets SET label = ?, payload = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$label, json_encode($payload, JSON_UNESCAPED_UNICODE), $presetId, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $presetId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM recruitment_presets WHERE id = ? AND user_id = ?');
        $stmt->execute([$presetId, $userId]);

        return $stmt->rowCount() > 0;
    }

    private function decodePayload(mixed $raw): array
    {
        if (is_string($raw)) {
            $d = json_decode($raw, true);
            return is_array($d) ? $d : [];
        }
        if (is_array($raw)) {
            return $raw;
        }

        return [];
    }
}
