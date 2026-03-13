<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class EnlistmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO enlistments (tenant_id, first_name, last_name, email, callsign, country, experience, specialty, platform, availability, notes,
             age, timezone, weekly_availability, system_config, microphone_quality, past_milsim_experience, ace_acre_level, motivation_why_join, motivation_accountability, commitment_effort, availability_wed_sat, no_ai_confirmed,
             status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['email'] ?? '',
            $data['callsign'] ?? null,
            $data['country'] ?? null,
            $data['experience'] ?? null,
            $data['specialty'] ?? null,
            $data['platform'] ?? null,
            $data['availability'] ?? null,
            $data['notes'] ?? null,
            isset($data['age']) && $data['age'] !== '' ? (int) $data['age'] : null,
            $data['timezone'] ?? null,
            $data['weekly_availability'] ?? null,
            $data['system_config'] ?? null,
            $data['microphone_quality'] ?? null,
            $data['past_milsim_experience'] ?? null,
            $data['ace_acre_level'] ?? null,
            $data['motivation_why_join'] ?? null,
            $data['motivation_accountability'] ?? null,
            $data['commitment_effort'] ?? null,
            $data['availability_wed_sat'] ?? null,
            !empty($data['no_ai_confirmed']) ? 1 : 0,
            $data['status'] ?? 'submitted',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function allForTenant(int $tenantId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM enlistments WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
