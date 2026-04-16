<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Campagnes de publication (suite ordonnée de jobs `deployment_jobs`).
 */
final class DeploymentCampaignRepository
{
    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deployment_campaigns' LIMIT 1"
            );
            if (!$st || !$st->fetchColumn()) {
                return false;
            }
            $st2 = $this->pdo()->query(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deployment_jobs' AND COLUMN_NAME = 'campaign_id' LIMIT 1"
            );

            return (bool) ($st2 && $st2->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<int> $channelIdsOrdered étapes dans l’ordre d’exécution (tri canal déjà fait côté appelant).
     */
    public function createCampaignWithJobs(
        int $moduleId,
        int $moduleVersionId,
        ?int $triggeredBy,
        array $channelIdsOrdered,
    ): int {
        if ($moduleId < 1 || $moduleVersionId < 1 || $channelIdsOrdered === []) {
            throw new \InvalidArgumentException('Campagne invalide.');
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'INSERT INTO deployment_campaigns (module_id, module_version_id, triggered_by, status) VALUES (?,?,?,\'queued\')'
            );
            $st->execute([$moduleId, $moduleVersionId, $triggeredBy !== null && $triggeredBy > 0 ? $triggeredBy : null]);
            $campaignId = (int) $pdo->lastInsertId();
            if ($campaignId < 1) {
                throw new \RuntimeException('Création de campagne impossible.');
            }
            $ins = $pdo->prepare(
                'INSERT INTO deployment_jobs (campaign_id, module_version_id, target_channel_id, step_order, status, triggered_by)
                 VALUES (?,?,?,?,\'queued\',?)'
            );
            $step = 1;
            foreach ($channelIdsOrdered as $cid) {
                $cid = (int) $cid;
                if ($cid < 1) {
                    continue;
                }
                $ins->execute([
                    $campaignId,
                    $moduleVersionId,
                    $cid,
                    $step,
                    $triggeredBy !== null && $triggeredBy > 0 ? $triggeredBy : null,
                ]);
                ++$step;
            }
            $pdo->commit();

            return $campaignId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    public function findCampaign(int $id): ?array
    {
        if (!$this->schemaReady() || $id < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT c.*, pm.name AS module_name, pm.code AS module_code, mv.version AS version_label
             FROM deployment_campaigns c
             INNER JOIN platform_modules pm ON pm.id = c.module_id
             INNER JOIN platform_module_versions mv ON mv.id = c.module_version_id
             WHERE c.id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listCampaignsRecent(int $limit = 40): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo()->prepare(
            "SELECT c.id, c.status, c.created_at, c.finished_at, c.module_id, c.module_version_id,
                    pm.name AS module_name, mv.version AS version_label
             FROM deployment_campaigns c
             INNER JOIN platform_modules pm ON pm.id = c.module_id
             INNER JOIN platform_module_versions mv ON mv.id = c.module_version_id
             ORDER BY c.id DESC
             LIMIT {$limit}"
        );
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listJobsForCampaign(int $campaignId): array
    {
        if (!$this->schemaReady() || $campaignId < 1) {
            return [];
        }
        $sql = 'SELECT dj.*, dc.code AS channel_code, dc.name AS channel_name
                FROM deployment_jobs dj
                INNER JOIN deployment_channels dc ON dc.id = dj.target_channel_id
                WHERE dj.campaign_id = ?
                ORDER BY dj.step_order ASC, dj.id ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$campaignId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function updateCampaignStatus(int $campaignId, string $status): void
    {
        $allowed = ['queued', 'in_progress', 'completed', 'failed', 'cancelled'];
        if (!in_array($status, $allowed, true) || $campaignId < 1) {
            return;
        }
        $st = $this->pdo()->prepare('UPDATE deployment_campaigns SET status = ? WHERE id = ?');
        $st->execute([$status, $campaignId]);
    }

    public function markCampaignFinished(int $campaignId, string $finalStatus): void
    {
        $allowed = ['completed', 'failed', 'cancelled'];
        if (!in_array($finalStatus, $allowed, true) || $campaignId < 1) {
            return;
        }
        $st = $this->pdo()->prepare(
            'UPDATE deployment_campaigns SET status = ?, finished_at = NOW() WHERE id = ?'
        );
        $st->execute([$finalStatus, $campaignId]);
    }

    public function markJobRunning(int $jobId): bool
    {
        if ($jobId < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            "UPDATE deployment_jobs SET status = 'running', started_at = NOW() WHERE id = ? AND status = 'queued'"
        );
        $st->execute([$jobId]);

        return $st->rowCount() > 0;
    }

    public function markJobSuccess(int $jobId): void
    {
        if ($jobId < 1) {
            return;
        }
        $st = $this->pdo()->prepare(
            "UPDATE deployment_jobs SET status = 'success', finished_at = NOW() WHERE id = ?"
        );
        $st->execute([$jobId]);
    }

    public function markJobFailed(int $jobId, string $userSafeMessage): void
    {
        if ($jobId < 1) {
            return;
        }
        $msg = mb_substr(trim($userSafeMessage), 0, 2000);
        $st = $this->pdo()->prepare(
            'UPDATE deployment_jobs SET status = \'failed\', finished_at = NOW(), error_message = ? WHERE id = ?'
        );
        $st->execute([$msg !== '' ? $msg : 'Échec.', $jobId]);
    }

    /** @return array<string, int> */
    public function countJobStatusesForCampaign(int $campaignId): array
    {
        if (!$this->schemaReady() || $campaignId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT status, COUNT(*) AS n FROM deployment_jobs WHERE campaign_id = ? GROUP BY status'
        );
        $st->execute([$campaignId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(string) ($row['status'] ?? '')] = (int) ($row['n'] ?? 0);
        }

        return $out;
    }
}
