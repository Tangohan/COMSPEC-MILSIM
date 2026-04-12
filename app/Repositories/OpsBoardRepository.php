<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class OpsBoardRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function expirePastItems(int $tenantId): void
    {
        try {
            $sql = "UPDATE ops_board_items
                SET status = 'expired', updated_at = NOW()
                WHERE tenant_id = :tenant_id
                  AND status = 'published'
                  AND end_date IS NOT NULL
                  AND end_date < NOW()";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['tenant_id' => $tenantId]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return;
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listBoardItemsForTenant(int $tenantId, array $filters = []): array
    {
        try {
            $where = ['obi.tenant_id = :tenant_id', "obi.status IN ('published','draft')"];
            $params = ['tenant_id' => $tenantId];

            if (!empty($filters['unit_id'])) {
                $where[] = '(obi.unit_id = :unit_id OR obi.unit_id IS NULL)';
                $params['unit_id'] = (int) $filters['unit_id'];
            }
            if (!empty($filters['block_type'])) {
                $where[] = 'obi.block_type = :block_type';
                $params['block_type'] = (string) $filters['block_type'];
            }
            if (!empty($filters['visibility_level'])) {
                $where[] = 'obi.visibility_level = :visibility_level';
                $params['visibility_level'] = (string) $filters['visibility_level'];
            }
            if (!empty($filters['priority'])) {
                $where[] = 'obi.priority = :priority';
                $params['priority'] = (string) $filters['priority'];
            }

            $periodStart = trim((string) ($filters['period_start'] ?? ''));
            $periodEnd = trim((string) ($filters['period_end'] ?? ''));
            if ($periodStart !== '') {
                $where[] = '(obi.end_date IS NULL OR obi.end_date >= :period_start)';
                $params['period_start'] = $periodStart . ' 00:00:00';
            }
            if ($periodEnd !== '') {
                $where[] = '(obi.start_date IS NULL OR obi.start_date <= :period_end)';
                $params['period_end'] = $periodEnd . ' 23:59:59';
            }

            $sql = 'SELECT obi.*,
                    u.name AS unit_name,
                    creator.display_name AS created_by_name,
                    (
                        SELECT COUNT(*)
                        FROM ops_board_assignments oba
                        WHERE oba.item_id = obi.id
                    ) AS assignment_count,
                    (
                        SELECT GROUP_CONCAT(DISTINCT CONCAT(COALESCE(oba.role_label, "Personnel"), ": ", COALESCE(assignee.display_name, CONCAT("#", oba.user_id))) ORDER BY oba.is_lead DESC, oba.id ASC SEPARATOR " | ")
                        FROM ops_board_assignments oba
                        LEFT JOIN users assignee ON assignee.id = oba.user_id
                        WHERE oba.item_id = obi.id
                    ) AS assignment_summary
                FROM ops_board_items obi
                LEFT JOIN units u ON u.id = obi.unit_id
                LEFT JOIN users creator ON creator.id = obi.created_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY obi.is_pinned DESC,
                         FIELD(obi.priority, "critical", "high", "normal", "low") ASC,
                         obi.display_order ASC,
                         COALESCE(obi.start_date, obi.created_at) DESC,
                         obi.id DESC';

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }
}
