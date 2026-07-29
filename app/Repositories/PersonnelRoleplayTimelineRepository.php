<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\RoleplayBilanPolicy;
use PDO;

class PersonnelRoleplayTimelineRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'personnel_roleplay_timeline_events'");

        return (bool) ($stmt && $stmt->fetchColumn());
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 50): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT e.*, u.display_name AS actor_display_name, u.callsign AS actor_callsign
             FROM personnel_roleplay_timeline_events e
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.tenant_id = ? AND e.user_id = ?
             ORDER BY COALESCE(e.event_date, DATE(e.created_at)) DESC, e.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addEvent(
        int $tenantId,
        int $userId,
        string $eventType,
        string $title,
        ?string $detail,
        ?string $eventDate,
        ?string $dueDate,
        string $status,
        ?int $progressDelta,
        ?int $createdBy
    ): void {
        if (!$this->tableExists()) {
            return;
        }
        $evType = trim($eventType);
        $label = trim($title);
        if ($evType === '' || $label === '') {
            return;
        }
        $st = trim($status);
        if (!in_array($st, ['planned', 'completed', 'blocked', 'cancelled'], true)) {
            $st = 'planned';
        }
        $delta = $progressDelta;
        if ($delta !== null) {
            $delta = max(-100, min(100, $delta));
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_roleplay_timeline_events
            (tenant_id, user_id, event_type, title, detail, event_date, due_date, status, progress_delta, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $userId,
            $evType,
            $label,
            $detail !== null && trim($detail) !== '' ? trim($detail) : null,
            $eventDate !== null && trim($eventDate) !== '' ? trim($eventDate) : null,
            $dueDate !== null && trim($dueDate) !== '' ? trim($dueDate) : null,
            $st,
            $delta,
            $createdBy,
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listDashboardDueItems(int $tenantId, int $upcomingDays = 14, int $limit = 20): array
    {
        $upcomingDays = max(1, min(90, $upcomingDays));
        $limit = max(1, min(100, $limit));

        $rows = [];

        if ($this->tableExists()) {
            $stmt = $this->pdo->prepare(
                "SELECT 'timeline' AS source,
                        e.user_id,
                        COALESCE(NULLIF(TRIM(u.display_name), ''), NULLIF(TRIM(u.callsign), ''), NULLIF(TRIM(u.email), ''), CONCAT('Compte #', u.id)) AS member_label,
                        e.title AS item_label,
                        e.detail AS item_detail,
                        e.event_type,
                        e.status AS timeline_status,
                        DATE(e.due_date) AS due_date,
                        CASE
                            WHEN DATE(e.due_date) < CURDATE() THEN 'overdue'
                            ELSE 'upcoming'
                        END AS urgency
                 FROM personnel_roleplay_timeline_events e
                 INNER JOIN users u ON u.id = e.user_id AND u.tenant_id = e.tenant_id
                 WHERE e.tenant_id = ?
                   AND u.status = 'active'
                   AND e.due_date IS NOT NULL
                   AND TRIM(COALESCE(e.due_date, '')) <> ''
                   AND e.event_type NOT IN ('entretien', 'medical', 'rotation', 'bilan')
                   AND e.status NOT IN ('completed', 'cancelled')
                   AND DATE(e.due_date) <= DATE_ADD(CURDATE(), INTERVAL ? DAY)"
            );
            $stmt->execute([$tenantId, $upcomingDays]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $profileStmt = $this->pdo->prepare(
            "SELECT source,
                    user_id,
                    member_label,
                    item_label,
                    item_detail,
                    event_type,
                    timeline_status,
                    due_date,
                    urgency
             FROM (
                SELECT 'profile' AS source,
                       u.id AS user_id,
                       COALESCE(NULLIF(TRIM(u.display_name), ''), NULLIF(TRIM(u.callsign), ''), NULLIF(TRIM(u.email), ''), CONCAT('Compte #', u.id)) AS member_label,
                       'Entretien individuel' AS item_label,
                       'Entretien à planifier ou préparer.' AS item_detail,
                       'entretien' AS event_type,
                       'planned' AS timeline_status,
                       DATE(pp.rp_next_interview_date) AS due_date,
                       CASE
                           WHEN DATE(pp.rp_next_interview_date) < CURDATE() THEN 'overdue'
                           ELSE 'upcoming'
                       END AS urgency
                FROM users u
                INNER JOIN personnel_profiles pp ON pp.user_id = u.id
                WHERE u.tenant_id = ?
                  AND u.status = 'active'
                  AND pp.rp_next_interview_date IS NOT NULL
                  AND TRIM(COALESCE(pp.rp_next_interview_date, '')) <> ''
                  AND DATE(pp.rp_next_interview_date) <= DATE_ADD(CURDATE(), INTERVAL ? DAY)

                UNION ALL

                SELECT 'profile' AS source,
                       u.id AS user_id,
                       COALESCE(NULLIF(TRIM(u.display_name), ''), NULLIF(TRIM(u.callsign), ''), NULLIF(TRIM(u.email), ''), CONCAT('Compte #', u.id)) AS member_label,
                       'Visite medicale' AS item_label,
                       'Controle medical a prevoir.' AS item_detail,
                       'medical' AS event_type,
                       'planned' AS timeline_status,
                       DATE(pp.rp_medical_due_date) AS due_date,
                       CASE
                           WHEN DATE(pp.rp_medical_due_date) < CURDATE() THEN 'overdue'
                           ELSE 'upcoming'
                       END AS urgency
                FROM users u
                INNER JOIN personnel_profiles pp ON pp.user_id = u.id
                WHERE u.tenant_id = ?
                  AND u.status = 'active'
                  AND pp.rp_medical_due_date IS NOT NULL
                  AND TRIM(COALESCE(pp.rp_medical_due_date, '')) <> ''
                  AND DATE(pp.rp_medical_due_date) <= DATE_ADD(CURDATE(), INTERVAL ? DAY)

                UNION ALL

                SELECT 'profile' AS source,
                       u.id AS user_id,
                       COALESCE(NULLIF(TRIM(u.display_name), ''), NULLIF(TRIM(u.callsign), ''), NULLIF(TRIM(u.email), ''), CONCAT('Compte #', u.id)) AS member_label,
                       'Rotation de service' AS item_label,
                       'Rotation a organiser.' AS item_detail,
                       'rotation' AS event_type,
                       'planned' AS timeline_status,
                       DATE(pp.rp_service_rotation_date) AS due_date,
                       CASE
                           WHEN DATE(pp.rp_service_rotation_date) < CURDATE() THEN 'overdue'
                           ELSE 'upcoming'
                       END AS urgency
                FROM users u
                INNER JOIN personnel_profiles pp ON pp.user_id = u.id
                WHERE u.tenant_id = ?
                  AND u.status = 'active'
                  AND pp.rp_service_rotation_date IS NOT NULL
                  AND TRIM(COALESCE(pp.rp_service_rotation_date, '')) <> ''
                  AND DATE(pp.rp_service_rotation_date) <= DATE_ADD(CURDATE(), INTERVAL ? DAY)

                UNION ALL

                SELECT 'bilan' AS source,
                       u.id AS user_id,
                       COALESCE(NULLIF(TRIM(u.display_name), ''), NULLIF(TRIM(u.callsign), ''), NULLIF(TRIM(u.email), ''), CONCAT('Compte #', u.id)) AS member_label,
                       'Bilan roleplay' AS item_label,
                       'Revue periodique a effectuer.' AS item_detail,
                       'bilan' AS event_type,
                       'planned' AS timeline_status,
                       DATE_ADD(
                           COALESCE(pp.rp_last_review_at, u.created_at),
                           INTERVAL CASE
                               WHEN DATEDIFF(NOW(), u.created_at) < 365 THEN " . RoleplayBilanPolicy::FIRST_YEAR_INTERVAL_DAYS . "
                               WHEN DATEDIFF(NOW(), u.created_at) < 730 THEN " . RoleplayBilanPolicy::SECOND_YEAR_INTERVAL_DAYS . "
                               ELSE " . RoleplayBilanPolicy::ONGOING_INTERVAL_DAYS . "
                           END DAY
                       ) AS due_date,
                       CASE
                           WHEN DATE_ADD(
                               COALESCE(pp.rp_last_review_at, u.created_at),
                               INTERVAL CASE
                                   WHEN DATEDIFF(NOW(), u.created_at) < 365 THEN " . RoleplayBilanPolicy::FIRST_YEAR_INTERVAL_DAYS . "
                                   WHEN DATEDIFF(NOW(), u.created_at) < 730 THEN " . RoleplayBilanPolicy::SECOND_YEAR_INTERVAL_DAYS . "
                                   ELSE " . RoleplayBilanPolicy::ONGOING_INTERVAL_DAYS . "
                               END DAY
                           ) < DATE_SUB(NOW(), INTERVAL " . RoleplayBilanPolicy::OVERDUE_GRACE_DAYS . " DAY) THEN 'overdue'
                           ELSE 'upcoming'
                       END AS urgency
                FROM users u
                INNER JOIN personnel_profiles pp ON pp.user_id = u.id
                WHERE u.tenant_id = ?
                  AND u.status = 'active'
                  AND (
                      TRIM(COALESCE(pp.rp_followup_stage, '')) <> ''
                      OR pp.rp_tutor_user_id IS NOT NULL
                      OR TRIM(COALESCE(pp.rp_recruitment_stream, '')) <> ''
                      OR TRIM(COALESCE(pp.rp_operational_function, '')) <> ''
                      OR TRIM(COALESCE(pp.rp_recruitment_origin, '')) <> ''
                      OR pp.rp_last_review_at IS NOT NULL
                  )
                HAVING due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ) roleplay_due
             ORDER BY CASE WHEN urgency = 'overdue' THEN 0 ELSE 1 END, due_date ASC, member_label ASC"
        );
        $profileStmt->execute([
            $tenantId, $upcomingDays,
            $tenantId, $upcomingDays,
            $tenantId, $upcomingDays,
            $tenantId, $upcomingDays,
        ]);
        $rows = array_merge($rows, $profileStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        usort($rows, static function (array $a, array $b): int {
            $aUrgency = (string) ($a['urgency'] ?? '');
            $bUrgency = (string) ($b['urgency'] ?? '');
            if ($aUrgency !== $bUrgency) {
                return $aUrgency === 'overdue' ? -1 : 1;
            }

            $aDue = (string) ($a['due_date'] ?? '9999-12-31');
            $bDue = (string) ($b['due_date'] ?? '9999-12-31');
            if ($aDue !== $bDue) {
                return strcmp($aDue, $bDue);
            }

            return strcmp((string) ($a['member_label'] ?? ''), (string) ($b['member_label'] ?? ''));
        });

        return array_slice($rows, 0, $limit);
    }
}
