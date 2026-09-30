<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\UnitRepository;
use PDO;

/**
 * Score de disponibilité par unité : pourvu/autorisé + quals à jour + dernière activité.
 */
final class UnitReadinessService
{
    public function __construct(
        private OrbatBilletRepository $billets,
        private QualificationAwardRepository $awards,
        private QualificationTemporalStatusService $temporal,
        private ?UnitRepository $units = null,
    ) {
        $this->units ??= new UnitRepository();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scoresForTenant(int $tenantId): array
    {
        $units = [];
        try {
            $units = $this->units->listFlatForStructure($tenantId);
        } catch (\Throwable) {
            $units = $this->fallbackUnits($tenantId);
        }
        $out = [];
        foreach ($units as $unit) {
            $unitId = (int) ($unit['id'] ?? 0);
            if ($unitId < 1) {
                continue;
            }
            $out[] = $this->scoreForUnit($tenantId, $unit);
        }
        usort($out, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return $out;
    }

    /**
     * @param array<string, mixed> $unit
     * @return array<string, mixed>
     */
    public function scoreForUnit(int $tenantId, array $unit): array
    {
        $unitId = (int) ($unit['id'] ?? 0);
        $manning = ['authorized' => 0, 'filled' => 0, 'vacant' => 0, 'billets' => []];
        try {
            $manning = $this->billets->manningForUnit($tenantId, $unitId);
        } catch (\Throwable) {
        }
        $authorized = max(0, (int) ($manning['authorized'] ?? 0));
        $filled = max(0, (int) ($manning['filled'] ?? 0));
        $fillRatio = $authorized > 0 ? $filled / $authorized : ($filled > 0 ? 1.0 : 0.0);

        $memberIds = [];
        foreach ($manning['billets'] ?? [] as $b) {
            foreach ($b['holders'] ?? [] as $h) {
                $uid = (int) ($h['user_id'] ?? $h['personnel_id'] ?? 0);
                if ($uid > 0) {
                    $memberIds[$uid] = $uid;
                }
            }
        }
        if ($memberIds === []) {
            $memberIds = $this->memberIdsForUnit($tenantId, $unitId);
        }

        $qualOk = 0;
        $qualTotal = 0;
        $lastActivity = null;
        foreach ($memberIds as $uid) {
            try {
                $rows = $this->awards->listForUser((int) $uid, $tenantId);
            } catch (\Throwable) {
                $rows = [];
            }
            foreach ($rows as $row) {
                $qualTotal++;
                if ($this->temporal->isEffectivelyActive($row)) {
                    $qualOk++;
                }
            }
            $act = $this->lastActivityForUser($tenantId, (int) $uid);
            if ($act !== null && ($lastActivity === null || $act > $lastActivity)) {
                $lastActivity = $act;
            }
        }
        $qualRatio = $qualTotal > 0 ? $qualOk / $qualTotal : 1.0;

        $activityScore = 0.5;
        if ($lastActivity !== null) {
            $days = (int) floor((time() - strtotime($lastActivity)) / 86400);
            $activityScore = $days <= 7 ? 1.0 : ($days <= 30 ? 0.75 : ($days <= 90 ? 0.45 : 0.2));
        }

        $score = (int) round(100 * (0.45 * $fillRatio + 0.40 * $qualRatio + 0.15 * $activityScore));

        return [
            'unit_id' => $unitId,
            'name' => (string) ($unit['name'] ?? 'Unité'),
            'authorized' => $authorized,
            'filled' => $filled,
            'vacant' => max(0, $authorized - $filled),
            'qual_ok' => $qualOk,
            'qual_total' => $qualTotal,
            'last_activity' => $lastActivity,
            'score' => max(0, min(100, $score)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackUnits(int $tenantId): array
    {
        try {
            $st = Database::getPdo()->prepare(
                'SELECT id, name FROM units WHERE tenant_id = ? ORDER BY name ASC'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, int> */
    private function memberIdsForUnit(int $tenantId, int $unitId): array
    {
        try {
            $st = Database::getPdo()->prepare(
                'SELECT DISTINCT user_id FROM users WHERE tenant_id = ? AND unit_id = ? AND status = \'active\''
            );
            $st->execute([$tenantId, $unitId]);
            $ids = [];
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
                $ids[(int) $id] = (int) $id;
            }

            return $ids;
        } catch (\Throwable) {
            return [];
        }
    }

    private function lastActivityForUser(int $tenantId, int $userId): ?string
    {
        $pdo = Database::getPdo();
        foreach ([
            'SELECT MAX(COALESCE(effective_at, created_at)) FROM personnel_career_journal WHERE tenant_id = ? AND user_id = ?',
            'SELECT MAX(last_login_at) FROM users WHERE tenant_id = ? AND id = ?',
        ] as $sql) {
            try {
                $st = $pdo->prepare($sql);
                $st->execute([$tenantId, $userId]);
                $v = $st->fetchColumn();
                if (is_string($v) && $v !== '' && !str_starts_with($v, '0000')) {
                    return $v;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
