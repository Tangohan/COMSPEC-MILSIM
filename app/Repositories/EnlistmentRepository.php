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
        // Colonnes de la table de base (CREATE TABLE) — toujours présentes
        $stmt = $this->pdo->prepare(
            'INSERT INTO enlistments (tenant_id, first_name, last_name, email, callsign, country, experience, specialty, platform, availability, notes, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
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
            $data['status'] ?? 'submitted',
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            $this->updateOlympusColumns($id, $data);
        }
        return $id;
    }

    /** Met à jour les colonnes Olympus (ajoutées par ALTER) si elles existent. */
    private function updateOlympusColumns(int $enlistmentId, array $data): void
    {
        $columns = [
            'age' => isset($data['age']) && $data['age'] !== '' ? (int) $data['age'] : null,
            'timezone' => $data['timezone'] ?? null,
            'weekly_availability' => $data['weekly_availability'] ?? null,
            'system_config' => $data['system_config'] ?? null,
            'microphone_quality' => $data['microphone_quality'] ?? null,
            'past_milsim_experience' => $data['past_milsim_experience'] ?? null,
            'ace_acre_level' => $data['ace_acre_level'] ?? null,
            'motivation_why_join' => $data['motivation_why_join'] ?? null,
            'motivation_accountability' => $data['motivation_accountability'] ?? null,
            'commitment_effort' => $data['commitment_effort'] ?? null,
            'availability_wed_sat' => $data['availability_wed_sat'] ?? null,
            'no_ai_confirmed' => !empty($data['no_ai_confirmed']) ? 1 : 0,
        ];
        try {
            $sets = [];
            $params = [];
            foreach ($columns as $col => $val) {
                $sets[] = "`{$col}` = ?";
                $params[] = $val;
            }
            $params[] = $enlistmentId;
            $stmt = $this->pdo->prepare('UPDATE enlistments SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $stmt->execute($params);
        } catch (\Throwable) {
            // Colonnes Olympus absentes (ALTER non exécuté) — on ignore
        }
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
