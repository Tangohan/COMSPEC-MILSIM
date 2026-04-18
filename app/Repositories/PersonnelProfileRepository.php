<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PersonnelProfileRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function getByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM personnel_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function ensureRecord(int $userId): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO personnel_profiles (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())');
        $stmt->execute([$userId]);
    }

    public function updatePortraitPath(int $userId, ?string $path): bool
    {
        $this->ensureRecord($userId);
        $stmt = $this->pdo->prepare('UPDATE personnel_profiles SET character_portrait_path = ?, updated_at = NOW() WHERE user_id = ?');
        $stmt->execute([$path, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function update(int $userId, array $data): bool
    {
        $allowed = [
            'character_name', 'callsign', 'rank_display', 'rank_display_override', 'primary_role', 'secondary_role',
            'personnel_job_role_id', 'role_sub_label',
            'primary_unit_id', 'clearance_level', 'character_portrait_path', 'character_banner_path',
            'blood_type', 'nationality', 'languages', 'enlistment_date', 'motto',
            'readiness_score', 'command_notes', 'matricule_internal', 'clearance_reviewed_at',
            'equipment_class', 'kit_assigned', 'radio_assigned', 'vehicle_authorized', 'weapon_specialty',
            'deployable',
            'rp_followup_stage', 'rp_followup_status', 'rp_followup_progress', 'rp_tutor_user_id',
            'rp_recruitment_stream', 'rp_operational_function', 'rp_recruitment_origin',
            'rp_next_interview_date', 'rp_medical_due_date', 'rp_service_rotation_date',
            'rp_followup_notes', 'rp_eligibility_snapshot_json',
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
        $params[] = $userId;
        $this->ensureRecord($userId);
        $stmt = $this->pdo->prepare('UPDATE personnel_profiles SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE user_id = ?');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function updateMatricule(int $userId, string $matricule): bool
    {
        return $this->update($userId, ['matricule_internal' => $matricule]);
    }

    public function updateCommandNotes(int $userId, ?string $notes): bool
    {
        return $this->update($userId, ['command_notes' => $notes]);
    }
}
