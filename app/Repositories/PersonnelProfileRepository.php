<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Session;
use App\Services\Identity\UserIdentityMergeRules;
use PDO;

class PersonnelProfileRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function getByUserId(int $userId, ?int $tenantId = null): ?array
    {
        if ($userId < 1) {
            return null;
        }
        $preferredTenantId = ($tenantId !== null && $tenantId > 0)
            ? $tenantId
            : ($this->preferredTenantId($userId) ?? 0);
        if ($this->hasTenantIdColumn()) {
            $stmt = $this->pdo->prepare('SELECT * FROM personnel_profiles WHERE user_id = ?');
            $stmt->execute([$userId]);
            $row = UserIdentityMergeRules::pickPreferredDossierRow(
                $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
                $preferredTenantId
            );
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM personnel_profiles WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$row) {
            return null;
        }

        return $this->withPrimaryJobRoleBridge($row);
    }

    private function hasTenantIdColumn(): bool
    {
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_profiles' AND COLUMN_NAME = 'tenant_id' LIMIT 1"
        );

        return $st !== false && (bool) $st->fetchColumn();
    }

    /**
     * Compatibilité en lecture : `primary_role` / `personnel_job_role_id` / `role_sub_label` n'existent
     * plus comme colonnes (fusionnées dans la table pivot personnel_profile_job_roles). Les appelants
     * historiques qui lisent ces clés sur le tableau retourné par getByUserId() continuent de fonctionner,
     * alimentés depuis le rôle métier principal de la pivot.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function withPrimaryJobRoleBridge(array $row): array
    {
        if (array_key_exists('primary_role', $row)) {
            // Colonnes encore présentes (migration pas encore appliquée sur cet environnement) : ne rien changer.
            return $row;
        }
        $userId = (int) ($row['user_id'] ?? 0);
        $row['primary_role'] = '';
        $row['personnel_job_role_id'] = null;
        $row['role_sub_label'] = '';
        if ($userId < 1) {
            return $row;
        }
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            $tenantId = $this->preferredTenantId($userId) ?? 0;
        }
        if ($tenantId < 1) {
            return $row;
        }
        $stmt = $this->pdo->prepare(
            'SELECT pj.personnel_job_role_id, pj.role_detail, r.name AS role_name
             FROM personnel_profile_job_roles pj
             INNER JOIN personnel_job_roles r ON r.id = pj.personnel_job_role_id AND r.tenant_id = pj.tenant_id
             WHERE pj.tenant_id = ? AND pj.user_id = ?
             ORDER BY pj.is_primary DESC, pj.sort_order ASC, pj.id ASC
             LIMIT 1'
        );
        try {
            $stmt->execute([$tenantId, $userId]);
        } catch (\Throwable) {
            return $row;
        }
        $pr = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pr) {
            return $row;
        }
        $name = trim((string) ($pr['role_name'] ?? ''));
        $detail = trim((string) ($pr['role_detail'] ?? ''));
        $row['personnel_job_role_id'] = (int) $pr['personnel_job_role_id'];
        $row['role_sub_label'] = $detail;
        $row['primary_role'] = $detail !== '' && $name !== '' ? $name . ' — ' . $detail : ($name !== '' ? $name : $detail);

        return $row;
    }

    public function ensureRecord(int $userId, ?int $tenantId = null): void
    {
        if ($tenantId !== null && $tenantId > 0 && $this->hasTenantIdColumn()) {
            $stmt = $this->pdo->prepare(
                'INSERT IGNORE INTO personnel_profiles (user_id, tenant_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
            );
            $stmt->execute([$userId, $tenantId]);

            return;
        }
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO personnel_profiles (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())');
        $stmt->execute([$userId]);
    }

    /** RGPD / soft-delete : efface le dossier personnel (noms RP, matricule, notes…). */
    public function deleteByUserId(int $userId): void
    {
        if ($userId < 1) {
            return;
        }
        try {
            $this->pdo->prepare('DELETE FROM personnel_profile_job_roles WHERE user_id = ?')->execute([$userId]);
        } catch (\Throwable) {
        }
        $stmt = $this->pdo->prepare('DELETE FROM personnel_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public function updatePortraitPath(int $userId, ?string $path): bool
    {
        $tenantId = $this->preferredTenantId($userId);
        $this->ensureRecord($userId, $tenantId);
        $sql = 'UPDATE personnel_profiles SET character_portrait_path = ?, updated_at = NOW() WHERE user_id = ?';
        $params = [$path, $userId];
        if ($tenantId !== null && $this->hasTenantIdColumn()) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function update(int $userId, array $data): bool
    {
        $allowed = [
            'character_name', 'callsign', 'extra_callsigns_json', 'nickname_primary', 'nicknames_json', 'medal_rack_json',
            'rank_display', 'rank_display_override',
            'primary_unit_id', 'clearance_level', 'character_portrait_path', 'character_portrait_locked', 'character_banner_path',
            'blood_type', 'nationality', 'languages', 'enlistment_date', 'motto',
            'sex', 'family_situation', 'weight_kg', 'operator_status', 'operator_tags',
            'service_branch', 'birth_place', 'service_status', 'gendarmerie_status', 'administrative_position',
            'bureau_sn', 'military_origin', 'statutory_limit_date', 'management_service_limit_date',
            'readiness_score', 'command_notes', 'matricule_internal', 'clearance_reviewed_at',
            'equipment_class', 'kit_assigned', 'radio_assigned', 'vehicle_authorized', 'weapon_specialty',
            'deployable',
            'rp_followup_stage', 'rp_followup_status', 'rp_followup_progress', 'rp_tutor_user_id',
            'rp_recruitment_stream', 'rp_operational_function', 'rp_recruitment_origin',
            'rp_next_interview_date', 'rp_medical_due_date', 'rp_service_rotation_date',
            'rp_followup_notes', 'rp_eligibility_snapshot_json', 'rp_last_review_at',
            'rp_last_interview_completed_at', 'rp_last_rotation_completed_at', 'rp_rotation_kind',
            'rp_blood_type_confirmed', 'rp_blood_type_confirmed_at',
            'rp_arma_blood_type', 'rp_arma_blood_type_at',
        ];
        $set = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $set[] = "`$key` = ?";
                $params[] = $data[$key];
            }
        }
        if (empty($set)) {
            return true;
        }
        $tenantId = $this->preferredTenantId($userId);
        $this->ensureRecord($userId, $tenantId);
        $sql = 'UPDATE personnel_profiles SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE user_id = ?';
        $params[] = $userId;
        if ($tenantId !== null && $this->hasTenantIdColumn()) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    private function preferredTenantId(int $userId): ?int
    {
        $sessionTid = 0;
        try {
            $sessionTid = (int) Session::get('tenant_id', 0);
        } catch (\Throwable) {
            $sessionTid = 0;
        }
        if ($sessionTid > 0) {
            return $sessionTid;
        }
        $st = $this->pdo->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $tid = (int) $st->fetchColumn();

        return $tid > 0 ? $tid : null;
    }

    public function updateMatricule(int $userId, string $matricule): bool
    {
        return $this->update($userId, ['matricule_internal' => $matricule]);
    }

    public function updateCommandNotes(int $userId, ?string $notes): bool
    {
        return $this->update($userId, ['command_notes' => $notes]);
    }

    /**
     * Compte les comptes actifs dont le dossier personnel est manifestement incomplet
     * (proxy léger sur 3 champs clés — pas la même heuristique à 11 critères que le
     * tableur RH, juste un indicateur agrégé pour le digest hebdomadaire, une seule requête).
     */
    public function countIncompleteForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users u
             LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
             WHERE u.tenant_id = ? AND u.status = 'active' AND (
                pp.user_id IS NULL
                OR TRIM(COALESCE(pp.character_name, '')) = ''
                OR TRIM(COALESCE(pp.matricule_internal, '')) = ''
                OR pp.primary_unit_id IS NULL
             )"
        );
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Compte les comptes actifs ayant une habilitation accordée dont la revue est absente
     * ou périmée (au-delà de $thresholdDays) — une seule requête, pas de N+1.
     */
    public function countOverdueClearanceReviewForTenant(int $tenantId, int $thresholdDays): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users u
             INNER JOIN personnel_profiles pp ON pp.user_id = u.id
             WHERE u.tenant_id = ? AND u.status = 'active'
               AND TRIM(COALESCE(pp.clearance_level, '')) <> ''
               AND (pp.clearance_reviewed_at IS NULL OR pp.clearance_reviewed_at < DATE_SUB(NOW(), INTERVAL ? DAY))"
        );
        $stmt->execute([$tenantId, $thresholdDays]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Membres actifs dont le bilan roleplay est dû, cadence App\Support\RoleplayBilanPolicy
     * (6/8/12 mois selon ancienneté depuis users.created_at). Une seule requête, pas de N+1.
     *
     * @return list<array{user_id: int, email: string, display_name: string, callsign: string, joined_at: string, rp_last_review_at: ?string, rp_tutor_user_id: ?int, next_due_at: string, is_overdue: int}>
     */
    public function listRoleplayBilanDueForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id AS user_id, u.email, u.display_name, u.callsign, u.created_at AS joined_at,
                    pp.rp_last_review_at, pp.rp_tutor_user_id,
                    DATE_ADD(COALESCE(pp.rp_last_review_at, u.created_at), INTERVAL
                        CASE
                            WHEN DATEDIFF(NOW(), u.created_at) < 365 THEN " . \App\Support\RoleplayBilanPolicy::FIRST_YEAR_INTERVAL_DAYS . '
                            WHEN DATEDIFF(NOW(), u.created_at) < 730 THEN ' . \App\Support\RoleplayBilanPolicy::SECOND_YEAR_INTERVAL_DAYS . "
                            ELSE " . \App\Support\RoleplayBilanPolicy::ONGOING_INTERVAL_DAYS . "
                        END DAY
                    ) AS next_due_at,
                    CASE WHEN DATE_ADD(COALESCE(pp.rp_last_review_at, u.created_at), INTERVAL
                        CASE
                            WHEN DATEDIFF(NOW(), u.created_at) < 365 THEN " . \App\Support\RoleplayBilanPolicy::FIRST_YEAR_INTERVAL_DAYS . '
                            WHEN DATEDIFF(NOW(), u.created_at) < 730 THEN ' . \App\Support\RoleplayBilanPolicy::SECOND_YEAR_INTERVAL_DAYS . "
                            ELSE " . \App\Support\RoleplayBilanPolicy::ONGOING_INTERVAL_DAYS . '
                        END DAY
                    ) < DATE_SUB(NOW(), INTERVAL ' . \App\Support\RoleplayBilanPolicy::OVERDUE_GRACE_DAYS . " DAY) THEN 1 ELSE 0 END AS is_overdue
             FROM users u
             INNER JOIN personnel_profiles pp ON pp.user_id = u.id
             WHERE u.tenant_id = ? AND u.status = 'active'
             HAVING next_due_at <= NOW()
             ORDER BY next_due_at ASC"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
