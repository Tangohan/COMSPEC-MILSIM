<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PersonnelAdminDataRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** Données admin par user : [ panel_id => [ data... ] ] */
    public function getAllForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT panel_id, data FROM personnel_admin_data WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $panelId = (int) $row['panel_id'];
            $out[$panelId] = $row['data'] !== null ? (json_decode($row['data'], true) ?? []) : [];
        }
        return $out;
    }

    public function getForUserAndPanel(int $userId, int $panelId): array
    {
        $stmt = $this->pdo->prepare('SELECT data FROM personnel_admin_data WHERE user_id = ? AND panel_id = ? LIMIT 1');
        $stmt->execute([$userId, $panelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['data'] === null) {
            return [];
        }
        return json_decode($row['data'], true) ?? [];
    }

    public function setForUserAndPanel(int $userId, int $panelId, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_admin_data (user_id, panel_id, data, updated_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()'
        );
        $stmt->execute([$userId, $panelId, $json]);
    }
}
