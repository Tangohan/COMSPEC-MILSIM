<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class EnlistmentRepository
{
    private PDO $pdo;

    private static ?bool $hasAccountColumns = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasAccountColumns(): bool
    {
        if (self::$hasAccountColumns === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'submitted_via' LIMIT 1");
            self::$hasAccountColumns = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasAccountColumns;
    }

    public function create(int $tenantId, array $data): int
    {
        $status = $data['status'] ?? 'submitted';
        $baseParams = [
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
            $status,
        ];

        if ($this->hasAccountColumns()) {
            $shared = null;
            if (!empty($data['shared_fields']) && is_array($data['shared_fields'])) {
                $shared = json_encode($data['shared_fields'], JSON_UNESCAPED_UNICODE);
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistments (tenant_id, first_name, last_name, email, callsign, country, experience, specialty, platform, availability, notes, status, submitter_user_id, recruitment_preset_id, submitted_via, consent_sharing_at, shared_fields, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                ...$baseParams,
                isset($data['submitter_user_id']) && $data['submitter_user_id'] !== '' ? (int) $data['submitter_user_id'] : null,
                isset($data['recruitment_preset_id']) && $data['recruitment_preset_id'] !== '' ? (int) $data['recruitment_preset_id'] : null,
                $data['submitted_via'] ?? 'guest',
                $data['consent_sharing_at'] ?? null,
                $shared,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistments (tenant_id, first_name, last_name, email, callsign, country, experience, specialty, platform, availability, notes, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute($baseParams);
        }
        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            $this->updateOlympusColumns($id, $data);
            if (!empty($data['recruitment_rp_snapshot']) && is_array($data['recruitment_rp_snapshot'])) {
                $this->updateRecruitmentRpJsonColumn($id, $data['recruitment_rp_snapshot']);
            }
        }
        return $id;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function updateRecruitmentRpJsonColumn(int $enlistmentId, array $snapshot): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE enlistments SET recruitment_rp_json = ? WHERE id = ?');
            $stmt->execute([json_encode($snapshot, JSON_UNESCAPED_UNICODE), $enlistmentId]);
        } catch (\Throwable) {
            // Colonne absente si migration non exécutée
        }
    }

    public function findForTenant(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM enlistments WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['recruitment_rp_json'])) {
            if (is_string($row['recruitment_rp_json'])) {
                $d = json_decode($row['recruitment_rp_json'], true);
                $row['recruitment_rp_json'] = is_array($d) ? $d : null;
            } elseif (!is_array($row['recruitment_rp_json'])) {
                $row['recruitment_rp_json'] = null;
            }
        } else {
            $row['recruitment_rp_json'] = null;
        }

        return $row;
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
