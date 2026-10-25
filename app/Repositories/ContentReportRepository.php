<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Signalements membres (fiches, photos de profil, visuels opérateur, formations, pages d’aide,
 * anomalies d’organisation) et leur historique de traitement.
 */
class ContentReportRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(
        int $tenantId,
        int $reporterId,
        string $reason,
        string $reportType = 'other',
        ?string $comment = null,
        ?string $reportedUrl = null,
        ?string $contentKind = null
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO content_reports (tenant_id, reporter_id, reason, report_type, comment, reported_url, content_kind, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', NOW())'
        );
        $stmt->execute([
            $tenantId,
            $reporterId,
            $reason,
            $reportType,
            $comment,
            $reportedUrl !== null && $reportedUrl !== '' ? $reportedUrl : null,
            $contentKind !== null && $contentKind !== '' ? $contentKind : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function countPending(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM content_reports WHERE tenant_id = ? AND status = ?');
        $stmt->execute([$tenantId, 'pending']);

        return (int) $stmt->fetchColumn();
    }

    /** Signalements encore ouverts, toutes communautés confondues. */
    public function countPendingAllTenants(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM content_reports WHERE status = 'pending'");

        return (int) $stmt->fetchColumn();
    }

    /**
     * Communautés avec le plus de signalements en attente (aperçu opérateur).
     *
     * @return list<array{tenant_id: int, pending: int, tenant_name: string|null}>
     */
    public function pendingCountTopTenants(int $limit): array
    {
        $limit = max(1, min(25, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT cr.tenant_id AS tenant_id, COUNT(*) AS pending, MAX(t.name) AS tenant_name
             FROM content_reports cr
             LEFT JOIN tenants t ON t.id = cr.tenant_id
             WHERE cr.status = \'pending\'
             GROUP BY cr.tenant_id
             ORDER BY pending DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $r): array {
            return [
                'tenant_id' => (int) ($r['tenant_id'] ?? 0),
                'pending' => (int) ($r['pending'] ?? 0),
                'tenant_name' => isset($r['tenant_name']) && $r['tenant_name'] !== '' && $r['tenant_name'] !== null
                    ? (string) $r['tenant_name']
                    : null,
            ];
        }, $rows);
    }

    public function listPending(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cr.*, u.display_name AS reporter_name, au.display_name AS assigned_to_name
             FROM content_reports cr
             LEFT JOIN users u ON u.id = cr.reporter_id
             LEFT JOIN users au ON au.id = cr.assigned_to
             WHERE cr.tenant_id = ? AND cr.status = \'pending\'
             ORDER BY cr.created_at DESC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listHandled(int $tenantId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cr.*, u.display_name AS reporter_name, hu.display_name AS handled_by_name, au.display_name AS assigned_to_name
             FROM content_reports cr
             LEFT JOIN users u ON u.id = cr.reporter_id
             LEFT JOIN users hu ON hu.id = cr.handled_by
             LEFT JOIN users au ON au.id = cr.assigned_to
             WHERE cr.tenant_id = ? AND cr.status = \'handled\'
             ORDER BY cr.handled_at DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Remet un dossier clos dans la file d’attente (statut pending).
     */
    public function reopenToPending(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE content_reports
             SET status = 'pending', handled_by = NULL, handled_at = NULL, assigned_to = NULL, assigned_at = NULL
             WHERE id = ? AND tenant_id = ? AND status = 'handled'"
        );
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function markHandled(int $id, int $tenantId, int $handledBy): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE content_reports
             SET status = \'handled\', handled_by = ?, handled_at = NOW(), assigned_to = NULL, assigned_at = NULL
             WHERE id = ? AND tenant_id = ?'
        );

        return $stmt->execute([$handledBy, $id, $tenantId]);
    }

    public function assignTo(int $id, int $tenantId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE content_reports
             SET assigned_to = ?, assigned_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = \'pending\''
        );

        return $stmt->execute([$userId, $id, $tenantId]);
    }

    public function unassign(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE content_reports
             SET assigned_to = NULL, assigned_at = NULL
             WHERE id = ? AND tenant_id = ? AND status = \'pending\''
        );

        return $stmt->execute([$id, $tenantId]);
    }

    public function saveResolution(int $id, int $tenantId, string $followUp, string $note): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE content_reports
             SET last_follow_up_action = ?, resolution_note = ?
             WHERE id = ? AND tenant_id = ?'
        );

        return $stmt->execute([$followUp !== '' ? $followUp : null, $note !== '' ? $note : null, $id, $tenantId]);
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM content_reports WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Dossier signalement enrichi (mêmes jointures que la file / l’historique console).
     */
    public function findEnrichedForConsole(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cr.*, u.display_name AS reporter_name, hu.display_name AS handled_by_name, au.display_name AS assigned_to_name
             FROM content_reports cr
             LEFT JOIN users u ON u.id = cr.reporter_id
             LEFT JOIN users hu ON hu.id = cr.handled_by
             LEFT JOIN users au ON au.id = cr.assigned_to
             WHERE cr.id = ? AND cr.tenant_id = ?
             LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Nombre total de signalements déposés par un membre sur cette communauté. */
    public function countReportsFiledByReporter(int $tenantId, int $reporterId): int
    {
        if ($reporterId < 1) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM content_reports WHERE tenant_id = ? AND reporter_id = ?');
        $stmt->execute([$tenantId, $reporterId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Signalements de ce membre ayant abouti à une sanction (dossiers clos uniquement).
     */
    public function countReportsFiledWithSubstantiveOutcome(int $tenantId, int $reporterId): int
    {
        if ($reporterId < 1) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM content_reports
             WHERE tenant_id = ? AND reporter_id = ? AND status = 'handled'
               AND last_follow_up_action LIKE 'sanction\\_%'"
        );
        $stmt->execute([$tenantId, $reporterId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Signalements dont le motif (fiche, photo, dossier) mentionne explicitement ce compte.
     */
    public function countProfileStyleReportsMentioningUser(int $tenantId, int $userId): int
    {
        if ($userId < 1) {
            return 0;
        }
        $like1 = '%compte n° ' . $userId . '%';
        $like2 = '%(n° ' . $userId . ')%';
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM content_reports
             WHERE tenant_id = ? AND (reason LIKE ? OR reason LIKE ?)'
        );
        $stmt->execute([$tenantId, $like1, $like2]);

        return (int) $stmt->fetchColumn();
    }

    public function addTimelineEvent(
        int $tenantId,
        int $reportId,
        ?int $actorUserId,
        string $eventType,
        string $eventLabel,
        ?string $eventDetail = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO content_report_timeline (tenant_id, report_id, actor_user_id, event_type, event_label, event_detail, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $reportId,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            $eventType,
            $eventLabel,
            $eventDetail !== null && $eventDetail !== '' ? $eventDetail : null,
        ]);
    }

    /**
     * @param list<int> $reportIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function timelineByReportIds(int $tenantId, array $reportIds, int $limitPerReport = 8): array
    {
        $reportIds = array_values(array_unique(array_filter(array_map('intval', $reportIds), static fn (int $id): bool => $id > 0)));
        if ($reportIds === []) {
            return [];
        }
        $limitPerReport = max(1, min(20, $limitPerReport));
        $in = implode(',', array_fill(0, count($reportIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT tr.*, u.display_name AS actor_name
             FROM content_report_timeline tr
             LEFT JOIN users u ON u.id = tr.actor_user_id
             WHERE tr.tenant_id = ? AND tr.report_id IN ({$in})
             ORDER BY tr.report_id ASC, tr.id DESC"
        );
        $stmt->execute(array_merge([$tenantId], $reportIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $rid = (int) ($row['report_id'] ?? 0);
            if ($rid < 1 || count($out[$rid] ?? []) >= $limitPerReport) {
                continue;
            }
            $out[$rid][] = $row;
        }

        return $out;
    }
}
