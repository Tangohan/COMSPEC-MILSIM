<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PersonnelDeploymentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS personnel_deployments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            user_id INT NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "deployed",
            campaign_tag VARCHAR(120) NULL,
            event_id INT NULL,
            deployed_by_user_id INT NULL,
            deployed_at DATETIME NOT NULL,
            mods_up_to_date TINYINT(1) NOT NULL DEFAULT 0,
            role_qualified_authorized TINYINT(1) NOT NULL DEFAULT 0,
            recycling_alpha_bravo_up_to_date TINYINT(1) NOT NULL DEFAULT 0,
            vmp_up_to_date TINYINT(1) NOT NULL DEFAULT 0,
            last_interview_done TINYINT(1) NOT NULL DEFAULT 0,
            weight_kg DECIMAL(5,2) NULL,
            blood_type VARCHAR(12) NULL,
            matricule VARCHAR(80) NULL,
            assignment_label VARCHAR(160) NULL,
            checkup_notes TEXT NULL,
            checkup_validated_at DATETIME NULL,
            checkup_validated_by_user_id INT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_personnel_deployments_tenant_user (tenant_id, user_id),
            KEY idx_personnel_deployments_tenant_status (tenant_id, status),
            KEY idx_personnel_deployments_user (user_id),
            KEY idx_personnel_deployments_event (event_id),
            KEY idx_personnel_deployments_campaign (campaign_tag)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Compatibilité bases déjà existantes
        try {
            $this->pdo->exec('ALTER TABLE personnel_deployments ADD COLUMN campaign_tag VARCHAR(120) NULL AFTER status');
        } catch (\Throwable) {
        }
        try {
            $this->pdo->exec('ALTER TABLE personnel_deployments ADD COLUMN event_id INT NULL AFTER campaign_tag');
        } catch (\Throwable) {
        }

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS personnel_deployment_anomalies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            user_id INT NOT NULL,
            reported_by_user_id INT NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT "open",
            created_at DATETIME NOT NULL,
            KEY idx_personnel_deployment_anomalies_tenant_user (tenant_id, user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function listDeployablePersonnel(int $tenantId, string $search = '', ?string $campaignTag = null, ?int $eventId = null): array
    {
        $params = [$tenantId];
        $where = 'u.tenant_id = ? AND u.status = "active"';
        $s = trim($search);
        if ($s !== '') {
            $where .= ' AND (u.display_name LIKE ? OR u.callsign LIKE ? OR u.email LIKE ?)';
            $like = '%' . $s . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $campaign = trim((string) ($campaignTag ?? ''));
        if ($campaign !== '') {
            $where .= ' AND d.campaign_tag = ?';
            $params[] = $campaign;
        }
        if (($eventId ?? 0) > 0) {
            $where .= ' AND d.event_id = ?';
            $params[] = (int) $eventId;
        }

        $sql = 'SELECT u.id AS user_id, u.display_name, u.callsign, u.email,
                    pp.primary_role, pp.blood_type AS profile_blood_type, pp.matricule_internal, pp.primary_unit_id, pp.deployable,
                    un.name AS unit_name,
                    d.id AS deployment_id, d.status AS deployment_status, d.campaign_tag, d.event_id, d.deployed_at,
                    d.mods_up_to_date, d.role_qualified_authorized, d.recycling_alpha_bravo_up_to_date, d.vmp_up_to_date,
                    d.last_interview_done, d.weight_kg, d.blood_type, d.matricule, d.assignment_label, d.checkup_notes,
                    d.checkup_validated_at,
                    ce.title AS event_title, ce.starts_at AS event_starts_at,
                    r.status AS event_rsvp_status, r.checked_in_at AS event_checked_in_at
                FROM users u
                LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
                LEFT JOIN units un ON un.id = pp.primary_unit_id
                LEFT JOIN personnel_deployments d ON d.user_id = u.id AND d.tenant_id = u.tenant_id
                LEFT JOIN community_events ce ON ce.id = d.event_id AND ce.tenant_id = u.tenant_id
                LEFT JOIN community_event_rsvps r ON r.event_id = d.event_id AND r.user_id = u.id
                WHERE ' . $where . '
                ORDER BY COALESCE(d.updated_at, u.updated_at, u.created_at) DESC, u.display_name ASC
                LIMIT 250';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listCampaignTagsForTenant(int $tenantId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare('SELECT campaign_tag, COUNT(*) AS n
            FROM personnel_deployments
            WHERE tenant_id = ? AND campaign_tag IS NOT NULL AND campaign_tag <> ""
            GROUP BY campaign_tag
            ORDER BY MAX(updated_at) DESC
            LIMIT ' . max(1, min(80, $limit)));
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findDeployment(int $tenantId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM personnel_deployments WHERE tenant_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function upsertDeployment(int $tenantId, int $userId, int $actorUserId, array $data): void
    {
        $existing = $this->findDeployment($tenantId, $userId);

        $base = [
            'status' => (string) ($data['status'] ?? ($existing['status'] ?? 'deployed')),
            'campaign_tag' => $data['campaign_tag'] ?? null,
            'event_id' => $data['event_id'] ?? null,
            'mods_up_to_date' => !empty($data['mods_up_to_date']) ? 1 : 0,
            'role_qualified_authorized' => !empty($data['role_qualified_authorized']) ? 1 : 0,
            'recycling_alpha_bravo_up_to_date' => !empty($data['recycling_alpha_bravo_up_to_date']) ? 1 : 0,
            'vmp_up_to_date' => !empty($data['vmp_up_to_date']) ? 1 : 0,
            'last_interview_done' => !empty($data['last_interview_done']) ? 1 : 0,
            'weight_kg' => $data['weight_kg'] ?? null,
            'blood_type' => $data['blood_type'] ?? null,
            'matricule' => $data['matricule'] ?? null,
            'assignment_label' => $data['assignment_label'] ?? null,
            'checkup_notes' => $data['checkup_notes'] ?? null,
        ];

        if ($existing) {
            $stmt = $this->pdo->prepare('UPDATE personnel_deployments
                SET status = ?, campaign_tag = ?, event_id = ?, mods_up_to_date = ?, role_qualified_authorized = ?, recycling_alpha_bravo_up_to_date = ?,
                    vmp_up_to_date = ?, last_interview_done = ?, weight_kg = ?, blood_type = ?, matricule = ?,
                    assignment_label = ?, checkup_notes = ?, updated_at = NOW()
                WHERE tenant_id = ? AND user_id = ?');
            $stmt->execute([
                $base['status'],
                $base['campaign_tag'],
                $base['event_id'],
                $base['mods_up_to_date'],
                $base['role_qualified_authorized'],
                $base['recycling_alpha_bravo_up_to_date'],
                $base['vmp_up_to_date'],
                $base['last_interview_done'],
                $base['weight_kg'],
                $base['blood_type'],
                $base['matricule'],
                $base['assignment_label'],
                $base['checkup_notes'],
                $tenantId,
                $userId,
            ]);

            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO personnel_deployments
            (tenant_id, user_id, status, campaign_tag, event_id, deployed_by_user_id, deployed_at, mods_up_to_date, role_qualified_authorized,
             recycling_alpha_bravo_up_to_date, vmp_up_to_date, last_interview_done, weight_kg, blood_type, matricule,
             assignment_label, checkup_notes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([
            $tenantId,
            $userId,
            $base['status'],
            $base['campaign_tag'],
            $base['event_id'],
            $actorUserId,
            $base['mods_up_to_date'],
            $base['role_qualified_authorized'],
            $base['recycling_alpha_bravo_up_to_date'],
            $base['vmp_up_to_date'],
            $base['last_interview_done'],
            $base['weight_kg'],
            $base['blood_type'],
            $base['matricule'],
            $base['assignment_label'],
            $base['checkup_notes'],
        ]);
    }

    public function validateCheckup(int $tenantId, int $userId, int $actorUserId): void
    {
        $stmt = $this->pdo->prepare('UPDATE personnel_deployments
            SET status = "checkup_validated", checkup_validated_at = NOW(), checkup_validated_by_user_id = ?, updated_at = NOW()
            WHERE tenant_id = ? AND user_id = ?');
        $stmt->execute([$actorUserId, $tenantId, $userId]);
    }

    public function createAnomaly(int $tenantId, int $userId, int $reportedByUserId, string $message): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO personnel_deployment_anomalies
            (tenant_id, user_id, reported_by_user_id, message, status, created_at)
            VALUES (?, ?, ?, ?, "open", NOW())');
        $stmt->execute([$tenantId, $userId, $reportedByUserId, $message]);
    }

    public function listAnomalies(int $tenantId, int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('SELECT a.*, u.display_name AS reported_by_name
            FROM personnel_deployment_anomalies a
            LEFT JOIN users u ON u.id = a.reported_by_user_id
            WHERE a.tenant_id = ? AND a.user_id = ?
            ORDER BY a.created_at DESC
            LIMIT ' . max(1, min(50, $limit)));
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
