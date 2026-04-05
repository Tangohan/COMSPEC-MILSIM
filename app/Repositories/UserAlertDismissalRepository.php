<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserAlertDismissalRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * @param 'platform'|'tenant' $scope
     */
    public function dismiss(int $userId, string $scope, int $alertId): void
    {
        try {
            $stmt = $this->pdo->prepare('INSERT IGNORE INTO user_alert_dismissals (user_id, scope, alert_id, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$userId, $scope, $alertId]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return;
            }
            throw $e;
        }
    }

    /**
     * @param list<int> $platformIds
     * @param list<int> $tenantIds
     * @return array{platform: array<int, true>, tenant: array<int, true>}
     */
    public function dismissedSetsForUser(int $userId, array $platformIds, array $tenantIds): array
    {
        $out = ['platform' => [], 'tenant' => []];
        if ($userId <= 0) {
            return $out;
        }
        try {
            if ($platformIds !== []) {
                $ph = implode(',', array_fill(0, count($platformIds), '?'));
                $params = array_merge([$userId, 'platform'], $platformIds);
                $stmt = $this->pdo->prepare("SELECT alert_id FROM user_alert_dismissals WHERE user_id = ? AND scope = ? AND alert_id IN ($ph)");
                $stmt->execute($params);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $out['platform'][(int) $row['alert_id']] = true;
                }
            }
            if ($tenantIds !== []) {
                $ph = implode(',', array_fill(0, count($tenantIds), '?'));
                $params = array_merge([$userId, 'tenant'], $tenantIds);
                $stmt = $this->pdo->prepare("SELECT alert_id FROM user_alert_dismissals WHERE user_id = ? AND scope = ? AND alert_id IN ($ph)");
                $stmt->execute($params);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $out['tenant'][(int) $row['alert_id']] = true;
                }
            }
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return $out;
            }
            throw $e;
        }

        return $out;
    }
}
