<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\AtakMarkerDetection;
use App\Support\LazyDatabaseConnection;
use PDO;

final class AtakMarkerDetectionRuleRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function tablesReady(): bool
    {
        try {
            if ($this->tableExists()) {
                return true;
            }
            $migration = dirname(__DIR__, 2) . '/bootstrap/atak_marker_detection_rules_migration.php';
            if (is_file($migration)) {
                require_once $migration;
                if (function_exists('run_atak_marker_detection_rules_migration')) {
                    run_atak_marker_detection_rules_migration($this->pdo());
                }
            }

            return $this->tableExists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function tableExists(): bool
    {
        $st = $this->pdo()->query(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_marker_detection_rules' LIMIT 1"
        );

        return (bool) $st?->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, bool $enabledOnly = false): array
    {
        if ($tenantId < 1 || !$this->tablesReady()) {
            return [];
        }
        $sql = 'SELECT * FROM atak_marker_detection_rules WHERE tenant_id = ?';
        if ($enabledOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY position ASC, id ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$tenantId]);
        $rows = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $this->normalizeRow(is_array($row) ? $row : []);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEnabled(int $tenantId): array
    {
        return $this->listForTenant($tenantId, true);
    }

    public function countForTenant(int $tenantId): int
    {
        if ($tenantId < 1 || !$this->tablesReady()) {
            return 0;
        }
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM atak_marker_detection_rules WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO atak_marker_detection_rules
                (tenant_id, label, match_mode, match_value, radius_m, confirm_arrival, notify_web, notify_atak, is_active, position)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $data['label'],
            $data['match_mode'],
            $data['match_value'],
            $data['radius_m'],
            !empty($data['confirm_arrival']) ? 1 : 0,
            !empty($data['notify_web']) ? 1 : 0,
            !empty($data['notify_atak']) ? 1 : 0,
            !empty($data['is_active']) ? 1 : 0,
            (int) ($data['position'] ?? 1),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $tenantId, int $id, array $data): bool
    {
        $st = $this->pdo()->prepare(
            'UPDATE atak_marker_detection_rules SET
                label = ?, match_mode = ?, match_value = ?, radius_m = ?,
                confirm_arrival = ?, notify_web = ?, notify_atak = ?, is_active = ?
             WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([
            $data['label'],
            $data['match_mode'],
            $data['match_value'],
            $data['radius_m'],
            !empty($data['confirm_arrival']) ? 1 : 0,
            !empty($data['notify_web']) ? 1 : 0,
            !empty($data['notify_atak']) ? 1 : 0,
            !empty($data['is_active']) ? 1 : 0,
            $tenantId,
            $id,
        ]);

        return $st->rowCount() > 0;
    }

    public function delete(int $tenantId, int $id): bool
    {
        $st = $this->pdo()->prepare('DELETE FROM atak_marker_detection_rules WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['tenant_id'] = (int) ($row['tenant_id'] ?? 0);
        $row['match_mode'] = AtakMarkerDetection::normalizeMode((string) ($row['match_mode'] ?? ''));
        $row['radius_m'] = AtakMarkerDetection::normalizeRadius((int) ($row['radius_m'] ?? 20));
        $row['confirm_arrival'] = !empty($row['confirm_arrival']);
        $row['notify_web'] = !empty($row['notify_web']);
        $row['notify_atak'] = !empty($row['notify_atak']);
        $row['is_active'] = !empty($row['is_active']);
        $row['position'] = (int) ($row['position'] ?? 1);

        return $row;
    }
}
