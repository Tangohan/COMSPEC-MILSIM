<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Core\Database;
use App\Repositories\PersonnelAbsenceRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PersonnelQualificationRepository;
use PDO;

/**
 * Agrège les alertes RH du bureau effectifs (qualifs, absences, inactivité, mobilité, postes).
 */
final class RhAlertAggregatorService
{
    public const INACTIVITY_DAYS = 45;

    public const PROLONGED_ABSENCE_DAYS = 14;

    private PDO $pdo;

    public function __construct(
        private ?PersonnelQualificationRepository $qualifications = null,
        private ?PersonnelAbsenceRepository $absences = null,
        private ?PersonnelMobilityRequestRepository $mobility = null,
        ?PDO $pdo = null,
    ) {
        $this->qualifications ??= new PersonnelQualificationRepository();
        $this->absences ??= new PersonnelAbsenceRepository();
        $this->mobility ??= new PersonnelMobilityRequestRepository();
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * @return array{
     *   items: list<array{id:string,severity:string,label:string,count:int,href:string,tone:string}>,
     *   total: int
     * }
     */
    public function summarize(int $tenantId): array
    {
        $items = [];

        $qualifCount = 0;
        try {
            $qualifCount = count($this->qualifications->listExpiringForTenant($tenantId, 60, 300));
        } catch (\Throwable) {
            $qualifCount = 0;
        }
        $items[] = [
            'id' => 'qualif_expiring',
            'severity' => 'Qualifications',
            'label' => 'Qualification expirant sous 60 j',
            'count' => $qualifCount,
            'href' => effectifs_workspace_url('qualifications'),
            'tone' => $qualifCount > 0 ? 'warn' : 'ok',
        ];

        $absenceCount = $this->countProlongedAbsences($tenantId);
        $items[] = [
            'id' => 'prolonged_absence',
            'severity' => 'Disponibilité',
            'label' => 'Absence prolongée (≥ ' . self::PROLONGED_ABSENCE_DAYS . ' j)',
            'count' => $absenceCount,
            'href' => effectifs_workspace_url('alertes'),
            'tone' => $absenceCount > 0 ? 'warn' : 'ok',
        ];

        $inactiveCount = $this->countInactiveMembers($tenantId, self::INACTIVITY_DAYS);
        $items[] = [
            'id' => 'inactive_members',
            'severity' => 'Activité',
            'label' => 'Sans activité depuis ' . self::INACTIVITY_DAYS . ' j',
            'count' => $inactiveCount,
            'href' => effectifs_workspace_url('alertes'),
            'tone' => $inactiveCount > 0 ? 'warn' : 'ok',
        ];

        $mobilityPending = 0;
        try {
            $mobilityPending = $this->mobility->countPending($tenantId);
        } catch (\Throwable) {
            $mobilityPending = 0;
        }
        $items[] = [
            'id' => 'mobility_pending',
            'severity' => 'Mobilité',
            'label' => 'Demande de mobilité en attente',
            'count' => $mobilityPending,
            'href' => effectifs_workspace_url('mobilite'),
            'tone' => $mobilityPending > 0 ? 'info' : 'ok',
        ];

        $vacantBillets = $this->countVacantBillets($tenantId);
        $items[] = [
            'id' => 'vacant_billets',
            'severity' => 'Organigramme',
            'label' => 'Poste sous-pourvu',
            'count' => $vacantBillets,
            'href' => effectifs_workspace_url('alertes'),
            'tone' => $vacantBillets > 0 ? 'warn' : 'ok',
        ];

        $total = 0;
        foreach ($items as $item) {
            if (($item['tone'] ?? '') !== 'ok') {
                $total += (int) ($item['count'] ?? 0);
            }
        }

        return ['items' => $items, 'total' => $total];
    }

    public function countProlongedAbsences(int $tenantId): int
    {
        if (!$this->absences->tableExists() || $tenantId < 1) {
            return 0;
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM personnel_absences
                 WHERE tenant_id = ?
                   AND status = 'active'
                   AND cancelled_at IS NULL
                   AND starts_on <= CURDATE()
                   AND (ends_on IS NULL OR ends_on >= CURDATE())
                   AND DATEDIFF(CURDATE(), starts_on) >= ?"
            );
            $st->execute([$tenantId, self::PROLONGED_ABSENCE_DAYS]);

            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function countInactiveMembers(int $tenantId, int $days): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        $days = max(7, min(365, $days));
        try {
            $st = $this->pdo->prepare(
                "SELECT COUNT(*) FROM users u
                 WHERE u.tenant_id = ?
                   AND u.status = 'active'
                   AND (u.display_name IS NULL OR TRIM(u.display_name) <> 'Compte supprimé')
                   AND (
                     u.last_login_at IS NULL
                     OR u.last_login_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)
                   )"
            );
            $st->execute([$tenantId]);

            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInactiveMembers(int $tenantId, int $days = self::INACTIVITY_DAYS, int $limit = 40): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $days = max(7, min(365, $days));
        $limit = max(1, min(100, $limit));
        try {
            $st = $this->pdo->prepare(
                "SELECT u.id, u.display_name, u.email, u.callsign, u.last_login_at
                 FROM users u
                 WHERE u.tenant_id = ?
                   AND u.status = 'active'
                   AND (u.display_name IS NULL OR TRIM(u.display_name) <> 'Compte supprimé')
                   AND (
                     u.last_login_at IS NULL
                     OR u.last_login_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)
                   )
                 ORDER BY u.last_login_at IS NULL DESC, u.last_login_at ASC
                 LIMIT {$limit}"
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProlongedAbsences(int $tenantId, int $limit = 40): array
    {
        if (!$this->absences->tableExists() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        try {
            $st = $this->pdo->prepare(
                "SELECT a.*, u.display_name AS user_display_name, u.email AS user_email,
                        DATEDIFF(CURDATE(), a.starts_on) AS days_open
                 FROM personnel_absences a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.tenant_id = ?
                   AND a.status = 'active'
                   AND a.cancelled_at IS NULL
                   AND a.starts_on <= CURDATE()
                   AND (a.ends_on IS NULL OR a.ends_on >= CURDATE())
                   AND DATEDIFF(CURDATE(), a.starts_on) >= ?
                 ORDER BY a.starts_on ASC
                 LIMIT {$limit}"
            );
            $st->execute([$tenantId, self::PROLONGED_ABSENCE_DAYS]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function countVacantBillets(int $tenantId): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orbat_billets' LIMIT 1"
            );
            if (!$st || !$st->fetchColumn()) {
                return 0;
            }
            $q = $this->pdo->prepare(
                "SELECT COUNT(*) FROM orbat_billets b
                 WHERE b.tenant_id = ? AND b.is_active = 1
                   AND b.authorized_slots > (
                     SELECT COUNT(*) FROM orbat_billet_holders h
                     WHERE h.billet_id = b.id AND (h.ends_at IS NULL OR h.ends_at >= CURDATE())
                   )"
            );
            $q->execute([$tenantId]);

            return (int) $q->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
